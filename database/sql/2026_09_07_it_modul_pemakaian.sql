-- ════════════════════════════════════════════════════════════════════════════
-- Katalog Modul — kolom PEMAKAIAN ("dipakai N× 7 hari terakhir") — 2026-09-07
-- IDEMPOTEN. Rencana: docs/rencana/rencana-it-tracker.md §14.26.
--
-- KENAPA KOLOM INI ADA (jangan dihapus saat menyederhanakan):
--   Dengan tiga angka lama (hidup · berjalan · utang), "Keluhan Sistem" terlihat
--   SEMPURNA SEHAT — status Hidup, nol tiket berjalan, nol utang. Padahal
--   terukur MATI: 3 catatan seumur hidup, terakhir 28 Juni, nol dalam 30 hari.
--   Angka pemakaian satu-satunya yang membedakan modul TENANG KARENA BERES dari
--   modul SUNYI KARENA DITINGGALKAN — dan yang kedua adalah kegagalan paling
--   mahal, karena tak ada yang mengeluh.
--
-- ⚠️ Menghitung live tiap muat halaman MAHAL (omnicare_sales_detail 146rb baris),
--    jadi angkanya DISIMPAN oleh `php artisan it:hitung-pemakaian` (harian).
--    Angka tersimpan bisa basi ⇒ `pakai_at` WAJIB ikut tampil di sebelahnya.
--    Angka tersimpan boleh, asal usianya kelihatan.
--
-- ⚠️ ADD COLUMN IF NOT EXISTS + SELECT … LIMIT 0 — BUKAN information_schema /
--    SHOW COLUMNS / DESCRIBE (membunuh mysqld lokal).
-- ════════════════════════════════════════════════════════════════════════════
ALTER TABLE `it_modul`
  ADD COLUMN IF NOT EXISTS `pakai_tabel` varchar(64) DEFAULT NULL
    COMMENT 'tabel yang membuktikan modul ini DIPAKAI; NULL = belum terukur (tampil "—", BUKAN 0)' AFTER `access_id`,
  ADD COLUMN IF NOT EXISTS `pakai_kolom` varchar(64) DEFAULT NULL
    COMMENT 'kolom waktu di tabel itu, mis. created_at' AFTER `pakai_tabel`,
  ADD COLUMN IF NOT EXISTS `pakai_7hari` int(11) DEFAULT NULL
    COMMENT 'hasil hitung terakhir: baris dalam 7 hari; NULL = belum pernah dihitung' AFTER `pakai_kolom`,
  ADD COLUMN IF NOT EXISTS `pakai_at` datetime DEFAULT NULL
    COMMENT 'kapan angka itu dihitung — WAJIB tampil di sebelah angkanya supaya usianya kelihatan' AFTER `pakai_7hari`;

SELECT `pakai_tabel`, `pakai_kolom`, `pakai_7hari`, `pakai_at` FROM `it_modul` LIMIT 0;
SELECT 'it_modul: kolom pemakaian siap. Isi pemetaannya: php artisan it:seed-katalog --env-guard=<env>, lalu hitung: php artisan it:hitung-pemakaian' AS info;
