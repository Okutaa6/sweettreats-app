@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Tambah Reservation</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-8">
                <div class="st-card">
                    <div class="st-card__body">
                        <form method="post" action="{{ route('rawreservation.store') }}">
                            @csrf
                            <a href="{{ route('rawreservation') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <table class="st-table">
                                <tr>
                                    <td width="20%" style="font-weight:600;">Pasien</td>
                                    <td>
                                        <select class="form-control {{ $errors->has('raw_customer_id') ? 'is-invalid' : '' }}"
                                            name="raw_customer_id" id="customerSelect">
                                            <option value="">- Cari Pasien -</option>
                                        </select>
                                        @if ($errors->has('raw_customer_id'))
                                            <div class="invalid-feedback">{{ $errors->first('raw_customer_id') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Doctor</td>
                                    <td>
                                        <select class="select2 form-control {{ $errors->has('raw_doctor_id') ? 'is-invalid' : '' }}"
                                            name="raw_doctor_id">
                                            <option value="">- Pilih Doctor -</option>
                                            @foreach ($doctors as $doctor)
                                                <option value="{{ $doctor->id }}" {{ old('raw_doctor_id') == $doctor->id ? 'selected' : '' }}>
                                                    {{ $doctor->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('raw_doctor_id'))
                                            <div class="invalid-feedback">{{ $errors->first('raw_doctor_id') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Cabang</td>
                                    <td>
                                        <select class="select2 form-control {{ $errors->has('raw_branch_id') ? 'is-invalid' : '' }}"
                                            name="raw_branch_id">
                                            <option value="">- Pilih Cabang -</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}" {{ old('raw_branch_id') == $branch->id ? 'selected' : '' }}>
                                                    {{ $branch->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('raw_branch_id'))
                                            <div class="invalid-feedback">{{ $errors->first('raw_branch_id') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Tanggal</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('date') ? 'is-invalid' : '' }}"
                                            type="date" name="date" value="{{ old('date') }}" />
                                        @if ($errors->has('date'))
                                            <div class="invalid-feedback">{{ $errors->first('date') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Jam Mulai</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('start_hour') ? 'is-invalid' : '' }}"
                                            type="time" name="start_hour" value="{{ old('start_hour') }}" />
                                        @if ($errors->has('start_hour'))
                                            <div class="invalid-feedback">{{ $errors->first('start_hour') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Jam Selesai</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('end_hour') ? 'is-invalid' : '' }}"
                                            type="time" name="end_hour" value="{{ old('end_hour') }}" />
                                        @if ($errors->has('end_hour'))
                                            <div class="invalid-feedback">{{ $errors->first('end_hour') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Keterangan</td>
                                    <td>
                                        <textarea class="form-control {{ $errors->has('keterangan') ? 'is-invalid' : '' }}"
                                            name="keterangan" rows="3"
                                            placeholder="Tambahkan keterangan reservasi...">{{ old('keterangan') }}</textarea>
                                        @if ($errors->has('keterangan'))
                                            <div class="invalid-feedback">{{ $errors->first('keterangan') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Waiting List</td>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="is_waiting"
                                                name="is_waiting" value="1" {{ old('is_waiting') ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="is_waiting">Ya, masukkan ke waiting list</label>
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
$(document).ready(function() {
    $('#customerSelect').select2({
        placeholder: '- Cari Pasien -',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("api.customer.search") }}',
            dataType: 'json',
            delay: 300,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data }; }
        }
    });
});
</script>
@endsection
