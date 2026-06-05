-- =====================================================================
-- Composite index untuk Executive Report trend queries — 2026-06-05
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Masalah:
--   Query trend 12-bulan di ExecutiveReportController (buildTreatmentTrends,
--   buildDoctorTrends) memfilter `item_code IN (...)` / `doc_reff_id IN (...)`
--   bersamaan dengan range `trx_date BETWEEN ...`. Index single-column existing
--   (idx_omnicare_sales_item = item_code saja, idx_omnicare_sales_doctor =
--   doc_reff_id saja) tidak cukup — EXPLAIN menunjukkan type=ALL (full table
--   scan 135rb baris, ~1.7-1.8 detik per query).
--
-- Solusi:
--   Composite index (item_code, trx_date) & (doc_reff_id, trx_date) supaya
--   MySQL bisa seek per kode/dokter lalu range-scan tanggal — bukan scan semua.
--
-- Idempoten: guard via information_schema (aman dijalankan ulang).
-- =====================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `__st_add_idx2`$$
CREATE PROCEDURE `__st_add_idx2`(
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

-- Trend per perawatan: WHERE item_code IN (...) AND trx_date BETWEEN ...
CALL __st_add_idx2('omnicare_sales_detail', 'idx_oms_item_date', '`item_code`, `trx_date`');

-- Trend per dokter: WHERE doc_reff_id IN (...) AND trx_date BETWEEN ...
CALL __st_add_idx2('omnicare_sales_detail', 'idx_oms_doctor_date', '`doc_reff_id`, `trx_date`');

DROP PROCEDURE IF EXISTS `__st_add_idx2`;

-- =====================================================================
-- Verifikasi:
--   SHOW INDEX FROM omnicare_sales_detail WHERE Key_name LIKE 'idx_oms_%';
--   EXPLAIN SELECT item_code, SUM(net_sell_price) FROM omnicare_sales_detail
--     WHERE item_code IN ('26CON178') AND trx_date BETWEEN '2025-07-01' AND '2026-06-30'
--     GROUP BY item_code;  -- key harus = idx_oms_item_date (bukan kosong/ALL)
-- =====================================================================
