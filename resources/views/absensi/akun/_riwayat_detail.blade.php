@php
    // ikut satu sumber kebenaran, jangan disalin ulang (jenis baru pernah terlewat)
    // (komentar PHP, BUKAN {{-- --}} — blade comment di dalam @php tidak di-strip
    //  oleh compiler dan jadi syntax error saat view dirender)
    // TIPE_RIWAYAT, bukan TIPE: jenis lama yang tak lagi ditawarkan (mis.
    // 'libur') tetap harus punya nama saat riwayatnya dibuka.
    $tipeMap = \App\Http\Controllers\Absensi\AkunPengajuanController::TIPE_RIWAYAT;

    // Tukar Hari menyimpan DAFTAR tanggal, bukan rentang. Menampilkan
    // "09 – 13 Sep" untuk tukar 09+10 ↔ 12+13 itu bohong: hari 11 tak terlibat
    // sama sekali. Jadi tanggalnya dibaca dari daftarnya sendiri.
    $tukarHari = null;
    if ($req->tipe === 'tukar') {
        $baris = \App\Models\AttRequestHari::where('request_id', $req->id)
            ->orderBy('tanggal')->get();
        if ($baris->count()) {
            $fmt = fn ($c) => $c->groupBy(fn ($h) => $h->peran);
            $g = $fmt($baris);
            $tukarHari = [
                'masuk' => ($g[\App\Models\AttRequestHari::PERAN_MASUK] ?? collect())
                    ->map(fn ($h) => $h->tanggal->translatedFormat('d M Y'))->all(),
                'libur' => ($g[\App\Models\AttRequestHari::PERAN_LIBUR] ?? collect())
                    ->map(fn ($h) => $h->tanggal->translatedFormat('d M Y'))->all(),
            ];
        }
    }
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
        @if($tukarHari)
            <div class="rdt-row"><span class="rdt-k">Masuk</span><span class="num">{{ implode(' · ', $tukarHari['masuk']) }}</span></div>
            <div class="rdt-row"><span class="rdt-k">Gantinya libur</span><span class="num">{{ implode(' · ', $tukarHari['libur']) }}</span></div>
        @else
            <div class="rdt-row"><span class="rdt-k">Tanggal</span><span class="num">{{ \Illuminate\Support\Carbon::parse($req->tanggal_from)->translatedFormat('d M Y') }}@if($req->tanggal_to != $req->tanggal_from) – {{ \Illuminate\Support\Carbon::parse($req->tanggal_to)->translatedFormat('d M Y') }}@endif</span></div>
        @endif
        @if($req->jam_from)<div class="rdt-row"><span class="rdt-k">Jam</span><span class="num">{{ $hm($req->jam_from) }}@if($req->jam_to) – {{ $hm($req->jam_to) }}@endif</span></div>@endif
        @if($req->alasan)<div class="rdt-row"><span class="rdt-k">Alasan</span><span>{{ $req->alasan }}</span></div>@endif
        @if($req->foto)<div class="rdt-row"><span class="rdt-k">{{ $req->tipe === 'sakit' ? 'Surat Dokter' : 'Foto' }}</span><a href="{{ route('akun.pengajuan.berkas', $req->id) }}" target="_blank">Lihat {{ $req->tipe === 'sakit' ? 'surat dokter' : 'foto bukti' }}</a></div>@endif
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
