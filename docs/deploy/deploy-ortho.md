# Serah-Terima Deploy — Tracking Pasien Ortho (IT-2026-013)

Dibuat: 2026-10-02 · Untuk: rekan yang push/upload ke produksi
Domain: `app.sweettreats.id` · DB prod: `u695655413_app_sweettreat`

> **Kenapa berkas ini ada.** Langkah manual modul ini menumpuk lintas lima sesi
> dan sampai hari ini hanya tercatat di dalam komentar masing-masing berkas SQL.
> Catatan sesi `docs/log-sesi/2026-09-24-tracking-ortho-serah-terima.md` dibuat
> SEBELUM seluruh pekerjaan Oktober dan kini kurang empat berkas SQL serta dua
> perintah artisan yang wajib. Berkas inilah yang menggantikannya.
>
> ⚠ **Dua kegagalan di bawah ini SUNYI** — tidak ada pesan galat, halamannya
> terbuka normal, dan salahnya baru ketahuan berminggu-minggu kemudian:
> melewatkan `access:rebuild`, dan melewatkan `ort:rollup`.

---

## 0. Ringkasan

Halaman kerja harian Sales untuk menagih pasien behel supaya kontrol. Ia
**membaca** cermin rekam medis Omnicare dan **menulis** hanya ke tabel `ort_*`
miliknya sendiri. Satu arah; tidak pernah menulis balik ke Omnicare.

Deploy = unggah berkas → **11 SQL** → `access:rebuild` → **7 perintah isi data**
→ rsync aset. Tidak ada `composer install`, tidak ada dependensi baru, tidak ada
langkah build.

Lima cabang: Bintaro (29957) · Sunter (29958) · Kalideres (29959) ·
Cinere (30439) · Pamulang (30941).

---

## 1. PRASYARAT — jalankan SEBELUM DDL apa pun

```
database/sql/2026_09_24_ort_audit_collation_prod.sql
```

**Isinya SELECT saja, nol DDL.** Gunanya memeriksa satu hal yang tidak bisa
dilihat dari lokal: collation prod bercampur.

⚠ **Pertanyaan 3 di dalamnya yang menentukan. Kalau ia mengembalikan baris,
DDL `ort_*` TIDAK BOLEH dijalankan apa adanya** — JOIN lintas keluarga tabel
akan meledak `#1267` di prod sementara mulus di lokal.

Cara jalan: phpMyAdmin, satu blok per blok. Perintah SSH lengkap ada di header
berkasnya.

---

## 2. SKEMA — urut, jangan ditukar

| # | Berkas | Catatan |
|---|---|---|
| 1 | `2026_09_23_ort_tracking_schema.sql` | **Jalankan DUA KALI.** Keduanya harus lolos tanpa galat — itu bukti idempotensinya |
| 2 | `2026_09_23_ort_tracking_access.sql` | node menu + grant **BY LINK**, bukan by id node |
| 3 | `2026_10_01_ort_tahap_c.sql` | `install_is_estimated` · `median_gap_days` · `sch_basis` · `sch_basis_n` + backfill |
| 4 | `2026_10_02_ort_sebaran_irama.sql` | `gap_spread_pct` |
| 5 | `2026_10_03_ort_komentar_sch_date.sql` | hanya `COMMENT` pada `sch_date` |
| 6 | `2026_10_04_ort_komentar_unscheduled.sql` | hanya `COMMENT` pada `unscheduled` — meluruskan "datang tanpa reservasi" jadi "tidak cocok dengan jadwal mana pun"; sistem memang tidak tahu soal reservasi |
| 7 | `2026_10_05_ort_hasil_followup.sql` | `fu_result` + `fu_note` di `ort_control` — HASIL follow-up (dihubungi · tidak respon · minta jadwal ulang · menolak · salah nomor) + catatan bebas. Tanpa backfill: follow-up lama memang tidak punya hasil |
| 8 | `2026_10_06_ort_dokter_per_kontrol.sql` | `doctor_ref_id` + `doctor_name` di `ort_control` — dokter per KUNJUNGAN. Dari sini kolom "Dokter" jadi dinamis dan riwayat kontrol dapat nama dokter per baris. **Butuh langkah isi data tambahan** — lihat 3.7 |
| 9 | `2026_10_06_ort_case_sales_active.sql` | `sales_active` + tiga kolom jejak di `ort_case`, plus `idx_ort_case_sales`. Penilaian Sales "Active/Inactive" — **sumbu TERPISAH dari `status`**, yang itu fakta klinis dan diisi mesin. Semua baris lama jadi Active |
| 10 | `2026_10_07_ort_riwayat_followup.sql` | tabel baru `ort_followup` — satu baris per pasien per hari, UNIQUE (`ort_case_id`, `fu_date`). **Tanpa backfill, dan itu disengaja**: `ort_control.fu_date` hanya menyimpan follow-up TERAKHIR, jadi memasukkannya sebagai satu-satunya baris akan membuat pasien yang ditelepon enam kali terlihat ditelepon sekali |

⚠ Nomor 11 adalah `2026_09_24_ort_audit_collation_prod.sql` di bagian 1 —
SELECT saja, dijalankan paling awal. Itu sebabnya ringkasannya berbunyi 11.

### ⚠ Sesudah berkas #2, SEGERA:

```
php artisan access:rebuild
```

lalu **logout–login**. Struktur akses sidebar hidup di
`storage/app/group_access/*.txt` yang tidak ikut git. Kalau langkah ini
terlewat, AuthGuard menolak semua dan hasilnya **404 di SEMUA halaman**, bukan
cuma modul ini.

### Verifikasi skema

```sql
-- harus 0 baris: modul ini sengaja nol FK (collation prod campur)
SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'ort_%'
   AND CONSTRAINT_TYPE = 'FOREIGN KEY';

-- harus ada: idx_ort_control_worklist, idx_ort_case_backlog, idx_ort_case_sales
SHOW INDEX FROM ort_control; SHOW INDEX FROM ort_case;

-- Kolom Oktober gelombang kedua — harus 7 baris
SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND ((TABLE_NAME = 'ort_control' AND COLUMN_NAME IN ('doctor_ref_id','doctor_name'))
     OR (TABLE_NAME = 'ort_case' AND COLUMN_NAME LIKE 'sales_active%'))
 ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- Tabel riwayat follow-up ada, dan UNIQUE-nya terpasang
SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ort_followup'
   AND INDEX_NAME = 'uq_ort_followup_hari';
```

❌ **`2026_09_29_ort_cermin_tiruan.sql` TIDAK dijalankan di prod.** Ia penyemai
cermin tiruan untuk lokal; di luar lokal ia no-op, dan menyebutnya di runbook
hanya mengundang orang menjalankannya.

---

## 3. ISI DATA — urut, dan butir ketiga yang paling mudah salah

```bash
# 3.1  Semai kasus dari rekam medis — PRATINJAU dulu, periksa angkanya
php artisan ort:seed-cases --sejak=2020-01-01
php artisan ort:seed-cases --sejak=2020-01-01 --commit

# 3.2  ⚠ Tarikan mundur PERTAMA: isi FAKTA kunjungan saja
php artisan ort:pull-visits --sejak=2020-01-01 --tanpa-usulan

# 3.3  Hitung ringkasan — WAJIB, gagalnya sunyi
php artisan ort:rollup

# 3.4  Hitung jatuh tempo untuk baris yang sudah ada
php artisan ort:rapikan-usulan                 # pratinjau
php artisan ort:rapikan-usulan --commit

# 3.5  Tautkan reservasi yang sudah ada — pratinjau dulu
php artisan ort:tautkan-reservasi            # pratinjau — periksa tabel "Contoh pasangan"
php artisan ort:tautkan-reservasi --commit
# Sejak 2026-10-04 perintah ini menyapu TIGA jenis baris, bukan satu:
#   RIWAYAT (visit_date ±3 hari) · TIDAK HADIR (sch_date ±3 hari, termasuk
#   reservasi batal) · TERBUKA (reservasi mendatang ≤120 hari / waitlist,
#   batal dibuang, layanan behel diutamakan). Angka "Cakupan: N%" HANYA
#   mengukur RIWAYAT — itulah yang dibandingkan ke 91% prod.
#   --sejak/--sampai hanya menyaring RIWAYAT.
```

### Kenapa 3.2 pakai `--tanpa-usulan`

Permintaan tegas audit HUB 1 Okt. Tanpa bendera itu, tarikan mundur
berbulan-bulan melahirkan ribuan baris jatuh tempo sekaligus di layar Sales
pada hari pertama. Tarikan malam berikutnya (cron 04:00, jendela 14 hari)
mengisinya sendiri secara bertahap.

### Kenapa 3.3 tidak boleh dilewat

`median_gap_days` dan `gap_spread_pct` lahir NULL. Tanpa rollup, jatuh tempo
diam-diam jatuh kembali ke jarak baku 30 hari untuk **semua** pasien dan
kolom irama berbunyi "belum cukup kunjungan" di seluruh layar — **tanpa satu
pun pesan galat.**

Pemeriksa kesehatannya:

```bash
php artisan ort:rollup --verify     # keluar bukan-nol kalau ada yang melenceng
```

### 3.7 — Dokter dinamis: segarkan snapshot (BARU, 2026-10-06)

```
php artisan ort:pull-visits      # mengisi doctor_ref_id per kontrol
php artisan ort:rollup           # memilih "yang terakhir menangani"
```

⚠ **Kegagalan SUNYI yang ketiga.** Sesudah SQL #8, kolom `ort_control`-nya ada
tapi masih NULL semua — jadi `OrtRollup` tidak menemukan satu calon pun dan
**mempertahankan snapshot lama**. Halamannya terbuka normal, kolom Dokter
terisi, dan tidak ada pesan galat apa pun; yang ditampilkan cuma dokter yang
MEMASANG bracket, bukan yang sedang menangani. Persis keadaan yang SQL itu
dibuat untuk diperbaiki.

Dua perintah di atas yang mengisinya. `ort:pull-visits` menulis dokter ke tiap
kontrol yang punya kunjungan; `ort:rollup` lalu memilih yang paling baru di
antara kunjungan dan reservasi.

Verifikasi — kedua angka harus naik dari nol:

```sql
SELECT COUNT(*) AS kunjungan,
       SUM(doctor_ref_id IS NOT NULL) AS ada_dokter
  FROM ort_control WHERE visit_date IS NOT NULL;
```

### Verifikasi isi data

```sql
SELECT COUNT(*) total,
       SUM(median_gap_days IS NOT NULL) ber_irama,
       SUM(gap_spread_pct  IS NOT NULL) ber_sebaran,
       ROUND(SUM(install_is_estimated)/COUNT(*)*100,1) persen_pasang_perkiraan
  FROM ort_case WHERE status='aktif';
```

`persen_pasang_perkiraan` seharusnya mendekati **45,7%** — angka yang diukur
HUB di prod. Jauh meleset berarti penyemainya membaca rentang yang salah.

⚠ **Sesudah tarikan pertama, UKUR SEBARAN JATUH TEMPO.** Modul ini sengaja
tidak punya pagar kapasitas, dan ini satu-satunya kesempatan membuktikan
keputusan itu benar di volume prod:

```sql
SELECT sch_date, COUNT(*) n FROM ort_control
 WHERE is_proposal = 1 AND closed_at IS NULL
 GROUP BY sch_date ORDER BY n DESC LIMIT 5;
```

Di lokal paling padat **2 orang** per tanggal. Kalau di prod ada tanggal
berisi puluhan pasien dalam satu cabang, lapor — pagarnya mudah dipasang
kembali karena mesinnya masih utuh. Lihat
`docs/handoff/balasan-ortho-hub-2026-10-02.md`.

---

## 4. ASET — rsync DIBATASI

```bash
rsync -av --delete laravel/public/assets/ public_html/assets/
```

`public_html` prod itu **salinan manual, bukan symlink**. Modul ini menambah
`public/assets/css/ort-tracking.sweettreats.css`; tanpa rsync, seluruh halaman
tampil tanpa CSS.

⚠ **Batasi ke `assets/`.** rsync seluruh `public/` akan menimpa berkas lain di
`public_html` yang tidak berasal dari repo.

---

## 5. Cron

Sudah terdaftar di `app/Console/Kernel.php`, tidak perlu disentuh:

| Jam | Perintah |
|---|---|
| 02:00 | `omnicare:sync` (rantai Omnicare, prasyarat) |
| 02:45 | `omnicare:match-patients` |
| 03:00 | `omnicare:reconcile` |
| **04:00** | **`ort:pull-visits`** — jendela 14 hari, semua cabang |

Urutannya disengaja: tarikan ortho harus berjalan sesudah rantai Omnicare
tuntas. Log: `storage/logs/ortho-cron.log`.

**Konsekuensi yang perlu diketahui Sales:** pasien yang datang pukul 10:00
hari ini baru terlihat di kolom Datang besok subuh — **jeda sekitar 18 jam**.
Status "Selesai" di modul Reservasi tidak mempercepatnya; modul ini sengaja
hanya mempercayai rekam medis.

---

## 6. Mengurungkan

Modul ini tidak mengubah tabel milik modul lain. Untuk mencabutnya:

1. Cabut grant menu (`group_access` untuk node ber-link `tracking-ortho`),
   lalu `php artisan access:rebuild`.
2. Tabel `ort_*` boleh ditinggal apa adanya — tidak ada yang membacanya selain
   modul ini.
3. `ort:seed-cases --hapus-seed --commit` membuang kasus hasil penyemaian saja.

⚠ Jangan `DROP` tabel `ort_*` kalau Sales sudah memakai halamannya: kolom
`fu_date` dan `reservation_id` berisi pekerjaan yang tidak ada di tempat lain.

---

## 7. Yang BELUM selesai saat dokumen ini ditulis

| Hal | Status |
|---|---|
| Impor follow-up lama dari Google Sheets | **menunggu keputusan Ikhsan** |
| Pemetaan `ort_case.doctor_ref_id` → `raw_doctor` | bolong; di dev hanya 12 dari 42 terpetakan. Akibatnya pemilih hari praktik dokter sering kosong dan Sales mengisi tanggal sendiri |
| Sebaran jatuh tempo di volume prod | belum pernah diukur — lihat §3 |
