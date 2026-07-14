@extends('layouts.app')

@section('content')
@php
    $monthName = $bulanLabel[$month] ?? '-';
    $rupiah = function ($v) { return 'Rp ' . number_format((float) $v, 0, ',', '.'); };
@endphp
<div class="container-fluid">

    {{-- ─── HEADER ─── --}}
    <div class="sd-page-header">
        <div>
            <h4 class="page-title mb-1">Setoran</h4>
            <div class="sd-page-sub">
                Periode {{ $isCurrentMonth ? 'berjalan' : 'lampau' }}
                · <strong>{{ $monthName }} {{ $year }}</strong>
                @if($activeBranch)
                    · cabang <strong>{{ $activeBranch->name }}</strong>
                @endif
            </div>
        </div>
        <div>
            @if($period->is_closed)
                <span class="sd-badge sd-badge--closed">
                    <i class="fa fa-lock"></i> Periode ditutup · read-only
                </span>
            @elseif($isCurrentMonth)
                <span class="sd-badge sd-badge--active">
                    <i class="fa fa-calendar-day"></i> Bulan berjalan
                </span>
            @else
                <span class="sd-badge sd-badge--past">
                    <i class="fa fa-history"></i> Bulan lampau · read-only
                </span>
            @endif
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── SUMMARY CARDS ─── --}}
    @include('crud.setoran._summary_cards', ['summary' => $summary, 'rupiah' => $rupiah])

    {{-- ─── FILTER + ACTIONS ─── --}}
    @include('crud.setoran._filter', [
        'year'         => $year,
        'month'        => $month,
        'years'        => $years,
        'bulanLabel'   => $bulanLabel,
        'search'       => $search,
        'filterBranch' => $filterBranch,
        'branches'     => $branches,
        'sessionBranch'=> $sessionBranch,
        'isReadOnly'   => $isReadOnly,
    ])

    {{-- ─── TABLE ─── --}}
    <div class="st-card mt-3 sd-table-card">
        <div class="st-card__body" style="padding:0;">
            <div class="st-table-wrap--scroll">
                <table class="st-table sd-table">
                    <thead>
                        <tr>
                            <th width="100">Tanggal</th>
                            <th width="200">Pasien</th>
                            <th>Keterangan</th>
                            @if(!$sessionBranch)
                                <th width="120">Cabang</th>
                            @endif
                            <th class="text-right" width="140">Cash in</th>
                            <th class="text-right" width="140">Cash out</th>
                            <th class="text-right" width="160">Saldo</th>
                            @if(!$isReadOnly)
                                <th width="60"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr class="{{ $r->is_setor ? 'sd-row-setor' : '' }}">
                                <td class="sd-date">{{ \Carbon\Carbon::parse($r->trx_date)->translatedFormat('d M') }}</td>
                                <td>
                                    @if($r->is_setor)
                                        <span class="sd-muted">—</span>
                                    @elseif($r->customer)
                                        <strong>{{ $r->customer->name }}</strong>
                                        @if($r->customer->phone)
                                            <small class="sd-meta">({{ $r->customer->phone }})</small>
                                        @endif
                                    @else
                                        <span class="sd-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($r->is_setor)
                                        <i class="fa-solid fa-money-bill-wave" style="color:#b45309;"></i>
                                        <strong>{{ $r->description ?: 'SETOR TUNAI' }}</strong>
                                        <span class="sd-pill-setor">setor</span>
                                    @else
                                        {{ $r->description ?: '—' }}
                                    @endif
                                </td>
                                @if(!$sessionBranch)
                                    <td>
                                        @if($r->branch)
                                            <span class="sd-branch-chip">{{ $r->branch->name }}</span>
                                        @else
                                            <span class="sd-muted">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="text-right sd-cash-in">
                                    {{ $r->cash_in > 0 ? number_format($r->cash_in, 0, ',', '.') : '—' }}
                                </td>
                                <td class="text-right sd-cash-out">
                                    {{ $r->cash_out > 0 ? number_format($r->cash_out, 0, ',', '.') : '—' }}
                                </td>
                                <td class="text-right sd-saldo-cell">{{ number_format($r->saldo, 0, ',', '.') }}</td>
                                @if(!$isReadOnly)
                                    <td>
                                        <form method="POST" action="{{ route('setoran.destroy', $r->id) }}"
                                              onsubmit="return confirm('Hapus transaksi ini? Tidak bisa di-undo.')" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sd-icon-btn" title="Hapus transaksi">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($sessionBranch ? 6 : 7) + ($isReadOnly ? 0 : 1) }}">
                                    <div class="sd-empty-state">
                                        <i class="fa fa-folder-open"></i>
                                        <div class="sd-empty-state__title">Belum ada transaksi bulan ini</div>
                                        <div class="sd-empty-state__desc">
                                            Klik <strong>+ Pembayaran Pasien</strong> untuk mencatat pembayaran cash dari pasien,
                                            atau <strong>Setor Tunai</strong> setelah saldo terkumpul.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($rows) > 0)
                        <tfoot>
                            <tr class="sd-row-total">
                                <td colspan="{{ $sessionBranch ? 3 : 4 }}"><strong>Total bulan ini</strong></td>
                                <td class="text-right sd-cash-in">{{ number_format($summary['total_in'], 0, ',', '.') }}</td>
                                <td class="text-right sd-cash-out">{{ number_format($summary['total_out'], 0, ',', '.') }}</td>
                                <td class="text-right sd-saldo-cell"><strong>{{ number_format($summary['closing'], 0, ',', '.') }}</strong></td>
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
    @include('crud.setoran.modal-payment', ['period' => $period])
    @include('crud.setoran.modal-setor',   ['period' => $period, 'summary' => $summary])
@endif
@endsection

@section('js')
<script>
(function () {
    // Format Rupiah otomatis di input nominal
    document.querySelectorAll('.sd-amount-input').forEach(function (el) {
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
    document.querySelectorAll('[data-sd-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.dataset.sdModal;
            const m = document.getElementById(id);
            if (m) m.classList.add('is-open');
        });
    });
    document.querySelectorAll('[data-sd-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { btn.closest('.sd-modal').classList.remove('is-open'); });
    });
    document.querySelectorAll('.sd-modal').forEach(function (m) {
        m.addEventListener('click', function (e) { if (e.target === m) m.classList.remove('is-open'); });
    });

})();

// ───────────── Select2 AJAX untuk dropdown pasien ─────────────
$(function () {
    if (typeof $.fn.select2 === 'undefined') return;

    $('#sdCustomerSelect').select2({
        dropdownParent: $('#sdModalPayment'),
        placeholder:    '- Cari Pasien -',
        allowClear:     true,
        minimumInputLength: 2,
        language: {
            inputTooShort: function () { return 'Ketik minimal 2 huruf...'; },
            noResults:     function () { return 'Pasien tidak ditemukan'; },
            searching:     function () { return 'Mencari...'; },
        },
        ajax: {
            url:      '{{ route('api.customer.search') }}',
            dataType: 'json',
            delay:    250,
            data:     function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data }; },
            cache: true,
        },
    });

    // Reset Select2 saat modal ditutup (klik backdrop)
    $('#sdModalPayment').on('click', function (e) {
        if (e.target === this) {
            $('#sdCustomerSelect').val(null).trigger('change');
        }
    });
});
</script>
@endsection
