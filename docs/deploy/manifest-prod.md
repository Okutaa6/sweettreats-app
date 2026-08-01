# Manifest Naik PROD — apa saja yang belum ada di app.sweettreats.id

> Dibuat 2026-08-01 dari pemeriksaan langsung dev + prod + GitHub (read-only).
> Tujuan: **tidak ada yang ketinggalan** saat migrasi besar nanti.
> Runbook langkah-per-langkah absensi tetap di `deploy-absensi.md`; file ini
> adalah **daftar isi & peta cabang**, bukan pengganti runbook.
>
> ⚠️ Prod TIDAK disentuh saat dokumen ini dibuat. Semua di bawah = rencana.

## 1. Keadaan hari ini

| | Branch | Commit | Catatan |
|---|---|---|---|
| **PROD** | `main` | `b8b8f2d` (25 Jul) | cuma sampai hotfix reservasi |
| **DEV** | `feature/rop` | `9e7154d` (1 Agu) | ROP + kerjaan Reza, sudah diuji |
| **LOKAL** | `feature/absensi` + reskin **belum commit** | `df8b6cb` | masih testing |

`feature/rop` = **87 commit** di depan `main`. Isinya: absensi lengkap, absen
HP, Pusat Akun, jatah cuti, lembar gaji, checklist Reza, dashboard marketing,
cuci AC, dan ROP.

## 2. Inventaris cabang GitHub — mana yang ikut, mana yang tidak

**Sudah tergabung di `feature/rop`** (tidak perlu dipikirkan lagi):
`feature/absensi` · `dashboardmarketing-checklistdesign-monitoringAC-bugfixlainnya`
(Reza, 30 Jul) · `dashboard-login-update-dashboardmarketing` ·
`checklist-hierarki-version` · `waiting-list-bug` · `fix/affiliate-reward-counter` ·
`detail-pasien-dan-media-sosial`

**Belum tergabung — harus diputuskan satu per satu:**

| Cabang | Isi | Sikap yang disarankan |
|---|---|---|
| `main` (2 commit) | hotfix reservasi 25 Jul | Sudah **di-port** ke absensi (`5dcf0f2`) — isinya sama, tapi commit-nya beda. Saat merge ke main, **verifikasi hasil akhirnya**, jangan asal terima |
| `hotfix/sweet-mates-auth` | tambalan celah menu sweet-mates | **UTANG KEAMANAN** — sekarang prod aman hanya karena opcache lama. Sudah ikut di dalam `feature/rop`, tapi kalau prod besar tertunda lagi, naikkan branch ini sendiri |
| `fix/doctor-availability-cuti` | cuti vs jadwal khusus dokter | Belum pernah naik ke mana pun. Putuskan: ikut gerbong ini atau dibuang |
| `feature/accurate-integration` (9 commit) | modul Accurate lengkap + **`accurate:pull-glaccount`** | Di-pause, TAPI ROP butuh satu bagiannya (§4.3) |
| `feature/accurate-commands` | WIP push/pull | Pesan commitnya sendiri bilang **"BELUM SELESAI, jangan merge"** |
| `feature/project-tracker` | Project Tracker fase A | WIP, sengaja tidak di-merge |
| `chore/deploy-branch-switch` | perbaikan `deploy.sh` bisa ganti branch | Kecil & berguna — layak diambil kapan saja |
| `docs/notes`, `docs/git-workflow-reza` | dokumen | Opsional |
| `fitur/detail-pasien-…`, `fix/branch-target-step-nominal` | perbaikan kecil Reza | Cek dulu apakah isinya sudah terwakili di cabang Reza yang baru |

## 3. Kode: satu kali merge besar

Rencana: `feature/rop` → `main` → deploy prod. Sebelum itu **reskin Kehadiran
harus sudah commit** dan ikut masuk, kalau tidak halaman absensi di prod akan
tampil versi lama.

⚠️ **Aset prod tidak ikut `deploy.sh`.** `public_html` prod = salinan manual,
bukan symlink. Deploy yang menyentuh `public/assets/**` (ROP membawa
`rop.sweettreats.css` + `rop-foto.js`; Reza membawa `checklist.sweettreats.css`;
reskin membawa `zona.css`) **wajib** diikuti rsync — kalau lupa, semua halaman
baru tampil tanpa gaya.

## 4. Database prod — yang belum ada

Hasil pemeriksaan prod hari ini: tabel `att_*` **0**, tabel cuti **0**, tabel
`sys_ac*` **0**, `sys_checklist*` **8 (sudah ada)**, `audit_log` **sudah ada**.

### 4.1 SQL manual — 28 berkas, urut tanggal

Absensi (9): `2026_07_17_absensi_core` · `2026_07_18_absensi_work_rule` ·
`2026_07_18_absensi_day` · `2026_07_18_absensi_request` ·
`2026_07_18_absensi_approver` · `2026_07_18_absensi_zz_fk` ·
`2026_07_21_absensi_period` · `2026_07_21_absensi_exception_lebaran` ·
`2026_07_22_absensi_approval_rule`

Perbaikan & seed absensi (5): `2026_07_23_att_day_masa_depan` ·
`2026_07_23_att_day_yatim_akun_admin` · `2026_07_23_att_request_koreksi` ·
`2026_07_23_att_rule_akun_pegawai` · `2026_07_23_att_work_rule_seed`

Menu & lain-lain (4): `2026_07_22_checklist_content` ·
**`2026_07_23_finance_menu_restructure`** (bikin induk menu "Keuangan" —
**prod belum punya**, ROP & petty cash butuh ini) · `2026_07_23_fix_landing_sales` ·
`2026_07_23_menu_sweet_mates`

Absen HP (2): `2026_07_24_absen_hp_core` · `2026_07_24_absen_hp_access`

Pusat Akun (3): `2026_07_24_pusat_akun_user` · `2026_07_24_pusat_akun_access` ·
**`2026_07_24_pusat_akun_audit`** (prod sudah punya tabelnya, tetap jalankan —
berkasnya idempoten & sekalian membersihkan hash lama)

Cuti & gaji (3): `2026_07_25_cuti_core` · `2026_07_25_cuti_access` ·
`2026_07_25_lembar_gaji_access`

ROP (2): `2026_07_31_rop_core` · `2026_07_31_rop_access`

Menyusul: SQL WiFi dari reskin (`2026_07_31_wifi_ke_pusat_akun.sql`, masih
belum commit).

### 4.2 Migration — 19 berkas

Prod punya **5 pending lama** (checklist excuse/photo + konfirmasi posisi) +
**8 milik Reza** (foto before/after, unit AC, catatan servis, is_wash, indeks,
persetujuan) + sisanya. Di dev tadi `migrate --force` jalan mulus tanpa drift.
**Di prod cek dulu `migrate:status`** — jebakan baseline setengah-jalan pernah
terjadi (schema sudah ada tapi tercatat Pending → migrate berhenti di tengah).

### 4.3 ⚠️ Ganjalan khusus ROP: `accurate_staging_glaccount`

Tanpa tabel ini **Finance tidak bisa meng-ACC** — dropdown akun beban kosong.
Keadaan: dev punya 1.098 baris/7 buku; **prod tidak punya tabelnya sama sekali**.
Yang bikin repot: **DDL dan command penariknya tidak ada di garis keturunan
`feature/rop`** — keduanya hidup di `feature/accurate-integration` yang
di-pause (`database/sql/2026_06_12_accurate_tables.sql` dan
`app/Console/Commands/AccuratePullGlaccount.php`).

Tiga pilihan:
1. **Petik satu berkas** DDL + command itu ke gerbong prod, lalu jalankan
   `accurate:pull-glaccount --all` di prod (prod sudah punya 7 kredensial
   Accurate + `ACCURATE_*` di .env — modalnya lengkap). *Paling bersih.*
2. **Salin data dari dev**: dump tabel `accurate_staging_glaccount` lalu impor
   ke prod. Cepat, tapi datanya jadi beku dan tidak bisa disegarkan.
3. Tunda ROP sampai modul Accurate dilanjutkan — tidak disarankan, ROP jadi
   sandera proyek yang di-pause.

## 5. Langkah manual di luar SQL

1. **8 role baru**: Supervisor · Coordinator Dentist · Finance · IT · Head IT ·
   Front Office · Assistant Dentist · General Services. Prod baru punya 11 role,
   dev 18.
2. **29 akun karyawan** + pensiunkan 5-6 akun peran legacy (jangan di-rename —
   riwayat 483 reservasi ikut salah klaim).
3. **Grant akses BY LINK, bukan by id node** — node pembawa link berbeda antar
   lingkungan. SQL akses sudah ditulis begitu; jangan diakali.
4. `php artisan access:rebuild` **lalu** `cache:clear`, dan **user harus
   logout-login** supaya menu baru muncul.
5. `php artisan cuti:saldo-awal --terapkan` (di dev: 21/23; Yanwar Fauzi &
   Madalena belum punya akun).
6. `php artisan rop:tarik-vendor` — isi master vendor (dev: 73 vendor).
7. **Verifikasi TrustProxies** — premis "WiFi = saksi" pada absen HP dan
   pembatasan laju keamanan sama-sama bergantung pada IP asli yang benar.
8. **rsync aset** ke `public_html` (lihat §3).
9. Cek penjadwal (cron) hidup: `rop:sapu` 07:00 · `rop:tarik-vendor` Senin
   04:10 · `checklist:mark-missed`.

## 6. Urutan yang disarankan

1. Reskin selesai testing → commit → gabungkan ke `feature/rop`.
2. Selesaikan pilihan §4.3 (glaccount) dan putuskan `fix/doctor-availability-cuti`.
3. Uji sekali lagi di dev **sebagai satu kesatuan**.
4. Backup DB prod.
5. `feature/rop` → `main` (verifikasi 2 commit main yang di-port tidak hilang).
6. Deploy prod + SQL berurutan + migration + rebuild + rsync.
7. Verifikasi per peran: login, absensi, cuti, pengajuan ROP, antrian Finance.

## 7. Yang sengaja TIDAK ikut

Accurate (di-pause) · Project Tracker (WIP) · `feature/accurate-commands`
(ditandai belum selesai oleh pembuatnya) · template impor Accurate untuk ROP
(di-pause menunggu pembahasan dengan Finance — tombolnya sementara berlabel
"Draf untuk Accurate").
