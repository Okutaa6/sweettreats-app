-- =====================================================================
-- Jejak login KOC — kolom `raw_affiliate`.`last_login` — 2026-09-23
-- IDEMPOTENT (cek INFORMATION_SCHEMA dulu; MySQL 5.7 tidak punya
-- ADD COLUMN IF NOT EXISTS). Aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Konteks (tiket IT-2026-045):
--   Marketing tidak bisa melihat berapa KOC yang sudah benar-benar masuk ke
--   portal affiliate. Sampai hari ini aplikasi TIDAK mencatat login affiliate
--   sama sekali: AffiliatePortalController::loginProcess() hanya mengisi
--   session lalu redirect — tanpa audit log, tanpa update kolom. Model
--   RawAffiliate juga $timestamps = false, jadi `updated_at` pun tak bergerak.
--   Jadi pertanyaan "KOC ini sudah pernah login atau belum?" memang belum bisa
--   dijawab dari data mana pun; harus direkam lebih dulu.
--
-- Yang dilakukan skrip ini:
--   1. Menambah kolom `raw_affiliate`.`last_login` (timestamp NULL).
--      Di-stamp oleh loginProcess() tepat setelah session affiliate di-set,
--      yaitu SETELAH semua gerbang lolos (PIN benar, registration_status
--      approved, is_active) — jadi isinya memang "berhasil login", bukan
--      percobaan login. NULL = belum pernah login SEJAK pencatatan aktif.
--
-- Catatan tipe — `NULL DEFAULT NULL` ditulis eksplisit dan itu WAJIB:
--   Kalau MySQL sampai menempelkan ON UPDATE CURRENT_TIMESTAMP ke kolom ini,
--   maka setiap UPDATE baris KOC dari halaman admin (approve pendaftaran,
--   reset PIN, toggle aktif) akan MEMALSUKAN jejak login jadi "baru saja
--   login" — dan tidak ada yang menjaganya karena model ini $timestamps=false.
--   Blok verifikasi di kaki file memeriksa kolom EXTRA harus kosong.
--   Tipe TIMESTAMP dipilih agar seragam dengan tetangganya di tabel yang sama
--   (`created_at`, `reward_program_started_at`, `koc_sosmed_confirmed_at`);
--   nama `last_login` mengikuti kolom sejenis di tabel staf `users`.
--
-- Yang SENGAJA TIDAK dilakukan (jangan ditambahkan tanpa keputusan baru):
--   - TIDAK membuat tabel riwayat login. Kebutuhannya cuma "sudah/belum +
--     kapan terakhir", bukan audit trail per sesi.
--   - TIDAK menambah kolom `login_count`. Tidak diminta, dan tiap login jadi
--     tulisan tambahan tanpa pembaca.
--   - TIDAK memasang index pada kolom ini. `raw_affiliate` hanya puluhan baris
--     (92 total / 27 KOC) dan filter query-nya `type`, yang sudah tercover
--     idx_raw_affiliate_type_branch. Index cuma menambah ongkos tulis saat login.
--   - TIDAK ada BACKFILL. Login yang terjadi SEBELUM file ini dijalankan tidak
--     meninggalkan jejak apa pun, jadi kolomnya tetap NULL — dan itu memang
--     jujur. Mengisinya dari `created_at` sama dengan melaporkan login yang
--     tidak pernah terjadi. Konsekuensinya: di minggu-minggu pertama angka
--     "sudah login" akan terlihat sangat rendah. UI WAJIB memberi keterangan
--     "dicatat sejak 23 Sep 2026" dan menulis "Belum tercatat login" —
--     bukan "belum pernah login".
--   - TIDAK menyentuh raw_cus_reservation.raw_promo_id (kolom itu belum
--     ber-index). Query dashboard digiring lewat index `date` + `raw_branch_id`.
--     Kalau EXPLAIN di prod menunjukkan full scan, buat file SQL TERPISAH untuk
--     idx_resv_promo(raw_promo_id, date) — satu file = satu perubahan.
-- =====================================================================

SET @exist := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'raw_affiliate'
       AND COLUMN_NAME  = 'last_login'
);
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate`
        ADD COLUMN `last_login` TIMESTAMP NULL DEFAULT NULL
            COMMENT 'Waktu terakhir affiliate BERHASIL login portal. NULL = belum pernah login sejak pencatatan aktif (2026-09-23).'
            AFTER `koc_sosmed_confirmed_by`",
    "SELECT 'raw_affiliate.last_login already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- VERIFIKASI (jangan dilewati)
--   Harus 1 baris: COLUMN_TYPE 'timestamp', IS_NULLABLE 'YES',
--   COLUMN_DEFAULT NULL, dan EXTRA **KOSONG**.
--   Kalau EXTRA berisi 'on update CURRENT_TIMESTAMP', server memaksa
--   auto-update → jejak login akan dipalsukan tiap edit KOC. Perbaiki dengan:
--     ALTER TABLE `raw_affiliate`
--       MODIFY `last_login` TIMESTAMP NULL DEFAULT NULL;
--   sebelum kode dideploy.
-- =====================================================================
-- SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
--   FROM INFORMATION_SCHEMA.COLUMNS
--  WHERE TABLE_SCHEMA = DATABASE()
--    AND TABLE_NAME = 'raw_affiliate' AND COLUMN_NAME = 'last_login';
