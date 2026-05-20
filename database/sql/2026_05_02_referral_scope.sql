-- =====================================================================
-- Referral Scope (Behel vs Non-Behel) — 2026-05-02
-- IDEMPOTENT: aman dijalankan berapa kali pun. Kalau column/key sudah ada → skip.
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
-- =====================================================================

-- ── raw_affiliate.referral_scope ──────────────────────────────────────
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_affiliate'
       AND COLUMN_NAME  = 'referral_scope'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate`
        ADD COLUMN `referral_scope` enum('behel','non_behel') DEFAULT NULL
            COMMENT 'Scope kode referral: behel = khusus pemasangan behel; non_behel = scaling/whitening/PSA/veneer'
            AFTER `type`",
    "SELECT 'raw_affiliate.referral_scope already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_affiliate'
       AND INDEX_NAME   = 'idx_referral_scope'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate` ADD KEY `idx_referral_scope` (`referral_scope`)",
    "SELECT 'idx_referral_scope on raw_affiliate already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ── raw_promo.referral_scope (denormalized) ───────────────────────────
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_promo'
       AND COLUMN_NAME  = 'referral_scope'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_promo`
        ADD COLUMN `referral_scope` enum('behel','non_behel') DEFAULT NULL
            COMMENT 'Denormalisasi dari raw_affiliate.referral_scope — set saat promo di-generate'
            AFTER `raw_affiliate_id`",
    "SELECT 'raw_promo.referral_scope already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_promo'
       AND INDEX_NAME   = 'idx_referral_scope'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_promo` ADD KEY `idx_referral_scope` (`referral_scope`)",
    "SELECT 'idx_referral_scope on raw_promo already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =====================================================================
-- BACKFILL — aman di-rerun karena WHERE clause skip yang sudah ke-isi
-- =====================================================================
UPDATE `raw_affiliate`
   SET `referral_scope` = 'behel'
 WHERE `reward_program` IS NOT NULL
   AND `reward_program` != ''
   AND `referral_scope` IS NULL;

UPDATE `raw_affiliate`
   SET `referral_scope` = 'non_behel'
 WHERE `referral_scope` IS NULL
   AND `registration_status` = 'approved';

UPDATE `raw_promo` rp
  JOIN `raw_affiliate` ra ON ra.id = rp.raw_affiliate_id
   SET rp.referral_scope = ra.referral_scope
 WHERE rp.referral_scope IS NULL
   AND ra.referral_scope IS NOT NULL;
