<?php

namespace App\Console\Commands;

use App\Models\RawAffiliate;
use App\Services\KocPasien;
use Illuminate\Console\Command;

/**
 * Backfill SEKALI `raw_affiliate.raw_customer_id` — hanya yang cocok TUNGGAL.
 *
 * Yang ganda (mis. Dewi Santi: satu HP, dua rekam pasien) dan yang tak
 * ketemu DIBIARKAN NULL supaya manusia memilih di layar KOC. Jangan pernah
 * "menyelesaikan" yang ganda dengan first().
 *
 * Aman diulang: tautan yang sudah terisi tak pernah ditimpa.
 *
 *   php artisan koc:tautkan-pasien --dry-run   # lihat dulu
 *   php artisan koc:tautkan-pasien             # tulis
 */
class KocTautkanPasien extends Command
{
    protected $signature = 'koc:tautkan-pasien {--dry-run : Tampilkan hasil tanpa menulis}';

    protected $description = 'Tautkan KOC/KOL ke rekam pasien bila nomor HP-nya cocok TUNGGAL (yang ganda dibiarkan untuk dipilih manusia)';

    public function handle(KocPasien $pasien): int
    {
        $dry = (bool) $this->option('dry-run');

        $affiliates = RawAffiliate::whereNull('raw_customer_id')
            ->whereNotNull('phone')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $rekap = [KocPasien::TERTAUT => 0, KocPasien::GANDA => 0, KocPasien::KOSONG => 0];
        $baris = [];
        foreach ($affiliates as $a) {
            $hasil = $pasien->tautkanOtomatis($a, !$dry);
            $rekap[$hasil] = ($rekap[$hasil] ?? 0) + 1;
            if ($hasil !== KocPasien::KOSONG) {
                $baris[] = [$a->name, $a->type ?: '-', $a->phone, $hasil,
                    $hasil === KocPasien::TERTAUT ? ($dry ? '(dry-run)' : $a->raw_customer_id) : 'perlu dipilih manusia'];
            }
        }

        if ($baris) {
            $this->table(['Nama', 'Tipe', 'HP', 'Hasil', 'raw_customer_id'], $baris);
        }
        $this->info(sprintf('%sDiperiksa %d · tertaut %d · ganda %d · tak ketemu %d',
            $dry ? '[DRY-RUN] ' : '', $affiliates->count(),
            $rekap[KocPasien::TERTAUT], $rekap[KocPasien::GANDA], $rekap[KocPasien::KOSONG]));

        return self::SUCCESS;
    }
}
