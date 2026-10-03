-- =====================================================================
-- Tracking Ortho — RIWAYAT follow-up: tabel `ort_followup` — 2026-10-07
-- IDEMPOTENT (CREATE TABLE IF NOT EXISTS; menjalankannya berulang tidak
-- mengubah apa pun dan tidak menyentuh baris yang sudah ada).
-- Jalankan di DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- ---------------------------------------------------------------------
-- Konteks
-- ---------------------------------------------------------------------
-- ort_control menyimpan follow-up TERAKHIR saja: satu fu_date, satu fu_by,
-- satu fu_result, satu fu_note (2026_09_23 + 2026_10_05). Follow-up kedua ke
-- pasien yang sama MENIMPA yang pertama. Jadi riwayatnya tidak ada, dan
-- prompt_jadwal_kontrol.md (Phase 2, kolom Follow Up) meminta daftar bernomor:
--     1. 30 Sep 2026, oleh Nama Sales
--     2. 1 Sep 2026, oleh Nama Sales
-- Tidak ada sumber yang bisa merendernya hari ini.
--
-- Prompt juga minta "setiap klik disimpan" dengan UNIQUE (pasien, tanggal),
-- dan tombol yang otomatis hijau lagi besok TANPA cron — cukup cek tanggal.
-- UNIQUE di tabel inilah yang menegakkan keduanya sekaligus: satu baris per
-- pasien per hari, dan "sudah di-FU hari ini?" jadi satu lookup index.
--
-- Kuncinya PASIEN, bukan kontrol. Dua alasan, dan keduanya nyata:
--   1. Daftar kerja hanya memuat pasien yang punya kontrol TERBUKA
--      (OrtQuery.php:157). Pasien tanpa kontrol terbuka ada di antrean
--      "Belum punya jadwal" — mereka tetap ditelepon, dan follow-up-nya harus
--      tercatat di riwayat yang sama, bukan hilang karena tidak punya
--      ort_control untuk ditempeli.
--   2. Riwayat pasien harus utuh melintasi kontrol: "sudah 4x ditelepon bulan
--      ini" adalah pertanyaan tentang pasien, bukan tentang satu jadwal.
-- ort_control_id tetap disimpan sebagai KONTEKS — kontrol mana yang sedang
-- dikejar saat telepon itu terjadi — tapi ia NULL-able.
--
-- ---------------------------------------------------------------------
-- Yang dilakukan skrip ini
-- ---------------------------------------------------------------------
--   Membuat satu tabel baru, ort_followup. Kolom hasil/catatan/kanal bernama
--   PERSIS seperti di ort_control (fu_result, fu_note, fu_channel) supaya
--   kamus nilainya tetap satu: App\Models\OrtControl::FU_HASIL dan FU_KANAL.
--   Dua kolom pencatat, sesuai pola repo yang sudah ada:
--     fu_by      = session('name'), nama tampilan — ini yang dirender
--                  "oleh Nama Sales" di daftar bernomor
--     created_by = session('id'), untuk penjejakan
--
-- ---------------------------------------------------------------------
-- Yang SENGAJA TIDAK dilakukan
-- ---------------------------------------------------------------------
--   - TIDAK ada FOREIGN KEY. Modul ini nol FK karena collation prod campur
--     (2026_09_24_ort_audit_collation_prod.sql). Keterkaitan dijaga aplikasi.
--   - TIDAK ada backfill dari ort_control.fu_date. Sekilas menggoda: satu
--     INSERT ... SELECT akan memberi satu baris riwayat per kontrol. Tapi itu
--     akan MENGARANG riwayat — fu_date yang tersimpan sekarang adalah
--     follow-up terakhir, dan memasukkannya sebagai satu-satunya baris membuat
--     pasien yang ditelepon enam kali terlihat ditelepon sekali. Riwayat yang
--     salah lebih buruk daripada riwayat yang mulai dari kosong, karena ia
--     tidak bisa dibedakan dari yang benar. Daftarnya mulai hari ini.
--   - TIDAK menghapus fu_date/fu_result/fu_note dari ort_control. Keduanya
--     hidup berdampingan dengan sengaja: ort_control menjawab "sudah di-FU?"
--     untuk baris yang sudah terambil tanpa kueri tambahan (dipakai chip
--     "Sudah di-FU" di tabel), ort_followup menjawab "berapa kali dan oleh
--     siapa". Menghapus yang pertama akan menambah satu kueri ke setiap
--     muat halaman untuk informasi yang sudah ada di tangan.
--   - TIDAK ada index terpisah (ort_case_id, fu_date): UNIQUE-nya sudah
--     menyediakannya persis. Index kembar hanya memperlambat tulis.
--   - TIDAK menyentuh akses/menu, jadi `php artisan access:rebuild` TIDAK
--     diperlukan untuk skrip ini.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `ort_followup` (
  `id`             char(36)     NOT NULL,
  `ort_case_id`    char(36)     NOT NULL COMMENT 'FK app-level ke ort_case.id. Kuncinya PASIEN — lihat Konteks',
  `ort_control_id` char(36)     DEFAULT NULL COMMENT 'Kontrol yang sedang dikejar saat telepon ini. NULL = pasien belum punya jadwal (antrean), tetap sah',
  `place_id`       varchar(20)  NOT NULL COMMENT 'omnicare place_id cabang yang menelepon. Untuk laporan per cabang tanpa join ke ort_case',

  `fu_date`        date         NOT NULL COMMENT 'Tanggal follow-up, zona Asia/Jakarta. Satu baris per pasien per tanggal (UNIQUE)',
  `fu_channel`     varchar(16)  DEFAULT NULL COMMENT 'wa|telepon|langsung|lainnya. Kamus di OrtControl::FU_KANAL. NULL = tidak dicatat',
  `fu_result`      varchar(24)  DEFAULT NULL COMMENT 'dihubungi|tidak_respon|minta_jadwal_ulang|menolak|salah_nomor. Kamus di OrtControl::FU_HASIL, divalidasi aplikasi',
  `fu_note`        varchar(255) DEFAULT NULL COMMENT 'Catatan bebas, pelengkap fu_result',

  `fu_by`          varchar(255) DEFAULT NULL COMMENT 'Nama tampilan pencatat (session name) — inilah yang dirender sebagai oleh Nama Sales di daftar bernomor',
  `created_by`     varchar(255) DEFAULT NULL COMMENT 'Id pengguna pencatat (session id) — untuk penjejakan, bukan untuk tampilan',
  `created_at`     timestamp    NOT NULL DEFAULT current_timestamp(),

  PRIMARY KEY (`id`),

  -- Penegak aturan prompt: satu follow-up per pasien per hari. Tombol jadi abu
  -- hari itu dan hijau lagi besok TANPA cron — cukup lookup ke index ini.
  -- Sekaligus satu-satunya index yang dibutuhkan untuk "riwayat pasien X,
  -- terbaru di atas"; index (ort_case_id, fu_date) terpisah akan kembar.
  UNIQUE KEY `uq_ort_followup_hari` (`ort_case_id`, `fu_date`),

  KEY `idx_ort_followup_place` (`place_id`, `fu_date`) COMMENT 'Laporan "berapa follow-up cabang ini bulan ini"',
  KEY `idx_ort_followup_ctl`   (`ort_control_id`)      COMMENT 'Urungkan: temukan baris riwayat milik kontrol yang baru di-FU'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verifikasi:
--   SELECT COUNT(*) FROM information_schema.TABLES
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ort_followup';
-- Harus 1.
--
--   SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
--     FROM information_schema.STATISTICS
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ort_followup'
--    ORDER BY INDEX_NAME, SEQ_IN_INDEX;
-- Harus memuat uq_ort_followup_hari dengan NON_UNIQUE = 0.
--
-- Uji UNIQUE-nya sekali, lalu bersihkan:
--   INSERT INTO ort_followup (id, ort_case_id, place_id, fu_date)
--        VALUES ('t1','UJI','29957',CURDATE());
--   INSERT INTO ort_followup (id, ort_case_id, place_id, fu_date)
--        VALUES ('t2','UJI','29957',CURDATE());   -- harus GAGAL #1062
--   DELETE FROM ort_followup WHERE ort_case_id = 'UJI';
-- =====================================================================
