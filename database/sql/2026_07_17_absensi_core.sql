-- ════════════════════════════════════════════════════════════════════════════
-- Rekonsiliasi Absensi — tabel inti (Fase A)
-- ════════════════════════════════════════════════════════════════════════════
-- Tujuan: fondasi penyimpanan absen fingerprint 5 cabang. Tiga tabel:
--         1. att_device_user  — mapping (cabang, No.ID mesin) -> user DataCenter
--         2. att_import_batch  — jejak tiap upload CSV (siapa, kapan, format, hasil)
--         3. att_scan          — scan mentah, APPEND-ONLY, idempotent via hash
--
-- Konteks penuh: docs/rencana/rencana-rekonsiliasi-absensi.md (§5.2)
--
-- Keputusan desain yang dibuktikan dari data (jangan diubah tanpa baca plan):
--   • Kunci mapping = (raw_branch_id, device_user_id), BUKAN No.ID saja.
--     26 No.ID dipakai orang berbeda di cabang berbeda — tiap mesin mulai dari 1.
--   • att_scan append-only + hash UNIQUE. Export mesin KUMULATIF (nyimpen sejak
--     2021), tiap upload ngasih data lama lagi. Hash bikin re-upload idempoten =
--     nggak dobel. Ini juga fondasi deteksi-edit (§9.2): scan yang ada di DB tapi
--     hilang dari export baru = nggak pernah keluar dari mesin.
--   • user_id NULLABLE di scan & device_user: scan masuk DULU (mentah suci),
--     mapping ke user nyusul. Scan yang belum ter-mapping tetap tersimpan utuh.
--   • source enum siap multi-sumber: 'fingerprint' sekarang, 'adms'/'mobile' nanti
--     (Fase E) tanpa ubah skema.
--
-- Collation utf8mb4_unicode_ci — WAJIB samain `users` & `raw_branch` biar JOIN
-- via user_id / raw_branch_id nggak kena "illegal mix of collations".
--
-- Idempotent: CREATE IF NOT EXISTS. Seeding device_user menyusul terpisah
--             (butuh akun user per orang dulu — Fase A1).
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. att_device_user — mapping (cabang, No.ID mesin) -> user
--    Many-to-one: satu orang bisa punya banyak baris (Rohma terdaftar di 4 mesin).
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_device_user` (
  `id`              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `raw_branch_id`   varchar(255) NOT NULL              COMMENT 'FK raw_branch.id — cabang si mesin',
  `device_user_id`  varchar(32)  NOT NULL              COMMENT 'No.ID di mesin (string, ada yg 8 digit spt 66666666)',
  `user_id`         varchar(255) DEFAULT NULL          COMMENT 'FK users.id — NULL = belum di-mapping',
  `nama_di_mesin`   varchar(191) DEFAULT NULL          COMMENT 'Nama panggilan dari mesin, cuma buat referensi mata manusia',
  `status`          enum('mapped','ignored','unmapped') NOT NULL DEFAULT 'unmapped'
                                                        COMMENT 'ignored = dokter/tes, scan disimpan tapi nggak dinilai',
  `catatan`         varchar(255) DEFAULT NULL,
  `created_at`      timestamp NULL DEFAULT NULL,
  `updated_at`      timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branch_device` (`raw_branch_id`, `device_user_id`),
  KEY `idx_devuser_user` (`user_id`),
  KEY `idx_devuser_status` (`status`),
  CONSTRAINT `fk_devuser_branch` FOREIGN KEY (`raw_branch_id`) REFERENCES `raw_branch` (`id`),
  CONSTRAINT `fk_devuser_user`   FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. att_import_batch — jejak tiap upload
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_import_batch` (
  `id`              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `raw_branch_id`   varchar(255) NOT NULL              COMMENT 'Cabang dipilih manual saat upload (Departemen di file BUKAN cabang)',
  `uploaded_by`     varchar(255) DEFAULT NULL          COMMENT 'FK users.id — yang upload (finance)',
  `file_name`       varchar(255) DEFAULT NULL,
  `format`          enum('log','matriks_a','matriks_b') NOT NULL
                                                        COMMENT 'log=Bintaro per-scan | matriks_a=Lap.Detail | matriks_b=Catatan Kehadiran (Pamulang)',
  `periode_from`    date DEFAULT NULL,
  `periode_to`      date DEFAULT NULL,
  `total_rows`      int(11) NOT NULL DEFAULT 0         COMMENT 'baris/sel scan terbaca dari file',
  `new_rows`        int(11) NOT NULL DEFAULT 0         COMMENT 'scan baru masuk att_scan',
  `dup_rows`        int(11) NOT NULL DEFAULT 0         COMMENT 'scan sudah ada (hash bentrok) — normal, export kumulatif',
  `unmapped_rows`   int(11) NOT NULL DEFAULT 0         COMMENT 'scan dari device_user_id yang belum di-mapping',
  `diagnostic`      json DEFAULT NULL                 COMMENT 'peringatan parser: kolom gagal detect, baris sampah, dll',
  `uploaded_at`     timestamp NULL DEFAULT NULL,
  `created_at`      timestamp NULL DEFAULT NULL,
  `updated_at`      timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_batch_branch` (`raw_branch_id`),
  KEY `idx_batch_uploader` (`uploaded_by`),
  CONSTRAINT `fk_batch_branch` FOREIGN KEY (`raw_branch_id`) REFERENCES `raw_branch` (`id`),
  CONSTRAINT `fk_batch_user`   FOREIGN KEY (`uploaded_by`)   REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. att_scan — scan mentah, APPEND-ONLY. Jangan pernah UPDATE isinya.
--    Semua penilaian ada di tabel turunan (att_day, Fase B) yang bisa dihitung ulang.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_scan` (
  `id`              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id`        bigint(20) UNSIGNED DEFAULT NULL   COMMENT 'FK att_import_batch — batch mana yg masukin scan ini',
  `raw_branch_id`   varchar(255) NOT NULL              COMMENT 'cabang MESIN tempat scan diambil (satu hari kerja bisa lintas cabang)',
  `device_user_id`  varchar(32)  NOT NULL,
  `user_id`         varchar(255) DEFAULT NULL          COMMENT 'hasil resolve device_user -> user saat import. NULL kalau belum mapped',
  `scanned_at`      datetime     NOT NULL,
  `source`          enum('fingerprint','mobile','adms','manual') NOT NULL DEFAULT 'fingerprint',
  `raw_row`         json DEFAULT NULL                 COMMENT 'baris asli dari file, buat audit & re-parse',
  `hash`            char(40) NOT NULL                 COMMENT 'sha1(raw_branch_id|device_user_id|scanned_at) — kunci idempotensi',
  `created_at`      timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scan_hash` (`hash`),
  KEY `idx_scan_user_time` (`user_id`, `scanned_at`),
  KEY `idx_scan_branch_dev` (`raw_branch_id`, `device_user_id`),
  KEY `idx_scan_batch` (`batch_id`),
  KEY `idx_scan_time` (`scanned_at`),
  CONSTRAINT `fk_scan_batch`  FOREIGN KEY (`batch_id`)      REFERENCES `att_import_batch` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scan_branch` FOREIGN KEY (`raw_branch_id`) REFERENCES `raw_branch` (`id`),
  CONSTRAINT `fk_scan_user`   FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
