@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Tambah Role</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-8">
                <div class="st-card">
                    <div class="st-card__body">
                        <form method="post" action="{{ route('setgroup.new') }}">
                            @csrf
                            <a href="{{ route('setgroup') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <table class="st-table">
                                <tr>
                                    <td width="20%" style="font-weight:600;">Nama</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('nama') ? 'is-invalid' : '' }}"
                                            type="text" name="nama" value="{{ old('nama') }}" required />
                                        @if ($errors->has('nama'))
                                            <div class="invalid-feedback">{{ $errors->first('nama') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Deskripsi</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('deskripsi') ? 'is-invalid' : '' }}"
                                            type="text" name="deskripsi" value="{{ old('deskripsi') }}" required />
                                        @if ($errors->has('deskripsi'))
                                            <div class="invalid-feedback">{{ $errors->first('deskripsi') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Landing Page</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('landing_page') ? 'is-invalid' : '' }}"
                                            type="text" name="landing_page" value="{{ old('landing_page') }}" required />
                                        @if ($errors->has('landing_page'))
                                            <div class="invalid-feedback">{{ $errors->first('landing_page') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Atasan</td>
                                    <td>
                                        <select class="form-control" name="parent_id">
                                            <option value="">— Tidak ada (puncak) —</option>
                                            @foreach ($parents as $p)
                                                <option value="{{ $p->id }}" {{ old('parent_id', $preselectParent ?? '') == $p->id ? 'selected' : '' }}>
                                                    {{ str_repeat('— ', $p->level) }}{{ $p->nama }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small style="color:var(--text-subtle)">Untuk struktur organisasi / approval berjenjang. Kosongkan jika role ini tidak ikut hierarki.</small>
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
