-- ═══════════════════════════════════════════════════════════════════════════
-- Flag baru `pulang_cepat` — pulang lebih awal dari jam pulang aturan kerja
--
-- Sebab B diagnosis 6 Sep 2026: tombol absen langsung jadi "Pulang" begitu
-- masuk tercatat; ketukan kedua = pulang; pulang sungguhan sore harinya
-- ditolak. Perbaikannya TIDAK memblokir (keputusan Ekky): dua peringatan yang
-- bisa dilanjut. Yang kedua — "jadwalmu sampai 17.00, ini pulang cepat 2 jam"
-- — tetap dicatat, tapi ditandai sebagai flag supaya terlihat di Pengawasan.
--
-- Hanya aturan BAKU yang bisa pulang cepat; fleksibel tak punya jam pulang.
--
-- Pola idempoten sama dengan 2026_08_13_aturan_jadwal_dokter.sql: enum di-set
-- ke bentuk akhirnya, dilewati kalau sudah memuat nilainya.
-- ═══════════════════════════════════════════════════════════════════════════
SET @jenis := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'att_flag' AND COLUMN_NAME = 'jenis');
SET @sql := IF(@jenis IS NOT NULL AND LOCATE('pulang_cepat', @jenis) = 0,
  "ALTER TABLE `att_flag`
     MODIFY `jenis` enum('ip_asing','perangkat_ganda','pola_ip','luar_seharian',
                         'jam_janggal','single_scan','perangkat_desktop',
                         'cabang_beda_jadwal','pulang_cepat') NOT NULL",
  "SELECT 'att_flag.jenis sudah punya pulang_cepat (atau tabel tak ada), dilewati' AS info");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
