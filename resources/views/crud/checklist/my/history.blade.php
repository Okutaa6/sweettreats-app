@extends('layouts.app')

@section('css')
<style>
    .cl-hist-wrap { max-width: 1100px; margin: 0 auto; }
    .cl-hist-grid { display: grid; grid-template-columns: 1fr 300px; gap: 16px; align-items: start; }
    @media (max-width: 992px) { .cl-hist-grid { grid-template-columns: 1fr; } }

    /* KPI strip */
    .cl-hist-kpi {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 10px;
        margin-bottom: 14px;
    }
    @media (max-width: 760px) {
        .cl-hist-kpi { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 460px) {
        .cl-hist-kpi { grid-template-columns: repeat(2, 1fr); }
    }
    .cl-hist-kpi__cell {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 12px;
        padding: 12px 14px;
        box-shadow: 0 2px 6px rgba(45,24,16,.04);
        position: relative;
        overflow: hidden;
    }
    .cl-hist-kpi__label {
        font-size: 10.5px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.4px; color: #9B8578; margin-bottom: 4px;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .cl-hist-kpi__val {
        font-size: 22px; font-weight: 800; color: #2D1810; line-height: 1;
        letter-spacing: -0.02em;
        display: flex; align-items: baseline; gap: 2px;
    }
    .cl-hist-kpi__val small { font-size: 12px; color: #6B4423; font-weight: 600; }
    .cl-hist-kpi__cell--success { border-left: 3px solid #1F6E5F; }
    .cl-hist-kpi__cell--warning { border-left: 3px solid #A66019; }
    .cl-hist-kpi__cell--danger  { border-left: 3px solid #A32D2D; }
    .cl-hist-kpi__cell--neutral { border-left: 3px solid #9B8578; }
    .cl-hist-kpi__cell--accent  { border-left: 3px solid #5C2E15; }

    /* Header / month nav */
    .cl-hist-header {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 14px;
        padding: 14px 18px;
        margin-bottom: 14px;
        display: flex; justify-content: space-between; align-items: center;
        gap: 10px; flex-wrap: wrap;
        box-shadow: 0 2px 6px rgba(45,24,16,.04);
    }
    .cl-hist-header__title {
        font-size: 16px; font-weight: 800; color: #2D1810;
        display: inline-flex; align-items: center; gap: 8px; margin: 0;
    }
    .cl-hist-header__title i { color: #9B5E2C; font-size: 13px; }
    .cl-hist-navbtn {
        width: 34px; height: 34px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 50%;
        background: #FAF4EC; color: #5C2E15;
        text-decoration: none;
        border: 1px solid #E0D2C1;
        transition: background .15s, transform .1s;
    }
    .cl-hist-navbtn:hover { background: #F5EBE0; transform: translateY(-1px); text-decoration: none; color: #5C2E15; }

    /* Submission cards */
    .cl-hist-list { display: flex; flex-direction: column; gap: 10px; }
    .cl-hist-card {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 14px;
        box-shadow: 0 2px 6px rgba(45,24,16,.04);
        overflow: hidden;
        border-left: 4px solid var(--cl-accent, #9B8578);
        transition: box-shadow .2s, border-color .2s;
    }
    .cl-hist-card:hover { box-shadow: 0 8px 20px rgba(45,24,16,.08); }
    .cl-hist-card.is-open { box-shadow: 0 8px 22px rgba(45,24,16,.10); }
    .cl-hist-card__head {
        display: flex; align-items: center; gap: 14px;
        padding: 14px 16px;
        cursor: pointer; user-select: none;
        background: linear-gradient(180deg, #FFFFFF 0%, #FAF4EC 100%);
    }
    .cl-hist-card__head:hover { background: linear-gradient(180deg, #FAF4EC 0%, #F5EBE0 100%); }
    .cl-hist-card__date {
        min-width: 110px;
        flex-shrink: 0;
    }
    .cl-hist-card__date strong {
        display: block;
        font-size: 13.5px; font-weight: 700; color: #2D1810;
        line-height: 1.2;
    }
    .cl-hist-card__date small {
        display: block;
        font-size: 11px; color: #9B8578; margin-top: 3px;
    }
    .cl-hist-card__body { flex: 1; min-width: 0; }
    .cl-hist-card__bar {
        background: #F5F0EA; height: 7px; border-radius: 4px; overflow: hidden;
        margin-bottom: 6px;
    }
    .cl-hist-card__bar > i {
        display: block; height: 100%;
        background: linear-gradient(90deg, #9B5E2C, #5C2E15);
        border-radius: 4px;
    }
    .cl-hist-card__meta {
        font-size: 11.5px; color: #6B4423;
        display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
    }
    .cl-hist-card__meta i.dot { font-size: 4px; color: #C9B69A; }
    .cl-hist-card__right {
        text-align: right; flex-shrink: 0;
        display: flex; flex-direction: column; align-items: flex-end; gap: 6px;
    }
    .cl-hist-card__right small { font-size: 10.5px; color: #9B8578; }
    .cl-hist-card__chev {
        color: #9B8578; font-size: 13px;
        transition: transform .25s ease, color .2s;
        flex-shrink: 0;
    }
    .cl-hist-card.is-open .cl-hist-card__chev {
        transform: rotate(180deg); color: #5C2E15;
    }
    .cl-hist-card__detail {
        display: none;
        background: #FAF4EC;
        border-top: 1px solid #E0D2C1;
        padding: 14px 16px;
    }
    .cl-hist-card.is-open .cl-hist-card__detail { display: block; animation: clHistFade .25s ease; }
    @keyframes clHistFade {
        from { opacity: 0; transform: translateY(-3px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* Catatan supervisor inline */
    .cl-hist-note {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-left: 3px solid var(--cl-note-accent, #9B5E2C);
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 12px; color: #2D1810; line-height: 1.5;
        margin-bottom: 12px;
        display: flex; gap: 8px; align-items: flex-start;
    }
    .cl-hist-note i { color: var(--cl-note-accent, #9B5E2C); font-size: 11px; margin-top: 2px; flex-shrink: 0; }
    .cl-hist-note strong {
        display: block; font-size: 10.5px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.4px;
        color: var(--cl-note-accent, #9B5E2C); margin-bottom: 2px;
    }

    /* Per-category mini block */
    .cl-hist-cat {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 10px;
        margin-bottom: 8px;
        overflow: hidden;
    }
    .cl-hist-cat:last-child { margin-bottom: 0; }
    .cl-hist-cat__head {
        padding: 8px 12px;
        background: #FFFFFF;
        border-bottom: 1px solid #F0E8DC;
        display: flex; justify-content: space-between; align-items: center;
        font-size: 12.5px; font-weight: 700; color: #2D1810;
    }
    .cl-hist-cat__head i { color: #9B5E2C; font-size: 10px; margin-right: 5px; }
    .cl-hist-cat__chip {
        font-size: 10.5px; font-weight: 700;
        padding: 2px 8px; border-radius: 999px;
    }
    .cl-hist-cat__chip--good { background: rgba(31,110,95,.12); color: #1F6E5F; }
    .cl-hist-cat__chip--warn { background: rgba(166,96,25,.12); color: #A66019; }
    .cl-hist-cat__task {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 7px 12px;
        border-bottom: 1px solid #F5EBE0;
        font-size: 12.5px;
    }
    .cl-hist-cat__task:last-child { border-bottom: none; }
    .cl-hist-cat__task .ck {
        width: 18px; height: 18px; border-radius: 50%;
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 10px; font-weight: 700;
        background: #F5F0EA; color: #9B8578;
        margin-top: 1px;
    }
    .cl-hist-cat__task.is-done .ck { background: #1F6E5F; color: #FFFFFF; }
    .cl-hist-cat__task.is-done .ck::before { content: "✓"; }
    .cl-hist-cat__task .ttl { flex: 1; min-width: 0; color: #2D1810; line-height: 1.4; }
    .cl-hist-cat__task.is-done .ttl { color: #9B8578; }
    .cl-hist-cat__task .time { font-size: 10.5px; color: #9B8578; flex-shrink: 0; margin-top: 2px; }
    .cl-hist-cat__task .note {
        font-size: 11px; color: #6B4423; margin-top: 3px;
        padding: 5px 8px; background: #FAF4EC; border-radius: 6px;
        border-left: 2px solid #C9B69A;
    }
    .cl-hist-cat__task .note i { font-size: 9px; margin-right: 3px; color: #9B5E2C; }

    /* Mini calendar */
    .cl-hist-cal {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
    }
    .cl-hist-cal__dow {
        font-size: 9.5px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.4px; color: #9B8578;
        text-align: center; padding: 4px 0;
    }
    .cl-hist-cal__cell {
        aspect-ratio: 1;
        background: #FAFAF8;
        border: 1px solid #E8DCC9;
        border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700;
        color: #6B4423;
        position: relative;
        cursor: default;
        transition: transform .1s;
    }
    .cl-hist-cal__cell.is-blank { background: transparent; border: none; }
    .cl-hist-cal__cell.is-future { background: #FFFFFF; color: #C9B69A; border-style: dashed; }
    .cl-hist-cal__cell.is-today {
        outline: 2px solid #5C2E15; outline-offset: -2px;
    }
    .cl-hist-cal__cell--approved { background: #1F6E5F; color: #FFFFFF; border-color: #1F6E5F; }
    .cl-hist-cal__cell--submitted { background: #FAEEDA; color: #A66019; border-color: #E8C29A; }
    .cl-hist-cal__cell--rejected { background: #FCEBEB; color: #A32D2D; border-color: #E5B5B5; }
    .cl-hist-cal__cell--draft { background: #F5F0EA; color: #6B4423; }
    .cl-hist-cal__legend {
        margin-top: 12px;
        display: flex; flex-direction: column; gap: 5px;
        font-size: 11px; color: #6B4423;
    }
    .cl-hist-cal__legend span { display: inline-flex; align-items: center; gap: 6px; }
    .cl-hist-cal__legend i {
        width: 11px; height: 11px; border-radius: 3px; border: 1px solid #E0D2C1;
        flex-shrink: 0;
    }

    /* Sidebar mini cards (reuse style from index) */
    .cl-hist-side {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 14px;
        padding: 14px 16px;
        margin-bottom: 14px;
        box-shadow: 0 2px 6px rgba(45,24,16,.04);
    }
    .cl-hist-side h6 {
        font-size: 11.5px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.4px; color: #9B8578; margin: 0 0 12px;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .cl-hist-side h6 i { color: #5C2E15; font-size: 11px; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <h4 class="page-title">Riwayat Checklist Saya</h4>
                <a href="{{ $type === 'weekly' ? route('mychecklist.weekform') : route('mychecklist') }}" class="st-btn-outline-brown">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="cl-hist-wrap">

        {{-- ─── TAB: Daily / Weekly ─── --}}
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
            <div style="display:inline-flex; gap:2px; padding:4px; background:#FFFFFF; border-radius:999px; border:1px solid #E0D2C1; box-shadow:0 1px 3px rgba(45,24,16,.06);">
                <a href="{{ route('mychecklist.history', ['type' => 'daily', 'month' => $month]) }}"
                   style="padding:8px 18px; font-size:13px; font-weight:{{ $type === 'daily' ? '700' : '600' }}; text-decoration:none; border-radius:999px; {{ $type === 'daily' ? 'background:linear-gradient(180deg,#5C2E15,#471500); color:#FFFFFF; box-shadow:0 2px 6px rgba(71,21,0,.20);' : 'color:#6B4423;' }} display:inline-flex; align-items:center; gap:7px;">
                    <i class="fas fa-sun"></i> Checklist Daily
                </a>
                <a href="{{ route('mychecklist.history', ['type' => 'weekly', 'month' => $month]) }}"
                   style="padding:8px 18px; font-size:13px; font-weight:{{ $type === 'weekly' ? '700' : '600' }}; text-decoration:none; border-radius:999px; {{ $type === 'weekly' ? 'background:linear-gradient(180deg,#5C2E15,#471500); color:#FFFFFF; box-shadow:0 2px 6px rgba(71,21,0,.20);' : 'color:#6B4423;' }} display:inline-flex; align-items:center; gap:7px;">
                    <i class="fas fa-calendar-week"></i> Checklist Weekly
                </a>
            </div>
        </div>

        {{-- ─── MONTH NAVIGATION ─── --}}
        <div class="cl-hist-header">
            <a href="{{ route('mychecklist.history', ['type' => $type, 'month' => $prevMonth]) }}" class="cl-hist-navbtn" title="Bulan sebelumnya">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h5 class="cl-hist-header__title">
                <i class="fas fa-calendar"></i>
                {{ $monthLabel }}
            </h5>
            <a href="{{ route('mychecklist.history', ['type' => $type, 'month' => $nextMonth]) }}" class="cl-hist-navbtn" title="Bulan berikutnya">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        {{-- ─── KPI STRIP ─── --}}
        <div class="cl-hist-kpi">
            <div class="cl-hist-kpi__cell cl-hist-kpi__cell--accent">
                <div class="cl-hist-kpi__label"><i class="fas fa-clipboard-list" style="color:#5C2E15;"></i> Total Pengisian</div>
                <div class="cl-hist-kpi__val">{{ $stats['total'] }} <small>{{ $type === 'weekly' ? 'minggu' : 'hari' }}</small></div>
            </div>
            <div class="cl-hist-kpi__cell cl-hist-kpi__cell--success">
                <div class="cl-hist-kpi__label"><i class="fas fa-check-circle" style="color:#1F6E5F;"></i> Disetujui</div>
                <div class="cl-hist-kpi__val">{{ $stats['approved'] }}</div>
            </div>
            <div class="cl-hist-kpi__cell cl-hist-kpi__cell--warning">
                <div class="cl-hist-kpi__label"><i class="fas fa-clock" style="color:#A66019;"></i> Menunggu</div>
                <div class="cl-hist-kpi__val">{{ $stats['pending'] }}</div>
            </div>
            <div class="cl-hist-kpi__cell cl-hist-kpi__cell--danger">
                <div class="cl-hist-kpi__label"><i class="fas fa-times-circle" style="color:#A32D2D;"></i> Ditolak</div>
                <div class="cl-hist-kpi__val">{{ $stats['rejected'] }}</div>
            </div>
            <div class="cl-hist-kpi__cell cl-hist-kpi__cell--neutral">
                <div class="cl-hist-kpi__label"><i class="fas fa-percent" style="color:#9B5E2C;"></i> Avg Progress</div>
                <div class="cl-hist-kpi__val">{{ $stats['avg_pct'] }}<small>%</small></div>
            </div>
        </div>

        <div class="cl-hist-grid">

            {{-- ─── MAIN: SUBMISSION LIST ─── --}}
            <div>
                @if($submissions->isEmpty())
                    <div style="background:#FFFFFF; border:1px solid #E0D2C1; border-radius:14px; padding:36px 22px; text-align:center;">
                        <div style="display:inline-flex; align-items:center; justify-content:center; width:70px; height:70px; border-radius:50%; background:#FAF4EC; color:#9B5E2C; font-size:26px; margin-bottom:14px;">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <h5 style="font-size:16px; font-weight:700; color:#2D1810; margin-bottom:6px;">
                            Tidak ada pengisian di {{ $monthLabel }}
                        </h5>
                        <p style="font-size:13px; color:#6B4423; max-width:360px; margin:0 auto;">
                            Coba pilih bulan lain atau mulai isi {{ $type === 'weekly' ? 'form mingguan' : 'checklist hari ini' }}.
                        </p>
                    </div>
                @else
                    <div class="cl-hist-list">
                        @foreach($submissions as $sub)
                            @php
                                $statusClass = $type === 'weekly' ? \App\Models\ChecklistWeeklySubmission::class : \App\Models\ChecklistSubmission::class;
                                $opt = $statusClass::statusOptions()[$sub->status] ?? ['label'=>$sub->status,'color'=>'neutral'];
                                $accentColor = [
                                    'success' => '#1F6E5F',
                                    'warning' => '#A66019',
                                    'danger'  => '#A32D2D',
                                    'neutral' => '#9B8578',
                                    'info'    => '#1E5A8C',
                                ][$opt['color']] ?? '#9B8578';
                                $pct = $sub->progressPct();
                                if ($type === 'weekly') {
                                    $weekStart = $sub->week_start_date;
                                    $weekEnd   = $weekStart->copy()->addDays(6);
                                    $dateLabel = $weekStart->translatedFormat('d M') . ' – ' . $weekEnd->translatedFormat('d M');
                                } else {
                                    $dateLabel = $sub->submission_date->translatedFormat('D, d M');
                                }
                                $grouped = $sub->items->groupBy('category_title_snapshot');
                                $isRejected = $sub->status === $statusClass::STATUS_REJECTED;
                            @endphp
                            <div class="cl-hist-card" data-hist-card style="--cl-accent: {{ $accentColor }};">
                                <div class="cl-hist-card__head" data-hist-toggle>
                                    <div class="cl-hist-card__date">
                                        <strong>{{ $dateLabel }}</strong>
                                        <small>{{ $sub->done_tasks }}/{{ $sub->total_tasks }} tugas ({{ $pct }}%)</small>
                                    </div>
                                    <div class="cl-hist-card__body">
                                        <div class="cl-hist-card__bar"><i style="width:{{ $pct }}%"></i></div>
                                        <div class="cl-hist-card__meta">
                                            @if($sub->submitted_at)
                                                <span><i class="fas fa-paper-plane" style="font-size:9px; color:#9B5E2C;"></i> Submit {{ $sub->submitted_at->format('H:i') }}</span>
                                            @endif
                                            @if($sub->reviewed_at)
                                                <i class="fas fa-circle dot"></i>
                                                <span><i class="fas fa-gavel" style="font-size:9px; color:#9B5E2C;"></i> Review {{ $sub->reviewed_at->format('d M H:i') }}</span>
                                            @endif
                                            @if($sub->review_notes)
                                                <i class="fas fa-circle dot"></i>
                                                <span style="color:{{ $accentColor }}; font-weight:600;"><i class="fas fa-comment-dots" style="font-size:9px;"></i> Ada catatan</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="cl-hist-card__right">
                                        <span class="st-pill st-pill--{{ $opt['color'] }}">{{ $opt['label'] }}</span>
                                    </div>
                                    <i class="fas fa-chevron-down cl-hist-card__chev"></i>
                                </div>

                                <div class="cl-hist-card__detail">
                                    @if($sub->review_notes)
                                        <div class="cl-hist-note" style="--cl-note-accent: {{ $accentColor }};">
                                            <i class="fas fa-comment-dots"></i>
                                            <div style="flex:1;">
                                                <strong>{{ $isRejected ? 'Catatan Penolakan' : 'Catatan Supervisor' }}</strong>
                                                {{ $sub->review_notes }}
                                            </div>
                                        </div>
                                    @endif

                                    @if($grouped->isEmpty())
                                        <div style="text-align:center; padding:14px 0; font-size:12.5px; color:#9B8578; font-style:italic;">
                                            Tidak ada item pada pengisian ini.
                                        </div>
                                    @else
                                        @foreach($grouped as $catTitle => $items)
                                            @php
                                                $catDone = $items->where('is_done', true)->count();
                                                $catTotal = $items->count();
                                                $catComplete = $catDone === $catTotal;
                                            @endphp
                                            <div class="cl-hist-cat">
                                                <div class="cl-hist-cat__head">
                                                    <span><i class="fas fa-folder"></i>{{ $catTitle }}</span>
                                                    <span class="cl-hist-cat__chip cl-hist-cat__chip--{{ $catComplete ? 'good' : 'warn' }}">
                                                        {{ $catDone }}/{{ $catTotal }}
                                                    </span>
                                                </div>
                                                @foreach($items as $item)
                                                    <div class="cl-hist-cat__task {{ $item->is_done ? 'is-done' : '' }}">
                                                        <span class="ck"></span>
                                                        <div class="ttl">
                                                            {{ $item->task_title_snapshot }}
                                                            @if($item->note)
                                                                <div class="note">
                                                                    <i class="fas fa-edit"></i>{{ $item->note }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                        @if($item->done_at)
                                                            <span class="time">{{ $item->done_at->format('H:i') }}</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($submissions->hasPages())
                        <div style="margin-top:16px;">
                            {{ $submissions->links() }}
                        </div>
                    @endif
                @endif
            </div>

            {{-- ─── SIDEBAR ─── --}}
            <aside>
                @if($type === 'daily')
                    <div class="cl-hist-side">
                        <h6><i class="fas fa-calendar-alt"></i> Kalender {{ $monthLabel }}</h6>
                        <div class="cl-hist-cal">
                            @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $dow)
                                <div class="cl-hist-cal__dow">{{ $dow }}</div>
                            @endforeach
                            @foreach($calendar as $c)
                                @if($c === null)
                                    <div class="cl-hist-cal__cell is-blank"></div>
                                @else
                                    @php
                                        $cls = 'cl-hist-cal__cell';
                                        if ($c->status) $cls .= ' cl-hist-cal__cell--' . $c->status;
                                        if ($c->is_today) $cls .= ' is-today';
                                        if ($c->is_future) $cls .= ' is-future';
                                        $statusLabel = match($c->status) {
                                            'approved'  => 'Disetujui',
                                            'submitted' => 'Menunggu',
                                            'rejected'  => 'Ditolak',
                                            'draft'     => 'Draft',
                                            default     => $c->is_future ? 'Belum tiba' : 'Tidak diisi',
                                        };
                                    @endphp
                                    <div class="{{ $cls }}"
                                         title="{{ $c->date->translatedFormat('l, d M Y') }} — {{ $statusLabel }}{{ $c->pct !== null ? ' (' . $c->pct . '%)' : '' }}">
                                        {{ $c->day }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <div class="cl-hist-cal__legend">
                            <span><i style="background:#1F6E5F; border-color:#1F6E5F;"></i> Disetujui</span>
                            <span><i style="background:#FAEEDA; border-color:#E8C29A;"></i> Menunggu approval</span>
                            <span><i style="background:#FCEBEB; border-color:#E5B5B5;"></i> Ditolak</span>
                            <span><i style="background:#F5F0EA;"></i> Draft / belum submit</span>
                            <span><i style="background:#FAFAF8;"></i> Tidak diisi</span>
                        </div>
                    </div>
                @endif

                <div class="cl-hist-side" style="background:linear-gradient(135deg, #FAF4EC 0%, #F5EBE0 100%); border-color:#E0D2C1;">
                    <h6 style="color:#5C2E15;"><i class="fas fa-info-circle" style="color:#A66019;"></i> Cara Pakai</h6>
                    <p style="font-size:12.5px; color:#5C2E15; line-height:1.55; margin:0;">
                        @if($type === 'weekly')
                            Tab <strong>Weekly</strong> menampilkan form mingguan yang sudah di-submit per minggu. Klik card untuk lihat detail tugas yang dicentang di setiap hari.
                        @else
                            Klik card untuk lihat detail tugas yang dicentang. Klik tanggal di kalender untuk lihat status singkat (hover).
                        @endif
                    </p>
                </div>
            </aside>

        </div>{{-- /grid --}}
    </div>
</div>

<script>
(function() {
    document.querySelectorAll('[data-hist-toggle]').forEach(head => {
        head.addEventListener('click', () => {
            head.closest('[data-hist-card]').classList.toggle('is-open');
        });
    });
})();
</script>
@endsection
