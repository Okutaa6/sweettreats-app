{{--
    Peringatan periode belum final.

    Muncul HANYA kalau rentang yang dipilih menyentuh hari ini. Basis angka
    komisi adalah "setelah pembayaran": bulan yang sudah lewat sudah final dan
    stabil, sedangkan bulan berjalan masih naik tiap hari mengikuti cicilan
    pasien yang masuk.

    Sengaja baris tipis di alur halaman, BUKAN modal — Finance sedang bekerja,
    dan modal yang harus ditutup akan ditutup tanpa dibaca.

    Selalu disertai jalan keluar (tombol ke bulan lalu). Peringatan tanpa jalan
    keluar hanya membuat orang mengabaikannya.
--}}
@php
    $paramBulanLalu = ['date_from' => $bulanLalu[0], 'date_to' => $bulanLalu[1]];
    if (! $branchLocked && $placeId) { $paramBulanLalu['place'] = $placeId; }
    if (! empty($hidden)) { $paramBulanLalu = array_merge($hidden, $paramBulanLalu); }
@endphp

<div class="kd-banner noprint">
    @include('finance.komisi._ikon', ['n' => 'awas', 's' => 18])
    <div>
        <strong>Periode berjalan</strong> &mdash; angka masih bertambah mengikuti pembayaran
        yang masuk. Untuk pembayaran fee, pakai bulan yang sudah lewat.
    </div>
    <div class="kd-banner__aksi">
        <a class="kd-btn kd-btn--sm" href="{{ $aksi }}?{{ http_build_query($paramBulanLalu) }}">
            Pakai {{ $bulanLaluLbl }}
        </a>
    </div>
</div>
