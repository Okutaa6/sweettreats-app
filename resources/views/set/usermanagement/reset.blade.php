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
                            <li class="breadcrumb-item active">User</li>
                            <li class="breadcrumb-item active">Ubah Password</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Ubah Password</h4>
                </div>
                <!--end page-title-box-->
            </div>
            <!--end col-->
        </div>
        @include('layouts.notif')

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <form method="post" action="{{ route('setusermanagement.reset', $user->id) }}">
                                @csrf
                                <input type="hidden" name="_method" value="PUT">
                                <a href="{{ route('setusermanagement') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;"><i class="fa fa-arrow-left"></i> Kembali </a>
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <tr>
                                        <td width="20%">Password Baru</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}"
                                                type="password" name="password" value="{{ old('password') }}" />
                                            @if ($errors->has('password'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('password') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Konfirmasi Password Baru</td>
                                        <td>
                                            <input
                                                class="form-control {{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}"
                                                type="password" name="password_confirmation"
                                                value="{{ old('password_confirmation') }}" />
                                            @if ($errors->has('password_confirmation'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('password_confirmation') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td>
                                            <button type="submit" class="btn btn-primary">Simpan</button>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </div>
@endsection
