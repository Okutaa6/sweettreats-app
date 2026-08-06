-- ════════════════════════════════════════════════════════════════════════════
-- Pengajuan top-up petty cash — field bergaya "Pengajuan" (nama, butuh, darurat)
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks: modal "Ajukan Top Up" dibikin persis format form Pengajuan ROP. Tiga
-- data baru ikut disubmit Sales dan ditampilkan ke Finance untuk konteks approval:
--
--   requester_name : nama pemohon (default nama user login, boleh diedit).
--   needed_by      : "butuh kapan" — tanggal dana diharapkan cair (nullable).
--   is_urgent      : flag DARURAT untuk prioritas antrian Finance.
--
-- Idempotent: cek information_schema (mirror migration
-- 2026_08_05_140000_add_pengajuan_fields_to_topup_requests_table.php).
-- ════════════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS `tur_add_col`;
DELIMITER $$
CREATE PROCEDURE `tur_add_col`(IN col VARCHAR(64), IN ddl VARCHAR(255))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'topup_requests' AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `topup_requests` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

CALL tur_add_col('requester_name', '`requester_name` varchar(255) NULL DEFAULT NULL COMMENT ''nama pemohon (editable) AFTER `requested_by`''');
CALL tur_add_col('needed_by',      '`needed_by` date NULL DEFAULT NULL COMMENT ''butuh kapan''');
CALL tur_add_col('is_urgent',      '`is_urgent` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''flag darurat''');

DROP PROCEDURE IF EXISTS `tur_add_col`;
