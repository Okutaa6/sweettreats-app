{{--
    Pill status — SATU-SATUNYA tempat status dirender jadi warna + teks.
    Dipakai layar pengaju & Finance supaya kamus statusnya tidak pernah beda.

    $status  : kode status (diajukan/disetujui/…)
    $pendek  : true = label ringkas (kartu & tabel sempit), false = label penuh
--}}
@php
    $pendekMap = [
        'diajukan'   => 'Menunggu',
        'disetujui'  => 'Disetujui',
        'ditolak'    => 'Ditolak',
        'dibayar'    => 'Dibayar',
        'selesai'    => 'Selesai',
        'kadaluarsa' => 'Kadaluarsa',
    ];
    $teks = ($pendek ?? false)
        ? ($pendekMap[$status] ?? $status)
        : (\App\Models\RopRequest::LABEL[$status] ?? $status);
@endphp
<span class="rop-pill rop-pill--{{ $status }}">{{ $teks }}</span>
