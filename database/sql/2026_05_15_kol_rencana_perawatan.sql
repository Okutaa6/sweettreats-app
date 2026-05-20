-- =====================================================================
-- KOL Rencana Perawatan — 2026-05-15
-- IDEMPOTENT — aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   Tambah field bebas untuk catat rencana perawatan KOL yang sedang
--   di-approach (mis. "Behel + Whitening", "Veneer 4 gigi atas", dll).
-- =====================================================================

SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_affiliate'
       AND COLUMN_NAME  = 'rencana_perawatan'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate`
        ADD COLUMN `rencana_perawatan` text DEFAULT NULL
            COMMENT 'Catatan bebas rencana perawatan KOL (free-text, inline editable di tabel KOL Bulan Ini)'
            AFTER `kol_kategori`",
    "SELECT 'raw_affiliate.rencana_perawatan already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
