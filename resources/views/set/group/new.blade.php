@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a>User Access</a></li>
                            <li class="breadcrumb-item active">Role</li>
                            <li class="breadcrumb-item active">Tambah</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Tambah</h4>
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
                            <form method="post" action="{{ route('setgroup.new') }}">
                                @csrf
                                <a href="{{ route('setgroup') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;"><i class="fa fa-arrow-left"></i> Kembali </a>

                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <tr>
                                        <td width="20%">Nama</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('nama') ? 'is-invalid' : '' }}"
                                                type="text" name="nama" value="{{ old('nama') }}" required />
                                            @if ($errors->has('nama'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('nama') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Deskripsi</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('deskripsi') ? 'is-invalid' : '' }}"
                                                type="text" name="deskripsi" value="{{ old('deskripsi') }}" required />
                                            @if ($errors->has('deskripsi'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('deskripsi') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Landing Page</td>
                                        <td>
                                            <input
                                                class="form-control {{ $errors->has('landing_page') ? 'is-invalid' : '' }}"
                                                type="text" name="landing_page" value="{{ old('landing_page') }}"
                                                required />
                                            @if ($errors->has('landing_page'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('landing_page') }}
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
