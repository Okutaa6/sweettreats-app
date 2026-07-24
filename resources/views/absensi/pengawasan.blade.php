@extends('layouts.app')

{{-- Pengawasan Absen — layar review SPV (handoff_absen_hp/Pengawasan Absen).
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
    .aws-kanan { flex: 0 0 480px; width: 480px; background: #fff; border-left: 1px solid #eadfd6; padding: 0; position: sticky; top: 0; align-self: flex-start; max-height: 100vh; overflow-y: auto; }
    @media (max-width: 1280px) { .aws-kanan { flex-basis: 400px; width: 400px; } }
    .aws-judul { color: #461500; font-weight: 800; font-size: 22px; margin: 0 0 2px; }
    .aws-sub { color: #8a7263; font-size: 13px; margin-bottom: 14px; }
    .aws-banner { display: flex; gap: 14px; align-items: center; background: #fdecec; border: 1px solid #f5c9c9; border-radius: 14px; padding: 14px 16px; margin-bottom: 16px; }
    .aws-banner.aman { background: #e8f5e9; border-color: #bfe3c2; }
    .aws-banner h6 { color: #b03030; font-weight: 800; margin: 0 0 3px; }
    .aws-banner.aman h6 { color: #1b5e20; }
    .aws-banner p { color: #8a6060; font-size: 12.5px; margin: 0; }
    .aws-banner .angka { text-align: center; min-width: 46px; }
    .aws-banner .angka b { display: block; font-size: 20px; color: #b03030; }
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
    .aws-card { background: #fff; border: 1px solid #eadfd6; border-radius: 14px; padding: 14px 16px; margin-bottom: 12px; }
    .aws-cabang-judul { display: flex; align-items: center; gap: 8px; font-weight: 800; color: #461500; margin-bottom: 10px; }
    .aws-strip { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 6px; }
    /* tile strip — ukuran ikut desain (±130px), bukan 86px */
    .aws-tile { flex: 0 0 auto; width: 130px; cursor: pointer; text-align: left; border: 0; background: none; padding: 0; position: relative; }
    .aws-tile-warn { position: absolute; top: 6px; right: 6px; z-index: 2; width: 24px; height: 24px; border-radius: 50%; background: #d9534f; color: #fff; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; }
    .aws-tile img { width: 130px; height: 150px; object-fit: cover; border-radius: 12px; border: 3px solid transparent; background: #2e1a10; }
    .aws-tile.flagged img { border-color: #d9534f; }
    .aws-tile .nm { font-size: 13px; color: #3B1500; font-weight: 700; margin-top: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .aws-tile .jm { font-size: 12px; color: #8a7263; }
    .aws-chipst { display: inline-flex; gap: 6px; margin-bottom: 12px; }
    .aws-chip { border: 1px solid #e0d2c6; background: #fff; color: #8a7263; border-radius: 99px; padding: 5px 13px; font-size: 12.5px; font-weight: 700; text-decoration: none; }
    .aws-chip.aktif { background: #461500; border-color: #461500; color: #fff; }
    .aws-flag { display: flex; align-items: center; gap: 12px; width: 100%; text-align: left; background: #fff; border: 1px solid #eadfd6; border-radius: 14px; padding: 12px 14px; margin-bottom: 10px; cursor: pointer; }
    .aws-flag:hover { border-color: #c9a888; }
    .aws-flag .ikn { width: 38px; height: 38px; border-radius: 10px; background: #fdf3e7; display: flex; align-items: center; justify-content: center; color: #b06a00; flex: 0 0 auto; }
    .aws-flag .isi { min-width: 0; flex: 1; }
    .aws-flag .isi b { color: #461500; font-size: 14px; }
    .aws-flag .isi small { color: #8a7263; display: block; }
    .aws-st { font-size: 11px; font-weight: 800; border-radius: 99px; padding: 3px 10px; flex: 0 0 auto; }
    .aws-st-baru { background: #fdecec; color: #b03030; }
    .aws-st-dicek { background: #fff3d6; color: #9a6b00; }
    .aws-st-selesai { background: #e8f5e9; color: #1b5e20; }
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
    .aws-p-jenis { position: absolute; top: 12px; left: 12px; background: #fff; color: #b03030; font-weight: 800; font-size: 12.5px; border-radius: 99px; padding: 5px 12px; }
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
    .aws-sinyal.merah .tx { color: #b03030; }
    .aws-sinyal.warn { border-color: #f0dca8; background: #fffaf0; }
    .aws-sinyal.warn .ic { background: #fdf0d6; color: #9a6b00; }
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
            <div style="flex:0 0 auto;width:46px;height:46px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;color:{{ $openTotal === 0 ? '#1b5e20' : '#b03030' }}">
                @if($openTotal === 0)
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 7.7-1.5"/></svg>
                @else
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>
                @endif
            </div>
            <div style="flex:1">
                @if($openTotal > 0)
                    <h6>{{ $openTotal }} flag masih terbuka</h6>
                    <p>Periode gaji <strong>tidak bisa ditutup</strong> selama masih ada flag yang belum diselesaikan.
                        Selesaikan semua untuk membuka penutupan periode.</p>
                @else
                    <h6>Semua flag beres</h6>
                    <p>Tidak ada kejanggalan menunggu — periode gaji bisa ditutup.</p>
                @endif
            </div>
            <div class="angka"><b>{{ $nBaru }}</b><span>BARU</span></div>
            <div class="angka"><b style="color:#9a6b00">{{ $nDicek }}</b><span>DICEK</span></div>
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
                Strip Foto Harian
            </button>
            <button type="button" class="aws-tab" data-tab="flag">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="vertical-align:-.12em"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                Antrian Flag
                @if($totalFlagTanggal > 0)<span class="bdg">{{ $totalFlagTanggal }}</span>@endif
            </button>
        </div>
        </div>

        {{-- ── TAB STRIP ── --}}
        <div id="tab-strip">
            <p style="color:#8a7263;font-size:13px">Semua selfie hari ini dijejer per cabang. Karena latar poster seragam,
                foto yang <strong>beda sendiri</strong> langsung terlihat — klik untuk memeriksa.</p>
            @forelse($stripPerCabang as $cabang => $items)
                <div class="aws-card">
                    <div class="aws-cabang-judul"><span style="width:9px;height:9px;border-radius:3px;background:#2e8e7a;display:inline-block"></span>
                        Sweet Treats {{ $cabang }}
                        <span style="font-weight:600;color:#8a7263;font-size:12.5px">{{ $items->count() }} absen</span>
                        @php $nFlag = $items->filter(fn($b) => isset($userFlagged[$b->user_id]))->count(); @endphp
                        @if($nFlag > 0)<span style="color:#b03030;font-size:12.5px;font-weight:700">● {{ $nFlag }} perlu dicek</span>@endif
                    </div>
                    <div class="aws-strip">
                        @foreach($items as $b)
                            <button type="button" class="aws-tile {{ isset($userFlagged[$b->user_id]) ? 'flagged' : '' }}"
                                    onclick="awsBukaBukti({{ $b->id }})">
                                @if(isset($userFlagged[$b->user_id]))
                                    <span class="aws-tile-warn"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4L2.5 20h19L12 4z"/><path d="M12 10.5v4M12 17.2v.1"/></svg></span>
                                @endif
                                <img src="{{ route('pengawasan.foto', $b->id) }}" alt="" loading="lazy">
                                <div class="nm">{{ optional($b->user)->nama }}</div>
                                <div class="jm">{{ $b->taken_at->format('H.i') }} · {{ $b->jenis }}</div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="aws-card" style="color:#8a7263">Belum ada absen HP di tanggal ini.</div>
            @endforelse
        </div>

        {{-- ── TAB ANTRIAN ── --}}
        <div id="tab-flag" class="aws-hidden">
            <div class="aws-chipst" style="align-items:center">
                @foreach(['' => 'Semua', 'baru' => 'Baru', 'dicek' => 'Dicek', 'selesai' => 'Selesai'] as $val => $lbl)
                    <a class="aws-chip {{ ($statusFilter ?? '') === $val ? 'aktif' : '' }}"
                       href="{{ request()->fullUrlWithQuery(['status' => $val ?: null]) }}">{{ $lbl }}</a>
                @endforeach
                <span style="color:#8a7263;font-size:12.5px;margin-left:6px">Menampilkan <b>{{ $totalFlagTanggal }}</b> flag</span>
            </div>

            {{-- grup serentak --}}
            @foreach($grup as $key => $items)
                @php $cabangGrup = $branchName->get($key, 'Tak diketahui'); @endphp
                <div class="aws-grup">
                    <div style="display:flex;align-items:center;gap:8px">
                        <b style="color:#461500;flex:1">{{ count($items) }} absen di luar WiFi {{ $cabangGrup }}</b>
                        <span style="background:#f3e6d8;color:#9a6b00;font-size:10.5px;font-weight:800;border-radius:99px;padding:3px 10px;letter-spacing:.04em">GRUP · {{ count($items) }} ORANG</span>
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
                        <input type="text" name="catatan" class="aws-catatan" style="flex:1;min-width:180px" placeholder="Catatan (mis. WiFi gangguan)">
                        <button type="submit" class="aws-btn">Selesaikan {{ count($items) }} sekaligus (wajar)</button>
                    </form>
                </div>
            @endforeach

            {{-- flag tunggal --}}
            @forelse($flagTunggal as $f)
                <button type="button" class="aws-flag" onclick="awsBukaFlag({{ $f->id }})">
                    <div class="ikn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 21V4M6 4h11l-2.2 3.5L17 11H6"/></svg></div>
                    <div class="isi">
                        <b>{{ optional($f->user)->nama }}</b>
                        <small>{{ $labelJenis[$f->jenis] ?? $f->jenis }}</small>
                    </div>
                    <span class="aws-st aws-st-{{ $f->status }}">{{ ucfirst($f->status) }}</span>
                </button>
            @empty
                @if(empty($grup))
                    <div class="aws-card" style="color:#8a7263">Tidak ada flag di tanggal ini{{ $statusFilter ? ' dengan status itu' : '' }}.</div>
                @endif
            @endforelse
        </div>
    </div>

    {{-- ═════════ KANAN: panel detail ═════════ --}}
    <div class="aws-kanan">
        <div id="aws-p-kosong" class="aws-p-kosong">
            <div style="width:88px;height:88px;border-radius:24px;background:#F6ECDF;color:#B99A7C;display:flex;align-items:center;justify-content:center;margin:0 auto 14px"><svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5A1.5 1.5 0 0 1 4.5 7H7l1.4-2.2a1 1 0 0 1 .8-.5h5.6a1 1 0 0 1 .8.5L17 7h2.5A1.5 1.5 0 0 1 21 8.5V18a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18z"/><circle cx="12" cy="13" r="3.3"/></svg></div>
            <b style="color:#461500">Pilih foto atau flag</b>
            <div style="font-size:13px">Klik selfie di strip harian atau item di antrian flag untuk memeriksa.</div>
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
                <div class="aws-h6" style="margin-top:0">Sinyal sistem</div>
                <div id="aws-p-sinyal"></div>

                <div id="aws-p-riwayat-wrap">
                    <div class="aws-h6">Riwayat singkat</div>
                    <div class="aws-stats">
                        <div class="aws-stat"><b id="aws-p-hf">0</b><span>flag bulan ini</span></div>
                        <div class="aws-stat"><b id="aws-p-hp">0</b><span>lupa pulang</span></div>
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
                        <span><span class="lbl">Wajar</span><span class="desc">Tidak ada masalah, absen valid</span></span>
                    </div>
                    <div class="aws-res aws-res-perlu_koreksi" data-hasil="perlu_koreksi">
                        <span class="rad"></span>
                        <span><span class="lbl">Perlu Koreksi</span><span class="desc">Jam / data perlu dibetulkan</span></span>
                    </div>
                    <div class="aws-res aws-res-pelanggaran" data-hasil="pelanggaran">
                        <span class="rad"></span>
                        <span><span class="lbl">Pelanggaran</span><span class="desc">Melanggar aturan kehadiran</span></span>
                    </div>
                    <div class="aws-h6">Catatan singkat</div>
                    <textarea name="catatan" class="aws-catatan" rows="2" placeholder="Contoh: WiFi cabang sempat mati, sudah dikonfirmasi ke FO."></textarea>
                    <div class="aws-p-footer">
                        <button type="submit" id="aws-p-simpan" class="aws-btn" style="width:100%" disabled>Pilih penyelesaian dulu</button>
                    </div>
                </form>
                <div id="aws-p-sudah" class="aws-hidden" style="margin-top:14px"></div>
            </div>
        </div>
    </div>
</div>
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
    ])->toJson() !!};

    var AWS_RIWAYAT = {!! $riwayat->toJson() !!};
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
            btn.disabled = false; btn.textContent = 'Selesaikan flag';
        });
    });

    function awsCari(arr, id) { return arr.filter(function (x) { return x.id === id; })[0] || null; }

    function awsIsiPanel(bukti, flag) {
        document.getElementById('aws-p-kosong').classList.add('aws-hidden');
        document.getElementById('aws-p-isi').classList.remove('aws-hidden');

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

    function awsBukaBukti(id) {
        var b = awsCari(AWS_BUKTI, id);
        var f = AWS_FLAGS.filter(function (x) { return x.bukti_id === id; })[0] || null;
        awsIsiPanel(b, f);
    }

    function awsBukaFlag(id) {
        var f = awsCari(AWS_FLAGS, id);
        var b = f && f.bukti_id ? awsCari(AWS_BUKTI, f.bukti_id) : null;
        awsIsiPanel(b, f);
    }
</script>
@endsection
