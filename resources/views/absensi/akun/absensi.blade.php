@extends('layouts.app')

{{--
    "Akun Saya → Absensi Saya" a.k.a Kehadiran Saya — reskin dari
    handoff_kehadiran/Kehadiran Saya.dc.html (2026-07). HP-first.

    Kiri (desktop sticky): identitas → Kartu Hari Ini (garis waktu MASUK→PULANG
    + satu tombol absen yang berubah sendiri) → Rekap bulan 7 status → Ajukan.
    Kanan: kalender bulan 7 kolom (Sn–Mg) → Riwayat permohonan.

    Form Lapor Koreksi & Ajukan Permohonan = form asli (_koreksi_form,
    _pengajuan_form) — hanya cara membukanya yang ikut desain baru.
    Kartu detail hari: absensi/_kartu_absen_harian.blade.php (KAH).
--}}

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/absensi.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/absensi.sweettreats.css')) ?: '1' }}">
<style>
    .aks { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #3B1500; padding: 16px 22px 30px; }
    .aks *, .aks *::before, .aks *::after { box-sizing: border-box; }
    .aks .num { font-variant-numeric: tabular-nums; font-feature-settings: "tnum" 1; }
    .aks .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; }
    .aks-btn { transition: filter .12s ease, background .12s ease; }
    .aks-btn:hover { filter: brightness(.96); }
    .aks-big { transition: transform .12s ease, filter .12s ease; }
    .aks-big:hover { filter: brightness(1.04); }
    .aks-big:active { transform: scale(.985); }

    .aks-grid { display: flex; gap: 18px; align-items: flex-start; }
    .aks-left { display: flex; flex-direction: column; gap: 13px; width: 372px; flex: none; position: sticky; top: 84px; }
    .aks-right { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 16px; }

    .aks-card { background: #fff; border: 1px solid #EFE0CE; border-radius: 18px; box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05); }

    /* identitas */
    .aks-ph { display: flex; align-items: center; gap: 12px; background: linear-gradient(120deg, #5A2410, #3A1100); color: #FBEADB; border-radius: 18px; padding: 15px 17px; }
    .aks-ph-av { width: 42px; height: 42px; flex: none; border-radius: 50%; background: rgba(255,255,255,.16); color: #FFF3E9; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; box-shadow: 0 0 0 2px rgba(255,255,255,.2); }
    .aks-ph-name { font-size: 15.5px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .aks-ph-sub { font-size: 12px; color: #E0BFA6; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .aks-bell { position: relative; width: 38px; height: 38px; flex: none; border-radius: 11px; background: rgba(255,255,255,.13); display: flex; align-items: center; justify-content: center; color: #FBEADB; text-decoration: none; }
    .aks-bell-badge { position: absolute; top: -4px; right: -4px; min-width: 17px; height: 17px; border-radius: 9px; background: #E8842B; color: #FFF6EC; font-size: 10px; font-weight: 800; display: flex; align-items: center; justify-content: center; padding: 0 4px; border: 2px solid #4A1D0B; }

    /* kartu hari ini */
    .aks-today { padding: 16px 17px; display: flex; flex-direction: column; gap: 14px; }
    .aks-k { font-size: 10.5px; font-weight: 800; letter-spacing: .6px; color: #A9866A; }
    .aks-badge { display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent; border-radius: 20px; padding: 7px 14px; font-size: 13px; font-weight: 800; letter-spacing: .2px; white-space: nowrap; }
    .aks-tl { display: flex; align-items: center; gap: 12px; }
    .aks-tl-k { font-size: 9.5px; font-weight: 800; letter-spacing: .5px; color: #A9866A; }
    .aks-tl-v { font-size: 27px; font-weight: 800; color: #3B1500; line-height: 1.1; }
    .aks-tl-v.off { color: #D3BCA6; }
    .aks-tl-line { width: 52px; height: 2px; border-radius: 2px; background: #D9BE9F; }
    .aks-tl-line.open { background: repeating-linear-gradient(90deg, #D9BE9F 0 5px, transparent 5px 10px); }
    .aks-tl-mid { flex: none; display: flex; flex-direction: column; align-items: center; gap: 3px; }
    .aks-tl-dur { font-size: 9.5px; font-weight: 700; color: #C9AE94; }
    .aks-cta { display: flex; align-items: center; justify-content: center; gap: 11px; background: linear-gradient(135deg, #6B2400, #461500); color: #FFEAD6; border-radius: 14px; padding: 15px 18px; text-decoration: none; min-height: 56px; box-shadow: 0 10px 24px -8px rgba(70,21,0,.4); }
    .aks-cta:hover { color: #FFEAD6; }
    .aks-cta-l { font-size: 14.5px; font-weight: 800; }
    .aks-cta-s { font-size: 11.5px; font-weight: 600; color: #E0BFA6; }
    .aks-report { display: flex; align-items: center; justify-content: center; gap: 7px; background: transparent; border: 1px solid #E7D3BE; color: #8A5A16; border-radius: 12px; padding: 11px; font-family: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; min-height: 44px; width: 100%; }

    /* rekap */
    .aks-rekap { padding: 15px 16px; display: flex; flex-direction: column; gap: 13px; }
    .aks-rekap-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .aks-rekap-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(84px, 1fr)); gap: 8px; }
    .aks-rk { display: flex; align-items: center; gap: 9px; border: 1px solid transparent; border-radius: 13px; padding: 10px 11px; }
    .aks-rk-n { font-size: 17px; font-weight: 800; }
    .aks-rk-l { font-size: 10px; font-weight: 700; letter-spacing: .3px; }
    .aks-tiles { display: flex; gap: 8px; flex-wrap: wrap; }
    .aks-tile { flex: 1; min-width: 100px; background: #FBF5ED; border: 1px solid #F2E6D8; border-radius: 12px; padding: 10px 12px; }
    .aks-tile-k { font-size: 9.5px; font-weight: 800; letter-spacing: .5px; color: #A9866A; }
    .aks-tile-v { font-size: 16px; font-weight: 800; margin-top: 2px; }

    .aks-ajukan { display: flex; align-items: center; justify-content: center; gap: 9px; background: linear-gradient(135deg, #6B2400, #461500); color: #FFEAD6; border: none; border-radius: 14px; padding: 15px; font-family: inherit; font-size: 14.5px; font-weight: 800; cursor: pointer; min-height: 52px; box-shadow: 0 10px 24px -8px rgba(70,21,0,.42); width: 100%; }

    /* kalender */
    .aks-cal { overflow: hidden; }
    .aks-cal-head { display: flex; align-items: center; gap: 9px; padding: 13px 15px; border-bottom: 1px solid #F2E6D8; flex-wrap: wrap; }
    .aks-cal-nav { width: 36px; height: 36px; flex: none; border-radius: 10px; border: 1px solid #EFE0CE; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; text-decoration: none; }
    .aks-cal-label { font-size: 16.5px; font-weight: 800; color: #3B1500; min-width: 130px; text-align: center; }
    .aks-cal-today { margin-left: auto; background: linear-gradient(135deg, #6B2400, #461500); color: #FBEADB; border: none; border-radius: 11px; padding: 10px 15px; font-family: inherit; font-size: 12.5px; font-weight: 800; cursor: pointer; min-height: 40px; text-decoration: none; display: inline-flex; align-items: center; }
    .aks-cal-today:hover { color: #FBEADB; }
    .aks-dow { display: grid; grid-template-columns: repeat(7, minmax(0,1fr)); background: #FBF5ED; border-bottom: 1px solid #F2E6D8; }
    .aks-dow div { text-align: center; font-size: 10.5px; font-weight: 800; letter-spacing: .4px; color: #8A6A52; padding: 10px 0; }
    .aks-dow div.red { color: #C0392B; }
    .aks-week { display: grid; grid-template-columns: repeat(7, minmax(0,1fr)); }
    .aks-day { min-height: 92px; border-right: 1px solid #F4EADD; border-bottom: 1px solid #F4EADD; padding: 8px 9px; display: flex; flex-direction: column; gap: 5px; background: #fff; transition: background .12s ease, box-shadow .12s ease; }
    .aks-day.blank { background: #FBF7F1; }
    .aks-day.today { background: #FBF0DE; box-shadow: inset 0 2px 0 #B5701A; }
    .aks-day.klik:hover { background: #FDF7EF; cursor: pointer; box-shadow: inset 0 0 0 2px #EBD6BC; }
    .aks-day-top { display: flex; align-items: center; gap: 5px; }
    .aks-day-num { font-size: 12.5px; font-weight: 700; color: #8A6A52; }
    .aks-day-num.red { color: #C0392B; }
    .aks-day-num.circ { width: 22px; height: 22px; border-radius: 50%; background: linear-gradient(135deg, #6B2400, #461500); color: #FBEADB; display: flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 800; flex: none; }
    .aks-dot { width: 6px; height: 6px; border-radius: 50%; flex: none; }
    .aks-chip { display: inline-flex; align-items: center; gap: 5px; align-self: flex-start; max-width: 100%; border: 1px solid transparent; border-radius: 9px; padding: 4px 8px; }
    .aks-chip-l { font-size: 11px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .aks-day-jam { font-size: 10px; color: #A9866A; font-weight: 600; }
    .aks-cal-foot { display: flex; gap: 13px; padding: 12px 15px; background: #FCF7F0; border-top: 1px solid #F2E6D8; flex-wrap: wrap; align-items: center; }
    .aks-leg { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: #7A5B43; font-weight: 600; }
    .aks-leg-box { width: 20px; height: 20px; border-radius: 6px; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; }
    .aks-cal-hint { margin-left: auto; font-size: 11.5px; color: #A9866A; }

    /* riwayat */
    .aks-rw-head { display: flex; align-items: center; gap: 9px; margin: 4px 2px 10px; }
    .aks-rw-list { display: flex; flex-direction: column; gap: 9px; }
    .aks-rw { display: flex; align-items: center; gap: 11px; background: #fff; border: 1px solid #EFE0CE; border-radius: 14px; padding: 12px 14px; cursor: pointer; transition: background .12s ease; }
    .aks-rw:hover { background: #FDF7F0; }
    .aks-rw-type { display: inline-flex; align-items: center; flex: none; border: 1px solid transparent; border-radius: 9px; padding: 5px 10px; font-size: 11.5px; font-weight: 800; white-space: nowrap; }
    .aks-rw-date { font-size: 12.5px; font-weight: 700; color: #3B1500; }
    .aks-rw-reason { font-size: 11.5px; color: #9A7256; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .aks-rw-pill { display: inline-flex; align-items: center; flex: none; border: 1px solid transparent; border-radius: 20px; padding: 4px 10px; font-size: 11px; font-weight: 800; white-space: nowrap; }
    /* chevron kartu riwayat (pola-interaksi P1) — aksen cokelat, redup saat diam */
    .aks-rw-chev { flex: none; display: flex; color: #8A3A12; opacity: .8; transition: transform .15s ease, opacity .15s ease; }
    .aks-rw:hover .aks-rw-chev { opacity: 1; transform: translateX(2px); }
    .aks-rw-empty { background: #fff; border: 1px dashed #E2C8A8; border-radius: 14px; padding: 28px; text-align: center; font-size: 12.5px; color: #A9866A; }

    /* kartu detail (KAH) */
    .aks-scrim { position: fixed; inset: 0; background: rgba(38,18,4,.44); z-index: 1080; display: none; animation: aksFadeIn .18s ease; }
    .aks-scrim.on { display: block; }
    .aks-shell { position: fixed; z-index: 1081; display: none; }
    .aks-shell.on { display: block; }
    .aks-shell.panel { top: 50%; left: 50%; transform: translate(-50%, -50%); width: 376px; max-width: calc(100vw - 32px); border-radius: 18px; overflow: hidden; box-shadow: 0 26px 60px -14px rgba(40,16,0,.42); animation: aksPopIn .16s ease; max-height: 88vh; overflow-y: auto; background: #fff; }
    .aks-shell.sheet { left: 0; right: 0; bottom: 0; border-radius: 20px 20px 0 0; overflow: hidden; max-height: 88vh; overflow-y: auto; background: #fff; box-shadow: 0 -14px 40px rgba(40,16,0,.28); animation: aksSheetIn .24s cubic-bezier(.4,0,.2,1); }

    @keyframes aksFadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes aksPopIn { from { opacity: 0; transform: translate(-50%, -50%) scale(.985); } to { opacity: 1; transform: translate(-50%, -50%); } }
    @keyframes aksSheetIn { from { transform: translateY(100%); } to { transform: translateY(0); } }
    @media (prefers-reduced-motion: reduce) {
        .aks *, .aks-shell, .aks-scrim { animation-duration: .01ms !important; transition-duration: .01ms !important; }
    }

    @media (max-width: 900px) {
        .aks { padding: 14px 14px 30px; }
        .aks-grid { flex-direction: column; gap: 14px; }
        .aks-left { width: 100%; position: static; }
        .aks-right { width: 100%; }
        .aks-day { min-height: 70px; padding: 6px 5px; gap: 4px; }
        .aks-day-jam { display: none; }
        .aks-chip { padding: 3px 6px; }
        .aks-chip-l { font-size: 10px; }
        .aks-cal-label { min-width: 0; flex: 1; }
        .aks-cal-hint { margin-left: 0; }
    }
</style>
@endsection

@php
    use Illuminate\Support\Carbon;

    // kamus warna status — samakan dengan KAH.ST (JS) di _kartu_absen_harian
    $STC = [
        'hadir' => ['l' => 'Hadir', 'bg' => '#E3F4E8', 'c' => '#2F8E55', 'br' => '#C9E6D3', 'ico' => 'check'],
        'telat' => ['l' => 'Telat', 'bg' => '#FBEFD9', 'c' => '#B5701A', 'br' => '#F0DDBB', 'ico' => 'clock'],
        'alpa'  => ['l' => 'Alpa',  'bg' => '#FBE3DE', 'c' => '#C0392B', 'br' => '#F2CFC7', 'ico' => 'cross'],
        'cuti'  => ['l' => 'Cuti',  'bg' => '#E4ECFB', 'c' => '#3F6BAF', 'br' => '#CFDCF3', 'ico' => 'sun'],
        'izin'  => ['l' => 'Izin',  'bg' => '#F4E7D6', 'c' => '#9A6614', 'br' => '#E8D5B9', 'ico' => 'note'],
        'wfh'   => ['l' => 'WFH',   'bg' => '#DFEDE9', 'c' => '#2E7D74', 'br' => '#C7DED8', 'ico' => 'home'],
        'libur' => ['l' => 'Libur', 'bg' => '#F1E9DF', 'c' => '#8A6A52', 'br' => '#E4D8C9', 'ico' => 'moon'],
    ];
    $hm = fn ($t) => $t ? Carbon::parse($t)->format('H:i') : null;
    $hariF = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $reqLabel = [
        'draft' => ['Draf', '#F1E9DF', '#8A6A52', '#E4D8C9'],
        'pending_l1' => ['Menunggu', '#FBEFD9', '#B5701A', '#F0DDBB'],
        'pending_l2' => ['Menunggu', '#FBEFD9', '#B5701A', '#F0DDBB'],
        'approved' => ['Disetujui', '#E3F4E8', '#2F8E55', '#C9E6D3'],
        'rejected' => ['Ditolak', '#FBE3DE', '#C0392B', '#F2CFC7'],
        'cancelled' => ['Dibatalkan', '#F1E9DF', '#8A6A52', '#E4D8C9'],
    ];
    $typeColor = [
        'cuti' => ['#E4ECFB', '#3F6BAF', '#CFDCF3'],
        'izin' => ['#F4E7D6', '#9A6614', '#E8D5B9'],
        'tukar_libur' => ['#F1E9DF', '#8A6A52', '#E4D8C9'],
        'lembur' => ['#FBEFD9', '#B5701A', '#F0DDBB'],
        'wfh' => ['#DFEDE9', '#2E7D74', '#C7DED8'],
        'koreksi' => ['#F7EFE6', '#8A5A16', '#E8D5B9'],
    ];

    // ── Hari Ini ──
    $todayIso = Carbon::today()->toDateString();
    $tIn  = $today ? $hm($today->scan_first) : null;
    $tOutRaw = $today ? $hm($today->scan_last) : null;
    $openShift = $today && $today->scan_first && (! $today->scan_last || $tOutRaw === $tIn);
    $tOut = $openShift ? null : $tOutRaw;
    $tSt  = $today->status ?? null;
    $durasi = '—';
    if ($openShift) $durasi = 'berjalan';
    elseif ($today && $today->scan_first && $tOut) {
        $mnt = Carbon::parse($today->scan_first)->diffInMinutes(Carbon::parse($today->scan_last));
        $durasi = str_replace('.', ',', (string) round($mnt / 60, 1)) . ' jam';
    }

    // ── rekap 7 status ──
    $cnt7 = [];
    foreach (array_keys($STC) as $k) $cnt7[$k] = $days->where('status', $k)->count();
    $recorded = $days->count();

    // ── kalender: offset Senin-awal ──
    $calDow = ['Sn','Sl','Rb','Km','Jm','Sb','Mg'];
    $firstOff = ($anchor->copy()->startOfMonth()->dayOfWeek + 6) % 7;
    $calCount = (int) $anchor->daysInMonth;
    $isCurMonth = $anchor->isSameMonth(Carbon::now());
@endphp

@section('content')
<div class="aks">
    @include('layouts.notif')

    <div class="aks-grid">

    {{-- ══════ KIRI ══════ --}}
    <div class="aks-left">

        <div class="aks-ph">
            <div class="aks-ph-av">{{ $me['inisial'] }}</div>
            <div style="flex:1;min-width:0">
                <div class="aks-ph-name">{{ $me['nama'] }}</div>
                <div class="aks-ph-sub">{{ $me['jabatan'] ?? 'Karyawan' }}@if($me['cabang']) · Cabang {{ $me['cabang'] }}@endif</div>
            </div>
            <a href="#" class="aks-bell" title="Notifikasi">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2.5a4 4 0 0 0-4 4c0 3.6-1.5 5-1.5 5h11S13 10.1 13 6.5a4 4 0 0 0-4-4ZM7.3 14a1.9 1.9 0 0 0 3.4 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @if($notifCount)<span class="aks-bell-badge num">{{ $notifCount > 9 ? '9+' : $notifCount }}</span>@endif
            </a>
        </div>

        {{-- KARTU HARI INI --}}
        <div class="aks-card aks-today">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span class="aks-k">HARI INI · <span class="num">{{ Carbon::today()->translatedFormat('d M') }}</span></span>
                @if($openShift)
                    <span class="aks-badge" style="background:#E3F4E8;color:#2F8E55;border-color:#C9E6D3"><span class="kico" data-kico="clock" data-ksz="15"></span>Sedang bekerja</span>
                @elseif($tSt && isset($STC[$tSt]))
                    <span class="aks-badge" style="background:{{ $STC[$tSt]['bg'] }};color:{{ $STC[$tSt]['c'] }};border-color:{{ $STC[$tSt]['br'] }}"><span class="kico" data-kico="{{ $STC[$tSt]['ico'] }}" data-ksz="15"></span>{{ $STC[$tSt]['l'] }}</span>
                @else
                    <span class="aks-badge" style="background:#F1E9DF;color:#8A6A52;border-color:#E4D8C9">Belum absen</span>
                @endif
            </div>

            <div class="aks-tl">
                <div style="flex:1;min-width:0">
                    <div class="aks-tl-k">MASUK</div>
                    <div class="aks-tl-v mono">{{ $tIn ?? '—' }}</div>
                </div>
                <div class="aks-tl-mid">
                    <div class="aks-tl-line {{ $openShift ? 'open' : '' }}"></div>
                    <span class="aks-tl-dur">{{ $durasi }}</span>
                </div>
                <div style="flex:1;min-width:0;text-align:right">
                    <div class="aks-tl-k">PULANG</div>
                    <div class="aks-tl-v mono {{ $tOut ? '' : 'off' }}">{{ $tOut ?? '—' }}</div>
                </div>
            </div>

            <a class="aks-cta aks-big" href="{{ route('akun.absen') }}">
                <span class="kico" data-kico="camera" data-ksz="22"></span>
                <span style="display:flex;flex-direction:column;align-items:flex-start;line-height:1.25">
                    <span class="aks-cta-l">{{ $openShift ? 'Absen Pulang' : 'Absen Masuk' }}</span>
                    <span class="aks-cta-s">{{ $openShift ? 'Masuk ' . $tIn . ' · belum absen pulang' : 'Selfie di titik absen cabang' }}</span>
                </span>
            </a>

            <button type="button" class="aks-report aks-btn"
                    data-koreksi="{{ $todayIso }}"
                    data-kor-label="{{ Carbon::today()->translatedFormat('d M Y') }}"
                    data-kor-status="{{ $openShift ? 'Sedang bekerja' : ($tSt && isset($STC[$tSt]) ? $STC[$tSt]['l'] : 'Belum absen') }}"
                    data-kor-klas="s-{{ $tSt ?: 'libur' }}"
                    data-kor-jam="{{ $tIn ? ($tIn . ' – ' . ($tOut ?? 'belum absen pulang')) : '—' }}">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M8 2.2 14.4 13.2H1.6L8 2.2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 6.4v3M8 11.2v.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                Absen hari ini keliru? Lapor koreksi
            </button>
        </div>

        {{-- REKAP BULAN --}}
        <div class="aks-card aks-rekap">
            <div class="aks-rekap-head">
                <span class="aks-k">REKAP {{ strtoupper($anchor->translatedFormat('F')) }}</span>
                <span class="num" style="font-size:11.5px;color:#A9866A;font-weight:600">{{ $recorded }} hari tercatat</span>
            </div>
            <div class="aks-rekap-grid">
                @foreach($STC as $k => $st)
                    <div class="aks-rk" style="background:{{ $st['bg'] }};color:{{ $st['c'] }};border-color:{{ $st['br'] }}">
                        <span class="kico" data-kico="{{ $st['ico'] }}" data-ksz="15"></span>
                        <span style="display:flex;flex-direction:column;line-height:1.15">
                            <span class="aks-rk-n num">{{ $cnt7[$k] }}</span>
                            <span class="aks-rk-l">{{ strtoupper($st['l']) }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="aks-tiles">
                <div class="aks-tile">
                    <div class="aks-tile-k">TOTAL TELAT</div>
                    <div class="aks-tile-v num" style="color:#B5701A">{{ $rekap['telat_menit'] }} menit</div>
                </div>
                <div class="aks-tile">
                    <div class="aks-tile-k">LEMBUR DISETUJUI</div>
                    <div class="aks-tile-v num" style="color:#2F8E55">{{ $rekap['lembur_menit'] ? str_replace('.', ',', (string) round($rekap['lembur_menit'] / 60, 1)) : 0 }} jam</div>
                </div>
                @if($cutiSisa !== null)
                    <div class="aks-tile">
                        <div class="aks-tile-k">SISA CUTI</div>
                        <div class="aks-tile-v num" style="color:{{ $cutiSisa < 0 ? '#C0392B' : '#3F6BAF' }}">{{ rtrim(rtrim(number_format($cutiSisa, 1, ',', '.'), '0'), ',') }} hari</div>
                        @if($cutiSisa < 0)<div class="aks-tile-k" style="margin-top:1px">melebihi jatah — ganti hari</div>@endif
                    </div>
                @endif
            </div>
        </div>

        <button type="button" class="aks-ajukan aks-big" data-open-ajukan>
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 4v10M4 9h10" stroke="#FFEAD6" stroke-width="2" stroke-linecap="round"/></svg>
            Ajukan Permohonan
        </button>
    </div>

    {{-- ══════ KANAN ══════ --}}
    <div class="aks-right">

        {{-- KALENDER BULAN --}}
        <div class="aks-card aks-cal">
            <div class="aks-cal-head">
                <a class="aks-cal-nav aks-btn" href="{{ route('akun.absensi', ['m' => $anchor->copy()->subMonth()->format('Y-m')]) }}" title="Bulan sebelumnya">
                    <svg width="16" height="16" viewBox="0 0 17 17" fill="none"><path d="M10.5 3.5 5.5 8.5l5 5" stroke="#7A5B43" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <div class="aks-cal-label num">{{ $anchor->translatedFormat('F Y') }}</div>
                <a class="aks-cal-nav aks-btn" href="{{ route('akun.absensi', ['m' => $anchor->copy()->addMonth()->format('Y-m')]) }}" title="Bulan berikutnya">
                    <svg width="16" height="16" viewBox="0 0 17 17" fill="none"><path d="M6.5 3.5l5 5-5 5" stroke="#7A5B43" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <a class="aks-cal-today aks-btn" href="{{ route('akun.absensi', ['m' => Carbon::now()->format('Y-m')]) }}">Hari Ini</a>
            </div>

            <div class="aks-dow">
                @foreach($calDow as $i => $dl)<div class="{{ $i === 6 ? 'red' : '' }}">{{ $dl }}</div>@endforeach
            </div>

            @php $cellNo = 0; @endphp
            <div class="aks-week">
            @for($i = 0; $i < $firstOff; $i++)
                <div class="aks-day blank"></div>
                @php $cellNo++; @endphp
            @endfor
            @for($d = 1; $d <= $calCount; $d++)
                @php
                    $ds = $anchor->format('Y-m') . '-' . sprintf('%02d', $d);
                    $cd = $days[$ds] ?? null;
                    $st = $cd->status ?? null;
                    $meta = $st ? ($STC[$st] ?? null) : null;
                    $isToday = $ds === $todayIso;
                    $wd = ($firstOff + $d - 1) % 7;   // 0=Sn .. 6=Mg
                    $red = $wd === 6;
                    $dOpen = $isToday && $cd && $cd->scan_first && (! $cd->scan_last || $cd->scan_last === $cd->scan_first);
                    $cin = $cd ? $hm($cd->scan_first) : null;
                    $cout = $cd && $cd->scan_last ? $hm($cd->scan_last) : null;
                    if ($cout !== null && $cout === $cin) $cout = null;   // beda detik saja = satu jam
                    $cjam = $cin ? ($cin . ($cout ? '–' . $cout : ($dOpen ? '– …' : ''))) : null;
                    $ada = (bool) $meta;
                    $tip = $ada ? (Carbon::parse($ds)->translatedFormat('d M') . ' · ' . $meta['l'] . ($cjam ? ' · ' . $cjam : '') . ($cd->telat_menit ? ' · telat ' . $cd->telat_menit . ' mnt' : '')) : '';
                @endphp
                <div class="aks-day {{ $isToday ? 'today' : '' }} {{ $ada ? 'klik' : '' }}"
                     @if($ada) role="button" tabindex="0" data-day="{{ $ds }}" title="{{ $tip }}" @endif>
                    <div class="aks-day-top">
                        <span class="aks-day-num num {{ $isToday ? 'circ' : ($red ? 'red' : '') }}">{{ $d }}</span>
                        @if($cd && $cd->lembur_menit_disetujui)<span class="aks-dot" style="background:#B5701A"></span>@endif
                        @if(isset($buktiMap[$ds]))<span class="aks-dot" style="background:#3F6BAF"></span>@endif
                    </div>
                    @if($ada)
                        <span class="aks-chip" style="background:{{ $meta['bg'] }};color:{{ $meta['c'] }};border-color:{{ $meta['br'] }}">
                            <span class="kico" data-kico="{{ $meta['ico'] }}" data-ksz="12"></span>
                            <span class="aks-chip-l">{{ $meta['l'] }}</span>
                        </span>
                        @if($cjam)<span class="aks-day-jam mono">{{ $cjam }}</span>@endif
                    @endif
                </div>
                @php $cellNo++; @endphp
                @if($cellNo % 7 === 0 && $d < $calCount)
                    </div><div class="aks-week">
                @endif
            @endfor
            @while($cellNo % 7 !== 0)
                <div class="aks-day blank"></div>
                @php $cellNo++; @endphp
            @endwhile
            </div>

            <div class="aks-cal-foot">
                @foreach($STC as $k => $st)
                    <span class="aks-leg"><span class="aks-leg-box" style="background:{{ $st['bg'] }};border-color:{{ $st['br'] }};color:{{ $st['c'] }}"><span class="kico" data-kico="{{ $st['ico'] }}" data-ksz="12"></span></span>{{ $st['l'] }}</span>
                @endforeach
                <span class="aks-leg"><span style="width:7px;height:7px;border-radius:50%;background:#3F6BAF"></span>ada foto</span>
                <span class="aks-cal-hint">Ketuk tanggal untuk lihat detail</span>
            </div>
        </div>

        {{-- RIWAYAT --}}
        <div>
            <div class="aks-rw-head">
                <span style="font-size:12px;font-weight:800;letter-spacing:.4px;color:#7A5B43">RIWAYAT PERMOHONAN</span>
                <span class="num" style="font-size:11px;font-weight:800;color:#A9866A;background:#F4E7D6;border-radius:20px;padding:2px 9px">{{ $requests->count() }}</span>
            </div>
            @if($requests->isEmpty())
                <div class="aks-rw-empty">Belum ada pengajuan.</div>
            @else
                <div class="aks-rw-list">
                    @foreach($requests as $r)
                        @php
                            $tc = $typeColor[$r->tipe] ?? $typeColor['koreksi'];
                            $rl = $reqLabel[$r->status] ?? [$r->status, '#F1E9DF', '#8A6A52', '#E4D8C9'];
                        @endphp
                        <div class="aks-rw" data-detail="{{ route('akun.pengajuan.show', $r->id) }}" role="button" tabindex="0">
                            <span class="aks-rw-type" style="background:{{ $tc[0] }};color:{{ $tc[1] }};border-color:{{ $tc[2] }}">{{ $tipeList[$r->tipe] ?? $r->tipe }}</span>
                            <div style="flex:1;min-width:0">
                                <div class="aks-rw-date num">
                                    {{ Carbon::parse($r->tanggal_from)->translatedFormat('d M Y') }}@if($r->tanggal_to != $r->tanggal_from) – {{ Carbon::parse($r->tanggal_to)->translatedFormat('d M Y') }}@endif
                                </div>
                                @if($r->alasan)<div class="aks-rw-reason">{{ $r->alasan }}</div>@endif
                            </div>
                            <span class="aks-rw-pill" style="background:{{ $rl[1] }};color:{{ $rl[2] }};border-color:{{ $rl[3] }}">{{ $rl[0] }}</span>
                            {{-- chevron: kartu ini membuka detail (pola-interaksi P1) --}}
                            <span class="aks-rw-chev" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    </div>{{-- .aks-grid --}}
</div>

{{-- ── Kartu detail hari (KAH): panel tengah desktop / bottom sheet HP ── --}}
<div class="aks-scrim" id="aksScrim"></div>
<div class="aks-shell" id="aksShell" role="dialog" aria-modal="true"></div>

{{-- ── Drawer Ajukan Permohonan (form asli) ── --}}
<div class="absk-ajscrim" id="ajScrim"></div>
<aside class="absk-ajdrawer" id="ajDrawer" aria-hidden="true">
    <div class="absk-ajhead">
        <div class="absk-ajhead-icon">+</div>
        <div class="absk-ajhead-tx">
            <div class="absk-ajhead-sub">{{ strtoupper($me['nama']) }}@if($me['cabang']) · {{ strtoupper($me['cabang']) }}@endif</div>
            <div class="absk-ajhead-title">Ajukan Permohonan</div>
        </div>
        <button type="button" class="absk-ajclose" data-close-ajukan aria-label="Tutup">✕</button>
    </div>
    @include('absensi.akun._pengajuan_form', ['tipeList' => $tipeList, 'tipe' => $ajTipe, 'inDrawer' => true, 'wfhUsed' => $wfhUsed])
</aside>

{{-- ── Drawer detail riwayat (populate via fetch) ── --}}
<div class="absk-dtscrim" id="dtScrim"></div>
<aside class="absk-dtdrawer" id="dtDrawer" aria-hidden="true">
    <div class="absk-ajhead">
        <div class="absk-ajhead-icon"><i class="fa fa-file-alt"></i></div>
        <div class="absk-ajhead-tx">
            <div class="absk-ajhead-sub">RIWAYAT</div>
            <div class="absk-ajhead-title">Detail Pengajuan</div>
        </div>
        <button type="button" class="absk-ajclose" data-close-detail aria-label="Tutup">✕</button>
    </div>
    <div class="absk-dtbody" id="dtBody"><div class="absk-empty">Memuat…</div></div>
</aside>

{{-- ── Modal Koreksi Absensi (form asli) ── --}}
<div class="absk-korwrap" id="korWrap" role="dialog" aria-modal="true" aria-hidden="true">
    @include('absensi.akun._koreksi_form', ['korAlasan' => $korAlasan])
</div>

@include('absensi._kartu_absen_harian')

<script>
var AKS_DAYS = {
    @foreach($days as $tgl => $d)
        @php
            $din  = $hm($d->scan_first);
            $dout = $d->scan_last ? $hm($d->scan_last) : null;
            if ($dout !== null && $dout === $din) $dout = null;   // beda detik saja = satu jam
            $dc = Carbon::parse($tgl);
            $fotoArr = array_map(fn ($f) => ['id' => $f['id'], 'j' => $f['jenis'], 'w' => $f['jam']], $buktiMap[$tgl] ?? []);
        @endphp
        @json($tgl): {
            s: @json($d->status),
            i: @json($din), o: @json($dout),
            t: {{ (int) $d->telat_menit }}, l: {{ (int) $d->lembur_menit_disetujui }},
            g: {{ (int) $d->single_scan }},
            f: @json($fotoArr),
            dl: @json($hariF[$dc->dayOfWeek] . ', ' . $dc->translatedFormat('d F Y')),
            kl: @json($dc->translatedFormat('d M Y')),
            sl: @json(($STC[$d->status]['l'] ?? ucfirst($d->status)))
        },
    @endforeach
};
var AKS_ME = { nama: @json($me['nama']), sub: @json(($me['jabatan'] ?? 'Karyawan') . ($me['cabang'] ? ' · ' . $me['cabang'] : '')) };
var AKS_FOTO_BASE = @json(url('akun/absensi/foto'));
var AKS_TODAY = @json($todayIso);
</script>
<script>
(function () {
    'use strict';
    var body = document.body;
    var narrow = function () { return window.matchMedia('(max-width:900px)').matches; };

    // hydrate ikon SVG (server render markup, ikon dari kamus KAH)
    document.querySelectorAll('.kico').forEach(function (el) {
        el.outerHTML = KAH.svg(el.dataset.kico, Number(el.dataset.ksz || 14));
    });

    // ── Kartu detail hari (KAH) ──
    var curDay = null;
    function openDay(ds) {
        var c = AKS_DAYS[ds];
        if (!c) return;
        curDay = ds;
        var shell = document.getElementById('aksShell');
        shell.innerHTML = KAH.render({
            person: AKS_ME.nama,
            roleBranch: AKS_ME.sub,
            dateLabel: c.dl,
            status: c.s,
            jamIn: c.i, jamOut: c.o,
            single: (ds === AKS_TODAY && c.i && !c.o) ? 0 : c.g,
            lateMin: c.t, lemburJam: Math.round(c.l / 60 * 10) / 10,
            photos: (c.f || []).map(function (f) { return { url: AKS_FOTO_BASE + '/' + f.id, jenis: f.j, jam: f.w }; }),
            variant: narrow() ? 'sheet' : 'panel',
            monthBtnLabel: 'Lapor koreksi',
            hint: 'Esc tutup',
            onClose: 'aksCloseDay()',
            onOpenMonth: 'aksKoreksiFromDay()'
        });
        shell.className = 'aks-shell on ' + (narrow() ? 'sheet' : 'panel');
        document.getElementById('aksScrim').classList.add('on');
    }
    window.aksCloseDay = function () {
        curDay = null;
        document.getElementById('aksShell').classList.remove('on');
        document.getElementById('aksScrim').classList.remove('on');
    };
    window.aksKoreksiFromDay = function () {
        var ds = curDay, c = AKS_DAYS[ds];
        window.aksCloseDay();
        if (!c) return;
        openKorPrefill(ds, c.kl, c.sl, 's-' + c.s, c.i ? (c.i + ' – ' + (c.o || (ds === AKS_TODAY ? 'belum absen pulang' : '—'))) : '—');
    };
    document.getElementById('aksScrim').addEventListener('click', window.aksCloseDay);
    document.querySelectorAll('.aks-day[data-day]').forEach(function (el) {
        var go = function () { openDay(el.dataset.day); };
        el.addEventListener('click', go);
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });

    // ── Drawer detail riwayat ──
    var dtBody = document.getElementById('dtBody');
    function closeDt() { body.classList.remove('absk-dtopen'); }
    document.querySelectorAll('[data-detail]').forEach(function (card) {
        card.addEventListener('click', function () {
            var url = card.getAttribute('data-detail');
            dtBody.innerHTML = '<div class="absk-empty">Memuat…</div>';
            body.classList.add('absk-dtopen');
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.text(); })
                .then(function (html) { dtBody.innerHTML = html; })
                .catch(function () { dtBody.innerHTML = '<div class="absk-empty">Gagal memuat detail.</div>'; });
        });
    });
    document.querySelectorAll('[data-close-detail]').forEach(function (b) { b.addEventListener('click', closeDt); });
    document.getElementById('dtScrim').addEventListener('click', closeDt);

    // ── Drawer Ajukan ──
    function openAj() { body.classList.add('absk-ajopen'); }
    function closeAj() { body.classList.remove('absk-ajopen'); }
    document.querySelectorAll('[data-open-ajukan]').forEach(function (b) { b.addEventListener('click', openAj); });
    document.querySelectorAll('[data-close-ajukan]').forEach(function (b) { b.addEventListener('click', closeAj); });
    var ajScrim = document.getElementById('ajScrim');
    if (ajScrim) ajScrim.addEventListener('click', closeAj);

    // ── Modal Koreksi ──
    var korWrap = document.getElementById('korWrap');
    var korForm = document.getElementById('korForm');
    var korSubmit = document.getElementById('korSubmit');
    function openKorPrefill(iso, label, statusLbl, klas, jam) {
        korForm.reset();
        korSubmit.disabled = true;
        korSubmit.textContent = 'Pilih dulu apa yang keliru';   // P3: alasan matinya tertulis
        document.getElementById('korFotoZone').classList.remove('is-on');
        document.querySelector('.absk-korfoto-tick').hidden = true;
        document.querySelector('.absk-korfoto-lbl').textContent = 'Lampirkan foto (opsional)';
        document.getElementById('korTanggal').value = iso;
        document.getElementById('korTglLabel').textContent = label;
        var pill = document.getElementById('korStatusPill');
        pill.textContent = statusLbl;
        pill.className = 'absk-pill ' + klas;
        document.getElementById('korJam').textContent = 'tercatat ' + jam;
        body.classList.add('absk-koropen');
    }
    function closeKor() { body.classList.remove('absk-koropen'); }
    document.querySelectorAll('[data-koreksi]').forEach(function (row) {
        var go = function () {
            openKorPrefill(
                row.getAttribute('data-koreksi'),
                row.getAttribute('data-kor-label'),
                row.getAttribute('data-kor-status'),
                row.getAttribute('data-kor-klas'),
                row.getAttribute('data-kor-jam')
            );
        };
        row.addEventListener('click', go);
        row.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });
    korForm.querySelectorAll('input[name="koreksi_alasan"]').forEach(function (r) {
        r.addEventListener('change', function () { korSubmit.disabled = false; });
    });
    document.getElementById('korFotoInput').addEventListener('change', function () {
        var ada = this.files && this.files.length;
        document.getElementById('korFotoZone').classList.toggle('is-on', !!ada);
        document.querySelector('.absk-korfoto-tick').hidden = !ada;
        document.querySelector('.absk-korfoto-lbl').textContent = ada ? 'Foto bukti terlampir' : 'Lampirkan foto (opsional)';
    });
    document.querySelectorAll('[data-close-koreksi]').forEach(function (b) { b.addEventListener('click', closeKor); });
    korWrap.addEventListener('click', function (e) { if (e.target === korWrap) closeKor(); });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        window.aksCloseDay(); closeAj(); closeDt(); closeKor();
    });

    @if(old('tipe')) openAj(); @endif
})();
</script>
@endsection
