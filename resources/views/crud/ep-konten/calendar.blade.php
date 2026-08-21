@extends('layouts.app')

@section('css')
{{-- Font Awesome 6 (full) — supaya icon brands (Instagram/TikTok) + grid icons render --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@endsection

@php
    /**
     * Helper kecil: hash-based color generator untuk pillar tanpa color manual.
     * Stabil per nama, generate warna pastel dengan saturation rendah.
     * Defined di TOP supaya tersedia di semua loop blade di bawahnya.
     */
    if (!function_exists('pillarColorFromName')) {
        function pillarColorFromName(string $name): string {
            $hash = crc32($name);
            $hue  = $hash % 360;
            return "hsl({$hue}, 55%, 55%)";
        }
    }
@endphp

@section('content')
@php
    use Carbon\Carbon;
    $prevMonth = Carbon::create($year, $month, 1)->subMonth();
    $nextMonth = Carbon::create($year, $month, 1)->addMonth();
    $todayMonth = now()->month;
    $todayYear  = now()->year;
    $isCurrentMonth = ($month === $todayMonth && $year === $todayYear);
    $platformLabel = $platform === 'all' ? 'Semua Platform' : ($platforms[$platform] ?? $platform);

    $platformTabs = [
        'all'      => ['label' => 'Semua',      'icon' => 'fa-solid fa-th-large'],
        'ig_post'  => ['label' => 'IG Post',    'icon' => 'fa-brands fa-instagram'],
        'ig_story' => ['label' => 'IG Stories', 'icon' => 'fa-regular fa-play-circle'],
        'tiktok'   => ['label' => 'TikTok',     'icon' => 'fa-brands fa-tiktok'],
        'gp'       => ['label' => 'Branding GP','icon' => 'fa-solid fa-user-doctor'],
    ];

    $qs = function ($overrides = []) {
        return http_build_query(array_filter(array_merge(request()->all(), $overrides), fn($v) => $v !== null && $v !== ''));
    };
@endphp

<div class="container-fluid">

    {{-- ───────── HEADER ───────── --}}
    <div class="ep-cal-header">
        <div>
            <h4 class="page-title mb-1">Editorial Plan</h4>
            <div class="ep-cal-meta">
                <span class="ep-cal-month-pill">
                    <i class="fa fa-calendar-day"></i> {{ $monthLabel }}
                </span>
                <span class="ep-cal-meta-sep">·</span>
                <span class="ep-cal-meta-text"><strong>{{ $monthTotal }}</strong> konten bulan ini</span>
                <span class="ep-cal-meta-sep">·</span>
                <span class="ep-cal-meta-text">Filter: <strong>{{ $platformLabel }}</strong></span>
            </div>
        </div>
        <div class="ep-cal-actions">
            <a href="{{ route('epkonten') }}?{{ $qs(['month' => $todayMonth, 'year' => $todayYear]) }}"
               class="ep-cal-nav-btn {{ $isCurrentMonth ? 'is-disabled' : '' }}"
               title="Hari ini">
                <i class="fa fa-calendar-check"></i> Hari Ini
            </a>
            <a href="{{ route('epkonten') }}?{{ $qs(['month' => $prevMonth->month, 'year' => $prevMonth->year]) }}"
               class="ep-cal-nav-btn ep-cal-nav-arrow" title="Bulan sebelumnya">
                <i class="fa fa-chevron-left"></i>
            </a>
            <a href="{{ route('epkonten') }}?{{ $qs(['month' => $nextMonth->month, 'year' => $nextMonth->year]) }}"
               class="ep-cal-nav-btn ep-cal-nav-arrow" title="Bulan berikutnya">
                <i class="fa fa-chevron-right"></i>
            </a>
            <button type="button" class="st-btn-brand" onclick="openQuickAdd('{{ now()->format('Y-m-d') }}')">
                <i class="fa fa-plus"></i> Tambah
            </button>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ───────── PLATFORM TABS + FILTERS ───────── --}}
    @php
        $activeFilterCount = (int)!!$filterStatus + (int)!!$filterPillar + (int)!!$filterTipe;
        $kindOptions = [
            'all'     => ['label' => 'Semua',     'icon' => 'fa-layer-group'],
            'regular' => ['label' => 'Regular',   'icon' => 'fa-file-alt'],
            // Ikon dipisah dari label: label dirender ter-escape, ikon SVG statis
            // dirender mentah. Jangan gabung keduanya jadi satu string.
            'khusus'  => ['label' => 'Khusus', 'icon' => null, 'svg' => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="vertical-align:-.15em"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r=".9" fill="currentColor"/></svg>'],
        ];
    @endphp
    <div class="ep-cal-toolbar">
        <div style="display:flex; flex-direction:column; gap:6px;">
            <div class="ep-cal-tabs">
                @foreach ($platformTabs as $key => $tab)
                    @php $isActive = $platform === $key; @endphp
                    <a href="{{ route('epkonten') }}?{{ $qs(['platform' => $key]) }}"
                       class="ep-cal-tab {{ $isActive ? 'is-active' : '' }}">
                        <i class="{{ $tab['icon'] }}"></i>
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </div>
            {{-- Kind chips: Regular vs Khusus --}}
            <div class="ep-cal-kind-chips">
                @foreach ($kindOptions as $key => $opt)
                    @php $isKindActive = ($filterKind ?? 'all') === $key; @endphp
                    <a href="{{ route('epkonten') }}?{{ $qs(['kind' => $key]) }}"
                       class="ep-cal-kind-chip {{ $isKindActive ? 'is-active' : '' }} {{ $key === 'khusus' ? 'is-khusus' : '' }}">
                        @if($opt['icon'])<i class="fa-solid {{ $opt['icon'] }}"></i>@endif
                        @if($opt['svg'] ?? false){!! $opt['svg'] !!}@endif
                        {{ $opt['label'] }}
                    </a>
                @endforeach
            </div>
        </div>

        <form method="GET" class="ep-cal-filter-row" id="calendarFilterForm">
            <input type="hidden" name="month"    value="{{ $month }}">
            <input type="hidden" name="year"     value="{{ $year }}">
            <input type="hidden" name="platform" value="{{ $platform }}">

            <div class="ep-search-wrap">
                <i class="fa fa-search ep-search-icon"></i>
                <input type="text" name="search" class="ep-search-input"
                       placeholder="Cari konten..." value="{{ $filterSearch }}"
                       onchange="this.form.submit()">
            </div>

            <button type="button" class="ep-filter-trigger" onclick="toggleFilterPanel()">
                <i class="fa fa-sliders-h"></i> Filter
                @if ($activeFilterCount > 0)
                    <span class="ep-filter-badge">{{ $activeFilterCount }}</span>
                @endif
            </button>

            {{-- Filter dropdown panel (hidden by default) --}}
            <div class="ep-filter-panel" id="filterPanel">
                <div class="ep-filter-row">
                    <label>Status</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $key => $opt)
                            <option value="{{ $key }}" {{ $filterStatus == $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ep-filter-row">
                    <label>Pillar</label>
                    <select name="pillar_id" onchange="this.form.submit()">
                        <option value="">Semua pillar</option>
                        @foreach ($pillars as $p)
                            <option value="{{ $p->id }}" {{ $filterPillar == $p->id ? 'selected' : '' }}>{{ $p->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ep-filter-row">
                    <label>Tipe</label>
                    <select name="tipe_konten" onchange="this.form.submit()">
                        <option value="">Semua tipe</option>
                        @foreach ($tipes as $key => $label)
                            <option value="{{ $key }}" {{ $filterTipe == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($activeFilterCount > 0 || $filterSearch)
                    <a href="{{ route('epkonten') }}?platform={{ $platform }}&month={{ $month }}&year={{ $year }}"
                       class="ep-filter-reset-btn">
                        <i class="fa fa-undo"></i> Reset semua
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- ───────── CALENDAR GRID ───────── --}}
    <div class="ep-cal-wrap">
        <div class="ep-cal-grid">
            {{-- Header row: Sun..Sat --}}
            @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $day)
                <div class="ep-cal-dayname {{ in_array($i, [0, 6]) ? 'is-weekend' : '' }}">{{ $day }}</div>
            @endforeach

            @foreach ($weeks as $week)
                @foreach ($week as $cell)
                    @php
                        $cellClasses = [
                            'ep-cal-cell',
                            $cell->in_month  ? 'is-in-month' : 'is-out-month',
                            $cell->is_today  ? 'is-today'    : '',
                            $cell->is_weekend ? 'is-weekend' : '',
                        ];
                    @endphp
                    <div class="{{ trim(implode(' ', $cellClasses)) }}"
                         data-date="{{ $cell->date->format('Y-m-d') }}"
                         onclick="cellAddClick(event, '{{ $cell->date->format('Y-m-d') }}')">
                        <div class="ep-cal-cell-head">
                            <span class="ep-cal-day-num">{{ $cell->date->day }}</span>
                            @if ($cell->date->day === 1)
                                <span class="ep-cal-day-mlabel">{{ $cell->date->translatedFormat('M') }}</span>
                            @endif
                            @if ($cell->kontens->isNotEmpty())
                                <span class="ep-cal-day-count">{{ $cell->kontens->count() }}</span>
                            @endif
                        </div>

                        <div class="ep-cal-cell-body">
                            @foreach ($cell->kontens as $k)
                                @php
                                    $sCfg  = $statusColors[$k->status] ?? $statusColors['draft'];
                                    $tCfg  = $k->tipe_konten ? ($tipeColors[$k->tipe_konten] ?? null) : null;

                                    // Pilih platform "primer" buat warna card (prioritas: ig_post → ig_story → tiktok → gp)
                                    $kPlatforms = $k->platforms ?? [];
                                    $primaryPlatform = null;
                                    foreach (['ig_post', 'ig_story', 'tiktok', 'gp'] as $pref) {
                                        if (in_array($pref, $kPlatforms, true)) { $primaryPlatform = $pref; break; }
                                    }
                                    $platformClass = $primaryPlatform ? 'ep-cal-card--' . str_replace('_', '-', $primaryPlatform) : '';
                                @endphp
                                <a href="{{ $k->is_breakdown ? route('epbreakdown.edit', $k->id) : route('epkonten.edit', $k->id) }}"
                                   class="ep-cal-card {{ $k->is_breakdown ? 'is-breakdown' : '' }} {{ $platformClass }}"
                                   onclick="event.stopPropagation()"
                                   style="border-left-color:{{ $sCfg['dot'] }};">
                                    <div class="ep-cal-card-title">
                                        @if($k->is_breakdown)
                                            <span class="ep-cal-khusus-badge" title="Konten khusus / breakdown"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="vertical-align:-.15em"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r=".9" fill="currentColor"/></svg></span>
                                        @endif
                                        {{ $k->judul }}
                                    </div>
                                    <div class="ep-cal-card-meta">
                                        <span class="ep-cal-status" style="background:{{ $sCfg['bg'] }}; color:{{ $sCfg['text'] }};">
                                            <span class="ep-cal-status-dot" style="background:{{ $sCfg['dot'] }};"></span>
                                            {{ $statuses[$k->status]['label'] ?? $k->status }}
                                        </span>
                                    </div>
                                    @if ($k->pillars->isNotEmpty() || $tCfg)
                                        <div class="ep-cal-card-tags">
                                            @foreach ($k->pillars->take(3) as $p)
                                                @php $pColor = $p->color ?: pillarColorFromName($p->nama); @endphp
                                                <span class="ep-cal-tag" style="background:{{ $pColor }}; color:#fff;">{{ $p->nama }}</span>
                                            @endforeach
                                            @if ($tCfg)
                                                <span class="ep-cal-tag" style="background:{{ $tCfg['bg'] }}; color:{{ $tCfg['text'] }}; border:1px solid {{ $tCfg['border'] }};">
                                                    {{ $tipes[$k->tipe_konten] }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>

                        <button type="button" class="ep-cal-add-btn"
                                onclick="event.stopPropagation(); openQuickAdd('{{ $cell->date->format('Y-m-d') }}')"
                                title="Tambah konten di {{ $cell->date->format('d M Y') }}">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
</div>

{{-- ───────── QUICK ADD MODAL ───────── --}}
<div class="modal fade" id="quickAddModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="quickAddForm" autocomplete="off">
            @csrf
            <div class="modal-content" style="border-radius:14px; border:1px solid #c4ebe9;">
                <div class="modal-header" style="background:linear-gradient(135deg,#f4faf9,#e6f5f4); border-radius:14px 14px 0 0; border-bottom:1px solid #c4ebe9;">
                    <h5 class="modal-title" style="color:#0e6e6b; font-weight:800;">
                        <i class="fa fa-pen-fancy mr-2"></i> Konten Baru
                        <span id="quickAddDateLabel" style="font-size:0.75em; font-weight:600; color:#0a4f4d; margin-left:6px;"></span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="tanggal_upload" id="quickAddDate">

                    <div class="form-group">
                        <label style="font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#0a4f4d;">Judul Konten</label>
                        <input type="text" name="judul" class="form-control" required
                               placeholder="Mis. INTERAKSI Sweet Surprise 5.5" autofocus>
                    </div>

                    <div class="form-group">
                        <label style="font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#0a4f4d;">Platform</label>
                        <div class="ep-quick-pills" id="quickAddPlatforms">
                            @foreach ($platforms as $key => $label)
                                @php $pc = $platformColors[$key] ?? null; @endphp
                                <label class="ep-quick-pill" style="--pill-bg:{{ $pc['bg'] ?? '#e6f5f4' }}; --pill-text:{{ $pc['text'] ?? '#0a4f4d' }};">
                                    <input type="checkbox" name="platforms[]" value="{{ $key }}">
                                    <span><i class="{{ $pc['icon'] ?? 'fa-solid fa-circle' }}"></i> {{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#0a4f4d;">Tipe Konten</label>
                        <div class="ep-quick-pills" id="quickAddTipes">
                            @foreach ($tipes as $key => $label)
                                @php $tc = $tipeColors[$key]; @endphp
                                <label class="ep-quick-pill ep-quick-pill-radio" style="--pill-bg:{{ $tc['bg'] }}; --pill-text:{{ $tc['text'] }};">
                                    <input type="radio" name="tipe_konten" value="{{ $key }}">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label style="font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#0a4f4d;">Status</label>
                            <select name="status" class="form-control" required>
                                @foreach ($statuses as $key => $opt)
                                    <option value="{{ $key }}" {{ $key === 'draft' ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label style="font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#0a4f4d;">Prioritas</label>
                            <select name="prioritas" class="form-control" required>
                                @foreach ($prios as $key => $opt)
                                    <option value="{{ $key }}" {{ $key === 'normal' ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label style="font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#0a4f4d;">Konten Pillar</label>

                        {{-- Selected pillars (chips) + trigger button --}}
                        <div class="ep-pillar-trigger" onclick="togglePillarPanel()">
                            <div class="ep-pillar-selected" id="pillarSelectedDisplay">
                                <span class="ep-pillar-empty">Belum ada pillar dipilih</span>
                            </div>
                            <button type="button" class="ep-pillar-toggle-btn" id="pillarToggleBtn">
                                <i class="fa fa-chevron-down"></i>
                            </button>
                        </div>

                        {{-- Collapsible panel — hidden by default --}}
                        <div class="ep-pillar-panel" id="pillarPanel" style="display:none;">
                            <div class="ep-pillar-search-wrap">
                                <i class="fa fa-search ep-search-icon"></i>
                                <input type="text" class="ep-pillar-search" id="pillarSearch"
                                       placeholder="Cari pillar..." onkeyup="filterPillars(this.value)">
                            </div>
                            <div class="ep-quick-pills ep-pillar-list" id="quickAddPillars">
                                @foreach ($pillars as $p)
                                    @php $pColor = $p->color ?: '#13928e'; @endphp
                                    <label class="ep-quick-pill" style="--pill-bg:{{ $pColor }}1a; --pill-text:{{ $pColor }};"
                                           data-pillar-name="{{ strtolower($p->nama) }}">
                                        <input type="checkbox" name="pillar_ids[]" value="{{ $p->id }}"
                                               data-name="{{ $p->nama }}" data-color="{{ $pColor }}"
                                               onchange="updatePillarChips()">
                                        <span>{{ $p->nama }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #c4ebe9;">
                    <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                    <button type="submit" class="st-btn-brand" id="quickAddSubmit">
                        <i class="fa fa-save"></i> Simpan & Tutup
                    </button>
                    <button type="submit" class="st-btn-brand" id="quickAddSubmitAnother" style="background:#1aaaa5; border-color:#13928e;">
                        <i class="fa fa-plus"></i> Simpan & Tambah Lagi
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>


@endsection

@section('js')
<script>
const QUICK_ADD_URL = "{{ route('epkonten.quick-store') }}";
const CSRF_TOKEN    = "{{ csrf_token() }}";

let _saveAndAddAnother = false;

function openQuickAdd(dateStr) {
    const $modal = $('#quickAddModal');
    $('#quickAddDate').val(dateStr);
    $('#quickAddDateLabel').text(formatDateLabel(dateStr));
    // Reset form
    document.getElementById('quickAddForm').reset();
    $('#quickAddDate').val(dateStr);
    // Reset pillar chips display + close panel
    updatePillarChips();
    document.getElementById('pillarPanel').style.display = 'none';
    document.querySelector('.ep-pillar-trigger')?.classList.remove('is-open');
    document.getElementById('pillarSearch').value = '';
    document.querySelectorAll('#quickAddPillars label[data-pillar-name]').forEach(l => l.dataset.hidden = 'false');
    // If platform tab is active, pre-check it
    const currentPlatform = "{{ $platform }}";
    if (currentPlatform && currentPlatform !== 'all') {
        $('#quickAddPlatforms input[value="' + currentPlatform + '"]').prop('checked', true);
    }
    $modal.modal('show');
    setTimeout(() => $modal.find('input[name="judul"]').focus(), 250);
}

function cellAddClick(e, dateStr) {
    // Only trigger if click was on cell itself (not on card or button)
    if (e.target.closest('.ep-cal-card') || e.target.closest('.ep-cal-add-btn')) return;
    openQuickAdd(dateStr);
}

function formatDateLabel(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    const opts = { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' };
    return '· ' + d.toLocaleDateString('id-ID', opts);
}

// Submit handler — split between "save & close" vs "save & add another"
$('#quickAddSubmit').on('click', function () { _saveAndAddAnother = false; });
$('#quickAddSubmitAnother').on('click', function () { _saveAndAddAnother = true; });

$('#quickAddForm').on('submit', function (e) {
    e.preventDefault();
    const $form = $(this);
    const formData = $form.serialize();
    const $submitBtns = $('#quickAddSubmit, #quickAddSubmitAnother').prop('disabled', true);

    $.ajax({
        url: QUICK_ADD_URL,
        method: 'POST',
        data: formData + '&_token=' + CSRF_TOKEN,
        success: function (res) {
            $submitBtns.prop('disabled', false);
            Swal.fire({
                title: 'Berhasil',
                text: res.message,
                type: 'success',
                timer: 1200,
                showConfirmButton: false,
            });
            if (_saveAndAddAnother) {
                // Reset judul + pillar tapi keep tanggal+platform+status+tipe untuk batch add
                $form.find('input[name="judul"]').val('').focus();
                $form.find('input[type="checkbox"][name^="pillar_ids"]').prop('checked', false);
                updatePillarChips();
            } else {
                $('#quickAddModal').modal('hide');
                setTimeout(() => window.location.reload(), 600);
            }
        },
        error: function (xhr) {
            $submitBtns.prop('disabled', false);
            let msg = 'Gagal simpan konten.';
            if (xhr.responseJSON?.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
            } else if (xhr.responseJSON?.message) {
                msg = xhr.responseJSON.message;
            }
            Swal.fire({ title: 'Gagal', text: msg, type: 'error' });
        }
    });
});

// ─────────────────────────────────────────────────────────────────
// Filter panel toggle
// ─────────────────────────────────────────────────────────────────
function toggleFilterPanel() {
    const panel  = document.getElementById('filterPanel');
    const trigger = document.querySelector('.ep-filter-trigger');
    panel.classList.toggle('is-open');
    trigger.classList.toggle('is-open');
}
// Close filter panel when click outside
document.addEventListener('click', function (e) {
    const panel  = document.getElementById('filterPanel');
    const trigger = document.querySelector('.ep-filter-trigger');
    if (!panel || !trigger) return;
    if (!panel.contains(e.target) && !trigger.contains(e.target)) {
        panel.classList.remove('is-open');
        trigger.classList.remove('is-open');
    }
});

// ─────────────────────────────────────────────────────────────────
// Pillar collapsible + chips + search
// ─────────────────────────────────────────────────────────────────
function togglePillarPanel() {
    const panel   = document.getElementById('pillarPanel');
    const trigger = document.querySelector('.ep-pillar-trigger');
    const isOpen  = panel.style.display === 'block';
    panel.style.display = isOpen ? 'none' : 'block';
    trigger.classList.toggle('is-open', !isOpen);
    if (!isOpen) {
        document.getElementById('pillarSearch').focus();
    }
}

function updatePillarChips() {
    const display = document.getElementById('pillarSelectedDisplay');
    const checked = document.querySelectorAll('#quickAddPillars input[type="checkbox"]:checked');

    if (checked.length === 0) {
        display.innerHTML = '<span class="ep-pillar-empty">Belum ada pillar dipilih</span>';
        return;
    }

    let html = '';
    checked.forEach(inp => {
        const name  = inp.dataset.name;
        const color = inp.dataset.color || '#13928e';
        const id    = inp.value;
        html += `<span class="ep-pillar-chip" style="background:${color};">
            ${name}
            <span class="ep-pillar-chip-x" onclick="removePillar(event, '${id}')"><i class="fa fa-times"></i></span>
        </span>`;
    });
    display.innerHTML = html;
}

function removePillar(e, id) {
    e.stopPropagation();
    const inp = document.querySelector(`#quickAddPillars input[value="${id}"]`);
    if (inp) {
        inp.checked = false;
        updatePillarChips();
    }
}

function filterPillars(term) {
    const t = term.trim().toLowerCase();
    document.querySelectorAll('#quickAddPillars label[data-pillar-name]').forEach(label => {
        const name = label.dataset.pillarName;
        label.dataset.hidden = (t && !name.includes(t)) ? 'true' : 'false';
    });
}
</script>
@endsection
