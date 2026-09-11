<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Header pengisian checklist 1 hari per karyawan.
 *
 * State machine (lihat plan):
 *   draft → submitted → approved | rejected
 *   rejected → draft (resubmit)
 *   draft → missed (cron Fase 2)
 *
 * division_id & raw_branch_id di-snapshot dari user_position aktif
 * saat record dibuat — supaya kalau user pindah, history aman.
 */
class ChecklistSubmission extends Model
{
    use HasUuids;

    protected $table = 'sys_checklist_submission';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'division_id', 'raw_branch_id', 'submission_date',
        'status', 'total_tasks', 'done_tasks',
        'reviewer_user_id', 'review_notes', 'excuse_reason',
        'excuse_source', 'excuse_ref_id',
        'submitted_at', 'reviewed_at', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'submission_date' => 'date',
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

    /**
     * Jam tutup hari checklist. "Hari checklist" berjalan 06:00 → 06:00, bukan
     * tengah malam: klinik sering baru beres larut, dan sebelum ini pengisian
     * terpotong tepat saat cron 00:10 mencap "Tidak Diisi". Jadi sebelum jam 6
     * pagi yang terbuka MASIH checklist tanggal kemarin.
     *
     * Dipakai lewat helper checklistOperationalDate() — jangan bandingkan jam
     * secara manual di tempat lain.
     */
    const DAY_CUTOFF_HOUR = 6;

    /** Pengecualian dibuat karyawan sendiri (tombol "hari ini tidak perlu diisi"). */
    const EXCUSE_SOURCE_MANUAL = 'manual';
    /** Pengecualian otomatis dari cuti/izin/sakit approved (ChecklistExcuseService). */
    const EXCUSE_SOURCE_SYSTEM = 'system';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT     => ['label' => 'Belum Dikirim',     'color' => 'neutral', 'desc' => 'Sudah dibuat, belum dikirim ke supervisor'],
            self::STATUS_SUBMITTED => ['label' => 'Menunggu Approval',  'color' => 'warning', 'desc' => 'Sudah disubmit, menunggu review supervisor'],
            self::STATUS_APPROVED  => ['label' => 'Disetujui',          'color' => 'success', 'desc' => 'Telah diverifikasi supervisor'],
            self::STATUS_REJECTED  => ['label' => 'Ditolak',            'color' => 'danger',  'desc' => 'Perlu diperbaiki — lihat catatan supervisor'],
            self::STATUS_MISSED    => ['label' => 'Tidak Diisi',        'color' => 'slate',   'desc' => 'Tanggal sudah lewat — checklist tidak diisi'],
            self::STATUS_EXCUSED   => ['label' => 'Dikecualikan',       'color' => 'coffee',  'desc' => 'Hari ini tidak perlu diisi (mis. sedang menangani pasien)'],
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

    public function progressPct(): int
    {
        if (! $this->total_tasks) return 0;
        return (int) round(($this->done_tasks / $this->total_tasks) * 100);
    }

    /**
     * Jumlah task yang "beres": sudah dicentang ATAU dikecualikan (tidak berlaku
     * hari ini). Dipakai untuk gating submit & progress tampilan — bukan
     * menggantikan done_tasks yang tetap = benar-benar dikerjakan.
     */
    public function satisfiedCount(): int
    {
        return $this->items
            ->filter(fn ($item) => $item->is_done || $item->is_excepted)
            ->count();
    }

    /** Jumlah item yang dikecualikan (untuk penanda di monitoring/export). */
    public function exceptedCount(): int
    {
        return $this->items->where('is_excepted', true)->count();
    }

    /** Persentase "beres" (dicentang + dikecualikan) — untuk tampilan progress. */
    public function satisfiedPct(): int
    {
        if (! $this->total_tasks) return 0;
        return (int) round(($this->satisfiedCount() / $this->total_tasks) * 100);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    /**
     * Pengecualian OTOMATIS dari cuti/izin/sakit yang disetujui — tak boleh
     * dibatalkan manual (karyawan maupun supervisor): reconcile akan memasangnya
     * lagi begitu halaman dibuka, jadi pembatalan manual cuma jadi ilusi.
     * Cabutnya lewat menolak/mencabut pengajuannya di modul Kehadiran.
     *
     * Aman di DB yang belum di-apply 2026_09_03_daily_excuse_source.sql —
     * kolomnya null → dianggap manual (perilaku lama).
     */
    public function isSystemExcused(): bool
    {
        return $this->status === self::STATUS_EXCUSED
            && ($this->excuse_source ?? self::EXCUSE_SOURCE_MANUAL) === self::EXCUSE_SOURCE_SYSTEM;
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
        return $this->hasMany(ChecklistSubmissionItem::class, 'submission_id', 'id')
            ->orderBy('category_sort_order')
            ->orderBy('task_sort_order');
    }
}
