<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = SATU foto bukti pada item Form Mingguan.
 *
 * Menggantikan model lama "1 kolom = 1 foto" (photo_path / photo_before_path /
 * photo_after_path di sys_checklist_weekly_submission_item). Kolom-kolom itu
 * TIDAK dibuang: aplikasi tetap mengisinya dengan foto PERTAMA tiap slot
 * (lihat MyChecklistController::syncPhotoMirror) supaya
 * ChecklistWeeklySubmission::missingPhotos() tetap nol-query dan rollback cukup
 * revert kode.
 *
 * Tabelnya dibuat lewat database/sql/2026_09_17_checklist_mingguan_multi_foto.sql
 * (skema di repo ini di-apply manual, bukan lewat artisan migrate).
 */
class ChecklistWeeklySubmissionPhoto extends Model
{
    use HasUuids;

    protected $table = 'sys_checklist_weekly_submission_photo';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    /** Batas foto per slot. Ditegakkan di server, bukan cuma disembunyikan di UI. */
    public const MAX_PER_SLOT = 5;

    public const SLOT_SINGLE = 'single';
    public const SLOT_BEFORE = 'before';
    public const SLOT_AFTER  = 'after';

    protected $fillable = [
        'id', 'item_id', 'slot', 'path', 'sort_order', 'created_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'created_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(ChecklistWeeklySubmissionItem::class, 'item_id', 'id');
    }

    /** Slot yang dikenal. Dipakai untuk memvalidasi parameter rute. */
    public static function slots(): array
    {
        return [self::SLOT_SINGLE, self::SLOT_BEFORE, self::SLOT_AFTER];
    }

    /**
     * Nama kolom "cermin" di tabel item untuk slot ini — kolom lama yang tetap
     * diisi foto pertama slot tsb.
     */
    public static function mirrorColumn(string $slot): string
    {
        return $slot === self::SLOT_SINGLE ? 'photo_path' : "photo_{$slot}_path";
    }
}
