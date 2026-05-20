<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TransportCostTransaction
 *
 * Ledger biaya transportasi. Struktur kembaran PettyCashTransaction.
 */
class TransportCostTransaction extends Model
{
    protected $table = 'transport_cost_transactions';

    public const DIR_IN  = 'in';
    public const DIR_OUT = 'out';

    protected $fillable = [
        'period_id',
        'trx_date',
        'description',
        'direction',
        'amount',
        'is_topup',
        'ref_no',
        'created_by',
    ];

    protected $casts = [
        'period_id'  => 'integer',
        'trx_date'   => 'date',
        'amount'     => 'decimal:2',
        'is_topup'   => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(TransportCostPeriod::class, 'period_id', 'id');
    }

    public function scopeActive($q)
    {
        return $q->whereNull('deleted_at');
    }

    public function scopeForPeriod($q, int $periodId)
    {
        return $q->where('period_id', $periodId);
    }

    public static function netForPeriod(int $periodId): float
    {
        $rows = static::active()->forPeriod($periodId)->get(['direction', 'amount']);
        $net = 0.0;
        foreach ($rows as $r) {
            $amt = (float) $r->amount;
            $net += $r->direction === self::DIR_IN ? $amt : -$amt;
        }
        return $net;
    }
}
