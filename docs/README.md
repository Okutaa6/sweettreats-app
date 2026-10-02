# Indeks Dokumentasi — SweetTreats

Peta isi folder `docs/`. **Mulai dari sini** kalau bingung cari apa.

## Mau cari apa?

| Kalau kamu mau… | Buka |
|---|---|
| **Lihat potret utuh proyek (status, utang, rencana)** | `status-proyek.md` |
| **Kirim update milestone ke task-management (Gemini)** | `log-update.md` (entri teratas) |
| Tahu aturan warna/tema per halaman | `spec/peta-tema.md` |
| Tahu pola interaksi (chevron, toast, tombol-mati, dll) | `spec/pola-interaksi.md` |
| Tahu "kalau X gimana?" soal absensi & cuti | `spec/sop-absensi-cuti.md` |
| Buka chat baru untuk security / peta tema / deploy | `spec/prompt-chat-baru.md` |
| Paham cara kerja mesin absensi (scan → att_day) | `rencana/rencana-rekonsiliasi-absensi.md` |
| Paham absen HP selfie (WiFi = saksi, flag) | `rencana/rencana-absen-hp.md` |
| Paham jatah cuti per kontrak | `rencana/rencana-jatah-cuti.md` |
| Paham pengajuan ROP (expenses digital) | `rencana/rencana-pengajuan-rop.md` |
| Paham role Konsultan & restrukturisasi Supervisor | `rencana/rencana-konsultan-struktur.md` |
| Paham dua pagar pengajuan lembur (kembar + chip) | `rencana/rencana-pagar-lembur.md` |
| Paham belajar-sendiri WiFi untuk IPv4 | `rencana/rencana-wifi-ipv4-belajar.md` |
| Paham kenapa libur cabang ikut tempat bertugas (rolling) | `rencana/rencana-libur-cabang-bertugas.md` |
| Paham layar admin alur persetujuan + delegasi | `rencana/rencana-alur-persetujuan.md` |
| Paham fitur Aturan Kerja (jam kerja/libur — Ekky) | `rencana/rencana-aturan-kerja.md` |
| Paham fitur Penempatan (rolling cabang — Reza) | `rencana/rencana-penempatan.md` |
| Deploy absensi ke dev/prod | `deploy/deploy-absensi.md` |
| Tahu apa saja yang belum naik prod (+ peta cabang GitHub) | `deploy/manifest-prod.md` |
| Kerjakan hardening keamanan | `handoff/handoff-security-hardening.md` |
| Paham reskin Kehadiran/zona Absensi + cara deploy-nya | `handoff/handoff-kehadiran-reskin.md` |
| Kasih dokumen ke Reza | `untuk-reza/` |
| Kasih dokumen ke tim ops/management | `untuk-tim/` |
| Tahu modul mana yang beneran dipakai vs mangkrak | `untuk-tim/rapor-adopsi-*.md` |
| Kenalin proyek ke orang luar (bos/konsultan/UI-UX) | `untuk-tim/peta-modul-data-center.html` |
| Lihat spesifikasi API vendor (Cekat/Omnicare) | `api/` |
| Cari brief desain yang belum dikerjakan | `brief-desain/` |
| Cari dokumen lama / sudah selesai | `arsip/` |

## Isi tiap folder

### `rencana/` — rencana fitur (AKTIF)
Rencana teknis yang **masih jadi rujukan kode**. ⚠️ Komentar di ~43 file
PHP/SQL menunjuk ke sini (mis. `docs/rencana/rencana-rekonsiliasi-absensi.md §5.3`) —
**kalau file di sini dipindah/di-rename, update juga komentar-komentar itu.**

- `rencana-rekonsiliasi-absensi.md` — mesin absensi: scan → att_day, aturan
  kerja, dedup, dini-hari, lintas cabang. Dirujuk 20× dari kode.
- `rencana-absen-hp.md` — absen HP selfie: WiFi sebagai saksi, flag, remember
  me. Dirujuk 12× dari kode.
- `rencana-jatah-cuti.md` — jatah cuti per kontrak 12 bulan, ledger, cuti
  bersama. Dirujuk 7× dari kode.
- `rencana-pusat-akun.md` — Pusat Akun: Kesehatan, User, Akses, Role, Setting.
- `rencana-konsultan-struktur.md` — role Konsultan (wajib absen, nol wewenang
  ACC) + Supervisor membawahi Coordinator Dentist/FO/Koordinator GS. Berisi
  peta mesin ACC: `att_approval_rule` vs bagan organisasi, dan kenapa
  `sort_order` bukan lapis berjenjang.
- `rencana-pagar-lembur.md` — dua pagar: pengajuan lembur KEMBAR (Afrina 115
  menit dibayar 230) + peringatan chip yang datang sesudah keputusan.
- `rencana-wifi-ipv4-belajar.md` — belajar-sendiri untuk IPv4, cocok-penuh,
  NOL pelebaran. Kalideres & Cinere sempat nol IPv4 terdaftar.
- `rencana-libur-cabang-bertugas.md` — libur per-cabang harus mengikuti cabang
  tempat orang BERTUGAS (rolling), bukan cabang asalnya. Berisi jebakan `$home`
  yang dipakai tiga arti, dan catatan bahwa kalender libur tak pernah tampil di
  layar mana pun.
- `rencana-alur-persetujuan.md` — layar admin untuk `att_approval_rule` +
  delegasi penyetuju. Empat pagar wajib; pernah ada insiden "SEMUA cuti
  auto-approve".

### `spec/` — spesifikasi & aturan kerja (AKTIF)
- `peta-tema.md` — **peta tema/zona visual**: cokelat = default; Marketing teal,
  IT grafit, Finance burgundy. 3 lapis warna, mapping per halaman,
  device-intent (mobile vs desktop), aturan emoji→ikon. **Baca sebelum
  bikin/reskin halaman apa pun.**
- `pola-interaksi.md` — kamus pola interaksi (afordansi, umpan balik, alasan
  tombol mati, empty state) + mapping status per halaman + backlog prioritas.
- `sop-absensi-cuti.md` — skenario dunia nyata absensi & cuti: apa yang sistem
  lakukan otomatis vs apa yang manusia harus lakukan (HP mati, WiFi mati,
  backdate, cuti minus, tutup periode, dll).
- `prompt-chat-baru.md` — prompt siap-tempel untuk membuka chat baru per alur
  kerja (security / peta tema / planning-deploy).

### `brief-desain/` — brief untuk claude.ai/design (BELUM SELESAI)
Brief layar yang **belum dieksekusi**. Yang sudah jadi halamannya dipindah ke
`arsip/brief-selesai/`.


⚠ **Pengecualian: berkas ortho di sini TIDAK menunggu dikerjakan** — modulnya
sudah jadi. Tiga berkas menggambarkan keadaan SEKARANG dan saling menunjuk:

- `brief-tracking-ortho-berjalan.md` — apa yang dilihat Sales di layar
- `design-system-tracking-ortho.md` — 30 token, 273 kelas, 4 grid, responsif,
  animasi, kelas yatim, dan daftar aturan yang ada tapi tidak berlaku
- `alur-fungsi-tracking-ortho.md` — 11 rute, 11 aksi, 8 service, 6 perintah,
  4 model, observer, 23 view, 11 blok JavaScript; tiap fungsi disertai validasi,
  pagar, kolom yang ditulis, dan bisa/tidaknya diurungkan

`design-brief-tracking-ortho.md` dan `revisi-3-*` disimpan sebagai riwayat
rancangan, sebagian isinya sudah tidak berlaku (lihat blok peringatan di kepala
masing-masing).
- `design-brief-cuti.md` — layar Cuti Finance (rekap + cuti bersama).
- `design-brief-rop.md` — 4 layar Pengajuan/ROP (form + riwayat pengaju
  cokelat; antrian + rekap Finance burgundy). Satu layar per sesi.

### `deploy/` — runbook & koordinasi deploy
- `deploy-absensi.md` — runbook deploy absensi (dev & prod), termasuk daftar
  temuan audit yang sengaja ditunda (§10).
- `manifest-prod.md` — **daftar isi migrasi prod**: 30 SQL + 19 migration +
  langkah manual + inventaris cabang GitHub (mana yang ikut, mana yang tidak).
- `deploy-coordination.md` — koordinasi deploy antar branch/orang.
- `deploy-ortho.md` — runbook Tracking Ortho: 6 SQL + `access:rebuild` + 6
  perintah isi data, berurutan. **Dua kegagalannya sunyi** (lupa
  `access:rebuild` → 404 di SEMUA halaman; lupa `ort:rollup` → irama kosong
  untuk semua pasien, tanpa pesan galat).
- `cekat-DEPLOY.md`, `omnicare-DEPLOY.md` — deploy integrasi vendor.
- `test-flow-deploy-prod.md` / `.html` — alur uji sebelum naik prod.

### `handoff/` — serah-terima kerjaan antar sesi/chat
- `handoff-security-hardening.md` — brief lengkap hardening keamanan
  (rate-limit P0/P1 + mandat menyapu lubang lain). Dikerjakan di chat terpisah.
- `handoff-kehadiran-reskin.md` — catatan implementasi reskin zona Absensi
  (Kehadiran kalender, Kehadiran Saya, Pengawasan, Absen HP, komponen
  KartuAbsenHarian) + antrian §8 peta-tema + checklist deploy-nya.

### `api/` — spesifikasi API & proposal vendor
`cekat-api.md` / `.http` / `.pdf` · `cekat-TEST_FLOW.html` ·
`proposal-sweet-treats-api-omnicare-v2.md` / `.pdf`
*(PDF & proposal omnicare `.md` di-gitignore — lokal saja.)*

### `untuk-reza/` — dokumen untuk Reza
`handoff-reza.md` · `Panduan-Git-Reza.pdf` · `git-workflow-reza.html` ·
`git-workflow-guide.html`
*(PDF & git-workflow-reza.html di-gitignore.)*

### `untuk-tim/` — dokumen untuk tim ops / management
`sample-rekam-medis-*` (contoh tampilan rekam medis) ·
`presentasi-data-center-h1-2026.pdf` · `laporan-audit-sales-presentasi.html`

### `arsip/` — sudah lewat / sudah dieksekusi
Dipisah berdasarkan **alasan** diarsipkan:

- `brief-selesai/` — brief desain yang halamannya **sudah jadi**: absen-hp,
  absensi, absensi-karyawan, shell, pusat-akun (4 file), kehadiran-kalender.
  Disimpan sebagai rujukan "kenapa desainnya begini".
- `test-flow/` — dokumen uji sekali-pakai: TEST_FLOW umum, editorial plan,
  durasi akses cabang, petty cash, reservasi multipasien, rekam medis.
- `accurate/` — proyek integrasi Accurate (**di-pause**): mapping akun, sample
  faktur.
- `laporan-lama/` — audit sales Maret 2026, proposal laporan sales otomatis.

---

## Aturan merawat folder ini

1. **Satu tema = satu folder.** Jangan taruh file baru di root `docs/`.
2. **Kalau memindah/rename file di `rencana/`**, cari dulu rujukannya:
   `rg -n "docs/rencana/<nama-file>" app/ database/ resources/ routes/`
   lalu update komentarnya. Sama untuk memori Claude.
3. **Brief desain yang sudah dieksekusi** → pindah ke `arsip/brief-selesai/`.
4. **File binary/sensitif** (PDF, proposal vendor, panduan internal) di-gitignore
   — lihat `.gitignore` bagian docs. Simpan lokal, jangan push.
5. Kalau bikin dokumen baru yang jadi rujukan jangka panjang, **tambahkan
   barisnya ke tabel "Mau cari apa?" di atas.**
