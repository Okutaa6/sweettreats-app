@extends('layouts.app')

@section('css')
<style>
    /* ──────── Mobile-first interactive checklist ──────── */
    .cl-my-wrap { max-width: 1100px; margin: 0 auto; }
    .cl-my-grid { display: grid; grid-template-columns: 1fr 320px; gap: 16px; align-items: start; }
    @media (max-width: 992px) { .cl-my-grid { grid-template-columns: 1fr; } }

    /* ──────── MODERN Hari Ini design ──────── */

    /* Hero gradient header */
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
        content: "";
        position: absolute;
        border-radius: 50%;
        pointer-events: none;
    }
    .cl-mod-hero::before {
        top: -50px; right: -50px;
        width: 180px; height: 180px;
        background: rgba(255,255,255,.06);
    }
    .cl-mod-hero::after {
        bottom: -60px; right: 90px;
        width: 110px; height: 110px;
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
    .cl-mod-hero__avatar {
        width: 52px; height: 52px;
        border-radius: 50%;
        background: rgba(255,255,255,.16);
        color: #FFFFFF;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 19px;
        border: 1.5px solid rgba(255,255,255,.30);
        flex-shrink: 0;
        backdrop-filter: blur(8px);
    }
    .cl-mod-hero__greet {
        font-size: 20px; font-weight: 700; color: #FFFFFF;
        margin: 0 0 4px; line-height: 1.15;
        letter-spacing: -0.01em;
    }
    .cl-mod-hero__greet small {
        font-size: 14px; font-weight: 500; opacity: .85;
    }
    .cl-mod-hero__meta {
        font-size: 12.5px; color: rgba(255,255,255,.85);
        display: flex; align-items: center; gap: 8px;
        flex-wrap: wrap;
    }
    .cl-mod-hero__chip {
        background: rgba(255,255,255,.14);
        padding: 4px 11px; border-radius: 999px;
        border: 1px solid rgba(255,255,255,.22);
        font-size: 11.5px; font-weight: 600;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .cl-mod-hero__status {
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        backdrop-filter: blur(8px);
        display: inline-flex; align-items: center; gap: 6px;
    }
    .cl-mod-hero__status--success { background: rgba(225,245,238,.95); color: #0F6E56; border: 1px solid rgba(31,110,95,.30); }
    .cl-mod-hero__status--warning { background: rgba(250,238,218,.95); color: #5C2E15; border: 1px solid rgba(166,96,25,.30); }
    .cl-mod-hero__status--danger  { background: rgba(252,235,235,.95); color: #7C2929; border: 1px solid rgba(163,45,45,.30); }

    /* Modern progress card */
    .cl-mod-progress {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 16px;
        padding: 22px 24px;
        margin-bottom: 14px;
        box-shadow: 0 2px 8px rgba(45,24,16,.04);
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 24px;
        align-items: center;
        position: relative;
        overflow: hidden;
    }
    .cl-mod-progress::after {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(155,94,44,.06), transparent 50%);
        pointer-events: none;
    }
    .cl-mod-progress__big {
        position: relative;
        font-size: 52px; font-weight: 800;
        color: #2D1810; line-height: 1;
        letter-spacing: -0.04em;
        display: flex; align-items: baseline; gap: 2px;
    }
    .cl-mod-progress__big small {
        font-size: 20px; color: #9B5E2C; font-weight: 700;
    }
    .cl-mod-progress__body { min-width: 0; position: relative; }
    .cl-mod-progress__label {
        font-size: 11.5px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: #9B8578;
        margin-bottom: 6px;
    }
    .cl-mod-progress__count {
        font-size: 14.5px; color: #2D1810; font-weight: 700; margin-bottom: 12px;
    }
    .cl-mod-progress__count small {
        color: #6B4423; font-weight: 500; font-size: 12.5px;
    }
    .cl-mod-progress__bar {
        background: #F5F0EA; height: 10px; border-radius: 5px; overflow: hidden;
    }
    .cl-mod-progress__bar > i {
        display: block; height: 100%;
        background: linear-gradient(90deg, #9B5E2C, #5C2E15);
        border-radius: 5px;
        transition: width .4s cubic-bezier(.45,.05,.55,.95);
    }

    @media (max-width: 540px) {
        .cl-mod-progress { grid-template-columns: 1fr; padding: 20px 22px; gap: 14px; }
        .cl-mod-progress__big { font-size: 44px; }
    }

    /* Modern category cards */
    .cl-mod-cat-list { display: flex; flex-direction: column; gap: 10px; }
    .cl-mod-cat {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(45,24,16,.04);
        overflow: hidden;
        transition: box-shadow .2s, border-color .2s, transform .2s;
    }
    .cl-mod-cat:hover {
        box-shadow: 0 10px 28px rgba(45,24,16,.10);
        border-color: #C9B69A;
        transform: translateY(-1px);
    }
    .cl-mod-cat.is-open {
        box-shadow: 0 10px 28px rgba(45,24,16,.10);
        border-color: #C9B69A;
    }
    .cl-mod-cat.is-complete .cl-mod-cat__num {
        background: linear-gradient(135deg, #1F6E5F, #155148);
        box-shadow: 0 4px 10px rgba(31,110,95,.30);
    }
    .cl-mod-cat__head {
        display: flex; align-items: center; gap: 14px;
        padding: 16px 18px;
        cursor: pointer; user-select: none;
        background: linear-gradient(180deg, #FFFFFF 0%, #FAF4EC 100%);
        transition: background .15s;
    }
    .cl-mod-cat__head:hover {
        background: linear-gradient(180deg, #FAF4EC 0%, #F5EBE0 100%);
    }
    .cl-mod-cat__num {
        width: 38px; height: 38px;
        border-radius: 10px;
        background: linear-gradient(135deg, #5C2E15, #471500);
        color: #FFFFFF;
        display: flex; align-items: center; justify-content: center;
        font-size: 15px; font-weight: 800;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(71,21,0,.22);
        transition: background .2s, box-shadow .2s;
    }
    .cl-mod-cat__info { flex: 1; min-width: 0; }
    .cl-mod-cat__title {
        font-size: 15px; font-weight: 700; color: #2D1810;
        margin: 0; line-height: 1.2;
    }
    .cl-mod-cat__meta {
        display: flex; align-items: center; gap: 8px;
        margin-top: 5px;
        font-size: 12px; color: #6B4423;
        flex-wrap: wrap;
    }
    .cl-mod-cat__chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px; border-radius: 999px;
        font-size: 11px; font-weight: 700;
    }
    .cl-mod-cat__chip--good { background: rgba(31,110,95,.12); color: #1F6E5F; }
    .cl-mod-cat__chip--warn { background: rgba(166,96,25,.12); color: #A66019; }
    .cl-mod-cat__chev {
        color: #9B8578;
        font-size: 14px;
        transition: transform .25s ease, color .25s ease;
        flex-shrink: 0;
    }
    .cl-mod-cat.is-open .cl-mod-cat__chev {
        transform: rotate(180deg);
        color: #5C2E15;
    }
    .cl-mod-cat__body { display: none; border-top: 1px solid #F0E8DC; background: #FFFFFF; }
    .cl-mod-cat.is-open .cl-mod-cat__body { display: block; animation: clFadeIn .25s ease; }
    @keyframes clFadeIn {
        from { opacity: 0; transform: translateY(-4px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .cl-mod-task {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 14px 18px;
        border-bottom: 1px solid #F5EBE0;
        cursor: pointer;
        transition: background .15s;
    }
    .cl-mod-task:last-child { border-bottom: none; }
    .cl-mod-task:hover { background: #FAF4EC; }
    .cl-mod-task.is-locked { cursor: not-allowed; opacity: .65; }
    .cl-mod-task__check {
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
    .cl-mod-task.is-done .cl-mod-task__check {
        background: linear-gradient(135deg, #5C2E15, #471500);
        border-color: #471500;
        box-shadow: 0 2px 6px rgba(71,21,0,.25);
    }
    .cl-mod-task.is-done .cl-mod-task__check::before { content: "✓"; }
    .cl-mod-task__name {
        flex: 1; min-width: 0;
        font-size: 13.5px; color: #2D1810; line-height: 1.5;
    }
    .cl-mod-task.is-done .cl-mod-task__name {
        color: #9B8578;
        text-decoration: line-through;
        text-decoration-thickness: 1px;
    }
    .cl-mod-task__time { font-size: 11px; color: #9B8578; flex-shrink: 0; margin-top: 4px; }

    /* Modern submit button */
    .cl-mod-submit {
        margin-top: 16px;
        background: linear-gradient(135deg, #5C2E15, #471500);
        color: #FFFFFF;
        border-radius: 14px;
        padding: 14px 22px;
        display: flex; align-items: center; justify-content: space-between;
        gap: 14px; flex-wrap: wrap;
        box-shadow: 0 6px 20px rgba(71,21,0,.20);
    }
    .cl-mod-submit__hint {
        font-size: 12.5px;
        color: rgba(255,255,255,.85);
        line-height: 1.4;
    }
    .cl-mod-submit__btn {
        border: none;
        background: #FFFFFF;
        color: #471500;
        padding: 10px 22px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex; align-items: center; gap: 8px;
        transition: transform .15s, box-shadow .15s;
        box-shadow: 0 4px 12px rgba(0,0,0,.10);
    }
    .cl-mod-submit__btn:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(0,0,0,.15); }
    .cl-mod-submit__btn:disabled {
        background: rgba(255,255,255,.30);
        color: rgba(255,255,255,.50);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    /* Status banner modern */
    .cl-mod-banner {
        padding: 14px 18px;
        border-radius: 14px;
        margin-bottom: 14px;
        display: flex; align-items: flex-start; gap: 12px;
        font-size: 13px; line-height: 1.5;
        border-left: 4px solid;
    }
    .cl-mod-banner i { font-size: 16px; margin-top: 1px; flex-shrink: 0; }
    .cl-mod-banner--success { background: #E1F5EE; border-color: #1F6E5F; color: #0F6E56; }
    .cl-mod-banner--warning { background: #FAEEDA; border-color: #A66019; color: #5C2E15; }
    .cl-mod-banner--danger  { background: #FCEBEB; border-color: #A32D2D; color: #7C2929; }
    .cl-mod-banner strong { font-weight: 700; }

    /* ── Side widgets ── */
    .cl-mini {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(45,24,16,.04);
        padding: 16px 18px;
        margin-bottom: 14px;
    }
    .cl-mini__head {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 12px;
    }
    .cl-mini__title {
        font-size: 12px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: #9B8578; margin: 0;
        display: inline-flex; align-items: center; gap: 7px;
    }
    .cl-mini__title i { color: #5C2E15; font-size: 11px; }

    /* Streak / stats card */
    .cl-stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .cl-stat-cell {
        background: linear-gradient(135deg, #FAF4EC 0%, #F5EBE0 100%);
        border: 1px solid #E0D2C1; border-radius: 10px;
        padding: 12px 14px;
    }
    .cl-stat-cell__icon {
        width: 28px; height: 28px; border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 13px; margin-bottom: 6px;
    }
    .cl-stat-cell__icon--fire { background: rgba(255, 107, 53, 0.15); color: #FF6B35; }
    .cl-stat-cell__icon--good { background: rgba(31, 110, 95, 0.15); color: #1F6E5F; }
    .cl-stat-cell__label {
        font-size: 10.5px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.4px; color: #9B8578; margin-bottom: 2px;
    }
    .cl-stat-cell__val {
        font-size: 22px; font-weight: 800; color: #2D1810; line-height: 1;
        letter-spacing: -0.02em;
    }
    .cl-stat-cell__val small { font-size: 12px; font-weight: 600; color: #6B4423; }

    /* Heatmap 14 hari */
    .cl-heatmap { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
    .cl-heatmap__cell {
        position: relative;
        aspect-ratio: 1;
        background: #FAFAF8;
        border: 1px solid #E0D2C1;
        border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        flex-direction: column;
        font-size: 10px;
        color: #9B8578;
        font-weight: 600;
        cursor: default;
        transition: transform .1s;
    }
    .cl-heatmap__cell:hover { transform: translateY(-1px); z-index: 1; }
    .cl-heatmap__cell--approved { background: #1F6E5F; color: #FFFFFF; border-color: #1F6E5F; }
    .cl-heatmap__cell--submitted { background: #FAEEDA; color: #A66019; border-color: #E8C29A; }
    .cl-heatmap__cell--rejected { background: #FCEBEB; color: #A32D2D; border-color: #E5B5B5; }
    .cl-heatmap__cell--draft    { background: #F5F0EA; color: #6B4423; border-color: #E0D2C1; }
    .cl-heatmap__cell--missed   { background: #FAFAF8; color: #C9B69A; }
    .cl-heatmap__cell--today { outline: 2px solid #5C2E15; outline-offset: -2px; }
    .cl-heatmap__cell .h-num { font-size: 13px; font-weight: 800; line-height: 1; margin-bottom: 1px; }
    .cl-heatmap__cell .h-short { font-size: 9px; opacity: .85; }
    .cl-heatmap__legend {
        display: flex; gap: 10px; flex-wrap: wrap;
        margin-top: 10px; font-size: 10.5px; color: #6B4423;
    }
    .cl-heatmap__legend span {
        display: inline-flex; align-items: center; gap: 4px;
    }
    .cl-heatmap__legend i {
        width: 10px; height: 10px; border-radius: 3px; border: 1px solid #E0D2C1;
    }

    /* Recent activity */
    .cl-recent { display: flex; flex-direction: column; gap: 6px; }
    .cl-recent__item {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 10px;
        border-radius: 8px;
        background: #FAF4EC;
        border: 1px solid transparent;
        text-decoration: none; color: inherit;
        transition: background .15s, border-color .15s;
    }
    .cl-recent__item:hover {
        background: #F5EBE0;
        border-color: #E0D2C1;
        text-decoration: none; color: inherit;
    }
    .cl-recent__date {
        min-width: 0; flex: 1;
    }
    .cl-recent__date strong {
        display: block; font-size: 12px; color: #2D1810; font-weight: 700;
    }
    .cl-recent__date small { font-size: 10.5px; color: #9B8578; }

    .cl-progress-bar {
        background: #F5F0EA; height: 10px; border-radius: 5px; overflow: hidden;
    }
    .cl-progress-bar > i {
        display: block; height: 100%;
        background: linear-gradient(90deg, #9B5E2C, #5C2E15);
        border-radius: 5px;
        transition: width .3s ease;
    }

    .cl-cat-block {
        background: #FFFFFF; border: 1px solid #E0D2C1; border-radius: 12px;
        margin-bottom: 12px; overflow: hidden;
        box-shadow: 0 1px 3px rgba(45,24,16,.04);
    }
    .cl-cat-head {
        display: flex; align-items: center; gap: 12px;
        padding: 14px 16px; cursor: pointer; user-select: none;
        background: #FFFFFF;
        transition: background .15s;
    }
    .cl-cat-head:hover { background: #FAF4EC; }
    .cl-cat-num {
        width: 28px; height: 28px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 12px; color: #FFFFFF;
        background: #5C2E15; flex-shrink: 0;
    }
    .cl-cat-num.is-complete { background: #1F6E5F; }
    .cl-cat-num.is-warn { background: #A66019; }
    .cl-cat-info { flex: 1; min-width: 0; }
    .cl-cat-info strong {
        display: block; color: #2D1810; font-size: 14px; font-weight: 700;
    }
    .cl-cat-info small {
        display: block; color: #6B4423; font-size: 11.5px; margin-top: 2px;
    }
    .cl-chevron {
        color: #9B8578; font-size: 16px;
        transition: transform .2s;
    }
    .cl-cat-block.is-open .cl-chevron { transform: rotate(180deg); color: #5C2E15; }
    .cl-cat-body { display: none; border-top: 1px solid #E0D2C1; }
    .cl-cat-block.is-open .cl-cat-body { display: block; }

    .cl-task {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid #F0E8DC;
        cursor: pointer;
        transition: background .15s;
    }
    .cl-task:last-child { border-bottom: none; }
    .cl-task:hover { background: #FAF4EC; }
    .cl-task.is-locked { cursor: not-allowed; opacity: .65; }
    .cl-cbox {
        width: 24px; height: 24px; border-radius: 6px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 700; color: #FFFFFF;
        background: #FFFFFF; border: 2px solid #C9B69A;
        transition: all .15s;
    }
    .cl-task.is-done .cl-cbox {
        background: #5C2E15; border-color: #5C2E15;
    }
    .cl-task.is-done .cl-cbox::before { content: "✓"; }
    .cl-task-name {
        flex: 1; min-width: 0; font-size: 13.5px; color: #2D1810;
        line-height: 1.45;
    }
    .cl-task.is-done .cl-task-name {
        color: #9B8578; text-decoration: line-through;
        text-decoration-thickness: 1px;
    }
    .cl-task-meta {
        display: flex; align-items: center; gap: 6px; flex-shrink: 0;
        font-size: 10.5px; color: #9B8578;
    }

    .cl-submit-bar {
        position: sticky; bottom: 0; z-index: 50;
        background: #FFFFFF; border-top: 1px solid #E0D2C1;
        padding: 12px 16px;
        box-shadow: 0 -2px 12px rgba(45,24,16,.06);
        margin: 12px -22px -22px;
    }
    .cl-submit-bar button {
        width: 100%; padding: 14px;
        font-size: 14.5px; font-weight: 700; border-radius: 10px;
        border: none; cursor: pointer;
        background: linear-gradient(180deg, #5C2E15, #471500);
        color: #FFFFFF;
        transition: opacity .15s, transform .15s;
    }
    .cl-submit-bar button:hover { transform: translateY(-1px); }
    .cl-submit-bar button:disabled {
        background: #C9B69A; color: #FFFFFF; cursor: not-allowed;
        transform: none;
    }

    .cl-banner {
        padding: 14px 16px; border-radius: 10px; margin-bottom: 14px;
        border-left: 4px solid; font-size: 13px;
        display: flex; align-items: flex-start; gap: 10px;
    }
    .cl-banner i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
    .cl-banner-rejected {
        background: #FCEBEB; border-color: #A32D2D; color: #7C2929;
    }
    .cl-banner-rejected strong { color: #A32D2D; }
    .cl-banner-pending {
        background: #FAEEDA; border-color: #A66019; color: #5C2E15;
    }
    .cl-banner-approved {
        background: #E1F5EE; border-color: #1F6E5F; color: #0F6E56;
    }

    @media (max-width: 600px) {
        .cl-submit-bar { margin: 12px -18px -18px; }
        .cl-task { padding: 13px 14px; }
        .cl-task-name { font-size: 14px; }
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

    <div class="cl-my-wrap">
        @if(! $position)
            {{-- ─── Empty state: belum punya divisi ─── --}}
            <div class="st-card">
                <div class="st-card__body" style="padding:48px 22px; text-align:center;">
                    <div style="display:inline-flex; align-items:center; justify-content:center; width:80px; height:80px; border-radius:50%; background:#FAF4EC; color:#9B5E2C; font-size:32px; margin-bottom:18px;">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <h5 style="font-size:18px; font-weight:700; color:#2D1810; margin-bottom:8px;">Posisi belum diatur</h5>
                    <p style="font-size:13.5px; color:#6B4423; max-width:420px; margin:0 auto 18px; line-height:1.6;">
                        Anda belum di-assign ke divisi mana pun di sistem checklist. Hubungi supervisor untuk mendaftarkan posisi (FO / GS / Asdent).
                    </p>
                </div>
            </div>
        @else
            {{-- ─── VIEW TOGGLE — di luar card, jelas terlihat ─── --}}
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                <div style="display:inline-flex; gap:2px; padding:4px; background:#FFFFFF; border-radius:999px; border:1px solid #E0D2C1; box-shadow:0 1px 3px rgba(45,24,16,.06); flex-wrap:wrap;">
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
                    <i class="fas fa-clock-rotate-left"></i> Riwayat
                </a>
            </div>

            {{-- SUB-TOGGLE: Hari Ini vs Tampilan Mingguan (7 hari) --}}
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
                <span style="font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#9B8578;">Tampilan:</span>
                <div style="display:inline-flex; gap:2px; padding:3px; background:#FAF4EC; border-radius:999px; border:1px solid #E0D2C1;">
                    <a href="{{ route('mychecklist') }}"
                       style="padding:6px 14px; font-size:12px; font-weight:700; text-decoration:none; border-radius:999px; background:#FFFFFF; color:#5C2E15; box-shadow:0 1px 3px rgba(45,24,16,.10); display:inline-flex; align-items:center; gap:6px;">
                        <i class="fas fa-sun" style="font-size:10px;"></i> Hari Ini
                    </a>
                    <a href="{{ route('mychecklist.weekly') }}"
                       style="padding:6px 14px; font-size:12px; font-weight:600; text-decoration:none; border-radius:999px; color:#6B4423; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fas fa-th" style="font-size:10px;"></i> Tampilan Mingguan
                    </a>
                </div>
            </div>

            <div class="cl-my-grid">
            <div>

                {{-- ─── HERO GRADIENT (greeting + status + position) ─── --}}
                @php
                    $hour = (int) now()->format('H');
                    $greet = $hour < 11 ? 'Selamat Pagi' : ($hour < 15 ? 'Selamat Siang' : ($hour < 18 ? 'Selamat Sore' : 'Selamat Malam'));
                    $statusOpt = \App\Models\ChecklistSubmission::statusOptions()[$submission->status] ?? null;
                    $statusColorMap = [
                        'success' => 'success', 'warning' => 'warning', 'danger' => 'danger',
                        'neutral' => 'warning', 'info' => 'warning',
                    ];
                    $heroStatusColor = $statusColorMap[$statusOpt['color'] ?? 'neutral'] ?? 'warning';
                @endphp
                <div class="cl-mod-hero">
                    <div class="cl-mod-hero__row">
                        <div class="cl-mod-hero__left">
                            <div class="cl-mod-hero__avatar">{{ strtoupper(mb_substr(session('name') ?? 'A', 0, 1)) }}</div>
                            <div style="min-width:0;">
                                <h5 class="cl-mod-hero__greet">
                                    {{ $greet }}, {{ session('name') ?? 'Karyawan' }}!
                                </h5>
                                <div class="cl-mod-hero__meta">
                                    <i class="fas fa-calendar-day" style="font-size:11px; opacity:.85;"></i>
                                    {{ $today->translatedFormat('l, d F Y') }}
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
                        @if($submission->status !== \App\Models\ChecklistSubmission::STATUS_DRAFT && $statusOpt)
                            <span class="cl-mod-hero__status cl-mod-hero__status--{{ $heroStatusColor }}">
                                @if($submission->status === 'approved')<i class="fas fa-circle-check"></i>
                                @elseif($submission->status === 'rejected')<i class="fas fa-circle-xmark"></i>
                                @else<i class="fas fa-clock"></i>
                                @endif
                                {{ $statusOpt['label'] }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- ─── BANNER STATUS ─── --}}
                @if($submission->status === \App\Models\ChecklistSubmission::STATUS_REJECTED)
                    <div class="cl-mod-banner cl-mod-banner--danger">
                        <i class="fas fa-circle-xmark"></i>
                        <div style="flex:1;">
                            <strong>Checklist anda ditolak supervisor.</strong>
                            @if($rejectedNote)<div style="margin-top:4px;">Catatan: {{ $rejectedNote }}</div>@endif
                            <form method="POST" action="{{ route('mychecklist.reopen-self') }}" style="margin-top:10px;">
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
                        <div><strong>Menunggu approval supervisor.</strong> Anda akan diberitahu via notifikasi saat sudah direview.</div>
                    </div>
                @elseif($submission->status === \App\Models\ChecklistSubmission::STATUS_APPROVED)
                    <div class="cl-mod-banner cl-mod-banner--success">
                        <i class="fas fa-circle-check"></i>
                        <div><strong>Checklist hari ini disetujui supervisor.</strong> Terima kasih atas konsistensinya! 🎉</div>
                    </div>
                @endif

                {{-- ─── PROGRESS CARD (modern, big %) ─── --}}
                <div class="cl-mod-progress">
                    <div class="cl-mod-progress__big" id="clProgressPct">
                        {{ $progress['pct'] }}<small>%</small>
                    </div>
                    <div class="cl-mod-progress__body">
                        <div class="cl-mod-progress__label">Progres Hari Ini</div>
                        <div class="cl-mod-progress__count" id="clProgressCount">
                            <strong>{{ $progress['done'] }}</strong> dari {{ $progress['total'] }} tugas <small>· {{ max(0, $progress['total'] - $progress['done']) }} belum dikerjakan</small>
                        </div>
                        <div class="cl-mod-progress__bar"><i id="clProgressFill" style="width:{{ $progress['pct'] }}%"></i></div>
                    </div>
                </div>

                {{-- ─── KATEGORI LIST (modern cards) ─── --}}
                @if($grouped->isEmpty())
                    <div class="st-card">
                        <div class="st-card__body" style="padding:36px 20px;">
                            <div class="cl-empty">
                                <span class="cl-empty__icon"><i class="fas fa-clipboard"></i></span>
                                <div class="cl-empty__title">Belum ada tugas</div>
                                <div class="cl-empty__desc">Supervisor belum mengatur task untuk divisi {{ $position->division?->name }}.</div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="cl-mod-cat-list">
                        @php $catIdx = 0; @endphp
                        @foreach($grouped as $catTitle => $items)
                            @php
                                $catIdx++;
                                $catDone = $items->where('is_done', true)->count();
                                $catTotal = $items->count();
                                $catComplete = $catDone === $catTotal;
                                $isOpen = ! $catComplete;
                            @endphp
                            <div class="cl-mod-cat {{ $isOpen ? 'is-open' : '' }} {{ $catComplete ? 'is-complete' : '' }}" data-cat-block>
                                <div class="cl-mod-cat__head" data-cat-toggle>
                                    <span class="cl-mod-cat__num" data-cat-num>{{ $catIdx }}</span>
                                    <div class="cl-mod-cat__info">
                                        <h6 class="cl-mod-cat__title">{{ $catTitle }}</h6>
                                        <div class="cl-mod-cat__meta">
                                            <span class="cl-mod-cat__chip cl-mod-cat__chip--{{ $catComplete ? 'good' : 'warn' }}" data-cat-chip>
                                                @if($catComplete)<i class="fas fa-check" style="font-size:9px;"></i>@endif
                                                {{ $catDone }}/{{ $catTotal }} tugas
                                            </span>
                                            <span data-cat-meta>
                                                @if($catComplete)
                                                    semua selesai
                                                @else
                                                    {{ $catTotal - $catDone }} belum dikerjakan
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-down cl-mod-cat__chev"></i>
                                </div>
                                <div class="cl-mod-cat__body">
                                    @foreach($items as $item)
                                        <div class="cl-mod-task {{ $item->is_done ? 'is-done' : '' }} {{ $submission->isEditable() ? '' : 'is-locked' }}"
                                             data-item-id="{{ $item->id }}"
                                             data-toggle-url="{{ route('mychecklist.item.toggle', $item->id) }}"
                                             data-editable="{{ $submission->isEditable() ? '1' : '0' }}">
                                            <span class="cl-mod-task__check"></span>
                                            <div class="cl-mod-task__name">{{ $item->task_title_snapshot }}</div>
                                            @if($item->done_at)
                                                <div class="cl-mod-task__time">{{ $item->done_at->format('H:i') }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- ─── SUBMIT FOOTER (modern) ─── --}}
                @if($submission->isEditable() && $grouped->isNotEmpty())
                    <form method="POST" action="{{ route('mychecklist.submit') }}"
                          onsubmit="return confirm('Submit checklist hari ini? Setelah submit, anda tidak bisa edit lagi sampai supervisor review.');">
                        @csrf
                        <div class="cl-mod-submit">
                            <div class="cl-mod-submit__hint">
                                {{-- Hint dinamis: kalau belum 100%, ingat user. Kalau 100%, congrats vibe. --}}
                                @if($progress['done'] === 0)
                                    Mulai centang task yang sudah selesai.
                                @elseif($progress['done'] < $progress['total'])
                                    Masih ada <strong style="color:#FFF;">{{ $progress['total'] - $progress['done'] }} task</strong> belum dicentang. Lanjut atau submit sekarang?
                                @else
                                    <strong style="color:#FFF;">Semua task selesai! 🎉</strong> Klik submit untuk kirim ke supervisor.
                                @endif
                            </div>
                            <button type="submit" class="cl-mod-submit__btn" id="clSubmitBtn" {{ $progress['done'] === 0 ? 'disabled' : '' }}>
                                @if($progress['done'] === 0)
                                    Isi minimal 1 task
                                @else
                                    <i class="fas fa-paper-plane"></i> Submit Checklist
                                @endif
                            </button>
                        </div>{{-- /cl-mod-submit --}}
                    </form>
                @endif
            </div>{{-- /main column --}}

            {{-- ─── SIDEBAR ─── --}}
            <aside>
                {{-- Stats: Streak + Rate minggu ini --}}
                <div class="cl-mini">
                    <div class="cl-mini__head">
                        <h6 class="cl-mini__title"><i class="fas fa-chart-line"></i> Statistik</h6>
                    </div>
                    <div class="cl-stat-grid">
                        <div class="cl-stat-cell">
                            <span class="cl-stat-cell__icon cl-stat-cell__icon--fire"><i class="fas fa-fire"></i></span>
                            <div class="cl-stat-cell__label">Streak</div>
                            <div class="cl-stat-cell__val">{{ $extras['streak'] }} <small>hari</small></div>
                        </div>
                        <div class="cl-stat-cell">
                            <span class="cl-stat-cell__icon cl-stat-cell__icon--good"><i class="fas fa-calendar-week"></i></span>
                            <div class="cl-stat-cell__label">Minggu Ini</div>
                            <div class="cl-stat-cell__val">{{ $extras['week_rate'] }}<small>%</small></div>
                            <div style="font-size:10.5px; color:#6B4423; margin-top:2px;">{{ $extras['week_submitted'] }}/{{ $extras['week_total'] }} hari isi</div>
                        </div>
                    </div>
                </div>

                {{-- Heatmap 14 hari --}}
                <div class="cl-mini">
                    <div class="cl-mini__head">
                        <h6 class="cl-mini__title"><i class="fas fa-th"></i> Riwayat 14 Hari</h6>
                    </div>
                    <div class="cl-heatmap">
                        @foreach($extras['heatmap'] as $h)
                            @php
                                $cls = 'cl-heatmap__cell';
                                $cls .= ' cl-heatmap__cell--' . ($h->status ?? 'missed');
                                if ($h->is_today) $cls .= ' cl-heatmap__cell--today';
                                $statusLabel = match($h->status) {
                                    'approved'  => 'Disetujui',
                                    'submitted' => 'Menunggu',
                                    'rejected'  => 'Ditolak',
                                    'draft'     => 'Draft',
                                    default     => 'Tidak diisi',
                                };
                            @endphp
                            <div class="{{ $cls }}" title="{{ $h->date->translatedFormat('l, d M') }} — {{ $statusLabel }}">
                                <span class="h-num">{{ $h->day }}</span>
                                <span class="h-short">{{ $h->short }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="cl-heatmap__legend">
                        <span><i style="background:#1F6E5F; border-color:#1F6E5F;"></i> Disetujui</span>
                        <span><i style="background:#FAEEDA; border-color:#E8C29A;"></i> Menunggu</span>
                        <span><i style="background:#FCEBEB; border-color:#E5B5B5;"></i> Ditolak</span>
                        <span><i style="background:#FAFAF8;"></i> Tidak diisi</span>
                    </div>
                </div>

                {{-- Recent activity --}}
                <div class="cl-mini">
                    <div class="cl-mini__head">
                        <h6 class="cl-mini__title"><i class="fas fa-history"></i> Aktivitas Terbaru</h6>
                        <a href="{{ route('mychecklist.history') }}" style="font-size:11px; color:#5C2E15; text-decoration:none; font-weight:600;">Semua →</a>
                    </div>
                    @if($extras['recent']->isEmpty())
                        <div style="text-align:center; color:#9B8578; font-size:12px; padding:14px 0; font-style:italic;">
                            Belum ada riwayat — mulai isi checklist hari ini.
                        </div>
                    @else
                        <div class="cl-recent">
                            @foreach($extras['recent'] as $r)
                                @php
                                    $opt = \App\Models\ChecklistSubmission::statusOptions()[$r->status] ?? ['label'=>$r->status,'color'=>'neutral'];
                                @endphp
                                <div class="cl-recent__item">
                                    <div class="cl-recent__date">
                                        <strong>{{ $r->submission_date->translatedFormat('D, d M') }}</strong>
                                        <small>{{ $r->done_tasks }}/{{ $r->total_tasks }} tugas · {{ $r->progressPct() }}%</small>
                                    </div>
                                    <span class="st-pill st-pill--{{ $opt['color'] }}" style="font-size:10.5px; padding:3px 8px;">{{ $opt['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Tips card --}}
                <div class="cl-mini" style="background:linear-gradient(135deg, #FAF4EC 0%, #F5EBE0 100%); border-color:#E0D2C1;">
                    <div class="cl-mini__head" style="margin-bottom:8px;">
                        <h6 class="cl-mini__title" style="color:#5C2E15;"><i class="fas fa-lightbulb" style="color:#A66019;"></i> Tips Hari Ini</h6>
                    </div>
                    <p style="font-size:12.5px; color:#5C2E15; line-height:1.55; margin:0;">
                        Cek list <strong>sebelum mulai shift</strong> & isi <strong>setelah selesai</strong> biar nggak ada yang kelewat. Pekerjaan rapi = waktu lebih efisien 🚀
                    </p>
                </div>
            </aside>
            </div>{{-- /cl-my-grid --}}
        @endif
    </div>
</div>

@if($position && $grouped->isNotEmpty())
<script>
(function() {
    // Accordion toggle per kategori
    document.querySelectorAll('[data-cat-toggle]').forEach(head => {
        head.addEventListener('click', () => {
            head.closest('[data-cat-block]').classList.toggle('is-open');
        });
    });

    // Toggle task — AJAX optimistic
    const csrf = document.querySelector('meta[name=csrf-token]')?.content;
    const pctEl   = document.getElementById('clProgressPct');
    const fillEl  = document.getElementById('clProgressFill');
    const countEl = document.getElementById('clProgressCount');
    const submitBtn = document.getElementById('clSubmitBtn');

    document.querySelectorAll('[data-item-id]').forEach(task => {
        task.addEventListener('click', async (e) => {
            if (task.dataset.editable !== '1') return;
            const url = task.dataset.toggleUrl;

            // Optimistic UI
            const wasDone = task.classList.contains('is-done');
            task.classList.toggle('is-done');
            updateCatCounter(task);

            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Content-Type': 'application/json',
                    },
                });
                const data = await res.json();
                if (! data.ok) throw new Error(data.error || 'Gagal toggle');

                task.classList.toggle('is-done', data.is_done);
                updateProgressGlobal(data.progress);
            } catch (err) {
                task.classList.toggle('is-done', wasDone);
                updateCatCounter(task);
                alert('Gagal: ' + (err.message || err));
            }
        });
    });

    function updateCatCounter(task) {
        const block = task.closest('[data-cat-block]');
        if (! block) return;
        const all   = block.querySelectorAll('[data-item-id]');
        const done  = block.querySelectorAll('[data-item-id].is-done');
        const total = all.length;
        const cnt   = done.length;
        const complete = cnt === total;

        // Update chip count
        const chip = block.querySelector('[data-cat-chip]');
        if (chip) {
            chip.innerHTML = (complete ? '<i class="fas fa-check" style="font-size:9px;"></i> ' : '') + cnt + '/' + total + ' tugas';
            chip.classList.toggle('cl-mod-cat__chip--good', complete);
            chip.classList.toggle('cl-mod-cat__chip--warn', ! complete);
        }
        const meta = block.querySelector('[data-cat-meta]');
        if (meta) meta.textContent = complete ? 'semua selesai' : (total - cnt) + ' belum dikerjakan';

        // Update card state classes
        block.classList.toggle('is-complete', complete);
    }

    function updateProgressGlobal(p) {
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
                ? '<i class="fas fa-paper-plane"></i> Submit Checklist'
                : 'Isi minimal 1 task';
        }
        // Update submit hint
        const hintEl = document.querySelector('.cl-mod-submit__hint');
        if (hintEl) {
            if (p.done === 0) {
                hintEl.innerHTML = 'Mulai centang task yang sudah selesai.';
            } else if (p.done < p.total) {
                hintEl.innerHTML = 'Masih ada <strong style="color:#FFF;">' + (p.total - p.done) + ' task</strong> belum dicentang. Lanjut atau submit sekarang?';
            } else {
                hintEl.innerHTML = '<strong style="color:#FFF;">Semua task selesai! 🎉</strong> Klik submit untuk kirim ke supervisor.';
            }
        }
    }
})();
</script>
@endif
@endsection
