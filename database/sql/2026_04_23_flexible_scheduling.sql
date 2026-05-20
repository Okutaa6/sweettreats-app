-- =====================================================================
-- Flexible Doctor Scheduling & Service Duration — 2026-04-23
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
-- =====================================================================

-- 1. Kolom duration override di pivot raw_doctor_service
--    NULL = fallback ke raw_doc_service.duration_minutes (global default)
ALTER TABLE `raw_doctor_service`
  ADD COLUMN `duration_minutes` DOUBLE NULL DEFAULT NULL
    COMMENT 'Override per doctor. NULL = fallback ke raw_doc_service.duration_minutes'
    AFTER `raw_doc_service_id`;

-- 2. Tabel baru pengecualian jadwal per-tanggal
CREATE TABLE `raw_doctor_schedule_exception` (
  `id` VARCHAR(36) NOT NULL PRIMARY KEY,
  `raw_doctor_id` VARCHAR(36) NOT NULL,
  `raw_branch_id` VARCHAR(36) NOT NULL,
  `date` DATE NOT NULL,
  `start_hour` TIME NOT NULL,
  `end_hour` TIME NOT NULL,
  `type` ENUM('replace','add') NOT NULL DEFAULT 'replace'
    COMMENT 'replace = timpa recurring schedule di tanggal itu; add = tambah jadwal (utk hari yg biasanya tidak kerja)',
  `reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` VARCHAR(36) NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_by` VARCHAR(36) NULL,
  INDEX `idx_doctor_date` (`raw_doctor_id`, `date`),
  INDEX `idx_branch_date` (`raw_branch_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- Opsional: seed awal duration pivot = duration global service
-- (kalau ingin starting point explicit per doctor)
-- Uncomment jika mau dijalankan:
-- =====================================================================
-- UPDATE raw_doctor_service ds
--   INNER JOIN raw_doc_service s ON s.id = ds.raw_doc_service_id
--   SET ds.duration_minutes = s.duration_minutes
--   WHERE ds.duration_minutes IS NULL;

-- =====================================================================
-- 3. Tambah kategori 'behel' di raw_doc_service.category
-- =====================================================================
-- Cek dulu tipe kolom:
--   DESCRIBE raw_doc_service;
-- Kalau 'category' bertipe VARCHAR/TEXT → tidak perlu ALTER, kode sudah cukup.
-- Kalau bertipe ENUM → jalankan baris di bawah utk menambahkan 'behel':

-- ALTER TABLE `raw_doc_service`
--   MODIFY COLUMN `category` ENUM('estetika','behel','standar','pembedahan','lainnya') NULL;
