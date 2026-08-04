{{--
    Kartu satu pengajuan di daftar pengaju.

    Urutan baca yang disengaja: nomor (kecil) → penerima (tebal) → keterangan
    (1 baris) → nominal besar + status. Baris "nudge" paling bawah menjawab
    "lalu aku harus apa" — itu yang bikin orang tidak perlu bertanya-tanya.

    $r      : RopRequest
    $aktif  : true kalau sedang dibuka di panel kanan (layar lebar)
--}}
@php
    $nudge = match ($r->status) {
        'dibayar'  => ['hijau', 'Sudah dibayar — upload nota untuk menutup'],
        'ditolak'  => ['merah', 'Ditolak — bisa diajukan ulang'],
        'diajukan' => $r->umurHari() >= 3
            ? ['kuning', 'Sudah ' . $r->umurHari() . ' hari menunggu ACC']
            : null,
        default    => null,
    };
@endphp
<a href="{{ route('rop.pengajuan.show', $r->id) }}"
   data-rop-lihat="{{ $r->id }}"
   class="rop-item rop-item--{{ $r->status }} {{ ($aktif ?? false) ? 'rop-item--aktif' : '' }}">
    <div class="rop-item__atas">
        <span class="rop-num">{{ $r->number }}</span>
        <span>{{ $r->umurHari() === 0 ? 'hari ini' : $r->umurHari() . ' hari lalu' }}</span>
    </div>

    <div class="rop-item__vendor">{{ $r->namaPenerima() }}</div>
    <div class="rop-item__ket">{{ $r->description }}</div>

    <div class="rop-item__bawah">
        <span class="rop-item__nominal rop-num">Rp {{ number_format((float) $r->amount, 0, ',', '.') }}</span>
        @include('rop._pill', ['status' => $r->status, 'pendek' => true])
    </div>

    @if ($r->is_urgent && in_array($r->status, ['diajukan', 'disetujui'], true))
        <div style="margin-top:8px"><span class="rop-badge-darurat">Darurat</span></div>
    @endif

    @if ($nudge)
        <div class="rop-nudge rop-nudge--{{ $nudge[0] }}">
            @include('rop._ikon', ['n' => $nudge[0] === 'hijau' ? 'cek' : 'awas', 's' => 15])
            <span>{{ $nudge[1] }}</span>
        </div>
    @endif
</a>
