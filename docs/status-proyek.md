# Status Proyek — Data Center SweetTreats (app.sweettreats.id)

> **Untuk siapa file ini**: alat task-management di luar chat teknis (mis.
> Gemini) dan siapa pun yang butuh gambaran utuh tanpa akses ke memori chat.
> Teknis & coding dikerjakan di chat Claude; file ini adalah **potret proyek**:
> apa yang sudah, sedang, dan akan dikerjakan, plus utang yang belum dibayar.
>
> **Terakhir diperbarui: 2026-08-05 — batch "rapi-rapi pasca-launch" naik prod,
> lalu dua ronde perbaikan akses: (1) 6 orang tak bisa absen & rantai
> persetujuan putus di Head Marketing/Executive; (2) akar semua "menu hilang"
> ketemu — prod menyimpan 162 salinan berkas basi yang membajak
> `access:rebuild`, sudah disingkirkan. Menu Anisa & Irma yang di-ACC 5 Agu kini
> benar-benar muncul. Prod = `main` @ `6fc928b`; keempat branch sejajar.
> Pengguna perlu logout-login sekali supaya menu barunya muncul.**
> Kalau tanggal ini sudah jauh lewat,
> minta chat teknis menyegarkan file ini sebelum dipakai mengambil keputusan.

## 1. Apa aplikasi ini

CMS internal klinik gigi **Sweet Treats** (5 cabang: Bintaro/pusat, Sunter,
Kalideres, Pamulang, Cinere; ±29 karyawan + 7 staf kantor). Laravel + MariaDB,
Bootstrap 4, FontAwesome 5.8.2. Fungsinya berkembang dari CRM/reservasi menjadi
"Data Center": satu pintu untuk reservasi, pasien, dokter, penjualan
(tarik-otomatis dari vendor Omnicare), pengeluaran, absensi, cuti, gaji,
checklist operasional, dan integrasi akunting (Accurate).

**Lingkungan:**
| Env | URL | Branch git | Keadaan |
|---|---|---|---|
| Prod | app.sweettreats.id | `main` (`6fc928b`, 5 Agu) | GERBONG LIVE + batch rapi-rapi + fix akses menu (personal, Anisa/Irma, berkas basi disingkirkan) |
| Dev | dev.sweettreats.id | `feature/rapi-pasca-launch` (= main) | = main + data demo |
| Lokal | 2 working tree (utama + worktree rop) | `feature/absensi` (pensiun) / `feature/rop` | — |

**Orang-orang:**
- **Ekky** (= Muhamad Ikhsan, Head IT) — pemilik keputusan, bisa mengubah SOP.
- **Reza** (IT) — developer kedua; kerjaannya masuk lewat branch sendiri lalu
  di-merge ke branch integrasi.
- **Dani** (Executive), **Della** (Finance), **Anisa/Nissa** (Supervisor),
  **Irma** (Coordinator Dentist), **Yovanka** (Head Marketing).

**Aturan kerja repo (disepakati 2026-08-01):**
- Semua kerjaan bermuara ke **`feature/rop`** (branch integrasi). Tidak ada
  cabang yang lompat langsung ke `main`.
- `release/2026-08-pengeluaran` = kandidat rilis, hanya dimajukan saat mau
  deploy. PR ke `main` dibuka **saat isinya beku**, bukan di awal.
- Prod tidak pernah disentuh tanpa perintah eksplisit; dev bebas.
- DB pakai SQL manual di `database/sql/` (bukan migration), KECUALI kerjaan
  Reza yang pakai migration — dua-duanya harus dijalankan saat deploy.

## 2. FOKUS SEKARANG: stabilisasi prod pasca-gerbong (deploy SELESAI 2026-08-04)

> Gerbong SUDAH NAIK — lihat entri log-update 2026-08-04 tengah malam.
> Sisa: cek cron hPanel `schedule:run`, setup divisi checklist role baru
> (Reza), buat 14 akun karyawan sisanya via Pusat Akun, kabari kredensial
> (Yovanka-Alif-Talitha email berubah), demo 2026-08-05 office+Bintaro.
>
> **Batch "rapi-rapi pasca-launch" (ACC 2026-08-05): 4 item LIVE DI DEV**
> — (1) sidebar absensi pintu tunggal "Kehadiran", (2) absen HP digerbang di
> desktop, (3) persetujuan absensi 1 lapis + `absensi:selesaikan-l2`,
> (4) sidebar marketing 3 kelompok + fix typo Referral. Sudah lewat audit
> adversarial (1 bug SQL lintas-server difix sebelum naik) dan verifikasi
> 41 halaman per peran — semua 200, log bersih.
> **Dev berjalan di branch `feature/rapi-pasca-launch`** (= feature/rop + 4
> item ini); penggabungan ke `feature/rop` belum dilakukan karena worktree-nya
> dipakai chat lain. **Prod: menunggu Ekky, chat terpisah** — urutannya 2 SQL
> → access:rebuild → absensi:selesaikan-l2 → cek role Executive ada
> penghuninya. Detail: entri log-update 2026-08-05.

### Arsip rencana (sudah terlaksana):

> **REVISI 2026-08-04 malam**: deploy prod DITUNDA sampai fitur **Aturan
> Kerja** selesai (dikerjakan Ekky sekarang, masuk gerbong). Besok
> **2026-08-05: demo di office/ABG + cabang Bintaro** memakai dev — data
> demo sudah terisi (absensi Juli–Agustus, cuti/lembur, 14 ROP).
> Hotfix sweet-mates (kiriman 1) TIDAK ikut ditunda — tetap bisa naik
> duluan kapan pun Ekky bilang "prod".

## 2a. Rencana deploy prod (setelah Aturan Kerja selesai)

Rujukan wajib: **`docs/deploy/manifest-prod.md`** (daftar centang lengkap:
32 SQL urut, 19 migration, 8 role baru + 29 akun, rsync aset, verifikasi
per peran). Jangan deploy dari ingatan.

Dua kiriman, urut:
1. **Hotfix sweet-mates** (branch `hotfix/sweet-mates-auth`, 2 berkas) —
   duluan & sendirian. Celah: halaman member KOL/KOC + komisi bisa terbuka
   tanpa login; sekarang cuma tertutup karena opcache lama, dan deploy besar
   justru me-reset opcache. **Status: siap, belum dieksekusi.**
2. **Gerbong besar** `release/2026-08-pengeluaran` → `main` (±95 commit,
   ±300 berkas): absensi lengkap + absen HP selfie + Pusat Akun + jatah cuti +
   lembar gaji + checklist & cuci AC (Reza) + dashboard marketing + login baru +
   reskin tema/zona + ROP + fix jadwal dokter. **Status: lengkap di dev,
   sedang masa uji.**

Sisa pekerjaan sebelum gas: selesaikan masa uji dev (Ekky), buka PR, ikuti
manifest langkah demi langkah. Gotcha yang sering kelupaan: SQL checklist Reza
(`2026_08_01_checklist_item_exception.sql`) **harus lewat mysql CLI** (pakai
DELIMITER, gagal via tinker); DDL Accurate harus jalan **sebelum**
`2026_07_31_rop_access`; aset publik wajib **rsync** (public_html prod = copy,
bukan symlink).

## 3. Peta status per modul

### Sudah LIVE di prod (jalan sehari-hari)
- **Reservasi + drawer** (dgn hotfix anti-tertimpa 25 Jul), pasien, dokter,
  cabang, keluhan, waiting list.
- **Sales/penjualan dari Omnicare** — tarik otomatis harian, 5 cabang,
  dashboard sales + executive report. Angka terbukti cocok dengan Accurate
  sampai rupiah.
- **Marketing arm**: editorial plan, kode referral, komisi, affiliate (Behel),
  sweet mates, KOL/KOC.
- **Checklist harian/mingguan** versi awal (8 tabel sudah ada di prod).

### Selesai & teruji di DEV — ikut gerbong minggu ini
- **Absensi**: mesin rekonsiliasi scan→hari kerja, lintas cabang, aturan per
  orang, persetujuan, periode gaji, rekap. (Data scan bulanan tetap di-upload
  manual dari mesin sidik jari — arah ADMS masih rencana.)
- **Absen HP selfie**: WiFi cabang sebagai saksi + IP cabang, fix IPv6 /64,
  remember-me, paksa ganti password.
- **Pusat Akun**: Kesehatan (detektor kegagalan-senyap), User, Akses, Role,
  Setting, audit log login.
- **Jatah cuti** per kontrak 12 bulan (saldo boleh minus, cuti bersama) +
  kartu SISA CUTI; **Lembar Gaji** + export CSV.
- **ROP/Pengajuan** (pengganti Formulir A kertas): form HP, antrian Finance
  burgundy, ACC + pilih akun beban Accurate per buku, tahap dibayar + bukti,
  nota pertanggungjawaban, "Ajukan lagi", deteksi dobel, toast Urungkan 2
  menit, auto-expire 30 hari, master 73 vendor tarik-otomatis, rekap + export.
- **Kerjaan Reza**: login redesign, dashboard marketing, monitoring cuci AC,
  foto before/after checklist, export Excel checklist, pengecualian
  per-pertanyaan, grup promo.
- **Reskin tema/zona**: cokelat default + 3 zona (Marketing teal, IT grafit,
  Finance burgundy), Kehadiran gaya kalender Google, sapuan emoji→ikon SVG.
- **Fix dokter**: jadwal khusus menang atas cuti di resolusi slot reservasi.

### Berjalan PARALEL di chat lain (belum selesai)
- **Pola interaksi (UX)** — kamus P1–P6 (chevron, toast-urungkan, tombol mati
  yang bilang kenapa, dll) sedang disebar bertahap; spec:
  `docs/spec/pola-interaksi.md`. Ada WIP belum commit di worktree rop.
- **Security hardening** — chat terpisah; brief:
  `docs/handoff/handoff-security-hardening.md`. P0: throttle `/signin` &
  `/affiliate/login` (PIN 6 digit tanpa throttle). Deploy-nya hotfix dari
  `main`, TERPISAH dari gerbong. Lintas-isu: TrustProxies kosong memengaruhi
  throttle DAN premis WiFi-saksi absen HP.

### Selesai di LOKAL — menunggu review Ekky (2026-08-04 malam)
- **Aturan Kerja** (`/absensi/aturan`, branch `feature/aturan-kerja` dari
  `feature/rop`, BELUM commit): halaman 3 tab — (1) Aturan: CRUD + editor jam
  per hari + konfirmasi "berlaku utk N karyawan mulai besok"; (2) Penugasan:
  ganti aturan = baris baru bertanggal (riwayat gaji utuh) + default per role
  utk akun baru Pusat Akun; (3) Kalender Libur: exception pola Lebaran, rambu
  libur ≠ cuti bersama. Gate Supervisor/Head IT/Finance/Super Admin di
  controller (Supervisor ditambah saat review); semua
  perubahan forward-only; mesin rekonsiliasi tak disentuh; log `att_rule_log`.
  Uji 28/28 lulus + smoke HTTP (termasuk audit putaran-2: fix section JS,
  default-role menggantung, self-XSS confirm, old() lintas-form).
  Deploy: SQL `2026_08_04_aturan_kerja.sql` +
  `access:rebuild`. Rencana: `rencana/rencana-aturan-kerja.md`.

### Rencana yang SUDAH di-ACC, belum dibangun
- **Paket "Pengaturan Operasional"** (ACC 2026-08-04, dibagi dua pemilik,
  keduanya basis `feature/rop`):
  - ~~Aturan Kerja (Ekky)~~ → **SELESAI di lokal**, lihat blok di atas.
  - **Penempatan — dikerjakan REZA** (`rencana/rencana-penempatan.md`):
    staf rolling/backup ke cabang lain tercatat resmi; checklist ikut cabang
    penempatan; Nissa & Irma boleh menempatkan staf mana pun di bawah bagan
    (lintas domain); tanggal akhir wajib; cabang asal tak pernah disentuh.
- **ROP fase 2**: threshold ACC Executive > 2 jt, four-eyes untuk pengajuan
  Finance, budget per departemen, multi-item, rekening vendor, push otomatis
  ke Accurate. Template impor Accurate **di-pause** (tunggu bahas dengan
  Finance; tombol sementara berlabel "Draf untuk Accurate").
- **Launch paket "Pengeluaran"**: petty cash sudah ada tapi **belum diadopsi
  siapa pun** (prod 0 transaksi nyata) — diluncurkan bareng ROP ke Finance +
  para head sebagai satu paket, butuh sosialisasi/demo.

### Di-PAUSE (sengaja, jangan dikira hilang)
- **Integrasi Accurate penuh** (pull 6 buku + push engine + 5 halaman laporan
  Finance) — branch `feature/accurate-integration`, pause sejak 21 Jun, menu
  disembunyikan. Push engine terbukti (25 faktur, verifikasi 25/25 cocok).
  Sebagian kecil (DDL + pull-glaccount) sudah dipetik ke gerbong untuk ROP.
  ⚠️ Utang saat dilanjutkan: **rotate semua token API** (token testing pernah
  terpapar di chat) + password SSH.
- **Project Tracker** (Project>Tugas>To-do multi-user) — Fase A selesai lokal,
  branch `feature/project-tracker`, jangan merge dulu.
- **Redesign Reservasi / Pasien / Doctor** — re-skin bergaya baru; Doctor
  sampai Fase A (list). Diparkir sejak fokus pindah ke absensi.
- **Marketing dashboard** (akuisisi pasien) — plan matang, belum dibangun.
- **Cekat Open API / Omnicare push v2** — proposal sudah dikirim ke vendor
  (`docs/api/proposal-sweet-treats-api-omnicare-v2.md`), menunggu vendor.

## 4. Utang & pekerjaan tertinggal (di luar gerbong)

| # | Utang | Beratnya | Catatan |
|---|---|---|---|
| 1 | **Hotfix sweet-mates ke prod** | P0 keamanan | lihat §2 kiriman 1 |
| 2 | **Security hardening P0** (throttle signin/affiliate) | P0 keamanan | chat terpisah, hotfix dari main |
| 3 | Repo men-track foto upload (`expense-receipts`, `checklist-photos`, `affiliate`) — hanya `doctors/` & `rop/` yang diabaikan | sedang | bahan chat security; bukti petty cash ada di git history |
| 4 | Setelah gerbong naik: **retire `feature/absensi`** + arahkan semua orang cabang dari `feature/rop` | kecil | sudah disepakati, tinggal eksekusi |
| 5 | Prod penuh berkas cruft lama (`Helpers20260413.php`, `.env20260525`, dll) | kecil | bersihkan saat jendela deploy |
| 6 | Warning di form Jadwal Khusus kalau dokter cuti di tanggal itu | kecil | pasangan fix/doctor-availability yang ikut gerbong |
| 7 | Drawer Tambah Reservasi belum konsisten di halaman Pasien & Reservasi Cabang | kecil | masih link polos |
| 8 | 3 node menu ber-awalan `/` di dev (`/my-checklist`, dll) | kecil | prod bersih |
| 9 | `audit_log` baru merekam login/logout, belum perubahan data | besar (fase lanjut Pusat Akun) | "siapa mengubah apa" belum terjawab |
| 10 | Akun Yanwar Fauzi & Madalena belum ada (cuti:saldo-awal 21/23) | kecil | tunggu tanggal kontrak dari Finance |
| 11 | `Ela #58` (mesin Bintaro) belum di-mapping ke akun | kecil | |
| 12 | Konversi `public_html` prod jadi symlink (hapus gotcha rsync selamanya) | kecil | perbaikan permanen |

## 5. Menunggu keputusan/aksi ORANG (bukan kerjaan koding)

| Siapa | Apa yang ditunggu |
|---|---|
| **Head sales** | CSV 150 kandidat reservasi yang mungkin tertimpa selama regresi 14–25 Jul (Sunter 60/Bintaro 46/Cinere 23/Kalideres 17/Pamulang 4) — hanya untuk cross-check FO; identitas lama tak bisa dipulihkan (reservasi tak punya audit-trail) |
| **Finance/Dani** | (a) cuti potong hari kalender vs hari kerja; (b) cuti seminggu penuh tanpa pengajuan = alpa?; (c) tanggal kontrak 29 karyawan; (d) pembahasan template impor Accurate utk ROP; (e) kapan siap adopsi paket Pengeluaran |
| **Reza** | (a) keputusan produk day-excuse checklist; (b) izin reskin halaman Role/setgroup + header tabel Reservasi (2 item terakhir §8 peta-tema) |
| **Ekky sendiri** | (a) selesaikan masa uji dev → bilang "gas prod"; (b) `gh auth login` sekali di mesin ini kalau mau PR dibuat otomatis; (c) demo/sosialisasi absensi+ROP ke tim saat naik prod (migrasi manual bertahap, tidak ada yang dipensiun seketika) |

## 6. Rencana SETELAH prod naik (urutan kasar prioritas)

1. **Stabilisasi prod** — pantau 1–2 minggu, angkat 8 role + 29 akun, demo ke
   tim, adopsi petty cash + ROP oleh Finance & para head.
2. **Security hardening** — habiskan P0/P1 dari chat security.
3. **ROP fase 2** (threshold Executive, budget, push Accurate) — sekalian
   membuka kembali proyek **Accurate** dari pause (ingat: rotate token dulu).
4. **Pusat Akun lanjutan** — perluas audit_log ke perubahan data; halaman
   Kesehatan jadi detektor kegagalan-senyap penuh.
5. **Jatah cuti penyempurnaan** — setelah jawaban Finance (kalender vs hari
   kerja, dst) + isi tanggal kontrak.
6. **Kalender Kehadiran lanjutan / arah ADMS** — mesin absen push otomatis
   (P207 sekarang USB-only), supaya upload manual hilang.
7. **Project Tracker Fase B/C** — lanjutkan dari branch parkiran.
8. **Redesign yang diparkir** — Reservasi drawer/shell, Pasien, Doctor B/C/D,
   Marketing dashboard akuisisi.
9. **Omnicare v2 / Cekat** — lanjut saat vendor merespons proposal.

## 7. Rujukan dokumen (semua di folder `docs/`)

| Butuh apa | Buka |
|---|---|
| Indeks semua docs | `README.md` |
| Daftar centang deploy prod | `deploy/manifest-prod.md` (+ `deploy/deploy-absensi.md`) |
| Cara kerja mesin absensi | `rencana/rencana-rekonsiliasi-absensi.md` |
| Absen HP (WiFi saksi) | `rencana/rencana-absen-hp.md` |
| Jatah cuti | `rencana/rencana-jatah-cuti.md` |
| ROP lengkap | `rencana/rencana-pengajuan-rop.md` + `handoff/handoff-rop.md` |
| Aturan tema/zona warna | `spec/peta-tema.md` |
| Kamus pola interaksi | `spec/pola-interaksi.md` |
| Skenario absensi dunia-nyata | `spec/sop-absensi-cuti.md` |
| Brief security | `handoff/handoff-security-hardening.md` |
| Prompt buka chat baru per alur | `spec/prompt-chat-baru.md` |

## 8. Cara merawat file ini

Setiap ada kejadian besar (deploy, fitur selesai, keputusan produk, pause/
lanjut proyek), minta chat teknis memperbarui file ini + tanggal di atas.
Sumber kebenaran teknis tetap: git history, `docs/`, dan memori chat Claude —
file ini ringkasannya, bukan penggantinya.
