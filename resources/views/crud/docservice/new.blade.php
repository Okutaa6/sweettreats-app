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
                            <li class="breadcrumb-item"><a href="{{ route('rawdocservice') }}">Doctor Service</a></li>
                            <li class="breadcrumb-item active">Tambah</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Tambah Doctor Service</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <form method="post" action="{{ route('rawdocservice.store') }}">
                                @csrf
                                <a href="{{ route('rawdocservice') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;">
                                    <i class="fa fa-arrow-left"></i> Kembali
                                </a>

                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <tr>
                                        <td width="20%">Nama Layanan</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                                type="text" name="name" value="{{ old('name') }}" />
                                            @if ($errors->has('name'))
                                                <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Durasi (Menit)</td>
                                        <td>
                                            <input
                                                class="form-control {{ $errors->has('duration_minutes') ? 'is-invalid' : '' }}"
                                                type="number" name="duration_minutes" min="1"
                                                value="{{ old('duration_minutes') }}" />
                                            @if ($errors->has('duration_minutes'))
                                                <div class="invalid-feedback">{{ $errors->first('duration_minutes') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Harga (Rp)</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('price') ? 'is-invalid' : '' }}"
                                                type="number" name="price" min="0" value="{{ old('price') }}" />
                                            @if ($errors->has('price'))
                                                <div class="invalid-feedback">{{ $errors->first('price') }}</div>
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
