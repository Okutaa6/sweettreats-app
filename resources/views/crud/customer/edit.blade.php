@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Detail Pasien</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        {{-- Summary Cards --}}
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="st-dash-card d-flex align-items-center">
                    <div class="st-dash-card__icon st-dash-card__icon--info mr-3"><i class="fa fa-calendar"></i></div>
                    <div>
                        <div class="st-dash-card__value">{{ $totalKunjungan }}</div>
                        <div class="st-dash-card__label">Total Reservasi</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="st-dash-card d-flex align-items-center">
                    <div class="st-dash-card__icon st-dash-card__icon--success mr-3"><i class="fa fa-check-circle"></i></div>
                    <div>
                        <div class="st-dash-card__value">{{ $totalSelesai }}</div>
                        <div class="st-dash-card__label">Kunjungan Selesai</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="st-dash-card d-flex align-items-center">
                    <div class="st-dash-card__icon st-dash-card__icon--warning mr-3"><i class="fa fa-coins"></i></div>
                    <div>
                        <div class="st-dash-card__value">Rp {{ number_format($totalSpending, 0, ',', '.') }}</div>
                        <div class="st-dash-card__label">Total Spending</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Data Form --}}
        <div class="st-card mb-3">
            <div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-user"></i> Data Pasien</h6>
                <form method="post" action="{{ route('rawcustomer.update', $customer->id) }}">
                    @csrf
                    @method('PUT')

                    <a href="{{ route('rawcustomer') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                        <i class="fa fa-arrow-left"></i> Kembali
                    </a>

                    <div class="row">
                        {{-- Kolom Kiri --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight:600;">Nama <span class="text-danger">*</span></label>
                                <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                    type="text" name="name" value="{{ old('name', $customer->name) }}" required />
                                @if ($errors->has('name'))
                                    <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                @endif
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">No. WhatsApp <span class="text-danger">*</span></label>
                                <input class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}"
                                    type="text" name="phone" value="{{ old('phone', $customer->phone) }}" required />
                                @if ($errors->has('phone'))
                                    <div class="invalid-feedback">{{ $errors->first('phone') }}</div>
                                @endif
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Email</label>
                                <input class="form-control" type="email" name="email"
                                    value="{{ old('email', $customer->email) }}" />
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Jenis Kelamin</label>
                                <select class="form-control" name="jenis_kelamin">
                                    <option value="">-- Pilih --</option>
                                    <option value="L" {{ old('jenis_kelamin', $customer->jenis_kelamin) == 'L' ? 'selected' : '' }}>Laki-laki</option>
                                    <option value="P" {{ old('jenis_kelamin', $customer->jenis_kelamin) == 'P' ? 'selected' : '' }}>Perempuan</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Tempat Lahir</label>
                                <input class="form-control" type="text" name="tempat_lahir"
                                    value="{{ old('tempat_lahir', $customer->tempat_lahir) }}" />
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Tanggal Lahir</label>
                                @php
                                    $tgl = ($customer->tanggal_lahir && $customer->tanggal_lahir != '0000-00-00') ? $customer->tanggal_lahir : null;
                                @endphp
                                <input class="form-control" type="date" id="tanggal_lahir" name="tanggal_lahir"
                                    value="{{ old('tanggal_lahir', $tgl) }}" />
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Usia</label>
                                <input class="form-control" type="text" id="usia" readonly
                                    placeholder="Otomatis dari tanggal lahir"
                                    value="{{ $tgl ? \Carbon\Carbon::parse($tgl)->age . ' tahun' : '' }}" />
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">NIK</label>
                                <input class="form-control" type="text" name="nik"
                                    value="{{ old('nik', $customer->nik) }}" placeholder="16 digit NIK" maxlength="20" />
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Alamat</label>
                                <textarea class="form-control" name="alamat" rows="3">{{ old('alamat', $customer->alamat) }}</textarea>
                            </div>
                        </div>

                        {{-- Kolom Kanan --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight:600;">Tanggal Daftar</label>
                                <input class="form-control" type="date" name="tanggal_daftar"
                                    value="{{ old('tanggal_daftar', $customer->tanggal_daftar) }}" />
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Pasien Baru/Lama?</label>
                                <select class="form-control" name="pasien_baru_lama">
                                    <option value="">-- Pilih --</option>
                                    <option value="baru" {{ old('pasien_baru_lama', $customer->pasien_baru_lama) == 'baru' ? 'selected' : '' }}>Pasien Baru</option>
                                    <option value="lama" {{ old('pasien_baru_lama', $customer->pasien_baru_lama) == 'lama' ? 'selected' : '' }}>Pasien Lama</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Cabang</label>
                                @if($userBranch)
                                    <input class="form-control" type="text" value="{{ $userBranch->name }}" readonly />
                                    <input type="hidden" name="raw_branch_id" value="{{ $userBranch->id }}" />
                                @else
                                    <select class="form-control" name="raw_branch_id">
                                        <option value="">-- Pilih Cabang --</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}"
                                                {{ old('raw_branch_id', $customer->raw_branch_id) == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Dokter</label>
                                <select class="form-control" name="raw_doctor_id">
                                    <option value="">-- Pilih Dokter --</option>
                                    @foreach ($doctors as $doctor)
                                        <option value="{{ $doctor->id }}"
                                            {{ old('raw_doctor_id', $customer->raw_doctor_id) == $doctor->id ? 'selected' : '' }}>
                                            {{ $doctor->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Perawatan</label>
                                <select class="form-control" name="raw_doc_service_id">
                                    <option value="">-- Pilih Perawatan --</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}"
                                            {{ old('raw_doc_service_id', $customer->raw_doc_service_id) == $service->id ? 'selected' : '' }}>
                                            {{ $service->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Kode Promo / Referral</label>
                                <select class="form-control" name="raw_promo_id">
                                    <option value="">-- Pilih Promo --</option>
                                    @foreach ($promos as $promo)
                                        <option value="{{ $promo->id }}"
                                            {{ old('raw_promo_id', $customer->raw_promo_id) == $promo->id ? 'selected' : '' }}>
                                            {{ $promo->promo_code }}
                                            @if($promo->affiliate) ({{ $promo->affiliate->name }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Referensi</label>
                                <input class="form-control" type="text" name="source_app"
                                    value="{{ old('source_app', $customer->source_app) }}" readonly />
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Catatan</label>
                                <textarea class="form-control" name="catatan" rows="4"
                                    placeholder="Catatan internal tentang pasien...">{{ old('catatan', $customer->catatan) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <hr style="border-color:var(--divider-soft)">
                    <button class="st-btn-brand" type="submit">
                        <i class="fa fa-save"></i> Simpan
                    </button>
                    <button type="button" class="st-btn-outline-brown" onclick="deleteData()">
                        <i class="fa fa-trash"></i> Hapus
                    </button>
                </form>
                <form action="{{ route('rawcustomer.destroy', $customer->id) }}" method="POST" id="delete">
                    @csrf @method('DELETE')
                </form>
            </div>
        </div>

        {{-- Riwayat Reservasi --}}
        <div class="st-card mb-3">
            <div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-history"></i> Riwayat Reservasi</h6>
                @if($customer->reservations->isEmpty())
                    <p class="st-muted">Belum ada riwayat reservasi.</p>
                @else
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--compact">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Cabang</th>
                                    <th>Dokter</th>
                                    <th>Perawatan</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th width="5%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customer->reservations as $i => $res)
                                    @php
                                        $statusInfo = $res->status_info;
                                        $serviceNames = $res->services->map(fn($s) => $s->docService?->name)->filter()->implode(', ');
                                        $pillMap = ['pending'=>'warning','confirmed'=>'info','completed'=>'success','cancelled'=>'danger','waitlist'=>'neutral'];
                                        $pillCls = $pillMap[$res->status] ?? 'neutral';
                                    @endphp
                                    <tr>
                                        <td class="st-num">{{ $i + 1 }}</td>
                                        <td nowrap>
                                            <span class="st-date">{{ \Carbon\Carbon::parse($res->date)->format('d/m/Y') }}</span>
                                            @if($res->start_hour)
                                                <br><small class="text-muted">{{ substr($res->start_hour, 0, 5) }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $res->branch?->name ?? '-' }}</td>
                                        <td>{{ $res->doctor?->name ?? '-' }}</td>
                                        <td>{{ $serviceNames ?: '-' }}</td>
                                        <td nowrap>
                                            <span class="st-pill st-pill--{{ $pillCls }}">
                                                {{ $statusInfo['label'] ?? $res->status }}
                                            </span>
                                        </td>
                                        <td nowrap class="st-tnum">
                                            @if($res->total_reservasi)
                                                Rp {{ number_format($res->total_reservasi, 0, ',', '.') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('rawreservation.edit', $res->id) }}" class="st-icon-btn" title="Detail Reservasi">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Riwayat & Rekam Medis — tabbed: Timeline + Rekam Medis --}}
        @php
            $branchNames = [
                '29957' => 'Bintaro', '29958' => 'Sunter', '29959' => 'Kalideres',
                '30439' => 'Cinere',  '30941' => 'Pamulang',
            ];
            $cleanText = function ($v) {
                if ($v === null) return null;
                $v = str_replace(['<br>', '<br/>', '<br />'], "\n", (string) $v);
                return trim(html_entity_decode(strip_tags($v)));
            };
            $mrCount = is_countable($medicalRecords ?? null) ? count($medicalRecords) : 0;
            $months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        @endphp
        <style>
            /* Scoped style untuk Riwayat & Rekam Medis */
            .rmx-card { background:#fff; border-radius:12px; }
            .rmx-tabs { display:flex; gap:4px; border-bottom:1.5px solid #EFE6DA; margin:6px 0 16px; }
            .rmx-tab { background:none; border:none; padding:10px 16px; cursor:pointer;
                       font-size:0.85rem; font-weight:500; color:#8B6F5C;
                       border-bottom:2.5px solid transparent; margin-bottom:-1.5px;
                       display:inline-flex; align-items:center; gap:6px; transition:color .15s; }
            .rmx-tab:hover { color:#6B4423; }
            .rmx-tab--on { color:#461500; font-weight:700; border-bottom-color:#461500; }
            .rmx-tab__pill { background:#F5EFEA; color:#6B4423; font-size:0.7rem;
                             padding:1px 8px; border-radius:9px; font-weight:600; }
            .rmx-tab--on .rmx-tab__pill { background:#461500; color:#fff; }

            /* TIMELINE */
            .rmx-tl { position:relative; padding-left:26px; border-left:2.5px solid #EFE6DA; }
            .rmx-tl__ev { position:relative; margin-bottom:12px; }
            .rmx-tl__dot { position:absolute; left:-37px; top:8px; width:18px; height:18px;
                           border-radius:50%; border:2.5px solid; display:flex;
                           align-items:center; justify-content:center; background:#fff; }
            .rmx-tl__dot i { font-size:8px; }
            .rmx-tl__box { display:block; padding:9px 13px; border-radius:7px;
                           border-left:3.5px solid; background:#FAF7F4; }
            .rmx-tl__head { display:flex; align-items:center; gap:10px; flex-wrap:wrap;
                            font-size:0.84rem; }
            .rmx-tl__title { font-weight:700; }
            .rmx-tl__date { color:#8B6F5C; font-size:0.77rem; font-weight:500; }
            .rmx-tl__money { font-weight:700; font-size:0.78rem; }
            .rmx-tl__tag { background:#fff; padding:1px 8px; border-radius:9px;
                           font-size:0.7rem; border:1px solid #EBE0D1; color:#6B4423; }
            .rmx-tl__detail { font-size:0.77rem; color:#6B4423; margin-top:3px; line-height:1.45;
                              display:flex; align-items:flex-start; gap:6px; }
            .rmx-tl__detail-text { flex:1; overflow:hidden; text-overflow:ellipsis;
                                   display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; }
            .rmx-tl__jump { white-space:nowrap; font-weight:700; text-decoration:none;
                            font-size:0.75rem; padding:1px 7px; border-radius:6px;
                            background:#fff; border:1px solid currentColor; }
            .rmx-tl__jump:hover { background:currentColor; color:#fff !important; }

            /* REKAM MEDIS — card-in-card */
            .rmx-list { display:flex; flex-direction:column; gap:7px; }
            .rmx-r { background:#fff; border:1px solid #EBE0D1; border-radius:9px;
                     overflow:hidden; transition:box-shadow .15s, border-color .15s; }
            .rmx-r:hover { border-color:#D0BFA8; box-shadow:0 1px 5px rgba(70,21,0,0.06); }
            .rmx-r[open] { border-color:#461500; box-shadow:0 3px 12px rgba(70,21,0,0.1); }
            .rmx-r__head { cursor:pointer; padding:11px 14px; list-style:none; user-select:none; }
            .rmx-r__head::-webkit-details-marker { display:none; }
            .rmx-r__line { display:flex; align-items:center; gap:9px; flex-wrap:wrap; }
            .rmx-r__date { display:inline-flex; align-items:center; gap:4px;
                           font-size:0.8rem; font-weight:700; color:#461500;
                           background:#F5EFEA; padding:3px 10px; border-radius:13px; }
            .rmx-r__date i { font-size:0.72rem; opacity:0.7; }
            .rmx-r__doc { font-size:0.86rem; font-weight:600; color:#2D1810; }
            .rmx-r__sep { color:#C2A98F; font-weight:600; }
            .rmx-r__branch { font-size:0.81rem; color:#6B4423; }
            .rmx-r__code { background:#FAF5F2; color:#8B6F5C; font-family:ui-monospace,monospace;
                           padding:1px 7px; border-radius:6px; font-size:0.7rem;
                           border:1px solid #EBE0D1; letter-spacing:0.02em; }
            .rmx-r__new { background:#FFF4E1; color:#B26B00; padding:2px 9px; border-radius:9px;
                          font-size:0.66rem; font-weight:700; border:1px solid #F0D9A5;
                          letter-spacing:0.03em; }
            .rmx-r__chev { margin-left:auto; color:#B0A597; font-size:0.78rem;
                           transition:transform .2s; }
            .rmx-r[open] .rmx-r__chev { transform:rotate(180deg); color:#461500; }
            .rmx-r__summary { font-size:0.78rem; color:#5D4A3D; line-height:1.5;
                              margin-top:5px; padding-left:1px;
                              display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical;
                              overflow:hidden; }
            .rmx-r__summary i { color:#8B6F5C; font-size:0.72rem; margin-right:2px; }
            .rmx-r__summary strong { color:#461500; font-weight:600; }
            .rmx-r__body { padding:0 14px 14px; border-top:1px solid #F0E5D9;
                           background:linear-gradient(180deg, #FAF7F4 0%, #fff 70%); }

            .rmx-sec { margin-top:13px; }
            .rmx-sec__title { font-size:0.7rem; font-weight:700; color:#461500;
                              text-transform:uppercase; letter-spacing:0.06em;
                              display:flex; align-items:center; gap:6px;
                              padding-bottom:6px; margin-bottom:7px;
                              border-bottom:1px solid #EBE0D1; }
            .rmx-sec__title i { font-size:0.85rem; opacity:0.85; }
            .rmx-f { display:grid; grid-template-columns:110px 1fr; gap:10px;
                     margin:5px 0; font-size:0.83rem; align-items:start; }
            .rmx-f__l { color:#6B4423; font-weight:600; padding-top:1px; }
            .rmx-f__v { color:#2D1810; white-space:pre-line; }
            .rmx-f__v--empty { color:#B0A597; font-style:italic; }

            .rmx-proc { list-style:none; padding:0; margin:4px 0 0; }
            .rmx-proc li { display:flex; align-items:center; gap:8px;
                           padding:5px 11px; margin:4px 0;
                           background:#fff; border:1px solid #EBE0D1; border-radius:6px;
                           font-size:0.82rem; color:#2D1810; }
            .rmx-proc li::before { content:'✓'; color:#1f7a3d; font-weight:700; font-size:0.85rem; }

            .rmx-vital { display:flex; flex-wrap:wrap; gap:7px; margin-top:3px; }
            .rmx-vital__c { background:#fff; border:1px solid #EBE0D1; border-radius:6px;
                            padding:4px 11px; font-size:0.81rem; color:#2D1810; }
            .rmx-vital__c strong { color:#6B4423; font-weight:600; margin-right:4px; }

            .rmx-empty { padding:22px 14px; text-align:center; color:#8B6F5C;
                         background:#FAF7F4; border:1.5px dashed #E0CFB8; border-radius:9px;
                         font-size:0.86rem; }
            .rmx-empty__hint { font-size:0.76rem; color:#B0A597; margin-top:3px; }

            @media (max-width: 580px) {
                .rmx-f { grid-template-columns:1fr; gap:1px; }
                .rmx-f__l { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em; }
            }
        </style>
        <div class="st-card">
            <div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-route"></i> Riwayat Perjalanan Pasien</h6>

                {{-- Tab nav --}}
                <div class="rmx-tabs">
                    <button type="button" class="rmx-tab rmx-tab--on" data-rm-tab="timeline">
                        <i class="fa fa-route"></i> Timeline
                        <span class="rmx-tab__pill">{{ count($timeline) }}</span>
                    </button>
                    <button type="button" class="rmx-tab" data-rm-tab="medical">
                        <i class="fa fa-notes-medical"></i> Rekam Medis
                        <span class="rmx-tab__pill">{{ $mrCount }}</span>
                    </button>
                </div>

                {{-- ── TAB 1: TIMELINE ─────────────────────────────────── --}}
                <div data-rm-content="timeline">
                    @if(!empty($timeline))
                        <div class="rmx-tl">
                            @foreach($timeline as $event)
                                <div class="rmx-tl__ev">
                                    <div class="rmx-tl__dot" style="border-color:{{ $event->color }};">
                                        <i class="fa {{ $event->icon }}" style="color:{{ $event->color }};"></i>
                                    </div>
                                    <div class="rmx-tl__box" style="border-left-color:{{ $event->color }};">
                                        <div class="rmx-tl__head">
                                            <span class="rmx-tl__title" style="color:{{ $event->color }};">{{ $event->title }}</span>
                                            <span class="rmx-tl__date">{{ $event->date->format('d M Y') }}</span>
                                            @if($event->amount !== null)
                                                <span class="rmx-tl__money" style="color:{{ $event->color }};">Rp {{ number_format((float) $event->amount, 0, ',', '.') }}</span>
                                            @endif
                                            @if($event->tag)
                                                <span class="rmx-tl__tag">{{ $event->tag }}</span>
                                            @endif
                                        </div>
                                        @if($event->detail)
                                            <div class="rmx-tl__detail">
                                                <span class="rmx-tl__detail-text">{{ $event->detail }}</span>
                                                @if(($event->type ?? null) === 'treatment' && !empty($event->medical))
                                                    <a href="#mr-{{ $event->medical->record_id }}"
                                                       data-rm-jump="{{ $event->medical->record_id }}"
                                                       class="rmx-tl__jump"
                                                       style="color:{{ $event->color }};">
                                                        Detail →
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="st-muted">Belum ada data perjalanan.</p>
                    @endif
                </div>

                {{-- ── TAB 2: REKAM MEDIS ──────────────────────────────── --}}
                <div data-rm-content="medical" style="display:none;">
                    @if($mrCount === 0)
                        <div class="rmx-empty">
                            <i class="fa fa-notes-medical" style="font-size:1.4rem; color:#C2A98F; margin-bottom:6px; display:block;"></i>
                            Belum ada rekam medis Omnicare
                            <div class="rmx-empty__hint">Pasien belum pernah datang atau belum link ke Omnicare</div>
                        </div>
                    @else
                        <div class="rmx-list">
                            @foreach($medicalRecords as $rec)
                                @php
                                    $dt = \Carbon\Carbon::parse($rec->date);
                                    $dateLabel = $dt->format('d') . ' ' . $months[(int)$dt->format('n') - 1] . ' ' . $dt->format('Y');
                                    $timeLabel = $rec->time_in ? substr($rec->time_in, 0, 5) : '';
                                    $branchLbl = $branchNames[$rec->branch_id] ?? ('Cabang ' . $rec->branch_id);
                                    $clinicalSummary = \App\Support\OmnicareEncounters::buildClinicalSummary($rec);
                                    $hasClinical = !empty($rec->chief_complaint) || !empty($rec->subjective)
                                                || !empty($rec->objective) || !empty($rec->diagnose_note);
                                    $hasVitals = ($rec->vs_blood_pressure ?? null)
                                              || (!empty($rec->vs_weight) && $rec->vs_weight !== '0')
                                              || (!empty($rec->vs_temperature) && $rec->vs_temperature !== '0');
                                    $rxClean = $cleanText($rec->prescription ?? null);
                                    $hasRx = $rxClean !== null && $rxClean !== '' && trim(str_replace(';', '', $rxClean)) !== '';
                                    $procList = !empty($rec->procedures)
                                        ? array_filter(array_map('trim', explode(';', $rec->procedures)))
                                        : [];
                                @endphp
                                <details id="mr-{{ $rec->record_id }}" class="rmx-r">
                                    <summary class="rmx-r__head">
                                        <div class="rmx-r__line">
                                            <span class="rmx-r__date">
                                                <i class="fa fa-calendar-day"></i>
                                                {{ $dateLabel }}@if($timeLabel) · {{ $timeLabel }}@endif
                                            </span>
                                            <span class="rmx-r__doc">drg. {{ $rec->doctor ?: '—' }}</span>
                                            <span class="rmx-r__sep">·</span>
                                            <span class="rmx-r__branch">{{ $branchLbl }}</span>
                                            @if(!empty($rec->booking_code))
                                                <span class="rmx-r__code">{{ $rec->booking_code }}</span>
                                            @endif
                                            @if($rec->is_new_patient)
                                                <span class="rmx-r__new">PASIEN BARU</span>
                                            @endif
                                            <span class="rmx-r__chev"><i class="fa fa-chevron-down"></i></span>
                                        </div>
                                        @if($clinicalSummary !== '')
                                            <div class="rmx-r__summary">{{ $clinicalSummary }}</div>
                                        @endif
                                    </summary>

                                    <div class="rmx-r__body">
                                        @if($hasClinical)
                                            <div class="rmx-sec">
                                                <div class="rmx-sec__title"><i class="fa fa-comment-medical"></i> Anamnesis &amp; Pemeriksaan</div>
                                                @if(!empty($rec->chief_complaint))
                                                    <div class="rmx-f"><span class="rmx-f__l">Keluhan</span><span class="rmx-f__v">{{ $cleanText($rec->chief_complaint) }}</span></div>
                                                @endif
                                                @if(!empty($rec->subjective))
                                                    <div class="rmx-f"><span class="rmx-f__l">Subjektif</span><span class="rmx-f__v">{{ $cleanText($rec->subjective) }}</span></div>
                                                @endif
                                                @if(!empty($rec->objective))
                                                    <div class="rmx-f"><span class="rmx-f__l">Objektif</span><span class="rmx-f__v">{{ $cleanText($rec->objective) }}</span></div>
                                                @endif
                                            </div>
                                        @endif

                                        @if(!empty($rec->diagnose_note) || !empty($rec->treatment_plan_note) || !empty($rec->plan_note))
                                            <div class="rmx-sec">
                                                <div class="rmx-sec__title"><i class="fa fa-stethoscope"></i> Diagnosa &amp; Rencana</div>
                                                @if(!empty($rec->diagnose_note))
                                                    <div class="rmx-f"><span class="rmx-f__l">Diagnosa</span><span class="rmx-f__v">{{ $cleanText($rec->diagnose_note) }}</span></div>
                                                @endif
                                                @if(!empty($rec->treatment_plan_note))
                                                    <div class="rmx-f"><span class="rmx-f__l">Rencana</span><span class="rmx-f__v">{{ $cleanText($rec->treatment_plan_note) }}</span></div>
                                                @endif
                                                @if(!empty($rec->plan_note))
                                                    <div class="rmx-f"><span class="rmx-f__l">Catatan</span><span class="rmx-f__v">{{ $cleanText($rec->plan_note) }}</span></div>
                                                @endif
                                            </div>
                                        @endif

                                        @if(!empty($procList))
                                            <div class="rmx-sec">
                                                <div class="rmx-sec__title"><i class="fa fa-syringe"></i> Tindakan / Prosedur <span style="color:#8B6F5C; font-weight:600; margin-left:auto;">{{ count($procList) }} item</span></div>
                                                <ul class="rmx-proc">
                                                    @foreach($procList as $proc)
                                                        <li>{{ $proc }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if($hasRx)
                                            <div class="rmx-sec">
                                                <div class="rmx-sec__title"><i class="fa fa-prescription-bottle"></i> Resep</div>
                                                <div class="rmx-f"><span class="rmx-f__l">Obat</span><span class="rmx-f__v">{{ $rxClean }}</span></div>
                                            </div>
                                        @endif

                                        @if($hasVitals)
                                            <div class="rmx-sec">
                                                <div class="rmx-sec__title"><i class="fa fa-heart-pulse"></i> Tanda Vital</div>
                                                <div class="rmx-vital">
                                                    @if(($rec->vs_blood_pressure ?? null))<div class="rmx-vital__c"><strong>BP</strong>{{ $rec->vs_blood_pressure }}</div>@endif
                                                    @if(!empty($rec->vs_weight) && $rec->vs_weight !== '0')<div class="rmx-vital__c"><strong>BB</strong>{{ $rec->vs_weight }} kg</div>@endif
                                                    @if(!empty($rec->vs_height) && $rec->vs_height !== '0')<div class="rmx-vital__c"><strong>TB</strong>{{ $rec->vs_height }} cm</div>@endif
                                                    @if(!empty($rec->vs_temperature) && $rec->vs_temperature !== '0')<div class="rmx-vital__c"><strong>Suhu</strong>{{ $rec->vs_temperature }} °C</div>@endif
                                                    @if(!empty($rec->vs_pulse) && $rec->vs_pulse !== '0')<div class="rmx-vital__c"><strong>Nadi</strong>{{ $rec->vs_pulse }}</div>@endif
                                                    @if(!empty($rec->vs_respiration) && $rec->vs_respiration !== '0')<div class="rmx-vital__c"><strong>Resp</strong>{{ $rec->vs_respiration }}</div>@endif
                                                </div>
                                            </div>
                                        @endif

                                        @if(!$hasClinical && empty($rec->treatment_plan_note) && empty($procList) && !$hasRx && !$hasVitals)
                                            <div style="padding:14px 0; text-align:center; color:#B0A597; font-size:0.82rem; font-style:italic;">
                                                Tidak ada catatan klinis (kontrol singkat)
                                            </div>
                                        @endif
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        {{-- Tab JS + jump from timeline → medical accordion --}}
        <script>
        (function() {
            const tabs = document.querySelectorAll('.rmx-tab');
            const contents = document.querySelectorAll('[data-rm-content]');
            function activate(name) {
                tabs.forEach(t => t.classList.toggle('rmx-tab--on', t.dataset.rmTab === name));
                contents.forEach(c => { c.style.display = c.dataset.rmContent === name ? 'block' : 'none'; });
            }
            tabs.forEach(t => t.addEventListener('click', () => activate(t.dataset.rmTab)));
            document.querySelectorAll('[data-rm-jump]').forEach(link => {
                link.addEventListener('click', e => {
                    e.preventDefault();
                    activate('medical');
                    const id = 'mr-' + link.dataset.rmJump;
                    setTimeout(() => {
                        const d = document.getElementById(id);
                        if (d) { d.open = true; d.scrollIntoView({behavior:'smooth', block:'center'}); }
                    }, 80);
                });
            });
        })();
        </script>
    </div>
@endsection

@section('js')
<script>
    document.getElementById('tanggal_lahir').addEventListener('change', function () {
        const dob = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        document.getElementById('usia').value = isNaN(age) || age < 0 ? '' : age + ' tahun';
    });

    function deleteData() {
        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Data pasien akan dihapus. Reservasi terkait tetap tersimpan tapi pasien-nya akan kosong.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#461500',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (result.isConfirmed) { $('#delete').submit(); }
        })
    }
</script>
@endsection
