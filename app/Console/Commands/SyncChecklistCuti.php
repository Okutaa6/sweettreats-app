<?php

namespace App\Console\Commands;

use App\Models\AttRequest;
use App\Models\ChecklistSubmission;
use App\Services\ChecklistExcuseService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sinkronkan cuti/izin/sakit APPROVED → pengecualian checklist HARIAN.
 *
 * Dipakai sekali setelah rilis fitur, untuk memperbaiki baris yang terlanjur
 * dicap "Tidak Diisi" (missed) padahal orangnya sedang cuti — dulu tak ada
 * mekanisme apa pun yang menghubungkan att_request ke checklist harian.
 * Sesudah itu tak perlu dijadwalkan: hook approval + reconcile saat render +
 * jaring pengaman di checklist:mark-missed sudah menutup jalurnya.
 *
 * Aturan mana yang boleh ditimpa TIDAK diduplikasi di sini — semuanya milik
 * ChecklistExcuseService::reconcileDailyDate(). --dry-run pun memakai jalur
 * kode yang sama persis, hanya dibungkus transaksi yang di-rollback, supaya
 * yang dilaporkan benar-benar apa yang akan terjadi.
 */
class SyncChecklistCuti extends Command
{
    protected $signature = 'checklist:sinkron-cuti
        {--from= : Tanggal mulai (YYYY-MM-DD). Default: cuti approved paling awal.}
        {--to= : Tanggal akhir (YYYY-MM-DD). Default: hari ini.}
        {--user= : Batasi ke 1 user_id (opsional).}
        {--dry-run : Hanya laporkan perubahannya, tidak menulis ke database.}';

    protected $description = 'Sinkronkan cuti/izin/sakit approved → pengecualian checklist harian (perbaiki cap "Tidak Diisi" yang salah).';

    public function handle(ChecklistExcuseService $service): int
    {
        // Modul absensi tidak ada di semua environment (dev lokal belum punya).
        // Bukan kegagalan — memang tak ada yang bisa disinkronkan.
        if (! Schema::hasTable('att_request')) {
            $this->warn('Tabel att_request tidak ada di database ini — tidak ada yang disinkronkan.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user') ?: null;

        $range = $this->resolveRange($userId);
        if (! $range) {
            return self::FAILURE;
        }
        [$from, $to] = $range;

        $leaves = AttRequest::approved()
            ->whereIn('tipe', ChecklistExcuseService::EXCUSE_TYPES)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->whereDate('tanggal_from', '<=', $to->toDateString())
            ->whereDate('tanggal_to', '>=', $from->toDateString())
            ->orderBy('user_id')->orderBy('tanggal_from')
            ->get();

        $this->line(sprintf(
            '%sRentang %s s/d %s — %d pengajuan cuti/izin/sakit approved.',
            $dryRun ? '[DRY-RUN] ' : '',
            $from->toDateString(), $to->toDateString(), $leaves->count()
        ));

        if ($leaves->isEmpty()) {
            return self::SUCCESS;
        }

        // Dry-run memakai jalur tulis yang sebenarnya lalu di-rollback — tak ada
        // aturan yang perlu ditiru ulang, jadi laporannya tak mungkin meleset
        // dari perilaku aslinya.
        if ($dryRun) {
            DB::beginTransaction();
        }

        $changes = [];
        try {
            foreach ($leaves as $req) {
                $d1 = Carbon::parse($req->tanggal_from)->startOfDay()->max($from);
                $d2 = Carbon::parse($req->tanggal_to ?? $req->tanggal_from)->startOfDay()->min($to);

                for ($d = $d1->copy(); $d->lte($d2); $d->addDay()) {
                    if ($d->isoWeekday() === 1) continue;   // Senin — klinik tutup

                    $before = $this->statusOf($req->user_id, $d);
                    $service->reconcileDailyDate((string) $req->user_id, $d);
                    $after = $this->statusOf($req->user_id, $d);

                    if ($before !== $after) {
                        $changes[] = [
                            $req->user_id,
                            $d->toDateString(),
                            $req->tipe,
                            $before ?? '(belum ada baris)',
                            $after ?? '-',
                        ];
                    }
                }
            }
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }

        if (empty($changes)) {
            $this->info(($dryRun ? '[DRY-RUN] ' : '') . 'Tidak ada yang perlu diubah — semua sudah sinkron.');

            return self::SUCCESS;
        }

        $this->table(['user_id', 'tanggal', 'tipe', 'status lama', 'status baru'], $changes);
        $this->info(sprintf(
            '%s%d baris %s.',
            $dryRun ? '[DRY-RUN] ' : '',
            count($changes),
            $dryRun ? 'AKAN diubah (belum ditulis)' : 'diubah'
        ));

        return self::SUCCESS;
    }

    /** Status baris harian saat ini, atau null bila barisnya belum ada. */
    private function statusOf(string $userId, Carbon $date): ?string
    {
        return ChecklistSubmission::where('user_id', $userId)
            ->whereDate('submission_date', $date->toDateString())
            ->value('status');
    }

    /**
     * [from, to] yang diproses. Default sengaja menyapu SELURUH riwayat cuti
     * approved — data lama yang salah cap justru itu masalahnya.
     */
    private function resolveRange(?string $userId): ?array
    {
        $to = Carbon::today();
        if ($opt = $this->option('to')) {
            try {
                $to = Carbon::parse($opt)->startOfDay();
            } catch (\Throwable $e) {
                $this->error('Format --to tidak valid. Pakai YYYY-MM-DD.');

                return null;
            }
        }

        if ($opt = $this->option('from')) {
            try {
                $from = Carbon::parse($opt)->startOfDay();
            } catch (\Throwable $e) {
                $this->error('Format --from tidak valid. Pakai YYYY-MM-DD.');

                return null;
            }
        } else {
            $earliest = AttRequest::approved()
                ->whereIn('tipe', ChecklistExcuseService::EXCUSE_TYPES)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->min('tanggal_from');
            $from = $earliest ? Carbon::parse($earliest)->startOfDay() : $to->copy();
        }

        if ($from->gt($to)) {
            $this->error('--from tidak boleh lebih besar dari --to.');

            return null;
        }

        return [$from, $to];
    }
}
