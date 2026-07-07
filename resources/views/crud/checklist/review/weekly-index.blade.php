@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Review Form Mingguan</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── TOGGLE Harian vs Mingguan ─── --}}
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px;">
        <div style="display:inline-flex; gap:2px; padding:4px; background:#FFFFFF; border-radius:999px; border:1px solid #E0D2C1; box-shadow:0 1px 3px rgba(45,24,16,.06);">
            <a href="{{ route('checklist.review') }}"
               style="padding:8px 18px; font-size:13px; font-weight:600; text-decoration:none; border-radius:999px; color:#6B4423; display:inline-flex; align-items:center; gap:7px;">
                <i class="fas fa-sun"></i> Review Harian
            </a>
            <a href="{{ route('checklist.review.weekly') }}"
               style="padding:8px 18px; font-size:13px; font-weight:700; text-decoration:none; border-radius:999px; background:linear-gradient(180deg,#5C2E15,#471500); color:#FFFFFF; box-shadow:0 2px 6px rgba(71,21,0,.20); display:inline-flex; align-items:center; gap:7px;">
                <i class="fas fa-calendar-week"></i> Review Mingguan
            </a>
        </div>
    </div>

    {{-- ─── HERO ─── --}}
    <div class="cl-hero">
        <div class="cl-hero__left">
            <h5 class="cl-hero__title">
                <span class="cl-hero__icon"><i class="fas fa-calendar-week"></i></span>
                Queue Review Form Mingguan
            </h5>
            <p class="cl-hero__subtitle">
                Verifikasi pengisian Form Mingguan karyawan (mengikuti pola form kertas Sales / Asdent). Filter berdasarkan minggu, divisi, dan status.
            </p>
        </div>
        @if(canSeeAllBranches() && $branches->count())
            <form method="GET" action="{{ route('checklist.review.weekly') }}" class="cl-hero__actions">
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

        {{-- TAB STATUS --}}
        <div class="cl-tabs">
            @php
                $statusTabs = [
                    'all'       => ['label' => 'Semua',              'count' => $counts['all'],       'icon' => 'fa-list'],
                    'submitted' => ['label' => 'Menunggu Approval', 'count' => $counts['submitted'], 'icon' => 'fa-clock'],
                    'approved'  => ['label' => 'Disetujui',          'count' => $counts['approved'],  'icon' => 'fa-check-circle'],
                    'rejected'  => ['label' => 'Ditolak',            'count' => $counts['rejected'],  'icon' => 'fa-times-circle'],
                    'excused'   => ['label' => 'Dikecualikan',       'count' => $counts['excused'],   'icon' => 'fa-mug-hot'],
                    'draft'     => ['label' => 'Draft',               'count' => $counts['draft'],     'icon' => 'fa-edit'],
                ];
            @endphp
            @foreach($statusTabs as $key => $tab)
                <a href="{{ route('checklist.review.weekly', array_merge(request()->except(['status','page']), ['status' => $key])) }}"
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
        <form method="GET" action="{{ route('checklist.review.weekly') }}" class="cl-filter">
            <input type="hidden" name="status" value="{{ $filterStat }}">
            @if($branchId)<input type="hidden" name="branch" value="{{ $branchId }}">@endif

            <label>Minggu (Senin)</label>
            <input type="date" name="week" value="{{ $filterWeek }}" onchange="this.form.submit();">

            <label>Divisi</label>
            <select name="division" onchange="this.form.submit();">
                <option value="all">Semua Divisi</option>
                @foreach($divisions as $div)
                    <option value="{{ $div->id }}" {{ $filterDiv === $div->id ? 'selected' : '' }}>{{ $div->name }}</option>
                @endforeach
            </select>

            <label>Cari</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="Nama karyawan...">

            <button type="submit" class="st-btn-brand"><i class="fas fa-search"></i> Cari</button>

            @if($search || $filterWeek || $filterDiv !== 'all')
                <a href="{{ route('checklist.review.weekly', ['status' => $filterStat]) }}" style="font-size:12px; color:var(--cl-brown-2); text-decoration:underline;">Reset filter</a>
            @endif

            <div class="cl-filter__right">
                Menampilkan <strong>{{ $submissions->count() }}</strong> dari <strong>{{ $submissions->total() }}</strong> submission
            </div>
        </form>

        @if($submissions->isEmpty())
            <div class="cl-empty">
                <span class="cl-empty__icon"><i class="fas fa-inbox"></i></span>
                <div class="cl-empty__title">Tidak ada Form Mingguan</div>
                <div class="cl-empty__desc">Form Mingguan akan muncul di sini setelah karyawan submit. Pastikan kategori <strong>Mingguan</strong> sudah dibuat di menu Setup.</div>
            </div>
        @else
            <div class="st-table-wrap st-table-wrap--scroll">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th style="width:180px;">Minggu</th>
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
                                $opt = \App\Models\ChecklistWeeklySubmission::statusOptions()[$s->status] ?? ['label'=>$s->status,'color'=>'neutral'];
                                $pct = $s->progressPct();
                            @endphp
                            <tr>
                                <td>
                                    <strong style="color:var(--cl-ink);">{{ $s->weekLabel() }}</strong>
                                    <div style="font-size:10.5px; color:var(--cl-subtle); margin-top:2px;">
                                        {{ $s->year_week }}
                                        @if($s->submitted_at)
                                            · <i class="fas fa-paper-plane" style="font-size:9px;"></i> {{ $s->submitted_at->format('d/m H:i') }}
                                        @endif
                                    </div>
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
                                    <a href="{{ route('checklist.review.weekly.show', $s->id) }}" class="st-icon-btn" title="Lihat detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
