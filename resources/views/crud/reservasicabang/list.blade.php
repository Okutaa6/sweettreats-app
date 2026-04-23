@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Reservation</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')
        <div class="row">
            <div class="col-md-12">
                <div class="st-card">
                    <div class="st-card__body">
                        <a href="{{ route('rawreservation.new') }}" class="st-btn-brand mb-3" style="display:inline-flex">
                            <i class="fa fa-plus"></i> Tambah
                        </a>

                        <div class="st-table-wrap--scroll">
                            <table class="st-table st-table--clickable" id="dataTable">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th>Customer</th>
                                        <th>Doctor</th>
                                        <th>Cabang</th>
                                        <th>Tanggal</th>
                                        <th>Jam</th>
                                        <th width="10%">Status</th>
                                        <th width="8%">Services</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reservations as $key => $row)
                                        @php
                                            $si = $row->status_info;
                                            $pillMap = [
                                                'pending'   => 'st-pill--warning',
                                                'confirmed' => 'st-pill--info',
                                                'completed' => 'st-pill--success',
                                                'cancelled' => 'st-pill--danger',
                                                'waitlist'  => 'st-pill--neutral',
                                            ];
                                            $pillClass = $pillMap[$row->status] ?? 'st-pill--neutral';
                                        @endphp
                                        <tr data-href="{{ route('rawreservation.edit', $row->id) }}">
                                            <td class="st-num">{{ $reservations->firstItem() + $key }}</td>
                                            <td>{{ $row->customer->name ?? '-' }}</td>
                                            <td>{{ $row->doctor->name ?? '-' }}</td>
                                            <td>{{ $row->branch->name ?? '-' }}</td>
                                            <td><span class="st-date">{{ $row->date ? $row->date->format('d/m/Y') : '-' }}</span></td>
                                            <td class="st-tnum">{{ $row->start_hour }} - {{ $row->end_hour }}</td>
                                            <td>
                                                <span class="st-pill {{ $pillClass }}">
                                                    {{ $si['label'] }}
                                                </span>
                                            </td>
                                            <td class="st-tnum">{{ $row->services_count }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{ $reservations->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
