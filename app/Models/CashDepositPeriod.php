<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CashDepositPeriod
 *
 * Periode bulanan setoran kas pasien. Beda dengan PettyCash, tidak ada
 * float_amount (bukan imprest) — saldo berkembang dari pembayaran pasien,
 * lalu di-zero-kan saat setor tunai.
 *
 * Carry-forward: opening bulan ini = closing bulan lalu (kalau ada sisa
 * belum tersetorkan).
 */
class CashDepositPeriod extends Model
{
    protected $table = 'cash_deposit_periods';

    protected $fillable = [
        'period_year',
        'period_month',
        'opening_balance',
        'closing_balance',
        'is_closed',
    ];

    protected $casts = [
        'period_year'     => 'integer',
        'period_month'    => 'integer',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'is_closed'       => 'boolean',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function transactions()
    {
        return $this->hasMany(CashDepositTransaction::class, 'period_id', 'id')
            ->whereNull('deleted_at')
            ->orderBy('trx_date')
            ->orderBy('id');
    }

    /**
     * Pastikan periode (year, month) ada. Carry-forward dari periode terakhir.
     */
    public static function ensurePeriod(int $year, int $month): self
    {
        $existing = static::where('period_year', $year)
            ->where('period_month', $month)
            ->first();
        if ($existing) return $existing;

        $prev = static::where(function ($q) use ($year, $month) {
                $q->where('period_year', '<', $year)
                  ->orWhere(function ($qq) use ($year, $month) {
                      $qq->where('period_year', $year)
                         ->where('period_month', '<', $month);
                  });
            })
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        $opening = 0.0;
        if ($prev) {
            if ($prev->closing_balance !== null) {
                $opening = (float) $prev->closing_balance;
            } else {
                $opening = (float) $prev->opening_balance + (float) CashDepositTransaction::netForPeriod($prev->id);
            }
        }

        return static::create([
            'period_year'     => $year,
            'period_month'    => $month,
            'opening_balance' => $opening,
            'closing_balance' => null,
            'is_closed'       => 0,
        ]);
    }

    public function isCurrentMonth(): bool
    {
        return $this->period_year === (int) date('Y')
            && $this->period_month === (int) date('n');
    }
}
