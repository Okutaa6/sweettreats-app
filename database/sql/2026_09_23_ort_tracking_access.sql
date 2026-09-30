-- ════════════════════════════════════════════════════════════════════════════
-- Tracking Pasien Ortho — node menu + grant akses — 2026-09-23
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- ⚠️ SETELAH berkas ini WAJIB `php artisan access:rebuild`, lalu logout-login.
--    Kalau dilewat, struktur akses di storage/app/group_access/*.txt tidak ikut
--    diperbarui dan AuthGuard menolak semua → 404 di SEMUA halaman, bukan cuma
--    modul ini.
-- ════════════════════════════════════════════════════════════════════════════
-- KONTEKS
--   Modul Tracking Pasien Ortho menggantikan Google Sheets per cabang yang
--   dipakai Sales untuk menagih pasien behel kembali kontrol. Berkas ini hanya
--   mengurus menu dan izinnya; skemanya ada di
--   2026_09_23_ort_tracking_schema.sql.
--
-- CATATAN GERBANG (jangan salah kaprah)
--   Segmen URL halaman ini `tracking-ortho`, BUKAN di bawah `finance`/`report`.
--   Kedua segmen itu ada di AuthGuard::SKIP_RBAC sehingga halaman di bawahnya
--   tidak dijaga RBAC menu sama sekali. Dengan segmen sendiri, node ber-link
--   'tracking-ortho' inilah yang menentukan siapa boleh membukanya — tanpa
--   grant, user kena 403/redirect landing. Tidak perlu guard tambahan di
--   controller.
--
--   Grant ditulis BY LINK (JOIN access_path.link), bukan by id node: id berbeda
--   antar environment dan pernah bikin akses "sudah dicentang tapi tetap 403"
--   (jebakan 2026-07-24).
--
-- YANG DILAKUKAN
--   1. Cari node induk yang tepat LEWAT NAMA, bukan id.
--   2. Buat node menu Tracking Ortho di bawahnya.
--   3. Grant halaman ke role yang memakainya.
--   4. Grant node induk juga — tanpa ini anaknya tidak muncul di sidebar.
--   5. Cetak ringkasan verifikasi.
--
-- YANG SENGAJA TIDAK DILAKUKAN
--   • TIDAK meng-grant akar "Data Center". Akar itu pernah membawa link mati
--     dan meng-grant-nya sempat hampir memberi MetricaSetting ke role yang
--     dikecualikan (2026_08_05_akses_data_center_root.sql). RebuildGroupAccess
--     sudah menyisipkan leluhur yang tak di-grant sebagai WADAH, jadi induknya
--     cukup satu tingkat.
--   • TIDAK memberi akses ke Coordinator Dentist, Executive, dan Finance —
--     halaman ini pekerjaan Sales/Front Office. Kalau nanti perlu, cukup
--     tambahkan namanya di kedua daftar WHERE di bawah; nol perubahan kode.
--   • TIDAK menyentuh grant halaman lain.
-- ════════════════════════════════════════════════════════════════════════════

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Tentukan node induk — LEWAT NAMA, karena id berbeda antar environment.
--
--    Urutan pilihan, dari yang paling tepat:
--      a. "Reservation Data Center" — di sinilah Pasien, Doctor, dan Reservation
--         bertetangga hari ini (lihat 2026_08_05_akses_data_center_root.sql:61).
--         Tracking Ortho memang sekelompok dengan mereka.
--      b. "Data Center" tingkat akar — cadangan kalau (a) belum ada.
--      c. '' (akar) — cadangan terakhir supaya berkas ini tidak pernah gagal.
--         Kalau jatuh ke sini, menunya muncul sebagai baris tingkat atas;
--         pindahkan belakangan dengan mengubah `pid`, bukan membuat node baru.
-- ─────────────────────────────────────────────────────────────────────────────
SET @ort_pid := COALESCE(
  (SELECT `id` FROM `access_path`
    WHERE `nama` = 'Reservation Data Center' ORDER BY `id` LIMIT 1),
  (SELECT `id` FROM `access_path`
    WHERE `nama` = 'Data Center' AND (`pid` IS NULL OR `pid` = '') ORDER BY `id` LIMIT 1),
  ''
);

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Node menu Tracking Ortho.
--    Ikon memakai nama Font Awesome 5 (`fa-user-clock`) — tema ini self-host
--    FA5, nama bergaya FA6 akan render sebagai kotak kosong. Dipilih karena
--    halaman ini soal "pasien + jadwal", bukan soal gigi.
--    `urutan` 12 menempatkannya sesudah Pasien & Reservation di kelompok itu.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `access_path`
  (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
VALUES
  ('ort-tracking-0000000000', @ort_pid, 'Tracking Ortho',
   'icon-fa fas fa-user-clock', 12, NULL, 'tracking-ortho', NULL);

-- Kalau node-nya sudah ada dari percobaan sebelumnya tapi induknya berubah
-- (mis. berkas ini pertama kali jalan sebelum "Reservation Data Center" dibuat),
-- betulkan induknya — jangan biarkan menggantung di akar.
UPDATE `access_path`
   SET `pid` = @ort_pid
 WHERE `id` = 'ort-tracking-0000000000'
   AND `pid` <> @ort_pid
   AND @ort_pid <> '';

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Grant halaman Tracking Ortho.
--    Sales & Front Office = pengguna utamanya. Supervisor ikut karena sudah
--    memegang paket sales (Pasien/Doctor/Reservation). Role IT & admin ikut
--    untuk pengembangan dan dukungan.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('ort-akses-', SUBSTRING(MD5(CONCAT(gp.`id`, ap.`id`)), 1, 22)),
       gp.`id`, ap.`id`
FROM `group_path` gp
JOIN `access_path` ap ON ap.`link` = 'tracking-ortho'
WHERE gp.`nama` IN ('Sales', 'Front Office', 'Supervisor',
                    'Super Admin', 'Admin General', 'Admin Backup', 'Admin',
                    'Head IT', 'IT')
  AND NOT EXISTS (
      SELECT 1 FROM `group_access` gx
      WHERE gx.`group_id` = gp.`id` AND gx.`access_id` = ap.`id`
  );

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. Grant node INDUK — tanpa ini node anak TIDAK muncul di sidebar
--    (buildTree hanya menyusun anak di bawah parent yang dimiliki group).
--    No-op untuk role yang sudah memegangnya lewat Pasien / Reservation.
--    Dilewati kalau induknya akar ('') — akar tidak perlu di-grant.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('ort-induk-', SUBSTRING(MD5(CONCAT(gp.`id`, @ort_pid)), 1, 22)),
       gp.`id`, @ort_pid
FROM `group_path` gp
WHERE @ort_pid <> ''
  AND gp.`nama` IN ('Sales', 'Front Office', 'Supervisor',
                    'Super Admin', 'Admin General', 'Admin Backup', 'Admin',
                    'Head IT', 'IT')
  AND NOT EXISTS (
      SELECT 1 FROM `group_access` gx
      WHERE gx.`group_id` = gp.`id` AND gx.`access_id` = @ort_pid
  );

COMMIT;

-- ════════════════════════════════════════════════════════════════════════════
-- 5. Ringkasan — supaya hasilnya kelihatan langsung di phpMyAdmin / CLI.
-- ════════════════════════════════════════════════════════════════════════════
SELECT ap.`nama`   AS node,
       ap.`link`   AS link,
       ap.`icon`   AS ikon,
       COALESCE(induk.`nama`, '(akar)') AS induk
  FROM `access_path` ap
  LEFT JOIN `access_path` induk ON induk.`id` = ap.`pid`
 WHERE ap.`link` = 'tracking-ortho';

SELECT gp.`nama` AS role_yang_dapat_akses
  FROM `group_access` gx
  JOIN `group_path` gp  ON gp.`id` = gx.`group_id`
  JOIN `access_path` ap ON ap.`id` = gx.`access_id`
 WHERE ap.`link` = 'tracking-ortho'
 ORDER BY gp.`nama`;

-- ════════════════════════════════════════════════════════════════════════════
-- VERIFIKASI setelah jalan + `access:rebuild` (JANGAN DILEWATI):
--   1. Kedua SELECT di atas mengembalikan baris — node ada, dan daftar role
--      terisi. Kalau kolom `induk` berbunyi "(akar)", node-nya menggantung:
--      cari nama node induk yang benar lalu jalankan ulang berkas ini.
--   2. Login sebagai Sales cabang → menu "Tracking Ortho" muncul TEPAT SATU
--      baris, dan /tracking-ortho terbuka.
--   3. Login sebagai role yang TIDAK di daftar (mis. Coordinator Dentist) →
--      /tracking-ortho menjawab 403 / redirect ke landing page role itu.
--      Kalau malah tembus, segmen URL-nya salah atau access:rebuild belum jalan.
-- ════════════════════════════════════════════════════════════════════════════
