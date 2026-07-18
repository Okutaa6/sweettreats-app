@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/absensi.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/absensi.sweettreats.css')) ?: '1' }}">
@endsection

@php
    $reqBadge = [
        'pending_l1' => 'warning', 'pending_l2' => 'warning',
        'approved' => 'success', 'rejected' => 'danger',
    ];
@endphp

@section('content')
<div class="container-fluid abs-akun">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title mb-0">Persetujuan</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ── Menunggu tindakan ── --}}
    @if($pending->isEmpty())
        <div class="card">
            <div class="card-body text-center text-muted py-4">
                Tidak ada pengajuan yang menunggu persetujuanmu.
            </div>
        </div>
    @else
        @foreach($pending as $r)
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <strong>{{ $r->user->nama ?? 'Pengaju' }}</strong>
                            <span class="badge badge-outline-secondary text-uppercase ml-1">{{ $tipeLabel[$r->tipe] ?? $r->tipe }}</span>
                        </div>
                        <span class="badge badge-{{ $reqBadge[$r->status] ?? 'light' }}">
                            {{ $r->status === 'pending_l2' ? 'Lapis 2' : 'Lapis 1' }}
                        </span>
                    </div>

                    <div class="small mb-1">
                        <i class="fa fa-calendar text-muted mr-1"></i>
                        {{ \Illuminate\Support\Carbon::parse($r->tanggal_from)->format('d/m/Y') }}
                        @if($r->tanggal_to != $r->tanggal_from)
                            – {{ \Illuminate\Support\Carbon::parse($r->tanggal_to)->format('d/m/Y') }}
                        @endif
                        @if($r->jam_from)
                            <span class="text-muted ml-2"><i class="fa fa-clock mr-1"></i>{{ substr($r->jam_from,0,5) }}@if($r->jam_to)–{{ substr($r->jam_to,0,5) }}@endif</span>
                        @endif
                        @if($r->claimed_menit)
                            <span class="text-muted ml-2">({{ round($r->claimed_menit/60,1) }} jam)</span>
                        @endif
                    </div>

                    @if($r->alasan)
                        <div class="mb-2">{{ $r->alasan }}</div>
                    @endif

                    @if($r->foto)
                        <a href="{{ asset('uploads/att_request/'.$r->foto) }}" target="_blank" class="small">
                            <i class="fa fa-image"></i> Lihat foto bukti
                        </a>
                    @endif

                    {{-- verifikasi vs scan = INFORMASI, bukan vonis (§4) --}}
                    @if($r->verifikasi)
                        <div class="small text-muted mt-1">Sanding scan: {{ str_replace('_',' ',$r->verifikasi) }}</div>
                    @endif

                    <form action="{{ route('persetujuan.act', $r->id) }}" method="POST" class="mt-3">
                        @csrf
                        <input type="text" name="catatan" class="form-control form-control-sm mb-2"
                               placeholder="Catatan (opsional)" maxlength="255">
                        <div class="d-flex">
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm flex-fill mr-2">
                                <i class="fa fa-check"></i> Setujui
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm flex-fill">
                                <i class="fa fa-times"></i> Tolak
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endif

    {{-- ── Riwayat tindakan ── --}}
    @if($recent->isNotEmpty())
        <h5 class="mt-4 mb-2">Sudah Ditindak</h5>
        <div class="card">
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @foreach($recent as $r)
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <div>
                                <span class="small">{{ $r->user->nama ?? 'Pengaju' }}</span>
                                <span class="badge badge-outline-secondary text-uppercase ml-1">{{ $tipeLabel[$r->tipe] ?? $r->tipe }}</span>
                                <div class="small text-muted">
                                    {{ \Illuminate\Support\Carbon::parse($r->tanggal_from)->format('d/m') }}
                                    @if($r->tanggal_to != $r->tanggal_from)– {{ \Illuminate\Support\Carbon::parse($r->tanggal_to)->format('d/m') }}@endif
                                </div>
                            </div>
                            <span class="badge badge-{{ $reqBadge[$r->status] ?? 'light' }}">{{ ucfirst($r->status) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
