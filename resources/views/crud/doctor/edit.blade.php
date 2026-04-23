@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Detail Doctor</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        {{-- Doctor Info --}}
        <div class="st-card mb-3">
            <div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-user-md"></i> Informasi Doctor</h6>
                <form method="post" action="{{ route('rawdoctor.update', $doctor->id) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <a href="{{ route('rawdoctor') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                        <i class="fa fa-arrow-left"></i> Kembali
                    </a>

                    <table class="st-table">
                        <tr>
                            <td width="20%" style="font-weight:600;">Nama <span class="text-danger">*</span></td>
                            <td>
                                <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                    type="text" name="name" value="{{ old('name', $doctor->name) }}" />
                                @if ($errors->has('name'))
                                    <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                @endif
                            </td>
                        </tr>
                        @php $stIsSales = session('group_name') === 'Sales'; @endphp
                        <tr>
                            <td style="font-weight:600;">Foto</td>
                            <td>
                                @if ($doctor->photo)
                                    <div class="mb-2 d-flex align-items-center" style="gap:12px;">
                                        <img src="{{ $doctor->photo_url }}" alt="Foto {{ $doctor->name }}"
                                            style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid #e0d4c5;">
                                        @if (!$stIsSales)
                                            <button type="button" class="st-btn-outline-brown" onclick="deleteDoctorPhoto()"
                                                style="font-size:12px; padding:6px 12px;">
                                                <i class="fa fa-trash"></i> Hapus Foto
                                            </button>
                                        @endif
                                    </div>
                                @endif

                                @if ($stIsSales)
                                    @if (!$doctor->photo)
                                        <small class="text-muted"><i class="fa fa-info-circle"></i> Belum ada foto dokter.</small>
                                    @endif
                                @else
                                    <input class="form-control {{ $errors->has('photo') ? 'is-invalid' : '' }}"
                                        type="file" name="photo" accept="image/jpeg,image/png,image/webp" />
                                    <small class="text-muted">
                                        {{ $doctor->photo ? 'Upload file baru untuk mengganti foto.' : 'Opsional.' }}
                                        Format JPG/PNG/WebP, maksimal 2 MB.
                                    </small>
                                    @if ($errors->has('photo'))
                                        <div class="invalid-feedback">{{ $errors->first('photo') }}</div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>
                                <button class="st-btn-brand" type="submit">
                                    <i class="fa fa-save"></i> Simpan
                                </button>
                                <button type="button" class="st-btn-outline-brown" onclick="deleteDoctor()">
                                    <i class="fa fa-trash"></i> Hapus
                                </button>
                            </td>
                        </tr>
                    </table>
                </form>
                <form action="{{ route('rawdoctor.destroy', $doctor->id) }}" method="POST" id="deleteDoctor">
                    @csrf @method('DELETE')
                </form>
                @if ($doctor->photo && !$stIsSales)
                    <form action="{{ route('rawdoctor.photo.delete', $doctor->id) }}" method="POST" id="deleteDoctorPhoto">
                        @csrf @method('DELETE')
                    </form>
                @endif
            </div>
        </div>

        {{-- Doctor Schedules --}}
        <div class="st-card mb-3">
            <div class="st-card__head">
                <h6 class="st-section-title mb-0"><i class="fa fa-calendar-alt"></i> Jadwal Doctor</h6>
            </div>
            <div class="st-card__body">
                <button class="st-btn-brand mb-3" data-toggle="modal" data-target="#addScheduleModal">
                    <i class="fa fa-plus"></i> Tambah Jadwal
                </button>

                @php
                    $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
                @endphp

                <div class="st-table-wrap--scroll">
                    <table class="st-table st-table--compact">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Hari</th>
                                <th>Cabang</th>
                                <th>Jam Mulai</th>
                                <th>Jam Selesai</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($schedules as $key => $schedule)
                                <tr>
                                    <td class="st-num">{{ $key + 1 }}</td>
                                    <td>{{ $dayNames[$schedule->day] ?? $schedule->day }}</td>
                                    <td>{{ $schedule->branch->name ?? '-' }}</td>
                                    <td>{{ $schedule->start_hour }}</td>
                                    <td>{{ $schedule->end_hour }}</td>
                                    <td nowrap>
                                        <div class="d-flex" style="gap:4px;">
                                            <button class="st-icon-btn"
                                                data-toggle="modal" data-target="#editScheduleModal{{ $schedule->id }}"
                                                title="Edit">
                                                <i class="fa fa-pencil-alt"></i>
                                            </button>
                                            <button class="st-icon-btn" onclick="deleteSchedule('{{ $schedule->id }}')" title="Hapus">
                                                <i class="fa fa-trash" style="color:#B23A3A"></i>
                                            </button>
                                            <form action="{{ route('rawdoctor.schedule.destroy', [$doctor->id, $schedule->id]) }}"
                                                method="POST" id="deleteSchedule{{ $schedule->id }}">
                                                @csrf @method('DELETE')
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Edit Schedule Modal --}}
                                <div class="modal fade" id="editScheduleModal{{ $schedule->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST"
                                                action="{{ route('rawdoctor.schedule.update', [$doctor->id, $schedule->id]) }}">
                                                @csrf @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Jadwal</h5>
                                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label style="font-weight:600;">Hari</label>
                                                        <select class="form-control" name="day" required>
                                                            @foreach ($dayNames as $num => $name)
                                                                <option value="{{ $num }}"
                                                                    {{ $schedule->day == $num ? 'selected' : '' }}>
                                                                    {{ $name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label style="font-weight:600;">Cabang</label>
                                                        <select class="form-control" name="raw_branch_id" required>
                                                            <option value="">- Pilih Cabang -</option>
                                                            @foreach ($branches as $branch)
                                                                <option value="{{ $branch->id }}"
                                                                    {{ $schedule->raw_branch_id == $branch->id ? 'selected' : '' }}>
                                                                    {{ $branch->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label style="font-weight:600;">Jam Mulai</label>
                                                        <input type="time" class="form-control" name="start_hour"
                                                            value="{{ $schedule->start_hour }}" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label style="font-weight:600;">Jam Selesai</label>
                                                        <input type="time" class="form-control" name="end_hour"
                                                            value="{{ $schedule->end_hour }}" required>
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
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center st-muted">Belum ada jadwal</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Doctor Services --}}
        <div class="st-card mb-3">
            <div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-stethoscope"></i> Perawatan yang Bisa Dilakukan</h6>
                <form method="POST" action="{{ route('rawdoctor.services.update', $doctor->id) }}">
                    @csrf
                    <div class="row">
                        @foreach ($allServices as $service)
                            <div class="col-md-4 mb-2">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input"
                                        id="svc_{{ $service->id }}" name="services[]"
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
                    <hr style="border-color:var(--divider-soft)">
                    <button type="submit" class="st-btn-brand">
                        <i class="fa fa-save"></i> Simpan Perubahan Service
                    </button>
                </form>
            </div>
        </div>

        {{-- Cuti Dokter --}}
        <div class="st-card">
            <div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-plane-departure"></i> Cuti Dokter</h6>

                <form method="post" action="{{ route('rawdoctor.leave.store', $doctor->id) }}" class="mb-3">
                    @csrf
                    <div class="row">
                        <div class="col-md-3">
                            <label style="font-weight:600; font-size:12px;">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label style="font-weight:600; font-size:12px;">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label style="font-weight:600; font-size:12px;">Alasan</label>
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Mis: Liburan keluarga" maxlength="255">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="st-btn-brand w-100" style="font-size:12px; padding:6px 12px;">
                                <i class="fa fa-plus"></i> Tambah Cuti
                            </button>
                        </div>
                    </div>
                </form>

                <div class="st-table-wrap--scroll">
                    <table class="st-table st-table--compact">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Tanggal Mulai</th>
                                <th>Tanggal Selesai</th>
                                <th>Durasi</th>
                                <th>Alasan</th>
                                <th>Status</th>
                                <th width="8%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leaves as $i => $leave)
                                @php
                                    $start = \Carbon\Carbon::parse($leave->start_date);
                                    $end   = \Carbon\Carbon::parse($leave->end_date);
                                    $today = \Carbon\Carbon::today();
                                    $durasi = $start->diffInDays($end) + 1;
                                    if ($today->lt($start)) {
                                        $status = ['label' => 'Akan Datang', 'pill' => 'info'];
                                    } elseif ($today->between($start, $end)) {
                                        $status = ['label' => 'Sedang Cuti', 'pill' => 'warning'];
                                    } else {
                                        $status = ['label' => 'Selesai', 'pill' => 'neutral'];
                                    }
                                @endphp
                                <tr>
                                    <td class="st-num">{{ $i + 1 }}</td>
                                    <td><span class="st-date">{{ $start->format('d/m/Y') }}</span></td>
                                    <td><span class="st-date">{{ $end->format('d/m/Y') }}</span></td>
                                    <td>{{ $durasi }} hari</td>
                                    <td>{{ $leave->reason ?: '-' }}</td>
                                    <td>
                                        <span class="st-pill st-pill--{{ $status['pill'] }}">{{ $status['label'] }}</span>
                                    </td>
                                    <td>
                                        <button class="st-icon-btn" onclick="deleteLeave('{{ $leave->id }}')" title="Hapus">
                                            <i class="fa fa-trash" style="color:#B23A3A"></i>
                                        </button>
                                        <form method="post" action="{{ route('rawdoctor.leave.destroy', [$doctor->id, $leave->id]) }}"
                                            id="deleteLeave{{ $leave->id }}">
                                            @csrf @method('DELETE')
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center st-muted">Belum ada data cuti</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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
                        <h5 class="modal-title">Tambah Jadwal — {{ $doctor->name }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label style="font-weight:600;">Hari</label>
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
                            <label style="font-weight:600;">Cabang</label>
                            <select class="form-control" name="raw_branch_id" required>
                                <option value="">- Pilih Cabang -</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600;">Jam Mulai</label>
                            <input type="time" class="form-control" name="start_hour" required>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600;">Jam Selesai</label>
                            <input type="time" class="form-control" name="end_hour" required>
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
    function deleteDoctor() {
        Swal.fire({
            title: 'Apakah Anda Yakin?', text: "Data doctor yang dihapus tidak dapat dikembalikan!",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal',
        }).then((result) => { if (result.isConfirmed) { $('#deleteDoctor').submit(); } })
    }

    function deleteDoctorPhoto() {
        Swal.fire({
            title: 'Hapus Foto Dokter?', text: "Foto akan dihapus permanen.",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal',
        }).then((result) => { if (result.isConfirmed) { $('#deleteDoctorPhoto').submit(); } })
    }

    function deleteSchedule(id) {
        Swal.fire({
            title: 'Hapus Jadwal?', text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal',
        }).then((result) => { if (result.isConfirmed) { $('#deleteSchedule' + id).submit(); } })
    }

    function deleteLeave(id) {
        Swal.fire({
            title: 'Hapus Cuti?', text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal',
        }).then((result) => { if (result.isConfirmed) { $('#deleteLeave' + id).submit(); } })
    }
</script>
@endsection
