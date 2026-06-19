-- =====================================================================
-- Petty Cash & Transport — Category column — 2026-06-10
-- IDEMPOTENT via INFORMATION_SCHEMA check.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Tambah kolom `category` (varchar, nullable) ke tabel transaksi
--   petty_cash & transport untuk klasifikasi pengeluaran (Konsumsi,
--   Transport, ATK, Bensin, Operasional, Lain-lain). Sebelum ini,
--   reporting harus parsing dari description string bebas.
--
-- Catatan:
--   - Nullable agar transaksi lama tetap valid (akan tampil "—" di UI).
--   - Hanya berlaku untuk baris pengeluaran (direction='out' &
--     is_topup=0 & is_transfer=0). Untuk top up & transfer kolom ini
--     biasanya NULL — kategori tidak relevan.
--   - Index ditambah untuk filter/aggregate report ke depan.
-- =====================================================================

-- a) petty_cash_transactions.category
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_transactions'
      AND COLUMN_NAME  = 'category'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `petty_cash_transactions`
        ADD COLUMN `category` varchar(32) NULL DEFAULT NULL
            COMMENT "Kategori pengeluaran: makan, transport, atk, bensin, operasional, lain_lain. NULL untuk top up / transfer."
            AFTER `description`,
        ADD KEY `idx_pc_category` (`category`)',
    'SELECT "petty_cash_transactions.category already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- b) transport_cost_transactions.category
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'transport_cost_transactions'
      AND COLUMN_NAME  = 'category'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `transport_cost_transactions`
        ADD COLUMN `category` varchar(32) NULL DEFAULT NULL
            COMMENT "Kategori pengeluaran: makan, transport, atk, bensin, operasional, lain_lain. NULL untuk top up / transfer."
            AFTER `description`,
        ADD KEY `idx_tc_category` (`category`)',
    'SELECT "transport_cost_transactions.category already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
