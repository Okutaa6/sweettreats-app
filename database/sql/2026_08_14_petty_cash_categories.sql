-- =====================================================================
-- Petty Cash — Master tabel Kategori Pengeluaran — 2026-08-14
-- IDEMPOTENT (CREATE TABLE IF NOT EXISTS + INSERT IGNORE).
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Sebelumnya daftar kategori pengeluaran petty cash HARDCODED di
--   constant PettyCashTransaction::CATEGORIES. Tabel ini memindahkannya
--   ke DB supaya Super Admin bisa tambah / edit / nonaktifkan / hapus
--   kategori dari halaman "Kelola Wallet" (/fund-sources).
--
-- Catatan:
--   - `key`   = kode teknis yang disimpan di petty_cash_transactions.category
--               (huruf kecil / underscore). IMMUTABLE setelah dibuat.
--   - `label` = nama tampilan (boleh diubah).
--   - `is_system` = 1  → kategori terkunci, tak boleh dihapus / dinonaktifkan.
--               Saat ini hanya 'pinjaman' (dipakai alur returnLoan / Pinjaman Lunas).
--   - `is_active` = 0  → disembunyikan dari pilihan input baru, tapi transaksi
--               lama tetap tampil labelnya (kategori terpakai tak boleh dihapus,
--               hanya dinonaktifkan).
--   - Seed = 7 kategori yang selama ini ada di constant (key identik supaya
--               transaksi lama tetap ter-mapping ke label yang benar).
-- =====================================================================

CREATE TABLE IF NOT EXISTS `petty_cash_categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(32)  NOT NULL COMMENT 'Kode teknis di petty_cash_transactions.category. Immutable.',
    `label`      VARCHAR(100) NOT NULL COMMENT 'Nama tampilan kategori.',
    `is_system`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = terkunci (tak boleh hapus/nonaktif), mis. pinjaman.',
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 = disembunyikan dari input baru; data lama tetap aman.',
    `sort_order` INT          NOT NULL DEFAULT 0 COMMENT 'Urutan tampil (kecil = atas).',
    `created_at` TIMESTAMP    NULL DEFAULT NULL,
    `updated_at` TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pcc_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed kategori awal (idempotent — INSERT IGNORE lewat unique key `key`).
INSERT IGNORE INTO `petty_cash_categories`
    (`key`, `label`, `is_system`, `is_active`, `sort_order`, `created_at`, `updated_at`)
VALUES
    ('makan',       'Konsumsi',    0, 1, 10, NOW(), NOW()),
    ('transport',   'Transport',   0, 1, 20, NOW(), NOW()),
    ('bensin',      'Bensin',      0, 1, 30, NOW(), NOW()),
    ('operasional', 'Operasional', 0, 1, 40, NOW(), NOW()),
    ('pinjaman',    'Pinjaman',    1, 1, 50, NOW(), NOW()),
    ('server',      'Server',      0, 1, 60, NOW(), NOW()),
    ('prv',         'PRV',         0, 1, 70, NOW(), NOW());
