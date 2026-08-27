-- =====================================================================
-- Pasien Baru (Lead) — slug cabang + kolom lead di raw_customer — 2026-08-15
-- IDEMPOTENT via INFORMATION_SCHEMA check.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Nomor Cekat AI disebar ke sosmed. Calon pasien baru diarahkan ke link
--   form ringan PER CABANG (mis. /pasien-baru/bintaro). Submit menyimpan
--   "lead" di raw_customer (source_app='pasien_baru'). Sales follow-up via
--   WA di tab "Pasien Baru" pada halaman reservasi, lalu klik "Tambah
--   Reservasi" (pasien sudah ter-prefill & tersimpan).
--
-- Perubahan:
--   1. raw_branch.slug            → slug rapi untuk URL publik per cabang.
--   2. raw_customer.lead_status   → baru | dihubungi | dijadwalkan | batal
--                                   (NULL = bukan lead / pasien biasa).
--   3. raw_customer.lead_followed_up_at / lead_followed_up_by → jejak follow-up.
--
-- Catatan:
--   - Keluhan lead disimpan di kolom `catatan` (SUDAH ADA) — tidak ada kolom baru.
--   - Slug cabang di-auto-generate dari nama (lowercase, karakter non-URL
--     dibuang, spasi→'-'). Bisa dirapikan lewat UI: Cabang → Edit → Slug URL.
--   - Bila DB sudah pernah menerima versi awal file ini (UNIQUE dipasang
--     sebelum UPDATE), jalankan juga 2026_08_27_pasien_baru_lead_fix.sql.
-- =====================================================================

-- ── 1. raw_branch.slug ───────────────────────────────────────────────
SET @has_slug := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'raw_branch' AND COLUMN_NAME = 'slug'
);
SET @sql := IF(@has_slug = 0,
    'ALTER TABLE `raw_branch`
        ADD COLUMN `slug` VARCHAR(120) NULL DEFAULT NULL
        COMMENT "Slug URL publik form pasien baru per cabang (mis. bintaro)."',
    'SELECT "raw_branch.slug already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Auto-generate slug dari nama untuk cabang yang belum punya slug.
-- URGENT: UPDATE ini WAJIB jalan SEBELUM UNIQUE dipasang. Kalau UNIQUE lebih
-- dulu, dua cabang yang menghasilkan slug sama bikin SELURUH statement ini
-- gagal (Duplicate entry) — bukan cuma baris yang bentrok.
-- Karakter non-URL (kurung, titik, koma, kutip, &, /, _) dibuang/diganti dulu
-- supaya slug aman dipakai di URL: "BSD (Ruko)" → "bsd-ruko".
UPDATE `raw_branch`
   SET `slug` = LOWER(TRIM(`name`))
 WHERE (`slug` IS NULL OR `slug` = '')
   AND `name` IS NOT NULL AND `name` <> '';

-- Pembersihan karakter, satu langkah satu karakter. Tiap UPDATE hanya
-- menyentuh baris yang masih mengandung karakter tsb → aman diulang.
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '&', ' dan ') WHERE `slug` LIKE '%&%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '/', ' ')     WHERE `slug` LIKE '%/%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '_', ' ')     WHERE `slug` LIKE '%\_%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '(', '')      WHERE `slug` LIKE '%(%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, ')', '')      WHERE `slug` LIKE '%)%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '.', '')      WHERE `slug` LIKE '%.%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, ',', '')      WHERE `slug` LIKE '%,%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '''', '')     WHERE `slug` LIKE '%''%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '"', '')      WHERE `slug` LIKE '%"%';

UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '  ', ' ') WHERE `slug` LIKE '%  %';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '  ', ' ') WHERE `slug` LIKE '%  %';

UPDATE `raw_branch` SET `slug` = REPLACE(TRIM(`slug`), ' ', '-') WHERE `slug` LIKE '% %';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '--', '-')      WHERE `slug` LIKE '%--%';
UPDATE `raw_branch` SET `slug` = TRIM(BOTH '-' FROM `slug`)      WHERE `slug` LIKE '-%' OR `slug` LIKE '%-';

-- Unique index untuk slug (idempotent). Dipasang PALING AKHIR.
SET @has_uq := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'raw_branch' AND INDEX_NAME = 'uq_raw_branch_slug'
);
SET @sql := IF(@has_uq = 0,
    'ALTER TABLE `raw_branch` ADD UNIQUE KEY `uq_raw_branch_slug` (`slug`)',
    'SELECT "uq_raw_branch_slug already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2-3. raw_customer: kolom lead ────────────────────────────────────
SET @has_ls := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'raw_customer' AND COLUMN_NAME = 'lead_status'
);
SET @sql := IF(@has_ls = 0,
    'ALTER TABLE `raw_customer`
        ADD COLUMN `lead_status` VARCHAR(20) NULL DEFAULT NULL
        COMMENT "Status follow-up lead: baru|dihubungi|dijadwalkan|batal. NULL = bukan lead."',
    'SELECT "raw_customer.lead_status already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_lfa := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'raw_customer' AND COLUMN_NAME = 'lead_followed_up_at'
);
SET @sql := IF(@has_lfa = 0,
    'ALTER TABLE `raw_customer`
        ADD COLUMN `lead_followed_up_at` TIMESTAMP NULL DEFAULT NULL
        COMMENT "Waktu terakhir lead di-follow-up sales."',
    'SELECT "raw_customer.lead_followed_up_at already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_lfb := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'raw_customer' AND COLUMN_NAME = 'lead_followed_up_by'
);
SET @sql := IF(@has_lfb = 0,
    'ALTER TABLE `raw_customer`
        ADD COLUMN `lead_followed_up_by` VARCHAR(255) NULL DEFAULT NULL
        COMMENT "user.id sales yang terakhir follow-up lead."',
    'SELECT "raw_customer.lead_followed_up_by already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
