@extends('layouts.app')

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Referral</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body">
                    <a href="{{ route('rawpromo.new') }}" class="st-btn-brand mb-3" style="display:inline-flex">
                        <i class="fa fa-plus"></i> Tambah
                    </a>

                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--clickable">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Kode Referral</th>
                                    <th>Affiliate</th>
                                    <th>Tipe</th>
                                    <th>Nilai Diskon</th>
                                    <th>Berlaku Sampai</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($promos as $key => $row)
                                    <tr data-href="{{ route('rawpromo.edit', $row->id) }}">
                                        <td class="st-num">{{ $promos->firstItem() + $key }}</td>
                                        <td class="st-primary">{{ $row->promo_code }}</td>
                                        <td>
                                            @if($row->affiliate)
                                                {{ $row->affiliate->name }}
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $row->discount_type == 'nominal' ? 'Nominal' : 'Persentase' }}</td>
                                        <td class="st-tnum">
                                            @if($row->discount_type == 'nominal')
                                                Rp {{ number_format($row->discount_value, 0, ',', '.') }}
                                            @else
                                                {{ $row->discount_value }}%
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->valid_until)
                                                <span class="st-date">{{ $row->valid_until->format('d/m/Y') }}</span>
                                                @if($row->valid_until->isPast())
                                                    <span class="st-pill st-pill--danger ml-1">Expired</span>
                                                @endif
                                            @else
                                                <span class="st-muted">Selamanya</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->is_active && (!$row->valid_until || $row->valid_until->isFuture()))
                                                <span class="st-pill st-pill--success">Aktif</span>
                                            @else
                                                <span class="st-pill st-pill--neutral">Nonaktif</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $promos->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
