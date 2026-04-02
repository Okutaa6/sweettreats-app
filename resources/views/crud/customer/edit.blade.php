@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a>CRUD</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('rawcustomer') }}">Customer</a></li>
                            <li class="breadcrumb-item active">Detail</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Detail Customer</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        {{-- Summary Cards --}}
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card" style="border-left: 4px solid #4e73df;">
                    <div class="card-body py-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Reservasi</div>
                        <div class="h4 mb-0 font-weight-bold">{{ $totalKunjungan }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card" style="border-left: 4px solid #1cc88a;">
                    <div class="card-body py-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Kunjungan Selesai</div>
                        <div class="h4 mb-0 font-weight-bold">{{ $totalSelesai }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card" style="border-left: 4px solid #f6c23e;">
                    <div class="card-body py-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Spending</div>
                        <div class="h4 mb-0 font-weight-bold">Rp {{ number_format($totalSpending, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Data Form --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Data Customer</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('rawcustomer.update', $customer->id) }}">
                            @csrf
                            @method('PUT')

                            <a href="{{ route('rawcustomer') }}" class="btn btn-sm btn-warning mb-3">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <div class="row">
                                {{-- Kolom Kiri --}}
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Nama <span class="text-danger">*</span></label>
                                        <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                            type="text" name="name" value="{{ old('name', $customer->name) }}" required />
                                        @if ($errors->has('name'))
                                            <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                        @endif
                                    </div>
                                    <div class="form-group">
                                        <label>No. WhatsApp <span class="text-danger">*</span></label>
                                        <input class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}"
                                            type="text" name="phone" value="{{ old('phone', $customer->phone) }}" required />
                                        @if ($errors->has('phone'))
                                            <div class="invalid-feedback">{{ $errors->first('phone') }}</div>
                                        @endif
                                    </div>
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input class="form-control" type="email" name="email"
                                            value="{{ old('email', $customer->email) }}" />
                                    </div>
                                    <div class="form-group">
                                        <label>Jenis Kelamin</label>
                                        <select class="form-control" name="jenis_kelamin">
                                            <option value="">-- Pilih --</option>
                                            <option value="L" {{ old('jenis_kelamin', $customer->jenis_kelamin) == 'L' ? 'selected' : '' }}>Laki-laki</option>
                                            <option value="P" {{ old('jenis_kelamin', $customer->jenis_kelamin) == 'P' ? 'selected' : '' }}>Perempuan</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Tempat Lahir</label>
                                        <input class="form-control" type="text" name="tempat_lahir"
                                            value="{{ old('tempat_lahir', $customer->tempat_lahir) }}" />
                                    </div>
                                    <div class="form-group">
                                        <label>Tanggal Lahir</label>
                                        <input class="form-control" type="date" id="tanggal_lahir" name="tanggal_lahir"
                                            value="{{ old('tanggal_lahir', $customer->tanggal_lahir) }}" />
                                    </div>
                                    <div class="form-group">
                                        <label>Usia</label>
                                        <input class="form-control" type="text" id="usia" readonly
                                            placeholder="Otomatis dari tanggal lahir"
                                            value="{{ $customer->tanggal_lahir ? \Carbon\Carbon::parse($customer->tanggal_lahir)->age . ' tahun' : '' }}" />
                                    </div>
                                    <div class="form-group">
                                        <label>NIK</label>
                                        <input class="form-control" type="text" name="nik"
                                            value="{{ old('nik', $customer->nik) }}" placeholder="16 digit NIK" maxlength="20" />
                                    </div>
                                    <div class="form-group">
                                        <label>Alamat</label>
                                        <textarea class="form-control" name="alamat" rows="3">{{ old('alamat', $customer->alamat) }}</textarea>
                                    </div>
                                </div>

                                {{-- Kolom Kanan --}}
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Tanggal Daftar</label>
                                        <input class="form-control" type="date" name="tanggal_daftar"
                                            value="{{ old('tanggal_daftar', $customer->tanggal_daftar) }}" />
                                    </div>
                                    <div class="form-group">
                                        <label>Pasien Baru/Lama?</label>
                                        <select class="form-control" name="pasien_baru_lama">
                                            <option value="">-- Pilih --</option>
                                            <option value="baru" {{ old('pasien_baru_lama', $customer->pasien_baru_lama) == 'baru' ? 'selected' : '' }}>Pasien Baru</option>
                                            <option value="lama" {{ old('pasien_baru_lama', $customer->pasien_baru_lama) == 'lama' ? 'selected' : '' }}>Pasien Lama</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Cabang</label>
                                        <select class="form-control" name="raw_branch_id">
                                            <option value="">-- Pilih Cabang --</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}"
                                                    {{ old('raw_branch_id', $customer->raw_branch_id) == $branch->id ? 'selected' : '' }}>
                                                    {{ $branch->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Dokter</label>
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
                                        <label>Perawatan</label>
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
                                        <label>Kode Promo / Referral</label>
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
                                        <label>Referensi</label>
                                        <input class="form-control" type="text" name="source_app"
                                            value="{{ old('source_app', $customer->source_app) }}" readonly />
                                    </div>
                                    <div class="form-group">
                                        <label>Catatan</label>
                                        <textarea class="form-control" name="catatan" rows="4"
                                            placeholder="Catatan internal tentang pasien...">{{ old('catatan', $customer->catatan) }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <button class="btn btn-primary" type="submit">
                                <i class="fa fa-save"></i> Simpan
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="deleteData()">
                                <i class="fa fa-trash"></i> Hapus
                            </button>
                        </form>
                        <form action="{{ route('rawcustomer.destroy', $customer->id) }}" method="POST" id="delete">
                            @csrf
                            @method('DELETE')
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Riwayat Reservasi --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Riwayat Reservasi</h4>
                    </div>
                    <div class="card-body">
                        @if($customer->reservations->isEmpty())
                            <p class="text-muted">Belum ada riwayat reservasi.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-sm">
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
                                            @endphp
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td nowrap>
                                                    {{ \Carbon\Carbon::parse($res->date)->format('d/m/Y') }}
                                                    @if($res->start_hour)
                                                        <br><small class="text-muted">{{ substr($res->start_hour, 0, 5) }}</small>
                                                    @endif
                                                </td>
                                                <td>{{ $res->branch?->name ?? '-' }}</td>
                                                <td>{{ $res->doctor?->name ?? '-' }}</td>
                                                <td>
                                                    @if($serviceNames)
                                                        {{ $serviceNames }}
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td nowrap>
                                                    <span class="badge"
                                                        style="background-color: {{ $statusInfo['bg_color'] ?? '#6c757d' }}; color: #fff; padding: 4px 8px;">
                                                        {{ $statusInfo['label'] ?? $res->status }}
                                                    </span>
                                                </td>
                                                <td nowrap>
                                                    @if($res->total_reservasi)
                                                        Rp {{ number_format($res->total_reservasi, 0, ',', '.') }}
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('rawreservation.edit', $res->id) }}"
                                                        class="btn btn-info btn-sm" style="padding:2px 6px" title="Detail Reservasi">
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
            </div>
        </div>

    </div>
@endsection

@section('js')
    <script>
        // Hitung usia saat tanggal lahir diubah
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
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
            }).then((result) => {
                if (result.isConfirmed) { $('#delete').submit(); }
            })
        }
    </script>
@endsection
