@extends(request()->boolean('drawer') ? 'layouts.bare' : 'layouts.app')

@section('css')
    <style>
        /* Quick-add pasien (1 nomor → banyak pasien) */
        .qp-trigger-wrap { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:6px; }
        .qp-trigger-wrap small { color:#8b7a6b; }
        .qp-trigger {
            display:inline-flex; align-items:center; gap:7px;
            background:#fff; color:#461500; border:1.5px dashed rgba(70,21,0,.35);
            border-radius:9px; padding:7px 14px; font-size:.82rem; font-weight:700;
            cursor:pointer; transition:.15s;
        }
        .qp-trigger:hover { background:#fff4e8; border-color:#461500; }
        .qp-card {
            margin-top:12px; border:1px solid #e6d5c4; border-radius:12px;
            background:#fffaf5; overflow:hidden;
            box-shadow:0 6px 18px rgba(70,21,0,.06);
        }
        .qp-card__head {
            display:flex; align-items:center; justify-content:space-between;
            padding:11px 16px; background:linear-gradient(135deg,#461500,#6b2400);
            color:#fff; font-weight:700; font-size:.9rem;
        }
        .qp-card__head i { margin-right:7px; }
        .qp-close {
            background:transparent; border:none; color:#fff; font-size:1.25rem;
            line-height:1; cursor:pointer; opacity:.85; padding:0 4px;
        }
        .qp-close:hover { opacity:1; }
        .qp-card__body { padding:16px; }
        .qp-card__body label { font-size:.78rem; font-weight:700; color:#5b4636; margin-bottom:4px; }
        .qp-card__body .form-control { border-radius:9px; border-color:#e0d5c8; }
        .qp-card__body .form-control:focus { border-color:#461500; box-shadow:0 0 0 .15rem rgba(70,21,0,.12); }
        .qp-req { color:#dc2626; }
        .qp-opt { color:#8b7a6b; font-weight:400; font-size:.72rem; }
        .qp-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:6px; }
        .qp-status { min-height:20px; margin:4px 0 2px; font-size:.82rem; }
        .qp-msg { display:inline-flex; align-items:center; gap:6px; font-weight:600; }
        .qp-ok  { color:#16a34a; }
        .qp-err { color:#dc2626; }
        .st-btn-ghost {
            background:#f0e8e0; color:#5b4636; border:none; border-radius:9px;
            padding:9px 18px; font-weight:700; font-size:.85rem; cursor:pointer;
        }
        .st-btn-ghost:hover { background:#e6dacc; }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        {{-- Chrome halaman (judul + notif + Kembali) — disembunyikan saat dimuat di drawer (?drawer=1). --}}
        @unless(request()->boolean('drawer'))
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Tambah Reservasi</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="mb-3">
            <a href="{{ route('rawreservation') }}" class="st-btn-back"><i class="fa fa-arrow-left"></i> Kembali</a>
        </div>
        @endunless

        <form method="post" action="{{ route('rawreservation.store') }}" id="rrReservationForm">
            @csrf
            <div class="row">
                <div class="col-lg-8">
                    <div class="st-card">
                        <div class="st-card__head">
                            <div class="st-card__title-wrap">
                                <span class="st-card__icon"><i class="fa fa-calendar-alt"></i></span>
                                <h4 class="st-card__title">Informasi Reservasi</h4>
                            </div>
                        </div>
                        <div class="st-card__body">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Pasien</label>
                                    <select class="form-control {{ $errors->has('raw_customer_id') ? 'is-invalid' : '' }}" name="raw_customer_id" id="customerSelect">
                                        <option value="">- Cari Pasien -</option>
                                        @foreach ($customers as $customer)
                                            @if(old('raw_customer_id') == $customer->id)
                                                <option value="{{ $customer->id }}" selected>
                                                    {{ $customer->name }} ({{ $customer->phone ?: '-' }})
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                    @if ($errors->has('raw_customer_id'))
                                        <div class="invalid-feedback">{{ $errors->first('raw_customer_id') }}</div>
                                    @endif
                                    <div class="qp-trigger-wrap">
                                        <small class="text-muted">1 nomor bisa punya banyak pasien (mis. anak / pasangan).</small>
                                        <button type="button" class="qp-trigger" id="toggleQuickPatient">
                                            <i class="fa fa-user-plus"></i> Tambah pasien baru di nomor ini
                                        </button>
                                    </div>

                                    <div id="quickPatientBox" class="qp-card" style="display:none;">
                                        <div class="qp-card__head">
                                            <span><i class="fa fa-user-plus"></i> Pasien Baru di Nomor Ini</span>
                                            <button type="button" class="qp-close" id="qpCloseBtn" aria-label="Tutup">&times;</button>
                                        </div>
                                        <div class="qp-card__body">
                                            <div class="row">
                                                <div class="col-md-12 form-group">
                                                    <label>Nama Pasien <span class="qp-req">*</span></label>
                                                    <input type="text" id="qpName" class="form-control" placeholder="Nama lengkap pasien">
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Jenis Kelamin</label>
                                                    <select id="qpJk" class="form-control">
                                                        <option value="">- Pilih -</option>
                                                        <option value="L">Laki-laki</option>
                                                        <option value="P">Perempuan</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Tanggal Lahir</label>
                                                    <input type="text" id="qpTgl" class="form-control" placeholder="Pilih tanggal" readonly>
                                                </div>
                                                <div class="col-md-4 form-group">
                                                    <label>Pasien ini adalah</label>
                                                    <select id="qpRelasi" class="form-control">
                                                        <option value="diri_sendiri">Diri sendiri</option>
                                                        <option value="pasangan">Pasangan</option>
                                                        <option value="anak" selected>Anak</option>
                                                        <option value="orang_tua">Orang tua</option>
                                                        <option value="lainnya">Lainnya</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-12 form-group">
                                                    <label>Email <span class="qp-opt">(opsional)</span></label>
                                                    <input type="email" id="qpEmail" class="form-control" placeholder="email@contoh.com">
                                                </div>
                                            </div>
                                            <div id="qpStatus" class="qp-status"></div>
                                            <div class="qp-actions">
                                                <button type="button" id="qpCancelBtn" class="st-btn-ghost">Batal</button>
                                                <button type="button" id="qpSaveBtn" class="st-btn-brand">
                                                    <i class="fa fa-check"></i> Simpan &amp; Pilih Pasien
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Dokter</label>
                                    <select id="doctorSelect2" class="select2 form-control {{ $errors->has('raw_doctor_id') ? 'is-invalid' : '' }}" name="raw_doctor_id">
                                        <option value="">- Pilih Doctor -</option>
                                        @foreach ($doctors as $doctor)
                                            <option value="{{ $doctor->id }}" {{ old('raw_doctor_id') == $doctor->id ? 'selected' : '' }}>
                                                {{ $doctor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('raw_doctor_id'))
                                        <div class="invalid-feedback">{{ $errors->first('raw_doctor_id') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Cabang</label>
                                <select id="branchSelect2" class="select2 form-control {{ $errors->has('raw_branch_id') ? 'is-invalid' : '' }}" name="raw_branch_id">
                                    <option value="">- Pilih Cabang -</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ old('raw_branch_id') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($errors->has('raw_branch_id'))
                                    <div class="invalid-feedback">{{ $errors->first('raw_branch_id') }}</div>
                                @endif
                            </div>
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Tanggal</label>
                                    <input class="form-control {{ $errors->has('date') ? 'is-invalid' : '' }}" type="text" id="datePickerReschedule" name="date" value="{{ old('date') }}" placeholder="Pilih tanggal..." readonly />
                                    <small id="datePickerInfo" class="text-muted">Pilih dokter dan cabang terlebih dahulu.</small>
                                    @if ($errors->has('date'))
                                        <div class="invalid-feedback">{{ $errors->first('date') }}</div>
                                    @endif
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Jam</label>
                                    <div id="slotPickerWrap">
                                        <button type="button" class="st-btn-outline" id="loadSlotsBtn" onclick="loadSlots()" style="margin-bottom:8px;">
                                            <i class="fa fa-clock"></i> Cek Slot Tersedia
                                        </button>
                                        <div class="st-slot-info" id="selectedTimeDisplay" style="{{ old('start_hour') ? '' : 'display:none;' }}">
                                            <i class="fa fa-bookmark"></i>
                                            <span class="st-slot-info-label">Jadwal dipilih</span>
                                            <span class="st-slot-info-value" id="currentTime">{{ old('start_hour') ? substr(old('start_hour'),0,5).' – '.substr(old('end_hour'),0,5) : '--:-- – --:--' }}</span>
                                        </div>
                                        <input type="hidden" name="start_hour" id="startHourInput" value="{{ old('start_hour') }}" />
                                        <input type="hidden" name="end_hour" id="endHourInput" value="{{ old('end_hour') }}" />
                                        @if ($errors->has('start_hour'))
                                            <div class="invalid-feedback" style="display:block">{{ $errors->first('start_hour') }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div id="slotsContainer"></div>
                        </div>
                    </div>

                    <div class="st-card">
                        <div class="st-card__head">
                            <div class="st-card__title-wrap">
                                <span class="st-card__icon"><i class="fa fa-tooth"></i></span>
                                <h4 class="st-card__title">Layanan Perawatan</h4>
                            </div>
                        </div>
                        <div class="st-card__body">
                            <div id="serviceChecklist">
                                <span class="text-muted">Pilih dokter terlebih dahulu untuk melihat layanan yang tersedia</span>
                            </div>
                            <script>
                                // Kontrak config form reservasi (mode TAMBAH) — semua nilai Blade ngumpul di sini.
                                // Dibaca initReservationForm() (file statik nanti), bukan interpolasi inline.
                                window.rrFormConfig = {
                                    mode:               'add',
                                    reservationId:      null,
                                    excludeId:          null,
                                    totalDuration:      30,
                                    originalBranchId:   null,
                                    selectedStart:      '{{ old('start_hour') ? substr(old('start_hour'),0,5) : '' }}',
                                    initialDoctorId:    '{{ old('raw_doctor_id', '') }}',
                                    existingServices:   {},
                                    waitlistPrompt:     null,
                                    customerSearchUrl:  '{{ route("api.customer.search") }}',
                                    quickPatientUrl:    '{{ route("rawreservation.quickpatient") }}',
                                };
                                // Alias bridge — JS lama masih baca dua nilai ini; di-set dari config.
                                window.initialDoctorId  = window.rrFormConfig.initialDoctorId;
                                window.existingServices = window.rrFormConfig.existingServices;
                            </script>
                            <div id="totalDurationInfo" class="st-total-duration" style="display:none;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="st-card">
                        <div class="st-card__head">
                            <div class="st-card__title-wrap">
                                <span class="st-card__icon"><i class="fa fa-sliders-h"></i></span>
                                <h4 class="st-card__title">Opsi Tambahan</h4>
                            </div>
                        </div>
                        <div class="st-card__body">
                            <div class="form-group">
                                <label>Keterangan Tambahan</label>
                                <textarea class="form-control {{ $errors->has('keterangan') ? 'is-invalid' : '' }}" name="keterangan" rows="4" placeholder="Tambahkan keterangan tambahan jika ada...">{{ old('keterangan') }}</textarea>
                                @if ($errors->has('keterangan'))
                                    <div class="invalid-feedback" style="display:block">{{ $errors->first('keterangan') }}</div>
                                @endif
                            </div>
                            <div class="form-group">
                                {{-- Banner waiting list: muncul otomatis saat jadwal di tanggal ini penuh. --}}
                                <div id="waitingBanner" style="display:none; background:#fef9e7; border:1px solid #f5d97b; color:#7a5b00; border-radius:8px; padding:10px 14px; margin-bottom:10px; font-size:13px;">
                                    <i class="fa fa-hourglass-half"></i>
                                    Jadwal di tanggal ini sudah penuh. Pasien dapat masuk <strong>waiting list</strong> — slot akan dipilih nanti saat ada yang cancel / reschedule.
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="is_waiting" name="is_waiting" value="1" {{ old('is_waiting') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_waiting">Masukkan ke waiting list</label>
                                </div>
                            </div>
                            <div class="st-btn-row">
                                <button class="st-btn-brand" type="submit" id="submitReservationBtn"><i class="fa fa-save"></i> <span id="submitReservationLabel">Simpan Reservasi</span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('js')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    {{-- Style quick-add pasien dipindah ke @section('css') agar ikut ter-render di bare layout (drawer). --}}
    <script src="{{ asset('assets/js/reservation-form.js') }}?v={{ @filemtime(public_path('assets/js/reservation-form.js')) ?: '1' }}"></script>
    <script>
        // Standalone tambah: panggil sekali setelah DOM & plugin siap.
        // (Drawer Fase 1 nanti memanggil window.initReservationForm() lagi setelah inject.)
        $(function () { window.initReservationForm(); });
    </script>
@endsection
