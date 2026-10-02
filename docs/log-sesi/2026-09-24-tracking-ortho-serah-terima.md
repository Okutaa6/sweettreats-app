# 2026-09-24 — Tracking Ortho: serah-terima untuk sesi berikutnya

> ⚠ **CATATAN INI SUDAH DILAMPAUI (2 Okt 2026).** Jangan dipakai sebagai
> daftar langkah deploy — §8 di bawah kurang **empat berkas SQL** dan **dua
> perintah artisan** yang kini wajib, dan §"Keputusan yang mengikat" masih
> menyebut tombol "Konfirmasi jadwal" beserta rute `kontrol/{id}/konfirmasi`
> yang sudah dihapus.
>
> Yang berlaku sekarang:
> - **`docs/deploy/deploy-ortho.md`** — runbook deploy lengkap dan berurutan
> - **`docs/handoff/balasan-ortho-hub-2026-10-02.md`** — apa yang berubah
>   sesudah audit HUB 1 Okt, dan kenapa
>
> Berkas ini tetap berharga untuk bagian "Jebakan yang sudah menggigit".

**Branch** `feat/komisi-dokter` · **Status** belum commit · **Prod** belum disentuh sama sekali

> Catatan ini ditulis untuk **dibaca sesi lain sebelum melanjutkan**. Bacanya
> utuh — bagian "Keputusan yang mengikat" dan "Jebakan yang sudah menggigit"
> adalah yang paling mahal kalau dilewati, karena git tidak mencatatnya.

---

## 1. Apa modul ini

Tracking pasien behel (ortho). Sales melacak jadwal kontrol pasien ortho yang
selama ini dikelola di Google Sheets per cabang. Dari 1.639 pasien ortho aktif
2026: **104 pernah kontrol di cabang lain** sehingga lenyap dari sheet
cabangnya, dan **581 (35%) jarang kontrol** tapi mustahil dihitung mata.

- Segmen URL: **`/tracking-ortho`** · prefix tabel: **`ort_`**
- Rancangan lengkap: `docs/brief-desain/design-brief-tracking-ortho.md`
  (+ `revisi-3-tracking-ortho.md`, `revisi-3-tracking-ortho-transfer.md`)

---

## 2. Keputusan yang mengikat — JANGAN diubah tanpa bicara dulu

| Hal | Keputusan |
|---|---|
| Pekerjaan Sales | **Hanya dua**: follow-up dan buat reservasi. Plus satu klik konfirmasi jadwal |
| Kolom Datang | **Diisi mesin** dari cermin rekam medis tiap malam. Tidak ada tombol "Catat Kunjungan" |
| Jadwal | **Lahir satu per satu.** Satu kasus paling banyak punya **satu** kontrol terbuka |
| Auto "Tidak hadir" | **DIBATALKAN** — menyimpang dari HANDOFF.md §1. Mesin hanya mengisi FAKTA (kunjungan); ketidakhadiran ditutup manusia |
| Reservasi | Pakai form Reservasi yang sudah ada, ter-prefill. `ort_control` simpan `reservation_id` saja |
| Cabang | Terkunci ke cabang akun. Tab cabang lain **lihat-saja** |
| Aksi tulis | Langsung simpan + toast + **Urungkan** 90 detik. Bukan dialog "Anda yakin?" |

### Kendala teknis yang membentuk rancangan

1. **Collation prod campur** (`utf8mb4_unicode_ci` vs `utf8mb4_uca1400_ai_ci`).
   FK ke `raw_*` **ditolak di prod padahal lolos di lokal** → **nol FOREIGN KEY,
   nol JOIN lintas keluarga tabel**. Satu-satunya `->join()` di seluruh modul
   adalah `ort_control ↔ ort_case` (sekeluarga). Ke `raw_*`/`omnicare_*` selalu
   kueri terpisah + `whereIn`.
2. Skema lewat `database/sql/*.sql` manual, idempotent. **Bukan** `artisan migrate`.
3. Tanpa window function, tanpa CTE.
4. Zona warna **cokelat** (operasional). Ikon **Font Awesome 5** (nama gaya FA6
   render kotak kosong).

---

## 3. Status per tahap

| Tahap | Isi | Status |
|---|---|---|
| **0** | 2 SQL · `access:rebuild` · model/route/controller | ✅ **kecuali audit collation prod** (terhalang, lihat §6) |
| **1** | `OrtBranches` · `OrtItemRules` · `ort:seed-cases` | 🟡 `OrtBranches` ✅ · `OrtItemRules` ✅ · **`ort:seed-cases` BELUM ADA** |
| **2** | Semai · tab List · kartu · antrean penjadwalan | ✅ (data peraga; penyemai sungguhan belum ada) |
| **3** | Drawer detail · baris terbuka · 4 aksi tulis · toast/Urungkan | ✅ **tuntas** |
| **4** | `ort:pull-visits` + jadwal malam | ✅ **tuntas** (lihat peringatan §6) |
| **5** | Sambungan Reservasi | 🟡 prefill ✅ · tulis-balik ✅ · **`OrtReservationObserver` BELUM ADA** |
| **6** | Transfer · tab cabang lain · pengingat · kalender · roster | 🟡 tampilan ✅ semua · **aksi tulis transfer & pengingat BELUM** |

**Ditunda sejak awal, dan katakan terang-terangan kalau ditanya:** ekspor Excel &
laporan, reminder WhatsApp otomatis, penggabungan pasien ber-dua-`pid`.

---

## 4. Yang sudah jalan (boleh dipakai, sudah diuji)

**Halaman** `/tracking-ortho` — 3 tab (List · Kalender · Pasien Ortho), sub-tab
kegentingan, tab cabang lain lihat-saja, kartu ringkas, antrean penjadwalan
dua tingkat, filter, paginasi.

**Lapisan dibuka tanpa memuat ulang halaman.** Empat lapisan memakai satu mesin
(`?frag=` di controller + satu blok JS di `list.blade.php`): drawer detail,
lembar 410px, drawer Tambah, drawer harian kalender. Luncuran mulai di **~2 ms**;
rangka kosong dipasang seketika lalu diisi.

**Aksi tulis yang HIDUP** — semuanya + toast + Urungkan:

| Aksi | Route |
|---|---|
| Catat Follow-up | `POST kontrol/{id}/follow-up` |
| Konfirmasi jadwal | `POST kontrol/{id}/konfirmasi` |
| Geser usulan | `POST kontrol/{id}/geser` |
| Tandai tidak hadir | `POST kontrol/{id}/tidak-hadir` |
| Jadwalkan kontrol | `POST kasus/{caseId}/jadwalkan` |
| Urungkan | `POST urungkan/{token}` |

**Buat Reservasi** — lembar → halaman daftar Reservasi → **side-drawer** form
ter-prefill (pasien, cabang, dokter, tanggal, keterangan) → simpan → `ort_control`
tertaut balik.

**`ort:pull-visits`** — tarikan malam, terjadwal **04:00**.

---

## 5. Peta berkas

```
app/Http/Controllers/Crud/OrthoController.php   738 baris  (index, drawerDetail, 5 aksi tulis, fragmen)
app/Services/Ortho/OrtBranches.php              127        peta cabang ter-cache, tanpa join
app/Services/Ortho/OrtQuery.php                 605        semua kueri baca
app/Services/Ortho/OrtRollup.php                174        SATU-SATUNYA penulis kolom rollup
app/Services/Ortho/OrtItemRules.php             232        ⚠ aturan nama item ortho — baca komentarnya
app/Console/Commands/OrtPullVisits.php          464        tarikan malam
app/Console/Commands/OrtSeedDemo.php                       data peraga, LOKAL saja
resources/views/crud/ortho/                     17 berkas  list.blade.php + belum-siap + 15 partial
public/assets/css/ort-tracking.sweettreats.css  64 KB      prefix .ort-
database/sql/2026_09_23_ort_tracking_schema.sql            4 tabel, nol FK
database/sql/2026_09_23_ort_tracking_access.sql            node menu + grant BY LINK
database/sql/2026_09_24_ort_audit_collation_prod.sql       audit prod, HANYA SELECT
```

**Berkas di LUAR modul yang ikut berubah** (semuanya dengan izin, jangan tambah
lagi tanpa bicara):

```
routes/web.php                                   grup tracking-ortho + 6 route
app/Console/Kernel.php                           jadwal ort:pull-visits 04:00
app/Http/Controllers/Crud/ReservationController.php   jembatan ?ort_control_id + tulis-balik (+123 baris, 0 dihapus)
resources/views/crud/reservation/_form.blade.php      hidden ort_control_id + spanduk konteks
resources/views/crud/reservation/list.blade.php       auto-buka drawer dari ?ort_control_id
resources/views/crud/report-referral/index.blade.php  (tak terkait ortho) tombol Reset diberi label
```

---

## 6. ⚠ DUA HAL YANG MENGHALANGI, DAN KENAPA

### a. Audit collation prod belum pernah jalan

`~/.ssh/rblas_ga` **tidak ada** di mesin ini, jadi tidak ada jalur apa pun ke
prod. Berkasnya sudah siap dan terbukti jalan (diuji di lokal):

```
database/sql/2026_09_24_ort_audit_collation_prod.sql
```

**Pertanyaan nomor 3 adalah penentunya**: apakah `raw_customer.id`,
`raw_cus_reservation.id`, `raw_branch.omnicare_place_id`, `raw_doctor.id`
berkoleksi `utf8mb4_unicode_ci`. Kosong = DDL boleh jalan apa adanya.

Temuan saat mengujinya di lokal, yang **membantah catatan lama** ("collation
campur cuma di prod"): lokal juga campur — **19 tabel `utf8mb4_general_ci`** —
dan **default database-nya `general_ci`**. Artinya `COLLATE` eksplisit di DDL
`ort_*` bukan kehati-hatian berlebih, itu yang menahannya.

Catatan penting: **menarik dump prod TIDAK menggantikan audit ini.**
`scripts/pull-prod-db.sh` sengaja menurunkan `uca1400 → unicode_ci` saat impor,
jadi salinan prod di lokal selalu terlihat seragam.

### b. `OrtItemRules` belum pernah bertemu katalog item sungguhan

`omnicare_sales_detail` = **0 baris** di lokal. Aturannya disusun dari brief
konsultan, bukan dari data. Jebakannya:

```
"Lepas Bracket"       → perawatan SELESAI
"Bracket Metal Lepas" → bracket copot, dipasang ulang — perawatan JALAN TERUS
                        (1.396 + 175 baris)
```

Pembedanya **posisi**, bukan isi — polanya dijangkarkan di awal
(`^lepas\s+bracket`), plus daftar pengecualian sebagai lapis kedua.

**SEBELUM `ort:pull-visits` dipercaya menulis apa pun di prod**, jalankan ini —
tidak menulis apa pun:

```bash
php artisan ort:pull-visits --uji-aturan                        # 24 fixture
php artisan ort:pull-visits --daftar-item --sejak=2026-01-01    # katalog nyata
php artisan ort:pull-visits --dry-run  --sejak=2026-01-01       # angka, tanpa tulis
```

Periksa baris berklasifikasi `lepas` **satu per satu**. Salah baca satu kata di
situ = ribuan pasien ditandai selesai di hari pertama.

---

## 7. Jebakan yang SUDAH menggigit di sesi ini — jangan ulangi

1. **PHP mengubah kunci array numerik jadi `int`.** `array_key_first($peta)`
   mengembalikan `29957` (int), bukan `'29957'`. Semua `===` terhadap kolom
   `place_id` (varchar) jadi `false`. Kena **4×**: dropdown cabang, tab cabang
   lain tidak menyala, pil roster, dan **403 di drawer untuk user tanpa cabang**.
   Selalu `(string)` di kedua sisi.
2. **Sidebar aplikasi `position: sticky`.** Mengunci gulir dengan
   `overflow:hidden` di `<html>`/`<body>` **mematikan sticky** — sidebar terjun
   sejauh posisi gulir (terukur −352px) dan halaman bergeser 15px karena
   scrollbar hilang. Kunci gulir di modul ini menahan **peristiwa**
   (`wheel`/`touchmove`), bukan tata letak. Jangan dikembalikan.
3. **`onclick="event.stopPropagation()"` di tombol baris membunuh pendengar
   `document`.** Penyergap tautan lapisan harus di fase **capture**. Gejalanya
   khas: satu tombol memuat ulang halaman sementara yang lain mulus.
4. **Anak flex menciut.** `.ort-drawer__body` kolom flex + `.ort-matrix`
   `overflow:hidden` = baris kontrol ke-5 dst terpotong **tanpa scrollbar, tanpa
   tanda**. Ditahan `flex-shrink: 0` pada anak langsung.
5. **Tiga deklarasi `padding` untuk `.ort-tr`** (baris 137, 487, 821) — yang
   terakhir menang. Aturan di tengah berkas tidak pernah terlihat efeknya.
   Blok penyesuaian ada di **akhir** berkas CSS. Jangan menyisipkan di tengah.
6. **`back()` sesudah aksi dari lembar membuka lembarnya lagi** (URL masih
   membawa `?sheet=`), menutupi toast. Dipakai `pulang()` yang membuang kunci
   lapisan tapi mempertahankan path.
7. **`@php return; @endphp` di Blade membunuh seluruh `@section`.**
8. **`ort_case.doctor_ref_id` itu kode Omnicare (`DOK1`), BUKAN `raw_doctor.id`.**
   Dioper mentah ke form Reservasi, JS-nya mengosongkan pilihan Cabang.
9. **`value` lebih tua dari `min` pada `<input type=date>`** = input tidak sah,
   form diam saja saat dikirim, tanpa pesan apa pun.
10. **Headless Chrome memaksa `prefers-reduced-motion: reduce`.** Uji animasi
    tanpa `Emulation.setEmulatedMedia` akan selalu menunjukkan 0 px.

---

## 8. Langkah manual yang belum jalan

- [ ] `database/sql/2026_09_23_ort_tracking_schema.sql` — lokal ✅ · dev ⬜ · prod ⬜
- [ ] `database/sql/2026_09_23_ort_tracking_access.sql` — lokal ✅ · dev ⬜ · prod ⬜
- [ ] `database/sql/2026_09_24_ort_audit_collation_prod.sql` — lokal ✅ · **prod ⬜ (ini yang menghalangi)**
- [ ] `php artisan access:rebuild` — lokal ✅ · dev ⬜ · prod ⬜
- [ ] rsync `public/assets/**` ke `public_html` prod — ⬜ (CSS baru 64 KB)
- [ ] **belum ada satu pun commit** untuk modul ini

---

## 9. Sisa pekerjaan, urut saran

1. **`OrtReservationObserver`** (Tahap 5) — kolom Reservasi sekarang **snapshot
   saat dibuat**. Tanggal diubah atau reservasi dibatalkan dari modul Reservasi
   → baris ortho belum ikut berubah. Sudah diperiksa: ketiga jalur tulis
   (`update()`, `updateInline()`, `destroy()`) lewat model, dan tidak ada
   mass-update yang menembusnya — jadi `ReservationController` **tidak perlu
   disentuh** untuk ini.
2. **`ort:seed-cases`** (Tahap 1) — penyemai sungguhan, `--dry-run` default.
   Butuh dump prod / akses data nyata.
3. **Tahap 6** — lembar Pindahkan (tulis `ort_transfer`), tombol Ingatkan
   (tulis `ort_reminder`), `Tandai Selesai (bracket dilepas)`.
4. **`ort:rollup --verify`** — ada di rencana, belum dibuat. Membandingkan kolom
   rollup dengan hitungan sebenarnya. Penting karena `open_control_id`,
   `visit_count_180d`, `owner_place_id` adalah turunan yang disimpan: kalau ada
   jalur tulis yang lupa `OrtRollup::refresh()`, **halamannya bohong tanpa error**.
5. Simpan drawer **Tambah Pasien Ortho**.

**Tombol yang masih sengaja mati** (bergaya `--mati`, bukan bug):
`Tandai Selesai`, `Ingatkan <cabang>` (2 tempat), simpan lembar **Pindahkan**,
simpan drawer **Tambah Pasien**.

---

## 10. Cara memeriksa hasilnya

Prasyarat lokal: `php artisan ort:seed-demo --jumlah=26 --paksa` (cermin Omnicare
kosong di lokal, jadi penyemai sungguhan tidak menghasilkan apa-apa di sini).

```
/tracking-ortho                                  List · Semua
/tracking-ortho?urgensi=terlewat                 sub-tab kegentingan
/tracking-ortho?kartu=backlog                    antrean penjadwalan
/tracking-ortho?cabang=30439                     tab cabang lain (lihat-saja)
/tracking-ortho?tab=kalender                     kalender
/tracking-ortho?tab=kalender&hari=2026-09-24     drawer harian
/tracking-ortho?tab=pasien                       roster Daftar
/tracking-ortho?tab=pasien&tampil=kartu          roster Kartu
/tracking-ortho?tambah=1                         drawer Tambah Pasien
/tracking-ortho/{caseId}                         drawer detail
```

---

## 11. File tersentuh

Keluaran `git status --porcelain`, disaring ke yang berkaitan dengan modul ini.
`git diff --name-only origin/main` tidak bisa dijalankan — `git fetch` timeout.

```
 M app/Console/Kernel.php
 M app/Http/Controllers/Crud/ReservationController.php
 M resources/views/crud/report-referral/index.blade.php
 M resources/views/crud/reservation/_form.blade.php
 M resources/views/crud/reservation/list.blade.php
 M routes/web.php
?? app/Console/Commands/OrtPullVisits.php
?? app/Console/Commands/OrtSeedDemo.php
?? app/Http/Controllers/Crud/OrthoController.php
?? app/Models/OrtCase.php
?? app/Models/OrtControl.php
?? app/Models/OrtReminder.php
?? app/Models/OrtTransfer.php
?? app/Services/Ortho/            (OrtBranches, OrtItemRules, OrtQuery, OrtRollup)
?? database/sql/2026_09_23_ort_tracking_access.sql
?? database/sql/2026_09_23_ort_tracking_schema.sql
?? database/sql/2026_09_24_ort_audit_collation_prod.sql
?? docs/brief-desain/design-brief-tracking-ortho.md
?? docs/brief-desain/revisi-3-tracking-ortho-transfer.md
?? docs/brief-desain/revisi-3-tracking-ortho.md
?? public/assets/css/ort-tracking.sweettreats.css
?? resources/views/crud/ortho/   (17 berkas)
```

⚠ `git status` juga menampilkan banyak berkas lain (WhatsApp, checklist,
pettycash, `scripts/`, `CLAUDE.md`) — itu **dari sesi-sesi sebelumnya**, bukan
pekerjaan modul ini. Jangan ikut di-commit dalam satu commit ortho.
