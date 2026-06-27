-- =====================================================================
-- Tabel CRM log pasien — raw_cus_activity — 2026_06_28
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) /
-- `u695655413_app_sweettreat` (prod) / `u695655413_dev` (dev).
--
-- Dipakai tab "Aktivitas" di detail pasien (redesign): catat telepon / wa /
-- kunjungan / catatan per pasien. CustomerController::storeActivity() insert
-- ke tabel ini; edit() baca buat feed. Ada schema-guard (Schema::hasTable)
-- jadi tab-nya mati (bukan crash) kalau tabel belum dibuat.
--
-- Idempoten: CREATE TABLE IF NOT EXISTS (aman dijalankan ulang).
-- =====================================================================

CREATE TABLE IF NOT EXISTS `raw_cus_activity` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `raw_customer_id` VARCHAR(255) NOT NULL,
  `type` VARCHAR(20) NOT NULL COMMENT 'telepon / wa / kunjungan / catatan',
  `note` TEXT NULL,
  `created_by_id` VARCHAR(255) NULL,
  `created_by_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cus_created` (`raw_customer_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
