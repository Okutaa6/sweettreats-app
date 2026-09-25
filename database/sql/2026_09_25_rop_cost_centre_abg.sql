-- ════════════════════════════════════════════════════════════════════════════
-- 2026_09_25_rop_cost_centre_abg.sql
-- Gabungkan empat cost centre kantor (IT/MKT/FIN/HQ) menjadi satu: ABG.
-- Permintaan Finance, 25 September 2026. SUDAH DIJALANKAN di dev dan prod.
--
-- ABG = Anugerah Berkah Group, perusahaan induk yang menaungi RB Las dan
-- Sweet Treats Dental Clinic.
--
-- YANG DILAKUKAN
--   1. Tambah cost centre `ABG` (tipe kantor, buku Accurate `holding`).
--   2. Nonaktifkan IT/MKT/FIN/HQ — hilang dari dropdown, riwayat tetap utuh.
--
-- ⛔ YANG SENGAJA TIDAK DILAKUKAN: menulis ulang `rop_requests.cost_centre`.
--    Kode cost centre tercetak DI DALAM nomor ROP — diverifikasi di prod:
--    `rop_requests.number` berisi mis. `ROP/FIN/2608/004`. Nomor itu sudah
--    beredar di lembar bayar. Mengubah kolom `cost_centre` membuat nomor dan
--    isinya saling bertentangan. Pengajuan lama tetap memakai kode aslinya.
--
-- DAMPAK TERUKUR (prod, 25 Sep 2026, sebelum eksekusi)
--   • 31 pengajuan memakai IT/MKT/FIN/HQ (FIN 20 · HQ 5 · IT 4 · MKT 2).
--     30 hidup + 1 soft-deleted (id 175, HQ, `diajukan`, dihapus 24 Sep 14:57).
--   • 26 pengajuan hidup BELUM berstatus `selesai` (27 termasuk yang dihapus).
--     Aman: validasi `aktif=1` HANYA ada di PengajuanController@store.
--     Aksi Finance (setujui/tolak/bayar/urungkan) dan @selesai membaca
--     `$rop->cost_centre` langsung tanpa memeriksa `aktif`, dan RopCoaCatalog
--     memakai `find()` bukan scope `aktif()`. Diuji di prod: keempat kode lama
--     tetap mengembalikan 53 akun beban, jadi Finance masih bisa menyetujui.
--   • Keempatnya SUDAH memakai buku Accurate yang sama (`holding`), jadi ABG
--     tidak memindahkan apa pun secara akuntansi.
--
-- ⚠️ KONSEKUENSI YANG DITERIMA SADAR
--    Dropdown FILTER di antrean Finance (RopQueueController:111) dan rekap
--    (RopRekapController:59) juga memakai scope `aktif()`. Sesudah ini riwayat
--    31 pengajuan tetap TERLIHAT di daftar, tapi tak bisa lagi disaring per
--    departemen. Kalau Finance membutuhkannya saat tutup buku, perbaikannya
--    ubah kode: tampilkan cost centre nonaktif di dropdown FILTER saja, jangan
--    di form pengajuan.
--
-- Idempotent: aman dijalankan ulang (diuji — INSERT IGNORE tak menggandakan,
-- UPDATE ke-2 menyentuh 0 baris).
-- ════════════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO `rop_cost_centre`
  (`kode`, `nama`, `tipe`, `raw_branch_id`, `accurate_slug`, `urutan`, `aktif`, `created_at`, `updated_at`)
VALUES
  ('ABG', 'ABG (Anugerah Berkah Group)', 'kantor', NULL, 'holding', 6, 1, NOW(), NOW());

UPDATE `rop_cost_centre`
   SET `aktif` = 0, `updated_at` = NOW()
 WHERE `kode` IN ('IT', 'MKT', 'FIN', 'HQ')
   AND `aktif` = 1;

-- Verifikasi: harus 5 cabang + ABG aktif, dan 4 kantor lama aktif=0.
-- SELECT kode, nama, tipe, accurate_slug, aktif, urutan
--   FROM rop_cost_centre ORDER BY aktif DESC, urutan;

-- ─────────────────────────────────────────────────────────────────────────────
-- PEMBALIKAN (kalau Finance berubah pikiran) — keadaan prod sebelum eksekusi:
--   UPDATE rop_cost_centre SET aktif = 1
--    WHERE kode IN ('IT','MKT','FIN','HQ');
--   UPDATE rop_cost_centre SET aktif = 0 WHERE kode = 'ABG';
-- Jangan HAPUS baris ABG kalau sudah ada pengajuan memakainya — nomornya
-- terlanjur tercetak, sama alasannya dengan keempat kode di atas.
-- ─────────────────────────────────────────────────────────────────────────────
