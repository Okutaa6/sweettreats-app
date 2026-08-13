-- ═══════════════════════════════════════════════════════════════════════════
-- Aturan kerja BAWAAN untuk role operasional — 2026-08-13
--
-- MASALAH yang ditutup:
--   `UserManagement::store()` memasang aturan kerja untuk akun baru dengan
--   membaca `group_path.att_default_rule_id`. Ketiga role operasional
--   (Front Office, Assistant Dentist, General Services) kolom itu KOSONG,
--   jadi akun yang dibuat lewat menu admin lahir TANPA aturan kerja.
--
--   Akibatnya bukan sekadar "kurang lengkap": mesin rekonsiliasi hanya
--   menyapa orang yang punya baris `att_user_rule`. Orang tanpa aturan
--   TIDAK PERNAH muncul di rekap absensi — tak hadir, tak alpa, tak ada.
--   Hilang diam-diam, dan baru ketahuan kalau ada yang mencarinya.
--
--   Kejadian nyata: Alpajri (GS Bintaro) absen tiap hari selama seminggu
--   tanpa sekali pun dinilai, sampai ketahuan saat audit 11 Agu.
--
-- Dipetakan lewat NAMA role & NAMA aturan, bukan id — id berbeda antar
-- lingkungan. Idempoten: aman dijalankan ulang.
-- ═══════════════════════════════════════════════════════════════════════════

UPDATE `group_path` g
JOIN `att_work_rule` r
  ON r.`nama` = CASE g.`nama`
        WHEN 'Front Office'      THEN 'FO-Asdent'
        WHEN 'Assistant Dentist' THEN 'FO-Asdent'
        WHEN 'General Services'  THEN 'GS'
     END
SET g.`att_default_rule_id` = r.`id`
WHERE g.`nama` IN ('Front Office', 'Assistant Dentist', 'General Services')
  AND (g.`att_default_rule_id` IS NULL OR g.`att_default_rule_id` <> r.`id`);

-- ── Catatan sengaja ────────────────────────────────────────────────────────
-- Role KANTOR (Creative, Finance, Head Marketing, dst) sengaja TIDAK diisi.
-- Mereka beraturan fleksibel, dan penugasannya lebih sering perkecualian
-- daripada pola — memberi bawaan justru membuat orang berhenti memikirkannya.
-- Yang dibawakan cuma tiga role yang aturannya memang seragam per peran.
--
-- Role "Dokter" (kalau nanti dibuat) TIDAK boleh diberi bawaan `FO-Asdent`
-- atau `GS`. Jam kerja dokter datang dari jadwal praktik, bukan pola mingguan.
