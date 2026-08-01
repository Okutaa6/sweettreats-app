<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use App\Models\RopRequest;
use App\Services\RopService;
use Illuminate\Console\Command;

/**
 * rop:sapu
 *
 * Penyapu harian antrian pengajuan — dua tugas yang sama-sama menjaga
 * "tidak ada pengajuan yang tenggelam":
 *
 *   1. REMINDER  — pengajuan menunggu ACC lebih dari 3 hari → ingatkan Finance.
 *      Diingatkan maksimal sekali sehari per pengajuan (kolom reminded_at),
 *      supaya kotak notifikasi tidak jadi kebisingan yang malah diabaikan.
 *   2. KADALUARSA — pengajuan diam 30 hari → ditutup otomatis + pengaju
 *      diberi tahu supaya bisa mengajukan ulang kalau masih dibutuhkan.
 *
 * Jalankan sekali sehari lewat penjadwal yang sudah ada.
 */
class RopSapu extends Command
{
    protected $signature = 'rop:sapu {--dry-run : Tampilkan yang akan dikerjakan tanpa mengubah data}';

    protected $description = 'Ingatkan Finance soal antrian tua & kadaluarsakan pengajuan yang didiamkan';

    public function handle(RopService $rop): int
    {
        $kering = (bool) $this->option('dry-run');

        /* ── 1. Reminder antrian tua ── */
        $tua = RopRequest::menunggu()
            ->where('created_at', '<=', now()->subDays(RopService::HARI_REMINDER))
            ->where(function ($q) {
                $q->whereNull('reminded_at')->orWhere('reminded_at', '<', now()->subDay());
            })
            ->with('requester')
            ->get();

        foreach ($tua as $r) {
            $this->line("ingatkan: {$r->number} — {$r->umurHari()} hari menunggu");
            if ($kering) continue;

            AdminNotification::notifyRole(
                RopService::ROLE_PENYETUJU,
                'rop_menunggu_lama',
                'Pengajuan menunggu ' . $r->umurHari() . ' hari',
                $r->number . ' (Rp ' . number_format((float) $r->amount, 0, ',', '.') . ') dari '
                    . ($r->requester?->nama ?: 'pengaju') . ' belum diputuskan.',
                url('finance/rop'),
            );
            $r->forceFill(['reminded_at' => now()])->save();
        }

        /* ── 2. Kadaluarsa ── */
        // Diukur dari updated_at, bukan created_at: pengajuan yang sudah
        // disentuh (mis. sudah di-ACC lalu menggantung) dihitung dari sentuhan
        // terakhir, bukan dari hari pertama diajukan.
        $diam = RopRequest::whereIn('status', [RopRequest::STATUS_DIAJUKAN, RopRequest::STATUS_DISETUJUI])
            ->where('updated_at', '<=', now()->subDays(RopService::HARI_KADALUARSA))
            ->get();

        foreach ($diam as $r) {
            $this->line("kadaluarsa: {$r->number} — diam sejak {$r->updated_at?->format('Y-m-d')}");
            if ($kering) continue;

            try {
                $rop->kadaluarsakan($r);
            } catch (\Throwable $e) {
                $this->error("  gagal: {$e->getMessage()}");
            }
        }

        $this->info(($kering ? '[uji coba] ' : '') . count($tua) . ' diingatkan, ' . count($diam) . ' dikadaluarsakan.');

        return self::SUCCESS;
    }
}
