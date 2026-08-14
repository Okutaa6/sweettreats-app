-- ════════════════════════════════════════════════════════════════════════════
-- Lembar Bayar ROP — Finance boleh MELENGKAPI tujuan transfer
-- ════════════════════════════════════════════════════════════════════════════
-- Masalahnya nyata di prod (dicek 14 Agu 2026): dari 7 pengajuan yang menunggu,
-- 5 tujuan transfernya sudah memuat nomor rekening ("VA Shopee 600 0821…",
-- "BCA AN HENG SUI PHIN - 865…") tapi 2 cuma nama ("TRIYANI ( MAS ENDRIK)",
-- "Ekky"). Baris tanpa nomor rekening membuat bos menerima lembar yang tak bisa
-- dieksekusi, lalu bertanya balik — persis kerja bolak-balik yang mau dihapus
-- fitur ini.
--
-- Kenapa kolomnya di SINI, bukan di rop_requests:
--   • Pengajuan TIDAK BOLEH diedit setelah dikirim — itu aturan audit bersih
--     yang sudah berlaku (lihat komentar 2026_07_31_rop_core.sql). Menambal
--     pay_to_text berarti mengubah apa yang ditulis pengaju, dan jejaknya jadi
--     berbohong.
--   • Lembar bayar itu dokumen FINANCE. Melengkapi tujuan transfer di lembar
--     adalah tindakan Finance atas dokumennya sendiri — tidak menyentuh
--     pengajuan orang lain.
--   • Per-item, bukan per-ROP: satu ROP bisa masuk beberapa lembar, dan tiap
--     lembar adalah dokumen tersendiri yang sudah/belum beredar.
--
-- NULL = pakai `rop_requests.pay_to_text` apa adanya. Kolom ini PELENGKAP,
-- bukan pengganti: kalau pengaju sudah menulis lengkap, Finance tidak perlu
-- menyentuh apa pun.
--
-- Idempotent: ADD COLUMN IF NOT EXISTS (MariaDB 11.8 di dev & prod).
-- ════════════════════════════════════════════════════════════════════════════

ALTER TABLE `rop_payment_sheet_items`
  ADD COLUMN IF NOT EXISTS `tujuan_transfer` varchar(191) DEFAULT NULL
    COMMENT 'Tujuan transfer versi Finance untuk lembar ini. NULL = pakai rop_requests.pay_to_text apa adanya. Diisi saat pengaju menulis penerima tanpa nomor rekening.'
    AFTER `amount`;

ALTER TABLE `rop_payment_sheet_items`
  ADD COLUMN IF NOT EXISTS `tujuan_oleh` varchar(255) DEFAULT NULL
    COMMENT 'FK users.id — siapa yang melengkapi tujuan transfer; jejak supaya jelas ini tulisan Finance, bukan tulisan pengaju'
    AFTER `tujuan_transfer`;

-- FK ditambah terpisah & dijaga: MariaDB tak punya ADD CONSTRAINT IF NOT EXISTS,
-- jadi dicek dulu ke information_schema supaya berkas ini aman dijalankan dua kali.
SET @ada_fk := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE()
    AND table_name = 'rop_payment_sheet_items'
    AND constraint_name = 'fk_item_tujuan_oleh'
);
SET @sql := IF(@ada_fk = 0,
  'ALTER TABLE `rop_payment_sheet_items`
     ADD CONSTRAINT `fk_item_tujuan_oleh` FOREIGN KEY (`tujuan_oleh`)
     REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT "fk_item_tujuan_oleh sudah ada, dilewati" AS catatan'
);
PREPARE p FROM @sql;
EXECUTE p;
DEALLOCATE PREPARE p;
