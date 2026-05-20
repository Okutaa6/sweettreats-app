<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PettyCashPeriod
 *
 * Periode bulanan kas kecil (imprest / dana tetap).
 *   - 1 row per (period_year, period_month).
 *   - opening_balance = closing_balance bulan sebelumnya (carry-forward).
 *   - float_amount = nilai dana tetap (default 1.500.000).
 *   - is_closed = 1 → periode read-only.
 *
 * Saldo akhir (closing) di-compute saat render, baru diisi DB ketika periode
 * ditutup atau saat ensurePeriod() butuh "snapshot" untuk carry-forward.
 */
class PettyCashPeriod extends Model
{
    protected $table = 'petty_cash_periods';

    protected $fillable = [
        'period_year',
        'period_month',
        'float_amount',
        'opening_balance',
        'closing_balance',
        'is_closed',
    ];

    protected $casts = [
        'period_year'     => 'integer',
        'period_month'    => 'integer',
        'float_amount'    => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'is_closed'       => 'boolean',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function transactions()
    {
        return $this->hasMany(PettyCashTransaction::class, 'period_id', 'id')
            ->whereNull('deleted_at')
            ->orderBy('trx_date')
            ->orderBy('id');
    }

    /**
     * Pastikan periode untuk (year, month) ada. Kalau belum: bikin baru,
     * dengan opening_balance = closing bulan sebelumnya (atau float_amount
     * kalau ini periode pertama). Idempotent.
     */
    public static function ensurePeriod(int $year, int $month, float $defaultFloat = 1500000): self
    {
        $existing = static::where('period_year', $year)
            ->where('period_month', $month)
            ->first();
        if ($existing) return $existing;

        // Cari periode terakhir (paling baru) sebelum (year, month)
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

        // Default: opening 0 (periode pertama / belum ada dana sama sekali).
        // Kalau ada periode sebelumnya → carry-forward saldo akhir bulan lalu.
        $opening = 0.0;
        $float   = $defaultFloat;
        if ($prev) {
            $float = (float) $prev->float_amount;
            if ($prev->closing_balance !== null) {
                $opening = (float) $prev->closing_balance;
            } else {
                $opening = (float) $prev->opening_balance + (float) PettyCashTransaction::netForPeriod($prev->id);
            }
        }

        return static::create([
            'period_year'     => $year,
            'period_month'    => $month,
            'float_amount'    => $float,
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
