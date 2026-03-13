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
                            <li class="breadcrumb-item active">Reservation</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Reservation</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">

                            <a href="{{ route('rawreservation.new') }}" class="btn btn-sm btn-primary float-left"
                                style="margin-bottom: 20px;"><i class="fa fa-plus"></i> Tambah</a>

                            <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th>Customer</th>
                                        <th>Doctor</th>
                                        <th>Cabang</th>
                                        <th>Tanggal</th>
                                        <th>Jam</th>
                                        <th width="8%">Status</th>
                                        <th width="8%">Services</th>
                                        <th width="8%">Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($reservations as $key => $row)
                                        @php $si = $row->status_info; @endphp
                                        <tr>
                                            <td>{{ $reservations->firstItem() + $key }}</td>
                                            <td>{{ $row->customer->name ?? '-' }}</td>
                                            <td>{{ $row->doctor->name ?? '-' }}</td>
                                            <td>{{ $row->branch->name ?? '-' }}</td>
                                            <td>{{ $row->date ? $row->date->format('d/m/Y') : '-' }}</td>
                                            <td>{{ $row->start_hour }} - {{ $row->end_hour }}</td>
                                            <td>
                                                <span class="badge"
                                                    style="background:{{ $si['bg'] }};color:{{ $si['color'] }}">
                                                    <i class="fa {{ $si['icon'] }}"></i> {{ $si['label'] }}
                                                </span>
                                            </td>
                                            <td>{{ $row->services_count }}</td>
                                            <td nowrap>
                                                <a href="{{ route('rawreservation.edit', $row->id) }}" class="btn btn-info"
                                                    style="padding: 2px 6px; margin: 0 3px" data-toggle="tooltip"
                                                    data-placement="top" title="Detail"><i class="fa fa-eye"></i></a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            {{ $reservations->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
