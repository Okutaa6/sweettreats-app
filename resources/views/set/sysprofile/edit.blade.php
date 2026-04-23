@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Ubah System Profile</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-8">
                <div class="st-card">
                    <div class="st-card__body">
                        <form method="post" action="{{ route('setsysprofile.update', $id) }}"
                            enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <a href="{{ route('setsysprofile') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <table class="st-table">
                                <tr>
                                    <td width="20%" style="font-weight:600;">Dashboard Title</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('systitle') ? 'is-invalid' : '' }}"
                                            type="text" name="systitle"
                                            value="{{ old('systitle', $data['systitle']) }}" required />
                                        @if ($errors->has('systitle'))
                                            <div class="invalid-feedback">{{ $errors->first('systitle') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Institution Name</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('sysname') ? 'is-invalid' : '' }}"
                                            type="text" name="sysname" value="{{ old('sysname', $data['sysname']) }}"
                                            required />
                                        @if ($errors->has('sysname'))
                                            <div class="invalid-feedback">{{ $errors->first('sysname') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="font-weight:700; color:var(--text-muted); background:var(--cream-header);">Penampungan</td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600; padding-left:28px;">Display Mutasi</td>
                                    <td>
                                        <select class="select2 form-control {{ $errors->has('penampungan_mt940_mutasi') ? 'is-invalid' : '' }}"
                                            name="penampungan_mt940_mutasi">
                                            @foreach ($listMutasi as $key => $value)
                                                <option value="{{ $key }}" {{ $key == $data['penampungan_mt940_mutasi'] ? 'selected' : '' }}>
                                                    {{ $value }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('penampungan_mt940_mutasi'))
                                            <div class="invalid-feedback">{{ $errors->first('penampungan_mt940_mutasi') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600; padding-left:28px;">Flag MT940</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('penampungan_mt940_flag') ? 'is-invalid' : '' }}"
                                            type="text" name="penampungan_mt940_flag"
                                            value="{{ old('penampungan_mt940_flag', $data['penampungan_mt940_flag']) }}" />
                                        @if ($errors->has('penampungan_mt940_flag'))
                                            <div class="invalid-feedback">{{ $errors->first('penampungan_mt940_flag') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="font-weight:700; color:var(--text-muted); background:var(--cream-header);">Utama</td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600; padding-left:28px;">Display Mutasi</td>
                                    <td>
                                        <select class="select2 form-control {{ $errors->has('utama_mt940_mutasi') ? 'is-invalid' : '' }}"
                                            name="utama_mt940_mutasi">
                                            @foreach ($listMutasi as $key => $value)
                                                <option value="{{ $key }}" {{ $key == $data['utama_mt940_mutasi'] ? 'selected' : '' }}>
                                                    {{ $value }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('utama_mt940_mutasi'))
                                            <div class="invalid-feedback">{{ $errors->first('utama_mt940_mutasi') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600; padding-left:28px;">Flag MT940</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('utama_mt940_flag') ? 'is-invalid' : '' }}"
                                            type="text" name="utama_mt940_flag"
                                            value="{{ old('utama_mt940_flag', $data['utama_mt940_flag']) }}" />
                                        @if ($errors->has('utama_mt940_flag'))
                                            <div class="invalid-feedback">{{ $errors->first('utama_mt940_flag') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="font-weight:700; color:var(--text-muted); background:var(--cream-header);">Pengeluaran</td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600; padding-left:28px;">Display Mutasi</td>
                                    <td>
                                        <select class="select2 form-control {{ $errors->has('pengeluaran_mt940_mutasi') ? 'is-invalid' : '' }}"
                                            name="pengeluaran_mt940_mutasi">
                                            @foreach ($listMutasi as $key => $value)
                                                <option value="{{ $key }}" {{ $key == $data['pengeluaran_mt940_mutasi'] ? 'selected' : '' }}>
                                                    {{ $value }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('pengeluaran_mt940_mutasi'))
                                            <div class="invalid-feedback">{{ $errors->first('pengeluaran_mt940_mutasi') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600; padding-left:28px;">Flag MT940</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('pengeluaran_mt940_flag') ? 'is-invalid' : '' }}"
                                            type="text" name="pengeluaran_mt940_flag"
                                            value="{{ old('pengeluaran_mt940_flag', $data['pengeluaran_mt940_flag']) }}" />
                                        @if ($errors->has('pengeluaran_mt940_flag'))
                                            <div class="invalid-feedback">{{ $errors->first('pengeluaran_mt940_flag') }}</div>
                                        @endif
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
