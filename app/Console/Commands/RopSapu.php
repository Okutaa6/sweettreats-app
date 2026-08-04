<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use App\Models\RopRequest;
use App\Services\RopService;
use Illuminate\Console\Command;

/**
 * rop:sapu
 *
 * Penyapu harian antrian pengajuan — tiga tugas yang sama-sama menjaga
 * "tidak ada pengajuan yang tenggelam":
 *
 *   1. MENUNGGU ACC  — menunggu keputusan > 3 hari → ingatkan Finance.
 *   2. BELUM DIBAYAR — sudah di-ACC > 3 hari tapi uangnya belum keluar →
 *      ingatkan Finance juga. Ini yang paling gampang terlupakan: di mata
 *      pengaju statusnya sudah "disetujui", jadi dia berhenti menagih.
 *   3. KADALUARSA    — pengajuan yang BELUM diputuskan dan didiamkan 30 hari
 *      ditutup otomatis + pengaju diberi tahu supaya bisa mengajukan ulang.
 *
 * Kadaluarsa sengaja HANYA untuk yang masih `diajukan`. Yang sudah disetujui
 * berarti Finance sudah berkomitmen membayar — menutupnya diam-diam justru
 * menyembunyikan kewajiban yang belum tuntas. Yang macet di situ diingatkan,
 * bukan dihapus dari pandangan.
 *
 * Semua pengingat dibatasi sekali sehari per pengajuan (kolom reminded_at),
 * supaya kotak notifikasi tidak jadi kebisingan yang malah diabaikan.
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

        $belumDiingatkanHariIni = fn ($q) => $q->where(function ($w) {
            $w->whereNull('reminded_at')->orWhere('reminded_at', '<', now()->subDay());
        });

        // Daftar kadaluarsa dikunci SEBELUM pengingat dikirim. Kalau diambil
        // belakangan, pengajuan yang baru saja diingatkan ikut tersaring keluar
        // dan tidak pernah tertutup.
        $diam = RopRequest::menunggu()
            ->where('updated_at', '<=', now()->subDays(RopService::HARI_KADALUARSA))
            ->get();

        /* ── 1. Menunggu ACC kelamaan ── */
        // Yang hari ini akan ditutup dikecualikan: mengirim "tolong di-ACC"
        // lalu "sudah kadaluarsa" dalam menit yang sama cuma bikin bingung.
        $tua = $belumDiingatkanHariIni(
            RopRequest::menunggu()
                ->where('created_at', '<=', now()->subDays(RopService::HARI_REMINDER))
                ->whereNotIn('id', $diam->pluck('id')->all() ?: [0])
        )->with('requester')->get();

        foreach ($tua as $r) {
            $this->line("ingatkan ACC: {$r->number} — {$r->umurHari()} hari menunggu");
            if ($kering) continue;

            AdminNotification::notifyRole(
                RopService::ROLE_PENYETUJU,
                'rop_menunggu_lama',
                'Pengajuan menunggu ' . $r->umurHari() . ' hari',
                $r->number . ' (' . $this->rupiah($r->amount) . ') dari '
                    . ($r->requester?->nama ?: 'pengaju') . ' belum diputuskan.',
                url('finance/rop'),
            );
            $this->tandaiDiingatkan($r);
        }

        /* ── 2. Disetujui tapi belum dibayar ── */
        $macet = $belumDiingatkanHariIni(
            RopRequest::disetujui()->where('reviewed_at', '<=', now()->subDays(RopService::HARI_REMINDER))
        )->with('requester')->get();

        foreach ($macet as $r) {
            $hari = (int) ($r->reviewed_at?->startOfDay()->diffInDays(now()->startOfDay()) ?? 0);
            $this->line("ingatkan BAYAR: {$r->number} — disetujui {$hari} hari lalu, belum dibayar");
            if ($kering) continue;

            AdminNotification::notifyRole(
                RopService::ROLE_PENYETUJU,
                'rop_belum_dibayar',
                'Disetujui ' . $hari . ' hari lalu, belum dibayar',
                $r->number . ' (' . $this->rupiah($r->amount) . ') untuk '
                    . ($r->requester?->nama ?: 'pengaju') . ' masih menunggu transfer.',
                url('finance/rop?tab=disetujui'),
            );
            $this->tandaiDiingatkan($r);
        }

        /* ── 3. Kadaluarsa (daftarnya sudah dikunci di awal) ── */
        $gagal = 0;
        foreach ($diam as $r) {
            $this->line("kadaluarsa: {$r->number} — diam sejak {$r->updated_at?->format('Y-m-d')}");
            if ($kering) continue;

            try {
                $rop->kadaluarsakan($r);
            } catch (\Throwable $e) {
                $this->error("  gagal: {$e->getMessage()}");
                $gagal++;
            }
        }

        $this->info(($kering ? '[uji coba] ' : '')
            . count($tua) . ' diingatkan (ACC), '
            . count($macet) . ' diingatkan (bayar), '
            . count($diam) . ' dikadaluarsakan'
            . ($gagal ? ", {$gagal} gagal" : '') . '.');

        return self::SUCCESS;
    }

    /**
     * Stempel "sudah diingatkan hari ini" TANPA menyentuh updated_at.
     *
     * Ini bukan kerapian: `updated_at` adalah ukuran "sudah didiamkan berapa
     * lama" yang dipakai aturan kadaluarsa. Kalau pengingat harian ikut
     * memperbaruinya, pengajuan tua akan diingatkan setiap hari dan justru
     * TIDAK PERNAH kadaluarsa — persis kebalikan dari maksudnya.
     */
    private function tandaiDiingatkan(RopRequest $r): void
    {
        $r->timestamps = false;
        $r->forceFill(['reminded_at' => now()])->save();
        $r->timestamps = true;
    }

    private function rupiah($angka): string
    {
        return 'Rp ' . number_format((float) $angka, 0, ',', '.');
    }
}
