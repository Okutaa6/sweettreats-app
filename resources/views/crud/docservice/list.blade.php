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
                            <li class="breadcrumb-item active">Doctor Service</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Doctor Service</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">

                            <a href="{{ route('rawdocservice.new') }}" class="btn btn-sm btn-primary float-left"
                                style="margin-bottom: 20px;"><i class="fa fa-plus"></i> Tambah</a>

                            <table class="table table-bordered table-hover table-brown" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th>Nama Layanan</th>
                                        <th width="15%">Durasi (Menit)</th>
                                        <th width="15%">Harga</th>
                                        <th width="10%">Digunakan</th>
                                        <th width="8%">Aksi</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($services as $key => $row)
                                        <tr>
                                            <td>{{ $services->firstItem() + $key }}</td>
                                            <td>{{ $row->name }}</td>
                                            <td>{{ $row->duration_minutes }} menit</td>
                                            <td>Rp {{ number_format($row->price, 0, ',', '.') }}</td>
                                            <td>{{ $row->reservation_services_count }}x</td>
                                            <td nowrap>
                                                <a href="{{ route('rawdocservice.edit', $row->id) }}" class="btn btn-info"
                                                    style="padding: 2px 6px; margin: 0 3px" data-toggle="tooltip"
                                                    data-placement="top" title="Detail"><i class="fa fa-eye"></i></a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            {{ $services->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
