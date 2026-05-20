-- =====================================================================
-- KOL Detail Extension (Fase 2E) — 2026-05-02
-- IDEMPOTENT — aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   Tambah field-field khusus KOL — followers, rate card, kategori, progress
--   approach, status relasi, contact person, tanggal approach.
--   Field `feedback` juga useful untuk KOC (bisa dipakai dua-duanya).
-- =====================================================================

-- ── kol_kategori ──────────────────────────────────────────────────────
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raw_affiliate' AND COLUMN_NAME = 'kol_kategori');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate` ADD COLUMN `kol_kategori` enum(
        'komika_standup',
        'musisi_penyanyi',
        'influencer',
        'content_creator',
        'athlete',
        'aktris',
        'aktor',
        'moms_content_creator'
    ) DEFAULT NULL COMMENT 'Kategori KOL (hanya relevant kalau type=kol)' AFTER `behel_type`",
    "SELECT 'raw_affiliate.kol_kategori already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── kol_progress ──────────────────────────────────────────────────────
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raw_affiliate' AND COLUMN_NAME = 'kol_progress');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate` ADD COLUMN `kol_progress` enum(
        'akan_di_approach',
        'waiting_confirmation',
        'no_respon',
        'confirm',
        'done'
    ) DEFAULT NULL COMMENT 'Progress approach KOL' AFTER `kol_kategori`",
    "SELECT 'raw_affiliate.kol_progress already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── kol_status_relasi ─────────────────────────────────────────────────
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raw_affiliate' AND COLUMN_NAME = 'kol_status_relasi');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate` ADD COLUMN `kol_status_relasi` enum(
        'baru',
        'pernah_kerja_sama',
        'rekomendasi'
    ) DEFAULT NULL COMMENT 'Status relasi KOL: baru kenal, pernah kerja sama, atau rekomendasi' AFTER `kol_progress`",
    "SELECT 'raw_affiliate.kol_status_relasi already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── followers_count ───────────────────────────────────────────────────
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raw_affiliate' AND COLUMN_NAME = 'followers_count');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate` ADD COLUMN `followers_count` int(11) DEFAULT NULL COMMENT 'Jumlah followers KOL di platform utama' AFTER `kol_status_relasi`",
    "SELECT 'raw_affiliate.followers_count already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── rate_card ─────────────────────────────────────────────────────────
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raw_affiliate' AND COLUMN_NAME = 'rate_card');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate` ADD COLUMN `rate_card` varchar(255) DEFAULT NULL COMMENT 'Rate card KOL — bisa nominal (Rp 5.000.000) atau range (5-10jt)' AFTER `followers_count`",
    "SELECT 'raw_affiliate.rate_card already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── contact_person ────────────────────────────────────────────────────
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raw_affiliate' AND COLUMN_NAME = 'contact_person');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate` ADD COLUMN `contact_person` varchar(255) DEFAULT NULL COMMENT 'Manager/agency contact untuk KOL' AFTER `rate_card`",
    "SELECT 'raw_affiliate.contact_person already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── tanggal_approach ──────────────────────────────────────────────────
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raw_affiliate' AND COLUMN_NAME = 'tanggal_approach');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate` ADD COLUMN `tanggal_approach` date DEFAULT NULL COMMENT 'Kapan pertama kali approach KOL' AFTER `contact_person`",
    "SELECT 'raw_affiliate.tanggal_approach already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── feedback (juga berguna untuk KOC) ─────────────────────────────────
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raw_affiliate' AND COLUMN_NAME = 'feedback');
SET @sql := IF(@exist = 0,
    "ALTER TABLE `raw_affiliate` ADD COLUMN `feedback` text DEFAULT NULL COMMENT 'Catatan internal admin tentang affiliate (kualitas konten, response, dll)' AFTER `tanggal_approach`",
    "SELECT 'raw_affiliate.feedback already exists, skipping' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
