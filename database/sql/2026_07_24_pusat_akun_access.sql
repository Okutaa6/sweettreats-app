-- ════════════════════════════════════════════════════════════════════════════
-- Pusat Akun — Fase A: node "Kesehatan" + grant section ke IT
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks: docs/rencana-pusat-akun.md §3. Dua hal:
--   1. Node baru 'setkesehatan' (halaman detektor kegagalan-senyap) di bawah
--      parent yang sama dengan setusermanagement ("User Access" — rename jadi
--      "Pusat Akun" menyusul di Fase C).
--   2. Grant SELURUH section ke Head IT + IT (keputusan user: IT akses penuh,
--      sekalian eksperimen batas akses role IT).
--
-- Portabel antar-environment: parent & node dicari BY LINK, role BY NAME —
-- id beda antara local/dev/prod, jangan pernah hardcode UUID.
-- Idempotent: INSERT IGNORE dengan id deterministik.
-- ⚠️ WAJIB setelah file ini: php artisan access:rebuild
-- ════════════════════════════════════════════════════════════════════════════

-- 1. Node Kesehatan (urutan 0 = paling atas di section)
INSERT IGNORE INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'pa-kesehatan-000000000', ap.pid, 'Kesehatan', 'dripicons-pulse', 0, NULL, 'setkesehatan', NULL
FROM `access_path` ap WHERE ap.link = 'setusermanagement' LIMIT 1;

-- 2. Grant node Kesehatan ke 4 admin + Head IT + IT
--    Guard NOT EXISTS: INSERT IGNORE id-deterministik TIDAK mencegah dobel
--    kalau grant yang sama sudah ada dengan id UUID (dari UI) — group_access
--    tak punya UNIQUE (group_id, access_id). Guard node: jangan menyisipkan
--    grant menggantung kalau nodenya gagal dibuat.
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('pa-g-keseh-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'pa-kesehatan-000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Super Admin', 'Admin General', 'Admin Backup', 'Admin', 'Head IT', 'IT')
  AND EXISTS (SELECT 1 FROM access_path apx WHERE apx.id = 'pa-kesehatan-000000000')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'pa-kesehatan-000000000');

-- 2b. Rename section "User Access" → "Pusat Akun" (nama tampilan menu; id & link tetap)
UPDATE `access_path` SET `nama` = 'Pusat Akun'
WHERE `nama` = 'User Access'
  AND `id` IN (SELECT x.pid FROM (SELECT pid FROM access_path WHERE link = 'setusermanagement' LIMIT 1) x);

-- 3. Grant section lama (parent User Access + User/Role/Akses/Setting) ke Head IT + IT
--    (admin sudah punya dari dulu)
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('pa-g-', LOWER(REPLACE(gp.nama, ' ', '-')), '-', LEFT(MD5(ap.id), 8)), gp.id, ap.id
FROM `group_path` gp
CROSS JOIN `access_path` ap
WHERE gp.nama IN ('Head IT', 'IT')
  AND (ap.link IN ('setusermanagement', 'setgroup', 'setaccess', 'setsystemprofile')
       OR ap.id IN (SELECT x.pid FROM (SELECT pid FROM access_path WHERE link = 'setusermanagement' LIMIT 1) x))
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = ap.id);

-- 3b. Grant KAKEK (root section, mis. "Data Center") ke Head IT + IT.
--     Tanpa ini seluruh subtree Pusat Akun GUGUR dari pohon menu (rebuild
--     mem-filter node yang parent-nya tak di-grant) — persis kegagalan-senyap
--     "Grant tak berefek". Dicari by-lineage: parent-nya parent setusermanagement.
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('pa-g-root-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, y.gpid
FROM `group_path` gp
CROSS JOIN (
    SELECT p.pid AS gpid FROM access_path p
    WHERE p.id = (SELECT x.pid FROM (SELECT pid FROM access_path WHERE link = 'setusermanagement' LIMIT 1) x)
) y
WHERE gp.nama IN ('Head IT', 'IT') AND y.gpid IS NOT NULL AND y.gpid <> ''
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = y.gpid);
