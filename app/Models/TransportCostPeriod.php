<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TransportCostPeriod
 *
 * Periode bulanan biaya transportasi (imprest / dana tetap).
 * Struktur kembaran PettyCashPeriod — pisah tabel supaya laporan tidak campur.
 */
class TransportCostPeriod extends Model
{
    protected $table = 'transport_cost_periods';

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
        return $this->hasMany(TransportCostTransaction::class, 'period_id', 'id')
            ->whereNull('deleted_at')
            ->orderBy('trx_date')
            ->orderBy('id');
    }

    /**
     * Pastikan periode (year, month) ada. Carry-forward saldo dari periode
     * sebelumnya kalau ada. Default opening 0.
     */
    public static function ensurePeriod(int $year, int $month, float $defaultFloat = 1000000): self
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
        $float   = $defaultFloat;
        if ($prev) {
            $float = (float) $prev->float_amount;
            if ($prev->closing_balance !== null) {
                $opening = (float) $prev->closing_balance;
            } else {
                $opening = (float) $prev->opening_balance + (float) TransportCostTransaction::netForPeriod($prev->id);
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
