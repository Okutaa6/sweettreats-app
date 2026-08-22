@extends('layouts.app')

{{--
    Kehadiran — matriks karyawan × tanggal gaya kalender (reskin dari
    handoff_kehadiran/Kehadiran.dc.html, 2026-07).

    Tiga view: Bulan (kode huruf) / Pekan (ikon+kata+jam) / Hari (daftar per
    cabang). <900px otomatis view Hari + bottom sheet. Data dirender client-side
    dari payload KH_DATA (server tetap sumber datanya, satu bulan per load).
    Kamus status & kartu detail: absensi/_kartu_absen_harian.blade.php (KAH).
--}}

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/finance.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/finance.sweettreats.css')) ?: '1' }}">
<style>
    .kh2 { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #3B1500; }
    .kh2 *, .kh2 *::before, .kh2 *::after { box-sizing: border-box; }
    .kh2 .num { font-variant-numeric: tabular-nums; font-feature-settings: "tnum" 1; }
    .kh2 .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; }
    .kh2-btn { transition: filter .12s ease, background .12s ease; }
    .kh2-btn:hover { filter: brightness(.96); }

    /* ── Toolbar ── */
    .kh2-toolbar { display: flex; align-items: center; gap: 11px; flex-wrap: wrap; margin-bottom: 13px; }
    .kh2-nav { position: relative; display: flex; align-items: center; gap: 4px; background: #fff; border: 1px solid #EFE0CE; border-radius: 13px; padding: 5px; }
    .kh2-nav-arrow { width: 34px; height: 34px; border-radius: 9px; border: none; background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; font-family: inherit; }
    .kh2-nav-label { display: flex; align-items: center; gap: 7px; min-width: 172px; justify-content: center; border: none; background: transparent; font-family: inherit; font-size: 17px; font-weight: 800; color: #3B1500; letter-spacing: -.2px; cursor: pointer; padding: 5px 8px; border-radius: 9px; }
    .kh2-dp { position: absolute; top: calc(100% + 8px); left: 0; z-index: 41; width: 262px; background: #fff; border: 1px solid #EFE0CE; border-radius: 16px; padding: 12px; box-shadow: 0 18px 44px -14px rgba(40,16,0,.32); animation: kh2PopIn .14s ease; display: none; }
    .kh2-dp.on { display: block; }
    .kh2-dp-scrim { position: fixed; inset: 0; z-index: 40; display: none; }
    .kh2-dp-scrim.on { display: block; }
    .kh2-dp-year { font-size: 12px; font-weight: 800; letter-spacing: .5px; color: #A9866A; padding: 0 4px 9px; }
    .kh2-dp-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; }
    .kh2-dp-mo { border: 1px solid #EFE0CE; border-radius: 9px; padding: 9px 0; font-family: inherit; font-size: 12px; font-weight: 700; cursor: pointer; background: #fff; color: #5A3A22; }
    .kh2-dp-mo.cur { background: linear-gradient(135deg, #6B2400, #461500); color: #FBEADB; border-color: transparent; }
    .kh2-dp-mo.today { background: #FBF0DE; color: #8A5A16; }
    .kh2-dp-today { width: 100%; margin-top: 9px; border: 1px solid #E7D3BE; background: #FBF5ED; color: #8A3A12; border-radius: 10px; padding: 9px; font-family: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; }
    .kh2-today-btn { display: inline-flex; align-items: center; gap: 7px; background: linear-gradient(135deg, #6B2400, #461500); color: #FBEADB; border: none; border-radius: 12px; padding: 11px 16px; font-family: inherit; font-size: 13px; font-weight: 800; cursor: pointer; }
    .kh2-tools { display: flex; align-items: center; gap: 9px; margin-left: auto; flex-wrap: wrap; }
    .kh2-search { position: relative; display: flex; align-items: center; }
    .kh2-search svg { position: absolute; left: 11px; pointer-events: none; }
    .kh2-inp { border: 1px solid #E7D3BE; border-radius: 11px; padding: 10px 12px; font-family: inherit; font-size: 13px; color: #3B1500; background: #fff; transition: background .12s, border-color .12s; }
    .kh2-inp:focus { background: #fff; border-color: #B5701A; outline: none; }
    .kh2-inp::placeholder { color: #C7AC92; }
    .kh2-search .kh2-inp { padding-left: 33px; width: 198px; }
    .kh2-select { border: 1px solid #E7D3BE; border-radius: 11px; padding: 10px 12px; font-family: inherit; font-size: 13px; font-weight: 600; color: #3B1500; background: #fff; cursor: pointer; }
    .kh2-views { display: flex; background: #fff; border: 1px solid #EFE0CE; border-radius: 11px; padding: 4px; gap: 3px; }
    .kh2-view-btn { border: none; border-radius: 8px; padding: 8px 15px; font-family: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; background: transparent; color: #8A6A52; }
    .kh2-view-btn.on { background: linear-gradient(135deg, #6B2400, #461500); color: #FBEADB; }

    /* ── Kartu ringkas (bisa diklik untuk menyorot) ── */
    .kh2-chips { display: flex; align-items: stretch; gap: 11px; flex-wrap: wrap; margin-bottom: 13px; }
    .kh2-chip { display: flex; align-items: center; gap: 11px; background: #fff; border: 1px solid #EFE0CE; border-radius: 15px; padding: 11px 17px 11px 14px; font-family: inherit; cursor: pointer; text-align: left; box-shadow: 0 1px 2px rgba(70,21,0,.04); }
    .kh2-chip-ic { width: 30px; height: 30px; flex: none; border-radius: 9px; border: 1px solid transparent; display: flex; align-items: center; justify-content: center; }
    .kh2-chip-col { display: flex; flex-direction: column; align-items: flex-start; line-height: 1.15; }
    .kh2-chip-n { font-size: 21px; font-weight: 800; }
    .kh2-chip-l { font-size: 11px; font-weight: 700; letter-spacing: .3px; color: #9A7256; }
    .kh2-count { display: flex; align-items: center; gap: 10px; margin-left: auto; font-size: 12.5px; color: #9A7256; text-align: right; }

    /* ── Filter aktif ── */
    .kh2-fchips { display: none; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 13px; }
    .kh2-fchips.on { display: flex; }
    .kh2-fchips-k { font-size: 11px; font-weight: 800; letter-spacing: .5px; color: #A9866A; }
    .kh2-libur { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 13px;
                 background: #FDF5F3; border: 1px solid #F3DED8; border-radius: 11px; padding: 9px 13px; }
    .kh2-libur-k { font-size: 11px; font-weight: 800; letter-spacing: .5px; color: #C0392B; }
    .kh2-libur-item { display: inline-flex; align-items: baseline; gap: 7px; font-size: 12px; color: #8A6A52; }
    .kh2-libur-item b { color: #5A3A22; font-weight: 800; }
    .kh2-libur-item i { font-style: normal; color: #A9866A; font-size: 11px; }
    .kh2-fchip { display: inline-flex; align-items: center; gap: 7px; background: #fff; border: 1px solid #E7D3BE; color: #5A3A22; border-radius: 20px; padding: 7px 12px; font-family: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
    .kh2-fclear { border: none; background: transparent; color: #8A3A12; font-family: inherit; font-size: 12px; font-weight: 700; text-decoration: underline; cursor: pointer; padding: 6px 4px; }

    /* ── Matriks ── */
    .kh2-card { background: #fff; border: 1px solid #EFE0CE; border-radius: 18px; overflow: hidden; box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 12px 30px rgba(70,21,0,.05); }
    .kh2-mx { overflow: auto; max-height: calc(100vh - 310px); min-height: 280px; }
    .kh2-mx::-webkit-scrollbar { width: 11px; height: 11px; }
    .kh2-mx::-webkit-scrollbar-thumb { background: #DCC4A8; border-radius: 8px; }
    .kh2-mx::-webkit-scrollbar-track { background: #FBF5EE; }
    .kh2-mx-inner { min-width: max-content; }
    .kh2-mx-head { display: grid; position: sticky; top: 0; z-index: 6; background: #FCF7F1; border-bottom: 1px solid #EDDFCF; }
    .kh2-mx-head-name { position: sticky; left: 0; z-index: 7; background: #FCF7F1; display: flex; align-items: flex-end; padding: 0 14px 9px; font-size: 10px; font-weight: 800; letter-spacing: .7px; color: #A9866A; }
    .kh2-mx-head-rekap { position: sticky; right: 0; z-index: 7; background: #FCF7F1; display: flex; align-items: flex-end; justify-content: flex-end; padding: 0 14px 9px; font-size: 10px; font-weight: 800; letter-spacing: .7px; color: #A9866A; box-shadow: -7px 0 9px -7px rgba(70,21,0,.16); }
    .kh2-hd { display: flex; flex-direction: column; align-items: center; gap: 2px; }
    .kh2-sep { border-bottom: 1px solid #F2E6D8; background: #FBF5ED; padding: 8px 0; }
    .kh2-sep-in { position: sticky; left: 0; display: flex; align-items: center; gap: 9px; padding: 0 14px; width: max-content; max-width: 100vw; }
    .kh2-sep-dot { width: 7px; height: 7px; flex: none; border-radius: 50%; }
    .kh2-sep-label { font-size: 12px; font-weight: 800; color: #5A3A22; letter-spacing: .2px; white-space: nowrap; }
    .kh2-sep-count { font-size: 11.5px; color: #A9866A; font-weight: 600; white-space: nowrap; }
    .kh2-sep-note { display: inline-flex; align-items: center; gap: 5px; background: #F1E9DF; color: #8A6A52; border-radius: 7px; padding: 3px 8px; font-size: 10.5px; font-weight: 700; white-space: nowrap; }
    .kh2-row { display: grid; border-bottom: 1px solid #F6EEE3; transition: background .12s ease; }
    .kh2-row:hover { background: #FDF9F4; }
    .kh2-name-cell { position: sticky; left: 0; z-index: 2; background: #fff; display: flex; align-items: center; gap: 10px; padding: 0 12px 0 14px; min-width: 0; cursor: pointer; box-shadow: 7px 0 9px -7px rgba(70,21,0,.14); transition: background .12s ease; }
    .kh2-row:hover .kh2-name-cell { background: #FDF9F4; }
    .kh2-av { flex: none; border-radius: 50%; color: #FFF3E9; display: flex; align-items: center; justify-content: center; font-weight: 800; letter-spacing: .2px; }
    .kh2-name-tx { min-width: 0; flex: 1; }
    /* chevron nama (pola-interaksi P1): nama → drawer sebulan */
    .kh2-name-chev { flex: none; display: flex; color: #8A3A12; opacity: .8; margin-left: auto; transition: transform .15s ease, opacity .15s ease; }
    .kh2-name-cell:hover .kh2-name-chev { opacity: 1; transform: translateX(2px); }
    .kh2-nm { font-size: 12.5px; font-weight: 700; color: #3B1500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .kh2-nsub { font-size: 10.5px; color: #A9866A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .kh2-cellwrap { position: relative; display: flex; align-items: center; justify-content: center; }
    .kh2-cell { width: 100%; border: 1px solid transparent; display: flex; align-items: center; cursor: pointer; transition: opacity .15s ease, transform .12s ease, box-shadow .12s ease; }
    .kh2-cell:hover { transform: translateY(-1.5px); box-shadow: 0 5px 12px -4px rgba(70,21,0,.3); z-index: 3; }
    .kh2-cell.empty { border: 1px dashed #E7D3BE; background: transparent; pointer-events: none; }
    .kh2-cell.dim { opacity: .2; }
    .kh2-dot { position: absolute; width: 5px; height: 5px; border-radius: 50%; }
    .kh2-tot { position: sticky; right: 0; z-index: 2; background: #fff; display: flex; align-items: center; justify-content: flex-end; gap: 5px; padding: 0 14px; box-shadow: -7px 0 9px -7px rgba(70,21,0,.14); }
    .kh2-empty-state { padding: 46px 20px; text-align: center; }
    .kh2-legend { display: flex; gap: 15px; padding: 12px 18px; background: #FCF7F0; border-top: 1px solid #F2E6D8; flex-wrap: wrap; align-items: center; }
    .kh2-leg { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: #7A5B43; font-weight: 600; }
    .kh2-leg-box { width: 17px; height: 17px; border-radius: 5px; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; font-size: 9.5px; font-weight: 800; }
    .kh2-leg-hint { margin-left: auto; font-size: 11.5px; color: #A9866A; }

    /* ── View Hari ── */
    .kh2-day { display: none; flex-direction: column; gap: 12px; }
    .kh2-day.on { display: flex; }
    .kh2-strip { display: flex; gap: 7px; overflow-x: auto; padding: 3px 1px 5px; scroll-snap-type: x proximity; scrollbar-width: none; }
    .kh2-strip::-webkit-scrollbar { height: 0; }
    .kh2-pill { display: flex; flex-direction: column; align-items: center; gap: 2px; min-width: 46px; flex: none; border-radius: 13px; padding: 9px 6px; font-family: inherit; cursor: pointer; scroll-snap-align: center; border: 1px solid #EFE0CE; background: #fff; }
    .kh2-day-title { display: flex; align-items: baseline; gap: 8px; padding: 0 2px; flex-wrap: wrap; }
    .kh2-day-card { background: #fff; border: 1px solid #EFE0CE; border-radius: 18px; overflow: hidden; }
    .kh2-day-head { display: flex; align-items: center; gap: 8px; padding: 11px 15px; background: #FBF5ED; border-bottom: 1px solid #F2E6D8; flex-wrap: wrap; }
    .kh2-lrow { display: flex; align-items: center; gap: 11px; padding: 12px 15px; border-bottom: 1px solid #F6EEE3; min-height: 56px; transition: background .12s ease; }
    .kh2-lrow:hover { background: #FDF7F0; cursor: pointer; }
    .kh2-lrow:last-child { border-bottom: none; }
    .kh2-spill { display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent; border-radius: 20px; padding: 3px 9px; font-size: 11px; font-weight: 800; letter-spacing: .2px; white-space: nowrap; }

    /* ── Overlay: popover / sheet ── */
    .kh2-scrim { position: fixed; inset: 0; z-index: 1080; display: none; }
    .kh2-scrim.on { display: block; }
    .kh2-scrim.dark { background: rgba(38,18,4,.42); animation: kh2FadeIn .18s ease; }
    .kh2-shell { position: fixed; z-index: 1081; display: none; }
    .kh2-shell.on { display: block; }
    .kh2-shell.pop { width: 346px; background: #fff; border: 1px solid #EFE0CE; border-radius: 18px; box-shadow: 0 20px 50px -12px rgba(40,16,0,.34), 0 2px 6px rgba(40,16,0,.1); animation: kh2PopIn .16s ease; overflow-y: auto; }
    .kh2-shell.sheet { left: 0; right: 0; bottom: 0; background: #fff; border-radius: 20px 20px 0 0; max-height: 88vh; overflow-y: auto; box-shadow: 0 -14px 40px rgba(40,16,0,.28); animation: kh2SheetIn .24s cubic-bezier(.4,0,.2,1); }

    /* ── Drawer sebulan per orang ── */
    .kh2-dscrim { position: fixed; inset: 0; background: rgba(38,18,4,.42); z-index: 1070; display: none; animation: kh2FadeIn .18s ease; }
    .kh2-dscrim.on { display: block; }
    .kh2-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: min(500px, 94vw); z-index: 1071; background: #F4ECE2; display: none; flex-direction: column; box-shadow: -16px 0 46px rgba(40,16,0,.3); animation: kh2DrawerIn .26s cubic-bezier(.4,0,.2,1); }
    .kh2-drawer.on { display: flex; }
    .kh2-dr-head { display: flex; align-items: center; gap: 12px; padding: 17px 19px; background: linear-gradient(120deg, #5A2410, #3A1100); color: #FBEADB; flex: none; }
    .kh2-dr-x { width: 32px; height: 32px; flex: none; border-radius: 9px; border: none; background: rgba(255,255,255,.13); cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .kh2-dr-body { flex: 1; min-height: 0; overflow-y: auto; padding: 15px 19px 24px; display: flex; flex-direction: column; gap: 14px; }
    .kh2-dr-sum { display: flex; gap: 7px; flex-wrap: wrap; }
    .kh2-dr-sum-i { display: flex; flex-direction: column; gap: 1px; min-width: 60px; border: 1px solid transparent; border-radius: 12px; padding: 9px 12px; }
    .kh2-dr-tots { display: flex; gap: 9px; }
    .kh2-dr-tot { flex: 1; background: #FBF5ED; border: 1px solid #F2E6D8; border-radius: 12px; padding: 10px 12px; }
    .kh2-dr-cal { background: #fff; border: 1px solid #EFE0CE; border-radius: 14px; overflow: hidden; }
    .kh2-dr-dow { display: grid; grid-template-columns: repeat(7, minmax(0,1fr)); background: #FBF5ED; border-bottom: 1px solid #F2E6D8; }
    .kh2-dr-week { display: grid; grid-template-columns: repeat(7, minmax(0,1fr)); }
    .kh2-dr-cell { min-height: 74px; border-right: 1px solid #F4EADD; border-bottom: 1px solid #F4EADD; padding: 6px 7px; display: flex; flex-direction: column; gap: 3px; background: #fff; }
    .kh2-dr-cell.blank { background: #FBF5ED; }
    .kh2-dr-cell.today { background: #FBF0DE; }

    @keyframes kh2PopIn { from { opacity: 0; transform: translateY(-6px) scale(.98); } to { opacity: 1; transform: none; } }
    @keyframes kh2SheetIn { from { transform: translateY(100%); } to { transform: translateY(0); } }
    @keyframes kh2DrawerIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
    @keyframes kh2FadeIn { from { opacity: 0; } to { opacity: 1; } }
    @media (prefers-reduced-motion: reduce) {
        .kh2 *, .kh2-shell, .kh2-drawer, .kh2-dp, .kh2-scrim.dark, .kh2-dscrim { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        .kh2-cell:hover { transform: none; }
    }

    @media (max-width: 900px) {
        .kh2-views, .kh2-matrix-wrap { display: none !important; }
        .kh2-search .kh2-inp { width: 100%; }
        .kh2-search { flex: 1 1 160px; }
        .kh2-tools { margin-left: 0; width: 100%; }
        .kh2-nav-label { min-width: 0; font-size: 15px; }
        .kh2-drawer { width: 100vw; }
        .kh2-dr-cell { min-height: 66px; }
    }
</style>
@endsection

@section('content')
<div class="fin-wrap kh2">
    @include('layouts.notif')
    @include('absensi._finance_tabs')

    {{-- Toolbar: navigasi ala Calendar --}}
    <div class="kh2-toolbar">
        <div class="kh2-nav">
            <button type="button" class="kh2-nav-arrow kh2-btn" id="khPrev" title="Bulan sebelumnya">
                <svg width="17" height="17" viewBox="0 0 17 17" fill="none"><path d="M10.5 3.5 5.5 8.5l5 5" stroke="#7A5B43" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="button" class="kh2-nav-label kh2-btn num" id="khRangeBtn" title="Pilih bulan">
                <span id="khRangeLabel">{{ $anchor->translatedFormat('F Y') }}</span>
                <svg width="13" height="13" viewBox="0 0 14 14" fill="none" style="flex:none"><path d="M3.5 5.5 7 9l3.5-3.5" stroke="#A9866A" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="button" class="kh2-nav-arrow kh2-btn" id="khNext" title="Bulan berikutnya">
                <svg width="17" height="17" viewBox="0 0 17 17" fill="none"><path d="M6.5 3.5l5 5-5 5" stroke="#7A5B43" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="kh2-dp-scrim" id="khDpScrim"></div>
            <div class="kh2-dp" id="khDp">
                <div class="kh2-dp-year num">{{ $anchor->year }}</div>
                <div class="kh2-dp-grid" id="khDpGrid"></div>
                <button type="button" class="kh2-dp-today kh2-btn" id="khDpToday">Lompat ke hari ini</button>
            </div>
        </div>
        <button type="button" class="kh2-today-btn kh2-btn" id="khToday">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.2" stroke="#FBEADB" stroke-width="1.5"/><circle cx="8" cy="8" r="2" fill="#FBEADB"/></svg>Hari Ini
        </button>
        <div class="kh2-tools">
            <div class="kh2-search">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.6" stroke="#B79A82" stroke-width="1.5"/><path d="M10.5 10.5 14 14" stroke="#B79A82" stroke-width="1.5" stroke-linecap="round"/></svg>
                <input type="text" class="kh2-inp" id="khQ" placeholder="Cari nama karyawan…" autocomplete="off">
            </div>
            <select class="kh2-select kh2-inp" id="khBranch">
                <option value="">Semua Cabang</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected((string) $branchFilter === (string) $b->id)>{{ $b->name }}</option>
                @endforeach
                <option value="{{ $abgKey }}" @selected($branchFilter === $abgKey)>{{ $abgLabel }}</option>
            </select>
            <div class="kh2-views" id="khViews">
                <button type="button" class="kh2-view-btn kh2-btn" data-view="bulan">Bulan</button>
                <button type="button" class="kh2-view-btn kh2-btn" data-view="pekan">Pekan</button>
                <button type="button" class="kh2-view-btn kh2-btn" data-view="hari">Hari</button>
            </div>
        </div>
    </div>

    {{-- Kartu ringkas Hadir/Telat/Alpa (klik = sorot) --}}
    <div class="kh2-chips" id="khChips"></div>

    {{-- Filter aktif --}}
    <div class="kh2-fchips" id="khFchips"></div>

    {{-- KALENDER LIBUR bulan ini. Sampai 21 Agu 2026 tabel att_work_rule_exception
         tak pernah tampil di layar mana pun — ditulis di Aturan Kerja, dibaca cuma
         oleh mesin rekonsiliasi. Salah catat baru ketahuan setelah harinya lewat.
         Strip ini alat audit: sekali lihat, tahu cabang mana tutup tanggal berapa. --}}
    @if (!empty($liburRingkas))
    <div class="kh2-libur">
        <span class="kh2-libur-k">{{ count($liburRingkas) > 1 ? 'LIBUR BULAN INI' : 'LIBUR' }}</span>
        @foreach ($liburRingkas as $l)
            <span class="kh2-libur-item">
                <b class="num">{{ $l['label'] }}</b>
                <span>{{ $l['alasan'] }}</span>
                <i>{{ $l['cabang'] }}</i>
            </span>
        @endforeach
    </div>
    @endif

    {{-- Matriks (Bulan/Pekan) --}}
    <div class="kh2-matrix-wrap" id="khMatrixWrap">
        <div class="kh2-card">
            <div class="kh2-mx" id="khMx"></div>
            <div class="kh2-legend" id="khLegend"></div>
        </div>
    </div>

    {{-- View Hari --}}
    <div class="kh2-day" id="khDay"></div>
</div>

{{-- Kartu detail: popover (desktop) / bottom sheet (HP) --}}
<div class="kh2-scrim" id="khScrim"></div>
<div class="kh2-shell" id="khShell" role="dialog" aria-modal="true"></div>

{{-- Drawer sebulan per orang --}}
<div class="kh2-dscrim" id="khDscrim"></div>
<div class="kh2-drawer" id="khDrawer" role="dialog" aria-modal="true"></div>

@include('absensi._kartu_absen_harian')
@endsection

@section('js')
@php
    // Payload ringkas untuk client-side render. Kunci cell: s=status, i=jam_in,
    // o=jam_out, t=telat_menit, l=lembur_menit, x=cabang lain, g=single_scan,
    // f=foto [{id,j(enis),w(aktu)}].
    $payloadGroups = [];
    foreach ($groups as $g) {
        $rows = [];
        $gkey = '';
        foreach ($g['rows'] as $r) {
            $gkey = (string) $r['key'];
            $cells = [];
            foreach ($r['cells'] as $d => $c) {
                $cells[$d] = [
                    's' => $c['status'],
                    'i' => $c['jam_in'],
                    'o' => $c['jam_out'],
                    't' => (int) $c['telat'],
                    'l' => (int) $c['lembur'],
                    'x' => array_values($c['lain']),
                    'g' => (int) $c['single'],
                    'f' => array_map(fn ($f) => ['id' => $f['id'], 'j' => $f['jenis'], 'w' => $f['jam'] ?? null], $c['foto']),
                ];
            }
            // lb = hari libur milik orang ini (0=Minggu..6=Sabtu) menurut aturan kerjanya
            // lt = TANGGAL libur kalender bagi orang ini (tgl => alasan) — klinik
            //      tempat dia BERTUGAS hari itu tutup. Beda dari lb: lb pola
            //      mingguan, lt tanggal tertentu (Maulid, Lebaran, tutup cabang).
            $rows[] = ['id' => (string) $r['id'], 'n' => $r['nama'], 'r' => $r['role'],
                       'lb' => $r['libur'] ?? [], 'lt' => (object) ($r['libur_tgl'] ?? []), 'cells' => $cells];
        }
        $payloadGroups[] = ['key' => $gkey, 'label' => $g['label'], 'flex' => (bool) $g['flex'], 'rows' => $rows];
    }
    $dpMonths = [];
    foreach (range(1, 12) as $mi) {
        $mm = $anchor->copy()->month($mi);
        $dpMonths[] = ['m' => $mm->format('Y-m'), 'label' => $mm->translatedFormat('M')];
    }
@endphp
<script>
var KH_DATA = {
    month: @json($anchor->format('Y-m')),
    year: {{ $anchor->year }},
    month0: {{ $anchor->month - 1 }},
    monthLabel: @json($anchor->translatedFormat('F Y')),
    monthShort: @json($anchor->translatedFormat('M')),
    daysInMonth: {{ $daysInMonth }},
    todayYmd: @json($today->toDateString()),
    todayD: {{ $today->format('Y-m') === $anchor->format('Y-m') ? (int) $today->day : 'null' }},
    curM: @json($today->format('Y-m')),
    prevM: @json($anchor->copy()->subMonth()->format('Y-m')),
    nextM: @json($anchor->copy()->addMonth()->format('Y-m')),
    dpMonths: @json($dpMonths),
    groups: @json($payloadGroups),
    fotoBase: @json(url('pengawasan/foto')),
    urlBase: @json(url('absensi/kehadiran')),
    initBranch: @json((string) ($branchFilter ?? '')),
    initView: @json($initView),
    tutup: @json(array_values($tutupDow ?? [])),
    initDay: {{ $selDay ?: 'null' }}
};
</script>
<script>
(function () {
    'use strict';
    var D = KH_DATA, ST = KAH.ST, ORDER = KAH.ORDER, esc = KAH.esc;
    var DOW2 = ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'];
    var DOWF = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    var N = D.daysInMonth;

    var S = {
        view: D.initView || 'bulan',
        selDay: D.initDay || D.todayD || 1,
        branch: D.initBranch || '',
        q: '',
        focus: '',
        pop: null,      // {uid, d, x, yT, yB}
        person: null,   // uid
        narrow: window.matchMedia('(max-width:900px)').matches
    };
    if (S.selDay > N) S.selDay = N;

    // ── index bantu ──
    var rowByUid = {}, groupByUid = {};
    D.groups.forEach(function (g) {
        g.rows.forEach(function (r) { rowByUid[r.id] = r; groupByUid[r.id] = g; });
    });
    function shortLabel(g) { return g.label.replace(/^Cabang\s+/i, ''); }
    function dow(d) { return new Date(D.year, D.month0, d).getDay(); }
    // Hari tutup klinik — dari aturan kerja, BUKAN asumsi "akhir pekan".
    var TUTUP = D.tutup || [];
    function isTutup(wd) { return TUTUP.indexOf(wd) >= 0; }
    // Hari libur milik satu orang (aturannya sendiri bisa beda dari klinik).
    function rowLibur(r, wd) { return !!(r && r.lb && r.lb.indexOf(wd) >= 0); }
    // Libur KALENDER pada tanggal tertentu — alasannya (string) atau null.
    // Per-orang: yang sedang rolling ikut cabang tempatnya bertugas, jadi dua
    // orang di grup cabang yang sama bisa beda.
    function rowLiburTgl(r, d) { return (r && r.lt && r.lt[d]) ? r.lt[d] : null; }
    function lemburJam(min) { return Math.round(min / 60 * 10) / 10; }
    function visRows() {
        var q = S.q.trim().toLowerCase(), out = [];
        D.groups.forEach(function (g) {
            if (S.branch && String(g.key) !== String(S.branch)) return;
            var rows = g.rows.filter(function (r) {
                if (!q) return true;
                return (r.n || '').toLowerCase().indexOf(q) >= 0 || (r.r || '').toLowerCase().indexOf(q) >= 0;
            });
            if (rows.length) out.push({ g: g, rows: rows });
        });
        return out;
    }
    function flatUids(vis) {
        var f = [];
        vis.forEach(function (o) { o.rows.forEach(function (r) { f.push(r.id); }); });
        return f;
    }
    function dayNums() {
        var a = [], anchor = Math.min(S.selDay, N);
        if (effView() === 'bulan') { for (var d = 1; d <= N; d++) a.push(d); }
        else if (effView() === 'pekan') {
            var off = (dow(anchor) + 6) % 7;
            for (var d2 = anchor - off; d2 < anchor - off + 7; d2++) if (d2 >= 1 && d2 <= N) a.push(d2);
        } else a.push(anchor);
        return a;
    }
    function effView() { return S.narrow ? 'hari' : S.view; }
    function jamTxt(c) { return c.i ? (c.i + (c.o ? '–' + c.o : '')) : ''; }
    function cellOf(uid, d) { return (rowByUid[uid] && rowByUid[uid].cells[d]) || null; }

    // ── URL state (biar refresh/pindah bulan bawa konteks) ──
    function stateUrl(m, extra) {
        var p = new URLSearchParams();
        p.set('m', m || D.month);
        if (S.view !== 'bulan') { p.set('view', S.view); p.set('d', String((extra && extra.d) || S.selDay)); }
        if (S.branch) p.set('branch', S.branch);
        return D.urlBase + '?' + p.toString();
    }
    function syncUrl() { history.replaceState(null, '', stateUrl(D.month)); }

    // ── render: view switcher ──
    function renderViews() {
        document.querySelectorAll('#khViews .kh2-view-btn').forEach(function (b) {
            b.classList.toggle('on', b.dataset.view === S.view);
        });
    }

    // ── render: label rentang + judul panah ──
    function renderRange() {
        var v = effView(), a = Math.min(S.selDay, N), lbl;
        if (v === 'bulan') lbl = D.monthLabel;
        else if (v === 'pekan') {
            var ds = dayNums(); lbl = ds[0] + '–' + ds[ds.length - 1] + ' ' + D.monthShort + ' ' + D.year;
        } else lbl = DOWF[dow(a)] + ', ' + a + ' ' + D.monthShort;
        document.getElementById('khRangeLabel').textContent = lbl;
        var unit = v === 'bulan' ? 'Bulan' : (v === 'pekan' ? 'Pekan' : 'Hari');
        document.getElementById('khPrev').title = unit + ' sebelumnya';
        document.getElementById('khNext').title = unit + ' berikutnya';
    }

    // ── render: kartu ringkas ──
    function renderChips(vis, ds) {
        var cnt = { hadir: 0, telat: 0, alpa: 0 };
        vis.forEach(function (o) {
            o.rows.forEach(function (r) {
                ds.forEach(function (d) {
                    var c = r.cells[d];
                    if (c && cnt[c.s] != null) cnt[c.s]++;
                });
            });
        });
        var h = '';
        ['hadir', 'telat', 'alpa'].forEach(function (k) {
            var a = ST[k], on = S.focus === k;
            h += '<button type="button" class="kh2-chip kh2-btn" data-focus="' + k + '" style="'
                + (on ? 'background:' + a.bg + ';border-color:' + a.c + ';box-shadow:0 4px 14px -6px ' + a.c : '') + '">'
                + '<span class="kh2-chip-ic" style="background:' + a.bg + ';border-color:' + a.br + ';color:' + a.c + '">' + KAH.svg(a.ico, 17) + '</span>'
                + '<span class="kh2-chip-col"><span class="kh2-chip-n num" style="color:' + a.c + '">' + cnt[k] + '</span>'
                + '<span class="kh2-chip-l">' + a.l.toUpperCase() + '</span></span></button>';
        });
        var nvis = 0; vis.forEach(function (o) { nvis += o.rows.length; });
        h += '<div class="kh2-count"><span><span class="num" style="font-weight:800;color:#3B1500">' + nvis + '</span> karyawan ditampilkan</span></div>';
        document.getElementById('khChips').innerHTML = h;
        document.querySelectorAll('#khChips [data-focus]').forEach(function (b) {
            b.addEventListener('click', function () {
                S.focus = (S.focus === b.dataset.focus) ? '' : b.dataset.focus;
                render();
            });
        });
    }

    // ── render: filter chips ──
    function renderFchips() {
        var chips = [];
        if (S.branch) {
            var bl = '';
            D.groups.forEach(function (g) { if (String(g.key) === String(S.branch)) bl = g.label; });
            if (!bl) { var sel = document.getElementById('khBranch'); bl = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : S.branch; }
            chips.push({ label: 'Cabang: ' + bl, act: function () { S.branch = ''; document.getElementById('khBranch').value = ''; render(); } });
        }
        if (S.q.trim()) chips.push({ label: 'Cari: ' + S.q.trim(), act: function () { S.q = ''; document.getElementById('khQ').value = ''; render(); } });
        if (S.focus) chips.push({ label: 'Sorot: ' + ST[S.focus].l, st: ST[S.focus], act: function () { S.focus = ''; render(); } });
        var el = document.getElementById('khFchips');
        el.classList.toggle('on', chips.length > 0);
        if (!chips.length) { el.innerHTML = ''; return; }
        var h = '<span class="kh2-fchips-k">FILTER AKTIF</span>';
        chips.forEach(function (c, i) {
            var style = c.st ? 'background:' + c.st.bg + ';border-color:' + c.st.br + ';color:' + c.st.c + ';font-weight:800;' : '';
            h += '<button type="button" class="kh2-fchip kh2-btn" data-fi="' + i + '" title="Hapus filter ini" style="' + style + '">' + esc(c.label)
                + '<svg width="12" height="12" viewBox="0 0 14 14" fill="none" style="flex:none"><path d="M3.5 3.5l7 7M10.5 3.5l-7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>';
        });
        if (chips.length > 1) h += '<button type="button" class="kh2-fclear" id="khFclear">Hapus semua</button>';
        el.innerHTML = h;
        el.querySelectorAll('[data-fi]').forEach(function (b) {
            b.addEventListener('click', function () { chips[Number(b.dataset.fi)].act(); });
        });
        var fc = document.getElementById('khFclear');
        if (fc) fc.addEventListener('click', function () {
            S.branch = ''; S.q = ''; S.focus = '';
            document.getElementById('khBranch').value = ''; document.getElementById('khQ').value = '';
            render();
        });
    }

    // ── render: matriks ──
    function colBg(d) {
        var wd = dow(d);
        if (d === D.todayD) return '#FBF0DE';
        if (isTutup(wd)) return '#FDF5F3';
        return 'transparent';
    }
    function renderMatrix(vis, ds) {
        var pekan = effView() === 'pekan';
        var colW = pekan ? 132 : 38, rowH = pekan ? 52 : 38, chipH = pekan ? 42 : 26, nameW = 238;
        var gridCols = nameW + 'px repeat(' + ds.length + ',' + colW + 'px) 84px';
        var h = '<div class="kh2-mx-inner">';

        // header tanggal
        h += '<div class="kh2-mx-head" style="grid-template-columns:' + gridCols + '">';
        h += '<div class="kh2-mx-head-name">KARYAWAN</div>';
        ds.forEach(function (d) {
            var wd = dow(d), isToday = d === D.todayD, red = isTutup(wd);
            h += '<div class="kh2-hd" style="padding:' + (pekan ? '10px 0' : '8px 0') + ';background:' + colBg(d) + ';' + (isToday ? 'border-top:2px solid #B5701A;' : '') + '">'
                + '<div style="font-size:' + (pekan ? 11 : 9.5) + 'px;font-weight:800;letter-spacing:.3px;color:' + (isToday ? '#B5701A' : (red ? '#C0392B' : '#A9866A')) + '">' + (pekan ? DOWF[wd] : DOW2[wd]) + '</div>'
                + (isToday
                    ? '<div class="num" style="width:' + (pekan ? 26 : 22) + 'px;height:' + (pekan ? 26 : 22) + 'px;border-radius:50%;background:linear-gradient(135deg,#6B2400,#461500);color:#FBEADB;display:flex;align-items:center;justify-content:center;font-size:' + (pekan ? 14 : 12) + 'px;font-weight:800">' + d + '</div>'
                    : '<div class="num" style="font-size:' + (pekan ? 15 : 12.5) + 'px;font-weight:700;color:' + (red ? '#C0392B' : '#5A3A22') + '">' + d + '</div>')
                + '</div>';
        });
        h += '<div class="kh2-mx-head-rekap">REKAP</div></div>';

        // grup + baris
        vis.forEach(function (o) {
            var g = o.g;
            h += '<div class="kh2-sep"><div class="kh2-sep-in">'
                + '<span class="kh2-sep-dot" style="background:' + KAH.avColor(g.label) + '"></span>'
                + '<span class="kh2-sep-label">' + esc(g.label) + '</span>'
                + '<span class="kh2-sep-count num">' + o.rows.length + ' orang</span>'
                + (g.flex ? '<span class="kh2-sep-note">' + KAH.svg('info', 12) + 'jam bebas — scan tak dinilai</span>' : '')
                + '</div></div>';
            o.rows.forEach(function (r) {
                var hh = 0, tt = 0, aa = 0;
                var cellsH = '';
                ds.forEach(function (d) {
                    var c = r.cells[d];
                    if (c) { if (c.s === 'hadir') hh++; else if (c.s === 'telat') tt++; else if (c.s === 'alpa') aa++; }
                    // Hari libur orang ini ikut disorot walau klinik buka — orang
                    // kantor libur Minggu+Senin, rekannya cuma Senin.
                    var offDiri = rowLibur(r, dow(d));
                    // Libur kalender (Maulid, Lebaran, tutup cabang) disorot
                    // sama seperti libur mingguan — tapi alasannya disebutkan,
                    // sebab tanggal begini tak bisa ditebak dari pola minggu.
                    var offTgl = rowLiburTgl(r, d);
                    var cellBg = colBg(d);
                    if (cellBg === 'transparent' && (offDiri || offTgl)) cellBg = '#FDF5F3';
                    var wrap = '<div class="kh2-cellwrap" style="height:' + rowH + 'px;padding:0 ' + (pekan ? 4 : 2) + 'px;background:' + cellBg + '">';
                    if (!c) {
                        // sel kosong di hari liburnya sendiri BUKAN data hilang
                        var kosongTitle = offTgl ? ('klinik tutup — ' + offTgl)
                            : (offDiri ? 'libur menurut aturan kerjanya' : 'belum ada data');
                        wrap += '<div class="kh2-cell empty" style="height:' + chipH + 'px;border-radius:' + (pekan ? 10 : 6) + 'px" title="' + esc(kosongTitle) + '"></div>';
                    } else {
                        var at = ST[c.s] || ST.libur, dim = S.focus && c.s !== S.focus;
                        var title = d + ' ' + D.monthShort + ' · ' + at.l;
                        var jt = jamTxt(c);
                        if (jt) title += ' · ' + jt;
                        if (c.t) title += ' · telat ' + c.t + ' menit';
                        if (c.l) title += ' · lembur ' + KAH.jamNum(lemburJam(c.l)) + ' jam';
                        if (c.g) title += ' · scan tunggal';
                        if (c.x && c.x.length) title += ' · scan di ' + c.x.join(', ');
                        if (offTgl) title += ' · klinik tutup — ' + offTgl;
                        wrap += '<div class="kh2-cell' + (dim ? ' dim' : '') + '" data-uid="' + r.id + '" data-d="' + d + '" title="' + esc(title) + '" '
                            + 'style="height:' + chipH + 'px;border-radius:' + (pekan ? 10 : 6) + 'px;background:' + at.bg + ';border-color:' + at.br + ';color:' + at.c + ';'
                            + (pekan ? 'gap:7px;padding:0 10px;justify-content:flex-start;' : 'justify-content:center;') + '">';
                        if (pekan) {
                            wrap += KAH.svg(at.ico, 14) + '<span style="display:flex;flex-direction:column;align-items:flex-start;min-width:0;line-height:1.25">'
                                + '<span style="font-size:11.5px;font-weight:800;white-space:nowrap">' + at.l + '</span>'
                                + (jt ? '<span class="num" style="font-size:10px;font-weight:600;opacity:.85;white-space:nowrap">' + jt + '</span>' : '')
                                + '</span>';
                        } else {
                            wrap += '<span style="font-size:10.5px;font-weight:800">' + at.code + '</span>';
                        }
                        wrap += '</div>';
                        var dotOff = pekan ? 9 : 7, dotR = pekan ? 9 : 4;
                        if (c.l && !dim) wrap += '<span class="kh2-dot" style="top:' + dotOff + 'px;right:' + dotR + 'px;background:#B5701A"></span>';
                        if (c.f && c.f.length && !dim) wrap += '<span class="kh2-dot" style="bottom:' + dotOff + 'px;right:' + dotR + 'px;background:#3F6BAF"></span>';
                        if (c.x && c.x.length && !dim) wrap += '<span class="kh2-dot" style="top:' + dotOff + 'px;left:' + dotR + 'px;background:#8A5A16"></span>';
                    }
                    wrap += '</div>';
                    cellsH += wrap;
                });
                h += '<div class="kh2-row" style="grid-template-columns:' + gridCols + '">'
                    + '<div class="kh2-name-cell" data-person="' + r.id + '" title="Lihat sebulan penuh" style="height:' + rowH + 'px">'
                    + '<div class="kh2-av" style="width:31px;height:31px;font-size:12px;background:' + KAH.avColor(r.n) + '">' + esc(KAH.ini(r.n)) + '</div>'
                    + '<div class="kh2-name-tx"><div class="kh2-nm">' + esc(r.n) + '</div>'
                    + '<div class="kh2-nsub">' + esc((r.r ? r.r + ' · ' : '') + shortLabel(g)) + '</div></div>'
                    // chevron: nama membuka drawer sebulan (pola-interaksi P1) —
                    // dulu satu-satunya petunjuknya cuma teks kecil di legenda
                    + '<span class="kh2-name-chev" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>'
                    + '</div>'
                    + cellsH
                    + '<div class="kh2-tot num" style="height:' + rowH + 'px">'
                    + '<span style="font-size:12px;font-weight:800;color:#2F8E55">' + hh + '</span><span style="color:#E2D2C0">·</span>'
                    + '<span style="font-size:12px;font-weight:800;color:#B5701A">' + tt + '</span><span style="color:#E2D2C0">·</span>'
                    + '<span style="font-size:12px;font-weight:800;color:#C0392B">' + aa + '</span></div></div>';
            });
        });

        if (!vis.length) {
            h += '<div class="kh2-empty-state"><div style="font-size:14px;font-weight:700;color:#5A3A22">Tidak ada karyawan yang cocok</div>'
                + '<div style="font-size:12.5px;color:#A9866A;margin-top:4px">Coba ubah kata pencarian atau pilih cabang lain.</div></div>';
        }
        h += '</div>';
        document.getElementById('khMx').innerHTML = h;

        // legenda
        var lg = '';
        ORDER.forEach(function (k) {
            var a = ST[k];
            lg += '<span class="kh2-leg"><span class="kh2-leg-box" style="background:' + a.bg + ';border-color:' + a.br + ';color:' + a.c + '">' + a.code + '</span>' + a.l + '</span>';
        });
        lg += '<span class="kh2-leg"><span style="width:7px;height:7px;border-radius:50%;background:#3F6BAF"></span>ada foto selfie</span>'
            + '<span class="kh2-leg"><span style="width:7px;height:7px;border-radius:50%;background:#B5701A"></span>ada lembur</span>'
            + '<span class="kh2-leg"><span style="width:7px;height:7px;border-radius:50%;background:#8A5A16"></span>scan di cabang lain</span>'
            + '<span class="kh2-leg"><span style="width:13px;height:13px;border-radius:4px;border:1px dashed #E0C9B2"></span>belum ada data</span>'
            + '<span class="kh2-leg-hint">Klik sel untuk detail jam · klik nama untuk sebulan penuh</span>';
        document.getElementById('khLegend').innerHTML = lg;

        // events
        document.querySelectorAll('#khMx .kh2-cell[data-uid]').forEach(function (el) {
            el.addEventListener('click', function (ev) {
                var r = ev.currentTarget.getBoundingClientRect();
                openDetail(el.dataset.uid, Number(el.dataset.d), r.left + r.width / 2, r.top, r.bottom);
            });
        });
        document.querySelectorAll('#khMx [data-person]').forEach(function (el) {
            el.addEventListener('click', function () { openPerson(el.dataset.person); });
        });
    }

    // ── render: view Hari ──
    function renderDay(vis) {
        var sd = Math.min(S.selDay, N), wd = dow(sd);
        var h = '<div class="kh2-strip" id="khStrip">';
        for (var d = 1; d <= N; d++) {
            var sel = d === sd, isToday = d === D.todayD, red = isTutup(dow(d));
            h += '<button type="button" class="kh2-pill kh2-btn" data-sel="' + d + '" style="'
                + (sel ? 'background:linear-gradient(135deg,#6B2400,#461500);border-color:transparent;' : (isToday ? 'background:#FBF0DE;border-color:#E3B77A;' : '')) + '">'
                + '<span style="font-size:9.5px;font-weight:800;letter-spacing:.4px;color:' + (sel ? '#E0BFA6' : (red ? '#C0392B' : '#A9866A')) + '">' + DOW2[dow(d)] + '</span>'
                + '<span class="num" style="font-size:15px;font-weight:800;color:' + (sel ? '#FBEADB' : (red ? '#C0392B' : '#3B1500')) + '">' + d + '</span></button>';
        }
        h += '</div>';

        var cnt = { hadir: 0, telat: 0, alpa: 0 }, tot = 0, isi = 0;
        vis.forEach(function (o) {
            o.rows.forEach(function (r) {
                tot++;
                var c = r.cells[sd];
                if (c) { isi++; if (cnt[c.s] != null) cnt[c.s]++; }
            });
        });
        h += '<div class="kh2-day-title"><div style="font-size:15px;font-weight:800;color:#3B1500">' + DOWF[wd] + ', ' + sd + ' ' + D.monthLabel + '</div>'
            + '<div style="font-size:12px;color:#A9866A">' + (isi === 0 ? 'belum ada data' : (cnt.hadir + ' hadir · ' + cnt.telat + ' telat · ' + cnt.alpa + ' alpa')) + '</div></div>';

        vis.forEach(function (o) {
            var g = o.g;
            h += '<div class="kh2-day-card"><div class="kh2-day-head">'
                + '<span style="width:7px;height:7px;flex:none;border-radius:50%;background:' + KAH.avColor(g.label) + '"></span>'
                + '<span style="font-size:12.5px;font-weight:800;color:#5A3A22">' + esc(g.label) + '</span>'
                + '<span class="num" style="font-size:11.5px;color:#A9866A;font-weight:600">' + o.rows.length + ' orang</span>'
                + (g.flex ? '<span style="font-size:10.5px;font-weight:700;color:#8A6A52;background:#F1E9DF;border-radius:7px;padding:3px 8px">jam bebas — scan tak dinilai</span>' : '')
                + '</div>';
            o.rows.forEach(function (r) {
                var c = r.cells[sd];
                var pill = c
                    ? '<span class="kh2-spill" style="background:' + ST[c.s].bg + ';color:' + ST[c.s].c + ';border-color:' + ST[c.s].br + '">' + KAH.svg(ST[c.s].ico, 13) + ST[c.s].l + '</span>'
                    : '<span class="kh2-spill" style="background:#FBF5ED;color:#B79A82;border:1px dashed #E7D3BE">belum ada data</span>';
                h += '<div class="kh2-lrow" ' + (c ? 'data-uid="' + r.id + '" data-d="' + sd + '"' : 'style="cursor:default"') + '>'
                    + '<div class="kh2-av" style="width:38px;height:38px;font-size:15px;background:' + KAH.avColor(r.n) + '">' + esc(KAH.ini(r.n)) + '</div>'
                    + '<div style="flex:1;min-width:0"><div style="font-size:13.5px;font-weight:700;color:#3B1500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + esc(r.n) + '</div>'
                    + '<div style="font-size:11.5px;color:#A9866A">' + esc((r.r ? r.r + ' · ' : '') + shortLabel(g)) + '</div></div>'
                    + '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex:none">' + pill
                    + (c && jamTxt(c) ? '<span class="mono" style="font-size:11px;color:#8A6A52">' + jamTxt(c) + '</span>' : '') + '</div>'
                    + (c && c.f && c.f.length ? '<span style="width:7px;height:7px;flex:none;border-radius:50%;background:#3F6BAF"></span>' : '')
                    + '</div>';
            });
            h += '</div>';
        });
        if (!vis.length) {
            h += '<div class="kh2-day-card"><div class="kh2-empty-state"><div style="font-size:14px;font-weight:700;color:#5A3A22">Tidak ada karyawan yang cocok</div>'
                + '<div style="font-size:12.5px;color:#A9866A;margin-top:4px">Coba ubah kata pencarian atau pilih cabang lain.</div></div></div>';
        }

        var el = document.getElementById('khDay');
        el.innerHTML = h;
        el.querySelectorAll('[data-sel]').forEach(function (b) {
            b.addEventListener('click', function () { S.selDay = Number(b.dataset.sel); render(); });
        });
        el.querySelectorAll('.kh2-lrow[data-uid]').forEach(function (row) {
            row.addEventListener('click', function (ev) {
                var r = ev.currentTarget.getBoundingClientRect();
                openDetail(row.dataset.uid, Number(row.dataset.d), r.left + r.width / 2, r.top, r.bottom);
            });
        });
        var selPill = el.querySelector('[data-sel="' + sd + '"]');
        if (selPill && selPill.scrollIntoView) selPill.scrollIntoView({ block: 'nearest', inline: 'center' });
    }

    // ── kartu detail ──
    function openDetail(uid, d, x, yT, yB) {
        S.pop = { uid: uid, d: d, x: x, yT: yT, yB: yB };
        renderDetail();
    }
    function closeDetail() {
        S.pop = null;
        document.getElementById('khShell').classList.remove('on');
        document.getElementById('khScrim').classList.remove('on', 'dark');
    }
    window.khCloseDetail = closeDetail;
    window.khOpenPersonFromPop = function () {
        if (S.pop) { var uid = S.pop.uid; closeDetail(); openPerson(uid); }
    };
    function renderDetail() {
        var p = S.pop;
        var shell = document.getElementById('khShell'), scrim = document.getElementById('khScrim');
        if (!p) { closeDetail(); return; }
        var r = rowByUid[p.uid], g = groupByUid[p.uid], c = cellOf(p.uid, p.d);
        if (!r || !c) { closeDetail(); return; }
        var html = KAH.render({
            person: r.n,
            roleBranch: (r.r ? r.r + ' · ' : '') + shortLabel(g),
            dateLabel: DOWF[dow(p.d)] + ', ' + p.d + ' ' + D.monthLabel,
            status: c.s,
            jamIn: c.i, jamOut: c.o, single: c.g,
            lateMin: c.t, lemburJam: lemburJam(c.l),
            crossBranch: (c.x && c.x.length) ? (shortLabel(g) + ' → ' + c.x.join(', ')) : '',
            photos: (c.f || []).map(function (f) { return { url: D.fotoBase + '/' + f.id, jenis: f.j, jam: f.w }; }),
            flex: g.flex,
            variant: S.narrow ? 'sheet' : 'popover',
            hint: S.narrow ? '' : '← → hari · ↑ ↓ orang · Esc tutup',
            onClose: 'khCloseDetail()',
            onOpenMonth: 'khOpenPersonFromPop()'
        });
        shell.innerHTML = html;
        shell.className = 'kh2-shell on ' + (S.narrow ? 'sheet' : 'pop');
        scrim.className = 'kh2-scrim on' + (S.narrow ? ' dark' : '');
        if (!S.narrow) {
            shell.style.left = '0px'; shell.style.top = '0px'; shell.style.maxHeight = '';
            var W = 346, vw = window.innerWidth, vh = window.innerHeight;
            var H = Math.min(shell.offsetHeight, vh - 28);
            var left = Math.round((p.x || vw / 2) - W / 2);
            left = Math.max(14, Math.min(left, vw - W - 16));
            var below = (p.yB || 0) + 10, above = (p.yT || 0) - 10 - H;
            var top = (below + H < vh - 12) ? below : Math.max(14, above);
            shell.style.left = left + 'px';
            shell.style.top = top + 'px';
            shell.style.maxHeight = (vh - 28) + 'px';
        } else {
            shell.style.left = ''; shell.style.top = ''; shell.style.maxHeight = '';
        }
    }

    // keyboard: ← → hari (lompat ke hari yang ada datanya) · ↑ ↓ orang · Esc
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (S.person != null) { closePerson(); return; }
            if (S.pop) { closeDetail(); return; }
            var dp = document.getElementById('khDp');
            if (dp.classList.contains('on')) toggleDp(false);
            return;
        }
        if (!S.pop || S.person != null) return;
        var p = S.pop;
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            var step = e.key === 'ArrowRight' ? 1 : -1;
            for (var d = p.d + step; d >= 1 && d <= N; d += step) {
                if (cellOf(p.uid, d)) { S.pop = { uid: p.uid, d: d, x: p.x, yT: p.yT, yB: p.yB }; renderDetail(); break; }
            }
        } else if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            e.preventDefault();
            var flat = flatUids(visRows());
            var i = flat.indexOf(p.uid);
            if (i < 0) return;
            var step2 = e.key === 'ArrowDown' ? 1 : -1;
            for (var j = i + step2; j >= 0 && j < flat.length; j += step2) {
                if (cellOf(flat[j], p.d)) { S.pop = { uid: flat[j], d: p.d, x: p.x, yT: p.yT, yB: p.yB }; renderDetail(); break; }
            }
        }
    });

    // ── drawer sebulan per orang ──
    function openPerson(uid) {
        S.person = uid;
        renderPerson();
    }
    function closePerson() {
        S.person = null;
        document.getElementById('khDrawer').classList.remove('on');
        document.getElementById('khDscrim').classList.remove('on');
    }
    window.khClosePerson = closePerson;
    function renderPerson() {
        var uid = S.person, r = rowByUid[uid], g = groupByUid[uid];
        if (!r) { closePerson(); return; }
        var cnt = {}, totLate = 0, totLembur = 0;
        ORDER.forEach(function (k) { cnt[k] = 0; });
        for (var d = 1; d <= N; d++) {
            var c = r.cells[d];
            if (!c) continue;
            if (cnt[c.s] != null) cnt[c.s]++;
            totLate += c.t || 0; totLembur += c.l || 0;
        }
        var h = '<div class="kh2-dr-head">'
            + '<div class="kh2-av" style="width:40px;height:40px;font-size:16px;background:' + KAH.avColor(r.n) + ';box-shadow:0 0 0 2px rgba(255,255,255,.25)">' + esc(KAH.ini(r.n)) + '</div>'
            + '<div style="flex:1;min-width:0"><div style="font-size:16px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + esc(r.n) + '</div>'
            + '<div style="font-size:12px;color:#E0BFA6;margin-top:1px">' + esc((r.r ? r.r + ' · ' : '') + shortLabel(g) + ' · ' + D.monthLabel) + '</div></div>'
            + '<button type="button" class="kh2-dr-x kh2-btn" onclick="khClosePerson()" title="Tutup"><svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M4 4l7 7M11 4l-7 7" stroke="#FBEADB" stroke-width="1.7" stroke-linecap="round"/></svg></button>'
            + '</div><div class="kh2-dr-body">';

        h += '<div class="kh2-dr-sum">';
        ORDER.forEach(function (k) {
            var a = ST[k];
            h += '<div class="kh2-dr-sum-i" style="background:' + a.bg + ';color:' + a.c + ';border-color:' + a.br + '">'
                + '<span class="num" style="font-size:15px;font-weight:800">' + cnt[k] + '</span>'
                + '<span style="font-size:10.5px;font-weight:700;letter-spacing:.3px">' + a.l.toUpperCase() + '</span></div>';
        });
        h += '</div>';

        h += '<div class="kh2-dr-tots">'
            + '<div class="kh2-dr-tot"><div style="font-size:10px;font-weight:800;letter-spacing:.5px;color:#A9866A">TOTAL TELAT</div>'
            + '<div class="num" style="font-size:17px;font-weight:800;color:#B5701A;margin-top:2px">' + totLate + ' menit</div></div>'
            + '<div class="kh2-dr-tot"><div style="font-size:10px;font-weight:800;letter-spacing:.5px;color:#A9866A">LEMBUR DISETUJUI</div>'
            + '<div class="num" style="font-size:17px;font-weight:800;color:#B5701A;margin-top:2px">' + KAH.jamNum(lemburJam(totLembur)) + ' jam</div></div></div>';

        h += '<div class="kh2-dr-cal"><div class="kh2-dr-dow">';
        DOW2.forEach(function (d2) { h += '<div style="text-align:center;font-size:10px;font-weight:800;letter-spacing:.4px;color:#8A6A52;padding:9px 0">' + d2 + '</div>'; });
        h += '</div>';
        var first = dow(1), cur = 0;
        h += '<div class="kh2-dr-week">';
        for (var b = 0; b < first; b++) { h += '<div class="kh2-dr-cell blank"></div>'; cur++; }
        for (var dd = 1; dd <= N; dd++) {
            var cc = r.cells[dd], isToday = dd === D.todayD;
            h += '<div class="kh2-dr-cell' + (isToday ? ' today' : '') + '">'
                + '<div class="num" style="font-size:11px;font-weight:700;color:#8A6A52">' + dd + '</div>';
            if (cc) {
                var a2 = ST[cc.s];
                h += '<span class="kh2-spill" style="background:' + a2.bg + ';color:' + a2.c + ';border-color:' + a2.br + ';align-self:flex-start">' + a2.l + '</span>';
                if (jamTxt(cc)) h += '<div class="mono" style="font-size:9.5px;color:#A9866A">' + jamTxt(cc) + '</div>';
                var extra = cc.t ? ('telat ' + cc.t + 'm') : (cc.l ? ('+' + KAH.jamNum(lemburJam(cc.l)) + 'j') : '');
                if (extra) h += '<div style="font-size:9.5px;font-weight:700;color:#B5701A">' + extra + '</div>';
            }
            h += '</div>';
            cur++;
            if (cur % 7 === 0 && dd < N) h += '</div><div class="kh2-dr-week">';
        }
        while (cur % 7 !== 0) { h += '<div class="kh2-dr-cell blank"></div>'; cur++; }
        h += '</div></div></div>';

        var el = document.getElementById('khDrawer');
        el.innerHTML = h;
        el.classList.add('on');
        document.getElementById('khDscrim').classList.add('on');
    }
    document.getElementById('khDscrim').addEventListener('click', closePerson);
    document.getElementById('khScrim').addEventListener('click', closeDetail);

    // ── navigasi bulan / hari ──
    function goPrev() {
        var v = effView();
        if (v === 'bulan') { location.href = stateUrl(D.prevM); return; }
        var step = v === 'pekan' ? 7 : 1, d = Math.min(S.selDay, N) - step;
        if (d < 1) { location.href = stateUrl(D.prevM, { d: 28 }); return; }
        S.selDay = d; S.pop = null; render();
    }
    function goNext() {
        var v = effView();
        if (v === 'bulan') { location.href = stateUrl(D.nextM); return; }
        var step = v === 'pekan' ? 7 : 1, d = Math.min(S.selDay, N) + step;
        if (d > N) { location.href = stateUrl(D.nextM, { d: 1 }); return; }
        S.selDay = d; S.pop = null; render();
    }
    function goToday() {
        toggleDp(false);
        if (D.curM === D.month) { S.selDay = D.todayD || 1; S.pop = null; render(); return; }
        S.selDay = 1;
        var p = new URLSearchParams();
        p.set('m', D.curM);
        if (S.view !== 'bulan') { p.set('view', S.view); }
        if (S.branch) p.set('branch', S.branch);
        location.href = D.urlBase + '?' + p.toString();
    }
    function toggleDp(force) {
        var dp = document.getElementById('khDp'), sc = document.getElementById('khDpScrim');
        var on = typeof force === 'boolean' ? force : !dp.classList.contains('on');
        dp.classList.toggle('on', on);
        sc.classList.toggle('on', on);
    }
    document.getElementById('khPrev').addEventListener('click', goPrev);
    document.getElementById('khNext').addEventListener('click', goNext);
    document.getElementById('khToday').addEventListener('click', goToday);
    document.getElementById('khRangeBtn').addEventListener('click', function () { toggleDp(); });
    document.getElementById('khDpScrim').addEventListener('click', function () { toggleDp(false); });
    document.getElementById('khDpToday').addEventListener('click', goToday);
    (function () {
        var h = '';
        D.dpMonths.forEach(function (mo) {
            var cls = mo.m === D.month ? ' cur' : (mo.m === D.curM ? ' today' : '');
            h += '<button type="button" class="kh2-dp-mo kh2-btn' + cls + '" data-m="' + mo.m + '">' + mo.label + '</button>';
        });
        var grid = document.getElementById('khDpGrid');
        grid.innerHTML = h;
        grid.querySelectorAll('[data-m]').forEach(function (b) {
            b.addEventListener('click', function () {
                if (b.dataset.m === D.month) { toggleDp(false); return; }
                location.href = stateUrl(b.dataset.m, { d: 1 });
            });
        });
    })();

    // ── filter events ──
    document.getElementById('khQ').addEventListener('input', function (e) { S.q = e.target.value; S.pop = null; render(); });
    document.getElementById('khBranch').addEventListener('change', function (e) { S.branch = e.target.value; S.pop = null; render(); });
    document.querySelectorAll('#khViews .kh2-view-btn').forEach(function (b) {
        b.addEventListener('click', function () { S.view = b.dataset.view; S.pop = null; toggleDp(false); render(); });
    });

    // ── responsive ──
    var mq = window.matchMedia('(max-width:900px)');
    (mq.addEventListener ? mq.addEventListener.bind(mq) : mq.addListener.bind(mq))('change', function (e) {
        S.narrow = e.matches; S.pop = null; render();
    });

    // ── render root ──
    function render() {
        var vis = visRows(), ds = dayNums(), v = effView();
        renderViews();
        renderRange();
        renderChips(vis, ds);
        renderFchips();
        var mxWrap = document.getElementById('khMatrixWrap'), day = document.getElementById('khDay');
        if (v === 'hari') {
            mxWrap.style.display = 'none';
            day.classList.add('on');
            renderDay(vis);
        } else {
            mxWrap.style.display = '';
            day.classList.remove('on');
            renderMatrix(vis, ds);
        }
        if (S.pop) renderDetail();
        syncUrl();
    }
    render();
})();
</script>
@endsection
