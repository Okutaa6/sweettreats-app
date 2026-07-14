@extends('layouts.app')

@section('content')
@php
    $campLabels = \App\Models\MarketingPromo::campaignTypes();
    $ccMap      = \App\Models\MarketingPromo::campaignColors();
    $rp = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $rangeUrl = fn($r) => request()->fullUrlWithQuery(['range' => $r]);
@endphp
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<div class="mds">
    {{-- ── Header + filter ── --}}
    <div class="mds-top">
        <div>
            <h4 class="mds-h">Dashboard Marketing</h4>
            <div class="mds-h__sub">{{ $branchName }} · {{ $monthLabel }}</div>
        </div>
        <form class="mds-filterbar" method="GET">
            <input type="hidden" name="range" value="{{ $range }}">
            <select name="cabang" class="mds-input" onchange="this.form.submit()">
                <option value="">Semua Cabang</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}" {{ $selected === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>
            <input type="month" name="bulan" value="{{ $monthValue }}" class="mds-input">
            <button class="mds-btn"><i class="fa fa-eye"></i> Lihat</button>
        </form>
    </div>

    {{-- ════ KPI strip (+ sparkline) ════ --}}
    <div class="mds-kpis">
        @php
            $delta = $kpi['pasien_baru_delta'];
            $deltaTxt = $delta == 0 ? 'sama dgn bulan lalu' : ($delta > 0 ? '▲ ' : '▼ ') . abs($delta) . ' vs bln lalu';
        @endphp
        <div class="mds-kpi">
            <div class="mds-chip" style="background:#F3E6F7;color:#8E4FA8;"><i class="fa fa-user-plus"></i></div>
            <div class="mds-kpi__label">Pasien Baru</div>
            <div class="mds-kpi__val">{{ $kpi['pasien_baru'] }}</div>
            <div class="mds-kpi__sub {{ $delta > 0 ? 'is-ok' : ($delta < 0 ? 'is-warn' : '') }}">{{ $deltaTxt }}</div>
        </div>
        <div class="mds-kpi">
            <div class="mds-chip" style="background:#E2F1EA;color:#2F8E55;"><i class="fa fa-heartbeat"></i></div>
            <div class="mds-kpi__label">Kunjungan</div>
            <div class="mds-kpi__val">{{ $kpi['kunjungan'] }}</div>
            <div class="mds-kpi__sub">total kunjungan bln ini</div>
        </div>
        <div class="mds-kpi">
            <div class="mds-chip" style="background:#E4ECFB;color:#3C5FA8;"><i class="fa fa-images"></i></div>
            <div class="mds-kpi__label">Konten Tayang</div>
            <div class="mds-kpi__val">{{ $kpi['konten_tayang'] }}</div>
            <div class="mds-kpi__sub">jadwal upload bln ini</div>
        </div>
        <div class="mds-kpi">
            <div class="mds-chip" style="background:#FBE7D2;color:#C0791F;"><i class="fa fa-bullhorn"></i></div>
            <div class="mds-kpi__label">KOL &amp; KOC</div>
            <div class="mds-kpi__val">{{ $kpi['affiliate'] }}</div>
            <div class="mds-kpi__sub">partner terdaftar</div>
        </div>
    </div>

    {{-- ════ TIER 1: Promo (kiri) | Sumber Pasien (kanan) — urutan diatur via CSS order ════ --}}
    <div class="mds-grid mds-grid--t1">
        {{-- SUMBER PASIEN (tampil di KANAN via order, kolom sempit) --}}
        <div class="mds-card">
            <div class="mds-sec"><span class="mds-sec__ic" style="background:#FBE7D2;color:#C0791F;"><i class="fa fa-compass"></i></span>
                Dari Mana Pasien Datang <span class="mds-sec__sub">— {{ number_format($sourceMix['total'], 0, ',', '.') }} pasien (keseluruhan)</span></div>
            @if ($sourceMix['total'] > 0)
                <div class="mds-donutwrap">
                    <div class="mds-donut">
                        <svg viewBox="0 0 120 120">
                            <circle cx="60" cy="60" r="{{ $sourceMix['r'] }}" fill="none" stroke="#F4ECE2" stroke-width="14"/>
                            @foreach ($sourceMix['segs'] as $sg)
                                <circle cx="60" cy="60" r="{{ $sourceMix['r'] }}" fill="none" stroke="{{ $sg['color'] }}" stroke-width="14"
                                    stroke-dasharray="{{ $sg['dash'] }} {{ $sg['gap'] }}" stroke-dashoffset="{{ $sg['offset'] }}"
                                    transform="rotate(-90 60 60)"/>
                            @endforeach
                            <text x="60" y="56" text-anchor="middle" class="mds-donut__n">{{ $sourceMix['total'] > 0 ? round($sourceMix['known'] / $sourceMix['total'] * 100) : 0 }}%</text>
                            <text x="60" y="70" text-anchor="middle" class="mds-donut__l">tercatat</text>
                        </svg>
                    </div>
                    <div class="mds-legend">
                        @foreach ($sourceMix['items'] as $it)
                            <div class="mds-legend__row">
                                <span class="mds-legend__dot" style="background:{{ $it['color'] }};"></span>
                                <span class="mds-legend__lbl">{{ $it['label'] }}</span>
                                <span class="mds-legend__pct">{{ $it['pct'] }}%</span>
                                <span class="mds-legend__n">{{ number_format($it['count'], 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                {{-- Bar proporsi berwarna (isi ruang + kasih warna) --}}
                <div class="mds-srcbar">
                    @foreach ($sourceMix['items'] as $it)
                        @if ($it['pct'] > 0)
                            <span class="mds-srcbar__seg" style="width:{{ $it['pct'] }}%;background:{{ $it['color'] }};" title="{{ $it['label'] }} · {{ $it['pct'] }}%"></span>
                        @endif
                    @endforeach
                </div>
                @php $topCh = collect($sourceMix['items'])->firstWhere('key', '!=', 'other'); @endphp
                @if ($topCh)
                    <div class="mds-srcnote">
                        <i class="fa fa-trophy"></i> Channel terbesar: <b style="color:{{ $topCh['color'] }};">{{ $topCh['label'] }}</b> ({{ $topCh['pct'] }}%)
                        · {{ round($sourceMix['known'] / $sourceMix['total'] * 100) }}% pasien sumbernya tercatat
                    </div>
                @endif
            @else
                <div class="mds-empty">Belum ada data pasien untuk cabang ini.</div>
            @endif
        </div>

        {{-- PROMO --}}
        <div class="mds-card">
            <div class="mds-sec"><span class="mds-sec__ic" style="background:#F4E7D8;color:#7A3415;"><i class="fa fa-tags"></i></span> Katalog Promo</div>
            @if ($promos->isEmpty())
                <div class="mds-empty">Belum ada promo untuk cabang ini.</div>
            @else
                <div class="sds-promo-grid">
                    @foreach ($promos as $p)
                        @include('dashboard._promo_card', ['p' => $p, 'active' => ! $p->is_upcoming, 'compact' => true])
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ════ TIER 2: Akuisisi (stacked) | Roster KOL/KOC ════ --}}
    <div class="mds-grid mds-grid--t2">
        <div class="mds-card">
            <div class="mds-sec mds-sec--split">
                <span><span class="mds-sec__ic" style="background:#F3E6F7;color:#8E4FA8;"><i class="fa fa-chart-bar"></i></span>
                    Akuisisi Pasien <span class="mds-sec__sub">— baru vs lama / bulan</span></span>
                <span class="mds-toggle">
                    <a href="{{ $rangeUrl(6) }}" class="{{ $range === 6 ? 'is-on' : '' }}">6 bln</a>
                    <a href="{{ $rangeUrl(12) }}" class="{{ $range === 12 ? 'is-on' : '' }}">12 bln</a>
                </span>
            </div>
            @if ($acq['has_data'])
                <div class="mds-chartwrap">
                    <svg id="mdsAcq" viewBox="0 0 {{ $acq['width'] }} {{ $acq['height'] }}" class="mds-chart">
                        @foreach ($acq['grid'] as $g)
                            <line x1="30" y1="{{ $g['y'] }}" x2="{{ $acq['width']-6 }}" y2="{{ $g['y'] }}" stroke="#EFE2D6" stroke-width="1"/>
                            <text x="26" y="{{ $g['y']+3 }}" text-anchor="end" class="mds-axis">{{ $g['label'] }}</text>
                        @endforeach
                        @foreach ($acq['bars'] as $b)
                            <rect class="mds-gbar" x="{{ $b['x'] }}" y="{{ $b['lama_y'] }}" width="{{ $b['w'] }}" height="{{ $b['lama_h'] }}" fill="#E0CBB6" rx="2"/>
                            <rect class="mds-gbar" x="{{ $b['x'] }}" y="{{ $b['baru_y'] }}" width="{{ $b['w'] }}" height="{{ $b['baru_h'] }}" fill="#8E4FA8" rx="2"/>
                            <rect class="mds-hit" x="{{ $b['x'] }}" y="14" width="{{ $b['w'] }}" height="{{ $acq['base_y']-14 }}" fill="transparent"
                                data-label="{{ $b['full'] }}" data-baru="{{ $b['baru'] }}" data-lama="{{ $b['lama'] }}"></rect>
                            <text x="{{ $b['tx'] }}" y="{{ $acq['height']-8 }}" text-anchor="middle" class="mds-axis">{{ $b['tick'] }}</text>
                        @endforeach
                    </svg>
                    <div class="mds-tip" id="mdsTip"></div>
                </div>
                <div class="mds-clegend">
                    <span><i class="mds-sw" style="background:#8E4FA8;"></i> Pasien baru ({{ number_format($acq['total_baru'],0,',','.') }})</span>
                    <span><i class="mds-sw" style="background:#E0CBB6;"></i> Pasien lama ({{ number_format($acq['total_lama'],0,',','.') }})</span>
                </div>
            @else
                <div class="mds-empty">Belum ada data kunjungan.</div>
            @endif
        </div>

        {{-- ROSTER + KEYWORD (di-stack biar ngisi tinggi grafik) --}}
        <div class="mds-col">
            <div class="mds-card">
                <div class="mds-sec mds-sec--m0"><span class="mds-sec__ic" style="background:#FBE7D2;color:#C0791F;"><i class="fa fa-star"></i></span> Roster KOL &amp; KOC</div>
                <div class="mds-twostat">
                    <div class="mds-twostat__b"><div class="mds-twostat__n">{{ $roster['kol'] }}</div><div class="mds-twostat__l">KOL</div></div>
                    <div class="mds-twostat__b"><div class="mds-twostat__n">{{ $roster['koc'] }}</div><div class="mds-twostat__l">KOC</div></div>
                </div>
                @if (! empty($roster['kategori']))
                    <div class="mds-subhead">Kategori KOL</div>
                    <div class="mds-tags">
                        @foreach ($roster['kategori'] as $k)<span class="mds-tag">{{ $k['label'] }} <b>{{ $k['count'] }}</b></span>@endforeach
                    </div>
                @endif
                @if (! empty($roster['top']))
                    <div class="mds-subhead">Followers Terbesar</div>
                    @foreach ($roster['top'] as $t)
                        <div class="mds-rank">
                            <span class="mds-rank__name">{{ $t['name'] }}@if ($t['kategori'])<span class="mds-rank__kat">{{ $t['kategori'] }}</span>@endif</span>
                            <span class="mds-rank__f">{{ $t['followers'] }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
            <div class="mds-card">
                <div class="mds-sec mds-sec--m0"><span class="mds-sec__ic" style="background:#E4ECFB;color:#3C5FA8;"><i class="fa fa-key"></i></span> Keyword SEO</div>
                <div class="mds-twostat">
                    <div class="mds-twostat__b"><div class="mds-twostat__n" style="color:#2F8E55;">{{ $keywords['active'] }}</div><div class="mds-twostat__l">Aktif</div></div>
                    <div class="mds-twostat__b"><div class="mds-twostat__n" style="color:#A9866A;">{{ $keywords['expired'] }}</div><div class="mds-twostat__l">Kadaluarsa</div></div>
                </div>
                @if ($keywords['expired'] > 0 && $keywords['active'] === 0)
                    <div class="mds-note"><i class="fa fa-exclamation-circle"></i> Semua keyword kadaluarsa — perlu diperbarui.</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ════ TIER 3: Funnel Konversi | Funnel Retensi ════ --}}
    <div class="mds-grid mds-grid--t3">
        @include('dashboard._funnel', ['funnel' => $convFunnel, 'fid' => 'conv',
            'title' => 'Corong Konversi', 'sub' => 'reservasi sistem · ' . $monthLabel, 'icon' => 'fa-filter', 'tint' => '#FBE7D2', 'color' => '#C0791F',
            'note' => 'Basis: reservasi via sistem Data Center. Beda dari "Kunjungan" di Retensi yang mencakup SEMUA pasien.'])
        @include('dashboard._funnel', ['funnel' => $retFunnel, 'fid' => 'ret',
            'title' => 'Corong Retensi', 'sub' => 'semua pengunjung · ' . $monthLabel, 'icon' => 'fa-redo', 'tint' => '#E2F1EA', 'color' => '#2F8E55',
            'note' => 'Basis: semua pasien yang berkunjung (termasuk walk-in & tanpa reservasi sistem).'])
    </div>

    {{-- ════ TIER 4: Produksi Konten | Atribusi ════ --}}
    <div class="mds-grid mds-grid--t4">
        {{-- PRODUKSI KONTEN --}}
        <div class="mds-card">
            <div class="mds-sec"><span class="mds-sec__ic" style="background:#E2F1EA;color:#2C8C63;"><i class="fa fa-images"></i></span>
                Produksi Konten <span class="mds-sec__sub">— {{ $content['total'] }} konten</span></div>
            @if ($content['total'] > 0)
                <div class="mds-statbars">
                    @foreach ($content['statuses'] as $st)
                        <div class="mds-statbar">
                            <div class="mds-statbar__head"><span>{{ $st['label'] }}</span><b>{{ $st['count'] }}</b></div>
                            <div class="mds-statbar__track"><div class="mds-statbar__fill" style="width:{{ $st['pct'] }}%;background:{{ $st['color'] }};"></div></div>
                        </div>
                    @endforeach
                </div>
                @if (! empty($content['tipe']))
                    <div class="mds-tags">
                        @foreach ($content['tipe'] as $t)<span class="mds-tag">{{ $t['label'] }} <b>{{ $t['count'] }}</b></span>@endforeach
                    </div>
                @endif
            @else
                <div class="mds-empty">Belum ada konten editorial.</div>
            @endif
        </div>

        {{-- ATRIBUSI --}}
        @unless ($attribution['has_data'])
            <div class="mds-card mds-card--ghost">
                <div class="mds-sec mds-sec--m0"><span class="mds-sec__ic" style="background:#EFE6DD;color:#9A7256;"><i class="fa fa-link"></i></span> Atribusi Referral</div>
                <div class="mds-ghost">
                    <div class="mds-ghost__ic"><i class="fa fa-unlink"></i></div>
                    <div class="mds-ghost__t">Belum ada data atribusi</div>
                    <div class="mds-ghost__s">{{ $attribution['promo_codes'] }} kode promo terdaftar, tapi reservasi belum mencatat kode/komisi. Aktifkan pencatatan kode saat reservasi — section ini otomatis terisi.</div>
                </div>
            </div>
        @else
            <div class="mds-card">
                <div class="mds-sec mds-sec--m0"><span class="mds-sec__ic" style="background:#E2F1EA;color:#2F8E55;"><i class="fa fa-link"></i></span> Atribusi Referral</div>
                <div class="mds-twostat">
                    <div class="mds-twostat__b"><div class="mds-twostat__n">{{ $attribution['resv_promo'] }}</div><div class="mds-twostat__l">Reservasi pakai promo</div></div>
                    <div class="mds-twostat__b"><div class="mds-twostat__n">{{ $attribution['commission'] }}</div><div class="mds-twostat__l">Komisi tercatat</div></div>
                </div>
            </div>
        @endunless
    </div>
</div>

{{-- Promo detail modal (dipakai partial _promo_card lewat data-* atribut) --}}
<div class="sds-pm" id="sdsPromoModal" hidden>
    <div class="sds-pm__backdrop" data-close></div>
    <div class="sds-pm__box" role="dialog" aria-modal="true" aria-labelledby="pmName">
        <div class="sds-pm__head" id="pmHead">
            <button class="sds-pm__x" data-close aria-label="Tutup">&times;</button>
            <div class="sds-pm__headtop">
                <span class="sds-pm__camp"><i class="fa" id="pmIcon"></i><span id="pmCamp"></span></span>
                <span class="sds-pm__status" id="pmStatus"></span>
            </div>
            <div class="sds-pm__name" id="pmName"></div>
        </div>
        <div class="sds-pm__body-wrap">
            <div class="sds-pm__price">
                <span class="sds-pm__now" id="pmNow"></span>
                <span class="sds-pm__old" id="pmOld"></span>
                <span class="sds-pm__hemat" id="pmHemat"></span>
            </div>
            <div class="sds-pm__sec" id="pmDetailWrap">
                <div class="sds-pm__lbl"><i class="fa fa-info-circle"></i> Detail</div>
                <div class="sds-pm__body" id="pmDetail"></div>
            </div>
            <div class="sds-pm__sec sds-pm__sec--sk" id="pmSkWrap">
                <div class="sds-pm__lbl"><i class="fa fa-shield-alt"></i> Syarat &amp; Ketentuan</div>
                <div class="sds-pm__body" id="pmSk"></div>
            </div>
            <div class="sds-pm__sec" id="pmCetakanWrap">
                <div class="sds-pm__lbl"><i class="fa fa-print"></i> Cetakan</div>
                <div class="sds-pm__body" id="pmCetakan"></div>
            </div>
            <div class="sds-pm__foot">
                <span class="sds-pm__date" id="pmDateWrap"><i class="fa fa-calendar-alt"></i> <span id="pmDate"></span></span>
                <button class="sds-pm__copy" id="pmCopy" type="button"><i class="fa fa-copy"></i> <span id="pmCopyTxt">Salin info</span></button>
            </div>
        </div>
    </div>
</div>

<style>
    .mds { font-family:'Plus Jakarta Sans', system-ui, sans-serif; color:#3B1500; font-variant-numeric:tabular-nums; padding:14px 26px 30px; }
    .mds h1,.mds h2,.mds h3,.mds h4,.mds h5,.mds p,.mds span:not([class*="fa"]),.mds div,.mds a,.mds text { font-family:'Plus Jakarta Sans', system-ui, sans-serif; }

    .mds-top { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:10px; margin-bottom:18px; }
    .mds-h { font-size:20px; font-weight:800; margin:0; }
    .mds-h__sub { font-size:12.5px; color:#9A7256; margin-top:2px; }
    .mds-filterbar { display:flex; gap:8px; flex-wrap:wrap; }
    .mds-input { border:1px solid #E7C9AC; border-radius:10px; padding:7px 11px; font-size:13px; color:#3B1500; background:#fff; }
    .mds-btn { background:linear-gradient(135deg,#6B2400,#461500); color:#fff; border:none; border-radius:10px; padding:8px 15px; font-size:13px; font-weight:600; cursor:pointer; }
    .mds-btn i { margin-right:5px; }

    /* KPI */
    .mds-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-bottom:18px; }
    .mds-kpi { background:#fff; border:1px solid #F0E2D6; border-radius:18px; padding:18px; box-shadow:0 1px 2px rgba(70,21,0,.04),0 10px 26px rgba(70,21,0,.05); }
    .mds-chip { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:18px; flex:0 0 auto; margin-bottom:13px; }
    .mds-kpi__label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:#9A7256; }
    .mds-kpi__val { font-size:27px; font-weight:800; line-height:1.15; margin:3px 0; }
    .mds-kpi__sub { font-size:11.5px; color:#9A7256; }
    .mds-kpi__sub.is-ok { color:#2F8E55; } .mds-kpi__sub.is-warn { color:#C0791F; }

    /* Grid tiers */
    .mds-grid { display:grid; gap:18px; align-items:start; margin-bottom:18px; }
    .mds-grid--t1 { grid-template-columns:minmax(0,1.8fr) minmax(280px,1fr); }  /* Promo lebar | Sumber sempit */
    .mds-grid--t1 > .mds-card:nth-child(1) { order:2; }  /* Sumber Pasien → kanan (sempit) */
    .mds-grid--t1 > .mds-card:nth-child(2) { order:1; }  /* Promo → kiri (lebar) */
    .mds-grid--t1 .sds-promo-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
    /* Kolom sumber pasien sempit → donut di atas, legend di bawah (stack) */
    .mds-grid--t1 .mds-donutwrap { flex-direction:column; align-items:center; gap:16px; }
    .mds-grid--t1 .mds-legend { width:100%; }
    .mds-grid--t2 { grid-template-columns:minmax(0,2fr) minmax(280px,1fr); }
    .mds-grid--t3 { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .mds-grid--t4 { grid-template-columns:repeat(2,minmax(0,1fr)); align-items:stretch; }
    .mds-col { display:flex; flex-direction:column; gap:18px; }
    @media (max-width:1100px){
        .mds-grid--t1,.mds-grid--t2,.mds-grid--t3,.mds-grid--t4 { grid-template-columns:1fr; }
        .mds-kpis { grid-template-columns:repeat(2,1fr); }
    }

    .mds-card { background:#fff; border:1px solid #F0E2D6; border-radius:20px; padding:20px 22px; box-shadow:0 1px 2px rgba(70,21,0,.04),0 10px 26px rgba(70,21,0,.05); }
    .mds-card--ghost { background:#FCFAF7; border-style:dashed; box-shadow:none; }
    .mds-sec { display:flex; align-items:center; gap:10px; font-size:15px; font-weight:800; margin-bottom:16px; }
    .mds-sec--m0 { margin-bottom:0; }
    .mds-sec--split { justify-content:space-between; }
    .mds-sec--split > span:first-child { display:flex; align-items:center; gap:10px; }
    .mds-sec__ic { width:30px; height:30px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; font-size:14px; flex:0 0 auto; }
    .mds-sec__sub { font-size:12px; font-weight:500; color:#A9866A; }
    .mds-empty { text-align:center; color:#A9866A; font-size:13px; padding:22px 0; }
    .mds-subhead { font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#A9866A; margin:16px 0 9px; }
    .mds-toggle { display:inline-flex; border:1px solid #EAD9C7; border-radius:9px; overflow:hidden; }
    .mds-toggle a { font-size:11.5px; font-weight:700; color:#9A7256; padding:5px 11px; text-decoration:none; }
    .mds-toggle a.is-on { background:#6B2400; color:#fff; }

    /* DONUT sumber pasien */
    .mds-donutwrap { display:flex; align-items:center; gap:22px; flex-wrap:wrap; }
    .mds-donut { flex:0 0 auto; }
    .mds-donut svg { width:130px; height:130px; }
    .mds-donut__n { fill:#3B1500; font-size:20px; font-weight:800; }
    .mds-donut__l { fill:#A9866A; font-size:8px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; }
    .mds-legend { flex:1; min-width:180px; display:flex; flex-direction:column; gap:9px; }
    .mds-legend__row { display:flex; align-items:center; gap:9px; font-size:12.5px; }
    .mds-legend__dot { width:11px; height:11px; border-radius:3px; flex:0 0 auto; }
    .mds-legend__lbl { flex:1; color:#3B1500; font-weight:600; }
    .mds-legend__pct { font-weight:800; color:#3B1500; }
    .mds-legend__n { width:54px; text-align:right; color:#A9866A; font-size:11.5px; }

    /* CHART stacked */
    .mds-chartwrap { position:relative; }
    .mds-chart { width:100%; height:auto; display:block; }
    .mds-hit { cursor:pointer; }
    .mds-hit:hover { fill:rgba(142,79,168,.07); }
    .mds-axis { fill:#A9866A; font-size:9px; }
    .mds-clegend { display:flex; gap:18px; font-size:12px; color:#7A5C45; margin-top:8px; }
    .mds-sw { display:inline-block; width:11px; height:11px; border-radius:3px; vertical-align:middle; margin-right:5px; }
    .mds-tip { position:absolute; display:none; transform:translate(-50%,-100%); background:#3B1500; color:#fff;
        font-size:11px; line-height:1.55; padding:8px 11px; border-radius:9px; white-space:nowrap; pointer-events:none;
        box-shadow:0 8px 22px rgba(0,0,0,.22); z-index:6; }
    .mds-tip b { font-size:12px; font-weight:700; }
    .mds-tip .kb { color:#D7B6E6; } .mds-tip .kl { color:#E0CBB6; }

    /* FUNNEL */
    .mds-funnel { margin-top:6px; }
    .mds-funnel__svg { width:100%; height:auto; display:block; }
    .mds-funnel__rate { margin-left:auto; font-size:15px; font-weight:800; }
    .mds-funnel__n { fill:#3B1500; font-size:13px; font-weight:800; }
    .mds-funnel__pct { fill:#3B1500; font-size:11px; font-weight:800; }
    .mds-funnel__lbl { fill:#3B1500; font-size:11px; font-weight:700; }
    .mds-funnel__sub { fill:#A9866A; font-size:8.5px; }
    .mds-funnel__note { font-size:11px; color:#A9866A; margin-top:10px; padding-top:10px; border-top:1px solid #F4ECE2; display:flex; align-items:flex-start; gap:7px; line-height:1.5; }
    .mds-funnel__note i { color:#C0791F; margin-top:1px; }

    /* STAT BARS + tags */
    .mds-statbars { display:flex; flex-direction:column; gap:11px; }
    .mds-statbar__head { display:flex; justify-content:space-between; font-size:12.5px; color:#5A4636; margin-bottom:4px; }
    .mds-statbar__head b { color:#3B1500; font-weight:800; }
    .mds-statbar__track { height:7px; border-radius:5px; background:#F4ECE2; overflow:hidden; }
    .mds-statbar__fill { height:100%; border-radius:5px; }
    .mds-tags { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
    .mds-tag { font-size:11.5px; color:#5A4636; background:#F7F0E8; border:1px solid #F0E2D6; border-radius:20px; padding:4px 11px; }
    .mds-tag b { color:#3B1500; font-weight:800; margin-left:2px; }

    /* TWO STAT + rank */
    .mds-twostat { display:flex; gap:12px; margin-top:14px; }
    .mds-twostat__b { flex:1; text-align:center; background:#FFFCF9; border:1px solid #F0E2D6; border-radius:14px; padding:14px 8px; }
    .mds-twostat__n { font-size:26px; font-weight:800; line-height:1; }
    .mds-twostat__l { font-size:11px; font-weight:600; color:#A9866A; margin-top:5px; }
    .mds-rank { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #F4ECE2; font-size:13px; }
    .mds-rank:last-child { border-bottom:0; }
    .mds-rank__name { font-weight:600; }
    .mds-rank__kat { display:block; font-size:10.5px; font-weight:500; color:#A9866A; margin-top:1px; }
    .mds-rank__f { font-size:12.5px; font-weight:800; color:#C0791F; white-space:nowrap; }

    /* NOTE + GHOST */
    .mds-note { font-size:11.5px; color:#C0791F; margin-top:12px; display:flex; align-items:center; gap:7px; }
    .mds-ghost { text-align:center; padding:10px 6px 4px; }
    .mds-ghost__ic { width:42px; height:42px; border-radius:50%; background:#EFE6DD; color:#B79A82; display:flex; align-items:center; justify-content:center; font-size:17px; margin:0 auto 10px; }
    .mds-ghost__t { font-size:13px; font-weight:700; color:#7A5C45; }
    .mds-ghost__s { font-size:11px; color:#A9866A; margin-top:5px; line-height:1.55; }

    /* PROMO grid + card (partial _promo_card, kelas .sds-*) */
    .sds-promo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:12px; margin-top:2px; }
    .sds-promo { border:1px solid #F0E2D6; border-left:4px solid var(--cc,#7A3415); border-radius:14px; padding:13px 15px; background:#FFFDFB; cursor:pointer; transition:box-shadow .12s,transform .12s; }
    .sds-promo:hover { box-shadow:0 8px 22px rgba(40,22,8,.12); transform:translateY(-2px); }
    .sds-promo:focus-visible { outline:2px solid var(--cc); outline-offset:2px; }
    .sds-promo__camp { font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.7px; }
    .sds-promo__name { font-size:14px; font-weight:700; color:#3B1500; margin:4px 0 8px; line-height:1.25; min-height:36px; }
    .sds-promo--compact .sds-promo__name { min-height:0; margin-bottom:6px; }
    .sds-promo__now { font-size:17px; font-weight:800; color:#3B1500; }
    .sds-promo__pr { display:flex; align-items:center; gap:8px; margin-top:3px; }
    .sds-promo__save { font-size:10px; font-weight:700; color:#2F8E55; background:#E2F1EA; padding:1px 7px; border-radius:20px; }
    .sds-promo__old { font-size:11px; color:#B79A82; text-decoration:line-through; }
    .sds-promo__until { font-size:10.5px; color:#A9866A; margin-top:8px; }
    .sds-promo__until--soon { color:#3C5FA8; font-weight:700; }
    .sds-promo__until i { margin-right:3px; }
    .sds-promo__text { font-size:10.5px; line-height:1.45; color:#6a615a; margin-top:7px; }
    .sds-promo__text b { display:block; font-size:8.5px; font-weight:800; text-transform:uppercase; letter-spacing:.4px; color:#b3a79e; margin-bottom:1px; }
    .sds-promo__text span { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .sds-promo__text--sk span { font-style:italic; color:#9a8f86; -webkit-line-clamp:1; }
    .sds-muted { color:#B79A82; }

    /* PROMO detail modal */
    .sds-pm[hidden] { display:none; }
    .sds-pm { position:fixed; inset:0; z-index:1000; display:flex; align-items:center; justify-content:center; padding:20px; }
    .sds-pm__backdrop { position:absolute; inset:0; background:rgba(40,22,8,.5); backdrop-filter:blur(3px); animation:sdsPmFade .2s ease; }
    .sds-pm__box { position:relative; background:#fff; border-radius:20px; max-width:440px; width:100%; box-shadow:0 28px 70px rgba(40,22,8,.34); max-height:88vh; overflow:hidden; display:flex; flex-direction:column; animation:sdsPmIn .24s cubic-bezier(.2,.8,.3,1.15); }
    @keyframes sdsPmFade { from{opacity:0} to{opacity:1} }
    @keyframes sdsPmIn { from{opacity:0;transform:translateY(14px) scale(.96)} to{opacity:1;transform:none} }
    .sds-pm__head { position:relative; padding:20px 22px 18px; color:#fff; background:var(--pmcc,#7A3415); }
    .sds-pm__head::after { content:''; position:absolute; inset:0; background:linear-gradient(135deg,rgba(255,255,255,.16),rgba(0,0,0,.24)); pointer-events:none; }
    .sds-pm__head > * { position:relative; z-index:1; }
    .sds-pm__headtop { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:9px; padding-right:30px; }
    .sds-pm__camp { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.7px; display:inline-flex; align-items:center; gap:7px; opacity:.96; }
    .sds-pm__status { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.4px; padding:3px 10px; border-radius:20px; background:rgba(255,255,255,.24); white-space:nowrap; }
    .sds-pm__name { font-size:20px; font-weight:800; line-height:1.22; }
    .sds-pm__x { position:absolute; top:12px; right:14px; z-index:2; border:0; width:28px; height:28px; border-radius:50%; background:rgba(255,255,255,.2); color:#fff; font-size:19px; line-height:1; cursor:pointer; transition:background .12s; }
    .sds-pm__x:hover { background:rgba(255,255,255,.4); }
    .sds-pm__body-wrap { padding:20px 22px; overflow:auto; }
    .sds-pm__price { display:flex; align-items:baseline; gap:11px; flex-wrap:wrap; }
    .sds-pm__now { font-size:27px; font-weight:800; color:#2c1a0e; letter-spacing:-.5px; }
    .sds-pm__old { color:#b3a79e; text-decoration:line-through; font-size:14px; }
    .sds-pm__hemat { background:#E9F6EC; color:#1f9d55; font-weight:800; font-size:12px; padding:4px 11px; border-radius:8px; flex-basis:100%; width:max-content; margin-top:3px; }
    .sds-pm__sec { margin-top:17px; }
    .sds-pm__sec--sk { background:#FBF7F2; border:1px solid #efe6db; border-radius:12px; padding:12px 14px; margin-top:14px; }
    .sds-pm__lbl { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:#b3a79e; margin-bottom:4px; display:flex; align-items:center; gap:6px; }
    .sds-pm__lbl i { color:#c9bbad; }
    .sds-pm__body { font-size:13px; line-height:1.6; color:#5a514a; white-space:pre-line; }
    .sds-pm__foot { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:20px; padding-top:15px; border-top:1px solid #f0e9e1; }
    .sds-pm__date { font-size:12.5px; font-weight:700; color:#A9866A; display:inline-flex; align-items:center; gap:7px; }
    .sds-pm__date.is-soon { color:#3C5FA8; }
    .sds-pm__copy { border:1px solid #e7ddd4; background:#fff; color:#7A3415; border-radius:10px; padding:8px 15px; font-size:12.5px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:7px; transition:all .14s; white-space:nowrap; }
    .sds-pm__copy:hover { background:#FBF6F0; border-color:#dcc9b8; }
    .sds-pm__copy.copied { background:#E9F6EC; color:#1f9d55; border-color:#bfe6cc; }

    /* Bar proporsi sumber pasien (isi ruang + warna) */
    .mds-srcbar { display:flex; height:12px; border-radius:7px; overflow:hidden; background:#F4ECE2; margin-top:20px; }
    .mds-srcbar__seg { height:100%; }
    .mds-srcnote { font-size:11.5px; color:#A9866A; margin-top:12px; display:flex; align-items:center; gap:7px; flex-wrap:wrap; }
    .mds-srcnote i { color:#C0791F; }
    .mds-srcnote b { font-weight:800; }

    /* ── Animasi ringan (CSS-only). Mati otomatis kalau user minta reduce-motion. ── */
    @media (prefers-reduced-motion: no-preference) {
        @keyframes mdsUp   { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:none; } }
        @keyframes mdsPop  { from { opacity:0; transform:rotate(-16deg) scale(.88); } to { opacity:1; transform:none; } }
        @keyframes mdsGrowW{ from { transform:scaleX(0); } to { transform:scaleX(1); } }
        @keyframes mdsGrowY{ from { transform:scaleY(0); } to { transform:scaleY(1); } }
        @keyframes mdsFade { from { opacity:0; } to { opacity:1; } }

        .mds-kpi, .mds-card { animation:mdsUp .5s cubic-bezier(.2,.7,.3,1) both; }
        .mds-kpis .mds-kpi:nth-child(2){ animation-delay:.06s; }
        .mds-kpis .mds-kpi:nth-child(3){ animation-delay:.12s; }
        .mds-kpis .mds-kpi:nth-child(4){ animation-delay:.18s; }
        .mds-grid .mds-card:nth-child(2){ animation-delay:.08s; }
        .mds-grid .mds-card:nth-child(3){ animation-delay:.16s; }

        .mds-donut svg { animation:mdsPop .7s ease both; transform-origin:center; }
        .mds-srcbar__seg, .mds-statbar__fill { transform-origin:left center; animation:mdsGrowW .8s cubic-bezier(.2,.7,.3,1) both; }
        .mds-gbar { transform-box:fill-box; transform-origin:bottom; animation:mdsGrowY .6s cubic-bezier(.2,.7,.3,1) both; }
        .mds-funnel__svg path { animation:mdsFade .9s ease both; }
    }
</style>

<script>
/* Hover tooltip grafik akuisisi */
(function () {
    var chart = document.getElementById('mdsAcq');
    if (!chart) return;
    var wrap = chart.closest('.mds-chartwrap'), tip = document.getElementById('mdsTip');
    chart.querySelectorAll('.mds-hit').forEach(function (c) {
        c.addEventListener('mouseenter', function () {
            tip.innerHTML = '<b>' + c.dataset.label + '</b>'
                + '<br><span class="kb">●</span> Baru: ' + c.dataset.baru
                + '<br><span class="kl">●</span> Lama: ' + c.dataset.lama;
            var cr = c.getBoundingClientRect(), wr = wrap.getBoundingClientRect();
            tip.style.left = (cr.left + cr.width / 2 - wr.left) + 'px';
            tip.style.top  = (wr.height * 0.18) + 'px';
            tip.style.display = 'block';
        });
        c.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
    });
})();

/* Promo detail modal */
(function () {
    var modal = document.getElementById('sdsPromoModal');
    if (!modal) return;
    var current = null;
    function setBlock(wrapId, bodyId, val) {
        document.getElementById(bodyId).textContent = val || '';
        document.getElementById(wrapId).style.display = val ? '' : 'none';
    }
    function resetCopy() {
        document.getElementById('pmCopy').classList.remove('copied');
        document.getElementById('pmCopyTxt').textContent = 'Salin info';
    }
    function openPromo(el) {
        current = el;
        var soon = el.dataset.soon === '1';
        document.getElementById('pmHead').style.setProperty('--pmcc', el.dataset.color || '#7A3415');
        document.getElementById('pmIcon').className = 'fa ' + (el.dataset.campIcon || 'fa-tags');
        document.getElementById('pmCamp').textContent = el.dataset.camp || '';
        document.getElementById('pmStatus').textContent = soon ? 'Akan Datang' : 'Aktif';
        document.getElementById('pmName').textContent = el.dataset.name || '';
        document.getElementById('pmNow').textContent = el.dataset.now || '—';
        var old = document.getElementById('pmOld');
        old.textContent = el.dataset.old || ''; old.style.display = el.dataset.old ? '' : 'none';
        var hemat = document.getElementById('pmHemat');
        if (el.dataset.saverp) {
            hemat.textContent = 'Hemat ' + el.dataset.saverp + (el.dataset.save ? '  ·  ' + el.dataset.save.replace('-', '') : '');
            hemat.style.display = '';
        } else { hemat.style.display = 'none'; }
        setBlock('pmDetailWrap', 'pmDetail', el.dataset.detail);
        setBlock('pmSkWrap', 'pmSk', el.dataset.sk);
        setBlock('pmCetakanWrap', 'pmCetakan', el.dataset.cetakan);
        document.getElementById('pmDate').textContent = el.dataset.date || '';
        document.getElementById('pmDateWrap').className = 'sds-pm__date' + (soon ? ' is-soon' : '');
        resetCopy();
        modal.hidden = false; document.body.style.overflow = 'hidden';
    }
    function closePromo() { modal.hidden = true; document.body.style.overflow = ''; }
    function markCopied() {
        document.getElementById('pmCopy').classList.add('copied');
        document.getElementById('pmCopyTxt').textContent = 'Tersalin!';
    }
    function copyInfo() {
        if (!current) return;
        var d = current.dataset, lines = [d.name];
        if (d.now) lines.push('Harga: ' + d.now + (d.old ? ' (dari ' + d.old + ')' : ''));
        if (d.saverp) lines.push('Hemat: ' + d.saverp);
        if (d.detail) lines.push('', 'Detail:', d.detail);
        if (d.sk) lines.push('', 'S&K:', d.sk);
        if (d.cetakan) lines.push('', 'Cetakan:', d.cetakan);
        if (d.date) lines.push('', 'Periode: ' + d.date);
        var text = lines.join('\n');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(markCopied, function () { fallbackCopy(text); });
        } else { fallbackCopy(text); }
    }
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); markCopied(); } catch (e) {}
        document.body.removeChild(ta);
    }
    document.querySelectorAll('[data-promo]').forEach(function (c) {
        c.addEventListener('click', function () { openPromo(c); });
        c.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPromo(c); }
        });
    });
    document.getElementById('pmCopy').addEventListener('click', copyInfo);
    modal.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', closePromo); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closePromo(); });
})();
</script>
@endsection
