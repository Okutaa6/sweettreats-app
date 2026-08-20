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

ALTER TABLE `petty_cash_transactions`
    ADD COLUMN `holder_user_id` VARCHAR(36) NULL DEFAULT NULL
        COMMENT 'NULL = kas cabang (host); isi = user id (UUID) sub-dompet operator tamu'
        AFTER `period_id`,
    ADD INDEX `idx_pct_period_holder` (`period_id`, `holder_user_id`);
