-- ════════════════════════════════════════════════════════════════════════════
-- Katalog Modul — fitur dipetakan per LINK, bukan per node — 2026-09-08
-- IDEMPOTEN. Rencana: docs/rencana/rencana-it-tracker.md §14.27 (ralat spesifikasi).
--
-- KENAPA BERUBAH: `access_id` menunjuk SATU node access_path, padahal MENU
-- mengelompokkan berdasarkan NAVIGASI sementara KATALOG berdasarkan
-- KEPEMILIKAN — dan keduanya tidak selalu berimpit. Branch · Doctor · Doctor
-- Service bersaudara di bawah "Reservation Data Center", tapi parent itu juga
-- memuat Reservation & Pasien milik modul lain: menunjuk parent kelebihan,
-- menunjuk satu daun kekurangan. Terukur: Reservasi 1 (harusnya 2) · Master
-- Jadwal 1 (harusnya 3) · Checklist 3 (harusnya 4).
--
-- KENAPA PER LINK, BUKAN PER NODE (bukan sekadar lebih lengkap — lebih JUJUR):
-- `rawreservation` muncul 4× di menu dengan empat nama berbeda, `report` 2×.
-- Menghitung node = menghitung LABEL MENU; menghitung link = menghitung
-- HALAMAN. Yang dijanjikan katalog kepada Executive adalah "halaman yang bisa
-- dibuka" — jadi link yang benar, dan daftarnya WAJIB dedup per link.
--
-- BONUS yang menentukan bentuk kolom ini: `link` STABIL antar lingkungan,
-- sedangkan id node BERBEDA (jebakan 2026-07-24). Menyimpan link membuat
-- katalog tak perlu diselesaikan ulang tiap kali menu ditata ulang.
--
-- ⚠️ Nama kolomnya `fitur_link`, BUKAN `pakai_link`: awalan `pakai_*` di tabel
--    ini sudah punya arti lain (pengukuran pemakaian). Menyatukan dua konsep
--    di satu awalan membuat pembaca berikutnya salah baca.
--
-- ⚠️ ADD COLUMN IF NOT EXISTS + SELECT … LIMIT 0 — BUKAN information_schema /
--    SHOW COLUMNS / DESCRIBE (membunuh mysqld lokal).
-- ════════════════════════════════════════════════════════════════════════════
ALTER TABLE `it_modul`
  ADD COLUMN IF NOT EXISTS `fitur_link` text DEFAULT NULL
    COMMENT 'JSON daftar access_path.link milik modul ini; kosong/NULL = tanpa menu. Fitur dihitung DEDUP PER LINK.' AFTER `access_id`;

SELECT `access_id`, `fitur_link` FROM `it_modul` LIMIT 0;
SELECT 'it_modul.fitur_link siap. Isi daftarnya: php artisan it:seed-katalog --env-guard=<env>' AS info;
