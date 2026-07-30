-- ============================================================================
-- 2026_07_23_att_work_rule_seed.sql
--
-- ISI aturan kerja + jadwal hariannya. Tanpa file ini, di server baru seluruh
-- modul absensi DIAM: `2026_07_18_absensi_work_rule.sql` cuma MEMBUAT tabelnya,
-- tak pernah mengisi. Akibatnya AttendanceReconciler::resolveRule() mengembalikan
-- null untuk semua orang, tiap hari di-`continue`, nol baris att_day terbentuk —
-- rekap Finance dan matriks Kehadiran kosong melompong tanpa satu pun pesan error.
--
-- Ditulis pakai NAMA aturan, bukan id auto-increment, supaya id server tak harus
-- sama dengan lokal. `att_work_rule.nama` sudah UNIQUE dan `att_work_rule_day`
-- unik per (rule_id, day) → aman dijalankan berulang.
--
-- day: 0=Minggu, 1=Senin, ... 6=Sabtu (cocok dgn Carbon::dayOfWeek).
-- Senin = hari tutup klinik.
--
-- Sumber angka: SOP yang dipakai di docs/rencana/rencana-rekonsiliasi-absensi.md §3.
-- ============================================================================

-- ── 1. Jenis aturan ─────────────────────────────────────────────────────────
INSERT INTO att_work_rule (nama, tipe, keterangan, created_at, updated_at) VALUES
  ('GS',        'baku',       'SOP: masuk 08:45-09:00, pulang 17:00, lembur mulai 18:00', NOW(), NOW()),
  ('FO-Asdent', 'baku',       'SOP: masuk 09:50-10:00, pulang 18:00, lembur mulai 19:00', NOW(), NOW()),
  ('Fleksibel', 'fleksibel',  'Office/management: scan cuma penanda, tak dinilai',        NOW(), NOW())
ON DUPLICATE KEY UPDATE
  tipe = VALUES(tipe), keterangan = VALUES(keterangan), updated_at = NOW();

-- ── 2. Jadwal per hari ──────────────────────────────────────────────────────
-- "Fleksibel" SENGAJA tak punya baris hari: jam kerjanya bebas, tak pernah
-- dinilai telat/alpa (§3.3). Jangan ditambahkan.
INSERT INTO att_work_rule_day (rule_id, day, libur, jam_masuk_awal, jam_masuk_akhir, jam_pulang, jam_lembur_mulai)
SELECT w.id, d.day, d.libur, d.awal, d.akhir, d.pulang, d.lembur
FROM (
              SELECT 'GS' AS rule_nama, 0 AS day, 0 AS libur, '08:45:00' AS awal, '09:00:00' AS akhir, '17:00:00' AS pulang, '18:00:00' AS lembur
    UNION ALL SELECT 'GS', 1, 1, NULL, NULL, NULL, NULL          -- Senin: tutup
    UNION ALL SELECT 'GS', 2, 0, '08:45:00', '09:00:00', '17:00:00', '18:00:00'
    UNION ALL SELECT 'GS', 3, 0, '08:45:00', '09:00:00', '17:00:00', '18:00:00'
    UNION ALL SELECT 'GS', 4, 0, '08:45:00', '09:00:00', '17:00:00', '18:00:00'
    UNION ALL SELECT 'GS', 5, 0, '08:45:00', '09:00:00', '17:00:00', '18:00:00'
    UNION ALL SELECT 'GS', 6, 0, '08:45:00', '09:00:00', '17:00:00', '18:00:00'

    UNION ALL SELECT 'FO-Asdent', 0, 0, '09:50:00', '10:00:00', '18:00:00', '19:00:00'
    UNION ALL SELECT 'FO-Asdent', 1, 1, NULL, NULL, NULL, NULL   -- Senin: tutup
    UNION ALL SELECT 'FO-Asdent', 2, 0, '09:50:00', '10:00:00', '18:00:00', '19:00:00'
    UNION ALL SELECT 'FO-Asdent', 3, 0, '09:50:00', '10:00:00', '18:00:00', '19:00:00'
    UNION ALL SELECT 'FO-Asdent', 4, 0, '09:50:00', '10:00:00', '18:00:00', '19:00:00'
    UNION ALL SELECT 'FO-Asdent', 5, 0, '09:50:00', '10:00:00', '18:00:00', '19:00:00'
    UNION ALL SELECT 'FO-Asdent', 6, 0, '09:50:00', '10:00:00', '18:00:00', '19:00:00'
) d
JOIN att_work_rule w ON w.nama = d.rule_nama
ON DUPLICATE KEY UPDATE
  libur = VALUES(libur), jam_masuk_awal = VALUES(jam_masuk_awal),
  jam_masuk_akhir = VALUES(jam_masuk_akhir), jam_pulang = VALUES(jam_pulang),
  jam_lembur_mulai = VALUES(jam_lembur_mulai);

-- ============================================================================
-- 3. att_user_rule — SENGAJA TIDAK DI-SEED. Isi manual, per ORANG.
-- ----------------------------------------------------------------------------
-- JANGAN menurunkan aturan dari jabatan/divisi. Aturan kerja nempel ke ORANG,
-- dan pengecualiannya nyata: Anisa Aulia (SPV Operasional) & Irma Islamiati
-- (Koordinator Asdent) itu office/manajemen TAPI ikut jam operasional dan punya
-- hak lembur — kalau diturunkan dari jabatan, lembur mereka ditolak sistem tiap
-- bulan padahal sah (Anisa Juni: 18 klaim / 69,4 jam, valid semua).
-- Lihat memory project-absensi-lintas-cabang §2 & §3.
--
-- Template (jalankan per orang, sesuaikan namanya):
--
--   INSERT INTO att_user_rule (user_id, rule_id, effective_from, created_at, updated_at)
--   SELECT u.id, w.id, '2026-01-01', NOW(), NOW()
--   FROM users u JOIN att_work_rule w ON w.nama = 'FO-Asdent'
--   WHERE u.nama = 'NAMA KARYAWAN'
--     AND NOT EXISTS (SELECT 1 FROM att_user_rule x WHERE x.user_id = u.id);
--
-- CEK SIAPA YANG BELUM PUNYA ATURAN (mereka tak akan muncul di rekap sama sekali,
-- dan scan-nya tak pernah jadi hari kerja — persis yang bikin 538 scan Head IT
-- hilang tanpa jejak):
--
--   SELECT u.nama, u.email FROM users u
--   LEFT JOIN att_user_rule ur ON ur.user_id = u.id
--   WHERE ur.id IS NULL AND u.status = 1 ORDER BY u.nama;
-- ============================================================================
