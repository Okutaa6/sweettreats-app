<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CashDepositTransaction
 *
 * Ledger setoran kas:
 *   - direction='in'  → pembayaran pasien (cash in), raw_customer_id wajib.
 *   - direction='out' → setor tunai (cash out), is_setor=1.
 */
class CashDepositTransaction extends Model
{
    protected $table = 'cash_deposit_transactions';

    public const DIR_IN  = 'in';
    public const DIR_OUT = 'out';

    protected $fillable = [
        'period_id',
        'trx_date',
        'raw_customer_id',
        'raw_branch_id',
        'description',
        'direction',
        'amount',
        'is_setor',
        'ref_no',
        'created_by',
    ];

    protected $casts = [
        'period_id'  => 'integer',
        'trx_date'   => 'date',
        'amount'     => 'decimal:2',
        'is_setor'   => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(CashDepositPeriod::class, 'period_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(RawCustomer::class, 'raw_customer_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
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
