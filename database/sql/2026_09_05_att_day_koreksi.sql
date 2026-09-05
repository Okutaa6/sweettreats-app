-- ═══════════════════════════════════════════════════════════════════════════
-- Koreksi absen oleh admin — berjejak audit
--
-- Sebab: tak ada satu pun jalan mengubah status att_day hari LAMPAU. Koreksi
-- yang ada cuma untuk CUTI (saldo), dan modal karyawan cuma untuk HARI INI.
-- Akibatnya alpa yang keliru — Melani & Putri, 25 Agu 2026 — hanya bisa
-- dibetulkan dengan menembak basis data langsung, tanpa alasan, tanpa nama
-- pelaku, tanpa jejak. Itu persis bentuk perubahan yang tak bisa diaudit.
--
-- ⚠️ KENAPA TABEL SENDIRI, BUKAN UPDATE att_day
--
-- `att_day` itu TURUNAN: AttendanceReconciler menghitungnya ulang dari scan +
-- aturan, lalu `upsert` menimpa kolom `status`. Koreksi yang ditulis langsung
-- ke sana akan bertahan sampai reconcile berikutnya — penjadwal harian —
-- lalu HILANG tanpa satu pun pesan. Bentuk kegagalan-senyap yang paling mahal:
-- rapi hari ini, salah besok pagi, dan tak ada yang menghubungkan keduanya.
--
-- Jadi koreksi disimpan di sini sebagai DEKLARASI MANUSIA, dan reconciler
-- membacanya sebagai kata terakhir setiap kali menghitung — pola yang sama
-- dengan pengajuan yang di-ACC ("deklarasi manusia menang atas scan", §4).
--
-- ⚠️ BUKU BESAR, BUKAN LEMBAR CORET. Baris tak pernah di-UPDATE atau DIHAPUS.
-- Membatalkan koreksi = mengisi `dicabut_at`; koreksi yang berlaku adalah
-- baris TERBARU yang belum dicabut untuk (user, tanggal). Riwayatnya harus
-- tetap terbaca — ini angka yang menentukan uang.
--
-- Idempoten: aman dijalankan ulang.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `att_day_koreksi` (
  `id`            bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id`       varchar(255) NOT NULL,
  `work_date`     date NOT NULL,

  -- Daftar nilainya SENGAJA sama persis dengan att_day.status. Kalau suatu
  -- saat status baru ditambahkan di sana dan lupa di sini, koreksi ke status
  -- itu ditolak database — berisik, bukan diam. Itu yang diinginkan.
  `status`        enum('hadir','telat','alpa','cuti','izin','sakit','libur','wfh') NOT NULL,

  -- Status sebelum dikoreksi, disalin saat koreksi dibuat. Bukan demi
  -- perhitungan — demi orang yang membacanya enam bulan lagi dan perlu tahu
  -- apa yang sebenarnya berubah tanpa merekonstruksi keadaan lama.
  `status_lama`   varchar(20) DEFAULT NULL,

  `alasan`        varchar(500) NOT NULL,

  `dibuat_oleh`   varchar(255) DEFAULT NULL,
  `dibuat_nama`   varchar(255) DEFAULT NULL,
  `created_at`    datetime NOT NULL,

  `dicabut_at`    datetime DEFAULT NULL,
  `dicabut_oleh`  varchar(255) DEFAULT NULL,
  `dicabut_nama`  varchar(255) DEFAULT NULL,
  `dicabut_alasan` varchar(500) DEFAULT NULL,

  PRIMARY KEY (`id`),

  -- Reconciler memuat koreksi per rentang tanggal untuk SEMUA orang sekaligus
  -- (pola loadRules/loadExceptions: sekali muat, bukan kueri di loop harian).
  KEY `idx_koreksi_tanggal` (`work_date`, `dicabut_at`),
  KEY `idx_koreksi_user` (`user_id`, `work_date`),

  CONSTRAINT `fk_koreksi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
