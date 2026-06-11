-- ════════════════════════════════════════════════════════════════════════════
-- Omnicare Medical Records — backup rekam medis dari /get/all_medical_records
-- ════════════════════════════════════════════════════════════════════════════
-- Tujuan: backup field-field rekam medis (anamnesis, diagnosa, treatment plan,
--         resep, prosedur) yang sebelumnya tidak ter-sync.
--
-- Latar belakang: insiden Omnicare 24 Agt 2025–Apr 2026 hilangkan file rontgen.
-- Field teks rekam medis aman di Omnicare tapi belum kita backup. SQL ini
-- ciptakan tabel mirror + endpoint sync baru dipanggil dari SyncOmnicare.
--
-- Idempotent: CREATE IF NOT EXISTS — aman dijalankan berulang tanpa lose data.
--             Setelah eksekusi, populate via `php artisan omnicare:sync --endpoint=medical`
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `omnicare_medical_record` (
  `record_id`            varchar(40)  NOT NULL COMMENT 'Omnicare medical record_id (sama dgn encounter_id di sales)',
  `branch_id`            varchar(40)  NOT NULL COMMENT 'Omnicare place_id — gabung ke PK karena record_id bisa kolisi antar cabang',
  `date`                 date         NOT NULL,
  `time_in`              time         DEFAULT NULL,
  `time_out`             time         DEFAULT NULL,

  -- Patient identifier & demografi (snapshot at time of record)
  `p_id`                 varchar(50)  DEFAULT NULL,
  `p_name`               varchar(255) DEFAULT NULL,
  `p_dob`                varchar(20)  DEFAULT NULL COMMENT 'API format DD/MM/YYYY — disimpan apa adanya',
  `p_mobile_number`      varchar(50)  DEFAULT NULL,
  `p_whatsapp_number`    varchar(50)  DEFAULT NULL,
  `p_home_phone`         varchar(50)  DEFAULT NULL,
  `p_home_address`       text         DEFAULT NULL,
  `p_email`              varchar(255) DEFAULT NULL,
  `p_identification_num` varchar(50)  DEFAULT NULL COMMENT 'NIK / no identitas',
  `p_ins_category`       varchar(100) DEFAULT NULL,
  `p_ins_number`         varchar(100) DEFAULT NULL,
  `p_ins_id`             varchar(50)  DEFAULT NULL,
  `p_insurance`          varchar(255) DEFAULT NULL,
  `p_ip_color`           varchar(50)  DEFAULT NULL,
  `pp_village`           varchar(255) DEFAULT NULL,

  -- Visit meta
  `queue_number`         varchar(20)  DEFAULT NULL,
  `is_new_patient`       tinyint(1)   NOT NULL DEFAULT 0,
  `booking_code`         varchar(50)  DEFAULT NULL,
  `branch`               varchar(255) DEFAULT NULL,
  `doctor`               varchar(255) DEFAULT NULL,
  `doc_id`               varchar(20)  DEFAULT NULL,

  -- ═══ INTI REKAM MEDIS — yang paling perlu di-backup ═══
  `chief_complaint`      text         DEFAULT NULL COMMENT 'Keluhan utama pasien',
  `subjective`           text         DEFAULT NULL COMMENT 'Anamnesis subjektif',
  `objective`            text         DEFAULT NULL COMMENT 'Pemeriksaan objektif klinis',
  `diagnose_note`        text         DEFAULT NULL COMMENT 'Diagnosa narrative',
  `diagnoses`            text         DEFAULT NULL COMMENT 'Diagnoses terstruktur (ICD/internal)',
  `plan_note`            text         DEFAULT NULL,
  `treatment_plan_note`  text         DEFAULT NULL COMMENT 'Rencana perawatan',
  `procedures`           text         DEFAULT NULL COMMENT 'Kode+nama prosedur, pisah ;',
  `prescription`         text         DEFAULT NULL COMMENT 'Resep obat',

  -- Vital signs (string karena API balas string termasuk "0")
  `vs_weight`            varchar(20)  DEFAULT NULL,
  `vs_height`            varchar(20)  DEFAULT NULL,
  `vs_temperature`       varchar(20)  DEFAULT NULL,
  `vs_blood_pressure`    varchar(50)  DEFAULT NULL,
  `vs_pulse`             varchar(20)  DEFAULT NULL,
  `vs_respiration`       varchar(20)  DEFAULT NULL,
  `vs_waist_line`        varchar(20)  DEFAULT NULL,

  -- Audit
  `raw_payload`          longtext     DEFAULT NULL COMMENT 'Full JSON response untuk forensik / field baru',
  `last_synced_at`       datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`           datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`record_id`, `branch_id`),
  KEY `idx_mr_date`           (`date`),
  KEY `idx_mr_pid`            (`p_id`),
  KEY `idx_mr_branch_date`    (`branch_id`, `date`),
  KEY `idx_mr_doc`            (`doc_id`),
  KEY `idx_mr_booking`        (`booking_code`),
  KEY `idx_mr_synced`         (`last_synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
