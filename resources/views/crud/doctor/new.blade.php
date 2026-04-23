@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Tambah Doctor</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-8">
                <div class="st-card">
                    <div class="st-card__body">
                        <form method="post" action="{{ route('rawdoctor.store') }}" enctype="multipart/form-data">
                            @csrf
                            <a href="{{ route('rawdoctor') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <table class="st-table">
                                <tr>
                                    <td width="20%" style="font-weight:600;">Nama</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                            type="text" name="name" value="{{ old('name') }}" />
                                        @if ($errors->has('name'))
                                            <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Foto</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('photo') ? 'is-invalid' : '' }}"
                                            type="file" name="photo" accept="image/jpeg,image/png,image/webp" />
                                        <small class="text-muted">Opsional. Format JPG/PNG/WebP, maksimal 2 MB.</small>
                                        @if ($errors->has('photo'))
                                            <div class="invalid-feedback">{{ $errors->first('photo') }}</div>
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
