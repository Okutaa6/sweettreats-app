@extends('layouts.app')

@php
    $rp  = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $rpc = function ($v) { // kompak: 1,2 jt / 3,4 rb
        $v = (float) $v;
        if (abs($v) >= 1_000_000_000) return 'Rp ' . number_format($v / 1_000_000_000, 1, ',', '.') . ' M';
        if (abs($v) >= 1_000_000)     return 'Rp ' . number_format($v / 1_000_000, 1, ',', '.') . ' jt';
        if (abs($v) >= 1_000)         return 'Rp ' . number_format($v / 1_000, 0, ',', '.') . ' rb';
        return 'Rp ' . number_format($v, 0, ',', '.');
    };
    $growthBadge = function (?float $g) {
        if ($g === null) return '<span style="color:var(--text-subtle);">—</span>';
        $up = $g >= 0;
        $color = $up ? '#2E7D32' : '#B23A3A';
        $icon  = $up ? 'fa-arrow-up' : 'fa-arrow-down';
        return '<span style="color:' . $color . '; font-weight:600;"><i class="fa ' . $icon . '" style="font-size:0.7rem;"></i> '
             . number_format(abs($g), 1, ',', '.') . '%</span>';
    };
    $startC = \Carbon\Carbon::parse($start);
    $endC   = \Carbon\Carbon::parse($end);
    $rangeDays  = $startC->diffInDays($endC) + 1;
    $periodLabel = $startC->translatedFormat('d M Y') . ' – ' . $endC->translatedFormat('d M Y');
    $qs = fn($overrides = []) => http_build_query(array_merge([
        'date_from' => $start, 'date_to' => $end, 'place' => $filterId,
    ], $overrides));
    // Label rentang yang digeser k tahun ke belakang (untuk baris YoY)
    $yoyCount = count($yoy['years']);
    $shiftLabel = function ($i) use ($startC, $endC, $yoyCount) {
        $off = $yoyCount - 1 - $i;
        return $startC->copy()->subYears($off)->translatedFormat('d M') . '–'
             . $endC->copy()->subYears($off)->translatedFormat('d M Y');
    };
    // Section meta — semua selalu visible (no dropdown), drill ke modal via "Lihat Detail"
    $lastMoM = $monthly['growth'][count($monthly['growth']) - 1] ?? null;
    $fmtPct = fn($v) => $v === null ? '—' : number_format(abs($v), 1, ',', '.') . '%';
    $mkSec = fn(string $title, string $icon, string $kpi, bool $sparse = false) => [
        'title' => $title, 'icon' => $icon, 'kpi' => $kpi, 'sparse' => $sparse, 'open' => true,
    ];
    $sections = [
        'achievement' => $mkSec('Pencapaian Target', 'fa-bullseye',
            $totals->pct !== null ? number_format($totals->pct, 1, ',', '.') . '%' : '—',
            $totals->target < 1_000_000),
        'growth' => $mkSec('Growth Trends (MoM &amp; DoD)', 'fa-chart-line',
            'MoM ' . ($lastMoM === null ? '—' : ($lastMoM >= 0 ? '+' : '') . number_format($lastMoM, 1, ',', '.') . '%')),
        'yoy' => $mkSec('Year-over-Year', 'fa-calendar',
            'YoY ' . ($hero->yoyDelta === null ? '—' : ($hero->yoyDelta >= 0 ? '+' : '') . number_format($hero->yoyDelta, 1, ',', '.') . '%')),
        'patients' => $mkSec('Patient Insights', 'fa-users',
            $patients->total > 0
                ? number_format($patients->baru) . ' baru · ' . number_format($patients->returning) . ' returning'
                : '—',
            $patients->total === 0),
        'treatments' => $mkSec('Top Perawatan', 'fa-tooth',
            $topTreatments->isNotEmpty() ? 'Top: ' . \Illuminate\Support\Str::limit($topTreatments[0]->name, 24) : '—',
            $topTreatments->isEmpty()),
        'doctors' => $mkSec('Top Dokter', 'fa-user-md',
            $topDoctors->isNotEmpty() ? 'Top: ' . \Illuminate\Support\Str::limit($topDoctors[0]->name, 24) : '—',
            $topDoctors->isEmpty()),
        'discount' => $mkSec('Discount Impact', 'fa-percent',
            $rpc($discount['totalDiscount']), (float) $discount['totalDiscount'] <= 0),
        'promos' => $mkSec('Top Promo', 'fa-tag',
            $topPromos->isNotEmpty() ? $topPromos->count() . ' promo' : '—',
            $topPromos->sum('uses') < 5),
    ];
    // Inject slug ke tiap section meta (untuk button data-modal-section)
    foreach ($sections as $__k => &$__s) { $__s['slug'] = $__k; }
    unset($__s);

    // Semua section sekarang punya modal drill — pola yang sama dgn Patient
    $sectionsWithModal = array_keys($sections);
    // $branchComparison di-pass dari controller (selalu semua cabang, filter-independent)
@endphp

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">
                    <i class="fa fa-chart-pie mr-2" style="color:var(--brown)"></i>
                    Executive Report
                    <span style="font-size:0.85rem; color:var(--text-subtle); font-weight:400;">
                        — {{ $branchName }} · {{ $periodLabel }} ({{ $rangeDays }} hari)
                    </span>
                </h4>
            </div>
        </div>
    </div>

    {{-- ═══ FILTER COLLAPSIBLE (di atas, default kebuka; klik header untuk tutup/buka) ═══ --}}
    <div class="exec-filter-wrap">
        <button type="button" class="exec-filter-toggle is-open" id="execFilterToggle">
            <span>
                <i class="fa fa-filter mr-1" style="color:var(--brown-2);"></i>
                Filter — {{ $periodLabel }} · {{ $branchName }}
            </span>
            <i class="fa fa-chevron-up exec-filter-toggle__chevron"></i>
        </button>
        <div class="exec-filter-body" id="execFilterBody">
        <div class="st-card"><div class="st-card__body">
            <form method="GET">
                <div style="display:flex; flex-wrap:wrap; gap:22px; align-items:flex-end;">
                    <div>
                        <label style="display:block; font-size:0.7rem; font-weight:700; letter-spacing:0.05em;
                                      color:var(--brown); margin-bottom:7px;">
                            <i class="fa fa-calendar-alt mr-1"></i> PERIODE
                        </label>
                        <div style="display:inline-flex; align-items:stretch; background:#fff;
                                    border:1.5px solid var(--brown-pale); border-radius:10px; overflow:hidden;">
                            <input type="date" name="date_from" value="{{ $start }}" required
                                   style="border:none; outline:none; background:transparent; color:var(--text-primary);
                                          font-size:0.86rem; padding:9px 12px; cursor:pointer;">
                            <span style="display:flex; align-items:center; padding:0 12px;
                                         background:var(--brown); color:#fff; font-size:0.78rem;">
                                <i class="fa fa-arrow-right"></i>
                            </span>
                            <input type="date" name="date_to" value="{{ $end }}" required
                                   style="border:none; outline:none; background:transparent; color:var(--text-primary);
                                          font-size:0.86rem; padding:9px 12px; cursor:pointer;">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.7rem; font-weight:700; letter-spacing:0.05em;
                                      color:var(--brown); margin-bottom:7px;">
                            <i class="fa fa-hospital mr-1"></i> CABANG
                        </label>
                        <select name="place"
                                style="width:200px; padding:9px 12px; font-size:0.86rem; cursor:pointer;
                                       border:1.5px solid var(--brown-pale); border-radius:10px;
                                       background:#fff; color:var(--text-primary); outline:none;">
                            <option value="">Semua Cabang</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ $filterId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="st-btn-brand"
                            style="padding:10px 24px; font-size:0.85rem; font-weight:600; border-radius:10px;">
                        <i class="fa fa-filter mr-1"></i> Terapkan
                    </button>
                    <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin-left:auto;">
                        <span style="font-size:0.72rem; color:var(--text-subtle); font-weight:600;">
                            <i class="fa fa-bolt" style="color:var(--brown-2);"></i>
                        </span>
                        @foreach($presets as $label => $range)
                            @php $ap = ($start === $range[0] && $end === $range[1]); @endphp
                            <a href="?{{ $qs(['date_from' => $range[0], 'date_to' => $range[1]]) }}"
                               style="text-decoration:none; padding:4px 11px; font-size:0.72rem; border-radius:18px;
                               {{ $ap ? 'background:var(--brown); color:#fff; font-weight:600;'
                                      : 'background:var(--cream-hover); color:var(--text-muted); border:1px solid var(--brown-pale);' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </form>
        </div></div>
        </div>{{-- /exec-filter-body --}}
    </div>{{-- /exec-filter-wrap --}}

    @if($totals->actual <= 0)
        <div style="background:#FFF8F0; border-left:3px solid #B26B00; padding:9px 14px; margin-bottom:16px;
                    border-radius:6px; font-size:0.83rem; color:var(--text-muted);">
            <i class="fa fa-info-circle" style="color:#B26B00;"></i>
            Revenue <strong>actual</strong> (Omnicare) untuk periode ini masih 0 — pastikan sync nightly /
            backfill sudah dijalankan. Angka <strong>booking</strong> &amp; target tetap tampil.
        </div>
    @endif

    {{-- ═══ LAYER 1: HERO SNAPSHOT ═══ --}}
    @php
        $heroUp = ($hero->yoyDelta !== null && $hero->yoyDelta >= 0);
        $heroDeltaColor = $hero->yoyDelta === null ? 'var(--text-subtle)' : ($heroUp ? '#2E7D32' : '#B23A3A');
        $heroDeltaIcon  = $hero->yoyDelta === null ? 'fa-minus' : ($heroUp ? 'fa-arrow-up' : 'fa-arrow-down');
    @endphp
    <style>
        .st-exec-hero{margin-bottom:18px;}
        .st-exec-hero__top{display:flex; justify-content:space-between; align-items:flex-end; gap:18px; flex-wrap:wrap;}
        .st-exec-hero__label{font-size:0.72rem; font-weight:700; letter-spacing:0.08em; color:var(--brown); text-transform:uppercase;}
        .st-exec-hero__sub{font-size:0.78rem; color:var(--text-subtle); margin-top:2px;}
        .st-exec-hero__value{font-size:2.6rem; font-weight:700; line-height:1.05; color:var(--brown); margin:6px 0 0;}
        .st-exec-hero__delta{font-size:1.1rem; font-weight:600;}
        .st-exec-hero__delta small{font-size:0.7rem; color:var(--text-subtle); font-weight:400; margin-left:4px;}
        .st-exec-hero__tiles{display:flex; flex-wrap:wrap; gap:14px; margin-top:18px; padding-top:14px;
                             border-top:1px dashed var(--brown-pale);}
        .st-exec-tile{flex:1; min-width:120px; text-align:center;}
        .st-exec-tile__label{display:block; font-size:0.68rem; font-weight:700; letter-spacing:0.05em;
                             color:var(--text-subtle); text-transform:uppercase; margin-bottom:5px;}
        .st-exec-tile__value{display:block; font-size:1.25rem; font-weight:700; color:var(--brown);}
        .st-exec-tile__value small{font-size:0.7rem; color:var(--text-subtle); font-weight:400;}
        .st-exec-tile--spark{min-width:160px;}
        .st-exec-spark-box{position:relative; height:50px; width:100%;}
        .st-exec-spark-box canvas{position:absolute !important; top:0 !important; left:0 !important;
                                  width:100% !important; height:100% !important;}

        /* ── Section cards (static, always-visible, no dropdown) ── */
        .st-exec-section{margin-bottom:14px; border:1px solid var(--brown-pale); background:#fff; border-radius:10px;
                         overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,0.03);}
        .st-exec-section__header{display:flex; align-items:center; justify-content:space-between; width:100%;
                                 padding:13px 18px; background:var(--cream-hover);
                                 border-bottom:1px solid var(--brown-pale);
                                 font-family:inherit; color:var(--brown);}
        .st-exec-section__left{display:flex; align-items:center; gap:11px; flex:1; min-width:0;}
        .st-exec-section__title{font-weight:700; font-size:0.95rem;}
        .st-exec-section__right{display:flex; align-items:center; gap:10px; flex-shrink:0;}
        .st-exec-section__kpi{font-weight:600; color:var(--brown); font-size:0.88rem;}
        .st-exec-section__body{padding:16px 14px 4px;}
        .st-exec-section__body > .row:last-child > [class*="col-"]{margin-bottom:14px;}
        .st-exec-section__body .st-card{box-shadow:none;}

        /* "Lihat Detail" button per section header */
        .st-detail-btn{display:inline-flex; align-items:center; gap:6px; padding:5px 12px; font-size:0.74rem;
                       font-weight:600; color:#fff; background:var(--brown); border:0; border-radius:18px;
                       cursor:pointer; transition:background .15s; text-decoration:none;}
        .st-detail-btn:hover{background:var(--brown-2); color:#fff; text-decoration:none;}
        .st-detail-btn--disabled{background:var(--brown-pale); color:var(--text-subtle); cursor:not-allowed;}

        /* ── Collapsible filter + tabs (executive UX) ── */
        .exec-filter-wrap{margin-bottom:16px;}
        .exec-filter-wrap .st-card{margin:0; box-shadow:0 1px 3px rgba(0,0,0,0.06);}
        .exec-filter-wrap .st-card__body{padding:0.85rem 1.1rem !important;}
        .exec-filter-toggle{display:flex; align-items:center; justify-content:space-between; width:100%;
                            padding:11px 16px; background:#fff; border:1px solid var(--brown-pale);
                            border-radius:10px; cursor:pointer; color:var(--brown); font-weight:600;
                            font-size:0.86rem; transition:background .15s; font-family:inherit;}
        .exec-filter-toggle:hover{background:var(--cream-hover);}
        .exec-filter-toggle__chevron{transition:transform .18s; color:var(--brown-2);}
        .exec-filter-toggle.is-open{border-bottom-left-radius:0; border-bottom-right-radius:0;
                                    border-bottom-color:transparent; background:var(--cream-hover);}
        .exec-filter-toggle.is-open .exec-filter-toggle__chevron{transform:rotate(180deg);}
        .exec-filter-body .st-card{border-top-left-radius:0; border-top-right-radius:0;}
        .exec-tabs{display:flex; gap:4px; list-style:none; padding:0; margin:0 0 18px;
                   border-bottom:2px solid var(--brown-pale); flex-wrap:wrap;}
        .exec-tab{padding:11px 20px; cursor:pointer; font-weight:600; font-size:0.88rem;
                  color:var(--text-muted); border-bottom:3px solid transparent; margin-bottom:-2px;
                  transition:all .15s; user-select:none; display:inline-flex; align-items:center; gap:7px;}
        .exec-tab:hover{color:var(--brown); background:var(--cream-hover);}
        .exec-tab.is-active{color:var(--brown); border-bottom-color:var(--brown); background:#fff;}
        .exec-tab__count{background:var(--brown-pale); color:var(--brown); padding:1px 7px;
                         border-radius:10px; font-size:0.68rem; font-weight:700;}

        /* Hero — bigger trend chart in side column */
        .exec-hero-trend{position:relative; height:240px;}
        .exec-hero-trend canvas{position:absolute !important; top:0 !important; left:0 !important;
                                width:100% !important; height:100% !important;}

        /* Branch comparison cards */
        .exec-branch-grid{display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px;}
        .exec-branch-card{background:#fff; border:1px solid var(--brown-pale); border-radius:12px;
                          padding:16px 18px; display:flex; flex-direction:column; gap:8px; position:relative;
                          overflow:hidden; transition:box-shadow .15s, transform .15s;}
        .exec-branch-card:hover{box-shadow:0 6px 18px -6px rgba(70,21,0,0.20); transform:translateY(-1px);}
        .exec-branch-card::before{content:""; position:absolute; left:0; top:0; bottom:0; width:4px;
                                  background:var(--brown);}
        .exec-branch-card__name{font-size:0.78rem; font-weight:700; letter-spacing:0.06em;
                                color:var(--brown); text-transform:uppercase;}
        .exec-branch-card__revenue{font-size:1.45rem; font-weight:700; color:var(--brown); line-height:1.1;}
        .exec-branch-card__row{display:flex; justify-content:space-between; align-items:baseline;
                               font-size:0.78rem; color:var(--text-muted); border-top:1px dashed var(--brown-pale);
                               padding-top:6px;}
        .exec-branch-card__row strong{color:var(--brown);}
        .exec-branch-card__delta{font-size:0.78rem; font-weight:600;}
        .exec-branch-card__delta.is-up{color:#2E7D32;}
        .exec-branch-card__delta.is-down{color:#B23A3A;}

        .st-pill--warn{background:#FFF3E0; color:#B26B00; font-size:0.66rem; padding:3px 9px; border-radius:10px; font-weight:600;}

        /* Drill row (Layer 3) */
        .st-drill-row:hover{background:var(--cream-hover);}
        .st-drill-row.is-open{background:var(--cream-hover);}
        .st-drill-chevron{transition:transform .15s;}
        .st-drill-row.is-open .st-drill-chevron{transform:rotate(90deg);}

        /* Chart bounds (defensive — cegah Chart.js responsive resize loop / overflow) */
        .container-fluid{overflow-x:hidden;}
        .st-exec-section__body canvas, .st-exec-section__body [data-drill-canvas]{
            max-height:300px !important;
        }
        .exec-chart-box{position:relative; height:240px;}
        .exec-chart-box canvas{position:absolute; left:0; top:0; width:100% !important; height:100% !important;}
        .exec-drill-box{position:relative; height:160px;}
        .exec-drill-box canvas{position:absolute; left:0; top:0; width:100% !important; height:100% !important;}
    </style>
    {{-- Hero full-width: angka revenue + 4 tile pendukung --}}
    <div class="st-card st-exec-hero"><div class="st-card__body" style="padding:1.25rem 1.5rem;">
        <div class="st-exec-hero__top">
            <div>
                <div class="st-exec-hero__label">Revenue Periode</div>
                <div class="st-exec-hero__sub">{{ $periodLabel }} ({{ $rangeDays }} hari) · {{ $branchName }}</div>
                <div class="st-exec-hero__value">{{ $rp($hero->revenue) }}</div>
            </div>
            <div class="st-exec-hero__delta" style="color:{{ $heroDeltaColor }};">
                <i class="fa {{ $heroDeltaIcon }}"></i>
                {{ $hero->yoyDelta === null ? '(baseline)' : number_format(abs($hero->yoyDelta), 1, ',', '.') . '%' }}
                <small>{{ $hero->yoyLabel }}</small>
            </div>
        </div>

        <div class="st-exec-hero__tiles">
            <div class="st-exec-tile">
                <span class="st-exec-tile__label"><i class="fa fa-bullseye"></i> vs Target</span>
                <span class="st-exec-tile__value">{{ $hero->targetPct !== null ? number_format($hero->targetPct, 1, ',', '.') . '%' : '—' }}</span>
            </div>
            <div class="st-exec-tile">
                <span class="st-exec-tile__label"><i class="fa fa-users"></i> Total Pasien</span>
                <span class="st-exec-tile__value">{{ number_format($hero->patientsTotal) }}</span>
            </div>
            <div class="st-exec-tile">
                <span class="st-exec-tile__label"><i class="fa fa-receipt"></i> Transaksi</span>
                <span class="st-exec-tile__value">{{ number_format($hero->txCount) }}</span>
            </div>
            <div class="st-exec-tile">
                <span class="st-exec-tile__label"><i class="fa fa-hospital"></i> Top Cabang</span>
                <span class="st-exec-tile__value" style="font-size:1rem;">
                    {{ $hero->topBranchName ?? '—' }}
                    @if($hero->topBranchName)<br><small>{{ $rpc($hero->topBranchRev) }}</small>@endif
                </span>
            </div>
        </div>
    </div></div>

    {{-- ═══ TREND REVENUE & PASIEN 12 BULAN (full-width, informatif) ═══ --}}
    @php
        $trendRev   = $monthly['revenue'];
        $trendPat   = $monthly['patients'];
        $trendLbl   = $monthly['labels'];
        $trendN     = count($trendRev);
        $peakIdx    = $trendN ? array_keys($trendRev, max($trendRev))[0] : 0;
        $lowIdx     = $trendN ? array_keys($trendRev, min(array_filter($trendRev) ?: [0]))[0] : 0;
        $trendAvg   = $trendN ? array_sum($trendRev) / $trendN : 0;
        $trendLast  = $trendN ? $trendRev[$trendN - 1] : 0;
        $trendFirst = $trendN ? $trendRev[0] : 0;
        $trendYoYpct = $trendFirst > 0 ? round(($trendLast - $trendFirst) / $trendFirst * 100, 1) : null;
        $trendTotal = array_sum($trendRev);
    @endphp
    <div class="st-card" style="margin-bottom:16px;"><div class="st-card__body">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
            <div>
                <h6 style="font-size:0.95rem; font-weight:700; color:var(--brown); margin:0;">
                    <i class="fa fa-chart-area mr-1"></i> Tren Revenue &amp; Pasien — 12 Bulan
                </h6>
                <p style="font-size:0.76rem; color:var(--text-subtle); margin:3px 0 0;">
                    {{ $trendLbl[0] ?? '—' }} s/d {{ end($trendLbl) ?: '—' }} ·
                    bar = revenue bulanan (kiri), garis biru = jumlah pasien (kanan)
                </p>
            </div>
        </div>

        {{-- Ringkasan angka penting --}}
        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
            <div style="flex:1; min-width:140px; background:var(--cream-hover); border-radius:9px; padding:11px 14px;">
                <div style="font-size:0.68rem; color:var(--text-subtle); font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Bulan Tertinggi</div>
                <div style="font-size:1.05rem; font-weight:700; color:#2E7D32;">{{ $rpc($trendRev[$peakIdx] ?? 0) }}</div>
                <div style="font-size:0.72rem; color:var(--text-muted);">{{ $trendLbl[$peakIdx] ?? '—' }}</div>
            </div>
            <div style="flex:1; min-width:140px; background:var(--cream-hover); border-radius:9px; padding:11px 14px;">
                <div style="font-size:0.68rem; color:var(--text-subtle); font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Bulan Terendah</div>
                <div style="font-size:1.05rem; font-weight:700; color:#B23A3A;">{{ $rpc($trendRev[$lowIdx] ?? 0) }}</div>
                <div style="font-size:0.72rem; color:var(--text-muted);">{{ $trendLbl[$lowIdx] ?? '—' }}</div>
            </div>
            <div style="flex:1; min-width:140px; background:var(--cream-hover); border-radius:9px; padding:11px 14px;">
                <div style="font-size:0.68rem; color:var(--text-subtle); font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Rata-rata / Bulan</div>
                <div style="font-size:1.05rem; font-weight:700; color:var(--brown);">{{ $rpc($trendAvg) }}</div>
                <div style="font-size:0.72rem; color:var(--text-muted);">total {{ $rpc($trendTotal) }}</div>
            </div>
            <div style="flex:1; min-width:140px; background:var(--cream-hover); border-radius:9px; padding:11px 14px;">
                <div style="font-size:0.68rem; color:var(--text-subtle); font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">Bulan Terakhir</div>
                <div style="font-size:1.05rem; font-weight:700; color:#1E5A8C;">{{ $rpc($trendLast) }}</div>
                <div style="font-size:0.72rem;">
                    @if($trendYoYpct !== null)
                        <span style="color:{{ $trendYoYpct >= 0 ? '#2E7D32' : '#B23A3A' }}; font-weight:600;">
                            <i class="fa fa-arrow-{{ $trendYoYpct >= 0 ? 'up' : 'down' }}" style="font-size:0.62rem;"></i>
                            {{ number_format(abs($trendYoYpct), 1, ',', '.') }}%
                        </span>
                        <span style="color:var(--text-subtle);">vs {{ $trendLbl[0] ?? '' }}</span>
                    @else <span style="color:var(--text-subtle);">—</span> @endif
                </div>
            </div>
        </div>

        {{-- Chart utama dengan sumbu & label --}}
        <div style="position:relative; height:300px;"><canvas id="heroSparkline"></canvas></div>
    </div></div>
    {{-- /trend --}}

    {{-- ═══ TAB NAVIGATION (executive UX: kurangi scroll) ═══ --}}
    <ul class="exec-tabs" role="tablist">
        <li class="exec-tab is-active" data-tab="overview"><i class="fa fa-th-large"></i> Overview</li>
        <li class="exec-tab" data-tab="growth"><i class="fa fa-chart-line"></i> Growth &amp; YoY</li>
        <li class="exec-tab" data-tab="topperf"><i class="fa fa-trophy"></i> Top Performers</li>
        <li class="exec-tab" data-tab="patients"><i class="fa fa-users"></i> Pasien</li>
        <li class="exec-tab" data-tab="commerce"><i class="fa fa-percent"></i> Discount &amp; Promo</li>
    </ul>

    {{-- ═══ TAB PANE: OVERVIEW (Branch Comparison + Achievement summary) ═══ --}}
    <div class="exec-tab-pane" data-tab-pane="overview">

        {{-- Branch comparison cards --}}
        <div class="st-exec-section" style="margin-bottom:18px;">
            <div class="st-exec-section__header">
                <span class="st-exec-section__left">
                    <i class="fa fa-balance-scale"></i>
                    <span class="st-exec-section__title">Perbandingan Antar Cabang — {{ $periodLabel }}</span>
                </span>
                <span class="st-exec-section__right">
                    <span class="st-exec-section__kpi">{{ $branchComparison->count() }} cabang</span>
                </span>
            </div>
            <div class="st-exec-section__body">
                <p style="font-size:0.78rem; color:var(--text-subtle); margin:0 0 12px;">
                    Urutkan revenue tertinggi → terendah. Klik card untuk filter ke cabang itu.
                </p>
                <div class="exec-branch-grid">
                    @foreach($branchComparison as $b)
                        @php $bUp = ($b->yoyDelta !== null && $b->yoyDelta >= 0); @endphp
                        <a href="?{{ $qs(['place' => $b->branchId]) }}" class="exec-branch-card" style="text-decoration:none; color:inherit;">
                            <div class="exec-branch-card__name">{{ $b->name }}</div>
                            <div class="exec-branch-card__revenue">{{ $rpc($b->revenue) }}</div>
                            @if($b->yoyDelta !== null)
                                <div class="exec-branch-card__delta {{ $bUp ? 'is-up' : 'is-down' }}">
                                    <i class="fa fa-arrow-{{ $bUp ? 'up' : 'down' }}"></i>
                                    {{ number_format(abs($b->yoyDelta), 1, ',', '.') }}% vs tahun lalu
                                </div>
                            @else
                                <div style="font-size:0.78rem; color:var(--text-subtle);">— baseline tahun ini</div>
                            @endif
                            <div class="exec-branch-card__row">
                                <span>Pasien</span><strong>{{ number_format($b->patients) }}</strong>
                            </div>
                            <div class="exec-branch-card__row">
                                <span>Baru / Returning</span>
                                <strong><span style="color:#2E7D32;">{{ number_format($b->baru) }}</span>
                                · <span style="color:#1E5A8C;">{{ number_format($b->returning) }}</span></strong>
                            </div>
                            <div class="exec-branch-card__row">
                                <span>Repeat Rate</span><strong>{{ number_format($b->repeatRate, 1, ',', '.') }}%</strong>
                            </div>
                            @if($b->targetPct !== null)
                                <div class="exec-branch-card__row">
                                    <span>vs Target</span>
                                    <strong style="color:{{ $b->targetPct >= 100 ? '#2E7D32' : ($b->targetPct >= 70 ? '#B26B00' : '#B23A3A') }};">
                                        {{ number_format($b->targetPct, 1, ',', '.') }}%
                                    </strong>
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        {{-- /Branch comparison --}}

    {{-- ═══ LAYER 2: ACCORDION SECTIONS ═══ --}}

    {{-- Section: Pencapaian Target --}}
    @php $sec = $sections['achievement']; @endphp
    <div class="st-exec-section">
        <div class="st-exec-section__header"
                data-section-toggle="achievement" aria-expanded="{{ $sec['open'] ? 'true' : 'false' }}">
            <span class="st-exec-section__left">
<i class="fa {{ $sec['icon'] }}"></i>
                <span class="st-exec-section__title">{{ $sec['title'] }} — {{ $periodLabel }}</span>
            </span>
            <span class="st-exec-section__right">
                <span class="st-exec-section__kpi">{{ $sec['kpi'] }}</span>
                @if($sec['sparse'])<span class="st-pill st-pill--warn">data masih dikumpulkan</span>@endif
                <button type="button" class="st-detail-btn" data-modal-section="{{ $sec['slug'] }}">
                    <i class="fa fa-expand-arrows-alt"></i> Detail
                </button>
            </span>
        </div>
        <div class="st-exec-section__body" data-section-body="achievement" {{ $sec['open'] ? '' : 'hidden' }}>
            @if($spansMonth)
                <p style="font-size:0.74rem; color:#B26B00; margin-top:0; margin-bottom:10px;">
                    <i class="fa fa-info-circle"></i> Rentang melintasi beberapa bulan — target = penjumlahan
                    target tiap bulan yang tersentuh (target bersifat bulanan), sementara actual mengikuti rentang persis.
                </p>
            @endif

            @php $tpct = $totals->pct ?? 0; @endphp
                <div class="d-flex justify-content-between align-items-end" style="margin-bottom:6px;">
                    <span style="font-size:0.82rem; color:var(--text-muted);">
                        Tercapai <strong style="color:#2E7D32;">{{ $rp($totals->actual) }}</strong>
                        dari target <strong>{{ $rp($totals->target) }}</strong>
                    </span>
                    <span style="font-weight:700; color:var(--brown);">{{ number_format($tpct, 1, ',', '.') }}%</span>
                </div>
                <div style="background:var(--divider-soft); border-radius:8px; height:16px; overflow:hidden; margin-bottom:8px;">
                    <div style="height:100%; width:{{ min($tpct, 100) }}%;
                                background:{{ $tpct >= 100 ? '#2E7D32' : 'var(--brown)' }};
                                transition:width .4s;"></div>
                </div>
                <div class="d-flex" style="gap:10px; margin-bottom:18px;">
                    <div style="flex:1; text-align:center; background:#E8F5E9; border-radius:9px; padding:10px 6px;">
                        <div style="font-size:1.05rem; font-weight:700; color:#2E7D32;">{{ $rpc($totals->actual) }}</div>
                        <div style="font-size:0.7rem; color:var(--text-subtle);">Sudah Tercapai</div>
                    </div>
                    <div style="flex:1; text-align:center; background:#FDECEA; border-radius:9px; padding:10px 6px;">
                        <div style="font-size:1.05rem; font-weight:700; color:#B23A3A;">{{ $rpc($totals->remaining) }}</div>
                        <div style="font-size:0.7rem; color:var(--text-subtle);">Sisa Belum Tercapai</div>
                    </div>
                    <div style="flex:1; text-align:center; background:var(--cream-hover); border-radius:9px; padding:10px 6px;">
                        <div style="font-size:1.05rem; font-weight:700; color:#1E5A8C;">
                            {{ $totals->conv !== null ? number_format($totals->conv, 1, ',', '.') . '%' : '—' }}
                        </div>
                        <div style="font-size:0.7rem; color:var(--text-subtle);">Konversi Booking→Actual</div>
                    </div>
                </div>

                <div class="st-table-wrap--scroll">
                    <table class="st-table st-table--compact">
                        <thead><tr>
                            <th>Cabang</th>
                            <th class="text-right">Target</th>
                            <th class="text-right">Actual</th>
                            <th class="text-right">Booking</th>
                            <th class="text-right">%</th>
                            <th class="text-right">Sisa</th>
                        </tr></thead>
                        <tbody>
                            @forelse($rows as $r)
                            <tr>
                                <td>{{ $r->name }}</td>
                                <td class="text-right st-tnum">{{ $rpc($r->target) }}</td>
                                <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($r->actual) }}</td>
                                <td class="text-right st-tnum" style="color:#1E5A8C;">{{ $rpc($r->booking) }}</td>
                                <td class="text-right st-tnum">
                                    @if($r->pct !== null)
                                        <span style="font-weight:600; color:{{ $r->pct >= 100 ? '#2E7D32' : ($r->pct >= 70 ? '#B26B00' : '#B23A3A') }};">
                                            {{ number_format($r->pct, 1, ',', '.') }}%
                                        </span>
                                    @else <span style="color:var(--text-subtle);">—</span> @endif
                                </td>
                                <td class="text-right st-tnum" style="color:#B23A3A;">{{ $rpc($r->remaining) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center st-muted">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                        @if($rows->count() > 1)
                        <tfoot><tr style="font-weight:700; border-top:2px solid var(--brown-pale);">
                            <td>TOTAL</td>
                            <td class="text-right st-tnum">{{ $rpc($totals->target) }}</td>
                            <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($totals->actual) }}</td>
                            <td class="text-right st-tnum" style="color:#1E5A8C;">{{ $rpc($totals->booking) }}</td>
                            <td class="text-right st-tnum">{{ $totals->pct !== null ? number_format($totals->pct, 1, ',', '.') . '%' : '—' }}</td>
                            <td class="text-right st-tnum" style="color:#B23A3A;">{{ $rpc($totals->remaining) }}</td>
                        </tr></tfoot>
                        @endif
                    </table>
                </div>
        </div>
    </div>

    </div>{{-- /tab-pane:overview --}}

    {{-- ═══ TAB PANE: PASIEN ═══ --}}
    <div class="exec-tab-pane" data-tab-pane="patients" hidden>

    {{-- Section: Patient Insights --}}
    @php $sec = $sections['patients']; @endphp
    <div class="st-exec-section">
        <div class="st-exec-section__header"
                data-section-key="patients">
            <span class="st-exec-section__left">
                <i class="fa {{ $sec['icon'] }}"></i>
                <span class="st-exec-section__title">{{ $sec['title'] }}</span>
            </span>
            <span class="st-exec-section__right">
                <span class="st-exec-section__kpi">{{ $sec['kpi'] }}</span>
                @if($sec['sparse'])<span class="st-pill st-pill--warn">data masih dikumpulkan</span>@endif
                <button type="button" class="st-detail-btn" data-modal-section="patients">
                    <i class="fa fa-expand-arrows-alt"></i> Lihat Detail Lengkap
                </button>
            </span>
        </div>
        <div class="st-exec-section__body" data-section-body="patients">
            <div class="row">
                <div class="col-lg-5 mb-3">
                    @php
                        $pt = max($patients->total, 1);
                        $pctBaru = round($patients->baru / $pt * 100, 1);
                        $pctRet  = round($patients->returning / $pt * 100, 1);
                    @endphp
                    <div style="position:relative; margin:6px auto 16px; width:100%; max-width:230px; height:200px;">
                        <canvas id="patientDonut"></canvas>
                    </div>
                    <div class="st-kv-row">
                        <span class="st-kv-row__label"><i class="fa fa-circle" style="color:#2E7D32; font-size:0.6rem;"></i> Pasien Baru</span>
                        <span class="st-kv-row__value">{{ number_format($patients->baru) }} <small style="color:var(--text-subtle)">({{ number_format($pctBaru, 1, ',', '.') }}%)</small></span>
                    </div>
                    <div class="st-kv-row">
                        <span class="st-kv-row__label"><i class="fa fa-circle" style="color:#1E5A8C; font-size:0.6rem;"></i> Returning</span>
                        <span class="st-kv-row__value">{{ number_format($patients->returning) }} <small style="color:var(--text-subtle)">({{ number_format($pctRet, 1, ',', '.') }}%)</small></span>
                    </div>
                    <small style="display:block; margin-top:11px; color:var(--text-subtle); font-size:0.7rem; line-height:1.55;">
                        <i class="fa fa-info-circle"></i> "Baru" = bulan registrasi (kode ID pasien Omnicare) jatuh di bulan ini.
                        Returning = pasien lama yang transaksi bulan ini.
                    </small>
                </div>
                <div class="col-lg-7 mb-3">
                    <h6 class="st-section-title" style="margin-top:0;"><i class="fa fa-redo"></i> Retention &amp; Cross-Branch Flow</h6>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <div style="text-align:center; background:var(--cream-hover); border-radius:9px; padding:14px 8px;">
                                <div style="font-size:1.5rem; font-weight:700; color:var(--brown);">{{ number_format($retention->repeatRate, 1, ',', '.') }}%</div>
                                <div style="font-size:0.72rem; color:var(--text-subtle); margin-top:2px;">Repeat Rate</div>
                            </div>
                        </div>
                        <div class="col-6 mb-2">
                            <div style="text-align:center; background:#E3F2FD; border-radius:9px; padding:14px 8px;">
                                <div style="font-size:1.5rem; font-weight:700; color:#1E5A8C;">{{ number_format($retention->avgVisitsPerPatient, 2, ',', '.') }}</div>
                                <div style="font-size:0.72rem; color:var(--text-subtle); margin-top:2px;">Avg Kunjungan/Pasien</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <small style="display:block; font-size:0.72rem; color:var(--text-subtle); margin-top:4px; line-height:1.5;">
                                <strong>{{ number_format($retention->repeat) }}</strong> dari {{ number_format($retention->total) }} pasien aktif juga punya transaksi sebelum periode ini.
                            </small>
                        </div>
                        <div class="col-12 mt-2">
                            <div style="background:#FFF8F0; border-left:3px solid var(--brown-2); padding:10px 12px; border-radius:6px;">
                                <div style="font-size:0.82rem; color:var(--text-muted);">
                                    <i class="fa fa-random" style="color:var(--brown);"></i>
                                    <strong style="color:var(--brown);">{{ number_format($crossBranch->cross) }}</strong>
                                    dari {{ number_format($crossBranch->total) }} pasien
                                    ({{ number_format($crossBranch->crossRate, 1, ',', '.') }}%)
                                    transaksi di <strong>>1 cabang</strong> selama periode.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>{{-- /tab-pane:patients --}}

    {{-- ═══ TAB PANE: GROWTH & YoY ═══ --}}
    <div class="exec-tab-pane" data-tab-pane="growth" hidden>

    {{-- Section: Year-over-Year --}}
    @php $sec = $sections['yoy']; @endphp
    <div class="st-exec-section">
        <div class="st-exec-section__header"
                data-section-toggle="yoy" aria-expanded="{{ $sec['open'] ? 'true' : 'false' }}">
            <span class="st-exec-section__left">
<i class="fa {{ $sec['icon'] }}"></i>
                <span class="st-exec-section__title">{{ $sec['title'] }} — periode yang sama, antar tahun</span>
            </span>
            <span class="st-exec-section__right">
                <span class="st-exec-section__kpi">{{ $sec['kpi'] }}</span>
                @if($sec['sparse'])<span class="st-pill st-pill--warn">data masih dikumpulkan</span>@endif
                <button type="button" class="st-detail-btn" data-modal-section="{{ $sec['slug'] }}">
                    <i class="fa fa-expand-arrows-alt"></i> Detail
                </button>
            </span>
        </div>
        <div class="st-exec-section__body" data-section-body="yoy" {{ $sec['open'] ? '' : 'hidden' }}>
            <p style="font-size:0.76rem; color:var(--text-subtle); margin:0 0 12px;">
                Berbasis <strong>revenue actual (Omnicare)</strong> &amp; jumlah pasien. Booking &amp; target
                tidak ikut karena hanya tersedia tahun berjalan.
            </p>
            <div class="row">
                <div class="col-lg-8 mb-3">
                    <div class="exec-chart-box"><canvas id="yoyChart"></canvas></div>
                </div>
                <div class="col-lg-4 mb-3">
                    <h6 class="st-section-title" style="margin-top:0;"><i class="fa fa-table"></i> Per Tahun</h6>
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--compact">
                            <thead><tr>
                                <th>Periode</th><th class="text-right">Revenue</th>
                                <th class="text-right">Pasien</th><th class="text-right">YoY</th>
                            </tr></thead>
                            <tbody>
                                @foreach($yoy['years'] as $i => $yr)
                                <tr>
                                    <td style="font-size:0.78rem;">{{ $shiftLabel($i) }}</td>
                                    <td class="text-right st-tnum">{{ $rpc($yoy['revenue'][$i]) }}</td>
                                    <td class="text-right st-tnum">{{ number_format($yoy['patients'][$i]) }}</td>
                                    <td class="text-right st-tnum">{!! $growthBadge($yoy['growth'][$i]) !!}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row"><div class="col-12 mb-3">
                <h6 class="st-section-title"><i class="fa fa-th"></i> Revenue per Cabang × Tahun</h6>
                <div class="st-table-wrap--scroll">
                    <table class="st-table st-table--compact">
                        <thead><tr>
                            <th>Cabang</th>
                            @foreach($yoy['years'] as $yr)
                                <th class="text-right">{{ $yr }}</th>
                            @endforeach
                            <th class="text-right">YoY (terakhir)</th>
                        </tr></thead>
                        <tbody>
                            @forelse($yoy['matrix'] as $m)
                            <tr>
                                <td>{{ $m->name }}</td>
                                @foreach($m->values as $v)
                                    <td class="text-right st-tnum">{{ $rpc($v) }}</td>
                                @endforeach
                                <td class="text-right st-tnum">{!! $growthBadge($m->growth) !!}</td>
                            </tr>
                            @empty
                            <tr><td colspan="{{ count($yoy['years']) + 2 }}" class="text-center st-muted">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <small style="display:block; margin-top:8px; color:var(--text-subtle); font-size:0.7rem;">
                    <i class="fa fa-info-circle"></i> Kolom = revenue actual pada periode yang sama ({{ $periodLabel }})
                    digeser ke tahun tsb. "YoY (terakhir)" = {{ end($yoy['years']) }} vs {{ $yoy['years'][count($yoy['years']) - 2] ?? '—' }}.
                </small>
            </div></div>
        </div>
    </div>

    {{-- Section: Growth Trends (MoM + DoD) --}}
    @php $sec = $sections['growth']; @endphp
    <div class="st-exec-section">
        <div class="st-exec-section__header"
                data-section-toggle="growth" aria-expanded="{{ $sec['open'] ? 'true' : 'false' }}">
            <span class="st-exec-section__left">
<i class="fa {{ $sec['icon'] }}"></i>
                <span class="st-exec-section__title">{!! $sec['title'] !!}</span>
            </span>
            <span class="st-exec-section__right">
                <span class="st-exec-section__kpi">{{ $sec['kpi'] }}</span>
                @if($sec['sparse'])<span class="st-pill st-pill--warn">data masih dikumpulkan</span>@endif
                <button type="button" class="st-detail-btn" data-modal-section="{{ $sec['slug'] }}">
                    <i class="fa fa-expand-arrows-alt"></i> Detail
                </button>
            </span>
        </div>
        <div class="st-exec-section__body" data-section-body="growth" {{ $sec['open'] ? '' : 'hidden' }}>
            {{-- MoM --}}
            <div class="row">
                <div class="col-lg-8 mb-3">
                    <h6 class="st-section-title" style="margin-top:0;"><i class="fa fa-chart-line"></i> Revenue &amp; Patient — 12 Bulan (MoM)</h6>
                    <div class="exec-chart-box"><canvas id="momChart"></canvas></div>
                </div>
                <div class="col-lg-4 mb-3">
                    <h6 class="st-section-title" style="margin-top:0;"><i class="fa fa-table"></i> Detail Bulanan</h6>
                    <div class="st-table-wrap--scroll" style="max-height:330px;">
                        <table class="st-table st-table--compact">
                            <thead><tr>
                                <th>Bulan</th><th class="text-right">Revenue</th>
                                <th class="text-right">Pasien</th><th class="text-right">MoM</th>
                            </tr></thead>
                            <tbody>
                                @foreach($monthly['labels'] as $i => $lbl)
                                <tr>
                                    <td>{{ $lbl }}</td>
                                    <td class="text-right st-tnum">{{ $rpc($monthly['revenue'][$i]) }}</td>
                                    <td class="text-right st-tnum">{{ number_format($monthly['patients'][$i]) }}</td>
                                    <td class="text-right st-tnum">{!! $growthBadge($monthly['growth'][$i]) !!}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- DoD --}}
            <div class="row">
                <div class="col-lg-8 mb-3">
                    <h6 class="st-section-title" style="margin-top:0;"><i class="fa fa-calendar-day"></i> Revenue &amp; Patient Harian (DoD)</h6>
                    <div class="exec-chart-box"><canvas id="dodChart"></canvas></div>
                </div>
                <div class="col-lg-4 mb-3">
                    <h6 class="st-section-title" style="margin-top:0;"><i class="fa fa-table"></i> Detail Harian</h6>
                    <div class="st-table-wrap--scroll" style="max-height:330px;">
                        <table class="st-table st-table--compact">
                            <thead><tr>
                                <th>Tanggal</th><th class="text-right">Revenue</th>
                                <th class="text-right">Pasien</th><th class="text-right">DoD</th>
                            </tr></thead>
                            <tbody>
                                @forelse($daily['rows'] as $d)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($d->date)->format('d M') }}</td>
                                    <td class="text-right st-tnum">{{ $rpc($d->net) }}</td>
                                    <td class="text-right st-tnum">{{ number_format($d->patients) }}</td>
                                    <td class="text-right st-tnum">{!! $growthBadge($d->growth) !!}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center st-muted">Belum ada transaksi bulan ini</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>{{-- /tab-pane:growth --}}

    {{-- ═══ TAB PANE: TOP PERFORMERS ═══ --}}
    <div class="exec-tab-pane" data-tab-pane="topperf" hidden>

    {{-- Section: Top Perawatan --}}
    @php $sec = $sections['treatments']; @endphp
    <div class="st-exec-section">
        <div class="st-exec-section__header"
                data-section-toggle="treatments" aria-expanded="{{ $sec['open'] ? 'true' : 'false' }}">
            <span class="st-exec-section__left">
<i class="fa {{ $sec['icon'] }}"></i>
                <span class="st-exec-section__title">{{ $sec['title'] }} (Top 10)</span>
            </span>
            <span class="st-exec-section__right">
                <span class="st-exec-section__kpi">{{ $sec['kpi'] }}</span>
                @if($sec['sparse'])<span class="st-pill st-pill--warn">data masih dikumpulkan</span>@endif
                <button type="button" class="st-detail-btn" data-modal-section="{{ $sec['slug'] }}">
                    <i class="fa fa-expand-arrows-alt"></i> Detail
                </button>
            </span>
        </div>
        <div class="st-exec-section__body" data-section-body="treatments" {{ $sec['open'] ? '' : 'hidden' }}>
            <p style="font-size:0.76rem; color:var(--text-subtle); margin:0 0 12px;">
                Ranking 10 perawatan terlaris (item_type=procedure, exclude Service Charge) by net revenue di {{ $periodLabel }}.
            </p>
            <div class="row">
                <div class="col-lg-8 mb-3">
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--compact">
                            <thead><tr>
                                <th>#</th><th>Kode</th><th>Nama Perawatan</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Pasien</th>
                                <th class="text-right">Revenue</th>
                                <th class="text-right">Profit</th>
                            </tr></thead>
                            <tbody>
                                @forelse($topTreatments as $i => $t)
                                <tr class="st-drill-row" data-drill="treatment-{{ $t->code }}" style="cursor:pointer;" title="Klik untuk lihat trend 12 bulan">
                                    <td class="st-tnum"><i class="fa fa-caret-right st-drill-chevron" style="margin-right:5px; color:var(--brown-2);"></i>{{ $i + 1 }}</td>
                                    <td><code style="font-size:0.74rem; color:var(--brown-2);">{{ $t->code }}</code></td>
                                    <td>{{ $t->name }}</td>
                                    <td class="text-right st-tnum"><strong>{{ number_format((float) $t->qty, 0, ',', '.') }}</strong></td>
                                    <td class="text-right st-tnum">{{ number_format($t->patients) }}</td>
                                    <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($t->net) }}</td>
                                    <td class="text-right st-tnum">{{ $rpc($t->profit) }}</td>
                                </tr>
                                <tr class="st-drill-detail" data-drill-body="treatment-{{ $t->code }}" hidden>
                                    <td colspan="7" style="background:var(--cream-hover); padding:14px;">
                                        <div style="font-size:0.78rem; color:var(--text-muted); margin-bottom:6px;">
                                            <i class="fa fa-chart-line" style="color:var(--brown);"></i>
                                            Trend 12 bulan — <strong>{{ $t->name }}</strong>
                                        </div>
                                        <div class="exec-drill-box"><canvas data-drill-canvas="treatment-{{ $t->code }}"></canvas></div>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center st-muted">Belum ada data periode ini</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-4 mb-3">
                    <h6 class="st-section-title" style="margin-top:0;"><i class="fa fa-layer-group"></i> Komposisi Penjualan per Tipe</h6>
                    <div style="position:relative; margin:6px auto 12px; width:100%; max-width:220px; height:200px;">
                        <canvas id="treatmentMixChart"></canvas>
                    </div>
                    @foreach($treatmentMix as $m)
                        @php $isExcl = in_array($m->type, ['V', 'SC'], true); @endphp
                        <div class="st-kv-row" style="font-size:0.78rem;">
                            <span class="st-kv-row__label">
                                <i class="fa fa-circle" style="color:{{ $isExcl ? '#B26B00' : 'var(--brown)' }}; font-size:0.55rem;"></i>
                                {{ $m->label }}
                                @if($isExcl)<small style="color:#B26B00;">(non-perawatan)</small>@endif
                            </span>
                            <span class="st-kv-row__value">{{ $rpc($m->net) }} <small style="color:var(--text-subtle)">({{ number_format($m->pct, 1, ',', '.') }}%)</small></span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Section: Top Dokter --}}
    @php $sec = $sections['doctors']; @endphp
    <div class="st-exec-section">
        <div class="st-exec-section__header"
                data-section-toggle="doctors" aria-expanded="{{ $sec['open'] ? 'true' : 'false' }}">
            <span class="st-exec-section__left">
<i class="fa {{ $sec['icon'] }}"></i>
                <span class="st-exec-section__title">{{ $sec['title'] }} (Top 10)</span>
            </span>
            <span class="st-exec-section__right">
                <span class="st-exec-section__kpi">{{ $sec['kpi'] }}</span>
                @if($sec['sparse'])<span class="st-pill st-pill--warn">data masih dikumpulkan</span>@endif
                <button type="button" class="st-detail-btn" data-modal-section="{{ $sec['slug'] }}">
                    <i class="fa fa-expand-arrows-alt"></i> Detail
                </button>
            </span>
        </div>
        <div class="st-exec-section__body" data-section-body="doctors" {{ $sec['open'] ? '' : 'hidden' }}>
            <p style="font-size:0.76rem; color:var(--text-subtle); margin:0 0 12px;">
                Ranking 10 dokter dengan revenue tertinggi di {{ $periodLabel }}. Net = total penjualan exclude voucher &amp; service charge.
            </p>
            <div class="st-table-wrap--scroll">
                <table class="st-table st-table--compact">
                    <thead><tr>
                        <th>#</th><th>Dokter</th>
                        <th class="text-right">Kunjungan</th>
                        <th class="text-right">Pasien Unik</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">Rata/Pasien</th>
                    </tr></thead>
                    <tbody>
                        @forelse($topDoctors as $i => $d)
                        <tr class="st-drill-row" data-drill="doctor-{{ $d->id }}" style="cursor:pointer;" title="Klik untuk lihat trend 12 bulan">
                            <td class="st-tnum"><i class="fa fa-caret-right st-drill-chevron" style="margin-right:5px; color:var(--brown-2);"></i>{{ $i + 1 }}</td>
                            <td><i class="fa fa-user-md" style="color:var(--brown-pale); margin-right:5px;"></i>{{ $d->name }}</td>
                            <td class="text-right st-tnum">{{ number_format($d->visits) }}</td>
                            <td class="text-right st-tnum">{{ number_format($d->patients) }}</td>
                            <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($d->net) }}</td>
                            <td class="text-right st-tnum">{{ $rpc($d->avgPerPatient) }}</td>
                        </tr>
                        <tr class="st-drill-detail" data-drill-body="doctor-{{ $d->id }}" hidden>
                            <td colspan="6" style="background:var(--cream-hover); padding:14px;">
                                <div style="font-size:0.78rem; color:var(--text-muted); margin-bottom:6px;">
                                    <i class="fa fa-chart-line" style="color:var(--brown);"></i>
                                    Trend revenue + kunjungan 12 bulan — <strong>{{ $d->name }}</strong>
                                </div>
                                <div class="exec-drill-box"><canvas data-drill-canvas="doctor-{{ $d->id }}"></canvas></div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center st-muted">Belum ada data periode ini</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    </div>{{-- /tab-pane:topperf --}}

    {{-- ═══ TAB PANE: DISCOUNT & PROMO ═══ --}}
    <div class="exec-tab-pane" data-tab-pane="commerce" hidden>

    {{-- Section: Discount Impact --}}
    @php $sec = $sections['discount']; @endphp
    <div class="st-exec-section">
        <div class="st-exec-section__header"
                data-section-toggle="discount" aria-expanded="{{ $sec['open'] ? 'true' : 'false' }}">
            <span class="st-exec-section__left">
<i class="fa {{ $sec['icon'] }}"></i>
                <span class="st-exec-section__title">{{ $sec['title'] }}</span>
            </span>
            <span class="st-exec-section__right">
                <span class="st-exec-section__kpi">{{ $sec['kpi'] }}</span>
                @if($sec['sparse'])<span class="st-pill st-pill--warn">data masih dikumpulkan</span>@endif
                <button type="button" class="st-detail-btn" data-modal-section="{{ $sec['slug'] }}">
                    <i class="fa fa-expand-arrows-alt"></i> Detail
                </button>
            </span>
        </div>
        <div class="st-exec-section__body" data-section-body="discount" {{ $sec['open'] ? '' : 'hidden' }}>
            <p style="font-size:0.76rem; color:var(--text-subtle); margin:0 0 12px;">
                Trend diskon 12 bulan trailing s/d {{ \Carbon\Carbon::parse($end)->translatedFormat('F Y') }} +
                breakdown per tipe untuk periode aktif. % = diskon ÷ net revenue.
            </p>
            <div class="row">
                <div class="col-lg-8 mb-3">
                    <h6 class="st-section-title" style="margin-top:0;"><i class="fa fa-chart-line"></i> Trend Diskon 12 Bulan</h6>
                    <div class="exec-chart-box"><canvas id="discountChart"></canvas></div>
                </div>
                <div class="col-lg-4 mb-3">
                    <h6 class="st-section-title" style="margin-top:0;"><i class="fa fa-table"></i> Breakdown per Tipe</h6>
                    <p style="font-size:0.75rem; color:var(--text-subtle); margin-bottom:8px;">
                        Total diskon periode: <strong style="color:#B26B00;">{{ $rp($discount['totalDiscount']) }}</strong>
                    </p>
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--compact">
                            <thead><tr>
                                <th>Tipe</th>
                                <th class="text-right">Diskon</th>
                                <th class="text-right">% dari total</th>
                            </tr></thead>
                            <tbody>
                                @forelse($discount['byType'] as $t)
                                <tr>
                                    <td>{{ $t->label }}</td>
                                    <td class="text-right st-tnum" style="color:#B26B00;">{{ $rpc($t->disc) }}</td>
                                    <td class="text-right st-tnum">{{ number_format($t->pct, 1, ',', '.') }}%</td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-center st-muted">Tidak ada diskon di periode ini</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section: Top Promo --}}
    @php $sec = $sections['promos']; @endphp
    <div class="st-exec-section">
        <div class="st-exec-section__header"
                data-section-toggle="promos" aria-expanded="{{ $sec['open'] ? 'true' : 'false' }}">
            <span class="st-exec-section__left">
<i class="fa {{ $sec['icon'] }}"></i>
                <span class="st-exec-section__title">{{ $sec['title'] }} (Top 10)</span>
            </span>
            <span class="st-exec-section__right">
                <span class="st-exec-section__kpi">{{ $sec['kpi'] }}</span>
                @if($sec['sparse'])<span class="st-pill st-pill--warn">data masih dikumpulkan</span>@endif
                <button type="button" class="st-detail-btn" data-modal-section="{{ $sec['slug'] }}">
                    <i class="fa fa-expand-arrows-alt"></i> Detail
                </button>
            </span>
        </div>
        <div class="st-exec-section__body" data-section-body="promos" {{ $sec['open'] ? '' : 'hidden' }}>
            <p style="font-size:0.76rem; color:var(--text-subtle); margin:0 0 12px;">
                Ranking promo by jumlah penggunaan di reservasi periode {{ $periodLabel }}.
                Promo dilink via <code>raw_cus_reservation.raw_promo_id</code>.
                @if($topPromos->sum('uses') < 5)
                    <br><strong style="color:#B26B00;">Catatan:</strong> kolom promo di reservasi masih belum/jarang terisi —
                    angka akan otomatis hidup begitu Sales mulai pilih promo saat input reservasi.
                @endif
            </p>
            <div class="st-table-wrap--scroll">
                <table class="st-table st-table--compact">
                    <thead><tr>
                        <th>#</th><th>Kode Promo</th><th>Scope</th>
                        <th class="text-right">Penggunaan</th>
                        <th class="text-right">Total Booking</th>
                    </tr></thead>
                    <tbody>
                        @forelse($topPromos as $i => $p)
                        <tr>
                            <td class="st-tnum">{{ $i + 1 }}</td>
                            <td><code style="font-size:0.78rem; color:var(--brown-2);">{{ $p->code }}</code></td>
                            <td>
                                @if($p->scope)<span class="st-pill st-pill--light" style="font-size:0.66rem;">{{ $p->scope }}</span>@else <span class="st-muted">—</span> @endif
                            </td>
                            <td class="text-right st-tnum">
                                @if($p->uses > 0)<strong style="color:#2E7D32;">{{ number_format($p->uses) }}</strong>
                                @else <span class="st-muted">0</span> @endif
                            </td>
                            <td class="text-right st-tnum">{{ $rpc($p->booking) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center st-muted">Belum ada promo terdaftar</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>{{-- /tab-pane:commerce --}}
</div>

{{-- ═══ MODAL DRILL: detail per section ═══ --}}
<div class="modal fade" id="execDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--cream-hover); border-bottom:1px solid var(--brown-pale);">
                <h5 class="modal-title" id="execDetailModalTitle" style="color:var(--brown); font-weight:700;">Detail</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="execDetailModalBody" style="background:#fff;"></div>
        </div>
    </div>
</div>

{{-- Hidden detail templates per section (cloned into modal on click) --}}
<template id="detailtpl-patients">
    <div>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:18px;">
            Rincian lengkap pasien aktif pada periode <strong>{{ $periodLabel }}</strong>
            ({{ $branchName }}). Klik di luar atau tombol × untuk tutup.
        </p>

        {{-- 1. Per cabang --}}
        <h6 style="color:var(--brown); font-weight:700; margin-bottom:10px;">
            <i class="fa fa-hospital"></i> Pasien per Cabang
        </h6>
        <div class="st-table-wrap--scroll" style="margin-bottom:24px;">
            <table class="st-table st-table--compact">
                <thead><tr>
                    <th>Cabang</th>
                    <th class="text-right">Total Pasien</th>
                    <th class="text-right">Pasien Baru</th>
                    <th class="text-right">Returning</th>
                    <th class="text-right">Repeat Rate</th>
                </tr></thead>
                <tbody>
                    @forelse($patientByBranch as $b)
                    <tr>
                        <td>{{ $b->name }}</td>
                        <td class="text-right st-tnum"><strong>{{ number_format($b->total) }}</strong></td>
                        <td class="text-right st-tnum" style="color:#2E7D32;">{{ number_format($b->baru) }}</td>
                        <td class="text-right st-tnum" style="color:#1E5A8C;">{{ number_format($b->returning) }}</td>
                        <td class="text-right st-tnum">{{ number_format($b->repeatRate, 1, ',', '.') }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center st-muted">Belum ada data</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr style="font-weight:700; border-top:2px solid var(--brown-pale);">
                    <td>TOTAL</td>
                    <td class="text-right st-tnum">{{ number_format($patients->total) }}</td>
                    <td class="text-right st-tnum" style="color:#2E7D32;">{{ number_format($patients->baru) }}</td>
                    <td class="text-right st-tnum" style="color:#1E5A8C;">{{ number_format($patients->returning) }}</td>
                    <td class="text-right st-tnum">{{ number_format($retention->repeatRate, 1, ',', '.') }}%</td>
                </tr></tfoot>
            </table>
        </div>

        {{-- 2. Trend 12 bulan --}}
        <h6 style="color:var(--brown); font-weight:700; margin-bottom:10px;">
            <i class="fa fa-chart-line"></i> Trend Pasien 12 Bulan Terakhir
        </h6>
        <div class="exec-chart-box" style="margin-bottom:24px;">
            <canvas id="modalPatientTrend"></canvas>
        </div>

        {{-- 3. Top 5 dokter by pasien --}}
        <h6 style="color:var(--brown); font-weight:700; margin-bottom:10px;">
            <i class="fa fa-user-md"></i> Top 5 Dokter (by Jumlah Pasien)
        </h6>
        <div class="st-table-wrap--scroll">
            <table class="st-table st-table--compact">
                <thead><tr>
                    <th>#</th><th>Dokter</th>
                    <th class="text-right">Pasien Unik</th>
                    <th class="text-right">Kunjungan</th>
                    <th class="text-right">Revenue</th>
                </tr></thead>
                <tbody>
                    @forelse($topDoctorsByPasien as $i => $d)
                    <tr>
                        <td class="st-tnum">{{ $i + 1 }}</td>
                        <td><i class="fa fa-user-md" style="color:var(--brown-pale); margin-right:5px;"></i>{{ $d->name }}</td>
                        <td class="text-right st-tnum"><strong>{{ number_format($d->patients) }}</strong></td>
                        <td class="text-right st-tnum">{{ number_format($d->visits) }}</td>
                        <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($d->net) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center st-muted">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</template>

{{-- ─── Achievement detail ─── --}}
<template id="detailtpl-achievement">
    <div>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:18px;">
            Rincian pencapaian target per cabang untuk periode <strong>{{ $periodLabel }}</strong>.
            Achievement = Actual ÷ Target.
        </p>
        <div class="st-table-wrap--scroll">
            <table class="st-table st-table--compact">
                <thead><tr>
                    <th>Cabang</th>
                    <th class="text-right">Target</th>
                    <th class="text-right">Actual</th>
                    <th class="text-right">Booking</th>
                    <th class="text-right">% vs Target</th>
                    <th class="text-right">Tercapai</th>
                    <th class="text-right">Sisa</th>
                    <th class="text-right">Konversi Booking→Actual</th>
                </tr></thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr>
                        <td><strong>{{ $r->name }}</strong></td>
                        <td class="text-right st-tnum">{{ $rpc($r->target) }}</td>
                        <td class="text-right st-tnum" style="color:#2E7D32;"><strong>{{ $rpc($r->actual) }}</strong></td>
                        <td class="text-right st-tnum" style="color:#1E5A8C;">{{ $rpc($r->booking) }}</td>
                        <td class="text-right st-tnum">
                            @if($r->pct !== null)
                                <span style="font-weight:700; color:{{ $r->pct >= 100 ? '#2E7D32' : ($r->pct >= 70 ? '#B26B00' : '#B23A3A') }};">
                                    {{ number_format($r->pct, 1, ',', '.') }}%
                                </span>
                            @else <span style="color:var(--text-subtle);">—</span> @endif
                        </td>
                        <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($r->actual) }}</td>
                        <td class="text-right st-tnum" style="color:#B23A3A;">{{ $rpc($r->remaining) }}</td>
                        <td class="text-right st-tnum">{{ $r->conv !== null ? number_format($r->conv, 1, ',', '.') . '%' : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center st-muted">Belum ada data</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr style="font-weight:700; border-top:2px solid var(--brown-pale);">
                    <td>TOTAL</td>
                    <td class="text-right st-tnum">{{ $rpc($totals->target) }}</td>
                    <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($totals->actual) }}</td>
                    <td class="text-right st-tnum" style="color:#1E5A8C;">{{ $rpc($totals->booking) }}</td>
                    <td class="text-right st-tnum">{{ $totals->pct !== null ? number_format($totals->pct, 1, ',', '.') . '%' : '—' }}</td>
                    <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($totals->actual) }}</td>
                    <td class="text-right st-tnum" style="color:#B23A3A;">{{ $rpc($totals->remaining) }}</td>
                    <td class="text-right st-tnum">{{ $totals->conv !== null ? number_format($totals->conv, 1, ',', '.') . '%' : '—' }}</td>
                </tr></tfoot>
            </table>
        </div>
    </div>
</template>

{{-- ─── Growth detail ─── --}}
<template id="detailtpl-growth">
    <div>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:18px;">
            Trend revenue &amp; pasien 12 bulan terakhir (MoM). Section utama sudah memuat chart MoM &amp; DoD.
        </p>
        <div class="st-table-wrap--scroll">
            <table class="st-table st-table--compact">
                <thead><tr>
                    <th>Bulan</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Booking</th>
                    <th class="text-right">Target</th>
                    <th class="text-right">Pasien</th>
                    <th class="text-right">MoM Growth</th>
                </tr></thead>
                <tbody>
                    @foreach($monthly['labels'] as $i => $lbl)
                    <tr>
                        <td><strong>{{ $lbl }}</strong></td>
                        <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($monthly['revenue'][$i]) }}</td>
                        <td class="text-right st-tnum" style="color:#1E5A8C;">{{ $rpc($monthly['bookingArr'][$i]) }}</td>
                        <td class="text-right st-tnum">{{ $rpc($monthly['targetArr'][$i]) }}</td>
                        <td class="text-right st-tnum">{{ number_format($monthly['patients'][$i]) }}</td>
                        <td class="text-right st-tnum">{!! $growthBadge($monthly['growth'][$i]) !!}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</template>

{{-- ─── YoY detail ─── --}}
<template id="detailtpl-yoy">
    <div>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:18px;">
            Revenue per cabang × tahun (periode yang sama digeser ke tiap tahun) +
            ringkasan growth per tahun.
        </p>
        <h6 style="color:var(--brown); font-weight:700; margin-bottom:10px;">
            <i class="fa fa-th"></i> Per Cabang × Tahun
        </h6>
        <div class="st-table-wrap--scroll" style="margin-bottom:22px;">
            <table class="st-table st-table--compact">
                <thead><tr>
                    <th>Cabang</th>
                    @foreach($yoy['years'] as $yr)<th class="text-right">{{ $yr }}</th>@endforeach
                    <th class="text-right">YoY (terakhir)</th>
                </tr></thead>
                <tbody>
                    @forelse($yoy['matrix'] as $m)
                    <tr>
                        <td><strong>{{ $m->name }}</strong></td>
                        @foreach($m->values as $v)<td class="text-right st-tnum">{{ $rpc($v) }}</td>@endforeach
                        <td class="text-right st-tnum">{!! $growthBadge($m->growth) !!}</td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ count($yoy['years']) + 2 }}" class="text-center st-muted">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <h6 style="color:var(--brown); font-weight:700; margin-bottom:10px;">
            <i class="fa fa-table"></i> Total per Tahun
        </h6>
        <div class="st-table-wrap--scroll">
            <table class="st-table st-table--compact">
                <thead><tr><th>Tahun</th><th class="text-right">Revenue</th><th class="text-right">Pasien</th><th class="text-right">YoY</th></tr></thead>
                <tbody>
                    @foreach($yoy['years'] as $i => $yr)
                    <tr>
                        <td><strong>{{ $yr }}</strong></td>
                        <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($yoy['revenue'][$i]) }}</td>
                        <td class="text-right st-tnum">{{ number_format($yoy['patients'][$i]) }}</td>
                        <td class="text-right st-tnum">{!! $growthBadge($yoy['growth'][$i]) !!}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</template>

{{-- ─── Treatments detail ─── --}}
<template id="detailtpl-treatments">
    <div>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:18px;">
            Top 10 perawatan terlaris untuk <strong>{{ $periodLabel }}</strong>. Klik di section utama untuk drill per-treatment trend 12 bulan.
        </p>
        <div class="st-table-wrap--scroll">
            <table class="st-table st-table--compact">
                <thead><tr>
                    <th>#</th><th>Kode</th><th>Nama Perawatan</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Pasien</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Profit</th>
                    <th class="text-right">Margin %</th>
                </tr></thead>
                <tbody>
                    @forelse($topTreatments as $i => $t)
                    @php $margin = $t->net > 0 ? round($t->profit / $t->net * 100, 1) : 0; @endphp
                    <tr>
                        <td class="st-tnum">{{ $i + 1 }}</td>
                        <td><code style="font-size:0.74rem;">{{ $t->code }}</code></td>
                        <td>{{ $t->name }}</td>
                        <td class="text-right st-tnum"><strong>{{ number_format((float) $t->qty, 0, ',', '.') }}</strong></td>
                        <td class="text-right st-tnum">{{ number_format($t->patients) }}</td>
                        <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($t->net) }}</td>
                        <td class="text-right st-tnum">{{ $rpc($t->profit) }}</td>
                        <td class="text-right st-tnum">{{ number_format($margin, 1, ',', '.') }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center st-muted">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</template>

{{-- ─── Doctors detail ─── --}}
<template id="detailtpl-doctors">
    <div>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:18px;">
            Ranking 10 dokter by revenue untuk <strong>{{ $periodLabel }}</strong>.
            Klik di section utama untuk drill trend 12 bulan per dokter.
        </p>
        <div class="st-table-wrap--scroll">
            <table class="st-table st-table--compact">
                <thead><tr>
                    <th>#</th><th>Dokter</th>
                    <th class="text-right">Kunjungan</th>
                    <th class="text-right">Pasien Unik</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Avg/Pasien</th>
                </tr></thead>
                <tbody>
                    @forelse($topDoctors as $i => $d)
                    <tr>
                        <td class="st-tnum">{{ $i + 1 }}</td>
                        <td><i class="fa fa-user-md" style="color:var(--brown-pale); margin-right:5px;"></i>{{ $d->name }}</td>
                        <td class="text-right st-tnum">{{ number_format($d->visits) }}</td>
                        <td class="text-right st-tnum"><strong>{{ number_format($d->patients) }}</strong></td>
                        <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($d->net) }}</td>
                        <td class="text-right st-tnum">{{ $rpc($d->avgPerPatient) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center st-muted">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</template>

{{-- ─── Discount detail ─── --}}
<template id="detailtpl-discount">
    <div>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:18px;">
            Trend diskon 12 bulan + breakdown per tipe item. Total diskon periode:
            <strong style="color:#B26B00;">{{ $rp($discount['totalDiscount']) }}</strong>.
        </p>
        <h6 style="color:var(--brown); font-weight:700; margin-bottom:10px;">
            <i class="fa fa-table"></i> Trend Bulanan
        </h6>
        <div class="st-table-wrap--scroll" style="margin-bottom:22px;">
            <table class="st-table st-table--compact">
                <thead><tr><th>Bulan</th><th class="text-right">Diskon</th><th class="text-right">Revenue Net</th><th class="text-right">% vs Net</th></tr></thead>
                <tbody>
                    @foreach($discount['labels'] as $i => $lbl)
                    <tr>
                        <td><strong>{{ $lbl }}</strong></td>
                        <td class="text-right st-tnum" style="color:#B26B00;">{{ $rpc($discount['discount'][$i]) }}</td>
                        <td class="text-right st-tnum" style="color:#2E7D32;">{{ $rpc($discount['net'][$i]) }}</td>
                        <td class="text-right st-tnum">{{ number_format($discount['pct'][$i], 1, ',', '.') }}%</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <h6 style="color:var(--brown); font-weight:700; margin-bottom:10px;">
            <i class="fa fa-layer-group"></i> Breakdown per Tipe
        </h6>
        <div class="st-table-wrap--scroll">
            <table class="st-table st-table--compact">
                <thead><tr><th>Tipe</th><th class="text-right">Diskon</th><th class="text-right">% dari Total</th></tr></thead>
                <tbody>
                    @forelse($discount['byType'] as $t)
                    <tr>
                        <td><strong>{{ $t->label }}</strong></td>
                        <td class="text-right st-tnum" style="color:#B26B00;">{{ $rpc($t->disc) }}</td>
                        <td class="text-right st-tnum">{{ number_format($t->pct, 1, ',', '.') }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center st-muted">Tidak ada diskon</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</template>

{{-- ─── Promos detail ─── --}}
<template id="detailtpl-promos">
    <div>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:18px;">
            Top 10 promo by penggunaan untuk <strong>{{ $periodLabel }}</strong>. Data dari
            <code>raw_cus_reservation.raw_promo_id</code>.
            @if($topPromos->sum('uses') < 5)
                <br><strong style="color:#B26B00;">Catatan:</strong> kolom promo_id di reservasi masih jarang terisi.
            @endif
        </p>
        <div class="st-table-wrap--scroll">
            <table class="st-table st-table--compact">
                <thead><tr>
                    <th>#</th><th>Kode Promo</th><th>Scope</th>
                    <th class="text-right">Penggunaan</th>
                    <th class="text-right">Total Booking</th>
                </tr></thead>
                <tbody>
                    @forelse($topPromos as $i => $p)
                    <tr>
                        <td class="st-tnum">{{ $i + 1 }}</td>
                        <td><code style="font-size:0.78rem;">{{ $p->code }}</code></td>
                        <td>@if($p->scope)<span class="st-pill st-pill--light" style="font-size:0.66rem;">{{ $p->scope }}</span>@else <span class="st-muted">—</span> @endif</td>
                        <td class="text-right st-tnum">
                            @if($p->uses > 0)<strong style="color:#2E7D32;">{{ number_format($p->uses) }}</strong>
                            @else <span class="st-muted">0</span> @endif
                        </td>
                        <td class="text-right st-tnum">{{ $rpc($p->booking) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center st-muted">Belum ada promo</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</template>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function () {
    const fmtRp = (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v);

    // ── Tren revenue & pasien 12 bulan (informatif: sumbu + tooltip + 2 axis) ──
    (function () {
        const el = document.getElementById('heroSparkline'); if (!el) return;
        const revenue = @json($monthly['revenue']);
        const avg = revenue.length ? revenue.reduce((a, b) => a + b, 0) / revenue.length : 0;
        new Chart(el, {
            data: {
                labels: @json($monthly['labels']),
                datasets: [
                    {
                        type: 'bar', label: 'Revenue', yAxisID: 'y',
                        data: revenue,
                        backgroundColor: 'rgba(70,21,0,0.82)',
                        hoverBackgroundColor: 'rgba(70,21,0,1)',
                        borderRadius: 4, order: 2,
                    },
                    {
                        type: 'line', label: 'Jumlah Pasien', yAxisID: 'y1',
                        data: @json($monthly['patients']),
                        borderColor: '#1E5A8C', backgroundColor: '#1E5A8C',
                        borderWidth: 2.5, tension: 0.3, pointRadius: 3,
                        pointHoverRadius: 5, order: 1,
                    },
                    {
                        type: 'line', label: 'Rata-rata Revenue', yAxisID: 'y',
                        data: revenue.map(() => avg),
                        borderColor: '#B26B00', borderDash: [6, 4], borderWidth: 1.5,
                        pointRadius: 0, fill: false, order: 0,
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 14, font: { size: 12 }, padding: 14 } },
                    tooltip: {
                        callbacks: {
                            label: (c) => c.dataset.yAxisID === 'y1'
                                ? '  ' + c.dataset.label + ': ' + new Intl.NumberFormat('id-ID').format(c.parsed.y) + ' pasien'
                                : '  ' + c.dataset.label + ': ' + fmtRp(c.parsed.y),
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                    y: {
                        position: 'left', beginAtZero: true,
                        title: { display: true, text: 'Revenue (Rp)', font: { size: 11, weight: '600' }, color: '#461500' },
                        ticks: { font: { size: 10 }, callback: (v) => 'Rp ' + (v / 1e6).toFixed(0) + ' jt' },
                        grid: { color: 'rgba(0,0,0,0.05)' },
                    },
                    y1: {
                        position: 'right', beginAtZero: true,
                        title: { display: true, text: 'Pasien', font: { size: 11, weight: '600' }, color: '#1E5A8C' },
                        ticks: { font: { size: 10 } },
                        grid: { drawOnChartArea: false },
                    },
                },
            },
        });
    })();

    // ── Donut: Baru vs Returning ──
    new Chart(document.getElementById('patientDonut'), {
        type: 'doughnut',
        data: {
            labels: ['Baru', 'Returning'],
            datasets: [{
                data: [{{ $patients->baru }}, {{ $patients->returning }}],
                backgroundColor: ['#2E7D32', '#1E5A8C'],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '64%',
            plugins: { legend: { display: false } },
        },
    });

    // ── MoM: revenue (bar) + pasien (line, sumbu kanan) ──
    new Chart(document.getElementById('momChart'), {
        data: {
            labels: @json($monthly['labels']),
            datasets: [
                {
                    type: 'bar', label: 'Revenue', yAxisID: 'y',
                    data: @json($monthly['revenue']),
                    backgroundColor: 'rgba(70,21,0,0.78)', borderRadius: 4, order: 2,
                },
                {
                    type: 'line', label: 'Target', yAxisID: 'y',
                    data: @json($monthly['targetArr']),
                    borderColor: '#B26B00', borderDash: [5, 4], borderWidth: 2,
                    pointRadius: 0, tension: 0, order: 0,
                },
                {
                    type: 'line', label: 'Pasien', yAxisID: 'y1',
                    data: @json($monthly['patients']),
                    borderColor: '#1E5A8C', backgroundColor: '#1E5A8C',
                    borderWidth: 2, tension: .3, pointRadius: 3, order: 1,
                },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: { callbacks: { label: (c) => c.dataset.yAxisID === 'y1'
                    ? c.dataset.label + ': ' + c.parsed.y
                    : c.dataset.label + ': ' + fmtRp(c.parsed.y) } },
            },
            scales: {
                y:  { position: 'left',  ticks: { callback: (v) => 'Rp ' + (v / 1e6).toFixed(0) + ' jt' } },
                y1: { position: 'right', grid: { drawOnChartArea: false }, beginAtZero: true,
                      title: { display: true, text: 'Pasien' } },
            },
        },
    });

    // ── DoD: revenue (bar) + pasien (line, sumbu kanan) ──
    new Chart(document.getElementById('dodChart'), {
        data: {
            labels: @json($daily['labels']),
            datasets: [
                {
                    type: 'bar', label: 'Revenue', yAxisID: 'y',
                    data: @json($daily['revenue']),
                    backgroundColor: 'rgba(70,21,0,0.70)', borderRadius: 3, order: 1,
                },
                {
                    type: 'line', label: 'Pasien', yAxisID: 'y1',
                    data: @json($daily['patients']),
                    borderColor: '#1E5A8C', backgroundColor: '#1E5A8C',
                    borderWidth: 2, tension: .3, pointRadius: 2, order: 0,
                },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: { callbacks: { label: (c) => c.dataset.yAxisID === 'y1'
                    ? c.dataset.label + ': ' + c.parsed.y
                    : c.dataset.label + ': ' + fmtRp(c.parsed.y) } },
            },
            scales: {
                x:  { title: { display: true, text: 'Tanggal' } },
                y:  { position: 'left',  ticks: { callback: (v) => 'Rp ' + (v / 1e6).toFixed(1) + ' jt' } },
                y1: { position: 'right', grid: { drawOnChartArea: false }, beginAtZero: true,
                      title: { display: true, text: 'Pasien' } },
            },
        },
    });

    // ── YoY: revenue (bar) + pasien (line, sumbu kanan) per tahun ──
    new Chart(document.getElementById('yoyChart'), {
        data: {
            labels: @json($yoy['years']),
            datasets: [
                {
                    type: 'bar', label: 'Revenue', yAxisID: 'y',
                    data: @json($yoy['revenue']),
                    backgroundColor: 'rgba(70,21,0,0.78)', borderRadius: 4, order: 1,
                },
                {
                    type: 'line', label: 'Pasien', yAxisID: 'y1',
                    data: @json($yoy['patients']),
                    borderColor: '#1E5A8C', backgroundColor: '#1E5A8C',
                    borderWidth: 2, tension: .3, pointRadius: 3, order: 0,
                },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: { callbacks: { label: (c) => c.dataset.yAxisID === 'y1'
                    ? c.dataset.label + ': ' + c.parsed.y
                    : c.dataset.label + ': ' + fmtRp(c.parsed.y) } },
            },
            scales: {
                y:  { position: 'left',  ticks: { callback: (v) => 'Rp ' + (v / 1e6).toFixed(0) + ' jt' } },
                y1: { position: 'right', grid: { drawOnChartArea: false }, beginAtZero: true,
                      title: { display: true, text: 'Pasien' } },
            },
        },
    });
})();

// ── Treatment mix donut (Step 4) ──
(function () {
    const el = document.getElementById('treatmentMixChart'); if (!el) return;
    const mix = @json($treatmentMix->values());
    if (!mix.length) return;
    const palette = { 'P':'#461500', 'D':'#8C5A3B', 'SC':'#B26B00', 'V':'#C9A66B', 'S':'#6B4433' };
    new Chart(el, {
        type: 'doughnut',
        data: {
            labels: mix.map(r => r.label),
            datasets: [{
                data: mix.map(r => r.net),
                backgroundColor: mix.map(r => palette[r.type] || '#999'),
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '60%',
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (c) => c.label + ': ' + fmtRp(c.parsed) } },
            },
        },
    });
})();

// ── Discount trend chart (12 bulan + % line) ──
(function () {
    const el = document.getElementById('discountChart'); if (!el) return;
    new Chart(el, {
        data: {
            labels: @json($discount['labels']),
            datasets: [
                {
                    type: 'bar', label: 'Diskon', yAxisID: 'y',
                    data: @json($discount['discount']),
                    backgroundColor: 'rgba(178,107,0,0.78)', borderRadius: 4, order: 1,
                },
                {
                    type: 'line', label: '% vs Net', yAxisID: 'y1',
                    data: @json($discount['pct']),
                    borderColor: '#1E5A8C', backgroundColor: '#1E5A8C',
                    borderWidth: 2, tension: .3, pointRadius: 3, order: 0,
                },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: { callbacks: { label: (c) => c.dataset.yAxisID === 'y1'
                    ? c.dataset.label + ': ' + c.parsed.y + '%'
                    : c.dataset.label + ': ' + fmtRp(c.parsed.y) } },
            },
            scales: {
                y:  { position: 'left',  ticks: { callback: (v) => 'Rp ' + (v / 1e6).toFixed(1) + ' jt' } },
                y1: { position: 'right', grid: { drawOnChartArea: false }, beginAtZero: true,
                      title: { display: true, text: '%' },
                      ticks: { callback: (v) => v + '%' } },
            },
        },
    });
})();

// ── Layer 3 drill: lazy-init chart on row click ──
(function () {
    const trends = {
        treatment: @json($treatmentTrends),
        doctor: @json($doctorTrends),
    };
    const charted = new Set();
    document.addEventListener('click', (e) => {
        const row = e.target.closest('.st-drill-row');
        if (!row) return;
        const key = row.dataset.drill; // e.g., treatment-26CON178 / doctor-13700
        const body = document.querySelector(`[data-drill-body="${CSS.escape(key)}"]`);
        if (!body) return;
        const opening = body.hasAttribute('hidden');
        if (opening) body.removeAttribute('hidden'); else body.setAttribute('hidden', '');
        row.classList.toggle('is-open', opening);
        if (opening && !charted.has(key)) {
            const sep = key.indexOf('-');
            const kind = key.slice(0, sep);     // 'treatment' / 'doctor'
            const id   = key.slice(sep + 1);
            const data = trends[kind] && trends[kind][id];
            const canvas = body.querySelector('[data-drill-canvas]');
            if (data && canvas) {
                new Chart(canvas, {
                    data: {
                        labels: data.labels,
                        datasets: [
                            {
                                type: 'bar', label: 'Revenue', yAxisID: 'y',
                                data: data.revenue,
                                backgroundColor: 'rgba(70,21,0,0.78)', borderRadius: 3, order: 1,
                            },
                            {
                                type: 'line', label: kind === 'treatment' ? 'Qty' : 'Kunjungan', yAxisID: 'y1',
                                data: data.qty,
                                borderColor: '#1E5A8C', backgroundColor: '#1E5A8C',
                                borderWidth: 2, tension: .3, pointRadius: 2, order: 0,
                            },
                        ],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } },
                            tooltip: { callbacks: { label: (c) => c.dataset.yAxisID === 'y1'
                                ? c.dataset.label + ': ' + c.parsed.y
                                : c.dataset.label + ': ' + fmtRp(c.parsed.y) } },
                        },
                        scales: {
                            y:  { position: 'left',  ticks: { font: { size: 9 }, callback: (v) => 'Rp ' + (v / 1e6).toFixed(0) + ' jt' } },
                            y1: { position: 'right', grid: { drawOnChartArea: false }, beginAtZero: true,
                                  ticks: { font: { size: 9 } } },
                        },
                    },
                });
                charted.add(key);
            }
        }
    });
})();

// ── Filter collapse toggle ──
(function () {
    const btn = document.getElementById('execFilterToggle');
    const body = document.getElementById('execFilterBody');
    if (!btn || !body) return;
    const KEY = 'executiveReport.filterOpen';
    const setOpen = (open) => {
        btn.classList.toggle('is-open', open);
        body.style.display = open ? '' : 'none';
        const chev = btn.querySelector('.exec-filter-toggle__chevron');
        if (chev) chev.classList.toggle('fa-chevron-up', open), chev.classList.toggle('fa-chevron-down', !open);
    };
    let saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) {}
    if (saved === '0') setOpen(false);
    btn.addEventListener('click', () => {
        const next = !btn.classList.contains('is-open');
        setOpen(next);
        try { localStorage.setItem(KEY, next ? '1' : '0'); } catch (e) {}
    });
})();

// ── Tab navigation: switch panes + Chart.js resize trigger ──
(function () {
    const tabs = document.querySelectorAll('.exec-tab');
    const panes = document.querySelectorAll('.exec-tab-pane');
    if (!tabs.length) return;
    const KEY = 'executiveReport.tab';

    const setActive = (slug) => {
        tabs.forEach(t => t.classList.toggle('is-active', t.dataset.tab === slug));
        panes.forEach(p => {
            if (p.dataset.tabPane === slug) p.removeAttribute('hidden');
            else p.setAttribute('hidden', '');
        });
        // Chart.js perlu re-size saat canvas yang sebelumnya hidden jadi visible
        requestAnimationFrame(() => {
            try { Chart.helpers.each(Chart.instances, (c) => c.resize()); } catch (e) {}
        });
    };

    let saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) {}
    if (saved && document.querySelector(`[data-tab="${saved}"]`)) setActive(saved);

    tabs.forEach(t => t.addEventListener('click', () => {
        const slug = t.dataset.tab;
        setActive(slug);
        try { localStorage.setItem(KEY, slug); } catch (e) {}
    }));
})();

// ── Modal drill: "Lihat Detail Lengkap" per section ──
(function () {
    const modal = document.getElementById('execDetailModal');
    if (!modal) return;
    const titleEl = document.getElementById('execDetailModalTitle');
    const bodyEl  = document.getElementById('execDetailModalBody');
    const titles  = @json(collect($sections)->mapWithKeys(fn($s,$k) => [$k => $s['title']])->all());

    let modalCharts = []; // cleanup on close
    const initPatientModalCharts = () => {
        const trendEl = bodyEl.querySelector('#modalPatientTrend');
        if (trendEl) {
            modalCharts.push(new Chart(trendEl, {
                data: {
                    labels: @json($patientTrend['labels']),
                    datasets: [
                        { type:'bar', label:'Total Pasien', yAxisID:'y',
                          data: @json($patientTrend['total']),
                          backgroundColor:'rgba(30,90,140,0.78)', borderRadius:3, order:1 },
                        { type:'line', label:'Pasien Baru', yAxisID:'y',
                          data: @json($patientTrend['baru']),
                          borderColor:'#2E7D32', backgroundColor:'#2E7D32',
                          borderWidth:2, tension:.3, pointRadius:3, order:0 },
                    ],
                },
                options: {
                    responsive:true, maintainAspectRatio:false,
                    interaction:{ mode:'index', intersect:false },
                    plugins:{
                        legend:{ position:'bottom', labels:{ boxWidth:12, font:{ size:11 } } },
                    },
                    scales:{ y:{ beginAtZero:true, ticks:{ font:{ size:10 } } } },
                },
            }));
        }
    };

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-modal-section]');
        if (!btn) return;
        e.preventDefault();
        const key = btn.dataset.modalSection;
        const tpl = document.getElementById('detailtpl-' + key);
        if (!tpl) return;
        // Cleanup previous charts
        modalCharts.forEach(c => { try { c.destroy(); } catch(e){} });
        modalCharts = [];
        // Swap content
        titleEl.textContent = titles[key] || 'Detail';
        bodyEl.innerHTML = '';
        bodyEl.appendChild(tpl.content.cloneNode(true));
        // Init relevant charts
        if (key === 'patients') initPatientModalCharts();
        // Show
        $(modal).modal('show');
    });

    $(modal).on('hidden.bs.modal', () => {
        modalCharts.forEach(c => { try { c.destroy(); } catch(e){} });
        modalCharts = [];
        bodyEl.innerHTML = '';
    });
})();
</script>
@endsection
