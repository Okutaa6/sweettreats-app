-- =====================================================================
-- Contact-anchored patients — 1 nomor HP bisa banyak pasien — 2026-05-18
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   raw_contact  = ANCHOR per nomor (1 baris per nomor ter-normalisasi)
--   raw_customer = PASIEN yang dimiliki sebuah contact (banyak per contact)
--
-- Reservasi & journey TETAP keyed ke raw_customer_id — TIDAK ada perubahan
-- skema reservasi/journey (raw_cus_journey.raw_contact_id sudah ada di prod;
-- di sini hanya ditambahkan INDEX-nya).
--
-- Idempoten: aman dijalankan ulang (guard via information_schema).
-- File ini HANYA skema. Backfill data ada di 2026_05_18_contact_backfill.sql
-- dan WAJIB dijalankan SETELAH file ini.
-- =====================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `__st_add_col`$$
CREATE PROCEDURE `__st_add_col`(
  IN p_tbl VARCHAR(64), IN p_col VARCHAR(64), IN p_ddl TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_tbl
      AND COLUMN_NAME = p_col
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_tbl, '` ADD COLUMN ', p_ddl);
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$

DROP PROCEDURE IF EXISTS `__st_add_idx`$$
CREATE PROCEDURE `__st_add_idx`(
  IN p_tbl VARCHAR(64), IN p_idx VARCHAR(64), IN p_cols VARCHAR(255)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_tbl
      AND INDEX_NAME = p_idx
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_tbl, '` ADD KEY `', p_idx, '` (', p_cols, ')');
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$

DELIMITER ;

-- raw_customer.raw_contact_id : jangkar nomor HP (soft FK ke raw_contact.id;
-- varchar(255) supaya match persis tipe PK raw_contact, hindari isu collation
-- saat JOIN). Tanpa FK constraint — proyek ini relasi app-level (Eloquent).
CALL __st_add_col(
  'raw_customer', 'raw_contact_id',
  "`raw_contact_id` varchar(255) DEFAULT NULL COMMENT 'Anchor nomor HP; banyak pasien bisa share 1 raw_contact' AFTER `phone`"
);

-- raw_customer.relasi : pembeda pasien dalam 1 nomor.
-- NOT NULL DEFAULT 'diri_sendiri' supaya insert lama (Cekat/webhook) yang tidak
-- menyebut kolom ini tetap valid tanpa error. Nilai divalidasi di app:
-- diri_sendiri | pasangan | anak | orang_tua | lainnya
CALL __st_add_col(
  'raw_customer', 'relasi',
  "`relasi` varchar(20) NOT NULL DEFAULT 'diri_sendiri' COMMENT 'Relasi pasien thd pemilik nomor: diri_sendiri|pasangan|anak|orang_tua|lainnya' AFTER `raw_contact_id`"
);

-- Index pendukung lookup & backfill.
CALL __st_add_idx('raw_customer', 'idx_raw_customer_contact', '`raw_contact_id`');
CALL __st_add_idx('raw_cus_journey', 'idx_raw_cus_journey_contact', '`raw_contact_id`');

DROP PROCEDURE IF EXISTS `__st_add_col`;
DROP PROCEDURE IF EXISTS `__st_add_idx`;

-- =====================================================================
-- Verifikasi (jalankan manual setelah eksekusi):
--   SHOW COLUMNS FROM raw_customer LIKE 'raw_contact_id';
--   SHOW COLUMNS FROM raw_customer LIKE 'relasi';
--   SHOW INDEX FROM raw_customer  WHERE Key_name = 'idx_raw_customer_contact';
--   SHOW INDEX FROM raw_cus_journey WHERE Key_name = 'idx_raw_cus_journey_contact';
-- Jalankan file ini 2x → run kedua harus no-op (tidak error, tidak ubah apa pun).
-- =====================================================================
