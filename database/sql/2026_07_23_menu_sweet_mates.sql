-- ============================================================================
-- 2026_07_23_menu_sweet_mates.sql
--
-- Pastikan halaman "Sweet Mates" (Program Rewards KOL/KOC) punya node menu,
-- supaya route-nya bisa dijaga AuthGuard TANPA membuat halamannya mati.
--
-- LATAR: route `sweet-mates` berada DI LUAR blok AuthGuard di routes/web.php —
-- indentasinya menipu (masih menjorok) padahal '});' penutup ada tepat di
-- atasnya. Controllernya (Crud/SweetMatesController) juga tak punya penjaga.
-- Route sudah dipindah ke dalam AuthGuard. Tapi memindahkan saja tak cukup:
-- AuthGuard mencocokkan segmen-1 URL ke access_path.link, jadi tanpa node yang
-- ter-grant, halamannya mati untuk semua orang.
--
-- PENTING — DIJAGA PER `link`, BUKAN PER id:
-- Di server produksi node ini SUDAH ADA (id UUID, di bawah Marketing >
-- "KOL & KOC Dashboard", ter-grant ke 5 role). Kalau file ini memaksa membuat
-- node dgn id karangan sendiri, hasilnya DUA node ber-link 'sweet-mates' →
-- menu dobel, dan lebih buruk: mencabut akses lewat UI jadi gagal senyap karena
-- AuthGuard cukup menemukan SATU grant yang tersisa.
-- Maka: kalau node ber-link 'sweet-mates' sudah ada, file ini TIDAK melakukan
-- apa pun. Ia hanya menambal database yang memang kehilangan node tersebut.
--
-- Penempatan & hak akses mengikuti yang sudah berlaku di produksi:
--   Marketing > KOL & KOC Dashboard > Sweet Mates
--   role: Super Admin, Admin General, Admin, Head Marketing, Content Creator
--
-- WAJIB setelahnya: php artisan access:rebuild
-- Idempoten & aman dijalankan di server mana pun.
-- ============================================================================

-- 1. Sub-parent "KOL & KOC Dashboard" di bawah section "Marketing" (kalau hilang).
INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'mkt-kolkoc-dash-000000', par.`id`, 'KOL & KOC Dashboard', 'fa fa-users', 2, NULL, '',
       CONCAT(par.`id`, ',', 'mkt-kolkoc-dash-000000')
FROM `access_path` par
WHERE par.`nama` = 'Marketing' AND (par.`pid` IS NULL OR par.`pid` = '')
  AND NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `access_path`) x
    WHERE x.`pid` = par.`id` AND x.`nama` = 'KOL & KOC Dashboard'
  );

-- 2. Node "Sweet Mates" — HANYA kalau belum ada node ber-link 'sweet-mates'.
INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'mkt-sweetmates-00000000', sub.`id`, 'Sweet Mates', 'dripicons-trophy', 4, NULL, 'sweet-mates',
       CONCAT(IFNULL(sub.`pid`, ''), ',', sub.`id`, ',', 'mkt-sweetmates-00000000')
FROM `access_path` sub
WHERE sub.`nama` = 'KOL & KOC Dashboard'
  AND NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `access_path`) y WHERE y.`link` = 'sweet-mates'
  )
LIMIT 1;

-- 3. Grant — mengikuti role yang berlaku di produksi. Hanya untuk node yang
--    dibuat file ini; node bawaan server tak disentuh grant-nya.
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT UUID(), g.`id`, 'mkt-sweetmates-00000000'
FROM `group_path` g
WHERE g.`nama` IN ('Super Admin', 'Admin General', 'Admin', 'Head Marketing', 'Content Creator')
  AND EXISTS (SELECT 1 FROM (SELECT * FROM `access_path`) z WHERE z.`id` = 'mkt-sweetmates-00000000')
  AND NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `group_access`) w
    WHERE w.`group_id` = g.`id` AND w.`access_id` = 'mkt-sweetmates-00000000'
  );

-- ============================================================================
-- VERIFIKASI — HARUS tepat 1 baris (bukan 2; kalau 2 berarti node dobel):
--   SELECT id, nama FROM access_path WHERE link = 'sweet-mates';
--
-- Lalu: php artisan access:rebuild
-- Uji: buka /sweet-mates TANPA login -> harus dilempar ke halaman login.
-- ============================================================================
