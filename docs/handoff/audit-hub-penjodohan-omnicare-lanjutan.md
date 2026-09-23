# Audit HUB lanjutan — penjodohan Omnicare, putaran kedua (16 Sep 2026)

> Untuk chat build. Bilang saja: **"baca `docs/handoff/audit-hub-penjodohan-omnicare-lanjutan.md`"**.
> Lanjutan dari `audit-hub-penjodohan-omnicare.md`. Diverifikasi ulang terhadap PROD.

---

## Ketiga temuan: DITUTUP

| | keadaan |
|---|---|
| **Temuan 1** — DOB pasien kita | ✅ `pilihKandidat($layak, $identLama, ?string $dobKita)` — argumen ketiga **wajib, tanpa nilai bawaan**. Tepat: tak ada pemanggil yang bisa kehilangan pagarnya diam-diam. |
| **Temuan 2** — swap 12 Mei | ✅ masuk runbook sebagai tabel ekspektasi + batas bawah, tetap tiket terpisah |
| **Temuan 3** — jejak backfill | ✅ `CREATE TABLE IF NOT EXISTS` + `INSERT IGNORE`, `RENCANA` memisahkan `diisi = 0` |
| Ralat 56% → 48,3% | ✅ masuk kepala SQL & runbook |

Uji dijalankan HUB sendiri: **20 · 25 · 25** — semuanya hijau.

⭐ **Koreksi diri kalian soal "8 ditolak · 8 mengubah hasil" itu justru bagian
terbaik dari putaran ini.** Menyimpulkan "tak ada kandidat tersisa = hasil
berubah" dari dalam `pilihKandidat()` memang tebakan, dan kontrol negatif yang
membatalkannya adalah disiplin yang benar. Angka rapi yang salah persis penyakit
yang sedang gerbong ini obati. Kalian menangkapnya sendiri sebelum HUB sempat.

---

## ⛔ TEMUAN BARU — "93 uji" itu 70. Yang 23 milik chat lain

```
tests/Unit/OmnicareIdentitasTest.php      20
tests/Unit/PenjodohanPidTest.php          25
tests/Feature/PenjodohanOmnicareTest.php  25
                                     TOTAL 70
```

Yang ke-23 adalah **`tests/Feature/AlurPersetujuanLayarTest.php`** — tepat 23
metode uji, tertanggal **22 Agustus**, tentang **Alur Persetujuan absensi**.
Bukan bagian dari gerbong ini sama sekali; ia menumpang di working copy bersama
sejak 25 hari lalu.

**Yang lebih berbahaya daripada salah hitung:** kalau gerbong ini di-commit
dengan `git add -A`, ikut terbawa **lima berkas milik chat lain yang belum
selesai**:

```
?? app/Http/Controllers/Absensi/AlurPersetujuanController.php   (22 Agu)
?? app/Services/AlurPersetujuanService.php                      (22 Agu)
?? database/sql/2026_08_21_alur_persetujuan_delegasi.sql        (21 Agu)
?? tests/Feature/AlurPersetujuanLayarTest.php                   (22 Agu)
?? tests/Unit/AlurPersetujuanTest.php                           (22 Agu)
```

Plus berkas **`M`** yang juga bukan milik gerbong ini —
`AturanKerjaController.php`, `AttendanceRequestWorkflow.php`,
`resources/views/absensi/aturan.blade.php`, `rencana-alur-persetujuan.md`.

⛔ **Commit dengan daftar berkas eksplisit, jangan `-A`, jangan `.`**
Folder ini dipakai banyak chat sekaligus; berkas bisa berubah di tengah sesi.
Milik gerbong ini cuma: dua SQL, `OmnicareIdentitas.php`,
`TinjauTautanOmnicare.php`, `MatchOmnicarePatients.php`, `OmnicareReconcile.php`,
`ReconciliationController.php`, `report/reconcile.blade.php`, tiga berkas uji,
dan runbook-nya.

---

## ⚠️ RALAT HUB — angka HUB yang salah sudah terlanjur masuk komentar kalian

`MatchOmnicarePatients.php` baris ±346 sekarang berbunyi:

```php
//    Diukur di prod: dari 130 kandidat yang DOB-nya "berbeda", 125 cuma
//    tertukar dan hanya 5 yang selisihnya >5 tahun. Yang mendesak 5, bukan 130.
```

**Itu angka HUB, dan HUB yang keliru memberikannya.** "125 / 5" memakai ambang
kasar *selisih >5 tahun*, bukan klasifikasi `bedaDob()` yang benar-benar dipakai
pagar itu. Jadi komentarnya menjelaskan pagar dengan ukuran yang bukan ukuran
pagar itu sendiri.

HUB mengukur ulang di prod, memakai `bedaDob()` kalian apa adanya:

| kategori | jumlah | nasib di pagar baru |
|---|---|---|
| `tertukar` | **108** | diterima ✅ |
| `selisih_sehari` | **3** | diterima ✅ |
| **`bertentangan`** | **19** | **ditolak** ⛔ |
| total | 130 | |

**Jadi pagar ini akan menolak 19 kandidat di prod, bukan 5.** Tolong ganti
komentarnya — angka di komentar harus angka yang dihasilkan kodenya sendiri.

---

## ℹ️ Yang perlu diketahui tentang ke-19 itu (bukan cacat, tapi jangan disalahbaca)

Sebagian dari 19 itu hampir pasti **orang yang sama dengan salah ketik tanggal**,
bukan orang berbeda:

```
Fidelia Florence Angkasa   kita=2005-09-09  kandidat=2005-09-19   (10 hari)
Winnie Hartanto            kita=1976-10-08  kandidat=1976-10-06   ( 2 hari)
Nayla Najma Tolail         kita=2012-02-12  kandidat=2012-02-15   ( 3 hari)
Louis Sofjar Gunawan       kita=1969-08-02  kandidat=1969-08-21   (19 hari)
```

`selisih_sehari` berambang **≤1 hari**, jadi salah ketik 2–19 hari jatuh ke
`bertentangan`. Yang lain memang patut dicurigai orang berbeda:

```
Haura Nazhifa Kinla        kita=2014-04-08  kandidat=2008-12-22   (6 tahun)
Sebastian Alexander        kita=2000-05-27  kandidat=2007-05-27   (7 tahun)
Keyla Afrillah Santi       kita=2009-04-08  kandidat=2004-04-08   (5 tahun)
```

**HUB tidak meminta ambangnya dilebarkan.** Prinsip kalian benar dan HUB setuju:
menahan tautan yang benar itu murah, menyatukan dua orang itu mahal. Dan
penolakannya **terlihat** (`--daftar-ambigu` + arahan ke `tinjau-tautan --dob`),
bukan hilang diam-diam — itu yang membuatnya bisa diterima.

Yang HUB minta cuma: **jangan laporkan 19 itu sebagai "19 orang salah"**. Yang
tepat: *19 ditahan untuk diperiksa manusia, sebagian besar kemungkinan salah
tanggal.* Persis triase yang kalian sendiri bangun.

---

## ✅ Saran tambahan kalian — HUB setuju, dengan satu catatan

> *"jalankan SQL backfill sekali lagi sesudah `match-patients`, karena tautan pid
> baru membuka sumber DOB yang tadinya tak terjangkau (terbukti: 11 pasien lagi)."*

Benar, **dan sekarang aman justru karena Temuan 3 sudah kalian perbaiki** — dengan
`DROP TABLE` yang lama, jalan kedua ini akan menghapus jejak pemulihan jalan
pertama. Urutan akhirnya jadi:

```
1. SQL backfill DOB
2. SQL multi-pid
3. deploy kode
4. omnicare:match-patients        (--dry-run dulu)
5. SQL backfill DOB  ← LAGI, memungut DOB yang baru terjangkau
6. omnicare:reconcile --perbaiki-batal, lalu --revisit
```

⚠️ Satu catatan kecil: `INSERT IGNORE` mempertahankan baris lama apa adanya,
jadi `variasi` milik pasien yang sudah tercatat **tidak dihitung ulang** di jalan
kedua. Untuk pasien yang sudah `diisi = 1` itu tidak jadi soal (tanggalnya sudah
terisi). Tapi kalau pid baru ternyata membawa DOB yang **berbeda** dari yang
terlanjur diisi, satu-satunya yang akan menangkapnya adalah
`tinjau-tautan --dob` — bukan backfill. Sebut itu di runbook supaya tidak ada
yang mengira jalan kedua ikut memverifikasi ulang.

---

## Yang HUB minta

1. Ganti angka "125 / 5" di komentar `MatchOmnicarePatients.php` jadi
   **108 `tertukar` · 3 `selisih_sehari` · 19 `bertentangan`** (diukur prod).
2. Commit dengan **daftar berkas eksplisit** — jangan sampai lima berkas
   Alur Persetujuan milik chat lain ikut terbawa.
3. Ralat "93 uji" jadi **70** di log-update/status-proyek.
4. Tambahkan catatan `variasi` tidak dihitung ulang di jalan kedua backfill.
