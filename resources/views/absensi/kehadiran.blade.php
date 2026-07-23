@extends('layouts.app')

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/finance.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/finance.sweettreats.css')) ?: '1' }}">
@endsection

@php $gridCols = '210px repeat(' . $daysInMonth . ', 30px)'; @endphp

@section('content')
<div class="fin-wrap">
    @include('layouts.notif')
    @include('absensi._finance_tabs')

    {{-- KPI --}}
    <div class="fin-kpis">
        <div class="fin-kpi">
            <div class="fin-kpi-k">KARYAWAN</div>
            <div class="fin-kpi-v num">{{ $summary['karyawan'] }}</div>
            <div class="fin-kpi-s">punya catatan absen periode ini</div>
        </div>
        <div class="fin-kpi">
            <div class="fin-kpi-k">TOTAL HADIR</div>
            <div class="fin-kpi-v num">{{ $summary['hadir'] }}</div>
            <div class="fin-kpi-s">hari-orang masuk normal</div>
        </div>
        <div class="fin-kpi">
            <div class="fin-kpi-k">TOTAL TELAT</div>
            <div class="fin-kpi-v num amber">{{ $summary['telat'] }}</div>
            <div class="fin-kpi-s">hari-orang datang terlambat</div>
        </div>
        <div class="fin-kpi warn">
            <div class="fin-kpi-k red">⚠ TIDAK MASUK</div>
            <div class="fin-kpi-v num red">{{ $summary['alpa'] }}</div>
            <div class="fin-kpi-s">hari-orang tanpa keterangan</div>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ url('absensi/kehadiran') }}" class="fin-filters" id="khFilter">
        <div class="fin-field">
            <label>PERIODE</label>
            <input type="month" name="m" value="{{ $anchor->format('Y-m') }}" class="fin-input" onchange="document.getElementById('khFilter').submit()">
        </div>
        <div class="fin-field">
            <label>CABANG</label>
            <select name="branch" class="fin-input" onchange="document.getElementById('khFilter').submit()">
                <option value="">Semua Cabang</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected($branchFilter === $b->id)>{{ $b->name }}</option>
                @endforeach
                <option value="{{ $abgKey }}" @selected($branchFilter === $abgKey)>{{ $abgLabel }}</option>
            </select>
        </div>
        <div class="fin-count">Dikelompokkan per <b>cabang asal</b> · <b class="num">{{ $summary['karyawan'] }}</b> karyawan</div>
    </form>

    {{-- Matriks --}}
    <div class="khx-card">
        @if(empty($groups))
            <div class="fin-empty">Belum ada catatan absen di periode / cabang ini.</div>
        @else
            <div class="khx-scroll">
                <div class="khx-inner">
                    {{-- header hari --}}
                    <div class="khx-head" style="grid-template-columns:{{ $gridCols }}">
                        <div class="khx-head-name">KARYAWAN</div>
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            <div class="khx-head-day">
                                <div class="khx-dow {{ $head[$d]['sun'] ? 'sun' : '' }}">{{ $head[$d]['dow'] }}</div>
                                <div class="num khx-dn">{{ $head[$d]['n'] }}</div>
                            </div>
                        @endfor
                    </div>

                    @foreach($groups as $g)
                        {{-- pembatas per cabang asal --}}
                        <div class="khx-sep {{ $g['flex'] ? 'is-abg' : '' }}">
                            <span class="khx-sep-label">{{ $g['label'] }} · <span class="num">{{ count($g['rows']) }}</span> orang
                                @if($g['flex'])<span class="khx-sep-note">jam bebas — scan tak dinilai</span>@endif
                            </span>
                        </div>

                        @foreach($g['rows'] as $row)
                            <div class="khx-row" style="grid-template-columns:{{ $gridCols }}">
                                <div class="khx-name">
                                    <div class="khx-av">{{ $row['initial'] }}</div>
                                    <div class="khx-name-tx">
                                        <div class="khx-nm">{{ $row['nama'] }}</div>
                                        <div class="khx-meta">{{ $row['label'] }} · <span class="num khx-h">{{ $row['h'] }}</span> <span class="num khx-t">{{ $row['t'] }}</span> <span class="num khx-a">{{ $row['a'] }}</span></div>
                                    </div>
                                </div>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    @php $c = $row['cells'][$d] ?? null; @endphp
                                    @if($c && isset($statusMeta[$c['status']]))
                                        @php
                                            $m = $statusMeta[$c['status']];
                                            $tip = $head[$d]['n'] . ' ' . $monthShort . ' · ' . $m['label']
                                                . ($c['telat'] ? ' · telat ' . $c['telat'] . ' mnt' : '')
                                                . ($c['lembur'] ? ' · lembur ' . round($c['lembur'] / 60, 1) . ' j' : '')
                                                . ($c['single'] ? ' · scan tunggal' : '')
                                                . ($c['lain'] ? ' · scan di ' . implode(', ', $c['lain']) : '');
                                        @endphp
                                        <div class="khx-cell" style="background:{{ $m['bg'] }}" title="{{ $tip }}">
                                            @if($c['lembur'])<span class="khx-lembur"></span>@endif
                                            @if($c['lain'])<span class="khx-lain"></span>@endif
                                        </div>
                                    @else
                                        <div class="khx-cell khx-empty" title="belum ada data"></div>
                                    @endif
                                @endfor
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>

            {{-- legenda --}}
            <div class="khx-legend">
                @foreach($statusMeta as $key => $m)
                    <span class="khx-leg"><span class="khx-sw" style="background:{{ $m['bg'] }}"></span>{{ $m['label'] }}</span>
                @endforeach
                <span class="khx-leg"><span class="khx-sw-dot"></span>ada lembur</span>
                <span class="khx-leg"><span class="khx-sw-lain"></span>scan di cabang lain</span>
                <span class="khx-leg"><span class="khx-sw-dash"></span>belum ada data</span>
            </div>
        @endif
    </div>
</div>
@endsection
