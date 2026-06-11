{{--
    Petty Cash — filter row + action buttons.
    Variables: $startDate, $endDate, $monthStartStr, $monthEndStr,
               $search, $isReadOnly, $kind, $branches, $branchId.

    Range tanggal di-clamp ke 1 bulan periode (min/max = awal/akhir bulan).
    Submit otomatis saat tanggal berubah.

    Branch dropdown hanya muncul untuk admin (Super/General/Backup). Sales
    di-lock ke session('raw_branch_id') — $branches dilewatkan kosong.
--}}
@php
    $kind          = $kind ?? 'petty_cash';
    $branches      = $branches ?? collect();
    $branchId      = $branchId ?? null;
    $todayMonthStr = date('Y-m-01');
    $todayEndStr   = date('Y-m-t');
    $isCurrentRange = $startDate === $todayMonthStr && $endDate === $todayEndStr;
@endphp
<form method="GET" action="{{ route('pettycash') }}" class="pc-filter-row">
    <input type="hidden" name="tab" value="{{ $kind }}">

    @if($branches->isNotEmpty())
        <div class="pc-filter-field">
            <label>Cabang</label>
            <select name="branch" onchange="this.form.submit();">
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
    @elseif($branchId)
        {{-- Sales: branch lock-in via session. Tidak dropdown, tidak input —
             tapi kirim id-nya supaya kalau session expired URL tetap bawa. --}}
        <input type="hidden" name="branch" value="{{ $branchId }}">
    @endif

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
            <a href="{{ route('pettycash', array_filter(['tab' => $kind, 'branch' => $branches->isNotEmpty() ? $branchId : null, 'start_date' => $startDate, 'end_date' => $endDate])) }}"
               class="st-btn-outline-brown" title="Reset cari">
                <i class="fa fa-times"></i>
            </a>
        @endif
        <button type="submit" class="st-btn-brand" title="Terapkan filter">
            <i class="fa fa-search"></i>
        </button>
        @if(! $isCurrentRange)
            <a href="{{ route('pettycash', array_filter(['tab' => $kind, 'branch' => $branches->isNotEmpty() ? $branchId : null])) }}"
               class="st-btn-outline-brown" title="Kembali ke bulan ini">
                <i class="fa fa-calendar-day"></i> Bulan ini
            </a>
        @endif
    </div>
</form>
