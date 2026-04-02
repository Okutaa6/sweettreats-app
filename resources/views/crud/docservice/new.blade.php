@extends('layouts.app')

@section('content')
    <div class="container-fluid">
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
                                        <td width="20%">Nama Layanan <span class="text-danger">*</span></td>
                                        <td>
                                            <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                                type="text" name="name" value="{{ old('name') }}" />
                                            @if ($errors->has('name'))
                                                <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                            @endif
                                        </td>
                                    </tr>

                                    {{-- ================================================ --}}
                                    {{-- KATEGORI - BARU: dropdown kategori & sort_order  --}}
                                    {{-- Sebelumnya field ini tidak ada sama sekali        --}}
                                    {{-- ================================================ --}}
                                    <tr>
                                        <td>Kategori <span class="text-danger">*</span></td>
                                        <td>
                                            <select class="form-control {{ $errors->has('category') ? 'is-invalid' : '' }}"
                                                name="category">
                                                <option value="">-- Pilih Kategori --</option>
                                                @foreach ($categories as $key => $label)
                                                    <option value="{{ $key }}"
                                                        {{ old('category') === $key ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">
                                                Menentukan di grup mana layanan ini muncul di form reservasi pasien
                                            </small>
                                            @if ($errors->has('category'))
                                                <div class="invalid-feedback">{{ $errors->first('category') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Urutan Tampil</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('sort_order') ? 'is-invalid' : '' }}"
                                                type="number" name="sort_order" min="0"
                                                value="{{ old('sort_order', 0) }}" />
                                            <small class="text-muted">
                                                Angka kecil tampil lebih dulu dalam kategorinya
                                            </small>
                                            @if ($errors->has('sort_order'))
                                                <div class="invalid-feedback">{{ $errors->first('sort_order') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    {{-- ================================================ --}}

                                    <tr>
                                        <td>Durasi (Menit) <span class="text-danger">*</span></td>
                                        <td>
                                            <input class="form-control {{ $errors->has('duration_minutes') ? 'is-invalid' : '' }}"
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
                                            <input class="form-control price-input {{ $errors->has('price') ? 'is-invalid' : '' }}"
                                                type="text" inputmode="numeric" name="price"
                                                value="{{ old('price', 0) }}" />
                                            @if ($errors->has('price'))
                                                <div class="invalid-feedback">{{ $errors->first('price') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>&nbsp;</td>
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
    </div>
@endsection

@section('js')
<script>
    function formatNumber(n) {
        if (typeof n !== 'string') n = String(n);
        return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function unformatNumber(n) {
        return String(n).replace(/\./g, "");
    }

    // Initial format
    $('.price-input').each(function() {
        $(this).val(formatNumber($(this).val()));
    });

    // Format on input
    $(document).on('input', '.price-input', function(e){
        $(this).val(formatNumber($(this).val()));
    });

    // Before submitting the form, unformat the price
    $('form').on('submit', function() {
        $(this).find('.price-input').each(function() {
            $(this).val(unformatNumber($(this).val()));
        });
    });
</script>
@endsection