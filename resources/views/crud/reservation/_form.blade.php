{{--
    _form.blade.php — markup form Edit/Tambah Reservasi (partial bersama).
    Dipakai edit.blade (mode='edit') & new.blade (mode='add') via @include.
    Variabel di-inherit dari view induk (controller edit()/create()): $reservation,
    $doctors, $branches, $customers, $services, $promos, $marketingPromos, $totalDuration.

    SEMUA id/name/class = HOOK untuk public/assets/js/reservation-form.js + submit
    AJAX di list.blade. JANGAN ubah tanpa update JS-nya.
    Fase 2b: layout design 660px (1 kolom, section gold-bar). Pasien = select2 (No.Telp
    bebas dari design dibuang; nomor ikut dari pasien terpilih).
--}}
@php
    $isEdit    = ($mode ?? 'add') === 'edit';
    $selDoctor = $isEdit ? $reservation->raw_doctor_id : old('raw_doctor_id');
    $selBranch = $isEdit ? $reservation->raw_branch_id : old('raw_branch_id');
    $valDate   = $isEdit ? old('date', $reservation->date ? $reservation->date->format('Y-m-d') : '') : old('date');
    $valStart  = $isEdit ? old('start_hour', $reservation->start_hour) : old('start_hour');
    $valEnd    = $isEdit ? old('end_hour', $reservation->end_hour) : old('end_hour');
@endphp

        <form method="post" action="{{ $isEdit ? route('rawreservation.update', $reservation->id) : route('rawreservation.store') }}" id="rrReservationForm">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="rrf">
                {{-- ── INFORMASI RESERVASI ── --}}
                <section class="rrf-sec">
                    <div class="rrf-sec__title"><span class="rrf-sec__bar"></span>Informasi Reservasi</div>
                    <div class="rrf-grid">
                        <div class="rrf-col">
                            <label class="rrf-lbl">Pasien</label>
                            @if($isEdit && !$reservation->customer)
                                <div class="st-alert st-alert--warning st-alert--compact">
                                    <i class="fa fa-exclamation-triangle st-alert__icon"></i>
                                    <div class="st-alert__body">Pasien telah dihapus. Pilih pasien pengganti di bawah.</div>
                                </div>
                            @endif
                            <select class="form-control {{ $errors->has('raw_customer_id') ? 'is-invalid' : '' }}" name="raw_customer_id" id="customerSelect">
                                @if($isEdit && $reservation->customer)
                                    <option value="{{ $reservation->customer->id }}" selected>
                                        {{ $reservation->customer->name }} ({{ $reservation->customer->phone ?: '-' }})
                                    </option>
                                @elseif($isEdit)
                                    <option value="">- Cari Pasien -</option>
                                @else
                                    <option value="">- Cari Pasien -</option>
                                    @foreach ($customers as $customer)
                                        @if(old('raw_customer_id') == $customer->id)
                                            <option value="{{ $customer->id }}" selected>
                                                {{ $customer->name }} ({{ $customer->phone ?: '-' }})
                                            </option>
                                        @endif
                                    @endforeach
                                @endif
                            </select>
                            @if ($errors->has('raw_customer_id'))
                                <div class="invalid-feedback">{{ $errors->first('raw_customer_id') }}</div>
                            @endif
                            <small class="text-muted rrf-hint">1 nomor bisa punya banyak pasien (mis. anak / pasangan).</small>
                        </div>
                        <div class="rrf-col">
                            <label class="rrf-lbl">Dokter</label>
                            <select id="doctorSelect2" class="select2 form-control {{ $errors->has('raw_doctor_id') ? 'is-invalid' : '' }}" name="raw_doctor_id">
                                <option value="">- Pilih Doctor -</option>
                                @foreach ($doctors as $doctor)
                                    <option value="{{ $doctor->id }}" {{ $selDoctor == $doctor->id ? 'selected' : '' }}>
                                        {{ $doctor->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($errors->has('raw_doctor_id'))
                                <div class="invalid-feedback">{{ $errors->first('raw_doctor_id') }}</div>
                            @endif
                        </div>

                        <div class="rrf-col rrf-col--full">
                            <div class="qp-trigger-wrap">
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
                                        @if($isEdit)
                                            <div class="col-md-6 form-group">
                                                <label>Nama Pasien <span class="qp-req">*</span></label>
                                                <input type="text" id="qpName" class="form-control" placeholder="Nama lengkap pasien">
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label>No. HP <span class="qp-req">*</span></label>
                                                <input type="text" id="qpPhone" class="form-control" placeholder="08xxxxxxxxxx">
                                            </div>
                                        @else
                                            <div class="col-md-12 form-group">
                                                <label>Nama Pasien <span class="qp-req">*</span></label>
                                                <input type="text" id="qpName" class="form-control" placeholder="Nama lengkap pasien">
                                            </div>
                                        @endif
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

                        <div class="rrf-col">
                            <label class="rrf-lbl">Cabang</label>
                            <select id="branchSelect2" class="select2 form-control {{ $errors->has('raw_branch_id') ? 'is-invalid' : '' }}" name="raw_branch_id">
                                <option value="">- Pilih Cabang -</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ $selBranch == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($errors->has('raw_branch_id'))
                                <div class="invalid-feedback">{{ $errors->first('raw_branch_id') }}</div>
                            @endif
                            @if($isEdit)
                            <div id="branchChangeNotice" class="st-alert st-alert--warning mt-2 mb-0" style="display:none;">
                                <i class="fa fa-exclamation-triangle st-alert__icon"></i>
                                <div class="st-alert__body"><strong>Perhatian!</strong> Jika cabang dipindahkan, data pasien tidak akan terlihat atau ditransfer ke cabang baru.</div>
                            </div>
                            @endif
                        </div>
                        <div class="rrf-col">
                            <label class="rrf-lbl">Tanggal</label>
                            <input class="form-control {{ $errors->has('date') ? 'is-invalid' : '' }}" type="text" id="datePickerReschedule" name="date" value="{{ $valDate }}" placeholder="Pilih tanggal..." readonly />
                            <small id="datePickerInfo" class="text-muted">Pilih dokter dan cabang terlebih dahulu.</small>
                            @if ($errors->has('date'))
                                <div class="invalid-feedback">{{ $errors->first('date') }}</div>
                            @endif
                        </div>

                        <div class="rrf-col rrf-col--full">
                            <label class="rrf-lbl">Jam</label>
                            <div id="slotPickerWrap">
                                <button type="button" class="st-btn-outline" id="loadSlotsBtn" onclick="loadSlots()" style="margin-bottom:8px;">
                                    <i class="fa fa-clock"></i> Cek Slot Tersedia
                                </button>
                                @if($isEdit)
                                <div class="st-slot-info" id="selectedTimeDisplay">
                                    <i class="fa fa-bookmark"></i>
                                    <span class="st-slot-info-label">Jadwal aktif</span>
                                    <span class="st-slot-info-value" id="currentTime">{{ \Illuminate\Support\Str::substr($reservation->start_hour, 0, 5) }} – {{ \Illuminate\Support\Str::substr($reservation->end_hour, 0, 5) }}</span>
                                </div>
                                @else
                                <div class="st-slot-info" id="selectedTimeDisplay" style="{{ old('start_hour') ? '' : 'display:none;' }}">
                                    <i class="fa fa-bookmark"></i>
                                    <span class="st-slot-info-label">Jadwal dipilih</span>
                                    <span class="st-slot-info-value" id="currentTime">{{ old('start_hour') ? substr(old('start_hour'),0,5).' – '.substr(old('end_hour'),0,5) : '--:-- – --:--' }}</span>
                                </div>
                                @endif
                                <input type="hidden" name="start_hour" id="startHourInput" value="{{ $valStart }}" />
                                <input type="hidden" name="end_hour" id="endHourInput" value="{{ $valEnd }}" />
                                @if ($errors->has('start_hour'))
                                    <div class="invalid-feedback" style="display:block">{{ $errors->first('start_hour') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ── PILIH SLOT JAM ── (grid + legend di-render reservation-form.js ke #slotsContainer) --}}
                <section class="rrf-sec">
                    <label class="rrf-lbl">Pilih Slot Jam</label>
                    <div id="slotsContainer"></div>
                </section>

                {{-- ── LAYANAN PERAWATAN ── --}}
                <section class="rrf-sec">
                    <div class="rrf-sec__title"><span class="rrf-sec__bar"></span>Layanan Perawatan</div>
                    @if($isEdit && $reservation->lainnya_text)
                        <div class="form-group">
                            <label class="rrf-lbl">Keluhan Awal Pasien</label>
                            <div class="st-alert st-alert--warning">
                                <i class="fa fa-comment-dots st-alert__icon"></i>
                                <div class="st-alert__body"><strong>{{ $reservation->lainnya_text }}</strong></div>
                            </div>
                            <small class="text-muted">Ini keluhan yang ditulis pasien saat reservasi online. Tentukan perawatan yang sesuai di bawah ini.</small>
                        </div>
                    @endif

                    <div id="serviceChecklist">
                        @if($isEdit)
                            <span class="text-muted"><i class="fa fa-spinner fa-spin"></i> Memuat layanan...</span>
                        @else
                            <span class="text-muted">Pilih dokter terlebih dahulu untuk melihat layanan yang tersedia</span>
                        @endif
                    </div>
                    <script>
                        @if($isEdit)
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
                        @else
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
                        @endif
                        // Alias bridge — JS lama masih baca dua nilai ini; di-set dari config.
                        window.initialDoctorId  = window.rrFormConfig.initialDoctorId;
                        window.existingServices = window.rrFormConfig.existingServices;
                    </script>
                    <div id="totalDurationInfo" class="st-total-duration" style="display:none;"></div>
                </section>

                @if($isEdit)
                {{-- ── AKSI & STATUS (edit only) ── --}}
                <section class="rrf-sec">
                    <div class="rrf-sec__title"><span class="rrf-sec__bar"></span>Aksi &amp; Status</div>
                    <div class="rrf-grid">
                        <div class="rrf-col rrf-col--full">
                            <label class="rrf-lbl">Status Reservasi</label>
                            <select class="form-control" name="status">
                                @foreach (\App\Models\RawCusReservation::statusOptions() as $key => $opt)
                                    <option value="{{ $key }}" {{ $reservation->status == $key ? 'selected' : '' }}>
                                        {{ $opt['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rrf-col">
                            <label class="rrf-lbl">Promo <small class="text-muted">(dari Marketing)</small></label>
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
                        <div class="rrf-col">
                            <label class="rrf-lbl">Gunakan Kode Referral</label>
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
                        </div>
                        <div class="rrf-col rrf-col--full">
                            <small class="text-muted">
                                <i class="fa fa-info-circle"></i>
                                Kode <strong>Behel</strong> hanya valid untuk Pemasangan Behel.
                                Kode <strong>Non-Behel</strong> hanya untuk Scaling, Whitening, PSA, atau Veneer.
                            </small>
                        </div>
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
                </section>
                @endif

                {{-- ── KETERANGAN TAMBAHAN ── --}}
                <section class="rrf-sec">
                    <label class="rrf-lbl">Keterangan Tambahan</label>
                    @if($isEdit)
                        <textarea class="form-control {{ $errors->has('keterangan') ? 'is-invalid' : '' }}" name="keterangan" rows="4" placeholder="Tambahkan keterangan tambahan jika ada...">{{ old('keterangan', $reservation->keterangan) }}</textarea>
                    @else
                        <textarea class="form-control {{ $errors->has('keterangan') ? 'is-invalid' : '' }}" name="keterangan" rows="4" placeholder="Tambahkan keterangan tambahan jika ada...">{{ old('keterangan') }}</textarea>
                    @endif
                    @if ($errors->has('keterangan'))
                        <div class="invalid-feedback" style="display:block">{{ $errors->first('keterangan') }}</div>
                    @endif
                </section>

                @unless($isEdit)
                {{-- ── WAITING LIST (add only) ── --}}
                <section class="rrf-sec">
                    <div id="waitingBanner" style="display:none; background:#fef9e7; border:1px solid #f5d97b; color:#7a5b00; border-radius:10px; padding:10px 14px; margin-bottom:10px; font-size:13px;">
                        <i class="fa fa-hourglass-half"></i>
                        Jadwal di tanggal ini sudah penuh. Pasien dapat masuk <strong>waiting list</strong> — slot akan dipilih nanti saat ada yang cancel / reschedule.
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="is_waiting" name="is_waiting" value="1" {{ old('is_waiting') ? 'checked' : '' }}>
                        <label class="custom-control-label" for="is_waiting">Masukkan ke waiting list</label>
                    </div>
                </section>
                @endunless
            </div>

            {{-- ── FOOTER ── --}}
            <div class="rrf-foot">
                @if($isEdit)
                    <button class="st-btn-brand rrf-save" type="submit"><i class="fa fa-save"></i> Simpan Perubahan</button>
                    <button type="button" class="st-btn-danger-outline" onclick="deleteReservation()" title="Hapus reservasi"><i class="fa fa-trash"></i></button>
                @else
                    <button class="st-btn-brand rrf-save" type="submit" id="submitReservationBtn"><i class="fa fa-save"></i> <span id="submitReservationLabel">Simpan Reservasi</span></button>
                @endif
            </div>
        </form>

        @if($isEdit)
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
            <div class="rrf-sec" style="margin-top:22px;">
                <div class="rrf-sec__title"><span class="rrf-sec__bar"></span>Komisi Affiliate</div>
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
        @endif
        @endif
