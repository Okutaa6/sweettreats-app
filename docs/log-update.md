# Log Update — jurnal milestone

> Entri terbaru selalu DI ATAS. Tiap entri ditulis ringkas & mandiri supaya
> bisa langsung di-copy ke alat task-management (Gemini) tanpa konteks tambahan.
> Potret utuh proyek: `status-proyek.md`. Jangan mengedit entri lama —
> file ini append-only.

---

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
