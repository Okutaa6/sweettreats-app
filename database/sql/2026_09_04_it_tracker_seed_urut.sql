-- ════════════════════════════════════════════════════════════════════════════
-- IT Request Tracker — kolom seed_urut untuk `it:seed-antrian` — 2026-09-04
-- IDEMPOTEN. Rencana §14.21: seeder antrian nyata dijalankan di dev (demo 5 Sep)
-- lalu DIULANG di prod; jalan ulang MEMPERBARUI by seed_urut, bukan menggandakan.
--
-- ⚠️ ADD COLUMN IF NOT EXISTS (MariaDB ≥10.2) + SELECT … LIMIT 0 — BUKAN
--    information_schema / SHOW COLUMNS / DESCRIBE (membunuh mysqld lokal).
-- ════════════════════════════════════════════════════════════════════════════
ALTER TABLE `it_ticket`
  ADD COLUMN IF NOT EXISTS `seed_urut` TINYINT(3) UNSIGNED DEFAULT NULL
    COMMENT 'nomor urut baris seed-antrian (idempoten); NULL = tiket lahir dari layar' AFTER `lahir_via`,
  ADD UNIQUE INDEX IF NOT EXISTS `uq_it_ticket_seed_urut` (`seed_urut`);

SELECT `seed_urut` FROM `it_ticket` LIMIT 0;
SELECT 'it_ticket.seed_urut siap.' AS info;
