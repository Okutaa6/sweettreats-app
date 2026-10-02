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
 *
 * ───────────────────────────────────
 * TIGA JENIS BARIS, TIGA ATURAN — sejak 2026-10-04
 * ───────────────────────────────────
 * Versi pertama hanya menyentuh baris ber-`visit_date`. Itu melewati persis
 * baris yang dilihat Sales di tab List: kontrol TERBUKA yang punya reservasi
 * mendatang (dibuat dari modul Reservasi biasa, jadi Observer tidak pernah
 * menemukannya) — dan baris `tidak_hadir`, tempat reservasi yang meleset atau
 * dibatalkan justru berada. Akibatnya tab "Tanggal reservasi" berbunyi
 * "Tidak ada reservasi" untuk hampir semua orang, padahal reservasinya ada.
 *
 *   RIWAYAT    visit_date terisi      jangkar visit_date ± toleransi
 *                                     (aturan asli, TIDAK berubah)
 *   TIDAK HADIR outcome=tidak_hadir,  jangkar sch_date ± toleransi — janji
 *              visit_date kosong      yang meleset; status apa pun, termasuk
 *                                     cancelled, karena itulah ceritanya
 *   TERBUKA    closed_at kosong       reservasi MENDATANG (≥ hari ini, sampai
 *                                     HORIZON_TERBUKA hari) milik pasien itu.
 *                                     Tanpa jendela toleransi: satu kasus
 *                                     hanya punya satu kontrol terbuka, jadi
 *                                     "pasien ini punya reservasi mendatang"
 *                                     sudah tidak ambigu. Cancelled DIBUANG —
 *                                     kalau satu-satunya yang mendatang sudah
 *                                     batal, biarkan kosong supaya Sales
 *                                     melihat "Belum ada reservasi" dan
 *                                     memesankan lagi. Waitlist (date NULL)
 *                                     IKUT, diurutkan paling belakang.
 *
 * ⚠ Untuk TERBUKA, reservasi berlayanan behel/ortho DIUTAMAKAN, tidak
 *   DIWAJIBKAN. Jembatan prefill dari Tracking Ortho tidak memilih layanan
 *   (hanya mengisi keterangan "Kontrol ortho ke-N"), jadi mewajibkannya akan
 *   melewatkan reservasi yang justru lahir dari modul ini sendiri. Yang
 *   dicegah cuma ini: pasien ortho memesan scaling bulan depan dan booking
 *   itu tersambar jadi "reservasi kontrol" — maka bila ada dua calon, yang
 *   berlayanan behel menang.
 *
 * ⚠ `--sejak`/`--sampai` hanya berlaku untuk RIWAYAT (menyaring visit_date).
 *   Dua jenis lain tidak punya visit_date; menyaringnya lewat opsi itu akan
 *   diam-diam mengosongkan hasilnya.
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

    /**
     * Seberapa jauh ke depan reservasi masih dianggap "untuk kontrol terbuka
     * ini". 120 hari = dua kali IRAMA_MAX_HARI (60): cukup untuk pasien yang
     * memesan jauh-jauh hari, cukup sempit untuk tidak menyambar booking tahun
     * depan yang bukan urusan kontrol sekarang.
     */
    private const HORIZON_TERBUKA = 120;

    /**
     * Penanda layanan ortho di `raw_doc_service.name` — dipakai hanya untuk
     * MENGUTAMAKAN, bukan menyaring (lihat kepala berkas). Nama master lokal:
     * "Kontrol Behel", "Pemasangan Behel".
     */
    private const POLA_LAYANAN_ORTHO = '/behel|ortho|orto|bracket|braces/i';

    /** Jenis sapuan — menentukan jangkar tanggal dan aturan pemilihan. */
    private const RIWAYAT     = 'riwayat';
    private const TIDAK_HADIR = 'tidak_hadir';
    private const TERBUKA     = 'terbuka';

    /** id reservasi yang punya layanan ortho, diisi per gelombang. */
    private array $berlayananOrtho = [];

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
            // dua jenis baris baru dihitung TERPISAH supaya angka cakupan
            // riwayat (yang dibandingkan ke 91% prod) tidak terencerkan.
            'tidakhadir_diperiksa', 'tidakhadir_tertaut',
            'terbuka_diperiksa',    'terbuka_tertaut',
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
        // Tiga sapuan, satu mesin. Urutannya disengaja: RIWAYAT dulu supaya
        // reservasi lama "diklaim" oleh kunjungan yang memang memakainya,
        // baru TIDAK HADIR, baru TERBUKA — yang mendatang tidak mungkin
        // bersaing dengan dua jenis pertama, tapi $terpakai tetap menjaganya.
        foreach ([self::RIWAYAT, self::TIDAK_HADIR, self::TERBUKA] as $jenis) {
            $q = DB::table('ort_control as c')
                ->join('ort_case as k', 'k.id', '=', 'c.ort_case_id')   // sekeluarga — aman
                ->whereNull('c.reservation_id');

            switch ($jenis) {
                case self::RIWAYAT:
                    $q->whereNotNull('c.visit_date');
                    // --sejak/--sampai HANYA di sini: dua jenis lain tidak punya
                    // visit_date, menyaringnya akan mengosongkan hasil diam-diam.
                    if ($this->option('sejak')) {
                        $q->where('c.visit_date', '>=', Carbon::parse($this->option('sejak'))->toDateString());
                    }
                    if ($this->option('sampai')) {
                        $q->where('c.visit_date', '<=', Carbon::parse($this->option('sampai'))->toDateString());
                    }
                    $q->orderBy('c.ort_case_id')->orderBy('c.visit_date');
                    break;

                case self::TIDAK_HADIR:
                    $q->whereNull('c.visit_date')
                      ->where('c.outcome', OrtControl::OUT_TIDAK_HADIR)
                      ->orderBy('c.ort_case_id')->orderBy('c.sch_date');
                    break;

                case self::TERBUKA:
                    $q->whereNull('c.closed_at')
                      ->orderBy('c.ort_case_id')->orderBy('c.sch_date');
                    break;
            }

            if ($this->option('cabang')) {
                $q->where('c.place_id', (string) $this->option('cabang'));
            }

            // Digarap per gelombang: identitas dan reservasi diambil sekali untuk
            // ratusan kontrol, bukan dua kueri per baris.
            $q->select('c.id', 'c.ort_case_id', 'c.place_id', 'c.visit_date', 'c.sch_date',
                       'k.omnicare_pid', 'k.raw_customer_id')
              ->chunk(300, function ($baris) use ($tulis, $toleransi, $jenis) {
                  $this->olahGelombang($baris, $tulis, $toleransi, $jenis);
              });
        }
    }

    /** Tanggal jangkar satu baris menurut jenis sapuannya (Y-m-d). */
    private function jangkar(object $r, string $jenis): string
    {
        return match ($jenis) {
            self::RIWAYAT     => substr((string) $r->visit_date, 0, 10),
            self::TIDAK_HADIR => substr((string) $r->sch_date, 0, 10),
            self::TERBUKA     => Carbon::today()->toDateString(),
        };
    }

    private function olahGelombang($baris, bool $tulis, int $toleransi, string $jenis = self::RIWAYAT): void
    {
        $kDiperiksa = match ($jenis) {
            self::RIWAYAT     => 'diperiksa',
            self::TIDAK_HADIR => 'tidakhadir_diperiksa',
            self::TERBUKA     => 'terbuka_diperiksa',
        };
        $kTertaut = match ($jenis) {
            self::RIWAYAT     => 'tertaut',
            self::TIDAK_HADIR => 'tidakhadir_tertaut',
            self::TERBUKA     => 'terbuka_tertaut',
        };
        $this->n[$kDiperiksa] += count($baris);

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

        // Jendela tanggal mengikuti jangkar jenis ini. TERBUKA: dari hari ini
        // sampai HORIZON_TERBUKA ke depan; waitlist (date NULL) ikut diambil
        // karena "sudah dimasukkan daftar tunggu" adalah tautan yang sah.
        $tgl = collect($baris)->map(fn ($r) => $this->jangkar($r, $jenis));
        if ($jenis === self::TERBUKA) {
            $sejak  = Carbon::today()->toDateString();
            $sampai = Carbon::today()->addDays(self::HORIZON_TERBUKA)->toDateString();
        } else {
            $sejak  = Carbon::parse($tgl->min())->subDays($toleransi)->toDateString();
            $sampai = Carbon::parse($tgl->max())->addDays($toleransi)->toDateString();
        }

        $rsvQ = DB::table('raw_cus_reservation')
            ->whereIn('raw_customer_id', $custIds)
            ->where(function ($w) use ($sejak, $sampai, $jenis) {
                $w->whereBetween('date', [$sejak, $sampai]);
                if ($jenis === self::TERBUKA) {
                    $w->orWhereNull('date');   // waitlist
                }
            });
        if ($jenis === self::TERBUKA) {
            // Yang sudah batal bukan "reservasi mendatang". Biarkan kosong
            // supaya Sales melihat "Belum ada reservasi" dan memesankan lagi.
            $rsvQ->where('status', '<>', 'cancelled');
        }
        $rsv = $rsvQ->get(['id', 'raw_customer_id', 'raw_branch_id', 'date', 'status'])
            ->groupBy('raw_customer_id');

        // Layanan ortho — untuk MENGUTAMAKAN calon di jenis TERBUKA. Dua
        // whereIn dengan literal PHP, bukan JOIN (collation prod campur).
        $this->berlayananOrtho = [];
        $rsvIds = $rsv->flatten(1)->pluck('id')->map(fn ($v) => (string) $v)->all();
        if ($jenis === self::TERBUKA && $rsvIds
            && Schema::hasTable('raw_cus_res_service') && Schema::hasTable('raw_doc_service')) {
            $layananOrtho = DB::table('raw_doc_service')->pluck('name', 'id')
                ->filter(fn ($nama) => preg_match(self::POLA_LAYANAN_ORTHO, (string) $nama))
                ->keys()->map(fn ($v) => (string) $v)->all();
            if ($layananOrtho) {
                $this->berlayananOrtho = DB::table('raw_cus_res_service')
                    ->whereIn('raw_cus_reservation_id', $rsvIds)
                    ->whereIn('raw_doc_service_id', $layananOrtho)
                    ->pluck('raw_cus_reservation_id')
                    ->map(fn ($v) => (string) $v)->flip()->all();
            }
        }

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

            $jangkar = $this->jangkar($r, $jenis);
            $pilih   = $this->calonTerbaik(
                $rsv->get($pelanggan[$r->id], collect()),
                $jangkar,
                (string) $r->place_id,
                $toleransi,
                $jenis
            );

            if (! $pilih) {
                $this->n['tanpa_calon']++;
                continue;
            }

            $this->terpakai[(string) $pilih->id] = true;
            $this->n[$kTertaut]++;

            if (count($this->contoh) < (int) $this->option('contoh')) {
                $tglRsv = filled($pilih->date) ? substr((string) $pilih->date, 0, 10) : null;
                $this->contoh[] = [
                    $jenis,
                    $jangkar,
                    $tglRsv ?? 'waitlist',
                    $tglRsv ? (int) Carbon::parse($tglRsv)->diffInDays(Carbon::parse($jangkar), false) : '—',
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
    private function calonTerbaik($calon, string $jangkar, string $placeId, int $toleransi, string $jenis = self::RIWAYAT)
    {
        $branchId = $this->branches->branchId($placeId);
        $acuan    = Carbon::parse($jangkar);

        $belumDipakai = $calon->reject(fn ($r) => isset($this->terpakai[(string) $r->id]));

        if ($jenis === self::TERBUKA) {
            // Mendatang (≥ hari ini) atau waitlist tanpa tanggal. Jendela atas
            // (HORIZON_TERBUKA) dan pembuangan `cancelled` sudah di kueri.
            $layak = $belumDipakai->filter(fn ($r) => ! filled($r->date)
                || ! Carbon::parse($r->date)->lt($acuan));
        } else {
            $layak = $belumDipakai->filter(fn ($r) => filled($r->date)
                && abs(Carbon::parse($r->date)->diffInDays($acuan)) <= $toleransi);
        }

        if ($layak->isEmpty()) {
            return null;
        }

        if ($layak->count() > 1) {
            $this->n['bentrok']++;
        }

        // Urutan TERBUKA: layanan ortho dulu (bukan syarat — lihat kepala
        // berkas), lalu yang bertanggal sebelum waitlist, lalu yang terdekat,
        // cabang sama, id. Jenis lain: terdekat, cabang sama, id — tak berubah.
        return $layak->sortBy(fn ($r) => [
            $jenis === self::TERBUKA && ! isset($this->berlayananOrtho[(string) $r->id]) ? 1 : 0,
            $jenis === self::TERBUKA && ! filled($r->date) ? 1 : 0,
            filled($r->date) ? abs(Carbon::parse($r->date)->diffInDays($acuan)) : PHP_INT_MAX,
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
                ['Jenis', 'Jangkar', 'Janji reservasi', 'Selisih hari', 'Status', 'Cabang'],
                $this->contoh
            );
            $this->newLine();
        }

        $v = $tulis ? 'tertaut' : 'akan ditaut';
        $this->table(['Hasil', 'Jumlah'], [
            ['RIWAYAT — kontrol berkunjungan diperiksa',     $n['diperiksa']],
            ["  → {$v} (jangkar visit_date ±toleransi)",      $n['tertaut']],
            ['TIDAK HADIR — diperiksa',                      $n['tidakhadir_diperiksa']],
            ["  → {$v} (jangkar sch_date ±toleransi)",        $n['tidakhadir_tertaut']],
            ['TERBUKA — kontrol terbuka diperiksa',          $n['terbuka_diperiksa']],
            ["  → {$v} (reservasi mendatang / waitlist)",     $n['terbuka_tertaut']],
            ['→ pasien belum tertaut raw_customer',          $n['tanpa_customer']],
            ['→ tidak ada calon yang cocok',                 $n['tanpa_calon']],
            ['Lebih dari satu calon (dipilih menurut aturan)', $n['bentrok']],
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
