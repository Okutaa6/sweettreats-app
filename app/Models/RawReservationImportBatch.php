<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawReservationImportBatch extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_reservation_import_batch';

    protected $fillable = [
        'id',
        'raw_branch_id',
        'uploaded_by',
        'file_name',
        'total_rows',
        'parsed_rows',
        'skipped_rows',
        'promoted_rows',
        'uploaded_at',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $casts = [
        'uploaded_at' => 'datetime',
        'total_rows' => 'integer',
        'parsed_rows' => 'integer',
        'skipped_rows' => 'integer',
        'promoted_rows' => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function rows()
    {
        return $this->hasMany(RawCusReservationStaging::class, 'batch_id', 'id');
    }

    public function uploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by', 'id');
    }
}
