-- =====================================================================
-- Per-source opening balances snapshot — 2026-06-03
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Fix bug: saat ganti bulan, semua saldo per source (BCA, BSI, GoPay…)
--   pindah jadi Cash karena opening_balance disimpan sebagai 1 angka
--   skalar, lalu di-attach ke Cash saja saat render.
--
--   Solusi: kolom JSON `source_opening_balances` menyimpan distribusi
--   per source di awal periode, format {"<source_id>": <amount>}.
--   - Diisi otomatis saat periode baru dibuat (ensurePeriod) —
--     mengambil closing per source dari periode sebelumnya.
--   - Diisi lazy (self-heal) di render kalau periode lama masih NULL,
--     supaya tidak butuh script backfill manual.
--   - NULL di backward-compat = fallback ke "semua opening masuk Cash"
--     (perilaku lama, tidak crash).
--
-- Berlaku untuk petty_cash_periods & transport_cost_periods.
-- =====================================================================

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'petty_cash_periods'
      AND COLUMN_NAME = 'source_opening_balances'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `petty_cash_periods`
        ADD COLUMN `source_opening_balances` JSON NULL AFTER `opening_balance`',
    'SELECT "petty_cash_periods.source_opening_balances already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transport_cost_periods'
      AND COLUMN_NAME = 'source_opening_balances'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `transport_cost_periods`
        ADD COLUMN `source_opening_balances` JSON NULL AFTER `opening_balance`',
    'SELECT "transport_cost_periods.source_opening_balances already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- ROLLBACK (kalau perlu balik):
--
-- ALTER TABLE `petty_cash_periods`     DROP COLUMN `source_opening_balances`;
-- ALTER TABLE `transport_cost_periods` DROP COLUMN `source_opening_balances`;
-- =====================================================================
