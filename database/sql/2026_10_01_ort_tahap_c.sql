-- =====================================================================
-- Tracking Ortho — Tahap C: kejujuran tanggal pasang + irama pasien — 2026-10-01
-- IDEMPOTENT (guard information_schema + backfill UPDATE yang menyaring
-- barisnya sendiri; aman dijalankan berulang).
-- Jalankan di DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- ---------------------------------------------------------------------
-- Konteks
-- ---------------------------------------------------------------------
-- Audit HUB 1 Okt 2026 menemukan dua hal yang tidak menghalangi deploy tapi
-- membuat halaman ini mengatakan hal yang tidak diketahuinya:
--
-- 1. TANGGAL PASANG YANG SEBENARNYA PERKIRAAN DITAMPILKAN SEBAGAI FAKTA.
--    45,7% episode tanggal pasangnya DIPERKIRAKAN dari kunjungan ortho
--    pertama yang terlihat di cermin rekam medis — brief §6.1 mencatat 132
--    nama item pemasangan berbeda, jadi sebagian besar pemasangan memang
--    tidak tertangkap pola. Penyemai SUDAH menghitungnya dan menulis satu
--    kalimat ke `note`, tapi kalimat itu tidak bisa dikueri dan tidak pernah
--    sampai ke layar. Drawer menulis "berjalan 57 bulan" dengan yakin untuk
--    pasien yang sebetulnya cuma "terakhir terlihat 5 tahun lalu".
--
-- 2. USULAN JADWAL MEMAKAI +30 HARI UNTUK SEMUA ORANG.
--    Diukur di prod: +30 meleset 22 hari dari kunjungan yang benar-benar
--    terjadi; irama pasien itu sendiri meleset 14 hari dan menang di 86%
--    kasus. Irama disimpan sebagai MEDIAN, bukan rata-rata — `avg_gap_days`
--    yang sudah ada tertarik pencilan: satu kali pasien hilang 8 bulan lalu
--    kembali sudah cukup menggeser rata-ratanya puluhan hari.
--
-- ---------------------------------------------------------------------
-- Yang dilakukan skrip ini
-- ---------------------------------------------------------------------
--   1. ort_case.install_is_estimated  — bendera yang bisa dikueri, pengganti
--      kalimat di `note`. Layar memakai ini untuk menulis "± Mei '24" dan
--      "sudah berjalan MINIMAL 17 bulan", bukan angka pasti.
--   2. ort_case.median_gap_days       — irama pasien. Diisi OrtRollup
--      berdampingan dengan avg_gap_days yang TETAP ADA.
--   3. ort_control.sch_basis          — dasar sebuah usulan: 'irama'|'default'.
--   4. ort_control.sch_basis_n        — dari berapa selang irama itu dihitung.
--      Inilah yang membedakan usulan dari 12 kunjungan dengan usulan dari 2.
--   5. Backfill bendera (1) dari baris `note` yang sudah ditulis penyemai,
--      supaya data yang SUDAH disemai tidak perlu disemai ulang.
--
-- ---------------------------------------------------------------------
-- Yang SENGAJA TIDAK dilakukan
-- ---------------------------------------------------------------------
--   - `avg_gap_days` TIDAK di-DROP dan tidak berhenti diisi. Ia masih dipakai
--     di kartu antrean dan drawer; median mendampinginya, tidak menggantikan.
--   - Kalimat di `note` TIDAK dihapus. Ia menyebut SEJAK KAPAN cermin rekam
--     medis dibaca — konteks yang tidak muat di sebuah bendera boolean, dan
--     satu-satunya tempat angka itu tercatat per baris.
--   - `sch_source` TIDAK disentuh. Enum itu menjawab SIAPA yang menjadwalkan
--     (manual/sistem/rekam_medis); dasar usulan adalah sumbu yang berbeda dan
--     mendapat kolomnya sendiri. Menumpuknya ke satu enum akan membuat
--     "usulan sistem dari irama" dan "usulan sistem default" tak terbedakan.
--   - TIDAK ada index baru. Keempat kolom ini dibaca per-baris yang sudah
--     terambil, tidak pernah jadi penyaring.
--   - TIDAK menghitung ulang median untuk baris lama. Itu pekerjaan
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

-- ── 1 & 2 · ort_case ─────────────────────────────────────────────────
CALL `__ort_add_col`('ort_case', 'install_is_estimated',
  "`install_is_estimated` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Tanggal pasang DIPERKIRAKAN dari kunjungan ortho pertama yang terlihat, bukan dari baris pemasangan. Layar wajib menandainya' AFTER `install_place_id`");

CALL `__ort_add_col`('ort_case', 'median_gap_days',
  "`median_gap_days` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Irama pasien: MEDIAN selang antar kunjungan. Dipakai usulan jadwal. Median, bukan rata-rata: avg_gap_days tertarik pencilan' AFTER `avg_gap_days`");

-- ── 3 & 4 · ort_control ──────────────────────────────────────────────
CALL `__ort_add_col`('ort_control', 'sch_basis',
  "`sch_basis` varchar(16) DEFAULT NULL COMMENT 'Dasar usulan: irama|default. NULL untuk jadwal yang bukan usulan sistem'");

CALL `__ort_add_col`('ort_control', 'sch_basis_n',
  "`sch_basis_n` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Dari berapa selang kunjungan irama itu dihitung. Penanda keyakinan: 12 selang != 2 selang'");

DROP PROCEDURE IF EXISTS `__ort_add_col`;

-- ── 5 · Backfill bendera perkiraan dari `note` yang sudah ada ────────
--
-- Penyemai menulis kalimat ini sejak hari pertama, jadi datanya memang sudah
-- ada — cuma tidak bisa dikueri. Menyaring dengan LIKE pada awal kalimat,
-- bukan LIKE '%...%', supaya catatan yang KEBETULAN menyebut kata itu di
-- tengah (mis. catatan Sales) tidak ikut tertandai.
--
-- Kondisi `install_is_estimated = 0` membuatnya idempotent sekaligus
-- menjaga koreksi manual: baris yang sudah pernah dibetulkan orang tidak
-- ditulis ulang.
UPDATE `ort_case`
   SET `install_is_estimated` = 1
 WHERE `install_is_estimated` = 0
   AND `note` LIKE 'Tanggal pasang DIPERKIRAKAN%';

-- =====================================================================
-- SESUDAH SKRIP INI — WAJIB, jangan dilewati:
--
--   php artisan ort:rollup
--
-- median_gap_days lahir NULL untuk semua baris lama. Tanpa rollup, usulan
-- jadwal diam-diam jatuh kembali ke +30 untuk SEMUA pasien dan Tahap C
-- tidak berefek apa pun — tanpa satu pun pesan galat.
--
-- Verifikasi (angka perkiraan prod ±45,7%):
--   SELECT COUNT(*) total,
--          SUM(install_is_estimated) perkiraan,
--          ROUND(SUM(install_is_estimated) / COUNT(*) * 100, 1) persen,
--          SUM(median_gap_days IS NOT NULL) ber_irama
--     FROM ort_case;
-- =====================================================================
