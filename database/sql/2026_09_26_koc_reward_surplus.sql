-- =====================================================================
-- Reward KOC: pintu `used`, surplus konten terbawa, tautan KOC → pasien
-- 2026-09-26 · handoff: docs/handoff/handoff-koc-reward-surplus-kontrol.md
-- IDEMPOTENT (cek INFORMATION_SCHEMA dulu; MySQL 5.7 tak punya
-- ADD COLUMN IF NOT EXISTS). Aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin DB `sweettreats` (dev) / `u695655413_app_sweettreat` (prod).
--
-- ⛔ URUTAN: SQL INI DULU, BARU DEPLOY KODE. Kolom baru tanpa kode tak
--    merusak apa pun; kode tanpa kolom merusak (SUM consumed_content_count
--    dipanggil di tiap hitungan konten portal KOC).
--
-- ⚠️ LOKAL: kueri INFORMATION_SCHEMA terbukti mematikan mysqld lokal. Di
--    lokal pakai ALTER polos di kaki file. Dev/prod aman.
--
-- Yang dilakukan:
--   1. affiliate_reward_claim.consumed_content_count INT NULL
--      Berapa konten yang DIMAKAN reward ini. Diisi = syarat tier saat admin
--      menandai "Reward sudah diberikan" (status → 'used'). Saldo konten KOC
--      = total approved − SUM(kolom ini pada klaim 'used'). Menggantikan
--      stempel waktu reward_program_started_at, yang tak bisa menyatakan
--      "bawa 4" (tujuh konten Linaily lahir dalam dua menit).
--      Tanpa backfill: se-jaringan 0 klaim berstatus 'used' (diukur 26 Sep).
--   2. affiliate_reward_claim.processed_at TIMESTAMP NULL
--      KAPAN reward diberikan. `claimed_at` tetap = kapan KOC meminta,
--      tidak ditimpa. `processed_by` (sudah ada) = siapa.
--   3. raw_affiliate.raw_customer_id VARCHAR(36) NULL + index (dicek terpisah)
--   4. raw_affiliate.reward_program_chosen_at TIMESTAMP NULL — batas siklus klaim
--      Tautan KOC → rekam pasien, diisi SEKALI. Backfill BUKAN di file ini:
--      jalankan `php artisan koc:tautkan-pasien --dry-run` lalu tanpa
--      --dry-run SESUDAH deploy kode. Perintah itu hanya mengisi yang cocok
--      TUNGGAL; yang ganda (mis. Dewi Santi) dibiarkan NULL untuk dipilih
--      manusia di /koc/{id}/pasien. Jangan pernah diselesaikan dengan first().
--
-- Yang SENGAJA TIDAK dilakukan:
--   - TIDAK men-DROP raw_affiliate.reward_program_started_at. Kode tak
--     membaca/menulisnya lagi; kolomnya dibiarkan sebagai sejarah.
--   - TIDAK memasang foreign key raw_customer_id → raw_customer.id
--     (tabel lain di skema ini juga tanpa FK; pasien bisa digabung FO).
-- =====================================================================

-- 1. affiliate_reward_claim.consumed_content_count
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'affiliate_reward_claim'
       AND COLUMN_NAME  = 'consumed_content_count'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `affiliate_reward_claim`
        ADD COLUMN `consumed_content_count` INT NULL DEFAULT NULL
            COMMENT 'Konten yang dimakan reward ini (= syarat tier saat status jadi used). Saldo KOC = total approved - SUM kolom ini.'",
    "SELECT 'affiliate_reward_claim.consumed_content_count already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. affiliate_reward_claim.processed_at
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'affiliate_reward_claim'
       AND COLUMN_NAME  = 'processed_at'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `affiliate_reward_claim`
        ADD COLUMN `processed_at` TIMESTAMP NULL DEFAULT NULL
            COMMENT 'Kapan admin menandai reward sudah diberikan (status used).'",
    "SELECT 'affiliate_reward_claim.processed_at already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. raw_affiliate.raw_customer_id
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_affiliate'
       AND COLUMN_NAME  = 'raw_customer_id'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate`
        ADD COLUMN `raw_customer_id` VARCHAR(36) NULL DEFAULT NULL
            COMMENT 'Rekam pasien KOC. Diisi sekali: otomatis bila HP cocok tunggal, selain itu dipilih manusia. NULL = perlu dipilih.'",
    "SELECT 'raw_affiliate.raw_customer_id already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3b. index raw_customer_id — dicek TERPISAH dari kolomnya, supaya kolom
--     yang sudah ada tanpa index tetap mendapat index.
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_affiliate'
       AND INDEX_NAME   = 'idx_raw_affiliate_customer'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate` ADD INDEX `idx_raw_affiliate_customer` (`raw_customer_id`)",
    "SELECT 'idx_raw_affiliate_customer already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. raw_affiliate.reward_program_chosen_at
--    Batas SIKLUS klaim, diisi tiap KOC pindah program. Klaim 'used' yang
--    diberikan sebelum program sekarang dipilih = sejarah → Bronze → Silver
--    → Bronze bisa melahirkan klaim Bronze baru. ⛔ BUKAN penyaring konten
--    (itu kesalahan reward_program_started_at). NULL (semua baris lama) =
--    klaim terakhir dianggap milik siklus berjalan — sikap aman (mengunci).
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_affiliate'
       AND COLUMN_NAME  = 'reward_program_chosen_at'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate`
        ADD COLUMN `reward_program_chosen_at` TIMESTAMP NULL DEFAULT NULL
            COMMENT 'Kapan reward_program terakhir dipilih lewat pindah program. Batas siklus klaim, BUKAN penyaring konten.'",
    "SELECT 'raw_affiliate.reward_program_chosen_at already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- VERIFIKASI (jangan dilewati) — harus 4 baris.
--   processed_at & reward_program_chosen_at: EXTRA harus KOSONG. Kalau berisi 'on update
--   CURRENT_TIMESTAMP', tiap save() klaim memalsukan waktu pemberian:
--     ALTER TABLE `affiliate_reward_claim` MODIFY `processed_at` TIMESTAMP NULL DEFAULT NULL;
-- =====================================================================
-- SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
--   FROM INFORMATION_SCHEMA.COLUMNS
--  WHERE TABLE_SCHEMA = DATABASE()
--    AND ((TABLE_NAME = 'affiliate_reward_claim' AND COLUMN_NAME IN ('consumed_content_count','processed_at'))
--      OR (TABLE_NAME = 'raw_affiliate' AND COLUMN_NAME IN ('raw_customer_id','reward_program_chosen_at')));

-- =====================================================================
-- LOKAL SAJA (mysqld lokal mati oleh INFORMATION_SCHEMA) — ALTER polos:
-- ALTER TABLE `affiliate_reward_claim`
--   ADD COLUMN `consumed_content_count` INT NULL DEFAULT NULL,
--   ADD COLUMN `processed_at` TIMESTAMP NULL DEFAULT NULL;
-- ALTER TABLE `raw_affiliate`
--   ADD COLUMN `raw_customer_id` VARCHAR(36) NULL DEFAULT NULL,
--   ADD COLUMN `reward_program_chosen_at` TIMESTAMP NULL DEFAULT NULL,
--   ADD INDEX `idx_raw_affiliate_customer` (`raw_customer_id`);
-- =====================================================================
