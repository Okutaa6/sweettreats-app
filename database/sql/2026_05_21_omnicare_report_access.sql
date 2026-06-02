-- =====================================================================
-- Menu access untuk Laporan Penjualan (Omnicare) — 2026-05-21
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   Daftarkan halaman /report/* ke access_path (segment-1 'report') supaya
--   AuthGuard mengizinkan akses + sidebar menampilkan menu "Laporan".
--   Struktur 3 level: parent "Laporan" → sub "Laporan" → child "Laporan Penjualan".
--   Di-grant ke SEMUA group.
--
-- Idempoten: pakai fixed UUID + WHERE NOT EXISTS — aman dijalankan ulang.
--
-- SETELAH file ini, WAJIB regenerate cache .txt:
--   php artisan access:rebuild
-- =====================================================================

-- ── access_path: parent "Laporan" (urutan 3, antara Data Center & Marketing) ──
INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'b1a70001-0000-4a17-9e30-5a1e5a1e0001', NULL, 'Laporan', 'dripicons-document', 3, NULL, '', NULL
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `access_path` WHERE `id` = 'b1a70001-0000-4a17-9e30-5a1e5a1e0001');

-- ── access_path: sub "Laporan" ──
INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'b1a70002-0000-4a17-9e30-5a1e5a1e0002', 'b1a70001-0000-4a17-9e30-5a1e5a1e0001', 'Laporan',
       'dripicons-list', 1, NULL, '', 'b1a70001-0000-4a17-9e30-5a1e5a1e0001'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `access_path` WHERE `id` = 'b1a70002-0000-4a17-9e30-5a1e5a1e0002');

-- ── access_path: child "Laporan Penjualan" → link 'report' ──
INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'b1a70003-0000-4a17-9e30-5a1e5a1e0003', 'b1a70002-0000-4a17-9e30-5a1e5a1e0002', 'Laporan Penjualan',
       'dripicons-graph-bar', 1, NULL, 'report',
       'b1a70001-0000-4a17-9e30-5a1e5a1e0001,b1a70002-0000-4a17-9e30-5a1e5a1e0002'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `access_path` WHERE `id` = 'b1a70003-0000-4a17-9e30-5a1e5a1e0003');

-- ── group_access: grant 3 entry baru ke SEMUA group ──
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT UUID(), g.`id`, ap.`id`
FROM `group_path` g
CROSS JOIN (
  SELECT 'b1a70001-0000-4a17-9e30-5a1e5a1e0001' AS `id`
  UNION ALL SELECT 'b1a70002-0000-4a17-9e30-5a1e5a1e0002'
  UNION ALL SELECT 'b1a70003-0000-4a17-9e30-5a1e5a1e0003'
) ap
WHERE NOT EXISTS (
  SELECT 1 FROM `group_access` ga
  WHERE ga.`group_id` = g.`id` AND ga.`access_id` = ap.`id`
);

-- =====================================================================
-- Verifikasi:
--   SELECT * FROM access_path WHERE id LIKE 'b1a7%';
--   SELECT g.nama, COUNT(*) FROM group_access ga
--     JOIN group_path g ON g.id=ga.group_id
--     WHERE ga.access_id LIKE 'b1a7%' GROUP BY g.nama;
-- Lalu: php artisan access:rebuild
-- =====================================================================
