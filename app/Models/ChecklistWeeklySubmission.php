<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
}
