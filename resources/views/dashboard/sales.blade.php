@extends('layouts.app')

@section('content')
@php
    $ccMap      = \App\Models\MarketingPromo::campaignColors();
    $campLabels = \App\Models\MarketingPromo::campaignTypes();
    $rp = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $donutR = 58; $donutC = 2 * M_PI * $donutR;
    $donutArc = $donutC * ($target['has_target'] ? $target['pct_clamped'] : 0) / 100;
@endphp
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<div class="sds">
    {{-- ── Header ── --}}
    <div class="sds-top">
        <div>
            <h4 class="sds-h">Dashboard Sales</h4>
            <div class="sds-h__sub">{{ $branchName }} · {{ $dayLabel }}{{ $isToday ? ' (Hari Ini)' : '' }}</div>
        </div>
        <form class="sds-datebar" method="GET">
            <input type="date" name="date" value="{{ $anchor }}" class="sds-date">
            <button class="sds-btn"><i class="fa fa-eye"></i> Lihat</button>
        </form>
    </div>

    {{-- ════ KPI strip ════ --}}
    <div class="sds-kpis">
        @php
            $deltaTxt = $kpi['revenue_today'] == 0
                ? 'belum ada transaksi hari ini'
                : ($kpi['revenue_delta'] >= 0 ? '▲ ' : '▼ ') . $rp(abs($kpi['revenue_delta'])) . ' dari kemarin';
        @endphp
        <div class="sds-kpi">
            <div class="sds-chip" style="background:#FBE7D2;color:#C0791F;"><i class="fa fa-coins"></i></div>
            <div class="sds-kpi__label">Penjualan Hari Ini</div>
            <div class="sds-kpi__val">{{ $rp($kpi['revenue_today']) }}</div>
            <div class="sds-kpi__sub {{ $kpi['revenue_today'] > 0 && $kpi['revenue_delta'] >= 0 ? 'is-ok' : ($kpi['revenue_delta'] < 0 ? 'is-warn' : '') }}">{{ $deltaTxt }}</div>
        </div>
        <div class="sds-kpi">
            <div class="sds-chip" style="background:#E4ECFB;color:#3C5FA8;"><i class="fa fa-calendar-alt"></i></div>
            <div class="sds-kpi__label">Reservasi Hari Ini</div>
            <div class="sds-kpi__val">{{ $kpi['reservasi_today'] }}</div>
            <div class="sds-kpi__sub is-warn">{{ $kpi['reservasi_pending'] }} belum dikonfirmasi</div>
        </div>
        <div class="sds-kpi">
            <div class="sds-chip" style="background:#F3E6F7;color:#8E4FA8;"><i class="fa fa-user-plus"></i></div>
            <div class="sds-kpi__label">Pasien Baru Bulan Ini</div>
            <div class="sds-kpi__val">{{ $kpi['pasien_baru_mtd'] }}</div>
            <div class="sds-kpi__sub">pasien yang baru pertama datang</div>
        </div>
        <div class="sds-kpi">
            <div class="sds-chip" style="background:#E2F1EA;color:#2F8E55;"><i class="fa fa-heartbeat"></i></div>
            <div class="sds-kpi__label">Kunjungan Bulan Ini</div>
            <div class="sds-kpi__val">{{ $kpi['kunjungan_mtd'] }}</div>
            <div class="sds-kpi__sub">total kunjungan pasien</div>
        </div>
    </div>

    {{-- ════ Main grid ════ --}}
    <div class="sds-grid">
        {{-- ─────────── KIRI ─────────── --}}
        <div class="sds-main">
            {{-- HERO: pencapaian + donut --}}
            <div class="sds-hero">
                <div class="sds-hero__l">
                    <div class="sds-hero__eyebrow">
                        Pencapaian {{ $target['month_label'] }}
                        @if ($target['has_target'])
                            <span class="sds-pill {{ $target['on_track'] ? 'is-ok' : 'is-behind' }}">
                                <i class="fa {{ $target['on_track'] ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>
                                {{ $target['on_track'] ? 'Sesuai target' : 'Di bawah target' }}
                            </span>
                        @endif
                    </div>
                    <div class="sds-hero__amount">{{ $rp($target['actual']) }}</div>
                    @if ($target['has_target'])
                        <div class="sds-hero__target">dari target <b>{{ $rp($target['target']) }}</b></div>
                        <div class="sds-hero__row">
                            <span>Kurang <b>{{ $rp($target['remaining']) }}</b> lagi</span>
                            <span class="sds-dot">·</span>
                            <span>Target s/d hari ini: <b>{{ $rp($target['expected']) }}</b></span>
                        </div>
                    @else
                        <div class="sds-hero__target sds-hero__target--warn">
                            <i class="fa fa-exclamation-triangle"></i> Target belum diisi — minta Finance set di admin
                        </div>
                    @endif
                </div>
                <div class="sds-hero__r">
                    <svg viewBox="0 0 150 150" class="sds-donut">
                        <circle cx="75" cy="75" r="{{ $donutR }}" fill="none" stroke="rgba(255,250,245,.13)" stroke-width="14"/>
                        @if ($target['has_target'])
                        <circle cx="75" cy="75" r="{{ $donutR }}" fill="none" stroke="{{ $target['on_track'] ? '#5FCB86' : '#E6A93D' }}" stroke-width="14"
                            stroke-linecap="round" stroke-dasharray="{{ round($donutArc,1) }} {{ round($donutC,1) }}" transform="rotate(-90 75 75)"/>
                        @endif
                        <text x="75" y="71" text-anchor="middle" class="sds-donut__pct">{{ $target['has_target'] ? number_format($target['attainment'],1).'%' : '—' }}</text>
                        <text x="75" y="90" text-anchor="middle" class="sds-donut__lbl">DARI TARGET</text>
                    </svg>
                </div>
            </div>

            {{-- PROMO (aktif; kalau kosong → fallback "akan datang") --}}
            @php $promoActive = $activePromos->isNotEmpty(); $promoList = $promoActive ? $activePromos : $upcomingPromos; @endphp
            <div class="sds-card">
                @if ($promoActive)
                    <div class="sds-sec"><span class="sds-sec__ic" style="background:#F4E7D8;color:#7A3415;"><i class="fa fa-tags"></i></span>
                        Promo Aktif <span class="sds-sec__sub">— bisa ditawarkan ke pasien buat ngejar target</span></div>
                @elseif ($promoList->isNotEmpty())
                    <div class="sds-sec"><span class="sds-sec__ic" style="background:#E4ECFB;color:#3C5FA8;"><i class="fa fa-clock"></i></span>
                        Promo Akan Datang <span class="sds-sec__sub">— belum aktif, siap-siap tawarin pas mulai</span></div>
                @else
                    <div class="sds-sec"><span class="sds-sec__ic" style="background:#F4E7D8;color:#7A3415;"><i class="fa fa-tags"></i></span>
                        Promo Aktif <span class="sds-sec__sub">— bisa ditawarkan ke pasien buat ngejar target</span></div>
                @endif

                @if ($promoList->isEmpty())
                    <div class="sds-empty">Belum ada promo aktif atau akan datang untuk cabang ini.</div>
                @else
                    <div class="sds-promo-grid">
                        @foreach ($promoList as $p)
                            @php
                                $cc = $ccMap[$p->campaign_type] ?? ['color' => '#7A3415', 'bg' => '#F4E7D8'];
                                $diskon = ($p->harga_normal && $p->harga_promo && $p->harga_normal > $p->harga_promo)
                                    ? (int) round((($p->harga_normal - $p->harga_promo) / $p->harga_normal) * 100) : null;
                            @endphp
                            <div class="sds-promo" style="--cc: {{ $cc['color'] }};">
                                <div class="sds-promo__camp" style="color: {{ $cc['color'] }};">{{ $campLabels[$p->campaign_type] ?? $p->campaign_type }}</div>
                                <div class="sds-promo__name">{{ $p->nama_promo }}</div>
                                @if ($p->harga_promo)
                                    <div class="sds-promo__now">{{ $rp($p->harga_promo) }}</div>
                                    <div class="sds-promo__pr">
                                        @if ($diskon)<span class="sds-promo__save">-{{ $diskon }}%</span>@endif
                                        @if ($p->harga_normal && $p->harga_normal > $p->harga_promo)<span class="sds-promo__old">{{ $rp($p->harga_normal) }}</span>@endif
                                    </div>
                                @else <div class="sds-promo__now sds-muted">—</div> @endif
                                @if ($promoActive)
                                    @if ($p->periode_end)
                                        <div class="sds-promo__until"><i class="fa fa-clock"></i> s/d {{ \Carbon\Carbon::parse($p->periode_end)->format('d M Y') }}</div>
                                    @endif
                                @else
                                    @if ($p->periode_start)
                                        <div class="sds-promo__until sds-promo__until--soon"><i class="fa fa-clock"></i> mulai {{ \Carbon\Carbon::parse($p->periode_start)->format('d M Y') }}</div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- GRAFIK penjualan harian (batang per tanggal, hover munculin angka) --}}
            <div class="sds-card">
                <div class="sds-sec"><span class="sds-sec__ic" style="background:#E2F1EA;color:#2C8C63;"><i class="fa fa-chart-bar"></i></span>
                    Penjualan Harian <span class="sds-sec__sub">— berapa penjualan tiap tanggal bulan ini</span></div>
                @if ($trend['has_data'])
                    <div class="sds-chartwrap">
                        <svg id="sdsChart" viewBox="0 0 {{ $trend['width'] }} {{ $trend['height'] }}" class="sds-chart">
                            @foreach ($trend['grid'] as $g)
                                <line x1="6" y1="{{ $g['y'] }}" x2="{{ $trend['width']-6 }}" y2="{{ $g['y'] }}" stroke="#EFE2D6" stroke-width="1"/>
                                <text x="8" y="{{ $g['y']-4 }}" class="sds-axis">{{ $g['label'] }}</text>
                            @endforeach
                            @foreach ($trend['bars'] as $b)
                                <rect class="sds-bar {{ $b['today'] ? 'is-today' : '' }}" x="{{ $b['x'] }}" y="{{ $b['y'] }}" width="{{ $b['w'] }}" height="{{ $b['h'] }}" rx="2"
                                    data-label="{{ $b['label'] }}" data-val="{{ $b['val'] }}"></rect>
                            @endforeach
                            @if ($trend['has_target'])
                                <line x1="6" y1="{{ $trend['target_y'] }}" x2="{{ $trend['width']-6 }}" y2="{{ $trend['target_y'] }}" stroke="#C9962F" stroke-width="1.4" stroke-dasharray="6 5"/>
                                <text x="{{ $trend['width']-8 }}" y="{{ $trend['target_y']-4 }}" text-anchor="end" class="sds-axis" fill="#C0791F">{{ $trend['target_label'] }}</text>
                            @endif
                            @foreach ($trend['xticks'] as $t)
                                <text x="{{ $t['x'] }}" y="{{ $trend['height']-6 }}" text-anchor="middle" class="sds-axis">{{ $t['label'] }}</text>
                            @endforeach
                        </svg>
                        <div class="sds-tip" id="sdsTip"></div>
                    </div>
                    <div class="sds-legend">
                        <span><i class="sds-lg sds-lg--bar"></i> Penjualan per hari</span>
                        <span><i class="sds-lg sds-lg--dash"></i> Target per hari</span>
                    </div>
                @else
                    <div class="sds-empty">Belum ada transaksi bulan ini.</div>
                @endif
            </div>

            {{-- JADWAL DOKTER --}}
            <div class="sds-card">
                <div class="sds-sec"><span class="sds-sec__ic" style="background:#E4ECFB;color:#3C5FA8;"><i class="fa fa-user-md"></i></span>
                    Jadwal Dokter <span class="sds-sec__sub">— {{ $dayLabel }}</span></div>
                @forelse ($doctorSchedule as $branchLabel => $docs)
                    @if ($scopeAll)<div class="sds-doc-branch"><i class="fa fa-map-marker-alt"></i> {{ $branchLabel }}</div>@endif
                    <div class="sds-doc-grid">
                        @foreach ($docs as $doc)
                            <div class="sds-doc {{ $doc->on_leave ? 'is-leave' : '' }}">
                                <div class="sds-doc__av">{{ strtoupper(mb_substr(preg_replace('/^drg?\.?\s*/i','',$doc->doctor_name),0,1)) }}</div>
                                <div class="sds-doc__name">{{ $doc->doctor_name }}</div>
                                <span class="sds-doc__time {{ $doc->on_leave ? 'is-leave' : '' }}">{{ $doc->on_leave ? 'Cuti' : $doc->start_hm.'–'.$doc->end_hm }}</span>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <div class="sds-empty">Tidak ada dokter praktik pada {{ $dayLabel }}.</div>
                @endforelse
            </div>
        </div>

        {{-- ─────────── KANAN ─────────── --}}
        <div class="sds-side">
            {{-- RESERVASI BARU + bar/hari --}}
            <div class="sds-card">
                <div class="sds-side-head">
                    <div class="sds-sec sds-sec--m0"><span class="sds-sec__ic" style="background:#FBE7D2;color:#C0791F;"><i class="fa fa-bell"></i></span> Reservasi Baru</div>
                    <a href="{{ url('rawreservation') }}" class="sds-arrow"><i class="fa fa-arrow-right"></i></a>
                </div>
                <div class="sds-big" style="color:#C0791F;">{{ $reservasiBaru['count'] }} <span>belum dikonfirmasi</span></div>

                <div class="sds-bars">
                    <div class="sds-bars__lbl">Reservasi 5 hari ke depan</div>
                    <div class="sds-bars__row">
                        @foreach ($reservasiBar['days'] as $i => $bd)
                            @php $h = $bd['count'] === 0 ? 5 : max(22, round($bd['count'] / $reservasiBar['max'] * 100)); @endphp
                            <div class="sds-bars__col">
                                <div class="sds-bars__num {{ $bd['count'] === 0 ? 'is-zero' : '' }}">{{ $bd['count'] }}</div>
                                <div class="sds-bars__track">
                                    <div class="sds-bars__fill {{ $i === 0 ? 'is-today' : '' }} {{ $bd['count'] === 0 ? 'is-empty' : '' }}" style="height: {{ $h }}%;" title="{{ $bd['count'] }} reservasi"></div>
                                </div>
                                <div class="sds-bars__day"><span class="sds-bars__dow">{{ $bd['dow'] }}</span><br>{{ $bd['label'] }} {{ $bd['mon'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($reservasiBaru['latest']->isNotEmpty())
                    <div class="sds-rule"></div>
                    @foreach ($reservasiBaru['latest'] as $r)
                        <div class="sds-resv">
                            <span class="sds-resv__l"><span class="sds-resv__dot"></span>{{ $r->customer_name ?? 'Tanpa nama' }}</span>
                            <span class="sds-resv__t">{{ \Carbon\Carbon::parse($r->date)->format('d M') }} · {{ substr((string)$r->start_hour,0,5) }}</span>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- BUTUH PERHATIAN --}}
            <div class="sds-card">
                <div class="sds-sec sds-sec--m0"><span class="sds-sec__ic" style="background:#F7E3EC;color:#B14A78;"><i class="fa fa-exclamation-triangle"></i></span> Butuh Perhatian</div>
                @php $adaMasalah = !empty($actions['dokter_cuti']) || !empty($actions['stale']); @endphp
                @if (! $adaMasalah)
                    <div class="sds-allok">
                        <div class="sds-allok__ic"><i class="fa fa-check"></i></div>
                        <div class="sds-allok__t">Semua aman hari ini</div>
                        <div class="sds-allok__s">Tidak ada reservasi terlewat atau target kritis</div>
                    </div>
                @else
                    <div style="margin-top:10px;">
                        @if (!empty($actions['dokter_cuti']))
                            <div class="sds-alert"><span class="sds-tag-red">CUTI</span> {{ implode(', ', $actions['dokter_cuti']) }}</div>
                        @endif
                        @foreach ($actions['stale'] as $s)
                            <div class="sds-alert sds-alert--muted"><i class="fa fa-database"></i> {{ $s['name'] }}: data Omnicare {{ $s['days'] === null ? 'belum ada' : round($s['days']).' hari telat' }}</div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- AKSI CEPAT (kontekstual) --}}
            <div class="sds-card">
                <div class="sds-sec sds-sec--m0"><span class="sds-sec__ic" style="background:#F4E7D8;color:#7A3415;"><i class="fa fa-bolt"></i></span>
                    Aksi Cepat <span class="sds-sec__sub">— prioritas hari ini</span></div>
                <div class="sds-aksi-list">
                    @foreach ($aksiCepat as $a)
                        <a href="{{ $a['url'] }}" class="sds-aksi">
                            <div class="sds-chip sds-chip--sm" style="background:{{ $a['tint'] }};color:{{ $a['color'] }};"><i class="fa {{ $a['icon'] }}"></i></div>
                            <div class="sds-aksi__body">
                                <div class="sds-aksi__t">{{ $a['title'] }}</div>
                                <div class="sds-aksi__s">{{ $a['sub'] }}</div>
                            </div>
                            @if ($a['badge_type'] === 'amber')
                                <span class="sds-aksi__badge sds-aksi__badge--amber">{{ $a['badge'] }}</span>
                            @elseif ($a['badge_type'] === 'green')
                                <span class="sds-aksi__badge sds-aksi__badge--green">{{ $a['badge'] }}</span>
                            @else
                                <i class="fa fa-arrow-right sds-aksi__arrow"></i>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Plus Jakarta Sans utk teks dashboard. JANGAN pakai `* { font-family:inherit }`
       — itu nimpa font FontAwesome di ikon (jadi kotak kosong). Cukup set di .sds,
       teks turun via inheritance; .fa tetap pakai font ikonnya sendiri. */
    .sds { font-family:'Plus Jakarta Sans', system-ui, sans-serif; color:#3B1500; font-variant-numeric:tabular-nums; padding:14px 26px 30px; }
    .sds h1,.sds h2,.sds h3,.sds h4,.sds h5,.sds p,.sds span:not([class*="fa"]),.sds div,.sds a,.sds text { font-family:'Plus Jakarta Sans', system-ui, sans-serif; }

    /* Chart interaktif */
    .sds-chartwrap { position:relative; }
    .sds-dot { cursor:pointer; transition:fill .1s; }
    .sds-dot:hover { fill:rgba(44,140,99,.20); stroke:#2C8C63; stroke-width:1.5; }
    .sds-tip { position:absolute; display:none; transform:translate(-50%,-100%); background:#3B1500; color:#fff;
        font-size:11px; line-height:1.5; padding:8px 11px; border-radius:9px; white-space:nowrap; pointer-events:none;
        box-shadow:0 8px 22px rgba(0,0,0,.22); z-index:6; }
    .sds-tip b { font-size:12px; font-weight:700; }
    .sds-tip .k { color:#C9B3A2; } .sds-tip .vg { color:#9FE6B4; font-weight:700; } .sds-tip .va { color:#FAD79A; font-weight:700; }

    .sds-top { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:10px; margin-bottom:18px; }
    .sds-h { font-size:20px; font-weight:800; color:#3B1500; margin:0; }
    .sds-h__sub { font-size:12.5px; color:#9A7256; margin-top:2px; }
    .sds-datebar { display:flex; gap:8px; }
    .sds-date { border:1px solid #E7C9AC; border-radius:10px; padding:7px 11px; font-size:13px; color:#3B1500; background:#fff; }
    .sds-btn { background:linear-gradient(135deg,#6B2400,#461500); color:#fff; border:none; border-radius:10px; padding:8px 15px; font-size:13px; font-weight:600; cursor:pointer; }
    .sds-btn i { margin-right:5px; }

    /* KPI */
    .sds-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-bottom:18px; }
    .sds-kpi { background:#fff; border:1px solid #F0E2D6; border-radius:18px; padding:18px; box-shadow:0 1px 2px rgba(70,21,0,.04),0 10px 26px rgba(70,21,0,.05); }
    .sds-chip { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:18px; margin-bottom:13px; }
    .sds-chip--sm { width:38px; height:38px; border-radius:10px; font-size:15px; margin-bottom:0; flex:0 0 auto; }
    .sds-kpi__label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:#9A7256; }
    .sds-kpi__val { font-size:27px; font-weight:800; color:#3B1500; line-height:1.15; margin:3px 0; }
    .sds-kpi__sub { font-size:11.5px; color:#9A7256; }
    .sds-kpi__sub.is-ok { color:#2F8E55; } .sds-kpi__sub.is-warn { color:#C0791F; }

    /* Grid */
    .sds-grid { display:grid; grid-template-columns:minmax(0,2.25fr) minmax(330px,1fr); gap:18px; align-items:start; }
    .sds-main, .sds-side { display:flex; flex-direction:column; gap:18px; }
    @media (max-width:1100px){ .sds-grid{ grid-template-columns:1fr; } .sds-kpis{ grid-template-columns:repeat(2,1fr); } }

    .sds-card { background:#fff; border:1px solid #F0E2D6; border-radius:20px; padding:20px 22px; box-shadow:0 1px 2px rgba(70,21,0,.04),0 10px 26px rgba(70,21,0,.05); }
    .sds-sec { display:flex; align-items:center; gap:10px; font-size:15px; font-weight:800; color:#3B1500; margin-bottom:16px; }
    .sds-sec--m0 { margin-bottom:0; }
    .sds-sec__ic { width:30px; height:30px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; font-size:14px; flex:0 0 auto; }
    .sds-sec__sub { font-size:12px; font-weight:500; color:#A9866A; }
    .sds-empty { text-align:center; color:#A9866A; font-size:13px; padding:22px 0; }
    .sds-muted { color:#B79A82; }

    /* HERO */
    .sds-hero { display:flex; align-items:center; gap:20px; background:linear-gradient(120deg,#6B2400 0%,#521A02 52%,#461500 100%); color:#fff; border-radius:22px; padding:24px 28px; box-shadow:0 14px 34px rgba(70,21,0,.30); }
    .sds-hero__l { flex:1; }
    .sds-hero__eyebrow { font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#E6CDBB; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .sds-pill { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; padding:3px 11px; border-radius:20px; text-transform:none; letter-spacing:0; }
    .sds-pill.is-ok { background:rgba(95,203,134,.2); color:#9FE6B4; }
    .sds-pill.is-behind { background:rgba(230,169,61,.2); color:#FAD79A; }
    .sds-hero__amount { font-size:46px; font-weight:800; letter-spacing:-1px; line-height:1.05; margin:8px 0 4px; }
    .sds-hero__target { font-size:14px; color:#E0C8B8; }
    .sds-hero__target--warn { color:#FAD79A; }
    .sds-hero__row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; font-size:13px; color:#E0C8B8; margin-top:12px; }
    .sds-hero__row b { color:#fff; } .sds-dot { color:#9a6b4e; }
    .sds-hero__r { flex:0 0 auto; }
    .sds-donut { width:150px; height:150px; }
    .sds-donut__pct { fill:#fff; font-size:30px; font-weight:800; text-anchor:middle; }
    .sds-donut__lbl { fill:#E6CDBB; font-size:9px; font-weight:700; letter-spacing:1px; text-anchor:middle; }

    /* CHART */
    .sds-chart { width:100%; height:auto; display:block; }
    .sds-bar { fill:#3DA86A; cursor:pointer; transition:fill .12s; }
    .sds-bar:hover { fill:#2C8C63; }
    .sds-bar.is-today { fill:#6B2400; }
    .sds-bar.is-today:hover { fill:#521A02; }
    .sds-axis { fill:#A9866A; font-size:9px; }
    .sds-legend { display:flex; gap:18px; font-size:12px; color:#7A5C45; margin-top:8px; }
    .sds-lg { display:inline-block; width:16px; height:0; vertical-align:middle; margin-right:5px; }
    .sds-lg--line { border-top:2.5px solid #2C8C63; }
    .sds-lg--dash { border-top:2px dashed #C9962F; }
    .sds-lg--bar { width:11px; height:11px; background:#3DA86A; border-radius:2px; }

    /* PROMO */
    .sds-promo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:13px; }
    .sds-promo { border:1px solid #F0E2D6; border-left:4px solid var(--cc,#7A3415); border-radius:14px; padding:13px 15px; background:#FFFDFB; transition:box-shadow .15s,transform .15s; }
    .sds-promo:hover { box-shadow:0 6px 22px rgba(70,21,0,.1); transform:translateY(-2px); }
    .sds-promo__camp { font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.7px; }
    .sds-promo__name { font-size:14px; font-weight:700; color:#3B1500; margin:4px 0 8px; line-height:1.25; min-height:36px; }
    .sds-promo__now { font-size:17px; font-weight:800; color:#3B1500; }
    .sds-promo__pr { display:flex; align-items:center; gap:8px; margin-top:3px; }
    .sds-promo__save { font-size:10px; font-weight:700; color:#2F8E55; background:#E2F1EA; padding:1px 7px; border-radius:20px; }
    .sds-promo__old { font-size:11px; color:#B79A82; text-decoration:line-through; }
    .sds-promo__until { font-size:10.5px; color:#A9866A; margin-top:8px; }
    .sds-promo__until--soon { color:#3C5FA8; font-weight:700; }
    .sds-promo__until i { margin-right:3px; }

    /* JADWAL */
    .sds-doc-branch { font-weight:700; font-size:12.5px; color:#7A3415; margin:6px 0 8px; }
    .sds-doc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:9px; }
    .sds-doc { display:flex; align-items:center; gap:11px; padding:9px 12px; border:1px solid #F0E2D6; border-radius:13px; background:#FFFCF9; }
    .sds-doc.is-leave { background:#FDF3F3; border-color:#F6DADA; }
    .sds-doc__av { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,#6B2400,#461500); color:#fff; font-size:13px; font-weight:700; display:flex; align-items:center; justify-content:center; flex:0 0 auto; }
    .sds-doc.is-leave .sds-doc__av { background:#B23A3A; }
    .sds-doc__name { flex:1; font-size:13px; font-weight:600; color:#3B1500; }
    .sds-doc__time { font-size:11px; font-weight:700; color:#3C5FA8; background:#EAF1FB; padding:3px 10px; border-radius:20px; white-space:nowrap; }
    .sds-doc__time.is-leave { color:#B23A3A; background:#FDEAEA; }

    /* SIDE */
    .sds-side-head { display:flex; justify-content:space-between; align-items:center; }
    .sds-arrow { width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#6B2400,#461500); color:#fff; display:flex; align-items:center; justify-content:center; text-decoration:none; }
    .sds-arrow:hover { color:#fff; opacity:.92; }
    .sds-big { font-size:34px; font-weight:800; color:#3B1500; margin:12px 0 4px; }
    .sds-big span { font-size:13px; font-weight:500; color:#C0791F; }
    .sds-bars { border:1px solid #F0E2D6; border-radius:14px; padding:11px 13px; margin-top:12px; background:#FFFCF9; }
    .sds-bars__lbl { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#A9866A; margin-bottom:9px; }
    .sds-bars__row { display:flex; gap:9px; align-items:flex-end; }
    .sds-bars__col { flex:1; text-align:center; }
    .sds-bars__track { height:46px; display:flex; align-items:flex-end; }
    .sds-bars__fill { width:100%; border-radius:7px; background:#EAD9C7; transition:height .3s; }
    .sds-bars__fill.is-today { background:linear-gradient(180deg,#6B2400,#461500); }
    .sds-bars__fill.is-empty { background:#EFE2D6; }
    .sds-bars__num { font-size:12px; font-weight:800; color:#6B2400; margin-bottom:4px; }
    .sds-bars__num.is-zero { color:#C9B7A6; }
    .sds-bars__day { font-size:10.5px; color:#7A5C45; font-weight:600; margin-top:5px; line-height:1.35; }
    .sds-bars__dow { font-size:9px; color:#C2A892; text-transform:uppercase; letter-spacing:.3px; font-weight:700; }
    .sds-rule { height:1px; background:#F0E2D6; margin:14px 0 8px; }
    .sds-resv { display:flex; justify-content:space-between; align-items:center; padding:6px 0; font-size:12.5px; }
    .sds-resv__l { display:flex; align-items:center; gap:8px; font-weight:600; color:#3B1500; }
    .sds-resv__dot { width:7px; height:7px; border-radius:50%; background:#C0791F; flex:0 0 auto; }
    .sds-resv__t { font-size:11px; color:#A9866A; }

    /* BUTUH PERHATIAN ok-state */
    .sds-allok { text-align:center; padding:14px 0 6px; }
    .sds-allok__ic { width:46px; height:46px; border-radius:50%; background:#E2F1EA; color:#2F8E55; display:flex; align-items:center; justify-content:center; font-size:20px; margin:0 auto 10px; }
    .sds-allok__t { font-size:14px; font-weight:700; color:#2F8E55; }
    .sds-allok__s { font-size:11.5px; color:#A9866A; margin-top:2px; }
    .sds-alert { font-size:12.5px; color:#7A2E2E; margin-bottom:7px; display:flex; align-items:center; gap:7px; }
    .sds-alert--muted { color:#8a7a6e; }
    .sds-tag-red { font-size:10px; font-weight:700; color:#B23A3A; background:#FDEAEA; padding:2px 8px; border-radius:20px; }

    /* AKSI CEPAT */
    .sds-aksi-list { display:flex; flex-direction:column; gap:10px; margin-top:14px; }
    .sds-aksi { display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid #F0E2D6; border-radius:14px; background:#FFFCF9; text-decoration:none; transition:box-shadow .15s,transform .15s; }
    .sds-aksi:hover { box-shadow:0 6px 22px rgba(70,21,0,.1); transform:translateY(-2px); }
    .sds-aksi__body { flex:1; min-width:0; }
    .sds-aksi__t { font-size:13px; font-weight:700; color:#3B1500; }
    .sds-aksi__s { font-size:11.5px; color:#A9866A; margin-top:1px; }
    .sds-aksi__badge { font-size:11px; font-weight:700; flex:0 0 auto; }
    .sds-aksi__badge--amber { width:26px; height:26px; border-radius:50%; background:#C0791F; color:#fff; display:flex; align-items:center; justify-content:center; }
    .sds-aksi__badge--green { color:#2F8E55; background:#E2F1EA; padding:3px 9px; border-radius:20px; }
    .sds-aksi__arrow { color:#B79A82; }
</style>

<script>
(function () {
    var chart = document.getElementById('sdsChart');
    if (!chart) return;
    var wrap = chart.closest('.sds-chartwrap'), tip = document.getElementById('sdsTip');
    chart.querySelectorAll('.sds-bar').forEach(function (c) {
        c.addEventListener('mouseenter', function () {
            tip.innerHTML = '<b>' + c.dataset.label + '</b>'
                + '<br><span class="vg">●</span> <span class="k">Penjualan:</span> ' + c.dataset.val;
            var cr = c.getBoundingClientRect(), wr = wrap.getBoundingClientRect();
            tip.style.left = (cr.left + cr.width / 2 - wr.left) + 'px';
            tip.style.top  = (cr.top - wr.top - 8) + 'px';
            tip.style.display = 'block';
        });
        c.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
    });
})();
</script>
@endsection
