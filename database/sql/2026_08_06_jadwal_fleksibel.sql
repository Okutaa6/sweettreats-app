-- ════════════════════════════════════════════════════════════════════════════
-- Jadwal hari untuk orang fleksibel — dipetakan lewat ROLE, bukan nama orang
-- ════════════════════════════════════════════════════════════════════════════
-- Keputusan Ekky 2026-08-06:
--   • ABG/kantor  → libur MINGGU + SENIN
--   • Supervisor & Coordinator Dentist → MASUK hari Minggu, libur SENIN saja
--   • Executive   → TIDAK dijadwalkan sama sekali
--   • Pemetaannya lewat ROLE dan bisa diatur di halaman Aturan Kerja —
--     JANGAN mengunci nama orang di kode/SQL.
--
-- ── KENAPA TIGA ATURAN, BUKAN SATU + PENGECUALIAN ──────────────────────────
-- Sistem ini memisahkan dua hal yang sering dikira satu:
--   `att_work_rule.tipe`  → CARA MENILAI (fleksibel = tak pernah alpa, jam tak
--                           dinilai, boleh Tukar Hari & WFH)
--   `att_work_rule_day`   → HARI MANA yang libur
-- Ketiganya tetap `fleksibel` (hak & cara penilaian sama), yang berbeda hanya
-- daftar hari liburnya. Jadi tak perlu satu pun `if nama == 'Anisa'`.
--
-- "Fleksibel" yang lama SENGAJA dibiarkan tanpa baris hari → itulah arti
-- "tidak dijadwalkan", dan di situlah Executive tinggal.
--
-- ⚠️ TIDAK MENGUBAH PERILAKU HARI INI. Mesin rekonsiliasi tak pernah membaca
-- konfigurasi hari untuk orang fleksibel (mereka tetap tak bisa alpa). Baris
-- hari ini menjadi DASAR bagi gerbang "hari ini jadwalmu libur, dalam rangka
-- apa?" dan bagi kalender agar menampilkan Libur alih-alih kosong.
--
-- Hari: 0=Minggu 1=Senin 2=Selasa 3=Rabu 4=Kamis 5=Jumat 6=Sabtu (dayOfWeek PHP).
-- Jam dibiarkan NULL: orang fleksibel tidak dinilai jamnya.
--
-- Idempoten & aman diulang.

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Dua aturan baru (kalau belum ada)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `att_work_rule` (`nama`, `tipe`, `keterangan`, `created_at`, `updated_at`)
SELECT 'Fleksibel Kantor', 'fleksibel',
       'Orang kantor/ABG — libur Minggu & Senin. Jam tidak dinilai.', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `att_work_rule`) x WHERE x.`nama` = 'Fleksibel Kantor');

INSERT INTO `att_work_rule` (`nama`, `tipe`, `keterangan`, `created_at`, `updated_at`)
SELECT 'Fleksibel Operasional', 'fleksibel',
       'Penyelia operasional — ikut klinik: MASUK hari Minggu, libur Senin. Jam tidak dinilai.', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `att_work_rule`) x WHERE x.`nama` = 'Fleksibel Operasional');

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Hari untuk "Fleksibel Kantor" — libur Minggu(0) & Senin(1)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `att_work_rule_day` (`rule_id`, `day`, `libur`, `jam_masuk_awal`, `jam_masuk_akhir`, `jam_pulang`, `jam_lembur_mulai`)
SELECT r.`id`, d.`day`, d.`libur`, NULL, NULL, NULL, NULL
FROM `att_work_rule` r
JOIN (
            SELECT 0 AS `day`, 1 AS `libur`
  UNION ALL SELECT 1, 1
  UNION ALL SELECT 2, 0
  UNION ALL SELECT 3, 0
  UNION ALL SELECT 4, 0
  UNION ALL SELECT 5, 0
  UNION ALL SELECT 6, 0
) d
WHERE r.`nama` = 'Fleksibel Kantor'
  AND NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `att_work_rule_day`) x
    WHERE x.`rule_id` = r.`id` AND x.`day` = d.`day`
  );

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Hari untuk "Fleksibel Operasional" — libur SENIN saja, Minggu MASUK
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `att_work_rule_day` (`rule_id`, `day`, `libur`, `jam_masuk_awal`, `jam_masuk_akhir`, `jam_pulang`, `jam_lembur_mulai`)
SELECT r.`id`, d.`day`, d.`libur`, NULL, NULL, NULL, NULL
FROM `att_work_rule` r
JOIN (
            SELECT 0 AS `day`, 0 AS `libur`
  UNION ALL SELECT 1, 1
  UNION ALL SELECT 2, 0
  UNION ALL SELECT 3, 0
  UNION ALL SELECT 4, 0
  UNION ALL SELECT 5, 0
  UNION ALL SELECT 6, 0
) d
WHERE r.`nama` = 'Fleksibel Operasional'
  AND NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `att_work_rule_day`) x
    WHERE x.`rule_id` = r.`id` AND x.`day` = d.`day`
  );

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. Pemetaan ROLE → aturan default (yang tampil & bisa diubah di halaman
--    Aturan Kerja, tab Penugasan). Inilah pengganti "daftar nama".
--    Role yang tak disebut di sini tidak disentuh.
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `group_path` g
JOIN `att_work_rule` r ON r.`nama` = 'Fleksibel Kantor'
SET g.`att_default_rule_id` = r.`id`
WHERE g.`nama` IN ('Head IT', 'IT', 'Finance', 'Head Marketing', 'Creative', 'Content Creator');

UPDATE `group_path` g
JOIN `att_work_rule` r ON r.`nama` = 'Fleksibel Operasional'
SET g.`att_default_rule_id` = r.`id`
WHERE g.`nama` IN ('Supervisor', 'Coordinator Dentist');

-- Executive sengaja TANPA default: tidak dijadwalkan sama sekali.
UPDATE `group_path` SET `att_default_rule_id` = NULL WHERE `nama` = 'Executive';

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. Terapkan ke karyawan yang SUDAH ADA, berdasarkan ROLE-nya.
--    Berlaku mulai HARI INI (masa lalu sudah ternilai — jangan diubah surut).
--    Hanya menyentuh orang yang aturannya belum sesuai default rolenya, dan
--    hanya kalau belum ada penugasan yang mulai hari ini/nanti (biar tak
--    menabrak penugasan masa depan yang sudah diketik manual).
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `att_user_rule` (`user_id`, `rule_id`, `effective_from`, `created_at`, `updated_at`)
SELECT u.`id`, g.`att_default_rule_id`, CURDATE(), NOW(), NOW()
FROM `users` u
JOIN `group_path` g ON g.`id` = u.`group_id`
WHERE u.`status` = 1
  AND g.`att_default_rule_id` IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `att_user_rule`) a
    WHERE a.`user_id` = u.`id` AND a.`effective_from` >= CURDATE()
  )
  AND COALESCE((
    SELECT b.`rule_id` FROM (SELECT * FROM `att_user_rule`) b
    WHERE b.`user_id` = u.`id` ORDER BY b.`effective_from` DESC LIMIT 1
  ), 0) <> g.`att_default_rule_id`;

COMMIT;

-- ============================================================================
-- VERIFIKASI:
--   SELECT u.nama, g.nama AS jabatan, r.nama AS aturan, ur.effective_from
--   FROM att_user_rule ur
--   JOIN users u ON u.id = ur.user_id
--   JOIN group_path g ON g.id = u.group_id
--   JOIN att_work_rule r ON r.id = ur.rule_id
--   WHERE u.status = 1 ORDER BY g.nama, u.nama;
--     → ABG di "Fleksibel Kantor", Anisa & Irma di "Fleksibel Operasional",
--       Executive tetap "Fleksibel" (tanpa jadwal), FO/Asdent/GS tak berubah.
--
--   SELECT r.nama, d.day, d.libur FROM att_work_rule_day d
--   JOIN att_work_rule r ON r.id = d.rule_id WHERE r.tipe = 'fleksibel'
--   ORDER BY r.nama, d.day;
--     → Kantor: hari 0 & 1 libur. Operasional: hanya hari 1 libur.
-- ============================================================================
