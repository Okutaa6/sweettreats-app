-- =====================================================================
-- Petty Cash (kas kecil) — 2026-05-20
-- IDEMPOTENT: aman dijalankan berapa kali pun (CREATE IF NOT EXISTS +
-- ALTER untuk auto-upgrade schema lama).
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Buku kas kecil dengan model IMPREST (dana tetap). float_amount =
--   1.500.000 sebagai target imprest. Saldo awal default 0 — user top
--   up dulu untuk mengisi kas.
--   Dua tabel:
--     - petty_cash_periods       : satu baris per (tahun, bulan), simpan
--                                  opening_balance & closing_balance.
--     - petty_cash_transactions  : ledger (cash in / cash out / top up).
--   Saldo berjalan TIDAK disimpan permanen — dihitung saat render.
--
-- Catatan upgrade:
--   - File ini mencakup ALTER untuk DB yang sudah punya schema lama
--     (created_by int(11) → varchar(36); opening_balance reset ke 0
--     hanya untuk periode yang BELUM ada transaksinya).
--   - Aman dijalankan di DB fresh maupun yang sudah punya tabel ini.
-- =====================================================================

-- ────────────────────────────────────────────────────────────────────
-- 1. CREATE TABLE — fresh install
-- ────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `petty_cash_periods` (
    `id`              int(11) NOT NULL AUTO_INCREMENT,
    `period_year`     smallint(6) NOT NULL,
    `period_month`    tinyint(4) NOT NULL,
    `float_amount`    decimal(15,2) NOT NULL DEFAULT '1500000.00',
    `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
    `closing_balance` decimal(15,2) DEFAULT NULL,
    `is_closed`       tinyint(1) NOT NULL DEFAULT '0',
    `created_at`      datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_period` (`period_year`,`period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `petty_cash_transactions` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `period_id`   int(11) NOT NULL,
    `trx_date`    date NOT NULL,
    `description` varchar(255) NOT NULL,
    `direction`   enum('in','out') NOT NULL,
    `amount`      decimal(15,2) NOT NULL,
    `is_topup`    tinyint(1) NOT NULL DEFAULT '0',
    `ref_no`      varchar(50) DEFAULT NULL,
    `created_by`  varchar(36) DEFAULT NULL,
    `created_at`  datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_period`   (`period_id`),
    KEY `idx_trx_date` (`trx_date`),
    KEY `idx_deleted`  (`deleted_at`),
    CONSTRAINT `fk_pc_period` FOREIGN KEY (`period_id`)
        REFERENCES `petty_cash_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────────────
-- 2. ALTER — auto-upgrade dari schema lama
--    Aman dijalankan di tabel yang sudah punya schema benar (no-op).
-- ────────────────────────────────────────────────────────────────────

-- a) Pastikan kolom created_by adalah varchar(36) (bukan int(11))
--    karena session('id') di project menyimpan UUID, bukan integer.
ALTER TABLE `petty_cash_transactions`
    MODIFY COLUMN `created_by` varchar(36) DEFAULT NULL;

-- ────────────────────────────────────────────────────────────────────
-- 3. SEED & DATA RESET
-- ────────────────────────────────────────────────────────────────────

-- Reset opening_balance ke 0 untuk periode yang BELUM ada transaksinya.
-- Periode yang sudah ada transaksi (real data) TIDAK akan tersentuh.
-- Ini handle kasus DB lama yang ter-seed dengan opening 1.500.000.
UPDATE `petty_cash_periods` p
SET p.opening_balance = 0.00
WHERE NOT EXISTS (
    SELECT 1 FROM `petty_cash_transactions` t
    WHERE t.period_id = p.id
      AND t.deleted_at IS NULL
);

-- Seed periode pertama (bulan/tahun saat ini) kalau belum ada periode
-- sama sekali. Saldo awal 0, target imprest 1.500.000.
INSERT INTO `petty_cash_periods` (period_year, period_month, float_amount, opening_balance)
SELECT YEAR(CURDATE()), MONTH(CURDATE()), 1500000.00, 0.00
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `petty_cash_periods` LIMIT 1);
