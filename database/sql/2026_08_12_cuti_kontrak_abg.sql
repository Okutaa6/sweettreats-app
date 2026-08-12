-- ═══════════════════════════════════════════════════════════════════════════
-- Jatah cuti + kontrak: ABG / Office
-- Sumber kebenaran: sheet HR/Finance 12 Agu 2026 (lebih benar daripada DB).
--
-- ATURAN CUTI yang dijelaskan Ekky 2026-08-12:
--   • Jatah 12 hari per kontrak, diberikan DI DEPAN (bukan menetes bulanan).
--     Bukti: Irma baru ganti kontrak Juli, langsung 12 penuh.
--   • KARYAWAN BARU BELUM BERHAK. Jatah baru muncul saat kontrak
--     DIPERPANJANG (masuk periode kedua). Della, Talitha, Reza, Alif semua
--     masuk tahun ini → saldo 0 karena BELUM BERHAK, bukan karena habis.
--   • Angka "Cuti" di sheet = SISA hari ini, bukan jatah awal.
--
-- Kolom "Kontrak" di sheet = hari jadi kontrak: akhir periode berjalan
-- sekaligus awal periode berikutnya. Periode berjalan = 12 bulan mundur.
--
-- Dikunci lewat EMAIL (ejaan nama beda antar sumber: "Yovanka Rima Millennia"
-- vs "YOVANKA RIMAMILLENNIA"). Idempoten: aman dijalankan ulang.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── 1) Yang SUDAH berhak (kontrak sudah pernah diperpanjang) ────────────────
-- saldo_awal = 12 (jatah penuh per kontrak). Selisih menuju sisa yang
-- sebenarnya diurus baris penyesuaian di bagian (3) — BUKAN dengan
-- mengecilkan saldo_awal, supaya hak 12 harinya tetap terbaca.
UPDATE `att_cuti_periode` p JOIN `users` u ON u.`id` = p.`user_id`
SET p.`saldo_awal` = 12.0,
    p.`kontrak_akhir` = CASE u.`email`
        WHEN 'irma@sweettreats.id'    THEN '2027-07-25'
        WHEN 'yovanka@sweettreats.id' THEN '2026-09-13'
        WHEN 'ikhsan@sweettreats.id'  THEN '2026-09-01'
        ELSE NULL                                      -- Anisa: PERMANEN
    END,
    p.`kontrak_mulai` = CASE u.`email`
        WHEN 'irma@sweettreats.id'    THEN '2026-07-25'
        WHEN 'yovanka@sweettreats.id' THEN '2025-09-13'
        WHEN 'ikhsan@sweettreats.id'  THEN '2025-09-01'
        ELSE NULL                                      -- Anisa: PERMANEN
    END,
    p.`updated_at` = DATE_ADD(NOW(), INTERVAL 7 HOUR)
WHERE u.`email` IN ('irma@sweettreats.id','yovanka@sweettreats.id',
                    'ikhsan@sweettreats.id','anisa@sweettreats.id');

-- ── 2) Yang BELUM berhak (kontrak pertama, masuk 2026) ──────────────────────
-- saldo 0 dengan arti "belum berhak", bukan "sudah habis". Tanggal kontraknya
-- tetap diisi kalau sudah diketahui — justru tanggal itulah yang nanti jadi
-- pemicu mereka melompat ke 12 saat perpanjangan.
-- Alif & Reza: kontrak masih dicari → tanggal dibiarkan kosong.
INSERT INTO `att_cuti_periode` (`user_id`, `saldo_awal`, `berjalan`, `created_at`, `updated_at`)
SELECT u.`id`, 0.0, 1, DATE_ADD(NOW(), INTERVAL 7 HOUR), DATE_ADD(NOW(), INTERVAL 7 HOUR)
FROM `users` u
WHERE u.`email` IN ('dellaps@sweettreats.id','talitha@sweettreats.id',
                    'alif@sweettreats.id','reza@sweettreats.id')
  AND NOT EXISTS (SELECT 1 FROM `att_cuti_periode` c WHERE c.`user_id` = u.`id`);

UPDATE `att_cuti_periode` p JOIN `users` u ON u.`id` = p.`user_id`
SET p.`saldo_awal` = 0.0,
    p.`kontrak_akhir` = CASE u.`email`
        WHEN 'dellaps@sweettreats.id' THEN '2027-05-31'
        WHEN 'talitha@sweettreats.id' THEN '2027-06-16'
        ELSE NULL                                      -- Alif & Reza: dicari
    END,
    p.`kontrak_mulai` = CASE u.`email`
        WHEN 'dellaps@sweettreats.id' THEN '2026-05-31'
        WHEN 'talitha@sweettreats.id' THEN '2026-06-16'
        ELSE NULL
    END,
    p.`updated_at` = DATE_ADD(NOW(), INTERVAL 7 HOUR)
WHERE u.`email` IN ('dellaps@sweettreats.id','talitha@sweettreats.id',
                    'alif@sweettreats.id','reza@sweettreats.id');

-- ── 3) Penyesuaian saldo untuk yang sudah memakai cutinya ───────────────────
-- Anisa & Yovanka sudah punya cuti TERCATAT di sistem (1 dan 2 hari, lengkap
-- dengan pengaju & penyetujunya). Baris itu TIDAK DISENTUH — menghapusnya demi
-- mencocokkan angka justru membuang yang paling berharga: jejaknya.
--
-- Yang ditambahkan satu baris penyesuaian sebesar selisih yang TIDAK tercatat
-- di sistem. Keterangannya sengaja tidak berbunyi "terpakai N hari" — kita
-- cuma tahu BESAR selisihnya, bukan sebabnya.
--
--   Anisa   : 12 − 7 penyesuaian − 1 tercatat = 4  ✓ sheet
--   Yovanka : 12 − 6 penyesuaian − 2 tercatat = 4  ✓ sheet
--   Ikhsan  : 12 − 4 penyesuaian − 0 tercatat = 8  ✓ sheet
INSERT INTO `att_cuti_mutasi`
    (`periode_id`, `user_id`, `tanggal`, `hari`, `jenis`, `sumber`, `keterangan`, `created_at`)
SELECT p.`id`, u.`id`, DATE(DATE_ADD(NOW(), INTERVAL 7 HOUR)),
       CASE u.`email`
           WHEN 'anisa@sweettreats.id'   THEN 7.0
           WHEN 'yovanka@sweettreats.id' THEN 6.0
           ELSE 4.0                                     -- Ikhsan
       END,
       'koreksi', 'manual',
       'Penyesuaian saldo awal: selisih dari jatah 12 saat sistem mulai mencatat (sumber: sheet HR 12 Agu 2026)',
       DATE_ADD(NOW(), INTERVAL 7 HOUR)
FROM `users` u JOIN `att_cuti_periode` p ON p.`user_id` = u.`id`
WHERE u.`email` IN ('anisa@sweettreats.id','yovanka@sweettreats.id','ikhsan@sweettreats.id')
  AND NOT EXISTS (
      SELECT 1 FROM `att_cuti_mutasi` m
      WHERE m.`user_id` = u.`id` AND m.`keterangan` LIKE 'Penyesuaian saldo awal:%'
  );

-- ── CATATAN ────────────────────────────────────────────────────────────────
-- • ANISA PERMANEN: kontrak_mulai & kontrak_akhir sengaja NULL — dia memang tak
--   punya tanggal berakhir, dan mengarang tanggal mulai akan merusak
--   perhitungan reset nanti. Keputusan Ekky: untuk karyawan permanen, jatah
--   di-reset tiap 1 JANUARI. Saat fitur reset dibangun, `kontrak_akhir IS NULL`
--   itulah penanda "permanen → pakai 1 Januari".
-- • DI LUAR SISTEM CUTI (keputusan Ekky): Dani, Hari, Robi — role Executive.
--   Jangan dibuatkan baris; ketiadaan baris = "—" di layar, itu yang benar.
-- • ELA PUPUT CAHYANI ada di sheet tapi belum punya akun di prod — sengaja
--   dilewati dulu atas permintaan Ekky.
-- • ALIF & REZA: tanggal kontrak masih dicari Ekky. Saldo 0 sudah benar
--   (belum berhak); tanggalnya menyusul.
