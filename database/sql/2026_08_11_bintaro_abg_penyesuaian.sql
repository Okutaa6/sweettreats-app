-- ═══════════════════════════════════════════════════════════════════════════
-- Penyesuaian data: ABG (4 orang) + operasional Bintaro (5 orang)
--
-- Konteks: prod digelar BERTAHAP — sekarang baru ABG/Office + operasional
-- Bintaro; cabang lain menyusul minggu per minggu. Jadi 13 nama cabang lain
-- yang belum ada di prod itu BUKAN data hilang, memang belum gilirannya.
--
-- Keputusan Ekky 2026-08-11:
--   • Ikhsan, Reza, Anisa, Irma = ABG → TIDAK di cabang mana pun.
--     Anisa & Irma = Fleksibel Operasional · Ikhsan & Reza = Fleksibel Kantor.
--   • Saldo cuti mengikuti SHEET (bukan angka yang sekarang di DB).
--
-- Dikunci lewat EMAIL, bukan nama — ejaan nama berbeda-beda antar sumber
-- ("Alpajri" vs "Apajri", "Meilani" vs "Melani"), email tidak.
--
-- Idempoten: aman dijalankan ulang.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── 1) Empat orang ABG: lepas dari cabang ────────────────────────────────────
-- Bukan sekadar kosmetik. `session('raw_branch_id')` diisi dari kolom ini saat
-- login, dan ReservationController MENGUNCI KERAS daftar reservasi ke cabang
-- itu — dropdown filternya pun ikut terkunci. Selama kolom ini terisi, orang
-- kantor cuma bisa melihat satu cabang. Dikosongkan = melihat semua cabang,
-- sebagaimana mestinya orang kantor.
UPDATE `users` SET `raw_branch_id` = NULL
WHERE `email` IN ('ikhsan@sweettreats.id','reza@sweettreats.id',
                  'anisa@sweettreats.id','irma@sweettreats.id');

-- ── 2) Bersihkan aturan kerja RANGKAP ────────────────────────────────────────
-- 8 orang punya DUA baris aturan aktif: penugasan 6 Agu tidak menutup yang
-- 5 Agu (`effective_to` dibiarkan NULL). Akibatnya aturan mana yang berlaku
-- ditentukan urutan balikan MySQL — dan untuk Anisa & Irma salah satunya
-- bertipe `baku`, yang BISA memunculkan alpa pada orang yang jam kerjanya
-- justru tidak dinilai. Ini sumber alpa Irma 5 Agu.
--
-- Yang lama ditutup sehari sebelum yang baru mulai. Baris TIDAK dihapus —
-- riwayat penugasan tetap terbaca.
UPDATE `att_user_rule` ur
JOIN (
    SELECT `user_id`, MAX(`effective_from`) AS mulai_terbaru
    FROM `att_user_rule`
    WHERE `effective_to` IS NULL
    GROUP BY `user_id`
    HAVING COUNT(*) > 1
) t ON t.`user_id` = ur.`user_id`
SET ur.`effective_to` = DATE_SUB(t.mulai_terbaru, INTERVAL 1 DAY)
WHERE ur.`effective_to` IS NULL
  AND ur.`effective_from` < t.mulai_terbaru;

-- Anisa & Irma: aturan `baku` 5 Agu itu SALAH TEMPEL sejak awal, bukan
-- kebijakan yang pernah berlaku sehari. Kalau cuma ditutup 4 Agu, tanggal
-- 5 Agu-nya tetap dinilai baku dan alpa Irma bertahan. Dibuat tak pernah
-- berlaku: effective_to = sehari SEBELUM effective_from.
UPDATE `att_user_rule` ur
JOIN `users` u ON u.`id` = ur.`user_id`
JOIN `att_work_rule` wr ON wr.`id` = ur.`rule_id`
SET ur.`effective_to` = DATE_SUB(ur.`effective_from`, INTERVAL 1 DAY)
WHERE u.`email` IN ('anisa@sweettreats.id','irma@sweettreats.id')
  AND wr.`tipe` = 'baku';

-- Jejak keputusannya — supaya pembaca berikutnya tahu ini disengaja.
INSERT INTO `att_rule_log` (`actor_nama`, `aksi`, `target`, `detail`, `created_at`)
SELECT 'SQL 2026_08_11 (ACC Ekky)', 'penugasan', u.`nama`,
       CONCAT('Aturan baku salah-tempel dibuat tak pernah berlaku; ',
              u.`nama`, ' adalah Fleksibel Operasional (ABG).'),
       DATE_ADD(NOW(), INTERVAL 7 HOUR)
FROM `users` u
WHERE u.`email` IN ('anisa@sweettreats.id','irma@sweettreats.id')
  AND NOT EXISTS (
      SELECT 1 FROM `att_rule_log` l
      WHERE l.`target` = u.`nama` AND l.`detail` LIKE 'Aturan baku salah-tempel%'
  );

-- ── 3) Alpajri: beri aturan kerja GS ─────────────────────────────────────────
-- Dia General Services di Bintaro, absen tiap hari, tapi TIDAK punya aturan
-- kerja sama sekali — jadi kehadirannya tak pernah dinilai dan dia cuma
-- tertangkap jaring pengaman di layar Kehadiran. Aturan GS (baku, libur Senin)
-- sudah ada di prod tapi belum dipakai siapa pun.
--
-- ⚠️ EMAILNYA BEDA ANTAR LINGKUNGAN: prod 'fajri@', dev 'apajri@'. Ketahuan
-- justru karena skrip ini diuji ke dev dulu — kalau langsung ke prod, bagian
-- ini diam-diam tak kena di salah satu sisi. Dicocokkan ke dua-duanya.
INSERT INTO `att_user_rule` (`user_id`, `rule_id`, `effective_from`, `created_at`, `updated_at`)
SELECT u.`id`, 1, '2026-08-05', DATE_ADD(NOW(), INTERVAL 7 HOUR), DATE_ADD(NOW(), INTERVAL 7 HOUR)
FROM `users` u
WHERE u.`email` IN ('fajri@sweettreats.id','apajri@sweettreats.id')
  AND NOT EXISTS (SELECT 1 FROM `att_user_rule` r WHERE r.`user_id` = u.`id`);

-- ── 4) Saldo cuti operasional Bintaro — IKUT SHEET ───────────────────────────
-- Sheet HR yang jadi acuan, BUKAN angka yang terlanjur ada di DB. Kelimanya
-- ditulis eksplisit (bukan cuma yang selisih) supaya prod & dev bertemu di
-- angka yang sama, apa pun isinya sekarang:
--   Firda 6 · Rizka 6 · Asih 7 · Afrina 0 · Alpajri 0
UPDATE `att_cuti_periode` p JOIN `users` u ON u.`id` = p.`user_id`
SET p.`saldo_awal` = CASE u.`email`
        WHEN 'firda@sweettreats.id' THEN 6.0
        WHEN 'rizka@sweettreats.id' THEN 6.0
        WHEN 'asih@sweettreats.id'  THEN 7.0
        ELSE 0.0                                   -- Afrina & Alpajri
    END,
    p.`updated_at` = DATE_ADD(NOW(), INTERVAL 7 HOUR)
WHERE u.`email` IN ('firda@sweettreats.id','rizka@sweettreats.id','asih@sweettreats.id',
                    'afrina@sweettreats.id','fajri@sweettreats.id','apajri@sweettreats.id');

INSERT INTO `att_cuti_periode` (`user_id`, `saldo_awal`, `berjalan`, `created_at`, `updated_at`)
SELECT u.`id`, 0.0, 1, DATE_ADD(NOW(), INTERVAL 7 HOUR), DATE_ADD(NOW(), INTERVAL 7 HOUR)
FROM `users` u
WHERE u.`email` IN ('fajri@sweettreats.id','apajri@sweettreats.id')
  AND NOT EXISTS (SELECT 1 FROM `att_cuti_periode` c WHERE c.`user_id` = u.`id`);

-- Ejaan nama: sheet HR & prod menulis "Alpajri", dev "Apajri" (kurang huruf L).
-- Disamakan supaya satu orang tak punya dua ejaan antar lingkungan — persis
-- kebingungan yang memakan waktu saat membandingkan data ini.
UPDATE `users` SET `nama` = 'Alpajri Suhendar Al Qhusni'
WHERE `email` IN ('fajri@sweettreats.id','apajri@sweettreats.id');

-- ── 5) Kontrak operasional Bintaro ───────────────────────────────────────────
-- Sheet cuma punya SATU kolom tanggal, dan Ekky menjelaskan artinya: tanggal
-- itu HARI JADI kontrak — akhir periode yang sedang berjalan, sekaligus awal
-- periode berikutnya kalau diperpanjang. Jadi periode berjalan = 12 bulan
-- mundur dari tanggal itu.
--
-- ⚠️ ASUMSI YANG DIPAKAI: masa kontrak 12 bulan (sesuai rencana-jatah-cuti).
-- Kalau ada yang kontraknya 6 atau 24 bulan, tanggal MULAI-nya jadi salah
-- (tanggal AKHIR tetap benar karena itu yang tertulis di sheet). Reset jatah
-- cuti berpatokan pada periode ini, jadi kekeliruan di sini baru terasa saat
-- reset — sebutkan kalau ada yang tidak 12 bulan.
UPDATE `att_cuti_periode` p JOIN `users` u ON u.`id` = p.`user_id`
SET p.`kontrak_akhir` = CASE u.`email`
        WHEN 'firda@sweettreats.id'  THEN '2026-09-12'
        WHEN 'rizka@sweettreats.id'  THEN '2026-10-24'
        WHEN 'asih@sweettreats.id'   THEN '2026-09-30'
        WHEN 'afrina@sweettreats.id' THEN '2026-10-28'
        ELSE '2026-11-13'                              -- Alpajri
    END,
    p.`kontrak_mulai` = DATE_SUB(CASE u.`email`
        WHEN 'firda@sweettreats.id'  THEN '2026-09-12'
        WHEN 'rizka@sweettreats.id'  THEN '2026-10-24'
        WHEN 'asih@sweettreats.id'   THEN '2026-09-30'
        WHEN 'afrina@sweettreats.id' THEN '2026-10-28'
        ELSE '2026-11-13'
    END, INTERVAL 12 MONTH),
    p.`updated_at` = DATE_ADD(NOW(), INTERVAL 7 HOUR)
WHERE u.`email` IN ('firda@sweettreats.id','rizka@sweettreats.id','asih@sweettreats.id',
                    'afrina@sweettreats.id','fajri@sweettreats.id','apajri@sweettreats.id');
--
-- ── CATATAN: alpa Asih 5 Agu ─────────────────────────────────────────────────
-- TIDAK disentuh di sini. Dia benar-benar tidak masuk (sakit), jadi
-- perbaikannya lewat pengajuan Sakit resmi — dan itu butuh fitur "sakit"
-- naik prod dulu (SQL 2026_08_11_aturan_sakit.sql + kode). Menutupnya lewat
-- UPDATE manual akan menghapus jejak pengaju & penyetujunya.
-- Alpa IRMA di tanggal yang sama lain perkara: itu murni salah tempel aturan,
-- dan hilang sendiri sesudah bagian (2) di atas + `php artisan att:reconcile`.
