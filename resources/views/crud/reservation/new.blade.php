@extends('layouts.app')

@section('content')
    <div class="container-fluid">
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

        <form method="post" action="{{ route('rawreservation.store') }}">
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
                                                <option value="{{ $customer->id }}" data-phone="{{ $customer->phone ?? '' }}" selected>
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
                                            <span id="qpCardTitle"><i class="fa fa-user-plus"></i> Pasien Baru</span>
                                            <button type="button" class="qp-close" id="qpCloseBtn" aria-label="Tutup">&times;</button>
                                        </div>
                                        <div class="qp-card__body">
                                            <div class="row">
                                                <div class="col-md-6 form-group">
                                                    <label>Nama Pasien <span class="qp-req">*</span></label>
                                                    <input type="text" id="qpName" class="form-control" placeholder="Nama lengkap pasien">
                                                </div>
                                                <div class="col-md-6 form-group">
                                                    <label>No. HP <span class="qp-req">*</span></label>
                                                    <input type="text" id="qpPhone" class="form-control" placeholder="08xxxxxxxxxx">
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
                                window.initialDoctorId = '{{ old('raw_doctor_id', '') }}';
                                window.existingServices = {};
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
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="is_waiting" name="is_waiting" value="1" {{ old('is_waiting') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_waiting">Masukkan ke waiting list</label>
                                </div>
                            </div>
                            <div class="st-btn-row">
                                <button class="st-btn-brand" type="submit"><i class="fa fa-save"></i> Simpan Reservasi</button>
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
        #qpTgl[readonly] { cursor: pointer; background: #fffaf5; }
    </style>
    <script>
        // ===== LAYANAN CHECKLIST =====
        function updateTotalDuration() {
            let total = 0;
            let names = [];

            $('.svc-checkbox:checked').each(function() {
                const svcId = $(this).data('svc-id');
                const detailCheckboxes = $('#detailWrap_' + svcId + ' .svc-detail-checkbox:checked');
                const defDur = parseFloat($(this).data('default-dur')) || 0;

                let subServiceDurations = 0;
                if (detailCheckboxes.length > 0) {
                    detailCheckboxes.each(function() {
                        const detailId = $(this).val();
                        const qtyStr = $('input[name="services[' + svcId + '][qty][' + detailId + ']"]').val();
                        const qty = parseInt(qtyStr) || 1;
                        subServiceDurations += (parseFloat($(this).data('dur')) || 0) * qty;
                    });
                }

                total += (subServiceDurations > 0) ? subServiceDurations : defDur;
                names.push($(this).data('svc-name'));
            });
            if (total > 0) {
                $('#totalDurationInfo').html(
                    '<i class="fa fa-clock"></i> '
                    + '<strong>Total durasi: ' + total + ' menit</strong>'
                    + '<span style="color:var(--text-subtle);font-weight:500;margin-left:6px;">(' + names.join(', ') + ')</span>'
                ).show();
            } else {
                $('#totalDurationInfo').html('Belum ada layanan dipilih').hide();
            }

            totalDuration = total || 30;
            if (allSlots.length > 0) reRenderSlots();

            // refresh end_hour kalau sudah ada start yang dipilih
            if (selectedStart) {
                const end = addMinutes(selectedStart, totalDuration);
                $('#endHourInput').val(end);
                $('#currentTime').text(selectedStart + ' – ' + end);
            }
        }

        function renderServiceChecklist(services) {
            if (!services || services.length === 0) {
                $('#serviceChecklist').html(
                    '<div class="text-warning"><i class="fa fa-exclamation-triangle"></i> '
                    + 'Dokter ini belum punya layanan yang bisa dilakukan. '
                    + 'Tambahkan di halaman <a href="/rawdoctor" target="_blank">Data Doctor</a>.</div>'
                );
                updateTotalDuration();
                return;
            }

            let html = '<div class="st-service-list">';
            services.forEach(function(svc) {
                const existing       = (window.existingServices || {})[svc.id] || null;
                const isChecked      = existing !== null;
                const existDetailIds = existing ? (existing.detail_ids || []) : [];

                html += '<div class="st-service-row ' + (isChecked ? 'is-checked' : '') + '" id="svcRow_' + svc.id + '">';
                html += '<div class="st-service-row__top">';
                html += '<div class="custom-control custom-checkbox">'
                      + '<input type="checkbox" class="custom-control-input svc-checkbox" '
                      + 'id="svc_' + svc.id + '" '
                      + 'name="services[' + svc.id + '][checked]" '
                      + 'value="1" '
                      + 'data-svc-id="' + svc.id + '" '
                      + 'data-svc-name="' + svc.name + '" '
                      + 'data-default-dur="' + svc.duration_minutes + '" '
                      + (isChecked ? 'checked' : '') + '>'
                      + '<label class="custom-control-label" for="svc_' + svc.id + '">'
                      + '<strong>' + svc.name + '</strong>'
                      + '</label></div>';
                html += '<span class="badge badge-light text-muted svc-dur-badge" id="svcDurBadge_' + svc.id + '">'
                      + '<i class="fa fa-clock"></i> ' + svc.duration_minutes + ' menit</span>';
                html += '<span class="badge badge-light text-muted" id="svcPriceBadge_' + svc.id + '">'
                      + '<i class="fa fa-tag"></i> Rp ' + Number(svc.price).toLocaleString('id-ID') + '</span>';
                html += '</div>';

                html += '<div class="svc-detail-wrap" id="detailWrap_' + svc.id + '" '
                      + 'style="' + (isChecked ? '' : 'display:none') + '">';
                html += '<div>';

                if (svc.details && svc.details.length > 0) {
                    const hasCheckedDetail = svc.details.some(function(d) { return (existDetailIds || []).includes(d.id); });
                    const collapseClass = hasCheckedDetail ? 'show' : '';

                    html += '<div class="d-flex align-items-center justify-content-between mb-1">';
                    html += '  <label class="small text-muted mb-0">Sub-detail Perawatan <span class="text-muted">(opsional)</span></label>';
                    html += '  <a href="#collapseSubSvc_' + svc.id + '" data-toggle="collapse" class="small text-primary" style="text-decoration:none;"><i class="fa fa-list"></i> Tampilkan/Sembunyikan</a>';
                    html += '</div>';

                    html += '<div class="collapse ' + collapseClass + '" id="collapseSubSvc_' + svc.id + '">';
                    html += '<div class="mb-1 mt-1">';
                    html += '<input type="text" class="sub-detail-search" placeholder="Cari sub-detail..." data-target="#listSubSvc_' + svc.id + '">';
                    html += '</div>';
                    html += '<div class="sub-service-checklist mt-1" id="listSubSvc_' + svc.id + '">';

                    svc.details.forEach(function(d) {
                        const existingDetail = existing && existing.details ? existing.details.find(function(e) { return e.id == d.id; }) : null;
                        const isDetailChecked = !!existingDetail || (existDetailIds || []).includes(d.id);
                        const qty = existingDetail ? (existingDetail.quantity || 1) : 1;
                        const safeName = d.name.replace(/"/g, '&quot;').toLowerCase();

                        html += '<div class="d-flex align-items-center justify-content-between mb-1 sub-detail-item" data-name="' + safeName + '">';
                        html += '<div class="custom-control custom-checkbox">';
                        html += '<input type="checkbox" class="custom-control-input svc-detail-checkbox" '
                              + 'id="svc_detail_' + d.id + '" '
                              + 'name="services[' + svc.id + '][detail_id][]" '
                              + 'value="' + d.id + '" '
                              + 'data-svc-id="' + svc.id + '" '
                              + 'data-dur="' + d.duration_minutes + '" '
                              + (isDetailChecked ? 'checked' : '') + '>';
                        html += '<label class="custom-control-label" style="cursor:pointer;" for="svc_detail_' + d.id + '">'
                              + d.name + ' <small class="text-muted">(' + d.duration_minutes + ' mnt — Rp ' + Number(d.price).toLocaleString('id-ID') + ')</small>'
                              + '</label></div>';

                        html += '<div class="d-flex align-items-center" style="gap:6px; ' + (isDetailChecked ? '' : 'display:none;') + '" id="qty_wrap_' + d.id + '">';
                        html += '<small class="text-muted" style="white-space:nowrap;">Qty</small>';
                        html += '<input type="number" name="services[' + svc.id + '][qty][' + d.id + ']" value="' + qty + '" class="form-control form-control-sm qty-input" min="1" placeholder="Qty" style="width:80px;">';
                        html += '</div></div>';
                    });
                    html += '</div></div>';
                } else {
                    html += '<label class="small text-muted mb-1">Sub-detail Perawatan <span class="text-muted">(opsional)</span></label>';
                    html += '<div class="form-control form-control-sm bg-light text-muted" style="cursor:not-allowed">'
                          + 'Belum ada sub-detail — <a href="/rawdocservice/' + svc.id + '" target="_blank">Tambah di sini</a>'
                          + '</div>';
                }

                html += '</div></div>';
                html += '</div>';
            });
            html += '</div>';

            $('#serviceChecklist').html(html);
            updateTotalDuration();
        }

        function loadServicesByDoctor() {
            const doctorId = $('#doctorSelect2').val()
                          || $('[name="raw_doctor_id"]').val()
                          || window.initialDoctorId
                          || '';

            if (!doctorId) {
                $('#serviceChecklist').html(
                    '<span class="text-muted">Pilih dokter terlebih dahulu untuk melihat layanan yang tersedia</span>'
                );
                return;
            }

            $('#serviceChecklist').html(
                '<span class="text-muted"><i class="fa fa-spinner fa-spin"></i> Memuat layanan dokter...</span>'
            );

            fetch('/api/reservation/services-by-doctor/' + doctorId)
                .then(function(r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function(services) { renderServiceChecklist(services); })
                .catch(function(err) {
                    $('#serviceChecklist').html(
                        '<span class="text-danger"><i class="fa fa-exclamation-circle"></i> '
                        + 'Fetch error: ' + err.message + '</span>'
                    );
                });
        }

        $(document).on('change', '.svc-checkbox', function() {
            const svcId   = $(this).data('svc-id');
            const checked = $(this).is(':checked');
            const row     = $('#svcRow_' + svcId);
            const wrap    = $('#detailWrap_' + svcId);

            if (checked) {
                wrap.show();
                row.addClass('is-checked');
            } else {
                wrap.hide();
                wrap.find('input[type="checkbox"]').prop('checked', false);
                row.removeClass('is-checked');
            }
            updateTotalDuration();
        });

        $(document).on('change', '.svc-detail-checkbox', function() {
            const detailId = $(this).val();
            const qtyWrap = $('#qty_wrap_' + detailId);
            if ($(this).is(':checked')) qtyWrap.show();
            else qtyWrap.hide();
            updateTotalDuration();
        });

        $(document).on('input change', '.qty-input', function() { updateTotalDuration(); });

        $(document).on('input', '.sub-detail-search', function() {
            const keyword = $(this).val().toLowerCase();
            const targetId = $(this).data('target');
            $(targetId).find('.sub-detail-item').each(function() {
                const name = String($(this).data('name') || '');
                if (name.includes(keyword)) $(this).removeClass('d-none').addClass('d-flex');
                else $(this).removeClass('d-flex').addClass('d-none');
            });
        });
        // ===== END LAYANAN CHECKLIST =====

        // ===== SLOT PICKER =====
        let totalDuration = 30;
        let allSlots      = [];
        let selectedStart = '{{ old('start_hour') ? substr(old('start_hour'),0,5) : '' }}';
        let datePicker    = null;

        function toMinutes(t) {
            const [h, m] = (t || '00:00').split(':').map(Number);
            return h * 60 + m;
        }
        function addMinutes(t, mins) {
            const total = toMinutes(t) + mins;
            return String(Math.floor(total / 60)).padStart(2,'0') + ':' + String(total % 60).padStart(2,'0');
        }

        function slotWouldConflict(slotStart, bookedRanges) {
            const sStart = toMinutes(slotStart);
            const sEnd   = sStart + totalDuration;
            return bookedRanges.some(function(r) {
                return sStart < toMinutes(r.end) && sEnd > toMinutes(r.start);
            });
        }

        function initDatePicker(enabledDates) {
            if (datePicker) datePicker.destroy();

            const config = {
                dateFormat: 'Y-m-d',
                disableMobile: true,
                locale: { firstDayOfWeek: 1 },
                onChange: function() { loadSlots(); }
            };

            if (enabledDates && enabledDates.length > 0) {
                config.enable = enabledDates;
                $('#datePickerInfo').html(
                    '<i class="fa fa-calendar-check text-success"></i> '
                    + enabledDates.length + ' tanggal tersedia dalam 90 hari ke depan'
                );
            } else {
                config.enable = [];
                $('#datePickerInfo').html(
                    '<i class="fa fa-info-circle text-muted"></i> Pilih dokter dan cabang terlebih dahulu'
                );
            }

            datePicker = flatpickr('#datePickerReschedule', config);
        }

        function loadDoctorDates() {
            const doctorId = $('#doctorSelect2').val() || $('[name="raw_doctor_id"]').val();
            const branchId = $('#branchSelect2').val() || $('[name="raw_branch_id"]').val();

            if (!doctorId || !branchId) {
                initDatePicker([]);
                $('#slotsContainer').html('');
                return;
            }

            $('#datePickerInfo').html('<i class="fa fa-spinner fa-spin"></i> Memuat jadwal dokter...');

            fetch('/api/reservation/dates/' + branchId + '/' + doctorId)
                .then(function(r) { return r.json(); })
                .then(function(dates) {
                    const dateStrings = dates.map(function(d) { return d.date; });
                    initDatePicker(dateStrings);
                    const currentDate = $('[name="date"]').val();
                    if (currentDate && !dateStrings.includes(currentDate)) {
                        if (datePicker) datePicker.clear();
                        $('#slotsContainer').html(
                            '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> '
                            + 'Tanggal sebelumnya tidak sesuai jadwal dokter. Pilih tanggal baru.</span>'
                        );
                    } else if (currentDate) {
                        loadSlots();
                    }
                })
                .catch(function() { initDatePicker([]); });
        }

        function renderSlots(data) {
            allSlots = data.slots || [];
            if (allSlots.length === 0) {
                $('#slotsContainer').html('<span class="text-muted">Tidak ada slot tersedia untuk jadwal ini</span>');
                return;
            }
            reRenderSlots();
        }

        function reRenderSlots() {
            const bookedRanges = allSlots
                .filter(function(s) { return s.is_booked; })
                .map(function(s) { return { start: s.time, end: s.end_time }; });

            const selectedEnd = selectedStart ? addMinutes(selectedStart, totalDuration) : null;

            let html = '<div class="st-slots">';

            allSlots.forEach(function(slot) {
                let variant, extraAttr = '', label;
                const slotStart = slot.time;

                const isInSelectedRange = selectedEnd
                    && toMinutes(slotStart) > toMinutes(selectedStart)
                    && toMinutes(slotStart) < toMinutes(selectedEnd);

                const isSelectedSlot = slotStart === selectedStart;

                const wouldConflict = !slot.is_booked && !slot.is_past
                    && slotWouldConflict(slotStart, bookedRanges);

                if (slot.is_booked) {
                    variant   = 'st-slot--booked';
                    extraAttr = 'disabled';
                    label     = slotStart + ' <small>Terisi</small>';
                } else if (slot.is_past) {
                    variant   = 'st-slot--past';
                    extraAttr = 'disabled';
                    label     = slotStart + ' <small>Lewat</small>';
                } else if (wouldConflict) {
                    variant   = 'st-slot--conflict';
                    extraAttr = 'disabled title="Jika dipilih, durasi ' + totalDuration + ' menit akan menabrak jadwal lain"';
                    label     = slotStart + ' <small>Konflik</small>';
                } else if (isSelectedSlot) {
                    variant = 'st-slot--selected';
                    label   = slotStart + ' <small>Dipilih</small>';
                } else if (isInSelectedRange) {
                    variant   = 'st-slot--preview';
                    extraAttr = 'disabled';
                    label     = slotStart;
                } else {
                    variant = 'st-slot--available';
                    label   = slotStart;
                }

                html += '<button type="button" class="st-slot ' + variant + '" '
                      + extraAttr
                      + ' data-start="' + slotStart + '">'
                      + label + '</button>';
            });

            html += '</div>';
            html += '<div class="st-slot-legend">'
                  + '<span class="st-slot-legend-item"><span class="st-slot-legend-dot st-slot-legend-dot--available"></span>Tersedia</span>'
                  + '<span class="st-slot-legend-item"><span class="st-slot-legend-dot st-slot-legend-dot--selected"></span>Dipilih</span>'
                  + '<span class="st-slot-legend-item"><span class="st-slot-legend-dot st-slot-legend-dot--conflict"></span>Konflik durasi</span>'
                  + '<span class="st-slot-legend-item"><span class="st-slot-legend-dot st-slot-legend-dot--booked"></span>Terisi</span>'
                  + '</div>';

            $('#slotsContainer').html(html);
        }

        function loadSlots() {
            const doctorId  = $('#doctorSelect2').val() || $('[name="raw_doctor_id"]').val();
            const branchId  = $('#branchSelect2').val() || $('[name="raw_branch_id"]').val();
            const date      = $('[name="date"]').val();

            if (!doctorId || !date || !branchId) {
                $('#slotsContainer').html(
                    '<span class="text-muted"><i class="fa fa-info-circle"></i> Lengkapi Cabang, Dokter, dan Tanggal</span>'
                );
                return;
            }

            $('#loadSlotsBtn').html('<i class="fa fa-spinner fa-spin"></i> Memuat...').prop('disabled', true);
            $('#slotsContainer').html('');

            fetch('/api/reservation/slots/' + branchId + '/' + doctorId + '/' + date)
                .then(function(r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function(data) { renderSlots(data); })
                .catch(function(err) {
                    $('#slotsContainer').html(
                        '<span class="text-danger"><i class="fa fa-exclamation-circle"></i> Gagal: ' + err.message + '</span>'
                    );
                })
                .finally(function() {
                    $('#loadSlotsBtn').html('<i class="fa fa-sync"></i> Refresh Slot').prop('disabled', false);
                });
        }

        $(document).on('click', '.st-slot:not([disabled])', function() {
            selectedStart = $(this).data('start');
            const end     = addMinutes(selectedStart, totalDuration);

            $('#startHourInput').val(selectedStart);
            $('#endHourInput').val(end);
            $('#currentTime').text(selectedStart + ' – ' + end);
            $('#selectedTimeDisplay').show();

            reRenderSlots();
        });

        function loadBranchesByDoctor(doctorId) {
            if (!doctorId) return;

            fetch('/api/reservation/branches/' + doctorId)
                .then(function(r) { return r.json(); })
                .then(function(branches) {
                    const currentBranch = $('#branchSelect2').val();

                    let opts = '<option value="">- Pilih Cabang -</option>';
                    branches.forEach(function(b) {
                        const selected = b.id === currentBranch ? 'selected' : '';
                        opts += '<option value="' + b.id + '" ' + selected + '>' + b.name + '</option>';
                    });
                    $('#branchSelect2').html(opts);

                    if (typeof $.fn.select2 !== 'undefined') {
                        $('#branchSelect2').trigger('change.select2');
                    }

                    const validIds = branches.map(function(b) { return b.id; });
                    if (currentBranch && !validIds.includes(currentBranch)) {
                        $('#branchSelect2').val('').trigger('change.select2');
                        $('#slotsContainer').html(
                            '<span class="text-warning">'
                            + '<i class="fa fa-exclamation-triangle"></i> '
                            + 'Dokter ini tidak bertugas di cabang sebelumnya. Silakan pilih cabang yang tersedia.'
                            + '</span>'
                        );
                    } else {
                        loadDoctorDates();
                    }
                })
                .catch(function() { loadDoctorDates(); });
        }

        $('#doctorSelect2').on('select2:select select2:unselect change', function() {
            const doctorId = $(this).val();
            if (doctorId) {
                loadBranchesByDoctor(doctorId);
                loadServicesByDoctor();
            } else {
                loadDoctorDates();
            }
        });

        $('#branchSelect2').on('select2:select select2:unselect change', function() {
            loadDoctorDates();
        });

        $(document).ready(function() {
            const initialDoctorId = window.initialDoctorId || $('#doctorSelect2').val();
            if (initialDoctorId) {
                loadBranchesByDoctor(initialDoctorId);
                loadServicesByDoctor();
            } else {
                loadDoctorDates();
            }
        });
        // ===== END SLOT PICKER =====

        // ===== CUSTOMER SELECT2 AJAX =====
        // Cache id -> phone supaya panel quick-add bisa prefill nomor pasien
        // yang dipilih (tanpa nanya nomor 2x).
        window.qpPhones = window.qpPhones || {};
        $('#customerSelect option').each(function () {
            const v = $(this).val(); const p = $(this).data('phone');
            if (v && p) window.qpPhones[v] = p;
        });
        $('#customerSelect').select2({
            placeholder: '- Cari Pasien -',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("api.customer.search") }}',
                dataType: 'json',
                delay: 300,
                data: function(params) { return { q: params.term }; },
                processResults: function(data) {
                    (data || []).forEach(function (r) { if (r.id) window.qpPhones[r.id] = r.phone || ''; });
                    return { results: data };
                }
            }
        });

        // ===== QUICK ADD PASIEN DI NOMOR INI =====
        flatpickr('#qpTgl', {
            dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y',
            maxDate: 'today', disableMobile: true
        });

        function qpSelectedPhone() {
            const id = $('#customerSelect').val();
            return id ? (window.qpPhones[id] || '') : '';
        }
        function qpApplyContext() {
            const phone = qpSelectedPhone();
            if (phone) {
                $('#qpCardTitle').html('<i class="fa fa-user-plus"></i> Pasien Baru di Nomor ' + phone);
                $('#qpPhone').val(phone).prop('readonly', true).attr('title', 'Otomatis ikut nomor pasien terpilih');
            } else {
                $('#qpCardTitle').html('<i class="fa fa-user-plus"></i> Pasien Baru');
                $('#qpPhone').val('').prop('readonly', false).removeAttr('title');
            }
        }
        function qpOpen()  { qpApplyContext(); $('#quickPatientBox').slideDown(150); $('#toggleQuickPatient').hide(); $('#qpName').focus(); }
        function qpClose() { $('#quickPatientBox').slideUp(150); $('#toggleQuickPatient').show(); $('#qpStatus').empty(); }

        $('#toggleQuickPatient').on('click', function (e) { e.preventDefault(); qpOpen(); });
        $('#qpCloseBtn, #qpCancelBtn').on('click', function () { qpClose(); });

        $('#qpSaveBtn').on('click', function () {
            const $btn = $(this);
            const payload = {
                _token:        $('input[name=_token]').first().val(),
                name:          $('#qpName').val().trim(),
                phone:         $('#qpPhone').val().trim(),
                email:         $('#qpEmail').val().trim(),
                jenis_kelamin: $('#qpJk').val(),
                tanggal_lahir: $('#qpTgl').val(),
                relasi:        $('#qpRelasi').val()
            };
            if (!payload.name || !payload.phone) {
                $('#qpStatus').html('<span class="qp-msg qp-err">Nama &amp; No. HP wajib diisi.</span>');
                return;
            }
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
            $.post('{{ route("rawreservation.quickpatient") }}', payload, function (res) {
                const opt = new Option(res.text, res.id, true, true);
                $('#customerSelect').append(opt).trigger('change');
                $('#qpName,#qpPhone,#qpEmail').val('');
                if ($('#qpTgl')[0]._flatpickr) $('#qpTgl')[0]._flatpickr.clear();
                $('#qpStatus').html('<span class="qp-msg qp-ok"><i class="fa fa-check-circle"></i> Pasien ditambahkan &amp; dipilih.</span>');
                setTimeout(qpClose, 900);
            }).fail(function (xhr) {
                let msg = 'Gagal menyimpan pasien.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).map(function (a) { return a[0]; }).join(' ');
                }
                $('#qpStatus').html('<span class="qp-msg qp-err">' + msg + '</span>');
            }).always(function () {
                $btn.prop('disabled', false).html(html);
            });
        });
    </script>
@endsection
