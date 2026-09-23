# Prompt untuk chat BUILD — Teks rekam medis dilebur jadi satu paragraf

> Salin isi berkas ini, atau cukup bilang ke chat build: **"baca
> `docs/handoff/prompt-build-teks-rekam-medis.md`"**.
>
> Disusun chat HUB 11 Sep 2026 dari pengukuran langsung di PROD.

---

Kamu chat **build** untuk SweetTreats. Ada satu cacat tampilan di halaman pasien
yang dilaporkan Ekky: *"banyak tulisan rekam medis kepotong-potong setengah."*

HUB sudah menelusurinya sampai akar dan **memisahkan mana yang cacat kita dan
mana yang bukan**. Baca dua bagian di bawah sebelum menyentuh apa pun.

---

## ⛔ BAGIAN 0 — Yang BUKAN cacat kita. JANGAN diperbaiki.

Laporan awalnya menunjuk satu rekam yang berbunyi:

```
Rencana: posisi kawat niti 0,12 up/low...
```

Terlihat seperti terpotong. **Bukan.** Diverifikasi tiga lapis di prod:

| lapis | isi |
|---|---|
| Yang tampil di layar | `posisi kawat niti 0,12 up/low...` |
| Kolom `treatment_plan_note` di DB kita | `'posisi kawat niti 0,12 up/low...'` (32 karakter) |
| **`raw_payload`** — jawaban MENTAH API Omnicare | **identik, titik-titik termasuk** |

`Str::limit` kita di 90 karakter; teksnya 32 — tak mungkin kena.

Sebarannya: **207 dari 16.130 kolom klinis (1,3%) berakhir "..."**, panjang
**8 sampai 179 karakter** (tengah 31). Kalau ini pemotongan sistematis,
panjangnya akan seragam di satu angka. Yang bervariasi begini hampir pasti
**dokternya yang mengetik "..."** sebagai singkatan.

⛔ **JANGAN menambahkan logika apa pun untuk "memulihkan" teks ini.** Tak ada
yang bisa dipulihkan — sumbernya memang begitu. Kalau isinya dirasa kurang,
itu pertanyaan untuk dokter atau vendor, bukan untuk kode.

---

## BAGIAN 1 — Yang BENAR-BENAR rusak

### Akarnya

`app/Http/Controllers/Crud/CustomerController.php`, `buildRiwayat()` (~baris 499):

```php
$clean = fn (?string $v) => $v === null ? null : trim(
    html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $v)))
);
```

**Ganti-baris diubah jadi SPASI.** Di catatan gigi, tiap baris adalah satu
temuan atau satu tindakan. Meleburnya membuat catatan terbaca seperti potongan
yang disambung serampangan.

Contoh nyata — rekam 9 Sep 2026, pasien Vanessa Taw:

```
ASLI   : ligature gigi 24-23<br>reposisi button ke palatal 24-27 <br>bikin hook
         di gigi 44<br>edukasi elastik 1/8 segitiga di gigi 13-43-44...
TAMPIL : ligature gigi 24-23 reposisi button ke palatal 24-27  bikin hook di
         gigi 44 edukasi elastik 1/8 segitiga di gigi 13-43-44...
```

Empat baris klinis → satu paragraf.

### Seberapa luas (diukur prod, kolom klinis terisi 2026 = 16.130)

| cacat | jumlah | porsi |
|---|---|---|
| **`<br>` dilebur jadi spasi** | **4.303** | **26,7%** |
| **Kata MENEMPEL (`</div><div>`)** | **189** | **1,2%** |
| **`&nbsp;` tak terbuang `trim()`** | **855** | **5,3%** |

**Kata menempel** itu yang paling berbahaya karena mengubah arti, bukan cuma
rupa. `strip_tags` membuang `</div><div>` tanpa menyisipkan apa pun:

```
ASLI   : <div>ligature 22-11, pc 17-13 ocs diganti baru di distal 24</div><div>ra ganti niti 0.16</div>
TAMPIL : ligature 22-11, pc 17-13 ocs diganti baru di distal 24ra ganti niti 0.16
                                                             ^^^^ "24ra"
```

Contoh lain yang terukur:
`next exo 34,44<div>next pasang 46</div>` · `<div>gingivitis</div><div>pulp rev 47 45 37 26</div>`

**`&nbsp;`**: `html_entity_decode` mengubahnya jadi U+00A0, dan **`trim()` PHP
tidak membuang U+00A0** (ia hanya membuang `" \t\n\r\0\x0B"`). Hasilnya spasi
tak terlihat menempel di ujung teks.

### ⚠️ Ini BUKAN regresi dari PR kalian

`$clean` yang identik sudah ada di `398e4dc` (sebelum jejak reservasi naik),
dipakai untuk `keluhan`, `objektif`, `diagnosa`. PR kalian menambah `subjektif`
dan `rencana` lewat pembersih yang sama — jadi cacatnya **jadi jauh lebih
terlihat** (karena `rencana` terisi di 37% rekam dan kini tampil di banyak kartu
yang dulu kosong), **bukan kalian yang membuatnya.**

---

## Arah perbaikan — bentuk akhirnya keputusan kalian

**Pertahankan ganti-barisnya, jangan leburkan.** Garis besarnya:

1. Ubah `<br>`, `<br/>`, `<br />`, `</div>`, `</p>` jadi penanda baris (mis.
   `"\n"`) **SEBELUM** `strip_tags`, bukan sesudah.
2. Baru `strip_tags`, lalu `html_entity_decode`.
3. Pecah per baris, rapikan spasi tiap baris, buang baris kosong.
4. Kembalikan sebagai **array baris** — biar blade merender tiap baris sebagai
   elemen sendiri.
5. Saat trim, ikut buang **U+00A0** (`"\u{00A0}"`), bukan hanya spasi ASCII.

⛔ **Jangan pakai `{!! !!}`** untuk merender hasilnya. Teks ini datang dari
sistem luar; render tiap baris dengan `{{ }}` yang meng-escape. Kalau butuh
jarak antarbaris, pakai elemen/CSS, bukan HTML mentah dari data.

⚠️ Perhatikan blade `resources/views/crud/customer/edit.blade.php` ~baris
189–200: `.pxd-tl__kv` itu **grid 2 kolom** (`96px 1fr`) dan tiap `@if`
memancarkan **sepasang** span (label + nilai). Kalau nilainya jadi banyak baris,
jaga pasangan itu tetap utuh atau grid-nya akan bergeser.

## Kriteria terima

- Rekam yang aslinya `a<br>b` menampilkan **dua baris**, bukan `a b`.
- Rekam `...distal 24</div><div>ra ganti...` **tidak lagi** menghasilkan
  `24ra` — kata-katanya terpisah.
- Teks ber-`&nbsp;` tak lagi menyisakan spasi tak terlihat di ujung.
- Rekam berbaris tunggal tampil **persis seperti sekarang** (tanpa perubahan).
- Teks berakhir `"..."` **dibiarkan apa adanya** (Bagian 0).
- Kolom kosong tetap **tidak** memunculkan label hampa.
- Uji lama tetap hijau: `RUN_DB_TESTS=1 php vendor/bin/phpunit
  tests/Unit/RiwayatRekamMedisTest.php` (9 uji / 22 assertion).

⚠️ **Uji MELEWATI DIRI dan tetap melapor "OK" tanpa `RUN_DB_TESTS=1`** — 9
dilewati, nol assertion, layar tetap hijau. Selalu sertakan flag itu.

## Aturan kerja repo ini

- **SQL langsung, bukan migration** (`database/sql/`, bertanggal, idempoten) —
  tapi perbaikan ini kemungkinan **tak butuh SQL sama sekali**.
- ⛔ **Jangan `Schema::hasTable`/`hasColumn`/`SHOW COLUMNS`/`information_schema`**
  — terbukti **mematikan mysqld lokal** (HUB kena 11 Sep). Pakai
  `kolomAda()`/`tabelAda()`.
- **Ikon FontAwesome 5.8.2** · warna `#461500` terkunci.
- **Jangan auto-commit.** Biarkan tak ter-stage untuk ditinjau Ekky. Branch sendiri.
- ⚠️ **Working copy lokal & DEV dipakai bersama banyak chat.** `git status` dulu;
  catat branch yang hidup di dev dan **kembalikan** sesudah selesai.
  Login dev `/signin`, bukan `/login`.
- ⚠️ Kalau menyentuh `public/assets/**`, naik prod **WAJIB diikuti rsync yang
  DIBATASI ke `assets/`** — rsync seluruh `public/` pernah mematikan prod 6 menit.

## Bahan rujukan

Angka lengkap + konteksnya: `docs/handoff/data-reservasi-2026-09-11.md`
§24 (ralat `prescription`) dan §34 (cacat teks ini).
