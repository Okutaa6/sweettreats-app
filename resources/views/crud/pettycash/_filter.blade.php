{{--
    Petty Cash — filter row + action buttons.
    Variables: $startDate, $endDate, $monthStartStr, $monthEndStr,
               $search, $categories (array), $categoryOptions, $isReadOnly, $kind,
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
    $categories      = $categories ?? [];
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
        {{-- Multi-select: bisa pilih beberapa kategori sekaligus. --}}
        <div class="pc-filter-field pc-catfilter" data-pc-catfilter>
            <label>Kategori</label>
            <button type="button" class="pc-catfilter__toggle" data-pc-catfilter-toggle>
                <span class="pc-catfilter__summary">
                    @if(empty($categories))
                        Semua kategori
                    @elseif(count($categories) === 1)
                        {{ $categoryOptions[$categories[0]] ?? $categories[0] }}
                    @else
                        {{ count($categories) }} kategori dipilih
                    @endif
                </span>
                <i class="fa fa-chevron-down"></i>
            </button>
            <div class="pc-catfilter__panel" data-pc-catfilter-panel hidden>
                @foreach($categoryOptions as $catKey => $catLabel)
                    <label class="pc-catfilter__opt">
                        <input type="checkbox" name="categories[]" value="{{ $catKey }}"
                               {{ in_array($catKey, $categories, true) ? 'checked' : '' }}>
                        <span>{{ $catLabel }}</span>
                    </label>
                @endforeach
                <div class="pc-catfilter__actions">
                    <button type="button" class="pc-catfilter__reset" data-pc-catfilter-reset>Reset</button>
                    <button type="submit" class="pc-catfilter__apply">Terapkan</button>
                </div>
            </div>
        </div>
    @endif

    <div class="pc-filter-field pc-filter-field--grow">
        <label>Cari keterangan</label>
        <input type="text" name="q" value="{{ $search }}" placeholder="Cari keterangan...">
    </div>

    <div class="pc-filter-actions">
        @if($search)
            <a href="{{ route('pettycash', array_filter(['tab' => $kind, 'branch' => $branches->isNotEmpty() ? $branchId : null, 'start_date' => $startDate, 'end_date' => $endDate, 'categories' => $categories, 'fund_source_id' => $fundSourceId ?: null])) }}"
               class="st-btn-outline-brown" title="Reset cari">
                <i class="fa fa-times"></i>
            </a>
        @endif
        <button type="submit" class="st-btn-brand" title="Terapkan filter">
            <i class="fa fa-search"></i>
        </button>
        @if(! $isCurrentRange)
            <a href="{{ route('pettycash', array_filter(['tab' => $kind, 'branch' => $branches->isNotEmpty() ? $branchId : null, 'categories' => $categories, 'fund_source_id' => $fundSourceId ?: null])) }}"
               class="st-btn-outline-brown" title="Kembali ke bulan ini">
                <i class="fa fa-calendar-day"></i> Bulan ini
            </a>
        @endif
    </div>
</form>

<style>
.pc-catfilter { position: relative; }
.pc-catfilter__toggle {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    width: 100%; min-width: 150px; padding: 7px 10px;
    background: #fff; border: 1px solid var(--divider-soft, #cbd5e1); border-radius: 8px;
    font-size: 13.5px; color: #1f2937; cursor: pointer; text-align: left;
}
.pc-catfilter__toggle:hover { border-color: var(--brown-2, #94a3b8); }
.pc-catfilter__toggle i { font-size: 11px; color: #6b7280; flex-shrink: 0; }
.pc-catfilter__summary { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pc-catfilter__panel {
    position: absolute; z-index: 30; top: calc(100% + 4px); left: 0; min-width: 200px;
    background: #fff; border: 1px solid var(--divider-soft, #e5e7eb); border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.12); padding: 8px; display: flex; flex-direction: column; gap: 2px;
}
.pc-catfilter__opt {
    display: flex; align-items: center; gap: 8px; padding: 7px 8px; border-radius: 6px;
    font-size: 13.5px; color: #374151; cursor: pointer; margin: 0;
}
.pc-catfilter__opt:hover { background: #f8fafc; }
.pc-catfilter__opt input { width: 15px; height: 15px; accent-color: var(--brown, #6b3f1d); cursor: pointer; }
.pc-catfilter__actions {
    display: flex; justify-content: space-between; gap: 8px;
    margin-top: 6px; padding-top: 8px; border-top: 1px solid var(--divider-soft, #eef2f7);
}
.pc-catfilter__reset, .pc-catfilter__apply {
    padding: 6px 14px; border-radius: 7px; font-size: 12.5px; font-weight: 700; cursor: pointer;
}
.pc-catfilter__reset { background: #fff; border: 1px solid #cbd5e1; color: #475569; }
.pc-catfilter__reset:hover { background: #f1f5f9; }
.pc-catfilter__apply { background: var(--brown, #6b3f1d); border: 1px solid var(--brown, #6b3f1d); color: #fff; }
.pc-catfilter__apply:hover { background: var(--brown-2, #4a2c14); }
</style>

<script>
(function () {
    document.querySelectorAll('[data-pc-catfilter]').forEach(function (root) {
        var toggle = root.querySelector('[data-pc-catfilter-toggle]');
        var panel  = root.querySelector('[data-pc-catfilter-panel]');
        var reset  = root.querySelector('[data-pc-catfilter-reset]');
        if (toggle && panel) {
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                panel.hidden = !panel.hidden;
            });
            document.addEventListener('click', function (e) {
                if (! root.contains(e.target)) panel.hidden = true;
            });
        }
        if (reset) {
            reset.addEventListener('click', function () {
                root.querySelectorAll('input[type=checkbox]').forEach(function (cb) { cb.checked = false; });
            });
        }
    });
})();
</script>
