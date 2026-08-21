-- ════════════════════════════════════════════════════════════════════════════
-- EP Konten — Dokter Branding GP
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks: platform/kategori "Branding GP" (gp) butuh konsep DOKTER yang ikut
-- konten video. Ada daftar dokter GP terkurasi (dipilih dari master raw_doctor),
-- dan tiap konten Branding GP menyimpan dokter GP-nya (wajib saat platform gp
-- dipilih).
--
--   ep_gp_doctor          : daftar keanggotaan dokter GP (sumber isi dropdown).
--                           1 raw_doctor cuma boleh 1x (UNIQUE raw_doctor_id).
--   ep_konten.gp_doctor_id : menyimpan raw_doctor.id (BUKAN id membership), supaya
--                            kalau dokter dikeluarkan dari daftar GP, konten lama
--                            tetap kebaca nama dokternya. Nullable — hanya terisi
--                            untuk konten Branding GP.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS + prosedur sekali-pakai untuk ADD COLUMN
-- (ADD COLUMN IF NOT EXISTS tak ada di MariaDB lama).
-- ════════════════════════════════════════════════════════════════════════════

-- 1. Tabel daftar dokter Branding GP
CREATE TABLE IF NOT EXISTS `ep_gp_doctor` (
  `id` varchar(36) NOT NULL,
  `raw_doctor_id` varchar(36) NOT NULL,
  `social_media` json DEFAULT NULL COMMENT 'Array {platform, username} — IG/TikTok/FB',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_raw_doctor` (`raw_doctor_id`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Kolom gp_doctor_id di ep_konten (idempotent)
DROP PROCEDURE IF EXISTS `gp_add_col`;
DELIMITER $$
CREATE PROCEDURE `gp_add_col`(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl VARCHAR(255))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

CALL `gp_add_col`('ep_konten', 'gp_doctor_id',
  '`gp_doctor_id` varchar(36) DEFAULT NULL COMMENT ''raw_doctor.id dokter Branding GP'' AFTER `platforms`');

-- social_media di ep_gp_doctor (idempotent — untuk install yang sudah bikin tabel tanpa kolom ini)
CALL `gp_add_col`('ep_gp_doctor', 'social_media',
  '`social_media` json DEFAULT NULL COMMENT ''Array {platform, username} — IG/TikTok/FB'' AFTER `raw_doctor_id`');

-- Index (idempotent — cek dulu)
DROP PROCEDURE IF EXISTS `gp_add_idx`;
DELIMITER $$
CREATE PROCEDURE `gp_add_idx`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ep_konten' AND INDEX_NAME = 'idx_gp_doctor'
  ) THEN
    ALTER TABLE `ep_konten` ADD KEY `idx_gp_doctor` (`gp_doctor_id`);
  END IF;
END$$
DELIMITER ;
CALL `gp_add_idx`();

DROP PROCEDURE IF EXISTS `gp_add_col`;
DROP PROCEDURE IF EXISTS `gp_add_idx`;
