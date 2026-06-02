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

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- OMNICARE LIVE DATA SECTION                                   --}}
    {{-- Pull dari mirror tables omnicare_*, sync overnight 02:00 WIB --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="row mb-2">
        <div class="col-12">
            <div class="st-card" style="background:linear-gradient(135deg, #FFF8F0 0%, #FAEFE0 100%); border-left:4px solid #461500;">
                <div class="st-card__body" style="padding:1rem 1.25rem;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h5 style="margin:0; color:#461500;">
                                <i class="fa fa-database mr-2"></i> Omnicare Operational Data
                            </h5>
                            <small style="color:var(--text-subtle)">
                                Periode: {{ \Carbon\Carbon::parse($omnicare['rangeStart'])->format('d M Y') }}
                                — {{ \Carbon\Carbon::parse($omnicare['rangeEnd'])->format('d M Y') }}
                            </small>
                        </div>
                        <div class="text-right">
                            @if($omnicare['lastSync'])
                                <small style="color:var(--text-subtle)">
                                    <i class="fa fa-sync-alt"></i> Last sync:
                                    <strong>{{ \Carbon\Carbon::parse($omnicare['lastSync']->finished_at ?? $omnicare['lastSync']->started_at)->diffForHumans() }}</strong>
                                </small><br>
                                <small style="color:{{ ($omnicare['syncStats']->error_count ?? 0) > 0 ? '#B23A3A' : '#2E7D32' }}">
                                    24h: {{ $omnicare['syncStats']->success_count ?? 0 }} ✓
                                    @if(($omnicare['syncStats']->error_count ?? 0) > 0)
                                        / {{ $omnicare['syncStats']->error_count }} ✗
                                    @endif
                                </small>
                            @else
                                <small style="color:#B23A3A"><i class="fa fa-exclamation-triangle"></i> Belum ada sync record</small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Omnicare Summary Cards --}}
    <div class="row mb-3">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-coins mr-1"></i> Omnicare Revenue</p>
                <p class="st-dash-card__value" style="color:#2E7D32">Rp {{ number_format($omnicare['summary']->gross_revenue ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-chart-line mr-1"></i> Net Profit</p>
                <p class="st-dash-card__value" style="color:#1E5A8C">Rp {{ number_format($omnicare['summary']->net_profit ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-receipt mr-1"></i> Total Kunjungan</p>
                <p class="st-dash-card__value">{{ number_format($omnicare['summary']->total_visits ?? 0) }}</p>
                <small style="color:var(--text-subtle)">{{ number_format($omnicare['summary']->total_lines ?? 0) }} line items</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-users mr-1"></i> Pasien Unik</p>
                <p class="st-dash-card__value">{{ number_format($omnicare['summary']->unique_patients ?? 0) }}</p>
            </div>
        </div>
    </div>

    {{-- Omnicare Per-Branch with stale-data alert --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="st-card">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-hospital"></i> Omnicare Revenue per Cabang</h6>
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--compact">
                            <thead>
                                <tr>
                                    <th>Cabang</th>
                                    <th>Status Data</th>
                                    <th class="text-right">Kunjungan</th>
                                    <th class="text-right">Line Items</th>
                                    <th class="text-right">Revenue</th>
                                    <th class="text-right">Profit</th>
                                    <th>Trx Terakhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($omnicare['perBranch'] as $br)
                                    @php
                                        $statusColor = [
                                            'active'  => '#2E7D32',
                                            'warning' => '#B26B00',
                                            'stale'   => '#B23A3A',
                                            'no_data' => '#999',
                                        ][$br->status];
                                        $statusLabel = [
                                            'active'  => '✓ Aktif',
                                            'warning' => '⚠ ' . round($br->days_since_last) . ' hari lalu',
                                            'stale'   => '⚠ STALE ' . round($br->days_since_last) . ' hari',
                                            'no_data' => '— No data',
                                        ][$br->status];
                                    @endphp
                                <tr>
                                    <td>
                                        {{ $br->branch_name }}
                                        @if($br->is_parent)
                                            <span class="st-pill st-pill--light" style="margin-left:6px;">parent</span>
                                        @endif
                                    </td>
                                    <td><span style="color:{{ $statusColor }}; font-weight:600">{{ $statusLabel }}</span></td>
                                    <td class="text-right st-tnum">{{ $br->visits }}</td>
                                    <td class="text-right st-tnum">{{ $br->transactions }}</td>
                                    <td class="text-right st-tnum">Rp {{ number_format($br->revenue, 0, ',', '.') }}</td>
                                    <td class="text-right st-tnum">Rp {{ number_format($br->profit, 0, ',', '.') }}</td>
                                    <td>{{ $br->last_trx_date ? \Carbon\Carbon::parse($br->last_trx_date)->format('d M Y') : '-' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center st-muted">Belum ada data Omnicare untuk periode ini</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Omnicare Top Procedures + Top Doctors --}}
    <div class="row mb-3">
        <div class="col-lg-6 mb-3">
            <div class="st-card h-100">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-tooth"></i> Top 10 Perawatan (Omnicare)</h6>
                    @forelse($omnicare['topProcedures'] as $i => $p)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center" style="flex:1; min-width:0;">
                                <span class="st-rank-num">{{ $i + 1 }}</span>
                                <div style="min-width:0;">
                                    <span style="font-size:0.85rem; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $p->item_name }}</span>
                                    <small style="color:var(--text-subtle); font-size:0.7rem;">{{ $p->item_code }}</small>
                                </div>
                            </div>
                            <div class="text-right" style="flex-shrink:0;">
                                <span class="st-pill st-pill--light">{{ (int) $p->qty }}x</span>
                                <small style="display:block; color:var(--text-subtle); font-size:0.7rem;">Rp {{ number_format($p->revenue, 0, ',', '.') }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="st-muted text-center">Belum ada data</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="st-card h-100">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-user-md"></i> Top 10 Dokter by Revenue (Omnicare)</h6>
                    @forelse($omnicare['topDoctors'] as $i => $d)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center" style="flex:1; min-width:0;">
                                <span class="st-rank-num">{{ $i + 1 }}</span>
                                <div style="min-width:0;">
                                    <span style="font-size:0.85rem; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        {{ $d->display_name }}
                                        @if(!$d->sweettreats_doctor_id)
                                            <i class="fa fa-unlink" style="color:#B26B00; font-size:0.7rem;" title="Belum mapped ke SweetTreats"></i>
                                        @endif
                                    </span>
                                    <small style="color:var(--text-subtle); font-size:0.7rem;">{{ $d->visits }} kunjungan</small>
                                </div>
                            </div>
                            <div class="text-right" style="flex-shrink:0;">
                                <small style="display:block; color:#2E7D32; font-weight:600;">Rp {{ number_format($d->revenue, 0, ',', '.') }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="st-muted text-center">Belum ada data</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Omnicare Daily Trend + Payment Status --}}
    <div class="row mb-3">
        <div class="col-lg-8 mb-3">
            <div class="st-card">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-chart-line"></i> Tren Revenue Harian (30 Hari Terakhir)</h6>
                    <canvas id="omnicareDailyChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="st-card">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-credit-card"></i> Status Pembayaran</h6>
                    <canvas id="omnicarePaymentChart" height="180"></canvas>
                    <hr style="border-color:var(--divider-soft); margin:0.75rem 0;">
                    @foreach($omnicare['paymentStatus'] as $ps)
                        <div class="st-kv-row">
                            <span class="st-kv-row__label">{{ ucwords(str_replace('_', ' ', $ps->payment_status)) }}</span>
                            <span class="st-kv-row__value">{{ $ps->line_count }} ({{ number_format($ps->revenue / 1000000, 1) }}jt)</span>
                        </div>
                    @endforeach
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

    // ─── Omnicare Daily Trend (30 days) ───
    new Chart(document.getElementById('omnicareDailyChart'), {
        type: 'line',
        data: {
            labels: @json($omnicare['trendLabels']),
            datasets: [{
                label: 'Revenue Harian',
                data: @json($omnicare['trendRevenue']),
                borderColor: '#461500',
                backgroundColor: 'rgba(70,21,0,0.12)',
                tension: 0.25,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Kunjungan',
                data: @json($omnicare['trendVisits']),
                type: 'bar',
                backgroundColor: 'rgba(30,90,140,0.4)',
                borderColor: '#1E5A8C',
                borderWidth: 1,
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
                    grid: { drawOnChartArea: false },
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: { labels: { font: { family: 'Montserrat' } } }
            }
        }
    });

    // ─── Omnicare Payment Status ───
    (function() {
        const paymentData = @json($omnicare['paymentStatus']->pluck('line_count', 'payment_status'));
        const colorMap = {
            'cash_paid':      '#2E7D32',
            'insurance_paid': '#1E5A8C',
            'unpaid':         '#B23A3A',
        };
        const labels = Object.keys(paymentData);
        if (labels.length > 0) {
            new Chart(document.getElementById('omnicarePaymentChart'), {
                type: 'doughnut',
                data: {
                    labels: labels.map(k => k.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase())),
                    datasets: [{
                        data: Object.values(paymentData),
                        backgroundColor: labels.map(k => colorMap[k] || '#999'),
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
        }
    })();
</script>
@endsection
