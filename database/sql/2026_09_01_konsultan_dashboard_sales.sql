-- ════════════════════════════════════════════════════════════════════════════
-- Dashboard Sales untuk Konsultan Marketing & Konsultan Finance — 2026-09-01
-- IDEMPOTENT: aman dijalankan berapa kali pun.
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`.
-- ════════════════════════════════════════════════════════════════════════════
-- Permintaan Ekky: kedua konsultan diberi "sales report seperti yang Sales
-- punya" + Executive Report.
--
-- Diperiksa dulu, dan sebagian besar TIDAK PERLU dikerjakan:
--   · `report` (Laporan Penjualan)  — SUDAH dimiliki keduanya
--   · `report/executive` (Executive) — SUDAH dimiliki keduanya
--   · yang benar-benar kurang cuma `sales-dashboard` (Dashboard Sales)
--
-- SENGAJA TIDAK diberikan, walau role Sales punya: `rawdoctor`, `rawcustomer`,
-- `pettycash`, `rawreservation`. Itu data operasional pasien/dokter/uang, bukan
-- laporan — di luar yang diminta, dan konsultan itu PENGAMAT.
--
-- Grant ditulis BY LINK + lewat relasi induk (`ap.pid`), BUKAN id node yang
-- di-hardcode: id berbeda antar environment dan pernah bikin akses "sudah
-- dicentang tapi tetap 403" (jebakan 2026-07-24).
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Node Dashboard Sales itu sendiri.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('ksd-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 28)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.link = 'sales-dashboard'
WHERE gp.nama IN ('Konsultan Marketing', 'Konsultan Finance')
  AND NOT EXISTS (
      SELECT 1 FROM `group_access` gx
      WHERE gx.group_id = gp.id AND gx.access_id = ap.id
  );

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Induknya ("Dashboard") — TANPA INI NODE ANAK TIDAK MUNCUL DI SIDEBAR.
--    buildTree hanya menyusun anak di bawah parent yang dimiliki group.
--    Induk dicari lewat ap.pid, bukan lewat nama "Dashboard" yang generik dan
--    bisa cocok ke node lain.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('ksdp-', SUBSTRING(MD5(CONCAT(gp.id, par.id)), 1, 27)), gp.id, par.id
FROM `group_path` gp
JOIN `access_path` ap  ON ap.link = 'sales-dashboard'
JOIN `access_path` par ON par.id = ap.pid
WHERE gp.nama IN ('Konsultan Marketing', 'Konsultan Finance')
  AND NOT EXISTS (
      SELECT 1 FROM `group_access` gx
      WHERE gx.group_id = gp.id AND gx.access_id = par.id
  );

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Bukti — bukan janji. Harus 2 baris, masing-masing 2 node (anak + induk).
-- ─────────────────────────────────────────────────────────────────────────────
SELECT gp.nama                                            AS role,
       SUM(ap.link = 'sales-dashboard')                   AS dashboard_sales,
       SUM(ap.link IS NULL OR ap.link = '')               AS node_induk,
       COUNT(*)                                           AS total_dicek
FROM `group_path` gp
JOIN `group_access` ga ON ga.group_id = gp.id
JOIN `access_path` ap  ON ap.id = ga.access_id
WHERE gp.nama IN ('Konsultan Marketing', 'Konsultan Finance')
  AND (ap.link = 'sales-dashboard'
       OR ap.id = (SELECT pid FROM `access_path` WHERE link = 'sales-dashboard' LIMIT 1))
GROUP BY gp.nama;
