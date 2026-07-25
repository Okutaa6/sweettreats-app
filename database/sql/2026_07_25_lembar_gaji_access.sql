-- ════════════════════════════════════════════════════════════════════════════
-- Lembar Gaji — node menu + grant (Finance pemilik, IT/admin akses penuh)
-- ════════════════════════════════════════════════════════════════════════════
-- Halaman absensi/lembar-gaji (rekap kehadiran/lembur per karyawan per bulan,
-- dasar hitung gaji manual). ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`.

INSERT IGNORE INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`) VALUES
  ('abs-gaji-0000000000000', 'abs-parent-000000000000', 'Lembar Gaji', 'icon-fa fas fa-file-invoice-dollar', 7, NULL, 'absensi/lembar-gaji', NULL);

INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-gaji-grant-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'abs-gaji-0000000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Finance', 'Head IT', 'IT', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'abs-gaji-0000000000000');

INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-gaji-parent-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'abs-parent-000000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Finance', 'Head IT', 'IT', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'abs-parent-000000000000');

-- Grant segmen 'absensi' BY LINK (jebakan node pembawa link beda antar env).
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-gaji-seg-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 24)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.link = 'absensi'
WHERE gp.nama IN ('Finance', 'Head IT', 'IT', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = ap.id);
