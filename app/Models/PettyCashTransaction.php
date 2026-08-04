<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PettyCashTransaction
 *
 * Ledger buku kas kecil:
 *   - direction = 'in'  → cash in  (top up atau penerimaan lain)
 *   - direction = 'out' → cash out (pengeluaran)
 *   - is_topup = 1      → flag visual baris top up saldo
 *
 * Saldo berjalan TIDAK disimpan — dihitung saat render (lihat
 * PettyCashController::buildRows()).
 *
 * Soft-delete manual lewat kolom deleted_at supaya jejak audit tetap ada.
 */
class PettyCashTransaction extends Model
{
    protected $table = 'petty_cash_transactions';

    public const DIR_IN  = 'in';
    public const DIR_OUT = 'out';

    /**
     * Kategori pengeluaran (hanya relevan saat direction='out' & is_topup=0 & is_transfer=0).
     * NULL untuk top up / transfer / data lama.
     */
    public const CATEGORIES = [
        'makan'       => 'Konsumsi',
        'transport'   => 'Transport',
        'bensin'      => 'Bensin',
        'operasional' => 'Operasional',
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
        return $this->belongsTo(PettyCashPeriod::class, 'period_id', 'id');
    }

    public function fundSource()
    {
        return $this->belongsTo(FundSource::class, 'fund_source_id', 'id');
    }

    /* ───────── Scopes ───────── */

    public function scopeActive($q)
    {
        return $q->whereNull('deleted_at');
    }

    public function scopeForPeriod($q, int $periodId)
    {
        return $q->where('period_id', $periodId);
    }

    /* ───────── Helpers ───────── */

    /**
     * Net (sum cash_in - cash_out) untuk 1 periode. Dipakai saat
     * carry-forward saldo (ensurePeriod).
     */
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
