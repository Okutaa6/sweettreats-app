-- =====================================================================
-- Fund Transfer flag — 2026-05-30
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- Tujuan:
--   Menandai transaksi yang merupakan bagian dari transfer antar sumber
--   dana (mis. BSI → BCA). Transfer = 1 baris CASH OUT dari source asal
--   + 1 baris CASH IN ke source tujuan, terkait via kolom `ref_no` yang
--   sama (format: TRF-{period_id}-{timestamp}-{rand}).
--
-- Catatan:
--   - Tidak idempotent. Kalau dijalankan dua kali akan error
--     "Duplicate column name 'is_transfer'" — abaikan, kolom sudah ada.
-- =====================================================================

ALTER TABLE `petty_cash_transactions`
    ADD COLUMN `is_transfer` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_topup`;

ALTER TABLE `transport_cost_transactions`
    ADD COLUMN `is_transfer` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_topup`;
