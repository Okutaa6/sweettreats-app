<?php

namespace App\Console\Commands;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtRollup;
use App\Services\Ortho\OrtUsulan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memeriksa ulang USULAN JADWAL yang sudah telanjur ada, lawan pagar hari ini.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KENAPA PERINTAH INI ADA
 * ────────────────────────────────────────────────────────────────────────────
 * Tahap A memasang pagar keras di OrtUsulan: klinik tutup Senin, dokternya
 * harus praktik hari itu, harinya belum penuh, dan dokter yang tidak terpetakan
 * berarti TIDAK ADA usulan sama sekali.
 *
 * Pagar itu bekerja SAAT USULAN DIBUAT. Dan hanya saat itu.
 *
 * Baris yang sudah telanjur ada di tabel tidak pernah diperiksa lagi oleh
 * siapa pun. Jadi 651 usulan yang lahir di satu tanggal yang sama sebelum
 * pagar dipasang tetap duduk di sana, rapi, dengan tombol "Konfirmasi jadwal"
 * yang sekali klik — termasuk 117 usulan Kalideres di hari drg. Andy tidak
 * praktik. Memperbaiki mesinnya menghentikan pendarahan; ia tidak membersihkan
 * lukanya.
 *
 * Ditemukan 1 Okt 2026 dengan cara paling sederhana: membuka halamannya dan
 * melihat satu usulan jatuh hari MINGGU untuk dokter yang bahkan tidak ada di
 * tabel raw_doctor. Usulan itu lahir pukul 11:49; pagarnya baru ada pukul
 * 15:08. Mesin yang benar tidak membatalkan apa yang sudah ditulis mesin lama.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * APA YANG DILAKUKAN TERHADAP TIAP USULAN
 * ────────────────────────────────────────────────────────────────────────────
 * OrtUsulan ditanya ulang, dengan pertanyaan yang sama persis seperti saat
 * tarikan malam: "untuk pasien ini, dengan kunjungan terakhirnya, hari ini,
 * tanggal berapa?" Lalu:
 *
 *   • jawabannya TANGGAL yang berbeda  → usulannya DIGESER ke sana
 *   • jawabannya TIDAK ADA USULAN      → barisnya DIHAPUS
 *   • tanggalnya sudah sah              → dibiarkan, tidak disentuh
 *
 * ⚠ MENGHAPUS, bukan menandai. Dan itu memang aman: `is_proposal = 1` berarti
 *   tebakan sistem yang BELUM disepakati siapa pun. Begitu Sales menekan
 *   "Konfirmasi jadwal", barisnya jadi is_proposal = 0 dan perintah ini tidak
 *   akan pernah menyentuhnya lagi. Tidak ada fakta yang hilang.
 *
 * ⚠ Dan pasiennya TIDAK ikut hilang dari layar. Kasus tanpa jadwal terbuka
 *   muncul di kartu "Belum punya jadwal" — kelompok ketiga antrean memang
 *   dikembalikan di Tahap A persis untuk keadaan ini. Yang berubah cuma: ia
 *   berpindah dari daftar kerja (dengan tanggal yang mustahil) ke antrean
 *   penjadwalan (tanpa tanggal, menunggu Sales). Itu yang jujur.
 *
 * Yang TIDAK pernah disentuh: baris ber-is_proposal = 0 (jadwal yang sudah
 * disepakati), baris yang sudah punya visit_date, dan baris yang sudah
 * tertutup. Perintah ini hanya berurusan dengan tebakan yang belum disepakati.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * Menulis BUTUH `--commit`. Tanpa itu: memindai, melaporkan, lalu berhenti.
 * Aman diulang — sesudah dijalankan sekali, jalanan kedua tidak menemukan apa
 * pun untuk dikerjakan.
 */
class OrtRapikanUsulan extends Command
{
    protected $signature = 'ort:rapikan-usulan
                            {--cabang= : batasi ke satu omnicare place_id}
                            {--ambang= : timpa ambang kapasitas per cabang-hari}
                            {--commit : TULIS perubahannya. Tanpa ini cuma pratinjau}
                            {--contoh=10 : berapa baris contoh dicetak}';

    protected $description = 'Periksa ulang usulan jadwal yang sudah ada lawan pagar OrtUsulan; geser atau hapus yang mustahil';

    private const PENULIS = 'ort:rapikan-usulan';

    public function __construct(
        private OrtBranches $branches,
        private OrtUsulan $usulan,
        private OrtRollup $rollup,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        foreach (['ort_control', 'ort_case'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->error("Tabel {$t} belum ada. Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.");

                return self::FAILURE;
            }
        }

        if ($this->option('ambang') !== null) {
            $this->usulan = new OrtUsulan(max(1, (int) $this->option('ambang')));
        }

        $tulis  = (bool) $this->option('commit');
        $cabang = $this->option('cabang') ? (string) $this->option('cabang') : null;

        if ($cabang && ! $this->branches->branchId($cabang)) {
            $this->error("place_id {$cabang} tidak dikenal.");

            return self::FAILURE;
        }

        $this->info('Memeriksa ulang usulan jadwal' . ($cabang ? ' · cabang ' . $cabang : ' · semua cabang'));
        $this->newLine();

        $n = ['diperiksa' => 0, 'tetap' => 0, 'dasar' => 0, 'digeser' => 0,
              'dihapus' => 0, 'tanpa_kunjungan' => 0];
        $sebab   = [];
        $contoh  = [];
        $batas   = max(0, (int) $this->option('contoh'));
        $kasusId = [];

        $q = OrtControl::query()
            ->where('is_proposal', 1)
            ->whereNull('closed_at')
            ->whereNull('visit_date');

        if ($cabang) {
            $q->where('place_id', $cabang);
        }

        $q->orderBy('id')->chunkById(200, function ($baris) use (&$n, &$sebab, &$contoh, &$kasusId, $batas, $tulis) {
            // Kasusnya diambil per gelombang, bukan per baris — dan lewat
            // whereIn, bukan join: ort_case memang satu keluarga dengan
            // ort_control, tapi memuatnya sekali tetap lebih murah daripada
            // 200 kueri.
            $kasus = OrtCase::whereIn('id', $baris->pluck('ort_case_id')->unique()->all())
                ->get()->keyBy('id');

            foreach ($baris as $ctl) {
                $n['diperiksa']++;

                $c = $kasus->get($ctl->ort_case_id);
                if (! $c) {
                    continue;
                }

                /*
                 * Titik berangkatnya kunjungan TERAKHIR yang benar-benar
                 * terjadi — persis yang dipakai tarikan malam. Tanpa satu pun
                 * kunjungan, tidak ada yang bisa dijadikan dasar; baris itu
                 * dibiarkan dan dilaporkan terpisah, bukan ditebak ulang.
                 */
                $terakhir = OrtControl::where('ort_case_id', $c->id)
                    ->whereNotNull('visit_date')
                    ->orderByDesc('visit_date')
                    ->value('visit_date');

                if (! $terakhir) {
                    $n['tanpa_kunjungan']++;

                    continue;
                }

                $putusan = $this->usulan->untuk($c, Carbon::parse($terakhir), (string) $ctl->place_id);
                $lama    = $ctl->sch_date?->toDateString();

                if ($putusan['tanggal'] === $lama) {
                    /*
                     * Tanggalnya sudah sah — tapi DASARNYA bisa saja usang.
                     *
                     * Saat ukuran keyakinan berganti (dari banyaknya selang ke
                     * sebaran irama), baris lama tetap menyimpan nilai lama
                     * meski tanggalnya kebetulan masih benar. Kalau perintah ini
                     * cuma memeriksa tanggal, nilai usang itu tinggal selamanya
                     * dan layar menilai keyakinannya dengan ukuran yang sudah
                     * ditinggalkan — diam-diam, tanpa satu pun baris berubah.
                     */
                    $dasarBeda = $ctl->sch_basis !== $putusan['basis']
                              || (int) $ctl->sch_basis_n !== (int) $putusan['basis_n'];

                    if (! $dasarBeda) {
                        $n['tetap']++;

                        continue;
                    }

                    $n['dasar']++;
                    $kunci = 'dasar diperbarui';
                    $sebab[$kunci] = ($sebab[$kunci] ?? 0) + 1;

                    if (count($contoh) < $batas) {
                        $contoh[] = [$c->patient_name, $lama, $lama, ($ctl->sch_basis ?? '—') . ' → ' . $putusan['basis']];
                    }

                    if ($tulis) {
                        $ctl->fill([
                            'sch_basis'   => $putusan['basis'],
                            'sch_basis_n' => $putusan['basis_n'],
                            'updated_by'  => self::PENULIS,
                        ])->save();
                    }

                    continue;
                }

                $kasusId[$c->id] = true;

                if ($putusan['tanggal'] === null) {
                    $n['dihapus']++;
                    $kunci = 'hapus · ' . $putusan['sebab'];
                    $sebab[$kunci] = ($sebab[$kunci] ?? 0) + 1;

                    if (count($contoh) < $batas) {
                        $contoh[] = [$c->patient_name, $lama, 'DIHAPUS', $putusan['sebab']];
                    }

                    if ($tulis) {
                        $ctl->delete();
                    }

                    continue;
                }

                $n['digeser']++;
                $kunci = 'geser';
                $sebab[$kunci] = ($sebab[$kunci] ?? 0) + 1;

                if (count($contoh) < $batas) {
                    $contoh[] = [$c->patient_name, $lama, $putusan['tanggal'], $putusan['basis']];
                }

                if ($tulis) {
                    $ctl->fill([
                        'sch_date'    => $putusan['tanggal'],
                        'sch_basis'   => $putusan['basis'],
                        'sch_basis_n' => $putusan['basis_n'],
                        'updated_by'  => self::PENULIS,
                    ])->save();
                }
            }
        });

        // Kolom ringkasan ort_case menunjuk ke jadwal terbuka (open_control_id,
        // next_sch_date). Menghapus atau menggeser usulan membuatnya melenceng,
        // dan kartu "Terlewat" membaca kolom itu — tanpa penyegaran ini,
        // angkanya salah sampai tarikan malam berikutnya.
        if ($tulis && $kasusId) {
            foreach (array_keys($kasusId) as $id) {
                $this->rollup->refresh($id);
            }
        }

        $this->laporkan($n, $sebab, $contoh, $tulis);

        return self::SUCCESS;
    }

    private function laporkan(array $n, array $sebab, array $contoh, bool $tulis): void
    {
        $this->table(['Hasil', 'Usulan'], [
            ['Usulan terbuka diperiksa', $n['diperiksa']],
            ['→ sudah sah, dibiarkan', $n['tetap']],
            ['→ tanggal tetap, dasarnya diperbarui', $n['dasar']],
            ['→ digeser ke tanggal sah', $n['digeser']],
            ['→ dihapus (tidak ada tanggal sah)', $n['dihapus']],
            ['→ belum punya kunjungan, dilewati', $n['tanpa_kunjungan']],
        ]);

        if ($sebab) {
            $this->newLine();
            ksort($sebab);
            $this->table(['Sebab', 'Usulan'], collect($sebab)->map(fn ($v, $k) => [$k, $v])->values()->all());
        }

        if ($contoh) {
            $this->newLine();
            $this->table(['Pasien', 'Tanggal lama', 'Tanggal baru', 'Sebab/dasar'], $contoh);
        }

        $this->newLine();

        if ($n['dihapus'] > 0) {
            $this->line('Yang dihapus TIDAK hilang dari layar: pasiennya pindah ke kartu');
            $this->line('"Belum punya jadwal" dan menunggu Sales menjadwalkan sendiri.');
            $this->newLine();
        }

        if (! $tulis) {
            $this->warn('PRATINJAU — tidak ada satu baris pun yang ditulis. Tambahkan --commit.');

            return;
        }

        $this->info('Selesai. Jalankan lagi kapan saja; yang sudah sah tidak disentuh.');
    }
}
