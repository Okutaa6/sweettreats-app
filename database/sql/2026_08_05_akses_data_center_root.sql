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
-- 2. Buang GRANT KEMBAR — satu halaman, satu baris menu.
--
--    `rawreservation` punya 4 node dan `report` punya 2 node (cruft menu lama,
--    tersebar di bawah Dashboard>Menu, Marketing>Schedule, Data Center>Schedule,
--    Data Center>Reservation Data Center, dan Laporan>Laporan). Selama akarnya
--    tak di-grant, kembaran itu tak kelihatan — begitu menu diperbaiki supaya
--    leluhurnya ikut tampil, Anisa akan melihat "Reservasi" EMPAT KALI dan
--    "Laporan Penjualan" dua kali. Terlihat seperti kerusakan, padahal cuma
--    grant berlebih.
--
--    Yang DIPERTAHANKAN dipilih supaya sekelompok dengan halaman sebelahnya:
--      • rawreservation → node di bawah "Reservation Data Center"
--        (setetangga dengan Pasien & Doctor)
--      • report         → node di bawah "Report" (di dalam Data Center)
--    Akses ke halamannya TIDAK berubah: izin dinilai dari link, dan link itu
--    tetap dipegang lewat node yang dipertahankan.
--
--    Dibatasi HANYA pada dua peran ini — peran lain tidak disentuh.
-- ─────────────────────────────────────────────────────────────────────────────
--    Admin General ikut dibereskan: akun itu SUDAH melihat "Reservasi" dan
--    "Reservation" berdampingan hari ini (bukan akibat perubahan ini) — dua
--    baris, satu halaman.
DELETE ga FROM `group_access` ga
JOIN `group_path` g ON g.`id` = ga.`group_id`
JOIN `access_path` ap ON ap.`id` = ga.`access_id`
LEFT JOIN `access_path` induk ON induk.`id` = ap.`pid`
WHERE g.`nama` IN ('Supervisor', 'Coordinator Dentist', 'Admin General')
  AND ap.`link` = 'rawreservation'
  AND (induk.`nama` IS NULL OR induk.`nama` <> 'Reservation Data Center');

DELETE ga FROM `group_access` ga
JOIN `group_path` g ON g.`id` = ga.`group_id`
JOIN `access_path` ap ON ap.`id` = ga.`access_id`
LEFT JOIN `access_path` induk ON induk.`id` = ap.`pid`
WHERE g.`nama` IN ('Supervisor', 'Coordinator Dentist', 'Admin General')
  AND ap.`link` = 'report'
  AND (induk.`nama` IS NULL OR induk.`nama` <> 'Report');

-- ─────────────────────────────────────────────────────────────────────────────
-- 2b. TUTUP JEBAKANNYA: kosongkan link mati di akar "Data Center".
--
--     Akar itu membawa link `MetricaSetting` — dan halamannya TIDAK ADA:
--     tak ada rutenya sama sekali, /MetricaSetting menjawab 404, dan tak satu
--     baris kode pun menyebutnya. Jadi link ini murni sisa lama.
--
--     Selama link itu menempel di akar, "menampilkan wadahnya" dan "memberi
--     halamannya" jadi satu keputusan yang tak bisa dipisah — persis jebakan
--     yang tadi hampir membuat Anisa & Irma diberi MetricaSetting. Dikosongkan
--     supaya akar itu jadi wadah murni dan siapa pun boleh meng-grant-nya
--     dengan aman di kemudian hari.
--
--     Tidak ada yang kehilangan apa pun: yang dilepas adalah link ke halaman
--     yang memang tak ada. 10 peran yang memegang akar ini tetap memegangnya.
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `access_path`
   SET `link` = ''
 WHERE `nama` = 'Data Center'
   AND (`pid` IS NULL OR `pid` = '')
   AND `link` = 'MetricaSetting';

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Cabut "Promo (Marketing)" dari Content Creator — keputusan Ekky 2026-08-05.
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
