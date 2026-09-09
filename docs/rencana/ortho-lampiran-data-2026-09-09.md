# Lampiran Data — Tracking Pasien Ortho (IT-2026-013)

> Pendamping `docs/brief-desain/brief-ortho-tracking-untuk-rahmat-2026-09-09.md`.
> Semua angka diukur di **Omnicare prod, 9 September 2026**. Tiap angka
> disertai kuerinya supaya bisa diperiksa ulang, bukan dipercaya begitu saja.

---

## 1. Tabel dan kolom yang relevan

Cermin Omnicare ditarik `omnicare:sync` tiap hari 02.00. Alurnya **satu arah**:
Omnicare → Data Center. Kita tidak menulis balik.

### `omnicare_sales_detail` — sumber utama
Tiap baris = satu item yang ditagihkan dalam satu kunjungan.

| kolom | isi | dipakai untuk |
|---|---|---|
| `p_id` | nomor rekam medis, mis. `D-2509-1035` | kunci pasien |
| `p_name` | nama pasien | tampilan |
| `trx_date` | tanggal transaksi | **tanggal kontrol** |
| `item_name` | nama layanan/barang | **jenis kunjungan & jenis bracket** |
| `item_code` | kode item, mis. `26CON178` | pencocokan yang lebih stabil |
| `branch_name` | cabang tempat transaksi | **cabang kunjungan** (kunci lintas cabang) |
| `doctor`, `doc_reff_id` | dokter penangan | opsional |
| `polyclinic` | poli | opsional |
| `trx_id`, `invoice_num` | identitas transaksi | anti-duplikat |

⚠️ Kolom tanggalnya **`trx_date`**, bukan `trans_date`. Salah tebak nama kolom
ini pernah menelan pengingat Accurate diam-diam selama berminggu-minggu.

### `omnicare_patient` — data pasien
`pid`, `full_name`, `dob`, `gender`, `mobile_number`, `whatsapp_number`,
`email`, `home_address`. Diperlukan kalau tindak lanjut mau menampilkan kontak.

### `omnicare_medical_record` — opsional
Berisi `procedures`, `diagnoses`, `doc_id`, `date`. Belum diperlukan untuk
kebutuhan ini, tapi tersedia kalau desain butuh alasan klinis.
⚠️ Kelengkapannya timpang: tindakan terisi ~100%, keluhan 27%, diagnosa 39%.

### Jangan dipakai untuk mencocokkan dokter
Pencocokan **nama** dokter sudah terbukti nyasar ke rekam dokter lain. Kalau
butuh dokter, pakai `doc_id` / `doc_reff_id`.

---

## 2. Item ortho — yang stabil

Item dengan ≥50 baris sepanjang 2025–2026. Inilah yang aman dijadikan aturan.

### Kunjungan kontrol (inti fitur)

| item | baris | rentang |
|---|---|---|
| `Control Metal Braces` | 125.220 (sejak 2023) | 2023-01-03 → 2026-09-08 |
| `Control Metal Braces Spesialis` | 6.978 | 2023-01-04 → 2026-09-08 |
| `Control Signature Ceramic Braces` | 970 | 2023-01-03 → 2026-09-06 |
| `Control Stellar Metal Braces Spesialis` | 636 | 2023-06-28 → 2026-09-06 |
| `Control Signature Sapphire Braces` | 512 | 2023-01-04 → 2026-08-29 |
| `Control Stellar Metal Braces` | 417 | 2023-03-18 → 2026-09-05 |
| `Control Deluxe Metal Braces Spesialis`, `Control Estetik Braces`, dll | 56–125 | 2025–2026 |
| **warisan 2022** `Control Ortho Metal` / `Ceramic` / `Sapphire` | 3.440 / 388 / 285 | hanya 2022 |

**Pola yang bekerja:** `item_name` diawali `Control ` dan mengandung `Braces`
atau `Ortho`. Kueri di bawah memakai `^Control .*(Braces|Ortho)`.

### Jenis bracket — dibaca dari nama item yang sama
`Ceramic` → Ceramic · `Sapphire` → Sapphire · `Metal` → Metal.
Sisanya (`Self Ligating`, `Estetik`) perlu keputusanmu.
⚠️ Anisa: jenis **bisa berubah** di tengah perawatan. Sheet hanya menyimpan
yang terbaru sehingga riwayat lama hilang; Omnicare menyimpan semuanya.

### Akhir perawatan
`Lepas Bracket` (218 sepanjang masa, 124 di 2025–2026).
⚠️ Jangan tertukar dengan `Bracket Metal Lepas` / `Bracket Ceramic Lepas` /
`Bracket Self Ligating Lepas` — itu **bracket yang copot lalu dipasang ulang**,
kejadian di tengah perawatan, bukan akhirnya.

### Barang penyerta (bukan kunjungan)
`Kawat Ortho`, `Kawat Recta`, `Kawat Reverse`, `Kawat Self Ligating`,
`Orthokit`, `Orthokit Gum`, `Ortho Brush Pesitro`, `Molar Bracket`,
`Fix Retainer`, `Fiber Retainer`, `Night Guard Retainer`.
Sering muncul di tanggal yang sama dengan kontrol → **jangan dihitung sebagai
kunjungan terpisah**, atau satu kunjungan akan terhitung dua kali.

---

## 3. ⛔ Pemasangan: ekor panjang tanpa pola

**132 nama item berbeda hanya menghasilkan 1.237 baris** sepanjang 2025–2026 —
hampir semuanya nama promo yang dipakai sekali lalu tak pernah lagi:

```
Opening Cinere - Metal Braces
Cimulang Oktober - Siganture Metal Braces Gp     (perhatikan salah ketik "Siganture")
Payday Pemasangan Signature Metal - Drg. Fri
AGS WAR (Deluxe Metal Braces SP)
JULY - DELUXE METAL BRACES DRG FRI
Happy Weekday - Cicilan 1 Metal Braces SP
SEP WEEKLY - Signature Metal Braces GP
WAR PROMO 8.8 - Signature Metal Braces
Ngabubutreats-Signature Metal Braces Sp
Signature Metal Braces (px an Vabbya)            (nama pasien ikut masuk)
```

Nama promo, nama dokter, nama pasien, bahkan salah ketik ikut masuk. **Tidak
ada daftar tetap yang bisa dicocokkan.** Kode itemnya pun baru tiap promo
(`25PM256`, `26PM048`, `26PM051`, …).

Kemungkinan pendekatan — **keputusan Rahmat, bukan kami**: transaksi ortho
**pertama** milik seorang pasien yang bukan `Control …`, bukan `Kawat …`,
dan bukan `Bracket … Lepas`. Diuji pada dua pasien, keduanya tepat:

| pasien | sheet | Omnicare |
|---|---|---|
| Fadilatul Khasanah | pasang 27/1/2024 | `2024-01-27 Opening Cinere - Metal Braces` |
| Tegar Sanfian (D-2509-1035) | pasang 19/9/2025 | `2025-09-19 Cimulang Oktober - Siganture Metal Braces Gp` |

---

## 4. Volume per cabang (kunjungan kontrol, 2026 berjalan)

| cabang | baris kontrol | pasien |
|---|---|---|
| Bintaro | 1.695 | 542 |
| Sunter | 1.594 | 467 |
| Kalideres | 994 | 285 |
| **Cinere** | **722** | **222** |
| Pamulang | 634 | 167 |

Cinere yang paling kecil — itu sebabnya ia dipakai sebagai alat ukur lebih dulu.
Rentang data Cinere: **29 Nov 2023 → 8 Sep 2026** (klinik buka Januari 2024,
jadi cakupannya utuh).

---

## 5. Bukti: jejak pasien nyata

### 5.1 Cocok tanggal per tanggal — Tegar Sanfian, D-2509-1035
Sheet: pemasangan 19/9/2025; kontrol Jan 16, Feb 8, Mar 8, Apr 12, Mei 10,
Jun 7, Jul 19, Agu 9.
Omnicare: `2025-09-19` pemasangan, lalu `2026-01-16`, `2026-03-08`,
`2026-04-12`, `2026-05-10`, `2026-06-07`, `2026-07-19`, `2026-08-09`.

### 5.2 Yang tak terlihat sheet — pindah cabang
```
D-2405-0030   2026-05-31[Bintaro] 2026-07-05[Bintaro] 2026-08-02[Bintaro] 2026-09-06[Bintaro]
D-2507-0978   2026-02-08[Bintaro] 2026-03-15[Bintaro] 2026-05-03[Bintaro] 2026-08-23[Bintaro]
B-2312-5166   2025-12-07[Bintaro] 2026-03-31[Bintaro] 2026-06-24[Cinere]  2026-08-16[Bintaro]
B-1909-2157   2025-10-26[Cinere]  2026-01-09[Bintaro] 2026-03-29[Bintaro] 2026-05-03[Bintaro]
```
`B-1909-2157` di sheet Cinere tampak hilang sejak Oktober 2025 dan pantas
ditandai *"hampir 1 tahun tidak kontrol"* — padahal rajin, hanya di Bintaro.

### 5.3 Nomor RM sheet tidak selalu benar
Dyah Ayu Dwi Antari: sheet **D-2412-0044**, Omnicare **D-2412-0744**.

---

## 6. Angka pembanding (kohor Cinere)

| ukuran | terukur | sheet |
|---|---|---|
| pasien kontrol ortho di Cinere **2026** | **222** | 239 |
| pasien kontrol ortho di Cinere **sepanjang masa** | 277 | — |
| kontrol terakhir di cabang **lain** (kohor 2026) | **12** | 4 (Pindah Kontrol) |
| jarang kontrol, <2× per 6 bulan (kohor 2026) | **60** | ditulis tangan |
| kontrol wajar (kohor 2026) | 162 | 217 (Active) |
| punya `Lepas Bracket` (sepanjang masa) | 27 | 18 (Done) |
| menyentuh >1 cabang (sepanjang masa) | 76 | — |
| ├ pindah permanen | 18 | — |
| └ **benar-benar bolak-balik** | **58** | — |

Cabang tujuan perpindahan: Bintaro 68 · Sunter 8 · Kalideres 3 · Pamulang 1.

---

## 7. Kueri untuk mengulang setiap angka

```sql
-- kohor Cinere 2026
SELECT DISTINCT p_id FROM omnicare_sales_detail
WHERE branch_name = 'SweetTreats Dental Clinic Cinere'
  AND item_name REGEXP '^Control .*(Braces|Ortho)'
  AND trx_date >= '2026-01-01';

-- seluruh kunjungan kontrol seorang pasien, LINTAS CABANG
SELECT trx_date, branch_name, item_name FROM omnicare_sales_detail
WHERE p_id = 'D-2509-1035' AND item_name REGEXP '^Control .*(Braces|Ortho)'
ORDER BY trx_date;

-- daftar item ortho + jumlah baris
SELECT item_name, item_code, COUNT(*) n, MIN(trx_date), MAX(trx_date)
FROM omnicare_sales_detail
WHERE item_name REGEXP '(?i)ortho|braces|bracket|kawat|behel|retainer'
GROUP BY item_name, item_code ORDER BY n DESC;

-- volume kontrol per cabang 2026
SELECT branch_name, COUNT(*) baris, COUNT(DISTINCT p_id) pasien
FROM omnicare_sales_detail
WHERE item_name REGEXP '^Control .*(Braces|Ortho)' AND trx_date >= '2026-01-01'
GROUP BY branch_name ORDER BY baris DESC;
```

"Pindah kontrol" dan "jarang kontrol" dihitung di PHP, bukan SQL murni: ambil
seluruh kunjungan tiap pasien terurut tanggal, lalu periksa cabang kunjungan
**terakhir** (pindah) dan **hitung kunjungan dalam 6 bulan terakhir** (jarang,
ambang <2 sesuai definisi Anisa).

⚠️ `SHOW COLUMNS` / `DESCRIBE` / `information_schema` **membunuh mysqld lokal**
di lingkungan pengembangan proyek ini. Pakai `SHOW TABLES LIKE` dan
`SELECT kolom … LIMIT 0`.
