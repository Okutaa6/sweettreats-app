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
        'raw_branch_id',
        'float_amount',
        'opening_balance',
        'source_opening_balances',
        'opening_set_by',
        'handover_at',
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
        'handover_at'             => 'datetime',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    public function transactions()
    {
        return $this->hasMany(PettyCashTransaction::class, 'period_id', 'id')
            ->whereNull('deleted_at')
            ->orderBy('trx_date')
            ->orderBy('id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    /**
     * Pastikan periode untuk (year, month, branch) ada. Kalau belum:
     * bikin baru, dengan opening_balance = closing bulan sebelumnya
     * cabang yang sama (atau 0 kalau ini periode pertama cabang).
     * Idempotent.
     */
    public static function ensurePeriod(int $year, int $month, float $defaultFloat = 1500000, ?string $branchId = null): self
    {
        $existing = static::where('period_year', $year)
            ->where('period_month', $month)
            ->where('raw_branch_id', $branchId)
            ->first();
        if ($existing) return $existing;

        // Cari periode terakhir (paling baru) sebelum (year, month) di cabang yg sama
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

        // Default: opening 0 (periode pertama / belum ada dana sama sekali).
        // Kalau ada periode sebelumnya → carry-forward saldo akhir bulan lalu,
        // termasuk DISTRIBUSI per sumber dana (BCA, BSI, GoPay, dll).
        $opening       = 0.0;
        $float         = $defaultFloat;
        $sourceOpening = null;
        if ($prev) {
            $float = (float) $prev->float_amount;
            if ($prev->closing_balance !== null) {
                $opening = (float) $prev->closing_balance;
            } else {
                // Carry-forward = total fisik kas cabang = opening + net semua
                // transaksi periode sebelumnya.
                $opening = (float) $prev->opening_balance + (float) PettyCashTransaction::netForPeriod($prev->id);
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
     * net (in - out) per source dari transaksi non-deleted.
     *
     * Return array `[<source_id> => <amount>]`. Keys termasuk semua source
     * yang punya saldo > 0 dan/atau aktivitas di periode ini.
     *
     * Dipakai untuk meng-snapshot ke periode berikutnya supaya distribusi
     * per source tidak hilang saat ganti bulan.
     */
    public function computeClosingPerSource(): array
    {
        $balances = $this->openingPerSourceResolved();

        // Total per source = opening + net semua transaksi periode ini →
        // di-carry-forward ke bulan berikutnya.
        $rows = PettyCashTransaction::active()
            ->forPeriod($this->id)
            ->get(['fund_source_id', 'direction', 'amount']);

        foreach ($rows as $r) {
            $sid = $r->fund_source_id;
            if (! $sid) continue;
            $amt = (float) $r->amount;
            $delta = $r->direction === PettyCashTransaction::DIR_IN ? $amt : -$amt;
            $balances[$sid] = ($balances[$sid] ?? 0.0) + $delta;
        }

        // Drop entri 0.0 supaya JSON ringkas
        return array_filter($balances, fn ($v) => abs($v) > 0.005);
    }

    /**
     * Resolve opening per source untuk periode ini:
     *   - Kalau source_opening_balances sudah ter-isi → pakai itu.
     *   - Kalau NULL (periode lama / backward compat) → fallback: dump
     *     scalar opening_balance ke source "Cash" (perilaku legacy),
     *     tapi self-heal kalau ada periode sebelumnya — re-compute dari
     *     prev period & save.
     */
    public function openingPerSourceResolved(): array
    {
        if (is_array($this->source_opening_balances)) {
            return $this->source_opening_balances;
        }

        // Self-heal: kalau ada prev di cabang yang sama, snapshot ulang.
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
            // Sync scalar opening_balance ke sum snapshot supaya kedua
            // representasi (scalar & per-source) tidak drift. Untuk
            // periode yang dibuat SEBELUM fix ini, scalar mungkin stale
            // (mis: prev edited setelah period ini dibuat) — overwrite
            // dengan nilai akurat dari snapshot.
            $this->opening_balance = array_sum($snap);
            $this->save();
            return $snap;
        }

        // Periode pertama branch — opening 0 di semua source. Sync scalar
        // juga untuk konsistensi.
        if ((float) $this->opening_balance != 0.0) {
            $this->opening_balance = 0.0;
            $this->source_opening_balances = [];
            $this->save();
        }
        return [];
    }
}
