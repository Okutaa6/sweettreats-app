# Log Update — jurnal milestone

> Entri terbaru selalu DI ATAS. Tiap entri ditulis ringkas & mandiri supaya
> bisa langsung di-copy ke alat task-management (Gemini) tanpa konteks tambahan.
> Potret utuh proyek: `status-proyek.md`. Jangan mengedit entri lama —
> file ini append-only.

---

## 2026-08-04 malam — Fitur Aturan Kerja SELESAI di lokal (halaman 3 tab)

- Halaman **Aturan Kerja** (`/absensi/aturan`, menu seksi Absensi) selesai
  dibangun & lolos uji di lokal — branch baru `feature/aturan-kerja` dari
  `feature/rop`. Tiga tab sesuai rencana:
  1. **Aturan** — daftar aturan (GS / FO-Asdent / Fleksibel) + tambah/ubah/
     hapus, editor jam per hari (libur, window masuk, pulang, mulai lembur).
     Ubah aturan yang dipakai orang → konfirmasi "berlaku untuk N karyawan
     mulai besok".
  2. **Penugasan** — tabel karyawan → aturan aktif; ganti aturan = baris baru
     ber-tanggal-mulai, baris lama ditutup otomatis (riwayat = jejak gaji,
     tidak pernah diedit). Plus **default per role** untuk akun baru
     (Pusat Akun otomatis pasang aturan saat akun dibuat).
  3. **Kalender Libur** — libur nasional/tanggal khusus (input manual SKB
     3 Menteri), mekanisme = baris exception pola SQL Lebaran, nol perubahan
     mesin. Libur ≠ cuti bersama (ada rambu + link ke halaman Cuti).
- Rel pengaman sesuai rencana: gate role di controller (Head IT + Finance +
  Super Admin), semua perubahan berlaku KE DEPAN saja (backdate ditolak),
  mesin rekonsiliasi TIDAK disentuh, tiap perubahan tercatat di log baru
  `att_rule_log` (siapa, kapan, dari→ke — tampil di bawah halaman).
- Uji: 28/28 skenario headless lulus (guard 403, CRUD aturan, tutup-baris-lama,
  engine baca aturan lama utk kemarin & baru utk hari ini, overlap libur
  ditolak, libur lampau terkunci) + smoke HTTP login Head IT → halaman render
  utuh dengan menu sidebar & JS aktif.
- Audit putaran kedua (sesi sama) menemukan & memperbaiki 5 hal: (1) KRITIS —
  section JS salah nama (`script` vs yield layout `js`) bikin seluruh JS
  halaman mati; (2) hapus aturan meninggalkan default-role menggantung →
  bikin AKUN BARU bisa 500, kini default ikut dilepas + hook Pusat Akun
  defensif; (3) nama aturan/libur tak lagi diinterpolasi ke JS confirm
  (celah self-XSS); (4) old() dibatasi ke form create saja (gagal-validasi
  satu form tadinya menulari nilai semua editor — bahaya rename tak sengaja);
  (5) minor: atribut `data-toggle` diganti `data-buka` (hindari data-API
  Bootstrap), colspan empty-state log, log rule.ubah simetris dari↔ke.
- Deploy nanti butuh: `database/sql/2026_08_04_aturan_kerja.sql` +
  `php artisan access:rebuild`. BELUM di-commit — menunggu review Ekky.

## 2026-08-04 malam — Deploy prod ditunda menunggu fitur Aturan Kerja; demo besok

- Keputusan Ekky: deploy prod MENUNGGU fitur Aturan Kerja selesai — fitur itu
  naik prioritas jadi dikerjakan sekarang dan ikut gerbong rilis.
- Besok (2026-08-05) demo di office/ABG + cabang Bintaro memakai dev — data
  demo absensi/cuti/ROP sudah terisi.
- Hotfix keamanan sweet-mates tidak ikut ditunda; tetap siap naik duluan.
- Aturan Kerja dibangun TANPA brief desain claude.ai/design — halaman setting
  internal, mengikuti pola desain yang sudah ada; hanya komponen editor
  jam-per-hari yang baru.

## 2026-08-04 — Paket "Pengaturan Operasional" di-ACC: 2 fitur, 2 pemilik

- Dua fitur baru direncanakan & di-ACC Ekky, dikerjakan setelah prod stabil:
  1. **Aturan Kerja (Ekky)** — UI kelola jam kerja, penugasan aturan per
     karyawan (default per role), kalender libur nasional. Selama ini semua
     itu harus lewat SQL. Rencana: `docs/rencana/rencana-aturan-kerja.md`.
  2. **Penempatan (Reza)** — staf rolling/backup ke cabang lain tercatat
     resmi; checklist otomatis ikut cabang penempatan (selama ini salah
     gedung); Nissa & Irma boleh menempatkan staf mana pun di bawah bagan
     (lintas domain). Rencana: `docs/rencana/rencana-penempatan.md`.
- Keputusan kunci: cabang asal karyawan TIDAK PERNAH diubah untuk rolling;
  penempatan wajib bertanggal akhir; jam kerja tidak ikut penempatan.
- Prompt chat build Aturan Kerja siap di `docs/spec/prompt-chat-baru.md` §E.
  Rencana Penempatan siap diserahkan ke Reza (basis branch `feature/rop`).

## 2026-08-04 — Dev diisi data demo + fix grup cabang Anisa/Irma

- Dev diisi data dummy untuk demo: 943 hari-kerja absensi (1 Jul–4 Agu, 27
  karyawan, pola realistis: hadir/telat/alpa/libur-Senin), 10 pengajuan
  cuti/izin/WFH/lembur approved, 7 potongan saldo cuti, dan 14 pengajuan ROP
  lintas status (menunggu ACC s/d selesai, termasuk 1 urgent, 1 ditolak,
  1 kadaluarsa). Halaman Kehadiran, Cuti, antrian ROP, dan Rekap sekarang
  hidup untuk demo.
- Penanda dummy: att_day rentang 1 Jul–4 Agu; att_request & mutasi cuti
  berawalan "[DEMO]" — gampang dibersihkan saat mau data asli.
- Fix data: Anisa & Irma tadinya nongol "Belum ada cabang" di kalender —
  kolom cabang mereka kosong. Diisi Sunter (cabang asal sesuai roster).
  Catatan: mereka TIDAK masuk grup "Kantor (ABG)" karena grup itu khusus
  aturan jam-bebas (tak dinilai), sedangkan mereka dinilai jam FO-Asdent.

## 2026-08-03 — Push Reza ke-2 masuk + bug 500 checklist difix

- Kerjaan baru Reza (export Excel checklist harian/mingguan + pengecualian
  per-pertanyaan) di-merge ke branch integrasi `feature/rop`, nol konflik,
  sudah live & diuji di dev.
- Bug dari kerjaan itu ditemukan & difix: halaman /checklist error 500 karena
  `@if` Blade ditulis menempel kata (`tugas@if`). Dev hijau lagi, log 0 error.
- Catatan deploy prod nambah 1: SQL checklist Reza wajib dijalankan via mysql
  CLI (pakai DELIMITER), bukan lewat tinker.
- Dibuat `docs/status-proyek.md` (potret utuh proyek) + file log ini.
- Status gerbong prod: `release/2026-08-pengeluaran` ikut maju, isi lengkap,
  masih masa uji dev. Deploy prod direncanakan minggu ini.

## 2026-08-01 sore — Ganjalan terakhir gerbong ditutup (glaccount)

- DDL Accurate + command `accurate:pull-glaccount` dipetik dari proyek
  Accurate (yang di-pause) ke gerbong rilis — tanpa ini Finance tidak bisa
  memilih akun beban saat meng-ACC pengajuan di prod.
- Dibuktikan di dev: 6 buku Accurate tertarik (135–183 akun per buku).
- Gerbong prod dinyatakan LENGKAP secara teknis. Keputusan menyusul yang
  disepakati: hotfix sweet-mates naik prod DULUAN sendirian; PR gerbong
  dibuka nanti saat isinya beku (bukan sekarang).

## 2026-08-01 — Semua kerjaan menyatu di satu branch + rilis dipotong

- Reskin zona Absensi (Kehadiran kalender, tema per zona, emoji→ikon) selesai
  testing → di-commit → digabung ke `feature/rop`. Konflik dengan kerjaan
  Reza ternyata sepele (2 emoji), selesai.
- Fix jadwal-khusus-dokter-vs-cuti ikut gerbong (keputusan Ekky).
- Branch rilis `release/2026-08-pengeluaran` dipotong (±91 commit, ±298
  berkas) — kandidat PR ke main.
- Manifest deploy prod dirapikan: 30 SQL urut, 19 migration, 8 role + 29
  akun, gotcha rsync aset. Rujukan: `docs/deploy/manifest-prod.md`.
- Aturan kerja baru disepakati: SEMUA kerjaan bermuara ke `feature/rop`;
  Reza cabang dari sana; tidak ada branch lompat langsung ke main.

## 2026-08-01 pagi — ROP live di dev, digabung dengan kerjaan Reza

- Fitur Pengajuan/ROP fase 1 (pengganti Formulir A kertas) selesai dibangun,
  diaudit (7 bug internal difix), dan di-deploy ke dev bersama kerjaan Reza
  (cuci AC, foto checklist, dashboard marketing).
- Master vendor terisi otomatis dari Accurate (73 vendor).
- Gerbang peran terverifikasi: Finance & Head IT bisa buka antrian; Supervisor
  bisa mengajukan tapi tidak bisa ACC; staf biasa tidak bisa mengajukan.
- Ditemukan & dipetakan: 2 dependensi prod baru (tabel akun Accurate + menu
  induk "Keuangan") — masuk manifest.
