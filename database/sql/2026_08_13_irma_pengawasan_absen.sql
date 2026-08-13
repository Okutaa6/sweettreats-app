-- ═══════════════════════════════════════════════════════════════════════════
-- Irma (Coordinator Dentist) diberi Pengawasan Absen — 2026-08-13
--
-- Permintaan Ekky: "kasih irma pengawasan absen yang sama kayak nissa".
-- Nissa = akun `Admin_Nisa` (role Admin Backup), yang memang memegang node
-- Pengawasan → Pengawasan Absen.
--
-- YANG DIBERIKAN, PERSIS DUA NODE:
--   • Pengawasan        (induk menu, tanpa link — tanpa ini menunya tak muncul)
--   • Pengawasan Absen  (link `pengawasan`)
-- Itu SELURUH cabang pohon Pengawasan; induknya cuma punya satu anak. Jadi
-- "sama kayak Nissa" di sisi pengawasan sudah lunas dengan dua baris ini.
--
-- YANG SENGAJA TIDAK DIBERIKAN: Kehadiran, Cuti, WiFi Cabang, Upload Data.
-- Nissa memang punya keempatnya, tapi itu tugas admin absensi — bukan yang
-- diminta. Menambahkannya diam-diam berarti memberi Irma wewenang yang tak
-- pernah diputuskan siapa pun. Kalau nanti memang perlu, tambahkan sadar-sadar.
--
-- CAKUPAN DATA: halaman Pengawasan Absen TIDAK menyaring per cabang pemakainya
-- — semua cabang terlihat, dengan penyaring cabang manual di layar. Irma yang
-- ABG (tanpa cabang) karena itu melihat seluruh klinik, sama seperti Nissa.
--
-- AMAN DIPUKUL RATA KE ROLE: `Coordinator Dentist` hanya berisi Irma seorang
-- (diperiksa di prod 13 Agu). Jadi memberi ke role = memberi ke Irma.
-- ⚠️ Kalau kelak ada Coordinator Dentist lain, dia ikut mewarisi hak ini.
--
-- Dipetakan lewat NAMA role & ID node. Idempoten: aman dijalankan ulang.
--
-- ⚠️ WAJIB SESUDAHNYA: `php artisan access:rebuild`
--    Hak akses dibaca dari berkas storage/app/group_access/<groupId>.txt,
--    bukan langsung dari tabel. Tanpa rebuild, baris di bawah tak berpengaruh
--    apa pun dan menunya tetap tak muncul.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT UUID(), g.`id`, n.`access_id`
FROM `group_path` g
JOIN (
    SELECT 'awas-parent-0000000000' AS access_id
    UNION ALL
    SELECT 'awas-utama-00000000000'
) n
WHERE g.`nama` = 'Coordinator Dentist'
  AND NOT EXISTS (
      SELECT 1 FROM `group_access` ga
      WHERE ga.`group_id` = g.`id` AND ga.`access_id` = n.`access_id`
  );
