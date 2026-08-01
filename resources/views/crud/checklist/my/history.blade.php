@extends('layouts.app')

@section('css')
<style>
    .cl-hist-wrap { max-width: 1100px; margin: 0 auto; }
    .cl-hist-grid { display: grid; grid-template-columns: 1fr 300px; gap: 16px; align-items: start; }
    @media (max-width: 992px) { .cl-hist-grid { grid-template-columns: 1fr; } }

    /* Ringkasan status — chip inline di dalam toolbar (ganti 5 kotak KPI biar
       tidak terlalu banyak box). Angka tebal menonjol, label muted. */
    .cl-hist-bar__stats {
        display: flex; align-items: center; flex-wrap: wrap;
        gap: 9px 22px;
        padding: 11px 18px;
        border-top: 1px solid #F0E6D8;
        background: #FDFBF7;
    }
    .cl-hist-stat {
        display: inline-flex; align-items: baseline; gap: 6px;
        font-size: 12.5px; color: #8A7563; font-weight: 600;
    }
    .cl-hist-stat i { font-size: 12px; position: relative; top: 1px; }
    .cl-hist-stat b { font-size: 15px; font-weight: 800; color: #2D1810; letter-spacing: -0.01em; }

    /* Toolbar wrapper: navigasi bulan + ringkasan status jadi SATU kartu
       (sebelumnya 2 blok terpisah: month-nav + 5 kotak KPI). */
    .cl-hist-bar {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 14px;
        margin-bottom: 14px;
        box-shadow: 0 2px 6px rgba(45,24,16,.04);
        overflow: hidden;
    }
    .cl-hist-bar__nav {
        display: flex; justify-content: space-between; align-items: center;
        gap: 10px; padding: 12px 16px;
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

    /* Submission cards — gaya handoff: kartu putih flat, tanggal + sub relatif kiri,
       bar + label progres tengah, pill status + chevron kanan */
    .cl-hist-list { display: flex; flex-direction: column; gap: 10px; }
    .cl-hist-card {
        background: #FFFFFF;
        border: 1px solid #EFE0CE;
        border-radius: 14px;
        box-shadow: 0 1px 2px rgba(70,21,0,.04);
        overflow: hidden;
        transition: box-shadow .2s;
    }
    .cl-hist-card:hover { box-shadow: 0 8px 20px rgba(45,24,16,.10); }
    .cl-hist-card__head {
        display: flex; align-items: center; gap: 14px;
        padding: 13px 16px;
        cursor: pointer; user-select: none;
        background: #FFFFFF;
        transition: background .15s;
    }
    .cl-hist-card__head:hover { background: #FCF7F0; }
    .cl-hist-card__date {
        width: 120px; min-width: 0;
        flex-shrink: 0;
    }
    .cl-hist-card__date strong {
        display: block;
        font-size: 13px; font-weight: 800; color: #3B1500;
        line-height: 1.2;
    }
    .cl-hist-card__date small {
        display: block;
        font-size: 11px; color: #9A7256; font-weight: 500; margin-top: 2px;
    }
    .cl-hist-card__body { flex: 1; min-width: 90px; }
    .cl-hist-card__bar {
        background: #F1E6D7; height: 8px; border-radius: 6px; overflow: hidden;
    }
    .cl-hist-card__bar > i {
        display: block; height: 100%;
        border-radius: 6px;
    }
    .cl-hist-card__prog {
        display: block; font-size: 11px; color: #9A7256; font-weight: 600; margin-top: 4px;
    }
    .cl-hist-card__pill {
        flex-shrink: 0; font-size: 11px; font-weight: 800;
        border-radius: 20px; padding: 5px 11px; white-space: nowrap;
    }
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

    /* Mini calendar — gaya handoff: sel flat radius 9, hari ini ber-ring coklat,
       mendatang dashed, legend horizontal + kotak "Cara pakai" krem */
    .cl-hist-cal {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 5px;
    }
    .cl-hist-cal__title {
        font-size: 11px; font-weight: 800; letter-spacing: .5px;
        text-transform: uppercase; color: #9A7256; margin-bottom: 11px;
    }
    .cl-hist-cal__dow {
        font-size: 10px; font-weight: 800; color: #B7926F;
        text-align: center; padding: 2px 0;
    }
    /* Gaya ubin mengikuti heatmap "Riwayat 14 Hari": ada border, sudut membulat,
       hover naik, warna status solid (tegas). */
    .cl-hist-cal__cell {
        aspect-ratio: 1;
        background: #FAFAF8;
        border: 1px solid #E0D2C1;
        border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 800;
        color: #9B8578;
        cursor: default;
        transition: transform .1s;
    }
    .cl-hist-cal__cell:not(.is-blank):hover { transform: translateY(-1px); z-index: 1; }
    .cl-hist-cal__cell.is-blank { background: transparent; border-color: transparent; }
    .cl-hist-cal__cell.is-future { background: transparent; color: #D3BFA6; border: 1.5px dashed #EADCC9; }
    .cl-hist-cal__cell--approved  { background: #57A876; color: #FFFFFF; border-color: #57A876; }
    .cl-hist-cal__cell--submitted { background: #E8A44D; color: #FFFFFF; border-color: #E8A44D; }
    .cl-hist-cal__cell--rejected  { background: #D96C60; color: #FFFFFF; border-color: #D96C60; }
    .cl-hist-cal__cell--draft     { background: #DCCDB4; color: #6B4423; border-color: #DCCDB4; }
    .cl-hist-cal__cell--excused   { background: #B5824A; color: #FFFFFF; border-color: #B5824A; }
    .cl-hist-cal__cell--missed    { background: #E4D7C6; color: #9B8578; border-color: #E4D7C6; }
    /* Hari ini: outline coklat ala heatmap (di atas warna status). */
    .cl-hist-cal__cell.is-today { outline: 2px solid #8A3A12; outline-offset: -2px; }
    .cl-hist-cal__legend {
        margin-top: 13px;
        display: flex; flex-wrap: wrap; gap: 9px;
        font-size: 10.5px; font-weight: 600; color: #9A7256;
    }
    .cl-hist-cal__legend span { display: inline-flex; align-items: center; gap: 5px; }
    .cl-hist-cal__legend i {
        width: 9px; height: 9px; border-radius: 3px;
        flex-shrink: 0; box-shadow: inset 0 0 0 1px rgba(0,0,0,.07);
    }
    .cl-hist-cal__hint {
        margin-top: 13px; background: #FBF6EE; border-radius: 11px;
        padding: 10px 13px; font-size: 11.5px; color: #9A7256;
        font-weight: 500; line-height: 1.5;
    }
    .cl-hist-cal__hint b { color: #5A3A22; }

    /* Sidebar mini cards — gaya handoff */
    .cl-hist-side {
        background: #FFFFFF;
        border: 1px solid #EFE0CE;
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 14px;
        box-shadow: 0 1px 2px rgba(70,21,0,.04);
    }
    .cl-hist-side h6 {
        font-size: 11.5px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.4px; color: #9B8578; margin: 0 0 12px;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .cl-hist-side h6 i { color: #5C2E15; font-size: 11px; }

    /* Kartu besar pilih jenis checklist (gaya handoff: aktif = putih + ring warna) */
    .cl-cad-tabs { display: flex; gap: 12px; flex-wrap: wrap; align-items: stretch; }
    .cl-cad-tab {
        flex: 0 1 auto; min-width: 250px; display: flex; align-items: center; gap: 13px;
        padding: 13px 16px; background: #FBF6EE; border: 1px solid #EADCC9; border-radius: 16px;
        cursor: pointer; text-align: left; text-decoration: none;
        transition: box-shadow .16s ease, border-color .16s ease, background .16s ease;
    }
    .cl-cad-tab:hover { border-color: #C9B69A; box-shadow: 0 4px 12px rgba(45,24,16,.08); }
    .cl-cad-tab__icon {
        width: 40px; height: 40px; border-radius: 12px; display: inline-flex;
        align-items: center; justify-content: center; font-size: 16px; color: #FFFFFF; flex-shrink: 0;
    }
    .cl-cad-tab--daily  .cl-cad-tab__icon { background: linear-gradient(135deg, #A8481B, #5C2E15); box-shadow: 0 4px 12px rgba(92,46,21,.25); }
    .cl-cad-tab--weekly .cl-cad-tab__icon { background: linear-gradient(135deg, #1F6E5F, #155148); box-shadow: 0 4px 12px rgba(21,81,72,.25); }
    .cl-cad-tab__body { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 2px; }
    .cl-cad-tab__body strong { font-size: 14px; font-weight: 800; color: #3B1500; line-height: 1.2; }
    .cl-cad-tab__body small { font-size: 11.5px; font-weight: 500; color: #9A7256; }
    .cl-cad-tab__check { opacity: 0; flex-shrink: 0; font-size: 22px; transition: opacity .16s ease; }
    .cl-cad-tab--daily  .cl-cad-tab__check { color: #8A3A12; }
    .cl-cad-tab--weekly .cl-cad-tab__check { color: #1F6E5F; }
    .cl-cad-tab.is-active { background: #FFFFFF; }
    .cl-cad-tab--daily.is-active  { border: 2px solid #A8481B; padding: 12px 15px; box-shadow: 0 4px 14px rgba(138,58,18,.14); }
    .cl-cad-tab--weekly.is-active { border: 2px solid #1F6E5F; padding: 12px 15px; box-shadow: 0 4px 14px rgba(31,110,95,.14); }
    .cl-cad-tab.is-active .cl-cad-tab__check { opacity: 1; }
    /* Mobile: kartu jenis full-width */
    @media (max-width: 600px) {
        .cl-cad-tab { flex: 1 1 100%; min-width: 0; }
    }
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

        @include('crud.checklist.my._marquee')

        {{-- ─── TAB: Daily / Weekly (kartu besar, gaya my-checklist) ─── --}}
        <div class="cl-cad-tabs" style="margin-bottom:14px;">
            <a href="{{ route('mychecklist.history', ['type' => 'daily', 'month' => $month]) }}"
               class="cl-cad-tab cl-cad-tab--daily {{ $type === 'daily' ? 'is-active' : '' }}">
                <span class="cl-cad-tab__icon"><i class="fas fa-sun"></i></span>
                <span class="cl-cad-tab__body">
                    <strong>Checklist Harian</strong>
                    <small>Riwayat pengisian per hari</small>
                </span>
                <i class="fas fa-check-circle cl-cad-tab__check"></i>
            </a>
            <a href="{{ route('mychecklist.history', ['type' => 'weekly', 'month' => $month]) }}"
               class="cl-cad-tab cl-cad-tab--weekly {{ $type === 'weekly' ? 'is-active' : '' }}">
                <span class="cl-cad-tab__icon"><i class="fas fa-calendar-week"></i></span>
                <span class="cl-cad-tab__body">
                    <strong>Checklist Mingguan</strong>
                    <small>Riwayat form mingguan</small>
                </span>
                <i class="fas fa-check-circle cl-cad-tab__check"></i>
            </a>
        </div>

        {{-- ─── TOOLBAR: navigasi bulan + ringkasan status (1 wrapper, ganti
             month-nav + 5 kotak KPI; Avg Progress dihapus). ─── --}}
        <div class="cl-hist-bar">
            <div class="cl-hist-bar__nav">
                <a href="{{ route('mychecklist.history', array_filter(['type' => $type, 'month' => $prevMonth, 'status' => $status])) }}" class="cl-hist-navbtn" title="Bulan sebelumnya">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <h5 class="cl-hist-header__title">
                    <i class="fas fa-calendar"></i>
                    {{ $monthLabel }}
                </h5>
                <a href="{{ route('mychecklist.history', array_filter(['type' => $type, 'month' => $nextMonth, 'status' => $status])) }}" class="cl-hist-navbtn" title="Bulan berikutnya">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <div class="cl-hist-bar__stats">
                <span class="cl-hist-stat"><i class="fas fa-clipboard-list" style="color:#5C2E15;"></i> <b>{{ $stats['total'] }}</b> {{ $type === 'weekly' ? 'minggu' : 'hari' }} terisi</span>
                <span class="cl-hist-stat"><i class="fas fa-check-circle" style="color:#1F6E5F;"></i> <b>{{ $stats['approved'] }}</b> Disetujui</span>
                <span class="cl-hist-stat"><i class="fas fa-clock" style="color:#A66019;"></i> <b>{{ $stats['pending'] }}</b> Menunggu</span>
                <span class="cl-hist-stat"><i class="fas fa-times-circle" style="color:#A32D2D;"></i> <b>{{ $stats['rejected'] }}</b> Ditolak</span>
            </div>
        </div>

        {{-- ─── FILTER STATUS (mempersempit daftar; ringkasan bulan tetap utuh) ─── --}}
        @php
            $filterOpts = [
                ''          => 'Semua',
                'approved'  => 'Disetujui',
                'submitted' => 'Menunggu',
                'rejected'  => 'Ditolak',
                'draft'     => 'Belum Dikirim',
                'excused'   => 'Dikecualikan',
                'missed'    => 'Tidak Diisi',
            ];
        @endphp
        <style>
            .cl-hist-filter { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 14px; }
            .cl-hist-fpill {
                text-decoration: none; font-size: 12px; font-weight: 700;
                padding: 6px 13px; border-radius: 999px;
                background: #FFFFFF; color: #8A6A4A; border: 1px solid #E6D8C6;
            }
            .cl-hist-fpill:hover { background: #FAF4EC; }
            .cl-hist-fpill.is-active { background: #6B2400; color: #FFEAD6; border-color: #6B2400; }
            /* Mobile: pill diganti dropdown biar tidak berdesakan. */
            .cl-hist-filter-select { display: none; }
            @media (max-width: 640px) {
                .cl-hist-filter { display: none; }
                .cl-hist-filter-select {
                    display: block; width: 100%; margin-bottom: 14px;
                    padding: 10px 13px; border-radius: 11px;
                    border: 1px solid #E6D8C6; background: #FFFFFF;
                    font-size: 13px; font-weight: 700; color: #6B2400;
                    font-family: inherit; cursor: pointer;
                }
            }
        </style>
        <select class="cl-hist-filter-select" aria-label="Filter status" onchange="location.href=this.value">
            @foreach($filterOpts as $val => $label)
                <option value="{{ route('mychecklist.history', array_filter(['type' => $type, 'month' => $month, 'status' => $val])) }}"
                    {{ ($status ?? '') === $val ? 'selected' : '' }}>{{ $label === 'Semua' ? 'Semua status' : $label }}</option>
            @endforeach
        </select>
        <div class="cl-hist-filter">
            @foreach($filterOpts as $val => $label)
                <a href="{{ route('mychecklist.history', array_filter(['type' => $type, 'month' => $month, 'status' => $val])) }}"
                   class="cl-hist-fpill {{ ($status ?? '') === $val ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
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
                            @if($status)
                                Tidak ada pengisian berstatus "{{ $filterOpts[$status] ?? $status }}" di {{ $monthLabel }}
                            @else
                                Tidak ada pengisian di {{ $monthLabel }}
                            @endif
                        </h5>
                        <p style="font-size:13px; color:#6B4423; max-width:360px; margin:0 auto;">
                            @if($status)
                                Coba ubah filter status atau pilih bulan lain.
                            @else
                                Coba pilih bulan lain atau mulai isi {{ $type === 'weekly' ? 'form mingguan' : 'checklist hari ini' }}.
                            @endif
                        </p>
                    </div>
                @else
                    <div class="cl-hist-list">
                        @foreach($submissions as $sub)
                            @php
                                $statusClass = $type === 'weekly' ? \App\Models\ChecklistWeeklySubmission::class : \App\Models\ChecklistSubmission::class;
                                $opt = $statusClass::statusOptions()[$sub->status] ?? ['label'=>$sub->status,'color'=>'neutral'];
                                $pct = $sub->progressPct();
                                $barColor = $pct >= 80 ? '#2F8E55' : ($pct >= 50 ? '#C9962F' : '#C0392B');
                                if ($type === 'weekly') {
                                    $weekStart = $sub->week_start_date;
                                    $weekEnd   = $weekStart->copy()->addDays(6);
                                    $dateLabel = $weekStart->translatedFormat('d M') . ' – ' . $weekEnd->translatedFormat('d M');
                                    // Sub relatif ala handoff: minggu ini / mingguan.
                                    $subLabel = \Carbon\Carbon::today()->between($weekStart, $weekEnd) ? 'minggu ini' : 'mingguan';
                                } else {
                                    $dateLabel = $sub->submission_date->translatedFormat('D, d M');
                                    // Sub relatif ala handoff: hari ini / kemarin / kemarin lusa / harian.
                                    $dayDiff  = $sub->submission_date->copy()->startOfDay()->diffInDays(\Carbon\Carbon::today());
                                    $subLabel = $sub->submission_date->isToday() ? 'hari ini'
                                        : ($sub->submission_date->isYesterday() ? 'kemarin'
                                        : ($dayDiff === 2 ? 'kemarin lusa' : 'harian'));
                                }
                            @endphp
                            <a class="cl-hist-card" href="{{ route('mychecklist.history.show', ['type' => $type, 'id' => $sub->id, 'month' => request('month')]) }}"
                               style="text-decoration:none; color:inherit; display:block;">
                                <div class="cl-hist-card__head">
                                    <div class="cl-hist-card__date">
                                        <strong>{{ $dateLabel }}</strong>
                                        <small>{{ $subLabel }}</small>
                                    </div>
                                    <div class="cl-hist-card__body">
                                        <div class="cl-hist-card__bar"><i style="width:{{ $pct }}%; background:{{ $barColor }};"></i></div>
                                        <span class="cl-hist-card__prog">{{ $sub->done_tasks }}/{{ $sub->total_tasks }} tugas ({{ $pct }}%)</span>
                                    </div>
                                    <span class="st-pill st-pill--{{ $opt['color'] }} cl-hist-card__pill">{{ $opt['label'] }}</span>
                                    <i class="fas fa-chevron-right cl-hist-card__chev" title="Lihat detail"></i>
                                </div>
                            </a>
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
                        <div class="cl-hist-cal__title">Kalender {{ $monthLabel }}</div>
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
                                            'draft'     => 'Belum Dikirim',
                                            'excused'   => 'Dikecualikan',
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
                            <span><i style="background:#57A876;"></i> Disetujui</span>
                            <span><i style="background:#E8A44D;"></i> Menunggu</span>
                            <span><i style="background:#D96C60;"></i> Ditolak</span>
                            <span><i style="background:#DCCDB4;"></i> Belum Dikirim</span>
                            <span><i style="background:#B5824A;"></i> Dikecualikan</span>
                            <span><i style="background:#E4D7C6;"></i> Tidak diisi</span>
                        </div>
                        <div class="cl-hist-cal__hint">
                            <b>Cara pakai:</b> klik kartu di daftar untuk lihat detail tugas. Arahkan kursor ke tanggal untuk status singkat.
                        </div>
                    </div>
                @else
                    <div class="cl-hist-side">
                        <div class="cl-hist-cal__title">Cara Pakai</div>
                        <div class="cl-hist-cal__hint" style="margin-top:0;">
                            <b>Tab Mingguan:</b> menampilkan form mingguan yang sudah di-submit per minggu. Klik kartu untuk lihat detail tugas yang dicentang di setiap hari.
                        </div>
                    </div>
                @endif
            </aside>

        </div>{{-- /grid --}}
    </div>
</div>
@endsection
