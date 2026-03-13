@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a>CRUD</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('rawreservation') }}">Reservation</a></li>
                            <li class="breadcrumb-item active">Tambah</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Tambah Reservation</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <form method="post" action="{{ route('rawreservation.store') }}">
                                @csrf
                                <a href="{{ route('rawreservation') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;">
                                    <i class="fa fa-arrow-left"></i> Kembali
                                </a>

                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <tr>
                                        <td width="20%">Customer</td>
                                        <td>
                                            <select
                                                class="select2 form-control {{ $errors->has('raw_customer_id') ? 'is-invalid' : '' }}"
                                                name="raw_customer_id">
                                                <option value="">- Pilih Customer -</option>
                                                @foreach ($customers as $customer)
                                                    <option value="{{ $customer->id }}"
                                                        {{ old('raw_customer_id') == $customer->id ? 'selected' : '' }}>
                                                        {{ $customer->name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('raw_customer_id'))
                                                <div class="invalid-feedback">{{ $errors->first('raw_customer_id') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Doctor</td>
                                        <td>
                                            <select
                                                class="select2 form-control {{ $errors->has('raw_doctor_id') ? 'is-invalid' : '' }}"
                                                name="raw_doctor_id">
                                                <option value="">- Pilih Doctor -</option>
                                                @foreach ($doctors as $doctor)
                                                    <option value="{{ $doctor->id }}"
                                                        {{ old('raw_doctor_id') == $doctor->id ? 'selected' : '' }}>
                                                        {{ $doctor->name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('raw_doctor_id'))
                                                <div class="invalid-feedback">{{ $errors->first('raw_doctor_id') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Cabang</td>
                                        <td>
                                            <select
                                                class="select2 form-control {{ $errors->has('raw_branch_id') ? 'is-invalid' : '' }}"
                                                name="raw_branch_id">
                                                <option value="">- Pilih Cabang -</option>
                                                @foreach ($branches as $branch)
                                                    <option value="{{ $branch->id }}"
                                                        {{ old('raw_branch_id') == $branch->id ? 'selected' : '' }}>
                                                        {{ $branch->name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('raw_branch_id'))
                                                <div class="invalid-feedback">{{ $errors->first('raw_branch_id') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Tanggal</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('date') ? 'is-invalid' : '' }}"
                                                type="date" name="date" value="{{ old('date') }}" />
                                            @if ($errors->has('date'))
                                                <div class="invalid-feedback">{{ $errors->first('date') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Jam Mulai</td>
                                        <td>
                                            <input
                                                class="form-control {{ $errors->has('start_hour') ? 'is-invalid' : '' }}"
                                                type="time" name="start_hour" value="{{ old('start_hour') }}" />
                                            @if ($errors->has('start_hour'))
                                                <div class="invalid-feedback">{{ $errors->first('start_hour') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Jam Selesai</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('end_hour') ? 'is-invalid' : '' }}"
                                                type="time" name="end_hour" value="{{ old('end_hour') }}" />
                                            @if ($errors->has('end_hour'))
                                                <div class="invalid-feedback">{{ $errors->first('end_hour') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Waiting List</td>
                                        <td>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="is_waiting"
                                                    name="is_waiting" value="1"
                                                    {{ old('is_waiting') ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="is_waiting">Ya, masukkan ke waiting
                                                    list</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>
                                            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i>
                                                Simpan</button>
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
