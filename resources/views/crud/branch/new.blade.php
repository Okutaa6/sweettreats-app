@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Tambah Branch</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-8">
                <div class="st-card">
                    <div class="st-card__body">
                        <form method="post" action="{{ route('rawbranch.store') }}">
                            @csrf
                            <a href="{{ route('rawbranch') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <table class="st-table">
                                <tr>
                                    <td width="20%" style="font-weight:600;">Nama Cabang</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                            type="text" name="name" value="{{ old('name') }}" />
                                        @if ($errors->has('name'))
                                            <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Target Tahunan (Rp)</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('target_tahunan') ? 'is-invalid' : '' }}"
                                            type="number" name="target_tahunan" min="0"
                                            value="{{ old('target_tahunan') }}"
                                            placeholder="Contoh: 500000000" />
                                        <small class="text-muted">Kosongkan jika belum ada target</small>
                                        @if ($errors->has('target_tahunan'))
                                            <div class="invalid-feedback">{{ $errors->first('target_tahunan') }}</div>
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
