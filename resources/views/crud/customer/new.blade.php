@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <!-- Page-Title -->
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
                    <div class="card-body">
                        <div class="table-responsive">
                            <form method="post" action="{{ route('rawcustomer.store') }}">
                                @csrf
                                <a href="{{ route('rawcustomer') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;">
                                    <i class="fa fa-arrow-left"></i> Kembali
                                </a>

                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <tr>
                                        <td width="20%">Nama</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                                type="text" name="name" value="{{ old('name') }}" />
                                            @if ($errors->has('name'))
                                                <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Phone</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}"
                                                type="text" name="phone" value="{{ old('phone') }}" />
                                            @if ($errors->has('phone'))
                                                <div class="invalid-feedback">{{ $errors->first('phone') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Source App</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('source_app') ? 'is-invalid' : '' }}"
                                                type="text" name="source_app" value="{{ old('source_app') }}" />
                                            @if ($errors->has('source_app'))
                                                <div class="invalid-feedback">{{ $errors->first('source_app') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Channel</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('channel') ? 'is-invalid' : '' }}"
                                                type="text" name="channel" value="{{ old('channel') }}" />
                                            @if ($errors->has('channel'))
                                                <div class="invalid-feedback">{{ $errors->first('channel') }}</div>
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
                </div>
            </div>
        </div>
    </div>
@endsection
