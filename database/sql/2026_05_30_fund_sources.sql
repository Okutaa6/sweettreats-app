-- =====================================================================
-- Fund Sources (sumber dana) — 2026-05-30
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Menambahkan dimensi "sumber dana" ke ledger Petty Cash & Biaya
--   Transport agar setiap cash in / cash out tercatat berasal dari /
--   masuk ke media penyimpanan apa (Cash, Bank BCA, GoPay, dll).
--
-- Yang dibuat:
--   1. Tabel master `fund_sources` (Cash, daftar Bank, daftar E-Wallet).
--   2. Kolom `fund_source_id` nullable di:
--        - petty_cash_transactions
--        - transport_cost_transactions
--   3. Seed default sumber dana yang umum dipakai (BCA, BSI, BRI,
--      Mandiri, BNI, GoPay, Dana, OVO, ShopeePay, QRIS, Cash).
--
-- Catatan:
--   - fund_source_id dibuat NULLable agar transaksi LAMA tetap valid
--     (di UI akan tampil sebagai "Belum dikategorikan").
--   - Opening balance periode lama diasumsikan masuk source "Cash"
--     saat agregasi di sisi aplikasi (lihat PettyCashController::buildRows).
--   - Berlaku untuk kedua modul (petty_cash & transport) karena keduanya
--     menggunakan controller & view yang sama.
-- =====================================================================

-- ────────────────────────────────────────────────────────────────────
-- 1. CREATE TABLE — master sumber dana
-- ────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `fund_sources` (
    `id`         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `category`   varchar(20) NOT NULL,
    `code`       varchar(50) NOT NULL,
    `name`       varchar(100) NOT NULL,
    `sort_order` int(10) UNSIGNED NOT NULL DEFAULT '0',
    `is_active`  tinyint(1) NOT NULL DEFAULT '1',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fund_sources_code` (`code`),
    KEY `idx_fund_sources_category` (`category`),
    KEY `idx_fund_sources_active`   (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────────────
-- 2. ALTER — tambah kolom fund_source_id ke tabel transaksi
--    Idempotent via INFORMATION_SCHEMA check (kompatibel MySQL 5.7+ /
--    MariaDB) — tidak fail kalau kolom sudah ada.
-- ────────────────────────────────────────────────────────────────────

-- a) petty_cash_transactions.fund_source_id
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_transactions'
      AND COLUMN_NAME  = 'fund_source_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `petty_cash_transactions`
        ADD COLUMN `fund_source_id` bigint(20) UNSIGNED NULL AFTER `amount`,
        ADD KEY `idx_pc_fund_source` (`fund_source_id`)',
    'SELECT "petty_cash_transactions.fund_source_id already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- b) transport_cost_transactions.fund_source_id
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'transport_cost_transactions'
      AND COLUMN_NAME  = 'fund_source_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `transport_cost_transactions`
        ADD COLUMN `fund_source_id` bigint(20) UNSIGNED NULL AFTER `amount`,
        ADD KEY `idx_tc_fund_source` (`fund_source_id`)',
    'SELECT "transport_cost_transactions.fund_source_id already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ────────────────────────────────────────────────────────────────────
-- 3. SEED — sumber dana default
--    INSERT IGNORE (UNIQUE KEY `code`) → aman dijalankan berulang,
--    tidak akan menimpa nama yang sudah diubah admin.
-- ────────────────────────────────────────────────────────────────────

INSERT IGNORE INTO `fund_sources` (`category`, `code`, `name`, `sort_order`, `is_active`) VALUES
    ('cash',    'cash',           'Cash / Tunai',  1,  1),
    ('bank',    'bank_bca',       'Bank BCA',     10, 1),
    ('bank',    'bank_bsi',       'Bank BSI',     11, 1),
    ('bank',    'bank_bri',       'Bank BRI',     12, 1),
    ('bank',    'bank_mandiri',   'Bank Mandiri', 13, 1),
    ('bank',    'bank_bni',       'Bank BNI',     14, 1),
    ('ewallet', 'ewallet_gopay',  'GoPay',        20, 1),
    ('ewallet', 'ewallet_dana',   'Dana',         21, 1),
    ('ewallet', 'ewallet_ovo',    'OVO',          22, 1),
    ('ewallet', 'ewallet_shopee', 'ShopeePay',    23, 1),
    ('ewallet', 'ewallet_qris',   'QRIS',         24, 1);

-- ────────────────────────────────────────────────────────────────────
-- 4. ALTER — kolom branding (brand_color, short_label, logo_url)
--    brand_color : warna brand resmi (hex), dipakai background badge
--    short_label : teks pendek di badge (mis. "BCA", "OVO", "Go")
--    logo_url    : path file logo asli (opsional). Kalau diisi, UI akan
--                  menampilkan <img> alih-alih badge inisial.
--    Idempotent via INFORMATION_SCHEMA check.
-- ────────────────────────────────────────────────────────────────────

-- a) brand_color
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fund_sources' AND COLUMN_NAME = 'brand_color'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `fund_sources` ADD COLUMN `brand_color` varchar(7) NULL AFTER `name`',
    'SELECT "fund_sources.brand_color already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- b) short_label
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fund_sources' AND COLUMN_NAME = 'short_label'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `fund_sources` ADD COLUMN `short_label` varchar(10) NULL AFTER `brand_color`',
    'SELECT "fund_sources.short_label already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- c) logo_url
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fund_sources' AND COLUMN_NAME = 'logo_url'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `fund_sources` ADD COLUMN `logo_url` varchar(255) NULL AFTER `short_label`',
    'SELECT "fund_sources.logo_url already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ────────────────────────────────────────────────────────────────────
-- 5. SEED branding default
--    Hanya isi yang brand_color masih NULL → tidak menimpa perubahan
--    yang sudah dilakukan admin.
-- ────────────────────────────────────────────────────────────────────

UPDATE `fund_sources` SET `brand_color` = '#16a34a', `short_label` = 'Rp'  WHERE `code` = 'cash'           AND `brand_color` IS NULL;
UPDATE `fund_sources` SET `brand_color` = '#0060af', `short_label` = 'BCA' WHERE `code` = 'bank_bca'       AND `brand_color` IS NULL;
UPDATE `fund_sources` SET `brand_color` = '#167b3a', `short_label` = 'BSI' WHERE `code` = 'bank_bsi'       AND `brand_color` IS NULL;
UPDATE `fund_sources` SET `brand_color` = '#003876', `short_label` = 'BRI' WHERE `code` = 'bank_bri'       AND `brand_color` IS NULL;
UPDATE `fund_sources` SET `brand_color` = '#003d79', `short_label` = 'MDR' WHERE `code` = 'bank_mandiri'   AND `brand_color` IS NULL;
UPDATE `fund_sources` SET `brand_color` = '#f37423', `short_label` = 'BNI' WHERE `code` = 'bank_bni'       AND `brand_color` IS NULL;
UPDATE `fund_sources` SET `brand_color` = '#00aed6', `short_label` = 'Go'  WHERE `code` = 'ewallet_gopay'  AND `brand_color` IS NULL;
UPDATE `fund_sources` SET `brand_color` = '#118eea', `short_label` = 'DN'  WHERE `code` = 'ewallet_dana'   AND `brand_color` IS NULL;
UPDATE `fund_sources` SET `brand_color` = '#4c2a86', `short_label` = 'OVO' WHERE `code` = 'ewallet_ovo'    AND `brand_color` IS NULL;
UPDATE `fund_sources` SET `brand_color` = '#ee4d2d', `short_label` = 'SP'  WHERE `code` = 'ewallet_shopee' AND `brand_color` IS NULL;
UPDATE `fund_sources` SET `brand_color` = '#c8161d', `short_label` = 'QR'  WHERE `code` = 'ewallet_qris'   AND `brand_color` IS NULL;
