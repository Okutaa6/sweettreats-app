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
{{-- Galat HARUS ikut dirender di sini.

     layouts.notif hidup di kerangka halaman, dan kerangka itu tidak pernah
     dimuat lagi saat aksi dikirim lewat fetch. Jadi aksi yang DITOLAK server
     (mis. kontrol sudah ditutup lebih dulu oleh tarikan malam) dulu berakhir
     tanpa satu pun tanda di layar: tabelnya dirender ulang, isinya sama,
     dan Sales mengira kliknya tidak terbaca lalu mengklik lagi. --}}
@if (session('error'))
    <div class="ort-toast ort-toast--galat" role="alert" data-ort-toast="galat">
        <i class="fas fa-exclamation-circle"></i>
        <span>{{ session('error') }}</span>
        <button type="button" class="ort-toast__x" aria-label="Tutup"
                onclick="this.closest('.ort-toast').remove()">&times;</button>
    </div>
@endif

@include('crud.ortho._toast')
@include('crud.ortho._tabel')
