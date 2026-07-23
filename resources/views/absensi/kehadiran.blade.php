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
            </select>
        </div>
        {{-- Legenda --}}
        <div class="kh-legend">
            @foreach($statusMeta as $key => [$huruf, $kelas, $label])
                <span class="kh-leg"><span class="kh-cell s-{{ $kelas }}">{{ $huruf }}</span> {{ $label }}</span>
            @endforeach
        </div>
    </form>

    {{-- Matriks --}}
    <div class="fin-tablewrap">
        @if(empty($matrix))
            <div class="fin-empty">Belum ada catatan absen di periode / cabang ini.</div>
        @else
            <div class="kh-scroll">
                <table class="kh-table">
                    <thead>
                        <tr>
                            <th class="kh-name-h">PENGAJU</th>
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                <th class="kh-day {{ $weekend[$d] ? 'kh-wend' : '' }}">{{ $d }}</th>
                            @endfor
                            <th class="kh-sum-h">H</th><th class="kh-sum-h">T</th><th class="kh-sum-h">A</th><th class="kh-sum-h">C/W/L</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($matrix as $row)
                            <tr>
                                <td class="kh-name">
                                    <div class="kh-name-t">{{ $row['nama'] }}</div>
                                    <div class="kh-name-b">{{ $row['cabang'] }}</div>
                                </td>
                                @for($d = 1; $d <= $daysInMonth; $d++)
                                    @php $c = $row['cells'][$d] ?? null; @endphp
                                    @if($c && isset($statusMeta[$c['status']]))
                                        @php [$huruf, $kelas, $label] = $statusMeta[$c['status']]; @endphp
                                        <td class="kh-td {{ $weekend[$d] ? 'kh-wend' : '' }}">
                                            <span class="kh-cell s-{{ $kelas }}{{ $c['single'] ? ' kh-single' : '' }}"
                                                  title="{{ $label }}{{ $c['telat_menit'] ? ' · telat '.$c['telat_menit'].' mnt' : '' }}{{ $c['single'] ? ' · scan tunggal' : '' }}">{{ $huruf }}</span>
                                        </td>
                                    @else
                                        <td class="kh-td {{ $weekend[$d] ? 'kh-wend' : '' }}"><span class="kh-dot">·</span></td>
                                    @endif
                                @endfor
                                <td class="kh-sum num">{{ $row['sum']['hadir'] ?: '' }}</td>
                                <td class="kh-sum num {{ $row['sum']['telat'] ? 'amber' : '' }}">{{ $row['sum']['telat'] ?: '' }}</td>
                                <td class="kh-sum num {{ $row['sum']['alpa'] ? 'red' : '' }}">{{ $row['sum']['alpa'] ?: '' }}</td>
                                <td class="kh-sum num">{{ ($row['sum']['cuti'] + $row['sum']['wfh'] + $row['sum']['libur']) ?: '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <p class="fin-hint" style="margin-top:10px">Sel bergaris bawah = scan cuma sekali (masuk/pulang tak lengkap). Kolom redup = Minggu. Arahkan kursor ke sel buat detail.</p>
</div>
@endsection
