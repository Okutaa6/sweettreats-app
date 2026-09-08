-- ════════════════════════════════════════════════════════════════════════════
-- Checklist HARIAN — sumber pengecualian (manual vs system/cuti)
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks: status 'excused' pada sys_checklist_submission kini bisa lahir
-- OTOMATIS dari cuti/izin/sakit yang disetujui (att_request). Sebelumnya semua
-- 'excused' pasti buatan karyawan, jadi tak perlu dibedakan. Sekarang perlu:
-- yang otomatis TIDAK boleh dibatalkan karyawan/supervisor (akan dipasang lagi
-- oleh reconcile), dan harus bisa dicabut saat pengajuannya ditolak.
--
--   excuse_source = 'manual' : karyawan menandai sendiri (MyChecklistController::excuseToday).
--   excuse_source = 'system' : otomatis dari att_request cuti/izin/sakit approved.
--   excuse_ref_id            : att_request.id sumbernya (idempotensi + revert saat ditolak).
--
-- Sejajar dengan sys_checklist_weekly_day_excuse.source/ref_id (2026_08_05).
-- Idempotent: cek information_schema (ADD COLUMN IF NOT EXISTS tak ada di MySQL 5.7).
-- ════════════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS `cs_add_col`;
DELIMITER $$
CREATE PROCEDURE `cs_add_col`(IN col VARCHAR(64), IN ddl VARCHAR(255))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sys_checklist_submission' AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `sys_checklist_submission` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

CALL cs_add_col('excuse_source', '`excuse_source` varchar(16) NULL DEFAULT ''manual'' COMMENT ''manual | system (dari cuti/izin/sakit approved)''');
CALL cs_add_col('excuse_ref_id', '`excuse_ref_id` varchar(64) NULL DEFAULT NULL COMMENT ''att_request.id bila excuse_source=system''');

DROP PROCEDURE IF EXISTS `cs_add_col`;

-- Index untuk unexcuseDailyForLeave(): "semua baris system milik att_request X".
SET @idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sys_checklist_submission'
      AND INDEX_NAME = 'idx_sub_excuse_ref'
);
SET @ddl := IF(@idx = 0,
    'CREATE INDEX `idx_sub_excuse_ref` ON `sys_checklist_submission` (`excuse_source`, `excuse_ref_id`)',
    'SELECT ''idx_sub_excuse_ref — skip (sudah ada).'' AS info'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Semua baris LAMA (termasuk yang 'excused') pasti manual — satu-satunya jalan
-- ke status excused sebelum ini adalah tombol karyawan. MySQL sudah mengisi
-- DEFAULT saat ADD COLUMN; UPDATE ini jaring pengaman untuk env yang kolomnya
-- terlanjur dibuat NULL (mirror UPDATE di 2026_08_05_weekly_day_excuse_source).
UPDATE `sys_checklist_submission`
   SET `excuse_source` = 'manual'
 WHERE `excuse_source` IS NULL OR `excuse_source` = '';
