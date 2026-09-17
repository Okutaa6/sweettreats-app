@extends('layouts.app')

@section('css')
<style>
    .wf-wrap { max-width: 1240px; margin: 0 auto; padding-bottom: 32px; }
    /* Hero ala handoff: gradient 120deg + aksen kiri teal (identitas Mingguan),
       ikon tile teal 44px, judul + (sub & pill dalam SATU baris di bawahnya) */
    .wf-hero {
        background: linear-gradient(120deg, #5C2E15, #471500);
        color: #FFFFFF;
        border-radius: 18px;
        border-left: 5px solid #1F6E5F;
        padding: 24px 28px;
        margin-bottom: 16px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(70,21,0,.22);
    }
    .wf-hero__row {
        display: flex; align-items: center;
        gap: 18px; flex-wrap: wrap; position: relative; z-index: 1;
    }
    .wf-hero__icon {
        width: 50px; height: 50px; flex-shrink: 0;
        border-radius: 14px;
        background: linear-gradient(135deg, #1F6E5F, #155148);
        display: flex; align-items: center; justify-content: center;
        color: #DFF3EC; font-size: 20px;
    }
    .wf-hero__title { margin: 0; font-size: 20px; font-weight: 800; color: #FBF1E7; letter-spacing: -0.01em; }
    .wf-hero__meta {
        display: flex; gap: 9px; row-gap: 8px; flex-wrap: wrap; align-items: center;
        margin-top: 8px;
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
    /* Mobile: pisahkan nav (1 baris penuh) & progres (di bawahnya). Hitungan
       "N dari N tugas · N belum" turun ke baris sendiri biar tak kepotong —
       sebelumnya nowrap + body min-width:140 bikin overflow di layar sempit. */
    @media (max-width: 640px) {
        .wf-toolbar { padding: 14px 16px; }
        .wf-toolbar__sep { display: none; }
        .wf-nav { width: 100%; justify-content: space-between; }
        .wf-progress { min-width: 100%; flex-wrap: wrap; row-gap: 10px; }
        .wf-progress__body { min-width: 0; }
        .wf-progress__count { white-space: normal; flex-basis: 100%; text-align: left; }
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
    .wf-day__pill--danger { background: #FBE3DE; color: #C0392B; }

    /* Panel status per-hari (kirim / menunggu review / disetujui / ditolak) — SELALU termuat
       walau minggu sudah tak editable, supaya tampilan approved/ditolak tetap berkartu. */
    .wf-day-submit { margin: 12px 0 2px; padding: 12px 14px; border: 1px solid #E0D2C1; border-radius: 10px; background: #FFFDF9; display: flex; flex-direction: column; gap: 10px; }
    .wf-day-submit__note { width: 100%; box-sizing: border-box; border: 1px solid #E0D2C1; border-radius: 8px; padding: 8px 10px; font-size: 13px; color: #2D1810; font-family: inherit; resize: vertical; }
    .wf-day-submit__note:focus { outline: none; border-color: #A66019; box-shadow: 0 0 0 3px rgba(166,96,25,.10); }
    .wf-day-submit__btn { align-self: flex-start; border: none; border-radius: 8px; background: linear-gradient(180deg,#5C2E15,#471500); color: #fff; font-size: 13px; font-weight: 700; padding: 10px 18px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
    .wf-day-submit__btn:hover:not(:disabled) { filter: brightness(1.08); }
    .wf-day-submit__btn:disabled { background: #D9C9B6; color: #8A7A66; cursor: not-allowed; }
    /* Menunggu review (pending) — kartu amber */
    .wf-day-sent { margin: 12px 0 2px; padding: 11px 14px; border: 1px solid #E7D3B8; border-left: 4px solid #B5701A; border-radius: 10px; background: #FCF7EE; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
    .wf-day-sent__text { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; color: #6B4423; }
    .wf-day-sent__text i { color: #B5701A; }
    .wf-day-sent__undo { border: 1px solid #C9A26B; border-radius: 8px; background: #fff; color: #5C2E15; font-size: 12px; font-weight: 700; padding: 7px 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .wf-day-sent__undo:hover { background: #FAF0E2; }
    /* Disetujui — kartu hijau */
    .wf-day-sent--ok { border-color: #BFE3C4; border-left-color: #2E9E4B; background: #F1FBF3; }
    .wf-day-sent--ok .wf-day-sent__text { color: #256B37; }
    .wf-day-sent--ok .wf-day-sent__text i { color: #2E9E4B; }
    /* Ditolak — kartu merah */
    .wf-day-reject { margin: 12px 0 2px; padding: 11px 14px; border: 1px solid #F1C9C1; border-left: 4px solid #C0392B; border-radius: 10px; background: #FDF1EF; display: flex; align-items: flex-start; gap: 10px; font-size: 12.5px; color: #8A2B1E; line-height: 1.45; }
    .wf-day-reject i { color: #C0392B; margin-top: 2px; }
    .wf-day-reject strong { color: #A32D2D; }

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

    /* Modal lihat foto (galeri — bisa digeser antar foto satu slot) */
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
    .wf-photo-modal__nav {
        position: absolute; top: 50%; transform: translateY(-50%);
        width: 42px; height: 42px; border-radius: 50%; border: none;
        background: rgba(24,8,0,.62); color: #FBEADB; font-size: 17px;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
    }
    .wf-photo-modal__nav:hover { background: rgba(24,8,0,.82); }
    .wf-photo-modal__nav[hidden] { display: none; }
    .wf-photo-modal__nav--prev { left: -18px; }
    .wf-photo-modal__nav--next { right: -18px; }
    .wf-photo-modal__pos {
        position: absolute; left: 50%; bottom: -30px; transform: translateX(-50%);
        font-size: 12px; font-weight: 700; color: #FBEADB; letter-spacing: .4px;
    }
    @media (max-width: 560px) {
        .wf-photo-modal__nav--prev { left: 4px; }
        .wf-photo-modal__nav--next { right: 4px; }
    }

    /* ── Grid foto bukti (dipakai mode foto satuan DAN before/after) ── */
    .wf-pgrid { display: flex; flex-wrap: wrap; gap: 7px; align-items: center; }
    .wf-thumb {
        position: relative; width: 62px; height: 62px; padding: 0; margin: 0;
        border: 1px solid #E0D2C1; border-radius: 10px; overflow: hidden;
        background: #F5EAD9; cursor: pointer; flex: 0 0 auto;
    }
    .wf-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .wf-thumb__del {
        position: absolute; top: 2px; right: 2px; width: 19px; height: 19px;
        border-radius: 50%; border: none; background: rgba(40,14,0,.72); color: #fff;
        font-size: 9px; cursor: pointer; display: flex; align-items: center; justify-content: center;
        padding: 0; line-height: 1;
    }
    .wf-thumb__del:hover { background: #A32D2D; }
    .wf-thumb--add {
        /* Lebih lebar dari thumbnail biasa supaya "Tambah Foto" muat satu baris. */
        width: auto; min-width: 84px; padding: 0 10px;
        border: 1.5px dashed #D9C4A6; background: #FBF6EE; color: #8A5A2B;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 3px; font-size: 10px; font-weight: 700; line-height: 1.2; text-align: center;
        white-space: nowrap; transition: .15s;
    }
    .wf-thumb--add:hover { background: #F5EAD9; border-color: #A66019; }
    .wf-thumb--add i { font-size: 15px; }
    .wf-thumb--add[hidden] { display: none; }
    .wf-photo__foot { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 5px; }
    .wf-photo__count { font-size: 10.5px; color: #8A5A2B; font-weight: 700; }
    .wf-photo__status { font-size: 11px; color: #6B4423; font-weight: 600; }

    /* ── Foto before/after opsional (divisi GS) — dua kolom, masing-masing 5 foto ── */
    .wf-ba { display: flex; gap: 14px; margin-top: 8px; flex-wrap: wrap; }
    .wf-ba__col { display: flex; flex-direction: column; gap: 5px; }
    .wf-ba__cap {
        text-transform: uppercase; letter-spacing: .5px; font-size: 10px;
        font-weight: 800; color: #8A5A2B;
    }
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
@php
    // Batas foto per slot — sumbernya konstanta model, supaya UI & server tak bisa beda.
    $fotoMax = \App\Models\ChecklistWeeklySubmissionPhoto::MAX_PER_SLOT;
@endphp
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
                            · Minggu ke-{{ \App\Models\ChecklistWeeklySubmission::weekOfMonthForDate($weekStart) }} bulan ini
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
                        <strong>Ada hari yang ditolak supervisor.</strong>
                        <div style="margin-top:4px;">Perbaiki hari yang ditandai <b>merah</b> di bawah, lalu <b>kirim ulang</b> hari itu. Hari yang sudah disetujui tak perlu diulang.</div>
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
                    @php $reopenGrants = $reopenGrants ?? []; $reopenPending = $reopenPending ?? []; @endphp
                    @foreach($days as $day)
                        @php
                            $dayItems = $grouped->get($day->iso, collect());
                            $totalDay = $dayItems->count();
                            // "beres" = dicentang ATAU dikecualikan per-item.
                            $doneDay  = $dayItems->filter(fn ($i) => $i->is_done || $i->is_excepted)->count();
                            $isComplete = $totalDay > 0 && $doneDay === $totalDay;
                            $isDayExcused = array_key_exists($day->iso, $excusedDays);
                            $dayExcuseReason = $excusedDays[$day->iso]['reason'] ?? null;
                            // source='system' = otomatis dari cuti/izin disetujui → karyawan tak boleh membatalkan.
                            $daySystemExcuse = ($excusedDays[$day->iso]['source'] ?? 'manual') === 'system';
                            // Status review per-hari: pending | approved | rejected | null (belum dikirim).
                            $dayReview        = $dayReviews[$day->iso] ?? null;
                            $dayReviewStatus  = $dayReview->review_status ?? null;
                            $isDayApproved    = $dayReviewStatus === 'approved';
                            $isDayPending     = $dayReviewStatus === 'pending';
                            $isDayRejected    = $dayReviewStatus === 'rejected';
                            // Terkunci = terkirim & belum ditolak (pending/approved). Rejected = terbuka lagi.
                            $isDayLocked      = $isDayPending || $isDayApproved;
                            $daySubmitAt      = $dayReview->submitted_at ?? null;
                            $dayReviewedAt    = $dayReview->reviewed_at ?? null;
                            $dayRejectNote    = $isDayRejected ? ($dayReview->review_notes ?? null) : null;
                            // Permintaan "buka kembali" untuk MINGGU LAMPAU (per-hari).
                            $dayHasGrant   = isset($reopenGrants[$day->iso]);   // disetujui & belum dipakai
                            $dayReopenPend = isset($reopenPending[$day->iso]);  // masih menunggu persetujuan
                            // Boleh diedit: minggu ini, ATAU minggu lampau dgn grant aktif, ATAU hari 'rejected'.
                            $dayReopenable = ($weekOffset === 0) || $dayHasGrant || $isDayRejected;
                            // Hari bisa diubah hanya bila form editable, tak dikecualikan, tidak terkunci, DAN reopenable.
                            $dayEditable = $submission && $submission->isEditable() && ! $isDayExcused && ! $isDayLocked && $dayReopenable;
                            $canDayExcuse = $submission && $submission->isEditable() && $weekOffset === 0 && $totalDay > 0 && ! $isDayLocked;
                            $canDaySubmit = $dayEditable && $totalDay > 0;
                            // Boleh minta buka: minggu lampau, ada tugas, belum dikirim/dikecualikan/ditolak, belum ada grant/pending.
                            $canReopenReq = $weekOffset < 0 && $totalDay > 0 && ! $isDayExcused && ! $isDayLocked
                                            && ! $isDayRejected && ! $dayHasGrant && ! $dayReopenPend;
                            $dayClass = '';
                            if ($day->is_today) $dayClass .= ' is-today';
                            elseif ($day->is_past) $dayClass .= ' is-past';
                            if ($totalDay === 0) $dayClass .= ' is-empty';
                            if ($isDayExcused) $dayClass .= ' is-excused';
                            if ($isDayLocked) $dayClass .= ' is-sent';
                            if ($isDayRejected) $dayClass .= ' is-rejected';
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
                                @elseif($isDayApproved)
                                    <span class="wf-day__pill wf-day__pill--good"><i class="fas fa-check-circle" style="font-size:9px;"></i> Disetujui</span>
                                @elseif($isDayPending)
                                    <span class="wf-day__pill wf-day__pill--warn"><i class="fas fa-clock" style="font-size:9px;"></i> Menunggu review</span>
                                @elseif($isDayRejected)
                                    <span class="wf-day__pill wf-day__pill--danger"><i class="fas fa-times-circle" style="font-size:9px;"></i> Ditolak</span>
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
                                            <div class="wf-task {{ $item->is_done ? 'is-done' : '' }} {{ $item->is_excepted && ! $isDayExcused ? 'is-excepted' : '' }} {{ $dayEditable ? '' : 'is-locked' }} {{ $isDayExcused ? 'is-excused' : '' }} {{ $isDayLocked ? 'is-sent' : '' }} {{ ! $isDayExcused && ! $item->is_excepted && $item->requires_photo && ! $item->photo_before_after && ! $item->photo_path ? 'needs-photo' : '' }}"
                                                 data-item-id="{{ $item->id }}"
                                                 data-toggle-url="{{ route('mychecklist.weekform.item.toggle', $item->id) }}"
                                                 @if($dayEditable)
                                                 data-except-url="{{ route('mychecklist.weekform.item.except', $item->id) }}"
                                                 data-unexcept-url="{{ route('mychecklist.weekform.item.unexcept', $item->id) }}"
                                                 @endif
                                                 data-editable="{{ $dayEditable ? '1' : '0' }}"
                                                 data-requires-photo="{{ $item->requires_photo && ! $item->photo_before_after ? '1' : '0' }}"
                                                 @if($item->requires_photo && ! $item->photo_before_after)
                                                 {{-- URL upload/hapus kini melekat di kotak foto & tiap thumbnail, bukan di task --}}
                                                 data-has-photo="{{ $item->photo_path ? '1' : '0' }}"
                                                 @endif>
                                                <span class="wf-task__check"></span>
                                                <div class="wf-task__body">
                                                    <div class="wf-task__name">
                                                        {{ $item->task_title_snapshot }}
                                                        @if($item->requires_photo && ! $item->photo_before_after)
                                                            @php
                                                                $nSingle = $item->photosFor('single')->count();
                                                            @endphp
                                                            <span class="wf-task__badge"><i class="fas fa-camera"></i> Perlu foto{{ $nSingle ? ' · ' . $nSingle . '/' . $fotoMax : '' }}</span>
                                                        @endif
                                                    </div>
                                                    @if($item->photo_before_after)
                                                        <div class="wf-ba" data-ba>
                                                            @foreach(['before' => 'Before', 'after' => 'After'] as $slot => $slotLabel)
                                                                @php
                                                                    $baPhotos = $item->photosFor($slot);
                                                                @endphp
                                                                <div class="wf-ba__col {{ $baPhotos->count() ? 'has' : '' }}"
                                                                     data-ba-slot="{{ $slot }}" data-slot="{{ $slot }}"
                                                                     data-max="{{ $fotoMax }}"
                                                                     data-upload-url="{{ route('mychecklist.weekform.item.baphoto.upload', ['id' => $item->id, 'slot' => $slot]) }}">
                                                                    <span class="wf-ba__cap">{{ $slotLabel }}</span>
                                                                    <input type="file" accept="image/*" capture="environment" class="wf-ba__file" hidden>
                                                                    <div class="wf-pgrid" data-photo-grid>
                                                                        @foreach($baPhotos as $p)
                                                                            <figure class="wf-thumb" data-photo-id="{{ $p->id }}" data-url="{{ asset($p->path) }}">
                                                                                <img src="{{ asset($p->path) }}" alt="Foto {{ $slotLabel }}" loading="lazy">
                                                                                @if($dayEditable)
                                                                                    <button type="button" class="wf-thumb__del" data-photo-del aria-label="Hapus foto"
                                                                                            data-delete-url="{{ route('mychecklist.weekform.photo.delete', $p->id) }}">
                                                                                        <i class="fas fa-times"></i>
                                                                                    </button>
                                                                                @endif
                                                                            </figure>
                                                                        @endforeach
                                                                        @if($dayEditable)
                                                                            <button type="button" class="wf-thumb wf-thumb--add" data-photo-add
                                                                                    @if($baPhotos->count() >= $fotoMax) hidden @endif>
                                                                                <i class="fas fa-plus"></i><span>Tambah Foto</span>
                                                                            </button>
                                                                        @elseif($baPhotos->isEmpty())
                                                                            <span class="wf-photo__count">Tidak ada foto</span>
                                                                        @endif
                                                                    </div>
                                                                    @if($dayEditable)
                                                                        <div class="wf-photo__foot">
                                                                            <span class="wf-photo__count" data-photo-count>{{ $baPhotos->count() }}/{{ $fotoMax }} foto</span>
                                                                            <span class="wf-ba__status" data-photo-status></span>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @elseif($item->requires_photo)
                                                        @php
                                                            $sPhotos = $item->photosFor('single');
                                                        @endphp
                                                        <div class="wf-photo" data-photo data-slot="single"
                                                             data-max="{{ $fotoMax }}"
                                                             data-upload-url="{{ route('mychecklist.weekform.item.photo.upload', $item->id) }}">
                                                            <input type="file" accept="image/*" capture="environment" class="wf-photo__file" hidden>
                                                            <div class="wf-pgrid" data-photo-grid>
                                                                @foreach($sPhotos as $p)
                                                                    <figure class="wf-thumb" data-photo-id="{{ $p->id }}" data-url="{{ asset($p->path) }}">
                                                                        <img src="{{ asset($p->path) }}" alt="Foto bukti" loading="lazy">
                                                                        @if($dayEditable)
                                                                            <button type="button" class="wf-thumb__del" data-photo-del aria-label="Hapus foto"
                                                                                    data-delete-url="{{ route('mychecklist.weekform.photo.delete', $p->id) }}">
                                                                                <i class="fas fa-times"></i>
                                                                            </button>
                                                                        @endif
                                                                    </figure>
                                                                @endforeach
                                                                @if($dayEditable)
                                                                    <button type="button" class="wf-thumb wf-thumb--add" data-photo-add
                                                                            @if($sPhotos->count() >= $fotoMax) hidden @endif>
                                                                        <i class="fas fa-plus"></i><span>Tambah Foto</span>
                                                                    </button>
                                                                @elseif($sPhotos->isEmpty())
                                                                    <span class="wf-photo__count">Tidak ada foto</span>
                                                                @endif
                                                            </div>
                                                            @if($dayEditable)
                                                                <div class="wf-photo__foot">
                                                                    <span class="wf-photo__count" data-photo-count>{{ $sPhotos->count() }}/{{ $fotoMax }} foto</span>
                                                                    <span class="wf-photo__status" data-photo-status></span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    {{-- Flag "Dikecualikan" per-item (tampil bila item dikecualikan
                                                         & harinya tidak dikecualikan penuh). --}}
                                                    @if($item->is_excepted && ! $isDayExcused)
                                                        <div class="cl-xcpt__flag" data-xcpt-flag>
                                                            <span class="cl-xcpt__pill"><i class="fas fa-ban"></i> Dikecualikan</span>
                                                            <span class="cl-xcpt__reason" data-xcpt-reason>{{ $item->exception_reason }}</span>
                                                            @if($dayEditable)
                                                                <button type="button" class="cl-xcpt__undo" data-xcpt-undo>Batalkan</button>
                                                            @endif
                                                        </div>
                                                    @elseif($dayEditable)
                                                        <div class="cl-xcpt__flag" data-xcpt-flag hidden>
                                                            <span class="cl-xcpt__pill"><i class="fas fa-ban"></i> Dikecualikan</span>
                                                            <span class="cl-xcpt__reason" data-xcpt-reason></span>
                                                            <button type="button" class="cl-xcpt__undo" data-xcpt-undo>Batalkan</button>
                                                        </div>
                                                    @endif

                                                    {{-- Tombol + form pengecualian (hanya editable & hari tidak dikecualikan). --}}
                                                    @if($dayEditable)
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
                                                <i class="fas {{ $daySystemExcuse ? 'fa-calendar-check' : 'fa-mug-hot' }}"></i>
                                                <span>Hari ini dikecualikan — <strong>{{ $dayExcuseReason }}</strong></span>
                                            </div>
                                            @if($daySystemExcuse)
                                                <span class="wf-day-excused__auto" title="Otomatis dari cuti/izin yang disetujui"
                                                      style="display:inline-flex; align-items:center; gap:5px; font-size:11.5px; font-weight:700; color:#7a5b12; background:#fdf3d3; border:1px solid #efd98a; padding:4px 9px; border-radius:999px;">
                                                    <i class="fas fa-lock" style="font-size:9px;"></i> otomatis dari cuti/izin
                                                </span>
                                            @else
                                                <form method="POST" action="{{ route('mychecklist.weekform.day.unexcuse') }}"
                                                      data-confirm="Batalkan pengecualian dan isi {{ $day->label }} seperti biasa?">
                                                    @csrf
                                                    <input type="hidden" name="day_of_week" value="{{ $day->iso }}">
                                                    <button type="submit" class="wf-day-excused__undo">
                                                        <i class="fas fa-undo"></i> Batalkan, isi hari ini
                                                    </button>
                                                </form>
                                            @endif
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

                                {{-- STATUS REVIEW HARI: disetujui / menunggu review / ditolak --}}
                                @if($isDayApproved)
                                    <div class="wf-day-sent wf-day-sent--ok">
                                        <div class="wf-day-sent__text">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Disetujui supervisor @if($dayReviewedAt)• {{ $dayReviewedAt->translatedFormat('d M Y, H:i') }} @endif</span>
                                        </div>
                                    </div>
                                @elseif($isDayPending)
                                    <div class="wf-day-sent">
                                        <div class="wf-day-sent__text">
                                            <i class="fas fa-clock"></i>
                                            <span>Terkirim · menunggu review @if($daySubmitAt)• {{ $daySubmitAt->translatedFormat('d M Y, H:i') }} @endif</span>
                                        </div>
                                        @if($submission->isEditable() && $weekOffset === 0)
                                            <form method="POST" action="{{ route('mychecklist.weekform.day.unsubmit') }}"
                                                  data-confirm="Batalkan kiriman {{ $day->label }} dan buka lagi untuk diubah?">
                                                @csrf
                                                <input type="hidden" name="day_of_week" value="{{ $day->iso }}">
                                                <button type="submit" class="wf-day-sent__undo"><i class="fas fa-undo"></i> Batalkan kirim</button>
                                            </form>
                                        @endif
                                    </div>
                                @elseif($isDayRejected)
                                    <div class="wf-day-reject">
                                        <i class="fas fa-times-circle"></i>
                                        <div>
                                            <strong>Ditolak supervisor.</strong>@if($dayRejectNote) {{ $dayRejectNote }}@endif
                                            <div style="opacity:.85; margin-top:2px;">Perbaiki lalu kirim ulang hari ini.</div>
                                        </div>
                                    </div>
                                @endif

                                {{-- MINGGU LAMPAU: status/permintaan buka kembali --}}
                                @if($dayHasGrant)
                                    <div class="wf-day-sent wf-day-sent--ok" style="background:#eaf6ef; border-color:#bfe3cd;">
                                        <div class="wf-day-sent__text">
                                            <i class="fas fa-unlock"></i>
                                            <span>Dibuka kembali oleh atasan — selesaikan lalu kirim.</span>
                                        </div>
                                    </div>
                                @elseif($dayReopenPend)
                                    <div class="wf-day-sent">
                                        <div class="wf-day-sent__text">
                                            <i class="fas fa-hourglass-half"></i>
                                            <span>Menunggu persetujuan atasan untuk dibuka kembali.</span>
                                        </div>
                                    </div>
                                @elseif($canReopenReq)
                                    <div class="cl-excuse cl-excuse--day">
                                        <button type="button" class="cl-excuse__trigger" data-excuse-toggle>
                                            <span class="cl-excuse__trigger-label"><i class="fas fa-unlock-alt"></i> Minta buka kembali hari ini</span>
                                            <i class="fas fa-chevron-down cl-excuse__caret"></i>
                                        </button>
                                        <div class="cl-excuse__reveal">
                                            <div class="cl-excuse__reveal-inner">
                                                <form method="POST" action="{{ route('mychecklist.reopen.request') }}" class="cl-excuse__form"
                                                      data-confirm="Kirim permintaan buka kembali {{ $day->label }} ({{ $day->date->translatedFormat('d M Y') }})? Menunggu persetujuan atasan.">
                                                    @csrf
                                                    <input type="hidden" name="scope" value="weekly">
                                                    <input type="hidden" name="week_start_date" value="{{ $weekStart->toDateString() }}">
                                                    <input type="hidden" name="day_of_week" value="{{ $day->iso }}">
                                                    <div class="cl-excuse__label">Alasan minta buka (wajib) — mis. lupa dikirim / belum sempat isi:</div>
                                                    <textarea name="reason" class="cl-excuse__input" rows="2" maxlength="500" required
                                                              placeholder="Contoh: lupa mengirim hari itu…"></textarea>
                                                    <button type="submit" class="cl-excuse__submit"><i class="fas fa-paper-plane"></i> Kirim permintaan</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- FORM KIRIM: hari belum dikirim ATAU sudah ditolak (perbaiki & kirim ulang) --}}
                                @if($canDaySubmit)
                                    <form method="POST" action="{{ route('mychecklist.weekform.day.submit') }}" class="wf-day-submit"
                                          data-confirm="Kirim tugas {{ $day->label }}? Setelah dikirim, hari ini menunggu review supervisor.">
                                        @csrf
                                        <input type="hidden" name="day_of_week" value="{{ $day->iso }}">
                                        <input type="hidden" name="week_start_date" value="{{ $weekStart->toDateString() }}">
                                        <textarea name="note" class="wf-day-submit__note" rows="1" maxlength="1000"
                                                  placeholder="Catatan untuk supervisor (opsional)…"></textarea>
                                        <button type="submit" class="wf-day-submit__btn" data-day-submit-btn {{ $isComplete ? '' : 'disabled' }}>
                                            <i class="fas fa-paper-plane"></i>
                                            <span data-day-submit-label>{{ $isComplete ? ($isDayRejected ? 'Kirim ulang hari ini' : 'Kirim tugas hari ini') : 'Selesaikan semua tugas dulu' }}</span>
                                        </button>
                                    </form>
                                @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- CSS cl-excuse (dipakai excuse-hari & "minta buka kembali") — selalu
                     dirender selama daftar hari tampil, termasuk minggu lampau. --}}
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

                {{-- FOOTER: ringkasan submit per-hari (submit dilakukan di tiap kartu hari) --}}
                @if($submission->isEditable() && $weekOffset === 0)
                    @php
                        $activeDays  = $submission->activeDayNumbers();
                        $sentDays    = array_values(array_intersect($submission->submittedDayNumbers(), $activeDays));
                        $activeCount = count($activeDays);
                        $sentCount   = count($sentDays);
                    @endphp
                    <div class="wf-submit">
                        <div class="wf-submit__top">
                            <div class="wf-submit__text">
                                <div class="wf-submit__headline">
                                    @if($activeCount === 0)
                                        Belum ada hari yang perlu dikirim minggu ini.
                                    @elseif($sentCount >= $activeCount)
                                        Semua hari sudah dikirim — menunggu review supervisor.
                                    @else
                                        {{ $sentCount }}/{{ $activeCount }} hari sudah dikirim.
                                    @endif
                                </div>
                                <div class="wf-submit__sub">Kirim tugas tiap hari lewat tombol <b>Kirim tugas hari ini</b> di kartu hari. Form otomatis diteruskan ke supervisor saat semua hari selesai atau dikecualikan.</div>
                            </div>
                        </div>
                    </div>
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
        <button type="button" class="wf-photo-modal__nav wf-photo-modal__nav--prev" id="wfPhotoPrev" aria-label="Foto sebelumnya" hidden><i class="fas fa-chevron-left"></i></button>
        <button type="button" class="wf-photo-modal__nav wf-photo-modal__nav--next" id="wfPhotoNext" aria-label="Foto berikutnya" hidden><i class="fas fa-chevron-right"></i></button>
        <span class="wf-photo-modal__pos" id="wfPhotoPos"></span>
    </div>
</div>

@if($submission && $progress['total'] > 0)
<script>
(function() {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content;
    const pctEl   = document.getElementById('wfPct');
    const fillEl  = document.getElementById('wfFill');
    const countEl = document.getElementById('wfCount');

    document.querySelectorAll('[data-item-id]').forEach(task => {
        // Klik area task (check/judul) → toggle. Foto bersifat opsional, jadi
        // centang tidak pernah diblokir walau foto belum diambil.
        task.addEventListener('click', () => {
            if (task.dataset.editable !== '1') return;
            toggleTask(task);
        });

        // ── Kotak foto: mode satuan [data-photo] & kolom before/after [data-ba-slot] ──
        // Satu jalur untuk keduanya. Klik didelegasikan di level kotak supaya
        // thumbnail yang baru diunggah tidak perlu di-bind ulang, sekaligus
        // menahan bubbling agar kontrol foto tak ikut men-toggle task.
        task.querySelectorAll('[data-photo], [data-ba-slot]').forEach(box => {
            box.addEventListener('click', e => {
                e.stopPropagation();

                const del = e.target.closest('[data-photo-del]');
                if (del) { removePhoto(del.closest('.wf-thumb')); return; }

                if (e.target.closest('[data-photo-add]')) {
                    const fi = box.querySelector('input[type=file]');
                    if (fi) fi.click();
                    return;
                }

                const fig = e.target.closest('.wf-thumb[data-photo-id]');
                if (fig) {
                    const figs = Array.from(thumbsOf(box));
                    openGallery(figs.map(f => f.dataset.url), figs.indexOf(fig));
                }
            });

            const fileInput = box.querySelector('input[type=file]');
            if (fileInput) fileInput.addEventListener('change', () => {
                if (fileInput.files && fileInput.files[0]) uploadPhoto(box, fileInput.files[0]);
                fileInput.value = ''; // reset supaya file yang sama bisa dipilih lagi
            });

            syncBox(box);
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

    // ── Modal lihat foto (galeri: bisa digeser antar foto dalam satu slot) ──
    let galUrls = [], galIdx = 0;

    function galShow(i) {
        const modal = document.getElementById('wfPhotoModal');
        const img   = document.getElementById('wfPhotoModalImg');
        const prev  = document.getElementById('wfPhotoPrev');
        const next  = document.getElementById('wfPhotoNext');
        const pos   = document.getElementById('wfPhotoPos');
        if (! modal || ! img || ! galUrls.length) return;
        galIdx = (i + galUrls.length) % galUrls.length; // wrap-around
        img.src = galUrls[galIdx];
        if (pos)  pos.textContent = galUrls.length > 1 ? (galIdx + 1) + ' / ' + galUrls.length : '';
        if (prev) prev.hidden = galUrls.length < 2;
        if (next) next.hidden = galUrls.length < 2;
        // Muat duluan foto berikutnya supaya geser terasa instan.
        if (galUrls.length > 1) new Image().src = galUrls[(galIdx + 1) % galUrls.length];
    }

    function openGallery(urls, index) {
        const modal = document.getElementById('wfPhotoModal');
        if (! modal || ! urls || ! urls.length) return;
        galUrls = urls.filter(u => !! u);
        if (! galUrls.length) return;
        galShow(index || 0);
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closePhotoModal() {
        const modal = document.getElementById('wfPhotoModal');
        const img   = document.getElementById('wfPhotoModalImg');
        if (! modal) return;
        modal.hidden = true;
        if (img) img.src = '';
        galUrls = [];
        document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closePhotoModal));
    document.getElementById('wfPhotoPrev')?.addEventListener('click', () => galShow(galIdx - 1));
    document.getElementById('wfPhotoNext')?.addEventListener('click', () => galShow(galIdx + 1));
    document.addEventListener('keydown', e => {
        const modal = document.getElementById('wfPhotoModal');
        if (e.key === 'Escape') { closePhotoModal(); return; }
        if (! modal || modal.hidden || galUrls.length < 2) return;
        if (e.key === 'ArrowLeft')  galShow(galIdx - 1);
        if (e.key === 'ArrowRight') galShow(galIdx + 1);
    });

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

    // ── Foto bukti (maks 5 per slot) ──────────────────────────────────────────
    // "box" = kotak foto: [data-photo] untuk mode satuan, [data-ba-slot] untuk
    // kolom Before/After. Keduanya berisi grid thumbnail + tombol "+".

    function thumbsOf(box) { return box.querySelectorAll('.wf-thumb[data-photo-id]'); }

    // Satu-satunya tempat state UI kotak foto dihitung ulang — tombol "+",
    // penghitung, dan penanda "belum ada foto" pada task.
    function syncBox(box) {
        const n   = thumbsOf(box).length;
        const max = parseInt(box.dataset.max || '5', 10);

        const add = box.querySelector('[data-photo-add]');
        if (add) add.hidden = (n >= max) || box.dataset.busy === '1';

        const cnt = box.querySelector('[data-photo-count]');
        if (cnt) cnt.textContent = n >= max ? ('Maks ' + max + ' foto') : (n + '/' + max + ' foto');

        box.classList.toggle('has', n > 0);

        // Hanya slot tunggal yang menentukan penanda "needs-photo" di task —
        // sama dengan kolom cermin photo_path yang dibaca server.
        if (box.dataset.slot === 'single') {
            const task = box.closest('[data-item-id]');
            if (task) {
                task.dataset.hasPhoto = n ? '1' : '0';
                // Tugas yang dikecualikan / harinya dikecualikan tidak pernah
                // ditandai "kurang foto" — sama dengan aturan render di server.
                const dikecualikan = task.classList.contains('is-excepted')
                                  || task.classList.contains('is-excused');
                task.classList.toggle('needs-photo', n === 0 && ! dikecualikan);
            }
        }
    }

    // Sisipkan thumbnail baru tepat sebelum tombol "+" supaya "+" selalu terakhir.
    function appendThumb(box, photo) {
        const grid = box.querySelector('[data-photo-grid]');
        if (! grid) return null;
        const fig = document.createElement('figure');
        fig.className = 'wf-thumb';
        fig.dataset.photoId = photo.id;
        fig.dataset.url     = photo.url;
        fig.innerHTML = '<img alt="Foto bukti" loading="lazy">'
                      + '<button type="button" class="wf-thumb__del" data-photo-del aria-label="Hapus foto"><i class="fas fa-times"></i></button>';
        fig.querySelector('img').src = photo.url;
        fig.querySelector('[data-photo-del]').dataset.deleteUrl = photo.deleteUrl;
        grid.insertBefore(fig, box.querySelector('[data-photo-add]'));
        return fig;
    }

    async function uploadPhoto(box, file) {
        if (box.dataset.busy === '1') return;   // cegah klik beruntun menembus batas
        box.dataset.busy = '1';
        syncBox(box);

        const status = box.querySelector('[data-photo-status]');
        const setStatus = t => { if (status) status.textContent = t; };

        setStatus('Mengompres…');
        let blob;
        try { blob = await resizeImage(file, 1600, 0.8); }
        catch (e) { blob = file; } // fallback: kirim asli kalau resize gagal
        const fd = new FormData();
        fd.append('photo', blob, 'foto.jpg');

        setStatus('Mengunggah…');
        try {
            const res = await fetch(box.dataset.uploadUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: fd,
            });
            const data = await res.json();
            if (! data.ok) throw new Error(data.error || 'Upload gagal');

            appendThumb(box, data.photo);
            setStatus('Foto tersimpan ✓');
            setTimeout(() => setStatus(''), 1500);

            // Foto PERTAMA = bukti selesai → tandai selesai kalau belum.
            // toggleTask() sifatnya flip, jadi syarat count === 1 wajib —
            // tanpa itu foto ke-2 justru bisa membatalkan centang.
            const task = box.closest('[data-item-id]');
            if (box.dataset.slot === 'single' && data.count === 1
                && task && ! task.classList.contains('is-done')) {
                toggleTask(task);
            }
        } catch (err) {
            setStatus('');
            alert('Gagal: ' + (err.message || err));
        } finally {
            box.dataset.busy = '0';
            syncBox(box);
        }
    }

    async function removePhoto(fig) {
        if (! fig) return;
        if (! confirm('Hapus foto ini?')) return;
        const box = fig.closest('[data-photo], [data-ba-slot]');
        const btn = fig.querySelector('[data-photo-del]');
        if (! box || ! btn) return;
        try {
            const res = await fetch(btn.dataset.deleteUrl, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
            });
            const data = await res.json();
            if (! data.ok) throw new Error(data.error || 'Gagal hapus');
            fig.remove();
            syncBox(box);
            // Foto opsional → status centang task tidak berubah saat foto dihapus.
            if (data.progress) updateGlobal(data.progress);
        } catch (err) {
            alert('Gagal: ' + (err.message || err));
        }
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
        // Tombol "Kirim tugas hari ini" aktif hanya bila semua tugas hari itu beres.
        const dayBtn = dayCard.querySelector('[data-day-submit-btn]');
        if (dayBtn) {
            dayBtn.disabled = ! complete;
            const lbl = dayBtn.querySelector('[data-day-submit-label]');
            if (lbl) lbl.textContent = complete ? 'Kirim tugas hari ini' : 'Selesaikan semua tugas dulu';
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
