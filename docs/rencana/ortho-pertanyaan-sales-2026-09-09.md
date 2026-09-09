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
