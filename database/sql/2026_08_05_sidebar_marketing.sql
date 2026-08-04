-- ════════════════════════════════════════════════════════════════════════════
-- Sidebar Marketing ditata 3 kelompok — batch rapi-rapi pasca-launch, item 4
-- ════════════════════════════════════════════════════════════════════════════
-- ACC Ekky 2026-08-05. Struktur lama tersebar: parent "Marketing" (sub
-- Dashboard / Referral / Editorial Plan / KOL & KOC Dashboard) + sebagian
-- halaman marketing nyangkut di "Data Center" (Affiliate, Keyword, Report
-- Referral). Ditata jadi TIGA section sidebar:
--
--   1. Dashboard & Laporan : Dashboard Marketing · Report Referral · Klaim Komisi
--   2. Konten              : Konten Pillar · EP Konten · Breakdown Content ·
--                            Campaign Framework · Review Konten · Keyword
--   3. Referral & Member   : Kode Referral · Affiliate · KOL · KOC · Sweet Mates
--
-- ("Promo" sengaja TIDAK ikut — tetap di Data Center, di luar keputusan ini.)
--
-- CARA KERJA (aman antar environment — id node BEDA antar env):
--   • Perpindahan anak dilakukan BY LINK (UPDATE pid), bukan by id.
--   • Parent section baru dibuat dgn id tetap mkt-sec-*; guard NOT EXISTS.
--   • Slot urutan: SEKALI di awal (hanya bila mkt-sec-dashlap belum ada),
--     semua parent level-0 dikali 10 → muncul celah; tiga section baru masuk
--     di urutan Marketing+1..+3, urutan relatif parent lain tak berubah.
--   • Node kol/koc DIBUAT hanya kalau env-nya belum punya (pola
--     2026_07_23_menu_sweet_mates.sql — di prod node itu sudah ada ber-UUID).
--   • Grant parent baru = semua role yang memegang minimal satu anak-nya.
--   • Typo "Refferal" → "Referral" dibereskan via REPLACE (idempoten).
--
-- Container lama yang jadi kosong (sub Dashboard/Referral/Editorial Plan/
-- KOL & KOC Dashboard, bahkan parent "Marketing" bila tak bersisa) otomatis
-- hilang dari sidebar — shellMenuSections melewati section tanpa leaf.
-- Node containernya SENGAJA dibiarkan (cruft tak tampil; hapus = urusan lain).
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`.
--
-- SELURUH ISI DIBUNGKUS TRANSAKSI (semua statement DML, tabelnya InnoDB).
-- Alasan: langkah 0 mengalikan urutan parent ×10 sedangkan penanda idempotensi
-- (mkt-sec-dashlap-000000) baru lahir di langkah 1 — kalau eksekusi putus di
-- antara keduanya lalu diulang, urutan jadi ×100. Dengan transaksi, gagal di
-- tengah = tak ada perubahan sama sekali, jadi mengulang selalu aman.

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────────────────────
-- 0. Buka celah urutan parent level-0 — HANYA di run pertama (marker: belum
--    ada mkt-sec-dashlap-000000).
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `access_path` ap
LEFT JOIN `access_path` marker ON marker.`id` = 'mkt-sec-dashlap-000000'
SET ap.`urutan` = ap.`urutan` * 10
WHERE (ap.`pid` IS NULL OR ap.`pid` = '') AND marker.`id` IS NULL;

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Tiga parent section baru, diposisikan tepat setelah parent "Marketing"
--    lama (urutan-nya sudah ×10, jadi +1..+3 tak menabrak parent berikutnya).
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'mkt-sec-dashlap-000000', '', 'Dashboard & Laporan', 'fas fa-chart-line', m.`urutan` + 1, NULL, '', NULL
FROM `access_path` m
WHERE m.`nama` = 'Marketing' AND (m.`pid` IS NULL OR m.`pid` = '')
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `access_path`) x WHERE x.`id` = 'mkt-sec-dashlap-000000')
LIMIT 1;

INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'mkt-sec-konten-0000000', '', 'Konten', 'fas fa-pen-nib', m.`urutan` + 2, NULL, '', NULL
FROM `access_path` m
WHERE m.`nama` = 'Marketing' AND (m.`pid` IS NULL OR m.`pid` = '')
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `access_path`) x WHERE x.`id` = 'mkt-sec-konten-0000000')
LIMIT 1;

INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'mkt-sec-refmem-0000000', '', 'Referral & Member', 'fas fa-user-friends', m.`urutan` + 3, NULL, '', NULL
FROM `access_path` m
WHERE m.`nama` = 'Marketing' AND (m.`pid` IS NULL OR m.`pid` = '')
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `access_path`) x WHERE x.`id` = 'mkt-sec-refmem-0000000')
LIMIT 1;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Node kol/koc — dibuat HANYA kalau env belum punya node ber-link itu
--    (prod sudah punya versi UUID; di sana blok ini no-op).
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'mkt-kol-00000000000000', p.`id`, 'KOL', 'fas fa-star', 3, NULL, 'kol', CONCAT(p.`id`, ',', 'mkt-kol-00000000000000')
FROM `access_path` p
WHERE p.`id` = 'mkt-sec-refmem-0000000'
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `access_path`) x WHERE x.`link` = 'kol')
LIMIT 1;

INSERT INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`)
SELECT 'mkt-koc-00000000000000', p.`id`, 'KOC', 'fas fa-star-half-alt', 4, NULL, 'koc', CONCAT(p.`id`, ',', 'mkt-koc-00000000000000')
FROM `access_path` p
WHERE p.`id` = 'mkt-sec-refmem-0000000'
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `access_path`) x WHERE x.`link` = 'koc')
LIMIT 1;

-- Grant kol/koc — HANYA untuk node buatan file ini (id tetap); node UUID
-- bawaan server tak disentuh. Role mengikuti pola sweet-mates.
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('mkt-kol-', SUBSTRING(MD5(CONCAT(g.`id`, 'mkt-kol')), 1, 24)), g.`id`, 'mkt-kol-00000000000000'
FROM `group_path` g
WHERE g.`nama` IN ('Super Admin', 'Admin General', 'Admin', 'Head Marketing', 'Content Creator')
  AND EXISTS (SELECT 1 FROM (SELECT * FROM `access_path`) z WHERE z.`id` = 'mkt-kol-00000000000000')
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `group_access`) w
                  WHERE w.`group_id` = g.`id` AND w.`access_id` = 'mkt-kol-00000000000000');

INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('mkt-koc-', SUBSTRING(MD5(CONCAT(g.`id`, 'mkt-koc')), 1, 24)), g.`id`, 'mkt-koc-00000000000000'
FROM `group_path` g
WHERE g.`nama` IN ('Super Admin', 'Admin General', 'Admin', 'Head Marketing', 'Content Creator')
  AND EXISTS (SELECT 1 FROM (SELECT * FROM `access_path`) z WHERE z.`id` = 'mkt-koc-00000000000000')
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `group_access`) w
                  WHERE w.`group_id` = g.`id` AND w.`access_id` = 'mkt-koc-00000000000000');

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Pindahkan halaman ke section barunya — BY LINK, id apa pun node-nya.
--    urutan = posisi dalam kelompok sesuai keputusan.
-- ─────────────────────────────────────────────────────────────────────────────
-- Kelompok 1: Dashboard & Laporan
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-dashlap-000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 1, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'marketing-dashboard';
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-dashlap-000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 2, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'report-referral';
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-dashlap-000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 3, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'commission-claim';

-- Kelompok 2: Konten
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-konten-0000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 1, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'konten-pillar';
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-konten-0000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 2, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'ep-konten';
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-konten-0000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 3, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'ep-breakdown';
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-konten-0000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 4, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'ep-campaign';
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-konten-0000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 5, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'content-review';
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-konten-0000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 6, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'rawkeyword';

-- Kelompok 3: Referral & Member
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-refmem-0000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 1, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'rawpromo';
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-refmem-0000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 2, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'rawaffiliate';
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-refmem-0000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 3, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'kol';
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-refmem-0000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 4, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'koc';
UPDATE `access_path` ap JOIN `access_path` np ON np.`id` = 'mkt-sec-refmem-0000000'
SET ap.`pid` = np.`id`, ap.`urutan` = 5, ap.`pid_path` = CONCAT(np.`id`, ',', ap.`id`)
WHERE ap.`link` = 'sweet-mates';

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. Fix typo "Refferal" → "Referral" di nama node mana pun (data dev/prod;
--    lokal bersih — no-op).
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `access_path` SET `nama` = REPLACE(`nama`, 'Refferal', 'Referral') WHERE `nama` LIKE '%Refferal%';

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. Grant parent baru — semua role yang memegang minimal SATU anak di
--    kelompoknya (tanpa parent, buildTree tak menampilkan anak).
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT DISTINCT CONCAT('mkt-s1-', SUBSTRING(MD5(ga.`group_id`), 1, 25)), ga.`group_id`, 'mkt-sec-dashlap-000000'
FROM `group_access` ga
JOIN `access_path` ap ON ap.`id` = ga.`access_id`
WHERE ap.`link` IN ('marketing-dashboard', 'report-referral', 'commission-claim')
  AND EXISTS (SELECT 1 FROM (SELECT * FROM `access_path`) x WHERE x.`id` = 'mkt-sec-dashlap-000000')
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `group_access`) w
                  WHERE w.`group_id` = ga.`group_id` AND w.`access_id` = 'mkt-sec-dashlap-000000');

INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT DISTINCT CONCAT('mkt-s2-', SUBSTRING(MD5(ga.`group_id`), 1, 25)), ga.`group_id`, 'mkt-sec-konten-0000000'
FROM `group_access` ga
JOIN `access_path` ap ON ap.`id` = ga.`access_id`
WHERE ap.`link` IN ('konten-pillar', 'ep-konten', 'ep-breakdown', 'ep-campaign', 'content-review', 'rawkeyword')
  AND EXISTS (SELECT 1 FROM (SELECT * FROM `access_path`) x WHERE x.`id` = 'mkt-sec-konten-0000000')
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `group_access`) w
                  WHERE w.`group_id` = ga.`group_id` AND w.`access_id` = 'mkt-sec-konten-0000000');

INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT DISTINCT CONCAT('mkt-s3-', SUBSTRING(MD5(ga.`group_id`), 1, 25)), ga.`group_id`, 'mkt-sec-refmem-0000000'
FROM `group_access` ga
JOIN `access_path` ap ON ap.`id` = ga.`access_id`
WHERE ap.`link` IN ('rawpromo', 'rawaffiliate', 'kol', 'koc', 'sweet-mates')
  AND EXISTS (SELECT 1 FROM (SELECT * FROM `access_path`) x WHERE x.`id` = 'mkt-sec-refmem-0000000')
  AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `group_access`) w
                  WHERE w.`group_id` = ga.`group_id` AND w.`access_id` = 'mkt-sec-refmem-0000000');

COMMIT;

-- ============================================================================
-- VERIFIKASI setelah jalan (JANGAN DILEWATI):
--   1. Tiap link tepat SATU node (kalau 2 = dobel, kelihatan dua kali di menu):
--      SELECT link, COUNT(*) n FROM access_path
--      WHERE link IN ('marketing-dashboard','report-referral','commission-claim',
--                     'konten-pillar','ep-konten','ep-breakdown','ep-campaign',
--                     'content-review','rawkeyword','rawpromo','rawaffiliate',
--                     'kol','koc','sweet-mates')
--      GROUP BY link HAVING n <> 1;
--      → harus 0 baris.
--   2. Tak ada lagi typo: SELECT id, nama FROM access_path WHERE nama LIKE '%Refferal%';
--   3. php artisan access:rebuild  → cek sidebar Head Marketing: 3 section.
-- ============================================================================
