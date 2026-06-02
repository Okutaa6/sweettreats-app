-- =====================================================================
-- customer_omnicare_link — jembatan identitas pasien — 2026-05-21
-- Jalankan di phpMyAdmin DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   Hubungkan pasien SweetTreats (raw_customer, dari sheets) dengan pasien
--   Omnicare (omnicare_patient, dari API) — orang yang SAMA, ID beda.
--   NON-DESTRUKTIF: kedua tabel sumber tetap utuh, ini cuma tabel jembatan.
--
--   Link 1:1 per orang. UNIQUE di dua kolom mencegah satu pasien ke-map ganda.
--   Di-isi oleh command `php artisan omnicare:match-patients` (auto-match
--   phone + DOB + nama). Yang ambigu TIDAK di-link — disisakan untuk review manual.
--
-- Idempoten: CREATE TABLE IF NOT EXISTS — aman dijalankan ulang.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `customer_omnicare_link` (
  `id`               varchar(36) NOT NULL,
  `raw_customer_id`  varchar(255) NOT NULL COMMENT 'raw_customer.id (UUID SweetTreats)',
  `omnicare_pid`     varchar(50)  NOT NULL COMMENT 'omnicare_patient.pid (mis. S-2604-0001)',
  `match_confidence` enum('exact','high','medium','manual') NOT NULL DEFAULT 'medium',
  `match_method`     varchar(60)  DEFAULT NULL COMMENT 'mis. phone+dob+nama',
  `match_score`      tinyint(4)   DEFAULT NULL COMMENT 'skor 0-100 dari auto-matcher',
  `matched_at`       timestamp NOT NULL DEFAULT current_timestamp(),
  `matched_by`       varchar(255) NOT NULL DEFAULT 'system' COMMENT 'system | user_id (kalau manual)',
  `notes`            text DEFAULT NULL,
  `created_at`       timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`       timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_col_customer` (`raw_customer_id`),
  UNIQUE KEY `uq_col_omnicare` (`omnicare_pid`),
  KEY `idx_col_confidence` (`match_confidence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Verifikasi:
--   SELECT match_confidence, COUNT(*) FROM customer_omnicare_link GROUP BY match_confidence;
-- =====================================================================
