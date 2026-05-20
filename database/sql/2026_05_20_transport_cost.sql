-- =====================================================================
-- Transport Cost (biaya transportasi) — 2026-05-20
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Buku biaya transportasi (Gocar, Gojek, tol, bensin, reimburse driver dll)
--   dengan model IMPREST mirip Petty Cash. Default float Rp 1.000.000.
--   Strukturnya KEMBARAN tabel petty_cash_* — sengaja terpisah supaya
--   laporan kas kecil & biaya transportasi tidak campur.
--
--   Dua tabel:
--     - transport_cost_periods       : 1 baris per (tahun, bulan)
--     - transport_cost_transactions  : ledger (cash in / cash out / top up)
--
--   Saldo berjalan TIDAK disimpan — dihitung saat render.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `transport_cost_periods` (
    `id`              int(11) NOT NULL AUTO_INCREMENT,
    `period_year`     smallint(6) NOT NULL,
    `period_month`    tinyint(4) NOT NULL,
    `float_amount`    decimal(15,2) NOT NULL DEFAULT '1000000.00',
    `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
    `closing_balance` decimal(15,2) DEFAULT NULL,
    `is_closed`       tinyint(1) NOT NULL DEFAULT '0',
    `created_at`      datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tc_period` (`period_year`,`period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transport_cost_transactions` (
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
    KEY `idx_tc_period`   (`period_id`),
    KEY `idx_tc_trx_date` (`trx_date`),
    KEY `idx_tc_deleted`  (`deleted_at`),
    CONSTRAINT `fk_tc_period` FOREIGN KEY (`period_id`)
        REFERENCES `transport_cost_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auto-upgrade kolom created_by ke varchar(36) (jaga-jaga rerun di DB lama).
ALTER TABLE `transport_cost_transactions`
    MODIFY COLUMN `created_by` varchar(36) DEFAULT NULL;

-- Seed periode pertama (bulan ini, opening 0). Idempoten.
INSERT INTO `transport_cost_periods` (period_year, period_month, float_amount, opening_balance)
SELECT YEAR(CURDATE()), MONTH(CURDATE()), 1000000.00, 0.00
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `transport_cost_periods` LIMIT 1);
