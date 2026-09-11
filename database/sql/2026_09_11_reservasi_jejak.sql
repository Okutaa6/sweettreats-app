-- ============================================================================
-- Jejak reservasi — created_by + alasan batal (rencana: docs/handoff/
-- data-reservasi-2026-09-11.md §7.4, §7.5, §7.6 · §15 butir 1)
--
-- ⛔ TIDAK BISA DIISI MUNDUR. `audit_log` punya NOL baris reservasi, jadi 6.400
--    reservasi lama tak akan pernah bisa diatribusikan. Pencatatan dimulai dari
--    hari fitur ini naik — jangan menjanjikan angka historis ke siapa pun.
--    Semua kolom di bawah NULL untuk baris lama, dan itu memang benar:
--    NULL = "tidak tercatat", bukan "tidak ada".
--
-- ⚠️ Nama kolom `cancel_reason` + `cancel_reason_note` SENGAJA meniru
--    `set/cancel_appt` Omnicare (§11) supaya saat reservasi kelak didorong ke
--    sana (tiket IT-2026-011) kolomnya tinggal diteruskan tanpa pemetaan.
--    NILAI-nya (slug `pasien_sakit` dkk) adalah milik kita — spek vendor
--    `docs/api/omnicare-open-api-2026-08-10.md` TIDAK ADA di repo ini, jadi
--    daftar nilai yang vendor terima BELUM terkonfirmasi. Kalau nanti vendor
--    punya daftarnya sendiri, yang berubah cuma tabel pemetaan slug→nilai
--    vendor di RawCusReservation::cancelReasonOptions(), bukan skema ini.
--
-- Idempoten: pola prosedur yang sama dengan
-- 2026_08_21_alur_persetujuan_delegasi.sql. Aman diulang — SQL prod
-- dijalankan TANGAN, sekali ragu lalu mengulang tidak boleh berhenti dengan
-- "ERROR 1060 Duplicate column name".
--
-- ⚠️ DI LOKAL (XAMPP) BERKAS INI BISA MEMATIKAN mysqld. Terjadi 2026-09-11:
--    `CALL rj_add_col(...)` pertama langsung "ERROR 2013 Lost connection" dan
--    mysqld mati — pembacaan information_schema memang sudah dikenal
--    mematikan mysqld lokal di mesin ini. Di DEV & PROD pola yang sama aman
--    (dipakai 2026_08_18/19/21). Kalau perlu kolomnya di LOKAL, jalankan ALTER
--    polos sekali saja, bukan berkas ini:
--      ALTER TABLE raw_cus_reservation
--        ADD COLUMN `created_by` VARCHAR(255) DEFAULT NULL,
--        ADD COLUMN `created_via` VARCHAR(32) DEFAULT NULL,
--        ADD COLUMN `cancel_reason` VARCHAR(32) DEFAULT NULL,
--        ADD COLUMN `cancel_reason_note` VARCHAR(255) DEFAULT NULL,
--        ADD INDEX `idx_rcr_created_by` (`created_by`, `created_at`),
--        ADD INDEX `idx_rcr_cancel_reason` (`cancel_reason`);
--    (`idx_audit_model` ternyata SUDAH ADA di lokal — wajar kalau ia mengeluh
--     "Duplicate key name"; itu bukan kegagalan.)
--
-- deploy.sh TIDAK menjalankan SQL. Jalankan berkas ini MANUAL.
-- Urutan aman: SQL DULU, baru deploy kode. Kode baru menulis `created_by`;
-- kalau kodenya naik lebih dulu, tiap insert reservasi gagal "Unknown column".
-- ============================================================================

DROP PROCEDURE IF EXISTS rj_add_col;
DELIMITER $$
CREATE PROCEDURE rj_add_col(IN kolom VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'raw_cus_reservation'
                   AND COLUMN_NAME = kolom) THEN
    SET @s = CONCAT('ALTER TABLE raw_cus_reservation ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

-- users.id di sistem ini UUID/VARCHAR, BUKAN integer — samakan dengan
-- `updated_by` yang sudah ada (VARCHAR 255). Kalau BIGINT, UUID ke-cast jadi 0
-- dan semua reservasi seolah dibuat orang yang sama.
CALL rj_add_col('created_by',
  "`created_by` VARCHAR(255) DEFAULT NULL COMMENT 'users.id pembuat reservasi. NULL = dibuat bukan oleh user login (form publik) ATAU baris lama sebelum 2026-09-11 — bedakan lewat created_via'");

-- Kenapa ada `created_via` dan bukan cuma `created_by`: form publik
-- (LandingReservationController) TIDAK punya user login. Memaksa user id palsu
-- di situ akan mengarang atribusi. `created_by` NULL + `created_via` terisi
-- menjawab "siapa" dengan jujur: "bukan siapa-siapa, ini masuk dari pintu X".
-- Ini juga satu-satunya cara membedakan NULL-baris-lama dari NULL-form-publik.
CALL rj_add_col('created_via',
  "`created_via` VARCHAR(32) DEFAULT NULL COMMENT 'pintu masuk: admin | import | import_auto | landing. NULL = baris lama (sebelum 2026-09-11)'");

CALL rj_add_col('cancel_reason',
  "`cancel_reason` VARCHAR(32) DEFAULT NULL COMMENT 'slug alasan batal (lihat RawCusReservation::cancelReasonOptions). Nama kolom meniru set/cancel_appt Omnicare'");

CALL rj_add_col('cancel_reason_note',
  "`cancel_reason_note` VARCHAR(255) DEFAULT NULL COMMENT 'keterangan bebas alasan batal. WAJIB kalau cancel_reason = lainnya. Nama kolom meniru set/cancel_appt Omnicare'");

DROP PROCEDURE IF EXISTS rj_add_col;


-- Indeks: "siapa membuat booking" (§8 — hari ini TIDAK DIUKUR SAMA SEKALI)
-- akan jadi laporan per-orang/per-pintu. Tanpa indeks itu full scan 6.400+ baris
-- yang terus tumbuh ~1.500/bulan.
SET @has_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'raw_cus_reservation'
      AND INDEX_NAME   = 'idx_rcr_created_by'
);
SET @sql := IF(@has_idx = 0,
    'ALTER TABLE raw_cus_reservation ADD INDEX `idx_rcr_created_by` (`created_by`, `created_at`)',
    'SELECT "idx_rcr_created_by already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'raw_cus_reservation'
      AND INDEX_NAME   = 'idx_rcr_cancel_reason'
);
SET @sql := IF(@has_idx = 0,
    'ALTER TABLE raw_cus_reservation ADD INDEX `idx_rcr_cancel_reason` (`cancel_reason`)',
    'SELECT "idx_rcr_cancel_reason already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ============================================================================
-- Riwayat perubahan TIDAK memakai tabel baru — memakai `audit_log` yang sudah
-- ada (kolomnya sudah pas: user_id, user_name, action, model_type, model_id,
-- description, old_values, new_values, ip_address, created_at). Tabel itu sudah
-- dipakai UserManagement, ItTicket, petty cash, AttFlag. Tidak ada DDL di sini.
--
-- Indeks pencarian per-reservasi: audit_log akan mulai menerima baris
-- model_type='RawCusReservation'. Halaman "Riwayat" membaca
-- WHERE model_type=? AND model_id=? — tanpa indeks itu full scan tabel yang
-- dipakai SEMUA modul (6.456 baris dan tumbuh dari banyak arah).
-- ============================================================================
SET @has_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'audit_log'
      AND INDEX_NAME   = 'idx_audit_model'
);
SET @sql := IF(@has_idx = 0,
    'ALTER TABLE audit_log ADD INDEX `idx_audit_model` (`model_type`, `model_id`, `created_at`)',
    'SELECT "idx_audit_model already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ── Verifikasi (jalankan sesudahnya, harus 4 baris + 3 indeks) ──────────────
-- SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
--  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raw_cus_reservation'
--    AND COLUMN_NAME IN ('created_by','created_via','cancel_reason','cancel_reason_note');
