# Prompt siap-tempel untuk chat baru

Prompt pembuka supaya chat baru langsung nyambung tanpa mengulang konteks.
Copy blok yang dibutuhkan, tempel sebagai pesan pertama.

**Pembagian chat (keputusan 2026-07-25):**

| Chat | Isi | Branch kerja |
|---|---|---|
| **A. Peta tema / visual** | terapkan tema per zona, reskin Kehadiran, sweep ikon | `feature/absensi` |
| **B. Security** | rate-limit + sapu lubang lain | `hotfix/*` dari `main` |
| **C. Planning / verifikasi / deploy** | koordinasi, audit, deploy dev & prod | docs/memori + deploy |
| **D. Build ROP / Pengeluaran** | bangun fitur Pengajuan/ROP + adopsi petty cash | `feature/rop` dari `feature/absensi` |
| **E. Build Aturan Kerja** | UI jam kerja + penugasan + kalender libur | `feature/aturan-kerja` dari `feature/rop` |

**Dua aturan biar chat paralel tidak tabrakan:**
1. **Chat tidak bisa saling lihat.** Jembatan satu-satunya = file di disk
   (`docs/` + memori Claude). Jadi **tulis keputusan ke docs/memori sebelum chat
   ditutup**, kalau tidak chat lain tak akan pernah tahu.
2. **Jangan dua chat commit ke branch yang sama** — ikuti kolom "Branch kerja".

---

## A. Chat: PETA TEMA / VISUAL

> Saran model: bebas.

> **STATUS 2026-07-31: SUDAH DIEKSEKUSI** (handoff kehadiran terpasang semua +
> antrian §8 lunas kecuali 2 item yang nunggu Reza). Detail:
> docs/handoff/handoff-kehadiran-reskin.md. Prompt di bawah disimpan sebagai
> arsip pola; kalau buka chat visual baru, susun antrian baru dari §8 terkini.

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini fokus EKSEKUSI VISUAL.

Baca dulu:
- docs/README.md — indeks dokumentasi (biar tahu isi folder apa)
- docs/spec/peta-tema.md — peta tema/zona final (cokelat = default; Marketing
  teal, IT grafit, Finance burgundy #8A2E43), 3 lapis warna, mapping per-page,
  device-intent, aturan emoji->ikon. §8 = daftar utang = backlog chat ini.
- docs/handoff/handoff-kehadiran-reskin.md — catatan implementasi terakhir.

JANGAN sentuh header tabel Reservasi & halaman Role (setgroup) — nunggu
koordinasi Reza, masuk redesign masing-masing.

Aturan kerja: branch feature/absensi; test di LOKAL (php artisan serve
--host=0.0.0.0 --port=8899 · akun muhamad.ikhsan@sweettreats.local /
sweettreats2026); diskusi dulu kalau ada keputusan desain baru; jangan commit
tanpa izin; jangan push/deploy tanpa diminta; audit ulang error/bug/
inkonsistensi setelah tiap batch. Tanpa emoji di UI. Catat keputusan penting ke
docs/ + memori sebelum chat ditutup.
```

---

## B. Chat: SECURITY HARDENING

> **Saran model: Opus.** Topik keamanan (brute-force, rate-limit, enumerasi)
> gampang men-trigger pengaman otomatis di model lain.

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini KHUSUS security hardening.

Baca dulu docs/handoff/handoff-security-hardening.md — brief lengkapnya (audit
rate-limit + prioritas fix + nuansa deploy). Ringkasnya: grup middleware `web`
tidak punya throttle default, jadi login/affiliate/reservasi/webhook rawan
brute-force. P0 = POST /signin (brute-force + enumerasi email) dan
POST /affiliate/login (PIN 6 digit tanpa throttle).

Yang kuminta:
1. Verifikasi temuan di handoff itu masih akurat terhadap kode terkini (jangan
   telan mentah).
2. Sapu kemungkinan lubang LAIN sesuai §6 handoff (IDOR/otorisasi baris,
   mass-assignment, file upload, SQLi, XSS, CSRF except, session/open-redirect,
   secrets, APP_DEBUG prod) — laporkan dengan tingkat risiko + fix konkret.
3. Bahas dulu temuannya sama aku SEBELUM koding. Setelah aku ACC, kerjakan
   bertahap dari P0.

Aturan kerja: test di LOKAL dulu; endpoint ini hidup di prod (main), jadi
perbaikannya hotfix branch dari main (pola hotfix/reservasi-simpan-keluar),
kode-only tanpa SQL; JANGAN deploy prod tanpa aku sebut prod eksplisit; jangan
push tanpa diminta. Penting: cek topologi proxy prod dulu (TrustProxies kosong)
karena memengaruhi throttle-by-IP DAN premis WiFi-saksi absen HP. Catat
keputusan ke docs/ + memori sebelum chat ditutup.
```

---

## C. Chat: PLANNING / VERIFIKASI / DEPLOY  — HUB

> **Saran model: Opus.** High-stakes (deploy prod) + butuh konteks luas.
> Disegarkan **2026-08-21**. Kalau tanggal ini sudah lewat sebulan, minta
> Claude mengaudit ulang §C sebelum dipercaya.

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini HUB: planning, koordinasi,
verifikasi/audit, dan deploy — BUKAN tempat ngoding fitur.

Baca dulu: docs/README.md (indeks), docs/status-proyek.md (potret penuh),
docs/log-update.md entri teratas (kerjaan terakhir), lalu memori
project_absensi_status_deploy + reference_deploy_access.

Peran chat ini:
- Koordinasi: pegang daftar kerjaan & keputusan yang menggantung.
- Verifikasi: audit hasil kerja chat lain (bug/error/inkonsistensi), uji
  regresi, bandingkan dev vs prod.
- Deploy: dev kapan saja bila diminta; PROD hanya kalau aku sebut "prod".

== KEADAAN PER 2026-08-21 ==

PROD sehat dan MUTAKHIR: main = prod = `618ca57` (deploy 21 Agu 10.50, berisi
pelebaran pencocokan WiFi /48). Kelima cabang hidup (Bintaro, Sunter, Cinere,
Pamulang, Kalideres), 19 staf operasional berakun.

Yang live & dipakai harian: absensi + absen HP selfie, WiFi-sebagai-saksi,
Pusat Akun, cuti (+Kontrak Baru), checklist, ROP/Pengeluaran, Aturan Kerja,
petty cash (kategori dinamis + dompet per-operator), reskin zona.

BRANCH YANG MENGANTRE (belum di main):
- `feature/rop-lembar-bayar` — punya chat §G, sedang hidup di dev. Naik prod
  butuh SQL + rsync CSS. JANGAN disentuh dari sini.
- `feature/project-tracker`, `feature/accurate-*` — WIP, jangan merge.

YANG MENGGANTUNG (butuh keputusan/tindakan, bukan bug):
1. `movePeriod` petty cash masih NOL jejak audit dan masih MENUKAR diam-diam
   kalau periode tujuan tidak kosong. Sudah kutawarkan perbaikannya, belum
   dijawab.
2. Verini harus mengajukan sendiri top-up Rp985.200 miliknya.
3. Role `Executive` (2 orang) & `Branch Executive` (1) belum punya
   `att_default_rule_id` — mereka lenyap dari Kehadiran sampai diisi.
4. Agustus Sunter SENGAJA parsial (~Rp1,55 juta belum tercatat) — sudah
   didokumentasikan di log-update + audit_log; boleh dilengkapi kapan saja.
5. 27 penghapusan lama di Bintaro tanpa pelaku tercatat (sebelum deleted_by).
6. WiFi IPv4: 58 kegagalan tetap gagal dan memang sengaja dibiarkan — IPv4 tak
   punya padanan /48 yang aman. Jangan "perbaiki" dengan melebarkan ke /24//16.
7. `last_seen_at` saat cocok lewat /48 menempel ke baris ber-id terkecil
   (Bintaro selalu `10d5::` yang sudah mati). Menyesatkan ringan buat IT.

CHAT LAIN YANG JALAN (jangan dikerjakan di sini):
§B security hardening · §G lembar bayar ROP · §H beres-beres flag · §I jatah
cuti. Fitur dokter DITUNDA atas keputusan Ekky.

== ATURAN KERJA CHAT INI ==

- Chat tidak bisa saling lihat. Jembatannya cuma file di disk (docs/ + memori).
  Tulis keputusan SEBELUM chat ditutup.
- Jangan commit kode aplikasi fitur di sini (itu tugas chat build). Perbaikan
  kecil hasil audit boleh, dengan branch sendiri.
- DEV cuma SATU working copy bersama. Sebelum menggeser branch dev, cek dulu
  branch siapa yang sedang hidup di sana, dan KEMBALIKAN sesudah selesai.
- PROD `public_html` itu SALINAN, bukan symlink. Deploy yang menyentuh
  `public/assets/**` WAJIB diikuti rsync, kalau tidak CSS/JS baru 404.
- deploy.sh TIDAK menjalankan migration/SQL. Cek prasyarat SQL manual dulu.
- Login dev itu `/signin`, bukan `/login`.
- Jangan deploy prod tanpa aku menyebut "prod" eksplisit.
- WAJIB tiap milestone: entri docs/log-update.md (+status-proyek.md bila peta
  berubah) → commit → salin keduanya ke
  "G:\My Drive\Task Management\Sweet Treats\" (dibaca Gemini dari HP).

== CARA AKU MAU DIBANTU ==

- Jangan simpulkan dari dev untuk hal yang datanya beda di prod (rekam medis
  Omnicare contohnya).
- Uji HTTP yang "hijau" tidak membuktikan UI jalan — `curl -L` bisa mengikuti
  pentalan ke halaman login lalu melaporkan 200. Periksa URL akhirnya.
- Kalau aku bilang sesuatu yang bertentangan dengan datamu, cek datanya dulu
  lalu bilang terus terang — jangan ikut saja.
```


---

## Kalau aku (Claude) mulai ngelantur

Gejala di chat yang sudah sangat panjang: mengulang yang sudah dibahas, lupa
keputusan yang baru diambil, menulis simbol/ikon sembarangan menggantikan
huruf, atau menjawab tidak sesuai pertanyaan.

Kalau kelihatan begitu: **tegur langsung**, lalu buka chat baru pakai prompt di
atas. Semua keputusan sudah tertulis di `docs/` + memori, jadi tidak ada yang
hilang.

---

## D. Chat: BUILD ROP / PENGELUARAN

> Saran model: bebas (CRUD penuh + UI dari design handoff; kalau mau sekali
> jalan rapi, Opus).

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini fokus BUILD fitur
Pengajuan/ROP (expenses digital) — paket "Pengeluaran" bareng adopsi petty
cash.

Baca dulu:
- docs/README.md — indeks dokumentasi
- docs/rencana/rencana-pengajuan-rop.md — plan LENGKAP yang sudah di-ACC:
  scope v1 (termasuk 6 improvement), lifecycle, penomoran ROP/{CC}/{YYMM}/
  {NNN}, data model, dan daftar fase 2 yang JANGAN dibangun dulu.
- docs/spec/peta-tema.md — zona warna: halaman pengaju COKELAT default,
  halaman Finance (antrian + rekap) BURGUNDY #8A2E43 (body.zona-finance
  sudah ada di public/assets/css/zona.css).
- Hasil claude.ai/design dari docs/brief-desain/design-brief-rop.md:
  [tempel link/hasil desain di sini]

Konteks teknis (jangan reinvent):
- Precedent flow approval: tabel topup_requests + TopupRequestController
  (pending -> approved/rejected + AdminNotification) — generalisasi pola ini.
- Master vendor: seed dari staging Accurate (58 vendor) — lihat rencana §8.
- Konvensi DB: SQL manual di database/sql/ (BUKAN migration); akses menu via
  SQL + php artisan access:rebuild; grant akses BY LINK, bukan by id node.
- Upload foto terkompresi: pola sudah ada di absen HP selfie — pakai ulang.
- Export impor Accurate: blueprint template impor resmi ada di proyek
  accurate-integration (memori project_accurate_integration).

Urutan kerja yang disarankan: SQL skema -> backend lifecycle + notifikasi ->
UI pengaju (HP-first) -> antrian Finance -> rekap + export -> audit
end-to-end (termasuk deteksi dobel & auto-expire).

Aturan kerja: branch BARU `feature/rop` dibuat dari `feature/absensi`
TERBARU — cek dulu origin: pastikan reskin Kehadiran sudah di-commit dan
branch Reza (dashboardmarketing-checklistdesign-monitoringAC-bugfixlainnya)
sudah di-merge; kalau belum, tanya aku dulu. Test di LOKAL (php artisan
serve --host=0.0.0.0 --port=8899 · muhamad.ikhsan@sweettreats.local /
sweettreats2026). Diskusi dulu tiap keputusan produk baru. Jangan commit
tanpa izin (kerjaan dibiarkan unstaged buat review). Jangan push/deploy
tanpa diminta. Tanpa emoji di UI. Catat keputusan penting ke docs/ + memori
sebelum chat ditutup.
```

---

## E. Chat: BUILD ATURAN KERJA

> Saran model: bebas. Fitur ini UI di atas data yang sudah ada — bahayanya
> bukan kerumitan, tapi kesalahan menyentuh mesin rekonsiliasi.

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini fokus BUILD fitur
Aturan Kerja (UI kelola jam kerja + penugasan aturan + kalender libur).

Baca dulu:
- docs/README.md — indeks dokumentasi
- docs/rencana/rencana-aturan-kerja.md — plan yang sudah di-ACC (3 tab,
  aturan main, batas scope). Ikuti itu, jangan karang ulang.
- docs/rencana/rencana-rekonsiliasi-absensi.md — cara mesin membaca aturan
  (att_work_rule / _day / _exception / att_user_rule). WAJIB paham sebelum
  menulis apa pun.
- docs/spec/peta-tema.md — zona cokelat, tanpa emoji (ikon SVG).

Aturan keras:
- JANGAN sentuh mesin rekonsiliasi (reconcile, att_day) — fitur ini cuma
  menulis ke tabel aturan yang mesin baca.
- Penugasan aturan = baris baru ber-effective_from, BUKAN edit baris lama.
- Berlaku ke depan saja; jangan bikin re-reconcile massal.
- Gate role di controller (Head IT + Finance + Super Admin), jangan
  andalkan hasMenuAccess (cuma nemu node level 3).
- Libur nasional ditulis sebagai att_work_rule_exception semua aturan baku
  (pola SQL lebaran) — bukan mekanisme baru.

Aturan kerja: branch BARU `feature/aturan-kerja` dari `feature/rop`
TERBARU (cek origin dulu). Test di LOKAL (php artisan serve --host=0.0.0.0
--port=8899 · muhamad.ikhsan@sweettreats.local / sweettreats2026). Diskusi
dulu tiap keputusan produk baru. Jangan commit tanpa izin. Jangan push/
deploy tanpa diminta. Selesai milestone: entri docs/log-update.md +
status-proyek.md bila peta berubah + SALIN keduanya ke
"G:\My Drive\Task Management\Sweet Treats\" (kewajiban sinkron Gemini).
Catat keputusan penting ke docs/ + memori sebelum chat ditutup.
```

---

## F. Chat: BATCH RAPI-RAPI PASCA-LAUNCH (2026-08-05)

> Saran model: bebas. Isinya 4 pekerjaan menengah yang saling lepas —
> kerjakan URUT, uji per item di lokal, deploy dev per batch.
> (Item ke-5 lama — mode lihat-saja Irma — DIBATALKAN 2026-08-05: Ekky
> memutuskan Irma dapat akses penuh Doctor+Reservasi, sudah terpasang.)

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini mengerjakan BATCH
"rapi-rapi pasca-launch" — 5 item yang sudah di-ACC Ekky 2026-08-05
(memori project_absensi_status_deploy bagian BATCH; log-update.md entri
2026-08-05). PROD SUDAH LIVE — hati-hati, semua lewat dev dulu.

Baca dulu: docs/README.md · docs/spec/peta-tema.md · docs/spec/
pola-interaksi.md · memori project_absensi_status_deploy.

4 pekerjaan (urut prioritas):
1. KONSOLIDASI SIDEBAR ABSENSI — "Kehadiran" jadi pintu tunggal di sidebar;
   Upload Data / Mapping / Rekap Persetujuan / Periode Gaji cukup lewat bar
   tab yang SUDAH ADA (resources/views/absensi/_finance_tabs.blade.php).
   ⚠️ JEBAKAN TERBUKTI: AuthGuard meloloskan /absensi/* hanya kalau role
   pegang node ber-link 'absensi' — mencabut grant node = SEMUA halaman
   absensi role itu mati (sudah diuji di prod, lalu dikembalikan). Jadi ini
   perubahan KODE (AuthGuard / cara menu render), bukan cabut-grant.
   Perlakukan "Rekap Pengeluaran" ROP sama (tab di antrian, keluar sidebar).
2. GERBANG ABSEN HP — /akun/absen dari DESKTOP ditutup gerbang sopan
   (pola resources/views/layouts/_gerbang_sopan.blade.php): jelaskan "absen
   lewat HP/tablet di titik absen cabang". HP + TABLET tetap boleh.
   Alasan: file-picker desktop = bisa unggah foto lama, bukan kamera.
   Jangan hide menu-nya (hide senyap = katalog kegagalan-senyap) — halaman
   tetap ada, isinya yang menjelaskan.
3. PERSETUJUAN ABSENSI 1 LAPIS — rework att_approval_rule (13 aturan):
   staf (FO/GS/Asdent/dst) → ACC atasan langsung (Supervisor/Coordinator)
   SELESAI (tanpa L2); pengajuan milik head/SPV/koordinator → ACC role
   Executive (Dani ATAU Robi — dua-duanya sah, siapa cepat).
   Pahami dulu engine approval di rencana-rekonsiliasi-absensi.md.
4. SIDEBAR MARKETING 3 KELOMPOK — restrukturisasi node menu marketing:
   "Dashboard & Laporan" (Dashboard Marketing, Report Referral, Klaim
   Komisi) · "Konten" (Konten Pillar, EP Konten, Breakdown, Campaign
   Framework, Review Konten, Keyword) · "Referral & Member" (Kode Referral,
   Affiliate, KOL, KOC, Sweet Mates). Fix typo "Refferal"→"Referral".
   SQL node idempoten + grant BY LINK; ingat node id beda antar env.

Aturan kerja: worktree/branch SENDIRI dari feature/rop TERBARU (worktree
sweettreats-rop DIPAKAI chat pola-interaksi — jangan sentuh; koordinasi
lewat feature/rop). Test lokal → minta izin → deploy dev → verifikasi →
PROD HANYA kalau Ekky sebut prod. Tanpa emoji di UI. Selesai tiap item:
entri docs/log-update.md + salin log-update.md & status-proyek.md ke
"G:\My Drive\Task Management\Sweet Treats\". Catat keputusan ke docs/memori.
```

---

## G. Chat: BUILD LEMBAR BAYAR ROP (2026-08-14)

> Saran model: bebas. Satu fitur utuh, rencananya sudah lengkap dan ACC —
> tinggal dieksekusi. Ada halaman cetak (`@media print`), pola pertama di repo.

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini membangun LEMBAR BAYAR ROP
— rekap pembayaran yang Finance kirim ke bos sebagai SATU PDF, pengganti
kebiasaan kirim 30 berkas satu per satu. ACC Ekky 2026-08-14.

BACA DULU, jangan mulai sebelum selesai:
· docs/rencana/rencana-lembar-bayar-rop.md   ← rencana lengkap, sudah ACC
· docs/rencana/rencana-pengajuan-rop.md      §11 (tema burgundy, device-intent)
· docs/spec/peta-tema.md · docs/spec/pola-interaksi.md
· memori project_rop_pengajuan

Fiturnya SEPASANG, jangan cuma yang pertama:
1. Lembar cetak (daftar + total + terbilang + lampiran, satu PDF)
2. Tandai dibayar SEKALIGUS — satu bukti transfer untuk beberapa ROP

Alasan yang kedua wajib: bos sering bayar beberapa ROP dalam satu transfer,
tapi bayar() sekarang satu-per-satu, jadi Finance mengunggah berkas yang sama
berulang kali. Tanpa ini, kerja Finance cuma pindah tempat.

TIGA HAL YANG PALING MUDAH DISALAHKAN — semuanya sudah dibuktikan 14 Agu:
· Rute WAJIB di bawah segmen `finance/`. Segmen-1 BARU akan dipantulkan ke
  beranda untuk SEMUA orang, Super Admin sekalipun, dan gejalanya 302 ke
  beranda BUKAN 403 — terlihat seperti tombol rusak. Ini yang mematikan fitur
  kategori petty cash Reza. Lihat AuthGuard::SKIP_RBAC.
· `pay_to_text` itu NOMOR REKENING. Kolom bank di tabel vendors semuanya NULL
  dan vendor_id selalu NULL; orang menulis "VA MANDIRI - 896 085888064313" di
  kolom bebas itu. Cetak apa adanya, menonjol, jangan dipecah. Terpotong =
  uang salah alamat.
· Halaman cetak JANGAN @extends('layouts.app') — sidebar ikut tercetak.

Pakai ulang, jangan tulis ulang: RopService::nomorBaru() (penomoran atomik),
RopService::tandaiDibayar(), RopService::TRANSISI, RopAttachment::simpan(),
RopStatusLog, pola guard() Finance. Rinciannya di §3 rencana.

Aturan kerja: branch SENDIRI dari main terbaru. SQL di dev dulu & jalankan dua
kali (uji idempoten). Uji gerbang lewat HTTP sungguhan — jangan menebak dari
kode, dan JANGAN percaya `curl -L` (bisa mengikuti pentalan ke halaman login
lalu melaporkan 200; sudah dua kali menipu). Menyentuh public/assets/** →
prod WAJIB rsync sesudah deploy. Test lokal → dev → verifikasi → PROD HANYA
kalau Ekky sebut prod. Tanpa emoji di UI. Selesai: entri docs/log-update.md +
salin log-update.md & status-proyek.md ke "G:\My Drive\Task Management\Sweet
Treats\". Catat keputusan ke memori.
```

---

## H. Chat: BERES-BERES FLAG ABSENSI (2026-08-14)

> Saran model: bebas, condong ke yang cepat. Ini sebagian besar kerja
> OPERASIONAL (menilai flag nyata), bukan koding. Perkakas borongannya
> sudah ada dan sudah live.

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini membereskan FLAG PENGAWASAN
yang menumpuk di prod, dan menutup celah yang membuatnya menumpuk.

Keadaan 14 Agu 2026 (prod): 15 flag `baru`, 7 `dicek`, 13 `selesai`.

BACA DULU:
· memori project_flag_borongan   ← perkakas borongan, sudah live
· memori project_kehadiran_reskin · project_absen_hp (PETA WiFi Bintaro)
· memori project_wifi_belajar_sendiri  ← kenapa flag WiFi muncul berulang

PERKAKASNYA SUDAH ADA — jangan bangun ulang. PengawasanController punya
JENIS_BORONGAN (ip_asing, pola_ip, luar_seharian), lintas hari (?semua=1),
centang jenis-jaringan, pilih-semua-sejenis, catatan wajib, dan banner saran.
Tugasnya MEMAKAINYA, lalu menambal yang bocor.

Yang perlu dikerjakan:
1. Bereskan 22 flag yang menggantung. Sebagian besar sebab-akibatnya sudah
   diketahui: rotasi prefix IPv6 Telkom (~mingguan) + jeda pendaftaran WiFi.
   Rizka & Verini & Ramdan contoh yang sudah terverifikasi bukan kejanggalan.
2. Nilai apakah ambang pola_ip = 3 ip_asing/bulan masih masuk akal. Rizka
   kena pola_ip padahal WiFi-nya konsisten (Bintaro + Kalideres, dua-duanya
   jaringan klinik) — flag turunan yang menyala karena rotasi nomor, bukan
   karena orangnya. Kalau ambangnya terlalu peka, ia melatih orang mengabaikan
   merah, dan kejanggalan asli tenggelam.
3. ⚠️ CEK DULU sebelum menyimpulkan apa pun: FOTO SELFIE adalah kebenaran
   dasar. 14 Agu aku sempat menyimpulkan IPv4 Pamulang salah daftar karena
   staf Bintaro ikut cocok — ternyata orangnya memang sedang di Pamulang, dan
   fotonya membuktikannya. Jangan menuduh jaringan sebelum melihat fotonya.

Aturan kerja: ini menyentuh DATA PROD, bukan kode — tiap pembereskan flag
adalah keputusan yang tercatat namanya. Jangan borongan-kan tanpa membaca.
Kalau ada perubahan kode, lewat dev dulu. Selesai: entri docs/log-update.md +
salin ke Google Drive. Catat keputusan ambang ke memori.
```

---

## I. Chat: JATAH CUTI (2026-08-14)

> Saran model: bebas. ⚠️ MULAI DENGAN AUDIT, bukan koding — rencananya
> menyesatkan (lihat di bawah).

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini mengerjakan JATAH CUTI.

⚠️ HAL PERTAMA YANG HARUS KAMU TAHU: docs/rencana/rencana-jatah-cuti.md
menulis "Belum ada koding" — ITU BASI DAN MENYESATKAN. Per 14 Agu 2026
sebagian besar sudah TERBANGUN DAN JALAN DI PROD:

· tabel att_cuti_periode & att_cuti_mutasi — berisi data nyata
· App\Services\CutiService lengkap: JATAH_KONTRAK=12, periodeBerjalan(),
  sisa(), potongDariPengajuan(), cutiBersama(), koreksi(),
  mulaiKontrakBaru(), resetKontrakKedaluwarsa()
· layar Absensi → Cuti (CutiController + absensi/cuti.blade.php), lengkap
  tombol "Kontrak Baru" yang SUDAH DIPAKAI SUNGGUHAN (perpanjangan kontrak
  Rokhmatun, 14 Agu, tercatat di audit_log)

Jadi TUGAS PERTAMA: audit apa yang sudah ada vs apa yang rencananya minta,
lalu segarkan rencananya. Jangan membangun ulang yang sudah jalan.

BACA: docs/rencana/rencana-jatah-cuti.md (dengan skeptis) · memori
project_jatah_cuti · feedback_alpa_palsu_aturan_kerja · project_roster_karyawan

Yang kelihatannya BELUM ada / belum diputuskan:
1. Satu asumsi terbuka di rencana: sisa positif HANGUS saat reset kontrak —
   sudah dijawab di dokumen tapi verifikasi implementasinya
   (mulaiKontrakBaru: saldo_awal = 12 + min(sisaLama, 0))
2. Input MASSAL cuti bersama + pengecualian per-orang — cutiBersama() ada di
   service, tapi apakah ada layarnya?
3. Saat HR menekan "Kontrak Baru", jatahnya masih otomatis 12. Ekky pernah
   minta HR/Finance WAJIB mengisi jatahnya sendiri (boleh 0, boleh 100) —
   belum dibangun. Ini bagian "hardcoded → bisa diatur admin" yang lebih besar.
4. Kontrak Alif & Reza masih dikosongkan (data belum ada).

⚠️ JEBAKAN: sisa cuti = saldo_awal − SUM(mutasi.hari) (CutiPeriode::sisa()).
Baris penyesuaian POSITIF berarti MEMOTONG. Jangan verifikasi dengan SQL
saldo_awal + SUM(...) — pernah bikin aku kira Putri punya 17 padahal 7.

Aturan kerja: branch SENDIRI dari main terbaru. SQL di dev dulu, dua kali
(idempoten). Ini menyentuh HAK KARYAWAN — salah hitung = orang kehilangan
cuti, jadi verifikasi pakai logika APLIKASI (CutiPeriode::sisa()), bukan SQL
buatan sendiri. Test lokal → dev → PROD hanya kalau Ekky sebut prod. Selesai:
entri docs/log-update.md + salin ke Google Drive. Catat keputusan ke memori.
```
