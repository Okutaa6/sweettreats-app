@extends('layouts.app')

@section('content')
<style>
    .rv-seg { display:flex; background:#fff; border:1px solid #EADCC9; border-radius:12px; padding:4px; gap:3px; }
    .rv-seg a {
        display:inline-flex; align-items:center; gap:7px;
        border-radius:9px; padding:8px 15px;
        font-weight:700; font-size:12.5px; text-decoration:none;
        color:#7A5B43; transition:background-color .15s ease, filter .15s ease;
        white-space:nowrap;
    }
    .rv-seg a:hover { background:#F1E4D3; color:#5A3A22; }
    .rv-seg a.is-active--weekly { background:linear-gradient(120deg,#1F6E5F,#155148); color:#E4F3EF; }
    .rv-seg a.is-active--weekly:hover { filter:brightness(1.08); background:linear-gradient(120deg,#1F6E5F,#155148); }

    .rv-input {
        border:1px solid #E7D3BE; border-radius:11px; padding:9px 12px;
        font-family:inherit; font-size:13px; color:#3B1500; background:#fff;
        transition:border-color .15s ease, background-color .15s ease;
    }
    .rv-input:hover { border-color:#D9A94E; background:#FFFCF8; }
    .rv-input:focus { outline:none; border-color:#C9962F; }

    .rv-export { transition:border-color .15s ease, box-shadow .15s ease; }
    .rv-export:hover { border-color:#CFE6D6; box-shadow:0 3px 14px rgba(31,110,95,.10); }
    .rv-export-btn {
        display:inline-flex; align-items:center; gap:8px; white-space:nowrap;
        background:linear-gradient(135deg,#2F8E55,#1F6E5F); color:#fff;
        border:none; border-radius:10px; padding:9px 16px;
        font-weight:700; font-size:12.5px; font-family:inherit; cursor:pointer;
        transition:filter .15s ease, box-shadow .15s ease, transform .05s ease;
    }
    .rv-export-btn:hover { filter:brightness(1.08); box-shadow:0 5px 15px rgba(31,110,95,.28); }
    .rv-export-btn:active { transform:translateY(1px); }

    .rv-table { width:100%; border-collapse:collapse; min-width:760px; }
    .rv-table thead tr { background:linear-gradient(120deg,#183F38,#0F2F2A); }
    .rv-table thead th {
        padding:13px 18px; text-align:left;
        font-size:11px; font-weight:800; letter-spacing:.6px; color:#CFE6DF;
        text-transform:uppercase; border:none;
    }
    .rv-table tbody td { padding:12px 18px; border-bottom:1px solid #F4EADD; vertical-align:middle; }
    .rv-table tbody tr { transition:background .12s ease; }
    .rv-table tbody tr:hover { background:#F3F8F6; }

    .rv-btn-review {
        display:inline-flex; align-items:center; gap:6px;
        background:linear-gradient(135deg,#1F6E5F,#155148); color:#E4F3EF;
        border:none; border-radius:9px; padding:7px 15px;
        font-weight:700; font-size:12px; text-decoration:none; white-space:nowrap;
        transition:filter .15s ease;
    }
    .rv-btn-review:hover { filter:brightness(1.1); color:#E4F3EF; text-decoration:none; }
</style>
<div class="container-fluid">

    @include('layouts.notif')

    <div style="max-width:1240px;margin:0 auto">

    {{-- ─── HERO ─── --}}
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:18px 22px;box-shadow:0 1px 2px rgba(70,21,0,.04);margin-bottom:14px">
        <div style="flex:1;min-width:240px">
            <div style="font-size:21px;font-weight:800;color:#3B1500">Review &amp; Approve Checklist</div>
            <div style="font-size:13px;color:#9A7256;margin-top:3px">Pilih karyawan untuk melihat riwayat Form Mingguan &amp; melakukan approval per orang.</div>
        </div>
        <a href="{{ route('checklist') }}" class="cl-btn-dash" style="flex:none;background:#fff;color:#5A3A22;border:1px solid #E7D3BE;border-radius:11px;padding:10px 16px;font-weight:700;font-size:13px;text-decoration:none">← Dashboard</a>
    </div>

    {{-- ─── SEG ROW: divisi kiri · jenis (Harian/Mingguan) kanan ─── --}}
    @php $divParam = $filterDiv !== 'all' ? ['division' => $filterDiv] : []; @endphp
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">
        <div class="rv-seg">
            @foreach($divisions as $div)
                <a href="{{ route('checklist.review.weekly', array_merge(request()->except(['division','page']), ['division' => $div->id])) }}"
                   class="{{ $filterDiv === $div->id ? 'is-active--weekly' : '' }}">{{ $div->name }}</a>
            @endforeach
        </div>
        <div class="rv-seg" style="margin-left:auto">
            <a href="{{ route('checklist.review', $divParam) }}"><i class="fas fa-sun"></i> Checklist Harian</a>
            <a href="{{ route('checklist.review.weekly', $divParam) }}" class="is-active--weekly"><i class="fas fa-calendar-week"></i> Checklist Mingguan</a>
            @php
                $reopenPendingCount = \App\Models\ChecklistReopenRequest::whereIn('division_id', checklistVisibleDivisionIds())
                    ->where('status', \App\Models\ChecklistReopenRequest::STATUS_PENDING)->count();
            @endphp
            <a href="{{ route('checklist.reopen.index') }}"><i class="fas fa-unlock-alt"></i> Permintaan Buka
                @if($reopenPendingCount > 0)
                    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:#C0392B;color:#fff;font-size:10.5px;font-weight:800;">{{ $reopenPendingCount }}</span>
                @endif
            </a>
        </div>
    </div>

    {{-- ─── FILTER CARD ─── --}}
    <form method="GET" action="{{ route('checklist.review.weekly') }}"
          style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:13px 16px;display:flex;align-items:center;gap:11px;flex-wrap:wrap;box-shadow:0 1px 2px rgba(70,21,0,.04);margin-bottom:14px">
        <input type="hidden" name="division" value="{{ $filterDiv }}">

        @if(canSeeAllBranches() && $branches->count())
            <select name="branch" onchange="this.form.submit();" class="rv-input" style="cursor:pointer">
                <option value="">Semua Cabang</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>
        @elseif($branchId)
            <input type="hidden" name="branch" value="{{ $branchId }}">
        @endif

        <div style="position:relative;flex:1;min-width:190px">
            <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#B79A82;font-size:13px"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Nama karyawan…" class="rv-input" style="width:100%;box-sizing:border-box;padding-left:36px;background:#FFFCF8">
        </div>

        <button type="submit" style="background:linear-gradient(135deg,#1F6E5F,#155148);color:#E4F3EF;border:none;border-radius:10px;padding:9px 16px;font-weight:700;font-size:12.5px;font-family:inherit;cursor:pointer">Cari</button>

        @if($search || $filterDiv !== 'all')
            <a href="{{ route('checklist.review.weekly') }}" style="font-size:12px;font-weight:700;color:#B0855F;text-decoration:underline">Reset</a>
        @endif

        <span style="margin-left:auto;font-size:12.5px;color:#9A7256"><b style="color:#3B1500">{{ $people->count() }}</b> karyawan · <b style="color:#3B1500">{{ $people->sum('pending') }}</b> menunggu approval</span>
    </form>

    {{-- ─── EXPORT EXCEL MINGGUAN ─── --}}
    @php
        $exportDiv = $filterDiv !== 'all' ? $divisions->firstWhere('id', $filterDiv) : $divisions->first();
    @endphp
    @if($exportDiv)
    <form method="GET" action="{{ route('checklist.export.weekly') }}" class="rv-export"
          style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:13px 16px;display:flex;align-items:center;gap:11px;flex-wrap:wrap;box-shadow:0 1px 2px rgba(70,21,0,.04);margin-bottom:14px">
        <div style="display:flex;align-items:center;gap:11px;flex:1;min-width:210px">
            <div style="width:38px;height:38px;flex:none;border-radius:11px;background:#E3F4E8;display:flex;align-items:center;justify-content:center">
                <i class="fas fa-file-excel" style="color:#2F8E55;font-size:16px"></i>
            </div>
            <div style="min-width:0">
                <div style="font-size:13.5px;font-weight:800;color:#3B1500">Export Excel Checklist Mingguan — {{ $exportDiv->name }}</div>
                <div style="font-size:11.5px;color:#9A7256;margin-top:1px">Rekap divisi {{ $exportDiv->name }} 1 bulan — tugas per hari × minggu, tiap karyawan jadi tab sendiri</div>
            </div>
        </div>

        <input type="hidden" name="division" value="{{ $exportDiv->id }}">
        @if(canSeeAllBranches() && $branchId)
            <input type="hidden" name="branch" value="{{ $branchId }}">
        @endif

        <input type="month" name="month" value="{{ now()->format('Y-m') }}" max="{{ now()->format('Y-m') }}" class="rv-input" title="Bulan">

        <button type="submit" class="rv-export-btn">
            <i class="fas fa-download" style="font-size:12px"></i> Download Excel
        </button>
    </form>
    @endif

    {{-- ─── TABEL CHECKLIST KARYAWAN (MINGGUAN) ─── --}}
    <div style="background:#fff;border:1px solid #EFE0CE;border-radius:18px;overflow-x:auto;box-shadow:0 1px 2px rgba(70,21,0,.04),0 10px 26px rgba(70,21,0,.05)">
        @if($people->isEmpty())
            <div style="padding:34px 20px;text-align:center;font-size:12.5px;color:#9A7256">
                Tidak ada karyawan aktif untuk filter ini — coba ubah divisi, cabang, atau pencarian.
            </div>
        @else
            <table class="rv-table">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th style="width:160px;">Divisi</th>
                        <th style="width:130px;">Cabang</th>
                        <th style="width:160px;">Antrian Approval</th>
                        <th style="width:120px;">Lihat</th>
                    </tr>
                </thead>
                <tbody>
                    @php $avPaletteP = ['#8A3A12','#A8481B','#2F8E55','#3C5FA8','#B5701A','#7A3415','#5A6B2E','#8E2419']; @endphp
                    @foreach($people as $p)
                        @php
                            $avName     = trim($p->user?->nama ?? '');
                            $avParts    = preg_split('/\s+/', $avName, -1, PREG_SPLIT_NO_EMPTY);
                            $avInitials = strtoupper(mb_substr($avParts[0] ?? '?', 0, 1) . (isset($avParts[1]) ? mb_substr($avParts[1], 0, 1) : ''));
                            $avColor    = $avPaletteP[abs(crc32($avName !== '' ? $avName : '?')) % 8];
                            $empLink = $p->pending > 0 && $p->pendingMonth
                                ? ['user' => $p->user?->id, 'status' => 'submitted', 'month' => $p->pendingMonth]
                                : ['user' => $p->user?->id];
                        @endphp
                        <tr @if($p->pending > 0) style="background:#F5FAF8" @endif>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;min-width:0">
                                    <div style="width:32px;height:32px;border-radius:50%;background:{{ $avColor }};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:11px;flex-shrink:0">{{ $avInitials }}</div>
                                    <a href="{{ route('checklist.review.weekly.employee', $empLink) }}" style="font-size:13px;font-weight:700;color:#3B1500;text-decoration:none" title="Lihat Form Mingguan karyawan">{{ $p->user?->nama ?? '—' }}</a>
                                </div>
                            </td>
                            <td style="font-size:12.5px;color:#7A5B43;">{{ $p->division?->name ?? '—' }}</td>
                            <td style="font-size:12.5px;color:#7A5B43;">{{ $p->branch?->name ?? '—' }}</td>
                            <td>
                                @if($p->pending > 0)
                                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:800;padding:4px 11px;border-radius:20px;background:#FBEFD9;color:#B5701A;white-space:nowrap"><span style="width:6px;height:6px;border-radius:50%;background:#B5701A"></span>{{ $p->pending }} menunggu</span>
                                @else
                                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:4px 11px;border-radius:20px;background:#E3F4E8;color:#2F8E55;white-space:nowrap"><i class="fas fa-check" style="font-size:9px"></i> Tidak ada</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('checklist.review.weekly.employee', $empLink) }}" class="rv-btn-review">Lihat</a>
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
