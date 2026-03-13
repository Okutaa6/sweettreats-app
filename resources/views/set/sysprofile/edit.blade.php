@extends('layouts.app')
@section('content')

    <div class="container-fluid">
        <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item active"><a>Parameter</a></li>
                            <li class="breadcrumb-item active">System Profile</li>
                            <li class="breadcrumb-item">Ubah</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Ubah</h4>
                </div>
                <!--end page-title-box-->
            </div>
            <!--end col-->
        </div>
        <!-- end page title end breadcrumb -->

        <div class="row">
            <div class="col-md-12">

                <div class="card">

                    <div class="card-body">

                        <div class="table-responsive">
                            <form method="post" action="{{ route('setsysprofile.update', $id) }}"
                                enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <a href="{{ route('setsysprofile') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;"><i class="fa fa-arrow-left"></i> Kembali </a>

                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <tr>
                                        <td width="20%">Dashboard Title</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('systitle') ? 'is-invalid' : '' }}"
                                                type="text" name="systitle"
                                                value="{{ old('systitle', $data['systitle']) }}" required />
                                            @if ($errors->has('systitle'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('systitle') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Institution Name</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('sysname') ? 'is-invalid' : '' }}"
                                                type="text" name="sysname" value="{{ old('sysname', $data['sysname']) }}"
                                                required />
                                            @if ($errors->has('sysname'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('sysname') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Penampungan</td>
                                    </tr>
                                    <tr>
                                        <td>---- Display Mutasi</td>
                                        <td>
                                            <select
                                                class="select2 form-control mb-3 custom-select {{ $errors->has('penampungan_mt940_mutasi') ? 'is-invalid' : '' }}"
                                                name="penampungan_mt940_mutasi">
                                                @foreach ($listMutasi as $key => $value)
                                                    <option value="{{ $key }}"
                                                        {{ $key == $data['penampungan_mt940_mutasi'] ? 'selected' : '' }}>
                                                        {{ $value }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('penampungan_mt940_mutasi'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('penampungan_mt940_mutasi') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>---- Flag MT940 (optional)</td>
                                        <td>
                                            <input
                                                class="form-control {{ $errors->has('penampungan_mt940_flag') ? 'is-invalid' : '' }}"
                                                type="text" name="penampungan_mt940_flag"
                                                value="{{ old('penampungan_mt940_flag', $data['penampungan_mt940_flag']) }}" />
                                            @if ($errors->has('penampungan_mt940_flag'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('penampungan_mt940_flag') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>

                                    <tr>
                                        <td>Utama</td>
                                    </tr>
                                    <tr>
                                        <td>---- Display Mutasi</td>
                                        <td>
                                            <select
                                                class="select2 form-control mb-3 custom-select {{ $errors->has('utama_mt940_mutasi') ? 'is-invalid' : '' }}"
                                                name="utama_mt940_mutasi">
                                                @foreach ($listMutasi as $key => $value)
                                                    <option value="{{ $key }}"
                                                        {{ $key == $data['utama_mt940_mutasi'] ? 'selected' : '' }}>
                                                        {{ $value }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('utama_mt940_mutasi'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('utama_mt940_mutasi') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>---- Flag MT940 (optional)</td>
                                        <td>
                                            <input
                                                class="form-control {{ $errors->has('utama_mt940_flag') ? 'is-invalid' : '' }}"
                                                type="text" name="utama_mt940_flag"
                                                value="{{ old('utama_mt940_flag', $data['utama_mt940_flag']) }}" />
                                            @if ($errors->has('utama_mt940_flag'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('utama_mt940_flag') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>

                                    <tr>
                                        <td>Pengeluaran</td>
                                    </tr>
                                    <tr>
                                        <td>---- Display Mutasi</td>
                                        <td>
                                            <select
                                                class="select2 form-control mb-3 custom-select {{ $errors->has('pengeluaran_mt940_mutasi') ? 'is-invalid' : '' }}"
                                                name="pengeluaran_mt940_mutasi">
                                                @foreach ($listMutasi as $key => $value)
                                                    <option value="{{ $key }}"
                                                        {{ $key == $data['pengeluaran_mt940_mutasi'] ? 'selected' : '' }}>
                                                        {{ $value }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('pengeluaran_mt940_mutasi'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('pengeluaran_mt940_mutasi') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>---- Flag MT940 (optional)</td>
                                        <td>
                                            <input
                                                class="form-control {{ $errors->has('pengeluaran_mt940_flag') ? 'is-invalid' : '' }}"
                                                type="text" name="pengeluaran_mt940_flag"
                                                value="{{ old('pengeluaran_mt940_flag', $data['pengeluaran_mt940_flag']) }}" />
                                            @if ($errors->has('pengeluaran_mt940_flag'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('pengeluaran_mt940_flag') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>
                                            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i>
                                                Simpan</button>
                                        </td>
                                    </tr>

                                </table>
                            </form>

                        </div>
                    </div>
                    <!--end card-body-->


                </div>
                <!--end card-->
            </div>
        </div>
    </div>
@endsection
