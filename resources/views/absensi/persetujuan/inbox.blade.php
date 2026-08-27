@extends('layouts.app')

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/absensi.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/absensi.sweettreats.css')) ?: '1' }}">
<style>
    /* Peringatan "uang bisa salah bayar" — sengaja DI SINI, bukan di berkas CSS
       bersama: public_html produksi itu SALINAN, bukan symlink, jadi tiap
       sentuhan pada public/assets menuntut rsync tersendiri saat deploy.
       Sepotong gaya sekecil ini tak sepadan dengan risiko lupa. */
    .appr-flag { display: flex; gap: 8px; align-items: flex-start; border-radius: 11px;
                 padding: 9px 11px; margin: 8px 0 0; font-size: 12px; font-weight: 600; line-height: 1.45; }
    .appr-flag svg { flex: none; margin-top: 1px; }
    .appr-flag b { font-weight: 800; }
    .appr-flag .appr-flag-sub { display: block; font-weight: 500; margin-top: 2px; opacity: .92; }
    /* Merah = jam yang sama diklaim dua kali; amber = jenis lembur kemungkinan
       salah. Dua warna karena akibatnya beda: yang satu dibayar dobel, yang
       satu dibayar tarif keliru. */
    .appr-flag.dup  { background: #FBE4DF; color: #9B3016; }
    .appr-flag.chip { background: #FBEFD9; color: #96601A; }
</style>
@endsection

@php
    $apName   = session('name') ?? 'Approver';
    $apRole   = session('group_name') ?? 'Approver';
    $apBranch = session('raw_branch_id') ? optional(\App\Models\RawBranch::find(session('raw_branch_id')))->name : null;
    $apInit   = strtoupper(mb_substr($apName, 0, 1));
    $verifLabel = ['nyambung' => 'Nyambung', 'selisih' => 'Perlu dicek', 'tidak_bisa_dicek' => 'Tak bisa dicek'];
    $jamOf = fn ($m) => rtrim(rtrim(number_format($m / 60, 1, ',', ''), '0'), ',');
@endphp

@section('content')
<div class="absk-wrap appr-wrap">
    @include('layouts.notif')

    {{-- PersonalHeader + tab --}}
    <div class="absk-head">
        <div class="absk-idrow">
            <div class="absk-avatar">{{ $apInit }}</div>
            <div class="absk-idmeta">
                <div class="absk-name">{{ $apName }}</div>
                <div class="absk-sub">{{ $apRole }}@if($apBranch) · Cabang {{ $apBranch }}@endif</div>
            </div>
            <a href="#" class="absk-bell" title="Notifikasi"><i class="fa fa-bell"></i></a>
        </div>
        <div class="appr-title">Inbox Persetujuan</div>
        <div class="absk-seg appr-tabs">
            <button type="button" class="active" data-aview="menunggu">Menunggu @if($pending->count())<span class="appr-tabbadge">{{ $pending->count() }}</span>@endif</button>
            <button type="button" data-aview="riwayat">Riwayat</button>
        </div>
    </div>

    {{-- ══ Menunggu ══ --}}
    <div class="absk-view" data-view="menunggu">
        @if($pending->isEmpty())
            <div class="absk-empty">Tidak ada pengajuan yang menunggu persetujuanmu. <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="vertical-align:-.15em"><path d="M8 1.8 9.3 5.6 13 7 9.3 8.4 8 12.2 6.7 8.4 3 7l3.7-1.4L8 1.8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M12.8 11.4v2.8M11.4 12.8h2.8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div>
        @else
            <div class="appr-grid">
                @foreach($pending as $c)
                    @php $r = $c->r; $needCheck = ($c->sanding['verifikasi'] ?? null) === 'selisih'; @endphp
                    <div class="appr-card @if($needCheck) is-check @endif">
                        <div class="appr-card-top">
                            <div class="appr-who">
                                <div class="appr-name">{{ $r->user->nama ?? 'Pengaju' }}</div>
                                <div class="appr-branch">{{ optional($r->branch)->name ?? '—' }}</div>
                            </div>
                            <span class="absk-type">{{ $tipeLabel[$r->tipe] ?? $r->tipe }}</span>
                        </div>

                        <div class="appr-meta">
                            <span class="appr-aging">⏳ {{ $c->aging > 0 ? 'Menunggu '.$c->aging.' hari' : 'Baru masuk' }}</span>
                            @if($c->is_final)
                                <span class="appr-chain final">✓ Keputusan final di tangan Anda</span>
                            @else
                                <span class="appr-chain"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" style="vertical-align:-.1em"><path d="M6 10.5H4a2.5 2.5 0 0 1 0-5h2M10 5.5h2a2.5 2.5 0 0 1 0 5h-2M5.6 8h4.8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> Lapis {{ $c->level }}/{{ $c->total }}@if($c->next_names) · lalu ke {{ $c->next_names }}@endif</span>
                            @endif
                        </div>

                        <div class="appr-chips">
                            <span class="appr-chip num">{{ \Illuminate\Support\Carbon::parse($r->tanggal_from)->translatedFormat('d M Y') }}@if($r->tanggal_to != $r->tanggal_from) – {{ \Illuminate\Support\Carbon::parse($r->tanggal_to)->translatedFormat('d M') }}@endif</span>
                            @if($r->jam_from)<span class="appr-chip num">{{ substr($r->jam_from,0,5) }}@if($r->jam_to) – {{ substr($r->jam_to,0,5) }}@endif</span>@endif
                            @if($r->foto)<a href="{{ route('akun.pengajuan.berkas', $r->id) }}" target="_blank" class="appr-photo" title="{{ $r->tipe === 'sakit' ? 'Lihat surat dokter' : 'Lihat foto bukti' }}"><i class="fa fa-image"></i></a>@endif
                        </div>

                        @if($r->alasan)<div class="appr-reason">"{{ $r->alasan }}"</div>@endif

                        @if($c->sanding && $c->sanding['klaim_menit'])
                            @php
                                $s = $c->sanding;
                                $pct = $s['klaim_menit'] ? min(100, round($s['supported_menit'] / $s['klaim_menit'] * 100)) : 0;
                                $selisih = ($s['klaim_menit'] - $s['supported_menit']) / 60;
                            @endphp
                            <div class="appr-sanding @if($needCheck) warn @endif">
                                <div class="appr-sanding-top">
                                    <span class="appr-sanding-verif">{{ $needCheck ? '● ' : '' }}{{ $verifLabel[$s['verifikasi']] ?? 'Sanding scan' }}</span>
                                    @if($needCheck && $selisih != 0)<span class="appr-sanding-sel">selisih {{ $selisih > 0 ? '+' : '' }}{{ $jamOf($s['klaim_menit'] - $s['supported_menit']) }} jam</span>@endif
                                </div>
                                <div class="appr-sanding-vals">
                                    <span>Klaim <b class="num">{{ $jamOf($s['klaim_menit']) }} jam</b></span>
                                    <span>Didukung scan <b class="num">{{ $jamOf($s['supported_menit']) }} jam</b></span>
                                </div>
                                <div class="appr-bar"><div class="appr-bar-fill" style="width:{{ $pct }}%"></div></div>
                            </div>
                        @endif

                        {{-- Ditaruh TEPAT DI ATAS tombol Setujui, bukan di pojok
                             kartu: yang mau dicegah adalah keputusan, bukan
                             ketidaktahuan. Sistem cuma memberi tahu — tak ada
                             yang dikunci, tak ada yang digabung otomatis; dua
                             sesi lembur terpisah di hari yang sama itu sah. --}}
                        @if($c->kembar ?? null)
                            <div class="appr-flag dup">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 8v5M12 16.5v.1M10.3 3.9 2.6 17.4A2 2 0 0 0 4.3 20.4h15.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                                <div>
                                    <b>Jam ini bertindih dengan pengajuan lain di tanggal yang sama</b> — {{ $c->kembar }}.
                                    <span class="appr-flag-sub">Kalau ini kiriman ulang yang sama, setujui satu saja: menit yang di-ACC dijumlahkan, jadi dua-duanya = dibayar dobel. Kalau memang dua sesi terpisah, lanjutkan.</span>
                                </div>
                            </div>
                        @endif

                        @if($c->chip ?? null)
                            <div class="appr-flag chip">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 8.5v5M12 16.6v.1"/><circle cx="12" cy="12" r="9"/></svg>
                                <div>
                                    <b>Jenis lembur perlu dicek</b> — {{ $c->chip }}
                                    <span class="appr-flag-sub">Tarifnya beda antara lembur biasa dan masuk hari libur. Anda yang tahu duduk perkaranya; kalau jenisnya keliru, minta pengaju membetulkan sebelum di-ACC.</span>
                                </div>
                            </div>
                        @endif

                        <form action="{{ route('persetujuan.act', $r->id) }}" method="POST" class="appr-form">
                            @csrf
                            <input type="text" name="catatan" class="appr-note" placeholder="Catatan (opsional)…" maxlength="255">
                            <div class="appr-actions">
                                <button type="submit" name="action" value="approve" class="appr-approve"><i class="fa fa-check"></i> Setujui</button>
                                <button type="submit" name="action" value="reject" class="appr-reject">Tolak</button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ══ Riwayat ══ --}}
    <div class="absk-view" data-view="riwayat" hidden>
        @if($recent->isEmpty())
            <div class="absk-empty">Belum ada pengajuan yang kamu tindak.</div>
        @else
            <div class="appr-grid">
                @foreach($recent as $r)
                    <div class="appr-card done">
                        <div class="appr-card-top">
                            <div class="appr-who">
                                <div class="appr-name">{{ $r->user->nama ?? 'Pengaju' }}</div>
                                <div class="appr-branch">{{ optional($r->branch)->name ?? '—' }} · {{ \Illuminate\Support\Carbon::parse($r->tanggal_from)->translatedFormat('d M Y') }}</div>
                            </div>
                            <span class="absk-type">{{ $tipeLabel[$r->tipe] ?? $r->tipe }}</span>
                        </div>
                        <div class="appr-done-row">
                            @if($r->alasan)<span class="appr-reason-sm">"{{ \Illuminate\Support\Str::limit($r->alasan, 60) }}"</span>@endif
                            <span class="absk-pill s-{{ $r->status === 'approved' ? 'hadir' : 'alpa' }}">{{ $r->status === 'approved' ? 'Disetujui' : 'Ditolak' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.absk-seg [data-aview]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var view = btn.getAttribute('data-aview');
            document.querySelectorAll('.absk-seg [data-aview]').forEach(function (b) { b.classList.toggle('active', b === btn); });
            document.querySelectorAll('.absk-view').forEach(function (v) { v.hidden = (v.getAttribute('data-view') !== view); });
        });
    });
})();
</script>
@endsection
