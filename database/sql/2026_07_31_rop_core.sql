-- ════════════════════════════════════════════════════════════════════════════
-- Pengajuan / ROP (Request of Payment) — tabel inti (Fase 1)
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks penuh: docs/rencana/rencana-pengajuan-rop.md (§8 data model).
-- Pengganti formulir kertas "Request of Payment": pengaju (kepala departemen)
-- mengajukan → Finance ACC → dibayar transfer → pengaju konfirmasi selesai.
--
-- Lima tabel:
--   1. rop_cost_centre — master kode cost centre (BIN/SUN/…/IT/MKT/FIN/HQ)
--   2. vendors         — master penerima pembayaran (seed dari Accurate)
--   3. rop_counter     — sekuens nomor per cost centre per bulan
--   4. rop_requests    — pengajuan itu sendiri
--   5. rop_status_log  — jejak audit tiap transisi status
--
-- Keputusan desain (jangan diubah tanpa baca plan):
--   • Cost centre jadi TABEL, bukan enum di kolom. Cabang baru / departemen
--     baru cukup INSERT — tidak perlu ALTER TABLE + deploy kode. Kode CC juga
--     dipakai di nomor ROP, jadi harus stabil & bisa dinonaktifkan tanpa
--     menghapus (riwayat nomor lama tetap valid).
--   • `coa_no` DAN `coa_name` di-SNAPSHOT ke rop_requests, bukan cuma FK ke
--     staging Accurate. accurate_staging_glaccount di-resync berkala dan bisa
--     berubah nama/hilang — riwayat pengajuan tidak boleh ikut berubah arti.
--   • Daftar akun beban diambil PER BUKU Accurate (rop_cost_centre.accurate_slug),
--     BUKAN gabungan semua cabang. Alasannya nyata: nomor akun yang sama
--     berarti lain di tiap buku — 600010 = "Beban Tunjangan Karyawan" di
--     bintaro, "Beban THR" di cinere, "Beban Training" di holding. Menggabung
--     daftarnya = Finance memilih arti yang salah tanpa sadar.
--   • Penomoran lewat rop_counter + `LAST_INSERT_ID(last_no + 1)` (atomik di
--     MySQL), BUKAN `MAX(number)+1` yang balapan kalau dua orang submit barengan.
--   • Tiga slot lampiran yang beda MAKNA, jangan digabung: attachment_path
--     (penawaran/invoice dari pengaju), paid_proof_path (bukti transfer
--     Finance), nota_path (nota pertanggungjawaban saat barang diterima).
--   • Kolom fase 2 disiapkan NULL sejak sekarang (approved_by/at untuk ACC
--     Executive > 2 jt, budget_ref untuk budgeting Finance) supaya tidak perlu
--     ALTER tabel produksi nanti. Logikanya BELUM ada — biarkan kosong.
--   • Status pakai bahasa Indonesia, sama persis dengan kamus status di UI
--     (docs/brief-desain/design-brief-rop.md) — hindari terjemahan bolak-balik.
--   • Edit setelah diajukan TIDAK diperbolehkan (audit bersih) → yang ada cuma
--     tolak + ajukan ulang; `resubmit_of` merantai pengajuan ulang ke asalnya.
--
-- Collation utf8mb4_unicode_ci — samain `users`/`raw_branch` (JOIN tanpa
-- "illegal mix of collations"). Idempotent: CREATE IF NOT EXISTS + INSERT IGNORE.
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. rop_cost_centre — master cost centre.
--    Cabang nempel ke raw_branch (buat filter/laporan per cabang); departemen
--    kantor raw_branch_id NULL. Kode = potongan {CC} di nomor ROP.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rop_cost_centre` (
  `kode`          varchar(8)   NOT NULL              COMMENT 'BIN/SUN/KAL/PAM/CIN/IT/MKT/FIN/HQ — dipakai di nomor ROP, jangan diubah setelah dipakai',
  `nama`          varchar(64)  NOT NULL              COMMENT 'label buat manusia: "Bintaro", "Marketing"',
  `tipe`          enum('cabang','kantor') NOT NULL   COMMENT 'cabang = klinik, kantor = departemen pusat',
  `raw_branch_id` varchar(255) DEFAULT NULL          COMMENT 'FK raw_branch.id — terisi untuk tipe=cabang, NULL untuk kantor',
  `accurate_slug` varchar(32)  DEFAULT NULL          COMMENT 'buku Accurate sumber daftar akun beban (accurate_staging_glaccount.slug); kantor pakai holding',
  `urutan`        smallint(6)  NOT NULL DEFAULT 0    COMMENT 'urutan tampil di dropdown',
  `aktif`         tinyint(1)   NOT NULL DEFAULT 1    COMMENT '0 = tidak bisa dipilih lagi; riwayat nomor lama tetap valid',
  `created_at`    timestamp NULL DEFAULT NULL,
  `updated_at`    timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`kode`),
  UNIQUE KEY `uq_cc_branch` (`raw_branch_id`),
  CONSTRAINT `fk_cc_branch` FOREIGN KEY (`raw_branch_id`) REFERENCES `raw_branch` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cabang: raw_branch_id diisi lewat pencocokan NAMA (id UUID beda antar
-- environment — jangan pernah hardcode id cabang di file SQL).
INSERT IGNORE INTO `rop_cost_centre` (`kode`, `nama`, `tipe`, `raw_branch_id`, `accurate_slug`, `urutan`, `created_at`, `updated_at`)
SELECT 'BIN', 'Bintaro',   'cabang', rb.id, 'bintaro',   1, NOW(), NOW() FROM `raw_branch` rb WHERE rb.name = 'Bintaro'
UNION ALL
SELECT 'SUN', 'Sunter',    'cabang', rb.id, 'sunter',    2, NOW(), NOW() FROM `raw_branch` rb WHERE rb.name = 'Sunter'
UNION ALL
SELECT 'KAL', 'Kalideres', 'cabang', rb.id, 'kalideres', 3, NOW(), NOW() FROM `raw_branch` rb WHERE rb.name = 'Kalideres'
UNION ALL
SELECT 'PAM', 'Pamulang',  'cabang', rb.id, 'pamulang',  4, NOW(), NOW() FROM `raw_branch` rb WHERE rb.name = 'Pamulang'
UNION ALL
SELECT 'CIN', 'Cinere',    'cabang', rb.id, 'cinere',    5, NOW(), NOW() FROM `raw_branch` rb WHERE rb.name = 'Cinere';

INSERT IGNORE INTO `rop_cost_centre` (`kode`, `nama`, `tipe`, `raw_branch_id`, `accurate_slug`, `urutan`, `created_at`, `updated_at`) VALUES
  ('IT',  'IT',        'kantor', NULL, 'holding', 6, NOW(), NOW()),
  ('MKT', 'Marketing', 'kantor', NULL, 'holding', 7, NOW(), NOW()),
  ('FIN', 'Finance',   'kantor', NULL, 'holding', 8, NOW(), NOW()),
  ('HQ',  'Kantor Pusat', 'kantor', NULL, 'holding', 9, NOW(), NOW());

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. vendors — master penerima pembayaran.
--    Seed dari Accurate (`vendor/list.do`) lewat `php artisan rop:tarik-vendor`.
--    Pengaju boleh mengetik bebas (rop_requests.pay_to_text) kalau vendornya
--    belum terdaftar — daftar ini bantuan, bukan gerbang.
--    Kolom bank_* disiapkan untuk fase 2 (rekening tersimpan); v1 biarkan NULL.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vendors` (
  `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`               varchar(191) NOT NULL          COMMENT 'nama vendor/supplier seperti di Accurate',
  `accurate_id`        varchar(40)  DEFAULT NULL      COMMENT 'id vendor di Accurate — kunci sinkron, NULL untuk vendor buatan tangan',
  `accurate_no`        varchar(40)  DEFAULT NULL      COMMENT 'nomor/kode vendor di Accurate',
  `slug_sumber`        varchar(32)  DEFAULT NULL      COMMENT 'database Accurate asal (holding/bintaro/…) — jejak dari mana ditarik',
  `bank_nama`          varchar(64)  DEFAULT NULL      COMMENT 'FASE 2 — biarkan NULL di v1',
  `bank_rekening`      varchar(64)  DEFAULT NULL      COMMENT 'FASE 2 — biarkan NULL di v1',
  `bank_atas_nama`     varchar(191) DEFAULT NULL      COMMENT 'FASE 2 — biarkan NULL di v1',
  `sumber`             enum('accurate','manual') NOT NULL DEFAULT 'manual' COMMENT 'accurate = hasil tarik, manual = ditambah dari layar',
  `aktif`              tinyint(1)   NOT NULL DEFAULT 1,
  `last_synced_at`     datetime     DEFAULT NULL      COMMENT 'terakhir disamakan dengan Accurate',
  `created_at`         timestamp NULL DEFAULT NULL,
  `updated_at`         timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- Kunci dedup = NAMA (collation _ci → tidak peduli besar-kecil huruf).
  -- JANGAN bikin accurate_id unik: id vendor Accurate hanya unik DI DALAM satu
  -- buku, jadi id 300 bisa milik "Chaniago Dental Lab" di pamulang sekaligus
  -- vendor lain di sunter (ketahuan saat tarik pertama 2026-07-31).
  UNIQUE KEY `uq_vendor_nama` (`nama`),
  KEY `idx_vendor_accurate` (`accurate_id`, `slug_sumber`),
  KEY `idx_vendor_aktif` (`aktif`, `nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. rop_counter — sekuens nomor per cost centre per bulan.
--    Dipakai dengan pola atomik (lihat RopNumberService):
--      INSERT INTO rop_counter (cost_centre, periode, last_no) VALUES (?, ?, 1)
--        ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1);
--      SELECT LAST_INSERT_ID();
--    Reset otomatis tiap bulan karena `periode` (YYMM) jadi bagian PK.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rop_counter` (
  `cost_centre` varchar(8) NOT NULL,
  `periode`     char(4)    NOT NULL              COMMENT 'YYMM, mis. 2608 = Agustus 2026',
  `last_no`     int(11)    NOT NULL DEFAULT 0    COMMENT 'nomor urut terakhir yang sudah dipakai',
  `updated_at`  timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`cost_centre`, `periode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. rop_requests — pengajuan.
--    Alur status: diajukan → disetujui → dibayar → selesai
--                        ↘ ditolak (wajib alasan, bisa diajukan ulang)
--                        ↘ kadaluarsa (30 hari tak disentuh, oleh cron)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rop_requests` (
  `id`              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `number`          varchar(32)  NOT NULL             COMMENT 'ROP/{CC}/{YYMM}/{NNN} — mis. ROP/BIN/2608/001',
  `requester_id`    varchar(255) DEFAULT NULL         COMMENT 'FK users.id — "Made by (User)" di kertas; SET NULL kalau user dihapus, riwayat tetap',
  `cost_centre`     varchar(8)   NOT NULL             COMMENT 'FK rop_cost_centre.kode',
  `raw_branch_id`   varchar(255) DEFAULT NULL         COMMENT 'FK raw_branch — turunan cost centre (NULL untuk kantor); disalin biar filter cabang murah',
  `vendor_id`       bigint(20) UNSIGNED DEFAULT NULL  COMMENT 'FK vendors — NULL kalau pengaju mengetik bebas',
  `pay_to_text`     varchar(191) DEFAULT NULL         COMMENT 'penerima ketik-bebas; terisi kalau vendor_id NULL',
  `description`     varchar(500) NOT NULL             COMMENT 'keterangan kebutuhan (kolom Description di kertas)',
  `amount`          decimal(15,2) NOT NULL            COMMENT 'rupiah',
  `needed_by`       date         DEFAULT NULL         COMMENT 'butuh kapan — sinyal prioritas utama antrian Finance',
  `is_urgent`       tinyint(1)   NOT NULL DEFAULT 0   COMMENT 'flag darurat; dipakai hemat, mencolok di antrian',
  `budget_ref`      varchar(64)  DEFAULT NULL         COMMENT 'FASE 2 — BUDGET NUMBER, nunggu budgeting Finance selesai',
  `status`          enum('diajukan','disetujui','ditolak','dibayar','selesai','kadaluarsa') NOT NULL DEFAULT 'diajukan',
  `reject_reason`   varchar(500) DEFAULT NULL         COMMENT 'WAJIB terisi saat status=ditolak',
  `coa_no`          varchar(20)  DEFAULT NULL         COMMENT 'akun beban Accurate — diisi FINANCE saat ACC, bukan pengaju',
  `coa_name`        varchar(191) DEFAULT NULL         COMMENT 'snapshot nama akun saat ACC (staging bisa berubah/resync)',
  `attachment_path` varchar(255) DEFAULT NULL         COMMENT 'relatif dari public/ — foto penawaran/invoice dari PENGAJU',
  `paid_proof_path` varchar(255) DEFAULT NULL         COMMENT 'relatif dari public/ — bukti transfer dari FINANCE',
  `nota_path`       varchar(255) DEFAULT NULL         COMMENT 'relatif dari public/ — nota pertanggungjawaban saat pengaju konfirmasi selesai',
  `reviewed_by`     varchar(255) DEFAULT NULL         COMMENT 'FK users.id — Finance yang ACC/tolak',
  `reviewed_at`     datetime     DEFAULT NULL,
  `approved_by`     varchar(255) DEFAULT NULL         COMMENT 'FASE 2 — Executive untuk nominal > threshold; v1 selalu NULL',
  `approved_at`     datetime     DEFAULT NULL         COMMENT 'FASE 2 — v1 selalu NULL',
  `paid_by`         varchar(255) DEFAULT NULL         COMMENT 'FK users.id — Finance yang menandai dibayar',
  `paid_at`         datetime     DEFAULT NULL         COMMENT 'tanggal uang keluar (diisi Finance, boleh mundur dari hari input)',
  `completed_by`    varchar(255) DEFAULT NULL         COMMENT 'FK users.id — yang konfirmasi barang/dana diterima (pengaju, atau Finance yang menutup)',
  `completed_at`    datetime     DEFAULT NULL,
  `resubmit_of`     bigint(20) UNSIGNED DEFAULT NULL  COMMENT 'FK rop_requests.id — pengajuan ulang dari yang ditolak',
  `reminded_at`     datetime     DEFAULT NULL         COMMENT 'terakhir Finance diingatkan (antrian > 3 hari) — biar tidak spam tiap hari',
  `expired_at`      datetime     DEFAULT NULL         COMMENT 'kapan dinyatakan kadaluarsa oleh cron (30 hari diam)',
  `created_at`      timestamp NULL DEFAULT NULL       COMMENT 'tanggal pengajuan (kolom Date di kertas)',
  `updated_at`      timestamp NULL DEFAULT NULL,
  `deleted_at`      timestamp NULL DEFAULT NULL       COMMENT 'soft delete — pembatalan oleh pengaju sebelum di-ACC',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rop_number` (`number`),
  KEY `idx_rop_antrian` (`status`, `needed_by`),
  KEY `idx_rop_requester` (`requester_id`, `status`),
  KEY `idx_rop_cc_periode` (`cost_centre`, `created_at`),
  KEY `idx_rop_branch` (`raw_branch_id`),
  KEY `idx_rop_vendor` (`vendor_id`),
  KEY `idx_rop_dibayar` (`status`, `paid_at`),
  KEY `idx_rop_resubmit` (`resubmit_of`),
  CONSTRAINT `fk_rop_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rop_reviewer`  FOREIGN KEY (`reviewed_by`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rop_approver`  FOREIGN KEY (`approved_by`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rop_payer`     FOREIGN KEY (`paid_by`)      REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rop_completer` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rop_cc`        FOREIGN KEY (`cost_centre`)  REFERENCES `rop_cost_centre` (`kode`),
  CONSTRAINT `fk_rop_branch`    FOREIGN KEY (`raw_branch_id`) REFERENCES `raw_branch` (`id`),
  CONSTRAINT `fk_rop_vendor`    FOREIGN KEY (`vendor_id`)    REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rop_resubmit`  FOREIGN KEY (`resubmit_of`)  REFERENCES `rop_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. rop_status_log — jejak audit tiap transisi.
--    Ditulis oleh service, TIDAK pernah di-update/hapus. Ini yang bikin
--    timeline di layar detail pengaju bisa menjawab "siapa & kapan".
--    (Kontras: tabel reservasi tidak punya audit-trail dan itu pernah bikin
--    data tertimpa tak bisa dipulihkan — jangan ulangi pola itu di sini.)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rop_status_log` (
  `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rop_request_id` bigint(20) UNSIGNED NOT NULL,
  `dari_status`    varchar(16)  DEFAULT NULL         COMMENT 'NULL = baris pertama (pengajuan dibuat)',
  `ke_status`      varchar(16)  NOT NULL,
  `actor_id`       varchar(255) DEFAULT NULL         COMMENT 'FK users.id — NULL kalau perubahan oleh sistem (cron kadaluarsa)',
  `catatan`        varchar(500) DEFAULT NULL         COMMENT 'alasan tolak / catatan bayar / keterangan sistem',
  `created_at`     timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_roplog_request` (`rop_request_id`, `id`),
  CONSTRAINT `fk_roplog_request` FOREIGN KEY (`rop_request_id`) REFERENCES `rop_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_roplog_actor`   FOREIGN KEY (`actor_id`)       REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
