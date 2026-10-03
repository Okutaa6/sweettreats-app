-- =====================================================================
-- Tracking Ortho — DOKTER per kontrol: `doctor_ref_id` + `doctor_name` — 2026-10-06
-- IDEMPOTENT (guard information_schema lewat prosedur sementara; kolom yang
-- sudah ada dilewati, menjalankannya berulang menghasilkan keadaan identik).
-- Jalankan di DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- ---------------------------------------------------------------------
-- Konteks
-- ---------------------------------------------------------------------
-- `ort_case.doctor_name` adalah SNAPSHOT dari kunjungan pertama yang menyebut
-- item ortho (OrtSeedCases.php:455-456) dan tidak pernah disegarkan sejak itu.
-- Jadi kolom "Dokter" di layar menampilkan dokter yang MEMASANG bracket, bukan
-- yang sedang menangani. Kalau pasien dioper ke dokter lain — hal yang biasa
-- terjadi antar cabang — layarnya tetap menyebut dokter lama, dan Sales
-- menelepon pasien dengan nama dokter yang salah.
--
-- prompt_jadwal_kontrol.md (Phase 1, kolom 2) memintanya DINAMIS: dokter =
-- yang terakhir menangani, dideteksi dari data terbaru antara kunjungan
-- Omnicare, reservasi, atau transfer manual.
--
-- Datanya SUDAH ditarik tiap malam: OrtVisits.php:121-122 membawa `dokter` dan
-- `doc_reff` per kunjungan. Tapi ort_control tidak punya tempat menyimpannya,
-- jadi nilainya dibuang begitu saja setiap tarikan. Dua kolom ini tempatnya.
--
-- Efek keduanya, dari satu perubahan yang sama:
--   1. Kolom "Dokter" jadi benar — OrtRollup memilih yang paling baru.
--   2. Riwayat kontrol bisa berbunyi "4 Okt 2026, Sunter, drg. Nama" seperti
--      diminta prompt (Phase 2). Sebelum ini, nama dokter per baris riwayat
--      tidak ada sumbernya sama sekali.
--
-- ---------------------------------------------------------------------
-- Yang dilakukan skrip ini
-- ---------------------------------------------------------------------
--   Menambah DUA kolom NULL-able ke ort_control, di dalam blok "Kunjungan"
--   sesudah omnicare_encounter_id — karena dokter adalah FAKTA KUNJUNGAN,
--   bukan properti jadwal:
--     doctor_ref_id  varchar(20)  kode Omnicare doc_reff_id
--     doctor_name    varchar(255) snapshot nama untuk tampilan
--   Keduanya NULL untuk semua baris lama.
--
-- ---------------------------------------------------------------------
-- Yang SENGAJA TIDAK dilakukan
-- ---------------------------------------------------------------------
--   - TIDAK ada backfill di dalam SQL ini. Dokter per kontrol lampau hanya ada
--     di omnicare_sales_detail, dan mencocokkannya butuh aturan episode yang
--     hidup di PHP (OrtVisits + OrtItemRules), bukan di SQL. Jalankan
--     `php artisan ort:pull-visits` lalu `OrtRollup::refreshSemua()` sesudah
--     skrip ini — itu yang mengisinya. Sampai itu dijalankan, kolom Dokter
--     masih menampilkan snapshot lama, dan itu keadaan yang jujur.
--   - TIDAK ada FOREIGN KEY ke raw_doctor. doctor_ref_id itu kode OMNICARE,
--     bukan raw_doctor.id — jembatannya raw_doctor.omnicare_doc_id. Dan
--     collation prod campur (lihat 2026_09_24_ort_audit_collation_prod.sql),
--     jadi modul ini nol FK dan nol JOIN lintas keluarga tabel.
--   - TIDAK ada index. Kolom ini dibaca per-baris yang sudah terambil lewat
--     idx_ort_control_case. Saringan "semua kontrol dokter X" belum ada di
--     layar mana pun; kalau nanti ada, index (doctor_ref_id, visit_date) baru
--     layak dipertimbangkan — saat itu, bukan sekarang.
--   - TIDAK menyentuh ort_case.doctor_ref_id/doctor_name. Keduanya tetap jadi
--     tempat hasil rollup mendarat; skema aslinya memang sudah menyebutnya
--     "Snapshot untuk tampilan".
-- =====================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `__ort_add_col`$$
CREATE PROCEDURE `__ort_add_col`(
  IN p_tbl VARCHAR(64), IN p_col VARCHAR(64), IN p_ddl TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_tbl
      AND COLUMN_NAME  = p_col
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_tbl, '` ADD COLUMN ', p_ddl);
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$

DELIMITER ;

CALL `__ort_add_col`('ort_control', 'doctor_ref_id',
  "`doctor_ref_id` varchar(20) DEFAULT NULL COMMENT 'omnicare doc_reff_id dokter yang menangani KUNJUNGAN ini. JANGAN cocokkan dokter lewat nama. NULL = kontrol belum dikunjungi, atau kunjungan lama sebelum kolom ini ada' AFTER `omnicare_encounter_id`");

CALL `__ort_add_col`('ort_control', 'doctor_name',
  "`doctor_name` varchar(255) DEFAULT NULL COMMENT 'Snapshot nama dokter untuk tampilan riwayat kontrol. Diisi tarikan malam bersama doctor_ref_id' AFTER `doctor_ref_id`");

DROP PROCEDURE IF EXISTS `__ort_add_col`;

-- Verifikasi:
--   SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
--     FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA = DATABASE()
--      AND TABLE_NAME = 'ort_control'
--      AND COLUMN_NAME IN ('doctor_ref_id', 'doctor_name');
-- Harus 2 baris, keduanya YES.
--
-- Sesudah tarikan malam pertama, cakupannya:
--   SELECT COUNT(*) AS terbuka_atau_tutup,
--          SUM(doctor_ref_id IS NOT NULL) AS ada_dokter
--     FROM ort_control WHERE visit_date IS NOT NULL;
-- =====================================================================
