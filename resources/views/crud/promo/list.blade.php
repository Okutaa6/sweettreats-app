@extends('layouts.app')

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a>CRUD</a></li>
                        <li class="breadcrumb-item active">Promo</li>
                    </ol>
                </div>
                <h4 class="page-title">Promo</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <a href="{{ route('rawpromo.new') }}" class="btn btn-sm btn-primary float-left mb-3">
                            <i class="fa fa-plus"></i> Tambah
                        </a>

                        <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Kode Promo</th>
                                    <th>Affiliate</th>
                                    <th>Tipe</th>
                                    <th>Nilai Diskon</th>
                                    <th>Berlaku Sampai</th>
                                    <th>Status</th>
                                    <th width="8%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($promos as $key => $row)
                                    <tr>
                                        <td>{{ $promos->firstItem() + $key }}</td>
                                        <td><strong>{{ $row->promo_code }}</strong></td>
                                        <td>{{ $row->affiliate->name ?? '<span class="text-muted">-</span>' }}</td>
                                        <td>{{ $row->discount_type == 'nominal' ? 'Nominal' : 'Persentase' }}</td>
                                        <td>
                                            @if($row->discount_type == 'nominal')
                                                Rp {{ number_format($row->discount_value, 0, ',', '.') }}
                                            @else
                                                {{ $row->discount_value }}%
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->valid_until)
                                                {{ $row->valid_until->format('d/m/Y') }}
                                                @if($row->valid_until->isPast())
                                                    <span class="badge badge-danger ml-1">Expired</span>
                                                @endif
                                            @else
                                                <span class="text-muted">Selamanya</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->is_active && (!$row->valid_until || $row->valid_until->isFuture()))
                                                <span class="badge badge-success">Aktif</span>
                                            @else
                                                <span class="badge badge-secondary">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('rawpromo.edit', $row->id) }}"
                                                class="btn btn-info" style="padding:2px 6px" title="Detail">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        {{ $promos->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection