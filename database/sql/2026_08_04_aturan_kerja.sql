-- ════════════════════════════════════════════════════════════════════════════
-- Aturan Kerja — UI kelola jam kerja, penugasan aturan, kalender libur
-- ════════════════════════════════════════════════════════════════════════════
-- Fitur ini CUMA menulis ke tabel aturan yang mesin rekonsiliasi BACA
-- (att_work_rule / _day / _exception / att_user_rule — sudah ada sejak
-- 2026_07_18_absensi_work_rule.sql). Mesin (AttendanceReconciler, att_day)
-- TIDAK disentuh. Rencana: docs/rencana/rencana-aturan-kerja.md.
--
-- File ini menambah TIGA hal:
--   1. att_rule_log            — jejak perubahan (siapa, kapan, dari→ke).
--                                Tabel sendiri, JANGAN numpang audit_log login
--                                (rencana §3).
--   2. group_path.att_default_rule_id — aturan default per role saat akun baru
--                                dibuat di Pusat Akun (rencana Tab 2). Soft
--                                reference ke att_work_rule.id (tanpa FK,
--                                kolom ditambah kondisional).
--   3. Node menu "Aturan Kerja" + grant Supervisor / Head IT / Finance /
--      Super Admin (Supervisor ditambah Ekky 2026-08-04).
--
-- ⚠️ SETELAH file ini WAJIB `php artisan access:rebuild`.
-- Idempotent: CREATE IF NOT EXISTS, ALTER kondisional, INSERT ber-NOT EXISTS.
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. att_rule_log — jejak perubahan aturan kerja
--    Append-only. aksi: rule.buat / rule.ubah / rule.hapus / penugasan /
--    penugasan.default-role / libur.buat / libur.hapus.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_rule_log` (
  `id`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id`    varchar(255) DEFAULT NULL      COMMENT 'FK users.id — siapa yang mengubah',
  `actor_nama`  varchar(191) DEFAULT NULL      COMMENT 'snapshot nama (kalau user di-rename/hapus, jejak tetap terbaca)',
  `aksi`        varchar(40) NOT NULL           COMMENT 'rule.buat|rule.ubah|rule.hapus|penugasan|penugasan.default-role|libur.buat|libur.hapus',
  `target`      varchar(191) NOT NULL          COMMENT 'yang diubah: nama aturan / nama karyawan / alasan libur',
  `detail`      text DEFAULT NULL              COMMENT 'JSON dari→ke',
  `created_at`  timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rulelog_waktu` (`created_at`),
  CONSTRAINT `fk_rulelog_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. group_path.att_default_rule_id — default aturan per role
--    Akun baru di Pusat Akun dengan role ini otomatis dapat att_user_rule
--    (mis. Front Office → FO-Asdent). Override per orang tetap lewat Tab
--    Penugasan (kasus Anisa/Irma). NULL = tidak otomatis.
-- ─────────────────────────────────────────────────────────────────────────────
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'group_path'
                 AND COLUMN_NAME = 'att_default_rule_id');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `group_path`
        ADD COLUMN `att_default_rule_id` bigint(20) UNSIGNED NULL DEFAULT NULL
            COMMENT 'aturan kerja default saat akun baru dibuat (soft ref att_work_rule.id); NULL = tidak otomatis'",
    "SELECT 'group_path.att_default_rule_id already exists, skipping' AS info");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Node menu "Aturan Kerja" di bawah parent Absensi
--    Gate sebenarnya di controller (Supervisor + Head IT + Finance + Super
--    Admin — AturanKerjaController::guard); node menu supaya muncul di
--    sidebar orang yang berhak. urutan 8 = setelah Lembar Gaji (7).
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `access_path` (`id`, `pid`, `nama`, `icon`, `urutan`, `urutan_path`, `link`, `pid_path`) VALUES
  ('abs-aturan-00000000000', 'abs-parent-000000000000', 'Aturan Kerja', 'icon-fa fas fa-business-time', 8, NULL, 'absensi/aturan', NULL);

-- Grant node — role BY NAME (id role beda antar environment, jangan hardcode).
-- group_access TAK punya UNIQUE (group_id, access_id) → wajib guard NOT EXISTS.
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-aturan-g-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'abs-aturan-00000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Supervisor', 'Head IT', 'Finance', 'Super Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'abs-aturan-00000000000');

-- Parent Absensi — tanpa ini node anak tak muncul di sidebar.
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-aturan-p-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'abs-parent-000000000000'
FROM `group_path` gp
WHERE gp.nama IN ('Supervisor', 'Head IT', 'Finance', 'Super Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = 'abs-parent-000000000000');

-- AuthGuard meloloskan /absensi/* hanya kalau role pegang node ber-link
-- 'absensi' (pembanding segment-1). Node pembawa link BEDA antar environment
-- → grant BY LINK, bukan by id (jebakan dev 2026-07-24).
INSERT IGNORE INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('abs-aturan-s-', SUBSTRING(MD5(CONCAT(gp.id, ap.id)), 1, 20)), gp.id, ap.id
FROM `group_path` gp
JOIN `access_path` ap ON ap.link = 'absensi'
WHERE gp.nama IN ('Supervisor', 'Head IT', 'Finance', 'Super Admin')
  AND NOT EXISTS (SELECT 1 FROM group_access gx WHERE gx.group_id = gp.id AND gx.access_id = ap.id);
