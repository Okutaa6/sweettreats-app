-- Bili (Muhammad Bili Ibrohim) — tutup masa kerja & buang jejak palsu
-- SUDAH DIJALANKAN di prod 23 Sep 2026 oleh HUB. Berkas ini = catatan + cara mengulang.
--
-- Masa kerja berakhir 14 Sep 2026 (Ikhsan: "tanggal 15 sudah tidak kerja").
-- Akun baru dinonaktifkan 17 Sep → celah 2 hari itu yang melahirkan data palsu.
--
-- DUA lubang, bukan satu — keduanya di UserManagement::toggleStatus() (IT-2026-044):
--   1. att_user_rule tidak ditutup      → AttendanceReconciler terus cetak att_day = alpa
--   2. sys_user_position menyisakan 1 baris terbuka → cron checklist cetak "missed"
--
-- Diperiksa sebelum menulis: tidak ada periode gaji September terdaftar
-- (att_period hanya 21/07–20/08, open) → nol baris beku. Baris yang dihapus
-- semuanya nol scan, nol lembur disetujui, nol item checklist, nol audit log.
-- Cadangan JSON ada di ~/cadangan-bili-*.json di server prod.

SET @BILI  = 'a2a3cec9-f686-47b2-a5ed-ffa4a2234765';
SET @AKHIR = '2026-09-14';

-- 1. Absensi
UPDATE att_user_rule SET effective_to = @AKHIR, updated_at = NOW()
 WHERE user_id = @BILI AND effective_to IS NULL;                      -- 1 baris
DELETE FROM att_day WHERE user_id = @BILI AND work_date > @AKHIR;     -- 7 baris (15,16,17,18,19,20,22 Sep)

-- 2. Checklist
DELETE FROM sys_checklist_submission
 WHERE user_id = @BILI AND submission_date > @AKHIR;                  -- 2 baris (15,16 Sep, keduanya missed 0/0)
UPDATE sys_user_position SET effective_to = @AKHIR, updated_at = NOW()
 WHERE user_id = @BILI AND effective_to IS NULL;                      -- 1 baris

-- 3. Verifikasi — semua WAJIB 0
SELECT COUNT(*) AS alpa_tersisa FROM att_day WHERE user_id = @BILI AND status = 'alpa';
SELECT COUNT(*) AS nonaktif_aturan_terbuka FROM users u
  JOIN att_user_rule r ON r.user_id = u.id WHERE u.status = 0 AND r.effective_to IS NULL;
SELECT COUNT(*) AS nonaktif_posisi_terbuka FROM users u
  JOIN sys_user_position p ON p.user_id = u.id WHERE u.status = 0 AND p.effective_to IS NULL;
SELECT COUNT(*) AS missed_milik_nonaktif FROM sys_checklist_submission s
  JOIN users u ON u.id = s.user_id WHERE u.status = 0 AND s.status = 'missed';

-- Hasil 23 Sep sesudah dijalankan: 0 · 0 · 0 · 0.
-- att_day Bili tersisa 12 baris, terakhir 13 Sep (hadir). Nol alpa.
--
-- Sisa kecil yang SENGAJA dibiarkan: posisi kedua Bili berakhir 16 Sep (dua hari
-- sesudah masa kerja) — warisan toggleStatus yang memakai "kemarin". Tak berakibat
-- apa pun sekarang; jangan diutak-atik tanpa alasan.
