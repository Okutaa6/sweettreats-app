@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a>CRUD</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('rawkeyword') }}">Keyword</a></li>
                        <li class="breadcrumb-item active">Tambah</li>
                    </ol>
                </div>
                <h4 class="page-title">Tambah Keyword</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('rawkeyword.store') }}">
                        @csrf
                        <a href="{{ route('rawkeyword') }}" class="btn btn-sm btn-warning mb-3">
                            <i class="fa fa-arrow-left"></i> Kembali
                        </a>

                        <table class="table table-bordered" width="100%">
                            <tr>
                                <td width="30%">Keyword <span class="text-danger">*</span></td>
                                <td>
                                    <input class="form-control {{ $errors->has('keyword') ? 'is-invalid' : '' }}"
                                        type="text" name="keyword" value="{{ old('keyword') }}"
                                        placeholder="Contoh: promo scaling 99k" required />
                                    <small class="text-muted">Pengecekan tidak peduli huruf besar/kecil</small>
                                    @if($errors->has('keyword'))
                                        <div class="invalid-feedback">{{ $errors->first('keyword') }}</div>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>Deskripsi</td>
                                <td>
                                    <textarea class="form-control" name="description" rows="3"
                                        placeholder="Keterangan intent ini untuk apa...">{{ old('description') }}</textarea>
                                </td>
                            </tr>
                            <tr>
                                <td>Berlaku Sampai</td>
                                <td>
                                    <input class="form-control" type="date" name="end_date"
                                        value="{{ old('end_date') }}" />
                                    <small class="text-muted">Kosongkan jika keyword aktif selamanya</small>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <button class="btn btn-primary" type="submit">
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