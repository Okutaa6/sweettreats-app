<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Task individual dalam kategori. Saat karyawan submit checklist, judulnya
 * di-snapshot ke ChecklistSubmissionItem.task_title_snapshot — supaya edit
 * master tidak mengubah history.
 */
class ChecklistTask extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sys_checklist_task';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    const DELETED_AT = 'deleted_at';

    protected $fillable = [
        'id', 'category_id', 'title', 'description', 'day_of_week', 'weeks_of_month',
        'requires_photo', 'photo_before_after', 'sort_order', 'is_active', 'created_at', 'updated_at', 'deleted_at',
    ];

    protected $casts = [
        'requires_photo'    => 'boolean',
        'photo_before_after'=> 'boolean',
        'day_of_week'   => 'integer',
        'sort_order'    => 'integer',
        'is_active'     => 'boolean',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    /**
     * Day-of-week (ISO 2=Selasa … 7=Minggu) → label Indonesia.
     * Senin (1) tidak dipakai karena klinik tutup hari Senin.
     */
    public static function dayLabels(): array
    {
        return [
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
    }

    public static function dayShortLabels(): array
    {
        return [2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
    }

    public function dayLabel(): ?string
    {
        return $this->day_of_week ? (self::dayLabels()[$this->day_of_week] ?? null) : null;
    }

    /* ─── Minggu ke-berapa dalam bulan (rotasi bulanan) ─────────────────────
     * weeks_of_month: CSV "1,3" = task hanya berlaku di minggu ke-1 & ke-3.
     * NULL/kosong = SETIAP minggu (perilaku lama → backward-compatible).
     * Minggu-ke dihitung dari tanggal Senin (reset tiap bulan), lihat
     * ChecklistWeeklySubmission::weekOfMonthForDate().
     * ──────────────────────────────────────────────────────────────────── */

    /** Parse CSV weeks_of_month → array int terurut. `[]` bila null/kosong (=setiap minggu). */
    public function weeksOfMonthArray(): array
    {
        $raw = trim((string) $this->weeks_of_month);
        if ($raw === '') return [];

        $weeks = [];
        foreach (explode(',', $raw) as $part) {
            $n = (int) trim($part);
            if ($n >= 1 && $n <= 5) $weeks[$n] = $n;
        }
        sort($weeks);
        return array_values($weeks);
    }

    /**
     * Apakah task ini berlaku di minggu ke-$w (1..5) bulan berjalan?
     * Kosong = setiap minggu. Minggu ke-5 (parsial) memakai tugas minggu ke-4
     * sbg fallback — sesuai keputusan setup.
     */
    public function appliesToWeekOfMonth(int $w): bool
    {
        $weeks = $this->weeksOfMonthArray();
        if (empty($weeks))                            return true;   // setiap minggu
        if (in_array($w, $weeks, true))               return true;
        if ($w === 5 && in_array(4, $weeks, true))    return true;   // fallback mgg-5 → mgg-4
        return false;
    }

    /** Label ringkas utk badge setup, mis. "Tiap mgg" / "Mgg 1,3". */
    public function weeksLabel(): string
    {
        $weeks = $this->weeksOfMonthArray();
        return empty($weeks) ? 'Tiap mgg' : 'Mgg ' . implode(',', $weeks);
    }

    public function category()
    {
        return $this->belongsTo(ChecklistCategory::class, 'category_id', 'id');
    }
}
