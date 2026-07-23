@php
    $tipeMap = ['cuti'=>'Cuti','izin'=>'Izin','libur'=>'Libur / Tukar Hari','lembur'=>'Lembur','wfh'=>'Kerja dari Rumah'];
    $stPill  = ['approved'=>'hadir','rejected'=>'alpa','cancelled'=>'alpa','pending_l1'=>'telat','pending_l2'=>'telat','draft'=>'libur'];
    $stText  = ['approved'=>'Disetujui','rejected'=>'Ditolak','cancelled'=>'Dibatalkan','pending_l1'=>'Menunggu','pending_l2'=>'Menunggu','draft'=>'Draf'];
    $stLbl   = ['menunggu'=>'Menunggu','disetujui'=>'Disetujui','ditolak'=>'Ditolak','batal'=>'—','belum'=>'Belum'];
    $hm = fn ($t) => $t ? substr($t, 0, 5) : null;
@endphp

<div class="rdt">
    <div class="rdt-sum">
        <span class="absk-type">{{ $tipeMap[$req->tipe] ?? $req->tipe }}</span>
        <span class="absk-pill s-{{ $stPill[$req->status] ?? 'libur' }}">{{ $stText[$req->status] ?? $req->status }}</span>
    </div>

    <div class="rdt-info">
        <div class="rdt-row"><span class="rdt-k">Tanggal</span><span class="num">{{ \Illuminate\Support\Carbon::parse($req->tanggal_from)->translatedFormat('d M Y') }}@if($req->tanggal_to != $req->tanggal_from) – {{ \Illuminate\Support\Carbon::parse($req->tanggal_to)->translatedFormat('d M Y') }}@endif</span></div>
        @if($req->jam_from)<div class="rdt-row"><span class="rdt-k">Jam</span><span class="num">{{ $hm($req->jam_from) }}@if($req->jam_to) – {{ $hm($req->jam_to) }}@endif</span></div>@endif
        @if($req->alasan)<div class="rdt-row"><span class="rdt-k">Alasan</span><span>{{ $req->alasan }}</span></div>@endif
        @if($req->foto)<div class="rdt-row"><span class="rdt-k">Foto</span><a href="{{ asset('uploads/att_request/'.$req->foto) }}" target="_blank">Lihat foto bukti</a></div>@endif
    </div>

    <div class="rdt-sechead">RANTAI PERSETUJUAN</div>
    <div class="rdt-timeline">
        @forelse($timeline as $t)
            <div class="rdt-node s-{{ $t['state'] }}">
                <div class="rdt-dot"></div>
                <div class="rdt-nbody">
                    <div class="rdt-lvl">Lapis {{ $t['level'] }} · <span class="rdt-state">{{ $stLbl[$t['state']] ?? $t['state'] }}</span></div>
                    <div class="rdt-names">{{ $t['names'] }}</div>
                    @if($t['by'])<div class="rdt-by">{{ $stLbl[$t['state']] ?? '' }} oleh {{ $t['by'] }}@if($t['at']) · {{ \Illuminate\Support\Carbon::parse($t['at'])->translatedFormat('d M, H:i') }}@endif</div>@endif
                    @if($t['catatan'])<div class="rdt-note">"{{ $t['catatan'] }}"</div>@endif
                </div>
            </div>
        @empty
            <div class="absk-empty">Tak ada rantai (langsung disetujui / tak butuh persetujuan).</div>
        @endforelse
    </div>
</div>
