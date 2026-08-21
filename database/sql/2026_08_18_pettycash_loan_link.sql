-- =====================================================================
-- Petty Cash — Pinjaman: peminjam terstruktur + link pelunasan — 2026-08-18
-- IDEMPOTENT via INFORMATION_SCHEMA check (aman dijalankan berulang).
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Sebelumnya pinjaman & pelunasan hanyalah dua baris transaksi terpisah
--   yang tidak saling terhubung. Baris pinjaman asli tetap tampil polos
--   walau sudah dikembalikan → bingung mana yang masih terutang.
--
--   Dua kolom baru pada `petty_cash_transactions`:
--     - `borrower_name`          : nama peminjam. Diisi pada baris PINJAMAN
--                                  (cash-out, category='pinjaman'). Ikut disalin
--                                  ke baris pelunasannya untuk kejelasan.
--     - `settles_transaction_id` : diisi pada baris PELUNASAN (cash-in,
--                                  category='pinjaman') → menunjuk id baris
--                                  pinjaman yang dilunasi. NULL untuk transaksi
--                                  lain. Dari sini status LUNAS / SEBAGIAN /
--                                  BELUM LUNAS + sisa dihitung per-pinjaman.
--
-- Catatan:
--   - Data pinjaman lama (sebelum kolom ada) → kedua kolom NULL. Aman: baris
--     pinjaman lama akan tampil "BELUM LUNAS" sampai dilunasi lewat alur baru,
--     atau bisa di-backfill manual bila perlu.
--   - Tidak pakai FOREIGN KEY ke id sendiri supaya hapus/soft-delete tetap
--     fleksibel; integritas dijaga di aplikasi (returnLoan / buildRows).
-- =====================================================================

-- 1) borrower_name -----------------------------------------------------
SET @has_borrower := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_transactions'
      AND COLUMN_NAME  = 'borrower_name'
);
SET @sql := IF(@has_borrower = 0,
    'ALTER TABLE `petty_cash_transactions`
        ADD COLUMN `borrower_name` VARCHAR(120) NULL DEFAULT NULL
        COMMENT "Nama peminjam (diisi pada baris pinjaman kategori pinjaman)."
        AFTER `category`',
    'SELECT "petty_cash_transactions.borrower_name already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) settles_transaction_id -------------------------------------------
SET @has_settles := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_transactions'
      AND COLUMN_NAME  = 'settles_transaction_id'
);
SET @sql := IF(@has_settles = 0,
    'ALTER TABLE `petty_cash_transactions`
        ADD COLUMN `settles_transaction_id` BIGINT UNSIGNED NULL DEFAULT NULL
        COMMENT "Baris pelunasan → id baris pinjaman yang dilunasi."
        AFTER `borrower_name`',
    'SELECT "petty_cash_transactions.settles_transaction_id already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) index untuk lookup pelunasan-per-pinjaman ------------------------
SET @has_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_transactions'
      AND INDEX_NAME   = 'idx_pct_settles'
);
SET @sql := IF(@has_idx = 0,
    'ALTER TABLE `petty_cash_transactions`
        ADD INDEX `idx_pct_settles` (`settles_transaction_id`)',
    'SELECT "idx_pct_settles already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
