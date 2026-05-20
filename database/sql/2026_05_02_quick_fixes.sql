-- =====================================================================
-- Quick Fixes — 2026-05-02
-- IDEMPOTENT — aman dijalankan berapa kali pun.
--
-- Fix #1: Gold tier required_content_count: 13 → 15
-- Fix #2: Tambah kolom raw_affiliate.behel_type untuk specific behel sub-type
-- =====================================================================

-- ── Fix #1: Update Gold tier required count ───────────────────────────
UPDATE `affiliate_reward_tier`
   SET `required_content_count` = 15
 WHERE `name` = 'Gold'
   AND `required_content_count` = 13;


-- ── Fix #2: Tambah kolom behel_type ───────────────────────────────────
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_affiliate'
       AND COLUMN_NAME  = 'behel_type'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate`
        ADD COLUMN `behel_type` varchar(100) DEFAULT NULL
            COMMENT 'Tipe behel spesifik (Stellar/Signature/Self-Ligating/Deluxe/dll). Hanya di-isi kalau referral_scope=behel'
            AFTER `referral_scope`",
    "SELECT 'raw_affiliate.behel_type already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
