-- ════════════════════════════════════════════════════════════════════════════
-- Katalog Modul — skema (halaman saudara IT Request Tracker) — 2026-09-06
-- IDEMPOTEN: aman dijalankan berapa kali pun.
-- Rencana: docs/rencana/rencana-it-tracker.md §14.24–14.25.
-- Isi: docs/rencana/katalog-modul-2026-09.md (33 modul, sudah dikoreksi Ekky).
--
-- ⚠️ ADD COLUMN IF NOT EXISTS + SELECT … LIMIT 0 — BUKAN information_schema /
--    SHOW COLUMNS / DESCRIBE (membunuh mysqld lokal).
--
-- ⛔ TIGA ANGKA TIDAK DISIMPAN DI SINI. "hidup" dihitung dari `status`;
--    "berjalan" & "utang" dihitung dari it_ticket. Angka tersimpan basi
--    diam-diam — sama alasannya dengan larangan kolom "berapa hari menunggu"
--    di it_ticket_fase.
-- ⛔ DAFTAR FITUR JUGA TIDAK DISIMPAN. Fitur = keturunan `access_id` di
--    access_path yang punya `link` — diturunkan saat dibaca, jadi tak bisa
--    basi. Katalog yang diketik tangan akan salah dalam dua bulan.
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. it_modul — satu baris per entri katalog (33 entri saat seed pertama).
--    `nama` UNIK: itulah kunci idempotensi seeder DAN yang dilihat manusia.
--    `sejak_terbukti` = ada tabel yang membuktikan tanggalnya. Kalau 0 dan
--    status hidup, layar menulis "belum terbukti" — BUKAN tanggal karangan
--    dan bukan kosong yang tampak seperti data hilang.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `it_modul` (
  `id`             bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama`           varchar(120) NOT NULL                COMMENT 'yang disebut departemen kalau ditanya "sistem apa yang kalian punya?"',
  `departemen`     varchar(64)  NOT NULL                COMMENT 'PEMILIK — satu departemen, tak pernah dua',
  `jenis`          varchar(32)  NOT NULL                COMMENT 'Operasional / Laporan & Dashboard / Integrasi / Platform',
  `status`         varchar(12)  NOT NULL DEFAULT 'rencana' COMMENT 'hidup / dibangun / rencana — BUKAN progres, jangan pernah dijadikan persen',
  `sejak`          date         DEFAULT NULL            COMMENT 'data paling awal yang bisa dibuktikan — bukan tanggal rilis resmi',
  `sejak_terbukti` tinyint(1)   NOT NULL DEFAULT 0      COMMENT '1 = ada tabel yang membuktikannya; 0 + hidup = "belum terbukti"',
  `access_id`      varchar(255) DEFAULT NULL            COMMENT 'access_path.id — akar menu modul ini; NULL = tanpa menu (berjalan di belakang)',
  `catatan`        text         DEFAULT NULL            COMMENT 'bukti data, peringatan, warisan — tampil di panel',
  `urutan`         int(11)      NOT NULL DEFAULT 0,
  `created_at`     timestamp NULL DEFAULT NULL,
  `updated_at`     timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_it_modul_nama` (`nama`),
  KEY `ix_it_modul_dept` (`departemen`),
  KEY `ix_it_modul_jenis` (`jenis`),
  KEY `ix_it_modul_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. it_ticket.modul_id — sambungan papan ⇄ katalog.
--    ⛔ TIDAK ADA tebakan dari judul. Mockup menebak lewat regex; tebakan yang
--    meleset TIDAK TERLIHAT siapa pun dan diam-diam merusak angka utang per
--    modul selamanya. Kolom ini diisi manusia saat triase; yang kosong masuk
--    baris "Tanpa modul" di katalog — jujur kosong, bukan diam-diam salah.
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `it_ticket`
  ADD COLUMN IF NOT EXISTS `modul_id` bigint(20) unsigned DEFAULT NULL
    COMMENT 'it_modul.id — modul yang disentuh tiket ini; NULL = belum ditentukan' AFTER `induk_id`,
  ADD INDEX IF NOT EXISTS `ix_it_ticket_modul` (`modul_id`);

SELECT `modul_id` FROM `it_ticket` LIMIT 0;
SELECT `nama`, `departemen`, `jenis`, `status`, `sejak`, `sejak_terbukti`, `access_id`, `catatan`, `urutan`
FROM `it_modul` LIMIT 0;
SELECT 'it_modul + it_ticket.modul_id siap. Isi katalognya: php artisan it:seed-katalog --env-guard=<local|dev|prod>' AS info;
