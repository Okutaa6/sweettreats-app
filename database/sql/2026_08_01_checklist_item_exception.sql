-- ════════════════════════════════════════════════════════════════════════════
-- Checklist — pengecualian per-PERTANYAAN (bukan per-hari)
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks: sebelumnya pengecualian hanya sekala hari penuh (harian → status
-- `excused`; mingguan → sys_checklist_weekly_day_excuse). Kebutuhan baru: satu
-- pertanyaan yang "tidak terjadi hari itu" bisa dikecualikan sendiri, TETAP
-- terhitung 100% (bisa submit), dan diberi flag agar beda dari "belum dikerjakan".
--
--   is_excepted       : 1 = item ini dikecualikan (tidak berlaku hari/minggu ini).
--                        Dihitung "satisfied" seperti is_done untuk gating submit.
--   exception_reason  : alasan wajib saat mengecualikan (mis. "Tidak terjadi hari ini").
--   excepted_at       : stempel waktu saat dikecualikan.
--
-- Berlaku untuk DUA tabel item: harian (sys_checklist_submission_item) &
-- mingguan (sys_checklist_weekly_submission_item).
--
-- Idempotent: cek information_schema dulu (ADD COLUMN IF NOT EXISTS tak ada di
-- MariaDB lama) — pakai prosedur sekali-pakai yang menerima nama tabel + kolom.
-- ════════════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS `cl_add_col`;
DELIMITER $$
CREATE PROCEDURE `cl_add_col`(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl VARCHAR(255))
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

-- ── Harian ──────────────────────────────────────────────────────────────────
CALL cl_add_col('sys_checklist_submission_item', 'is_excepted',      '`is_excepted` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1=item dikecualikan (tidak berlaku hari ini)''');
CALL cl_add_col('sys_checklist_submission_item', 'exception_reason', '`exception_reason` varchar(500) NULL DEFAULT NULL COMMENT ''alasan pengecualian item''');
CALL cl_add_col('sys_checklist_submission_item', 'excepted_at',      '`excepted_at` datetime NULL DEFAULT NULL COMMENT ''waktu item dikecualikan''');

-- ── Mingguan ────────────────────────────────────────────────────────────────
CALL cl_add_col('sys_checklist_weekly_submission_item', 'is_excepted',      '`is_excepted` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1=item dikecualikan (tidak berlaku minggu ini)''');
CALL cl_add_col('sys_checklist_weekly_submission_item', 'exception_reason', '`exception_reason` varchar(500) NULL DEFAULT NULL COMMENT ''alasan pengecualian item''');
CALL cl_add_col('sys_checklist_weekly_submission_item', 'excepted_at',      '`excepted_at` datetime NULL DEFAULT NULL COMMENT ''waktu item dikecualikan''');

DROP PROCEDURE IF EXISTS `cl_add_col`;
