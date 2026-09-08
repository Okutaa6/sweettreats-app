-- ════════════════════════════════════════════════════════════════════════════
-- IT Request Tracker — AKSES SEMENTARA untuk peluncuran senyap — 2026-09-09
-- IDEMPOTEN: aman dijalankan berapa kali pun. Dijalankan di DEV dan PROD.
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild` (+ cache:clear) —
--    menu & gerbang RBAC dibaca dari berkas storage/app/group_access/*.txt,
--    bukan dari tabel ini secara langsung (jebakan 2026-06-30: 403/302 basi).
--
-- KENAPA (keputusan Ekky 9 Sep 2026): tracker naik prod DIAM-DIAM dulu —
-- dicapai dari drop-up profil, dipakai IT + Executive + Konsultan (+ admin)
-- untuk membiasakan diri sebelum diperkenalkan ke kepala departemen.
-- SQL 2026_09_02 memberi grant ke Finance (Della), Head Marketing (Yovan), dan
-- Admin juga; file ini MENCABUT tiga itu. SQL lama SENGAJA tak diubah:
-- MENGEMBALIKAN akses = jalankan ulang 2026_09_02_it_tracker_access.sql
-- (INSERT IGNORE + NOT EXISTS, idempoten) lalu access:rebuild.
--
-- Yang TERSISA sesudah file ini: Head IT · IT · Executive · Consultant ·
-- Konsultan IT · Konsultan Finance · Konsultan Marketing · Super Admin ·
-- Admin General · Admin Backup.
--
-- Yang dicabut: node halaman (link 'it-tracker') DAN induknya
-- ('it-parent-0000000000000'). Induk ikut dicabut supaya seksi "IT" tak
-- menyisakan section kosong di sidebar role-role itu. Tanpa grant halaman,
-- AuthGuard MEMANTULKAN ke landing (302), bukan 403 — itulah tanda "tak ada
-- pintu", dan memang itu yang dimaksud senyap.
-- ════════════════════════════════════════════════════════════════════════════

-- 1. cabut grant halaman it-tracker
DELETE ga FROM `group_access` ga
JOIN `group_path`  gp ON gp.id = ga.group_id
JOIN `access_path` ap ON ap.id = ga.access_id
WHERE ap.link = 'it-tracker'
  AND gp.nama IN ('Finance', 'Head Marketing', 'Admin');

-- 2. cabut grant induk "IT" (section root) untuk role yang sama
DELETE ga FROM `group_access` ga
JOIN `group_path` gp ON gp.id = ga.group_id
WHERE ga.access_id = 'it-parent-0000000000000'
  AND gp.nama IN ('Finance', 'Head Marketing', 'Admin');

-- 3. ringkasan — siapa yang MASIH memegang pintunya (harus 10 group)
SELECT gp.nama AS grup_dengan_akses_it_tracker
FROM `group_access` ga
JOIN `group_path`  gp ON gp.id = ga.group_id
JOIN `access_path` ap ON ap.id = ga.access_id
WHERE ap.link = 'it-tracker'
ORDER BY gp.nama;

SELECT 'Akses sementara diterapkan. WAJIB: php artisan access:rebuild && php artisan cache:clear' AS info;
