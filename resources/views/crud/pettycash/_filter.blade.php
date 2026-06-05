{{--
    Petty Cash — filter row + action buttons.
    Variables: $startDate, $endDate, $monthStartStr, $monthEndStr,
               $search, $isReadOnly, $kind.

    Range tanggal di-clamp ke 1 bulan periode (min/max = awal/akhir bulan).
    Submit otomatis saat tanggal berubah.
--}}
@php
    $kind          = $kind ?? 'petty_cash';
    $todayMonthStr = date('Y-m-01');
    $todayEndStr   = date('Y-m-t');
    $isCurrentRange = $startDate === $todayMonthStr && $endDate === $todayEndStr;
@endphp
<form method="GET" action="{{ route('pettycash') }}" class="pc-filter-row">
    <input type="hidden" name="tab" value="{{ $kind }}">

    <div class="pc-filter-field">
        <label>Tanggal mulai</label>
        <input type="date" name="start_date"
               value="{{ $startDate }}"
               onchange="this.form.submit();">
    </div>

    <div class="pc-filter-field">
        <label>Tanggal akhir</label>
        <input type="date" name="end_date"
               value="{{ $endDate }}"
               onchange="this.form.submit();">
    </div>

    <div class="pc-filter-field pc-filter-field--grow">
        <label>Cari keterangan</label>
        <input type="text" name="q" value="{{ $search }}" placeholder="Cari keterangan...">
    </div>

    <div class="pc-filter-actions">
        @if($search)
            <a href="{{ route('pettycash', ['tab' => $kind, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
               class="st-btn-outline-brown" title="Reset cari">
                <i class="fa fa-times"></i>
            </a>
        @endif
        <button type="submit" class="st-btn-brand" title="Terapkan filter">
            <i class="fa fa-search"></i>
        </button>
        @if(! $isCurrentRange)
            <a href="{{ route('pettycash', ['tab' => $kind]) }}" class="st-btn-outline-brown" title="Kembali ke bulan ini">
                <i class="fa fa-calendar-day"></i> Bulan ini
            </a>
        @endif
    </div>
</form>
