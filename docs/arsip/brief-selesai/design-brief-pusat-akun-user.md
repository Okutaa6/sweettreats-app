# Design Brief: Pusat Akun — layar USER (list + detail)

2026-07-24. **Satu layar per sesi claude.ai/design.** Ini bagian dari keluarga
"Pusat Akun" (induk: `design-brief-pusat-akun.md`); Kesehatan sudah didesain terpisah.
File ini berdiri sendiri — cukup pakai ini buat mendesain layar User.

## Konteks singkat

Sweet Treats = klinik gigi 5 cabang, ±36 akun. **Pusat Akun** = ruang kerja IT
(2 orang) buat kelola akun & akses. Layar **User** = daftar semua akun + halaman
detail per user tempat IT bertindak (reset password, nonaktifkan, cabut perangkat,
paksa logout). Pemakainya teknis — boleh padat info, tetap bahasa Indonesia jelas.

## Batasan visual (WAJIB — samakan keluarga absen HP / Pengawasan / Kesehatan)

- Cream `#F4ECE2`, coklat brand `#461500` (terkunci), aksen `#C77A1E`/`#E8842B`,
  hijau `#2F8E55`, kuning `#9a6b00`, merah `#C0392B`. Font Plus Jakarta Sans.
- **Desktop-first** (±1440px). Ikon SVG garis (currentColor), **TANPA emoji**.
- Header pattern shell DataCenter (top-bar tipis) — jangan bikin shell sendiri.
- FontAwesome 5.8.2 (bukan 6) + dripicons saat implementasi; di prototype pakai SVG.

## Layar 1 — User (list)

- **Toolbar**: kotak cari (nama/email) + filter Role, Cabang, Status akun + hitungan
  ("Menampilkan N akun") + tombol "+ Akun baru".
- **Tabel**: Avatar+Nama · Email · Role · Cabang · **Aktivitas** (dot + teks:
  🟢 "baru saja" / 🟡 "hari ini" / abu "N hari lalu" / abu "belum pernah") ·
  **Perangkat** (angka jumlah perangkat "diingat") · Status (Aktif/Nonaktif badge) ·
  penanda ⚠ kecil kalau user ini kena detektor Kesehatan (mis. akun tak bertuan).
- Baris diklik → buka **detail**. Baris akun nonaktif tampil redup.

## Layar 2 — User (detail) — LAYAR TERPENTING

Layout **2 kolom**:

**Kolom kiri — identitas + aksi:**
- Kartu identitas: avatar besar, nama, email, badge role + cabang, status akun,
  "dibuat sejak", "login terakhir", "terakhir aktif".
- **Kartu Aksi** (tombol + dialog konfirmasi masing-masing):
  - **Reset Password** → memicu state hasil (lihat bawah)
  - **Nonaktifkan / Aktifkan akun**
  - **Paksa Logout** (tendang semua sesi aktif — buat HP hilang / akun disusupi)
  - (Cabut semua perangkat)
- Tiap aksi = dialog konfirmasi ringkas (judul, dampak 1 kalimat, batal/lanjut).

**Kolom kanan — perangkat + riwayat:**
- **Perangkat diingat**: list kartu (label "Android · Chrome", "terakhir dipakai
  <waktu>", tombol **Cabut** per perangkat). Empty state hangat kalau kosong.
- **Riwayat aktivitas**: timeline (login/logout sekarang). PENTING: desainnya harus
  MUAT jenis entri lain nanti (fase 2 audit: "mengubah role", "mereset password X",
  "menyelesaikan flag") — jadi pola timeline generik (ikon + aksi + waktu + siapa),
  bukan cuma "login/logout".

**State hasil Reset Password** (penting — sekali tampil):
- Setelah IT klik Reset → muncul **kartu password sementara**: password acak huruf
  besar (mis. `K7-Mferl-92`), tombol **Salin**, catatan tegas: "Kasih ke orangnya.
  Wajib diganti saat login berikutnya." + peringatan "Tidak akan ditampilkan lagi."
- Nada: aman & tenang, bukan alarm. Ini kejadian rutin (staf lupa password → lapor IT).

## Komponen

- Dialog konfirmasi aksi (reusable). Toast sukses/gagal. Empty states hangat tanpa emoji.
- Badge status akun (Aktif hijau / Nonaktif abu). Dot aktivitas (hijau/kuning/abu).

## Deliverable

HTML/PNG per layar per state (folder ~/Downloads), desktop ±1440px.
Urutan: **User detail (termasuk state reset password) → User list**.
