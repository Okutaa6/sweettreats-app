-- ════════════════════════════════════════════════════════════════════════════
-- Rekonsiliasi Absensi — form pengajuan & approval (Fase C)
-- ════════════════════════════════════════════════════════════════════════════
-- Tujuan: DEKLARASI MANUSIA yg jadi kebenaran tertinggi di atas scan (§4).
--         Lima jenis form + jejak approval 2 lapis.
--
-- Konteks: docs/rencana/rencana-rekonsiliasi-absensi.md §4, §5.2, §5.4.
--
-- Cara engine pakai (AttendanceReconciler):
--   • cuti/izin/wfh/libur APPROVED nutup tanggalnya → status att_day jadi jenis itu
--     (bukan alpa). WFH & libur = hadir versi lain, resolve "tak ada scan".
--   • lembur APPROVED → lembur_menit_disetujui di att_day (scan TIDAK pernah bikin
--     lembur otomatis, §3.2). Form tetap WAJIB walau tombol pre-fill dari scan.
--   • verifikasi = INFORMASI buat approver (klaim nyambung/selisih/tak-bisa-dicek
--     vs scan), BUKAN vonis. Sistem tak pernah approve/reject sendiri (§4).
--
-- Approval (§5.4): lapis 1 dari POHON group_path (atasan langsung, disaring cabang);
--   lapis 2 dari IZIN (finance/management), bukan pohon. Cuti & lembur = 2 lapis;
--   izin/libur/wfh cukup lapis 1.
--
-- Collation utf8mb4_unicode_ci. Idempotent: CREATE IF NOT EXISTS.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `att_request` (
  `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        varchar(255) NOT NULL,
  `raw_branch_id`  varchar(255) DEFAULT NULL,
  `tipe`           enum('cuti','izin','libur','lembur','wfh') NOT NULL,
  `sub_tipe`       enum('jam','tambah_hari') DEFAULT NULL     COMMENT 'lembur: jam (hari kerja) / tambah_hari (hari libur)',
  `tanggal_from`   date NOT NULL,
  `tanggal_to`     date NOT NULL,
  `jam_from`       time DEFAULT NULL,
  `jam_to`         time DEFAULT NULL,
  `alasan`         text DEFAULT NULL,
  `foto`           varchar(255) DEFAULT NULL                 COMMENT 'bukti (wajib utk lembur/wfh yg tak ada scan)',
  `claimed_menit`  int(11) DEFAULT NULL                      COMMENT 'menit lembur diklaim',
  `scan_evidence`  json DEFAULT NULL                         COMMENT 'snapshot scan terkait saat pengajuan',
  `verifikasi`     enum('nyambung','selisih','tidak_bisa_dicek') DEFAULT NULL COMMENT 'hasil sanding vs scan — INFORMASI, bukan vonis',
  `status`         enum('draft','pending_l1','pending_l2','approved','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `created_at`     timestamp NULL DEFAULT NULL,
  `updated_at`     timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_req_user_tgl` (`user_id`, `tanggal_from`, `tanggal_to`),
  KEY `idx_req_status` (`status`),
  CONSTRAINT `fk_req_user`   FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_req_branch` FOREIGN KEY (`raw_branch_id`) REFERENCES `raw_branch` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `att_request_approval` (
  `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id`       bigint(20) UNSIGNED NOT NULL,
  `level`            tinyint(4) NOT NULL                     COMMENT '1 = atasan langsung (pohon); 2 = finance/management (izin)',
  `expected_role_id` varchar(255) DEFAULT NULL               COMMENT 'role yg diharapkan approve (dari pohon), buat audit',
  `approver_user_id` varchar(255) DEFAULT NULL,
  `action`           enum('approve','reject') DEFAULT NULL,
  `catatan`          varchar(255) DEFAULT NULL,
  `acted_at`         timestamp NULL DEFAULT NULL,
  `created_at`       timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_appr_request` (`request_id`, `level`),
  CONSTRAINT `fk_appr_request`  FOREIGN KEY (`request_id`)       REFERENCES `att_request` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appr_approver` FOREIGN KEY (`approver_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
