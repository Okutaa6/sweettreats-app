@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Tambah Doctor Service</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-8">
                <div class="st-card">
                    <div class="st-card__body">
                        <form method="post" action="{{ route('rawdocservice.store') }}">
                            @csrf
                            <a href="{{ route('rawdocservice') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <table class="st-table">
                                <tr>
                                    <td width="20%" style="font-weight:600;">Nama Layanan <span class="text-danger">*</span></td>
                                    <td>
                                        <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                            type="text" name="name" value="{{ old('name') }}" />
                                        @if ($errors->has('name'))
                                            <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Kategori <span class="text-danger">*</span></td>
                                    <td>
                                        <select class="form-control {{ $errors->has('category') ? 'is-invalid' : '' }}" name="category">
                                            <option value="">-- Pilih Kategori --</option>
                                            @foreach ($categories as $key => $label)
                                                <option value="{{ $key }}" {{ old('category') === $key ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Menentukan di grup mana layanan ini muncul di form reservasi pasien</small>
                                        @if ($errors->has('category'))
                                            <div class="invalid-feedback">{{ $errors->first('category') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Urutan Tampil</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('sort_order') ? 'is-invalid' : '' }}"
                                            type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}" />
                                        <small class="text-muted">Angka kecil tampil lebih dulu dalam kategorinya</small>
                                        @if ($errors->has('sort_order'))
                                            <div class="invalid-feedback">{{ $errors->first('sort_order') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Durasi (Menit) <span class="text-danger">*</span></td>
                                    <td>
                                        <input class="form-control {{ $errors->has('duration_minutes') ? 'is-invalid' : '' }}"
                                            type="number" name="duration_minutes" min="1" value="{{ old('duration_minutes') }}" />
                                        @if ($errors->has('duration_minutes'))
                                            <div class="invalid-feedback">{{ $errors->first('duration_minutes') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Harga (Rp)</td>
                                    <td>
                                        <input class="form-control price-input {{ $errors->has('price') ? 'is-invalid' : '' }}"
                                            type="text" inputmode="numeric" name="price" value="{{ old('price', 0) }}" />
                                        @if ($errors->has('price'))
                                            <div class="invalid-feedback">{{ $errors->first('price') }}</div>
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
    function formatNumber(n) { if (typeof n !== 'string') n = String(n); return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, "."); }
    function unformatNumber(n) { return String(n).replace(/\./g, ""); }
    $('.price-input').each(function() { $(this).val(formatNumber($(this).val())); });
    $(document).on('input', '.price-input', function(e){ $(this).val(formatNumber($(this).val())); });
    $('form').on('submit', function() { $(this).find('.price-input').each(function() { $(this).val(unformatNumber($(this).val())); }); });
</script>
@endsection
