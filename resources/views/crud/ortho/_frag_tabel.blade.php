{{--
    Fragmen tabel daftar kerja — dipakai JS untuk memperbarui tabel di tempat
    tanpa memuat ulang halaman.

    Isinya toast DULU, baru tabel. Urutan itu disengaja: pemanggilnya memindah
    toast ke <body> lalu menjalankan ulang <script> di dalam fragmen, dan
    skrip umur-8-detik milik _toast baru bisa menemukan toast-nya kalau toast
    itu sudah ada di dokumen.

    Kenapa server yang merender, bukan JS yang menambal DOM: status baris
    (Menunggu / Sudah di-FU / Terlewat N hari), tombol tindakan berikutnya, dan
    syarat munculnya tombol "Batalkan follow-up" semuanya diturunkan dari
    beberapa kolom sekaligus di _tabel.blade.php. Menirunya di JS berarti dua
    sumber kebenaran yang pasti melenceng suatu hari.
--}}
@include('crud.ortho._toast')
@include('crud.ortho._tabel')
