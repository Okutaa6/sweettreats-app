@extends('layouts.app')

@section('css')
    <style>
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
            padding: 4px 10px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: opacity .2s;
        }

        .quick-btn:hover {
            opacity: .8;
        }

        .page-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .08);
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">Waiting List</li>
                        </ol>
                    </div>
                    <h4 class="page-title">
                        <i class="fa fa-list-ol text-purple mr-2"></i>
                        Waiting List Hari Ini
                    </h4>
                </div>
            </div>
        </div>

        <div class="card page-card">
            <div class="card-body">
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
                            @forelse ($reservations as $res)
                                @php $si = $res->status_info; @endphp
                                <tr id="row-{{ $res->id }}">
                                    <td><strong>{{ $res->start_hour }} - {{ $res->end_hour }}</strong></td>
                                    <td>{{ $res->customer->name ?? '-' }}</td>
                                    <td>{{ $res->doctor->name ?? '-' }}</td>
                                    <td>{{ $res->branch->name ?? '-' }}</td>
                                    <td>
                                        @foreach ($res->services as $svc)
                                            <span class="badge badge-light">{{ $svc->docService->name ?? '-' }}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        <span class="status-badge" id="badge-{{ $res->id }}"
                                            style="background:{{ $si['bg'] }};color:{{ $si['color'] }}">
                                            <i class="fa {{ $si['icon'] }}"></i> {{ $si['label'] }}
                                        </span>
                                    </td>
                                    <td nowrap>
                                        <button class="quick-btn text-white" style="background:#1565c0"
                                            onclick="quickStatus('{{ $res->id }}','confirmed')">
                                            <i class="fa fa-check"></i> Konfirmasi
                                        </button>
                                        <button class="quick-btn text-white" style="background:#dc2626"
                                            onclick="quickStatus('{{ $res->id }}','cancelled')">
                                            <i class="fa fa-times"></i> Batal
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fa fa-check-circle fa-2x mb-2 d-block" style="color:#7c3aed"></i>
                                        Tidak ada waiting list hari ini
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
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
                    $('#row-' + id + ' td:last').html(
                        '<span class="text-muted"><i class="fa fa-check-circle"></i> Updated</span>');
                },
                error: function() {
                    alert('Gagal mengubah status');
                }
            });
        }
    </script>
@endsection
