@extends('layouts.app')

@section('css')
<style>
    .wf-wrap { max-width: 1100px; margin: 0 auto; padding-bottom: 32px; }
    .wf-hero {
        background: linear-gradient(135deg, #5C2E15 0%, #471500 100%);
        color: #FFFFFF;
        border-radius: 18px;
        padding: 20px 24px;
        margin-bottom: 14px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(71,21,0,.18);
    }
    .wf-hero::before {
        content: ""; position: absolute; top: -50px; right: -50px;
        width: 180px; height: 180px; border-radius: 50%;
        background: rgba(255,255,255,.06); pointer-events: none;
    }
    .wf-hero__row {
        display: flex; justify-content: space-between; align-items: center;
        gap: 14px; flex-wrap: wrap; position: relative; z-index: 1;
    }
    .wf-hero__title { margin: 0 0 4px; font-size: 19px; font-weight: 800; letter-spacing: -0.01em; }
    .wf-hero__title small { font-size: 12px; font-weight: 500; opacity: .82; display: block; margin-top: 2px; }
    .wf-hero__meta {
        font-size: 12px; color: rgba(255,255,255,.85);
        display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
    }
    .wf-hero__chip {
        background: rgba(255,255,255,.14); padding: 3px 10px;
        border-radius: 999px; border: 1px solid rgba(255,255,255,.22);
        font-size: 11px; font-weight: 600;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .wf-hero__status {
        padding: 6px 14px; border-radius: 999px;
        font-size: 12px; font-weight: 700;
        backdrop-filter: blur(8px);
        display: inline-flex; align-items: center; gap: 6px;
    }
    .wf-hero__status--success { background: rgba(225,245,238,.95); color: #0F6E56; border: 1px solid rgba(31,110,95,.30); }
    .wf-hero__status--warning { background: rgba(250,238,218,.95); color: #5C2E15; border: 1px solid rgba(166,96,25,.30); }
    .wf-hero__status--danger  { background: rgba(252,235,235,.95); color: #7C2929; border: 1px solid rgba(163,45,45,.30); }

    /* Week nav */
    .wf-nav {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 14px;
        padding: 12px 16px;
        margin-bottom: 14px;
        display: flex; justify-content: space-between; align-items: center;
        gap: 10px; flex-wrap: wrap;
        box-shadow: 0 2px 6px rgba(45,24,16,.04);
    }
    .wf-nav__title { font-size: 14.5px; font-weight: 800; color: #2D1810; margin: 0; display: inline-flex; align-items: center; gap: 8px; }
    .wf-nav__title i { color: #9B5E2C; font-size: 12px; }
    .wf-nav__btn {
        width: 34px; height: 34px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 50%;
        background: #FAF4EC; color: #5C2E15;
        text-decoration: none; border: 1px solid #E0D2C1;
        transition: background .15s, transform .1s;
    }
    .wf-nav__btn:hover { background: #F5EBE0; transform: translateY(-1px); text-decoration: none; color: #5C2E15; }

    /* Progress card */
    .wf-progress {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 16px;
        padding: 18px 22px;
        margin-bottom: 14px;
        box-shadow: 0 2px 8px rgba(45,24,16,.04);
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 22px;
        align-items: center;
    }
    .wf-progress__big {
        font-size: 44px; font-weight: 800;
        color: #2D1810; line-height: 1;
        letter-spacing: -0.04em;
        display: flex; align-items: baseline; gap: 2px;
    }
    .wf-progress__big small { font-size: 18px; color: #9B5E2C; font-weight: 700; }
    .wf-progress__body { min-width: 0; }
    .wf-progress__label {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: #9B8578; margin-bottom: 4px;
    }
    .wf-progress__count { font-size: 13.5px; color: #2D1810; font-weight: 700; margin-bottom: 10px; }
    .wf-progress__count small { color: #6B4423; font-weight: 500; font-size: 12px; }
    .wf-progress__bar { background: #F5F0EA; height: 9px; border-radius: 5px; overflow: hidden; }
    .wf-progress__bar > i {
        display: block; height: 100%;
        background: linear-gradient(90deg, #9B5E2C, #5C2E15);
        border-radius: 5px;
        transition: width .4s cubic-bezier(.45,.05,.55,.95);
    }
    @media (max-width: 540px) {
        .wf-progress { grid-template-columns: 1fr; padding: 18px; gap: 12px; }
        .wf-progress__big { font-size: 36px; }
    }

    /* Banners */
    .wf-banner {
        padding: 14px 18px;
        border-radius: 14px;
        margin-bottom: 14px;
        display: flex; align-items: flex-start; gap: 12px;
        font-size: 13px; line-height: 1.5;
        border-left: 4px solid;
    }
    .wf-banner i { font-size: 16px; margin-top: 1px; flex-shrink: 0; }
    .wf-banner--success { background: #E1F5EE; border-color: #1F6E5F; color: #0F6E56; }
    .wf-banner--warning { background: #FAEEDA; border-color: #A66019; color: #5C2E15; }
    .wf-banner--danger  { background: #FCEBEB; border-color: #A32D2D; color: #7C2929; }
    .wf-banner--info    { background: #E0EFFA; border-color: #1E5A8C; color: #1A4D78; }

    /* Day card */
    .wf-day-list { display: flex; flex-direction: column; gap: 12px; }
    .wf-day {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(45,24,16,.04);
        overflow: hidden;
    }
    .wf-day.is-empty { opacity: .55; }
    .wf-day__head {
        display: flex; align-items: center; gap: 14px;
        padding: 14px 18px;
        background: linear-gradient(180deg, #FFFFFF 0%, #FAF4EC 100%);
        border-bottom: 1px solid #F0E8DC;
    }
    .wf-day__num {
        width: 50px; height: 50px;
        border-radius: 12px;
        background: linear-gradient(135deg, #5C2E15, #471500);
        color: #FFFFFF;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(71,21,0,.22);
    }
    .wf-day__num strong { font-size: 18px; font-weight: 800; line-height: 1; }
    .wf-day__num small { font-size: 10px; opacity: .85; margin-top: 2px; }
    .wf-day.is-today .wf-day__num { background: linear-gradient(135deg, #1F6E5F, #155148); box-shadow: 0 4px 10px rgba(31,110,95,.30); }
    .wf-day.is-past .wf-day__num { background: linear-gradient(135deg, #9B8578, #6B5944); box-shadow: 0 4px 10px rgba(107,89,68,.20); }
    .wf-day__info { flex: 1; min-width: 0; }
    .wf-day__title {
        font-size: 15px; font-weight: 700; color: #2D1810;
        margin: 0; line-height: 1.2;
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .wf-day__title .badge-today {
        font-size: 10px; font-weight: 700;
        background: #1F6E5F; color: #FFFFFF;
        padding: 2px 8px; border-radius: 999px;
        text-transform: uppercase; letter-spacing: 0.4px;
    }
    .wf-day__meta { font-size: 12px; color: #6B4423; margin-top: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .wf-day__chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 9px; border-radius: 999px;
        font-size: 11px; font-weight: 700;
    }
    .wf-day__chip--good { background: rgba(31,110,95,.12); color: #1F6E5F; }
    .wf-day__chip--warn { background: rgba(166,96,25,.12); color: #A66019; }
    .wf-day__chip--neutral { background: rgba(155,133,120,.12); color: #6B4423; }

    .wf-day__body { background: #FFFFFF; }
    .wf-day__empty {
        padding: 24px 18px; text-align: center;
        font-size: 12.5px; color: #9B8578; font-style: italic;
    }

    .wf-task {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 13px 18px;
        border-bottom: 1px solid #F5EBE0;
        cursor: pointer;
        transition: background .15s;
    }
    .wf-task:last-child { border-bottom: none; }
    .wf-task:hover { background: #FAF4EC; }
    .wf-task.is-locked { cursor: not-allowed; opacity: .65; }
    .wf-task__check {
        width: 24px; height: 24px;
        border-radius: 8px;
        border: 2px solid #C9B69A;
        background: #FFFFFF;
        display: flex; align-items: center; justify-content: center;
        color: #FFFFFF;
        font-size: 13px;
        font-weight: 700;
        flex-shrink: 0;
        margin-top: 1px;
        transition: all .15s;
    }
    .wf-task.is-done .wf-task__check {
        background: linear-gradient(135deg, #5C2E15, #471500);
        border-color: #471500;
        box-shadow: 0 2px 6px rgba(71,21,0,.25);
    }
    .wf-task.is-done .wf-task__check::before { content: "✓"; }
    .wf-task__body { flex: 1; min-width: 0; }
    .wf-task__name { font-size: 13.5px; color: #2D1810; line-height: 1.5; }
    .wf-task.is-done .wf-task__name { color: #9B8578; text-decoration: line-through; text-decoration-thickness: 1px; }
    .wf-task__cat {
        font-size: 10.5px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.4px; color: #9B5E2C; margin-bottom: 3px;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .wf-task__time { font-size: 11px; color: #9B8578; flex-shrink: 0; margin-top: 4px; }

    /* Category divider within day */
    .wf-cat-divider {
        padding: 8px 18px 4px;
        font-size: 10.5px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: #9B5E2C;
        background: #FAFAF8;
        border-bottom: 1px solid #F0E8DC;
        display: flex; align-items: center; gap: 6px;
    }
    .wf-cat-divider i { font-size: 9px; }

    /* Submit footer — brown wrapper */
    .wf-submit {
        margin-top: 16px;
        background: linear-gradient(135deg, #5C2E15, #471500);
        color: #FFFFFF;
        border-radius: 14px;
        padding: 16px 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        box-shadow: 0 6px 20px rgba(71,21,0,.20);
    }
    .wf-submit__hint {
        font-size: 12.5px;
        color: rgba(255,255,255,.88);
        line-height: 1.4;
        flex: 1;
        min-width: 200px;
    }
    .wf-submit__hint strong { color: #FFFFFF; }
    .wf-submit__btn {
        border: 0;
        outline: 0;
        background: #FFFFFF;
        color: #471500;
        padding: 10px 22px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: transform .15s, box-shadow .15s;
        box-shadow: 0 4px 12px rgba(0,0,0,.12);
    }
    .wf-submit__btn:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(0,0,0,.18); }
    .wf-submit__btn:focus { outline: 0; }
    .wf-submit__btn:disabled {
        background: rgba(255,255,255,.30);
        color: rgba(255,255,255,.55);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    /* ── Wajib foto (kamera) ── */
    .wf-task__badge {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;
        color: #A66019; background: rgba(166,96,25,.12);
        padding: 2px 8px; border-radius: 999px; margin-left: 6px; vertical-align: middle;
    }
    .wf-task.needs-photo .wf-task__check { border-style: dashed; border-color: #A66019; }
    .wf-photo { margin-top: 10px; cursor: default; }
    .wf-photo__actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .wf-photo__view {
        border: 1px solid #E0D2C1; background: #fff; color: #5C2E15;
        font-size: 12px; font-weight: 700; padding: 7px 14px; border-radius: 999px;
        cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: .15s;
    }
    .wf-photo__view:hover { background: #FAF0E2; border-color: #A66019; }

    /* Modal lihat foto */
    .wf-photo-modal { position: fixed; inset: 0; z-index: 1080; display: flex; align-items: center; justify-content: center; }
    .wf-photo-modal[hidden] { display: none; }
    .wf-photo-modal__backdrop { position: absolute; inset: 0; background: rgba(30,15,5,.72); }
    .wf-photo-modal__box { position: relative; z-index: 1; max-width: 92vw; max-height: 88vh; }
    .wf-photo-modal__box img { max-width: 92vw; max-height: 88vh; border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,.4); display: block; }
    .wf-photo-modal__close {
        position: absolute; top: -14px; right: -14px; width: 36px; height: 36px;
        border-radius: 50%; border: none; background: #fff; color: #5C2E15;
        font-size: 15px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,.25);
        display: flex; align-items: center; justify-content: center;
    }
    .wf-photo__btn {
        border: 1px solid #A66019; background: #FFF7EE; color: #5C2E15;
        font-size: 12px; font-weight: 700; padding: 7px 14px; border-radius: 999px;
        cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: .15s;
    }
    .wf-photo__btn:hover { background: #FAF0E2; }
    .wf-photo__remove {
        border: 1px solid #E0D2C1; background: #fff; color: #7C2929;
        font-size: 11.5px; font-weight: 600; padding: 6px 12px; border-radius: 999px;
        cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: .15s;
    }
    .wf-photo__remove:hover { background: #FCEBEB; border-color: #A32D2D; }
    .wf-photo__status { font-size: 11px; color: #6B4423; font-weight: 600; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <h4 class="page-title">Form Mingguan</h4>
                <a href="{{ route('mychecklist') }}" class="st-btn-outline-brown">
                    <i class="fas fa-arrow-left"></i> Kembali ke Hari Ini
                </a>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="wf-wrap">
        @if(! $position)
            <div class="st-card">
                <div class="st-card__body" style="padding:48px 22px; text-align:center;">
                    <div style="display:inline-flex; align-items:center; justify-content:center; width:80px; height:80px; border-radius:50%; background:#FAF4EC; color:#9B5E2C; font-size:32px; margin-bottom:18px;">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <h5 style="font-size:18px; font-weight:700; color:#2D1810; margin-bottom:8px;">Posisi belum diatur</h5>
                    <p style="font-size:13.5px; color:#6B4423; max-width:420px; margin:0 auto; line-height:1.6;">
                        Anda belum di-assign ke divisi mana pun. Hubungi supervisor untuk daftarkan posisi.
                    </p>
                </div>
            </div>
        @else
            {{-- VIEW TOGGLE (consistent dgn index daily) --}}
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                <div style="display:inline-flex; gap:2px; padding:4px; background:#FFFFFF; border-radius:999px; border:1px solid #E0D2C1; box-shadow:0 1px 3px rgba(45,24,16,.06);">
                    <a href="{{ route('mychecklist') }}"
                       style="padding:8px 18px; font-size:13px; font-weight:600; text-decoration:none; border-radius:999px; color:#6B4423; display:inline-flex; align-items:center; gap:7px;">
                        <i class="fas fa-sun"></i> Checklist Daily
                    </a>
                    <a href="{{ route('mychecklist.weekform') }}"
                       style="padding:8px 18px; font-size:13px; font-weight:700; text-decoration:none; border-radius:999px; background:linear-gradient(180deg,#5C2E15,#471500); color:#FFFFFF; box-shadow:0 2px 6px rgba(71,21,0,.20); display:inline-flex; align-items:center; gap:7px;">
                        <i class="fas fa-calendar-week"></i> Checklist Weekly
                    </a>
                </div>
                <a href="{{ route('mychecklist.history', ['type' => 'weekly']) }}" style="font-size:12.5px; color:#6B4423; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-weight:600;">
                    <i class="fas fa-history"></i> Riwayat
                </a>
            </div>

            {{-- HERO --}}
            @php
                $statusOpt = $submission
                    ? (\App\Models\ChecklistWeeklySubmission::statusOptions()[$submission->status] ?? null)
                    : null;
                $heroStatusColor = $statusOpt
                    ? (['success'=>'success','warning'=>'warning','danger'=>'danger','neutral'=>'warning','info'=>'warning'][$statusOpt['color']] ?? 'warning')
                    : 'warning';
            @endphp
            <div class="wf-hero">
                <div class="wf-hero__row">
                    <div style="min-width:0;">
                        <h5 class="wf-hero__title">
                            Form Mingguan
                            <small>Daftar tugas per hari dalam satu minggu kerja</small>
                        </h5>
                        <div class="wf-hero__meta" style="margin-top:6px;">
                            <span class="wf-hero__chip"><i class="fas fa-id-badge" style="font-size:10px;"></i> {{ $position->division?->name ?? $position->division_id }}</span>
                            @if($position->branch)
                                <span class="wf-hero__chip"><i class="fas fa-building" style="font-size:10px;"></i> {{ $position->branch->name }}</span>
                            @endif
                        </div>
                    </div>
                    @if($submission && $submission->status !== \App\Models\ChecklistWeeklySubmission::STATUS_DRAFT && $statusOpt)
                        <span class="wf-hero__status wf-hero__status--{{ $heroStatusColor }}">
                            @if($submission->status === 'approved')<i class="fas fa-check-circle"></i>
                            @elseif($submission->status === 'rejected')<i class="fas fa-times-circle"></i>
                            @else<i class="fas fa-clock"></i>
                            @endif
                            {{ $statusOpt['label'] }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- WEEK NAVIGATION --}}
            <div class="wf-nav">
                <a href="{{ route('mychecklist.weekform', ['week' => $weekOffset - 1]) }}" class="wf-nav__btn" title="Minggu sebelumnya">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <div style="text-align:center;">
                    <h5 class="wf-nav__title">
                        <i class="fas fa-calendar-week"></i>
                        Minggu {{ $weekStart->translatedFormat('d M') }} – {{ $weekEnd->translatedFormat('d M Y') }}
                    </h5>
                    <div style="font-size:11px; color:#9B8578; margin-top:2px;">
                        @if($weekOffset === 0)
                            Minggu Ini
                        @elseif($weekOffset === -1)
                            Minggu Lalu
                        @elseif($weekOffset === 1)
                            Minggu Depan
                        @elseif($weekOffset < 0)
                            {{ abs($weekOffset) }} minggu lalu
                        @else
                            {{ $weekOffset }} minggu lagi
                        @endif
                    </div>
                </div>
                <a href="{{ route('mychecklist.weekform', ['week' => $weekOffset + 1]) }}" class="wf-nav__btn" title="Minggu berikutnya">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>

            @if($isFuture)
                <div class="wf-banner wf-banner--info">
                    <i class="fas fa-info-circle"></i>
                    <div><strong>Minggu di masa depan.</strong> Form belum dibuat — kembali ke minggu ini untuk mulai mengisi.</div>
                </div>
            @endif

            {{-- BANNERS --}}
            @if($submission && $submission->status === \App\Models\ChecklistWeeklySubmission::STATUS_REJECTED)
                <div class="wf-banner wf-banner--danger">
                    <i class="fas fa-times-circle"></i>
                    <div style="flex:1;">
                        <strong>Form Mingguan ditolak supervisor.</strong>
                        @if($rejectedNote)<div style="margin-top:4px;">Catatan: {{ $rejectedNote }}</div>@endif
                        <form method="POST" action="{{ route('mychecklist.weekform.reopen-self') }}" style="margin-top:10px;">
                            @csrf
                            <button type="submit" class="st-btn-brand" style="font-size:12.5px; padding:6px 14px;">
                                <i class="fas fa-pen"></i> Perbaiki Form
                            </button>
                        </form>
                    </div>
                </div>
            @elseif($submission && $submission->status === \App\Models\ChecklistWeeklySubmission::STATUS_SUBMITTED)
                <div class="wf-banner wf-banner--warning">
                    <i class="fas fa-clock"></i>
                    <div><strong>Menunggu approval supervisor.</strong> Anda akan diberitahu via notifikasi saat sudah direview.</div>
                </div>
            @elseif($submission && $submission->status === \App\Models\ChecklistWeeklySubmission::STATUS_APPROVED)
                <div class="wf-banner wf-banner--success">
                    <i class="fas fa-check-circle"></i>
                    <div><strong>Form Mingguan minggu ini disetujui supervisor.</strong> Terima kasih atas konsistensinya!</div>
                </div>
            @elseif($submission && $submission->status === \App\Models\ChecklistWeeklySubmission::STATUS_EXCUSED)
                <div class="wf-banner wf-banner--warning">
                    <i class="fas fa-mug-hot"></i>
                    <div style="flex:1;">
                        <strong>Form Mingguan ini ditandai "Dikecualikan" — tidak perlu diisi.</strong>
                        <div style="margin-top:4px;">Alasan: {{ $submission->excuse_reason }}</div>
                        @if($weekOffset === 0)
                            <form method="POST" action="{{ route('mychecklist.weekform.unexcuse') }}" style="margin-top:10px;"
                                  onsubmit="return confirm('Batalkan pengecualian dan isi Form Mingguan seperti biasa?');">
                                @csrf
                                <button type="submit" class="st-btn-brand" style="font-size:12.5px; padding:6px 14px;">
                                    <i class="fas fa-undo"></i> Batalkan, isi form
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            {{-- PROGRESS --}}
            @if($submission && $progress['total'] > 0)
                <div class="wf-progress">
                    <div class="wf-progress__big" id="wfPct">{{ $progress['pct'] }}<small>%</small></div>
                    <div class="wf-progress__body">
                        <div class="wf-progress__label">Progres Minggu Ini</div>
                        <div class="wf-progress__count" id="wfCount">
                            <strong>{{ $progress['done'] }}</strong> dari {{ $progress['total'] }} tugas
                            <small>· {{ max(0, $progress['total'] - $progress['done']) }} belum dikerjakan</small>
                        </div>
                        <div class="wf-progress__bar"><i id="wfFill" style="width:{{ $progress['pct'] }}%"></i></div>
                    </div>
                </div>
            @endif

            {{-- DAY-BY-DAY LIST --}}
            @if(! $submission || $progress['total'] === 0)
                <div style="background:#FFFFFF; border:1px solid #E0D2C1; border-radius:14px; padding:42px 22px; text-align:center;">
                    <div style="display:inline-flex; align-items:center; justify-content:center; width:70px; height:70px; border-radius:50%; background:#FAF4EC; color:#9B5E2C; font-size:26px; margin-bottom:14px;">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <h5 style="font-size:16px; font-weight:700; color:#2D1810; margin-bottom:6px;">
                        Belum ada task mingguan
                    </h5>
                    <p style="font-size:13px; color:#6B4423; max-width:480px; margin:0 auto; line-height:1.55;">
                        Supervisor belum menambahkan kategori <strong>Mingguan</strong> untuk divisi <strong>{{ $position->division?->name }}</strong>.
                        Form akan otomatis aktif setelah katalog mingguan diisi di menu Setup.
                    </p>
                </div>
            @else
                <div class="wf-day-list">
                    @foreach($days as $day)
                        @php
                            $dayItems = $grouped->get($day->iso, collect());
                            $totalDay = $dayItems->flatten(1)->count();
                            $doneDay  = $dayItems->flatten(1)->where('is_done', true)->count();
                            $isComplete = $totalDay > 0 && $doneDay === $totalDay;
                            $dayClass = '';
                            if ($day->is_today) $dayClass .= ' is-today';
                            elseif ($day->is_past) $dayClass .= ' is-past';
                            if ($totalDay === 0) $dayClass .= ' is-empty';
                        @endphp
                        <div class="wf-day {{ $dayClass }}">
                            <div class="wf-day__head">
                                <div class="wf-day__num">
                                    <strong>{{ $day->date->format('d') }}</strong>
                                    <small>{{ $day->short }}</small>
                                </div>
                                <div class="wf-day__info">
                                    <h6 class="wf-day__title">
                                        {{ $day->label }}
                                        @if($day->is_today)
                                            <span class="badge-today">Hari Ini</span>
                                        @endif
                                    </h6>
                                    <div class="wf-day__meta">
                                        <span>{{ $day->date->translatedFormat('d F Y') }}</span>
                                        @if($totalDay > 0)
                                            <span class="wf-day__chip wf-day__chip--{{ $isComplete ? 'good' : 'warn' }}">
                                                @if($isComplete)<i class="fas fa-check" style="font-size:9px;"></i>@endif
                                                {{ $doneDay }}/{{ $totalDay }} tugas
                                            </span>
                                        @else
                                            <span class="wf-day__chip wf-day__chip--neutral">Tidak ada task</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="wf-day__body">
                                @if($totalDay === 0)
                                    <div class="wf-day__empty">Tidak ada tugas terjadwal di hari ini.</div>
                                @else
                                    @foreach($dayItems as $catTitle => $items)
                                        <div class="wf-cat-divider">
                                            <i class="fas fa-folder"></i> {{ $catTitle }}
                                        </div>
                                        @foreach($items as $item)
                                            <div class="wf-task {{ $item->is_done ? 'is-done' : '' }} {{ $submission->isEditable() ? '' : 'is-locked' }} {{ $item->requires_photo && ! $item->photo_path ? 'needs-photo' : '' }}"
                                                 data-item-id="{{ $item->id }}"
                                                 data-toggle-url="{{ route('mychecklist.weekform.item.toggle', $item->id) }}"
                                                 data-editable="{{ $submission->isEditable() ? '1' : '0' }}"
                                                 data-requires-photo="{{ $item->requires_photo ? '1' : '0' }}"
                                                 @if($item->requires_photo)
                                                 data-photo-upload-url="{{ route('mychecklist.weekform.item.photo.upload', $item->id) }}"
                                                 data-photo-delete-url="{{ route('mychecklist.weekform.item.photo.delete', $item->id) }}"
                                                 data-has-photo="{{ $item->photo_path ? '1' : '0' }}"
                                                 @endif>
                                                <span class="wf-task__check"></span>
                                                <div class="wf-task__body">
                                                    <div class="wf-task__name">
                                                        {{ $item->task_title_snapshot }}
                                                        @if($item->requires_photo)
                                                            <span class="wf-task__badge"><i class="fas fa-camera"></i> Wajib foto</span>
                                                        @endif
                                                    </div>
                                                    @if($item->requires_photo)
                                                        <div class="wf-photo" data-photo>
                                                            <input type="file" accept="image/*" capture="environment" class="wf-photo__file" hidden>
                                                            <div class="wf-photo__actions">
                                                                <button type="button" class="wf-photo__view" data-photo-view
                                                                        data-url="{{ $item->photo_path ? asset($item->photo_path) : '' }}"
                                                                        style="{{ $item->photo_path ? '' : 'display:none;' }}">
                                                                    <i class="fas fa-image"></i> Lihat foto
                                                                </button>
                                                                @if($submission->isEditable())
                                                                    <button type="button" class="wf-photo__btn" data-photo-take>
                                                                        <i class="fas fa-camera"></i> <span data-photo-label>{{ $item->photo_path ? 'Foto ulang' : 'Ambil foto' }}</span>
                                                                    </button>
                                                                    <button type="button" class="wf-photo__remove" data-photo-remove style="{{ $item->photo_path ? '' : 'display:none;' }}">
                                                                        <i class="fas fa-trash"></i> Hapus
                                                                    </button>
                                                                    <span class="wf-photo__status" data-photo-status></span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                @if($item->done_at)
                                                    <div class="wf-task__time">{{ $item->done_at->format('d/m H:i') }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- SUBMIT FOOTER --}}
                @if($submission->isEditable() && $weekOffset === 0)
                    <form id="wfSubmitForm" method="POST" action="{{ route('mychecklist.weekform.submit') }}"
                          style="display:none;"
                          onsubmit="return confirm('Submit Form Mingguan minggu ini? Setelah submit, tidak bisa edit sampai supervisor review.');">
                        @csrf
                    </form>
                    <div class="wf-submit">
                        <div class="wf-submit__hint" id="wfHint">
                            @if($progress['done'] === 0)
                                Mulai centang task yang sudah selesai.
                            @elseif($progress['done'] < $progress['total'])
                                Masih ada <strong>{{ $progress['total'] - $progress['done'] }} task</strong> belum dicentang. Submit setelah seluruh minggu selesai.
                            @else
                                <strong>Semua task minggu ini selesai!</strong> Klik submit untuk kirim ke supervisor.
                            @endif
                        </div>
                        <button type="submit" form="wfSubmitForm" class="wf-submit__btn" id="wfSubmitBtn" {{ $progress['done'] === 0 ? 'disabled' : '' }}>
                            @if($progress['done'] === 0)
                                Isi minimal 1 task
                            @else
                                <i class="fas fa-paper-plane"></i> Submit Mingguan
                            @endif
                        </button>
                    </div>

                    {{-- ─── Aksi: minggu ini tidak perlu diisi (Dikecualikan) ─── --}}
                    <details class="cl-excuse">
                        <summary class="cl-excuse__trigger">
                            <span class="cl-excuse__trigger-label"><i class="fas fa-mug-hot"></i> Minggu ini tidak perlu diisi?</span>
                            <i class="fas fa-chevron-down cl-excuse__caret"></i>
                        </summary>
                        <form method="POST" action="{{ route('mychecklist.weekform.excuse') }}" class="cl-excuse__form"
                              onsubmit="return confirm('Tandai Form Mingguan ini sebagai tidak perlu diisi?');">
                            @csrf
                            <div class="cl-excuse__label">Pilih / tulis alasan (wajib):</div>
                            <div class="cl-excuse__chips">
                                <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Cuti';">Cuti</button>
                                <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Klinik tutup';">Klinik tutup</button>
                                <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Libur';">Libur</button>
                                <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Tidak ada pasien';">Tidak ada pasien</button>
                            </div>
                            <textarea name="excuse_reason" class="cl-excuse__input" rows="2" maxlength="500" required
                                      placeholder="Contoh: libur / tanggal merah…"></textarea>
                            <button type="submit" class="cl-excuse__submit"><i class="fas fa-check"></i> Tandai "Dikecualikan"</button>
                        </form>
                    </details>
                    <style>
                        .cl-excuse { margin-top: 14px; border: 1px solid #E0D2C1; border-radius: 12px; background: #FFFDF9; box-shadow: 0 1px 3px rgba(45,24,16,.05); }
                        .cl-excuse__trigger { list-style: none; cursor: pointer; padding: 13px 16px; font-size: 13px; font-weight: 700; color: #6B4423; display: flex; align-items: center; justify-content: space-between; gap: 8px; border-radius: 12px; transition: background .15s; }
                        .cl-excuse__trigger:hover { background: #FAF0E2; }
                        .cl-excuse__trigger::-webkit-details-marker { display: none; }
                        .cl-excuse__trigger-label { display: inline-flex; align-items: center; gap: 8px; }
                        .cl-excuse__trigger i { color: #A66019; }
                        .cl-excuse__caret { font-size: 12px; transition: transform .2s ease; }
                        .cl-excuse[open] .cl-excuse__trigger { border-radius: 12px 12px 0 0; border-bottom: 1px solid #EFE1CF; background: #FAF0E2; }
                        .cl-excuse[open] .cl-excuse__caret { transform: rotate(180deg); }
                        .cl-excuse__form { padding: 12px 16px 16px; }
                        .cl-excuse__label { font-size: 12px; font-weight: 700; color: #6B4423; margin-bottom: 8px; }
                        .cl-excuse__chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
                        .cl-excuse__chip { font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 999px; border: 1px solid #E0D2C1; background: #fff; color: #6B4423; cursor: pointer; transition: .15s; }
                        .cl-excuse__chip:hover { border-color: #A66019; color: #5C2E15; background: #FAF4EC; }
                        .cl-excuse__input { width: 100%; box-sizing: border-box; border: 1px solid #E0D2C1; border-radius: 8px; padding: 8px 10px; font-size: 13px; color: #2D1810; font-family: inherit; resize: vertical; }
                        .cl-excuse__input:focus { outline: none; border-color: #A66019; box-shadow: 0 0 0 3px rgba(166,96,25,.10); }
                        .cl-excuse__submit { margin-top: 10px; border: none; border-radius: 8px; background: linear-gradient(180deg,#5C2E15,#471500); color: #fff; font-size: 13px; font-weight: 700; padding: 9px 16px; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; }
                        .cl-excuse__submit:hover { filter: brightness(1.08); }
                    </style>
                @elseif($weekOffset !== 0 && $submission->isEditable())
                    <div class="wf-banner wf-banner--info" style="margin-top:14px;">
                        <i class="fas fa-info-circle"></i>
                        <div>Submit hanya bisa dilakukan dari <strong>minggu ini</strong>. Kembali ke minggu aktif untuk submit.</div>
                    </div>
                @endif
            @endif
        @endif
    </div>
</div>

{{-- Modal lihat foto bukti (dipakai bersama semua task) --}}
<div class="wf-photo-modal" id="wfPhotoModal" hidden>
    <div class="wf-photo-modal__backdrop" data-modal-close></div>
    <div class="wf-photo-modal__box">
        <button type="button" class="wf-photo-modal__close" data-modal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        <img src="" alt="Foto bukti" id="wfPhotoModalImg">
    </div>
</div>

@if($submission && $progress['total'] > 0)
<script>
(function() {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content;
    const pctEl   = document.getElementById('wfPct');
    const fillEl  = document.getElementById('wfFill');
    const countEl = document.getElementById('wfCount');
    const hintEl  = document.getElementById('wfHint');
    const submitBtn = document.getElementById('wfSubmitBtn');

    document.querySelectorAll('[data-item-id]').forEach(task => {
        // Klik area task (check/judul) → toggle. Kalau wajib foto & belum ada
        // foto, klik malah membuka kamera dulu (tidak bisa selesai tanpa foto).
        task.addEventListener('click', () => {
            if (task.dataset.editable !== '1') return;
            const requiresPhoto = task.dataset.requiresPhoto === '1';
            const hasPhoto = task.dataset.hasPhoto === '1';
            const isDone = task.classList.contains('is-done');
            if (requiresPhoto && ! hasPhoto && ! isDone) {
                const input = task.querySelector('.wf-photo__file');
                if (input) input.click();
                return;
            }
            toggleTask(task);
        });

        // Kontrol foto tidak boleh ikut men-toggle task (stop bubbling).
        const photoBox = task.querySelector('[data-photo]');
        if (photoBox) {
            photoBox.addEventListener('click', e => e.stopPropagation());
            const fileInput = task.querySelector('.wf-photo__file');
            const takeBtn   = task.querySelector('[data-photo-take]');
            const removeBtn = task.querySelector('[data-photo-remove]');
            const viewBtn   = task.querySelector('[data-photo-view]');
            if (takeBtn && fileInput) takeBtn.addEventListener('click', () => fileInput.click());
            if (fileInput) fileInput.addEventListener('change', () => {
                if (fileInput.files && fileInput.files[0]) uploadPhoto(task, fileInput.files[0]);
                fileInput.value = '';
            });
            if (removeBtn) removeBtn.addEventListener('click', () => removePhoto(task));
            if (viewBtn) viewBtn.addEventListener('click', () => { if (viewBtn.dataset.url) openPhotoModal(viewBtn.dataset.url); });
        }
    });

    // ── Modal lihat foto ──
    function openPhotoModal(url) {
        const modal = document.getElementById('wfPhotoModal');
        const img   = document.getElementById('wfPhotoModalImg');
        if (! modal || ! img) return;
        img.src = url;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }
    function closePhotoModal() {
        const modal = document.getElementById('wfPhotoModal');
        const img   = document.getElementById('wfPhotoModalImg');
        if (! modal) return;
        modal.hidden = true;
        if (img) img.src = '';
        document.body.style.overflow = '';
    }
    document.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closePhotoModal));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closePhotoModal(); });

    async function toggleTask(task) {
        const url = task.dataset.toggleUrl;
        const wasDone = task.classList.contains('is-done');
        task.classList.toggle('is-done');
        updateDayCounter(task);
        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
            });
            const data = await res.json();
            if (! data.ok) throw new Error(data.error || 'Gagal toggle');
            task.classList.toggle('is-done', data.is_done);
            updateGlobal(data.progress);
        } catch (err) {
            task.classList.toggle('is-done', wasDone);
            updateDayCounter(task);
            alert('Gagal: ' + (err.message || err));
        }
    }

    // Kompres/resize gambar di browser sebelum upload (hemat kuota & storage).
    function resizeImage(file, maxDim, quality) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onerror = () => reject(new Error('Gagal membaca file'));
            reader.onload = e => {
                const img = new Image();
                img.onerror = () => reject(new Error('File bukan gambar valid'));
                img.onload = () => {
                    let w = img.width, h = img.height;
                    if (w > maxDim || h > maxDim) {
                        if (w >= h) { h = Math.round(h * maxDim / w); w = maxDim; }
                        else { w = Math.round(w * maxDim / h); h = maxDim; }
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = w; canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    canvas.toBlob(b => b ? resolve(b) : reject(new Error('Kompres gagal')), 'image/jpeg', quality);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    async function uploadPhoto(task, file) {
        const status = task.querySelector('[data-photo-status]');
        const setStatus = t => { if (status) status.textContent = t; };
        setStatus('Mengompres…');
        let blob;
        try { blob = await resizeImage(file, 1600, 0.8); }
        catch (e) { blob = file; } // fallback: kirim asli kalau resize gagal
        const fd = new FormData();
        fd.append('photo', blob, 'foto.jpg');
        setStatus('Mengunggah…');
        try {
            const res = await fetch(task.dataset.photoUploadUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: fd,
            });
            const data = await res.json();
            if (! data.ok) throw new Error(data.error || 'Upload gagal');
            setPhoto(task, data.url);
            setStatus('Foto tersimpan ✓');
            setTimeout(() => setStatus(''), 1500);
            // Foto = bukti selesai → otomatis tandai selesai kalau belum.
            if (! task.classList.contains('is-done')) toggleTask(task);
        } catch (err) {
            setStatus('');
            alert('Gagal: ' + (err.message || err));
        }
    }

    async function removePhoto(task) {
        if (! confirm('Hapus foto ini? Task akan kembali "belum selesai".')) return;
        try {
            const res = await fetch(task.dataset.photoDeleteUrl, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
            });
            const data = await res.json();
            if (! data.ok) throw new Error(data.error || 'Gagal hapus');
            clearPhoto(task);
            task.classList.remove('is-done'); // server auto-uncheck task wajib foto
            updateDayCounter(task);
            updateGlobal(data.progress);
        } catch (err) {
            alert('Gagal: ' + (err.message || err));
        }
    }

    function setPhoto(task, url) {
        task.dataset.hasPhoto = '1';
        task.classList.remove('needs-photo');
        const viewBtn   = task.querySelector('[data-photo-view]');
        const label     = task.querySelector('[data-photo-label]');
        const removeBtn = task.querySelector('[data-photo-remove]');
        if (viewBtn) { viewBtn.dataset.url = url; viewBtn.style.display = ''; }
        if (label) label.textContent = 'Foto ulang';
        if (removeBtn) removeBtn.style.display = '';
    }

    function clearPhoto(task) {
        task.dataset.hasPhoto = '0';
        task.classList.add('needs-photo');
        const viewBtn   = task.querySelector('[data-photo-view]');
        const label     = task.querySelector('[data-photo-label]');
        const removeBtn = task.querySelector('[data-photo-remove]');
        if (viewBtn) { viewBtn.dataset.url = ''; viewBtn.style.display = 'none'; }
        if (label) label.textContent = 'Ambil foto';
        if (removeBtn) removeBtn.style.display = 'none';
    }

    function updateDayCounter(task) {
        const dayCard = task.closest('.wf-day');
        if (! dayCard) return;
        const all  = dayCard.querySelectorAll('[data-item-id]');
        const done = dayCard.querySelectorAll('[data-item-id].is-done');
        const total = all.length;
        const cnt = done.length;
        const complete = cnt === total && total > 0;
        const chip = dayCard.querySelector('.wf-day__chip');
        if (chip) {
            chip.innerHTML = (complete ? '<i class="fas fa-check" style="font-size:9px;"></i> ' : '') + cnt + '/' + total + ' tugas';
            chip.classList.toggle('wf-day__chip--good', complete);
            chip.classList.toggle('wf-day__chip--warn', ! complete);
        }
    }

    function updateGlobal(p) {
        if (! p) return;
        if (pctEl)   pctEl.innerHTML   = p.pct + '<small>%</small>';
        if (fillEl)  fillEl.style.width = p.pct + '%';
        if (countEl) {
            const remaining = Math.max(0, p.total - p.done);
            countEl.innerHTML = '<strong>' + p.done + '</strong> dari ' + p.total + ' tugas <small>· ' + remaining + ' belum dikerjakan</small>';
        }
        if (submitBtn) {
            const has = p.done > 0;
            submitBtn.disabled = ! has;
            submitBtn.innerHTML = has
                ? '<i class="fas fa-paper-plane"></i> Submit Mingguan'
                : 'Isi minimal 1 task';
        }
        if (hintEl) {
            if (p.done === 0) {
                hintEl.innerHTML = 'Mulai centang task yang sudah selesai.';
            } else if (p.done < p.total) {
                hintEl.innerHTML = 'Masih ada <strong>' + (p.total - p.done) + ' task</strong> belum dicentang. Submit setelah seluruh minggu selesai.';
            } else {
                hintEl.innerHTML = '<strong>Semua task minggu ini selesai!</strong> Klik submit untuk kirim ke supervisor.';
            }
        }
    }
})();
</script>
@endif
@endsection
