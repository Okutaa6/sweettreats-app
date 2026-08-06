-- ════════════════════════════════════════════════════════════════════════════
-- Anisa (Supervisor) & Irma (Coordinator Dentist) → aturan kerja FLEKSIBEL
-- ════════════════════════════════════════════════════════════════════════════
-- Keputusan Ekky 2026-08-05.
--
-- ALASANNYA (dari Ekky): jam kerja mereka memang seperti operasional — hari
-- Minggu pun masuk. TAPI untuk penilaian absensi mereka fleksibel, DAN mereka
-- punya hak yang sama dengan orang kantor/ABG: boleh tukar hari dan boleh WFH.
--
-- Kenapa lewat aturan kerja, bukan daftar nama di kode: seluruh sistem absensi
-- sudah memutuskan berdasarkan `att_work_rule.tipe`. Menaruh pengecualian
-- sebagai nama-nama di dalam kode berarti setiap fitur baru harus mengingat
-- daftar itu — dan cepat atau lambat ada yang lupa. Dengan memindahkan aturan
-- kerjanya, semua fitur (alpa, tukar hari, WFH, jenis form yang ditawarkan)
-- ikut benar sekaligus tanpa satu pun `if nama == 'Anisa'`.
--
-- AKIBAT YANG DISENGAJA:
--   • Mereka TIDAK BISA lagi kena `alpa` (orang fleksibel tak pernah alpa).
--   • Hari tanpa absen tak menghasilkan baris apa pun.
--   • Hari dengan absen tercatat `hadir`.
--   • Telat tidak dinilai dari scan (orang fleksibel memang tak dinilai jam).
-- Kalau kelak Ekky ingin mereka tetap dinilai jamnya, kembalikan ke FO-Asdent
-- lalu jalankan ulang perhitungan pada rentangnya — tidak ada data yang hilang.
--
-- Dicocokkan lewat NAMA JABATAN + NAMA ORANG, bukan UUID: id user & role beda
-- antar environment (jebakan yang sudah beberapa kali kena di proyek ini).
--
-- ⚠️ SETELAH file ini, jalankan perhitungan ulang untuk rentang yang terdampak:
--      php artisan att:reconcile 2026-08-05 <hari-ini>
--    Tanpa itu, baris `alpa` lama milik mereka masih menempel.
--
-- Idempoten: dijalankan berulang hasilnya sama.

START TRANSACTION;

UPDATE `att_user_rule` ur
JOIN `users` u            ON u.id = ur.user_id
JOIN `group_path` g       ON g.id = u.group_id
JOIN `att_work_rule` flex ON flex.tipe = 'fleksibel'
SET ur.rule_id = flex.id
WHERE g.nama IN ('Supervisor', 'Coordinator Dentist')
  AND ur.rule_id <> flex.id;

COMMIT;

-- ============================================================================
-- VERIFIKASI:
--   SELECT u.nama, g.nama AS jabatan, r.nama AS aturan, r.tipe
--   FROM att_user_rule ur
--   JOIN users u      ON u.id = ur.user_id
--   JOIN group_path g ON g.id = u.group_id
--   JOIN att_work_rule r ON r.id = ur.rule_id
--   WHERE g.nama IN ('Supervisor','Coordinator Dentist');
--     → tipe harus 'fleksibel' untuk semuanya
--
--   Lalu: php artisan att:reconcile 2026-08-05 <hari-ini>
--   Lalu pastikan tak ada alpa tersisa untuk mereka:
--   SELECT COUNT(*) FROM att_day d
--   JOIN users u ON u.id=d.user_id JOIN group_path g ON g.id=u.group_id
--   WHERE g.nama IN ('Supervisor','Coordinator Dentist') AND d.status='alpa';
--     → harus 0
-- ============================================================================
