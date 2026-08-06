-- ════════════════════════════════════════════════════════════════════════════
-- Tambah kolom `weeks_of_month` ke sys_checklist_task — 2026-08-06  (idempoten)
-- Mirror SQL dari migration 2026_08_06_..._add_weeks_of_month_to_sys_checklist_task_table.
-- Prod di-apply lewat file SQL manual (bukan artisan migrate), jadi kolom ini
-- WAJIB dibuat di prod lewat file ini SEBELUM kode "minggu ke-berapa" naik.
-- DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
-- ════════════════════════════════════════════════════════════════════════════
-- Rotasi tugas mingguan per "minggu ke-berapa dalam bulan" (reset tiap bulan).
--   weeks_of_month : CSV minggu-ke berlaku (mis. "1,3"); NULL/kosong = SETIAP minggu
--                    (perilaku lama → backward-compatible). Hanya relevan utk task
--                    di kategori cadence=weekly; task harian biarkan NULL.
--   Minggu-ke dihitung dari tanggal Senin: floor((tglSenin-1)/7)+1 → 1..5.
--   Minggu ke-5 (parsial) memakai tugas minggu ke-4 sbg fallback (di kode).
--
-- MySQL 8 tak dukung `ADD COLUMN IF NOT EXISTS`, jadi cek information_schema dulu.
-- ════════════════════════════════════════════════════════════════════════════

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'sys_checklist_task'
    AND COLUMN_NAME  = 'weeks_of_month'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE `sys_checklist_task`
     ADD COLUMN `weeks_of_month` varchar(16) NULL DEFAULT NULL
     COMMENT ''CSV minggu-ke (1..5); NULL=setiap minggu'' AFTER `day_of_week`',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
