@extends('layouts.app')

{{-- Pengawasan Absen — layar review SPV (handoff_absen_hp, direvisi
     handoff_kehadiran §6: kosakata tanpa "flag", strip dipecah baris
     MASUK/PULANG, blok KartuAbsenHarian di panel, keyboard 1/2/3·Enter·J/K).
     Prinsip: kejanggalan kelihatan TANPA dicari. 2 kolom: kiri (banner + filter
     + strip/antrian), kanan panel detail menetap. --}}

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .aws-wrap { display: flex; gap: 0; font-family: 'Plus Jakarta Sans', sans-serif; min-height: calc(100vh - 60px); }
    .aws-kiri { flex: 1; padding: 20px 24px; min-width: 0; }
    /* panel kanan FIXED ±480px (desain: ~25% layar), BUKAN persentase —
       45% bikin foto jadi setengah layar di monitor lebar */
    .aws-kanan { flex: 0 0 480px; width: 480px; background: #fff; border-left: 1px solid #EFE0CE; padding: 0; position: sticky; top: 0; align-self: flex-start; max-height: 100vh; overflow-y: auto; }
    @media (max-width: 1280px) { .aws-kanan { flex-basis: 400px; width: 400px; } }
    .aws-judul { color: #461500; font-weight: 800; font-size: 22px; margin: 0 0 2px; }
    .aws-sub { color: #8a7263; font-size: 13px; margin-bottom: 14px; }
    .aws-banner { display: flex; gap: 14px; align-items: center; background: #fdecec; border: 1px solid #f5c9c9; border-radius: 14px; padding: 14px 16px; margin-bottom: 16px; }
    .aws-banner.aman { background: #E3F4E8; border-color: #BFE3C2; }
    .aws-banner h6 { color: #C0392B; font-weight: 800; margin: 0 0 3px; }
    .aws-banner.aman h6 { color: #2F8E55; }
    .aws-banner p { color: #8a6060; font-size: 12.5px; margin: 0; }
    .aws-banner .angka { text-align: center; min-width: 46px; }
    .aws-banner .angka b { display: block; font-size: 20px; color: #C0392B; }
    .aws-banner .angka span { font-size: 10.5px; color: #8a6060; letter-spacing: .05em; }
    /* toolbar: filter (kiri) + view-toggle (kanan) — SATU baris, sesuai handoff */
    .aws-toolbar { display: flex; align-items: flex-end; gap: 11px; flex-wrap: wrap; margin-bottom: 14px; }
    .aws-filter { display: flex; gap: 11px; flex-wrap: wrap; margin: 0; }
    .aws-filter label { display: block; font-size: 10px; color: #A9866A; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; font-weight: 700; }
    .aws-filter input, .aws-filter select { border: 1px solid #E7D3BE; border-radius: 11px; padding: 9px 12px; background: #fff; color: #3B1500; font-weight: 600; font-size: 13px; }
    .aws-tabs { display: inline-flex; background: #fff; border: 1px solid #EADCC9; border-radius: 13px; padding: 5px; gap: 3px; margin-left: auto; align-self: flex-end; }
    .aws-tab { display: inline-flex; align-items: center; gap: 6px; border: 0; background: transparent; color: #8a7263; font-weight: 700; font-size: 13px; padding: 9px 15px; border-radius: 9px; cursor: pointer; }
    .aws-tab.aktif { background: #461500; color: #fff; }
    .aws-tab .bdg { background: #C0392B; color: #fff; border-radius: 99px; font-size: 11px; font-weight: 800; padding: 1px 7px; margin-left: 3px; }
    .aws-card { background: #fff; border: 1px solid #EFE0CE; border-radius: 14px; padding: 14px 16px; margin-bottom: 12px; }
    .aws-cabang-judul { display: flex; align-items: center; gap: 8px; font-weight: 800; color: #461500; margin-bottom: 10px; }
    .aws-strip { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 6px; }
    /* tile strip — ukuran ikut desain (±130px), bukan 86px */
    .aws-tile { flex: 0 0 auto; width: 130px; cursor: pointer; text-align: left; border: 0; background: none; padding: 0; position: relative; }
    .aws-tile-warn { position: absolute; top: 6px; right: 6px; z-index: 2; width: 24px; height: 24px; border-radius: 50%; background: #C0392B; color: #fff; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; }
    .aws-tile img { width: 130px; height: 150px; object-fit: cover; border-radius: 12px; border: 3px solid transparent; background: #2e1a10; }
    .aws-tile.flagged img { border-color: #C0392B; }
    .aws-tile .nm { font-size: 13px; color: #3B1500; font-weight: 700; margin-top: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .aws-tile .jm { font-size: 12px; color: #8a7263; }
    /* baris MASUK / PULANG di kartu cabang (handoff_kehadiran §6) */
    .aws-rowhead { display: flex; align-items: center; gap: 8px; margin: 10px 0 8px; flex-wrap: wrap; }
    .aws-rowhead .k { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; letter-spacing: .5px; color: #A9866A; }
    .aws-lengkap { display: inline-flex; align-items: center; border-radius: 8px; padding: 2px 8px; font-size: 11px; font-weight: 800; }
    .aws-lengkap.ok { background: #E3F4E8; color: #2F8E55; border: 1px solid #C9E6D3; }
    .aws-lengkap.kurang { background: #FBEFD9; color: #B5701A; border: 1px solid #F0DDBB; }
    .aws-belum { font-size: 12px; color: #B5701A; font-weight: 600; }
    .aws-toggle { display: inline-flex; align-items: center; gap: 7px; border: 1px solid #E7D3BE; background: #fff; color: #8A5A16; border-radius: 11px; padding: 8px 13px; font-size: 12.5px; font-weight: 700; cursor: pointer; margin-bottom: 12px; }
    .aws-toggle.on { background: #461500; border-color: #461500; color: #fff; }
    /* nomor tercetak di kartu penyelesaian (keyboard 1/2/3) */
    .aws-res .no { flex: 0 0 auto; width: 22px; height: 22px; border-radius: 7px; background: #F6ECDF; color: #9A7256; font-size: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin-top: 0; }
    .aws-p-hint { font-size: 11px; color: #B79A82; text-align: center; margin-top: 8px; }
    .aws-p-kah { border: 1px solid #EFE0CE; border-radius: 14px; overflow: hidden; margin-bottom: 4px; }
    .aws-chipst { display: inline-flex; gap: 6px; margin-bottom: 12px; }
    .aws-chip { border: 1px solid #e0d2c6; background: #fff; color: #8a7263; border-radius: 99px; padding: 5px 13px; font-size: 12.5px; font-weight: 700; text-decoration: none; }
    .aws-chip.aktif { background: #461500; border-color: #461500; color: #fff; }
    .aws-flag { display: flex; align-items: center; gap: 12px; width: 100%; text-align: left; background: #fff; border: 1px solid #EFE0CE; border-radius: 14px; padding: 12px 14px; margin-bottom: 10px; cursor: pointer; }
    .aws-flag:hover { border-color: #c9a888; }
    .aws-flag .ikn { width: 38px; height: 38px; border-radius: 10px; background: #fdf3e7; display: flex; align-items: center; justify-content: center; color: #B5701A; flex: 0 0 auto; }
    .aws-flag .isi { min-width: 0; flex: 1; }
    .aws-flag .isi b { color: #461500; font-size: 14px; }
    .aws-flag .isi small { color: #8a7263; display: block; }
    .aws-st { font-size: 11px; font-weight: 800; border-radius: 99px; padding: 3px 10px; flex: 0 0 auto; }
    .aws-st-baru { background: #fdecec; color: #C0392B; }
    .aws-st-dicek { background: #fff3d6; color: #B5701A; }
    .aws-st-selesai { background: #E3F4E8; color: #2F8E55; }
    .aws-grup { border: 1.5px solid #e8c9a0; background: #fdf7ef; border-radius: 14px; padding: 14px; margin-bottom: 10px; }
    .aws-grup .nm-chips { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0; }
    .aws-grup .nm-chips span { background: #f3e6d8; color: #6b4a2f; border-radius: 8px; padding: 3px 9px; font-size: 12px; font-weight: 600; }
    .aws-btn { background: #461500; color: #fff; border: 0; border-radius: 10px; padding: 9px 16px; font-weight: 700; font-size: 13.5px; }
    .aws-btn-garis { background: #fff; color: #461500; border: 1.5px solid #461500; border-radius: 10px; padding: 8px 14px; font-weight: 700; font-size: 13px; }
    /* panel kanan */
    .aws-p-kosong { text-align: center; color: #8a7263; padding: 80px 30px; }
    .aws-p-foto { position: relative; background: #241309; text-align: center; min-height: 120px; }
    /* cover selebar panel (desain penuh, tanpa bar hitam samping); tinggi dikunci */
    .aws-p-foto img { display: block; width: 100%; height: 380px; object-fit: cover; object-position: center 30%; }
    .aws-p-jenis { position: absolute; top: 12px; left: 12px; background: #fff; color: #C0392B; font-weight: 800; font-size: 12.5px; border-radius: 99px; padding: 5px 12px; }
    .aws-p-id { position: absolute; left: 0; right: 0; bottom: 0; text-align: left; padding: 26px 18px 12px; background: linear-gradient(transparent, rgba(20,10,4,.85)); }
    .aws-p-id .nm { color: #fff; font-weight: 800; font-size: 19px; }
    .aws-p-id .sb { color: #d8c4b2; font-size: 13px; }
    .aws-p-body { padding: 16px 18px; }
    /* sinyal = kartu putih ikon+label/nilai (handoff) */
    .aws-sinyal { display: flex; align-items: center; gap: 11px; background: #fff; border: 1px solid #EFE0CE; border-radius: 12px; padding: 11px 13px; margin-bottom: 8px; font-size: 12.5px; font-weight: 700; color: #3B1500; }
    .aws-sinyal .ic { flex: 0 0 auto; width: 34px; height: 34px; border-radius: 9px; background: #F6ECDF; color: #9A7256; display: flex; align-items: center; justify-content: center; }
    .aws-sinyal .tx { flex: 1; min-width: 0; }
    .aws-sinyal small { display: block; color: #9A7256; font-size: 11px; font-weight: 600; }
    .aws-sinyal.merah { border-color: #f0c4c4; background: #fdf6f6; }
    .aws-sinyal.merah .ic { background: #fdecea; color: #c0392b; }
    .aws-sinyal.merah .tx { color: #C0392B; }
    .aws-sinyal.warn { border-color: #f0dca8; background: #fffaf0; }
    .aws-sinyal.warn .ic { background: #fdf0d6; color: #B5701A; }
    .aws-sinyal.warn .tx { color: #8a6b1e; }
    /* riwayat = 2 kartu stat (handoff) */
    .aws-stats { display: flex; gap: 10px; margin-bottom: 4px; }
    .aws-stat { flex: 1; background: #fff; border: 1px solid #EFE0CE; border-radius: 12px; padding: 12px; text-align: center; }
    .aws-stat b { display: block; font-size: 22px; font-weight: 800; color: #3B1500; }
    .aws-stat span { font-size: 10.5px; color: #9A7256; font-weight: 600; }
    .aws-h6 { font-size: 11px; color: #7A5B43; text-transform: uppercase; letter-spacing: .05em; font-weight: 800; margin: 16px 0 9px; }
    /* penyelesaian = kartu radio deskriptif (handoff) */
    .aws-res { display: flex; align-items: flex-start; gap: 11px; border: 1.5px solid #E7D3BE; border-radius: 12px; padding: 12px 13px; margin-bottom: 8px; cursor: pointer; background: #fff; }
    .aws-res .rad { flex: 0 0 auto; width: 20px; height: 20px; border-radius: 50%; border: 2px solid #C9B39C; margin-top: 1px; }
    .aws-res .lbl { display: block; font-size: 13px; font-weight: 800; }
    .aws-res .desc { display: block; font-size: 11px; color: #9A7256; font-weight: 500; margin-top: 1px; }
    .aws-res.on-wajar { border-color: #2F8E55; } .aws-res.on-wajar .rad { border-color: #2F8E55; background: radial-gradient(#2F8E55 55%, transparent 60%); }
    .aws-res.on-perlu_koreksi { border-color: #C77A1E; } .aws-res.on-perlu_koreksi .rad { border-color: #C77A1E; background: radial-gradient(#C77A1E 55%, transparent 60%); }
    .aws-res.on-pelanggaran { border-color: #C0392B; } .aws-res.on-pelanggaran .rad { border-color: #C0392B; background: radial-gradient(#C0392B 55%, transparent 60%); }
    .aws-res-wajar .lbl { color: #2F8E55; } .aws-res-perlu_koreksi .lbl { color: #C77A1E; } .aws-res-pelanggaran .lbl { color: #C0392B; }
    .aws-catatan { width: 100%; border: 1px solid #E7D3BE; border-radius: 11px; padding: 10px 12px; font-size: 13px; font-family: inherit; }
    .aws-p-footer { position: sticky; bottom: 0; margin: 14px -18px -16px; padding: 14px 18px; border-top: 1px solid #EDDFCE; background: #FBF5EE; }
    .aws-hidden { display: none !important; }

    /* ── beres-beres borongan (lintas tanggal + centang) ── */
    .aws-sw { display: inline-flex; align-items: center; gap: 7px; background: #fff; border: 1px solid #E7D3BE; border-radius: 99px; padding: 5px 12px 5px 7px; font-size: 12px; font-weight: 700; color: #6b4a2f; cursor: pointer; }
    .aws-sw .knob { width: 30px; height: 17px; border-radius: 99px; background: #E2D2BF; position: relative; transition: background .15s; flex: none; }
    .aws-sw .knob::after { content: ''; position: absolute; top: 2px; left: 2px; width: 13px; height: 13px; border-radius: 50%; background: #fff; transition: transform .15s; }
    .aws-sw.on { border-color: #B5701A; color: #8a5310; }
    .aws-sw.on .knob { background: #B5701A; } .aws-sw.on .knob::after { transform: translateX(13px); }

    /* saran pintar — jaringan sudah terdaftar */
    .aws-saran { border: 1.5px solid #bcd9c6; background: #f2f9f4; border-radius: 14px; padding: 14px; margin-bottom: 12px; }
    .aws-saran b { color: #1E7A46; }
    .aws-saran .why { color: #4a7159; font-size: 12.5px; margin: 3px 0 9px; }

    /* baris flag dengan centang */
    .aws-pick { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #EFE0CE; border-radius: 14px; padding: 10px 13px; margin-bottom: 9px; }
    .aws-pick.checked { border-color: #B5701A; background: #fffaf2; }
    .aws-pick input[type=checkbox] { width: 17px; height: 17px; accent-color: #B5701A; cursor: pointer; flex: none; }
    .aws-pick .body { flex: 1; min-width: 0; background: none; border: none; padding: 0; text-align: left; cursor: pointer; font-family: inherit; }
    .aws-pick .body b { color: #461500; font-size: 13.5px; display: block; }
    .aws-pick .body small { color: #8a7263; }
    .aws-pick .tgl { font-size: 11px; font-weight: 800; color: #B5701A; background: #FBF0DE; border-radius: 7px; padding: 2px 8px; flex: none; }
    .aws-pick.satuan { border-style: dashed; opacity: .92; }
    .aws-pick .kunci { font-size: 10.5px; color: #9A7256; flex: none; }

    /* bar aksi melayang */
    .aws-bar { position: sticky; bottom: 10px; z-index: 20; background: #461500; color: #FBEADB; border-radius: 16px; padding: 12px 14px; box-shadow: 0 8px 22px rgba(70,21,0,.28); margin-top: 12px; }
    .aws-bar .top { display: flex; align-items: center; gap: 10px; margin-bottom: 9px; }
    .aws-bar .n { font-size: 14px; font-weight: 800; }
    .aws-bar .lnk { background: none; border: none; color: #E8C9AF; font-size: 12px; font-weight: 700; cursor: pointer; font-family: inherit; text-decoration: underline; padding: 0; }
    .aws-bar .row { display: flex; gap: 8px; flex-wrap: wrap; }
    .aws-bar select, .aws-bar input { border: 1px solid #7A4A2A; background: #FFF8F0; color: #3B1500; border-radius: 10px; padding: 9px 11px; font-size: 13px; font-family: inherit; }
    .aws-bar input { flex: 1; min-width: 170px; }
    .aws-bar button.go { background: #FBEADB; color: #461500; border: none; border-radius: 10px; padding: 9px 16px; font-size: 13px; font-weight: 800; font-family: inherit; cursor: pointer; }
    .aws-bar .hint { font-size: 11px; color: #C9A98D; margin-top: 7px; }
</style>
@endsection

@section('content')
@php
    $labelJenis = \App\Models\AttFlag::LABEL;
    $totalFlagTanggal = count($flagTunggal) + collect($grup)->flatten(1)->count();
@endphp
<div class="aws-wrap">
    {{-- ═════════ KIRI ═════════ --}}
    <div class="aws-kiri">
        <h4 class="aws-judul">Pengawasan Absen</h4>
        <div class="aws-sub">Review kejanggalan absen selfie · {{ $branches->count() }} cabang · {{ \Carbon\Carbon::parse($date)->translatedFormat('l, d M Y') }}</div>

        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <div class="aws-banner {{ $openTotal === 0 ? 'aman' : '' }}">
            <div style="flex:0 0 auto;width:46px;height:46px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;color:{{ $openTotal === 0 ? '#2F8E55' : '#C0392B' }}">
                @if($openTotal === 0)
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 7.7-1.5"/></svg>
                @else
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>
                @endif
            </div>
            <div style="flex:1">
                @if($openTotal > 0)
                    <h6>{{ $openTotal }} absen belum selesai dicek</h6>
                    <p>Periode gaji <strong>tidak bisa ditutup</strong> selama masih ada absen yang belum dicek.
                        Selesaikan semuanya untuk membuka penutupan periode.</p>
                @else
                    <h6>Semua absen sudah dicek</h6>
                    <p>Tidak ada kejanggalan menunggu — periode gaji bisa ditutup.</p>
                @endif
            </div>
            <div class="angka"><b>{{ $nBaru }}</b><span>BARU</span></div>
            <div class="angka"><b style="color:#B5701A">{{ $nDicek }}</b><span>DICEK</span></div>
        </div>

        <div class="aws-toolbar">
        <form method="GET" class="aws-filter">
            <div>
                <label>Tanggal</label>
                <input type="date" name="tanggal" value="{{ $date }}" onchange="this.form.submit()">
            </div>
            <div>
                <label>Cabang</label>
                <select name="cabang" onchange="this.form.submit()">
                    <option value="">Semua Cabang</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchFilter === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="aws-tabs">
            <button type="button" class="aws-tab aktif" data-tab="strip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="vertical-align:-.12em"><rect x="3.5" y="5" width="5" height="14" rx="1.2"/><rect x="10" y="5" width="5" height="14" rx="1.2"/><rect x="16.5" y="5" width="4" height="14" rx="1.2"/></svg>
                Foto Hari Ini
            </button>
            <button type="button" class="aws-tab" data-tab="flag">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="vertical-align:-.12em"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                Perlu Dicek
                @if($totalFlagTanggal > 0)<span class="bdg">{{ $totalFlagTanggal }}</span>@endif
            </button>
        </div>
        </div>

        {{-- ── TAB FOTO HARI INI ── --}}
        <div id="tab-strip">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <p style="color:#8a7263;font-size:13px;margin:0 0 12px;flex:1;min-width:200px">Foto berbingkai merah perlu dicek.</p>
                <button type="button" class="aws-toggle" id="awsToggleCek">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4L2.5 20h19L12 4z"/><path d="M12 10.5v4M12 17.2v.1"/></svg>
                    Hanya yang perlu dicek
                </button>
            </div>
            @forelse($stripPerCabang as $cabang => $items)
                @php
                    $masuk  = $items->where('jenis', 'masuk')->values();
                    $pulang = $items->where('jenis', 'pulang')->values();
                    $nFlag  = $items->filter(fn ($b) => isset($userFlagged[$b->user_id]))->count();
                    // siapa yang sudah masuk tapi belum absen pulang (per cabang ini)
                    $uidPulang = $pulang->pluck('user_id')->flip();
                    $belumPulang = $masuk->filter(fn ($b) => ! isset($uidPulang[$b->user_id]))
                        ->map(fn ($b) => optional($b->user)->nama)->filter()->unique()->values();
                    $nM = $masuk->pluck('user_id')->unique()->count();
                    $nP = $pulang->pluck('user_id')->unique()->count();
                @endphp
                <div class="aws-card" data-cabang-card>
                    <div class="aws-cabang-judul"><span style="width:9px;height:9px;border-radius:3px;background:#2F8E55;display:inline-block"></span>
                        Sweet Treats {{ $cabang }}
                        <span style="font-weight:600;color:#8a7263;font-size:12.5px">{{ $items->count() }} absen</span>
                        @if($nFlag > 0)<span style="color:#C0392B;font-size:12.5px;font-weight:700">• {{ $nFlag }} perlu dicek</span>@endif
                    </div>

                    {{-- baris MASUK --}}
                    <div class="aws-rowhead">
                        <span class="k"><svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M8.5 2.5h2A1.5 1.5 0 0 1 12 4v6a1.5 1.5 0 0 1-1.5 1.5h-2M6.5 4.5 9 7l-2.5 2.5M9 7H2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>MASUK</span>
                        <span class="aws-lengkap ok num">{{ $nM }}</span>
                    </div>
                    @if($masuk->isEmpty())
                        <div style="color:#8a7263;font-size:12.5px;margin-bottom:4px">Belum ada foto masuk.</div>
                    @else
                        <div class="aws-strip">
                            @foreach($masuk as $b)
                                <button type="button" class="aws-tile {{ isset($userFlagged[$b->user_id]) ? 'flagged' : '' }}"
                                        data-nav-bukti="{{ $b->id }}" onclick="awsBukaBukti({{ $b->id }})">
                                    @if(isset($userFlagged[$b->user_id]))
                                        <span class="aws-tile-warn"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4L2.5 20h19L12 4z"/><path d="M12 10.5v4M12 17.2v.1"/></svg></span>
                                    @endif
                                    <img src="{{ route('pengawasan.foto', $b->id) }}" alt="" loading="lazy">
                                    <div class="nm">{{ optional($b->user)->nama }}</div>
                                    <div class="jm num">{{ $b->taken_at->format('H.i') }}</div>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    {{-- baris PULANG --}}
                    <div class="aws-rowhead">
                        <span class="k"><svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M5.5 2.5h-2A1.5 1.5 0 0 0 2 4v6a1.5 1.5 0 0 0 1.5 1.5h2M9 4.5 11.5 7 9 9.5M11.5 7H5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>PULANG</span>
                        <span class="aws-lengkap {{ $belumPulang->isEmpty() ? 'ok' : 'kurang' }} num">{{ $nP }}/{{ $nM }}</span>
                        @if($belumPulang->isNotEmpty())
                            <span class="aws-belum">{{ $belumPulang->count() }} belum absen pulang · {{ $belumPulang->implode(', ') }}</span>
                        @endif
                    </div>
                    @if($pulang->isEmpty())
                        <div style="color:#8a7263;font-size:12.5px">Belum ada foto pulang.</div>
                    @else
                        <div class="aws-strip">
                            @foreach($pulang as $b)
                                <button type="button" class="aws-tile {{ isset($userFlagged[$b->user_id]) ? 'flagged' : '' }}"
                                        data-nav-bukti="{{ $b->id }}" onclick="awsBukaBukti({{ $b->id }})">
                                    @if(isset($userFlagged[$b->user_id]))
                                        <span class="aws-tile-warn"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4L2.5 20h19L12 4z"/><path d="M12 10.5v4M12 17.2v.1"/></svg></span>
                                    @endif
                                    <img src="{{ route('pengawasan.foto', $b->id) }}" alt="" loading="lazy">
                                    <div class="nm">{{ optional($b->user)->nama }}</div>
                                    <div class="jm num">{{ $b->taken_at->format('H.i') }}</div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="aws-card" style="color:#8a7263">Belum ada absen HP di tanggal ini.</div>
            @endforelse
        </div>

        {{-- ── TAB ANTRIAN ── --}}
        <div id="tab-flag" class="aws-hidden">
            <div class="aws-chipst" style="align-items:center;flex-wrap:wrap">
                @foreach(['' => 'Semua', 'baru' => 'Baru', 'dicek' => 'Dicek', 'selesai' => 'Selesai'] as $val => $lbl)
                    <a class="aws-chip {{ ($statusFilter ?? '') === $val ? 'aktif' : '' }}"
                       href="{{ request()->fullUrlWithQuery(['status' => $val ?: null]) }}">{{ $lbl }}</a>
                @endforeach

                {{-- Beres-beres: lepas antrian dari SATU tanggal. Rotasi WiFi /
                     pendaftaran telat menebar flag serumpun lintas hari. --}}
                <a class="aws-sw {{ $lintasHari ? 'on' : '' }}" style="margin-left:6px"
                   href="{{ request()->fullUrlWithQuery(['semua' => $lintasHari ? null : 1]) }}"
                   title="Tampilkan semua flag belum selesai dari semua tanggal">
                    <span class="knob"></span> Semua tanggal
                </a>
                <span style="color:#8a7263;font-size:12.5px;margin-left:6px"><b>{{ $totalFlagTanggal }}</b> absen ditampilkan</span>
                {{-- batas dikatakan terus terang: daftar terpotong tanpa diberi tahu
                     terbaca sebagai "cuma segini", padahal bukan. --}}
                @if($batasArsip)
                    <span style="color:#B5701A;font-size:12px;margin-left:2px">· hanya 90 hari terakhir</span>
                @endif
            </div>

            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            {{-- ── Saran pintar: sebabnya sudah beres ──
                 Flag ip_asing terbuka yang alamatnya SEKARANG cocok jaringan
                 terdaftar = sisa dari WiFi yang telat didaftarkan. Sistem cuma
                 menawarkan yang bisa ia jelaskan; vonis tetap di tangan SPV. --}}
            @if($bolehAksi && ($saranBeres['n'] ?? 0) > 0)
                <form method="POST" action="{{ route('pengawasan.grup') }}" class="aws-saran">
                    @csrf
                    <b>{{ $saranBeres['n'] }} flag lama sebabnya sudah beres</b>
                    <div class="why">
                        Alamat WiFi-nya sekarang sudah terdaftar
                        @if($saranBeres['jaringan'])sebagai <b>{{ implode(', ', $saranBeres['jaringan']) }}</b>@endif —
                        ke-flag dulu karena jaringannya belum didaftarkan waktu itu.
                    </div>
                    <input type="hidden" name="ids" value="{{ implode(',', $saranBeres['ids']) }}">
                    <input type="hidden" name="hasil" value="wajar">
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <input type="text" name="catatan" class="aws-catatan" style="flex:1;min-width:200px" required
                               value="WiFi cabang belum terdaftar saat itu; jaringannya kini sudah terdaftar.">
                        <button type="submit" class="aws-btn">Selesaikan {{ $saranBeres['n'] }} (wajar)</button>
                    </div>
                </form>
            @endif

            {{-- grup serentak --}}
            @foreach($grup as $key => $items)
                @php $cabangGrup = $branchName->get($key, 'Tak diketahui'); @endphp
                <div class="aws-grup">
                    <div style="display:flex;align-items:center;gap:8px">
                        <b style="color:#461500;flex:1">{{ count($items) }} absen di luar WiFi {{ $cabangGrup }}</b>
                        <span style="background:#f3e6d8;color:#B5701A;font-size:10.5px;font-weight:800;border-radius:99px;padding:3px 10px;letter-spacing:.04em">GRUP · {{ count($items) }} ORANG</span>
                    </div>
                    <div style="color:#8a7263;font-size:12.5px">Terjadi serentak — kemungkinan WiFi cabang gangguan, bukan pelanggaran.
                        Bisa diselesaikan sekaligus.</div>
                    <div class="nm-chips">
                        @foreach($items as $f)<span>{{ optional($f->user)->nama }}</span>@endforeach
                    </div>
                    <form method="POST" action="{{ route('pengawasan.grup') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap" @if(!$bolehAksi) hidden @endif>
                        @csrf
                        <input type="hidden" name="ids" value="{{ collect($items)->pluck('id')->implode(',') }}">
                        <input type="hidden" name="hasil" value="wajar">
                        <input type="text" name="catatan" class="aws-catatan" style="flex:1;min-width:180px" required
                               placeholder="Catatan — wajib (mis. WiFi cabang gangguan)">
                        <button type="submit" class="aws-btn">Selesaikan {{ count($items) }} sekaligus (wajar)</button>
                    </form>
                </div>
            @endforeach

            {{-- ── flag satuan + centang borongan ──
                 Centang hanya untuk jenis cerita-jaringan; lupa-pulang &
                 absen-dari-laptop ceritanya per-orang → tetap dibuka satuan
                 (pagarnya juga ditegakkan server, bukan cuma disembunyikan). --}}
            @php $jenisBorongan = \App\Http\Controllers\Absensi\PengawasanController::JENIS_BORONGAN; @endphp
            <form method="POST" action="{{ route('pengawasan.grup') }}" id="aws-borong">
                @csrf
                <input type="hidden" name="ids" id="aws-borong-ids">
                @forelse($flagTunggal as $f)
                    @php $bisaBorong = $bolehAksi && in_array($f->jenis, $jenisBorongan, true) && $f->status !== 'selesai'; @endphp
                    <div class="aws-pick {{ $bisaBorong ? '' : 'satuan' }}">
                        @if($bisaBorong)
                            <input type="checkbox" class="aws-cb" value="{{ $f->id }}" data-jenis="{{ $f->jenis }}"
                                   aria-label="Pilih flag {{ optional($f->user)->nama }}">
                        @endif
                        <button type="button" class="body" data-nav-flag="{{ $f->id }}" onclick="awsBukaFlag({{ $f->id }})">
                            <b>{{ optional($f->user)->nama }}</b>
                            <small>{{ $labelJenis[$f->jenis] ?? $f->jenis }}</small>
                        </button>
                        @if($lintasHari)
                            <span class="tgl">{{ \Carbon\Carbon::parse($f->work_date)->translatedFormat('d M') }}</span>
                        @endif
                        @if(! $bisaBorong && $bolehAksi && $f->status !== 'selesai')
                            <span class="kunci" title="Jenis ini ceritanya per-orang — buka satu per satu">satuan</span>
                        @endif
                        <span class="aws-st aws-st-{{ $f->status }}">{{ ucfirst($f->status) }}</span>
                    </div>
                @empty
                    @if(empty($grup) && ($saranBeres['n'] ?? 0) === 0)
                        <div class="aws-card" style="color:#8a7263">
                            @if($lintasHari)
                                Bersih — tidak ada flag terbuka di tanggal mana pun. 🎉
                            @else
                                Tidak ada yang perlu dicek di tanggal ini{{ $statusFilter ? ' dengan status itu' : '' }}.
                            @endif
                        </div>
                    @endif
                @endforelse

                {{-- bar aksi: muncul begitu ada yang dicentang --}}
                <div class="aws-bar aws-hidden" id="aws-bar">
                    <div class="top">
                        <span class="n"><span id="aws-bar-n">0</span> flag dipilih</span>
                        <button type="button" class="lnk" id="aws-pick-sejenis">Pilih semua sejenis</button>
                        <button type="button" class="lnk" id="aws-pick-clear">Batal</button>
                    </div>
                    <div class="row">
                        <select name="hasil">
                            <option value="wajar">Wajar</option>
                            <option value="perlu_koreksi">Perlu Koreksi</option>
                            <option value="pelanggaran">Pelanggaran</option>
                        </select>
                        <input type="text" name="catatan" required minlength="3"
                               placeholder="Catatan — wajib, berlaku untuk semua yang dipilih">
                        <button type="submit" class="go">Selesaikan</button>
                    </div>
                    <div class="hint">Catatan ini tersimpan di jejak audit sebagai alasan penutupan.</div>
                </div>
            </form>
        </div>
    </div>

    {{-- ═════════ KANAN: panel detail ═════════ --}}
    <div class="aws-kanan">
        <div id="aws-p-kosong" class="aws-p-kosong">
            <div style="width:88px;height:88px;border-radius:24px;background:#F6ECDF;color:#B99A7C;display:flex;align-items:center;justify-content:center;margin:0 auto 14px"><svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5A1.5 1.5 0 0 1 4.5 7H7l1.4-2.2a1 1 0 0 1 .8-.5h5.6a1 1 0 0 1 .8.5L17 7h2.5A1.5 1.5 0 0 1 21 8.5V18a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18z"/><circle cx="12" cy="13" r="3.3"/></svg></div>
            <b style="color:#461500">Pilih satu absen</b>
            <div style="font-size:13px">Klik foto atau baris di daftar untuk memeriksa detail dan memutuskannya.</div>
            <div style="font-size:12px;color:#B79A82;margin-top:8px">Tombol <b>J</b> / <b>K</b> untuk pindah antar absen</div>
        </div>

        <div id="aws-p-isi" class="aws-hidden">
            <div class="aws-p-foto">
                <span id="aws-p-jenis" class="aws-p-jenis aws-hidden"></span>
                <img id="aws-p-img" src="" alt="Selfie">
                <div class="aws-p-id">
                    <div id="aws-p-nama" class="nm"></div>
                    <div id="aws-p-sub" class="sb"></div>
                </div>
            </div>
            <div class="aws-p-body">
                <div id="aws-p-kah-wrap" class="aws-hidden">
                    <div class="aws-h6" style="margin-top:0">Catatan kehadiran hari itu</div>
                    <div id="aws-p-kah" class="aws-p-kah"></div>
                </div>

                <div class="aws-h6" id="aws-p-sinyal-h6">Sinyal sistem</div>
                <div id="aws-p-sinyal"></div>

                <div id="aws-p-riwayat-wrap">
                    <div class="aws-h6">Riwayat singkat</div>
                    <div class="aws-stats">
                        {{-- bulannya disebut: di mode lintas tanggal, "bulan ini" jadi ambigu --}}
                        <div class="aws-stat"><b id="aws-p-hf">0</b><span>pernah dicek {{ $bulanRiwayat }}</span></div>
                        <div class="aws-stat"><b id="aws-p-hp">0</b><span>lupa pulang {{ $bulanRiwayat }}</span></div>
                    </div>
                </div>

                {{-- daftarkan IP (muncul untuk ip_asing; reviewer saja) --}}
                <form id="aws-p-daftar" data-boleh-aksi="{{ $bolehAksi ? '1' : '' }}" method="POST" action="{{ route('pengawasan.daftarkan-ip') }}" class="aws-hidden" style="margin-top:12px">
                    @csrf
                    <div class="aws-h6">Jalur WiFi sah yang belum terdaftar?</div>
                    <input type="hidden" name="ip" id="aws-p-daftar-ip">
                    <div style="display:flex;gap:8px">
                        <select name="raw_branch_id" class="aws-catatan" style="flex:1" required>
                            <option value="">Daftarkan IP ini ke cabang…</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="aws-btn-garis">Daftarkan</button>
                    </div>
                    @if(in_array(session('group_name'), ['Head IT', 'IT', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin'], true))
                        <a href="{{ route('absensi.wifi') }}" style="font-size:12.5px;color:#8a7263;display:inline-block;margin-top:6px">Kelola semua IP di halaman WiFi Cabang →</a>
                    @endif
                </form>

                {{-- penyelesaian --}}
                <form id="aws-p-selesai" method="POST" action="" class="aws-hidden" style="margin-top:14px">
                    @csrf
                    <div class="aws-h6">Penyelesaian <span style="color:#C0392B">*</span></div>
                    <input type="hidden" name="hasil" id="aws-p-hasil-val" required>
                    <div class="aws-res aws-res-wajar" data-hasil="wajar">
                        <span class="rad"></span>
                        <span style="flex:1"><span class="lbl">Wajar</span><span class="desc">Tidak ada masalah, absen valid</span></span>
                        <span class="no num">1</span>
                    </div>
                    <div class="aws-res aws-res-perlu_koreksi" data-hasil="perlu_koreksi">
                        <span class="rad"></span>
                        <span style="flex:1"><span class="lbl">Perlu Koreksi</span><span class="desc">Jam / data perlu dibetulkan</span></span>
                        <span class="no num">2</span>
                    </div>
                    <div class="aws-res aws-res-pelanggaran" data-hasil="pelanggaran">
                        <span class="rad"></span>
                        <span style="flex:1"><span class="lbl">Pelanggaran</span><span class="desc">Melanggar aturan kehadiran</span></span>
                        <span class="no num">3</span>
                    </div>
                    <div class="aws-h6">Catatan singkat</div>
                    <textarea name="catatan" class="aws-catatan" rows="2" placeholder="Contoh: WiFi cabang sempat mati, sudah dikonfirmasi ke FO."></textarea>
                    <div class="aws-p-footer">
                        <button type="submit" id="aws-p-simpan" class="aws-btn" style="width:100%" disabled>Pilih penyelesaian dulu</button>
                        <div class="aws-p-hint"><b>1 2 3</b> pilih · <b>Enter</b> simpan · <b>J</b>/<b>K</b> pindah antar absen · <b>Esc</b> tutup</div>
                    </div>
                </form>
                <div id="aws-p-sudah" class="aws-hidden" style="margin-top:14px"></div>
            </div>
        </div>
    </div>
</div>
@include('absensi._kartu_absen_harian')
@endsection

@section('js')
<script>
    // Data buat panel — di-render server, dibaca JS. Volume kecil (per hari).
    var AWS_BUKTI = {!! $buktiById->values()->map(fn ($b) => [
        'id'     => $b->id,
        'nama'   => optional($b->user)->nama,
        'jenis'  => $b->jenis,
        'jam'    => $b->taken_at->format('H.i'),
        'foto'   => route('pengawasan.foto', $b->id),
        'ip'     => $b->ip,
        'cocok'  => (bool) $b->branch_id_cocok,
        'cabang' => $branchName->get($b->branch_id_cocok ?: $b->branch_id_manual, 'Tak diketahui'),
        'user_id'=> $b->user_id,
        'device' => $b->device_label ?: 'Tidak tercatat',
        'desktop'=> (bool) $b->is_desktop,
    ])->toJson() !!};

    var AWS_FLAGS = {!! collect($flagTunggal)->merge(collect($grup)->flatten(1))->map(fn ($f) => [
        'id'      => $f->id,
        'user_id' => $f->user_id,
        'nama'    => optional($f->user)->nama,
        'jenis'   => $f->jenis,
        'label'   => \App\Models\AttFlag::LABEL[$f->jenis] ?? $f->jenis,
        'status'  => $f->status,
        'hasil'   => $f->hasil,
        'catatan' => $f->catatan,
        'bukti_id'=> $f->ref_type === 'att_hp_bukti' ? $f->ref_id : null,
        // wd = tanggal kerja flag ini. Wajib: di mode lintas tanggal, panel
        // harus mengambil kehadiran HARI FLAG-nya, bukan hari yang terpilih.
        'wd'      => \Carbon\Carbon::parse($f->work_date)->toDateString(),
        'wd_label'=> \Carbon\Carbon::parse($f->work_date)->translatedFormat('l, d F Y'),
    ])->toJson() !!};

    var AWS_RIWAYAT = {!! $riwayat->toJson() !!};
    // att_day per orang hari itu — untuk blok "Catatan kehadiran hari itu" (KAH)
    var AWS_HARI = {!! json_encode($hariMap) !!};
    var AWS_SUB = {!! json_encode($subOrang) !!};
    var AWS_TGL_LABEL = @json($tanggalLabel);
    var AWS_TGL = @json($date);   // tanggal terpilih — kunci kehadiran utk foto di strip
    var AWS_URL_SELESAI = "{{ url('pengawasan') }}/";  // + id + /selesai
    var AWS_URL_DICEK   = "{{ url('pengawasan') }}/";  // + id + /dicek
    var AWS_CSRF = document.querySelector('meta[name="csrf-token"]').content;

    // tabs
    document.querySelectorAll('.aws-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('.aws-tab').forEach(function (x) { x.classList.remove('aktif'); });
            t.classList.add('aktif');
            document.getElementById('tab-strip').classList.toggle('aws-hidden', t.dataset.tab !== 'strip');
            document.getElementById('tab-flag').classList.toggle('aws-hidden', t.dataset.tab !== 'flag');
        });
    });

    // ── penyelesaian: kartu radio deskriptif ──
    function awsResetPenyelesaian() {
        document.querySelectorAll('.aws-res').forEach(function (c) {
            c.classList.remove('on-wajar', 'on-perlu_koreksi', 'on-pelanggaran');
        });
        document.getElementById('aws-p-hasil-val').value = '';
        var q = document.querySelector('#aws-p-selesai textarea'); if (q) q.value = '';
        var btn = document.getElementById('aws-p-simpan');
        btn.disabled = true; btn.textContent = 'Pilih penyelesaian dulu';
    }
    document.querySelectorAll('.aws-res').forEach(function (c) {
        c.addEventListener('click', function () {
            var h = c.dataset.hasil;
            document.querySelectorAll('.aws-res').forEach(function (x) {
                x.classList.remove('on-wajar', 'on-perlu_koreksi', 'on-pelanggaran');
            });
            c.classList.add('on-' + h);
            document.getElementById('aws-p-hasil-val').value = h;
            var btn = document.getElementById('aws-p-simpan');
            btn.disabled = false; btn.textContent = 'Simpan keputusan';
        });
    });

    function awsCari(arr, id) { return arr.filter(function (x) { return x.id === id; })[0] || null; }

    function awsIsiPanel(bukti, flag) {
        document.getElementById('aws-p-kosong').classList.add('aws-hidden');
        document.getElementById('aws-p-isi').classList.remove('aws-hidden');

        // "Catatan kehadiran hari itu" — kartu bersama KAH (chrome=false, tanpa foto):
        // status, jam masuk/pulang, telat, lembur, lintas cabang dalam bahasa yang
        // sama dengan layar Kehadiran dan Kehadiran Saya.
        var uidKah = flag ? flag.user_id : (bukti ? bukti.user_id : null);
        // Kunci kehadiran = orang + TANGGAL. Flag membawa tanggalnya sendiri;
        // foto di strip selalu milik tanggal yang sedang dipilih.
        var wdKah = flag && flag.wd ? flag.wd : AWS_TGL;
        var hari = uidKah != null ? AWS_HARI[uidKah + '|' + wdKah] : null;
        var kahWrap = document.getElementById('aws-p-kah-wrap');
        if (hari) {
            kahWrap.classList.remove('aws-hidden');
            document.getElementById('aws-p-kah').innerHTML = KAH.render({
                person: flag ? flag.nama : (bukti ? bukti.nama : '—'),
                roleBranch: AWS_SUB[uidKah] || '',
                dateLabel: (flag && flag.wd_label) ? flag.wd_label : AWS_TGL_LABEL,
                status: hari.st,
                jamIn: hari.in, jamOut: hari.out, single: hari.single,
                lateMin: hari.late, lemburJam: Math.round(hari.lembur / 60 * 10) / 10,
                crossBranch: (hari.lain && hari.lain.length) ? ((hari.home || '') + ' → ' + hari.lain.join(', ')) : '',
                flex: !!hari.flex,
                chrome: false,
                showPhotos: false
            });
        } else {
            kahWrap.classList.add('aws-hidden');
            document.getElementById('aws-p-kah').innerHTML = '';
        }

        var img = document.getElementById('aws-p-img');
        img.src = bukti ? bukti.foto : '';
        img.style.display = bukti ? '' : 'none';

        var jenisEl = document.getElementById('aws-p-jenis');
        jenisEl.classList.toggle('aws-hidden', !flag);
        if (flag) jenisEl.textContent = flag.label;

        var nama = flag ? flag.nama : (bukti ? bukti.nama : '');
        document.getElementById('aws-p-nama').textContent = nama || '—';
        var cabangLabel = bukti && bukti.cabang !== 'Tak diketahui' ? ('Sweet Treats ' + bukti.cabang) : (bukti ? bukti.cabang : '');
        document.getElementById('aws-p-sub').textContent = bukti
            ? (bukti.jam + ' · ' + cabangLabel + ' · absen ' + bukti.jenis) : '';

        // sinyal — kartu ikon+label/nilai (textContent, aman dari XSS)
        var sinyalEl = document.getElementById('aws-p-sinyal');
        sinyalEl.innerHTML = '';
        function kartu(kelas, ikon, label, nilai) {
            var d = document.createElement('div'); d.className = 'aws-sinyal ' + (kelas || '');
            d.innerHTML = '<span class="ic">' + ikon + '</span><span class="tx"><small></small><span class="v"></span></span>';
            d.querySelector('small').textContent = label;
            d.querySelector('.v').textContent = nilai;
            sinyalEl.appendChild(d);
        }
        var IC_JAM   = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="8.5"/><path d="M12 8v4.2l2.6 1.6" stroke-linecap="round"/></svg>';
        var IC_WIFI  = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4.5 9.5a12 12 0 0 1 15 0M7.5 12.8a7.5 7.5 0 0 1 9 0M10.3 16a3.5 3.5 0 0 1 3.4 0"/><circle cx="12" cy="19" r=".7" fill="currentColor"/></svg>';
        var IC_HP    = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="7" y="3" width="10" height="18" rx="2.5"/><path d="M11 18h2" stroke-linecap="round"/></svg>';
        var IC_PC    = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8 20h8M12 16.5V20" stroke-linecap="round"/></svg>';
        if (bukti) {
            kartu('', IC_JAM, 'Jam server', bukti.jam);
            kartu(bukti.cocok ? '' : 'merah', IC_WIFI, 'Cabang / WiFi',
                  bukti.cocok ? ('Cocok · ' + bukti.cabang) : ('Tak terdaftar · di luar WiFi cabang (IP ' + bukti.ip + ')'));
            kartu(bukti.desktop ? 'warn' : '', bukti.desktop ? IC_PC : IC_HP, 'Perangkat',
                  bukti.device + (bukti.desktop ? ' · absen dari laptop/PC' : ''));
        } else {
            kartu('', IC_JAM, 'Sumber', 'Bukan dari absen HP (mis. lupa absen pulang — dari data harian)');
        }

        // riwayat — 2 kartu stat
        var uid = flag ? flag.user_id : (bukti ? bukti.user_id : null);
        var r = uid && AWS_RIWAYAT[uid] ? AWS_RIWAYAT[uid] : null;
        document.getElementById('aws-p-hf').textContent = r ? r.total : 0;
        document.getElementById('aws-p-hp').textContent = r ? r.lupa_pulang : 0;

        // daftarkan IP — cuma relevan kalau bukti ada & tak cocok, dan cuma reviewer
        var fd = document.getElementById('aws-p-daftar');
        var bolehAksi = !!fd.dataset.bolehAksi;
        fd.classList.toggle('aws-hidden', !(bolehAksi && bukti && !bukti.cocok));
        if (bukti) document.getElementById('aws-p-daftar-ip').value = bukti.ip;

        // penyelesaian
        var fs = document.getElementById('aws-p-selesai');
        var sudah = document.getElementById('aws-p-sudah');
        if (!bolehAksi && flag && flag.status !== 'selesai') {
            // Finance view-only: lihat status, tanpa form aksi
            fs.classList.add('aws-hidden');
            sudah.classList.remove('aws-hidden');
            sudah.innerHTML = '<div class="aws-sinyal"><span class="tx"><small>Status</small><span class="v"></span></span></div>';
            sudah.querySelector('.v').textContent =
                (flag.status === 'baru' ? 'Baru — menunggu dicek Supervisor' : 'Sedang dicek Supervisor');
        } else if (flag && flag.status !== 'selesai') {
            fs.classList.remove('aws-hidden');
            sudah.classList.add('aws-hidden');
            fs.action = AWS_URL_SELESAI + flag.id + '/selesai';
            awsResetPenyelesaian();
            // buka flag baru → tandai "dicek" diam-diam
            if (flag.status === 'baru') {
                var b = new URLSearchParams(); b.append('_token', AWS_CSRF);
                fetch(AWS_URL_DICEK + flag.id + '/dicek', { method: 'POST', body: b, headers: { 'Accept': 'application/json' } });
                flag.status = 'dicek';
            }
        } else {
            fs.classList.add('aws-hidden');
            if (flag) {
                sudah.classList.remove('aws-hidden');
                // textContent, BUKAN innerHTML — catatan itu input bebas SPV,
                // jangan pernah dirender sebagai HTML (stored XSS).
                var LABEL_HASIL = { wajar: 'Wajar', perlu_koreksi: 'Perlu Koreksi', pelanggaran: 'Pelanggaran' };
                sudah.innerHTML = '<div class="aws-sinyal"><span class="tx"><small>Sudah diselesaikan</small><span class="v"></span></span></div>';
                sudah.querySelector('.v').textContent =
                    (LABEL_HASIL[flag.hasil] || flag.hasil || '—') + (flag.catatan ? (' · ' + flag.catatan) : '');
            } else {
                sudah.classList.add('aws-hidden');
            }
        }
    }

    // posisi navigasi J/K — urutan mengikuti tombol yang terlihat di tab aktif
    var awsNavPos = { type: null, id: null };

    function awsBukaBukti(id) {
        var b = awsCari(AWS_BUKTI, id);
        var f = AWS_FLAGS.filter(function (x) { return x.bukti_id === id; })[0] || null;
        awsNavPos = { type: 'bukti', id: id };
        awsIsiPanel(b, f);
    }

    function awsBukaFlag(id) {
        var f = awsCari(AWS_FLAGS, id);
        var b = f && f.bukti_id ? awsCari(AWS_BUKTI, f.bukti_id) : null;
        awsNavPos = { type: 'flag', id: id };
        awsIsiPanel(b, f);
    }

    // ── "Hanya yang perlu dicek": saring kedua baris (masuk & pulang) sekaligus ──
    var awsToggleCek = document.getElementById('awsToggleCek');
    if (awsToggleCek) awsToggleCek.addEventListener('click', function () {
        var on = awsToggleCek.classList.toggle('on');
        document.querySelectorAll('#tab-strip .aws-tile').forEach(function (t) {
            t.style.display = (on && !t.classList.contains('flagged')) ? 'none' : '';
        });
        document.querySelectorAll('#tab-strip [data-cabang-card]').forEach(function (card) {
            var visible = Array.prototype.some.call(card.querySelectorAll('.aws-tile'), function (t) { return t.style.display !== 'none'; });
            card.style.display = (on && !visible) ? 'none' : '';
        });
    });

    // ── Keyboard: 1/2/3 pilih penyelesaian · Enter simpan · J/K pindah · Esc tutup ──
    function awsNavList() {
        var tabStrip = !document.getElementById('tab-strip').classList.contains('aws-hidden');
        var sel = tabStrip ? '#tab-strip .aws-tile[data-nav-bukti]' : '#tab-flag [data-nav-flag]';
        return Array.prototype.filter.call(document.querySelectorAll(sel), function (el) {
            return el.offsetParent !== null;   // skip yang tersembunyi (toggle/tab)
        }).map(function (el) {
            return tabStrip
                ? { type: 'bukti', id: Number(el.dataset.navBukti) }
                : { type: 'flag', id: Number(el.dataset.navFlag) };
        });
    }
    function awsBuka(item) {
        if (!item) return;
        if (item.type === 'bukti') awsBukaBukti(item.id); else awsBukaFlag(item.id);
    }
    document.addEventListener('keydown', function (e) {
        var tag = (e.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
        var panelOn = !document.getElementById('aws-p-isi').classList.contains('aws-hidden');

        if (e.key === 'Escape') {
            if (!panelOn) return;
            document.getElementById('aws-p-isi').classList.add('aws-hidden');
            document.getElementById('aws-p-kosong').classList.remove('aws-hidden');
            awsNavPos = { type: null, id: null };
            return;
        }
        if (e.key === 'j' || e.key === 'J' || e.key === 'k' || e.key === 'K') {
            var list = awsNavList();
            if (!list.length) return;
            e.preventDefault();
            var i = -1;
            for (var x = 0; x < list.length; x++) {
                if (list[x].type === awsNavPos.type && list[x].id === awsNavPos.id) { i = x; break; }
            }
            var step = (e.key === 'j' || e.key === 'J') ? 1 : -1;
            var next = i < 0 ? 0 : Math.min(list.length - 1, Math.max(0, i + step));
            awsBuka(list[next]);
            return;
        }
        if (!panelOn) return;
        var fs = document.getElementById('aws-p-selesai');
        if (fs.classList.contains('aws-hidden')) return;
        if (e.key === '1' || e.key === '2' || e.key === '3') {
            var map = { '1': 'wajar', '2': 'perlu_koreksi', '3': 'pelanggaran' };
            var card = document.querySelector('.aws-res[data-hasil="' + map[e.key] + '"]');
            if (card) card.click();
        } else if (e.key === 'Enter') {
            var btn = document.getElementById('aws-p-simpan');
            if (!btn.disabled) { e.preventDefault(); fs.requestSubmit ? fs.requestSubmit() : fs.submit(); }
        }
    });

    // ── Beres-beres borongan: centang → bar aksi ──
    // "Pilih semua sejenis" (bukan pilih-semua-tampil) — sengaja: satu vonis
    // untuk satu sebab. Memilih lintas jenis berarti satu catatan dipakai
    // menjelaskan dua cerita berbeda.
    (function () {
        var form = document.getElementById('aws-borong');
        if (!form) return;
        var bar   = document.getElementById('aws-bar');
        var nEl   = document.getElementById('aws-bar-n');
        var idsEl = document.getElementById('aws-borong-ids');
        var cbs   = Array.prototype.slice.call(form.querySelectorAll('.aws-cb'));
        if (!cbs.length) return;

        function terpilih() { return cbs.filter(function (c) { return c.checked; }); }

        function sync() {
            var pick = terpilih();
            cbs.forEach(function (c) { c.closest('.aws-pick').classList.toggle('checked', c.checked); });
            nEl.textContent = pick.length;
            idsEl.value = pick.map(function (c) { return c.value; }).join(',');
            bar.classList.toggle('aws-hidden', pick.length === 0);
        }

        cbs.forEach(function (c) { c.addEventListener('change', sync); });

        document.getElementById('aws-pick-sejenis').addEventListener('click', function () {
            var pertama = terpilih()[0];
            if (!pertama) return;
            var jenis = pertama.getAttribute('data-jenis');
            cbs.forEach(function (c) { if (c.getAttribute('data-jenis') === jenis) c.checked = true; });
            sync();
        });

        document.getElementById('aws-pick-clear').addEventListener('click', function () {
            cbs.forEach(function (c) { c.checked = false; });
            sync();
        });

        form.addEventListener('submit', function (e) {
            var pick = terpilih();
            if (!pick.length) { e.preventDefault(); return; }
            // Satu catatan untuk banyak jenis = satu alasan menjelaskan dua
            // cerita. Tahan di sini, bukan di server, supaya bisa dijelaskan.
            var jenis = {};
            pick.forEach(function (c) { jenis[c.getAttribute('data-jenis')] = true; });
            if (Object.keys(jenis).length > 1 &&
                !confirm('Pilihanmu mencampur ' + Object.keys(jenis).length + ' jenis kejanggalan.\n\n' +
                         'Satu catatan akan dipakai untuk semuanya. Lanjutkan?')) {
                e.preventDefault(); return;
            }
            idsEl.value = pick.map(function (c) { return c.value; }).join(',');
        });

        sync();
    })();
</script>
@endsection
