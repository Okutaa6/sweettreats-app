-- =====================================================================
-- Marketing Promo (entitas baru) — 2026-05-02
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   "Promo" yang dimaksud Marketing (Promo Bulanan, Sweet Surprise, Cicilan
--   Behel, Tanggal Kembar, Payday, Promo Cabang) — TIDAK pakai kode referral,
--   diisi oleh Head Sales. Beda dari `raw_promo` (yang sekarang dipakai
--   untuk kode referral KOC/KOL — secara UI akan di-relabel "Kode Referral").
-- =====================================================================

CREATE TABLE IF NOT EXISTS `marketing_promo` (
  `id` varchar(36) NOT NULL,
  `nama_promo` varchar(255) NOT NULL,
  `campaign_type` enum(
      'promo_bulanan',
      'promo_cabang',
      'sweet_surprise',
      'cicilan_behel',
      'tanggal_kembar',
      'payday'
  ) NOT NULL DEFAULT 'promo_bulanan',
  `detail_promo` text DEFAULT NULL,
  `syarat_ketentuan` text DEFAULT NULL,
  `cetakan` varchar(255) DEFAULT NULL COMMENT 'Mis. "BANNER STANDING", "FLAYER 50 MASING2 CABANG"',
  `bulan_promo` varchar(20) DEFAULT NULL COMMENT 'January..December (untuk filter cepat)',
  `tahun_promo` smallint(6) DEFAULT NULL,
  `periode_start` date DEFAULT NULL,
  `periode_end` date DEFAULT NULL,
  `status` enum('planning','aktif','selesai') NOT NULL DEFAULT 'planning',
  `harga_normal` decimal(12,2) DEFAULT NULL,
  `harga_promo` decimal(12,2) DEFAULT NULL,
  `cogs_fee_dokter` decimal(12,2) DEFAULT NULL,
  `margin_rp` decimal(12,2) DEFAULT NULL COMMENT 'harga_promo - cogs_fee_dokter',
  `margin_pct` decimal(8,4) DEFAULT NULL COMMENT 'margin_rp / harga_promo * 100',
  `voucher_tambahan_pasien` decimal(12,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_periode` (`periode_start`, `periode_end`),
  KEY `idx_campaign` (`campaign_type`),
  KEY `idx_bulan_tahun` (`tahun_promo`, `bulan_promo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Pivot M:N promo ↔ cabang.
-- Konvensi: tidak ada row sama sekali untuk promo X = berlaku di SEMUA CABANG.
CREATE TABLE IF NOT EXISTS `marketing_promo_branch` (
  `id` varchar(36) NOT NULL,
  `marketing_promo_id` varchar(36) NOT NULL,
  `raw_branch_id` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_promo_branch` (`marketing_promo_id`, `raw_branch_id`),
  KEY `idx_promo` (`marketing_promo_id`),
  KEY `idx_branch` (`raw_branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── raw_cus_reservation.marketing_promo_id ────────────────────────────
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_cus_reservation'
       AND COLUMN_NAME  = 'marketing_promo_id'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_cus_reservation`
        ADD COLUMN `marketing_promo_id` varchar(36) DEFAULT NULL AFTER `raw_promo_id`",
    "SELECT 'raw_cus_reservation.marketing_promo_id already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_cus_reservation'
       AND INDEX_NAME   = 'idx_marketing_promo'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_cus_reservation` ADD KEY `idx_marketing_promo` (`marketing_promo_id`)",
    "SELECT 'idx_marketing_promo on raw_cus_reservation already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =====================================================================
-- OPSIONAL — Sidebar menu (jalankan setelah Anda cek pid grup induk).
-- Sesuaikan `<PID_PARENT>` dan `<PID_PATH>` dengan struktur access_path Anda.
-- =====================================================================
-- Re-label menu lama supaya jelas itu kode referral, bukan promo:
-- UPDATE `access_path` SET `nama` = 'Kode Referral' WHERE `link` = 'rawpromo';
--
-- Tambah menu baru "Promo":
-- INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `link`, `pid_path`)
-- VALUES (UUID(), '<PID_PARENT_MARKETING>', 'Promo', 'fa fa-tag', 1, 'marketing-promo', '<PID_PATH>');
--
-- Setelah INSERT/UPDATE, regenerate cache `storage/app/group_access/{group_id}.txt`
-- (atau hapus cache "group_access_*" dengan `php artisan cache:clear`).
