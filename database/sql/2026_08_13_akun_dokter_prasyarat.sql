-- ════════════════════════════════════════════════════════════════════════════
-- Akun Dokter — PRASYARAT (docs/rencana/rencana-akun-dokter.md §8 butir 1 & 2)
-- ════════════════════════════════════════════════════════════════════════════
-- Dua hal saja, dan dua-duanya harus ada SEBELUM satu pun akun dokter dibuat:
--
--   1. `users.raw_doctor_id` — kolom penghubung akun ↔ dokter (§3).
--      Tanpa ini akun dokter = akun KOSONG. Datanya sudah lengkap di prod
--      (35 dokter, 103 jadwal praktik, 57 jadwal khusus, 261 layanan,
--      4.759 reservasi) — yang tak ada cuma cara menjawab "user yang login
--      ini dokter yang mana". Sekali akun terlanjur dibuat tanpa kolom ini,
--      penautannya jadi kerja tangan yang gampang salah pasang: nama dokter
--      panjang-panjang dan mirip-mirip.
--
--   2. Role "Dokter" berdiri sendiri (§4). Tak ada perubahan skema — group_path
--      sudah mendukung role puncak (parent_id NULL, level 0); Executive dan
--      Super Admin persis begitu. Role yang ada TIDAK ada yang cocok:
--      "Assistant Dentist" & "Coordinator Dentist" itu untuk asisten, bukan
--      dokternya, dan menumpangkan dokter di sana akan menyeret hak akses
--      checklist + hierarki approval milik asisten ke orang yang salah.
--
-- Yang SENGAJA belum ada di file ini:
--   • Penugasan aturan kerja (att_user_rule) untuk dokter — arahnya belum
--     diputuskan, lihat docs/rencana/rencana-akun-dokter-absensi-jadwal.md.
--     Memberi dokter aturan 'baku' sekarang = alpa massal tiap hari mereka
--     tak praktik; memberi 'fleksibel' = absennya tak pernah dinilai sama
--     sekali. Dua-duanya keputusan yang tak boleh diambil diam-diam lewat SQL.
--   • Akun ke-6 dokternya sendiri, dan penautan raw_doctor_id-nya. Menyusul
--     bareng penggelaran Pamulang (keenamnya praktik di sana) — template
--     penautannya ada di bagian VERIFIKASI paling bawah.
--
-- Mirror migration: 2026_08_13_100000_add_raw_doctor_id_to_users_table.php
-- Idempotent: cek information_schema untuk kolom, NOT EXISTS untuk baris.
-- Aman dijalankan ulang.
--
-- URUTAN: `php artisan migrate` DULU, baru berkas ini (pelajaran gerbong petty
-- cash). Tapi urutan terbalik pun kini TIDAK merusak: migration-nya sengaja
-- dibuat idempoten juga (cek `Schema::hasColumn`), jadi tak ada lagi
-- "Duplicate column name" yang memblokir seluruh batch migration.
--
-- ⚠️ SETELAH file ini: WAJIB `php artisan access:rebuild` — grant tanpa
--    rebuild = menu tak muncul (kegagalan-senyap #3 di katalog).
--
-- ⚠️⚠️ JANGAN BUAT AKUN DOKTER SEBELUM ATURAN KERJA `jadwal` ADA.
--    `group_path.att_default_rule_id` untuk role Dokter sengaja NULL — aturan
--    `jadwal`-nya memang belum dibangun. Akibatnya akun dokter yang dibuat
--    sekarang lahir TANPA baris `att_user_rule`, dan reconciler hanya menyapa
--    orang yang punya baris itu (AttendanceReconciler::reconcile). Scan dokter
--    akan jadi YATIM: tak dinilai, tak muncul di rekap, dan gagalnya DIAM —
--    persis kelas cacat yang dulu menelan 538 scan Head IT.
--    Urutan yang benar: aturan `jadwal` dibangun → isi att_default_rule_id role
--    Dokter → BARU buat akunnya.
-- ════════════════════════════════════════════════════════════════════════════


-- ─────────────────────────────────────────────────────────────────────────────
-- 1. users.raw_doctor_id — nullable + UNIQUE
-- ─────────────────────────────────────────────────────────────────────────────
-- UNIQUE, bukan index biasa: satu akun = satu dokter, dan satu dokter tak boleh
-- dipegang dua akun (jadwal & reservasi yang sama muncul di dua tempat, absen
-- jadi ambigu). NULL boleh berulang — MySQL tak menghitung NULL di UNIQUE —
-- jadi seluruh akun karyawan yang bukan dokter tetap aman.
--
-- TANPA foreign key, sengaja, mengikuti `users.raw_branch_id` yang juga varchar
-- polos: FK menuntut collation dua tabel persis sama, dan collation di sini
-- sudah terbukti melenceng antar environment (lihat COLLATE eksplisit yang
-- terpaksa ditulis di query att_approval_rule ↔ group_path). FK yang gagal
-- pasang saat deploy jauh lebih mahal daripada baris yatim yang bisa dicek.

DROP PROCEDURE IF EXISTS `usr_add_doctor_col`;
DELIMITER $$
CREATE PROCEDURE `usr_add_doctor_col`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users' AND COLUMN_NAME = 'raw_doctor_id'
  ) THEN
    ALTER TABLE `users`
      ADD COLUMN `raw_doctor_id` varchar(255) NULL DEFAULT NULL
        COMMENT 'FK logis raw_doctor.id — NULL = akun ini bukan dokter'
        AFTER `raw_branch_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_raw_doctor_id_unique'
  ) THEN
    ALTER TABLE `users`
      ADD UNIQUE KEY `users_raw_doctor_id_unique` (`raw_doctor_id`);
  END IF;
END$$
DELIMITER ;

CALL usr_add_doctor_col();

DROP PROCEDURE IF EXISTS `usr_add_doctor_col`;


-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Role "Dokter" — berdiri sendiri di puncak struktur
-- ─────────────────────────────────────────────────────────────────────────────
-- parent_id NULL + level 0 = tidak di bawah siapa pun. Itu bukan pernyataan
-- soal pangkat, melainkan soal APPROVAL: hierarki group_path dipakai untuk
-- menentukan penyetuju berjenjang. Menaruh Dokter di bawah Supervisor akan
-- membuat pengajuan dokter mendarat di inbox SPV cabang — dan dokter praktik di
-- beberapa cabang, jadi tak ada satu SPV pun yang benar. Biarkan puncak sampai
-- ada keputusan sadar siapa yang menyetujui pengajuan dokter.
--
-- landing_page 'akun' = zona pribadi. Ini SENGAJA: menu utama role Dokter akan
-- kosong, dan itu bukan cacat — dokter tidak mengerjakan kerjaan role, dia
-- membuka aplikasi dari HP untuk melihat harinya sendiri dan absen. Kalau kelak
-- beranda dokter jadi dibangun, ganti landing_page ke halaman itu.
--
-- theme dibiarkan NULL → cokelat default (docs/spec/peta-tema.md). Dokter bukan
-- zona spesial.
--
-- id deterministik supaya file ini idempoten lintas environment; guard by NAMA
-- supaya kalau role "Dokter" sudah terlanjur dibuat lewat UI (dengan id UUID
-- acak), file ini tidak membuat role kembar.

-- `parent_id_2` sengaja TIDAK disebut: kolom itu lahir di gerbong 6 Agu, dan
-- menyebutnya di sini membuat file ini gagal total di database yang belum
-- kebagian gerbong itu. Nullable, jadi diam-diam terisi NULL — yang memang benar.
INSERT INTO `group_path` (`id`, `nama`, `parent_id`, `level`, `deskripsi`, `landing_page`)
SELECT 'role-dokter-000000000000', 'Dokter', NULL, 0,
       'Dokter gigi praktik. Zona pribadi: jadwal praktik, reservasi hari ini, absen. Lintas cabang.',
       'akun'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `group_path` WHERE `nama` = 'Dokter');


-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Grant menu untuk role Dokter — zona pribadi saja
-- ─────────────────────────────────────────────────────────────────────────────
-- Cukup node ber-link `akun` ("Absensi Saya") + INDUKNYA. AuthGuard membandingkan
-- SEGMENT-1, jadi satu grant ini sekaligus membuka /akun/absen (selfie) dan
-- /akun/pengajuan — tak perlu grant terpisah per halaman.
--
-- Induk ikut di-grant karena buildTree tidak menampilkan anak tanpa induk
-- (pelajaran gerbong 4 Agu: 6 orang tak bisa absen sama sekali).
--
-- BY LINK, bukan by id node: id node berbeda antar environment.
--
-- YANG SENGAJA TIDAK DIBERI:
--   • `persetujuan` — dokter bukan penyetuju di att_approval_rule, jadi
--     inboxnya dijamin selalu kosong. Pintu kosong lebih buruk daripada tak ada
--     pintu (keputusan yang sama dipakai untuk Content Creator & Creative).
--   • `rawreservation` / halaman Dokter sisi admin — itu layar lebar milik
--     operasional klinik, terkunci cabang sesi, dan menampilkan data pasien
--     seluruh cabang. Reservasi milik dokter sendiri akan datang lewat layar
--     pribadinya, disaring per-DOKTER, bukan per-cabang.

-- ⚠️ `GROUP BY` di kedua INSERT bukan hiasan — ini penjaga terhadap cacat nyata.
-- Audit menu 5 Agu meninggalkan utang yang BELUM ditutup: **node ber-link
-- kembar** (lebih dari satu baris access_path memakai link yang sama). Kalau
-- ada dua node ber-link 'akun' yang berbagi INDUK yang sama, SELECT di bawah
-- menghasilkan dua baris dengan `id` grant yang IDENTIK (id-nya turunan dari
-- g.id + p.id saja). `NOT EXISTS` tak menolongnya: subquery itu melihat isi
-- tabel SEBELUM statement jalan, jadi ia tak tahu baris kembarnya lahir di
-- statement yang sama. Hasilnya duplicate-key 1062 dan SELURUH berkas gagal.
-- GROUP BY meruntuhkan kembarannya sebelum sempat jadi masalah.

START TRANSACTION;

INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('dok-akun-', SUBSTRING(MD5(CONCAT(g.`id`, ap.`id`)), 1, 22)), g.`id`, ap.`id`
FROM `group_path` g
JOIN `access_path` ap ON ap.`link` = 'akun'
WHERE g.`nama` = 'Dokter'
  AND NOT EXISTS (SELECT 1 FROM `group_access` w
                  WHERE w.`group_id` = g.`id` AND w.`access_id` = ap.`id`)
GROUP BY g.`id`, ap.`id`;

INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('dok-akunp-', SUBSTRING(MD5(CONCAT(g.`id`, p.`id`)), 1, 21)), g.`id`, p.`id`
FROM `group_path` g
JOIN `access_path` ap ON ap.`link` = 'akun'
JOIN `access_path` p  ON p.`id` = ap.`pid`
WHERE g.`nama` = 'Dokter'
  AND NOT EXISTS (SELECT 1 FROM `group_access` w
                  WHERE w.`group_id` = g.`id` AND w.`access_id` = p.`id`)
GROUP BY g.`id`, p.`id`;

COMMIT;


-- ============================================================================
-- VERIFIKASI setelah jalan (JANGAN DILEWATI)
-- ============================================================================
--
-- 1. Kolom + UNIQUE terpasang — harus 2 baris (COLUMNS & STATISTICS):
--      SELECT 'kolom' AS apa, COUNT(*) FROM information_schema.COLUMNS
--        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'
--          AND COLUMN_NAME='raw_doctor_id'
--      UNION ALL
--      SELECT 'unique', COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
--        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'
--          AND INDEX_NAME='users_raw_doctor_id_unique';
--
-- 2. Role Dokter berdiri sendiri — parent_id NULL, level 0, TEPAT satu baris:
--      SELECT id, nama, parent_id, level, landing_page
--        FROM group_path WHERE nama='Dokter';
--
-- 3. Role Dokter punya menu pribadi. JANGAN mematok angka — kalau ada node
--    ber-link kembar (utang audit menu 5 Agu), jumlahnya sah lebih dari 2.
--    Yang WAJIB benar: node ber-link 'akun' ADA, dan tiap node cuma sekali.
--      SELECT ap.nama, IFNULL(ap.link,'(induk)') AS link, COUNT(*) AS n
--        FROM group_access ga
--        JOIN access_path ap ON ap.id=ga.access_id
--        JOIN group_path g   ON g.id=ga.group_id
--       WHERE g.nama='Dokter' GROUP BY ap.id;      -- tiap n HARUS 1
--    dan minimal satu barisnya ber-link 'akun'.
--
-- 4. `php artisan access:rebuild` — TANPA ini menu tak muncul walau grant ada.
--
-- 5. Tidak ada penautan yatim (jaga-jaga sesudah akun dibuat) — harus 0 baris.
--    COLLATE eksplisit: collation `users` vs `raw_doctor` sudah terbukti bisa
--    melenceng antar environment di proyek ini, dan tanpa COLLATE query ini
--    mati dengan "Illegal mix of collations" — bukan menjawab pertanyaannya.
--      SELECT u.id, u.nama, u.raw_doctor_id FROM users u
--       WHERE u.raw_doctor_id IS NOT NULL
--         AND NOT EXISTS (SELECT 1 FROM raw_doctor d
--                          WHERE d.id COLLATE utf8mb4_unicode_ci
--                              = u.raw_doctor_id COLLATE utf8mb4_unicode_ci);
--
-- ============================================================================
-- TEMPLATE penautan akun ↔ dokter (BARU dipakai setelah akunnya dibuat)
-- ============================================================================
-- Jangan menyalin id dokter dengan tangan — nama keenam dokter panjang dan
-- mirip ("drg. Putri Moortiningsih Hasheila", "drg. Maharetta Ditaprilia,
-- Sp. Ort") dan salah pasang berarti seorang dokter melihat pasien dokter lain.
-- Tautkan lewat pencocokan nama yang EKSPLISIT satu per satu, lalu verifikasi
-- butir 5 & 6 di atas/bawah:
--
--   UPDATE users u JOIN raw_doctor d ON d.name = 'drg. Nadya Ayustia'
--      SET u.raw_doctor_id = d.id
--    WHERE u.email = '<email akun dokter itu>';
--
-- 6. Sesudah menautkan — tiap akun dokter benar-benar dapat pasangan, dan
--    pasangannya dokter AKTIF. Harus 0 baris:
--      SELECT u.nama, u.email FROM users u
--        JOIN group_path g ON g.id=u.group_id
--       WHERE g.nama='Dokter'
--         AND (u.raw_doctor_id IS NULL
--              OR NOT EXISTS (SELECT 1 FROM raw_doctor d
--                              WHERE d.id COLLATE utf8mb4_unicode_ci
--                                  = u.raw_doctor_id COLLATE utf8mb4_unicode_ci
--                                AND d.is_active = 1));
--
-- 7. Tiap akun dokter punya aturan kerja — kalau tidak, scannya YATIM dan
--    orangnya hilang dari rekap tanpa satu pun pesan. Harus 0 baris:
--      SELECT u.nama FROM users u
--        JOIN group_path g ON g.id=u.group_id
--       WHERE g.nama='Dokter' AND u.status=1
--         AND NOT EXISTS (SELECT 1 FROM att_user_rule r WHERE r.user_id=u.id);
-- ============================================================================
