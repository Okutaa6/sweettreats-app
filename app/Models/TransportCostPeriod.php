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
        'raw_branch_id',
        'float_amount',
        'opening_balance',
        'source_opening_balances',
        'closing_balance',
        'is_closed',
    ];

    protected $casts = [
        'period_year'             => 'integer',
        'period_month'            => 'integer',
        'float_amount'            => 'decimal:2',
        'opening_balance'         => 'decimal:2',
        'source_opening_balances' => 'array',
        'closing_balance'         => 'decimal:2',
        'is_closed'               => 'boolean',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    public function transactions()
    {
        return $this->hasMany(TransportCostTransaction::class, 'period_id', 'id')
            ->whereNull('deleted_at')
            ->orderBy('trx_date')
            ->orderBy('id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    /**
     * Pastikan periode (year, month, branch) ada. Carry-forward saldo
     * dari periode sebelumnya cabang yang sama kalau ada.
     */
    public static function ensurePeriod(int $year, int $month, float $defaultFloat = 1000000, ?string $branchId = null): self
    {
        $existing = static::where('period_year', $year)
            ->where('period_month', $month)
            ->where('raw_branch_id', $branchId)
            ->first();
        if ($existing) return $existing;

        $prev = static::where('raw_branch_id', $branchId)
            ->where(function ($q) use ($year, $month) {
                $q->where('period_year', '<', $year)
                  ->orWhere(function ($qq) use ($year, $month) {
                      $qq->where('period_year', $year)
                         ->where('period_month', '<', $month);
                  });
            })
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        $opening       = 0.0;
        $float         = $defaultFloat;
        $sourceOpening = null;
        if ($prev) {
            $float = (float) $prev->float_amount;
            if ($prev->closing_balance !== null) {
                $opening = (float) $prev->closing_balance;
            } else {
                $opening = (float) $prev->opening_balance + (float) TransportCostTransaction::netForPeriod($prev->id);
            }
            $sourceOpening = $prev->computeClosingPerSource();
        }

        return static::create([
            'period_year'             => $year,
            'period_month'            => $month,
            'raw_branch_id'           => $branchId,
            'float_amount'            => $float,
            'opening_balance'         => $opening,
            'source_opening_balances' => $sourceOpening,
            'closing_balance'         => null,
            'is_closed'               => 0,
        ]);
    }

    public function isCurrentMonth(): bool
    {
        return $this->period_year === (int) date('Y')
            && $this->period_month === (int) date('n');
    }

    /**
     * Saldo per source di AKHIR periode ini = opening per source +
     * net (in - out) per source. Lihat PettyCashPeriod::computeClosingPerSource
     * untuk detail — implementasi identik, beda hanya tabel transaksi.
     */
    public function computeClosingPerSource(): array
    {
        $balances = $this->openingPerSourceResolved();

        $rows = TransportCostTransaction::active()
            ->forPeriod($this->id)
            ->get(['fund_source_id', 'direction', 'amount']);

        foreach ($rows as $r) {
            $sid = $r->fund_source_id;
            if (! $sid) continue;
            $amt = (float) $r->amount;
            $delta = $r->direction === TransportCostTransaction::DIR_IN ? $amt : -$amt;
            $balances[$sid] = ($balances[$sid] ?? 0.0) + $delta;
        }

        return array_filter($balances, fn ($v) => abs($v) > 0.005);
    }

    public function openingPerSourceResolved(): array
    {
        if (is_array($this->source_opening_balances)) {
            return $this->source_opening_balances;
        }

        $prev = static::where('raw_branch_id', $this->raw_branch_id)
            ->where(function ($q) {
                $q->where('period_year', '<', $this->period_year)
                  ->orWhere(function ($qq) {
                      $qq->where('period_year', $this->period_year)
                         ->where('period_month', '<', $this->period_month);
                  });
            })
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        if ($prev) {
            $snap = $prev->computeClosingPerSource();
            $this->source_opening_balances = $snap;
            // Sync scalar opening_balance dengan sum snapshot — lihat
            // PettyCashPeriod::openingPerSourceResolved untuk rationale.
            $this->opening_balance = array_sum($snap);
            $this->save();
            return $snap;
        }

        if ((float) $this->opening_balance != 0.0) {
            $this->opening_balance = 0.0;
            $this->source_opening_balances = [];
            $this->save();
        }
        return [];
    }
}
