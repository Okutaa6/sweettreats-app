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
                                            type="text" name="name" id="branchName" value="{{ old('name') }}" />
                                        @if ($errors->has('name'))
                                            <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Slug URL</td>
                                    <td>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text" style="font-size:12px;">{{ url('/pasien-baru') }}/</span>
                                            </div>
                                            <input class="form-control {{ $errors->has('slug') ? 'is-invalid' : '' }}"
                                                type="text" name="slug" id="branchSlug" maxlength="120"
                                                value="{{ old('slug') }}" placeholder="otomatis dari nama" />
                                        </div>
                                        <small class="text-muted">Dipakai untuk link form Pasien Baru cabang ini. Kosongkan untuk mengisi otomatis dari nama.</small>
                                        @if ($errors->has('slug'))
                                            <div class="text-danger" style="font-size:12px;">{{ $errors->first('slug') }}</div>
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

@section('js')
<script>
    // Isi slug otomatis dari nama SELAMA user belum mengetik slug sendiri.
    (function () {
        var name = document.getElementById('branchName');
        var slug = document.getElementById('branchSlug');
        if (!name || !slug) return;

        var manual = slug.value.trim() !== '';
        slug.addEventListener('input', function () { manual = true; });

        name.addEventListener('input', function () {
            if (manual) return;
            slug.value = name.value.toString().toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        });
    })();
</script>
@endsection
