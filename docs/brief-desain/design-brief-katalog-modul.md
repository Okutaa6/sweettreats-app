# Design Brief — Katalog Modul (halaman saudara IT Request Tracker)

> Untuk claude.ai/design. **Halaman baru**, tapi di dalam dunia yang sudah ada.
> Lampirkan paket ronde 3: `IT Request Tracker.dc.html`, `Sidebar.dc.html`,
> `it-store.js`, `support.js` + 22 screenshot. Zona grafit, bahasa visual, dan
> sistem lima-saluran-warna **diteruskan apa adanya**.
> Isi nyata: `docs/rencana/katalog-modul-2026-09.md` (33 modul, sudah dikoreksi Ekky).
> Ikon **FontAwesome 5** — nama khas FA6 jadi kotak kosong.

## 0. Kenapa halaman ini ada

IT Request Tracker menjawab **"apa yang sedang bergerak"**. Executive ternyata
juga bertanya hal lain — **"apa saja yang sudah kami punya"** — dan sampai hari
ini jawabannya cuma ada di kepala Head IT.

Dua pertanyaan berbeda, dua halaman. Papan untuk pekerjaan yang mengalir;
katalog untuk barang yang dimiliki.

⛔ **Ini BUKAN tab keempat di papan.** Tiga tingkat tab di papan sekarang punya
disiplin yang tegas (jenis = segmen besar · tahap = teks bergaris bawah ·
parkir = chip putus), dan ketiganya adalah *jenis tiket*. Katalog bukan tiket.
Ia **halaman saudara** — `/it-tracker/katalog` — dicapai dari kepala halaman
papan. Sidebar tetap **satu** entri IT Tracker.

## 1. Siapa yang memakai

**Executive (Robi) — pemakai utama.** Datang sesekali, tidak hafal istilah kita,
dan ingin jawaban dalam lima detik: berapa yang kami punya, siapa pemiliknya,
mana yang sedang berubah. Ia juga punya satu pertanyaan khas: *"perlihatkan
semua laporan & dashboard"* — lintas departemen sekaligus.

**Head IT (Ekky).** Memakainya sebagai daftar induk saat triase: tiket ini
menyentuh modul yang mana? Dan sesekali menambah entri modul baru.

**Kepala departemen.** Melihat bagian miliknya: apa yang kami punya, dan apa
yang sedang dikerjakan untuk kami.

## 2. Model datanya — dua tingkat, dua sumbu, tiga status

**Dua tingkat.** **Modul** = yang disebut departemen kalau ditanya *"sistem apa
yang kalian punya?"* (33 entri). **Fitur** = halaman yang bisa dibuka (±60,
diturunkan otomatis dari menu — tak pernah basi).

**Dua sumbu, dan ini kuncinya:**
- **Pemilik** — satu departemen yang memutuskan & meneken UAT. Tak pernah dua.
- **Jenis** — `Operasional` · `Laporan & Dashboard` · `Integrasi` · `Platform`.

Sumbu kedua yang menjawab pertanyaan Executive **tanpa** membuat satu modul
bertuan dua. *Laporan Penjualan* tetap milik **Sales**, tapi berjenis
**Laporan & Dashboard** — jadi ia muncul saat Executive menyaring jenis.

**Tiga status:** `Hidup` (dengan tanggal) · `Sedang dibangun` · `Direncanakan`.

⛔ **Status BUKAN progres.** Modul hidup bukan 80% — ia hidup, dan tetap punya
tiket terbuka. **Jangan ada bar persen di halaman ini.**

## 3. Tiga angka per departemen & per modul — semuanya terhitung

> **Finance** — 4 modul hidup · 6 tiket berjalan · 3 utang terbuka

- **hidup** dari katalog · **berjalan** & **utang** dari tabel tiket.
- Tak satu pun dirawat manual, jadi tak satu pun bisa basi.
- **Utang bukan konsep baru** — utang sebuah modul = tiket terbuka yang
  menempel padanya. Tiga utang Petty Cash (SQL pensiun kategori · Setoran Awal
  tanpa jejak audit · cut-over 2 wallet) **memang sudah tiket**.

Angka *berjalan* dan *utang* harus bisa **diklik** → membuka papan yang sudah
tersaring ke modul itu. Katalog dan papan adalah dua tampilan atas satu data.

## 4. Tantangan desain yang sebenarnya

**a) Dua lensa, satu halaman.** Bawaan: **per departemen**. Saklar: **per
jenis**. Perpindahan lensa harus terasa seperti memutar sudut pandang, bukan
membuka halaman lain. Ini interaksi terpenting di layar ini.

**b) Sebaran timpang, dan itu permanen.** Sales 6 · IT 6 · Finance 5 ·
Operasional/HR 5 · Marketing 4 · Executive 3 · Data Pasien 2 · **Stok/Inventory
0 hidup**. Departemen kosong harus terbaca **"direncanakan"**, bukan **"rusak"**
— dan ia memang harus tetap tampil, karena kenyataan bahwa departemen itu ada
di rencana sebelum ada di perangkat lunak adalah informasi yang berguna.

**c) Baris modul membawa enam hal**: nama · jenis · status · sejak · jumlah
fitur · jumlah tiket terbuka. Terapkan disiplin yang sama seperti kartu papan
ronde 3 — apa yang naik ke baris, apa yang cukup saat dibuka.

**d) ±60 fitur tak boleh membanjiri.** Terlipat di bawah modulnya. Yang terlihat
sekilas cukup jumlahnya; namanya muncul saat dibuka.

**e) Jangan bersaing dengan panas.** Papan memakai **isi strip** untuk panas,
**garis kiri teal** untuk unit, **garis putus merah** untuk pelewatan paraf,
**teks kuning** untuk basi. Katalog hidup di zona yang sama — kalau ia memakai
saluran yang sudah punya arti, arti itu rusak di dua halaman sekaligus. Cari
saluran yang belum terpakai untuk membedakan status.

## 5. Yang WAJIB ada — bentuknya bebas

1. **Jawaban lima detik di atas**: total modul hidup · sedang dibangun ·
   direncanakan, dan berapa tiket berjalan seluruhnya.
2. **Pengelompokan per departemen** dengan tiga angka di kepalanya.
3. **Saklar lensa ke per jenis** — Executive menyaring `Laporan & Dashboard`
   dan melihat keempatnya dari empat departemen berbeda.
4. **Status terbaca tanpa membaca teks** — tapi bukan lewat saluran warna yang
   sudah dipakai papan (lihat 4e).
5. **Tanggal "sejak"** untuk yang hidup. Ini yang menjawab *"sudah berapa lama
   kami punya ini"*.
6. **Angka tiket berjalan & utang yang bisa diklik** → papan tersaring.
7. **Fitur terlipat** di bawah modulnya.
8. **Keadaan kosong yang disengaja** untuk Stok/Inventory (0 hidup, 2
   direncanakan) — harus terlihat seperti rencana, bukan kegagalan.
9. **Tautan dua arah**: dari kepala papan ke katalog, dan dari tiap modul di
   katalog kembali ke papan tersaring.
10. **Penanda "warisan"** untuk Pusat Akun — kerangkanya bertanggal **27 April
    2022**, empat tahun sebelum kerjaan Sweet Treats. Kecil saja, tapi jujur.

## 6. Angka nyata untuk mengukur rancangan

- **33 modul · 25 hidup · 2 sedang dibangun · 6 direncanakan · 8 departemen.**
- **±60 fitur** tersebar tak rata: Absensi 8 · Konten 6 · Pusat Akun 6 ·
  Reservation Data Center 6 · Referral & Member 5 · sisanya 1–4.
- **26 tiket aktif** di tracker yang akan menempel ke modul-modul ini.
- Modul dengan utang terbanyak hari ini: **Petty Cash (3)**.
- Executive membuka halaman ini mungkin **sebulan sekali** — ia tidak akan
  belajar navigasinya. Semua harus jelas pada kunjungan pertama.

## 7. Yang sengaja TIDAK diminta

- Tak ada bar persen, tak ada skor kesehatan, tak ada "kematangan modul".
- Tak ada garis waktu/gantt — tanggal cukup sebagai teks.
- Tak ada penyuntingan katalog dari halaman ini kecuali satu: Head IT menambah
  modul baru. Selebihnya diturunkan.
- Tak ada grafik. Kalau sebuah angka butuh grafik untuk dipahami, angkanya yang
  salah pilih.

## 8. Satu kalimat

> Daftar barang yang kami punya — siapa pemiliknya, sejak kapan, dan apa yang
> sedang terjadi padanya.
