-- =====================================================================
-- Tracking Ortho — sebaran irama pasien (kerapatan) — 2026-10-02
-- IDEMPOTENT (guard information_schema; aman dijalankan berulang).
-- Jalankan di DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- ---------------------------------------------------------------------
-- Konteks
-- ---------------------------------------------------------------------
-- Tahap C membuat usulan jadwal berangkat dari irama pasien, dan menandai
-- keyakinannya lewat `ort_control.sch_basis_n` — BANYAKNYA selang yang
-- dipakai menghitung irama. Layar menyebut usulan "kuat" kalau selangnya 6
-- ke atas.
--
-- Itu salah ukur, dan saya yang mengirimnya. Banyaknya data bukan
-- konsistensinya: pasien berselang 20 · 90 · 25 · 110 punya LEBIH BANYAK
-- selang daripada pasien berselang 28 · 28 · 28, tapi iramanya jauh lebih
-- tidak bisa ditebak. Dengan ukuran lama, yang liar justru dinilai lebih
-- meyakinkan.
--
-- Handoff HUB 1 Okt sudah memberi rumus yang benar dan sudah mengukurnya di
-- prod: pasien disebut beririma RAPAT kalau (max − min) / median <= 0,5, dan
-- hanya **24%** pasien yang memenuhinya. Itu angka yang mengikat seluruh
-- rancangan baru: untuk 76% sisanya, tanggal yang kita hitung adalah tebakan
-- dua minggu, dan layar tidak boleh menawarkannya lewat tombol sekali klik.
--
-- ---------------------------------------------------------------------
-- Yang dilakukan skrip ini
-- ---------------------------------------------------------------------
--   1. ort_case.gap_spread_pct — sebaran selang kunjungan, dalam PERSEN
--      median. 0 = selalu sama persis; 50 = batas "rapat"; 300 = liar.
--      Disimpan sebagai persen bulat supaya tidak perlu kolom pecahan dan
--      tetap terbaca orang saat dikueri tangan.
--
-- ---------------------------------------------------------------------
-- Yang SENGAJA TIDAK dilakukan
-- ---------------------------------------------------------------------
--   - `sch_basis_n` TIDAK di-DROP. Ia tetap jujur sebagai "dari berapa
--     selang" dan tetap ditampilkan; yang berhenti adalah PEMAKAIANNYA
--     sebagai penentu keyakinan.
--   - TIDAK menyentuh enum `sch_source`. Nilai `dokter` (merekam saran lisan
--     dokter) sengaja ditunda — keputusan Ikhsan, bukan IT.
--   - TIDAK ada index. Kolom ini dibaca per-baris yang sudah terambil, tidak
--     pernah jadi penyaring.
--   - TIDAK menghitung ulang untuk baris lama. Itu pekerjaan
--     `php artisan ort:rollup`, dan WAJIB dijalankan sesudah skrip ini.
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

CALL `__ort_add_col`('ort_case', 'gap_spread_pct',
  "`gap_spread_pct` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Sebaran selang kunjungan = (max-min)/median, dalam persen. <=50 berarti irama RAPAT (rumus handoff HUB, 24% pasien di prod). NULL = belum cukup selang' AFTER `median_gap_days`");

DROP PROCEDURE IF EXISTS `__ort_add_col`;

-- =====================================================================
-- SESUDAH SKRIP INI — WAJIB, jangan dilewati:
--
--   php artisan ort:rollup
--   php artisan ort:rapikan-usulan --commit
--
-- Yang pertama mengisi gap_spread_pct; tanpa itu SEMUA pasien terbaca
-- "riwayat belum cukup" dan tidak ada satu pun yang dapat tombol sekali
-- klik — salah ke arah yang aman, tapi tetap salah, dan tanpa pesan galat.
-- Yang kedua menulis ulang tingkat keyakinan pada usulan yang sudah ada.
--
-- Verifikasi (di prod, sekitar 24% yang rapat):
--   SELECT COUNT(*) total,
--          SUM(gap_spread_pct IS NOT NULL) terukur,
--          SUM(gap_spread_pct <= 50) rapat,
--          ROUND(SUM(gap_spread_pct <= 50) / NULLIF(SUM(gap_spread_pct IS NOT NULL), 0) * 100, 1) persen_rapat
--     FROM ort_case WHERE status = 'aktif';
-- =====================================================================
