# Test Flow Deploy Omnicare ke Produksi — Step-by-Step Bertahap

> **Untuk:** Rekan yang melakukan upload ke server prod (Hostinger)
> **Tujuan:** **Test setiap langkah** supaya data Omnicare masuk lengkap (3 tahun ke
> belakang), **baru** aktifkan sync rutin. Prod saat ini **belum ada data Omnicare**.
>
> **Cara pakai:** ikuti urut dari atas sampai bawah. Tiap fase punya **Action** (yang
> dijalankan) + **Verifikasi** (test untuk konfirmasi sukses). **Jangan ke fase berikutnya
> kalau verifikasi belum lulus** — itu inti test flow-nya: validasi tiap langkah sebelum
> bergerak, biar tidak ada data yang terlewat di akhir.

Tanya Ekky kalau ada step yang gagal verifikasi.

---

## ✅ Sebelum mulai — pastikan punya akses ini

- [ ] **FileZilla** terhubung ke server prod (untuk upload file)
- [ ] **Hostinger hPanel** login (untuk Cron Jobs, phpMyAdmin, dan Terminal kalau ada)
- [ ] **phpMyAdmin** bisa buka DB `u695655413_app_sweettreat`
- [ ] **Credentials Omnicare** (`OMNICARE_USERNAME` & `OMNICARE_PASSWORD` dari `.env` lokal — minta ke Ekky)
- [ ] **Terminal/SSH atau hPanel Terminal** untuk jalankan `php artisan` (di Hostinger Premium+ ada SSH; di hPanel ada "Browser Terminal" di Advanced)
- [ ] **Backup DB prod** terbaru — download dari phpMyAdmin → Export sebelum mulai (untuk rollback kalau perlu)

> ⚠️ **Penting:** prod **belum ada data Omnicare sama sekali**. Tujuan deploy ini = bawa
> data Omnicare 3 tahun ke prod + aktifkan sync harian. Kalau ada langkah yang skip,
> data bisa tidak lengkap.

---

## FASE 1 — Upload file aplikasi

### Action
Lewat FileZilla, upload semua file di daftar `docs/omnicare-DEPLOY.md` §1A & §1B & §1C.
Rangkumannya:

**File baru** (dari lokal ke prod, persis struktur foldernya):
- `app/Console/Commands/MatchOmnicarePatients.php`
- `app/Console/Commands/OmnicareHealthCheck.php`
- `app/Console/Commands/OmnicareReconcile.php`
- `app/Console/Commands/RebuildGroupAccess.php`
- `app/Console/Commands/SyncOmnicare.php`
- `app/Http/Controllers/ReconciliationController.php`
- `app/Http/Controllers/SalesDashboardController.php`
- `app/Http/Controllers/SalesReportController.php`
- `app/Services/Omnicare/` (seluruh folder)
- `app/Support/DoctorNameMatcher.php`
- `app/Support/OmnicareEncounters.php`
- `config/omnicare.php`
- `resources/views/dashboard/sales.blade.php`
- `resources/views/report/reconcile.blade.php`

**File diubah** (timpa yang ada di prod):
- `app/Console/Kernel.php`
- `app/Http/Controllers/Crud/CustomerController.php`
- `app/Http/Controllers/ExecutiveDashboardController.php`
- `config/logging.php`
- `resources/views/crud/customer/edit.blade.php`
- `resources/views/dashboard/executive.blade.php`
- `routes/web.php`
- `composer.json`, `composer.lock`

### Verifikasi
- [ ] Buka homepage prod di browser — **masih bisa diakses** (belum berubah, karena SQL & cache belum diapa-apain)
- [ ] Cek 3 file random di FileZilla — file size cocok dengan lokal

### ❌ Kalau gagal
- Homepage error setelah upload → kemungkinan file kepotong / tidak lengkap. Cek log error
  di `storage/logs/` lewat FileZilla.

---

## FASE 2 — Install dependencies (composer)

### Action
Buka **hPanel → Advanced → Browser Terminal** (atau SSH). Pindah ke folder app:
```bash
cd /home/u695655413/domains/<DOMAIN>/public_html
composer install --no-dev --optimize-autoloader
```

> Kalau Hostinger plan-nya tidak punya Terminal/SSH: install lokal (`composer install`),
> lalu upload seluruh folder `vendor/` lewat FileZilla. Lama (~5000 file) tapi sekali aja.

### Verifikasi
- [ ] Folder `vendor/` ada di server
- [ ] `php artisan --version` jalan tanpa error (output: Laravel Framework 10.x.x)
- [ ] Buka homepage prod — masih bisa diakses

### ❌ Kalau gagal
- `php: command not found` → cek path lengkap PHP: `which php` atau `/usr/bin/php artisan --version`
- Extension missing → cek `php -m` (butuh: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `gd`)

---

## FASE 3 — Setup database schema (4 SQL files)

### Action
Buka **phpMyAdmin** → pilih DB `u695655413_app_sweettreat` → tab **SQL** →
copy-paste isi file dan klik **Go**, **satu per satu berurutan**:

| # | File | Tujuan |
|---|---|---|
| 1 | `database/sql/2026_05_20_omnicare_tables.sql` | Buat 8 tabel `omnicare_*` + isi mapping cabang |
| 2 | `database/sql/2026_05_21_customer_omnicare_link.sql` | Tabel jembatan identitas pasien |
| 3 | `database/sql/2026_05_21_omnicare_report_access.sql` | Menu "Laporan Penjualan" |
| 4 | `database/sql/2026_05_21_reconcile_access.sql` | Menu "Rekonsiliasi" |

### Verifikasi setelah SQL #1 (paling penting!)
Di phpMyAdmin tab SQL, jalankan:
```sql
SHOW TABLES LIKE 'omnicare_%';
```
Harus ada **8 tabel** (omnicare_doctor, omnicare_location, omnicare_patient, omnicare_polyclinic, omnicare_product, omnicare_product_sales, omnicare_sales_detail, omnicare_sync_log).

```sql
SELECT name, omnicare_place_id FROM raw_branch;
```
**Setiap cabang harus punya `omnicare_place_id` terisi:**
| name | omnicare_place_id |
|---|---|
| Bintaro | 29957 |
| Sunter | 29958 |
| Kalideres | 29959 |
| Cinere | 30439 |
| Pamulang | 30941 |

⚠️ **Kalau ada `omnicare_place_id` yang NULL** — mapping cabang gagal. Berhenti, hubungi
Ekky. Penyebab: nama cabang di `raw_branch` prod beda dengan yang di script (mis. "BINTARO"
vs "Bintaro").

### Verifikasi setelah SQL #2–4
```sql
SELECT COUNT(*) FROM customer_omnicare_link;
-- Harus 0 (belum match patient, akan diisi di Fase 7)
SELECT id, nama, link FROM access_path WHERE id LIKE 'b1a7%';
-- Harus 4 baris: parent "Laporan", sub "Laporan", child "Laporan Penjualan", child "Rekonsiliasi"
```

---

## FASE 4 — Setup `.env` (kredensial Omnicare)

### Action
Lewat FileZilla: download `.env` dari prod → edit lokal → upload kembali.

Tambahkan di `.env` prod (atau timpa kalau sudah ada):
```
OMNICARE_BASE_URL=https://api.lokadok.xyz
OMNICARE_USERNAME=<minta ke Ekky>
OMNICARE_PASSWORD=<minta ke Ekky>
OMNICARE_COOLDOWN_MS=11000
OMNICARE_BACKFILL_MONTHS=36
```

Lalu clear cache config (lewat Terminal):
```bash
php artisan config:clear
```

### Verifikasi
```bash
php artisan tinker --execute="echo config('omnicare.credentials.username');"
```
Harus print username Omnicare (bukan kosong / bukan "null").

### ❌ Kalau output kosong
- `.env` salah ter-upload → cek file di FileZilla, pastikan ada baris `OMNICARE_USERNAME=...`
- Cache belum cleared → `php artisan config:clear` lagi

---

## FASE 5 — Test koneksi ke Omnicare

### Action
```bash
php artisan omnicare:health
```

### Verifikasi
Output harus menunjukkan:
- ✓ API reachable (`https://api.lokadok.xyz`)
- ✓ Authentication success
- ✓ Test endpoint return data (mis. `/get/location` return 6 cabang)

### ❌ Kalau gagal
- HTTP 401/403 → username/password salah, cek `.env`
- Connection timeout → server prod tidak bisa akses internet (jarang di Hostinger; hubungi support kalau perlu)

---

## FASE 6 — BACKFILL DATA (langkah paling penting, ~30–60 menit)

> ⚠️ **Jangan skip atau persingkat range tanggal.** Tujuan deploy ini = bawa **3 tahun
> riwayat** transaksi. Kalau dipersingkat, laporan jadi pincang.

### 6A. Backfill data master (cepat, ~1 menit)

```bash
php artisan omnicare:sync --endpoint=doctor
php artisan omnicare:sync --endpoint=polyclinic
php artisan omnicare:sync --endpoint=patient
```

### Verifikasi 6A
```sql
SELECT COUNT(*) FROM omnicare_doctor;       -- ~puluhan
SELECT COUNT(*) FROM omnicare_polyclinic;   -- ~puluhan
SELECT COUNT(*) FROM omnicare_patient;      -- ~belasan ribu
SELECT COUNT(*) FROM omnicare_location;     -- 6
```

⚠️ Kalau `omnicare_patient` < 5.000, kemungkinan paginasi error. Hubungi Ekky.

### 6B. Backfill sales 3 tahun (LAMA — ~30 menit)

Hitung tanggal: hari ini = `2026-05-23` → 3 tahun ke belakang = `2023-05-23`.

```bash
php artisan omnicare:sync --endpoint=sales --start=20230523 --end=20260523
```

> Sync ini auto-split per 31 hari (cooldown 10 detik antar call Omnicare). Total:
> ~36 split × 10 detik = ~6 menit cooldown + waktu response API. Diam menunggu, **jangan
> tutup terminal**. Kalau pakai SSH lewat laptop, pakai `screen` atau `tmux`:
>
> ```bash
> screen -S backfill-sales
> # jalankan command di atas
> # Ctrl+A lalu D untuk detach; balik dengan: screen -r backfill-sales
> ```

Setelah itu:
```bash
php artisan omnicare:sync --endpoint=product --start=20230523 --end=20260523
```
(Mirip lamanya.)

### Verifikasi 6B
```sql
SELECT
  MIN(trx_date) sejak,
  MAX(trx_date) sampai,
  COUNT(*) baris,
  COUNT(DISTINCT trx_id) trx_unique
FROM omnicare_sales_detail;
```

Harapan:
- `sejak` ≈ `2023-05-23` (atau lebih awal)
- `sampai` = kemarin / hari ini
- `baris` = ratusan ribu (estimasi ~100.000+ untuk 3 tahun × 5 cabang)
- `trx_unique` = `baris` (tidak ada duplikat)

Per cabang:
```sql
SELECT place_id, COUNT(*) baris, COUNT(DISTINCT trx_date) hari, MAX(trx_date) terakhir
FROM omnicare_sales_detail
GROUP BY place_id;
```
**Semua 5 cabang harus ada barisnya.** Kalau ada place_id yang 0 baris, ada masalah.

### Sanity check angka — banding dengan audit Bintaro Maret 2026
```sql
SELECT SUM(net_sell_price) total
FROM omnicare_sales_detail
WHERE place_id = 29957
  AND trx_date BETWEEN '2026-03-01' AND '2026-03-31'
  AND item_type <> 'V'
  AND LOWER(item_name) NOT LIKE 'service charge%';
```
Harapan: **±Rp 299.126.000** (dari audit lokal). Toleransi 1–3% wajar (timing).

⚠️ Kalau angka jauh berbeda (>10%) → ada masalah sync. Hubungi Ekky.

### ❌ Kalau putus di tengah
**Aman.** Sync idempotent (pakai `INSERT ... ON DUPLICATE KEY UPDATE` per `trx_id`).
Jalankan ulang command yang sama — akan skip yang sudah masuk, lanjut yang belum.

---

## FASE 7 — Match pasien (raw_customer ↔ omnicare_patient)

### Action
```bash
php artisan omnicare:match-patients
```

Ini mencocokkan customer SweetTreats dengan pasien Omnicare via phone+DOB+nama. Cepat
(~1-2 menit).

### Verifikasi
```sql
SELECT COUNT(*) FROM customer_omnicare_link;
-- Harus ribuan (di lokal: 5.619 dari 6.026 = 93%)

SELECT match_confidence, COUNT(*) FROM customer_omnicare_link GROUP BY match_confidence;
-- Breakdown: exact (paling banyak), high, medium
```

Kalau total < 50% dari `raw_customer` → ada masalah, mungkin nomor HP / DOB tidak ter-sync
benar. Hubungi Ekky.

---

## FASE 8 — Reconcile reservasi ↔ encounter

### Action
```bash
php artisan omnicare:reconcile --days=90
```

Mencocokkan reservasi SweetTreats 90 hari terakhir dengan encounter Omnicare yang
sesuai (±3 hari window).

### Verifikasi
```sql
SELECT COUNT(*) FROM reservation_omnicare_link;
-- Ratusan (tergantung jumlah reservasi 90 hari terakhir)

SELECT match_status, COUNT(*) FROM reservation_omnicare_link GROUP BY match_status;
-- Mix: matched / unmatched / conflict
```

---

## FASE 9 — Aktifkan menu Data Center

### Action
```bash
php artisan access:rebuild
```

### Verifikasi
- [ ] Login ke Data Center sebagai admin (akun Ekky atau setara)
- [ ] Menu kiri: muncul **Laporan** dengan 2 submenu:
  - Laporan Penjualan
  - Rekonsiliasi
- [ ] Klik **Laporan Penjualan** → halaman terbuka tanpa error

---

## FASE 10 — Spot-check angka di browser

### Action
Buka `/report/sales` di browser → pilih:
- Cabang: **Bintaro**
- Periode: **1 – 31 Maret 2026**

### Verifikasi — banding dengan audit
Banding angka di halaman dengan:

| Metrik | Harapan |
|---|---:|
| Total net | ≈ Rp 299.126.000 |
| Per cabang Bintaro | sama |
| Item terbanyak | `26CON178 Control Metal Braces` (~91 qty) |

Buka juga:
- [ ] `/reconcile` — kartu summary muncul, ada nilai
- [ ] `/dashboard/executive` — section Omnicare tampil dengan angka
- [ ] Buka detail 1 pasien (Pasien → pilih → Edit) — section **Timeline Perjalanan Pasien** menampilkan riwayat lintas-sistem

### ❌ Kalau angka aneh
- Halaman blank → cek log `storage/logs/laravel.log`
- Angka jauh berbeda dengan audit → kemungkinan backfill incomplete; jalankan ulang Fase 6 untuk range yang mungkin terlewat

---

## FASE 11 — Aktifkan cron sync harian (LANGKAH TERAKHIR)

### Action
Buka **Hostinger hPanel → Advanced → Cron Jobs** → klik **Add cron job**:

| Field | Value |
|---|---|
| **Schedule** | Every minute (atau pilih `* * * * *`) |
| **Command** | `/usr/bin/php /home/u695655413/domains/<DOMAIN>/public_html/artisan schedule:run` |

> Path PHP-nya kemungkinan beda — Hostinger biasanya `/usr/bin/php` atau
> `/opt/alt/php82/usr/bin/php`. Cek lewat hPanel → Advanced → **PHP Configuration** untuk
> path PHP CLI yang benar. Atau test:
>
> ```bash
> which php
> ```

### Verifikasi (langsung)
Tunggu 5 menit setelah save cron. Cek di phpMyAdmin:
```sql
SELECT * FROM omnicare_sync_log ORDER BY started_at DESC LIMIT 5;
```

Tidak akan ada output kalau belum jam 02:00. Tunggu sampai besok pagi.

### Verifikasi (esok hari, setelah jam 03:30)
```sql
SELECT
  endpoint,
  status,
  started_at,
  finished_at,
  rows_synced
FROM omnicare_sync_log
WHERE started_at >= CURDATE()
ORDER BY started_at;
```

Harus ada baris untuk:
- `sales` — jam 02:00, status `success`
- `product` — jam 02:xx, status `success`
- `match-patients` — jam 02:45
- `reconcile` — jam 03:00

Plus:
```sql
SELECT MAX(trx_date) FROM omnicare_sales_detail;
```
Harus update ke H-1 (kemarin).

Cek file log:
```bash
tail -100 storage/logs/omnicare-*.log
```
Harus ada entri sukses.

### ❌ Kalau cron tidak jalan
- Cek path PHP di cron command (`/usr/bin/php` atau lainnya)
- Cek di hPanel → Cron Jobs → **View Output** atau **Send Output to Email** untuk lihat error
- Test manual: jalankan `php artisan schedule:run` di Terminal, lihat output

---

## ✅ Sukses — sistem live!

Setelah Fase 11 verified, sistem berjalan otomatis. Yang perlu dipantau:
- **Tiap minggu sekali:** buka log `storage/logs/omnicare-*.log` — cek tidak ada error
- **Tiap akhir bulan:** banding angka di `/report/sales` dengan kasir manual (kalau sales
  masih maintain Google Sheet di parallel-run period)
- **Tiap saat:** kalau `last_synced_at` di `omnicare_sales_detail` lebih dari 2 hari lama,
  berarti cron mati — investigasi

Untuk diskusi rollout ke sales team: lihat
[`docs/proposal-laporan-sales-otomatis.md`](proposal-laporan-sales-otomatis.md).

---

## 🚨 Rollback (kalau ada masalah serius pasca-deploy)

Backup sudah diambil di awal (sebelum mulai Fase 1).

1. **Disable cron:** hPanel → Cron Jobs → delete entry
2. **Restore DB:** phpMyAdmin → Import → upload backup `.sql`
3. **Hapus file Omnicare** atau restore folder pre-deploy (kalau backup folder ada)
4. Hubungi Ekky untuk troubleshoot sebelum coba lagi

---

## 📋 Checklist final (centang setelah selesai)

- [ ] Fase 1 — file ter-upload, homepage masih jalan
- [ ] Fase 2 — composer install sukses
- [ ] Fase 3 — 4 SQL jalan, 5 cabang punya place_id, 4 entri menu
- [ ] Fase 4 — `.env` Omnicare credentials terisi
- [ ] Fase 5 — `omnicare:health` sukses
- [ ] Fase 6A — patient, doctor, polyclinic ter-sync
- [ ] Fase 6B — sales+product 3 tahun ter-sync, sanity check angka cocok
- [ ] Fase 7 — match-patients sukses, ribuan link
- [ ] Fase 8 — reconcile sukses
- [ ] Fase 9 — menu Data Center muncul
- [ ] Fase 10 — `/report/sales` & `/reconcile` & `/dashboard/executive` tampil benar
- [ ] Fase 11 — cron aktif, verified sync esok hari sukses

---

*Dokumen ini disusun 2026-05-23 untuk deploy Omnicare ke produksi SweetTreats.
Referensi teknis: [`docs/omnicare-DEPLOY.md`](omnicare-DEPLOY.md) (daftar file & SQL).
Untuk pertanyaan saat eksekusi: hubungi Ekky.*
