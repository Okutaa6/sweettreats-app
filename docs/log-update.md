# Log Update — jurnal milestone

> Entri terbaru selalu DI ATAS. Tiap entri ditulis ringkas & mandiri supaya
> bisa langsung di-copy ke alat task-management (Gemini) tanpa konteks tambahan.
> Potret utuh proyek: `status-proyek.md`. Jangan mengedit entri lama —
> file ini append-only.

---

## 2026-08-05 — Batch rapi-rapi NAIK PROD (4 item, terverifikasi)

Prod sekarang berjalan di `main` @ `b26eb26`. Keempat item batch "rapi-rapi
pasca-launch" sudah live: sidebar Absensi jadi pintu tunggal "Kehadiran",
gerbang sopan absen HP di komputer, persetujuan absensi satu lapis, dan
sidebar Marketing tertata tiga kelompok. Tidak ada perubahan aset (CSS/JS),
jadi rsync `public_html` tidak diperlukan kali ini.

**Diaudit ulang dari nol sebelum naik** (chat HUB, bukan mengandalkan laporan
chat pembuatnya). Yang diperiksa dan hasilnya:

- Klaim "baris sidebar yang disembunyikan memang berujung 403" **dibuktikan
  langsung di kode**: keempat controller (Upload, Mapping, Rekap, Periode)
  benar-benar dijaga `canSeeAllBranches`. Jadi yang hilang dari sidebar
  memang pintu buntu, bukan halaman yang bisa dipakai.
- Grant node "Kehadiran" **persis sama** dengan daftar `canSeeAllBranches`
  (Super Admin, Admin General, Admin Backup, Finance) — tidak lebih.
- Kasus paling mudah salah **ditangani benar**: Executive hanya memegang Rekap
  Pengeluaran tanpa Antrian, dan kode sengaja mengecualikannya dari aturan
  penyembunyian — kalau tidak, satu-satunya pintunya ikut hilang.
- Prasyarat prod dicek dulu, bukan diasumsikan: induk menu "Absensi" dan
  "Marketing" ada, node kol/koc sudah ada (jadi blok pembuatnya no-op),
  14 halaman marketing masing-masing tepat satu node, dan aturan persetujuan
  tetap 13 baris.
- **Risiko "Executive kosong" tidak berlaku di prod**: ada 3 penghuni aktif
  (Robi, Hari, Dani). Catatan untuk Ekky: **Hari** juga berperan Executive,
  jadi ia ikut menerima pengajuan para head/SPV — kalau itu tidak diinginkan,
  perannya perlu diubah.
- Backlog persetujuan warisan **nihil**: prod belum punya satu pun pengajuan
  absensi (`att_request` kosong), jadi `absensi:selesaikan-l2` memang no-op.

**Urutan eksekusi**: backup DB prod (`~/backups/prod_pre_rapi_2026-08-05.sql.gz`,
45 MB) → `deploy.sh main` → 2 SQL (`2026_08_05_sidebar_kehadiran`,
`2026_08_05_sidebar_marketing`) → `access:rebuild` + `cache:clear` +
`view:clear` → `absensi:selesaikan-l2`.

**Verifikasi prod — semua lulus**: nol link menu dobel; typo "Kode Refferal"
sudah jadi "Kode Referral"; tiga section marketing berdiri dengan 14 halaman
di kelompok yang benar; urutan menu lain tidak bergeser (parent dikali sepuluh
sekali saja, section baru menyelip di 41–43 antara Marketing 40 dan Keluhan
50); tidak ada node yatim; berkas akses hasil rebuild memuat "Kehadiran" untuk
tepat empat peran itu dan tidak untuk Supervisor; situs 200; **0 error** di log
aplikasi.

**Branch**: `feature/rapi-pasca-launch` digabung maju (fast-forward) ke
`feature/rop`, `release/2026-08-pengeluaran`, dan `main` — keempatnya kini di
`b26eb26`, jadi tidak ada lagi cabang yang tertinggal. WIP chat pola-interaksi
di worktree `feature/rop` tidak tersentuh (12 berkasnya tidak beririsan dengan
11 berkas batch).

**Yang perlu diketahui pengguna**: menu berubah, jadi semua orang perlu
logout-login sekali supaya sidebar barunya muncul.

## 2026-08-05 — Batch rapi-rapi: AUDIT + NAIK DEV (terverifikasi, prod belum)

Dev sekarang menjalankan branch `feature/rapi-pasca-launch` (5 commit, dari
feature/rop). **Prod TIDAK disentuh** — dikerjakan Ekky di chat terpisah.

**Audit adversarial dulu (dua penelaah, kode + SQL).** Area inti dinyatakan
bersih; 6 temuan, yang layak sudah diperbaiki sebelum naik:

- **BUG NYATA, ketemu & difix**: SQL Kehadiran meresolusi induk lewat link
  `absensi` — pembawa link itu BEDA antar server (lokal: induk "Absensi";
  dev/prod: anaknya "Upload Data"), jadi di dev/prod node-nya tak akan pernah
  dibuat dan gagalnya DIAM. Diganti resolusi lewat nama induk. Ini persis
  kelas jebakan yang bikin insiden 15 node saat gerbong kemarin.
- **Jaring pengaman ditambah**: `absensi:selesaikan-l2` kini juga dijadwalkan
  harian 06:25, bukan cuma langkah manual — kalau satu server lupa
  menjalankannya, pengajuan warisan sembuh sendiri, tidak beku diam-diam.
- **Jejak audit**: finalisasi menulis baris persetujuan seperti ACC manusia,
  jadi "siapa yang menyelesaikan ini" tetap terjawab dari riwayat.
- **Kedua SQL dibungkus transaksi** — gagal di tengah = tak berubah sama
  sekali, jadi aman diulang (sebelumnya, putus di titik tertentu bisa membuat
  urutan menu terkali dua kali).
- **Gerbang absen HP**: alasan tertulisnya dikoreksi (halaman ini memakai
  kamera langsung, bukan pemilih berkas — yang dijaga adalah absen dari meja
  mana pun), dan halaman di baliknya dimatikan betulan supaya Tab+Enter tak
  bisa menjalankan alur kamera tak terlihat.
- **Dicatat, tidak diubah** (perilaku lama, di luar batch): kalau role
  Executive tak punya penghuni aktif, pengajuan para head bisa lolos sendiri.
  Di dev aman — Robi & Hari dua-duanya aktif sebagai Executive.

**Verifikasi dev — semua lulus:**
- Data menu: nol duplikat link, typo "Kode Refferal" hilang, node Kehadiran
  terpasang, **8 grant link `absensi` utuh** (kunci akses seluruh /absensi/*),
  3 section marketing berdiri, urutan menu lain tak bergeser.
- Sidebar per peran: Finance melihat "Kehadiran" (tab-tab lama sudah tidak
  muncul), Head Marketing melihat tepat 3 kelompok baru, Executive tetap
  memegang Rekap Pengeluaran, Head IT/Supervisor/FO tak lagi melihat baris
  absensi yang dulu berujung 403.
- **41 halaman dijalankan per peran: semua 200**, termasuk seluruh halaman tab
  (masih terjangkau dari bar tab walau tak ada di sidebar) dan 14 halaman
  marketing. Yang bukan haknya tetap ditolak dengan pesan jelas.
- Persetujuan: 16 jabatan semua 1 lapis dengan penyetuju benar; 2 pengajuan
  warisan difinalkan + jejaknya tercatat; sisa pending_l2 = nol.
- Absen HP: gerbang komputer tampil utuh, alur kamera halaman tetap utuh.
- Log dev bersih (0 error aplikasi).

**Catatan koordinasi**: kerjaan ini belum digabung ke `feature/rop` — worktree
branch itu sedang dipakai chat pola-interaksi, dan penggabungannya perlu
dilakukan dari sana (atau dengan izin terpisah). Dev untuk sementara berjalan
di branch batch ini, yang isinya = feature/rop + 4 item ini.

**Kalau nanti naik prod, urutannya**: 2 SQL (`2026_08_05_sidebar_kehadiran`,
`2026_08_05_sidebar_marketing`) → `php artisan access:rebuild` →
`php artisan absensi:selesaikan-l2` → pastikan role Executive ada penghuninya.

## 2026-08-05 — Batch "rapi-rapi pasca-launch" SELESAI LOKAL (4 item, menunggu review → dev)

Keempat item yang di-ACC kemarin selesai dibangun & diuji di lokal (branch
`feature/rapi-pasca-launch` dari feature/rop; belum di-commit, menunggu review
Ekky sebelum naik dev). PROD TIDAK disentuh.

- **(1) Sidebar Absensi = pintu tunggal "Kehadiran"** — Upload Data / Mapping /
  Rekap Persetujuan / Periode Gaji keluar dari sidebar, cukup lewat bar tab
  yang sudah ada di dalam halaman; "Rekap Pengeluaran" ROP sama (tombol Rekap
  di Antrian sudah ada sejak awal). Dikerjakan lewat KODE render menu
  (`shellTabbedLinks` di Helpers) — grant TIDAK dicabut, kunci AuthGuard
  `absensi` aman (jebakan prod kemarin tak terulang). Node menu "Kehadiran"
  baru via SQL `2026_08_05_sidebar_kehadiran.sql` (grant hanya role yang
  memang boleh buka: Finance/Admin General/Admin Backup/Super Admin).
  Bonus: entri sidebar yang selama ini berujung 403 (mis. Upload Data di
  Head IT/IT) ikut hilang — kegagalan senyap berkurang. Role Executive tetap
  pegang "Rekap Pengeluaran" (satu-satunya pintu dia — dilindungi khusus).
  Teruji per 6 role × 6 halaman: menu, sorot aktif, judul header benar.
- **(2) Absen HP ditutup sopan di komputer** — /akun/absen dibuka dari desktop
  kini menampilkan penjelasan "absen lewat HP/tablet di titik absen cabang"
  (+ arahan Koreksi Absensi kalau darurat), tanpa tombol lanjut. HP + TABLET
  tetap normal (deteksi: pointer mouse tanpa layar sentuh). Menu TIDAK
  disembunyikan — halaman tetap ada, isinya yang menjelaskan.
- **(3) Persetujuan absensi jadi SATU lapis** — staf di-ACC atasan langsung,
  selesai (FO/GS/Sales→Supervisor; Asdent→Koordinator ATAU Supervisor;
  Creative/CC→Head Marketing; IT→Head IT); pengajuan milik head/SPV/
  koordinator/Finance di-ACC role Executive (Dani ATAU Robi, siapa cepat).
  13 aturan `att_approval_rule` TIDAK berubah (isinya memang sudah atasan
  langsung) — yang dipangkas plafon lapis di engine (semua jenis form = 1).
  Pengajuan lama yang keburu menunggu lapis-2 difinalkan otomatis command
  baru `absensi:selesaikan-l2` (wajib dijalankan sekali saat deploy; kalau
  tidak, mereka macet tak terlihat selamanya). Teruji: rantai 11 role semua
  1 lapis, approver benar; command idempoten.
  ⚠️ Prasyarat data di dev/prod: akun Robi ber-role Executive (di lokal baru
  Dani) — tanpa itu praktisnya hanya Dani yang menerima.
- **(4) Sidebar marketing 3 kelompok** — "Dashboard & Laporan" (Dashboard
  Marketing, Report Referral, Klaim Komisi) · "Konten" (Konten Pillar,
  EP Konten, Breakdown, Campaign Framework, Review Konten, Keyword) ·
  "Referral & Member" (Kode Referral, Affiliate, KOL, KOC, Sweet Mates).
  SQL `2026_08_05_sidebar_marketing.sql`: pindah node BY LINK (id beda antar
  env aman), parent section baru + grant mengikuti pemegang anak, typo
  "Refferal"→"Referral" dibereskan sekalian, node KOL/KOC dibuat hanya di
  env yang belum punya. Promo sengaja tetap di Data Center (di luar
  keputusan). Idempoten — diuji jalan 2×, hasil sama.
- **Deploy dev nanti butuh**: 2 SQL di atas + `php artisan access:rebuild` +
  `php artisan absensi:selesaikan-l2` + verifikasi akun Robi (Executive).

## 2026-08-05 — Penyesuaian hari pertama prod (feedback Ekky)

- **Supervisor (Nisa) dapat paket pengawasan sales** di prod & dev: Reservasi,
  Pasien, Doctor, Laporan Penjualan, Dashboard Sales (karena dia SPV sales;
  MetricaSetting sengaja tidak — wilayah IT).
- **Lembar Gaji disembunyikan di prod** (fitur belum selesai; dev tetap ada).
- WiFi "Sunter" yang kemarin dicurigai salah label ternyata BENAR — Ekky
  memang menguji dari cabang Sunter.
- Ditemukan keterikatan arsitektur: node menu "Upload Data" (link `absensi`)
  adalah syarat gerbang semua halaman /absensi/* — tidak bisa dicabut dari
  sidebar tanpa perubahan kode (dibuktikan: dicabut → Aturan Kerja ikut mati
  → dikembalikan).
- **Batch "rapi-rapi pasca-launch" di-ACC Ekky**, dikerjakan menyusul:
  (1) sidebar absensi dikonsolidasi — "Kehadiran" jadi pintu tunggal, Upload/
  Mapping/Rekap/Periode jadi tab (tab bar-nya sudah ada); Rekap Pengeluaran
  ROP sama; (2) absen HP: akses dari desktop ditutup sopan (kekhawatiran:
  desktop bisa unggah file foto, bukan kamera langsung); (3) persetujuan
  absensi jadi SATU lapis — staf di-ACC atasan langsung (Nisa/Irma), pengajuan
  head/SPV/koordinator di-ACC Executive (Dani ATAU Robi); (4) sidebar
  marketing ditata 3 kelompok (struktur datanya tidak berubah oleh gerbong —
  tampilan beda karena shell baru reskin).

## 2026-08-04 tengah malam — 🚀 GERBONG NAIK PROD (deploy besar selesai)

- **Prod (app.sweettreats.id) sekarang menjalankan gerbong penuh** — commit
  `06938ed`: absensi + absen HP + Pusat Akun + cuti + lembar gaji + checklist/
  AC + dashboard marketing + login baru + reskin zona + ROP + Aturan Kerja.
- Sebelum naik: fitur Aturan Kerja diuji fungsional di dev (10/10 lolos:
  CRUD aturan, log jejak, penugasan backdate ditolak, kalender libur +
  tumpang-tindih ditolak, gate role, hapus bersih).
- Eksekusi prod: backup DB 45MB → merge rilis→main (1 konflik komentar) →
  deploy kode → **8 role baru** (UUID = dev) → **32 SQL** (2 via mysql CLI
  karena DELIMITER) → **24 migration** → 9 akun karyawan baru + 3 akun
  marketing dipindah ke email @sweettreats.id → akses FO = salinan Sales →
  WiFi Bintaro+Sunter didaftarkan → saldo cuti dibuka (9 akun) → 75 vendor +
  CoA Accurate ditarik → rsync aset.
- **Insiden saat deploy, ketemu & dibereskan**: 15 node menu (Absensi, Akun,
  Persetujuan, Checklist) ternyata tak pernah dibikin SQL mana pun — dulu
  dibuat manual di dev → disalin utuh ke prod + seluruh grant 8 role
  disalin dari dev (50 baris). Pelajaran: grant BY LINK diam saja kalau
  node-nya tak ada.
- Verifikasi akhir per peran di prod: Head IT, Finance, Supervisor, FO —
  semua halaman kunci 200, yang bukan haknya tertolak, log prod 0 error.
  12/12 akun baru terkunci wajib-ganti-password.
- Kredensial akun: file LOKAL (gitignored), BUKAN di Drive.
- PR: tidak lewat PR — merge langsung sesuai jendela deploy malam ini.
- **Checklist prod DIBERESKAN juga (tanpa nunggu Reza)** — 403 Supervisor
  ternyata 3 lapis data yang hilang di prod: (1) bagan role (parent/level)
  belum diisi → disalin dari dev (17 role); (2) tabel divisi checklist prod
  KOSONG → 3 divisi (FO/GS/ASDENT) disalin; (3) posisi user belum di-sync →
  dijalankan logika resmi UserPosition::syncFromUser utk 12 akun (4 staf
  operasional dapat posisi). Hasil: Nissa bisa buka dashboard/review/setup/
  monitoring-AC checklist di prod (semua 200) — siap setup data besok;
  Firda dapat form checklist FO berisi tugas.
- Tersisa (bukan penghalang): cek cron hPanel `schedule:run`, 14 karyawan
  sisanya dibuat via Pusat Akun, kabari kredensial (terutama 3 akun marketing
  yang emailnya berubah).

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
- Rel pengaman sesuai rencana: gate role di controller (Supervisor + Head IT +
  Finance + Super Admin — Supervisor ditambah Ekky saat review 2026-08-04),
  semua perubahan berlaku KE DEPAN saja (backdate ditolak),
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
