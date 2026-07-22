@extends('layouts.app')

@php
    $badge = [
        'cuti' => 'info', 'izin' => 'info', 'libur' => 'secondary',
        'lembur' => 'primary', 'wfh' => 'warning',
    ];
    $verifBadge = ['nyambung' => 'success', 'selisih' => 'warning', 'tidak_bisa_dicek' => 'secondary'];
@endphp

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Absensi — Rekap Persetujuan</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ── Filter periode + cabang ── --}}
    <div class="card">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('absensi.recap') }}" class="form-inline">
                <label class="mr-2 mb-0">Periode</label>
                <a href="{{ route('absensi.recap', ['m' => $anchor->copy()->subMonth()->format('Y-m'), 'branch' => $branchFilter, 'verif' => $verifFilter]) }}"
                   class="btn btn-outline-secondary btn-sm mr-1"><i class="fa fa-chevron-left"></i></a>
                <input type="month" name="m" value="{{ $anchor->format('Y-m') }}" class="form-control form-control-sm mr-1">
                <a href="{{ route('absensi.recap', ['m' => $anchor->copy()->addMonth()->format('Y-m'), 'branch' => $branchFilter, 'verif' => $verifFilter]) }}"
                   class="btn btn-outline-secondary btn-sm mr-3"><i class="fa fa-chevron-right"></i></a>

                <label class="mr-2 mb-0">Cabang</label>
                <select name="branch" class="form-control form-control-sm mr-2">
                    <option value="">Semua cabang</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected($branchFilter === $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
                <div class="form-check mr-3">
                    <input type="checkbox" name="verif" value="selisih" class="form-check-input" id="fVerif" @checked($verifFilter === 'selisih')>
                    <label class="form-check-label small" for="fVerif">cuma perlu dicek</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
            </form>
        </div>
    </div>

    {{-- ── Ringkasan ── --}}
    <div class="row">
        @foreach(['total'=>'Total','cuti'=>'Cuti','izin'=>'Izin','libur'=>'Libur','wfh'=>'WFH','lembur'=>'Lembur'] as $k => $lbl)
            <div class="col">
                <div class="card mb-2 text-center">
                    <div class="card-body py-2">
                        <div class="h4 mb-0">{{ $summary[$k] }}</div>
                        <small class="text-muted">{{ $lbl }}</small>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @if($summary['lembur_menit'] || $summary['selisih'])
        <p class="small mb-3">
            @if($summary['lembur_menit'])
                <span class="text-muted">Total lembur disetujui: <strong>{{ round($summary['lembur_menit']/60, 1) }} jam</strong>
                ({{ $summary['lembur_menit'] }} menit).</span>
            @endif
            @if($summary['selisih'])
                <span class="badge badge-warning ml-1"><i class="fa fa-exclamation-triangle"></i>
                    {{ $summary['selisih'] }} lembur perlu dicek</span>
                <span class="text-muted">(klaim &gt; dukungan scan)</span>
            @endif
        </p>
    @endif

    {{-- ── Daftar approved ── --}}
    <div class="card">
        <div class="card-body">
            <h5 class="mb-3"><i class="fa fa-check-circle text-muted"></i>
                Pengajuan Disetujui — {{ $anchor->translatedFormat('F Y') }}</h5>

            @if($items->isEmpty())
                <p class="text-muted mb-0">Tidak ada pengajuan disetujui pada periode/cabang ini.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Pengaju</th>
                                <th>Cabang</th>
                                <th>Jenis</th>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th class="text-right">Lembur</th>
                                <th>Sanding scan</th>
                                <th>Alasan</th>
                                <th>Bukti</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $r)
                                <tr class="{{ $r->verifikasi === 'selisih' ? 'table-warning' : '' }}">
                                    <td style="white-space:nowrap">{{ $r->user->nama ?? '—' }}</td>
                                    <td>{{ $r->branch->name ?? '—' }}</td>
                                    <td><span class="badge badge-{{ $badge[$r->tipe] ?? 'light' }}">{{ $tipeLabel[$r->tipe] ?? $r->tipe }}</span></td>
                                    <td style="white-space:nowrap">
                                        {{ \Illuminate\Support\Carbon::parse($r->tanggal_from)->format('d/m') }}
                                        @if($r->tanggal_to != $r->tanggal_from)
                                            –{{ \Illuminate\Support\Carbon::parse($r->tanggal_to)->format('d/m') }}
                                        @endif
                                    </td>
                                    <td style="white-space:nowrap">
                                        @if($r->jam_from){{ substr($r->jam_from,0,5) }}@if($r->jam_to)–{{ substr($r->jam_to,0,5) }}@endif @else — @endif
                                    </td>
                                    <td class="text-right">
                                        @if($r->claimed_menit){{ round($r->claimed_menit/60,1) }}j @else — @endif
                                    </td>
                                    <td style="white-space:nowrap">
                                        @if($r->verifikasi)
                                            <span class="badge badge-{{ $verifBadge[$r->verifikasi] ?? 'light' }}">{{ str_replace('_',' ',$r->verifikasi) }}</span>
                                            @if($r->tipe === 'lembur' && isset($r->live_supported) && $r->live_supported !== null && $r->claimed_menit)
                                                <div class="small text-muted">
                                                    klaim {{ round($r->claimed_menit/60,1) }}j / scan dukung {{ round($r->live_supported/60,1) }}j
                                                </div>
                                            @endif
                                        @else <span class="text-muted">—</span> @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($r->alasan, 40) }}</td>
                                    <td>
                                        @if($r->foto)
                                            <a href="{{ asset('uploads/att_request/'.$r->foto) }}" target="_blank"><i class="fa fa-image"></i></a>
                                        @else <span class="text-muted">—</span> @endif
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
@endsection
