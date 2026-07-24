<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Scan mentah dari mesin — APPEND-ONLY, jangan pernah di-UPDATE.
 * Idempoten via `hash` = sha1(raw_branch_id|device_user_id|scanned_at).
 * Semua penilaian ada di tabel turunan (att_day, Fase B).
 * Lihat docs/rencana-rekonsiliasi-absensi.md §5.2.
 */
class AttScan extends Model
{
    protected $table = 'att_scan';

    /** Append-only: created_at diisi manual saat import, tidak ada updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'batch_id', 'raw_branch_id', 'device_user_id', 'user_id',
        'scanned_at', 'source', 'raw_row', 'hash', 'created_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'raw_row' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function batch()
    {
        return $this->belongsTo(AttImportBatch::class, 'batch_id', 'id');
    }
}
