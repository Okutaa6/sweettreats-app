@extends('layouts.app')

@section('css')
<style>
    /* ──────── Mobile-first interactive checklist ──────── */
    .cl-my-wrap { max-width: 1100px; margin: 0 auto; }
    .cl-my-grid { display: grid; grid-template-columns: 1fr 320px; gap: 16px; align-items: start; }
    @media (max-width: 992px) { .cl-my-grid { grid-template-columns: 1fr; } }
    /* Sembunyikan toggle "Hari Ini / Tampilan Mingguan" di layar mobile.
       !important untuk mengalahkan inline style display:flex pada wrapper. */
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
    /* Pill status — 6 warna berbeda (ikut handoff), tak lagi collapse ke oranye. */
    .cl-mod-hero__status--success { background: #E1F5EE; color: #1F6E5F; border: 1px solid rgba(31,110,95,.30); }
    .cl-mod-hero__status--warning { background: #FAEEDA; color: #A66019; border: 1px solid rgba(166,96,25,.30); }
    .cl-mod-hero__status--danger  { background: #FCEBEB; color: #A32D2D; border: 1px solid rgba(163,45,45,.30); }
    .cl-mod-hero__status--draft   { background: #F5F0EA; color: #7A5B43; border: 1px solid rgba(122,91,67,.28); }
    .cl-mod-hero__status--coffee  { background: #F2E7D8; color: #8A5A2B; border: 1px solid rgba(138,90,43,.30); }
    .cl-mod-hero__status--slate   { background: #EFE7DC; color: #9B8578; border: 1px solid rgba(155,133,120,.30); }

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
        font-size: 20px; color: inherit; opacity: .6; font-weight: 700;
    }
    /* Angka % besar + isi bar berwarna sesuai nilai (hijau/amber/merah) */
    .cl-mod-progress.is-good .cl-mod-progress__big { color: #2F8E55; }
    .cl-mod-progress.is-warn .cl-mod-progress__big { color: #C9962F; }
    .cl-mod-progress.is-bad  .cl-mod-progress__big { color: #C0392B; }
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
        background: #C0392B;
        border-radius: 5px;
        transition: width .4s cubic-bezier(.45,.05,.55,.95), background .2s;
    }
    .cl-mod-progress.is-good .cl-mod-progress__bar > i { background: #2F8E55; }
    .cl-mod-progress.is-warn .cl-mod-progress__bar > i { background: #C9962F; }
    .cl-mod-progress.is-bad  .cl-mod-progress__bar > i { background: #C0392B; }
    /* Pill status di pojok kanan-atas kartu progress (pindahan dari hero).
       Warna diambil dari .cl-mod-hero__status--{success|warning|danger}. */
    .cl-mod-progress__status {
        position: absolute; top: 16px; right: 16px; z-index: 2;
        padding: 6px 14px; border-radius: 999px;
        font-size: 12px; font-weight: 700; white-space: nowrap;
        display: inline-flex; align-items: center; gap: 6px;
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
    /* Chip nomor kategori dibuat tan-flat (ikut handoff); sinyal "selesai"
       cukup lewat count pill hijau — tak lagi teal saat komplet. */
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
        width: 30px; height: 30px;
        border-radius: 9px;
        background: #F5ECE2;
        color: #8A3A12;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 800;
        flex-shrink: 0;
        transition: background .2s, color .2s;
    }
    .cl-mod-cat__title {
        flex: 1; min-width: 0;
        font-size: 14px; font-weight: 800; color: #3B1500;
        margin: 0; line-height: 1.2;
    }
    .cl-mod-cat__chip {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px; border-radius: 20px;
        font-size: 11.5px; font-weight: 800;
        flex-shrink: 0;
    }
    .cl-mod-cat__chip--good { background: #E1F5EE; color: #1F6E5F; }
    .cl-mod-cat__chip--warn { background: #FAEEDA; color: #B5701A; }
    .cl-mod-cat__status {
        font-size: 11.5px; font-weight: 700; white-space: nowrap; flex-shrink: 0;
    }
    .cl-mod-cat__status--good { color: #2F8E55; }
    .cl-mod-cat__status--warn { color: #B5701A; }
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
    /* Buka/tutup halus via grid-template-rows (0fr → 1fr) */
    .cl-mod-cat__body {
        display: grid;
        grid-template-rows: 0fr;
        background: #FFFFFF;
        transition: grid-template-rows .3s ease;
    }
    .cl-mod-cat.is-open .cl-mod-cat__body { grid-template-rows: 1fr; }
    .cl-mod-cat__body-inner { overflow: hidden; min-height: 0; }
    .cl-mod-cat.is-open .cl-mod-cat__body-inner { border-top: 1px solid #F0E8DC; }

    .cl-mod-task {
        display: flex; align-items: center; gap: 12px;
        padding: 12px 18px;
        border-bottom: 1px solid #F5EBE0;
        cursor: pointer;
        transition: background .15s;
    }
    .cl-mod-task:last-child { border-bottom: none; }
    .cl-mod-task:hover { background: #FAF4EC; }
    .cl-mod-task.is-locked { cursor: not-allowed; opacity: .65; }
    /* Checkbox 30px sesuai handoff — dicentang = gradient hangat #A8481B→#6B2400 */
    .cl-mod-task__check {
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
    .cl-mod-task.is-done .cl-mod-task__check {
        background: linear-gradient(135deg, #A8481B, #6B2400);
        border-color: #6B2400;
        box-shadow: 0 2px 6px rgba(71,21,0,.25);
    }
    .cl-mod-task.is-done .cl-mod-task__check::before { content: "✓"; }
    .cl-mod-task__name {
        flex: 1; min-width: 0;
        font-size: 13.5px; font-weight: 600; color: #2D1810; line-height: 1.45;
    }
    .cl-mod-task.is-done .cl-mod-task__name {
        color: #9A7256;
        text-decoration: line-through;
        text-decoration-thickness: 1px;
    }
    .cl-mod-task__time { font-size: 11px; color: #9B8578; flex-shrink: 0; }

    /* Catatan per item: input ringkas di KANAN baris (sejajar judul), sesuai handoff. */
    .cl-mod-task__note { flex: 0 0 170px; max-width: 170px; cursor: default; }
    .cl-mod-task__note--ro { flex-basis: 100%; max-width: none; padding-left: 42px; }
    .cl-mod-note__input {
        width: 100%; box-sizing: border-box;
        border: 1px solid transparent; border-radius: 8px; background: #FBF6EE;
        padding: 7px 10px; font-size: 12.5px; color: #5A3A22; line-height: 1.45;
        font-family: inherit; resize: none; overflow: hidden;
        transition: border-color .15s, box-shadow .15s, background .15s;
    }
    .cl-mod-note__input:hover { background: #FAF1E6; border-color: #EAD9C5; }
    .cl-mod-note__input::placeholder { color: #C7AC92; }
    .cl-mod-note__input:focus { outline: none; border-color: #C9962F; background: #FFFFFF; box-shadow: 0 0 0 3px rgba(201,150,47,.12); }
    .cl-mod-note__status { display: inline-block; min-height: 12px; margin-top: 3px; font-size: 10.5px; color: #9B8578; }
    .cl-mod-note__ro { font-size: 12px; color: #6B4423; padding: 6px 10px; background: #FFF7EE; border-radius: 6px; border-left: 3px solid #9B5E2C; }
    .cl-mod-note__ro i { font-size: 10px; margin-right: 4px; color: #9B5E2C; }

    /* Kartu submit — gradient gelap sesuai handoff: headline + sub kiri, tombol kanan */
    .cl-mod-submit {
        margin-top: 16px;
        background: linear-gradient(120deg, #5C2E15, #471500);
        color: #FFFFFF;
        border-radius: 16px;
        padding: 16px 20px;
        display: flex; align-items: center;
        gap: 16px; flex-wrap: wrap;
        box-shadow: 0 8px 24px rgba(70,21,0,.2);
    }
    .cl-mod-submit__text { flex: 1; min-width: 220px; }
    .cl-mod-submit__headline { font-size: 13.5px; font-weight: 800; color: #FBF1E7; }
    .cl-mod-submit__sub { font-size: 12px; color: #D8B99C; font-weight: 500; margin-top: 3px; }
    .cl-mod-submit__btn {
        border: none;
        background: #FFEAD6;
        color: #5C2E15;
        padding: 12px 22px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 13.5px;
        cursor: pointer;
        display: inline-flex; align-items: center; gap: 8px;
        transition: transform .15s, box-shadow .15s, filter .15s;
        box-shadow: 0 4px 14px rgba(0,0,0,.25);
    }
    .cl-mod-submit__btn:hover { filter: brightness(.96); transform: translateY(-1px); }
    .cl-mod-submit__btn:disabled {
        background: rgba(255,255,255,.12);
        color: #C9A583;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
        filter: none;
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
    .cl-mod-banner--coffee  { background: #F2E7D8; border-color: #8A5A2B; color: #6B4423; }
    .cl-mod-banner strong { font-weight: 700; }

    /* ═══ Wrapper "Checklist Hari Ini" — banner + progress + daftar tugas jadi
       SATU kartu biar tidak terlihat kepisah (satu kesatuan). Elemen di dalam
       dibuat flush: chrome kartu masing-masing dilepas, dipisah garis tipis. ═══ */
    .cl-today {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(45,24,16,.05);
        overflow: hidden;
        margin-bottom: 14px;
    }
    .cl-today .cl-mod-banner { border-radius: 0 !important; margin: 0 !important; }
    .cl-today .cl-mod-progress {
        border: none !important; border-radius: 0 !important;
        box-shadow: none !important; margin: 0 !important;
        border-bottom: 1px solid #F0E6D8 !important;
    }
    .cl-today .cl-mod-cat-list { gap: 0 !important; }
    .cl-today .cl-mod-cat {
        border: none !important; border-radius: 0 !important; box-shadow: none !important;
    }
    .cl-today .cl-mod-cat + .cl-mod-cat { border-top: 1px solid #F0E6D8 !important; }
    .cl-today .cl-mod-cat:hover,
    .cl-today .cl-mod-cat.is-open {
        box-shadow: none !important; transform: none !important; border-color: transparent !important;
    }
    /* Baris tombol "Buka semua" (multi-kategori) — beri padding biar tak mepet tepi kartu */
    .cl-today .cl-cat-tools { padding: 12px 16px 0 !important; margin-bottom: 0 !important; }

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
    /* Widget "review" (Riwayat 14 Hari + Aktivitas Terbaru) hanya di desktop.
       Saat layout stacked (≤992px) disembunyikan biar mobile fokus ke checklist —
       riwayat lengkap tetap bisa diakses via tombol "Riwayat". */
    @media (max-width: 992px) {
        .cl-mini--mreview { display: none; }
    }

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
    .cl-heatmap__cell--approved { background: #57A876; color: #FFFFFF; border-color: #57A876; }
    .cl-heatmap__cell--submitted { background: #E8A44D; color: #FFFFFF; border-color: #E8A44D; }
    .cl-heatmap__cell--rejected { background: #D96C60; color: #FFFFFF; border-color: #D96C60; }
    .cl-heatmap__cell--draft    { background: #DCCDB4; color: #6B4423; border-color: #DCCDB4; }
    .cl-heatmap__cell--missed   { background: #E4D7C6; color: #9B8578; border-color: #E4D7C6; }
    .cl-heatmap__cell--today { outline: 2px solid #8A3A12; outline-offset: -2px; }
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

    /* Recent activity — baris polos dgn garis pemisah, sesuai handoff */
    .cl-recent { display: flex; flex-direction: column; }
    .cl-recent__item {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 0;
        border-bottom: 1px solid #F6EEE1;
        text-decoration: none; color: inherit;
        transition: background .15s;
    }
    .cl-recent__item:last-child { border-bottom: none; }
    .cl-recent__item:hover {
        background: #FCF7F0;
        text-decoration: none; color: inherit;
    }
    .cl-recent__date {
        min-width: 0; flex: 1;
    }
    .cl-recent__date strong {
        display: block; font-size: 12.5px; color: #3B1500; font-weight: 700;
    }
    .cl-recent__date small { font-size: 11px; color: #9A7256; font-weight: 500; }
    .cl-recent__pill {
        flex-shrink: 0; font-size: 11px; font-weight: 800;
        border-radius: 20px; padding: 5px 11px; white-space: nowrap;
    }

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
        /* Di layar kecil: catatan turun ke bawah (sejajar judul), tak lagi di kanan. */
        .cl-mod-task { flex-wrap: wrap; align-items: flex-start; }
        .cl-mod-task__note { flex-basis: 100%; max-width: none; padding-left: 42px; }
        /* Header kategori sempit → teks status disembunyikan, pill hitung cukup. */
        .cl-mod-cat__status { display: none; }
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
        @include('crud.checklist.my._marquee')

        @if(! $position)
            @if(empty($roleDivision))
                {{-- ─── Role belum ditautkan ke divisi checklist ─── --}}
                <div class="st-card">
                    <div class="st-card__body" style="padding:44px 22px; text-align:center;">
                        <div style="display:inline-flex; align-items:center; justify-content:center; width:80px; height:80px; border-radius:50%; background:#FCEBEB; color:#A32D2D; font-size:32px; margin-bottom:16px;">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <h5 style="font-size:19px; font-weight:800; color:#2D1810; margin-bottom:8px;">Role Anda belum terhubung ke checklist</h5>
                        <p style="font-size:13.5px; color:#6B4423; max-width:460px; margin:0 auto; line-height:1.6;">
                            Halo <strong>{{ $userName }}</strong>, role Anda belum ditautkan ke divisi checklist mana pun. Hubungi admin/supervisor untuk menautkan role Anda ke divisinya terlebih dahulu.
                        </p>
                    </div>
                </div>
            @else
                {{-- ─── Divisi sudah ada (dari role) tapi cabang belum diset admin ─── --}}
                <div class="st-card">
                    <div class="st-card__body" style="padding:44px 22px; text-align:center;">
                        <div style="display:inline-flex; align-items:center; justify-content:center; width:80px; height:80px; border-radius:50%; background:#FAEEDA; color:#A66019; font-size:32px; margin-bottom:16px;">
                            <i class="fas fa-building"></i>
                        </div>
                        <h5 style="font-size:19px; font-weight:800; color:#2D1810; margin-bottom:8px;">Cabang Anda belum diset</h5>
                        <p style="font-size:13.5px; color:#6B4423; max-width:460px; margin:0 auto 16px; line-height:1.6;">
                            Halo <strong>{{ $userName }}</strong>, divisi Anda <strong>{{ $roleDivision->name }}</strong> (otomatis dari role), tetapi cabang Anda belum diisi admin. Posisi checklist akan langsung aktif begitu admin menetapkan cabang Anda — Anda tidak perlu mengajukan apa pun.
                        </p>
                        <span style="display:inline-flex; align-items:center; gap:7px; font-size:12.5px; font-weight:700; background:#FFF7EE; border:1px solid #E7D6C2; color:#5C2E15; padding:6px 14px; border-radius:999px;">
                            <i class="fas fa-id-badge" style="font-size:11px; color:#A66019;"></i> {{ $roleDivision->name }}
                        </span>
                    </div>
                </div>
            @endif
        @else
            {{-- ─── PILIH JENIS CHECKLIST + segmented Hari Ini|Rekap + Riwayat — SATU baris (handoff) ─── --}}
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
                        <a href="{{ route('mychecklist') }}" class="cl-vtoggle__btn is-active">Hari Ini</a>
                        <a href="{{ route('mychecklist.weekly') }}" class="cl-vtoggle__btn">Rekap 6 Hari</a>
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

            <div class="cl-my-grid">
            <div>

                {{-- ─── HERO GRADIENT (greeting + status + position) ─── --}}
                @php
                    $hour = (int) now()->format('H');
                    $greet = $hour < 11 ? 'Selamat Pagi' : ($hour < 15 ? 'Selamat Siang' : ($hour < 18 ? 'Selamat Sore' : 'Selamat Malam'));
                    $statusOpt = \App\Models\ChecklistSubmission::statusOptions()[$submission->status] ?? null;
                    $statusColorMap = [
                        'success' => 'success', 'warning' => 'warning', 'danger' => 'danger',
                        'coffee'  => 'coffee',  'slate'   => 'slate',   'neutral' => 'draft',
                        'info'    => 'warning',
                    ];
                    $heroStatusColor = $statusColorMap[$statusOpt['color'] ?? 'neutral'] ?? 'draft';
                    $pct = (int) ($progress['pct'] ?? 0);
                    $pctClass = $pct >= 80 ? 'is-good' : ($pct >= 50 ? 'is-warn' : 'is-bad');
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
                        {{-- Pill status dipindah ke kartu Progres Hari Ini (lihat .cl-mod-progress__status) --}}
                    </div>
                </div>

                {{-- ═══ Wrapper "Checklist Hari Ini": banner + progress + daftar tugas
                     dibungkus 1 kartu biar tidak terlihat kepisah (satu kesatuan). ═══ --}}
                <div class="cl-today">

                {{-- ─── BANNER STATUS ─── --}}
                @if($submission->status === \App\Models\ChecklistSubmission::STATUS_REJECTED)
                    <div class="cl-mod-banner cl-mod-banner--danger">
                        <i class="fas fa-times-circle"></i>
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
                        <div>
                            <strong>Menunggu approval supervisor.</strong> Anda akan diberitahu via notifikasi saat sudah direview.
                            @if($submission->submitted_at)<div style="margin-top:4px; font-size:12px; opacity:.85;">Dikirim {{ $submission->submitted_at->translatedFormat('d M Y, H:i') }}</div>@endif
                        </div>
                    </div>
                @elseif($submission->status === \App\Models\ChecklistSubmission::STATUS_APPROVED)
                    <div class="cl-mod-banner cl-mod-banner--success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Checklist hari ini disetujui supervisor.</strong> Terima kasih atas konsistensinya! <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="vertical-align:-.15em"><path d="M8 1.8 9.3 5.6 13 7 9.3 8.4 8 12.2 6.7 8.4 3 7l3.7-1.4L8 1.8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M12.8 11.4v2.8M11.4 12.8h2.8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                            @if($submission->submitted_at)<div style="margin-top:4px; font-size:12px; opacity:.85;">Dikirim {{ $submission->submitted_at->translatedFormat('d M Y, H:i') }}</div>@endif
                        </div>
                    </div>
                @elseif($submission->status === \App\Models\ChecklistSubmission::STATUS_EXCUSED)
                    <div class="cl-mod-banner cl-mod-banner--coffee">
                        <i class="fas fa-mug-hot"></i>
                        <div style="flex:1;">
                            <strong>Checklist hari ini ditandai "Dikecualikan" — tidak perlu diisi.</strong>
                            <div style="margin-top:4px;">Alasan: {{ $submission->excuse_reason }}</div>
                            <form method="POST" action="{{ route('mychecklist.unexcuse') }}" style="margin-top:10px;"
                                  data-confirm="Batalkan pengecualian dan isi checklist seperti biasa?">
                                @csrf
                                <button type="submit" class="st-btn-brand" style="font-size:12.5px; padding:6px 14px;">
                                    <i class="fas fa-undo"></i> Batalkan, isi checklist
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- ─── PROGRESS CARD (modern, big %) ─── --}}
                <div class="cl-mod-progress {{ $pctClass }}" id="clProgressCard">
                    {{-- Pill status (pindahan dari hero) — pojok kanan-atas kartu --}}
                    @if($submission->status !== \App\Models\ChecklistSubmission::STATUS_DRAFT && $statusOpt)
                        <span class="cl-mod-progress__status cl-mod-hero__status--{{ $heroStatusColor }}">
                            @if($submission->status === 'approved')<i class="fas fa-check-circle"></i>
                            @elseif($submission->status === 'rejected')<i class="fas fa-times-circle"></i>
                            @else<i class="fas fa-clock"></i>
                            @endif
                            {{ $statusOpt['label'] }}
                        </span>
                    @endif
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
                                <div class="cl-empty__title">Tidak ada tugas hari ini</div>
                                <div class="cl-empty__desc">Belum ada tugas checklist untuk divisi {{ $position->division?->name }} — Anda tidak perlu mengisi apa pun hari ini. Hubungi supervisor bila menurut Anda ini keliru.</div>
                            </div>
                        </div>
                    </div>
                @else
                    @if($grouped->count() > 1)
                        <div class="cl-cat-tools" style="display:flex; justify-content:flex-end; margin-bottom:10px;">
                            <button type="button" class="st-btn-outline-brown" id="clCatExpandAll" data-mode="expand" style="padding:6px 12px; font-size:12px;">
                                <i class="fas fa-angle-double-down"></i> <span data-label>Buka semua</span>
                            </button>
                        </div>
                    @endif
                    <div class="cl-mod-cat-list">
                        @php $catIdx = 0; $openedOne = false; @endphp
                        @foreach($grouped as $catTitle => $items)
                            @php
                                $catIdx++;
                                $catDone = $items->where('is_done', true)->count();
                                $catTotal = $items->count();
                                $catComplete = $catDone === $catTotal;
                                // Exclusive accordion: hanya kategori pertama yang belum selesai yang terbuka.
                                $isOpen = ! $catComplete && ! $openedOne;
                                if ($isOpen) $openedOne = true;
                            @endphp
                            <div class="cl-mod-cat {{ $isOpen ? 'is-open' : '' }} {{ $catComplete ? 'is-complete' : '' }}" data-cat-block>
                                {{-- Header sesuai handoff: nomor · judul (flex) · pill hitung · status — semua satu baris. --}}
                                <div class="cl-mod-cat__head" data-cat-toggle>
                                    <span class="cl-mod-cat__num" data-cat-num>{{ $catIdx }}</span>
                                    <h6 class="cl-mod-cat__title">{{ $catTitle }}</h6>
                                    <span class="cl-mod-cat__chip cl-mod-cat__chip--{{ $catComplete ? 'good' : 'warn' }}" data-cat-chip>
                                        @if($catComplete)<i class="fas fa-check" style="font-size:9px;"></i>@endif
                                        {{ $catDone }}/{{ $catTotal }}
                                    </span>
                                    <span class="cl-mod-cat__status cl-mod-cat__status--{{ $catComplete ? 'good' : 'warn' }}" data-cat-meta>
                                        @if($catComplete)
                                            semua selesai
                                        @else
                                            {{ $catTotal - $catDone }} belum dikerjakan
                                        @endif
                                    </span>
                                    <i class="fas fa-chevron-down cl-mod-cat__chev"></i>
                                </div>
                                <div class="cl-mod-cat__body">
                                    <div class="cl-mod-cat__body-inner">
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

                                            {{-- Catatan per item (opsional). Di luar area toggle (stopPropagation
                                                 di JS) supaya mengetik tidak ikut mencentang item. --}}
                                            @if($submission->isEditable())
                                                <div class="cl-mod-task__note">
                                                    <textarea class="cl-mod-note__input" rows="1" maxlength="1000"
                                                              data-note-url="{{ route('mychecklist.item.note', $item->id) }}"
                                                              placeholder="+ catatan">{{ $item->note }}</textarea>
                                                    <span class="cl-mod-note__status" data-note-status></span>
                                                </div>
                                            @elseif($item->note)
                                                <div class="cl-mod-task__note cl-mod-task__note--ro">
                                                    <div class="cl-mod-note__ro"><strong>Catatan:</strong> {{ $item->note }}</div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                    </div>{{-- /cl-mod-cat__body-inner --}}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                </div>{{-- /cl-today --}}

                {{-- ─── SUBMIT FOOTER (modern) ─── --}}
                @if($submission->isEditable() && $grouped->isNotEmpty())
                    <form method="POST" action="{{ route('mychecklist.submit') }}"
                          data-confirm="Submit checklist hari ini? Setelah submit, anda tidak bisa edit lagi sampai supervisor review.">
                        @csrf
                        <div class="cl-mod-submit">
                            @php $isComplete = $progress['total'] > 0 && $progress['done'] === $progress['total']; @endphp
                            {{-- Kalimat mengikuti handoff: headline tebal + sub redup. --}}
                            <div class="cl-mod-submit__text">
                                <div class="cl-mod-submit__headline" data-submit-headline>
                                    @if($isComplete)
                                        Semua tugas selesai — siap dikirim!
                                    @else
                                        Masih ada {{ $progress['total'] - $progress['done'] }} tugas belum dicentang.
                                    @endif
                                </div>
                                <div class="cl-mod-submit__sub" data-submit-sub>
                                    @if($isComplete)
                                        Setelah dikirim, isian terkunci sampai direview supervisor.
                                    @else
                                        Semua tugas harus selesai (100%) sebelum submit.
                                    @endif
                                </div>
                            </div>
                            <button type="submit" class="cl-mod-submit__btn" id="clSubmitBtn" {{ $isComplete ? '' : 'disabled' }}>
                                @if($isComplete)
                                    <i class="fas fa-paper-plane"></i> Kirim Checklist
                                @else
                                    <i class="fas fa-paper-plane"></i> Selesaikan semua tugas dulu
                                @endif
                            </button>
                        </div>{{-- /cl-mod-submit --}}
                    </form>
                @endif

                {{-- ─── Aksi "Dikecualikan" — di luar wrapper submit, tampil sbg dropdown button ─── --}}
                @if($submission->isEditable())
                    @include('crud.checklist.my._excuse_block')
                    <style>
                        /* Kartu pengecualian sesuai handoff: bg krem, border putus-putus, aksen coklat #8A5A2B */
                        .cl-excuse { margin-top: 14px; border: 1px dashed #DCC4A8; border-radius: 16px; background: #FBF6EE; overflow: hidden; }
                        .cl-excuse__trigger { list-style: none; cursor: pointer; padding: 13px 18px; font-size: 13px; font-weight: 700; color: #8A5A2B; display: flex; align-items: center; justify-content: space-between; gap: 8px; transition: background .15s; }
                        .cl-excuse__trigger:hover { background: #F6EEDF; }
                        .cl-excuse__trigger::-webkit-details-marker { display: none; }
                        .cl-excuse__trigger-label { display: inline-flex; align-items: center; gap: 11px; }
                        .cl-excuse__trigger i { color: #8A5A2B; }
                        .cl-excuse__caret { font-size: 12px; transition: transform .18s ease; }
                        .cl-excuse[open] .cl-excuse__caret { transform: rotate(180deg); }
                        .cl-excuse__form { padding: 2px 18px 16px; display: flex; flex-direction: column; gap: 11px; }
                        .cl-excuse__label { font-size: 12px; font-weight: 500; color: #9A7256; }
                        .cl-excuse__label b { color: #6B4423; }
                        .cl-excuse__chips { display: flex; flex-wrap: wrap; gap: 8px; }
                        .cl-excuse__chip { font-size: 11.5px; font-weight: 700; padding: 7px 14px; border-radius: 20px; border: 1px dashed #D9C4A6; background: #fff; color: #8A5A2B; cursor: pointer; transition: .15s; font-family: inherit; }
                        .cl-excuse__chip:hover { filter: brightness(.97); }
                        .cl-excuse__chip.is-active { background: #8A5A2B; color: #fff; border: 1px solid transparent; }
                        .cl-excuse__input { width: 100%; box-sizing: border-box; border: 1px solid #E7D3BE; border-radius: 11px; padding: 10px 13px; font-size: 12.5px; color: #3B1500; background: #fff; font-family: inherit; resize: vertical; }
                        .cl-excuse__input:focus { outline: none; border-color: #C9962F; box-shadow: 0 0 0 3px rgba(201,150,47,.12); }
                        .cl-excuse__submit { align-self: flex-start; border: none; border-radius: 11px; background: #8A5A2B; color: #fff; font-size: 12.5px; font-weight: 800; padding: 10px 18px; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; font-family: inherit; transition: .15s; }
                        .cl-excuse__submit:hover { filter: brightness(.96); }
                        .cl-excuse__submit:disabled { background: #EFE3D2; color: #B7926F; cursor: not-allowed; filter: none; }
                    </style>
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

                {{-- Heatmap 14 hari (widget review — desktop only) --}}
                <div class="cl-mini cl-mini--mreview">
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
                                    'draft'     => 'Belum Dikirim',
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
                        <span><i style="background:#57A876; border-color:#57A876;"></i> Disetujui</span>
                        <span><i style="background:#E8A44D; border-color:#E8A44D;"></i> Menunggu</span>
                        <span><i style="background:#D96C60; border-color:#D96C60;"></i> Ditolak</span>
                        <span><i style="background:#E4D7C6; border-color:#E4D7C6;"></i> Tidak diisi</span>
                    </div>
                </div>

                {{-- Recent activity (widget review — desktop only) --}}
                <div class="cl-mini cl-mini--mreview">
                    <div class="cl-mini__head">
                        <h6 class="cl-mini__title"><i class="fas fa-history"></i> Aktivitas Terbaru</h6>
                        <a href="{{ route('mychecklist.history') }}" style="font-size:11.5px; color:#8A3A12; text-decoration:none; font-weight:700;">Semua →</a>
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
                                    <span class="st-pill st-pill--{{ $opt['color'] }} cl-recent__pill">{{ $opt['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </aside>
            </div>{{-- /cl-my-grid --}}
        @endif
    </div>
</div>

@if($position && $grouped->isNotEmpty())
<script>
(function() {
    // Accordion per kategori: klik = buka/tutup kategori itu saja (boleh banyak
    // terbuka sekaligus). Tombol "Buka semua / Tutup semua" untuk sekali klik.
    const catBlocks = Array.prototype.slice.call(document.querySelectorAll('[data-cat-block]'));
    const catExpandAll = document.getElementById('clCatExpandAll');
    // Textarea catatan yang tadinya display:none punya scrollHeight 0 →
    // hitung ulang tingginya begitu kategorinya terlihat.
    function recalcNotes(block) {
        block.querySelectorAll('[data-note-url]').forEach(ta => {
            ta.style.height = 'auto';
            ta.style.height = ta.scrollHeight + 'px';
        });
    }
    function syncCatExpandAll() {
        if (! catExpandAll) return;
        const anyClosed = catBlocks.some(b => ! b.classList.contains('is-open'));
        catExpandAll.dataset.mode = anyClosed ? 'expand' : 'collapse';
        const lbl = catExpandAll.querySelector('[data-label]');
        if (lbl) lbl.textContent = anyClosed ? 'Buka semua' : 'Tutup semua';
        const ic = catExpandAll.querySelector('i');
        if (ic) ic.className = anyClosed ? 'fas fa-angle-double-down' : 'fas fa-angle-double-up';
    }
    document.querySelectorAll('[data-cat-toggle]').forEach(head => {
        head.addEventListener('click', () => {
            const block = head.closest('[data-cat-block]');
            const willOpen = ! block.classList.contains('is-open');
            block.classList.toggle('is-open', willOpen);
            if (willOpen) recalcNotes(block);
            syncCatExpandAll();
        });
    });
    if (catExpandAll) {
        catExpandAll.addEventListener('click', () => {
            const expand = catExpandAll.dataset.mode === 'expand';
            catBlocks.forEach(b => {
                b.classList.toggle('is-open', expand);
                if (expand) recalcNotes(b);
            });
            syncCatExpandAll();
        });
        syncCatExpandAll();
    }

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
                alert(navigator.onLine
                    ? ('Gagal menyimpan: ' + (err.message || err) + '\nBila status checklist sudah berubah (mis. sudah direview), muat ulang halaman.')
                    : 'Koneksi terputus — perubahan belum tersimpan. Coba lagi setelah kembali online.');
            }
        });
    });

    // ── Catatan per item (opsional) — auto-save debounce ke updateNote ──
    // Cegah klik di area catatan ikut men-toggle item (listener toggle ada di parent).
    document.querySelectorAll('.cl-mod-task__note').forEach(box => {
        box.addEventListener('click', e => e.stopPropagation());
    });
    document.querySelectorAll('[data-note-url]').forEach(ta => {
        const grow = () => { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; };
        grow();
        const statusEl = ta.parentElement.querySelector('[data-note-status]');
        let timer, lastSaved = ta.value;
        const save = async () => {
            if (ta.value === lastSaved) return;
            if (statusEl) statusEl.textContent = 'Menyimpan…';
            try {
                const res = await fetch(ta.dataset.noteUrl, {
                    method: 'PATCH',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ note: ta.value }),
                });
                const data = await res.json();
                if (! data.ok) throw new Error();
                lastSaved = ta.value;
                if (statusEl) { statusEl.textContent = 'Tersimpan ✓'; setTimeout(() => { statusEl.textContent = ''; }, 1500); }
            } catch (err) {
                if (statusEl) statusEl.textContent = 'Gagal menyimpan';
            }
        };
        ta.addEventListener('input', () => { grow(); clearTimeout(timer); timer = setTimeout(save, 700); });
        ta.addEventListener('blur', () => { clearTimeout(timer); save(); });
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
            chip.innerHTML = (complete ? '<i class="fas fa-check" style="font-size:9px;"></i> ' : '') + cnt + '/' + total;
            chip.classList.toggle('cl-mod-cat__chip--good', complete);
            chip.classList.toggle('cl-mod-cat__chip--warn', ! complete);
        }
        const meta = block.querySelector('[data-cat-meta]');
        if (meta) {
            meta.textContent = complete ? 'semua selesai' : (total - cnt) + ' belum dikerjakan';
            meta.classList.toggle('cl-mod-cat__status--good', complete);
            meta.classList.toggle('cl-mod-cat__status--warn', ! complete);
        }

        // Update card state classes
        block.classList.toggle('is-complete', complete);
    }

    const progressCard = document.getElementById('clProgressCard');
    function updateProgressGlobal(p) {
        if (! p) return;
        if (pctEl)   pctEl.innerHTML   = p.pct + '<small>%</small>';
        if (fillEl)  fillEl.style.width = p.pct + '%';
        if (progressCard) {
            const cls = p.pct >= 80 ? 'is-good' : (p.pct >= 50 ? 'is-warn' : 'is-bad');
            progressCard.classList.remove('is-good', 'is-warn', 'is-bad');
            progressCard.classList.add(cls);
        }
        if (countEl) {
            const remaining = Math.max(0, p.total - p.done);
            countEl.innerHTML = '<strong>' + p.done + '</strong> dari ' + p.total + ' tugas <small>· ' + remaining + ' belum dikerjakan</small>';
        }
        const complete = p.total > 0 && p.done === p.total;
        if (submitBtn) {
            submitBtn.disabled = ! complete;
            submitBtn.innerHTML = complete
                ? '<i class="fas fa-paper-plane"></i> Kirim Checklist'
                : '<i class="fas fa-paper-plane"></i> Selesaikan semua tugas dulu';
        }
        // Update headline + sub kartu submit (kalimat handoff) — submit hanya bisa saat 100%
        const headEl = document.querySelector('[data-submit-headline]');
        const subEl  = document.querySelector('[data-submit-sub]');
        if (headEl) {
            headEl.textContent = complete
                ? 'Semua tugas selesai — siap dikirim!'
                : 'Masih ada ' + (p.total - p.done) + ' tugas belum dicentang.';
        }
        if (subEl) {
            subEl.textContent = complete
                ? 'Setelah dikirim, isian terkunci sampai direview supervisor.'
                : 'Semua tugas harus selesai (100%) sebelum submit.';
        }
    }
})();
</script>
@endif

@include('crud.checklist.my._confirm_modal')
@endsection
