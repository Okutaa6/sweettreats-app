{{--
    Petty Cash — summary cards.
    Variables: $summary (array: opening/total_in/total_out/closing/suggested_topup),
               $rupiah (closure).
--}}
<div class="pc-summary-grid">
    <div class="pc-summary-card">
        <div class="pc-summary-card__label">Total cash in</div>
        <div class="pc-summary-card__value pc-summary-card__value--in">{{ $rupiah($summary['total_in']) }}</div>
    </div>

    <div class="pc-summary-card">
        <div class="pc-summary-card__label">Total cash out</div>
        <div class="pc-summary-card__value pc-summary-card__value--out">{{ $rupiah($summary['total_out']) }}</div>
    </div>

    <div class="pc-summary-card pc-summary-card--highlight">
        <div class="pc-summary-card__label">Saldo akhir</div>
        <div class="pc-summary-card__value">{{ $rupiah($summary['closing']) }}</div>
    </div>
</div>
