# Permintaan Revisi 3 — Tracking Pasien Ortho

> ⚠ **SEBAGIAN DOKUMEN INI SUDAH TIDAK BERLAKU (2 Okt 2026).** Dibiarkan apa
> adanya dengan sengaja: ia menjelaskan KENAPA rancangannya pernah begitu, dan
> penjelasan itu yang hilang kalau ditulis ulang.
>
> Yang sudah dicabut:
> - **Tombol "Konfirmasi jadwal" tidak ada lagi.** Premis dokumen ini — tanggal
>   kontrol berikutnya adalah kesepakatan dokter dan pasien di kursi perawatan —
>   dikoreksi Ikhsan 1 Okt: SALES yang menentukan, dokter hanya menyarankan
>   lisan. Tangga tombolnya kini dua anak: Catat Follow-up → Buat Reservasi.
> - **Kolom Jadwal tidak lagi menampilkan tanggal**, melainkan perkiraan
>   ("Pekan ini", "± Nov", "Sudah waktunya").
>
> Duduk perkaranya di `docs/handoff/balasan-ortho-hub-2026-10-02.md`.
> Langkah deploy di `docs/deploy/deploy-ortho.md`.

**Untuk:** tim desain · **Dari:** Ekky · **Tanggal:** 17 September 2026
**Menanggapi:** handoff Revisi 2 (termasuk pembaruan nama cabang ditulis penuh)

> Dokumen ini **menggantikan** `revisi-3-tracking-ortho-transfer.md` yang beredar
> sebelumnya — isinya sudah diserap ke sini. Berkas itu boleh dibuang.

---

## Ringkasan

Revisi 2 sudah kuat. Antrean penjadwalan bertingkat (`02`) persis seperti yang
dibayangkan, sambungan dua arah ke modul Reservasi jalan penuh, nomor rekam medis sudah
benar, dan nama cabang ditulis penuh — itu perbaikan yang tepat, kolomnya memang harus
melebar.

Revisi 3 mengubah **tiga hal mendasar**, bukan poles:

| # | Perubahan | Kenapa |
|---|---|---|
| 1 | **Kolom Datang terisi sendiri dari rekam medis.** Aksi "Catat Kunjungan" **dihapus** | Sales cuma boleh mengerjakan dua hal: follow-up dan buat reservasi |
| 2 | **Baris tidak boleh hilang** setelah kunjungan tercatat — ia berubah di tempat jadi usulan jadwal berikutnya | Sekarang barisnya lenyap tanpa penjelasan |
| 3 | **Tab cabang di dalam List** menggantikan tab Transfer Cabang | Cabang asal harus bisa melihat progress pasiennya di cabang lain |

Plus satu perampingan menyeluruh: **10 kolom jadi 7, 4 tab jadi 3, 4 kartu jadi 3.**

---

## 1. Kunjungan terisi sendiri — "Catat Kunjungan" dihapus

### Kenapa

Ini kebutuhan nomor satu di brief konsultan, dan satu-satunya yang sampai sekarang belum
tercapai:

> **Sales berhenti mengisi apa pun yang datanya sudah ada di rekam medis.**
> *Ukurannya: tak ada lagi kolom bulan yang diketik manusia.*

Tanggal kunjungan **sudah ada** di rekam medis — 125.220 baris, ditarik tiap malam.
Meminta Sales mengetiknya ulang adalah pekerjaan ganda yang persis ingin dihapus.

### Alurnya jadi begini

Sales hanya punya **dua** pekerjaan. Sisanya jalan sendiri.

```
  Jadwal ada
      │
      ▼
  ① Sales: Follow-up            ← WhatsApp, lalu catat
      │
      ▼
  ② Sales: Buat Reservasi       ← drawer reservasi, pilih dokter & jam
      │
      ▼
  ─ ─ semalam, dari rekam medis ─ ─
      │
      ▼
  Kolom Datang terisi sendiri · status jadi Hadir · kontrol ditutup
      │
      ▼
  Sistem mengusulkan jadwal berikutnya (+30 hari)
      │
      ▼
  ③ Sales: Konfirmasi jadwal    ← satu klik, atau geser tanggalnya
```

### Yang diminta diubah

1. **Hapus tombol `Catat Kunjungan`** dari mana pun — baris List, baris yang dibuka,
   dan matriks di drawer.
2. **Hapus lembar "Catat Kunjungan"** beserta peringatan lintas cabangnya. Perpindahan
   cabang sekarang ditangani cara lain (bagian 3).
3. **Tangga status jadi lebih pendek**:
   `Menunggu` → `Sudah di-FU` → `Ada reservasi` → **`Hadir`** (otomatis) /
   **`Tidak hadir`** (otomatis kalau lewat 14 hari tanpa kunjungan).
4. **Tombol tindakan hanya punya tiga bunyi**: `Catat Follow-up` · `Buat Reservasi` ·
   `Konfirmasi jadwal`. Tidak ada lagi yang keempat.
5. **Kolom Datang diberi penanda asal-usul** di drawer — ikon kecil + tooltip
   *"Terisi otomatis dari rekam medis, 21 Jan 02:00"*. Sales harus tahu angka itu bukan
   ketikan siapa pun, supaya percaya.
6. **Kalau pasien datang tanpa reservasi** (langsung datang), kunjungannya tetap masuk
   otomatis dan menutup jadwal yang terdekat. Baris itu diberi chip kecil
   **`Datang tanpa reservasi`** — jangan diperlakukan sebagai kesalahan.

### Satu hal yang tetap manual, dan memang harus

**Tanggal kontrol berikutnya tidak ada di rekam medis.** Itu kesepakatan antara dokter
dan pasien di kursi perawatan, tidak pernah tercatat di mana pun. Karena itu jadwal
berikutnya tetap lahir dari keputusan manusia — tapi sistem **mengusulkan** tanggalnya
supaya Sales tidak perlu mengetik. Lihat bagian 2.

---

## 2. Baris tidak boleh hilang

### Yang terjadi sekarang

Di prototipe, daftar hanya memuat kontrol yang **masih terbuka**:

```js
rowsFor(){
  ...
  if (last.visit || last.att === false) return;   // ← kontrol yang sudah ditutup dibuang
  ...
}
```

Jadi begitu kunjungan tercatat, barisnya **langsung keluar dari tab List** dan pindah
diam-diam ke antrean kartu ke-3 ("Belum punya jadwal"). Tidak ada yang memberi tahu.
Toast-nya cuma berbunyi *"Kunjungan Septiana dicatat 20 Jan"* — tanpa menyebut ke mana
barisnya pergi.

Di Revisi 1 masih ada kelompok `Sudah ditutup — perlu jadwal berikutnya` di dalam List
yang menampung baris itu. Kelompok itu dibuang di Revisi 2, tapi penggantinya ada di tab
lain. Dari sudut pandang Sales, barisnya **menghilang**.

### Yang diminta

**Baris berubah di tempat, tidak pindah ke mana-mana.** Begitu kunjungan masuk:

```
SEBELUM (kontrol #4 masih terbuka)
 Septiana        12 Jan   13 Jan   15 Jan   Ada reservasi   [ Buat Reservasi ]

SESUDAH kunjungan masuk otomatis semalam
 Septiana        12 Feb   X        X        Usulan jadwal   [ Konfirmasi jadwal ]
 B-2011-3014               ┈┈┈┈┈┈
 kontrol ke-5    ↑ tanggal usulan, bergaris putus-putus, bisa diklik untuk diubah
                 ✓ kontrol #4 selesai — hadir 20 Jan
```

- Baris tetap di posisi yang sama, pasien yang sama. Yang berganti adalah **nomor
  kontrolnya**.
- Tanggal usulan = kunjungan terakhir + 30 hari, digambar **bergaris bawah putus-putus**
  supaya jelas ini belum disepakati. Diklik → kalender kecil untuk menggeser.
- Chip status baru: **`Usulan jadwal`** — `#A9866A` / `#F1E9DF`, sama seperti Menunggu
  tapi teksnya beda. (Ini chip ke-10; boleh menggantikan slot chip lama yang tidak
  terpakai lagi, lihat bagian 4.)
- Di bawah baris, satu baris kecil hijau: **`✓ kontrol #4 selesai — hadir 20 Jan`**.
  Inilah yang menggantikan toast. Hilang sendiri setelah jadwal dikonfirmasi.
- Tombolnya **`Konfirmasi jadwal`**. Satu klik, selesai. Setelah dikonfirmasi, garis
  putus-putus jadi garis biasa, chip berubah jadi `Menunggu`, dan siklusnya berulang.

Aturan **"jadwal lahir satu per satu"** tetap utuh — manusia tetap yang menyetujui tiap
jadwal, satu per satu. Yang dihapus cuma pengetikan tanggalnya.

### Akibat lanjutan yang perlu ikut diubah

- Kartu ke-3 **"Belum punya jadwal"** sekarang hanya berisi pasien hasil penyemaian yang
  memang belum pernah dijadwalkan — bukan lagi tempat sampah baris yang barusan ditutup.
  Isinya menyusut ke satu makna. Subjudulnya jadi:
  *"Belum pernah dijadwalkan sejak data ditarik."*
- Kelompok **"Baru selesai kontrol — tinggal dijadwalkan"** di antrean itu **dihapus** —
  kasusnya sekarang ditangani langsung di baris List sebagai usulan. Antrean tinggal dua
  tingkat: **Hilang lebih dari 6 bulan** dan **Jarang kontrol**.

---

## 3. Tab cabang di dalam List — menggantikan tab Transfer Cabang

### Yang diminta

Kalau pasien Bintaro dipindahkan ke Sunter, Bintaro harus bisa **melihat apa yang terjadi
pada pasiennya di Sunter** — bukan sekadar tahu dia pindah. Caranya: **deret tab cabang
di dalam tab List.**

```
  List  │  Kalender  │  Pasien Ortho
  ▔▔▔▔

  ┌──────────────┬────────────┬────────────┐
  │  Bintaro 12  │  Sunter 2  │  Cinere 1  │      ← tab cabang
  └──▔▔▔▔▔▔▔▔▔▔──┴────────────┴────────────┘
     cabang sendiri   pasien kita di cabang lain — HANYA LIHAT
```

- **Tab pertama = cabang sendiri.** Penuh aksi, persis seperti sekarang.
- **Tab berikutnya muncul hanya kalau ada pasien kita di sana.** Tidak ada pasien di
  Kalideres → tab Kalideres tidak ada. Deretnya tidak pernah lebih dari lima.
- **Tab cabang lain bersifat lihat-saja.** Tidak ada dropdown cabang, tidak ada tombol
  tindakan, tidak ada kotak centang. Hanya data dan chevron ke drawer detail.

### Isi tab cabang lain

Kolomnya **berbeda** dari tab sendiri, karena pertanyaannya berbeda. Di tab sendiri
pertanyaannya *"apa yang harus saya kerjakan"*; di tab cabang lain *"pasien saya
baik-baik saja tidak di sana"*.

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│  2 pasien Bintaro sedang dipegang Sunter · hanya bisa dilihat                     │
├──────────────────────────────────────────────────────────────────────────────────┤
│ PASIEN          PINDAH SEJAK  KONTROL TERAKHIR  JADWAL BERIKUT   KEHADIRAN       ›│
│ Raffi Ramadhany  6 Jan '27    14 Des · Hadir    12 Feb           2/2 sejak pindah ›│
│ B-2506-0218                                     Menunggu                          │
│ ──────────────────────────────────────────────────────────────────────────────────│
│▌Eka Rulianti     8 Jan '27    8 Nov · Hadir     —                1/1 sejak pindah ›│
│ B-2203-1180                                     Belum dijadwal                    │
│                                                 71 hari lalu     [ Ingatkan Sunter ]│
└──────────────────────────────────────────────────────────────────────────────────┘
```

- **Satu-satunya tombol** yang boleh ada di tab ini: **`Ingatkan <Cabang>`**, dan hanya
  muncul kalau pasien itu **terlewat lebih dari 14 hari** atau **belum dijadwalkan lebih
  dari 30 hari** di sana. Kalau semua baik-baik saja, tidak ada tombol sama sekali —
  halamannya tenang, dan itu memang kabar baik.
- Tombol itu cukup membuka WhatsApp ke admin cabang tujuan dan mencatat bahwa pengingat
  sudah dikirim. **Jangan** rancang alur persetujuan atau notifikasi dalam aplikasi.
- Baris yang bermasalah diberi garis tepi kiri merah, sama seperti di tab sendiri.
- **Jangan diredupkan.** Kartu "Keluar" sekarang memakai `opacity:.72` — itu bertentangan
  dengan maksudnya. Ini halaman yang harus dibaca, bukan dilewati.

### Tab "Transfer Cabang" dihapus

Setelah tab cabang ada, tab Transfer Cabang tidak punya pekerjaan lagi:

| Isi lama | Pindah ke mana |
|---|---|
| "Keluar dari cabang ini" | Tab cabang tujuan (Sunter, Cinere, …) |
| "Masuk ke cabang ini" | Tab cabang sendiri, barisnya diberi chip **`Baru masuk dari Cinere`** yang hilang setelah dijadwalkan |

Empat tab jadi **tiga**: `List` · `Kalender` · `Pasien Ortho`.

### Transfer dilakukan dari mana

Dropdown CABANG dikeluarkan dari tabel (bagian 4). Penggantinya: di **baris yang dibuka**
dan di **drawer detail**, ada satu tombol **`Pindahkan ke cabang lain`** yang membuka
lembar kecil:

```
┌────────────────────────────────────────┐
│ Pindahkan Septiana                     │
│                                        │
│ CABANG TUJUAN                          │
│ [ Sunter                          ▾ ]  │
│                                        │
│ ALASAN                                 │
│ [ Pasien minta pindah             ▾ ]  │
│   · Pasien minta pindah                │
│   · Dokternya pindah cabang            │
│   · Cabang tujuan lebih dekat          │
│   · Pasien pindah domisili             │
│   · Lainnya                            │
│                                        │
│ [ ] Kontrol sekali saja di sana,       │
│     setelah itu kembali ke Bintaro     │
│                                        │
│ Sunter yang akan menghubungi pasien    │
│ untuk kontrol berikutnya. Bintaro      │
│ tetap bisa melihat perkembangannya.    │
│                                        │
│          [ Batal ]  [ Pindahkan ]      │
└────────────────────────────────────────┘
```

Tiga hal yang selama ini hilang dan sekarang ada tempatnya:

1. **Alasan transfer.** Wajib. Ini yang akhirnya bisa menjawab teka-teki di brief
   konsultan — Kalideres menandai 46 "Pindah Kontrol" tapi tidak satu pun pasiennya
   berkontrol terakhir di cabang lain. Tanpa alasan tercatat, itu tidak akan pernah
   terjawab.
2. **Transfer sementara.** Anisa menyebutnya sendiri: *"…berarti bintaro yang harus
   menghubunginya, **kecuali pasien itu mau balik lagi ke cinere**."* Kotak centang itu
   mewujudkannya — kepemilikan kembali otomatis setelah satu kontrol.
3. **Kalimat yang menjelaskan akibatnya** sebelum tombol ditekan, bukan sesudah.

Tetap ada **`Urungkan`** di toast, seperti aksi tulis lainnya.

---

## 4. Perampingan — buang yang tidak dipakai

Permintaannya: sebersih mungkin tapi tetap informatif. Ini yang saya nilai bisa dibuang
tanpa ada yang kehilangan informasi.

### Tabel List: 10 kolom → 7

| Kolom | Nasib | Alasan |
|---|---|---|
| PASIEN | tetap | — |
| DOKTER | **dilebur** ke baris kecil di bawah nama: `B-2011-3014 · Metal · drg. Vega · kontrol ke-5` | Satu kolom penuh untuk satu nama pendek itu boros; tetap terbaca di tempat barunya |
| JADWAL | tetap | — |
| FOLLOW-UP | tetap | — |
| RESERVASI | tetap | — |
| **DATANG** | **dibuang dari tabel** | Setelah perubahan 1 dan 2, baris yang tampil **selalu** kontrol yang belum dikunjungi — isinya `X` seratus persen. Kolom ini tidak pernah memuat apa pun. Riwayat kunjungan tetap lengkap di matriks drawer |
| **CABANG** | **dibuang dari tabel**, jadi tombol `Pindahkan ke cabang lain` di baris terbuka & drawer | Di tab Bintaro isinya selalu "Bintaro" — kolom yang isinya selalu sama tidak memberi informasi. Sebagai dropdown ia juga terlalu mudah tersenggol untuk aksi sebesar memindahkan pasien |
| STATUS | tetap | Jangkar untuk memindai cepat |
| TINDAKAN BERIKUTNYA | tetap | — |
| chevron | tetap | — |

Lebar minimum turun dari **1190px ke ±900px** — muat di laptop 13 inci tanpa geser
mendatar. Itu perbedaan nyata buat Sales yang tidak semuanya pakai monitor besar.

### Kartu ringkas: 4 → 3

| Kartu | Nasib |
|---|---|
| Jadwal minggu ini | tetap |
| Terlewat | tetap |
| Belum punya jadwal | tetap, subjudul diperjelas (bagian 2) |
| **Transfer bulan ini** | **dibuang** — angkanya sudah terbaca di deret tab cabang, dan di sana lebih berguna karena bisa langsung diklik |

### Yang juga bisa dibuang

- **Chip `Cabang: Bintaro` di baris "Filter aktif"** — setelah ada tab cabang, chip
  bergembok itu mengulang informasi yang sudah jelas dari tab yang aktif.
- **Chip `Modul Reservasi · 4 dari halaman ini` di header** — sembunyikan saat keadaan
  normal. Munculkan **hanya** kalau ada pembatalan (keadaan merah
  `Modul Reservasi · 2 dibatalkan di sana`). Waktu tenang ia cuma hiasan; waktu ada
  masalah ia berharga.
- **Kolom kosong di kartu transfer** (kolom ke-5, lebar 128px, tidak berisi apa pun) —
  hilang sendiri karena kartunya dibuang.
- **Tombol `Ubah data pemasangan`** di header drawer masih stub. Kalau belum akan
  dikerjakan, sembunyikan dulu daripada memancing klik yang tidak menghasilkan apa-apa.

### Yang JANGAN dibuang

Supaya tidak kebablasan: baris kecil di bawah nama pasien, lencana
`N kontrol dalam 6 bulan` di antrean, pemisah `transfer ke Sunter · 15 Nov` di matriks
drawer, dan sub-baris pembanding di kartu ringkas. Keempatnya terlihat seperti detail
kecil, tapi justru itu yang membuat halaman ini bisa dipakai tanpa bertanya.

---

## 5. Screenshot drawer perlu diambil ulang

Di **tiga handoff berturut-turut**, screenshot drawer selalu menunjukkan tulisan
bertumpuk: nama pasien menimpa nomor HP, nama dokter, dan baris "Pasang …".

Saya periksa markup-nya, dan **markup-nya benar** — `display:flex`, `flex-wrap:wrap`,
`row-gap:9px`, `line-height:1.3`, `padding:18px 58px 20px 22px`. Tidak ada yang
diposisikan mutlak, tidak ada margin negatif.

Dugaan kuat: **screenshot diambil saat drawer masih bergerak** —
`animation: drawerIn .26s`. Tangkapan di tengah animasi menghasilkan bayangan ganda yang
terlihat seperti tabrakan. Bukti pendukung: hanya screenshot drawer yang begini;
`01`, `02`, `05` bersih semua, dan semuanya elemen yang tidak beranimasi.

**Diminta:** tunggu animasi selesai sebelum menangkap, lalu pastikan sekali di browser
bahwa memang tidak bertabrakan. Kalau ternyata benar-benar bertabrakan, baru itu bug
layout dan tolong kabari.

Sekalian: **`06` dan `07` diambil ulang tanpa drawer terbuka** — drawer menutupi justru
kolom-kolom yang perlu dinilai di kedua tab itu. Drawer cukup didokumentasikan di `09`.

---

## 6. Yang sudah benar — jangan diubah

- **Antrean penjadwalan bertingkat** (`02`). Bagian terkuat dari seluruh handoff.
  Hanya kelompok ketiga yang dihapus (bagian 2); dua kelompok lainnya tetap persis.
- **Pemisah transfer di dalam matriks kontrol** (`09`) —
  `⇄ transfer ke Sunter · 15 Nov` di antara baris. Riwayat perpindahan terbaca sebagai
  cerita. Pertahankan.
- **Sambungan dua arah ke modul Reservasi**, termasuk pembatalan yang balik jadi chip
  merah dan tombol `Buat Reservasi` yang muncul lagi.
- **Nama cabang ditulis penuh.** Perbaikan yang tepat — jangan kembali ke singkatan.
- **Chip identitas rekam medis** dan peringatan amber saat pasien belum tertaut.
- **Semua token warna, tipografi, radius, bayangan.** Tidak ada yang perlu disentuh.

---

## 7. Ringkasan permintaan

| # | Permintaan | Berat |
|---|---|---|
| 1 | Hapus aksi **Catat Kunjungan**; kolom Datang terisi sendiri dari rekam medis | besar |
| 2 | Baris **berubah di tempat** jadi `Usulan jadwal` + baris kecil `✓ kontrol #4 selesai` | besar |
| 3 | **Tab cabang di dalam List** (lihat-saja) menggantikan tab Transfer Cabang | besar |
| 4 | Lembar **Pindahkan ke cabang lain**: alasan wajib + kotak "kontrol sekali saja" | sedang |
| 5 | Tabel **10 kolom → 7**; buang DATANG dan CABANG, lebur DOKTER | sedang |
| 6 | Kartu ringkas **4 → 3**; buang "Transfer bulan ini" | kecil |
| 7 | Antrean kartu ke-3 tinggal **dua tingkat**; subjudul diperjelas | kecil |
| 8 | Sembunyikan chip `Cabang:` dan chip Modul Reservasi saat keadaan normal | kecil |
| 9 | Ambil ulang screenshot drawer **setelah animasi selesai**; `06` & `07` tanpa drawer | kecil |
| 10 | Sembunyikan tombol stub `Ubah data pemasangan` | kecil |

Nomor 1–3 adalah alasan revisi ini ada. Nomor 5 yang paling terasa bedanya di layar.

---

## Lampiran — catatan implementasi

Bukan untuk ditempel ke alat desain.

**Pengisian kolom Datang otomatis**

- Sumbernya transaksi ortho di cermin rekam medis, ditarik tiap malam pukul 02.00. Aturan
  membaca item hidup di **satu** kelas. ⚠ `Lepas Bracket` (akhir perawatan) **jangan**
  tertukar dengan `Bracket Metal Lepas` (bracket copot lalu dipasang ulang — 1.396 + 175
  baris). Salah baca = ribuan pasien salah ditandai selesai.
- Barang penyerta (`Kawat …`, `Orthokit`, `Ortho Brush`, `Molar Bracket`) sering di
  tanggal yang sama dengan kontrol — **dedup per tanggal**, jangan dihitung kunjungan
  terpisah.
- Pencocokan ke baris jadwal: ambil jadwal terbuka **terdekat** dengan tanggal kunjungan.
  Kalau selisihnya lebih dari 45 hari, tandai `dugaan` dan minta Sales mengonfirmasi —
  jangan menutup jadwal secara diam-diam.
- Kolom `visit_source`, `visit_confidence`, `omnicare_trx_id`, `visit_verified_by` sudah
  disiapkan sejak awal untuk ini — **nol `ALTER TABLE`**.
- **Index `(p_id, trx_date)` di tabel transaksi belum ada** dan harus dipasang sebelum
  fitur ini menyala. Pola yang sama pernah bikin full-scan ~1,8 detik di kolom lain.

**Transfer**

- Perlu tabel sendiri, mis. `ortho_transfer`: `ortho_case_id`, `from_place_id`,
  `to_place_id`, `transfer_date`, `reason`, `is_temporary`, `return_after_control_id`,
  `created_by`. Tanpa ini, alasan dan transfer sementara tidak punya tempat.
- Sekarang transfer cuma **disimpulkan** dari perbandingan cabang dua kontrol berurutan
  (`prev.cab === br || c.pb === br`). Itu sebabnya tidak ada alasan, tidak ada jenis, dan
  hitungan "bulan ini" rapuh.
- `ortho_case.owner_place_id` tetap jadi cabang penagih. Transfer sementara =
  `owner_place_id` dikembalikan otomatis saat kontrol yang ditunjuk ditutup.

**Tab cabang lain**

- Ini satu-satunya tempat di halaman yang **menembus kunci cabang**. Penyaringnya bukan
  "cabang aktif saja", melainkan **"pasien yang cabang pasangnya = cabang aktif, tapi
  cabang penagihnya bukan"**. Kalau ini tidak dinyatakan tegas saat membangun, filter
  cabang akan menelannya dan tabnya selalu kosong.
- Tab ini **lihat-saja di tingkat data**, bukan cuma di tingkat tampilan — jangan
  sediakan endpoint tulis apa pun untuk pasien yang bukan milik cabang aktif, kecuali
  pencatatan pengingat.
