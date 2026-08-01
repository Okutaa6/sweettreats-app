-- ════════════════════════════════════════════════════════════════════════════
-- Pengajuan / ROP — node menu + grant akses
-- ════════════════════════════════════════════════════════════════════════════
-- Tiga layar, dua zona:
--   • "Pengajuan"        link `pengajuan`         — PENGAJU (kepala departemen), cokelat
--   • "Antrian Pengajuan" link `finance/rop`      — FINANCE, burgundy
--   • "Rekap Pengeluaran" link `finance/rop/rekap` — Finance + Executive, burgundy
--
-- Semuanya digantung di section "Keuangan" (fin-parent-000000000000) yang sudah
-- dibuat 2026_07_23_finance_menu_restructure.sql — satu tempat dengan Petty Cash,
-- karena dua-duanya sisi "pengeluaran" (paket rilis yang sama).
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`.
--
-- Catatan gerbang (jangan salah kaprah):
--   • Segment `finance` ada di AuthGuard::SKIP_RBAC → dua layar Finance TIDAK
--     dijaga RBAC menu; gerbang sebenarnya canSeeAllBranches() di controller.
--     Node menunya murni supaya muncul di sidebar orang yang berhak.
--   • Segment `pengajuan` TIDAK di-skip → node ber-link `pengajuan` inilah
--     yang menentukan siapa boleh membuka halaman pengaju. Tanpa node ini
--     di-grant, role ybs kena 403/redirect landing.
--   • Grant ditulis BY LINK (JOIN access_path.link), bukan by id node — id
--     berbeda antar environment dan pernah bikin akses "sudah dicentang tapi
--     tetap 403" (jebakan 2026-07-24).

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Node menu
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`) VALUES
  ('rop-pengajuan-00000000', 'fin-parent-000000000000', 'Pengajuan',         'icon-fa fas fa-file-invoice-dollar', 2, NULL, 'pengajuan',        NULL),
  ('rop-antrian-0000000000', 'fin-parent-000000000000', 'Antrian Pengajuan', 'icon-fa fas fa-inbox',               3, NULL, 'finance/rop',      NULL),
  ('rop-rekap-000000000000', 'fin-parent-000000000000', 'Rekap Pengeluaran', 'icon-fa fas fa-chart-pie',           4, NULL, 'finance/rop/rekap', NULL);

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Grant "Pengajuan" — kepala departemen yang boleh mengajukan.
--    Karyawan biasa TIDAK dapat: mereka titip ke atasannya (rencana §2).
--    Finance ikut dapat karena Finance juga mengajukan kebutuhannya sendiri.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('rop-aju-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 24)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.link = 'pengajuan'
WHERE gp.nama IN ('Supervisor', 'Coordinator Dentist', 'Head Marketing', 'Head IT', 'IT',
                  'Finance', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = ap.id);

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Grant "Antrian Pengajuan" — yang meng-ACC & membayar.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('rop-antri-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 22)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.link = 'finance/rop'
WHERE gp.nama IN ('Finance', 'Head IT', 'IT', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = ap.id);

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. Grant "Rekap Pengeluaran" — Finance + Executive (Executive read-only,
--    gerbangnya di RopRekapController::guard).
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('rop-rekap-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 22)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.link = 'finance/rop/rekap'
WHERE gp.nama IN ('Finance', 'Executive', 'Head IT', 'IT', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = ap.id);

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. Parent "Keuangan" — tanpa ini, node anak tidak muncul di sidebar
--    (buildTree hanya menyusun anak di bawah parent yang dimiliki group).
--    NOT EXISTS = no-op untuk role yang sudah punya (mis. Finance dari Petty Cash).
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('rop-parent-', SUBSTRING(MD5(CONCAT(gp.id, 'fin-parent-000000000000')), 1, 21)), gp.id, 'fin-parent-000000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Supervisor', 'Coordinator Dentist', 'Head Marketing', 'Head IT', 'IT',
                  'Finance', 'Executive', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'fin-parent-000000000000');
