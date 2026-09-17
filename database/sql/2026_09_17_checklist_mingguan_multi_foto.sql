-- ════════════════════════════════════════════════════════════════════════════
-- Checklist Mingguan — Multi-foto per slot (maks 5) — 2026-09-17  (IDEMPOTENT)
-- DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Konteks:
--   Satu item Form Mingguan selama ini hanya bisa menyimpan SATU foto per slot,
--   karena fotonya disimpan sebagai kolom di `sys_checklist_weekly_submission_item`:
--     • photo_path        → mode foto satuan (task.requires_photo)
--     • photo_before_path → slot Before (mode before/after, divisi GS/ASDENT)
--     • photo_after_path  → slot After
--   Mengambil foto kedua MENIMPA yang pertama. Ops butuh sampai 5 foto per slot
--   (mis. GS membersihkan beberapa ruangan dalam satu tugas).
--
-- Yang dilakukan skrip ini:
--   1. Membuat tabel anak `sys_checklist_weekly_submission_photo`.
--      1 baris = 1 foto. `slot` = 'single' | 'before' | 'after'.
--      `sort_order` = urutan tampil, monoton naik (MAX+1), TIDAK dinomori ulang
--      saat ada foto dihapus — batas 5 ditegakkan lewat COUNT, bukan nilai
--      sort_order, jadi lubang penomoran tidak masalah.
--   2. Backfill: tiap kolom foto lama yang terisi dijadikan foto PERTAMA
--      (sort_order = 0) di slot-nya, supaya foto yang sudah telanjur diunggah
--      tidak hilang dari layar setelah kode baru naik.
--
-- Kolom lama SENGAJA DIPERTAHANKAN sebagai CERMIN foto pertama tiap slot.
-- Aplikasi terus mengisinya setiap upload/hapus (MyChecklistController::
-- syncPhotoMirror). Dua alasannya:
--   • ChecklistWeeklySubmission::missingPhotos() memindai SELURUH item satu
--     minggu; membacanya dari kolom cermin = nol query tambahan. Kalau diubah
--     jadi cek ke tabel anak, halaman review atasan kena N+1.
--   • Rollback cukup revert kode — kolom lama tetap valid, halaman lama langsung
--     berfungsi kembali menampilkan foto pertama. Tidak perlu SQL balikan.
--
-- Tipe `varchar(255)` + collation utf8mb4_unicode_ci MENGIKUTI tabel induk
-- (`sys_checklist_weekly_submission_item`.`id` = varchar(255)), BUKAN char(36).
-- Kalau berbeda, JOIN-nya kena error #1267 "illegal mix of collations" di prod.
--
-- Yang SENGAJA TIDAK dilakukan (jangan ditambahkan tanpa keputusan baru):
--   - Kolom photo_path / photo_before_path / photo_after_path TIDAK di-DROP dan
--     TIDAK dikosongkan. Lihat alasan "cermin" di atas.
--   - TIDAK memakai FOREIGN KEY / ON DELETE CASCADE. Tidak satu pun tabel
--     checklist di aplikasi ini memakainya. Pembersihan baris anak saat item
--     dihapus dikerjakan di kode (MyChecklistController::reconcileWeeklyItems).
--   - TIDAK menyentuh checklist HARIAN — tabel itu memang tidak punya kolom foto.
--   - TIDAK ada ALTER TABLE sama sekali, jadi keterbatasan MySQL 5.7 (tak punya
--     ADD COLUMN IF NOT EXISTS) tidak relevan di file ini.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `sys_checklist_weekly_submission_photo` (
  `id`         varchar(255) NOT NULL,
  `item_id`    varchar(255) NOT NULL                      COMMENT 'FK logis sys_checklist_weekly_submission_item.id',
  `slot`       varchar(10)  NOT NULL DEFAULT 'single'     COMMENT 'single | before | after',
  `path`       varchar(255) NOT NULL                      COMMENT 'relatif dari public/, mis. uploads/checklist-photos/2026/W38/uuid.jpg',
  `sort_order` int(11)      NOT NULL DEFAULT 0            COMMENT 'urutan tampil, monoton naik, tidak dinomori ulang saat hapus',
  `created_at` timestamp    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wsp_item_slot_sort` (`item_id`,`slot`,`sort_order`),
  KEY `idx_wsp_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Backfill 3 kolom lama → slot masing-masing (sort_order = 0) ──────────────
-- UUID() dievaluasi per baris. INSERT IGNORE ditahan UNIQUE KEY
-- (item_id, slot, sort_order) → dijalankan berulang tidak menambah duplikat.

INSERT IGNORE INTO `sys_checklist_weekly_submission_photo`
  (`id`, `item_id`, `slot`, `path`, `sort_order`, `created_at`)
SELECT UUID(), i.`id`, 'single', i.`photo_path`, 0,
       COALESCE(i.`done_at`, i.`created_at`, NOW())
  FROM `sys_checklist_weekly_submission_item` i
 WHERE i.`photo_path` IS NOT NULL AND i.`photo_path` <> '';

INSERT IGNORE INTO `sys_checklist_weekly_submission_photo`
  (`id`, `item_id`, `slot`, `path`, `sort_order`, `created_at`)
SELECT UUID(), i.`id`, 'before', i.`photo_before_path`, 0,
       COALESCE(i.`done_at`, i.`created_at`, NOW())
  FROM `sys_checklist_weekly_submission_item` i
 WHERE i.`photo_before_path` IS NOT NULL AND i.`photo_before_path` <> '';

INSERT IGNORE INTO `sys_checklist_weekly_submission_photo`
  (`id`, `item_id`, `slot`, `path`, `sort_order`, `created_at`)
SELECT UUID(), i.`id`, 'after', i.`photo_after_path`, 0,
       COALESCE(i.`done_at`, i.`created_at`, NOW())
  FROM `sys_checklist_weekly_submission_item` i
 WHERE i.`photo_after_path` IS NOT NULL AND i.`photo_after_path` <> '';

-- ── Verifikasi (jalankan manual; rerun skrip tidak boleh menambah baris) ─────
-- 1) Jumlah per slot:
-- SELECT `slot`, COUNT(*) FROM `sys_checklist_weekly_submission_photo` GROUP BY `slot`;
--
-- 2) Harus sama dengan jumlah kolom lama yang terisi:
-- SELECT
--   (SELECT COUNT(*) FROM `sys_checklist_weekly_submission_item` WHERE `photo_path`        IS NOT NULL AND `photo_path`        <> '') AS single_lama,
--   (SELECT COUNT(*) FROM `sys_checklist_weekly_submission_item` WHERE `photo_before_path` IS NOT NULL AND `photo_before_path` <> '') AS before_lama,
--   (SELECT COUNT(*) FROM `sys_checklist_weekly_submission_item` WHERE `photo_after_path`  IS NOT NULL AND `photo_after_path`  <> '') AS after_lama;
--
-- 3) Tidak ada foto lama yang gagal ikut backfill (harus 0 baris):
-- SELECT i.`id` FROM `sys_checklist_weekly_submission_item` i
--  LEFT JOIN `sys_checklist_weekly_submission_photo` p
--    ON p.`item_id` = i.`id` AND p.`slot` = 'single' AND p.`sort_order` = 0
--  WHERE i.`photo_path` IS NOT NULL AND i.`photo_path` <> '' AND p.`id` IS NULL;
