@extends('layouts.app')

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a>CRUD</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('rawaffiliate') }}">Affiliate</a></li>
                        <li class="breadcrumb-item active">Tambah</li>
                    </ol>
                </div>
                <h4 class="page-title">Tambah Affiliate</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('rawaffiliate.store') }}">
                        @csrf
                        <a href="{{ route('rawaffiliate') }}" class="btn btn-sm btn-warning mb-3">
                            <i class="fa fa-arrow-left"></i> Kembali
                        </a>

                        <table class="table table-bordered" width="100%">
                            <tr>
                                <td width="25%">Nama <span class="text-danger">*</span></td>
                                <td>
                                    <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                        type="text" name="name" value="{{ old('name') }}" required />
                                    @if($errors->has('name'))
                                        <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>Role <span class="text-danger">*</span></td>
                                <td>
                                    <select class="form-control" name="role">
                                        @foreach(App\Models\RawAffiliate::roles() as $key => $label)
                                            <option value="{{ $key }}"
                                                {{ old('role', 'pasien') == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Role menentukan jenis pemilik kode referral ini</small>
                                </td>
                            </tr>
                            <tr>
                                <td>No. WhatsApp</td>
                                <td>
                                    <input class="form-control" type="text" name="phone"
                                        value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" />
                                </td>
                            </tr>
                            <tr>
                                <td>Info Rekening Bank</td>
                                <td>
                                    <input class="form-control" type="text" name="bank_account_info"
                                        value="{{ old('bank_account_info') }}"
                                        placeholder="BCA 1234567890 a/n Nama" />
                                </td>
                            </tr>
                            <tr>
                                <td>Commission Rate (%) <span class="text-danger">*</span></td>
                                <td>
                                    <div class="input-group">
                                        <input class="form-control {{ $errors->has('commission_rate') ? 'is-invalid' : '' }}"
                                            type="number" name="commission_rate" step="0.01" min="0" max="100"
                                            value="{{ old('commission_rate', 0) }}" required />
                                        <div class="input-group-append">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    @if($errors->has('commission_rate'))
                                        <div class="invalid-feedback d-block">{{ $errors->first('commission_rate') }}</div>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>Status</td>
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