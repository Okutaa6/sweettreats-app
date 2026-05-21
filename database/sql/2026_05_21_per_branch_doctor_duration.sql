-- =====================================================================
-- Durasi perawatan dokter PER-CABANG — 2026-05-21
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- MASALAH: durasi override dokter disimpan di raw_doctor_service.duration_minutes
-- dan raw_doctor_service_detail.duration_minutes — keyed (dokter, perawatan)
-- TANPA cabang. Akibatnya 1 dokter yang praktik di banyak cabang berbagi 1
-- durasi: ubah durasi di cabang A ikut berubah di cabang B.
--
-- PERBAIKAN: durasi override jadi PER-CABANG lewat 2 tabel baru. Daftar
-- perawatan yang dilayani dokter (capability) TETAP universal di
-- raw_doctor_service / raw_doc_service_detail — yang per-cabang hanya DURASI.
--
-- Kolom lama raw_doctor_service.duration_minutes & raw_doctor_service_detail
-- dibiarkan (legacy, tidak dipakai kode baru) — tidak di-drop demi keamanan.
--
-- Idempoten: aman dijalankan ulang (CREATE TABLE IF NOT EXISTS + INSERT IGNORE).
-- =====================================================================

-- 1. Durasi perawatan (service-level) per (dokter, cabang, perawatan)
CREATE TABLE IF NOT EXISTS `raw_doctor_branch_service_duration` (
  `id` varchar(255) NOT NULL,
  `raw_doctor_id` varchar(255) NOT NULL,
  `raw_branch_id` varchar(255) NOT NULL,
  `raw_doc_service_id` varchar(255) NOT NULL,
  `duration_minutes` double DEFAULT NULL COMMENT 'Override durasi per dokter PER CABANG. NULL = fallback ke raw_doc_service.duration_minutes',
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_doctor_branch_service` (`raw_doctor_id`, `raw_branch_id`, `raw_doc_service_id`),
  KEY `idx_lookup` (`raw_doctor_id`, `raw_branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Durasi sub-detail per (dokter, cabang, sub-detail)
CREATE TABLE IF NOT EXISTS `raw_doctor_branch_detail_duration` (
  `id` varchar(255) NOT NULL,
  `raw_doctor_id` varchar(255) NOT NULL,
  `raw_branch_id` varchar(255) NOT NULL,
  `raw_doc_service_detail_id` varchar(255) NOT NULL,
  `duration_minutes` double DEFAULT NULL COMMENT 'Override durasi sub-detail per dokter PER CABANG. NULL = fallback ke raw_doc_service_detail.duration_minutes',
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_doctor_branch_detail` (`raw_doctor_id`, `raw_branch_id`, `raw_doc_service_detail_id`),
  KEY `idx_lookup` (`raw_doctor_id`, `raw_branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. BACKFILL — salin durasi lama ke SETIAP cabang tempat dokter punya
--    jadwal, dengan nilai durasi yang sama. INSERT IGNORE → idempoten
--    (unique key skip duplikat saat re-run).
-- =====================================================================

-- 3a. service-level: dari raw_doctor_service (yang punya override non-NULL)
INSERT IGNORE INTO `raw_doctor_branch_service_duration`
  (`id`, `raw_doctor_id`, `raw_branch_id`, `raw_doc_service_id`, `duration_minutes`, `created_by`, `created_at`)
SELECT UUID(), ds.`raw_doctor_id`, sch.`raw_branch_id`, ds.`raw_doc_service_id`,
       ds.`duration_minutes`, 'backfill', NOW()
FROM `raw_doctor_service` ds
JOIN (SELECT DISTINCT `raw_doctor_id`, `raw_branch_id` FROM `raw_doctor_schedule`) sch
  ON sch.`raw_doctor_id` = ds.`raw_doctor_id`
WHERE ds.`duration_minutes` IS NOT NULL;

-- 3b. sub-detail: dari raw_doctor_service_detail (semua baris = override)
INSERT IGNORE INTO `raw_doctor_branch_detail_duration`
  (`id`, `raw_doctor_id`, `raw_branch_id`, `raw_doc_service_detail_id`, `duration_minutes`, `created_by`, `created_at`)
SELECT UUID(), dd.`raw_doctor_id`, sch.`raw_branch_id`, dd.`raw_doc_service_detail_id`,
       dd.`duration_minutes`, 'backfill', NOW()
FROM `raw_doctor_service_detail` dd
JOIN (SELECT DISTINCT `raw_doctor_id`, `raw_branch_id` FROM `raw_doctor_schedule`) sch
  ON sch.`raw_doctor_id` = dd.`raw_doctor_id`
WHERE dd.`duration_minutes` IS NOT NULL;

-- =====================================================================
-- Verifikasi:
--   SELECT COUNT(*) FROM raw_doctor_branch_service_duration;
--   SELECT COUNT(*) FROM raw_doctor_branch_detail_duration;
-- Re-run file ini → 0 baris baru (idempoten).
-- =====================================================================
