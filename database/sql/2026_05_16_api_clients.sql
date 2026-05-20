-- =====================================================================
-- API Clients — Kredensial Vendor Open API — 2026-05-16
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan: 1 baris = 1 vendor per environment. Dipakai middleware
-- VerifyApiClientToken untuk auth semua endpoint apicekat/apicekatdev.
-- Vendor-agnostic: tambah vendor baru (3-5 ke depan) cukup tambah baris
-- via command `php artisan apiclient:issue <Nama> <env>` — endpoint &
-- middleware TIDAK perlu diubah.
--
--  - token_hash = hash('sha256', token_plaintext). Plaintext TIDAK disimpan.
--  - is_active = 0  -> blokir vendor itu seketika (vendor lain tak terganggu).
--  - environment   -> 'live' hanya cocok di apicekat/*, 'sandbox' di apicekatdev/*.
--    Mengunci "mode auth = mode data": token sandbox tak bisa sentuh data asli.
--
-- Tipe & charset disamakan dengan pola tabel existing (utf8mb4_unicode_ci).
-- =====================================================================

CREATE TABLE `api_clients` (
  `id`           varchar(36)  NOT NULL,
  `name`         varchar(100) NOT NULL COMMENT 'Nama vendor, mis. Cekat',
  `token_hash`   char(64)     NOT NULL COMMENT "hash('sha256', token); plaintext TIDAK disimpan",
  `environment`  enum('live','sandbox') NOT NULL,
  `is_active`    tinyint(1)   NOT NULL DEFAULT 1 COMMENT '0 = blokir vendor seketika',
  `last_used_at` timestamp    NULL DEFAULT NULL,
  `created_at`   timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_api_token` (`token_hash`),
  KEY `idx_active_env` (`is_active`, `environment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Setelah tabel dibuat, terbitkan token (JANGAN insert manual — pakai command
-- supaya token di-hash & plaintext tampil sekali):
--   php artisan apiclient:issue Cekat sandbox
--   php artisan apiclient:issue Cekat live
-- =====================================================================
