# Serah-Terima Deploy — Fitur Open API Cekat

Dibuat: 2026-05-16 · Untuk: rekan yang push/upload ke produksi
Sudah dites end-to-end di lokal (semua endpoint LULUS).

---

## 0. Ringkasan

Fitur Open API untuk vendor AI WhatsApp **Cekat**: 4 endpoint (`customer_profile`,
`service_range`, `jadwal_dokter`, `reservation`) + lingkungan sandbox + auth
token per-vendor. Deploy = upload file + jalankan 3 SQL + terbitkan token live.

Domain: `app.sweettreats.id` · DB prod: `u695655413_app_sweettreat`

---

## 1. Daftar File

### 1A. File BARU — 100% Cekat, aman di-upload apa adanya

**Wajib di produksi (runtime):**
- `app/Http/Controllers/Api/ApiCekatController.php`
- `app/Http/Controllers/Concerns/ResolvesDoctorAvailability.php`
- `app/Http/Middleware/VerifyApiClientToken.php`
- `app/Console/Commands/IssueApiClientToken.php`
- `routes/cekat.php`

**Jalankan di DB (lihat §2), tidak perlu jadi file runtime:**
- `database/sql/2026_05_16_cekat_service_code.sql`
- `database/sql/2026_05_16_cekat_dev_tables.sql`
- `database/sql/2026_05_16_api_clients.sql`

**Dokumentasi / dev-only — TIDAK perlu di-upload ke produksi:**
- `docs/api/cekat-api.md` (sumber dokumen untuk tim Cekat — bukan runtime)
- `docs/api/cekat-api.pdf` (versi PDF dari .md — INI yang dikirim ke tim Cekat & konsultan/senior untuk dibaca/review; regenerate bila API berubah)
- `docs/api/cekat-api.http` (alat tes lokal; sudah TANPA token)
- `docs/api/cekat-TEST_FLOW.html` (checklist tes interaktif — buka di browser, untuk lokal & produksi; progress ke-save di browser, bisa export JSON / print)
- `docs/deploy/cekat-DEPLOY.md` (dokumen ini)
- `tests/Feature/CekatApiTest.php`, `tests/Unit/CekatHelpersTest.php`

### 1B. File DIUBAH — 100% Cekat (sebelumnya bersih, aman upload utuh)
- `app/Http/Kernel.php` — tambah 1 baris alias middleware `api.client`
- `app/Http/Middleware/VerifyCsrfToken.php` — tambah `apicekat/*`, `apicekatdev/*` ke `$except`
- `app/Providers/RouteServiceProvider.php` — tambah load `routes/cekat.php`

### 1C. File DIUBAH — ⚠️ BERCAMPUR dengan perubahan lama (HATI-HATI)

Kedua file ini **sudah `modified` sebelum** kerjaan Cekat (ada perubahan dari
pekerjaan lain yang juga belum di-commit). `git diff` akan menampilkan campuran.
**Jangan asumsikan seluruh diff = Cekat.** Bagian yang BARU dari Cekat:

- **`app/Http/Controllers/Api/ReservationApiController.php`**
  - Tambah import `use ;`
  - Tambah `use ResolvesDoctorAvailability;` di dalam class
  - `getAvailableDates()` & `getTimeSlots()` diringkas jadi pemanggil trait
  - Method `private resolveDuration()` dipindah ke trait (dihapus dari sini)
  - *Perilaku route `/api/reservation/*` lama TIDAK berubah (output identik).*

- **`app/Models/RawDocService.php`**
  - Tambah `'code'` ke `$fillable`
  - Tambah 2 method statis: `priceRangeByCodes()` & `resolveByTreatment()`

> Sisanya di kedua file (di luar poin di atas) = pekerjaan lain, bukan Cekat.

---

## 2. SQL — jalankan di phpMyAdmin DB produksi `u695655413_app_sweettreat`

Urut, satu per satu (buka file, copy isi, tab SQL → Go):

1. **`2026_05_16_cekat_service_code.sql`** — tambah kolom `code` di
   `raw_doc_service` + isi kode SVC001–SVC011 (UPDATE sudah ada di file,
   sesuaikan jika nama treatment di prod beda).
2. **`2026_05_16_cekat_dev_tables.sql`** — buat 5 tabel `_dev` (sandbox).
3. **`2026_05_16_api_clients.sql`** — buat tabel `api_clients` (kredensial vendor).
4. **`2026_05_21_per_branch_doctor_duration.sql`** — buat 2 tabel durasi per-cabang
   (`raw_doctor_branch_service_duration` & `raw_doctor_branch_detail_duration`) +
   backfill dari tabel legacy. **Wajib untuk fitur `sub_service` (v1.1)** di
   endpoint `/jadwal_dokter`. SQL ini idempoten (aman dijalankan ulang).

Cek:
- `SELECT id,name,code FROM raw_doc_service;` → kolom `code` terisi.
- `SHOW TABLES LIKE 'raw_doctor_branch_%';` → 2 tabel muncul.

---

## 3. Setelah file ter-upload — di server produksi

```bash
php artisan apiclient:issue Cekat live      # token LIVE  → catat, beri ke Cekat
php artisan apiclient:issue Cekat sandbox   # token SANDBOX→ catat, beri ke Cekat
php artisan route:clear                     # WAJIB: daftarkan routes/cekat.php
php artisan config:clear
```

> Token plaintext **tampil sekali**. Simpan & kirim ke tim Cekat lewat channel
> aman (jangan via dokumen publik). Token tersimpan ter-hash di `api_clients`.
> Blokir vendor kapan saja: `UPDATE api_clients SET is_active=0 WHERE id='...';`

Verifikasi route hidup:
```bash
php artisan route:list | grep apicekat      # harus muncul 14 route (7 live + 7 sandbox)
```
7 endpoint per environment: `customer_profile`, `service_range`, `jadwal_dokter`,
`reservation`, **`reservation/{id}`** (cek status v1.4), **`treatments`** (discovery v1.3),
**`branches`** (discovery v1.3).

---

## 4. PRE-DEPLOY — BACKUP & ROLLBACK PLAN

### 4.1 Backup wajib SEBELUM jalankan SQL apa pun
phpMyAdmin → DB `u695655413_app_sweettreat` → tab **Export** → format SQL →
Custom → centang **"Add DROP TABLE"** → simpan file `backup_pre_cekat_YYYYMMDD.sql`.
Simpan minimal 2 minggu.

> SQL #4 (`per_branch_doctor_duration`) berisi **backfill data** dari tabel
> legacy. Kalau backfill salah, tidak bisa di-undo otomatis — backup wajib.

### 4.2 Rollback bila ada masalah

| Masalah | Langkah revert |
|---|---|
| Route Cekat error / break route lain | Hapus `routes/cekat.php`, hapus `Route::middleware('web')->group(base_path('routes/cekat.php'));` di `RouteServiceProvider.php`, jalankan `php artisan route:clear`. Route lama tetap utuh. |
| Tabel `api_clients` / `_dev` / `raw_doctor_branch_*` perlu dihapus | `DROP TABLE api_clients, raw_contact_dev, raw_customer_dev, raw_cus_journey_dev, raw_cus_reservation_dev, raw_cus_res_service_dev, raw_doctor_branch_service_duration, raw_doctor_branch_detail_duration;` |
| Kolom `code` di `raw_doc_service` mau dihapus | `ALTER TABLE raw_doc_service DROP INDEX uniq_doc_service_code, DROP COLUMN code;` |
| Vendor (mis. Cekat) bermasalah | `UPDATE api_clients SET is_active=0 WHERE name='Cekat';` (instan, tidak perlu deploy) |
| Korupsi data total | Restore dari backup §4.1. |

### 4.3 Pengingat untuk tim dev
- **Sebelum commit & push**: pastikan `docs/api/cekat-api.http` di-revert ke
  `@token = ISI_TOKEN_SANDBOX_DI_SINI` dan baris `Authorization: Bearer {{token}}`
  (tester biasanya paste token literal saat tes lokal). Token JANGAN masuk git.
- Tester lokal: kalau setelah tes mau bersihkan `_dev`, jalankan
  `TRUNCATE raw_contact_dev; TRUNCATE raw_customer_dev; TRUNCATE raw_cus_journey_dev; TRUNCATE raw_cus_reservation_dev; TRUNCATE raw_cus_res_service_dev;`

---

## 4. Smoke test di produksi (aman — sandbox dulu)

Pakai token **sandbox** ke `https://app.sweettreats.id/apicekatdev/...`
(tulis ke tabel `_dev`, tidak menyentuh data klinik asli). Contoh:

```
GET  https://app.sweettreats.id/apicekatdev/service_range?codes=SVC007
     Header: Authorization: Bearer <token-sandbox>
     → 200, harga min/max
(tanpa header → harus 401 UNAUTHORIZED)
```

Setelah sandbox OK, baru tes 1 request ringan token **live** ke `/apicekat/...`.

---

## 5. Catatan / kemungkinan improvement (opsional, bukan blocker)

- `photo_url` dokter ikut `APP_URL`. Pastikan `APP_URL=https://app.sweettreats.id`
  di `.env` prod supaya URL foto benar untuk Cekat.
- Belum ada command `apiclient:revoke` — blokir vendor pakai SQL `is_active=0`
  (sudah cukup). Bisa ditambah nanti bila perlu.
- Jika prod pakai `config:cache`/`route:cache`, WAJIB rebuild cache setelah
  upload (`php artisan route:clear` lalu cache ulang) — kalau tidak, route
  Cekat tidak terdaftar.
- Tidak ada perubahan `.env` (token di tabel `api_clients`, bukan env).
