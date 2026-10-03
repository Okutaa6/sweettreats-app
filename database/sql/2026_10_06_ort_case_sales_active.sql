-- =====================================================================
-- Tracking Ortho — penilaian Sales "Active / Inactive": `sales_active` — 2026-10-06
-- IDEMPOTENT (guard information_schema lewat dua prosedur sementara; kolom dan
-- index yang sudah ada dilewati, menjalankannya berulang menghasilkan keadaan
-- identik).
-- Jalankan di DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- ---------------------------------------------------------------------
-- Konteks
-- ---------------------------------------------------------------------
-- prompt_jadwal_kontrol.md (Phase 1, Filter + Urutan; Phase 2, toggle) meminta
-- penanda Active/Inactive: "apakah pasien masih rutin kontrol (Active) atau
-- sudah jarang/menghilang (Inactive)", diubah MANUAL oleh Sales, dan pasien
-- Inactive tetap tampil tapi pindah ke baris paling bawah.
--
-- Itu TIDAK BOLEH memakai ort_case.status, dan alasannya ada di skema aslinya
-- (2026_09_23_ort_tracking_schema.sql:79-80):
--     "Status episode. BUKAN berbasis waktu: pasien tetap aktif sampai bracket
--      dilepas. Lama tak kontrol memicu PERINGATAN, bukan mengubah status."
-- `status` adalah FAKTA KLINIS dan diisi MESIN — OrtPullVisits.php:442
-- menandai 'selesai' begitu bracket terdeteksi lepas dari rekam medis.
-- Active/Inactive adalah PENILAIAN MANUSIA. Dua sumbu berbeda; kalau
-- ditumpangkan ke satu kolom, tarikan malam akan menimpa keputusan Sales tanpa
-- jejak, dan pasien yang sengaja "dilepas" akan kembali sendiri tiap malam.
--
-- Satu lagi yang perlu diketahui pembaca berikutnya: daftar kerja menyaring
-- `k.status = 'aktif'` (OrtQuery.php:158). Baris sales_active = 0 HARUS tetap
-- lolos saringan itu — "Inactive" bukan "tidak ditampilkan", ia hanya turun ke
-- bawah. Jangan pernah menambahkan WHERE sales_active = 1.
--
-- ---------------------------------------------------------------------
-- Yang dilakukan skrip ini
-- ---------------------------------------------------------------------
--   Menambah EMPAT kolom ke ort_case, sesudah finished_at:
--     sales_active      tinyint(1)   NOT NULL DEFAULT 1  (1=Active, 0=Inactive)
--     sales_active_at   timestamp    NULL   kapan diubah
--     sales_active_by   varchar(255) NULL   siapa yang mengubah (nama tampilan)
--     sales_active_note varchar(255) NULL   alasan, opsional
--
--   Menambah SATU index:
--     idx_ort_case_sales (owner_place_id, sales_active, last_visit_date)
--   Index ini untuk SARINGAN dan HITUNGAN badge ("· 400 Inactive, di bawah"),
--   BUKAN untuk ORDER BY — urutan mendesaknya ekspresi dan tidak bisa
--   di-index-kan (lihat catatan di bawah).
--
--   tinyint, BUKAN enum. Kamus nilainya di App\Models\OrtCase::SALES_* supaya
--   bucket ketiga ("ditunda", "titip cabang lain") tidak butuh ALTER TABLE di
--   prod — pola yang sama dengan fu_result (2026_10_05) dan rsv_status.
--
--   NOT NULL DEFAULT 1: 1.639 baris prod langsung Active, dan itu benar —
--   "Inactive" adalah penilaian POSITIF yang harus dibuat seseorang. Kolom
--   NULL-able akan memaksa COALESCE di setiap ORDER BY dan melahirkan keadaan
--   ketiga yang tidak punya arti di layar.
--
-- ---------------------------------------------------------------------
-- Yang SENGAJA TIDAK dilakukan
-- ---------------------------------------------------------------------
--   - TIDAK menyentuh ort_case.status sama sekali. Lihat Konteks.
--   - TIDAK ada backfill ke 0 untuk siapa pun. Tidak ada aturan otomatis yang
--     boleh memutuskan pasien "dilepas" — kalau ada, ia bukan penilaian
--     manusia lagi dan kolom ini kehilangan maknanya.
--   - TIDAK memakai enum. Lihat Yang dilakukan.
--   - TIDAK ada tabel audit riwayat toggle. Tiga kolom jejak memenuhi "simpan
--     siapa dan kapan status diubah" yang diminta prompt; riwayat lengkap
--     perubahan adalah pekerjaan lain.
--   - TIDAK ada index untuk ORDER BY. Kunci urut mendesaknya
--     DATEDIFF(hari_ini, last_visit_date) / LEAST(60, GREATEST(21,
--     COALESCE(median_gap_days, 30))) — ekspresi atas dua kolom plus parameter
--     terikat, tidak ada index yang bisa menyimpannya. Konsekuensinya
--     dinyatakan terbuka: idx_ort_control_worklist sekarang MEMBERI urutan
--     sehingga LIMIT 50 berhenti dini; sesudah ini tiap halaman memindai
--     seluruh baris terbuka cabang itu (<= ~1.000 baris sempit di cabang
--     terbesar, muat di sort_buffer). Aman pada ukuran sekarang, dan LINEAR
--     terhadap jumlah kasus aktif per cabang — bukan aman tanpa syarat.
--   - TIDAK menyentuh akses/menu, jadi `php artisan access:rebuild` TIDAK
--     diperlukan untuk skrip ini.
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

-- Prosedur KEDUA, yang contoh baku 2026_10_05 tidak punya — ia sengaja tidak
-- menambah index. Guard-nya information_schema.STATISTICS, bukan COLUMNS.
DROP PROCEDURE IF EXISTS `__ort_add_idx`$$
CREATE PROCEDURE `__ort_add_idx`(
  IN p_tbl VARCHAR(64), IN p_idx VARCHAR(64), IN p_ddl TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_tbl
      AND INDEX_NAME   = p_idx
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_tbl, '` ADD ', p_ddl);
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$

DELIMITER ;

CALL `__ort_add_col`('ort_case', 'sales_active',
  "`sales_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'PENILAIAN SALES, bukan fakta klinis: 1=Active (masih dikejar kontrolnya), 0=Inactive (sudah jarang/menghilang). Diubah MANUAL. Kamus di OrtCase::SALES_AKTIF/SALES_PASIF. JANGAN campur dengan kolom status — itu rekam medis, diisi mesin. Baris 0 tetap DITAMPILKAN, hanya turun ke bawah' AFTER `finished_at`");

CALL `__ort_add_col`('ort_case', 'sales_active_at',
  "`sales_active_at` timestamp NULL DEFAULT NULL COMMENT 'Kapan sales_active terakhir diubah. NULL = belum pernah disentuh manusia' AFTER `sales_active`");

CALL `__ort_add_col`('ort_case', 'sales_active_by',
  "`sales_active_by` varchar(255) DEFAULT NULL COMMENT 'Nama tampilan yang mengubah. Bukan pagar akses, jadi dua orang bernama sama bisa tertukar — risiko yang sama sudah diterima sadar untuk fu_by' AFTER `sales_active_at`");

CALL `__ort_add_col`('ort_case', 'sales_active_note',
  "`sales_active_note` varchar(255) DEFAULT NULL COMMENT 'Alasan dilepas/dikejar lagi, opsional' AFTER `sales_active_by`");

CALL `__ort_add_idx`('ort_case', 'idx_ort_case_sales',
  "KEY `idx_ort_case_sales` (`owner_place_id`, `sales_active`, `last_visit_date`) COMMENT 'Saringan + hitungan badge Active/Inactive per cabang. BUKAN untuk ORDER BY mendesak — itu ekspresi'");

DROP PROCEDURE IF EXISTS `__ort_add_col`;
DROP PROCEDURE IF EXISTS `__ort_add_idx`;

-- Verifikasi:
--   SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
--     FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ort_case'
--      AND COLUMN_NAME LIKE 'sales_active%';
-- Harus 4 baris. sales_active: NO / 1. Tiga sisanya: YES / NULL.
--
--   SELECT INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME
--     FROM information_schema.STATISTICS
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ort_case'
--      AND INDEX_NAME = 'idx_ort_case_sales' ORDER BY SEQ_IN_INDEX;
-- Harus 3 baris: owner_place_id, sales_active, last_visit_date.
--
--   SELECT sales_active, COUNT(*) FROM ort_case GROUP BY sales_active;
-- Sesudah skrip ini: satu baris saja, sales_active = 1.
-- =====================================================================
