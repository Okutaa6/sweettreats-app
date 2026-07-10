<?php

namespace App\Console\Commands;

use App\Models\MarketingPromo;
use Illuminate\Console\Command;

/**
 * Set status promo (planning/aktif/selesai) otomatis dari periode-nya.
 *
 *   php artisan promo:sync-status
 *
 * Dijalankan terjadwal tiap hari (lihat Console\Kernel) supaya promo berpindah
 * status sendiri saat melewati periode_start / periode_end. Promo tanpa tanggal
 * tidak disentuh (status tetap manual).
 */
class SyncPromoStatus extends Command
{
    protected $signature = 'promo:sync-status';

    protected $description = 'Selaraskan status promo dengan periode (planning → aktif → selesai)';

    public function handle(): int
    {
        $n = MarketingPromo::syncStatuses();
        $this->info("Status promo disinkron: {$n} baris berubah.");

        return self::SUCCESS;
    }
}
