<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Header pengisian checklist mingguan — 1 row per (user, ISO week).
 * State machine identik dengan ChecklistSubmission (harian).
 *
 * week_start_date = Senin minggu tersebut. Items berisi 1 row per task aktif
 * (kategori cadence=weekly) yang punya day_of_week — direnderkan grouped
 * per hari di view (sesuai form kertas: Selasa column, Kamis column, dst).
 */
class ChecklistWeeklySubmission extends Model
{
    use HasUuids;

    protected $table = 'sys_checklist_weekly_submission';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'division_id', 'raw_branch_id',
        'week_start_date', 'year_week',
        'status', 'total_tasks', 'done_tasks',
        'reviewer_user_id', 'review_notes', 'excuse_reason', 'submit_note',
        'submitted_at', 'reviewed_at', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'total_tasks'     => 'integer',
        'done_tasks'      => 'integer',
        'submitted_at'    => 'datetime',
        'reviewed_at'     => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    const STATUS_DRAFT     = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED  = 'approved';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_MISSED    = 'missed';
    const STATUS_EXCUSED   = 'excused';

    public static function statusOptions(): array
    {
        // Pakai label yang sama dengan harian supaya UI konsisten.
        return ChecklistSubmission::statusOptions();
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::statusOptions()[$this->status]['color'] ?? 'neutral';
    }

    public function progressPct(): int
    {
        if (! $this->total_tasks) return 0;
        return (int) round(($this->done_tasks / $this->total_tasks) * 100);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    /** Nomor hari (ISO 2–7) yang ditandai "tidak perlu diisi" di minggu ini. */
    public function excusedDayNumbers(): array
    {
        return $this->dayExcuses->pluck('day_of_week')->map(fn ($d) => (int) $d)->all();
    }

    public function isDayExcused(int $iso): bool
    {
        return in_array($iso, $this->excusedDayNumbers(), true);
    }

    /** Row submit hari tertentu (dari relasi), null bila belum dikirim. */
    public function daySubmit(int $iso): ?ChecklistWeeklyDaySubmit
    {
        return $this->daySubmits->firstWhere('day_of_week', $iso);
    }

    /** Status review hari: pending|approved|rejected, atau null bila belum dikirim. */
    public function dayReviewStatus(int $iso): ?string
    {
        return optional($this->daySubmit($iso))->review_status;
    }

    /**
     * Nomor hari (ISO 2–7) yang "terkirim & tidak ditolak" (pending/approved).
     * Hari yang di-reject dianggap BELUM terkirim (dibuka lagi untuk diperbaiki),
     * jadi tak dihitung di sini — supaya lock & roll-up status benar.
     */
    public function submittedDayNumbers(): array
    {
        return $this->daySubmits
            ->filter(fn ($ds) => $ds->review_status !== ChecklistWeeklyDaySubmit::REVIEW_REJECTED)
            ->pluck('day_of_week')->map(fn ($d) => (int) $d)->all();
    }

    public function isDaySubmitted(int $iso): bool
    {
        return in_array($iso, $this->submittedDayNumbers(), true);
    }

    /** Kunci sisi karyawan: hari terkunci bila terkirim & belum ditolak. */
    public function isDayLocked(int $iso): bool
    {
        return $this->isDaySubmitted($iso);
    }

    private function dayNumbersByReview(string $reviewStatus): array
    {
        return $this->daySubmits
            ->filter(fn ($ds) => $ds->review_status === $reviewStatus)
            ->pluck('day_of_week')->map(fn ($d) => (int) $d)->all();
    }

    /** Hari aktif yang menunggu review supervisor. */
    public function pendingReviewDayNumbers(): array
    {
        return array_values(array_intersect(
            $this->activeDayNumbers(),
            $this->dayNumbersByReview(ChecklistWeeklyDaySubmit::REVIEW_PENDING)
        ));
    }

    public function approvedDayNumbers(): array
    {
        return $this->dayNumbersByReview(ChecklistWeeklyDaySubmit::REVIEW_APPROVED);
    }

    public function rejectedDayNumbers(): array
    {
        return $this->dayNumbersByReview(ChecklistWeeklyDaySubmit::REVIEW_REJECTED);
    }

    /**
     * Hari (ISO 2–7) yang WAJIB disubmit: punya minimal 1 item DAN tidak
     * dikecualikan penuh. Hari kosong / dikecualikan tak perlu tombol submit.
     */
    public function activeDayNumbers(): array
    {
        $excused = $this->excusedDayNumbers();
        return $this->items
            ->pluck('day_of_week')
            ->map(fn ($d) => (int) $d)
            ->unique()
            ->reject(fn ($iso) => in_array($iso, $excused, true))
            ->values()
            ->all();
    }

    /**
     * true bila setiap hari ber-tugas sudah "beres di level hari": submitted
     * ATAU dikecualikan. Dipakai untuk auto-finalize header ke 'submitted'.
     * (Termasuk kasus seluruh minggu dikecualikan — semua hari excused.)
     */
    public function allDaysAccountedFor(): bool
    {
        $daysWithItems = $this->items
            ->pluck('day_of_week')->map(fn ($d) => (int) $d)->unique()->values()->all();
        if (count($daysWithItems) === 0) return false; // tak ada tugas sama sekali

        $excused   = $this->excusedDayNumbers();
        $submitted = $this->submittedDayNumbers();
        foreach ($daysWithItems as $iso) {
            if (! in_array($iso, $excused, true) && ! in_array($iso, $submitted, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Jumlah task yang "beres": sudah dicentang, ATAU item itu dikecualikan
     * sendiri (is_excepted), ATAU jatuh di hari yang dikecualikan penuh. Dipakai
     * untuk gating submit & progress form berjalan (bukan menggantikan done_tasks
     * yang tetap = benar-benar dikerjakan).
     */
    public function satisfiedCount(): int
    {
        $excused = $this->excusedDayNumbers();

        return $this->items
            ->filter(fn ($item) => $item->is_done
                || $item->is_excepted
                || in_array((int) $item->day_of_week, $excused, true))
            ->count();
    }

    /** Jumlah item yang dikecualikan per-item (untuk penanda di monitoring/export). */
    public function exceptedCount(): int
    {
        return $this->items->where('is_excepted', true)->count();
    }

    /** Persentase "beres" (satisfied) — untuk tampilan progress. */
    public function satisfiedPct(): int
    {
        if (! $this->total_tasks) return 0;
        return (int) round(($this->satisfiedCount() / $this->total_tasks) * 100);
    }

    public function isWeekComplete(): bool
    {
        return $this->total_tasks > 0 && $this->satisfiedCount() === $this->total_tasks;
    }

    /**
     * "Minggu 24 Jun – 30 Jun 2026" — display friendly.
     */
    public function weekLabel(): string
    {
        $start = $this->week_start_date;
        $end   = $start->copy()->addDays(6);
        if ($start->month === $end->month) {
            return $start->translatedFormat('d') . '–' . $end->translatedFormat('d F Y');
        }
        return $start->translatedFormat('d M') . ' – ' . $end->translatedFormat('d M Y');
    }

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Users::class, 'reviewer_user_id', 'id');
    }

    public function division()
    {
        return $this->belongsTo(ChecklistDivision::class, 'division_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(ChecklistWeeklySubmissionItem::class, 'submission_id', 'id')
            ->orderBy('day_of_week')
            ->orderBy('category_sort_order')
            ->orderBy('task_sort_order');
    }

    public function dayExcuses()
    {
        return $this->hasMany(ChecklistWeeklyDayExcuse::class, 'submission_id', 'id')
            ->orderBy('day_of_week');
    }

    public function daySubmits()
    {
        return $this->hasMany(ChecklistWeeklyDaySubmit::class, 'submission_id', 'id')
            ->orderBy('day_of_week');
    }

    /**
     * Turunkan status header dari kumpulan status review PER-HARI (roll-up).
     * Aturan (di antara hari AKTIF = punya item & tak dikecualikan):
     *   - ada hari rejected            → header 'rejected' (editable, karyawan perbaiki hari itu)
     *   - belum semua hari terkirim    → header 'draft'    (masih diisi)
     *   - semua hari sudah approved     → header 'approved' (final)
     *   - selainnya (semua terkirim)    → header 'submitted' (sebagian masih pending review)
     * Tidak menulis audit sendiri — aksi per-hari (submit/approve/reject) yang mengaudit.
     * Lewati jalur legacy whole-week 'excused'/'missed'.
     *
     * Catatan: relasi items/dayExcuses/daySubmits harus fresh; pemanggil sebaiknya
     * $sub->load(['items','dayExcuses','daySubmits']) dulu sesudah mengubah data hari.
     */
    public function recomputeStatus(): void
    {
        if (in_array($this->status, [self::STATUS_EXCUSED, self::STATUS_MISSED], true)) return;

        $target = $this->deriveStatus();
        if ($target === null) return;

        $total = $this->items()->count();
        $done  = $this->items()->where('is_done', 1)->count();
        $attrs = ['total_tasks' => $total, 'done_tasks' => $done, 'updated_at' => now()];

        if ($target !== $this->status) {
            $attrs['status'] = $target;
            if (in_array($target, [self::STATUS_SUBMITTED, self::STATUS_APPROVED], true) && ! $this->submitted_at) {
                $attrs['submitted_at'] = now();
            }
            if ($target === self::STATUS_APPROVED) {
                $attrs['reviewed_at'] = now();
            }
            if (in_array($target, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
                // Kembali editable — bersihkan jejak review header (detail ada per-hari).
                $attrs['reviewed_at']      = null;
                $attrs['reviewer_user_id'] = null;
            }
        }

        $this->update($attrs);
    }

    /** Status header yang seharusnya berdasarkan state hari. null = tak perlu ubah. */
    private function deriveStatus(): ?string
    {
        $active = $this->activeDayNumbers();

        // Tak ada hari aktif: entah tak ada tugas, atau semua hari dikecualikan.
        if (count($active) === 0) {
            $hasItems = $this->items->pluck('day_of_week')->unique()->isNotEmpty();
            return $hasItems ? self::STATUS_SUBMITTED : self::STATUS_DRAFT;
        }

        $submitted = $this->submittedDayNumbers(); // pending/approved (bukan rejected)
        $approved  = $this->approvedDayNumbers();
        $rejected  = $this->rejectedDayNumbers();

        if (count(array_intersect($active, $rejected)) > 0) return self::STATUS_REJECTED;
        if (count(array_diff($active, $submitted)) > 0)     return self::STATUS_DRAFT;
        if (count(array_diff($active, $approved)) === 0)    return self::STATUS_APPROVED;
        return self::STATUS_SUBMITTED;
    }

    /**
     * Bootstrap item mingguan dari katalog aktif (kategori cadence=weekly + task
     * ber-day_of_week 2..7) untuk divisi + cabang tsb. No-op kalau submission
     * sudah punya item. Satu sumber logika katalog — dipakai MyChecklistController
     * (form karyawan) & ChecklistExcuseService (auto-excuse cuti/izin).
     */
    public function bootstrapItems(string $divisionId, string $branchId): void
    {
        if ($this->items()->count() > 0) return;

        $categories = ChecklistCategory::query()
            ->where('division_id', $divisionId)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->where('cadence', ChecklistCategory::CADENCE_WEEKLY)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('raw_branch_id')->orWhere('raw_branch_id', $branchId);
            })
            ->with(['tasks' => function ($q) {
                $q->whereNull('deleted_at')->where('is_active', 1)
                  ->whereNotNull('day_of_week')
                  ->whereIn('day_of_week', [2, 3, 4, 5, 6, 7])
                  ->orderBy('day_of_week')->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $rows = [];
        foreach ($categories as $cat) {
            foreach ($cat->tasks as $task) {
                $rows[] = [
                    'id'                      => (string) Str::uuid(),
                    'submission_id'           => $this->id,
                    'task_id'                 => $task->id,
                    'category_title_snapshot' => $cat->name,
                    'task_title_snapshot'     => $task->title,
                    'category_sort_order'     => (int) $cat->sort_order,
                    'task_sort_order'         => (int) $task->sort_order,
                    'day_of_week'             => (int) $task->day_of_week,
                    'is_done'                 => 0,
                    'note'                    => null,
                    'requires_photo'          => (int) $task->requires_photo,
                    'photo_before_after'      => (int) $task->photo_before_after,
                    'photo_path'              => null,
                    'done_at'                 => null,
                    'created_at'              => now(),
                ];
            }
        }
        if ($rows) DB::table('sys_checklist_weekly_submission_item')->insert($rows);

        $this->update(['total_tasks' => count($rows), 'updated_at' => now()]);
    }
}
