-- ════════════════════════════════════════════════════════════════════════════
-- IT Request Tracker — skema inti (Fase 1) — 2026-09-02
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di DB `sweettreats` (lokal/dev). Prod hanya atas perintah eksplisit.
--
-- Rencana: docs/rencana/rencana-it-tracker.md §14.2 — enam tabel + counter.
-- Fase 1 memakai it_ticket + it_ticket_fase + it_form + it_counter; tiga tabel
-- gerbang (it_fr / it_test_case / it_temuan) ikut dibuat sekarang karena mesin
-- gerbang (PhaseEngine::gateInfo) sudah membacanya — UI pengisiannya Fase 2.
--
-- ⚠️ Fitur ini TIDAK menyentuh tabel lama mana pun (uji terima §14.6 no. 17).
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. it_ticket — satu baris per tiket.
--    ⚠️ prioritas_pemohon ≠ prioritas_it — DUA kolom, jangan pernah disatukan
--    (keputusan rencana §11). Pemohon memberi masukan; IT yang memutuskan.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `it_ticket` (
  `id`                 bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nomor`              varchar(16)  NOT NULL                COMMENT 'IT-2026-001 — dipakai sampai ke commit message',
  `judul`              varchar(255) NOT NULL,
  `sumber`             varchar(12)  NOT NULL DEFAULT 'permintaan' COMMENT 'permintaan / inisiatif / insiden',
  `pemohon_user_id`    varchar(255) DEFAULT NULL            COMMENT 'users.id — NULL untuk inisiatif IT / insiden',
  `raw_branch_id`      varchar(255) DEFAULT NULL            COMMENT 'cabang pemohon saat mengajukan',
  `jenis_permintaan`   varchar(32)  DEFAULT NULL            COMMENT 'Modul baru / Fitur baru / Perubahan fitur / Integrasi / Lainnya',
  `jenjang`            char(1)      DEFAULT NULL            COMMENT 'S/M/L — ditetapkan IT saat triase, NULL sebelum itu',
  `bobot`              tinyint(4)   DEFAULT NULL            COMMENT '1-5, diisi saat triase',
  `prioritas_pemohon`  varchar(8)   DEFAULT NULL            COMMENT 'Rendah/Sedang/Tinggi — masukan pemohon, BUKAN keputusan',
  `prioritas_it`       varchar(4)   DEFAULT NULL            COMMENT 'P1-P3 — keputusan IT saat triase',
  `pic_user_id`        varchar(255) DEFAULT NULL            COMMENT 'users.id pengerja (Ekky/Reza)',
  `fase`               varchar(12)  NOT NULL DEFAULT 'TRIASE' COMMENT 'TRIASE/ANTRE/ANALISIS/DEV/UJI/UAT/GOLIVE/EVALUASI/SELESAI/DITUNDA/DITOLAK',
  `fase_since`         datetime     NOT NULL                COMMENT 'kapan fase ini dimasuki — sumber hitungan "berapa hari"',
  `induk_id`           bigint(20) unsigned DEFAULT NULL     COMMENT 'self-FK: modul induk untuk fitur di dalamnya',
  `evaluator_user_id`  varchar(255) DEFAULT NULL            COMMENT 'default pemohon, bisa dialihkan (rencana §12)',
  `alasan_parkir`      text         DEFAULT NULL            COMMENT 'alasan DITUNDA / DITOLAK — wajib terisi saat parkir',
  `created_at`         timestamp NULL DEFAULT NULL,
  `updated_at`         timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_it_ticket_nomor` (`nomor`),
  KEY `ix_it_ticket_fase` (`fase`),
  KEY `ix_it_ticket_pemohon` (`pemohon_user_id`),
  KEY `ix_it_ticket_induk` (`induk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. it_ticket_fase — riwayat fase. Tabel terpenting kedua setelah it_ticket.
--    Dari sini lahir: waitOf().days (NOW() - masuk_at baris berjalan), biaya
--    menunggu yang bisa dicetak, dan jejak audit yang dicari Konsultan.
--    ⛔ JANGAN pernah menambah kolom "berapa hari menunggu" — angka tersimpan
--    akan basi diam-diam; ia HARUS dihitung dari masuk_at.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `it_ticket_fase` (
  `id`             bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id`      bigint(20) unsigned NOT NULL,
  `fase`           varchar(12)  NOT NULL,
  `masuk_at`       datetime     NOT NULL,
  `keluar_at`      datetime     DEFAULT NULL               COMMENT 'NULL = fase sedang berjalan',
  `oleh_user_id`   varchar(255) DEFAULT NULL               COMMENT 'siapa yang memindahkan tiket ke fase ini',
  `pihak_menunggu` varchar(16)  DEFAULT NULL               COMMENT 'it / pemohon / executive / evaluator / bersama — NULL utk ANTRE & fase akhir',
  PRIMARY KEY (`id`),
  KEY `ix_itf_ticket` (`ticket_id`, `masuk_at`),
  KEY `ix_itf_berjalan` (`ticket_id`, `keluar_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. it_form — payload naratif per formulir (FRM-IT-01..07), JSON.
--    Untuk field prosa: latar belakang, ruang lingkup, masukan end user, dst.
--    dikonfirmasi_at = paraf Kelas B ("Benar, ini yang kukerjakan").
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `it_form` (
  `id`                bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id`         bigint(20) unsigned NOT NULL,
  `kode`              varchar(12)  NOT NULL                COMMENT 'FRM-IT-01 .. FRM-IT-07',
  `payload`           longtext     DEFAULT NULL            COMMENT 'JSON — isi formulir',
  `dikonfirmasi_at`   datetime     DEFAULT NULL,
  `dikonfirmasi_oleh` varchar(255) DEFAULT NULL,
  `created_at`        timestamp NULL DEFAULT NULL,
  `updated_at`        timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_it_form` (`ticket_id`, `kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. it_fr — baris Kebutuhan Fungsional (FRM-IT-02 §B).
--    Dinormalkan, BUKAN JSON — gerbang ANALISIS→DEV membacanya
--    (≥1 FR dengan kriteria_diterima terisi).
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `it_fr` (
  `id`                bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id`         bigint(20) unsigned NOT NULL,
  `kode`              varchar(8)   NOT NULL                COMMENT 'FR-01, FR-02, ...',
  `kebutuhan`         text         NOT NULL,
  `prioritas`         varchar(8)   DEFAULT NULL,
  `kriteria_diterima` text         DEFAULT NULL            COMMENT 'field terpenting di seluruh sistem — jangan dibuat opsional di UI',
  `urutan`            int(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_it_fr_ticket` (`ticket_id`, `urutan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. it_test_case — TC (FRM-IT-04 §B).
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `it_test_case` (
  `id`         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id`  bigint(20) unsigned NOT NULL,
  `kode`       varchar(8)   NOT NULL                       COMMENT 'TC-01, TC-02, ...',
  `skenario`   text         NOT NULL,
  `diharapkan` text         DEFAULT NULL,
  `aktual`     text         DEFAULT NULL,
  `status`     varchar(8)   DEFAULT NULL                   COMMENT 'pass / fail — NULL = belum dijalankan (gerbang menolak)',
  PRIMARY KEY (`id`),
  KEY `ix_it_tc_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 6. it_temuan — temuan uji (FRM-IT-04 §C).
--    Gerbang UJI→UAT membacanya: nol temuan Major berstatus terbuka.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `it_temuan` (
  `id`          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id`   bigint(20) unsigned NOT NULL,
  `deskripsi`   text         NOT NULL,
  `tingkat`     varchar(8)   NOT NULL DEFAULT 'Minor'      COMMENT 'Minor / Major',
  `pic_user_id` varchar(255) DEFAULT NULL,
  `status`      varchar(12)  NOT NULL DEFAULT 'Terbuka'    COMMENT 'Terbuka / Selesai',
  PRIMARY KEY (`id`),
  KEY `ix_it_temuan_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 7. it_counter — sekuens nomor tiket per TAHUN (IT-2026-001).
--    Pola atomik yang sama dengan rop_counter (RopService::urutBaru) —
--    LAST_INSERT_ID(last_no + 1), BUKAN MAX(id)+1 (balapan).
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `it_counter` (
  `periode`    char(4)   NOT NULL                          COMMENT 'YYYY, mis. 2026',
  `last_no`    int(11)   NOT NULL DEFAULT 0                COMMENT 'nomor urut terakhir yang sudah dipakai',
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`periode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
