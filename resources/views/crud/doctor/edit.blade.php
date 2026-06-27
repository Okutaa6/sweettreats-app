@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/doctor-redesign.css') }}?v={{ @filemtime(public_path('assets/css/doctor-redesign.css')) ?: '1' }}">
@endsection

@section('content')
<div class="container-fluid">
    <div class="page-title-box"><h4 class="page-title">Detail Dokter</h4></div>

    @include('layouts.notif')

    @php
        $avatarColors = ['#7A3415', '#52200A', '#8A3A12', '#6B2400', '#9A6614', '#5A2410', '#7E2E0E', '#693012'];
        $initial = mb_strtoupper(mb_substr(preg_replace('/^drg\.?\s*/i', '', trim($doctor->name)) ?: '?', 0, 1));
        $avColor = $avatarColors[crc32((string) $doctor->id) % count($avatarColors)];
        $branchNames = $practiceBranches->pluck('name')->implode(' · ');
        $crumbBranch = optional($practiceBranches->first())->name;
        // Edit profil dokter (nama/foto) = non-Sales saja (Admin/Admin General/
        // Super Admin/Admin Backup=head sales Nissa). Sales tetap read-only.
        $canEdit = session('group_name') !== 'Sales';
        // Kalau ada error validasi update() → buka form edit + tab Informasi.
        $showEdit = $canEdit && $errors->any();
        if ($errors->any()) { $activeTab = 'informasi'; }
    @endphp

    <div class="dxd">

        {{-- ════════ BACK + BREADCRUMB ════════ --}}
        <div class="dx-back">
            <a href="{{ route('rawdoctor') }}" class="dx-back__btn" title="Kembali ke daftar dokter"><i class="fa fa-chevron-left"></i></a>
            <span class="dx-back__crumb">Doctor{!! $crumbBranch ? ' · <strong>' . e($crumbBranch) . '</strong>' : '' !!}</span>
        </div>

        {{-- ════════ IDENTITY HEADER + STATUS TOGGLE ════════ --}}
        <div class="dxd-card dxd-head">
            <div class="dxd-avatar" style="background:{{ $avColor }};">@if($doctor->photo)<img src="{{ $doctor->photo_url }}" alt="{{ $doctor->name }}">@else{{ $initial }}@endif</div>
            <div class="dxd-head__main">
                <div class="dxd-head__title">
                    <span class="dxd-head__name">{{ $doctor->name }}</span>
                    <span class="dxd-spec" style="color:{{ $spec['color'] }};background:{{ $spec['bg'] }};">{{ $spec['label'] }}</span>
                </div>
                <div class="dxd-head__meta">
                    <span><i class="fa fa-calendar-alt"></i> {{ $jamKerja }} jam kerja/minggu</span>
                    <span><i class="fa fa-map-marker-alt"></i> {{ $branchNames ?: 'Belum ada cabang' }}</span>
                    <span><i class="fa fa-clipboard-list"></i> {{ number_format($reservationCount, 0, ',', '.') }} reservasi total</span>
                </div>
            </div>
            <form method="POST" action="{{ route('rawdoctor.toggle-active', $doctor->id) }}" id="dxdStatusForm">
                @csrf @method('PATCH')
                <button type="submit" class="dxd-status dxd-status--{{ $doctor->is_active ? 'on' : 'off' }}" id="dxdStatusBtn">
                    <div class="dxd-status__txt">
                        <div class="dxd-status__label">Status Dokter</div>
                        <div class="dxd-status__val">{{ $doctor->is_active ? 'Aktif' : 'Nonaktif' }}</div>
                    </div>
                    <div class="dxd-switch"><div class="dxd-switch__knob"></div></div>
                </button>
            </form>
        </div>

        {{-- ════════ TAB NAV ════════ --}}
        <div class="dxd-tabs">
            <button type="button" class="dxd-tab {{ $activeTab === 'informasi' ? 'active' : '' }}" data-dxd-target="informasi"><i class="fa fa-user"></i> Informasi</button>
            <button type="button" class="dxd-tab {{ $activeTab === 'jadwal' ? 'active' : '' }}" data-dxd-target="jadwal"><i class="fa fa-calendar-alt"></i> Jadwal <span class="dxd-tab__badge">{{ $jamKerja }}</span></button>
            <button type="button" class="dxd-tab {{ $activeTab === 'cuti' ? 'active' : '' }}" data-dxd-target="cuti"><i class="fa fa-umbrella-beach"></i> Cuti &amp; Khusus</button>
            <button type="button" class="dxd-tab {{ $activeTab === 'perawatan' ? 'active' : '' }}" data-dxd-target="perawatan"><i class="fa fa-tooth"></i> Perawatan &amp; Durasi</button>
        </div>

        {{-- ════════ TAB: INFORMASI (read-only) ════════ --}}
        <div class="dxd-card" data-dxd-panel="informasi" style="display:{{ $activeTab === 'informasi' ? '' : 'none' }}; padding:22px 24px;">
            <div class="dxd-sec">
                <div class="dxd-sec__l"><span class="dxd-sec__tick"></span><span class="dxd-sec__t--sm">Informasi Dokter</span></div>
                @if($canEdit)
                    <button type="button" class="dxd-editbtn" id="dxdEditBtn"><i class="fa fa-pen"></i> Edit Profil</button>
                @else
                    <span class="dxd-readonly"><i class="fa fa-lock"></i> Hanya-baca</span>
                @endif
            </div>

            {{-- VIEW (read-only) --}}
            <div id="dxdInfoView" style="display:{{ $showEdit ? 'none' : '' }};">
                <div class="dxd-info-id">
                    <div class="dxd-info-id__av" style="background:{{ $avColor }};">@if($doctor->photo)<img src="{{ $doctor->photo_url }}" alt="{{ $doctor->name }}">@else{{ $initial }}@endif</div>
                    <div>
                        <div class="dxd-info-id__name">{{ $doctor->name }}</div>
                        <span class="dxd-spec" style="color:{{ $spec['color'] }};background:{{ $spec['bg'] }};margin-top:5px;display:inline-block;">{{ $spec['label'] }}</span>
                    </div>
                </div>

                <div class="dxd-info-grid">
                    <div class="dxd-info-cell"><div class="dxd-info-cell__k">Spesialisasi</div><div class="dxd-info-cell__v">{{ $spec['label'] }}</div></div>
                    <div class="dxd-info-cell"><div class="dxd-info-cell__k">Cabang Praktik</div><div class="dxd-info-cell__v">{{ $branchNames ?: '—' }}</div></div>
                    <div class="dxd-info-cell"><div class="dxd-info-cell__k">Jam Kerja / Minggu</div><div class="dxd-info-cell__v">{{ $jamKerja }} sesi</div></div>
                    <div class="dxd-info-cell"><div class="dxd-info-cell__k">Total Reservasi</div><div class="dxd-info-cell__v">{{ number_format($reservationCount, 0, ',', '.') }}</div></div>
                </div>

                <div class="dxd-info-banner">
                    <i class="fa fa-info-circle"></i>
                    @if($canEdit)
                        <div>Profil dokter (nama, foto, spesialisasi) sebaiknya seragam di semua cabang. Spesialisasi diturunkan otomatis dari gelar pada nama. Status, jadwal, cuti, dan perawatan diatur lewat tab di atas.</div>
                    @else
                        <div>Profil dokter (nama, foto, spesialisasi) dikelola oleh <strong>admin pusat</strong> agar data seragam di semua cabang. Sales hanya dapat mengubah <strong>status aktif/nonaktif</strong> lewat tombol di atas, serta mengatur jadwal, cuti, dan perawatan dokter.</div>
                    @endif
                </div>
            </div>

            {{-- EDIT (non-Sales: head sales / admin) --}}
            @if($canEdit)
                <form id="dxdInfoEdit" method="post" action="{{ route('rawdoctor.update', $doctor->id) }}" enctype="multipart/form-data" class="dxd-edit" style="display:{{ $showEdit ? '' : 'none' }};">
                    @csrf @method('PUT')
                    <div>
                        <label class="dxd-edit__label">Nama Dokter <span style="color:#C0392B;">*</span></label>
                        <input type="text" name="name" class="dxd-input {{ $errors->has('name') ? 'dxd-input--err' : '' }}" value="{{ old('name', $doctor->name) }}" required>
                        @if($errors->has('name'))<div class="dxd-err">{{ $errors->first('name') }}</div>@endif
                        <div class="dxd-edit__hint">Spesialisasi mengikuti gelar di nama (mis. "Sp. Ort" → Orthodonti).</div>
                    </div>
                    <div>
                        <label class="dxd-edit__label">Foto Dokter</label>
                        <div class="dxd-photo-row">
                            @if($doctor->photo)<img src="{{ $doctor->photo_url }}" alt="{{ $doctor->name }}">@endif
                            <div style="flex:1; min-width:200px;">
                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="dxd-input {{ $errors->has('photo') ? 'dxd-input--err' : '' }}">
                                <div class="dxd-edit__hint">{{ $doctor->photo ? 'Upload file baru untuk mengganti foto.' : 'Opsional.' }} Format JPG/PNG/WebP, maksimal 2 MB.</div>
                                @if($errors->has('photo'))<div class="dxd-err">{{ $errors->first('photo') }}</div>@endif
                            </div>
                        </div>
                    </div>
                    <div class="dxd-edit__foot">
                        <button type="submit" class="dx-btn dx-btn--primary"><i class="fa fa-save"></i> Simpan</button>
                        <button type="button" class="dx-btn" id="dxdEditCancel">Batal</button>
                        @if($doctor->photo)
                            <button type="button" class="dx-btn dx-btn--danger" onclick="dxdDeletePhoto()" style="margin-left:auto;"><i class="fa fa-trash"></i> Hapus Foto</button>
                        @endif
                    </div>
                </form>
                <form action="{{ route('rawdoctor.photo.delete', $doctor->id) }}" method="POST" id="dxdDeletePhotoForm">@csrf @method('DELETE')</form>
            @endif
        </div>

        {{-- ════════ TAB: JADWAL (grid mingguan + drawer add/edit) ════════ --}}
        @php
            $dayLabels = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
            $H0 = 8; $H1 = 21; $PXH = 46;
            $hourDec = fn ($t) => (int) substr($t, 0, 2) + ((int) substr($t, 3, 2)) / 60;
            $schedByBranch = $schedules->groupBy('raw_branch_id');
        @endphp
        <div class="dxd-card" data-dxd-panel="jadwal" style="display:{{ $activeTab === 'jadwal' ? '' : 'none' }}; padding:22px 24px;">
            <div class="dxd-sec" style="margin-bottom:4px;">
                <div class="dxd-sec__l"><span class="dxd-sec__tick"></span><span class="dxd-sec__t">Jadwal Mingguan</span></div>
                @unless($doctorBranches->isEmpty())
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="dx-btn" id="dxjCopyBtn"><i class="fa fa-copy"></i> Salin jadwal</button>
                        <button type="button" class="dx-btn" id="dxjAddBtn"><i class="fa fa-plus"></i> Tambah jam</button>
                    </div>
                @endunless
            </div>
            <div class="dxj-hint" style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                <span>Klik kosong untuk menambah · klik blok untuk edit · <strong>seret</strong> blok untuk geser jam / pindah hari · <strong>tepi atas/bawah</strong> untuk ubah jam mulai/selesai.</span>
                <span class="dxj-legend"><span class="dxj-legend__bar"><span></span></span> terisi / kapasitas</span>
            </div>

            @if($doctorBranches->isEmpty())
                <div class="dxj-empty">
                    <div>{{ session('raw_branch_id') ? 'Dokter ini belum punya jadwal di cabang Anda.' : 'Dokter ini belum punya jadwal mingguan.' }}</div>
                    <button type="button" class="dx-btn dx-btn--primary" id="dxjEmptyAdd" style="margin-top:12px;"><i class="fa fa-plus"></i> Tambah Jam Kerja</button>
                </div>
            @else
                <div class="dxj-tabs">
                    @foreach($doctorBranches as $bi => $b)
                        <button type="button" class="dxj-tab {{ $bi === 0 ? 'is-active' : '' }}" data-dxj-branch="{{ $b->id }}">{{ $b->name }}</button>
                    @endforeach
                </div>

                @foreach($doctorBranches as $bi => $b)
                    <div data-dxj-panel="{{ $b->id }}" style="display:{{ $bi === 0 ? '' : 'none' }};">
                        <div class="dxj-grid">
                            <div class="dxj-dayhead">
                                <div class="dxj-dayhead__gutter"></div>
                                @foreach($dayLabels as $dn => $dl)<div class="dxj-dayhead__day">{{ $dl }}</div>@endforeach
                            </div>
                            <div class="dxj-body">
                                <div class="dxj-gutter">
                                    @for($h = $H0; $h <= $H1; $h++)
                                        <div class="dxj-gutter__h" style="top:{{ ($h - $H0) * $PXH }}px;">{{ sprintf('%02d:00', $h) }}</div>
                                    @endfor
                                </div>
                                @foreach($dayLabels as $dn => $dl)
                                    <div class="dxj-col" data-day="{{ $dn }}" data-branch="{{ $b->id }}">
                                        @foreach(($schedByBranch[$b->id] ?? collect())->where('day', $dn) as $s)
                                            @php
                                                $sd = $hourDec($s->start_hour); $ed = $hourDec($s->end_hour);
                                                $top = max(0, ($sd - $H0) * $PXH);
                                                $hgt = max(20, ($ed - $sd) * $PXH);
                                                $cap = (int) round(($ed - $sd) * 2);
                                                $fill = $shiftFill[$s->id] ?? 0;
                                                $pct = $cap > 0 ? min(100, (int) round($fill / $cap * 100)) : 0;
                                            @endphp
                                            <div class="dxj-block" style="top:{{ $top }}px; height:{{ $hgt }}px; background:{{ $spec['color'] }};"
                                                 data-id="{{ $s->id }}" data-day="{{ $s->day }}" data-branch="{{ $s->raw_branch_id }}"
                                                 data-start="{{ substr($s->start_hour, 0, 5) }}" data-end="{{ substr($s->end_hour, 0, 5) }}">
                                                <div class="dxj-block__rz dxj-block__rz--top" title="Seret untuk ubah jam mulai"></div>
                                                <div class="dxj-block__time">{{ substr($s->start_hour, 0, 5) }}–{{ substr($s->end_hour, 0, 5) }}</div>
                                                <div class="dxj-block__br">{{ $b->name }}</div>
                                                @if($hgt >= 46)
                                                    <div class="dxj-block__util">
                                                        <span class="dxj-util-track"><span class="dxj-util-fill" style="width:{{ $pct }}%;"></span></span>
                                                        <span class="dxj-util-count">{{ $fill }}/{{ $cap }}</span>
                                                    </div>
                                                @endif
                                                <div class="dxj-block__rz" title="Seret untuk ubah durasi"></div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- ════════ TAB: CUTI & KHUSUS ════════ --}}
        @php $cMonths = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']; @endphp
        <div data-dxd-panel="cuti" style="display:{{ $activeTab === 'cuti' ? '' : 'none' }};">

            {{-- ── Card Cuti ── --}}
            <div class="dxd-card" style="padding:22px 24px; margin-bottom:16px;">
                <div class="dxc-head"><i class="fa fa-umbrella-beach"></i><span class="dxc-head__t">Cuti Dokter</span></div>
                <div class="dxc-sub">Tanggal dokter tidak praktik. Slot reservasi otomatis tertutup pada rentang ini.</div>

                <form method="post" action="{{ route('rawdoctor.leave.store', $doctor->id) }}">
                    @csrf
                    <div class="dxc-form dxc-form--cuti">
                        <div><label class="dxd-edit__label">Tanggal Mulai <span style="color:#C0392B;">*</span></label><input type="date" name="start_date" id="dxcStart" class="dxd-input" required></div>
                        <div><label class="dxd-edit__label">Tanggal Selesai <span style="color:#C0392B;">*</span></label><input type="date" name="end_date" id="dxcEnd" class="dxd-input" required></div>
                        <div><label class="dxd-edit__label">Alasan</label><input type="text" name="reason" class="dxd-input" placeholder="Mis: Liburan keluarga" maxlength="255"></div>
                        <button type="submit" class="dxc-btn-add"><i class="fa fa-plus"></i> Tambah Cuti</button>
                    </div>
                    <div class="dxc-warn" id="dxcWarn"><i class="fa fa-exclamation-triangle"></i> <span id="dxcWarnText"></span></div>
                </form>
                <script type="application/json" id="dxcResDates">@json($futureReservationDates)</script>

                <div class="dxc-tablewrap">
                    <table class="dxc-table">
                        <thead><tr><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Alasan</th><th>Status</th><th style="width:48px;"></th></tr></thead>
                        <tbody>
                            @forelse($leaves as $leave)
                                @php
                                    $ls = \Carbon\Carbon::parse($leave->start_date);
                                    $le = \Carbon\Carbon::parse($leave->end_date);
                                    $today = \Carbon\Carbon::today();
                                    $durasi = $ls->diffInDays($le) + 1;
                                    if ($today->lt($ls))            $cst = ['Akan Datang', '#3C5FA8', '#E4ECFB'];
                                    elseif ($today->between($ls,$le)) $cst = ['Sedang Cuti', '#B5701A', '#FBEFD9'];
                                    else                            $cst = ['Selesai', '#8A6A52', '#EFE7E1'];
                                @endphp
                                <tr>
                                    <td class="num">{{ $ls->format('d') }} {{ $cMonths[$ls->month-1] }} {{ $ls->format('Y') }}</td>
                                    <td class="num">{{ $le->format('d') }} {{ $cMonths[$le->month-1] }} {{ $le->format('Y') }}</td>
                                    <td class="num">{{ $durasi }} hari</td>
                                    <td>{{ $leave->reason ?: '—' }}</td>
                                    <td><span class="dxc-pill" style="color:{{ $cst[1] }};background:{{ $cst[2] }};">{{ $cst[0] }}</span></td>
                                    <td>
                                        <button type="button" class="dxc-del" onclick="dxcDelLeave('{{ $leave->id }}')" title="Hapus"><i class="fa fa-trash"></i></button>
                                        <form method="post" action="{{ route('rawdoctor.leave.destroy', [$doctor->id, $leave->id]) }}" id="dxcLeave{{ $leave->id }}" style="display:none;">@csrf @method('DELETE')</form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="dxc-table__empty"><i class="fa fa-umbrella-beach"></i> Belum ada data cuti — dokter praktik sesuai jadwal mingguan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ── Card Jadwal Khusus ── --}}
            @if(config('features.flexible_scheduling'))
            <div class="dxd-card" style="padding:22px 24px;">
                <div class="dxc-head"><i class="fa fa-calendar-plus"></i><span class="dxc-head__t">Jadwal Khusus</span></div>
                <div class="dxc-sub">Untuk satu tanggal yang berbeda dari jadwal mingguan — jam berubah, atau buka di hari yang biasanya libur.</div>

                <form method="post" action="{{ route('rawdoctor.exception.store', $doctor->id) }}">
                    @csrf
                    <input type="hidden" name="type" id="dxcType" value="replace">
                    <div class="dxc-sit">
                        <div class="dxc-sit__card is-on" data-sit="replace" id="dxcSitReplace">
                            <div class="dxc-sit__ic dxc-sit__ic--jam"><i class="fa fa-clock"></i></div>
                            <div><div class="dxc-sit__t">Jam Berbeda</div><div class="dxc-sit__d">Tetap kerja, tapi jamnya beda</div></div>
                        </div>
                        <div class="dxc-sit__card" data-sit="add" id="dxcSitAdd">
                            <div class="dxc-sit__ic dxc-sit__ic--tambahan"><i class="fa fa-calendar-plus"></i></div>
                            <div><div class="dxc-sit__t">Hari Tambahan</div><div class="dxc-sit__d">Buka di tanggal yang biasanya libur</div></div>
                        </div>
                    </div>
                    <div class="dxc-form dxc-form--khusus">
                        <div><label class="dxd-edit__label">Tanggal <span style="color:#C0392B;">*</span></label><input type="date" name="date" class="dxd-input" required></div>
                        <div><label class="dxd-edit__label">Cabang <span style="color:#C0392B;">*</span></label>
                            <select name="raw_branch_id" class="dx-select" required>
                                <option value="">- Pilih -</option>
                                @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                            </select>
                        </div>
                        <div><label class="dxd-edit__label">Mulai <span style="color:#C0392B;">*</span></label><input type="time" name="start_hour" class="dxd-input" step="300" required></div>
                        <div><label class="dxd-edit__label">Selesai <span style="color:#C0392B;">*</span></label><input type="time" name="end_hour" class="dxd-input" step="300" required></div>
                        <div><label class="dxd-edit__label">Catatan</label><input type="text" name="reason" class="dxd-input" placeholder="Mis: Dokter ada acara" maxlength="255"></div>
                        <button type="submit" class="dxc-btn-add"><i class="fa fa-save"></i> Simpan</button>
                    </div>
                </form>

                <div class="dxc-tablewrap">
                    <table class="dxc-table">
                        <thead><tr><th>Tanggal</th><th>Situasi</th><th>Cabang</th><th>Jam</th><th>Catatan</th><th style="width:48px;"></th></tr></thead>
                        <tbody>
                            @forelse($exceptions as $ex)
                                @php $exd = \Carbon\Carbon::parse($ex->date); $isReplace = $ex->type === 'replace'; @endphp
                                <tr>
                                    <td>{{ $exd->format('d') }} {{ $cMonths[$exd->month-1] }} {{ $exd->format('Y') }}</td>
                                    <td><span class="dxc-pill" style="{{ $isReplace ? 'color:#B5701A;background:#FBEFD9;' : 'color:#2F8E55;background:#E3F4E8;' }}">{{ $isReplace ? 'Jam Berbeda' : 'Hari Tambahan' }}</span></td>
                                    <td>{{ $ex->branch->name ?? '—' }}</td>
                                    <td class="num">{{ substr($ex->start_hour, 0, 5) }}–{{ substr($ex->end_hour, 0, 5) }}</td>
                                    <td>{{ $ex->reason ?: '—' }}</td>
                                    <td>
                                        <button type="button" class="dxc-del" onclick="dxcDelExc('{{ $ex->id }}')" title="Hapus"><i class="fa fa-trash"></i></button>
                                        <form method="post" action="{{ route('rawdoctor.exception.destroy', [$doctor->id, $ex->id]) }}" id="dxcExc{{ $ex->id }}" style="display:none;">@csrf @method('DELETE')</form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="dxc-table__empty"><i class="fa fa-calendar-plus"></i> Belum ada jadwal khusus untuk dokter ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>

        {{-- ════════ TAB: PERAWATAN & DURASI ════════ --}}
        @php
            $fmtDur = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
            $flex = config('features.flexible_scheduling');
            $hasBranch = $flex && $doctorBranches->isNotEmpty();
        @endphp
        <div class="dxd-card" data-dxd-panel="perawatan" style="display:{{ $activeTab === 'perawatan' ? '' : 'none' }}; padding:22px 24px;">
            <div class="dxc-head"><i class="fa fa-tooth"></i><span class="dxc-head__t">Perawatan &amp; Durasi</span></div>
            <div class="dxc-sub">Aktifkan perawatan yang dilayani dokter ini — berlaku universal di semua cabang. Durasi diatur <strong>per cabang</strong>; kosongkan untuk pakai durasi default perawatan.</div>

            @if($flex && $doctorBranches->isEmpty())
                <div class="dxp-warn"><i class="fa fa-exclamation-triangle"></i><div>Dokter ini belum punya jadwal di cabang mana pun — durasi per cabang belum bisa diatur. Tambahkan jadwal dulu di tab <strong>Jadwal</strong>. Centang perawatan tetap bisa disimpan.</div></div>
            @endif

            <form method="post" action="{{ route('rawdoctor.services.update', $doctor->id) }}" id="dxpForm">
                @csrf

                <div class="dxp-toolbar">
                    @if($hasBranch)
                        <div class="dxp-toolbar__grp">
                            <span class="dxp-toolbar__lbl">Atur durasi untuk cabang</span>
                            <div class="dxj-tabs" style="margin-bottom:0;">
                                @foreach($doctorBranches as $bi => $b)
                                    <button type="button" class="dxj-tab {{ $bi === 0 ? 'is-active' : '' }}" data-dxp-branch="{{ $b->id }}">{{ $b->name }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="dxp-toolbar__grp dxp-search">
                        <span class="dxp-toolbar__lbl">Cari perawatan</span>
                        <div class="dxp-search__box">
                            <i class="fa fa-search"></i>
                            <input type="text" id="dxpSearch" placeholder="Cari nama perawatan…">
                        </div>
                    </div>
                    @if($hasBranch)
                        <button type="button" class="dxp-reset" id="dxpResetBranch" title="Reset durasi cabang yang sedang dibuka"><i class="fa fa-sync-alt"></i> Pakai durasi default</button>
                    @endif
                </div>

                @php
                    $served   = $allServices->filter(fn ($s) => in_array($s->id, $doctorServiceIds));
                    $unserved = $allServices->filter(fn ($s) => !in_array($s->id, $doctorServiceIds));
                @endphp

                <div class="dxp-strip">
                    <span><strong id="dxpServedCount">{{ $served->count() }}</strong> dari {{ $allServices->count() }} perawatan dilayani · <span class="dxp-strip__hint">berlaku semua cabang{!! $hasBranch ? '; durasi diatur per cabang' : '' !!}</span></span>
                    @if($hasBranch)<button type="button" class="dxp-strip__reset" id="dxpReset"><i class="fa fa-pen"></i> Semua pakai durasi default</button>@endif
                </div>

                {{-- ── DILAYANI ── --}}
                <div class="dxp-sechead dxp-sechead--on dxp-sechead--first">
                    <span class="dxp-sechead__t">Dilayani</span>
                    <span class="dxp-sechead__badge" id="dxpBadgeOn">{{ $served->count() }}</span>
                </div>
                <div class="dxp-list" id="dxpServed">
                    @foreach($served as $service)@include('crud.doctor._perawatan_item')@endforeach
                    <div class="dxp-list__empty" id="dxpServedEmpty" style="{{ $served->count() ? 'display:none;' : '' }}">Belum ada perawatan yang dilayani — aktifkan dari daftar di bawah.</div>
                </div>

                {{-- ── BELUM DILAYANI ── --}}
                <div class="dxp-sechead dxp-sechead--off">
                    <span class="dxp-sechead__t">Belum Dilayani</span>
                    <span class="dxp-sechead__badge" id="dxpBadgeOff">{{ $unserved->count() }}</span>
                </div>
                <div class="dxp-list" id="dxpUnserved">
                    @foreach($unserved as $service)@include('crud.doctor._perawatan_item')@endforeach
                    <div class="dxp-list__empty" id="dxpUnservedEmpty" style="{{ $unserved->count() ? 'display:none;' : '' }}">Semua perawatan sudah dilayani dokter ini.</div>
                </div>

                <div class="dxp-empty" id="dxpEmpty" style="display:none;">Tidak ada perawatan yang cocok dengan pencarian.</div>

                <div style="margin-top:16px;">
                    <span class="dxp-autonote"><i class="fa fa-check-circle"></i> Perubahan otomatis tersimpan — centang untuk aktif, ubah durasi langsung tersimpan.</span>
                </div>
                <div class="dxp-toast" id="dxpToast"></div>
            </form>
        </div>

    </div>

    {{-- ════════ SCHEDULE DRAWER (tambah/edit jam kerja) ════════ --}}
    <div class="dxj-overlay" id="dxjOverlay"></div>
    <div class="dxj-drawer" id="dxjDrawer" aria-hidden="true">
        <div class="dxj-drawer__head">
            <span class="dxj-drawer__title" id="dxjTitle">Tambah Jam Kerja</span>
            <button type="button" class="dxj-drawer__close" id="dxjClose">&times;</button>
        </div>
        <div class="dxj-drawer__body">
            <form id="dxjForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="dxjMethod" value="PUT" disabled>
                <div class="dxj-field2">
                    <div>
                        <label class="dxd-edit__label">Hari</label>
                        <select class="dx-select" name="day" id="dxjDay" required>
                            @foreach(['1'=>'Senin','2'=>'Selasa','3'=>'Rabu','4'=>'Kamis','5'=>'Jumat','6'=>'Sabtu','7'=>'Minggu'] as $dv => $dn)
                                <option value="{{ $dv }}">{{ $dn }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="dxd-edit__label">Cabang</label>
                        <select class="dx-select" name="raw_branch_id" id="dxjBranch" required>
                            <option value="">- Pilih -</option>
                            @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="dxd-edit__label">Jam Mulai</label>
                        <input type="time" class="dxd-input" name="start_hour" id="dxjStart" step="300" required>
                    </div>
                    <div>
                        <label class="dxd-edit__label">Jam Selesai</label>
                        <input type="time" class="dxd-input" name="end_hour" id="dxjEnd" step="300" required>
                    </div>
                </div>
                <div class="dxd-err" id="dxjErr" style="display:none; margin-top:12px;"></div>
            </form>
        </div>
        <div class="dxj-drawer__foot">
            <button type="submit" form="dxjForm" class="dx-btn dx-btn--primary" style="flex:1; justify-content:center;"><i class="fa fa-save"></i> Simpan</button>
            <button type="button" class="dx-btn dx-btn--danger" id="dxjDelete" style="display:none;"><i class="fa fa-trash"></i> Hapus</button>
        </div>
    </div>
    <form id="dxjDeleteForm" method="POST" style="display:none;">@csrf @method('DELETE')</form>

    {{-- ════════ SALIN JADWAL DRAWER (antar hari / antar cabang) ════════ --}}
    <div class="dxj-overlay" id="dxcpOverlay"></div>
    <div class="dxj-drawer" id="dxcpDrawer" aria-hidden="true">
        <div class="dxj-drawer__head">
            <span class="dxj-drawer__title">Salin Jadwal</span>
            <button type="button" class="dxj-drawer__close" id="dxcpClose">&times;</button>
        </div>
        <div class="dxj-drawer__body">
            <form id="dxcpForm" method="POST" action="{{ route('rawdoctor.schedule.copy', $doctor->id) }}">
                @csrf
                <input type="hidden" name="mode" id="dxcpMode" value="days">
                <input type="hidden" name="raw_branch_id" id="dxcpBranchId">
                <input type="hidden" name="src_branch" id="dxcpSrcBranch">

                <div class="dxcp-modes">
                    <button type="button" class="dxcp-mode is-on" data-cpmode="days"><i class="fa fa-calendar-week"></i> Antar Hari</button>
                    @unless(session('raw_branch_id'))
                        <button type="button" class="dxcp-mode" data-cpmode="branch"><i class="fa fa-code-branch"></i> Antar Cabang</button>
                    @endunless
                </div>
                <div class="dxcp-ctx">Cabang: <strong id="dxcpBranchName">—</strong></div>

                {{-- mode: antar hari --}}
                <div data-cppanel="days">
                    <label class="dxd-edit__label">Hari Sumber</label>
                    <select class="dx-select" name="src_day" id="dxcpSrcDay">
                        @foreach(['1'=>'Senin','2'=>'Selasa','3'=>'Rabu','4'=>'Kamis','5'=>'Jumat','6'=>'Sabtu','7'=>'Minggu'] as $dv => $dn)
                            <option value="{{ $dv }}">{{ $dn }}</option>
                        @endforeach
                    </select>
                    <label class="dxd-edit__label" style="margin-top:14px;">Salin ke Hari</label>
                    <div class="dxcp-days">
                        @foreach(['1'=>'Sen','2'=>'Sel','3'=>'Rab','4'=>'Kam','5'=>'Jum','6'=>'Sab','7'=>'Min'] as $dv => $dn)
                            <label class="dxcp-day"><input type="checkbox" name="days[]" value="{{ $dv }}"> {{ $dn }}</label>
                        @endforeach
                    </div>
                </div>

                {{-- mode: antar cabang (admin) --}}
                @unless(session('raw_branch_id'))
                    <div data-cppanel="branch" style="display:none;">
                        <label class="dxd-edit__label">Salin Semua Jadwal ke Cabang</label>
                        <select class="dx-select" name="to_branch" id="dxcpToBranch">
                            @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                        </select>
                    </div>
                @endunless

                <div class="dxj-hint" style="margin-top:14px;">Jam kerja yang bentrok dengan jadwal yang sudah ada otomatis dilewati.</div>
            </form>
        </div>
        <div class="dxj-drawer__foot">
            <button type="submit" form="dxcpForm" class="dx-btn dx-btn--primary" style="flex:1; justify-content:center;"><i class="fa fa-copy"></i> Salin</button>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    // ── Tab switcher detail dokter ──
    (function () {
        var tabs = document.querySelectorAll('.dxd-tab');
        var panels = document.querySelectorAll('[data-dxd-panel]');
        function activate(name) {
            tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.dxdTarget === name); });
            panels.forEach(function (p) { p.style.display = p.dataset.dxdPanel === name ? '' : 'none'; });
        }
        tabs.forEach(function (t) { t.addEventListener('click', function () { activate(t.dataset.dxdTarget); }); });
    })();

    // ── Status toggle: konfirmasi dulu (nonaktif → hilang dari dropdown reservasi) ──
    (function () {
        var btn = document.getElementById('dxdStatusBtn');
        var form = document.getElementById('dxdStatusForm');
        if (!btn || !form) return;
        var isActive = btn.classList.contains('dxd-status--on');
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            Swal.fire({
                title: isActive ? 'Nonaktifkan dokter?' : 'Aktifkan dokter?',
                text: isActive
                    ? 'Dokter tidak akan muncul lagi di form reservasi baru. Reservasi yang sudah terjadwal tetap valid.'
                    : 'Dokter akan kembali muncul di dropdown form reservasi baru.',
                icon: isActive ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonColor: '#461500',
                cancelButtonColor: '#aaa',
                confirmButtonText: isActive ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan',
                cancelButtonText: 'Batal',
                reverseButtons: true,
            }).then(function (result) { if (result.isConfirmed) form.submit(); });
        });
    })();

    // ── Edit Profil (non-Sales): toggle view ↔ form ──
    (function () {
        var btn = document.getElementById('dxdEditBtn');
        if (!btn) return;
        var view = document.getElementById('dxdInfoView');
        var form = document.getElementById('dxdInfoEdit');
        var cancel = document.getElementById('dxdEditCancel');
        btn.addEventListener('click', function () { view.style.display = 'none'; form.style.display = ''; });
        if (cancel) cancel.addEventListener('click', function () { form.style.display = 'none'; view.style.display = ''; });
    })();

    function dxdDeletePhoto() {
        Swal.fire({
            title: 'Hapus Foto Dokter?', text: 'Foto akan dihapus permanen.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true,
        }).then(function (result) { if (result.isConfirmed) document.getElementById('dxdDeletePhotoForm').submit(); });
    }

    // ── Tab Jadwal: schedule drawer (tambah/edit/hapus jam) + branch sub-tabs ──
    (function () {
        var drawer = document.getElementById('dxjDrawer');
        if (!drawer) return;
        var storeUrl   = '{{ route('rawdoctor.schedule.store', $doctor->id) }}';
        var updateTpl  = '{{ route('rawdoctor.schedule.update', [$doctor->id, '__ID__']) }}';
        var destroyTpl = '{{ route('rawdoctor.schedule.destroy', [$doctor->id, '__ID__']) }}';
        var overlay = document.getElementById('dxjOverlay');
        var form = document.getElementById('dxjForm'), method = document.getElementById('dxjMethod');
        var title = document.getElementById('dxjTitle'), del = document.getElementById('dxjDelete'), delForm = document.getElementById('dxjDeleteForm');
        var day = document.getElementById('dxjDay'), branch = document.getElementById('dxjBranch');
        var start = document.getElementById('dxjStart'), end = document.getElementById('dxjEnd'), err = document.getElementById('dxjErr');

        function openDrawer() { overlay.classList.add('is-open'); drawer.classList.add('is-open'); }
        function closeDrawer() { overlay.classList.remove('is-open'); drawer.classList.remove('is-open'); }
        function currentBranch() { var t = document.querySelector('.dxj-tab.is-active'); return t ? t.dataset.dxjBranch : ''; }

        function pad2(n) { return (n < 10 ? '0' : '') + n; }
        function openAdd(d, b, startHour) {
            title.textContent = 'Tambah Jam Kerja';
            form.action = storeUrl; method.disabled = true;        // POST
            day.value = d || '1'; branch.value = b || '';
            if (startHour != null) {                               // preset jam dari sel yang diklik
                start.value = pad2(startHour) + ':00';
                end.value = pad2(Math.min(21, startHour + 1)) + ':00';
            } else { start.value = ''; end.value = ''; }
            del.style.display = 'none'; err.style.display = 'none'; openDrawer();
        }
        function openEdit(o) {
            title.textContent = 'Ubah Jam Kerja';
            form.action = updateTpl.replace('__ID__', o.id); method.disabled = false; method.value = 'PUT';
            day.value = o.day; branch.value = o.branch; start.value = o.start; end.value = o.end;
            delForm.action = destroyTpl.replace('__ID__', o.id);
            del.style.display = ''; err.style.display = 'none'; openDrawer();
        }

        var addBtn = document.getElementById('dxjAddBtn');   // "Cabang lain" — tambah jam, cabang dipilih sendiri
        if (addBtn) addBtn.addEventListener('click', function () { openAdd('', ''); });
        var emptyAdd = document.getElementById('dxjEmptyAdd'); // empty-state: dokter belum punya jadwal sama sekali
        if (emptyAdd) emptyAdd.addEventListener('click', function () { openAdd('', ''); });
        // ── Salin Jadwal drawer (antar hari / antar cabang) ──
        var copyBtn = document.getElementById('dxjCopyBtn');
        var cpOverlay = document.getElementById('dxcpOverlay'), cpDrawer = document.getElementById('dxcpDrawer');
        function openCopy() {
            var tab = document.querySelector('[data-dxj-branch].is-active'); if (!tab) return;
            var bid = tab.dataset.dxjBranch, bname = tab.textContent.trim();
            document.getElementById('dxcpBranchId').value = bid;
            document.getElementById('dxcpSrcBranch').value = bid;
            document.getElementById('dxcpBranchName').textContent = bname;
            var toSel = document.getElementById('dxcpToBranch');
            if (toSel) { // sembunyikan cabang sumber dari pilihan tujuan
                var firstOther = '';
                Array.prototype.forEach.call(toSel.options, function (o) {
                    var same = o.value === bid; o.hidden = same; o.disabled = same;
                    if (!same && !firstOther) firstOther = o.value;
                });
                if (firstOther) toSel.value = firstOther;
            }
            cpOverlay.classList.add('is-open'); cpDrawer.classList.add('is-open');
        }
        function closeCopy() { cpOverlay.classList.remove('is-open'); cpDrawer.classList.remove('is-open'); }
        if (copyBtn) copyBtn.addEventListener('click', openCopy);
        document.getElementById('dxcpClose').addEventListener('click', closeCopy);
        cpOverlay.addEventListener('click', closeCopy);
        document.querySelectorAll('.dxcp-mode').forEach(function (m) {
            m.addEventListener('click', function () {
                document.querySelectorAll('.dxcp-mode').forEach(function (x) { x.classList.remove('is-on'); });
                m.classList.add('is-on');
                document.getElementById('dxcpMode').value = m.dataset.cpmode;
                document.querySelectorAll('[data-cppanel]').forEach(function (p) { p.style.display = p.dataset.cppanel === m.dataset.cpmode ? '' : 'none'; });
            });
        });
        document.getElementById('dxjClose').addEventListener('click', closeDrawer);
        overlay.addEventListener('click', closeDrawer);

        document.querySelectorAll('.dxj-col').forEach(function (c) {
            c.addEventListener('click', function (e) {
                if (e.target.closest('.dxj-block')) return;
                // Hitung jam dari posisi klik (grid 08:00, 46px/jam).
                var rect = c.getBoundingClientRect();
                var hour = 8 + Math.floor((e.clientY - rect.top) / 46);
                hour = Math.max(8, Math.min(20, hour));
                openAdd(c.dataset.day, c.dataset.branch, hour);
            });
        });
        // ── Drag: geser jam (vertikal) + pindah hari (horizontal) + resize tepi atas/bawah — snap 15 menit ──
        var PXH = 46, H0 = 8, H1 = 21;
        var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var ajaxTpl = '{{ route('rawdoctor.schedule.ajax', [$doctor->id, '__ID__']) }}';
        var drag = null;
        function snapH(h) { return Math.round(h * 4) / 4; }
        function toH(str) { var p = (str || '0:0').split(':'); return (+p[0]) + (+p[1]) / 60; }
        function hToStr(h) { var hh = Math.floor(h + 1e-6), mm = Math.round((h - hh) * 60); return (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm; }
        function dxToast(msg, kind) {
            if (typeof Swal === 'undefined') return;
            Swal.fire({ toast: true, position: 'bottom-end', timer: 2200, showConfirmButton: false, icon: kind === 'error' ? 'error' : 'success', title: msg });
        }
        function colAtX(body, x) {
            var found = null;
            body.querySelectorAll('.dxj-col').forEach(function (c) { var r = c.getBoundingClientRect(); if (x >= r.left && x < r.right) found = c; });
            return found;
        }
        document.querySelectorAll('.dxj-block').forEach(function (bl) {
            bl.addEventListener('mousedown', function (e) {
                if (e.button !== 0) return;
                e.preventDefault(); e.stopPropagation();
                var mode = e.target.classList.contains('dxj-block__rz--top') ? 'resizeTop'
                         : e.target.classList.contains('dxj-block__rz') ? 'resize' : 'move';
                drag = {
                    el: bl, id: bl.dataset.id, day: bl.dataset.day, mode: mode,
                    startY: e.clientY, os: toH(bl.dataset.start), oe: toH(bl.dataset.end),
                    cs: toH(bl.dataset.start), ce: toH(bl.dataset.end), moved: false,
                    oTop: parseFloat(bl.style.top) || 0, oHgt: parseFloat(bl.style.height) || 0,
                    oCol: bl.closest('.dxj-col'), body: bl.closest('.dxj-body'),
                };
                bl.classList.add('is-dragging');
            });
        });
        window.addEventListener('mousemove', function (e) {
            if (!drag) return;
            var dy = e.clientY - drag.startY;
            if (Math.abs(dy) > 3) drag.moved = true;
            var dh = snapH(dy / PXH);
            if (drag.mode === 'resize') {                     // tepi bawah → ubah jam selesai
                drag.cs = drag.os;
                drag.ce = Math.max(drag.os + 0.25, Math.min(H1, drag.oe + dh));
            } else if (drag.mode === 'resizeTop') {           // tepi atas → ubah jam mulai
                drag.ce = drag.oe;
                drag.cs = Math.min(drag.oe - 0.25, Math.max(H0, drag.os + dh));
            } else {                                          // geser badan: vertikal (jam) + horizontal (hari)
                var dur = drag.oe - drag.os;
                drag.cs = Math.max(H0, Math.min(H1 - dur, drag.os + dh));
                drag.ce = drag.cs + dur;
                if (drag.body) {
                    var tc = colAtX(drag.body, e.clientX);
                    if (tc && tc !== drag.el.parentNode) { tc.appendChild(drag.el); drag.day = tc.dataset.day; drag.moved = true; }
                }
            }
            drag.el.style.top = ((drag.cs - H0) * PXH) + 'px';
            drag.el.style.height = ((drag.ce - drag.cs) * PXH) + 'px';
            var t = drag.el.querySelector('.dxj-block__time'); if (t) t.textContent = hToStr(drag.cs) + '–' + hToStr(drag.ce);
        });
        window.addEventListener('mouseup', function () {
            if (!drag) return;
            var d = drag; drag = null; d.el.classList.remove('is-dragging');
            if (!d.moved) { // tak bergerak = klik biasa → buka edit
                openEdit({ id: d.id, day: d.day, branch: d.el.dataset.branch, start: d.el.dataset.start, end: d.el.dataset.end });
                return;
            }
            function revert() {
                if (d.oCol && d.el.parentNode !== d.oCol) d.oCol.appendChild(d.el);
                d.el.style.top = d.oTop + 'px'; d.el.style.height = d.oHgt + 'px';
                var t = d.el.querySelector('.dxj-block__time'); if (t) t.textContent = d.el.dataset.start + '–' + d.el.dataset.end;
            }
            // cek bentrok dengan blok lain di kolom (hari+cabang) tujuan
            var clash = false, col = d.el.closest('.dxj-col');
            if (col) col.querySelectorAll('.dxj-block').forEach(function (o) {
                if (o === d.el) return;
                if (d.cs < toH(o.dataset.end) && toH(o.dataset.start) < d.ce) clash = true;
            });
            if (clash) { revert(); dxToast('Bentrok dengan jadwal lain — dibatalkan', 'error'); return; }
            var ns = hToStr(d.cs), ne = hToStr(d.ce);
            fetch(ajaxTpl.replace('__ID__', d.id), {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ day: d.day, start_hour: ns, end_hour: ne }),
            }).then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); })
              .then(function () { d.el.dataset.start = ns; d.el.dataset.end = ne; d.el.dataset.day = d.day; dxToast('Jam kerja diperbarui', 'saved'); })
              .catch(function () { revert(); dxToast('Gagal menyimpan — coba lagi', 'error'); });
        });
        form.addEventListener('submit', function (e) {
            if (start.value && end.value && end.value <= start.value) {
                e.preventDefault(); err.textContent = 'Jam selesai harus setelah jam mulai.'; err.style.display = '';
            }
        });
        del.addEventListener('click', function () {
            Swal.fire({
                title: 'Hapus Jadwal?', text: 'Sesi jam kerja ini akan dihapus.',
                icon: 'warning', showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa',
                confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true,
                allowOutsideClick: false,
                // Naikkan di atas drawer (z-index 1081) supaya confirm tidak ketutup & klik tidak ke-serobot.
                didOpen: function () { var c = document.querySelector('.swal2-container'); if (c) c.style.zIndex = '2000'; },
            }).then(function (r) { if (r.isConfirmed) delForm.submit(); });
        });
        // Scope ke [data-dxj-branch] saja — JANGAN '.dxj-tab' (class itu dipakai
        // ulang oleh tab cabang di tab Perawatan, nanti bocor & nyembunyiin grid).
        document.querySelectorAll('[data-dxj-branch]').forEach(function (t) {
            t.addEventListener('click', function () {
                document.querySelectorAll('[data-dxj-branch]').forEach(function (x) { x.classList.remove('is-active'); });
                t.classList.add('is-active');
                document.querySelectorAll('[data-dxj-panel]').forEach(function (p) {
                    p.style.display = p.dataset.dxjPanel === t.dataset.dxjBranch ? '' : 'none';
                });
            });
        });
    })();

    // ── Tab Cuti & Khusus: situation picker + peringatan bentrok reservasi ──
    (function () {
        var rep = document.getElementById('dxcSitReplace'), add = document.getElementById('dxcSitAdd'), type = document.getElementById('dxcType');
        if (rep && add && type) {
            function pick(t) { type.value = t; rep.classList.toggle('is-on', t === 'replace'); add.classList.toggle('is-on', t === 'add'); }
            rep.addEventListener('click', function () { pick('replace'); });
            add.addEventListener('click', function () { pick('add'); });
        }
        var start = document.getElementById('dxcStart'), end = document.getElementById('dxcEnd');
        var warn = document.getElementById('dxcWarn'), warnText = document.getElementById('dxcWarnText');
        var el = document.getElementById('dxcResDates');
        var resDates = el ? JSON.parse(el.textContent || '[]') : [];
        function checkClash() {
            if (!start.value || !end.value || end.value < start.value) { warn.classList.remove('is-show'); return; }
            var n = resDates.filter(function (d) { return d >= start.value && d <= end.value; }).length;
            if (n > 0) {
                warnText.textContent = 'Ada ' + n + ' reservasi pada rentang ini. Pindahkan / reschedule sebelum cuti dimulai.';
                warn.classList.add('is-show');
            } else { warn.classList.remove('is-show'); }
        }
        if (start && end) { start.addEventListener('change', checkClash); end.addEventListener('change', checkClash); }
    })();

    function dxcDelLeave(id) {
        Swal.fire({ title: 'Hapus Cuti?', text: 'Data cuti ini akan dihapus.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true })
            .then(function (r) { if (r.isConfirmed) document.getElementById('dxcLeave' + id).submit(); });
    }
    function dxcDelExc(id) {
        Swal.fire({ title: 'Hapus Jadwal Khusus?', text: 'Data jadwal khusus ini akan dihapus.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true })
            .then(function (r) { if (r.isConfirmed) document.getElementById('dxcExc' + id).submit(); });
    }

    // ── Tab Perawatan & Durasi (2 seksi: Dilayani / Belum Dilayani) ──
    (function () {
        var form = document.getElementById('dxpForm');
        if (!form) return;
        var served = document.getElementById('dxpServed'), unserved = document.getElementById('dxpUnserved');

        function activeBranch() { var a = form.querySelector('[data-dxp-branch].is-active'); return a ? a.dataset.dxpBranch : null; }
        function setText(id, v) { var e = document.getElementById(id); if (e) e.textContent = v; }
        function updCounts() {
            var onN = served.querySelectorAll('.dxp-item').length, offN = unserved.querySelectorAll('.dxp-item').length;
            setText('dxpBadgeOn', onN); setText('dxpBadgeOff', offN); setText('dxpServedCount', onN);
            document.getElementById('dxpServedEmpty').style.display = onN ? 'none' : '';
            document.getElementById('dxpUnservedEmpty').style.display = offN ? 'none' : '';
        }
        // sublabel: "Pakai durasi default" vs "Durasi khusus" (per cabang aktif)
        function updSublabel(item) {
            var lbl = item.querySelector('.dxp-name__d'); if (!lbl) return;
            var bid = activeBranch();
            var inp = item.querySelector('.dxp-svc-input[data-branch="' + bid + '"]');
            lbl.textContent = (inp && inp.value.trim()) ? 'Durasi khusus untuk cabang ini' : 'Pakai durasi default';
        }

        function setActive(item, on) {
            item.classList.toggle('is-on', on);
            item.querySelectorAll('.dxp-dur-input').forEach(function (inp) { inp.disabled = !on; if (!on) inp.value = ''; });
            if (!on) item.querySelector('.dxp-sub') && item.querySelector('.dxp-sub').classList.remove('is-open');
            var target = on ? served : unserved;
            target.insertBefore(item, target.querySelector('.dxp-list__empty'));
            updSublabel(item); updCounts();
        }

        form.querySelectorAll('.dxp-cb').forEach(function (cb) {
            cb.addEventListener('change', function () { setActive(cb.closest('.dxp-item'), cb.checked); });
        });
        // kotak centang + tombol "Aktifkan" → toggle checkbox
        // (bubbles:true WAJIB — biar listener autosave di <form> kebaca; event manual default tak bubble)
        form.querySelectorAll('[data-svc-toggle]').forEach(function (t) {
            t.addEventListener('click', function (e) {
                e.preventDefault();
                var cb = document.getElementById('svc_' + t.dataset.svcToggle);
                cb.checked = !cb.checked; cb.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
        // durasi service diketik → update sublabel
        form.querySelectorAll('.dxp-svc-input').forEach(function (inp) {
            inp.addEventListener('input', function () { updSublabel(inp.closest('.dxp-item')); });
        });

        // sub-tab cabang → tampilkan input durasi cabang terpilih + update label + sublabel
        form.querySelectorAll('[data-dxp-branch]').forEach(function (t) {
            t.addEventListener('click', function () {
                form.querySelectorAll('[data-dxp-branch]').forEach(function (x) { x.classList.remove('is-active'); });
                t.classList.add('is-active');
                form.querySelectorAll('.dxp-dur-input').forEach(function (inp) { inp.style.display = inp.dataset.branch === t.dataset.dxpBranch ? '' : 'none'; });
                served.querySelectorAll('.dxp-item').forEach(updSublabel);
            });
        });

        // expand sub-detail
        form.querySelectorAll('[data-sub-toggle]').forEach(function (tg) {
            tg.addEventListener('click', function () { tg.closest('.dxp-sub').classList.toggle('is-open'); });
        });

        // search filter (lintas dua seksi)
        var search = document.getElementById('dxpSearch'), empty = document.getElementById('dxpEmpty');
        if (search) search.addEventListener('input', function () {
            var q = search.value.toLowerCase().trim(), any = false;
            form.querySelectorAll('.dxp-item').forEach(function (it) {
                var show = !q || it.dataset.name.indexOf(q) >= 0;
                it.style.display = show ? '' : 'none'; if (show) any = true;
            });
            if (empty) empty.style.display = any ? 'none' : '';
        });

        function refreshSublabels() { served.querySelectorAll('.dxp-item').forEach(updSublabel); }
        // Strip "Semua pakai durasi default" → reset durasi SEMUA cabang
        var resetAll = document.getElementById('dxpReset');
        if (resetAll) resetAll.addEventListener('click', function () {
            form.querySelectorAll('.dxp-dur-input').forEach(function (inp) { if (!inp.disabled) inp.value = ''; });
            refreshSublabels(); scheduleSave();
        });
        // Toolbar "Pakai durasi default" → reset durasi CABANG AKTIF saja
        var resetBranch = document.getElementById('dxpResetBranch');
        if (resetBranch) resetBranch.addEventListener('click', function () {
            var bid = activeBranch();
            form.querySelectorAll('.dxp-dur-input[data-branch="' + bid + '"]').forEach(function (inp) { if (!inp.disabled) inp.value = ''; });
            refreshSublabels(); scheduleSave();
        });

        // ── Auto-save (AJAX) — toggle langsung, durasi pas blur (event 'change') ──
        var toast = document.getElementById('dxpToast');
        var toastTimer, saveTimer;
        function showToast(kind, html) { if (!toast) return; toast.className = 'dxp-toast is-show dxp-toast--' + kind; toast.innerHTML = html; }
        function hideToast() { if (toast) toast.className = 'dxp-toast'; }
        function autosave() {
            showToast('saving', '<i class="fa fa-spinner fa-spin"></i> Menyimpan…');
            fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json().catch(function () { return {}; }); })
                .then(function () { showToast('saved', '<i class="fa fa-check"></i> Tersimpan'); clearTimeout(toastTimer); toastTimer = setTimeout(hideToast, 1800); })
                .catch(function () { showToast('error', '<i class="fa fa-exclamation-triangle"></i> Gagal menyimpan — klik untuk coba lagi'); });
        }
        function scheduleSave() { clearTimeout(saveTimer); saveTimer = setTimeout(autosave, 400); }
        if (toast) toast.addEventListener('click', function () { if (toast.classList.contains('dxp-toast--error')) autosave(); });
        // 'change' = toggle checkbox + commit durasi (blur). Search pakai 'input' → tak ikut.
        form.addEventListener('change', scheduleSave);

        // init sublabel
        served.querySelectorAll('.dxp-item').forEach(updSublabel);
    })();
</script>
@endsection
