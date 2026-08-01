@extends('layouts.app')

@section('content')
<style>
    /* ── Review list Mingguan — gaya handoff (paritas dgn review Harian) ── */
    .rv-seg { display:flex; background:#fff; border:1px solid #EADCC9; border-radius:12px; padding:4px; gap:3px; }
    .rv-seg a {
        display:inline-flex; align-items:center; gap:7px;
        border-radius:9px; padding:8px 15px;
        font-weight:700; font-size:12.5px; text-decoration:none;
        color:#7A5B43; transition:background-color .15s ease, filter .15s ease;
        white-space:nowrap;
    }
    .rv-seg a:hover { background:#F1E4D3; color:#5A3A22; }
    .rv-seg a.is-active { background:linear-gradient(135deg,#6B2400,#461500); color:#FFEAD6; }
    .rv-seg a.is-active:hover { filter:brightness(1.08); background:linear-gradient(135deg,#6B2400,#461500); }
    .rv-seg a.is-active--weekly { background:linear-gradient(120deg,#1F6E5F,#155148); color:#E4F3EF; }
    .rv-seg a.is-active--weekly:hover { filter:brightness(1.08); background:linear-gradient(120deg,#1F6E5F,#155148); }

    .rv-sttab {
        display:inline-flex; align-items:center; gap:8px;
        border-radius:20px; padding:8px 15px;
        font-weight:700; font-size:12px; text-decoration:none;
        background:#fff; color:#7A5B43; border:1px solid #EADCC9;
        transition:background-color .15s ease, border-color .15s ease;
        white-space:nowrap;
    }
    .rv-sttab:hover { background:#FBF4EC; color:#5A3A22; border-color:#D9C3AC; }
    .rv-sttab__dot { width:7px; height:7px; border-radius:50%; flex:none; }
    .rv-sttab__badge {
        min-width:19px; text-align:center;
        font-size:10.5px; font-weight:800; border-radius:20px; padding:1px 6px;
        background:#EADCC9; color:#7A5B43;
        font-variant-numeric:tabular-nums;
    }

    .rv-input {
        border:1px solid #E7D3BE; border-radius:11px; padding:9px 12px;
        font-family:inherit; font-size:13px; color:#3B1500; background:#fff;
        transition:border-color .15s ease, background-color .15s ease;
    }
    .rv-input:hover { border-color:#D9A94E; background:#FFFCF8; }
    .rv-input:focus { outline:none; border-color:#C9962F; }

    /* ── Export card ── */
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

    .rv-table { width:100%; border-collapse:collapse; min-width:820px; }
    .rv-table thead tr { background:linear-gradient(120deg,#3A1505,#52200A); }
    .rv-table thead th {
        padding:13px 18px; text-align:left;
        font-size:11px; font-weight:800; letter-spacing:.6px; color:#EAD3BC;
        text-transform:uppercase; border:none;
    }
    .rv-table tbody td { padding:12px 18px; border-bottom:1px solid #F4EADD; vertical-align:middle; }
    .rv-table tbody tr { transition:background .12s ease; }
    .rv-table tbody tr:hover { background:#FBF4EC; }

    .rv-btn-review {
        display:inline-flex; align-items:center; gap:6px;
        background:linear-gradient(135deg,#6B2400,#461500); color:#FFEAD6;
        border:none; border-radius:9px; padding:7px 15px;
        font-weight:700; font-size:12px; text-decoration:none; white-space:nowrap;
        transition:filter .15s ease;
    }
    .rv-btn-review:hover { filter:brightness(1.1); color:#FFEAD6; text-decoration:none; }
    .rv-btn-eye {
        display:inline-flex; align-items:center; justify-content:center;
        width:32px; height:32px; border-radius:9px;
        border:1px solid #E7D3BE; background:#fff; color:#7A5B43;
        text-decoration:none; transition:background-color .15s ease, border-color .15s ease;
    }
    .rv-btn-eye:hover { background:#FBF4EC; border-color:#D9C3AC; color:#5A3A22; }
</style>
<div class="container-fluid">

    @include('layouts.notif')

    <div style="max-width:1240px;margin:0 auto">

    {{-- ─── HERO ─── --}}
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:18px 22px;box-shadow:0 1px 2px rgba(70,21,0,.04);margin-bottom:14px">
        <div style="flex:1;min-width:240px">
            <div style="font-size:21px;font-weight:800;color:#3B1500">Review &amp; Approve Checklist</div>
            <div style="font-size:13px;color:#9A7256;margin-top:3px">Verifikasi pengisian Form Mingguan karyawan. Filter berdasarkan minggu, divisi, dan status.</div>
        </div>
        <a href="{{ route('checklist') }}" class="cl-btn-dash" style="flex:none;background:#fff;color:#5A3A22;border:1px solid #E7D3BE;border-radius:11px;padding:10px 16px;font-weight:700;font-size:13px;text-decoration:none">← Dashboard</a>
    </div>

    {{-- ─── SEG ROW: divisi kiri · jenis (Harian/Mingguan) kanan ─── --}}
    @php $divParam = $filterDiv !== 'all' ? ['division' => $filterDiv] : []; @endphp
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">
        <div class="rv-seg">
            @foreach($divisions as $div)
                <a href="{{ route('checklist.review.weekly', array_merge(request()->except(['division','page']), ['division' => $div->id])) }}"
                   class="{{ $filterDiv === $div->id ? 'is-active' : '' }}">{{ $div->name }}</a>
            @endforeach
        </div>
        <div class="rv-seg" style="margin-left:auto">
            <a href="{{ route('checklist.review', $divParam) }}"><i class="fas fa-sun"></i> Checklist Harian</a>
            <a href="{{ route('checklist.review.weekly', $divParam) }}" class="is-active--weekly"><i class="fas fa-calendar-week"></i> Checklist Mingguan</a>
        </div>
    </div>

    {{-- ─── TAB STATUS (chip berbadge) ─── --}}
    @php
        $statusTabs = [
            'submitted' => ['label' => 'Menunggu Approval', 'count' => $counts['submitted'], 'c' => '#B5701A', 'bg' => '#FBEFD9'],
            'approved'  => ['label' => 'Disetujui',          'count' => $counts['approved'],  'c' => '#2F8E55', 'bg' => '#E3F4E8'],
            'rejected'  => ['label' => 'Ditolak',            'count' => $counts['rejected'],  'c' => '#C0392B', 'bg' => '#FBE3DE'],
            'excused'   => ['label' => 'Dikecualikan',       'count' => $counts['excused'],   'c' => '#8A5A2B', 'bg' => '#F2E7D8'],
            'missed'    => ['label' => 'Tidak Diisi',        'count' => $counts['missed'],    'c' => '#9B8578', 'bg' => '#EFE7DE'],
        ];
    @endphp
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px">
        @foreach($statusTabs as $key => $tab)
            @php $on = $filterStat === $key; @endphp
            <a href="{{ route('checklist.review.weekly', array_merge(request()->except(['status','page']), ['status' => $key])) }}"
               class="rv-sttab"
               @if($on) style="background:{{ $tab['bg'] }};color:{{ $tab['c'] }};border:1.5px solid {{ $tab['c'] }}55" @endif>
                <span class="rv-sttab__dot" style="background:{{ $tab['c'] }}"></span>
                {{ $tab['label'] }}
                @if($tab['count'] > 0)
                    <span class="rv-sttab__badge" @if($on) style="background:{{ $tab['c'] }};color:#fff" @endif>{{ $tab['count'] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- ─── FILTER CARD ─── --}}
    <form method="GET" action="{{ route('checklist.review.weekly') }}"
          style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:13px 16px;display:flex;align-items:center;gap:11px;flex-wrap:wrap;box-shadow:0 1px 2px rgba(70,21,0,.04);margin-bottom:14px">
        <input type="hidden" name="status" value="{{ $filterStat }}">
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

        <input type="date" name="week" value="{{ $filterWeek }}" onchange="this.form.submit();" class="rv-input"
               title="Pilih tanggal apa saja — otomatis diarahkan ke minggu (Senin–Minggu) yang memuatnya.">

        <div style="position:relative;flex:1;min-width:190px">
            <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#B79A82;font-size:13px"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Nama karyawan…" class="rv-input" style="width:100%;box-sizing:border-box;padding-left:36px;background:#FFFCF8">
        </div>

        <button type="submit" style="background:linear-gradient(135deg,#6B2400,#461500);color:#FFEAD6;border:none;border-radius:10px;padding:9px 16px;font-weight:700;font-size:12.5px;font-family:inherit;cursor:pointer">Cari</button>

        @if($search || $filterWeek || $filterDiv !== 'all')
            <a href="{{ route('checklist.review.weekly', ['status' => $filterStat]) }}" style="font-size:12px;font-weight:700;color:#B0855F;text-decoration:underline">Reset</a>
        @endif

        <span style="margin-left:auto;font-size:12.5px;color:#9A7256">Menampilkan <b style="color:#3B1500">{{ $submissions->count() }}</b> dari <b style="color:#3B1500">{{ $submissions->total() }}</b> submission</span>
    </form>

    {{-- ─── EXPORT EXCEL (rekap mingguan divisi yang sedang dibuka — tiap karyawan jadi tab) ─── --}}
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

    {{-- ─── TABEL FORM MINGGUAN ─── --}}
    <div style="background:#fff;border:1px solid #EFE0CE;border-radius:18px;overflow-x:auto;box-shadow:0 1px 2px rgba(70,21,0,.04),0 10px 26px rgba(70,21,0,.05)">
        @if($submissions->isEmpty())
            <div style="padding:34px 20px;text-align:center;font-size:12.5px;color:#9A7256">
                Tidak ada Form Mingguan untuk filter ini — form muncul setelah karyawan submit. Pastikan <b>tugas mingguan</b> sudah dibuat di menu Setup.
            </div>
        @else
            <table class="rv-table">
                <thead>
                    <tr>
                        <th style="width:190px;">Minggu</th>
                        <th>Karyawan</th>
                        <th style="width:120px;">Cabang</th>
                        <th style="width:190px;">Progres</th>
                        <th style="width:165px;">Status</th>
                        <th style="width:104px;">Lihat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($submissions as $s)
                        @php
                            $stMap = [
                                'submitted' => ['label' => 'Menunggu Approval', 'c' => '#B5701A', 'bg' => '#FBEFD9'],
                                'approved'  => ['label' => 'Disetujui',          'c' => '#2F8E55', 'bg' => '#E3F4E8'],
                                'rejected'  => ['label' => 'Ditolak',            'c' => '#C0392B', 'bg' => '#FBE3DE'],
                                'excused'   => ['label' => 'Dikecualikan',       'c' => '#8A5A2B', 'bg' => '#F2E7D8'],
                                'missed'    => ['label' => 'Tidak Diisi',        'c' => '#9B8578', 'bg' => '#EFE7DE'],
                                'draft'     => ['label' => 'Belum Dikirim',     'c' => '#9B8578', 'bg' => '#EFE7DE'],
                            ];
                            $st  = $stMap[$s->status] ?? ['label' => $s->status, 'c' => '#9B8578', 'bg' => '#EFE7DE'];
                            $pct = $s->progressPct();
                            $barColor = $s->status === 'excused'
                                ? '#C7AC92'
                                : ($pct >= 80 ? '#2F8E55' : ($pct >= 50 ? '#C9962F' : '#C0392B'));
                            $avPalette  = ['#8A3A12','#A8481B','#2F8E55','#3C5FA8','#B5701A','#7A3415','#5A6B2E','#8E2419'];
                            $avName     = trim($s->user?->nama ?? '');
                            $avParts    = preg_split('/\s+/', $avName, -1, PREG_SPLIT_NO_EMPTY);
                            $avInitials = strtoupper(mb_substr($avParts[0] ?? '?', 0, 1) . (isset($avParts[1]) ? mb_substr($avParts[1], 0, 1) : ''));
                            $avColor    = $avPalette[abs(crc32($avName !== '' ? $avName : '?')) % 8];
                        @endphp
                        <tr>
                            <td>
                                <div style="font-size:12.5px;font-weight:600;color:#5A3A22;font-variant-numeric:tabular-nums">{{ $s->weekLabel() }}</div>
                                <div style="font-size:10.5px;color:#B79A82;margin-top:2px;font-variant-numeric:tabular-nums">
                                    {{ $s->year_week }}
                                    @if($s->submitted_at) · submit {{ $s->submitted_at->format('d/m H:i') }} @endif
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;min-width:0">
                                    <div style="width:32px;height:32px;border-radius:50%;background:{{ $avColor }};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:11px;flex-shrink:0">{{ $avInitials }}</div>
                                    <span style="font-size:13px;font-weight:700;color:#3B1500">{{ $s->user?->nama ?? '—' }}</span>
                                </div>
                            </td>
                            <td style="font-size:12.5px;color:#7A5B43;">{{ $s->branch?->name ?? '—' }}</td>
                            <td>
                                <div style="display:flex;align-items:center;gap:9px">
                                    <div style="flex:1;min-width:70px;height:7px;border-radius:6px;background:#F1E6D7;overflow:hidden"><div style="height:100%;width:{{ $pct }}%;background:{{ $barColor }};border-radius:6px"></div></div>
                                    <span style="font-size:11.5px;font-weight:800;color:#5A3A22;flex:none;font-variant-numeric:tabular-nums">{{ $s->done_tasks }}/{{ $s->total_tasks }}</span>
                                </div>
                            </td>
                            <td>
                                <span style="display:inline-flex;align-items:center;font-size:11px;font-weight:800;padding:4px 11px;border-radius:20px;background:{{ $st['bg'] }};color:{{ $st['c'] }};white-space:nowrap">{{ $st['label'] }}</span>
                            </td>
                            <td>
                                @if($s->status === \App\Models\ChecklistWeeklySubmission::STATUS_SUBMITTED)
                                    <a href="{{ route('checklist.review.weekly.show', $s->id) }}" class="rv-btn-review">Review</a>
                                @else
                                    <a href="{{ route('checklist.review.weekly.show', $s->id) }}" class="rv-btn-eye" title="Lihat detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($submissions->hasPages())
                <div style="padding:14px 18px;border-top:1px solid #F4EADD;background:#fff;">
                    {{ $submissions->links() }}
                </div>
            @endif
        @endif
    </div>

    </div>{{-- /max-width --}}
</div>
@endsection
