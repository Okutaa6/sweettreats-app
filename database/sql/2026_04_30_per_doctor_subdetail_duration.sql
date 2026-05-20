-- =====================================================================
-- Per-Doctor Sub-Detail Duration Override — 2026-04-30
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan: durasi sub-detail (mis. "Control Metal Braces") bisa beda per dokter.
-- Pattern sama seperti raw_doctor_service: pivot dengan duration_minutes nullable.
-- NULL = fallback ke raw_doc_service_detail.duration_minutes (global default).
--
-- Tipe & collation disamakan dengan pola tabel existing (varchar(255), utf8mb4_unicode_ci).
-- =====================================================================

CREATE TABLE `raw_doctor_service_detail` (
  `id` varchar(255) NOT NULL,
  `raw_doctor_id` varchar(255) NOT NULL,
  `raw_doc_service_detail_id` varchar(255) NOT NULL,
  `duration_minutes` double DEFAULT NULL COMMENT 'Override per doctor. NULL = fallback ke raw_doc_service_detail.duration_minutes',
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_doctor_detail` (`raw_doctor_id`, `raw_doc_service_detail_id`),
  KEY `idx_doctor` (`raw_doctor_id`),
  KEY `idx_detail` (`raw_doc_service_detail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
