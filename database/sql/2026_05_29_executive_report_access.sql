-- =====================================================================
-- Menu access untuk Executive Report — 2026-05-29
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   Daftarkan halaman /report/executive ke sidebar sebagai child ketiga di
--   bawah sub-menu "Laporan" (parent b1a70001 → sub b1a70002), di sebelah
--   "Laporan Penjualan" & "Rekonsiliasi".
--
--   PENTING — beda dengan report access lain: entry ini HANYA di-grant ke group
--   admin (Super Admin & Admin General). Belum ada role executive khusus, jadi
--   sementara dibatasi admin. AuthGuard sendiri hanya cek segment-1 'report'
--   (sudah granted ke semua untuk Laporan Penjualan), karena itu gate sebenarnya
--   ada DI CONTROLLER (ExecutiveReportController abort 403 untuk non-admin).
--   Grant selektif di sini hanya mengatur VISIBILITAS menu sidebar.
--
--   link = 'report/executive' supaya url('report/executive') benar; AuthGuard
--   tetap lolos via grant 'report' yang sudah ada.
--
-- Prasyarat: 2026_05_21_omnicare_report_access.sql sudah dijalankan
--   (parent & sub "Laporan" b1a70001 / b1a70002 harus sudah ada).
--
-- Idempoten: fixed UUID + WHERE NOT EXISTS — aman dijalankan ulang.
--
-- SETELAH file ini, WAJIB regenerate cache .txt:
--   php artisan access:rebuild
-- =====================================================================

-- ── access_path: child "Executive Report" → link 'report/executive' ──
INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'b1a70005-0000-4a17-9e30-5a1e5a1e0005', 'b1a70002-0000-4a17-9e30-5a1e5a1e0002', 'Executive Report',
       'dripicons-meter', 3, NULL, 'report/executive',
       'b1a70001-0000-4a17-9e30-5a1e5a1e0001,b1a70002-0000-4a17-9e30-5a1e5a1e0002'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `access_path` WHERE `id` = 'b1a70005-0000-4a17-9e30-5a1e5a1e0005');

-- ── group_access: grant HANYA ke group admin ──
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT UUID(), g.`id`, 'b1a70005-0000-4a17-9e30-5a1e5a1e0005'
FROM `group_path` g
WHERE g.`nama` IN ('Super Admin', 'Admin General')
  AND NOT EXISTS (
    SELECT 1 FROM `group_access` ga
    WHERE ga.`group_id` = g.`id` AND ga.`access_id` = 'b1a70005-0000-4a17-9e30-5a1e5a1e0005'
  );

-- =====================================================================
-- Verifikasi:
--   SELECT * FROM access_path WHERE id = 'b1a70005-0000-4a17-9e30-5a1e5a1e0005';
--   SELECT g.nama FROM group_access ga JOIN group_path g ON g.id=ga.group_id
--     WHERE ga.access_id = 'b1a70005-0000-4a17-9e30-5a1e5a1e0005';  -- harus cuma admin
-- Lalu: php artisan access:rebuild
-- =====================================================================
