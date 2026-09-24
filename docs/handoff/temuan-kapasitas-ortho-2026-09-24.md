# Kapasitas Ortho — temuan data, kasus Kalideres

> **Dokumen INTERNAL IT.** Untuk rapat tim IT dan sebagai bahan modul ORTHO
> (IT-2026-013). Disusun HUB 24 Sep 2026, **direvisi malam itu juga** sesudah
> uji tekan oleh ChatGPT & Gemini dan sesudah dua koreksi dari Ikhsan.
>
> ⛔ **Ini TEMUAN, bukan rekomendasi final.** Angka terukur dari Omnicare
> produksi, tapi data tidak tahu keadaan lapangan. Menunggu tinjauan
> **Nisa (SPV Operasional)** dan **kunjungan Kalideres**.
>
> ⛔ **Jangan disalin apa adanya ke versi Executive.** §8 dan §9 khusus internal.

---

## 1. Kasus pemicu: Kalideres

| | Rp/hari | pasien/hari |
|---|---|---|
| Hari **ada drg. Andy** (37 hari) | **23,9 jt** | **21,4** |
| Hari **tanpa Andy** (160 hari) | 4,3 jt | 5,6 |

**Selisih 5,6×.** Rabu — satu-satunya hari praktik Andy — menyumbang **56% omzet
Kalideres setahun**. Jadwalnya: **Kalideres, Rabu, 15:00–22:00**, satu hari seminggu.

Konsentrasi omzet dua dokter teratas: **Kalideres 75,2%** · Pamulang 50,9% ·
Bintaro 45,1% · Cinere 44,5% · Sunter 43,0%.

drg. Andy sendiri **46,6% omzet cabang dari 37 hari praktik**; drg. Stiefani Vega
28,6% dari 106 hari.

### Ketimpangan harian

| Cabang | Hari terbesar | Hari tersepi | Timpang |
|---|---|---|---|
| **Kalideres** | Rabu **25,6 jt** | Kamis 2,5 jt | **10,1×** |
| Bintaro | Minggu 27,9 jt | Jumat 4,9 jt | 5,7× |
| Pamulang | Minggu 12,5 jt | Rabu 2,9 jt | 4,2× |
| Sunter | Sabtu 25,8 jt | Kamis 6,5 jt | 4,0× |
| Cinere | Jumat 11,9 jt | Selasa 4,3 jt | 2,8× |

**Kalideres bukan anomali — ia yang paling telanjang.** Polanya ada di lima cabang.

---

## 2. Beban per dokter — "buku besar" bukan ukurannya

| Dokter | Buku 2026 | Hari/bln | Kontrol/hari | Cabang |
|---|---|---|---|---|
| **drg. Andy Sp.Ort** | 149 | **3,1** | **17,6** | Kalideres |
| drg. Muhammad Deafridhininetri (Fridhi) | 123 | 4,1 | 11,5 | Sunter |
| drg. Githa Ayu Sp.ort | 50 | 1,9 | 7,8 | Bintaro |
| drg. Nugroho Sp. Ort | 203 | 11,3 | 6,9 | Bintaro, Cinere |
| DR. drg. Komalawaty Sp. Ort | 195 | 10,9 | 6,6 | 3 cabang |
| Drg. Ditaprilia Sp.ort | 48 | 2,8 | 6,6 | Pamulang |
| drg. Yasnelly Sp.orto | 114 | 6,8 | 6,4 | Bintaro, Sunter |
| drg. Anindito (Dito) | 185 | 10,8 | 5,6 | Bintaro, Cinere |
| **drg. Rachmad (Ryan)** | **240** | **15,4** | **5,2** | Bintaro, Sunter |
| drg. Stiefani Vega | 100 | 11,3 | 4,1 | Kalideres |

**Ryan memegang buku terbesar (240) tapi kepadatan paling ringan** — praktik 15,4
hari/bulan. Andy bukunya separuh Ryan, kepadatannya **3,4×**.

> **Yang menentukan bukan berapa banyak pasien, tapi berapa sedikit harinya.**

Variabel yang bisa digeser manajemen adalah **hari**, bukan pasien.

⚠️ **Fridhi di Sunter adalah pola yang sama, belum separah** — buku 123, 4,1
hari/bulan, dan Sunter sudah timpang 4,0×.

### Tabel ini peta RISIKO, bukan peringkat prestasi

17,6/hari bisa dibaca *"paling produktif"* atau *"paling terancam"*. Dokumen ini
membacanya sebagai **risiko**. Siapa pun yang memakai ulang tabel ini wajib
membawa bingkai yang sama — lihat §9 soal kenapa.

---

## 3. ⭐ Durasi slot: angka yang menjelaskan mekanismenya

Ini bagian terpenting dokumen, dan yang paling baru.

Sistem kita menyimpan durasi per **dokter × cabang × layanan**. Untuk
**Kontrol Behel**:

| Dokter | Cabang | Slot | Terakhir disetel |
|---|---|---|---|
| **drg. Andy** | **Kalideres** | **10 mnt** | 21 Mei (backfill) |
| Komalawaty | Cinere/Sunter/Bintaro | 15 | Jun–Sep |
| Dito | Bintaro | 15 | 21 Agu |
| Nugroho · Yasnelly · Githa · Yusmaini · Haikal · Fridhi · Elvia | — | 20 | Jul–Sep |
| **drg. Stiefani** | **Kalideres** | **30** | 7 Agu |
| *(dokter tanpa penyetelan)* | — | *30 (default)* | — |

**Di cabang yang sama, untuk layanan yang sama: Andy 10 menit, Stiefani 30 menit.**

Andy juga punya slot terpendek untuk **Pemasangan Behel: 10 menit** (default 60).

### Dan kecepatan sebenarnya lebih lambat dari slotnya

Diukur dari `checkout_time` (stempel waktu pasien membayar), 29 hari praktik Andy
di 2026:

| | |
|---|---|
| Checkout pertama | biasanya **16:15–17:30** |
| Checkout terakhir | biasanya **20:43–22:36** |
| Rentang checkout | median **301 menit (5 jam)** |
| Jarak antar pasien | **~13,5 menit** |
| **Hari yang selesai lewat jam tutup 20:00** | **27 dari 29 — 93%** |
| Selesai ≥22:00 | 7 hari |
| Rekor | **15 Juli — 28 pasien, checkout terakhir 00:08** |

> **Slot 10 menit, kecepatan nyata 13,5 menit.** Tiap pasien molor ~3,5 menit;
> dengan 22 pasien itu **±77 menit yang menumpuk** sepanjang sore.

Inilah mekanisme yang mengubah "hari padat" menjadi "hari yang selesai lewat
tengah malam". Bukan tafsir — aritmetika dari dua angka yang dua-duanya terukur.

⚠️ **Satu baris data rusak:** 1 April 2026 mencatat checkout terakhir lebih awal
dari yang pertama. Jelas salah catat, satu dari 29, sudah dikeluarkan dari median.

### ⚠️ Yang TIDAK boleh disimpulkan dari angka ini

`checkout_time` adalah **waktu membayar**, bukan lama perawatan. Kalau ada dua
kursi jalan paralel atau asisten mengerjakan sebagian, jarak checkout bisa lebih
rapat dari waktu di kursi.

Jadi 13,5 menit **bukan** bukti Andy menangani pasien 13,5 menit. Yang terbukti:
**pasien keluar tiap ~13,5 menit, dan harinya berakhir lewat jam tutup di 93%
hari praktik.**

### ⚠️ Dari mana angka 10 menit itu — dan apa yang BUKAN artinya

Tiga baris durasi Andy dibuat **21 Mei 2026 oleh `backfill`**. **Itu BUKAN berarti
tak ada yang memutuskan.** `backfill` adalah proses yang memasukkan data yang
**sudah ada sebelum sistem ini dibangun** — angkanya datang dari FO/operasional.

Jadi 10 menit itu **praktik lapangan yang terekam apa adanya**, bukan kelalaian
konfigurasi. Yang bisa dikatakan: **angka itu belum pernah ditinjau ulang lewat
layar sejak sistem hidup**, sementara rekan-rekannya sudah (Jun–Sep 2026).

Apakah 10 menit memadai secara klinis — **itu pertanyaan lapangan, bukan
kesimpulan IT.** Jangan diubah sebelum dijawab.

---

## 4. Jarak kontrol: di mana biayanya jatuh

| | rata-rata | median | >60 hari |
|---|---|---|---|
| Se-jaringan | 54,3 hari | 42 | 25% |
| **Kalideres** | **48,7 hari** | **35** | **22%** |

**Kalideres lebih rapat dari rata-rata jaringan.** Median 35 hari — **di dalam
norma** (lihat di bawah).

Jadi rumusan yang benar **bukan** *"biayanya tidak jatuh ke pasien"* (terlalu
memihak), melainkan:

> **Kalideres tidak lebih buruk dari jaringan, dan jaringan sendiri sudah di luar
> normanya.** Yang khas di Kalideres: jadwal terkompresi pada satu orang, satu
> hari, dan berakhir lewat jam tutup di 93% hari praktik.

### Norma jarak kontrol — dari praktik kita sendiri, bukan standar luar

| Jarak | Porsi |
|---|---|
| ≤14 hari | 0,6% |
| 15–21 | 4,7% |
| **22–28** | **21,7%** ← modus |
| **29–35** | **20,4%** |
| 36–42 | 12,4% |
| 43–56 | 14,3% |
| 57–70 | 10,2% |
| 71–90 | 6,1% |
| **>90** | **9,6%** |

**42% selang jatuh di 22–35 hari.** Rumusan yang dipakai:

> **Norma kita sendiri 22–35 hari, tapi 30% selang lebih dari 56 hari.**

Tidak bisa dibantah dengan *"standarmu dari mana?"* — standarnya dari data kita.

---

## 5. Stok kewajiban yang bertumbuh

Ortho se-jaringan, **21 bulan (Jan 2025 – Sep 2026)**:

| | |
|---|---|
| Pemasangan | **966** |
| Lepas bracket **dalam jendela ini** | **130** |
| **Tumbuh bersih** | **+836** |

Laju naik: 2025 ±33/bulan → 2026 **±49/bulan**. Kontrol se-jaringan
**459/bulan (Jan-25) → 794 (Agu-26), +73%**.

### ⚠️ Definisi tiga angka yang mirip tapi berbeda — supaya tidak dibantah

| Angka | Artinya |
|---|---|
| **261** | seluruh baris `Lepas Bracket` **sepanjang masa** |
| **130** | lepas bracket **dalam jendela 21 bulan** yang dipakai di atas |
| **56** | lepas bracket yang **tanggal pemasangannya bisa dilacak** (sisanya dipasang sebelum data kita mulai) |

Ketiganya benar untuk pertanyaan berbeda. **Jangan sandingkan tanpa label.**

### Rasio selesai — kohor, bukan potongan melintang

⛔ Angka **"5,7% selesai"** di draf pertama **SALAH cara hitungnya**: membagi
seluruh pemasangan dengan seluruh yang selesai, padahal yang baru pasang bulan
lalu memang belum waktunya selesai.

Dihitung ulang sebagai kohor — hanya yang pemasangannya sudah cukup tua:

| Kohor | Jumlah | Selesai | Rasio |
|---|---|---|---|
| Pasang **≥18 bln** lalu | 70 | 5 | **7,1%** |
| ≥15 bln | 156 | 11 | **7,1%** |
| ≥12 bln | 281 | 18 | **6,4%** |

**Pakai 7,1%, buang 5,7%.** Angkanya nyaris sama, tapi sekarang bisa dipertahankan.

ℹ️ Kohor ≥21 bulan kosong: data pemasangan kita mulai Jan 2025, jadi maksimal 20
bulan — sedikit di bawah durasi perawatan (~21 bulan).

### ⚠️ Berapa dari +836 yang benar-benar aktif?

Dari **789** pasien yang dipasang 2025–26 dan belum lepas bracket:

| Kontrol terakhir | Jumlah | |
|---|---|---|
| dalam 60 hari | 457 | 58% |
| **dalam 90 hari** | **533** | **68%** |
| dalam 180 hari | 595 | 75% |

**32% (±250 orang) belum kontrol 90 hari ke atas.** Jadi kewajiban aktif yang
nyata **±533, bukan 836**.

Itu mengoreksi angkanya **tanpa melemahkan tesisnya** — dan 250 yang dorman itu
**persis yang modul ORTHO (IT-013) dirancang untuk menemukan**: entah dropout,
entah "jarang kontrol" yang hilang dari pandangan. Sekarang kita tahu ordenya.

### Rumusan kewajiban yang benar

⛔ Draf pertama menulis *"±24 kewajiban kontrol bulanan"* — itu mengandaikan
kontrol sebulan sekali. Dengan median nyata 42 hari, perawatan 24 bulan
menghasilkan **±17 kontrol**, bukan 24.

> Tiap pemasangan hari ini adalah **±17 kewajiban kontrol** selama ±2 tahun.

⚠️ **Ini BUKAN spiral.** Lama perawatan yang selesai stabil ~21 bulan; jarak
kontrol melar sepanjang 2024 lalu **mendatar sejak 2025**. Bawa sebagai
**ekuilibrium yang dibayar dengan kompresi jadwal**, bukan lingkaran setan.

---

## 6. Ortho = separuh bisnis, seragam di lima cabang

**Ukuran (1) — PERAWATAN** (jenis tindakan yang ditagih):

| Cabang | Ortho | Non-ortho |
|---|---|---|
| Bintaro | 49,2% | 50,8% |
| Cinere | 48,1% | 51,9% |
| Kalideres | 47,8% | 52,2% |
| Sunter | 47,5% | 52,5% |
| Pamulang | 47,4% | 52,6% |
| **Jaringan** | **48,1%** | 51,9% |

Selisih antar cabang hanya **1,8 poin** → **ini model bisnisnya**, bukan kekhasan cabang.

**Ukuran (2) — DOKTER** (bergelar Sp.Ort): **49,1%** omzet total.

⚠️ **Dua ukuran ini angkanya mirip tapi artinya beda total.** Satu soal *tindakan
apa*, satu soal *siapa yang mengerjakan*. Jangan dicampur.

### Ortho tidak eksklusif milik yang bergelar

**56% kontrol ortho dikerjakan dokter TANPA gelar Sp.Ort:** Ryan **717**
(terbanyak se-jaringan) · Dito 539 · Fridhi 424 · Stiefani 422 — masing-masing
**20–25% pekerjaannya**.

### ⚠️ Tapi volume ≠ nilai

| | Kontrol | Omzet | Per kontrol |
|---|---|---|---|
| Bergelar Sp.Ort | 44% | **50%** | **Rp 449.210** |
| Tanpa gelar | 56% | **50%** | Rp 351.755 |

Kontrol oleh yang bergelar bernilai **28% lebih tinggi**. Entah kasusnya lebih
berat atau tarifnya beda — **data tidak bisa membedakannya.**

> Karena itu **"tinggal dipindah ke Ryan" BUKAN kesimpulan dokumen ini.**
> Memindahkan pasien antar dokter bukan operasi netral.

Dan jangan dibalik juga: 56% **tidak** membuktikan spesialis tak diperlukan.

---

## 7. ⭐ AGENDA UTAMA IT

Tiga temuan di atas tidak bisa diselesaikan IT — tapi **ketidakmampuan melihatnya
adalah murni masalah kita.**

### 7.0 ⛔ PRASYARAT: IT-2026-042 jadwal dokter dibereskan DULU

Hitungan kapasitas berdiri di atas `raw_doctor_schedule`, dan tabel itu **sedang
diketahui berantakan**: baris `22:00–20:00`, baris kembar, satu dokter di lima
cabang pada jam sama, dokter nonaktif masih berjadwal. Tanpa ini, dashboard apa
pun akan dibantah *"datanya salah"* — dan benar.

### 7.1 Dashboard beban ortho per dokter
Buku · hari praktik/bulan · kontrol/hari · **slot vs kecepatan nyata** · jarak
kontrol · proyeksi 6 bulan. Bingkai **risiko**, bukan peringkat.

### 7.2 Peringatan di titik pemasangan
Saat pemasangan dijadwalkan, sistem memberi tahu kalau dokter itu di atas ambang.
**Peringatan, bukan larangan** — menyajikan informasi, tidak memutuskan.

### 7.3 Layar yang menunjukkan slot vs kenyataan
Temuan §3 (slot 10 menit, nyata 13,5) hanya ketahuan karena digali manual.
Seharusnya terlihat sendiri: **selisih slot-vs-checkout per dokter**, supaya
penyetelan durasi bisa ditinjau berdasarkan bukti, bukan ingatan.

### 7.4 Slot ortho dipisah dari slot umum di layar reservasi

### 7.5 Sambungan ke modul ORTHO (IT-013) Fase 4 "Ukur"
Tiga metrik yang belum ada di mana pun: **stok kewajiban aktif** · **jarak
kontrol** · **rasio selesai kohor**. Plus daftar **250 pasien dorman** (§5).

### 7.6 Lubang yang murni milik kita

24 kumpulan agregat Laporan Executive diperiksa seluruhnya — omzet, target, YoY,
top perawatan, top dokter, diskon, promo, retensi:

> **Nol stok kewajiban. Nol jarak kontrol. Nol rasio selesai. Nol slot-vs-nyata.**

Bukan manajemen yang lalai — **alatnya memang buta**, dan alat itu milik kita.

---

## 8. 🔒 INTERNAL — kesalahan HUB dalam analisa ini

Ditulis supaya tim tidak mengulanginya. **Jangan masuk versi manajemen.**

1. **Rumus "beban" mengandaikan kontrol bulanan** — `buku ÷ hari/bln` (Andy 48)
   disajikan seolah beban kerja. Kepadatan nyata **17,6/hari**.
2. **"Ideal 30 menit" tanpa sumber** — asumsi diselundupkan sebagai fakta.
   Diganti norma dari data sendiri (§4).
3. **Klasifikasi spesialis dari NAMA** — `doctor` teks bebas vendor. Catatan lama
   sudah melarang; tetap dilanggar. Klaim "7 spesialis = 49% omzet" runtuh.
4. **Mencampur jendela waktu** — 261 / 130 / 56 disandingkan tanpa label (§5).
5. **Rasio selesai cross-sectional** disajikan sebagai completion rate.
6. **`LIKE '%Andy%'` juga cocok "drg. Candra Fitri*andy*"** — `value()` mengambil
   yang salah, dan sempat memicu alarm palsu bahwa data Andy terhapus.
7. **Menyimpulkan "tidak punya data durasi"** padahal ada di enam tabel —
   ditangkap Ikhsan, bukan oleh pemeriksaan sendiri.
8. **Menyimpulkan "10 menit = tak pernah diputuskan"** — salah paham arti
   `backfill`; ditangkap Ikhsan (§3).

**Pola nomor 1–8 sama: menyimpulkan sebelum mengecek satu lapis lagi ke bawah.**
Kesimpulan soal durasi berbalik **tiga kali** dalam satu malam.

Pengelompokan dokter **selalu** pakai `doc_reff_id`; `doctor` hanya untuk
ditampilkan.

---

## 9. 🔒 INTERNAL — batas data & pertanyaan lapangan

### Yang data TIDAK bisa jawab

⛔ **"Dokternya tak muat" vs "pasiennya tak datang"** — angka identik. Batas keras.
⛔ **Apakah kasus non-Sp.Ort lebih sederhana** — selisih nilai 28% cuma petunjuk.
⛔ **Apakah 13,5 menit itu waktu di kursi** — `checkout_time` = waktu membayar.
⛔ **Reservasi baru ada sejak 1 Mei 2026** (7.077 baris). Riwayat permintaan cuma
5 bulan. Transaksi kasir panjang (2020-11-29 → 2026-09-23, 148.966 baris) tapi
merekam apa yang **terjadi**, bukan apa yang **diminta**.

ℹ️ **Grain sudah diverifikasi:** 5.974 baris kontrol : 5.966 kunjungan unik =
rasio **1,001**. Satu kontrol praktis satu baris — volume tidak terdistorsi.

ℹ️ **Riwayat 2024–25 dipakai SEKALI** sebagai konteks: jarak kontrol melar
36,9 → 55,9 hari sepanjang 2024 lalu mendatar. Selebihnya fokus 2026 ke depan.

### Pertanyaan untuk Nisa & Kalideres

**Tentang durasi — paling menentukan:**
1. Slot Andy **10 menit**, rekan-rekannya 15–30, Stiefani di cabang yang sama
   **30**. Dari mana angka 10 itu, dan apakah masih sesuai?
2. **Pemasangan Behel 10 menit** (default 60) — itu benar, atau sisa data lama?
3. Checkout menunjukkan **13,5 menit** antar pasien padahal slot 10. Itu karena
   dikerjakan lebih cepat, atau ada **kursi/asisten yang jalan paralel**?

**Tentang kapasitas:**
4. Hari Andy penuh — ada pasien yang **tidak dapat slot**, atau semua akhirnya dapat?
5. Kalau Rabu penuh, apa yang **biasanya terjadi**: tunggu minggu depan, pindah
   dokter, pindah cabang, atau batal?
6. Andy sampai **22:00** (93% hari lewat jam tutup) — keputusan siapa, dan apa
   akibatnya kalau dihentikan 20:00?

**Tentang distribusi:**
7. **Yang paling menentukan:** pasien dipegang Andy atau Ryan/Stiefani karena
   **kondisi klinis**, atau karena **siapa yang memasang pertama**?
8. Kalau Andy tidak praktik seminggu, pasiennya diarahkan ke mana?
9. Siapa yang **nyata** memutuskan dokter untuk kontrol berikutnya — dokter,
   sales, pasien, atau jadwal yang tersedia?

**Tentang hari sepi & stok:**
10. Kamis Rp 2,5 jt — benar-benar sepi, atau **dibuat sepi** karena diarahkan ke Rabu?
11. Ada mekanisme menandai pasien **dropout permanen**, atau menggantung selamanya?
    (±250 pasien tak kontrol >90 hari — §5)
12. Dari 130 lepas bracket, berapa yang **tuntas** dan berapa karena alasan lain?

Kalau jawaban (7) adalah *"siapa yang memasang"*, ini masalah **distribusi** dan
bisa dibereskan tanpa merekrut siapa pun. Kalau *"kondisi klinis"*, penambahan
kapasitas spesialis tak terhindarkan. **Data tidak bisa memilihkan.**

---

## 10. Yang BUKAN wilayah IT

Rem laju pemasangan · menambah hari praktik atau spesialis · strategi promo ·
membangun permintaan di hari sepi.

Catatan untuk Ikhsan sebagai anggota manajemen — **bukan usulan IT**:

> Promo ortho saat ini (**"Payday"**, **"AGS War"**, **"Freedom 1"**,
> **"Cimulang Oktober"**) menjual kewajiban kontrol dua tahun ke depan tanpa
> memeriksa apakah ada yang bisa mengerjakannya.

### Tesis untuk Executive

⛔ **Bukan** "kita kekurangan dokter" — belum terbukti.
⛔ **Bukan** "Andy overload" — mengundang debat soal orang.

> **"Pertumbuhan ortho sudah menjadi bagian struktural bisnis, tapi kapasitasnya
> sangat terkonsentrasi pada sedikit hari-dokter. Kalideres memperlihatkan
> konsekuensi paling ekstrem: 46,6% omzet cabang bertumpu pada satu dokter yang
> praktik ±3 hari per bulan, sementara stok pasien ortho jaringan bertambah
> bersih +836 dalam 21 bulan."**

Keputusan yang dipaksa bukan *"hire atau tidak"*, melainkan: **"apakah kita
nyaman mempertahankan model pertumbuhan yang bergantung pada konsentrasi
seperti ini?"** — jauh lebih sulit dibantah, dan tidak memaksa solusi tertentu.

⛔ **Yang dicabut dari draf pertama:** *"kalau dia berhenti, 46,6% omzet ikut"*.
Itu ramalan skenario, bukan observasi — pasien bisa pindah dokter, hari, atau
cabang, dan kita belum tahu proporsinya. Yang benar: **"46,6% omzet Kalideres
saat ini terkonsentrasi pada satu dokter."**

### ⚠️ Risiko salah-baca terbesar

**Executive melihat Rp 23,9 jt/hari lalu menjadikan Andy standar emas**, bukan
sinyal bahaya — lalu bertanya kenapa Ryan (5,2 kontrol/hari) tidak digenjot
menyamai. Peringatan sistemik berubah jadi **cambuk produktivitas**, memicu
burnout dan menghancurkan ekuilibrium yang sekarang masih menahan.

**Pencegahannya:** angka yang dipajang paling besar adalah **+836** dan
**7,1% selesai** — bukan nama orang. Yang bermasalah sistemnya, bukan orangnya.

Dan jebakan kedua: **menganggap konsentrasi = kekurangan**. Bisa jadi kapasitas
cukup, pasien memilih dokter tertentu, jadwal sengaja dipadatkan, pasien tetap
on-schedule. Kalau itu yang ditemukan lapangan, narasi "krisis kapasitas" runtuh.
Karena itu pertanyaan terpenting besok bukan *"Andy kebanyakan pasien?"*
melainkan: **"di titik mana kapasitas benar-benar membatasi pilihan pasien?"**

---

## 11. Urutan

1. Rapat tim IT — dokumen ini
2. Nisa + kunjungan Kalideres — **bawa 12 pertanyaan §9, bukan kesimpulan**
3. Gabungkan POV lapangan
4. **Baru** dokumen manajemen — pendek, satu tesis, tanpa §8–§9, tanpa tabel per dokter

⚠️ Untuk rapat: **mulai dari +836 dan 7,1% selesai, bukan dari tabel per dokter.**

---

## Lampiran — catatan keadaan data (24 Sep malam)

⚠️ Angka durasi di §3 diambil **sebelum pukul 19:29**. Malam itu penyetelan durasi
Andy tersentuh tanpa sengaja lewat layar Doctor: **17 dari 19 baris sub-detail
terhapus**, 3 baris layanan dibuat ulang dengan nilai sama (10/10/10). Hanya Andy
yang terdampak — diverifikasi: total tabel 225 → 208, selisih persis 17.

Seluruh 19 baris **ada utuh** di cadangan `prod_pre_slot_20260908_1736.sql.gz`
(8 Sep); baris-baris itu dibuat 21 Mei dan tak pernah diubah, jadi cadangan itu =
keadaan persis sebelum perubahan. **Pemulihan tertunda** — server sedang
terdegradasi berat (respons 12–26 detik, SSH sempat menolak, load 42–48 di mesin
64 CPU, sementara pemakaian akun kita 0,1%).

Dampak sementara **konservatif**: tanpa penyetelan khusus, sistem memakai durasi
default yang **lebih panjang** (30/60 menit), jadi slot Andy jadi lebih longgar,
bukan lebih sempit.
