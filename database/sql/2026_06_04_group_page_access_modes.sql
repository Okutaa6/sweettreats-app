-- =====================================================================
-- Mode Akses View-Only per Halaman (group_page_access_modes)
-- 2026-06-04
-- IDEMPOTENT: aman dijalankan berapa kali pun (CREATE TABLE IF NOT EXISTS).
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Menambah dimensi MODE (full / view_only) per (group, halaman) di atas
--   sistem permission existing (tabel group_access). Sistem lama tetap
--   binary: punya akses atau tidak. Tabel ini menentukan MODE kalau
--   aksesnya sudah granted.
--
-- Default behavior: tidak ada row untuk (group, page) → treat sebagai 'full'.
-- Pakai 'view_only' untuk role yang harus read-only.
--
-- Pertama dipakai untuk halaman Raw Reservation (page_key = 'rawreservation').
-- Generic — bisa dipakai untuk halaman lain di masa depan tanpa migration tambahan.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `group_page_access_modes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` VARCHAR(255) NOT NULL,
  `page_key` VARCHAR(64) NOT NULL,
  `access_mode` VARCHAR(16) NOT NULL DEFAULT 'full',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_page_access_modes_group_id_page_key_unique` (`group_id`, `page_key`),
  INDEX `group_page_access_modes_group_id_index` (`group_id`),
  INDEX `group_page_access_modes_page_key_index` (`page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Catatan:
--   - Set mode ke 'view_only' lewat UI: login sebagai Super Admin / Admin
--     General → buka Raw Reservation → klik tombol "Kelola Halaman" →
--     pilih pill "View Only" pada role yang dimaksud → Simpan.
--   - Mode 'full' tidak perlu disimpan (default state). Saat user set 'full'
--     via UI, controller akan menghapus row untuk pasangan (group, page)
--     supaya tabel tetap ringkas.
--   - Super Admin & Admin General SELALU full access (admin override) —
--     setting di tabel ini tidak berpengaruh untuk mereka.
-- =====================================================================

-- =====================================================================
-- Tema warna brand per role (group_path.theme)
-- Disimpan di kolom group_path.theme. Diterapkan GLOBAL ke seluruh app
-- (sidebar, header, tombol, dll) — body class di-set saat login berdasarkan
-- theme yang di-assign ke group user.
--
-- Nilai valid: 'theme-default', 'theme-blue', 'theme-green', 'theme-purple',
-- 'theme-amber', 'theme-dark'. Definisi warna di public/assets/css/themes.css.
-- Default: 'theme-default' (coklat brand existing).
-- =====================================================================

-- ── 1) Tambah kolom theme kalau belum ada ────────────────────────────
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'group_path'
      AND COLUMN_NAME = 'theme'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `group_path` ADD COLUMN `theme` VARCHAR(64) NOT NULL DEFAULT ''theme-default'' AFTER `landing_page`',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── 2) Backfill: row existing yang theme NULL / kosong → 'theme-default' ─
UPDATE `group_path`
SET `theme` = 'theme-default'
WHERE `theme` IS NULL OR `theme` = '';
