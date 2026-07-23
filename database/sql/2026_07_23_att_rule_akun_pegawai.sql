-- ============================================================================
-- 2026_07_23_att_rule_akun_pegawai.sql
--
-- Perbaikan data absensi, 2 hal:
--
-- BUG 1 — aturan kerja "Fleksibel" nempel di akun yang SALAH.
--   Mesin sidik jari map ke akun PEGAWAI:
--       Ikhsan #52 -> Muhamad Ikhsan (Head IT)
--       Reza   #62 -> Reza Fauzi Ramadhan (IT)
--   tapi rule 3 (Fleksibel) justru dikasih ke Admin_Ekky & Admin_Reza — akun
--   SISTEM, bukan karyawan (sengaja dipisah: 1 orang, 2 akun). Akibatnya akun
--   yang beneran nerima scan tak punya aturan fleksibel → begitu scan mereka
--   di-reconcile, Head IT bisa ketandai ALPA/TELAT. Itu dilarang keras:
--   lihat memory project-absensi-lintas-cabang §2 & §4 dan
--   docs/rencana-rekonsiliasi-absensi.md §3.3.
--
-- BUG 2 (sebagian) — home branch kosong bikin orang "kesebar" di rekap.
--   Irma Islamiati (Koordinator Asdent) & Admin_Nisa / Anisa Aulia (SPV
--   Operasional) itu orang SUNTER yang sering nutupin cabang lain. Aturan
--   mereka tetap BAKU (FO-Asdent + hak lembur) — mereka pengecualian yang
--   didokumentasikan, JANGAN dijadikan office/fleksibel.
--
-- Orang fleksibel (ABG/kantor) sengaja home branch-nya dibiarkan KOSONG —
-- mereka memang tak nempel satu klinik; pengelompokannya diturunkan dari
-- att_work_rule.tipe='fleksibel', bukan dari cabang.
--
-- Idempoten (aman dijalankan ulang).
-- ============================================================================

-- ── BUG 1a: akun ADMIN (bukan karyawan) tak boleh punya aturan absensi ──
DELETE ur FROM `att_user_rule` ur
JOIN `users` u ON u.`id` = ur.`user_id`
WHERE u.`nama` IN ('Admin_Ekky', 'Admin_Reza');

-- ── BUG 1b: pasang Fleksibel (rule 3) ke akun PEGAWAI yang discan mesin ──
-- rule dicari lewat NAMA ('Fleksibel'), bukan id 3: id itu auto-increment hasil
-- urutan seed di mesin lokal dan belum tentu sama di server.
INSERT INTO `att_user_rule` (`user_id`, `rule_id`, `effective_from`, `created_at`, `updated_at`)
SELECT u.`id`, w.`id`, '2026-01-01', NOW(), NOW()
FROM `users` u
JOIN `att_work_rule` w ON w.`nama` = 'Fleksibel'
WHERE u.`nama` IN ('Muhamad Ikhsan', 'Reza Fauzi Ramadhan')
  AND NOT EXISTS (SELECT 1 FROM `att_user_rule` x WHERE x.`user_id` = u.`id`);

-- ── BUG 2: home branch Irma & Anisa → Sunter (aturan tetap FO-Asdent/baku) ──
UPDATE `users`
SET `raw_branch_id` = (SELECT `id` FROM `raw_branch` WHERE `name` = 'Sunter' LIMIT 1)
WHERE `nama` IN ('Irma Islamiati', 'Admin_Nisa')
  AND `raw_branch_id` IS NULL;
