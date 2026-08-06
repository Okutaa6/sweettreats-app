<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Permintaan karyawan untuk membuka kembali checklist yang tanggalnya sudah
 * lewat supaya bisa diisi & dikirim. Perlu persetujuan atasan lebih dulu.
 *
 * scope=daily   → target_date terisi (checklist harian).
 * scope=weekly  → week_start_date + day_of_week terisi (Form Mingguan per-hari).
 *
 * GRANT AKTIF = status=approved AND fulfilled_at IS NULL. Selama grant aktif,
 * tanggal/hari lampau tsb terbuka untuk diisi; begitu karyawan mengirim,
 * fulfilled_at diisi → grant habis, hari terkunci lagi (tak bisa dipakai ulang).
 * Pola alur approval meniru TopupRequest (pending → approved/rejected, 1 lapis).
 */
class ChecklistReopenRequest extends Model
{
    use HasUuids;

    protected $table = 'sys_checklist_reopen_request';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'division_id', 'raw_branch_id',
        'scope', 'target_date', 'week_start_date', 'day_of_week',
        'reason', 'status', 'reviewer_user_id', 'review_notes',
        'reviewed_at', 'fulfilled_at', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'target_date'     => 'date',
        'week_start_date' => 'date',
        'day_of_week'     => 'integer',
        'reviewed_at'     => 'datetime',
        'fulfilled_at'    => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    const SCOPE_DAILY  = 'daily';
    const SCOPE_WEEKLY = 'weekly';

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING  => ['label' => 'Menunggu Persetujuan', 'color' => 'warning'],
            self::STATUS_APPROVED => ['label' => 'Disetujui',            'color' => 'success'],
            self::STATUS_REJECTED => ['label' => 'Ditolak',              'color' => 'danger'],
        ];
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::statusOptions()[$this->status]['color'] ?? 'neutral';
    }

    /** Permintaan yang sudah disetujui tapi belum "dipakai" (belum dikirim). */
    public function isActiveGrant(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->fulfilled_at === null;
    }

    /** Label tanggal/hari target untuk tampilan ("Rabu, 05 Ags 2026" / "05 Ags 2026"). */
    public function targetLabel(): string
    {
        if ($this->scope === self::SCOPE_WEEKLY) {
            $iso   = (int) $this->day_of_week;
            $label = ChecklistTask::dayLabels()[$iso] ?? ('Hari ' . $iso);
            $date  = $this->week_start_date
                ? $this->week_start_date->copy()->addDays($iso - 1)->translatedFormat('d M Y')
                : '';
            return trim($label . ($date ? ', ' . $date : ''));
        }
        return optional($this->target_date)->translatedFormat('d M Y') ?? '-';
    }

    /* ─── Query grant aktif (dipakai controller untuk gating) ──────────────── */

    /** Grant harian aktif untuk (user, tanggal), atau null. */
    public static function activeDailyGrant(string $userId, CarbonInterface $date): ?self
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('scope', self::SCOPE_DAILY)
            ->whereDate('target_date', $date->toDateString())
            ->where('status', self::STATUS_APPROVED)
            ->whereNull('fulfilled_at')
            ->orderByDesc('reviewed_at')
            ->first();
    }

    /** Grant mingguan aktif untuk (user, minggu, iso hari), atau null. */
    public static function activeWeeklyGrant(string $userId, CarbonInterface $weekStart, int $iso): ?self
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('scope', self::SCOPE_WEEKLY)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->where('day_of_week', $iso)
            ->where('status', self::STATUS_APPROVED)
            ->whereNull('fulfilled_at')
            ->orderByDesc('reviewed_at')
            ->first();
    }

    /**
     * Apakah target ini sudah punya permintaan "aktif" (pending ATAU grant belum
     * dipakai)? Dipakai mencegah duplikat saat karyawan mengajukan.
     */
    public static function hasOpenRequestDaily(string $userId, CarbonInterface $date): bool
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('scope', self::SCOPE_DAILY)
            ->whereDate('target_date', $date->toDateString())
            ->where(function ($q) {
                $q->where('status', self::STATUS_PENDING)
                  ->orWhere(fn ($q2) => $q2->where('status', self::STATUS_APPROVED)->whereNull('fulfilled_at'));
            })
            ->exists();
    }

    public static function hasOpenRequestWeekly(string $userId, CarbonInterface $weekStart, int $iso): bool
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('scope', self::SCOPE_WEEKLY)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->where('day_of_week', $iso)
            ->where(function ($q) {
                $q->where('status', self::STATUS_PENDING)
                  ->orWhere(fn ($q2) => $q2->where('status', self::STATUS_APPROVED)->whereNull('fulfilled_at'));
            })
            ->exists();
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
}
