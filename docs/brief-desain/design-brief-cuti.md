# Design Brief — Cuti (rekap Finance + cuti bersama)

⚠️ **SATU layar per sesi claude.ai/design.** Brief ini = 1 layar saja:
halaman **Cuti** (rekap saldo semua karyawan + aksi cuti bersama). Kartu
saldo di halaman Akun karyawan TIDAK ikut brief ini (dibangun langsung
mengikuti gaya halaman Akun yang sudah ada).

## Konteks produk (baca dulu)

Klinik gigi 5 cabang, ~29 karyawan. Jatah cuti 12 hari per kontrak 12 bulan
(tanggal kontrak beda-beda per orang; sebagian tanggal BELUM diketahui —
sistem tetap jalan, tanggalnya menyusul). Halaman ini dipakai **Finance/admin
di desktop** untuk: melihat saldo semua orang, menandai cuti bersama massal,
dan merapikan kasus khusus. Bahasa UI: Indonesia awam, bukan istilah HR
Inggris.

Aturan penting yang HARUS terasa di desain:
- **Saldo boleh minus** = karyawan "ganti hari" (nambah hari kerja). Minus
  bukan error — tapi harus mencolok (merah) dan tertulis awam:
  "melebihi jatah 2 hari — ganti hari".
- **Cuti bersama motong semua orang sekaligus**, kecuali yang dikecualikan
  (mis. ganti hari). Alurnya: pilih tanggal + label → lihat daftar siapa saja
  yang kena → hilangkan centang orang yang dikecualikan → terapkan.
- **Tanggal kontrak kosong itu normal** (datanya sedang dikumpulkan) — tampil
  sebagai badge kuning "tanggal kontrak belum ada", BUKAN error merah.
- **Eksekutif tanpa jatah tidak muncul** di daftar ini sama sekali.

## Layar: Cuti — rekap & cuti bersama

Struktur (desktop-first, boleh responsive):

1. **Header halaman**: judul "Cuti", subjudul tanggal hari ini. Kanan: tombol
   primer "Cuti bersama" (aksi utama halaman) + tombol sekunder kecil
   "Mulai kontrak baru" (dipakai jarang).
2. **Baris statistik** (3-4 kartu kecil): Total karyawan ber-jatah ·
   Sedang minus (angka merah) · Tanggal kontrak belum ada (angka kuning) ·
   Rata-rata sisa.
3. **Tabel/daftar karyawan** (grid rapi, satu baris per orang):
   - Avatar inisial berwarna + nama + cabang (sekunder).
   - **Sisa cuti** = angka paling menonjol di baris (mis. "7 hari").
     Minus: merah + teks kecil "ganti hari". Nol: netral, bukan merah.
   - Terpakai periode ini (kecil, sekunder), termasuk berapa dari cuti
     bersama.
   - Kolom kontrak: "s/d 18 Jun 2027" ATAU badge kuning "tanggal belum ada".
   - Klik baris → **drawer kanan** riwayat orang itu: daftar potongan
     (tanggal, jenis: cuti / cuti bersama / koreksi ganti hari, siapa yang
     mencatat) + tombol kecil "Koreksi" dan "Mulai kontrak baru".
   - Filter atas tabel: cari nama, filter cabang, toggle "Hanya yang minus".
4. **Alur "Cuti bersama"** (modal/panel, bagian dari layar ini): langkah
   ringkas — (a) pilih tanggal + label ("Cuti bersama Idul Fitri"),
   (b) daftar semua karyawan ber-jatah dengan centang aktif semua,
   Finance menghilangkan centang orang yang dikecualikan, (c) ringkasan
   "akan memotong 1 hari dari 27 orang" + tombol Terapkan. Sertakan keadaan
   sesudah: baris sukses hijau.

## Gaya visual (WAJIB ikut design system Pusat Akun yang sudah ada)

- Latar cream `#F4ECE2`, teks ink `#3B1500`, kartu putih radius besar,
  header tabel graphite gradient `linear-gradient(120deg,#2C2C29,#3A3A36)`
  dengan teks terang.
- Warna status: merah `#C0392B`/bg `#FBE3DE` (minus), kuning `#B5701A`/bg
  `#FBEFD9` (tanggal kontrak belum ada), hijau `#2F8E55`/bg `#E3F4E8`
  (sukses). Font Plus Jakarta Sans. Angka pakai tabular.
- Avatar palette: `#8A3A12 #A8481B #2F8E55 #3C5FA8 #B5701A #7A3415 #5A6B2E
  #8E2419`.
- **Tanpa emoji.** Ikon = SVG garis (stroke currentColor) minimalis.
- Fokus visual: kolom sisa cuti harus jadi hal pertama yang terbaca;
  kontrol (filter/tombol) jangan bersaing dengan data.

## Yang TIDAK usah didesain

- Halaman karyawan (kartu saldo di Akun) — sudah ada polanya.
- Alur pengajuan cuti — sudah hidup di fitur absensi.
- Ekspor/cetak, setengah hari, integrasi gaji — di luar v1.
