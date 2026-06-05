@extends('layouts.app')

@section('content')
@php
    $monthName = $bulanLabel[$month] ?? '-';
    $rupiah = function ($v) { return 'Rp ' . number_format((float) $v, 0, ',', '.'); };
    $kind     = $kind ?? 'petty_cash';
    $tabs     = $tabs ?? [];
    $tabLabel = collect($tabs)->firstWhere('kind', $kind)['label'] ?? 'Petty cash';
@endphp
<div class="container-fluid">

    {{-- ─── HEADER ─── --}}
    <div class="pc-page-header">
        <div>
            <h4 class="page-title mb-1">{{ $tabLabel }}</h4>
            <div class="pc-page-sub">
                Periode {{ $isCurrentMonth ? 'berjalan' : 'lampau' }}
                · <strong>{{ $monthName }} {{ $year }}</strong>
            </div>
        </div>
        <div class="pc-page-header__actions">
            @if(isSuperAdmin())
                <a href="{{ route('fundsource') }}" class="st-btn-outline-brown" title="Kelola sumber dana & logo brand">
                    <i class="fa fa-cog"></i> Kelola Sumber Dana
                </a>
            @endif
            @if($period->is_closed)
                <span class="pc-badge pc-badge--closed">
                    <i class="fa fa-lock"></i> Periode ditutup · read-only
                </span>
            @elseif($isCurrentMonth)
                <span class="pc-badge pc-badge--active">
                    <i class="fa fa-calendar-day"></i> Bulan berjalan · dapat diubah
                </span>
            @else
                <span class="pc-badge pc-badge--past">
                    <i class="fa fa-clock-rotate-left"></i> Bulan lampau · read-only
                </span>
            @endif
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── TAB NAVIGATION ─── --}}
    @if(count($tabs) > 1)
        <div class="pc-tabs" role="tablist">
            @foreach($tabs as $t)
                <a href="{{ route('pettycash', ['tab' => $t['kind']]) }}"
                   class="pc-tab {{ $t['active'] ? 'is-active' : '' }}"
                   role="tab" aria-selected="{{ $t['active'] ? 'true' : 'false' }}">
                    {{ $t['label'] }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- ─── SUMMARY CARDS ─── --}}
    @include('crud.pettycash._summary_cards', [
        'summary'        => $summary,
        'rupiah'         => $rupiah,
        'sourceBalances' => $sourceBalances ?? [],
        'isReadOnly'     => $isReadOnly,
        'kind'           => $kind,
    ])

    {{-- ─── FILTER ─── --}}
    @include('crud.pettycash._filter', [
        'startDate'     => $startDate,
        'endDate'       => $endDate,
        'monthStartStr' => $monthStartStr,
        'monthEndStr'   => $monthEndStr,
        'search'        => $search,
        'isReadOnly'    => $isReadOnly,
        'kind'          => $kind,
    ])

    {{-- ─── TABLE ─── --}}
    <div class="st-card mt-3 pc-table-card">
        <div class="st-card__body" style="padding:0;">
            <div class="st-table-wrap--scroll">
                <table class="st-table pc-table">
                    <thead>
                        <tr>
                            <th width="110">Tanggal</th>
                            <th width="140">Sumber</th>
                            <th>Keterangan</th>
                            <th class="text-right" width="130">Cash in</th>
                            <th class="text-right" width="130">Cash out</th>
                            <th class="text-right" width="150">Saldo</th>
                            @if(!$isReadOnly)
                                <th width="60"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr class="{{ $r->is_topup ? 'pc-row-topup' : '' }} {{ $r->is_transfer ? 'pc-row-transfer' : '' }}">
                                <td class="pc-date">{{ \Carbon\Carbon::parse($r->trx_date)->translatedFormat('d M') }}</td>
                                <td>
                                    @if($r->source_name)
                                        <span class="pc-source-inline">
                                            @include('crud.pettycash._fund_source_badge', [
                                                'color' => $r->source_color,
                                                'short' => $r->source_short,
                                                'logo'  => $r->source_logo,
                                                'name'  => $r->source_name,
                                                'size'  => 'sm',
                                            ])
                                            <span class="pc-source-inline__name">{{ $r->source_name }}</span>
                                        </span>
                                    @else
                                        <span class="pc-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($r->is_topup)
                                        <i class="fa-solid fa-arrow-down-up-across-line" style="color:#16a34a;"></i>
                                    @elseif($r->is_transfer)
                                        <i class="fa-solid fa-right-left" style="color:#2563eb;"></i>
                                    @endif
                                    <strong>{{ $r->description }}</strong>
                                    @if($r->is_topup)
                                        <span class="pc-pill-topup">top up</span>
                                    @elseif($r->is_transfer)
                                        <span class="pc-pill-transfer">transfer</span>
                                    @endif
                                </td>
                                <td class="text-right pc-cash-in {{ $r->is_transfer ? 'pc-cash--internal' : '' }}">
                                    {{ $r->cash_in > 0 ? number_format($r->cash_in, 0, ',', '.') : '—' }}
                                </td>
                                <td class="text-right pc-cash-out {{ $r->is_transfer ? 'pc-cash--internal' : '' }}">
                                    {{ $r->cash_out > 0 ? number_format($r->cash_out, 0, ',', '.') : '—' }}
                                </td>
                                <td class="text-right pc-saldo-cell">{{ number_format($r->saldo, 0, ',', '.') }}</td>
                                @if(!$isReadOnly)
                                    <td>
                                        <form method="POST" action="{{ route('pettycash.destroy', ['id' => $r->id, 'tab' => $kind]) }}"
                                              onsubmit="return confirm('Hapus transaksi ini? Tidak bisa di-undo.')" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="pc-icon-btn" title="Hapus transaksi">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isReadOnly ? 6 : 7 }}">
                                    <div class="pc-empty-state">
                                        <i class="fa fa-folder-open"></i>
                                        <div class="pc-empty-state__title">Belum ada transaksi bulan ini</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($rows) > 0)
                        <tfoot>
                            <tr class="pc-row-total">
                                <td colspan="3"><strong>Total bulan ini</strong></td>
                                <td class="text-right pc-cash-in">{{ number_format($summary['total_in'], 0, ',', '.') }}</td>
                                <td class="text-right pc-cash-out">{{ number_format($summary['total_out'], 0, ',', '.') }}</td>
                                <td class="text-right pc-saldo-cell"><strong>{{ number_format($summary['closing'], 0, ',', '.') }}</strong></td>
                                @if(!$isReadOnly)<td></td>@endif
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

@if(!$isReadOnly)
    @include('crud.pettycash.modal-topup', [
        'period'             => $period,
        'summary'            => $summary,
        'kind'               => $kind,
        'fundSourcesGrouped' => $fundSourcesGrouped,
    ])
    @include('crud.pettycash.modal-transfer', [
        'period'             => $period,
        'kind'               => $kind,
        'fundSourcesGrouped' => $fundSourcesGrouped,
    ])
@endif
@endsection

@section('js')
<script>
(function () {
    // Format Rupiah otomatis di input amount (thousand separator).
    document.querySelectorAll('.pc-amount-input').forEach(function (el) {
        const hidden = document.getElementById(el.dataset.target);

        function format() {
            const raw = el.value.replace(/\D/g, '');
            if (!raw) { el.value = ''; if (hidden) hidden.value = ''; return; }
            el.value = parseInt(raw, 10).toLocaleString('id-ID');
            if (hidden) hidden.value = raw;
        }
        el.addEventListener('input', format);
        format();
    });

    // Modal trigger
    document.querySelectorAll('[data-pc-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.dataset.pcModal;
            const modal = document.getElementById(id);
            if (modal) modal.classList.add('is-open');
        });
    });
    document.querySelectorAll('[data-pc-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.pc-modal').classList.remove('is-open');
        });
    });
    document.querySelectorAll('.pc-modal').forEach(function (m) {
        m.addEventListener('click', function (e) {
            if (e.target === m) m.classList.remove('is-open');
        });
    });

    // Klik card sumber dana → buka modal "Kelola Saldo Sumber Dana"
    // dengan source asal ter-populate. Modal punya 2 tab: Transfer dan
    // Top up.
    const transferModal = document.getElementById('pcModalTransfer');

    function switchModalTab(target) {
        if (!transferModal) return;
        // Refuse to switch to a disabled tab
        const targetBtn = transferModal.querySelector('[data-tab-target="' + target + '"]');
        if (targetBtn && targetBtn.disabled) return;
        transferModal.querySelectorAll('[data-tab-target]').forEach(function (btn) {
            const active = btn.dataset.tabTarget === target;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        transferModal.querySelectorAll('[data-tab-content]').forEach(function (form) {
            form.hidden = form.dataset.tabContent !== target;
        });
    }

    function openSourceModalFromCard(card) {
        if (!transferModal) return;
        const id       = card.dataset.sourceId;
        const name     = card.dataset.sourceName;
        const balance  = parseFloat(card.dataset.sourceBalance || '0');
        const color    = card.dataset.sourceColor || '#64748b';
        const short    = card.dataset.sourceShort || '?';
        const logo     = card.dataset.sourceLogo || '';
        const category = card.dataset.sourceCategory || '';
        const isCash   = category === 'cash';
        if (!id) return;
        // Tolerance 0.005 untuk handle floating-point edge case
        const hasSaldo = balance >= 0.005;
        const fmt = function (n) { return Math.round(n).toLocaleString('id-ID'); };

        // Source preview (shared antar tab)
        document.getElementById('transferFromName').textContent = name;
        document.getElementById('transferFromBalance').textContent = 'Rp ' + fmt(balance);
        const badge = document.getElementById('transferFromBadge');
        badge.style.background = color;
        badge.innerHTML = logo
            ? '<img src="' + logo + '" alt="" class="fs-badge__img">'
            : '<span class="fs-badge__text">' + short + '</span>';

        // Form Transfer: set from_source, filter dropdown destination, reset amount
        document.getElementById('transferFromSourceId').value = id;
        document.getElementById('transferMaxHint').textContent = fmt(balance);
        const sel = document.getElementById('transferToSelect');
        sel.value = '';
        Array.from(sel.options).forEach(function (opt) {
            opt.hidden = (opt.value === id);
            opt.disabled = (opt.value === id);
        });
        sel.dispatchEvent(new Event('change'));
        const transferAmtVisible = document.querySelector('[data-target="transferAmount"]');
        const transferAmtHidden  = document.getElementById('transferAmount');
        if (transferAmtVisible) {
            transferAmtVisible.value = '';
            transferAmtVisible.dataset.maxAmount = String(balance);
        }
        if (transferAmtHidden) transferAmtHidden.value = '';

        // Form Top up: lock fund_source_id ke source asal, reset amount
        document.getElementById('topupSourceFundSourceId').value = id;
        const topupAmtVisible = document.querySelector('[data-target="topupSourceAmount"]');
        const topupAmtHidden  = document.getElementById('topupSourceAmount');
        if (topupAmtVisible) topupAmtVisible.value = '';
        if (topupAmtHidden)  topupAmtHidden.value  = '';

        // Form Pengeluaran: lock fund_source_id, set max = saldo source,
        // reset amount.
        document.getElementById('expenseSourceFundSourceId').value = id;
        document.getElementById('expenseSourceMaxHint').textContent = fmt(balance);
        const expAmtVisible = document.querySelector('[data-target="expenseSourceAmount"]');
        const expAmtHidden  = document.getElementById('expenseSourceAmount');
        if (expAmtVisible) {
            expAmtVisible.value = '';
            expAmtVisible.dataset.maxAmount = String(balance);
        }
        if (expAmtHidden) expAmtHidden.value = '';

        // Reset description di semua tab form
        transferModal.querySelectorAll('[data-tab-content] input[name="description"]').forEach(function (el) {
            el.value = '';
        });

        // Aturan tab:
        //   - Transfer / Pengeluaran  → butuh saldo > 0 (disabled kalau kosong)
        //   - Tambah Saldo (topup)    → hanya untuk Cash, untuk non-Cash
        //                              tab di-HIDE total dari tab bar
        //                              (uang baru selalu masuk sebagai cash dulu,
        //                              isi Bank/E-Wallet via Transfer dari Cash)
        transferModal.querySelectorAll('[data-tab-target]').forEach(function (btn) {
            const target = btn.dataset.tabTarget;
            let disabled = false;
            let hidden   = false;
            let reason   = '';
            if (target === 'transfer' || target === 'expense') {
                if (!hasSaldo) {
                    disabled = true;
                    reason = 'Tidak bisa ' + (target === 'transfer' ? 'transfer' : 'pengeluaran') + ' — saldo kosong';
                }
            } else if (target === 'topup') {
                if (!isCash) {
                    hidden = true; // sembunyikan total dari tab bar
                }
            }
            btn.disabled = disabled;
            btn.classList.toggle('is-disabled', disabled);
            btn.classList.toggle('is-hidden', hidden);
            btn.style.display = hidden ? 'none' : '';
            btn.title = reason;
        });

        // Pilih default tab pertama yang available (tidak disabled, tidak hidden).
        // Priority:
        //   - Punya saldo → Transfer dulu
        //   - Saldo 0 + Cash → Topup
        //   - Saldo 0 + non-Cash → Transfer / Expense (semua disabled, fallback ke yg ada)
        const priority = hasSaldo
            ? ['transfer', 'topup', 'expense']
            : (isCash ? ['topup', 'transfer', 'expense'] : ['transfer', 'expense']);
        for (const t of priority) {
            const btn = transferModal.querySelector('[data-tab-target="' + t + '"]');
            if (btn && !btn.disabled && btn.style.display !== 'none') {
                switchModalTab(t);
                break;
            }
        }
        transferModal.classList.add('is-open');
    }

    document.querySelectorAll('[data-pc-source-card]').forEach(function (card) {
        card.addEventListener('click', function () { openSourceModalFromCard(card); });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openSourceModalFromCard(card);
            }
        });
    });

    // Tab switcher buttons
    if (transferModal) {
        transferModal.querySelectorAll('[data-tab-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchModalTab(btn.dataset.tabTarget);
            });
        });
    }

    // Cap nominal ke saldo max (transfer & pengeluaran)
    function attachAmountCap(visibleSelector, hiddenId) {
        if (!transferModal) return;
        const visible = transferModal.querySelector(visibleSelector);
        if (!visible) return;
        visible.addEventListener('input', function () {
            const max = parseFloat(this.dataset.maxAmount || '0');
            const raw = parseInt(this.value.replace(/\D/g, ''), 10);
            if (max > 0 && raw > max) {
                this.value = max.toLocaleString('id-ID');
                const hidden = document.getElementById(hiddenId);
                if (hidden) hidden.value = String(max);
            }
        });
    }
    attachAmountCap('[data-target="transferAmount"]',      'transferAmount');
    attachAmountCap('[data-target="expenseSourceAmount"]', 'expenseSourceAmount');

    // Preview badge sumber dana saat user pilih option di dropdown.
    document.querySelectorAll('.pc-source-select').forEach(function (sel) {
        const preview = sel.parentElement.querySelector('[data-pc-source-preview]');
        if (!preview) return;
        function render() {
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) { preview.innerHTML = ''; return; }
            const color = opt.dataset.color || '#64748b';
            const short = opt.dataset.short || '?';
            const logo  = opt.dataset.logo  || '';
            const name  = opt.textContent.trim();
            const inner = logo
                ? '<img src="' + logo + '" alt="" class="fs-badge__img">'
                : '<span class="fs-badge__text">' + short + '</span>';
            preview.innerHTML =
                '<span class="fs-badge fs-badge--md" style="background:' + color + '">' + inner + '</span>' +
                '<span class="pc-source-preview__name">' + name + '</span>';
        }
        sel.addEventListener('change', render);
        render();
    });
})();
</script>
@endsection
