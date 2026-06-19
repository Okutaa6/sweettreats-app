-- =====================================================================
-- Petty Cash & Transport — Edit audit columns — 2026-06-10
-- IDEMPOTENT via INFORMATION_SCHEMA check.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Mendukung fitur "Edit Transaksi (24-jam window)" untuk pengeluaran
--   biasa. Tambah 2 kolom audit:
--     - updated_by  : UUID user yang melakukan edit terakhir
--     - edited_at   : timestamp edit terakhir (NULL = belum pernah diedit)
--
-- Catatan:
--   - Kedua kolom nullable supaya transaksi lama (NULL) tetap valid.
--   - Edit window 24 jam di-enforce di controller, BUKAN di DB level —
--     supaya bisa di-relax/ubah tanpa migration.
--   - Hanya transaksi pengeluaran (direction='out', is_topup=0,
--     is_transfer=0) yang boleh diedit; guard di controller.
-- =====================================================================

-- a) petty_cash_transactions.updated_by + edited_at
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_transactions'
      AND COLUMN_NAME  = 'updated_by'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `petty_cash_transactions`
        ADD COLUMN `updated_by` varchar(36) NULL DEFAULT NULL
            COMMENT "UUID user yang melakukan edit terakhir. NULL = belum pernah diedit."
            AFTER `created_by`',
    'SELECT "petty_cash_transactions.updated_by already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_transactions'
      AND COLUMN_NAME  = 'edited_at'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `petty_cash_transactions`
        ADD COLUMN `edited_at` datetime NULL DEFAULT NULL
            COMMENT "Timestamp edit terakhir. NULL = belum pernah diedit."
            AFTER `updated_at`',
    'SELECT "petty_cash_transactions.edited_at already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- b) transport_cost_transactions.updated_by + edited_at
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'transport_cost_transactions'
      AND COLUMN_NAME  = 'updated_by'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `transport_cost_transactions`
        ADD COLUMN `updated_by` varchar(36) NULL DEFAULT NULL
            COMMENT "UUID user yang melakukan edit terakhir. NULL = belum pernah diedit."
            AFTER `created_by`',
    'SELECT "transport_cost_transactions.updated_by already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'transport_cost_transactions'
      AND COLUMN_NAME  = 'edited_at'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `transport_cost_transactions`
        ADD COLUMN `edited_at` datetime NULL DEFAULT NULL
            COMMENT "Timestamp edit terakhir. NULL = belum pernah diedit."
            AFTER `updated_at`',
    'SELECT "transport_cost_transactions.edited_at already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
