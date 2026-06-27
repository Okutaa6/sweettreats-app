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
        $avatarColors = ['#7A3415', '#52200A', '#8A3A12', '#6B2400', '#9A6614', '#5A2410', '#7E2E0E', '#693012'];
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

        {{-- ══════ Control row: search + Filter + toggle Tabel|Jadwal Semua ══════ --}}
        <div class="dx-controls">
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
            <div class="dx-toggle">
                <button type="button" class="dx-toggle__opt is-active" data-dx-mode="tabel"><i class="fa fa-list-ul"></i> Tabel</button>
                <button type="button" class="dx-toggle__opt" data-dx-mode="jadwal"><i class="fa fa-table"></i> Jadwal Semua</button>
            </div>
        </div>

        {{-- ══════ Filter panel (spesialisasi + status) ══════ --}}
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

        {{-- ══════ MODE: TABEL ══════ --}}
        <div data-dx-panel="tabel" class="dx-wrap">
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

        {{-- ══════ MODE: JADWAL SEMUA (master — fase berikutnya) ══════ --}}
        <div data-dx-panel="jadwal" style="display:none;">
            <div class="dx-master-stub">
                <i class="fa fa-table"></i>
                <div style="font-weight:700;color:var(--dx-text-2);">Jadwal Semua Dokter</div>
                <div style="margin-top:4px;">Matriks jadwal mingguan semua dokter — dibangun di fase berikutnya.</div>
            </div>
        </div>

    </div>
</div>
@endsection

@section('js')
<script>
    // ── Toggle mode Tabel | Jadwal Semua (client-side) ──
    (function () {
        var opts = document.querySelectorAll('.dx-toggle__opt');
        var panels = document.querySelectorAll('[data-dx-panel]');
        var filterBtn = document.getElementById('dxFilterToggle');
        function activate(mode) {
            opts.forEach(function (o) { o.classList.toggle('is-active', o.dataset.dxMode === mode); });
            panels.forEach(function (p) { p.style.display = p.dataset.dxPanel === mode ? '' : 'none'; });
            if (filterBtn) filterBtn.style.display = mode === 'tabel' ? '' : 'none';
        }
        opts.forEach(function (o) { o.addEventListener('click', function () { activate(o.dataset.dxMode); }); });
    })();
</script>
@endsection
