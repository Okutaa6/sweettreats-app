-- ════════════════════════════════════════════════════════════════════════════
-- Checklist mingguan — sumber pengecualian hari (manual vs system/cuti)
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks: pengecualian hari ("tidak perlu diisi") kini bisa lahir OTOMATIS dari
-- cuti/izin yang disetujui. Yang otomatis TAK boleh dibatalkan karyawan.
--
--   source = 'manual' : dibuat karyawan sendiri.
--   source = 'system' : otomatis dari att_request cuti/izin approved.
--   ref_id            : att_request.id sumbernya (idempotensi + revert saat ditolak).
--
-- Idempotent: cek information_schema (mirror migration
-- 2026_08_05_130000_add_source_to_weekly_day_excuse_table.php).
-- ════════════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS `wde_add_col`;
DELIMITER $$
CREATE PROCEDURE `wde_add_col`(IN col VARCHAR(64), IN ddl VARCHAR(255))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sys_checklist_weekly_day_excuse' AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `sys_checklist_weekly_day_excuse` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

CALL wde_add_col('source', '`source` varchar(16) NOT NULL DEFAULT ''manual'' COMMENT ''manual | system (dari cuti/izin)''');
CALL wde_add_col('ref_id', '`ref_id` varchar(255) NULL DEFAULT NULL COMMENT ''att_request.id bila source=system''');

DROP PROCEDURE IF EXISTS `wde_add_col`;

UPDATE `sys_checklist_weekly_day_excuse` SET `source` = 'manual' WHERE `source` IS NULL OR `source` = '';
