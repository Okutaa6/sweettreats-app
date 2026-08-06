-- ════════════════════════════════════════════════════════════════════════════
-- Tabel submit per-hari Form Mingguan — 2026-08-06  (idempoten)
-- Mirror SQL dari migration 2026_08_05_120000 (create) + 2026_08_05_140000
-- (kolom review). Prod di-apply lewat file SQL manual (bukan artisan migrate),
-- jadi tabel ini WAJIB dibuat di prod lewat file ini SEBELUM:
--   • kode review per-hari naik, dan
--   • 2026_08_06_weekly_day_submit_backfill.sql dijalankan.
-- DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
-- ════════════════════════════════════════════════════════════════════════════
-- 1 baris = 1 hari yang sudah disubmit pada minggu tsb. Hari yang punya baris
-- di sini terkunci (kecuali review_status='rejected' → dibuka lagi utk perbaiki).
-- Header mingguan (sys_checklist_weekly_submission.status) di-roll-up dari
-- kumpulan review_status ini (ChecklistWeeklySubmission::recomputeStatus).
--
--   review_status : pending (default saat dikirim) | approved | rejected.
--   reviewer_user_id / reviewed_at / review_notes : jejak review supervisor per hari.
--
-- Collation utf8mb4_unicode_ci — samain `users`/`sys_checklist_weekly_submission`
-- (JOIN tanpa "illegal mix of collations"). Idempotent: CREATE IF NOT EXISTS.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `sys_checklist_weekly_day_submit` (
  `id`               varchar(255) NOT NULL,
  `submission_id`    varchar(255) NOT NULL              COMMENT 'FK sys_checklist_weekly_submission.id',
  `day_of_week`      tinyint(4)   NOT NULL              COMMENT 'ISO 2=Selasa … 7=Minggu (Senin=1 tak dipakai)',
  `note`             varchar(1000) DEFAULT NULL,
  `review_status`    varchar(16)  NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected',
  `reviewer_user_id` varchar(255) DEFAULT NULL          COMMENT 'FK users.id — supervisor yang mereview hari ini',
  `review_notes`     varchar(500) DEFAULT NULL          COMMENT 'catatan supervisor (wajib saat menolak)',
  `reviewed_at`      timestamp    NULL DEFAULT NULL,
  `submitted_at`     timestamp    NULL DEFAULT NULL,
  `created_at`       timestamp    NULL DEFAULT NULL,
  `updated_at`       timestamp    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `weekly_day_submit_unique` (`submission_id`, `day_of_week`),
  KEY `idx_wds_submission` (`submission_id`),
  KEY `idx_wds_review_status` (`review_status`),
  KEY `idx_wds_reviewer` (`reviewer_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
