@extends('layouts.app')

@section('content')
<style>
    .re-navbtn { width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; background:#FAF4EC; color:#5C2E15; text-decoration:none; border:1px solid #E0D2C1; transition:background .15s; }
    .re-navbtn:hover { background:#F1E4D3; color:#5C2E15; }
    .re-btn-approve { display:inline-flex; align-items:center; gap:6px; background:#1F6E5F; color:#fff; border:none; border-radius:9px; padding:8px 13px; font-family:inherit; font-weight:800; font-size:12px; cursor:pointer; }
    .re-btn-approve:hover { filter:brightness(1.08); }
    .re-btn-eye { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:9px; background:#F1F7F5; color:#1F6E5F; border:1px solid #CFE6DF; text-decoration:none; }
    .re-btn-eye:hover { background:#E4F0EC; color:#1F6E5F; }
    .re-table { width:100%; border-collapse:collapse; }
    .re-table thead th { text-align:left; font-size:10.5px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:#9A7256; padding:11px 16px; border-bottom:1px solid #F0E6D8; background:#FDFBF7; white-space:nowrap; }
    .re-table tbody td { padding:12px 16px; border-bottom:1px solid #F4EADD; vertical-align:middle; }
    .re-table tbody tr:last-child td { border-bottom:none; }
    .re-chip { display:inline-flex; align-items:center; gap:7px; border-radius:20px; padding:7px 13px; font-weight:700; font-size:11.5px; text-decoration:none; background:#fff; color:#7A5B43; border:1px solid #EADCC9; white-space:nowrap; transition:background .15s, border-color .15s; }
    .re-chip:hover { background:#FBF4EC; color:#5A3A22; border-color:#D9C3AC; }
    .re-chip__badge { min-width:18px; text-align:center; font-size:10px; font-weight:800; border-radius:20px; padding:1px 6px; background:#EADCC9; color:#7A5B43; font-variant-numeric:tabular-nums; }
</style>

@php
    $stMap = [
        'submitted' => ['label' => 'Menunggu Approval', 'c' => '#B5701A', 'bg' => '#FBEFD9'],
        'approved'  => ['label' => 'Disetujui',          'c' => '#2F8E55', 'bg' => '#E3F4E8'],
        'rejected'  => ['label' => 'Ditolak',            'c' => '#C0392B', 'bg' => '#FBE3DE'],
        'excused'   => ['label' => 'Dikecualikan',       'c' => '#8A5A2B', 'bg' => '#F2E7D8'],
        'missed'    => ['label' => 'Tidak Diisi',        'c' => '#9B8578', 'bg' => '#EFE7DE'],
        'draft'     => ['label' => 'Belum Dikirim',      'c' => '#9B8578', 'bg' => '#EFE7DE'],
    ];
    $avName     = trim($employee->nama ?? '');
    $avParts    = preg_split('/\s+/', $avName, -1, PREG_SPLIT_NO_EMPTY);
    $avInitials = strtoupper(mb_substr($avParts[0] ?? '?', 0, 1) . (isset($avParts[1]) ? mb_substr($avParts[1], 0, 1) : ''));
    $avPalette  = ['#8A3A12','#A8481B','#2F8E55','#3C5FA8','#B5701A','#7A3415','#5A6B2E','#8E2419'];
    $avColor    = $avPalette[abs(crc32($avName !== '' ? $avName : '?')) % 8];
@endphp

<div style="padding:22px 20px 40px;font-family:'Plus Jakarta Sans',system-ui,sans-serif">
    <div style="max-width:1000px;margin:0 auto">

    {{-- Flash --}}
    @if(session('success'))
        <div style="background:#E3F4E8;border:1px solid #B9E0C6;color:#256F42;border-radius:12px;padding:11px 15px;font-size:12.5px;font-weight:700;margin-bottom:12px">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="background:#FBE3DE;border:1px solid #EEC2B9;color:#A5382A;border-radius:12px;padding:11px 15px;font-size:12.5px;font-weight:700;margin-bottom:12px">{{ session('error') }}</div>
    @endif

    {{-- HERO: identitas karyawan --}}
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:16px 20px;box-shadow:0 1px 2px rgba(70,21,0,.04);margin-bottom:14px">
        <a href="{{ route('checklist.review.weekly') }}" class="re-navbtn" title="Kembali ke daftar karyawan"><i class="fas fa-arrow-left" style="font-size:13px"></i></a>
        <div style="width:46px;height:46px;border-radius:50%;background:{{ $avColor }};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;flex-shrink:0">{{ $avInitials }}</div>
        <div style="flex:1;min-width:200px">
            <div style="font-size:19px;font-weight:800;color:#3B1500">{{ $employee->nama ?? '—' }}</div>
            <div style="font-size:12.5px;color:#9A7256;margin-top:2px">
                {{ $empDiv?->name ?? '—' }} · {{ $employee->branch?->name ?? '—' }} · Form Mingguan
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
            <div style="text-align:right">
                <div style="font-size:10.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#B0855F">Hari Ini</div>
                <div style="font-size:13.5px;font-weight:800;color:#3B1500;white-space:nowrap">{{ now()->translatedFormat('l, d F Y') }}</div>
            </div>
            @if($stats['pending'] > 0)
                <form method="POST" action="{{ route('checklist.review.weekly.employee.approve-pending', ['user' => $employee->id]) }}"
                      onsubmit="return confirm('Setujui semua {{ $stats['pending'] }} Form Mingguan yang menunggu approval di {{ $monthLabel }}?')">
                    @csrf @method('PATCH')
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button type="submit" class="re-btn-approve" style="padding:10px 16px;font-size:12.5px">
                        <i class="fas fa-check-double" style="font-size:12px"></i> Approve semua ({{ $stats['pending'] }})
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- TOOLBAR: navigasi bulan (panah) + chip filter status --}}
    @php
        $navStatus = array_filter(['status' => $status]);
        $statusChips = [
            'submitted' => ['label' => 'Menunggu Approval', 'count' => $stats['pending'],  'c' => '#B5701A', 'bg' => '#FBEFD9'],
            'approved'  => ['label' => 'Disetujui',          'count' => $stats['approved'], 'c' => '#2F8E55', 'bg' => '#E3F4E8'],
            'rejected'  => ['label' => 'Ditolak',            'count' => $stats['rejected'], 'c' => '#C0392B', 'bg' => '#FBE3DE'],
            'excused'   => ['label' => 'Dikecualikan',       'count' => $stats['excused'],  'c' => '#8A5A2B', 'bg' => '#F2E7D8'],
            'missed'    => ['label' => 'Tidak Diisi',        'count' => $stats['missed'],   'c' => '#9B8578', 'bg' => '#EFE7DE'],
            'draft'     => ['label' => 'Belum Dikirim',      'count' => $stats['draft'],    'c' => '#9B8578', 'bg' => '#EFE7DE'],
        ];
    @endphp
    <div style="background:#fff;border:1px solid #EFE0CE;border-radius:14px;box-shadow:0 1px 2px rgba(70,21,0,.04);margin-bottom:14px;overflow:hidden">
        <div style="display:flex;align-items:center;gap:10px;padding:12px 16px">
            <a href="{{ route('checklist.review.weekly.employee', array_merge(['user' => $employee->id, 'month' => $prevMonth], $navStatus)) }}" class="re-navbtn"><i class="fas fa-chevron-left" style="font-size:12px"></i></a>
            <div style="font-size:15.5px;font-weight:800;color:#2D1810;min-width:150px;text-align:center">{{ $monthLabel }}</div>
            <a href="{{ route('checklist.review.weekly.employee', array_merge(['user' => $employee->id, 'month' => $nextMonth], $navStatus)) }}" class="re-navbtn"><i class="fas fa-chevron-right" style="font-size:12px"></i></a>
        </div>
        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;padding:11px 18px;border-top:1px solid #F0E6D8;background:#FDFBF7">
            <a href="{{ route('checklist.review.weekly.employee', ['user' => $employee->id, 'month' => $month]) }}"
               class="re-chip" @if(!$status) style="background:#1F6E5F;color:#E4F3EF;border-color:#1F6E5F" @endif>
                Semua <span class="re-chip__badge" @if(!$status) style="background:#E4F3EF;color:#1F6E5F" @endif>{{ $stats['total'] }}</span>
            </a>
            @foreach($statusChips as $key => $chip)
                @php $on = $status === $key; @endphp
                <a href="{{ route('checklist.review.weekly.employee', ['user' => $employee->id, 'month' => $month, 'status' => $key]) }}"
                   class="re-chip" @if($on) style="background:{{ $chip['bg'] }};color:{{ $chip['c'] }};border-color:{{ $chip['c'] }}55" @endif>
                    <span style="width:7px;height:7px;border-radius:50%;background:{{ $chip['c'] }}"></span>
                    {{ $chip['label'] }}
                    @if($chip['count'] > 0)<span class="re-chip__badge" @if($on) style="background:{{ $chip['c'] }};color:#fff" @endif>{{ $chip['count'] }}</span>@endif
                </a>
            @endforeach
        </div>
    </div>

    {{-- DAFTAR MINGGU (tabel) --}}
    <div style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;overflow:hidden;box-shadow:0 1px 2px rgba(70,21,0,.04)">
        @if($subs->isEmpty())
            <div style="padding:34px 20px;text-align:center;font-size:12.5px;color:#9A7256">
                @if($status)
                    Tidak ada Form Mingguan berstatus ini di {{ $monthLabel }}.
                @else
                    Belum ada Form Mingguan untuk {{ $monthLabel }}.
                @endif
            </div>
        @else
            <table class="re-table">
                <thead>
                    <tr>
                        <th style="width:200px">Minggu</th>
                        <th>Progres</th>
                        <th style="width:150px">Status</th>
                        <th style="width:150px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subs as $s)
                        @php
                            $st  = $stMap[$s->status] ?? ['label' => $s->status, 'c' => '#9B8578', 'bg' => '#EFE7DE'];
                            $pct = $s->progressPct();
                            $barColor = $s->status === 'excused' ? '#C7AC92' : ($pct >= 80 ? '#2F8E55' : ($pct >= 50 ? '#C9962F' : '#C0392B'));
                        @endphp
                        <tr>
                            <td>
                                <div style="font-size:13px;font-weight:800;color:#3B1500;font-variant-numeric:tabular-nums">{{ $s->weekLabel() }}</div>
                                @if($s->submitted_at)
                                    <div style="font-size:10.5px;color:#B79A82;margin-top:2px">submit {{ $s->submitted_at->format('d M · H:i') }}</div>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:9px;min-width:110px">
                                    <div style="flex:1;min-width:56px;height:7px;border-radius:6px;background:#F1E6D7;overflow:hidden"><div style="height:100%;width:{{ $pct }}%;background:{{ $barColor }};border-radius:6px"></div></div>
                                    <span style="font-size:11.5px;font-weight:800;color:#5A3A22;flex:none;font-variant-numeric:tabular-nums">{{ $s->done_tasks }}/{{ $s->total_tasks }}</span>
                                </div>
                            </td>
                            <td>
                                <span style="display:inline-flex;align-items:center;font-size:11px;font-weight:800;padding:4px 11px;border-radius:20px;background:{{ $st['bg'] }};color:{{ $st['c'] }};white-space:nowrap">{{ $st['label'] }}</span>
                                @if($s->pending_days > 0)
                                    <div style="font-size:10.5px;color:#B5701A;font-weight:700;margin-top:3px"><i class="fas fa-clock" style="font-size:9px"></i> {{ $s->pending_days }} hari menunggu review</div>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:7px">
                                    <a href="{{ route('checklist.review.weekly.show', ['id' => $s->id, 'return_user' => $employee->id, 'return_month' => $month]) }}" class="re-btn-eye" title="{{ $s->pending_days > 0 ? 'Detail / Setujui / Tolak per hari' : 'Lihat detail' }}"><i class="fas fa-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    </div>{{-- /max-width --}}
</div>
@endsection
