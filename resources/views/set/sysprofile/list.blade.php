@extends('layouts.app')

@section('content')

    <div class="container-fluid">

        <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a>Parameter</a></li>
                            <li class="breadcrumb-item active">System Profile</li>
                        </ol>
                    </div>
                    <h4 class="page-title">System Profile</h4>
                </div>
                <!--end page-title-box-->
            </div>
            <!--end col-->
        </div>
        <!-- end page title end breadcrumb -->

        @include('layouts.notif')
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Dashboard Title</th>
                                        <th>Institution Title</th>
                                        <th>Logo</th>
                                        <th width="10%">Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr>
                                        <td>
                                            {{ $sysprofile->systitle }}
                                        </td>
                                        <td>
                                            {{ $sysprofile->sysname }}
                                        </td>
                                        <td><img src="{{ url('sysprofile/' . $sysprofile->syslogo) }}" style="width:100px;">
                                        </td>
                                        <td nowrap>
                                            <a href="{{ url('setsystemprofile/' . $sysprofile->id) }}"
                                                class="btn btn-success" style="padding: 2px 6px; margin: 0 3px"><i
                                                    class="fa fa-edit"></i></a>
                                            <button type="button" class="btn btn-primary" data-toggle="modal"
                                                data-target="#uploadLogoModal" style="padding: 2px 6px; margin: 0 3px">
                                                <i class="fa fa-upload"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="uploadLogoModal" tabindex="-1" role="dialog" aria-labelledby="uploadLogoModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ url('setsystemprofile/upload/' . $sysprofile->id) }}" method="POST"
                enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="uploadLogoModalLabel">Upload Logo
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="logo">Choose Logo</label>
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times"></i> Close
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-upload"></i> Upload
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
