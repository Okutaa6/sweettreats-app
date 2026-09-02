-- ============================================================================
-- Koreksi satu baris checklist Alpajri Suhendar — 2026-09-01
--
-- MASALAHNYA (terukur, bukan dugaan):
--   Baris 2026-08-27 berstatus `missed`, padahal di
--   `sys_checklist_submission_item` ke-76 tugasnya tercatat SELESAI (76/76).
--   `submitted_at` NULL → dia menuntaskan pekerjaannya tapi tak sempat
--   menekan kirim, lalu cron `checklist:mark-missed` 00:10 menandainya missed.
--
--   Sesudah pindah role ke Koordinator GS, baris posisinya tertutup
--   (home Bintaro 2026-08-08 -> 2026-08-31), jadi dia TIDAK BISA lagi
--   membetulkannya sendiri lewat layar.
--
-- YANG DIUBAH: status -> 'submitted', isi submitted_at, tulis alasan.
-- YANG TIDAK DIUBAH: done_tasks/total_tasks (sudah benar), reviewer_user_id,
--   reviewed_at. Keputusan APPROVE tetap milik peninjau — koreksi ini hanya
--   mengembalikan barisnya ke antrean tinjauan, tidak meluluskan apa pun.
--
-- YANG SENGAJA TIDAK DISENTUH (sudah diperiksa, catatannya AKURAT):
--   2026-08-30 missed 13/76  — memang tak tuntas
--   2026-08-10 draft   0/74  — memang tak dikerjakan
--   2026-08-25 missed   0/0  — NOL tugas sama sekali; kelas masalah lain,
--                              menunggu keputusan Ekky (lihat catatan di bawah)
--
-- IDEMPOTEN & MENJAGA DIRI: semua syarat ada di WHERE. Kalau barisnya tidak
-- persis dalam keadaan yang dijelaskan di atas, statement ini mengubah
-- 0 baris — bukan mengubah baris yang salah.
-- ============================================================================

UPDATE sys_checklist_submission s
JOIN users u ON u.id = s.user_id
SET s.status       = 'submitted',
    s.submitted_at = '2026-08-27 23:59:00',
    s.review_notes = CONCAT(
        'Koreksi data IT 2026-09-01: ke-76 tugas tercatat SELESAI di ',
        'sys_checklist_submission_item, tapi submitted_at kosong sehingga cron ',
        'menandainya missed. Status dikembalikan ke submitted agar bisa ',
        'ditinjau normal. Keputusan approve TETAP di peninjau.'
    ),
    s.updated_at   = NOW()
WHERE u.nama LIKE '%ajri Suhendar%'
  AND s.submission_date = '2026-08-27'
  AND s.status          = 'missed'        -- kalau sudah dibetulkan, 0 baris
  AND s.submitted_at IS NULL              -- jangan timpa pengiriman sungguhan
  AND s.total_tasks > 0                   -- jangan sentuh baris kosong
  AND s.done_tasks = s.total_tasks;       -- HANYA yang benar-benar 100% tuntas

-- Verifikasi — jalankan sesudahnya, harus menampilkan 1 baris 'submitted'
-- dengan 76/76, dan tiga baris lain TIDAK berubah.
SELECT s.submission_date, s.status, s.done_tasks, s.total_tasks, s.submitted_at
FROM sys_checklist_submission s
JOIN users u ON u.id = s.user_id
WHERE u.nama LIKE '%ajri Suhendar%'
  AND s.submission_date IN ('2026-08-10','2026-08-25','2026-08-27','2026-08-30')
ORDER BY s.submission_date;

-- ============================================================================
-- CATATAN TERPISAH — baris 2026-08-25 (missed, 0/0)
--
-- Baris itu `missed` padahal NOL tugas pernah dibuat untuknya. Tak ada yang
-- bisa dikerjakan, tapi tetap terhitung sebagai tidak mengisi. Ini bukan salah
-- catat seperti 27 Agu — ini pertanyaan kebijakan: apakah hari tanpa tugas
-- boleh dihitung missed? Sengaja TIDAK diubah di sini; kalau Ekky memutuskan
-- tidak boleh, itu perbaikan sistemik (menyentuh semua orang), bukan
-- tambalan satu baris.
-- ============================================================================
