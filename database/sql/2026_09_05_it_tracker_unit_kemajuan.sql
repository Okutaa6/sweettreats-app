-- ════════════════════════════════════════════════════════════════════════════
-- IT Request Tracker — revisi pasca-demo 5 Sep: unit usaha + catatan kemajuan
-- IDEMPOTEN. Tak menyentuh tabel lain.
--
-- 1. `unit` — kerjaan ini untuk SweetTreats atau RB Las. Satu papan (keputusan
--    Ekky: "task RB digabung, warnanya dibedain"), jadi ini kolom penanda,
--    BUKAN pemisah papan.
-- 2. `kemajuan*` — "sudah sampai mana", ditulis IT kapan saja, tampil di kartu
--    papan. Siapa & kapan ikut disimpan supaya catatan basi ketahuan basi.
--
-- ⚠️ ADD COLUMN IF NOT EXISTS (MariaDB ≥10.2) + SELECT … LIMIT 0 — BUKAN
--    information_schema / SHOW COLUMNS / DESCRIBE (membunuh mysqld lokal).
-- ════════════════════════════════════════════════════════════════════════════
ALTER TABLE `it_ticket`
  ADD COLUMN IF NOT EXISTS `unit` VARCHAR(12) NOT NULL DEFAULT 'sweettreats'
    COMMENT 'sweettreats | rblas — unit usaha yang dilayani' AFTER `sumber`,
  ADD COLUMN IF NOT EXISTS `kemajuan` VARCHAR(300) DEFAULT NULL
    COMMENT 'catatan "sudah sampai mana" dari IT — tampil di kartu papan' AFTER `alasan_parkir`,
  ADD COLUMN IF NOT EXISTS `kemajuan_at` DATETIME DEFAULT NULL
    COMMENT 'kapan catatan kemajuan terakhir ditulis' AFTER `kemajuan`,
  ADD COLUMN IF NOT EXISTS `kemajuan_oleh` VARCHAR(255) DEFAULT NULL
    COMMENT 'users.id penulis catatan kemajuan' AFTER `kemajuan_at`,
  ADD INDEX IF NOT EXISTS `ix_it_ticket_unit` (`unit`);

SELECT `unit`, `kemajuan`, `kemajuan_at`, `kemajuan_oleh` FROM `it_ticket` LIMIT 0;
SELECT 'it_ticket: unit + kemajuan siap.' AS info;
