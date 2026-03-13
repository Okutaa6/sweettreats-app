@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a>CRUD</a></li>
                        <li class="breadcrumb-item active">Affiliate</li>
                    </ol>
                </div>
                <h4 class="page-title">Affiliate</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <a href="{{ route('rawaffiliate.new') }}" class="btn btn-sm btn-primary float-left mb-3">
                            <i class="fa fa-plus"></i> Tambah
                        </a>

                        <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Nama</th>
                                    <th>Phone</th>
                                    <th>Info Bank</th>
                                    <th>Komisi %</th>
                                    <th>Promo</th>
                                    <th>Status</th>
                                    <th width="8%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($affiliates as $key => $row)
                                    <tr>
                                        <td>{{ $affiliates->firstItem() + $key }}</td>
                                        <td>{{ $row->name }}</td>
                                        <td>{{ $row->phone ?? '-' }}</td>
                                        <td>{{ $row->bank_account_info ?? '-' }}</td>
                                        <td>{{ $row->commission_rate }}%</td>
                                        <td>{{ $row->promos_count }} kode</td>
                                        <td>
                                            @if($row->is_active)
                                                <span class="badge badge-success">Aktif</span>
                                            @else
                                                <span class="badge badge-secondary">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td nowrap>
                                            <a href="{{ route('rawaffiliate.edit', $row->id) }}"
                                                class="btn btn-info" style="padding:2px 6px" title="Detail">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        {{ $affiliates->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection