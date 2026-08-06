@extends('layouts.app')

{{-- ============================================================
     Absen HP (selfie) — visual dari handoff_absen_hp (9 state).
     Ikon: SVG garis minimalis (currentColor) — bukan emoji, ikut
     gaya handoff. Logika server: AkunAbsenController + AttendanceHpAbsen.
     Prinsip: absen tak pernah ditolak; janggal = flag (urusan SPV).
     ============================================================ --}}

@php
    // Ikon SVG dipakai ulang — 1em mengikuti font-size wadahnya.
    $svgCam   = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5A1.5 1.5 0 0 1 4.5 7H7l1.4-2.2a1 1 0 0 1 .8-.5h5.6a1 1 0 0 1 .8.5L17 7h2.5A1.5 1.5 0 0 1 21 8.5V18a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18z"/><circle cx="12" cy="13" r="3.3"/></svg>';
    $svgWifi  = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 9.5a12 12 0 0 1 15 0"/><path d="M7.5 12.8a7.5 7.5 0 0 1 9 0"/><path d="M10.3 16a3.5 3.5 0 0 1 3.4 0"/><circle cx="12" cy="19" r=".7" fill="currentColor" stroke="none"/></svg>';
    $svgPoster= '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4.5" width="14" height="16" rx="2"/><path d="M9 4.5V3.5h6v1M8.5 10h7M8.5 13.5h7M8.5 17h4"/></svg>';
    $svgPin   = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s6.5-5.5 6.5-11a6.5 6.5 0 1 0-13 0C5.5 15.5 12 21 12 21z"/><circle cx="12" cy="10" r="2.3"/></svg>';
    $svgCheck = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
    $svgCamOff= '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 2l20 20"/><path d="M9 7l.9-1.4a1 1 0 0 1 .8-.5h2.6a1 1 0 0 1 .8.5L18 7h1.5A1.5 1.5 0 0 1 21 8.5V17M20.5 20.5H4.5A1.5 1.5 0 0 1 3 19V8.5A1.5 1.5 0 0 1 4.5 7"/><path d="M9.5 12.8A3.3 3.3 0 0 0 14 16.4"/></svg>';
@endphp

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/absensi.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/absensi.sweettreats.css')) ?: '1' }}">
<style>
    /* Halaman aksi fokus — footer copyright shell disembunyikan supaya tak
       bertabrakan dengan tombol Absen yang fixed di bawah. */
    .footer { display: none !important; }

    .abshp-wrap { max-width: 480px; margin: 0 auto; padding: 14px 14px 120px; font-family: 'Plus Jakarta Sans', sans-serif; }
    .abshp-back { color: #461500; font-weight: 700; text-decoration: none; font-size: 14px; }
    .abshp-ic { display: inline-flex; vertical-align: -.15em; }
    /* status hari ini — kartu coklat tua (handoff mobile-1) */
    .abshp-status { background: linear-gradient(135deg, #3B1500, #5A2410); color: #fff; border-radius: 16px; padding: 16px 18px; margin: 12px 0; }
    .abshp-status .k { display: flex; align-items: center; gap: 7px; font-size: 11px; letter-spacing: .08em; color: #E8842B; font-weight: 800; }
    .abshp-status .k .dot { width: 7px; height: 7px; border-radius: 50%; background: #E8842B; }
    .abshp-status .v { font-size: 19px; font-weight: 800; line-height: 1.3; margin-top: 6px; }
    .abshp-status .v small { display: block; font-size: 13px; font-weight: 600; color: #E6C09B; margin-top: 3px; }
    /* garis waktu MASUK → PULANG (handoff_kehadiran §7 — sama dgn Kehadiran Saya) */
    .abshp-tl { display: flex; align-items: center; gap: 12px; margin-top: 10px; }
    .abshp-tl .kk { font-size: 9.5px; font-weight: 800; letter-spacing: .5px; color: #C9A183; }
    .abshp-tl .jj { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; font-size: 26px; font-weight: 800; color: #FFF3E9; line-height: 1.1; }
    .abshp-tl .jj.off { color: #8A6248; }
    .abshp-tl-mid { flex: none; display: flex; flex-direction: column; align-items: center; gap: 3px; }
    .abshp-tl-line { width: 48px; height: 2px; border-radius: 2px; background: #8A6248; }
    .abshp-tl-line.open { background: repeating-linear-gradient(90deg, #B98A63 0 5px, transparent 5px 10px); }
    .abshp-tl-dur { font-size: 9.5px; font-weight: 700; color: #C9A183; }
    .abshp-tl-cabang { font-size: 12px; font-weight: 600; color: #E6C09B; margin-top: 8px; }
    /* checklist "sebelum absen" */
    .abshp-cek { background: #fff; border: 1px solid #EAD8C4; border-radius: 16px; padding: 14px 16px; margin-bottom: 12px; }
    .abshp-cek .k { font-size: 11px; letter-spacing: .08em; color: #C77A1E; font-weight: 800; margin-bottom: 8px; }
    .abshp-cek .item { display: flex; align-items: center; gap: 10px; padding: 6px 0; color: #461500; font-size: 14px; }
    .abshp-cek .item .ic { width: 30px; height: 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center; background: #eaf3f7; color: #3F6BAF; flex: 0 0 auto; font-size: 17px; }
    .abshp-cek .item .ic.oranye { background: #fdf0e0; color: #C77A1E; }
    .abshp-wifi-ok { color: #2F8E55 !important; font-weight: 700; }
    .abshp-inline-ok { color: #2F8E55; font-size: .85em; }
    /* pengingat lupa pulang */
    .abshp-ingat { background: #fff; border: 1.5px solid #F1C994; border-radius: 14px; padding: 12px 14px; margin-bottom: 12px; }
    .abshp-ingat b { color: #461500; font-size: 14px; display: block; }
    .abshp-ingat span { color: #9A7256; font-size: 13px; }
    .abshp-ingat a { color: #C77A1E; font-weight: 800; text-decoration: none; white-space: nowrap; }
    /* CTA zona jempol — SOLID supaya tak tembus ke footer/konten di belakang */
    .abshp-cta { position: fixed; left: 0; right: 0; bottom: 0; padding: 12px 16px calc(16px + env(safe-area-inset-bottom)); background: #F4ECE2; box-shadow: 0 -12px 22px 8px #F4ECE2; max-width: 480px; margin: 0 auto; z-index: 50; }
    .abshp-btn { display: flex; align-items: center; justify-content: center; gap: 9px; width: 100%; padding: 16px; border: 0; border-radius: 16px; font-weight: 800; font-size: 17px; font-family: inherit; text-decoration: none; }
    .abshp-btn .abshp-ic { font-size: 21px; }
    .abshp-btn-utama { background: #461500; color: #fff; box-shadow: 0 6px 18px rgba(70,21,0,.35); }
    .abshp-btn-utama:disabled { opacity: .55; }
    .abshp-btn-kedua { background: #EFE0CE; color: #461500; }
    .abshp-tautan { display: block; text-align: center; color: #9A7256; font-weight: 700; font-size: 14px; margin-top: 12px; text-decoration: none; }
    .abshp-selesai-info { display: flex; align-items: center; justify-content: center; gap: 8px; text-align: center; color: #2F8E55; font-weight: 800; padding: 14px; background: #e8f5e9; border-radius: 16px; }
    /* ── state layar penuh ── */
    .abshp-ov { position: fixed; inset: 0; z-index: 1050; display: flex; flex-direction: column; }
    .abshp-hidden { display: none !important; }
    .abshp-gelap { background: #1b0d05; }
    .abshp-krem { background: #F4ECE2; align-items: center; text-align: center; padding: 18px; overflow-y: auto; }
    .abshp-krem .atas { align-self: flex-start; }
    .abshp-krem .tengah { margin: auto 0; width: 100%; max-width: 340px; align-self: center; }
    .abshp-ikon-bulat { width: 92px; height: 92px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 18px; }
    .abshp-ikon-coklat { background: #5A2410; color: #F1C994; }
    .abshp-ikon-merah { background: #fdecea; color: #C0392B; }
    .abshp-krem h5 { color: #461500; font-weight: 800; font-size: 20px; }
    .abshp-krem p { color: #9A7256; font-size: 14px; line-height: 1.5; }
    .abshp-langkah { display: flex; gap: 10px; align-items: flex-start; background: #fff; border-radius: 12px; padding: 12px 14px; margin-bottom: 8px; text-align: left; color: #5A3A22; font-size: 13.5px; }
    .abshp-langkah .no { flex: 0 0 auto; width: 22px; height: 22px; border-radius: 50%; background: #5A2410; color: #fff; font-size: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; }
    .abshp-ov-back { width: 38px; height: 38px; border-radius: 50%; background: #fff; color: #461500; border: 1px solid #EAD8C4; font-size: 17px; font-weight: 800; }
    /* kamera */
    .abshp-ov-top { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; z-index: 2; }
    .abshp-ov-top .abshp-ov-back { background: rgba(255,255,255,.92); border: 0; }
    .abshp-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 99px; padding: 7px 14px; font-size: 12.5px; font-weight: 800; }
    .abshp-chip-ok { background: #2F8E55; color: #fff; }
    .abshp-chip-tanya { background: #fff3d6; color: #9a6b00; }
    .abshp-chip-siap { position: absolute; top: 14px; left: 14px; z-index: 2; display: inline-flex; align-items: center; gap: 6px; background: rgba(0,0,0,.4); color: #fff; border-radius: 99px; padding: 7px 14px; font-size: 13px; font-weight: 800; }
    .abshp-chip-siap .cek { color: #6fd693; font-size: 15px; display: inline-flex; }
    .abshp-kamera-area { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .abshp-video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
    .abshp-fotoku { width: 100%; height: 100%; object-fit: cover; }
    .abshp-oval { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
    .abshp-oval .o { width: 62vw; max-width: 290px; aspect-ratio: 3/4; border: 2.5px dashed rgba(255,255,255,.75); border-radius: 50%; }
    .abshp-oval .t { color: rgba(255,255,255,.85); font-size: 13px; margin-top: 14px; text-shadow: 0 1px 4px rgba(0,0,0,.5); padding: 0 24px; }
    .abshp-ov-bawah { position: absolute; left: 0; right: 0; bottom: 0; padding: 18px 16px calc(22px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; gap: 10px; align-items: center; background: linear-gradient(transparent, rgba(0,0,0,.55)); }
    .abshp-rana { width: 74px; height: 74px; border-radius: 50%; border: 5px solid #fff; background: rgba(255,255,255,.25); }
    .abshp-rana:active { background: #fff; }
    /* sukses (handoff mobile-7) */
    .abshp-sukses { background: linear-gradient(160deg, #3B1500, #5A2410); align-items: center; justify-content: center; text-align: center; color: #fff; padding: 30px 24px; }
    .abshp-sukses .cek { width: 84px; height: 84px; border-radius: 50%; background: #2F8E55; display: flex; align-items: center; justify-content: center; font-size: 42px; box-shadow: 0 0 0 14px rgba(47,142,85,.18); margin-bottom: 26px; }
    .abshp-sukses .salam { color: #F1C994; font-weight: 700; font-size: 15px; }
    .abshp-sukses .judul { font-weight: 800; font-size: 23px; margin: 4px 0 8px; }
    .abshp-sukses .jam { font-weight: 800; font-size: 56px; letter-spacing: .02em; }
    .abshp-sukses .pil { display: inline-flex; align-items: center; gap: 7px; background: rgba(255,255,255,.14); border-radius: 99px; padding: 8px 16px; font-weight: 700; font-size: 14px; margin-top: 10px; }
    .abshp-sukses .pil .abshp-ic { font-size: 16px; }
    .abshp-sukses .catatan { color: #E6C09B; font-size: 12.5px; margin-top: 14px; max-width: 280px; }
    .abshp-sukses .abshp-tautan { color: #F1C994; }
    /* pilih cabang (handoff mobile-8) */
    .abshp-info-biru { display: flex; gap: 10px; background: #e8f1f8; border: 1px solid #c4dcee; border-radius: 14px; padding: 12px 14px; text-align: left; margin-bottom: 14px; }
    .abshp-info-biru .ic { color: #3F6BAF; font-size: 20px; flex: 0 0 auto; margin-top: 1px; }
    .abshp-info-biru b { color: #3F6BAF; font-size: 14px; display: block; }
    .abshp-info-biru span { color: #5b7f99; font-size: 13px; }
    .abshp-cb { display: flex; align-items: center; gap: 12px; width: 100%; text-align: left; background: #fff; border: 1.5px solid #EAD8C4; border-radius: 14px; padding: 14px 16px; margin-bottom: 10px; color: #461500; font-weight: 700; font-size: 15px; }
    .abshp-cb .lingkar { width: 22px; height: 22px; border-radius: 50%; border: 2px solid #C9B39C; flex: 0 0 auto; }
    .abshp-cb.aktif { border-color: #461500; }
    .abshp-cb.aktif .lingkar { border-color: #461500; background: radial-gradient(#461500 55%, transparent 60%); }
    /* mengirim */
    .abshp-kirim { align-items: center; justify-content: center; color: #fff; text-align: center; background: rgba(27,13,5,.96); }
    .abshp-spin { width: 46px; height: 46px; border: 4px solid rgba(255,255,255,.25); border-top-color: #F1C994; border-radius: 50%; animation: abshp-rot 0.9s linear infinite; margin: 0 auto 16px; }
    @keyframes abshp-rot { to { transform: rotate(360deg); } }
    /* gagal (handoff mobile-9) */
    .abshp-thumb-wrap { position: relative; width: 116px; margin: 0 auto 18px; }
    .abshp-thumb { width: 116px; height: 116px; object-fit: cover; border-radius: 22px; }
    .abshp-thumb-x { position: absolute; right: -8px; bottom: -4px; width: 34px; height: 34px; border-radius: 50%; background: #C0392B; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; border: 3px solid #F4ECE2; font-size: 14px; }
</style>
@endsection

@section('content')
@php
    $masuk  = $buktiHariIni->firstWhere('jenis', 'masuk');
    $pulang = $buktiHariIni->firstWhere('jenis', 'pulang');
    $jenisBerikut = $masuk ? 'pulang' : 'masuk';
    $lengkap = $masuk && $pulang;
@endphp
{{-- Dibuka di komputer → ditutup gerbang sopan (file-picker desktop bisa
     mengunggah foto lama; kamera langsung hanya di HP/tablet). --}}
@include('absensi.akun._gerbang_desktop')
<div class="abshp-wrap">
    <a href="{{ route('akun.absensi') }}" class="abshp-back">‹ Absensi Saya</a>

    {{-- PersonalHeader (pola resmi) --}}
    <div class="absk-head" style="margin-top:10px">
        <div class="absk-idrow">
            <div class="absk-avatar">{{ $me['inisial'] }}</div>
            <div class="absk-idmeta">
                <div class="absk-name">{{ $me['nama'] }}</div>
                <div class="absk-sub">{{ $me['jabatan'] }}@if($me['cabang']) · Cabang {{ $me['cabang'] }}@endif</div>
            </div>
        </div>
    </div>

    {{-- Status hari ini — garis waktu MASUK → PULANG, hanya tampil setelah
         absen masuk (handoff_kehadiran §7). Sebelum masuk: kartu ajakan singkat. --}}
    <div class="abshp-status">
        <div class="k"><span class="dot"></span> STATUS HARI INI</div>
        @if(!$masuk)
            <div class="v">
                Kamu belum absen masuk hari ini
                <small>Selfie di titik absen cabang — mulai dari tombol di bawah.</small>
            </div>
        @else
            @php
                $durasi = 'berjalan';
                if ($pulang) {
                    $mnt = $masuk->taken_at->diffInMinutes($pulang->taken_at);
                    $durasi = str_replace('.', ',', (string) round($mnt / 60, 1)) . ' jam';
                }
            @endphp
            <div class="abshp-tl">
                <div style="flex:1;min-width:0">
                    <div class="kk">MASUK</div>
                    <div class="jj">{{ $masuk->taken_at->format('H:i') }}</div>
                </div>
                <div class="abshp-tl-mid">
                    <div class="abshp-tl-line {{ $pulang ? '' : 'open' }}"></div>
                    <span class="abshp-tl-dur">{{ $durasi }}</span>
                </div>
                <div style="flex:1;min-width:0;text-align:right">
                    <div class="kk">PULANG</div>
                    <div class="jj {{ $pulang ? '' : 'off' }}">{{ $pulang ? $pulang->taken_at->format('H:i') : '—' }}</div>
                </div>
            </div>
            @if($me['cabang'])<div class="abshp-tl-cabang">Cabang {{ $me['cabang'] }}</div>@endif
            @if(!$pulang)<div class="abshp-tl-cabang" style="color:#C9A183">Jangan lupa absen pulang sebelum meninggalkan cabang.</div>@endif
        @endif
    </div>

    {{-- Absen di HARI LIBUR terjadwal → tanyakan dalam rangka apa, SEKARANG,
         selagi orangnya paling tahu alasannya. Kerja di luar jadwal hanya sah
         kalau dideklarasikan (keputusan Ekky 2026-08-05); tanpa itu harinya
         tidak dihitung — tapi absennya TIDAK PERNAH ditolak, dan tak ada yang
         memaksa mengisi sekarang juga.
         $liburHariIni null = tak bisa dijawab (aturan kerja belum diatur, atau
         memang tak dijadwalkan) → jangan bertanya berdasarkan ketidaktahuan. --}}
    @if(($liburHariIni ?? null) === true)
        <div class="abshp-ingat" style="border-color:#C4DCEE;background:#F4F9FD">
            <b>Hari ini jadwalmu libur</b>
            <span>Kalau kamu tetap masuk, catat dulu dalam rangka apa — kalau tidak, hari ini tidak dihitung sebagai hari kerja. Absennya tetap tersimpan.</span>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
                @if($bolehTukar ?? false)
                    <a class="abshp-btn abshp-btn-kedua" style="flex:1;min-width:130px;padding:10px;font-size:14px"
                       href="{{ route('akun.pengajuan', ['tipe' => 'tukar']) }}">Tukar hari</a>
                @endif
                <a class="abshp-btn abshp-btn-kedua" style="flex:1;min-width:130px;padding:10px;font-size:14px"
                   href="{{ route('akun.pengajuan', ['tipe' => 'lembur']) }}">Lembur</a>
            </div>
        </div>
    @endif

    @if($lupaKemarin)
        <div class="abshp-ingat" style="display:flex;align-items:center;gap:10px">
            <div style="flex:1">
                <b>Kamu belum absen pulang kemarin</b>
                <span>Tenang, bisa dibetulkan.</span>
            </div>
            <a href="{{ route('akun.pengajuan') }}">Koreksi Absensi →</a>
        </div>
    @endif

    {{-- Sebelum absen, pastikan --}}
    <div class="abshp-cek">
        <div class="k">SEBELUM ABSEN, PASTIKAN</div>
        <div class="item">
            <span class="ic"><span class="abshp-ic">{!! $svgWifi !!}</span></span>
            <span id="cek-wifi" class="{{ $ipCocok ? 'abshp-wifi-ok' : '' }}">
                @if($ipCocok) Terhubung WiFi <strong>{{ $ipCocok->name }}</strong>
                @else HP terhubung <strong>WiFi cabang</strong> @endif
            </span>
        </div>
        <div class="item">
            <span class="ic oranye"><span class="abshp-ic">{!! $svgPoster !!}</span></span>
            <span>Berdiri di depan <strong>poster titik absen</strong></span>
        </div>
    </div>
</div>

{{-- CTA zona jempol --}}
<div class="abshp-cta">
    @if($lengkap)
        <div class="abshp-selesai-info"><span class="abshp-ic">{!! $svgCheck !!}</span> Absen hari ini sudah lengkap</div>
    @else
        <button id="btn-mulai" class="abshp-btn abshp-btn-utama" data-jenis="{{ $jenisBerikut }}">
            <span class="abshp-ic">{!! $svgCam !!}</span> Absen {{ ucfirst($jenisBerikut) }}
        </button>
    @endif
</div>

{{-- ══ STATE: izin kamera (halaman krem — handoff mobile-2) ══ --}}
<div id="ov-izin" class="abshp-ov abshp-krem abshp-hidden">
    <div class="atas"><button type="button" class="abshp-ov-back" data-tutup="ov-izin">‹</button></div>
    <div class="tengah">
        <div class="abshp-ikon-bulat abshp-ikon-coklat"><span class="abshp-ic">{!! $svgCam !!}</span></div>
        <h5>Absen pakai foto selfie</h5>
        <p>Kehadiranmu tercatat jujur lewat selfie di titik absen. Fotonya cuma dipakai buat absen — sekali izin, seterusnya lancar.</p>
        <button id="btn-izinkan" class="abshp-btn abshp-btn-utama">Lanjutkan</button>
        <p style="font-size:12px;color:#9A7256;margin-top:9px">HP kamu akan menanyakan izin kamera setelah ini.</p>
    </div>
</div>

{{-- ══ STATE: izin ditolak (halaman krem — handoff mobile-3) ══ --}}
<div id="ov-tolak" class="abshp-ov abshp-krem abshp-hidden">
    <div class="atas"><button type="button" class="abshp-ov-back" data-tutup="ov-tolak">‹</button></div>
    <div class="tengah">
        <div class="abshp-ikon-bulat abshp-ikon-merah"><span class="abshp-ic">{!! $svgCamOff !!}</span></div>
        <h5 id="tolak-judul">Kamera belum diizinkan</h5>
        <p id="tolak-teks">Buka setelan izin di browser lalu aktifkan <strong>Kamera</strong> untuk halaman ini.</p>
        <div id="tolak-langkah">
            <div class="abshp-langkah"><span class="no">1</span><span>Ketuk ikon gembok/kamera di kolom alamat browser</span></div>
            <div class="abshp-langkah"><span class="no">2</span><span>Kembali ke sini, ketuk <strong>Coba Lagi</strong></span></div>
        </div>
        <button id="btn-tolak-coba" class="abshp-btn abshp-btn-utama" style="margin-top:8px">Coba Lagi</button>
    </div>
</div>

{{-- ══ STATE: kamera + konfirmasi (gelap — handoff mobile-4/5) ══ --}}
<div id="ov-kamera" class="abshp-ov abshp-gelap abshp-hidden">
    <div class="abshp-kamera-area">
        <video id="video" class="abshp-video" autoplay playsinline muted></video>
        <img id="fotoku" class="abshp-fotoku abshp-hidden" alt="">
        <div id="oval" class="abshp-oval">
            <div class="o"></div>
            <div class="t">Poster titik absen di belakangmu · muka kelihatan jelas</div>
        </div>
    </div>
    <span id="chip-siap" class="abshp-chip-siap abshp-hidden"><span class="cek abshp-ic">{!! $svgCheck !!}</span> Foto siap dikirim</span>
    <div class="abshp-ov-top">
        <button id="btn-kembali" class="abshp-ov-back">‹</button>
        <span class="abshp-chip {{ $ipCocok ? 'abshp-chip-ok' : 'abshp-chip-tanya' }}">
            <span class="abshp-ic">{!! $svgWifi !!}</span>
            {{ $ipCocok ? ('Sweet Treats ' . $ipCocok->name) : 'WiFi belum dikenali' }}
        </span>
    </div>
    <div class="abshp-ov-bawah">
        <button id="btn-rana" class="abshp-rana" title="Ambil foto"></button>
        <div id="aksi-konfirm" class="abshp-hidden" style="display:flex;gap:10px;width:100%">
            <button id="btn-ulang" class="abshp-btn abshp-btn-kedua" style="flex:1">Ulangi</button>
            <button id="btn-kirim" class="abshp-btn abshp-btn-utama" style="flex:2">Kirim Absen</button>
        </div>
    </div>
</div>

{{-- ══ STATE: pilih cabang (halaman krem — handoff mobile-8) ══ --}}
<div id="ov-cabang" class="abshp-ov abshp-krem abshp-hidden">
    <div class="atas" style="display:flex;align-items:center;gap:12px">
        <button type="button" class="abshp-ov-back" data-tutup="ov-cabang">‹</button>
        <b style="color:#461500;font-size:17px">Pilih cabang</b>
    </div>
    <div style="width:100%;max-width:340px;align-self:center;margin-top:14px">
        <div class="abshp-info-biru">
            <span class="ic abshp-ic">{!! $svgWifi !!}</span>
            <div>
                <b>WiFi tidak dikenali</b>
                <span>Bukan masalah — pilih cabang tempatmu absen, lalu kirim. Nanti dicek supervisor.</span>
            </div>
        </div>
        @foreach($branches as $b)
            <button type="button" class="abshp-cb" data-id="{{ $b->id }}">
                <span class="lingkar"></span> Sweet Treats {{ $b->name }}
            </button>
        @endforeach
        <button id="btn-cabang-lanjut" class="abshp-btn abshp-btn-utama" disabled style="margin-top:6px">Kirim Absen</button>
    </div>
</div>

{{-- ══ STATE: mengirim (gelap — handoff mobile-6) ══ --}}
<div id="ov-kirim" class="abshp-ov abshp-kirim abshp-hidden">
    <div>
        <div class="abshp-spin"></div>
        <b>Mengirim absen…</b>
        <div style="color:#E6C09B;font-size:13px;margin-top:6px">Foto kecil, sebentar saja</div>
    </div>
</div>

{{-- ══ STATE: gagal kirim (halaman krem — handoff mobile-9) ══ --}}
<div id="ov-gagal" class="abshp-ov abshp-krem abshp-hidden">
    <div class="tengah">
        <div class="abshp-thumb-wrap">
            <img id="gagal-thumb" class="abshp-thumb" src="" alt="">
            <span class="abshp-thumb-x">✕</span>
        </div>
        <h5>Gagal mengirim</h5>
        <p id="gagal-pesan">Koneksi terputus sebentar. Fotomu aman, tinggal kirim ulang.</p>
        <button id="btn-gagal-ulang" class="abshp-btn abshp-btn-utama">Coba lagi</button>
        <a href="{{ route('akun.pengajuan') }}" class="abshp-tautan">Simpan &amp; lapor lewat Koreksi Absensi</a>
    </div>
</div>

{{-- ══ STATE: sukses (coklat penuh — handoff mobile-7) ══ --}}
<div id="ov-sukses" class="abshp-ov abshp-sukses abshp-hidden">
    <div class="cek"><span class="abshp-ic">{!! $svgCheck !!}</span></div>
    <div id="s-salam" class="salam"></div>
    <div id="s-judul" class="judul"></div>
    <div id="s-jam" class="jam"></div>
    <div id="s-cabang" class="pil abshp-hidden"><span class="abshp-ic">{!! $svgPin !!}</span> <span></span></div>
    <div id="s-catatan" class="catatan abshp-hidden">WiFi belum dikenali — absen tetap tercatat dan akan dicek supervisor. Santai saja.</div>
    <button id="btn-selesai" class="abshp-btn abshp-btn-kedua" style="max-width:260px;margin-top:26px">Selesai</button>
    <a href="{{ route('akun.absensi') }}" class="abshp-tautan">Lihat Absensi Saya</a>
</div>
@endsection

@section('js')
<script>
(function () {
    var jenis = @json($jenisBerikut);
    var ipDikenal = @json((bool) $ipCocok);
    var URL_STORE = @json(route('akun.absen.store'));
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;

    var stream = null, dataUrl = null, cabangManual = null, kirimMulai = 0;
    var $ = function (id) { return document.getElementById(id); };
    function lihat(id, ya) { $(id).classList.toggle('abshp-hidden', !ya); }

    document.querySelectorAll('[data-tutup]').forEach(function (b) {
        b.addEventListener('click', function () { lihat(b.dataset.tutup, false); });
    });

    var btnMulai = $('btn-mulai');
    if (btnMulai) btnMulai.addEventListener('click', function () {
        if (localStorage.getItem('abshp_izin_ok')) { bukaKamera(); }
        else { lihat('ov-izin', true); }
    });
    $('btn-izinkan').addEventListener('click', function () { lihat('ov-izin', false); bukaKamera(); });
    $('btn-tolak-coba').addEventListener('click', function () { lihat('ov-tolak', false); bukaKamera(); });

    // Layar "kamera tak bisa dibuka" — dua sebab yang beda obatnya:
    //   insecure = halaman dibuka lewat http:// (mis. dites dari HP ke IP
    //              komputer). Browser TIDAK menyediakan kamera sama sekali;
    //              menyuruh "izinkan di setelan" cuma bikin bingung.
    //   selain itu = izin ditolak / kamera dipakai app lain → bisa Coba Lagi.
    function tolakKamera(insecure) {
        lihat('ov-kamera', false);
        lihat('ov-tolak', true);
        localStorage.removeItem('abshp_izin_ok');
        if (insecure) {
            $('tolak-judul').textContent = 'Kamera tidak bisa dibuka dari alamat ini';
            $('tolak-teks').innerHTML = 'Browser hanya mengizinkan kamera di alamat aman (<strong>https://</strong>). Buka halaman ini dari alamat resmi Sweet Treats, bukan lewat alamat IP.';
            $('tolak-langkah').hidden = true;
            $('btn-tolak-coba').hidden = true;
        } else {
            $('tolak-judul').textContent = 'Kamera belum diizinkan';
            $('tolak-teks').innerHTML = 'Buka setelan izin di browser lalu aktifkan <strong>Kamera</strong> untuk halaman ini.';
            $('tolak-langkah').hidden = false;
            $('btn-tolak-coba').hidden = false;
        }
    }

    function bukaKamera() {
        // navigator.mediaDevices TIDAK ADA di halaman non-secure — kalau langsung
        // dipanggil, dia melempar TypeError sinkron yang tak tertangkap .catch()
        // dan layar kamera nyangkut hitam tanpa pesan apa pun.
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            tolakKamera(true);
            return;
        }
        lihat('ov-kamera', true);
        resetKamera();
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 720 } }, audio: false })
            .then(function (s) {
                stream = s; $('video').srcObject = s;
                localStorage.setItem('abshp_izin_ok', '1');
            })
            .catch(function () { tolakKamera(false); });
    }

    function matikanKamera() {
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    }

    function resetKamera() {
        dataUrl = null;
        lihat('fotoku', false); lihat('aksi-konfirm', false); lihat('chip-siap', false);
        $('video').classList.remove('abshp-hidden');
        $('btn-rana').classList.remove('abshp-hidden');
        $('oval').classList.remove('abshp-hidden');
    }

    $('btn-kembali').addEventListener('click', function () { matikanKamera(); lihat('ov-kamera', false); });

    $('btn-rana').addEventListener('click', function () {
        var v = $('video');
        if (!v.videoWidth) return;
        var c = document.createElement('canvas');
        var skala = Math.min(1, 640 / Math.max(v.videoWidth, v.videoHeight));
        c.width = Math.round(v.videoWidth * skala);
        c.height = Math.round(v.videoHeight * skala);
        c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
        dataUrl = c.toDataURL('image/jpeg', 0.72);

        $('fotoku').src = dataUrl;
        lihat('fotoku', true); lihat('chip-siap', true);
        v.classList.add('abshp-hidden');
        $('btn-rana').classList.add('abshp-hidden');
        $('oval').classList.add('abshp-hidden');
        lihat('aksi-konfirm', true);
    });

    $('btn-ulang').addEventListener('click', resetKamera);

    document.querySelectorAll('.abshp-cb').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.abshp-cb').forEach(function (x) { x.classList.remove('aktif'); });
            b.classList.add('aktif');
            cabangManual = b.dataset.id;
            $('btn-cabang-lanjut').disabled = false;
        });
    });
    $('btn-cabang-lanjut').addEventListener('click', function () { lihat('ov-cabang', false); kirim(); });

    $('btn-kirim').addEventListener('click', function () {
        if (!ipDikenal && !cabangManual) { lihat('ov-cabang', true); return; }
        kirim();
    });
    $('btn-gagal-ulang').addEventListener('click', function () { lihat('ov-gagal', false); kirim(); });

    function kirim() {
        if (!dataUrl) return;
        kirimMulai = Date.now();
        lihat('ov-kirim', true);

        var body = new URLSearchParams();
        body.append('_token', CSRF);
        body.append('jenis', jenis);
        body.append('foto_base64', dataUrl);
        if (cabangManual) body.append('branch_manual', cabangManual);

        // Accept:json WAJIB — tanpanya gagal validasi/CSRF dibalas redirect HTML
        // → r.json() meledak → pesan "koneksi bermasalah" yang bohong.
        fetch(URL_STORE, { method: 'POST', body: body, headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (r.status === 419) {
                    return { st: 419, j: { pesan: 'Sesi login sudah kadaluarsa. Muat ulang halaman ini dulu, lalu absen lagi.' } };
                }
                return r.json().then(function (j) { return { st: r.status, j: j }; });
            })
            .then(function (res) {
                // tahan "Mengirim…" minimal 600ms biar tak sekadar berkedip
                // (di koneksi cepat upload <200ms) — state-nya jadi terasa.
                var sisa = Math.max(0, 600 - (Date.now() - kirimMulai));
                setTimeout(function () {
                    lihat('ov-kirim', false);
                    if (res.j.ok) { sukses(res.j); }
                    else if (res.j.need_branch) { lihat('ov-cabang', true); }
                    else if (res.j.errors) { gagal('Data absen tidak lengkap/valid. Coba ulangi selfienya.'); }
                    else { gagal(res.j.pesan); }
                }, sisa);
            })
            .catch(function () {
                lihat('ov-kirim', false);
                gagal(null);
            });
    }

    function gagal(pesan) {
        $('gagal-thumb').src = dataUrl || '';
        $('gagal-pesan').textContent = pesan || 'Koneksi terputus sebentar. Fotomu aman, tinggal kirim ulang.';
        lihat('ov-gagal', true);
    }

    function sukses(j) {
        matikanKamera(); lihat('ov-kamera', false);
        var jamAngka = parseInt(j.jam.split('.')[0], 10);
        var salam = jamAngka < 11 ? 'Selamat pagi' : jamAngka < 15 ? 'Selamat siang' : jamAngka < 19 ? 'Selamat sore' : 'Selamat malam';
        $('s-salam').textContent = salam + ', ' + @json(explode(' ', trim($me['nama']))[0] ?? '') + '!';
        $('s-judul').textContent = 'Absen ' + j.jenis + ' tercatat';
        $('s-jam').textContent = j.jam;
        if (j.cabang) { $('s-cabang').querySelector('span').textContent = 'Sweet Treats ' + j.cabang; lihat('s-cabang', true); }
        lihat('s-catatan', !j.cocok);
        lihat('ov-sukses', true);
    }

    $('btn-selesai').addEventListener('click', function () { window.location.reload(); });
})();
</script>
@endsection
