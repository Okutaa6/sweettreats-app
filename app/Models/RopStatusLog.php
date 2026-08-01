<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RopStatusLog
 *
 * Jejak audit tiap transisi status pengajuan. Ditulis SEKALI oleh
 * RopService, tidak pernah di-update/hapus — ini sumber timeline
 * "siapa & kapan" di layar detail pengaju.
 */
class RopStatusLog extends Model
{
    protected $table = 'rop_status_log';
    public $timestamps = false;

    protected $fillable = [
        'rop_request_id', 'dari_status', 'ke_status', 'actor_id', 'catatan', 'created_at',
    ];

    protected $casts = [
        'rop_request_id' => 'integer',
        'created_at'     => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(RopRequest::class, 'rop_request_id', 'id');
    }

    public function actor()
    {
        return $this->belongsTo(Users::class, 'actor_id', 'id');
    }
}
