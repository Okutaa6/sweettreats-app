# Data untuk Deck — Kondisi Modul, Go Live, & Requirement

> Disusun chat HUB **5 Sep 2026** untuk dipakai chat lain menyusun deck.
> **Berdiri sendiri** — chat itu tak punya konteks proyek, jadi semua istilah
> dijelaskan di §0.
>
> **Asal angka, supaya bisa dipertanggungjawabkan:**
> · **Katalog & tiket** → basis data **dev** (`it_modul`, `it_ticket`), 5 Sep.
> · **Angka pemakaian** → basis data **PRODUKSI**, diukur 4 Sep (7 & 30 hari).
> · **Tanggal "sejak"** → `MIN(created_at)` tabel terkait di prod + `access_path`.
>
> ⚠️ **JANGAN memakai angka pemakaian dari dev.** Di dev semuanya nol karena dev
> tidak dipakai kerja nyata — kalau dipakai, setiap modul akan terlihat mati.
> Angka di dokumen ini semuanya dari produksi.

---

## 0. Istilah — baca ini dulu

| Istilah | Arti |
|---|---|
| **Modul** | Yang disebut departemen kalau ditanya *"sistem apa yang kalian punya?"* — mis. Absensi, Petty Cash |
| **Fitur** | Halaman yang bisa dibuka di dalam modul. **Diturunkan otomatis dari menu**, tidak diketik manusia |
| **Departemen pemilik** | Satu departemen yang memutuskan & meneken persetujuan. Tak pernah dua |
| **Jenis modul** | `Operasional` · `Laporan & Dashboard` · `Integrasi` · `Platform`. Sumbu terpisah dari pemilik, supaya Executive bisa melihat semua laporan lintas departemen |
| **Status** | `Hidup` (dipakai orang) · `Sedang dibangun` · `Direncanakan`. **Bukan persen** — modul hidup tetap bisa punya utang |
| **Tiket** | Satu potong pekerjaan yang punya awal & akhir. Modul tidak berakhir; tiket berakhir |
| **Fase tiket** | Requirement → Triase → Antre → Analisis → Development → Uji → UAT → **Go Live** → Evaluasi |
| **Jenjang S/M/L** | Ukuran tiket. S lewat 2 fase, M lewat 5, L lewat 7 |
| **Utang modul** | Tiket terbuka yang menempel ke modul itu. Bukan daftar terpisah |

---

## 1. Angka utama

| | |
|---|---|
| **37** | entri katalog |
| **25** | modul **hidup** — dipakai orang hari ini |
| **3** | sedang dibangun · **9** direncanakan |
| **54** | fitur (halaman) yang dimiliki modul |
| **8** | departemen |
| **35** | tiket di papan · **34 bertaut modul**, 1 belum |
| **4** | tiket di fase **Go Live** (evaluasi rilis) |
| **9** | tiket di fase **Requirement/Triase** |
| **4** | tiket **ditunda** dengan alasan tertulis |

Tiga angka menjawab tiga pertanyaan berbeda: **37** = apa yang kami punya ·
**54** = berapa halaman bisa dibuka · **35** = apa yang sedang terjadi.

---

## 2. KATALOG MODUL — kondisi & arah ke depan

Angka `dipakai` = jumlah transaksi/baris **7 hari terakhir di produksi** (4 Sep).
Tanda `—` berarti **belum terukur** (tak ada tabel yang dipetakan), berbeda dari
`0` yang berarti **tidak dipakai**.

### 2.1 SALES — 6 hidup · 1 dibangun · 1 rencana

| Modul | Jenis | Status | Sejak | Fitur | Dipakai 7hr | Arah ke depan |
|---|---|---|---|---|---|---|
| **Reservasi** | Operasional | Hidup | 7 Apr 2026 | 2 | **534** | Modal detail pasien (tiket S di Development); sinkron dua arah ke Omnicare menunggu antrean |
| **Master Jadwal & Layanan** | Operasional | Hidup | 11 Mar 2026 | 3 | — | Stabil, tak ada tiket terbuka |
| **Kontak & Percakapan** | Operasional | Hidup | **18 Feb 2026** | 0 *(tanpa menu)* | 184 | Data tertua di sistem — 12.231 kontak, 34.313 chat |
| **Pasien Baru / Lead Cekat AI** | Operasional | Hidup | 27 Agu 2026 | 0 *(tanpa menu)* | 184 | **Di Go Live** — belum ada angka follow-up & konversi |
| **Laporan Penjualan** | Laporan & Dashboard | Hidup | — | 1 | — | Masuk cakupan redesign laporan |
| **Dashboard Sales** | Laporan & Dashboard | Hidup | Jun 2026 | 1 | — | Masuk cakupan redesign laporan |
| **Akun Dokter** | Operasional | **Dibangun** | — | 0 | — | Zona dokter (Hari Ini · Jadwal · Pasien Saya) — **lulus dev, prod belum** |
| **ORTHO — tracking & retensi** | Operasional | **Rencana** | — | 0 | — | Diajukan Yovan, menunggu triase |

### 2.2 MARKETING — 4 hidup

| Modul | Jenis | Status | Sejak | Fitur | Dipakai 7hr | Arah ke depan |
|---|---|---|---|---|---|---|
| **Konten** | Operasional | Hidup | 7 Mei 2026 | 6 | 26 | Stabil |
| **Referral & Member** | Operasional | Hidup | 1 Apr 2026 | 5 | — | Stabil |
| **Promo** | Operasional | Hidup | 20 Jun 2026 | 1 | — | Stabil |
| **Dashboard & Laporan Marketing** | Laporan & Dashboard | Hidup | — | 3 | — | Masuk cakupan redesign laporan |

### 2.3 FINANCE — 4 hidup · 1 rencana

| Modul | Jenis | Status | Sejak | Fitur | Dipakai 7hr | Arah ke depan |
|---|---|---|---|---|---|---|
| **Petty Cash** | Operasional | Hidup | 4 Jun 2026 | 1 | **96** | **Di Go Live** — 3 utang: SQL pensiun kategori Pinjaman, Setoran Awal tanpa jejak audit, cut-over 2 wallet |
| **ROP** | Operasional | Hidup | 6 Agu 2026 | 1 | **33** | **Di Go Live** — adopsi meledak (2 → 89 pengajuan), diuji apakah alurnya menahan |
| **Komisi Dokter** | Laporan & Dashboard | Hidup | 29 Agu 2026 | 1 | — | Penyesuaian 70% + baris manual **sedang di Analisis** |
| **Integrasi Accurate** | Integrasi | Hidup *(push dijeda)* | 12 Jun 2026 | 0 *(tanpa menu)* | **0** | ⏰ Push berhenti sejak 28 Agu; Finance sudah ACC lanjut |
| **Laporan Keuangan** | Laporan & Dashboard | **Rencana** | — | 0 | — | Belum diajukan |

### 2.4 OPERASIONAL / HR — 5 hidup · 2 rencana

| Modul | Jenis | Status | Sejak | Fitur | Dipakai 7hr | Arah ke depan |
|---|---|---|---|---|---|---|
| **Absensi** | Operasional | Hidup | 4 Agu 2026 | **8** | **305** | **Di Evaluasi** — jatah cuti di Analisis; aturan kerja per role/cabang & rekonsiliasi P207 menunggu triase |
| **Checklist Operasional** | Operasional | Hidup | 1 Jul 2026 | 4 | **117** | **Di Go Live** — diuji keadilan pengecualian & buka-kembali |
| **Persetujuan** | Operasional | Hidup | Agu 2026 | 1 | 67 | Alur persetujuan + delegasi **lulus dev, prod belum** |
| **Akun Saya** | Operasional | Hidup | Agu 2026 | 4 | — | Tumbuh bersama zona dokter |
| **Pengawasan Absen** | Operasional | Hidup | 5 Agu 2026 | 1 | — | Cegat IP kembar (tiket S di Development) |
| **Slip Gaji & Insentif** | Operasional | **Rencana** | — | 0 | — | **Ditunda** sampai komisi 70% & jatah cuti selesai |
| **Rolling / Penempatan global** | Operasional | **Rencana** | — | 0 | — | Melepas fitur rolling dari Checklist jadi sumber penempatan tunggal |

### 2.5 DATA PASIEN & REKAM MEDIS — 2 hidup

| Modul | Jenis | Status | Sejak | Fitur | Dipakai 7hr | Arah ke depan |
|---|---|---|---|---|---|---|
| **Data Pasien** | Operasional | Hidup | 5 Apr 2026 | 1 | — | 15.656 pasien |
| **Integrasi Omnicare** | Integrasi | Hidup | 20 Mei 2026 | 0 *(tanpa menu)* | **843** | Reservasi & pasien dua arah **di antrean**; pembayaran **ditunda menunggu vendor** |

### 2.6 EXECUTIVE — 2 hidup · 1 dibangun

| Modul | Jenis | Status | Fitur | Arah ke depan |
|---|---|---|---|---|
| **Executive Report** | Laporan & Dashboard | Hidup | 1 | Redesign dashboard & laporan menunggu triase |
| **Ringkasan** | Laporan & Dashboard | Hidup | 1 | — |
| **Target vs Pencapaian** | Laporan & Dashboard | **Dibangun** | 0 | Lulus dev, prod belum |

### 2.7 IT — 2 hidup · 1 dibangun · 3 rencana

| Modul | Jenis | Status | Sejak | Fitur | Dipakai 7hr | Arah ke depan |
|---|---|---|---|---|---|---|
| **Pusat Akun** | Platform | Hidup | **27 Apr 2022** | 6 | — | Pengembangan Pusat Akun & security hardening **di Analisis** |
| **Keluhan Sistem** | Platform | Hidup | 20 Jun 2026 | 1 | **0** | ⚠️ 3 catatan seumur hidup, terakhir 28 Juni — kemungkinan tergantikan IT Tracker |
| **IT Request Tracker** | Platform | **Dibangun** | — | 1 | — | Live dev, **prod belum**; Fase 3 di antrean |
| **Katalog Modul** | Platform | **Rencana** | — | 0 | — | Live dev bersama tracker |
| **Project Tracker** | Platform | **Rencana** | — | 0 | — | **Ditunda** — prioritas IT Tracker dulu |
| **Notifikasi** | Platform | **Rencana** | — | 0 | — | WA reservasi + hub internal, di antrean |

⚠️ **Pusat Akun bertanggal 27 April 2022** — empat tahun sebelum kerjaan Sweet
Treats. Kerangka platformnya **warisan**, bukan dibangun dari nol.

### 2.8 STOK / INVENTORY — 0 hidup · 2 rencana

| Modul | Status | Arah ke depan |
|---|---|---|
| **COGS — kendali harga & HPP** | **Rencana** | Diajukan Finance, menunggu triase |
| **Inventory / Stok** | **Rencana** | Menunggu triase; bergantung COGS |

Departemennya **ada di rencana sebelum ada di perangkat lunak** — dan itu memang
harus terlihat.

---

## 3. GO LIVE — empat rilis yang sedang diawasi

Fase Go Live = rilis sudah jalan, sedang dinilai sebelum ditutup. Tiap tiket
punya **evaluator bernama** dan **pertanyaan nyata** — bukan formalitas.

| Tiket | Modul | Evaluator | Angka nyata | Pertanyaannya |
|---|---|---|---|---|
| **IT-2026-032** | Petty Cash | Finance (Della) | 96 transaksi/7hr · **10 pencatat aktif** | Kepercayaan pencatat sudah pulih? 3 utang belum ditutup |
| **IT-2026-033** | Checklist Operasional | Ops (Anisa) | 117 pengisian harian + 17 mingguan/7hr | Pengecualian & "Minta Buka Kembali" sudah terasa adil? |
| **IT-2026-034** | Pasien Baru / Lead Cekat AI | Sales (Yovan) | 184 kontak masuk/7hr | **Belum ada angka**: berapa di-follow-up, berapa jadi reservasi |
| **IT-2026-035** | ROP | Finance (Della) | 89 total · **33 dalam 7hr** | Adopsi meledak dari 2 pengajuan — alurnya menahan? |

**Plus satu sudah lebih jauh:** **IT-2026-001 Absensi** — di **Evaluasi** sejak
27 Agu, evaluator HR. Terukur 305 scan + 91 pengajuan + 67 persetujuan per 7
hari — dipakai harian.

### Dua keyakinan lama yang patah oleh pengukuran

- **ROP bukan lagi "adopsi tipis".** Catatan internal menyebut *2 pengajuan per
  11 Agustus*; terukur 4 Sep: **89 total, 33 dalam 7 hari terakhir**.
- **Pencatat petty cash sudah kembali.** Sempat ada kekhawatiran satu cabang
  berhenti mencatat dan kembali ke buku manual. Terukur: **10 orang mencatat
  dalam 30 hari**, yang bersangkutan 70 transaksi (terakhir 3 Sep).

---

## 4. REQUIREMENT — sembilan yang sedang diminta

Tiket di fase **Triase** = sudah diajukan, menunggu IT menetapkan ukuran &
prioritas. Ini **antrean permintaan yang belum dikerjakan**.

| Tiket | Yang diminta | Pemohon | Departemen |
|---|---|---|---|
| **IT-2026-013** | ORTHO — tracking & retensi pasien ortho untuk Sales | Yovan | Sales |
| **IT-2026-014** | Aturan kerja per role per cabang | HR | Operasional / HR |
| **IT-2026-015** | Rolling global — penempatan lepas dari Checklist | HR | Operasional / HR |
| **IT-2026-016** | Rekonsiliasi absensi mesin P207 (5 cabang) | HR | Operasional / HR |
| **IT-2026-017** | Redesign dashboard & laporan untuk stakeholder | Robi | Executive |
| **IT-2026-028** | COGS — kendali harga, HPP, dan promo | Finance | Stok / Inventory |
| **IT-2026-029** | Inventory / stok — menggantikan lembar kerja staf | Ops | Stok / Inventory |
| **IT-2026-031** | Customer survey | HR | *(belum bertaut modul)* |

### Sedang dianalisis (satu tingkat lebih maju)

| Tiket | Yang dirancang | Modul |
|---|---|---|
| **IT-2026-009** | Penyesuaian komisi dokter 70% + baris manual | Komisi Dokter |
| **IT-2026-010** | Jatah cuti per kontrak 12 bulan | Absensi |
| **IT-2026-018** | Pusat Akun — user management + detektor kegagalan senyap | Pusat Akun |
| **IT-2026-019** | Security hardening — brute-force, throttle, TrustProxies | Pusat Akun |

### Menunggu giliran (Antre)

Reservasi & pasien dua arah ke Omnicare · IT Tracker Fase 3 · Notifikasi WA
reservasi · Hub notifikasi internal.

### Ditunda — dengan alasan tertulis

| Tiket | Alasannya |
|---|---|
| **IT-2026-012** Pembayaran → Omnicare | **Endpoint tulis pembayaran belum ada dari vendor.** Spek konkret sudah dikirim Sep 2026; dibuka lagi saat vendor menjawab |
| **IT-2026-021** Integrasi Accurate penuh | Finance sedang mapping ulang di Accurate |
| **IT-2026-030** Slip gaji & insentif | IT fokus IT Tracker & Finance; dibuka sesudah komisi 70% dan jatah cuti selesai |
| **IT-2026-020** Project Tracker | Prioritas IT Tracker dulu; disambungkan lewat Fase 3 |

---

## 5. Yang perlu keputusan

1. **Accurate** — push berhenti sejak 28 Agu, terakhir terkirim 27 Agu. Finance
   sudah menyetujui lanjut. Perlu dijalankan sekali manual dengan jendela lebar
   sebelum diserahkan ke penjadwal harian.
2. **Keluhan Sistem** — 3 catatan seumur hidup, nol dalam 30 hari. Masih perlu,
   atau memang sudah tergantikan IT Request Tracker?
3. **IT Request Tracker & Katalog Modul** — hidup di dev, **belum naik produksi**.
4. **Lima hal lain lulus dev tapi belum prod**: Alur Persetujuan + delegasi ·
   Zona akun dokter · Target vs Pencapaian · cegat IP kembar · koreksi checklist.

---

## 6. Batas data ini — sebutkan kalau ditanya

- **"Sejak" bukan tanggal rilis resmi**, melainkan tanggal data pertama masuk.
  Data impor membawa tanggal aslinya; klaster Omnicare serempak 20 Mei karena
  itu hari sinkronnya dinyalakan, bukan hari fiturnya jadi.
- **Angka pemakaian dari produksi 4 Sep**, jendela 7 hari. Modul bertanda `—`
  belum punya tabel yang dipetakan — **bukan berarti tidak dipakai**.
- **Katalog & tiket dibaca dari dev**, karena tracker belum naik produksi.
  Isinya kerjaan nyata, tapi papan itu sendiri belum dipakai harian.
- **54 fitur** dihitung per **halaman unik**, bukan per label menu — satu
  halaman bisa muncul beberapa kali di menu dengan nama berbeda.
