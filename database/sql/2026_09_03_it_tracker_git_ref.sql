-- ════════════════════════════════════════════════════════════════════════════
-- IT Request Tracker — Fase 2a: jejak git per tiket (Kelas B) — 2026-09-03
-- IDEMPOTENT: aman dijalankan berapa kali pun.
--
-- Rencana §14.3: FRM-IT-03 TERISI SENDIRI dari git. Isinya diambil perintah
-- artisan `it:sync-git` (dijadwalkan harian + tombol segarkan di layar), lalu
-- layar tinggal MEMBACA tabel ini.
-- ⛔ JANGAN shell `git log` dari request web — layar hanya membaca tabel.
--
-- ⚠️ Fitur ini tidak menyentuh tabel lama mana pun.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `it_git_ref` (
  `id`           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_nomor` varchar(16)  NOT NULL              COMMENT 'IT-2026-001 — diurai dari subject commit',
  `branch`       varchar(255) DEFAULT NULL          COMMENT 'ref tempat commit ditemukan (git log --source)',
  `commit_sha`   varchar(40)  NOT NULL,
  `subject`      varchar(512) DEFAULT NULL,
  `commit_at`    datetime     DEFAULT NULL,
  `created_at`   timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- Rencana §14.3 menulis "unik pada commit_sha". DISENGAJA dilebarkan jadi
  -- (ticket_nomor, commit_sha): satu commit boleh menyebut DUA tiket
  -- ("IT-2026-004 + IT-2026-007: ..."), dan dengan unik-sha-saja tiket kedua
  -- kehilangan jejak gitnya diam-diam — persis kegagalan yang membuat Kelas B
  -- tak dipercaya. Sifat idempotennya sama persis (uji terima §14.6 no. 14).
  UNIQUE KEY `uq_it_git_ref` (`ticket_nomor`, `commit_sha`),
  KEY `ix_it_git_ref_nomor` (`ticket_nomor`, `commit_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
