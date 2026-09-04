# Katalog Modul — Data Center SweetTreats

> **Langkah (a): pemetaan untuk DIKOREKSI Ekky, belum dibangun.** Disusun chat
> HUB 4 Sep 2026 dari **dua sumber hidup di prod**: 87 node `access_path`
> (menu = apa yang bisa dibuka orang) dan sapuan `MIN(created_at)` seluruh
> tabel (kapan data pertama masuk). Bukan dari ingatan.
>
> ⚠️ **Batas yang harus dibaca dulu:** *"data pertama masuk"* **bukan** *"kapan
> dibangun"* — data impor membawa tanggal aslinya. Klaster Omnicare misalnya
> serempak 20 Mei karena itu hari sinkronnya dinyalakan. Tanggal di bawah
> adalah **paling awal yang bisa dibuktikan**, bukan tanggal rilis resmi.

## 0. Cara membaca

**Dua tingkat saja.** **Modul** = yang disebut departemen kalau ditanya
*"sistem apa yang kalian punya?"*. **Fitur** = halaman yang bisa dibuka
(punya `link` di `access_path`).

**Dua sumbu.** **Pemilik** = satu departemen yang memutuskan & meneken UAT.
**Jenis** = macam pekerjaannya: `Operasional` · `Laporan & Dashboard` ·
`Integrasi` · `Platform`. Sumbu kedua inilah yang membuat Executive bisa
menyaring semua laporan lintas departemen tanpa ada modul bertuan dua.

**Status.** `Hidup` · `Sedang dibangun` · `Direncanakan`.

---

## 1. SALES

| Modul | Jenis | Status | Sejak | Fitur |
|---|---|---|---|---|
| **Reservasi** | Operasional | Hidup | **7 Apr 2026** | Reservation · Reservasi Cabang · Reservasi Pasien (Schedule) |
| **Master Jadwal & Layanan** | Operasional | Hidup | **11 Mar 2026** | Branch · Doctor · Doctor Service |
| **Laporan Penjualan** | Laporan & Dashboard | Hidup | — | `report` · Rekonsiliasi |
| **Dashboard Sales** | Laporan & Dashboard | Hidup | Jun 2026 | `sales-dashboard` |
| **Keluhan** | Operasional | Hidup | **20 Jun 2026** | Keluhan → Sales |
| **Pasien Baru / Lead Cekat AI** | Operasional | Hidup | **27 Agu 2026** | form publik per cabang · tab Pasien Cekat AI |

*Bukti data:* `raw_cus_reservation` 6.039 (7 Apr) · `raw_doctor_schedule` 105
(11 Mar) · `raw_doc_service_detail` 148 (11 Mar) · `sys_complaint` 3 (20 Jun).

---

## 2. MARKETING

| Modul | Jenis | Status | Sejak | Fitur |
|---|---|---|---|---|
| **Konten** | Operasional | Hidup | **7 Mei 2026** | Review Konten · Konten Pillar · EP Konten · Breakdown Konten · Campaign Framework · Keyword |
| **Referral & Member** | Operasional | Hidup | **1 Apr 2026** | Affiliate · KOL · KOC · Sweet Mates · Kode Referral |
| **Promo** | Operasional | Hidup | **20 Jun 2026** | Promo (Marketing) |
| **Dashboard & Laporan Marketing** | Laporan & Dashboard | Hidup | — | Dashboard Marketing · Klaim Komisi · Report Referral |

*Bukti data:* `ep_konten_pillar` 22 (7 Mei) · `ep_konten` 214 (12 Mei) ·
`raw_promo` 32 (1 Apr) · `raw_affiliate` 133 (15 Mei) · `marketing_promo` 16 (20 Jun).

⚠️ **Dashboard Marketing perlu diperiksa**: memori mencatat *"atribusi promo/
komisi KOSONG di prod"* dan arahnya sempat dipivot ke akuisisi pasien. Statusnya
mungkin **bukan** Hidup penuh — tolong koreksi.

---

## 3. FINANCE

| Modul | Jenis | Status | Sejak | Fitur |
|---|---|---|---|---|
| **Petty Cash** | Operasional | Hidup | **4 Jun 2026** | Pettycash · Petty Cash Monitoring · Operational Expense |
| **ROP (Pengajuan Pengeluaran)** | Operasional | Hidup | **6 Agu 2026** | Pengajuan · Antrian Pengajuan · Rekap Pengeluaran · Lembar Bayar |
| **Komisi Dokter** | Laporan & Dashboard | Hidup | **29 Agu 2026** | `komisi-dokter` |
| **Integrasi Accurate** | Integrasi | Hidup *(push dijeda)* | **12 Jun 2026** | tanpa menu — `accurate:push-daily` |
| **Laporan Keuangan** | Laporan & Dashboard | **Direncanakan** | — | belum ada |

*Bukti data:* `petty_cash_periods` 23 (4 Jun) · `petty_cash_transactions` 359
(5 Jun) · `rop_requests` 89 (6 Agu) · `accurate_push_log` 3.118 (12 Jun).

⏰ **Accurate: push DIJEDA sejak 28 Agu**, terakhir ter-push 27 Agu. Finance
sudah ACC lanjut — jalankan sekali manual `--days=12` sebelum menyerahkan ke cron.

---

## 4. OPERASIONAL / HR

| Modul | Jenis | Status | Sejak | Fitur |
|---|---|---|---|---|
| **Absensi** | Operasional | Hidup | **4–5 Agu 2026** | Upload Data · Kehadiran · Mapping Karyawan · Aturan Kerja · Cuti · Lembar Gaji · Periode Gaji · Rekap Persetujuan |
| **Pengawasan Absen** | Operasional | Hidup | **5 Agu 2026** | `pengawasan` · WiFi Cabang |
| **Checklist Operasional** | Operasional | Hidup | **1 Jul 2026** | Dashboard · Setup Tugas · Review & Approval · Checklist Saya |
| **Persetujuan** | Platform | Hidup | Agu 2026 | Inbox Persetujuan |
| **Akun Saya** | Platform | Hidup | Agu 2026 | Absensi Saya |
| **Slip Gaji & Insentif** | Operasional | **Direncanakan** | — | belum ada |
| **Rolling / Penempatan global** | Operasional | **Direncanakan** | — | kini masih di dalam Checklist |

*Bukti data:* `att_work_rule` 6 + `att_user_rule` 41 (4 Agu) · `att_scan` 1.045
(5 Agu) · `att_hp_bukti` 1.045 (5 Agu) · `sys_checklist_division` 3 (1 Jul) ·
`sys_checklist_submission` 403 (4 Agu) · `sys_ac_unit` 10 (8 Agu).

---

## 5. DATA PASIEN & REKAM MEDIS

| Modul | Jenis | Status | Sejak | Fitur |
|---|---|---|---|---|
| **Data Pasien** | Operasional | Hidup | **5 Apr 2026** | Pasien (`rawcustomer`) |
| **Integrasi Omnicare** | Integrasi | Hidup | **20 Mei 2026** | tanpa menu — sinkron harian |
| **Kontak & Percakapan** | Operasional | Hidup | **18 Feb 2026** | tanpa menu — `raw_contact`, `raw_chat` |

*Bukti data:* `raw_customer` **15.656** (5 Apr) · `omnicare_sales_detail`
**146.542** · `omnicare_medical_record` **51.020** · `omnicare_commission`
**88.238** (7 Jul) · `raw_contact` 12.231 (**18 Feb — data tertua di sistem**) ·
`raw_chat` 34.313 (17 Mar).

⚠️ **Rekam medis Omnicare tidak seragam** — tindakan 100%, tapi keluhan 27% dan
diagnosa 39%; `doc_id` berisi **nama** di rekam medis tapi **angka** di komisi.
Jangan menjanjikan fitur rekam medis tanpa memeriksa ini dulu.

---

## 6. EXECUTIVE *(laporan yang lintas-departemen sifatnya)*

| Modul | Jenis | Status | Sejak | Fitur |
|---|---|---|---|---|
| **Executive Report** | Laporan & Dashboard | Hidup | — | `report/executive` |
| **Ringkasan** | Laporan & Dashboard | Hidup | — | `dashboard` |
| **Target vs Pencapaian** | Laporan & Dashboard | **Sedang dibangun** | — | lulus dev, prod belum |

---

## 7. IT

| Modul | Jenis | Status | Sejak | Fitur |
|---|---|---|---|---|
| **Pusat Akun** | Platform | Hidup *(sebagian warisan)* | **27 Apr 2022** | User · Role · Akses · Setting · Kesehatan · WiFi Cabang |
| **IT Request Tracker** | Platform | **Sedang dibangun** | — | live di dev, prod belum |
| **Katalog Modul** | Platform | **Direncanakan** | — | dokumen ini |

⚠️ **Temuan arkeologi:** `Data Center`, `User`, `Role` ber-UUID **v1** yang
menyimpan stempel waktu **27 April 2022, 15:51–15:55** — empat tahun sebelum
kerjaan Sweet Treats. Kerangka platform ini **warisan**, bukan dibangun dari
nol. Bagian mana yang warisan dan mana yang kita tulis ulang belum dipisahkan.

---

## 8. STOK / INVENTORY

| Modul | Jenis | Status |
|---|---|---|
| **Inventory / Stok** | Operasional | **Direncanakan** |
| **COGS — kendali harga & HPP** | Operasional | **Direncanakan** |

Departemennya ada di rencana sebelum ada di perangkat lunak — dan itu memang
harus terlihat.

---

## 9. Yang perlu KAMU koreksi

1. **Pasien milik Sales atau Data Pasien?** Kutaruh di **Data Pasien**; Sales
   memakainya untuk reservasi. Kalau yang mengelola datanya Sales, pindahkan.
2. **Keluhan** kutaruh di Sales karena node menunya berbunyi *Keluhan → Sales*.
   Betul, atau ini milik Operasional?
3. **Kontak & Percakapan** (12.231 kontak, 34.313 chat, sejak Feb) — **data
   tertua di sistem** dan tak punya menu sama sekali. Ini apa, dan siapa
   pemiliknya? Aku menebak Data Pasien.
4. **Dashboard Marketing** benar-benar Hidup, atau kerangka tanpa isi?
5. **Persetujuan & Akun Saya** kutandai `Platform` karena melayani semua
   departemen, bukan HR saja. Setuju?

## 10. Temuan sampingan: menu yang menyesatkan

Lima node Marketing **tak punya link dan tak punya anak** — `Dashboard` ·
`Referral` · `Editorial Plan` · `KOL & KOC Dashboard` — sisa struktur lama yang
tidak dibuang saat Marketing ditata ulang jadi tiga bagian baru (`Konten`,
`Referral & Member`, `Dashboard & Laporan`). Di layar mereka terlihat seperti
menu yang bisa diklik tapi tak menuju ke mana pun.

Ini utang yang sudah tercatat sejak audit menu 5 Agustus (*"menu tanpa route"*)
dan masih ada. **Bukan bagian katalog** — ini tiket S sendiri.

---

## 11. Ringkasan angka

| Departemen | Modul hidup | Sedang dibangun | Direncanakan |
|---|---|---|---|
| Sales | 6 | 0 | 0 |
| Marketing | 4 | 0 | 0 |
| Finance | 4 | 0 | 1 |
| Operasional / HR | 5 | 0 | 2 |
| Data Pasien & Rekam Medis | 3 | 0 | 0 |
| Executive | 2 | 1 | 0 |
| IT | 1 | 1 | 1 |
| Stok / Inventory | 0 | 0 | 2 |
| **TOTAL** | **25** | **2** | **6** |

**25 modul hidup · 33 entri katalog.** Bandingkan dengan **±60 fitur** (daun
`access_path`) dan **26 tiket aktif** di tracker — tiga angka yang menjawab tiga
pertanyaan berbeda, dan sekarang ketiganya bisa dilihat.
