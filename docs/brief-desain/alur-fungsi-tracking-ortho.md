# Alur Setiap Fungsi — Tracking Pasien Ortho

**2 Oktober 2026 · 11 rute · 11 aksi controller · 8 service · 6 perintah · 4 model · 1 observer · 23 view · 11 blok JavaScript**

> Teman berkas ini: `brief-tracking-ortho-berjalan.md` (apa yang dilihat Sales)
> dan `design-system-tracking-ortho.md` (bagaimana ia digambar).
> Langkah deploy: `../deploy/deploy-ortho.md`.

---

## 0. Gambar besar

```
          OMNICARE (sumber kebenaran, read-only)
                 │
     ┌───────────┴────────────┐
  ort:seed-cases          ort:pull-visits
  (siapa pasien ortho)    (kunjungan + penutupan)   ← cron 04:00
     └───────────┬────────────┘
                 ▼
     ort_case ─ ort_control ─ ort_reminder ─ ort_transfer
                 │
          OrtRollup  (satu-satunya penulis kolom turunan)
                 │
          OrtQuery  (satu-satunya pembaca layar)
                 │
          OrthoController → list.blade.php + 22 partial
                 │
               SALES
                 │
    (menelepon, memesan) ──► RawCusReservation
                                   │
                        OrtReservationObserver
                                   │
                     ort_control.rsv_date / rsv_status
```

**Arah data satu-satunya: Omnicare → `ort_*`. Tidak pernah balik.** Modul ini
tidak pernah menulis ke tabel Omnicare mana pun.

### Tiga aturan arsitektur yang menjelaskan hampir semua keputusan

1. **JOIN hanya antar tabel `ort_*`.** Collation prod campur
   (`utf8mb4_unicode_ci` vs `utf8mb4_uca1400_ai_ci`) membuat JOIN ke
   `raw_branch` gagal `#1267`. Relasi lintas keluarga diselesaikan dengan
   `whereIn` berisi literal PHP.
2. **Daftar kerja digerakkan dari `ort_control`**, antrean dari kolom cache
   `ort_case.open_control_id`.
3. **Satu kasus punya paling banyak SATU kontrol terbuka.** Dijaga service,
   bukan DB — MySQL tidak punya UNIQUE parsial. Pelanggarannya dilaporkan
   `ort:rollup --verify`.

---

## 1. Sebelas rute

`routes/web.php:228-277`, di dalam `Route::middleware([AuthGuard::class])`.
Gerbang RBAC = node `access_path` ber-link `tracking-ortho` — segmen sendiri,
sengaja **bukan** di bawah `finance`/`report` yang masuk `AuthGuard::SKIP_RBAC`.

| Metode | Path | Nama | Aksi | Pola parameter |
|---|---|---|---|---|
| GET | `/tracking-ortho` | `ortho.index` | `index` | — |
| POST | `kontrol/{id}/follow-up` | `ortho.followup` | `catatFollowUp` | `[A-Za-z0-9-]{36}` |
| POST | `kontrol/{id}/batal-follow-up` | `ortho.batalfollowup` | `batalFollowUp` | idem |
| POST | `kontrol/{id}/geser` | `ortho.geser` | `geserUsulan` | idem |
| POST | `kontrol/{id}/tidak-hadir` | `ortho.tidakhadir` | `tandaiTidakHadir` | idem |
| POST | `kasus/{caseId}/jadwalkan` | `ortho.jadwalkan` | `jadwalkanKontrol` | idem |
| POST | `kasus/{caseId}/pindahkan` | `ortho.pindahkan` | `pindahkan` | idem |
| POST | `kasus/{caseId}/ingatkan` | `ortho.ingatkan` | `ingatkan` | idem |
| POST | `kasus/{caseId}/selesai` | `ortho.selesai` | `tandaiSelesai` | idem |
| POST | `urungkan/{token}` | `ortho.urungkan` | `urungkan` | `[A-Za-z0-9]{32}` |
| GET | `/tracking-ortho/{caseId}` | `ortho.detail` | `drawerDetail` | `[A-Za-z0-9-]{36}` |

⚠ **Dua jebakan urutan yang sudah ditulis di berkasnya:**
- Rute literal (`kontrol/…`, `kasus/…`, `urungkan/…`) **wajib** terdaftar
  sebelum wildcard `{caseId}`.
- Pembatas pola dipasang **per-rute**, bukan `->where()` sesudah `->group()` —
  yang belakangan tidak berlaku.

⚠ **Rute `kontrol/{id}/konfirmasi` sudah TIDAK ADA.** Dokumen lama
(`revisi-3-tracking-ortho.md`, `log-sesi/2026-09-24`) masih menyebutnya;
keduanya sudah diberi blok peringatan di kepala berkas.

**Rute luar yang jadi bagian alur:** `rawreservation` (sasaran form Buat
Reservasi), `api.customer.search` (pencarian di drawer Tambah),
`rawcustomer.new`.

---

## 2. `index()` — satu metode, tujuh tampilan, empat fragmen

`OrthoController.php:62-95`. **Nol tulis.**

### Langkah

1. **Gerbang kesiapan** — `! Schema::hasTable('ort_case')` → render
   `belum-siap.blade.php` (bukan abort, karena ini keadaan "belum dipasang",
   bukan galat).
2. **`resolveBranch()`** → `placeId` null → `belum-siap` dengan
   `['alasan' => 'cabang']`.
3. **`frag` dicabut dari query string SEBELUM konteks dibangun** (`:85-86`) —
   kalau tidak, `withQueryString()` pada paginator menyeret `frag` ke setiap
   tautan halaman, dan klik "halaman 2" mengembalikan fragmen telanjang.
4. **`konteksList()`** menyusun seluruh data layar.
5. **`fragmen($frag, $ctx)`** atau `view('crud.ortho.list', $ctx)`.

### Tujuh tampilan (`?v=` / `?tab=`)

| Tampilan | Partial | Isi |
|---|---|---|
| *(bawaan)* | `_tabel` | Daftar kerja — jatuh tempo terdekat di atas |
| `kartu=backlog` | `_antrean` | Pasien tanpa jatuh tempo, 3 kelompok sebab |
| `tab=kalender` | `_kalender` | Sebaran jatuh tempo per bulan |
| `tab=kalender&hari=` | `_drawer_hari` | Satu tanggal |
| `tab=pasien` | `_roster` | Semua pasien ortho (kartu / daftar) |
| `cabang=` | `_cabang_lain` | Pasien kita yang dipegang cabang lain |
| `/{caseId}` | `_drawer_detail` | Riwayat lengkap satu pasien |

### Empat fragmen (`?frag=`) + satu di luar kamus

`fragmen()` `:110-141` — kamus `[view, syarat]`:

| `frag` | View | Syarat |
|---|---|---|
| `sheet` | `_sheet` | `$ctx['sheet']` && `$ctx['sheetKasus']` |
| `tambah` | `_drawer_tambah` | `(bool) $ctx['tambah']` |
| `hari` | `_drawer_hari` | `$ctx['hariDrawer'] !== null` |
| `tabel` | `_frag_tabel` | tab list && bukan lihat-saja && bukan backlog |

- Nilai tak dikenal → `return null`, pemanggil lanjut merender halaman penuh.
- Syarat tidak terpenuhi → **404**, supaya JS jatuh ke navigasi biasa alih-alih
  memasang panel kosong.
- `?frag=drawer` **tidak** ada di kamus ini — ditangani langsung di
  `drawerDetail():446`, dengan konteks dirakit sendiri (hemat ±¼ detik karena
  tidak lewat `konteksList()`).
- `penautFragmen()` `:151-162` menyuntik dua closure (`$tautan`,
  `$tautanDetail`) yang normalnya lahir di `list.blade.php`.

**Kenapa fragmen, bukan JSON:** server satu-satunya yang tahu cara menggambar
baris. Status, chip, dan syarat munculnya tombol diturunkan dari beberapa kolom
sekaligus; mengirim JSON berarti menulis ulang logika itu di JavaScript — dua
sumber kebenaran yang pasti berselisih.

---

## 3. `konteksList()` — tempat semua query string divalidasi

`:169-403`. Tidak ada `validate()` di `index()`; semuanya whitelist di sini.

| Query | Cara divalidasi |
|---|---|
| `tab` | whitelist `['list','kalender','pasien']`, default `list` |
| `kartu` | whitelist `['minggu-ini','terlewat','backlog']`; **dipaksa null kalau `tab !== 'list'`** |
| `urgensi` | DUA kamus — `URGENSI_ANTREAN` kalau kartu `backlog`, selain itu `URGENSI` |
| `cabang` | hanya sah kalau ada di `hitungPerCabangLain($placeId)` |
| `q` `dokter` `status` `dari` `sampai` | masuk `$filter` |
| `saya` | **bukan masukan pengguna** — `session('name')`, lalu dibuang lagi dari `'filter'` lewat `Arr::except` supaya tidak terhitung sebagai filter aktif |
| `sheet` | whitelist `['reservasi','pindahkan','geser','jadwalkan']` |
| `kontrol` / `kasus` | subjek lembar; **ketiganya dinolkan kalau `owner_place_id !== placeId`** |
| `bulan` | regex `/^\d{4}-\d{2}$/` |
| `hari` | regex `/^\d{4}-\d{2}-\d{2}$/` |
| `rawat` | `'selesai'` / `'aktif'` |
| `tampil` | `'kartu'` / `'daftar'` |
| `utama` | dibaca di `resolveBranch()`, bukan di sini |

### Mode cabang lain — dua variabel kunci

```php
$lihatPlace = $cabangDilihat ?? $placeId;   // yang DIBACA
$asalPasien = $cabangDilihat !== null ? $placeId : null;
```

Semua endpoint **tulis** tetap memakai `$placeId`. Hanya pembacaan yang boleh
menembus cabang.

### Lima cabang keluaran, saling eksklusif dan berurutan

1. `tab === 'kalender'` → `kalender`, `hariDrawer`, `hariData`
2. `tab === 'pasien'` → `baris` (roster), `hitungRoster`, `tampil`, `rawat`
3. `cabangDilihat !== null` → `baris` (diCabangLain), `pengingat`, `tertinggal`
4. `kartuAktif === 'backlog'` → `baris` (antrean), `kelompok`, `hitungAntrean`
5. Daftar kerja harian → `baris`, `riwayat`, `masukDari`, `usulanSelesai`

⚠ Cabang 5 memakai **`array_merge`, bukan `+`** — `konteksList()` sudah
menyetel `'urgensi'` lebih awal, dan operator `+` tidak menimpa kunci yang ada.
Jebakan yang sama ada di `drawerDetail()` untuk kunci `'detail' => null`.

⚠ Semua hitungan dipanggil **sekali per request**, bukan per baris. Memanggil
`usulanSelesai()` di dalam loop Blade pernah membuat 50 kueri per halaman.

---

## 4. `resolveBranch()` — pagar cabang yang paling sering disalahpahami

`:1131-1190`. Mengembalikan `[?string $placeId, bool $terkunci, string $nama]`.

1. **`session('raw_branch_id')` ada** → terjemahkan lewat
   `placeIdDariBranchId()`; kalau ketemu, **kembali LEBIH AWAL** dengan
   `terkunci = true`. `?utama=` **tidak pernah dilirik** untuk mereka — inilah
   pagar yang menahan Sales/FO menembus cabang lain lewat URL.
2. **Yang sampai bawah = orang tanpa cabang.** `request('utama')` divalidasi
   lewat `branches->ada()`.
3. **Cadangan:** `array_key_first($branches->all())`, di-cast `(string)`.

⚠ Cast `(string)` itu wajib: PHP mengubah kunci array numerik jadi int, dan
tanpa cast semua `===` terhadap `place_id` varchar jadi false — **Super Admin
kena 403 di drawer detail**.

⚠ **Tidak ada pemeriksaan role di sini, dan itu disengaja** (alasan panjang di
`:1149-1171`). Versi lama menuntut `canSeeAllBranches()` dan memaku
Admin/Head IT/IT/Supervisor ke cabang pertama tanpa pesan galat apa pun.

---

## 5. Sebelas aksi — satu per satu

Pola seragam: **validasi → pagar → potret keadaan sebelum → tulis → rollup →
token urungkan → toast**. Aksi gagal selalu `back()->with('error', …)`, aksi
berhasil selalu `toast()`.

### 5.1 `catatFollowUp()` — `:482-565`

Mencatat bahwa Sales sudah menghubungi pasien, **dan boleh sekaligus memasang
jadwal**.

| | |
|---|---|
| Validasi | `kanal` → `nullable\|in:wa,telepon,langsung,lainnya` · `tanggal` → `nullable\|date\|before_or_equal:today` · `sch_tanggal` dan `sch_tanggal_lain` → `nullable\|date\|after_or_equal:today` |
| Pagar | tabel ada → 404 · `findOrFail` · `pastikanMilikCabang` → 403 · `closed_at !== null` → *"Kontrol ini sudah ditutup — follow-up tidak bisa dicatat."* |
| Kolom ditulis | `fu_date` (default hari ini) · `fu_channel` (default `wa`) · `fu_by` = `session('name') ?: 'Sales'` · `updated_by` |
| Kalau jadwal ikut dikirim | + `sch_date` · `is_proposal = 0` · `sch_source = SCH_MANUAL` |
| Urungkan | ✅ — dan kalau jadwal ikut dipasang, **satu Urungkan memulihkan keduanya** |
| Realtime | ✅ dicegat JS, fragmen `tabel` menggantikan isi tabel |

⚠ Kedua tanggal opsional **dengan sengaja**: menelepon tanpa mendapat tanggal
adalah hasil yang sah.

⚠ `$schBaru = $data['sch_tanggal'] ?? $data['sch_tanggal_lain'] ?? null` memakai
`??`, bukan `?:` — `validate()` tidak memasukkan kunci yang tidak dikirim.

### 5.2 `batalFollowUp()` — `:581-608`

Jalan pulang permanen dari salah klik, karena jendela Urungkan hanya 90 detik
dan hidup di toast.

| | |
|---|---|
| Pagar | `closed_at` → *"…sudah ditutup"* · `! fu_date` → *"Belum ada follow-up yang bisa dibatalkan."* |
| Kolom ditulis | `fu_date = null` · `fu_channel = null` · `fu_by = null` · `updated_by` |
| Urungkan | ✅ — jadi "membatalkan pembatalan" juga bisa |

**Bukan penghapusan riwayat medis** — `visit_date` tidak tersentuh.

⚠ Ini satu-satunya aksi berbasis kontrol **tanpa**
`abort_unless(Schema::hasTable('ort_control'), 404)`; keempat sejawatnya punya.
Tidak berbahaya (findOrFail akan meledak lebih dulu), tapi tidak seragam.

### 5.3 `geserUsulan()` — `:617-656`

| | |
|---|---|
| Validasi | `tanggal` → `required\|date\|after_or_equal:<hari ini + 3>` · `catatan` → `nullable\|string\|max:255` |
| Pesan kustom | *"Jadwal paling cepat <tgl> — beri jeda minimal 3 hari untuk mengabari pasien."* |
| Kolom ditulis | `sch_date` · `sch_note` · `sch_source = SCH_MANUAL` · `is_proposal = 0` · `updated_by` |

⚠ **Menggeser sekaligus mengonfirmasi** — `is_proposal` jadi 0, dan
`sch_source = manual` membuat baris itu **kebal `ort:rapikan-usulan`
selamanya**.

⚠ Ini satu-satunya tempat `MIN_MAJU_HARI = 3` masih berlaku — hanya untuk
tanggal yang **diketik Sales sekarang**, bukan untuk tanggal yang **dihitung
sistem**. Lantai pada tanggal hitungan itulah yang melahirkan 651 usulan di
satu tanggal, dan ia sudah dicabut.

### 5.4 `tandaiTidakHadir()` — `:670-703`

| | |
|---|---|
| Pagar (tiga, berurutan) | `closed_at` → *"sudah ditutup"* · `visit_date !== null` → *"Pasien tercatat datang <tgl> — tidak bisa ditandai tidak hadir."* · `sch_date > hari ini` → *"Jadwalnya masih <tgl> — belum bisa ditandai tidak hadir."* |
| Kolom ditulis | `outcome = OUT_TIDAK_HADIR` · `closed_at = now()` · `closed_by` · `updated_by` |

⚠ **Tidak membuat kontrol berikutnya.** Kasus kehilangan kontrol terbuka dan
jatuh ke antrean penjadwalan — itu disengaja, supaya Sales yang memutuskan
kapan menjadwalkan ulang.

⚠ Sengaja **manual**, menyimpang dari handoff §1. Mesin hanya mengisi fakta
yang ia tahu dari rekam medis; "pasien tidak datang" bukan fakta yang ada di
rekam medis.

### 5.5 `jadwalkanKontrol()` — `:716-767`

Pintu masuk lembar **Jadwalkan** dari antrean.

| | |
|---|---|
| Validasi | `tanggal` → `required\|date\|after_or_equal:<hari ini + 3>` · `catatan` → `nullable\|max:255` |
| Pagar | `owner_place_id !== placeId` → 403 · status bukan aktif → *"Perawatan pasien ini sudah <status>."* · **aturan satu-kontrol-terbuka** dibaca dari tabel (bukan kolom cache) → *"Pasien ini sudah punya kontrol terbuka (<tgl>) — tutup dulu yang itu."* |
| Baris BARU | `id` uuid · `seq = max(seq)+1` · `place_id` · `is_proposal = 0` · `sch_date` · `sch_source = SCH_MANUAL` · `sch_note` · `created_by` · `updated_by` |
| Urungkan | ✅ **bentuknya beda** — `sebelum = null` berarti Urungkan **MENGHAPUS** baris itu, bukan memulihkan kolom |

⚠ `:725` membandingkan status dengan literal `'aktif'`, sementara `pindahkan()`
dan `tandaiSelesai()` memakai `OrtCase::STATUS_AKTIF`. Nilainya sama, jadi
tidak ada bug — hanya tidak seragam.

### 5.6 `pindahkan()` — `:794-863`

| | |
|---|---|
| Validasi | `tujuan` → `required` · `alasan` → `required\|in:pasien_minta,dokter_pindah,lebih_dekat,pindah_domisili,lainnya` · `catatan` → `nullable\|max:255` · `sementara` → `nullable` |
| Pesan kustom | *"Alasan wajib dipilih — tanpa itu perpindahan tidak bisa ditelusuri."* |
| Pagar | 403 kalau bukan pemilik · status bukan aktif · tujuan tidak dikenal · tujuan sama dengan sekarang · **tidak ada kontrol terbuka** → *"jadwalkan dulu sebelum dipindahkan"* |
| Ditulis (satu transaksi) | **kontrol terbuka:** `place_id = tujuan`, `updated_by` · **baris `ort_transfer` baru:** `from_place_id`, `to_place_id`, `transfer_date`, `reason`, `reason_note`, `is_temporary`, `return_after_control_id`, `detected = manual`, `created_by` |
| Urungkan | ✅ dua bentuk digabung: pulihkan `place_id` kontrol **dan** hapus baris transfer |

⚠ **Yang berpindah adalah KONTROL, bukan kasus.** `ort_case.owner_place_id`
dihitung ulang oleh `OrtRollup` dari `place_id` kontrol terbuka. Jangan tulis
`owner_place_id` langsung.

⚠ `rollup->refresh()` dipanggil **di luar** transaksi.

**Lingkaran transfer sementara ditutup bukan di sini**, melainkan
`OrtRollup::pulangkanTransferSementara()` — karena kontrol bisa tertutup dari
banyak arah (tarikan malam, Tandai Selesai, tidak hadir).

### 5.7 `ingatkan()` — `:873-918`

Satu-satunya aksi tulis yang boleh menyentuh kasus **milik cabang lain**.

| | |
|---|---|
| Validasi | `kanal` → `nullable\|in:wa,telepon,lainnya` · `catatan` → `nullable\|max:255` |
| Pagar | `abort_unless($milikKita && $dipegangLain, 403)` — yaitu `install_place_id === placeId` **dan** `owner_place_id !== placeId` |
| Jeda | pengingat terakhir < `OrtReminder::JEDA_HARI` (7) → *"Sudah diingatkan <tgl> — tunggu 7 hari sebelum mengingatkan lagi."* |
| Baris baru `ort_reminder` | `from_place_id`, `to_place_id`, `reminded_at`, `reminded_by`, `channel`, `note` |
| Urungkan | ✅ hapus baris |

⚠ **Satu-satunya aksi tulis yang tidak memanggil `rollup->refresh()`** — dan
itu benar: tidak ada kolom turunan yang berubah.

⚠ Kamus `kanal` di sini **tanpa `langsung`**, beda dari `catatFollowUp`. Masuk
akal (tidak mungkin mengingatkan cabang lain secara langsung), tapi perlu
diingat kalau kamusnya nanti disatukan.

### 5.8 `tandaiSelesai()` — `:931-983`

| | |
|---|---|
| Pagar | 403 kalau bukan pemilik · status bukan aktif |
| Ditulis (satu transaksi) | **`ort_case`:** `status = STATUS_SELESAI`, `finished_at`, `updated_by` · **kontrol terbuka (kalau ada):** `outcome = OUT_BATAL`, `closed_at`, `closed_by`, `updated_by` |
| Urungkan | ✅ dua bentuk: `kasusKolom` + `kontrol` |

⚠ Kontrol terbuka **harus** ikut ditutup; jadwal yang menggantung akan
selamanya dilaporkan `ort:rollup --verify`.

### 5.9 `urungkan()` — `:1038-1090`

| | |
|---|---|
| Penyimpanan | `Cache::put('ort:undo:<token>', …, 90 detik)` — bukan tabel, sengaja fana |
| Pagar | token hilang **atau** `$simpan['user'] !== session('id')` → *"Sudah lewat waktunya untuk diurungkan."* |
| Gerbang cabang | dibaca dari **TOKEN**, bukan dari baris |

⚠ Gerbang cabang wajib dari token: sesudah transfer, kontrolnya sudah milik
cabang tujuan, dan `pastikanMilikCabang()` akan menolak Urungkan-nya sendiri.

**Tiga bentuk pemulihan, boleh digabung:**

| Bentuk | Artinya |
|---|---|
| `kontrol.sebelum` = array | `fill()` kolom lama |
| `kontrol.sebelum` = `null` | **hapus** baris (yang baru dibuat) |
| `kasusKolom` | `fill()` kolom `ort_case` lama |
| `hapus` | `delete()` baris `OrtTransfer` / `OrtReminder` |

Baris yang sudah hilang dilewati diam-diam.

⚠ Memakai `back()`, **bukan `pulang()`**, dan **tidak mengeluarkan token baru**
— jadi urungkan tidak bisa diurungkan lewat toast. `batalFollowUp` ada untuk
mengisi celah itu pada aksi yang paling sering salah klik.

**Prinsipnya:** yang disimpan adalah **keadaan sebelumnya**, bukan perintah
balik. Perintah balik harus tahu cara membatalkan setiap aksi; keadaan hanya
perlu ditulis ulang.

### 5.10 `drawerDetail()` — `:412-470`

| | |
|---|---|
| Pagar | tabel ada → 404 · `findOrFail` · `abort_unless($milikKita \|\| $pernahKita, 403)` di mana `$pernahKita` = `install_place_id === placeId` |
| Tulis | nol |

⚠ Cast `(string)` di **kedua sisi** setiap perbandingan — `place_id` varchar vs
int PHP.

⚠ Memakai `array_merge`, bukan `+`, karena `konteksList()` sudah menyetel
`'detail' => null`.

### 5.11 Lima penolong privat

| Metode | Guna |
|---|---|
| `selesai()` `:773` | Ekor seragam: `rollup->refresh()` → `toast(…, simpanUrungkan(…))`. Dipakai 5 aksi berbasis kontrol; `pindahkan`/`ingatkan`/`tandaiSelesai` memanggil sendiri karena bentuk undo-nya majemuk |
| `toast()` `:986` | `redirect()->to(pulang())->with(['ort_toast', 'ort_undo'])` |
| `pulang()` `:1006` | Tujuan redirect sesudah sukses |
| `simpanUrungkan()` `:1106` | `Str::random(32)` + cache 90 detik + `user` + `cabang` |
| `pastikanMilikCabang()` `:1099` | 403 kalau `ctl.place_id !== placeId` |

**`pulang()` punya empat lapis**, semuanya `route('ortho.index')` kalau gagal:
tanpa referer · host referer ≠ host request (referer tidak dipercaya sebagai
tujuan redirect) · path tidak diawali `/tracking-ortho` · selain itu **path
dipertahankan** (supaya drawer detail tetap terbuka) tapi query dibersihkan dari
`sheet`, `kontrol`, `kasus`, `tambah`, `frag` — supaya lembar yang sama tidak
terbuka lagi menutupi toast.

⚠ `pastikanMilikCabang()` sengaja memakai `place_id` **kontrol**, bukan
`owner_place_id` kasus — kontrol itulah satuan yang berpindah saat transfer.
Aksi berbasis kasus memeriksa `owner_place_id` inline.

---

## 6. Delapan service

### 6.1 `OrtQuery` — 981 baris, 24 metode publik, nol tulis

`PER_HAL = 50` (dulu 10 → 96 halaman untuk 959 kasus Bintaro).

**Hitungan:** `kartu` (tiga kartu dalam **dua** kueri) · `hitungUrgensi` (basis
`ort_control.sch_date`) · `hitungUrgensiKasus` (basis `ort_case.next_sch_date`,
"semua" = jumlah **pasien**) · `hitungAntrean` · `hitungPerCabang` ·
`hitungRoster` · `hitungPerCabangLain` · `kolomBelumDipakai`

**Daftar:** `daftar` · `antrean` · `roster` · `diCabangLain` · `kalender`
(grid 6×7 = 42 hari) · `hari`

**Pengaya baris:** `usulanSelesai` · `kontrolTerakhir` (maks 4) ·
`pengingatTerakhir` · `masukDari` · `tertinggal` · `dokterDiCabang`

| Catatan | |
|---|---|
| `hariIni()` | Dipisah jadi metode supaya bisa dibekukan saat uji |
| `antrean()` | Urut `last_visit_date` naik — **belum pernah datang = paling genting** |
| `kelompokkanAntrean()` | Tiga kelompok saling lepas: `hilang` merah · `jarang` amber · `baru` hijau |
| `tertinggal()` | Satu-satunya penentu munculnya tombol "Ingatkan" |
| `masukDari()` | Badge "Baru masuk dari <Cabang>", dibuang begitu kasus itu punya `fu_date` |
| `kalender()` | Hitungan sub-tab dihitung dari **baris grid yang sama** dengan yang dirender |
| `hari()` | Jam lewat `whereIn` ke `raw_cus_reservation`, **tanpa join**, dilindungi `Schema::hasTable` |
| Saringan harian | Optgroup "Kerja hari ini": *Belum pernah di-FU*, *Saya FU hari ini* |
| Saringan ke-10 *(4 Okt 2026)* | *Terlewat 2× atau lebih* — dihitung **siklus**, bukan hari, langsung di SQL supaya menumpang `idx_ort_case_backlog`: `DATEDIFF(hari_ini, k.last_visit_date) >= 2 × LEAST(60, GREATEST(21, COALESCE(k.median_gap_days, 30)))`. Jepitannya wajib sama dengan `OrtCase::intervalKontrol()` |

⚠ `'Saya FU hari ini'` memakai `$filter['saya']` yang berasal dari
`session('name')`, bukan dari masukan pengguna. Kalau kosong, kueri jadi
`whereRaw('1 = 0')` — lebih baik tidak menampilkan apa pun daripada menampilkan
follow-up orang lain.

⚠ `kartu()` memakai `is_proposal = 0` **hanya** pada dua hitungan minggu-ini;
dibuang dari hitungan terlewat. Baris usulan yang sudah lewat tetap terlewat.

### 6.2 `OrtRollup` — satu-satunya penulis kolom turunan

Empat belas kolom miliknya: `owner_place_id`, `open_control_id`,
`next_sch_date`, `next_is_proposal`, `last_visit_date`, `last_visit_place_id`,
`control_count`, `visit_count`, `visit_count_180d`, `avg_gap_days`,
`median_gap_days`, `gap_spread_pct`, `branches_visited`, `rollup_at`.

| Metode | Guna |
|---|---|
| `refresh($caseId)` | `forceFill` 14 kolom. `owner` = kontrol terbuka → kontrol terakhir → `install_place_id`, ditimpa `$pulangKe` kalau ada transfer sementara jatuh tempo |
| `refreshSemua(?$placeId)` | Kasus **aktif** saja, `chunkById(200)` |
| `verifikasi(?$placeId)` | Daftar penyimpangan, **nol tulis** |
| `pulangkanTransferSementara()` | Mengembalikan `place_id` ke cabang asal + `returned_at` |

⚠ `verifikasi()` mendeteksi penyimpangan dengan cara **memperbaiki lalu
membandingkan**, jadi seluruh pemeriksaan dibungkus
`DB::beginTransaction() … finally { DB::rollBack(); }`.

⚠ **Tidak dijalankan = irama kosong untuk SEMUA pasien, tanpa satu pun pesan
galat.** Salah satu dari dua kegagalan sunyi di runbook deploy.

### 6.3 `OrtUsulan` — 143 baris, menyusut drastis

`untuk(OrtCase, $kunjungan, $placeId)` →
`['tanggal' => ?string, 'sebab' => ?string, 'basis' => ?string, 'basis_n' => int]`

- `tanggal = null` + `sebab = 'hilang'` kalau kunjungan > 180 hari lalu —
  **satu-satunya pagar yang tersisa**, dan ia soal beban kerja, bukan jadwal.
- Selain itu `OrtControl::tanggalUsulan($kunjungan, clamp(median, 21, 60))`.
- **Tanggal boleh di masa lalu.**

⚠ Irama dihitung **segar** dari `visit_date`, bukan dari kolom
`median_gap_days` — saat tarikan malam berjalan, kunjungan terbaru belum masuk
kolom.

⚠ `$placeId` sudah tidak menentukan apa pun; dipertahankan di tanda tangan
secara sadar. Tiga pagar jadwal (kapasitas, hari dokter, klinik tutup) dan
`hariSahBerikutnya()` sudah dibuang — alasan terukurnya di
`../handoff/balasan-ortho-hub-2026-10-02.md` §2.

### 6.4 `OrtJadwalDokter` — 212 baris *(baru Oktober)*

`use ResolvesDoctorAvailability` — **memakai ulang mesin slot modul Reservasi**,
jadi jam istirahat, cuti, dan pengecualian per tanggal ikut terpotong.
`JUMLAH_HARI = 6`, `JELAJAH_HARI = 70`.

`untuk(OrtCase, $placeId, ?$mulai)` →
`['hari' => [{tanggal,label,jam,terisi,total,penuh}], 'dokter' => ?string, 'sebab' => ?string]`

Empat sebab kalau kosong, masing-masing dengan kalimat sendiri lewat
`penjelasan()`:

| Sebab | Arti | Siapa yang harus dihubungi |
|---|---|---|
| `tanpa_dokter` | Kasus tanpa `doctor_ref_id` | — |
| `tak_terpetakan` | Ada kodenya, tidak ketemu di `raw_doctor` | data master |
| `tak_praktik` | Terpetakan tapi tanpa jadwal praktik | operasional |
| `tak_ada_hari` | Terjadwal, tapi tidak ada hari di jangkauan 70 | — |

⚠ `tak_terpetakan` dan `tak_praktik` sempat jadi satu sebab. Itu mengirim Sales
ke orang yang salah.

⚠ Angka penuh **dijumlahkan dari** `sessions[*].available_count/total_count`,
bukan rumus kapasitas baru. Repo sudah punya tiga rumus kapasitas yang saling
berbeda; jangan tambah yang keempat.

⚠ `berjadwalDi()` memakai `->first()` — aman karena `uq_raw_doctor_omnicare`.
Sengaja **tidak** memakai cadangan pencocokan NAMA: hari praktik yang salah akan
disebut ke pasien lewat telepon. Kembar nama memang ada, tapi dengan kode NULL.

### 6.5 `OrtVisits` — cermin → kunjungan

`kueri($places, $sejak, $sampai)` (query builder, urutan sengaja tidak
ditentukan) · `kumpulkan($baris)` (kunci dedup `pid|tanggal|place`, keluaran
di-`usort` jadi stabil) · `pid()` static · `bandingTrx()` static.

⚠ `bandingTrx()` tidak meng-cast ke int — `trx_id` bisa melebihi 64 bit.

⚠ Baris ber-`p_id` kosong **tidak dibuang**; `pid = ''` dan pemanggil yang
memutuskan. Prioritas item: `pasang > lepas > kontrol > penyerta`.

### 6.6 `OrtItemRules` — 471 baris, satu-satunya rumah pola nama item

`klasifikasi($nama, $extra, $kode)` → `pasang` / `lepas` / `kontrol` /
`penyerta` / `null`.

⚠ **Urutan sepuluh tahapnya tidak boleh diubah:** veto kode → pengecualian
akhir-perawatan → paket lepas (Freedom) → akhir perawatan → awal perawatan →
kontrol eksplisit → produk braces bernama → penyerta → prefix kode → veto retail
+ jaring terakhir.

Static: `jenisBracket()` (Damon Quantum → Self Ligating → Sapphire → Ceramic →
Metal; urutan khusus-dulu penting) · `rahang()` (null lebih jujur daripada
menebak) · `fixture()` untuk `--uji-aturan`.

### 6.7 `OrtKodeItem` — pembaca prefix kode

`prefix()` (regex sama persis dengan `AccuratePushSample::resolve()`) ·
`peran()` · `bukanOrtho()`.

⚠ Catatan tegas di berkasnya: **prefix `PO` BUKAN pemasangan** — ia `campuran`.

### 6.8 `OrtBranches` — peta cabang, tidak pernah JOIN

Cache 30 menit. `all()` · `nama()` · `branchId()` · `placeIdDariBranchId()` ·
`ada()` · `wa()` · `linkWaPasien()` · `linkWaIngatkan()` · `pilihan()` ·
`selain()` · `lupakanCache()`.

⚠ `WA_CABANG` (lima nomor di-hardcode) ditandai **tempat sementara** di
berkasnya — rumah yang benar adalah kolom di `raw_branch`.

---

## 7. Enam perintah artisan

| Perintah | Guna | Pratinjau | Dijadwalkan |
|---|---|---|---|
| `ort:seed-cases` | Menemukan siapa pasien ortho + riwayatnya | butuh `--commit` | — |
| `ort:pull-visits` | Menarik kunjungan & menutup kontrol | `--dry-run` | ✅ **04:00** |
| `ort:rollup` | Menghitung kolom turunan | `--verify` | — |
| `ort:rapikan-usulan` | Menghitung ulang jatuh tempo tersimpan | butuh `--commit` | — |
| `ort:tautkan-reservasi` | Menjodohkan reservasi yang sudah ada | butuh `--commit` | — |
| `ort:seed-demo` | Data peraga — **lokal saja** | — | — |

### 7.1 `ort:pull-visits` — satu-satunya yang dijadwalkan

`Kernel.php:84-88`: `dailyAt('04:00')`, `withoutOverlapping(60)`,
`runInBackground()`, `appendOutputTo(storage/logs/ortho-cron.log)`.

⚠ **Harus sesudah `omnicare:sync` 02:00.** Kalau dibalik, ia membaca cermin
hari sebelumnya.

Opsi: `--sejak` `--sampai` `--cabang` `--dry-run` `--daftar-item`
`--uji-aturan` `--tanpa-usulan`. Toleransi pencocokan jadwal **45 hari**.

**Dua jalur tulis:**

| Jalur | Syarat | Yang ditulis |
|---|---|---|
| **cocok** | selisih ≤ 45 hari dari jadwal terbuka terdekat | `visit_date`, `visit_source`, `visit_confidence = 'pasti'`, `omnicare_trx_id`, `outcome = hadir`, `closed_at`, `closed_by = 'sistem'`, `place_id` (transfer terdeteksi) |
| **dugaan** | tidak ada jadwal yang cocok | baris baru dengan `visit_confidence = 'dugaan'`, `unscheduled = 1`, `sch_date = tanggal kunjungan`. **Jadwal terbuka tidak disentuh** |

Lalu usulan berikutnya lewat `OrtUsulan`, kecuali `--tanpa-usulan`.

**Dua lapis anti-dobel:** `omnicare_trx_id` sudah ada → lewati · ada
`visit_date` di tanggal itu → lewati. Lapis kedua ada karena tiga keadaan nyata
membocorkan lapis pertama.

⚠ `--daftar-item` punya penjaga **sendiri yang lebih sempit** dan jalan
**sebelum** penjaga tabel `ort_*` — hanya menuntut `omnicare_sales_detail` ada.
Alasannya: membedakan `Lepas Bracket` dari `Bracket Metal Lepas` adalah risiko
nomor satu modul ini, dan harus bisa diperiksa **sebelum** DDL dipasang.

⚠ `--daftar-item` memberi peringatan kalau baris "Lepas Bracket" mencapai
ribuan — brief konsultan menyebut ±218 sepanjang masa. Ribuan artinya aturannya
salah baca; **jangan menyemai**.

⚠ `rollup->refresh()` dipanggil untuk kasus tersentuh **plus semua kasus aktif
cabang itu** — `visit_count_180d` jadi basi karena waktu berjalan, bukan karena
ada kunjungan.

### 7.2 `ort:seed-cases`

Opsi: `--sejak=2023-01-01` `--sampai` `--cabang` `--commit` `--pid` `--batas`
`--laporan` `--hapus-seed`.

⚠ **Tidak ada `--dry-run`; menulis butuh `--commit`.** Alasannya ditulis di
berkasnya: *"pengaman yang harus diingat untuk dinyalakan adalah pengaman yang
suatu hari lupa dinyalakan."*

⚠ Pemindaian **selalu lima cabang** walau `--cabang` dipakai — supaya riwayat
lintas cabang tidak terpotong. `--cabang` menyaring di tahap **tulis**.

⚠ **Nol jadwal terbuka dibuat.** Semua pasien hasil penyemaian mendarat di
antrean "Belum punya jadwal". Itu disengaja: riwayat lama tidak punya jadwal
yang pernah disepakati siapa pun.

⚠ `--hapus-seed` **mempertahankan** kasus yang sudah disentuh manusia
(`created_by <> PENULIS` OR `closed_at IS NULL` OR `reservation_id IS NOT NULL`
OR `fu_date IS NOT NULL`).

⚠ Peringatan kalau perkiraan tanggal pasang > 50% episode — artinya
`OrtItemRules` belum mengenal banyak nama pemasangan; jalankan `--daftar-item`
dulu.

### 7.3 `ort:rapikan-usulan`

Hanya menyentuh baris `is_proposal = 1` AND `closed_at IS NULL` AND
`visit_date IS NULL`. Tiga keputusan:

| Keadaan | Tindakan |
|---|---|
| Tanggal sama, dasar beda | perbarui `sch_basis`, `sch_basis_n` saja |
| `tanggal === null` (pasien hilang) | **hapus** baris → pindah ke "Belum punya jadwal" |
| Tanggal berbeda | perbarui `sch_date`, `sch_basis`, `sch_basis_n` |

⚠ Menyegarkan `sch_basis`/`sch_basis_n` **walaupun tanggalnya tidak berubah** —
kalau tidak, tingkat keyakinan membeku di nilai lama sementara tanggalnya sudah
benar.

⚠ Begitu Sales menyentuh tanggalnya (`sch_source = manual`, `is_proposal = 0`),
baris itu **kebal selamanya**.

### 7.4 `ort:tautkan-reservasi`

Dicocokkan ke `raw_cus_reservation.date` (tanggal **janji**), **bukan**
`created_at`. Toleransi `--toleransi=3` hari.

**Sejak 4 Okt 2026 menyapu TIGA jenis baris**, bukan satu. Versi pertama hanya
menyentuh baris ber-`visit_date` — dan itu melewati persis baris yang dilihat
Sales di tab List: kontrol terbuka yang reservasinya dibuat dari modul
Reservasi biasa (Observer tidak pernah menemukannya), serta baris
`tidak_hadir` tempat reservasi yang meleset/batal justru berada.

| Jenis | Saringan | Jangkar | Aturan khusus |
|---|---|---|---|
| **Riwayat** | `visit_date` terisi | `visit_date` ± toleransi | tidak berubah; `--sejak`/`--sampai` hanya berlaku di sini |
| **Tidak hadir** | `outcome = tidak_hadir`, `visit_date` kosong | `sch_date` ± toleransi | status apa pun ikut, **termasuk `cancelled`** — itulah ceritanya |
| **Terbuka** | `closed_at` kosong | hari ini … +`HORIZON_TERBUKA` (120 hari) | `cancelled` **dibuang**; waitlist (`date` NULL) ikut, diurutkan terakhir; **layanan behel diutamakan, tidak diwajibkan** |

Kenapa terbuka tanpa jendela toleransi: satu kasus hanya punya satu kontrol
terbuka, jadi "pasien ini punya reservasi mendatang" sudah tidak ambigu.
Memaksakan ±3 hari dari `sch_date` justru melewatkan hampir semuanya — Sales
memesan kapan pasien setuju, bukan tepat di jatuh tempo.

Kenapa layanan behel tidak diwajibkan: jembatan prefill dari Tracking Ortho
**tidak memilih layanan** (hanya mengisi keterangan "Kontrol ortho ke-N").
Mewajibkannya akan melewatkan reservasi yang lahir dari modul ini sendiri.
Yang dicegah cuma satu: pasien ortho memesan scaling bulan depan dan booking
itu tersambar jadi "reservasi kontrol" — bila ada dua calon, yang berlayanan
behel menang. Penanda: `raw_doc_service.name` cocok `/behel|ortho|orto|bracket|braces/i`,
diperiksa lewat dua `whereIn` berliteral PHP, bukan JOIN.

Urutan sapuan disengaja: riwayat → tidak hadir → terbuka, supaya reservasi
lama diklaim kunjungan yang memang memakainya sebelum kontrol terbuka
sempat melihatnya. `$terpakai` tetap menjaga "satu reservasi satu kontrol"
lintas ketiganya.

Angka **"Cakupan: N%"** di laporan **hanya mengukur riwayat** — itulah yang
dibandingkan ke 91% prod. Dua jenis lain dilaporkan terpisah supaya tidak
mengencerkannya.

`calonTerbaik()` deterministik — riwayat & tidak hadir: (1) selisih hari
terkecil, (2) cabang sama, (3) `id`. Terbuka: (0) berlayanan behel dulu,
(0b) bertanggal sebelum waitlist, lalu (1)–(3) yang sama.

⚠ Cabang jadi penimbang **kedua**, bukan syarat — pasien lintas cabang nyata
(104 orang di prod).

⚠ Pasien tanpa `raw_customer` **dibiarkan kosong, tidak ditebak**.

⚠ Satu reservasi hanya boleh menempel pada satu kontrol — ditandai termasuk di
tengah jalannya perintah.

⚠ Peringatan kalau cakupan < 60%; prod mengukur 91%. Cakupan rendah → curigai
`customer_omnicare_link`, **jangan** simpulkan walk-in.

### 7.5 `ort:rollup --verify`

Tiga jenis penyimpangan: `kontrol terbuka ganda` (pelanggaran aturan domain,
tidak sembuh dengan rollup biasa) · `kasus <status> masih punya jadwal terbuka`
(berlaku semua status) · `cache <kolom> melenceng` (7 kolom).

⚠ **Exit code FAILURE** kalau ada masalah, supaya bisa dipakai pemeriksa
kesehatan.

### 7.6 `ort:seed-demo` — lokal saja

Ditolak keras di `production`. 18 pola sebaran. `--bersih` / `--paksa`
menghapus keempat tabel `ort_*` berurutan.

---

## 8. Empat model

### `OrtCase`

| Tetapan | Nilai |
|---|---|
| `STATUS_AKTIF` / `SELESAI` / `PUTUS` / `PINDAH_KLINIK` | `aktif` · `selesai` · `putus` · `pindah_klinik` |
| `HILANG_HARI` | 180 |
| `JARANG_MIN_VISIT` | 2 |
| `TARGET_GAP` | 30 (wajar 28–45) |
| `IRAMA_MIN_SELANG` | 2 (= tiga kunjungan) |
| `IRAMA_MIN_HARI` / `IRAMA_MAX_HARI` | 21 / 60 |
| `IRAMA_RAPAT_PCT` | 50 — hanya 24% pasien prod memenuhinya |
| `IRAMA_RAPAT_MIN_SELANG` | 3 |
| `SIKLUS_ESKALASI` | 2 — mulai berapa siklus terlewat pasien dianggap perlu pendekatan berbeda |

**Dua statis untuk siklus terlewat** *(4 Okt 2026)*:

| Metode | Guna |
|---|---|
| `intervalKontrol(?int $median)` | median dijepit `[IRAMA_MIN_HARI, IRAMA_MAX_HARI]`, cadangan `USULAN_MAJU_HARI` — **persis** jepitan `OrtUsulan`, dan padanan SQL-nya `LEAST(60, GREATEST(21, COALESCE(k.median_gap_days, 30)))` di `OrtQuery::dasar()`. Ubah satu, ubah dua-duanya |
| `hitungSiklusTerlewat(?string $lastVisit, ?int $median, $hariIni)` | `floor((hari ini − kunjungan terakhir) ÷ interval)`; 0 kalau belum pernah datang atau belum satu interval lewat. Statis supaya bisa dipanggil Blade atas `stdClass` query builder |

⚠ **Sengaja bukan kolom rollup.** Nilainya berubah tiap hari walau datanya
diam — penyakit `visit_count_180d` yang memaksa tarikan malam menyegarkan
semua kasus aktif tiap cabang hanya karena waktu berjalan. Dua masukannya
sudah tersimpan; ini aritmetika murni, nol kueri, tidak pernah basi.
| `BASIS_RAPAT` / `BASIS_LONGGAR` / `BASIS_DEFAULT` | `irama_rapat` · `irama_longgar` · `default` |

**`irama(iterable $tanggal)` static** →
`['median','selang','min','max','sebar','basis']`

- **Median, bukan rata-rata** — satu jeda enam bulan menggeser rata-rata, tidak
  menggeser median.
- Dua kunjungan di hari sama **bukan** selang.
- `sebar` = `(max − min) / median × 100`.
- `basis` rapat butuh ≥3 selang **dan** `sebar ≤ 50`.

**Satu salinan rumus**, dipakai `OrtRollup` (simpan ke kolom) dan `OrtUsulan`
(hitung segar).

**Scope:** `aktif` · `dipegang` · `belumPunyaJadwal` · `diCabangLain`

⚠ `belumPunyaJadwal()` memakai `whereNull('open_control_id')` — kolom cache,
**sengaja bukan** `whereDoesntHave` yang jadi 542 subquery.

**Turunan:** `isTertaut()` · `hariSejakKunjunganTerakhir()` · `isHilang()`
(null **atau** > 180) · `isJarangKontrol()` (`visit_count_180d < 2`) ·
`branchesVisited()` · `siklusTerlewat()` (mendelegasi ke statis
`hitungSiklusTerlewat()` — satu rumus, bukan dua)

**Lima relasi:**

| Relasi | Jenis | Catatan |
|---|---|---|
| `controls()` | `hasMany` | + `orderBy('seq')` — urutan kontrol selalu dari nomor urut, bukan tanggal |
| `openControl()` | `belongsTo` | via **`open_control_id`** — kolom cache, bukan subquery. Inilah yang dipakai roster dan `scopeBelumPunyaJadwal()` |
| `transfers()` | `hasMany` | + `orderBy('transfer_date')` |
| `reminders()` | `hasMany` | + `orderByDesc('reminded_at')` — terbaru di atas |
| `customer()` | `belongsTo` | `RawCustomer` — **satu-satunya relasi ke luar keluarga `ort_*`** |

⚠ Relasi dipakai lewat `with()`. **Jangan `->join()`** ke luar keluarga `ort_*`
— termasuk `customer()`; ia aman sebagai `with()` karena Eloquent memecahnya
jadi `whereIn`, tapi di-`join()` ia kena `#1267` di prod.

### `OrtControl`

| Tetapan | Nilai |
|---|---|
| `SCH_MANUAL` / `SCH_SISTEM` / `SCH_REKAM_MEDIS` | `manual` · `sistem` · `rekam_medis` |
| `OUT_HADIR` / `OUT_TIDAK_HADIR` / `OUT_DIJADWAL_ULANG` / `OUT_BATAL` | `hadir` · `tidak_hadir` · `dijadwal_ulang` · `batal` |
| `USULAN_MAJU_HARI` | 30 (cadangan) |
| `MIN_MAJU_HARI` | 3 — **hanya** untuk tanggal yang diketik Sales |
| `TERLEWAT_PARAH_HARI` | 14 |

⚠ `OUT_DIJADWAL_ULANG` terdefinisi tapi **tidak pernah ditulis maupun dibaca**
di seluruh modul.

**`tanggalUsulan($kunjungan, ?$majuHari)` static** —
`startOfDay()->addDays($majuHari ?? 30)`. **Boleh jatuh di masa lalu; itu
jawabannya, bukan cacat.** Lantai lama menyembunyikan 26 dari 39 pasien
terlambat dan melahirkan 651 usulan di satu tanggal. Parameter `$hariIni`
**dibuang**, bukan diabaikan diam-diam.

**`perkiraanJatuhTempo($tempo, ?$hariIni)` static** — jantung keputusan "kolom
Jadwal berhenti menyebut tanggal". Murni fungsi tanggal, nol kueri:

```
selisih < 0   → "Sudah waktunya"
selisih = 0   → "Hari ini"
selisih ≤ 6   → "Pekan ini"
selisih ≤ 27  → "± N pekan lagi"
lainnya       → "± Nov"  /  "± Jan '27" kalau menyeberang tahun
```

**`statusKey()`** — sembilan keadaan, urutan: `hadir` → `tidak_hadir` →
`reservasi_batal` → `terlewat_parah` (>14) → `terlewat` → `ada_reservasi` →
`sudah_fu` → `menunggu`. Chip `'usulan'` **sudah dibuang dari tangga ini**.

**`tindakanBerikutnya()`** — `null` kalau tertutup · `'follow_up'` kalau
`! fu_date` · `'buat_reservasi'` kalau `! reservation_id` atau
`rsv_status === 'cancelled'` · selain itu `null`.

⚠ **KEMBARAN** `$aksi` di `_tabel.blade.php:129-133`. Keduanya **wajib sama
persis**. Kalau salah satu diubah, yang lain ikut.

**Scope:** `terbuka` · `diCabang` · `daftarKerja` (ikut index
`idx_ort_control_worklist`) · `terlewat`

**Dua relasi:** `case()` → `belongsTo OrtCase` · `reservation()` →
`belongsTo RawCusReservation` via `reservation_id`.

**Dua turunan lain:**

| Metode | Guna |
|---|---|
| `isTerbuka()` | `closed_at === null`. Dipakai `tindakanBerikutnya()` sebagai gerbang pertama |
| `hariTerlewat($hariIni = null)` | Selisih hari dari `sch_date`, **tidak pernah negatif** |

⚠ `hariTerlewat()` mengembalikan **0 untuk tiga keadaan berbeda**: sudah
ditutup · `sch_date` masih null · belum terlewat. Itu disengaja — layar hanya
perlu tahu "terlambat berapa hari", dan nilai negatif akan memaksa setiap
pemanggil mengecek tandanya. Kalau butuh membedakan ketiganya, pakai
`isTerbuka()` dan `sch_date` langsung.

⚠ `$hariIni` di sini di-`Carbon::parse()`, **bukan** bertipe `CarbonInterface`
seperti di `tanggalUsulan()`/`perkiraanJatuhTempo()` — jadi ia menerima string.
Jangan ikut pola ini untuk metode baru.

### `OrtTransfer` · `OrtReminder`

`OrtTransfer`: `UPDATED_AT = null` · `REASONS` (5 entri) ·
`DETECTED_MANUAL`/`DETECTED_REKAM_MEDIS` · `scopeMenungguKembali()` ·
`labelAlasan()`

`OrtReminder`: `JEDA_HARI = 7` · `bolehDiingatkanLagi()`

Keduanya hanya ditulis, tidak pernah diubah — supaya riwayatnya utuh.

---

## 9. Observer — satu-satunya pintu masuk otomatis

`OrtReservationObserver`, dipasang di `AppServiceProvider.php:48` pada
`RawCusReservation`.

| Event | Yang ditulis |
|---|---|
| `saved` (created **dan** updated) | `rsv_date` (null untuk waitlist), `rsv_status`, `rsv_synced_at`, `updated_by = 'observer'` → lalu `rollup->refresh()` |
| `deleted` | `reservation_id = null`, `rsv_date = null`, `rsv_status = null`, `rsv_synced_at`, `updated_by` → refresh |

⚠ `created` ikut diawasi karena `ReservationController::store()` menulis tautan
ortho **sesudah** reservasi dibuat, dan urutan bisa terbalik di jalur lain.

⚠ **Dilewati kalau tidak ada yang berubah** — supaya `updated_at` tidak bergerak
dan rollup tidak dipicu sia-sia.

⚠ Pada `deleted`, **baris kontrolnya sengaja dipertahankan**. Jadwal kontrol
masih berlaku; baris itu kembali menampilkan tombol "Buat Reservasi".

### Tiga aturan keras

1. **Tidak pernah menggagalkan operasi reservasi.** Seluruh badan lewat
   `jaga()` yang `try/catch \Throwable` dan hanya
   `Log::warning('OrtReservationObserver gagal — reservasi tetap tersimpan')`.
2. **Tidak pernah menyentuh** `sch_date`, `visit_date`, `closed_at`. Observer
   hanya menyalin ulang potret.
3. Berhenti diam-diam kalau `ort_control` belum ada.

`kontrolTertaut()` dibatasi ke yang **masih terbuka** — kontrol tertutup adalah
riwayat dan tidak boleh berubah karena reservasinya diedit belakangan.

### ⚠ Lubang yang diketahui dan sengaja dibiarkan

`CustomerController:650` memakai `$customer->reservations()->update([...])` —
mass-update lewat relasi, **tidak memicu event Eloquent**. Aman untuk sekarang
karena ia hanya menyentuh `raw_customer_id`. **Kalau baris itu suatu hari ikut
mengubah `date` atau `status`, observer ini akan terlewat.**

### Jalur tulis `reservation_id` yang bukan observer

- **`ReservationController::store():1051-1090`** — tiga pagar: `closed_at`
  null, `! reservation_id` (menolak submit ulang menimpa tautan benar), dan
  cabang reservasi **wajib sama** dengan cabang pemegang kontrol. Seluruhnya di
  dalam `try/catch` + `Log::warning('Tautan ortho gagal, reservasi tetap
  dibuat')`.
- **`ReservationController::create():655-740`** — bridge prefill
  `?ort_control_id=`, hanya baca.
- **`ort:tautkan-reservasi`** — penjodohan retroaktif.

### ⚠ Status reservasi BUKAN penanda kontrol selesai

"Kontrol beres" datang hanya dari cermin rekam medis Omnicare (133/133 kontrol
tertutup diverifikasi dari sana), dengan jeda ±18 jam — memang begitu
rancangannya. Reservasi `confirmed` tidak berarti pasien datang.

---

## 10. Dua puluh tiga view

| Berkas | Guna |
|---|---|
| `list.blade.php` (603) | Induk + dua IIFE JavaScript |
| `belum-siap.blade.php` (63) | Halaman penuh sendiri: tabel belum dibuat (daftar 2 SQL + `access:rebuild`) atau cabang belum terpeta |
| `_navrow` (44) | Pita satu baris: slot kiri + `.st-view-tabs` **komponen bersama**, bukan tiruan |
| `_nav_urgensi` (48) | 5 keranjang; `title` "Semua" **berbeda per tab** karena populasinya memang berbeda |
| `_nav_antrean` (44) | 4 keranjang dengan kamus warna sendiri |
| `_btabs` (196) | Dua peran: pil statis (terkunci) atau pemilih cabang utama. Strip melipat mendatar, keadaan di `localStorage` |
| `_cards` (71) | Tiga kartu-tombol; tiap href memaksa `tab=null`, `cabang=null`, `urgensi=null`, `page=null` |
| `_filter` (95) | 7 opsi status + optgroup "Kerja hari ini". **Tidak ada field cabang** |
| `_tabel` (446) | 7 kolom + toggle; form FU inline; `$aksi` kembaran `tindakanBerikutnya()` |
| `_baris_terbuka` (263) | 8 fakta + 4 kontrol terakhir dengan segmented **3 arah** |
| `_antrean` (204) | 7 kolom; kepala kelompok disembunyikan kalau sub-tab bukan "semua" |
| `_antrean_terbuka` (143) | Digerakkan `ort_case`, bukan `ort_control`. Sengaja tanpa Batalkan FU / Tandai tidak hadir |
| `_cabang_lain` (184) | Lihat-saja; baris tertinggal dapat form Ingatkan yang sekaligus membuka WA |
| `_roster` (240) | Dua tampilan (kartu/daftar) + dua segmented (Aktif⇄Selesai, Daftar⇄Kartu) |
| `_kalender` (110) | Grid 42 sel, maks 3 chip + "+N lainnya". **Satu-satunya tempat yang masih memakai tanggal kalender** |
| `_drawer_hari` (66) | Kepala berisi "N jadwal · N sudah reservasi · N belum" |
| `_drawer_detail` (424) | Kepala gelap + 3 KPI + matriks 8 kolom dengan pemisah transfer + kartu kaki **empat keadaan** |
| `_sheet` (317) | **Satu berkas empat isi**: `reservasi` · `pindahkan` · `geser` · `jadwalkan` |
| `_drawer_tambah` (188) | **PROTOTIPE** — `onsubmit="return false"`, tombol `ort-btn--mati`. Pintunya sudah dicabut dari `list.blade.php` |
| `_frag_tabel` (33) | Blok galat + `_toast` + `_tabel`. Urutan toast-sebelum-tabel disengaja |
| `_toast` (51) | `$idToast` unik per toast |
| `_kosong` (38) | Empat bunyi: tersaring habis · mode selesai · antrean bersih · bawaan |
| `_paginasi` (40) | Tulis sendiri, bukan `links()` |

⚠ `_sheet` menaruh tombol submit **di luar `<form>`**, disambung
`form="ortSheetForm"` — supaya kaki lembar bisa `position: sticky`.

⚠ `_sheet` merender galat **di dalam** lembar, karena `layouts.notif` ada di
halaman belakang yang sedang tertutup scrim.

⚠ `_drawer_detail` tidak butuh konteks daftar — itu yang membuat `?frag=drawer`
murah.

⚠ `_drawer_tambah` **tidak pernah mengarang nomor RM.** Peringatan "belum
tertaut" adalah keadaan default yang jujur, karena endpoint pencarian belum bisa
memeriksa RM.

---

## 11. JavaScript — 11 blok, tanpa framework

Tanpa Alpine, tanpa Vue, tanpa build step — konvensi hosting bersama. Semua
inline di Blade.

### 11.1 Mesin lapisan — `list.blade.php:252-499`

Empat lapisan, satu mesin: `drawer` · `sheet` · `tambah` · `hari`.

⚠ **Memakai fase CAPTURE**, bukan bubbling. Tombol aksi memakai
`onclick="event.stopPropagation()"` yang menghentikan klik sebelum sampai ke
`document`; di capture, handler ini dapat duluan. *"Buat Reservasi" pernah
memuat ulang halaman karena ini.*

| Fungsi | Guna |
|---|---|
| `jenis(href)` | Membaca jenis lapisan dari URL lewat 4 regex — satu tempat, dipakai klik **dan** popstate |
| `rangka(t, href)` | Memasang rangka kosong **seketika**, sebelum fetch selesai |
| `bar(nyala)` | Garis pemuat, disuntik ke `.ort` — **bukan `<body>`** |
| `buka(url, t, dorong)` | `pushState` → `fetch(url + 'frag=' + t)` dengan `X-Requested-With` + `credentials: same-origin` |
| `tutup(host, urlPulang)` | Kelas `is-closing`, bersihkan di `animationend` **atau** `setTimeout(320)` sebagai jaring |
| `tutupTeratas()` | Lembar dulu, baru drawer — dua host karena lembar boleh berdiri **di atas** drawer |
| `kunciGulir(nyala)` | Menahan `wheel` + `touchmove` |

⚠ Jawaban di-parse `DOMParser` lalu **mengisi panel yang sudah terpasang**
(`panel.innerHTML = baru.innerHTML`), tidak mengganti `<aside>` — mengganti
elemennya memicu animasi masuk **dua kali**.

⚠ `kunciGulir()` **tidak** memasang `overflow:hidden` di `<html>`/`<body>`:
sidebar aplikasi `position:sticky` akan mati dan terjun −352px, plus membuang
scrollbar menggeser halaman 15px.

⚠ `.catch()` → `location.href = url`. Apa pun yang gagal (jaringan, 403/404,
HTML aneh), **Sales tidak boleh berakhir di layar diam**.

⚠ Guard penurunan bertahap: kalau host tidak ada, atau `fetch`/`pushState`
tidak tersedia → `return`, semuanya jadi tautan biasa.

**Esc** menutup lapisan teratas. **Tombol Kembali/Maju** membaca
`e.state.ortUrl`; kalau halaman memang lahir dengan lapisan di badannya →
`location.reload()`.

### 11.2 Aksi tulis di tempat — `list.blade.php:501-602`

Tiga aksi lewat `POLA = /\/(batal-)?follow-up$|\/urungkan\/[A-Za-z0-9]{32}$/`.

⚠ Pendengarnya di **kerangka halaman**, bukan di `_tabel` — tabelnya sendiri
yang akan ditukar.

⚠ **`redirect: 'manual'` WAJIB.** Kalau redirect diikuti, permintaan itu yang
memakan flash session dan toast tidak pernah sampai ke permintaan fragmen.
`res.type === 'opaqueredirect'` adalah jalur sukses.

`segarkan()`:
1. URL sekarang + `frag=tabel`, hapus `sheet`/`kontrol`/`kasus`
2. `document.querySelector('[data-ort-tabel]').replaceWith(baru)`
3. Toast lama di-`remove()`, toast baru di-`appendChild` ke **`.ort`**
4. **Setiap `<script>` dalam fragmen disalin ulang** (`createElement` →
   `textContent` → `appendChild` → `.remove()`)

⚠ Langkah 4 wajib: `<script>` yang masuk lewat `innerHTML` **tidak dijalankan
peramban**. Tanpa ini, baris baru kehilangan panel ringkas + segmen, dan toast
tidak pernah pergi.

⚠ Penjaga klik ganda: tombol di-`disabled` **dan** diberi kelas `is-sibuk` —
tombol yang cuma mati terbaca seperti tombol rusak.

`.catch()` → `f.submit()`, jatuh ke jalur lama.

**Prinsipnya:** yang dirender ulang **server**, bukan ditambal JS.

### 11.3 Panel ringkas + segmented — `_tabel.blade.php:354-446`

`buka(id, dari)` accordion satu-terbuka · `pakaiTampilan(id, v)` segmented 3
arah **nol permintaan server**, hanya mengganti elemen mana yang `hidden`.

Pilihan disimpan `sessionStorage['ortMiniView']` dan dipulihkan saat halaman
dimuat. Klik baris mini ber-`dataset.aktif` → `window.open(href, '_blank',
'noopener')`.

⚠ Listener didaftarkan **sekali** saat halaman dimuat, bukan di dalam `buka()`.
Kalau dipanggil tiap baris dibuka, listener menumpuk — sudah pernah kejadian.

### 11.4–11.11 Blok lain

| Blok | Catatan |
|---|---|
| `_antrean.blade.php:168` | Salinan sederhana 11.3 tanpa segmented — `_tabel` tidak dirender di mode antrean |
| `_btabs.blade.php:131` | `localStorage` dibungkus `try/catch` karena di jendela privat ia **melempar galat**, bukan cuma kosong. `requestAnimationFrame` dua lapis melepas `is-preload` |
| `_filter.blade.php:87` | Satu listener toggle; keadaan awal dari server |
| `_toast.blade.php:34` | Elemen dipegang **sekarang** lewat `[data-ort-toast="<id>"]`, bukan dicari saat timer berbunyi — tanpa ini timer toast lama membunuh toast baru di detik ke-5 |
| `_sheet.blade.php:194` | Chip hari praktik hanya **mengisi** input tanggal. Tanpa JS lembarnya tetap bekerja penuh |
| `_cabang_lain:157` · `_drawer_detail:398` | `onsubmit="window.open(this.dataset.wa, …)"` — dipanggil **dari gerakan klik**; kalau ditunda sampai form kembali, peramban memblokirnya sebagai popup |
| `_drawer_tambah.blade.php:130` | Debounce 250 ms, min 2 karakter, 8 hasil |

### 11.12 `event.stopPropagation()` inline

Dipakai pada setiap tautan/form di dalam baris yang bisa diklik, supaya klik
tidak ikut membuka panel ringkas: `_tabel` (4×), `_baris_terbuka` (4×),
`_antrean`, `_antrean_terbuka` (2×), `_cabang_lain`.

**Inilah yang memaksa kedua mesin di `list.blade.php` memakai fase capture.**

---

## 12. Jebakan yang sudah menggigit

| Jebakan | Akibat |
|---|---|
| Token CSS di-scope `.ort` | Toast/loadbar di luar `.ort` kehilangan seluruh warna |
| `innerHTML` tidak menjalankan `<script>` | Baris baru kehilangan panel ringkas; toast tidak pernah pergi |
| `redirect: 'follow'` pada fetch POST | Toast hilang — flash dimakan permintaan yang salah |
| Bubbling, bukan capture | `stopPropagation()` tombol mematikan mesin lapisan |
| Mengganti `<aside>` alih-alih isinya | Animasi masuk berjalan dua kali |
| `overflow:hidden` di body | Sidebar sticky terjun −352px |
| Listener didaftarkan di dalam `buka()` | Menumpuk tiap baris dibuka |
| Timer toast mencari elemen saat berbunyi | Toast lama membunuh toast baru |
| `localStorage` di jendela privat | **Melempar galat**, bukan mengembalikan null |
| `frag` ikut `withQueryString()` | Klik "halaman 2" mengembalikan fragmen telanjang |
| `+` alih-alih `array_merge` | Kunci yang sudah ada tidak tertimpa |
| Kunci array numerik PHP jadi int | Super Admin kena 403 di drawer detail |
| `@php(expr)` dengan kurung bersarang | Parser putus di kurung pertama |
| Komentar `{{-- --}}` di dalam `match` | 500 — pakai `/* */` PHP |
| Directive Blade nempel huruf | Tidak dikompilasi, tampil mentah |
| Nama ikon gaya FA6 | Kotak kosong — FA5 saja |
| JOIN lintas keluarga tabel | `#1267` di prod, aman di lokal |
| `whereDoesntHave` untuk "belum punya jadwal" | 542 subquery |
| Lupa `access:rebuild` | **404 di SEMUA halaman** |
| Lupa `ort:rollup` | Irama kosong, tanpa pesan galat |
| Lupa rsync `public/assets/` | Semua halaman tanpa CSS |
| `doctor_ref_id` cuma `varchar(20)` | Nilai uji panjang meluap |

---

## 13. Dua kembaran yang wajib dijaga seiring

| Kembaran | Kalau menyimpang |
|---|---|
| `OrtControl::tindakanBerikutnya()` ⇄ `$aksi` di `_tabel.blade.php:129-133` | Tombol di layar berbeda dari yang dianggap controller sah |
| `OrtCase::irama()` dipakai `OrtRollup` (simpan kolom) **dan** `OrtUsulan` (hitung segar) | Jatuh tempo berbeda dari yang ditampilkan |

Rumus irama hanya **satu salinan** — itu disengaja. Yang berbeda cuma *kapan*
ia dihitung.

---

## 14. Delapan ketidakseragaman yang tercatat (bukan bug)

1. `batalFollowUp()` satu-satunya aksi kontrol tanpa `Schema::hasTable()` guard.
2. `jadwalkanKontrol():725` memakai literal `'aktif'`, bukan `STATUS_AKTIF`.
3. `ingatkan()` satu-satunya aksi tulis tanpa `rollup->refresh()` — **benar**.
4. Kamus `kanal`: `catatFollowUp` punya `langsung`, `ingatkan` tidak.
5. `urungkan()` memakai `back()` dan tidak mengeluarkan token baru.
6. `OrtControl::OUT_DIJADWAL_ULANG` terdefinisi, tidak pernah dipakai.
7. `OrtBranches::linkWaPasien()`, `pilihan()`, `selain()`, `lupakanCache()`
   tidak dipanggil dari view ortho — partial membangun `wa.me` langsung dari
   `Phone::toWa()`, dan dropdown cabang tujuan dari `$peta`.
8. `_drawer_tambah` dan tiga varian non-`reservasi` di `_sheet` masih menyebut
   "Tahap 3" di komentar, padahal `pindahkan`/`geser`/`jadwalkan` **sudah
   tersambung**. Yang benar-benar masih inert hanya drawer Tambah.

---

## 15. Yang masih terbuka

| Hal | Status |
|---|---|
| Tiga pagar jadwal tetap dibuang? | Rekomendasi: ya, ukur sebaran di prod sesudah tarikan pertama — mesinnya masih utuh, jadi reversibel |
| Impor follow-up lama dari Sheets | Menunggu Ikhsan |
| Pemetaan dokter bolong | 12 dari 42 kasus terpetakan di dev; angka prod belum diukur |
| `WA_CABANG` di-hardcode | Rumah yang benar kolom di `raw_branch` |
| `.st-empty__text` tidak ada di CSS | 5 teks keadaan-kosong tampil tanpa gaya; perbaikannya menyentuh berkas di luar modul, menunggu izin |
