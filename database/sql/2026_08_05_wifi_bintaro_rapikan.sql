-- ════════════════════════════════════════════════════════════════════════════
-- Rapikan daftar WiFi Bintaro — satu jaringan cukup satu baris
-- ════════════════════════════════════════════════════════════════════════════
-- Keputusan Ekky 2026-08-05, setelah menelusuri sendiri tiap SSID kantor.
--
-- PETA BINTARO (dibuktikan dengan membuka halaman WiFi Cabang dari tiap SSID):
--   Jalur A — SSID Xiaomi_FD3D_5G, Sweet Treats Lt.2, Sweet Treats BO
--             → IPv4 180.252.168.224 · IPv6 prefix 2001:448a:2092:2313::/64
--   Jalur B — SSID Sweet Treats (dan kemungkinan Lt.1)
--             → IPv4 180.252.170.180 · IPv6 prefix 2001:448a:2092:134e::/64
-- Terbukti: Lt.2 dan BO memberi alamat yang sama persis (satu jaringan, dua
-- nama), dan Xiaomi tidak melayani IPv6 sama sekali (hanya IPv4).
--
-- KENAPA DAFTARNYA MEMBENGKAK JADI 10:
-- Pencocokan IPv6 memakai 64 bit pertama, jadi ekor alamat tidak berpengaruh.
-- Tapi pemeriksaan duplikat pada FORMULIR pendaftaran membandingkan TEKS
-- alamat. Karena tiap HP punya ekor sendiri, siapa pun yang membuka halaman
-- itu bisa menambah baris baru untuk jaringan yang SUDAH tercakup — tanpa
-- peringatan apa pun. Lima dari sepuluh baris lahir begitu.
-- (Perbaikan formulirnya dicatat sebagai utang, bukan bagian berkas ini.)
--
-- YANG DINONAKTIFKAN (bukan dihapus — bisa dinyalakan lagi kapan saja):
--   • id 7, 9, 10 — kembar: jaringannya sudah diwakili id 5 dan id 8.
--   • id 1, 2     — BASI. Prefix 2001:448a:2092:ab6::/64 didaftarkan 25 Juli
--                   dan sudah tidak dipakai Bintaro hari ini (dua jalur yang
--                   hidup adalah 2313 dan 134e). Baris basi berbahaya: kalau
--                   Telkom sudah melepas prefix itu ke pelanggan lain, orang
--                   di luar kantor bisa terbaca sebagai Bintaro.
--
-- YANG DIPERTAHANKAN — empat wajah Bintaro + Sunter:
--   id 4  180.252.168.224                          (Jalur A, IPv4)
--   id 8  2001:448a:2092:2313:1fe9:bc37:8ef7:93cd  (Jalur A, IPv6)
--   id 6  180.252.170.180                          (Jalur B, IPv4)
--   id 5  2001:448a:2092:134e:b0cc:8a6a:22f5:8c53  (Jalur B, IPv6)
--   id 3  2001:448a:2062:208c:465d:430d:3098:7584  (Sunter)
--
-- TIDAK ADA YANG KEHILANGAN AKSES: tiap jaringan tetap terwakili satu baris,
-- dan absen dari IP tak dikenal pun tidak pernah ditolak — hanya diminta
-- memilih cabang lalu ditandai untuk supervisor.
--
-- Dicocokkan BY IP (data), bukan by id, supaya aman dijalankan di environment
-- mana pun; di lingkungan yang tak punya baris itu, perintahnya no-op.

START TRANSACTION;

UPDATE `att_branch_ip`
   SET `status` = 'nonaktif',
       `label`  = CONCAT(COALESCE(NULLIF(`label`, ''), '—'), ' · dinonaktifkan 5 Agu: jaringan kembar')
 WHERE `ip` IN (
        '2001:448a:2092:134e:fd2d:312f:c76e:d78b',  -- kembar Jalur B (id 5 mewakili)
        '2001:448a:2092:134e:4062:7f6:5a14:3ed8',   -- kembar Jalur B
        '2001:448a:2092:2313:293d:5f83:1cd9:68ec'   -- kembar Jalur A (id 8 mewakili)
      )
   AND `status` = 'aktif';

UPDATE `att_branch_ip`
   SET `status` = 'nonaktif',
       `label`  = CONCAT(COALESCE(NULLIF(`label`, ''), '—'), ' · dinonaktifkan 5 Agu: prefix ab6 sudah tak dipakai')
 WHERE `ip` IN (
        '2001:448a:2092:ab6:4135:e5ba:23eb:1429',
        '2001:448a:2092:ab6:80b6:5f1a:1382:6201'
      )
   AND `status` = 'aktif';

COMMIT;

-- ============================================================================
-- VERIFIKASI setelah jalan:
--   SELECT b.name, i.ip, i.status FROM att_branch_ip i
--     JOIN raw_branch b ON b.id = i.raw_branch_id
--    WHERE i.status = 'aktif' ORDER BY b.name, i.ip;
--   → harus TEPAT 5 baris: 4 Bintaro (dua IPv4 + dua prefix IPv6 berbeda)
--     dan 1 Sunter. Tidak boleh ada dua baris dengan 4 kelompok IPv6 pertama
--     yang sama.
--
-- UJI SEBENARNYA ada besok pagi: kalau orang Bintaro absen tanpa flag
-- `ip_asing`, berarti kedua jalur sudah tertutup benar.
-- ============================================================================
