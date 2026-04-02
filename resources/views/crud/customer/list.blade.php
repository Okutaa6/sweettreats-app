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
                    <div class="card-body" style="zoom: 0.70;">
                        <div class="table-responsive">
                        @php 
                            $hasFilter = request('search') || request('service_id') || request('doctor_id');
                        @endphp

                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <a href="{{ route('rawcustomer.new') }}" class="btn btn-sm btn-primary">
                                <i class="fa fa-plus"></i> Tambah
                            </a>
                            <button class="btn btn-sm {{ $hasFilter ? 'btn-secondary' : 'btn-outline-secondary' }}" type="button" data-toggle="collapse" data-target="#filterCollapse" aria-expanded="{{ $hasFilter ? 'true' : 'false' }}">
                                <i class="fa fa-filter"></i> {{ $hasFilter ? 'Filter Aktif' : 'Filter' }}
                            </button>
                        </div>

                        <div class="collapse {{ $hasFilter ? 'show' : '' }}" id="filterCollapse">
                            <div class="card card-body mb-3 border-0" style="background-color: #f8f9fa;">
                                <form method="GET" action="{{ route('rawcustomer') }}" class="mb-0">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="small mb-1">Pencarian</label>
                                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari Nama / No. HP..." value="{{ $filterSearch ?? '' }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small mb-1">Perawatan</label>
                                            <select name="service_id" class="form-control form-control-sm">
                                                <option value="">Semua Perawatan</option>
                                                @foreach($services as $service)
                                                    <option value="{{ $service->id }}" {{ ($filterService ?? '') == $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small mb-1">Dokter</label>
                                            <select name="doctor_id" class="form-control form-control-sm">
                                                <option value="">Semua Dokter</option>
                                                @foreach($doctors as $doctor)
                                                    <option value="{{ $doctor->id }}" {{ ($filterDoctor ?? '') == $doctor->id ? 'selected' : '' }}>{{ $doctor->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="submit" class="btn btn-sm btn-primary w-100 mr-1">Filter</button>
                                            <a href="{{ route('rawcustomer') }}" class="btn btn-sm btn-outline-danger" title="Reset"><i class="fa fa-times"></i></a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <table class="table table-bordered table-hover table-brown" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th>Nama</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Jenis Kelamin</th>
                                        <th>Tanggal Lahir</th>
                                        <th>Cabang</th>
                                        <th style="min-width: 150px;">Perawatan</th>
                                        <th>Dokter</th>
                                    
                                        <th>Referensi</th>
                                        <th width="8%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($customers as $key => $row)
                                        @php
                                            // Ambil reservasi terakhir dari koleksi yang sudah di-eager load
                                            $latestReservation = $row->reservations->sortByDesc('date')->first();
                                        @endphp
                                        <tr>
                                            <td>{{ $customers->firstItem() + $key }}</td>
                                            <td>{{ $row->name }}</td>
                                            <td>{{ $row->phone }}</td>
                                            <td>{{ $row->email ?? '-' }}</td>
                                            <td>
                                                {{ $row->jenis_kelamin == 'L' ? 'Laki-laki' : ($row->jenis_kelamin == 'P' ? 'Perempuan' : '-') }}
                                            </td>
                                            <td>{{ $row->tanggal_lahir ? \Carbon\Carbon::parse($row->tanggal_lahir)->format('d/m/Y') : '-' }}</td>
                                            <td>{{ $latestReservation?->branch?->name ?? '-' }}</td>
                                            <td style="white-space: normal;">
                                                @if($latestReservation && $latestReservation->services->isNotEmpty())
                                                    {{ $latestReservation->services->map(fn($s) => $s->docService?->name)->filter()->implode(', ') }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $latestReservation?->doctor?->name ?? '-' }}</td>
 
                                            <td>{{ $row->source_app ?? '-' }}</td>
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