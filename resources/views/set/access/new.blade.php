@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Tambah Akses</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-8">
                <div class="st-card">
                    <div class="st-card__body">
                        <form method="post" action="{{ route('setaccess.store') }}">
                            @csrf
                            <a href="{{ route('setaccess') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <table class="st-table">
                                <tr>
                                    <td width="20%" style="font-weight:600;">Induk</td>
                                    <td>
                                        <select class="select2 form-control" name="pid">
                                            <option value="">- Tanpa Induk -</option>
                                            @foreach ($access as $row)
                                                @php $row = (object) $row; @endphp
                                                <option value="{{ $row->id }}" {{ old('pid') == $row->id ? 'selected' : '' }}>
                                                    {{ $row->nama }}
                                                </option>
                                                @if (!empty($row->sub))
                                                    @foreach ($row->sub as $rowsub)
                                                        @php $rowsub = (object) $rowsub; @endphp
                                                        <option value="{{ $rowsub->id }}" {{ old('pid') == $rowsub->id ? 'selected' : '' }}>
                                                            -- {{ $rowsub->nama }}
                                                        </option>
                                                        @if (!empty($rowsub->child))
                                                            @foreach ($rowsub->child as $rowchild)
                                                                @php $rowchild = (object) $rowchild; @endphp
                                                                <option value="{{ $rowchild->id }}" {{ old('pid') == $rowchild->id ? 'selected' : '' }}>
                                                                    ------ {{ $rowchild->nama }}
                                                                </option>
                                                            @endforeach
                                                        @endif
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        </select>
                                        <small class="form-text text-danger">{{ $errors->first('group') }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Nama</td>
                                    <td>
                                        <input class="form-control" type="text" name="nama" value="{{ old('nama') }}" required />
                                        <small class="form-text text-danger">{{ $errors->first('nama') }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Icon</td>
                                    <td>
                                        <input class="form-control" type="text" name="icon" value="{{ old('icon') }}" />
                                        <small class="form-text text-danger">{{ $errors->first('icon') }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Urutan</td>
                                    <td>
                                        <input class="form-control" type="number" name="urutan" value="{{ old('urutan') }}" required />
                                        <small class="form-text text-danger">{{ $errors->first('urutan') }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Link</td>
                                    <td>
                                        <input class="form-control" type="text" name="link" value="{{ old('link') }}" />
                                        <small class="form-text text-danger">{{ $errors->first('link') }}</small>
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
