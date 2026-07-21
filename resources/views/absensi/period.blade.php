@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Absensi — Periode Gaji</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        {{-- ── Buat periode ── --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-1"><i class="fa fa-calendar-plus text-muted"></i> Buat Periode</h5>
                    <p class="text-muted mb-3" style="font-size:.85rem">
                        Rentang cut-off penggajian (mis. 26 s/d 25). Tutup periode → data absen
                        di rentang itu terkunci (reconcile tak menimpanya lagi), lalu export payroll.
                    </p>
                    <form action="{{ route('absensi.period.store') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="mb-1" style="font-weight:600">Nama <span class="text-muted small">(opsional)</span></label>
                            <input type="text" name="nama" class="form-control form-control-sm" placeholder="mis. Gaji Juli 2026" value="{{ old('nama') }}">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label class="mb-1" style="font-weight:600">Dari</label>
                                <input type="date" name="periode_from" class="form-control form-control-sm" value="{{ old('periode_from') }}" required>
                            </div>
                            <div class="form-group col-6">
                                <label class="mb-1" style="font-weight:600">Sampai</label>
                                <input type="date" name="periode_to" class="form-control form-control-sm" value="{{ old('periode_to') }}" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Buat</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ── Daftar periode ── --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fa fa-history text-muted"></i> Periode</h5>
                    @if($periods->isEmpty())
                        <p class="text-muted mb-0">Belum ada periode.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Periode</th>
                                        <th>Rentang</th>
                                        <th>Status</th>
                                        <th>Ditutup</th>
                                        <th class="text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($periods as $p)
                                        <tr>
                                            <td>{{ $p->nama }}</td>
                                            <td style="white-space:nowrap">{{ $p->periode_from->format('d/m/y') }} – {{ $p->periode_to->format('d/m/y') }}</td>
                                            <td>
                                                @if($p->isClosed())
                                                    <span class="badge badge-secondary"><i class="fa fa-lock"></i> Ditutup</span>
                                                @else
                                                    <span class="badge badge-success">Terbuka</span>
                                                @endif
                                            </td>
                                            <td class="small text-muted" style="white-space:nowrap">
                                                @if($p->isClosed())
                                                    {{ optional($p->closer)->nama ?? '—' }}<br>{{ optional($p->closed_at)->format('d/m H:i') }}
                                                @else — @endif
                                            </td>
                                            <td class="text-right" style="white-space:nowrap">
                                                <a href="{{ route('absensi.period.export', $p->id) }}" class="btn btn-outline-secondary btn-sm" title="Export payroll CSV">
                                                    <i class="fa fa-download"></i>
                                                </a>
                                                @if(! $p->isClosed())
                                                    <form action="{{ route('absensi.period.close', $p->id) }}" method="POST" class="d-inline"
                                                          onsubmit="return confirm('Tutup periode ini? Data absen di rentangnya akan terkunci.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Tutup & kunci periode">
                                                            <i class="fa fa-lock"></i> Tutup
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
