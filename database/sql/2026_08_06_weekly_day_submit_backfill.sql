-- ════════════════════════════════════════════════════════════════════════════
-- Backfill baris per-hari Form Mingguan — 2026-08-06  (idempoten)
-- Jalankan SETELAH 2026_08_06_weekly_day_submit_table.sql (yang membuat tabel
-- sys_checklist_weekly_day_submit di prod), dan SEBELUM kode review per-hari
-- naik prod. NB: di prod tabel dibuat via file SQL itu, BUKAN artisan migrate.
-- DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks: review Form Mingguan kini per-hari — semua aksi supervisor
-- (setujui-semua / tolak / reopen) dan badge antrian menghitung dari baris
-- sys_checklist_weekly_day_submit, dan status header di-roll-up darinya
-- (ChecklistWeeklySubmission::recomputeStatus / deriveStatus).
--
-- Alur LAMA menulis header status='submitted'/'approved' TANPA baris per-hari.
-- Tanpa backfill, minggu lama tak punya baris day_submit → tak bisa di-approve,
-- tak bisa ditolak, tak bisa di-reopen, tak muncul di antrian (badge 0), dan
-- recompute apa pun (mis. dari cuti backdate) menurunkannya ke 'draft'.
--
-- Backfill ini membuat 1 baris day_submit untuk tiap HARI AKTIF dari tiap
-- submission 'submitted'/'approved' yang belum punya barisnya.
--   HARI AKTIF = day_of_week (2..7) yang punya item DAN tidak dikecualikan
--                (padanan ChecklistWeeklySubmission::activeDayNumbers()).
--   review_status = 'approved' bila header approved, selain itu 'pending'.
--   reviewer_user_id / reviewed_at = disalin dari header HANYA bila approved
--                (jejak approval tetap utuh; deriveStatus() tetap 'approved').
--   submitted_at  = header.submitted_at (fallback updated_at bila null).
--
-- TIDAK membackfill 'draft'/'rejected' (keduanya isEditable → self-heal saat
-- karyawan mengisi ulang) maupun 'excused'/'missed' (recomputeStatus melewatinya).
--
-- Idempoten: guard NOT EXISTS per (submission_id, day_of_week) + DISTINCT →
-- re-run = 0 baris terpengaruh (juga dijaga unique weekly_day_submit_unique).
-- ════════════════════════════════════════════════════════════════════════════

INSERT INTO sys_checklist_weekly_day_submit
  (id, submission_id, day_of_week, note,
   review_status, reviewer_user_id, review_notes, reviewed_at,
   submitted_at, created_at, updated_at)
SELECT
  UUID(),
  d.submission_id,
  d.day_of_week,
  NULL,
  CASE WHEN d.status = 'approved' THEN 'approved' ELSE 'pending' END,
  CASE WHEN d.status = 'approved' THEN d.reviewer_user_id ELSE NULL END,
  NULL,
  CASE WHEN d.status = 'approved' THEN d.reviewed_at ELSE NULL END,
  COALESCE(d.submitted_at, d.updated_at),
  NOW(),
  NOW()
FROM (
  SELECT DISTINCT
    s.id               AS submission_id,
    it.day_of_week     AS day_of_week,
    s.status           AS status,
    s.reviewer_user_id AS reviewer_user_id,
    s.reviewed_at      AS reviewed_at,
    s.submitted_at     AS submitted_at,
    s.updated_at       AS updated_at
  FROM sys_checklist_weekly_submission s
  JOIN sys_checklist_weekly_submission_item it
    ON it.submission_id = s.id
  WHERE s.status IN ('submitted', 'approved')
    AND it.day_of_week BETWEEN 2 AND 7
    AND NOT EXISTS (
      SELECT 1 FROM sys_checklist_weekly_day_excuse ex
      WHERE ex.submission_id = s.id AND ex.day_of_week = it.day_of_week
    )
    AND NOT EXISTS (
      SELECT 1 FROM sys_checklist_weekly_day_submit ds
      WHERE ds.submission_id = s.id AND ds.day_of_week = it.day_of_week
    )
) d;

-- ════════════════════════════════════════════════════════════════════════════
-- Verifikasi:
--   -- Idealnya 0 setelah backfill (submitted/approved tanpa baris per-hari):
--   SELECT COUNT(*) FROM sys_checklist_weekly_submission s
--   WHERE s.status IN ('submitted','approved')
--     AND EXISTS (SELECT 1 FROM sys_checklist_weekly_submission_item it
--                 WHERE it.submission_id = s.id AND it.day_of_week BETWEEN 2 AND 7
--                   AND NOT EXISTS (SELECT 1 FROM sys_checklist_weekly_day_excuse ex
--                                   WHERE ex.submission_id = s.id AND ex.day_of_week = it.day_of_week))
--     AND NOT EXISTS (SELECT 1 FROM sys_checklist_weekly_day_submit ds
--                     WHERE ds.submission_id = s.id);
--   -- Re-run file ini → 0 baris terpengaruh (idempoten).
-- ════════════════════════════════════════════════════════════════════════════
