{{--
    Petty Cash — summary cards.
    Variables:
      $summary (array: opening/total_in/total_out/closing/suggested_topup),
      $rupiah  (closure),
      $sourceBalances (array of objects: id, category, name, brand_color,
                       short_label, logo_url, in, out, balance).
--}}
@php
    $sourceBalances = $sourceBalances ?? [];
    $isReadOnly     = $isReadOnly ?? false;
@endphp
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

@if(count($sourceBalances) > 0)
    <div class="pc-source-breakdown">
        <div class="pc-source-breakdown__head">
            <div class="pc-source-breakdown__title">
                <i class="fa fa-wallet"></i> Rincian Saldo per Sumber Dana
            </div>
            @if(!$isReadOnly)
                <div class="pc-source-breakdown__actions">
                    <button type="button" class="pc-btn-topup" data-pc-modal="pcModalTopup">
                        <i class="fa fa-arrow-down-up-across-line"></i> Top up saldo
                    </button>
                </div>
            @endif
        </div>
        <div class="pc-source-breakdown__grid">
            @foreach($sourceBalances as $sb)
                @php
                    // Bulatkan ke 2 desimal untuk handle floating-point edge case.
                    $balanceRounded = round((float) $sb->balance, 2);
                    // Card clickable selama ada source id (bukan uncategorized)
                    // dan periode masih editable. Card dengan saldo 0 tetap
                    // bisa dibuka modalnya — modal akan default ke tab Top up,
                    // tab Transfer & Pengeluaran di-disable dari JS.
                    $isClickable = !$isReadOnly && $sb->id;
                    $hasSaldo    = $balanceRounded > 0;
                @endphp
                <div class="pc-source-card pc-source-card--{{ $sb->category ?? 'unknown' }} {{ $isClickable ? 'is-clickable' : '' }}"
                     style="border-left-color: {{ $sb->brand_color ?? '#cbd5e1' }};"
                     @if($isClickable)
                         data-pc-source-card
                         data-source-id="{{ $sb->id }}"
                         data-source-name="{{ $sb->name }}"
                         data-source-balance="{{ $sb->balance }}"
                         data-source-color="{{ $sb->brand_color }}"
                         data-source-short="{{ $sb->short_label }}"
                         data-source-logo="{{ $sb->logo_url }}"
                         data-source-category="{{ $sb->category }}"
                         title="{{ $hasSaldo ? 'Klik untuk kelola saldo' : 'Klik untuk top up saldo' }}"
                         role="button"
                         tabindex="0"
                     @endif>
                    <div class="pc-source-card__head">
                        @include('crud.pettycash._fund_source_badge', [
                            'color' => $sb->brand_color ?? null,
                            'short' => $sb->short_label ?? null,
                            'logo'  => $sb->logo_url ?? null,
                            'name'  => $sb->name,
                            'size'  => 'md',
                        ])
                        <span class="pc-source-card__name">{{ $sb->name }}</span>
                        @if($isClickable)
                            <i class="fa {{ $hasSaldo ? 'fa-right-left' : 'fa-plus' }} pc-source-card__transfer-hint"
                               title="{{ $hasSaldo ? 'Transfer / Top up / Pengeluaran' : 'Top up saldo' }}"></i>
                        @endif
                    </div>
                    <div class="pc-source-card__balance">{{ $rupiah($sb->balance) }}</div>
                    <div class="pc-source-card__meta">
                        <span class="pc-source-card__in" title="Cash in">
                            <i class="fa fa-arrow-up"></i> {{ $rupiah($sb->in) }}
                        </span>
                        <span class="pc-source-card__out" title="Cash out">
                            <i class="fa fa-arrow-down"></i> {{ $rupiah($sb->out) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
