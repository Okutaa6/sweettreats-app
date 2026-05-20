-- =====================================================================
-- Backfill contact anchor + laporan konflik — 2026-05-18  (idempoten)
-- Jalankan SETELAH 2026_05_18_contact_anchored_patients.sql
-- DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Butuh REGEXP_REPLACE: MariaDB >= 10.0.5 (prod Hostinger OK) atau MySQL >= 8.0.
-- Jika lokal MySQL 5.7 (tak ada REGEXP_REPLACE), jalankan di staging/prod saja.
--
-- Normalisasi __st_norm_phone() = padanan App\Support\Phone::normalize
-- (best-effort): strip non-digit; 0062->0 ; 62->0 ; tanpa 0 depan -> +0.
-- BATAS: TIDAK memvalidasi panjang 9-14 (baris pendek tetap diproses apa
-- adanya, muncul di laporan konflik). Baris yang sudah ter-MERGE bug
-- firstOrCreate lama (1 row = 2 orang) TIDAK bisa di-split otomatis —
-- hanya terlihat di v_contact_patient_conflicts bila ada >1 BARIS; 1 baris
-- campur tetap invisible -> butuh audit manual (riwayat reservasi/WA).
--
-- Idempoten: 2a/2b/2c di-guard NOT EXISTS / IS NULL -> re-run = 0 affected.
-- =====================================================================

DELIMITER $$

DROP FUNCTION IF EXISTS `__st_norm_phone`$$
CREATE FUNCTION `__st_norm_phone`(p VARCHAR(255))
RETURNS VARCHAR(64)
DETERMINISTIC
NO SQL
BEGIN
  DECLARE d VARCHAR(64);
  IF p IS NULL THEN RETURN NULL; END IF;
  SET d = REGEXP_REPLACE(p, '[^0-9]', '');
  IF d IS NULL OR d = '' THEN RETURN NULL; END IF;
  IF LEFT(d, 4) = '0062' THEN
    SET d = CONCAT('0', SUBSTRING(d, 5));
  ELSEIF LEFT(d, 2) = '62' THEN
    SET d = CONCAT('0', SUBSTRING(d, 3));
  ELSEIF LEFT(d, 1) <> '0' THEN
    SET d = CONCAT('0', d);
  END IF;
  RETURN d;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 2a. Buat raw_contact untuk tiap nomor ter-normalisasi yang BELUM punya
--     contact apa pun (webhook/cekat/backfill). Nama = customer terlama.
-- ---------------------------------------------------------------------
INSERT INTO raw_contact (id, source_app, name, phone, channel, created_at)
SELECT UUID(), 'backfill', g.nm, g.np, 'backfill', NOW()
FROM (
  SELECT
    __st_norm_phone(c.phone) AS np,
    SUBSTRING_INDEX(
      GROUP_CONCAT(c.name ORDER BY c.created_at ASC SEPARATOR 0x1f),
      0x1f, 1
    ) AS nm
  FROM raw_customer c
  WHERE c.phone IS NOT NULL AND c.phone <> ''
    AND __st_norm_phone(c.phone) IS NOT NULL
  GROUP BY __st_norm_phone(c.phone)
) g
WHERE NOT EXISTS (
  SELECT 1 FROM raw_contact rc
  WHERE __st_norm_phone(rc.phone) = g.np
);

-- ---------------------------------------------------------------------
-- 2b. Link raw_customer.raw_contact_id ke contact ber-nomor sama.
--     Deterministik: contact terlama (created_at, lalu id) per nomor.
--     Hanya baris yang belum ter-link -> idempoten.
-- ---------------------------------------------------------------------
UPDATE raw_customer c
SET c.raw_contact_id = (
  SELECT rc.id FROM raw_contact rc
  WHERE __st_norm_phone(rc.phone) = __st_norm_phone(c.phone)
  ORDER BY rc.created_at ASC, rc.id ASC
  LIMIT 1
)
WHERE c.raw_contact_id IS NULL
  AND c.phone IS NOT NULL AND c.phone <> ''
  AND __st_norm_phone(c.phone) IS NOT NULL
  AND EXISTS (
    SELECT 1 FROM raw_contact rc2
    WHERE __st_norm_phone(rc2.phone) = __st_norm_phone(c.phone)
  );

-- ---------------------------------------------------------------------
-- 2c. Rapikan relasi legacy (kolom NOT NULL DEFAULT sudah meng-handle
--     baris lama; ini hanya jaga-jaga bila ada NULL/'' dari import lama).
-- ---------------------------------------------------------------------
UPDATE raw_customer
SET relasi = 'diri_sendiri'
WHERE relasi IS NULL OR relasi = '';

-- ---------------------------------------------------------------------
-- 2d. LAPORAN KONFLIK (read-only) — nomor dengan >1 pasien BERBEDA
--     (beda = kombinasi name|tanggal_lahir). Untuk ditinjau staf.
--     Tidak menyentuh function -> aman walau function di-drop.
-- ---------------------------------------------------------------------
DROP VIEW IF EXISTS v_contact_patient_conflicts;
CREATE VIEW v_contact_patient_conflicts AS
SELECT
  rc.id                                                AS contact_id,
  rc.phone                                             AS contact_phone,
  COUNT(*)                                             AS total_customer_rows,
  COUNT(DISTINCT CONCAT(COALESCE(c.name,''), '|',
        COALESCE(c.tanggal_lahir,'')))                 AS distinct_patients,
  GROUP_CONCAT(DISTINCT c.name
        ORDER BY c.created_at SEPARATOR ' / ')          AS names,
  GROUP_CONCAT(DISTINCT c.tanggal_lahir SEPARATOR ' / ') AS dobs
FROM raw_customer c
JOIN raw_contact rc ON rc.id = c.raw_contact_id
GROUP BY rc.id, rc.phone
HAVING distinct_patients > 1;

-- Bersihkan helper (view 2d tidak memakainya).
DROP FUNCTION IF EXISTS `__st_norm_phone`;

-- =====================================================================
-- Verifikasi:
--   -- sisa yang tak ter-link (idealnya 0; sisanya = nomor tak valid):
--   SELECT COUNT(*) FROM raw_customer
--     WHERE raw_contact_id IS NULL AND phone IS NOT NULL AND phone <> '';
--   -- daftar konflik utk ditinjau staf:
--   SELECT * FROM v_contact_patient_conflicts ORDER BY distinct_patients DESC;
-- Re-run file ini -> 2a/2b/2c harus 0 baris terpengaruh (idempoten).
-- =====================================================================
