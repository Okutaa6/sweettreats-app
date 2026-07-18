@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/absensi.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/absensi.sweettreats.css')) ?: '1' }}">
@endsection

@php
    $badge = [
        'hadir' => 'success', 'telat' => 'warning', 'alpa' => 'danger',
        'cuti' => 'info', 'izin' => 'info', 'wfh' => 'primary', 'libur' => 'secondary',
    ];
    $reqBadge = [
        'draft' => 'secondary', 'pending_l1' => 'warning', 'pending_l2' => 'warning',
        'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary',
    ];
    $hari = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
@endphp

@section('content')
<div class="container-fluid abs-akun">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center">
                <h4 class="page-title mb-0">Absensi Saya</h4>
                <a href="{{ route('akun.pengajuan') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Ajukan</a>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ── Pindah bulan ── --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('akun.absensi', ['m' => $anchor->copy()->subMonth()->format('Y-m')]) }}"
           class="btn btn-outline-secondary btn-sm"><i class="fa fa-chevron-left"></i></a>
        <strong>{{ $anchor->translatedFormat('F Y') }}</strong>
        <a href="{{ route('akun.absensi', ['m' => $anchor->copy()->addMonth()->format('Y-m')]) }}"
           class="btn btn-outline-secondary btn-sm"><i class="fa fa-chevron-right"></i></a>
    </div>

    {{-- ── Rekap ringkas (stat tiles, wrap di HP) ── --}}
    <div class="row no-gutters mb-3 abs-stats">
        @foreach(['hadir'=>'Hadir','telat'=>'Telat','alpa'=>'Alpa','cuti'=>'Cuti/Izin'] as $k => $lbl)
            <div class="col-3 px-1">
                <div class="card mb-0 text-center">
                    <div class="card-body py-2 px-1">
                        <div class="h4 mb-0 text-{{ $badge[$k] ?? 'dark' }}">{{ $rekap[$k] }}</div>
                        <small class="text-muted">{{ $lbl }}</small>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @if($rekap['telat_menit'] || $rekap['lembur_menit'])
        <p class="text-muted small mb-3">
            Total telat {{ $rekap['telat_menit'] }} menit
            @if($rekap['lembur_menit']) · lembur disetujui {{ round($rekap['lembur_menit']/60,1) }} jam @endif
        </p>
    @endif

    {{-- ── Daftar hari ── --}}
    <div class="card">
        <div class="card-body p-0">
            @if($days->isEmpty())
                <p class="text-muted text-center py-4 mb-0">Belum ada data absen bulan ini.</p>
            @else
                <div class="list-group list-group-flush">
                    @foreach($days as $tgl => $d)
                        @php $c = \Illuminate\Support\Carbon::parse($tgl); @endphp
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <span class="text-muted mr-2" style="min-width:52px;display:inline-block">
                                    {{ $hari[$c->dayOfWeek] }} {{ $c->format('d/m') }}
                                </span>
                                @if($d->scan_first)
                                    <span class="small">
                                        {{ \Illuminate\Support\Carbon::parse($d->scan_first)->format('H:i') }}
                                        –
                                        {{ $d->scan_last ? \Illuminate\Support\Carbon::parse($d->scan_last)->format('H:i') : '—' }}
                                    </span>
                                @else
                                    <span class="small text-muted">tanpa scan</span>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="badge badge-{{ $badge[$d->status] ?? 'light' }}">{{ ucfirst($d->status) }}</span>
                                @if($d->telat_menit)<span class="badge badge-light text-warning">+{{ $d->telat_menit }}m</span>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ── Riwayat pengajuan ── --}}
    <h5 class="mt-4 mb-2">Pengajuan Saya</h5>
    <div class="card">
        <div class="card-body p-0">
            @if($requests->isEmpty())
                <p class="text-muted text-center py-4 mb-0">Belum ada pengajuan.</p>
            @else
                <div class="list-group list-group-flush">
                    @foreach($requests as $r)
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <span class="badge badge-outline-secondary text-uppercase">{{ $r->tipe }}</span>
                                <span class="small ml-1">
                                    {{ \Illuminate\Support\Carbon::parse($r->tanggal_from)->format('d/m') }}
                                    @if($r->tanggal_to != $r->tanggal_from)
                                        – {{ \Illuminate\Support\Carbon::parse($r->tanggal_to)->format('d/m') }}
                                    @endif
                                </span>
                                @if($r->alasan)<div class="small text-muted">{{ \Illuminate\Support\Str::limit($r->alasan, 50) }}</div>@endif
                            </div>
                            <span class="badge badge-{{ $reqBadge[$r->status] ?? 'light' }}">{{ str_replace('_',' ',$r->status) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
