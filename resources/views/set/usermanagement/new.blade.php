@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Tambah User</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-8">
                <div class="st-card">
                    <div class="st-card__body">
                        <form method="post" action="{{ route('setusermanagement.new') }}">
                            @csrf
                            <a href="{{ route('setusermanagement') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <div style="background:#FBEFD9;border:1px solid #f0dca8;border-radius:10px;padding:11px 14px;font-size:13px;color:#9A6614;margin-bottom:16px;display:flex;gap:9px">
                                <i class="fa fa-info-circle" style="margin-top:2px"></i>
                                <span>Password sementara dibuat <strong>otomatis</strong> dan ditampilkan sekali setelah akun jadi — kasih ke orangnya. Dia <strong>wajib menggantinya</strong> saat login pertama.</span>
                            </div>

                            <table class="st-table">
                                <tr>
                                    <td width="20%" style="font-weight:600;">Email</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                            type="email" name="email" value="{{ old('email') }}" />
                                        @if ($errors->has('email'))
                                            <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Nama</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('nama') ? 'is-invalid' : '' }}"
                                            type="text" name="nama" value="{{ old('nama') }}" />
                                        @if ($errors->has('nama'))
                                            <div class="invalid-feedback">{{ $errors->first('nama') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Role</td>
                                    <td>
                                        <select class="select2 form-control {{ $errors->has('group_id') ? 'is-invalid' : '' }}"
                                            name="group_id">
                                            <option value="">- Pilih Role -</option>
                                            @foreach ($groups as $row)
                                                <option value="{{ $row['id'] }}"
                                                    {{ old('group_id') == $row['id'] ? 'selected' : '' }}>
                                                    {{ $row['nama'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('group_id'))
                                            <div class="invalid-feedback">{{ $errors->first('group_id') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Cabang</td>
                                    <td>
                                        <select class="select2 form-control" name="raw_branch_id">
                                            <option value="">- Semua Cabang -</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch['id'] }}" {{ old('raw_branch_id') == $branch['id'] ? 'selected' : '' }}>{{ $branch['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td>
                                        <button class="st-btn-brand" type="submit">
                                            <i class="fa fa-save"></i> Simpan
                                        </button>
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
