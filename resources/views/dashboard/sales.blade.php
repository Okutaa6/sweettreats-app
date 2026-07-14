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
            @if ($scopeAll)
                <select name="cabang" class="sds-select" onchange="this.form.submit()">
                    <option value="">Semua Cabang</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" {{ $selected === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="date" name="date" value="{{ $anchor }}" class="sds-date">
            <button class="sds-btn"><i class="fa fa-eye"></i> Lihat</button>
        </form>
    </div>

    {{-- ════ KPI strip ════ --}}
    <div class="sds-kpis">
        @php
            $deltaTxt = $kpi['revenue_yday'] == 0
                ? 'belum ada transaksi kemarin'
                : ($kpi['revenue_delta'] >= 0 ? '▲ ' : '▼ ') . $rp(abs($kpi['revenue_delta'])) . ' dari hari sebelumnya';
        @endphp
        <div class="sds-kpi">
            <div class="sds-chip" style="background:#FBE7D2;color:#C0791F;"><i class="fa fa-coins"></i></div>
            <div class="sds-kpi__label">Penjualan Kemarin</div>
            <div class="sds-kpi__val">{{ $rp($kpi['revenue_yday']) }}</div>
            <div class="sds-kpi__sub {{ $kpi['revenue_yday'] > 0 && $kpi['revenue_delta'] >= 0 ? 'is-ok' : ($kpi['revenue_delta'] < 0 ? 'is-warn' : '') }}">{{ $deltaTxt }}</div>
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

            {{-- PROMO: 2 section — Aktif (sekarang) + Akan Datang (prep) --}}
            <div class="sds-card">
                @if ($activePromos->isEmpty() && $upcomingPromos->isEmpty())
                    <div class="sds-sec"><span class="sds-sec__ic" style="background:#F4E7D8;color:#7A3415;"><i class="fa fa-tags"></i></span>
                        Promo Aktif <span class="sds-sec__sub">— bisa ditawarkan ke pasien buat ngejar target</span></div>
                    <div class="sds-empty">Belum ada promo aktif atau akan datang untuk cabang ini.</div>
                @else
                    @if ($activePromos->isNotEmpty())
                        <div class="sds-sec"><span class="sds-sec__ic" style="background:#F4E7D8;color:#7A3415;"><i class="fa fa-tags"></i></span>
                            Promo Aktif <span class="sds-sec__sub">— bisa ditawarkan ke pasien sekarang</span></div>
                        <div class="sds-promo-grid">
                            @foreach ($activePromos as $p)
                                @include('dashboard._promo_card', ['p' => $p, 'active' => true])
                            @endforeach
                        </div>
                    @endif
                    @if ($upcomingPromos->isNotEmpty())
                        <div class="sds-sec {{ $activePromos->isNotEmpty() ? 'sds-sec--gap' : '' }}"><span class="sds-sec__ic" style="background:#E4ECFB;color:#3C5FA8;"><i class="fa fa-clock"></i></span>
                            Promo Akan Datang <span class="sds-sec__sub">— belum aktif, siap-siap tawarin pas mulai</span></div>
                        <div class="sds-promo-grid">
                            @foreach ($upcomingPromos as $p)
                                @include('dashboard._promo_card', ['p' => $p, 'active' => false])
                            @endforeach
                        </div>
                    @endif
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
                <div class="sds-sec {{ $scopeAll && count($doctorSchedule) ? 'sds-sec--split' : '' }}">
                    <span class="sds-sec__title"><span class="sds-sec__ic" style="background:#E4ECFB;color:#3C5FA8;"><i class="fa fa-user-md"></i></span>
                        Jadwal Dokter <span class="sds-sec__sub">— {{ $dayLabel }}</span></span>
                    @if ($scopeAll && count($doctorSchedule))
                        <div class="sds-docsel">
                            <i class="fa fa-map-marker-alt sds-docsel__ic"></i>
                            <select class="sds-docsel__sel" id="sdsDocBranch" onchange="sdsFilterDoc(this.value)">
                                @foreach ($doctorSchedule as $branchLabel => $b)
                                    <option value="{{ $branchLabel }}">{{ $b['name'] }} · {{ $b['count'] }} dokter</option>
                                @endforeach
                            </select>
                            <i class="fa fa-chevron-down sds-docsel__caret"></i>
                        </div>
                    @endif
                </div>
                @forelse ($doctorSchedule as $branchLabel => $b)
                    <div class="sds-doc-group" data-branch="{{ $branchLabel }}">
                        @if ($scopeAll)
                            <div class="sds-doc-branch">
                                <span class="sds-doc-branch__name"><i class="fa fa-map-marker-alt"></i> {{ $b['name'] }}</span>
                                <span class="sds-doc-branch__count">{{ $b['count'] }} dokter</span>
                            </div>
                        @endif

                        {{-- Matrix jadwal mingguan: dokter × 7 hari --}}
                        @php $sdwCols = '188px repeat(' . count($b['days']) . ', minmax(84px,1fr))'; @endphp
                        <div class="sdw-scroll">
                            <div class="sdw-grid">
                                <div class="sdw-head" style="grid-template-columns:{{ $sdwCols }};">
                                    <div class="sdw-head__dr">DOKTER</div>
                                    @foreach ($b['days'] as $d)
                                        <div class="sdw-head__day {{ $d['is_sel'] ? 'is-sel' : '' }}">
                                            <div class="sdw-head__short">{{ $d['short'] }}</div>
                                            <div class="sdw-head__num">{{ $d['num'] }}</div>
                                            <div class="sdw-head__badge {{ $d['count'] === 0 ? 'is-off' : '' }}">{{ $d['count'] > 0 ? $d['count'] . ' drg' : 'Libur' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                                @foreach ($b['rows'] as $doc)
                                    <div class="sdw-row" style="grid-template-columns:{{ $sdwCols }};">
                                        <div class="sdw-dr {{ $doc['active'] ? '' : 'is-off' }}">
                                            <div class="sdw-dr__av" style="background:{{ $doc['spec']['color'] }};">{{ $doc['initial'] }}</div>
                                            <div class="sdw-dr__main">
                                                <div class="sdw-dr__name" title="{{ $doc['name'] }}">{{ $doc['name'] }}</div>
                                                <div class="sdw-dr__spec" style="color:{{ $doc['spec']['color'] }};"><span style="background:{{ $doc['spec']['color'] }};"></span>{{ $doc['spec']['label'] }}</div>
                                            </div>
                                        </div>
                                        @foreach ($b['days'] as $d)
                                            @php $cell = $doc['cells'][$d['iso']]; @endphp
                                            <div class="sdw-cell {{ $d['is_sel'] ? 'is-sel' : '' }}">
                                                @if ($cell['cuti'])
                                                    <div class="sdw-cuti">CUTI</div>
                                                @else
                                                    @foreach ($cell['blocks'] as $blk)
                                                        <div class="sdw-blk" style="background:{{ $doc['spec']['color'] }};">{{ $blk }}</div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
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
    .sds-select { border:1px solid #E7C9AC; border-radius:10px; padding:7px 11px; font-size:13px; color:#3B1500; background:#fff; cursor:pointer; max-width:180px; }
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
    .sds-sec--split { justify-content:space-between; }
    .sds-sec__title { display:inline-flex; align-items:center; gap:10px; min-width:0; }
    .sds-docsel { position:relative; display:inline-flex; align-items:center; flex:0 0 auto; }
    .sds-docsel__ic { position:absolute; left:11px; font-size:11px; color:#B5701A; pointer-events:none; }
    .sds-docsel__caret { position:absolute; right:11px; font-size:9px; color:#B5701A; pointer-events:none; transition:transform .15s; }
    .sds-docsel:hover .sds-docsel__caret { transform:translateY(1px); }
    .sds-docsel__sel { -webkit-appearance:none; -moz-appearance:none; appearance:none;
        font-family:inherit; font-size:12px; font-weight:700; color:#5C2A0A;
        background:#fff; border:1.5px solid #E3C7A8; border-radius:10px;
        padding:7px 30px 7px 28px; cursor:pointer; max-width:200px;
        box-shadow:0 1px 2px rgba(70,21,0,.05);
        text-overflow:ellipsis; transition:border-color .15s, box-shadow .15s; }
    .sds-docsel__sel:hover { border-color:#C0791F; }
    .sds-docsel__sel:focus-visible { outline:none; border-color:#C0791F; box-shadow:0 0 0 3px rgba(192,121,31,.16); }
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
    .sds-sec--gap { margin-top:22px; }
    .sds-promo__text { font-size:10.5px; line-height:1.45; color:#6a615a; margin-top:7px; }
    .sds-promo__text b { display:block; font-size:8.5px; font-weight:800; text-transform:uppercase; letter-spacing:.4px; color:#b3a79e; margin-bottom:1px; }
    .sds-promo__text span { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .sds-promo__text--sk span { font-style:italic; color:#9a8f86; -webkit-line-clamp:1; }
    .sds-promo__until i { margin-right:3px; }

    /* JADWAL */
    .sds-doc-group { margin-bottom:16px; }
    .sds-doc-group:last-child { margin-bottom:0; }
    .sds-doc-branch { display:flex; align-items:center; justify-content:space-between; gap:10px; padding-bottom:7px; margin:4px 0 10px; border-bottom:1px solid #F0E2D6; }
    .sds-doc-branch__name { font-weight:700; font-size:12.5px; color:#7A3415; display:inline-flex; align-items:center; gap:6px; }
    .sds-doc-branch__name i { color:#B5701A; font-size:11px; }
    .sds-doc-branch__count { font-size:11px; font-weight:600; color:#9A7256; background:#F6EEE4; padding:2px 9px; border-radius:20px; white-space:nowrap; }
    /* Matrix jadwal mingguan (dokter × hari) */
    .sdw-scroll { overflow-x:auto; border:1px solid #F0E2D2; border-radius:14px; }
    .sdw-grid { min-width:640px; }
    .sdw-head { display:grid; background:linear-gradient(90deg,#6B2400 0%,#4A1B06 55%,#3A1505 100%); }
    .sdw-head__dr { padding:11px 14px; font-size:10.5px; font-weight:800; letter-spacing:.6px; color:#EAD3BC; align-self:center; }
    .sdw-head__day { padding:8px 4px; text-align:center; border-left:1px solid rgba(255,255,255,.08); }
    .sdw-head__day.is-sel { background:rgba(255,255,255,.10); }
    .sdw-head__short { font-size:10.5px; font-weight:800; letter-spacing:.4px; color:#EAD3BC; }
    .sdw-head__num { font-size:13px; font-weight:800; margin-top:1px; color:#fff; font-variant-numeric:tabular-nums; }
    .sdw-head__badge { display:inline-block; margin-top:4px; font-size:9px; font-weight:800; padding:1px 7px; border-radius:20px; background:rgba(255,255,255,.16); color:#EAD3BC; }
    .sdw-head__badge.is-off { background:rgba(255,255,255,.08); color:#C2A98F; }
    .sdw-row { display:grid; border-top:1px solid #F0E2D2; }
    .sdw-dr { display:flex; align-items:center; gap:9px; padding:9px 12px; border-right:1px solid #F0E2D2; min-width:0; }
    .sdw-dr.is-off { opacity:.4; }
    .sdw-dr__av { width:30px; height:30px; flex:none; border-radius:50%; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; }
    .sdw-dr__main { min-width:0; }
    .sdw-dr__name { font-size:12px; font-weight:700; color:#3B1500; line-height:1.25; white-space:normal; overflow-wrap:break-word; }
    .sdw-dr__spec { display:inline-flex; align-items:center; gap:5px; font-size:9.5px; font-weight:700; margin-top:2px; }
    .sdw-dr__spec span { width:7px; height:7px; border-radius:2px; display:inline-block; flex:none; }
    .sdw-cell { padding:6px 5px; border-left:1px solid #F3E9DE; display:flex; flex-direction:column; gap:4px; min-height:44px; }
    .sdw-cell.is-sel { background:#FFF4E4; box-shadow:inset 0 0 0 1px #F1D9BA; }
    .sdw-blk { border-radius:7px; padding:4px 7px; color:#fff; font-size:10.5px; font-weight:800; line-height:1.15; font-variant-numeric:tabular-nums; text-align:center; box-shadow:0 1px 2px rgba(40,22,8,.16); }
    .sdw-cuti { font-size:9.5px; font-weight:800; letter-spacing:.5px; color:#B23A3A; background:#FDEAEA; border-radius:6px; padding:3px 0; text-align:center; }
    /* Hari lain diredupkan → kolom hari terpilih yang menonjol */
    .sdw-cell:not(.is-sel) .sdw-blk { opacity:.38; box-shadow:none; }
    .sdw-cell:not(.is-sel) .sdw-cuti { opacity:.5; }

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
    /* Promo detail modal */
    .sds-promo { cursor:pointer; transition:transform .12s ease, box-shadow .12s ease; }
    .sds-promo:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(40,22,8,.12); }
    .sds-promo:focus-visible { outline:2px solid var(--cc); outline-offset:2px; }
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
</style>

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

/* Jadwal Dokter — filter per cabang (client-side) */
function sdsFilterDoc(val) {
    document.querySelectorAll('.sds-doc-group').forEach(function (g) {
        g.style.display = (g.dataset.branch === val) ? '' : 'none';
    });
}
(function () {
    var sel = document.getElementById('sdsDocBranch');
    if (sel) sdsFilterDoc(sel.value);
})();
</script>
@endsection
