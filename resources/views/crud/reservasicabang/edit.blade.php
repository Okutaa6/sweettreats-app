@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Detail Reservation</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        {{-- Reservation Info --}}
        <div class="st-card mb-3">
            <div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-calendar-alt"></i> Informasi Reservasi</h6>
                <form method="post" action="{{ route('rawreservation.update', $reservation->id) }}">
                    @csrf
                    @method('PUT')

                    <a href="{{ route('rawreservation') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                        <i class="fa fa-arrow-left"></i> Kembali
                    </a>

                    <table class="st-table">
                        <tr>
                            <td width="20%" style="font-weight:600;">Customer</td>
                            <td>
                                <select class="select2 form-control {{ $errors->has('raw_customer_id') ? 'is-invalid' : '' }}"
                                    name="raw_customer_id">
                                    <option value="">- Pilih Customer -</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}"
                                            {{ $reservation->raw_customer_id == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($errors->has('raw_customer_id'))
                                    <div class="invalid-feedback">{{ $errors->first('raw_customer_id') }}</div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Doctor</td>
                            <td>
                                <select class="select2 form-control {{ $errors->has('raw_doctor_id') ? 'is-invalid' : '' }}"
                                    name="raw_doctor_id">
                                    <option value="">- Pilih Doctor -</option>
                                    @foreach ($doctors as $doctor)
                                        <option value="{{ $doctor->id }}"
                                            {{ $reservation->raw_doctor_id == $doctor->id ? 'selected' : '' }}>
                                            {{ $doctor->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($errors->has('raw_doctor_id'))
                                    <div class="invalid-feedback">{{ $errors->first('raw_doctor_id') }}</div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Cabang</td>
                            <td>
                                <select class="select2 form-control {{ $errors->has('raw_branch_id') ? 'is-invalid' : '' }}"
                                    name="raw_branch_id">
                                    <option value="">- Pilih Cabang -</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}"
                                            {{ $reservation->raw_branch_id == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($errors->has('raw_branch_id'))
                                    <div class="invalid-feedback">{{ $errors->first('raw_branch_id') }}</div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Tanggal</td>
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
                            <td style="font-weight:600;">Jam Mulai</td>
                            <td>
                                <input class="form-control {{ $errors->has('start_hour') ? 'is-invalid' : '' }}"
                                    type="time" name="start_hour"
                                    value="{{ old('start_hour', $reservation->start_hour) }}" />
                                @if ($errors->has('start_hour'))
                                    <div class="invalid-feedback">{{ $errors->first('start_hour') }}</div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Jam Selesai</td>
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
                            <td style="font-weight:600;">Status</td>
                            <td>
                                <select class="form-control" name="status">
                                    @foreach (\App\Models\RawCusReservation::statusOptions() as $key => $opt)
                                        <option value="{{ $key }}" {{ $reservation->status == $key ? 'selected' : '' }}>
                                            {{ $opt['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>
                                <button class="st-btn-brand" type="submit">
                                    <i class="fa fa-save"></i> Simpan
                                </button>
                                <button type="button" class="st-btn-outline-brown" onclick="deleteReservation()">
                                    <i class="fa fa-trash"></i> Hapus
                                </button>
                            </td>
                        </tr>
                    </table>
                </form>
                <form action="{{ route('rawreservation.destroy', $reservation->id) }}" method="POST" id="deleteReservation">
                    @csrf @method('DELETE')
                </form>
            </div>
        </div>

        {{-- Reservation Services --}}
        <div class="st-card">
            <div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-stethoscope"></i> Layanan Reservasi</h6>

                <button class="st-btn-brand mb-3" data-toggle="modal" data-target="#addServiceModal">
                    <i class="fa fa-plus"></i> Tambah Layanan
                </button>

                <div class="st-table-wrap--scroll">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Doctor</th>
                                <th>Layanan</th>
                                <th>Durasi</th>
                                <th>Harga</th>
                                <th width="8%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($services as $key => $service)
                                <tr>
                                    <td class="st-num">{{ $key + 1 }}</td>
                                    <td>{{ $service->doctor->name ?? '-' }}</td>
                                    <td>{{ $service->docService->name ?? '-' }}</td>
                                    <td>{{ $service->docService->duration_minutes ?? '-' }} menit</td>
                                    <td class="st-tnum">Rp {{ number_format($service->docService->price ?? 0, 0, ',', '.') }}</td>
                                    <td>
                                        <button class="st-icon-btn" onclick="deleteService('{{ $service->id }}')" title="Hapus">
                                            <i class="fa fa-trash" style="color:#B23A3A"></i>
                                        </button>
                                        <form action="{{ route('rawreservation.service.destroy', [$reservation->id, $service->id]) }}"
                                            method="POST" id="deleteService{{ $service->id }}">
                                            @csrf @method('DELETE')
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center st-muted">Belum ada layanan</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label style="font-weight:600;">Doctor</label>
                            <select class="form-control" name="raw_doctor_id" required>
                                <option value="">- Pilih Doctor -</option>
                                @foreach ($doctors as $doctor)
                                    <option value="{{ $doctor->id }}">{{ $doctor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600;">Layanan</label>
                            <select class="form-control" name="raw_doc_service_id" required>
                                <option value="">- Pilih Layanan -</option>
                                @foreach ($docServices as $svc)
                                    <option value="{{ $svc->id }}">
                                        {{ $svc->name }} ({{ $svc->duration_minutes }} menit - Rp {{ number_format($svc->price, 0, ',', '.') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                        <button type="submit" class="st-btn-brand"><i class="fa fa-save"></i> Simpan</button>
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
            confirmButtonColor: '#461500',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (result.isConfirmed) { $('#deleteReservation').submit(); }
        })
    }

    function deleteService(id) {
        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Layanan yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#461500',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (result.isConfirmed) { $('#deleteService' + id).submit(); }
        })
    }
</script>
@endsection
