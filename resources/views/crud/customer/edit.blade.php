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

        {{-- Journey Timeline --}}
        <div class="st-card">
            <div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-route"></i> Timeline Perjalanan Pasien</h6>
                @if(isset($timeline) && $timeline->isNotEmpty())
                    <div style="position:relative; padding-left:30px; border-left:3px solid var(--divider-soft);">
                        @foreach($timeline as $event)
                            @php
                                $label = $event->journey_label;
                                $colors = [
                                    'Stat_New'   => ['bg' => '#E8F0F5', 'color' => '#1E5A8C', 'icon' => 'fa-user-plus'],
                                    'Stat_Leads' => ['bg' => '#FFF4E1', 'color' => '#B26B00', 'icon' => 'fa-comments'],
                                    'Stat_Rsv'   => ['bg' => '#E8F5E9', 'color' => '#2E7D32', 'icon' => 'fa-calendar-check'],
                                ];
                                $cfg = $colors[$label] ?? ['bg' => '#F5F0EA', 'color' => '#6B4423', 'icon' => 'fa-circle'];
                            @endphp
                            <div style="position:relative; margin-bottom:16px;">
                                <div style="position:absolute; left:-40px; top:4px; width:20px; height:20px; border-radius:50%; background:{{ $cfg['bg'] }}; border:2px solid {{ $cfg['color'] }}; display:flex; align-items:center; justify-content:center;">
                                    <i class="fa {{ $cfg['icon'] }}" style="font-size:9px; color:{{ $cfg['color'] }}"></i>
                                </div>
                                <div style="background:{{ $cfg['bg'] }}; border-radius:8px; padding:10px 14px; display:inline-block;">
                                    <strong style="color:{{ $cfg['color'] }}">{{ $label }}</strong>
                                    <span style="font-size:0.8rem; margin-left:8px; color:var(--text-subtle)">
                                        {{ \Carbon\Carbon::parse($event->created_at)->format('d/m/Y H:i') }}
                                    </span>
                                    @if($event->raw_contact_id && !$event->raw_customer_id)
                                        <span class="st-pill st-pill--light ml-1" style="font-size:0.7rem">via Contact</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="st-muted">Belum ada data perjalanan.</p>
                @endif
            </div>
        </div>
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
