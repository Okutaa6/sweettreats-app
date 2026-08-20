-- ============================================================================
-- Petty Cash — dompet per-operator (kolaborasi host ↔ tamu rolling)
-- ============================================================================
-- Model: dalam 1 cabang/bulan, kas bisa dipegang beberapa operator.
--   - holder_user_id NULL  → KAS CABANG (dipegang FO tetap / host).
--   - holder_user_id = <id> → SUB-DOMPET operator TAMU (rolling) itu.
--
-- PENTING: user id di sistem ini = UUID (VARCHAR 36), BUKAN integer. Kolom
-- WAJIB VARCHAR — kalau BIGINT, UUID ke-cast jadi 0 & semua tamu tabrakan.
--
-- Saldo & rincian wallet DIHITUNG PER holder (tiap operator lihat dompetnya
-- sendiri; host & Super Admin bisa lihat dompet lain view-only). Daftar
-- transaksi tetap SATU per cabang (shared), tiap baris ditandai holder.
--
-- Menggantikan mekanisme handover_at / opening_set_by (serah-terima-reset)
-- yang lama — kolom itu DIBIARKAN ada (backward compat) tapi tak dipakai lagi.
--
-- Kalau kolom sudah terlanjur dibuat BIGINT (rilis awal), pakai MODIFY:
--   ALTER TABLE `petty_cash_transactions`
--     MODIFY `holder_user_id` VARCHAR(36) NULL DEFAULT NULL;
--   UPDATE `petty_cash_transactions` SET `holder_user_id` = NULL WHERE `holder_user_id` = '0';
-- ============================================================================

-- IDEMPOTEN — pola yang sama dengan 2026_08_18_pettycash_loan_link.sql dan
-- ..._opening_set_by.sql. Versi pertama berkas ini memakai ALTER polos dan
-- BERHENTI dengan "ERROR 1060 Duplicate column name 'holder_user_id'" kalau
-- dijalankan dua kali (dibuktikan di dev 20 Agu). Itu penting karena SQL prod
-- dijalankan TANGAN: sekali ragu lalu mengulang, prosesnya berhenti dan mudah
-- dikira ada yang rusak. Kolom dan indeks dipisah supaya masing-masing bisa
-- menyusul sendiri kalau rilis sebelumnya cuma separuh jalan.

SET @has_col := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_transactions'
      AND COLUMN_NAME  = 'holder_user_id'
);
SET @sql := IF(@has_col = 0,
    'ALTER TABLE `petty_cash_transactions`
        ADD COLUMN `holder_user_id` VARCHAR(36) NULL DEFAULT NULL
        COMMENT "NULL = kas cabang (host); isi = user id (UUID) sub-dompet operator tamu"
        AFTER `period_id`',
    'SELECT "petty_cash_transactions.holder_user_id already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'petty_cash_transactions'
      AND INDEX_NAME   = 'idx_pct_period_holder'
);
SET @sql := IF(@has_idx = 0,
    'ALTER TABLE `petty_cash_transactions`
        ADD INDEX `idx_pct_period_holder` (`period_id`, `holder_user_id`)',
    'SELECT "idx_pct_period_holder already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
