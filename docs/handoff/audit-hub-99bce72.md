# Audit HUB atas `99bce72` — dua temuan sebelum naik prod

> Untuk chat build. Bilang saja: **"baca `docs/handoff/audit-hub-99bce72.md`"**.
> Disusun chat HUB 11 Sep 2026, diverifikasi terhadap data PROD.

---

## Dulu yang lolos — perbaikan kalian bekerja

Kuadu pembersih baru kalian dengan **16.127 kolom klinis prod 2026**:

| pemeriksaan | hasil |
|---|---|
| Kini berbaris banyak | **4.018 (24,9%)** |
| Sisa U+00A0 | **0** ✅ |
| Tag HTML bocor | **0** ✅ |
| Kata menempel pola `</div><div>` | **0** ✅ |
| Teks berakhir `"..."` | **207 dari 207 UTUH** ✅ |

`RiwayatRekamMedisTest` **17/17, 35 assertion**. Nol galat di dev 3 jam terakhir.
Render konsisten: `$satuBaris` meratakan ringkasan, kelima nilai klinis jatuh di
`.pxd-tl__v` yang kini ber-`pre-line`, dan `--strong` mewarisinya.

Dan dua penyimpangan kalian dari saran HUB **keduanya lebih benar** — string
ber-`\n` + `pre-line` (menghindari grid `96px 1fr` bergeser), dan regex `/u`
alih-alih `trim($s,$charlist)` (yang bekerja per-BYTE dan bisa mencacah
multibyte). Saran HUB akan menanam bug halus. Terima kasih sudah menolaknya.

---

## ⛔ TEMUAN 1 — regex hanya menangkap tag PENUTUP

```php
// sekarang
'~<\s*(?:br|/\s*(?:div|p|li|tr))\b[^>]*>~i'
```

`</div>`, `</p>`, `</li>`, `</tr>` jadi `"\n"`. Tapi **tag PEMBUKA** `<div>`,
`<p>`, `<li>` tidak — ia dibuang `strip_tags` **tanpa disisipi apa pun**, jadi
dua kata menempel. Itu **kelas bug yang sama dengan `24ra`** yang seharusnya
ditutup, cuma bentuknya lain.

Kasus tepi (diuji):

```
kontrol 24<div>ganti kawat</div>  ->  "kontrol 24ganti kawat"   ⛔
teks A<p>teks B</p>               ->  "teks Ateks B"            ⛔
<li>satu<li>dua                   ->  "satudua"                 ⛔
```

**4 rekam prod nyata terkena (0,02%)**, dan semuanya mengubah ARTI:

| asli | jadi |
|---|---|
| `ligature 16-15 &amp; 25-27, pc ra<div>pasang 37,47 niti rb 12</div>` | **`pc rapasang 37,47`** |
| `niti ra 18, rb 16<div>pc RA</div>` | **`rb 16pc RA`** |
| `stop elastic<div>next gingivec, scalling, n slicing</div>` | **`stop elasticnext`** |
| `next exo 34,44<div>next pasang 46</div><div>pc 13,23</div>` | **`34,44next pasang`** |

### Usul perbaikan — satu karakter

```php
// usul: '/' jadi '/?' , dan tag dikeluarkan dari grup alternasi
'~<\s*/?\s*(?:br|div|p|li|tr)\b[^>]*>~i'
```

**Sudah HUB uji terhadap seluruh 16.130 kolom klinis 2026:**

| | |
|---|---|
| Semua kasus tepi di atas | **tertutup** ✅ |
| Hasil berbeda dari versi sekarang | **6 kolom (0,04%)** — bedah presisi |
| Berbaris banyak | 4.018 → **4.023** |
| Teks `"..."` jadi rusak | **0** ✅ |
| Jadi kosong padahal berisi | **0** ✅ |
| `<div><div>a</div><div>b</div></div>` | tetap `a ⏎ b` (tak jadi baris kosong berlebih) |

⚠️ Tetap **jangan** masukkan tag inline (`i`, `span`, `ul`, `b`, `em`) —
keputusan kalian soal itu benar dan jangan diubah.

Kalau kalian punya alasan menolak usul ini, **katakan alasannya** — bukan tidak
mungkin ada sisi yang HUB tak lihat. Yang penting jangan dibiarkan tanpa keputusan.

---

## ⚠️ TEMUAN 2 — dev menjalankan berkas uji LAMA

```
dev    : tests/Unit/RiwayatRekamMedisTest.php  148 baris,  9 metode uji
commit : 99bce72:tests/Unit/RiwayatRekamMedisTest.php  248 baris, 17 metode uji
```

Kode perbaikannya di dev **identik** dengan commit (md5 cocok untuk
`CustomerController.php` dan `pasien-redesign.css`). Yang tak ikut naik cuma
berkas ujinya.

Akibatnya: klaim **"uji 9 → 17"** benar untuk commit, **tidak untuk dev**. Siapa
pun yang menjalankan uji di dev akan menjalankan 9 yang lama dan mengira itu
versi terbaru — hijau palsu dengan bentuk yang baru.

(HUB sudah menjalankan versi ter-commit di lokal: **17/17, 35 assertion**. Jadi
ujinya sendiri sehat; yang salah cuma apa yang duduk di dev.)

---

## Yang HUB minta

1. Terapkan perbaikan regex Temuan 1 (atau jelaskan kenapa tidak).
2. **Tambah uji penjaga** untuk tag PEMBUKA — `a<div>b`, `a<p>b</p>`,
   `<li>x<li>y` — supaya kelas bug ini tak bisa kembali diam-diam.
3. Samakan berkas uji di dev dengan yang ter-commit.
4. Jalankan ulang **dengan flag**: `RUN_DB_TESTS=1 php vendor/bin/phpunit
   tests/Unit/RiwayatRekamMedisTest.php` — tanpa flag itu ujinya **melewati diri
   dan tetap melapor "OK"**.

## Catatan lingkungan

⚠️ **mysqld lokal HUB sedang mati** — HUB menjalankan `SHOW COLUMNS` dan
membunuhnya (jebakan yang sudah tercatat di memori, tetap kena). Karena itu
`JejakReservasiTest` kini 15 galat **karena lingkungan, bukan regresi**. Bukti
yang sah dari sebelum mysqld mati: **15/15, 47 assertion**. Kalau kalian
menjalankannya dan hijau, itu yang benar.

## Kalau nanti naik prod

⚠️ Menyentuh `public/assets/css/pasien-redesign.css` → **WAJIB rsync yang
DIBATASI ke `assets/`**.

⛔ Dan verifikasinya **bukan** kode 200 + ukuran. Aset prod di-cache
**`max-age=604800` (7 hari)**. Patokan yang sah: **md5 isi HTTP dengan
pembuat-cache dibandingkan md5 berkas di disk**:

```
md5sum public_html/assets/css/pasien-redesign.css
curl -s "https://app.sweettreats.id/assets/css/pasien-redesign.css?v=$(date +%s)" | md5sum
```

Plus pastikan `filemtime` sejajar di **kedua** lokasi — penanda versi dihitung
dari `laravel/public/` sedangkan yang disajikan dari `public_html/`; deploy tanpa
rsync membuat penanda maju sementara isinya lama, lalu terkunci 7 hari di bawah
URL yang terlihat segar.
