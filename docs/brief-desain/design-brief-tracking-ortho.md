# Design Brief — Tracking Pasien Ortho

**Revisi 2 · 16 September 2026.** Revisi 1 sudah dikerjakan jadi handoff desain; berkas
ini menggantikannya.

⚠️ **SATU layar per sesi claude.ai/design.** File ini berisi **4 sesi**.
Tiap sesi, tempel: blok **Konteks produk** + **Angka nyata** + **Kamus istilah** +
blok **Gaya visual** + **satu** blok layar. Jangan gabung layar.

Audiens: **Sales / Front Office** di 5 cabang. Halaman kerja harian, banyak aksi tulis —
bukan halaman laporan.

Asal rancangan: coretan papan tulis Ekky + brief konsultan IT-2026-013.

---

## Apa yang berubah di Revisi 2 (baca dulu kalau sudah pernah mengerjakan Revisi 1)

Revisi 1 diuji ulang terhadap brief konsultan yang mendasarinya. Dari tujuh hal yang
"harus benar" di brief itu, **2 tercapai, 4 sebagian, 1 gagal**. Yang gagal adalah yang
pertama: *"Sales berhenti mengisi apa pun yang datanya sudah ada di rekam medis."*
Revisi 2 menutup itu.

| # | Perubahan | Kena di |
|---|---|---|
| 1 | **Pasien tidak lagi diketik satu per satu.** Sistem mengisi sendiri 1.639 pasien dari rekam medis, sekali jalan | Konteks · Sesi 1 · Sesi 3 |
| 2 | **Keadaan "hari pertama" jadi kasus desain wajib** — semua pasien belum punya jadwal, kartu "Terlewat" berbunyi nol padahal ratusan terlantar | Konteks · Sesi 1 |
| 3 | **Kartu ke-3 diperkaya** jadi antrean bertingkat kegentingan; "jarang kontrol" dilebur ke sini, **bukan** kartu atau tab baru | Sesi 1 |
| 4 | **Nomor rekam medis ikut terbawa** dari pasien terpilih. Belum tertaut → peringatan. **Jangan pernah mengarang nomor** | Sesi 3 |
| 5 | **Reservasi yang dibatalkan harus langsung ketahuan** — badge merah di tab List | Kamus · Sesi 1 |

Yang **tidak** berubah: empat tab, tabel datar jadwal kontrol, transfer lewat dropdown
CABANG, kalender jadwal, drawer detail, semua token warna dan tipografi.

---

## Konteks produk (tempel di TIAP sesi)

Klinik gigi 5 cabang (Bintaro, Sunter, Kalideres, Pamulang, Cinere). Halaman ini
menggantikan Google Sheets yang dipakai Sales untuk melacak pasien **behel (ortho)** —
satu berkas per cabang, dengan 11–20 tab yang angkanya saling berbeda dan tidak ada satu
pun yang jadi sumber kebenaran.

Perawatan behel berlangsung **±2 tahun** dan menuntut pasien kontrol rutin, umumnya
sebulan sekali. Pekerjaan Sales bukan mencatat kunjungan — itu sudah tercatat di sistem
rekam medis. Pekerjaannya adalah **menagih pasien supaya datang**: mengingatkan lewat
WhatsApp, menawarkan jadwal, membuatkan reservasi.

**Pasien tidak diketik satu per satu.** Sistem menyisir rekam medis sekali jalan dan
mengisi sendiri daftar pasien behel beserta riwayat kontrol dan cabang terakhirnya —
1.639 pasien sudah ada di halaman sejak hari pertama. Yang dikerjakan Sales adalah
**menentukan jadwal kontrol berikutnya**, bukan mengetik ulang data yang sudah ada.

Tiga pain point yang HARUS terasa terjawab di desain:

1. **Berhenti menghitung.** Sales harus langsung melihat siapa yang perlu ditagih hari
   ini, tanpa menyisir ratusan baris. Keluhan aslinya: *"harus liat daftar pasien
   berhari hari lalu, jadinya scroll panjang banget dan lihat isinya apa aja."*
2. **Pasien pindah cabang tidak boleh hilang.** Kalau pasien minta kontrol di cabang
   lain — atau dokternya tidak lagi praktek di cabang itu — perpindahan dicatat sebagai
   **transfer**: cabang tujuan langsung melihat pasiennya, cabang asal tahu ke mana
   perginya. Bukan lagi *"selama ini manual, di cek satu satu lewat WA atau koordinasi
   sama sales lain, jadi cuma seingetnya aja sih."*
3. **Berhenti pindah aplikasi.** Follow-up WA, buat reservasi, dan catat kunjungan
   dilakukan dari baris yang sama. *"carinya juga susah harus ke omni."*

Cara kerja intinya: **tiap pasien punya rentetan jadwal kontrol yang tumbuh satu per
satu.** Begitu satu kontrol ditutup (pasien datang atau tidak datang), Sales menentukan
jadwal berikutnya. Tidak ada 24 baris kosong yang dibuat di muka.

### Hari pertama — keadaan yang wajib ikut didesain

Pada hari pertama, **semua pasien belum punya jadwal**. Artinya kartu "Terlewat" berbunyi
**nol** dan halamannya terlihat tenang — padahal ratusan pasien sedang terlantar.
"Terlewat" hanya bisa ada kalau seseorang sudah membuat jadwalnya duluan.

Antrean sesungguhnya di hari pertama adalah kartu **"Belum punya jadwal"**, dan isinya
harus terurut dari yang paling parah. Kalau 1.639 baris muncul tanpa urutan kegentingan,
kita mengulang keluhan aslinya persis: *"scroll panjang banget."*

Halaman ini **hanya untuk pasien behel**. Pasien umum tetap di halaman Reservasi.

Status pasien **bukan berbasis waktu** — pasien tetap Aktif sampai bracket dilepas atau
pindah klinik. Lama tak kontrol memicu **peringatan**, bukan mengubah status. Jangan
desain status gaya sheet lama seperti "Tdk Aktif 6bln" atau "1 tahun tidak kontrol".

Bahasa UI: Indonesia awam. **Tanpa emoji** — ikon SVG garis, stroke `currentColor`,
`fill:none`, tebal ±2.2, ukuran 14–16px.

## Angka nyata untuk mengisi desain (tempel di TIAP sesi — bukan lorem)

Semua angka terukur dari data produksi 9 September 2026, kecuali satu yang ditandai.

- Pasien ortho aktif 2026 per cabang: **Bintaro 542 · Sunter 467 · Kalideres 285 ·
  Cinere 222 · Pamulang 167**. Total pasien unik **1.639**.
- Pasien **jarang kontrol** (< 2 kunjungan dalam 6 bulan): **581 se-lima-cabang (35%)** —
  Sunter 193 · Bintaro 184 · Kalideres 96 · Cinere 60 · Pamulang 52.
- Pasien yang **kontrol terakhirnya lebih dari 6 bulan lalu**: pakai **214** sebagai
  angka contoh. ⚠️ Ini satu-satunya angka ilustratif di berkas ini; sisanya terukur.
- Pasien yang kini kontrol di cabang lain: **44** se-lima-cabang; yang pernah lintas
  cabang **104**.
- Jumlah kunjungan 6 bulan terakhir per pasien wajar **0–8**.
- Jumlah kontrol seumur perawatan wajar **1–50**, rata-rata 8–14.
- Jarak antar kontrol wajar **28–45 hari**; target **30 hari**.
- Nama pasien: Septiana · Yunita Fauziah · Kamilah Jasmine · Susan Wu · Febriana Muflika
  Sari · Lestia Juni Yanti · Aisyah Nurwulandari · Muhammad Nazhif Faqih · Dinda Ayu
  Kusumadanti · Rima Inderawati · Raffi Ramadhany · Eka Rulianti.
- Nomor rekam medis bergaya **`B-2011-3014`** · `S-2101-1008` · `C-2101-0137` ·
  `D-2509-1035` (huruf depan = cabang tempat pasien pertama mendaftar).
- Dokter ortho: drg. Nugroho Ahmad R Sp.Ort · drg. Komalawaty Sp.Ort · drg. Vega ·
  drg. Andi Sp.Ort · drg. Cut Nyak Shafira · drg. Fridhi · drg. Ryan · drg. Rahmah.
- Jenis bracket: **Metal** (mayoritas mutlak, ±96% pasien) · Ceramic · Sapphire ·
  Self Ligating · Damon Quantum.
- Singkatan cabang untuk kolom sempit: **BIN · SUN · KAL · CIN · PAM**.
- Nomor HP bergaya `0896-5656-9938` / `081219540996`.

## Kamus istilah (tempel di TIAP sesi — konsisten di semua layar)

Di papan tulis kolomnya ditulis singkat. **Di UI pakai kata panjangnya**, jangan
singkatan papan tulis.

| Papan tulis | Tulis di UI | Artinya |
|---|---|---|
| `SCH` | **Jadwal** | Tanggal pasien seharusnya datang kontrol |
| `FU` | **Follow-up** | Tanggal Sales menghubungi pasien untuk mengingatkan |
| `RSV` | **Reservasi** | Reservasi nyata di modul Reservasi. Kosong = pasien belum booking |
| `VISIT` | **Datang** | Tanggal pasien benar-benar datang |
| `CAB` | **Cabang Kontrol** | Cabang tempat kontrol ini dikerjakan. Mengubahnya = **mentransfer** pasien ke cabang itu |
| `X` | `X` | Tanda "belum/tidak terjadi". **Pertahankan apa adanya** — sel kosong terasa seperti data hilang, `X` terasa seperti fakta |

Istilah lain:

| Istilah di UI | Artinya |
|---|---|
| **Jadwal kontrol** | Satu baris = satu siklus kontrol. Bernomor urut per pasien (#1, #2, …). ⚠ Tanggalnya JATUH TEMPO, bukan janji — kata "janji" dipakai khusus untuk Reservasi |
| **Terlewat** | Tanggal jadwal sudah lewat, pasien belum datang dan belum ditandai tidak hadir |
| **Jarang kontrol** | Kurang dari 2 kunjungan dalam 6 bulan. **Definisi Sales sendiri**, bukan karangan sistem |
| **Hilang** | Kontrol terakhir lebih dari 6 bulan lalu |
| **Belum punya jadwal** | Kontrol terakhir sudah ditutup, atau belum pernah dijadwalkan sama sekali |
| **Transfer** | Pasien berpindah cabang kontrol. Dicatat sebagai tindakan, bukan ditebak belakangan |
| **Cabang pasang** | Cabang tempat behel dipasang pertama kali. **Tidak pernah berubah** |
| **Kontrol ke-n** | Urutan kontrol pasien itu, bukan jumlah total |

Chip status baris jadwal — sembilan keadaan, jangan ditambah:

| Teks chip | Kapan muncul | Warna teks / latar |
|---|---|---|
| "Menunggu" | Jadwal dibuat, belum disentuh, belum lewat | `#A9866A` / `#F1E9DF` |
| "Sudah di-FU" | Follow-up terisi, reservasi belum | `#3F6BAF` / `#E4ECFB` |
| "Ada reservasi" | Reservasi terisi, pasien belum datang | `#3F6BAF` / `#E4ECFB` |
| "Hadir" | Tanggal datang terisi | `#2F8E55` / `#E3F4E8` |
| "Tidak hadir" | Ditandai tidak datang | `#B5701A` / `#FBEFD9` |
| "Terlewat 6 hari" | Lewat 1–14 hari | `#B5701A` / `#FBEFD9` |
| "Terlewat 32 hari" | Lewat lebih dari 14 hari | `#C0392B` / `#FBE3DE` |
| **"Reservasi batal"** | Reservasi yang tertaut dibatalkan dari halaman Reservasi | `#C0392B` / `#FBE3DE` |
| "Transfer → SUN" | Cabang kontrol diubah | `#6B2400` / `#F6EBE1` |

## Gaya visual — zona COKELAT / operasional (tempel di TIAP sesi)

Ini halaman **operasional**, bukan Finance. Pakai zona cokelat default — **jangan**
burgundy, **jangan** teal.

- Latar halaman cream `#F4ECE2`. Teks ink `#3B1500`, sekunder `#5A3A22` dan `#7A5B43`,
  muted `#A9866A`. Kartu putih. Border kartu `#EFE0CE`, border kontrol `#E7D3BE`,
  hover baris `#FBF4EC`, footer wash `#FCF7F0`.
- Font **Plus Jakarta Sans** (400–800). Angka `font-variant-numeric: tabular-nums` —
  **wajib**, semua kolom tanggal harus sejajar digitnya saat ditumpuk.
- Radius: kartu-tabel **18** · kartu KPI **16** · kontrol/tombol **10–11** · badge
  **7–8** · chip **20**.
- Shadow kartu `0 1px 2px rgba(70,21,0,.04)`, kartu tabel tambah
  `0 10px 26px rgba(70,21,0,.05)`. Tombol primer `0 4px 12px rgba(20,20,18,.22)`.
- **Header tabel**: `linear-gradient(120deg,#5A2410,#3A1100)`, teks `#FBEADB`.
  ⚠️ Gradient dipasang di **baris header (`thead tr`)**, tiap sel header transparan —
  kalau dipasang per sel, gradient me-restart dan tabelnya jadi **belang**. Sudah pernah
  kejadian di dua halaman lain.
- Tombol primer `linear-gradient(135deg,#6B2400,#461500)`.
- **Disiplin warna**: cokelat **hanya** untuk chrome — header tabel, tab aktif, tombol
  primer, ikon kartu ringkas. Haram mewarnai permukaan besar. Merah terang `#C0392B`
  tetap **khusus** makna negatif, jangan dipakai sebagai aksen.
- **Desktop-first 1280px+** untuk tabel, **tapi kartu harus tetap terbaca di HP** —
  Sales sering bekerja sambil berdiri di front office.
- Kepadatan: baris tabel rapat, jangan boros whitespace. Kartu ringkas kecil dan di
  atas — bukan hero besar yang mendorong tabel ke bawah lipatan.
- **Tanpa emoji.** Ikon SVG garis.

---

## SESI 1 — Layar: Tab LIST, tabel jadwal kontrol (desktop, zona COKELAT)

Layar yang dibuka Sales tiap pagi. **Ini layar terpenting.** Kalau hanya satu layar yang
sempat didesain bagus, pilih yang ini.

### Susunan dari atas ke bawah

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  Tracking Pasien Ortho                                                       │
│  Bintaro · 542 pasien ortho aktif · Senin, 18 Jan 2027                        │
├──────────────────────────────────────────────────────────────────────────────┤
│  List ② │ Kalender │ Transfer Cabang ③ │ Pasien Ortho    [⚙ Filter] [+ Tambah]│
│  ▔▔▔▔▔                                                                       │
│    ↑ badge MERAH = reservasi baru dibatalkan dari halaman Reservasi           │
├──────────────────────────────────────────────────────────────────────────────┤
│  Filter aktif:  (🔒 Cabang: Bintaro)  ( Dokter: drg. Vega × )                 │
├──────────────────────────────────────────────────────────────────────────────┤
│ ┌───────────┐ ┌───────────┐ ┌────────────────┐ ┌───────────┐                 │
│ │ Jadwal    │ │ Terlewat  │ │ Belum punya    │ │ Transfer  │                 │
│ │ minggu ini│ │           │ │ jadwal       › │ │ bulan ini │                 │
│ │    18   › │ │    31   › │ │                │ │    4    › │                 │
│ │ 7 sudah   │ │ 12% dari  │ │    1.639       │ │ 2 masuk · │                 │
│ │ reservasi·│ │ aktif ·   │ │ 581 jarang     │ │ 2 keluar  │                 │
│ │ 11 belum  │ │ 9 lewat   │ │ kontrol ·      │ │           │                 │
│ │           │ │ >14 hari  │ │ 214 hilang     │ │           │                 │
│ │           │ │           │ │ >6 bulan       │ │           │                 │
│ └───────────┘ └───────────┘ └────────────────┘ └───────────┘                 │
├──────────────────────────────────────────────────────────────────────────────┤
│  PASIEN      DOKTER    JADWAL   FOLLOW-UP  RESERVASI  DATANG  CABANG STATUS  ›│
│ ▌Septiana    drg.Vega  12 Jan   X          [Buat…]    X       [BIN▾] Terlewat›│
│  B-2011-3014           6 hari                                        6 hari   │
│  Metal · ke-4          lalu                                                   │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Rinciannya

1. **Header halaman**: judul "Tracking Pasien Ortho", subjudul menyebut cabang aktif +
   jumlah pasien ortho aktif + tanggal hari ini.

2. **Baris tab**: deretan tab teks berikon dengan badge angka, tab aktif ditandai
   **garis bawah tebal cokelat**. Bukan pill besar, bukan kotak.
   `List` · `Kalender` · `Transfer Cabang` ⟨badge⟩ · `Pasien Ortho`.

   **Baru di Revisi 2:** tab `List` membawa **badge angka MERAH** kalau ada reservasi
   kontrol yang baru dibatalkan dari halaman Reservasi. Badge hilang setelah barisnya
   ditindak. Bedakan tegas dari badge abu di tab Transfer Cabang yang hanya menghitung
   isi — yang merah menuntut tindakan.

3. **Tombol di kanan baris tab**: ikon corong `Filter` (membuka/menutup panel filter) dan
   tombol primer `+ Tambah Pasien Ortho`.

4. **Panel filter yang bisa dilipat** — tertutup secara default, terbuka sendiri kalau
   ada filter aktif. Isi satu baris rapat: Pencarian (nama atau no. RM) · Cabang ·
   Dokter · Status · Tanggal Mulai · Tanggal Akhir · tombol `Terapkan` dan ikon reset.
   Untuk Sales cabang, dropdown Cabang **tidak bisa diganti** — tampil sebagai **chip
   bergembok** bertuliskan nama cabangnya.

5. **Deret chip filter aktif** di bawah panel, tiap chip ada tanda × untuk melepas satu
   per satu. Diawali label kecil "Filter aktif:". Chip cabang bergembok tidak bisa
   dilepas.

6. **Empat kartu ringkas** sebaris. Tiap kartu adalah **tombol filter** — ada chevron `›`
   sebagai afordansi. Angka besar + baris kecil pembanding di bawahnya, jangan angka
   telanjang:

   | Kartu | Angka besar | Baris kecil |
   |---|---|---|
   | Jadwal minggu ini | `18` | `7 sudah reservasi · 11 belum` |
   | **Terlewat** | `31` | `12% dari aktif · 9 lewat >14 hari` |
   | **Belum punya jadwal** | `1.639` | `581 jarang kontrol · 214 hilang >6 bulan` |
   | Transfer bulan ini | `4` | `2 masuk · 2 keluar` |

   Kartu **Terlewat** memakai ikon dan angka warna warning `#B5701A`.

   **Kartu ke-3 adalah kartu terpenting di hari-hari awal** — di situlah 1.639 pasien
   hasil penyemaian menunggu dijadwalkan. Angkanya akan menyusut tiap hari; itu memang
   ukuran kemajuannya.

7. **Group header saat kartu ke-3 dipilih.** Pakai pola group header yang sama dengan
   pengelompokan tab List (`Terlewat — tagih hari ini` / `Minggu ini` / `Mendatang`),
   jadi tidak ada komponen baru:

   | Kelompok | Isi | Titik |
   |---|---|---|
   | **Hilang lebih dari 6 bulan** | kontrol terakhir >180 hari lalu | `#C0392B` |
   | **Jarang kontrol — kurang dari 2× per 6 bulan** | definisi Sales persis | `#B5701A` |
   | **Baru selesai kontrol — tinggal dijadwalkan** | kontrol ditutup <30 hari lalu | `#2F8E55` |

   Di baris, tambah **lencana** — bukan kolom baru: `2 kontrol dalam 6 bulan`. Itu
   memberi Sales alasan menelepon, bukan sekadar perintah.

   Tindakan tunggalnya selalu `Jadwalkan kontrol`. Begitu dijadwalkan, baris keluar dari
   kelompok ini dan masuk alur normal — antreannya mengecil sendiri, dan yang paling
   parah selalu di atas.

8. **Tabel jadwal kontrol** — satu baris = satu jadwal kontrol, semua pasien bercampur,
   diurut tanggal jadwal menaik, yang terlewat di paling atas.

   | Kolom | Isi |
   |---|---|
   | **Pasien** | Nama tebal. Baris kecil di bawahnya: no. RM · jenis bracket · "kontrol ke-4" |
   | **Dokter** | Nama dokter ortho |
   | **Jadwal** | Tanggal ("12 Jan 27"). Kalau terlewat: tanggal merah + baris kecil "6 hari lalu" |
   | **Follow-up** | Tanggal + ikon kanal kecil (WA / telepon). Belum ada → **`X` muted** |
   | **Reservasi** | Tanggal + chip status kecil. Belum ada → tombol mungil `Buat Reservasi`. Waitlist tanpa tanggal → tulis **"Waitlist"**, jangan `X` |
   | **Datang** | Tanggal. Belum ada → **`X` muted** |
   | **Cabang Kontrol** | Singkatan 3 huruf di dalam **dropdown kecil**. Kalau berbeda dari cabang pasang: latar aksen `#F6EBE1` + ikon panah transfer |
   | **Status** | Chip sesuai kamus istilah |
   | — | Chevron `›` — baris bisa diklik menuju detail pasien |

   Baris yang terlewat lebih dari 14 hari diberi **garis tebal merah di tepi kiri baris**
   — bukan seluruh baris diwarnai merah, itu bikin tabel berisik dan tidak terbaca.

9. **Baris dengan reservasi dibatalkan** (baru di Revisi 2): garis tepi kiri merah, chip
   "Reservasi batal", kolom Reservasi menampilkan tanggal **dicoret** + teks muted
   "dibatalkan 12 Jan", dan tombol `Buat Reservasi` **muncul kembali** — supaya
   menjadwalkan ulang cukup satu klik dari baris yang sama.

10. **Aksi cepat di baris**, muncul saat baris disorot, di kolom paling kanan sebelum
    chevron: ikon WhatsApp + satu tombol tindakan yang **berubah mengikuti tangga
    status** (`Catat Follow-up` → `Buat Reservasi` → `Catat Kunjungan` →
    `Jadwalkan berikutnya`).
    Tombol yang tidak berlaku **mati sambil menyebutkan alasannya** lewat tooltip —
    misalnya *"Sudah dicatat 20 Des oleh Rina"*. Jangan sekadar diabu-abukan tanpa
    penjelasan.

11. **Dropdown Cabang Kontrol adalah aksi transfer, dan ini perilaku paling penting di
    layar ini.** Memilih cabang lain **langsung menyimpan** dan memunculkan **toast
    dengan tombol "Urungkan"** — bukan dialog "Anda yakin?". Bunyi toast:

    > Kontrol Septiana dipindahkan ke Sunter. Sunter sekarang bisa melihat pasien ini.
    > **[ Urungkan ]**

    Chip status baris berubah jadi "Transfer → SUN", dan baris itu berpindah ke tab
    Transfer Cabang.

12. **Empty state yang mengajarkan**, bukan "tidak ada data". Dua bunyi berbeda:

    - Tidak cocok filter: *"Tidak ada jadwal kontrol yang cocok dengan filter ini."* +
      tombol `Reset filter`.
    - Benar-benar kosong: *"Semua pasien behel di cabang ini sudah punya jadwal
      berikutnya. Antrean bersih."* — ini kabar baik, jadi jangan bernada peringatan.

### Varian: tab TRANSFER CABANG

Tabel yang sama persis, disaring, dan dipecah **dua kelompok berjudul** karena tindakannya
berbeda:

- **"Masuk ke cabang ini"** — pasien dari cabang lain. Perlu disambut dan dijadwalkan.
  Baris sepenuhnya bisa ditindak.
- **"Keluar dari cabang ini"** — pasien yang kita lepas. Tetap terlihat supaya Sales tahu
  ke mana perginya, tapi ditampilkan lebih redup, dan slot tindakannya berupa label mati
  `Dipegang Sunter`.

Tambah satu kolom **"Dari / Ke"** berisi nama cabang lawan + tanggal transfer
(mis. "← dari Cinere · 20 Des"). Tiap baris juga membawa tombol **Urungkan**.

---

## SESI 2 — Layar: Tab KALENDER (desktop, zona COKELAT)

Grid bulanan berisi **jadwal kontrol**, bukan kunjungan. Gunanya melihat beban minggu
depan dan menyisir yang terlewat.

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  [‹]        Januari 2027        [›]   14 jadwal          [⌖ Hari Ini]        │
├────────┬────────┬────────┬────────┬────────┬────────┬────────────────────────┤
│ Minggu │ Senin  │ Selasa │  Rabu  │ Kamis  │ Jumat  │ Sabtu                  │
├────────┼────────┼────────┼────────┼────────┼────────┼────────────────────────┤
│   4    │   5    │  (6)   │   7    │   8    │   9    │  10                    │
│        │ ▪Rima  │ ▪Sept. │        │ ▪Eka   │ ▪Dinda │                        │
│        │ ▪Yunita│ ▪Raffi │        │        │ ▪Aisyah│                        │
│        │        │ ▪Febri │        │        │ +5 lagi│                        │
├────────┴────────┴────────┴────────┴────────┴────────┴────────────────────────┤
│  ● Menunggu ● Sudah di-FU ● Ada reservasi ● Hadir ● Tidak hadir ● Terlewat   │
└──────────────────────────────────────────────────────────────────────────────┘
```

1. **Toolbar kalender**: tombol `‹` · label bulan besar ("Januari 2027") · tombol `›` ·
   jumlah jadwal bulan itu · tombol primer `Hari Ini` di ujung kanan.
2. **Baris nama hari** Minggu–Sabtu memakai gradient cokelat yang sama dengan header
   tabel.
3. **Grid 6 × 7.** Tiap sel tanggal berisi angka tanggal di pojok, lalu **chip-chip
   kecil** satu per jadwal kontrol. Isi chip = **nama depan pasien saja**, warna mengikuti
   status. Kalau lebih dari 3 jadwal, tampilkan 3 + chip **"+5 lagi"**.
4. **Hari ini** diberi cincin aksen dan lingkaran gelap pada angka tanggalnya.
5. Tanggal di luar bulan berjalan **diredupkan**, tapi tetap ditampilkan.
6. **Tanggal yang punya jadwal terlewat** diberi latar sel merah sangat muda `#FBE3DE` —
   supaya langsung kelihatan saat menggulir bulan ke belakang.
7. **Legenda titik warna** di bawah grid, satu baris.
8. Klik chip → membuka **drawer detail pasien**. Jangan pindah halaman.
9. Panel filter dan empat kartu ringkas **tetap tampil di atas kalender** — filter dan
   kartu yang aktif ikut menyaring isi kalender, dan itu harus terasa.

---

## SESI 3 — Layar: Tab PASIEN ORTHO + drawer Tambah Pasien (desktop, zona COKELAT)

Daftar per orang. Dipakai untuk melihat siapa saja yang sedang dirawat, melihat riwayat
satu pasien utuh, dan menambah pasien yang tidak tertangkap penyemaian.

### Grid kartu pasien

**Kartu, bukan tabel** — ini halaman kelola, dan standar produk ini menetapkan grid kartu
sebagai default untuk halaman kelola. Sediakan toggle **kartu ⇄ tabel** di kanan atas
untuk yang lebih suka baris padat.

```
┌────────────────────────────────────────────┐
│ ▔▔▔ Terlewat 14 hari ▔▔▔                   │  ← strip warning di tepi atas
│ SEPTIANA                          [ Aktif ]│
│ B-2011-3014 · Metal · Keduanya             │
│ Pasang 12 Okt 25 · BIN · drg. Vega         │
│ Dikunjungi:  ( BIN )  ( SUN • )            │  ← titik = bukan cabang pasang
│ Kontrol terakhir 20 Des · Berikutnya 12 Jan│
│ 36 kontrol · hadir 32 (89%)                │
│                                   Detail › │
└────────────────────────────────────────────┘
```

Kartu yang jadwal berikutnya sudah lewat diberi **strip warning di tepi atas**. Kartu
normal tidak punya strip.

Pill cabang: cabang pasang polos, cabang lain diberi **titik kecil** + latar aksen.

### Drawer "Tambah Pasien Ortho"

Drawer ini untuk pasien yang **tidak tertangkap penyemaian** — pasang di klinik lain,
pindahan, atau pasien lama yang datanya tidak lengkap. Bukan jalur utama.

**Side-drawer dari kanan**, bukan modal tengah.

Isi form, urut dari atas:

1. **Pasien** — kotak cari. Ketik nama atau nomor HP, hasil muncul sebagai daftar bergaya
   `Nama · Relasi (nomor)` — relasi disertakan karena satu nomor HP bisa dipakai beberapa
   pasien (orang tua + anak).

   **Setelah pasien dipilih**, kartu terpilih menampilkan nama, nomor HP, dan
   **nomor rekam medisnya** — sebagai **teks mati, bukan field yang bisa diketik**.

   Kalau pasien itu **belum tertaut ke rekam medis**, ganti baris nomor RM dengan kotak
   peringatan amber (`#B5701A` / `#FBEFD9`):

   > ⚠ Pasien ini belum tertaut ke rekam medis. Riwayat kunjungannya tidak akan terbaca.
   > **Tautkan sekarang**

   **Jangan pernah mengarang nomor rekam medis.** Nomor karangan tidak akan pernah cocok
   dengan rekam medis, dan menutup jalan untuk mengotomatiskan kolom Datang kelak.

   Di bawahnya tautan kecil `+ Pasien belum terdaftar` yang membuka drawer "Tambah Pasien
   Baru" yang sudah ada — **drawer ortho tetap utuh di belakangnya**, jangan ditutup.
2. **Tanggal pasang behel** — date input.
3. **Cabang pasang** — default cabang aktif.
4. **Jenis bracket** — dropdown: Metal · Ceramic · Sapphire · Self Ligating ·
   Damon Quantum.
5. **Rahang** — tiga pilihan tersegmen: Atas / Bawah / Keduanya.
6. **Dokter ortho** — dropdown.
7. **Garis pemisah**, lalu kartu bersarang berjudul **"Jadwal kontrol pertama"**:
   date input (default = tanggal pasang + 30 hari, dengan keterangan yang menyebutkan
   perhitungan itu) + catatan opsional.
8. **Tombol simpan yang menjelaskan akibatnya**: `Simpan & buat jadwal #1` — mati sampai
   pasien dan kedua tanggal terisi.
9. Kalimat petunjuk kecil di bawah tombol:
   *"Jadwal berikutnya dibuat nanti, setiap kali pasien selesai kontrol."*

---

## SESI 4 — Layar: Detail satu pasien (drawer 760px, zona COKELAT)

Dibuka dari chevron di tab List, dari kartu di tab Pasien Ortho, dan dari chip di
Kalender. **Inilah tabel yang digambar di papan tulis** — jaga kolomnya persis.

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ TRACKING PASIEN ORTHO › SEPTIANA                                         [✕] │
│                                                                              │
│ SEPTIANA                                    [ kini kontrol di SUN ]          │
│ 0896-5656-9938 [WA]                                                          │
│ B-2011-3014 · Metal · Keduanya · drg. Vega                                   │
│ Pasang 12 Okt '25 di BIN · berjalan 1 tahun 3 bulan                          │
│ [ ✓ Tandai Selesai (bracket dilepas) ]  [ Ubah data pemasangan ]             │
├──────────────────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐                          │
│  │ TOTAL KONTROL│ │ KEHADIRAN    │ │ JARAK RATA²  │                          │
│  │     12       │ │  9/12 · 75%  │ │   38 hari    │                          │
│  │              │ │              │ │  target 30   │                          │
│  └──────────────┘ └──────────────┘ └──────────────┘                          │
├──────────────────────────────────────────────────────────────────────────────┤
│  #  JADWAL      FOLLOW-UP  RESERVASI  DATANG    CABANG  STATUS        AKSI    │
│  1  12 Okt '26  12 Okt     X          X         BIN     Tidak hadir   —       │
│  2  12 Nov '26  13 Nov     15 Nov     X         BIN     Tidak hadir   —       │
│  ─────────────── transfer ke Sunter · 20 Des ───────────────────────────────  │
│  3  12 Des '26  14 Des     20 Des     20 Des    SUN     Hadir         —       │
│ ▌4  12 Jan '27  X          X          X         SUN     Terlewat 6 h  [FU][✕] │
├──────────────────────────────────────────────────────────────────────────────┤
│  ┌ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┐      │
│    Jadwalkan kontrol berikutnya                                              │
│    Selesaikan dulu kontrol #4 — catat kunjungan atau tandai tidak hadir.     │
│    Jadwal lahir satu per satu, tidak dibuat di muka.  [ tombol MATI ]        │
│  └ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ┘      │
└──────────────────────────────────────────────────────────────────────────────┘
```

1. **Header drawer** bergradient cokelat: breadcrumb kecil, nama besar, chip `kini
   kontrol di <Cabang>` kalau cabang penagih ≠ cabang pasang, link WhatsApp hijau, lalu
   `RM · bracket · rahang · dokter` dan `Pasang <tgl> di <BR> · berjalan <N> bulan`.

   ⚠️ **Beri ruang cukup** — di Revisi 1 nama panjang bertabrakan dengan link WA dan
   gelar dokter sampai tidak terbaca. Nama, kontak, dan meta harus bertumpuk rapi sebagai
   tiga baris terpisah, bukan saling menimpa.

   Kalau nomor HP tidak valid: teks abu + ikon peringatan dengan tooltip *"Nomor tidak
   valid, tidak bisa dibuka di WhatsApp"* — **jangan tombol mati**, jangan link buntu.

2. **Tiga KPI kecil**: `Total kontrol` · `Kehadiran x/y · %` · `Jarak rata-rata N hari`
   dengan pembanding kecil "target 30 hari". Kecil dan sebaris — jangan hero.

3. **Matriks kontrol** — tabel rapat, kolom persis papan tulis:
   `# · Jadwal · Follow-up · Reservasi · Datang · Cabang · Status · Aksi`.

   - Sel kosong ditulis **`X` muted** `#A9866A` — sesuai papan tulis. Sel kosong terasa
     seperti data hilang; `X` terasa seperti fakta.
   - **Baris yang cabangnya berbeda dari baris sebelumnya** diberi garis pemisah tipis
     dengan label kecil di tengahnya: *"transfer ke Sunter · 20 Des"*. Inilah yang
     membuat riwayat perpindahan terbaca sebagai cerita, bukan tebakan.
   - **Baris yang masih terbuka** diberi latar `#FBF4EC` + garis tepi kiri aksen, membawa
     tombol tindakan + tombol kecil `✕` untuk menandai tidak hadir.
   - Baris yang sudah ditutup: kolom Aksi berisi `—` dengan tooltip yang menjelaskan
     kenapa sudah tidak bisa ditindak.

4. **Blok "Jadwalkan kontrol berikutnya"** di kartu bergaris putus-putus. Aktif **hanya**
   kalau baris terakhir sudah ditutup. Kalau belum, tombol mati **dengan alasan tertulis
   di sebelahnya** — bukan cuma abu-abu.

5. **Drawer "Catat Kunjungan"**: tanggal datang (default = tanggal reservasi) ·
   **dropdown cabang** (default = cabang jadwal) · catatan.
   Di bawah dropdown cabang, kotak peringatan yang muncul **hanya kalau cabangnya
   diubah**: *"Pasien akan dipindahkan ke Sunter. Sunter yang menghubungi untuk kontrol
   berikutnya."*

6. Semua aksi tulis → **toast + tombol "Urungkan"**, muncul di bawah-tengah, hilang
   sendiri setelah 8 detik. Tidak ada dialog "Anda yakin?" di mana pun di halaman ini.

---

## Yang TIDAK usah didesain

- **Layar proses penyemaian.** Penyemaian dijalankan sekali oleh tim IT lewat perintah,
  bukan lewat tombol di halaman — jangan desain progress bar, layar impor, atau
  pratinjau data.
- **Reminder WhatsApp otomatis.** Aplikasi tidak punya jalur kirim WA keluar sama sekali
  — integrasi WhatsApp yang ada hanya arah masuk. Tombol WA membuka WhatsApp di tab baru;
  pesannya diketik dan dikirim manual oleh Sales. Jangan gambar antrean pesan, jadwal
  blast, atau editor template.
- **Kolom "Datang" terisi sendiri dari rekam medis.** Menyusul di tahap berikutnya
  setelah aturan pencocokannya terbukti. Di versi ini Sales mengetik.
- **Export Excel** dan **laporan** — belum masuk versi pertama. Ini diakui sebagai
  kekurangan terhadap brief konsultan, bukan hal yang dilupakan.
- **Grafik apa pun.** Ini halaman kerja, bukan dashboard. Kartu ringkas sudah cukup.
- **Status gaya sheet lama** ("Tdk Aktif 6bln", "1 tahun tidak kontrol", "2 tahun tidak
  balas"). Status dihitung dari jadwal dan riwayat, bukan diketik manusia.
- **Kolom PIC / penanggung jawab per pasien.** Tanggung jawab mengikuti cabang. Yang
  dicatat hanya siapa yang mengisi tiap baris, dan itu muncul di tooltip — bukan kolom.

---

## Catatan untuk implementasi (bukan untuk ditempel ke Claude Design)

- Segmen URL sendiri **`tracking-ortho`** — jangan nebeng `finance`/`report`, karena
  segmen itu ada di daftar lolos-RBAC sehingga halaman di bawahnya tidak dijaga.
- Dua tabel baru: `ortho_case` (episode perawatan) + `ortho_control` (baris jadwal).
  Kolom `visit_source`, `omnicare_trx_id`, `visit_confidence` disiapkan sejak awal supaya
  tahap berikutnya (auto-isi kolom Datang) **nol `ALTER TABLE`**.
- Kolom `owner_place_id` di `ortho_case` = cabang penagih sekarang. Inilah yang
  memindahkan pasien antar cabang saat transfer.

**Penyemaian:**

1. **Perintah sekali jalan**, kering (`--dry-run`) sebagai default; menulis butuh
   `--commit`. Menyisir `omnicare_sales_detail` **per cabang per rentang tanggal** —
   pakai index `(place_id, trx_date)` yang **sudah ada**. Jangan query per `p_id`; index
   `(p_id, trx_date)` **belum terpasang** dan pola itu sudah pernah bikin full-scan
   ~1,8 detik di kolom lain.
2. **Wajib membawa tanggal kunjungan 12 bulan terakhir**, bukan cuma kontrol terakhir.
   Tanpa itu hitungan rolling 6 bulan ("jarang kontrol") basi setelah beberapa minggu.
   Murah — rata-rata 8–14 baris per pasien.
3. **Anchor identitas ke `omnicare_patient.pid`**; `raw_customer_id` **nullable**,
   ditautkan lewat `customer_omnicare_link` (query terpisah, `whereIn`, **tanpa join**).
   Tautan itu UNIQUE di kedua sisi, jadi pasien dengan dua pid akan muncul sebagai **dua
   kasus** — terlihat, bukan hilang diam-diam.
4. **Aturan baca item ortho hidup di SATU kelas.** ⚠ `Lepas Bracket` (akhir perawatan)
   **jangan** tertukar dengan `Bracket Metal Lepas` (bracket copot lalu dipasang ulang —
   1.396 + 175 baris). Salah baca = ribuan pasien salah ditandai selesai di hari pertama.
5. **Seeder tidak pernah menimpa** data yang sudah disentuh Sales. Boleh dijalankan ulang
   kapan saja untuk menangkap pasien pasang baru.

**Sambungan Reservasi:**

- Arah maju (buat reservasi) memakai pola prefill drawer yang sudah terbukti dua kali di
  `ReservationController::create()`.
- Arah balik (reservasi diubah/dibatalkan) memakai **satu Eloquent Observer**, bukan kait
  di controller. Sudah diperiksa: ketiga jalur tulis (`update()`, `updateInline()`,
  `destroy()`) semuanya lewat model, dan **tidak ada satu pun mass-update** di `app/` yang
  menembusnya — jadi `ReservationController` tidak disentuh sama sekali untuk urusan ini.

**Umum:**

- **Nol FOREIGN KEY fisik** ke `raw_customer` / `raw_branch` / `raw_cus_reservation`, dan
  **nol `->join()`** lintas keluarga tabel — collation produksi campur, FK akan **ditolak
  di prod padahal lolos di lokal**. Pola aman: ambil nilai dulu sebagai query terpisah,
  tempel sebagai literal.
- Cabang disimpan sebagai `place_id`, bukan `raw_branch_id`.
- File SQL akses harus grant **by link** dan **grant node parent juga**, lalu
  **`php artisan access:rebuild` wajib** — kalau lupa, 404 di SEMUA halaman.
- Deploy prod: jalankan SQL manual + **rsync `public/assets/**` ke `public_html`**.

### Tiga hal yang masih tebakan dari foto papan tulis

1. Tulisan `BIN | SUN` di atas kolom SCH dan FU dibaca sebagai label pasien contoh
   (pasang di BIN, sekarang di SUN), **bukan** header grup kolom.
2. Kotak `RSV` di bawah kolom RSV dianggap coretan penegas nama kolom, bukan tombol atau
   total.
3. Di ERD, `ORT_SCH` tidak terhubung langsung ke `PX`. Rancangan ini tetap menggantungkan
   jadwal ke pasien secara langsung — tanpa itu, jadwal yang belum punya reservasi maupun
   kunjungan (baris #4 di foto, semuanya `X`) tidak punya pemilik.

### Tiga temuan dari handoff Revisi 1 yang BELUM diputuskan

Dicatat supaya tidak hilang. Ketiganya tidak menghalangi pengerjaan Revisi 2.

| Temuan | Catatan |
|---|---|
| Tab "Pasien Ortho" dibuat **tabel**, brief minta grid kartu + toggle | Revisi 2 menegaskan ulang permintaan kartu di Sesi 3 |
| **Nol `@media`** di prototipe — desktop-only | Brief minta kartu tetap terbaca di HP |
| Sheet "Buat Reservasi" membangun form sendiri (10 slot jam, tanpa dokter) alih-alih memakai form Reservasi yang ada | Memotong aturan anti-bentrok, pilihan layanan, dan promo |
