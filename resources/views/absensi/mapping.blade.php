@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Absensi — Mapping Karyawan</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="card">
        <div class="card-body">
            <p class="text-muted mb-2" style="font-size:.87rem">
                No.ID mesin yang belum tersambung ke karyawan. Selama belum di-map,
                scan-nya <strong>tak terhitung</strong> (tak masuk absen, tak kesanding lembur).
                Sambungkan ke orang, atau <em>Abaikan</em> (dokter / enrollment tes / sudah keluar).
                Total scan belum ter-map: <strong>{{ number_format($totalUnmapped) }}</strong>.
            </p>

            <form method="GET" action="{{ route('absensi.mapping') }}" class="form-inline mb-3">
                <label class="mr-2 mb-0">Cabang</label>
                <select name="branch" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                    <option value="">Semua cabang</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected($branchFilter === $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-sm btn-primary">Tampilkan</button></noscript>
            </form>

            @if($rows->isEmpty())
                <p class="text-success mb-0"><i class="fa fa-check-circle"></i> Semua scan sudah ter-mapping (di filter ini).</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Cabang</th>
                                <th>No.ID</th>
                                <th>Nama di Mesin</th>
                                <th class="text-right">Scan</th>
                                <th>Rentang</th>
                                <th style="min-width:280px">Sambungkan ke</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $r)
                                <tr>
                                    <td>{{ $r->branch_name ?? '—' }}</td>
                                    <td><span class="badge badge-light">{{ $r->device_user_id }}</span></td>
                                    <td>{{ $r->nama_di_mesin ?: '—' }}</td>
                                    <td class="text-right">{{ $r->scan_count }}</td>
                                    <td class="small text-muted" style="white-space:nowrap">
                                        {{ \Illuminate\Support\Carbon::parse($r->first_scan)->format('d/m') }}–{{ \Illuminate\Support\Carbon::parse($r->last_scan)->format('d/m/y') }}
                                    </td>
                                    <td>
                                        <form action="{{ route('absensi.mapping.assign') }}" method="POST" class="form-inline">
                                            @csrf
                                            <input type="hidden" name="raw_branch_id" value="{{ $r->raw_branch_id }}">
                                            <input type="hidden" name="device_user_id" value="{{ $r->device_user_id }}">
                                            <input type="hidden" name="nama_di_mesin" value="{{ $r->nama_di_mesin }}">
                                            <select name="user_id" class="form-control form-control-sm mr-1" required style="min-width:190px">
                                                <option value="">— pilih karyawan —</option>
                                                @foreach($users as $u)
                                                    <option value="{{ $u->id }}">{{ $u->nama }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-link"></i></button>
                                        </form>
                                    </td>
                                    <td class="text-right">
                                        <form action="{{ route('absensi.mapping.ignore') }}" method="POST"
                                              onsubmit="return confirm('Abaikan No.ID {{ $r->device_user_id }} ({{ $r->branch_name }})?');">
                                            @csrf
                                            <input type="hidden" name="raw_branch_id" value="{{ $r->raw_branch_id }}">
                                            <input type="hidden" name="device_user_id" value="{{ $r->device_user_id }}">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm" title="Abaikan">Abaikan</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-2 mb-0">Maks 200 baris (urut scan terbaru dulu). Filter cabang untuk mempersempit.</p>
            @endif
        </div>
    </div>
</div>
@endsection
