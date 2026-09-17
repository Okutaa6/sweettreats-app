-- =====================================================================
-- Petty Cash — Pensiunkan kategori "Pinjaman" — 2026-09-03
-- IDEMPOTENT (UPDATE by `key`, aman dijalankan berulang).
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Konteks:
--   Fitur pinjaman petty cash (kategori 'pinjaman', pencatatan peminjam,
--   modal "Catat Pengembalian", kartu piutang per wallet) SUDAH DIHAPUS dari
--   aplikasi karena tidak dipakai lagi.
--
-- Yang dilakukan skrip ini:
--   1. is_active = 0 → kategori 'pinjaman' hilang dari dropdown transaksi baru.
--   2. is_system = 0 → kunci "kategori sistem" dilepas (dulu dikunci karena
--      dipakai alur returnLoan yang kini sudah tidak ada), supaya Super Admin
--      bisa mengelolanya seperti kategori biasa dari halaman Kelola Wallet.
--
-- Yang SENGAJA TIDAK dilakukan (jangan ditambahkan tanpa keputusan baru):
--   - Baris kategori TIDAK di-DELETE. Label "Pinjaman" masih dibutuhkan agar
--     transaksi lama tetap tampil berlabel (categoryMap() membaca kategori
--     aktif + nonaktif).
--   - Kolom `borrower_name` & `settles_transaction_id` di petty_cash_transactions
--     TIDAK di-DROP, dan transaksi pinjaman lama TIDAK dihapus. Riwayat prod
--     tetap utuh; kolomnya sekadar berhenti diisi oleh aplikasi.
-- =====================================================================

UPDATE `petty_cash_categories`
   SET `is_active`  = 0,
       `is_system`  = 0,
       `updated_at` = NOW()
 WHERE `key` = 'pinjaman';

-- Verifikasi (harus 1 baris: is_active=0, is_system=0):
-- SELECT `key`, `label`, `is_system`, `is_active` FROM `petty_cash_categories` WHERE `key` = 'pinjaman';
