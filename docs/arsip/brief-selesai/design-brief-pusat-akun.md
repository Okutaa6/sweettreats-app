# Design Brief: Pusat Akun — buat claude.ai/design

2026-07-24. Pasangan dari `rencana-pusat-akun.md` (keputusan & fungsi ada di sana).
Pola kerja sama dengan brief absen HP: design di claude.ai/design, handoff (HTML/PNG)
balik ke Claude Code buat implementasi.

## Konteks produk

Sweet Treats = klinik gigi 5 cabang, ±36 akun (bertambah). **Pusat Akun** = ruang kerja
IT (2 orang: Ekky & Reza) buat kelola akun, akses, dan — bagian terpentingnya —
**melihat masalah senyap SEBELUM orang teriak**. Mengganti section lama "User Access"
(User/Role/Akses/Setting) + satu halaman baru "Kesehatan".

**Tujuan jangka panjang yang harus dijaga desainnya** (jangan didesain buntu):
- Monitoring user tumbuh (makin banyak akun saat migrasi prod: +8 role, +29 akun).
- Audit log bakal diperluas (fase 2) merekam perubahan sensitif → riwayat per-user
  di detail user harus siap menampung lebih dari sekadar login/logout.
- Kelak ada sistem ticketing TERPISAH — sisakan "rumah" konseptual (mis. area aksi/
  inbox IT), tapi JANGAN mendesain ticketing sekarang.

**Pemakainya IT (teknis)** — boleh lebih padat-informasi daripada layar staf klinik,
TAPI tetap bahasa Indonesia yang jelas; istilah teknis boleh (role, grant, cache)
asal konsisten.

## Batasan teknis yang mengikat

- Bootstrap 4 · **FontAwesome 5.8.2** (BUKAN 6 — ikon FA6-only jadi kotak kosong) + dripicons.
- Keluarga visual SAMA dengan handoff absen HP / Pengawasan: cream `#F4ECE2`, coklat
  brand `#461500` (terkunci), aksen `#C77A1E/#E8842B`, hijau `#2F8E55`, merah `#C0392B`,
  Plus Jakarta Sans. Ini nambah layar ke keluarga yang ada, bukan bahasa baru.
- **Desktop-first** (IT kerja di komputer). Cukup tampak wajar di layar sempit, tak
  perlu mobile-first.
- Ikon: SVG garis minimalis (currentColor) — TANPA emoji (keputusan user).
- Header pattern resmi shell DataCenter (top-bar tipis); jangan bikin shell sendiri.

> ⚠️ **Cara pakai (penting):** claude.ai/design paling bagus **1 layar per sesi**.
> Jangan lempar file ini utuh (isinya 6 layar) — dia bakal ngerjain "bintang"-nya saja.
> Pakai brief per-layar yang berdiri sendiri:
> - Kesehatan → §1 di bawah (atau sesi tersendiri)
> - User (list+detail+reset) → `design-brief-pusat-akun-user.md`
> - Akses (+guardrail) → `design-brief-pusat-akun-akses.md`
> - Role + Setting → `design-brief-pusat-akun-role-setting.md`

## Layar

### 1. Kesehatan (halaman utama section — BINTANG)

"Dokter umum"-nya sistem akun. Isi:
- **Ringkasan atas**: total masalah terbuka (angka besar) + kalimat status
  ("Semua sehat" hijau / "N masalah butuh perhatian" merah-oranye).
- **Grid kartu detektor** (7 kartu): Landing terkunci · Scan yatim · Grant belum
  rebuild · Node menu dobel · Approval nyasar · Akun tak bertuan · Perangkat
  menggantung. Tiap kartu: ikon, judul awam, ANGKA, satu kalimat "apa artinya",
  state: sehat (hijau, angka 0) vs bermasalah (merah/oranye).
- **Klik kartu → panel/daftar terdampak**: baris per kasus (siapa/apa, sejak kapan)
  + blok "Cara benerin" (langkah singkat + link ke halaman terkait, mis. "buka Role →
  perbaiki landing"). BUKAN tombol auto-fix.
- Badge angka total di item menu sidebar "Kesehatan".

### 2. User — list

- Toolbar: cari nama/email + filter Role, Cabang, Status + indikator jumlah.
- Tabel: Avatar+Nama, Email, Role, Cabang, **Aktivitas** (dot status: hijau "baru saja",
  kuning "hari ini", abu "N hari lalu", plus teks), Perangkat (angka), Status akun,
  badge ⚠ kecil kalau user kena detektor Kesehatan.
- Baris diklik → Detail user. Tombol "+ Akun baru" (form lama, re-skin).

### 3. User — detail (BARU, layar terpenting kedua)

Layout 2 kolom:
- Kiri: kartu identitas (avatar, nama, email, role+cabang, status, sejak kapan,
  last login & last seen) + **kartu Aksi**: Reset Password · Nonaktifkan/Aktifkan ·
  Paksa Logout · (Cabut semua perangkat). Tiap aksi = dialog konfirmasi singkat.
- Kanan: **Perangkat diingat** (list: label "Android · Chrome", terakhir dipakai,
  tombol cabut per-perangkat) + **Riwayat** (timeline audit: login/logout sekarang;
  desain harus muat entri jenis lain nanti — "X mengubah role Y", fase 2).
- **State hasil reset password**: kartu password sementara yang tampil SEKALI
  (huruf besar, tombol salin, catatan "wajib diganti saat login berikutnya",
  peringatan "tak akan ditampilkan lagi").

### 4. Role

Pohon hierarki (data sudah ada) di-re-skin: node = kartu kecil (nama role, jumlah
anggota, ⚠ kalau landing-nya tak di-grant). Panel kanan: detail role (anggota,
landing, akses ringkas). CRUD-nya pakai form lama (re-skin ringan).

### 5. Akses (+ guardrail — bagian penting)

- List node akses (pohon parent-child) dengan penanda: node yatim (tak di-grant ke
  role mana pun), node dobel (link sama).
- **Dialog guardrail** saat hapus/ubah node yang masih di-grant: sebut role yang
  bakal kehilangan, minta konfirmasi eksplisit.
- **Banner/tombol "Terapkan perubahan (rebuild akses)"** yang muncul SETELAH ada
  perubahan — mengingatkan bahwa perubahan belum hidup sebelum rebuild (sumber
  kegagalan-senyap nyata).

### 6. Setting — re-skin ringan form profil sistem (nama, logo). Ikut keluarga.

## Komponen bersama

- Dialog konfirmasi aksi (judul, dampak 1 kalimat, tombol batal/lanjut).
- Toast/alert sukses-gagal konsisten.
- Empty state ("Semua sehat", "Tidak ada perangkat") yang hangat, tanpa emoji.

## Yang TIDAK perlu didesain

- Ticketing (fitur terpisah, nanti).
- Halaman audit log global lama (`auditlog`) — cukup timeline di detail user.
- Mobile layout khusus.
- Form CRUD lama secara mendetail — cukup arah re-skin (field sama).

## Deliverable

Seperti handoff sebelumnya: HTML/PNG per layar per state (folder ~/Downloads),
desktop ±1440px. Prioritas: **Kesehatan → User detail (termasuk state reset) →
User list → Akses → Role → Setting**.
