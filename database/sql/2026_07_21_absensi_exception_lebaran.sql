-- ════════════════════════════════════════════════════════════════════════════
-- Rekonsiliasi Absensi — seed exception LEBARAN (Fase B, §2.10)
-- ════════════════════════════════════════════════════════════════════════════
-- Lebaran = libur tahunan 6–10 hari, 5 cabang, geser ~11 hari/tahun (Hijriah vs
-- Masehi). TIDAK bisa ditulis sebagai aturan berulang — 2021 jatuh Mei, 2026 Maret.
-- Tanpa exception ini, TIAP Lebaran engine nandai SELURUH karyawan 5 cabang ALPA
-- selama seminggu → sistem "rusak di tahun kedua". Ini yg bikin exception WAJIB.
--
-- Sumber tanggal: riwayat 5 tahun mesin Bintaro (docs/rencana/rencana-rekonsiliasi-absensi.md §2.10).
-- raw_branch_id NULL = nasional (semua cabang). libur=1 = tutup.
-- AttendanceReconciler::resolveDay() → exception cabang-NULL menang atas aturan harian.
--
-- Akreditasi Sunter Mar–Jun 2026 SENGAJA TIDAK di-seed: itu konteks lembur (dari
-- form), bukan perubahan hari kerja. Seed libur=0 malah bikin Sunter Senin jadi
-- hari kerja → staff tak-terjadwal ke-tag alpa palsu (§10, jangan simpulkan
-- kebijakan dari data).
--
-- Idempotent: hapus dulu baris Lebaran nasional, lalu insert ulang.
-- ════════════════════════════════════════════════════════════════════════════

DELETE FROM `att_work_rule_exception`
 WHERE `raw_branch_id` IS NULL AND `alasan` LIKE 'Lebaran %';

INSERT INTO `att_work_rule_exception`
  (`raw_branch_id`, `tgl_from`, `tgl_to`, `libur`, `jam_masuk`, `jam_pulang`, `alasan`, `dibuat_oleh`, `created_at`, `updated_at`)
VALUES
  (NULL, '2021-05-10', '2021-05-17', 1, NULL, NULL, 'Lebaran 2021', NULL, NOW(), NOW()),
  (NULL, '2022-05-01', '2022-05-07', 1, NULL, NULL, 'Lebaran 2022', NULL, NOW(), NOW()),
  (NULL, '2023-04-19', '2023-04-24', 1, NULL, NULL, 'Lebaran 2023', NULL, NOW(), NOW()),
  (NULL, '2024-04-08', '2024-04-16', 1, NULL, NULL, 'Lebaran 2024', NULL, NOW(), NOW()),
  (NULL, '2025-03-29', '2025-04-07', 1, NULL, NULL, 'Lebaran 2025', NULL, NOW(), NOW()),
  (NULL, '2026-03-19', '2026-03-24', 1, NULL, NULL, 'Lebaran 2026', NULL, NOW(), NOW());
