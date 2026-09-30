# Permintaan Revisi 3 — Tracking Pasien Ortho: Transfer Cabang

**Untuk:** tim desain · **Dari:** Ekky · **Tanggal:** 16 September 2026
**Menanggapi:** handoff Revisi 2 (`design_handoff_tracking_ortho`, 14 screenshot)

---

## Ringkasan

Revisi 2 menutup hampir semua yang diminta. Antrean penjadwalan bertingkat
(`02-antrean-belum-punya-jadwal.png`) **persis seperti yang dibayangkan** — tiga tingkat
kegentingan, lencana `0 kontrol dalam 6 bulan`, satu tombol tindakan. Sambungan dua arah
ke modul Reservasi berfungsi penuh sampai pembatalan balik. Chip identitas rekam medis
sudah benar, dan nomor RM tidak lagi dikarang.

**Yang belum: transfer cabang.** Bukan soal poles — ada satu kebutuhan yang belum
terwakili sama sekali, dan enam titik yang belum jelas duduk perkaranya.

| | Status |
|---|---|
| Antrean penjadwalan (kartu ke-3) | ✅ selesai, jangan diubah |
| Sambungan ke modul Reservasi | ✅ selesai |
| Nomor rekam medis + peringatan belum tertaut | ✅ selesai |
| Toggle Daftar ⇄ Kartu di Pasien Ortho | ✅ selesai |
| **Cabang asal melihat progress pasiennya di cabang lain** | ❌ **belum ada** |
| **Transfer sebagai konsep sistem** | ⚠️ ambigu di 6 titik |
| Header drawer tidak bertabrakan | ❌ **masih bertabrakan** |
| Screenshot yang bisa dibaca | ❌ 3 dari 14 tertutup drawer |

---

## 1. Temuan inti — cabang asal tidak bisa melihat progress

### Yang diminta

Brief konsultan, bab 4, keputusan Sales:

> **Cabang asal** — Tetap melihat pasiennya yang pindah, ditandai terpisah.

Dan ini maksud sebenarnya, yang perlu ditegaskan karena Revisi 2 menafsirkannya terlalu
sempit: **cabang asal ingin melihat *apa yang terjadi* pada pasiennya di cabang lain** —
apakah masih kontrol, kapan terakhir datang, jadwal berikutnya kapan, atau justru
terlantar di sana.

Ini bukan permintaan tambahan. Ini inti Masalah A di brief konsultan:

> *"B-1909-2157 di sheet Cinere tampak menghilang sejak Oktober 2025 dan pantas ditandai
> 'hampir 1 tahun tidak kontrol'. Padahal rajin — di Bintaro."*

Kegagalan yang dijelaskan brief bukan "cabang asal tidak tahu pasiennya pindah".
Kegagalannya adalah **cabang asal tidak tahu pasiennya baik-baik saja**. Kalau sistem
baru cuma bilang "sudah pindah ke Sunter" lalu diam, kegagalan itu terulang — cuma
dengan tampilan yang lebih rapi.

### Yang ada sekarang

Kartu **"Keluar dari cabang ini"** menampilkan tujuh kolom: nama pasien · dokter ·
tanggal · chip `ke Sunter · 10 Jan` · kolom kosong · label mati `Dipegang Sunter` ·
chevron. Seluruh kartu diredupkan `opacity: .72`.

Tidak ada satu pun kolom yang memberitahu **apa yang terjadi sesudah pindah**. Tidak ada
kontrol terakhir, tidak ada jadwal berikutnya, tidak ada status. Subjudulnya jujur
menyatakan maksudnya: *"Sudah dipegang cabang lain — tetap terlihat supaya tahu ke mana
perginya."* Itu arsip, bukan pemantauan.

Pengecekan di prototipe: kata **"progres" muncul nol kali**, dan grid kartunya
`minmax(170px,1.3fr) minmax(110px,.9fr) 104px minmax(150px,1fr) 128px 214px 22px` —
tidak ada slot untuk data kemajuan.

Satu-satunya jalan melihat progress adalah **membuka drawer detail** — dan di sana
memang bagus (`09-detail-drawer.png` menunjukkan pemisah `⇄ transfer ke Sunter · 15 Nov`
lalu baris 3–4 di SUN dengan status Hadir). Tapi itu menuntut Sales membuka pasien satu
per satu untuk tahu ada yang bermasalah. Persis pekerjaan yang halaman ini seharusnya
hapuskan.

### Yang diminta diubah

**Ganti kartu "Keluar dari cabang ini" menjadi "Pasien kami di cabang lain"** — dari
arsip menjadi papan pantau.

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│ ⇄  Pasien kami di cabang lain                                              [ 2 ]  │
│    Masih pasien Bintaro. Cabang tujuan yang menagih — pantau supaya tidak         │
│    terlantar di sana.                                                            │
├──────────────────────────────────────────────────────────────────────────────────┤
│ PASIEN        DI CABANG   KONTROL TERAKHIR  JADWAL BERIKUT  KEHADIRAN  PENAGIH  ›│
│ Raffi R.      SUN         14 Des · Hadir    12 Jan          2/2        Sunter   ›│
│ B-2506-0218   sejak 6 Jan                   Menunggu        sejak pindah         │
│ ─────────────────────────────────────────────────────────────────────────────────│
│▌Eka Rulianti  PAM         8 Nov · Hadir     —               1/1        Pamulang ›│
│ B-2203-1180   sejak 8 Jan                   Belum dijadwal  sejak pindah         │
│                                             71 hari lalu                         │
│                                             ⚠ Ingatkan Pamulang                  │
└──────────────────────────────────────────────────────────────────────────────────┘
```

Perubahan yang diminta:

1. **Buang `opacity: .72`.** Meredupkannya bertentangan langsung dengan maksudnya. Ini
   kartu yang harus dibaca, bukan dilewati.
2. **Ganti judul dan subjudul** seperti di atas. Kata "Keluar" membuatnya terasa selesai;
   pasien ini belum selesai.
3. **Tambah empat kolom kemajuan**: `KONTROL TERAKHIR` (tanggal + hadir/tidak) ·
   `JADWAL BERIKUT` (tanggal + chip status, atau "Belum dijadwal" + umur hari) ·
   `KEHADIRAN sejak pindah` · `PENAGIH`.
4. **Tanda bahaya.** Kalau di cabang tujuan pasien **terlewat >14 hari** atau **belum
   dijadwalkan >30 hari**, beri baris itu garis tepi kiri merah dan tombol
   **`Ingatkan <Cabang>`**. Itu yang membuat kartu ini berguna, bukan sekadar informatif.
   Tombolnya cukup mencatat pengingat + membuka WhatsApp ke admin cabang tujuan — jangan
   rancang alur persetujuan.
5. **Kolom `DI CABANG` menyebut sejak kapan** (`SUN · sejak 6 Jan`), bukan cuma nama
   cabang.
6. **Pertahankan chevron** — drawer detail sudah benar dan tetap jadi jalan melihat
   riwayat penuh.

**Efek berantai yang perlu ikut diubah:**

- Kartu ringkas ke-4 `Transfer bulan ini · 2 masuk · 2 keluar` → ubah sub-barisnya jadi
  **`2 masuk · 2 di cabang lain · 1 perlu diingatkan`**. Angka terakhir itu yang
  sebenarnya menuntut tindakan.
- Badge pada tab **Transfer Cabang** sekarang menghitung masuk + keluar. Ubah supaya
  menghitung **hanya yang menuntut tindakan**: pasien masuk yang belum dijadwalkan +
  pasien di cabang lain yang perlu diingatkan. Badge yang menghitung arsip melatih orang
  mengabaikan badge.

---

## 2. Enam titik ambigu pada desain sistem transfer

### 2.1 Transfer bukan entitas — ia cuma turunan

Di prototipe, transfer **disimpulkan** dengan membandingkan cabang dua kontrol berurutan:

```js
if (k.cab === br)                         → masuk
else if (prev.cab === br || c.pb === br)  → keluar
```

Akibatnya tidak ada tempat menyimpan: **kapan** transfer disepakati (beda dari tanggal
kontrol), **kenapa**, **siapa** yang memutuskan, dan **apakah sudah dikonfirmasi cabang
tujuan**. Hitungan "bulan ini" juga jadi rapuh karena bersandar pada tanggal kontrol,
bukan tanggal transfer.

**Diminta:** perlakukan transfer sebagai **kejadian yang dicatat**, bukan selisih yang
dihitung. Untuk desain, artinya tiap transfer punya barisnya sendiri dengan tanggal,
alasan, jenis, dan pencatat — dan itu yang dirender, bukan hasil perbandingan.

### 2.2 Dua peristiwa berbeda dipakaikan satu mekanisme

Aturan 7 dan 8 di README menyatakan dua hal yang sebenarnya berbeda waktu dan maknanya:

| | Mengubah dropdown CABANG | Mencatat kunjungan di cabang lain |
|---|---|---|
| Kapan | **Sebelum** pasien datang | **Sesudah** pasien datang |
| Artinya | Rencana — "pasien bilang mau kontrol di Sunter" | Fakta — "ternyata pasien datang di Sunter" |
| Bisa salah? | Ya, pasien bisa batal | Tidak, sudah terjadi |

Keduanya kini memicu hasil yang sama persis, dan UI tidak membedakannya. Padahal yang
pertama **boleh dibatalkan tanpa akibat**, yang kedua **tidak boleh diurungkan begitu
saja** — mengurungkannya berarti menghapus fakta kunjungan.

**Diminta:** bedakan di UI.
- Dropdown diubah → chip **`Akan kontrol di SUN`** (rencana, mudah diurungkan).
- Kunjungan tercatat di cabang lain → chip **`Transfer → SUN`** (fakta). `Urungkan` di
  sini hanya boleh mengembalikan kepemilikan, **bukan** menghapus kunjungannya, dan
  tooltipnya harus mengatakan itu.

### 2.3 Tidak ada transfer sementara — padahal Sales menyebutnya

Jawaban Anisa, pertanyaan 7 di dokumen tanya-jawab:

> *"…berarti bintaro yang harus menghubunginya, **kecuali pasien itu mau balik lagi ke
> cinere**."*

Kalimat terakhir itu adalah **transfer sementara**, dan sekarang tidak ada cara
menyatakannya. Pasien yang cuma sekali kontrol di Sunter karena sedang di sana lalu balik
ke Bintaro akan tercatat sebagai pindah permanen, dan Bintaro kehilangan kepemilikannya
tanpa alasan.

**Diminta:** saat mentransfer, Sales memilih salah satu:

| Pilihan | Artinya |
|---|---|
| **Pindah tetap** | Cabang tujuan yang menagih, seterusnya |
| **Kontrol sekali di sana** | Cabang tujuan melayani kontrol ini saja; kepemilikan **kembali otomatis** ke cabang asal setelah kontrol itu ditutup |

Default: **Pindah tetap** — itu kasus yang paling sering. Pilihan kedua cukup berupa
kotak centang di lembar transfer, dengan kalimat penjelas.

### 2.4 Transfer hanya bisa didorong, tidak bisa ditarik

Dropdown CABANG ada di baris tab List, dan tab List hanya memuat pasien milik cabang
aktif. Artinya **hanya cabang pemilik yang bisa mentransfer**.

Kejadian nyatanya sering terbalik: pasien muncul di Sunter, dan **Sunter** yang pertama
tahu. Sekarang Sunter tidak punya cara menarik pasien itu — harus menelepon Bintaro dan
minta didorong. Itu persis koordinasi lewat WhatsApp yang sedang kita hapus
(*"selama ini manual, di cek satu satu lewat WA atau koordinasi sama sales lain"*).

**Diminta:** tambah jalan tarik. Di tab Transfer Cabang, kartu **"Masuk ke cabang ini"**
dapat tombol **`+ Tarik pasien dari cabang lain`** — membuka pencarian pasien ortho
lintas cabang, lalu mencatat transfer dengan alasan. Cabang asal melihatnya muncul di
kartu "Pasien kami di cabang lain" dengan penanda *baru*.

### 2.5 Alasan transfer tidak pernah ditanyakan

Tidak ada tempat mencatat **kenapa** pasien pindah. Padahal brief konsultan menyisakan
satu teka-teki yang hanya bisa dijawab kalau alasannya dicatat (lubang 6.8):

> *"Sheet Kalideres menandai 46 Pindah Kontrol, tetapi tak satu pun pasiennya berkontrol
> terakhir di cabang lain. Mungkin angka itu kumulatif bertahun-tahun, atau 'pindah' di
> sana berarti keluar dari klinik."*

**Diminta:** satu dropdown wajib di lembar transfer:
`Pasien minta pindah` · `Dokternya pindah cabang` · `Cabang tujuan lebih dekat` ·
`Pasien pindah domisili` · `Lainnya (isi)`.

Ini juga menutup lubang 6.3 (**"Done" mencampur perawatan tuntas dengan kehilangan
pelanggan**) dari sisi lain: kalau alasannya `pasien pindah domisili`, itu bukan transfer
antar cabang melainkan calon kehilangan pelanggan.

### 2.6 "Cabang asal" punya dua arti yang bercampur

Kode memakai `prev.cab === br || c.pb === br` — jadi baris "keluar" muncul di **cabang
yang memegang sebelumnya** *dan* di **cabang tempat behel dipasang**. Untuk pasien yang
sudah berpindah dua kali, ia akan tampil di dua daftar cabang sekaligus, dan tidak ada
yang menjelaskan kenapa.

**Diminta:** tetapkan satu arti dan tampilkan dua peran itu berbeda.
- **Cabang pasang** — permanen, tidak pernah berubah. Ini "cabang asal" dalam brief.
  Selalu melihat pasiennya di kartu "Pasien kami di cabang lain".
- **Cabang sebelumnya** — hanya relevan untuk `Urungkan`, dan hanya sampai kontrol
  berikutnya ditutup. Sesudah itu baris transfer masuk riwayat dan `Urungkan` hilang.

Kalau keduanya kebetulan cabang yang sama, tampilkan satu baris, bukan dua.

---

## 3. Perbaikan tampilan yang diminta

### 3.1 Header drawer masih bertabrakan — belum diperbaiki

README Revisi 2 poin 7 menyatakan: *"Drawer header re-spaced — long names no longer
collide with the WA link or the doctor's title."* **Tidak terbukti di screenshot.**

Terlihat di **tiga** screenshot:

| Screenshot | Yang bertabrakan |
|---|---|
| `06-transfer-cabang.png` | "Dewi Kartika Sari" menimpa `B-2006-1177` dan `3-2245-2781`; "drg. Rahmah" tertimpa baris di bawahnya |
| `07-pasien-ortho-daftar.png` | sama |
| `09-detail-drawer.png` | "Septiana" menimpa `0896-5656-9938`; "drg. Vega" tertimpa; "Pasang 12 Okt '25 di BIN" menimpa "berjalan 1 tahun 3 bulan" |

Nama pasien Indonesia tiga-empat kata itu biasa. **Diminta:** header drawer disusun
sebagai baris bertumpuk yang jelas terpisah, bukan elemen mengambang yang saling menimpa:

```
TRACKING PASIEN ORTHO › DEWI KARTIKA SARI              [✕]

Dewi Kartika Sari                        [ kini kontrol di SUN ]
📞 0896-5656-9938  ·  WhatsApp
B-2006-1177 · Metal · Keduanya · drg. Rahmah
Pasang 4 Jun '25 di BIN · berjalan 1 tahun 7 bulan

[ ✓ Tandai Selesai (bracket dilepas) ]  [ Ubah data pemasangan ]
```

Empat baris, tinggi tetap, nama boleh dipotong dengan elipsis kalau melebihi dua baris.

### 3.2 Tiga screenshot tidak bisa dibaca

`06-transfer-cabang.png`, `07-pasien-ortho-daftar.png`, dan `09-detail-drawer.png`
diambil **dengan drawer detail terbuka menutupi tab yang sedang didokumentasikan**. Untuk
06 dan 07 ini fatal — justru kolom-kolom yang perlu dinilai tertutup.

**Diminta:** ambil ulang 06 dan 07 **tanpa drawer**. Drawer cukup didokumentasikan di 09.

### 3.3 Dua hal kecil

- Kartu ringkas ke-2 berbunyi `1.1% dari aktif · 2 lewat >14 hari` sementara angka
  besarnya `6`, dan header halaman menyebut `542 pasien ortho aktif`. Persentasenya
  dihitung terhadap 542 (angka produksi) sedangkan pembilangnya dari 22 pasien contoh.
  **Diminta:** pakai satu basis. Kalau data contoh 22 pasien, header juga harus bilang 22.
- Tombol `Jadwalkan kontrol #3` dan `Simpan & buat jadwal #1` teksnya terpotong dua baris
  di dalam tombol (`09`, `11`). **Diminta:** lebarkan tombol atau pendekkan teks jadi
  `Jadwalkan kontrol` saja — nomornya sudah tertulis di kalimat penjelas di sebelahnya.

---

## 4. Yang sudah benar — jangan diubah

Supaya revisi ini tidak merusak yang sudah jadi:

- **Antrean penjadwalan bertingkat** (`02`). Tiga kelompok, lencana `N kontrol dalam
  6 bulan`, satu tombol `Jadwalkan kontrol`. Ini bagian terkuat dari seluruh handoff.
- **Pemisah transfer di dalam matriks kontrol** (`09`) — `⇄ transfer ke Sunter · 15 Nov`
  di antara baris. Riwayat perpindahan terbaca sebagai cerita. Pertahankan persis.
- **Sambungan dua arah ke modul Reservasi**, termasuk chip status di header halaman dan
  pembatalan yang balik jadi chip merah + tombol `Buat Reservasi` yang muncul lagi.
- **Chip identitas rekam medis** dan peringatan amber saat pasien belum tertaut.
- **Semua token warna, tipografi, radius, dan bayangan.** Tidak ada yang perlu disentuh.
- **Tangga status satu tombol** `Catat Follow-up → Buat Reservasi → Catat Kunjungan →
  Jadwalkan berikutnya`.

---

## 5. Ringkasan permintaan

| # | Permintaan | Berat |
|---|---|---|
| 1 | Kartu "Keluar" → **"Pasien kami di cabang lain"** dengan 4 kolom kemajuan + tanda bahaya + tombol `Ingatkan <Cabang>` | besar |
| 2 | Buang `opacity:.72` dari kartu itu | kecil |
| 3 | Kartu ringkas ke-4 dan badge tab hanya menghitung yang **menuntut tindakan** | kecil |
| 4 | Bedakan **rencana** (`Akan kontrol di SUN`) dari **fakta** (`Transfer → SUN`) | sedang |
| 5 | Tambah pilihan **Pindah tetap / Kontrol sekali di sana** di lembar transfer | sedang |
| 6 | Tambah dropdown **alasan transfer** (wajib) | kecil |
| 7 | Tambah **`+ Tarik pasien dari cabang lain`** di kartu "Masuk" | sedang |
| 8 | Pisahkan arti **cabang pasang** vs **cabang sebelumnya** | sedang |
| 9 | Perbaiki **tabrakan teks header drawer** — sungguhan kali ini | kecil |
| 10 | Ambil ulang screenshot **06 dan 07 tanpa drawer** | kecil |
| 11 | Satukan basis angka contoh (22 vs 542) | kecil |
| 12 | Perbaiki teks tombol yang terpotong dua baris | kecil |

Nomor 1 adalah alasan revisi ini ada. Sisanya bisa menyusul kalau waktunya sempit —
kecuali nomor 9, yang membuat handoff terlihat kurang teliti padahal isinya rapi.

---

## Lampiran — catatan untuk implementasi

Bukan untuk ditempel ke alat desain.

- **Transfer butuh tabelnya sendiri**, mis. `ortho_transfer`: `ortho_case_id`,
  `from_place_id`, `to_place_id`, `transfer_date`, `reason`, `is_temporary`,
  `return_after_control_id`, `created_by`. Tanpa ini, permintaan 4, 5, 6, dan 8 tidak
  punya tempat menyimpan.
- **`ortho_case.owner_place_id`** tetap jadi cabang penagih; transfer sementara berarti
  `owner_place_id` dikembalikan otomatis saat kontrol yang ditunjuk ditutup.
- **Kartu "Pasien kami di cabang lain" membaca lintas cabang** — ini satu-satunya tempat
  di halaman ini yang menembus kunci cabang. Jadi kuncinya bukan "cabang aktif saja",
  melainkan "cabang aktif **plus** pasien yang cabang pasangnya = cabang aktif". Perlu
  dinyatakan tegas saat membangun penyaringnya, kalau tidak akan tertutup filter cabang
  dan kartunya selalu kosong.
- **Tombol `Ingatkan <Cabang>`** cukup mencatat baris pengingat + membuka WhatsApp ke
  nomor admin cabang tujuan. Jangan bangun alur notifikasi dalam aplikasi — belum ada
  jalurnya, dan brief sudah menyatakan pengiriman pesan otomatis di luar lingkup.
