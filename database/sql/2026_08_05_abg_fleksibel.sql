-- ════════════════════════════════════════════════════════════════════════════
-- Orang kantor masuk ABG — aturan kerja Fleksibel untuk 6 orang
-- ════════════════════════════════════════════════════════════════════════════
-- Keputusan Ekky 2026-08-05: "Dani, Hari, Robi masukin aja ke ABG, dan semua
-- yang di ABG itu fleksibel."
--
-- MASALAH YANG DIPERBAIKI: enam orang ini **tidak punya aturan kerja sama
-- sekali**, dan daftar Kehadiran dibangun DARI `att_user_rule`. Artinya mereka
-- tak muncul di halaman Kehadiran mana pun — bukan cuma tidak ada di ABG.
-- Mereka bisa absen (Yovanka & Talitha sudah absen 5 Agu pagi) tapi
-- kehadirannya tak pernah dinilai: tak dihitung hadir, telat, maupun alpa.
--
-- Lebih buruk lagi, mereka justru KENA FLAG `ip_asing` — pengecualian flag
-- hanya berlaku bagi yang beraturan `fleksibel` (AttendanceHpAbsen::absen →
-- isFleksibel). Della & Ikhsan tidak kena flag persis karena sudah Fleksibel.
-- Pagi 5 Agu, Yovanka dan Talitha kena flag palsu gara-gara ini.
--
-- Grup "Kantor (ABG)" DITURUNKAN dari `att_work_rule.tipe = 'fleksibel'`
-- (lihat komentar AbsensiKehadiranController) — jadi memberi aturan Fleksibel
-- otomatis memasukkan mereka ke ABG, tanpa menyentuh kode sama sekali.
--
-- Rule id di-resolve BY NAMA ('Fleksibel'), bukan angka — id bisa beda antar
-- environment. Orang di-resolve by EMAIL, bukan nama (nama bisa berubah).
--
-- effective_from = 2026-08-01, disamakan dengan penghuni ABG yang sudah ada,
-- supaya rekap Agustus utuh sejak awal bulan.
--
-- Idempoten: NOT EXISTS mengecek apakah orangnya sudah punya aturan yang masih
-- berlaku — dijalankan dua kali tidak menggandakan.

START TRANSACTION;

INSERT INTO `att_user_rule` (`user_id`, `rule_id`, `effective_from`, `effective_to`, `created_at`, `updated_at`)
SELECT u.`id`, wr.`id`, '2026-08-01', NULL, NOW(), NOW()
FROM `users` u
JOIN `att_work_rule` wr ON wr.`nama` = 'Fleksibel'
WHERE u.`status` = 1
  AND u.`email` IN (
        'yovanka@sweettreats.id',   -- Head Marketing
        'talitha@sweettreats.id',   -- Content Creator
        'alif@sweettreats.id',      -- Creative
        'dani@stdc.id',             -- Executive  (⚠️ domain @stdc.id, bukan
        'hari@stdc.id',             -- Executive   @sweettreats.id — dicek dulu
        'robi@stdc.id'              -- Executive   supaya tak no-op senyap)
      )
  AND NOT EXISTS (
        SELECT 1 FROM `att_user_rule` x
         WHERE x.`user_id` = u.`id`
           AND (x.`effective_to` IS NULL OR x.`effective_to` >= CURDATE())
      );

COMMIT;

-- ============================================================================
-- VERIFIKASI setelah jalan (JANGAN DILEWATI):
--   1. Siapa saja penghuni ABG sekarang (harus 9: 3 lama + 6 baru):
--      SELECT u.nama, wr.nama AS aturan, wr.tipe
--        FROM att_user_rule ur
--        JOIN users u ON u.id = ur.user_id
--        JOIN att_work_rule wr ON wr.id = ur.rule_id
--       WHERE wr.tipe = 'fleksibel' AND u.status = 1
--         AND (ur.effective_to IS NULL OR ur.effective_to >= CURDATE())
--       ORDER BY u.nama;
--   2. Tak ada orang NYATA yang tersisa tanpa aturan (akun peran bersama —
--      Admin*, Sales*, Executive Pamulang — memang TIDAK boleh punya aturan,
--      itu akun jabatan, bukan orang):
--      SELECT u.nama, g.nama AS role FROM users u
--        LEFT JOIN group_path g ON g.id = u.group_id
--       WHERE u.status = 1
--         AND NOT EXISTS (SELECT 1 FROM att_user_rule x WHERE x.user_id = u.id)
--       ORDER BY u.nama;
--   3. Buka /absensi/kehadiran → grup "Kantor (ABG)" berisi 9 orang.
-- ============================================================================
