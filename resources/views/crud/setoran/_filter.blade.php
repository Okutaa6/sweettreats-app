{{--
    Setoran — filter row + action buttons.
    Variables: $year, $month, $years, $bulanLabel, $search, $filterBranch,
               $branches, $sessionBranch, $isReadOnly.
--}}
<form method="GET" action="{{ route('setoran') }}" class="sd-filter-row">
    <div class="sd-filter-field">
        <label>Tahun</label>
        <select name="tahun" onchange="this.form.submit();">
            @foreach($years as $y)
                <option value="{{ $y }}" {{ (int) $year === (int) $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
    </div>

    <div class="sd-filter-field">
        <label>Bulan</label>
        <select name="bulan" onchange="this.form.submit();">
            @foreach($bulanLabel as $num => $label)
                <option value="{{ $num }}" {{ (int) $month === (int) $num ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    {{-- Cabang dropdown — hanya muncul untuk admin (Sales lock ke cabangnya sendiri) --}}
    @if(!$sessionBranch && $branches->count() > 0)
        <div class="sd-filter-field">
            <label>Cabang</label>
            <select name="branch" onchange="this.form.submit();">
                <option value="all" {{ $filterBranch === 'all' ? 'selected' : '' }}>Semua Cabang</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ $filterBranch == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="sd-filter-field sd-filter-field--grow">
        <label>Cari pasien / keterangan</label>
        <input type="text" name="q" value="{{ $search }}" placeholder="Nama pasien, no HP, atau keterangan...">
    </div>

    <div class="sd-filter-actions">
        @if($search)
            <a href="{{ route('setoran', ['tahun' => $year, 'bulan' => $month, 'branch' => $filterBranch]) }}"
               class="st-btn-outline-brown" title="Reset cari">
                <i class="fa fa-times"></i>
            </a>
        @endif
        <button type="submit" class="st-btn-brand" title="Terapkan filter">
            <i class="fa fa-search"></i>
        </button>
        @if((int) $year !== (int) date('Y') || (int) $month !== (int) date('n'))
            <a href="{{ route('setoran') }}" class="st-btn-outline-brown" title="Kembali ke bulan ini">
                <i class="fa fa-calendar-day"></i> Bulan ini
            </a>
        @endif
    </div>
</form>

@if(!$isReadOnly)
    <div class="sd-action-row">
        <button type="button" class="sd-btn-setor" data-sd-modal="sdModalSetor">
            <i class="fa fa-money-bill-transfer"></i> Setor Tunai
        </button>
        <button type="button" class="sd-btn-payment" data-sd-modal="sdModalPayment">
            <i class="fa fa-plus"></i> Pembayaran Pasien
        </button>
    </div>
@endif
