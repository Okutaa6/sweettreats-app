# Audit HUB — `feat/checklist-review-redesain-dan-multi-foto` (`1e0f993`)

> Dua commit Reza, 17 Sep: `542c70e` multi-foto · `1e0f993` redesain review.
> Disusun HUB 17 Sep 2026, diverifikasi terhadap PROD & DEV.

---

## Putusan: **layak naik**, dan bisa malam ini

13 berkas · 2.117 baris tambah · **1 SQL** · **tidak menyentuh `public/assets/`**
→ **tidak perlu rsync**. Basis `23fb5b1`, nol konflik saat digabung dengan
gerbong malam ini (sudah dibuktikan di branch uji `test/pettycash-checklist-dev`).

---

## Yang membuat HUB percaya

### SQL-nya yang terbaik di repo ini sejauh ini

**Kolom lama sengaja DIPERTAHANKAN sebagai cermin foto pertama**, dan alasannya
ditulis — dua-duanya benar:

1. `missingPhotos()` memindai SELURUH item satu minggu. Membacanya dari kolom
   cermin = **nol kueri tambahan**; kalau dipindah ke tabel anak, halaman review
   atasan kena N+1.
2. **Rollback cukup `git revert`.** Kolom lama tetap valid, halaman lama langsung
   berfungsi lagi. **Tidak ada SQL balikan yang perlu ditulis.**

Dan ia menyebut jebakan yang HUB sendiri kena hari ini:

> *"Tipe `varchar(255)` + collation `utf8mb4_unicode_ci` MENGIKUTI tabel induk,
> BUKAN `char(36)`. Kalau berbeda, JOIN-nya kena error #1267 'illegal mix of
> collations' di prod."*

Itu persis galat yang menghentikan kueri HUB pagi ini di `raw_branch`.

Idempoten lewat `UNIQUE(item_id, slot, sort_order)` + `INSERT IGNORE`, dan
berkasnya membawa **tiga kueri verifikasi** sendiri.

### Pagar 5 foto ditegakkan di SERVER, bukan cuma di layar

```php
DB::table('sys_checklist_weekly_submission_item')
    ->where('id', $item->id)->lockForUpdate()->first();   // <- ini yang penting
$agg = …COUNT(*) AS n, COALESCE(MAX(sort_order), -1) AS mx…
if ((int) $agg->n >= $max) throw new \RuntimeException(…);
```

`lockForUpdate()` mencegah dua unggahan serentak sama-sama membaca 4 lalu
menyisipkan keduanya jadi 6. Itu detail yang paling mudah terlewat.

Dan kalau transaksinya gagal, **berkas yang telanjur dipindah ikut dihapus** —
tidak meninggalkan sampah di disk.

### Jalur hapus menutup IDOR

```php
$photo = ChecklistWeeklySubmissionPhoto::with('item.submission')->findOrFail($photoId);
…
// Dicek lewat submission-nya, BUKAN lewat id foto yang dikirim client.
if ($sub->user_id !== session('id')) abort(403);
```

Plus: cermin di-sinkron ulang sesudah hapus (foto berikutnya naik), dan file
di-`unlink` **sesudah commit** — transaksi gagal tidak menghilangkan berkas.

### Satu bug nyata ikut diperbaiki

Daftar review atasan dulu memakai `done_tasks`, sementara halaman detail memakai
"beres" (dicentang **atau** dikecualikan). Pengisian yang sama terbaca **44/50 di
daftar dan 50/50 di detail** — supervisor bisa salah kira. Sekarang keduanya
memakai definisi yang sama, dihitung di DB (`withCount`) supaya tidak N+1.

`items.photos` juga di-eager-load — tanpa itu grid thumbnail per task jadi N+1
(6 hari × belasan task).

### Nol pelemahan otorisasi

Satu-satunya perubahan di jalur izin justru **menambah** pagar: `abort(404)`
sebelum `abort(403)`.

---

## Diverifikasi HUB

| | |
|---|---|
| Backfill di DEV | 3 single · 0 before · 0 after — **cocok persis** dgn kolom lama, **0 gagal** ✅ |
| Kueri verifikasi #3 (foto lama yang tak ikut) | **0 baris** ✅ |
| `php -l` seluruh berkas PHP | bersih ✅ |
| Menyentuh `public/assets/`? | **tidak** → tanpa rsync ✅ |
| Konflik dgn gerbong malam ini | **nol** (dibuktikan di branch gabungan) ✅ |

### Yang akan terjadi di PROD

```
item mingguan total   1.549
  punya foto single     461
  punya foto before     283
  punya foto after      285
  -> baris dibuat     1.029
submission mingguan (30 hari)  78
```

Jadi backfill-nya menyentuh **1.029 foto sungguhan** — jauh di atas 3 di dev.
Kueri verifikasi #2 dan #3 **wajib dijalankan setelah SQL di prod**, dan angkanya
harus 461/283/285 dengan 0 gagal.

---

## ⚠️ Yang HUB catat, bukan penghalang

**Nol berkas uji.** Sama seperti seluruh modul checklist dan petty cash — repo
ini memang belum punya uji untuk keduanya. Yang menggantikannya di sini: SQL
idempoten, kueri verifikasi bawaan, dan rollback yang cuma butuh revert kode.

Kalau kelak ditambah, tiga yang paling berharga:
1. unggah ke-6 **ditolak** (dan kendali: ke-5 diterima)
2. hapus foto pertama → **foto kedua naik jadi cermin** kolom lama
3. hapus foto orang lain → **403**, bukan 404 yang membocorkan keberadaannya

**Dan yang menurut HUB paling layak diuji manusia sebelum tidur:** unggah 5 foto
lalu hapus yang **tengah**, bukan yang terakhir. `sort_order` sengaja tidak
dinomori ulang (lubang penomoran memang dibiarkan), jadi itu jalur yang paling
mungkin menyimpan kejutan — meski kodenya terlihat benar.

---

## Catatan deploy

- **1 SQL manual**: `database/sql/2026_09_17_checklist_mingguan_multi_foto.sql`.
  Tanpa `ALTER`, jadi keterbatasan MySQL 5.7 tidak relevan.
- **Tanpa rsync.**
- Rollback: `git revert` saja. Tabel anak boleh ditinggal — kolom cermin tetap
  berisi foto pertama, halaman lama langsung jalan lagi.

---

## Addendum 17 Sep malam — E2E di DEV & hasil PROD

E2E persona (Fajri GS mengisi, Super Admin mereview) lewat harness controller:
unggah ke-5 **diterima**, ke-6 **ditolak** (pesan dari server); hapus foto
**tengah** → `sort_order` berlubang dan foto berikutnya tetap MAX+1 (sesuai
desain, layar tetap benar); hapus foto pertama → cermin kolom lama naik ke foto
kedua; hapus foto milik orang lain → **403**; halaman detail review
merender, `items.photos` ter-eager-load, thumbnail 4/4 tampil. Data uji
dibersihkan, dev dipulihkan ke potret sebelum uji.

**PROD** (`1d33d9c`, SQL dijalankan manual lalu 3 kueri verifikasi):
backfill **1.029** = single 461 / before 283 / after 285, **0 gagal**; dijalankan
ulang → 0 baris baru (idempoten terbukti). Tiket IT-2026-038/039 → GOLIVE.
