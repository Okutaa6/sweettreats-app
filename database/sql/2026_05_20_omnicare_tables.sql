-- =====================================================================
-- Omnicare (Lokadok) Open API integration — 2026-05-20
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   Pull data Omnicare (sales, patient, doctor, location) via API GET nightly,
--   mirror ke tabel `omnicare_*` di DB SweetTreats, lalu reconcile
--   reservation SweetTreats ↔ encounter Omnicare.
--
-- Strategi mapping:
--   - raw_branch  + kolom `omnicare_place_id` (UUID SweetTreats ↔ numeric Omnicare)
--   - raw_doctor  + kolom `omnicare_doc_id`   (UUID ↔ numeric)
--   - Service:    prefix item_code → SVC parent (no mapping table needed)
--
-- Idempoten: aman dijalankan ulang (guard via information_schema).
-- =====================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `__st_add_col`$$
CREATE PROCEDURE `__st_add_col`(
  IN p_tbl VARCHAR(64), IN p_col VARCHAR(64), IN p_ddl TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_tbl
      AND COLUMN_NAME = p_col
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_tbl, '` ADD COLUMN ', p_ddl);
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$

DROP PROCEDURE IF EXISTS `__st_add_idx`$$
CREATE PROCEDURE `__st_add_idx`(
  IN p_tbl VARCHAR(64), IN p_idx VARCHAR(64), IN p_cols VARCHAR(255)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_tbl
      AND INDEX_NAME = p_idx
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_tbl, '` ADD KEY `', p_idx, '` (', p_cols, ')');
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$

DROP PROCEDURE IF EXISTS `__st_add_unique`$$
CREATE PROCEDURE `__st_add_unique`(
  IN p_tbl VARCHAR(64), IN p_idx VARCHAR(64), IN p_cols VARCHAR(255)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_tbl
      AND INDEX_NAME = p_idx
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_tbl, '` ADD UNIQUE KEY `', p_idx, '` (', p_cols, ')');
    PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$

DELIMITER ;

-- ────────────────────────────────────────────────────────────────────
-- 1. MIRROR TABLES (data dari Omnicare)
-- ────────────────────────────────────────────────────────────────────

-- 1.1 omnicare_location — branch list dari /get/location
CREATE TABLE IF NOT EXISTS `omnicare_location` (
  `id`              varchar(20) NOT NULL COMMENT 'Omnicare place_id (numeric string, e.g. 29957)',
  `name`            varchar(255) NOT NULL,
  `address`         text DEFAULT NULL,
  `phone`           varchar(50) DEFAULT NULL,
  `wa_number`       varchar(50) DEFAULT NULL,
  `fax`             varchar(50) DEFAULT NULL,
  `email`           varchar(255) DEFAULT NULL,
  `type`            varchar(50) DEFAULT NULL COMMENT 'Klinik | RS',
  `village`         varchar(255) DEFAULT NULL,
  `sub_district`    varchar(255) DEFAULT NULL,
  `city`            varchar(255) DEFAULT NULL,
  `province`        varchar(255) DEFAULT NULL,
  `is_parent`       tinyint(1) NOT NULL DEFAULT 0,
  `last_synced_at`  timestamp NULL DEFAULT NULL,
  `raw_payload`     longtext DEFAULT NULL COMMENT 'Full JSON from API for future field access',
  `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`      timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.2 omnicare_polyclinic — list dari /get/polyclinic_list (per place)
CREATE TABLE IF NOT EXISTS `omnicare_polyclinic` (
  `poly_id`         varchar(20) NOT NULL COMMENT 'Omnicare polyclinic ID',
  `place_id`        varchar(20) NOT NULL,
  `name`            varchar(255) NOT NULL,
  `last_synced_at`  timestamp NULL DEFAULT NULL,
  `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`      timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`poly_id`, `place_id`),
  KEY `idx_omnicare_poly_place` (`place_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.3 omnicare_doctor — list dari /get/doctor_list (per place)
CREATE TABLE IF NOT EXISTS `omnicare_doctor` (
  `doc_id`            varchar(20) NOT NULL COMMENT 'Omnicare doctor reference ID per branch',
  `place_id`          varchar(20) NOT NULL,
  `doc_name`          varchar(255) NOT NULL,
  `gender`            varchar(20) DEFAULT NULL,
  `poly_id`           varchar(20) DEFAULT NULL,
  `poly_name`         varchar(255) DEFAULT NULL,
  `specialty`         varchar(255) DEFAULT NULL,
  `email`             varchar(255) DEFAULT NULL,
  `mobile_number`     varchar(50) DEFAULT NULL,
  `session_duration`  int(11) DEFAULT NULL COMMENT 'Slot duration in minutes',
  `prefix_queue`      varchar(10) DEFAULT NULL,
  `room_name`         varchar(255) DEFAULT NULL,
  `book_online`       tinyint(1) NOT NULL DEFAULT 0,
  `online_consultation` tinyint(1) NOT NULL DEFAULT 0,
  `receive_comission` tinyint(1) NOT NULL DEFAULT 0,
  `duty_period`       longtext DEFAULT NULL COMMENT 'JSON object: day-of-week → time ranges',
  `last_synced_at`    timestamp NULL DEFAULT NULL,
  `raw_payload`       longtext DEFAULT NULL,
  `created_at`        timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`        timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`doc_id`, `place_id`),
  KEY `idx_omnicare_doctor_place` (`place_id`),
  KEY `idx_omnicare_doctor_name` (`doc_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.4 omnicare_patient — list dari /get/all_patients (paginated, dedupe across branches)
CREATE TABLE IF NOT EXISTS `omnicare_patient` (
  `pid`                     varchar(50) NOT NULL COMMENT 'Omnicare patient ID, format S-YYMM-NNNN or B-YYMM-NNNN',
  `last_id`                 varchar(20) DEFAULT NULL COMMENT 'Internal numeric ID, pagination cursor',
  `old_pid`                 varchar(50) DEFAULT NULL,
  `first_name`              varchar(255) DEFAULT NULL,
  `last_name`               varchar(255) DEFAULT NULL,
  `full_name`               varchar(255) DEFAULT NULL COMMENT 'Computed from first + last for search',
  `dob`                     date DEFAULT NULL COMMENT 'Parsed from DD/MM/YYYY API format',
  `gender`                  char(1) DEFAULT NULL COMMENT '1=male, 2=female (Omnicare encoding)',
  `marital_status`          char(1) DEFAULT NULL COMMENT 'M|S|W|D',
  `mobile_number`           varchar(50) DEFAULT NULL COMMENT 'Normalized to 08xxx via App\\Support\\Phone',
  `whatsapp_number`         varchar(50) DEFAULT NULL,
  `home_phone`              varchar(50) DEFAULT NULL,
  `email`                   varchar(255) DEFAULT NULL COMMENT 'Trimmed leading/trailing whitespace',
  `home_address`            text DEFAULT NULL,
  `village`                 varchar(255) DEFAULT NULL,
  `sub_district`            varchar(255) DEFAULT NULL,
  `city`                    varchar(255) DEFAULT NULL,
  `province`                varchar(255) DEFAULT NULL,
  `zipcode`                 varchar(20) DEFAULT NULL,
  `nationality`             varchar(100) DEFAULT NULL,
  `ethnicity`               varchar(100) DEFAULT NULL,
  `religion`                varchar(50) DEFAULT NULL,
  `occupation`              varchar(255) DEFAULT NULL,
  `blood_type`              varchar(5) DEFAULT NULL,
  `identification_num`      varchar(30) DEFAULT NULL COMMENT 'NIK',
  `identification_type`     varchar(50) DEFAULT NULL,
  `emergency_contact`       varchar(255) DEFAULT NULL,
  `emergency_phone`         varchar(50) DEFAULT NULL,
  `membership_id`           varchar(50) DEFAULT NULL,
  `prime_ins_plan_id`       varchar(50) DEFAULT NULL,
  `prime_ins_policy_number` varchar(100) DEFAULT NULL,
  `calculated_final_point`  int(11) NOT NULL DEFAULT 0,
  `last_visit_cal_id`       varchar(50) DEFAULT NULL,
  `last_visit_pp`           varchar(255) DEFAULT NULL,
  `update_timestamp`        bigint(20) DEFAULT NULL COMMENT 'Omnicare Unix ms, for incremental sync',
  `last_synced_at`          timestamp NULL DEFAULT NULL,
  `raw_payload`             longtext DEFAULT NULL,
  `created_at`              timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`              timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`pid`),
  KEY `idx_omnicare_patient_mobile` (`mobile_number`),
  KEY `idx_omnicare_patient_dob` (`dob`),
  KEY `idx_omnicare_patient_name` (`full_name`),
  KEY `idx_omnicare_patient_nik` (`identification_num`),
  KEY `idx_omnicare_patient_updated` (`update_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.5 omnicare_patient_branch — junction: 1 patient bisa visit multiple branches
-- /get/all_patients?place=X return semua patient yang pernah visit place X,
-- jadi 1 pid bisa muncul di multiple place response. Junction track relasi ini.
CREATE TABLE IF NOT EXISTS `omnicare_patient_branch` (
  `pid`             varchar(50) NOT NULL,
  `place_id`        varchar(20) NOT NULL,
  `first_seen_at`   timestamp NULL DEFAULT current_timestamp() COMMENT 'Saat pertama ditemukan di response branch ini',
  `last_seen_at`    timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'Saat terakhir ditemukan',
  PRIMARY KEY (`pid`, `place_id`),
  KEY `idx_omnicare_pb_place` (`place_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.6 omnicare_sales_detail — line-item sales dari /get/sales_detail
-- 1 encounter (visit) bisa punya N rows di sini, link via encounter_id.
CREATE TABLE IF NOT EXISTS `omnicare_sales_detail` (
  `trx_id`            varchar(40) NOT NULL COMMENT 'Unique transaction identifier (timestamp + suffix)',
  `trx_date`          date NOT NULL,
  `invoice_num`       varchar(50) DEFAULT NULL,
  `service_type`      varchar(10) DEFAULT NULL COMMENT 'RJ | RI | OTC | MCU',
  `item_code`         varchar(50) DEFAULT NULL COMMENT 'SKU, e.g. 26CON178',
  `item_type`         char(1) DEFAULT NULL COMMENT 'P=procedure, D=drug, S=supply',
  `item_name`         varchar(500) NOT NULL,
  `item_extra_name`   varchar(500) DEFAULT NULL,
  `qty_sold`          decimal(10,2) NOT NULL DEFAULT 0,
  `net_sell_price`    decimal(15,2) NOT NULL DEFAULT 0,
  `gross_price`       decimal(15,2) NOT NULL DEFAULT 0,
  `discount`          decimal(15,2) NOT NULL DEFAULT 0,
  `pajak`             decimal(15,2) NOT NULL DEFAULT 0,
  `base_price`        decimal(15,2) NOT NULL DEFAULT 0,
  `usage_cost`        decimal(15,2) NOT NULL DEFAULT 0,
  `net_profit`        decimal(15,2) NOT NULL DEFAULT 0,
  `polyclinic`        varchar(255) DEFAULT NULL,
  `p_id`              varchar(50) DEFAULT NULL COMMENT 'Omnicare patient ID',
  `p_name`            varchar(255) DEFAULT NULL,
  `p_ins_id`          varchar(50) DEFAULT NULL COMMENT '"0" or "-1" = cash, else insurance plan ID',
  `p_insurance`       varchar(255) DEFAULT NULL,
  `p_email`           varchar(255) DEFAULT NULL COMMENT 'Trimmed',
  `redeem_rx`         tinyint(1) NOT NULL DEFAULT 0,
  `sales_notes`       text DEFAULT NULL,
  `value_point`       int(11) NOT NULL DEFAULT 0,
  `point_expired_date` date DEFAULT NULL,
  `doctor`            varchar(255) DEFAULT NULL,
  `doc_reff_id`       varchar(20) DEFAULT NULL COMMENT 'Omnicare doctor ID for join with omnicare_doctor',
  `checkout_time`     bigint(20) DEFAULT NULL COMMENT 'Unix ms',
  `encounter_id`      varchar(40) DEFAULT NULL COMMENT 'Link to medical_record.record_id (V1.x). "0" = not linked.',
  `place_id`          varchar(20) NOT NULL,
  `branch_name`       varchar(255) DEFAULT NULL,
  `username`          varchar(100) DEFAULT NULL COMMENT 'Staff who input the transaction',
  `payment_status`    varchar(20) NOT NULL DEFAULT 'unpaid' COMMENT 'cash_paid | insurance_paid | unpaid',
  `last_synced_at`    timestamp NULL DEFAULT NULL,
  `raw_payload`       longtext DEFAULT NULL,
  `created_at`        timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`        timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`trx_id`),
  KEY `idx_omnicare_sales_place_date` (`place_id`, `trx_date`),
  KEY `idx_omnicare_sales_patient` (`p_id`),
  KEY `idx_omnicare_sales_encounter` (`encounter_id`),
  KEY `idx_omnicare_sales_doctor` (`doc_reff_id`),
  KEY `idx_omnicare_sales_item` (`item_code`),
  KEY `idx_omnicare_sales_payment` (`payment_status`),
  KEY `idx_omnicare_sales_date` (`trx_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.7 omnicare_product_sales_daily — aggregate snapshot harian dari /get/product_sales
-- 1 row per (place, date, item). Untuk trend analysis.
CREATE TABLE IF NOT EXISTS `omnicare_product_sales_daily` (
  `id`              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `place_id`        varchar(20) NOT NULL,
  `snapshot_date`   date NOT NULL COMMENT 'End date dari range yang di-pull',
  `item_id`         varchar(20) NOT NULL COMMENT 'Omnicare inventory item ID',
  `item_type`       char(1) DEFAULT NULL,
  `item_name`       varchar(500) NOT NULL,
  `group_name`      varchar(255) DEFAULT NULL,
  `sku`             varchar(100) DEFAULT NULL,
  `manufacturer`    varchar(255) DEFAULT NULL,
  `category`        varchar(255) DEFAULT NULL,
  `qty_sold`        decimal(10,2) NOT NULL DEFAULT 0,
  `sum_price`       decimal(15,2) NOT NULL DEFAULT 0 COMMENT 'Net revenue after discounts',
  `gross_price`     decimal(15,2) NOT NULL DEFAULT 0,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0,
  `discount_pct`    decimal(8,4) NOT NULL DEFAULT 0,
  `sum_base`        decimal(15,2) NOT NULL DEFAULT 0,
  `sum_extra`       decimal(15,2) NOT NULL DEFAULT 0,
  `comission`       decimal(15,2) NOT NULL DEFAULT 0,
  `net_profit`      decimal(15,2) NOT NULL DEFAULT 0,
  `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_omnicare_psd` (`place_id`, `snapshot_date`, `item_id`),
  KEY `idx_omnicare_psd_date` (`snapshot_date`),
  KEY `idx_omnicare_psd_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.8 omnicare_sync_log — audit trail tiap sync run
CREATE TABLE IF NOT EXISTS `omnicare_sync_log` (
  `id`              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `endpoint`        varchar(100) NOT NULL COMMENT '/get/location, /get/sales_detail, dst.',
  `place_id`        varchar(20) DEFAULT NULL,
  `params`          longtext DEFAULT NULL COMMENT 'JSON: date range, page, dll.',
  `started_at`      timestamp NOT NULL DEFAULT current_timestamp(),
  `finished_at`     timestamp NULL DEFAULT NULL,
  `duration_ms`     int(11) DEFAULT NULL,
  `status`          enum('running','success','error','partial') NOT NULL DEFAULT 'running',
  `records_count`   int(11) NOT NULL DEFAULT 0,
  `pages_fetched`   int(11) NOT NULL DEFAULT 0,
  `error_message`   text DEFAULT NULL,
  `triggered_by`    varchar(50) NOT NULL DEFAULT 'cron' COMMENT 'cron | manual | api',
  PRIMARY KEY (`id`),
  KEY `idx_omnicare_log_endpoint_started` (`endpoint`, `started_at`),
  KEY `idx_omnicare_log_status` (`status`),
  KEY `idx_omnicare_log_place` (`place_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 2. RECONCILIATION TABLE (Phase 5)
-- ────────────────────────────────────────────────────────────────────

-- 2.1 reservation_omnicare_link — explicit link reservation ↔ encounter
-- NULL raw_cus_reservation_id = walk-in (encounter tanpa reservation)
-- NULL omnicare_record_id     = no-show (reservation tanpa encounter)
CREATE TABLE IF NOT EXISTS `reservation_omnicare_link` (
  `id`                       varchar(36) NOT NULL COMMENT 'UUID',
  `raw_cus_reservation_id`   varchar(255) DEFAULT NULL COMMENT 'NULL = walk-in (orphan encounter)',
  `omnicare_record_id`       varchar(40) DEFAULT NULL COMMENT 'encounter_id from sales_detail. NULL = no-show',
  `place_id`                 varchar(20) NOT NULL,
  `match_confidence`         enum('exact','high','medium','low','manual','no_match') NOT NULL DEFAULT 'no_match',
  `match_method`             varchar(50) DEFAULT NULL COMMENT 'phone_date_branch | name_date | manual | orphan_walkin | orphan_noshow',
  `service_match`            enum('matches','partial','deviated','mismatch','unknown') NOT NULL DEFAULT 'unknown',
  `expected_revenue`         decimal(15,2) DEFAULT NULL,
  `actual_revenue`           decimal(15,2) DEFAULT NULL,
  `revenue_variance`         decimal(15,2) DEFAULT NULL COMMENT 'actual - expected',
  `payment_status`           varchar(20) DEFAULT NULL COMMENT 'paid | partial | unpaid (aggregated)',
  `encounter_date`           date DEFAULT NULL,
  `reconciled_at`            timestamp NOT NULL DEFAULT current_timestamp(),
  `reconciled_by`            varchar(255) NOT NULL DEFAULT 'system' COMMENT 'user_id if manual, "system" if auto',
  `notes`                    text DEFAULT NULL,
  `created_at`               timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`               timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rol_reservation` (`raw_cus_reservation_id`),
  KEY `idx_rol_encounter` (`omnicare_record_id`),
  KEY `idx_rol_place_date` (`place_id`, `encounter_date`),
  KEY `idx_rol_confidence` (`match_confidence`),
  KEY `idx_rol_service_match` (`service_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 3. MAPPING COLUMNS pada tabel SweetTreats existing (additive)
-- ────────────────────────────────────────────────────────────────────

CALL __st_add_col(
  'raw_branch', 'omnicare_place_id',
  "`omnicare_place_id` varchar(20) DEFAULT NULL COMMENT 'Omnicare branch ID (numeric string)' AFTER `name`"
);

CALL __st_add_unique('raw_branch', 'uq_raw_branch_omnicare', '`omnicare_place_id`');

CALL __st_add_col(
  'raw_doctor', 'omnicare_doc_id',
  "`omnicare_doc_id` varchar(20) DEFAULT NULL COMMENT 'Omnicare doctor reference ID' AFTER `name`"
);

CALL __st_add_unique('raw_doctor', 'uq_raw_doctor_omnicare', '`omnicare_doc_id`');

-- ────────────────────────────────────────────────────────────────────
-- 4. INITIAL DATA: populate branch mapping berdasarkan nama
-- ────────────────────────────────────────────────────────────────────

-- Match by case-insensitive name contains. Existing branches yang ditemukan
-- via smoke test 2026-05-20: Bintaro(29957), Sunter(29958), Kalideres(29959),
-- Cinere(30439), Pamulang(30941). ABG(31163) EXCLUDED — placeholder.

UPDATE `raw_branch` SET `omnicare_place_id` = '29957'
  WHERE `omnicare_place_id` IS NULL AND LOWER(`name`) LIKE '%bintaro%' LIMIT 1;

UPDATE `raw_branch` SET `omnicare_place_id` = '29958'
  WHERE `omnicare_place_id` IS NULL AND LOWER(`name`) LIKE '%sunter%' LIMIT 1;

UPDATE `raw_branch` SET `omnicare_place_id` = '29959'
  WHERE `omnicare_place_id` IS NULL AND LOWER(`name`) LIKE '%kalideres%' LIMIT 1;

UPDATE `raw_branch` SET `omnicare_place_id` = '30439'
  WHERE `omnicare_place_id` IS NULL AND LOWER(`name`) LIKE '%cinere%' LIMIT 1;

UPDATE `raw_branch` SET `omnicare_place_id` = '30941'
  WHERE `omnicare_place_id` IS NULL AND LOWER(`name`) LIKE '%pamulang%' LIMIT 1;

-- Note: omnicare_doc_id untuk raw_doctor di-populate via Artisan command setelah
-- /get/doctor_list di-sync (auto-match by name, manual review unmatched).

-- ────────────────────────────────────────────────────────────────────
-- CLEANUP procedures
-- ────────────────────────────────────────────────────────────────────

DROP PROCEDURE IF EXISTS `__st_add_col`;
DROP PROCEDURE IF EXISTS `__st_add_idx`;
DROP PROCEDURE IF EXISTS `__st_add_unique`;

-- =====================================================================
-- Verifikasi (jalankan manual setelah eksekusi):
--   SHOW TABLES LIKE 'omnicare_%';
--   SHOW TABLES LIKE 'reservation_omnicare_link';
--   SHOW COLUMNS FROM raw_branch  LIKE 'omnicare_place_id';
--   SHOW COLUMNS FROM raw_doctor  LIKE 'omnicare_doc_id';
--   SELECT id, name, omnicare_place_id FROM raw_branch ORDER BY name;
-- Jalankan file ini 2x → run kedua harus no-op.
-- =====================================================================
