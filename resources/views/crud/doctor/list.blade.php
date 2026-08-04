@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/doctor-redesign.css') }}?v={{ @filemtime(public_path('assets/css/doctor-redesign.css')) ?: '1' }}">
@endsection

@section('content')
<div class="container-fluid">
    <div class="page-title-box"><h4 class="page-title">Doctor</h4></div>

    @include('layouts.notif')

    @php
        $hasFilter = request('spec') || request('status');
        $avatarColors = ['#8A3A12', '#A8481B', '#2F8E55', '#3C5FA8', '#B5701A', '#7A3415', '#5A6B2E', '#8E2419'];
        // Preserve state untuk link segment.
        $segBase = array_filter([
            'search' => $filterSearch ?: null,
            'spec'   => $filterSpec ?: null,
            'status' => $filterStatus ?: null,
        ]);
        $segUrl = fn ($s) => route('rawdoctor', $s === 'semua' ? $segBase : array_merge($segBase, ['seg' => $s]));
        $segDefs = ['semua' => 'Semua', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'spesialis' => 'Spesialis'];
    @endphp

    <div class="dx-wrap">

        {{-- ══════ Insight tiles ══════ --}}
        <div class="dx-tiles">
            <div class="dx-tile">
                <div class="dx-tile__head">
                    <div class="dx-tile__icon" style="background:#EDE9FE;color:#7C3AED;"><i class="fa fa-user-md"></i></div>
                    <div class="dx-tile__label">Total Dokter</div>
                </div>
                <div class="dx-tile__num">{{ number_format($tiles['total'], 0, ',', '.') }}</div>
                <div class="dx-tile__sub">terdaftar di grup</div>
            </div>
            <div class="dx-tile">
                <div class="dx-tile__head">
                    <div class="dx-tile__icon" style="background:#E3F4E8;color:#2F8E55;"><i class="fa fa-check-circle"></i></div>
                    <div class="dx-tile__label">Dokter Aktif</div>
                </div>
                <div class="dx-tile__num">{{ number_format($tiles['aktif'], 0, ',', '.') }}</div>
                <div class="dx-tile__sub">siap menerima reservasi</div>
            </div>
            <div class="dx-tile">
                <div class="dx-tile__head">
                    <div class="dx-tile__icon" style="background:#FBEFD9;color:#B5701A;"><i class="fa fa-exclamation-triangle"></i></div>
                    <div class="dx-tile__label">Cuti Minggu Ini</div>
                </div>
                <div class="dx-tile__num">{{ number_format($tiles['cuti'], 0, ',', '.') }}</div>
                <div class="dx-tile__sub">dokter terdampak</div>
            </div>
            <div class="dx-tile">
                <div class="dx-tile__head">
                    <div class="dx-tile__icon" style="background:#FBE7CF;color:#9A6614;"><i class="fa fa-calendar-alt"></i></div>
                    <div class="dx-tile__label">Total Reservasi</div>
                </div>
                <div class="dx-tile__num">{{ number_format($tiles['reservasi'], 0, ',', '.') }}</div>
                <div class="dx-tile__sub">sepanjang waktu</div>
            </div>
        </div>

        {{-- ══════ Control row: Tambah (admin) + search + Filter (mode Tabel) + toggle Tabel|Jadwal Semua ══════ --}}
        <div class="dx-controls">
            {{-- Tambah dokter = non-Sales (Admin/Admin General/Super Admin/Admin Backup).
                 Sama seperti edit profil, Sales read-only untuk data dokter (nama/foto).
                 Guard server-side ada di DoctorController::create/store. --}}
            @if(session('group_name') !== 'Sales')
                {{-- href = fallback halaman penuh kalau JS mati; JS intercept → buka drawer. --}}
                <a href="{{ route('rawdoctor.new') }}" class="dx-btn dx-btn--primary" id="dxAddBtn"><i class="fa fa-plus"></i> Tambah Dokter</a>
            @endif
            @if($view === 'tabel')
                <form method="GET" action="{{ route('rawdoctor') }}" class="dx-search">
                    @if(($seg ?? 'semua') !== 'semua')<input type="hidden" name="seg" value="{{ $seg }}">@endif
                    @if($filterSpec)<input type="hidden" name="spec" value="{{ $filterSpec }}">@endif
                    @if($filterStatus)<input type="hidden" name="status" value="{{ $filterStatus }}">@endif
                    <i class="fa fa-search"></i>
                    <input type="text" name="search" value="{{ $filterSearch }}" placeholder="Cari nama dokter…">
                </form>
                <button class="dx-btn {{ $hasFilter ? 'is-active' : '' }}" type="button" id="dxFilterToggle" data-toggle="collapse" data-target="#dxFilter" aria-expanded="{{ $hasFilter ? 'true' : 'false' }}">
                    <i class="fa fa-filter"></i> {{ $hasFilter ? 'Filter Aktif' : 'Filter' }}
                </button>
            @endif
            <div class="dx-toggle {{ $view === 'jadwal' ? 'dx-toggle--solo' : '' }}">
                <a href="{{ route('rawdoctor', $segBase) }}" class="dx-toggle__opt {{ $view === 'tabel' ? 'is-active' : '' }}"><i class="fa fa-list-ul"></i> Tabel</a>
                <a href="{{ route('rawdoctor', ['view' => 'jadwal']) }}" class="dx-toggle__opt {{ $view === 'jadwal' ? 'is-active' : '' }}"><i class="fa fa-table"></i> Jadwal Semua</a>
            </div>
        </div>

        {{-- ══════ Filter panel (spesialisasi + status) — mode Tabel ══════ --}}
        @if($view === 'tabel')
        <div class="collapse {{ $hasFilter ? 'show' : '' }}" id="dxFilter">
            <form method="GET" action="{{ route('rawdoctor') }}" class="dx-filter">
                <input type="hidden" name="search" value="{{ $filterSearch }}">
                @if(($seg ?? 'semua') !== 'semua')<input type="hidden" name="seg" value="{{ $seg }}">@endif
                <div class="dx-filter__grid">
                    <div>
                        <label class="dx-flabel">Spesialisasi</label>
                        <select class="dx-select" name="spec">
                            <option value="">Semua Spesialisasi</option>
                            @foreach($specOptions as $k => $label)
                                <option value="{{ $k }}" {{ $filterSpec === $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="dx-flabel">Status</label>
                        <select class="dx-select" name="status">
                            <option value="">Semua Status</option>
                            <option value="aktif" {{ $filterStatus === 'aktif' ? 'selected' : '' }}>Aktif</option>
                            <option value="nonaktif" {{ $filterStatus === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="dx-filter__foot">
                    <button type="submit" class="dx-btn" style="background:var(--dx-grad);color:#FFEAD6;border:none;"><i class="fa fa-check"></i> Terapkan</button>
                    <a href="{{ route('rawdoctor') }}" class="dx-btn"><i class="fa fa-times"></i> Reset</a>
                </div>
            </form>
        </div>
        @endif

        {{-- ══════ MODE: TABEL ══════ --}}
        @if($view === 'tabel')
        <div class="dx-wrap">
            <div class="dx-segs">
                <div class="dx-segs__tabs">
                    @foreach($segDefs as $k => $label)
                        <a href="{{ $segUrl($k) }}" class="dx-seg {{ ($seg ?? 'semua') === $k ? 'active' : '' }}">
                            {{ $label }} <span class="dx-seg__badge">{{ number_format($segCounts[$k] ?? 0, 0, ',', '.') }}</span>
                        </a>
                    @endforeach
                </div>
                <span class="dx-segs__count">Menampilkan <strong>{{ number_format($doctors->count(), 0, ',', '.') }}</strong> dokter</span>
            </div>

            <div class="dx-table-card">
                <div class="dx-table-wrap">
                    <table class="dx-table st-table--clickable">
                        <thead>
                            <tr>
                                <th style="width:48px;">No</th>
                                <th>Nama</th>
                                <th style="width:150px;">Status</th>
                                <th style="width:140px;">Jadwal</th>
                                <th style="width:120px;">Reservasi</th>
                                <th style="width:22px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($doctors as $i => $row)
                                @php
                                    $initial = mb_strtoupper(mb_substr(preg_replace('/^drg\.?\s*/i', '', trim($row->name)) ?: '?', 0, 1));
                                    $avColor = $avatarColors[crc32((string) $row->id) % count($avatarColors)];
                                @endphp
                                <tr data-href="{{ route('rawdoctor.edit', $row->id) }}" class="{{ $row->is_active ? '' : 'is-off' }}">
                                    <td class="dx-tnum dx-muted">{{ $i + 1 }}</td>
                                    <td>
                                        <div class="dx-name">
                                            <div class="dx-avatar" style="background:{{ $avColor }};">@if($row->photo)<img src="{{ $row->photo_url }}" alt="{{ $row->name }}">@else{{ $initial }}@endif</div>
                                            <div>
                                                <div class="dx-name__name">{{ $row->name }}</div>
                                                <span class="dx-spec" style="color:{{ $row->spec_color }};background:{{ $row->spec_bg }};">{{ $row->spec_label }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="dx-status {{ $row->is_active ? 'dx-status--on' : 'dx-status--off' }}">{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                    </td>
                                    <td><span class="dx-sched"><i class="fa fa-calendar-alt"></i> {{ $row->schedules_count }} jam kerja</span></td>
                                    <td><span class="dx-resv">{{ number_format($row->reservations_count, 0, ',', '.') }}</span></td>
                                    <td><i class="fa fa-chevron-right dx-chev"></i></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="dx-empty">Tidak ada dokter di segmen ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @else

        {{-- ══════ MODE: JADWAL SEMUA (master) — matriks dokter × hari ══════ --}}
        @php
            // mUrl: base view+cabang+minggu. mday hanya ikut kalau lagi mode drill
            // (biar ganti cabang saat drill tetap di hari yang sama).
            $mBase = ['view' => 'jadwal', 'mbranch' => $master['mbranch'], 'mweek' => $master['weekStart']];
            $mUrl  = fn ($extra = []) => route('rawdoctor', array_merge($mBase, $extra));
            $mDayExtra = $master['dayMode'] ? ['mday' => $master['mday']] : [];
        @endphp
        <div class="dxm">

            {{-- kontrol: toggle cabang + nav minggu / kembali --}}
            <div class="dxm-controls">
                <div class="dxm-branches">
                    @foreach($master['branches'] as $b)
                        <a href="{{ $mUrl(array_merge(['mbranch' => $b->id], $mDayExtra)) }}" class="dxm-branch {{ $b->id === $master['mbranch'] ? 'is-active' : '' }}">
                            <span class="dxm-branch__dot" style="background:{{ $master['dotMap'][$b->id] }};"></span>{{ $b->name }}
                        </a>
                    @endforeach
                </div>
                @if($master['dayMode'])
                    <a href="{{ $mUrl() }}" class="dxm-back"><i class="fa fa-chevron-left"></i> Kembali ke minggu</a>
                @else
                    <div class="dxm-weeknav">
                        <a href="{{ $mUrl(['mweek' => $master['prevWeek']]) }}" class="dxm-navbtn" title="Minggu sebelumnya">‹</a>
                        <span class="dxm-weeklabel">{{ $master['weekLabel'] }}</span>
                        <a href="{{ $mUrl(['mweek' => $master['nextWeek']]) }}" class="dxm-navbtn" title="Minggu berikutnya">›</a>
                        <a href="{{ $mUrl(['mweek' => $master['thisWeek']]) }}" class="dxm-thisweek">Minggu Ini</a>
                    </div>
                @endif
            </div>

            @if(!$master['dayMode'])

            {{-- matriks --}}
            <div class="dxm-card">
                <div class="dxm-info">
                    Jadwal <strong>{{ $master['branchName'] }}</strong> · <span>{{ number_format($master['rows']->count(), 0, ',', '.') }}</span> dokter praktik. Klik <strong>nama dokter</strong> untuk cuti &amp; khusus · klik <strong>sel</strong> untuk atur jam — keduanya membuka halaman detail.
                </div>
                @if($master['gapShow'])
                    <div class="dxm-gap">
                        <i class="fa fa-exclamation-triangle"></i>
                        <span>{{ $master['gapText'] }}</span>
                    </div>
                @endif
                <div class="dxm-scroll">
                    <div class="dxm-grid">
                        {{-- header hari --}}
                        <div class="dxm-head">
                            <div class="dxm-head__dr">DOKTER</div>
                            @foreach($master['days'] as $d)
                                <a href="{{ $mUrl(['mday' => $d['iso']]) }}" class="dxm-head__day {{ $d['isToday'] ? 'is-today' : '' }}" title="Lihat timeline per jam">
                                    <div class="dxm-head__short">{{ $d['short'] }}</div>
                                    <div class="dxm-head__num">{{ $d['num'] }}</div>
                                    <div class="dxm-head__badge {{ $d['status'] === 'kosong' ? 'dxm-head__badge--empty' : ($d['status'] === 'libur' ? 'dxm-head__badge--off' : '') }}">{{ $d['status'] === 'ada' ? $d['count'] . ' dr' : ($d['status'] === 'libur' ? 'Libur' : 'kosong') }}</div>
                                </a>
                            @endforeach
                        </div>
                        {{-- baris dokter --}}
                        @forelse($master['rows'] as $row)
                            @php
                                $rInit = mb_strtoupper(mb_substr(preg_replace('/^drg\.?\s*/i', '', trim($row->name)) ?: '?', 0, 1));
                                $rAv   = $avatarColors[crc32((string) $row->id) % count($avatarColors)];
                            @endphp
                            <div class="dxm-row">
                                <a href="{{ route('rawdoctor.edit', [$row->id, 'tab' => 'cuti']) }}" class="dxm-dr" title="Cuti & jadwal khusus {{ $row->name }}">
                                    <div class="dxm-dr__av" style="background:{{ $rAv }};">@if($row->photo)<img src="{{ $row->photo_url }}" alt="{{ $row->name }}">@else{{ $rInit }}@endif</div>
                                    <div class="dxm-dr__main">
                                        <div class="dxm-dr__name">{{ $row->name }}</div>
                                        <div class="dxm-dr__spec" style="color:{{ $row->spec_color }};"><span style="background:{{ $row->spec_color }};"></span>{{ $row->spec_label }}</div>
                                    </div>
                                </a>
                                @foreach($master['days'] as $d)
                                    @php $cell = $master['cells'][$row->id][$d['iso']]; @endphp
                                    <a href="{{ route('rawdoctor.edit', [$row->id, 'tab' => 'jadwal']) }}" class="dxm-cell {{ $d['isToday'] ? 'is-today' : '' }}">
                                        @if($cell['isCuti'])
                                            <div class="dxm-cuti">CUTI</div>
                                        @endif
                                        @foreach($cell['blocks'] as $blk)
                                            <div class="dxm-blk" style="background:{{ $row->spec_color }};">
                                                <div class="dxm-blk__time">{{ $blk['time'] }}</div>
                                                <div class="dxm-blk__util">
                                                    <span class="dxm-blk__track"><span style="width:{{ $blk['pct'] }}%;"></span></span>
                                                    <span class="dxm-blk__count">{{ $blk['fill'] }}/{{ $blk['cap'] }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                        @foreach($cell['khusus'] as $k)
                                            <div class="dxm-khusus">{{ $k['time'] }} ★</div>
                                        @endforeach
                                    </a>
                                @endforeach
                            </div>
                        @empty
                            <div class="dxm-emptyrow">Belum ada dokter dengan jadwal di {{ $master['branchName'] }}.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            @else
            {{-- ── timeline harian (drill dari klik header hari) ── --}}
            <div class="dxm-card">
                <div class="dxm-tlhead">
                    <div class="dxm-tlhead__t">{{ $master['dayLabel'] }}</div>
                    <div class="dxm-tlhead__s">Timeline per jam · {{ $master['branchName'] }} · klik <strong>nama</strong> untuk cuti &amp; khusus · klik <strong>bar</strong> untuk atur jam — keduanya membuka halaman detail.</div>
                </div>
                <div class="dxm-scroll">
                    <div class="dxm-tl">
                        <div class="dxm-tl__axis">
                            <div></div>
                            <div class="dxm-tl__marks">
                                @foreach($master['dayHourMarks'] as $hm)<span style="left:{{ $hm['pct'] }}%;">{{ $hm['label'] }}</span>@endforeach
                            </div>
                        </div>
                        @forelse($master['dayRows'] as $dr)
                            @php
                                $row  = $dr['row'];
                                $rInit = mb_strtoupper(mb_substr(preg_replace('/^drg\.?\s*/i', '', trim($row->name)) ?: '?', 0, 1));
                                $rAv   = $avatarColors[crc32((string) $row->id) % count($avatarColors)];
                            @endphp
                            <div class="dxm-tl__row">
                                <a href="{{ route('rawdoctor.edit', [$row->id, 'tab' => 'cuti']) }}" class="dxm-tl__dr" title="Cuti & jadwal khusus {{ $row->name }}">
                                    <div class="dxm-dr__av" style="background:{{ $rAv }};">@if($row->photo)<img src="{{ $row->photo_url }}" alt="{{ $row->name }}">@else{{ $rInit }}@endif</div>
                                    <div class="dxm-dr__name">{{ $row->name }}</div>
                                </a>
                                <a href="{{ route('rawdoctor.edit', [$row->id, 'tab' => 'jadwal']) }}" class="dxm-tl__track">
                                    @if($dr['isCuti'])<div class="dxm-tl__cuti">CUTI</div>@endif
                                    @foreach($dr['blocks'] as $b)
                                        <div class="dxm-tl__blk {{ $b['kind'] === 'khusus' ? 'is-khusus' : '' }}" style="left:{{ $b['left'] }}%; width:{{ $b['width'] }}%;{!! $b['kind'] === 'shift' ? 'background:' . $b['color'] . ';' : '' !!}">{{ $b['label'] }}</div>
                                    @endforeach
                                </a>
                            </div>
                        @empty
                            <div class="dxm-emptyrow">Tidak ada dokter praktik di {{ $master['branchName'] }} pada hari ini.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            @endif
        </div>
        @endif

    </div>
</div>

{{-- ════════ TAMBAH DOKTER DRAWER (slide dari kanan) — non-Sales ════════
     Reuse pola .dxj-drawer dari halaman edit (jadwal). Form statis, POST biasa
     ke rawdoctor.store; kalau validasi gagal → redirect balik ke list, drawer
     auto-open lagi dengan old('name') + pesan error. --}}
@if(session('group_name') !== 'Sales')
<div class="dxj-overlay" id="dxAddOverlay"></div>
<div class="dxj-drawer" id="dxAddDrawer" aria-hidden="true">
    <div class="dxj-drawer__head">
        <span class="dxj-drawer__title"><i class="fa fa-user-plus" style="margin-right:8px;"></i>Tambah Dokter</span>
        <button type="button" class="dxj-drawer__close" id="dxAddClose" aria-label="Tutup">&times;</button>
    </div>
    <div class="dxj-drawer__body">
        <form id="dxAddForm" method="POST" action="{{ route('rawdoctor.store') }}" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:16px;">
            @csrf
            <div>
                <label class="dxd-edit__label">Nama Dokter <span style="color:#C0392B;">*</span></label>
                <input type="text" id="dxAddName" name="name" class="dxd-input {{ $errors->has('name') ? 'dxd-input--err' : '' }}" value="{{ old('name') }}" placeholder="Mis: drg. Andi Sp.Ort" autocomplete="off" required>
                @error('name')<div class="dxd-err" style="display:block; margin-top:6px;">{{ $message }}</div>@enderror
                <div class="dxj-hint" style="margin-top:6px;">Spesialisasi otomatis terbaca dari akhiran nama (mis. “Sp.Ort” → Orthodonti).</div>
            </div>
            <div>
                <label class="dxd-edit__label">Foto Dokter</label>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="dxd-input {{ $errors->has('photo') ? 'dxd-input--err' : '' }}">
                @error('photo')<div class="dxd-err" style="display:block; margin-top:6px;">{{ $message }}</div>@enderror
                <div class="dxj-hint" style="margin-top:6px;">Opsional. Format JPG/PNG/WebP, maksimal 2 MB.</div>
            </div>
        </form>
    </div>
    <div class="dxj-drawer__foot">
        <button type="button" class="dx-btn" id="dxAddCancel">Batal</button>
        <button type="submit" form="dxAddForm" class="dx-btn dx-btn--primary" style="flex:1; justify-content:center;"><i class="fa fa-save"></i> Simpan Dokter</button>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('dxAddOverlay');
    var drawer  = document.getElementById('dxAddDrawer');
    var openBtn = document.getElementById('dxAddBtn');
    if (!overlay || !drawer) return;

    function openDrawer() {
        overlay.classList.add('is-open');
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        var name = document.getElementById('dxAddName');
        if (name) setTimeout(function () { name.focus(); }, 130);
    }
    function closeDrawer() {
        overlay.classList.remove('is-open');
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
    }

    if (openBtn) openBtn.addEventListener('click', function (e) { e.preventDefault(); openDrawer(); });
    overlay.addEventListener('click', closeDrawer);
    ['dxAddClose', 'dxAddCancel'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', closeDrawer);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) closeDrawer();
    });

    // Validasi gagal di store() → balik ke list dengan errors: buka lagi drawer-nya.
    @if($errors->has('name') || $errors->has('photo'))
        openDrawer();
    @endif
})();
</script>
@endif
@endsection

