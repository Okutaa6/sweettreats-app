-- ════════════════════════════════════════════════════════════════════════════
-- Pusat Akun — Fase B: kolom monitoring & kontrol akun di `users`
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks: docs/rencana/rencana-pusat-akun.md §3 & §5.
--   last_seen_at         : "terakhir aktif" (di-update AuthGuard, throttle 60s) —
--                          beda dari last_login ("terakhir MASUK"). Buat monitoring.
--   must_change_password : 1 = login berikutnya WAJIB ganti password (dipakai saat
--                          IT reset → pw sementara acak). AuthGuard menggiring ke
--                          halaman ganti password sampai diganti.
--   force_logout_at      : stempel "tendang semua sesi yang login SEBELUM waktu ini".
--                          IT klik Paksa Logout → sesi aktif ybs ditolak AuthGuard.
--
-- Idempotent: cek information_schema dulu (ADD COLUMN IF NOT EXISTS tak ada di
-- MariaDB lama) — pakai prosedur sekali-pakai.
-- ════════════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS `pa_add_col`;
DELIMITER $$
CREATE PROCEDURE `pa_add_col`(IN col VARCHAR(64), IN ddl VARCHAR(255))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `users` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

-- created_at TIDAK dijamin ada di semua environment (migration users lama tak
-- punya; local kebetulan drift). Detektor "akun tak bertuan" + list User + badge
-- sidebar membacanya → tanpa kolom ini SEMUA halaman itu 500. DEFAULT
-- current_timestamp supaya akun baru otomatis kena masa tenggang.
CALL pa_add_col('created_at',           '`created_at` timestamp NULL DEFAULT current_timestamp()');
CALL pa_add_col('last_seen_at',         '`last_seen_at` datetime NULL DEFAULT NULL COMMENT ''terakhir aktif (AuthGuard throttle 60s)''');
CALL pa_add_col('must_change_password', '`must_change_password` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1=wajib ganti pw di login berikutnya''');
CALL pa_add_col('force_logout_at',      '`force_logout_at` datetime NULL DEFAULT NULL COMMENT ''tendang sesi yang login sebelum stempel ini''');

DROP PROCEDURE IF EXISTS `pa_add_col`;
