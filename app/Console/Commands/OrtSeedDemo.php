<?php

namespace App\Console\Commands;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Models\OrtReminder;
use App\Models\OrtTransfer;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtRollup;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data peraga Tracking Ortho — KHUSUS LOKAL/DEV.
 *
 * KENAPA ADA. Penyemai sungguhan (`ort:seed-cases`) membaca cermin rekam medis,
 * dan di DB lokal tabel omnicare_sales_detail & omnicare_patient KOSONG (0
 * baris). Tanpa perintah ini halaman tidak bisa dilihat sama sekali di lokal.
 *
 * ⚠ JANGAN DIJALANKAN DI PRODUKSI. Perintah ini menolak jalan kalau
 *   APP_ENV=production, dan butuh --paksa di lingkungan lain mana pun yang
 *   sudah punya data ort_case.
 *
 * Nama pasien diambil dari raw_customer yang memang ada di DB ini, jadi
 * datanya terasa nyata tanpa mengarang orang baru. Dokter dari raw_doctor.
 *
 * Sebaran sengaja dibuat menyulitkan diri sendiri, meniru seed handoff desain:
 * terlewat <=14 dan >14 hari, reservasi dibatalkan, usulan jadwal, kunjungan
 * tanpa reservasi, transfer keluar (tetap & sementara), transfer masuk, nomor
 * telepon tidak valid, pasien belum tertaut rekam medis, antrean "hilang" dan
 * "jarang kontrol", serta beberapa pasien menumpuk di satu tanggal.
 */
class OrtSeedDemo extends Command
{
    protected $signature = 'ort:seed-demo
                            {--place= : place_id cabang utama (default: cabang pertama)}
                            {--jumlah=26 : berapa kasus dibuat}
                            {--paksa : timpa data ort_* yang sudah ada}
                            {--bersih : hapus semua data ort_* lalu keluar}';

    protected $description = 'Isi tabel ort_* dengan data peraga (LOKAL saja)';

    /** Jenis bracket beserta bobot kemunculannya — Metal mendominasi ±96% di data nyata. */
    private const BRACKET = ['Metal', 'Metal', 'Metal', 'Metal', 'Metal', 'Metal',
                             'Metal', 'Metal', 'Ceramic', 'Sapphire', 'Self Ligating', 'Damon Quantum'];

    private const RAHANG = ['atas', 'bawah', 'keduanya', 'keduanya'];

    public function handle(OrtBranches $branches, OrtRollup $rollup): int
    {
        if (app()->environment('production')) {
            $this->error('Ditolak: perintah ini data peraga, tidak boleh jalan di produksi.');

            return self::FAILURE;
        }

        if ($this->option('bersih')) {
            return $this->bersihkan();
        }

        $peta = $branches->all();
        if (empty($peta)) {
            $this->error('Tidak ada cabang terpeta (raw_branch.omnicare_place_id kosong).');

            return self::FAILURE;
        }

        $home = (string) ($this->option('place') ?: array_key_first($peta));
        if (! isset($peta[$home])) {
            $this->error("place_id {$home} tidak dikenal. Pilihan: " . implode(', ', array_keys($peta)));

            return self::FAILURE;
        }

        if (OrtCase::exists() && ! $this->option('paksa')) {
            $this->error('Tabel ort_case sudah berisi. Pakai --paksa untuk menimpa, atau --bersih untuk menghapus.');

            return self::FAILURE;
        }

        if ($this->option('paksa')) {
            $this->bersihkan(true);
        }

        $jumlah  = max(6, (int) $this->option('jumlah'));
        $lain    = array_values(array_diff(array_keys($peta), [$home]));
        $pasien  = $this->ambilPasien($jumlah);
        $dokter  = $this->ambilDokter();

        if ($pasien->isEmpty()) {
            $this->error('raw_customer kosong — tidak ada nama yang bisa dipakai.');

            return self::FAILURE;
        }

        $this->info("Cabang utama : {$peta[$home]['nama']} ({$home})");
        $this->info('Kasus dibuat : ' . $jumlah);
        $this->newLine();

        $hariIni = Carbon::today();
        $dibuat  = 0;

        DB::transaction(function () use (
            $pasien, $dokter, $jumlah, $home, $lain, $hariIni, $rollup, &$dibuat
        ) {
            foreach (range(0, $jumlah - 1) as $i) {
                $p    = $pasien[$i % $pasien->count()];
                $dok  = $dokter[$i % max(1, count($dokter))] ?? null;
                $pola = $this->pola($i, $jumlah);

                $case = $this->buatKasus($p, $dok, $home, $hariIni, $pola, $i);
                $this->buatRiwayat($case, $home, $lain, $hariIni, $pola);

                $rollup->refresh($case->id);
                $dibuat++;
            }
        });

        $this->ringkasan($home, $peta, $dibuat);

        return self::SUCCESS;
    }

    /**
     * Pola sebaran. Tiap kasus dapat satu peran supaya seluruh keadaan UI
     * terwakili, bukan 26 baris yang seragam.
     */
    private function pola(int $i, int $jumlah): string
    {
        return match (true) {
            $i === 0  => 'terlewat_parah',     // >14 hari, merah
            $i === 1  => 'reservasi_batal',
            $i === 2  => 'terlewat_ringan',    // <=14 hari, amber
            $i === 3  => 'usulan',             // baru selesai kontrol, menunggu konfirmasi
            $i === 4  => 'hari_ini',
            $i === 5  => 'ada_reservasi',
            $i === 6  => 'sudah_fu',
            $i === 7  => 'transfer_keluar',
            $i === 8  => 'transfer_sementara',
            $i === 9  => 'transfer_masuk',
            $i === 10 => 'backlog_hilang',
            $i === 11 => 'backlog_hilang',
            $i === 12 => 'backlog_jarang',
            $i === 13 => 'backlog_jarang',
            $i === 14 => 'telepon_invalid',
            $i === 15 => 'belum_tertaut',
            $i === 16 => 'walk_in',
            $i >= $jumlah - 4 => 'menumpuk',   // beberapa pasien di tanggal yang sama
            default   => 'menunggu',
        };
    }

    private function buatKasus($p, $dok, string $home, Carbon $hariIni, string $pola, int $i): OrtCase
    {
        $pasang = $hariIni->copy()->subDays(random_int(90, 700));

        $telepon = match ($pola) {
            'telepon_invalid' => '0812-000',
            default           => $p->phone ?: '08' . random_int(1111111111, 9999999999),
        };

        // Nomor RM bergaya Omnicare: huruf cabang - tahun bulan - urutan.
        $pid = $pola === 'belum_tertaut'
            ? null
            : strtoupper(substr($home === '29957' ? 'B' : 'S', 0, 1))
              . '-' . $pasang->format('ym') . '-' . str_pad((string) (1000 + $i * 37 % 8999), 4, '0', STR_PAD_LEFT);

        return OrtCase::create([
            'id'               => (string) Str::uuid(),
            'omnicare_pid'     => $pid,
            'raw_customer_id'  => $p->id,
            'patient_name'     => $p->name,
            'patient_phone'    => $telepon,
            'install_date'     => $pasang->toDateString(),
            'install_place_id' => $home,
            'bracket_type'     => self::BRACKET[$i % count(self::BRACKET)],
            'bracket_arch'     => self::RAHANG[$i % count(self::RAHANG)],
            'doctor_ref_id'    => $dok?->omnicare_doc_id ?: ('DOK' . ($i % 5 + 1)),
            'doctor_name'      => $dok?->name ?: 'drg. Vega',
            'status'           => OrtCase::STATUS_AKTIF,
            'owner_place_id'   => $home,   // diperbaiki OrtRollup sesudah riwayat dibuat
            'seed_source'      => 'demo',
            'created_by'       => 'ort:seed-demo',
        ]);
    }

    /**
     * Riwayat kontrol. Yang lama ditutup dengan kunjungan (meniru hasil
     * penyemaian dari rekam medis), yang terakhir dibiarkan terbuka sesuai pola.
     */
    private function buatRiwayat(OrtCase $case, string $home, array $lain, Carbon $hariIni, string $pola): void
    {
        $backlog = str_starts_with($pola, 'backlog_');

        // Berapa kontrol lampau yang sudah ditutup.
        $lampau = match ($pola) {
            'backlog_hilang' => random_int(2, 4),
            'backlog_jarang' => random_int(1, 2),
            default          => random_int(2, 5),
        };

        // Kunjungan terakhir berapa hari lalu.
        $mundur = match ($pola) {
            'backlog_hilang' => random_int(200, 320),
            'backlog_jarang' => random_int(40, 90),
            default          => random_int(25, 45),
        };

        $seq     = 0;
        $tanggal = $hariIni->copy()->subDays($mundur + $lampau * 32);
        $cabang  = $home;

        // Transfer keluar/sementara terjadi di tengah riwayat.
        $tujuan     = ! empty($lain) ? $lain[array_rand($lain)] : null;
        $pindahPada = in_array($pola, ['transfer_keluar', 'transfer_sementara'], true)
            ? max(1, $lampau - 1)
            : null;

        for ($n = 1; $n <= $lampau; $n++) {
            if ($pindahPada !== null && $n === $pindahPada && $tujuan) {
                $this->catatTransfer($case, $cabang, $tujuan, $tanggal, $pola === 'transfer_sementara');
                $cabang = $tujuan;
            }

            $tanggal = $tanggal->copy()->addDays(random_int(28, 38));
            $seq++;

            OrtControl::create([
                'id'              => (string) Str::uuid(),
                'ort_case_id'     => $case->id,
                'seq'             => $seq,
                'place_id'        => $cabang,
                'sch_date'        => $tanggal->toDateString(),
                'sch_source'      => OrtControl::SCH_REKAM_MEDIS,
                'fu_date'         => $tanggal->copy()->subDays(2)->toDateString(),
                'fu_channel'      => 'wa',
                'fu_by'           => 'Andini',
                'visit_date'      => $tanggal->toDateString(),
                'visit_source'    => 'rekam_medis',
                'visit_filled_at' => $tanggal->copy()->addDay()->setTime(2, 0),
                'unscheduled'     => $pola === 'walk_in' && $n === $lampau,
                'outcome'         => OrtControl::OUT_HADIR,
                'closed_at'       => $tanggal->copy()->addDay()->setTime(2, 0),
                'closed_by'       => 'sistem',
                'created_by'      => 'ort:seed-demo',
            ]);
        }

        // Transfer masuk: pasien cabang lain yang sekarang kita pegang.
        if ($pola === 'transfer_masuk' && ! empty($lain)) {
            $asal = $lain[array_rand($lain)];
            $this->catatTransfer($case, $asal, $home, $hariIni->copy()->subDays(9), false);
            $cabang = $home;
        }

        if ($backlog) {
            return;   // sengaja TIDAK punya kontrol terbuka — inilah antreannya
        }

        // Kontrol terbuka, bentuknya mengikuti pola.
        $seq++;
        $sch = match ($pola) {
            'terlewat_parah'   => $hariIni->copy()->subDays(random_int(22, 47)),
            'terlewat_ringan'  => $hariIni->copy()->subDays(random_int(2, 12)),
            'reservasi_batal'  => $hariIni->copy()->subDays(random_int(16, 39)),
            'hari_ini'         => $hariIni->copy(),
            'menumpuk'         => $hariIni->copy()->addDay(),
            // Aturan domain 16 ("jadwal tidak boleh lahir sudah terlewat")
            // hidup di OrtControl::tanggalUsulan(), bukan disalin ke sini —
            // salinan keempatlah yang dulu bikin tarikan malam menyimpang.
            'usulan'           => OrtControl::tanggalUsulan($tanggal, $hariIni),
            default            => $hariIni->copy()->addDays(random_int(2, 20)),
        };

        $punyaFu  = in_array($pola, ['sudah_fu', 'ada_reservasi', 'reservasi_batal', 'menumpuk'], true);
        $punyaRsv = in_array($pola, ['ada_reservasi', 'hari_ini', 'menumpuk'], true);
        $batal    = $pola === 'reservasi_batal';

        OrtControl::create([
            'id'             => (string) Str::uuid(),
            'ort_case_id'    => $case->id,
            'seq'            => $seq,
            'place_id'       => $cabang,
            'is_proposal'    => $pola === 'usulan',
            'sch_date'       => $sch->toDateString(),
            'sch_source'     => $pola === 'usulan' ? OrtControl::SCH_SISTEM : OrtControl::SCH_MANUAL,
            'fu_date'        => ($punyaFu || $batal) ? $sch->copy()->subDays(4)->toDateString() : null,
            'fu_channel'     => ($punyaFu || $batal) ? 'wa' : null,
            'fu_by'          => ($punyaFu || $batal) ? 'Andini' : null,
            'reservation_id' => ($punyaRsv || $batal) ? 'demo-rsv-' . Str::random(8) : null,
            'rsv_date'       => ($punyaRsv || $batal) ? $sch->toDateString() : null,
            'rsv_status'     => $batal ? 'cancelled' : ($punyaRsv ? 'confirmed' : null),
            'rsv_synced_at'  => ($punyaRsv || $batal) ? now() : null,
            'created_by'     => 'ort:seed-demo',
        ]);
    }

    private function catatTransfer(OrtCase $case, string $dari, string $ke, Carbon $tanggal, bool $sementara): void
    {
        OrtTransfer::create([
            'id'            => (string) Str::uuid(),
            'ort_case_id'   => $case->id,
            'from_place_id' => $dari,
            'to_place_id'   => $ke,
            'transfer_date' => $tanggal->toDateString(),
            'reason'        => array_rand(OrtTransfer::REASONS),
            'is_temporary'  => $sementara,
            'detected'      => OrtTransfer::DETECTED_MANUAL,
            'created_by'    => 'ort:seed-demo',
        ]);

        // Sebagian yang tertinggal di cabang lain sudah pernah diingatkan.
        if (! $sementara && random_int(0, 1) === 1) {
            OrtReminder::create([
                'id'            => (string) Str::uuid(),
                'ort_case_id'   => $case->id,
                'from_place_id' => $dari,
                'to_place_id'   => $ke,
                'reminded_at'   => $tanggal->copy()->addDays(random_int(3, 20)),
                'reminded_by'   => 'Andini',
                'channel'       => 'wa',
            ]);
        }
    }

    /** Nama pasien nyata dari DB ini — supaya datanya terasa betulan. */
    private function ambilPasien(int $jumlah)
    {
        return DB::table('raw_customer')
            ->whereNotNull('name')
            ->where('name', '<>', '')
            ->orderByRaw('RAND()')
            ->limit($jumlah)
            ->get(['id', 'name', 'phone']);
    }

    private function ambilDokter(): array
    {
        return DB::table('raw_doctor')->orderBy('name')->get(['name', 'omnicare_doc_id'])->all();
    }

    private function bersihkan(bool $diam = false): int
    {
        foreach (['ort_reminder', 'ort_transfer', 'ort_control', 'ort_case'] as $t) {
            DB::table($t)->delete();
        }

        if (! $diam) {
            $this->info('Semua data ort_* dihapus.');
        }

        return self::SUCCESS;
    }

    private function ringkasan(string $home, array $peta, int $dibuat): void
    {
        $this->info("Selesai. {$dibuat} kasus dibuat.");
        $this->newLine();

        $rows = [];
        foreach ($peta as $pid => $b) {
            $dipegang = OrtCase::aktif()->dipegang($pid)->count();
            $terbuka  = DB::table('ort_control')->where('place_id', $pid)->whereNull('closed_at')->count();
            $antrean  = OrtCase::aktif()->dipegang($pid)->belumPunyaJadwal()->count();

            if ($dipegang || $terbuka) {
                $rows[] = [$b['nama'] . ($pid === $home ? ' (utama)' : ''), $dipegang, $terbuka, $antrean];
            }
        }

        $this->table(['Cabang', 'Pasien dipegang', 'Kontrol terbuka', 'Belum punya jadwal'], $rows);
        $this->line('Buka: /tracking-ortho');
    }
}
