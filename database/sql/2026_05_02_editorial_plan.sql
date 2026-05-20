-- =====================================================================
-- Editorial Plan (Fase 3A) — 2026-05-02
-- IDEMPOTENT — aman dijalankan berapa kali pun.
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan:
--   3 sub-modul Editorial Plan untuk Creative Marketing:
--   1. EP Bulanan/Mingguan (ep_konten)       — calendar konten harian/mingguan
--   2. Breakdown Konten   (ep_breakdown)      — special content per project (promo/event)
--   3. Campaign Framework (ep_campaign_*)     — gambaran besar campaign per bulan + weekly fokus
--
-- Plus master `ep_konten_pillar` yang extensible (admin/creative bisa tambah pillar baru).
-- =====================================================================

-- ─────────────────────────────────────────────────────────────────
-- MASTER: Konten Pillar (extensible — admin bisa add)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ep_konten_pillar` (
  `id` varchar(36) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL COMMENT 'optional warna pill (mis. #c4683f)',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────
-- CAMPAIGN FRAMEWORK (gambaran besar campaign per bulan)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ep_campaign_framework` (
  `id` varchar(36) NOT NULL,
  `bulan` tinyint(2) NOT NULL COMMENT '1-12',
  `tahun` smallint(4) NOT NULL,
  `campaign_name` varchar(255) NOT NULL,
  `big_idea` text DEFAULT NULL COMMENT 'Konsep besar / tagline campaign',
  `target` text DEFAULT NULL COMMENT 'Target/KPI campaign',
  `status` enum('planning','aktif','selesai') NOT NULL DEFAULT 'planning',
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bulan_tahun` (`tahun`, `bulan`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Per minggu: fokus + deliverables
CREATE TABLE IF NOT EXISTS `ep_campaign_week` (
  `id` varchar(36) NOT NULL,
  `ep_campaign_framework_id` varchar(36) NOT NULL,
  `minggu_ke` tinyint(2) NOT NULL COMMENT '1-5',
  `fokus` varchar(500) DEFAULT NULL COMMENT 'Fokus utama minggu ini',
  `deliverables` text DEFAULT NULL COMMENT 'Apa saja yang harus dideliver minggu ini',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_campaign_week` (`ep_campaign_framework_id`, `minggu_ke`),
  KEY `idx_campaign` (`ep_campaign_framework_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────
-- BREAKDOWN KONTEN (project / event special content)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ep_breakdown` (
  `id` varchar(36) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `framework` varchar(255) DEFAULT NULL,
  `lokasi` varchar(255) DEFAULT NULL,
  `acc_by` varchar(255) DEFAULT NULL COMMENT 'user_id yang ACC',
  `acc_at` timestamp NULL DEFAULT NULL,
  `note` text DEFAULT NULL,
  `tanggal_upload` date DEFAULT NULL,
  `waktu_pengerjaan` varchar(100) DEFAULT NULL COMMENT 'mis. 2 hari, 4 jam',
  `status` enum('draft','riset','progress','revisi','done_revisi','on_hold','selesai') NOT NULL DEFAULT 'draft',
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_tanggal_upload` (`tanggal_upload`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────
-- EP KONTEN (calendar konten — bulanan/mingguan/harian)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ep_konten` (
  `id` varchar(36) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,

  -- Platform (multi-value, JSON array)
  `platforms` json DEFAULT NULL COMMENT 'Array: ig_feeds, ig_reels, tiktok, ig_story',

  -- Tipe konten
  `tipe_konten` enum(
      'paid_ads',
      'video_reels',
      'carousel',
      'single_post',
      'collab_post',
      'story',
      'live'
  ) DEFAULT NULL,

  `status` enum(
      'draft',
      'riset',
      'progress',
      'revisi',
      'done_revisi',
      'on_hold',
      'selesai'
  ) NOT NULL DEFAULT 'draft',

  `prioritas` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',

  -- Text fields creative
  `script` text DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `referensi` text DEFAULT NULL COMMENT 'link / catatan referensi visual/script',
  `framework` varchar(255) DEFAULT NULL,
  `visual_plan` text DEFAULT NULL,
  `note` text DEFAULT NULL,

  -- Schedule
  `tanggal_upload` date DEFAULT NULL,
  `waktu_pengerjaan` varchar(100) DEFAULT NULL COMMENT 'mis. 2 hari, 4 jam',

  -- Optional link ke Breakdown (kalau konten ini bagian dari project khusus)
  `ep_breakdown_id` varchar(36) DEFAULT NULL,

  -- Optional link ke Campaign Framework (kalau konten ini bagian dari campaign besar)
  `ep_campaign_framework_id` varchar(36) DEFAULT NULL,

  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_tanggal_upload` (`tanggal_upload`),
  KEY `idx_status` (`status`),
  KEY `idx_prioritas` (`prioritas`),
  KEY `idx_breakdown` (`ep_breakdown_id`),
  KEY `idx_campaign` (`ep_campaign_framework_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Pivot M:N konten ↔ pillar (1 konten bisa multi-pillar, 1 pillar bisa banyak konten)
CREATE TABLE IF NOT EXISTS `ep_konten_pillar_pivot` (
  `id` varchar(36) NOT NULL,
  `ep_konten_id` varchar(36) NOT NULL,
  `ep_konten_pillar_id` varchar(36) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_konten_pillar` (`ep_konten_id`, `ep_konten_pillar_id`),
  KEY `idx_konten` (`ep_konten_id`),
  KEY `idx_pillar` (`ep_konten_pillar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Pivot M:N breakdown ↔ pillar
CREATE TABLE IF NOT EXISTS `ep_breakdown_pillar_pivot` (
  `id` varchar(36) NOT NULL,
  `ep_breakdown_id` varchar(36) NOT NULL,
  `ep_konten_pillar_id` varchar(36) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_breakdown_pillar` (`ep_breakdown_id`, `ep_konten_pillar_id`),
  KEY `idx_breakdown` (`ep_breakdown_id`),
  KEY `idx_pillar` (`ep_konten_pillar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- SEED: Default Konten Pillars (sesuai daftar dari spec marketing)
-- INSERT IGNORE supaya idempotent — kalau slug sudah ada, skip
-- =====================================================================
INSERT IGNORE INTO `ep_konten_pillar` (`id`, `nama`, `slug`, `sort_order`, `is_active`, `created_at`) VALUES
(UUID(), 'Pic',                       'pic',                        1,  1, NOW()),
(UUID(), 'Testimoni',                 'testimoni',                  2,  1, NOW()),
(UUID(), 'Star',                      'star',                       3,  1, NOW()),
(UUID(), 'Promo',                     'promo',                      4,  1, NOW()),
(UUID(), 'Sult',                      'sult',                       5,  1, NOW()),
(UUID(), 'Fact',                      'fact',                       6,  1, NOW()),
(UUID(), 'Science',                   'science',                    7,  1, NOW()),
(UUID(), 'Niche Konten',              'niche-konten',               8,  1, NOW()),
(UUID(), 'Greetings',                 'greetings',                  9,  1, NOW()),
(UUID(), 'Awareness Beauty',          'awareness-beauty',          10,  1, NOW()),
(UUID(), 'Interest Content',          'interest-content',          11,  1, NOW()),
(UUID(), 'Female',                    'female',                    12,  1, NOW()),
(UUID(), 'Paid Ads',                  'paid-ads',                  13,  1, NOW()),
(UUID(), 'Boost Post Ads',            'boost-post-ads',            14,  1, NOW()),
(UUID(), 'Dark Boost Ads',            'dark-boost-ads',            15,  1, NOW()),
(UUID(), 'Message Conversion Female', 'message-conversion-female', 16,  1, NOW()),
(UUID(), 'Message Conversion Casual', 'message-conversion-casual', 17,  1, NOW()),
(UUID(), 'Engagement',                'engagement',                18,  1, NOW()),
(UUID(), 'Branding',                  'branding',                  19,  1, NOW()),
(UUID(), 'Awareness Content',         'awareness-content',         20,  1, NOW()),
(UUID(), 'Traffic Content',           'traffic-content',           21,  1, NOW()),
(UUID(), 'Live',                      'live',                      22,  1, NOW());
