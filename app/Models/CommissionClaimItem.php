<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionClaimItem extends Model
{
    protected $table = 'commission_claim_item';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'commission_claim_batch_id',
        'raw_commission_id',
        'amount',
        'created_at',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(CommissionClaimBatch::class, 'commission_claim_batch_id', 'id');
    }

    public function commission()
    {
        return $this->belongsTo(RawCommission::class, 'raw_commission_id', 'id');
    }
}
