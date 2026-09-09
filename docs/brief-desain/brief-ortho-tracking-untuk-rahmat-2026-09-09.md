# Brief — Tracking Pasien Ortho (IT-2026-013)

> Untuk **Rahmat, Konsultan IT**, yang akan menyusun Analysis & Design.
> Disiapkan chat HUB, 9 September 2026. Pemohon tiket: **Anisa (Sales/FO)**.
>
> ⛔ **Ini BUKAN desain.** Isinya kebutuhan, data yang tersedia, keputusan yang
> sudah diambil Sales, dan lubang yang belum tertutup. Bentuk layar, model data,
> dan alur kerjanya milikmu.
>
> Semua angka **terukur langsung dari Omnicare prod 9 Sep 2026**. Cara
> mengulang tiap pengukuran ada di lampiran data (berkas terpisah).

---

## 1. Keadaan hari ini

Sales/Front Office melacak pasien ortho (behel) di **Google Sheets, satu berkas
per cabang, dibuat baru tiap tahun**. Yang dipakai sekarang berjudul
*DATA ORTHO CINERE*, berisi Januari–September 2026.

Tiap pasien satu baris: tanggal pemasangan, nomor RM, nama, keterangan status,
lalu **dua belas kolom bulan** yang diisi tanggal saat pasien datang kontrol.
Di kepala berkas ada rekap manual: 217 Active · 18 Done · 4 Pindah Kontrol ·
0 Endorser, dan 232 Metal · 2 Ceramic · 5 Sapphire · 0 Removable.

Pengisiannya, kata Anisa: *"tergantung situasi, kalau lagi free aja, ga pernah
nentu, makannya sering kelewat sama lupa."*

## 2. Kenapa cara ini gagal — dan ini bukan soal kedisiplinan

**Sheet-nya dokumen per cabang. Pasiennya tidak.**

Terukur pada kohor Cinere 2026 (**222 pasien** yang kontrol ortho di Cinere
tahun ini; sheet mencatat 239):

| | terukur | ditandai sheet |
|---|---|---|
| kontrol terakhirnya di cabang **lain** | **12** | 4 |
| kontrol jarang (<2× dalam 6 bulan, definisi Anisa) | **60** | segelintir, ditulis tangan |

Sheet menangkap **4 dari 12** pasien yang sudah berpindah cabang. Delapan
sisanya tetap tercatat sebagai pasien Cinere yang "lama tak kontrol", padahal
mereka rajin — hanya di Bintaro.

Contoh yang bisa kamu periksa sendiri:

```
D-2405-0030   2026-05-31[Bintaro] 2026-07-05[Bintaro] 2026-08-02[Bintaro] 2026-09-06[Bintaro]
D-2507-0978   2026-02-08[Bintaro] 2026-03-15[Bintaro] 2026-05-03[Bintaro] 2026-08-23[Bintaro]
B-2312-5166   2025-12-07[Bintaro] 2026-03-31[Bintaro] 2026-06-24[Cinere]  2026-08-16[Bintaro]
```

Pada kohor sepanjang masa (277 pasien), **76 menyentuh lebih dari satu cabang**;
setelah dipisahkan yang pindah permanen (18), **58 benar-benar bolak-balik**.
Tujuannya paling banyak Bintaro (68 pasien), lalu Sunter (8), Kalideres (3),
Pamulang (1).

Cara mengeceknya sekarang, kata Anisa: *"di cek satu satu lewat WA atau
koordinasi sama sales lain, jadi cuma seingetnya aja."*

**Angka 60 "jarang kontrol" itu bahkan lebih menyakitkan**, karena definisinya
datang dari Anisa sendiri dan tetap tak terdeteksi — menghitung frekuensi
kunjungan enam bulan ke belakang di sheet selebar dua belas kolom memang tak
bisa dilakukan dengan mata.

## 3. Keputusan yang SUDAH diambil Sales

Jangan ditanyakan ulang; ini sudah dijawab Anisa 9 Sep 2026.

| # | Keputusan |
|---|---|
| **Status "Active"** | **Bukan berbasis waktu.** Pasien tetap Active sampai selesai atau pindah ke klinik lain. Lama tak kontrol **tidak** mengubah status — ia memicu peringatan, bukan perubahan status. |
| **"Jarang kontrol"** | **Kurang dari 2 kunjungan dalam 6 bulan** (setara <1× per 3 bulan). Ini ukuran **frekuensi**, bukan kebaruan. |
| **Pasien kembali** | Yang sempat bolong lalu datang lagi **tetap Active**; riwayat bolongnya tetap terlihat. |
| **"Pindah Kontrol"** | Pindah ke **cabang Sweet Treats lain**, bukan keluar. |
| **Siapa menagih** | **Cabang tempat perawatan TERAKHIR.** Dipasang di Cinere tapi terakhir dirawat di Bintaro → **Bintaro** yang menghubungi, kecuali pasien menyatakan mau kembali ke Cinere. |
| **Cabang asal tetap tahu** | Cabang asal ingin tetap bisa melihat pasiennya yang pindah — *"dipisah aja, jadi tau mana yang pernah mana yang ngga."* |
| **"Done"** | Perawatan tuntas / bracket dilepas — **tetapi** kolom ini juga dipakai untuk pasien yang pindah ke klinik lain atau pindah pulau. |
| **"Endorser"** | **Bukan status.** Kategori pemasaran: pasien yang memasang behel lewat endorse Sweet Treats. |
| **Kolom "CONTROL"** | Bukan data klinis — **penanda anti-duplikat** buatan sendiri. Kalau berisi 2, berarti pasien itu terinput dua kali. |
| **Target durasi** | **± 2 tahun**, tapi berakhir saat dokter menyatakan selesai. |
| **Tindak lanjut** | FO menghubungi pasien yang lama tak kontrol, mengingatkan dan menawarkan jadwal. |

## 4. Kebutuhan yang lahir dari keluhan

Dikutip dari jawaban Anisa nomor 14 dan 15, diterjemahkan jadi kebutuhan:

1. **Berhenti mengisi manual.** *"jadi ga perlu isi terus."* Tanggal kontrol,
   jenis bracket, dan status seharusnya turun sendiri dari Omnicare.
2. **Berhenti menggulir.** *"harus liat daftar pasien berhari hari lalu, jadinya
   scroll panjang banget."* Yang dibutuhkan bukan tabel panjang, melainkan
   **daftar siapa yang perlu dihubungi hari ini**.
3. **Berhenti bolak-balik ke Omnicare.** *"carinya juga susah harus ke omni."*
   Pencarian pasien harus ada di layar yang sama.
4. **Bisa dilaporkan.** *"maunya biar bisa laporan gitu sih, tapi kan susah yaa
   kalau manual."*
5. **Lintas cabang tidak boleh hilang** — lihat §2.

## 5. Data yang tersedia — ringkas

Rinciannya di lampiran data. Intinya:

- **Tanggal kontrol**: item `Control Metal Braces` dan keluarganya di
  `omnicare_sales_detail`. **125.220 baris sejak 2023**, masih terisi sampai
  8 Sep 2026.
- **Jenis bracket**: terbaca dari nama item — Metal, Ceramic, Sapphire,
  Stellar, Self Ligating. ⚠️ Anisa: jenis **bisa berubah di tengah perawatan**,
  dan di sheet hanya yang terbaru dicatat sehingga **riwayat lama hilang**.
  Omnicare menyimpan seluruh riwayatnya — ini keunggulan yang layak dipakai.
- **Selesai**: item `Lepas Bracket`.
- **Cabang tiap kunjungan**: kolom `branch_name` per baris transaksi.
- **Pasien**: `p_id` (nomor RM), `p_name`, plus `omnicare_patient` untuk kontak.

**Terbukti cocok dengan sheet, tanggal per tanggal.** Tegar Sanfian
(D-2509-1035): sheet mencatat pemasangan 19/9/2025 dan kontrol 8/3, 12/4, 10/5,
7/6, 19/7; Omnicare mencatat 2025-09-19 lalu 03-08, 04-12, 05-10, 06-07, 07-19.

## 6. ⛔ Lubang yang harus kamu putuskan

### 6.1 Tanggal pemasangan tidak punya item baku
Pemasangan **ada** di data, tetapi namanya mengikuti kampanye promo dan berubah
terus:

```
Opening Cinere - Metal Braces                    (27 Jan 2024)
Cimulang Oktober - Siganture Metal Braces Gp     (19 Sep 2025)
Payday Pemasangan Signature Metal - Drg. Fri     (8 Agu 2026)
Pemasangan otho metal - drg fri                  (28 Mar 2026)
```

Tak ada daftar nama tetap yang bisa dicocokkan. Kemungkinan pendekatan —
**keputusanmu, bukan kami**: transaksi ortho **pertama** seorang pasien yang
bukan `Control …`, bukan `Kawat …`, dan bukan `Bracket … Lepas`.

### 6.2 Kepemilikan bisa berbalik tiap kunjungan
Aturan "cabang perawatan terakhir" jelas, tapi pasien yang bolak-balik membuat
kepemilikan berpindah tiap bulan:

```
B-2312-5166   Bintaro → Bintaro → Cinere → Bintaro
```

Perlu diputuskan: kepemilikan pindah pada **satu** kunjungan di cabang lain,
atau butuh beberapa kunjungan berturut-turut? Kalau berbalik tiap bulan,
tanggung jawab menghubungi ikut berpindah-pindah dan bisa jadi tak ada yang
merasa bertanggung jawab.

### 6.3 "Done" mencampur dua hal yang berbeda
Selesai perawatan (keberhasilan) dan pindah ke klinik lain (kehilangan pelanggan)
saat ini masuk kotak yang sama. Untuk pelaporan keduanya perlu dipisah.
Terukur: **27 pasien Cinere punya catatan `Lepas Bracket`**, sementara sheet
menandai **18** sebagai Done — selisih 9 kemungkinan besar berasal dari
percampuran ini.

### 6.4 Satu daftar atau per cabang?
Anisa ragu: *"enaknya bisa lihat cabang lain sih, tapi kalau biar rapih mending
per cabang ga sih?"*

Catatan dari kami, bukan keputusan: **datanya harus satu dan global** — kalau
disimpan per cabang, cacat yang sedang kita buang akan tertanam ulang. Yang
boleh per cabang adalah **tampilannya**.

### 6.5 Nomor RM di sheet tidak selalu benar
Disalin manual. Contoh: Dyah Ayu Dwi Antari tertulis **D-2412-0044** di sheet,
**D-2412-0744** di Omnicare. Kalau ada migrasi data lama, jangan percaya nomor
RM di sheet sebagai kunci.

### 6.6 Selisih jumlah pasien
Kohor 2026 terukur **222**, sheet mencatat **239**. Anisa menyebut sheet kadang
berisi entri dobel dan kadang ada pasien yang tak dimasukkan. Selisih 17 belum
ditelusuri satu per satu — kalau kamu butuh, kami bisa mengurainya.

## 7. Batas ruang lingkup

- **Cinere dipakai lebih dulu sebagai alat ukur, bukan sebagai batas.**
  Sheet Cinere paling rapi, jadi ia jadi pembanding: sistem yang benar harus
  mereproduksi 222 pasien miliknya **dan** memunculkan 12 pindah-cabang serta
  60 jarang-kontrol yang tak terlihat.
- **Rancang global sejak awal.** Membangun per cabang lalu "nanti digabung"
  akan menanam ulang cacat di §2.
- Yang **tidak** diminta: mengubah apa pun di Omnicare. Alur kita satu arah,
  Omnicare → Data Center, dan tetap begitu.

## 8. Lampiran

- `docs/rencana/ortho-lampiran-data-2026-09-09.md` — peta tabel & kolom, daftar
  item ortho beserta kode dan jumlah baris, jejak pasien nyata, dan kueri untuk
  mengulang tiap angka di dokumen ini.
- `docs/rencana/ortho-pertanyaan-sales-2026-09-09.md` — 21 pertanyaan asli
  beserta jawaban Anisa.
