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
        <div class="pc-summary-card__top">
            <div class="pc-summary-card__label">Total cash in</div>
            <span class="pc-summary-card__icon pc-summary-card__icon--in" aria-hidden="true">
                <i class="fa fa-arrow-up"></i>
            </span>
        </div>
        <div class="pc-summary-card__value pc-summary-card__value--in">{{ $rupiah($summary['total_in']) }}</div>
        <div class="pc-summary-card__sub">Total uang masuk bulan ini</div>
    </div>

    <div class="pc-summary-card">
        <div class="pc-summary-card__top">
            <div class="pc-summary-card__label">Total cash out</div>
            <span class="pc-summary-card__icon pc-summary-card__icon--out" aria-hidden="true">
                <i class="fa fa-arrow-down"></i>
            </span>
        </div>
        <div class="pc-summary-card__value pc-summary-card__value--out">{{ $rupiah($summary['total_out']) }}</div>
        <div class="pc-summary-card__sub">Total uang keluar bulan ini</div>
    </div>

    <div class="pc-summary-card pc-summary-card--highlight">
        <div class="pc-summary-card__top">
            <div class="pc-summary-card__label">Saldo akhir</div>
            <span class="pc-summary-card__icon pc-summary-card__icon--saldo" aria-hidden="true">
                <i class="fa fa-calculator"></i>
            </span>
        </div>
        <div class="pc-summary-card__value">{{ $rupiah($summary['closing']) }}</div>
        <div class="pc-summary-card__sub">Cash in &minus; cash out + saldo awal</div>
    </div>
</div>

@php
    // First-time / fresh setup: kalau semua sumber masih 0 (tidak ada saldo,
    // tidak ada aktivitas in/out), tampilkan empty state. Begitu ada saldo,
    // breakdown akan otomatis muncul.
    $hasAnyData = collect($sourceBalances)->contains(function ($sb) {
        return abs((float) ($sb->balance ?? 0)) > 0.005
            || abs((float) ($sb->in ?? 0))      > 0.005
            || abs((float) ($sb->out ?? 0))     > 0.005;
    });
    $activeBranch = $activeBranch ?? null;
    $rupiah       = $rupiah ?? (fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'));
    $showSetupEmpty = ! $hasAnyData && ! $isReadOnly;
@endphp

@if($showSetupEmpty)
    <div class="pc-source-empty">
        {{-- Skeleton placeholder cards — visual hint bahwa di sini nanti wallet
             user akan tampil setelah setoran awal. --}}
        <div class="pc-source-empty__skeletons" aria-hidden="true">
            <div class="pc-source-empty__skel">
                <span class="pc-source-empty__skel-badge"></span>
                <span class="pc-source-empty__skel-bar"></span>
            </div>
            <div class="pc-source-empty__skel">
                <span class="pc-source-empty__skel-badge"></span>
                <span class="pc-source-empty__skel-bar"></span>
            </div>
            <div class="pc-source-empty__skel">
                <span class="pc-source-empty__skel-badge"></span>
                <span class="pc-source-empty__skel-bar"></span>
            </div>
        </div>
        <div class="pc-source-empty__icon"><i class="fa fa-wallet"></i></div>
        <div class="pc-source-empty__title">Belum ada saldo di wallet manapun</div>
        <div class="pc-source-empty__desc">
            Mulai dengan mencatat saldo awal tiap wallet &mdash; uang tunai di tangan, saldo bank, atau e-wallet. Setoran awal tidak masuk ke tabel transaksi.
        </div>
        <button type="button" class="pc-btn-topup pc-btn-topup--solid"
                data-pc-modal="pcModalOpening"
                data-pc-tooltip="Daftarkan saldo awal kas, bank, e-wallet. Tidak masuk ke tabel transaksi — hanya menentukan posisi saldo pembuka.">
            <i class="fa fa-plus"></i> Mulai Setoran Awal
        </button>
    </div>
@elseif($hasAnyData)
    @php
        // Tombol "Ajukan Top Up" muncul kalau ada cabang aktif (sesi Sales,
        // atau admin yang sedang buka ?branch=X). Admin biasanya tidak butuh
        // ngajuin (bisa top-up langsung), tapi dibuka untuk testing flow.
        $canRequestTopup    = !$isReadOnly && !empty($branchId);
        $pendingTopupCount  = $pendingTopupCount ?? 0;
    @endphp
    <div class="pc-source-breakdown">
        <div class="pc-source-breakdown__head">
            <div class="pc-source-breakdown__title">
                <i class="fa fa-wallet"></i> Rincian Saldo per Wallet
                @if($pendingTopupCount > 0)
                    {{-- Badge klik → modal rincian pengajuan top up yang masih
                         menunggu persetujuan Finance. --}}
                    <button type="button" class="pc-pending-badge"
                            data-pc-modal="pcModalPendingTopups"
                            title="Klik untuk lihat rincian {{ $pendingTopupCount }} pengajuan top up yang menunggu persetujuan Finance">
                        <i class="fa fa-clock"></i>
                        {{ $pendingTopupCount }} pengajuan menunggu Finance
                        <i class="fa fa-chevron-right pc-pending-badge__chevron"></i>
                    </button>
                @endif
            </div>
            @if($canRequestTopup)
                <div class="pc-source-breakdown__actions">
                    <button type="button" class="pc-btn-request-topup"
                            data-pc-modal="pcModalRequestTopup"
                            title="Kirim permintaan top up ke Finance">
                        <i class="fa fa-paper-plane"></i> Ajukan Top Up
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
                    // Tooltip kontekstual per kategori wallet — bantu user
                    // discover hidden action seperti "tarik tunai" yg cuma
                    // muncul kalau klik kartu Bank/E-Wallet.
                    $cardTooltip = match($sb->category ?? null) {
                        \App\Models\FundSource::CAT_CASH    => 'Klik untuk setor cash, transfer ke wallet lain, atau catat pengeluaran',
                        \App\Models\FundSource::CAT_BANK    => 'Klik untuk tarik tunai ke Cash, transfer antar wallet, atau catat pengeluaran',
                        \App\Models\FundSource::CAT_EWALLET => 'Klik untuk transfer ke wallet lain atau catat pengeluaran',
                        default                              => ($hasSaldo ? 'Klik untuk kelola saldo' : 'Klik untuk top up saldo'),
                    };
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
                         title="{{ $cardTooltip }}"
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
                        <div class="pc-source-card__headtext">
                            <span class="pc-source-card__name">{{ $sb->name }}</span>
                            @if(!empty($sb->account_number))
                                @php
                                    $acctLabel = $sb->category === \App\Models\FundSource::CAT_BANK
                                        ? 'No. Rek'
                                        : ($sb->category === \App\Models\FundSource::CAT_EWALLET ? 'No. HP' : 'No.');
                                @endphp
                                <div class="pc-source-card__account" onclick="event.stopPropagation()">
                                    <span class="pc-source-card__account-label">{{ $acctLabel }}</span>
                                    <span class="pc-source-card__account-value pc-copy-value"
                                          data-pc-copy="{{ $sb->account_number }}"
                                          role="button" tabindex="0"
                                          title="Klik untuk salin nomor"
                                      /     aria-label="Salin nomor {{ $sb->account_number }}">{{ $sb->account_number }}</span>
                                </div>
                            @endif
                        </div>
                        @if($isClickable)
                            @php
                                $iconClass = match($sb->category ?? null) {
                                    \App\Models\FundSource::CAT_BANK    => 'fa-money-bill-wave',
                                    \App\Models\FundSource::CAT_CASH    => 'fa-exchange-alt',
                                    \App\Models\FundSource::CAT_EWALLET => 'fa-exchange-alt',
                                    default => ($hasSaldo ? 'fa-exchange-alt' : 'fa-plus'),
                                };
                                $iconHint = match($sb->category ?? null) {
                                    \App\Models\FundSource::CAT_BANK    => 'Tarik tunai / Transfer / Pengeluaran',
                                    \App\Models\FundSource::CAT_CASH    => 'Setor cash / Transfer / Pengeluaran',
                                    \App\Models\FundSource::CAT_EWALLET => 'Transfer / Pengeluaran',
                                    default => ($hasSaldo ? 'Transfer / Top up / Pengeluaran' : 'Top up saldo'),
                                };
                            @endphp
                            <i class="fa {{ $iconClass }} pc-source-card__transfer-hint"
                               title="{{ $iconHint }}"></i>
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
                    @php $piutang = round((float) ($sb->piutang ?? 0), 2); @endphp
                    {{-- Hanya tampil saat kategori Pinjaman ikut difilter — biar
                         kartu wallet tetap ringkas di tampilan normal. --}}
                    @if($piutang > 0 && in_array('pinjaman', $summary['filter_categories'] ?? [], true))
                        {{-- Ada uang wallet ini yang dipinjam & belum kembali.
                             Saldo di atas = uang fisik; saldo sesungguhnya =
                             fisik + piutang (uang yang masih milik petty cash). --}}
                        <div class="pc-source-card__piutang" onclick="event.stopPropagation()">
                            <div class="pc-source-card__piutang-row" title="Piutang pinjaman — uang wallet ini yang sedang dipinjam & belum kembali">
                                <span class="pc-source-card__piutang-label" aria-label="Piutang pinjaman">
                                    <i class="fa fa-hand-holding-usd"></i>
                                </span>
                                <span class="pc-source-card__piutang-val">{{ $rupiah($piutang) }}</span>
                            </div>
                            <div class="pc-source-card__piutang-row pc-source-card__piutang-row--real" title="Saldo sesungguhnya — saldo fisik + piutang">
                                <span class="pc-source-card__piutang-label" aria-label="Saldo sesungguhnya">
                                    <i class="fa fa-wallet"></i>
                                </span>
                                <span class="pc-source-card__piutang-val">{{ $rupiah($balanceRounded + $piutang) }}</span>
                            </div>
                            @if(! $isReadOnly)
                                <button type="button" class="pc-source-card__piutang-btn"
                                        data-pc-modal="pcModalLoanReturn"
                                        data-loan-source="{{ $sb->id }}">
                                    <i class="fa fa-hand-holding-usd"></i> Catat Pengembalian
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @if(!$isReadOnly)
            <div class="pc-source-breakdown__hint">
                <span class="pc-source-breakdown__hint-icon" aria-hidden="true">
                    <i class="fa fa-info"></i>
                </span>
                <span>Klik kartu wallet untuk <strong>Transfer</strong>, <strong>Tarik / Setor Tunai</strong>, atau catat <strong>Pengeluaran</strong>.</span>
            </div>
        @endif
    </div>
@endif

<style>
/* Empty state — ajakan setoran awal saat belum ada saldo di sumber manapun. */
.pc-source-empty {
    margin: 16px 0 8px;
    padding: 28px 24px 32px;
    background: linear-gradient(180deg, #fff8f3 0%, #ffffff 100%);
    border: 2px dashed var(--brown-2);
    border-radius: 14px;
    text-align: center;
}
/* Varian serah terima rolling — palette ungu + badge status rolling. */
.pc-source-empty--rolling {
    background: linear-gradient(180deg, #f5f3ff 0%, #ffffff 100%);
    border-color: #c4b5fd;
}
.pc-source-empty__rolling-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 16px;
    padding: 5px 14px;
    background: #ede9fe;
    color: #5b21b6;
    border: 1px solid #ddd6fe;
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 700;
}
.pc-source-empty--rolling .pc-source-empty__icon {
    background: #7c3aed;
    box-shadow: 0 4px 12px rgba(124,58,237,0.25);
}
.pc-source-empty--rolling .pc-source-empty__title { color: #4c1d95; }
.pc-source-empty--rolling .pc-btn-topup--solid {
    background: #7c3aed !important;
}
.pc-source-empty--rolling .pc-btn-topup--solid:hover {
    background: #6d28d9 !important;
}
/* Skeleton placeholder cards — visual hint untuk wallet yg akan tampil. */
.pc-source-empty__skeletons {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    max-width: 720px;
    margin: 0 auto 24px;
}
.pc-source-empty__skel {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 14px;
    background: #fff;
    border: 1px dashed var(--divider-soft);
    border-radius: 10px;
}
.pc-source-empty__skel-badge {
    flex: 0 0 auto;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: var(--divider-soft);
}
.pc-source-empty__skel-bar {
    flex: 1;
    height: 8px;
    border-radius: 4px;
    background: var(--divider-soft);
}
.pc-source-empty__icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 14px;
    border-radius: 12px;
    background: var(--brown);
    color: #fff;
    font-size: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(70,21,0,0.18);
}
@media (max-width: 700px) {
    .pc-source-empty__skeletons { grid-template-columns: 1fr; }
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

/* ─── CTA Ajukan Top Up (Sales) ─── */
.pc-source-breakdown__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.pc-pending-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-left: 8px;
    padding: 3px 9px;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
    border-radius: 999px;
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s, border-color .15s, transform .15s;
}
.pc-pending-badge:hover {
    background: #fde68a;
    border-color: #f59e0b;
    transform: translateY(-1px);
}
.pc-pending-badge i { font-size: 10px; }
.pc-pending-badge__chevron { font-size: 8px; opacity: .7; }
.pc-btn-request-topup {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    background: var(--brown);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s, transform .15s;
}
.pc-btn-request-topup:hover {
    background: var(--brown-2);
    transform: translateY(-1px);
}
.pc-btn-request-topup i { font-size: 11px; }

/* ─── Piutang pinjaman per wallet ─── */
.pc-source-card__piutang {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed var(--divider-soft, #e5e7eb);
    display: flex;
    flex-direction: column;
    gap: 3px;
    cursor: default;
}
.pc-source-card__piutang-row {
    display: flex;
    align-items: center;
    justify-content: flex-start;         /* ikon & nominal berdempetan di kiri */
    gap: 8px;
    font-size: 12px;
    line-height: 1.35;
}
.pc-source-card__piutang-label {
    color: #b45309;                      /* amber — uang di tangan peminjam */
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 18px;                      /* lebar tetap → nominal antar baris sejajar */
}
.pc-source-card__piutang-label i {
    font-size: 13px;
    flex: 0 0 auto;                      /* ikon tak ikut menyusut */
}
.pc-source-card__piutang-val {
    color: #b45309;
    font-weight: 700;
    flex: 0 0 auto;
    white-space: nowrap;                 /* 'Rp 40.000' tetap satu baris */
    text-align: right;
}
.pc-source-card__piutang-row--real .pc-source-card__piutang-label {
    color: #047857;                      /* hijau — nilai sesungguhnya */
    font-weight: 700;
}
.pc-source-card__piutang-row--real .pc-source-card__piutang-val { color: #047857; }
.pc-source-card__piutang-btn {
    margin-top: 6px;
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 10px;
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s, border-color .15s;
}
.pc-source-card__piutang-btn:hover { background: #d1fae5; border-color: #6ee7b7; }
.pc-source-card__piutang-btn i { font-size: 11px; }
</style>
