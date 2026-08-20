-- =====================================================================
-- Petty Cash — penanda "siapa yang set rincian wallet" (serah terima rolling)
-- 2026-08-18. IDEMPOTENT via INFORMATION_SCHEMA check.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Saat operator rolling masuk ke cabang tujuan, halaman petty cash tampil
--   "kosong" (Setoran Awal dulu) sampai DIA sendiri mendeklarasikan rincian
--   wallet-nya — karena cara tiap orang menyimpan uang beda-beda. Untuk tahu
--   apakah rincian wallet sekarang sudah di-set oleh operator yang sedang
--   login, periode menyimpan `opening_set_by` = user id terakhir yang
--   melakukan Setoran Awal.
--
--   NULL = legacy / belum pernah di-set lewat fitur ini (tidak dipaksa setup
--   untuk operator home, hanya untuk operator rolling).
-- =====================================================================

-- 1) opening_set_by -----------------------------------------------------
SET @has_col := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_periods'
      AND COLUMN_NAME  = 'opening_set_by'
);
SET @sql := IF(@has_col = 0,
    'ALTER TABLE `petty_cash_periods`
        ADD COLUMN `opening_set_by` VARCHAR(36) NULL DEFAULT NULL
        COMMENT "User id terakhir yang set rincian wallet (Setoran Awal). Dipakai deteksi serah terima rolling."
        AFTER `source_opening_balances`',
    'SELECT "petty_cash_periods.opening_set_by already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) handover_at --------------------------------------------------------
-- Titik serah terima: saat operator rolling setoran awal, saldo & tabel
-- mulai BERSIH dari titik ini (transaksi operator sebelumnya tetap ada di
-- DB sebagai histori, tapi tidak ikut ke saldo/tabel operator baru).
-- NULL = periode biasa (tanpa serah terima) → semua transaksi dihitung.
SET @has_ho := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_periods'
      AND COLUMN_NAME  = 'handover_at'
);
SET @sql := IF(@has_ho = 0,
    'ALTER TABLE `petty_cash_periods`
        ADD COLUMN `handover_at` DATETIME NULL DEFAULT NULL
        COMMENT "Titik serah terima rolling — saldo/tabel operator baru mulai dari sini."
        AFTER `opening_set_by`',
    'SELECT "petty_cash_periods.handover_at already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
