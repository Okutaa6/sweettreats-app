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
                            <li class="breadcrumb-item"><a href="{{ route('rawdoctor') }}">Doctor</a></li>
                            <li class="breadcrumb-item active">Detail</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Detail Doctor</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        {{-- Doctor Info Section --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Informasi Doctor</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <form method="post" action="{{ route('rawdoctor.update', $doctor->id) }}">
                                @csrf
                                @method('PUT')

                                <a href="{{ route('rawdoctor') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;"><i class="fa fa-arrow-left"></i> Kembali </a>

                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <tr>
                                        <td width="20%">Nama</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                                type="text" name="name" value="{{ old('name', $doctor->name) }}" />
                                            @if ($errors->has('name'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('name') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>
                                            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i>
                                                Simpan</button>
                                            <button type="button" class="btn btn-outline-info"
                                                onclick="deleteDoctor('{{ $doctor->id }}')"><i class="fa fa-trash"></i>
                                                Hapus </button>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                            <form action="{{ route('rawdoctor.destroy', $doctor->id) }}" method="POST" id="deleteDoctor">
                                @csrf
                                @method('DELETE')
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Doctor Schedules Section (Child Data) --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Jadwal Doctor</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">

                            <button class="btn btn-sm btn-primary float-left" style="margin-bottom: 20px;"
                                data-toggle="modal" data-target="#addScheduleModal">
                                <i class="fa fa-plus"></i> Tambah Jadwal
                            </button>

                            <table class="table table-bordered table-hover table-jadwal-doctor">
                                <colgroup>
                                    <col class="c-no">
                                    <col class="c-hari">
                                    <col class="c-cabang">
                                    <col class="c-mulai">
                                    <col class="c-selesai">
                                    <col class="c-action">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Hari</th>
                                        <th>Cabang</th>
                                        <th>Jam Mulai</th>
                                        <th>Jam Selesai</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $dayNames = [
                                            1 => 'Senin',
                                            2 => 'Selasa',
                                            3 => 'Rabu',
                                            4 => 'Kamis',
                                            5 => 'Jumat',
                                            6 => 'Sabtu',
                                            7 => 'Minggu',
                                        ];
                                    @endphp
                                    @forelse ($schedules as $key => $schedule)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td>{{ $dayNames[$schedule->day] ?? $schedule->day }}</td>
                                            <td>{{ $schedule->branch->name ?? '-' }}</td>
                                            <td>{{ $schedule->start_hour }}</td>
                                            <td>{{ $schedule->end_hour }}</td>
                                            <td nowrap>
                                                <button class="btn btn-success" style="padding: 2px 6px; margin: 0 3px"
                                                    data-toggle="modal" data-target="#editScheduleModal{{ $schedule->id }}"
                                                    title="Edit"><i class="fa fa-edit"></i></button>
                                                <button class="btn btn-danger" style="padding: 2px 6px; margin: 0 3px"
                                                    onclick="deleteSchedule('{{ $schedule->id }}')" title="Hapus"><i
                                                        class="fa fa-trash"></i></button>

                                                {{-- Delete form for this schedule --}}
                                                <form
                                                    action="{{ route('rawdoctor.schedule.destroy', [$doctor->id, $schedule->id]) }}"
                                                    method="POST" id="deleteSchedule{{ $schedule->id }}">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </td>
                                        </tr>

                                        {{-- Edit Schedule Modal --}}
                                        <div class="modal fade" id="editScheduleModal{{ $schedule->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST"
                                                        action="{{ route('rawdoctor.schedule.update', [$doctor->id, $schedule->id]) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edit Jadwal</h5>
                                                            <button type="button" class="close" data-dismiss="modal">
                                                                <span>&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="form-group">
                                                                <label>Hari</label>
                                                                <select class="form-control" name="day" required>
                                                                    @foreach ($dayNames as $num => $name)
                                                                        <option value="{{ $num }}"
                                                                            {{ $schedule->day == $num ? 'selected' : '' }}>
                                                                            {{ $name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Cabang</label>
                                                                <select class="form-control" name="raw_branch_id" required>
                                                                    <option value="">- Pilih Cabang -</option>
                                                                    @foreach ($branches as $branch)
                                                                        <option value="{{ $branch->id }}"
                                                                            {{ $schedule->raw_branch_id == $branch->id ? 'selected' : '' }}>
                                                                            {{ $branch->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Jam Mulai</label>
                                                                <input type="time" class="form-control" name="start_hour"
                                                                    value="{{ $schedule->start_hour }}" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Jam Selesai</label>
                                                                <input type="time" class="form-control"
                                                                    name="end_hour" value="{{ $schedule->end_hour }}"
                                                                    required>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-primary"><i
                                                                    class="fa fa-save"></i> Simpan</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">Belum ada jadwal</td>
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

    {{-- Add Schedule Modal --}}
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('rawdoctor.schedule.store', $doctor->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Jadwal</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Hari</label>
                            <select class="form-control" name="day" required>
                                <option value="">- Pilih Hari -</option>
                                <option value="1">Senin</option>
                                <option value="2">Selasa</option>
                                <option value="3">Rabu</option>
                                <option value="4">Kamis</option>
                                <option value="5">Jumat</option>
                                <option value="6">Sabtu</option>
                                <option value="7">Minggu</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Cabang</label>
                            <select class="form-control" name="raw_branch_id" required>
                                <option value="">- Pilih Cabang -</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Jam Mulai</label>
                            <input type="time" class="form-control" name="start_hour" required>
                        </div>
                        <div class="form-group">
                            <label>Jam Selesai</label>
                            <input type="time" class="form-control" name="end_hour" required>
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

    {{-- Doctor Services Section --}}
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Perawatan yang Bisa Dilakukan</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('rawdoctor.services.update', $doctor->id) }}">
                    @csrf
                    <div class="row">
                        @foreach ($allServices as $service)
                            <div class="col-md-4 mb-2">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox"
                                        class="custom-control-input"
                                        id="svc_{{ $service->id }}"
                                        name="services[]"
                                        value="{{ $service->id }}"
                                        {{ in_array($service->id, $doctorServiceIds) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="svc_{{ $service->id }}">
                                        <strong>{{ $service->name }}</strong>
                                        <small class="text-muted d-block">{{ $service->duration_minutes }} menit</small>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Simpan Perubahan Service
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
    <script>
        function deleteDoctor(id) {
            Swal.fire({
                title: 'Apakah Anda Yakin?',
                text: "Data doctor yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#deleteDoctor').submit();
                }
            })
        }

        function deleteSchedule(id) {
            Swal.fire({
                title: 'Apakah Anda Yakin?',
                text: "Jadwal yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#deleteSchedule' + id).submit();
                }
            })
        }
    </script>
@endsection
