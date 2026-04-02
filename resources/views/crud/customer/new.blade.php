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
                            <li class="breadcrumb-item active">Tambah</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Tambah Customer</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Data Customer</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('rawcustomer.store') }}">
                            @csrf
                            <a href="{{ route('rawcustomer') }}" class="btn btn-sm btn-warning mb-3">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <div class="row">
                                {{-- Kolom Kiri --}}
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Nama <span class="text-danger">*</span></label>
                                        <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                            type="text" name="name" value="{{ old('name') }}" required />
                                        @if ($errors->has('name'))
                                            <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                        @endif
                                    </div>
                                    <div class="form-group">
                                        <label>No. WhatsApp</label>
                                        <input class="form-control" type="text" name="phone" value="{{ old('phone') }}" />
                                    </div>
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input class="form-control" type="email" name="email" value="{{ old('email') }}" />
                                    </div>
                                    <div class="form-group">
                                        <label>Jenis Kelamin</label>
                                        <select class="form-control" name="jenis_kelamin">
                                            <option value="">-- Pilih --</option>
                                            <option value="L" {{ old('jenis_kelamin') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                                            <option value="P" {{ old('jenis_kelamin') == 'P' ? 'selected' : '' }}>Perempuan</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Tempat Lahir</label>
                                        <input class="form-control" type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}" />
                                    </div>
                                    <div class="form-group">
                                        <label>Tanggal Lahir</label>
                                        <input class="form-control" type="date" id="tanggal_lahir" name="tanggal_lahir"
                                            value="{{ old('tanggal_lahir') }}" />
                                    </div>
                                    <div class="form-group">
                                        <label>Usia</label>
                                        <input class="form-control" type="text" id="usia" readonly
                                            placeholder="Otomatis dari tanggal lahir" />
                                    </div>
                                    <div class="form-group">
                                        <label>NIK</label>
                                        <input class="form-control" type="text" name="nik"
                                            value="{{ old('nik') }}" placeholder="16 digit NIK" maxlength="20" />
                                    </div>
                                    <div class="form-group">
                                        <label>Alamat</label>
                                        <textarea class="form-control" name="alamat" rows="3">{{ old('alamat') }}</textarea>
                                    </div>
                                </div>

                                {{-- Kolom Kanan --}}
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Tanggal Daftar</label>
                                        <input class="form-control" type="date" name="tanggal_daftar"
                                            value="{{ old('tanggal_daftar', date('Y-m-d')) }}" />
                                    </div>
                                    <div class="form-group">
                                        <label>Pasien Baru/Lama?</label>
                                        <select class="form-control" name="pasien_baru_lama">
                                            <option value="">-- Pilih --</option>
                                            <option value="baru" {{ old('pasien_baru_lama') == 'baru' ? 'selected' : '' }}>Pasien Baru</option>
                                            <option value="lama" {{ old('pasien_baru_lama') == 'lama' ? 'selected' : '' }}>Pasien Lama</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Cabang</label>
                                        <select class="form-control" name="raw_branch_id">
                                            <option value="">-- Pilih Cabang --</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}" {{ old('raw_branch_id') == $branch->id ? 'selected' : '' }}>
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
                                                <option value="{{ $doctor->id }}" {{ old('raw_doctor_id') == $doctor->id ? 'selected' : '' }}>
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
                                                <option value="{{ $service->id }}" {{ old('raw_doc_service_id') == $service->id ? 'selected' : '' }}>
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
                                                <option value="{{ $promo->id }}" {{ old('raw_promo_id') == $promo->id ? 'selected' : '' }}>
                                                    {{ $promo->promo_code }}
                                                    @if($promo->affiliate) ({{ $promo->affiliate->name }}) @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Referensi</label>
                                        <input class="form-control" type="text" name="source_app"
                                            value="{{ old('source_app') }}" placeholder="Contoh: Instagram, Teman, dll" />
                                    </div>
                                    <div class="form-group">
                                        <label>Catatan</label>
                                        <textarea class="form-control" name="catatan" rows="4"
                                            placeholder="Catatan internal tentang pasien...">{{ old('catatan') }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <button class="btn btn-primary" type="submit">
                                <i class="fa fa-save"></i> Simpan
                            </button>
                        </form>
                    </div>
                </div>
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
    </script>
@endsection
