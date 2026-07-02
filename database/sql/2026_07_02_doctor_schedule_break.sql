-- =====================================================================
-- Doctor Schedule — jam istirahat (ISOMA) sebagai child dari jam kerja
-- 2026-07-02
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) /
-- `u695655413_app_sweettreat` (prod) / `u695655413_dev` (dev).
--
-- Tambah 2 kolom ke `raw_doctor_schedule`:
--   1. `type`      — 'work' / 'break'. Row 'work' = jam praktik bookable,
--                    row 'break' = window istirahat yg DI-EXCLUDE dari slot.
--                    Default 'work' supaya legacy row otomatis behave lama.
--   2. `parent_id` — pointer child istirahat → id row jam kerja induk.
--                    NULL untuk row work; non-null untuk row break.
--                    Semantik "istirahat menempel ke shift spesifik" — kalau
--                    parent dihapus, admin controller cascade delete child.
--
-- Trait ResolvesDoctorAvailability::subtractBreaks() otomatis kurangi break
-- dari working ranges → efektif di admin CRUD + public /reservasi.
--
-- IDEMPOTENT: aman dijalankan ulang (guard via information_schema).
-- =====================================================================

-- ── 1. Tambah kolom `type` ───────────────────────────────────────────
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_doctor_schedule'
       AND COLUMN_NAME  = 'type'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_doctor_schedule`
        ADD COLUMN `type` varchar(8) NOT NULL DEFAULT 'work'
            COMMENT 'work = jam kerja bookable; break = jam istirahat (exclude dari slot)'
            AFTER `end_hour`",
    "SELECT 'raw_doctor_schedule.type already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ── 2. Index untuk `type` ────────────────────────────────────────────
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_doctor_schedule'
       AND INDEX_NAME   = 'raw_doctor_schedule_type_index'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_doctor_schedule` ADD KEY `raw_doctor_schedule_type_index` (`type`)",
    "SELECT 'raw_doctor_schedule_type_index already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ── 3. Tambah kolom `parent_id` ──────────────────────────────────────
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_doctor_schedule'
       AND COLUMN_NAME  = 'parent_id'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_doctor_schedule`
        ADD COLUMN `parent_id` varchar(255) DEFAULT NULL
            COMMENT 'FK ke id row jam kerja induk untuk row bertipe break; NULL untuk row work'
            AFTER `type`",
    "SELECT 'raw_doctor_schedule.parent_id already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ── 4. Index untuk `parent_id` ───────────────────────────────────────
SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_doctor_schedule'
       AND INDEX_NAME   = 'raw_doctor_schedule_parent_id_index'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_doctor_schedule` ADD KEY `raw_doctor_schedule_parent_id_index` (`parent_id`)",
    "SELECT 'raw_doctor_schedule_parent_id_index already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =====================================================================
-- Verifikasi (jalankan manual setelah eksekusi):
--   SHOW COLUMNS FROM raw_doctor_schedule LIKE 'type';
--   SHOW COLUMNS FROM raw_doctor_schedule LIKE 'parent_id';
--   SHOW INDEX FROM raw_doctor_schedule WHERE Key_name LIKE '%type%' OR Key_name LIKE '%parent%';
-- Jalankan file ini 2x → run kedua harus no-op (info skipping).
-- =====================================================================
