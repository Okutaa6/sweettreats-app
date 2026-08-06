-- ════════════════════════════════════════════════════════════════════════════
-- Tabel permintaan "Buka Kembali" checklist lampau — 2026-08-06  (idempoten)
-- Prod di-apply lewat file SQL manual (bukan artisan migrate), jadi tabel ini
-- WAJIB dibuat di prod lewat file ini SEBELUM kode reopen-request naik.
-- DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
-- ════════════════════════════════════════════════════════════════════════════
-- 1 baris = 1 permintaan karyawan untuk membuka kembali tanggal/hari yang sudah
-- lewat supaya bisa diisi & dikirim. Perlu persetujuan atasan (dashboard
-- monitoring) sebelum benar-benar terbuka.
--
--   scope           : daily  → target_date terisi (checklist harian).
--                     weekly → week_start_date + day_of_week terisi (Form Mingguan per-hari).
--   status          : pending (default) | approved | rejected.
--   fulfilled_at    : diisi saat karyawan akhirnya mengirim → "grant" habis, hari
--                     terkunci lagi. GRANT AKTIF = status='approved' AND fulfilled_at IS NULL.
--   reviewer_user_id/review_notes/reviewed_at : jejak keputusan atasan.
--
-- Collation utf8mb4_unicode_ci — samain `users`/`sys_checklist_submission`
-- (JOIN tanpa "illegal mix of collations"). Idempotent: CREATE IF NOT EXISTS.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `sys_checklist_reopen_request` (
  `id`               varchar(255) NOT NULL,
  `user_id`          varchar(255) NOT NULL              COMMENT 'FK users.id — karyawan pengaju',
  `division_id`      varchar(32)  NOT NULL,
  `raw_branch_id`    varchar(255) DEFAULT NULL,
  `scope`            varchar(8)   NOT NULL              COMMENT 'daily | weekly',
  `target_date`      date         DEFAULT NULL          COMMENT 'scope=daily: tanggal yang diminta',
  `week_start_date`  date         DEFAULT NULL          COMMENT 'scope=weekly: Senin minggu tsb',
  `day_of_week`      tinyint(4)   DEFAULT NULL          COMMENT 'scope=weekly: ISO 2..7',
  `reason`           varchar(500) NOT NULL              COMMENT 'alasan karyawan',
  `status`           varchar(16)  NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected',
  `reviewer_user_id` varchar(255) DEFAULT NULL          COMMENT 'FK users.id — atasan yang memutuskan',
  `review_notes`     varchar(500) DEFAULT NULL          COMMENT 'catatan atasan (wajib saat menolak)',
  `reviewed_at`      timestamp    NULL DEFAULT NULL,
  `fulfilled_at`     timestamp    NULL DEFAULT NULL     COMMENT 'saat karyawan akhirnya mengirim → grant habis',
  `created_at`       timestamp    NULL DEFAULT NULL,
  `updated_at`       timestamp    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reopen_user` (`user_id`),
  KEY `idx_reopen_status` (`status`),
  KEY `idx_reopen_daily` (`user_id`, `scope`, `target_date`),
  KEY `idx_reopen_weekly` (`user_id`, `scope`, `week_start_date`, `day_of_week`),
  KEY `idx_reopen_branch` (`raw_branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
