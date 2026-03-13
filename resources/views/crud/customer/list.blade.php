@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a>CRUD</a></li>
                            <li class="breadcrumb-item active">Customer</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Customer</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">

                            <a href="{{ route('rawcustomer.new') }}" class="btn btn-sm btn-primary float-left mb-3">
                                <i class="fa fa-plus"></i> Tambah
                            </a>

                            <table class="table table-bordered table-hover table-brown" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th>Nama</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Jenis Kelamin</th>
                                        <th>Tanggal Lahir</th>
                                        <th>Source App</th>
                                        <th>Channel</th>
                                        <th width="8%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($customers as $key => $row)
                                        <tr>
                                            <td>{{ $customers->firstItem() + $key }}</td>
                                            <td>{{ $row->name }}</td>
                                            <td>{{ $row->phone }}</td>
                                            <td>{{ $row->email ?? '-' }}</td>
                                            <td>
                                                @if($row->jenis_kelamin == 'L') Laki-laki
                                                @elseif($row->jenis_kelamin == 'P') Perempuan
                                                @else -
                                                @endif
                                            </td>
                                            <td>{{ $row->tanggal_lahir ? \Carbon\Carbon::parse($row->tanggal_lahir)->format('d/m/Y') : '-' }}</td>
                                            <td>{{ $row->source_app ?? '-' }}</td>
                                            <td>{{ $row->channel ?? '-' }}</td>
                                            <td nowrap>
                                                <a href="{{ route('rawcustomer.edit', $row->id) }}"
                                                    class="btn btn-info" style="padding:2px 6px" title="Detail">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            {{ $customers->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection