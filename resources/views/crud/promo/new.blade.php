@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Tambah Referral</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-8">
            <div class="st-card">
                <div class="st-card__body">
                    <form method="POST" action="{{ route('rawpromo.store') }}">
                        @csrf
                        <a href="{{ route('rawpromo') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                            <i class="fa fa-arrow-left"></i> Kembali
                        </a>

                        <table class="st-table">
                            <tr>
                                <td width="30%" style="font-weight:600;">Kode Referral <span class="text-danger">*</span></td>
                                <td>
                                    <input class="form-control {{ $errors->has('promo_code') ? 'is-invalid' : '' }}"
                                        type="text" name="promo_code" value="{{ old('promo_code') }}"
                                        placeholder="Contoh: HEMAT50" style="text-transform:uppercase" required />
                                    <small class="text-muted">Akan otomatis diubah ke huruf kapital</small>
                                    @if($errors->has('promo_code'))
                                        <div class="invalid-feedback">{{ $errors->first('promo_code') }}</div>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Affiliate</td>
                                <td>
                                    <select class="form-control select2" name="raw_affiliate_id">
                                        <option value="">-- Tanpa Affiliate --</option>
                                        @foreach($affiliates as $aff)
                                            <option value="{{ $aff->id }}" {{ old('raw_affiliate_id') == $aff->id ? 'selected' : '' }}>
                                                {{ $aff->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Tipe Diskon <span class="text-danger">*</span></td>
                                <td>
                                    <select class="form-control" name="discount_type" id="discountType" required>
                                        <option value="nominal" {{ old('discount_type') == 'nominal' ? 'selected' : '' }}>Nominal (Rp)</option>
                                        <option value="percent" {{ old('discount_type') == 'percent' ? 'selected' : '' }}>Persentase (%)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Nilai Diskon <span class="text-danger">*</span></td>
                                <td>
                                    <div class="input-group">
                                        <div class="input-group-prepend" id="prefixLabel">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input class="form-control {{ $errors->has('discount_value') ? 'is-invalid' : '' }}"
                                            type="number" name="discount_value" step="0.01" min="0"
                                            value="{{ old('discount_value', 0) }}" required />
                                        <div class="input-group-append" id="suffixLabel" style="display:none">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    @if($errors->has('discount_value'))
                                        <div class="invalid-feedback d-block">{{ $errors->first('discount_value') }}</div>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Berlaku Sampai</td>
                                <td>
                                    <input class="form-control" type="date" name="valid_until"
                                        value="{{ old('valid_until') }}" />
                                    <small class="text-muted">Kosongkan jika tidak ada batas waktu</small>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Status</td>
                                <td>
                                    <div class="custom-control custom-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" class="custom-control-input" id="isActive"
                                            name="is_active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="isActive">Aktif</label>
                                    </div>
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
    $('#discountType').on('change', function() {
        if ($(this).val() === 'percent') {
            $('#prefixLabel').hide();
            $('#suffixLabel').show();
        } else {
            $('#prefixLabel').show();
            $('#suffixLabel').hide();
        }
    }).trigger('change');
</script>
@endsection
