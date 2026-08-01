-- =====================================================================
-- Accurate Online Open API — tabel integrasi finance — 2026-06-12
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   Integrasi finance Accurate via metode API Token. FASE TESTING = PULL-ONLY
--   (read-only): tarik glaccount / sales-invoice / customer / item dari Accurate
--   untuk dibandingkan dengan data Omnicare, sebelum nanti push.
--
--   3 tabel:
--     1. accurate_credentials  — 1 baris per Data Usaha (Holding + 5 cabang).
--        Token & host TIDAK di-seed di file ini (rahasia + host dinamis).
--        Token diisi via file lokal gitignored; host diisi otomatis saat
--        resolve via /api/api-token.do (di-cache, refresh tiap 30 hari / HTTP 308).
--     2. accurate_sync_log     — audit tiap run pull (mirror omnicare_sync_log).
--     3. accurate_staging_glaccount — Chart of Accounts mentah per Data Usaha
--        (fondasi mapping akun nanti).
--
-- Idempoten: CREATE TABLE IF NOT EXISTS + INSERT ... ON DUPLICATE KEY UPDATE.
-- NON-DESTRUKTIF: tidak menyentuh tabel omnicare_* / raw_*.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. accurate_credentials — kredensial per Data Usaha
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `accurate_credentials` (
  `id`              varchar(36)  NOT NULL,
  `slug`            varchar(40)  NOT NULL COMMENT 'holding|sunter|cinere|kalideres|bintaro|pamulang',
  `company_name`    varchar(150) NOT NULL COMMENT 'Nama Data Usaha di Accurate',
  `db_uuid`         varchar(64)  DEFAULT NULL COMMENT 'Database ID (UUID) dari Informasi Database',
  `db_numeric_id`   bigint(20)   DEFAULT NULL COMMENT 'id numerik Data Usaha (dari response api-token.do)',
  `billing_code`    varchar(30)  DEFAULT NULL COMMENT 'Kode Billing Accurate',
  `branch_place_id` varchar(20)  DEFAULT NULL COMMENT 'omnicare place_id untuk rekonsiliasi (Holding=NULL)',
  `api_token`       text         DEFAULT NULL COMMENT 'API Token (Bearer). RAHASIA — testing; rotate sebelum prod',
  `host`            varchar(120) DEFAULT NULL COMMENT 'host data usaha (cache), mis. https://zeus.accurate.id',
  `host_checked_at` timestamp    NULL DEFAULT NULL COMMENT 'kapan host terakhir di-resolve',
  `is_active`       tinyint(1)   NOT NULL DEFAULT 1,
  `notes`           text         DEFAULT NULL,
  `created_at`      timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`      timestamp    NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_acc_slug` (`slug`),
  UNIQUE KEY `uq_acc_db_uuid` (`db_uuid`),
  KEY `idx_acc_branch` (`branch_place_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed metadata 6 Data Usaha dalam scope (TANPA token). branch_place_id
-- dipetakan ke omnicare place_id (lihat config/omnicare.php active_branches).
-- RB Las Jaya (db e8a941ed.., billing 6NBB28PP7) SENGAJA tidak di-seed (ditunda).
INSERT INTO `accurate_credentials`
  (`id`, `slug`, `company_name`, `db_uuid`, `billing_code`, `branch_place_id`) VALUES
  ('89d24b02-6023-4d00-8432-e57f423683e3', 'holding',   'PT Anugerah Berkah Group (Holding)', '89d24b02-6023-4d00-8432-e57f423683e3', '61DDKYXND', NULL),
  ('49bc405b-dc61-4db7-81d2-3f76b4ad13e8', 'sunter',    'PT Cahaya Fitrah Sejati (Sunter)',   '49bc405b-dc61-4db7-81d2-3f76b4ad13e8', '3NBBNEGXX', '29958'),
  ('eafe9dfd-54af-492d-9c3b-1ccaa9f6da9b', 'cinere',    'PT Insan Karsa Amanah (Cinere)',     'eafe9dfd-54af-492d-9c3b-1ccaa9f6da9b', '38YYK5GPE', '30439'),
  ('cc601e95-568e-44f8-85d3-fb74b12db421', 'kalideres', 'PT Inti Medika Karya (Kalideres)',   'cc601e95-568e-44f8-85d3-fb74b12db421', '6GRRNLXEG', '29959'),
  ('4c4c1e66-02ac-4263-9fb2-db063e87bfb0', 'bintaro',   'PT Sakinah Berdaya Abadi (Bintaro)', '4c4c1e66-02ac-4263-9fb2-db063e87bfb0', '3DEE7ML5K', '29957'),
  ('9768877b-e708-4dbf-a4e1-4da0bff6c37a', 'pamulang',  'PT Tunas Rahmat Mulia (Pamulang)',   '9768877b-e708-4dbf-a4e1-4da0bff6c37a', '3PYY8L75N', '30941')
ON DUPLICATE KEY UPDATE
  `company_name`   = VALUES(`company_name`),
  `billing_code`   = VALUES(`billing_code`),
  `branch_place_id`= VALUES(`branch_place_id`);

-- ---------------------------------------------------------------------
-- 2. accurate_sync_log — audit run pull (mirror omnicare_sync_log)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `accurate_sync_log` (
  `id`            bigint(20)   NOT NULL AUTO_INCREMENT,
  `endpoint`      varchar(120) NOT NULL COMMENT 'mis. /api/glaccount/list.do',
  `slug`          varchar(40)  DEFAULT NULL COMMENT 'Data Usaha target (NULL = semua)',
  `status`        enum('running','success','error','partial') NOT NULL DEFAULT 'running',
  `records_count` int(11)      NOT NULL DEFAULT 0,
  `pages_fetched` int(11)      NOT NULL DEFAULT 0,
  `http_status`   int(11)      DEFAULT NULL,
  `error_message` text         DEFAULT NULL,
  `triggered_by`  varchar(40)  NOT NULL DEFAULT 'manual' COMMENT 'cron|manual',
  `started_at`    timestamp    NOT NULL DEFAULT current_timestamp(),
  `finished_at`   timestamp    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_acclog_slug` (`slug`),
  KEY `idx_acclog_status` (`status`),
  KEY `idx_acclog_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. accurate_staging_glaccount — Chart of Accounts mentah per Data Usaha
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `accurate_staging_glaccount` (
  `slug`           varchar(40)  NOT NULL COMMENT 'Data Usaha sumber',
  `accurate_id`    bigint(20)   NOT NULL COMMENT 'glaccount.id di Accurate',
  `no`             varchar(40)  DEFAULT NULL COMMENT 'No Akun (mis. 400201)',
  `name`           varchar(240) DEFAULT NULL COMMENT 'Nama Akun (mis. Pendapatan Bleaching)',
  `account_type`   varchar(60)  DEFAULT NULL,
  `currency`       varchar(10)  DEFAULT NULL,
  `balance`        decimal(20,2) DEFAULT NULL,
  `suspended`      tinyint(1)   DEFAULT NULL,
  `parent_id`      bigint(20)   DEFAULT NULL,
  `raw_payload`    longtext     DEFAULT NULL COMMENT 'JSON mentah dari API',
  `last_synced_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`slug`, `accurate_id`),
  KEY `idx_accgl_no` (`no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. accurate_staging_sales_invoice — faktur penjualan mentah per Data Usaha
-- ---------------------------------------------------------------------
-- CATATAN PENTING: untuk pembanding revenue vs Omnicare gunakan `sub_total`
-- (nilai KOTOR penjualan), BUKAN `total_amount`. Faktur harian agregat (pola
-- "PLJ{yymmdd}") punya total_amount = 0 karena revenue dialokasikan habis ke
-- detailExpense (fee dokter, voucher, piutang non-cash). sub_total = nilai jual.
CREATE TABLE IF NOT EXISTS `accurate_staging_sales_invoice` (
  `slug`           varchar(40)   NOT NULL COMMENT 'Data Usaha sumber',
  `accurate_id`    bigint(20)    NOT NULL COMMENT 'sales-invoice.id di Accurate',
  `number`         varchar(60)   DEFAULT NULL COMMENT 'No Faktur (mis. PLJ260430)',
  `trans_date`     date          DEFAULT NULL,
  `description`    varchar(255)  DEFAULT NULL,
  `customer_no`    varchar(40)   DEFAULT NULL,
  `customer_name`  varchar(200)  DEFAULT NULL,
  `sub_total`      decimal(20,2) DEFAULT NULL COMMENT 'KOTOR — metrik pembanding revenue',
  `total_amount`   decimal(20,2) DEFAULT NULL COMMENT 'net AR (sering 0 utk faktur harian)',
  `tax_amount`     decimal(20,2) DEFAULT NULL,
  `status_name`    varchar(40)   DEFAULT NULL COMMENT 'Lunas | Belum Lunas',
  `raw_payload`    longtext      DEFAULT NULL COMMENT 'JSON mentah (header list)',
  `last_synced_at` timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`slug`, `accurate_id`),
  KEY `idx_accsi_date` (`slug`, `trans_date`),
  KEY `idx_accsi_number` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. accurate_staging_purchase_invoice — faktur pembelian (stok/operasional)
-- ---------------------------------------------------------------------
-- Data yang TIDAK ada di Omnicare. Sisi biaya/pembelian per Data Usaha
-- (dental lab, supplier alat/bahan, dll). Pembanding sisi cost.
CREATE TABLE IF NOT EXISTS `accurate_staging_purchase_invoice` (
  `slug`           varchar(40)   NOT NULL,
  `accurate_id`    bigint(20)    NOT NULL COMMENT 'purchase-invoice.id di Accurate',
  `number`         varchar(60)   DEFAULT NULL,
  `trans_date`     date          DEFAULT NULL,
  `description`    varchar(255)  DEFAULT NULL,
  `vendor_no`      varchar(40)   DEFAULT NULL,
  `vendor_name`    varchar(200)  DEFAULT NULL,
  `sub_total`      decimal(20,2) DEFAULT NULL,
  `total_amount`   decimal(20,2) DEFAULT NULL,
  `tax_amount`     decimal(20,2) DEFAULT NULL,
  `status_name`    varchar(40)   DEFAULT NULL,
  `raw_payload`    longtext      DEFAULT NULL,
  `last_synced_at` timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`slug`, `accurate_id`),
  KEY `idx_accpi_date` (`slug`, `trans_date`),
  KEY `idx_accpi_number` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. accurate_staging_sales_item — baris item faktur penjualan (level detail)
-- ---------------------------------------------------------------------
-- Dipakai untuk: (a) breakdown revenue per akun pendapatan (400xxx);
-- (b) pisahkan obat/HPP dari total penjualan jasa (per permintaan Finance:
-- obat/HPP TIDAK dihitung sebagai penjualan). account_no = item.no di Accurate
-- (item klinik dipetakan ke kode akun pendapatan). item_type SERVICE vs INVENTORY
-- membantu identifikasi obat/barang.
CREATE TABLE IF NOT EXISTS `accurate_staging_sales_item` (
  `slug`           varchar(40)   NOT NULL,
  `invoice_id`     bigint(20)    NOT NULL COMMENT 'accurate_staging_sales_invoice.accurate_id',
  `seq`            int(11)       NOT NULL COMMENT 'urutan baris dalam faktur',
  `trans_date`     date          DEFAULT NULL COMMENT 'denormalisasi dari header (untuk agregasi cepat)',
  `account_no`     varchar(40)   DEFAULT NULL COMMENT 'item.no = kode akun pendapatan (mis. 400202)',
  `item_name`      varchar(240)  DEFAULT NULL,
  `service_name`   varchar(240)  DEFAULT NULL COMMENT 'detailName = nama tindakan',
  `item_type`      varchar(30)   DEFAULT NULL COMMENT 'SERVICE | INVENTORY | NON_INVENTORY',
  `quantity`       decimal(18,4) DEFAULT NULL,
  `unit_price`     decimal(20,2) DEFAULT NULL,
  `line_total`     decimal(20,2) DEFAULT NULL COMMENT 'totalPrice baris (gross)',
  `last_synced_at` timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`slug`, `invoice_id`, `seq`),
  KEY `idx_accsit_acc` (`slug`, `account_no`),
  KEY `idx_accsit_date` (`slug`, `trans_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. accurate_staging_cashbank — transaksi kas/bank (uang masuk & keluar)
-- ---------------------------------------------------------------------
-- Gabungan 3 jenis transaksi yang TIDAK ada di Omnicare:
--   other_payment  = beban operasional dibayar (admin, payroll, pajak, dll)
--   other_deposit  = uang masuk lain (AR per bank, bagi hasil, transfer modal)
--   bank_transfer  = pindah dana antar bank (top-up kasir, pinbuk)
-- Dipakai untuk laporan Beban Operasional & Arus Kas sederhana.
CREATE TABLE IF NOT EXISTS `accurate_staging_cashbank` (
  `slug`           varchar(40)   NOT NULL,
  `type`           varchar(20)   NOT NULL COMMENT 'other_payment | other_deposit | bank_transfer',
  `accurate_id`    bigint(20)    NOT NULL,
  `number`         varchar(80)   DEFAULT NULL,
  `trans_date`     date          DEFAULT NULL,
  `bank_name`      varchar(120)  DEFAULT NULL,
  `amount`         decimal(20,2) DEFAULT NULL,
  `description`    varchar(255)  DEFAULT NULL,
  `raw_payload`    longtext      DEFAULT NULL,
  `last_synced_at` timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`slug`, `type`, `accurate_id`),
  KEY `idx_acccb_date` (`slug`, `type`, `trans_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Verifikasi:
--   SELECT slug, company_name, branch_place_id,
--          (api_token IS NOT NULL) AS has_token, host
--   FROM accurate_credentials ORDER BY slug;
--   SELECT slug, COUNT(*), SUM(sub_total) FROM accurate_staging_sales_invoice GROUP BY slug;
--   SELECT slug, COUNT(*), SUM(total_amount) FROM accurate_staging_purchase_invoice GROUP BY slug;
--   SELECT type, COUNT(*), SUM(amount) FROM accurate_staging_cashbank GROUP BY type;
-- =====================================================================
