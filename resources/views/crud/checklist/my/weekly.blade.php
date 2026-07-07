@extends('layouts.app')

@section('css')
<style>
    /* ──────── Weekly grid (calendar-style checklist) ──────── */
    .cl-week-wrap { max-width: 1280px; margin: 0 auto; }

    /* ── Modern hero (gradient brown) — sama style dgn Hari Ini ── */
    .cl-mod-hero {
        background: linear-gradient(135deg, #5C2E15 0%, #471500 100%);
        color: #FFFFFF;
        border-radius: 18px;
        padding: 22px 24px;
        margin-bottom: 14px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(71,21,0,.18);
    }
    .cl-mod-hero::before, .cl-mod-hero::after {
        content: ""; position: absolute; border-radius: 50%; pointer-events: none;
    }
    .cl-mod-hero::before {
        top: -50px; right: -50px; width: 180px; height: 180px;
        background: rgba(255,255,255,.06);
    }
    .cl-mod-hero::after {
        bottom: -60px; right: 90px; width: 110px; height: 110px;
        background: rgba(255,255,255,.04);
    }
    .cl-mod-hero__row {
        display: flex; justify-content: space-between; align-items: center;
        gap: 14px; flex-wrap: wrap;
        position: relative; z-index: 1;
    }
    .cl-mod-hero__left {
        display: flex; align-items: center; gap: 14px;
        min-width: 0; flex: 1;
    }
    .cl-mod-hero__icon-box {
        width: 52px; height: 52px;
        border-radius: 14px;
        background: rgba(255,255,255,.16);
        color: #FFFFFF;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px;
        border: 1.5px solid rgba(255,255,255,.30);
        flex-shrink: 0;
    }
    .cl-mod-hero__greet {
        font-size: 20px; font-weight: 700; color: #FFFFFF;
        margin: 0 0 4px; line-height: 1.15; letter-spacing: -0.01em;
    }
    .cl-mod-hero__meta {
        font-size: 12.5px; color: rgba(255,255,255,.85);
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .cl-mod-hero__meta strong { color: #FFF; font-weight: 700; }
    .cl-mod-hero__chip {
        background: rgba(255,255,255,.14);
        padding: 4px 11px; border-radius: 999px;
        border: 1px solid rgba(255,255,255,.22);
        font-size: 11.5px; font-weight: 600;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .cl-mod-hero__actions {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }

    /* Modern view toggle pill — di atas hero gradient */
    .cl-mod-toggle {
        display: inline-flex; gap: 2px; padding: 4px;
        background: rgba(255,255,255,.16);
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,.22);
        backdrop-filter: blur(8px);
    }
    .cl-mod-toggle a {
        padding: 7px 16px;
        font-size: 12.5px; font-weight: 600;
        color: rgba(255,255,255,.85);
        text-decoration: none;
        border-radius: 999px;
        display: inline-flex; align-items: center; gap: 6px;
        transition: all .15s;
        line-height: 1.2;
    }
    .cl-mod-toggle a:hover { color: #FFFFFF; text-decoration: none; }
    .cl-mod-toggle a.is-active {
        background: #FFFFFF;
        color: #471500;
        box-shadow: 0 2px 6px rgba(0,0,0,.15);
        font-weight: 700;
    }
    .cl-mod-hero__btn-ghost {
        background: rgba(255,255,255,.14);
        border: 1px solid rgba(255,255,255,.22);
        color: #FFFFFF;
        padding: 7px 14px; border-radius: 999px;
        font-size: 12.5px; font-weight: 600;
        text-decoration: none;
        display: inline-flex; align-items: center; gap: 6px;
        transition: background .15s;
    }
    .cl-mod-hero__btn-ghost:hover { background: rgba(255,255,255,.24); color: #FFF; text-decoration: none; }

    /* Modern banner */
    .cl-mod-banner {
        padding: 14px 18px; border-radius: 14px; margin-bottom: 14px;
        display: flex; align-items: flex-start; gap: 12px;
        font-size: 13px; line-height: 1.5;
        border-left: 4px solid;
    }
    .cl-mod-banner i { font-size: 16px; margin-top: 1px; flex-shrink: 0; }
    .cl-mod-banner--success { background: #E1F5EE; border-color: #1F6E5F; color: #0F6E56; }
    .cl-mod-banner--warning { background: #FAEEDA; border-color: #A66019; color: #5C2E15; }
    .cl-mod-banner--danger  { background: #FCEBEB; border-color: #A32D2D; color: #7C2929; }

    /* Modern progress strip (compact horizontal) */
    .cl-mod-week-progress {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 16px;
        padding: 16px 22px;
        margin-bottom: 14px;
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 20px;
        align-items: center;
        box-shadow: 0 2px 8px rgba(45,24,16,.04);
        position: relative;
        overflow: hidden;
    }
    .cl-mod-week-progress::after {
        content: "";
        position: absolute; inset: 0;
        background: radial-gradient(circle at top right, rgba(155,94,44,.05), transparent 50%);
        pointer-events: none;
    }
    .cl-mod-week-progress__pct {
        font-size: 36px; font-weight: 800;
        color: #2D1810; line-height: 1;
        letter-spacing: -0.03em;
        display: flex; align-items: baseline;
    }
    .cl-mod-week-progress__pct small { font-size: 16px; color: #9B5E2C; font-weight: 700; }
    .cl-mod-week-progress__body { min-width: 0; }
    .cl-mod-week-progress__label {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: #9B8578; margin-bottom: 5px;
    }
    .cl-mod-week-progress__bar {
        background: #F5F0EA; height: 8px; border-radius: 4px; overflow: hidden;
    }
    .cl-mod-week-progress__bar > i {
        display: block; height: 100%;
        background: linear-gradient(90deg, #9B5E2C, #5C2E15);
        border-radius: 4px;
        transition: width .4s cubic-bezier(.45,.05,.55,.95);
    }
    .cl-mod-week-progress__count {
        font-size: 13px; color: #6B4423; font-weight: 600; white-space: nowrap;
        text-align: right;
    }
    .cl-mod-week-progress__count strong { color: #2D1810; font-size: 15px; }
    @media (max-width: 540px) {
        .cl-mod-week-progress {
            grid-template-columns: 1fr; padding: 16px 18px; gap: 12px; text-align: center;
        }
        .cl-mod-week-progress__count { text-align: center; }
    }

    /* Modern week nav + grid card */
    .cl-mod-week-card {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(45,24,16,.04);
    }
    .cl-mod-week-nav {
        display: flex; justify-content: space-between; align-items: center;
        padding: 14px 18px;
        border-bottom: 1px solid #E0D2C1;
        background: linear-gradient(180deg, #FFFFFF 0%, #FAF4EC 100%);
    }
    .cl-mod-week-nav__label {
        font-weight: 700; color: #2D1810; font-size: 14.5px;
        display: flex; align-items: center; gap: 8px;
    }
    .cl-mod-week-nav__label i { color: #5C2E15; font-size: 13px; }
    .cl-mod-week-nav__label small { color: #6B4423; font-weight: 500; margin-left: 6px; font-size: 12px; }
    .cl-mod-week-nav__label small a { color: #5C2E15; text-decoration: underline; font-weight: 600; }
    .cl-mod-week-nav__btn {
        width: 36px; height: 36px;
        border-radius: 10px;
        border: 1px solid #E0D2C1;
        background: #FFFFFF;
        color: #5C2E15;
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 14px; text-decoration: none;
        transition: all .15s;
    }
    .cl-mod-week-nav__btn:hover {
        background: #FAF4EC;
        border-color: #C9B69A;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .cl-view-toggle {
        display: inline-flex;
        gap: 2px;
        padding: 4px;
        background: var(--cl-cream-2);
        border-radius: 999px;
        border: 1px solid var(--cl-line);
    }
    .cl-view-toggle a {
        padding: 6px 14px;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--cl-muted);
        text-decoration: none;
        border-radius: 999px;
        line-height: 1.2;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all .15s;
    }
    .cl-view-toggle a:hover {
        color: var(--cl-brown-2);
        text-decoration: none;
    }
    .cl-view-toggle a.is-active {
        background: #FFFFFF;
        color: var(--cl-brown);
        box-shadow: 0 1px 3px rgba(45,24,16,.10);
    }

    .cl-week-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 18px;
        border-bottom: 1px solid var(--cl-line);
        background: linear-gradient(180deg, #FFFFFF 0%, var(--cl-cream-1) 100%);
    }
    .cl-week-nav__label {
        font-weight: 700;
        color: var(--cl-ink);
        font-size: 14px;
    }
    .cl-week-nav__label small { color: var(--cl-muted); font-weight: 500; margin-left: 6px; }
    .cl-week-nav__btn {
        width: 32px; height: 32px;
        border-radius: 8px;
        border: 1px solid var(--cl-line);
        background: #FFFFFF;
        color: var(--cl-brown-2);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        transition: all .15s;
        text-decoration: none;
    }
    .cl-week-nav__btn:hover {
        background: var(--cl-cream-1);
        border-color: var(--cl-line-strong);
        text-decoration: none;
    }

    .cl-week-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
        background: #FFFFFF;
    }
    .cl-week-table thead th {
        background: var(--cl-cream-1);
        color: var(--cl-ink);
        font-weight: 700;
        font-size: 11.5px;
        padding: 10px 6px;
        text-align: center;
        border: 1px solid var(--cl-line);
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .cl-week-table thead th.task-col {
        text-align: left;
        padding-left: 16px;
        background: #FFFFFF;
        width: 38%;
        min-width: 280px;
    }
    .cl-week-table thead th.day-col {
        width: 80px;
        min-width: 64px;
    }
    .cl-week-table thead th.day-col.today {
        background: linear-gradient(180deg, var(--cl-brown-2) 0%, var(--cl-brown) 100%);
        color: #FFFFFF;
        position: relative;
    }
    .cl-week-table thead th.day-col.today::after {
        content: "HARI INI";
        position: absolute;
        bottom: -19px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--cl-brown);
        color: #FFFFFF;
        font-size: 8.5px;
        letter-spacing: 0.5px;
        padding: 2px 8px;
        border-radius: 0 0 4px 4px;
        font-weight: 700;
        white-space: nowrap;
    }
    .cl-week-table thead th .day-num {
        display: block;
        font-size: 16px;
        font-weight: 800;
        margin-top: 2px;
    }

    .cl-week-table tbody td {
        border: 1px solid var(--cl-line);
        padding: 8px 10px;
        background: #FFFFFF;
        vertical-align: middle;
    }
    .cl-week-table tbody td.task-col {
        font-size: 13px;
        color: var(--cl-ink);
        padding: 10px 16px;
        font-weight: 500;
    }

    /* Category header row */
    .cl-week-table tbody tr.cat-row td {
        background: var(--cl-cream-2);
        font-weight: 700;
        color: var(--cl-ink);
        font-size: 12.5px;
        padding: 10px 14px;
    }
    .cl-week-table tbody tr.cat-row .cat-inner {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .cl-week-table tbody tr.cat-row .cat-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px; height: 22px;
        background: linear-gradient(135deg, var(--cl-brown-2), var(--cl-brown));
        color: #FFFFFF;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
    }
    .cl-week-table tbody tr.cat-row .cat-meta {
        margin-left: auto;
        font-size: 10.5px;
        color: var(--cl-muted);
        font-weight: 500;
        background: #FFFFFF;
        padding: 2px 8px;
        border-radius: 10px;
        border: 1px solid var(--cl-line);
    }
    /* Accordion per kategori */
    .cl-week-table tbody tr.cat-row { cursor: pointer; user-select: none; }
    .cl-week-table tbody tr.cat-row:hover td { background: var(--cl-cream-1); }
    .cl-week-table tbody tr.cat-row .cat-caret {
        margin-left: 10px;
        font-size: 11px;
        color: var(--cl-brown-2);
        transition: transform .2s ease;
    }
    .cl-week-table tbody tr.cat-row.is-collapsed .cat-caret { transform: rotate(-90deg); }
    .cl-week-table tbody tr.cl-task-row.is-hidden { display: none; }

    /* Day cells */
    .cl-week-cell {
        text-align: center;
        height: 36px;
        position: relative;
        font-size: 14px;
        line-height: 1;
    }
    .cl-week-cell.is-past { background: #FAFAF8 !important; cursor: not-allowed; color: var(--cl-subtle); }
    .cl-week-cell.is-future { background: #FAFAF8 !important; cursor: not-allowed; color: var(--cl-subtle); }
    .cl-week-cell.is-done {
        background: var(--cl-success-bg) !important;
        cursor: not-allowed;
    }
    .cl-week-cell.is-done::before {
        content: "✓";
        color: var(--cl-success);
        font-weight: 800;
        font-size: 14px;
    }
    .cl-week-cell.is-today {
        background: #FFFFFF !important;
        outline: 2px solid var(--cl-brown);
        outline-offset: -2px;
        cursor: pointer;
        transition: background .15s;
    }
    .cl-week-cell.is-today:hover { background: var(--cl-cream-1) !important; }
    .cl-week-cell.is-today.is-done {
        background: var(--cl-success-bg) !important;
        cursor: pointer;
    }
    .cl-week-cell.is-today.is-done:hover { background: #CDEAD9 !important; }
    .cl-week-cell.is-today:not(.is-done):hover::before {
        content: "+";
        color: var(--cl-brown-3);
        font-size: 14px;
        font-weight: 600;
        opacity: .5;
    }
    .cl-week-cell.is-locked { cursor: not-allowed !important; opacity: .65; }

    /* Progress header (sama dengan view harian) */
    .cl-week-progress {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px 18px;
        background: var(--cl-cream-1);
        border-bottom: 1px solid var(--cl-line);
    }
    .cl-week-progress__pct {
        font-size: 26px;
        font-weight: 800;
        color: var(--cl-ink);
        line-height: 1;
        letter-spacing: -0.02em;
    }
    .cl-week-progress__meta { flex: 1; min-width: 0; }
    .cl-week-progress__label {
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--cl-subtle);
        margin-bottom: 4px;
    }
    .cl-week-progress__bar {
        background: var(--cl-cream-2);
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
    }
    .cl-week-progress__bar > i {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, var(--cl-brown-2), var(--cl-brown));
        border-radius: 4px;
        transition: width .3s;
    }
    .cl-week-progress__count {
        font-size: 13px;
        color: var(--cl-muted);
        font-weight: 600;
        white-space: nowrap;
    }
    .cl-week-progress__count strong { color: var(--cl-ink); }

    @media (max-width: 900px) {
        .cl-week-table thead th.day-col { min-width: 50px; padding: 8px 4px; }
        .cl-week-table thead th .day-num { font-size: 14px; }
        .cl-week-cell { height: 32px; font-size: 12px; }
        .cl-week-table tbody td.task-col { font-size: 12px; padding: 8px 12px; }
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Checklist Saya</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="cl-week-wrap">
        @if(! $position)
            <div class="st-card">
                <div class="st-card__body" style="padding:48px 22px; text-align:center;">
                    <div style="display:inline-flex; align-items:center; justify-content:center; width:80px; height:80px; border-radius:50%; background:var(--cl-cream-1); color:var(--cl-brown-2); font-size:32px; margin-bottom:18px;">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <h5 style="font-size:18px; font-weight:700; color:var(--cl-ink); margin-bottom:8px;">Posisi belum diatur</h5>
                    <p style="font-size:13.5px; color:var(--cl-muted); max-width:420px; margin:0 auto;">
                        Anda belum di-assign ke divisi mana pun. Hubungi supervisor untuk mendaftarkan posisi.
                    </p>
                </div>
            </div>
        @else
            {{-- ─── TOP TAB: Checklist Daily / Checklist Weekly ─── --}}
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                <div style="display:inline-flex; gap:2px; padding:4px; background:#FFFFFF; border-radius:999px; border:1px solid #E0D2C1; box-shadow:0 1px 3px rgba(45,24,16,.06);">
                    <a href="{{ route('mychecklist') }}"
                       style="padding:8px 18px; font-size:13px; font-weight:700; text-decoration:none; border-radius:999px; background:linear-gradient(180deg,#5C2E15,#471500); color:#FFFFFF; box-shadow:0 2px 6px rgba(71,21,0,.20); display:inline-flex; align-items:center; gap:7px;">
                        <i class="fas fa-sun"></i> Checklist Daily
                    </a>
                    <a href="{{ route('mychecklist.weekform') }}"
                       style="padding:8px 18px; font-size:13px; font-weight:600; text-decoration:none; border-radius:999px; color:#6B4423; display:inline-flex; align-items:center; gap:7px;">
                        <i class="fas fa-calendar-week"></i> Checklist Weekly
                    </a>
                </div>
                <a href="{{ route('mychecklist.history') }}" style="font-size:12.5px; color:#6B4423; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-weight:600;">
                    <i class="fas fa-history"></i> Riwayat
                </a>
            </div>

            {{-- SUB-TOGGLE: Hari Ini vs Tampilan Mingguan (7 hari) --}}
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
                <span style="font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#9B8578;">Tampilan:</span>
                <div style="display:inline-flex; gap:2px; padding:3px; background:#FAF4EC; border-radius:999px; border:1px solid #E0D2C1;">
                    <a href="{{ route('mychecklist') }}"
                       style="padding:6px 14px; font-size:12px; font-weight:600; text-decoration:none; border-radius:999px; color:#6B4423; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fas fa-sun" style="font-size:10px;"></i> Hari Ini
                    </a>
                    <a href="{{ route('mychecklist.weekly') }}"
                       style="padding:6px 14px; font-size:12px; font-weight:700; text-decoration:none; border-radius:999px; background:#FFFFFF; color:#5C2E15; box-shadow:0 1px 3px rgba(45,24,16,.10); display:inline-flex; align-items:center; gap:6px;">
                        <i class="fas fa-th" style="font-size:10px;"></i> Seminggu
                    </a>
                </div>
            </div>

            {{-- ─── MODERN HERO (gradient brown) ─── --}}
            <div class="cl-mod-hero">
                <div class="cl-mod-hero__row">
                    <div class="cl-mod-hero__left">
                        <div class="cl-mod-hero__icon-box">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div style="min-width:0;">
                            <h5 class="cl-mod-hero__greet">Checklist Mingguan</h5>
                            <div class="cl-mod-hero__meta">
                                <i class="fas fa-calendar" style="font-size:11px; opacity:.85;"></i>
                                <strong>{{ $start->translatedFormat('d M') }} – {{ $end->translatedFormat('d M Y') }}</strong>
                                <span class="cl-mod-hero__chip">
                                    <i class="fas fa-id-badge" style="font-size:10px;"></i>
                                    {{ $position->division?->name ?? $position->division_id }}
                                </span>
                                @if($position->branch)
                                    <span class="cl-mod-hero__chip">
                                        <i class="fas fa-building" style="font-size:10px;"></i>
                                        {{ $position->branch->name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- BANNER STATUS --}}
            @if($submission->status === \App\Models\ChecklistSubmission::STATUS_REJECTED)
                <div class="cl-mod-banner cl-mod-banner--danger">
                    <i class="fas fa-times-circle"></i>
                    <div style="flex:1;">
                        <strong>Checklist anda ditolak supervisor.</strong>
                        @if($submission->review_notes)<div style="margin-top:4px;">Catatan: {{ $submission->review_notes }}</div>@endif
                        <form method="POST" action="{{ route('mychecklist.reopen-self') }}" style="margin-top:8px;">
                            @csrf
                            <button type="submit" class="st-btn-brand" style="font-size:12.5px; padding:6px 14px;">
                                <i class="fas fa-pen"></i> Perbaiki Checklist
                            </button>
                        </form>
                    </div>
                </div>
            @elseif($submission->status === \App\Models\ChecklistSubmission::STATUS_SUBMITTED)
                <div class="cl-mod-banner cl-mod-banner--warning">
                    <i class="fas fa-clock"></i>
                    <div><strong>Menunggu approval supervisor.</strong> Toggle nonaktif sampai supervisor review.</div>
                </div>
            @elseif($submission->status === \App\Models\ChecklistSubmission::STATUS_APPROVED)
                <div class="cl-mod-banner cl-mod-banner--success">
                    <i class="fas fa-check-circle"></i>
                    <div><strong>Checklist hari ini disetujui supervisor.</strong> Terima kasih atas konsistensinya! 🎉</div>
                </div>
            @endif

            {{-- PROGRESS STRIP (compact) --}}
            <div class="cl-mod-week-progress">
                <div class="cl-mod-week-progress__pct">{{ $progress['pct'] }}<small>%</small></div>
                <div class="cl-mod-week-progress__body">
                    <div class="cl-mod-week-progress__label">Progres Hari Ini</div>
                    <div class="cl-mod-week-progress__bar"><i style="width:{{ $progress['pct'] }}%"></i></div>
                </div>
                <div class="cl-mod-week-progress__count">
                    <strong>{{ $progress['done'] }}</strong> / {{ $progress['total'] }} tugas
                </div>
            </div>

            {{-- CARD: Week nav + Grid --}}
            <div class="cl-mod-week-card">

                {{-- WEEK NAVIGATION --}}
                <div class="cl-mod-week-nav">
                    <a href="{{ route('mychecklist.weekly', ['week' => $weekOffset - 1]) }}" class="cl-mod-week-nav__btn" title="Minggu sebelumnya">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <div class="cl-mod-week-nav__label">
                        <i class="fas fa-calendar-day"></i>
                        {{ $start->translatedFormat('d M') }} – {{ $end->translatedFormat('d M Y') }}
                        @if($weekOffset !== 0)
                            <small>· <a href="{{ route('mychecklist.weekly') }}">Kembali ke minggu ini</a></small>
                        @endif
                    </div>
                    <a href="{{ route('mychecklist.weekly', ['week' => $weekOffset + 1]) }}" class="cl-mod-week-nav__btn" title="Minggu berikutnya">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>

                {{-- GRID --}}
                @if($rows->isEmpty())
                    <div class="cl-empty">
                        <span class="cl-empty__icon"><i class="fas fa-clipboard"></i></span>
                        <div class="cl-empty__title">Belum ada tugas</div>
                        <div class="cl-empty__desc">Supervisor belum mengatur task untuk divisi {{ $position->division?->name }}.</div>
                    </div>
                @else
                    <div style="overflow-x:auto;">
                        <table class="cl-week-table">
                            <thead>
                                <tr>
                                    <th class="task-col">Tugas</th>
                                    @foreach($days as $d)
                                        <th class="day-col {{ $d->is_today ? 'today' : '' }}">
                                            {{ $d->day_short }}
                                            <span class="day-num">{{ $d->day_num }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $idx => $row)
                                    @php $catCount = $row->tasks->count(); @endphp
                                    <tr class="cat-row {{ $idx === 0 ? '' : 'is-collapsed' }}" data-cat-toggle="{{ $idx }}">
                                        <td colspan="{{ count($days) + 1 }}">
                                            <div class="cat-inner">
                                                <span class="cat-num">{{ $idx + 1 }}</span>
                                                {{ $row->category->name }}
                                                <span class="cat-meta">{{ $catCount }} tugas</span>
                                                <i class="fas fa-chevron-down cat-caret"></i>
                                            </div>
                                        </td>
                                    </tr>
                                    @foreach($row->tasks as $taskRow)
                                        <tr class="cl-task-row {{ $idx === 0 ? '' : 'is-hidden' }}" data-cat="{{ $idx }}">
                                            <td class="task-col">{{ $taskRow->task->title }}</td>
                                            @foreach($taskRow->cells as $cell)
                                                @php
                                                    $classes = ['cl-week-cell'];
                                                    if ($cell->is_done)    $classes[] = 'is-done';
                                                    if ($cell->day->is_today) $classes[] = 'is-today';
                                                    if ($cell->day->is_past)  $classes[] = 'is-past';
                                                    if ($cell->day->is_future) $classes[] = 'is-future';
                                                    if ($cell->day->is_today && ! $cell->editable) $classes[] = 'is-locked';
                                                @endphp
                                                <td class="{{ implode(' ', $classes) }}"
                                                    @if($cell->editable && $cell->item)
                                                        data-item-id="{{ $cell->item->id }}"
                                                        data-toggle-url="{{ route('mychecklist.item.toggle', $cell->item->id) }}"
                                                    @endif></td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            </div>{{-- /cl-mod-week-card --}}

            {{-- SUBMIT FOOTER (gradient brown, ala Hari Ini) --}}
            @if($submission->isEditable() && $rows->isNotEmpty())
                <form method="POST" action="{{ route('mychecklist.submit') }}" style="margin-top:14px;"
                      onsubmit="return confirm('Submit checklist hari ini?');">
                    @csrf
                    <div style="background:linear-gradient(135deg,#5C2E15,#471500); color:#FFFFFF; border-radius:14px; padding:14px 22px; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; box-shadow:0 6px 20px rgba(71,21,0,.20);">
                        <div style="font-size:12.5px; color:rgba(255,255,255,.85); line-height:1.4;">
                            @if($progress['done'] === 0)
                                Mulai centang task hari ini di kolom <strong style="color:#FFF;">HARI INI</strong>.
                            @elseif($progress['done'] < $progress['total'])
                                Masih ada <strong style="color:#FFF;">{{ $progress['total'] - $progress['done'] }} task</strong> belum dicentang.
                            @else
                                <strong style="color:#FFF;">Semua task selesai! 🎉</strong> Klik submit untuk kirim.
                            @endif
                        </div>
                        <button type="submit"
                                style="border:none; background:#FFFFFF; color:#471500; padding:10px 22px; border-radius:999px; font-weight:700; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(0,0,0,.10);"
                                {{ $progress['done'] === 0 ? 'disabled' : '' }}>
                            <i class="fas fa-paper-plane"></i> Submit Checklist
                        </button>
                    </div>
                </form>
            @endif
        @endif
    </div>
</div>

@if($position && $rows->isNotEmpty())
<script>
(function() {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content;
    const pctEl   = document.querySelector('.cl-mod-week-progress__pct');
    const fillEl  = document.querySelector('.cl-mod-week-progress__bar i');
    const countEl = document.querySelector('.cl-mod-week-progress__count');

    // Accordion eksklusif: klik header kategori → buka baris task-nya & tutup kategori lain.
    document.querySelectorAll('tr.cat-row[data-cat-toggle]').forEach(catRow => {
        catRow.addEventListener('click', () => {
            const cat = catRow.dataset.catToggle;
            const willOpen = catRow.classList.contains('is-collapsed');
            // Tutup semua dulu.
            document.querySelectorAll('tr.cat-row[data-cat-toggle]').forEach(cr => cr.classList.add('is-collapsed'));
            document.querySelectorAll('tr.cl-task-row').forEach(r => r.classList.add('is-hidden'));
            // Buka yang diklik (kalau tadinya tertutup).
            if (willOpen) {
                catRow.classList.remove('is-collapsed');
                document.querySelectorAll('tr.cl-task-row[data-cat="' + cat + '"]').forEach(r => r.classList.remove('is-hidden'));
            }
        });
    });

    document.querySelectorAll('.cl-week-cell.is-today:not(.is-locked)').forEach(cell => {
        if (! cell.dataset.toggleUrl) return;
        cell.addEventListener('click', async () => {
            const wasDone = cell.classList.contains('is-done');
            cell.classList.toggle('is-done');

            try {
                const res = await fetch(cell.dataset.toggleUrl, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/json',
                    },
                });
                const data = await res.json();
                if (! data.ok) throw new Error(data.error || 'Gagal toggle');

                // Sync from server (in case optimistic was wrong)
                cell.classList.toggle('is-done', data.is_done);

                if (data.progress) {
                    if (pctEl)   pctEl.innerHTML   = data.progress.pct + '<small>%</small>';
                    if (fillEl)  fillEl.style.width = data.progress.pct + '%';
                    if (countEl) countEl.innerHTML = '<strong>' + data.progress.done + '</strong> / ' + data.progress.total + ' tugas';
                }
            } catch (err) {
                cell.classList.toggle('is-done', wasDone);
                alert('Gagal: ' + (err.message || err));
            }
        });
    });
})();
</script>
@endif
@endsection
