-- =====================================================================
-- Petty Cash & Transport Cost — branch scoping + per-branch fund accounts
-- 2026-06-03
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   1. Pisahkan buku Petty Cash & Biaya Transport per cabang. Periode
--      sekarang di-key (period_year, period_month, raw_branch_id).
--      Sales hanya lihat cabangnya; admin (Super Admin / Admin General /
--      Admin Backup) bisa switch antar cabang via dropdown.
--   2. Per-cabang imprest (float) default — disimpan di raw_branch.
--      Fallback ke konstanta controller (1.5jt petty / 1jt transport).
--   3. Per-cabang nomor rekening / telepon untuk tiap fund source
--      (BCA cabang A ≠ BCA cabang B). Tabel baru fund_source_branch_accounts
--      menggantikan kolom account_number di fund_sources (drop kolom lama).
--
-- Backfill:
--   Semua periode & transaksi existing tanpa branch di-assign ke
--   "default branch" = cabang pertama urut nama (paling awal alfabetik).
--   Kalau setelah dijalankan ada mis-assign, admin bisa pindahkan via
--   query manual (UPDATE … SET raw_branch_id = '<uuid-cabang-lain>').
-- =====================================================================

-- ────────────────────────────────────────────────────────────────────
-- 0. Hitung default branch sekali, simpan di session var
-- ────────────────────────────────────────────────────────────────────

SET @default_branch_id := (
    SELECT `id` FROM `raw_branch` ORDER BY `name` ASC LIMIT 1
);

-- Guard: kalau belum ada cabang sama sekali, batalkan eksekusi blok backfill
-- (CREATE TABLE & ADD COLUMN tetap jalan supaya schema siap).
-- Backfill akan di-skip via IF check di setiap UPDATE.

-- ────────────────────────────────────────────────────────────────────
-- 1. raw_branch — tambah per-branch imprest default
-- ────────────────────────────────────────────────────────────────────

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'raw_branch'
      AND COLUMN_NAME = 'petty_cash_default_float'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `raw_branch` ADD COLUMN `petty_cash_default_float` decimal(15,2) NULL AFTER `name`',
    'SELECT "raw_branch.petty_cash_default_float already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'raw_branch'
      AND COLUMN_NAME = 'transport_cost_default_float'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `raw_branch` ADD COLUMN `transport_cost_default_float` decimal(15,2) NULL AFTER `petty_cash_default_float`',
    'SELECT "raw_branch.transport_cost_default_float already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ────────────────────────────────────────────────────────────────────
-- 2. petty_cash_periods — tambah raw_branch_id, ganti unique key
-- ────────────────────────────────────────────────────────────────────

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'petty_cash_periods'
      AND COLUMN_NAME = 'raw_branch_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `petty_cash_periods` ADD COLUMN `raw_branch_id` char(36) NULL AFTER `period_month`',
    'SELECT "petty_cash_periods.raw_branch_id already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill ke default branch — hanya kalau ada cabang & raw_branch_id masih NULL
SET @sql := IF(@default_branch_id IS NOT NULL,
    CONCAT('UPDATE `petty_cash_periods` SET `raw_branch_id` = ''',
           @default_branch_id,
           ''' WHERE `raw_branch_id` IS NULL'),
    'SELECT "no default branch — skip backfill petty_cash_periods" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Drop unique key lama (year, month) — pakai DROP INDEX guarded via INFORMATION_SCHEMA
SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'petty_cash_periods'
      AND INDEX_NAME = 'uq_period'
);
SET @sql := IF(@idx_exists > 0,
    'ALTER TABLE `petty_cash_periods` DROP INDEX `uq_period`',
    'SELECT "petty_cash_periods.uq_period already dropped" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Bikin unique key baru (year, month, branch)
SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'petty_cash_periods'
      AND INDEX_NAME = 'uq_period_branch'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `petty_cash_periods`
        ADD UNIQUE KEY `uq_period_branch` (`period_year`,`period_month`,`raw_branch_id`)',
    'SELECT "petty_cash_periods.uq_period_branch already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index untuk filter cepat per cabang
SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'petty_cash_periods'
      AND INDEX_NAME = 'idx_pc_periods_branch'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `petty_cash_periods` ADD KEY `idx_pc_periods_branch` (`raw_branch_id`)',
    'SELECT "petty_cash_periods.idx_pc_periods_branch already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ────────────────────────────────────────────────────────────────────
-- 3. transport_cost_periods — sama persis
-- ────────────────────────────────────────────────────────────────────

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transport_cost_periods'
      AND COLUMN_NAME = 'raw_branch_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `transport_cost_periods` ADD COLUMN `raw_branch_id` char(36) NULL AFTER `period_month`',
    'SELECT "transport_cost_periods.raw_branch_id already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@default_branch_id IS NOT NULL,
    CONCAT('UPDATE `transport_cost_periods` SET `raw_branch_id` = ''',
           @default_branch_id,
           ''' WHERE `raw_branch_id` IS NULL'),
    'SELECT "no default branch — skip backfill transport_cost_periods" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- transport_cost_periods bisa punya unique key bernama `uq_period`
-- (skema baru) ATAU `uq_tc_period` (skema asli dari migrasi 2026_05_20).
-- Cek dan drop keduanya kalau ada.
SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transport_cost_periods'
      AND INDEX_NAME = 'uq_period'
);
SET @sql := IF(@idx_exists > 0,
    'ALTER TABLE `transport_cost_periods` DROP INDEX `uq_period`',
    'SELECT "transport_cost_periods.uq_period already dropped" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transport_cost_periods'
      AND INDEX_NAME = 'uq_tc_period'
);
SET @sql := IF(@idx_exists > 0,
    'ALTER TABLE `transport_cost_periods` DROP INDEX `uq_tc_period`',
    'SELECT "transport_cost_periods.uq_tc_period already dropped" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transport_cost_periods'
      AND INDEX_NAME = 'uq_period_branch'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `transport_cost_periods`
        ADD UNIQUE KEY `uq_period_branch` (`period_year`,`period_month`,`raw_branch_id`)',
    'SELECT "transport_cost_periods.uq_period_branch already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transport_cost_periods'
      AND INDEX_NAME = 'idx_tc_periods_branch'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE `transport_cost_periods` ADD KEY `idx_tc_periods_branch` (`raw_branch_id`)',
    'SELECT "transport_cost_periods.idx_tc_periods_branch already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ────────────────────────────────────────────────────────────────────
-- 4. fund_source_branch_accounts — per-branch nomor rek / telepon
-- ────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `fund_source_branch_accounts` (
    `id`              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `raw_branch_id`   char(36) NOT NULL,
    `fund_source_id`  bigint(20) UNSIGNED NOT NULL,
    `account_number`  varchar(50) NOT NULL,
    `created_at`      datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fsba_branch_source` (`raw_branch_id`,`fund_source_id`),
    KEY `idx_fsba_branch` (`raw_branch_id`),
    KEY `idx_fsba_source` (`fund_source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrasi data lama: kalau fund_sources.account_number sudah pernah diisi
-- (dari migrasi 06_03 yang lama), pindahkan ke tabel baru untuk default
-- branch. Hanya jalan kalau kolom masih ada & nilainya non-null.
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fund_sources'
      AND COLUMN_NAME = 'account_number'
);

SET @sql := IF(@col_exists > 0 AND @default_branch_id IS NOT NULL,
    CONCAT('INSERT IGNORE INTO `fund_source_branch_accounts`
                (`raw_branch_id`, `fund_source_id`, `account_number`)
            SELECT ''', @default_branch_id, ''', `id`, `account_number`
            FROM `fund_sources`
            WHERE `account_number` IS NOT NULL AND `account_number` <> '''''),
    'SELECT "no fund_sources.account_number to migrate" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Drop kolom account_number dari fund_sources — sudah dipindah ke
-- tabel relasi. Penting supaya tidak ada source of truth ganda.
SET @sql := IF(@col_exists > 0,
    'ALTER TABLE `fund_sources` DROP COLUMN `account_number`',
    'SELECT "fund_sources.account_number already removed" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- VERIFY (opsional):
--
-- DESCRIBE `raw_branch`;
-- DESCRIBE `petty_cash_periods`;
-- DESCRIBE `transport_cost_periods`;
-- SHOW CREATE TABLE `fund_source_branch_accounts`;
-- SELECT `raw_branch_id`, COUNT(*) FROM `petty_cash_periods` GROUP BY 1;
-- SELECT `raw_branch_id`, COUNT(*) FROM `transport_cost_periods` GROUP BY 1;
--
-- ROLLBACK (kalau perlu balik — DESTRUKTIF, data per-branch hilang):
--
-- ALTER TABLE `petty_cash_periods` DROP INDEX `uq_period_branch`;
-- ALTER TABLE `petty_cash_periods` DROP INDEX `idx_pc_periods_branch`;
-- ALTER TABLE `petty_cash_periods` DROP COLUMN `raw_branch_id`;
-- ALTER TABLE `petty_cash_periods` ADD UNIQUE KEY `uq_period` (`period_year`,`period_month`);
-- ALTER TABLE `transport_cost_periods` DROP INDEX `uq_period_branch`;
-- ALTER TABLE `transport_cost_periods` DROP INDEX `idx_tc_periods_branch`;
-- ALTER TABLE `transport_cost_periods` DROP COLUMN `raw_branch_id`;
-- ALTER TABLE `transport_cost_periods` ADD UNIQUE KEY `uq_period` (`period_year`,`period_month`);
-- ALTER TABLE `raw_branch` DROP COLUMN `petty_cash_default_float`;
-- ALTER TABLE `raw_branch` DROP COLUMN `transport_cost_default_float`;
-- DROP TABLE `fund_source_branch_accounts`;
-- ALTER TABLE `fund_sources` ADD COLUMN `account_number` varchar(50) NULL AFTER `logo_url`;
-- =====================================================================
