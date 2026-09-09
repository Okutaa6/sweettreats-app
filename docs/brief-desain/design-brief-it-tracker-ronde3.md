# Design Brief — IT Request Tracker, RONDE 3 (papan yang sudah terisi nyata)

> Untuk claude.ai/design. **Lanjutan ketiga**, bukan mulai dari nol.
> Lampirkan paket ronde 2 p2: `IT Request Tracker.dc.html`, `Sidebar.dc.html`,
> `it-store.js`, `support.js` + 11 screenshot. Bahasa visual, zona grafit,
> sistem badge, dan mesin fase **diteruskan apa adanya** — jangan dirancang ulang.
> Konteks: `docs/rencana/rencana-it-tracker.md` §7, §14.13, §14.16, §14.21.
> Ikon **FontAwesome 5** — nama khas FA6 jadi kotak kosong.

## 0. Kenapa ada ronde 3

Dua hal berubah sejak ronde 2, dan keduanya mengubah pekerjaan desain.

**Pertama: papannya tidak lagi kosong.** Sampai 4 Sep, semua rancangan diuji
dengan 3–6 kartu contoh. Sekarang dev berisi **31 tiket kerjaan sungguhan**
(26 aktif · 4 ditunda · 1 baru dari demo), dengan tanggal masuk asli — ada
yang sudah menunggu **59 hari**. Rancangan yang enak dilihat pada 5 kartu
belum tentu bertahan pada 26 kartu; itulah yang perlu dinilai ulang.

**Kedua: ada delapan permukaan baru yang DIKODING LEBIH DULU tanpa lewat
desain.** Ekky memintanya berturut-turut, sebagian saat demo berlangsung, dan
HUB mengerjakannya langsung karena semuanya memakai komponen yang sudah ada.
Semua sudah live di dev dan berjalan; **tapi tiga di antaranya sebenarnya
menambah bahasa visual baru** dan itu keputusan desain, bukan keputusan
koding. Brief ini menyerahkan ketiganya untuk dinilai dan dirapikan — bukan
untuk dibangun dari nol.

⚠️ Jadi peran ronde 3 berbeda dari dua ronde sebelumnya: **ini ronde koreksi
dan pemadatan, bukan ronde penambahan.** Kalau jawabannya "buang saja elemen
X", itu jawaban yang sah dan disambut.

## 1. Yang sudah dibangun sejak ronde 2 (semua live di dev)

| # | Permukaan | Status desain |
|---|---|---|
| 1 | **Lampiran FRM-IT-01** — kartu berkas (pratinjau gambar / badge XLSX·PDF), tampil di detail & ekspor | pakai komponen lama, aman |
| 2 | **Jalan pintas paraf bertanda** — Head IT meneruskan gerbang Analisis tanpa paraf BO dengan alasan wajib; penanda merah bergaris putus di kepala tiket + panel 02, chip di kartu, baris di ekspor | **perlu dinilai** |
| 3 | **Papan bertab** — 2 tab jenis permintaan (Support ‖ Feature/Modul) menggantikan chip filter S/M/L | **perlu dinilai** |
| 4 | **Tahap jadi tab di dalam kolom** — Development ‖ Uji Internal, dan UAT ‖ Go Live ‖ Evaluasi | **perlu dinilai** |
| 5 | **Parkiran bertab** — Ditunda ‖ Ditolak ‖ Selesai | pakai komponen no. 4 |
| 6 | **Badge jenjang jadi kata** — Support · Fitur · Modul · "belum ditriase" (kuning) menggantikan huruf S/M/L | pakai komponen lama |
| 7 | **Unit usaha RB Las** — kartu bergaris kiri oranye + badge `RB LAS`; Sweet Treats tanpa badge | **perlu dinilai** |
| 8 | **Catatan kemajuan "sudah sampai mana"** — panel di detail + satu baris biru di kartu; berubah kuning + menyebut umurnya sesudah 7 hari | **perlu dinilai** |
| 9 | **Badge nama PIC di kartu**; **unggah bahan rancangan di FRM-IT-02** | pakai komponen lama |

## 2. Empat masalah desain yang sebenarnya

### 2.1 Kartu papan kelebihan muatan

Satu kartu sekarang bisa membawa **15 elemen**: nomor · sumber/pemohon ·
badge `RB LAS` · badge jenjang · judul · chip kemajuan fase (`Analisis · 2/5`) ·
prioritas IT · "N fitur di dalam" · "menunggu IT-xxx" · "↳ dari IT-xxx" ·
"tanpa paraf BO" · nama PIC · baris catatan kemajuan · strip menunggu ·
tombol aksi. Semuanya lahir dari kebutuhan nyata satu per satu, dan tak ada
satu pun yang pernah dinilai **bersama-sama**.

Pertanyaan untuk ronde 3: mana yang naik ke kartu, mana yang cukup di detail,
mana yang jadi satu baris ringkas. Kartu yang harus dibaca dua detik tidak
boleh punya 15 hal.

### 2.2 Dua perusahaan dalam satu papan

Ekky melayani **Sweet Treats** (klinik) dan **RB Las**. Keputusannya: satu
papan, dibedakan warna — *"task RB digabung, warnanya dibedain"*. Yang
terpasang sekarang: garis kiri oranye + badge oranye, dan Sweet Treats sengaja
**tidak** ditandai sama sekali (menandai semuanya sama saja tak menandai apa pun).

Kesulitannya: papan ini **sudah memakai warna untuk arti lain** — strip
menunggu memanas kuning/merah bila penunggunya di luar IT, prioritas P1/P2/P3,
status Ditunda/Ditolak/Selesai, penanda merah "tanpa paraf BO". Oranye untuk
"perusahaan" masuk ke ruang yang penuh. Perlu keputusan sistem warna, bukan
tambalan.

### 2.3 Tiga sistem tab di satu layar

Papan sekarang punya tab jenis permintaan (atas), tab tahap (di dalam tiap
kolom), dan tab status (di parkiran). Masing-masing menyelesaikan masalah
nyata — Ekky sendiri yang minta, supaya Development dan Internal Testing tak
perlu digulir sampai bawah. Tapi tiga tingkat tab di satu halaman adalah
beban ingatan; perlu dinilai apakah hierarkinya terbaca atau justru bertumpuk.

⚠️ Satu keputusan yang perlu diketahui: tiket yang **belum ditriase** belum
punya jenjang, jadi ia **muncul di kedua tab** dengan catatan yang mengatakannya.
Akibatnya badge kedua tab (20 + 13) tidak berjumlah 26. Itu disengaja dan
dijelaskan di layar. Kalau ada bentuk yang lebih jujur, itu sambutan baik.

### 2.4 "Sudah sampai mana" — komponen yang benar-benar baru

Ini satu-satunya elemen yang tak punya rujukan di paket r1/r2. Isinya satu
kalimat dari IT ("tinggal uji di 2 cabang"), plus siapa dan kapan. Sesudah
**7 hari** ia berubah kuning dan menyebut umurnya — karena catatan kemajuan
tanpa tanggal berbohong pelan-pelan: kelihatan terurus padahal tiga minggu basi.

Yang perlu dirancang: bagaimana ia tampil di kartu (sekarang satu baris biru),
di detail (sekarang panel paling atas), dan bagaimana keadaan "basi"
ditampilkan tanpa berebut perhatian dengan strip menunggu yang juga memanas.

## 3. Yang WAJIB diikuti — jangan dirancang ulang

- **Mesin fase** (`it-store.js`): `pathFor`, `RANK`, `gateInfo`, aturan panas.
  Sudah diterjemahkan ke PHP dan dikunci **106 uji**. Tampilan boleh berubah;
  aturannya tidak.
- **Panas hanya untuk tunggu di LUAR IT.** Tiket 59 hari di Development tetap
  dingin. Papan ini menagih orang lain, bukan menghakimi IT sendiri.
- **Kelas A/B/C.** Kelas B (03·04·06) **terisi sendiri** — jangan pernah
  digambar sebagai formulir kosong untuk diketik ulang.
- **Prioritas pemohon ≠ prioritas IT** — dua kolom terpisah, selamanya.
- **Zona grafit** untuk seluruh area IT; palet cokelat Sweet Treats di luar itu.
- **Ekspor FRM-IT-01** mengikuti templat resmi — bentuknya sudah benar.

## 4. Angka nyata untuk mengukur rancangan (dev, 5 Sep)

| Yang diukur | Angka |
|---|---|
| Tiket di papan | 31 (26 aktif · 4 ditunda · 1 baru) |
| Sebaran fase | 7 triase · 5 antre · 3 analisis · 6 development · 4 UAT · 1 evaluasi |
| Jenis | 13 fitur/modul · 6 support · 7 belum ditriase |
| Macet di luar IT | 3 |
| Tunggu terlama | 59 hari (Development — sengaja tetap dingin) |
| Orang IT | 2 (Ekky + Reza) untuk 5 cabang + RB Las |
| Kartu terpanjang | 15 elemen |

Rancangan ronde 3 harus diuji pada angka-angka ini, bukan pada 5 kartu contoh.

## 5. Yang diharapkan dari handoff

Sama seperti dua ronde sebelumnya: `.dc.html` yang bisa dibaca sebagai acuan
koding + screenshot tiap keadaan kunci. Yang **paling dibutuhkan**:

1. **Papan pada kepadatan nyata** — 26 kartu, dengan tab jenis di Support dan
   di Feature/Modul, termasuk keadaan "belum ditriase muncul di dua tab".
2. **Kartu dalam beberapa varian**: paling sederhana (S di Development) sampai
   paling ramai (L di Analisis, RB Las, ada dep + parent + tanpa paraf BO +
   PIC + catatan kemajuan basi). Kalau kartu terramai tak terbaca dua detik,
   tunjukkan apa yang harus turun ke detail.
3. **Sistem warna** yang menampung: panas menunggu, prioritas, status parkir,
   penanda pelewatan paraf, dan **unit usaha**. Satu gambar yang memperlihatkan
   kelimanya hidup berdampingan.
4. **Komponen "sudah sampai mana"** — di kartu dan di detail, keadaan segar
   dan basi.
5. **Hierarki tab** — kalau tiga tingkat terlalu banyak, usulkan
   penggantinya. Membuang satu tingkat adalah hasil yang sah.

## 5b. Yang WAJIB ada — bentuknya bebas

1. Kartu papan tetap terbaca **dua detik**: nomor, judul, menunggu siapa,
   berapa lama.
2. Tiket **RB Las** terbaca sekilas tanpa mengalahkan strip panas.
3. Tiket **belum ditriase** tetap paling menonjol di kolom Masuk — itu
   satu-satunya keadaan yang menuntut tindakan Head IT hari itu.
4. Catatan kemajuan **basi** terlihat basi, bukan sekadar ada.
5. Parkiran tetap memperlihatkan **alasan** ditunda — ditunda bukan berarti hilang.
6. Semua tetap terbaca di lebar **1024 px** (laptop Ekky) dan tidak rusak di
   1280–1500 px.

## 6. Satu kalimat yang menjelaskan ronde 3

> Ronde 1 membuat papannya, ronde 2 membuat formulirnya, **ronde 3 merapikan
> papan yang sekarang benar-benar penuh** — dan memutuskan apa yang harus
> turun dari kartu supaya sisanya tetap terbaca dua detik.

---

**Catatan untuk chat desain:** semua yang disebut di §1 sudah berjalan di
`https://dev.sweettreats.id/it-tracker`. Kalau rancangan ronde 3 mengubah
sesuatu, cukup katakan mana yang diganti dan mana yang tetap — HUB yang akan
menyesuaikan kodenya. Tidak ada yang perlu dibangun ulang dari nol.
