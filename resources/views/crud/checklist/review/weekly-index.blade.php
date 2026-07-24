@extends('layouts.app')

@section('content')
<style>
    /* ── Pilih jenis checklist (kartu besar, gaya halaman setup) ── */
    .cl-cad-label {
        font-size: 12px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .5px; color: #5C2E15; margin-bottom: 9px;
    }
    .cl-cad-tabs { display: flex; gap: 10px; flex-wrap: wrap; }
    .cl-cad-tab {
        flex: 1; min-width: 220px;
        display: flex; align-items: center; gap: 12px;
        padding: 13px 16px;
        background: #FFFFFF;
        border: 1.5px solid #E0D2C1;
        border-radius: 14px;
        cursor: pointer; text-align: left; text-decoration: none;
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease, background .16s ease;
        box-shadow: 0 2px 6px rgba(45,24,16,.04);
    }
    .cl-cad-tab:hover { border-color: #C9B69A; box-shadow: 0 4px 12px rgba(45,24,16,.08); }
    .cl-cad-tab__icon {
        width: 40px; height: 40px; border-radius: 11px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 16px; color: #FFFFFF; flex-shrink: 0;
    }
    .cl-cad-tab--daily  .cl-cad-tab__icon { background: linear-gradient(135deg, #A66019, #5C2E15); box-shadow: 0 3px 8px rgba(166,96,25,.22); }
    .cl-cad-tab--weekly .cl-cad-tab__icon { background: linear-gradient(135deg, #1F6E5F, #155148); box-shadow: 0 3px 8px rgba(31,110,95,.22); }
    .cl-cad-tab__body { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 2px; }
    .cl-cad-tab__body strong { font-size: 15px; font-weight: 800; color: #2D1810; line-height: 1.2; }
    .cl-cad-tab__body small { font-size: 11.5px; color: #6B4423; }
    .cl-cad-tab__check { opacity: 0; flex-shrink: 0; font-size: 18px; color: #FFFFFF; transition: opacity .16s ease; }
    .cl-cad-tab.is-active { border-color: transparent; transform: translateY(-1px); }
    .cl-cad-tab--daily.is-active  { background: linear-gradient(135deg, #A66019, #5C2E15); box-shadow: 0 7px 18px rgba(92,46,21,.28); }
    .cl-cad-tab--weekly.is-active { background: linear-gradient(135deg, #1F6E5F, #155148); box-shadow: 0 7px 18px rgba(21,81,72,.28); }
    .cl-cad-tab.is-active .cl-cad-tab__body strong { color: #FFFFFF; }
    .cl-cad-tab.is-active .cl-cad-tab__body small { color: #FFFFFF; opacity: .82; }
    .cl-cad-tab.is-active .cl-cad-tab__icon { background: rgba(255,255,255,.18); box-shadow: none; }
    .cl-cad-tab.is-active .cl-cad-tab__check { opacity: .95; }

    /* Tombol aksi tabel review (paritas dgn antrean Harian) */
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
                <h4 class="page-title">Review Form Mingguan</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── HERO ─── --}}
    @php
        $activeDivName = ($filterDiv !== 'all') ? optional($divisions->firstWhere('id', $filterDiv))->name : null;
    @endphp
    <div class="cl-hero">
        <div class="cl-hero__left">
            <h5 class="cl-hero__title">
                <span class="cl-hero__icon"><i class="fas fa-calendar-week"></i></span>
                Review & Approve Checklist Mingguan @if($activeDivName)<span style="color:#9B5E2C; font-weight:700;">— {{ $activeDivName }}</span>@endif
            </h5>
            <p class="cl-hero__subtitle">
                Verifikasi pengisian Form Mingguan karyawan. Filter berdasarkan minggu, divisi, dan status.
            </p>
        </div>
        <div class="cl-hero__actions">
            <a href="{{ route('checklist') }}" class="st-btn-outline-brown">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    {{-- ─── CARD: TABS + FILTER + TABLE ─── --}}
    <div class="cl-panel" style="padding:0;">

        {{-- MASTER: TAB DIVISI --}}
        <div class="cl-rev-master">
            <div class="cl-divtabs">
                @foreach($divisions as $div)
                    <a href="{{ route('checklist.review.weekly', array_merge(request()->except(['division','page']), ['division' => $div->id])) }}"
                       class="cl-divtab {{ $filterDiv === $div->id ? 'is-active' : '' }}">
                        @if($div->id === 'FO')
                            <i class="fas fa-headset"></i>
                        @elseif($div->id === 'GS')
                            <i class="fas fa-broom"></i>
                        @else
                            <i class="fas fa-user-nurse"></i>
                        @endif
                        {{ $div->name }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- SUB-BAR: pilih jenis checklist (kartu besar, gaya setup) --}}
        @php $divParam = $filterDiv !== 'all' ? ['division' => $filterDiv] : []; @endphp
        <div class="cl-rev-subbar">
            <div class="cl-cad-label">Pilih jenis checklist:</div>
            <div class="cl-cad-tabs" style="margin-bottom:0;">
                <a href="{{ route('checklist.review', $divParam) }}" class="cl-cad-tab cl-cad-tab--daily">
                    <span class="cl-cad-tab__icon"><i class="fas fa-sun"></i></span>
                    <span class="cl-cad-tab__body">
                        <strong>Checklist Harian</strong>
                        <small>Review pengisian per hari</small>
                    </span>
                    <i class="fas fa-check-circle cl-cad-tab__check"></i>
                </a>
                <a href="{{ route('checklist.review.weekly', $divParam) }}" class="cl-cad-tab cl-cad-tab--weekly is-active">
                    <span class="cl-cad-tab__icon"><i class="fas fa-calendar-week"></i></span>
                    <span class="cl-cad-tab__body">
                        <strong>Checklist Mingguan</strong>
                        <small>Review form mingguan</small>
                    </span>
                    <i class="fas fa-check-circle cl-cad-tab__check"></i>
                </a>
            </div>
        </div>

        {{-- TAB STATUS --}}
        <div class="cl-tabs cl-tabs--underline">
            @php
                $statusTabs = [
                    'submitted' => ['label' => 'Menunggu Approval', 'count' => $counts['submitted'], 'icon' => 'fa-clock',        'color' => '#E08A1E'],
                    'approved'  => ['label' => 'Disetujui',          'count' => $counts['approved'],  'icon' => 'fa-check-circle', 'color' => '#1F9E6F'],
                    'rejected'  => ['label' => 'Ditolak',            'count' => $counts['rejected'],  'icon' => 'fa-times-circle', 'color' => '#C0392B'],
                    'excused'   => ['label' => 'Dikecualikan',       'count' => $counts['excused'],   'icon' => 'fa-mug-hot',      'color' => '#8A5A2B'],
                    'missed'    => ['label' => 'Tidak Diisi',        'count' => $counts['missed'],    'icon' => 'fa-minus-circle', 'color' => '#9B8578'],
                ];
            @endphp
            @foreach($statusTabs as $key => $tab)
                <a href="{{ route('checklist.review.weekly', array_merge(request()->except(['status','page']), ['status' => $key])) }}"
                   class="cl-tab {{ $filterStat === $key ? 'is-active' : '' }}">
                    <i class="fas {{ $tab['icon'] }}" style="color: {{ $tab['color'] }};"></i>
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
            <input type="hidden" name="division" value="{{ $filterDiv }}">

            @if(canSeeAllBranches() && $branches->count())
                <label>Cabang</label>
                <select name="branch" onchange="this.form.submit();">
                    <option value="">— Semua —</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            @elseif($branchId)
                <input type="hidden" name="branch" value="{{ $branchId }}">
            @endif

            <label>Tanggal</label>
            <input type="date" name="week" value="{{ $filterWeek }}" onchange="this.form.submit();"
                   title="Pilih tanggal apa saja — otomatis diarahkan ke minggu (Senin–Minggu) yang memuatnya.">

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
                <div class="cl-empty__desc">Form Mingguan akan muncul di sini setelah karyawan submit. Pastikan <strong>tugas mingguan</strong> sudah dibuat di menu Setup.</div>
            </div>
        @else
            <div class="st-table-wrap st-table-wrap--scroll">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th style="width:180px;">Minggu</th>
                            <th>Karyawan</th>
                            <th style="width:160px;">Cabang</th>
                            <th style="width:200px;">Progres</th>
                            <th style="width:160px;">Status</th>
                            <th style="width:80px; text-align:center;">Lihat</th>
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
                                    @if($s->status === \App\Models\ChecklistWeeklySubmission::STATUS_SUBMITTED)
                                        <a href="{{ route('checklist.review.weekly.show', $s->id) }}" class="st-btn-brand cl-rev-btn">
                                            <i class="fas fa-clipboard-check"></i> Review
                                        </a>
                                    @else
                                        <a href="{{ route('checklist.review.weekly.show', $s->id) }}" class="cl-rev-eye" title="Lihat detail">
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
