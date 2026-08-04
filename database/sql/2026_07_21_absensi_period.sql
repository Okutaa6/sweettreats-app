-- ════════════════════════════════════════════════════════════════════════════
-- Rekonsiliasi Absensi — Periode & kontrol finance (Fase D, §5.2 / §7 Fase D)
-- ════════════════════════════════════════════════════════════════════════════
-- Tujuan: finance TUTUP periode penggajian → att_day di rentang itu BEKU (sign-off),
--         reconcile tak lagi menimpanya, lalu export buat payroll. Ini yang bikin
--         cut-off balik ke tgl 25 (finance percaya angkanya, kerja manual mati).
--
-- Contek pola `petty_cash_periods` + close() (§5.1 poin 4). Beda: attendance
-- LINTAS CABANG (att_day tak dimiliki satu cabang, §2.7) → periode default GLOBAL
-- (raw_branch_id NULL = semua cabang/seluruh perusahaan). Kolom cabang tetap ada
-- untuk fleksibilitas (kalau nanti mau tutup per-cabang).
--
-- periode_from/to = rentang tanggal fleksibel (bukan year+month) supaya cut-off
-- non-kalender (mis. 26 s/d 25) kehandle.
--
-- Collation utf8mb4_unicode_ci. Idempotent: CREATE IF NOT EXISTS.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `att_period` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `raw_branch_id` varchar(255) DEFAULT NULL              COMMENT 'NULL = semua cabang (default, attendance lintas cabang)',
  `nama`          varchar(100) DEFAULT NULL              COMMENT 'label opsional, mis. "Gaji Juli 2026"',
  `periode_from`  date NOT NULL,
  `periode_to`    date NOT NULL,
  `status`        enum('open','closed') NOT NULL DEFAULT 'open',
  `closed_by`     varchar(255) DEFAULT NULL,
  `closed_at`     timestamp NULL DEFAULT NULL,
  `catatan`       varchar(255) DEFAULT NULL              COMMENT 'catatan finance saat tutup periode',
  `created_by`    varchar(255) DEFAULT NULL,
  `created_at`    timestamp NULL DEFAULT NULL,
  `updated_at`    timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_period` (`raw_branch_id`, `periode_from`, `periode_to`),
  KEY `idx_period_range` (`periode_from`, `periode_to`),
  KEY `idx_period_status` (`status`),
  CONSTRAINT `fk_period_branch` FOREIGN KEY (`raw_branch_id`) REFERENCES `raw_branch` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_period_closer` FOREIGN KEY (`closed_by`)     REFERENCES `users` (`id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
