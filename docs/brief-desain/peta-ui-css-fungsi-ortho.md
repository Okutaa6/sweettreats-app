# Peta UI ⇄ CSS ⇄ Fungsi — Tracking Pasien Ortho

**3 Oktober 2026 · pemetaan satu-banding-satu**

> Tiga dokumen lain menjawab satu sisi masing-masing:
> `brief-tracking-ortho-berjalan.md` (apa yang dilihat Sales),
> `design-system-tracking-ortho.md` (bagaimana ia digambar),
> `alur-fungsi-tracking-ortho.md` (apa yang terjadi saat ditekan).
>
> **Berkas ini menyambungkan ketiganya.** Untuk tiap elemen di layar:
> kelas CSS yang menggambarnya, dan fungsi/kolom yang mengisinya.
>
> Dipakai saat: mengubah satu elemen dan ingin tahu apa lagi yang ikut kena.

---

## Cara membaca

```
ELEMEN DI LAYAR  →  berkas view  →  kelas CSS  →  fungsi / kolom DB
```

Kolom **Fungsi/kolom** menyebut sumber datanya, bukan sekadar nama variabel.
Kalau sebuah elemen diisi hitungan (bukan kolom tersimpan), rumusnya disebut.

⚠ Dua jebakan yang berulang di seluruh berkas ini:

1. **Tabel memakai query builder (`stdClass`), panel memakai Eloquent.**
   `OrtQuery::daftar()` punya `select()` eksplisit — kolom yang tidak ikut
   di-select melempar *"Undefined property"*, bukan `null` diam-diam. Sudah
   menjatuhkan halaman sekali saat `fu_result` mulai ditampilkan.
2. **Token `--ort-*` di-scope ke `.ort`.** Apa pun yang dirender di luar
   pembungkus itu kehilangan seluruh warnanya.

---

## 1. Kerangka halaman

| Elemen | View | Kelas CSS | Fungsi / kolom |
|---|---|---|---|
| Pembungkus + rumah token | `list.blade.php:63` | `.ort` | — (31 token, CSS:16–46) |
| Baris atas + aksi kanan | `list.blade.php` | `.ort-toprow` · `.ort-topact` | — |
| Pita navigasi | `_navrow` | `.ort-navrow` · `__kiri` · `__tabs` | `$tab` ← whitelist `TABS` di `konteksList()` |
| Tab tingkat atas | `_navrow` | `.st-view-tabs` · `.st-view-tab` **(bersama)** | `?tab=list\|kalender\|pasien` |
| Sub-tab kegentingan | `_nav_urgensi` | `.ort-utabs` · `.ort-utab` · `__dot` · `__n` | `OrtQuery::hitungUrgensi()` |
| Sub-tab antrean | `_nav_antrean` | sama | `OrtQuery::hitungAntrean()` |
| Hint kanan sub-tab | keduanya | `.ort-urow__hint` | — ⚠ memakai `--ort-faint` |
| Garis muat | `list.blade.php:334` | `.ort-loadbar` | JS `bar()` — **disuntik ke `.ort`**, bukan `<body>` |

---

## 2. Tab cabang

| Elemen | View | Kelas CSS | Fungsi / kolom |
|---|---|---|---|
| Pil cabang utama (terkunci) | `_btabs` | `.ort-btab` + `__star` `__count` | `resolveBranch()` → `$namaCabang`, `$kartu['aktif']` |
| Tombol pemilih (lintas cabang) | `_btabs` | `.ort-btab--pick` + `__caret` | `$terkunci === false` |
| Menu pemilih | `_btabs` | `.ort-btabpick` + `__menu` `__head` `__item` `__nama` `__star` | `OrtQuery::hitungPerCabang()` |
| **Kelompok "di cabang lain"** | `_btabs` | `.ort-btablain` | `OrtQuery::hitungPerCabangLain()` |
| Tombol pembuka kelompok | `_btabs` | `.ort-btab--more` + `__eye` `__chev` | `$perCabangLain` tidak kosong |
| Lapis beranimasi | `_btabs` | `.ort-branchtabs__wrap` | JS toggle `is-open` + `localStorage` |
| Lapis pemotong | `_btabs` | `.ort-branchtabs__more` | — |
| Pil cabang lain | `_btabs` | `.ort-btab` (datar saat terbuka) | `?cabang=` → `OrtQuery::diCabangLain()` |

⚠ **Pil cabang utama sengaja DI LUAR `.ort-btablain`.** Ia bukan bagian
kelompok itu, dan uji `OrtoPemilihCabangTest` menjaganya lewat DOMXPath.

⚠ `.ort-btablain` punya `padding: 5px; margin: -6px 0` **di kedua keadaan** —
jejak tata letaknya 31px, sama dengan tinggi pil, jadi membuka strip tidak
pernah mendorong halaman turun.

---

## 3. Kartu ringkas

| Kartu | Kelas CSS | Fungsi / kolom |
|---|---|---|
| Jadwal minggu ini | `.ort-card` + `__top` `__icon` `__label` `__num` `__sub` `__chev` | `kartu()['minggu_ini']` · `['sudah_rsv']` · `['belum_rsv']` |
| Terlewat | `.ort-card--warn` | `kartu()['terlewat']` · `['terlewat_lama']` · `['persen_terlewat']` |
| Belum punya jadwal | `.ort-card` | `kartu()['backlog']` · `['hilang']` |

Ketiganya dari **dua kueri**, bukan enam — `OrtQuery::kartu()` memakai
`SUM(CASE)` untuk dua kartu pertama, dan `ort_case` untuk kartu ketiga.

⚠ `is_proposal = 0` sengaja **dibuang** dari hitungan Terlewat: jatuh tempo
yang lewat berarti pasien telat, siapa pun yang menuliskan tanggalnya.

---

## 4. Spanduk

| Spanduk | Kelas CSS | Fungsi / kolom |
|---|---|---|
| Hari pertama | `.ort-banner` + `__icon` `__title` `__text` `__body` | `kartu()['aktif'] > 0 && backlog === aktif` |
| Kolom belum dipakai | `.ort-banner--info` | `OrtQuery::kolomBelumDipakai()` → `['fu' => bool, 'rsv' => bool]` |

⚠ `--info` ditaruh di **akhir berkas CSS** supaya menang atas penimpaan
`__icon` di blok sebelumnya.

---

## 5. Panel filter

| Elemen | Kelas CSS | Fungsi / kolom |
|---|---|---|
| Panel | `.ort-filter` + `__r1` `__r2` `__act` | `$filter` ← `konteksList()` |
| Kotak cari | `.ort-search` + `.ort-inp` | `?q=` → nama **atau telepon**, tidak pernah RM |
| Dropdown dokter | `.ort-inp` | `OrtQuery::dokterDiCabang()` |
| Dropdown status (10 pilihan) | `.ort-inp` | diterjemahkan `OrtQuery::dasar()` |
| Optgroup "Kerja hari ini" | — | *Belum pernah di-FU* · *Saya FU hari ini* |
| Chip filter aktif | `.ort-chips` · `__label` · `.ort-chipf` | `array_filter(Arr::except($filter, ['saya']))` |
| Tombol buka panel | `.ort-obtn` + `__n` | — |

⚠ `'Saya FU hari ini'` memakai `session('name')`, bukan masukan pengguna; kalau
kosong ia `whereRaw('1 = 0')` — lebih baik kosong daripada menampilkan
follow-up orang lain.

⚠ `'Terlewat 2× atau lebih'` dihitung **siklus** di SQL, menumpang
`idx_ort_case_backlog`:
`DATEDIFF(hari_ini, k.last_visit_date) >= 2 × LEAST(60, GREATEST(21, COALESCE(median_gap_days, 30)))`

---

## 6. Tabel daftar kerja — kolom per kolom

Grid final (3 Okt 2026, TUJUH track):
`minmax(186px,1.5fr) 146px 150px minmax(190px,1.1fr) 132px 118px 30px`,
`min-width: 1044px` (CSS:1470 — deklarasi yang MENANG; yang di CSS:304 adalah
dasarnya, dan keduanya wajib diubah bersamaan).
Pembungkus gulir `.ort-tablewrap` adalah
**satu-satunya** mekanisme responsifnya — kolomnya tidak pernah berubah.

| # | Kolom | Kelas CSS | Fungsi / kolom DB |
|---|---|---|---|
| 1 | **Pasien** | `.ort-name` · `.ort-meta` · `.ort-clamp2` | `patient_name` · `omnicare_pid` · `patient_phone` |
| | telepon → WhatsApp | `.ort-wa` · `--bad` | `Phone::toWa()` — `--bad` kalau nomor tidak valid |
| | badge transfer | `.ort-badge--transfer` | `OrtQuery::masukDari()` |
| | badge tanpa jadwal | `.ort-badge--walkin` | `unscheduled` ⚠ **bukan** "tanpa reservasi" |
| | badge eskalasi | `.ort-badge--transfer` | `OrtCase::hitungSiklusTerlewat()` ≥ `SIKLUS_ESKALASI` |
| | chip Inactive | `.ort-chip--inactive` | `sales_active = 0` |
| 2 | **Dokter** | `.ort-cell.ort-clamp2` | `doctor_name` — **DINAMIS**, dipilih `OrtRollup::dokterTerkini()` |
| 3 | **Last Visit** | `.ort-lv` · `__tgl` · `__umur--{ok,warn,bad,faint}` · `__due` | `last_visit_date` + `OrtCase::nadaLastVisit()`; sub-baris `sch_date` + tooltip `perkiraanJatuhTempo()` |
| 4 | **Jadwal Dokter** | `.ort-dokhari` · `.ort-datepill--{ok,warn,bad,faint}` · `__sebab` · `__more` · `__belum` | `OrtJadwalDokter::untukBanyak()` — gerbang H-3 `OrtCase::HARI_PRAKTIK_MUNCUL` |
| 5 | **Follow Up** | `.ort-actions` · `.ort-btn` · `.ort-nodo` · `.ort-btn--wa` | TOMBOL; mati kalau `fu_date = hari ini`. Tooltip memuat `fu_by` `fu_channel` `fu_result` `fu_note` |
| 6 | **Reservasi** | `.ort-btn` · `.ort-struck` · `.ort-waitlist` · `.ort-cell--sub` | TOMBOL ke jembatan `?ort_control_id`, atau `rsv_date`/`rsv_status` kalau sudah ada |
| 7 | toggle | `.ort-toggle` | JS `buka()` |

⚠ **Tiga kolom lama tidak ada lagi, dan ini ke mana perginya** (3 Okt 2026,
prompt_jadwal_kontrol.md Phase 1):

| Dulu | Sekarang |
|---|---|
| **Jadwal** (jatuh tempo) | sub-baris `.ort-lv__due` di dalam Last Visit — **beserta tooltipnya**, yang berkasnya sendiri sebut penutup ambiguitas termahal di modul ini |
| **Status** (chip) | panel baris (`.ort-chips` di `_baris_terbuka`), dioper dari `_tabel` supaya rumusnya tetap satu. Kecuali **eskalasi**, yang tetap badge di baris tertutup |
| **Tindakan berikutnya** | melebur ke kolom 5 dan 6 — prompt memang menetapkan keduanya sebagai TOMBOL, jadi `$aksi` yang memilih satu tombol untuk keduanya **dibuang**: pasien yang sudah punya reservasi tetap boleh ditelepon |

### Baris dan kelompok

| Elemen | Kelas CSS | Fungsi |
|---|---|---|
| Baris | `.ort-tr` | — |
| Terlambat / parah | `.ort-tr--warn` · `--bad` | `hariTerlewat()` vs `TERLEWAT_PARAH_HARI` (14) |
| Baris dibuka | `.ort-tr--open` | JS |
| Kepala header | `.ort-thead` | gradient di **baris**, bukan per sel |
| Kepala kelompok | `.ort-group` + `__dot` `__label` `__n` `__hint` | `hitungUrgensi()` |
| Pita penutup | `.ort-done` + `__note` · `--amber` | `OrtQuery::usulanSelesai()` |

⚠ `.ort-done::before` menggambar siku ↳ dari **dua border**, bukan glyph, dan
`margin-top: -10px` menembus ke baris di atasnya.

---

## 7. Panel baris yang dibuka

| Elemen | Kelas CSS | Fungsi / kolom |
|---|---|---|
| Panel | `.ort-expand` + `__title` `__btns` | — |
| Delapan fakta | `.ort-facts` · `.ort-fact__k` `__v` `__dim` | lihat di bawah |
| Garis waktu mini | `.ort-mini` + `__row` `__n` `__d` `__x` `__go` | `OrtQuery::kontrolTerakhir($ids, 4)` |
| Segmented 3 arah | `.ort-miniseg` | JS `pakaiTampilan()` — **nol permintaan server** |

### Delapan fakta, satu per satu

| Fakta | Kelas | Sumber |
|---|---|---|
| No. rekam medis | `.ort-fact__v` | `omnicare_pid` |
| Nomor HP | `.ort-fact__v` | `patient_phone` |
| Bracket · rahang | | `bracket_type` · `bracket_arch` |
| Dokter ortho | | `doctor_name` |
| Pasang behel | `.ort-kira` kalau diperkirakan | `install_date` · `install_is_estimated` |
| Kehadiran | | `visit_count` / `control_count` |
| **Siklus terlewat** | `.ort-sub-red` + `.ort-fact__dim` | `OrtCase::hitungSiklusTerlewat()` — **tidak disimpan** |
| Irama kontrol | `.ort-fact__dim` | `median_gap_days` · `gap_spread_pct` · `avg_gap_days` |
| Kontrol terakhir datang | | `last_visit_date` |

### Satu baris DOM, tiga isi

`.ort-mini__x` dirender **sekali**, lalu JS mengganti mana yang `hidden`:

| Tampilan | Isi | Sumber |
|---|---|---|
| Semua | `#N · tgl · FU/RSV · cabang` | gabungan |
| Follow-up | `FU <tgl> · oleh <nama> · <kanal> · <hasil>` | `fu_*` + `FU_KANAL` + `FU_HASIL` |
| Tanggal reservasi | `<tgl> · <cabang>` / "Belum ada reservasi" | `rsv_date` · `reservation_id` · `unscheduled` |

⚠ `.ort-mini__x` — **satu baris, dipotong elipsis**, warna `--ort-ink2`,
**bobot 500**. Bobot itu yang menentukan keterbacaannya: tetangganya di baris
yang sama berbobot 700 (`__d`) dan 800 (`__n`), jadi 400 terbaca pudar
berapa pun gelap warnanya.

⚠ Urutan cabang di tampilan reservasi **menentukan**: `rsv_date` wajib
diperiksa **sebelum** `unscheduled`, kalau tidak baris yang sudah ditautkan
tetap berbunyi "tanpa jadwal" dan menyembunyikan tanggalnya.

---

## 8. Antrean penjadwalan

| Elemen | Kelas CSS | Fungsi |
|---|---|---|
| Grid 7 kolom | `.ort-grid--antrean` (min-width 1086px) | `OrtQuery::antrean()` |
| Kepala kelompok | `.ort-group` + `__dot` `__label` `__n` `__hint` | `kelompokkanAntrean()` |
| Panel baris | `.ort-expand` (tanpa segmented) | `_antrean_terbuka` |

Tiga kelompok saling lepas:

| Kelompok | Warna | Aturan |
|---|---|---|
| Hilang > 6 bulan | `#C0392B` | `isHilang()` — `HILANG_HARI = 180` |
| Jarang kontrol | `#B5701A` | `visit_count_180d < 2` |
| Baru selesai kontrol | `#2F8E55` | sisanya |

---

## 9. Lima lembar samping

Satu berkas `_sheet.blade.php`, lebar 410px, `z-index: 49`.

| Varian | Kelas khusus | Aksi | Rute |
|---|---|---|---|
| `reservasi` | `.ort-callfacts` · `.ort-hdok` + `__row` `__hari` `__jam` `__isi` | GET ke modul Reservasi | `rawreservation` |
| `pindahkan` | `.ort-checkcard` + `__box` · `.ort-infobox--brown` | POST | `ortho.pindahkan` |
| `geser` | `.ort-inp` | POST | `ortho.geser` |
| `jadwalkan` | `.ort-inp` | POST | `ortho.jadwalkan` |
| **`follow-up`** | `.ort-inp` · `.ort-infobox` | POST | `ortho.followup` |

Pemilih hari praktik dokter diisi `OrtJadwalDokter::untuk()`, yang memakai
ulang trait `ResolvesDoctorAvailability` milik modul Reservasi — **bukan rumus
kedua**.

⚠ Hari penuh **tetap ditampilkan**, `:disabled`. Sales perlu tahu harinya ada
tapi habis.

⚠ Lembar `follow-up` berakhiran `/follow-up`, jadi tercegat `POLA` di
`list.blade.php` — submit lewat fetch, tabel ditukar, lembar ditutup **sesudah**
tabel selesai (bukan sebelum, supaya kegagalan masih bisa jatuh ke `f.submit()`).

---

## 10. Drawer detail

| Elemen | Kelas CSS | Fungsi / kolom |
|---|---|---|
| Drawer 760px | `.ort-drawer` · `.ort-scrim` | `drawerDetail()` |
| Kepala gelap | `__head` `__kicker` `__name` `__namerow` `__phone` `__meta` `__transfer` | `patient_name` · `patient_phone` · `install_date` |
| Tiga KPI | `.ort-kpi` + `__card` `__lbl` `__val` `__sub` | `visit_count` · `control_count` · `median_gap_days` |
| Matriks 8 kolom | `.ort-matrix` + `__scroll` `__head` `__grid` `__row` `__n` `__aksi` `__empty` | `$c->controls` (**termasuk yang tertutup**) |
| Pemisah transfer | `.ort-matrix__sep` | `branchesVisited()` — dua garis dari `::before`/`::after` |
| Kolom Datang | `.ort-visit` | `visit_date` + ikon unduh = **diisi mesin** |
| Kartu kaki (4 keadaan) | `.ort-next` · `.ort-held` · `--selesai` | `$terbuka` · `is_proposal` · `status` |

⚠ `.ort-matrix__grid` final **proporsional** (`1fr`), dan `__scroll` berubah
`auto` → **`visible`**, supaya 8 kolom muat di badan drawer 716px tanpa gulir.

⚠ `.ort-drawer__body > *` wajib `flex-shrink: 0` — tanpa itu baris ke-5 matriks
**terpotong diam-diam**.

---

## 11. Kalender dan drawer harian

| Elemen | Kelas CSS | Fungsi |
|---|---|---|
| Baris nama hari | `.ort-cal-dow` | gradient di **baris** |
| Grid 42 sel | `.ort-cal-grid` · `-cell` | `OrtQuery::kalender()` — grid 6×7 |
| Luar bulan / terlewat / hari ini | `.is-out` · `.is-late` · `.is-today` | — |
| Chip nama pasien | `.ort-cal-chip` | ⚠ **warnanya dari `.ort-chip--*`** |
| "+N lainnya" | `.ort-cal-more` | maks 3 chip per sel |
| Legenda | `.ort-cal-legend` · `.ort-legend-dot` | 6 dari 11 chip |
| Drawer harian 540px | `.ort-drawer--hari` · `.ort-daycard` + `__top` `__name` `__mid` `__doc` `__sub` | `OrtQuery::hari()` |
| Pil jam | `.ort-timepill` · `--none` | `raw_cus_reservation` lewat `whereIn`, **tanpa join** |

⚠ Kalender **satu-satunya** tempat yang memang memakai tanggal kalender —
di sini tanggalnya memang yang dicari.

---

## 12. Roster (tab Pasien Ortho)

| Elemen | Kelas CSS | Fungsi |
|---|---|---|
| Kepala | `.ort-roster-head` + `__n` `__r` | `hitungRoster()` → `['aktif', 'selesai']` |
| Dua segmented | `.ort-seg` | `?rawat=` · `?tampil=` |
| Grid kartu | `.ort-cardgrid` (`auto-fill minmax(320px,1fr)`) | `OrtQuery::roster()` |
| Kartu pasien | `.ort-pcard` + `__head` `__name` `__meta` `__br` `__foot` · `--warn` `--bad` | `statusPasien()` closure |
| Avatar | `.ort-avatar` | inisial |
| Tiga statistik | `.ort-stat` + `__k` `__v` | `next_sch_date` · `last_visit_date` · persen hadir |
| Bilah kehadiran | `.ort-bar` · `--low > i` | `visit_count / control_count` |
| Pil cabang | `.ort-pill-br` · `--other` | `branchesVisited()` |
| Grid daftar 8 kolom | `.ort-rgrid` (min-width 1050px) | — |
| Pil tanggal | `.ort-datepill` | ⚠ **tanpa warna sendiri** |

---

## 13. Toast, paginasi, keadaan kosong

| Elemen | Kelas CSS | Fungsi |
|---|---|---|
| Toast | `.ort-toast` + `__undo` `__x` · `--galat` | `session('ort_toast')` · `session('ort_undo')` |
| Paginasi | `.ort-pagin` + `__btn` `__info` `__nav` | 50 per halaman, bawaan |
| **Pemilih baris** | `.st-perpage` **(bersama)** | `?per_page=` whitelist `[10,25,50,100]` |
| Keadaan kosong | `.st-empty` + `__icon` `__title` `__desc` **(bersama)** | empat bunyi |

⚠ Toast hidup **8 detik** di layar, Urungkan berlaku **90 detik** di server.
Ia **wajib** ditempel ke `.ort` — di `<body>` gradiennya resolve kosong.

⚠ URL pemilih baris dibangun closure `$tautan` yang membuang `page`, jadi
mengubah jumlah baris kembali ke halaman 1.

---

## 14. Sebelas aksi tulis

| Aksi | Rute | Kolom yang ditulis | Urungkan |
|---|---|---|---|
| Catat follow-up | `ortho.followup` | `fu_date` `fu_channel` `fu_by` `fu_result` `fu_note` (+ jadwal kalau dikirim) | ✅ |
| Batalkan follow-up | `ortho.batalfollowup` | kelima kolom FU → `null` | ✅ |
| Geser jatuh tempo | `ortho.geser` | `sch_date` `sch_note` `sch_source=manual` `is_proposal=0` | ✅ |
| Tandai tidak hadir | `ortho.tidakhadir` | `outcome` `closed_at` `closed_by` | ✅ |
| Jadwalkan | `ortho.jadwalkan` | baris `ort_control` **baru** | ✅ **menghapus** barisnya |
| Pindahkan | `ortho.pindahkan` | `place_id` kontrol + baris `ort_transfer` | ✅ dua bentuk |
| Ingatkan | `ortho.ingatkan` | baris `ort_reminder` | ✅ hapus |
| Tandai selesai | `ortho.selesai` | `status` `finished_at` + tutup kontrol | ✅ dua bentuk |
| Urungkan | `ortho.urungkan` | memulihkan potret | — |
| Drawer detail | `ortho.detail` | nol | — |
| Halaman | `ortho.index` | nol | — |

⚠ `ingatkan` satu-satunya aksi tulis yang **tidak** memanggil
`rollup->refresh()` — dan itu benar, tidak ada kolom turunan yang berubah.

---

## 15. Tiga kembaran yang wajib dijaga seiring

Kalau salah satu diubah tanpa yang lain, layar dan server berbeda pendapat:

| Kembaran | Akibat kalau menyimpang |
|---|---|
| `OrtControl::tindakanBerikutnya()` ⇄ `$aksi` di `_tabel.blade.php` | tombol di layar beda dari yang dianggap controller sah |
| `OrtCase::intervalKontrol()` ⇄ `LEAST/GREATEST` di `OrtQuery::dasar()` | saringan "Terlewat 2×" memilih baris yang berbeda dari yang ditandai layar |
| Urutan cabang reservasi di `_baris_terbuka` ⇄ `_tabel` ⇄ `_drawer_detail` | tiga layar berbeda pendapat tentang baris yang sama |

---

## 16. Komponen bersama — yang dipinjam, bukan ditulis ulang

| Kelas | Dari | Dipakai |
|---|---|---|
| `.st-card` · `__body` | `components.sweettreats.css` | 9× · 3× |
| `.st-empty` · `__icon` `__title` `__desc` | sama | keadaan kosong |
| `.st-view-tabs` · `.st-view-tab` | sama | tab tingkat atas |
| `.st-perpage` · `.st-pagination-bar` | sama | pemilih baris |
| `.st-pill--ortho` | **ditulis modul ini**, dipakai di **daftar Reservasi** | penanda reservasi dari ortho |

⚠ Baris terakhir itu satu-satunya tempat modul ini **menulis** ke CSS bersama.
Grep di folder `crud/ortho/` saja akan salah menyimpulkannya yatim.

---

## 17. Kalau mengubah sesuatu, periksa ini dulu

| Mengubah… | Periksa juga |
|---|---|
| Kolom tabel | `OrtQuery::daftar()` `select()` — kolom tak ter-select = 500 |
| Teks/kelas chip | `statusKey()` **dan** `$chipLabel` di `_tabel` |
| Tombol tindakan | `tindakanBerikutnya()` **dan** `$aksi` |
| Rumus jatuh tempo | `OrtUsulan` · `intervalKontrol()` · padanan SQL-nya |
| Warna/gaya | apakah ia hidup di dalam `.ort`; dan §21 design-system (11 kelas punya 2–4 deklarasi) |
| Tampilan reservasi | **ketiga** layar yang merendernya |
| Apa pun yang disimpan | **sudah ditampilkan di suatu tempat?** Hasil follow-up sempat tersimpan rapi selama satu commit tanpa pernah terlihat |
