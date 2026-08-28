{{--
    Chip spesialisasi dokter. $nama = nama lengkap (dengan gelar).

    Warna diambil dari App\Support\DoctorSpec, KECUALI satu override lokal:

    DoctorSpec memberi "Bedah Mulut" warna #C0392B. Di halaman ini #C0392B
    dicadangkan khusus untuk delta negatif (komisi turun). Chip merah yang duduk
    sebaris dengan panah turun merah akan terbaca sebagai peringatan, padahal
    cuma nama spesialisasi. Diganti cokelat-bata netral — hanya di sini,
    DoctorSpec sendiri tidak disentuh karena dipakai halaman Doctor.
--}}
@php
    $spek = \App\Support\DoctorSpec::forName($nama);
    if ($spek['key'] === 'bedah') {
        $spek['color'] = '#8E4B2E';
        $spek['bg']    = '#F6E7DE';
    }
@endphp
<span class="kd-chip" style="background:{{ $spek['bg'] }};color:{{ $spek['color'] }}">
    {{ $spek['label'] }}
</span>
