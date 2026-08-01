@extends('layouts.app')

@section('css')
<style>
    /* ──────── Weekly grid (calendar-style checklist) ──────── */
    .cl-week-wrap { max-width: 1280px; margin: 0 auto; }

    @media (max-width: 768px) { .cl-view-toggle { display: none !important; } }
    /* Sub-toggle tampilan (Hari Ini / Rekap 6 Hari) — dengan hover */
    /* Segmented Hari Ini | Rekap 6 Hari — kotak putih, aktif = gradient coklat gelap (handoff) */
    .cl-vtoggle { display: inline-flex; gap: 4px; padding: 4px; background: #FFFFFF; border-radius: 12px; border: 1px solid #EADCC9; }
    .cl-vtoggle__btn {
        padding: 8px 14px; font-size: 12px; font-weight: 700; text-decoration: none;
        border-radius: 9px; color: #9A7256; display: inline-flex; align-items: center; gap: 6px;
        transition: background .15s, color .15s;
    }
    .cl-vtoggle__btn:hover { color: #5C2E15; }
    .cl-vtoggle__btn.is-active { background: linear-gradient(135deg, #6B2400, #461500); color: #FFEAD6; font-weight: 800; }
    .cl-vtoggle__btn.is-active:hover { background: linear-gradient(135deg, #6B2400, #461500); color: #FFEAD6; }

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
    /* Grup kanan sebaris dgn kartu: segmented Hari Ini|Rekap + tombol Riwayat (handoff) */
    .cl-cad-tabs__right { margin-left: auto; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .cl-btn-riwayat {
        display: inline-flex; align-items: center; gap: 7px;
        background: #FFFFFF; color: #5A3A22; border: 1px solid #E7D3BE;
        border-radius: 11px; padding: 10px 16px; font-weight: 700; font-size: 12.5px;
        text-decoration: none; transition: filter .15s;
    }
    .cl-btn-riwayat:hover { filter: brightness(.97); color: #5A3A22; text-decoration: none; }
    /* Mobile: kartu jenis full-width; segmented Hari Ini|Rekap disembunyikan */
    @media (max-width: 600px) {
        .cl-cad-tab { flex: 1 1 100%; min-width: 0; }
        .cl-cad-tabs__right { margin-left: 0; width: 100%; }
        .cl-vtoggle { display: none; }
        .cl-btn-riwayat { flex: 1; justify-content: center; }
    }

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
        border: 1px solid #EFE0CE;
        border-radius: 16px;
        padding: 14px 20px;
        margin-bottom: 14px;
        box-shadow: 0 1px 2px rgba(70,21,0,.04);
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 16px;
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
        font-size: 30px; font-weight: 800;
        color: #2D1810; line-height: 1;
        letter-spacing: -0.03em;
        display: flex; align-items: baseline;
    }
    .cl-mod-week-progress__pct small { font-size: 16px; color: inherit; opacity: .6; font-weight: 700; }
    .cl-mod-week-progress.is-good .cl-mod-week-progress__pct { color: #2F8E55; }
    .cl-mod-week-progress.is-warn .cl-mod-week-progress__pct { color: #C9962F; }
    .cl-mod-week-progress.is-bad  .cl-mod-week-progress__pct { color: #C0392B; }
    .cl-mod-week-progress__body { min-width: 0; }
    .cl-mod-week-progress__label {
        font-size: 10.5px; font-weight: 800; text-transform: uppercase;
        letter-spacing: 0.6px; color: #9A7256; margin-bottom: 6px;
    }
    .cl-mod-week-progress__bar {
        background: #F1E6D7; height: 9px; border-radius: 8px; overflow: hidden;
    }
    .cl-mod-week-progress__bar > i {
        display: block; height: 100%;
        background: #C0392B;
        border-radius: 8px;
        transition: width .4s cubic-bezier(.45,.05,.55,.95), background .2s;
    }
    .cl-mod-week-progress.is-good .cl-mod-week-progress__bar > i { background: #2F8E55; }
    .cl-mod-week-progress.is-warn .cl-mod-week-progress__bar > i { background: #C9962F; }
    .cl-mod-week-progress.is-bad  .cl-mod-week-progress__bar > i { background: #C0392B; }
    .cl-mod-week-progress__count {
        font-size: 13px; color: #5A3A22; font-weight: 700; white-space: nowrap;
        text-align: right; align-self: center;
    }
    .cl-mod-week-progress__count strong { color: #2D1810; font-size: 14px; }
    @media (max-width: 540px) {
        .cl-mod-week-progress {
            grid-template-columns: 1fr; padding: 16px 18px; gap: 12px; text-align: center;
        }
        .cl-mod-week-progress__count { text-align: center; }
    }

    /* Modern week nav + grid card */
    .cl-mod-week-card {
        background: #FFFFFF;
        border: 1px solid #EFE0CE;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(70,21,0,.04);
    }
    .cl-mod-week-nav {
        display: flex; justify-content: space-between; align-items: center;
        padding: 14px 18px;
        border-bottom: 1px solid #F1E6D7;
        background: #FFFFFF;
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

    /* Tabel rekap gaya handoff: tanpa garis vertikal, baris dipisah garis tipis */
    .cl-week-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
        background: #FFFFFF;
    }
    .cl-week-table thead th {
        background: #FFFFFF;
        color: #5A3A22;
        font-weight: 700;
        font-size: 11px;
        padding: 10px 8px;
        text-align: center;
        border: none;
        border-bottom: 1px solid #F1E6D7;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .cl-week-table thead th.task-col {
        text-align: left;
        padding: 13px 18px;
        background: #FFFFFF;
        width: 38%;
        min-width: 240px;
        vertical-align: bottom;
        font-size: 11px; font-weight: 800; letter-spacing: .5px;
        text-transform: uppercase; color: #9A7256;
    }
    .cl-week-table thead th.day-col {
        width: 88px;
        min-width: 72px;
        color: #5A3A22;
    }
    .cl-week-table thead th.day-col.today {
        background: linear-gradient(135deg, #A8481B, #5C2E15);
        color: #FBEADB;
        position: relative;
    }
    /* Chip "HARI INI" di DALAM kolom hari (ikut handoff), bukan menggantung di bawah */
    .cl-week-table thead th.day-col.today::before {
        content: "HARI INI";
        display: block;
        width: max-content;
        margin: 0 auto 3px;
        background: rgba(255,255,255,.22);
        color: #FBEADB;
        border-radius: 6px;
        padding: 2px 7px;
        font-size: 8.5px;
        letter-spacing: 0.8px;
        font-weight: 800;
        white-space: nowrap;
    }
    .cl-week-table thead th .day-num {
        display: block;
        font-size: 19px;
        font-weight: 800;
        margin-top: 2px;
    }

    .cl-week-table tbody td {
        border: none;
        border-bottom: 1px solid #F6EEE1;
        padding: 6px;
        background: #FFFFFF;
        vertical-align: middle;
    }
    .cl-week-table tbody td.task-col {
        font-size: 12.5px;
        color: #5A3A22;
        padding: 10px 18px;
        font-weight: 600;
    }

    /* Category header row — bg krem, chip jumlah nempel judul, caret di kanan (handoff) */
    .cl-week-table tbody tr.cat-row td {
        background: #FBF6EE;
        font-weight: 800;
        color: #3B1500;
        font-size: 13px;
        padding: 11px 18px;
        border-bottom: 1px solid #F1E6D7;
    }
    .cl-week-table tbody tr.cat-row .cat-inner {
        display: flex;
        align-items: center;
        gap: 11px;
    }
    .cl-week-table tbody tr.cat-row .cat-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px; height: 24px;
        background: #F0E2CF;
        color: #8A3A12;
        border-radius: 7px;
        font-size: 11.5px;
        font-weight: 800;
        flex-shrink: 0;
    }
    .cl-week-table tbody tr.cat-row .cat-meta {
        font-size: 11px;
        color: #9A7256;
        font-weight: 700;
        background: #F1E6D7;
        padding: 2px 9px;
        border-radius: 20px;
        border: none;
    }
    /* Accordion per kategori */
    .cl-week-table tbody tr.cat-row { cursor: pointer; user-select: none; }
    .cl-week-table tbody tr.cat-row:hover td { background: #F6EEDF; }
    .cl-week-table tbody tr.cat-row .cat-caret {
        margin-left: auto;
        font-size: 11px;
        color: #9A7256;
        transition: transform .2s ease;
    }
    .cl-week-table tbody tr.cat-row.is-collapsed .cat-caret { transform: rotate(-90deg); }
    .cl-week-table tbody tr.cl-task-row.is-hidden { display: none; }
    /* Buka kategori terasa halus: baris muncul fade + slide (stagger di-set dari JS) */
    .cl-week-table tbody tr.cl-task-row:not(.is-hidden) { animation: wkRowIn .24s ease both; }
    @keyframes wkRowIn {
        from { opacity: 0; transform: translateY(-5px); }
        to   { opacity: 1; transform: none; }
    }

    /* Day cells — kotak 34px rounded DI DALAM sel (handoff):
       selesai = hijau + centang · hari ini kosong = putih dashed "+" · hari lain = flat krem */
    .cl-week-cell {
        text-align: center;
        line-height: 1;
    }
    .cl-week-cell::before {
        content: "";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px; height: 34px;
        box-sizing: border-box;
        border-radius: 10px;
        background: #F4EDE2;
        font-size: 15px;
        font-weight: 800;
        transition: background .15s, filter .15s;
    }
    .cl-week-cell.is-done::before {
        content: "✓";
        color: #FFFFFF;
        background: #57A876;
    }
    .cl-week-cell.is-today { cursor: pointer; }
    .cl-week-cell.is-today:not(.is-done)::before {
        content: "+";
        background: #FFFFFF;
        border: 2px dashed #DCC4A8;
        color: #C7AC92;
        font-size: 16px;
    }
    .cl-week-cell.is-today:not(.is-done):hover::before { background: #FBF6EE; }
    .cl-week-cell.is-today.is-done:hover::before { background: #4E9A6C; }
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
        .cl-week-cell::before { width: 30px; height: 30px; font-size: 13px; }
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
        @include('crud.checklist.my._marquee')

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
            {{-- ─── PILIH JENIS CHECKLIST + segmented Hari Ini|Rekap + Riwayat — SATU baris (handoff).
                 Rekap = sub-view Checklist Harian → kartu Harian tetap aktif. ─── --}}
            <div class="cl-cad-tabs" style="margin-bottom:14px;">
                <a href="{{ route('mychecklist') }}" class="cl-cad-tab cl-cad-tab--daily is-active">
                    <span class="cl-cad-tab__icon"><i class="fas fa-sun"></i></span>
                    <span class="cl-cad-tab__body">
                        <strong>Checklist Harian</strong>
                        <small>Isi setiap hari operasional</small>
                    </span>
                    <i class="fas fa-check-circle cl-cad-tab__check"></i>
                </a>
                <a href="{{ route('mychecklist.weekform') }}" class="cl-cad-tab cl-cad-tab--weekly">
                    <span class="cl-cad-tab__icon"><i class="fas fa-calendar-week"></i></span>
                    <span class="cl-cad-tab__body">
                        <strong>Checklist Mingguan</strong>
                        <small>Tugas terjadwal per minggu</small>
                    </span>
                    <i class="fas fa-check-circle cl-cad-tab__check"></i>
                </a>
                <div class="cl-cad-tabs__right">
                    <div class="cl-vtoggle">
                        <a href="{{ route('mychecklist') }}" class="cl-vtoggle__btn">Hari Ini</a>
                        <a href="{{ route('mychecklist.weekly') }}" class="cl-vtoggle__btn is-active">Rekap 6 Hari</a>
                    </div>
                    @php
                        $acPos = currentUserPosition();
                        $acOverdue = acOverdueCountForCurrentGs();
                    @endphp
                     @if($acPos && $acPos->division_id === 'GS')
                        <a href="{{ route('mychecklist.ac') }}" class="cl-btn-riwayat ac-nav-btn">
                            <i class="fas fa-snowflake"></i> Cuci AC
                             @if($acOverdue > 0)
                                <span class="ac-nav-badge">{{ $acOverdue }}</span>
                             @endif
                        </a>
                     @endif
                    <a href="{{ route('mychecklist.history') }}" class="cl-btn-riwayat">
                        <i class="fas fa-history"></i> Riwayat Checklist
                    </a>
                </div>
            </div>

            {{-- ─── MODERN HERO (gradient brown) ─── --}}
            <div class="cl-mod-hero">
                <div class="cl-mod-hero__row">
                    <div class="cl-mod-hero__left">
                        <div class="cl-mod-hero__icon-box">
                            <i class="fas fa-th"></i>
                        </div>
                        <div style="min-width:0;">
                            <h5 class="cl-mod-hero__greet">Rekap 6 Hari</h5>
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
                    <div><strong>Checklist hari ini disetujui supervisor.</strong> Terima kasih atas konsistensinya! <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="vertical-align:-.15em"><path d="M8 1.8 9.3 5.6 13 7 9.3 8.4 8 12.2 6.7 8.4 3 7l3.7-1.4L8 1.8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M12.8 11.4v2.8M11.4 12.8h2.8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div>
                </div>
            @endif

            {{-- REKAP 6 HARI (agregat) — metrik UTAMA. Statis: ikut nilai saat halaman
                 dimuat (toggle hari ini meng-update strip "Progres Hari Ini" di bawah). --}}
            @php $wkAggClass = $weekProgress['pct'] >= 80 ? 'is-good' : ($weekProgress['pct'] >= 50 ? 'is-warn' : 'is-bad'); @endphp
            <div class="cl-mod-week-progress {{ $wkAggClass }}" style="margin-bottom:10px;">
                <div class="cl-mod-week-progress__pct">{{ $weekProgress['pct'] }}<small>%</small></div>
                <div class="cl-mod-week-progress__body">
                    <div class="cl-mod-week-progress__label">Rekap Minggu Ini (6 hari, tanpa hari libur)</div>
                    <div class="cl-mod-week-progress__bar"><i style="width:{{ $weekProgress['pct'] }}%"></i></div>
                </div>
                <div class="cl-mod-week-progress__count">
                    <strong>{{ $weekProgress['done'] }}</strong> / {{ $weekProgress['total'] }} tugas
                </div>
            </div>

            {{-- PROGRESS STRIP (compact) — HARI INI (di-update live saat toggle) --}}
            @php $wkPctClass = $progress['pct'] >= 80 ? 'is-good' : ($progress['pct'] >= 50 ? 'is-warn' : 'is-bad'); @endphp
            <div class="cl-mod-week-progress {{ $wkPctClass }}" id="clWeekProgressCard">
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
                        <div class="cl-empty__desc">Supervisor belum mengatur tugas untuk divisi {{ $position->division?->name }}.</div>
                    </div>
                @else
                    <style>
                        /* Dot status submission per hari (di header kolom). */
                        .cl-week-table thead th .day-dot {
                            display: block; width: 7px; height: 7px; border-radius: 50%;
                            margin: 4px auto 0;
                        }
                        .day-dot--approved  { background: #57A876; }
                        .day-dot--submitted { background: #E8A44D; }
                        .day-dot--rejected  { background: #D96C60; }
                        .day-dot--draft     { background: #B7926F; }
                        .day-dot--excused   { background: #8A5A2B; }
                        /* Kolom hari yang dikecualikan — tint coffee lembut. */
                        .cl-week-table thead th.day-col--excused,
                        .cl-week-table tfoot td.day-col--excused { background: #F3E7D6; }
                        /* Baris kaki "Terisi" — subtotal per hari. */
                        .cl-week-table tfoot td {
                            text-align: center; font-size: 11.5px; font-weight: 800;
                            color: #8A6A4A; padding: 9px 4px;
                            border-top: 2px solid #EFE3D2; background: #FBF6EE;
                        }
                        .cl-week-table tfoot td.task-col {
                            text-align: left; color: #9A7256; font-size: 10.5px;
                            font-weight: 800; text-transform: uppercase; letter-spacing: .5px;
                        }
                        .cl-week-table tfoot td.today { color: #6B2400; }
                        .wk-foot-muted { color: #C9B79E; font-weight: 700; }
                        .wk-foot-ex    { color: #8A5A2B; font-weight: 800; }
                    </style>
                    <div style="overflow-x:auto;">
                        <table class="cl-week-table">
                            <thead>
                                <tr>
                                    <th class="task-col">Tugas</th>
                                    @foreach($days as $d)
                                        <th class="day-col {{ $d->is_today ? 'today' : '' }} {{ $d->is_excused ? 'day-col--excused' : '' }}">
                                            {{ $d->day_short }}
                                            <span class="day-num">{{ $d->day_num }}</span>
                                            @if($d->status)
                                                <span class="day-dot day-dot--{{ $d->status }}"
                                                      title="{{ $d->is_excused ? 'Dikecualikan' : ($d->sub_pct !== null ? $d->sub_done . '/' . $d->sub_total . ' (' . $d->sub_pct . '%)' : '') }}"></span>
                                            @endif
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
                            <tfoot>
                                <tr class="cl-week-foot-row">
                                    <td class="task-col">Terisi</td>
                                    @foreach($days as $d)
                                        <td class="day-col {{ $d->is_today ? 'today' : '' }} {{ $d->is_excused ? 'day-col--excused' : '' }}">
                                            @if($d->is_excused)
                                                <span class="wk-foot-ex">Libur</span>
                                            @elseif($d->is_future)
                                                <span class="wk-foot-muted">—</span>
                                            @elseif($d->sub_total)
                                                {{ $d->sub_done }}/{{ $d->sub_total }}
                                            @else
                                                <span class="wk-foot-muted">0/0</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif

            </div>{{-- /cl-mod-week-card --}}

            {{-- SUBMIT FOOTER — baris polos gaya handoff: teks kiri + tombol kecil kanan --}}
            @if($submission->isEditable() && $rows->isNotEmpty())
                @php $wkComplete = $progress['total'] > 0 && $progress['done'] === $progress['total']; @endphp
                <form method="POST" action="{{ route('mychecklist.submit') }}" style="margin-top:14px;"
                      data-confirm="Submit checklist hari ini?">
                    @csrf
                    <div class="cl-week-footer">
                        <div class="cl-week-footer__text" id="clWeekFootText">
                            @if($wkComplete)
                                Semua tugas hari ini selesai.
                            @else
                                Masih ada {{ $progress['total'] - $progress['done'] }} task belum dicentang.
                            @endif
                        </div>
                        <button type="submit" class="cl-week-footer__btn" id="clWeekSubmitBtn" {{ $wkComplete ? '' : 'disabled' }}>
                            Kirim Checklist
                        </button>
                    </div>
                </form>
                <style>
                    .cl-week-footer { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
                    .cl-week-footer__text { font-size: 12.5px; font-weight: 600; color: #9A7256; }
                    .cl-week-footer__btn {
                        margin-left: auto; display: inline-flex; align-items: center; gap: 8px;
                        background: linear-gradient(135deg, #6B2400, #461500); color: #FFEAD6;
                        border: none; border-radius: 11px; padding: 11px 20px;
                        font-weight: 800; font-size: 13px; cursor: pointer; font-family: inherit;
                        box-shadow: 0 4px 12px rgba(70,21,0,.2);
                    }
                    .cl-week-footer__btn:hover { filter: brightness(1.08); }
                    .cl-week-footer__btn:disabled {
                        background: #EFE3D2; color: #B7926F; cursor: not-allowed;
                        box-shadow: none; filter: none;
                    }
                </style>
            @endif
        @endif
    </div>
</div>

@if($position && $rows->isNotEmpty())
<script>
(function() {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content;
    // Scope ke strip "Progres Hari Ini" (bukan strip "Rekap Minggu" yang statis di atasnya).
    const progressCard = document.getElementById('clWeekProgressCard');
    const pctEl   = progressCard?.querySelector('.cl-mod-week-progress__pct');
    const fillEl  = progressCard?.querySelector('.cl-mod-week-progress__bar i');
    const countEl = progressCard?.querySelector('.cl-mod-week-progress__count');
    function applyProgressColor(pct) {
        if (! progressCard) return;
        const cls = pct >= 80 ? 'is-good' : (pct >= 50 ? 'is-warn' : 'is-bad');
        progressCard.classList.remove('is-good', 'is-warn', 'is-bad');
        progressCard.classList.add(cls);
    }

    // Accordion eksklusif: klik header kategori → buka baris task-nya & tutup kategori lain.
    document.querySelectorAll('tr.cat-row[data-cat-toggle]').forEach(catRow => {
        catRow.addEventListener('click', () => {
            const cat = catRow.dataset.catToggle;
            const willOpen = catRow.classList.contains('is-collapsed');
            // Tutup semua dulu.
            document.querySelectorAll('tr.cat-row[data-cat-toggle]').forEach(cr => cr.classList.add('is-collapsed'));
            document.querySelectorAll('tr.cl-task-row').forEach(r => r.classList.add('is-hidden'));
            // Buka yang diklik (kalau tadinya tertutup) — stagger kecil biar halus.
            if (willOpen) {
                catRow.classList.remove('is-collapsed');
                document.querySelectorAll('tr.cl-task-row[data-cat="' + cat + '"]').forEach((r, i) => {
                    r.style.animationDelay = (i * 30) + 'ms';
                    r.classList.remove('is-hidden');
                });
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
                    applyProgressColor(data.progress.pct);

                    // Footer submit: teks + tombol ikut progress (submit hanya saat 100%).
                    const complete = data.progress.total > 0 && data.progress.done === data.progress.total;
                    const footText = document.getElementById('clWeekFootText');
                    const footBtn  = document.getElementById('clWeekSubmitBtn');
                    if (footText) footText.textContent = complete
                        ? 'Semua tugas hari ini selesai.'
                        : 'Masih ada ' + (data.progress.total - data.progress.done) + ' task belum dicentang.';
                    if (footBtn) footBtn.disabled = ! complete;
                }
            } catch (err) {
                cell.classList.toggle('is-done', wasDone);
                alert(navigator.onLine
                    ? ('Gagal menyimpan: ' + (err.message || err) + '\nBila status sudah berubah (mis. sudah direview), muat ulang halaman.')
                    : 'Koneksi terputus — perubahan belum tersimpan. Coba lagi setelah kembali online.');
            }
        });
    });
})();
</script>
@endif

@include('crud.checklist.my._confirm_modal')
@endsection
