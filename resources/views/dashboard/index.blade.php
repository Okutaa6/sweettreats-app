@extends('layouts.app')

@section('css')
    <style>
        .dash-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .08);
            transition: transform .2s;
        }

        .dash-card:hover {
            transform: translateY(-3px);
        }

        .dash-card .card-body {
            padding: 20px 24px;
        }

        .dash-card .dash-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .dash-card .dash-value {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }

        .dash-card .dash-label {
            font-size: 13px;
            color: #888;
            margin: 0;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .quick-btn {
            padding: 3px 8px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            transition: opacity .2s;
        }

        .quick-btn:hover {
            opacity: .8;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        {{-- Page Title --}}
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a>Dashboard</a></li>
                            <li class="breadcrumb-item active">Ringkasan</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Ringkasan Hari Ini</h4>
                </div>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="card dash-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="dash-icon mr-3" style="background:#e3f2fd;color:#1565c0">
                            <i class="fa fa-calendar"></i>
                        </div>
                        <div>
                            <p class="dash-value">{{ $todayCount }}</p>
                            <p class="dash-label">Reservasi Hari Ini</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card dash-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="dash-icon mr-3" style="background:#fef3c7;color:#f59e0b">
                            <i class="fa fa-hourglass-half"></i>
                        </div>
                        <div>
                            <p class="dash-value">{{ $pendingCount }}</p>
                            <p class="dash-label">Menunggu Konfirmasi</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card dash-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="dash-icon mr-3" style="background:#ede9fe;color:#7c3aed">
                            <i class="fa fa-list-ol"></i>
                        </div>
                        <div>
                            <p class="dash-value">{{ $waitlistToday }}</p>
                            <p class="dash-label">Waiting List</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card dash-card">
                    <div class="card-body d-flex align-items-center">
                        <div class="dash-icon mr-3" style="background:#e8f5e9;color:#2e7d32">
                            <i class="fa fa-spinner"></i>
                        </div>
                        <div>
                            <p class="dash-value">{{ $ongoingToday }}</p>
                            <p class="dash-label">Sedang Berlangsung</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts --}}
        <div class="row">
            <div class="col-lg-8">
                <div class="card dash-card">
                    <div class="card-body">
                        <h5 class="mt-0 mb-3">Tren Reservasi 7 Hari Terakhir</h5>
                        <div id="trendChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card dash-card">
                    <div class="card-body">
                        <h5 class="mt-0 mb-3">Status Hari Ini</h5>
                        <div id="donutChart"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabel Reservasi Hari Ini --}}
        <div class="row">
            <div class="col-12">
                <div class="card dash-card">
                    <div class="card-body">
                        <h5 class="mt-0 mb-3">Reservasi Hari Ini</h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Jam</th>
                                        <th>Customer</th>
                                        <th>Dokter</th>
                                        <th>Cabang</th>
                                        <th>Layanan</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($todayReservations as $res)
                                        @php $si = $res->status_info; @endphp
                                        <tr id="row-{{ $res->id }}">
                                            <td><strong>{{ $res->start_hour }} - {{ $res->end_hour }}</strong></td>
                                            <td>{{ $res->customer->name ?? '-' }}</td>
                                            <td>{{ $res->doctor->name ?? '-' }}</td>
                                            <td>{{ $res->branch->name ?? '-' }}</td>
                                            <td>
                                                @foreach ($res->services as $svc)
                                                    <span
                                                        class="badge badge-light">{{ $svc->docService->name ?? '-' }}</span>
                                                @endforeach
                                            </td>
                                            <td>
                                                <span class="status-badge" id="badge-{{ $res->id }}"
                                                    style="background:{{ $si['bg'] }};color:{{ $si['color'] }}">
                                                    <i class="fa {{ $si['icon'] }}"></i>
                                                    {{ $si['label'] }}
                                                </span>
                                            </td>
                                            <td nowrap>
                                                @if ($res->status === 'pending')
                                                    <button class="quick-btn text-white" style="background:#1565c0"
                                                        onclick="quickStatus('{{ $res->id }}','confirmed')">
                                                        <i class="fa fa-check"></i> Konfirmasi
                                                    </button>
                                                @elseif ($res->status === 'confirmed')
                                                    <button class="quick-btn text-white" style="background:#2e7d32"
                                                        onclick="quickStatus('{{ $res->id }}','ongoing')">
                                                        <i class="fa fa-play"></i> Mulai
                                                    </button>
                                                @elseif ($res->status === 'ongoing')
                                                    <button class="quick-btn text-white" style="background:#8b7a6b"
                                                        onclick="quickStatus('{{ $res->id }}','completed')">
                                                        <i class="fa fa-check"></i> Selesai
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fa fa-calendar-times fa-2x mb-2 d-block"></i>
                                                Belum ada reservasi hari ini
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
        // Trend Line Chart
        var trendOptions = {
            chart: {
                type: 'area',
                height: 280,
                toolbar: {
                    show: false
                }
            },
            series: [{
                name: 'Reservasi',
                data: @json($trendValues)
            }],
            xaxis: {
                categories: @json($trendLabels)
            },
            colors: ['#1565c0'],
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.05
                }
            },
            dataLabels: {
                enabled: false
            },
            grid: {
                borderColor: '#f1f1f1'
            }
        };
        new ApexCharts(document.querySelector("#trendChart"), trendOptions).render();

        // Donut Chart
        @if (count($donutValues) > 0)
            var donutOptions = {
                chart: {
                    type: 'donut',
                    height: 280
                },
                series: @json($donutValues),
                labels: @json($donutLabels),
                colors: @json($donutColors),
                legend: {
                    position: 'bottom'
                },
                dataLabels: {
                    enabled: true
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '60%'
                        }
                    }
                }
            };
            new ApexCharts(document.querySelector("#donutChart"), donutOptions).render();
        @else
            document.querySelector("#donutChart").innerHTML =
                '<div class="text-center text-muted py-5"><i class="fa fa-chart-pie fa-2x mb-2 d-block"></i>Belum ada data</div>';
        @endif

        // Quick Status Update
        function quickStatus(id, status) {
            $.ajax({
                url: '/dashboard/reservasi/' + id + '/status',
                method: 'PATCH',
                data: {
                    status: status,
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    var badge = $('#badge-' + id);
                    badge.css({
                        background: res.bg,
                        color: res.color
                    });
                    badge.html('<i class="fa ' + res.icon + '"></i> ' + res.label);

                    // Remove action buttons after status change
                    $('#row-' + id + ' td:last').html(
                        '<span class="text-muted"><i class="fa fa-check-circle"></i></span>');
                },
                error: function(xhr) {
                    alert('Gagal mengubah status');
                }
            });
        }
    </script>
@endsection
