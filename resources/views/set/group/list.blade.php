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
                            <li class="breadcrumb-item active">Role</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Role</h4>
                </div>
                <!--end page-title-box-->
            </div>
            <!--end col-->
        </div>

        @include('layouts.notif')
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body table-responsive">
                        <a href="{{ route('setgroup.new') }}" class="btn btn-sm btn-primary float-left"
                            style="margin-bottom: 20px;"><i class="fa fa-plus"></i> Tambah</a>

                        <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Name</th>
                                    <th>Deskripsi</th>
                                    <th>Landing Page</th>
                                    <th width="10%">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($groups as $index => $list)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $list->nama }}</td>
                                        <td>{{ $list->deskripsi }}</td>
                                        <td>{{ $list->landing_page }}</td>
                                        <td nowrap>
                                            <a href="{{ route('setgroup.edit', $list->id) }}" class="btn btn-success"
                                                style="padding: 2px 6px; margin: 0 3px"><i class="fa fa-edit"></i></a>
                                            <a href="{{ route('setgroup.access', $list->id) }}" class="btn btn-primary"
                                                style="padding: 2px 6px; margin: 0 3px"><i class="fa fa-key"></i></a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        {{ $groups->links() }}
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
