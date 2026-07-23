-- ============================================================================
-- 2026_07_23_att_day_yatim_akun_admin.sql
--
-- Bersihkan att_day YATIM di akun sistem (Admin_Ekky / Admin_Reza).
--
-- Asal-usulnya: dulu mesin `Ikhsan #52` & `Reza #62` ke-map ke akun ADMIN, jadi
-- reconcile (computed_at 2026-07-21) bikin att_day atas nama akun admin. Mapping
-- lalu dibetulkan ke akun KARYAWAN (Muhamad Ikhsan / Reza Fauzi Ramadhan) —
-- seluruh att_scan ikut pindah ke akun karyawan, tapi att_day lamanya nyangkut.
--
-- Akibatnya di rekap: akun admin (BUKAN karyawan) tampil punya kehadiran padahal
-- nol scan pendukung, sementara akun karyawan yang megang 538 scan tampil kosong.
--
-- Baris ini tak punya dasar (tak ada att_scan di akun itu) → dihapus. Kehadiran
-- yang sebenarnya dibangun ulang oleh `php artisan att:reconcile 2026-03-01 2026-06-30`
-- di akun karyawan, yang sejak 2026_07_23_att_rule_akun_pegawai.sql sudah punya
-- aturan kerja Fleksibel.
--
-- Idempoten. Konteks: memory project-absensi-lintas-cabang §3b.
-- ============================================================================

DELETE d FROM `att_day` d
JOIN `users` u ON u.`id` = d.`user_id`
WHERE u.`nama` IN ('Admin_Ekky', 'Admin_Reza')
  AND NOT EXISTS (SELECT 1 FROM `att_scan` s WHERE s.`user_id` = d.`user_id`);
