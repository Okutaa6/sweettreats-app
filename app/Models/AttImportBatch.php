<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jejak tiap upload CSV absen. Lihat docs/rencana-rekonsiliasi-absensi.md §5.2.
 */
class AttImportBatch extends Model
{
    protected $table = 'att_import_batch';

    protected $fillable = [
        'raw_branch_id', 'uploaded_by', 'file_name', 'format',
        'periode_from', 'periode_to',
        'total_rows', 'new_rows', 'dup_rows', 'unmapped_rows',
        'diagnostic', 'uploaded_at',
    ];

    protected $casts = [
        'diagnostic' => 'array',
        'periode_from' => 'date',
        'periode_to' => 'date',
        'uploaded_at' => 'datetime',
        'total_rows' => 'integer',
        'new_rows' => 'integer',
        'dup_rows' => 'integer',
        'unmapped_rows' => 'integer',
    ];

    public function scans()
    {
        return $this->hasMany(AttScan::class, 'batch_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }
}
