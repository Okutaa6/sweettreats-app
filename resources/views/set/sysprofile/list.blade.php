@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">System Profile</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="st-card">
            <div class="st-card__body">
                <div class="st-table-wrap--scroll">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Dashboard Title</th>
                                <th>Institution Title</th>
                                <th>Logo</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $sysprofile->systitle }}</td>
                                <td>{{ $sysprofile->sysname }}</td>
                                <td>
                                    <img src="{{ url('sysprofile/' . $sysprofile->syslogo) }}"
                                        style="width:100px; border-radius:6px;">
                                </td>
                                <td nowrap>
                                    <div class="d-flex" style="gap:4px;">
                                        <a href="{{ url('setsystemprofile/' . $sysprofile->id) }}"
                                            class="st-icon-btn" title="Edit">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <button type="button" class="st-icon-btn" data-toggle="modal"
                                            data-target="#uploadLogoModal" title="Upload Logo">
                                            <i class="fa fa-upload"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Upload Logo Modal --}}
    <div class="modal fade" id="uploadLogoModal" tabindex="-1" role="dialog" aria-labelledby="uploadLogoModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ url('setsystemprofile/upload/' . $sysprofile->id) }}" method="POST"
                enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="uploadLogoModalLabel">Upload Logo</h5>
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
                        <button type="button" class="st-btn-outline-brown" data-dismiss="modal">
                            <i class="fa fa-times"></i> Close
                        </button>
                        <button type="submit" class="st-btn-brand">
                            <i class="fa fa-upload"></i> Upload
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
