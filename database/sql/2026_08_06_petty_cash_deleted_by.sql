-- =====================================================================
-- Petty Cash & Transport — Delete audit column (deleted_by) — 2026-08-06
-- IDEMPOTENT via INFORMATION_SCHEMA check.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Melengkapi jejak transaksi. Hapus transaksi sudah pakai soft-delete
--   (deleted_at), tapi belum mencatat SIAPA yang menghapus. Tambah kolom:
--     - deleted_by : UUID user yang menghapus (NULL = belum dihapus)
--
--   Dipakai bersama pembukaan fitur edit/hapus untuk role cabang/sales
--   (bukan lagi Super Admin saja) + batas 24 jam. Data ini tampil di
--   sub-tab "Riwayat" di halaman Petty Cash bersama updated_by/edited_at.
--
-- Catatan:
--   - Kolom nullable supaya baris lama tetap valid.
--   - Padanan updated_by/edited_at ditambah di 2026_06_10_petty_cash_edit_audit.sql.
-- =====================================================================

-- a) petty_cash_transactions.deleted_by
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_transactions'
      AND COLUMN_NAME  = 'deleted_by'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `petty_cash_transactions`
        ADD COLUMN `deleted_by` varchar(36) NULL DEFAULT NULL
            COMMENT "UUID user yang menghapus. NULL = belum dihapus."
            AFTER `deleted_at`',
    'SELECT "petty_cash_transactions.deleted_by already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- b) transport_cost_transactions.deleted_by
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'transport_cost_transactions'
      AND COLUMN_NAME  = 'deleted_by'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `transport_cost_transactions`
        ADD COLUMN `deleted_by` varchar(36) NULL DEFAULT NULL
            COMMENT "UUID user yang menghapus. NULL = belum dihapus."
            AFTER `deleted_at`',
    'SELECT "transport_cost_transactions.deleted_by already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
