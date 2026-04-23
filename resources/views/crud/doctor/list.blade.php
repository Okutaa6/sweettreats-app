@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Doctor</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body">
                    @php
                        $hasFilter = request('search') || request('branch_id');
                        $showBranchFilter = isset($branches) && $branches->isNotEmpty();
                    @endphp

                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <a href="{{ route('rawdoctor.new') }}" class="st-btn-brand">
                            <i class="fa fa-plus"></i> Tambah
                        </a>
                        <button class="st-btn-outline-brown {{ $hasFilter ? 'is-active' : '' }}" type="button" data-toggle="collapse" data-target="#filterCollapse" aria-expanded="{{ $hasFilter ? 'true' : 'false' }}">
                            <i class="fa fa-filter"></i> {{ $hasFilter ? 'Filter Aktif' : 'Filter' }}
                        </button>
                    </div>

                    <div class="collapse {{ $hasFilter ? 'show' : '' }}" id="filterCollapse">
                        <div class="st-card mb-3" style="background-color: var(--cream-header);">
                            <div class="st-card__body" style="padding:14px 18px;">
                                <form method="GET" action="{{ route('rawdoctor') }}" class="mb-0">
                                    <div class="row" style="gap:8px 0;">
                                        <div class="col-md-{{ $showBranchFilter ? '5' : '8' }}">
                                            <label class="small mb-1">Nama Dokter</label>
                                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama dokter..." value="{{ $filterSearch ?? '' }}">
                                        </div>
                                        @if($showBranchFilter)
                                        <div class="col-md-3">
                                            <label class="small mb-1">Cabang</label>
                                            <select name="branch_id" class="form-control form-control-sm">
                                                <option value="">Semua Cabang</option>
                                                @foreach($branches as $branch)
                                                    <option value="{{ $branch->id }}" {{ ($filterBranch ?? '') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @endif
                                        <div class="col-md-2 d-flex align-items-end" style="gap:4px;">
                                            <button type="submit" class="st-btn-brand w-100">Filter</button>
                                            <a href="{{ route('rawdoctor') }}" class="st-btn-outline-brown" title="Reset"><i class="fa fa-times"></i></a>
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
                                    <th width="5%">No</th>
                                    <th>Nama</th>
                                    <th width="20%">Jumlah Jadwal</th>
                                    <th width="20%">Jumlah Reservasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($doctors as $key => $row)
                                    <tr data-href="{{ route('rawdoctor.edit', $row->id) }}">
                                        <td class="st-num">{{ $doctors->firstItem() + $key }}</td>
                                        <td class="st-primary">{{ $row->name }}</td>
                                        <td class="st-tnum">{{ $row->schedules_count }}</td>
                                        <td class="st-tnum">{{ $row->reservations_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">{{ $doctors->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
