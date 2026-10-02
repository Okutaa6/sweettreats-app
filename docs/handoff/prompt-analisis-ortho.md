# Prompt untuk chat ANALISIS — Jadwal Control Ortho

> Salin isi berkas ini ke chat mana pun, atau kalau chat-nya punya akses repo,
> cukup bilang: **"baca `docs/handoff/prompt-analisis-ortho.md`"**.
>
> Disusun 2 Oktober 2026. Isinya orientasi, **bukan** perintah membangun —
> tujuannya supaya yang menganalisis paham dulu modulnya sebelum menilai UI,
> design system, atau alurnya.

---

Kamu akan menganalisis satu halaman di aplikasi internal klinik gigi
**Sweet Treats** (Laravel 10 + Blade, MariaDB, 5 cabang: Bintaro, Sunter,
Kalideres, Cinere, Pamulang). Halamannya **Tracking Pasien Ortho**, URL
`/tracking-ortho`.

Baca seluruh berkas ini dulu. Jangan menyarankan apa pun sebelum selesai —
sebagian keputusan di halaman ini **sengaja** melawan dugaan pertama, dan
alasannya ada di bawah.

---

## 1. Masalah yang dipecahkan

Pasien **ortho** (behel) bukan pasien sekali datang. Mereka harus kontrol
berulang, kira-kira tiap **28–45 hari**, selama **1–3 tahun**. Kalau tidak
kontrol, perawatannya mandek dan hasilnya rusak — dan klinik kehilangan
pendapatan berulang yang sudah pasti.

Sebelum halaman ini ada, Sales melacaknya di **spreadsheet**. Satu berkas per
cabang, dan bentuknya begini: berkas Cinere punya **149 kolom, 30 di antaranya
kolom BULAN**. Petugas mencatat tanggal pasien DATANG di bawah bulannya.

Akibatnya satu pertanyaan paling dasar **tidak bisa dijawab**:

> *"Siapa yang hari ini sudah waktunya kontrol tapi belum datang?"*

Untuk menjawabnya, orang harus memindai 30 kolom × ratusan baris dengan mata.
Jadi tidak pernah dijawab. Pasien hilang tanpa ada yang tahu kapan hilangnya.

**Halaman ini ada untuk menjawab satu pertanyaan itu, setiap hari.**

---

## 2. Hasilnya jadi apa

Keluaran halaman ini **bukan laporan**. Keluarannya **daftar telepon hari ini**,
terurut dari yang paling mendesak.

Rantai hasilnya:

```
daftar kerja  →  Sales menelepon  →  pasien dibujuk  →  reservasi dibuat
                                                              │
                                                     pasien datang kontrol
                                                              │
                                              perawatan jalan, klinik dibayar
```

Yang bisa diukur sesudah halaman ini dipakai:

| Ukuran | Artinya |
|---|---|
| Berapa pasien terlewat jatuh tempo | beban kerja hari ini |
| Berapa yang terlewat > 14 hari | sudah gawat |
| Berapa yang hilang > 180 hari | kemungkinan besar sudah lepas, bukan lagi target telepon |
| Berapa yang sudah di-FU tapi belum ada reservasi | kerja Sales yang belum tuntas |

Di prod ada sekitar **1.639 kasus ortho** (angka dari audit HUB). Jadi ini bukan
halaman yang dibuka sesekali — ini layar kerja harian.

---

## 3. Siapa penggunanya

### Pengguna utama: Sales / FO per cabang

Orang yang **menelepon pasien**. Dia:

- terkunci ke cabangnya sendiri (dari `session('raw_branch_id')`)
- tidak bisa menembus cabang lain lewat URL, bahkan kalau URL-nya diubah tangan
- hanya melihat pasien yang dipegang cabangnya

### Pengguna kedua: role lintas cabang

Super Admin, Admin, Head IT, IT, Supervisor. Mereka **tidak punya cabang**, jadi
dapat **dropdown pemilih cabang utama**. Pilihannya tersimpan di sesi.

⚠ Versi pertama halaman ini memaku mereka ke cabang pertama (Bintaro) **tanpa
pesan apa pun** — mereka tidak tahu sedang melihat cabang yang salah. Itu bug
yang sudah diperbaiki, dan jadi alasan kenapa pemilih cabangnya ada.

### Yang BUKAN pengguna

**Dokter tidak memakai halaman ini.** Ini penting dan sempat salah dipahami:

> Rancangan awal menyangka tanggal kontrol berikutnya **sudah disepakati dokter
> dan pasien di kursi perawatan**, jadi Sales cuma perlu mengonfirmasi sekali
> klik. Koreksi dari Ikhsan (orang operasional): **Sales yang menentukan
> tanggal; dokter hanya menyarankan lisan.**

Premis yang salah itu melahirkan tombol "Konfirmasi jadwal" yang akhirnya
**dihapus seluruhnya**. Kalau kamu melihat dokumen lama yang menyebut tombol
itu, dokumen itu sudah tidak berlaku.

---

## 4. Satu konsep yang harus benar dulu: "Jadwal" itu JATUH TEMPO

Ini sumber kebingungan terbesar di modul ini. Kolom bernama **Jadwal** **bukan
tanggal janji**. Ia **jatuh tempo** — perkiraan kapan pasien *seharusnya* sudah
kontrol lagi.

| Istilah | Artinya | Nyata atau hitungan? |
|---|---|---|
| **Jadwal** (`sch_date`) | kapan pasien SEHARUSNYA datang | **hitungan sistem** |
| **Follow-up** (`fu_date`) | kapan Sales benar-benar menelepon | fakta |
| **Reservasi** (`rsv_date`) | janji yang benar-benar dipesan | fakta |
| **Datang** (`visit_date`) | pasien benar-benar hadir | fakta, dari rekam medis |

Konsekuensinya: **tidak ada slot yang dipesan dan tidak ada pasien yang
diberi tahu** saat jatuh tempo dihitung. Jadi tidak ada yang bisa "mustahil".
Jatuh tempo **boleh jatuh di hari Minggu, boleh jatuh di masa lalu** — kalau
pasien memang sudah waktunya kontrol tiga minggu lalu, itu faktanya.

### Kolomnya menampilkan tanggal — tapi ditandai

Kolom Jadwal berbunyi `31 Agt '26`, dengan **garis putus amber** selama
tanggalnya masih hitungan mesin, dan perkiraannya (`Sudah waktunya`,
`± 3 pekan lagi`) di tooltip.

⚠ Antara 2–5 Okt 2026 kolom ini sempat menampilkan **perkiraan saja**, lalu
dibatalkan. Alasan membatalkannya penting kalau kamu hendak menyarankannya
lagi: perkiraan memberi masa depan **empat tingkat** tapi masa lalu cuma
**satu ember** ("Sudah waktunya"), sementara daftar diurutkan dari jatuh tempo
terdekat — jadi semua yang terlambat menumpuk di paling atas dan di situ
kehilangan pembeda. Terukur: enam baris teratas semuanya "Sudah waktunya",
padahal yang satu telat 32 hari dan lima sisanya 2–3 hari.

Tiga alasan di bawah ini **masih berlaku** sebagai pembatas — ia menjelaskan
kenapa tanggalnya ditandai sebagai hitungan, bukan dipajang sebagai janji:

1. Spreadsheet Sales **tidak punya kolom tanggal kontrol berikutnya** — jadi
   satuan itu tidak pernah dipakai di lapangan.
2. Kata Anisa (Sales): *"di kasih tau kalau udah lama ga kontrol dan di tawarin
   jadwal"* — **pemicunya durasi**, tanggalnya ditawarkan saat menelepon.
3. Menampilkan tanggal hitungan sebagai angka pasti membuatnya **terbaca seperti
   janji**, padahal ia tebakan.

Tanggalnya tetap dihitung dan tetap dipakai untuk mengurutkan daftar, memilah
sub-tab, mengisi kartu, dan jadi default lembar Buat Reservasi. Ia hanya
**berhenti dipajang**.

Sesudah itu, satu-satunya tanggal di tabel adalah tanggal **nyata**: Follow-up
dan Reservasi.

---

## 5. Dari mana jatuh temponya dihitung

Bukan `+30 hari` buta. Dari **irama pasien itu sendiri**:

```
median selang antar kunjungan pasien ini  →  dijepit ke [21, 60] hari
                     │
      kalau selangnya < 2 (belum cukup data)  →  cadangan +30 hari
```

Dipakai **median, bukan rata-rata** — satu jeda enam bulan menggeser rata-rata,
tidak menggeser median.

Sistem juga menandai seberapa **yakin** ia: `sebar = (max − min) / median`.
Pasien dengan selang 28·28·28 jauh lebih bisa dipegang daripada 20·90·25·110,
walaupun yang kedua punya lebih banyak data.

### Satu pagar, dan hanya satu

Pasien yang **hilang > 180 hari tidak diberi jatuh tempo sama sekali**. Mereka
masuk antrean "Belum punya jadwal". Alasannya **beban kerja**, bukan jadwal:
menelepon orang yang sudah setengah tahun hilang beda pekerjaan dari menagih
yang terlambat dua minggu.

### Tiga pagar yang SENGAJA dibuang — jangan sarankan balik tanpa membaca ini

Rancangan awal memasang tiga pagar keras: **kapasitas per cabang-tanggal**,
**hari praktik dokter**, dan **klinik tutup Senin**. Ketiganya dicabut.

Sebabnya terukur. Penumpukan 651 usulan di satu tanggal **bukan** karena tidak
ada pagar, melainkan karena ada **lantai `hari ini + 3`** yang mendorong setiap
jatuh tempo yang sudah lewat ke tanggal dekat yang sama. Begitu lantai itu
dibuang, **tanpa pagar apa pun**:

| | |
|---|---|
| Pasien aktif terukur | 34 |
| Tanggal jatuh tempo berbeda | 28 |
| Paling padat satu tanggal | **2 orang** |
| Rata-rata per tanggal | 1,21 |

Jatuh tempo mewarisi sebaran kunjungan terakhir, dan kunjungan tersebar di semua
hari buka — jadi jatuh temponya ikut tersebar sendiri.

**Pagar hari dokter tidak hilang, ia pindah ke tempat yang benar:** diperiksa
saat **MEMESAN**, bukan saat menghitung jatuh tempo.

⚠ Kelemahannya dikatakan apa adanya: angka di atas dari 39 pasien lokal,
prod punya 1.639. Jadi ini keputusan yang **menunggu diukur ulang di prod**, dan
mesinnya masih utuh supaya reversibel.

---

## 6. Tujuh tampilan — apa yang bisa dilakukan di masing-masing

Semuanya satu URL `/tracking-ortho` dengan query string berbeda. Tidak ada
halaman kedua.

### 6.1 Daftar kerja *(bawaan)*

Layar utama. Tabel 8 kolom, **50 baris per halaman** (bisa diubah ke 10/25/100
lewat pemilih di kaki tabel), jatuh tempo terdekat di
atas:

```
Pasien │ Dokter │ Jadwal │ Follow-up │ Reservasi │ Status │ Tindakan berikutnya │ ⌄
```

Baris terlambat dapat **garis tepi kiri amber**; terlambat > 14 hari **merah**.

Yang bisa dilakukan:

| Aksi | Hasilnya |
|---|---|
| **Catat Follow-up** | membuka lembar: **hasil** (dihubungi · tidak respon · minta jadwal ulang · menolak · salah nomor), kanal, tanggal, catatan bebas — boleh sekaligus memasang tanggal. Sejak 4 Okt 2026; sebelumnya sekali klik dan hasilnya tidak pernah tercatat |
| **Buat Reservasi** | membuka lembar pemesanan |
| Buka baris (⌄) | panel ringkas: 8 fakta pasien + 4 kontrol terakhir |
| Klik nama | drawer riwayat lengkap |
| Klik nomor telepon | langsung ke WhatsApp |
| **Geser pengingat** | ganti tanggal jatuh tempo |
| **Tandai tidak hadir** | pasien tidak datang |
| **Batalkan follow-up** | jalan pulang dari salah klik |
| **Pindahkan** | transfer ke cabang lain |

### 6.2 Antrean penjadwalan — kartu "Belum punya jadwal"

Pasien **tanpa** jatuh tempo. Dikelompokkan jadi tiga, dan kelompoknya
menjelaskan **sebabnya**, bukan cuma memisahkan:

| Kelompok | Siapa |
|---|---|
| **Hilang** (merah) | > 180 hari tidak kontrol |
| **Jarang** (amber) | selangnya di atas target 30 hari |
| **Baru selesai kontrol** (hijau) | tinggal dijadwalkan |

Satu tindakan: **Jadwalkan**.

### 6.3 Kalender

Grid bulanan, sebaran jatuh tempo. Satu-satunya tempat yang masih memakai
tanggal kalender sungguhan — karena di sini tanggalnya memang yang dicari.
Maksimal 3 chip nama per sel, sisanya "+N lainnya".

### 6.4 Drawer satu hari

Dari klik sel kalender. Kepala berisi hitungan *"N jadwal · N sudah reservasi ·
N belum"*, lalu satu kartu per pasien terurut jam.

### 6.5 Pasien Ortho (roster)

Semua pasien ortho cabang itu, dua tampilan: **kartu** atau **daftar**. Dua
pemilah: Aktif ⇄ Selesai, dan Daftar ⇄ Kartu. Dipakai untuk melihat populasi,
bukan untuk kerja harian.

### 6.6 Cabang Lain

Pasien yang **dipasang di cabang kita tapi sekarang dipegang cabang lain**.
Lihat-saja. Baris yang tertinggal dapat strip merah dan satu tombol:
**Ingatkan** — mengirim WA ke cabang yang memegang.

Ini **satu-satunya aksi tulis yang boleh menyentuh kasus milik cabang lain**,
dan ia punya jeda **7 hari** supaya tidak jadi spam antar cabang.

### 6.7 Drawer detail pasien

Riwayat lengkap: 3 KPI, matriks kontrol 8 kolom dengan pemisah saat pasien
berpindah cabang, dan kartu kaki yang berubah empat keadaan (bisa dijadwalkan /
dipegang cabang lain / perawatan selesai / dsb).

### Lima lembar samping (410px)

`reservasi` · `pindahkan` · `geser` · `jadwalkan` · `follow-up`. Satu berkas, lima isi.

Lembar **Buat Reservasi** menampilkan **hari praktik dokter pasien itu** lengkap
jam dan seberapa penuh. Hari yang penuh **tetap ditampilkan** (dinonaktifkan),
tidak disembunyikan — Sales perlu tahu harinya ada tapi habis.

Kalau dokternya tidak terpetakan, lembarnya **tetap berguna**: sebabnya
dikatakan apa adanya, dan kotak tanggal bebas selalu ada.

---

## 7. Alurnya, lengkap

### 7.1 Lingkaran besarnya

```
        OMNICARE (rekam medis — sumber kebenaran, read-only)
                          │
            ┌─────────────┴─────────────┐
      ort:seed-cases              ort:pull-visits
   (siapa pasien ortho,         (kunjungan baru +
    sekali, saat pasang)         menutup kontrol)
                                   cron 04:00 tiap hari
            └─────────────┬─────────────┘
                          ▼
                 jatuh tempo dihitung
                  dari irama pasien
                          ▼
              ╔═══════════════════════╗
              ║   DAFTAR KERJA SALES  ║
              ╚═══════════════════════╝
                          ▼
                 Sales menelepon
                 → Catat Follow-up
                          ▼
                 Sales memesan
                 → Buat Reservasi ──► modul Reservasi
                          ▼
                  pasien DATANG
                          ▼
         malam berikutnya 04:00, tarikan membaca
         rekam medis → tutup kontrol "hadir"
         → hitung jatuh tempo berikutnya
                          │
                          └──────► kembali ke daftar kerja
```

**Arah data satu arah.** Modul ini **tidak pernah menulis ke Omnicare**. Ia
hanya membaca cermin dan menulis empat tabelnya sendiri.

### 7.2 Tangga tindakan per pasien — dua anak, bukan satu

```
belum ditelepon          →  [ Catat Follow-up ]
sudah ditelepon          →  [ Buat Reservasi ]
sudah ada reservasi      →  tidak ada tombol — "Menunggu pasien"
reservasi dibatalkan     →  [ Buat Reservasi ] lagi
```

**Telepon dulu, baru pesankan** — persis urutan di lapangan. Tangga ini dulunya
punya tombol ketiga "Konfirmasi jadwal" di puncaknya; ia dihapus karena satu
klik tanpa dialog di atas tebakan 14 hari memang tidak pantas ada.

### 7.3 Siapa yang menyatakan kontrol "selesai"

**Hanya rekam medis Omnicare.** Bukan status reservasi.

Reservasi `confirmed` **tidak berarti pasien datang**. Orang membatalkan, orang
tidak muncul. Jadi penutupan kontrol menunggu tarikan malam membaca bahwa
pasien benar-benar ada di rekam medis — **jeda ±18 jam, dan itu disengaja**.

Satu pengecualian manual: **Tandai tidak hadir**. Mesin tidak bisa tahu "pasien
tidak datang" dari rekam medis, karena ketiadaan bukan fakta yang dicatat.

### 7.4 Satu hal otomatis di luar tarikan malam

Ada observer yang mengawasi tabel reservasi. Begitu reservasi dibuat, diubah,
atau dibatalkan, status itu **disalin** ke baris kontrol. Itu sebabnya chip
**Dibatalkan** dan tanggal dicoret muncul tanpa Sales melakukan apa pun.

Observer itu **hanya menyalin potret** — ia tidak pernah menyentuh jatuh tempo,
tanggal datang, atau penutupan.

### 7.5 Jaring pengaman

Setiap aksi tulis bisa **diurungkan 90 detik** lewat toast. Yang disimpan adalah
**keadaan sebelumnya**, bukan perintah balik.

Untuk aksi yang paling sering salah klik (Catat Follow-up), ada jalan pulang
**permanen**: tombol **Batalkan follow-up**. Ia tidak menghapus riwayat medis —
tanggal kunjungan tidak tersentuh.

---

## 8. Hal-hal yang sengaja TIDAK ada

Jangan sarankan ini sebagai "yang kurang" tanpa membaca alasannya:

| Tidak ada | Kenapa |
|---|---|
| Tombol "Konfirmasi jadwal" | satu klik di atas tebakan 14 hari; premisnya salah (lihat §3) |
| Pagar kapasitas per tanggal | akar masalahnya lain; terukur tidak menumpuk (§5) |
| Pagar klinik tutup / hari dokter di jatuh tempo | jatuh tempo bukan janji; pagarnya pindah ke saat memesan |
| Kirim WA massal | belum diputuskan; menelepon masih manual dan sengaja |
| Tanggal pasti di kolom Jadwal | satuannya bukan yang dipakai Sales (§4) |
| Tambah Pasien Ortho | drawernya **ada tapi mati** — pintunya dicabut. Pasien ortho lahir dari rekam medis, bukan diketik tangan |
| Dokter login ke halaman ini | bukan penggunanya |

---

## 9. Batasan teknis yang membentuk rancangan

Kalau kamu menyarankan perubahan, empat hal ini mengikat:

1. **Hosting shared Hostinger**, struktur folder terpisah (`laravel/` +
   `public_html/`). **Tidak boleh ada dependensi yang butuh build step atau
   Node di server.** Jadi: tanpa Alpine, tanpa Vue, tanpa bundler. Seluruh
   JavaScript inline di Blade — 350 baris di halaman induk, plus beberapa blok
   kecil di partial.
2. **Collation prod campur** → **nol foreign key, nol JOIN lintas keluarga
   tabel**. Relasi lintas modul diselesaikan dengan `whereIn` berisi literal.
3. **Skema diubah manual** lewat berkas SQL, bukan `artisan migrate`.
4. **CSS pakai framework dan komponen yang sudah ada di project.** Kalau butuh
   gaya baru, tambahkan di berkas CSS project — **jangan inline style**.

Dan satu konvensi isi: **teks UI bahasa Indonesia**, responsif **minimal untuk
desktop dan tablet**.

---

## 10. Kalau kamu butuh kedalaman

Tiga berkas di repo, dan ketiganya menggambarkan keadaan **sekarang**:

| Berkas | Isi |
|---|---|
| `docs/brief-desain/brief-tracking-ortho-berjalan.md` | apa yang dilihat Sales, 14 bagian |
| `docs/brief-desain/design-system-tracking-ortho.md` | 30 token, 273 selector, 4 grid, 11 chip, z-index, responsif, animasi, kelas yatim |
| `docs/brief-desain/alur-fungsi-tracking-ortho.md` | 11 rute, 11 aksi, 8 service, 6 perintah, 4 model, observer, 23 view, 11 blok JS — tiap fungsi dengan validasi, pagar, kolom yang ditulis, dan bisa/tidaknya diurungkan |

⚠ **Tiga berkas lain di folder yang sama JANGAN dipakai** — itu riwayat
rancangan, sebagian isinya sudah tidak berlaku:
`design-brief-tracking-ortho.md`, `revisi-3-tracking-ortho.md`,
`revisi-3-tracking-ortho-transfer.md`. Dua yang `revisi-3-*` masih
memerintahkan tombol "Konfirmasi jadwal" yang sudah dihapus.

---

## 11. Yang saya minta darimu

Analisis **UI**, **design system**, dan **alur fungsinya**. Untuk tiap temuan,
sebutkan:

1. **apa** yang kamu lihat bermasalah,
2. **siapa** yang dirugikan — Sales yang menelepon, atau yang menyusul membaca
   kodenya,
3. **seberapa yakin** kamu, dan dari mana kamu tahu.

Dua permintaan khusus:

- **Pisahkan tegas** antara "ini salah" dan "ini beda dari kebiasaan saya".
  Beberapa keputusan di sini melawan dugaan pertama **dengan alasan terukur**;
  kalau kamu tidak setuju, bantah angkanya, jangan selera.
- **Kalau menyarankan sesuatu yang §8 sebut sengaja tidak ada**, sebutkan dulu
  alasan yang tertulis di sana dan kenapa menurutmu alasan itu kurang.

Mulai dengan menyebut **tiga hal yang menurutmu paling perlu diperbaiki,
berurutan**, sebelum masuk ke daftar panjang.
