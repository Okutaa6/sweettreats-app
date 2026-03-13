@extends('layouts.app')

@section('css')
    <style>
        .filter-bar {
            background: #fff;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
        }

        .status-select {
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            outline: none;
            transition: border-color .2s;
            min-width: 160px;
        }

        .status-select:focus {
            border-color: #4361ee;
        }

        .status-select:disabled {
            opacity: .6;
            cursor: not-allowed;
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
                            <li class="breadcrumb-item active">Semua Reservasi</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Semua Reservasi</h4>
                </div>
            </div>
        </div>

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('dashboard.reservasi') }}">
            <div class="filter-bar d-flex flex-wrap align-items-end" style="gap:12px;">
                <div>
                    <label class="small mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                        value="{{ request('date_from') }}">
                </div>
                <div>
                    <label class="small mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                        value="{{ request('date_to') }}">
                </div>
                <div>
                    <label class="small mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">Semua</option>
                        @foreach (\App\Models\RawCusReservation::statusOptions() as $key => $opt)
                            <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                {{ $opt['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="small mb-1">Dokter</label>
                    <select name="doctor" class="form-control form-control-sm">
                        <option value="">Semua</option>
                        @foreach ($doctors as $doc)
                            <option value="{{ $doc->id }}" {{ request('doctor') == $doc->id ? 'selected' : '' }}>
                                {{ $doc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="small mb-1">Cabang</label>
                    <select name="branch" class="form-control form-control-sm">
                        <option value="">Semua</option>
                        @foreach ($branches as $br)
                            <option value="{{ $br->id }}" {{ request('branch') == $br->id ? 'selected' : '' }}>
                                {{ $br->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Filter</button>
                    <a href="{{ route('dashboard.reservasi') }}" class="btn btn-light btn-sm">Reset</a>
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="card" style="border:none;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.08)">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Customer</th>
                                <th>Dokter</th>
                                <th>Cabang</th>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reservations as $i => $row)
                                @php $si = $row->status_info; @endphp
                                <tr id="row-{{ $row->id }}">
                                    <td>{{ $reservations->firstItem() + $i }}</td>
                                    <td>{{ $row->customer->name ?? '-' }}</td>
                                    <td>{{ $row->doctor->name ?? '-' }}</td>
                                    <td>{{ $row->branch->name ?? '-' }}</td>
                                    <td>{{ $row->date ? $row->date->format('d/m/Y') : '-' }}</td>
                                    <td nowrap>{{ $row->start_hour }} - {{ $row->end_hour }}</td>
                                    <td>
                                        <select class="status-select" id="status-{{ $row->id }}"
                                            data-id="{{ $row->id }}" data-original="{{ $row->status }}"
                                            onchange="changeStatus(this)"
                                            style="background:{{ $si['bg'] }};color:{{ $si['color'] }};border-color:{{ $si['color'] }}40">
                                            @foreach (\App\Models\RawCusReservation::statusOptions() as $key => $opt)
                                                <option value="{{ $key }}"
                                                    {{ $row->status === $key ? 'selected' : '' }}>
                                                    {{ $opt['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fa fa-inbox fa-2x mb-2 d-block"></i>
                                        Tidak ada data reservasi
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $reservations->links() }}</div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        var statusColors = @json(\App\Models\RawCusReservation::statusOptions());

        function changeStatus(el) {
            var id = $(el).data('id');
            var newStatus = $(el).val();
            var originalStatus = $(el).data('original');

            if (newStatus === originalStatus) return;

            Swal.fire({
                title: 'Ubah Status?',
                text: 'Status akan diubah ke "' + statusColors[newStatus].label + '"',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4361ee',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Ya, ubah!',
                cancelButtonText: 'Batal'
            }).then(function(result) {
                if (result.value) {
                    $(el).prop('disabled', true);

                    $.ajax({
                        url: '/dashboard/reservasi/' + id + '/status',
                        method: 'PATCH',
                        data: {
                            _token: '{{ csrf_token() }}',
                            status: newStatus
                        },
                        success: function(res) {
                            var info = statusColors[res.status];
                            $(el).css({
                                background: info.bg,
                                color: info.color,
                                borderColor: info.color + '40'
                            });
                            $(el).data('original', res.status);
                            $(el).prop('disabled', false);

                            Swal.fire({
                                title: 'Berhasil!',
                                text: 'Status berhasil diubah ke "' + info.label + '"',
                                type: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        },
                        error: function(xhr) {
                            $(el).val(originalStatus);
                            $(el).prop('disabled', false);

                            Swal.fire({
                                title: 'Gagal!',
                                text: xhr.responseJSON?.error ||
                                    'Terjadi kesalahan saat mengubah status',
                                type: 'error'
                            });
                        }
                    });
                } else {
                    $(el).val(originalStatus);
                }
            });
        }
    </script>
@endsection
