# Design Brief — Sistem Rekonsiliasi Absensi & Penggajian

> **Untuk claude.ai/design.** Brief ini **murni fungsional**: menjelaskan data, fungsi, layar,
> dan form. **Tidak menyebut apa pun soal tampilan** (warna, font, tata letak, komponen) — biar
> desain bebas dari bias. Bentuk visual sepenuhnya keputusan desainer. Deskripsi juga
> **agnostik-struktur**: menyebut "informasi apa" dan "aksi apa", bukan "tabel/kartu/tombol".

---

## 1. Tujuan sistem

Sebuah klinik gigi punya **5 cabang**. Setiap karyawan mencatat kehadiran lewat **mesin sidik
jari** di cabangnya, dan mengajukan **cuti / izin / libur / lembur / WFH** lewat form. Selama ini
tim finance mencocokkan dua sumber itu **manual** — mengekspor data mesin, mencocokkan
nama/tanggal/jam satu per satu, cek silang ke form, lalu mengejar orangnya lewat chat kalau ragu.
Prosesnya makan waktu berhari-hari sehingga cut-off penggajian mundur.

Sistem ini menyatukan **data mesin (pengamatan)** dan **klaim manusia (form)** di satu tempat,
menyandingkannya otomatis, dan **menonjolkan yang perlu diperiksa** — sehingga manusia tinggal
memutuskan, bukan mengumpulkan data. Hasil akhir: finance percaya angkanya, kerja manual hilang,
cut-off penggajian kembali cepat.

**Inti nilainya:** dulu tidak ada tempat di mana klaim dan scan diadu; finance jadi satu-satunya
"sendi" antara dua sistem. Sistem ini menjadi tempat itu.

---

## 2. Aktor & konteks pemakaian

| Aktor | Perangkat | Yang dilakukan |
|---|---|---|
| **Karyawan** | **HP** | Melihat absensinya sendiri; mengajukan permohonan |
| **Atasan / Approver** | **HP** | Meninjau & memutuskan (setujui/tolak) permohonan bawahannya |
| **Finance / Admin** | **Desktop** | Unggah data mesin; petakan karyawan; tinjau rekap; tutup periode; ekspor payroll |

Konteks perangkat ini penting: sisi karyawan & approver dipakai **sambil berdiri, di HP,
sela-sela kerja**; sisi finance dipakai **fokus, di depan komputer, saat rekonsiliasi bulanan**.

---

## 3. Alur end-to-end (bagaimana data mengalir)

```
Finance unggah file mesin ──► kumpulan SCAN (cap waktu mentah)
      │
      ├─ No.ID belum dikenal? ──► Finance PETAKAN ke karyawan (sekali) ──┐
      │                                                                   ▼
      └──────────────────────────────► scan jadi HARI-ABSEN per orang (hadir/telat/alpa/…)

Karyawan AJUKAN (cuti/lembur/dll) ──► naik RANTAI PERSETUJUAN ──► Approver SETUJUI/TOLAK
      │                                                                   │
      │  (khusus lembur) klaim otomatis DISANDINGKAN dengan scan ─────────┤
      ▼                                                                   ▼
Permohonan disetujui ──► masuk REKAP finance (dengan hasil sanding-scan)
      │
      └─ Finance TUTUP periode (angka beku) ──► EKSPOR payroll
```

---

## 4. Entitas data (istilah awam)

- **Scan** — satu cap waktu ketika seseorang menempel sidik jari di mesin sebuah cabang. Mentah,
  hanya "ada badan di mesin ini, jam sekian".
- **Hari-absen** — catatan harian per orang yang **dihitung** sistem dari scan + aturan + form:
  status (**hadir / telat / alpa / cuti / izin / libur / WFH**), jam scan pertama & terakhir,
  menit keterlambatan, menit lembur yang disetujui.
- **Pengajuan** — permohonan dari karyawan: jenis, rentang tanggal, rentang jam, alasan, foto
  bukti, dan status persetujuannya.
- **Sanding-scan** — hasil membandingkan klaim lembur dengan yang **didukung** data scan:
  - **nyambung** — klaim ≤ yang didukung scan (aman),
  - **selisih** — klaim **melebihi** dukungan scan (perlu diperiksa),
  - **tidak bisa dicek** — tak ada scan pembanding (mis. WFH, atau scan belum diunggah).
  Ini **informasi untuk pengambil keputusan, bukan vonis** — sistem tak pernah menyetujui/menolak
  sendiri.
- **Rantai persetujuan** — permohonan naik dari pengaju melewati satu atau lebih approver.
  Sebagian jenis butuh **dua lapis** (cuti, lembur), sebagian cukup **satu lapis** (izin, libur,
  WFH).
- **Periode gaji** — rentang tanggal penggajian yang **ditutup** finance (mengunci angka di rentang
  itu), lalu **diekspor**. Rentangnya bisa non-kalender (mis. tanggal 26 sampai 25).
- **Pemetaan karyawan** — menghubungkan **No.ID di mesin** ke karyawan sungguhan. Selama belum
  terpetakan, scan orang itu tak terhitung (absennya tak muncul, klaimnya tak bisa disanding).

**Catatan penting soal identitas:** satu No.ID hanya unik **dalam satu mesin/cabang** — nomor yang
sama di cabang berbeda adalah orang berbeda. Satu orang bisa punya No.ID di beberapa cabang (mis.
perawat yang menutup cabang lain), dan absennya digabung lintas cabang.

---

## 5. Layar

### Sisi Karyawan (HP)

**5.1 Absensi Saya**
Seseorang melihat absensinya sendiri untuk **satu bulan**:
- Untuk **tiap hari**: statusnya (hadir/telat/alpa/cuti/izin/libur/WFH), jam masuk–pulang, dan
  menit telat bila ada.
- **Ringkasan bulanan**: jumlah tiap status (mis. sekian hadir, sekian telat, sekian alpa), total
  menit telat, total jam lembur disetujui.
- Bisa **berpindah antar bulan**.
- **Riwayat permohonan miliknya** beserta jenis, tanggal, dan status (menunggu / disetujui /
  ditolak).
- **Aksi:** membuka form pengajuan baru.

**5.2 Ajukan Permohonan**
Form mengajukan satu dari lima jenis (rincian field di §6). Setelah dikirim, permohonan masuk ke
rantai persetujuan; pengaju melihatnya muncul sebagai "menunggu".

### Sisi Approver (HP)

**5.3 Inbox Persetujuan**
Approver melihat permohonan yang **menunggu keputusannya**. Untuk tiap permohonan ditampilkan:
pengaju, jenis, rentang tanggal/jam, alasan, foto bukti, tingkat/lapis di rantai, dan **khusus
lembur** hasil sanding-scan (nyambung/selisih/tidak-bisa-dicek).
- **Aksi:** **setujui** atau **tolak**, dengan **catatan opsional**.
- Ada juga **ringkas riwayat** permohonan yang sudah ia tindak.
- Bila ia bukan approver untuk permohonan apa pun, inbox-nya kosong (dan itu wajar).

### Sisi Finance / Admin (desktop)

**5.4 Upload Data Mesin**
Finance mengunggah **file mentah hasil export mesin sidik jari** (tanpa diedit). Karena file tidak
menyimpan nama cabang, ia **memilih cabang asal** file, memilih filenya, lalu mengirim. Sistem
mem-parse otomatis (ada beberapa format mesin) dan menyimpan scan-nya.
- Ditampilkan **riwayat unggahan**: cabang, periode yang tercakup, berapa scan **baru /
  duplikat / belum-terpetakan**, dan waktunya.
- Mengunggah ulang file yang sama aman (scan yang sudah ada tidak digandakan).

**5.5 Mapping Karyawan**
Menampilkan **No.ID mesin yang belum terhubung** ke karyawan (selama belum, scan-nya tak
terhitung). Untuk tiap No.ID: cabang, nomornya, nama-di-mesin bila diketahui, **jumlah scan**, dan
**rentang tanggal** scan-nya. Yang paling baru diprioritaskan agar mudah ditemukan.
- **Aksi:** **menghubungkan** ke seorang karyawan (setelah itu scan lamanya otomatis tersambung),
  atau **menandai diabaikan** (mis. dokter, enrollment tes, atau orang yang sudah keluar).
- Bisa **disaring per cabang**.

**5.6 Rekap Persetujuan**
Finance meninjau **semua permohonan yang disetujui pada satu periode, lintas cabang**. Untuk tiap
permohonan: pengaju, cabang, jenis, rentang tanggal/jam; **khusus lembur** juga jam yang diklaim
dan **hasil sanding-scan beserta selisihnya** (berapa yang diklaim vs berapa yang didukung scan);
alasan; foto.
- **Ringkasan**: jumlah per jenis, total jam lembur disetujui, dan **berapa banyak klaim lembur
  yang perlu dicek** (selisih).
- **Saringan:** per bulan, per cabang, dan **"hanya yang perlu dicek"**.
- **Tujuan:** pemeriksaan silang cepat sebelum menutup periode. Yang perlu perhatian (selisih)
  harus **mudah dibedakan** dari yang aman.

**5.7 Periode Gaji**
Finance mengelola periode penggajian.
- **Membuat** periode: rentang tanggal (boleh non-kalender, mis. 26–25) + nama opsional.
- Melihat **daftar periode** beserta status (terbuka / ditutup), siapa menutup, dan kapan.
- **Menutup** periode: **mengunci** absensi di rentang itu — perhitungan ulang tak lagi mengubah
  angka yang sudah di-sign-off.
- **Mengekspor** berkas payroll: per orang, berisi jumlah hadir/telat/alpa/cuti, total menit telat,
  menit lembur disetujui, dan sejenisnya.

---

## 6. Form (field & aturan)

### 6.1 Ajukan Permohonan (karyawan)
| Field | Aturan |
|---|---|
| **Jenis** | salah satu: cuti · izin · libur/tukar-hari · lembur · WFH (wajib) |
| **Sub-jenis lembur** | "per-jam (hari kerja)" atau "tambah-hari (hari libur)" — hanya bila jenis = lembur |
| **Tanggal mulai** & **tanggal selesai** | wajib; selesai ≥ mulai |
| **Jam mulai** & **jam selesai** | opsional; relevan untuk lembur & izin sebagian; selesai > mulai |
| **Alasan** | teks, wajib (approver membacanya untuk memutuskan) |
| **Foto bukti** | gambar, opsional; jadi **satu-satunya bukti** untuk lembur/WFH tanpa scan |

- **Nama & cabang pengaju otomatis dari login** (tidak diketik).
- Untuk lembur, **durasi klaim dihitung** dari rentang jam.
- Aturan khusus yang terasa saat mengisi: WFH punya **kuota 2× per bulan**; pengajuan ke-3 di
  bulan sama ditolak otomatis (beri tahu pengaju dengan jelas).

### 6.2 Tindakan lain (aksi ringkas, bukan form panjang)
- **Persetujuan** — setujui / tolak + catatan opsional.
- **Upload Data** — cabang (wajib, dipilih manual) + file (wajib).
- **Mapping** — pilih karyawan untuk dihubungkan, atau tandai diabaikan.
- **Buat Periode** — nama (opsional) + tanggal mulai + tanggal selesai.

---

## 7. Konsep kunci yang harus TERASA di desain

1. **Dua sumber bertemu** — data mesin vs klaim manusia. Nilai utamanya: sistem **menonjolkan yang
   tidak cocok** agar mudah diperiksa. Yang "perlu dicek" jangan tenggelam di antara yang aman.
2. **Sistem memberi informasi, manusia memutuskan** — sanding-scan bukan vonis; approver/finance
   yang menentukan. Desain harus mendukung *keputusan cepat oleh manusia*, bukan menampilkan
   "benar/salah" otomatis.
3. **Alur berjenjang** — permohonan naik dari pengaju ke approver; approver perlu paham cepat
   "ini permohonan siapa, minta apa, konteks scan-nya bagaimana".
4. **Tutup periode = angka beku** — supaya payroll bisa dipercaya; status terbuka vs terkunci
   harus jelas.
5. **Beda sisi, beda mode** — sisi karyawan/approver ringkas & cepat (HP, sela kerja); sisi finance
   padat-informasi & fokus (desktop, rekonsiliasi).

---

## 8. Ringkasan layar (referensi cepat)

| # | Layar | Aktor | Perangkat | Inti |
|---|---|---|---|---|
| 5.1 | Absensi Saya | Karyawan | HP | Lihat absen sendiri per bulan + riwayat pengajuan |
| 5.2 | Ajukan Permohonan | Karyawan | HP | Form 5 jenis pengajuan |
| 5.3 | Inbox Persetujuan | Approver | HP | Tinjau & setujui/tolak, lihat sanding-scan |
| 5.4 | Upload Data Mesin | Finance | Desktop | Unggah export mesin per cabang + riwayat |
| 5.5 | Mapping Karyawan | Finance | Desktop | Hubungkan No.ID mesin → karyawan |
| 5.6 | Rekap Persetujuan | Finance | Desktop | Tinjau approved + sanding-scan per periode |
| 5.7 | Periode Gaji | Finance | Desktop | Buat/tutup periode + ekspor payroll |
