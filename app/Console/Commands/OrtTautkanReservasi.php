<?php

namespace App\Console\Commands;

use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menautkan reservasi yang SUDAH ADA ke baris kontrol ortho.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KENAPA PERINTAH INI ADA
 * ────────────────────────────────────────────────────────────────────────────
 * Diukur di prod (audit HUB 1 Okt): dari 2.521 kunjungan ortho Jul–Sep, **91%
 * punya reservasi** — 73%–100% tergantung dokter. Kontrol ortho BUKAN walk-in.
 *
 * Tapi di tabel kita `ort_control.reservation_id` terisi **0 dari 35.317**.
 * Satu-satunya yang pernah mengisinya adalah Observer, dan ia hanya bekerja
 * untuk reservasi yang dibuat LEWAT modul ortho, ke depan. Semua reservasi yang
 * sudah ada sejak dulu tidak pernah tersentuh.
 *
 * Akibatnya kolom RESERVASI menampilkan `X` untuk semua orang — bukan sekadar
 * kosong, melainkan **membuang data milik kita sendiri**, lengkap dengan
 * dokter, cabang, jam, dan status kehadirannya.
 *
 * Ini bukan fitur baru. Ini menjodohkan dua tabel yang sama-sama kita punya.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * ATURAN PENJODOHAN — dan kenapa begitu
 * ────────────────────────────────────────────────────────────────────────────
 * ⚠ Dicocokkan ke `raw_cus_reservation.date` (tanggal JANJI), **BUKAN**
 *   `created_at`. Dikonfirmasi Ikhsan: pasien memesan tanggal 13 untuk janji
 *   tanggal 20, lalu datang tanggal 20. Mencocokkan ke tanggal pesan akan
 *   meleset sistematis sebanyak jeda pemesanan.
 *
 * ⚠ Toleransi ±3 hari, terbukti pas di prod. Reservasi digeser sehari-dua
 *   itu biasa; lebih longgar dari itu mulai menyambar reservasi tetangga.
 *
 * ⚠ Pasien yang belum tertaut `raw_customer` DIBIARKAN KOSONG. 85% sudah
 *   tertaut; sisanya tidak ditebak — kolom kosong jauh lebih murah daripada
 *   reservasi orang lain yang menempel di riwayat pasien ini.
 *
 * Satu reservasi hanya boleh menempel pada SATU kontrol (uq idx_ort_control_rsv
 * secara semantik). Yang sudah terpakai tidak dipakai ulang, termasuk yang
 * terpakai di tengah jalannya perintah ini.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * Menulis BUTUH `--commit`. Tanpa itu: memindai, melaporkan, lalu berhenti.
 * Pengaman yang harus diingat untuk dinyalakan adalah pengaman yang suatu hari
 * lupa dinyalakan.
 */
class OrtTautkanReservasi extends Command
{
    protected $signature = 'ort:tautkan-reservasi
                            {--sejak= : batasi ke kunjungan sejak tanggal ini}
                            {--sampai= : batasi ke kunjungan sampai tanggal ini}
                            {--cabang= : batasi ke satu place_id}
                            {--toleransi=3 : selisih hari maksimum antara tanggal janji dan tanggal datang}
                            {--commit : benar-benar menulis. TANPA ini tidak ada yang ditulis}
                            {--contoh=10 : berapa pasangan dicetak untuk diperiksa tangan}';

    protected $description = 'Tautkan reservasi yang sudah ada ke baris kontrol ortho';

    /** Jejak, supaya bisa dibedakan dari tautan buatan Observer. */
    private const PENULIS = 'ort:tautkan-reservasi';

    private array $n = [];

    /** Reservasi yang sudah terpakai dalam jalannya perintah ini. */
    private array $terpakai = [];

    private array $contoh = [];

    public function __construct(private OrtBranches $branches)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        foreach (['ort_control', 'ort_case', 'raw_cus_reservation'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->error("Tabel {$t} belum ada.");

                return self::FAILURE;
            }
        }

        $tulis     = (bool) $this->option('commit');
        $toleransi = max(0, (int) $this->option('toleransi'));

        $this->n = array_fill_keys([
            'diperiksa', 'tanpa_customer', 'tanpa_calon', 'tertaut', 'bentrok',
        ], 0);

        $this->info(sprintf(
            '%s · toleransi ±%d hari%s',
            $tulis ? 'PENJODOHAN (menulis):' : 'PRATINJAU (tidak menulis):',
            $toleransi,
            $this->option('cabang') ? ' · cabang ' . $this->branches->nama($this->option('cabang')) : ''
        ));
        $this->newLine();

        $this->sapu($tulis, $toleransi);
        $this->laporkan($tulis);

        return self::SUCCESS;
    }

    // ════════════════════════════════════════════════════════════════════════

    private function sapu(bool $tulis, int $toleransi): void
    {
        $q = DB::table('ort_control as c')
            ->join('ort_case as k', 'k.id', '=', 'c.ort_case_id')   // sekeluarga — aman
            ->whereNotNull('c.visit_date')
            ->whereNull('c.reservation_id');

        if ($this->option('cabang')) {
            $q->where('c.place_id', (string) $this->option('cabang'));
        }
        if ($this->option('sejak')) {
            $q->where('c.visit_date', '>=', Carbon::parse($this->option('sejak'))->toDateString());
        }
        if ($this->option('sampai')) {
            $q->where('c.visit_date', '<=', Carbon::parse($this->option('sampai'))->toDateString());
        }

        // Digarap per gelombang: identitas dan reservasi diambil sekali untuk
        // ratusan kontrol, bukan dua kueri per baris.
        $q->orderBy('c.ort_case_id')->orderBy('c.visit_date')
            ->select('c.id', 'c.ort_case_id', 'c.place_id', 'c.visit_date',
                     'k.omnicare_pid', 'k.raw_customer_id')
            ->chunk(300, function ($baris) use ($tulis, $toleransi) {
                $this->olahGelombang($baris, $tulis, $toleransi);
            });
    }

    private function olahGelombang($baris, bool $tulis, int $toleransi): void
    {
        $this->n['diperiksa'] += count($baris);

        // ── Identitas: raw_customer_id, dengan cadangan lewat tautan ────────
        // ort_case.raw_customer_id diisi penyemai, tapi tautan bisa lahir
        // SESUDAH penyemaian. Jadi yang kosong dicoba sekali lagi lewat
        // customer_omnicare_link — nol join, whereIn dengan literal PHP.
        $pelanggan = [];
        $pidPerlu  = [];
        foreach ($baris as $r) {
            if (filled($r->raw_customer_id)) {
                $pelanggan[$r->id] = (string) $r->raw_customer_id;
            } elseif (filled($r->omnicare_pid)) {
                $pidPerlu[(string) $r->omnicare_pid][] = $r->id;
            }
        }

        if ($pidPerlu && Schema::hasTable('customer_omnicare_link')) {
            $tautan = DB::table('customer_omnicare_link')
                ->whereIn('omnicare_pid', array_keys($pidPerlu))
                ->pluck('raw_customer_id', 'omnicare_pid');

            foreach ($pidPerlu as $pid => $ids) {
                if (filled($tautan[$pid] ?? null)) {
                    foreach ($ids as $id) {
                        $pelanggan[$id] = (string) $tautan[$pid];
                    }
                }
            }
        }

        // ── Calon reservasi untuk seluruh gelombang, sekali ambil ───────────
        $custIds = array_values(array_unique($pelanggan));
        if (! $custIds) {
            $this->n['tanpa_customer'] += count($baris);

            return;
        }

        $tgl    = collect($baris)->pluck('visit_date')->filter()->map(fn ($d) => substr((string) $d, 0, 10));
        $sejak  = Carbon::parse($tgl->min())->subDays($toleransi)->toDateString();
        $sampai = Carbon::parse($tgl->max())->addDays($toleransi)->toDateString();

        $rsv = DB::table('raw_cus_reservation')
            ->whereIn('raw_customer_id', $custIds)
            ->whereBetween('date', [$sejak, $sampai])
            ->get(['id', 'raw_customer_id', 'raw_branch_id', 'date', 'status'])
            ->groupBy('raw_customer_id');

        // Reservasi yang SUDAH menempel di kontrol lain tidak boleh dipakai lagi.
        $sudahDipakai = DB::table('ort_control')
            ->whereIn('reservation_id', $rsv->flatten(1)->pluck('id')->all() ?: ['-'])
            ->pluck('reservation_id')->all();
        foreach ($sudahDipakai as $id) {
            $this->terpakai[(string) $id] = true;
        }

        foreach ($baris as $r) {
            if (! isset($pelanggan[$r->id])) {
                $this->n['tanpa_customer']++;
                continue;
            }

            $pilih = $this->calonTerbaik(
                $rsv->get($pelanggan[$r->id], collect()),
                substr((string) $r->visit_date, 0, 10),
                (string) $r->place_id,
                $toleransi
            );

            if (! $pilih) {
                $this->n['tanpa_calon']++;
                continue;
            }

            $this->terpakai[(string) $pilih->id] = true;
            $this->n['tertaut']++;

            if (count($this->contoh) < (int) $this->option('contoh')) {
                $this->contoh[] = [
                    substr((string) $r->visit_date, 0, 10),
                    substr((string) $pilih->date, 0, 10),
                    (int) Carbon::parse($pilih->date)->diffInDays(Carbon::parse($r->visit_date), false),
                    $pilih->status,
                    (string) $pilih->raw_branch_id === (string) $this->branches->branchId($r->place_id) ? 'sama' : 'beda',
                ];
            }

            if ($tulis) {
                OrtControl::where('id', $r->id)->update([
                    'reservation_id' => $pilih->id,
                    'rsv_date'       => substr((string) $pilih->date, 0, 10),
                    'rsv_status'     => $pilih->status,
                    'rsv_synced_at'  => now(),
                    'updated_by'     => self::PENULIS,
                ]);
            }
        }
    }

    /**
     * Reservasi paling masuk akal untuk satu kunjungan.
     *
     * Urutan keputusan, dan semuanya DETERMINISTIK — dijalankan dua kali atas
     * data yang sama harus memilih baris yang sama:
     *   1. selisih hari terkecil terhadap tanggal datang
     *   2. cabangnya sama dengan cabang kontrol
     *   3. id, sebagai pemecah seri terakhir
     *
     * Cabang jadi penimbang KEDUA, bukan syarat: pasien lintas cabang itu
     * nyata (104 orang di prod), dan membuang reservasi mereka karena
     * cabangnya beda sama saja mengulang lubang yang sedang kita tambal.
     */
    private function calonTerbaik($calon, string $visitDate, string $placeId, int $toleransi)
    {
        $branchId = $this->branches->branchId($placeId);
        $datang   = Carbon::parse($visitDate);

        $layak = $calon
            ->reject(fn ($r) => isset($this->terpakai[(string) $r->id]))
            ->filter(fn ($r) => filled($r->date)
                && abs(Carbon::parse($r->date)->diffInDays($datang)) <= $toleransi);

        if ($layak->isEmpty()) {
            return null;
        }

        if ($layak->count() > 1) {
            $this->n['bentrok']++;
        }

        return $layak->sortBy(fn ($r) => [
            abs(Carbon::parse($r->date)->diffInDays($datang)),
            (string) $r->raw_branch_id === (string) $branchId ? 0 : 1,
            (string) $r->id,
        ])->first();
    }

    // ════════════════════════════════════════════════════════════════════════

    private function laporkan(bool $tulis): void
    {
        $n = $this->n;

        if ($this->contoh) {
            $this->line('Contoh pasangan — PERIKSA TANGAN sebelum --commit:');
            $this->table(
                ['Datang', 'Janji reservasi', 'Selisih hari', 'Status', 'Cabang'],
                $this->contoh
            );
            $this->newLine();
        }

        $this->table(['Hasil', 'Jumlah'], [
            ['Kontrol berkunjungan diperiksa',      $n['diperiksa']],
            [$tulis ? 'Tertaut' : 'Akan ditaut',    $n['tertaut']],
            ['→ pasien belum tertaut raw_customer', $n['tanpa_customer']],
            ['→ tidak ada reservasi di rentangnya', $n['tanpa_calon']],
            ['Lebih dari satu calon (dipilih terdekat)', $n['bentrok']],
        ]);

        if ($n['diperiksa'] > 0) {
            $persen = round($n['tertaut'] / $n['diperiksa'] * 100);
            $this->newLine();
            $this->line("Cakupan: {$persen}% kunjungan menemukan reservasinya.");

            // Prod mengukur 91%. Jauh di bawah itu berarti ada yang salah di
            // jembatannya — bukan berarti pasiennya memang walk-in.
            if ($persen < 60) {
                $this->warn('Prod mengukur 91%. Angka jauh di bawah itu biasanya tanda');
                $this->warn('tautan customer yang belum lengkap, bukan pasien walk-in —');
                $this->warn('periksa customer_omnicare_link sebelum mempercayai hasilnya.');
            }
        }

        if (! $tulis) {
            $this->newLine();
            $this->warn('PRATINJAU — tidak ada satu baris pun yang ditulis. Tambahkan --commit.');
        }
    }
}
