-- ============================================================================
-- LANGKAH 2 (bagian skema) — satu pasien boleh punya BANYAK pid Omnicare
--
-- Rujukan: docs/handoff/data-reservasi-2026-09-11.md §9 "AKAR MASALAH".
--
-- ⭐ TEMUAN YANG MENGUBAH RENCANA.
--    Catatan handoff menduga "skemanya kemungkinan sudah sanggup, yang menahan
--    adalah logika kodenya". TIDAK BENAR — separuhnya ada di skema.
--    `2026_05_21_customer_omnicare_link.sql` memasang DUA kunci unik:
--
--      UNIQUE KEY `uq_col_customer` (`raw_customer_id`)   ← INI yang menahan
--      UNIQUE KEY `uq_col_omnicare` (`omnicare_pid`)      ← INI pagar keselamatan
--
--    Jadi memperbaiki `MatchOmnicarePatients` saja TIDAK CUKUP: baris pid kedua
--    akan ditolak database dengan "Duplicate entry" dan perbaikannya gagal
--    diam-diam (insert chunk 500 baris gagal seluruhnya). Berkas ini harus
--    dijalankan SEBELUM kode barunya naik.
--
-- ⛔ `uq_col_omnicare` SENGAJA DIPERTAHANKAN. Satu pid Omnicare tetap hanya
--    boleh menempel ke SATU raw_customer. Itulah yang mencegah dua orang
--    bertukar rekam medis, dan sekarang ia dijaga oleh DATABASE — bukan oleh
--    kehati-hatian kode. Kriteria terima #3 ("nol pid menempel ke lebih dari
--    satu raw_customer") karena itu tidak bisa dilanggar tanpa error, bukan
--    sekadar "diharapkan tidak terjadi".
--
-- ⚠️ BERKAS LAMA ITU KINI MENYESATKAN. Komentar di kepala
--    `2026_05_21_customer_omnicare_link.sql` masih berbunyi "Link 1:1 per
--    orang. UNIQUE di dua kolom mencegah satu pasien ke-map ganda." Sejak
--    berkas ini dijalankan, kalimat itu TIDAK BERLAKU LAGI untuk sisi pasien.
--    Sengaja tidak diubah di sana — berkas SQL yang sudah dijalankan adalah
--    catatan sejarah, bukan dokumentasi keadaan sekarang.
--
-- Kenapa satu orang punya banyak pid: Omnicare menerbitkan p_id PER CABANG.
--   B- 6.801 · S- 4.545 · C- 2.673 · D- 1.422 · P- 975 (+ pid lama berupa
--   angka). 892 orang punya lebih dari satu pid — 1.797 pid kembar.
--   Contoh nyata: ISMU RAHARJO → B-2007-2758 dan B-2008-2793.
--
-- Idempoten: pola prosedur yang sama dengan 2026_09_11_reservasi_jejak.sql.
--
-- ⚠️ DI LOKAL (XAMPP) berkas ini bisa MEMATIKAN mysqld — pembacaan
--    information_schema sudah terbukti melakukannya di mesin ini
--    (2026-09-11). Di DEV & PROD pola yang sama aman. Kalau butuh
--    perubahannya di LOKAL, jalankan perintah polos ini sekali saja,
--    bukan berkas ini:
--      ALTER TABLE customer_omnicare_link DROP INDEX `uq_col_customer`;
--      ALTER TABLE customer_omnicare_link ADD INDEX `idx_col_customer` (`raw_customer_id`);
--      ALTER TABLE customer_omnicare_link ADD COLUMN `review_flag` VARCHAR(40) DEFAULT NULL;
--      ALTER TABLE customer_omnicare_link ADD INDEX `idx_col_review` (`review_flag`);
--
-- deploy.sh TIDAK menjalankan SQL. Jalankan MANUAL.
-- Urutan aman: backfill DOB → BERKAS INI → baru deploy kode.
-- ============================================================================

-- ────────────────────────────────────────────────────────────────────────────
-- SEBELUM — potret tautan, untuk dibandingkan sesudah rerun penjodohan
-- ────────────────────────────────────────────────────────────────────────────
SELECT 'SEBELUM' AS fase,
       COUNT(*)                            AS tautan,
       COUNT(DISTINCT raw_customer_id)     AS pasien_tertaut,
       COUNT(DISTINCT omnicare_pid)        AS pid_tertaut
FROM customer_omnicare_link;

-- ────────────────────────────────────────────────────────────────────────────
-- 1. Lepas kunci 1:1 di sisi pasien, ganti index biasa (JOIN tetap cepat)
-- ────────────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS mp_drop_idx;
DELIMITER $$
CREATE PROCEDURE mp_drop_idx(IN nama VARCHAR(64))
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'customer_omnicare_link'
               AND INDEX_NAME   = nama) THEN
    SET @s = CONCAT('ALTER TABLE customer_omnicare_link DROP INDEX `', nama, '`');
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS mp_add_idx;
DELIMITER $$
CREATE PROCEDURE mp_add_idx(IN nama VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'customer_omnicare_link'
                   AND INDEX_NAME   = nama) THEN
    SET @s = CONCAT('ALTER TABLE customer_omnicare_link ADD ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS mp_add_col;
DELIMITER $$
CREATE PROCEDURE mp_add_col(IN kolom VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'customer_omnicare_link'
                   AND COLUMN_NAME  = kolom) THEN
    SET @s = CONCAT('ALTER TABLE customer_omnicare_link ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

-- ⚠️ URUTAN DISENGAJA: TAMBAH index biasa DULU, baru LEPAS yang unik.
--    Kalau suatu saat ada FOREIGN KEY di `raw_customer_id` (tidak ada di SQL
--    repo, tapi prod pernah disentuh tangan), MySQL menolak melepas index
--    terakhir yang menopang FK itu: "Cannot drop index needed in a foreign key
--    constraint". Dengan urutan ini, penopangnya sudah ada sebelum yang lama
--    dilepas, jadi aman dalam kedua keadaan.
CALL mp_add_idx('idx_col_customer', 'INDEX `idx_col_customer` (`raw_customer_id`)');
CALL mp_drop_idx('uq_col_customer');

-- ────────────────────────────────────────────────────────────────────────────
-- 2. Penanda tinjauan manusia (Langkah 3b)
--    Diisi oleh `omnicare:tinjau-tautan` untuk tautan lama yang DOB-nya
--    bertentangan dengan Omnicare. ⛔ Tautan TIDAK PERNAH dihapus otomatis —
--    menghapus tautan yang salah lebih berbahaya daripada membiarkannya
--    sampai ada manusia melihat.
-- ────────────────────────────────────────────────────────────────────────────
CALL mp_add_col('review_flag',
  "`review_flag` VARCHAR(40) DEFAULT NULL COMMENT 'dob_bertentangan | nama_lemah — ditandai omnicare:tinjau-tautan, MENUNGGU MANUSIA'");
CALL mp_add_idx('idx_col_review', 'INDEX `idx_col_review` (`review_flag`)');

DROP PROCEDURE IF EXISTS mp_drop_idx;
DROP PROCEDURE IF EXISTS mp_add_idx;
DROP PROCEDURE IF EXISTS mp_add_col;

-- ────────────────────────────────────────────────────────────────────────────
-- 3. ⛔ PAGAR KESELAMATAN — kriteria terima #3
--    Harus mengembalikan NOL BARIS. Kalau tidak, BERHENTI dan laporkan:
--    ada pid yang menempel ke lebih dari satu pasien, dan dua orang
--    berisiko bertukar rekam medis.
-- ────────────────────────────────────────────────────────────────────────────
SELECT omnicare_pid,
       COUNT(DISTINCT raw_customer_id) AS jumlah_pasien,
       GROUP_CONCAT(DISTINCT raw_customer_id SEPARATOR ' | ') AS pasien
FROM customer_omnicare_link
GROUP BY omnicare_pid
HAVING COUNT(DISTINCT raw_customer_id) > 1;

-- Bukti kunci unik pid masih terpasang (harus muncul 1 baris: uq_col_omnicare).
SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'customer_omnicare_link'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;
