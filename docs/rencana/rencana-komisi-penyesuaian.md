# Rencana — Penyesuaian 70% & Baris Manual Komisi Dokter

> Status: **draf, menunggu ACC Ekky**. Ditulis 2026-09-01 oleh chat HUB sesudah
> rekonsiliasi penuh rekap Finance Agustus 2026 vs `omnicare_commission` prod.
> Angka & bukti: `docs/log-update.md` entri 2026-09-01.

## 1. Masalahnya, dalam satu kalimat

Halaman **Komisi Dokter** menampilkan angka Omnicare apa adanya, sedangkan yang
**benar-benar dibayar** Finance berbeda — dan perbedaannya **tidak bisa dihitung
dari data mana pun yang kita punya**.

Rekonsiliasi 26 Jul – 25 Agu 2026 **tertutup tepat**:

```
Omnicare Rp 444.360.154  ·  dibayar Finance Rp 439.240.197  ·  selisih Rp 5.119.957

A. faktur dibayar 70%        10 faktur / 25 baris   Rp   759.198
B. faktur tak ada di rekap   10 faktur / 17 baris   Rp 4.360.759
C. baris Rp 0 di rekap saja            88 baris     Rp         0
                                       ─────────────────────────
A + B − C                                           Rp 5.119.957  ✓
```

Dua cabang — **Sunter & Kalideres** — cocok **sampai rupiah** tanpa penyesuaian
apa pun. Itu yang membuktikan rentang dan sumbernya sudah benar; yang kurang
hanyalah fakta yang hidup di spreadsheet Finance.

## 2. Empat fakta yang sudah dipastikan — jangan diteliti ulang

1. **Periode gaji = 26 bulan lalu s/d 25 bulan ini**, bukan bulan kalender.
   Bulan kalender meleset **−Rp 27,7 juta**. Halaman sekarang defaultnya bulan
   kalender → **itu bukan angka yang keluar duitnya.**

2. **Potongan selalu 70%**, rata untuk semua dokter (ACC Ekky 2026-09-01).
   Bukan variabel per dokter, bukan per tindakan — satu angka.

3. **Berlakunya PER FAKTUR.** Diuji: 10 faktur memuat baris 70%, dan **nol** di
   antaranya memuat baris 100%. Pembanding: per **pasien** TIDAK konsisten (1
   dari 10 pasien juga punya faktur 100%), per **jenis tindakan** TIDAK konsisten
   (15 dari 15 jenis juga muncul di 100%).
   ⚠️ Per **hari** kelihatan "campur" — itu artefak, satu dokter bisa punya
   beberapa faktur dalam sehari. **JANGAN modelkan per dokter-per-tanggal.**

4. **Dokter yang kerjanya manual = dokter yang belum punya `omnicare_doc_id`.**
   Terverifikasi: `raw_doctor` punya **5 dari 35** dokter tanpa `omnicare_doc_id`,
   dan dua di antaranya persis **drg Wiliam Sp Bm** dan **drg Fanisa Ayu C** —
   yang muncul di rekap Finance tapi **nol baris di `omnicare_commission`
   sepanjang riwayat**. **Rizka** dan **drg. Arif** bahkan belum ada di master.

## 3. Yang dibangun

### 3a. Penanda 70% per FAKTUR

Tabel `komisi_faktur_penyesuaian`:

| kolom | catatan |
|---|---|
| `place_id` + `invoice_num` | kunci; **wajib berpasangan** — nomor faktur berulang antar cabang (`26070358` ada di Bintaro *dan* cabang lain) |
| `persen` | `TINYINT`, default **70**. Disimpan walau konstan, supaya perubahan perjanjian tak butuh migrasi kode |
| `alasan` | teks pendek, **WAJIB** — tanpa ini, enam bulan lagi tak ada yang tahu kenapa |
| `dibuat_oleh`, `created_at` | jejak; Finance yang mempertanggungjawabkan angka ini |

Unik pada (`place_id`, `invoice_num`) → menandai dua kali = idempoten, bukan
dobel potong.

### 3b. Baris manual (pekerjaan yang tak masuk Omnicare)

Terverifikasi nyata di Agustus: **Rizka** (Rp 133.077) & **drg. Willam**
(Rp 87.500) di Bintaro, **drg. Fanisa** (Rp 633.970) di Cinere — total
**Rp 854.547**, ditulis Finance **di luar Grand Total**.

Tabel `komisi_baris_manual`: `place_id`, `trx_date`, `raw_doctor_id` (nullable),
`doctor_nama` (teks bebas), `item_name`, `qty`, `nilai_jual`, `komisi`,
`alasan`, `dibuat_oleh`.

⚠️ **Nama dokter WAJIB boleh teks bebas.** Finance menulis **"Willam"**,
master kita **"Wiliam"** — beda satu huruf. Dropdown-saja akan memaksa orang
memilih dokter yang salah atau menyerah. Sediakan pilihan dari `raw_doctor`
(**termasuk yang `omnicare_doc_id`-nya NULL**) *plus* isian bebas.

⛔ **JANGAN menulis ke `omnicare_commission`.** Tabel itu **cermin vendor**;
menulis ke sana membuat `accurate:push-daily` menganggap harinya "berubah" lalu
**menghapus & menulis ulang dokumen di Accurate**. Baris manual hidup di tabel
sendiri dan hanya digabung **saat menampilkan**.

### 3c. Layar

- Preset periode **"Periode Gaji (26–25)"**, dan itu jadi **default** halaman ini.
- Kolom **berdampingan**: `Komisi Omnicare` · `Dibayar` · `Selisih` + alasan.
  ⛔ **JANGAN mengganti angka Omnicare diam-diam.** Justru karena selisih yang
  tak terlihat itulah rekonsiliasi ini butuh dua hari; layar yang menyembunyikan
  penyesuaian akan mengulang kejadian yang sama bulan depan.
- Baris manual diberi penanda visual jelas (**bukan** dari Omnicare).
- Ekspor CSV memakai kolom yang sama.

### 3d. Hak akses

**Finance saja** yang boleh menambah/mengubah (ACC Ekky). Executive/IT tetap
boleh **melihat**. Gerbang tulisnya **di controller**, bukan sekadar
menyembunyikan tombol — tombol yang disembunyikan tetap bisa dikirim lewat form
buatan sendiri.

## 4. Yang TIDAK dibangun

**Menebak faktur mana yang 70%.** Sudah diuji dari tiga arah: tak ada di data.
Sistem tidak menerka — **Finance menandai, sistem menghitung.**

## 5. Utang yang tetap milik Finance, bukan kode

**Kelompok B: 10 faktur, Rp 4.360.759, ada di Omnicare tapi tak ada di rekap.**

Diuji: **nol** di antaranya punya faktur yang muncul sebagian di rekap Finance —
**seluruh fakturnya absen**, bukan barisnya yang dilewat. Kemungkinan terkuat
**late entry**: faktur lahir di Omnicare sesudah Finance mengekspor. Terbesar
sendirian **Rp 2.603.450** (`MEI - C&S STELLAR METAL SP`, drg. Nugroho, Bintaro
29/07).

⚠️ Ini **bukan** kelebihan hitung kita — ini kemungkinan **dokter kurang
dibayar**. Rincian per baris sudah diserahkan:
`SELISIH FEE DOKTER 26Jul-25Agu 2026.csv` (130 baris, kelompok A/B/C).

Kalau benar late entry, layar ini sebaiknya **menandai faktur yang belum pernah
masuk rekap mana pun** — detektor kegagalan-senyap, bukan sekadar laporan.

## 6. Uji terima

1. Preset 26–25 jadi default; preset bulan kalender tetap ada.
2. **Kendali negatif: Sunter & Kalideres Agustus tetap cocok sampai rupiah.**
   Penyesuaian tak boleh menggeser yang sudah benar.
3. Menandai 10 faktur → total Agustus **Rp 439.240.197**, + Rp 854.547 manual.
4. Menandai faktur yang sama dua kali = idempoten, bukan dobel potong.
5. Baris manual muncul & bertanda, dan **`omnicare_commission` tak bertambah**
   (cacah baris sebelum/sesudah).
6. **`accurate_push_log` tak bertambah** sesudah memakai fitur ini.
7. Role non-Finance: tombol tulis tak ada **dan** POST langsung ditolak.
8. Angka Omnicare tetap tampil berdampingan, tak pernah tertimpa.
9. Hapus penyesuaian → angka kembali 100%, jejaknya tetap ada.
10. Dokter yang `omnicare_doc_id`-nya NULL bisa dipilih untuk baris manual.

## 7. Jebakan proyek yang berlaku di sini

- Rute baru bersegmen-1 **wajib** punya node `access_path`; kalau tidak RBAC
  **memantulkan semua orang ke beranda — bukan 403**, sehingga uji "nol 403"
  lulus palsu. Halaman ini sudah punya nodenya; **jangan** pindahkan ke bawah
  `finance` (segmen itu ada di `AuthGuard::SKIP_RBAC`).
- Ikon **FontAwesome 5**; nama gaya FA6 jadi kotak kosong.
- **Jangan `JOIN` ke `raw_doctor`** untuk mengambil nama — `omnicare_doc_id`
  kembar melipatgandakan `SUM`. Ikuti `KomisiQuery::perDokter()` yang sudah ada.
- Deploy yang menyentuh `public/assets/**` → **rsync WAJIB, dibatasi ke
  `assets/`**. `rsync` seluruh `public/` **mematikan prod** (kejadian 29 Agu).
- SQL ditulis idempoten; grant **by link**, bukan by id node.

## 8. Prompt chat build

```
Lanjutkan proyek SweetTreats lewat memori. Chat ini membangun PENYESUAIAN 70%
dan BARIS MANUAL untuk halaman Komisi Dokter. Dev dulu; prod hanya kalau Ekky
menyebut "prod" eksplisit.

BACA DULU — rencananya lengkap, JANGAN menyusun ulang:
· docs/rencana/rencana-komisi-penyesuaian.md   <- dokumen utama
· memori project_komisi_dokter                 <- halaman & jebakannya
· memori project_accurate_push_jeda            <- kenapa JANGAN tulis ke omnicare_commission

EMPAT fakta sudah dipastikan lewat rekonsiliasi penuh, jangan diteliti ulang:
periode gaji 26-25 (bukan bulan kalender); potongan SELALU 70% rata semua dokter;
berlakunya PER FAKTUR (10 faktur, nol di antaranya memuat baris 100%); dan
dokter yang kerjanya manual = dokter yang omnicare_doc_id-nya NULL.

WARNING Per hari kelihatan campur - itu artefak, satu dokter bisa punya
beberapa faktur sehari. JANGAN modelkan per dokter-per-tanggal.

JANGAN menulis ke omnicare_commission. Itu cermin vendor; menulis ke sana
membuat accurate:push-daily menghapus & menulis ulang dokumen di Accurate.
Baris manual hidup di tabel sendiri, digabung hanya saat menampilkan.

JANGAN mengganti angka Omnicare diam-diam. Tampilkan berdampingan:
Komisi Omnicare - Dibayar - Selisih + alasan. Selisih yang tak terlihat itulah
yang butuh dua hari untuk ditemukan.

Nama dokter WAJIB boleh teks bebas: Finance menulis "Willam", master kita
"Wiliam" - beda satu huruf. Dropdown saja akan memaksa orang memilih yang salah.

Hak tulis: Finance saja (ACC Ekky). Gerbang di controller, bukan cuma menu.

Uji terima paragraf 6, 10 poin. Termasuk kendali negatif: Sunter & Kalideres
Agustus HARUS tetap cocok sampai rupiah, dan accurate_push_log tak boleh
bertambah.

Aturan kerja: dev = SATU working copy bersama - cek `git status --porcelain |
grep "^??"` dulu, ada kerjaan chat lain yang belum di-commit di sana.
STOP Kalau `git checkout -B` MENOLAK, itu PENGAMAN, JANGAN diakali dengan
`git reset --hard` (reset menimpa untracked tanpa peringatan; checkout menolak).
Dev di-install --no-dev jadi phpunit TAK ADA; kembalikan
`--no-dev --optimize-autoloader` sesudahnya. phpunit dev mati di lebih dari
sekitar 17 uji ber-DB per proses -> jalankan PER KELAS.
Selesai: entri docs/log-update.md + salin ke Google Drive + catat ke memori.
```
