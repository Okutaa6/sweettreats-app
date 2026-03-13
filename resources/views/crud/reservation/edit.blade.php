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
                                                id="doctorSelect2"
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
                                                id="branchSelect2"
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
                                                type="text" id="datePickerReschedule" name="date"
                                                value="{{ old('date', $reservation->date ? $reservation->date->format('Y-m-d') : '') }}"
                                                placeholder="Pilih tanggal..." readonly />
                                            <small id="datePickerInfo" class="text-muted">Pilih dokter dan cabang terlebih dahulu untuk melihat jadwal tersedia</small>
                                            @if ($errors->has('date'))
                                                <div class="invalid-feedback">{{ $errors->first('date') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Jam Mulai</td>
                                        <td>
                                            {{-- Slot picker otomatis cek jadwal saat reschedule --}}
                                            <div id="slotPickerWrap">
                                                @if($totalDuration > 0)
                                                    <span class="badge badge-info" style="margin-bottom:8px;display:inline-block">
                                                        <i class="fa fa-clock"></i> Total durasi perawatan: {{ $totalDuration }} menit
                                                    </span><br>
                                                @else
                                                    <span class="badge badge-warning" style="margin-bottom:8px;display:inline-block">
                                                        <i class="fa fa-exclamation-triangle"></i> Belum ada perawatan — durasi default 30 menit
                                                    </span><br>
                                                @endif
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="loadSlotsBtn" onclick="loadSlots()">
                                                    <i class="fa fa-clock"></i> Cek Slot Tersedia
                                                </button>
                                                <div id="slotsContainer" style="margin-top:10px"></div>
                                            </div>
                                            {{-- hidden inputs yang dikirim ke server --}}
                                            <input type="hidden" name="start_hour" id="startHourInput"
                                                value="{{ old('start_hour', $reservation->start_hour) }}" />
                                            <input type="hidden" name="end_hour" id="endHourInput"
                                                value="{{ old('end_hour', $reservation->end_hour) }}" />
                                            {{-- tampilkan jam yang sedang aktif --}}
                                            <div id="selectedTimeDisplay" style="margin-top:8px;font-weight:600;color:#333">
                                                Jadwal aktif: <span id="currentTime">{{ \Illuminate\Support\Str::substr($reservation->start_hour, 0, 5) }} - {{ \Illuminate\Support\Str::substr($reservation->end_hour, 0, 5) }}</span>
                                            </div>
                                            @if ($errors->has('start_hour'))
                                                <div class="text-danger" style="font-size:0.85em;margin-top:4px">{{ $errors->first('start_hour') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Jam Selesai</td>
                                        <td>
                                            <span class="text-muted" style="font-size:0.9em">Otomatis terisi saat pilih slot di atas</span>
                                            <span id="endHourDisplay" style="margin-left:8px;font-weight:600">{{ \Illuminate\Support\Str::substr($reservation->end_hour, 0, 5) }}</span>
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

                                    {{-- Layanan checklist langsung di form --}}
                                    <tr>
                                        <td valign="top" style="padding-top:14px">Layanan</td>
                                        <td>
                                            {{-- checklist layanan di-render JS berdasarkan dokter yang dipilih --}}
                                            {{-- data awal dari PHP dipass ke JS via JSON --}}
                                            <div id="serviceChecklist">
                                                <span class="text-muted"><i class="fa fa-spinner fa-spin"></i> Memuat layanan...</span>
                                            </div>

                                            {{-- data reservasi saat ini (untuk restore state setelah render) --}}
                                            <script>
                                                // inject nilai awal dari PHP — tidak bergantung pada select2 timing
                                                window.initialDoctorId = '{{ $reservation->raw_doctor_id ?? "" }}';
                                                window.existingServices = @json(
                                                    $services->mapWithKeys(fn($s) => [
                                                        $s->raw_doc_service_id => [
                                                            'detail_id' => $s->raw_doc_service_detail_id,
                                                        ]
                                                    ])
                                                );
                                            </script>

                                            {{-- total durasi hitung otomatis --}}
                                            <div id="totalDurationInfo" class="mt-2" style="font-size:0.85em; color:#666"></div>
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

        {{-- Komisi --}}
@if($reservation->commission)
@php $comm = $reservation->commission; $commOpt = \App\Models\RawCommission::statusOptions()[$comm->status] ?? []; @endphp
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Komisi Affiliate</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <td width="25%">Affiliate</td>
                        <td>{{ $comm->affiliate->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Kode Promo</td>
                        <td>{{ $reservation->promo->promo_code ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Jumlah Komisi</td>
                        <td><strong>Rp {{ number_format($comm->amount, 0, ',', '.') }}</strong></td>
                    </tr>
                    <tr>
                        <td>Status Komisi</td>
                        <td>
                            <span class="badge badge-{{ $commOpt['color'] ?? 'secondary' }}">
                                {{ $commOpt['label'] ?? $comm->status }}
                            </span>
                        </td>
                    </tr>
                    @if($comm->status === 'pending')
                    <tr>
                        <td></td>
                        <td>
                            <form method="POST" action="{{ route('rawreservation.commission.approve', [$reservation->id, $comm->id]) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-primary btn-sm" type="submit">
                                    ✅ Approve Komisi
                                </button>
                            </form>
                        </td>
                    </tr>
                    @elseif($comm->status === 'approved')
                    <tr>
                        <td></td>
                        <td>
                            <form method="POST" action="{{ route('rawreservation.commission.pay', [$reservation->id, $comm->id]) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-success btn-sm" type="submit">
                                    💰 Tandai Sudah Dibayar
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('js')
    {{-- Flatpickr untuk date picker dengan restrict hari dokter --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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

        // LAYANAN CHECKLIST
        // Hitung total durasi dari semua layanan yang diceklis
        function updateTotalDuration() {
            let total = 0;
            let names = [];

            $('.svc-checkbox:checked').each(function() {
                const svcId     = $(this).data('svc-id');
                const detailSel = $('#detailWrap_' + svcId + ' .svc-detail-select');
                const defDur    = parseFloat($(this).data('default-dur')) || 0;

                let dur = defDur;
                if (detailSel.length && detailSel.val()) {
                    dur = parseFloat(detailSel.find('option:selected').data('dur')) || defDur;
                }

                total += dur;
                names.push($(this).data('svc-name'));
            });

            if (total > 0) {
                $('#totalDurationInfo').html(
                    '<i class="fa fa-clock text-info"></i> '
                    + '<strong>Total durasi: ' + total + ' menit</strong>'
                    + ' (' + names.join(', ') + ')'
                );
            } else {
                $('#totalDurationInfo').html('<span class="text-muted">Belum ada layanan dipilih</span>');
            }

            // auto-refresh slot karena durasi berubah
            totalDuration = total || {{ $totalDuration ?? 30 }};
            if (allSlots.length > 0) reRenderSlots();
        }

        // render checklist layanan dari services yang dokter bisa lakukan
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

            let html = '';
            services.forEach(function(svc) {
                const existing    = (window.existingServices || {})[svc.id] || null;
                const isChecked   = existing !== null;
                const existDetail = existing ? existing.detail_id : '';
                const bg          = isChecked ? '#f0fff4' : '#fff';
                const border      = isChecked ? '#b2dfdb' : '#e3e6f0';

                html += '<div class="service-row mb-2 p-2" '
                      + 'style="border:1px solid ' + border + '; border-radius:6px; background:' + bg + '" '
                      + 'id="svcRow_' + svc.id + '">';

                html += '<div class="d-flex align-items-center flex-wrap" style="gap:10px">';

                // checkbox layanan
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

                // badge durasi & harga default
                html += '<span class="badge badge-light text-muted svc-dur-badge" id="svcDurBadge_' + svc.id + '">'
                      + '<i class="fa fa-clock"></i> ' + svc.duration_minutes + ' menit</span>';
                html += '<span class="badge badge-light text-muted" id="svcPriceBadge_' + svc.id + '">'
                      + '<i class="fa fa-tag"></i> Rp ' + Number(svc.price).toLocaleString('id-ID') + '</span>';

                html += '</div>'; // end d-flex

                // baris sub-detail — muncul kalau diceklis
                html += '<div class="svc-detail-wrap mt-2" id="detailWrap_' + svc.id + '" '
                      + 'style="' + (isChecked ? '' : 'display:none') + '">';
                html += '<div class="d-flex flex-wrap" style="gap:10px">';

                // dropdown sub-detail (opsional)
                html += '<div style="min-width:220px; flex:1">';
                html += '<label class="small text-muted mb-1">Sub-detail Perawatan <span class="text-muted">(opsional)</span></label>';

                if (svc.details && svc.details.length > 0) {
                    html += '<select name="services[' + svc.id + '][detail_id]" '
                          + 'class="form-control form-control-sm svc-detail-select" '
                          + 'data-svc-id="' + svc.id + '" '
                          + 'data-default-dur="' + svc.duration_minutes + '" '
                          + 'data-default-price="' + svc.price + '">';
                    html += '<option value="">- Pakai default (' + svc.name + ') -</option>';
                    svc.details.forEach(function(d) {
                        const sel = existDetail === d.id ? 'selected' : '';
                        html += '<option value="' + d.id + '" '
                              + 'data-dur="' + d.duration_minutes + '" '
                              + 'data-price="' + d.price + '" '
                              + sel + '>'
                              + d.name + ' (' + d.duration_minutes + ' mnt — Rp ' + Number(d.price).toLocaleString('id-ID') + ')'
                              + '</option>';
                    });
                    html += '</select>';
                } else {
                    html += '<div class="form-control form-control-sm bg-light text-muted" style="cursor:not-allowed">'
                          + 'Belum ada sub-detail — <a href="/rawdocservice/' + svc.id + '" target="_blank">Tambah di sini</a>'
                          + '</div>';
                }

                html += '</div></div></div>'; // end sub-detail wrap
                html += '</div>'; // end service-row
            });

            $('#serviceChecklist').html(html);

            // kalau ada sub-detail yang sudah dipilih sebelumnya, update badge-nya
            services.forEach(function(svc) {
                const detailSel = $('#detailWrap_' + svc.id + ' .svc-detail-select');
                if (detailSel.length && detailSel.val()) {
                    detailSel.trigger('change');
                }
            });

            updateTotalDuration();
        }

        // load layanan berdasarkan dokter yang dipilih di header
        function loadServicesByDoctor() {
            // prioritaskan select2, fallback ke PHP-injected initial value
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
                .then(function(services) {
                    try {
                        renderServiceChecklist(services);
                    } catch(renderErr) {
                        console.error('renderServiceChecklist error:', renderErr);
                        $('#serviceChecklist').html(
                            '<span class="text-danger"><i class="fa fa-exclamation-circle"></i> '
                            + 'Render error: ' + renderErr.message + '</span>'
                        );
                    }
                })
                .catch(function(err) {
                    console.error('fetch error:', err);
                    $('#serviceChecklist').html(
                        '<span class="text-danger"><i class="fa fa-exclamation-circle"></i> '
                        + 'Fetch error: ' + err.message + '</span>'
                    );
                });
        }

        // pas checkbox layanan di-toggle
        $(document).on('change', '.svc-checkbox', function() {
            const svcId   = $(this).data('svc-id');
            const checked = $(this).is(':checked');
            const row     = $('#svcRow_' + svcId);
            const wrap    = $('#detailWrap_' + svcId);

            if (checked) {
                wrap.show();
                row.css('background', '#f0fff4').css('border-color', '#b2dfdb');
            } else {
                wrap.hide();
                wrap.find('select').val('');
                row.css('background', '#fff').css('border-color', '#e3e6f0');
            }

            updateTotalDuration();
        });

        // pas sub-detail berubah → update badge durasi & harga
        $(document).on('change', '.svc-detail-select', function() {
            const svcId    = $(this).data('svc-id');
            const selected = $(this).find('option:selected');
            const defDur   = $(this).data('default-dur');
            const defPrice = $(this).data('default-price');

            const dur   = $(this).val() ? selected.data('dur')   : defDur;
            const price = $(this).val() ? selected.data('price') : defPrice;

            $('#svcDurBadge_' + svcId).html('<i class="fa fa-clock"></i> ' + dur + ' menit');
            $('#svcPriceBadge_' + svcId).html('<i class="fa fa-tag"></i> Rp ' + Number(price).toLocaleString('id-ID'));

            updateTotalDuration();
        });
        // END LAYANAN CHECKLIST

        // ===== SLOT PICKER FOR RESCHEDULE =====
        let totalDuration = {{ $totalDuration ?? 30 }}; // let bukan const karena di-update saat layanan berubah
        let   allSlots      = [];
        let   selectedStart = '{{ substr($reservation->start_hour ?? "", 0, 5) }}';
        let   datePicker    = null;   // flatpickr instance

        // Helper: "HH:MM" → total menit
        function toMinutes(t) {
            const [h, m] = (t || '00:00').split(':').map(Number);
            return h * 60 + m;
        }
        // Helper: tambah menit ke "HH:MM"
        function addMinutes(t, mins) {
            const total = toMinutes(t) + mins;
            return String(Math.floor(total / 60)).padStart(2,'0') + ':' + String(total % 60).padStart(2,'0');
        }

        // Cek apakah slot [slotStart, slotStart+totalDuration] overlap dengan range [bookedStart, bookedEnd]
        function slotWouldConflict(slotStart, bookedRanges) {
            const sStart = toMinutes(slotStart);
            const sEnd   = sStart + totalDuration;
            return bookedRanges.some(function(r) {
                return sStart < toMinutes(r.end) && sEnd > toMinutes(r.start);
            });
        }

        // ---- DATE PICKER: hanya izinkan hari sesuai jadwal dokter ----
        function initDatePicker(enabledDates) {
            if (datePicker) datePicker.destroy();

            const config = {
                dateFormat: 'Y-m-d',
                disableMobile: true,
                locale: { firstDayOfWeek: 1 },
                onChange: function(selectedDates, dateStr) {
                    loadSlots();
                }
            };

            if (enabledDates && enabledDates.length > 0) {
                // Whitelist: hanya tanggal yang ada di array
                config.enable = enabledDates;
                $('#datePickerInfo').html(
                    '<i class="fa fa-calendar-check text-success"></i> '
                    + enabledDates.length + ' tanggal tersedia dalam 90 hari ke depan'
                );
            } else {
                // Kalau belum ada dokter/cabang, disable semua
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
                    // Kalau tanggal yang sedang aktif tidak valid untuk dokter ini, kosongkan
                    const currentDate = $('[name="date"]').val();
                    if (currentDate && !dateStrings.includes(currentDate)) {
                        if (datePicker) datePicker.clear();
                        $('#slotsContainer').html(
                            '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> '
                            + 'Tanggal sebelumnya tidak sesuai jadwal dokter. Pilih tanggal baru.</span>'
                        );
                    } else {
                        loadSlots();
                    }
                })
                .catch(function() {
                    initDatePicker([]);
                });
        }

        // ---- SLOT RENDERING ----
        function renderSlots(data) {
            allSlots = data.slots || [];
            if (allSlots.length === 0) {
                $('#slotsContainer').html('<span class="text-muted">Tidak ada slot tersedia untuk jadwal ini</span>');
                return;
            }
            reRenderSlots();
        }

        function reRenderSlots() {
            // kumpulin semua range yang SUDAH terisi (dari API)
            const bookedRanges = allSlots
                .filter(function(s) { return s.is_booked; })
                .map(function(s) { return { start: s.time, end: s.end_time }; });

            const selectedEnd = selectedStart ? addMinutes(selectedStart, totalDuration) : null;

            let html = '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">';

            allSlots.forEach(function(slot) {
                let btnClass, extraAttr = '', label;
                const slotStart = slot.time;

                // Apakah slot ini masuk dalam range durasi yang sedang dipilih user?
                const isInSelectedRange = selectedEnd
                    && toMinutes(slotStart) > toMinutes(selectedStart)
                    && toMinutes(slotStart) < toMinutes(selectedEnd);

                const isSelectedSlot = slotStart === selectedStart;

                // Apakah memilih slot ini akan menabrak booking yang ada?
                const wouldConflict = !slot.is_booked && !slot.is_past
                    && slotWouldConflict(slotStart, bookedRanges);

                if (slot.is_booked) {
                    btnClass  = 'btn btn-sm btn-danger';
                    extraAttr = 'disabled';
                    label     = slotStart + ' <small>(Terisi)</small>';
                } else if (slot.is_past) {
                    btnClass  = 'btn btn-sm btn-secondary';
                    extraAttr = 'disabled';
                    label     = slotStart + ' <small>(Lewat)</small>';
                } else if (wouldConflict) {
                    // slot ini ada tapi kalau dipilih durasinya akan tabrak booking
                    btnClass  = 'btn btn-sm btn-warning';
                    extraAttr = 'disabled title="Jika dipilih, durasi ' + totalDuration + ' menit akan menabrak jadwal lain"';
                    label     = slotStart + ' <small>(Konflik durasi)</small>';
                } else if (isSelectedSlot) {
                    btnClass  = 'btn btn-sm btn-primary';
                    label     = slotStart + ' <small>(Dipilih)</small>';
                } else if (isInSelectedRange) {
                    btnClass  = 'btn btn-sm btn-danger';
                    extraAttr = 'disabled';
                    label     = slotStart + ' <small>(Akan terisi)</small>';
                } else {
                    btnClass  = 'btn btn-sm btn-success';
                    label     = slotStart;
                }

                html += '<button type="button" class="slot-btn ' + btnClass + '" '
                      + extraAttr
                      + ' data-start="' + slotStart + '">'
                      + label + '</button>';
            });

            html += '</div>';
            html += '<small class="text-muted d-block mt-2">'
                  + '<span class="badge badge-primary">Dipilih</span> &nbsp;'
                  + '<span class="badge badge-success">Tersedia</span> &nbsp;'
                  + '<span class="badge badge-warning">Konflik durasi</span> &nbsp;'
                  + '<span class="badge badge-danger">Terisi / Akan terisi</span>'
                  + '</small>';

            $('#slotsContainer').html(html);
        }

        function loadSlots() {
            const doctorId  = $('#doctorSelect2').val() || $('[name="raw_doctor_id"]').val();
            const branchId  = $('#branchSelect2').val() || $('[name="raw_branch_id"]').val();
            const date      = $('[name="date"]').val();
            const excludeId = '{{ $reservation->id }}';

            if (!doctorId || !date || !branchId) {
                $('#slotsContainer').html(
                    '<span class="text-muted"><i class="fa fa-info-circle"></i> Lengkapi Cabang, Dokter, dan Tanggal</span>'
                );
                return;
            }

            $('#loadSlotsBtn').html('<i class="fa fa-spinner fa-spin"></i> Memuat...').prop('disabled', true);
            $('#slotsContainer').html('');

            fetch('/api/reservation/slots/' + branchId + '/' + doctorId + '/' + date + '?exclude_id=' + excludeId)
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

        // Klik slot
        $(document).on('click', '.slot-btn:not([disabled])', function() {
            selectedStart = $(this).data('start');
            const end     = addMinutes(selectedStart, totalDuration);

            $('#startHourInput').val(selectedStart);
            $('#endHourInput').val(end);
            $('#currentTime').text(selectedStart + ' - ' + end);
            $('#endHourDisplay').text(end);

            reRenderSlots();
        });

        // ---- BRANCH FILTER BY DOCTOR ----
        function loadBranchesByDoctor(doctorId) {
            if (!doctorId) return;

            fetch('/api/reservation/branches/' + doctorId)
                .then(function(r) { return r.json(); })
                .then(function(branches) {
                    const currentBranch = $('#branchSelect2').val();

                    // Rebuild option list
                    let opts = '<option value="">- Pilih Cabang -</option>';
                    branches.forEach(function(b) {
                        const selected = b.id === currentBranch ? 'selected' : '';
                        opts += '<option value="' + b.id + '" ' + selected + '>' + b.name + '</option>';
                    });
                    $('#branchSelect2').html(opts);

                    // Trigger select2 refresh
                    if (typeof $.fn.select2 !== 'undefined') {
                        $('#branchSelect2').trigger('change.select2');
                    }

                    // Kalau cabang aktif tidak ada di list dokter ini → clear & info
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
                        // Cabang masih valid, lanjut load tanggal
                        loadDoctorDates();
                    }
                })
                .catch(function() {
                    // Kalau gagal fetch, biarkan dropdown apa adanya
                    loadDoctorDates();
                });
        }

        // pas dokter berubah → filter cabang, reload dates & slots, reload layanan
        $('#doctorSelect2').on('select2:select select2:unselect change', function() {
            const doctorId = $(this).val();
            if (doctorId) {
                loadBranchesByDoctor(doctorId);
                loadServicesByDoctor();
            } else {
                loadDoctorDates();
            }
        });

        // pas cabang berubah (tanpa ganti dokter) → reload dates & slots
        $('#branchSelect2').on('select2:select select2:unselect change', function() {
            loadDoctorDates();
        });

        // Saat load pertama: pakai window.initialDoctorId dari PHP — tidak bergantung select2 timing
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
    </script>
@endsection