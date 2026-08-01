# Deploy Coordination — 3 Stream Pending

Dibuat: 2026-05-23 · Untuk: rekan yang push/upload ke produksi
Folder ini berisi 3 pekerjaan paralel yang **belum dideploy**. Dokumen ini
memberi tahu **mana dulu**, **share-apa**, dan **doc mana yang dibuka untuk
detail tiap stream**.

---

## 0. Snapshot status produksi (per 2026-05-23)

Yang sudah **dideploy** (committed sampai `e5e098d feat(cekat) base v1.0`):
- Multi-pasien / contact-anchored (`raw_contact`, `raw_cus_journey.raw_contact_id`)
- Cekat Open API **v1.0** base (4 endpoint dasar) + 3 SQL (`cekat_service_code`,
  `cekat_dev_tables`, `api_clients`)
- Affiliate / Marketing / Petty cash / Cash deposit / Transport cost — semua
  modul keuangan & marketing yang ter-commit sebelum `e5e098d`.

Yang **belum** dideploy (commit `afa0c6a` + semua untracked):
- **Per-branch doctor duration** (commit `afa0c6a` — kode sudah committed, **SQL belum dijalankan**)
- **Cekat v1.3 + v1.4** (uncommitted; built di atas v1.0 base)
- **Omnicare integration lengkap** (uncommitted; semua brand-new)

> ⚠️ Asumsi ini berdasar git log. Kalau ragu apakah SQL sudah pernah dijalankan
> di prod, cek lewat phpMyAdmin: `SHOW TABLES LIKE '<nama>';` atau
> `DESCRIBE <tabel>;` — kalau tabel/kolom sudah ada, skip SQL itu.

---

## 1. 3 stream pending — ringkasan

| # | Stream | Doc utama | Risiko utama |
|---|---|---|---|
| **A** | **Durasi per-cabang & cross-cabang access** (bug fix) | `docs/arsip/test-flow/durasi-akses-cabang-TEST_FLOW.html` (QA only; deploy via §3 di bawah) | SQL `per_branch_doctor_duration` belum jalan → Cekat sub_service & durasi ortho rusak |
| **B** | **Cekat v1.3 + v1.4** (Open API enhancement) | `docs/deploy/cekat-DEPLOY.md` + `docs/api/cekat-TEST_FLOW.html` | Bergantung Stream A (chain durasi sub-detail) |
| **C** | **Omnicare integration** (data center + sales report) | `docs/deploy/test-flow-deploy-prod.md` (master Omnicare) + `docs/deploy/omnicare-DEPLOY.md` | Paling besar; butuh composer install + cron + backfill 30-60 menit |

---

## 2. ⚠️ File yang dipakai DUA atau lebih stream (PENTING)

Karena rekan upload manual via FileZilla, file di bawah ini **berisi gabungan
perubahan dari beberapa stream**. Saat upload, **kamu otomatis dapat semuanya**
— tidak bisa upload "Stream A version saja" tanpa juga upload Stream B/C version
(versi di folder ini sudah merged).

| File | Berisi perubahan dari |
|---|---|
| `app/Console/Kernel.php` | Cekat (alias `api.client`) **dan** Omnicare (3 cron schedule) |
| `app/Http/Controllers/Concerns/ResolvesDoctorAvailability.php` | Durasi-cabang (Stream A) **dan** Cekat sub_service (Stream B) |
| `app/Models/RawDocServiceDetail.php` | Cekat (static `resolveByTreatment`) |
| `app/Models/RawDoctor.php` | Cekat (static `resolveByIdOrName`) |
| `routes/web.php` | Omnicare (3 grup route) |
| `app/Http/Controllers/Crud/CustomerController.php` | Durasi-cabang (cross-cabang lookup) **dan** Omnicare (`buildTimeline()`) |
| `composer.json` / `composer.lock` | Omnicare (`intervention/image:^3`) |

**Implikasi**: kalau kamu mau deploy stream "satu per satu" (bertahap), upload file
gabungan di atas akan tetap terjadi dari awal — tapi efek runtime baru muncul
saat SQL & cache di-refresh. Jadi urutan deploy yang aman:

---

## 3. Urutan deploy yang DIREKOMENDASIKAN (bertahap)

### Sesi 1 — Stream A (Durasi per-cabang) — paling cepat, prasyarat B
1. Backup DB (phpMyAdmin → Export DB `u695655413_app_sweettreat`).
2. Jalankan SQL: `database/sql/2026_05_21_per_branch_doctor_duration.sql`.
3. Cek: `SHOW TABLES LIKE 'raw_doctor_branch_%';` → 2 tabel muncul.
4. (Opsional) test cepat fitur per-branch durasi via UI CRUD reservasi.
5. ✅ Selesai — STOP DI SINI. Tunggu konfirmasi sebelum lanjut Sesi 2.

### Sesi 2 — Stream B (Cekat v1.3 + v1.4) — singkat
Pre-req: Sesi 1 sudah lewat (chain durasi siap untuk sub_service).
1. Backup file lama (download via FileZilla → folder lokal `backup_prod_YYYYMMDD/`):
   - `app/Http/Controllers/Api/ApiCekatController.php`
   - `app/Http/Controllers/Concerns/ResolvesDoctorAvailability.php`
   - `app/Models/RawDoctor.php`, `app/Models/RawDocServiceDetail.php`
   - `app/Http/Kernel.php`, `routes/cekat.php`
   - `app/Providers/RouteServiceProvider.php`, `app/Http/Middleware/VerifyCsrfToken.php`
2. Upload **NEW** files (kalau belum ada di prod):
   - `app/Http/Middleware/VerifyApiClientToken.php` (mungkin sudah dari v1.0 base)
   - `app/Console/Commands/IssueApiClientToken.php` (mungkin sudah)
3. Upload **MODIFIED** files (timpa) — pakai daftar di `cekat-DEPLOY.md` §1B/§1C.
4. Hapus cache via FileZilla: `bootstrap/cache/routes.php`, `config.php`, `services.php`.
   (Atau via CLI: `php artisan route:clear && php artisan config:clear`.)
5. Verifikasi route: `php artisan route:list | grep apicekat` → harus **14 route**.
6. (Token Cekat sandbox/live sudah ada di prod dari v1.0 base — TIDAK perlu issue ulang.)
7. Test lewat `cekat-TEST_FLOW.html` (33 test) atau `cekat-api.http` blok 8 & 9 & 7b.
8. ✅ Kirim PDF v1.4 (`docs/api/cekat-api.pdf`) ke tim Cekat.

### Sesi 3 — Stream C (Omnicare) — paling besar (~1-2 jam total)
Pre-req: Sesi 1 & 2 sudah lewat (Kernel.php sudah punya alias Cekat + cron Omnicare bareng).
**Ikut `docs/deploy/test-flow-deploy-prod.md` dari Fase 1 sampai Fase 11.** Master doc itu
sudah lengkap untuk Omnicare. Catatan tambahan:
- Saat upload `app/Console/Kernel.php` (Fase 1), file itu **sudah merged** dgn
  alias Cekat `api.client` dari Sesi 2 — tidak masalah, dua-duanya valid.
- Cron Omnicare 3x (sync/match/reconcile) baru aktif setelah cron entry
  Hostinger ditambah di Fase 11.

---

## 4. SQL — daftar pending lengkap (semua stream)

Urut tanggal (= urut dependensi):

| SQL | Stream | Idempoten? | Catatan |
|---|---|---|---|
| `2026_05_21_per_branch_doctor_duration.sql` | A (+ B share) | ✅ Ya (`CREATE TABLE IF NOT EXISTS`) | Sesi 1 |
| `2026_05_20_omnicare_tables.sql` | C | ✅ Ya | Sesi 3 — SQL #1 master doc |
| `2026_05_21_customer_omnicare_link.sql` | C | ✅ Ya | Sesi 3 — SQL #2 |
| `2026_05_21_omnicare_report_access.sql` | C | ⚠️ Cek — kalau `INSERT INTO access_path` belum guard, bisa duplikat. Sudah ada di prod? cek `SELECT * FROM access_path WHERE path LIKE '%report%';` | Sesi 3 — SQL #3 |
| `2026_05_21_reconcile_access.sql` | C | ⚠️ Sama, cek dulu | Sesi 3 — SQL #4 |

**SQL Cekat 3 file** (`cekat_service_code`, `cekat_dev_tables`, `api_clients`) =
**SUDAH ada di prod** dari Cekat v1.0 base (commit `e5e098d`). **JANGAN dijalankan
ulang** — `ALTER TABLE ADD COLUMN` akan error kalau kolom sudah ada.

**SQL `contact_anchored_patients` & `contact_backfill`** (May 18) = **SUDAH** dari
commit `5586ea7`. Skip.

---

## 5. Fallback per masalah umum

| Masalah | Penyebab paling sering | Cek |
|---|---|---|
| Endpoint Cekat 404 | `routes/cekat.php` lupa upload, atau `RouteServiceProvider` lama, atau cache route belum dihapus | `php artisan route:list \| grep apicekat` |
| Endpoint Cekat 401 walau token benar | `Kernel.php` lupa upload (alias `api.client` tak ada), atau middleware file `VerifyApiClientToken.php` tak ada | grep file existence + `php artisan route:list` |
| `jadwal_dokter` slot interval salah | SQL `per_branch_doctor_duration.sql` belum dijalankan (Sesi 1) | `SHOW TABLES LIKE 'raw_doctor_branch_%';` |
| Omnicare command "class not found" | `composer install` lupa | `composer install --no-dev --optimize-autoloader` |
| Omnicare cron tak jalan | Cron entry Hostinger belum di-add | `crontab -l` di Terminal hPanel |

---

## 6. Per-stream detail doc

| Untuk | Buka |
|---|---|
| Test Cekat (lokal & prod) | `docs/api/cekat-TEST_FLOW.html` |
| Spec API Cekat (kirim ke vendor) | `docs/api/cekat-api.pdf` |
| Tester REST Client Cekat | `docs/api/cekat-api.http` |
| Deploy Cekat detail (file manifest) | `docs/deploy/cekat-DEPLOY.md` |
| Test Durasi-Cabang & Cross-Cabang | `docs/arsip/test-flow/durasi-akses-cabang-TEST_FLOW.html` |
| Deploy Omnicare (11 fase step-by-step) | `docs/deploy/test-flow-deploy-prod.md` (atau `.html`) |
| File manifest Omnicare | `docs/deploy/omnicare-DEPLOY.md` |

---

## 7. Pertanyaan / kebuntuan

Tanya **Ekky** (`ekky@rblas.id`) — terutama untuk:
- Cek apakah SQL tertentu sudah dijalankan di prod
- Credentials Omnicare (`OMNICARE_USERNAME`/`PASSWORD`)
- Token Cekat live (kalau perlu rotate)
