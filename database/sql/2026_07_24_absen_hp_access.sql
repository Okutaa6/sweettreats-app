-- ════════════════════════════════════════════════════════════════════════════
-- Absen HP — node akses "Pengawasan Absen" + grant
-- ════════════════════════════════════════════════════════════════════════════
-- Layar review SPV (docs/rencana-absen-hp.md §7 layar 2). Pola sama dengan
-- node Persetujuan (pers-parent/pers-inbox): parent tanpa link + child ber-link
-- 'pengawasan' (= segment-1 route, syarat AuthGuard).
--
-- Grant: Supervisor (Anisa, reviewer utama) + Finance (perlu lihat flag yang
-- ngeblok tutup periode) + role admin. Role dicari BY NAME dari group_path —
-- id role beda antar environment (local/dev/prod), jangan pernah hardcode.
--
-- Idempotent: INSERT IGNORE (id deterministik awas-*; grant pakai id gabungan).
-- ⚠️ SETELAH file ini: WAJIB `php artisan access:rebuild` — grant tanpa rebuild
--    = menu tak muncul (kegagalan-senyap #3 di katalog).
-- ════════════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`) VALUES
  ('awas-parent-0000000000', '',                       'Pengawasan',       'icon-fa fas fa-eye', 93, NULL, '',           NULL),
  ('awas-utama-00000000000', 'awas-parent-0000000000', 'Pengawasan Absen', 'dripicons-camera',    1, NULL, 'pengawasan', NULL);

-- Grant per-role BY NAME (id grant deterministik: awas-grant-<node>-<slug role>)
-- Guard NOT EXISTS: group_access TAK punya UNIQUE (group_id, access_id) —
-- INSERT IGNORE saja tidak mencegah dobel kalau grant sudah ada dengan id lain.
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('awas-grant-p-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'awas-parent-0000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Supervisor', 'Finance', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'awas-parent-0000000000');

INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('awas-grant-c-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'awas-utama-00000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Supervisor', 'Finance', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'awas-utama-00000000000');

-- ── Node menu "WiFi Cabang" di bawah parent Absensi — tanpa ini halamannya
--    yatim (cuma bisa diketik manual = pola kegagalan-senyap menu). Grant
--    mengikuti guard controller-nya: IT (Head IT + IT) + admin — kelola IP =
--    kerjaan infrastruktur, BUKAN Finance (keputusan user 2026-07-24).
--    IT juga butuh parent Absensi supaya node child muncul di sidebar.
INSERT IGNORE INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`) VALUES
  ('abs-wifi-0000000000000', 'abs-parent-000000000000', 'WiFi Cabang', 'dripicons-wifi', 5, NULL, 'absensi/wifi', NULL);

INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-wifi-grant-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'abs-wifi-0000000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Head IT', 'IT', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'abs-wifi-0000000000000');

INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-wifi-parent-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'abs-parent-000000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Head IT', 'IT')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'abs-parent-000000000000');

-- ── AuthGuard meloloskan /absensi/* HANYA kalau role pegang node ber-link
--    'absensi' (pembanding segment-1). Node pembawa link itu BEDA antar
--    environment (lokal: abs-parent; dev: node lama "Upload Data") — jadi
--    grant BY LINK, bukan by id, supaya kebal drift. (Kena di dev 2026-07-24:
--    Head IT punya abs-parent & abs-wifi tapi tetap 302 di /absensi/wifi.)
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-seg-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 24)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.link = 'absensi'
WHERE gp.nama IN ('Head IT', 'IT')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = ap.id);

-- Pencabutan grant Finance (kalau sempat terpasang dari versi awal file ini)
DELETE FROM `group_access` WHERE `id` = 'abs-wifi-grant-finance';
