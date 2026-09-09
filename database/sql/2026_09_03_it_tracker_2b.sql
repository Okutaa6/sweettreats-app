-- ════════════════════════════════════════════════════════════════════════════
-- IT Request Tracker — Fase 2b: empat formulir kerja — 2026-09-03
-- IDEMPOTENT: aman dijalankan berapa kali pun.
--
-- Rencana §14.13/§14.14. Isi formulir 02/05/07 hidup di it_form.payload (JSON);
-- yang dinormalkan tetap it_fr / it_test_case / it_temuan karena gerbang
-- membacanya. Dua tambahan kecil di bawah.
--
-- ⚠️ Sengaja memakai `ADD COLUMN IF NOT EXISTS` bawaan MariaDB (ada sejak
--    10.2 — jadi sah di lokal 10.4 maupun prod 11.8), BUKAN pola
--    information_schema.COLUMNS yang dipakai berkas SQL lama: kueri
--    information_schema MEMBUNUH mysqld lokal (segfault; terbukti 3 Sep
--    tepat pada baris itu, dan mysql_error.log tak mencatatnya).
--
-- ⚠️ Fitur ini tidak menyentuh tabel lama mana pun.
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. it_test_case.terkunci — lima pemeriksaan tetap (TC-01..05) tak bisa
--    dihapus / diganti nama, tapi tetap diisi & ikut terhitung.
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `it_test_case`
  ADD COLUMN IF NOT EXISTS `terkunci` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = pemeriksaan tetap TC-01..05: tak bisa dihapus/diganti nama' AFTER `status`;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. it_ticket.lahir_dari_id + lahir_via — tiket yang LAHIR dari catatan UAT
--    atau rencana tindak lanjut evaluasi (spawnTicket, menutup lingkaran).
--    Sengaja BUKAN induk_id: induk_id = modul→fitur di dalamnya (tampil
--    "N fitur di dalam"); ini relasi berbeda ("↳ dari IT-2026-017 · Evaluasi").
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `it_ticket`
  ADD COLUMN IF NOT EXISTS `lahir_dari_id` BIGINT(20) UNSIGNED DEFAULT NULL
    COMMENT 'tiket asal (spawn dari catatan UAT / RTL evaluasi)' AFTER `evaluator_user_id`,
  ADD COLUMN IF NOT EXISTS `lahir_via` VARCHAR(12) DEFAULT NULL
    COMMENT 'UAT / Evaluasi' AFTER `lahir_dari_id`,
  ADD INDEX IF NOT EXISTS `ix_it_ticket_lahir` (`lahir_dari_id`);

-- Ringkasan — lewat jalur kueri biasa. ⚠️ JANGAN diganti SHOW COLUMNS /
-- DESCRIBE: di mysqld lokal keduanya ikut membunuh server (jalur pembacaan
-- metadata kolom yang sama dengan information_schema). SELECT … LIMIT 0
-- gagal berisik kalau kolomnya tidak ada — itu yang dibutuhkan.
SELECT `lahir_dari_id`, `lahir_via` FROM `it_ticket` LIMIT 0;
SELECT `terkunci` FROM `it_test_case` LIMIT 0;
SELECT 'Fase 2b: kolom lahir_dari_id, lahir_via, terkunci siap.' AS info;
