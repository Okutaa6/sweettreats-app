-- ════════════════════════════════════════════════════════════════════════════
-- Lembar Bayar ROP — rekap pembayaran yang dikirim Finance ke bos (satu PDF)
-- ════════════════════════════════════════════════════════════════════════════
-- Konteks penuh: docs/rencana/rencana-lembar-bayar-rop.md (§4.1 data model).
--
-- Masalahnya: Finance mengirim ROP ke bos SATU PER SATU lewat WhatsApp — satu
-- berkas ROP + satu invoice per item, 15 pembayaran = 30 berkas. Lembar bayar
-- menggantinya dengan satu berkas cetak: daftar + total + terbilang + lampiran.
--
-- Dua tabel:
--   1. rop_payment_sheets      — lembarnya sendiri (nomor, total, kapan dikirim)
--   2. rop_payment_sheet_items — item di dalamnya (pivot ke rop_requests)
--
-- Keputusan desain (jangan diubah tanpa baca rencana §4.1):
--   • PIVOT, bukan satu kolom FK di rop_requests. Dari 15 item, bos sering
--     membayar 12 — tiga sisanya masuk lembar BERIKUTNYA, dan riwayat "pernah
--     ada di lembar 1" harus tetap terbaca. Kolom FK tunggal akan menimpanya.
--     Karena itu TIDAK ada UNIQUE pada rop_id sendirian: satu ROP boleh muncul
--     di beberapa lembar. Yang dilarang cuma muncul DUA KALI di lembar yang
--     sama (uq_sheet_item).
--   • NOMINAL DI-SNAPSHOT (sheet_items.amount + sheets.total/jumlah_item).
--     Lembar yang sudah dicetak dan dikirim ke bos tidak boleh berubah angkanya
--     kalau ROP-nya kelak diedit atau dibatalkan. Yang tercetak = yang tersimpan.
--   • Nomor lembar `BAYAR/{YYMM}/{NNN}` memakai `rop_counter` yang SUDAH ADA
--     dengan kunci cost_centre = 'BAYAR' (rop_counter tidak punya FK ke
--     rop_cost_centre, jadi kunci semu ini sah dan tidak butuh baris master
--     baru). Mekanisme atomiknya dipakai ulang lewat
--     RopService::nomorLembarBayar() — JANGAN menyalin SQL LAST_INSERT_ID-nya,
--     dan JANGAN `MAX(number)+1` yang balapan kalau dua orang menekan barengan.
--   • `dikirim_at` NULL = lembar sudah dibuat tapi belum dikirim ke bos. Dicap
--     saat Finance menekan "sudah dikirim" — bukan otomatis saat dicetak,
--     karena mencetak dan mengirim itu dua kejadian berbeda dan yang perlu
--     dilacak adalah yang kedua.
--   • Tidak ada `deleted_at`: lembar yang sudah dikirim ke bos adalah dokumen
--     yang beredar di luar sistem, jadi tidak boleh bisa dihapus diam-diam.
--
-- Collation utf8mb4_unicode_ci — samain `users`/`rop_requests` (JOIN tanpa
-- "illegal mix of collations"). Idempotent: CREATE TABLE IF NOT EXISTS, aman
-- dijalankan dua kali.
-- ════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. rop_payment_sheets — satu baris = satu rekap yang dikirim ke bos.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rop_payment_sheets` (
  `id`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `number`      varchar(32)  NOT NULL             COMMENT 'BAYAR/{YYMM}/{NNN} — mis. BAYAR/2608/001; reset tiap bulan',
  `dibuat_oleh` varchar(255) DEFAULT NULL         COMMENT 'FK users.id — Finance yang menyusun lembar; SET NULL kalau user dihapus, riwayat lembar tetap',
  `dibuat_at`   datetime     NOT NULL             COMMENT 'kapan lembar disusun — tanggal yang tercetak di kepala lembar',
  `dikirim_at`  datetime     DEFAULT NULL         COMMENT 'NULL = belum dikirim ke bos; dicap manual, bukan otomatis saat dicetak',
  `dikirim_oleh` varchar(255) DEFAULT NULL        COMMENT 'FK users.id — siapa yang menyatakan sudah dikirim',
  `jumlah_item` smallint(6)  NOT NULL DEFAULT 0   COMMENT 'SNAPSHOT jumlah baris saat lembar dibuat',
  `total`       decimal(15,2) NOT NULL DEFAULT 0  COMMENT 'SNAPSHOT jumlah rupiah saat lembar dibuat — yang tercetak & terbilang',
  `catatan`     varchar(500) DEFAULT NULL         COMMENT 'catatan bebas Finance untuk bos, ikut tercetak',
  `updated_at`  timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sheet_number` (`number`),
  KEY `idx_sheet_dibuat` (`dibuat_at`),
  KEY `idx_sheet_kirim` (`dikirim_at`),
  CONSTRAINT `fk_sheet_pembuat` FOREIGN KEY (`dibuat_oleh`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sheet_pengirim` FOREIGN KEY (`dikirim_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. rop_payment_sheet_items — isi lembar.
--    ON DELETE CASCADE ke sheet (isi tanpa lembar tidak punya arti);
--    RESTRICT ke rop_requests DIHINDARI dengan sengaja — rop_requests dihapus
--    hanya lewat soft delete, jadi CASCADE di sini tidak akan terpicu praktiknya
--    dan tetap membersihkan diri kalau kelak ada hard delete.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rop_payment_sheet_items` (
  `id`         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sheet_id`   bigint(20) UNSIGNED NOT NULL,
  `rop_id`     bigint(20) UNSIGNED NOT NULL,
  `amount`     decimal(15,2) NOT NULL            COMMENT 'SNAPSHOT nominal ROP saat lembar dibuat — bukan dibaca ulang dari rop_requests',
  `urutan`     smallint(6)  NOT NULL DEFAULT 0   COMMENT 'urutan baris di lembar cetak (ikut urutan antrian saat dibuat)',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- Satu ROP tidak boleh dobel DI DALAM satu lembar. Tapi boleh ada di lembar
  -- lain — lihat catatan pivot di kepala berkas ini.
  UNIQUE KEY `uq_sheet_item` (`sheet_id`, `rop_id`),
  KEY `idx_item_rop` (`rop_id`),
  CONSTRAINT `fk_item_sheet` FOREIGN KEY (`sheet_id`) REFERENCES `rop_payment_sheets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_rop`   FOREIGN KEY (`rop_id`)   REFERENCES `rop_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
