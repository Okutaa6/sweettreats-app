@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Doctor Service</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')
        <div class="row">
            <div class="col-md-12">
                <div class="st-card">
                    <div class="st-card__body">
                        <a href="{{ route('rawdocservice.new') }}" class="st-btn-brand mb-3" style="display:inline-flex">
                            <i class="fa fa-plus"></i> Tambah
                        </a>

                        <div class="st-table-wrap--scroll">
                            <table class="st-table st-table--clickable" id="dataTable">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th>Nama Layanan</th>
                                        <th width="15%">Durasi (Menit)</th>
                                        <th width="15%">Harga</th>
                                        <th width="12%">Digunakan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($services as $key => $row)
                                        <tr data-href="{{ route('rawdocservice.edit', $row->id) }}">
                                            <td class="st-num">{{ $services->firstItem() + $key }}</td>
                                            <td class="st-primary">{{ $row->name }}</td>
                                            <td class="st-tnum">{{ $row->duration_minutes }} menit</td>
                                            <td class="st-tnum">Rp {{ number_format($row->price, 0, ',', '.') }}</td>
                                            <td class="st-tnum">{{ $row->reservation_services_count }}x</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{ $services->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
