-- ════════════════════════════════════════════════════════════════════════════
-- Akses yang di-ACC tapi TAK PERNAH MUNCUL — induk level-0 "Data Center"
-- ════════════════════════════════════════════════════════════════════════════
-- Ditemukan 2026-08-05 dari pertanyaan Ekky: "sidebar Anisa belum diperbarui?
-- bukannya batch rapi-rapi kasih akses page tambahan ke Nisa?"
--
-- Ternyata grant-nya SUDAH dipasang 5 Agu — dan tak pernah terlihat.
-- Sebabnya: menu dirender sebagai POHON. Daun yang di-grant tidak muncul
-- kalau salah satu LELUHURNYA tidak ikut di-grant. Untuk kedua peran ini,
-- induk perantaranya sudah benar ("Reservation Data Center", "Report",
-- "Dashboard"), tetapi akar level-0 **Data Center** tidak pernah di-grant →
-- seluruh cabangnya tak pernah dirender. Gagal DIAM: di tabel grant terlihat
-- lengkap, di layar tak ada apa-apa.
--
-- Ini kejadian KETIGA dari keluarga yang sama dalam dua hari (insiden 15 node
-- saat gerbong, menu personal role lama, dan ini). Deteksinya murah — query
-- "daun di-grant tapi leluhur tidak" ada di bagian VERIFIKASI di bawah;
-- jalankan itu setiap kali selesai memberi akses.
--
-- YANG DIPERBAIKI:
--   • Supervisor (Anisa)          → Pasien, Doctor, Reservation,
--                                    Laporan Penjualan, Dashboard Sales
--   • Coordinator Dentist (Irma)  → Doctor, Reservation
--   (dua-duanya keputusan Ekky 2026-08-05 yang selama ini tak berefek)
--
-- TIDAK ada halaman baru yang terbuka di luar itu: pohon hanya menampilkan
-- node yang memang sudah di-grant per peran. MetricaSetting tetap TIDAK
-- dipegang Supervisor (sesuai keputusan "paket sales TANPA MetricaSetting").
--
-- CATATAN DUPLIKAT (sengaja dibiarkan, bukan kelalaian): `rawreservation`
-- punya 4 node dan `report` punya 2 node di prod (cruft lama). Kedua peran
-- memegang beberapa di antaranya, TAPI induk node-node kembar itu ("Menu",
-- "Schedule", "Laporan") tidak di-grant, jadi yang tampil tetap TEPAT SATU
-- per halaman. Membersihkan node kembarnya urusan terpisah.
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`, lalu logout-login.

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Akar "Data Center" untuk Supervisor & Coordinator Dentist
--    Resolve BY NAMA level-0 (id node beda antar environment).
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('dcroot-', SUBSTRING(MD5(CONCAT(g.`id`, ap.`id`)), 1, 24)), g.`id`, ap.`id`
FROM `group_path` g
JOIN `access_path` ap ON ap.`nama` = 'Data Center' AND (ap.`pid` IS NULL OR ap.`pid` = '')
WHERE g.`nama` IN ('Supervisor', 'Coordinator Dentist')
  AND NOT EXISTS (SELECT 1 FROM `group_access` w
                  WHERE w.`group_id` = g.`id` AND w.`access_id` = ap.`id`);

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Cabut "Promo (Marketing)" dari Content Creator — keputusan Ekky 2026-08-05.
--    (Talitha satu-satunya penghuni role ini.) Grant-nya toh selama ini juga
--    tak pernah tampil karena induknya tak di-grant; ini merapikan niatnya
--    supaya tak "menyala" kalau induknya kelak diberikan.
-- ─────────────────────────────────────────────────────────────────────────────
DELETE ga FROM `group_access` ga
JOIN `group_path` g ON g.`id` = ga.`group_id`
JOIN `access_path` ap ON ap.`id` = ga.`access_id`
WHERE g.`nama` = 'Content Creator' AND ap.`link` = 'marketing-promo';

COMMIT;

-- ============================================================================
-- VERIFIKASI setelah jalan (JANGAN DILEWATI):
--   1. DETEKTOR KEGAGALAN SENYAP — daun di-grant tapi leluhur tidak.
--      Baris yang tersisa = menu yang diberikan tapi tak akan pernah tampil:
--
--      SELECT g.nama AS role, ap.nama AS halaman, ap.link,
--             p1.nama AS induk1, p2.nama AS induk2
--        FROM group_access ga
--        JOIN group_path g   ON g.id = ga.group_id
--        JOIN access_path ap ON ap.id = ga.access_id
--        LEFT JOIN access_path p1 ON p1.id = ap.pid
--        LEFT JOIN access_path p2 ON p2.id = p1.pid
--       WHERE ap.link IS NOT NULL AND ap.link <> ''
--         AND EXISTS (SELECT 1 FROM users u WHERE u.group_id=g.id AND u.status=1)
--         AND ( (p1.id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM group_access w
--                  WHERE w.group_id=g.id AND w.access_id=p1.id))
--            OR (p2.id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM group_access w
--                  WHERE w.group_id=g.id AND w.access_id=p2.id)) )
--       ORDER BY g.nama, ap.link;
--
--      Yang WAJAR tersisa: node kembar rawreservation/report yang induknya
--      (Menu / Schedule / Laporan) memang sengaja tak di-grant.
--   2. Anisa logout-login → sidebar bertambah Pasien, Doctor, Reservation,
--      Laporan Penjualan, Dashboard Sales, masing-masing TEPAT SATU baris.
--   3. Talitha logout-login → tak ada menu Promo.
-- ============================================================================
