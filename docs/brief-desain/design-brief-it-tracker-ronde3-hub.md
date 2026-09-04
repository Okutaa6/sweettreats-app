<!-- CATATAN GABUNGAN (6 Sep): dua chat menulis brief RONDE 3 pada hari yang
sama tanpa saling tahu. Berkas ini versi chat HUB (detail tiket & papan +
temuan "filter aktif tak terlihat"); versi chat build ada di
design-brief-it-tracker-ronde3.md. Keduanya dipertahankan apa adanya —
paket desain r3 yang datang menjawab keduanya. -->

# Design Brief — IT Request Tracker, RONDE 3 (detail tiket & papan)

> Untuk claude.ai/design. **Ronde perbaikan**, bukan fitur baru. Lampirkan
> paket ronde 2: `IT Request Tracker.dc.html`, `Sidebar.dc.html`, `it-store.js`,
> `support.js`. Konteks: `docs/rencana/rencana-it-tracker.md` §14.13 (tujuh
> keputusan desain **TERKUNCI** — jangan dibatalkan). Ikon **FontAwesome 5**.

## 0. Kenapa ada ronde 3

Fase 1, 2a, dan 2b sudah **dibangun dan hidup di dev** dengan **30 tiket
antrian nyata**. Untuk pertama kalinya kita melihat layarnya dengan beban
sungguhan, bukan seed mockup — dan dua hal langsung terasa (kata Ekky sendiri):

1. **Halaman detail tiket terlalu panjang.** Tujuh fase ditumpuk vertikal.
   *"Kalau sudah di Evaluation harus scroll ke ujung banget, ya ampun."*
2. **Papan terlalu banyak ruang kosong**, dan kolom-kolomnya kurang menonjol.

Ronde ini **tidak menambah field, tidak mengubah alur, tidak menyentuh mesin
fase.** Ini soal susunan dan kepadatan.

## 1. Angka nyata — rancang untuk sebaran INI, bukan yang dibayangkan

Diukur di dev 4 Sep, 30 tiket:

| Fase | Isi | | Fase | Isi |
|---|---|---|---|---|
| Triase | **8** | | Uji Internal | **0** |
| Antre | **4** | | UAT | **4** |
| Analisis | **4** | | Go Live | **0** |
| Development | **6** | | Evaluasi | **1** |
| | | | Ditunda | **4** |

⚠️ **Dua dari tujuh fase kosong, dan itu keadaan normal**, bukan kebetulan.
Uji Internal hanya dilalui jenjang L; Go Live cuma dihuni beberapa hari.
Rancangan yang memberi tiap fase kolom setinggi layar akan **selamanya**
memperlihatkan lubang. Itu sumber "banyak empty space" yang Ekky keluhkan.

**Sebaran panjang halaman detail** (baris blade, indikasi seberapa jauh
scroll-nya): Analisis 315 · UAT 224 · Uji 170 · Go Live 163 · Evaluasi 189 —
plus kerangka 390. Tiket L menampilkan **semuanya sekaligus**.

## 2. Masalah 1 — detail tiket: maraton scroll

**Kenyataannya, dari tujuh fase hanya SATU yang sedang dikerjakan.** Sisanya:
sudah lewat (tinggal dibaca) atau belum sampai (kosong). Sekarang ketiganya
mendapat ruang yang sama.

Yang sudah ada dan **berhasil** — pertahankan:
- **Rel fase 1–7 di atas** (lingkaran bernomor, yang lewat ✓ hijau, yang
  sekarang gelap). Sekarang ia **hiasan**; jadikan **navigasi**.
- **Rail kanan "Langkah berikutnya"** dengan lampu gerbang + tombol mati yang
  menyebut kekurangannya. Ini bagian terbaik layar itu.

**Tantangannya:** buat fase yang sedang berjalan **langsung terlihat tanpa
scroll**, sementara riwayat tetap bisa dibuka. Beberapa arah yang mungkin —
pilih dan pertajam, jangan kerjakan semuanya:
- fase lampau menyusut jadi **satu baris ringkasan** yang bisa dibuka
  (*"✓ Analisis · selesai 22 Agu · diparaf Robi · 4 FR"*);
- rel fase jadi **tab**: klik 5 → loncat ke UAT tanpa scroll;
- fase yang **tidak dilewati jenjangnya** dihilangkan sama sekali — tiket **S
  cuma punya 2 fase** (`REQ → DEV`), memperlihatkan 7 itu berbohong;
- rail kanan **menempel (sticky)** saat menggulir.

⚠️ **Jangan** menjadikannya wizard satu-fase-per-halaman. Nilai layar ini
justru satu tiket = satu halaman yang bisa dibaca utuh dan **dicetak**.

## 3. Masalah 2 — papan: ruang kosong & kolom kurang menonjol

Papan mengelompokkan tujuh fase jadi empat: **Masuk · Rancang · Kerjakan ·
Rilis**. Dua sub-fase hampir selalu kosong (lihat §1).

Yang perlu dipecahkan:
- **Sub-fase kosong tak boleh memakan tinggi penuh.** Susutkan jadi strip
  tipis, atau gabungkan, atau sembunyikan sampai terisi — pilihanmu.
- **Kepala kolom kurang menonjol.** Ekky ingin Masuk/Rancang/Kerjakan/Rilis
  lebih tegas sebagai empat wilayah. Angkanya sekarang badge kecil.
- **Kartu terlalu longgar** untuk 30 tiket. Rapatkan tanpa membuang strip
  "menunggu siapa · berapa hari" — itu jantungnya.

⛔ **Sumbu papan tetap FASE.** Keputusan ronde 1, jangan diubah.

## 4. Masalah 3 — filter aktif tak terlihat (temuan 4 Sep)

Papan punya tab **Permintaan / Inisiatif IT** dan tombol **S / M / L**.
Saat Ekky membukanya, kolom **KERJAKAN menulis 0 padahal ada 6 tiket** di
Development — keenamnya bersumber *insiden*, dan tab "Permintaan" membuangnya.
Angka `0` polos terbaca *"tak ada yang dikerjakan"*, padahal artinya *"tak ada
yang cocok filter"*. Itu salah paham yang mahal di depan Executive.

**Yang harus dirancang:**
1. Filter yang **aktif** terlihat aktif — jelas, bukan sekadar tombol lebih tua.
2. Kolom yang kosong **karena filter** berbicara: *"0 yang cocok — 6 tiket
   Development ada di tab Inisiatif IT"*, dengan jalan keluar sekali klik.
3. Cara **mematikan semua filter** yang selalu terlihat saat ada yang menyala.

## 5. Yang WAJIB dipertahankan (jangan dirancang ulang)

- **Tujuh keputusan §14.13**: kedalaman 02 otomatis · UAT di dalam detail ·
  baris hantu tanpa tombol Tambah · catatan UAT jadi tindak lanjut · TC-01..05
  terkunci · paraf = baris berstempel waktu · pemilih ketergantungan termasuk
  yang ditunda.
- **Gerbang = lampu yang menyala sendiri**, bukan checkbox. Tombol mati
  menyebut apa yang kurang.
- **"Menunggu siapa · berapa hari"**, memanas **hanya** bila penunggunya di
  luar IT (≥3 kuning, ≥7 merah). Kartu 72 hari di Development **tetap dingin**
  — itu bukan bug, itu tesisnya.
- **Zona GRAFIT** untuk IT, shell cokelat. Badge Fase 1/2/3.
- **`it-store.js`**: `pathFor`, `RANK`, aturan panas, `gateInfo` — semuanya
  sudah diterjemahkan ke PHP dan lulus **99 uji**. Boleh **menambah**, jangan
  mengubah bentuk yang ada.

## 6. Yang diharapkan dari handoff

Paket seperti ronde 2, **plus dua keadaan yang selama ini tak pernah dirancang**:
- **papan dengan 30 tiket** (sebaran §1, termasuk dua kolom kosong);
- **detail tiket L di fase Evaluasi** — kasus terburuk scroll;
- **detail tiket S** (hanya 2 fase) — kasus terpendek;
- papan dengan **filter menyala** dan satu kolom kosong karenanya.

README yang menyebut apa yang **sengaja tidak dikerjakan** — kebiasaan ronde 1
dan 2 yang sangat menolong.

## 7. Angka untuk mengukur rancangan

- Membuka tiket yang sedang di **Evaluasi**: fase berjalan harus terlihat
  **tanpa scroll sama sekali** di layar laptop 1080p.
- Papan 30 tiket: keempat kepala kolom terlihat **dalam satu layar**.
- Tinggi kartu turun **minimal 25%** tanpa kehilangan strip menunggu-siapa.
- Tiket **S** tidak pernah memperlihatkan fase yang tak dilaluinya.

## 8. Satu kalimat

> Satu tiket, satu halaman — tapi yang sedang dikerjakan ada di depan mata, dan
> yang sudah lewat cukup satu baris.
