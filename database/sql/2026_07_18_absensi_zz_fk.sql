-- ============================================================================
-- 2026_07_18_absensi_zz_fk.sql
--
-- Pasang foreign key yang menyeberang antar-file skema absensi.
--
-- KENAPA FILE TERPISAH: `att_day` (2026_07_18_absensi_day.sql) mengacu
-- `att_work_rule` (2026_07_18_absensi_work_rule.sql), tapi urutan ALFABET
-- menaruh `_day` DULUAN. Kalau FK-nya ditulis inline di `_day`, perintah lazim
--
--     for f in database/sql/*.sql; do mysql db < "$f"; done
--
-- gagal dengan ERROR 1215 dan `att_day` TIDAK PERNAH TERBENTUK — seluruh modul
-- absensi mati sejak langkah pertama deploy, dan penyebabnya tidak jelas karena
-- error-nya muncul di file yang "salah".
--
-- Nama file diawali `zz` supaya selalu terurut PALING AKHIR, jadi kedua tabel
-- dijamin sudah ada berapa pun urutan file lain dijalankan.
--
-- Idempoten: dijaga INFORMATION_SCHEMA, aman dijalankan berulang.
-- ============================================================================

-- fk_day_rule : att_day.rule_id -> att_work_rule.id
SET @sql := (
  SELECT IF(
    -- kedua tabel ada DAN constraint-nya belum terpasang
        (SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'att_day') = 1
    AND (SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'att_work_rule') = 1
    AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'att_day'
            AND CONSTRAINT_NAME = 'fk_day_rule') = 0,
    'ALTER TABLE `att_day` ADD CONSTRAINT `fk_day_rule` FOREIGN KEY (`rule_id`) REFERENCES `att_work_rule` (`id`) ON DELETE SET NULL',
    'SELECT "fk_day_rule dilewati (tabel belum lengkap atau FK sudah ada)"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- VERIFIKASI (harus mengembalikan 1 baris):
--   SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'att_day'
--     AND CONSTRAINT_NAME = 'fk_day_rule';
-- ============================================================================
