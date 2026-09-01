-- ════════════════════════════════════════════════════════════════════════════
-- Index pelaporan untuk halaman Komisi Dokter — 2026-08-28
-- IDEMPOTENT: aman dijalankan berapa kali pun.
-- Jalankan di DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod).
-- ════════════════════════════════════════════════════════════════════════════
-- Kenapa perlu:
--   Index bawaan `idx_omnicare_comm_place_date` leftmost-nya `place_id`. Halaman
--   Komisi Dokter defaultnya "Semua Cabang" → place_id TIDAK difilter → index itu
--   tidak terpakai sama sekali dan query jatuh ke full scan (±80.911 baris di prod).
--
--   idx_comm_role_date : semua query halaman ini selalu diawali
--                        `commission_role='doctor' AND trx_date BETWEEN …`
--   idx_comm_doc_date  : layar detail & tab Rincian selalu
--                        `doc_id = ? AND trx_date BETWEEN …`
--                        (index bawaan `idx_omnicare_comm_doc` hanya doc_id,
--                         rentang tanggalnya masih harus di-filter baris per baris)
-- ════════════════════════════════════════════════════════════════════════════

-- Tabel belum ada → berhenti sopan, jangan bikin error merah.
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'omnicare_commission'
);

-- 1) (commission_role, trx_date)
SET @idx1 := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'omnicare_commission'
      AND INDEX_NAME   = 'idx_comm_role_date'
);

SET @ddl1 := IF(@tbl_exists = 1 AND @idx1 = 0,
    'CREATE INDEX `idx_comm_role_date` ON `omnicare_commission` (`commission_role`, `trx_date`)',
    'SELECT ''idx_comm_role_date — skip (tabel belum ada / index sudah ada).'' AS info'
);

PREPARE stmt FROM @ddl1;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) (doc_id, trx_date)
SET @idx2 := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'omnicare_commission'
      AND INDEX_NAME   = 'idx_comm_doc_date'
);

SET @ddl2 := IF(@tbl_exists = 1 AND @idx2 = 0,
    'CREATE INDEX `idx_comm_doc_date` ON `omnicare_commission` (`doc_id`, `trx_date`)',
    'SELECT ''idx_comm_doc_date — skip (tabel belum ada / index sudah ada).'' AS info'
);

PREPARE stmt FROM @ddl2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
