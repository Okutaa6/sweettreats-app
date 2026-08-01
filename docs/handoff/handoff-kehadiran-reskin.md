# Catatan Implementasi — Reskin Zona Absensi + Antrian §8 Peta Tema

Dikerjakan 2026-07-31 (chat "eksekusi visual"), branch `feature/absensi`,
**belum di-commit** (dibiarkan unstaged untuk review). Sumber desain:
`C:\Users\Ekky13\Downloads\handoff_kehadiran\` (paket claude.ai/design —
README-nya rinci, simpan folder itu sebagai rujukan).

---

## 1. Apa yang berubah

### A. Empat halaman zona Absensi (handoff kehadiran)

| Halaman | File | Bentuk perubahan |
|---|---|---|
| Kehadiran (Finance) | `resources/views/absensi/kehadiran.blade.php` + `AbsensiKehadiranController` | REWRITE — matriks gaya kalender |
| Kehadiran Saya | `resources/views/absensi/akun/absensi.blade.php` + `AkunAbsensiController` | REWRITE — layout handoff, form asli dipertahankan |
| Pengawasan Absen | `resources/views/absensi/pengawasan.blade.php` + `PengawasanController` | UBAH — kosakata, strip 2 baris, kartu KAH, keyboard |
| Absen HP | `resources/views/absensi/akun/absen.blade.php` | UBAH — izin kamera 1 tombol, timeline MASUK→PULANG |

**Komponen bersama BARU**: `resources/views/absensi/_kartu_absen_harian.blade.php`
— kamus 7 status (warna+ikon+kata, `window.KAH.ST`) + renderer kartu detail
(`KAH.render(props)`). Dipakai di 3 halaman (popover/sheet/panel/embedded).
**Kamus status sekarang tinggal DI SINI** — jangan salin hex status di halaman
lain; PHP yang butuh server-side menyalin dari `$STC` di absensi.blade.php
(sudah diberi komentar rujukan).

Detail per halaman:

1. **Kehadiran** — 3 view (Bulan kode-huruf / Pekan ikon+jam / Hari daftar per
   cabang), kartu ringkas H/T/A bisa diklik untuk MENYOROT (sel lain meredup),
   chip filter aktif, cari nama/role, kolom REKAP sticky kanan, drawer sebulan
   per orang (klik nama), popover `KartuAbsenHarian` dengan keyboard
   ←→ hari · ↑↓ orang · Esc (lompat hanya ke sel yang ada datanya), <900px
   otomatis view Hari + bottom sheet. Data tetap server-side per bulan
   (payload `KH_DATA`), navigasi bulan = reload dengan `?m=` (state view/d/
   branch dibawa lewat URL). Sel punya 3 titik indikator: biru foto, amber
   lembur, **cokelat kiri-atas = scan di cabang lain** (dipertahankan dari
   versi lama, tak ada di handoff).
2. **Kehadiran Saya** — kiri: identitas gelap + Kartu Hari Ini (timeline
   MASUK→PULANG, tombol tunggal Absen Masuk/Pulang → akun/absen, lapor koreksi)
   + rekap 7 status + tile telat/lembur/sisa cuti + Ajukan; kanan: kalender
   Sn–Mg + riwayat permohonan. Ketuk tanggal → KartuAbsenHarian (panel/sheet)
   → tombol "Lapor koreksi" membuka FORM ASLI (`_koreksi_form`) terprefill.
   Form Ajukan juga form asli (`_pengajuan_form` drawer). **View "Harian"
   (daftar reverse-kronologis) DIHAPUS** — kalender menggantikannya (keputusan
   handoff; kalau ada yang kangen, gampang dikembalikan dari git).
   Foto selfie milik sendiri dilayani rute BARU `akun/absensi/foto/{id}`
   (cek kepemilikan `user_id`), BUKAN rute pengawasan.
3. **Pengawasan** — tab "Foto Hari Ini" / "Perlu Dicek (N)", kata "flag" hilang
   dari semua teks UI; strip per cabang dipecah baris MASUK & PULANG (scroll
   sendiri-sendiri) + angka kelengkapan + nama yang belum absen pulang ditulis;
   toggle "Hanya yang perlu dicek" menyaring kedua baris; panel kanan dapat
   blok "Catatan Kehadiran Hari Itu" (KAH `chrome=false, showPhotos=false`,
   data att_day + role + lintas cabang + catatan ABG); keyboard `1 2 3` pilih
   penyelesaian (angka tercetak), `Enter` simpan, `J/K` pindah, `Esc` tutup.
   **Keputusan angka MASUK**: badge MASUK = jumlah orang berfoto saja (bukan
   x/y roster) — selama absen HP belum dipakai semua orang, penyebut roster
   akan menyesatkan. PULANG = x/y relatif ke yang masuk. Revisit saat absen HP
   full rollout.
4. **Absen HP** — layar izin kamera: judul "Absen pakai foto selfie", satu
   tombol "Lanjutkan" + keterangan "HP kamu akan menanyakan izin kamera setelah
   ini" ("Nanti saja" dihapus; tombol kembali di header tetap). Kartu status
   jadi timeline MASUK→PULANG (garis putus + "berjalan" saat belum pulang).

### B. Antrian §8 peta-tema — lihat `docs/spec/peta-tema.md` §8 (sudah dicentang)

Ringkas: burgundy Finance (`public/assets/css/zona.css` + body-class dari
layout), WiFi Cabang pindah menu ke Pusat Akun + grafit
(`database/sql/2026_07_31_wifi_ke_pusat_akun.sql`), sweep emoji 14 file,
token teal tertulis, gerbang sopan (`layouts/_gerbang_sopan.blade.php`),
polish palet avatar/border/marker.

Bonus fix menu: `app/Helpers.php` — sorot sidebar & judul header kini memilih
node menu paling spesifik (dulu `absensi/wifi` bikin "Upload Data" ikut nyala
dan judul header salah).

### C. Perbaikan kecil ikutan

- `jam pulang == jam masuk` (scan dobel beda detik) tidak lagi tampil
  "10:22–10:22" — jam pulang disembunyikan + catatan "scan tunggal" di kartu.
- Hex drift di konstanta STATUS `AbsensiKehadiranController` disamakan ke kamus
  (telat `#FBEFD9`, cuti `#3F6BAF`).

## 2. Keamanan — temuan sampingan (buat chat security hardening)

`GET pengawasan/foto/{id}` (PengawasanController::foto) TIDAK memeriksa
kepemilikan/role — siapa pun yang lolos node `pengawasan` bisa menarik foto
selfie SIAPA PUN by id (enumerable). Rute baru `akun/absensi/foto/{id}` sudah
kupasangi cek kepemilikan; yang pengawasan sengaja tidak kusentuh di chat ini
(wilayah security hardening). Tambahkan ke daftar sapu di
`docs/handoff/handoff-security-hardening.md` saat chat itu jalan.

## 3. Checklist deploy (dev & prod)

1. `git pull` (setelah di-commit & merge).
2. Jalankan `database/sql/2026_07_31_wifi_ke_pusat_akun.sql` → lalu
   `php artisan access:rebuild`. **Sudah dijalankan di LOKAL; dev & prod belum.**
3. `php artisan view:clear`.
4. Tidak ada composer/npm baru. Tidak ada perubahan skema DB lain.
5. Smoke test: /absensi/kehadiran (Finance), /akun (karyawan), /pengawasan
   (SPV, tanggal yang ada absen HP), /akun/absen (HP), /finance/pettycash
   (burgundy), /absensi/wifi (grafit + posisi menu di Pusat Akun),
   halaman desktop-first dari HP (gerbang sopan muncul sekali per sesi).

## 3b. Hasil audit menyeluruh (2026-07-31, sebelum review Ekky)

Audit otomatis Playwright: 9 lebar layar (360–1440) × semua halaman tersentuh,
interaksi (popover/drawer/sheet/form/keyboard/toggle), console & page error.
Hasil: **nol error JS, nol overflow-x** — kecuali halaman desktop-only setelah
menekan "Lanjut lihat" (memang by design boleh scroll samping).

Bug yang ketemu saat audit & SUDAH diperbaiki:
1. **Kehadiran + `?branch=`**: server dulu ikut memfilter payload → pilih
   "Semua Cabang" di layar tidak mengembalikan cabang lain tanpa reload.
   Fix: filter cabang murni client-side, server selalu kirim semua grup.
2. **Gerbang sopan saat HP diputar landscape (>900px)**: gerbang dulu nyangkut;
   sekarang auto-tutup saat media query lepas.
3. Teks hint form Ajukan masih menyebut "daftar absen" (view Harian yang sudah
   diganti kalender) → diganti "ketuk tanggalnya di kalender".
4. Kosmetik: hex `#ECECe8` → `#ECECE8` di zona.css; komentar header
   pengawasan.blade dirujuk ke handoff_kehadiran juga.
5. **Absen HP di halaman non-secure (http://)**: `navigator.mediaDevices` tidak
   ada sama sekali, jadi `getUserMedia` melempar TypeError **sinkron** yang tak
   tertangkap `.catch()` → layar kamera nyangkut hitam tanpa pesan apa pun
   (kegagalan-senyap). Sekarang dijaga: cek dulu ketersediaan API, lalu tampilkan
   layar "Kamera tidak bisa dibuka dari alamat ini" + arahan pakai alamat
   `https://` (langkah "izinkan di setelan" & tombol Coba Lagi disembunyikan,
   karena percuma). Jalur izin-ditolak biasa tetap seperti semula.
   → **Konsekuensi tes**: alur kamera Absen HP TIDAK bisa diuji dari HP lewat
   `http://<IP-komputer>:8899` — itu aturan browser, bukan bug aplikasi.
   Sisa halaman (layout, tombol, status) tetap bisa dites normal dari HP.

## 3c. Audit putaran kedua (2026-08-01) — celah sweep §8

Audit pertama hanya menguji 4 halaman absensi. Putaran ini menyisir **14 file
lain** yang tersentuh sweep emoji/palet avatar. Tiga bug ditemukan & diperbaiki:

1. **`checklist/my/weekform.blade.php` — markup SVG di dalam `.textContent`.**
   Emoji 📷 kuganti SVG di dua tempat yang memakai `.textContent`, sehingga
   yang tampil ke karyawan adalah teks mentah `<svg width="14"...>`, bukan ikon.
   Fix: `.textContent` → `.innerHTML`. Pengecekan hapus-otomatis 4 detik tetap
   memakai `.textContent.indexOf(...)` dan tetap benar (elemen SVG tidak
   menyumbang teks). Terverifikasi: hasilnya elemen `<svg>`, teks bersih.
2. **`ep-konten/calendar.blade.php` — SVG di dalam string yang dirender
   ter-escape.** Ikon 🎯 masuk ke `$kindOptions['khusus']['label']`, sedangkan
   label dirender `{{ }}` → user melihat kode SVG mentah di chip filter.
   Fix: ikon dipindah ke field `svg` sendiri (dirender `{!! !!}`, isinya statis),
   label kembali teks polos.
3. **Emoji di `app/Helpers.php` (`checklistInfoText`).** Sweep pertama hanya
   memindai `resources/views/`, tidak `app/`. Teks default banner Info
   my-checklist masih memuat 📢 dan 🦷 → sekarang bersih.
   ⚠️ Kalau di dev/prod admin pernah **menyimpan** teks Info sendiri lewat
   Setup Tugas, nilai DB itu menang atas default — emoji-nya harus dibersihkan
   manual dari halaman Setup Tugas. (Di lokal tabel `sys_setting` belum ada,
   jadi default yang dipakai.)

Pelajaran yang dipakai untuk sisa sweep: mengganti emoji tak boleh membabi buta —
harus dicek dulu konteks render (`textContent`? atribut? `{{ }}` vs `{!! !!}`?).
Seluruh repo sudah disisir ulang untuk pola yang sama: bersih.

**Belum sempat diuji (tak ada akun ber-password test untuk role-nya):**
`rawcustomer`, `rawdoctor` (palet avatar), `ep-breakdown`, `ep-konten` (ikon
target), `report` sales (ikon sparkle) — role Admin/Admin Backup/Sales/Super
Admin. Perubahannya kosmetik dan sudah diperiksa di tingkat kode + kompilasi
blade, tapi belum dilihat mata. Masuk daftar cek manual.

## 4. Belum dikerjakan (dari handoff §10 + antrian)

- Pasang pola Kehadiran baru ke tab "Kehadiran" di halaman gabungan mana pun —
  TIDAK relevan di app kita (tab Finance `_finance_tabs` tetap).
- Kurangi 5 tab Finance jadi ≤4 (usulan handoff; butuh keputusan).
- Ekspor Kehadiran (brief melarang menambah fitur — belum ada tetap belum ada).
- Pengawasan belum punya perilaku layar sempit (gerbang sopan menutupi
  sementara; kandidat dibuat tablet-friendly).
- Header Reservasi & halaman Role (setgroup) — nunggu koordinasi Reza.
