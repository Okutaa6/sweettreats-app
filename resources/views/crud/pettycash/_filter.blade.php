{{--
    Petty Cash — filter row + action buttons.
    Variables: $year, $month, $years, $bulanLabel, $search, $isReadOnly, $kind.
--}}
@php $kind = $kind ?? 'petty_cash'; @endphp
<form method="GET" action="{{ route('pettycash') }}" class="pc-filter-row">
    <input type="hidden" name="tab" value="{{ $kind }}">
    <div class="pc-filter-field">
        <label>Tahun</label>
        <select name="tahun" onchange="this.form.submit();">
            @foreach($years as $y)
                <option value="{{ $y }}" {{ (int) $year === (int) $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
    </div>

    <div class="pc-filter-field">
        <label>Bulan</label>
        <select name="bulan" onchange="this.form.submit();">
            @foreach($bulanLabel as $num => $label)
                <option value="{{ $num }}" {{ (int) $month === (int) $num ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="pc-filter-field pc-filter-field--grow">
        <label>Cari keterangan</label>
        <input type="text" name="q" value="{{ $search }}" placeholder="Cari keterangan...">
    </div>

    <div class="pc-filter-actions">
        @if($search)
            <a href="{{ route('pettycash', ['tab' => $kind, 'tahun' => $year, 'bulan' => $month]) }}"
               class="st-btn-outline-brown" title="Reset cari">
                <i class="fa fa-times"></i>
            </a>
        @endif
        <button type="submit" class="st-btn-brand" title="Terapkan filter">
            <i class="fa fa-search"></i>
        </button>
        @if((int) $year !== (int) date('Y') || (int) $month !== (int) date('n'))
            <a href="{{ route('pettycash', ['tab' => $kind]) }}" class="st-btn-outline-brown" title="Kembali ke bulan ini">
                <i class="fa fa-calendar-day"></i> Bulan ini
            </a>
        @endif
    </div>
</form>

@if(!$isReadOnly)
    <div class="pc-action-row">
        <button type="button" class="pc-btn-topup" data-pc-modal="pcModalTopup">
            <i class="fa fa-arrow-down-up-across-line"></i> Top up saldo
        </button>
        <button type="button" class="pc-btn-expense" data-pc-modal="pcModalExpense">
            <i class="fa fa-plus"></i> Pengeluaran
        </button>
    </div>
@endif
