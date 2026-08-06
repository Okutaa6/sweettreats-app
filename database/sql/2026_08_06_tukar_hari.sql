-- ════════════════════════════════════════════════════════════════════════════
-- Tukar Hari — satu pengajuan, dua sisi (masuk ↔ libur pengganti)
-- ════════════════════════════════════════════════════════════════════════════
-- Keputusan Ekky 2026-08-05:
--   • Tukar hari = kebijakan resmi HANYA untuk ABG/office (aturan Fleksibel).
--   • ABG tak bisa alpa, TAPI tukarnya tetap WAJIB DILAPORKAN — hari libur
--     penggantinya harus terlihat, bukan kosong senyap di kalender.
--   • Boleh N hari (masuk Minggu+Senin → 2 hari libur pengganti); jumlah masuk
--     harus SAMA dengan jumlah libur pengganti, dan semuanya dalam satu periode.
--
-- ── KENAPA BUTUH TABEL BARU ────────────────────────────────────────────────
-- `att_request` menyimpan rentang (tanggal_from..tanggal_to). Tukar hari itu
-- DAFTAR tanggal yang bisa terpisah-pisah (masuk Minggu, libur Rabu) dan punya
-- DUA PERAN berbeda dalam satu pengajuan. Memaksakannya ke rentang berarti
-- hari-hari di antaranya ikut tertandai — salah, dan diam-diam.
--
-- Alternatif "bikin beberapa pengajuan lalu dikaitkan" sengaja DITOLAK: satu
-- kesepakatan harus jadi SATU keputusan atasan. Kalau sisi masuk dan sisi
-- libur bisa diputus terpisah, orang bisa dapat libur pengganti tanpa hari
-- masuknya — persis lubang yang bikin fitur ini ada.
--
-- ── BENTUKNYA ──────────────────────────────────────────────────────────────
-- Satu baris `att_request` (tipe='tukar') memegang alasan + status + jejak
-- persetujuan. `att_request_hari` memegang tanggal-tanggalnya, satu baris per
-- tanggal, dengan peran 'masuk' atau 'libur'.
--
-- Mesin rekonsiliasi membaca tabel ini: kalau sebuah pengajuan punya tanggal
-- eksplisit, HANYA tanggal itu yang berlaku (rentang from..to diabaikan).
-- Pengajuan lama tanpa tanggal eksplisit tetap memakai rentang — tak ada
-- perilaku lama yang berubah.
--
-- tanggal_from/tanggal_to di baris induk tetap diisi (tanggal paling awal &
-- paling akhir) supaya penyaringan rentang di query tetap bekerja dan riwayat
-- lama tetap bisa dibaca alat yang belum tahu tabel ini.
--
-- Idempoten & aman diulang.

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Tambah jenis 'tukar' ke daftar jenis pengajuan
-- ─────────────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS `tkr_enum`;
DELIMITER $$
CREATE PROCEDURE `tkr_enum`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'att_request'
      AND COLUMN_NAME = 'tipe' AND COLUMN_TYPE LIKE '%tukar%'
  ) THEN
    ALTER TABLE `att_request`
      MODIFY COLUMN `tipe` ENUM('cuti','izin','libur','lembur','wfh','koreksi','tukar') NOT NULL;
  END IF;
END$$
DELIMITER ;
CALL tkr_enum();
DROP PROCEDURE IF EXISTS `tkr_enum`;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Tanggal-tanggal milik satu pengajuan
--    UNIQUE (request_id, tanggal): satu tanggal tak boleh dua peran dalam satu
--    pengajuan — "masuk sekaligus libur" itu tak punya arti.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_request_hari` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `request_id` BIGINT UNSIGNED NOT NULL,
  `tanggal`    DATE NOT NULL,
  `peran`      ENUM('masuk','libur') NOT NULL
               COMMENT 'masuk = hari saya bekerja | libur = hari libur penggantinya',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_req_tgl` (`request_id`, `tanggal`),
  KEY `idx_tanggal` (`tanggal`),
  KEY `idx_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

-- ============================================================================
-- VERIFIKASI:
--   SHOW COLUMNS FROM att_request LIKE 'tipe';        → daftar memuat 'tukar'
--   SHOW TABLES LIKE 'att_request_hari';              → ada
--   SELECT COUNT(*) FROM att_request_hari;            → 0 di awal
-- ============================================================================
