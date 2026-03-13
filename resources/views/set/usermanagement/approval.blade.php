@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a>User Access</a></li>
                            <li class="breadcrumb-item active">User</li>
                            <li class="breadcrumb-item active">Approval</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Approval</h4>
                </div>
                <!--end page-title-box-->
            </div>
            <!--end col-->
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <a href="{{ route('setusermanagement') }}" class="btn btn-sm btn-warning mb-2">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>
                            <form action="{{ route('setusermanagement.apv.process', $user->id) }}" method="post">
                                @csrf
                                @method('PUT')
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <tr>
                                        <td>Nama</td>
                                        <td>{{ $user->nama }}</td>
                                    </tr>
                                    <tr>
                                        <td>Email</td>
                                        <td>{{ $user->email }}</td>
                                    </tr>
                                    <tr>
                                        <td>Target Approval</td>
                                        <td>
                                            <select style="width:300px" multiple="multiple" name="users_id[]"
                                                id="multiselect">
                                                @foreach ($users as $user)
                                                    <option value="{{ $user->id }}"
                                                        {{ in_array($user->id, $slcuser) ? 'selected' : '' }}>
                                                        {{ $user->nama }} ({{ $user->group->nama }})</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
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
