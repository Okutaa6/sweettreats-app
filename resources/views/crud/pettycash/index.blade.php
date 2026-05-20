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
        <div>
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
        'summary' => $summary,
        'rupiah'  => $rupiah,
    ])

    {{-- ─── FILTER + ACTIONS ─── --}}
    @include('crud.pettycash._filter', [
        'year'       => $year,
        'month'      => $month,
        'years'      => $years,
        'bulanLabel' => $bulanLabel,
        'search'     => $search,
        'isReadOnly' => $isReadOnly,
        'kind'       => $kind,
    ])

    {{-- ─── TABLE ─── --}}
    <div class="st-card mt-3 pc-table-card">
        <div class="st-card__body" style="padding:0;">
            <div class="st-table-wrap--scroll">
                <table class="st-table pc-table">
                    <thead>
                        <tr>
                            <th width="120">Tanggal</th>
                            <th>Keterangan</th>
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
                            <tr class="{{ $r->is_topup ? 'pc-row-topup' : '' }}">
                                <td class="pc-date">{{ \Carbon\Carbon::parse($r->trx_date)->translatedFormat('d M') }}</td>
                                <td>
                                    @if($r->is_topup)
                                        <i class="fa-solid fa-arrow-down-up-across-line" style="color:#16a34a;"></i>
                                    @endif
                                    <strong>{{ $r->description }}</strong>
                                    @if($r->is_topup)
                                        <span class="pc-pill-topup">top up</span>
                                    @endif
                                </td>
                                <td class="text-right pc-cash-in">
                                    {{ $r->cash_in > 0 ? number_format($r->cash_in, 0, ',', '.') : '—' }}
                                </td>
                                <td class="text-right pc-cash-out">
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
                                <td colspan="{{ $isReadOnly ? 5 : 6 }}">
                                    <div class="pc-empty-state">
                                        <i class="fa fa-folder-open"></i>
                                        <div class="pc-empty-state__title">Belum ada transaksi bulan ini</div>
                                        <div class="pc-empty-state__desc">
                                            Saldo awal: <strong>{{ $rupiah($summary['opening']) }}</strong>.
                                            @if($summary['opening'] <= 0)
                                                Lakukan <strong>Top up saldo</strong> dulu untuk mulai mencatat pengeluaran.
                                            @else
                                                Klik <strong>+ Pengeluaran</strong> atau <strong>Top up saldo</strong> untuk mulai mencatat.
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($rows) > 0)
                        <tfoot>
                            <tr class="pc-row-total">
                                <td colspan="2"><strong>Total bulan ini</strong></td>
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
    @include('crud.pettycash.modal-expense', ['period' => $period, 'kind' => $kind])
    @include('crud.pettycash.modal-topup',   ['period' => $period, 'summary' => $summary, 'kind' => $kind])
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
})();
</script>
@endsection
