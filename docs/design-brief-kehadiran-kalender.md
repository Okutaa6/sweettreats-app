# Design Brief — Kehadiran (gaya Google Calendar)

⚠️ **SATU layar per sesi claude.ai/design.** Brief ini = 1 layar: **Kehadiran**
(rekap kehadiran semua karyawan per bulan, dilihat Finance/admin). Cakupan
sesi ini: **RESKIN saja** — tampilan & pola interaksi dibuat sefamiliar Google
Calendar, TAPI data, fitur, dan alur tetap sama persis dengan yang ada
sekarang. Jangan tambah/kurangi fitur.

## Tujuan & filosofi

Karyawan/Finance sudah terbiasa dengan **Google Calendar**. Kalau halaman ini
"terasa" seperti Calendar, mereka langsung bisa pakai tanpa belajar (Jakob's
Law). Jadi: ambil **bahasa visual & pola navigasi Google Calendar** — kalender
bulan yang lapang, hari-hari sebagai sel bersih, status sebagai "chip acara"
berwarna, navigasi bulan `‹ Hari Ini ›`, kolom hari-ini disorot, header hari
(Sn–Mg) — lalu terapkan ke data kehadiran yang sudah ada.

**Nuansa penting (jangan salin mentah):** Google Calendar itu *event-centric*
(1 orang, banyak acara). Kehadiran itu *matriks orang × hari* (banyak orang,
1 status/hari). Jadi yang diambil adalah **rasa & pola Calendar**, bukan
struktur event-nya. Layar utama tetap "semua karyawan sebulan" — cuma
dikemas sebersih dan selapang Calendar.

## Konteks produk (baca dulu)

- Klinik gigi 5 cabang, ~29 karyawan. Dilihat **Finance/admin di desktop**.
- Tiap karyawan per hari punya SATU status: **Hadir · Telat · Alpa · Cuti ·
  Izin · Libur · WFH**. Ada indikator kalau hari itu punya **foto selfie absen**.
- Karyawan dikelompokkan **per cabang**; ada satu grup khusus **"Kantor (ABG)"**
  (pekerja fleksibel) yang **jam-nya bebas — scan tak dinilai** (tampilkan
  catatan itu, jangan tandai telat/alpa untuk mereka).
- Bahasa Indonesia awam. **Tanpa emoji.** Ikon = SVG garis (stroke currentColor).

## Layar: Kehadiran

Struktur (desktop-first, boleh responsive):

1. **Header halaman**: judul "Kehadiran", subjudul tanggal hari ini. Kanan:
   navigasi bulan ala Calendar — tombol `‹`, label bulan besar ("Juli 2026"),
   tombol `›`, dan tombol **"Hari Ini"** (lompat ke bulan berjalan). Plus filter
   **Cabang** (dropdown) dan **Ekspor** (kalau memang sudah ada; kalau tidak,
   jangan buat baru).

2. **Baris ringkas** (opsional, kalau muat): beberapa angka bulan itu — total
   hadir, telat, alpa — sebagai kartu kecil bertema Calendar (bersih, banyak
   ruang putih).

3. **Grid utama — inti layar.** Matriks **karyawan (baris) × tanggal (kolom)**,
   dikemas seperti kanvas Google Calendar:
   - **Baris header tanggal**: tiap kolom = 1 tanggal, tampilkan singkatan hari
     (Sn/Sl/Rb/Km/Jm/Sb/Mg) di atas angka tanggal — persis header hari Calendar.
     **Kolom hari ini disorot** (aksen lembut, seperti Calendar menyorot "today").
     Kolom **Minggu/tanggal merah** diberi warna beda tipis.
   - **Kolom kiri (sticky)**: nama karyawan + avatar inisial berwarna + role/cabang
     kecil. Tetap terlihat saat scroll horizontal.
   - **Sel status**: tiap sel = status 1 orang 1 hari, ditampilkan sebagai
     **chip berwarna ala "acara" Google Calendar** (bukan sekadar blok penuh) —
     latar lembut + teks/titik warna status. Titik kecil di pojok sel kalau ada
     **foto selfie**. Sel kosong (belum ada data / di luar kontrak) dibiarkan
     bersih. Hover: efek angkat halus ala Calendar.
   - **Pemisah grup cabang**: baris pemisah tipis dengan label "Cabang X ·
     N orang"; untuk grup **Kantor (ABG)** tambahkan catatan "jam bebas — scan
     tak dinilai".

4. **Klik sel → kartu detail (ala popover acara Google Calendar).** Muncul
   kartu mengambang berisi (semua data ini SUDAH ada, cuma dikemas ulang):
   - Nama + tanggal + **badge status** besar berwarna.
   - **Jam masuk** & **jam pulang** (mono/tabular).
   - **Telat** (menit) bila ada; **lembur disetujui** (jam) bila ada.
   - **Lintas cabang** bila orang itu hari itu absen di >1 cabang.
   - **Foto selfie** (thumbnail masuk & pulang) bila ada — kalau tidak, tulis
     "tidak ada foto".
   Tutup dengan klik luar / Esc, persis popover Calendar.

## Warna & tema (WAJIB — zona ABSENSI = COKLAT, BUKAN grafit)

Halaman ini ada di menu **Absensi**, jadi ikut **tema coklat** (bukan grafit
Pusat Akun). Pakai:
- Latar cream `#F4ECE2`, teks ink `#3B1500`, kartu putih border `#EFE0CE`
  radius 18, kontrol/input border `#E7D3BE`.
- **Header/permukaan gelap** (mis. baris header tanggal kalau dibuat gelap):
  coklat `linear-gradient(120deg, #5A2410, #3A1100)` teks `#FBEADB`.
- **Tombol primer** (Hari Ini/Ekspor): coklat `linear-gradient(135deg,#6B2400,#461500)`.
- **Warna status** (konsisten seluruh sistem — jangan bikin hex baru):
  - Hadir → hijau teks `#2F8E55` / bg `#E3F4E8`
  - Telat → amber teks `#B5701A` / bg `#FBEFD9`
  - Alpa → merah teks `#C0392B` / bg `#FBE3DE`
  - Cuti → biru teks `#3F6BAF` / bg `#E4ECFB`
  - Izin → kuning-tua teks `#9A6614` / bg `#F4E7D6`
  - Libur → cokelat-abu teks `#8A6A52` / bg `#F1E9DF`
  - WFH → teal teks `#2E7D74` / bg `#DFEDE9`
  - Indikator foto → biru `#3F6BAF`
- **Sorot "hari ini"**: aksen coklat lembut (mis. bg `#FBF0DE` + garis atas
  `#B5701A`), meniru cara Calendar menyorot hari ini.
- Avatar palette: `#8A3A12 #A8481B #2F8E55 #3C5FA8 #B5701A #7A3415 #5A6B2E #8E2419`.
- Font: **Plus Jakarta Sans**. Angka pakai tabular (`font-variant-numeric: tabular-nums`).

## Rasa "Google Calendar" yang diminta (checklist)

- Banyak ruang putih, garis tipis, tipografi bersih — bukan tabel padat.
- Navigasi bulan `‹ [Bulan] › · Hari Ini` di kanan atas.
- Header hari (Sn–Mg) + angka tanggal di atas tiap kolom.
- Kolom hari-ini disorot; weekend/tanggal merah dibedakan.
- Status sebagai "chip acara" berwarna lembut, bukan blok solid penuh.
- Klik → popover kartu detail mengambang (bukan pindah halaman).
- Transisi halus, hover angkat lembut. Hormati `prefers-reduced-motion`.

## Yang TIDAK usah / TIDAK boleh (jaga scope reskin)

- **Jangan ubah data/fitur**: status, jam, telat, lembur, lintas cabang, foto,
  grup cabang, grup ABG, filter cabang, navigasi bulan — semua tetap.
- **Jangan tambah** drag-drop, quick-add acara, undangan, reminder — itu fitur
  Calendar yang tak relevan untuk absensi (mungkin fase lain, bukan sekarang).
- **Jangan** jadikan grafit — ini zona coklat Absensi.
- **Jangan** hilangkan tampilan "semua karyawan sekaligus" — Finance butuh
  memindai satu bulan penuh untuk semua orang; view per-orang bisa jadi fase 2,
  bukan sekarang.
- Tanpa emoji; ikon SVG garis saja.

## Referensi
- **Google Calendar (month view)** untuk: navigasi bulan, header hari, sorot
  hari-ini, popover detail acara, kebersihan/ruang putih.
- Layar existing (untuk data & fitur yang harus dipertahankan): halaman
  Kehadiran sekarang di menu Absensi.
