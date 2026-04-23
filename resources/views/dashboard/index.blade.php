@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Ringkasan Hari Ini</h4>
                </div>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="st-dash-card d-flex align-items-center">
                    <div class="st-dash-card__icon st-dash-card__icon--info mr-3"><i class="fa fa-calendar"></i></div>
                    <div>
                        <div class="st-dash-card__value">{{ $todayCount }}</div>
                        <div class="st-dash-card__label">Reservasi Hari Ini</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="st-dash-card d-flex align-items-center">
                    <div class="st-dash-card__icon st-dash-card__icon--warning mr-3"><i class="fa fa-hourglass-half"></i></div>
                    <div>
                        <div class="st-dash-card__value">{{ $pendingCount }}</div>
                        <div class="st-dash-card__label">Menunggu Konfirmasi</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="st-dash-card d-flex align-items-center">
                    <div class="st-dash-card__icon mr-3" style="background:#EDE9FE;color:#7C3AED;"><i class="fa fa-list-ol"></i></div>
                    <div>
                        <div class="st-dash-card__value">{{ $waitlistToday }}</div>
                        <div class="st-dash-card__label">Waiting List</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="st-dash-card d-flex align-items-center">
                    <div class="st-dash-card__icon st-dash-card__icon--success mr-3"><i class="fa fa-spinner"></i></div>
                    <div>
                        <div class="st-dash-card__value">{{ $confirmedToday }}</div>
                        <div class="st-dash-card__label">Konfirmasi Hari Ini</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts --}}
        <div class="row">
            <div class="col-lg-8 mb-3">
                <div class="st-card">
                    <div class="st-card__body">
                        <h5 class="mt-0 mb-3">Tren Reservasi 7 Hari Terakhir</h5>
                        <div id="trendChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="st-card">
                    <div class="st-card__body">
                        <h5 class="mt-0 mb-3">Status Hari Ini</h5>
                        <div id="donutChart"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabel Reservasi Hari Ini --}}
        <div class="row">
            <div class="col-12">
                <div class="st-card">
                    <div class="st-card__body">
                        <h5 class="mt-0 mb-3">Reservasi Hari Ini</h5>
                        <div class="st-table-wrap--scroll">
                            <table class="st-table">
                                <thead>
                                    <tr>
                                        <th>Jam</th>
                                        <th>Pasien</th>
                                        <th>Dokter</th>
                                        <th>Cabang</th>
                                        <th>Layanan</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($todayReservations as $res)
                                        @php
                                            $si = $res->status_info;
                                            $pillMap = ['pending'=>'st-pill--warning','confirmed'=>'st-pill--info','completed'=>'st-pill--success','cancelled'=>'st-pill--danger','waitlist'=>'st-pill--neutral'];
                                            $pillCls = $pillMap[$res->status] ?? 'st-pill--neutral';
                                        @endphp
                                        <tr id="row-{{ $res->id }}">
                                            <td class="st-tnum"><strong>{{ $res->start_hour }} - {{ $res->end_hour }}</strong></td>
                                            <td>{{ $res->customer->name ?? '-' }}</td>
                                            <td>{{ $res->doctor->name ?? '-' }}</td>
                                            <td>{{ $res->branch->name ?? '-' }}</td>
                                            <td>
                                                @foreach ($res->services as $svc)
                                                    <span class="st-pill st-pill--plain st-pill--light" style="font-size:0.72rem;margin:1px;">{{ $svc->docService->name ?? '-' }}</span>
                                                @endforeach
                                            </td>
                                            <td>
                                                <span class="st-pill {{ $pillCls }}" id="badge-{{ $res->id }}">
                                                    <i class="fa {{ $si['icon'] }}"></i> {{ $si['label'] }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($res->status === 'pending')
                                                    <button class="st-quick-btn st-quick-btn--info"
                                                        onclick="quickStatus('{{ $res->id }}','confirmed')">
                                                        <i class="fa fa-check"></i> Konfirmasi
                                                    </button>
                                                @elseif ($res->status === 'confirmed')
                                                    <button class="st-quick-btn st-quick-btn--success"
                                                        onclick="quickStatus('{{ $res->id }}','completed')">
                                                        <i class="fa fa-check"></i> Selesai
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <div class="st-muted">
                                                    <i class="fa fa-calendar-times fa-2x mb-2 d-block"></i>
                                                    Belum ada reservasi hari ini
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('assets/plugins/apexcharts/apexcharts.min.js') }}"></script>
    <script>
        var PILL_MAP = {pending:'st-pill--warning',confirmed:'st-pill--info',completed:'st-pill--success',cancelled:'st-pill--danger',waitlist:'st-pill--neutral'};

        var trendOptions = {
            chart: { type: 'area', height: 280, toolbar: { show: false } },
            series: [{ name: 'Reservasi', data: @json($trendValues) }],
            xaxis: { categories: @json($trendLabels) },
            colors: ['#461500'],
            stroke: { curve: 'smooth', width: 3 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#F0EBE4' }
        };
        new ApexCharts(document.querySelector("#trendChart"), trendOptions).render();

        @if (count($donutValues) > 0)
            var donutOptions = {
                chart: { type: 'donut', height: 280 },
                series: @json($donutValues),
                labels: @json($donutLabels),
                colors: @json($donutColors),
                legend: { position: 'bottom' },
                dataLabels: { enabled: true },
                plotOptions: { pie: { donut: { size: '60%' } } }
            };
            new ApexCharts(document.querySelector("#donutChart"), donutOptions).render();
        @else
            document.querySelector("#donutChart").innerHTML =
                '<div class="text-center st-muted py-5"><i class="fa fa-chart-pie fa-2x mb-2 d-block"></i>Belum ada data</div>';
        @endif

        function quickStatus(id, status) {
            $.ajax({
                url: '/dashboard/reservasi/' + id + '/status',
                method: 'PATCH',
                data: { status: status, _token: '{{ csrf_token() }}' },
                success: function(res) {
                    var badge = $('#badge-' + id);
                    badge.attr('class', 'st-pill ' + (PILL_MAP[status] || 'st-pill--neutral'));
                    badge.html('<i class="fa ' + res.icon + '"></i> ' + res.label);
                    $('#row-' + id + ' td:last').html('<span class="st-muted"><i class="fa fa-check-circle"></i></span>');
                },
                error: function() { alert('Gagal mengubah status'); }
            });
        }
    </script>
@endsection
