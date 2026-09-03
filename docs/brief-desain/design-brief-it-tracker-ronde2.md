# Design Brief — IT Request Tracker, RONDE 2 (tiga formulir kerja)

> Untuk claude.ai/design. **Lanjutan**, bukan mulai dari nol.
> Lampirkan paket ronde 1: `IT Request Tracker.dc.html`, `Sidebar.dc.html`,
> `it-store.js`, `support.js` — bahasa visual, zona grafit, sistem badge fase,
> dan mesin fasenya **diteruskan apa adanya**, jangan dirancang ulang.
> Konteks: `docs/rencana/rencana-it-tracker.md` §7 (isi ketujuh formulir) dan
> §14.5 (pembagian fase). Ikon **FontAwesome 5** — nama FA6 jadi kotak kosong.

## 0. Kenapa ada ronde 2

Ronde 1 menghasilkan enam layar dan **semuanya berhasil**. Papan, detail tiket,
triase, halaman Head, ekspor FRM-IT-01, dan checklist Go Live sudah dipakai
sebagai acuan koding; Fase 1 sudah jadi dan jalan.

Ronde 1 juga **sengaja menyisakan tiga hal** — README-nya sendiri menulis
*"Belum termasuk: Formulir Analisis (FRM-IT-02 berat)"*. Ketiganya itulah isi
brief ini. Bukan tambalan: tanpa ketiganya, tiket jenjang **M dan L berhenti
di gerbang Analisis** dan tak pernah sampai Go Live.

Satu pola dari ronde 1 yang menentukan brief ini: **yang sudah didesain adalah
permukaan yang mengisi dirinya sendiri; yang tersisa adalah tiga permukaan
yang harus diketik manusia.** Bagian mudahnya sudah selesai.

## 1. Siapa yang memakai — berbeda dari ronde 1

Ronde 1 melayani empat kelompok. Ketiga layar ini hampir seluruhnya milik
**dua orang**, dengan satu pengecualian penting:

- **FRM-IT-02 Analisis** — ditulis **Ekky (Head IT)**, diparaf **satu**
  Business Owner (Konsultan atau Executive). Bukan tiga paraf.
- **FRM-IT-04 Uji Internal** — **IT sendiri, tanpa paraf pihak lain.**
- **FRM-IT-05 UAT** — ⚠️ **satu-satunya yang diisi PEMOHON** (Yovan, Della,
  Konsultan). Orang yang membukanya mungkin hanya sebulan sekali, tidak hafal
  istilahnya, dan sedang mengerjakan hal lain. Perlakukan berbeda dari dua
  yang lain — ini bukan layar untuk orang IT.

## 2. Yang mereka butuhkan, urut kepentingan

1. **Mendefinisikan "selesai" sebelum koding.** Itu seluruh alasan FRM-IT-02
   ada. Kolom **Kriteria Diterima** adalah field terpenting di seluruh sistem.
2. **Mengisinya dalam hitungan menit, bukan setengah jam.** Tim dua orang.
   Formulir yang terasa berat akan dilewati, dan sistemnya mati.
3. **Melihat sisa pekerjaan uji sekilas** — berapa test case lulus, berapa
   temuan Major yang masih terbuka.
4. **Pemohon bisa bilang "ya, ini yang aku minta"** tanpa merasa sedang
   mengisi dokumen resmi.

## 3. Tantangan desain yang sebenarnya

**Tiga formulir ini satu-satunya tempat manusia mengetik di seluruh sistem.**
Semua permukaan lain terisi sendiri dari git, hasil uji, dan catatan deploy.
Jadi setiap field di sini harus membayar ongkosnya sendiri.

**a) Satu formulir, dua kedalaman.** Jenjang **M** hanya boleh diminta
*02 ringkas*: tabel FR + Kriteria Diterima + komponen terdampak. Jenjang **L**
mendapat 02 penuh (ruang lingkup termasuk / **tidak** termasuk, desain teknis,
estimasi & risiko). ⛔ **02 penuh JANGAN pernah muncul untuk M** — itu yang
menenggelamkan tim dua orang. Rancang satu permukaan yang jujur menampilkan
dua kedalaman, bukan dua layar terpisah.

**b) Ada field yang tak boleh dilihat semua orang.** *Estimasi hari kerja*
adalah **internal IT** — tidak tampil ke Executive. Perlu bentuk visual yang
menyatakan "ini catatan dapur", bukan sekadar disembunyikan diam-diam.

**c) Ketergantungan bukan teks bebas.** "Menunggu tiket lain" harus jadi
**relasi ke tiket**, supaya papan bisa menampilkannya. Rancang pemilih tiket,
bukan kotak teks.

**d) UAT adalah gerbang manusia.** Didiamkan **tidak** berarti disetujui — ia
menyala di papan sebagai "bola di tangan pemohon" dan memanas setelah 3 lalu 7
hari. Layar UAT harus terasa seperti pertanyaan yang ditujukan kepadamu, bukan
formulir yang menunggu diisi.

**e) Bahaya terbesar: terlihat seperti PDF.** Templat aslinya dokumen Word
dengan kotak tanda tangan. Kalau rancangannya terasa seperti memindahkan Word
ke layar, orang berhenti mengisinya dalam dua minggu. Ronde 1 sudah
membuktikan bisa lebih baik — lampu gerbang yang menyala sendiri, bukan
checkbox yang diklik. Teruskan sikap itu.

## 4. Yang sudah ada dan WAJIB diikuti

- **Zona GRAFIT** untuk IT, shell tetap cokelat (`peta-tema.md` §3).
- **Sumbu papan = FASE.** Jangan diubah.
- **Badge Fase 1/2/3** di tiap permukaan. Ketiga layar ini **Fase 2**.
- **Gerbang = lampu status yang menyala sendiri** + tombol mati yang menyebut
  field mana yang kurang (pola P3 di `pola-interaksi.md`).
- **Mesin fase `it-store.js` sudah diterjemahkan jadi kode dan jalan di dev.**
  Jangan mengubah `pathFor`, `RANK`, atau aturan panas. Kalau butuh state
  baru, tambahkan — jangan ganti yang sudah ada.
- Ketiga formulir ini **hidup di dalam halaman detail tiket yang sudah ada**,
  bukan halaman berdiri sendiri — kecuali rancanganmu punya alasan kuat
  sebaliknya.

## 5. Yang diharapkan dari handoff

Paket seperti ronde 1: `.dc.html` yang bisa dibuka, store yang menyambung ke
`it-store.js` yang sudah ada, screenshot tiap keadaan kunci, dan README yang
menyebut **apa yang sengaja tidak dikerjakan**. README ronde 1 jujur soal itu
dan sangat menolong — teruskan kebiasaan tersebut.

## 5b. Yang WAJIB ada — bentuknya bebas

1. **Tabel FR** `FR-01..n`: kebutuhan/fungsi · prioritas · **Kriteria
   Diterima**. Minimal **satu baris terisi** untuk membuka gerbang ke
   Development — perlihatkan gerbang itu di layar.
2. **02 ringkas (M) vs 02 penuh (L)** jelas bedanya, dan M tak pernah dipaksa
   mengisi field milik L.
3. **Estimasi hari kerja ditandai internal IT**, tidak tampil ke Executive.
4. **Ketergantungan sebagai relasi ke tiket lain**, bukan teks bebas.
5. **Tabel test case** `TC-01..n`: skenario · hasil diharapkan · hasil aktual ·
   Pass/Fail, dengan ringkasan **terhitung sendiri** (jumlah · pass · fail).
6. **Daftar temuan** bertingkat **Minor/Major**, PIC, status — dan **gerbang
   ke UAT yang menolak selama masih ada Major terbuka**, dengan kalimat yang
   menyebutkan berapa yang menahannya.
7. **Lima pemeriksaan tetap** di uji internal: fungsi utama · validasi & error
   handling · database/API · hak akses · **fitur lama terkait tetap berjalan**.
8. **Skenario UAT** untuk pemohon: proses bisnis · hasil diharapkan ·
   **Sesuai / Tidak** · catatan, plus kotak **Masukan End User**.
9. **Tiga keputusan UAT**: Disetujui Go Live · Disetujui dengan Catatan ·
   Perlu Perbaikan — dan "Perlu Perbaikan" harus jelas **mengembalikan tiket
   ke DEV**, bukan menghentikannya.
10. **Paraf sesuai kesepakatan**: 02 = IT + satu Business Owner · 04 = IT
    sendiri tanpa paraf pihak lain · 05 = pemohon saja. Jangan menambah.
11. **Keadaan kosong** untuk ketiganya. Tiket M yang baru masuk Analisis akan
    lebih sering terlihat kosong daripada terisi — rancang keadaan itu, bukan
    cuma keadaan penuh.

## 6. Angka nyata untuk mengukur rancangan

- Tiket **M** khas: **3-6 baris FR**, **5-10 test case**, **0-3 temuan**.
- Tiket **L**: sampai **12 baris FR**, **20+ test case**.
- Sekitar **5 modul besar per bulan**; sisanya jenjang S yang **tak menyentuh
  layar ini sama sekali**.
- Pengisi FRM-IT-02 cuma **satu orang** (Ekky). Kalau satu tiket M butuh lebih
  dari **10 menit**, rancangannya gagal.
- Pemohon membuka layar UAT mungkin **sekali sebulan**.

## 7. Satu kalimat yang menjelaskan ketiga layar ini

> Tiga tempat di mana manusia harus berpikir — mendefinisikan "selesai",
> membuktikannya, lalu mengakuinya — dibuat seringan mungkin supaya tim dua
> orang benar-benar mengisinya.
