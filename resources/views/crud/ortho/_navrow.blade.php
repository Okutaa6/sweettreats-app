{{--
    Pita navigasi — slot kiri milik tab yang sedang terbuka + tab tingkat atas
    di kanan, dalam SATU baris, duduk tepat di atas isi tab.

    Kenapa di bawah, bukan di kepala halaman: tab ini menentukan isi di
    bawahnya. Ditaruh di kepala, ia terpisah dari yang diubahnya oleh tiga blok
    lain (filter, kartu ringkas, tab cabang) — mata harus melompat jauh untuk
    menghubungkan sebab dan akibat.

    $kiri = nama partial untuk sisi kiri, atau null. Pita ini SENGAJA tidak
    tahu apa isinya — saat ini selalu _nav_urgensi (sub-tab kegentingan), dan
    null hanya di antrean penjadwalan, yang isinya justru pasien TANPA jadwal
    sehingga tidak bisa disaring per rentang tanggal.

    Tab CABANG bukan urusan pita ini: ia baris tersendiri (_btabs) di atasnya,
    juga berlaku di ketiga tab.

    Urutan di markup: sisi kiri DULU, baru tab utama. Sengaja begitu, bukan
    lewat flex row-reverse — urutan Tab keyboard dan pembaca layar mengikuti
    markup, jadi row-reverse hanya memindahkan yang terlihat dan meninggalkan
    urutan sebenarnya terbalik.
--}}
<div class="ort-navrow">

    @if (! empty($kiri))
        <div class="ort-navrow__kiri">
            @include($kiri)
        </div>
    @endif

    {{-- Komponen BERSAMA .st-view-tabs (components.sweettreats.css §11), bukan
         tiruan. Bentuk yang sama dipakai modul Reservasi: wadah berbingkai,
         tab aktif jadi pil cokelat penuh, hover ikut cokelat. Kalau gaya tab
         aplikasi berubah suatu hari, halaman ini ikut tanpa disentuh. --}}
    <div class="st-view-tabs ort-navrow__tabs">
        @foreach ($tabs as $key => $t)
            <a href="{{ $tautan(['tab' => $key, 'urgensi' => null, 'cabang' => null, 'rawat' => null]) }}"
               class="st-view-tab {{ $tab === $key ? 'active' : '' }}">
                <i class="fas {{ $t['ikon'] }}"></i> {{ $t['label'] }}
            </a>
        @endforeach
    </div>

</div>
