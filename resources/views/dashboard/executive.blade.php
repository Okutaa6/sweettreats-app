@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <form class="form-inline" method="GET">
                        <select name="month" class="form-control form-control-sm mr-1">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create(null, $m)->format('F') }}
                                </option>
                            @endfor
                        </select>
                        <select name="year" class="form-control form-control-sm mr-1">
                            @for($y = now()->year; $y >= now()->year - 3; $y--)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                        <button class="st-btn-brand"><i class="fa fa-filter"></i> Filter</button>
                    </form>
                </div>
                <h4 class="page-title">
                    <i class="fa fa-chart-line mr-2" style="color:var(--brown)"></i>
                    Executive Dashboard
                </h4>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row mb-3">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label">Total Revenue</p>
                <p class="st-dash-card__value" style="color:#2E7D32">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label">Target Bulan Ini</p>
                <p class="st-dash-card__value" style="color:#1E5A8C">Rp {{ number_format($totalTarget, 0, ',', '.') }}</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label">Pencapaian</p>
                <p class="st-dash-card__value" style="color:{{ $totalPct >= 100 ? '#2E7D32' : ($totalPct >= 70 ? '#B26B00' : '#B23A3A') }}">{{ $totalPct }}%</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label">Total Reservasi</p>
                <p class="st-dash-card__value">{{ $totalCount }}</p>
            </div>
        </div>
    </div>

    {{-- Revenue per Branch --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="st-card">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-building"></i> Revenue per Cabang</h6>
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--compact">
                            <thead>
                                <tr>
                                    <th>Cabang</th>
                                    <th class="text-right">Reservasi</th>
                                    <th class="text-right">Revenue</th>
                                    <th class="text-right">Target</th>
                                    <th style="width:30%">Progress</th>
                                    <th class="text-right">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($branchData as $b)
                                <tr>
                                    <td>{{ $b['name'] }}</td>
                                    <td class="text-right st-tnum">{{ $b['count'] }}</td>
                                    <td class="text-right st-tnum">Rp {{ number_format($b['revenue'], 0, ',', '.') }}</td>
                                    <td class="text-right st-tnum">Rp {{ number_format($b['target'], 0, ',', '.') }}</td>
                                    <td>
                                        <div class="st-progress-target">
                                            <div class="bar" style="width:{{ min($b['pct'], 100) }}%; background:{{ $b['pct'] >= 100 ? '#2E7D32' : ($b['pct'] >= 70 ? '#B26B00' : '#B23A3A') }}"></div>
                                        </div>
                                    </td>
                                    <td class="text-right st-tnum" style="font-weight:700; color:{{ $b['pct'] >= 100 ? '#2E7D32' : ($b['pct'] >= 70 ? '#B26B00' : '#B23A3A') }}">{{ $b['pct'] }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        {{-- Funnel --}}
        <div class="col-lg-4 mb-3">
            <div class="st-card h-100">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-filter"></i> Funnel Konversi</h6>
                    @php
                        $maxFunnel = max($funnelData['new'], $funnelData['leads'], $funnelData['rsv'], 1);
                    @endphp
                    <div class="st-funnel-bar" style="width:{{ round(($funnelData['new']/$maxFunnel)*100) }}%; background:#42a5f5;">
                        <i class="fa fa-user-plus mr-1"></i> New: {{ $funnelData['new'] }}
                    </div>
                    <div class="st-funnel-bar" style="width:{{ round(($funnelData['leads']/$maxFunnel)*100) }}%; background:#B26B00;">
                        <i class="fa fa-comments mr-1"></i> Leads: {{ $funnelData['leads'] }}
                    </div>
                    <div class="st-funnel-bar" style="width:{{ round(($funnelData['rsv']/$maxFunnel)*100) }}%; background:#2E7D32;">
                        <i class="fa fa-calendar-check mr-1"></i> Reservasi: {{ $funnelData['rsv'] }}
                    </div>
                    <hr style="border-color:var(--divider-soft)">
                    <small style="color:var(--text-subtle)">
                        Konversi New→Leads: <strong>{{ $funnelData['new'] > 0 ? round(($funnelData['leads']/$funnelData['new'])*100, 1) : 0 }}%</strong><br>
                        Konversi Leads→Rsv: <strong>{{ $funnelData['leads'] > 0 ? round(($funnelData['rsv']/$funnelData['leads'])*100, 1) : 0 }}%</strong>
                    </small>
                </div>
            </div>
        </div>

        {{-- Top Services --}}
        <div class="col-lg-4 mb-3">
            <div class="st-card h-100">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-stethoscope"></i> Top 10 Layanan</h6>
                    @forelse($topServices as $i => $svc)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <span class="st-rank-num">{{ $i + 1 }}</span>
                                <span style="font-size:0.85rem">{{ $svc->name }}</span>
                            </div>
                            <span class="st-pill st-pill--light">{{ $svc->total }}x</span>
                        </div>
                    @empty
                        <p class="st-muted text-center">Belum ada data</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Top Doctors + Commission --}}
        <div class="col-lg-4 mb-3">
            <div class="st-card mb-3">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-user-md"></i> Top 5 Dokter</h6>
                    @forelse($topDoctors as $i => $doc)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <span class="st-rank-num">{{ $i + 1 }}</span>
                                <span style="font-size:0.85rem">{{ $doc->doctor->name ?? '-' }}</span>
                            </div>
                            <span class="st-pill st-pill--light">{{ $doc->total }} rsv</span>
                        </div>
                    @empty
                        <p class="st-muted text-center">Belum ada data</p>
                    @endforelse
                </div>
            </div>
            <div class="st-card">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-money-bill-wave"></i> Komisi Afiliasi</h6>
                    <div class="st-kv-row">
                        <span class="st-kv-row__label">Pending</span>
                        <span class="st-kv-row__value" style="color:#B26B00">Rp {{ number_format($commissionSummary->pending_total ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="st-kv-row">
                        <span class="st-kv-row__label">Approved</span>
                        <span class="st-kv-row__value" style="color:#1E5A8C">Rp {{ number_format($commissionSummary->approved_total ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="st-kv-row">
                        <span class="st-kv-row__label">Paid</span>
                        <span class="st-kv-row__value" style="color:#2E7D32">Rp {{ number_format($commissionSummary->paid_total ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Revenue Trend Chart --}}
    <div class="row mb-3">
        <div class="col-lg-8 mb-3">
            <div class="st-card">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-chart-area"></i> Tren Revenue 12 Bulan</h6>
                    <canvas id="revenueTrendChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="st-card">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-chart-pie"></i> Distribusi Status</h6>
                    <canvas id="statusChart" height="180"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    new Chart(document.getElementById('revenueTrendChart'), {
        type: 'bar',
        data: {
            labels: @json($trendLabels),
            datasets: [{
                label: 'Revenue',
                data: @json($trendRevenue),
                backgroundColor: 'rgba(70,21,0,0.15)',
                borderColor: '#461500',
                borderWidth: 1,
                borderRadius: 4,
                yAxisID: 'y'
            }, {
                label: 'Jumlah Reservasi',
                data: @json($trendCount),
                type: 'line',
                borderColor: '#1E5A8C',
                backgroundColor: 'rgba(30,90,140,0.08)',
                tension: 0.3,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    type: 'linear', position: 'left',
                    ticks: { callback: v => 'Rp ' + (v/1000000).toFixed(1) + 'jt' },
                    grid: { color: '#F0EBE4' }
                },
                y1: {
                    type: 'linear', position: 'right',
                    grid: { drawOnChartArea: false }
                }
            },
            plugins: {
                legend: { labels: { font: { family: 'Montserrat' } } }
            }
        }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: @json($statusDist->map(fn($s) => ($statusOptions[$s->status] ?? ['label' => $s->status])['label'])->values()),
            datasets: [{
                data: @json($statusDist->pluck('total')->values()),
                backgroundColor: @json($statusDist->map(fn($s) => ($statusOptions[$s->status] ?? ['color' => '#999'])['color'])->values()),
                borderWidth: 2,
                borderColor: '#FFFFFF'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11, family: 'Montserrat' } } }
            }
        }
    });
</script>
@endsection
