-- ════════════════════════════════════════════════════════════════════════════
-- Absen HP (selfie) — tabel inti (Fase 1)
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks penuh: docs/rencana/rencana-absen-hp.md (§5). Empat tabel:
--   1. att_branch_ip  — daftar IP internet terdaftar per cabang (data, bukan kode)
--   2. att_hp_bukti   — bukti tiap absen HP (foto selfie + sinyal), 1 baris/selfie
--   3. att_flag       — antrian review SPV (janggal = flag, BUKAN blokir)
--   4. user_device    — perangkat "Ingat perangkat ini" (remember me)
--
-- Keputusan desain (jangan diubah tanpa baca plan):
--   • att_scan TIDAK diubah strukturnya. Absen HP nulis att_scan source='mobile'
--     dengan device_user_id='HP' (sentinel) dan HASH formula sendiri:
--     sha1('hp|user_id|scanned_at') — WAJIB pakai user_id, kalau pakai formula
--     importer (branch|device|waktu) dua orang absen di detik sama = tabrakan.
--   • WiFi = SAKSI bukan GERBANG: IP tak dikenal ≠ ditolak. Scan tetap masuk
--     (cabang dari pilihan manual), yang janggal cuma jadi att_flag.
--   • Satu IP hanya boleh terdaftar di SATU cabang (UNIQUE ip) — kalau boleh
--     dobel, pencocokan IP→cabang jadi ambigu.
--   • att_flag UNIQUE (user, tanggal, jenis) — flag idempoten, evaluasi boleh
--     jalan berulang tanpa nyampah.
--   • user_device nyimpen HASH token (sha256), bukan tokennya. Token diputar
--     tiap dipakai; revoked_at terisi = mati permanen.
--
-- Collation utf8mb4_unicode_ci — samain `users`/`raw_branch` (JOIN tanpa
-- "illegal mix of collations"). Idempotent: CREATE IF NOT EXISTS.
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. att_branch_ip — IP internet terdaftar per cabang.
--    Banyak SSID ≠ banyak IP; yang dihitung jumlah langganan internet.
--    Diisi lewat halaman admin IT + tombol "daftarkan IP ini" dari flag SPV
--    (lingkaran belajar) — TIDAK pernah hardcode di kode.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_branch_ip` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `raw_branch_id` varchar(255) NOT NULL              COMMENT 'FK raw_branch.id — cabang pemilik jalur internet ini',
  `ip`            varchar(45)  NOT NULL              COMMENT 'IP publik jalur (45 = muat IPv6)',
  `label`         varchar(191) DEFAULT NULL          COMMENT 'buat mata manusia: "jalur utama Cinere", "ruang dokter"',
  `status`        enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif' COMMENT 'nonaktif = ganti ISP/jalur pensiun; riwayat jangan dihapus',
  `last_seen_at`  datetime DEFAULT NULL              COMMENT 'terakhir ada absen yang cocok IP ini — deteksi jalur mati',
  `created_by`    varchar(255) DEFAULT NULL          COMMENT 'FK users.id — siapa yang daftarin',
  `created_at`    timestamp NULL DEFAULT NULL,
  `updated_at`    timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branchip_ip` (`ip`),
  KEY `idx_branchip_branch` (`raw_branch_id`),
  CONSTRAINT `fk_branchip_branch` FOREIGN KEY (`raw_branch_id`) REFERENCES `raw_branch` (`id`),
  CONSTRAINT `fk_branchip_user`   FOREIGN KEY (`created_by`)    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. att_hp_bukti — bukti tiap absen HP. Foto + semua sinyal saat kejadian.
--    att_scan tetap sumber kebenaran buat engine; tabel ini buat mata manusia
--    (strip foto SPV, panel detail flag) + audit.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_hp_bukti` (
  `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          varchar(255) DEFAULT NULL         COMMENT 'FK users.id — SET NULL saat user dihapus (bukti historis tetap)',
  `att_scan_id`      bigint(20) UNSIGNED DEFAULT NULL  COMMENT 'FK att_scan — scan yang lahir dari selfie ini',
  `jenis`            enum('masuk','pulang') NOT NULL,
  `photo_path`       varchar(255) NOT NULL             COMMENT 'relative dari public/, JPEG terkompres ~640px',
  `ip`               varchar(45)  NOT NULL             COMMENT 'IP publik request saat absen',
  `branch_id_cocok`  varchar(255) DEFAULT NULL         COMMENT 'FK raw_branch — hasil pencocokan IP. NULL = IP tak dikenal',
  `branch_id_manual` varchar(255) DEFAULT NULL         COMMENT 'FK raw_branch — pilihan user saat IP tak dikenal',
  `device_id`        bigint(20) UNSIGNED DEFAULT NULL  COMMENT 'FK user_device — perangkat "diingat" yang dipakai. NULL = tanpa remember',
  `device_label`     varchar(120) DEFAULT NULL         COMMENT 'ringkasan UA saat absen (mis. "Android · Chrome") — buat baris Perangkat di panel SPV',
  `is_desktop`       tinyint(1) NOT NULL DEFAULT 0     COMMENT 'UA laptop/PC (Windows/Mac/Linux non-mobile) → penanda SPV, BUKAN blokir',
  `taken_at`         datetime NOT NULL                 COMMENT 'JAM SERVER, jangan pernah percaya jam HP',
  `created_at`       timestamp NULL DEFAULT NULL,
  `updated_at`       timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bukti_scan_user_jenis` (`att_scan_id`, `user_id`, `jenis`),
  KEY `idx_bukti_user_time` (`user_id`, `taken_at`),
  KEY `idx_bukti_scan` (`att_scan_id`),
  KEY `idx_bukti_cocok` (`branch_id_cocok`),
  CONSTRAINT `fk_bukti_user`   FOREIGN KEY (`user_id`)          REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bukti_scan`   FOREIGN KEY (`att_scan_id`)      REFERENCES `att_scan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bukti_cocok`  FOREIGN KEY (`branch_id_cocok`)  REFERENCES `raw_branch` (`id`),
  CONSTRAINT `fk_bukti_manual` FOREIGN KEY (`branch_id_manual`) REFERENCES `raw_branch` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. att_flag — antrian review SPV (Anisa). Siklus: baru → dicek → selesai.
--    Periode gaji TIDAK bisa di-close selama masih ada flag terbuka di
--    rentangnya (guard di AbsensiPeriodController, bukan di DB).
--    Orang fleksibel TIDAK pernah dapat flag (suppress di engine).
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `att_flag` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       varchar(255) DEFAULT NULL          COMMENT 'FK users.id — orang yang di-flag; SET NULL saat user dihapus',
  `work_date`     date NOT NULL                      COMMENT 'hari kerja ybs (sesudah dawn-pushback)',
  `jenis`         enum('ip_asing','perangkat_ganda','pola_ip','luar_seharian','jam_janggal','single_scan','perangkat_desktop') NOT NULL,
  `ref_type`      varchar(32)  DEFAULT NULL          COMMENT 'umumnya att_hp_bukti',
  `ref_id`        bigint(20) UNSIGNED DEFAULT NULL   COMMENT 'id baris ref_type',
  `status`        enum('baru','dicek','selesai') NOT NULL DEFAULT 'baru',
  `hasil`         enum('wajar','perlu_koreksi','pelanggaran') DEFAULT NULL COMMENT 'WAJIB terisi saat status=selesai',
  `catatan`       varchar(500) DEFAULT NULL,
  `ditindak_oleh` varchar(255) DEFAULT NULL          COMMENT 'FK users.id — SPV yang nyelesaiin',
  `ditindak_at`   datetime DEFAULT NULL,
  `created_at`    timestamp NULL DEFAULT NULL,
  `updated_at`    timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_flag_user_date_jenis` (`user_id`, `work_date`, `jenis`),
  KEY `idx_flag_status` (`status`, `work_date`),
  KEY `idx_flag_date` (`work_date`),
  CONSTRAINT `fk_flag_user`    FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_flag_actor`   FOREIGN KEY (`ditindak_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. user_device — "Ingat perangkat ini" (remember me).
--    Nyimpen HASH sha256 token, bukan token. Token hidup di cookie 90 hari,
--    DIPUTAR tiap dipakai. Cabut: logout manual / ganti password / akun
--    nonaktif → revoked_at terisi. UI kelola perangkat nyusul di fitur
--    user-management IT; fase 1 cukup mekanismenya.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_device` (
  `id`           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      varchar(255) NOT NULL               COMMENT 'FK users.id',
  `token_hash`   char(64) NOT NULL                   COMMENT 'sha256(token) — token asli cuma ada di cookie user',
  `label`        varchar(191) DEFAULT NULL           COMMENT 'ringkasan user-agent: "Android · Chrome"',
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at`   datetime DEFAULT NULL               COMMENT 'terisi = perangkat dicabut, token mati permanen',
  `created_at`   timestamp NULL DEFAULT NULL,
  `updated_at`   timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_device_token` (`token_hash`),
  KEY `idx_device_user` (`user_id`),
  CONSTRAINT `fk_device_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
