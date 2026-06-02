-- =====================================================================
-- Menu access untuk Rekonsiliasi (Omnicare) — 2026-05-21
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   Daftarkan halaman /reconcile/* ke access_path (segment-1 'reconcile')
--   supaya AuthGuard mengizinkan akses + sidebar menampilkan menu.
--   Dipasang sebagai child kedua di bawah sub-menu "Laporan" yang sudah ada
--   (parent b1a70001 → sub b1a70002), sebelah "Laporan Penjualan".
--   Di-grant ke SEMUA group.
--
-- Prasyarat: 2026_05_21_omnicare_report_access.sql sudah dijalankan
--   (parent & sub "Laporan" b1a70001 / b1a70002 harus sudah ada).
--
-- Idempoten: fixed UUID + WHERE NOT EXISTS — aman dijalankan ulang.
--
-- SETELAH file ini, WAJIB regenerate cache .txt:
--   php artisan access:rebuild
-- =====================================================================

-- ── access_path: child "Rekonsiliasi" → link 'reconcile' ──
INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'b1a70004-0000-4a17-9e30-5a1e5a1e0004', 'b1a70002-0000-4a17-9e30-5a1e5a1e0002', 'Rekonsiliasi',
       'dripicons-link', 2, NULL, 'reconcile',
       'b1a70001-0000-4a17-9e30-5a1e5a1e0001,b1a70002-0000-4a17-9e30-5a1e5a1e0002'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `access_path` WHERE `id` = 'b1a70004-0000-4a17-9e30-5a1e5a1e0004');

-- ── group_access: grant child baru ke SEMUA group ──
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT UUID(), g.`id`, 'b1a70004-0000-4a17-9e30-5a1e5a1e0004'
FROM `group_path` g
WHERE NOT EXISTS (
  SELECT 1 FROM `group_access` ga
  WHERE ga.`group_id` = g.`id` AND ga.`access_id` = 'b1a70004-0000-4a17-9e30-5a1e5a1e0004'
);

-- =====================================================================
-- Verifikasi:
--   SELECT * FROM access_path WHERE id = 'b1a70004-0000-4a17-9e30-5a1e5a1e0004';
--   SELECT g.nama FROM group_access ga JOIN group_path g ON g.id=ga.group_id
--     WHERE ga.access_id = 'b1a70004-0000-4a17-9e30-5a1e5a1e0004';
-- Lalu: php artisan access:rebuild
-- =====================================================================
