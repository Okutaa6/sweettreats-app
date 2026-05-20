-- =====================================================================
-- Breakdown Konten Refactor — 2026-05-02
-- IDEMPOTENT — aman dijalankan berapa kali pun.
--
-- Perubahan konseptual:
--   Sebelumnya: ep_breakdown adalah entitas project terpisah, ep_konten link
--               via ep_breakdown_id.
--   Sekarang:   Breakdown adalah ep_konten dengan flag `is_breakdown=1`,
--               punya extra fields (lokasi, acc_by). Otomatis muncul di
--               kalender EP karena memang satu tabel.
--
-- Alasan: breakdown kontennya RICH version dari konten EP biasa — sama
-- punya tanggal upload, platform, pillar, status, dll. Pisah tabel jadi
-- redundant. Filter "khusus" di calendar bermain di flag.
-- =====================================================================

-- ── ADD kolom baru ke ep_konten ────────────────────────────────────────
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ep_konten' AND COLUMN_NAME = 'is_breakdown');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `ep_konten`
        ADD COLUMN `is_breakdown` tinyint(1) NOT NULL DEFAULT 0
            COMMENT '1 = konten khusus / breakdown (Promo/Event), muncul di kalender dengan badge'
            AFTER `tipe_konten`,
        ADD KEY `idx_is_breakdown` (`is_breakdown`)",
    "SELECT 'ep_konten.is_breakdown already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ep_konten' AND COLUMN_NAME = 'lokasi');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `ep_konten`
        ADD COLUMN `lokasi` varchar(255) DEFAULT NULL
            COMMENT 'Lokasi shooting/produksi (untuk breakdown khusus)'
            AFTER `is_breakdown`",
    "SELECT 'ep_konten.lokasi already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ep_konten' AND COLUMN_NAME = 'acc_by');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `ep_konten`
        ADD COLUMN `acc_by` varchar(255) DEFAULT NULL
            COMMENT 'Nama PJ yang ACC konten ini (text dulu, kedepan jadi user_id button)'
            AFTER `lokasi`",
    "SELECT 'ep_konten.acc_by already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ── DROP kolom ep_breakdown_id (tidak terpakai lagi) ──────────────────
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ep_konten' AND COLUMN_NAME = 'ep_breakdown_id');
SET @sql := IF(@exist > 0,
    "ALTER TABLE `ep_konten` DROP COLUMN `ep_breakdown_id`",
    "SELECT 'ep_konten.ep_breakdown_id already dropped' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ── DROP tabel ep_breakdown + pivot (tidak terpakai lagi) ─────────────
DROP TABLE IF EXISTS `ep_breakdown_pillar_pivot`;
DROP TABLE IF EXISTS `ep_breakdown`;
