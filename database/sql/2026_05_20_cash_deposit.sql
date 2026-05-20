-- =====================================================================
-- Cash Deposit (setoran tunai dari pasien) — 2026-05-20
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Buku setoran tunai — pasien bayar cash → masuk ke cash in.
--   Saat saldo terkumpul disetorkan ke kantor pusat → cash out (is_setor=1).
--   Saldo idealnya kembali 0 setelah setor tunai.
--
--   Dua tabel:
--     - cash_deposit_periods       : 1 baris per (tahun, bulan), global.
--     - cash_deposit_transactions  : ledger, link ke raw_customer (kalau in)
--                                    dan raw_branch (denormalized) untuk
--                                    filter Sales per-cabang.
--
--   Saldo berjalan dihitung saat render (opening_balance + Σ(in − out)).
-- =====================================================================

CREATE TABLE IF NOT EXISTS `cash_deposit_periods` (
    `id`              int(11) NOT NULL AUTO_INCREMENT,
    `period_year`     smallint(6) NOT NULL,
    `period_month`    tinyint(4) NOT NULL,
    `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
    `closing_balance` decimal(15,2) DEFAULT NULL,
    `is_closed`       tinyint(1) NOT NULL DEFAULT '0',
    `created_at`      datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cd_period` (`period_year`,`period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cash_deposit_transactions` (
    `id`              int(11) NOT NULL AUTO_INCREMENT,
    `period_id`       int(11) NOT NULL,
    `trx_date`        date NOT NULL,
    `raw_customer_id` varchar(36) DEFAULT NULL,
    `raw_branch_id`   varchar(36) DEFAULT NULL,
    `description`     varchar(255) NOT NULL,
    `direction`       enum('in','out') NOT NULL,
    `amount`          decimal(15,2) NOT NULL,
    `is_setor`        tinyint(1) NOT NULL DEFAULT '0',
    `ref_no`          varchar(50) DEFAULT NULL,
    `created_by`      varchar(36) DEFAULT NULL,
    `created_at`      datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`      datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cd_period`   (`period_id`),
    KEY `idx_cd_trx_date` (`trx_date`),
    KEY `idx_cd_customer` (`raw_customer_id`),
    KEY `idx_cd_branch`   (`raw_branch_id`),
    KEY `idx_cd_deleted`  (`deleted_at`),
    CONSTRAINT `fk_cd_period` FOREIGN KEY (`period_id`)
        REFERENCES `cash_deposit_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed periode pertama (bulan ini, opening 0). Idempoten.
INSERT INTO `cash_deposit_periods` (period_year, period_month, opening_balance)
SELECT YEAR(CURDATE()), MONTH(CURDATE()), 0.00
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `cash_deposit_periods` LIMIT 1);
