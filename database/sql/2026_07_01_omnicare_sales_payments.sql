-- ════════════════════════════════════════════════════════════════════════════
-- Omnicare Sales Payments — record pembayaran dari /get/sales_payments
-- ════════════════════════════════════════════════════════════════════════════
-- Tujuan: mirror record pembayaran (uang masuk per-transaksi) yang sebelumnya
--         tidak ter-sync. Melengkapi omnicare_sales_detail (per-item) dengan
--         METODE BAYAR (Debit/Tunai/QRIS/dll), jumlah aktual diterima, tipe
--         pembayaran (Bayar/refund), dan status faktur (AKTIF/cancel).
--
-- Sumber: POST /get/sales_payments — READ, live (bukan preview). Cap 31 hari
--         per range, paginated (per_page). Endpoint commission & unpaid_invoices
--         masih preview → belum di-sync.
--
-- Idempotent: CREATE IF NOT EXISTS — aman dijalankan berulang tanpa lose data.
--             Setelah eksekusi, populate via `php artisan omnicare:sync --endpoint=payments`
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `omnicare_sales_payments` (
  `payment_id`        varchar(40)  NOT NULL COMMENT 'Unique payment identifier (timestamp + suffix)',
  `pay_date`          datetime     DEFAULT NULL COMMENT 'Tanggal bayar (API format dd-mm-yyyy H:i:s)',
  `pay_timestamp`     bigint(20)   DEFAULT NULL COMMENT 'Unix ms',
  `invoice_date`      date         DEFAULT NULL,
  `invoice_num`       varchar(50)  DEFAULT NULL,
  `service_type`      varchar(10)  DEFAULT NULL COMMENT 'RJ | RI | OTC | MCU',

  -- Nominal
  `pay_amount`        decimal(15,2) NOT NULL DEFAULT 0 COMMENT 'Nominal pembayaran (string di API)',
  `extra_amount`      decimal(15,2) NOT NULL DEFAULT 0,
  `actual_amount`     decimal(15,2) NOT NULL DEFAULT 0 COMMENT 'Jumlah aktual diterima',
  `payment_note`      text         DEFAULT NULL,
  `payment_method`    varchar(100) DEFAULT NULL COMMENT 'Debit BSI | Tunai | QRIS | Transfer | dll',
  `payment_type`      varchar(50)  DEFAULT NULL COMMENT 'Bayar | Refund | dll',
  `voucher`           varchar(100) DEFAULT NULL,

  -- Patient (snapshot)
  `p_id`              varchar(50)  DEFAULT NULL COMMENT 'Omnicare patient ID',
  `p_name`            varchar(255) DEFAULT NULL,
  `p_ins_id`          varchar(50)  DEFAULT NULL COMMENT '"0" = cash, else insurance plan ID',
  `p_insurance`       varchar(255) DEFAULT NULL,

  -- Proses & status
  `process_date`      datetime     DEFAULT NULL COMMENT 'Kapan pembayaran diproses/diinput',
  `process_timestamp` bigint(20)   DEFAULT NULL COMMENT 'Unix ms',
  `invoice_status`    varchar(20)  DEFAULT NULL COMMENT 'AKTIF | BATAL',
  `cancel_reason`     text         DEFAULT NULL,

  -- Branch & meta
  `place_id`          varchar(20)  NOT NULL,
  `branch_name`       varchar(255) DEFAULT NULL,
  `username`          varchar(100) DEFAULT NULL COMMENT 'Staf/kasir yang memproses',

  `last_synced_at`    timestamp    NULL DEFAULT NULL,
  `raw_payload`       longtext     DEFAULT NULL,
  `created_at`        timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`        timestamp    NULL DEFAULT NULL ON UPDATE current_timestamp(),

  PRIMARY KEY (`payment_id`),
  KEY `idx_omnicare_pay_place_date` (`place_id`, `invoice_date`),
  KEY `idx_omnicare_pay_invoice` (`invoice_num`),
  KEY `idx_omnicare_pay_patient` (`p_id`),
  KEY `idx_omnicare_pay_method` (`payment_method`),
  KEY `idx_omnicare_pay_status` (`invoice_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
