# Handoff — Penanda Slot Lintas Cabang & Peringatan Tumpang Tindih Jadwal

**Untuk:** Reza
**Dari:** sesi Claude Ekky, 8 September 2026
**Status:** siap dikerjakan · nol keputusan yang masih menggantung
**Perkiraan:** kecil–sedang · **nol tabel baru, nol SQL**

---

## 1. Kenapa ini ada — kejadian nyatanya

10 Sep 2026, admin mau menambah pasien untuk **drg. Dharma** di **Cinere jam 17:00**.
Yang dia lihat: jam 17:00 bertanda **"Terisi"**, padahal daftar reservasi Cinere
tanggal itu **kosong** untuk Dharma. Layarnya juga menampilkan **jam kembar** —
17:00, 17:30, 18:00 muncul dua kali.

Sesudah ditelusuri ke prod, itu **dua hal yang sama sekali berbeda**:

| Gejala | Sebab sebenarnya | Status |
|---|---|---|
| Slot kembar (9 kotak untuk 6 jam) | Jadwal rutin Cinere `17:00–20:00` ditumpuk Hari Tambahan `17:00–18:30` | ✅ **SUDAH DIPERBAIKI** — jangan diulang |
| "Terisi" tanpa orang | Reservasi **nyata** di **Kalideres** `17:00–17:30` a.n. Ghita Lestary | ⬅️ **ini bagianmu** |

Yang kedua **bukan bug**. Dharma satu orang; tak bisa di Kalideres dan Cinere
pada jam yang sama, jadi sistem benar menahan slot itu. Yang salah: **layarnya
tidak pernah bilang kenapa.** Admin membacanya sebagai sistem rusak, lalu
laporan naik ke Ekky — padahal informasinya ada di database, cuma tak
ditampilkan.

**Itulah yang kamu bangun: membuat slot yang terisi bisa menjelaskan dirinya sendiri.**

---

## 2. ⛔ Tiga aturan keras — baca sebelum menulis kode

### 2.1 JANGAN menambah penyaring cabang di `resolveBookedRanges()`

`app/Http/Controllers/Concerns/ResolvesDoctorAvailability.php`

Fungsi itu menyaring `raw_doctor_id` + `date` saja, **tanpa `raw_branch_id`**.
Kelihatan seperti bug. **Bukan.** Itu satu-satunya yang mencegah seorang dokter
dipesan di dua cabang pada jam yang sama.

Menambahkan `->where('raw_branch_id', $branchId)` akan **membuka double-booking
dokter yang sungguhan**, dan gagalnya diam — tak ada error, cuma dokter yang
tiba-tiba punya dua pasien di dua tempat pada jam yang sama.

Yang kurang adalah **penjelasannya**, bukan penyaringnya.

### 2.2 Form publik TIDAK BOLEH ikut menampilkan detail

Pasien di `/reservasi` tak boleh melihat nama pasien lain. Kalau detail
dipasang di lapisan bersama tanpa pagar, kebocorannya **keluar perusahaan**.

Karena itu bentuknya wajib **mati secara bawaan**: `computeTimeSlots()` hanya
mengembalikan detail kalau pemanggil memintanya secara eksplisit
(mis. opsi `with_booking_detail`). Endpoint admin menyalakannya; form publik
tak menyentuh apa pun.

> Kalau kelak ada yang lupa menyalakan, yang terjadi adalah **fitur tak muncul** —
> bukan **data bocor**. Itu arah gagal yang benar.

### 2.3 Nomor HP TIDAK ditampilkan (keputusan Ekky, 8 Sep)

Yang boleh muncul lintas cabang: **cabang · jam · nama pasien**.
**Nomor HP tidak.** Koordinasinya ke admin cabang, bukan ke pasien orang lain —
nomor HP tak menambah kemampuan koordinasi, cuma menambah permukaan bocor.

---

## 3. Yang SUDAH diperbaiki — jangan dikerjakan ulang

Branch **`fix/slot-kembar`** (dari `origin/main`):

1. **`gabungRentangBertindih()`** di `ResolvesDoctorAvailability.php` — rentang
   jam yang bertindih digabung sebelum slot dicetak, jadi jam kembar hilang.
   Yang cuma **bersentuhan** (10:00–12:00 lalu 12:00–15:00) sengaja **tidak**
   digabung: itu dua sesi terpisah, dan menyatukannya menggeser titik grid untuk
   durasi yang tak habis membagi rentang.
2. **`after:start_hour`** pada `end_hour` di `DoctorController::scheduleStore()`
   dan `scheduleUpdate()` — jam praktik terbalik tak bisa disimpan lagi.
   (Jam istirahat sudah dijaga sejak awal; jam praktiknya yang kelupaan.)

Terverifikasi lawan data prod: **3 → 0** kombinasi berkembar, **0** jam hilang
dari 166 kombinasi, **0 dari 518** hari-dokter berubah di jalur absensi dokter.

---

## 4. Lingkup kerjamu — dua layar

### Layar A — Jadwal Dokter (mencegah datanya lahir)

`/rawdoctor/{id}/edit` → `app/Http/Controllers/Crud/DoctorController.php`
(`scheduleStore` / `scheduleUpdate` / `exceptionStore` / `exceptionUpdate`)

Saat ini **nol pemeriksaan tumpang tindih**. Itu sebabnya Della membuat Hari
Tambahan Cinere `17:00–18:30` tanpa tahu hari itu sudah praktik `17:00–20:00`.

Peringatan yang dibutuhkan — **memberi tahu, tidak menghalangi**:

1. **Tumpang tindih di cabang yang sama**
   > ⚠ Hari itu sudah ada jadwal di cabang ini: **17:00–20:00**.
   > Hari Tambahan ini tumpang tindih dan tidak menambah jam praktik.

2. **Tumpang tindih di cabang lain**
   > ⚠ Dokter ini juga terjadwal di **Kalideres 17:00–21:00** pada hari yang sama.

Peringatan #1 sendirian sudah cukup untuk mencegah kejadian kemarin terulang.

### Layar B — Reservasi (menjelaskan yang sudah ada)

**Dua tempat render, dan keduanya harus dikerjakan sadar:**

| | Berkas | Untuk siapa | Boleh lihat detail? |
|---|---|---|---|
| Admin | `public/assets/js/reservation-form.js` | staf | **ya** |
| Publik | `resources/views/reservation-form.blade.php` | pasien | **TIDAK** |

Berkas JS admin dipakai bersama oleh **empat** pintu — `crud/reservation/new`,
`edit`, `list`, dan `_import` — jadi satu perubahan menjangkau keempatnya.
Hook-nya ada di `crud/reservation/_form.blade.php` (`#slotsContainer`).

Yang perlu muncul:

1. **Pada kotak terisi** — hover (desktop) / tap (HP):
   > `Terisi · Kalideres · 17:00–17:30 · a.n. Ghita Lestary`

2. **Spanduk hari** di atas grid, muncul kalau dokter itu punya janji di cabang
   lain pada tanggal itu — **walaupun jam yang dipilih bebas**:
   > ⚠ Dharma sudah punya janji di **Kalideres** hari ini (17:00–17:30).
   > Pastikan sudah dikoordinasikan.

Spanduk hari itu yang menutup lubang **pindah cabang nol menit**: jam 17:30
Cinere memang kosong, tapi Dharma ada di Kalideres sampai 17:30. Sistem hanya
mencegah **jam bertabrakan**, tak pernah mencegah **cabang berpindah**.

**Desain visualnya bebas kamu** — yang wajib cuma: **ada tanda, dan tandanya
bisa dibuka isinya oleh user tanpa bertanya ke siapa pun.**

---

## 5. Peta teknis

### Dari mana datanya

`resolveBookedRanges()` sekarang hanya mengembalikan `['start' => …, 'end' => …]`.
Perlu membawa `raw_branch_id`, nama cabang, dan nama pasien.

**Aman ditambahkan**: logika tabrakan di `computeTimeSlots()` dan
`assignFamilySlots()` hanya membaca kunci `start` dan `end` — kunci ekstra
diabaikan begitu saja. Tak perlu membuat fungsi kembar.

Nama pasien ada di `raw_customer` (join lewat `raw_cus_reservation.raw_customer_id`).
Nama cabang di `raw_branch`.

Status yang **menahan** slot (jangan diubah): semua **kecuali** `cancelled` dan
`waitlist`; status `NULL` (data lama) tetap dianggap terisi.

### Bentuk keluaran yang disarankan

Tiap slot dari `computeTimeSlots()` sudah punya `is_booked`. Tambahkan
`booked_by` **hanya bila diminta** — berisi `branch_id`, `branch`, `nama`, `jam`.
Tanpa opsi itu, keluarannya persis seperti sekarang.

### ⚠️ Jebakan deploy — `public/assets/**`

`public_html` di **prod itu SALINAN manual, bukan symlink**. Deploy yang
menyentuh `public/assets/**` **wajib** diikuti rsync:

```
rsync -a laravel/public/assets/  public_html/assets/     # BENAR
rsync -a laravel/public/         public_html/            # ⛔ MEMATIKAN PROD
```

Menyalin seluruh `public/` pernah membuat **seluruh situs 500 selama 6 menit** —
`index.php` prod sengaja berbeda. Batasi ke `assets/`.

Dan sesudah deploy, `laravel.log` yang bersih **bukan** bukti sehat:
`curl -s -o /dev/null -w '%{http_code}' https://app.sweettreats.id/` harus 200,
dan periksa URL akhirnya (pentalan ke login juga melapor 200).

---

## 6. Daftar periksa sebelum bilang selesai

- [ ] Admin Cinere melihat detail reservasi Kalideres pada slot terisi
- [ ] **Form publik `/reservasi` tetap hanya menulis "Terisi"** — tak ada nama, tak ada cabang
- [ ] **Nomor HP tidak muncul di mana pun**
- [ ] Spanduk hari muncul saat dokter punya janji di cabang lain, walau slot yang dipilih bebas
- [ ] Endpoint yang tak meminta detail mengembalikan keluaran **persis seperti sebelumnya**
- [ ] `resolveBookedRanges()` **masih tanpa penyaring cabang** — uji: pesan dokter yang sama, jam sama, dua cabang → wajib tertolak
- [ ] Layar Jadwal Dokter memperingatkan tumpang tindih cabang sama **dan** cabang lain
- [ ] Peringatannya **tidak menghalangi penyimpanan**
- [ ] Kalau `public/assets/**` tersentuh → rsync `assets/` saja + curl beranda 200

---

## 7. Angka prod yang mungkin berguna (diukur 8 Sep 2026, baca-saja)

- **36** dokter berjadwal; hanya **2** yang jam praktiknya bertindih di cabang
  berbeda pada hari yang sama: **drg. Dharma** (Kamis, 4 cabang) dan
  **drg. Wira** (Selasa, 5 cabang). Itu pola **dokter on-call**, bukan data rusak.
- **0 dari 931** hari-dokter sejak 1 Jan 2026 pernah dipakai di lebih dari satu
  cabang dalam sehari. Jadi kenyataannya selalu satu cabang per hari — jadwal
  lintas-cabang itu template "bisa ditaruh di mana saja", bukan klaim dia ada di
  empat tempat sekaligus.
- Exception di prod: **69 `replace`, 7 `add`**.

---

## 8. Utang yang sengaja ditinggalkan (bukan bagianmu, tapi jangan kaget)

1. **Label "90 hari" bohong.** Teks di layar dan docblock bilang 90 hari; loop
   `computeAvailableDates()` sebenarnya **535 hari**. Dharma/Cinere: 77 tanggal,
   terakhir **24 Feb 2028**, padahal cuma 13 yang dalam 90 hari.
   Yang salah **labelnya, bukan jendelanya** — ada 12 reservasi nyata di luar 90
   hari, jadi jendela panjang itu memang dipakai. Jangan dipendekkan.
2. **Baris jadwal terbalik milik Dharma** — Bintaro, Minggu, `22:00–20:00`,
   satu-satunya baris Minggu-nya. Nol slot (tak ada yang bisa memesan), tapi
   mesin absensi tetap menganggapnya dijadwalkan **tiap Minggu** lalu mencatatnya
   "praktik tanpa scan". Perlu keputusan operasional, bukan kode. Validasi baru
   mencegah baris seperti ini lahir lagi, tapi tidak menyentuh yang sudah ada.
3. **Dua baris jadwal redundan** menunggu dibersihkan Della: Hari Tambahan Cinere
   10 Sep, dan **Panji · Bintaro · Sabtu `15:00–20:00`** (termuat penuh di
   `10:00–20:00`, bikin 10 jam kembar tiap Sabtu). Sesudah perbaikan penggabungan,
   keduanya **tak berbahaya** — tinggal rapi-rapi, tak ada tenggat.
4. **Tak ada pagar waktu tempuh antar cabang.** Sengaja: butuh matriks jarak.
   Spanduk hari di §4 adalah penggantinya yang jujur — memberi tahu, bukan menebak.

---

## 9. Kalau butuh menelusuri sendiri

Pola yang dipakai untuk semua angka di dokumen ini: **skrip yang mem-bootstrap
aplikasi di server**, jadi Laravel membaca `.env` sendiri — tak perlu menyentuh
password DB sama sekali.

```php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// DB::table(...) di sini
```

⚠️ Kalau menguji kelas yang **sudah ada** di server dengan versi barumu:
`require` berkas barumu **sebelum apa pun menyentuhnya**, kalau tidak autoloader
memuat versi lama dan simulasimu diam-diam menguji kode yang bukan kodemu —
hasilnya tetap masuk akal, jadi tak ada yang curiga.
Patokan yang menyelamatkan: cetak `(new ReflectionClass(X::class))->getFileName()`
dulu, percaya angkanya belakangan.

⚠️ Uji ber-DB di dev tumbang di atas ±17 uji per proses (batas laju koneksi
hosting bersama, `SQLSTATE[HY000] [2002]`) — **jalankan per kelas** sebelum
menyangka ada regresi.
