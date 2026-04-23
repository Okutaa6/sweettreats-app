@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Branch</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body">
                    <a href="{{ route('rawbranch.new') }}" class="st-btn-brand mb-3" style="display:inline-flex">
                        <i class="fa fa-plus"></i> Tambah
                    </a>

                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--clickable">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Nama Cabang</th>
                                    <th width="20%">Jumlah Jadwal</th>
                                    <th width="20%">Jumlah Reservasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($branches as $key => $row)
                                    <tr data-href="{{ route('rawbranch.edit', $row->id) }}">
                                        <td class="st-num">{{ $branches->firstItem() + $key }}</td>
                                        <td class="st-primary">{{ $row->name }}</td>
                                        <td class="st-tnum">{{ $row->doctor_schedules_count }}</td>
                                        <td class="st-tnum">{{ $row->reservations_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">{{ $branches->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
