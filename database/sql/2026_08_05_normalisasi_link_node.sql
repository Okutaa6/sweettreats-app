-- ════════════════════════════════════════════════════════════════════════════
-- Normalisasi link node menu — buang garis miring di depan
-- ════════════════════════════════════════════════════════════════════════════
-- Temuan audit 2026-08-05 (dev; PROD SUDAH BERSIH, di sana file ini no-op).
--
-- MASALAHNYA: tiga node menu di dev menyimpan link berawalan '/':
--   /report/executive · /my-checklist · /my-checklist/history
-- Gerbang halaman mencocokkan link TANPA garis miring, jadi awalan itu membuat
-- pencocokan meleset. Akibat nyata yang terbukti di dev:
--   1. Executive/Admin/Super Admin kena 403 di Executive Report padahal
--      menunya tampil dan grant-nya ada (hasMenuAccess('report/executive')
--      tidak cocok dengan node ber-link '/report/executive').
--   2. "Checklist Saya" milik General Services & Assistant Dentist nyasar ke
--      MENU UTAMA, bukan ke drop-up akun — karena peta menu personal
--      mencocokkan 'my-checklist' persis. Dua peran itu jadi beda sendiri
--      dari FO/Asdent tanpa alasan.
--
-- CARA MEMPERBAIKI BERBEDA PER NODE — sengaja, bukan satu UPDATE borongan:
--   • '/report/executive'      → tak ada node bersih padanannya → cukup
--                                dibuang garis miringnya.
--   • '/my-checklist'          → node bersih SUDAH ADA. Kalau ini ikut
--                                di-strip, hasilnya DUA node ber-link sama
--                                ("node menu dobel" — mencabut satu tak
--                                mencabut aksesnya). Jadi: grant-nya
--                                dipindahkan ke node bersih, node lamanya
--                                dibuang.
--   • '/my-checklist/history'  → tak ada node bersih, routenya ADA → cukup
--                                dibuang garis miringnya (bukan sampah).
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`.
-- Idempoten: dijalankan ulang tak ada yang tersentuh (tak ada lagi link ber-'/').

START TRANSACTION;

-- ── 1. Pindahkan grant '/my-checklist' → node 'my-checklist' yang bersih ──
--    NOT EXISTS menjaga supaya peran yang sudah punya node bersih tak dobel.
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('mcl-', SUBSTRING(MD5(CONCAT(ga.`group_id`, bersih.`id`)), 1, 26)), ga.`group_id`, bersih.`id`
FROM `group_access` ga
JOIN `access_path` miring ON miring.`id` = ga.`access_id` AND miring.`link` = '/my-checklist'
JOIN `access_path` bersih ON bersih.`link` = 'my-checklist'
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `group_access`) w
    WHERE w.`group_id` = ga.`group_id` AND w.`access_id` = bersih.`id`
);

-- ── 2. Buang grant + node '/my-checklist' (sudah digantikan node bersih) ──
DELETE ga FROM `group_access` ga
JOIN `access_path` ap ON ap.`id` = ga.`access_id`
WHERE ap.`link` = '/my-checklist';

DELETE FROM `access_path` WHERE `link` = '/my-checklist';

-- ── 3. Dua sisanya: cukup buang garis miring di depan ──
UPDATE `access_path`
SET `link` = TRIM(LEADING '/' FROM `link`)
WHERE `link` IN ('/report/executive', '/my-checklist/history');

COMMIT;

-- ============================================================================
-- VERIFIKASI:
--   SELECT id, nama, link FROM access_path WHERE link LIKE '/%';   → 0 baris
--   SELECT link, COUNT(*) n FROM access_path
--     WHERE link IN ('my-checklist','my-checklist/history','report/executive')
--     GROUP BY link;                                               → semua 1
--   lalu: php artisan access:rebuild
--   Uji: Executive buka /report/executive → 200 (bukan 403);
--        General Services → "Checklist Saya" ada di DROP-UP, bukan menu utama.
-- ============================================================================
