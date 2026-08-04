-- ════════════════════════════════════════════════════════════════════════════
-- Menu personal untuk ROLE LAMA — Absensi Saya + Inbox Persetujuan
-- ════════════════════════════════════════════════════════════════════════════
-- Ditemukan 2026-08-05 dari keluhan nyata: drop-up akun Yovanka (Head
-- Marketing) hanya berisi "Ganti kata sandi" dan "Keluar" — tak ada Absensi
-- Saya, jadi ia tak bisa absen sama sekali.
--
-- AKAR MASALAH (bukan akibat batch rapi-rapi — dibuktikan dengan membandingkan
-- backup pra-deploy: nol grant hilang, 376 → 397 baris murni bertambah):
-- saat gerbong naik prod 4 Agu, node menu + grant disalin dev→prod HANYA untuk
-- 8 role BARU. Role LAMA yang sudah ada di prod sejak dulu — Executive, Head
-- Marketing, Content Creator, Creative — tak pernah kebagian node personal.
-- Ini kelas kegagalan yang sama dengan insiden 15 node: grant tak bisa
-- menempel pada node yang tak ada, dan gagalnya DIAM.
--
-- DAMPAK YANG DIPERBAIKI DI SINI:
--   1. 6 orang nyata tak bisa absen: Yovanka (Head Marketing), Talitha
--      (Content Creator), Alif (Creative), Dani/Hari/Robi (Executive).
--   2. LEBIH GAWAT — Head Marketing & Executive adalah PENYETUJU di
--      att_approval_rule, tapi tak punya menu Inbox Persetujuan → /persetujuan
--      ditolak AuthGuard. Sejak persetujuan jadi satu lapis (2026-08-05),
--      Executive adalah satu-satunya penyetuju bagi para head/SPV, dan Head
--      Marketing satu-satunya bagi Content Creator & Creative. Tanpa inbox,
--      pengajuan pertama akan MENGGANTUNG tanpa gejala. Belum ada korban
--      hanya karena att_request prod masih kosong saat ini.
--
-- YANG SENGAJA TIDAK DIBERI (jangan "dilengkapi" tanpa keputusan):
--   • `akun` untuk role akun-peran bersama — Sales (6 akun cabang), Admin,
--     Admin General, Admin Backup, Super Admin, Branch Executive. Itu akun
--     PERAN, bukan orang; memberi mereka absen = data kehadiran palsu.
--     Orang-orangnya absen dari akun pribadi masing-masing.
--   • `persetujuan` untuk Content Creator & Creative — mereka bukan penyetuju
--     di att_approval_rule, jadi inboxnya dijamin selalu kosong. Dev memberi
--     mereka grant ini; prod sengaja BEDA supaya menu tak berisi pintu kosong.
--     (Kalau kelak mereka jadi penyetuju, tambahkan di sini.)
--
-- Grant BY LINK, bukan by id node — id node beda antar environment.
-- Parent ikut di-grant: tanpa parent, buildTree tidak menampilkan anaknya
-- (pelajaran gerbong 4 Agu). NOT EXISTS di mana-mana → idempoten.
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`, lalu pengguna
--    logout-login sekali.

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. "Absensi Saya" (link `akun`) — 4 role berisi orang nyata
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('pm-akun-', SUBSTRING(MD5(CONCAT(g.`id`, ap.`id`)), 1, 22)), g.`id`, ap.`id`
FROM `group_path` g
JOIN `access_path` ap ON ap.`link` = 'akun'
WHERE g.`nama` IN ('Executive', 'Head Marketing', 'Content Creator', 'Creative')
  AND NOT EXISTS (SELECT 1 FROM `group_access` w
                  WHERE w.`group_id` = g.`id` AND w.`access_id` = ap.`id`);

-- Parent-nya ("Akun Saya") — tanpa ini anaknya tak muncul.
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('pm-akunp-', SUBSTRING(MD5(CONCAT(g.`id`, p.`id`)), 1, 21)), g.`id`, p.`id`
FROM `group_path` g
JOIN `access_path` ap ON ap.`link` = 'akun'
JOIN `access_path` p ON p.`id` = ap.`pid`
WHERE g.`nama` IN ('Executive', 'Head Marketing', 'Content Creator', 'Creative')
  AND NOT EXISTS (SELECT 1 FROM `group_access` w
                  WHERE w.`group_id` = g.`id` AND w.`access_id` = p.`id`);

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. "Inbox Persetujuan" (link `persetujuan`) — HANYA role yang benar-benar
--    muncul sebagai approver_role_id di att_approval_rule
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('pm-pers-', SUBSTRING(MD5(CONCAT(g.`id`, ap.`id`)), 1, 22)), g.`id`, ap.`id`
FROM `group_path` g
JOIN `access_path` ap ON ap.`link` = 'persetujuan'
WHERE g.`nama` IN ('Executive', 'Head Marketing')
  AND NOT EXISTS (SELECT 1 FROM `group_access` w
                  WHERE w.`group_id` = g.`id` AND w.`access_id` = ap.`id`);

INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('pm-persp-', SUBSTRING(MD5(CONCAT(g.`id`, p.`id`)), 1, 21)), g.`id`, p.`id`
FROM `group_path` g
JOIN `access_path` ap ON ap.`link` = 'persetujuan'
JOIN `access_path` p ON p.`id` = ap.`pid`
WHERE g.`nama` IN ('Executive', 'Head Marketing')
  AND NOT EXISTS (SELECT 1 FROM `group_access` w
                  WHERE w.`group_id` = g.`id` AND w.`access_id` = p.`id`);

COMMIT;

-- ============================================================================
-- VERIFIKASI setelah jalan (JANGAN DILEWATI):
--   1. Tiap role penyetuju punya inbox — harus 0 baris:
--      SELECT DISTINCT g.nama FROM att_approval_rule r
--        JOIN group_path g ON g.id COLLATE utf8mb4_unicode_ci
--                           = r.approver_role_id COLLATE utf8mb4_unicode_ci
--       WHERE (SELECT COUNT(*) FROM users u WHERE u.group_id=g.id AND u.status=1) > 0
--         AND NOT EXISTS (SELECT 1 FROM group_access x JOIN access_path p
--                           ON p.id=x.access_id
--                        WHERE x.group_id=g.id AND p.link='persetujuan');
--   2. Orang nyata bisa absen: cek 4 role di atas punya link `akun`.
--   3. php artisan access:rebuild → Yovanka logout-login → drop-up akun
--      berisi "Absensi Saya" + "Persetujuan".
-- ============================================================================
