-- ════════════════════════════════════════════════════════════════════════════
-- WiFi Cabang belajar sendiri — bekal tabel
-- ════════════════════════════════════════════════════════════════════════════
-- Rencana + keputusan: docs/rencana/rencana-wifi-belajar-sendiri.md (ACC Ekky
-- 2026-08-12: ambang 3 orang, otomatis penuh, pagar /48, pemicu saat absen).
--
-- Dua hal saja yang dibutuhkan:
--   1. att_branch_ip.sumber — membedakan baris yang lahir sendiri dari baris
--      yang didaftarkan manusia. Bukan hiasan: §3 mensyaratkan baris otomatis
--      "diberi label jelas dan perlu ditinjau", dan halaman WiFi Cabang harus
--      bisa menyorotnya tanpa menebak-nebak isi kolom label.
--   2. sys_setting 'wifi_belajar_sendiri' — saklar mati. §3 minta fiturnya
--      "gampang dinonaktifkan dari halaman WiFi Cabang yang sudah ada", jadi
--      simpanannya harus data (bisa diklik admin), bukan .env (butuh deploy).
--
-- Idempoten: aman dijalankan dua kali.
-- ════════════════════════════════════════════════════════════════════════════

-- ── 1. Kolom sumber ──────────────────────────────────────────────────────────
-- Baris lama semuanya buatan manusia → default 'manual' sudah benar untuk
-- seluruh isi tabel saat ini; tak ada backfill yang perlu dijalankan.
SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'att_branch_ip'
               AND COLUMN_NAME  = 'sumber');

SET @sql := IF(@ada = 0,
  "ALTER TABLE `att_branch_ip`
     ADD COLUMN `sumber` enum('manual','otomatis') NOT NULL DEFAULT 'manual'
     COMMENT 'otomatis = lahir dari WiFi-belajar-sendiri, belum ditinjau manusia'
     AFTER `label`",
  "SELECT 'kolom sumber sudah ada — dilewati'");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 1b. Indeks jendela 72 jam ────────────────────────────────────────────────
-- Menghitung "siapa saja yang memakai /64 ini" berarti menyapu att_hp_bukti
-- berdasarkan taken_at SAJA. Indeks yang ada (user_id, taken_at) tak terpakai
-- untuk itu — tanpa indeks ini, tiap absen ber-IP asing memindai seluruh tabel,
-- dan tabelnya tumbuh ~150 baris/hari. Cocok-prefix tetap dikerjakan di PHP
-- (satu /64 punya tak terhingga bentuk tertulis), jadi yang bisa dipersempit di
-- SQL hanyalah rentang waktunya — justru karena itu indeksnya penting.
SET @ada := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'att_hp_bukti'
               AND INDEX_NAME   = 'idx_bukti_taken');

SET @sql := IF(@ada = 0,
  "ALTER TABLE `att_hp_bukti` ADD INDEX `idx_bukti_taken` (`taken_at`)",
  "SELECT 'indeks idx_bukti_taken sudah ada — dilewati'");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2. Saklar ────────────────────────────────────────────────────────────────
-- Menyala sejak awal. Kalau nanti dimatikan lewat halaman WiFi Cabang, baris
-- ini berubah jadi '0' — jangan dijalankan ulang tanpa sadar, INSERT IGNORE
-- di bawah memang sengaja tidak menimpa nilai yang sudah ada.
INSERT IGNORE INTO `sys_setting` (`key`, `value`) VALUES ('wifi_belajar_sendiri', '1');
