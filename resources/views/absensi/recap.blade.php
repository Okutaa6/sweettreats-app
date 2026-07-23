@extends('layouts.app')

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/finance.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/finance.sweettreats.css')) ?: '1' }}">
@endsection

@php
    $jamOf = fn ($m) => $m ? rtrim(rtrim(number_format($m / 60, 1, ',', ''), '0'), ',') : '0';
    $hm = fn ($t) => $t ? substr($t, 0, 5) : null;
@endphp

@section('content')
<div class="fin-wrap">
    @include('layouts.notif')
    @include('absensi._finance_tabs')

    {{-- KPI --}}
    <div class="fin-kpis">
        <div class="fin-kpi">
            <div class="fin-kpi-k">PENGAJUAN DISETUJUI</div>
            <div class="fin-kpi-v num">{{ $summary['total'] }}</div>
            <div class="fin-kpi-s">periode ini · lintas cabang</div>
        </div>
        <div class="fin-kpi">
            <div class="fin-kpi-k">JAM LEMBUR DISETUJUI</div>
            <div class="fin-kpi-v num amber">{{ $jamOf($summary['lembur_menit']) }}<span class="fin-kpi-u">jam</span></div>
            <div class="fin-kpi-s">total yang diklaim</div>
        </div>
        <div class="fin-kpi warn">
            <div class="fin-kpi-k red">⚠ PERLU DICEK</div>
            <div class="fin-kpi-v num red">{{ $summary['selisih'] }}</div>
            <div class="fin-kpi-s">klaim lembur &gt; dukungan scan</div>
        </div>
        <div class="fin-kpi">
            <div class="fin-kpi-k">SCAN TAK BISA DICEK</div>
            <div class="fin-kpi-v num">{{ $summary['tidak_bisa_dicek'] }}</div>
            <div class="fin-kpi-s">WFH / scan belum diunggah</div>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ url('absensi/recap') }}" class="fin-filters" id="finFilter">
        <div class="fin-field">
            <label>PERIODE</label>
            <input type="month" name="m" value="{{ $anchor->format('Y-m') }}" class="fin-input" onchange="document.getElementById('finFilter').submit()">
        </div>
        <div class="fin-field">
            <label>CABANG</label>
            <select name="branch" class="fin-input" onchange="document.getElementById('finFilter').submit()">
                <option value="">Semua Cabang</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected($branchFilter === $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="fin-field">
            <label>JENIS</label>
            <select name="jenis" class="fin-input" onchange="document.getElementById('finFilter').submit()">
                <option value="">Semua Jenis</option>
                @foreach($tipeLabel as $k => $v)
                    <option value="{{ $k }}" @selected(($tipeFilter ?? '') === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <a href="{{ url('absensi/recap') }}?m={{ $anchor->format('Y-m') }}{{ $branchFilter ? '&branch='.$branchFilter : '' }}{{ ($tipeFilter ?? '') ? '&jenis='.$tipeFilter : '' }}{{ $verifFilter === 'selisih' ? '' : '&verif=selisih' }}"
           class="fin-checkbtn {{ $verifFilter === 'selisih' ? 'on' : '' }}">⚠ Hanya yang perlu dicek</a>
        <div class="fin-count">Menampilkan <b class="num">{{ $items->count() }}</b> pengajuan</div>
    </form>

    {{-- Tabel --}}
    <div class="fin-tablewrap">
        <table class="fin-table">
            <thead>
                <tr><th>PENGAJU</th><th>CABANG</th><th>JENIS</th><th>TANGGAL / JAM</th><th>SANDING-SCAN (KLAIM vs SCAN)</th></tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                    @php $need = $it->verifikasi === 'selisih'; @endphp
                    <tr class="{{ $need ? 'is-check' : '' }}">
                        <td>
                            <div class="fin-pengaju">{{ $it->user->nama ?? 'Pengaju' }}</div>
                            @if($it->foto)<div class="fin-foto"><i class="fa fa-image"></i> ada foto bukti</div>@endif
                        </td>
                        <td>{{ optional($it->branch)->name ?? '—' }}</td>
                        <td><span class="fin-type t-{{ $it->tipe }}">{{ $tipeLabel[$it->tipe] ?? $it->tipe }}</span></td>
                        <td>
                            <div class="num">{{ \Illuminate\Support\Carbon::parse($it->tanggal_from)->translatedFormat('d M Y') }}@if($it->tanggal_to != $it->tanggal_from) – {{ \Illuminate\Support\Carbon::parse($it->tanggal_to)->translatedFormat('d M') }}@endif</div>
                            <div class="fin-sub num">@if($it->jam_from){{ $hm($it->jam_from) }} – {{ $hm($it->jam_to) }}@else Sepanjang hari @endif</div>
                        </td>
                        <td>
                            @if($it->tipe === 'lembur')
                                @php $sup = (int) ($it->live_supported ?? 0); $klaim = (int) $it->claimed_menit; $sel = ($klaim - $sup) / 60; @endphp
                                @if($it->verifikasi === 'selisih')
                                    <span class="fin-verif red">● Perlu dicek</span>
                                @elseif($it->verifikasi === 'nyambung')
                                    <span class="fin-verif green">● Nyambung</span>
                                @else
                                    <span class="fin-verif gray">● Tak bisa dicek</span>
                                @endif
                                <span class="fin-sanding num">klaim {{ $jamOf($klaim) }} jam · scan {{ $jamOf($sup) }} jam @if($need && $sel != 0)<b class="fin-sel">{{ $sel > 0 ? '+' : '' }}{{ $jamOf($klaim - $sup) }}j</b>@endif</span>
                            @else
                                <span class="fin-nolembur">— bukan lembur —</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="fin-empty">Tak ada pengajuan disetujui di periode/filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
