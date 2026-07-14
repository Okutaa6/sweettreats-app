-- ════════════════════════════════════════════════════════════════════════════
-- Omnicare Commission — detail komisi dokter dari /get/commission
-- ════════════════════════════════════════════════════════════════════════════
-- Tujuan: mirror komisi dokter per-baris (fee dokter) yang sebelumnya cuma bisa
--         diambil manual (print PDF Rincian Komisi). Melengkapi sales_detail
--         (revenue) & sales_payments (metode bayar) → jadi bahan lengkap untuk
--         push faktur ke Accurate (seksi EXPENSE "Hutang Fee Dokter" 210205).
--
-- Sumber: POST /get/commission — READ, live (per 2026-07-04, sebelumnya preview).
--         1 baris per tenaga medis per sale line. Cap 31 hari + paginated.
--         commission_role = doctor | assistant (komisi rujukan TIDAK termasuk).
--         commission_type "" = flat (rate = nominal), "%" = persen; commission_amount
--         = nilai FINAL. Basis "after payment": komisi ikut pembayaran yang sudah
--         masuk (bulan lunas stabil, bulan berjalan ikut cicilan).
--
-- Validasi: total commission_amount Bintaro (29957) 1-30 Apr 2026 = Rp 133.823.989
--           (cocok PERSIS laporan Rincian Komisi internal).
--
-- Idempotent: CREATE IF NOT EXISTS. Populate via
--             `php artisan omnicare:sync --endpoint=commission`
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `omnicare_commission` (
  `id`                bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `trx_id`            varchar(40)  NOT NULL COMMENT 'Transaction/sale-line id',
  `trx_date`          date         NOT NULL,
  `invoice_num`       varchar(50)  DEFAULT NULL,
  `service_type`      varchar(10)  DEFAULT NULL COMMENT 'RJ | RI | OTC | MCU',
  `item_code`         varchar(50)  DEFAULT NULL COMMENT 'Kode prosedur, mis. 26CB068',
  `item_type`         char(1)      DEFAULT NULL COMMENT 'P=procedure, D=drug, S=supply',
  `item_name`         varchar(500) DEFAULT NULL,
  `item_extra_name`   varchar(500) DEFAULT NULL,
  `qty_sold`          decimal(10,2) NOT NULL DEFAULT 0,
  `net_sell_price`    decimal(15,2) NOT NULL DEFAULT 0,
  `gross_price`       decimal(15,2) NOT NULL DEFAULT 0,
  `polyclinic`        varchar(255) DEFAULT NULL,

  -- Tenaga medis & komisi
  `doctor`            varchar(255) DEFAULT NULL,
  `doc_id`            varchar(20)  DEFAULT NULL COMMENT 'Omnicare doctor id (join omnicare_doctor)',
  `commission_role`   varchar(20)  DEFAULT NULL COMMENT 'doctor | assistant',
  `commission_type`   varchar(5)   DEFAULT NULL COMMENT '"" = flat (rate=nominal), "%" = persen',
  `commission_rate`   decimal(15,2) NOT NULL DEFAULT 0 COMMENT 'Nominal (flat) atau persen (%)',
  `commission_amount` decimal(15,2) NOT NULL DEFAULT 0 COMMENT 'Nilai komisi FINAL per baris',

  -- Pasien & link
  `p_id`              varchar(50)  DEFAULT NULL,
  `p_name`            varchar(255) DEFAULT NULL,
  `encounter_id`      varchar(40)  DEFAULT NULL COMMENT 'Link ke medical_record.record_id. "0" = NULL.',

  -- Branch & meta
  `place_id`          varchar(20)  NOT NULL,
  `branch_name`       varchar(255) DEFAULT NULL,
  `username`          varchar(100) DEFAULT NULL,

  `last_synced_at`    timestamp    NULL DEFAULT NULL,
  `raw_payload`       longtext     DEFAULT NULL,
  `created_at`        timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`        timestamp    NULL DEFAULT NULL ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_omnicare_commission_line` (`trx_id`, `doc_id`, `commission_role`),
  KEY `idx_omnicare_comm_place_date` (`place_id`, `trx_date`),
  KEY `idx_omnicare_comm_doc` (`doc_id`),
  KEY `idx_omnicare_comm_invoice` (`invoice_num`),
  KEY `idx_omnicare_comm_patient` (`p_id`),
  KEY `idx_omnicare_comm_encounter` (`encounter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
