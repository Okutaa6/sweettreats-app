{{--
    Tiga kartu ringkas. Tiap kartu adalah TOMBOL FILTER, bukan angka pajangan —
    chevron di pojok yang menandakannya.

    Kartu ke-3 beda perilakunya: ia MENGGANTI tabel dengan antrean penjadwalan,
    bukan menyaringnya. Itu antrean yang sebenarnya di hari-hari awal, karena
    "Terlewat" cuma bisa ada kalau seseorang sudah menjadwalkan duluan.

    Tiap angka besar selalu ditemani baris pembanding — audiens di sini butuh
    konteks, bukan satu angka telanjang.
--}}
@php
    $kartuDaftar = [
        [
            'key'   => 'minggu-ini',
            'label' => 'Jadwal minggu ini',
            'ikon'  => 'fa-calendar-week',
            'angka' => $kartu['minggu_ini'],
            'sub'   => $kartu['sudah_rsv'] . ' sudah reservasi · ' . $kartu['belum_rsv'] . ' belum',
            'warn'  => false,
        ],
        [
            'key'   => 'terlewat',
            'label' => 'Terlewat',
            'ikon'  => 'fa-exclamation-triangle',
            'angka' => $kartu['terlewat'],
            'sub'   => $kartu['aktif'] > 0
                        ? $kartu['persen_terlewat'] . '% dari aktif · ' . $kartu['terlewat_lama'] . ' lewat >14 hari'
                        : 'belum ada jadwal yang bisa terlewat',
            'warn'  => true,
        ],
        [
            'key'   => 'backlog',
            'label' => 'Belum punya jadwal',
            'ikon'  => 'fa-clock',
            'angka' => $kartu['backlog'],
            'sub'   => 'belum pernah dijadwalkan sejak data ditarik · ' . $kartu['hilang'] . ' hilang >6 bulan',
            'warn'  => false,
        ],
    ];
@endphp

<div class="ort-cards">
    @foreach ($kartuDaftar as $k)
        @php
            $aktif = ($kartuAktif ?? null) === $k['key'];
            /* 'tab' => null memulangkan ke tab List. Kartu ini menyaring
               daftar kerja; ditekan dari Kalender atau Pasien Ortho ia harus
               membawa Sales ke tempat yang memang tersaring, bukan menyalakan
               dirinya sendiri di tab yang mengabaikannya. */
            $href  = $tautan([
                'kartu'   => $aktif ? null : $k['key'],
                'tab'     => null,
                'cabang'  => null,
                'urgensi' => null,
                'page'    => null,
            ]);
        @endphp
        <a href="{{ $href }}"
           class="ort-card {{ $k['warn'] ? 'ort-card--warn' : '' }} {{ $aktif ? 'is-active' : '' }}"
           title="{{ $aktif ? 'Klik untuk melepas filter ini' : 'Klik untuk menyaring ke kelompok ini' }}">
            <div class="ort-card__top">
                <span class="ort-card__icon"><i class="fas {{ $k['ikon'] }}"></i></span>
                <span class="ort-card__label">{{ $k['label'] }}</span>
                <span class="ort-card__chev"><i class="fas fa-chevron-right"></i></span>
            </div>
            <div class="ort-card__num tabular">{{ number_format($k['angka'], 0, ',', '.') }}</div>
            <div class="ort-card__sub">{{ $k['sub'] }}</div>
        </a>
    @endforeach
</div>
