-- ============================================================================
-- 2026_07_23_att_request_koreksi.sql
--
-- Tambah jenis pengajuan ke-6: KOREKSI ABSEN.
--
-- Kenapa perlu: kalau scan seseorang keliru (lupa tap, sidik jari gagal, jam
-- tercatat salah), sampai sekarang dia TAK PUNYA JALAN buat lapor — hari itu
-- terlanjur kehitung alpa/telat dan cuma bisa dibetulkan lewat orang dalam.
-- Padahal data scan memang rapuh (mesin per cabang, No.ID bentrok, orang rotasi).
--
-- Semantiknya BEDA dari 5 jenis lain: koreksi bukan "jenis ketidakhadiran",
-- tapi bantahan atas hasil scan. Kalau DISETUJUI, hari itu dihitung HADIR —
-- lewat jalur override yang sudah ada di AttendanceReconciler::requestsForDate()
-- (§4: deklarasi manusia menang atas scan). att_day.status TAK perlu nilai baru.
--
-- Alasan koreksi (chip di form) disimpan di kolom `alasan` yang sudah ada,
-- formatnya "<alasan pilihan> — <catatan>", sama seperti desain handoff.
--
-- Idempoten: MODIFY enum aman dijalankan ulang.
-- ============================================================================

ALTER TABLE `att_request`
    MODIFY `tipe` ENUM('cuti','izin','libur','lembur','wfh','koreksi') NOT NULL;
