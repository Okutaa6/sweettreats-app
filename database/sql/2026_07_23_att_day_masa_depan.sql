-- ============================================================================
-- 2026_07_23_att_day_masa_depan.sql
--
-- Hapus att_day 'alpa' untuk tanggal yang BELUM LEWAT.
--
-- Bug-nya di AttendanceReconciler: hari kerja tanpa scan & tanpa form langsung
-- ditandai ALPA, tanpa cek tanggalnya sudah terjadi atau belum. Akibatnya sisa
-- bulan berjalan ikut ditandai bolos — karyawan membuka "Absensi Saya" dan
-- melihat dirinya alpa di tanggal yang belum datang, dan angka "TIDAK MASUK"
-- di rekap Finance ikut menggelembung.
--
-- Selama ini tak kelihatan karena daftar hari diurut menaik, jadi tanggal masa
-- depan tenggelam di bawah. Ketahuan begitu urutannya dibalik (terbaru dulu,
-- ikut desain).
--
-- Penjaga sudah dipasang di reconciler (`if ($key >= today) continue;`), jadi
-- baris seperti ini tak akan terbentuk lagi. Yang dihapus di sini hanya baris
-- ALPA tanpa scan — cuti/izin/WFH yang sudah disetujui untuk tanggal mendatang
-- SENGAJA dibiarkan, karena itu memang sah dan berguna ditampilkan.
--
-- Idempoten.
-- ============================================================================

DELETE FROM `att_day`
WHERE `work_date` >= CURDATE()
  AND `status` = 'alpa'
  AND `scan_count` = 0;
