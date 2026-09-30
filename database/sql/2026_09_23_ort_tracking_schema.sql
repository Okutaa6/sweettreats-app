-- ════════════════════════════════════════════════════════════════════════════
-- Tracking Pasien Ortho — skema inti (4 tabel) — 2026-09-23
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
--
-- KONTEKS
--   Sales melacak pasien behel di Google Sheets per cabang (11–20 tab yang
--   angkanya saling berbeda). Dari 1.639 pasien ortho aktif 2026: 104 pernah
--   kontrol di cabang LAIN sehingga lenyap dari sheet cabangnya, dan 581 (35%)
--   jarang kontrol tapi mustahil dihitung mata di tabel 12 kolom.
--
--   Modul ini menggantikan sheet itu. Pekerjaan Sales tinggal DUA: follow-up
--   dan buat reservasi. Kolom kunjungan diisi mesin dari cermin rekam medis.
--
-- YANG DILAKUKAN
--   • ort_case     — 1 baris = 1 episode perawatan behel (pasang s/d lepas)
--   • ort_control  — 1 baris = 1 jadwal kontrol (SCH/FU/RSV/DATANG/CABANG)
--   • ort_transfer — 1 baris = 1 kejadian pindah cabang, dengan ALASAN
--   • ort_reminder — 1 baris = 1 pengingat ke admin cabang lain
--   Plus seluruh index yang diturunkan dari kueri layar, bukan ditebak.
--
-- YANG SENGAJA TIDAK DILAKUKAN
--   • TIDAK ADA FOREIGN KEY sama sekali. Collation prod campur
--     (utf8mb4_unicode_ci vs utf8mb4_uca1400_ai_ci); FK menuntut collation
--     kolom identik, jadi FK ke raw_* akan DITOLAK saat CREATE TABLE di prod
--     padahal lolos mulus di lokal — kegagalan yang baru ketahuan saat deploy.
--     Semua relasi ditegakkan di level aplikasi (norma repo ini, lihat
--     2026_05_18_contact_anchored_patients.sql baris 56).
--   • TIDAK menyentuh satu pun tabel raw_* maupun omnicare_*. Nol ALTER.
--   • TIDAK menambah index pada omnicare_sales_detail. Pencocok malam dan
--     penyemai sama-sama berjalan per cabang per tanggal, jadi index
--     (place_id, trx_date) yang SUDAH ADA sudah cukup. Index (p_id, trx_date)
--     baru dipasang kalau backfill per-pasien terbukti lambat — ALTER pada
--     tabel 125 ribu baris tidak dibayar di muka tanpa bukti.
--   • TIDAK ada UNIQUE parsial "satu kontrol terbuka per kasus" — MySQL tidak
--     punya. Aturan itu dijaga service, dan `ort:rollup --verify` melaporkan
--     kasus yang melanggar.
--
-- CATATAN TIPE KOLOM
--   PK internal memakai char(36). Kolom PENUNJUK ke tabel lama memakai tipe
--   persis aslinya — varchar(255) untuk raw_customer.id dan
--   raw_cus_reservation.id. Campur tipe ini DISENGAJA; jangan "dirapikan"
--   jadi seragam, itu justru mengundang masalah collation di kemudian hari.
-- ════════════════════════════════════════════════════════════════════════════


-- ════════════════════════════════════════════════════════════════════════════
-- 1. ort_case — satu baris = satu episode perawatan behel
-- ════════════════════════════════════════════════════════════════════════════
-- Kolom ROLLUP di bawah adalah turunan yang DISIMPAN. Alasannya bukan
-- kecepatan render, melainkan karena tiga di antaranya dipakai sebagai
-- PENYARING, bukan tampilan:
--   open_control_id IS NULL      → memilih antrean "Belum punya jadwal"
--   visit_count_180d < 2         → memilih tingkat "Jarang kontrol"
--   owner_place_id               → memilih pasien milik cabang mana
-- Menyaring pada nilai yang dihitung saat baca = full scan tiap buka halaman.
-- Konsekuensinya: HANYA App\Services\Ortho\OrtRollup yang boleh menulis kolom
-- di blok rollup. Jalur tulis lain yang lupa memanggilnya membuat halaman
-- bohong TANPA error.
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ort_case` (
  `id`                  char(36)     NOT NULL,

  -- ── Identitas. Snapshot, supaya kartu terender tanpa lookup ke tabel lain.
  `omnicare_pid`        varchar(50)  DEFAULT NULL COMMENT 'omnicare_patient.pid — jangkar utama. NULL = belum tertaut rekam medis',
  `raw_customer_id`     varchar(255) DEFAULT NULL COMMENT 'FK app-level ke raw_customer.id. NULLABLE: kasus tetap ada walau belum tertaut',
  `patient_name`        varchar(255) NOT NULL,
  `patient_phone`       varchar(30)  DEFAULT NULL COMMENT 'Untuk deep link wa.me',

  -- ── Pemasangan. Tidak pernah berubah sepanjang episode.
  `install_date`        date         NOT NULL,
  `install_place_id`    varchar(20)  NOT NULL COMMENT 'omnicare place_id (29957..30941). "Cabang asal" di brief konsultan',
  `bracket_type`        varchar(60)  DEFAULT NULL COMMENT 'Metal / Ceramic / Sapphire / Self Ligating / Damon Quantum',
  `bracket_arch`        enum('atas','bawah','keduanya') DEFAULT NULL,
  `doctor_ref_id`       varchar(20)  DEFAULT NULL COMMENT 'omnicare doc_reff_id. JANGAN cocokkan dokter lewat nama',
  `doctor_name`         varchar(255) DEFAULT NULL COMMENT 'Snapshot untuk tampilan',

  -- ── Status episode. BUKAN berbasis waktu: pasien tetap aktif sampai bracket
  --    dilepas. Lama tak kontrol memicu PERINGATAN, bukan mengubah status.
  `status`              enum('aktif','selesai','putus','pindah_klinik') NOT NULL DEFAULT 'aktif',
  `finished_at`         date         DEFAULT NULL,

  -- ── ROLLUP — dihitung ulang tiap tulis ke ort_control. Lihat catatan di atas.
  `owner_place_id`      varchar(20)  NOT NULL COMMENT 'Cabang penagih = place_id kontrol terakhir. Inilah yang memindahkan pasien antar tab cabang',
  `open_control_id`     char(36)     DEFAULT NULL COMMENT 'NULL = belum punya jadwal. Kunci antrean penjadwalan tanpa anti-join',
  `next_sch_date`       date         DEFAULT NULL,
  `next_is_proposal`    tinyint(1)   NOT NULL DEFAULT 0 COMMENT '1 = usulan sistem, belum dikonfirmasi manusia',
  `last_visit_date`     date         DEFAULT NULL,
  `last_visit_place_id` varchar(20)  DEFAULT NULL,
  `control_count`       smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `visit_count`         smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `visit_count_180d`    tinyint(3)  UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Kunjungan 180 hari terakhir. <2 = "Jarang kontrol" (definisi Sales). BASI SENDIRINYA karena waktu berjalan — wajib disegarkan tiap tarikan malam',
  `avg_gap_days`        smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Jarak rata-rata antar kunjungan. Target 30 hari',
  `branches_visited`    varchar(120) DEFAULT NULL COMMENT 'CSV place_id terurut — pil cabang di kartu, tanpa N+1',
  `rollup_at`           timestamp    NULL DEFAULT NULL,

  -- ── Jejak
  `seed_source`         varchar(40)  DEFAULT NULL COMMENT 'rekam_medis | manual',
  `note`                text         DEFAULT NULL,
  `created_at`          timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`          timestamp    NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `created_by`          varchar(255) DEFAULT NULL,
  `updated_by`          varchar(255) DEFAULT NULL,

  PRIMARY KEY (`id`),

  -- Idempotensi penyemai. Episode kedua (lepas lalu pasang lagi) punya
  -- install_date berbeda, jadi UNIQUE ini tidak menghalanginya.
  -- ⚠ MySQL mengizinkan duplikat bila omnicare_pid NULL → penyemai TETAP wajib
  --   memeriksa keberadaan lebih dulu untuk pasien yang belum tertaut.
  -- Leftmost-nya (omnicare_pid) sekaligus melayani pencocok tarikan malam.
  UNIQUE KEY `uq_ort_case_pid` (`omnicare_pid`, `install_date`),

  -- Kartu + antrean "Belum punya jadwal". Urutannya kesamaan → kesamaan →
  -- kesamaan(IS NULL) → pengurutan.
  KEY `idx_ort_case_backlog`  (`status`, `owner_place_id`, `open_control_id`, `last_visit_date`),
  KEY `idx_ort_case_roster`   (`status`, `owner_place_id`, `next_sch_date`),
  -- Tab cabang lain: pasien yang cabang pasangnya kita, tapi penagihnya bukan.
  KEY `idx_ort_case_origin`   (`status`, `install_place_id`, `owner_place_id`),
  KEY `idx_ort_case_rare`     (`status`, `owner_place_id`, `visit_count_180d`),
  KEY `idx_ort_case_customer` (`raw_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracking Ortho: satu baris = satu episode perawatan behel';


-- ════════════════════════════════════════════════════════════════════════════
-- 2. ort_control — satu baris = satu jadwal kontrol
-- ════════════════════════════════════════════════════════════════════════════
-- ATURAN DOMAIN YANG MENYEDERHANAKAN SEGALANYA:
--   satu kasus punya PALING BANYAK SATU kontrol terbuka (closed_at IS NULL).
-- Artinya pola "ambil baris terakhir per grup" — yang biasanya menuntut window
-- function atau derived table — TIDAK PERNAH dibutuhkan di modul ini. Itu yang
-- membuatnya tetap cepat di MySQL 5.7 (lokal) maupun MariaDB 11 (prod).
--
-- Kontrol lama hasil PENYEMAIAN juga masuk ke tabel ini, bukan sebagai satu
-- baris jangkar: sch_date = visit_date, sch_source='rekam_medis', closed_at
-- terisi. Itu yang membuat matriks di drawer dan hitungan 6 bulan sama-sama
-- benar tanpa menyentuh cermin rekam medis saat render.
--
-- Kunjungan yang datang tanpa jadwal terbuka (walk-in) juga dicatat di sini:
-- kontrol baru yang langsung tertutup, unscheduled=1. Riwayat tidak pernah
-- bolong.
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ort_control` (
  `id`                    char(36)     NOT NULL,
  `ort_case_id`           char(36)     NOT NULL,
  `seq`                   smallint(5) UNSIGNED NOT NULL COMMENT 'Nomor "#" di layar, per kasus',
  `place_id`              varchar(20)  NOT NULL COMMENT 'Cabang pemilik kontrol INI. Perubahan kolom ini ADALAH transfernya',
  `is_proposal`           tinyint(1)   NOT NULL DEFAULT 0 COMMENT '1 = usulan sistem, tanggal bergaris putus-putus, menunggu Konfirmasi',

  -- ── Jadwal (SCH)
  `sch_date`              date         NOT NULL,
  `sch_source`            enum('manual','sistem','rekam_medis') NOT NULL DEFAULT 'manual'
                          COMMENT 'manual=diketik Sales · sistem=usulan +30 hari · rekam_medis=hasil penyemaian',
  `sch_note`              varchar(255) DEFAULT NULL,

  -- ── Follow-up (FU). Catatan, bukan pengirim pesan.
  `fu_date`               date         DEFAULT NULL,
  `fu_channel`            enum('wa','telepon','langsung','lainnya') DEFAULT NULL,
  `fu_by`                 varchar(255) DEFAULT NULL,

  -- ── Reservasi (RSV). HANYA penunjuk + snapshot.
  --    Sumber kebenaran tetap raw_cus_reservation: jam, dokter, layanan, dan
  --    aturan anti-bentrok semuanya milik modul Reservasi. Snapshot di sini
  --    supaya halaman daftar tidak perlu menyentuh tabel itu; halaman DETAIL
  --    menyegarkannya lewat satu whereIn(), bukan join.
  `reservation_id`        varchar(255) DEFAULT NULL COMMENT 'FK app-level ke raw_cus_reservation.id',
  `rsv_date`              date         DEFAULT NULL COMMENT 'Snapshot. NULL padahal reservation_id terisi = waitlist (date memang nullable di sana)',
  `rsv_status`            varchar(20)  DEFAULT NULL COMMENT 'Snapshot: pending|confirmed|waitlist|completed|cancelled',
  `rsv_synced_at`         timestamp    NULL DEFAULT NULL,

  -- ── Kunjungan (DATANG). DIISI MESIN — Sales tidak punya tombolnya.
  `visit_date`            date         DEFAULT NULL,
  `visit_source`          enum('rekam_medis','manual') NOT NULL DEFAULT 'rekam_medis',
  `visit_confidence`      enum('pasti','dugaan') DEFAULT NULL COMMENT 'dugaan = selisih tanggal >45 hari, minta konfirmasi Sales, jangan tutup diam-diam',
  `omnicare_trx_id`       varchar(40)  DEFAULT NULL COMMENT 'Bukti baris omnicare_sales_detail asal',
  `omnicare_encounter_id` varchar(40)  DEFAULT NULL,
  `visit_filled_at`       timestamp    NULL DEFAULT NULL COMMENT 'Kapan tarikan malam mengisinya — dipakai tooltip di drawer',
  `unscheduled`           tinyint(1)   NOT NULL DEFAULT 0 COMMENT '1 = "Datang tanpa reservasi". Keterangan, bukan kesalahan',

  -- ── Penutupan.
  --    outcome='hadir' boleh ditulis mesin (fakta, ada transaksinya).
  --    'tidak_hadir' SELALU manusia — ketidakhadiran itu KESIMPULAN dari
  --    ketiadaan, dan kalau mesin menutupnya, pasien terlantar otomatis dapat
  --    tanggal baru lalu keluar dari kartu Terlewat. Jejak penelantaran
  --    terhapus — persis yang modul ini dibangun untuk menampakkannya.
  `outcome`               enum('hadir','tidak_hadir','dijadwal_ulang','batal') DEFAULT NULL,
  `closed_at`             timestamp    NULL DEFAULT NULL COMMENT 'NULL = masih terbuka',
  `closed_by`             varchar(255) DEFAULT NULL,

  `created_at`            timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`            timestamp    NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `created_by`            varchar(255) DEFAULT NULL,
  `updated_by`            varchar(255) DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ort_control_seq` (`ort_case_id`, `seq`),

  -- PAGAR ANTI-DOBEL TARIKAN MALAM. Satu transaksi rekam medis hanya boleh
  -- dikonsumsi sekali. MySQL mengizinkan banyak NULL pada UNIQUE, jadi baris
  -- yang belum punya kunjungan tidak saling mengganggu.
  UNIQUE KEY `uq_ort_control_trx` (`omnicare_trx_id`),

  -- ⭐ PENGGERAK SELURUH TAB LIST. Urutannya kesamaan → kesamaan(IS NULL) →
  --    rentang/pengurutan. Kolom rentang WAJIB paling kanan: kalau sch_date
  --    ditaruh sebelum closed_at, index berhenti dipakai setelah kolom rentang
  --    dan ORDER BY jatuh ke filesort.
  --      Semua       : place_id=? AND closed_at IS NULL            ORDER BY sch_date
  --      Terlewat    : ... AND sch_date < CURDATE()
  --      Hari/Minggu : ... AND sch_date BETWEEN ? AND ?
  KEY `idx_ort_control_worklist` (`place_id`, `closed_at`, `sch_date`),

  KEY `idx_ort_control_case`  (`ort_case_id`, `seq`),
  KEY `idx_ort_control_rsv`   (`reservation_id`) COMMENT 'Observer reservasi mencari baris ortho-nya lewat sini',
  KEY `idx_ort_control_visit` (`ort_case_id`, `visit_date`) COMMENT 'Hitung ulang visit_count_180d'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracking Ortho: satu baris = satu jadwal kontrol';


-- ════════════════════════════════════════════════════════════════════════════
-- 3. ort_transfer — satu baris = satu kejadian pindah cabang
-- ════════════════════════════════════════════════════════════════════════════
-- Transfer DICATAT, bukan disimpulkan dari membandingkan cabang dua kontrol
-- berurutan. Tanpa tabel ini, tiga hal tidak punya tempat: alasan, transfer
-- sementara, dan hitungan "bulan ini" yang tidak rapuh.
--
-- `reason` wajib. Brief konsultan menyisakan satu teka-teki yang hanya bisa
-- dijawab kalau alasannya tercatat (lubang 6.8): sheet Kalideres menandai 46
-- "Pindah Kontrol" tapi tak satu pun pasiennya berkontrol terakhir di cabang
-- lain — kumulatif bertahun-tahun, atau "pindah" di sana berarti keluar dari
-- klinik? Tidak akan pernah terjawab tanpa kolom ini.
--
-- `is_temporary` mewujudkan kalimat Anisa sendiri: "…berarti bintaro yang
-- harus menghubunginya, KECUALI pasien itu mau balik lagi ke cinere."
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ort_transfer` (
  `id`                      char(36)     NOT NULL,
  `ort_case_id`             char(36)     NOT NULL,
  `ort_control_id`          char(36)     DEFAULT NULL COMMENT 'Kontrol yang memicu, kalau ada',

  `from_place_id`           varchar(20)  NOT NULL,
  `to_place_id`             varchar(20)  NOT NULL,
  `transfer_date`           date         NOT NULL COMMENT 'Tanggal kesepakatan pindah — beda dari tanggal kontrol',

  `reason`                  enum('pasien_minta','dokter_pindah','lebih_dekat','pindah_domisili','lainnya') NOT NULL,
  `reason_note`             varchar(255) DEFAULT NULL COMMENT 'Wajib diisi kalau reason=lainnya',

  `is_temporary`            tinyint(1)   NOT NULL DEFAULT 0 COMMENT '1 = "Kontrol sekali saja di sana, setelah itu kembali"',
  `return_after_control_id` char(36)     DEFAULT NULL COMMENT 'Kepemilikan kembali otomatis setelah kontrol ini ditutup',
  `returned_at`             timestamp    NULL DEFAULT NULL,

  `detected`                enum('manual','rekam_medis') NOT NULL DEFAULT 'manual'
                            COMMENT 'manual=lewat lembar Pindahkan (RENCANA) · rekam_medis=pasien muncul di cabang lain, ketahuan tarikan malam (KENYATAAN)',

  `created_at`              timestamp    NOT NULL DEFAULT current_timestamp(),
  `created_by`              varchar(255) DEFAULT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_ort_transfer_case` (`ort_case_id`, `transfer_date`),
  KEY `idx_ort_transfer_to`   (`to_place_id`, `transfer_date`),
  KEY `idx_ort_transfer_from` (`from_place_id`, `transfer_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracking Ortho: kejadian pindah cabang, dengan alasan';


-- ════════════════════════════════════════════════════════════════════════════
-- 4. ort_reminder — pengingat ke admin cabang lain
-- ════════════════════════════════════════════════════════════════════════════
-- Tabel sendiri, bukan kolom last_reminded_at di ort_case, supaya riwayatnya
-- utuh dan bisa dihitung ("cabang mana yang paling sering harus diingatkan").
--
-- Chip "Diingatkan 18 Jan" membaca yang TERAKHIR. Jangan pakai window function
-- (tidak ada di MySQL 5.7) dan jangan derived table (tidak ter-index): ambil
-- halamannya dulu (≤10 baris), lalu satu kueri
--   WHERE ort_case_id IN (:ids) ORDER BY reminded_at DESC
-- dan reduksi di PHP.
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ort_reminder` (
  `id`            char(36)     NOT NULL,
  `ort_case_id`   char(36)     NOT NULL,
  `from_place_id` varchar(20)  NOT NULL COMMENT 'Cabang yang mengingatkan',
  `to_place_id`   varchar(20)  NOT NULL COMMENT 'Cabang yang diingatkan',
  `reminded_at`   timestamp    NOT NULL DEFAULT current_timestamp(),
  `reminded_by`   varchar(255) DEFAULT NULL,
  `channel`       enum('wa','telepon','lainnya') NOT NULL DEFAULT 'wa',
  `note`          varchar(255) DEFAULT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_ort_reminder_case`  (`ort_case_id`, `reminded_at`),
  KEY `idx_ort_reminder_place` (`to_place_id`, `reminded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracking Ortho: pengingat ke admin cabang lain';


-- ════════════════════════════════════════════════════════════════════════════
-- VERIFIKASI — jangan dilewati
-- ════════════════════════════════════════════════════════════════════════════
-- 1. Jalankan berkas ini DUA KALI. Keduanya harus lolos tanpa error.
-- 2. Nol FOREIGN KEY:
--      SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
--       WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'
--         AND TABLE_NAME LIKE 'ort\_%';
--      → harus KOSONG.
-- 3. Collation seragam:
--      SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES
--       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'ort\_%';
--      → keempatnya utf8mb4_unicode_ci.
-- 4. Index terpasang lengkap:
--      SHOW INDEX FROM `ort_control`;  -- cari idx_ort_control_worklist
--      SHOW INDEX FROM `ort_case`;     -- cari idx_ort_case_backlog
-- ════════════════════════════════════════════════════════════════════════════
SELECT t.TABLE_NAME                                   AS tabel,
       t.TABLE_COLLATION                              AS collation,
       (SELECT COUNT(*) FROM information_schema.STATISTICS s
         WHERE s.TABLE_SCHEMA = t.TABLE_SCHEMA
           AND s.TABLE_NAME   = t.TABLE_NAME
         GROUP BY s.TABLE_NAME)                       AS kolom_terindeks
  FROM information_schema.TABLES t
 WHERE t.TABLE_SCHEMA = DATABASE()
   AND t.TABLE_NAME IN ('ort_case','ort_control','ort_transfer','ort_reminder')
 ORDER BY t.TABLE_NAME;
