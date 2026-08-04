# Rencana: Aturan Kerja (jam kerja, penugasan aturan, kalender libur)

> Status: ACC 2026-08-04 — **dikerjakan EKKY** di chat build terpisah
> (prompt: `docs/spec/prompt-chat-baru.md` §E). Pasangannya: fitur
> **Penempatan** dikerjakan Reza (`rencana-penempatan.md`).
> Prioritas (REVISI 2026-08-04 malam): **dikerjakan SEKARANG — gerbong prod
> MENUNGGU fitur ini selesai.** Demo ke office/ABG + Bintaro 2026-08-05
> pakai dev. Desain: TANPA brief claude.ai/design — ikuti pola halaman
> setting yang ada (Pusat Akun / WiFi Cabang); hanya editor jam-per-hari
> yang komponen baru.

## 1. Masalah

Mesin absensi sudah data-driven — jam kerja hidup di `att_work_rule` +
`att_work_rule_day`, pengecualian tanggal di `att_work_rule_exception`
(dipakai Lebaran), penugasan per orang di `att_user_rule` (bertanggal).
**Yang hardcode itu pintunya**: tiap ubah jam kerja, tambah libur nasional,
atau menugaskan aturan ke karyawan baru = SQL manual lewat chat. Fitur ini =
UI di atas data yang sudah ada. **JANGAN merombak mesin rekonsiliasi.**

## 2. Bentuk: satu halaman "Aturan Kerja", 3 tab (zona Absensi cokelat, desktop)

### Tab 1 — Aturan
- Daftar aturan (sekarang: GS · FO-Asdent · Fleksibel) + CRUD.
- Per aturan: nama, tipe (`baku`/`fleksibel`), dan per hari-dalam-minggu:
  libur?, jam masuk awal–akhir, jam pulang, jam mulai lembur
  (= isi `att_work_rule_day`, day 0=Minggu; contoh nyata: GS & FO-Asdent
  libur Senin).
- Fleksibel tidak punya baris jam (jam bebas, scan tak dinilai).

### Tab 2 — Penugasan
- Tabel karyawan → aturan aktif (dari `att_user_rule` yang efektif hari ini).
- Ganti aturan = **baris baru dengan `effective_from`** (dan `effective_to`
  baris lama diisi) — BUKAN update baris lama. Riwayat = jejak gaji.
- Default per role saat akun baru dibuat (mis. Front Office → FO-Asdent),
  override per orang tetap bisa (kasus Anisa/Irma: role kantor, aturan baku).

### Tab 3 — Kalender Libur
- Libur nasional & tanggal khusus, input manual (SKB 3 menteri tiap tahun,
  JANGAN auto-API).
- Mekanisme = tulis ke `att_work_rule_exception` untuk SEMUA aturan baku
  sekaligus (persis pola SQL Lebaran `2026_07_21_absensi_exception_lebaran.sql`)
  → nol perubahan mesin.
- **Libur nasional ≠ cuti bersama**: libur tak memotong saldo; cuti bersama
  memotong dan SUDAH ADA di halaman Cuti Finance — tab ini cukup kasih link,
  jangan bikin dobel.

## 3. Aturan main (default usulan — Ekky boleh ubah)

- **Siapa boleh ubah**: Head IT + Finance (+ Super Admin). Ini menentukan
  gaji — bukan setting sembarangan. Gate via role di controller (jangan
  andalkan `hasMenuAccess` — cuma nemu node level 3).
- **Berlaku ke depan saja**: `att_day` itu materialized (telat_menit,
  wajib_pulang tersimpan) — masa lalu beku. Setelah ubah aturan JANGAN
  re-reconcile massal ke belakang; koreksi masa lalu tetap lewat pengajuan
  koreksi yang sudah ada.
- Ubah jam pada aturan yang dipakai banyak orang → tampilkan konfirmasi
  "berlaku untuk N karyawan mulai besok".
- Setiap perubahan dicatat (siapa, kapan, dari→ke) — minimal tabel log kecil
  sendiri, jangan numpang audit_log login.

## 4. Teknis

- Branch: `feature/aturan-kerja` dari `feature/rop` TERBARU.
- DB: TIDAK ada tabel baru wajib (kecuali log perubahan kecil). SQL akses
  menu manual (`database/sql/`), grant BY LINK, `access:rebuild`.
- Reconcile membaca aturan saat compute — sudah benar, jangan disentuh.
- Menu: seksi Absensi. Perlu dipikirkan: halaman ini vs `absensi/wifi`
  (pola serupa: config operasional di zona berbeda) — konsisten saja.

## 5. Di luar scope v1

Split-shift, aturan per-cabang (aturan tetap nempel ke ORANG — keputusan
desain lama yang benar), auto-import kalender libur, re-reconcile massal.
