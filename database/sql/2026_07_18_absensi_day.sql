-- ════════════════════════════════════════════════════════════════════════════
-- Rekonsiliasi Absensi — hasil harian att_day (Fase B)
-- ════════════════════════════════════════════════════════════════════════════
-- Tujuan: HASIL rekonsiliasi, 1 baris per (user, hari kerja). TURUNAN — bisa
--         dihitung ulang kapan aja dari att_scan + aturan. Bukan sumber kebenaran.
--
-- Konteks: docs/rencana/rencana-rekonsiliasi-absensi.md §5.2, §5.3. Diisi oleh
--          App\Services\AttendanceReconciler (command: php artisan att:reconcile).
--
-- Catatan desain:
--   • work_date = HARI KERJA, bukan tanggal kalender scan. Scan dini hari (<06:00)
--     dilempar balik ke hari sebelumnya sebagai pulang (§2.8, Sunter jam 00:54).
--   • raw_branch_id = cabang scan PERTAMA, cuma buat pengelompokan laporan. Satu
--     hari kerja bisa lintas cabang (§2.7) → cabang_terlibat json simpan semuanya.
--   • wajib_pulang = jam_pulang baku + telat_menit (telat mendorong pulang, §3.2).
--   • lembur_menit_disetujui: 0 sampai form lembur ada (Fase C). Scan TIDAK pernah
--     bikin lembur otomatis (§3.2).
--   • status 'cuti'/'izin'/'wfh' baru terisi setelah att_request ada (Fase C).
--
-- Idempotent: CREATE IF NOT EXISTS. Reconciler upsert per (user_id, work_date).
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `att_day` (
  `id`                      bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`                 varchar(255) NOT NULL,
  `raw_branch_id`           varchar(255) DEFAULT NULL   COMMENT 'cabang scan pertama — grouping saja, BUKAN kebenaran',
  `work_date`               date NOT NULL,
  `rule_id`                 bigint(20) UNSIGNED DEFAULT NULL COMMENT 'snapshot aturan yg dipakai saat hitung',
  `scan_first`              datetime DEFAULT NULL,
  `scan_last`               datetime DEFAULT NULL,
  `scan_count`              int(11) NOT NULL DEFAULT 0,
  `single_scan`             tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = cuma 1 scan seharian (dianggap hadir, §3.2)',
  `cabang_terlibat`         json DEFAULT NULL            COMMENT 'daftar cabang yg terlibat di hari ini (bisa >1, §2.7)',
  `status`                  enum('hadir','telat','alpa','cuti','izin','libur','wfh') NOT NULL,
  `telat_menit`             int(11) NOT NULL DEFAULT 0,
  `wajib_pulang`            time DEFAULT NULL            COMMENT 'jam_pulang baku + telat_menit',
  `lembur_menit_disetujui`  int(11) NOT NULL DEFAULT 0,
  `computed_at`             timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_day_user_date` (`user_id`, `work_date`),
  KEY `idx_day_date_status` (`work_date`, `status`),
  KEY `idx_day_branch` (`raw_branch_id`),
  CONSTRAINT `fk_day_user`   FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_day_branch` FOREIGN KEY (`raw_branch_id`) REFERENCES `raw_branch` (`id`) ON DELETE SET NULL
  -- fk_day_rule (rule_id -> att_work_rule) SENGAJA TIDAK di sini.
  -- att_work_rule dibuat di 2026_07_18_absensi_work_rule.sql, yang urutan
  -- ALFABETNYA ADA DI BELAKANG file ini. Kalau FK-nya ditulis inline, perintah
  -- lazim `for f in database/sql/*.sql` gagal dgn error 1215 dan att_day tak
  -- pernah terbentuk — seluruh modul absensi mati sejak langkah pertama deploy.
  -- FK-nya dipasang di 2026_07_18_absensi_zz_fk.sql yang selalu jalan terakhir.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
