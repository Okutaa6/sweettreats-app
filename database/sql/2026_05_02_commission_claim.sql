-- =====================================================================
-- Commission Claim Batch (Fase 2D) — 2026-05-02
-- IDEMPOTENT — aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   Sistem klaim komisi 500k batch:
--   - KOC behel kumpulin 5 pasien → klaim 500k
--   - KOC non-behel kumpulin 10 pasien → klaim 500k
--
-- Flow status batch:
--   requested  → KOC baru klik "Klaim"
--   in_review  → Admin/PJ sedang konfirmasi nominal via WA
--   approved   → Diajukan ke finance, menunggu transfer
--   transferred → PJ upload bukti transfer
--   completed  → KOC konfirmasi terima (commission jadi 'paid')
-- =====================================================================

CREATE TABLE IF NOT EXISTS `commission_claim_batch` (
  `id` varchar(36) NOT NULL,
  `raw_affiliate_id` varchar(36) NOT NULL,
  `referral_scope` enum('behel','non_behel') NOT NULL COMMENT 'snapshot scope saat klaim dibuat',
  `patient_count` int(11) NOT NULL COMMENT 'jumlah pasien yang masuk batch ini',
  `total_amount` decimal(12,2) NOT NULL COMMENT 'total komisi dalam batch (harus minimal 500k)',
  `status` enum('requested','in_review','approved','transferred','completed') NOT NULL DEFAULT 'requested',
  `pj_user_id` varchar(255) DEFAULT NULL COMMENT 'creative/PJ yang handle batch ini',
  `notes` text DEFAULT NULL COMMENT 'catatan dari PJ atau admin',
  `tx_proof_path` varchar(500) DEFAULT NULL COMMENT 'path file bukti transfer di public/uploads/commission_proof/',
  `tx_at` timestamp NULL DEFAULT NULL COMMENT 'kapan transfer dilakukan finance',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'KOC klik klaim',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'PJ mulai review',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'PJ approve nominal & ajukan ke finance',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'KOC konfirmasi terima',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_affiliate` (`raw_affiliate_id`),
  KEY `idx_status` (`status`),
  KEY `idx_scope` (`referral_scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Pivot: commission ↔ batch.
-- Konvensi: 1 commission HANYA bisa masuk 1 batch (UNIQUE key).
CREATE TABLE IF NOT EXISTS `commission_claim_item` (
  `id` varchar(36) NOT NULL,
  `commission_claim_batch_id` varchar(36) NOT NULL,
  `raw_commission_id` varchar(36) NOT NULL,
  `amount` decimal(12,2) NOT NULL COMMENT 'snapshot amount commission saat masuk batch',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_commission` (`raw_commission_id`),
  KEY `idx_batch` (`commission_claim_batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
