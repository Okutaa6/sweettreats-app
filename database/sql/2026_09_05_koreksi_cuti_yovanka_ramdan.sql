-- ═══════════════════════════════════════════════════════════════════════════
-- Koreksi buku besar cuti — Yovanka & Ramdan (§A serah-terima 5 Sep 2026)
--
-- ⛔ JANGAN DIJALANKAN TANPA MEMBACA BAGIAN "PRASYARAT" DI BAWAH.
-- ⛔ Ini DATA PRODUKSI yang menentukan uang. Jalankan per blok, baca hasil tiap
--    SELECT, dan berhenti kalau ada satu angka yang tak sesuai.
--
-- ─────────────────────────────────────────────────────────────────────────
-- PRASYARAT YANG BELUM TERPENUHI PER 5 SEP 2026
-- ─────────────────────────────────────────────────────────────────────────
-- Serah-terima menyatakan perbaikan kode "cuti tidak memakan hari libur" SUDAH
-- hidup di prod. Itu TIDAK benar, dan mudah diperiksa:
--
--   * `CutiService::potongDariPengajuan()` di `main` masih memotong SETIAP HARI
--     KALENDER dalam rentang — komentarnya sendiri menyebut "belum melihat
--     jadwal kerja/hari libur".
--   * Blob `app/Services/CutiService.php` SAMA PERSIS di seluruh branch repo.
--   * Branch `fix/cuti-hari-libur` NOL COMMIT (reflog: "Created from
--     origin/main"), jadi `git merge-base --is-ancestor` terhadap prod memang
--     lulus — tapi yang lulus itu branch KOSONG, bukan perbaikannya.
--   * Perbaikan sesungguhnya masih menggantung TANPA COMMIT di worktree
--     `sweettreats-dodo` (10 berkas + 6 kelas uji).
--
-- Akibatnya untuk skrip ini:
--
--   ✅ AMAN dijalankan sekarang — pembalik di bawah TIDAK menghapus baris asli,
--      jadi penjaga idempoten (`sumber` + `ref_id` + `tanggal`) tetap melihat
--      potongan lamanya dan tak akan melahirkannya lagi.
--   ⚠️ TIDAK MENYELESAIKAN MASALAHNYA — pengajuan cuti BERIKUTNYA akan tetap
--      memotong hari libur. Skrip ini membereskan yang sudah telanjur; yang
--      menghentikannya adalah kode yang belum naik.
--
-- ─────────────────────────────────────────────────────────────────────────
-- KENAPA MEMBALIK, BUKAN MENGHAPUS
-- ─────────────────────────────────────────────────────────────────────────
-- `att_cuti_mutasi` itu BUKU BESAR: saldo tak pernah disimpan sebagai angka,
-- selalu dijumlahkan dari baris-barisnya. Menghapus baris memang membetulkan
-- angkanya, tapi menghapus pula bukti bahwa kesalahannya pernah ada — dan
-- inilah tabel yang akan dibuka kalau suatu hari ada yang mempersoalkan gajinya.
-- Jadi tiap koreksi masuk sebagai baris BARU yang menyebut nomor baris yang
-- dibatalkannya.
--
-- ⚠️ RAMDAN: SALDONYA TETAP 11 SESUDAH INI, dan itu BENAR.
-- Yang salah bukan angkanya, melainkan catatannya: 5 baris untuk 1 hari cuti.
-- Kalau sesudah menjalankan ini saldo Ramdan BERUBAH, ada yang keliru —
-- kembalikan (blok 5 memuat cara membatalkannya).
-- ═══════════════════════════════════════════════════════════════════════════


-- ═══ BLOK 1 — POTRET SEBELUM (WAJIB, catat hasilnya sebelum lanjut) ═══
-- Jalankan, SALIN hasilnya ke catatan deploy. Tanpa nilai lama, tak ada cara
-- membuktikan bahwa yang berubah cuma yang dimaksud.

SELECT m.id, u.nama, m.tanggal, DAYNAME(m.tanggal) AS hari, m.hari AS jml,
       m.jenis, m.sumber, m.ref_id, m.keterangan
FROM att_cuti_mutasi m
JOIN users u ON u.id COLLATE utf8mb4_unicode_ci = m.user_id COLLATE utf8mb4_unicode_ci
WHERE m.id IN (31,32,33,34,35,36, 22,23,24,25,27)
ORDER BY m.id;

-- Saldo kedua orang SEBELUM. Catat dua angka ini.
SELECT u.nama,
       p.saldo_awal,
       p.saldo_awal - COALESCE(SUM(m.hari), 0) AS sisa_sekarang
FROM att_cuti_periode p
JOIN users u ON u.id COLLATE utf8mb4_unicode_ci = p.user_id COLLATE utf8mb4_unicode_ci
LEFT JOIN att_cuti_mutasi m ON m.periode_id = p.id
WHERE p.berjalan = 1
  AND u.nama IN ('Yovanka Rima Millennia', 'Ramdan Ramdhani')
GROUP BY u.nama, p.saldo_awal;

-- Kendali negatif — cacah seluruh buku besar milik ORANG LAIN. Angka ini harus
-- SAMA PERSIS di blok 4. Kalau bergeser, ada baris orang lain yang tersenggol.
SELECT COUNT(*) AS baris_orang_lain, COALESCE(SUM(hari), 0) AS total_hari_orang_lain
FROM att_cuti_mutasi
WHERE user_id COLLATE utf8mb4_unicode_ci NOT IN (
    SELECT id COLLATE utf8mb4_unicode_ci FROM users
    WHERE nama IN ('Yovanka Rima Millennia', 'Ramdan Ramdhani')
);


-- ═══ BLOK 2 — YOVANKA: batalkan potongan di hari liburnya ═══
--
-- Pengajuan #243 (3–8 Sep, approved) memotong 6 hari kalender, id 31–36.
-- Dua di antaranya jatuh di hari libur Yovanka (aturan Fleksibel Kantor,
-- libur Minggu & Senin):
--     id 34 = 2026-09-06 (MINGGU)
--     id 35 = 2026-09-07 (SENIN)
--
-- Sesudah ini: cuti terpakai 4 hari (3, 4, 5, 8 Sep) dan saldo minusnya hilang.
--
-- SEMUA syarat ada di WHERE — id DAN nama DAN tanggal DAN nilai lama. Kalau
-- salah satu tak cocok, hasilnya NOL BARIS, bukan baris lain yang terbalik.

INSERT INTO att_cuti_mutasi
    (periode_id, user_id, tanggal, hari, jenis, sumber, ref_id, keterangan, dibuat_oleh, created_at)
SELECT m.periode_id, m.user_id, m.tanggal, -1.0, 'ganti_hari', 'manual',
       CONCAT('batal-', m.id),
       CONCAT('Batalkan mutasi #', m.id, ' — ', DAYNAME(m.tanggal),
              ' adalah hari libur ybs; cuti tidak memakan hari libur (keputusan Ekky 5 Sep 2026)'),
       NULL, NOW()
FROM att_cuti_mutasi m
JOIN users u ON u.id COLLATE utf8mb4_unicode_ci = m.user_id COLLATE utf8mb4_unicode_ci
WHERE m.id IN (34, 35)
  AND u.nama    = 'Yovanka Rima Millennia'
  AND m.hari    = 1.0
  AND m.jenis   = 'cuti'
  AND m.sumber  = 'pengajuan'
  AND m.ref_id  = '243'
  AND m.tanggal IN ('2026-09-06', '2026-09-07')
  -- jangan menulis dua kali kalau skrip diulang
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT * FROM att_cuti_mutasi) x
      WHERE x.ref_id = CONCAT('batal-', m.id) AND x.sumber = 'manual'
  );

-- Harus mengembalikan 2. Kalau 0: periksa dulu id/nama/tanggal — JANGAN
-- melonggarkan syaratnya supaya "berhasil".
SELECT ROW_COUNT() AS baris_pembalik_yovanka;


-- ═══ BLOK 3 — RAMDAN: sisakan satu potongan yang sah ═══
--
-- Keadaan yang ada (aturan GS, libur SENIN):
--     id 22 · 16 Agu (Minggu) · +1 · SAH — Minggu hari kerja GS
--     id 24 · 16 Agu (Minggu) · +1 · KEMBAR dari 22
--     id 23 · 17 Agu (Senin)  · +1 · hari LIBURnya
--     id 25 · 17 Agu (Senin)  · +1 · kembar DAN hari libur
--     id 27 · 21 Agu          · -3 · tambalan manual atas kekacauan di atas
--
-- Yang disisakan HANYA id 22. Tiga potongan dibatalkan (-3) dan tambalan lama
-- ikut dibatalkan (+3) — dua-duanya, kalau tidak, saldonya justru bergeser.
--
-- ⚠️ NETONYA NOL. Sisa Ramdan tetap 11. Itu memang tujuannya: yang diperbaiki
--    catatannya, bukan angkanya.

-- 3a) batalkan tiga potongan yang tak seharusnya ada (id 23, 24, 25)
INSERT INTO att_cuti_mutasi
    (periode_id, user_id, tanggal, hari, jenis, sumber, ref_id, keterangan, dibuat_oleh, created_at)
SELECT m.periode_id, m.user_id, m.tanggal, -1.0, 'ganti_hari', 'manual',
       CONCAT('batal-', m.id),
       CONCAT('Batalkan mutasi #', m.id, ' — ',
              CASE WHEN m.tanggal = '2026-08-17' THEN 'Senin, hari libur ybs (aturan GS)'
                   ELSE 'potongan kembar untuk tanggal yang sama' END,
              '; cuti 16-17 Agu sebenarnya hanya 1 hari kerja'),
       NULL, NOW()
FROM att_cuti_mutasi m
JOIN users u ON u.id COLLATE utf8mb4_unicode_ci = m.user_id COLLATE utf8mb4_unicode_ci
WHERE m.id IN (23, 24, 25)
  AND u.nama    = 'Ramdan Ramdhani'
  AND m.hari    = 1.0
  AND m.jenis   = 'cuti'
  AND m.sumber  = 'pengajuan'
  AND m.tanggal IN ('2026-08-16', '2026-08-17')
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT * FROM att_cuti_mutasi) x
      WHERE x.ref_id = CONCAT('batal-', m.id) AND x.sumber = 'manual'
  );

-- Harus 3.
SELECT ROW_COUNT() AS baris_pembalik_ramdan_potongan;

-- 3b) batalkan tambalan manual -3 (id 27) yang kini tak punya alasan berdiri
INSERT INTO att_cuti_mutasi
    (periode_id, user_id, tanggal, hari, jenis, sumber, ref_id, keterangan, dibuat_oleh, created_at)
SELECT m.periode_id, m.user_id, m.tanggal, 3.0, 'koreksi', 'manual',
       CONCAT('batal-', m.id),
       CONCAT('Batalkan mutasi #', m.id, ' — koreksi manual "salah input" tak lagi diperlukan ',
              'setelah potongan kembar & hari libur dibatalkan; menyisakan keduanya = kelebihan 3 hari'),
       NULL, NOW()
FROM att_cuti_mutasi m
JOIN users u ON u.id COLLATE utf8mb4_unicode_ci = m.user_id COLLATE utf8mb4_unicode_ci
WHERE m.id      = 27
  AND u.nama    = 'Ramdan Ramdhani'
  AND m.hari    = -3.0
  AND m.jenis   = 'ganti_hari'
  AND m.sumber  = 'manual'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT * FROM att_cuti_mutasi) x
      WHERE x.ref_id = 'batal-27' AND x.sumber = 'manual'
  );

-- Harus 1. Kalau 0 karena `hari` bukan -3.0, BERHENTI dan periksa blok 1:
-- artinya tambalan lamanya berbeda dari yang diperkirakan serah-terima, dan
-- angka penutupnya harus dihitung ulang.
SELECT ROW_COUNT() AS baris_pembalik_ramdan_tambalan;


-- ═══ BLOK 4 — VERIFIKASI (wajib dibaca, jangan dilewati) ═══

-- Saldo SESUDAH. Yang diharapkan:
--     Yovanka  : naik 2 hari — dari minus jadi TIDAK minus
--     Ramdan   : TETAP 11 — tak bergerak sama sekali
SELECT u.nama,
       p.saldo_awal,
       p.saldo_awal - COALESCE(SUM(m.hari), 0) AS sisa_sesudah
FROM att_cuti_periode p
JOIN users u ON u.id COLLATE utf8mb4_unicode_ci = p.user_id COLLATE utf8mb4_unicode_ci
LEFT JOIN att_cuti_mutasi m ON m.periode_id = p.id
WHERE p.berjalan = 1
  AND u.nama IN ('Yovanka Rima Millennia', 'Ramdan Ramdhani')
GROUP BY u.nama, p.saldo_awal;

-- Riwayat lengkap keduanya — harus terbaca sebagai cerita yang utuh:
-- potongan aslinya MASIH ADA, masing-masing berpasangan dengan pembatalnya.
SELECT m.id, u.nama, m.tanggal, DAYNAME(m.tanggal) AS hari, m.hari AS jml,
       m.jenis, m.sumber, m.ref_id, m.keterangan
FROM att_cuti_mutasi m
JOIN users u ON u.id COLLATE utf8mb4_unicode_ci = m.user_id COLLATE utf8mb4_unicode_ci
WHERE u.nama IN ('Yovanka Rima Millennia', 'Ramdan Ramdhani')
ORDER BY u.nama, m.tanggal, m.id;

-- Kendali negatif — WAJIB sama persis dengan blok 1.
SELECT COUNT(*) AS baris_orang_lain, COALESCE(SUM(hari), 0) AS total_hari_orang_lain
FROM att_cuti_mutasi
WHERE user_id COLLATE utf8mb4_unicode_ci NOT IN (
    SELECT id COLLATE utf8mb4_unicode_ci FROM users
    WHERE nama IN ('Yovanka Rima Millennia', 'Ramdan Ramdhani')
);


-- ═══ BLOK 5 — CARA MEMBATALKAN (kalau angkanya tak sesuai harapan) ═══
--
-- Pembalik yang ditulis skrip ini semuanya ber-`ref_id` berawalan 'batal-' dan
-- ber-`sumber` = 'manual'. Karena BELUM PERNAH menjadi rujukan apa pun, baris
-- ini aman dihapus — beda dari baris asli, yang tidak boleh disentuh.
--
--   DELETE FROM att_cuti_mutasi
--   WHERE sumber = 'manual' AND ref_id IN ('batal-34','batal-35','batal-23','batal-24','batal-25','batal-27');
--
-- Sesudahnya jalankan blok 1 lagi dan pastikan angkanya kembali seperti semula.
