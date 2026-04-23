@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Pasien</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-12">
                <div class="st-card">
                    <div class="st-card__body">
                        @php
                            $hasFilter = request('search') || request('service_id') || request('doctor_id') || request('branch_id');
                            $showBranchFilter = isset($branches) && $branches->isNotEmpty();
                        @endphp

                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <a href="{{ route('rawcustomer.new') }}" class="st-btn-brand">
                                <i class="fa fa-plus"></i> Tambah
                            </a>
                            <button class="st-btn-outline-brown {{ $hasFilter ? 'is-active' : '' }}" type="button" data-toggle="collapse" data-target="#filterCollapse" aria-expanded="{{ $hasFilter ? 'true' : 'false' }}">
                                <i class="fa fa-filter"></i> {{ $hasFilter ? 'Filter Aktif' : 'Filter' }}
                            </button>
                        </div>

                        <div class="collapse {{ $hasFilter ? 'show' : '' }}" id="filterCollapse">
                            <div class="st-card mb-3" style="background-color: var(--cream-header);">
                                <div class="st-card__body" style="padding:14px 18px;">
                                    <form method="GET" action="{{ route('rawcustomer') }}" class="mb-0">
                                        <div class="row" style="gap:8px 0;">
                                            <div class="col-md-3">
                                                <label class="small mb-1">Pencarian</label>
                                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari Nama / No. HP..." value="{{ $filterSearch ?? '' }}">
                                            </div>
                                            @if($showBranchFilter)
                                            <div class="col-md-2">
                                                <label class="small mb-1">Cabang</label>
                                                <select name="branch_id" class="form-control form-control-sm">
                                                    <option value="">Semua Cabang</option>
                                                    @foreach($branches as $branch)
                                                        <option value="{{ $branch->id }}" {{ ($filterBranch ?? '') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            @endif
                                            <div class="col-md-{{ $showBranchFilter ? '2' : '3' }}">
                                                <label class="small mb-1">Perawatan</label>
                                                <select name="service_id" class="form-control form-control-sm">
                                                    <option value="">Semua Perawatan</option>
                                                    @foreach($services as $service)
                                                        <option value="{{ $service->id }}" {{ ($filterService ?? '') == $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-{{ $showBranchFilter ? '3' : '3' }}">
                                                <label class="small mb-1">Dokter</label>
                                                <select name="doctor_id" class="form-control form-control-sm">
                                                    <option value="">Semua Dokter</option>
                                                    @foreach($doctors as $doctor)
                                                        <option value="{{ $doctor->id }}" {{ ($filterDoctor ?? '') == $doctor->id ? 'selected' : '' }}>{{ $doctor->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end" style="gap:4px;">
                                                <button type="submit" class="st-btn-brand w-100">Filter</button>
                                                <a href="{{ route('rawcustomer') }}" class="st-btn-outline-brown" title="Reset"><i class="fa fa-times"></i></a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="st-table-wrap--scroll">
                            <table class="st-table st-table--clickable">
                                <thead>
                                    <tr>
                                        <th width="4%">No</th>
                                        <th>Nama</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Jenis Kelamin</th>
                                        <th>Tanggal Lahir</th>
                                        <th>Cabang</th>
                                        <th style="min-width:150px;">Perawatan</th>
                                        <th>Dokter</th>
                                        <th>Referensi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($customers as $key => $row)
                                        @php $latestReservation = $row->latestReservation; @endphp
                                        <tr data-href="{{ route('rawcustomer.edit', $row->id) }}">
                                            <td class="st-num">{{ $customers->firstItem() + $key }}</td>
                                            <td class="st-primary">{{ $row->name }}</td>
                                            <td class="st-tnum">{{ $row->phone }}</td>
                                            <td>{{ $row->email ?? '-' }}</td>
                                            <td>{{ $row->jenis_kelamin == 'L' ? 'Laki-laki' : ($row->jenis_kelamin == 'P' ? 'Perempuan' : '-') }}</td>
                                            <td><span class="st-date">{{ ($row->tanggal_lahir && $row->tanggal_lahir != '0000-00-00') ? \Carbon\Carbon::parse($row->tanggal_lahir)->format('d/m/Y') : '-' }}</span></td>
                                            <td>{{ $row->branch?->name ?? '-' }}</td>
                                            <td style="white-space:normal;">
                                                @if($latestReservation && $latestReservation->services->isNotEmpty())
                                                    {{ $latestReservation->services->map(fn($s) => $s->docService?->name)->filter()->implode(', ') }}
                                                @else
                                                    <span class="st-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $latestReservation?->doctor?->name ?? '-' }}</td>
                                            <td>{{ $row->source_app ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">{{ $customers->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
