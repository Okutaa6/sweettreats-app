-- ════════════════════════════════════════════════════════════════════════════
-- Aturan kerja bertipe `jadwal` — absensi dokter dari jadwal praktik
-- ════════════════════════════════════════════════════════════════════════════
-- Arah §6 yang di-ACC Ekky 13 Agu 2026 (pilihan C). Konteks penuh & alasan
-- kenapa A dan B ditolak: docs/rencana/rencana-akun-dokter-absensi-jadwal.md.
--
-- INTINYA: aturan bertipe `jadwal` TIDAK membawa jam sendiri. Ia sengaja tak
-- punya satu pun baris `att_work_rule_day`. Yang menjawab "hari ini kerja atau
-- tidak, jam berapa, di cabang mana" adalah raw_doctor_schedule + jadwal khusus
-- + cuti dokter — sumber yang SAMA dengan yang dibaca pasien saat memesan.
--
-- Kenapa bukan menyalin jadwal ke att_work_rule_day (pilihan A): itu bikin DUA
-- sumber kebenaran. Jadwal diubah di halaman Dokter, salinannya tidak ikut, dan
-- hasilnya ALPA PALSU di hari dokter memang tak praktik. Ada 57 baris jadwal
-- khusus di prod — perubahan jadwal itu kejadian rutin, bukan kasus langka.
-- Lagipula att_work_rule_day tak punya kolom cabang, jadi gagasan inti §6
-- ("hadir di cabang yang sesuai jadwalnya") tak bisa diungkapkan di sana.
--
-- Kenapa bukan `fleksibel` (pilihan B): selain tak dinilai sama sekali, tipe
-- itu MEMATIKAN SELURUH flag pengawasan (AttendanceHpAbsen::absen). Dokter yang
-- absen dari jaringan asing atau dari perangkat akun lain tak akan memunculkan
-- apa pun. Itu keputusan yang harus diambil sadar, bukan ikut terbawa label.
--
-- ⚠️ TIDAK ADA baris att_user_rule di berkas ini — belum ada akun dokter untuk
--    ditugaskan. Penugasan datang otomatis lewat att_default_rule_id (bagian 3)
--    saat akunnya dibuat di Pusat Akun.
--
-- Idempotent: cek information_schema & NOT EXISTS. Aman dijalankan ulang.
-- Prasyarat: 2026_08_13_akun_dokter_prasyarat.sql sudah jalan (role Dokter).
-- ════════════════════════════════════════════════════════════════════════════


-- ─────────────────────────────────────────────────────────────────────────────
-- 1. att_work_rule.tipe — tambah nilai 'jadwal'
-- ─────────────────────────────────────────────────────────────────────────────
-- MODIFY, bukan ALTER ... SET: enum di MySQL harus ditulis utuh. Dua nilai lama
-- WAJIB ikut disebut — kalau tertinggal, seluruh aturan `baku`/`fleksibel` yang
-- dipakai ±16 karyawan berubah jadi nilai kosong dalam satu perintah.
--
-- Dijaga cek COLUMN_TYPE supaya tak memodifikasi kolom yang sudah benar.

SET @tipe := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'att_work_rule' AND COLUMN_NAME = 'tipe');

SET @sql := IF(@tipe IS NOT NULL AND LOCATE('jadwal', @tipe) = 0,
  "ALTER TABLE `att_work_rule`
     MODIFY `tipe` enum('baku','fleksibel','jadwal') NOT NULL
       COMMENT 'baku=dinilai telat/alpa dari scan; fleksibel=scan cuma penanda; jadwal=jam kerja dibaca dari jadwal praktik dokter'",
  "SELECT 'att_work_rule.tipe sudah punya jadwal (atau tabel tak ada), dilewati' AS info");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Aturan "Dokter" — bertipe jadwal, TANPA baris hari
-- ─────────────────────────────────────────────────────────────────────────────
-- Satu aturan untuk SEMUA dokter, bukan satu aturan per dokter. Itu justru
-- yang membuat pilihan C rapi: karena jamnya tidak disimpan di aturan, dokter
-- dengan hari praktik berbeda-beda tetap bisa berbagi satu aturan — hal yang
-- mustahil pada tipe `baku` (att_work_rule_day berkunci rule_id + day).
--
-- JANGAN menambahkan baris att_work_rule_day untuk aturan ini. Baris hari akan
-- diam-diam dibaca hariLiburTerjadwal() sebagai jawaban pola mingguan dan
-- berselisih dengan jadwal praktik yang sebenarnya.

INSERT INTO `att_work_rule` (`nama`, `tipe`, `keterangan`, `created_at`, `updated_at`)
SELECT 'Dokter', 'jadwal',
       'Jam kerja dibaca dari jadwal praktik dokter (raw_doctor_schedule + jadwal khusus + cuti). Tak punya baris hari.',
       NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `att_work_rule` WHERE `nama` = 'Dokter');


-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Role Dokter → aturan Dokter otomatis saat akun dibuat
-- ─────────────────────────────────────────────────────────────────────────────
-- Ini yang menutup jebakan yang ditulis besar-besar di berkas prasyarat:
-- reconciler HANYA menyapa orang yang punya baris att_user_rule
-- (AttendanceReconciler::reconcile). Akun dokter tanpa baris itu scannya jadi
-- YATIM — tak dinilai, tak muncul di rekap, dan gagalnya DIAM. Persis kelas
-- cacat yang dulu menelan 538 scan Head IT.
--
-- Dengan att_default_rule_id terisi, Pusat Akun membuatkan att_user_rule sendiri
-- saat akun lahir (Set\UserManagement::store) — jadi jebakannya tertutup di
-- sumbernya, bukan mengandalkan orang ingat.
--
-- Kolomnya datang dari 2026_08_04_aturan_kerja.sql; environment yang belum
-- apply itu dilewati tanpa galat.

SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'group_path' AND COLUMN_NAME = 'att_default_rule_id');

SET @sql := IF(@ada = 1,
  "UPDATE `group_path` g
      JOIN `att_work_rule` r ON r.`nama` = 'Dokter' AND r.`tipe` = 'jadwal'
       SET g.`att_default_rule_id` = r.`id`
     WHERE g.`nama` = 'Dokter' AND g.`att_default_rule_id` IS NULL",
  "SELECT 'group_path.att_default_rule_id belum ada, dilewati' AS info");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ─────────────────────────────────────────────────────────────────────────────
-- 4. att_flag.jenis — SIAPKAN nilai 'cabang_beda_jadwal' (arah K2)
-- ─────────────────────────────────────────────────────────────────────────────
-- ⚠️ NILAI ENUM-nya saja yang disiapkan di sini. BELUM ADA satu pun kode yang
--    menaikkan flag ini — reconciler sengaja hanya MELAPORKAN selisih cabang
--    (lihat `dokter_cabang_beda_jadwal` di AttendanceReconciler::reconcile,
--    dicetak oleh `php artisan att:reconcile`).
--
-- Kenapa ditahan, padahal arahnya sudah jelas: flag yang TERBUKA MEMBLOKIR
-- penutupan periode gaji — dan gerbangnya GLOBAL, tidak menyaring per cabang
-- maupun per orang (AbsensiPeriodController: hitung semua att_flag berstatus
-- != 'selesai' di rentang periode). Artinya satu dokter yang absen di cabang
-- "salah" bisa menyandera gajian SELURUH klinik. Memakai alat sekeras itu
-- sebelum K1 (cara bayar dokter) dijawab dan K2 di-ACC eksplisit = risiko yang
-- tak sepadan dengan manfaatnya.
--
-- Menyiapkan nilainya lebih dulu tetap berguna: saat K2 di-ACC, yang tersisa
-- cuma satu panggilan AttFlag::raise() — tanpa perubahan skema, tanpa deploy DB.
--
-- Arah yang dituju (kalau nanti di-ACC): selisih cabang = FLAG PENGAWASAN,
-- bukan penolakan dan bukan alpa. Ikut filosofi absen HP "WiFi = SAKSI bukan
-- GERBANG" — dokter bisa saja tukar jadwal dengan rekannya dan itu sah; yang
-- sistem tahu cuma "hari ini beda dari biasanya". Jenis baru langsung masuk
-- layar Pengawasan yang sudah ada dan bisa ditutup borongan — nol layar baru.
--
-- Nilai lama WAJIB ikut disebut utuh (lihat catatan MODIFY di bagian 1).

SET @jenis := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'att_flag' AND COLUMN_NAME = 'jenis');

SET @sql := IF(@jenis IS NOT NULL AND LOCATE('cabang_beda_jadwal', @jenis) = 0,
  "ALTER TABLE `att_flag`
     MODIFY `jenis` enum('ip_asing','perangkat_ganda','pola_ip','luar_seharian',
                         'jam_janggal','single_scan','perangkat_desktop',
                         'cabang_beda_jadwal') NOT NULL",
  "SELECT 'att_flag.jenis sudah punya cabang_beda_jadwal (atau tabel tak ada), dilewati' AS info");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ─────────────────────────────────────────────────────────────────────────────
-- 5. GERBANG: aturan Dokter tak boleh punya baris hari
-- ─────────────────────────────────────────────────────────────────────────────
-- Kalau suatu saat ada yang menambahkannya lewat UI Aturan Kerja, dua sumber
-- kebenaran hidup lagi dan alpa palsu kembali — tanpa gejala. Dijadikan galat
-- keras supaya ketahuan di sini, bukan di kartu absen dokter bulan depan.

DROP PROCEDURE IF EXISTS `dok_cek_jadwal`;
DELIMITER $$
CREATE PROCEDURE `dok_cek_jadwal`()
BEGIN
  DECLARE n INT DEFAULT 0;

  SELECT COUNT(*) INTO n
  FROM `att_work_rule_day` d
  JOIN `att_work_rule` r ON r.`id` = d.`rule_id`
  WHERE r.`tipe` = 'jadwal';

  IF n > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'GAGAL: ada aturan bertipe `jadwal` yang punya baris att_work_rule_day. Aturan jadwal TIDAK boleh punya jam sendiri — jamnya dibaca dari jadwal praktik dokter. Hapus baris harinya dulu.';
  END IF;
END$$
DELIMITER ;

CALL dok_cek_jadwal();

DROP PROCEDURE IF EXISTS `dok_cek_jadwal`;


-- ============================================================================
-- VERIFIKASI setelah jalan (JANGAN DILEWATI)
-- ============================================================================
-- 1. enum sudah bertiga:
--      SELECT COLUMN_TYPE FROM information_schema.COLUMNS
--       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='att_work_rule'
--         AND COLUMN_NAME='tipe';   -- enum('baku','fleksibel','jadwal')
--
-- 2. Aturan Dokter ada, TEPAT satu, dan tanpa baris hari:
--      SELECT r.id, r.nama, r.tipe,
--             (SELECT COUNT(*) FROM att_work_rule_day d WHERE d.rule_id=r.id) AS baris_hari
--        FROM att_work_rule r WHERE r.nama='Dokter';   -- baris_hari HARUS 0
--
-- 3. Role Dokter menunjuk aturan itu:
--      SELECT g.nama, g.att_default_rule_id, r.nama AS aturan, r.tipe
--        FROM group_path g LEFT JOIN att_work_rule r ON r.id=g.att_default_rule_id
--       WHERE g.nama='Dokter';
--
-- 4. Aturan lama TIDAK tersentuh — jumlahnya harus sama seperti sebelum ini:
--      SELECT tipe, COUNT(*) FROM att_work_rule GROUP BY tipe;
--
-- 5. Tak ada orang yang tanpa sengaja dipindah ke aturan jadwal:
--      SELECT u.nama, r.nama FROM att_user_rule ur
--        JOIN att_work_rule r ON r.id=ur.rule_id JOIN users u ON u.id=ur.user_id
--       WHERE r.tipe='jadwal';    -- kosong sampai akun dokter dibuat
--
-- 6. Jenis flag baru tersedia:
--      SELECT COLUMN_TYPE FROM information_schema.COLUMNS
--       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='att_flag'
--         AND COLUMN_NAME='jenis';   -- harus memuat 'cabang_beda_jadwal'
-- ============================================================================
