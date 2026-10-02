-- =====================================================================
-- Tracking Ortho — HASIL follow-up: `fu_result` + `fu_note` — 2026-10-05
-- IDEMPOTENT (guard information_schema lewat prosedur sementara; kolom yang
-- sudah ada dilewati, menjalankannya berulang menghasilkan keadaan identik).
-- Jalankan di DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- ---------------------------------------------------------------------
-- Konteks
-- ---------------------------------------------------------------------
-- Sampai hari ini tombol "Catat Follow-up" sekali klik tanpa form: ia menulis
-- fu_date (hari ini), fu_channel (bawaan 'wa'), dan fu_by. HASIL teleponnya —
-- pasien bersedia, tidak respon, minta jadwal ulang, menolak, salah nomor —
-- tidak pernah tercatat di mana pun. Akibatnya pertanyaan paling dasar tim
-- ("berapa pasien tidak respon bulan ini", "siapa yang minta dihubungi lagi")
-- tidak bisa dijawab dari data, dan Sales mengulang telepon ke nomor yang
-- sudah ketahuan salah.
--
-- Prompt 4 Okt 2026 memintanya; Reza memilih PILIHAN TETAP + CATATAN BEBAS:
-- pilihan tetap supaya bisa dihitung, catatan bebas supaya kasus di luar
-- kategori tetap tertampung. Kamus nilainya satu-satunya di
-- App\Models\OrtControl::FU_HASIL — bukan ENUM di DB, supaya menambah pilihan
-- tidak butuh ALTER TABLE (varchar + validasi aplikasi, pola yang sama dengan
-- rsv_status).
--
-- ---------------------------------------------------------------------
-- Yang dilakukan skrip ini
-- ---------------------------------------------------------------------
--   Menambah DUA kolom NULL-able ke ort_control, sesudah fu_by:
--     fu_result  varchar(24)  kunci hasil (dihubungi|tidak_respon|
--                             minta_jadwal_ulang|menolak|salah_nomor)
--     fu_note    varchar(255) catatan bebas
--   Keduanya NULL untuk semua baris lama — follow-up yang sudah tercatat
--   sebelum ini memang tidak punya hasil, dan itu dibiarkan jujur.
--
-- ---------------------------------------------------------------------
-- Yang SENGAJA TIDAK dilakukan
-- ---------------------------------------------------------------------
--   - TIDAK ada backfill. Tidak ada data historis yang bisa dijadikan hasil.
--   - TIDAK ada index. Kolom ini dibaca per-baris yang sudah terambil; kalau
--     nanti ada laporan "hasil per bulan", index (fu_date, fu_result) baru
--     layak dipertimbangkan — saat itu, bukan sekarang.
--   - TIDAK memakai ENUM. Lihat Konteks.
--   - TIDAK mengubah fu_date/fu_channel/fu_by.
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

CALL `__ort_add_col`('ort_control', 'fu_result',
  "`fu_result` varchar(24) DEFAULT NULL COMMENT 'HASIL follow-up: dihubungi|tidak_respon|minta_jadwal_ulang|menolak|salah_nomor. Kamus di OrtControl::FU_HASIL, divalidasi aplikasi. NULL = follow-up lama sebelum kolom ini ada' AFTER `fu_by`");

CALL `__ort_add_col`('ort_control', 'fu_note',
  "`fu_note` varchar(255) DEFAULT NULL COMMENT 'Catatan bebas follow-up, pelengkap fu_result untuk yang tidak masuk kategori' AFTER `fu_result`");

DROP PROCEDURE IF EXISTS `__ort_add_col`;

-- Verifikasi:
--   SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
--     FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA = DATABASE()
--      AND TABLE_NAME = 'ort_control'
--      AND COLUMN_NAME IN ('fu_result', 'fu_note');
-- Harus 2 baris, keduanya YES.
-- =====================================================================
