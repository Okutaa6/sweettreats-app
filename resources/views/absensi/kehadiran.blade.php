@extends('layouts.app')

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/finance.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/finance.sweettreats.css')) ?: '1' }}">
<style>
    .khx-cell { position: relative; border: 0; cursor: pointer; padding: 0; }
    .khx-cell:not(.khx-empty):hover { outline: 2px solid #461500; outline-offset: -1px; }
    .khx-foto { position: absolute; bottom: 2px; left: 2px; width: 5px; height: 5px; border-radius: 50%; background: #2779a7; }
    .khx-sw-foto { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #2779a7; }
    .kh-pop-scrim { position: fixed; inset: 0; background: rgba(30,15,5,.4); z-index: 1090; display: none; }
    .kh-pop-scrim.on { display: block; }
    .kh-pop { position: fixed; z-index: 1091; display: none; width: 300px; max-width: calc(100vw - 24px); background: #fff; border: 1px solid #eadfd6; border-radius: 16px; box-shadow: 0 18px 44px rgba(58,17,0,.28); padding: 16px 18px; font-family: 'Plus Jakarta Sans', sans-serif; }
    .kh-pop.on { display: block; }
    .kh-pop-x { position: absolute; top: 10px; right: 12px; border: 0; background: none; font-size: 22px; line-height: 1; color: #b7a595; cursor: pointer; }
    .kh-pop-nama { font-weight: 800; color: #3B1500; font-size: 16px; }
    .kh-pop-tgl { color: #9A7256; font-size: 12.5px; }
    .kh-pop-status { display: inline-block; margin: 10px 0 4px; font-size: 12px; font-weight: 800; border-radius: 99px; padding: 3px 12px; }
    .kh-pop-rows { margin-top: 6px; }
    .kh-pop-row { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-top: 1px solid #f2e9df; font-size: 13px; }
    .kh-pop-row span { color: #9A7256; }
    .kh-pop-row b { color: #3B1500; font-weight: 700; }
    .kh-pop-foto { display: flex; gap: 8px; margin-top: 10px; }
    .kh-pop-foto figure { margin: 0; text-align: center; }
    .kh-pop-foto img { width: 92px; height: 116px; object-fit: cover; border-radius: 10px; background: #241309; display: block; }
    .kh-pop-foto figcaption { font-size: 11px; color: #9A7256; margin-top: 3px; }
    .kh-pop-nofoto { color: #b7a595; font-size: 12px; margin-top: 8px; font-style: italic; }
</style>
@endsection

@php $gridCols = '210px repeat(' . $daysInMonth . ', 30px)'; @endphp

@section('content')
<div class="fin-wrap">
    @include('layouts.notif')
    @include('absensi._finance_tabs')

    {{-- KPI --}}
    <div class="fin-kpis">
        <div class="fin-kpi">
            <div class="fin-kpi-k">KARYAWAN</div>
            <div class="fin-kpi-v num">{{ $summary['karyawan'] }}</div>
            <div class="fin-kpi-s">punya catatan absen periode ini</div>
        </div>
        <div class="fin-kpi">
            <div class="fin-kpi-k">TOTAL HADIR</div>
            <div class="fin-kpi-v num">{{ $summary['hadir'] }}</div>
            <div class="fin-kpi-s">hari-orang masuk normal</div>
        </div>
        <div class="fin-kpi">
            <div class="fin-kpi-k">TOTAL TELAT</div>
            <div class="fin-kpi-v num amber">{{ $summary['telat'] }}</div>
            <div class="fin-kpi-s">hari-orang datang terlambat</div>
        </div>
        <div class="fin-kpi warn">
            <div class="fin-kpi-k red">⚠ TIDAK MASUK</div>
            <div class="fin-kpi-v num red">{{ $summary['alpa'] }}</div>
            <div class="fin-kpi-s">hari-orang tanpa keterangan</div>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ url('absensi/kehadiran') }}" class="fin-filters" id="khFilter">
        <div class="fin-field">
            <label>PERIODE</label>
            <input type="month" name="m" value="{{ $anchor->format('Y-m') }}" class="fin-input" onchange="document.getElementById('khFilter').submit()">
        </div>
        <div class="fin-field">
            <label>CABANG</label>
            <select name="branch" class="fin-input" onchange="document.getElementById('khFilter').submit()">
                <option value="">Semua Cabang</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected($branchFilter === $b->id)>{{ $b->name }}</option>
                @endforeach
                <option value="{{ $abgKey }}" @selected($branchFilter === $abgKey)>{{ $abgLabel }}</option>
            </select>
        </div>
        <div class="fin-count">Dikelompokkan per <b>cabang asal</b> · <b class="num">{{ $summary['karyawan'] }}</b> karyawan</div>
    </form>

    {{-- Matriks --}}
    <div class="khx-card">
        @if(empty($groups))
            <div class="fin-empty">Belum ada catatan absen di periode / cabang ini.</div>
        @else
            <div class="khx-scroll">
                <div class="khx-inner">
                    {{-- header hari --}}
                    <div class="khx-head" style="grid-template-columns:{{ $gridCols }}">
                        <div class="khx-head-name">KARYAWAN</div>
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            <div class="khx-head-day">
                                <div class="khx-dow {{ $head[$d]['sun'] ? 'sun' : '' }}">{{ $head[$d]['dow'] }}</div>
                                <div class="num khx-dn">{{ $head[$d]['n'] }}</div>
                            </div>
                        @endfor
                    </div>

                    @foreach($groups as $g)
                        {{-- pembatas per cabang asal --}}
                        <div class="khx-sep {{ $g['flex'] ? 'is-abg' : '' }}">
                            <span class="khx-sep-label">{{ $g['label'] }} · <span class="num">{{ count($g['rows']) }}</span> orang
                                @if($g['flex'])<span class="khx-sep-note">jam bebas — scan tak dinilai</span>@endif
                            </span>
                        </div>

                        @foreach($g['rows'] as $row)
                            <div class="khx-row" style="grid-template-columns:{{ $gridCols }}">
                                <div class="khx-name">
                                    <div class="khx-av">{{ $row['initial'] }}</div>
                                    <div class="khx-name-tx">
                                        <div class="khx-nm">{{ $row['nama'] }}</div>
                                        <div class="khx-meta">{{ $row['label'] }} · <span class="num khx-h">{{ $row['h'] }}</span> <span class="num khx-t">{{ $row['t'] }}</span> <span class="num khx-a">{{ $row['a'] }}</span></div>
                                    </div>
                                </div>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    @php $c = $row['cells'][$d] ?? null; @endphp
                                    @if($c && isset($statusMeta[$c['status']]))
                                        @php
                                            $m = $statusMeta[$c['status']];
                                            $tip = $head[$d]['n'] . ' ' . $monthShort . ' · ' . $m['label']
                                                . ($c['telat'] ? ' · telat ' . $c['telat'] . ' mnt' : '')
                                                . ($c['lembur'] ? ' · lembur ' . round($c['lembur'] / 60, 1) . ' j' : '')
                                                . ($c['single'] ? ' · scan tunggal' : '')
                                                . ($c['lain'] ? ' · scan di ' . implode(', ', $c['lain']) : '');
                                        @endphp
                                        <button type="button" class="khx-cell" style="background:{{ $m['bg'] }}" title="{{ $tip }}"
                                                onclick="khOpen(this,'{{ $row['id'] }}',{{ $d }})">
                                            @if($c['lembur'])<span class="khx-lembur"></span>@endif
                                            @if($c['lain'])<span class="khx-lain"></span>@endif
                                            @if(!empty($c['foto']))<span class="khx-foto"></span>@endif
                                        </button>
                                    @else
                                        <div class="khx-cell khx-empty" title="belum ada data"></div>
                                    @endif
                                @endfor
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>

            {{-- legenda --}}
            <div class="khx-legend">
                @foreach($statusMeta as $key => $m)
                    <span class="khx-leg"><span class="khx-sw" style="background:{{ $m['bg'] }}"></span>{{ $m['label'] }}</span>
                @endforeach
                <span class="khx-leg"><span class="khx-sw-dot"></span>ada lembur</span>
                <span class="khx-leg"><span class="khx-sw-lain"></span>scan di cabang lain</span>
                <span class="khx-leg"><span class="khx-sw-foto"></span>ada foto selfie</span>
                <span class="khx-leg"><span class="khx-sw-dash"></span>belum ada data</span>
            </div>
        @endif
    </div>
</div>

{{-- Popover detail hari (klik sel) --}}
<div id="kh-pop-scrim" class="kh-pop-scrim" onclick="khClose()"></div>
<div id="kh-pop" class="kh-pop" role="dialog" aria-modal="true">
    <button type="button" class="kh-pop-x" onclick="khClose()" aria-label="Tutup">&times;</button>
    <div class="kh-pop-head">
        <div id="kh-pop-nama" class="kh-pop-nama"></div>
        <div id="kh-pop-tgl" class="kh-pop-tgl"></div>
    </div>
    <span id="kh-pop-status" class="kh-pop-status"></span>
    <div id="kh-pop-rows" class="kh-pop-rows"></div>
    <div id="kh-pop-foto" class="kh-pop-foto"></div>
</div>
@endsection

@section('js')
<script>
    // Detail per sel — dibangun server-side, dibaca saat sel diklik. Volume kecil.
    var KH = {};
    @foreach($groups as $g)
        @foreach($g['rows'] as $row)
            @foreach($row['cells'] as $d => $c)
                KH['{{ $row['id'] }}_{{ $d }}'] = {
                    nama: @json($row['nama']),
                    status: @json($statusMeta[$c['status']]['label'] ?? $c['status']),
                    bg: @json($statusMeta[$c['status']]['bg'] ?? '#eee'),
                    fg: @json($statusMeta[$c['status']]['c'] ?? '#555'),
                    jamIn: @json($c['jam_in']),
                    jamOut: @json($c['jam_out']),
                    telat: {{ $c['telat'] }},
                    lembur: {{ $c['lembur'] }},
                    lain: @json($c['lain']),
                    single: {{ $c['single'] }},
                    foto: [
                        @foreach($c['foto'] as $f){ url: @json(route('pengawasan.foto', $f['id'])), jenis: @json($f['jenis']) },@endforeach
                    ]
                };
            @endforeach
        @endforeach
    @endforeach

    var KH_HARI = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    var KH_BULAN = @json($anchor->translatedFormat('F Y'));
    var KH_ANCHOR = @json($anchor->format('Y-m'));

    function khOpen(cellEl, uid, day) {
        var d = KH[uid + '_' + day];
        if (!d) return;
        document.getElementById('kh-pop-nama').textContent = d.nama;
        var tgl = new Date(KH_ANCHOR + '-' + String(day).padStart(2, '0') + 'T00:00:00');
        document.getElementById('kh-pop-tgl').textContent = KH_HARI[tgl.getDay()] + ', ' + day + ' ' + KH_BULAN;

        var st = document.getElementById('kh-pop-status');
        st.textContent = d.status; st.style.background = d.bg; st.style.color = d.fg;

        var rows = '';
        function row(k, v) { rows += '<div class="kh-pop-row"><span>' + k + '</span><b>' + v + '</b></div>'; }
        if (d.jamIn)  row('Absen masuk', d.jamIn);
        if (d.jamOut) row('Absen pulang', d.jamOut);
        else if (d.jamIn && d.single) row('Absen pulang', 'tidak ada (scan tunggal)');
        if (d.telat)  row('Telat', d.telat + ' menit');
        if (d.lembur) row('Lembur', (Math.round(d.lembur / 60 * 10) / 10) + ' jam');
        if (d.lain && d.lain.length) row('Scan di cabang lain', d.lain.join(', '));
        document.getElementById('kh-pop-rows').innerHTML = rows;

        var fEl = document.getElementById('kh-pop-foto');
        if (d.foto && d.foto.length) {
            var h = '';
            d.foto.forEach(function (f) {
                h += '<figure><img src="' + f.url + '" alt="selfie" loading="lazy"><figcaption>' + f.jenis + '</figcaption></figure>';
            });
            fEl.innerHTML = h;
        } else {
            fEl.innerHTML = '<div class="kh-pop-nofoto">Tidak ada foto selfie (absen dari mesin sidik jari).</div>';
        }

        // posisikan popover dekat sel yang diklik
        var pop = document.getElementById('kh-pop');
        pop.classList.add('on');
        document.getElementById('kh-pop-scrim').classList.add('on');
        var cell = cellEl.getBoundingClientRect();
        var pw = pop.offsetWidth, ph = pop.offsetHeight;
        var left = Math.min(window.innerWidth - pw - 12, Math.max(12, cell.left + cell.width / 2 - pw / 2));
        var top = cell.bottom + 8;
        if (top + ph > window.innerHeight - 12) top = Math.max(12, cell.top - ph - 8);
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
    }

    function khClose() {
        document.getElementById('kh-pop').classList.remove('on');
        document.getElementById('kh-pop-scrim').classList.remove('on');
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') khClose(); });
</script>
@endsection
