@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        {{-- Page Head --}}
        @php
            $isNewReservation = $reservation->created_at && \Carbon\Carbon::parse($reservation->created_at)->diffInHours(\Carbon\Carbon::now()) < 6;
        @endphp
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">
                        Detail Reservasi
                        @if($isNewReservation)
                            <span class="st-card__badge-new"><i class="fa fa-star"></i> Baru</span>
                        @endif
                    </h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="mb-3">
            <a href="{{ route('rawreservation') }}" class="st-btn-back"><i class="fa fa-arrow-left"></i> Kembali</a>
        </div>

        {{-- Reservation Info Section --}}
        <form method="post" action="{{ route('rawreservation.update', $reservation->id) }}">
            @csrf
            @method('PUT')

            <div class="row">
                {{-- Kolom Kiri (Info Utama) --}}
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
                                    @if(!$reservation->customer)
                                        <div class="st-alert st-alert--warning st-alert--compact">
                                            <i class="fa fa-exclamation-triangle st-alert__icon"></i>
                                            <div class="st-alert__body">Pasien telah dihapus. Pilih pasien pengganti di bawah.</div>
                                        </div>
                                    @endif
                                    <select class="form-control {{ $errors->has('raw_customer_id') ? 'is-invalid' : '' }}" name="raw_customer_id" id="customerSelect">
                                        @if($reservation->customer)
                                            <option value="{{ $reservation->customer->id }}" selected>
                                                {{ $reservation->customer->name }} ({{ $reservation->customer->phone ?: '-' }})
                                            </option>
                                        @else
                                            <option value="">- Cari Pasien -</option>
                                        @endif
                                    </select>
                                    @if ($errors->has('raw_customer_id'))
                                        <div class="invalid-feedback">{{ $errors->first('raw_customer_id') }}</div>
                                    @endif
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Dokter</label>
                                    <select id="doctorSelect2" class="select2 form-control {{ $errors->has('raw_doctor_id') ? 'is-invalid' : '' }}" name="raw_doctor_id">
                                        <option value="">- Pilih Doctor -</option>
                                        @foreach ($doctors as $doctor)
                                            <option value="{{ $doctor->id }}" {{ $reservation->raw_doctor_id == $doctor->id ? 'selected' : '' }}>
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
                                        <option value="{{ $branch->id }}" {{ $reservation->raw_branch_id == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($errors->has('raw_branch_id'))
                                    <div class="invalid-feedback">{{ $errors->first('raw_branch_id') }}</div>
                                @endif
                                <div id="branchChangeNotice" class="st-alert st-alert--warning mt-2 mb-0" style="display:none;">
                                    <i class="fa fa-exclamation-triangle st-alert__icon"></i>
                                    <div class="st-alert__body"><strong>Perhatian!</strong> Jika cabang dipindahkan, data pasien tidak akan terlihat atau ditransfer ke cabang baru.</div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Tanggal</label>
                                    <input class="form-control {{ $errors->has('date') ? 'is-invalid' : '' }}" type="text" id="datePickerReschedule" name="date" value="{{ old('date', $reservation->date ? $reservation->date->format('Y-m-d') : '') }}" placeholder="Pilih tanggal..." readonly />
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
                                        <div class="st-slot-info" id="selectedTimeDisplay">
                                            <i class="fa fa-bookmark"></i>
                                            <span class="st-slot-info-label">Jadwal aktif</span>
                                            <span class="st-slot-info-value" id="currentTime">{{ \Illuminate\Support\Str::substr($reservation->start_hour, 0, 5) }} – {{ \Illuminate\Support\Str::substr($reservation->end_hour, 0, 5) }}</span>
                                        </div>
                                        <input type="hidden" name="start_hour" id="startHourInput" value="{{ old('start_hour', $reservation->start_hour) }}" />
                                        <input type="hidden" name="end_hour" id="endHourInput" value="{{ old('end_hour', $reservation->end_hour) }}" />
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
                            @if($reservation->lainnya_text)
                                <div class="form-group">
                                    <label>Keluhan Awal Pasien</label>
                                    <div class="st-alert st-alert--warning">
                                        <i class="fa fa-comment-dots st-alert__icon"></i>
                                        <div class="st-alert__body"><strong>{{ $reservation->lainnya_text }}</strong></div>
                                    </div>
                                    <small class="text-muted">Ini keluhan yang ditulis pasien saat reservasi online. Tentukan perawatan yang sesuai di bawah ini.</small>
                                </div>
                            @endif

                            <div id="serviceChecklist">
                                <span class="text-muted"><i class="fa fa-spinner fa-spin"></i> Memuat layanan...</span>
                            </div>
                            <script>
                                window.initialDoctorId = '{{ $reservation->raw_doctor_id ?? "" }}';
                                @php
                                    $mappedServices = $services->groupBy('raw_doc_service_id')
                                        ->mapWithKeys(function ($group, $serviceId) {
                                            return [
                                                $serviceId => [
                                                    'detail_ids' => $group->pluck('raw_doc_service_detail_id')->filter()->values()->all(),
                                                    'details'    => $group->filter(function($item) { return $item->raw_doc_service_detail_id != null; })->map(function($item) {
                                                        return [
                                                            'id'       => $item->raw_doc_service_detail_id,
                                                            'quantity' => $item->quantity ?? 1
                                                        ];
                                                    })->values()->all()
                                                ]
                                            ];
                                        });
                                @endphp
                                window.existingServices = @json($mappedServices);
                            </script>
                            <div id="totalDurationInfo" class="st-total-duration" style="display:none;"></div>
                        </div>
                    </div>
                </div>

                {{-- Kolom Kanan (Aksi & Info Tambahan) --}}
                <div class="col-lg-4">
                    <div class="st-card">
                        <div class="st-card__head">
                            <div class="st-card__title-wrap">
                                <span class="st-card__icon"><i class="fa fa-sliders-h"></i></span>
                                <h4 class="st-card__title">Aksi &amp; Status</h4>
                            </div>
                        </div>
                        <div class="st-card__body">
                            <div class="form-group">
                                <label>Status Reservasi</label>
                                <select class="form-control" name="status">
                                    @foreach (\App\Models\RawCusReservation::statusOptions() as $key => $opt)
                                        <option value="{{ $key }}" {{ $reservation->status == $key ? 'selected' : '' }}>
                                            {{ $opt['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Keterangan Tambahan</label>
                                <textarea class="form-control {{ $errors->has('keterangan') ? 'is-invalid' : '' }}" name="keterangan" rows="4" placeholder="Tambahkan keterangan tambahan jika ada...">{{ old('keterangan', $reservation->keterangan) }}</textarea>
                                @if ($errors->has('keterangan'))
                                    <div class="invalid-feedback" style="display:block">{{ $errors->first('keterangan') }}</div>
                                @endif
                            </div>
                            <div class="form-group">
                                <label>Gunakan Promo</label>
                                <select class="select2 form-control" name="raw_promo_id">
                                    <option value="">- Tanpa Promo -</option>
                                    @foreach ($promos as $promo)
                                        <option value="{{ $promo->id }}" {{ old('raw_promo_id', $reservation->raw_promo_id) == $promo->id ? 'selected' : '' }}>
                                            {{ $promo->promo_code }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @if($reservation->promo)
                                <ul class="st-kv-list">
                                    <li>
                                        <strong>Kode</strong>
                                        <span class="st-pill st-pill--info">{{ $reservation->promo->promo_code }}</span>
                                    </li>
                                    <li>
                                        <strong>Diskon</strong>
                                        <span>
                                            @if($reservation->promo->discount_type == 'percent')
                                                {{ $reservation->promo->discount_value }}%
                                            @else
                                                Rp {{ number_format($reservation->promo->discount_value, 0, ',', '.') }}
                                            @endif
                                        </span>
                                    </li>
                                    @if($reservation->promo->affiliate)
                                        <li>
                                            <strong>Affiliate</strong>
                                            <span>{{ $reservation->promo->affiliate->name }}</span>
                                        </li>
                                    @endif
                                </ul>
                            @endif
                            <div class="st-btn-row">
                                <button class="st-btn-brand" type="submit"><i class="fa fa-save"></i> Simpan Perubahan</button>
                                <button type="button" class="st-btn-danger-outline" onclick="deleteReservation()" title="Hapus reservasi"><i class="fa fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <form action="{{ route('rawreservation.destroy', $reservation->id) }}" method="POST" id="deleteReservation" style="display: none;">
            @csrf
            @method('DELETE')
        </form>

        {{-- Komisi --}}
        @if($reservation->commission)
            @php $comm = $reservation->commission; $commOpt = \App\Models\RawCommission::statusOptions()[$comm->status] ?? []; @endphp
            @php
                $commPillClass = match($comm->status) {
                    'pending'  => 'st-pill--warning',
                    'approved' => 'st-pill--info',
                    'paid'     => 'st-pill--success',
                    default    => 'st-pill--neutral',
                };
            @endphp
            <div class="row">
                <div class="col-md-12">
                    <div class="st-card">
                        <div class="st-card__head">
                            <div class="st-card__title-wrap">
                                <span class="st-card__icon"><i class="fa fa-hand-holding-usd"></i></span>
                                <h4 class="st-card__title">Komisi Affiliate</h4>
                            </div>
                        </div>
                        <div class="st-card__body" style="padding: 0;">
                            <table class="st-kv-table">
                                <tbody>
                                    <tr>
                                        <td>Affiliate</td>
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
                                            <span class="st-pill {{ $commPillClass }}">{{ $commOpt['label'] ?? $comm->status }}</span>
                                        </td>
                                    </tr>
                                    @if($comm->status === 'pending')
                                        <tr>
                                            <td>Tindakan</td>
                                            <td>
                                                <form method="POST" action="{{ route('rawreservation.commission.approve', [$reservation->id, $comm->id]) }}" class="m-0">
                                                    @csrf @method('PATCH')
                                                    <button class="st-btn-brand" type="submit"><i class="fa fa-check"></i> Approve Komisi</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @elseif($comm->status === 'approved')
                                        <tr>
                                            <td>Tindakan</td>
                                            <td>
                                                <form method="POST" action="{{ route('rawreservation.commission.pay', [$reservation->id, $comm->id]) }}" class="m-0">
                                                    @csrf @method('PATCH')
                                                    <button class="st-btn-brand" type="submit"><i class="fa fa-money-bill-wave"></i> Tandai Sudah Dibayar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
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

        // Notice muncul saat cabang diganti
            const originalBranchId = '{{ $reservation->raw_branch_id }}';
            $('#branchSelect2').on('change', function () {
                if ($(this).val() !== originalBranchId) {
                    $('#branchChangeNotice').show();
                } else {
                    $('#branchChangeNotice').hide();
                }
            });
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

            let html = '<div class="st-service-list">';
            services.forEach(function(svc) {
                const existing       = (window.existingServices || {})[svc.id] || null;
                const isChecked      = existing !== null;
                const existDetailIds = existing ? (existing.detail_ids || []) : [];

                html += '<div class="st-service-row ' + (isChecked ? 'is-checked' : '') + '" '
                      + 'id="svcRow_' + svc.id + '">';

                html += '<div class="st-service-row__top">';

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

                html += '</div>'; // end st-service-row__top

                // baris sub-detail — muncul kalau diceklis
                html += '<div class="svc-detail-wrap" id="detailWrap_' + svc.id + '" '
                      + 'style="' + (isChecked ? '' : 'display:none') + '">';
                html += '<div>';

                if (svc.details && svc.details.length > 0) {
                    // Cek apakah ada sub-detail yang sudah dicentang untuk membuka area ini otomatis
                    const hasCheckedDetail = svc.details.some(function(d) { return (existDetailIds || []).includes(d.id); });
                    const collapseClass = hasCheckedDetail ? 'show' : '';
                    
                    html += '<div class="d-flex align-items-center justify-content-between mb-1">';
                    html += '  <label class="small text-muted mb-0">Sub-detail Perawatan <span class="text-muted">(opsional)</span></label>';
                    html += '  <a href="#collapseSubSvc_' + svc.id + '" data-toggle="collapse" class="small text-primary" style="text-decoration:none;"><i class="fa fa-list"></i> Tampilkan/Sembunyikan</a>';
                    html += '</div>';

                    // Wrap dalam collapse dan beri max-height dengan scrollbar
                    html += '<div class="collapse ' + collapseClass + '" id="collapseSubSvc_' + svc.id + '">';
                    
                    // Input pencarian sub-detail
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

                html += '</div></div>'; // end sub-detail wrap
                html += '</div>'; // end service-row
            });
            html += '</div>'; // end st-service-list

            $('#serviceChecklist').html(html);

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
                row.addClass('is-checked');
            } else {
                wrap.hide();
                wrap.find('input[type="checkbox"]').prop('checked', false); // Uncheck all sub-services
                row.removeClass('is-checked');
            }

            updateTotalDuration();
        });

        // pas sub-detail (checkbox) berubah -> update total durasi
        $(document).on('change', '.svc-detail-checkbox', function() {
            const isChecked = $(this).is(':checked');
            const detailId = $(this).val();
            const qtyWrap = $('#qty_wrap_' + detailId);
            
            if (isChecked) {
                qtyWrap.show();
            } else {
                qtyWrap.hide();
            }
            
            updateTotalDuration();
        });

        // Update durasi saat input quantity diubah
        $(document).on('input change', '.qty-input', function() {
            updateTotalDuration();
        });

        // Fitur pencarian sub-detail
        $(document).on('input', '.sub-detail-search', function() {
            const keyword = $(this).val().toLowerCase();
            const targetId = $(this).data('target');
            
            $(targetId).find('.sub-detail-item').each(function() {
                const name = String($(this).data('name') || '');
                if (name.includes(keyword)) {
                    $(this).removeClass('d-none').addClass('d-flex');
                } else {
                    $(this).removeClass('d-flex').addClass('d-none');
                }
            });
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

            let html = '<div class="st-slots">';

            allSlots.forEach(function(slot) {
                let variant, extraAttr = '', label;
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
        $(document).on('click', '.st-slot:not([disabled])', function() {
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

        // ===== CUSTOMER SELECT2 AJAX =====
        $('#customerSelect').select2({
            placeholder: '- Cari Pasien -',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("api.customer.search") }}',
                dataType: 'json',
                delay: 300,
                data: function(params) { return { q: params.term }; },
                processResults: function(data) { return { results: data }; }
            }
        });
    </script>
@endsection