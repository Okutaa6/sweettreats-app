-- =====================================================================
-- Reservasi — Kolom tanggal jadi NULLABLE (waiting list tanpa tanggal) — 2026-08-12
-- IDEMPOTENT via INFORMATION_SCHEMA check.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Pasien yang masuk waiting list (is_waiting=1) tidak selalu punya tanggal
--   pasti — mereka sekadar antre & tanggalnya ditentukan nanti saat ada slot.
--   Sebelumnya `date` NOT NULL memaksa sales mengisi tanggal walau belum tahu.
--   Kolom diubah jadi NULL supaya reservasi waitlist boleh disimpan tanpa tanggal.
--
-- Catatan:
--   - Baris lama (yang selalu punya tanggal) tetap valid — hanya melonggarkan
--     constraint, tidak mengubah data.
--   - Validasi aplikasi tetap mewajibkan tanggal untuk reservasi NON-waitlist
--     (rule `required_unless:is_waiting,1` di ReservationController::store()).
-- =====================================================================

-- raw_cus_reservation.date → NULL
SET @is_nullable := (
    SELECT IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'raw_cus_reservation'
      AND COLUMN_NAME  = 'date'
);
SET @sql := IF(@is_nullable = 'NO',
    'ALTER TABLE `raw_cus_reservation`
        MODIFY COLUMN `date` date NULL DEFAULT NULL
        COMMENT "Tanggal reservasi. NULL hanya untuk waiting list yang belum ditentukan tanggalnya."',
    'SELECT "raw_cus_reservation.date already nullable (or column missing)" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
