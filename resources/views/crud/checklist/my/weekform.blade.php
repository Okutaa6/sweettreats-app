@extends('layouts.app')

@section('css')
<style>
    .wf-wrap { max-width: 1100px; margin: 0 auto; padding-bottom: 32px; }
    /* Hero ala handoff: gradient 120deg + aksen kiri teal (identitas Mingguan),
       ikon tile teal 44px, judul + (sub & pill dalam SATU baris di bawahnya) */
    .wf-hero {
        background: linear-gradient(120deg, #5C2E15, #471500);
        color: #FFFFFF;
        border-radius: 18px;
        border-left: 5px solid #1F6E5F;
        padding: 18px 22px;
        margin-bottom: 14px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(70,21,0,.22);
    }
    .wf-hero__row {
        display: flex; align-items: center;
        gap: 15px; flex-wrap: wrap; position: relative; z-index: 1;
    }
    .wf-hero__icon {
        width: 44px; height: 44px; flex-shrink: 0;
        border-radius: 12px;
        background: linear-gradient(135deg, #1F6E5F, #155148);
        display: flex; align-items: center; justify-content: center;
        color: #DFF3EC; font-size: 18px;
    }
    .wf-hero__title { margin: 0; font-size: 17px; font-weight: 800; color: #FBF1E7; letter-spacing: -0.01em; }
    .wf-hero__meta {
        display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
        margin-top: 5px;
    }
    .wf-hero__sub { font-size: 12px; color: #D8B99C; font-weight: 600; }
    .wf-hero__chip {
        background: rgba(255,255,255,.12); padding: 3px 10px;
        border-radius: 20px; border: none;
        font-size: 11px; font-weight: 700; color: #FBEADB;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .wf-hero__status {
        padding: 6px 14px; border-radius: 999px;
        font-size: 12px; font-weight: 700;
        backdrop-filter: blur(8px);
        display: inline-flex; align-items: center; gap: 6px;
    }
    .wf-hero__status--success { background: #E1F5EE; color: #1F6E5F; border: 1px solid rgba(31,110,95,.30); }
    .wf-hero__status--warning { background: #FAEEDA; color: #A66019; border: 1px solid rgba(166,96,25,.30); }
    .wf-hero__status--danger  { background: #FCEBEB; color: #A32D2D; border: 1px solid rgba(163,45,45,.30); }
    .wf-hero__status--draft   { background: #F5F0EA; color: #7A5B43; border: 1px solid rgba(122,91,67,.28); }
    .wf-hero__status--coffee  { background: #F2E7D8; color: #8A5A2B; border: 1px solid rgba(138,90,43,.30); }
    .wf-hero__status--slate   { background: #EFE7DC; color: #9B8578; border: 1px solid rgba(155,133,120,.30); }

    /* Toolbar SATU BARIS (handoff): pager ‹ › + label minggu | pembatas | % + bar | hitungan */
    .wf-toolbar {
        background: #FFFFFF;
        border: 1px solid #EFE0CE;
        border-radius: 16px;
        margin-bottom: 14px;
        box-shadow: 0 1px 2px rgba(70,21,0,.04);
        padding: 14px 20px;
        display: flex; align-items: center;
        gap: 16px; flex-wrap: wrap;
    }
    .wf-nav { display: flex; align-items: center; gap: 9px; }
    .wf-nav__title { font-size: 13.5px; font-weight: 800; color: #3B1500; margin: 0; white-space: nowrap; }
    .wf-nav__sub { font-size: 11px; color: #1F6E5F; font-weight: 700; white-space: nowrap; margin-top: 1px; }
    .wf-nav__btn {
        width: 34px; height: 34px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 50%;
        background: #FFFFFF; color: #5A3A22;
        text-decoration: none; border: 1px solid #E7D3BE;
        font-weight: 800;
        transition: background .15s, transform .1s;
    }
    .wf-nav__btn:hover { background: #FAF4EC; transform: translateY(-1px); text-decoration: none; color: #5A3A22; }
    .wf-toolbar__sep { width: 1px; align-self: stretch; background: #F1E6D7; }
    .wf-progress { display: flex; align-items: center; gap: 16px; flex: 1; min-width: 260px; }
    .wf-progress__big {
        font-size: 30px; font-weight: 800;
        color: #2D1810; line-height: 1;
        letter-spacing: -0.03em;
        display: flex; align-items: baseline; gap: 2px;
        flex-shrink: 0;
    }
    .wf-progress__big small { font-size: 14px; color: inherit; opacity: .6; font-weight: 700; }
    .wf-progress.is-good .wf-progress__big { color: #2F8E55; }
    .wf-progress.is-warn .wf-progress__big { color: #C9962F; }
    .wf-progress.is-bad  .wf-progress__big { color: #C0392B; }
    .wf-progress__body { flex: 1; min-width: 140px; }
    .wf-progress__label {
        font-size: 10.5px; font-weight: 800; text-transform: uppercase;
        letter-spacing: 0.6px; color: #9A7256;
    }
    .wf-progress__count { font-size: 12.5px; color: #5A3A22; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
    .wf-progress__count small { color: #9A7256; font-weight: 600; font-size: 12px; }
    .wf-progress__bar { background: #F1E6D7; height: 9px; border-radius: 8px; overflow: hidden; margin-top: 6px; }
    .wf-progress__bar > i {
        display: block; height: 100%;
        background: #C0392B;
        border-radius: 8px;
        transition: width .4s cubic-bezier(.45,.05,.55,.95), background .2s;
    }
    .wf-progress.is-good .wf-progress__bar > i { background: #2F8E55; }
    .wf-progress.is-warn .wf-progress__bar > i { background: #C9962F; }
    .wf-progress.is-bad  .wf-progress__bar > i { background: #C0392B; }
    @media (max-width: 540px) {
        .wf-toolbar { padding: 14px 16px; }
        .wf-toolbar__sep { display: none; }
        .wf-progress { min-width: 100%; }
        .wf-progress__count { white-space: normal; }
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

    /* Day card — gaya handoff: kotak tanggal 46px (bulan di atas), pill hitung di kanan */
    .wf-day-list { display: flex; flex-direction: column; gap: 12px; }
    .wf-day {
        background: #FFFFFF;
        border: 1px solid #EFE0CE;
        border-radius: 16px;
        box-shadow: 0 1px 2px rgba(70,21,0,.04);
        overflow: hidden;
    }
    .wf-day.is-today { border-color: #BCE0D2; }
    .wf-day.is-empty { opacity: .72; }
    .wf-day__head {
        display: flex; align-items: center; gap: 14px;
        padding: 13px 16px;
        background: #FFFFFF;
        border-bottom: 1px solid #F1E6D7;
        transition: background .15s;
    }
    .wf-day__head:hover { background: #FCF7F0; }
    .wf-day__num {
        width: 46px; height: 46px;
        border-radius: 13px;
        background: linear-gradient(135deg, #A8481B, #5C2E15);
        color: #FFFFFF;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .wf-day__num strong { font-size: 18px; font-weight: 800; line-height: 1; }
    .wf-day__num small { font-size: 9px; font-weight: 800; letter-spacing: .5px; opacity: .85; text-transform: uppercase; }
    .wf-day.is-today .wf-day__num { background: linear-gradient(135deg, #1F6E5F, #155148); }
    .wf-day__info { flex: 1; min-width: 0; }
    .wf-day__title {
        font-size: 14px; font-weight: 800; color: #3B1500;
        margin: 0; line-height: 1.2;
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .wf-day__title .badge-today {
        font-size: 9.5px; font-weight: 800; letter-spacing: 0.6px;
        background: #E1F5EE; color: #1F6E5F;
        padding: 2px 7px; border-radius: 6px;
        text-transform: uppercase;
    }
    .wf-day__meta { font-size: 11.5px; color: #9A7256; font-weight: 500; margin-top: 2px; }
    /* Pill hitung di KANAN header (sebelum caret) — ikut handoff */
    .wf-day__pill {
        flex-shrink: 0;
        display: inline-flex; align-items: center; gap: 4px;
        padding: 5px 11px; border-radius: 20px;
        font-size: 11px; font-weight: 800; white-space: nowrap;
    }
    .wf-day__pill--good { background: #E1F5EE; color: #1F6E5F; }
    .wf-day__pill--warn { background: #FAEEDA; color: #B5701A; }
    .wf-day__pill--neutral { background: #F5EDE0; color: #B7926F; font-weight: 700; }
    .wf-day__pill--excused { background: #F2E7D8; color: #8A5A2B; }

    /* Accordion: head jadi tombol buka/tutup, body diciutkan halus (0fr↔1fr) */
    .wf-day__body {
        background: #FFFFFF;
        display: grid;
        grid-template-rows: 1fr;
        transition: grid-template-rows .32s cubic-bezier(.4,0,.2,1);
    }
    .wf-day__body-inner { overflow: hidden; min-height: 0; }
    .wf-day.is-collapsed .wf-day__body { grid-template-rows: 0fr; }

    .wf-day__head { cursor: pointer; user-select: none; }
    /* Caret polos (tanpa lingkaran) ala handoff: terbuka = mengarah ke atas */
    .wf-day__caret {
        flex-shrink: 0; margin-left: 4px;
        color: #9A7256; font-size: 13px;
        transform: rotate(180deg);
        transition: transform .3s cubic-bezier(.4,0,.2,1), color .15s ease;
    }
    .wf-day__head:hover .wf-day__caret { color: #5C2E15; }
    .wf-day.is-collapsed .wf-day__caret { transform: rotate(0deg); }
    .wf-day.is-collapsed .wf-day__head { border-bottom: none; }

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
    .wf-task.is-excused { cursor: default; opacity: .5; }
    .wf-task.is-excused:hover { background: transparent; }
    .wf-task__check {
        width: 30px; height: 30px;
        border-radius: 9px;
        border: 2px solid #DCC4A8;
        background: #FFFFFF;
        display: flex; align-items: center; justify-content: center;
        color: #FFFFFF;
        font-size: 14px;
        font-weight: 700;
        flex-shrink: 0;
        transition: all .15s;
    }
    /* Checkbox mingguan pakai teal (identitas jenis Mingguan, ikut handoff). */
    .wf-task.is-done .wf-task__check {
        background: linear-gradient(135deg, #2A8A76, #155148);
        border-color: transparent;
        box-shadow: 0 2px 6px rgba(31,110,95,.28);
    }
    .wf-task.is-done .wf-task__check::before { content: "✓"; }
    .wf-task__body { flex: 1; min-width: 0; }
    .wf-task__name { font-size: 13.5px; font-weight: 600; color: #3B1500; line-height: 1.5; }
    .wf-task.is-done .wf-task__name { color: #9A7256; text-decoration: line-through; text-decoration-thickness: 1px; }
    .wf-task__time { font-size: 11px; color: #B7926F; font-weight: 600; flex-shrink: 0; align-self: center; }

    /* ── Pengecualian per-pertanyaan (mingguan) ─────────────────────────── */
    .wf-task.is-excepted:not(.is-done) .wf-task__check { background: #F0E4D3; border-color: #C9A97F; color: #8A5A2B; }
    .wf-task.is-excepted:not(.is-done) .wf-task__check::before { content: "–"; }
    .wf-task.is-excepted:not(.is-done) .wf-task__name { color: #9A7256; }
    .cl-xcpt__open {
        margin-top: 8px; border: 1px solid #E7D3BE; background: #fff; color: #9A7256;
        border-radius: 8px; padding: 5px 10px; font-size: 11px; font-weight: 700;
        font-family: inherit; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: .15s;
    }
    .cl-xcpt__open:hover { background: #FBF6EE; color: #8A5A2B; border-color: #DCC4A8; }
    .wf-task.is-excepted .cl-xcpt__open { display: none; }
    .cl-xcpt__flag { margin-top: 8px; display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
    .cl-xcpt__pill { flex-shrink: 0; font-size: 11px; font-weight: 800; color: #fff; background: #8A5A2B; border-radius: 20px; padding: 4px 11px; display: inline-flex; align-items: center; gap: 5px; }
    .cl-xcpt__reason { font-size: 12px; color: #6B4423; font-style: italic; }
    .cl-xcpt__undo { border: none; background: transparent; color: #8A3A12; font-size: 11.5px; font-weight: 700; cursor: pointer; font-family: inherit; text-decoration: underline; padding: 2px 4px; }
    .cl-xcpt__undo:hover { color: #6B2400; }
    .cl-xcpt__form { margin-top: 8px; padding: 12px 14px; border: 1px dashed #DCC4A8; border-radius: 12px; background: #FBF6EE; display: flex; flex-direction: column; gap: 10px; }
    .cl-xcpt__label { font-size: 12px; color: #9A7256; }
    .cl-xcpt__label b { color: #6B4423; }
    .cl-xcpt__chips { display: flex; flex-wrap: wrap; gap: 7px; }
    .cl-xcpt__chip { font-size: 11px; font-weight: 700; padding: 6px 12px; border-radius: 20px; border: 1px dashed #D9C4A6; background: #fff; color: #8A5A2B; cursor: pointer; font-family: inherit; transition: .15s; }
    .cl-xcpt__chip:hover { filter: brightness(.97); }
    .cl-xcpt__chip.is-active { background: #8A5A2B; color: #fff; border-color: transparent; }
    .cl-xcpt__input { width: 100%; box-sizing: border-box; border: 1px solid #E7D3BE; border-radius: 10px; padding: 9px 12px; font-size: 12.5px; color: #3B1500; background: #fff; font-family: inherit; resize: vertical; }
    .cl-xcpt__input:focus { outline: none; border-color: #C9962F; box-shadow: 0 0 0 3px rgba(201,150,47,.12); }
    .cl-xcpt__actions { display: flex; gap: 8px; align-items: center; }
    .cl-xcpt__cancel { border: none; background: transparent; color: #9A7256; font-size: 12px; font-weight: 700; cursor: pointer; font-family: inherit; padding: 8px 10px; }
    .cl-xcpt__save { border: none; border-radius: 10px; background: #8A5A2B; color: #fff; font-size: 12px; font-weight: 800; padding: 9px 16px; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 6px; transition: .15s; }
    .cl-xcpt__save:hover { filter: brightness(.96); }
    .cl-xcpt__save:disabled { background: #EFE3D2; color: #B7926F; cursor: not-allowed; filter: none; }
    {{-- Catatan: task Mingguan TIDAK pakai kategori (beda dgn handoff yang menampilkan
         divider kategori) — task langsung dijadwalkan per hari, jadi tak ada divider. --}}

    /* Submit footer — kartu gradient gelap ala handoff: headline + sub kiri, tombol kanan,
       textarea catatan supervisor semi-transparan di bawah */
    .wf-submit {
        margin-top: 16px;
        background: linear-gradient(120deg, #5C2E15, #471500);
        color: #FFFFFF;
        border-radius: 16px;
        padding: 16px 20px;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
        box-shadow: 0 8px 24px rgba(70,21,0,.2);
    }
    .wf-submit__top {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .wf-submit__text { flex: 1; min-width: 220px; }
    .wf-submit__headline { font-size: 13.5px; font-weight: 800; color: #FBF1E7; }
    .wf-submit__sub { font-size: 12px; color: #D8B99C; font-weight: 500; margin-top: 3px; }
    .wf-submit__note textarea {
        width: 100%; resize: vertical; min-height: 44px; box-sizing: border-box;
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.2);
        border-radius: 11px; padding: 10px 13px;
        color: #FBF1E7; font-size: 12.5px; font-family: inherit; line-height: 1.5;
        transition: border-color .15s, background .15s;
    }
    .wf-submit__note textarea::placeholder { color: rgba(216,185,156,.8); }
    .wf-submit__note textarea:focus {
        outline: 0; border-color: rgba(255,255,255,.45); background: rgba(255,255,255,.13);
    }
    .wf-submit__btn {
        border: 0;
        outline: 0;
        background: #FFEAD6;
        color: #5C2E15;
        padding: 12px 22px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 13.5px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: transform .15s, filter .15s, box-shadow .15s;
        box-shadow: 0 4px 14px rgba(0,0,0,.25);
    }
    .wf-submit__btn:hover { filter: brightness(.96); transform: translateY(-1px); }
    .wf-submit__btn:focus { outline: 0; }
    .wf-submit__btn:disabled {
        background: rgba(255,255,255,.12);
        color: #C9A583;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
        filter: none;
    }

    /* ── Perlu foto (kamera) ── */
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
        border: 1px dashed #D9C4A6; background: #FBF6EE; color: #8A5A2B;
        font-size: 12px; font-weight: 700; padding: 7px 14px; border-radius: 999px;
        cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: .15s;
    }
    .wf-photo__btn:hover { background: #F5EAD9; border-color: #C9B69A; }
    .wf-photo__remove {
        border: 1px solid #E0D2C1; background: #fff; color: #7C2929;
        font-size: 11.5px; font-weight: 600; padding: 6px 12px; border-radius: 999px;
        cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: .15s;
    }
    .wf-photo__remove:hover { background: #FCEBEB; border-color: #A32D2D; }
    .wf-photo__status { font-size: 11px; color: #6B4423; font-weight: 600; }

    /* ── Foto before/after opsional (divisi GS) — pill ringkas, gambar di modal ── */
    .wf-ba { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
    .wf-ba__col { display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    /* Kosong → dashed netral (foto opsional, tak memblok centang — ikut handoff) */
    .wf-ba__pill {
        display: inline-flex; align-items: center; gap: 7px;
        border: 1.5px dashed #DCC4A8; background: #FFFFFF; color: #9A7256;
        font-size: 11px; font-weight: 800; padding: 7px 13px; border-radius: 20px;
        cursor: pointer; transition: .15s; line-height: 1;
    }
    .wf-ba__pill:hover { background: #F5EAD9; border-color: #C9B69A; }
    .wf-ba__pill i { font-size: 13px; }
    .wf-ba__pill-cap { text-transform: uppercase; letter-spacing: .5px; font-size: 10px; font-weight: 800; color: #8A5A2B; }
    /* Ada foto → tombol solid brand (jelas bisa diklik untuk lihat) */
    .wf-ba__pill.has {
        border-color: transparent;
        background: linear-gradient(180deg, #6B3A1C, #4A1A05);
        color: #FFFFFF; box-shadow: 0 2px 6px rgba(71,21,0,.28);
    }
    .wf-ba__pill.has i { color: #F7C77E; }
    .wf-ba__pill.has:hover { transform: translateY(-1px); box-shadow: 0 6px 15px rgba(71,21,0,.34); }
    .wf-ba__pill.has:active { transform: translateY(0); box-shadow: 0 2px 6px rgba(71,21,0,.28); }
    .wf-ba__pill.has .wf-ba__pill-cap {
        color: #FBE4BE; padding-right: 9px; margin-right: 1px;
        border-right: 1px solid rgba(255,236,205,.30);
    }
    .wf-ba__pill.has > span:last-child { color: #FFFFFF; font-weight: 800; }
    .wf-ba__tools { display: none; gap: 5px; align-items: center; }
    .wf-ba__col.has .wf-ba__tools { display: inline-flex; }
    .wf-ba__mini {
        border: 1px solid #A66019; background: #FFF7EE; color: #5C2E15;
        font-size: 11px; font-weight: 700; padding: 5px 10px; border-radius: 999px;
        cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: .15s;
    }
    .wf-ba__mini:hover { background: #FAF0E2; }
    .wf-ba__mini--del { border-color: #E0D2C1; background: #fff; color: #7C2929; padding: 5px 9px; }
    .wf-ba__mini--del:hover { background: #FCEBEB; border-color: #A32D2D; }
    .wf-ba__status { font-size: 10.5px; color: #6B4423; font-weight: 600; }

    /* Kartu besar pilih jenis checklist (gaya halaman Setup) */
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
    /* Grup kanan sebaris dgn kartu: tombol Riwayat (handoff) */
    .cl-cad-tabs__right { margin-left: auto; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .cl-btn-riwayat {
        display: inline-flex; align-items: center; gap: 7px;
        background: #FFFFFF; color: #5A3A22; border: 1px solid #E7D3BE;
        border-radius: 11px; padding: 10px 16px; font-weight: 700; font-size: 12.5px;
        text-decoration: none; transition: filter .15s;
    }
    .cl-btn-riwayat:hover { filter: brightness(.97); color: #5A3A22; text-decoration: none; }
    /* Mobile: kartu jenis full-width */
    @media (max-width: 600px) {
        .cl-cad-tab { flex: 1 1 100%; min-width: 0; }
        .cl-cad-tabs__right { margin-left: 0; width: 100%; }
        .cl-btn-riwayat { flex: 1; justify-content: center; }
    }
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
        @include('crud.checklist.my._marquee')

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
            {{-- ─── PILIH JENIS CHECKLIST (kartu besar, gaya Setup) ─── --}}
            <div class="cl-cad-tabs" style="margin-bottom:14px;">
                <a href="{{ route('mychecklist') }}" class="cl-cad-tab cl-cad-tab--daily">
                    <span class="cl-cad-tab__icon"><i class="fas fa-sun"></i></span>
                    <span class="cl-cad-tab__body">
                        <strong>Checklist Harian</strong>
                        <small>Isi setiap hari operasional</small>
                    </span>
                    <i class="fas fa-check-circle cl-cad-tab__check"></i>
                </a>
                <a href="{{ route('mychecklist.weekform') }}" class="cl-cad-tab cl-cad-tab--weekly is-active">
                    <span class="cl-cad-tab__icon"><i class="fas fa-calendar-week"></i></span>
                    <span class="cl-cad-tab__body">
                        <strong>Checklist Mingguan</strong>
                        <small>Tugas terjadwal per minggu</small>
                    </span>
                    <i class="fas fa-check-circle cl-cad-tab__check"></i>
                </a>
                <div class="cl-cad-tabs__right">
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
                    <a href="{{ route('mychecklist.history', ['type' => 'weekly']) }}" class="cl-btn-riwayat">
                        <i class="fas fa-history"></i> Riwayat Checklist
                    </a>
                </div>
            </div>

            {{-- HERO --}}
            @php
                $statusOpt = $submission
                    ? (\App\Models\ChecklistWeeklySubmission::statusOptions()[$submission->status] ?? null)
                    : null;
                $heroStatusColor = $statusOpt
                    ? (['success'=>'success','warning'=>'warning','danger'=>'danger','coffee'=>'coffee','slate'=>'slate','neutral'=>'draft','info'=>'warning'][$statusOpt['color']] ?? 'draft')
                    : 'draft';
                $wfPctClass = ($progress['pct'] ?? 0) >= 80 ? 'is-good' : (($progress['pct'] ?? 0) >= 50 ? 'is-warn' : 'is-bad');
            @endphp
            <div class="wf-hero">
                <div class="wf-hero__row">
                    <div class="wf-hero__icon"><i class="fas fa-calendar-week"></i></div>
                    <div style="min-width:0; flex:1;">
                        <h5 class="wf-hero__title">Form Mingguan</h5>
                        {{-- Sub + pill divisi/cabang SATU baris di bawah judul (ikut handoff) --}}
                        <div class="wf-hero__meta">
                            <span class="wf-hero__sub">Daftar tugas per hari dalam satu minggu kerja</span>
                            <span class="wf-hero__chip">{{ $position->division?->name ?? $position->division_id }}</span>
                            @if($position->branch)
                                <span class="wf-hero__chip">{{ $position->branch->name }}</span>
                            @endif
                        </div>
                    </div>
                    @if($submission && $submission->status !== \App\Models\ChecklistWeeklySubmission::STATUS_DRAFT && $statusOpt)
                        <span class="wf-hero__status wf-hero__status--{{ $heroStatusColor }}" style="margin-left:auto;">
                            @if($submission->status === 'approved')<i class="fas fa-check-circle"></i>
                            @elseif($submission->status === 'rejected')<i class="fas fa-times-circle"></i>
                            @else<i class="fas fa-clock"></i>
                            @endif
                            {{ $statusOpt['label'] }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- TOOLBAR SATU BARIS (handoff): pager + label minggu | % + bar | hitungan --}}
            <div class="wf-toolbar">
                <div class="wf-nav">
                    <a href="{{ route('mychecklist.weekform', ['week' => $weekOffset - 1]) }}" class="wf-nav__btn" title="Minggu sebelumnya">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <div>
                        <h5 class="wf-nav__title">
                            Minggu {{ $weekStart->translatedFormat('d M') }} – {{ $weekEnd->translatedFormat('d M Y') }}
                        </h5>
                        <div class="wf-nav__sub">
                            @if($weekOffset === 0)
                                Minggu ini
                            @elseif($weekOffset === -1)
                                Minggu lalu
                            @elseif($weekOffset === 1)
                                Minggu depan
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

                @if($submission && $progress['total'] > 0)
                    <div class="wf-toolbar__sep"></div>
                    <div class="wf-progress {{ $wfPctClass }}" id="wfProgressCard">
                        <div class="wf-progress__big" id="wfPct">{{ $progress['pct'] }}<small>%</small></div>
                        <div class="wf-progress__body">
                            <div class="wf-progress__label">Progres Minggu Ini</div>
                            <div class="wf-progress__bar"><i id="wfFill" style="width:{{ $progress['pct'] }}%"></i></div>
                        </div>
                        <div class="wf-progress__count" id="wfCount">
                            <strong>{{ $progress['done'] }}</strong> dari {{ $progress['total'] }} tugas
                            <small>· {{ max(0, $progress['total'] - $progress['done']) }} belum</small>
                        </div>
                    </div>
                @endif
            </div>

            {{-- BANNERS (status form) — di bawah toolbar --}}
            @if($isFuture)
                <div class="wf-banner wf-banner--info">
                    <i class="fas fa-info-circle"></i>
                    <div><strong>Minggu di masa depan.</strong> Form belum dibuat — kembali ke minggu ini untuk mulai mengisi.</div>
                </div>
            @endif

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
            @endif

            {{-- DAY-BY-DAY LIST --}}
            @if(! $submission || $progress['total'] === 0)
                <div style="background:#FFFFFF; border:1px solid #E0D2C1; border-radius:14px; padding:42px 22px; text-align:center;">
                    <div style="display:inline-flex; align-items:center; justify-content:center; width:70px; height:70px; border-radius:50%; background:#FAF4EC; color:#9B5E2C; font-size:26px; margin-bottom:14px;">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <h5 style="font-size:16px; font-weight:700; color:#2D1810; margin-bottom:6px;">
                        Belum ada tugas mingguan
                    </h5>
                    <p style="font-size:13px; color:#6B4423; max-width:480px; margin:0 auto; line-height:1.55;">
                        Supervisor belum menambahkan <strong>tugas mingguan</strong> untuk divisi <strong>{{ $position->division?->name }}</strong>.
                        Form akan otomatis aktif setelah tugas mingguan diisi di menu Setup.
                    </p>
                </div>
            @else
                <div style="display:flex; justify-content:flex-end; margin-bottom:10px;">
                    <button type="button" class="st-btn-outline-brown" id="wfDayExpandAll" data-mode="expand" style="padding:6px 12px; font-size:12px;">
                        <i class="fas fa-angle-double-down"></i> <span data-label>Buka semua</span>
                    </button>
                </div>
                <div class="wf-day-list">
                    @foreach($days as $day)
                        @php
                            $dayItems = $grouped->get($day->iso, collect());
                            $totalDay = $dayItems->count();
                            // "beres" = dicentang ATAU dikecualikan per-item.
                            $doneDay  = $dayItems->filter(fn ($i) => $i->is_done || $i->is_excepted)->count();
                            $isComplete = $totalDay > 0 && $doneDay === $totalDay;
                            $isDayExcused = array_key_exists($day->iso, $excusedDays);
                            $dayExcuseReason = $excusedDays[$day->iso] ?? null;
                            $canDayExcuse = $submission && $submission->isEditable() && $weekOffset === 0 && $totalDay > 0;
                            $dayClass = '';
                            if ($day->is_today) $dayClass .= ' is-today';
                            elseif ($day->is_past) $dayClass .= ' is-past';
                            if ($totalDay === 0) $dayClass .= ' is-empty';
                            if ($isDayExcused) $dayClass .= ' is-excused';
                        @endphp
                        <div class="wf-day {{ $dayClass }}">
                            <div class="wf-day__head">
                                {{-- Kotak tanggal ala handoff: bulan kecil di atas, angka besar di bawah --}}
                                <div class="wf-day__num">
                                    <small>{{ $day->date->translatedFormat('M') }}</small>
                                    <strong>{{ $day->date->format('d') }}</strong>
                                </div>
                                <div class="wf-day__info">
                                    <h6 class="wf-day__title">
                                        {{ $day->label }}
                                        @if($day->is_today)
                                            <span class="badge-today">Hari Ini</span>
                                        @endif
                                    </h6>
                                    <div class="wf-day__meta">{{ $day->date->translatedFormat('d F Y') }}</div>
                                </div>
                                {{-- Pill hitung di kanan (sebelum caret) — ikut handoff --}}
                                @if($isDayExcused)
                                    <span class="wf-day__pill wf-day__pill--excused">Dikecualikan</span>
                                @elseif($totalDay > 0)
                                    <span class="wf-day__pill wf-day__pill--{{ $isComplete ? 'good' : 'warn' }}" data-day-pill>
                                        @if($isComplete)<i class="fas fa-check" style="font-size:9px;"></i>@endif
                                        {{ $doneDay }}/{{ $totalDay }} tugas
                                    </span>
                                @else
                                    <span class="wf-day__pill wf-day__pill--neutral">Tidak ada tugas</span>
                                @endif
                                <i class="fas fa-chevron-down wf-day__caret"></i>
                            </div>
                            <div class="wf-day__body">
                                <div class="wf-day__body-inner">
                                @if($totalDay === 0)
                                    <div class="wf-day__empty">Tidak ada tugas terjadwal di hari ini.</div>
                                @else
                                    @foreach($dayItems as $item)
                                            <div class="wf-task {{ $item->is_done ? 'is-done' : '' }} {{ $item->is_excepted && ! $isDayExcused ? 'is-excepted' : '' }} {{ $submission->isEditable() && ! $isDayExcused ? '' : 'is-locked' }} {{ $isDayExcused ? 'is-excused' : '' }} {{ ! $isDayExcused && ! $item->is_excepted && $item->requires_photo && ! $item->photo_before_after && ! $item->photo_path ? 'needs-photo' : '' }}"
                                                 data-item-id="{{ $item->id }}"
                                                 data-toggle-url="{{ route('mychecklist.weekform.item.toggle', $item->id) }}"
                                                 @if($submission->isEditable() && ! $isDayExcused)
                                                 data-except-url="{{ route('mychecklist.weekform.item.except', $item->id) }}"
                                                 data-unexcept-url="{{ route('mychecklist.weekform.item.unexcept', $item->id) }}"
                                                 @endif
                                                 data-editable="{{ $submission->isEditable() && ! $isDayExcused ? '1' : '0' }}"
                                                 data-requires-photo="{{ $item->requires_photo && ! $item->photo_before_after ? '1' : '0' }}"
                                                 @if($item->requires_photo && ! $item->photo_before_after)
                                                 data-photo-upload-url="{{ route('mychecklist.weekform.item.photo.upload', $item->id) }}"
                                                 data-photo-delete-url="{{ route('mychecklist.weekform.item.photo.delete', $item->id) }}"
                                                 data-has-photo="{{ $item->photo_path ? '1' : '0' }}"
                                                 @endif>
                                                <span class="wf-task__check"></span>
                                                <div class="wf-task__body">
                                                    <div class="wf-task__name">
                                                        {{ $item->task_title_snapshot }}
                                                        @if($item->requires_photo && ! $item->photo_before_after)
                                                            <span class="wf-task__badge"><i class="fas fa-camera"></i> Perlu foto</span>
                                                        @endif
                                                    </div>
                                                    @if($item->photo_before_after)
                                                        <div class="wf-ba" data-ba>
                                                            @foreach(['before' => 'Before', 'after' => 'After'] as $slot => $slotLabel)
                                                                @php $baPath = $item->{'photo_' . $slot . '_path'}; @endphp
                                                                <div class="wf-ba__col {{ $baPath ? 'has' : '' }}" data-ba-slot="{{ $slot }}"
                                                                     data-upload-url="{{ route('mychecklist.weekform.item.baphoto.upload', ['id' => $item->id, 'slot' => $slot]) }}"
                                                                     data-delete-url="{{ route('mychecklist.weekform.item.baphoto.delete', ['id' => $item->id, 'slot' => $slot]) }}">
                                                                    <input type="file" accept="image/*" capture="environment" class="wf-ba__file" hidden>
                                                                    <button type="button" class="wf-ba__pill {{ $baPath ? 'has' : '' }}" data-ba-thumb
                                                                            data-url="{{ $baPath ? asset($baPath) : '' }}">
                                                                        <i class="fas fa-{{ $baPath ? 'image' : 'camera' }}" data-ba-icon></i>
                                                                        <span class="wf-ba__pill-cap">{{ $slotLabel }}</span>
                                                                        <span data-ba-state>@if($baPath)Lihat @else{{ $submission->isEditable() ? 'Ambil foto' : 'Tidak ada' }}@endif</span>
                                                                    </button>
                                                                    @if($submission->isEditable())
                                                                        <div class="wf-ba__tools">
                                                                            <button type="button" class="wf-ba__mini" data-ba-take>
                                                                                <i class="fas fa-camera"></i> <span data-ba-label>{{ $baPath ? 'Ganti' : 'Ambil' }}</span>
                                                                            </button>
                                                                            <button type="button" class="wf-ba__mini wf-ba__mini--del" data-ba-remove>
                                                                                <i class="fas fa-trash"></i>
                                                                            </button>
                                                                        </div>
                                                                        <span class="wf-ba__status" data-ba-status></span>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @elseif($item->requires_photo)
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

                                                    {{-- Flag "Dikecualikan" per-item (tampil bila item dikecualikan
                                                         & harinya tidak dikecualikan penuh). --}}
                                                    @if($item->is_excepted && ! $isDayExcused)
                                                        <div class="cl-xcpt__flag" data-xcpt-flag>
                                                            <span class="cl-xcpt__pill"><i class="fas fa-ban"></i> Dikecualikan</span>
                                                            <span class="cl-xcpt__reason" data-xcpt-reason>{{ $item->exception_reason }}</span>
                                                            @if($submission->isEditable() && ! $isDayExcused)
                                                                <button type="button" class="cl-xcpt__undo" data-xcpt-undo>Batalkan</button>
                                                            @endif
                                                        </div>
                                                    @elseif($submission->isEditable() && ! $isDayExcused)
                                                        <div class="cl-xcpt__flag" data-xcpt-flag hidden>
                                                            <span class="cl-xcpt__pill"><i class="fas fa-ban"></i> Dikecualikan</span>
                                                            <span class="cl-xcpt__reason" data-xcpt-reason></span>
                                                            <button type="button" class="cl-xcpt__undo" data-xcpt-undo>Batalkan</button>
                                                        </div>
                                                    @endif

                                                    {{-- Tombol + form pengecualian (hanya editable & hari tidak dikecualikan). --}}
                                                    @if($submission->isEditable() && ! $isDayExcused)
                                                        <button type="button" class="cl-xcpt__open" data-xcpt-open
                                                                title="Tandai pertanyaan ini tidak perlu diisi minggu ini">
                                                            <i class="fas fa-ban"></i> <span>Kecualikan</span>
                                                        </button>
                                                        <div class="cl-xcpt__form" data-xcpt-form hidden>
                                                            <div class="cl-xcpt__label">Tandai <b>Dikecualikan</b> — pertanyaan ini tak berlaku/tak terjadi minggu ini. Tetap dihitung 100%.</div>
                                                            <div class="cl-xcpt__chips">
                                                                <button type="button" class="cl-xcpt__chip" data-xcpt-preset="Tidak terjadi minggu ini">Tidak terjadi minggu ini</button>
                                                                <button type="button" class="cl-xcpt__chip" data-xcpt-preset="Alat/ruangan tidak dipakai">Alat/ruangan tidak dipakai</button>
                                                                <button type="button" class="cl-xcpt__chip" data-xcpt-preset="Tidak ada kegiatan terkait">Tidak ada kegiatan terkait</button>
                                                            </div>
                                                            <textarea class="cl-xcpt__input" rows="2" maxlength="500" placeholder="Alasan pengecualian…"></textarea>
                                                            <div class="cl-xcpt__actions">
                                                                <button type="button" class="cl-xcpt__cancel" data-xcpt-cancel>Batal</button>
                                                                <button type="button" class="cl-xcpt__save" data-xcpt-save disabled><i class="fas fa-ban"></i> Tandai Dikecualikan</button>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                @if($item->done_at && ! $item->is_excepted)
                                                    <div class="wf-task__time">selesai</div>
                                                @endif
                                            </div>
                                    @endforeach
                                @endif

                                @if($canDayExcuse)
                                    @if($isDayExcused)
                                        <div class="wf-day-excused">
                                            <div class="wf-day-excused__text">
                                                <i class="fas fa-mug-hot"></i>
                                                <span>Hari ini dikecualikan — <strong>{{ $dayExcuseReason }}</strong></span>
                                            </div>
                                            <form method="POST" action="{{ route('mychecklist.weekform.day.unexcuse') }}"
                                                  data-confirm="Batalkan pengecualian dan isi {{ $day->label }} seperti biasa?">
                                                @csrf
                                                <input type="hidden" name="day_of_week" value="{{ $day->iso }}">
                                                <button type="submit" class="wf-day-excused__undo">
                                                    <i class="fas fa-undo"></i> Batalkan, isi hari ini
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <div class="cl-excuse cl-excuse--day">
                                            <button type="button" class="cl-excuse__trigger" data-excuse-toggle>
                                                <span class="cl-excuse__trigger-label"><i class="fas fa-mug-hot"></i> Hari ini tidak perlu diisi?</span>
                                                <i class="fas fa-chevron-down cl-excuse__caret"></i>
                                            </button>
                                            <div class="cl-excuse__reveal">
                                                <div class="cl-excuse__reveal-inner">
                                                    <form method="POST" action="{{ route('mychecklist.weekform.day.excuse') }}" class="cl-excuse__form"
                                                          data-confirm="Tandai {{ $day->label }} sebagai tidak perlu diisi?">
                                                        @csrf
                                                        <input type="hidden" name="day_of_week" value="{{ $day->iso }}">
                                                        <div class="cl-excuse__label">Pilih / tulis alasan (wajib):</div>
                                                        <div class="cl-excuse__chips">
                                                            <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Klinik tutup';">Klinik tutup</button>
                                                            <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Libur';">Libur</button>
                                                            <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Tidak ada pasien';">Tidak ada pasien</button>
                                                            <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Cuti';">Cuti</button>
                                                        </div>
                                                        <textarea name="excuse_reason" class="cl-excuse__input" rows="2" maxlength="500" required
                                                                  placeholder="Contoh: libur / tanggal merah…"></textarea>
                                                        <button type="submit" class="cl-excuse__submit"><i class="fas fa-check"></i> Tandai hari ini tidak perlu</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- SUBMIT FOOTER --}}
                @if($submission->isEditable() && $weekOffset === 0)
                    <form id="wfSubmitForm" method="POST" action="{{ route('mychecklist.weekform.submit') }}"
                          style="display:none;"
                          data-confirm="Submit Form Mingguan minggu ini? Setelah submit, tidak bisa edit sampai supervisor review.">
                        @csrf
                    </form>
                    <div class="wf-submit">
                        @php $wfComplete = $progress['total'] > 0 && $progress['done'] === $progress['total']; @endphp
                        <div class="wf-submit__top">
                            {{-- Kalimat mengikuti handoff: headline tebal + sub redup. --}}
                            <div class="wf-submit__text">
                                <div class="wf-submit__headline" id="wfHint">
                                    @if($wfComplete)
                                        Semua tugas minggu ini terpenuhi — siap dikirim!
                                    @else
                                        Selesaikan semua tugas dulu.
                                    @endif
                                </div>
                                <div class="wf-submit__sub">Form Mingguan bisa disubmit setelah semua tugas selesai atau harinya dikecualikan.</div>
                            </div>
                            <button type="submit" form="wfSubmitForm" class="wf-submit__btn" id="wfSubmitBtn" {{ $wfComplete ? '' : 'disabled' }}>
                                @if($wfComplete)
                                    <i class="fas fa-paper-plane"></i> Kirim Form Mingguan
                                @else
                                    <i class="fas fa-paper-plane"></i> Belum 100%
                                @endif
                            </button>
                        </div>
                        <div class="wf-submit__note">
                            <textarea id="wfNote" name="note" form="wfSubmitForm" rows="2" maxlength="1000"
                                      placeholder="Catatan untuk supervisor (opsional) — mis. hari Kamis alat pel rusak, sudah lapor ke admin cabang…"></textarea>
                        </div>
                    </div>

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
                        .cl-excuse__chip { font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 20px; border: 1px dashed #D9C4A6; background: #FBF6EE; color: #8A5A2B; cursor: pointer; transition: .15s; font-family: inherit; }
                        .cl-excuse__chip:hover { filter: brightness(.97); }
                        .cl-excuse__input { width: 100%; box-sizing: border-box; border: 1px solid #E0D2C1; border-radius: 8px; padding: 8px 10px; font-size: 13px; color: #2D1810; font-family: inherit; resize: vertical; }
                        .cl-excuse__input:focus { outline: none; border-color: #A66019; box-shadow: 0 0 0 3px rgba(166,96,25,.10); }
                        .cl-excuse__submit { margin-top: 10px; border: none; border-radius: 8px; background: linear-gradient(180deg,#5C2E15,#471500); color: #fff; font-size: 13px; font-weight: 700; padding: 9px 16px; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; }
                        .cl-excuse__submit:hover { filter: brightness(1.08); }
                        /* Varian per-hari: tampil di dalam kartu hari (lebih ringkas). */
                        .cl-excuse--day { margin: 10px 0 2px; }
                        .cl-excuse--day .cl-excuse__trigger { width: 100%; background: transparent; border: none; font-family: inherit; padding: 11px 14px; font-size: 12.5px; }
                        /* Dropdown halus: pakai trik grid-rows 0fr↔1fr seperti accordion hari. */
                        .cl-excuse--day .cl-excuse__reveal { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .28s cubic-bezier(.4,0,.2,1); }
                        .cl-excuse--day .cl-excuse__reveal-inner { overflow: hidden; min-height: 0; }
                        .cl-excuse--day.is-open .cl-excuse__reveal { grid-template-rows: 1fr; }
                        .cl-excuse--day.is-open .cl-excuse__trigger { border-radius: 12px 12px 0 0; border-bottom: 1px solid #EFE1CF; background: #FAF0E2; }
                        .cl-excuse--day.is-open .cl-excuse__caret { transform: rotate(180deg); }
                        /* Banner "hari ini dikecualikan" + tombol batalkan. */
                        .wf-day-excused { margin: 10px 0 2px; padding: 12px 14px; border: 1px solid #E7D3B8; border-left: 4px solid #A66019; border-radius: 10px; background: #FCF4E7; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
                        .wf-day-excused__text { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; color: #6B4423; }
                        .wf-day-excused__text i { color: #A66019; }
                        .wf-day-excused__undo { border: 1px solid #C9A26B; border-radius: 8px; background: #fff; color: #5C2E15; font-size: 12px; font-weight: 700; padding: 7px 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
                        .wf-day-excused__undo:hover { background: #FAF0E2; }
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
        // Klik area task (check/judul) → toggle. Foto bersifat opsional, jadi
        // centang tidak pernah diblokir walau foto belum diambil.
        task.addEventListener('click', () => {
            if (task.dataset.editable !== '1') return;
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

        // Kolom foto before/after (divisi GS) — opsional, tidak men-toggle task.
        const editable = task.dataset.editable === '1';
        task.querySelectorAll('[data-ba-slot]').forEach(col => {
            col.addEventListener('click', e => e.stopPropagation());
            const fileInput = col.querySelector('.wf-ba__file');
            const takeBtn   = col.querySelector('[data-ba-take]');
            const removeBtn = col.querySelector('[data-ba-remove]');
            const thumb     = col.querySelector('[data-ba-thumb]');
            if (takeBtn && fileInput) takeBtn.addEventListener('click', () => fileInput.click());
            if (thumb) thumb.addEventListener('click', () => {
                if (thumb.dataset.url) openPhotoModal(thumb.dataset.url);
                else if (editable && fileInput) fileInput.click();
            });
            if (fileInput) fileInput.addEventListener('change', () => {
                if (fileInput.files && fileInput.files[0]) uploadBaPhoto(col, fileInput.files[0]);
                fileInput.value = '';
            });
            if (removeBtn) removeBtn.addEventListener('click', () => removeBaPhoto(col));
        });

        // ── Pengecualian per-item ──
        // Semua kontrol pengecualian stopPropagation supaya klik tak men-toggle.
        task.querySelectorAll('[data-xcpt-open], [data-xcpt-form], [data-xcpt-flag]').forEach(el => {
            el.addEventListener('click', e => e.stopPropagation());
        });
        const xForm = task.querySelector('[data-xcpt-form]');
        if (xForm) {
            const xOpen   = task.querySelector('[data-xcpt-open]');
            const xInput  = xForm.querySelector('.cl-xcpt__input');
            const xSave   = xForm.querySelector('[data-xcpt-save]');
            const xCancel = xForm.querySelector('[data-xcpt-cancel]');
            const xChips  = xForm.querySelectorAll('[data-xcpt-preset]');
            const xUndo   = task.querySelector('[data-xcpt-undo]');

            const xSync = () => {
                xSave.disabled = xInput.value.trim() === '';
                xChips.forEach(c => c.classList.toggle('is-active', c.getAttribute('data-xcpt-preset') === xInput.value.trim()));
            };
            if (xOpen)   xOpen.addEventListener('click',   () => { xForm.hidden = false; xInput.focus(); });
            if (xCancel) xCancel.addEventListener('click', () => { xForm.hidden = true; xInput.value = ''; xSync(); });
            xChips.forEach(c => c.addEventListener('click', () => { xInput.value = c.getAttribute('data-xcpt-preset'); xSync(); }));
            xInput.addEventListener('input', xSync);
            xSync();

            if (xSave) xSave.addEventListener('click', async () => {
                const reason = xInput.value.trim();
                if (! reason) return;
                xSave.disabled = true;
                try {
                    const res = await fetch(task.dataset.exceptUrl, {
                        method: 'PATCH',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ reason }),
                    });
                    const data = await res.json();
                    if (! data.ok) throw new Error(data.error || 'Gagal');
                    task.classList.remove('is-done');
                    setExceptedUI(task, true, data.reason || reason);
                    xForm.hidden = true;
                    updateDayCounter(task);
                    updateGlobal(data.progress);
                } catch (err) {
                    xSync();
                    alert('Gagal menyimpan pengecualian: ' + (err.message || err));
                }
            });

            if (xUndo) xUndo.addEventListener('click', async () => {
                xUndo.disabled = true;
                try {
                    const res = await fetch(task.dataset.unexceptUrl, {
                        method: 'PATCH',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
                    });
                    const data = await res.json();
                    if (! data.ok) throw new Error(data.error || 'Gagal');
                    setExceptedUI(task, false);
                    xInput.value = ''; xSync();
                    updateDayCounter(task);
                    updateGlobal(data.progress);
                } catch (err) {
                    xUndo.disabled = false;
                    alert('Gagal membatalkan pengecualian: ' + (err.message || err));
                }
            });
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

    // Tampilkan/sembunyikan state "Dikecualikan" pada satu item (class + flag).
    function setExceptedUI(task, on, reason) {
        task.classList.toggle('is-excepted', on);
        const flag = task.querySelector('[data-xcpt-flag]');
        if (flag) {
            flag.hidden = ! on;
            if (on && typeof reason === 'string') {
                const r = flag.querySelector('[data-xcpt-reason]');
                if (r) r.textContent = reason;
            }
        }
    }

    async function toggleTask(task) {
        const url = task.dataset.toggleUrl;
        const wasDone = task.classList.contains('is-done');
        const wasExcepted = task.classList.contains('is-excepted');
        task.classList.toggle('is-done');
        if (task.classList.contains('is-done') && wasExcepted) setExceptedUI(task, false);
        updateDayCounter(task);
        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
            });
            const data = await res.json();
            if (! data.ok) throw new Error(data.error || 'Gagal toggle');
            task.classList.toggle('is-done', data.is_done);
            if (data.is_done) setExceptedUI(task, false);
            updateGlobal(data.progress);
        } catch (err) {
            task.classList.toggle('is-done', wasDone);
            if (wasExcepted) setExceptedUI(task, true);
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
        if (! confirm('Hapus foto ini?')) return;
        try {
            const res = await fetch(task.dataset.photoDeleteUrl, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
            });
            const data = await res.json();
            if (! data.ok) throw new Error(data.error || 'Gagal hapus');
            clearPhoto(task);
            // Foto opsional → status centang task tidak berubah saat foto dihapus.
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

    // ── Foto before/after (divisi GS): opsional, tak mengubah status task ──
    async function uploadBaPhoto(col, file) {
        const status = col.querySelector('[data-ba-status]');
        const setStatus = t => { if (status) status.textContent = t; };
        setStatus('Mengompres…');
        let blob;
        try { blob = await resizeImage(file, 1600, 0.8); }
        catch (e) { blob = file; }
        const fd = new FormData();
        fd.append('photo', blob, 'foto.jpg');
        setStatus('Mengunggah…');
        try {
            const res = await fetch(col.dataset.uploadUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: fd,
            });
            const data = await res.json();
            if (! data.ok) throw new Error(data.error || 'Upload gagal');
            setBaPhoto(col, data.url);
            setStatus('Tersimpan ✓');
            setTimeout(() => setStatus(''), 1500);
        } catch (err) {
            setStatus('');
            alert('Gagal: ' + (err.message || err));
        }
    }

    async function removeBaPhoto(col) {
        if (! confirm('Hapus foto ini?')) return;
        try {
            const res = await fetch(col.dataset.deleteUrl, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
            });
            const data = await res.json();
            if (! data.ok) throw new Error(data.error || 'Gagal hapus');
            clearBaPhoto(col);
        } catch (err) {
            alert('Gagal: ' + (err.message || err));
        }
    }

    function setBaPhoto(col, url) {
        const pill  = col.querySelector('[data-ba-thumb]');
        const icon  = col.querySelector('[data-ba-icon]');
        const state = col.querySelector('[data-ba-state]');
        const label = col.querySelector('[data-ba-label]');
        col.classList.add('has');
        if (pill)  { pill.dataset.url = url; pill.classList.add('has'); }
        if (icon)  { icon.classList.remove('fa-camera'); icon.classList.add('fa-image'); }
        if (state) state.textContent = 'Lihat';
        if (label) label.textContent = 'Ganti';
    }

    function clearBaPhoto(col) {
        const pill  = col.querySelector('[data-ba-thumb]');
        const icon  = col.querySelector('[data-ba-icon]');
        const state = col.querySelector('[data-ba-state]');
        const label = col.querySelector('[data-ba-label]');
        col.classList.remove('has');
        if (pill)  { pill.dataset.url = ''; pill.classList.remove('has'); }
        if (icon)  { icon.classList.remove('fa-image'); icon.classList.add('fa-camera'); }
        if (state) state.textContent = 'Ambil foto';
        if (label) label.textContent = 'Ambil';
    }

    function updateDayCounter(task) {
        const dayCard = task.closest('.wf-day');
        if (! dayCard) return;
        const all  = dayCard.querySelectorAll('[data-item-id]');
        // "beres" = dicentang ATAU dikecualikan.
        const done = dayCard.querySelectorAll('[data-item-id].is-done, [data-item-id].is-excepted');
        const total = all.length;
        const cnt = done.length;
        const complete = cnt === total && total > 0;
        const chip = dayCard.querySelector('[data-day-pill]');
        if (chip) {
            chip.innerHTML = (complete ? '<i class="fas fa-check" style="font-size:9px;"></i> ' : '') + cnt + '/' + total + ' tugas';
            chip.classList.toggle('wf-day__pill--good', complete);
            chip.classList.toggle('wf-day__pill--warn', ! complete);
        }
    }

    const wfProgressCard = document.getElementById('wfProgressCard');
    function updateGlobal(p) {
        if (! p) return;
        if (pctEl)   pctEl.innerHTML   = p.pct + '<small>%</small>';
        if (fillEl)  fillEl.style.width = p.pct + '%';
        if (wfProgressCard) {
            const cls = p.pct >= 80 ? 'is-good' : (p.pct >= 50 ? 'is-warn' : 'is-bad');
            wfProgressCard.classList.remove('is-good', 'is-warn', 'is-bad');
            wfProgressCard.classList.add(cls);
        }
        if (countEl) {
            const remaining = Math.max(0, p.total - p.done);
            countEl.innerHTML = '<strong>' + p.done + '</strong> dari ' + p.total + ' tugas <small>· ' + remaining + ' belum</small>';
        }
        const complete = p.total > 0 && p.done === p.total;
        if (submitBtn) {
            submitBtn.disabled = ! complete;
            submitBtn.innerHTML = complete
                ? '<i class="fas fa-paper-plane"></i> Kirim Form Mingguan'
                : '<i class="fas fa-paper-plane"></i> Belum 100%';
        }
        if (hintEl) {
            // Headline mengikuti handoff — sub di bawahnya statis.
            hintEl.textContent = complete
                ? 'Semua tugas minggu ini terpenuhi — siap dikirim!'
                : 'Selesaikan semua tugas dulu.';
        }
    }
})();
</script>
@endif

{{-- Accordion hari: klik = buka/tutup hari itu saja (boleh banyak terbuka).
     Tombol "Buka semua / Tutup semua" untuk sekali klik. --}}
<script>
(function () {
    // Dropdown "Hari ini tidak perlu diisi?" — buka/tutup halus (grid-rows).
    document.querySelectorAll('[data-excuse-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = btn.closest('.cl-excuse--day');
            if (box) box.classList.toggle('is-open');
        });
    });
    function collapseExcuses(scope) {
        scope.querySelectorAll('.cl-excuse--day.is-open').forEach(function (c) {
            c.classList.remove('is-open');
        });
    }

    var days = Array.prototype.slice.call(document.querySelectorAll('.wf-day'));
    if (! days.length) return;

    // Default terbuka: hari ini → kalau tak ada, hari pertama yang ada task-nya
    // → kalau tak ada, hari pertama.
    var open = days.find(function (d) { return d.classList.contains('is-today'); })
        || days.find(function (d) { return ! d.classList.contains('is-empty'); })
        || days[0];

    days.forEach(function (d) { d.classList.toggle('is-collapsed', d !== open); });

    var btn = document.getElementById('wfDayExpandAll');
    function syncExpandAll() {
        if (! btn) return;
        var anyCollapsed = days.some(function (d) { return d.classList.contains('is-collapsed'); });
        btn.dataset.mode = anyCollapsed ? 'expand' : 'collapse';
        var lbl = btn.querySelector('[data-label]');
        if (lbl) lbl.textContent = anyCollapsed ? 'Buka semua' : 'Tutup semua';
        var ic = btn.querySelector('i');
        if (ic) ic.className = anyCollapsed ? 'fas fa-angle-double-down' : 'fas fa-angle-double-up';
    }

    days.forEach(function (day) {
        var head = day.querySelector('.wf-day__head');
        if (! head) return;
        head.addEventListener('click', function () {
            day.classList.toggle('is-collapsed');
            // Hari ditutup → tutup juga dropdown "tidak perlu diisi" di dalamnya,
            // supaya saat dibuka lagi mulai dari keadaan tertutup.
            if (day.classList.contains('is-collapsed')) collapseExcuses(day);
            syncExpandAll();
        });
    });

    if (btn) {
        btn.addEventListener('click', function () {
            var expand = btn.dataset.mode === 'expand';
            days.forEach(function (d) { d.classList.toggle('is-collapsed', ! expand); });
            if (! expand) collapseExcuses(document);
            syncExpandAll();
        });
        syncExpandAll();
    }
})();
</script>

@include('crud.checklist.my._confirm_modal')
@endsection
