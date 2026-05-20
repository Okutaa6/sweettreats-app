-- =====================================================================
-- Reward Program Lock — 2026-05-02
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   Lock pilihan reward program (3x/12x/above_12x) sampai user selesai claim
--   tier yang dipilih. Setelah claim, user boleh switch ke program baru —
--   counter konten reset (mulai dari 0 lagi untuk program berikutnya).
-- =====================================================================

SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_affiliate'
       AND COLUMN_NAME  = 'reward_program_started_at'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate`
        ADD COLUMN `reward_program_started_at` timestamp NULL DEFAULT NULL
            COMMENT 'Kapan program reward yang sekarang mulai dihitung (filter content count)'
            AFTER `reward_program`",
    "SELECT 'raw_affiliate.reward_program_started_at already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =====================================================================
-- BACKFILL — aman di-rerun karena WHERE clause skip yang sudah ke-isi
-- Existing affiliate yang sudah punya reward_program → set started_at = created_at
-- =====================================================================
UPDATE `raw_affiliate`
   SET `reward_program_started_at` = `created_at`
 WHERE `reward_program` IS NOT NULL
   AND `reward_program` != ''
   AND `reward_program_started_at` IS NULL;
