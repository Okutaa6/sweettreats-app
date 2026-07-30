# Serah-Terima Deploy — Integrasi Omnicare (Lokadok) Open API

Dibuat: 2026-05-21 · Untuk: rekan yang push/upload ke produksi
Sudah dites end-to-end di lokal (sync, dashboard, report, match, reconcile, patient-flow).

Domain: `app.sweettreats.id` · DB prod: `u695655413_app_sweettreat`

---

## 0. Ringkasan

Integrasi **one-way pull** dari Omnicare (Lokadok) ke SweetTreats Data Center:

- **Sync** data Omnicare overnight → mirror tables `omnicare_*` (sales, pasien, dokter, cabang).
- **Dashboard** Sales & Executive + **Laporan Penjualan** 7-tab (ganti workflow download CSV manual).
- **Mapping pasien** `customer_omnicare_link` — jembatan identitas raw_customer ↔ omnicare_patient.
- **Reconciliation** — cocokkan reservasi SweetTreats ↔ encounter Omnicare + UI review manual.
- **Patient timeline** — riwayat lintas sistem di halaman detail pasien (crud/customer).

Deploy = upload file → `composer install` → 4 SQL → set `.env` → backfill data → set cron.

5 cabang aktif: Bintaro (29957), Sunter (29958), Kalideres (29959), Cinere (30439),
Pamulang (30941). ABG (31163) di-exclude (placeholder).

---

## 1. Daftar File

### 1A. File BARU — 100% Omnicare, aman di-upload apa adanya

**Wajib di produksi (runtime):**
- `app/Console/Commands/SyncOmnicare.php`
- `app/Console/Commands/MatchOmnicarePatients.php`
- `app/Console/Commands/OmnicareReconcile.php`
- `app/Console/Commands/OmnicareHealthCheck.php`
- `app/Console/Commands/RebuildGroupAccess.php`
- `app/Http/Controllers/SalesDashboardController.php`
- `app/Http/Controllers/SalesReportController.php`
- `app/Http/Controllers/ReconciliationController.php`
- `app/Services/Omnicare/OmnicareClient.php`
- `app/Services/Omnicare/OmnicareResponse.php`
- `app/Services/Omnicare/PayloadNormalizer.php`
- `app/Support/DoctorNameMatcher.php`
- `app/Support/OmnicareEncounters.php`
- `config/omnicare.php`
- `resources/views/dashboard/sales.blade.php`
- `resources/views/report/sales.blade.php`
- `resources/views/report/reconcile.blade.php`

**Jalankan di DB (lihat §3), tidak perlu jadi file runtime:**
- `database/sql/2026_05_20_omnicare_tables.sql`
- `database/sql/2026_05_21_customer_omnicare_link.sql`
- `database/sql/2026_05_21_omnicare_report_access.sql`
- `database/sql/2026_05_21_reconcile_access.sql`

**Dokumentasi — TIDAK perlu di-upload ke produksi:**
- `docs/deploy/omnicare-DEPLOY.md` (dokumen ini)

### 1B. File DIUBAH — aman upload utuh (perubahan murni Omnicare)
- `app/Console/Kernel.php` — `schedule()` sebelumnya kosong, sekarang berisi 3 entry
  cron Omnicare (sync 02:00, match-patients 02:45, reconcile 03:00).
- `config/logging.php` — tambah channel `omnicare` (log harian, rotasi 30 hari).
- `routes/web.php` — tambah 2 grup route: `report`, `reconcile`.
- `composer.json` / `composer.lock` — tambah dependency `intervention/image:^3`.

### 1C. File DIUBAH — ⚠️ periksa diff sebelum upload
- `app/Http/Controllers/ExecutiveDashboardController.php` — ditambah method & query
  untuk section "Omnicare Sales" di executive dashboard.
- `resources/views/dashboard/executive.blade.php` — ditambah section Omnicare.
- `app/Http/Controllers/Crud/CustomerController.php` — method `edit()` sekarang
  membangun timeline gabungan (journey + reservasi + perawatan Omnicare) lewat
  method baru `buildTimeline()`. Aman tanpa tabel Omnicare (di-guard `Schema::hasTable`).
- `resources/views/crud/customer/edit.blade.php` — section "Timeline Perjalanan
  Pasien" di-upgrade untuk menampilkan timeline gabungan tsb.

> Semua perubahan di atas adalah penambahan blok Omnicare ke file lama. Tidak
> menyentuh logika lama. Kalau ragu, bandingkan `git diff` sebelum upload.

---

## 2. composer install (dependency baru)

Fitur lain (EpKonten image) butuh `intervention/image`. Di server produksi:

```bash
composer install --no-dev --optimize-autoloader
```

Pastikan ekstensi PHP **GD** aktif di server (`php -m | grep -i gd`). Kalau belum,
aktifkan `extension=gd` di `php.ini` lalu restart PHP-FPM/Apache.

---

## 3. SQL — jalankan di phpMyAdmin DB produksi `u695655413_app_sweettreat`

Urut, satu per satu (buka file, copy isi, tab SQL → Go). Semua **idempoten** —
aman dijalankan ulang.

1. **`2026_05_20_omnicare_tables.sql`** — buat 8 mirror table `omnicare_*` +
   `reservation_omnicare_link` + kolom `raw_branch.omnicare_place_id` &
   `raw_doctor.omnicare_doc_id` + isi 5 mapping cabang.
2. **`2026_05_21_customer_omnicare_link.sql`** — tabel jembatan identitas pasien.
3. **`2026_05_21_omnicare_report_access.sql`** — menu "Laporan" → "Laporan Penjualan".
4. **`2026_05_21_reconcile_access.sql`** — menu "Rekonsiliasi" (butuh #3 dulu).

⚠️ **Cek mapping cabang** setelah SQL #1 — script #1 mengisi `omnicare_place_id`
dengan mencocokkan **nama cabang**. Kalau nama di prod beda dari lokal, mapping
bisa kosong:

```sql
SELECT id, name, omnicare_place_id FROM raw_branch ORDER BY name;
```

Kelima cabang harus punya `omnicare_place_id`. Kalau ada yang `NULL`, isi manual:
```sql
UPDATE raw_branch SET omnicare_place_id='29957' WHERE name LIKE '%Bintaro%';
UPDATE raw_branch SET omnicare_place_id='29958' WHERE name LIKE '%Sunter%';
UPDATE raw_branch SET omnicare_place_id='29959' WHERE name LIKE '%Kalideres%';
UPDATE raw_branch SET omnicare_place_id='30439' WHERE name LIKE '%Cinere%';
UPDATE raw_branch SET omnicare_place_id='30941' WHERE name LIKE '%Pamulang%';
```

---

## 4. .env produksi — tambah kredensial Omnicare

Tambah di `.env` server produksi (JANGAN commit ke git):

```
OMNICARE_BASE_URL=https://api.lokadok.xyz
OMNICARE_USERNAME=sweettreats
OMNICARE_PASSWORD=K5ISjh
```

Opsional (sudah ada default di `config/omnicare.php`, ubah hanya bila perlu):
`OMNICARE_COOLDOWN_MS`, `OMNICARE_TIMEOUT`, `OMNICARE_RETRY_ATTEMPTS`,
`OMNICARE_SYNC_DAYS`, `OMNICARE_MATCH_WINDOW`.

Lalu bersihkan cache config:
```bash
php artisan config:clear
php artisan route:clear
```

---

## 5. Initial data load — WAJIB, di server produksi (urut)

Cron harian hanya menarik window 3 hari. Data historis TIDAK masuk tanpa backfill
manual sekali ini. Jalankan SETELAH §2–§4 selesai, SEBELUM cron aktif.

```bash
# 5.1 — Cek koneksi & kredensial dulu
php artisan omnicare:health

# 5.2 — Master data (cabang, dokter, poliklinik, pasien) — cepat, tanpa tanggal
php artisan omnicare:sync --endpoint=location
php artisan omnicare:sync --endpoint=doctor
php artisan omnicare:sync --endpoint=polyclinic
php artisan omnicare:sync --endpoint=patient

# 5.3 — Backfill penjualan. Untuk PRODUKSI disarankan 3 tahun supaya riwayat
#        transaksi lengkap (laporan & analisis perlu data multi-tahun).
#        Auto-split per 31 hari, cooldown 10s/call — 3 tahun ~30-50 menit;
#        jalankan di terminal yang tidak putus / pakai nohup.
php artisan omnicare:sync --endpoint=sales   --start=20230521 --end=20260521
php artisan omnicare:sync --endpoint=product --start=20230521 --end=20260521
#   (Lokal cukup 1 tahun: --start=20250521. Prod: 3 tahun seperti di atas.)

# 5.4 — Link pasien SweetTreats ↔ Omnicare (auto-match phone+DOB+nama)
php artisan omnicare:match-patients

# 5.5 — Reconcile reservasi ↔ encounter (90 hari ke belakang untuk catch semua)
php artisan omnicare:reconcile --days=90

# 5.6 — Regenerate cache menu sidebar (WAJIB setelah SQL access §3)
php artisan access:rebuild
```

> `omnicare:sync --endpoint=doctor` otomatis auto-match `raw_doctor.omnicare_doc_id`
> via kemiripan nama. Cek dokter yang belum ke-match:
> `SELECT name FROM raw_doctor WHERE omnicare_doc_id IS NULL;` — isi manual bila perlu.

---

## 6. Cron — sync & reconcile otomatis tiap malam

`Kernel.php` sudah punya 3 jadwal (sync 02:00, match-patients 02:45, reconcile 03:00).
Supaya jalan, server butuh SATU cron entry Laravel scheduler. Tambah di crontab:

```cron
* * * * * cd /path/ke/app.sweettreats.id && php artisan schedule:run >> /dev/null 2>&1
```

(Di shared hosting Hostinger: hPanel → Advanced → Cron Jobs → tambah perintah di atas.)

Cek jadwal terdaftar:
```bash
php artisan schedule:list
```

---

## 7. Verifikasi pasca-deploy

```sql
-- Data ter-sync?
SELECT COUNT(*) FROM omnicare_sales_detail;        -- harus puluhan ribu (1 thn)
SELECT MIN(trx_date), MAX(trx_date) FROM omnicare_sales_detail;
SELECT match_confidence, COUNT(*) FROM customer_omnicare_link GROUP BY match_confidence;
SELECT match_confidence, COUNT(*) FROM reservation_omnicare_link GROUP BY match_confidence;

-- Menu access ter-grant?
SELECT * FROM access_path WHERE id LIKE 'b1a7%';   -- harus 4 baris
```

Di browser (login sebagai admin):
- Menu **Laporan** muncul di sidebar → **Laporan Penjualan**, **Rekonsiliasi**.
- `/report/sales` — angka penjualan tampil, filter tanggal & cabang jalan, Export CSV.
- `/reconcile` — kartu ringkasan + antrian conflict tampil.
- Buka detail 1 pasien (menu Pasien → Edit) — section **Timeline Perjalanan Pasien**
  menampilkan gabungan kontak/lead + reservasi + perawatan Omnicare.
- `/dashboard/sales` & `/dashboard/executive` — section Omnicare tampil.

Cek log bila ada masalah: `storage/logs/omnicare-*.log`.

---

## 8. Catatan / hal penting

- **Cooldown 10 detik** di endpoint sales/product Omnicare → backfill memang lama.
  Wajar. Jangan dihentikan di tengah; kalau putus, jalankan ulang (idempoten,
  upsert by primary key).
- **`match-patients` & `reconcile` idempoten** — hanya proses baris yang belum
  ter-link. Aman dijalankan ulang kapan saja.
- **Service Charge & Voucher Tunai** dikecualikan dari nilai net di semua laporan
  (sudah diverifikasi cocok dengan catatan manual cabang).
- **Conflict rekonsiliasi** ditangani FO/Sales Admin per cabang lewat `/reconcile`
  (user ter-scope cabang hanya lihat cabangnya).
- **Tidak ada write/POST ke Omnicare** — integrasi murni baca. Aman, tidak mengubah
  data di Omnicare.
- Kalau prod pakai `config:cache` / `route:cache`, WAJIB rebuild cache setelah
  upload (`php artisan config:clear && php artisan route:clear`, lalu cache ulang).
- Backfill `--start=20250521` = 1 tahun ke belakang dari tanggal deploy. Sesuaikan
  bila ingin lebih panjang (data Omnicare tersedia ~4 tahun ke belakang).
