-- ════════════════════════════════════════════════════════════════════════════
-- Konsolidasi sidebar Absensi — node "Kehadiran" jadi pintu tunggal
-- ════════════════════════════════════════════════════════════════════════════
-- Batch rapi-rapi pasca-launch (ACC Ekky 2026-08-05), item 1.
--
-- Sebelum ini /absensi/kehadiran TIDAK punya node menu sama sekali — halaman
-- dibuka lewat tab (_finance_tabs) dari Upload Data dkk. Sekarang dibalik:
-- "Kehadiran" satu-satunya baris di sidebar; Upload Data / Mapping Karyawan /
-- Rekap Persetujuan / Periode Gaji cukup lewat bar tab di dalam halaman.
-- Penyembunyian tab dari sidebar dilakukan di KODE (shellTabbedLinks di
-- app/Helpers.php), BUKAN dengan mencabut grant.
--
-- ⚠️ JANGAN PERNAH mencabut grant node ber-link 'absensi': itu kunci AuthGuard
--    untuk SEMUA halaman /absensi/* (segment-1). Dibuktikan di prod 2026-08-05:
--    cabut grant → seluruh halaman absensi role itu mati → dikembalikan.
--
-- Grant "Kehadiran" HANYA untuk role yang lolos canSeeAllBranches() —
-- controller-nya (AbsensiKehadiranController) menolak selain itu, dan baris
-- sidebar yang berujung 403 adalah kegagalan senyap.
--
-- Id node & pid TIDAK di-hardcode ke id env tertentu: pid di-resolve BY NAMA
-- parent level-0 'Absensi' (pola yang sama dgn 2026_08_05_sidebar_marketing).
-- ⚠️ JANGAN resolve by link 'absensi' + pid kosong: pembawa link 'absensi'
-- BEDA antar env (lokal: parent "Absensi"; dev/prod: anak "Upload Data") —
-- terverifikasi 2026-08-05, resolusi by-link no-op senyap di dev.
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`.
--
-- Semua DML pada tabel InnoDB → dibungkus transaksi: putus di tengah tidak
-- meninggalkan node tanpa grant (menu ada, aksesnya tidak).

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Node "Kehadiran" di bawah parent level-0 "Absensi"
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'abs-kehadiran-00000000', p.id, 'Kehadiran', 'icon-fa fas fa-calendar-check', 1, NULL, 'absensi/kehadiran', NULL
FROM `access_path` p
WHERE p.nama = 'Absensi' AND (p.pid IS NULL OR p.pid = '')
  AND NOT EXISTS (SELECT 1 FROM `access_path` x WHERE x.link = 'absensi/kehadiran')
LIMIT 1;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Grant "Kehadiran" — persis daftar canSeeAllBranches() (app/Helpers.php)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-kh-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 25)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.link = 'absensi/kehadiran'
WHERE gp.nama IN ('Super Admin', 'Admin General', 'Admin Backup', 'Finance')
  AND NOT EXISTS (SELECT 1 FROM `group_access` gx WHERE gx.group_id = gp.id AND gx.access_id = ap.id);

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Jaring pengaman: pastikan parent "Absensi" ikut ter-grant untuk role di
--    atas (tanpa parent, buildTree tidak menampilkan anaknya). Normalnya
--    sudah ada semua — NOT EXISTS bikin ini no-op.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-khp-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 24)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.nama = 'Absensi' AND (ap.pid IS NULL OR ap.pid = '')
WHERE gp.nama IN ('Super Admin', 'Admin General', 'Admin Backup', 'Finance')
  AND NOT EXISTS (SELECT 1 FROM `group_access` gx WHERE gx.group_id = gp.id AND gx.access_id = ap.id);

COMMIT;
