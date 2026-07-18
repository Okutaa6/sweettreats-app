<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jejak approval per lapis untuk att_request. Lihat §5.4.
 */
class AttRequestApproval extends Model
{
    protected $table = 'att_request_approval';

    /** Append-only: created_at diisi manual, tak ada updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'request_id', 'level', 'expected_role_id', 'approver_user_id', 'action', 'catatan', 'acted_at', 'created_at',
    ];

    protected $casts = ['acted_at' => 'datetime'];

    public function request()
    {
        return $this->belongsTo(AttRequest::class, 'request_id', 'id');
    }
}
