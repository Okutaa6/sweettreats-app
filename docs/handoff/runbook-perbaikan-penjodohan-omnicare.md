# Runbook — perbaikan penjodohan Omnicare (`feat/penjodohan-omnicare`)

Ditulis untuk: siapa pun yang menjalankan gerbong ini di dev/prod.
Rujukan angka: `docs/handoff/data-reservasi-2026-09-11.md` §9–§10.

---

## ⛔ URUTAN. Jangan dibalik.

`deploy.sh` TIDAK menjalankan SQL. Ketiga langkah di bawah dijalankan tangan.

| # | Apa | Kenapa harus di urutan ini |
|---|---|---|
| 1 | `database/sql/2026_09_16_omnicare_dob_backfill.sql` | Skor penjodohan = phone 30 + **DOB 35** + nama 35. Mengisi DOB lebih dulu membuat langkah 2 bekerja di atas data yang jauh lebih kuat, dan menurunkan jumlah kasus ambigu dengan sendirinya. |

⚠️ **Patokan "SEBELUM" cakupan DOB prod = 7.597 / 15.732 (48,3%)**, bukan 56%
seperti di `data-reservasi-2026-09-11.md` §10 (diukur ulang HUB 16 Sep; §10
tampaknya memakai penyebut lain). Kalau laporannya menunjukkan ~56% sebagai
"sebelum", curigai penyebutnya — jangan simpulkan datanya membaik sendiri.

### Urutan lengkap (SQL backfill dijalankan DUA KALI)

```
1. SQL backfill DOB
2. SQL multi-pid
3. deploy kode
4. omnicare:match-patients              (--dry-run dulu)
5. SQL backfill DOB  ← LAGI, memungut DOB yang baru terjangkau lewat pid baru
6. omnicare:reconcile --perbaiki-batal, lalu --revisit
```

Langkah 5 aman justru karena jejak pemulihan kini bertahan lintas jalan
(terbukti di lokal: jalan kedua masih menemukan 11 pasien lagi).

⚠️ **Jalan kedua TIDAK memverifikasi ulang yang sudah terisi.** Baris ber-`diisi
= 1` sengaja dikunci — ia jejak pemulihan, dan ia tidak boleh bergerak. Jadi
kalau pid baru ternyata membawa DOB yang **berbeda** dari yang terlanjur diisi,
backfill tidak akan menangkapnya. Yang menangkapnya **hanya**
`omnicare:tinjau-tautan --dob`. Jangan mengira langkah 5 ikut memeriksa ulang.

⛔ Sebaliknya, baris ber-`diisi = 0` **harus** disegarkan, dan itu sempat tidak
terjadi: dengan `INSERT IGNORE`, pasien yang pid-pidnya dulu bertengkar terkunci
`variasi > 1` **selamanya** — konfliknya beres pun, tanggal lahirnya tak pernah
terisi, berapa kali pun berkas dijalankan. Sekarang memakai
`ON DUPLICATE KEY UPDATE … IF(diisi = 0, …)`. Diuji kedua arahnya.
| 2 | `database/sql/2026_09_16_customer_omnicare_link_multi_pid.sql` | **Melepas `UNIQUE uq_col_customer`.** Tanpa ini, kode barunya NOL EFEK — tiap pid tambahan dibuang database diam-diam. |
| 3 | deploy kode | Baru sesudah kedua SQL di atas. |

### ⭐ Yang berbeda dari rencana awal

Catatan handoff menduga *"skemanya kemungkinan sudah sanggup, yang menahan
adalah logika kodenya"*. **Tidak benar** — separuhnya ada di skema.
`2026_05_21_customer_omnicare_link.sql` memasang **dua** kunci unik:

```
UNIQUE KEY uq_col_customer (raw_customer_id)   ← INI yang menahan, dilepas
UNIQUE KEY uq_col_omnicare (omnicare_pid)      ← pagar keselamatan, DIPERTAHANKAN
```

Konsekuensi baiknya: **kriteria terima #3 ("nol pid menempel ke lebih dari satu
`raw_customer`") sekarang dijaga DATABASE**, bukan kehati-hatian kode. Ia tidak
bisa dilanggar tanpa error.

Kalau kode terlanjur naik sebelum SQL #2: tidak ada yang rusak, tapi perintahnya
tidak berbuat apa-apa. Ia akan mengatakannya sendiri — laporan
`omnicare:match-patients` mencetak peringatan bahwa `uq_col_customer` masih
terpasang.

---

## Menjalankan

Semua perintah punya `--dry-run` yang mencetak rencana tanpa menulis.
**Jalankan dry-run dulu, selalu.**

```bash
# LANGKAH 2 — banyak pid per pasien
php artisan omnicare:match-patients --dry-run
php artisan omnicare:match-patients

# LANGKAH 3a — lepas cap "tidak hadir" dari reservasi yang DIBATALKAN
#              (berdiri sendiri, supaya efeknya pada angka no-show terpisah)
php artisan omnicare:reconcile --perbaiki-batal --dry-run
php artisan omnicare:reconcile --perbaiki-batal

# LANGKAH 3 — tinjau ulang vonis lama yang gagal
php artisan omnicare:reconcile --revisit --days=90 --dry-run
php artisan omnicare:reconcile --revisit --days=90

# LANGKAH 3b — tandai tautan lama yang DOB-nya bertentangan (TIDAK menghapus)
php artisan omnicare:tinjau-tautan --dob
php artisan omnicare:tinjau-tautan --dob --tulis
```

⭐ **Langkah 3b men-TRIASE, jangan dibaca sebagai satu tumpukan.** Diukur di
data nyata, sebagian "konflik DOB" ternyata artefak format, bukan orang berbeda:

| `review_flag` | artinya | tindakan |
|---|---|---|
| `dob_tertukar` | hari & bulan tertukar (`1963-01-06` vs `1963-06-01`) — jejak format d/m/Y Omnicare | hampir pasti orang yang SAMA; betulkan tanggalnya |
| `dob_selisih_sehari` | beda satu hari | kemungkinan besar orang yang sama |
| `dob_bertentangan` | selisih besar | ⛔ patut dicurigai sebagai orang BERBEDA |

Melaporkan ketiganya sebagai "tautan bertentangan" membuat angkanya terdengar
jauh lebih menakutkan daripada kenyataannya — dan tindakannya pun berbeda.

### ⚠️ Ekspektasi angka PROD — jangan kaget melihat 474

HUB menjalankan triase ini terhadap prod (bukan salinan lokal):

| kategori | lokal | **PROD** |
|---|---|---|
| tautan ber-DOB di kedua sisi | 5.702 | **7.005** |
| `sama` | — | 6.464 (92,3%) |
| **`dob_tertukar`** | 2 | **474 (6,8%)** |
| `dob_selisih_sehari` | 3 | 6 |
| `dob_bertentangan` | 11 | **61** |

⭐ **474 itu bukan tersebar — seluruhnya jatuh di SATU HARI: 12 Mei 2026**, hari
yang sama ketika 2.164 pasien dibuat dalam satu menit (11:18). April punya 2×
lebih banyak pasangan yang bisa diperiksa dan hampir nol swap, jadi ini bukan
artefak populasi dan bukan salah ketik FO: **satu batch impor menulis ~472
tanggal lahir dengan hari & bulan tertukar, di sisi KITA.** Omnicare-nya benar
(`createFromFormat('d/m/Y')`, tak ambigu).

⚠️ 472 itu **batas bawah** — swap hanya terdeteksi kalau hari DAN bulan sama-sama
≤12. Dari 2.123 pasien ber-DOB di batch itu, hanya 700 yang bisa diperiksa
silang; sisanya tidak terbukti benar, cuma tak terbukti salah.

ℹ️ Di antara 61 `bertentangan` ada yang mustahil, bukan sekadar beda: tanggal
lahir bertahun **2026** (tanggal pendaftaran yang masuk ke kolom tanggal lahir).

⛔ **Membereskan impor 12 Mei itu TIKET TERPISAH, bukan PR ini.** Tapi ia ikut
menyebabkan kegagalan penjodohan yang sedang diperbaiki: DOB yang salah membuat
matcher kehilangan bonus 35 poin.

⚠️ Jalankan 3a **terpisah** dari 3, seperti di atas. Kalau ketiganya jalan
sekaligus, tidak akan ketahuan perubahan angka no-show disebabkan yang mana.

---

## Angka yang wajib dilaporkan

Perintahnya mencetak sendiri **sebelum vs sesudah**. Salin apa adanya.

- `omnicare:match-patients` → kriteria **#1** (tautan · pasien tertaut · pid
  tertaut), **#2** (pasien AKTIF tertaut — patokan 1.775 / 2.157 = 82,3%,
  harus naik), **#3** (pagar pid; kalau bocor ia berteriak dan kamu **berhenti**),
  **#4** (perintah ini hanya menyisipkan, tidak memindahkan).
- `omnicare:reconcile` → **#5** (berapa `no_customer_link` / `conflict_multi`
  jadi `link_*`, berapa `orphan_noshow` berkurang) dan **#6** (angka no-show).

⚠️ **Kalau angka no-show berubah drastis, itu temuan — bukan kesalahan.**
Laporkan apa adanya. Patokan yang sudah diketahui: pembatalan yang ikut dicap
tidak hadir menaikkan angka dari **18,2% menjadi 26,8%**.

---

## ⛔ Yang SENGAJA tidak dilonggarkan

Ada uji penjaga untuk ketiganya (`tests/Unit/OmnicareIdentitasTest.php`).

- **`MATCH_WINDOW` tetap 3 hari.** 469 dari 594 kegagalan terjadi di hari yang
  PERSIS SAMA — jendela bukan penyebabnya, melebarkannya menambah jodoh palsu.
  Ada uji yang juga memastikan `ReconciliationController::WINDOW` tetap sama.
- **Penjaga ambiguitas tidak dibuang** — ia dipertajam. Dulu: "kandidat #2
  terlalu dekat → jangan taut apa pun", yang justru memblokir kasus yang mau
  diperbaiki (dua pid milik orang yang sama selalu berskor identik). Sekarang
  ditanya lebih dulu apakah kandidat yang berhimpit itu **orang yang sama**
  (nama token-set persis + DOB tidak bertentangan). Kalau bukan → ditahan,
  persis seperti dulu. 682 kontak punya 2–7 pasien di satu nomor.
  Bonus: penjaganya kini diuji terhadap **semua** penantang, bukan hanya
  kandidat #2 — lubang lama yang bisa meloloskan ibu↔anak sudah ditutup.
- **Vonis yang boleh ditinjau ulang hanya `no_customer_link` dan
  `conflict_multi`.** `link_*` tidak ada di daftar dan jangan ditambahkan —
  meninjau ulang tautan yang berhasil berarti membongkar atribusi pendapatan
  yang sudah dipakai orang. Ada uji penjaga untuk daftar ini.
- **Tanggal lahir PASIEN KITA ikut memagari** (`pilihKandidat($layak,
  $identLama, $dobKita)` — argumen ketiga sengaja WAJIB, tanpa nilai bawaan,
  supaya tak ada pemanggil yang diam-diam kehilangan pagarnya). Sebelum audit
  HUB, seluruh pagar DOB bekerja di sisi Omnicare saja; `score()` pun tidak
  MENGHUKUM DOB yang bertentangan, ia cuma tak memberi bonus — jadi
  `phone 30 + nama persis 35 = 65 ≥ 60` tetap auto-link ke orang yang tanggal
  lahirnya jelas berbeda.
  ⛔ **Hanya kategori `bertentangan` yang ditolak** — `tertukar` dan
  `selisih_sehari` TIDAK. Menolak ketiganya akan membuang ratusan tautan yang
  benar gara-gara impor 12 Mei (lihat di bawah). Diukur prod dengan `bedaDob()`
  itu sendiri: dari 130 kandidat yang DOB-nya berbeda → `tertukar` **108** ·
  `selisih_sehari` **3** · `bertentangan` **19**.
  ⛔ **Laporkan 19 itu sebagai "ditahan untuk diperiksa manusia", BUKAN "19 orang
  salah".** Sebagian besar kemungkinan salah ketik tanggal 2–19 hari — ambang
  `selisih_sehari` cuma ≤1 hari, jadi mereka jatuh ke `bertentangan`. Sisanya
  selisih 5–7 tahun dan memang patut dicurigai orang berbeda. Penolakannya
  terlihat (`--daftar-ambigu`), bukan hilang diam-diam.
- **Tanggal lahir tidak boleh BERCABANG dalam satu pasien.** `samaOrang()`
  memaafkan DOB kosong (harus — 54,5% tautan lama begitu), tapi maaf itu tidak
  menurun: rekam tanpa DOB cocok dengan ayah MAUPUN anak yang senama, jadi ia
  jadi jembatan yang menyatukan dua orang. Pemeriksaan berpasangan tak pernah
  menangkapnya; `OmnicareIdentitas::dobBercabang()` memeriksa kelompoknya
  sekaligus. ⛔ Jangan dilepas — tanpa itu, tiga rekam "AYU LESTARI" (satu tanpa
  DOB, satu lahir 1990, satu lahir 2015) menyatu jadi satu pasien.

## ⛔ Vonis baru WAJIB punya ember di layar

`OmnicareReconcile::VONIS_TANPA_ENCOUNTER` mendaftar vonis yang menghasilkan
baris tanpa encounter. **Tiap nilai di sana harus punya ember di
`ReconciliationController` dan label sendiri di `report/reconcile.blade.php`.**

Ini bukan bahaya hipotetis: saat `cancelled` pertama ditambahkan, ia tidak masuk
ringkasan maupun riwayat — reservasi yang dulu tampil sebagai no-show **lenyap
dari layar**, dan kalau pun ditambahkan tanpa menyentuh blade, ia akan berlabel
"NO-SHOW" — persis cacat yang sedang diperbaiki. Dua uji menjaganya.

---

## ⛔ Commit: daftar berkas EKSPLISIT, jangan `-A` dan jangan `.`

Working copy ini dipakai banyak chat sekaligus. Saat ini ada pekerjaan **Alur
Persetujuan absensi** (tertanggal 21–22 Agustus) yang menumpang belum selesai —
`git add -A` akan menyeretnya ikut naik.

```bash
git add \
  database/sql/2026_09_16_omnicare_dob_backfill.sql \
  database/sql/2026_09_16_customer_omnicare_link_multi_pid.sql \
  app/Support/OmnicareIdentitas.php \
  app/Console/Commands/TinjauTautanOmnicare.php \
  app/Console/Commands/MatchOmnicarePatients.php \
  app/Console/Commands/OmnicareReconcile.php \
  app/Http/Controllers/ReconciliationController.php \
  app/Http/Controllers/Crud/CustomerController.php \
  resources/views/report/reconcile.blade.php \
  tests/Unit/OmnicareIdentitasTest.php \
  tests/Unit/PenjodohanPidTest.php \
  tests/Feature/PenjodohanOmnicareTest.php \
  tests/Feature/RiwayatMultiPidTest.php \
  docs/handoff/runbook-perbaikan-penjodohan-omnicare.md

git status --short   # periksa: TIDAK boleh ada AlurPersetujuan* ter-stage
```

⛔ **Yang BUKAN milik gerbong ini** (jangan ikut): `AlurPersetujuanController.php`,
`AlurPersetujuanService.php`, `2026_08_21_alur_persetujuan_delegasi.sql`,
`AlurPersetujuanLayarTest.php`, `AlurPersetujuanTest.php`, dan yang bertanda `M`:
`AturanKerjaController.php`, `AttendanceRequestWorkflow.php`,
`absensi/aturan.blade.php`, `rencana-alur-persetujuan.md`, `routes/web.php`.

## ⚠️ Utang yang diakui, bukan disembunyikan

**Layar tinjauan manusia masih belum ada.** `matched_by` = `system` untuk
seluruh 14.176 tautan — nol koreksi manusia, selamanya. Kodenya menyisakan
kasus ambigu "untuk review manual", tapi layar reviewnya tidak pernah dibuat;
`/reconcile` kena pola yang sama.

PR ini **tidak** menutup utang itu. Yang ia lakukan: membuat antreannya
**bisa dibaca**, supaya ia tidak diam-diam membesar tanpa ada yang tahu.

```bash
php artisan omnicare:tinjau-tautan                 # ringkasan semua antrean
php artisan omnicare:tinjau-tautan --antrean       # conflict + tanpa tautan pasien
php artisan omnicare:match-patients --dry-run --daftar-ambigu
```

Jangan laporkan ini sebagai "review manual sudah ada". Tidak ada tombol di sana.

---

## Uji

⚠️ Per kelas, jangan beruntun (phpunit dev tumbang di >±17 uji ber-DB per proses).

```bash
vendor/bin/phpunit tests/Unit/OmnicareIdentitasTest.php     # 20
vendor/bin/phpunit tests/Unit/PenjodohanPidTest.php         # 25
vendor/bin/phpunit tests/Feature/PenjodohanOmnicareTest.php # 25
vendor/bin/phpunit tests/Feature/RiwayatMultiPidTest.php    #  7
```

Ketiganya hijau. `PenjodohanOmnicareTest` memakai SQLite in-memory dengan skema
yang dibuat sendiri (meniru keadaan sesudah SQL multi-pid).

⚠️ **Gerbong ini punya 77 uji.** Angka 93 pernah dilaporkan karena ikut
menjumlahkan `tests/Unit/RiwayatRekamMedisTest.php` (19 metode → 23 kasus lewat
dataProvider). Kelas itu sudah ter-commit di `c3f1e33` dan dijalankan sebagai
regresi — sah, tapi **bukan milik gerbong ini**.

⛔ **Dan regresi itu ternyata tidak menyentuh baris yang gerbong ini ubah.**
`RiwayatRekamMedisTest` menguji `buildRiwayat()`, yang cuma memformat koleksi
yang SUDAH ditarik controller — **nol kueri**. Ia membuktikan teks rekam medis
tidak mundur, dan itu berharga; tapi ia tidak bisa menangkap regresi pada
`value()` → `pluck()` / `where` → `whereIn` di `CustomerController`, satu-satunya
baris yang berubah di berkas itu. Menjalankan uji yang hijau untuk hal LAIN
adalah rasa aman yang paling mahal.

→ Ditutup oleh `tests/Feature/RiwayatMultiPidTest.php` (7 uji): pasien dengan DUA
pid, rekam di dua cabang, dan kendali negatifnya. **Tidak lewat route** — `show()`
memanggil `Schema::hasTable`, yang terbukti mematikan mysqld LOKAL; ujinya
memanggil metode controller langsung di atas SQLite in-memory.

Patokannya diaudit dengan **kontrol negatif** (pelajaran berulang repo ini:
hijau palsu lebih mahal daripada merah):

- Cacat 1:1 dikembalikan → **3 uji merah**, tepat yang membuktikan perbaikan.
- Vonis `cancelled` dimatikan → **1 uji merah**, tepat yang membuktikan Langkah 3a.
- `whereIn('p_id', $pids)` rekam medis → `where(…, $pids[0])` → **3 uji merah**.
- `whereIn('p_id', $pids)` LTV → `where(…, $pids[0])` → **1 uji merah**.
- Semuanya dikembalikan; tiap berkas diperiksa **identik** dengan sebelum kontrol.

ℹ️ `RiwayatMultiPidTest` juga membawa kendali negatifnya **di dalam uji** —
ia menjalankan cara lama (satu pid) berdampingan dengan cara baru dan menuntut
hasilnya berbeda. Kalau suatu hari keduanya sama, uji itu merah sendiri tanpa
menunggu ada orang yang ingat menjalankan kontrol manual.

### Dijalankan sungguhan di MariaDB (16 Sep)

mysqld lokal berhasil dihidupkan lagi (recovery InnoDB) — **MariaDB 10.4.32**,
berisi salinan BASI tapi berbentuk nyata: 6.022 pasien · 15.331 rekam Omnicare ·
5.625 tautan · 140.155 baris sales. Seluruh gerbong dijalankan di atasnya.

| langkah | hasil |
|---|---|
| SQL backfill DOB | jalan bersih; cakupan **79,4% → 95,8%** (992 terisi) · RENCANA 992 = HASIL 992 |
| ALTER multi-pid | `uq_col_customer` lepas · `uq_col_omnicare` tetap · `review_flag` ada |
| `match-patients` | +179 tautan · +87 pasien · **92 pid tambahan** · pagar #3 nol bocor |
| jalan kedua | **0 baris** ditulis — idempoten terbukti |
| `reconcile --revisit` | 26 vonis lama dinilai ulang · 3 jadi ter-link · **nol reservasi berbaris ganda** |
| `--perbaiki-batal` | diuji dengan probe berstatus `Cancelled` (huruf besar) → dikenali, dikembalikan |
| `tinjau-tautan --dob` | 5.702 tautan ber-DOB dua sisi · 16 berbeda · ditandai, **nol dihapus** |
| pagar DOB pasien kita | **8 kandidat ditolak, 0 mengubah hasil** — semua toh gugur di pagar berikutnya |

⚠️ **"Kandidat ditolak" ≠ "tautan dicegah".** Laporan memisahkan keduanya, dan
angka kedua dihitung dengan **mengadu putusan melawan tandingannya tanpa pagar**
— bukan ditebak. Versi pertama menebak, dan menebaknya salah: ia melaporkan
"8 mengubah hasil" padahal kontrol negatif membuktikan nol.

⭐ **Ramalan `--dry-run` = hasil sungguhan, persis**, pada matcher maupun
reconcile. Itu yang membuat dry-run layak dipercaya sebelum jalan di prod.

⛔ **Angka di tabel ini BUKAN angka prod** — sumbernya salinan lokal yang basi
(mis. cuma 18 pasien "aktif"). Ia membuktikan gerbongnya jalan di MariaDB dan
angkanya konsisten, bukan berapa hasilnya nanti. Angka kriteria terima tetap
harus datang dari menjalankan `--dry-run` di prod.

### ⚠️ Keadaan DB LOKAL sesudah pengujian ini

Ia **sudah ikut berubah** (backfill 992 DOB · 179 tautan · 50 baris rekonsiliasi ·
16 `review_flag` · ALTER skema). Semua itu memang efek fitur ini, bukan sampah.
Kalau mau dikosongkan lagi:

```sql
-- balikkan backfill DOB (melewati baris yang sudah dibetulkan staf)
UPDATE raw_customer c JOIN omnicare_dob_backfill_log b ON b.raw_customer_id = c.id
SET c.tanggal_lahir = NULL
WHERE b.diisi = 1 AND CAST(c.tanggal_lahir AS CHAR) = CAST(b.dob_omnicare AS CHAR);

DELETE FROM customer_omnicare_link  WHERE matched_at    >= '2026-09-16';
DELETE FROM reservation_omnicare_link WHERE reconciled_at >= '2026-09-16';
UPDATE customer_omnicare_link SET review_flag = NULL;
```

ℹ️ mysqld lokal tadinya mati; dihidupkan dengan resep di memori (XAMPP
standalone). Log recovery-nya penuh "page … in the future" — data lokal memang
pernah rusak, jadi jangan jadikan ia sumber kebenaran untuk apa pun.
