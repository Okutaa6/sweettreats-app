@extends('layouts.app')

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/finance.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/finance.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
<div class="fin-wrap">
    @include('layouts.notif')
    @include('absensi._finance_tabs')

    <div class="fin-two">
        {{-- ── Buat periode ── --}}
        <div class="fin-panel">
            <div class="fin-panel-h"><i class="fa fa-calendar-plus"></i> Buat Periode Gaji</div>
            <p class="fin-panel-sub">
                Rentang cut-off gaji (mis. 26 s/d 25). Menutup periode → data absen di rentang itu
                dikunci (reconcile tak menimpanya lagi), lalu bisa di-export buat payroll.
            </p>
            <form action="{{ route('absensi.period.store') }}" method="POST" class="fin-form">
                @csrf
                <div class="fin-field">
                    <label>NAMA <span style="color:var(--fin-ink-3);font-weight:600">(opsional)</span></label>
                    <input type="text" name="nama" class="fin-input" placeholder="mis. Gaji Juli 2026" value="{{ old('nama') }}">
                </div>
                <div class="fin-row2">
                    <div class="fin-field">
                        <label>DARI</label>
                        <input type="date" name="periode_from" class="fin-input" value="{{ old('periode_from') }}" required>
                    </div>
                    <div class="fin-field">
                        <label>SAMPAI</label>
                        <input type="date" name="periode_to" class="fin-input" value="{{ old('periode_to') }}" required>
                    </div>
                </div>
                <button type="submit" class="fin-btn"><i class="fa fa-plus"></i> Buat Periode</button>
            </form>
        </div>

        {{-- ── Daftar periode ── --}}
        <div class="fin-tablewrap">
            @if($periods->isEmpty())
                <div class="fin-empty">Belum ada periode.</div>
            @else
                <table class="fin-table">
                    <thead>
                        <tr><th>PERIODE</th><th>RENTANG</th><th>STATUS</th><th>DITUTUP OLEH</th><th style="text-align:right">AKSI</th></tr>
                    </thead>
                    <tbody>
                        @foreach($periods as $p)
                            <tr>
                                <td class="fin-pengaju">{{ $p->nama ?: '—' }}</td>
                                <td class="num" style="white-space:nowrap">{{ $p->periode_from->format('d/m/y') }} – {{ $p->periode_to->format('d/m/y') }}</td>
                                <td>
                                    @if($p->isClosed())
                                        <span class="fin-badge lock"><i class="fa fa-lock"></i> Ditutup</span>
                                    @else
                                        <span class="fin-badge ok">● Terbuka</span>
                                    @endif
                                </td>
                                <td class="fin-sub" style="white-space:nowrap">
                                    @if($p->isClosed())
                                        {{ optional($p->closer)->nama ?? '—' }}<br><span class="num">{{ optional($p->closed_at)->format('d/m H:i') }}</span>
                                    @else — @endif
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    <a href="{{ route('absensi.period.export', $p->id) }}" class="fin-btn-icon" title="Export payroll CSV"><i class="fa fa-download"></i></a>
                                    @if(! $p->isClosed())
                                        <form action="{{ route('absensi.period.close', $p->id) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Tutup periode ini? Data absen di rentangnya akan terkunci.');">
                                            @csrf
                                            <button type="submit" class="fin-btn-danger" title="Tutup & kunci periode"><i class="fa fa-lock"></i> Tutup</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
