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
| **DEV** | `feature/rop` | `253c02e` (4 Agu) | gerbong LENGKAP + Aturan Kerja, teruji |
| **Calon rilis** | `release/2026-08-pengeluaran` | `253c02e` | selalu disamakan dgn feature/rop, ini yang di-PR ke `main` |

Gerbong = **91 commit / 298 berkas** di depan `main`. Isinya: absensi lengkap,
absen HP, Pusat Akun, jatah cuti, lembar gaji, checklist + cuci AC (Reza),
dashboard marketing & login baru, reskin zona/peta tema, ROP, dan fix jadwal
khusus dokter.

Semua sudah menyatu **2026-08-01**: reskin di-commit (`2d7f03a`) lalu
digabung, `fix/doctor-availability-cuti` ikut, dan dev diuji ulang sebagai
satu kesatuan (log bersih, 0 error).

## 2. Inventaris cabang GitHub — mana yang ikut, mana yang tidak

**Sudah tergabung** (tidak perlu dipikirkan lagi): `feature/absensi` (termasuk
reskin) · `dashboardmarketing-checklistdesign-monitoringAC-bugfixlainnya`
(Reza, 30 Jul) · `dashboard-login-update-dashboardmarketing` ·
`checklist-hierarki-version` · `waiting-list-bug` · `fix/affiliate-reward-counter` ·
`detail-pasien-dan-media-sosial` · **`fix/doctor-availability-cuti`** (ikut
gerbong atas keputusan user 2026-08-01)

**Cara memeriksa ulang sebelum memotong rilis** (jangan mengandalkan ingatan):

```
for b in $(git for-each-ref --format='%(refname:short)' refs/remotes/origin | grep -v HEAD); do
  n=$(git log --oneline release/2026-08-pengeluaran..$b | wc -l)
  [ "$n" -gt 0 ] && echo "$b: $n commit belum masuk"
done
```

**Belum tergabung — harus diputuskan satu per satu:**

| Cabang | Isi | Sikap yang disarankan |
|---|---|---|
| `main` (2 commit) | hotfix reservasi 25 Jul | Sudah **di-port** ke absensi (`5dcf0f2`) — isinya sama, tapi commit-nya beda. Saat merge ke main, **verifikasi hasil akhirnya**, jangan asal terima |
| `hotfix/sweet-mates-auth` | tambalan celah menu sweet-mates | **UTANG KEAMANAN** — sekarang prod aman hanya karena opcache lama. Sudah ikut di dalam `feature/rop`, tapi kalau prod besar tertunda lagi, naikkan branch ini sendiri |
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

### 4.1 SQL manual — 32 berkas, urut tanggal

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

Reskin (1): `2026_07_31_wifi_ke_pusat_akun` (WiFi Cabang pindah ke zona IT)

Accurate untuk ROP (1): `2026_06_12_accurate_tables` — lihat §4.3. Urutkan
**sebelum** `2026_07_31_rop_access`, karena dropdown akun bergantung padanya.

ROP (2): `2026_07_31_rop_core` · `2026_07_31_rop_access`

Checklist Reza (1): `2026_08_01_checklist_item_exception` — ⚠️ WAJIB via
**mysql CLI** (pakai DELIMITER; gagal lewat tinker/DB::unprepared).

Aturan Kerja (1): `2026_08_04_aturan_kerja` — node menu + kolom default rule
+ tabel att_rule_log; jalankan PALING AKHIR (butuh parent menu Absensi dan
role sudah ada). Grant: Supervisor + Head IT + Finance + Super Admin.

### 4.2 Migration — 19 berkas

Prod punya **5 pending lama** (checklist excuse/photo + konfirmasi posisi) +
**8 milik Reza** (foto before/after, unit AC, catatan servis, is_wash, indeks,
persetujuan) + sisanya. Di dev tadi `migrate --force` jalan mulus tanpa drift.
**Di prod cek dulu `migrate:status`** — jebakan baseline setengah-jalan pernah
terjadi (schema sudah ada tapi tercatat Pending → migrate berhenti di tengah).

### 4.3 ✅ `accurate_staging_glaccount` — SUDAH DIBERESKAN (2026-08-01)

Tanpa tabel ini **Finance tidak bisa meng-ACC** karena dropdown akun beban
kosong. Prod belum punya tabelnya, dan DDL + command penariknya hidup di
`feature/accurate-integration` yang di-pause.

**Keputusan user: opsi "petik berkas".** Dua berkas sudah dibawa masuk ke
gerbong apa adanya (commit `4728fcd`) supaya tidak bercabang dari proyek
Accurate saat nanti dilanjutkan:

- `database/sql/2026_06_12_accurate_tables.sql` — idempoten. Seed kredensialnya
  `ON DUPLICATE KEY UPDATE` **hanya metadata**; kolom `api_token` tidak
  disentuh, jadi 7 token yang sudah ada di prod aman.
- `app/Console/Commands/AccuratePullGlaccount.php`

**Langkah prod**: jalankan DDL di atas → `php artisan accurate:pull-glaccount --all`.
Prod sudah punya 7 kredensial + `ACCURATE_*` di .env, jadi modalnya lengkap.
ROP hanya butuh 2 dari 7 tabel DDL itu (`accurate_staging_glaccount` +
`accurate_sync_log`); sisanya menyusul kosong dan tidak mengganggu.

**Sudah dibuktikan di dev**: command jalan, 6 buku tertarik (bintaro 183,
cinere 181, holding 135, kalideres 176, pamulang 180, sunter 180 akun;
53–62 akun beban per buku). Buku `testing` gagal — kredensial sandbox-nya
memang invalid, sama seperti pada `rop:tarik-vendor`, dan tidak dipakai ROP.

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

**Kiriman 1 — `hotfix/sweet-mates-auth` sendirian, secepatnya.** 2 berkas
(6 baris route + 1 SQL menu), tanpa migrasi data. Alasannya: route rentannya
**sudah ada di prod sekarang**, tertutup hanya karena opcache menyajikan
bytecode lama — dan deploy besar justru akan me-reset opcache. Selama gerbong
belum jalan, satu kali restart PHP-FPM saja sudah membuka halaman member
KOL/KOC + komisi tanpa login.

**Kiriman 2 — gerbong besar:**

1. ~~Reskin commit & gabung~~ — SELESAI 2026-08-01.
2. ~~Ganjalan glaccount (§4.3)~~ — SELESAI 2026-08-01, terbukti jalan di dev.
3. Backup DB prod.
4. PR `release/2026-08-pengeluaran` → `main` (verifikasi 2 commit main yang
   sudah di-port tidak hilang).
5. Deploy prod + SQL berurutan + migration + `access:rebuild` + `cache:clear`
   + **rsync aset**.
6. Role & akun (§5), lalu `cuti:saldo-awal --terapkan` dan `rop:tarik-vendor`.
7. Verifikasi per peran — pola yang dipakai di dev: Finance & Head IT membuka
   antrian ROP, Supervisor bisa mengajukan tapi ditolak di antrian, Front
   Office tidak bisa mengajukan.

## 6a. Catatan gerbang akses yang mudah bikin salah sangka

Beberapa halaman **tidak** dijaga node menu, melainkan helper di controller:
`/absensi/kehadiran` dan `/finance/pettycash` sama-sama memakai
`canSeeAllBranches()`. Akibatnya **Head IT/IT dapat 403 di sana walau mereka
admin** — ini perilaku lama, bukan kerusakan. Jangan "memperbaiki"-nya dengan
menambah grant menu; kalau memang mau dibuka, ubah helper/guard-nya sadar-sadar.
Halaman `absensi/kehadiran` juga memang **tidak punya node menu** di dev.

## 7. Yang sengaja TIDAK ikut

Accurate (di-pause) · Project Tracker (WIP) · `feature/accurate-commands`
(ditandai belum selesai oleh pembuatnya) · template impor Accurate untuk ROP
(di-pause menunggu pembahasan dengan Finance — tombolnya sementara berlabel
"Draf untuk Accurate").
