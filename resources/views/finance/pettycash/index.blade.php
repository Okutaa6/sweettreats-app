@extends('layouts.app')

@section('content')
@php
    $rupiah = function ($v) { return 'Rp ' . number_format((float) $v, 0, ',', '.'); };
@endphp
<div class="container-fluid">

    {{-- ─── HEADER ─── --}}
    <div class="pc-page-header pc-page-header--v2 fpc-page-header">
        <div class="pc-page-header__main">
            <div class="pc-page-icon">
                <i class="fa fa-chart-bar"></i>
            </div>
            <div class="pc-page-header__heading">
                <h4 class="pc-page-title">Monitoring Petty Cash</h4>
                <div class="pc-page-meta">
                    <span class="pc-page-meta__item">
                        <i class="fa fa-calendar"></i> {{ $monthName }} {{ $year }}
                    </span>
                    <span class="pc-page-meta__divider"></span>
                    <span class="pc-page-meta__item">
                        <i class="fa fa-building"></i> {{ $branches->count() }} cabang
                    </span>
                    <span class="pc-page-meta__divider"></span>
                    <span class="pc-page-meta__item" title="Aktivitas terakhir tercatat dari seluruh cabang">
                        <i class="fa fa-database"></i> Data per
                        <strong>{{ $cutoffAt->translatedFormat('d M Y, H:i') }}</strong>
                    </span>
                    <span class="pc-page-meta__divider"></span>
                    <span class="pc-page-meta__item" title="Halaman dimuat pada">
                        <i class="fa fa-clock"></i> Dimuat {{ $generatedAt->translatedFormat('H:i') }} WIB
                    </span>
                    <span class="pc-page-meta__divider"></span>
                    <span class="fpc-live-pill"><span class="fpc-live-dot"></span> Live</span>
                </div>
            </div>
        </div>
        <div class="fpc-header-actions">
            {{-- Bulan & tahun picker — submit GET dengan ?year=&month= --}}
            @php
                $nowYear  = (int) date('Y');
                $yearFrom = min($year, $nowYear) - 2;
                $yearTo   = max($year, $nowYear) + 1;
            @endphp
            <form method="GET" action="{{ route('finance.pettycash') }}" class="fpc-month-picker">
                <select name="month" onchange="this.form.submit();" class="fpc-month-select" aria-label="Pilih bulan">
                    @foreach($bulanLabel as $m => $label)
                        <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="year" onchange="this.form.submit();" class="fpc-month-select fpc-year-select" aria-label="Pilih tahun">
                    @for($y = $yearTo; $y >= $yearFrom; $y--)
                        <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </form>
            <button type="button" class="fpc-action-btn" onclick="window.print();">
                <i class="fa fa-file-export"></i> Export
            </button>
            <a href="{{ route('finance.pettycash', ['year' => $year, 'month' => $month]) }}"
               class="fpc-action-btn fpc-action-btn--primary">
                <i class="fa fa-sync"></i> Refresh
            </a>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── SUMMARY CARDS (cross-branch) ─── --}}
    @include('finance.pettycash._summary_cards', [
        'rupiah'                => $rupiah,
        'totalOpening'          => $totalOpening,
        'totalSaldo'            => $totalSaldo,
        'totalCashIn'           => $totalCashIn,
        'totalCashOut'          => $totalCashOut,
        'netFlow'               => $netFlow,
        'prevTotalSaldo'        => $prevTotalSaldo,
        'saldoMoMDelta'         => $saldoMoMDelta,
        'saldoMoMPct'           => $saldoMoMPct,
        'prevMonthName'         => $prevMonthName,
        'pendingCount'          => $pendingRequests->count(),
        'distribution'          => $distribution,
        'distributionRestCount' => $distributionRestCount,
        'distributionRestPct'   => $distributionRestPct,
        'idleCount'             => $idleCount,
        'branchesWithCashOut'   => $branchesWithCashOut,
        'branches'              => $branches,
    ])

    {{-- ─── PENDING QUEUE (Approval workflow) ─── --}}
    @include('finance.pettycash._pending_queue', [
        'rupiah'          => $rupiah,
        'pendingRequests' => $pendingRequests,
    ])

    {{-- ─── BRANCH GRID (per-cabang breakdown) ─── --}}
    @include('finance.pettycash._branch_grid', [
        'rupiah'          => $rupiah,
        'branchSummaries' => $branchSummaries,
        'year'            => $year,
        'month'           => $month,
    ])

    {{-- ─── AUDIT FEED (request yang sudah di-review) ─── --}}
    @include('finance.pettycash._audit_feed', [
        'rupiah'          => $rupiah,
        'recentReviewed'  => $recentReviewed,
    ])
</div>

{{-- Modals approve & reject — shared, prefill via JS dari tombol di pending queue --}}
@include('finance.pettycash._modal_approve', ['fundSources' => $fundSources])
@include('finance.pettycash._modal_reject')
@endsection

@section('css')
<style>
/* ─── Layout dashboard ─── */
.fpc-section { margin: 24px 0; }
.fpc-section__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
    padding: 0 2px;
}
.fpc-section__title {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: var(--brown);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.1px;
}
/* Icon dalam chip cream supaya title lebih ber-substansi */
.fpc-section__title i {
    color: var(--brown);
    font-size: 13px;
    width: 30px; height: 30px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #f5e6d3;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(70,21,0,0.06);
}
.fpc-section__sub {
    color: #64748b;
    font-size: 12.5px;
    font-weight: 500;
}
.fpc-section__sub--italic { font-style: italic; color: #8b6f47; }
.fpc-section__sub--link   { color: var(--brown); font-weight: 700; cursor: default; }
.fpc-section__sub--link::before { content: "▸  "; color: var(--brown-2); }

/* Header pill — quick indicator pengajuan menunggu di page header */
.fpc-header-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px;
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
    color: #fff;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
    box-shadow: 0 2px 4px rgba(245,158,11,0.3);
}
.fpc-header-pill i { font-size: 10px; }

/* Live indicator — green pulsing dot */
.fpc-live-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 2px 9px;
    background: #dcfce7;
    color: #15803d;
    border-radius: 999px;
    font-size: 11.5px; font-weight: 700;
}
.fpc-live-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: #16a34a;
    animation: fpc-live-pulse 1.6s ease-in-out infinite;
}
@keyframes fpc-live-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(22,163,74,0.5); }
    50%      { box-shadow: 0 0 0 6px rgba(22,163,74,0); }
}

/* Header right-side action buttons (Bulan picker, Export, Refresh) */
.fpc-header-actions {
    display: inline-flex; align-items: center; gap: 8px;
    flex-shrink: 0;
}
.fpc-month-picker { margin: 0; display: inline-flex; gap: 8px; }
.fpc-year-select { min-width: 88px; }
.fpc-month-select {
    appearance: none;
    -webkit-appearance: none;
    background: #fff url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23461500' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>") no-repeat right 12px center / 10px;
    border: 1px solid var(--divider-soft);
    color: var(--brown);
    font-size: 12.5px;
    font-weight: 700;
    padding: 8px 32px 8px 14px;
    border-radius: 999px;
    cursor: pointer;
    transition: border-color .15s, box-shadow .15s;
}
.fpc-month-select:hover  { border-color: var(--brown-2); }
.fpc-month-select:focus  { outline: none; border-color: var(--brown); box-shadow: 0 0 0 3px rgba(70,21,0,0.08); }

.fpc-action-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px;
    background: #fff;
    color: var(--brown);
    border: 1px solid var(--divider-soft);
    border-radius: 999px;
    font-size: 12.5px; font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
}
.fpc-action-btn:hover {
    border-color: var(--brown-2);
    color: var(--brown);
    text-decoration: none;
    transform: translateY(-1px);
}
.fpc-action-btn i { font-size: 11px; }
.fpc-action-btn--primary {
    background: var(--brown);
    color: #fff;
    border-color: var(--brown);
}
.fpc-action-btn--primary:hover { background: #5c1f00; color: #fff; }
</style>
@endsection

@section('js')
<script>
(function () {
    // Util: format rupiah saat user input nominal
    document.querySelectorAll('.pc-amount-input').forEach(function (el) {
        const hidden = document.getElementById(el.dataset.target);
        el.addEventListener('input', function () {
            const raw = parseInt(el.value.replace(/\D/g, ''), 10) || 0;
            el.value = raw === 0 ? '' : 'Rp ' + raw.toLocaleString('id-ID');
            if (hidden) hidden.value = String(raw);
        });
    });

    // Open modal helper
    document.querySelectorAll('[data-pc-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const m = document.getElementById(btn.dataset.pcModal);
            if (m) m.classList.add('is-open');
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

    // Tombol Approve di pending queue → prefill modal & open
    document.querySelectorAll('[data-fpc-approve]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const url = btn.dataset.fpcDetailUrl;
            const actionUrl = btn.dataset.fpcActionUrl;
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(function (req) {
                    const form    = document.getElementById('fpcApproveForm');
                    const amount  = document.getElementById('fpcApproveAmountVisible');
                    const hidden  = document.getElementById('fpcApproveAmount');
                    const fundSel = document.getElementById('fpcApproveFundSource');
                    const note    = document.getElementById('fpcApproveNote');
                    const summary = document.getElementById('fpcApproveSummary');

                    form.action = actionUrl;
                    amount.value = 'Rp ' + Math.round(req.amount_requested).toLocaleString('id-ID');
                    hidden.value = String(Math.round(req.amount_requested));
                    if (fundSel) fundSel.value = req.fund_source_id;
                    if (note) note.value = '';
                    summary.innerHTML = '<strong>' + (req.branch_name || '-') + '</strong> · ' +
                        (req.requester_name || '-') + '<br>' +
                        'Wallet awal: <strong>' + (req.fund_source_name || '-') + '</strong>' +
                        (req.description ? '<br>Catatan: ' + req.description : '');

                    document.getElementById('fpcModalApprove').classList.add('is-open');
                });
        });
    });

    // Tombol Reject di pending queue → prefill modal & open
    document.querySelectorAll('[data-fpc-reject]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const actionUrl = btn.dataset.fpcActionUrl;
            const branchName = btn.dataset.fpcBranchName;
            const requester  = btn.dataset.fpcRequester;
            const amount     = btn.dataset.fpcAmount;

            const form    = document.getElementById('fpcRejectForm');
            const summary = document.getElementById('fpcRejectSummary');
            const reason  = document.getElementById('fpcRejectReason');

            form.action = actionUrl;
            reason.value = '';
            summary.innerHTML = '<strong>' + branchName + '</strong> · ' + requester +
                '<br>Nominal: <strong>Rp ' + Number(amount).toLocaleString('id-ID') + '</strong>';

            document.getElementById('fpcModalReject').classList.add('is-open');
        });
    });

    // Copy nomor rekening — tombol [data-pc-copy] di pending queue card.
    function fpcFlashCopied(btn) {
        const original = btn.innerHTML;
        btn.classList.add('is-copied');
        btn.innerHTML = '<i class="fa fa-check"></i>';
        setTimeout(function () {
            btn.classList.remove('is-copied');
            btn.innerHTML = original;
        }, 1200);
    }
    function fpcCopyText(btn, text) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () { fpcFlashCopied(btn); });
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); fpcFlashCopied(btn); } catch (_) {}
            document.body.removeChild(ta);
        }
    }
    document.querySelectorAll('[data-pc-copy]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            fpcCopyText(btn, btn.dataset.pcCopy || '');
        });
    });

    // Attach-zone file preview (modal Approve "Bukti foto transfer")
    function fpcFormatSize(b) {
        if (b < 1024) return b + ' B';
        if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
        return (b / 1024 / 1024).toFixed(2) + ' MB';
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
                alert('Ukuran file melebihi 5 MB.');
                input.value = '';
                return;
            }
            if (nameEl) nameEl.textContent = file.name;
            if (sizeEl) sizeEl.textContent = fpcFormatSize(file.size);
            if (file.type.startsWith('image/')) {
                const r = new FileReader();
                r.onload = function (e) { thumb.innerHTML = '<img src="' + e.target.result + '" alt="preview">'; };
                r.readAsDataURL(file);
            } else {
                thumb.innerHTML = '<i class="fa fa-file-pdf"></i>';
            }
            label.hidden = true;
            preview.hidden = false;
        });
        if (removeBtn) removeBtn.addEventListener('click', function () {
            input.value = '';
            preview.hidden = true;
            label.hidden = false;
            if (thumb) thumb.innerHTML = '<i class="fa fa-file"></i>';
        });
    });
})();
</script>
@endsection
