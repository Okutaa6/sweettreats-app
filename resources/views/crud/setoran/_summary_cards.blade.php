{{--
    Setoran — summary cards.
    Variables: $summary (opening/total_in/total_out/closing), $rupiah (closure).
--}}
<div class="sd-summary-grid">
    <div class="sd-summary-card">
        <div class="sd-summary-card__label">Total cash in</div>
        <div class="sd-summary-card__value sd-summary-card__value--in">{{ $rupiah($summary['total_in']) }}</div>
    </div>

    <div class="sd-summary-card">
        <div class="sd-summary-card__label">Total cash out (setor)</div>
        <div class="sd-summary-card__value sd-summary-card__value--out">{{ $rupiah($summary['total_out']) }}</div>
    </div>

    <div class="sd-summary-card sd-summary-card--highlight">
        <div class="sd-summary-card__label">Saldo belum disetor</div>
        <div class="sd-summary-card__value">{{ $rupiah($summary['closing']) }}</div>
    </div>
</div>
