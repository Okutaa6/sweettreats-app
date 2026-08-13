-- ============================================================================
-- Gelar Cinere & Pamulang — cuti, penempatan (rolling), dan rapi-rapi
--
-- Rujukan  : docs/rencana/rencana-gelar-cinere-pamulang.md (§2, §4, §6)
-- Angka    : sheet HR 12 Agu 2026 — dinyatakan Ekky sebagai sumber paling benar
--            (13 Agu 2026), mengalahkan isi dev yang menyimpang.
--
-- AMAN DIULANG. Tiap bagian membersihkan jejaknya sendiri dulu, jadi file ini
-- boleh dijalankan berkali-kali tanpa menggandakan apa pun.
--
-- COCOK LEWAT EMAIL, BUKAN NAMA. Ejaan berbeda antar sumber sudah terbukti
-- memakan waktu: Alpajri/Apajri, Meilani/Melani, Naskah/Nashkah. Juga ada DUA
-- Della — Della Raqisiah (FO Cinere, della@) dan Della Puspita Sari (Finance,
-- dellaps@). Yang disentuh di sini hanya yang pertama.
--
-- ⚠️ Di lingkungan yang akunnya BELUM ada (mis. prod sebelum akun dibuat),
-- semua pernyataan di sini kena 0 baris — tidak error, tapi juga tidak
-- berefek. Jalankan lagi SETELAH akunnya dibuat.
--
-- ⚠️ Kolom users.raw_branch_id sengaja TIDAK disentuh untuk orang penempatan.
-- Cabang asal itu identitas roster & dasar rekap; penempatan bersifat
-- sementara (§4 rencana — keputusan ini pernah diambil lalu dibatalkan sekali,
-- jangan diulang).
-- ============================================================================


-- ── 1. Ejaan nama: sheet HR menulis "Naskah", dev menulis "Nashkah" ─────────
UPDATE users SET nama = 'Verini Selvia Naskah'
WHERE email = 'verini@sweettreats.id';


-- ── 2. Ahmad Saipulloh (GS Pamulang) sudah RESIGN ──────────────────────────
-- Dinonaktifkan, BUKAN dihapus — jejak absensinya harus tetap utuh.
-- Cron "Tidak Diisi" (MarkChecklistMissed & MarkWeeklyChecklistMissed) sudah
-- menyaring users.status = 1, jadi baris posisinya tak perlu ikut ditutup.
UPDATE users SET status = 0
WHERE email = 'ahmad@sweettreats.id';


-- ── 3. Buang sisa data DEMO pada orang dalam lingkup ───────────────────────
-- Dev punya 7 baris "[DEMO] Cuti disetujui"; 4 di antaranya milik Della &
-- Wahyudin dan ikut mengurangi saldo mereka. Tiga baris sisanya milik Rizka
-- (Bintaro, di luar lingkup gelar ini) — SENGAJA tidak disentuh di sini.
DELETE m FROM att_cuti_mutasi m
JOIN users u ON u.id = m.user_id
WHERE u.email IN ('della@sweettreats.id', 'wahyudin@sweettreats.id')
  AND m.keterangan LIKE '[DEMO]%';


-- ── 4. Periode cuti sesuai sheet HR ────────────────────────────────────────
-- Bentuknya mengikuti keputusan §2: saldo_awal = 12 (jatah penuh per kontrak),
-- lalu selisih ke angka sheet ditutup satu baris koreksi di bagian 5 — supaya
-- "berapa yang sudah terpakai" tetap punya jejak, bukan hilang ke dalam angka.
--
-- Kecuali VERINI: kontrak pertama, BELUM BERHAK atas jatah (jatah baru muncul
-- saat kontrak diperpanjang). Saldonya 0 dan TIDAK diberi baris koreksi —
-- 12-tanpa-koreksi justru akan menampilkan 12 hari yang bukan haknya.
--
-- Kolom "Kontrak" di sheet = hari jadi kontrak (akhir periode berjalan).
-- kontrak_mulai = 12 bulan mundur (asumsi §2; kalau ada yang 6/24 bulan,
-- tanggal MULAI-nya yang meleset, tanggal akhir tetap benar).
UPDATE att_cuti_periode p
JOIN users u ON u.id = p.user_id
JOIN (
    SELECT 'anggita@sweettreats.id'  AS email, 12.0 AS saldo, '2026-04-21' AS mulai, '2027-04-21' AS akhir
    UNION ALL SELECT 'della@sweettreats.id',    12.0, '2025-11-28', '2026-11-28'
    UNION ALL SELECT 'wahyudin@sweettreats.id', 12.0, '2026-05-06', '2027-05-06'
    UNION ALL SELECT 'alviona@sweettreats.id',  12.0, '2025-10-21', '2026-10-21'
    UNION ALL SELECT 'mutiara@sweettreats.id',  12.0, '2025-11-07', '2026-11-07'
    UNION ALL SELECT 'ramdan@sweettreats.id',   12.0, '2026-07-24', '2027-07-24'
    UNION ALL SELECT 'verini@sweettreats.id',    0.0, '2025-11-07', '2026-11-07'
) s ON s.email = u.email
SET p.saldo_awal    = s.saldo,
    p.kontrak_mulai = s.mulai,
    p.kontrak_akhir = s.akhir,
    p.berjalan      = 1,
    p.updated_at    = NOW();


-- ── 5. Baris koreksi penutup selisih ───────────────────────────────────────
-- Sisa = saldo_awal − Σ hari (CutiPeriode::sisa), jadi `hari` POSITIF di sini
-- MENGURANGI saldo. Angkanya = 12 − sisa menurut sheet:
--   Anggita 10 → 2 · Della 6 → 6 · Wahyudin 11 → 1 · Alviona 2 → 10 · Mutiara 7 → 5
-- Ramdan (sisa 12, utuh) dan Verini (belum berhak) tidak dapat baris.
--
-- Keterangannya sengaja jujur: kita tahu BESAR selisihnya, bukan tanggal atau
-- alasan pemakaiannya — jangan mengarang "terpakai N hari pada tanggal X".
DELETE FROM att_cuti_mutasi
WHERE keterangan = 'Penyesuaian saldo awal — sheet HR 12 Agu 2026';

INSERT INTO att_cuti_mutasi
    (periode_id, user_id, tanggal, hari, jenis, sumber, keterangan, created_at)
SELECT p.id, u.id, '2026-08-13', s.hari, 'koreksi', 'manual',
       'Penyesuaian saldo awal — sheet HR 12 Agu 2026', NOW()
FROM users u
JOIN att_cuti_periode p ON p.user_id = u.id AND p.berjalan = 1
JOIN (
    SELECT 'anggita@sweettreats.id'  AS email,  2.0 AS hari
    UNION ALL SELECT 'della@sweettreats.id',     6.0
    UNION ALL SELECT 'wahyudin@sweettreats.id',  1.0
    UNION ALL SELECT 'alviona@sweettreats.id',  10.0
    UNION ALL SELECT 'mutiara@sweettreats.id',   5.0
) s ON s.email = u.email;


-- ── 6. Penempatan (rolling) ────────────────────────────────────────────────
-- TIGA baris, semuanya TERBUKA (effective_to NULL) — belum diketahui sampai
-- kapan, keputusan Ekky 13 Agu 2026. Terbuka tetap pulih otomatis: begitu
-- supervisor menekan "Akhiri rolling", effective_to diisi kemarin dan orangnya
-- kembali ke cabang asal tanpa satu pun data ditulis ulang.
--
--   Della Raqisiah  Cinere    → ditempatkan di Sunter    ⎫ swap dua sisi
--   Verini S. Naskah Sunter   → ditempatkan di Cinere    ⎭
--   Ramdan Ramdhani Kalideres → ditempatkan di Pamulang  (menutup lowongan
--                                                        Ahmad yang resign)
--
-- effective_from = 13 Agu 2026, hari file ini dijalankan. Penempatannya
-- sebetulnya sudah berjalan sebelum itu, tapi tanggal mulai sengaja TIDAK
-- dimundurkan: memundurkannya akan mengubah arti checklist & rekap hari-hari
-- yang sudah lewat. Kalau jejak lama memang mau dibetulkan, itu pekerjaan
-- terpisah dengan tanggalnya sendiri.
--
-- division_id ikut divisi role masing-masing (rolling hanya memindah CABANG,
-- bukan divisi) — FO / GS / ASDENT.
DELETE p FROM sys_user_position p
JOIN users u ON u.id = p.user_id
WHERE p.kind = 'rolling'
  AND p.note LIKE 'Gelar Cinere-Pamulang%'
  AND u.email IN ('della@sweettreats.id', 'verini@sweettreats.id', 'ramdan@sweettreats.id');

INSERT INTO sys_user_position
    (id, user_id, division_id, raw_branch_id, is_primary, kind,
     effective_from, effective_to, confirmed_at, note, created_at, updated_at)
SELECT UUID(), u.id, s.divisi, b.id, 1, 'rolling',
       '2026-08-13', NULL, NOW(), s.catatan, NOW(), NOW()
FROM users u
JOIN (
    SELECT 'della@sweettreats.id'  AS email, 'FO' AS divisi, 'Sunter'   AS cabang,
           'Gelar Cinere-Pamulang: swap dengan Verini, terbuka' AS catatan
    UNION ALL SELECT 'verini@sweettreats.id', 'FO', 'Cinere',
           'Gelar Cinere-Pamulang: swap dengan Della, terbuka'
    UNION ALL SELECT 'ramdan@sweettreats.id', 'GS', 'Pamulang',
           'Gelar Cinere-Pamulang: menutup lowongan GS (Ahmad resign), terbuka'
) s ON s.email = u.email
JOIN raw_branch b ON b.name = s.cabang;
