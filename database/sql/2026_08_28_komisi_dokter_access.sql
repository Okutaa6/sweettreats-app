-- ════════════════════════════════════════════════════════════════════════════
-- Komisi Dokter — node menu + grant akses — 2026-08-28
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`.
-- ════════════════════════════════════════════════════════════════════════════
-- Catatan gerbang (jangan salah kaprah):
--   Segmen URL halaman ini adalah `komisi-dokter`, BUKAN di bawah `finance`.
--   `finance` ada di AuthGuard::SKIP_RBAC sehingga halaman di bawahnya tidak
--   dijaga RBAC menu sama sekali. Dengan segmen sendiri, node ber-link
--   'komisi-dokter' inilah yang menentukan siapa boleh membuka halamannya —
--   tanpa grant, user kena 403/redirect landing. Tidak perlu guard tambahan
--   di controller.
--
--   Grant ditulis BY LINK (JOIN access_path.link), bukan by id node: id berbeda
--   antar environment dan pernah bikin akses "sudah dicentang tapi tetap 403"
--   (jebakan 2026-07-24).
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Section "Keuangan" — dipastikan ada.
--    Di prod node ini sudah dibuat 2026_07_23_finance_menu_restructure.sql,
--    jadi INSERT IGNORE di bawah tidak melakukan apa-apa di sana. Di DB lokal
--    yang belum menjalankan file itu, baris ini yang membuatnya — supaya node
--    Komisi Dokter tidak menggantung tanpa induk.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`) VALUES
  ('fin-parent-000000000000', '', 'Keuangan', 'icon-fa fas fa-wallet', 95, NULL, '', NULL);

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Node menu Komisi Dokter.
--    Ikon memakai nama Font Awesome 5 (`fa-user-md`) — tema ini self-host FA5,
--    nama bergaya FA6 akan render sebagai kotak kosong.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`) VALUES
  ('kd-komisi-dokter-000000', 'fin-parent-000000000000', 'Komisi Dokter', 'icon-fa fas fa-user-md', 5, NULL, 'komisi-dokter', NULL);

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Grant halaman Komisi Dokter.
--    Finance = pengguna utamanya. Executive ikut karena angka fee dokter masuk
--    ke pembacaan biaya. Role cabang TIDAK diberi di v1; kalau nanti perlu,
--    cukup tambahkan namanya di daftar WHERE ini — kode chip cabang-terkunci
--    sudah ada dan berjalan, nol perubahan kode.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('kd-akses-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 22)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.link = 'komisi-dokter'
WHERE gp.nama IN ('Finance', 'Executive', 'Head IT', 'IT',
                  'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (
      SELECT 1 FROM `group_access` gx
      WHERE gx.group_id = gp.id AND gx.access_id = ap.id
  );

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. Grant parent "Keuangan" — tanpa ini node anak TIDAK muncul di sidebar
--    (buildTree hanya menyusun anak di bawah parent yang dimiliki group).
--    No-op untuk role yang sudah punya dari Petty Cash / ROP.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('kd-induk-', SUBSTRING(MD5(CONCAT(gp.id, 'fin-parent-000000000000')), 1, 22)),
       gp.id, 'fin-parent-000000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Finance', 'Executive', 'Head IT', 'IT',
                  'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (
      SELECT 1 FROM `group_access` gx
      WHERE gx.group_id = gp.id AND gx.access_id = 'fin-parent-000000000000'
  );

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. Ringkasan — supaya hasilnya kelihatan langsung di phpMyAdmin / CLI.
-- ─────────────────────────────────────────────────────────────────────────────
SELECT gp.nama AS role_yang_dapat_akses
FROM `group_access` gx
JOIN `group_path` gp  ON gp.id = gx.group_id
JOIN `access_path` ap ON ap.id = gx.access_id
WHERE ap.link = 'komisi-dokter'
ORDER BY gp.nama;
