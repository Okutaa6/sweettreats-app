@extends('layouts.app')

{{-- Cuti — rekap saldo + cuti bersama + koreksi + kontrak baru (Finance).
     Sumber desain: C:\Users\Ekky13\Downloads\design_handoff_cuti (hifi).
     Baris tabel & drawer dirender client-side dari ROSTER (pola prototype)
     supaya filter/pencarian hidup tanpa reload; mutasi tetap POST biasa. --}}

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .ct-wrap { font-family: 'Plus Jakarta Sans', sans-serif; padding: 20px 26px 30px; display: flex; flex-direction: column; gap: 16px; color: #3B1500; }
    .ct-wrap .num { font-variant-numeric: tabular-nums; font-feature-settings: "tnum" 1; }

    /* ── kepala halaman ── */
    .ct-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
    .ct-title { font-size: 20px; font-weight: 800; color: #3B1500; margin: 0; }
    .ct-subtitle { font-size: 12.5px; font-weight: 500; color: #9A7256; margin-top: 2px; }
    .ct-subtitle .hariini { color: #B7926F; }
    .ct-head-btns { display: flex; gap: 10px; }
    .ct-btn { display: inline-flex; align-items: center; gap: 8px; border-radius: 11px; padding: 11px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; font-family: inherit; border: 1px solid transparent; }
    .ct-head-btns .ct-btn-pri { padding: 11px 18px; }
    .ct-btn svg { flex: none; }
    .ct-btn-sec { background: #fff; border-color: #E7D3BE; color: #5A3A22; }
    .ct-btn-sec:hover { background: #FBF4EC; }
    .ct-btn-pri { background: linear-gradient(135deg, #3A3A36, #242422); color: #fff; box-shadow: 0 4px 12px rgba(20,20,18,.22); }
    .ct-btn-pri:hover { filter: brightness(1.12); }
    .ct-btn-green { background: #2F8E55; color: #fff; }
    .ct-btn-green:hover { filter: brightness(1.08); }

    /* ── kartu statistik ── */
    .ct-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
    @media (max-width: 1100px) { .ct-stats { grid-template-columns: repeat(2, 1fr); } }
    .ct-stat { background: #fff; border: 1px solid #EFE0CE; border-radius: 16px; padding: 16px 18px; box-shadow: 0 1px 2px rgba(70,21,0,.04); }
    .ct-stat .lbl { font-size: 10.5px; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; color: #A9866A; }
    .ct-stat .val { font-size: 30px; font-weight: 800; line-height: 1; margin: 6px 0 4px; color: #3B1500; }
    .ct-stat .hint { font-size: 11.5px; color: #9A7256; }
    .ct-stat.minus { border-color: #F0CFC8; }
    .ct-stat.minus .lbl, .ct-stat.minus .val { color: #C0392B; }
    .ct-stat.minus .hint { color: #B07B45; }
    .ct-stat.kontrak { border-color: #EBD9BD; }
    .ct-stat.kontrak .lbl, .ct-stat.kontrak .val { color: #B5701A; }
    .ct-stat.rata .val { color: #2F8E55; }

    /* ── toolbar ── */
    .ct-toolbar { display: flex; align-items: center; gap: 11px; flex-wrap: wrap; }
    .ct-search { position: relative; flex: 1; min-width: 230px; max-width: 360px; }
    .ct-search svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #B79A82; }
    .ct-search input { width: 100%; border: 1px solid #E7D3BE; border-radius: 11px; background: #fff; padding: 10px 12px 10px 38px; font-family: inherit; font-size: 13.5px; color: #3B1500; }
    .ct-search input::placeholder { color: #C7AC92; }
    .ct-select { border: 1px solid #E7D3BE; border-radius: 11px; background: #fff; padding: 10px 12px; font-family: inherit; font-size: 13px; color: #3B1500; }
    .ct-minusbtn { display: inline-flex; align-items: center; gap: 7px; border: 1px solid #F0CFC8; border-radius: 11px; background: #fff; color: #C0392B; padding: 10px 14px; font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; }
    .ct-minusbtn.on { background: #C0392B; border-color: #C0392B; color: #fff; }
    .ct-count { margin-left: auto; display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; color: #9A7256; }
    .ct-count b { color: #3B1500; font-weight: 800; }

    /* ── tabel ── */
    .ct-card { background: #fff; border: 1px solid #EFE0CE; border-radius: 18px; overflow: hidden; box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05); }
    .ct-thead, .ct-prow { display: grid; grid-template-columns: minmax(220px,1.7fr) 168px minmax(150px,1.1fr) minmax(170px,1.1fr) 22px; gap: 12px; align-items: center; padding: 13px 20px; }
    .ct-thead { background: linear-gradient(120deg, #2C2C29, #3A3A36); color: #E3D8C9; font-size: 11px; font-weight: 800; letter-spacing: .6px; }
    .ct-prow { border-bottom: 1px solid #F4EADD; border-left: 3px solid transparent; cursor: pointer; }
    .ct-prow:hover { background: #FBF4EC; }
    .ct-prow.is-minus { border-left-color: #C0392B; }
    .ct-who { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .ct-av { width: 40px; height: 40px; border-radius: 50%; flex: none; display: grid; place-items: center; color: #fff; font-size: 15px; font-weight: 800; }
    .ct-who .nm { font-size: 14px; font-weight: 700; color: #3B1500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ct-who .rl { font-size: 11.5px; font-weight: 600; color: #A9866A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ct-sisa .n { font-size: 26px; font-weight: 800; }
    .ct-sisa .h { font-size: 12.5px; font-weight: 700; margin-left: 4px; }
    .ct-sisa .note { font-size: 10.5px; font-weight: 700; margin-top: 1px; }
    .ct-c-pos { color: #2F8E55; } .ct-c-nol { color: #A08A76; } .ct-c-min { color: #C0392B; }
    .ct-pakai .a { font-size: 13px; font-weight: 700; color: #5A3A22; }
    .ct-pakai .b { font-size: 11px; color: #9A7256; margin-top: 1px; }
    .ct-kontrak { font-size: 12.5px; font-weight: 600; color: #5A3A22; }
    .ct-badge-kuning { display: inline-flex; align-items: center; gap: 6px; background: #FBEFD9; color: #9A6614; border-radius: 8px; padding: 4px 9px; font-size: 11.5px; font-weight: 700; }
    .ct-badge-kuning i { width: 6px; height: 6px; border-radius: 50%; background: #B5701A; }
    .ct-chev { color: #C9AE94; }
    .ct-empty { text-align: center; padding: 40px 20px; }
    .ct-empty b { display: block; font-size: 14px; font-weight: 800; color: #5A3A22; margin-bottom: 4px; }
    .ct-empty span { font-size: 12.5px; color: #9A7256; }
    .ct-foot { display: flex; justify-content: space-between; gap: 12px; padding: 12px 20px; background: #FCF7F0; font-size: 12px; color: #9A7256; }

    /* ── drawer umum ── */
    .ct-scrim { position: fixed; inset: 0; background: rgba(40,12,0,.42); z-index: 1050; opacity: 0; pointer-events: none; transition: opacity 180ms; }
    .ct-scrim.on { opacity: 1; pointer-events: auto; }
    .ct-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: 468px; max-width: 96vw; background: #FBF5EE; z-index: 1060; box-shadow: -12px 0 40px rgba(40,12,0,.28); transform: translateX(100%); transition: transform 260ms cubic-bezier(.2,.8,.2,1); display: flex; flex-direction: column; }
    .ct-drawer.lebar { width: 580px; }
    .ct-drawer.sedang { width: 480px; }
    .ct-drawer.on { transform: translateX(0); }
    @media (prefers-reduced-motion: reduce) { .ct-drawer, .ct-scrim, .ct-toast { transition: none; } }
    .ct-dhead { background: linear-gradient(120deg, #2C2C29, #3A3A36); color: #F3E7DA; padding: 20px 22px; position: relative; }
    .ct-dhead .eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .8px; color: #B7A48F; margin-bottom: 10px; }
    .ct-dhead .who .eyebrow { margin-bottom: 2px; }
    .ct-dico { width: 38px; height: 38px; border-radius: 11px; background: rgba(255,255,255,.14); display: grid; place-items: center; flex: none; color: #F3E7DA; }
    .ct-dhead .who { display: flex; align-items: center; gap: 13px; }
    .ct-dhead .who .ct-av { width: 46px; height: 46px; font-size: 17px; }
    .ct-dhead .who .nm { font-size: 19px; font-weight: 800; color: #F3E7DA; }
    .ct-dhead .who .rl { font-size: 12.5px; color: #C3B09C; }
    .ct-dhead .tutup { position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; border: 0; border-radius: 9px; background: rgba(255,255,255,.14); color: #F3E7DA; font-size: 15px; cursor: pointer; display: grid; place-items: center; }
    .ct-chip { display: inline-flex; align-items: center; gap: 7px; border-radius: 9px; padding: 6px 11px; font-size: 12px; font-weight: 700; margin-top: 13px; background: rgba(255,255,255,.14); color: #F3E7DA; }
    .ct-chip.amber { background: rgba(181,112,26,.24); color: #F0C98A; }
    .ct-dbody { flex: 1; overflow-y: auto; padding: 20px 22px; display: flex; flex-direction: column; gap: 18px; }
    .ct-dfoot { border-top: 1px solid #EDDFCE; background: #FBF5EE; padding: 16px 22px; display: flex; gap: 10px; }
    .ct-dfoot .ct-btn { flex: 1; justify-content: center; }

    /* drawer orang: blok saldo */
    .ct-saldo { background: #fff; border: 1px solid #EFE0CE; border-radius: 16px; padding: 18px 20px; display: flex; gap: 18px; align-items: center; box-shadow: 0 1px 2px rgba(70,21,0,.04); }
    .ct-saldo .big { text-align: center; min-width: 92px; }
    .ct-saldo .big .n { font-size: 44px; font-weight: 800; line-height: 1; }
    .ct-saldo .big .l { font-size: 12px; font-weight: 700; margin-top: 3px; }
    .ct-saldo .sep { width: 1px; align-self: stretch; background: #EFE0CE; }
    .ct-saldo .status { font-size: 13px; font-weight: 700; line-height: 1.45; }
    .ct-saldo .ministats { display: flex; gap: 16px; margin-top: 10px; }
    .ct-saldo .ministats .l { font-size: 10px; font-weight: 700; color: #A9866A; text-transform: uppercase; }
    .ct-saldo .ministats .v { font-size: 16px; font-weight: 800; color: #3B1500; margin-top: 1px; }

    /* form koreksi */
    .ct-kor { background: #fff; border: 1px solid #E7D3BE; border-radius: 16px; padding: 16px 18px; }
    .ct-kor h6 { font-size: 12px; font-weight: 800; letter-spacing: .4px; color: #5A3A22; margin: 0 0 12px; }
    .ct-kor-toggle { display: flex; gap: 8px; margin-bottom: 12px; }
    .ct-kor-toggle button { flex: 1; border-radius: 10px; border: 1px solid #E7D3BE; background: #fff; color: #9A7256; font-family: inherit; font-size: 12.5px; font-weight: 700; padding: 9px 10px; cursor: pointer; }
    .ct-kor-toggle button.on { background: #F1E9DF; border-color: #C9962F; color: #3B1500; }
    .ct-kor label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #A9866A; margin: 0 0 5px; }
    .ct-kor input[type=number] { width: 96px; }
    .ct-kor input, .ct-kor textarea { border: 1px solid #E7D3BE; border-radius: 10px; padding: 9px 11px; font-family: inherit; font-size: 13px; color: #3B1500; background: #fff; }
    .ct-kor .baris { display: flex; gap: 12px; margin-bottom: 12px; }
    .ct-kor .baris > div:last-child { flex: 1; }
    .ct-kor .baris input[name=catatan] { width: 100%; }
    .ct-info { background: #FBF0DE; border: 1px solid #EBD9BD; border-radius: 11px; padding: 10px 13px; font-size: 12px; color: #7A5B43; line-height: 1.5; }
    .ct-kor .ct-info { color: #9A7256; }
    .ct-kor .aksi { display: flex; gap: 9px; margin-top: 12px; }

    /* riwayat */
    .ct-riw h6 { display: flex; align-items: center; gap: 8px; font-size: 11.5px; font-weight: 800; letter-spacing: .5px; color: #7A5B43; margin: 0 0 10px; }
    .ct-riw .pil { background: #F1E4D3; color: #B79A82; border-radius: 999px; font-size: 10.5px; padding: 2px 8px; }
    .ct-riw-item { background: #fff; border: 1px solid #EFE0CE; border-radius: 13px; padding: 12px 14px; display: flex; align-items: center; gap: 11px; margin-bottom: 8px; box-shadow: 0 1px 2px rgba(70,21,0,.04); }
    .ct-riw-badge { flex: none; font-size: 10px; font-weight: 800; letter-spacing: .4px; border-radius: 7px; padding: 5px 8px; }
    .ct-riw-badge.bersama { background: #E4ECFB; color: #3C5FA8; }
    .ct-riw-badge.koreksi { background: #F1E9DF; color: #8A6A52; }
    .ct-riw-badge.cuti { background: #FBEFD9; color: #9A6614; }
    .ct-riw-item .mid { flex: 1; min-width: 0; }
    .ct-riw-item .mid .a { font-size: 13px; font-weight: 700; color: #3B1500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ct-riw-item .mid .b { font-size: 11px; color: #9A7256; }
    .ct-riw-item .kanan { text-align: right; }
    .ct-riw-item .kanan .n { font-size: 14px; font-weight: 800; color: #5A3A22; }
    .ct-riw-item .kanan .n.plus { color: #2F8E55; }
    .ct-riw-item .kanan .t { font-size: 10.5px; color: #9A7256; }
    .ct-riw-kosong { border: 1px dashed #E2C8A8; border-radius: 13px; padding: 16px; text-align: center; font-size: 12.5px; color: #9A7256; }

    /* wizard cuti bersama */
    .ct-steps { display: flex; align-items: center; gap: 0; background: #F6ECE0; border-bottom: 1px solid #EEDFCD; padding: 13px 22px; }
    .ct-step { display: flex; align-items: center; gap: 8px; }
    .ct-step .dot { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; font-size: 11.5px; font-weight: 800; background: #E7D6C2; color: #A9866A; }
    .ct-step.aktif .dot { background: #3A3A36; color: #fff; }
    .ct-step.beres .dot { background: #2F8E55; color: #fff; }
    .ct-step .sl { font-size: 11.5px; font-weight: 700; color: #A9866A; }
    .ct-step.aktif .sl { color: #3B1500; }
    .ct-step-garis { flex: 1; height: 1px; background: #E1CFB8; margin: 0 10px; min-width: 18px; }
    .ct-cb-field label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #A9866A; margin-bottom: 5px; }
    .ct-cb-field input { width: 100%; border: 1px solid #E7D3BE; border-radius: 10px; padding: 10px 12px; font-family: inherit; font-size: 13px; color: #3B1500; background: #fff; }
    .ct-preset { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 8px; }
    .ct-preset button { background: #F4E7D6; color: #7A5B43; border: 0; border-radius: 20px; padding: 6px 12px; font-family: inherit; font-size: 11.5px; font-weight: 700; cursor: pointer; }
    .ct-cb-status { display: flex; justify-content: space-between; background: #F1E9DF; border-radius: 10px; padding: 9px 13px; font-size: 12px; font-weight: 700; color: #5A3A22; }
    .ct-cb-row { display: flex; align-items: center; gap: 11px; padding: 10px 13px; background: #fff; border: 1px solid #EFE0CE; border-radius: 12px; margin-bottom: 7px; cursor: pointer; }
    .ct-cb-row.off { opacity: .5; }
    .ct-cb-check { width: 20px; height: 20px; border-radius: 6px; border: 1.5px solid #C9AE94; flex: none; display: grid; place-items: center; color: #fff; background: #fff; }
    .ct-cb-row:not(.off) .ct-cb-check { background: #2F8E55; border-color: #2F8E55; }
    .ct-cb-row .ct-av { width: 32px; height: 32px; font-size: 12.5px; }
    .ct-cb-row .nm { flex: 1; min-width: 0; font-size: 13px; font-weight: 700; color: #3B1500; }
    .ct-cb-row .nm small { display: block; font-weight: 600; color: #A9866A; font-size: 11px; }
    .ct-cb-row .sal { font-size: 12.5px; font-weight: 800; }
    .ct-cb-tools { display: flex; gap: 8px; }
    .ct-cb-tools .ct-btn { padding: 8px 12px; font-size: 12px; }
    .ct-cb-ringkas { background: linear-gradient(120deg, #2C2C29, #3A3A36); color: #F3E7DA; border-radius: 14px; padding: 16px 18px; font-size: 20px; font-weight: 800; line-height: 1.4; }
    .ct-cb-ringkas .eye { font-size: 12px; font-weight: 700; letter-spacing: .6px; color: #B7A48F; margin-bottom: 6px; }
    .ct-cb-ringkas b { color: #F5C98A; }
    .ct-cb-ringkas .sub { font-size: 12px; font-weight: 600; color: #B7A48F; margin-top: 7px; }
    .ct-tintbtn { border: 1px solid #F0CFC8; border-radius: 11px; background: #FBE3DE; color: #C0392B; padding: 8px 12px; font-family: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
    .ct-cb-infos { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .ct-cb-info { background: #fff; border-radius: 12px; padding: 11px 14px; box-shadow: 0 1px 2px rgba(70,21,0,.04); }
    .ct-cb-info .l { font-size: 10px; font-weight: 700; color: #A9866A; text-transform: uppercase; }
    .ct-cb-info .v { font-size: 13.5px; font-weight: 800; color: #3B1500; margin-top: 2px; }
    .ct-cb-chips { display: flex; flex-wrap: wrap; gap: 7px; }
    .ct-cb-chip { display: inline-flex; align-items: center; gap: 6px; background: #F4E7D6; border: 0; border-radius: 20px; padding: 5px 11px; font-size: 11.5px; font-weight: 700; color: #7A5B43; }
    .ct-cb-chip .tag { color: #B07B45; font-size: 10px; font-weight: 700; }

    /* toast */
    .ct-toast { position: fixed; left: 50%; bottom: 26px; transform: translate(-50%, 12px); background: #2F3330; color: #F3E7DA; border-radius: 12px; padding: 12px 16px; display: flex; align-items: center; gap: 11px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; font-weight: 600; z-index: 1100; box-shadow: 0 12px 34px rgba(20,10,4,.34); opacity: 0; transition: opacity 200ms, transform 200ms; pointer-events: none; }
    .ct-toast.on { opacity: 1; transform: translate(-50%, 0); pointer-events: auto; }
    .ct-toast .ck { color: #7BE0A2; }
    .ct-toast form { margin: 0; }
    .ct-toast button { background: rgba(255,255,255,.14); color: #FFDFA6; border: 0; border-radius: 8px; padding: 6px 11px; font-family: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
</style>
@endsection

@section('content')
@php
    $svg = [
        'plus'     => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'kalender' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'cari'     => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>',
        'alert'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'daftar'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
        'chev'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>',
        'pensil'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>',
        'cek'      => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
    ];
    $hariIni = \Carbon\Carbon::today()->translatedFormat('l, j M Y');
@endphp

<div class="ct-wrap">

    {{-- kepala --}}
    <div class="ct-head">
        <div>
            <h1 class="ct-title">Cuti</h1>
            <div class="ct-subtitle">Rekap saldo cuti · {{ $hariIni }} <span class="hariini">(Hari Ini)</span></div>
        </div>
        <div class="ct-head-btns">
            <button type="button" class="ct-btn ct-btn-sec" id="ctBtnKb">{!! $svg['plus'] !!} Mulai kontrak baru</button>
            <button type="button" class="ct-btn ct-btn-pri" id="ctBtnCb">{!! $svg['kalender'] !!} Cuti bersama</button>
        </div>
    </div>

    {{-- statistik (selalu roster penuh, bukan hasil filter) --}}
    <div class="ct-stats">
        <div class="ct-stat">
            <div class="lbl">Karyawan ber-jatah</div>
            <div class="val num">{{ $stats['total'] }}</div>
            <div class="hint">di {{ count($branches) }} cabang · jatah 12 hari</div>
        </div>
        <div class="ct-stat minus">
            <div class="lbl">Sedang minus</div>
            <div class="val num">{{ $stats['minus'] }}</div>
            <div class="hint">perlu ganti hari</div>
        </div>
        <div class="ct-stat kontrak">
            <div class="lbl">Kontrak belum ada</div>
            <div class="val num">{{ $stats['tanpaKontrak'] }}</div>
            <div class="hint">tanggal sedang dikumpulkan</div>
        </div>
        <div class="ct-stat rata">
            <div class="lbl">Rata-rata sisa</div>
            <div class="val num">{{ number_format($stats['rata'], 1, ',', '.') }}</div>
            <div class="hint">hari per karyawan</div>
        </div>
    </div>

    {{-- toolbar --}}
    <div class="ct-toolbar">
        <div class="ct-search">
            {!! $svg['cari'] !!}
            <input type="text" id="ctCari" placeholder="Cari nama karyawan…" autocomplete="off">
        </div>
        <select class="ct-select" id="ctCabang">
            <option value="">Semua Cabang</option>
            @foreach($branches as $b)
                <option value="{{ $b }}">{{ $b }}</option>
            @endforeach
        </select>
        <button type="button" class="ct-minusbtn" id="ctMinusOnly">{!! $svg['alert'] !!} Hanya yang minus</button>
        <div class="ct-count">{!! $svg['daftar'] !!} Menampilkan <b class="num" id="ctJumlah">0</b>&nbsp;karyawan</div>
    </div>

    {{-- tabel --}}
    <div class="ct-card">
        <div class="ct-thead">
            <div>KARYAWAN</div><div>SISA CUTI</div><div>TERPAKAI PERIODE INI</div><div>KONTRAK</div><div></div>
        </div>
        <div id="ctRows"></div>
        <div class="ct-empty" id="ctKosong" hidden>
            <b>Tidak ada karyawan yang cocok</b>
            <span>Coba ubah kata kunci atau reset filter.</span>
        </div>
        <div class="ct-foot">
            <span>Menampilkan <span class="num" id="ctFootN">0</span> dari <span class="num">{{ $stats['total'] }}</span> karyawan ber-jatah</span>
            <span>Eksekutif tanpa jatah tidak ditampilkan</span>
        </div>
    </div>
</div>

{{-- ══ scrim + drawer orang ══ --}}
<div class="ct-scrim" id="ctScrim"></div>

<div class="ct-drawer" id="ctDrawerOrang" aria-hidden="true">
    <div class="ct-dhead">
        <div class="eyebrow">SALDO CUTI</div>
        <div class="who">
            <span class="ct-av" id="ctOrAv"></span>
            <div><div class="nm" id="ctOrNama"></div><div class="rl" id="ctOrRole"></div></div>
        </div>
        <div id="ctOrChip"></div>
        <button type="button" class="tutup js-ct-tutup" aria-label="Tutup">✕</button>
    </div>
    <div class="ct-dbody">
        <div class="ct-saldo">
            <div class="big"><div class="n num" id="ctOrSisa"></div><div class="l" id="ctOrSisaL">hari sisa</div></div>
            <div class="sep"></div>
            <div style="flex:1">
                <div class="status" id="ctOrStatus"></div>
                <div class="ministats">
                    <div><div class="l">Jatah</div><div class="v num" id="ctOrJatah"></div></div>
                    <div><div class="l">Terpakai</div><div class="v num" id="ctOrPakai"></div></div>
                    <div><div class="l">Cuti bersama</div><div class="v num" id="ctOrBersama"></div></div>
                </div>
            </div>
        </div>

        {{-- form koreksi (muncul saat tombol Koreksi diklik) --}}
        <form class="ct-kor" id="ctKorForm" method="POST" action="{{ route('absensi.cuti.koreksi') }}" hidden>
            @csrf
            <input type="hidden" name="user_id" id="ctKorUid">
            <input type="hidden" name="arah" id="ctKorArah" value="kembalikan">
            <h6>KOREKSI SALDO</h6>
            <div class="ct-kor-toggle">
                <button type="button" data-arah="kembalikan" class="on">Kembalikan (ganti hari)</button>
                <button type="button" data-arah="kurangi">Kurangi saldo</button>
            </div>
            <div class="baris">
                <div><label>Jumlah hari</label><input type="number" name="hari" id="ctKorHari" value="1" min="0.5" max="12" step="0.5" required></div>
                <div><label>Catatan</label><input type="text" name="catatan" id="ctKorCatatan" placeholder="mis. ganti hari libur nasional" maxlength="200" required></div>
            </div>
            <div class="ct-info" id="ctKorPreview">Saldo bertambah 1 hari (karyawan ganti hari kerja).</div>
            <div class="aksi">
                <button type="submit" class="ct-btn ct-btn-pri">Simpan koreksi</button>
                <button type="button" class="ct-btn ct-btn-sec" id="ctKorBatal">Batal</button>
            </div>
        </form>

        <div class="ct-riw">
            <h6>RIWAYAT POTONGAN <span class="pil num" id="ctOrRiwN">0</span></h6>
            <div id="ctOrRiwayat"></div>
        </div>
    </div>
    <div class="ct-dfoot">
        <button type="button" class="ct-btn ct-btn-sec" id="ctBtnKor">{!! $svg['pensil'] !!} Koreksi</button>
        <button type="button" class="ct-btn ct-btn-pri" id="ctBtnKbDariOrang">{!! $svg['plus'] !!} Mulai kontrak baru</button>
    </div>
</div>

{{-- ══ drawer cuti bersama (wizard 3 langkah) ══ --}}
<div class="ct-drawer lebar" id="ctDrawerCb" aria-hidden="true">
    <div class="ct-dhead">
        <div class="who">
            <span class="ct-dico">{!! $svg['kalender'] !!}</span>
            <div>
                <div class="eyebrow">CUTI BERSAMA</div>
                <div class="nm" id="ctCbJudul">Atur tanggal &amp; label</div>
            </div>
        </div>
        <button type="button" class="tutup js-ct-tutup" aria-label="Tutup">✕</button>
    </div>
    <div class="ct-steps" id="ctCbSteps">
        <div class="ct-step" data-s="1"><span class="dot num">1</span><span class="sl">Tanggal &amp; label</span></div>
        <div class="ct-step-garis"></div>
        <div class="ct-step" data-s="2"><span class="dot num">2</span><span class="sl">Pilih orang</span></div>
        <div class="ct-step-garis"></div>
        <div class="ct-step" data-s="3"><span class="dot num">3</span><span class="sl">Terapkan</span></div>
    </div>
    <div class="ct-dbody">
        {{-- langkah 1 --}}
        <div id="ctCbS1">
            <div class="ct-cb-field" style="margin-bottom:14px">
                <label>Tanggal cuti bersama</label>
                <input type="date" id="ctCbTanggal">
            </div>
            <div class="ct-cb-field" style="margin-bottom:14px">
                <label>Label</label>
                <input type="text" id="ctCbLabel" placeholder="mis. Cuti Bersama Idul Fitri" maxlength="120">
                <div class="ct-preset">
                    <button type="button">Cuti Bersama Idul Fitri</button>
                    <button type="button">Cuti Bersama Tahun Baru</button>
                    <button type="button">Cuti Bersama Kemerdekaan</button>
                    <button type="button">Cuti Bersama Natal</button>
                </div>
            </div>
            <div class="ct-cb-field" style="margin-bottom:14px">
                <label>Potong berapa hari</label>
                <input type="number" id="ctCbHari" value="1" min="0.5" max="3" step="0.5" style="width:96px">
            </div>
            <div class="ct-info">Semua karyawan ber-jatah akan tercentang di langkah berikutnya. Kamu bisa hilangkan centang orang yang dikecualikan (mis. yang sedang minus / ganti hari).</div>
        </div>
        {{-- langkah 2 --}}
        <div id="ctCbS2" hidden>
            <div style="display:flex;gap:9px;margin-bottom:11px;flex-wrap:wrap">
                <div class="ct-search" style="max-width:none">
                    {!! $svg['cari'] !!}
                    <input type="text" id="ctCbCari" placeholder="Cari nama…" autocomplete="off">
                </div>
                <div class="ct-cb-tools">
                    <button type="button" class="ct-tintbtn" id="ctCbKecualiMinus">Kecualikan yang minus</button>
                    <button type="button" class="ct-btn ct-btn-sec" id="ctCbSemua">Pilih semua</button>
                </div>
            </div>
            <div class="ct-cb-status" style="margin-bottom:9px">
                <span id="ctCbStatKena"></span><span id="ctCbStatKecuali"></span>
            </div>
            <div id="ctCbDaftar"></div>
        </div>
        {{-- langkah 3 --}}
        <div id="ctCbS3" hidden>
            <div class="ct-cb-ringkas" style="margin-bottom:12px">
                <div class="eye">RINGKASAN</div>
                <div id="ctCbRingkas"></div>
                <div class="sub" id="ctCbRingkasSub"></div>
            </div>
            <div class="ct-cb-infos" style="margin-bottom:12px">
                <div class="ct-cb-info"><div class="l">Tanggal</div><div class="v num" id="ctCbInfoTgl"></div></div>
                <div class="ct-cb-info"><div class="l">Label</div><div class="v" id="ctCbInfoLabel"></div></div>
            </div>
            <div id="ctCbKecualiWrap" hidden>
                <h6 style="font-size:11.5px;font-weight:800;color:#7A5B43;margin:0 0 8px">DIKECUALIKAN (<span class="num" id="ctCbKecualiN">0</span>)</h6>
                <div class="ct-cb-chips" id="ctCbKecualiChips"></div>
            </div>
        </div>
    </div>
    <div class="ct-dfoot">
        <button type="button" class="ct-btn ct-btn-sec" id="ctCbKembali" hidden>Kembali</button>
        <button type="button" class="ct-btn ct-btn-pri" id="ctCbLanjut">Lanjut pilih orang {!! $svg['chev'] !!}</button>
        <form method="POST" action="{{ route('absensi.cuti.bersama') }}" id="ctCbForm" style="flex:1;display:none">
            @csrf
            <input type="hidden" name="tanggal" id="ctCbFTanggal">
            <input type="hidden" name="label" id="ctCbFLabel">
            <input type="hidden" name="hari" id="ctCbFHari">
            <input type="hidden" name="kecuali" id="ctCbFKecuali">
            <button type="submit" class="ct-btn ct-btn-green" style="width:100%;justify-content:center">{!! $svg['cek'] !!} Terapkan cuti bersama</button>
        </form>
    </div>
</div>

{{-- ══ drawer kontrak baru ══ --}}
<div class="ct-drawer sedang" id="ctDrawerKb" aria-hidden="true">
    <div class="ct-dhead">
        <div class="who">
            <span class="ct-dico">{!! $svg['plus'] !!}</span>
            <div>
                <div class="eyebrow">CUTI</div>
                <div class="nm">Mulai kontrak baru</div>
            </div>
        </div>
        <button type="button" class="tutup js-ct-tutup" aria-label="Tutup">✕</button>
    </div>
    <form method="POST" action="{{ route('absensi.cuti.kontrak') }}" style="display:flex;flex-direction:column;flex:1;min-height:0">
        @csrf
        <div class="ct-dbody">
            <div class="ct-info">Saldo di-reset ke <b>12 hari</b>; kalau karyawan sedang minus, utangnya ikut terbawa (12 dikurangi utang). Riwayat periode lama diarsipkan. Tanggal boleh dikosongkan dulu bila belum diketahui.</div>
            <div class="ct-cb-field">
                <label>Karyawan</label>
                <select name="user_id" id="ctKbUser" class="ct-select" style="width:100%" required>
                    <option value="">— Pilih karyawan —</option>
                </select>
            </div>
            <div class="ct-cb-field">
                <label>Tanggal mulai kontrak</label>
                <input type="date" name="tanggal" id="ctKbTanggal">
                <div style="font-size:11.5px;color:#9A7256;margin-top:6px" id="ctKbHint">Tanggal dikosongkan — akan tampil badge "tanggal kontrak belum ada".</div>
            </div>
        </div>
        <div class="ct-dfoot">
            <button type="submit" class="ct-btn ct-btn-pri">Mulai kontrak &amp; reset saldo</button>
            <button type="button" class="ct-btn ct-btn-sec js-ct-tutup">Batal</button>
        </div>
    </form>
</div>

{{-- ══ toast flash ══ --}}
@if(session('success') || session('error'))
    <div class="ct-toast on" id="ctToast">
        <span class="ck">{!! session('error') ? $svg['alert'] : $svg['cek'] !!}</span>
        <span>{{ session('success') ?: session('error') }}</span>
        @if(session('cb_undo'))
            <form method="POST" action="{{ route('absensi.cuti.bersama.undo') }}">
                @csrf
                <input type="hidden" name="ref" value="{{ session('cb_undo')['ref'] }}">
                <input type="hidden" name="label" value="{{ session('cb_undo')['label'] }}">
                <button type="submit">Urungkan</button>
            </form>
        @endif
    </div>
@endif

<script>
(function () {
    'use strict';
    var ROSTER  = @json($roster);
    var PALET   = ['#8A3A12','#A8481B','#2F8E55','#3C5FA8','#B5701A','#7A3415','#5A6B2E','#8E2419'];
    var MINUS   = '\u2212'; // tanda minus asli, bukan hyphen

    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    };
    var fmt = function (n) { // 6.5 -> "6,5" · 6 -> "6" · -2 -> "−2"
        var a = Math.abs(n), s = (a % 1 === 0) ? String(a) : String(a).replace('.', ',');
        return (n < 0 ? MINUS : '') + s;
    };
    var warna = function (sisa) { return sisa < 0 ? 'ct-c-min' : (sisa === 0 ? 'ct-c-nol' : 'ct-c-pos'); };
    var byId = {};
    ROSTER.forEach(function (o) { byId[o.uid] = o; });

    /* ── filter + render tabel ── */
    var elRows = document.getElementById('ctRows');
    var fCari = '', fCabang = '', fMinus = false;

    function tampil() {
        return ROSTER.filter(function (o) {
            if (fCari && o.nama.toLowerCase().indexOf(fCari) === -1) return false;
            if (fCabang && o.cabang !== fCabang) return false;
            if (fMinus && o.sisa >= 0) return false;
            return true;
        });
    }

    function renderRows() {
        var list = tampil();
        elRows.innerHTML = list.map(function (o) {
            var note = o.sisa < 0
                ? '<div class="note ct-c-min">lebih ' + fmt(Math.abs(o.sisa)) + ' hari — ganti hari</div>'
                : (o.sisa === 0 ? '<div class="note ct-c-nol">jatah habis</div>' : '');
            var pakaiSub = o.bersama > 0 ? fmt(o.bersama) + ' dari cuti bersama' : 'tanpa cuti bersama';
            var kontrak = o.kontrak
                ? '<span class="num">s/d ' + esc(o.kontrak) + '</span>'
                : '<span class="ct-badge-kuning"><i></i>Tanggal belum ada</span>';
            return '<div class="ct-prow ' + (o.sisa < 0 ? 'is-minus' : '') + '" data-uid="' + esc(o.uid) + '">'
                + '<div class="ct-who"><span class="ct-av" style="background:' + PALET[o.av % PALET.length] + '">' + esc(o.inisial) + '</span>'
                +   '<div style="min-width:0"><div class="nm">' + esc(o.nama) + '</div><div class="rl">' + esc(o.role) + ' · ' + esc(o.cabang) + '</div></div></div>'
                + '<div class="ct-sisa"><span class="n num ' + warna(o.sisa) + '">' + fmt(o.sisa) + '</span><span class="h ' + warna(o.sisa) + '">hari</span>' + note + '</div>'
                + '<div class="ct-pakai"><div class="a num">' + fmt(o.terpakai) + ' hari</div><div class="b">' + esc(pakaiSub) + '</div></div>'
                + '<div class="ct-kontrak">' + kontrak + '</div>'
                + '<div class="ct-chev">{!! $svg['chev'] !!}</div>'
                + '</div>';
        }).join('');
        document.getElementById('ctKosong').hidden = list.length > 0;
        document.getElementById('ctJumlah').textContent = list.length;
        document.getElementById('ctFootN').textContent = list.length;
    }

    document.getElementById('ctCari').addEventListener('input', function () { fCari = this.value.trim().toLowerCase(); renderRows(); });
    document.getElementById('ctCabang').addEventListener('change', function () { fCabang = this.value; renderRows(); });
    document.getElementById('ctMinusOnly').addEventListener('click', function () { fMinus = !fMinus; this.classList.toggle('on', fMinus); renderRows(); });

    /* ── drawer helper ── */
    var scrim = document.getElementById('ctScrim');
    var buka = function (d) { scrim.classList.add('on'); d.classList.add('on'); d.setAttribute('aria-hidden', 'false'); };
    var tutupSemua = function () {
        scrim.classList.remove('on');
        ['ctDrawerOrang','ctDrawerCb','ctDrawerKb'].forEach(function (id) {
            var d = document.getElementById(id);
            d.classList.remove('on'); d.setAttribute('aria-hidden', 'true');
        });
    };
    scrim.addEventListener('click', tutupSemua);
    document.querySelectorAll('.js-ct-tutup').forEach(function (b) { b.addEventListener('click', tutupSemua); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutupSemua(); });

    /* ── drawer orang ── */
    var orangAktif = null;
    elRows.addEventListener('click', function (e) {
        var row = e.target.closest('.ct-prow');
        if (row) bukaOrang(byId[row.getAttribute('data-uid')]);
    });

    function bukaOrang(o) {
        if (!o) return;
        orangAktif = o;
        document.getElementById('ctOrAv').style.background = PALET[o.av % PALET.length];
        document.getElementById('ctOrAv').textContent = o.inisial;
        document.getElementById('ctOrNama').textContent = o.nama;
        document.getElementById('ctOrRole').textContent = o.role + ' · ' + o.cabang;
        document.getElementById('ctOrChip').innerHTML = o.kontrak
            ? '<span class="ct-chip num">Kontrak s/d ' + esc(o.kontrak) + '</span>'
            : '<span class="ct-chip amber">Tanggal kontrak belum ada</span>';
        var elSisa = document.getElementById('ctOrSisa');
        elSisa.textContent = fmt(o.sisa);
        elSisa.className = 'n num ' + warna(o.sisa);
        document.getElementById('ctOrSisaL').className = 'l ' + warna(o.sisa);
        var st = document.getElementById('ctOrStatus');
        if (o.sisa < 0) { st.textContent = 'Melebihi jatah ' + fmt(Math.abs(o.sisa)) + ' hari — perlu ganti hari kerja.'; st.className = 'status ct-c-min'; }
        else if (o.sisa === 0) { st.textContent = 'Jatah cuti sudah habis untuk periode ini.'; st.className = 'status ct-c-nol'; }
        else { st.textContent = 'Masih ada ' + fmt(o.sisa) + ' hari cuti tersisa periode ini.'; st.className = 'status ct-c-pos'; }
        document.getElementById('ctOrJatah').textContent = fmt(o.saldoAwal);
        document.getElementById('ctOrPakai').textContent = fmt(o.terpakai);
        document.getElementById('ctOrBersama').textContent = fmt(o.bersama);

        document.getElementById('ctOrRiwN').textContent = o.mutasi.length;
        document.getElementById('ctOrRiwayat').innerHTML = o.mutasi.length
            ? o.mutasi.map(function (m) {
                var badge = m.jenis === 'cuti_bersama' ? ['bersama','BERSAMA']
                    : (m.jenis === 'cuti' ? ['cuti','CUTI'] : ['koreksi','KOREKSI']);
                var plus = m.hari < 0;
                var nilai = m.hari === 0 ? '0' : (plus ? '+' + fmt(Math.abs(m.hari)) : MINUS + fmt(m.hari));
                return '<div class="ct-riw-item">'
                    + '<span class="ct-riw-badge ' + badge[0] + '">' + badge[1] + '</span>'
                    + '<div class="mid"><div class="a">' + esc(m.keterangan || (m.jenis === 'cuti' ? 'Cuti' : 'Koreksi')) + '</div><div class="b">' + esc(m.oleh) + '</div></div>'
                    + '<div class="kanan"><div class="n num' + (plus ? ' plus' : '') + '">' + nilai + '</div><div class="t num">' + esc(m.tanggal) + '</div></div>'
                    + '</div>';
            }).join('')
            : '<div class="ct-riw-kosong">Belum ada potongan di periode ini.</div>';

        korReset();
        buka(document.getElementById('ctDrawerOrang'));
    }

    /* koreksi */
    var korForm = document.getElementById('ctKorForm');
    function korReset() { korForm.hidden = true; }
    document.getElementById('ctBtnKor').addEventListener('click', function () {
        korForm.hidden = false;
        document.getElementById('ctKorUid').value = orangAktif ? orangAktif.uid : '';
        korPreview();
        document.getElementById('ctKorCatatan').focus();
    });
    document.getElementById('ctKorBatal').addEventListener('click', korReset);
    document.querySelectorAll('.ct-kor-toggle button').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.ct-kor-toggle button').forEach(function (x) { x.classList.remove('on'); });
            b.classList.add('on');
            document.getElementById('ctKorArah').value = b.getAttribute('data-arah');
            korPreview();
        });
    });
    document.getElementById('ctKorHari').addEventListener('input', korPreview);
    function korPreview() {
        var n = parseFloat(document.getElementById('ctKorHari').value) || 0;
        var arah = document.getElementById('ctKorArah').value;
        document.getElementById('ctKorPreview').textContent = arah === 'kembalikan'
            ? 'Saldo bertambah ' + fmt(n) + ' hari (karyawan ganti hari kerja).'
            : 'Saldo berkurang ' + fmt(n) + ' hari.';
    }

    /* ── drawer kontrak baru ── */
    var kbSelect = document.getElementById('ctKbUser');
    kbSelect.innerHTML = '<option value="">— Pilih karyawan —</option>' + ROSTER.map(function (o) {
        return '<option value="' + esc(o.uid) + '">' + esc(o.nama) + ' · ' + esc(o.cabang) + '</option>';
    }).join('');
    function bukaKb(uid) {
        tutupSemua();
        // reset state basi — jangan sampai karyawan pilihan sebelumnya nempel
        kbSelect.value = uid || '';
        var t = document.getElementById('ctKbTanggal');
        t.value = '';
        document.getElementById('ctKbHint').textContent = 'Tanggal dikosongkan — akan tampil badge "tanggal kontrak belum ada".';
        buka(document.getElementById('ctDrawerKb'));
    }
    document.getElementById('ctBtnKb').addEventListener('click', function () { bukaKb(null); });
    document.getElementById('ctBtnKbDariOrang').addEventListener('click', function () { bukaKb(orangAktif && orangAktif.uid); });
    document.getElementById('ctKbTanggal').addEventListener('input', function () {
        var hint = document.getElementById('ctKbHint');
        if (!this.value) { hint.textContent = 'Tanggal dikosongkan — akan tampil badge "tanggal kontrak belum ada".'; return; }
        var d = new Date(this.value + 'T00:00:00');
        d.setFullYear(d.getFullYear() + 1); d.setDate(d.getDate() - 1);
        hint.textContent = 'Kontrak berlaku s/d ' + d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + '.';
    });

    /* ── wizard cuti bersama ── */
    var cbStep = 1, cbKecuali = {}, cbCari = '';
    document.getElementById('ctBtnCb').addEventListener('click', function () {
        cbStep = 1; cbKecuali = {}; cbCari = '';
        document.getElementById('ctCbCari').value = '';
        document.getElementById('ctCbTanggal').value = '';
        document.getElementById('ctCbLabel').value = '';
        document.getElementById('ctCbHari').value = '1';
        tutupSemua(); cbRender();
        buka(document.getElementById('ctDrawerCb'));
    });
    document.querySelectorAll('.ct-preset button').forEach(function (b) {
        b.addEventListener('click', function () { document.getElementById('ctCbLabel').value = b.textContent; });
    });
    document.getElementById('ctCbLanjut').addEventListener('click', function () {
        if (cbStep === 1) {
            if (!document.getElementById('ctCbTanggal').value) { alert('Isi dulu tanggal cuti bersamanya.'); return; }
            if (!document.getElementById('ctCbLabel').value.trim()) { alert('Isi dulu label cuti bersamanya.'); return; }
            var h = parseFloat(document.getElementById('ctCbHari').value);
            if (!(h >= 0.5 && h <= 3)) { alert('Jumlah hari harus antara 0,5 dan 3.'); return; }
        }
        cbStep++; cbRender();
    });
    document.getElementById('ctCbKembali').addEventListener('click', function () { cbStep--; cbRender(); });
    document.getElementById('ctCbCari').addEventListener('input', function () { cbCari = this.value.trim().toLowerCase(); cbRenderDaftar(); });
    document.getElementById('ctCbKecualiMinus').addEventListener('click', function () {
        ROSTER.forEach(function (o) { if (o.sisa < 0) cbKecuali[o.uid] = true; });
        cbRenderDaftar();
    });
    document.getElementById('ctCbSemua').addEventListener('click', function () { cbKecuali = {}; cbRenderDaftar(); });
    document.getElementById('ctCbDaftar').addEventListener('click', function (e) {
        var row = e.target.closest('.ct-cb-row');
        if (!row) return;
        var uid = row.getAttribute('data-uid');
        if (cbKecuali[uid]) delete cbKecuali[uid]; else cbKecuali[uid] = true;
        cbRenderDaftar();
    });

    function cbRender() {
        document.getElementById('ctCbS1').hidden = cbStep !== 1;
        document.getElementById('ctCbS2').hidden = cbStep !== 2;
        document.getElementById('ctCbS3').hidden = cbStep !== 3;
        document.getElementById('ctCbJudul').textContent =
            cbStep === 1 ? 'Atur tanggal & label' : (cbStep === 2 ? 'Pilih karyawan' : 'Tinjau & terapkan');
        document.querySelectorAll('#ctCbSteps .ct-step').forEach(function (s) {
            var n = parseInt(s.getAttribute('data-s'), 10);
            s.classList.toggle('aktif', n === cbStep);
            s.classList.toggle('beres', n < cbStep);
        });
        document.getElementById('ctCbKembali').hidden = cbStep === 1;
        var lanjut = document.getElementById('ctCbLanjut');
        lanjut.style.display = cbStep === 3 ? 'none' : '';
        lanjut.innerHTML = (cbStep === 1 ? 'Lanjut pilih orang ' : 'Lanjut tinjau ') + '{!! $svg['chev'] !!}';
        document.getElementById('ctCbForm').style.display = cbStep === 3 ? '' : 'none';
        if (cbStep === 2) cbRenderDaftar();
        if (cbStep === 3) cbRenderTinjau();
    }

    function cbRenderDaftar() {
        var kena = ROSTER.filter(function (o) { return !cbKecuali[o.uid]; }).length;
        document.getElementById('ctCbStatKena').textContent = kena + ' dari ' + ROSTER.length + ' akan dipotong';
        document.getElementById('ctCbStatKecuali').textContent = (ROSTER.length - kena) + ' dikecualikan';
        document.getElementById('ctCbDaftar').innerHTML = ROSTER
            .filter(function (o) { return !cbCari || o.nama.toLowerCase().indexOf(cbCari) !== -1; })
            .map(function (o) {
                var off = !!cbKecuali[o.uid];
                return '<div class="ct-cb-row ' + (off ? 'off' : '') + '" data-uid="' + esc(o.uid) + '">'
                    + '<span class="ct-cb-check">' + (off ? '' : '{!! $svg['cek'] !!}'.replace('width="15" height="15"', 'width="12" height="12"')) + '</span>'
                    + '<span class="ct-av" style="background:' + PALET[o.av % PALET.length] + '">' + esc(o.inisial) + '</span>'
                    + '<span class="nm">' + esc(o.nama) + '<small>' + esc(o.cabang) + '</small></span>'
                    + '<span class="sal num ' + warna(o.sisa) + '">' + fmt(o.sisa) + ' hari</span>'
                    + '</div>';
            }).join('');
    }

    function cbRenderTinjau() {
        var hari = parseFloat(document.getElementById('ctCbHari').value);
        var tgl = document.getElementById('ctCbTanggal').value;
        var label = document.getElementById('ctCbLabel').value.trim();
        var kecualiIds = Object.keys(cbKecuali);
        var kena = ROSTER.length - kecualiIds.length;

        document.getElementById('ctCbRingkas').innerHTML = 'Akan memotong <b class="num">' + fmt(hari) + ' hari</b> dari <b class="num">' + kena + ' orang</b>';
        document.getElementById('ctCbRingkasSub').textContent = kecualiIds.length
            ? kecualiIds.length + ' orang dikecualikan tidak akan dipotong.'
            : 'Tidak ada yang dikecualikan.';
        document.getElementById('ctCbInfoTgl').textContent = tgl
            ? new Date(tgl + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '(belum dipilih)';
        document.getElementById('ctCbInfoLabel').textContent = label || '—';

        document.getElementById('ctCbKecualiWrap').hidden = kecualiIds.length === 0;
        document.getElementById('ctCbKecualiN').textContent = kecualiIds.length;
        document.getElementById('ctCbKecualiChips').innerHTML = kecualiIds.map(function (uid) {
            var o = byId[uid];
            return '<span class="ct-cb-chip">' + esc(o ? o.nama : uid)
                + (o && o.sisa < 0 ? '<span class="tag">minus</span>' : '') + '</span>';
        }).join('');

        document.getElementById('ctCbFTanggal').value = tgl;
        document.getElementById('ctCbFLabel').value = label;
        document.getElementById('ctCbFHari').value = hari;
        document.getElementById('ctCbFKecuali').value = kecualiIds.join(',');
    }

    /* ── toast auto-hilang ── */
    var toast = document.getElementById('ctToast');
    if (toast) setTimeout(function () { toast.classList.remove('on'); }, toast.querySelector('form') ? 6000 : 2800);

    renderRows();
})();
</script>
@endsection
