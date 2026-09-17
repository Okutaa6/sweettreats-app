-- =====================================================================
-- Petty Cash — pensiunkan wallet PER CABANG (bukan serentak se-klinik)
-- 2026-09-09. IDEMPOTENT via INFORMATION_SCHEMA check.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Konteks:
--   Prod hari ini tiap cabang memakai banyak wallet (Cash, BCA, BSI, GoPay).
--   Model barunya cukup 2 (Cash + satu bank), tapi peralihannya TIDAK boleh
--   serentak: cabang yang belum dimigrasi harus tetap bisa mencatat
--   pengeluaran dari wallet lamanya sampai gilirannya tiba.
--
--   Selama ini satu-satunya saklar adalah `fund_sources.is_active` yang
--   berlaku GLOBAL — mematikan GoPay untuk Bintaro otomatis mematikannya di
--   Sunter juga. Kolom di bawah memindahkan saklar itu ke level pasangan
--   (cabang × wallet), sehingga migrasi bisa cabang per cabang.
--
--   Diisi otomatis oleh tombol "Migrasi Saldo" di halaman Petty Cash
--   (PettyCashController::resetOpening) — wallet yang TIDAK dicentang untuk
--   dipertahankan akan ditandai pensiun untuk cabang itu saja.
--
-- Yang dilakukan:
--   1. `account_number` dibuat NULLABLE. Baris pasangan (cabang × wallet)
--      sekarang juga dipakai untuk menyimpan penanda pensiun, dan Cash tidak
--      punya nomor rekening — tanpa ini, Cash mustahil dipensiunkan.
--   2. Tambah kolom `retired_at` — NULL = wallet masih dipakai cabang itu,
--      terisi = sudah dipensiunkan sejak tanggal tsb.
--   3. Tambah index (raw_branch_id, retired_at) — dibaca tiap render halaman
--      petty cash untuk menyaring daftar wallet.
--
-- Yang SENGAJA TIDAK dilakukan:
--   - TIDAK menyentuh `fund_sources.is_active`. Saklar global tetap ada dan
--     tetap menang; kolom ini hanya mempersempit per cabang.
--   - TIDAK menghapus baris wallet, transaksi, maupun nomor rekening.
--     Transaksi lama yang memakai BSI/GoPay harus tetap tampil berlabel
--     benar — polanya sama dengan cara kategori "Pinjaman" dipensiunkan
--     (2026_09_03_pettycash_retire_kategori_pinjaman.sql).
--   - TIDAK mengisi `retired_at` untuk cabang mana pun. Skrip ini cuma
--     menyiapkan kolomnya; keputusan cabang mana yang dimigrasi ada di
--     tangan Super Admin lewat UI, supaya ada jejak siapa & kapan.
-- =====================================================================

-- 1) account_number → NULLABLE -----------------------------------------
SET @is_nullable := (
    SELECT IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'fund_source_branch_accounts'
      AND COLUMN_NAME  = 'account_number'
);
SET @sql := IF(@is_nullable = 'NO',
    'ALTER TABLE `fund_source_branch_accounts`
        MODIFY COLUMN `account_number` VARCHAR(50) NULL DEFAULT NULL
        COMMENT "No rek bank / no HP e-wallet. NULL utk Cash atau baris yang hanya menandai pensiun."',
    'SELECT "fund_source_branch_accounts.account_number already nullable" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) retired_at ---------------------------------------------------------
SET @has_col := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'fund_source_branch_accounts'
      AND COLUMN_NAME  = 'retired_at'
);
SET @sql := IF(@has_col = 0,
    'ALTER TABLE `fund_source_branch_accounts`
        ADD COLUMN `retired_at` DATETIME NULL DEFAULT NULL
        COMMENT "Terisi = wallet ini sudah dipensiunkan untuk cabang ini (lewat Migrasi Saldo). NULL = masih dipakai."
        AFTER `account_number`',
    'SELECT "fund_source_branch_accounts.retired_at already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) index penyaring ----------------------------------------------------
SET @has_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'fund_source_branch_accounts'
      AND INDEX_NAME   = 'idx_fsba_branch_retired'
);
SET @sql := IF(@has_idx = 0,
    'ALTER TABLE `fund_source_branch_accounts`
        ADD KEY `idx_fsba_branch_retired` (`raw_branch_id`, `retired_at`)',
    'SELECT "idx_fsba_branch_retired already exists" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verifikasi (aman dijalankan kapan saja):
-- SELECT raw_branch_id, fund_source_id, account_number, retired_at
-- FROM fund_source_branch_accounts ORDER BY raw_branch_id, fund_source_id;
