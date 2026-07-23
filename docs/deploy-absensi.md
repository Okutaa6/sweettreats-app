# Runbook Deploy — Modul Absensi

Panduan langkah-per-langkah menaikkan `feature/absensi` ke **dev**, lalu ke **prod**.
Ditulis setelah audit 2026-07-23; angka & urutan di sini sudah diverifikasi di database kosong,
bukan perkiraan.

> **Aturan emas:** jangan pernah menjalankan `for f in database/sql/*.sql` secara buta
> tanpa membaca bagian [4](#4-sql-yang-tidak-boleh-dibawa) — ada file yang khusus data lokal
> dan akan salah sasaran di server lain.

---

## 0. Sebelum mulai — cek yang gampang terlewat

| Cek | Kenapa penting |
|---|---|
| **Dev sedang dipakai siapa?** | Per 2026-07-23 dev ada di branch `detail-pasien-dan-media-sosial`. Menimpanya mengganggu pekerjaan orang lain — koordinasikan dulu. |
| **Role `Finance` ada?** | Seluruh surface Finance dijaga `canSeeAllBranches()` yang mencocokkan **nama role** `'Finance'`. Tanpa role itu, tak seorang pun bisa membukanya. Di dev per 2026-07-23: **belum ada**. |
| **Backup DB** | Beberapa skrip melakukan `DELETE`. Lihat bagian [5](#5-skrip-yang-menghapus-data). |

```bash
mysqldump -u USER -p DBNAME \
  group_path group_access access_path users \
  sys_checklist_task sys_checklist_category \
  > backup_pre_absensi_$(date +%F).sql
```

---

## 1. Ambil kode

```bash
php artisan down
git fetch origin
git checkout -B feature/absensi origin/feature/absensi   # dev
# untuk prod nanti: git checkout main && git reset --hard origin/main
composer install --no-dev --optimize-autoloader          # tak ada dependensi baru; opsional
```

---

## 2. Migration Laravel

```bash
php artisan migrate --force
```

Enam migration baru (hirarki checklist division & group_path). Urutan sudah benar dari timestamp-nya.

---

## 3. SQL — urutan aman

Sejak `2026_07_18_absensi_zz_fk.sql` ditambahkan, **urutan alfabet sudah aman** untuk file skema
(sudah diuji di database kosong: 6 file, 0 gagal, `att_day` terbentuk, FK terpasang).

Sebelumnya tidak: `att_day` punya FK inline ke `att_work_rule` yang file-nya justru terurut
belakangan, sehingga `for f in *.sql` gagal dengan error 1005/150 dan `att_day` tak pernah dibuat.

### 3a. Skema (wajib)

```bash
M="mysql -u USER -p DBNAME"
$M < database/sql/2026_07_17_absensi_core.sql        # att_device_user, att_import_batch, att_scan
$M < database/sql/2026_07_18_absensi_approver.sql    # dormant, tak dibaca kode — dibuat saja
$M < database/sql/2026_07_18_absensi_day.sql         # att_day (tanpa FK rule)
$M < database/sql/2026_07_18_absensi_request.sql     # att_request + att_request_approval
$M < database/sql/2026_07_18_absensi_work_rule.sql   # att_work_rule, *_day, *_exception, att_user_rule
$M < database/sql/2026_07_18_absensi_zz_fk.sql       # FK att_day.rule_id — HARUS setelah dua di atas
$M < database/sql/2026_07_21_absensi_period.sql      # att_period
$M < database/sql/2026_07_23_att_request_koreksi.sql # ALTER enum tipe: + 'koreksi'
```

### 3b. Role & menu — **seeder dulu, SQL menyusul**

```bash
php artisan db:seed --class=OrgRoleStructureSeeder --force   # bikin role Finance/IT/Head IT/Content Creator
php artisan db:seed --class=ChecklistDivisionSeeder --force
php artisan db:seed --class=AbsensiAccessPathSeeder --force  # bikin node abs-*/akun-*/pers-*
```

> `AbsensiAccessPathSeeder` memberi grant ke **semua role yang ADA saat itu**, jadi
> `OrgRoleStructureSeeder` **wajib duluan** — kalau tidak, role baru tak kebagian menu.

```bash
$M < database/sql/2026_07_22_checklist_content.sql          # butuh divisi dari ChecklistDivisionSeeder
$M < database/sql/2026_07_23_finance_menu_restructure.sql   # butuh node abs-parent dari seeder
$M < database/sql/2026_07_22_absensi_approval_rule.sql      # butuh role dari seeder
$M < database/sql/2026_07_23_menu_sweet_mates.sql           # aman di mana pun (dijaga per link)
$M < database/sql/2026_07_23_fix_landing_sales.sql          # no-op kalau landing Sales bukan 'dashboard'
$M < database/sql/2026_07_21_absensi_exception_lebaran.sql  # libur nasional, generik
```

### 3c. Seed aturan kerja (wajib — tanpa ini modul DIAM)

```bash
$M < database/sql/2026_07_23_att_work_rule_seed.sql
```

Mengisi 3 aturan (GS, FO-Asdent, Fleksibel) + 14 baris jadwal harian (Senin = klinik tutup).
Tanpa ini `resolveRule()` mengembalikan null untuk semua orang → **nol baris `att_day`** →
rekap Finance & matriks Kehadiran kosong melompong, **tanpa satu pun pesan error**.

---

## 4. SQL yang TIDAK boleh dibawa

| File | Alasan |
|---|---|
| `2026_07_23_att_rule_akun_pegawai.sql` | Merujuk nama orang di DB lokal (`Muhamad Ikhsan`, `Reza Fauzi Ramadhan`, `Admin_Ekky`, `Admin_Reza`, `Irma Islamiati`, `Admin_Nisa`). Di server lain: no-op senyap — atau **salah sasaran** kalau kebetulan ada nama sama, karena ia menghapus `att_user_rule`. |
| `2026_07_23_att_day_yatim_akun_admin.sql` | Sama — pembersihan khusus kekacauan data lokal. |
| `2026_07_23_att_day_masa_depan.sql` | Membersihkan `alpa` bertanggal masa depan yang dihasilkan reconciler versi lama. Reconciler sekarang sudah punya penjaganya, jadi di server baru ini **tak perlu**. Jalankan hanya kalau server itu terlanjur punya data lama. |

---

## 5. Skrip yang menghapus data

Baca sebelum menjalankan:

| File | Yang dihapus | Risiko |
|---|---|---|
| `2026_07_22_checklist_content.sql` | task & kategori ber-id `cat-*`/`task-*` | **Menimpa hasil edit admin** lewat UI Setup Checklist. Cek dulu: `SELECT COUNT(*) FROM sys_checklist_task WHERE category_id LIKE 'cat-%';` |
| `2026_07_22_absensi_approval_rule.sql` | seluruh isi `att_approval_rule` | Sengaja — file ini me-reseed penuh 13 aturan. |
| `2026_07_23_finance_menu_restructure.sql` | grant untuk leaf absensi yang diringkas | Sengaja — menyisakan satu pintu menu. |
| `2026_07_21_absensi_exception_lebaran.sql` | exception ber-alasan `Lebaran %` | Menimpa exception Lebaran yang ditambah manual. |

---

## 6. Folder & izin (tak ada di git)

```bash
mkdir -p public/uploads/att_request storage/app/att_import storage/app/group_access
chown -R www-data:www-data public/uploads/att_request storage/app
chmod -R 775 public/uploads/att_request storage/app
```

Tanpa ini: upload foto bukti pengajuan melempar error, dan upload CSV mesin absen gagal senyap.

---

## 7. Bersihkan cache — `access:rebuild` WAJIB

```bash
php artisan view:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan access:rebuild     # <-- JANGAN DILEWATI
php artisan up
```

**Kenapa `access:rebuild` krusial:** sidebar TIDAK dibaca dari database. `getGroupAccessData()`
membaca file snapshot `storage/app/group_access/{group_id}.txt`. Semua perubahan menu di atas
menulis langsung ke `access_path`/`group_access` lewat SQL — tanpa rebuild, **menu Absensi,
Akun Saya, Persetujuan, dan Petty Cash tidak akan muncul sama sekali.**

---

## 8. Isi data operasional (manual, tak bisa di-seed)

### 8a. `att_user_rule` — per ORANG, bukan per jabatan

**Jangan menurunkan aturan dari jabatan.** Anisa Aulia (SPV Operasional) & Irma Islamiati
(Koordinator Asdent) itu office/manajemen **tapi ikut jam operasional dan punya hak lembur** —
kalau diturunkan dari jabatan, lembur mereka ditolak sistem tiap bulan padahal sah.

```sql
INSERT INTO att_user_rule (user_id, rule_id, effective_from, created_at, updated_at)
SELECT u.id, w.id, '2026-01-01', NOW(), NOW()
FROM users u JOIN att_work_rule w ON w.nama = 'FO-Asdent'   -- atau 'GS' / 'Fleksibel'
WHERE u.nama = 'NAMA KARYAWAN'
  AND NOT EXISTS (SELECT 1 FROM att_user_rule x WHERE x.user_id = u.id);
```

Cek siapa yang belum punya aturan:

```sql
SELECT u.nama, u.email FROM users u
LEFT JOIN att_user_rule ur ON ur.user_id = u.id
WHERE ur.id IS NULL AND u.status = 1 ORDER BY u.nama;
```

### 8b. Mapping mesin → karyawan

Lewat UI: **Absensi → Mapping Karyawan** (ada usulan nama otomatis). Satu orang bisa punya
banyak No.ID di banyak mesin (many-to-one), dan No.ID **bentrok antar cabang** karena tiap
mesin menomori dari 1.

### 8c. Impor scan & reconcile

```bash
php artisan att:import "Bintaro" /path/export_bintaro.csv --by=<user_id>
php artisan att:reconcile 2026-06-01 2026-06-30
```

`att:reconcile` sekarang **menyebutkan nama** karyawan yang punya scan tapi belum punya aturan
kerja — perhatikan peringatan itu, mereka tak dihitung sama sekali.

---

## 9. Verifikasi setelah deploy

```sql
-- aturan persetujuan lengkap (harus 13; kurang = ada nama jabatan yg belum ada)
SELECT COUNT(*) FROM att_approval_rule;

-- aturan kerja terisi (harus 3 dan 14)
SELECT COUNT(*) FROM att_work_rule;      SELECT COUNT(*) FROM att_work_rule_day;

-- tak ada role yang landing-nya menunjuk halaman tanpa grant (harus 0 baris)
SELECT g.nama, g.landing_page FROM group_path g
WHERE g.landing_page IS NOT NULL
  AND SUBSTRING_INDEX(g.landing_page,'/',1) NOT IN ('export','api','auditlog','executive','fund-sources','finance')
  AND NOT EXISTS (SELECT 1 FROM group_access ga JOIN access_path ap ON ap.id=ga.access_id
                  WHERE ga.group_id=g.id AND ap.link=SUBSTRING_INDEX(g.landing_page,'/',1));

-- node sweet-mates tepat 1 (2 = dobel, pencabutan akses akan gagal senyap)
SELECT id, nama FROM access_path WHERE link='sweet-mates';
```

**Uji manual:**

1. Login **Finance** → sidebar: satu item **Absensi** (masuk ke Rekap Persetujuan) + section **Keuangan → Petty Cash**. Buka kelima tab.
2. Login **karyawan** → drop-up akun berisi *Absensi Saya* & *Persetujuan*. Klik satu hari → modal **Koreksi Absensi** muncul.
3. Ajukan cuti sebagai staf → **harus** berstatus *menunggu*, **bukan** langsung disetujui.
   Kalau langsung disetujui, berarti `att_approval_rule` tak nyambung ke role di server ini.
4. Buka `/sweet-mates` **tanpa login** → harus dilempar ke halaman login.

---

## 10. Yang sengaja BELUM diperbaiki

Temuan audit yang diketahui dan diterima untuk sekarang — supaya tidak terlihat seperti kejutan nanti:

| Temuan | Dampak |
|---|---|
| Approve & reject bersamaan tanpa penguncian | Dua atasan menekan tombol berbarengan → hasil akhir tergantung urutan tulis. |
| Periode gaji masa depan bisa ditutup | Salah ketik `periode_to` lalu Tutup → reconcile berhenti menulis; **tak ada tombol buka kembali**. |
| Form bisa diajukan untuk periode yang sudah ditutup | Disetujui tapi tak berefek ke `att_day`; karyawan melihat "disetujui" yang tak mengubah apa pun. |
| Foto bukti di folder publik | `public/uploads/att_request/{user_id}_{epoch}.ext` — bisa ditebak, tanpa gerbang. |
| N+1 di Rekap Finance & Inbox Persetujuan | Lambat saat data setahun masuk. |
| Lembur lintas tengah malam ditolak validator | Klaim 22:00→01:00 tak bisa dikirim. |
| `setoran` tanpa node menu | **Disengaja** — fitur selesai tapi belum diluncurkan (rencana akhir bulan). |
