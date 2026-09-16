-- ============================================================================
-- LANGKAH 1 — Backfill tanggal lahir pasien dari Omnicare (DATA SAJA, NOL KODE)
--
-- Rujukan angka: docs/handoff/data-reservasi-2026-09-11.md §10.
--   Omnicare punya DOB untuk 15.906 dari 16.500 pasien (96,4%).
--
-- ⚠️ RALAT (audit HUB 16 Sep, diukur langsung di prod): cakupan DOB kita
--    **7.597 / 15.732 = 48,3%**, BUKAN 56% seperti tertulis di §10 — angka itu
--    tampaknya memakai penyebut lain. Pakai **48,3%** sebagai patokan
--    "SEBELUM"; kalau perintah di bawah melaporkan ~56%, curigai penyebutnya,
--    jangan langsung percaya bahwa datanya membaik sendiri.
--
--   ~5.786 pasien kita bisa diisi SEKARANG lewat customer_omnicare_link
--   yang sudah ada.
--
-- ℹ️ Jalankan SEKALI LAGI sesudah `omnicare:match-patients`. Tautan pid baru
--    membuka sumber DOB yang tadinya tak terjangkau — terbukti di lokal:
--    jalan kedua sesudah matcher masih menemukan 11 pasien lagi.
--
-- ⭐ KENAPA INI DULUAN, sebelum sentuh kode penjodohan sama sekali.
--    Skor penjodohan = phone 30 + DOB 35 + nama 35, auto-link bila ≥60.
--    Tanpa DOB, satu-satunya pembeda antara dua orang di SATU nomor adalah
--    nama — dan itulah yang membuat 682 kontak berisi 2–7 pasien menjadi
--    kasus ambigu. Dengan DOB terisi, Langkah 2 bekerja di atas data yang
--    jauh lebih kuat dan jumlah kasus ambigu turun dengan sendirinya.
--
-- ⛔ HANYA MENGISI YANG KOSONG. `tanggal_lahir` yang sudah ada TIDAK PERNAH
--    ditimpa — meski Omnicare berbeda. Kalau keduanya berbeda, itu temuan
--    untuk manusia (`php artisan omnicare:tinjau-tautan --dob`), bukan alasan
--    bagi SQL untuk menimpa data yang sudah dipegang staf kita.
--
-- ⚠️ `omnicare_patient.dob` sudah bertipe DATE dan sudah diurai dari format
--    API d/m/Y oleh SyncOmnicare (N::dateFromDmy). Jadi JANGAN menguraikannya
--    lagi di sini — jebakan "Carbon::parse menukar hari↔bulan" tidak berlaku
--    pada kolom ini, dan menambah parsing justru akan merusaknya.
--
-- ⚠️ TAHAN TERHADAP LANGKAH 2. Sesudah satu pasien boleh punya BANYAK pid,
--    baris ini bisa menemukan >1 DOB untuk satu orang. Yang dipakai hanya
--    kalau SELURUH pid-nya sepakat pada satu tanggal (`variasi = 1`).
--    Kalau pid-pid itu bertengkar, pasien DILEWATI dan dilaporkan — menebak
--    tanggal lahir yang salah lebih mahal daripada membiarkannya kosong.
--
-- ⚠️ SENGAJA TIDAK MEMAKAI `UPDATE ... JOIN (subquery)`. Pola itu tidak pernah
--    dipakai di repo ini dan MariaDB lama menolak tabel turunan di dalam
--    UPDATE. Dipakai tabel pembantu nyata — pola yang jalan di versi mana pun,
--    dan sekaligus memberi dua hal yang tidak diberi subquery:
--      1. Rencana dan eksekusi membaca SUMBER YANG SAMA, jadi angka "RENCANA"
--         tidak mungkin berbeda dari yang benar-benar ditulis.
--      2. Jejak yang bisa DIBALIK — lihat bagian pemulihan di bawah.
--
-- Idempoten: aman dijalankan berulang. Jalan kedua akan melaporkan 0 terisi.
--
-- deploy.sh TIDAK menjalankan SQL. Jalankan berkas ini MANUAL.
-- Urutan aman: berkas ini DULU → baru SQL multi-pid → baru deploy kode.
-- ============================================================================

-- ────────────────────────────────────────────────────────────────────────────
-- SEBELUM — catat angkanya, ini yang akan dibandingkan sesudah
-- ────────────────────────────────────────────────────────────────────────────
SELECT 'SEBELUM' AS fase,
       COUNT(*)                                                      AS pasien_total,
       SUM(CASE WHEN tanggal_lahir IS NOT NULL
                 AND CAST(tanggal_lahir AS CHAR) NOT IN ('', '0000-00-00')
                THEN 1 ELSE 0 END)                                   AS dob_terisi,
       ROUND(100.0 * SUM(CASE WHEN tanggal_lahir IS NOT NULL
                               AND CAST(tanggal_lahir AS CHAR) NOT IN ('', '0000-00-00')
                              THEN 1 ELSE 0 END) / COUNT(*), 1)      AS persen
FROM raw_customer;

-- ────────────────────────────────────────────────────────────────────────────
-- 1. Tabel pembantu — SATU sumber kebenaran untuk rencana DAN eksekusi
--
-- ⛔ JANGAN kembalikan `DROP TABLE` di sini (ada sampai audit 16 Sep).
--    Log ini diisi HANYA dari pasien yang tanggal lahirnya masih kosong. Pada
--    jalan KEDUA, yang sudah terisi di jalan pertama tidak lagi kosong → tidak
--    masuk log → kalau tabelnya di-DROP dulu, jejak jalan pertama LENYAP dan
--    resep pemulihan di kaki berkas tidak lagi cocok dengan baris mana pun.
--    Datanya memang tetap idempoten; yang hilang diam-diam adalah kemampuan
--    MEMBATALKAN — justru alasan tabel ini dibuat. Seseorang yang menjalankan
--    ulang "untuk memastikan" akan kehilangannya tanpa pesan apa pun.
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `omnicare_dob_backfill_log` (
  `raw_customer_id` varchar(255) NOT NULL,
  `dob_omnicare`    date         NOT NULL,
  `variasi`         int          NOT NULL COMMENT '>1 = pid-pid pasien ini tidak sepakat',
  `diisi`           tinyint(1)   NOT NULL DEFAULT 0 COMMENT '1 = baris ini benar-benar ditulis',
  `diisi_pada`      timestamp    NULL DEFAULT NULL,
  PRIMARY KEY (`raw_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Jejak backfill DOB 2026-09-16 — audit & pemulihan. Aman di-DROP kalau sudah tak diperlukan.';

-- ⛔ BUKAN `INSERT IGNORE`. Pernah begitu, dan itu MENGUNCI pasien selamanya:
--    baris yang belum terisi (`diisi = 0`, mis. karena pid-pidnya dulu
--    bertengkar) tidak pernah dihitung ulang, jadi walau konfliknya kemudian
--    beres, `variasi` tetap >1 → UPDATE di bawah melewatinya → tanggal lahirnya
--    TIDAK PERNAH terisi, berapa kali pun berkas ini dijalankan. Diuji: baris
--    `diisi = 0` bertahan basi lintas jalan ulang.
--
-- Jadi: baris yang BELUM terisi disegarkan, baris yang SUDAH terisi dikunci apa
-- adanya — yang terakhir itu jejak pemulihan, dan ia tidak boleh bergerak.
INSERT INTO `omnicare_dob_backfill_log` (`raw_customer_id`, `dob_omnicare`, `variasi`)
SELECT l.raw_customer_id,
       MIN(p.dob),
       COUNT(DISTINCT p.dob)
FROM customer_omnicare_link l
JOIN omnicare_patient p ON p.pid = l.omnicare_pid
JOIN raw_customer c     ON c.id  = l.raw_customer_id
WHERE p.dob IS NOT NULL
  AND p.dob <> '0000-00-00'
  AND YEAR(p.dob) BETWEEN 1900 AND YEAR(CURDATE())
  -- hanya pasien yang tanggal lahirnya MASIH KOSONG
  AND (c.tanggal_lahir IS NULL
       OR CAST(c.tanggal_lahir AS CHAR) IN ('', '0000-00-00'))
GROUP BY l.raw_customer_id
ON DUPLICATE KEY UPDATE
  -- `diisi = 1` = jejak pemulihan → JANGAN disentuh, apa pun yang terjadi.
  -- `diisi = 0` = pekerjaan tertunda → segarkan, supaya konflik yang sudah
  --               beres tidak terkunci basi selamanya.
  `dob_omnicare` = IF(`diisi` = 0, VALUES(`dob_omnicare`), `dob_omnicare`),
  `variasi`      = IF(`diisi` = 0, VALUES(`variasi`),      `variasi`);

-- Rencana — dibaca dari tabel yang sama yang akan dipakai UPDATE di bawah.
-- `diisi = 0` memisahkan pekerjaan JALAN INI dari jejak jalan sebelumnya;
-- tanpa itu, jalan kedua akan melaporkan ulang ribuan baris yang sudah selesai.
SELECT 'RENCANA' AS fase,
       SUM(CASE WHEN variasi = 1 AND diisi = 0 THEN 1 ELSE 0 END) AS akan_terisi,
       SUM(CASE WHEN variasi > 1 AND diisi = 0 THEN 1 ELSE 0 END) AS dilewati_pid_bertengkar,
       SUM(diisi)                                                 AS sudah_terisi_jalan_sebelumnya
FROM `omnicare_dob_backfill_log`;

-- Siapa saja yang dilewati karena pid-pidnya bertengkar — untuk manusia.
SELECT 'DILEWATI (pid bertengkar)' AS catatan,
       b.raw_customer_id, c.name, c.phone,
       GROUP_CONCAT(DISTINCT p.dob ORDER BY p.dob SEPARATOR ' / ') AS dob_calon
FROM `omnicare_dob_backfill_log` b
JOIN raw_customer c            ON c.id = b.raw_customer_id
JOIN customer_omnicare_link l  ON l.raw_customer_id = b.raw_customer_id
JOIN omnicare_patient p        ON p.pid = l.omnicare_pid
WHERE b.variasi > 1
GROUP BY b.raw_customer_id, c.name, c.phone
LIMIT 200;

-- ────────────────────────────────────────────────────────────────────────────
-- 2. ISI — hanya yang pid-pidnya sepakat
-- ────────────────────────────────────────────────────────────────────────────
UPDATE raw_customer c
JOIN `omnicare_dob_backfill_log` b ON b.raw_customer_id = c.id
SET c.tanggal_lahir = b.dob_omnicare
WHERE b.variasi = 1
  AND (c.tanggal_lahir IS NULL
       OR CAST(c.tanggal_lahir AS CHAR) IN ('', '0000-00-00'));

-- Tandai apa yang benar-benar terisi (dasar pemulihan).
UPDATE `omnicare_dob_backfill_log` b
JOIN raw_customer c ON c.id = b.raw_customer_id
SET b.diisi = 1, b.diisi_pada = NOW()
WHERE b.variasi = 1
  AND CAST(c.tanggal_lahir AS CHAR) = CAST(b.dob_omnicare AS CHAR);

-- ────────────────────────────────────────────────────────────────────────────
-- 3. SESUDAH — angka yang wajib dilaporkan
-- ────────────────────────────────────────────────────────────────────────────
SELECT 'SESUDAH' AS fase,
       COUNT(*)                                                      AS pasien_total,
       SUM(CASE WHEN tanggal_lahir IS NOT NULL
                 AND CAST(tanggal_lahir AS CHAR) NOT IN ('', '0000-00-00')
                THEN 1 ELSE 0 END)                                   AS dob_terisi,
       ROUND(100.0 * SUM(CASE WHEN tanggal_lahir IS NOT NULL
                               AND CAST(tanggal_lahir AS CHAR) NOT IN ('', '0000-00-00')
                              THEN 1 ELSE 0 END) / COUNT(*), 1)      AS persen
FROM raw_customer;

-- KUMULATIF: mencakup jalan-jalan sebelumnya juga, karena lognya sengaja
-- tidak pernah dibuang (lihat catatan di bagian 1).
SELECT 'HASIL (kumulatif)' AS fase,
       SUM(diisi)                                   AS total_terisi_sejak_awal,
       SUM(CASE WHEN variasi > 1 THEN 1 ELSE 0 END) AS dilewati_bertengkar,
       COUNT(*)                                     AS kandidat_tercatat
FROM `omnicare_dob_backfill_log`;

-- Yang TETAP kosong walau tertaut — sisanya memang Omnicare pun tak punya.
SELECT 'SISA KOSONG (tertaut, Omnicare juga tak punya DOB)' AS catatan,
       COUNT(DISTINCT c.id) AS jumlah
FROM raw_customer c
JOIN customer_omnicare_link l ON l.raw_customer_id = c.id
LEFT JOIN omnicare_patient p  ON p.pid = l.omnicare_pid
WHERE (c.tanggal_lahir IS NULL OR CAST(c.tanggal_lahir AS CHAR) IN ('', '0000-00-00'))
  AND (p.dob IS NULL OR p.dob = '0000-00-00');

-- Pasien AKTIF (reservasi 90 hari) — ini yang menentukan seberapa besar
-- Langkah 2 akan menolong.
SELECT 'PASIEN AKTIF' AS fase,
       COUNT(DISTINCT c.id) AS aktif_total,
       COUNT(DISTINCT CASE WHEN c.tanggal_lahir IS NOT NULL
                            AND CAST(c.tanggal_lahir AS CHAR) NOT IN ('', '0000-00-00')
                           THEN c.id END) AS aktif_dob_terisi
FROM raw_customer c
JOIN raw_cus_reservation r ON r.raw_customer_id = c.id
WHERE r.date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY);

-- ============================================================================
-- ⭐ PEMULIHAN — kalau ternyata backfill ini keliru
--
-- Mengisi 5.786 tanggal lahir itu perubahan besar pada data pasien; ia harus
-- bisa dibatalkan tanpa menebak-nebak baris mana yang tersentuh. Jalankan:
--
--   UPDATE raw_customer c
--   JOIN `omnicare_dob_backfill_log` b ON b.raw_customer_id = c.id
--   SET c.tanggal_lahir = NULL
--   WHERE b.diisi = 1
--     AND CAST(c.tanggal_lahir AS CHAR) = CAST(b.dob_omnicare AS CHAR);
--
-- ⚠️ Syarat terakhir itu penting: kalau sesudah backfill ada STAF yang
--    membetulkan tanggal lahir seseorang secara manual, baris itu tidak lagi
--    sama dengan nilai Omnicare — dan pemulihan HARUS melewatinya, bukan
--    mengosongkan kerja orang.
--
-- Kalau sudah yakin, tabel jejaknya boleh dibuang:
--   DROP TABLE `omnicare_dob_backfill_log`;
-- ============================================================================
