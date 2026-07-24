-- ════════════════════════════════════════════════════════════════════════════
-- Pusat Akun Fase D — tabel audit_log (baseline)
-- ════════════════════════════════════════════════════════════════════════════
-- PENTING: tabel ini selama ini TIDAK ada di repo (lokal & dev "kebetulan"
-- sudah punya dari sesi lama). AuthController mencatat SETIAP login ke sini —
-- environment tanpa tabel ini = semua login error 500. WAJIB jalan sebelum
-- deploy kode ke environment baru (prod!).
--
-- Idempotent: CREATE TABLE IF NOT EXISTS, aman diulang. Skema disalin persis
-- dari lokal/dev (SHOW CREATE TABLE, 2026-07-24).
-- Retensi: baris > 6 bulan dihapus scheduler harian 01:40 (app/Console/Kernel).
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` varchar(50) NOT NULL COMMENT 'create, update, delete, login, logout',
  `model_type` varchar(100) DEFAULT NULL COMMENT 'e.g. RawCusReservation',
  `model_id` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_model` (`model_type`,`model_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bersihkan nilai sensitif dari baris LAMA (ditulis sebelum masking di
-- AuditLog::record ada). Idempotent — baris yang sudah bersih tak tersentuh.
UPDATE `audit_log`
SET `new_values` = JSON_SET(`new_values`, '$.password', '[disamarkan]')
WHERE JSON_VALID(`new_values`) AND JSON_CONTAINS_PATH(`new_values`, 'one', '$.password')
  AND JSON_UNQUOTE(JSON_EXTRACT(`new_values`, '$.password')) <> '[disamarkan]';

UPDATE `audit_log`
SET `old_values` = JSON_SET(`old_values`, '$.password', '[disamarkan]')
WHERE JSON_VALID(`old_values`) AND JSON_CONTAINS_PATH(`old_values`, 'one', '$.password')
  AND JSON_UNQUOTE(JSON_EXTRACT(`old_values`, '$.password')) <> '[disamarkan]';
