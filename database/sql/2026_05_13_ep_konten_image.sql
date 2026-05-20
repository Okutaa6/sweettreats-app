-- ─────────────────────────────────────────────────────────────────────
-- Migration: ep_konten_image
-- Purpose : Foto/gambar yang di-attach ke record ep_konten + workflow review
-- Created : 2026-05-13
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ep_konten_image` (
    `id`              VARCHAR(36)  NOT NULL PRIMARY KEY,
    `ep_konten_id`    VARCHAR(36)  NOT NULL,
    `path_original`   VARCHAR(255) NOT NULL,
    `path_large`      VARCHAR(255) NOT NULL,
    `path_thumb`      VARCHAR(255) NOT NULL,
    `original_name`   VARCHAR(255) NOT NULL,
    `mime_type`       VARCHAR(50)  NOT NULL,
    `size_bytes`      INT UNSIGNED NOT NULL,
    `width`           SMALLINT UNSIGNED NULL,
    `height`          SMALLINT UNSIGNED NULL,
    `sort_order`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `label`           VARCHAR(100) NULL,
    `review_status`   ENUM('pending','approved','revisi') NOT NULL DEFAULT 'pending',
    `review_note`     TEXT NULL,
    `reviewed_by`     VARCHAR(36) NULL,
    `reviewed_at`     DATETIME NULL,
    `created_by`      VARCHAR(36) NULL,
    `created_at`      DATETIME NOT NULL,
    INDEX `idx_eki_konten`  (`ep_konten_id`),
    INDEX `idx_eki_status`  (`review_status`),
    CONSTRAINT `fk_eki_konten` FOREIGN KEY (`ep_konten_id`)
        REFERENCES `ep_konten` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    