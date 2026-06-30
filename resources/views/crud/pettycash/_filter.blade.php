{{--
    Petty Cash — filter row + action buttons.
    Variables: $startDate, $endDate, $monthStartStr, $monthEndStr,
               $search, $category, $categoryOptions, $isReadOnly, $kind,
               $branches, $branchId, $fundSources, $fundSourceId.

    Range tanggal di-clamp ke 1 bulan periode (min/max = awal/akhir bulan).
    Submit otomatis saat tanggal berubah.

    Branch dropdown hanya muncul untuk admin (Super/General/Backup). Sales
    di-lock ke session('raw_branch_id') — $branches dilewatkan kosong.
--}}
@php
    $kind            = $kind ?? 'petty_cash';
    $branches        = $branches ?? collect();
    $branchId        = $branchId ?? null;
    $category        = $category ?? '';
    $categoryOptions = $categoryOptions ?? [];
    $fundSources     = $fundSources ?? collect();
    $fundSourceId    = (int) ($fundSourceId ?? 0);
    $year            = $year  ?? (int) date('Y');
    $month           = $month ?? (int) date('n');
    $bulanLabel      = $bulanLabel ?? [
        1 => 'Januari',  2 => 'Februari', 3 => 'Maret',
        4 => 'April',    5 => 'Mei',      6 => 'Juni',
        7 => 'Juli',     8 => 'Agustus',  9 => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $todayMonthStr   = date('Y-m-01');
    $todayEndStr     = date('Y-m-t');
    $isCurrentRange  = $startDate === $todayMonthStr && $endDate === $todayEndStr;

    // Filter bulan: hanya tampilkan bulan yang sudah lewat / bulan berjalan.
    // Bulan depan disembunyikan (belum ada periode-nya).
    $currentYear  = (int) date('Y');
    $currentMonth = (int) date('n');
    $monthOptions = collect($bulanLabel)->filter(function ($label, $m) use ($year, $currentYear, $currentMonth) {
        if ($year < $currentYear)  return true;             // tahun lampau → semua bulan
        if ($year > $currentYear)  return false;            // tahun depan → tidak ada (safety)
        return $m <= $currentMonth;                         // tahun ini → s/d bulan berjalan
    })->all();
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

    {{-- Tahun ikut terbawa supaya pindah bulan tidak reset ke tahun ini. --}}
    <input type="hidden" name="tahun" value="{{ $year }}">

    <div class="pc-filter-field">
        <label>Bulan</label>
        <select name="bulan" onchange="
            this.form.querySelector('[name=start_date]').value = '';
            this.form.querySelector('[name=end_date]').value   = '';
            this.form.submit();
        ">
            @foreach($monthOptions as $m => $label)
                <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="pc-filter-field">
        <label>Tanggal mulai</label>
        <input type="date" name="start_date"
               value="{{ $startDate }}"
               min="{{ $monthStartStr }}"
               max="{{ $monthEndStr }}"
               onchange="this.form.submit();">
    </div>

    <div class="pc-filter-field">
        <label>Tanggal akhir</label>
        <input type="date" name="end_date"
               value="{{ $endDate }}"
               min="{{ $monthStartStr }}"
               max="{{ $monthEndStr }}"
               onchange="this.form.submit();">
    </div>

    @if($fundSources->isNotEmpty())
        <div class="pc-filter-field">
            <label>Wallet</label>
            <select name="fund_source_id" onchange="this.form.submit();">
                <option value="">Semua wallet</option>
                @foreach($fundSources as $fs)
                    <option value="{{ $fs->id }}" {{ $fundSourceId === (int) $fs->id ? 'selected' : '' }}>{{ $fs->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if(! empty($categoryOptions))
        <div class="pc-filter-field">
            <label>Kategori</label>
            <select name="category" onchange="this.form.submit();">
                <option value="">Semua kategori</option>
                @foreach($categoryOptions as $catKey => $catLabel)
                    <option value="{{ $catKey }}" {{ $category === $catKey ? 'selected' : '' }}>{{ $catLabel }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="pc-filter-field pc-filter-field--grow">
        <label>Cari keterangan</label>
        <input type="text" name="q" value="{{ $search }}" placeholder="Cari keterangan...">
    </div>

    <div class="pc-filter-actions">
        @if($search)
            <a href="{{ route('pettycash', array_filter(['tab' => $kind, 'branch' => $branches->isNotEmpty() ? $branchId : null, 'start_date' => $startDate, 'end_date' => $endDate, 'category' => $category, 'fund_source_id' => $fundSourceId ?: null])) }}"
               class="st-btn-outline-brown" title="Reset cari">
                <i class="fa fa-times"></i>
            </a>
        @endif
        <button type="submit" class="st-btn-brand" title="Terapkan filter">
            <i class="fa fa-search"></i>
        </button>
        @if(! $isCurrentRange)
            <a href="{{ route('pettycash', array_filter(['tab' => $kind, 'branch' => $branches->isNotEmpty() ? $branchId : null, 'category' => $category, 'fund_source_id' => $fundSourceId ?: null])) }}"
               class="st-btn-outline-brown" title="Kembali ke bulan ini">
                <i class="fa fa-calendar-day"></i> Bulan ini
            </a>
        @endif
    </div>
</form>
