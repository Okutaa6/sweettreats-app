# Runbook Demo — IT Request Tracker (dev)

> Untuk Ekky memandu demo end-to-end di `dev.sweettreats.id`. Ditulis chat HUB
> 4 Sep 2026 dari kode yang hidup di dev (`feat/it-tracker`, 99 uji).
> **Diperbarui 4 Sep malam**: dev sudah diisi 30 tiket antrian nyata. Bagian
> 1–3 untuk **memahami & menjawab pertanyaan**, bagian 4 untuk **dijalankan**.
> Durasi jalur penuh ±40 menit; jalur pendek 3 menit.

## 0. Persiapan (malam sebelumnya)

**URL** `https://dev.sweettreats.id` — login di **`/signin`** (bukan `/login`).

| Peran dalam demo | Akun | Email |
|---|---|---|
| Pemohon (Head Marketing) | **Yovan** | creative@stdc.id |
| Head IT | **Muhamad Ikhsan** — ini kamu | ikhsan@sweettreats.id |
| IT / PIC pengerja | **Reza** | reza@sweettreats.id |
| Business Owner (BO) | **Robi** (Executive) | robi@stdc.id |
| BO cadangan | **Hari** (Konsultan Marketing) | hari@stdc.id |

- **Rahmat (Konsultan IT) tidak ada di dev** — pakai Hari sebagai persona Konsultan.
- Password bersama disetel sendiri (skrip sudah diberikan). ⛔ **Jangan buat akun
  baru** — membuat user menyeret aturan kerja absensi (riwayat alpa palsu 2×).
- Pakai **dua browser** (normal + incognito) supaya berganti peran tanpa logout.
- **Panaskan dulu**: buka `/it-tracker` dan `/it-tracker/permintaan` sekali sebelum
  demo. Muatan pertama sesudah deploy pernah galat transien.
- ⚠️ **Dev SUDAH TERISI 30 tiket antrian nyata** (seeder `it:seed-antrian`, 4 Sep):
  26 aktif · 4 ditunda · 3 macet menunggu di luar IT. Papan **tidak kosong** —
  itu memang tujuannya. Tiket baru yang kamu buat saat demo bernomor
  **IT-2026-031** dan seterusnya.
- **Jangan hapus 30 tiket seed.** Seeder idempoten; kalau dijalankan ulang ia
  memperbarui, tidak menggandakan, dan **tidak memundurkan fase** tiket yang
  sudah kamu gerakkan saat demo. Tiket demo (031+) boleh dihapus sesudahnya.
- Ada **dua momen (★)** yang butuh Robi/Hari. Kalau mereka tak ada, **jangan
  dipaksa** — tunjukkan tombol yang menolak Head IT. Penolakan itu justru fiturnya.

## 1. Apa yang didemokan — satu kalimat

> Permintaan ke IT yang selama ini lewat WhatsApp dan lisan kini punya **jejak,
> urutan, dan definisi "selesai" yang disepakati sebelum koding** — dan siapa pun
> bisa melihat IT sedang memikul apa.

Kalau ditanya "kenapa jadi lebih repot?": **beban ini disengaja.** Formulir memaksa
pemohon ikut berpikir; antrean yang terlihat jadi alat tawar. Yang dikurangi
bukan bebannya, tapi **bagian yang bisa diisi mesin** — itu inti bagian 3.

## 2. Istilah yang akan muncul di layar

**Tiket** — satu permintaan. Nomor `IT-2026-031` (berurut sejak 001), dipakai di ketujuh
formulir **dan di baris pertama commit message** — jadi git bisa dilacak ke
tiket, dan sebaliknya.

**Jenjang S / M / L** — *ukuran* tiket. **Ditentukan IT saat triase, bukan
pemohon.** Menentukan fase mana yang dilewati:

| Jenjang | Untuk | Fase yang dilewati |
|---|---|---|
| **S** | bug, tweak, < 1 hari | Requirement → Development → selesai (2) |
| **M** | fitur | + Analisis, UAT, Go Live (5) — *tanpa* Uji Internal & Evaluasi |
| **L** | modul, integrasi | ketujuhnya (7) |

**Tujuh fase** — Requirement · Analisis · Development · Uji Internal · UAT · Go
Live · Evaluasi. Sebelum masuk jalur, tiket permintaan lewat **Triase** lalu
**Antre**. Tiket bisa **Ditunda** (alasan wajib, tetap tampil) atau **Ditolak**.

**Gerbang** — syarat pindah fase. Tampil sebagai **lampu yang menyala sendiri**
saat syaratnya terpenuhi, bukan checkbox untuk dicentang. Tombol lanjut yang
mati **menyebut apa yang kurang**.

**Menunggu siapa · berapa hari** — **dihitung**, bukan diisi. Memanas (kuning
≥ 3 hari, merah ≥ 7) **hanya kalau yang menunggu di luar IT** — pemohon di UAT,
Executive di Go Live. Tiket 30 hari yang duduk di Development **tidak** memanas.
Papan ini menagih orang lain, bukan menghakimi IT.

**Bobot 1–5** dan **Prioritas IT P1–P3** — diisi IT saat triase. **Terpisah** dari
"prioritas menurut pemohon" (Rendah/Sedang/Tinggi). Keduanya tersimpan dan
tampil berdampingan.

**Business Owner (BO)** — Executive **atau** Konsultan. Memparaf Analisis (02) dan
meng-ACC Go Live (06). **Bukan IT** — Head IT pun ditolak.

**PIC** — orang IT yang mengerjakan (Reza). Hanya bisa menggerakkan tiket
miliknya, hanya di Development/Uji. Tidak bisa triase, tidak menetapkan
prioritas.

**Tiga kelas formulir** —
**A** (01·05·07) diisi pemohon/evaluator, bahasa manusia ·
**B** (03·04·06) **terisi sendiri** dari git, hasil uji, catatan deploy — IT
tinggal mengonfirmasi ·
**C** (02) harus benar-benar dipikirkan.

**Sumber tiket** — *permintaan* (pemohon → Triase) · *inisiatif IT* (→ Antre,
jenjang wajib saat dicatat) · *insiden* (→ Development langsung, formulir
menyusul).

**Catatan dapur** — estimasi & risiko internal IT. Non-IT melihat strip *"ada
catatan dapur IT — tidak ditampilkan"* — dinyatakan, bukan disembunyikan
diam-diam.

## 3. Kenapa berbeda dari templat — apa yang diubah, dan alasannya

Dasarnya templat **FRM-IT-01..07** (`Template_Development_Sweet_Treat.pdf`,
pemilik Konsultan IT & Management). Prinsipnya: **kata-kata dimanusiakan, isi
tidak dikurangi.** Semua field templat ada. Yang berubah adalah *siapa yang
mengisi, kapan, dan berapa banyak yang harus diketik manusia.*

| # | Di templat | Di sistem | Kenapa |
|---|---|---|---|
| 1 | Tujuh formulir, semua diisi manual | **Tiga kelas.** Kelas B (03/04/06) **terisi sendiri** dari git, hasil uji, dan catatan deploy | Ketiganya mendokumentasikan pekerjaan yang **sudah** dilakukan. Kalau diketik ulang, orang berhenti mengisi dalam dua minggu — di situlah sistem serupa mati |
| 2 | Semua permintaan lewat tujuh formulir penuh | **Jenjang S/M/L.** S cuma 2 fase; M lompati Uji & Evaluasi dan cukup **02 ringkas** | Tim IT dua orang, ±5 modul besar/bulan, sisanya kecil. 02 penuh untuk tiket M = tenggelam |
| 3 | Tiga paraf di hampir tiap tahap | **1–2 paraf.** 01 pemohon+IT · 02 IT + **satu** BO · 03/04 **IT sendiri** · 05 **pemohon saja** · 06 IT+BO · 07 evaluator | Di tim dua orang, tiga paraf = orang yang sama meneken kotak berbeda. Itu teater, bukan kendali |
| 4 | Identitas pemohon diketik | **Otomatis dari akun.** Ekspor tetap menampilkannya dengan nama bagian resmi templat | Tak perlu ditanya, tapi dokumen tercetak tetap dikenali Konsultan |
| 5 | *(tidak ada)* | **"Menunggu siapa · berapa hari"** dihitung dari fase + tanggal masuk; memanas hanya di luar IT | Satu-satunya angka yang membuat "IT lambat" bisa dibantah dengan data. Ini jantung sistemnya, dan tak ada di PDF |
| 6 | Prioritas satu kolom | **Dua kolom**: menurut pemohon, dan menurut IT | Pemohon boleh bilang "tinggi"; IT yang menetapkan nyata. Keduanya tersimpan, tak ada yang ditimpa |
| 7 | Kriteria diterima tersirat | **Wajib ≥ 1 FR ber-Kriteria Diterima** untuk masuk Development | Memaksa "selesai" didefinisikan **sebelum** koding. Field terpenting di seluruh sistem |
| 8 | Ketergantungan = teks bebas | **Relasi ke tiket lain**, termasuk yang ditunda | Papan bisa menampilkannya. Bergantung pada tiket ditunda = terblokir tanpa batas — harus terlihat |
| 9 | Paraf = kotak tanda tangan | **Baris berstempel waktu**: *"✓ Diparaf Robi · 3 Sep 14:20"* | Siapa + kapan adalah nilai auditnya. Tanpa teater tanda tangan |
| 10 | Estimasi tampil ke semua | **Catatan dapur** internal IT; non-IT diberi tahu bahwa ada, tidak ditampilkan | Executive tak perlu angka hari kerja; tapi menyembunyikan diam-diam itu tidak jujur |
| 11 | 03-C "developer testing" berdiri sendiri | **Dilebur ke 04** | Formulir yang sama diisi dua kali oleh tim dua orang |
| 12 | Pemeriksaan uji bebas | **TC-01..05 tetap & terkunci**: fungsi utama · validasi/error · DB/API · hak akses · **fitur lama tetap jalan** | Yang terakhir menangkap kerusakan diam-diam. Terkunci = tak bisa dihapus, tapi tetap diisi & terhitung |
| 13 | Gerbang ke UAT tersirat | **Nol temuan Major terbuka**; tombol menolak dengan **menyebut jumlahnya** | *"1 temuan Major masih terbuka — menahan gerbang"* — orang tahu persis apa yang ditunggu |
| 14 | UAT didiamkan = ? | **Menyala di papan dan memanas.** Tidak pernah otomatis diterima | Ini gerbang manusia. Kemacetan di pemohon harus terlihat, bukan disembunyikan |
| 15 | Go Live checklist | **Dipertahankan** (formulir paling matang) + ditambah *"curl beranda 200 + URL akhir"* | Pelajaran nyata: prod pernah mati 6 menit dengan `laravel.log` bersih — log sepi bukan bukti sehat |
| 16 | Evaluasi tanpa pemicu | **Pemicu terjadwal 14 hari** (Fase 3) + **Rencana Tindak Lanjut → "Jadikan tiket"** | Tanpa pemicu tahap ini tak pernah terjadi. Tindak lanjut yang melahirkan tiket **menutup lingkaran** |
| 17 | Nomor dokumen per formulir | **Satu nomor tiket** di tujuh formulir **dan di commit message** | Kelas B terisi dari sini: commit ber-`IT-2026-001` muncul sendiri di FRM-IT-03 |

## 4. Jalannya demo — langkah per langkah

Tiket utama **jenjang L** supaya ketujuh fase terlihat. Ditutup dengan satu
tiket **S** tiga menit untuk memperlihatkan jalur pendek — karena begitulah
mayoritas tiket nyata.

Format tiap langkah: **Siapa** · **Buka** · **Lakukan** · **Yang tampak** ·
**Katakan**.

### L0 — Papan dengan beban NYATA
**Ekky** (Muhamad Ikhsan) · `/it-tracker`
Lakukan: tunjukkan papan, legenda badge fase, panel *"Beban IT saat ini"*.
Yang tampak: **26 item aktif · 3 macet menunggu di luar IT**; kolom Masuk 7,
Rancang 3, Kerjakan 6, Rilis 5. Empat tiket ditunda tampil dengan alasannya.
Katakan: *"Sumbu papan ini fase, bukan orang. 'Menunggu siapa' ada di dalam
kartu."*
Lalu **tunjuk dua kartu ini berdampingan** — inilah inti papan:
· **IT-2026-025** (detail pasien) di *Development* **72 hari** — tetap **dingin**.
· **IT-2026-007** (akun dokter) di *UAT* **21 hari** — **merah**.
Katakan: *"Yang lama di tangan IT tidak memerahkan papan. Yang memerah cuma
yang menunggu di luar IT. Papan ini menagih orang lain, bukan menghakimi kami."*
Lalu klik **Lihat yang macet** — 3 tiket, semuanya menunggu di luar IT.

### L1 — Yovan mengajukan (FRM-IT-01, Kelas A)
**Yovan** · `/it-tracker/permintaan` → *Ajukan permintaan*
Lakukan: isi **judul**, jenis permintaan, latar belakang, kebutuhan & hasil yang
diharapkan, pengguna terdampak, target waktu, **prioritas menurut pemohon**,
dan **lampirkan satu screenshot**. Kirim.
Yang tampak: tiket **IT-2026-031**, status *Triase*, *"Menunggu triase IT"*.
Halaman Yovan menampilkan tiketnya sendiri.
Katakan: *"Nama, jabatan, cabang, kontak — tak satu pun ditanya. Semua dari
akun. Tapi nanti di ekspor semuanya tercetak."* Lalu buka ekspor tiket itu
sebentar: identitas terisi, lampiran tercetak.

### L2 — Ekky triase
**Ekky** · `/it-tracker` → tiket di kolom Triase → buka → **Triase**
Lakukan: jenjang **L**, bobot (1–5), prioritas IT **P2**, PIC **Reza**.
Yang tampak: tiket pindah ke *Antre*; **prioritas pemohon dan prioritas IT
tampil berdampingan**.
Katakan: *"Jenjang ditentukan IT, bukan pemohon. Dua prioritas ini sengaja tidak
disatukan."* Tunjuk tombol *Tunda / Tolak* — alasan wajib, tiket ditunda tetap
tampil. Jangan dipakai.

### L3 — Analisis (FRM-IT-02 penuh, Kelas C)
**Ekky** · detail tiket → *Tarik → Analisis*
Lakukan, berurutan:
1. Tunjukkan **keadaan kosong**: banner *02 PENUH · L*, teks pengajar *"Mulai dari
   yang paling penting…"*, baris hantu.
2. Ketik **FR-01** + **Kriteria Diterima** → lampu baris menyala hijau. Tambah
   FR-02 lewat baris hantu (Enter).
3. Centang **komponen terdampak**.
4. Isi **ruang lingkup termasuk / tidak termasuk** (hanya muncul karena L —
   sebut: *"tiket M tidak akan melihat blok ini"*).
5. Isi **catatan dapur** (estimasi hari) — lalu nanti tunjukkan dari akun Yovan:
   strip *"ada catatan dapur IT — tidak ditampilkan"*.
6. (Opsional) **Menunggu tiket lain** — pemilih tiket.
Yang tampak: gerbang *Development* — lampu ≥1 FR ✓, komponen ✓, lingkup ✓,
**paraf BO ✗**. Tombol mati: *"Masih perlu: paraf satu Business Owner"*.
Lakukan: coba klik **Paraf** sebagai Ekky → **403**.
Katakan: *"Ini disengaja. Satu-satunya syarat gerbang ini yang datang dari luar
IT."* ★ **BUTUH BO — momen 1.**

### L4 — Robi memparaf ★
**Robi** · detail tiket → **Paraf sebagai Business Owner**
Yang tampak: *"✓ Diparaf Robi (Business Owner) · tanggal jam"* — tanpa kotak
tanda tangan. Lampu paraf menyala; tombol *Buka gerbang → Development* hidup.
Katakan: *"Robi tidak bisa memparaf analisis kosong — tombolnya menolak kalau
lampu lain belum menyala. Dan kalau sesudah ini Ekky mengubah FR, parafnya
gugur — Robi diminta menyetujui ulang."*

### L5 — Buka gerbang → Development (FRM-IT-03, Kelas B)
**Ekky** · *Buka gerbang → Development*
Yang tampak: fase *Development*, *"Di tangan IT"* — **tidak memanas** walau
lama. Bagian FRM-IT-03: branch/commit **kosong** (belum ada commit ber-nomor
tiket) + tombol *Segarkan jejak git*.
Katakan: *"Begitu ada commit yang baris pertamanya menyebut IT-2026-031, ia
muncul di sini sendiri tiap pagi 06:40. Tak ada yang mengetik ulang."*
Lakukan: isi **konfirmasi FRM-IT-03** (catatan implementasi: aplikasi/DB/API/
hak akses/dokumentasi).

### L6 — Reza menandai siap uji → Uji Internal
**Reza** · `/it-tracker` → tiket miliknya → *Siap uji → Uji Internal*
Katakan: *"Reza hanya bisa menggerakkan tiket yang dia PIC-nya, hanya di
Development dan Uji. Dia tidak bisa triase."* (Tunjuk: tombol triase tak ada.)

### L7 — Uji Internal (FRM-IT-04, Kelas B)
**Reza** atau **Ekky** · bagian FRM-IT-04
Yang tampak: **TC-01..05 sudah ada, bergembok.**
Lakukan:
1. Coba hapus TC-05 → ditolak. Katakan: *"Lima pemeriksaan tetap. Yang kelima —
   fitur lama tetap jalan — yang paling sering menyelamatkan."*
2. Isi *hasil aktual* + **Pass** untuk TC-01..05. Tambah TC-06 lewat baris hantu.
3. Tambah **temuan Major** (PIC Reza, Terbuka).
Yang tampak: empat angka terhitung (TC · Pass · Fail · Major terbuka); tombol
*Lanjut → UAT* **mati**: *"1 temuan Major masih terbuka — menahan gerbang"*.
4. Ubah status temuan → **Selesai** → tombol hidup.

### L8 — Skenario UAT ditulis IT → UAT
**Reza** atau **Ekky** · bagian FRM-IT-05 (sisi IT)
Lakukan: tulis **≥ 1 skenario** dari sudut pandang pemohon (*proses bisnis* +
*hasil seharusnya*). Lalu *Lanjut → UAT*.
Yang tampak di papan: *"Menunggu Yovan (UAT)"* — **ini keadaan yang memanas**.
Katakan: *"Setelah 3 hari kuning, 7 hari merah — karena yang menunggu di luar
IT. Ini angka yang membuat 'IT lambat' bisa dijawab dengan data."*

### L9 — Yovan menjawab UAT (FRM-IT-05, Kelas A)
**Yovan** · `/it-tracker/permintaan` → *bola di tanganmu* → tiket
Yang tampak: kartu besar **"Yovan, apakah ini yang kamu minta?"** — bukan
formulir. Enam fase lain menyusut jadi rel tipis.
Lakukan: jawab **Sesuai / Tidak** per skenario, isi *Masukan End User*. Tunjuk
tiga keputusan; sebut *Perlu Perbaikan* mengembalikan tiket ke Development.
Pilih **Disetujui dengan Catatan** + satu catatan.
Yang tampak: catatan jadi **daftar tindak lanjut** di tiket (dengan tombol
*Jadikan tiket*); fase *Go Live*; papan: *"Menunggu Executive (ACC Go-Live)"*.

### L10 — Go Live (FRM-IT-06, Kelas B)
**Ekky** · bagian FRM-IT-06
Yang tampak: **checklist pra-deploy** — *UAT disetujui* dan *File/script siap*
**menyala sendiri**; sisanya (backup · akses & konfigurasi · panduan · rollback)
diisi Ya / N-A.
Lakukan: isi sisanya; **catatan deployment** (waktu · aktivitas · hasil);
**pemeriksaan pasca** 5 baris — yang pertama *"Aplikasi dapat diakses — curl
beranda 200 + URL akhir"* → Pass semua.
Lakukan: klik **ACC Go Live** sebagai Ekky → **403**.
Katakan: *"Formulir ini yang paling matang di templat, dipertahankan apa adanya.
Baris pertama pemeriksaan pasca itu pelajaran nyata — prod pernah mati enam
menit dengan log bersih."* ★ **BUTUH BO — momen 2.**

### L11 — Robi meng-ACC Go Live ★
**Robi** · *Setujui sebagai Business Owner* → lalu **Ekky**: *Lanjut → Evaluasi*.

### L12 — Evaluasi (FRM-IT-07, Kelas A)
**Yovan** (evaluator bawaan = pemohon) · tiket → kartu *"bagaimana rasanya
memakai ini?"*
Lakukan: nilai **lima aspek** 1–5 (rata-rata terhitung); tambah satu
*Enhancement* + **Rencana Tindak Lanjut**.
Lalu **Ekky**: klik **Jadikan tiket** pada tindak lanjut itu.
Yang tampak: **IT-2026-032 lahir di Triase**, pemohon sama, badge *"Lahir dari
IT-2026-031 · Evaluasi"*, chip *↳ dari IT-2026-031* di papan.
Katakan: *"Ini yang menutup lingkaran. Evaluasi tidak berakhir di dokumen — ia
melahirkan permintaan berikutnya."* Lalu *Tutup → Selesai*.

### L13 — Jalur pendek: tiket S (3 menit)
**Ekky** · catat **insiden** (judul + jenjang S) → langsung *Development*
Lakukan: isi **catatan singkat pengerjaan** → *Tandai selesai*.
Katakan: *"Tanpa formulir, tanpa paraf, tanpa BO. Begitulah mayoritas tiket."*

### L14 — Penutup: papan & beban
**Ekky** · `/it-tracker`
Yang tampak: 2–3 tiket di kolom berbeda, panel beban terisi, filter **Macet**,
chip *↳ dari*. Buka **ekspor FRM-IT-01** IT-2026-031 sekali lagi.

## 5. Pertanyaan yang mungkin muncul

**"Dulu tinggal WA, kenapa sekarang harus isi formulir?"**
Karena WA tak punya definisi "selesai". Bebannya disengaja — tapi hanya di
bagian yang memang butuh manusia berpikir. Tiket kecil (S) tak lewat formulir
sama sekali.

**"Kenapa Ekky tidak bisa memparaf sendiri?"**
Paraf itu satu-satunya syarat gerbang yang datang dari luar IT. Kalau IT bisa
menekennya sendiri, gerbangnya hiasan. *(Sedang disiapkan: Head IT boleh
melewati **tapi tercatat** dan tampil sampai ekspor — untuk saat BO lama.)*

**"Kalau Robi lama?"**
Papan memanas dan **menunjuk Robi**, bukan IT. Itu bukti, bukan aib.

**"Ini menggantikan Project Tracker?"**
Tidak — dua hal berbeda. Fase 3 akan menyambungkannya: tiket yang lolos
Analisis melahirkan project di sana; fase tetap di sini.

**"Kapan ke prod?"**
Jujur: pemicunya belum ditulis. Demo ini salah satu masukannya.

## 6. Sesudah demo

- Catat **tiga hal**: berapa menit Ekky mengisi 02 (target < 10 untuk M), momen
  mana yang bikin penonton bingung, dan apakah Robi/Hari nyaman dengan perannya.
- Putuskan nasib tiket demo (buang / simpan).
- Laporkan ke chat HUB — masukan ini menentukan apakah jalan pintas paraf
  perlu dibangun sama sekali.
