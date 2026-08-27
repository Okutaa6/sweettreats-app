-- =====================================================================
-- Pasien Baru (Lead) — perbaikan slug + index penunjang — 2026-08-27
-- IDEMPOTENT via INFORMATION_SCHEMA check.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Prasyarat: 2026_08_15_pasien_baru_lead.sql sudah dijalankan.
--
-- Perubahan:
--   1. Rapikan slug cabang yang mengandung karakter non-URL (sisa versi awal
--      auto-generate, mis. "BSD (Ruko)" → "bsd-(ruko)").
--   2. Backfill slug untuk cabang yang masih NULL/kosong.
--   3. Index (source_app, lead_status, raw_branch_id) di raw_customer —
--      dipakai badge tab "Pasien Baru" yang dihitung di SETIAP load halaman
--      reservasi (semua tab), sebelumnya full scan.
--   4. Index raw_customer.phone — dipakai dedup lead & pencarian nomor.
-- =====================================================================

-- ── 1-2. Seed slug dari nama ─────────────────────────────────────────
-- Sasaran: cabang yang slug-nya masih kosong, ATAU slug-nya mengandung
-- karakter di luar [a-z0-9-] (sisa versi awal auto-generate).
UPDATE `raw_branch`
   SET `slug` = LOWER(TRIM(`name`))
 WHERE `name` IS NOT NULL AND `name` <> ''
   AND (`slug` IS NULL OR `slug` = '' OR `slug` REGEXP '[^a-z0-9-]');

-- Pembersihan karakter, satu langkah satu karakter supaya mudah dibaca &
-- diaudit. Tiap UPDATE hanya menyentuh baris yang memang masih mengandung
-- karakter tsb, jadi aman dijalankan berulang kali.
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '&', ' dan ') WHERE `slug` LIKE '%&%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '/', ' ')     WHERE `slug` LIKE '%/%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '_', ' ')     WHERE `slug` LIKE '%\_%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '(', '')      WHERE `slug` LIKE '%(%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, ')', '')      WHERE `slug` LIKE '%)%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '.', '')      WHERE `slug` LIKE '%.%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, ',', '')      WHERE `slug` LIKE '%,%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '''', '')     WHERE `slug` LIKE '%''%';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '"', '')      WHERE `slug` LIKE '%"%';

-- Rapatkan spasi ganda (dua pass menutup kasus spasi tiga/empat).
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '  ', ' ') WHERE `slug` LIKE '%  %';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '  ', ' ') WHERE `slug` LIKE '%  %';

-- Spasi → '-', lalu rapikan '--' dan tanda hubung di ujung.
UPDATE `raw_branch` SET `slug` = REPLACE(TRIM(`slug`), ' ', '-') WHERE `slug` LIKE '% %';
UPDATE `raw_branch` SET `slug` = REPLACE(`slug`, '--', '-')      WHERE `slug` LIKE '%--%';
UPDATE `raw_branch` SET `slug` = TRIM(BOTH '-' FROM `slug`)      WHERE `slug` LIKE '-%' OR `slug` LIKE '%-';

-- Cek manual setelah menjalankan bagian ini — kalau ada baris keluar, dua
-- cabang punya slug sama dan salah satunya harus dirapikan lewat UI
-- (Cabang → Edit → Slug URL) sebelum UNIQUE bisa dipasang:
--   SELECT `slug`, COUNT(*) c FROM `raw_branch`
--    WHERE `slug` IS NOT NULL GROUP BY `slug` HAVING c > 1;

-- ── 3. Index lead di raw_customer ────────────────────────────────────
SET @has_idx_lead := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'raw_customer' AND INDEX_NAME = 'idx_raw_customer_lead'
);
SET @sql := IF(@has_idx_lead = 0,
    'ALTER TABLE `raw_customer`
        ADD INDEX `idx_raw_customer_lead` (`source_app`, `lead_status`, `raw_branch_id`)',
    'SELECT "idx_raw_customer_lead already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4. Index nomor HP di raw_customer ────────────────────────────────
-- Cek berdasarkan KOLOM (bukan nama index) supaya tidak menumpuk index baru
-- bila sudah ada index lain yang berawalan `phone`.
SET @has_idx_phone := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'raw_customer' AND COLUMN_NAME = 'phone' AND SEQ_IN_INDEX = 1
);
SET @sql := IF(@has_idx_phone = 0,
    'ALTER TABLE `raw_customer` ADD INDEX `idx_raw_customer_phone` (`phone`)',
    'SELECT "index on raw_customer.phone already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
