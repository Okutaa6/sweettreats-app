# Indeks Dokumentasi — SweetTreats

Peta isi folder `docs/`. **Mulai dari sini** kalau bingung cari apa.

## Mau cari apa?

| Kalau kamu mau… | Buka |
|---|---|
| Tahu aturan warna/tema per halaman | `spec/peta-tema.md` |
| Tahu pola interaksi (chevron, toast, tombol-mati, dll) | `spec/pola-interaksi.md` |
| Tahu "kalau X gimana?" soal absensi & cuti | `spec/sop-absensi-cuti.md` |
| Buka chat baru untuk security / peta tema / deploy | `spec/prompt-chat-baru.md` |
| Paham cara kerja mesin absensi (scan → att_day) | `rencana/rencana-rekonsiliasi-absensi.md` |
| Paham absen HP selfie (WiFi = saksi, flag) | `rencana/rencana-absen-hp.md` |
| Paham jatah cuti per kontrak | `rencana/rencana-jatah-cuti.md` |
| Paham pengajuan ROP (expenses digital) | `rencana/rencana-pengajuan-rop.md` |
| Deploy absensi ke dev/prod | `deploy/deploy-absensi.md` |
| Tahu apa saja yang belum naik prod (+ peta cabang GitHub) | `deploy/manifest-prod.md` |
| Kerjakan hardening keamanan | `handoff/handoff-security-hardening.md` |
| Paham reskin Kehadiran/zona Absensi + cara deploy-nya | `handoff/handoff-kehadiran-reskin.md` |
| Kasih dokumen ke Reza | `untuk-reza/` |
| Kasih dokumen ke tim ops/management | `untuk-tim/` |
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

- `design-brief-cuti.md` — layar Cuti Finance (rekap + cuti bersama).
- `design-brief-rop.md` — 4 layar Pengajuan/ROP (form + riwayat pengaju
  cokelat; antrian + rekap Finance burgundy). Satu layar per sesi.

### `deploy/` — runbook & koordinasi deploy
- `deploy-absensi.md` — runbook deploy absensi (dev & prod), termasuk daftar
  temuan audit yang sengaja ditunda (§10).
- `manifest-prod.md` — **daftar isi migrasi prod**: 30 SQL + 19 migration +
  langkah manual + inventaris cabang GitHub (mana yang ikut, mana yang tidak).
- `deploy-coordination.md` — koordinasi deploy antar branch/orang.
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
