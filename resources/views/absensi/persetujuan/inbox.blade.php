@extends('layouts.app')

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/absensi.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/absensi.sweettreats.css')) ?: '1' }}">
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
            <div class="absk-empty">Tidak ada pengajuan yang menunggu persetujuanmu. 🎉</div>
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
                                <span class="appr-chain">🔗 Lapis {{ $c->level }}/{{ $c->total }}@if($c->next_names) · lalu ke {{ $c->next_names }}@endif</span>
                            @endif
                        </div>

                        <div class="appr-chips">
                            <span class="appr-chip num">{{ \Illuminate\Support\Carbon::parse($r->tanggal_from)->translatedFormat('d M Y') }}@if($r->tanggal_to != $r->tanggal_from) – {{ \Illuminate\Support\Carbon::parse($r->tanggal_to)->translatedFormat('d M') }}@endif</span>
                            @if($r->jam_from)<span class="appr-chip num">{{ substr($r->jam_from,0,5) }}@if($r->jam_to) – {{ substr($r->jam_to,0,5) }}@endif</span>@endif
                            @if($r->foto)<a href="{{ asset('uploads/att_request/'.$r->foto) }}" target="_blank" class="appr-photo" title="Lihat foto bukti"><i class="fa fa-image"></i></a>@endif
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
