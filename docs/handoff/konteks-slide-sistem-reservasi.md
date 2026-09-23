# Konteks: 1–2 slide "keadaan SISTEM reservasi" (permintaan Robi)

> Untuk chat deck. Bilang saja: **"baca `docs/handoff/konteks-slide-sistem-reservasi.md`"**.
>
> ⚠️ **Ini bukan daftar perintah.** Ini penjelasan konteks — apa yang diminta,
> kenapa, dan apa yang sudah kita ketahui. Bentuk akhirnya keputusan kalian
> bersama Ekky. Angka lengkap: `docs/handoff/data-reservasi-2026-09-11.md`.

---

## 1. Apa yang sebenarnya terjadi — miskom, bukan deck yang jelek

Robi melihat deck lalu berkata:

> *"Gue minta 1 page yang on point aja yang ngegambarin Fitur Reservasi yang lagi
> lo buat. Deck lo panjang bgt dan tdk menggambarkan sistem nya. Tapi lo lebih
> nampilin kondisi yang Reservasinya."*

Dia benar, dan ini bukan soal deck yang buruk. **Dua pertanyaan yang berbeda:**

| | |
|---|---|
| Yang deck kita jawab | *"Keadaan reservasi bagaimana?"* — volume, no-show, kapasitas, retensi |
| Yang Robi tanyakan | *"Sistemnya seperti apa?"* — apa yang dibangun, alurnya, terpakai sejauh mana |

Data reservasi adalah **alat ukur**, bukan **subjek**. Pemisahan ini sudah
tertulis di `data-reservasi-2026-09-11.md` **§23** (deck sistem vs deck klinik) —
yang belum ada cuma halaman yang menggambarkan sistemnya sendiri.

## 2. Robi mengirim sebuah BENTUK, bukan isi

Mockup-nya (satu halaman, gelap, judul "GO LIVE · Reservasi") berisi kotak:

```
TUJUAN BISNIS  ·  ALUR UTAMA (5 langkah)  ·  TAMPILAN APLIKASI
DATA & INTEGRASI  ·  KINERJA (3 angka)  ·  GAP UTAMA  ·  KEPUTUSAN STRATEGIS
```

Angkanya masih placeholder: `[__]%`, `[__] reservasi/bulan`.

**Keputusan Ekky & HUB: ikuti BENTUKNYA, ganti ISINYA.**

Alasannya: bentuk itu yang dia kenali, jadi dia langsung melihat kita menjawab
pertanyaan **dia**. Tapi placeholder-nya adalah **dugaannya** tentang isi sistem,
dibuat sebelum melihat data kita — dan tiga di antaranya keliru (§3).

⛔ **Jangan memaksa data agar cocok dengan tebakannya.** Kalau kita menyerahkan
halaman yang pas dengan dugaannya, dia akan mengambil keputusan di atas tiga
fakta yang salah, dan kitalah yang menyediakannya.

⚠️ Tapi **jangan juga mengganti diam-diam**. Kalau kotak "WhatsApp" hilang tanpa
penjelasan, dia akan mengira kita lupa. Tulis terang-terangan sebagai temuan:
*"WhatsApp: belum ada sama sekali."* Itu jawaban, bukan penghapusan.

## 3. ⛔ TIGA hal di mockup Robi yang keliru — ini bagian terpenting

### (a) "WhatsApp" di kotak DATA & INTEGRASI — **tidak ada**

Modul `Notifikasi` di katalog berstatus **rencana**, dan catatannya berbunyi:
> *"Hari ini TAK ADA satu pun pemberitahuan keluar aplikasi."*

Nol notifikasi keluar — bukan cuma WhatsApp. Memajang ikonnya sebagai integrasi
hidup akan dibantah orang pertama yang menanyakannya.

**Yang BENAR-BENAR terintegrasi**: Data Pasien · Master Jadwal & Layanan ·
**Omnicare** (rekonsiliasi otomatis tiap malam 03:00) · Laporan/Komisi.

### (b) "Belum ada waiting list otomatis" — waiting list-nya **ADA**

132 entri, dan **naik**: Jul 43 · Agu 58. Yang benar bukan "belum ada",
melainkan **"ada tapi lemah"** — cuma **31%** (41 dari 132) berujung kunjungan.

Dan kita tahu kenapa: utilisasi jadwal **~20–24%**, jadi **tak ada kelangkaan
slot yang layak diperebutkan**. Waiting list adalah alat untuk kapasitas penuh;
kapasitas kita longgar.

### (c) "Funnel reservasi → kunjungan belum terlihat" — **TERBALIK**

Funnel-nya **sudah terukur otomatis tiap malam** oleh `omnicare:reconcile`:

| | |
|---|---|
| Cakupan | **98,6%** dari sisi reservasi |
| Mutu jodoh | **95,9% `exact`** |
| Konsistensi | jalan **14 dari 14 hari**, nol bolong |
| Pendapatan nyata tertaut | **Rp 2,61 miliar** |

Yang tidak ada bukan datanya — melainkan **pembacanya**: halaman `/reconcile`
bisa diakses **1 dari 31 grup, dan grup itu NOL pengguna**. 1.061 no-show dan
108 konflik mengantre sejak Juni tanpa pernah dibuka siapa pun.

⭐ **Perbedaan ini menentukan arah keputusan.** *"Belum terlihat"* mengarah ke
**bangun laporan** (proyek). *"Sudah terukur tapi tak ada yang membuka"*
mengarah ke **beri akses** — satu SQL, bukan proyek.

## 4. Angka siap pakai untuk kotak KINERJA

| kotak | isi terukur |
|---|---|
| **Usage** | **~1.500 reservasi/bulan** (Jun 1.243 · Jul 1.476 · **Agu 1.578**) |
| **Outcome No-show** | **18,2%** |
| **Adoption** | perlu diputuskan artinya — lihat di bawah |

⛔ **No-show BUKAN 25%.** Angka 25–28% yang sempat beredar **memuat 385
reservasi yang DIBATALKAN** dan ikut dicap `orphan_noshow`. Pembatalan bukan
ketidakhadiran — pasiennya memberi tahu. Termasuk batal 26,8%; tanpa batal
**18,2%**.

ℹ️ Dan kalau ada yang bertanya "berapa ruginya": **59% dari yang tidak datang
KEMBALI dalam 60 hari** (≤14 hr 31,6% · 15–30 hr 15,0% · 31–60 hr 12,6%). Itu
pendapatan **tertunda**, bukan hilang.

### "Adoption" ambigu — dua arti, dua jawaban

- **Kalau artinya "berapa bagian kegiatan klinik lewat sistem ini"** → jawabannya
  kuat: sejak Juni **reservasi ≈ kunjungan nyata 1:1** (Jul 1.259 vs 1.260 ·
  Agu 1.332 vs 1.330). Praktis **seluruh** operasi klinik.
- **Kalau artinya "berapa staf yang memakainya"** → sampai 10 Sep kita **tak
  punya angkanya**. Sejak `created_by` naik prod 11 Sep, **kini bisa dihitung**
  (sore pertama sudah terekam Firda Mareta Naskah, Della Raqisiah, Cici Nurjanah,
  Sales Sunter). Kalau butuh angkanya untuk deck, minta HUB mengukur.

## 5. Alur utama — 5 langkah Robi sudah benar, dengan dua nuansa

Alur di mockup (pilih pasien → layanan & dokter → cek slot → konfirmasi → masuk
master jadwal) **cocok dengan kode**. Dua hal yang layak disebut:

1. **"Cek slot" itu benteng nyata, bukan tampilan.** `slotConflict()` jalan
   **di server sebelum insert**, memakai fungsi yang sama dengan penggambar grid.
   Buktinya: **0 tindihan jadwal dokter dalam 5.621 reservasi (90 hari)**.
2. **Ada 5 jalur pembuatan hidup, bukan satu**: form admin · form publik ·
   impor CSV (2 titik) · pemroses otomatis. Alur 5 langkah itu menggambarkan
   jalur utamanya saja — itu wajar untuk satu halaman, tapi jangan diklaim
   sebagai satu-satunya.

## 6. ⚠️ Screenshot: perlu, tapi yang ASLI — dan ada risiko privasi

Kotak terbesar di mockup Robi adalah "TAMPILAN APLIKASI", dan itu benar —
halaman tentang sistem tanpa memperlihatkan sistemnya jadi abstrak.

Tapi yang dia pakai **mockup dengan nama karangan** (Andi Pratama, Siti Aisyah,
Budi Santoso). Kita punya yang asli dan jauh lebih meyakinkan.

⛔ **Tangkapan layar asli memuat NAMA PASIEN dan NOMOR HP.** Contoh yang beredar
kemarin menampilkan `Helmalia Emila · 085885997388` terbaca jelas. Deck ini
**sudah beredar lewat WhatsApp** — begitu diteruskan, itu data pasien yang keluar
dari kendali kita.

**Pilihan yang aman**, urut dari yang paling disarankan:
1. Pakai **kalender reservasi** — sudah menggambarkan sistem tanpa identitas.
2. Kabur-kan nama & nomor di tangkapan layar daftar/detail.
3. Kalau butuh halaman pasien, pakai akun/pasien demo, bukan pasien nyata.

## 7. Kotak KEPUTUSAN STRATEGIS — perlu diubah bentuknya

Robi menawarkan tiga: **conversion · no-show · utilisasi dokter.**

⛔ **Satu sudah terjawab datanya: utilisasi dokter.** Utilisasi **~20–24%**
(dua metode independen: slot reservasi 23,6%, menit pasien nyata 19,3%). Cabang
menjadwalkan **3–5 dokter serentak**, yang benar-benar ditangani pada satu waktu
**rata dua orang**. **Kapasitas bukan kendala** — tak ada yang perlu
dioptimalkan di sana. Menyodorkannya sebagai pilihan terbuka berarti meminta
ruangan memperdebatkan sesuatu yang sudah punya jawaban.

Sisa dua, dengan bobotnya:

| | |
|---|---|
| **Conversion** ⭐ | Konsultasi → Pemasangan Behel cuma **32,8%** (592 → 194). Jeda tengah **14 hari** yang tak diisi apa pun. Paket behel **Rp 4,32 juta**. Tiap +5 poin konversi ≈ **Rp 26 juta/bulan**. |
| **No-show** | 18,2%, tapi **59% kembali** dalam 60 hari. Dan karena jadwal longgar, mereka **tak merebut kursi siapa pun**. |

**Saran bentuk kotaknya**: bukan *"pilih satu dari tiga"*, melainkan
**"satu sudah terjawab, dua tersisa, ini bobotnya."** Lebih berguna untuk orang
yang harus memutuskan.

## 8. ⭐ Yang belum ada di mockup sama sekali: sistemnya BERTAMBAH kemarin

Untuk pertanyaan *"keadaan sistemnya sekarang"*, ini justru yang paling baru —
naik prod **11 September 2026**:

- **`created_by`** — reservasi kini mencatat **siapa yang membuatnya**.
  Sebelumnya nol jejak. (Terbukti dipakai: 100% terisi sejak sore pertama.)
- **`cancel_reason` + catatan** — pembatalan kini punya alasan.
  Sebelumnya **652 pembatalan, nol alasan tercatat**.
- **Riwayat perubahan** lewat `audit_log`.
- **Rekam medis lebih lengkap di halaman pasien** — rencana perawatan, dokter,
  subjektif, objektif. Plus perbaikan: catatan klinis yang dulu dilebur jadi satu
  paragraf kini kembali berbaris (**24,9%** catatan terpengaruh).

⚠️ **Batas jujurnya**: `created_by` **tidak bisa diisi mundur** — `audit_log`
nol baris reservasi sebelum 11 Sep, jadi **6.400 reservasi lama tak akan pernah**
bisa diatribusikan. Pencatatan dimulai dari hari rilis.

## 9. ⛔ Yang JANGAN diklaim di halaman ini

- **"Terintegrasi WhatsApp"** — nol notifikasi keluar dari sistem.
- **"No-show 25%"** — itu memuat pembatalan. Yang benar **18,2%**.
- **"Funnel belum terukur"** — sudah, 98,6%; yang tak ada pembacanya.
- **"Waiting list belum ada"** — ada, 132 entri; yang lemah konversinya (31%).
- **"100% pembatalan punya alasan"** — impor CSV melahirkan baris yang sudah
  berstatus batal tanpa melewati gerbang alasan.
- **Apa pun tentang margin/laba** — `net_profit` Omnicare = `net_sell_price`
  (`base_price` & `usage_cost` kosong). Kita bisa bicara **pendapatan**, tidak
  pernah margin.

---

## Rujukan

Angka lengkap + batas buktinya: **`docs/handoff/data-reservasi-2026-09-11.md`**
— khususnya **§8** (batas bukti, wajib dibaca), **§19** (angka mana yang akan
bergeser sesudah perbaikan penjodohan), **§23** (peta dua deck), dan
**Lampiran** (klaim HUB yang sudah diralat — jangan dipakai lagi).

Kalau butuh angka yang belum ada di sana, minta HUB mengukur ulang di prod.
**Jangan menaksir.**
