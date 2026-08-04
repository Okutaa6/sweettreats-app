<?php

namespace App\Console\Commands;

use App\Services\AttendanceRequestWorkflow;
use Illuminate\Console\Command;

/**
 * Finalisasi warisan pengajuan absensi yang tertahan di pending_l2.
 *
 * Persetujuan absensi jadi SATU lapis (2026-08-05): atasan langsung ACC =
 * selesai. Pengajuan yang keburu lolos L1 dan menunggu L2 di bawah aturan
 * lama tidak akan pernah muncul di inbox mana pun lagi — macet senyap.
 * Command ini memfinalkan mereka lewat jalur approve normal (potong cuti,
 * recalc att_day, kabar pengaju). Idempoten — aman dijalankan berulang.
 *
 * Jalankan SEKALI di tiap environment saat deploy perubahan 1-lapis:
 *   php artisan absensi:selesaikan-l2
 */
class AbsensiSelesaikanL2 extends Command
{
    protected $signature = 'absensi:selesaikan-l2';

    protected $description = 'Finalkan pengajuan absensi warisan yang tertahan di pending_l2 (persetujuan kini 1 lapis)';

    public function handle(AttendanceRequestWorkflow $workflow): int
    {
        $n = $workflow->selesaikanBacklogL2();
        $this->info($n === 0
            ? 'Tidak ada pengajuan pending_l2 — tidak ada yang perlu difinalkan.'
            : "{$n} pengajuan pending_l2 difinalkan jadi approved (efek approve normal ikut jalan).");

        return self::SUCCESS;
    }
}
