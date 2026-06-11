-- =====================================================================
-- Fix: drop old uq_tc_period — 2026-06-03
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Konteks:
--   Migrasi 2026_06_03_petty_cash_per_branch.sql menghapus unique key
--   lama (year, month) dan menggantinya dengan (year, month, branch).
--   Tapi nama unique key di transport_cost_periods adalah `uq_tc_period`
--   (bukan `uq_period` seperti yang diasumsikan).
--   Akibatnya: key lama masih ada, INSERT periode bulan yang sama (di
--   cabang berbeda) di-block dengan UniqueConstraintViolation.
--
-- File ini menambal: drop `uq_tc_period` kalau masih ada.
-- =====================================================================

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

-- Defensif: pastikan unique key (year, month, branch) sudah ada. Kalau
-- migrasi sebelumnya tidak jalan sempurna, ini akan create.
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
