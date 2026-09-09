# Pertanyaan untuk Anisa — tracking pasien ortho (IT-2026-013)

> Disiapkan chat HUB 9 Sep 2026 untuk Ekky, dijawab oleh **Anisa (Sales/FO)**.
> Jawabannya jadi isi dokumen kebutuhan yang dikirim ke **Rahmat (Konsultan IT)**
> sebagai bahan Analysis & Design. Kita tidak merancang; kita menyiapkan bahan.
>
> Semua angka di bawah **terukur dari data Omnicare 9 Sep 2026**, bukan perkiraan.
> Acuan: sheet DATA ORTHO CINERE (217 Active · 18 Done · 4 Pindah Kontrol ·
> 232 Metal · 2 Ceramic · 5 Sapphire = 239 pasien).

---

## A. Arti kolom KETERANGAN

Di sheet, kolom ini diisi tangan: `Active`, `jarang kontrol`,
`hampir 1 tahun tidak kontrol`. Supaya bisa dihitung sendiri oleh sistem,
kami perlu ambang angkanya.

1. **Berapa lama seorang pasien boleh tidak kontrol sebelum berhenti disebut
   "Active"?** Satu bulan? Dua? Tiga?
2. **Apa beda "jarang kontrol" dan "hampir 1 tahun tidak kontrol"** — ada
   ambang bulan tertentu, atau perasaan saja saat mengisi?
3. Kalau seorang pasien tidak kontrol 3 bulan lalu datang lagi, **apakah dia
   kembali "Active"**, atau tetap ditandai pernah bolong?

*Konteks terukur:* di Cinere ada **30 pasien** yang kontrol terakhirnya
6–12 bulan lalu dan **37 pasien** yang lewat 12 bulan.

## B. "PINDAH KONTROL" dan "DONE"

4. **"PINDAH KONTROL" (4 orang) itu pindah ke cabang Sweet Treats yang lain,
   atau pindah keluar ke klinik lain?** Dua hal ini sangat berbeda: yang
   pertama masih pasien kita, yang kedua bukan.
5. **"DONE" (18 orang) artinya perawatannya tuntas dan bracket dilepas**, atau
   termasuk yang berhenti di tengah jalan?
6. **"ENDORSER" (0) itu apa?** Belum pernah terisi, jadi kami tak tahu
   maksudnya.

*Konteks terukur:* di Omnicare, **27 pasien Cinere** punya catatan
"Lepas Bracket", sementara sheet menandai 18 sebagai Done. Selisih 9 perlu
dijelaskan — mungkin lepas bracket bukan berarti selesai?

## C. Pasien yang kontrol di cabang lain — INI YANG PALING PENTING

Ini sebab utama tracking sering kelewat, dan sudah terukur:

> Dari **277 pasien ortho Cinere**, **76 pernah kontrol di cabang lain**.
> Setelah dipisahkan yang cuma pindah permanen (18), tersisa **58 pasien yang
> benar-benar bolak-balik**. **30 di antaranya kontrol terakhirnya bukan di
> Cinere.** Mereka paling banyak ke Bintaro (68), lalu Sunter (8),
> Kalideres (3), Pamulang (1).
>
> Contoh nyata, pasien **B-1909-2157**: kontrol di Cinere Oktober 2025, lalu
> Januari, Maret, Mei, dan Juni 2026 semuanya di **Bintaro**. Di sheet Cinere
> dia terlihat hilang hampir setahun. Padahal rajin, cuma di cabang lain.

7. **Kalau pasien yang dipasang di Cinere kontrol di Bintaro, siapa yang
   bertanggung jawab menghubunginya kalau dia berhenti datang?** Cinere yang
   memasang, Bintaro yang terakhir melayani, atau keduanya?
8. **Apakah Sales Cinere tetap ingin melihat pasien itu di daftarnya**, dengan
   penanda "kontrol terakhir di Bintaro"? Atau begitu pindah, dia hilang saja
   dari daftar Cinere?
9. **Apakah selama ini kalian tahu 58 pasien ini ada?** Kalau sudah tahu,
   bagaimana cara mengeceknya sekarang — tanya lewat WhatsApp antar cabang?
10. Kalau nanti daftarnya jadi satu untuk lima cabang, **apakah Sales Cinere
    boleh melihat pasien milik cabang lain**, atau hanya miliknya sendiri?

## D. Cara kerja hari ini

11. **Kapan sheet diisi** — langsung tiap pasien selesai kontrol, atau
    direkap belakangan (harian/mingguan)?
12. **Siapa saja yang mengisi**, dan apakah tiap cabang punya sheet sendiri
    dengan cara pengisian yang berbeda-beda?
13. **Setelah tahu seorang pasien telat kontrol, apa yang kalian lakukan?**
    Telepon, WhatsApp, atau menunggu dia datang sendiri? Apakah tindakan itu
    dicatat di suatu tempat?
14. **Sheet ini dipakai untuk apa selain tracking?** Ada laporan rutin ke
    manajemen yang mengambil angkanya?
15. **Apa yang paling menyebalkan dari cara sekarang?** Ini pertanyaan bebas —
    jawaban yang jujur di sini sering lebih berguna daripada semua di atas.

## E. Kolom yang tidak ada padanannya di Omnicare

16. **Kolom "CONTROL" berisi angka 1 di semua baris** — itu artinya apa?
    Nomor urut, jumlah kontrol, atau penanda lain?
17. **Apakah jenis bracket bisa berubah di tengah perawatan?** (Metal lalu
    ganti Sapphire, misalnya.) Kalau bisa, yang dicatat yang mana?
18. **Apakah ada target lama perawatan** per pasien (misal 18 bulan), atau
    berjalan sampai dokter bilang selesai?

## F. Kualitas data

19. **Dari mana kalian menyalin nomor rekam medis ke sheet?** Kami menemukan
    setidaknya satu yang tak cocok: Dyah Ayu Dwi Antari tertulis
    **D-2412-0044** di sheet, tapi **D-2412-0744** di Omnicare.
20. **Apakah ada pasien ortho yang sengaja TIDAK dimasukkan ke sheet?**
    Omnicare menghitung 277 pasien pernah kontrol ortho di Cinere, sheet
    mencatat 239. Selisih 38 perlu dijelaskan.
21. **Sejak kapan sheet Cinere ini mulai dipakai?** Data Omnicare untuk Cinere
    ada sejak **29 November 2023**; kalau sheet dimulai lebih awal, ada bagian
    yang tak bisa kami cocokkan.

---

## Yang TIDAK perlu Anisa jawab

Ini sudah terjawab dari data, dicantumkan supaya tidak ditanyakan dua kali:

- **Tanggal kontrol** — ada, dari item `Control Metal Braces` dan sejenisnya
  (125.220 baris sejak 2023).
- **Jenis bracket** — bisa dibaca dari nama itemnya (Metal/Ceramic/Sapphire/
  Stellar/Self Ligating).
- **Tanggal pemasangan** — ADA tapi namanya tidak baku; ia memakai nama
  kampanye promo (`Opening Cinere - Metal Braces`, `Cimulang Oktober -
  Signature Metal Braces`, `Payday Pemasangan Signature Metal - Drg. Fri`).
  Ini lubang yang sudah kami catat untuk Rahmat, bukan pertanyaan untuk Sales.
- **Kecocokan data** — sudah diuji dengan pasien nyata. Tegar Sanfian
  (D-2509-1035): sheet mencatat pemasangan 19/9/2025 dan kontrol 8/3, 12/4,
  10/5, 7/6, 19/7; Omnicare mencatat tanggal yang sama persis.

---

# ✅ JAWABAN ANISA — 9 September 2026

Direkam apa adanya, lalu diterjemahkan jadi aturan yang bisa dihitung.
Yang **tercetak tebal** adalah aturan yang masuk ke brief Rahmat.

| # | Jawaban Anisa | Aturan yang lahir |
|---|---|---|
| 1 | "seharusnya tetep aktif sampe dia selesai atau pindah ke klinik lain" | **Active BUKAN berbasis waktu.** Lama tak kontrol memicu peringatan, bukan mengubah status. |
| 2 | "jarang kontrol itu 6 bulan 2 kali atau 3 bulan sekali" | **Jarang = <2 kunjungan dalam 6 bulan.** Ukuran FREKUENSI, bukan kebaruan. |
| 3 | "seharusnya tetep aktif, tapi rentangnya jelas" | Pasien yang kembali tetap Active; riwayat bolongnya tetap tampak. |
| 4 | "biasanya pindah kontrol ke cabang sweettreats lain" | **Pindah Kontrol = antar cabang kita**, bukan keluar. |
| 5 | "selesai atau bracketnya udah di lepas, tapi ada kemungkinan juga buat dia pindah ke klinik lain… pindah pulau" | ⚠️ **"Done" mencampur dua hal**: perawatan tuntas vs kehilangan pelanggan. Perlu dipisah untuk laporan. |
| 6 | "orang yang pasang behel dengan di endorse dari sweettreats" | **Endorser = kategori pemasaran, bukan status.** Bisa berbarengan dengan Active. |
| 7 | "dihitung dari terakhir dia perawatan… kecuali pasien itu mau balik lagi ke cinere" | **Pemilik = cabang perawatan TERAKHIR.** Bisa diturunkan otomatis. |
| 8 | "di pisah aja, jadi tau mana yang pernah mana yang ngga" | Cabang asal tetap melihat pasiennya yang pindah, ditandai terpisah. |
| 9 | "di cek satu satu lewat WA… cuma seingetnya aja" | Tak ada mekanisme; inilah sebab 8 dari 12 perpindahan lolos. |
| 10 | "enaknya bisa lihat cabang lain sih, tapi kalau biar rapih mending per cabang ga sih?" | ⚠️ **Belum diputuskan.** Catatan kami: data global, tampilan boleh per cabang. |
| 11 | "kalau lagi free aja, ga pernah nentu, makannya sering kelewat sama lupa" | Pengisian bergantung waktu luang → tak bisa diandalkan. |
| 12 | "satu template, tapi beda-beda sheets tiap cabang, cara isinya sama" | Lima berkas terpisah berpola sama. |
| 13 | "kalau udah lama ga kontrol… langsung di ingetin atau follow up… di tawarin jadwal" | Tindak lanjut = ingatkan + tawarkan jadwal. Belum tercatat di mana pun. |
| 14 | "maunya biar bisa laporan gitu sih, tapi kan susah yaa kalau manual" | Pelaporan termasuk kebutuhan, bukan tambahan. |
| 15 | "suka lupa sama kelewat… scroll panjang banget… carinya juga susah harus ke omni… jadi ga perlu isi terus" | Empat keluhan → empat kebutuhan (brief §4). |
| 16 | "cuma penanda aja, biar datanya ga double input, kalau dua jadi ketahuan" | **Kolom CONTROL = detektor duplikat manual.** Otomatisasi membuatnya tak perlu. |
| 17 | "bisa, yang di catat biasanya yang paling baru, makannya data lama tuh hilang" | **Jenis bracket bisa berubah; riwayat lama hilang di sheet.** Omnicare menyimpannya. |
| 18 | "biasanya 2 tahun sih harusnya udah selesai, tapi kadang sampe dokter bilang selesai" | Target ±2 tahun, berakhir saat dokter menyatakan selesai. |
| 19 | "itu manual, makannya banyak yang salah" | Nomor RM di sheet tak bisa dipercaya sebagai kunci. |
| 20 | "kadang ada yang double isinya, atau emang ngga di masukin aja" | Menjelaskan selisih 222 terukur vs 239 di sheet. |
| 21 | "sheets itu baru di pakai tiap tahun… yang ini tuh yang 2026 dari januari sampe sekarang" | **Sheet per TAHUN.** 239 = pasien 2026, bukan sepanjang masa — ini meralat pembandingan awal kami. |

## Yang masih menggantung

1. **Nomor 10 belum jadi keputusan.** Satu daftar global atau per cabang?
   Catatan kami: datanya wajib satu, tampilannya boleh dipilah.
2. **Kepemilikan bisa berbalik tiap kunjungan** (brief §6.2) — belum ditanyakan
   karena baru terlihat sesudah jawaban nomor 7 diukur ke data.
3. **Nomor 15 mengandung satu kata yang perlu dipastikan**: *"maunya sih manual
   yaaa, jadi ga perlu isi terus"*. Bagian "ga perlu isi terus" berarti
   otomatis. Kami membacanya sebagai **otomatis**; tolong dikonfirmasi.
