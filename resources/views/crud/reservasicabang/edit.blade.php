@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a>CRUD</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('rawreservation') }}">Reservation</a></li>
                            <li class="breadcrumb-item active">Detail</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Detail Reservation</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        {{-- Reservation Info Section --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Informasi Reservasi</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <form method="post" action="{{ route('rawreservation.update', $reservation->id) }}">
                                @csrf
                                @method('PUT')

                                <a href="{{ route('rawreservation') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;"><i class="fa fa-arrow-left"></i> Kembali</a>

                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <tr>
                                        <td width="20%">Customer</td>
                                        <td>
                                            <select
                                                class="select2 form-control {{ $errors->has('raw_customer_id') ? 'is-invalid' : '' }}"
                                                name="raw_customer_id">
                                                <option value="">- Pilih Customer -</option>
                                                @foreach ($customers as $customer)
                                                    <option value="{{ $customer->id }}"
                                                        {{ $reservation->raw_customer_id == $customer->id ? 'selected' : '' }}>
                                                        {{ $customer->name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('raw_customer_id'))
                                                <div class="invalid-feedback">{{ $errors->first('raw_customer_id') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Doctor</td>
                                        <td>
                                            <select
                                                class="select2 form-control {{ $errors->has('raw_doctor_id') ? 'is-invalid' : '' }}"
                                                name="raw_doctor_id">
                                                <option value="">- Pilih Doctor -</option>
                                                @foreach ($doctors as $doctor)
                                                    <option value="{{ $doctor->id }}"
                                                        {{ $reservation->raw_doctor_id == $doctor->id ? 'selected' : '' }}>
                                                        {{ $doctor->name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('raw_doctor_id'))
                                                <div class="invalid-feedback">{{ $errors->first('raw_doctor_id') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Cabang</td>
                                        <td>
                                            <select
                                                class="select2 form-control {{ $errors->has('raw_branch_id') ? 'is-invalid' : '' }}"
                                                name="raw_branch_id">
                                                <option value="">- Pilih Cabang -</option>
                                                @foreach ($branches as $branch)
                                                    <option value="{{ $branch->id }}"
                                                        {{ $reservation->raw_branch_id == $branch->id ? 'selected' : '' }}>
                                                        {{ $branch->name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('raw_branch_id'))
                                                <div class="invalid-feedback">{{ $errors->first('raw_branch_id') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Tanggal</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('date') ? 'is-invalid' : '' }}"
                                                type="date" name="date"
                                                value="{{ old('date', $reservation->date ? $reservation->date->format('Y-m-d') : '') }}" />
                                            @if ($errors->has('date'))
                                                <div class="invalid-feedback">{{ $errors->first('date') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Jam Mulai</td>
                                        <td>
                                            <input
                                                class="form-control {{ $errors->has('start_hour') ? 'is-invalid' : '' }}"
                                                type="time" name="start_hour"
                                                value="{{ old('start_hour', $reservation->start_hour) }}" />
                                            @if ($errors->has('start_hour'))
                                                <div class="invalid-feedback">{{ $errors->first('start_hour') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Jam Selesai</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('end_hour') ? 'is-invalid' : '' }}"
                                                type="time" name="end_hour"
                                                value="{{ old('end_hour', $reservation->end_hour) }}" />
                                            @if ($errors->has('end_hour'))
                                                <div class="invalid-feedback">{{ $errors->first('end_hour') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Status</td>
                                        <td>
                                            <select class="form-control" name="status">
                                                @foreach (\App\Models\RawCusReservation::statusOptions() as $key => $opt)
                                                    <option value="{{ $key }}"
                                                        {{ $reservation->status == $key ? 'selected' : '' }}>
                                                        {{ $opt['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>
                                            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i>
                                                Simpan</button>
                                            <button type="button" class="btn btn-outline-info"
                                                onclick="deleteReservation()"><i class="fa fa-trash"></i>
                                                Hapus</button>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                            <form action="{{ route('rawreservation.destroy', $reservation->id) }}" method="POST"
                                id="deleteReservation">
                                @csrf
                                @method('DELETE')
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reservation Services Section (Child Data) --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Layanan Reservasi</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">

                            <button class="btn btn-sm btn-primary float-left" style="margin-bottom: 20px;"
                                data-toggle="modal" data-target="#addServiceModal">
                                <i class="fa fa-plus"></i> Tambah Layanan
                            </button>

                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th>Doctor</th>
                                        <th>Layanan</th>
                                        <th>Durasi</th>
                                        <th>Harga</th>
                                        <th width="10%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($services as $key => $service)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td>{{ $service->doctor->name ?? '-' }}</td>
                                            <td>{{ $service->docService->name ?? '-' }}</td>
                                            <td>{{ $service->docService->duration_minutes ?? '-' }} menit</td>
                                            <td>Rp {{ number_format($service->docService->price ?? 0, 0, ',', '.') }}</td>
                                            <td nowrap>
                                                <button class="btn btn-danger" style="padding: 2px 6px; margin: 0 3px"
                                                    onclick="deleteService('{{ $service->id }}')" title="Hapus"><i
                                                        class="fa fa-trash"></i></button>

                                                <form
                                                    action="{{ route('rawreservation.service.destroy', [$reservation->id, $service->id]) }}"
                                                    method="POST" id="deleteService{{ $service->id }}">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">Belum ada layanan</td>
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

    {{-- Add Service Modal --}}
    <div class="modal fade" id="addServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('rawreservation.service.store', $reservation->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Layanan</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Doctor</label>
                            <select class="form-control" name="raw_doctor_id" required>
                                <option value="">- Pilih Doctor -</option>
                                @foreach ($doctors as $doctor)
                                    <option value="{{ $doctor->id }}">{{ $doctor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Layanan</label>
                            <select class="form-control" name="raw_doc_service_id" required>
                                <option value="">- Pilih Layanan -</option>
                                @foreach ($docServices as $svc)
                                    <option value="{{ $svc->id }}">
                                        {{ $svc->name }} ({{ $svc->duration_minutes }} menit - Rp
                                        {{ number_format($svc->price, 0, ',', '.') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        function deleteReservation() {
            Swal.fire({
                title: 'Apakah Anda Yakin?',
                text: "Data reservasi yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#deleteReservation').submit();
                }
            })
        }

        function deleteService(id) {
            Swal.fire({
                title: 'Apakah Anda Yakin?',
                text: "Layanan yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#deleteService' + id).submit();
                }
            })
        }
    </script>
@endsection
