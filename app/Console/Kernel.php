<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Status promo — daily 00:05. Pindahkan promo ke aktif/selesai otomatis
        // saat melewati periode_start / periode_end. Ringan (3 UPDATE indexed).
        $schedule->command('promo:sync-status')
            ->dailyAt('00:05')
            ->withoutOverlapping(10);

        // Checklist "Tidak Diisi" — daily 00:10. Tandai pengisian HARIAN kemarin
        // yang tidak tuntas (tak ada submission / masih draft) jadi status
        // "missed", supaya muncul di tab "Tidak Diisi" halaman review. Yang
        // submitted/approved/rejected/excused dilewati. Idempoten — aman rerun.
        $schedule->command('checklist:mark-missed')
            ->dailyAt('00:10')
            ->withoutOverlapping(15);

        // Checklist Mingguan "Tidak Diisi" — Senin 00:15. Tandai Form Mingguan
        // minggu lalu (yang sudah berakhir) yang tak disubmit / masih draft jadi
        // "missed", hanya untuk divisi yang punya tugas mingguan. Idempoten.
        $schedule->command('checklist:mark-weekly-missed')
            ->weeklyOn(1, '00:15')
            ->withoutOverlapping(15);

        // Omnicare sync — daily 02:00 (3-day overlap window untuk catch late entries).
        // withoutOverlapping mencegah dua run barengan kalau sync sebelumnya belum
        // selesai; runInBackground supaya kerja tidak blocking ke command lain.
        $schedule->command('omnicare:sync')
            ->dailyAt('02:00')
            ->withoutOverlapping(120) // lock 120 menit max
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/omnicare-cron.log'));

        // Match-patients — daily 02:45, di antara sync & reconcile.
        // Link customer baru (SweetTreats) ↔ pasien baru (Omnicare). Idempoten:
        // hanya proses yang belum ter-link. Reconcile butuh link ini fresh.
        $schedule->command('omnicare:match-patients')
            ->dailyAt('02:45')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/omnicare-cron.log'));

        // Reconcile — daily 03:00, setelah sync 02:00 punya data fresh.
        // Cocokkan reservasi ↔ encounter; conflict masuk antrian review manual.
        $schedule->command('omnicare:reconcile')
            ->dailyAt('03:00')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/omnicare-cron.log'));

        // Accurate push — daily 03:30. Sync sendiri window 14 hari (sales/
        // payments/commission), audit fresh-vs-pushed per hari per cabang,
        // repair yang berubah (late entries), push yang baru. Idempoten penuh
        // via accurate_push_log — aman di-rerun. Skip bersih kalau tabel
        // accurate_credentials belum ada (env yang belum deploy Accurate).
        $schedule->command('accurate:push-daily --days=14')
            ->dailyAt('03:30')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/accurate-cron.log'));

        // Absensi pagi — 06:10 reconcile (SETELAH ambang dini-hari 06:00, biar
        // scan subuh sudah final milik hari kemarin), lalu 06:20 sweep flag
        // "lupa absen pulang" dari att_day kemarin. Dua-duanya idempoten.
        $schedule->command(sprintf('att:reconcile %s %s',
                now()->subDay()->toDateString(), now()->toDateString()))
            ->dailyAt('06:10')
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/absensi-cron.log'));

        $schedule->command('att:flag-sweep')
            ->dailyAt('06:20')
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/absensi-cron.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
