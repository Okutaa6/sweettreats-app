-- ════════════════════════════════════════════════════════════════════════════
-- Penilaian absensi MULAI 5 Agustus 2026 — 4 Agustus dinyatakan tak berlaku
-- ════════════════════════════════════════════════════════════════════════════
-- Keputusan Ekky 2026-08-05.
--
-- MASALAHNYA: sapuan pagi 5 Agustus menilai 4 Agustus sebagai hari kerja tanpa
-- satu pun scan, lalu mencetak 6 baris `alpa` (Afrina, Anisa, Asih, Firda,
-- Irma, Rizka). Sistemnya bekerja persis sesuai aturan — premisnya yang salah:
-- absen HP baru benar-benar dipakai orang mulai 5 Agustus. Aturan kerja
-- terlanjur dipasang dengan mulai-berlaku 1 Agustus.
--
-- CARANYA: geser mulai-berlaku aturan kerja ke 2026-08-05. Ini memakai
-- mekanisme sistem sendiri (att_user_rule.effective_from), BUKAN menghapus
-- baris att_day secara langsung. Bedanya penting:
--   • Hapus baris = sementara. Siapa pun yang kelak menjalankan ulang
--     perhitungan untuk rentang yang memuat 4 Agustus akan MELAHIRKAN LAGI
--     alpa itu, tanpa ada yang sadar.
--   • Geser mulai-berlaku = permanen & jujur. Untuk tanggal sebelum 5 Agustus
--     tak ada aturan yang berlaku, jadi perhitungan tidak menghasilkan baris
--     apa pun, dan baris lama yang sudah tak dihasilkan akan DIBUANG SENDIRI
--     oleh pembersih bawaan (hapusHariBasi) saat perhitungan dijalankan ulang.
--
-- KONSEKUENSI YANG DISADARI: kalau kelak data mesin sidik jari untuk 1–4
-- Agustus diunggah, scan itu TIDAK akan dinilai (tak ada aturan yang berlaku
-- saat itu). Itu memang arti "mulai 5 Agustus". Kalau suatu saat perlu dinilai,
-- cukup mundurkan lagi tanggalnya di sini — tidak ada data yang dibuang.
--
-- ⚠️ SETELAH file ini WAJIB:
--      php artisan att:reconcile 2026-08-04 2026-08-04
--    Itulah yang membuang 6 baris alpa lama. Tanpanya, barisnya masih menempel
--    walau aturannya sudah digeser.
--
-- Idempoten: dijalankan berulang hasilnya sama (tanggal sudah 2026-08-05 →
-- tidak ada baris yang tersentuh).

UPDATE `att_user_rule`
SET `effective_from` = '2026-08-05'
WHERE `effective_from` < '2026-08-05';

-- ============================================================================
-- VERIFIKASI (jalankan setelah reconcile 4 Agustus):
--   SELECT effective_from, COUNT(*) FROM att_user_rule GROUP BY effective_from;
--     → semua harus 2026-08-05
--   SELECT COUNT(*) FROM att_day WHERE work_date < '2026-08-05';
--     → harus 0
--   SELECT status, COUNT(*) FROM att_day WHERE work_date='2026-08-05' GROUP BY status;
--     → berisi kehadiran hari pertama yang sah
-- ============================================================================
