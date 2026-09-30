-- ============================================================================
-- Audit collation sebelum modul Tracking Ortho naik ke prod
-- Tanggal   : 2026-09-24
-- IDEMPOTENT: ya — berkas ini HANYA SELECT. Nol CREATE, nol ALTER, nol
--             INSERT/UPDATE/DELETE. Aman dijalankan berapa kali pun, kapan pun,
--             termasuk di jam sibuk.
-- DB tujuan : prod  u695655413_app_sweettreat   (boleh juga dev / lokal)
--
-- KONTEKS
-- -------
-- Prod jalan di MariaDB 11.x dan sebagian kolomnya memakai
-- utf8mb4_uca1400_ai_ci, sementara sisanya utf8mb4_unicode_ci. Campuran itu
-- yang dulu memunculkan error #1267 saat JOIN raw_branch — dan yang membuat
-- rancangan ort_* memutuskan NOL FOREIGN KEY dan NOL JOIN lintas keluarga.
--
-- Gejala itu MUSTAHIL ketahuan dari lokal. scripts/pull-prod-db.sh sengaja
-- menurunkan uca1400 -> unicode_ci saat impor (kalau tidak, MariaDB 10.4 lokal
-- menolak dengan "Unknown collation"), jadi salinan prod di lokal selalu
-- terlihat seragam. Menarik dump prod TIDAK menggantikan audit ini.
--
-- CARA MENJALANKAN
-- ----------------
--   a) phpMyAdmin Hostinger -> pilih DB prod -> tab SQL -> tempel blok
--      pertanyaan SATU PER SATU (phpMyAdmin hanya menampilkan hasil kueri
--      terakhir kalau dijalankan sekaligus), ATAU
--   b) dari lokal, kalau kunci SSH sudah ada di ~/.ssh/rblas_ga:
--        ssh -p 65002 -i ~/.ssh/rblas_ga u695655413@93.127.219.164 \
--          "cd /home/u695655413/domains/app.sweettreats.id/laravel && \
--           MYSQL_PWD=\$(grep -m1 '^DB_PASSWORD=' .env | cut -d= -f2-) \
--           mysql -u \$(grep -m1 '^DB_USERNAME=' .env | cut -d= -f2-) \
--                 \$(grep -m1 '^DB_DATABASE=' .env | cut -d= -f2-) -t" \
--          < database/sql/2026_09_24_ort_audit_collation_prod.sql
--
-- YANG DICARI
-- -----------
-- Pertanyaan 3 adalah yang menentukan. Kalau ia mengembalikan baris, DDL
-- ort_* TIDAK BOLEH dijalankan apa adanya: kolom penunjuk (raw_customer_id,
-- reservation_id) harus mengikuti collation tabel yang ditunjuknya, atau
-- perbandingan string antar keduanya akan meledak di kemudian hari.
--
-- YANG SENGAJA TIDAK DILAKUKAN
-- ----------------------------
--  - Tidak mengubah collation apa pun. Menyeragamkan collation prod adalah
--    keputusan tersendiri dengan risikonya sendiri (rebuild index, downtime),
--    bukan efek samping dari memasang satu modul.
--  - Tidak menyentuh tabel ort_* (belum ada di prod saat audit ini dijalankan).
-- ============================================================================


-- ── 1. Peta collation seluruh tabel ─────────────────────────────────────────
-- Harapan: satu kelompok besar utf8mb4_unicode_ci. Tiap kelompok lain adalah
-- calon sumber #1267. Catat angkanya — ini potret "sebelum".
SELECT TABLE_COLLATION,
       COUNT(*) AS jumlah_tabel,
       GROUP_CONCAT(TABLE_NAME ORDER BY TABLE_NAME SEPARATOR ', ') AS contoh
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE   = 'BASE TABLE'
GROUP BY TABLE_COLLATION
ORDER BY jumlah_tabel DESC;


-- ── 2. Collation default database itu sendiri ───────────────────────────────
-- Tabel ort_* dibuat dengan COLLATE eksplisit, jadi ini cuma pembanding —
-- tapi kalau default-nya uca1400, tabel BARU yang lupa menyebut COLLATE akan
-- ikut uca1400 tanpa ada yang sadar.
SELECT DEFAULT_CHARACTER_SET_NAME AS charset_default,
       DEFAULT_COLLATION_NAME     AS collation_default
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = DATABASE();


-- ── 3. ⚠ PENENTU: collation kolom yang akan DITUNJUK oleh ort_* ─────────────
-- ort_case.raw_customer_id  -> raw_customer.id
-- ort_control.reservation_id -> raw_cus_reservation.id
-- ort_case.doctor_ref_id     -> (kode Omnicare, bukan raw_doctor.id)
-- ort_*.place_id             -> raw_branch.omnicare_place_id
--
-- Kalau kolom-kolom ini TIDAK utf8mb4_unicode_ci, DDL ort_* harus disesuaikan
-- sebelum dijalankan. Kosong = aman, jalankan skema apa adanya.
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLLATION_NAME IS NOT NULL
  AND COLLATION_NAME <> 'utf8mb4_unicode_ci'
  AND (
        (TABLE_NAME = 'raw_customer'        AND COLUMN_NAME = 'id')
     OR (TABLE_NAME = 'raw_cus_reservation' AND COLUMN_NAME = 'id')
     OR (TABLE_NAME = 'raw_branch'          AND COLUMN_NAME IN ('id', 'omnicare_place_id'))
     OR (TABLE_NAME = 'raw_doctor'          AND COLUMN_NAME = 'id')
     OR (TABLE_NAME LIKE 'omnicare\_%'      AND COLUMN_NAME IN ('p_id', 'place_id', 'trx_id'))
  )
ORDER BY TABLE_NAME, COLUMN_NAME;


-- ── 4. Versi server ─────────────────────────────────────────────────────────
-- Menentukan apa yang boleh dipakai: window function & CTE butuh MariaDB 10.2+
-- / MySQL 8. Rancangan ort_* sengaja menghindari keduanya, tapi angka ini perlu
-- tercatat sebelum ada yang "menyederhanakan" kueri dengan ROW_NUMBER().
SELECT VERSION() AS versi_server;


-- ── 5. Tabel prasyarat: ada, dan berapa isinya ──────────────────────────────
-- Penyemai (ort:seed-cases) dan pencocok malam (ort:pull-visits) membaca dari
-- sini. Nol baris di salah satunya = tahap itu belum bisa dijalankan.
SELECT 'raw_customer'          AS tabel, COUNT(*) AS baris FROM raw_customer
UNION ALL SELECT 'raw_branch',           COUNT(*) FROM raw_branch
UNION ALL SELECT 'raw_doctor',           COUNT(*) FROM raw_doctor
UNION ALL SELECT 'raw_cus_reservation',  COUNT(*) FROM raw_cus_reservation
UNION ALL SELECT 'omnicare_sales_detail',COUNT(*) FROM omnicare_sales_detail
UNION ALL SELECT 'omnicare_patient',     COUNT(*) FROM omnicare_patient;


-- ── 6. Apakah tabel ort_* sudah ada di sini? ────────────────────────────────
-- Kosong = skema belum pernah dijalankan di environment ini (yang diharapkan
-- untuk prod saat audit ini pertama kali dijalankan).
SELECT TABLE_NAME, TABLE_COLLATION, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'ort\_%'
ORDER BY TABLE_NAME;
