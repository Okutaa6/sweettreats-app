<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill baris per-hari Form Mingguan (mirror dari
 * database/sql/2026_08_06_weekly_day_submit_backfill.sql — di prod dijalankan
 * lewat file SQL itu; migration ini untuk parity/dev).
 *
 * Alur review LAMA menulis header status='submitted'/'approved' TANPA baris
 * sys_checklist_weekly_day_submit. Logika review per-hari yang baru (aksi
 * supervisor, badge antrian, recomputeStatus) berasumsi baris itu ada — tanpa
 * backfill, minggu lama terkunci total & tak masuk antrian mana pun.
 *
 * Membuat 1 baris day_submit untuk tiap HARI AKTIF (2..7, punya item & tak
 * dikecualikan) dari tiap submission 'submitted'/'approved' yang belum punya
 * barisnya. review_status='approved' bila header approved (reviewer/reviewed_at
 * disalin agar jejak approval utuh), selain itu 'pending'.
 *
 * Idempoten: guard NOT EXISTS per (submission_id, day_of_week) + DISTINCT.
 */
class BackfillWeeklyDaySubmit extends Migration
{
    public function up()
    {
        DB::statement(<<<'SQL'
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
SQL);
    }

    public function down()
    {
        // No-op: data hasil backfill tak bisa dibedakan dari submit per-hari asli.
    }
}
