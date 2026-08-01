-- ════════════════════════════════════════════════════════════════════════════
-- Rekonsiliasi Absensi — aturan kerja & exception (Fase B)
-- ════════════════════════════════════════════════════════════════════════════
-- Tujuan: sumber "jam kerja SEHARUSNYA" yang diadu sama scan. Empat tabel:
--         1. att_work_rule            — jenis aturan (baku / fleksibel)
--         2. att_work_rule_day        — jam baku PER HARI dalam seminggu
--         3. att_work_rule_exception  — override per tanggal (Lebaran, event, dll)
--         4. att_user_rule            — siapa pakai aturan apa (per ORANG)
--
-- Konteks penuh: docs/rencana/rencana-rekonsiliasi-absensi.md §3, §5.2, §5.3
--
-- Keputusan desain yg dibuktikan dari data (jangan diubah tanpa baca plan):
--   • Aturan nempel ke ORANG (att_user_rule), BUKAN ke divisi/cabang. Bukti: Irma
--     & Anisa office tapi ikut jam operasional (§3.4); Bintaro campur baku+fleksibel
--     dalam satu mesin (§2.6).
--   • Jam baku PER HARI (att_work_rule_day.day). Bukti: Senin tutup, Minggu lebih
--     pendek (§2.6, §2.9). Senin-tutup = pola MINGGUAN → di sini, bukan exception.
--   • att_work_rule_exception WAJIB. Bukti: Lebaran geser ~11 hari/tahun, tak bisa
--     jadi aturan berulang (§2.10); akreditasi Sunter Mar-Jun (§2.12). Tanpa ini,
--     tiap Lebaran seluruh karyawan 5 cabang ke-flag alpa seminggu.
--   • Window check-in (jam_masuk_awal..jam_masuk_akhir), bukan satu titik. Bukti:
--     SOP GS 08:45-09:00, FO/Asdent 09:50-10:00 (§3.1).
--   • Telat mendorong jam pulang (dihitung di engine, bukan di sini): telat 8 menit
--     -> wajib pulang jam_pulang + 8 (§3.2).
--
-- Yang MASIH pending (isi belakangan, nggak ngubah skema — §8):
--   • Jam Minggu spesifik (sementara = sama dgn hari lain).
--   • SOP poin 4 "GS < 08:45 = lembur" vs "dateng awal ga dihitung" (§8 #2).
--   • Tarif lembur hari libur (§8 #3).
--
-- Collation utf8mb4_unicode_ci — samain users/raw_branch buat JOIN.
-- Idempotent: CREATE IF NOT EXISTS. Seeding menyusul terpisah.
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. att_work_rule — jenis aturan
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_work_rule` (
  `id`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`        varchar(100) NOT NULL              COMMENT 'mis. "GS", "FO-Asdent", "Fleksibel"',
  `tipe`        enum('baku','fleksibel') NOT NULL  COMMENT 'baku=dinilai telat/alpa dari scan; fleksibel=scan cuma penanda, tak dinilai',
  `keterangan`  varchar(255) DEFAULT NULL,
  `created_at`  timestamp NULL DEFAULT NULL,
  `updated_at`  timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rule_nama` (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. att_work_rule_day — jam baku per hari dalam seminggu
--    day: 0=Minggu, 1=Senin, ... 6=Sabtu (ikut Carbon::dayOfWeek)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_work_rule_day` (
  `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_id`          bigint(20) UNSIGNED NOT NULL,
  `day`              tinyint(4) NOT NULL               COMMENT '0=Minggu .. 6=Sabtu (Carbon dayOfWeek)',
  `libur`            tinyint(1) NOT NULL DEFAULT 0      COMMENT '1 = hari tutup (mis. Senin)',
  `jam_masuk_awal`   time DEFAULT NULL                 COMMENT 'batas bawah window check-in',
  `jam_masuk_akhir`  time DEFAULT NULL                 COMMENT 'batas atas window; scan setelah ini = telat',
  `jam_pulang`       time DEFAULT NULL,
  `jam_lembur_mulai` time DEFAULT NULL                 COMMENT 'setelah grace istirahat (GS 18:00, FO 19:00)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ruleday` (`rule_id`, `day`),
  CONSTRAINT `fk_ruleday_rule` FOREIGN KEY (`rule_id`) REFERENCES `att_work_rule` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. att_work_rule_exception — override per tanggal
--    raw_branch_id NULL = berlaku SEMUA cabang (mis. Lebaran nasional).
--    Menang atas att_work_rule_day untuk tanggal dalam rentang.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_work_rule_exception` (
  `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `raw_branch_id`  varchar(255) DEFAULT NULL           COMMENT 'NULL = semua cabang',
  `tgl_from`       date NOT NULL,
  `tgl_to`         date NOT NULL,
  `libur`          tinyint(1) NOT NULL DEFAULT 1       COMMENT '1 = tutup (Lebaran); 0 = tetap kerja tapi jam beda (event)',
  `jam_masuk`      time DEFAULT NULL                   COMMENT 'override kalau libur=0',
  `jam_pulang`     time DEFAULT NULL,
  `alasan`         varchar(255) NOT NULL,
  `dibuat_oleh`    varchar(255) DEFAULT NULL           COMMENT 'FK users.id — jejak siapa yg deklarasi',
  `created_at`     timestamp NULL DEFAULT NULL,
  `updated_at`     timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_exc_branch_tgl` (`raw_branch_id`, `tgl_from`, `tgl_to`),
  CONSTRAINT `fk_exc_branch` FOREIGN KEY (`raw_branch_id`) REFERENCES `raw_branch` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exc_user`   FOREIGN KEY (`dibuat_oleh`)   REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. att_user_rule — siapa pakai aturan apa (history-aware)
--    effective_from/to biar saat orang pindah divisi/aturan, penilaian bulan
--    lama tetap pakai aturan yg berlaku saat itu (mirip sys_user_position).
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_user_rule` (
  `id`              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         varchar(255) NOT NULL,
  `rule_id`         bigint(20) UNSIGNED NOT NULL,
  `effective_from`  date NOT NULL,
  `effective_to`    date DEFAULT NULL                  COMMENT 'NULL = masih berlaku',
  `created_at`      timestamp NULL DEFAULT NULL,
  `updated_at`      timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_userrule_user` (`user_id`, `effective_from`),
  KEY `idx_userrule_rule` (`rule_id`),
  CONSTRAINT `fk_userrule_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_userrule_rule` FOREIGN KEY (`rule_id`) REFERENCES `att_work_rule` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
