# Design Brief: Absen HP (Selfie) — buat claude.ai/design

2026-07-24. Pasangan dari `rencana-absen-hp.md` (fundamental & keputusan ada di sana).
Pola kerja sama dengan brief shell/absensi sebelumnya: design di claude.ai/design,
handoff (HTML/PNG) balik ke Claude Code buat implementasi.

## Konteks produk (buat yang belum kenal)

Sweet Treats = klinik gigi 5 cabang. DataCenter = web app internal (Laravel + Bootstrap 4).
Karyawan (29 orang: front office, asisten dokter, general services, office) akan absen
masuk/pulang pakai **HP masing-masing lewat browser** — selfie langsung dari kamera,
membelakangi **poster titik absen** yang ditempel di tiap cabang. Sistem mencocokkan IP
WiFi cabang; yang janggal di-flag buat direview SPV (satu orang, namanya Anisa) di desktop.
Absen tidak pernah ditolak sistem — janggal = flag, bukan blokir.

**Pemakainya orang awam.** Semua label bahasa Indonesia sehari-hari, tanpa jargon:
"Absen Masuk", "Absen Pulang", "Kamu belum absen pulang kemarin", "Menunggu dicek" —
BUKAN "check-in", "clock out", "pending review".

## Batasan teknis yang mengikat desain

- **Stack CSS**: Bootstrap 4. Ikon **FontAwesome 5.8.2** (BUKAN 6 — nama ikon FA6-only
  jadi kotak kosong) + dripicons.
- **Warna brand**: coklat `#461500` = terkunci (identitas). Aksen pendukung: keluarga
  brown/cream yang sudah dipakai halaman "Absensi Saya" (skin claude.ai/design
  sebelumnya) — HARAP KONSISTEN dengan handoff `handoff_absensi` & `design_handoff_shell`
  yang sudah ada; ini nambah layar ke keluarga yang sama, bukan bikin bahasa visual baru.
- **Header pattern resmi**: shell top-bar tipis (hamburger + judul + bell) + PersonalHeader
  (kartu identitas coklat). Layar baru ikut pattern ini, jangan bikin header sendiri.
- Layar staff = **mobile-first 360px**, dipakai satu tangan sambil berdiri — tombol utama
  harus kejangkau jempol (bawah layar). Layar Anisa & admin = desktop.
- Kamera = elemen `<video>` live kamera depan, rasio ~3:4 portrait. Foto hasil = JPEG kecil.

---

## Layar 1 — Absen (mobile, layar paling penting)

Diakses dari "Absensi Saya" (tombol/FAB besar). Satu layar, state berubah-ubah:

1. **Idle / siap absen**: status hari ini di atas ("Kamu belum absen masuk" / "Masuk
   09.02 ✓ — belum absen pulang"), satu tombol besar sesuai konteks (**Absen Masuk** pagi
   / **Absen Pulang** setelahnya). Pengingat kecil: "Pastikan HP terhubung WiFi cabang &
   berdiri di depan poster titik absen."
2. **Minta izin kamera**: penjelasan singkat kenapa (sekali saja, saat pertama), state
   kalau izin DITOLAK (instruksi buka setting browser).
3. **Preview live**: video kamera depan penuh, panduan oval wajah + hint "poster di
   belakangmu", tombol rana besar di bawah.
4. **Konfirmasi**: foto hasil, dua aksi — **Kirim** (utama) / Ulangi.
5. **Mengirim**: progress; koneksi lemot harus tetap terasa hidup (foto cuma ~50 KB).
6. **Sukses**: jam (dari server) + cabang yang terdeteksi ("Absen masuk 09.02 —
   Sweet Treats Cinere ✓") + sapaan hangat. Ini momen tiap hari 2×, boleh menyenangkan.
7. **Cabang tak terdeteksi** (IP tak dikenal): TIDAK memblokir — info tenang ("WiFi tidak
   dikenali — pilih cabangmu") + pilihan 5 cabang, lanjut kirim. Nanti dicek SPV; nada
   netral, bukan menuduh.
8. **Gagal kirim**: tombol coba lagi, foto tidak hilang.

Tambahan kecil di layar "Absensi Saya" yang sudah ada: kartu pengingat kalau kemarin
lupa absen pulang → link ke Koreksi Absensi.

## Layar 2 — Review SPV "Pengawasan Absen" (desktop, Anisa)

Prinsip: **kejanggalan harus kelihatan tanpa dicari**. Tiga area:

1. **Strip foto harian** — per cabang satu baris, semua selfie hari itu dijejer
   (thumbnail + nama + jam). Karena latar poster seragam, foto yang beda sendiri langsung
   nongol. Filter tanggal + cabang. Klik foto → detail.
2. **Antrian flag** (daftar kerja, bukan laporan): item = orang + tanggal + jenis flag
   (bahasa awam: "Absen dari luar WiFi cabang", "Lupa absen pulang", "Satu HP dipakai 2
   akun", "Jam janggal") + status (**Baru / Dicek / Selesai**). Flag serentak satu cabang
   DIGRUPING jadi satu kartu ("8 absen Sunter pagi ini di luar WiFi — kemungkinan WiFi
   gangguan") dengan aksi selesaikan sekaligus.
3. **Panel detail flag**: foto besar, sinyal (jam server, cabang/IP cocok-tidaknya,
   perangkat), riwayat singkat orangnya (flag bulan ini, lupa-pulang bulan ini), lalu
   **penyelesaian wajib pilih satu**: Wajar / Perlu Koreksi / Pelanggaran + catatan
   singkat. Aksi pintas: **"Daftarkan IP ini ke cabang…"** (untuk jalur WiFi sah yang
   belum terdaftar).

Header kecil: hitungan flag terbuka + peringatan "periode gaji tidak bisa ditutup selama
masih ada flag terbuka".

## Layar 3 — Admin "WiFi Cabang" (desktop, IT)

CRUD sederhana daftar IP per cabang: kolom cabang, IP, label ("jalur utama", "ruang
dokter"), status aktif/nonaktif, terakhir terlihat. Form tambah kecil. Ini halaman teknis
yang jarang dibuka — fungsional > cantik, asal konsisten skin.

## Layar 4 — Login: "Ingat perangkat ini"

Cuma tambahan checkbox di form login yang ada + teks bantu satu kalimat ("Hanya centang
di HP pribadimu"). Jangan redesign login.

## Layar 5 — Koreksi Absensi

SUDAH ada desainnya di handoff lama (drawer "Apa yang keliru?", tipe pengajuan ke-6 di
Absensi Saya) — tidak perlu didesain ulang; cantumkan di flow sebagai tujuan link dari
state gagal/lupa.

## Bonus: Poster Titik Absen (print, per cabang)

A3/A4 tempel dinding. Identitas per cabang harus KONTRAS satu sama lain (nama cabang
sangat besar + blok warna beda per cabang) — poster ini akan jadi LATAR selfie, jadi
harus gampang dikenali di foto kecil 640px. Isi: nama cabang, 3 langkah absen
(WiFi → buka Absensi Saya → selfie di sini), "kalau gagal hubungi SPV". Brand coklat
`#461500` + aksen warna cabang.

## Yang TIDAK perlu didesain

- QR code apa pun (dicoret dari desain sistem — lihat rencana §3).
- Halaman rekap Finance / periode gaji (sudah ada).
- Face-recognition UI, peta/GPS (tidak dipakai).

## Deliverable

Seperti handoff sebelumnya: mockup HTML/PNG per layar per state (folder ~/Downloads),
mobile 360px buat layar 1, desktop buat layar 2–3. Prioritas urutan: Layar 1 → 2 → poster
→ 3 → 4.
