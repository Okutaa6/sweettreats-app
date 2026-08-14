-- ═══════════════════════════════════════════════════════════════════════════
-- Rahmat dapat aturan kerja · Hari pindah ke Consultant · bawaan role — 2026-08-14
--
-- MASALAH yang ditutup (kejadian nyata hari ini):
--   Rahmat (Consultant) absen 14 Agu 11.19 — buktinya tersimpan, foto ada,
--   WiFi cocok Bintaro — tapi dia TIDAK MUNCUL di layar Kehadiran sama sekali.
--   Sebabnya: dia tak punya baris `att_user_rule`, dan layar Kehadiran
--   menyusun rosternya DARI tabel itu (AbsensiKehadiranController: roster =
--   att_user_rule JOIN att_work_rule JOIN users). Tak punya aturan → tak masuk
--   roster → bukan hadir, bukan alpa, bukan apa-apa. Hilang diam-diam.
--
--   Akarnya: role `Consultant` tak punya `att_default_rule_id`, jadi akun yang
--   dibuat lewat menu admin lahir tanpa aturan. Kelas kegagalan yang sama
--   dengan Alpajri (Agustus) — sudah ditutup untuk 3 role operasional lewat
--   2026_08_13_aturan_bawaan_role_operasional.sql, ternyata masih bersisa.
--
--   Buktinya cuma dia: 14 Agu ada 24 orang absen, 23 punya baris `att_day`.
--
-- ⚠️ effective_from = 2026-08-14, TANGGAL ABSEN PERTAMANYA — sengaja TIDAK
--    dimundurkan. Aturan yang berlaku sebelum scan pertama seseorang membuat
--    hari-hari sebelumnya dinilai sebagai ALPA PALSU (Alpajri kena 3).
--
-- Aturan yang dipakai: `Fleksibel` (bukan `Fleksibel Kantor`) — keputusan Ekky
-- 14 Agu. Tipe fleksibel = tidak dinilai telat/alpa.
--
-- Dipetakan lewat NAMA & email, bukan id — id berbeda antar lingkungan.
-- Idempoten: aman dijalankan ulang.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── 1. Aturan kerja untuk Rahmat ────────────────────────────────────────────
INSERT INTO `att_user_rule` (`user_id`, `rule_id`, `effective_from`, `created_at`, `updated_at`)
SELECT u.`id`, r.`id`, '2026-08-14', NOW(), NOW()
FROM `users` u
JOIN `att_work_rule` r ON r.`nama` = 'Fleksibel'
WHERE u.`email` = 'rahmat@sweettreats.id'
  AND NOT EXISTS (
      SELECT 1 FROM `att_user_rule` ur WHERE ur.`user_id` = u.`id`
  );

-- ── 2. Hari pindah role: Executive → Consultant ─────────────────────────────
-- ⚠️ Pindah role IKUT MEMINDAHKAN HAK AKSES (group_access per role). Yang
--    DICABUT dari Hari: Persetujuan + Inbox Persetujuan · Keuangan + Rekap
--    Pengeluaran · Schedule + Reservasi Pasien.
--    Yang TETAP: Akun Saya · Absensi Saya · Data Center · Report · Laporan
--    Penjualan · Executive.
--    Aman dilakukan sekarang: Hari 0 baris `att_approver`, 0 absen, 0 att_day —
--    tak ada rantai persetujuan yang putus dan tak ada riwayat yang terganggu.
--    Aturan kerjanya sudah `Fleksibel` dan TIDAK ikut berubah (aturan menempel
--    di orang, bukan di role).
UPDATE `users` u
JOIN `group_path` g ON g.`nama` = 'Consultant'
SET u.`group_id` = g.`id`
WHERE u.`email` = 'hari@stdc.id'
  AND u.`group_id` <> g.`id`;

-- ── 3. Bawaan role Consultant, supaya tak terulang ──────────────────────────
UPDATE `group_path` g
JOIN `att_work_rule` r ON r.`nama` = 'Fleksibel'
SET g.`att_default_rule_id` = r.`id`
WHERE g.`nama` = 'Consultant'
  AND (g.`att_default_rule_id` IS NULL OR g.`att_default_rule_id` <> r.`id`);

-- ── Catatan: yang MASIH kosong bawaannya ────────────────────────────────────
-- `Executive` (2 org sesudah Hari pindah) dan `Branch Executive` (1 org)
-- sengaja TIDAK disentuh — belum diputuskan Ekky. Orang yang sudah ada di
-- sana aman (Dani dsb. punya aturan eksplisit); yang berisiko cuma akun BARU
-- di role itu, yang akan lahir tanpa aturan dan lenyap dari Kehadiran.
--
-- Role sisanya (Admin, Admin Backup, Admin General, Super Admin, Sales,
-- Cabang) memang akun sistem/legacy — kosongnya benar, jangan diisi.
--
-- ⚠️ SESUDAH SQL INI: hitung ulang supaya Rahmat langsung muncul —
--    php artisan att:reconcile 2026-08-14 2026-08-14
