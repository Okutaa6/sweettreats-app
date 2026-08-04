-- ════════════════════════════════════════════════════════════════════════════
-- Cuti — node menu + grant (Finance pemilik proses, IT akses penuh, admin)
-- ════════════════════════════════════════════════════════════════════════════
-- Halaman absensi/cuti (rekap saldo + cuti bersama). Karyawan TIDAK butuh
-- node — saldo mereka nempel di halaman Akun.
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`.

INSERT IGNORE INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`) VALUES
  ('abs-cuti-0000000000000', 'abs-parent-000000000000', 'Cuti', 'icon-fa fas fa-sun', 6, NULL, 'absensi/cuti', NULL);

INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-cuti-grant-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'abs-cuti-0000000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Finance', 'Head IT', 'IT', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'abs-cuti-0000000000000');

-- Parent Absensi supaya node child muncul di sidebar (NOT EXISTS = no-op
-- untuk role yang sudah punya).
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-cuti-parent-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'abs-parent-000000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Finance', 'Head IT', 'IT', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'abs-parent-000000000000');

-- AuthGuard cek segment-1: role wajib pegang node ber-link 'absensi'.
-- Node pembawanya beda antar environment (jebakan 2026-07-24) → grant BY LINK.
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-cuti-seg-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 24)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.link = 'absensi'
WHERE gp.nama IN ('Finance', 'Head IT', 'IT', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = ap.id);
