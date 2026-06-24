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
        {{-- Page Head --}}
        @php
            $isNewReservation = $reservation->created_at && \Carbon\Carbon::parse($reservation->created_at)->diffInHours(\Carbon\Carbon::now()) < 6;
        @endphp
        {{-- Chrome halaman (judul + notif + Kembali) — disembunyikan saat dimuat di drawer (?drawer=1). --}}
        @unless(request()->boolean('drawer'))
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
        @endunless

        {{-- Reservation Info Section --}}
        <form method="post" action="{{ route('rawreservation.update', $reservation->id) }}" id="rrReservationForm">
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
                                // Kontrak config form reservasi — SEMUA nilai dari Blade dikumpulkan di sini.
                                // initReservationForm() (file statik nanti) baca dari objek ini, bukan Blade echo inline.
                                window.rrFormConfig = {
                                    mode:               'edit',
                                    reservationId:      '{{ $reservation->id }}',
                                    excludeId:          '{{ $reservation->id }}',
                                    totalDuration:      {{ $totalDuration ?? 30 }},
                                    originalBranchId:   '{{ $reservation->raw_branch_id }}',
                                    selectedStart:      '{{ substr($reservation->start_hour ?? "", 0, 5) }}',
                                    initialDoctorId:    '{{ $reservation->raw_doctor_id ?? "" }}',
                                    existingServices:   @json($mappedServices),
                                    waitlistPrompt:     @json(session('waitlist_prompt')),
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
                                <label>Promo <small class="text-muted">(Promo Bulanan, Sweet Surprise, dll — dari Marketing)</small></label>
                                <select class="select2 form-control" name="marketing_promo_id">
                                    <option value="">- Tanpa Promo -</option>
                                    @foreach ($marketingPromos as $mp)
                                        <option value="{{ $mp->id }}"
                                            {{ old('marketing_promo_id', $reservation->marketing_promo_id) == $mp->id ? 'selected' : '' }}>
                                            {{ $mp->nama_promo }}
                                            @if($mp->harga_promo) — Rp {{ number_format($mp->harga_promo, 0, ',', '.') }} @endif
                                        </option>
                                    @endforeach
                                </select>
                                @if($marketingPromos->isEmpty())
                                    <small class="text-muted">Tidak ada promo aktif untuk cabang & tanggal ini.</small>
                                @endif
                            </div>
                            <div class="form-group">
                                <label>Gunakan Kode Referral</label>
                                <select class="select2 form-control {{ $errors->has('raw_promo_id') ? 'is-invalid' : '' }}" name="raw_promo_id">
                                    <option value="">- Tanpa Kode Referral -</option>
                                    @foreach ($promos as $promo)
                                        @php
                                            $scopeCfg = $promo->referral_scope ? \App\Models\RawAffiliate::referralScopes()[$promo->referral_scope] ?? null : null;
                                            $scopeLabel = $scopeCfg ? ' — ' . $scopeCfg['short'] . ' · Voucher Rp ' . number_format($scopeCfg['voucher_amount'], 0, ',', '.') : '';
                                        @endphp
                                        <option value="{{ $promo->id }}" {{ old('raw_promo_id', $reservation->raw_promo_id) == $promo->id ? 'selected' : '' }}>
                                            {{ $promo->promo_code }}{{ $scopeLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($errors->has('raw_promo_id'))
                                    <div class="invalid-feedback" style="display:block">
                                        <i class="fa fa-exclamation-triangle"></i> {{ $errors->first('raw_promo_id') }}
                                    </div>
                                @endif
                                <small class="text-muted">
                                    <i class="fa fa-info-circle"></i>
                                    Kode <strong>Behel</strong> hanya valid untuk Pemasangan Behel.
                                    Kode <strong>Non-Behel</strong> hanya untuk Scaling, Whitening, PSA, atau Veneer.
                                </small>
                            </div>
                            @if($reservation->marketingPromo)
                                <ul class="st-kv-list">
                                    <li>
                                        <strong>Promo</strong>
                                        <span class="st-pill st-pill--success">{{ $reservation->marketingPromo->nama_promo }}</span>
                                    </li>
                                    <li>
                                        <strong>Campaign</strong>
                                        <span>{{ \App\Models\MarketingPromo::campaignTypes()[$reservation->marketingPromo->campaign_type] ?? $reservation->marketingPromo->campaign_type }}</span>
                                    </li>
                                    @if($reservation->marketingPromo->harga_promo)
                                        <li>
                                            <strong>Harga Promo</strong>
                                            <span>Rp {{ number_format($reservation->marketingPromo->harga_promo, 0, ',', '.') }}</span>
                                        </li>
                                    @endif
                                    @if($reservation->marketingPromo->voucher_tambahan_pasien)
                                        <li>
                                            <strong>Voucher Tambahan</strong>
                                            <span>Rp {{ number_format($reservation->marketingPromo->voucher_tambahan_pasien, 0, ',', '.') }}</span>
                                        </li>
                                    @endif
                                </ul>
                            @endif
                            @if($reservation->promo)
                                @php $resPromoScope = $reservation->promo->referral_scope ? \App\Models\RawAffiliate::referralScopes()[$reservation->promo->referral_scope] ?? null : null; @endphp
                                <ul class="st-kv-list">
                                    <li>
                                        <strong>Kode</strong>
                                        <span class="st-pill st-pill--info">{{ $reservation->promo->promo_code }}</span>
                                    </li>
                                    @if($resPromoScope)
                                        <li>
                                            <strong>Scope</strong>
                                            <span class="st-pill st-pill--plain {{ $resPromoScope['has_reward_program'] ? 'st-pill--info' : 'st-pill--success' }}">
                                                <i class="fa-solid {{ $resPromoScope['icon'] }}"></i> {{ $resPromoScope['short'] }}
                                            </span>
                                        </li>
                                    @endif
                                    <li>
                                        <strong>Voucher Pasien</strong>
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
    {{-- Style quick-add pasien dipindah ke @section('css') agar ikut ter-render di bare layout (drawer). --}}
    <script src="{{ asset('assets/js/reservation-form.js') }}?v={{ @filemtime(public_path('assets/js/reservation-form.js')) ?: '1' }}"></script>
    <script>
        // Standalone edit: panggil sekali setelah DOM & plugin siap.
        // (Drawer Fase 1 nanti memanggil window.initReservationForm() lagi setelah inject.)
        $(function () { window.initReservationForm(); });
    </script>
@endsection