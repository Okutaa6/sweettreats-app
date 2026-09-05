-- ═══════════════════════════════════════════════════════════════════════════
-- Aturan kerja tiga orang — Alpajri, Ela, Dodi (§B serah-terima 5 Sep 2026)
--
-- ⛔ DATA PRODUKSI. Jalankan per blok dan baca hasil tiap SELECT.
--
--   Alpajri Suhendar Al Qhusni  GS            → Fleksibel Operasional
--   Ela                         (tak punya)   → Fleksibel Kantor
--   Dodi                        (tak punya)   → Fleksibel Kantor
--
-- ─────────────────────────────────────────────────────────────────────────
-- ⚠️ `effective_from` = HARI ORANG ITU MULAI KERJA, BUKAN HARI BARIS INI DIBUAT
-- ─────────────────────────────────────────────────────────────────────────
-- Sudah dua kali menggigit di proyek ini (Alpajri Agustus: 3 alpa palsu;
-- Sunter + Della Raqisiah 13 Agu: 5 alpa palsu). Aturan yang berlaku SEHARI
-- LEBIH AWAL dari kenyataan menuduh orang bolos pada hari saat sistemnya bahkan
-- belum di tangan mereka.
--
-- Kalau ragu, pilih tanggal yang lebih BELAKANGAN: aturan yang telat sehari
-- cuma membuat hari itu tak dinilai; yang kecepatan sehari menuduh.
--
-- Tanggal yang dipakai skrip ini, beserta alasannya:
--
--   Ela      2026-09-02  — scan pertamanya. BUKAN tebakan: 6 scan (2-5 Sep,
--                          masuk & pulang) sudah ada di att_scan, dan tak satu
--                          pun jadi att_day karena aturannya belum ada.
--                          Blok 1 MENCETAK tanggal ini dari data — kalau yang
--                          tercetak bukan 2026-09-02, pakai yang tercetak.
--   Dodi     2026-09-08  — hari pertama ia bekerja, ditetapkan Ekky 5 Sep 2026.
--                          Tanggal ini MASIH DI DEPAN saat skrip ditulis, dan itu
--                          benar: sampai 8 Sep ia memang belum dinilai, jadi tak
--                          ada hari yang bisa salah dituduhkan. Aturan fleksibel
--                          juga TAK PERNAH melahirkan alpa — diperiksa di
--                          AttendanceReconciler::reconcileUser(), cabang
--                          `tipe === 'fleksibel'` hanya menulis libur/hadir atau
--                          ketidakhadiran yang di-ACC. Jadi orang kantor memang
--                          tak punya risiko alpa palsu di sini.
--   Alpajri  hari ini     — dia SUDAH dinilai sebagai GS selama ini. Aturan
--                          barunya berlaku KE DEPAN; baris GS-nya ditutup, tak
--                          pernah diubah (jejak gaji masa lalu harus tetap
--                          terbaca apa adanya).
--
-- ─────────────────────────────────────────────────────────────────────────
-- 📌 "ABG" BUKAN CABANG
-- ─────────────────────────────────────────────────────────────────────────
-- Tak ada `raw_branch` bernama ABG. "Kantor (ABG)" adalah kelompok di layar
-- Kehadiran yang DITURUNKAN dari `att_work_rule.tipe = 'fleksibel'`. Jadi
-- memberi Ela & Dodi aturan Fleksibel Kantor ITULAH yang menaruh mereka di ABG
-- — tak ada kolom cabang yang perlu diisi, dan mengisinya justru salah.
-- Blok 4 memverifikasi mereka benar-benar muncul di kelompok itu.
--
-- ⚠️ Cabang Alpajri TETAP NULL — disengaja, supaya ia bisa meninjau lintas
-- cabang tanpa meninjau dirinya sendiri. JANGAN "dirapikan".
--
-- ─────────────────────────────────────────────────────────────────────────
-- SESUDAH SKRIP INI — WAJIB, kalau tidak Ela tetap tertulis "Belum absen"
-- ─────────────────────────────────────────────────────────────────────────
--     php artisan att:reconcile 2026-09-02 2026-09-05      # Ela saja; Dodi baru mulai 8 Sep
--
-- Rekonsiliasi tak menghitung mundur sendiri. Scan Ela sudah tersimpan sejak
-- 2 Sep tapi tak pernah jadi hari kerja; perintah itulah yang membuatnya ada.
-- ═══════════════════════════════════════════════════════════════════════════


-- ═══ BLOK 1 — POTRET SEBELUM (catat hasilnya) ═══

-- Keadaan aturan ketiganya sekarang. Yang diharapkan: Alpajri 1 baris (GS),
-- Ela & Dodi NOL baris.
SELECT u.id, u.nama, u.raw_branch_id, w.nama AS aturan,
       ur.effective_from, ur.effective_to
FROM users u
LEFT JOIN att_user_rule ur ON ur.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci
LEFT JOIN att_work_rule w  ON w.id = ur.rule_id
WHERE u.nama IN ('Alpajri Suhendar Al Qhusni', 'Ela', 'Dodi')
ORDER BY u.nama, ur.effective_from;

-- Scan Ela yang belum pernah jadi hari kerja. Kolom `scan_pertama` inilah yang
-- dipakai sebagai effective_from — bukan angka yang diketik dari ingatan.
SELECT u.nama, COUNT(*) AS jml_scan,
       MIN(DATE(s.scanned_at)) AS scan_pertama,
       MAX(DATE(s.scanned_at)) AS scan_terakhir
FROM att_scan s
JOIN users u ON u.id COLLATE utf8mb4_unicode_ci = s.user_id COLLATE utf8mb4_unicode_ci
WHERE u.nama IN ('Ela', 'Dodi')
GROUP BY u.nama;

-- Aturan yang akan dipakai harus benar-benar ada. Kalau salah satu tak muncul,
-- BERHENTI — namanya berbeda di environment ini.
SELECT id, nama, tipe FROM att_work_rule
WHERE nama IN ('Fleksibel Operasional', 'Fleksibel Kantor');

-- Kendali negatif — cacah penugasan aktif SELAIN ketiganya. Harus sama persis
-- di blok 4.
SELECT COUNT(*) AS penugasan_aktif_orang_lain
FROM att_user_rule ur
WHERE ur.effective_to IS NULL
  AND ur.user_id COLLATE utf8mb4_unicode_ci NOT IN (
      SELECT id COLLATE utf8mb4_unicode_ci FROM users
      WHERE nama IN ('Alpajri Suhendar Al Qhusni', 'Ela', 'Dodi')
  );


-- ═══ BLOK 2 — ALPAJRI: GS → Fleksibel Operasional ═══
--
-- Baris GS-nya DITUTUP (effective_to), tak pernah di-UPDATE rule_id-nya —
-- pola yang sama dengan AturanKerjaController::assign(). Masa lalu sudah
-- ternilai sebagai GS; menulis ulang rule_id akan mengubah cerita hari-hari
-- yang gajinya sudah dibayar.

-- 2a) tutup baris GS per kemarin
UPDATE att_user_rule ur
JOIN users u          ON u.id COLLATE utf8mb4_unicode_ci = ur.user_id COLLATE utf8mb4_unicode_ci
JOIN att_work_rule w  ON w.id = ur.rule_id
SET ur.effective_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
WHERE u.nama = 'Alpajri Suhendar Al Qhusni'
  AND w.nama = 'GS'
  AND ur.effective_to IS NULL
  AND ur.effective_from < CURDATE();

SELECT ROW_COUNT() AS baris_gs_alpajri_ditutup;   -- harus 1

-- 2b) penugasan baru mulai hari ini
INSERT INTO att_user_rule (user_id, rule_id, effective_from, effective_to)
SELECT u.id, w.id, CURDATE(), NULL
FROM users u
JOIN att_work_rule w ON w.nama = 'Fleksibel Operasional'
WHERE u.nama = 'Alpajri Suhendar Al Qhusni'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT * FROM att_user_rule) x
      WHERE x.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci
        AND x.rule_id = w.id
        AND x.effective_to IS NULL
  );

SELECT ROW_COUNT() AS baris_baru_alpajri;   -- harus 1


-- ═══ BLOK 3 — ELA & DODI: beri aturan Fleksibel Kantor (= masuk ABG) ═══
--
-- Keduanya belum punya baris sama sekali, jadi tak ada yang perlu ditutup.
-- Tanpa aturan kerja, orangnya LENYAP dari layar Kehadiran: bukan hadir, bukan
-- alpa, bukan apa-apa — scan-nya tersimpan tapi tak seorang pun bisa melihatnya.

-- 3a) ELA — effective_from = tanggal scan pertamanya, DIAMBIL DARI DATA.
--     Kalau ia tak punya scan sama sekali (mis. sudah di-backfill lebih dulu),
--     hasilnya NOL BARIS — bukan tanggal karangan.
INSERT INTO att_user_rule (user_id, rule_id, effective_from, effective_to)
SELECT u.id, w.id, s.scan_pertama, NULL
FROM users u
JOIN att_work_rule w ON w.nama = 'Fleksibel Kantor'
JOIN (
    SELECT s.user_id, MIN(DATE(s.scanned_at)) AS scan_pertama
    FROM att_scan s GROUP BY s.user_id
) s ON s.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci
WHERE u.nama   = 'Ela'
  AND u.status = 1
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT * FROM att_user_rule) x
      WHERE x.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci
  );

SELECT ROW_COUNT() AS baris_baru_ela;   -- harus 1

-- 3b) DODI — mulai kerja 8 Sep 2026 (ditetapkan Ekky). Tanggalnya ditulis
--     EKSPLISIT, bukan CURDATE(): kalau skrip ini baru dijalankan minggu depan,
--     CURDATE() diam-diam menggeser hari pertamanya dan hari-hari awalnya
--     hilang dari rekap tanpa ada yang tahu.
INSERT INTO att_user_rule (user_id, rule_id, effective_from, effective_to)
SELECT u.id, w.id, '2026-09-08', NULL
FROM users u
JOIN att_work_rule w ON w.nama = 'Fleksibel Kantor'
WHERE u.nama   = 'Dodi'
  AND u.status = 1
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT * FROM att_user_rule) x
      WHERE x.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci
  );

SELECT ROW_COUNT() AS baris_baru_dodi;   -- harus 1


-- ═══ BLOK 4 — VERIFIKASI ═══

-- Ketiganya harus punya tepat satu penugasan aktif dengan aturan yang benar.
SELECT u.nama, u.raw_branch_id, w.nama AS aturan, w.tipe,
       ur.effective_from, ur.effective_to
FROM users u
JOIN att_user_rule ur ON ur.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci
JOIN att_work_rule w  ON w.id = ur.rule_id
WHERE u.nama IN ('Alpajri Suhendar Al Qhusni', 'Ela', 'Dodi')
ORDER BY u.nama, ur.effective_from;

-- 📌 VERIFIKASI "MASUK ABG". Kelompok Kantor (ABG) di layar Kehadiran =
-- orang ber-aturan bertipe 'fleksibel'. Ela & Dodi HARUS muncul di daftar ini;
-- kalau tidak, aturannya salah tipe dan mereka tak akan tampil di kelompok itu.
SELECT u.nama, w.nama AS aturan
FROM users u
JOIN att_user_rule ur ON ur.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci
JOIN att_work_rule w  ON w.id = ur.rule_id
WHERE w.tipe = 'fleksibel'
  AND ur.effective_to IS NULL
  AND u.status = 1
ORDER BY u.nama;

-- Tak boleh ada karyawan aktif yang tersisa tanpa aturan kerja (§E cek 3).
SELECT u.nama, g.nama AS role
FROM users u
LEFT JOIN group_path g ON g.id = u.group_id
WHERE u.status = 1
  AND NOT EXISTS (
      SELECT 1 FROM att_user_rule ur
      WHERE ur.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci
  )
ORDER BY u.nama;

-- Kendali negatif — WAJIB sama persis dengan blok 1.
SELECT COUNT(*) AS penugasan_aktif_orang_lain
FROM att_user_rule ur
WHERE ur.effective_to IS NULL
  AND ur.user_id COLLATE utf8mb4_unicode_ci NOT IN (
      SELECT id COLLATE utf8mb4_unicode_ci FROM users
      WHERE nama IN ('Alpajri Suhendar Al Qhusni', 'Ela', 'Dodi')
  );


-- ═══ BLOK 5 — BACKFILL ELA (jalankan di shell, bukan di sini) ═══
--
--     php artisan att:reconcile 2026-09-02 2026-09-05      # Ela saja; Dodi baru mulai 8 Sep
--
-- Lalu pastikan barisnya benar-benar lahir — sebelum ini hasilnya NOL:
--
--     SELECT u.nama, d.work_date, d.status, d.scan_first, d.scan_last
--     FROM att_day d
--     JOIN users u ON u.id COLLATE utf8mb4_unicode_ci = d.user_id COLLATE utf8mb4_unicode_ci
--     WHERE u.nama = 'Ela' ORDER BY d.work_date;
--
-- ⚠️ Aturan fleksibel TIDAK PERNAH melahirkan alpa, jadi backfill ini aman:
-- yang paling buruk terjadi cuma hari bertanda "tanpa scan", bukan tuduhan.
