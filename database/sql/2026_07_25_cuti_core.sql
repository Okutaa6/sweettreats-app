-- ════════════════════════════════════════════════════════════════════════════
-- Jatah Cuti per kontrak 12 bulan — skema inti
-- ════════════════════════════════════════════════════════════════════════════
-- docs/rencana/rencana-jatah-cuti.md. Aturan: 12 hari/kontrak; sisa positif HANGUS
-- saat kontrak baru, utang (minus) KEBAWA; cuti bersama motong massal dengan
-- pengecualian per orang; saldo boleh minus (= "ganti hari", bukan blokir).
-- Tanggal kontrak BOLEH kosong (datanya menyusul) — tanpa tanggal tidak ada
-- reset otomatis, sisanya tetap jalan normal.
--
-- Saldo = saldo_awal - SUM(mutasi.hari) pada periode berjalan.
-- Idempotent: CREATE TABLE IF NOT EXISTS.
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `att_cuti_periode` (
  `id`            bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id`       varchar(255) NOT NULL,
  `saldo_awal`    decimal(4,1) NOT NULL DEFAULT 12.0
                  COMMENT 'periode pertama = Sisa Cuti Excel; reset = 12 - utang lama',
  `kontrak_mulai` date DEFAULT NULL COMMENT 'boleh kosong — data menyusul',
  `kontrak_akhir` date DEFAULT NULL COMMENT 'mulai + 12 bln - 1 hari',
  -- 1 = periode aktif, NULL = sudah ditutup. Pakai NULL (bukan 0) supaya
  -- UNIQUE di bawah hanya mengunci "1 periode aktif per orang" — baris
  -- riwayat (NULL) boleh banyak.
  `berjalan`      tinyint DEFAULT 1,
  `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cuti_periode_berjalan` (`user_id`, `berjalan`),
  KEY `idx_cuti_periode_user` (`user_id`),
  CONSTRAINT `fk_cuti_periode_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `att_cuti_mutasi` (
  `id`          bigint unsigned NOT NULL AUTO_INCREMENT,
  `periode_id`  bigint unsigned NOT NULL,
  `user_id`     varchar(255) NOT NULL COMMENT 'denormal biar rekap cepat',
  `tanggal`     date NOT NULL COMMENT 'tanggal cutinya',
  `hari`        decimal(3,1) NOT NULL COMMENT 'positif = pakai jatah; negatif = koreksi/ganti hari',
  `jenis`       enum('cuti','cuti_bersama','koreksi','ganti_hari') NOT NULL,
  `sumber`      enum('pengajuan','massal','manual') NOT NULL DEFAULT 'manual',
  `ref_id`      varchar(64) DEFAULT NULL COMMENT 'id att_request kalau dari alur ACC',
  `keterangan`  varchar(200) DEFAULT NULL,
  `dibuat_oleh` varchar(255) DEFAULT NULL COMMENT 'user_id pencatat; NULL = sistem',
  `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  -- Anti dobel-potong: satu pengajuan/batch tak boleh memotong orang yang
  -- sama di tanggal sama 2x (race ACC / dobel-klik). NULL ref_id bebas.
  UNIQUE KEY `uq_cuti_mutasi_ref` (`sumber`, `ref_id`, `user_id`, `tanggal`),
  KEY `idx_cuti_mutasi_periode` (`periode_id`),
  KEY `idx_cuti_mutasi_user` (`user_id`, `tanggal`),
  CONSTRAINT `fk_cuti_mutasi_periode` FOREIGN KEY (`periode_id`) REFERENCES `att_cuti_periode` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cuti_mutasi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
