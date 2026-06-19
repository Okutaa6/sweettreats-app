-- =====================================================================
-- Add attachment_path column to petty_cash_transactions &
-- transport_cost_transactions.
--
-- Untuk upload bukti pengeluaran (bon, struk, bukti transfer) di form
-- Pengeluaran modal sumber dana. Nullable — transaksi lama tidak punya
-- attachment.      
--
-- Path disimpan relatif dari public/, mis:
--   uploads/expense-receipts/2026/06/abc123-uuid.jpg
-- Sehingga bisa diakses publik via /uploads/expense-receipts/...
-- =====================================================================

ALTER TABLE `petty_cash_transactions`
    ADD COLUMN `attachment_path` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Path relatif dari public/ untuk bukti pengeluaran (bon/struk/transfer). Nullable.'
        AFTER `ref_no`;

ALTER TABLE `transport_cost_transactions`
    ADD COLUMN `attachment_path` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Path relatif dari public/ untuk bukti pengeluaran (bon/struk/transfer). Nullable.'
        AFTER `ref_no`;
