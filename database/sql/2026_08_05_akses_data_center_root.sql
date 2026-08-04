-- ════════════════════════════════════════════════════════════════════════════
-- Akses Supervisor & Coordinator Dentist — KOREKSI, plus cabut Promo Talitha
-- ════════════════════════════════════════════════════════════════════════════
-- ⚠️ BERKAS INI DIPERBAIKI 2026-08-05 SEBELUM NAIK PROD. Versi pertamanya
--    MENAMBAHKAN grant akar "Data Center" untuk Supervisor & Coordinator
--    Dentist. Itu SALAH DAN BERBAHAYA: node akar itu membawa link
--    `MetricaSetting`, jadi meng-grant akar = memberi halaman MetricaSetting —
--    persis yang dikecualikan waktu Ekky memutuskan Supervisor dapat paket
--    sales "TANPA MetricaSetting". Ketahuan saat diuji di dev (menu malah
--    memunculkan satu baris bernama "Data Center"), sebelum menyentuh prod.
--    Versi ini MENCABUT grant itu — aman dijalankan di lingkungan mana pun,
--    termasuk yang sempat menjalankan versi pertama.
--
-- LALU BAGAIMANA MENU ANISA & IRMA MUNCUL?
-- Diperbaiki di KODE, bukan dengan menambah grant: `RebuildGroupAccess`
-- sekarang menyisipkan leluhur yang tak di-grant sebagai WADAH (link
-- dikosongkan, jadi tidak menambah akses apa pun). Sebabnya pohon menu dulu
-- hanya dibangun dari node level-0 yang di-grant, sehingga halaman yang sudah
-- diberikan tapi akarnya tidak ikut diberikan HILANG tanpa suara — sudah tiga
-- kali menggigit dalam dua hari. `Set\Group::access_generator()` juga
-- disatukan ke command yang sama supaya menyimpan akses lewat UI tidak
-- merusaknya lagi.
--
-- Jadi setelah kode ini naik + `access:rebuild`:
--   • Supervisor (Anisa)         → Pasien, Doctor, Reservation,
--                                   Laporan Penjualan, Dashboard Sales
--   • Coordinator Dentist (Irma) → Doctor, Reservation
--   • dua-duanya TETAP TIDAK punya MetricaSetting.
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`, lalu logout-login.

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Cabut grant akar "Data Center" dari kedua peran ini.
--    Keduanya TIDAK pernah memegangnya sebelum 2026-08-05 (pemegang sahnya:
--    Admin, Admin Backup, Admin General, Branch Executive, Executive, Front
--    Office, Head IT, IT, Sales, Super Admin — tak satu pun disentuh di sini).
--    Kalau versi pertama berkas ini tak pernah jalan di environment ini,
--    perintah di bawah tidak melakukan apa-apa.
-- ─────────────────────────────────────────────────────────────────────────────
DELETE ga FROM `group_access` ga
JOIN `group_path` g ON g.`id` = ga.`group_id`
JOIN `access_path` ap ON ap.`id` = ga.`access_id`
WHERE g.`nama` IN ('Supervisor', 'Coordinator Dentist')
  AND ap.`nama` = 'Data Center'
  AND (ap.`pid` IS NULL OR ap.`pid` = '');

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Cabut "Promo (Marketing)" dari Content Creator — keputusan Ekky 2026-08-05.
--    (Talitha satu-satunya penghuni role ini.)
-- ─────────────────────────────────────────────────────────────────────────────
DELETE ga FROM `group_access` ga
JOIN `group_path` g ON g.`id` = ga.`group_id`
JOIN `access_path` ap ON ap.`id` = ga.`access_id`
WHERE g.`nama` = 'Content Creator' AND ap.`link` = 'marketing-promo';

COMMIT;

-- ============================================================================
-- VERIFIKASI setelah jalan + `access:rebuild` (JANGAN DILEWATI):
--   1. Tak ada peran yang kebobolan MetricaSetting:
--      SELECT g.nama FROM group_access ga
--        JOIN group_path g ON g.id = ga.group_id
--        JOIN access_path ap ON ap.id = ga.access_id
--       WHERE ap.link = 'MetricaSetting' ORDER BY g.nama;
--      → Supervisor & Coordinator Dentist TIDAK boleh ada di daftar.
--   2. Menu Anisa memuat Pasien/Doctor/Reservation/Laporan Penjualan/
--      Dashboard Sales, masing-masing TEPAT SATU baris; Irma memuat
--      Doctor + Reservation. Cek lewat berkas hasil rebuild, atau
--      panggil shellMenuSections() dengan session peran itu.
--   3. Talitha tak lagi punya Promo.
-- ============================================================================
