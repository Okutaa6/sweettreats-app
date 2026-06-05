-- =====================================================================
-- Add is_active flag to raw_doctor — 2026-06-02
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Menandai dokter aktif/nonaktif. Dokter nonaktif TIDAK muncul di
--   dropdown form reservasi baru (`/rawreservation/new`) dan tidak muncul
--   di endpoint API `/api/reservation/doctors/...`. Reservasi yang sudah
--   terjadwal dengan dokter ini TIDAK terpengaruh — tetap valid.
--
--   Kolom default `1` supaya semua dokter existing otomatis aktif tanpa
--   perlu data fix manual.
-- =====================================================================

-- 1) Tambah kolom is_active kalau belum ada (cek information_schema dulu
--    supaya idempotent di MySQL < 8.0.29 yg belum punya IF NOT EXISTS
--    pada ADD COLUMN).
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'raw_doctor'
      AND COLUMN_NAME  = 'is_active'
);

SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `raw_doctor` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `photo`',
    'SELECT ''Column is_active sudah ada — skip.'' AS info'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Backfill: set 1 untuk baris yang NULL (jaga-jaga kalau kolom sudah
--    pernah ditambah tanpa DEFAULT). Baris yang sudah deliberately 0
--    (sales/admin sengaja nonaktifkan) TIDAK diubah.
UPDATE `raw_doctor`
SET `is_active` = 1
WHERE `is_active` IS NULL;

-- 3) Tambahkan index supaya filter `WHERE is_active = 1` di query dokter
--    tetap cepat walau tabel membesar.
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'raw_doctor'
      AND INDEX_NAME   = 'idx_raw_doctor_is_active'
);

SET @idx_ddl := IF(@idx_exists = 0,
    'CREATE INDEX `idx_raw_doctor_is_active` ON `raw_doctor` (`is_active`)',
    'SELECT ''Index idx_raw_doctor_is_active sudah ada — skip.'' AS info'
);

PREPARE stmt FROM @idx_ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================================
-- VERIFY (opsional — jalankan untuk cek hasil):
--
-- DESCRIBE `raw_doctor`;
-- SELECT `is_active`, COUNT(*) FROM `raw_doctor` GROUP BY `is_active`;
-- SHOW INDEX FROM `raw_doctor` WHERE Key_name = 'idx_raw_doctor_is_active';
--
-- ROLLBACK (kalau perlu balik):
--
-- ALTER TABLE `raw_doctor` DROP INDEX `idx_raw_doctor_is_active`;
-- ALTER TABLE `raw_doctor` DROP COLUMN `is_active`;
-- =====================================================================
