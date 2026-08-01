@extends('layouts.app')

{{-- Pusat Akun → Kesehatan — hi-fi dari design_handoff_pusat_akun/Kesehatan.
     Tema cream + aksen grafit "IT" (token README handoff). Detektor & data
     dari KesehatanController (query hidup deploy-absensi §9). --}}

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --pk-ink: #3B1500; --pk-ink2: #5A3A22; --pk-muted: #7A5B43; --pk-muted2: #9A7256;
        --pk-border: #EFE0CE; --pk-divider: #F4EADD; --pk-graphite: #333330;
        --pk-crit-bg: #FBE3DE; --pk-crit-ink: #C0392B;
        --pk-warn-bg: #FBEFD9; --pk-warn-ink: #B5701A;
        --pk-ok-bg: #E3F4E8;  --pk-ok-ink: #2F8E55;
    }
    .pk { font-family: 'Plus Jakarta Sans', sans-serif; color: var(--pk-ink); padding: 20px 26px 30px; }
    .pk-num { font-variant-numeric: tabular-nums; }
    .pk-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
    .pk-top h1 { font-size: 20px; font-weight: 800; margin: 0; }
    .pk-top .sub { color: var(--pk-muted2); font-size: 13px; margin-top: 2px; }
    .pk-scan { display: inline-flex; align-items: center; gap: 8px; border: 1px solid #E7D3BE; background: #fff; border-radius: 11px; padding: 9px 14px; font-weight: 700; font-size: 13px; color: var(--pk-ink); cursor: pointer; }
    .pk-scan-meta { color: var(--pk-muted2); font-size: 12.5px; margin-right: 10px; }

    /* hero */
    .pk-hero { display: flex; align-items: center; gap: 20px; background: linear-gradient(120deg,#FDF0EA,#F4ECE2); border: 1px solid #F0D3C8; border-left: 5px solid var(--pk-crit-ink); border-radius: 18px; padding: 22px 24px; margin-bottom: 16px; box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05); }
    .pk-hero.ok { background: linear-gradient(120deg,#EEF8F0,#F4ECE2); border-color: #CFE9D6; border-left-color: var(--pk-ok-ink); }
    .pk-hero-ic { width: 62px; height: 62px; border-radius: 16px; display: flex; align-items: center; justify-content: center; flex: none; background: #fff; color: var(--pk-crit-ink); }
    .pk-hero.ok .pk-hero-ic { color: var(--pk-ok-ink); }
    .pk-hero-body { flex: 1; }
    .pk-kick { font-size: 10.5px; font-weight: 800; letter-spacing: .7px; color: var(--pk-muted2); text-transform: uppercase; }
    .pk-hero-n { font-size: 46px; font-weight: 800; line-height: 1; color: var(--pk-crit-ink); display: inline-block; margin: 4px 12px 4px 0; vertical-align: -2px; }
    .pk-hero.ok .pk-hero-n { color: var(--pk-ok-ink); }
    .pk-hero-t { font-size: 18px; font-weight: 800; color: var(--pk-ink); }
    .pk-hero-desc { color: var(--pk-muted); font-size: 13px; margin-top: 6px; max-width: 620px; }
    .pk-hero-boxes { display: flex; gap: 10px; flex: none; }
    .pk-box { min-width: 78px; text-align: center; background: #fff; border: 1px solid var(--pk-border); border-radius: 12px; padding: 12px 10px; }
    .pk-box b { display: block; font-size: 22px; font-weight: 800; }
    .pk-box span { font-size: 10px; font-weight: 800; letter-spacing: .5px; color: var(--pk-muted2); text-transform: uppercase; }
    .pk-box.crit b { color: var(--pk-crit-ink); } .pk-box.warn b { color: var(--pk-warn-ink); } .pk-box.ok b { color: var(--pk-ok-ink); }

    /* segment filter */
    .pk-segs { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
    .pk-seg { display: inline-flex; align-items: center; gap: 7px; border: 1px solid #EADCC9; background: #fff; border-radius: 99px; padding: 8px 15px; font-weight: 700; font-size: 13px; color: var(--pk-muted); cursor: pointer; }
    .pk-seg.on { background: linear-gradient(135deg,#3A3A36,#242422); color: #fff; border-color: transparent; }
    .pk-seg .cnt { font-size: 11px; font-weight: 800; border-radius: 99px; padding: 1px 7px; background: #F1E9DF; color: var(--pk-muted); }
    .pk-seg.on .cnt { background: rgba(255,255,255,.22); color: #fff; }
    .pk-seg-hint { color: var(--pk-muted2); font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px; }

    /* detector grid */
    .pk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 15px; }
    .pk-card { background: #fff; border: 1px solid var(--pk-border); border-left: 4px solid var(--pk-warn-ink); border-radius: 16px; padding: 18px; cursor: pointer; transition: transform .16s, box-shadow .16s; }
    .pk-card:hover { transform: translateY(-2px); box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 14px 30px rgba(70,21,0,.08); }
    .pk-card.crit { border-left-color: var(--pk-crit-ink); } .pk-card.ok { border-left-color: var(--pk-ok-ink); }
    .pk-card-top { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; }
    .pk-card-ic { width: 42px; height: 42px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex: none; background: var(--pk-warn-bg); color: var(--pk-warn-ink); }
    .pk-card.crit .pk-card-ic { background: var(--pk-crit-bg); color: var(--pk-crit-ink); }
    .pk-card.ok .pk-card-ic { background: var(--pk-ok-bg); color: var(--pk-ok-ink); }
    .pk-sev { margin-left: auto; font-size: 10.5px; font-weight: 800; letter-spacing: .3px; border-radius: 99px; padding: 3px 10px; background: var(--pk-warn-bg); color: var(--pk-warn-ink); }
    .pk-card.crit .pk-sev { background: var(--pk-crit-bg); color: var(--pk-crit-ink); }
    .pk-card.ok .pk-sev { background: var(--pk-ok-bg); color: var(--pk-ok-ink); }
    .pk-card h3 { font-size: 15px; font-weight: 800; margin: 0; }
    .pk-card-n { font-size: 34px; font-weight: 800; line-height: 1.1; margin: 6px 0 0; }
    .pk-card.crit .pk-card-n { color: var(--pk-crit-ink); } .pk-card.warn .pk-card-n { color: var(--pk-warn-ink); } .pk-card.ok .pk-card-n { color: var(--pk-ok-ink); }
    .pk-card-unit { font-size: 12px; color: var(--pk-muted2); font-weight: 600; }
    .pk-card-arti { color: var(--pk-muted); font-size: 12.5px; line-height: 1.5; margin: 8px 0 0; min-height: 38px; }
    .pk-card-foot { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--pk-divider); font-size: 12.5px; font-weight: 700; color: var(--pk-ink2); display: flex; align-items: center; justify-content: space-between; }
    .pk-card.ok .pk-card-foot { color: var(--pk-ok-ink); }

    /* drawer */
    .pk-scrim { position: fixed; inset: 0; background: rgba(40,12,0,.32); z-index: 1080; opacity: 0; pointer-events: none; transition: opacity .18s; }
    .pk-scrim.on { opacity: 1; pointer-events: auto; }
    .pk-drawer { position: fixed; top: 0; right: 0; height: 100vh; width: 480px; max-width: 94vw; background: #FBF5EE; z-index: 1081; box-shadow: -12px 0 40px rgba(40,12,0,.28); transform: translateX(100%); transition: transform .26s cubic-bezier(.2,.8,.2,1); display: flex; flex-direction: column; }
    .pk-drawer.on { transform: none; }
    .pk-dh { padding: 22px 22px 18px; color: #fff; background: linear-gradient(120deg,#B87A1E,#94600F); }
    .pk-dh.crit { background: linear-gradient(120deg,#B23124,#8E2419); } .pk-dh.ok { background: linear-gradient(120deg,#2F8E55,#237045); }
    .pk-dh .kick { font-size: 10.5px; font-weight: 800; letter-spacing: .6px; opacity: .85; text-transform: uppercase; }
    .pk-dh h2 { font-size: 20px; font-weight: 800; margin: 4px 0 0; }
    .pk-dh .n { font-size: 34px; font-weight: 800; margin-top: 6px; }
    .pk-dh .x { position: absolute; top: 16px; right: 16px; width: 34px; height: 34px; border-radius: 50%; border: 0; background: rgba(255,255,255,.2); color: #fff; font-size: 18px; cursor: pointer; }
    .pk-db { flex: 1; overflow-y: auto; padding: 18px 22px 26px; }
    .pk-db h4 { font-size: 11px; font-weight: 800; letter-spacing: .5px; color: var(--pk-muted); text-transform: uppercase; margin: 0 0 10px; }
    .pk-case { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px dashed var(--pk-divider); }
    .pk-case .dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; flex: none; background: var(--pk-warn-ink); }
    .pk-case.crit .dot { background: var(--pk-crit-ink); }
    .pk-case b { color: var(--pk-ink); font-size: 13.5px; } .pk-case span { color: var(--pk-muted2); font-size: 12px; display: block; }
    .pk-fix { margin-top: 18px; background: #F4ECE2; border: 1px solid var(--pk-border); border-radius: 12px; padding: 14px 16px; }
    .pk-fix .lbl { font-size: 10.5px; font-weight: 800; letter-spacing: .5px; color: var(--pk-warn-ink); text-transform: uppercase; margin-bottom: 6px; }
    .pk-fix p { color: var(--pk-ink2); font-size: 13px; line-height: 1.55; margin: 0; }
    .pk-ok-empty { text-align: center; color: var(--pk-ok-ink); padding: 30px 20px; }
    .pk-hidden { display: none; }
</style>
@endsection

@section('content')
@php
    // ikon SVG per nama (dari controller). Semua garis currentColor.
    $svg = [
        'lock'   => '<path d="M6 10V7a6 6 0 0 1 12 0v3M5 10h14a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1z"/>',
        'unlink' => '<path d="M9 15l-2 2a4 4 0 0 1-5.6-5.6l2-2M15 9l2-2a4 4 0 0 1 5.6 5.6l-2 2M8 8l8 8"/>',
        'sync'   => '<path d="M4 12a8 8 0 0 1 13.7-5.6L20 8M20 4v4h-4M20 12a8 8 0 0 1-13.7 5.6L4 16M4 20v-4h4"/>',
        'copy'   => '<rect x="8.5" y="8.5" width="11" height="11" rx="2"/><path d="M4.5 15.5V6a1.5 1.5 0 0 1 1.5-1.5h9.5"/>',
        'route'  => '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18H15a3.5 3.5 0 0 0 0-7H9a3.5 3.5 0 0 1 0-7"/>',
        'user-question' => '<circle cx="10" cy="8" r="3.5"/><path d="M4 20a6 6 0 0 1 9.5-4.9M17 14.5a1.8 1.8 0 1 1 2 2.3c-.6.4-1 .7-1 1.4M19 21v.1"/>',
        'mobile' => '<rect x="7" y="3" width="10" height="18" rx="2.5"/><path d="M11 18h2"/>',
        'shield' => '<path d="M12 3l7 3v5c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6z"/>',
    ];
    $ico = fn ($n, $sz = 22) => '<svg width="'.$sz.'" height="'.$sz.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'.($svg[$n] ?? $svg['shield']).'</svg>';
    $sevLabel = ['crit' => 'Kritis', 'warn' => 'Perlu ditinjau', 'ok' => 'Sehat'];
@endphp
<div class="pk">
    @include('layouts.notif')

    {{-- topbar --}}
    <div class="pk-top">
        <div>
            <h1>Kesehatan Akun</h1>
            <div class="sub">Pusat Akun · Deteksi masalah senyap sebelum jadi keluhan</div>
        </div>
        <div style="display:flex;align-items:center">
            <span class="pk-scan-meta">Terakhir dipindai <strong>baru saja</strong></span>
            <button type="button" class="pk-scan" onclick="location.reload()">
                {!! $ico('sync', 15) !!} Pindai ulang
            </button>
        </div>
    </div>

    {{-- hero --}}
    <div class="pk-hero {{ $totalMasalah === 0 ? 'ok' : '' }}">
        <div class="pk-hero-ic">{!! $ico($totalMasalah === 0 ? 'shield' : 'user-question', 28) !!}</div>
        <div class="pk-hero-body">
            <div class="pk-kick">Status Kesehatan Akun</div>
            @if($totalMasalah === 0)
                <span class="pk-hero-n pk-num">0</span><span class="pk-hero-t">Semua sehat</span>
                <div class="pk-hero-desc">Tidak ada masalah senyap yang terdeteksi pada akun & akses. Mampir lagi sesekali.</div>
            @else
                <span class="pk-hero-n pk-num">{{ $totalMasalah }}</span><span class="pk-hero-t">masalah butuh perhatian</span>
                <div class="pk-hero-desc">Ditemukan di {{ $nBermasalah }} dari {{ count($detektor) }} detektor. Prioritaskan yang kritis dulu — klik kartu untuk lihat siapa yang terdampak &amp; langkah perbaikannya.</div>
            @endif
        </div>
        <div class="pk-hero-boxes">
            <div class="pk-box crit"><b class="pk-num">{{ $nKritis }}</b><span>Kritis</span></div>
            <div class="pk-box warn"><b class="pk-num">{{ $nDitinjau }}</b><span>Ditinjau</span></div>
            <div class="pk-box ok"><b class="pk-num">{{ $nSehat }}</b><span>Sehat</span></div>
        </div>
    </div>

    {{-- segment filter --}}
    <div class="pk-segs">
        <button type="button" class="pk-seg on" data-seg="all">Semua <span class="cnt pk-num">{{ count($detektor) }}</span></button>
        <button type="button" class="pk-seg" data-seg="issues">Butuh perhatian <span class="cnt pk-num">{{ $nBermasalah }}</span></button>
        <button type="button" class="pk-seg" data-seg="ok">Sehat <span class="cnt pk-num">{{ $nSehat }}</span></button>
        <span class="pk-seg-hint">{!! $ico('sync', 14) !!} Klik kartu untuk lihat siapa yang terdampak &amp; cara benerin</span>
    </div>

    {{-- grid detektor --}}
    <div class="pk-grid">
        @foreach($detektor as $key => $d)
            @php $n = count($d['rows']); $sev = $n === 0 ? 'ok' : $d['sev']; @endphp
            <div class="pk-card {{ $sev }}" data-issue="{{ $n ? 1 : 0 }}" onclick="pkDrawer('{{ $key }}')">
                <div class="pk-card-top">
                    <div class="pk-card-ic">{!! $ico($d['icon'], 22) !!}</div>
                    <span class="pk-sev">{{ $sevLabel[$sev] }}</span>
                </div>
                <h3>{{ $d['judul'] }}</h3>
                <div class="pk-card-n pk-num">{{ $n }}</div>
                <div class="pk-card-unit">{{ $n === 1 ? 'kasus' : 'kasus' }}</div>
                <p class="pk-card-arti">{{ $d['arti'] }}</p>
                <div class="pk-card-foot">
                    <span>{{ $n ? 'Lihat ' . $n . ' terdampak' : 'Semua sehat · lihat detail' }}</span>
                    <span>›</span>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- drawer --}}
<div id="pk-scrim" class="pk-scrim" onclick="pkClose()"></div>
<div id="pk-drawer" class="pk-drawer">
    <div id="pk-dh" class="pk-dh" style="position:relative">
        <button type="button" class="x" onclick="pkClose()">&times;</button>
        <div class="kick" id="pk-dh-kick"></div>
        <h2 id="pk-dh-judul"></h2>
        <div class="n pk-num" id="pk-dh-n"></div>
    </div>
    <div class="pk-db">
        <h4>Yang terdampak</h4>
        <div id="pk-cases"></div>
        <div class="pk-fix">
            <div class="lbl">Cara benerin</div>
            <p id="pk-benerin"></p>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    var PK = {!! collect($detektor)->map(fn ($d, $k) => [
        'judul'   => $d['judul'],
        'sev'     => count($d['rows']) === 0 ? 'ok' : $d['sev'],
        'n'       => count($d['rows']),
        'benerin' => $d['benerin'],
        'rows'    => array_map(fn ($r) => ['utama' => $r['utama'], 'detail' => $r['detail']], $d['rows']),
    ])->toJson() !!};
    var PK_SEVLBL = { crit: 'Kritis', warn: 'Perlu ditinjau', ok: 'Sehat' };

    // segment filter
    document.querySelectorAll('.pk-seg').forEach(function (s) {
        s.addEventListener('click', function () {
            document.querySelectorAll('.pk-seg').forEach(function (x) { x.classList.remove('on'); });
            s.classList.add('on');
            var seg = s.dataset.seg;
            document.querySelectorAll('.pk-card').forEach(function (c) {
                var iss = c.dataset.issue === '1';
                c.classList.toggle('pk-hidden', seg === 'issues' ? !iss : seg === 'ok' ? iss : false);
            });
        });
    });

    function pkDrawer(key) {
        var d = PK[key]; if (!d) return;
        var dh = document.getElementById('pk-dh');
        dh.className = 'pk-dh ' + d.sev;
        document.getElementById('pk-dh-kick').textContent = PK_SEVLBL[d.sev];
        document.getElementById('pk-dh-judul').textContent = d.judul;
        document.getElementById('pk-dh-n').textContent = d.n + (d.n === 1 ? ' kasus' : ' kasus');

        var box = document.getElementById('pk-cases');
        if (d.rows.length === 0) {
            box.innerHTML = '<div class="pk-ok-empty"><b>Semua sehat di detektor ini.</b><div style="font-size:12.5px;margin-top:4px">Tidak ada yang terdampak.</div></div>';
        } else {
            box.innerHTML = '';
            d.rows.forEach(function (r) {
                var el = document.createElement('div');
                el.className = 'pk-case ' + (d.sev === 'crit' ? 'crit' : '');
                el.innerHTML = '<span class="dot"></span><div><b></b><span></span></div>';
                el.querySelector('b').textContent = r.utama;
                el.querySelector('span:last-child').textContent = r.detail;
                box.appendChild(el);
            });
        }
        document.getElementById('pk-benerin').textContent = d.benerin;
        document.getElementById('pk-drawer').classList.add('on');
        document.getElementById('pk-scrim').classList.add('on');
    }
    function pkClose() {
        document.getElementById('pk-drawer').classList.remove('on');
        document.getElementById('pk-scrim').classList.remove('on');
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') pkClose(); });
</script>
@endsection
