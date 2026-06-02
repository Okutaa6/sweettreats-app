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

    {{-- Filter periode --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="st-card"><div class="st-card__body" style="padding:1.15rem 1.4rem;">
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
                    </div>

                    <div style="margin-top:16px; padding-top:13px; border-top:1px dashed var(--divider-strong);
                                display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                        <span style="font-size:0.73rem; color:var(--text-subtle); font-weight:600;">
                            <i class="fa fa-bolt" style="color:var(--brown-2);"></i> Pintasan
                        </span>
                        @foreach($presets as $label => $range)
                            @php $ap = ($start === $range[0] && $end === $range[1]); @endphp
                            <a href="?{{ $qs(['date_from' => $range[0], 'date_to' => $range[1]]) }}"
                               style="text-decoration:none; padding:5px 14px; font-size:0.75rem; border-radius:20px;
                               {{ $ap
                                  ? 'background:var(--brown); color:#fff; font-weight:600;'
                                  : 'background:var(--cream-hover); color:var(--text-muted); border:1px solid var(--brown-pale);' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </form>
            </div></div>
        </div>
    </div>

    @if($totals->actual <= 0)
        <div style="background:#FFF8F0; border-left:3px solid #B26B00; padding:9px 14px; margin-bottom:16px;
                    border-radius:6px; font-size:0.83rem; color:var(--text-muted);">
            <i class="fa fa-info-circle" style="color:#B26B00;"></i>
            Revenue <strong>actual</strong> (Omnicare) untuk periode ini masih 0 — pastikan sync nightly /
            backfill sudah dijalankan. Angka <strong>booking</strong> &amp; target tetap tampil.
        </div>
    @endif

    {{-- ═══ KPI CARDS ═══ --}}
    <div class="row mb-2">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-cash-register mr-1"></i> Revenue Actual</p>
                <p class="st-dash-card__value" style="color:#2E7D32">{{ $rp($totals->actual) }}</p>
                <small style="color:var(--text-subtle)">uang nyata masuk (Omnicare)</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-calendar-check mr-1"></i> Revenue Booking</p>
                <p class="st-dash-card__value" style="color:#1E5A8C">{{ $rp($totals->booking) }}</p>
                <small style="color:var(--text-subtle)">
                    nilai reservasi
                    @if($totals->conv !== null)· konversi {{ number_format($totals->conv, 1, ',', '.') }}%@endif
                </small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-bullseye mr-1"></i> Realisasi vs Target</p>
                <p class="st-dash-card__value" style="color:var(--brown)">
                    {{ $totals->pct !== null ? number_format($totals->pct, 1, ',', '.') . '%' : '—' }}
                </p>
                <small style="color:var(--text-subtle)">target {{ $rpc($totals->target) }}</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-users mr-1"></i> Total Pasien</p>
                <p class="st-dash-card__value">{{ number_format($patients->total) }}</p>
                <small style="color:var(--text-subtle)">
                    <span style="color:#2E7D32;">{{ number_format($patients->baru) }} baru</span> ·
                    {{ number_format($patients->returning) }} returning
                </small>
            </div>
        </div>
    </div>

    {{-- ═══ TARGET ACHIEVEMENT + NEW vs RETURNING ═══ --}}
    <div class="row">
        {{-- Achievement summary --}}
        <div class="col-lg-8 mb-3">
            <div class="st-card h-100"><div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-bullseye"></i> Pencapaian Target — {{ $periodLabel }}</h6>

                @if($spansMonth)
                    <p style="font-size:0.74rem; color:#B26B00; margin-top:-6px; margin-bottom:10px;">
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
            </div></div>
        </div>

        {{-- New vs Returning --}}
        <div class="col-lg-4 mb-3">
            <div class="st-card h-100"><div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-user-plus"></i> Pasien Baru vs Returning</h6>
                @php
                    $pt = max($patients->total, 1);
                    $pctBaru = round($patients->baru / $pt * 100, 1);
                    $pctRet  = round($patients->returning / $pt * 100, 1);
                @endphp

                <div style="position:relative; margin:6px auto 16px; width:100%; max-width:230px;">
                    <canvas id="patientDonut" height="200"></canvas>
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
            </div></div>
        </div>
    </div>

    {{-- ═══ YoY COMPARISON ═══ --}}
    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="st-card h-100"><div class="st-card__body">
                <h6 class="st-section-title">
                    <i class="fa fa-calendar"></i> Perbandingan Year-over-Year — periode yang sama, antar tahun
                </h6>
                <p style="font-size:0.76rem; color:var(--text-subtle); margin-top:-6px; margin-bottom:12px;">
                    Berbasis <strong>revenue actual (Omnicare)</strong> &amp; jumlah pasien. Booking &amp; target
                    tidak ikut karena hanya tersedia tahun berjalan.
                </p>
                <canvas id="yoyChart" height="120"></canvas>
            </div></div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="st-card h-100"><div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-table"></i> Per Tahun</h6>
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
            </div></div>
        </div>
    </div>

    {{-- Matriks per cabang × tahun --}}
    <div class="row">
        <div class="col-12 mb-3">
            <div class="st-card"><div class="st-card__body">
                <h6 class="st-section-title">
                    <i class="fa fa-th"></i> Revenue per Cabang × Tahun
                </h6>
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

    {{-- ═══ MoM GROWTH ═══ --}}
    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="st-card h-100"><div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-chart-line"></i> Revenue &amp; Patient Growth — 12 Bulan (MoM)</h6>
                <canvas id="momChart" height="110"></canvas>
            </div></div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="st-card h-100"><div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-table"></i> Detail Bulanan</h6>
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
            </div></div>
        </div>
    </div>

    {{-- ═══ DoD GROWTH ═══ --}}
    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="st-card h-100"><div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-calendar-day"></i> Revenue &amp; Patient Harian (DoD)</h6>
                <canvas id="dodChart" height="110"></canvas>
            </div></div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="st-card h-100"><div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-table"></i> Detail Harian</h6>
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
            </div></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function () {
    const fmtRp = (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v);

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
            responsive: true, maintainAspectRatio: true,
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
            responsive: true, maintainAspectRatio: true,
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
            responsive: true, maintainAspectRatio: true,
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
</script>
@endsection
