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

    /**
     * Kategori pengeluaran transport (Bensin, Parkir, Tol, dll).
     * NULL untuk top up / transfer / data lama.
     */
    public const CATEGORIES = [
        'bensin'      => 'Bensin',
        'parkir'      => 'Parkir',
        'tol'         => 'Tol',
        'taxi_ojek'   => 'Taksi / Ojek',
        'transport_lain' => 'Transport Lain',
        'operasional' => 'Operasional',
        'lain_lain'   => 'Lain-lain',
    ];

    protected $fillable = [
        'period_id',
        'trx_date',
        'description',
        'category',
        'direction',
        'amount',
        'fund_source_id',
        'is_topup',
        'is_transfer',
        'ref_no',
        'attachment_path',
        'created_by',
        'updated_by',
        'edited_at',
        'deleted_by',
    ];

    protected $casts = [
        'period_id'      => 'integer',
        'trx_date'       => 'date',
        'amount'         => 'decimal:2',
        'fund_source_id' => 'integer',
        'is_topup'       => 'boolean',
        'is_transfer'    => 'boolean',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'edited_at'      => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(TransportCostPeriod::class, 'period_id', 'id');
    }

    public function fundSource()
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id', 'id');
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
