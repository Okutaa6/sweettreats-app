-- ════════════════════════════════════════════════════════════════════════════
-- Rekonsiliasi Absensi — mapping approver (Fase C)
-- ════════════════════════════════════════════════════════════════════════════
-- Tujuan: "siapa approve siapa" untuk form absen. SENGAJA TERPISAH dari pohon
--         checklist (group_path) — keputusan 2026-07-18.
--
-- Kenapa terpisah (bukan pakai group_path.parent_id):
--   • Struktur absen tak selalu pohon: Asdent punya DUA approver (Nisa + Irma).
--     group_path = 1 role 1 induk, tak muat.
--   • §6.1: naruh role di pohon checklist punya efek samping ke visibility checklist.
--     Mengikat approval absen ke situ = dua sistem saling rusak.
--   • group_path tetap dipakai untuk akses menu (role Finance dll), BUKAN routing approval ini.
--
-- Model: 1 baris = 1 relasi (pengaju -> approver, di level tertentu). Banyak baris
--        per user boleh (Asdent 2 approver di level 1).
--   level 1 = atasan langsung (dari hierarki HR).
--   level 2 = finance/management, hanya untuk item duit (cuti & lembur, §5.4). PENDING.
--
-- Konteks: docs/rencana-rekonsiliasi-absensi.md §5.4.
-- Idempotent: CREATE IF NOT EXISTS.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `att_approver` (
  `id`                bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           varchar(255) NOT NULL              COMMENT 'pengaju',
  `approver_user_id`  varchar(255) NOT NULL,
  `level`             tinyint(4) NOT NULL DEFAULT 1      COMMENT '1=atasan langsung, 2=finance/management',
  `catatan`           varchar(255) DEFAULT NULL,
  `created_at`        timestamp NULL DEFAULT NULL,
  `updated_at`        timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_approver` (`user_id`, `approver_user_id`, `level`),
  KEY `idx_approver_user` (`user_id`, `level`),
  CONSTRAINT `fk_approver_user`     FOREIGN KEY (`user_id`)          REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_approver_approver` FOREIGN KEY (`approver_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
