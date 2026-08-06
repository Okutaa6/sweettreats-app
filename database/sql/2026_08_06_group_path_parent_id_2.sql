-- ════════════════════════════════════════════════════════════════════════════
-- Atasan kedua (parent_id_2) untuk supervisi checklist
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks: struktur organisasi (group_path) selama ini pohon SATU-induk lewat
-- `parent_id`. Kolom `parent_id_2` (opsional) menambah SATU atasan tambahan yang
-- KHUSUS dipakai supervisi checklist — mis. GS di bawah Supervisor (parent_id)
-- SEKALIGUS di bawah Coordinator Dentist (parent_id_2), supaya kedua atasan bisa
-- me-review checklist GS (saling bantu). `parent_id` tetap atasan utama & satu-
-- satunya penentu struktur pohon (level, drag-drop, siklus) — tidak berubah.
--
-- Idempotent: cek information_schema (mirror migration
-- 2026_08_06_120000_add_parent_id_2_to_group_path_table.php).
-- ════════════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS `gp_add_col`;
DELIMITER $$
CREATE PROCEDURE `gp_add_col`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'group_path' AND COLUMN_NAME = 'parent_id_2'
  ) THEN
    ALTER TABLE `group_path`
      ADD COLUMN `parent_id_2` varchar(255) NULL DEFAULT NULL
        COMMENT 'atasan kedua (khusus supervisi checklist)' AFTER `parent_id`,
      ADD INDEX `group_path_parent_id_2_index` (`parent_id_2`);
  END IF;
END$$
DELIMITER ;

CALL gp_add_col();

DROP PROCEDURE IF EXISTS `gp_add_col`;
