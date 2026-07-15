@extends('layouts.app')

@section('content')
<style>
    /* Poles queue review */
    .cl-panel .st-table tbody tr { transition: background .12s; }
    .cl-panel .st-table tbody tr:hover { background: #FCF7F0; }
    .cl-panel .st-table tbody td { vertical-align: middle; }
    .cl-rev-btn { padding: 6px 14px; font-size: 12px; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
    .cl-rev-eye {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; border-radius: 9px;
        border: 1px solid #E0D2C1; background: #fff; color: #5C2E15;
        text-decoration: none; transition: .15s;
    }
    .cl-rev-eye:hover { background: #FAF0E2; border-color: #A66019; color: #5C2E15; }
</style>
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Review & Approval Checklist</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── HERO ─── --}}
    <div class="cl-hero">
        <div class="cl-hero__left">
            <h5 class="cl-hero__title">
                <span class="cl-hero__icon"><i class="fas fa-clipboard-list"></i></span>
                Queue Review Checklist
            </h5>
            <p class="cl-hero__subtitle">
                Verifikasi pengisian harian karyawan. Filter berdasarkan tanggal, divisi, dan status untuk mempercepat review.
            </p>
        </div>
        @if(canSeeAllBranches() && $branches->count())
            <form method="GET" action="{{ route('checklist.review') }}" class="cl-hero__actions">
                @foreach(request()->except(['branch','page']) as $k => $v)
                    @if(is_string($v) || is_numeric($v))
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endif
                @endforeach
                <label class="cl-hero__picker-label">Cabang</label>
                <select name="branch" onchange="this.form.submit();" class="cl-hero__picker">
                    <option value="">— Semua —</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    {{-- ─── CARD: TABS + FILTER + TABLE ─── --}}
    <div class="cl-panel" style="padding:0;">

        {{-- MASTER: TAB DIVISI (navigasi utama, seperti halaman setup) --}}
        <div class="cl-rev-master">
            <div class="cl-divtabs">
                @foreach($divisions as $div)
                    <a href="{{ route('checklist.review', array_merge(request()->except(['division','page']), ['division' => $div->id])) }}"
                       class="cl-divtab {{ $filterDiv === $div->id ? 'is-active' : '' }}">
                        @if($div->id === 'FO')
                            <i class="fas fa-headset"></i>
                        @elseif($div->id === 'GS')
                            <i class="fas fa-broom"></i>
                        @else
                            <i class="fas fa-user-nurse"></i>
                        @endif
                        {{ $div->name }}
                        @if(($divCounts[$div->id] ?? 0) > 0)
                            <span class="cl-divtab__count">{{ $divCounts[$div->id] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        {{-- SUB-NAV: Harian vs Mingguan --}}
        <div class="cl-rev-subbar">
            <div style="display:inline-flex; gap:2px; padding:4px; background:#FFFFFF; border-radius:999px; border:1px solid #E0D2C1; box-shadow:0 1px 3px rgba(45,24,16,.06);">
                <a href="{{ route('checklist.review') }}"
                   style="padding:8px 18px; font-size:13px; font-weight:700; text-decoration:none; border-radius:999px; background:linear-gradient(180deg,#5C2E15,#471500); color:#FFFFFF; box-shadow:0 2px 6px rgba(71,21,0,.20); display:inline-flex; align-items:center; gap:7px;">
                    <i class="fas fa-sun"></i> Review Checklist Harian
                </a>
                <a href="{{ route('checklist.review.weekly') }}"
                   style="padding:8px 18px; font-size:13px; font-weight:600; text-decoration:none; border-radius:999px; color:#6B4423; display:inline-flex; align-items:center; gap:7px;">
                    <i class="fas fa-calendar-week"></i> Review Checklist Mingguan
                </a>
            </div>
        </div>

        {{-- TAB STATUS --}}
        <div class="cl-tabs">
            @php
                $statusTabs = [
                    'all'       => ['label' => 'Semua',              'count' => $counts['all'],       'icon' => 'fa-list'],
                    'submitted' => ['label' => 'Menunggu Approval', 'count' => $counts['submitted'], 'icon' => 'fa-clock'],
                    'approved'  => ['label' => 'Disetujui',          'count' => $counts['approved'],  'icon' => 'fa-check-circle'],
                    'rejected'  => ['label' => 'Ditolak',            'count' => $counts['rejected'],  'icon' => 'fa-times-circle'],
                    'excused'   => ['label' => 'Dikecualikan',       'count' => $counts['excused'],   'icon' => 'fa-mug-hot'],
                    'missed'    => ['label' => 'Tidak Diisi',        'count' => $counts['missed'],    'icon' => 'fa-minus-circle'],
                ];
            @endphp
            @foreach($statusTabs as $key => $tab)
                <a href="{{ route('checklist.review', array_merge(request()->except(['status','page']), ['status' => $key])) }}"
                   class="cl-tab {{ $filterStat === $key ? 'is-active' : '' }}">
                    <i class="fas {{ $tab['icon'] }}"></i>
                    {{ $tab['label'] }}
                    @if($tab['count'] > 0)
                        <span class="cl-tab__count">{{ $tab['count'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- FILTER BAR --}}
        <form method="GET" action="{{ route('checklist.review') }}" class="cl-filter">
            <input type="hidden" name="status" value="{{ $filterStat }}">
            <input type="hidden" name="division" value="{{ $filterDiv }}">
            @if($branchId)<input type="hidden" name="branch" value="{{ $branchId }}">@endif

            <label>Tanggal</label>
            <input type="date" name="date" value="{{ $filterDate }}" onchange="this.form.submit();">

            <label>Cari</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="Nama karyawan...">

            <button type="submit" class="st-btn-brand"><i class="fas fa-search"></i> Cari</button>

            @if($search || $filterDate || $filterDiv !== 'all')
                <a href="{{ route('checklist.review', ['status' => $filterStat]) }}" style="font-size:12px; color:var(--cl-brown-2); text-decoration:underline;">Reset filter</a>
            @endif

            <div class="cl-filter__right">
                Menampilkan <strong>{{ $submissions->count() }}</strong> dari <strong>{{ $submissions->total() }}</strong> submission
            </div>
        </form>

        {{-- TABEL --}}
        @if($submissions->isEmpty())
            <div class="cl-empty">
                <span class="cl-empty__icon"><i class="fas fa-inbox"></i></span>
                <div class="cl-empty__title">Tidak ada submission</div>
                <div class="cl-empty__desc">Coba ubah filter atau pilih tanggal lain — submission baru akan muncul di sini setelah karyawan submit checklist.</div>
            </div>
        @else
            <div class="st-table-wrap st-table-wrap--scroll">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th style="width:140px;">Tanggal</th>
                            <th>Karyawan</th>
                            <th style="width:120px;">Divisi</th>
                            <th style="width:160px;">Cabang</th>
                            <th style="width:200px;">Progres</th>
                            <th style="width:160px;">Status</th>
                            <th style="width:80px; text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($submissions as $s)
                            @php
                                $opt = \App\Models\ChecklistSubmission::statusOptions()[$s->status] ?? ['label'=>$s->status,'color'=>'neutral'];
                                $pct = $s->progressPct();
                            @endphp
                            <tr>
                                <td>
                                    <strong style="color:var(--cl-ink);">{{ $s->submission_date->translatedFormat('D, d M Y') }}</strong>
                                    @if($s->submitted_at)
                                        <div style="font-size:10.5px; color:var(--cl-subtle); margin-top:2px;">
                                            <i class="fas fa-paper-plane" style="font-size:9px;"></i> submit {{ $s->submitted_at->format('H:i') }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="width:30px; height:30px; border-radius:50%; background:linear-gradient(135deg,var(--cl-brown-2),var(--cl-brown)); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:11px; flex-shrink:0;">
                                            {{ strtoupper(mb_substr($s->user?->nama ?? '?', 0, 1)) }}
                                        </div>
                                        <div style="font-weight:600; color:var(--cl-ink);">{{ $s->user?->nama ?? '—' }}</div>
                                    </div>
                                </td>
                                <td>
                                    <span class="st-pill st-pill--neutral">{{ $s->division?->name ?? $s->division_id }}</span>
                                </td>
                                <td style="color:var(--cl-muted); font-size:12.5px;">{{ $s->branch?->name ?? '—' }}</td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div class="cl-bar" style="flex:1; min-width:80px;"><i style="width:{{ $pct }}%;"></i></div>
                                        <span style="font-size:11.5px; color:var(--cl-muted); font-weight:600; white-space:nowrap;">
                                            <strong style="color:var(--cl-ink);">{{ $s->done_tasks }}</strong>/{{ $s->total_tasks }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="st-pill st-pill--{{ $opt['color'] }}">{{ $opt['label'] }}</span>
                                </td>
                                <td style="text-align:center;">
                                    @if($s->status === \App\Models\ChecklistSubmission::STATUS_SUBMITTED)
                                        <a href="{{ route('checklist.review.show', $s->id) }}" class="st-btn-brand cl-rev-btn">
                                            <i class="fas fa-clipboard-check"></i> Review
                                        </a>
                                    @else
                                        <a href="{{ route('checklist.review.show', $s->id) }}" class="cl-rev-eye" title="Lihat detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($submissions->hasPages())
                <div style="padding:14px 18px; border-top:1px solid var(--cl-line); background:#FFFFFF;">
                    {{ $submissions->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
