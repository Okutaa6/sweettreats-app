# Rencana: Rekonsiliasi Absensi

Status: **draft v2, menunggu ACC**
Ditulis ulang: 2026-07-17 (v1 punya 6 kesalahan analisis — lihat §10)
Branch rencana: `feature/absensi` (dari `user-hirarki`)

---

## 1. Masalah

Finance ngurus absen 5 cabang manual. Tiap tanggal 20 mereka export data fingerprint, cocokin
satu-satu nama/tanggal/jam, cek silang ke Google Form cuti/lembur/libur, dan kalau masih ragu
chat orangnya.

Cut-off penggajian **seharusnya tanggal 25**, dimundurin ke **20** semata-mata karena
rekonsiliasinya makan waktu. Biayanya bukan cuma jam kerja finance — tapi 5 hari kalender.

**Kenapa finance harus chat orangnya?** Bukan karena datanya kurang. Karena **dua sumbernya
nggak pernah ketemu**: mesin fingerprint punya jam, Google Form punya klaim, dan nggak ada
tempat di mana keduanya diadu. Finance jadi satu-satunya "sendi" antara dua sistem itu.

**Definisi selesai:** finance buka satu halaman, klaim udah otomatis disandingin sama scan,
dan yang perlu perhatian udah ditandain. Cut-off balik ke 25.

---

## 2. Temuan dari data

Sumber: `test absen juli bintaro.csv` (Bintaro, 1–15 Jul), `test absen.csv` (dump 5 tahun mesin
Bintaro, 26.778 scan), `absen sweet treats 5 cabang/` (4 cabang, Juni), `Form Lembur (2024) -
Form Responses 1.csv` (5.238 klaim, 21 bulan), + query prod (2026-07-17).

### 2.1 Mesin nggak nyimpen penanda masuk/pulang

Kolom `Pengecualian` **kosong di 26.778 baris**. `Verifikasi` = `Sidik Jari` semua. Tiap scan
cuma timestamp telanjang.

**Konsekuensi:** masuk/pulang cuma bisa disimpulin dari posisi waktu. Teori "staff salah pencet
tombol" nggak berlaku — mesinnya nggak punya tombol itu.

### 2.2 Tiga format export, cuma Bintaro yang log mentah

| Cabang | Judul laporan | Bentuk |
|---|---|---|
| **Bintaro** | *(tanpa judul)* | **log mentah per-scan**, ada detik |
| Cinere, Kalideres, Sunter | `Lap. Detail Absensi` | **matriks** — 1 baris/orang, kolom = tanggal |
| Pamulang | `Catatan Kehadiran Karyawan` | matriks, **software beda** (titik-dua fullwidth `：`) |

Semua: BOM UTF-8, CRLF, delimiter `;`. Jam **didempet tanpa pemisah**:

```
Cinere, Della, tgl 5   : "10:0021:10"            -> 10:00, 21:10
Kalideres, Cici, tgl 5 : "09:5009:5020:2321:41"  -> 4 scan dalam satu sel
```

Kepecah cuma karena `HH:MM` selalu 5 karakter. **Pamulang beda lagi** — jam dipisah **newline
di dalam sel ber-kutip**. Wajib `fgetcsv`, bukan `explode("\n")`.

Laporan matriks **nyimpen semua scan** (ada sel isi 3–4 jam), jadi nggak ada data hilang. Detik
hilang, tapi nggak penting: engine cuma butuh scan pertama & terakhir.

> **CEK SEBELUM NGODING (§8):** cabang lain bisa export log mentah kayak Bintaro? Kalau bisa,
> 2 parser ini hilang. Bedanya ~setengah kerjaan Fase A.

### 2.3 No.ID bentrok antar cabang — 26 kasus

Tiap mesin mulai penomoran dari 1:

```
ID 1 : cinere=Fikri | kalder=Fikri | pamulang=seli s
ID 5 : cinere=Amanda | kalder=Ryan | pamulang=yovan | sunter=IRMA
```

Kunci mapping **wajib** `(raw_branch_id, device_user_id)`. Dalam satu mesin, ID **nggak pernah
didaur ulang** (57 ID, 5 tahun, nol kasus) → mapping nggak butuh `effective_from/to`.

### 2.4 Kolom `Departemen` BUKAN nama cabang

```
Bintaro   : "Bintaro"
Cinere    : "Perusahaan"
Pamulang  : "ADMIN" / "PERUSAHAAN"
Kalideres : "HeadQuarter" / "OP1" / "OP2" / "Dokter"   <- satu-satunya yang ngisi bener
```

Cabang **harus dipilih manual saat upload**. Itu justru benar — yang upload tau dia dari mana.

### 2.5 Nama di mesin nggak bisa dipakai buat identitas

`asih~`, `DrgSheiLa`, `ROHMA~~`, `MUTI~`, `TaliTa` vs `Talitha`, `Nisa` vs `Anisa`, dan satu
user bernama literal `"10"`. Satu ID `66666666` (8 digit) — kemungkinan enrollment tes.

**Yang paling berbahaya — nama kembar tapi orang beda:**

```
DeLa  (Bintaro, 18hr) = Della Puspita Sari (finance)
Della (Cinere, 24hr)  = Della Raqisiah (sales)        <- DUA ORANG BEDA
```

Kepisah **cuma karena kebetulan** Bintaro ngetik `DeLa`. Kalau sama, dua orang ini kegabung dan
absen finance kecampur sales Cinere tanpa ada yang ngeh.

**Dan kebalikannya — nama beda tapi orang sama:** `gita`/`anggita`, `putri`/`puttri`,
`nisa`/`anisa`, `yovan`/`yovanka`. Empat kasus, semuanya lolos dari pencocokan otomatis (§10).

### 2.6 Bintaro itu kantor pusat

Mesin Bintaro 18 orang, roster operasionalnya **5**. Sisanya office/management/dokter —
termasuk head IT dan Reza. Satu file, campuran orang jam-baku dan fleksibel.

**Konsekuensi:** aturan kerja itu properti **per-orang**, bukan per-cabang/per-file. Dikuatkan
lagi oleh Irma & Anisa (§3.4).

### 2.7 SATU HARI KERJA BISA LINTAS CABANG

Dikonfirmasi manajemen: Rohma perawat Sunter yang nutupin Kalideres saat understaffed;
marketing tap masuk Bintaro tap pulang cabang lain.

```
tgl  Sunter (ID 39)      Kalideres (ID 30)
 2   09:32               21:21           -> masuk Sunter, pulang Kalideres
 4   22:03               09:49           -> masuk Kalideres, pulang Sunter
21   08:35  23:04        09:15  20:51    -> 08:35 Sunter, 09:15 Kalideres,
                                            20:51 Kalideres, 23:04 Sunter
```

Rohma terdaftar di **4 mesin**, aktif di 2. Alif aktif di **4 cabang** (25 hari). Anisa aktif
di 3 — tanggal 2 dia scan di Cinere **dan** Sunter.

**Konsekuensi:**
1. `att_day` **nggak bisa dimiliki satu cabang**. Scan wajib digabung **per user per tanggal,
   lintas SEMUA mesin**, baru dinilai.
2. `att_day.raw_branch_id` = cabang scan pertama, **cuma buat pengelompokan laporan**. Bukan
   sumber kebenaran.
3. Dedup harus **lintas mesin**.

### 2.8 Scan lewat tengah malam — universal, bukan cuma Sunter

```
Sunter  19/06 : IRMA 00:54 09:17 21:18 | ANISA 01:40 09:49 19:03 | + 3 orang lagi
Sunter  08/06 : TITO 00:06 07:48 19:29
Bintaro 20/06 : Ikhsan 00:01 08:41 21:42
```

Scan jam 00:54 itu **pulang tanggal 18** yang jatuh ke tanggal 19. Tanpa penanganan, engine
baca IN = 00:54 padahal dia masuk 09:17.

> v1 bilang "Bintaro aman, nggak ada scan jam 00–06". Itu disimpulin dari **Juli 1–15 doang**.
> Juni punya. Aturannya **universal**.

### 2.9 Hari libur: Senin — tapi jangan disimpulin dari data

```
cinere/kalder/pamulang : NOL scan di 5 Senin. Mutlak.
sunter                 : 2-6 orang tiap Senin (rata 3.4 vs 6.8 hari biasa)
bintaro                : 0 di 4 Senin, 2 orang di 1 Senin
```

Sunter Senin **bukan sifat cabang — itu akreditasi** (§2.12). Jam baku hari Minggu juga lebih
pendek (semua yang pulang <18:00 nyangkut di Minggu).

> **Pelajaran:** "tutup" itu bukan biner, dan **nggak boleh** disimpulin dari ada-atau-nggaknya
> scan. Harus dari `att_work_rule_day` yang diisi manusia (§10).

### 2.10 LEBARAN — libur tahunan yang nggak bisa dijadwal

Riwayat 5 tahun mesin Bintaro, libur ≥3 hari berturut:

```
Tahun  Mulai          Selesai        hari   geser
2021   Mon, 10 May    Mon, 17 May       8
2022   Sun, 01 May    Sat, 07 May       7
2023   Wed, 19 Apr    Mon, 24 Apr       6    -12 hari
2024   Mon, 08 Apr    Tue, 16 Apr       9    -10 hari
2025   Sat, 29 Mar    Mon, 07 Apr      10    -11 hari
2026   Thu, 19 Mar    Tue, 24 Mar       6    -10 hari
```

**Maju 10–12 hari tiap tahun, konsisten 5 tahun** = selisih kalender Hijriah vs Masehi. Tiap
libur melingkupi Idul Fitri tahun itu. Nggak ada libur nasional lain yang gerak begini.

**Ini yang bikin exception WAJIB, bukan pilihan.** Libur ini nggak bisa ditulis sebagai aturan
berulang — 2021 jatuh Mei, 2026 jatuh Maret. Tanggal pastinya baru ketauan beberapa hari
sebelumnya (sidang isbat).

Tanpa exception, **tiap Lebaran sistem nandain seluruh karyawan 5 cabang alpa selama seminggu.
Tiap tahun.** Sistem tanpa exception nggak cuma kurang lengkap — dia **rusak di tahun kedua**.

### 2.11 Klaim lembur SUDAH rutin — dan nggak nyambung sama scan

```
5.238 klaim, Nov 2024 - Jul 2026, 21 bulan  -> 249 klaim/bulan, stabil, nggak pernah bolong
709 jam lembur diklaim/bulan, 5 cabang
100% klaim ada foto bukti (5.238 dari 5.238)
```

**Juni 2026, 5 operasional Bintaro — klaim form vs sidik jari mereka sendiri:**

| Nama | Divisi | DIKLAIM | potensi (SOP) | selisih |
|---|---|---|---|---|
| Apajri Suhendar Al Qhusni | GS | 68.0 | 83.9 | **−19%** |
| Firda Mareta Naskah | FO | 38.8 | 39.5 | −2% |
| Afrina Apriliani | FO | 44.5 | 41.8 | +6% |
| Rizka Noviyanti | Asdent | 52.5 | 35.7 | **+47%** |
| Asih Wulan Sari | Asdent | 52.5 | 25.2 | **+108%** |
| | | **256.3** | **226.2** | |

**Klaim dan scan nggak nyambung — ke DUA arah.** Asih 2× lipat dari yang scan-nya dukung,
Fajri malah kurang dari haknya, Firda pas.

Nggak ada yang bisa liat ini sekarang. Ini **inti masalahnya**, dan ini yang fitur ini
selesaikan. Nilainya bukan nurunin atau naikin biaya lembur — tapi **bikin angkanya jujur**.

> **v1 SALAH TOTAL** soal ini. Lihat §10.1.

### 2.12 Akreditasi Sunter — Mar s/d Jun 2026, cuma Sunter

Jam lembur diklaim per cabang per bulan:

```
Bulan      BINTARO   SUNTER  KALIDERES  PAMULANG   CINERE    klaim ber-alasan "akre"
2025-09        288      209        139        79      137
2025-12        274      130        130       118       86
2026-02        232       98        134       112       60
2026-03        148       79         90        66       41    3   -> SUNTER:3      <- Lebaran
2026-04        309      195        174       150       93   22   -> SUNTER:19
2026-05        308      237        106        79       87   15   -> SUNTER:14
2026-06        270      414        111        97      106   40   -> BINTARO:2 SUNTER:38
```

**Sunter Juni 414 jam vs rata-ratanya ~170 = 2.4×.** Empat cabang lain Juni **pas di
rata-rata mereka sendiri**. Akreditasi = **38 dari 40 klaim Juni ada di Sunter**.

**Jadi Juni itu NORMAL buat 4 cabang.** Nggak perlu minta export bulan lain. Yang perlu cuma
satu deklarasi exception: *Sunter, Mar–Jun 2026, akreditasi*.

Turunnya Feb–Mar di **semua** cabang = Ramadan + libur Lebaran (§2.10).

### 2.13 Prod: 16 user, mayoritas akun PERAN bukan akun ORANG

```
total user: 16     (buat ~41 staff)
Sales    6 user  -> "Sales Bintaro", "Sales Cinere", "Sales kalideres",
                    "Sales Pamulang", "Sales Sunter", "Sales Admin"
group_path: id, nama, deskripsi, landing_page, theme   <- BELUM ada parent_id/level
sys_user_position: ADA
```

`Sales Bintaro` **dipakai berdua** — Apin (sales) dan Firda (kasir). Absensi itu per-manusia.
Akun peran nggak bisa ngajuin cuti, nggak bisa punya saldo cuti, dan kalau orangnya ganti,
riwayatnya jadi ngaco.

Jejak yang nyantol:

```
promo_klaim_by (komisi)  : KOSONG — nol baris
updated_by (reservasi)   : Sales Sunter 937 | Sales Bintaro 483 | Cinere 339 |
                           kalideres 259 | Pamulang 228
```

**Keputusan: JANGAN rename — bikin akun baru per orang, akun peran dipensiunin (`status=0`).**
Rename secara teknis aman (UUID tetap), tapi bakal ngeklaim 483 reservasi itu kerjaan Afrina
padahal separuhnya mungkin Firda. Riwayat lama tetap atas nama `Sales Bintaro` = **jujur**,
kita emang nggak tau manusia mana. Ongkos: 5 orang bikin login baru.

Efek samping bagus: begitu akun per orang, form nggak perlu ketik nama → typo
`putri syafira`/`putri syafita` mati sendiri.

`group_path.parent_id` belum ada di prod → pohon approval belum ada di produksi. Prasyarat
Fase C.

---

## 3. Aturan (dari management, 2026-07-17)

### 3.1 SOP operasional

| Posisi | Hari Kerja | Check In | Check Out | Jam Normal | Ketentuan Lembur |
|---|---|---|---|---|---|
| General Service (GS) | 6 hari/minggu | **08:45–09:00** | 17:00 | 7 jam/hari | 17:00–18:00 istirahat |
| Front Office (FO) | 6 hari/minggu | **09:50–10:00** | 18:00 | 7 jam/hari | 18:00–19:00 istirahat |
| Assistant Dentist | 6 hari/minggu | **09:50–10:00** | 18:00 | 7 jam/hari | 18:00–19:00 istirahat |

1. Lembur hanya atas persetujuan/instruksi atasan.
2. Kalau pasien masih registrasi/pembayaran/administrasi mulai 18:00+ sehingga FO nggak bisa
   pakai istirahat 18:00–19:00 → perhitungan lembur mulai 18:00.
3. Kalau pasien dijadwalkan mulai 19:00 → FO tetap istirahat 18:00–19:00, lembur mulai 19:00.
4. **GS di bawah 08:45 terhitung lembur.** ⚠️ bentrok dengan §3.2 — lihat §8.

### 3.2 Aturan turunan

1. **Jam kerja resmi = jam baku, selalu.** Dateng awal tetap dihitung dari jam baku.
2. **Telat → jam pulang DIDORONG** selambat telatnya. Telat 8 menit → pulang 18:08.
3. **Lembur cuma sah kalau diklaim** lewat form + approve. Berlaku operasional DAN fleksibel.
   Scan **nggak pernah** nyiptain lembur — Ikhsan scan 08:00–23:00 pun, tanpa form = nol.
4. **1 scan doang = dianggap hadir.**
5. **Lembur hari libur ("tambah hari")** itu jenis TERPISAH — nggak ada jam baku buat
   dilewatin. Kemungkinan tarifnya beda (belum dikonfirmasi, §8).

### 3.3 Office / management / ABG (fleksibel)

- Jam bebas, **nggak ada telat**.
- **Nggak dinilai dari scan sama sekali.** Scan cuma penanda "masuk", nggak pernah bikin alpa.
- **Nggak dapet lembur** kecuali kasus khusus, dan cuma lewat form.
- **Privilege WFH 2× per bulan** — **dihitung dari form yang di-approve**, bukan dari scan.
  Form ke-3 ditolak otomatis.

**Kenapa nggak dinilai scan:** aturan di atas bikin scan office nggak nyiptain telat dan nggak
nyiptain lembur. Jadi dia cuma jawab "dia dateng ke klinik nggak?" — dan buat orang yang boleh
kerja dari mana aja, itu nggak ngasih makan apa pun.

Bukti kalau maksa: kalau "nggak ada scan = alpa" + kuota 2 dipasang ke Juni (data udah
digabung lintas cabang):

```
Ikhsan 4 alpa | Reza 4 | Della P.S. 5 | Ela 4 | Talitha 13 | Yovanka 2 | Alif 3 | Anisa 1
                                                                   TOTAL 36 hari alpa/bulan
```

Head IT ketandain bolos 4 hari. Kuota 2 ngasumsiin selain itu mereka tap tiap hari — mereka
nggak (Talitha tap 10 dari 25 hari).

### 3.4 Pengecualian: Irma & Anisa

**Irma Islamiati** (koordinator asdent) dan **Anisa Aulia** (SPV operasional) itu
office/management **tapi ikut jam operasional dan punya hak lembur**, karena terlibat langsung
di operasional dan masuk hari Minggu.

Ini bukti kedua bahwa **aturan kerja nempel ke ORANG, bukan ke divisi/jabatan**. Kalau aturan
diturunkan dari jabatan, lembur mereka bakal ditolak sistem tiap bulan (Anisa: 18 klaim / 69,4
jam di Juni — sah semua).

### 3.5 Roster (dikonfirmasi 2026-07-17)

**Operasional (19) — jam baku + hak lembur**

| Cabang | Nama lengkap | Di mesin | Divisi |
|---|---|---|---|
| Bintaro | Apajri Suhendar Al Qhusni | Fajri #6 | GS |
| Bintaro | Rizka Noviyanti | RIZKA #12 (+Kalideres #25) | Asdent |
| Bintaro | Asih Wulan Sari | asih~ #48 | Asdent |
| Bintaro | Afrina Apriliani | Apin #43 | FO – sales |
| Bintaro | Firda Mareta Naskah | firda #47 | FO – kasir |
| Sunter | Tito Muhammad Taqiyuddin | TITO #42 | GS |
| Sunter | Verini Selvia Nashkah | VERIN #40 | FO – kasir |
| Sunter | Melani Safrida | MELAN #43 | FO – sales |
| Sunter | Putri Syafira | puttri #34 (+Kalideres #28) | Asdent |
| Sunter | Rokhmatun | ROHMA~~ #39 (+Kalideres #30) | Asdent |
| Kalideres | Ramdan Ramdhani | Ramdan #16 | GS |
| Kalideres | Rivanti Nadiana | Rivanti #15 (+Sunter #4) | FO (sales+kasir) |
| Kalideres | Cici Nurjanah | Cici #9 | Asdent |
| Pamulang | Ahmad Saipulloh | Ipul #4 (+Cinere #19) | GS |
| Pamulang | Mutiara Nurul Fajri | muti #14 | FO (sales+kasir) |
| Pamulang | Alviona Zunka Prasetyo | viona #7 | Asdent |
| Cinere | Wahyudin | Wahyu #11 | GS |
| Cinere | Della Raqisiah | Della #3 | FO (sales+kasir) |
| Cinere | Anggita Sarah Aprillia | ANGGITA #8 (+Pamulang #24) | Asdent |

> **FO dibagi 2: sales & kasir.** Bintaro & Sunter punya 2 orang terpisah; 3 cabang lain 1 orang
> merangkap. **Aturan absennya sama** (10:00–18:00) — pembagian ini nggak ngaruh ke jam.

**Office / fleksibel**

| Nama lengkap | Di mesin | Jabatan | Aturan |
|---|---|---|---|
| Muhamad Ikhsan (Ekky) | Ikhsan #52 | Head IT | fleksibel |
| Reza Fauzi Ramadhan | Reza #62 | IT | fleksibel |
| Della Puspita Sari | DeLa #61 | Finance | fleksibel |
| Alif Imam Fadillah | ALIF #60 + 4 cabang | Creative / marketing | fleksibel |
| Yovanka Rima Millennia | Yovanka #39 + 3 cabang | Head Marketing | fleksibel |
| Talitha Maritza Devi | TaliTa #63 + 2 cabang | Content Creator | fleksibel |
| **Anisa Aulia** | Nisa #38 + 4 cabang | SPV Operasional | **operasional + lembur** |
| **Irma Islamiati** | Irma #37 + Sunter #5 | Koordinator Asdent | **operasional + lembur** |

Belum teridentifikasi: `Ela` #58 (19 hari), `hari~` #59, `Jambang` #23, `Lena` #51,
`Ipay`/`ojah`/`Adi`/`Ryan`/`Azra` (nol scan). Dokter → `ignored`.

---

## 4. HIRARKI KEBENARAN

Ini keputusan arsitektur paling penting di dokumen ini. v1 salah di sini (§10.2).

```
1. DEKLARASI MANUSIA (form cuti/izin/libur/lembur/WFH, di-approve)   <- tertinggi
2. ATURAN KERJA + EXCEPTION (dideklarasiin juga)                     <- konteks
3. SCAN FINGERPRINT                                                  <- pengamatan
```

**Scan itu pengamatan.** Dia cuma bisa jawab: *ada badan nempel di mesin ini, jam segini.* Dia
nggak bisa mbuktiin orang kerja (bisa tap terus pulang), dan **nggak bisa mbantah orang kerja**
— Ikhsan WFH, mesinnya 20 km dari dia.

**Form + approval itu keputusan.** Ada manusia yang nyatain "ini kerjaan, ini dibayar", dan dia
tanggung jawab. Keputusan **selalu** menang atas pengamatan.

### Sistem NGGAK PERNAH mutusin sendiri

v1 mendesain sistem yang ngasih vonis `COCOK` / `NGGAK COCOK`. Itu bikin sistem jadi hakim
tanpa wewenang — dan Ikhsan mbuktiin kenapa salah: klaimnya bakal dicap "nggak cocok" padahal
dia beneran kerja 7 jam.

Yang bener, sistem **ngasih makan hakimnya**:

```
"Klaim 4 jam. Scan pulang 21:03, jam baku 17:00 — nyambung."
"Klaim 4 jam. Scan pulang 18:05 — cuma dukung 1 jam. Cek dulu?"
"Klaim 7 jam, WFH — nggak ada scan, dan emang nggak diharapkan ada."
```

Semuanya kalimat buat dibaca supervisor. **Sistem nggak pernah approve/reject sendiri.**

### Konsekuensi: foto bukti TETAP perlu

v1 nyiratin scan bakal gantiin foto. **Salah.** Buat klaim tanpa scan (WFH, hari libur, office),
foto itu **satu-satunya bukti**. Dua jalur:

```
ada scan + hari kerja  -> sistem sandingin, supervisor approve cepat
nggak ada scan         -> sistem bilang "NGGAK BISA DICEK" — bukan "cocok".
                          Foto + approval, kayak sekarang.
```

Sistem harus **ngaku kalau dia nggak tau**, bukan diam-diam meloloskan.

### Jebakan yang harus disadari

Kalau form+approval itu puncak, **apa yang nahan Asih ngeklaim 2× lipat?** Cuma approval — dan
approval **udah ada sekarang**, 5.238 klaim itu semua lewat atasan.

Kalau approval cuma stempel, kita nggak ngubah apa-apa. Yang bikin beda **cuma satu**:
supervisor sekarang **liat angka scan-nya pas mutusin**. Bukan sistem yang nolak — orangnya yang
jadi tau. **Itu doang bedanya, dan itu seluruh nilai fiturnya.**

---

## 5. Arsitektur

### 5.1 Prinsip

1. **Jangan bikin tabel `employee`.** Staff = `users` + `sys_user_position` (history-aware).
2. **Scan mentah itu suci.** `att_scan` append-only. Semua penilaian di tabel turunan yang bisa
   dihitung ulang.
3. **Netral-sumber.** `att_scan.source` = `fingerprint` hari ini, `mobile`/`adms` nanti.
4. **Ikut pola repo:** importer contek `ReservationCsvImporter`; periode contek
   `petty_cash_periods` + `close()`; rule+exception+resolver contek `raw_doctor_schedule` +
   `raw_doctor_schedule_exception` + `ResolvesDoctorAvailability`.
5. **DB pakai SQL langsung** di `database/sql/`, bukan migration.

### 5.2 Tabel

```
att_device_user          mapping (cabang, No.ID) -> users     [many-to-one: Rohma punya 4]
  raw_branch_id, device_user_id, user_id (nullable), nama_di_mesin,
  status enum('mapped','ignored','unmapped')
  UNIQUE(raw_branch_id, device_user_id)          -- wajib, 26 ID bentrok (§2.3)

att_import_batch
  raw_branch_id, uploaded_by, file_name, format enum('log','matriks_a','matriks_b'),
  periode_from, periode_to, total_rows, new_rows, dup_rows, unmapped_rows,
  diagnostic json, uploaded_at

att_scan                 mentah, append-only
  batch_id, raw_branch_id, device_user_id, user_id (nullable), scanned_at,
  source enum('fingerprint','mobile','adms','manual'), raw_row json,
  hash UNIQUE            -- sha1(branch|device_user_id|scanned_at) -> idempotent

att_work_rule            nama, tipe enum('baku','fleksibel')
att_work_rule_day        rule_id, day 0-6, libur bool, jam_masuk_awal, jam_masuk_akhir,
                         jam_pulang, jam_lembur_mulai    -- window check-in (§3.1)
att_work_rule_exception  raw_branch_id (nullable=semua cabang), tgl_from, tgl_to,
                         libur bool, jam_masuk/pulang override, alasan, dibuat_oleh
  -- WAJIB. Lebaran tiap tahun 5 cabang (§2.10) + akreditasi Sunter Mar-Jun (§2.12)
att_user_rule            user_id, rule_id, effective_from, effective_to
  -- per ORANG, bukan per divisi (§2.6, §3.4)

att_request              5 form: cuti | izin | libur | lembur | wfh
  user_id, raw_branch_id, tipe,
  sub_tipe enum('jam','tambah_hari')   -- lembur hari libur beda jenis (§3.2.5)
  tanggal_from/to, jam_from/to, alasan, foto, claimed_menit,
  scan_evidence json, verifikasi enum('nyambung','selisih','tidak_bisa_dicek'),
  status enum('draft','pending_l1','pending_l2','approved','rejected','cancelled')
  -- 'verifikasi' itu INFORMASI buat approver, BUKAN vonis (§4)

att_request_approval     request_id, level (1|2), expected_role_id, approver_user_id,
                         action enum('approve','reject'), catatan, acted_at

att_day                  HASIL — turunan, bisa dihitung ulang
  -- raw_branch_id = cabang scan PERTAMA, cuma buat pengelompokan. BUKAN kebenaran (§2.7)
  user_id, raw_branch_id, work_date, rule_id (snapshot),
  scan_first, scan_last, scan_count, single_scan bool, cabang_terlibat json,
  status enum('hadir','telat','alpa','cuti','izin','libur','wfh'),
  telat_menit, wajib_pulang, lembur_menit_disetujui, computed_at
  UNIQUE(user_id, work_date)

att_period               raw_branch_id, periode_from, periode_to,
                         status enum('open','closed'), closed_by, closed_at
```

### 5.3 Engine — `app/Services/AttendanceReconciler.php`

Murni, idempotent, bisa dijalanin ulang kapan aja.

```
untuk tiap (user, tanggal):
  0a. GABUNG scan dari SEMUA cabang untuk user ini            <- WAJIB §2.7 (Rohma)
  0b. scan sebelum ambang dini hari (default 06:00, disetel)
        -> LEMPAR BALIK ke tanggal sebelumnya sebagai OUT     <- WAJIB §2.8
  1.  dedup jarak <= N menit (default 5, disetel) — LINTAS MESIN juga
  2.  ambil rule user (att_user_rule) -> tipe baku / fleksibel
  3.  ambil exception (att_work_rule_exception) -> menang atas rule harian
  4.  ambil request APPROVED di tanggal itu

  kalau tipe = FLEKSIBEL:
      catat scan sebagai penanda "masuk". JANGAN pernah set alpa. Selesai. (§3.3)

  kalau tipe = BAKU:
      hari libur (rule/exception)      -> status = libur
      ada request approved             -> status = cuti/izin/wfh
      nggak ada scan + nggak ada form  -> status = ALPA        <- satu-satunya yg butuh manusia
      scan_pertama > jam_masuk_akhir   -> telat X menit; wajib_pulang = jam_pulang + X
      lembur                           -> HANYA dari request approved. Scan nggak pernah bikin.
```

Ambang dedup & ambang dini hari itu **dua angka yang murni tebakan** — makanya disetel, bukan
di-hardcode.

### 5.4 Approval

```
Lapis 1 = DARI POHON group_path
  approver = users yang role-nya ancestor terdekat dari role pengaju
             DAN (raw_branch_id = cabang pengaju ATAU raw_branch_id IS NULL)
  branch NULL = lintas cabang. Supervisor Operasional hari ini 1 orang, branch NULL
  -> otomatis 5 cabang. Nanti per-cabang: tinggal isi cabang di user. NOL kode berubah.

Lapis 2 = DARI IZIN, BUKAN DARI POHON
  Finance/management SEBELAHAN sama operasional di bagan, bukan di atasnya.
  Maksa mereka jadi ancestor = checklist operasional bocor ke Finance (§6.1).
```

**Cuma cuti & lembur yang 2 lapis.** Izin, libur, WFH cukup atasan langsung.

Alasannya: mayoritas pengajuan itu hal kecil. Kalau finance approve semuanya, kita nggak
ngilangin kerjaan manual — cuma ganti chat WhatsApp jadi klik tombol. Kontrol finance dipindah
ke **tutup periode**: liat rekap + semua yang di-approve, spot-check, tolak borongan. **Per
periode, bukan per item.**

---

## 6. Dampak ke kerjaan Reza

Petty cash: **nggak kesentuh.**

### 6.1 Jebakan: pohon lagi kerja rangkap

`checklistVisibleDivisionIds()` (`app/Helpers.php:250`): *divisi yang gua lihat = divisi milik
semua role keturunan gua.* Artinya **posisi di pohon punya efek samping langsung ke checklist.**

```
SALAH:  Direktur -> Finance -> Supervisor Ops -> GS
        Finance mendadak bisa review checklist GS. Nol kode berubah.

BENAR:  Direktur -> Supervisor Ops -> GS/FO/Asdent
                 -> Finance, IT, Marketing   (sebelahan, bukan di atas)
        Role tanpa divisi = nggak dapet checklist. Gratis, aman.
```

Ini juga **alasan** approval lapis 2 nggak boleh dari pohon (§5.4).

### 6.2 Yang perlu dibereskan

| # | Item | Risiko |
|---|---|---|
| 1 | `ScopedToSubtree` belum tau cabang | Rendah — **nol call-site**, fitur ini konsumen pertamanya |
| 2 | Perluas role untuk office | Nol, selama nggak dilampirin divisi (§6.1) |
| 3 | `user_approval` vs pohon — **dua sumber kebenaran** "siapa atasan siapa". `user_approval` UI-nya hidup tapi **nggak pernah dibaca kode manapun** | Perlu keputusan: matiin atau jadiin override |
| 4 | `canSeeAllBranches()` hardcode nama role | Rendah |
| 5 | `relevel()` di luar transaction di `reparent()` | Rendah, pre-existing |
| 6 | **`group_path.parent_id` belum ada di prod** (§2.13) | Prasyarat Fase C |

### 6.3 Akun per orang — kena checklist juga

Checklist Reza itu **by staff/user**. Selama `Sales Bintaro` dipakai Apin + Firda berdua,
checklist mereka nggak bisa dipisah. Jadi pemecahan akun (§2.13) itu **prasyarat bersama** —
satu kerjaan, dua fitur kepake.

---

## 7. Fase

### Fase A — Fondasi & mapping

> **PROGRESS LOCAL (2026-07-17):**
> - ✅ Schema 3 tabel — `database/sql/2026_07_17_absensi_core.sql`, applied + smoke-test di local
>   (idempotensi hash, anti-bentrok (cabang,id), FK — semua kebukti insert beneran).
> - ✅ A1 akun per orang — 25 baru + 3 disambung (Ekky/Reza/Anisa) = 33 user local. Email
>   `<nama>@sweettreats.local`, pw `sweettreats2026`, role placeholder `Cabang`. Script scratch,
>   BUKAN file repo (prod punya user sendiri).
> - ✅ A2 seed `att_device_user` — 49 mapped (28 orang) + 17 dokter ignored + 49 unmapped.
>   Penggabungan lintas cabang terverifikasi (Anisa/Alif/Yovanka 5 mesin → 1 user_id).
>   Ela #58 (aktif, mau resign) & Lena #51 sengaja unmapped.
> - ✅ A3 importer CSV → `att_scan` — `app/Services/AttendanceCsvImporter.php` (3 format: log +
>   matriks_a + matriks_b), model `AttScan`/`AttDeviceUser`/`AttImportBatch`, command
>   `php artisan att:import {cabang} {file}`. Diuji E2E di local: 1.161 scan dari 5 cabang,
>   idempotensi kebukti (run 2x → 0 baru), gabung lintas cabang kebukti (Rohma 21/06: masuk
>   Sunter 08:35 → Kalideres 09:15-20:51 → balik Sunter 23:04, satu user_id).
> - ⬜ A4 UI upload web (controller + blade) — pipeline data udah jalan via CLI, UI ditunda.
> - ✅ AUDIT (2026-07-18): 1 bug fixed (`parseDate` — Carbon::createFromFormat MELEMPAR pada
>   format mismatch → data tanpa-detik crash; ganti ke DateTime native), 1 inkonsistensi fixed
>   (import dibungkus DB::transaction — anti batch yatim). Alignment matriks & dedup-menit
>   diverifikasi AMAN. Semua file lolos php -l.
>
> **FASE B — mulai (2026-07-18, local):**
> - ✅ Schema 4 tabel aturan — `database/sql/2026_07_18_absensi_work_rule.sql` (att_work_rule,
>   att_work_rule_day, att_work_rule_exception, att_user_rule), applied + FK verified.
> - ✅ Seed aturan — GS (baku 08:45-09:00/17:00/lembur18), FO-Asdent (09:50-10:00/18:00/lembur19),
>   Fleksibel. Senin libur. 28 user di-assign (5 GS + 16 FO-Asdent + 7 office). **Jam Minggu
>   PLACEHOLDER** (= hari lain, nunggu §8). Poin-4 GS<08:45 dihitung di engine.
> - ✅ Engine `AttendanceReconciler` → att_day (`app/Services/AttendanceReconciler.php`, model
>   `AttDay`, schema `2026_07_18_absensi_day.sql`, command `php artisan att:reconcile from to`).
>   Verified E2E di local — kasus known-true SEMUA benar: Rohma 21/06 lintas cabang jadi 1 baris
>   (08:35-23:04 hadir), dawn-pushback (scan 00:54 → hari kerja sebelumnya), Fajri telat Juli
>   (51m/69m cocok manual), 1-scan=hadir (85 baris, 0 jadi telat).
> - ⬜ Seed exception (Lebaran/akreditasi) — tabel siap, belum diisi.
>
> **FASE C — mulai (2026-07-18, local): engine-integration form SELESAI.**
> - ✅ Schema `2026_07_18_absensi_request.sql` (att_request 5 tipe + att_request_approval),
>   model AttRequest/AttRequestApproval.
> - ✅ Reconciler konsumsi form APPROVED: cuti/izin/wfh/libur → resolve alpa jadi jenis itu;
>   lembur → lembur_menit_disetujui; WFH office (fleksibel) → baris wfh. Terverifikasi E2E:
>   cuti/lembur/wfh berubah benar, hapus form → revert ke alpa, reconcile 2x idempoten (MD5 sama).
> - ✅ Data Juni LENGKAP 5 cabang (Bintaro Juni di-import dari file besar 5-thn) → reconcile Juni
>   PERTAMA yang valid: 545 hadir / 67 telat / 21 alpa / 19 libur. parseDate fix teruji di 26k baris.
> - ✅ **KEPUTUSAN: approval absen TERPISAH dari pohon checklist** (2026-07-18). Alasan: Asdent
>   punya 2 approver (Nisa+Irma) — group_path 1-role-1-induk tak muat; + §6.1 coupling checklist.
>   Tabel `att_approver` (2026_07_18_absensi_approver.sql) — mapping eksplisit pengaju→approver.
> - ✅ Hierarki L1 di-seed (33 relasi), user **Dani** (HR+COO) dibuat:
>     GS/FO → Nisa | Asdent → Nisa+Irma | Reza → Ekky | Alif/Talitha → Yovan | Nisa/Irma/Ekky/Yovan → Dani
> - ⬜ SISA Fase C: approval workflow state-machine + resolver (pakai att_approver, bukan pohon),
>   UI form+approval (HP), lembur pre-fill + verifikasi, kuota WFH 2x/bulan.
>
> **HIERARKI FINAL (dikonfirmasi 2026-07-18):** approval = RANTAI naik. staf → head (L1) → Dani (L2).
>   Della-PS & Hari → Dani. Dani → self (puncak). L2 = walk 1 hop up di att_approver (tak perlu
>   seed level=2 terpisah). **Finance = recap/view-only approved requests, BUKAN gate approval.**
>   Resolver terverifikasi: GS→Nisa→Dani, Asdent→Nisa+Irma→Dani, Reza→Ekky→Dani, Alif/Tali→Yovan→Dani.
>
> - ✅ **Workflow approval SELESAI** (`app/Services/AttendanceRequestWorkflow.php`): submit →
>   pending_l1 (head) → pending_l2 (Dani) → approved. Rantai dari att_approver (walk up, dedup,
>   buang self). Dani approve SEMUA jenis form (dikonfirmasi user). Terverifikasi 5 skenario E2E:
>   alur normal, tolak approve-out-of-turn, Asdent any-one (Nisa/Irma salah satu cukup), rantai
>   pendek head→Dani, reject, inbox approver, + engine konsumsi approved→att_day cuti.
>   Fix: AttRequestApproval $timestamps=false (append-only, sama pola AttScan).
>
> **=== SELURUH LOGIKA BACKEND ABSEN LENGKAP di LOCAL (2026-07-18) ===**
> import(3 format)→scan→reconcile(lintas cabang/dawn/telat/alpa)→form(5 jenis)→approval(2 lapis)→att_day.
>
> **FASE UI — mulai (2026-07-18, local):**
> - ✅ Halaman **Upload Finance** — `AbsensiUploadController` (guard canSeeAllBranches, resolve
>   cabang manual, validate mimes, move+import, redirect+flash), route `absensi/upload` +
>   `absensi/upload/file`, view `resources/views/absensi/upload.blade.php`, menu
>   `AbsensiAccessPathSeeder` (grant Finance+admin) + access:rebuild. Verified headless: route
>   register, blade compile, controller upload E2E (idempoten, guard 403 non-finance). Render
>   penuh + upload nyata perlu browser+login (layout butuh session).
> - ✅ Halaman **Akun Saya → Absensi Saya** (self-service, mobile) — `AkunAbsensiController`
>   (self-view via session id, rekap bulan, navigasi bulan), route `akun/absensi`, view
>   `absensi/akun/absensi.blade.php`, menu "Akun Saya" grant SEMUA role. Verified headless:
>   rekap Apajri Juni cocok (24 hadir/1 telat 110m), isolasi self-view (data user lain 0 bocor).
> - ⬜ Tombol "Ajukan" masih placeholder — form submission (cuti/izin/wfh/lembur) BERIKUTNYA.
> - ⬜ Polish mobile (sembunyiin sidebar admin, bottom-nav) — CSS re-skin, iterasi feedback browser.
>
> **PRINSIP UI (disepakati 2026-07-18):** app-role (group_path) = AKSES; posisi/approver (att_approver)
> = HR — ORTHOGONAL, jangan disamain (bukti: Nisa role "Admin Backup" tapi approver via att_approver).
> Struktur 4 permukaan: Alat Kerja (per role) | Akun Saya (sisi-GUA: absensi+form+checklist, mobile) |
> Persetujuan (sisi-TIM approver, mobile) | Absensi Admin (finance, desktop). "Sisi-GUA vs sisi-TIM"
> = prinsip penempatan. Rename role "Admin Backup"→"OPS Supervisor" DITUNDA (hardcoded ~30 tempat
> di Helpers+controller cek string nama role — §6.2 #4; harus fix semua dulu).
> - ⬜ Berikutnya: form pengajuan, inbox Persetujuan, finance recap, pindah "Checklist Saya" ke grup Akun Saya (koord Reza).
>
> **SISA:** UI form/inbox/recap, role Finance (group_path — FinanceRoleSeeder ADA, blm run local),
> role Finance (group_path akses menu), seed exception Lebaran/akreditasi, lembur prefill+verifikasi,
> kuota WFH 2x/bulan, plafon telat (default 180 — management), jam Minggu (management), DEPLOY
> dev/prod (semua msh local, nol commit). Default any-one utk Asdent 2-approver (user konfirmasi).
>
> **KEPUTUSAN KEBIJAKAN PENDING (§8):**
> - Plafon telat (`AttendanceReconciler::$telatCeilingMinutes`, default 180): scan pertama > window
>   + plafon → dianggap hadir bukan telat (shift sore / lupa tap pagi). Sebaran: 53 baris <=30m,
>   7 (31-60), 4 (61-120), 3 (121-180 = masuk jam 12:xx, borderline). Management set angkanya.
>
> **CATATAN DATA (penting):** aggregate att_day BELUM bisa dipercaya — data local baru Bintaro
> JULI + 4 cabang JUNI, jadi tiap bulan ada cabang yang bolong → alpa & telat palsu (mis. Rizka
> "telat 12:11" 18/06 itu dari Kalideres krn scan pagi Bintaro-nya nggak ke-load). Butuh 1 bulan
> penuh 5 cabang buat rekonsiliasi jujur. Kasus known-true (Rohma/Fajri) tetap valid.
> Semua di LOCAL. Belum ke dev/prod. File baru belum di-commit.

**A0 — Mapping karyawan: SELESAI (2026-07-17).** Hasilnya = §3.5.

19 operasional + 8 office ternama lengkap, plus 8 kasus lintas cabang dikonfirmasi. Dokter →
`ignored`. Yang tersisa cuma 4 nama (§8 #8) — nggak nge-block, tinggal dilengkapi saat seeding.

Lembar kerja mentahnya masih ada di `Downloads/mapping-absensi/` (generator: scratchpad
`worksheet3.php`) kalau perlu dibangkitkan ulang buat karyawan baru. **Tapi jangan disebar —
isinya udah kejawab.**

**Ini bagian yang nggak bisa disimpulin dari data, makanya dia duluan.** §10 mbuktiin: tiap kali
mapping salah, angka alpa meledak — bukan karena orangnya bolos, tapi karena mapping-nya jelek.
§3.5 sekarang jadi **sumber kebenaran** buat seeding `att_device_user` + bikin akun (A1).

**A1 — Akun per orang.** Bikin ~35 akun baru, pensiunin akun peran (`status=0`, jangan hapus).
Prasyarat checklist juga (§6.3).

**A2 — Kode (mulai sekarang, pakai Bintaro — TIDAK nunggu 4 cabang):**
- Tabel `att_device_user`, `att_scan`, `att_import_batch`
- `AttendanceCsvImporter` — idempotent by hash. **Tulis parser log-mentah Bintaro dulu.**
  Parser matriks (`Lap. Detail Absensi` + `Catatan Kehadiran`) itu **adapter kedua** yang colok
  belakangan tanpa bongkar apa-apa — atau nggak usah ditulis kalau §8 #1 ternyata "bisa log
  mentah". Wajib `fgetcsv` (Pamulang punya newline dalam sel).

> **Format 4 cabang di-skip dari jalur kritis (2026-07-17).** Alasannya isolasi total: format
> cuma soal cara data MASUK. Begitu di `att_scan`, semua scan keliatan sama — engine, aturan,
> form, approval nggak peduli asalnya log atau matriks. Jadi nunda ini **nggak numpuk utang di
> tempat lain**, dan ada peluang kerjaannya HILANG (bukan tertunda) kalau cabang bisa export
> log mentah. Adapter, persis kayak ADMS (§Fase E).

> **Parser matriks TERVERIFIKASI parseable (2026-07-17).** Diuji ke `sunter 1-20.csv` (cabang
> paling brutal) — nahan semua kasus: sel 3-4 jam didempet tanpa pemisah (`01:4009:4919:03`),
> jam kembar dalam sel (`09:1609:1718:34` → dedup 09:16), scan lewat tengah malam (`00:56` →
> pulang hari kemarin). Regex `\d{2}:\d{2}` + dedup menit + lempar-balik dini hari cukup. Detik
> hilang di format matriks — nggak ngaruh (engine cuma butuh first/last). Script:
> scratchpad `verifikasi_sunter.php`.
- Halaman upload — **cabang dipilih manual** (§2.4)
- Halaman mapping No.ID → user:
  - kunci `(raw_branch_id, device_user_id)`, many-to-one
  - status `ignored` buat dokter
  - **tandai nol-scan biar bisa dilewatin borongan** (Kalideres 32 terdaftar, 8 aktif)
  - **peringatkan nama kembar lintas cabang** — tapi ingat: petunjuk ini **nggak bisa dipercaya
    dua arah** (§10.3). Manusia yang mutusin.

**Nilai:** data 5 cabang di satu tempat, ter-mapping. Finance berhenti buka Excel.

### Fase B — Aturan kerja & engine

- `att_work_rule` + `att_work_rule_day` + **`att_work_rule_exception`** + `att_user_rule`
- Deklarasi awal: **Lebaran per tahun** (5 cabang), **akreditasi Sunter Mar–Jun 2026**
- `AttendanceReconciler` → `att_day` (§5.3), termasuk gabung-lintas-cabang & lempar-balik
  dini hari
- Halaman rekap per cabang per periode

**Nilai:** telat & alpa otomatis buat 19 operasional.

> Uji ulang engine ke **Sunter** (cross-midnight) dan **Rohma** (lintas cabang) sebelum Fase B
> dianggap selesai. Bintaro itu cabang paling gampang, bukan paling mewakili.

### Fase C — Form & approval

- `att_request` 5 tipe + approval 2 lapis (§5.4)
- Resolver approver dari pohon + cabang
- UI ramah HP, **nempel di halaman checklist harian Reza** (kebiasaan hariannya udah ada)
- **Klaim lembur pre-filled dari scan** — tombolnya pintu masuk ke form yang udah keisi, **form
  tetap WAJIB diisi**. Alasan tetap diketik: supervisor butuh itu, dan hal khusus nggak akan
  ketebak sistem.
- Sistem nampilin hasil sandingan sebagai **informasi**, bukan vonis (§4)
- Kuota WFH 2×/bulan dihitung dari form approved

**Nilai:** Google Form mati. Klaim dan scan akhirnya ketemu di satu tempat.

### Fase D — Periode & kontrol finance

- `att_period` + close/lock
- Laporan: % single-scan per orang (§9), selisih klaim-vs-scan per orang
- Export buat payroll

**Nilai:** **cut-off balik ke tanggal 25.**

### Fase E — ADMS (tujuan #1) — NANTI

P207 **nggak punya jalur jaringan** — spek resmi: *"Jenis Komunikasi PC: USB Cable, USB Disk"*.
Auto-pull mustahil; portnya nggak ada.

**Tapi Solution punya ADMS** (https://www.solution.co.id/adms.php):

> *"koneksi internet di kantor2x cabang dengan koneksi apa saja tanpa perlu IP Public sama
> sekali"* — *"Tidak Perlu lagi adanya Installasi Software apapun di PC kantor cabang"*

Mesin **nelpon keluar**. Cabang cukup internet biasa; server pusat perlu IP publik —
**DataCenter udah punya**. **Butuh ganti mesin** (P207 nggak support). Model: X302S (TCP/IP,
10.000 sidik jari, 200.000 log) atau X601. Harga belum dicek.

| | ADMS | Absen HP |
|---|---|---|
| Fake GPS | tidak berlaku | **nggak bisa dideteksi** dari web app — `navigator.geolocation` nggak punya flag mock |
| Titip absen | tidak bisa (sidik jari) | **bisa** — password dirancang buat bisa dipindah |
| Edit file (§9.2) | nggak ada file | nggak ada file |
| Upload manual | hilang | hilang |
| Biaya | ganti 5 mesin | nol |

Absen HP nyelesain transport data dengan **ngorbanin buktinya** — dan bukti fisik itu
satu-satunya hal yang P207 lakuin dengan bener.

**Dampak ke kode: nyaris nol.** `att_scan.source` udah netral sejak Fase A. Fase A–D tetap
perlu: jembatan transisi, cadangan kalau internet cabang mati, dan ADMS butuh keputusan belanja
di luar kendali kita.

> **Jangan beli mesin sebelum Fase A–D jalan.** Begitu Fase B jalan, ada data bersih 5 cabang —
> bahan negosiasi anggaran yang jauh lebih kuat daripada proposal.

---

## 8. Keputusan yang masih nunggu

| # | Pertanyaan | Nge-block | Untuk |
|---|---|---|---|
| 1 | **Cabang lain bisa export log mentah kayak Bintaro?** (§2.2) — cek menu laporan software cabang: ada opsi log/detail per-scan (1 baris = 1 scan) selain `Lap. Detail Absensi`? Kalau BISA → parser matriks nggak usah ditulis sama sekali. | **Tidak** — Fase A jalan pakai Bintaro dulu (§7 A2). Parser matriks itu adapter yang colok belakangan | Nentuin parser matriks ditulis atau nggak |
| 2 | **SOP poin 4 vs §3.2:** *"GS di bawah 08.45 terhitung lembur"* bentrok dengan *"dateng awal nggak dihitung lembur"*. Fajri dateng sebelum 08:45 di **11 dari 13 hari**, rata-rata 76 menit → 14 jam/setengah bulan. Kali 5 GS = angka beneran. | Tidak | Fase B rilis |
| 3 | Tarif lembur hari libur beda dari lembur jam? (§3.2.5) | Tidak | Fase C |
| 4 | `OP1`/`OP2` di mesin Kalideres itu apa? | Tidak | Fase A |
| 5 | Foto mesin 5 cabang — semua P207 atau campur? | Tidak | Fase E |
| 6 | `user_approval` — matiin atau jadiin override? (§6.2 #3) | Tidak | Fase C |
| 7 | Anggaran ganti 5 mesin ke ADMS | Tidak | Fase E |
| 8 | 2 nama sisa: **`Ela`** #58 Bintaro (19 hari — aktif, paling penting, siapa?), `Lena` #51 (3 hari). `Jambang` #23 → kemungkinan resign, simpan dulu. | Tidak | seeding §3.5 |

**Terpetakan (2026-07-17):** `hari~` #59 = **Hari**, konsultan marketing, **udah punya akun prod**
(role Executive, hari@stdc.id) → seeding cukup petakan `device #59 → user Hari`, nggak bikin
akun baru. 4 nama nol-scan lainnya (`Ipay`/`ojah`/`Adi`/`Ryan`/`Azra` dst) → kemungkinan resign,
simpan dulu, tandai saat mapping.

### Terjawab

| Pertanyaan | Jawaban |
|---|---|
| No.ID bentrok antar cabang? | **YA, 26.** Kunci `(cabang, No.ID)` dikonfirmasi |
| Format 5 mesin sama? | **TIDAK — 3 format** (§2.2) |
| `Departemen` = cabang? | **TIDAK.** Pilih manual saat upload |
| Juni normal? | **YA buat 4 cabang.** Sunter 2.4× (akreditasi). Nggak perlu export bulan lain |
| Feb–Mar turun kenapa? | **Ramadan + Lebaran** — terbukti dari geseran 11 hari/tahun selama 5 tahun (§2.10) |
| Plafon lembur? | **Gugur** — §7.1 v1 salah, lemburnya nggak ketahan (§10.1) |
| Rename akun sales? | **Jangan.** Bikin baru, pensiunin yang lama (§2.13) |

---

## 9. Risiko

### 9.1 Approval jadi stempel

Kalau supervisor cuma klik approve tanpa liat sandingan scan, **nggak ada yang berubah** —
Asih tetap 2×, Fajri tetap kurang klaim. Seluruh nilai fitur ini gantung ke satu hal: **apakah
approver beneran baca angkanya.** Itu desain UI + kebiasaan, bukan database.

### 9.2 File CSV bisa diedit sebelum upload

Konsekuensi SOP "tiap cabang upload sendiri". CSV itu file teks. Konflik kepentingannya nggak
bisa diilangin: di cabang isi 3 orang, yang upload ya orang yang absennya ada di file itu.

**Yang nolongin:** mesin nyimpen seluruh riwayat, jadi rentang mana pun bisa di-export ulang.
Baris palsu itu **kebohongan sekali pakai**.

**SOP: rentang export = 2 BULAN TERAKHIR.** Upload 2 mingguan udah kasih tumpang-tindih di
dalam bulan; rentang 2 bulan nutup lubang **upload terakhir tiap bulan** yang nggak pernah
diverifikasi.

**Mitigasi — laporan selisih:** tiap import, bandingin rentang tumpang-tindih. Ada di
`att_scan` tapi nggak ada di file terbaru → **nggak pernah keluar dari mesin** → FLAG.

> Batas: kalau mesin mentok 50.000 log dan mulai nimpa, sifat ini ilang. Bintaro 26.778 (53%),
> +~5.500/tahun → aman ~4 tahun. Pantau.

**Alur upload: PIC cabang export → kirim ke finance → finance upload (2026-07-17).** User
**tidak percaya PIC**. Tapi lewat finance = checkpoint BUTA: file CSV teks biasa, PIC bisa edit
sebelum kirim, finance nggak bisa bedain file bersih dari yang diutak-atik cuma dari ngeliat.

Yang beneran nutup: **mesin jadi saksi**. Baris palsu PIC nggak pernah ada di mesin, jadi export
ulang mana pun bakal ngungkapnya. Tingkatan proteksi:

```
1. lewat finance doang            -> checkpoint buta, TIDAK nutup edit PIC
2. + export overlap (sebulan penuh tiap tgl 30, bukan 21-30)  -> mesin jadi saksi otomatis
3. + spot-check dadakan finance export langsung dari mesin     -> PIC nggak tau kapan dicek
```

**Keputusan (2026-07-17): pakai level 2 + 3.** Rentang export overlap 2–3 bulan (ganti setelan,
ongkos nol), plus finance export dadakan sesekali. Data mentah semua kesimpan → keputusan ini
**nggak permanen**: kalau ada PIC ketauan main, minta export ulang periode itu, sistem langsung
nunjukin selisihnya. Nggak ada jendela yang ketutup selamanya.

**ADMS (§Fase E) ngilangin risiko ini sepenuhnya** — nggak ada file, nggak ada tangan manusia.

### 9.3 Tap pulang jadi nggak ada gunanya

Konsekuensi §3.2: mau tap atau nggak, hasilnya sama. Pelan-pelan orang berhenti tap pulang —
Talitha udah gitu. Begitu merata, kemampuan ngedeteksi pulang-cepat hilang permanen.

**Dibiarkan** — nol kasus pulang-cepat di hari kerja biasa (yang ada semua nyangkut Minggu, dan
itu emang jam bakunya lebih pendek). **Penangkal murah:** catat % hari single-scan per orang,
tampilin ke supervisor. Gratis, datanya udah ada.

---

## 10. Kesalahan analisis v1 — sengaja dicatat

Ditulis biar yang baca dokumen ini nggak ngulang. **Enam kesalahan, dan empat di antaranya satu
sebab.**

### 10.1 "Biaya lembur bakal meledak begitu tombolnya digampangin" — SALAH TOTAL

v1 nulis: *"yang nahan biaya lembur bukan kebijakan, tapi ribetnya ngisi Google Form"*, dan
nyebutnya risiko terbesar proyek.

**Data: 5.238 klaim / 21 bulan / 249 per bulan, stabil, 100% ada foto.** Nggak ada yang males.
Kesimpulan itu dibangun dari asumsi, bukan data.

**Yang bener** (§2.11): klaim dan scan **nggak nyambung, ke dua arah**. Itu masalahnya, dan itu
nilainya.

### 10.2 Sistem sebagai hakim — SALAH

v1 mendesain vonis `COCOK`/`NGGAK COCOK`. Ikhsan mbuktiin salah: WFH → nggak ada scan → dicap
"nggak cocok" padahal beneran kerja. Sistem ngambil kesimpulan yang bukan haknya. → §4.

### 10.3 Empat kesalahan identitas — SEMUA karena percaya nama

| # | Kesalahan | Kenyataan |
|---|---|---|
| 1 | `ROHMA` (Kalideres) vs `ROHMA~~` (Sunter) = **dua orang** | **Satu orang** — Rokhmatun, lintas cabang (§2.7) |
| 2 | `gita`/`anggita`, `putri`/`puttri` = orang beda | **Satu orang** — lolos dari pencocokan |
| 3 | `Nisa` vs `Anisa` = orang beda → **"Nisa 23 hari alpa"** | **Satu orang** — Anisa Aulia, 26 hari scan, salah satu yang paling rajin. Alpa 23 → 1 |
| 4 | `yovan` vs `yovanka` = orang beda | **Satu orang** — Yovanka Rima Millennia, 26 hari, 4 cabang |

Dan **nyaris kebalikannya**: `DeLa`/`Della` itu **beneran dua orang**, kepisah cuma karena
kebetulan beda ejaan (§2.5).

**Kesimpulan yang mahal:** kolom "nama juga ada di cabang lain" itu **nggak bisa dipercaya dua
arah** — bisa kelewat, bisa salah gabung. Itu petunjuk buat mancing pertanyaan, **bukan
jawaban**. Dan: **angka alpa itu bukan ngukur orang bolos — dia ngukur kualitas mapping.**

### 10.4 "Sunter nggak pernah tutup" — SALAH DUA KALI

Ronde 1: ngukur *ada scan apa nggak*, bukan *berapa orang*. Satu orang tap Senin = "cabang
buka". Yang bener: kru tipis (3.4 vs 6.8).

Ronde 2: bahkan "kru tipis tiap Senin" pun salah — itu **akreditasi**, event Mar–Jun, bukan
sifat cabang (§2.12). Kalau nggak dikoreksi, Sunter dapet jam baku sendiri **selamanya** gara-gara
satu event, dan tahun depan nggak ada yang inget kenapa.

### 10.5 Pola induknya

**Datanya nggak pernah salah. Sekali pun.** Rohma emang scan di dua cabang. Sunter emang ada
scan tiap Senin. Asih emang ngeklaim 2×. Ikhsan emang nggak scan pas ngeklaim. Semua fakta itu
bener, dan **cuma ketahuan dari data**.

Yang salah itu **lompatan dari "datanya nunjukin X" ke "berarti kebijakannya Y"**.

Dan sebaliknya: **nggak ada satu pun temuan ini yang muncul dari nanya orang.** Nggak ada yang
bakal cerita "Rohma kadang tap di dua cabang" atau "Asih ngeklaim dua kali lipat".

> **Mesin itu pengamat yang lebih teliti. Manusia itu penafsir yang lebih bener.**
> Kesalahan v1 tiap kali sama: ngerangkap dua-duanya.

Ini bukan renungan — ini **spesifikasi**. Itu persis kenapa §4 naruh deklarasi manusia di atas
scan, kenapa `att_work_rule_exception` ada, dan kenapa mapping (A0) jalan sebelum kode.

**Contoh tandingannya — Lebaran (§2.10):** tebakan datang dari manusia ("kayaknya Lebaran"),
pembuktian datang dari mesin (geseran 11 hari selama 5 tahun — nggak akan keliatan sama manusia
yang ngeliat satu bulan). Nggak satu pun bisa nyampe sendirian. **Itu bentuk yang sistem ini
tiru.**

---

## 11. Yang sengaja TIDAK dibangun

| Nggak dibangun | Kenapa |
|---|---|
| Tabel `employee` | `users` + `sys_user_position` udah history-aware |
| Form koreksi absen | Ditarik — "1 scan = hadir" ngilangin kasusnya. Sisanya (nggak ada scan) udah dilayani cuti/izin/WFH |
| Deteksi pulang cepat | §9.3 — nol kasus. Cukup dicatat polanya |
| Riwayat efektif-tanggal di mapping | §2.3 — nol daur ulang dalam 5 tahun (dalam satu mesin) |
| Penggajian / payroll | Di luar lingkup. Finance mau **datanya jelas** dulu |
| Absensi dokter | `ignored` — scan tetap disimpan buat nanti |
| Penilaian scan buat office | §3.3 — nggak ngasih makan apa pun, dan bikin 36 alpa palsu/bulan |
| Sistem yang mutusin cocok/nggak | §4 — bukan wewenangnya |
| Auto-pull dari P207 | Nggak punya port jaringan. Jalannya ganti mesin + ADMS |
| Absen HP / geofence | Lebih lemah dari yang ada sekarang (§Fase E) |
| Rename akun peran | §2.13 — nulis ulang sejarah dengan tebakan |
| Engine yang "pinter" nyesuain diri | §10.4 — itu ngelembagain kesalahan v1 jadi kode, tiap bulan, tanpa ada yang push back |
