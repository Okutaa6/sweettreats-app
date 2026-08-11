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
INSERT INTO `att_user_rule` (`user_id`, `rule_id`, `effective_from`, `created_at`, `updated_at`)
SELECT u.`id`, 1, '2026-08-05', DATE_ADD(NOW(), INTERVAL 7 HOUR), DATE_ADD(NOW(), INTERVAL 7 HOUR)
FROM `users` u
WHERE u.`email` = 'fajri@sweettreats.id'
  AND NOT EXISTS (SELECT 1 FROM `att_user_rule` r WHERE r.`user_id` = u.`id`);

-- ── 4) Saldo cuti operasional Bintaro — IKUT SHEET ───────────────────────────
-- Sheet HR yang jadi acuan, bukan angka yang terlanjur ada di DB.
--   Firda 6 · Rizka 6 · Asih 7 (ketiganya sudah cocok) · Afrina 0 (DB: 1)
--   · Alpajri 0 (DB: belum punya baris sama sekali)
UPDATE `att_cuti_periode` p JOIN `users` u ON u.`id` = p.`user_id`
SET p.`saldo_awal` = 0.0, p.`updated_at` = DATE_ADD(NOW(), INTERVAL 7 HOUR)
WHERE u.`email` = 'afrina@sweettreats.id';

INSERT INTO `att_cuti_periode` (`user_id`, `saldo_awal`, `berjalan`, `created_at`, `updated_at`)
SELECT u.`id`, 0.0, 1, DATE_ADD(NOW(), INTERVAL 7 HOUR), DATE_ADD(NOW(), INTERVAL 7 HOUR)
FROM `users` u
WHERE u.`email` = 'fajri@sweettreats.id'
  AND NOT EXISTS (SELECT 1 FROM `att_cuti_periode` c WHERE c.`user_id` = u.`id`);

-- ── CATATAN: kontrak BELUM diisi ─────────────────────────────────────────────
-- Kolom kontrak_mulai / kontrak_akhir sengaja dibiarkan kosong. Sheet cuma
-- punya SATU kolom tanggal ("Kontrak") sementara skema butuh mulai DAN akhir,
-- dan belum dipastikan tanggal itu tanggal berakhir. Mengarang tanggal mulai
-- = merusak perhitungan reset jatah cuti setahun ke depan. Menyusul setelah
-- Ekky memastikan.
--
-- ── CATATAN: alpa Asih 5 Agu ─────────────────────────────────────────────────
-- TIDAK disentuh di sini. Dia benar-benar tidak masuk (sakit), jadi
-- perbaikannya lewat pengajuan Sakit resmi — dan itu butuh fitur "sakit"
-- naik prod dulu (SQL 2026_08_11_aturan_sakit.sql + kode). Menutupnya lewat
-- UPDATE manual akan menghapus jejak pengaju & penyetujunya.
-- Alpa IRMA di tanggal yang sama lain perkara: itu murni salah tempel aturan,
-- dan hilang sendiri sesudah bagian (2) di atas + `php artisan att:reconcile`.
