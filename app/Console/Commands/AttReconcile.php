<?php

namespace App\Console\Commands;

use App\Services\AttendanceReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Hitung ulang att_day dari att_scan + aturan, untuk rentang tanggal.
 *
 *   php artisan att:reconcile 2026-06-01 2026-06-30
 */
class AttReconcile extends Command
{
    protected $signature = 'att:reconcile {from : YYYY-MM-DD} {to : YYYY-MM-DD}';
    protected $description = 'Rekonsiliasi att_scan → att_day (telat/alpa/hadir per user per hari)';

    public function handle(AttendanceReconciler $engine): int
    {
        $from = Carbon::parse($this->argument('from'))->startOfDay();
        $to = Carbon::parse($this->argument('to'))->startOfDay();
        if ($to->lt($from)) {
            $this->error('Rentang tanggal terbalik.');
            return self::FAILURE;
        }

        $t0 = microtime(true);
        $res = $engine->reconcile($from, $to);
        $ms = round((microtime(true) - $t0) * 1000);

        $this->info("Rekonsiliasi {$from->toDateString()} s/d {$to->toDateString()} ({$ms}ms)");
        $this->line("  user diproses: {$res['users']}  |  baris att_day: {$res['days']}");
        $rows = [];
        foreach ($res['per_status'] as $status => $n) $rows[] = [$status, $n];
        usort($rows, fn ($a, $b) => $b[1] <=> $a[1]);
        $this->table(['status', 'jumlah'], $rows);

        if (! empty($res['basi_dibuang'])) {
            $this->line("  baris basi dibuang: {$res['basi_dibuang']} (hari yg kini libur/tak dinilai lagi; periode tertutup tak disentuh)");
        }

        // Peringatan keras: orang yang scan-nya masuk tapi belum punya aturan kerja
        // TIDAK diproses sama sekali — dia hilang dari rekap & kehadirannya nol,
        // tanpa error. Lebih baik berisik di sini daripada ketahuan saat gajian.
        if (! empty($res['tanpa_aturan'])) {
            $n = count($res['tanpa_aturan']);
            $this->newLine();
            $this->warn("⚠  {$n} orang punya scan di rentang ini tapi BELUM punya aturan kerja (att_user_rule).");
            $this->warn('   Scan mereka tidak dihitung dan mereka tidak muncul di rekap:');
            foreach ($res['tanpa_aturan'] as $nama) $this->line("     - {$nama}");
            $this->warn('   Isi att_user_rule mereka lalu jalankan ulang perintah ini.');
        }

        return self::SUCCESS;
    }
}
