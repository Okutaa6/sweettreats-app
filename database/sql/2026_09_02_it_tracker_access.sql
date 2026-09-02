-- ════════════════════════════════════════════════════════════════════════════
-- IT Request Tracker — node menu + grant akses — 2026-09-02
-- IDEMPOTENT: aman dijalankan berapa kali pun.
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`.
-- ════════════════════════════════════════════════════════════════════════════
-- Catatan gerbang (jebakan §14.7 rencana-it-tracker):
--   Segmen URL fitur ini `it-tracker` — segmen-1 BARU. Tanpa node access_path
--   ber-link 'it-tracker', RBAC MEMANTULKAN semua orang ke landing (302,
--   bukan 403) dan uji "nol 403" lulus palsu. Node inilah gerbangnya.
--
--   Grant ditulis BY LINK (JOIN access_path.link), bukan by id node — id
--   berbeda antar environment (jebakan 2026-07-24).
--
--   Menu TIDAK ditaruh di seksi Pusat Akun (itu perkakas admin IT): audiens
--   tracker ini termasuk Head/Executive/Konsultan, jadi ia dapat section
--   root sendiri "IT" — sejajar pola 'fin-parent' Komisi Dokter.
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Section root "IT".
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`) VALUES
  ('it-parent-0000000000000', '', 'IT', 'icon-fa fas fa-laptop-code', 96, NULL, '', NULL);

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Node menu IT Tracker. Ikon Font Awesome 5 (fa-tasks) — nama gaya FA6
--    render sebagai kotak kosong.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`) VALUES
  ('it-tracker-000000000000', 'it-parent-0000000000000', 'IT Tracker', 'icon-fa fas fa-tasks', 1, NULL, 'it-tracker', NULL);

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Grant halaman IT Tracker.
--    Audiens rencana §2: IT (Ekky+Reza), Head departemen, Executive, Konsultan
--    (Konsultan = sama seperti Executive, keputusan Robi). 'Finance' = akun
--    head Finance (Della); 'Head Marketing' = Yovan. Admin lengkap ikut.
--    Pembedaan apa yang BOLEH dilakukan tiap peran terjadi di controller —
--    grant ini hanya membuka pintunya.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('itk-akses-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 22)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.link = 'it-tracker'
WHERE gp.nama IN ('Head IT', 'IT', 'Executive', 'Consultant',
                  'Konsultan IT', 'Konsultan Finance', 'Konsultan Marketing',
                  'Finance', 'Head Marketing',
                  'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (
      SELECT 1 FROM `group_access` gx
      WHERE gx.group_id = gp.id AND gx.access_id = ap.id
  );

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. Grant parent "IT" — tanpa ini node anak TIDAK muncul di sidebar
--    (buildTree hanya menyusun anak di bawah parent yang dimiliki group).
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('itk-induk-', SUBSTRING(MD5(CONCAT(gp.id, 'it-parent-0000000000000')), 1, 22)),
       gp.id, 'it-parent-0000000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Head IT', 'IT', 'Executive', 'Consultant',
                  'Konsultan IT', 'Konsultan Finance', 'Konsultan Marketing',
                  'Finance', 'Head Marketing',
                  'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (
      SELECT 1 FROM `group_access` gx
      WHERE gx.group_id = gp.id AND gx.access_id = 'it-parent-0000000000000'
  );

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. Ringkasan — supaya hasilnya kelihatan langsung di phpMyAdmin / CLI.
-- ─────────────────────────────────────────────────────────────────────────────
SELECT gp.nama AS role_yang_dapat_akses
FROM `group_access` gx
JOIN `group_path` gp  ON gp.id = gx.group_id
JOIN `access_path` ap ON ap.id = gx.access_id
WHERE ap.link = 'it-tracker'
ORDER BY gp.nama;
