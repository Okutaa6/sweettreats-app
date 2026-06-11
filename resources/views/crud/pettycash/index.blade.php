@extends('layouts.app')

@section('content')
@php
    $monthName = $bulanLabel[$month] ?? '-';
    $rupiah = function ($v) { return 'Rp ' . number_format((float) $v, 0, ',', '.'); };
    $kind     = $kind ?? 'petty_cash';
    $tabs     = $tabs ?? [];
    $tabLabel = collect($tabs)->firstWhere('kind', $kind)['label'] ?? 'Petty cash';

    // Helper untuk preserve branch di semua URL (tab switcher, link footer
    // bulan ini, dll). Sales (di-lock via session) tidak perlu ?branch=.
    $branches        = $branches ?? collect();
    $branchId        = $branchId ?? null;
    $activeBranch    = $activeBranch ?? null;
    $branchAccountMap = $branchAccountMap ?? [];
    $branchParam     = $branches->isNotEmpty() && $branchId ? ['branch' => $branchId] : [];
@endphp
<div class="container-fluid">

    {{-- ─── HEADER ─── --}}
    <div class="pc-page-header pc-page-header--v2">
        <div class="pc-page-header__main">
            <div class="pc-page-icon">
                <i class="fa fa-wallet"></i>
            </div>
            <div class="pc-page-header__heading">
                <h4 class="pc-page-title">{{ $tabLabel }}</h4>
                <div class="pc-page-meta">
                    <span class="pc-page-meta__item">
                        <i class="fa fa-calendar"></i> {{ $monthName }} {{ $year }}
                    </span>
                    @if($activeBranch)
                        <span class="pc-page-meta__divider"></span>
                        <span class="pc-branch-chip">
                            <i class="fa fa-building"></i> {{ $activeBranch->name }}
                        </span>
                    @endif
                    <span class="pc-page-meta__divider"></span>
                    @if($period->is_closed)
                        <span class="pc-status pc-status--closed">
                            <i class="fa fa-lock"></i> Ditutup
                        </span>
                    @elseif($isCurrentMonth)
                        <span class="pc-status pc-status--active">
                            <span class="pc-status__dot"></span> Bulan berjalan
                        </span>
                    @else
                        <span class="pc-status pc-status--past">
                            <i class="fa fa-clock-rotate-left"></i> Lampau
                        </span>
                    @endif
                </div>
            </div>
        </div>
        <div class="pc-page-header__actions">
            <a href="{{ route('pettycash.export', array_filter([
                    'tab'        => $kind,
                    'branch'     => $branches->isNotEmpty() ? $branchId : null,
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
               ])) }}"
               class="st-btn-outline-brown"
               title="Download Excel: hanya top up & pengeluaran (transfer di-skip)">
                <i class="fa fa-file-excel"></i> Export Excel
            </a>
            @if(isSuperAdmin())
                <a href="{{ route('fundsource') }}" class="st-btn-outline-brown" title="Kelola sumber dana & logo brand">
                    <i class="fa fa-cog"></i> Kelola Sumber Dana
                </a>
            @endif
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── TAB NAVIGATION ─── --}}
    @if(count($tabs) > 1)
        <div class="pc-tabs" role="tablist">
            @foreach($tabs as $t)
                <a href="{{ route('pettycash', array_merge(['tab' => $t['kind']], $branchParam)) }}"
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
        'branches'      => $branches,
        'branchId'      => $branchId,
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
                            @php
                                $isComposite  = ! empty($r->is_composite);
                                $isTarikTunai = $r->is_transfer
                                    && \Illuminate\Support\Str::startsWith((string) $r->description, 'Tarik tunai');
                                $isDeposit    = $r->is_transfer
                                    && \Illuminate\Support\Str::startsWith((string) $r->description, 'Deposit');
                                $pillLabel    = $isTarikTunai ? 'tarik tunai' : ($isDeposit ? 'deposit' : 'transfer');
                                $rowId        = 'pcrow-' . ($r->ref_no ?: $r->id);
                            @endphp
                            <tr class="{{ $r->is_topup ? 'pc-row-topup' : '' }} {{ $r->is_transfer ? 'pc-row-transfer' : '' }} {{ $isComposite ? 'pc-row-composite' : '' }}"
                                @if($isComposite) data-pc-composite-parent="{{ $rowId }}" @endif>
                                <td class="pc-date">{{ \Carbon\Carbon::parse($r->trx_date)->translatedFormat('d M') }}</td>
                                <td>
                                    @if($isComposite && isset($r->source_to))
                                        {{-- Dua badge dengan panah: dari → ke --}}
                                        <span class="pc-source-inline pc-source-inline--pair">
                                            @include('crud.pettycash._fund_source_badge', [
                                                'color' => $r->source_color,
                                                'short' => $r->source_short,
                                                'logo'  => $r->source_logo,
                                                'name'  => $r->source_name,
                                                'size'  => 'sm',
                                            ])
                                            <i class="fa fa-arrow-right pc-source-inline__arrow"></i>
                                            @include('crud.pettycash._fund_source_badge', [
                                                'color' => $r->source_to->color,
                                                'short' => $r->source_to->short,
                                                'logo'  => $r->source_to->logo,
                                                'name'  => $r->source_to->name,
                                                'size'  => 'sm',
                                            ])
                                        </span>
                                    @elseif($r->source_name)
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
                                    @if($isComposite)
                                        <button type="button" class="pc-row-toggle"
                                                data-pc-toggle-target="{{ $rowId }}"
                                                aria-expanded="false"
                                                title="Lihat detail">
                                            <i class="fa fa-chevron-right"></i>
                                        </button>
                                    @endif
                                    @if($r->is_topup)
                                        <i class="fa-solid fa-arrow-down-up-across-line" style="color:#16a34a;"></i>
                                    @elseif($r->is_transfer && ! $isComposite)
                                        <i class="fa-solid fa-right-left" style="color:#2563eb;"></i>
                                    @endif
                                    <strong>{{ $r->description }}</strong>
                                    @if($r->is_topup)
                                        <span class="pc-pill-topup">top up</span>
                                    @elseif($r->is_transfer)
                                        <span class="pc-pill-transfer">{{ $pillLabel }}</span>
                                    @endif
                                </td>
                                <td class="text-right pc-cash-in {{ $r->is_transfer ? 'pc-cash--internal' : '' }}">
                                    {{ $isComposite ? '—' : ($r->cash_in > 0 ? number_format($r->cash_in, 0, ',', '.') : '—') }}
                                </td>
                                <td class="text-right pc-cash-out {{ $r->is_transfer ? 'pc-cash--internal' : '' }}">
                                    {{ $isComposite ? '—' : ($r->cash_out > 0 ? number_format($r->cash_out, 0, ',', '.') : '—') }}
                                </td>
                                <td class="text-right pc-saldo-cell">{{ number_format($r->saldo, 0, ',', '.') }}</td>
                                @if(!$isReadOnly)
                                    <td>
                                        <form method="POST" action="{{ route('pettycash.destroy', ['id' => $r->id, 'tab' => $kind]) }}"
                                              onsubmit="return confirm('{{ $isComposite ? 'Hapus transfer ini (kedua sisi)? Tidak bisa di-undo.' : 'Hapus transaksi ini? Tidak bisa di-undo.' }}')" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            @if(!session('raw_branch_id') && $branchId)
                                                <input type="hidden" name="branch" value="{{ $branchId }}">
                                            @endif
                                            <button type="submit" class="pc-icon-btn" title="Hapus transaksi">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                            @if($isComposite && !empty($r->children))
                                @foreach($r->children as $child)
                                    <tr class="pc-row-transfer pc-row-child" data-pc-composite-child="{{ $rowId }}" hidden>
                                        <td class="pc-date pc-row-child__date">↳</td>
                                        <td>
                                            @if($child->source_name)
                                                <span class="pc-source-inline">
                                                    @include('crud.pettycash._fund_source_badge', [
                                                        'color' => $child->source_color,
                                                        'short' => $child->source_short,
                                                        'logo'  => $child->source_logo,
                                                        'name'  => $child->source_name,
                                                        'size'  => 'sm',
                                                    ])
                                                    <span class="pc-source-inline__name">{{ $child->source_name }}</span>
                                                </span>
                                            @endif
                                        </td>
                                        <td class="pc-row-child__desc">
                                            <i class="fa fa-arrow-{{ $child->cash_in > 0 ? 'down-long' : 'up-long' }}"
                                               style="color: {{ $child->cash_in > 0 ? '#16a34a' : '#dc2626' }};"></i>
                                            {{ $child->description }}
                                        </td>
                                        <td class="text-right pc-cash-in pc-cash--internal">
                                            {{ $child->cash_in > 0 ? number_format($child->cash_in, 0, ',', '.') : '—' }}
                                        </td>
                                        <td class="text-right pc-cash-out pc-cash--internal">
                                            {{ $child->cash_out > 0 ? number_format($child->cash_out, 0, ',', '.') : '—' }}
                                        </td>
                                        <td class="text-right pc-saldo-cell pc-muted">—</td>
                                        @if(!$isReadOnly)<td></td>@endif
                                    </tr>
                                @endforeach
                            @endif
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
        'branchAccountMap'   => $branchAccountMap,
    ])
    @include('crud.pettycash.modal-transfer', [
        'period'             => $period,
        'kind'               => $kind,
        'fundSourcesGrouped' => $fundSourcesGrouped,
        'branchAccountMap'   => $branchAccountMap,
        'sourceBalances'     => $sourceBalances ?? [],
    ])
    @include('crud.pettycash.modal-opening', [
        'period'             => $period,
        'kind'               => $kind,
        'fundSourcesGrouped' => $fundSourcesGrouped,
        'branchAccountMap'   => $branchAccountMap ?? [],
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
        // Danger zone: populate source id untuk form "Hapus Data Sumber Dana Ini".
        const destroyIdInput = document.getElementById('pcSourceDestroyId');
        if (destroyIdInput) destroyIdInput.value = id;
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

        // Khusus source Cash/Tunai: tab "Transfer" diberi label "Deposit"
        // karena dari sudut pandang Cash, memindah dana ke Bank/E-Wallet
        // lebih natural disebut sebagai setor / deposit. Submit button
        // di tab transfer juga ikut berubah.
        const transferTabBtn = transferModal.querySelector('[data-tab-target="transfer"]');
        const transferSubmit = transferModal.querySelector('[data-tab-content="transfer"] button[type="submit"]');
        const transferToLabel = document.getElementById('transferToLabel');
        if (transferTabBtn) {
            transferTabBtn.innerHTML = isCash
                ? '<i class="fa fa-piggy-bank"></i> Deposit'
                : '<i class="fa fa-right-left"></i> Transfer';
        }
        if (transferSubmit) {
            transferSubmit.innerHTML = isCash
                ? '<i class="fa fa-piggy-bank"></i> Deposit Saldo'
                : '<i class="fa fa-right-left"></i> Transfer Saldo';
        }
        // Label "Transfer ke" — dari Bank / E-Wallet bisa tarik tunai
        // (ke Cash) atau transfer ke wallet lain, jadi labelnya gabungan.
        // Dari Cash, options-nya selalu setor ke wallet lain → "Deposit ke".
        if (transferToLabel) {
            transferToLabel.innerHTML = isCash
                ? 'Deposit ke <span class="text-danger">*</span>'
                : 'Tarik Tunai / Transfer ke <span class="text-danger">*</span>';
        }
        // Hint text di atas form Transfer — narasinya ikut konteks source.
        const hintIcon = document.getElementById('transferTabHintIcon');
        const hintText = document.getElementById('transferTabHintText');
        if (hintIcon && hintText) {
            if (isCash) {
                hintIcon.className = 'fa fa-piggy-bank';
                hintIcon.style.color = '#7c2d12';
                hintText.innerHTML = '<strong>Deposit:</strong> setor saldo dari Cash ke Bank / E-Wallet. Total saldo periode <em>tidak berubah</em> — hanya pindah antar wallet (mis: setor tunai ke bank, isi e-wallet).';
            } else {
                hintIcon.className = 'fa fa-right-left';
                hintIcon.style.color = '#2563eb';
                hintText.innerHTML = '<strong>Transfer:</strong> pindahkan saldo dari sumber ini ke sumber dana lain. Total saldo periode <em>tidak berubah</em> — hanya redistribusi antar wallet (mis: tarik tunai dari bank, isi e-wallet dari cash).';
            }
        }

        // Tab Tambah Saldo punya 2 varian:
        //   - external    : default untuk Bank / E-Wallet (form lama,
        //                   dana baru masuk dari luar).
        //   - from-source : khusus Cash (pilih sumber lain dgn saldo,
        //                   nominal muncul setelah pilih, submit sebagai
        //                   transfer ke Cash). Label tab ikut berubah
        //                   jadi "Tarik Tunai" karena itu yang dilakukan
        //                   secara nyata: cairin saldo Bank/E-Wallet jadi
        //                   uang fisik di kasir.
        const variantExternal   = transferModal.querySelector('[data-pc-topup-variant="external"]');
        const variantFromSource = transferModal.querySelector('[data-pc-topup-variant="from-source"]');
        if (variantExternal && variantFromSource) {
            variantExternal.hidden   = isCash;
            variantFromSource.hidden = !isCash;
        }
        const topupTabBtn = transferModal.querySelector('[data-tab-target="topup"]');
        if (topupTabBtn) {
            topupTabBtn.innerHTML = isCash
                ? '<i class="fa fa-piggy-bank"></i> Tarik Tunai'
                : '<i class="fa fa-arrow-down-up-across-line"></i> Tambah Saldo';
        }

        if (isCash) {
            // Lock destination = source Cash yang barusan diklik.
            const toIdInput = document.getElementById('topupCashToSourceId');
            if (toIdInput) toIdInput.value = id;

            // Reset dropdown & nominal. Mode default = migrasi (sumber kosong,
            // no cap, submit enabled). Submit baru terhalang kalau nominal=0
            // (HTML5 required di input hidden).
            const fromSel  = document.getElementById('topupCashFromSelect');
            const amtVis   = document.querySelector('[data-target="topupCashAmount"]');
            const amtHid   = document.getElementById('topupCashAmount');
            const cashHint = document.getElementById('topupCashHint');
            const submit   = document.getElementById('topupCashSubmitBtn');
            if (fromSel) {
                fromSel.value = '';
                fromSel.disabled = false;
            }
            if (amtVis) { amtVis.value = ''; amtVis.dataset.maxAmount = ''; }
            if (amtHid) amtHid.value = '';
            if (cashHint) cashHint.textContent = 'Setor langsung — input nominal cash yang sudah di tangan.';
            if (submit) submit.disabled = false;
        }

        // Form Top up: lock fund_source_id ke source asal, reset amount
        document.getElementById('topupSourceFundSourceId').value = id;
        const topupAmtVisible = document.querySelector('[data-target="topupSourceAmount"]');
        const topupAmtHidden  = document.getElementById('topupSourceAmount');
        if (topupAmtVisible) topupAmtVisible.value = '';
        if (topupAmtHidden)  topupAmtHidden.value  = '';

        // Account row di tab Tambah Saldo — toggle berdasarkan kategori
        // source, pre-fill dengan nomor existing kalau ada.
        const acctValue = card.dataset.sourceAccount || '';
        const isBank    = category === 'bank';
        const isEwallet = category === 'ewallet';
        const srcAcctRow   = transferModal.querySelector('[data-pc-topup-src-account-row]');
        const srcAcctLabel = transferModal.querySelector('[data-pc-topup-src-account-label]');
        const srcAcctInput = transferModal.querySelector('[data-pc-topup-src-account-input]');
        if (srcAcctRow && srcAcctLabel && srcAcctInput) {
            if (isBank || isEwallet) {
                srcAcctRow.hidden = false;
                srcAcctLabel.innerHTML = (isBank ? 'No. Rekening' : 'No. Telepon')
                    + ' <small class="pc-muted">(opsional)</small>';
                srcAcctInput.placeholder = isBank ? 'Mis: 1234567890' : 'Mis: 08123456789';
                srcAcctInput.value = acctValue;
            } else {
                srcAcctRow.hidden = true;
                srcAcctInput.value = '';
            }
        }

        // From-source preview: tampilkan nomor + copy button kalau ada
        const fromAcctWrap   = document.getElementById('transferFromAccount');
        const fromAcctLabel  = document.getElementById('transferFromAccountLabel');
        const fromAcctValEl  = document.getElementById('transferFromAccountValue');
        const fromAcctCopy   = document.getElementById('transferFromAccountCopy');
        if (fromAcctWrap && fromAcctLabel && fromAcctValEl && fromAcctCopy) {
            if (acctValue && (isBank || isEwallet)) {
                fromAcctWrap.hidden = false;
                fromAcctLabel.textContent = isBank ? 'No. Rek' : 'No. HP';
                fromAcctValEl.textContent = acctValue;
                fromAcctCopy.dataset.pcCopy = acctValue;
            } else {
                fromAcctWrap.hidden = true;
            }
        }

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
    attachAmountCap('[data-target="topupCashAmount"]',     'topupCashAmount');

    // Tab Tambah Saldo Cash: 2 mode tergantung apakah sumber dipilih.
    //   - Sumber dipilih → tarik dari Bank/E-Wallet ke Cash (submit ke endpoint
    //     transfer, nominal di-cap = saldo sumber).
    //   - Sumber dikosongkan → setor cash langsung / migrasi (submit ke endpoint
    //     topup dengan fund_source_id = Cash, nominal bebas).
    // Submit selalu enabled selama nominal valid — source bisa kosong.
    (function () {
        const form    = document.getElementById('topupCashForm');
        const fromSel = document.getElementById('topupCashFromSelect');
        if (!fromSel || !transferModal || !form) return;
        const amtVis  = document.querySelector('[data-target="topupCashAmount"]');
        const amtHid  = document.getElementById('topupCashAmount');
        const hint    = document.getElementById('topupCashHint');
        const submit  = document.getElementById('topupCashSubmitBtn');
        const toIdInp = document.getElementById('topupCashToSourceId');
        const fmt     = function (n) { return Math.round(n).toLocaleString('id-ID'); };

        // Cap = balance source kalau dipilih, atau no cap kalau migrasi.
        // Label tombol submit ikut menyesuaikan mode.
        function syncMode() {
            const opt = fromSel.options[fromSel.selectedIndex];
            const bal = parseFloat((opt && opt.dataset.balance) || '0');
            if (opt && opt.value && bal > 0) {
                if (amtVis) amtVis.dataset.maxAmount = String(bal);
                if (hint) hint.textContent = 'Maks: Rp ' + fmt(bal);
                if (submit) submit.innerHTML = '<i class="fa fa-piggy-bank"></i> Tarik Tunai';
            } else {
                if (amtVis) amtVis.dataset.maxAmount = '';
                if (hint) hint.textContent = 'Setor langsung — input nominal cash yang sudah di tangan.';
                if (submit) submit.innerHTML = '<i class="fa fa-piggy-bank"></i> Setor Cash';
            }
            if (submit) submit.disabled = false;
        }
        fromSel.addEventListener('change', function () {
            if (amtVis) amtVis.value = '';
            if (amtHid) amtHid.value = '';
            syncMode();
        });
        syncMode();

        // Saat submit, swap action & nama field tergantung mode.
        //   - Mode transfer: action = transfer endpoint, to_source_id = Cash (existing),
        //     from_source_id = source yang dipilih.
        //   - Mode migrasi: action = topup endpoint, kirim fund_source_id = Cash
        //     (rename to_source_id → fund_source_id supaya backend topup terima).
        form.addEventListener('submit', function () {
            const from = fromSel.value;
            if (from) {
                form.action = form.dataset.actionTransfer;
                if (toIdInp) toIdInp.name = 'to_source_id';
            } else {
                form.action = form.dataset.actionTopup;
                if (toIdInp) toIdInp.name = 'fund_source_id';
                fromSel.disabled = true; // jangan kirim from_source_id kosong
            }
        });
    })();

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

    // Transfer modal: toggle input nomor rekening / telepon untuk SUMBER
    // TUJUAN berdasarkan kategori. Cash → hidden. Pre-fill dengan nomor
    // existing (kalau ada) — user bisa update sambil transfer.
    (function () {
        const sel   = document.getElementById('transferToSelect');
        if (!sel) return;
        const row   = transferModal && transferModal.querySelector('[data-pc-transfer-to-account-row]');
        const label = transferModal && transferModal.querySelector('[data-pc-transfer-to-account-label]');
        const input = transferModal && transferModal.querySelector('[data-pc-transfer-to-account-input]');
        if (!row || !label || !input) return;
        function sync() {
            const opt  = sel.options[sel.selectedIndex];
            const cat  = (opt && opt.dataset.category) || '';
            const acct = (opt && opt.dataset.account)  || '';
            if (cat === 'bank' || cat === 'ewallet') {
                row.hidden = false;
                const isBank = cat === 'bank';
                label.innerHTML = (isBank ? 'No. Rekening' : 'No. Telepon')
                    + ' <small class="pc-muted">(opsional)</small>';
                input.placeholder = isBank ? 'Mis: 1234567890' : 'Mis: 08123456789';
                input.value = acct;
            } else {
                row.hidden = true;
                input.value = '';
            }
        }
        sel.addEventListener('change', sync);
        sync();
    })();

    // Modal Top Up Saldo (global): toggle input nomor rekening / telepon
    // berdasarkan kategori sumber dana yang dipilih. Pre-fill dengan nomor
    // existing kalau ada (form ini bisa dipakai untuk update juga).
    document.querySelectorAll('[data-pc-topup-source]').forEach(function (sel) {
        const form    = sel.closest('form');
        if (!form) return;
        const row     = form.querySelector('[data-pc-topup-account-row]');
        const label   = form.querySelector('[data-pc-topup-account-label]');
        const input   = form.querySelector('[data-pc-topup-account-input]');
        if (!row || !label || !input) return;
        function sync() {
            const opt = sel.options[sel.selectedIndex];
            const cat = (opt && opt.dataset.category) || '';
            const acct = (opt && opt.dataset.account) || '';
            if (cat === 'bank' || cat === 'ewallet') {
                row.hidden = false;
                const isBank = cat === 'bank';
                label.innerHTML = (isBank ? 'No. Rekening' : 'No. Telepon')
                    + ' <small class="pc-muted">(opsional)</small>';
                input.placeholder = isBank ? 'Mis: 1234567890' : 'Mis: 08123456789';
                input.value = acct;
            } else {
                row.hidden = true;
                input.value = '';
            }
        }
        sel.addEventListener('change', sync);
        sync();
    });

    // Toggle expand/collapse untuk composite transfer row di tabel.
    // Composite row punya 2 child <tr> (sisi OUT & IN) yang hidden by
    // default. Klik tombol [data-pc-toggle-target=ID] → unhide children
    // dengan data-pc-composite-child=ID.
    document.querySelectorAll('[data-pc-toggle-target]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const key = btn.dataset.pcToggleTarget;
            const children = document.querySelectorAll('[data-pc-composite-child="' + key + '"]');
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            children.forEach(function (tr) { tr.hidden = expanded; });
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-chevron-right', expanded);
                icon.classList.toggle('fa-chevron-down',  !expanded);
            }
        });
    });

    // Copy-to-clipboard untuk tombol .pc-copy-btn. Direct listener (bukan
    // delegation) karena tombol ini kadang nested di dalam container yang
    // call stopPropagation di onclick — kalau pakai document delegation,
    // event tidak pernah sampai ke document & handler tidak fire.
    function flashCopied(btn) {
        const original = btn.innerHTML;
        btn.classList.add('is-copied');
        btn.innerHTML = '<i class="fa fa-check"></i>';
        setTimeout(function () {
            btn.classList.remove('is-copied');
            btn.innerHTML = original;
        }, 1200);
    }
    function copyText(btn, text) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () { flashCopied(btn); });
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); flashCopied(btn); }
            catch (_) {}
            document.body.removeChild(ta);
        }
    }
    document.querySelectorAll('.pc-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            copyText(btn, btn.dataset.pcCopy || '');
        });
    });
})();
</script>
@endsection
