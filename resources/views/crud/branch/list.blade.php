@extends('layouts.app')

@section('content')


<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a>CRUD</a></li>
                        <li class="breadcrumb-item active">Branch</li>
                    </ol>
                </div>
                <h4 class="page-title">Branch</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">

                    <a href="{{ route('rawbranch.new') }}" class="btn btn-sm btn-primary float-left mb-3">
                        <i class="fa fa-plus"></i> Tambah
                    </a>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-branch">

                            <colgroup>
                                <col class="c-no">
                                <col class="c-nama">
                                <col class="c-jadwal">
                                <col class="c-reservasi">
                                <col class="c-action">
                            </colgroup>

                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Cabang</th>
                                    <th>Jumlah Jadwal</th>
                                    <th>Jumlah Reservasi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($branches as $key => $row)
                                    <tr>
                                        <td>{{ $branches->firstItem() + $key }}</td>
                                        <td title="{{ $row->name }}">{{ $row->name }}</td>
                                        <td>{{ $row->doctor_schedules_count }}</td>
                                        <td>{{ $row->reservations_count }}</td>
                                        <td>
                                            <a href="{{ route('rawbranch.edit', $row->id) }}"
                                               class="btn btn-info btn-sm btn-action" title="Detail">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>

                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $branches->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection