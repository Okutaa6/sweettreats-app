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

@php
    // First-time / fresh setup: kalau semua sumber masih 0 (tidak ada saldo,
    // tidak ada aktivitas in/out), tampilkan empty state ajakan setoran awal /
    // migrasi. Begitu user mulai setor saldo, breakdown akan otomatis muncul.
    $hasAnyData = collect($sourceBalances)->contains(function ($sb) {
        return abs((float) ($sb->balance ?? 0)) > 0.005
            || abs((float) ($sb->in ?? 0))      > 0.005
            || abs((float) ($sb->out ?? 0))     > 0.005;
    });
@endphp

@if(! $hasAnyData && ! $isReadOnly)
    <div class="pc-source-empty">
        <div class="pc-source-empty__icon"><i class="fa fa-piggy-bank"></i></div>
        <div class="pc-source-empty__title">Belum ada saldo di sumber dana manapun</div>
        <div class="pc-source-empty__desc">
            Pilih sumber dana dulu &mdash; rinci budget per sumber yang sudah berjalan operasional
            (cash di tangan, saldo bank, e-wallet). Tidak akan masuk ke tabel transaksi.
        </div>
        <button type="button" class="pc-btn-topup pc-btn-topup--solid" data-pc-modal="pcModalOpening">
            <i class="fa fa-piggy-bank"></i> Mulai Setoran Awal
        </button>
    </div>
@elseif($hasAnyData)
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
                         data-source-account="{{ $sb->account_number ?? '' }}"
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
                    @if(!empty($sb->account_number))
                        @php
                            $acctLabel = $sb->category === \App\Models\FundSource::CAT_BANK
                                ? 'No. Rek'
                                : ($sb->category === \App\Models\FundSource::CAT_EWALLET ? 'No. HP' : 'No.');
                        @endphp
                        <div class="pc-source-card__account" onclick="event.stopPropagation()">
                            <span class="pc-source-card__account-label">{{ $acctLabel }}</span>
                            <span class="pc-source-card__account-value">{{ $sb->account_number }}</span>
                            <button type="button" class="pc-copy-btn"
                                    data-pc-copy="{{ $sb->account_number }}"
                                    onkeydown="event.stopPropagation()"
                                    title="Salin nomor"
                                    aria-label="Salin nomor">
                                <i class="fa fa-copy"></i>
                            </button>
                        </div>
                    @endif
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

<style>
/* Empty state — ajakan setoran awal saat belum ada saldo di sumber manapun. */
.pc-source-empty {
    margin: 16px 0 8px;
    padding: 32px 24px;
    background: linear-gradient(180deg, #fff8f3 0%, #ffffff 100%);
    border: 2px dashed var(--brown-2);
    border-radius: 12px;
    text-align: center;
}
.pc-source-empty__icon {
    width: 64px;
    height: 64px;
    line-height: 64px;
    margin: 0 auto 12px;
    border-radius: 50%;
    background: var(--brown);
    color: #fff;
    font-size: 26px;
    box-shadow: 0 4px 12px rgba(70,21,0,0.20);
}
.pc-source-empty__title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--brown);
    margin-bottom: 6px;
}
.pc-source-empty__desc {
    color: #4b5563;
    font-size: 14px;
    max-width: 560px;
    margin: 0 auto 18px;
    line-height: 1.55;
}
.pc-btn-topup--solid {
    background: var(--brown) !important;
    color: #fff !important;
    border: none !important;
    padding: 10px 22px !important;
    font-weight: 600;
}
.pc-btn-topup--solid:hover {
    background: var(--brown-2) !important;
}
</style>
