-- ════════════════════════════════════════════════════════════════════════════
-- Rolling cabang — kolom `kind` + `note` pada sys_user_position
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks: karyawan sewaktu-waktu ditugaskan ke cabang lain untuk durasi tertentu
-- (rolling), lalu OTOMATIS balik ke cabang asal setelah periodenya lewat.
--
--   kind = 'home'    : posisi utama/asal (open-ended, effective_to NULL).
--   kind = 'rolling' : penugasan sementara (WAJIB bounded effective_from..to).
--   note             : alasan rolling / teks audit.
--
-- Rolling adalah row TERPISAH — tak mengubah row home; scopeActiveOn() mengutamakan
-- rolling di window-nya lalu row-nya gugur sendiri saat effective_to lewat (auto-revert).
--
-- Idempotent: cek information_schema dulu (mirror migration
-- 2026_08_05_121000_add_kind_to_sys_user_position_table.php).
-- ════════════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS `up_add_col`;
DELIMITER $$
CREATE PROCEDURE `up_add_col`(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl VARCHAR(255))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

CALL up_add_col('sys_user_position', 'kind', '`kind` varchar(16) NOT NULL DEFAULT ''home'' COMMENT ''home=posisi asal | rolling=penugasan sementara''');
CALL up_add_col('sys_user_position', 'note', '`note` varchar(500) NULL DEFAULT NULL COMMENT ''alasan rolling / teks audit''');

DROP PROCEDURE IF EXISTS `up_add_col`;

-- Index bantu resolusi cabang efektif per tanggal (idempotent, MariaDB lama).
DROP PROCEDURE IF EXISTS `up_add_idx`;
DELIMITER $$
CREATE PROCEDURE `up_add_idx`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sys_user_position'
      AND INDEX_NAME = 'sys_user_position_kind_index'
  ) THEN
    ALTER TABLE `sys_user_position` ADD INDEX `sys_user_position_kind_index` (`kind`);
  END IF;
END$$
DELIMITER ;
CALL up_add_idx();
DROP PROCEDURE IF EXISTS `up_add_idx`;

-- Backfill: semua posisi lama = home.
UPDATE `sys_user_position` SET `kind` = 'home' WHERE `kind` IS NULL OR `kind` = '';
