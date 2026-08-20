-- ============================================================================
-- KOREKSI PROD — Petty Cash Della: pindah periode Cinere → Sunter
-- ============================================================================
-- MASALAH:
--   Della (home = Cinere) rolling sebulan ke Sunter, tapi posisi rolling LUPA
--   dibuat → cabang efektif Della tetap Cinere → dia mencatat pengeluaran &
--   rincian wallet SUNTER ke dalam periode CINERE (sebagai kas cabang / holder
--   NULL). Sunter belum ada pencatatan apa pun. Isi periode Cinere bulan ini =
--   100% catatan Della (dikonfirmasi user).
--
-- SOLUSI (disetujui): re-point SELURUH periode Cinere bulan ini → Sunter dengan
--   mengubah SATU kolom `raw_branch_id` pada SATU baris `petty_cash_periods`.
--   Semua transaksi ikut otomatis via `period_id` (tidak disentuh). Opening /
--   rincian yang di-set Della memang angka Sunter → jadi benar di tempat baru.
--   Periode Cinere BARU akan terbentuk otomatis saat halaman dibuka (self-heal
--   carry-forward dari closing bulan lalu Cinere).
--
-- SIFAT OPERASI: 1 baris, 1 kolom. Tidak ada transaksi/nominal diubah, tidak
--   ada delete data. REVERSIBLE penuh (lihat blok ROLLBACK di bawah).
--
-- ⚠️ URUTAN WAJIB DI PROD:
--   0. Pastikan kode fitur rolling/holder sudah deploy + SQL
--      2026_08_19_pettycash_holder_wallet.sql (VARCHAR 36) sudah di-apply.
--   1. Minta Della BERHENTI mencatat sebentar.
--   2. Jalankan STEP 1 (VERIFIKASI) — BACA hasilnya, pastikan sesuai.
--   3. Jalankan STEP 2 (EKSEKUSI).
--   4. Jalankan STEP 3 (VALIDASI).
--   5. BARU buat rolling Della→Sunter (UI Checklist Positions). JANGAN sebelum
--      SQL — kalau Della buka halaman duluan, periode Sunter terbentuk &
--      re-point bentrok unique key (period_year, period_month, raw_branch_id).
--   6. Super Admin buka pettycash Sunter → SERAH UANG saldo kas cabang → dompet
--      Della. Della lanjut sebagai tamu; akhir rolling KEMBALIKAN sisa.
--   CATATAN: pengeluaran LAMA tetap "kas cabang Sunter"; hanya pengeluaran BARU
--            masuk dompet Della (histori terbagi — sesuai keputusan simpel).
--
-- Ganti placeholder <...> memakai hasil STEP 1. Bulan disesuaikan (contoh: 2026-8).
-- ============================================================================


-- ─────────────────────────────────────────────────────────────────────────
-- STEP 1 — VERIFIKASI (SELECT-only; JALANKAN DULU, jangan lanjut kalau janggal)
-- ─────────────────────────────────────────────────────────────────────────

-- 1a. Ambil id cabang Cinere & Sunter (catat untuk placeholder).
SELECT id, name FROM raw_branch WHERE name IN ('Cinere', 'Sunter');

-- 1b. Periode Cinere bulan ini — HARUS belum ditutup (is_closed = 0).
SELECT p.id AS cinere_period_id, p.period_year, p.period_month, p.raw_branch_id,
       p.is_closed, p.opening_balance, p.source_opening_balances
FROM petty_cash_periods p
JOIN raw_branch b ON b.id = p.raw_branch_id
WHERE b.name = 'Cinere' AND p.period_year = 2026 AND p.period_month = 8;

-- 1c. SEMUA transaksi periode Cinere itu + siapa pencatatnya — konfirmasi
--     semuanya Della (kalau ada nama lain → STOP, butuh pindah selektif).
SELECT t.id, t.trx_date, t.direction, t.amount, t.is_topup, t.is_transfer,
       t.holder_user_id, t.ref_no, u.nama AS pencatat, t.description
FROM petty_cash_transactions t
LEFT JOIN users u ON u.id = t.created_by
WHERE t.period_id = (
        SELECT p.id FROM petty_cash_periods p JOIN raw_branch b ON b.id = p.raw_branch_id
        WHERE b.name = 'Cinere' AND p.period_year = 2026 AND p.period_month = 8)
  AND t.deleted_at IS NULL
ORDER BY t.trx_date, t.id;

-- 1d. Periode Sunter bulan ini — HARUS belum ada. Kalau ADA & KOSONG (0 trx,
--     opening kosong) → hapus dulu (lihat 1e). Kalau ADA & BERISI → STOP, lapor.
SELECT p.id AS sunter_period_id, p.is_closed, p.opening_balance,
       (SELECT COUNT(*) FROM petty_cash_transactions t
        WHERE t.period_id = p.id AND t.deleted_at IS NULL) AS jml_trx
FROM petty_cash_periods p
JOIN raw_branch b ON b.id = p.raw_branch_id
WHERE b.name = 'Sunter' AND p.period_year = 2026 AND p.period_month = 8;

-- 1e. (HANYA bila 1d menghasilkan baris KOSONG) hapus periode Sunter kosong itu
--     supaya re-point tidak bentrok unique key. Isi <sunter_period_id> dari 1d.
--     DELETE FROM petty_cash_periods WHERE id = <sunter_period_id>;

-- 1f. Pengajuan top-up PENDING milik Della yang menunjuk Cinere (kalau ada,
--     batalkan lewat UI lalu ajukan ulang setelah rolling aktif → auto ke Sunter).
SELECT tr.* FROM topup_requests tr
JOIN raw_branch b ON b.id = tr.raw_branch_id
WHERE b.name = 'Cinere' AND tr.status = 'pending';


-- ─────────────────────────────────────────────────────────────────────────
-- STEP 2 — EKSEKUSI (atomic). Isi <cinere_aug_period_id> & <sunter_branch_id>
--          dari STEP 1. Guard is_closed=0 & raw_branch_id=<cinere_id> mencegah
--          eksekusi ke baris yang salah.
-- ─────────────────────────────────────────────────────────────────────────

START TRANSACTION;

UPDATE petty_cash_periods
SET raw_branch_id = '<sunter_branch_id>'
WHERE id = <cinere_aug_period_id>
  AND raw_branch_id = '<cinere_branch_id>'
  AND is_closed = 0;
-- Harus "Rows matched: 1  Changed: 1". Kalau 0 → periode sudah closed / id salah
-- → ROLLBACK; dan cek ulang.

COMMIT;


-- ─────────────────────────────────────────────────────────────────────────
-- STEP 3 — VALIDASI AKHIR (SELECT-only)
-- ─────────────────────────────────────────────────────────────────────────

-- 3a. Periode itu kini milik Sunter, jumlah transaksi sama dengan STEP 1c.
SELECT p.id, b.name AS cabang, p.period_year, p.period_month,
       (SELECT COUNT(*) FROM petty_cash_transactions t
        WHERE t.period_id = p.id AND t.deleted_at IS NULL) AS jml_trx
FROM petty_cash_periods p JOIN raw_branch b ON b.id = p.raw_branch_id
WHERE p.id = <cinere_aug_period_id>;

-- 3b. Sunter bulan ini sekarang punya periode berisi; Cinere bulan ini belum
--     ada (akan dibuat otomatis carry-forward saat halaman dibuka).
SELECT b.name, p.period_year, p.period_month, p.id
FROM petty_cash_periods p JOIN raw_branch b ON b.id = p.raw_branch_id
WHERE b.name IN ('Cinere', 'Sunter') AND p.period_year = 2026 AND p.period_month = 8;


-- ─────────────────────────────────────────────────────────────────────────
-- ROLLBACK (kalau perlu batal) — kembalikan periode ke Cinere.
--   Hapus dulu periode Cinere BARU yang mungkin sudah terbentuk (kosong) supaya
--   tidak bentrok unique key, lalu balikkan raw_branch_id.
-- ─────────────────────────────────────────────────────────────────────────
-- DELETE FROM petty_cash_periods
--   WHERE raw_branch_id = '<cinere_branch_id>' AND period_year = 2026
--     AND period_month = 8
--     AND NOT EXISTS (SELECT 1 FROM petty_cash_transactions t
--                     WHERE t.period_id = petty_cash_periods.id AND t.deleted_at IS NULL);
-- UPDATE petty_cash_periods SET raw_branch_id = '<cinere_branch_id>'
--   WHERE id = <cinere_aug_period_id> AND raw_branch_id = '<sunter_branch_id>';
