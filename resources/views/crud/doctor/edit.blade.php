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
                                                    <h5 class="modal-title"><i class="fa fa-pencil" style="margin-right:6px;opacity:.8;"></i> Edit Jadwal — {{ $doctor->name }}</h5>
                                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label>Hari <span class="text-danger">*</span></label>
                                                                <select class="form-control form-control-sm" name="day" required>
                                                                    @foreach ($dayNames as $num => $name)
                                                                        <option value="{{ $num }}" {{ $schedule->day == $num ? 'selected' : '' }}>{{ $name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label>Cabang <span class="text-danger">*</span></label>
                                                                <select class="form-control form-control-sm" name="raw_branch_id" required>
                                                                    <option value="">- Pilih Cabang -</option>
                                                                    @foreach ($branches as $branch)
                                                                        <option value="{{ $branch->id }}" {{ $schedule->raw_branch_id == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label>Jam Mulai <span class="text-danger">*</span></label>
                                                                <input type="time" class="form-control form-control-sm" name="start_hour" step="300" value="{{ $schedule->start_hour }}" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label>Jam Selesai <span class="text-danger">*</span></label>
                                                                <input type="time" class="form-control form-control-sm" name="end_hour" step="300" value="{{ $schedule->end_hour }}" required>
                                                            </div>
                                                        </div>
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

        {{-- Doctor Services + Durasi per Cabang --}}
        <div class="st-card mb-3">
            <div class="st-card__body">
                @php $fmtDur = fn($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp
                <h6 class="st-section-title"><i class="fa fa-stethoscope"></i> Perawatan &amp; Durasi per Cabang</h6>
                <p class="small text-muted mb-2" style="font-size:11px;">
                    Centang perawatan yang dilayani dokter ini — <strong>berlaku universal</strong> di semua cabang.
                    @if(config('features.flexible_scheduling'))
                        Kolom <strong>Durasi</strong> diatur <strong>per cabang</strong>: pilih cabang di bawah lalu isi durasinya. Ubah durasi di satu cabang <strong>tidak</strong> memengaruhi cabang lain. Kosongkan = pakai durasi default service.
                    @endif
                </p>
                <form method="POST" action="{{ route('rawdoctor.services.update', $doctor->id) }}">
                    @csrf

                    @if(config('features.flexible_scheduling'))
                        @if($doctorBranches->isEmpty())
                            <div class="alert alert-warning" style="font-size:11px; padding:8px 12px;">
                                <i class="fa fa-exclamation-triangle"></i>
                                Dokter ini belum punya jadwal di cabang mana pun — durasi per cabang belum bisa diatur. Tambahkan jadwal dulu. Centang perawatan tetap bisa disimpan.
                            </div>
                        @else
                            <div class="form-group" style="max-width:300px;">
                                <label style="font-weight:600; font-size:12px;">Atur durasi untuk cabang</label>
                                <select id="durationBranchSel" class="form-control form-control-sm">
                                    @foreach($doctorBranches as $b)
                                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted" style="font-size:10px;">Durasi tiap cabang tersimpan terpisah.</small>
                            </div>
                        @endif
                    @endif

                    <div class="row">
                        @foreach ($allServices as $service)
                            @php $isChecked = in_array($service->id, $doctorServiceIds); @endphp
                            <div class="col-md-6 mb-2">
                                <div class="d-flex align-items-center" style="gap:8px;">
                                    <div class="custom-control custom-checkbox" style="flex:1;">
                                        <input type="checkbox" class="custom-control-input doctor-service-check"
                                            id="svc_{{ $service->id }}" name="services[]"
                                            value="{{ $service->id }}"
                                            data-service="{{ $service->id }}"
                                            data-subdetails="subdetails_{{ $service->id }}"
                                            {{ $isChecked ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="svc_{{ $service->id }}">
                                            <strong>{{ $service->name }}</strong>
                                            <small class="text-muted d-block">Default: {{ $fmtDur($service->duration_minutes) }} menit</small>
                                        </label>
                                    </div>
                                    @if(config('features.flexible_scheduling') && $doctorBranches->isNotEmpty())
                                        @foreach($doctorBranches as $bi => $b)
                                            @php $ov = $branchServiceDurations[$b->id][$service->id] ?? null; @endphp
                                            <input type="number"
                                                name="services_duration[{{ $b->id }}][{{ $service->id }}]"
                                                class="form-control form-control-sm dur-svc-input"
                                                data-branch="{{ $b->id }}" data-service="{{ $service->id }}"
                                                style="width:90px; flex:0 0 90px; {{ $bi === 0 ? '' : 'display:none;' }}"
                                                min="5" step="5"
                                                placeholder="{{ $fmtDur($service->duration_minutes) }}"
                                                value="{{ $ov !== null ? $fmtDur($ov) : '' }}"
                                                {{ $isChecked ? '' : 'disabled' }}>
                                        @endforeach
                                    @endif
                                </div>

                                @if(config('features.flexible_scheduling') && $service->allDetails->isNotEmpty() && $doctorBranches->isNotEmpty())
                                    <div class="subdetail-block" id="subdetails_{{ $service->id }}"
                                        style="margin-left:24px; margin-top:8px; padding:8px 10px; background:#fdf8f1; border:1px solid #f0e3d2; border-radius:8px; {{ $isChecked ? '' : 'display:none;' }}">
                                        <button type="button" class="btn-toggle-subdetail" data-target="subdetail-list-{{ $service->id }}"
                                            style="background:none; border:none; padding:0; font-size:11px; color:#6b4c1e; cursor:pointer; font-weight:600; text-transform:none; letter-spacing:normal;">
                                            <i class="fa fa-chevron-right"></i>
                                            Durasi sub-detail per cabang ({{ $service->allDetails->count() }})
                                        </button>
                                        <div id="subdetail-list-{{ $service->id }}" style="margin-top:8px; display:none;">
                                            @foreach($service->allDetails as $detail)
                                                <div class="d-flex align-items-center" style="gap:6px; margin-bottom:4px;">
                                                    <span style="flex:1; font-size:11px; color:#5b3d18;">
                                                        {{ $detail->name }}
                                                        <small class="text-muted">(default {{ $fmtDur($detail->duration_minutes) }} mnt)</small>
                                                    </span>
                                                    @foreach($doctorBranches as $bi => $b)
                                                        @php $dov = $branchDetailDurations[$b->id][$detail->id] ?? null; @endphp
                                                        <input type="number"
                                                            name="details_duration[{{ $b->id }}][{{ $detail->id }}]"
                                                            class="form-control form-control-sm dur-detail-input"
                                                            data-branch="{{ $b->id }}"
                                                            style="width:70px; font-size:11px; {{ $bi === 0 ? '' : 'display:none;' }}"
                                                            min="5" step="5"
                                                            placeholder="{{ $fmtDur($detail->duration_minutes) }}"
                                                            value="{{ $dov !== null ? $fmtDur($dov) : '' }}">
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <hr style="border-color:var(--divider-soft)">
                    <button type="submit" class="st-btn-brand">
                        <i class="fa fa-save"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

        <script>
            // Branch selector → tampilkan hanya input durasi cabang terpilih.
            (function () {
                const sel = document.getElementById('durationBranchSel');
                function applyBranch(bid) {
                    document.querySelectorAll('.dur-svc-input, .dur-detail-input').forEach(function (inp) {
                        inp.style.display = (inp.dataset.branch === bid) ? '' : 'none';
                    });
                }
                if (sel) {
                    sel.addEventListener('change', function () { applyBranch(sel.value); });
                    applyBranch(sel.value);
                }
            })();

            // Centang/uncentang perawatan → enable/disable input durasi service
            // itu di SEMUA cabang + toggle blok sub-detail.
            document.querySelectorAll('.doctor-service-check').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    document.querySelectorAll('.dur-svc-input[data-service="' + cb.dataset.service + '"]').forEach(function (inp) {
                        inp.disabled = !cb.checked;
                        if (!cb.checked) inp.value = '';
                    });
                    const subBlock = document.getElementById(cb.dataset.subdetails);
                    if (subBlock) {
                        subBlock.style.display = cb.checked ? '' : 'none';
                        if (!cb.checked) {
                            subBlock.querySelectorAll('input[type=number]').forEach(function (inp) { inp.value = ''; });
                        }
                    }
                });
            });

            // Toggle expand/collapse sub-detail list
            document.querySelectorAll('.btn-toggle-subdetail').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const list = document.getElementById(btn.dataset.target);
                    if (!list) return;
                    const isHidden = list.style.display === 'none';
                    list.style.display = isHidden ? '' : 'none';
                    const icon = btn.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('fa-chevron-right', !isHidden);
                        icon.classList.toggle('fa-chevron-down', isHidden);
                    }
                });
            });
        </script>

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

        {{-- Jadwal Khusus (disabled until flexible_scheduling feature is approved) --}}
        @if(config('features.flexible_scheduling'))
        <div class="st-card mt-3">
            <div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-calendar-plus"></i> Jadwal Khusus</h6>

                {{-- Info box --}}
                <div style="background:#fef9f0; border:1px solid #f0d9a8; border-radius:10px; padding:12px 16px; margin-bottom:20px; display:flex; gap:10px; align-items:flex-start;">
                    <i class="fa fa-lightbulb-o" style="color:#b07d2a; margin-top:2px; flex-shrink:0;"></i>
                    <span style="font-size:12px; color:#6b4c1e; line-height:1.6;">
                        Gunakan bagian ini bila ada tanggal tertentu yang jadwalnya <strong>berbeda dari jadwal mingguan biasa</strong> dokter —
                        misalnya dokter pulang lebih awal, masuk di hari yang biasanya libur, atau ada perubahan jam mendadak.
                    </span>
                </div>

                {{-- Form tambah --}}
                <form method="post" action="{{ route('rawdoctor.exception.store', $doctor->id) }}" class="exc-add-form mb-4">
                    @csrf

                    {{-- Baris 1: tipe (radio card) --}}
                    <div class="mb-3">
                        <label style="font-weight:700; font-size:12px; display:block; margin-bottom:8px;">Situasinya apa? <span class="text-danger">*</span></label>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <label class="exc-type-card" id="exc-card-replace">
                                <input type="radio" name="type" value="replace" checked style="display:none;">
                                <div style="display:flex; gap:10px; align-items:flex-start;">
                                    <span style="font-size:1.3rem; line-height:1;">🕐</span>
                                    <div>
                                        <div style="font-weight:700; font-size:12px; color:#461500;">Jam Berbeda dari Biasanya</div>
                                        <div style="font-size:11px; color:#7a6a5a; margin-top:2px; line-height:1.4;">Dokter tetap kerja di hari itu, tapi jam mulai atau selesainya berbeda dari jadwal rutin</div>
                                    </div>
                                </div>
                            </label>
                            <label class="exc-type-card" id="exc-card-add">
                                <input type="radio" name="type" value="add" style="display:none;">
                                <div style="display:flex; gap:10px; align-items:flex-start;">
                                    <span style="font-size:1.3rem; line-height:1;">📅</span>
                                    <div>
                                        <div style="font-weight:700; font-size:12px; color:#461500;">Hari Tambahan</div>
                                        <div style="font-size:11px; color:#7a6a5a; margin-top:2px; line-height:1.4;">Dokter buka di tanggal ini, padahal hari itu biasanya libur</div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Baris 2: tanggal + cabang + jam --}}
                    <div class="row">
                        <div class="col-md-2">
                            <label style="font-weight:600; font-size:12px;">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-2">
                            <label style="font-weight:600; font-size:12px;">Cabang <span class="text-danger">*</span></label>
                            <select name="raw_branch_id" class="form-control form-control-sm" required>
                                <option value="">- Pilih -</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label style="font-weight:600; font-size:12px;">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" name="start_hour" class="form-control form-control-sm" step="300" required>
                        </div>
                        <div class="col-md-2">
                            <label style="font-weight:600; font-size:12px;">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time" name="end_hour" class="form-control form-control-sm" step="300" required>
                        </div>
                        <div class="col-md-2">
                            <label style="font-weight:600; font-size:12px;">Catatan <small class="text-muted">(opsional)</small></label>
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Mis: Dokter ada acara" maxlength="255">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="st-btn-brand w-100" style="font-size:12px; padding:6px 12px;">
                                <i class="fa fa-plus"></i> Simpan Jadwal Khusus
                            </button>
                        </div>
                    </div>
                </form>

                {{-- Tabel data --}}
                <div class="st-table-wrap--scroll">
                    <table class="st-table st-table--compact">
                        <thead>
                            <tr>
                                <th width="4%">No</th>
                                <th>Tanggal</th>
                                <th>Cabang</th>
                                <th>Situasi</th>
                                <th>Jam Kerja</th>
                                <th>Catatan</th>
                                <th width="8%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($exceptions as $i => $ex)
                                <tr>
                                    <td class="st-num">{{ $i + 1 }}</td>
                                    <td><span class="st-date">{{ \Carbon\Carbon::parse($ex->date)->isoFormat('dddd, D MMM Y') }}</span></td>
                                    <td>{{ $ex->branch->name ?? '-' }}</td>
                                    <td>
                                        @if ($ex->type === 'replace')
                                            <span style="display:inline-flex; align-items:center; gap:4px; background:#fff8e1; color:#7a5c00; border:1px solid #ffe082; border-radius:20px; padding:3px 10px; font-size:11px; font-weight:700;">
                                                🕐 Jam Berbeda
                                            </span>
                                        @else
                                            <span style="display:inline-flex; align-items:center; gap:4px; background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7; border-radius:20px; padding:3px 10px; font-size:11px; font-weight:700;">
                                                📅 Hari Tambahan
                                            </span>
                                        @endif
                                    </td>
                                    <td style="font-weight:600;">{{ substr($ex->start_hour, 0, 5) }} – {{ substr($ex->end_hour, 0, 5) }}</td>
                                    <td style="color:#7a6a5a;">{{ $ex->reason ?: '-' }}</td>
                                    <td>
                                        <button class="st-icon-btn" title="Edit"
                                            onclick="openEditException('{{ $ex->id }}','{{ $ex->raw_branch_id }}','{{ $ex->date }}','{{ $ex->type }}','{{ substr($ex->start_hour,0,5) }}','{{ substr($ex->end_hour,0,5) }}','{{ addslashes($ex->reason) }}')">
                                            <i class="fa fa-pencil" style="color:#461500"></i>
                                        </button>
                                        <button class="st-icon-btn" onclick="deleteException('{{ $ex->id }}')" title="Hapus">
                                            <i class="fa fa-trash" style="color:#B23A3A"></i>
                                        </button>
                                        <form method="post" action="{{ route('rawdoctor.exception.destroy', [$doctor->id, $ex->id]) }}"
                                            id="deleteException{{ $ex->id }}">
                                            @csrf @method('DELETE')
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center st-muted">Belum ada jadwal khusus</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif {{-- features.flexible_scheduling --}}
    </div>

    {{-- Add Schedule Modal --}}
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('rawdoctor.schedule.store', $doctor->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fa fa-calendar-plus" style="margin-right:6px;opacity:.8;"></i> Tambah Jadwal — {{ $doctor->name }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Hari <span class="text-danger">*</span></label>
                                    <select class="form-control form-control-sm" name="day" required>
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
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Cabang <span class="text-danger">*</span></label>
                                    <select class="form-control form-control-sm" name="raw_branch_id" required>
                                        <option value="">- Pilih Cabang -</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Jam Mulai <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control form-control-sm" name="start_hour" step="300" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Jam Selesai <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control form-control-sm" name="end_hour" step="300" required>
                                </div>
                            </div>
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

    @if(config('features.flexible_scheduling'))
    function deleteException(id) {
        Swal.fire({
            title: 'Hapus Jadwal Khusus?', text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal',
        }).then((result) => { if (result.isConfirmed) { $('#deleteException' + id).submit(); } })
    }

    // Radio card styling untuk type selection
    document.querySelectorAll('.exc-type-card input[type=radio]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.exc-type-card').forEach(c => c.classList.remove('exc-type-card--active'));
            if (this.checked) this.closest('.exc-type-card').classList.add('exc-type-card--active');
        });
    });
    // Set active state on load (replace is checked by default)
    document.querySelectorAll('.exc-type-card input[type=radio]:checked').forEach(function (r) {
        r.closest('.exc-type-card').classList.add('exc-type-card--active');
    });

    // Edit exception modal
    function openEditException(id, branchId, date, type, startHour, endHour, reason) {
        document.getElementById('editExcId').value       = id;
        document.getElementById('editExcBranch').value   = branchId;
        document.getElementById('editExcDate').value     = date;
        document.getElementById('editExcStart').value    = startHour;
        document.getElementById('editExcEnd').value      = endHour;
        document.getElementById('editExcReason').value   = reason;
        // Set radio + visual active state
        document.querySelectorAll('#editExceptionModal .exc-type-card').forEach(c => {
            const r = c.querySelector('input[type=radio]');
            r.checked = (r.value === type);
            c.classList.toggle('exc-type-card--active', r.checked);
        });
        document.getElementById('editExcAction').value =
            '{{ route("rawdoctor.exception.update", [$doctor->id, "__ID__"]) }}'.replace('__ID__', id);
        $('#editExceptionModal').modal('show');
    }

    document.querySelectorAll('#editExceptionModal .exc-type-card input[type=radio]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('#editExceptionModal .exc-type-card').forEach(c => c.classList.remove('exc-type-card--active'));
            if (this.checked) this.closest('.exc-type-card').classList.add('exc-type-card--active');
        });
    });

    document.getElementById('editExcSubmit').addEventListener('click', function () {
        document.getElementById('editExceptionForm').action = document.getElementById('editExcAction').value;
        document.getElementById('editExceptionForm').submit();
    });
    @endif {{-- features.flexible_scheduling --}}
</script>

@if(config('features.flexible_scheduling'))
{{-- Edit Exception Modal --}}
<div class="modal fade" id="editExceptionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#461500; color:#fff; border-radius:8px 8px 0 0;">
                <h5 class="modal-title" style="font-size:14px; font-weight:700;">
                    <i class="fa fa-pencil"></i> Edit Jadwal Khusus
                </h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editExceptionForm" method="post">
                    @csrf @method('PUT')
                    <input type="hidden" id="editExcId" name="_exc_id">
                    <input type="hidden" id="editExcAction">

                    <div class="mb-3">
                        <label style="font-weight:700; font-size:12px; display:block; margin-bottom:8px;">Situasinya apa? <span class="text-danger">*</span></label>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <label class="exc-type-card">
                                <input type="radio" name="type" value="replace" style="display:none;">
                                <div style="display:flex; gap:10px; align-items:flex-start;">
                                    <span style="font-size:1.3rem; line-height:1;">🕐</span>
                                    <div>
                                        <div style="font-weight:700; font-size:12px; color:#461500;">Jam Berbeda dari Biasanya</div>
                                        <div style="font-size:11px; color:#7a6a5a; margin-top:2px; line-height:1.4;">Dokter tetap kerja, tapi jam berbeda dari jadwal rutin</div>
                                    </div>
                                </div>
                            </label>
                            <label class="exc-type-card">
                                <input type="radio" name="type" value="add" style="display:none;">
                                <div style="display:flex; gap:10px; align-items:flex-start;">
                                    <span style="font-size:1.3rem; line-height:1;">📅</span>
                                    <div>
                                        <div style="font-weight:700; font-size:12px; color:#461500;">Hari Tambahan</div>
                                        <div style="font-size:11px; color:#7a6a5a; margin-top:2px; line-height:1.4;">Dokter buka di tanggal ini, padahal hari itu biasanya libur</div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <label style="font-weight:600; font-size:12px;">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="date" id="editExcDate" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label style="font-weight:600; font-size:12px;">Cabang <span class="text-danger">*</span></label>
                            <select name="raw_branch_id" id="editExcBranch" class="form-control form-control-sm" required>
                                <option value="">- Pilih -</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label style="font-weight:600; font-size:12px;">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" name="start_hour" id="editExcStart" class="form-control form-control-sm" step="300" required>
                        </div>
                        <div class="col-md-2">
                            <label style="font-weight:600; font-size:12px;">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time" name="end_hour" id="editExcEnd" class="form-control form-control-sm" step="300" required>
                        </div>
                        <div class="col-md-2">
                            <label style="font-weight:600; font-size:12px;">Catatan</label>
                            <input type="text" name="reason" id="editExcReason" class="form-control form-control-sm" maxlength="255">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                <button type="button" id="editExcSubmit" class="st-btn-brand">
                    <i class="fa fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </div>
    </div>
</div>
@endif {{-- features.flexible_scheduling --}}

<style>
    /* Radio card — override global uppercase label */
    .exc-type-card {
        display: block;
        padding: 12px 14px;
        border: 2px solid #e8ddd4;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
        background: #fffaf5;
        margin: 0;
    }
    .exc-type-card:hover { border-color: #c0946a; background: #fef5ec; }
    .exc-type-card--active { border-color: #461500 !important; background: #fef5ec !important; box-shadow: 0 0 0 3px rgba(70,21,0,0.08); }
    .exc-type-card * { text-transform: none !important; letter-spacing: normal !important; }
    .exc-type-card label { font-weight: 400 !important; margin: 0; }

    /* Consistent modal styling */
    .modal-content { border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.18); }
    .modal-header { background: linear-gradient(135deg, #461500, #6b2400) !important; padding: 14px 20px; border-bottom: none; }
    .modal-header .modal-title { color: #fff !important; font-size: 14px !important; font-weight: 700; }
    .modal-header .close { color: #fff !important; opacity: 0.8; text-shadow: none; }
    .modal-header .close:hover { opacity: 1; }
    .modal-body { padding: 20px; }
    .modal-body .form-group { margin-bottom: 14px; }
    .modal-body .form-group label { font-size: 12px; font-weight: 600; margin-bottom: 4px; }
    .modal-footer { border-top: 1px solid #f0e8e0; padding: 12px 20px; }
</style>

@endsection
