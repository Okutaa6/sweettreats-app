-- ═══════════════════════════════════════════════════════════════════════════
-- Aturan Sakit — jenis pengajuan & status harian "sakit"
--
-- Sebab: sakit TIDAK PUNYA TEMPAT di sistem. Orang sakit yang tak mengajukan
-- apa pun otomatis jadi ALPA (kejadian nyata: Asih Wulan Sari, 5 Agu 2026).
-- Menutupnya pakai 'izin' memang mempan, tapi menulis alasan yang SALAH ke
-- catatan permanen orang — dan sekali tercatat begitu, bedanya hilang selamanya.
--
-- Keputusan Ekky 2026-08-11:
--   • Surat dokter WAJIB mulai 3 hari berturut ke atas; 1–2 hari cukup kabar.
--   • Sakit DIBAYAR PENUH — tak ada logika potongan di mana pun.
--   • Persetujuan SATU lapis (sakit mendesak; sering diajukan atas nama).
--   • Sakit TIDAK memotong jatah cuti (att_cuti_mutasi sengaja tak disentuh).
--
-- Idempoten: aman dijalankan ulang — enum di-set ke bentuk akhirnya, bukan
-- ditambahi. Tak ada data yang berubah, cuma daftar nilai yang boleh.
-- ═══════════════════════════════════════════════════════════════════════════

-- 1) Jenis pengajuan. Urutan menyusul yang sudah ada; 'sakit' ditaruh sesudah
--    'izin' karena kekerabatannya paling dekat (dua-duanya ketidakhadiran
--    berizin), memudahkan mata saat membaca enum.
ALTER TABLE `att_request`
  MODIFY `tipe` enum('cuti','izin','sakit','libur','lembur','wfh','koreksi','tukar') NOT NULL;

-- 2) Status harian hasil reconcile. Reconciler generik: pengajuan absen yang
--    DISETUJUI menimpa hasil scan, jadi 'sakit' otomatis mengalahkan 'alpa'
--    tanpa satu baris pun logika baru — asal nilainya boleh ada di sini.
ALTER TABLE `att_day`
  MODIFY `status` enum('hadir','telat','alpa','cuti','izin','sakit','libur','wfh') NOT NULL;

-- ── Catatan pemakaian ────────────────────────────────────────────────────────
-- Setelah SQL ini jalan, alpa lama yang sebenarnya sakit TIDAK berubah sendiri.
-- Perbaikannya lewat alur resmi: karyawan (atau atasannya) mengajukan Sakit
-- untuk tanggal itu → disetujui → `php artisan att:reconcile <tgl> <tgl>`
-- menimpa 'alpa' jadi 'sakit'. Sengaja begitu: koreksi kehadiran harus punya
-- pengaju, penyetuju, dan jejaknya — bukan UPDATE diam-diam dari SQL.
-- Tunggakan per 2026-08-11: Asih Wulan Sari 5 Agu (sakit, terkonfirmasi Ekky)
-- dan Irma Islamiati 5 Agu (sebabnya BELUM dipastikan — jangan asal ditutup).
