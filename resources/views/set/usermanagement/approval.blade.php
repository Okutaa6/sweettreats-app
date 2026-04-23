@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Approval</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="st-card">
            <div class="st-card__body">
                <a href="{{ route('setusermanagement') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                    <i class="fa fa-arrow-left"></i> Kembali
                </a>

                <form action="{{ route('setusermanagement.apv.process', $user->id) }}" method="post">
                    @csrf
                    @method('PUT')
                    <table class="st-table">
                        <tbody>
                            <tr>
                                <td style="width:180px; font-weight:600;">Nama</td>
                                <td>{{ $user->nama }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Email</td>
                                <td>{{ $user->email }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Target Approval</td>
                                <td>
                                    <select style="width:300px" multiple="multiple" name="users_id[]" id="multiselect">
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}"
                                                {{ in_array($user->id, $slcuser) ? 'selected' : '' }}>
                                                {{ $user->nama }} ({{ $user->group->nama }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>&nbsp;</td>
                                <td>
                                    <button class="st-btn-brand" type="submit">
                                        <i class="fa fa-save"></i> Simpan
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
    </div>
@endsection
