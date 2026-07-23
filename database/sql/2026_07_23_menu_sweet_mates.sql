-- ============================================================================
-- 2026_07_23_menu_sweet_mates.sql
--
-- Node menu untuk halaman "Sweet Mates" (Program Rewards KOL/KOC).
--
-- LATAR: route `sweet-mates` berada DI LUAR blok AuthGuard di routes/web.php —
-- indentasinya menipu (masih menjorok) padahal '});' penutup ada di atasnya.
-- Controllernya (Crud/SweetMatesController) juga tak punya penjaga sendiri.
-- Hasilnya: daftar member KOL/KOC + nilai komisi bisa dibuka SIAPA PUN tanpa
-- login, cukup tahu URL-nya.
--
-- Route-nya sudah dipindah ke dalam AuthGuard. Tapi memindahkan saja TIDAK
-- cukup: AuthGuard mencocokkan segmen-1 URL ke access_path.link, dan node untuk
-- 'sweet-mates' belum pernah ada (node=0, grant=0). Tanpa file ini halamannya
-- akan mati untuk semua orang — persis nasib pettycash/setoran/kol/koc.
--
-- PENEMPATAN: di bawah Marketing > Referral, bersebelahan dengan "Klaim Komisi"
-- dan "Review Konten" yang sifat datanya paling mirip.
--
-- HAK AKSES: sengaja DIPERSEMPIT ke tingkat admin yang sudah memegang halaman
-- sejenis (Klaim Komisi = Admin; Review Konten = Super Admin/Admin Backup/Admin;
-- Dashboard Marketing = Admin General). Kalau ternyata tim Marketing juga perlu,
-- tambahkan lewat menu Akses — jangan dilebarkan di sini tanpa keputusan sadar,
-- karena halaman ini memuat angka komisi.
--
-- WAJIB setelahnya: php artisan access:rebuild
-- Idempoten.
-- ============================================================================

-- Node, ditaruh di bawah parent "Referral" milik section "Marketing".
INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'mkt-sweetmates-00000000', sub.`id`, 'Sweet Mates', 'icon-fa fas fa-gift', 4, NULL, 'sweet-mates',
       CONCAT(par.`id`, ',', sub.`id`, ',', 'mkt-sweetmates-00000000')
FROM `access_path` par
JOIN `access_path` sub ON sub.`pid` = par.`id` AND sub.`nama` = 'Referral'
WHERE par.`nama` = 'Marketing'
ON DUPLICATE KEY UPDATE
  `nama` = VALUES(`nama`), `link` = VALUES(`link`), `icon` = VALUES(`icon`),
  `pid` = VALUES(`pid`), `pid_path` = VALUES(`pid_path`);

-- Grant ke tingkat admin yang sudah memegang halaman sejenis di section ini.
DELETE FROM `group_access` WHERE `access_id` = 'mkt-sweetmates-00000000';
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT UUID(), g.`id`, 'mkt-sweetmates-00000000'
FROM `group_path` g
WHERE g.`nama` IN ('Super Admin', 'Admin General', 'Admin Backup', 'Admin');

-- ============================================================================
-- VERIFIKASI:
--   SELECT COUNT(*) FROM access_path  WHERE link = 'sweet-mates';        -- 1
--   SELECT COUNT(*) FROM group_access WHERE access_id = 'mkt-sweetmates-00000000'; -- 4
-- Lalu: php artisan access:rebuild
-- Uji: buka /sweet-mates TANPA login -> harus dilempar ke halaman login.
-- ============================================================================
