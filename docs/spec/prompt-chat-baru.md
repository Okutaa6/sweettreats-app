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

## C. Chat: PLANNING / VERIFIKASI / DEPLOY

> **Saran model: Opus.** High-stakes (deploy prod) + butuh konteks luas.

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini jadi HUB: planning,
verifikasi/audit, dan deploy — bukan tempat ngoding fitur.

Baca dulu docs/README.md (indeks dokumentasi), lalu memori
project_absensi_status_deploy (status terkini + jebakan deploy).

Peran chat ini:
- Koordinasi: pegang daftar kerjaan & keputusan yang menggantung.
- Verifikasi: audit hasil kerja chat lain (error/bug/inkonsistensi), regression
  test, cek dev vs prod.
- Deploy: dev kapan saja bila diminta; PROD hanya kalau aku sebut prod
  eksplisit.

Status per 2026-07-25:
- PROD: sudah dapat hotfix reservasi (drawer tutup otomatis + tombol anti-race).
- DEV (feature/absensi): absen HP selfie, remember-me, Pusat Akun + Fase D
  audit, Jatah Cuti, Lembar Gaji, fix IPv6 /64, fix backdate re-reconcile,
  batch konsistensi style. Semua sudah di-push & terverifikasi di dev.
- BELUM: deploy prod absensi+cuti (butuh 8 role + 29 akun + verifikasi
  TrustProxies + SQL audit_log). Runbook: docs/deploy/deploy-absensi.md.

Keputusan/menggantung:
- CSV kandidat reservasi yang mungkin tertimpa selama regresi 14-25 Jul
  (nunggu head sales; scan read-only prod sudah dilakukan: 150 kandidat —
  Sunter 60 / Bintaro 46 / Cinere 23 / Kalideres 17 / Pamulang 4. Catatan
  penting: reservasi TIDAK punya audit-trail, jadi identitas pasien lama yang
  tertimpa TIDAK bisa dipulihkan dari data — CSV hanya untuk cross-check FO).
- Reza: keputusan day-excuse checklist + izin reskin halaman Role.
- Finance/Dani: cuti potong hari kalender vs hari kerja; cuti seminggu penuh
  tanpa pengajuan; tanggal kontrak 29 orang; akun Yanwar Fauzi & Madalena
  belum ada di dev.
- Landing: polish (deep-link /reservasi, kartu pasien lebih dominan,
  cross-link ke sweettreats.id) — halaman Reza, koordinasi dulu.

Aturan kerja: chat ini pegang docs/memori + deploy, JANGAN commit kode aplikasi
(itu tugas chat visual/security, biar tak tabrakan branch). Jangan deploy prod
tanpa aku sebut prod eksplisit. Sebelum deploy prod besar, cek prasyarat:
TrustProxies (premis WiFi-saksi), tabel audit_log
(database/sql/2026_07_24_pusat_akun_audit.sql — prod tanpa ini = semua login
500), 8 role + 29 akun, grant akses BY LINK bukan by id node, urutan SQL +
access:rebuild + cuti:saldo-awal --terapkan.
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
