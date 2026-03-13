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
                                            <option value="L" {{ $customer->jenis_kelamin == 'L' ? 'selected' : '' }}>Laki-laki</option>
                                            <option value="P" {{ $customer->jenis_kelamin == 'P' ? 'selected' : '' }}>Perempuan</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Tanggal Lahir</label>
                                        <input class="form-control" type="date" name="tanggal_lahir"
                                            value="{{ old('tanggal_lahir', $customer->tanggal_lahir) }}" />
                                    </div>
                                </div>

                                {{-- Kolom Kanan --}}
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>NIK</label>
                                        <input class="form-control" type="text" name="nik"
                                            value="{{ old('nik', $customer->nik) }}" placeholder="16 digit NIK" maxlength="20" />
                                    </div>
                                    <div class="form-group">
                                        <label>Tempat Lahir</label>
                                        <input class="form-control" type="text" name="tempat_lahir"
                                            value="{{ old('tempat_lahir', $customer->tempat_lahir) }}" />
                                    </div>
                                    <div class="form-group">
                                        <label>Alamat</label>
                                        <textarea class="form-control" name="alamat" rows="3">{{ old('alamat', $customer->alamat) }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Source App</label>
                                        <input class="form-control" type="text" name="source_app"
                                            value="{{ old('source_app', $customer->source_app) }}" readonly />
                                    </div>
                                    <div class="form-group">
                                        <label>Channel</label>
                                        <input class="form-control" type="text" name="channel"
                                            value="{{ old('channel', $customer->channel) }}" readonly />
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
    </div>
@endsection

@section('js')
    <script>
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