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
                    'tab'            => $kind,
                    'branch'         => $branches->isNotEmpty() ? $branchId : null,
                    'start_date'     => $startDate,
                    'end_date'       => $endDate,
                    'category'       => $category,
                    'q'              => $search,
                    'fund_source_id' => ($fundSourceId ?? 0) ?: null,
               ])) }}"
               class="st-btn-outline-brown st-btn-excel"
               data-pc-tooltip-pos="bottom"
               data-pc-tooltip="Download laporan transaksi periode aktif ke Excel. Semua filter aktif (tanggal, cabang, kategori, wallet, search) ikut ke file. Transfer internal di-skip karena bersifat netral.">
                <i class="fa fa-file-excel"></i> Export Excel
            </a>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── READ-ONLY / VIEW-ONLY BANNER ─── --}}
    @if($isReadOnly)
        @php $isFinanceViewOnly = $isFinanceViewOnly ?? false; @endphp
        <div class="pc-readonly-banner {{ $isFinanceViewOnly ? 'pc-readonly-banner--finance' : '' }}" role="alert">
            <i class="fa {{ $isFinanceViewOnly ? 'fa-eye' : 'fa-lock' }} pc-readonly-banner__icon"></i>
            <div class="pc-readonly-banner__body">
                @if($isFinanceViewOnly)
                    <strong>Mode View-Only — role Finance.</strong>
                    Anda hanya bisa melihat data Petty Cash cabang. Penambahan, edit, dan hapus
                    transaksi (Top Up, Pengeluaran, Mutasi, Tutup Periode) dilakukan oleh
                    pemegang akun cabang atau admin.
                @elseif($period->is_closed)
                    <strong>Periode {{ $monthName }} {{ $year }} sudah ditutup.</strong>
                    Data hanya bisa dilihat — tidak dapat ditambah, diedit, atau dihapus.
                    Hubungi Admin General jika perlu buka kembali.
                @else
                    <strong>Anda sedang melihat periode lampau ({{ $monthName }} {{ $year }}).</strong>
                    Data hanya bisa dilihat — untuk catat transaksi baru, kembali ke bulan berjalan.
                @endif
            </div>
        </div>
        <style>
            .pc-readonly-banner{display:flex;align-items:flex-start;gap:10px;background:#fef3c7;border-left:4px solid #f59e0b;color:#78350f;padding:12px 16px;border-radius:6px;margin:0 0 16px;font-size:14px;line-height:1.5;}
            .pc-readonly-banner__icon{font-size:18px;margin-top:2px;color:#b45309;flex-shrink:0;}
            .pc-readonly-banner__body strong{color:#78350f;}
            /* Varian Finance: ganti palette ke biru (informational, bukan warning) */
            .pc-readonly-banner--finance{background:#dbeafe;border-left-color:#2563eb;color:#1e3a8a;}
            .pc-readonly-banner--finance .pc-readonly-banner__icon{color:#1d4ed8;}
            .pc-readonly-banner--finance .pc-readonly-banner__body strong{color:#1e3a8a;}
        </style>
    @endif

    {{-- ─── SUMMARY CARDS ─── --}}
    @include('crud.pettycash._summary_cards', [
        'summary'            => $summary,
        'rupiah'             => $rupiah,
        'sourceBalances'     => $sourceBalances ?? [],
        'isReadOnly'         => $isReadOnly,
        'kind'               => $kind,
        'branchId'           => $branchId,
        'pendingTopupCount'  => $pendingTopupCount ?? 0,
    ])

    {{-- ─── FILTER ─── --}}
    @include('crud.pettycash._filter', [
        'startDate'       => $startDate,
        'endDate'         => $endDate,
        'monthStartStr'   => $monthStartStr,
        'monthEndStr'     => $monthEndStr,
        'search'          => $search,
        'category'        => $category,
        'categoryOptions' => $categoryOptions,
        'fundSources'     => $fundSources,
        'fundSourceId'    => $fundSourceId ?? 0,
        'isReadOnly'      => $isReadOnly,
        'kind'            => $kind,
        'branches'        => $branches,
        'branchId'        => $branchId,
        'year'            => $year,
        'month'           => $month,
        'bulanLabel'      => $bulanLabel,
    ])

    {{-- ─── TABLE — split jadi 2 sub-tab ─── --}}
    @php
        // Klasifikasi "setor cash langsung": cash IN tapi BUKAN top up &
        // BUKAN transfer (dibuat via endpoint topup dengan is_topup=0).
        // Dipindahkan ke tab Mutasi supaya tab utama Pengeluaran cuma
        // berisi Top Up + Pengeluaran murni.
        $isSetorCash = function ($r) {
            return ! $r->is_transfer && ! $r->is_topup && (float) $r->cash_in > 0;
        };

        // Pisahkan transaksi:
        //   - regular  → top up + pengeluaran (bukan transfer, bukan setor cash)
        //   - transfer → composite transfer/deposit/tarik tunai + setor cash langsung
        $rowsRegular  = collect($rows)
            ->reject(fn($r) => $r->is_transfer || $isSetorCash($r))
            ->values();
        $rowsTransfer = collect($rows)
            ->filter(fn($r) => $r->is_transfer || $isSetorCash($r))
            ->values();

        $tableHasAnySource = collect($sourceBalances ?? [])->contains(function ($sb) {
            return abs((float) ($sb->balance ?? 0)) > 0.005
                || abs((float) ($sb->in ?? 0))      > 0.005
                || abs((float) ($sb->out ?? 0))     > 0.005;
        });

        $emptyDescRegular  = 'Tab ini menampilkan <strong>Top Up</strong> (dana masuk) dan <strong>Pengeluaran</strong> dari wallet, lengkap dengan tanggal, wallet, dan nominal.';
        $emptyDescTransfer = 'Tab ini menampilkan <strong>Transfer</strong>, <strong>Deposit</strong>, <strong>Tarik Tunai</strong>, dan <strong>Setor Cash Langsung</strong> &mdash; pergerakan saldo antar wallet maupun setoran fisik di luar top up.';

        // CTA box (info + tombol) di empty state tabel. Berbeda per tab &
        // per state (apakah sudah ada wallet aktif atau belum).
        $emptyCtaRegular  = null;
        $emptyCtaTransfer = null;
        if (! $isReadOnly) {
            if (! $tableHasAnySource) {
                // Belum ada wallet — arahkan ke setoran awal untuk kedua tab.
                $emptyCtaRegular = '<i class="fa fa-info-circle pc-empty-state__cta-icon"></i>'
                    . '<span class="pc-empty-state__cta-text">Belum ada wallet aktif &mdash; mulai dari setoran awal.</span>'
                    . '<button type="button" class="st-btn-brand" data-pc-modal="pcModalOpening">Mulai Setoran Awal</button>';
                $emptyCtaTransfer = $emptyCtaRegular;
            } else {
                // Sudah ada wallet — arahkan ke aksi yg relevan per tab.
                $emptyCtaRegular = '<i class="fa fa-info-circle pc-empty-state__cta-icon"></i>'
                    . '<span class="pc-empty-state__cta-text">Catat top up atau pengeluaran dengan klik kartu wallet di atas.</span>';
                $emptyCtaTransfer = '<i class="fa fa-info-circle pc-empty-state__cta-icon"></i>'
                    . '<span class="pc-empty-state__cta-text">Catat perpindahan dengan klik kartu wallet di atas.</span>';
            }
        }
    @endphp

    <div class="st-card mt-3 pc-table-card pc-trx-card" data-active-trx-tab="regular">
        {{-- Sub-tabs --}}
        <div class="pc-trx-subtabs" role="tablist">
            <button type="button"
                    class="pc-trx-subtab is-active"
                    data-trx-tab="regular"
                    role="tab"
                    aria-selected="true">
                <i class="fa fa-arrow-down-up-across-line"></i>
                Pengeluaran
                <span class="pc-trx-subtab__count">{{ $rowsRegular->count() }}</span>
            </button>
            <button type="button"
                    class="pc-trx-subtab"
                    data-trx-tab="transfer"
                    role="tab"
                    aria-selected="false">
                <i class="fa fa-right-left"></i>
                Mutasi
                <span class="pc-trx-subtab__count">{{ $rowsTransfer->count() }}</span>
            </button>
        </div>

        {{-- TAB 1: Top Up & Pengeluaran --}}
        <div class="st-card__body pc-trx-pane" data-trx-content="regular" style="padding:0;">
            <div class="st-table-wrap--scroll">
                <table class="st-table pc-table">
                    <thead>
                        <tr>
                            <th width="110">Tanggal</th>
                            <th width="140">Wallet</th>
                            <th>Keterangan</th>
                            <th class="text-right" width="130">Cash in</th>
                            <th class="text-right" width="130">Cash out</th>
                            <th class="text-right" width="150">Saldo</th>
                            @if(!$isReadOnly)
                                <th width="80"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @include('crud.pettycash._table_rows', [
                            'rowsInput'  => $rowsRegular,
                            'isReadOnly' => $isReadOnly,
                            'kind'       => $kind,
                            'branchId'   => $branchId,
                            'tabKind'    => 'regular',
                            'emptyTitle' => 'Belum ada Top Up atau Pengeluaran bulan ini',
                            'emptyDesc'  => $emptyDescRegular,
                            'emptyIcon'  => 'fa-file-alt',
                            'emptyCta'   => $emptyCtaRegular,
                        ])
                    </tbody>
                    @if($rowsRegular->count() > 0)
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

        {{-- TAB 2: Mutasi Antar Wallet (Transfer / Deposit / Tarik Tunai) --}}
        <div class="st-card__body pc-trx-pane" data-trx-content="transfer" style="padding:0;" hidden>
            <div class="st-table-wrap--scroll">
                <table class="st-table pc-table">
                    <thead>
                        <tr>
                            <th width="110">Tanggal</th>
                            <th width="180">Wallet → Tujuan</th>
                            <th>Keterangan</th>
                            <th class="text-right" width="130">Cash in</th>
                            <th class="text-right" width="130">Cash out</th>
                            <th class="text-right" width="150">Saldo</th>
                            @if(!$isReadOnly)
                                <th width="80"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @include('crud.pettycash._table_rows', [
                            'rowsInput'  => $rowsTransfer,
                            'isReadOnly' => $isReadOnly,
                            'kind'       => $kind,
                            'branchId'   => $branchId,
                            'tabKind'    => 'transfer',
                            'emptyTitle' => 'Belum ada Mutasi Antar Wallet bulan ini',
                            'emptyDesc'  => $emptyDescTransfer,
                            'emptyIcon'  => 'fa-exchange-alt',
                            'emptyCta'   => $emptyCtaTransfer,
                        ])
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- ─── Lightbox preview untuk bukti pengeluaran ───
     Dipakai saat user klik icon paperclip di tabel. Support gambar
     (jpg/png/webp) tampil inline + PDF tampil via <iframe>. --}}
<div class="pc-lightbox" id="pcAttachLightbox" role="dialog" aria-modal="true" hidden>
    <div class="pc-lightbox__backdrop" data-pc-lightbox-close></div>
    <div class="pc-lightbox__dialog">
        <div class="pc-lightbox__header">
            <div class="pc-lightbox__title">
                <i class="fa fa-paperclip"></i> Bukti Pengeluaran
            </div>
            <div class="pc-lightbox__actions">
                <a href="#" class="pc-lightbox__btn" id="pcAttachDownload" download
                   title="Download file">
                    <i class="fa fa-download"></i>
                </a>
                <a href="#" class="pc-lightbox__btn" id="pcAttachOpenTab"
                   target="_blank" rel="noopener"
                   title="Buka di tab baru">
                    <i class="fa fa-external-link-alt"></i>
                </a>
                <button type="button" class="pc-lightbox__btn pc-lightbox__btn--close"
                        data-pc-lightbox-close
                        title="Tutup (Esc)">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
        <div class="pc-lightbox__body" id="pcAttachBody">
            <!-- isi dinamis via JS: <img> atau <iframe> -->
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
    @include('crud.pettycash.modal-edit-expense', [
        'period' => $period,
        'kind'   => $kind,
    ])

    {{-- Modal Ajukan Top Up — Sales selalu, admin saat buka cabang spesifik --}}
    @if($branchId && !$isReadOnly)
        @include('crud.pettycash._modal_request_topup', [
            'period'             => $period,
            'fundSourcesGrouped' => $fundSourcesGrouped,
            'branchId'           => $branchId,
            'activeBranch'       => $activeBranch,
        ])
    @endif
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
        // Simpan kategori source asal di dataset badge supaya verb preview
        // di form transfer bisa hitung "Akan dicatat sebagai: Deposit/Tarik/Transfer".
        badge.dataset.fromCategory = category;
        badge.dataset.fromName = name;

        // Form Transfer: set from_source, filter dropdown destination, reset amount
        document.getElementById('transferFromSourceId').value = id;
        // Danger zone: populate source id untuk form "Hapus Data Sumber Dana Ini".
        const destroyIdInput = document.getElementById('pcSourceDestroyId');
        if (destroyIdInput) destroyIdInput.value = id;
        document.getElementById('transferMaxHint').textContent = fmt(balance);
        const sel = document.getElementById('transferToSelect');
        sel.value = '';
        // Hide source itself + hide Cash dest untuk semua source non-Cash
        // (Bank → pakai tab Tarik Tunai; E-Wallet → tidak ada tarik tunai).
        // Pakai kombinasi hidden + display:none supaya konsisten lintas browser.
        Array.from(sel.options).forEach(function (opt) {
            const isCashDest = (opt.dataset.category || '') === 'cash';
            const hideAsCash = !isCash && isCashDest;
            const shouldHide = (opt.value === id) || hideAsCash;
            opt.hidden = shouldHide;
            opt.disabled = shouldHide;
            opt.style.display = shouldHide ? 'none' : '';
        });
        // Sembunyikan optgroup yang semua opsinya hidden — beberapa browser
        // tetap render label optgroup kosong, biar bersih.
        Array.from(sel.querySelectorAll('optgroup')).forEach(function (og) {
            const visible = Array.from(og.children).some(function (o) {
                return !o.hidden;
            });
            og.hidden = !visible;
            og.style.display = visible ? '' : 'none';
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
                : '<i class="fa fa-right-left"></i> Mutasi';
        }
        if (transferSubmit) {
            transferSubmit.textContent = isCash ? 'Simpan Deposit' : 'Simpan Mutasi';
        }
        // Label dropdown tujuan:
        //   - isCash    → "Setor ke" (setor saldo Cash ke wallet lain)
        //   - non-Cash  → "Pindah ke"
        if (transferToLabel) {
            transferToLabel.textContent = isCash ? 'Setor ke' : 'Pindah ke';
        }
        // Hint banner di atas form Transfer — narasi & warna ikut konteks source.
        // Cash → varian "Deposit" (warna brown); non-Cash → "Mutasi" (warna biru).
        const hintBox  = document.getElementById('transferTabHint');
        const hintText = document.getElementById('transferTabHintText');
        if (hintBox && hintText) {
            hintBox.classList.toggle('pc-tab-hint--deposit',  isCash);
            hintBox.classList.toggle('pc-tab-hint--transfer', !isCash);
            hintText.textContent = isCash
                ? 'Setor saldo dari Cash ke Bank / E-Wallet. Total saldo periode tetap.'
                : 'Pindahkan saldo antar wallet. Total saldo periode tetap.';
        }

        // Tab tengah punya 2 varian dengan FUNGSI BERBEDA:
        //   - external    → "TARIK TUNAI" (untuk Bank/E-Wallet)
        //                   Cairkan saldo Bank/E-Wallet jadi cash. Submit ke
        //                   endpoint transfer (from=this source, to=Cash).
        //   - from-source → "SETOR CASH" (khusus Cash)
        //                   Catat uang cash langsung masuk. Submit ke endpoint
        //                   topup dengan is_topup=0 → saldo Cash naik tapi
        //                   tidak masuk Total Top Up.
        const variantExternal   = transferModal.querySelector('[data-pc-topup-variant="external"]');
        const variantFromSource = transferModal.querySelector('[data-pc-topup-variant="from-source"]');
        if (variantExternal && variantFromSource) {
            variantExternal.hidden   = isCash;
            variantFromSource.hidden = !isCash;
        }
        const topupTabBtn = transferModal.querySelector('[data-tab-target="topup"]');
        if (topupTabBtn) {
            topupTabBtn.innerHTML = isCash
                ? '<i class="fa fa-piggy-bank"></i> Setor Cash'
                : '<i class="fa fa-piggy-bank"></i> Tarik Tunai';
        }

        if (isCash) {
            // SETOR CASH form: lock fund_source_id ke Cash yg dipilih, reset amount.
            const setorIdInp = document.getElementById('setorCashFundSourceId');
            const setorAmtVis = document.querySelector('[data-target="setorCashAmount"]');
            const setorAmtHid = document.getElementById('setorCashAmount');
            const setorSubmit = document.getElementById('setorCashSubmitBtn');
            if (setorIdInp)  setorIdInp.value  = id;
            if (setorAmtVis) { setorAmtVis.value = ''; setorAmtVis.dataset.maxAmount = ''; }
            if (setorAmtHid) setorAmtHid.value = '';
            if (setorSubmit) setorSubmit.disabled = false;
        } else {
            // TARIK TUNAI form: from = source ini (Bank/E-Wallet), to = Cash.
            // Cap nominal = saldo source. Submit tampil kalau Cash source ada.
            const cashId = transferModal.dataset.pcCashSourceId || '';
            const tarikFromInp = document.getElementById('tarikTunaiFromSourceId');
            const tarikToInp   = document.getElementById('tarikTunaiToSourceId');
            const tarikAmtVis  = document.querySelector('[data-target="tarikTunaiAmount"]');
            const tarikAmtHid  = document.getElementById('tarikTunaiAmount');
            const tarikMaxHint = document.getElementById('tarikTunaiMaxHint');
            const tarikSubmit  = document.getElementById('tarikTunaiSubmitBtn');
            const tarikFromNm  = document.getElementById('tarikTunaiVerbFromName');
            if (tarikFromInp) tarikFromInp.value = id;
            if (tarikToInp)   tarikToInp.value   = cashId;
            if (tarikAmtVis) {
                tarikAmtVis.value = '';
                tarikAmtVis.dataset.maxAmount = String(balance);
            }
            if (tarikAmtHid)  tarikAmtHid.value = '';
            if (tarikMaxHint) tarikMaxHint.textContent = fmt(balance);
            // Update nama source di panel "Akan dicatat sebagai"
            if (tarikFromNm) tarikFromNm.textContent = name;
            if (tarikSubmit) {
                tarikSubmit.disabled = !cashId || balance < 0.005;
                tarikSubmit.title = !cashId
                    ? 'Sumber Cash tidak tersedia — hubungi admin'
                    : (balance < 0.005 ? 'Saldo sumber kosong' : '');
            }
        }

        // Pre-set isBank/isEwallet untuk inline edit nomor rek di preview header.
        const acctValue = card.dataset.sourceAccount || '';
        const isBank    = category === 'bank';
        const isEwallet = category === 'ewallet';

        // From-source preview: tampilkan nomor + copy button kalau ada.
        // Kalau no rek/HP belum diisi & source = Bank/E-Wallet, tampilkan
        // inline add form supaya user bisa lengkapi tanpa lewat form top up.
        const fromAcctWrap   = document.getElementById('transferFromAccount');
        const fromAcctLabel  = document.getElementById('transferFromAccountLabel');
        const fromAcctValEl  = document.getElementById('transferFromAccountValue');
        const fromAcctCopy   = document.getElementById('transferFromAccountCopy');
        const fromAcctAdd    = document.getElementById('transferFromAccountAdd');
        const fromAcctAddBtn = document.getElementById('transferFromAccountAddBtn');
        const fromAcctAddLbl = document.getElementById('transferFromAccountAddLabel');
        const fromAcctAddForm  = document.getElementById('transferFromAccountAddForm');
        const fromAcctAddInput = document.getElementById('transferFromAccountAddInput');
        const fromAcctAddError = document.getElementById('transferFromAccountAddError');
        if (fromAcctWrap && fromAcctLabel && fromAcctValEl && fromAcctCopy) {
            if (acctValue && (isBank || isEwallet)) {
                fromAcctWrap.hidden = false;
                fromAcctLabel.textContent = isBank ? 'No. Rek' : 'No. HP';
                fromAcctValEl.textContent = acctValue;
                fromAcctCopy.dataset.pcCopy = acctValue;
                if (fromAcctAdd) fromAcctAdd.hidden = true;
            } else if (!acctValue && (isBank || isEwallet)) {
                fromAcctWrap.hidden = true;
                if (fromAcctAdd && fromAcctAddBtn && fromAcctAddLbl && fromAcctAddForm && fromAcctAddInput) {
                    fromAcctAdd.hidden = false;
                    fromAcctAddLbl.textContent = isBank ? 'No. Rek' : 'No. HP';
                    fromAcctAddInput.placeholder = isBank ? 'Mis: 1234567890' : 'Mis: 08123456789';
                    fromAcctAddInput.value = '';
                    fromAcctAddForm.hidden = true;
                    fromAcctAddBtn.hidden = false;
                    if (fromAcctAddError) { fromAcctAddError.hidden = true; fromAcctAddError.textContent = ''; }
                    // Simpan source id untuk submit handler
                    fromAcctAdd.dataset.sourceId = id;
                }
            } else {
                fromAcctWrap.hidden = true;
                if (fromAcctAdd) fromAcctAdd.hidden = true;
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
        //   - Transfer / Pengeluaran → butuh saldo > 0 (disabled kalau kosong)
        //   - Tab tengah ('topup'):
        //       • Klik kartu Cash    → "Setor Cash" (tidak butuh saldo)
        //       • Klik kartu Bank    → "Tarik Tunai" (butuh saldo > 0 +
        //                              Cash source tersedia)
        //       • Klik kartu E-Wallet → tab HIDDEN (tidak ada Tarik Tunai
        //                              dari E-Wallet)
        const cashIdForModal = transferModal.dataset.pcCashSourceId || '';
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
                if (isCash) {
                    // Setor Cash: selalu enabled, tidak butuh saldo
                } else if (category === 'bank') {
                    // Tarik Tunai dari Bank: butuh saldo > 0 + Cash source ada
                    if (!hasSaldo) {
                        disabled = true;
                        reason = 'Tidak bisa tarik tunai — saldo kosong';
                    } else if (!cashIdForModal) {
                        disabled = true;
                        reason = 'Sumber Cash tidak tersedia di cabang ini';
                    }
                } else {
                    // E-Wallet / kategori lain → sembunyikan tab tengah
                    hidden = true;
                }
            }
            btn.disabled = disabled;
            btn.classList.toggle('is-disabled', disabled);
            btn.classList.toggle('is-hidden', hidden);
            btn.style.display = hidden ? 'none' : '';
            btn.title = reason;
        });

        // Pilih default tab pertama yang available.
        // Priority:
        //   - Punya saldo → Transfer dulu
        //   - Saldo 0 + Cash → Setor Cash (tab tengah)
        //   - Saldo 0 + non-Cash → Transfer / Expense (semua disabled, fallback)
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
    attachAmountCap('[data-target="tarikTunaiAmount"]',    'tarikTunaiAmount');

    // Lightbox preview untuk icon attachment di tabel transaksi.
    // Klik [data-pc-attach-url] → tampilkan file dalam modal:
    //   - Gambar (jpg/png/webp) → <img>
    //   - PDF → <iframe>
    // Close via tombol X / klik backdrop / tombol Escape.
    (function () {
        const lb       = document.getElementById('pcAttachLightbox');
        const body     = document.getElementById('pcAttachBody');
        const dlBtn    = document.getElementById('pcAttachDownload');
        const tabBtn   = document.getElementById('pcAttachOpenTab');
        if (!lb || !body) return;

        const IMG_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        function open(url, ext) {
            ext = (ext || '').toLowerCase();
            const isImg = IMG_EXTS.indexOf(ext) !== -1;
            body.innerHTML = isImg
                ? '<img src="' + url + '" alt="bukti pengeluaran" class="pc-lightbox__img">'
                : '<iframe src="' + url + '" class="pc-lightbox__iframe" frameborder="0"></iframe>';
            if (dlBtn)  dlBtn.href  = url;
            if (tabBtn) tabBtn.href = url;
            lb.hidden = false;
            document.body.style.overflow = 'hidden';
        }
        function close() {
            lb.hidden = true;
            body.innerHTML = '';
            document.body.style.overflow = '';
        }

        // Delegasi klik untuk tombol attachment (di tabel — bisa banyak)
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-pc-attach-url]');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                open(btn.dataset.pcAttachUrl, btn.dataset.pcAttachExt);
                return;
            }
            if (e.target.closest('[data-pc-lightbox-close]')) {
                e.preventDefault();
                close();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !lb.hidden) close();
        });
    })();

    // Upload bukti (pengeluaran, top up, transfer): preview file yg dipilih,
    // format size, tombol remove untuk reset input. Validasi tipe & size di
    // server-side juga. Looping semua `.pc-attach-zone` di halaman supaya
    // expense + top up + transfer dapat handler sendiri-sendiri.
    (function () {
        function formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1024 / 1024).toFixed(2) + ' MB';
        }

        document.querySelectorAll('.pc-attach-zone').forEach(function (zone) {
            const input     = zone.querySelector('.pc-attach-input');
            const label     = zone.querySelector('.pc-attach-label');
            const preview   = zone.querySelector('.pc-attach-preview');
            const thumb     = zone.querySelector('.pc-attach-preview__thumb');
            const nameEl    = zone.querySelector('.pc-attach-preview__name');
            const sizeEl    = zone.querySelector('.pc-attach-preview__size');
            const removeBtn = zone.querySelector('.pc-attach-preview__remove');
            if (!input || !label || !preview) return;

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (!file) return;
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ukuran file melebihi 5 MB. Pilih file lain.');
                    input.value = '';
                    return;
                }
                if (nameEl) nameEl.textContent = file.name;
                if (sizeEl) sizeEl.textContent = formatSize(file.size);
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        thumb.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
                    };
                    reader.readAsDataURL(file);
                } else {
                    thumb.innerHTML = '<i class="fa fa-file-pdf"></i>';
                }
                label.hidden = true;
                preview.hidden = false;
            });

            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    input.value = '';
                    preview.hidden = true;
                    label.hidden = false;
                    thumb.innerHTML = '<i class="fa fa-file"></i>';
                });
            }
        });
    })();

    // Setor Cash form: enable submit kalau nominal > 0. Tidak ada cap karena
    // input adalah jumlah cash fisik yang masuk (bebas, bukan dari sumber lain).
    (function () {
        const form   = document.getElementById('setorCashForm');
        if (!form) return;
        const amtVis = document.querySelector('[data-target="setorCashAmount"]');
        const amtHid = document.getElementById('setorCashAmount');
        const submit = document.getElementById('setorCashSubmitBtn');
        if (!amtVis || !amtHid || !submit) return;
        function sync() {
            const v = parseFloat(amtHid.value || '0');
            submit.disabled = !(v > 0);
        }
        amtVis.addEventListener('input', sync);
        amtVis.addEventListener('change', sync);
        sync();
    })();

    // Edit Pengeluaran (modal-edit-expense): klik tombol pensil di table
    // row → prefill form dari data-trx-* attributes & set form action.
    // Window 24 jam sudah di-enforce di server-side; tombol cuma muncul
    // kalau row masih dalam window.
    (function () {
        const modal       = document.getElementById('pcModalEditExpense');
        const form        = document.getElementById('pcEditExpenseForm');
        if (!modal || !form) return;
        const dateLabel   = modal.querySelector('#pcEditTrxDate');
        const walletLabel = modal.querySelector('#pcEditWallet');
        const categorySel = modal.querySelector('#pcEditCategory');
        const amountVis   = modal.querySelector('#pcEditAmountVisible');
        const amountHid   = modal.querySelector('#pcEditAmount');
        const maxHint     = modal.querySelector('#pcEditAmountMaxHint');
        const descInput   = modal.querySelector('#pcEditDescription');
        const actionTpl   = form.getAttribute('data-action-tpl') || '';

        // Format Rp dengan thousand separator.
        function fmt(n) {
            return Number(n || 0).toLocaleString('id-ID');
        }

        // Lookup balance wallet berdasarkan source-id (dari kartu wallet
        // di atas — pc-source-card punya data-source-id & data-source-balance).
        function lookupWalletBalance(sourceId) {
            if (!sourceId) return 0;
            const card = document.querySelector(
                '.pc-source-card[data-source-id="' + sourceId + '"]'
            );
            return card ? parseFloat(card.dataset.sourceBalance || '0') : 0;
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-pc-edit-trx]');
            if (!btn) return;
            e.preventDefault();

            const trxId         = btn.dataset.trxId || '';
            const trxDate       = btn.dataset.trxDate || '';
            const trxAmount     = parseFloat(btn.dataset.trxAmount || '0');
            const trxDesc       = btn.dataset.trxDescription || '';
            const trxCategory   = btn.dataset.trxCategory || '';
            const trxSourceName = btn.dataset.trxSourceName || '—';
            const trxSourceId   = btn.dataset.trxSourceId || '';

            // Set form action: replace __ID__ placeholder dengan id transaksi.
            form.action = actionTpl.replace('__ID__', trxId);

            // Prefill display fields.
            dateLabel.textContent   = trxDate;
            walletLabel.textContent = trxSourceName;
            categorySel.value       = trxCategory;
            descInput.value         = trxDesc;
            amountVis.value         = fmt(trxAmount);
            amountHid.value         = String(trxAmount);

            // Hitung max amount = saldo wallet sekarang + nominal asli
            // (karena nominal asli sudah "termakan" di saldo saat ini, jadi
            // wallet sebenarnya bisa nampung sampai max ini).
            const walletBalance = lookupWalletBalance(trxSourceId);
            const maxAmount     = walletBalance + trxAmount;
            if (maxHint) maxHint.textContent = fmt(maxAmount);

            // Buka modal (konvensi project: .pc-modal.is-open → display:flex).
            modal.classList.add('is-open');
        });
    })();

    // Panel "Akan dicatat sebagai: TARIK TUNAI" di tab Tarik Tunai —
    // klik header untuk collapse/expand. Default open saat modal dibuka.
    (function () {
        const previewEl = document.getElementById('tarikTunaiVerbPreview');
        if (!previewEl) return;
        const headEl = previewEl.querySelector('.pc-verb-preview__head');
        if (!headEl) return;
        headEl.addEventListener('click', function () {
            const willOpen = !previewEl.classList.contains('is-open');
            previewEl.classList.toggle('is-open', willOpen);
            headEl.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
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

    // Sub-tabs di card tabel transaksi: split Top Up/Pengeluaran vs Transfer.
    // Klik tombol [data-trx-tab=name] → set [data-active-trx-tab] di card +
    // toggle visibility pane [data-trx-content].
    (function () {
        const card = document.querySelector('.pc-trx-card');
        if (!card) return;
        const tabs  = card.querySelectorAll('[data-trx-tab]');
        const panes = card.querySelectorAll('[data-trx-content]');
        tabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const target = btn.dataset.trxTab;
                card.dataset.activeTrxTab = target;
                tabs.forEach(function (b) {
                    const active = b.dataset.trxTab === target;
                    b.classList.toggle('is-active', active);
                    b.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panes.forEach(function (p) {
                    p.hidden = (p.dataset.trxContent !== target);
                });
            });
        });
    })();

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

    // ─── Inline add / edit nomor rek/HP di preview modal ───
    // Mode "add"  : muncul saat card Bank/E-Wallet diklik & no rek kosong.
    // Mode "edit" : tombol pen di sebelah copy untuk ubah nomor existing.
    // Input dipaksa numeric (strip non-digit on-the-fly).
    // AJAX ke pettycash.account.save → update DOM + card.dataset supaya
    // klik berikutnya langsung tampil nomor (tanpa reload halaman).
    (function () {
        const addWrap   = document.getElementById('transferFromAccountAdd');
        const addBtn    = document.getElementById('transferFromAccountAddBtn');
        const addForm   = document.getElementById('transferFromAccountAddForm');
        const addInput  = document.getElementById('transferFromAccountAddInput');
        const saveBtn   = document.getElementById('transferFromAccountSaveBtn');
        const cancelBtn = document.getElementById('transferFromAccountCancelBtn');
        const errorEl   = document.getElementById('transferFromAccountAddError');
        const showWrap  = document.getElementById('transferFromAccount');
        const editBtn   = document.getElementById('transferFromAccountEdit');
        const valEl     = document.getElementById('transferFromAccountValue');
        if (!addWrap || !addBtn || !addForm || !addInput || !saveBtn || !cancelBtn) return;

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf     = csrfMeta ? csrfMeta.getAttribute('content') : '';

        const periodInp = document.querySelector('[data-tab-content="transfer"] input[name="period_id"]');
        const kindInp   = document.querySelector('[data-tab-content="transfer"] input[name="tab"]');
        const branchInp = document.querySelector('[data-tab-content="transfer"] input[name="branch"]');

        // State: 'add' (nomor kosong) vs 'edit' (nomor sudah ada → ubah)
        let mode = 'add';

        function showError(msg) {
            if (!errorEl) return;
            errorEl.textContent = msg;
            errorEl.hidden = false;
        }
        function clearError() {
            if (!errorEl) return;
            errorEl.hidden = true;
            errorEl.textContent = '';
        }

        // Strip non-digit saat user mengetik / paste — hanya angka yang masuk.
        addInput.addEventListener('input', function () {
            const cleaned = addInput.value.replace(/\D+/g, '');
            if (cleaned !== addInput.value) addInput.value = cleaned;
        });

        function openForm(prefill) {
            clearError();
            addWrap.hidden = false;
            addBtn.hidden = true;
            addForm.hidden = false;
            addInput.value = prefill || '';
            addInput.focus();
            addInput.select();
        }
        function closeForm() {
            clearError();
            addInput.value = '';
            addForm.hidden = true;
            if (mode === 'add') {
                addBtn.hidden = false;
            } else {
                // Edit: balikin tampilan nomor existing
                addWrap.hidden = true;
                if (showWrap) showWrap.hidden = false;
            }
        }

        addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            mode = 'add';
            openForm('');
        });

        if (editBtn) {
            editBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                mode = 'edit';
                const current = valEl ? (valEl.textContent || '').trim() : '';
                // Set source id dari card yang sedang aktif
                const activeCard = document.querySelector('[data-pc-source-card].is-active')
                    || document.querySelector('[data-pc-source-card][data-source-id="'
                        + (document.getElementById('transferFromSourceId')?.value || '') + '"]');
                if (activeCard) addWrap.dataset.sourceId = activeCard.dataset.sourceId || '';
                if (showWrap) showWrap.hidden = true;
                openForm(current);
            });
        }

        cancelBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeForm();
        });

        addInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  { e.preventDefault(); saveBtn.click(); }
            if (e.key === 'Escape') { e.preventDefault(); cancelBtn.click(); }
        });

        saveBtn.addEventListener('click', function (e) {
            e.preventDefault();
            clearError();
            const value = (addInput.value || '').trim();
            if (!value) { showError('Nomor tidak boleh kosong.'); return; }
            if (!/^[0-9]+$/.test(value)) { showError('Nomor hanya boleh berisi angka.'); return; }

            const sourceId = addWrap.dataset.sourceId
                || document.getElementById('transferFromSourceId')?.value
                || '';
            const periodId = periodInp ? periodInp.value : '';
            const tab      = kindInp ? kindInp.value : 'petty_cash';
            if (!sourceId || !periodId) { showError('Data tidak lengkap, coba tutup & buka lagi modal.'); return; }

            const body = new FormData();
            body.append('_token', csrf);
            body.append('period_id', periodId);
            body.append('fund_source_id', sourceId);
            body.append('account_number', value);
            body.append('tab', tab);
            if (branchInp && branchInp.value) body.append('branch', branchInp.value);

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

            fetch("{{ route('pettycash.account.save') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: body,
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; }); })
            .then(function (res) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa fa-check"></i>';
                if (!res.ok || !res.body || !res.body.ok) {
                    // 422 Laravel validation: ambil pesan dari errors.account_number
                    let msg = (res.body && res.body.message) || 'Gagal menyimpan. Coba lagi.';
                    if (res.body && res.body.errors && res.body.errors.account_number) {
                        msg = res.body.errors.account_number[0];
                    }
                    showError(msg);
                    return;
                }
                // Sukses: pindahkan UI ke mode "tampil nomor + copy + edit"
                const acctValue = res.body.account_number;
                const label     = res.body.label || 'No. Rek';
                const lblEl     = document.getElementById('transferFromAccountLabel');
                const copyEl    = document.getElementById('transferFromAccountCopy');
                if (showWrap && lblEl && valEl && copyEl) {
                    lblEl.textContent = label;
                    valEl.textContent = acctValue;
                    copyEl.dataset.pcCopy = acctValue;
                    showWrap.hidden = false;
                }
                addWrap.hidden = true;
                addForm.hidden = true;
                addBtn.hidden = false;
                // Sync ke kartu sumber dana di halaman supaya klik berikutnya
                // langsung tampil nomor (tanpa reload).
                const card = document.querySelector('[data-pc-source-card][data-source-id="' + sourceId + '"]');
                if (card) card.dataset.sourceAccount = acctValue;
            })
            .catch(function () {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa fa-check"></i>';
                showError('Network error. Coba lagi.');
            });
        });
    })();
})();
</script>
@endsection
