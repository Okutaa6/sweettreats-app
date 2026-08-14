# Rencana: Lembar Bayar ROP (rekap pembayaran untuk bos)

Status: **ACC Ekky 2026-08-14**, belum ada koding. Dikerjakan di **chat terpisah**
(prompt: `docs/spec/prompt-chat-baru.md` §G).

## 1. Kenapa ada

Finance mengirim ROP ke bos **satu per satu** lewat WhatsApp: satu berkas ROP +
satu berkas invoice, dikali jumlah item. Lima belas pembayaran = tiga puluh
berkas. Bos harus membuka semuanya, membayar, lalu mengabari balik.

Per 14 Agu 2026 prod memuat **7 ROP `diajukan`** senilai Rp1.119.572 tersebar di
5 cost centre — masalahnya nyata, bukan hipotetis.

**Masalah kedua, yang lebih mudah terlewat.** Bos sering membayar beberapa ROP
dalam **satu transfer**, tapi `paid_proof_path` itu per-ROP dan
`RopQueueController::bayar()` menerima `$id` tunggal. Jadi Finance mengunggah
bukti transfer yang sama berulang kali. Kalau lembar rekap dibangun tanpa
memikirkan ini, kerja Finance cuma **pindah tempat**: dari kirim 30 berkas jadi
unggah 1 berkas 15 kali. Karena itu fiturnya sepasang.

## 2. Keputusan Ekky (14 Agu 2026)

| Pilihan | Keputusan |
|---|---|
| Bentuk kiriman | **PDF cetak** lewat browser — bukan tautan. Bos tak mau buka aplikasi |
| Jejak rekap | **Disimpan & bernomor** (`BAYAR/2608/001`) |
| Lampiran PDF | **Disebut di daftar, dikirim terpisah** — PDF tak bisa ditempel di halaman cetak |
| ROP tanpa lampiran | **Boleh ikut, tapi ditandai** |

## 3. Pakai ulang, jangan tulis ulang

| Keperluan | Sudah ada di |
|---|---|
| Penomoran atomik, reset per bulan | `RopService::nomorBaru()` (`app/Services/RopService.php:86`) — mekanisme `rop_counter` + `LAST_INSERT_ID`. **Jangan** `MAX(number)+1` |
| Tandai dibayar + log + validasi transisi | `RopService::tandaiDibayar()`, contoh pemakaian `RopQueueController::bayar():319` |
| Peta transisi status yang sah | `RopService::TRANSISI` (`:68`) |
| Simpan lampiran (file & base64 kamera HP) | `App\Services\RopAttachment::simpan()` |
| Jejak audit status | `App\Models\RopStatusLog` |
| Gerbang Finance | pola `guard()` di `RopQueueController:44`, `RopRekapController:33` |
| Tema burgundy Finance | `body.zona-finance` di `public/assets/css/zona.css` |
| CSS modul ROP | `public/assets/css/rop.sweettreats.css` + pola `@section('css')` ber-`filemtime` |

**Lampiran ROP ada di `public/uploads/rop/{jenis}/{Y}/{m}`** — pilihan sadar
(invoice bukan wajah; lihat docblock `RopAttachment`). Halaman cetak bisa
menempelkannya langsung dengan `<img src="{{ asset($path) }}">`.

⚠️ **`/finance/rop/rekap` yang sudah ada BUKAN ini.** Itu rekap **akuntansi
bulanan** (total per cost centre + draf Accurate, lihat `rencana-pengajuan-rop.md`
§166). Bikin layar sendiri, jangan ditumpuk.

## 4. Rancangan

### 4.1 Dua tabel baru

```
rop_payment_sheets
  id · number (BAYAR/2608/001) · dibuat_oleh · dibuat_at
  dikirim_at (nullable — dicap saat Finance menekan "sudah dikirim ke bos")
  jumlah_item · total   ← snapshot
  catatan (nullable)

rop_payment_sheet_items
  sheet_id · rop_id · amount   ← snapshot nominal saat lembar dibuat
```

**Kenapa pivot, bukan satu kolom FK di `rop_requests`:** dari 15 item bos sering
membayar 12. Tiga sisanya masuk lembar berikutnya, dan riwayat "pernah ada di
lembar 1" harus tetap terbaca. Kolom FK tunggal akan menimpanya.

**Kenapa nominal di-snapshot:** lembar yang sudah dicetak dan dikirim ke bos tak
boleh berubah angkanya kalau ROP-nya kelak diedit. Yang tercetak = yang tersimpan.

Nomor lembar: **tambahkan method saudara** di `RopService` (mis.
`nomorLembarBayar()`) yang memakai `rop_counter` dengan kunci cost-centre khusus
`BAYAR`. Jangan menyalin SQL `LAST_INSERT_ID`-nya.

### 4.2 Layar Finance — `/finance/rop/lembar-bayar`

Desktop, burgundy. Device-intent sesuai `rencana-pengajuan-rop.md` §206.

- Daftar ROP yang layak masuk lembar · **centang banyak** · total berjalan
  ("7 dipilih · Rp1.119.572")
- **Buat Lembar Bayar** → tersimpan, dapat nomor
- Daftar lembar terdahulu: nomor · tanggal · jumlah item · total · sudah
  dikirim/belum · berapa yang sudah dibayar
- **Tandai sudah dikirim ke bos** → mengecap `dikirim_at`

### 4.3 Halaman cetak — `/finance/rop/lembar-bayar/{id}/cetak`

⚠️ **Blade berdiri sendiri, JANGAN `@extends('layouts.app')`** — sidebar dan
chrome aplikasi akan ikut tercetak. Belum ada pola `@media print` di repo ini,
jadi ini yang pertama: A4, `@page { margin }`, `page-break-after` antar lampiran.

**Halaman 1 — daftar.** Ikuti bahasa visual formulir ROP kertas yang dipakai
sekarang (lihat contoh formulir di lampiran koordinasi 14 Agu): logo + identitas
klinik + judul + blok tanda tangan.

- Kepala: logo, alamat klinik, `REKAP PERMINTAAN PEMBAYARAN`, nomor lembar,
  tanggal, jumlah item
- Tabel: No · ROP · Cost Centre · **Dibayarkan kepada** · Keterangan · Jumlah
- **TOTAL** besar + **terbilang**
- Blok tanda tangan meniru formulir kertas: Dibuat (Finance) · Disetujui (Director)

⚠️ **`pay_to_text` WAJIB dicetak apa adanya dan menonjol.** Kolom bank di tabel
`vendors` (`bank_nama`, `bank_rekening`, `bank_atas_nama`) **semuanya NULL** —
hasil sinkron Accurate yang tak membawa data bank — dan **setiap ROP nyata
`vendor_id`-nya NULL**. Orang menaruh tujuan transfer di `pay_to_text`:
`"VA MANDIRI - 896 085888064313"`, `"TALITHA MARITZA// BCA 5475304095"`,
`"VA Shopee 600 082130292683"`. Itulah nomor rekening yang dipakai bos. Jangan
mengandalkan relasi vendor; jangan mencoba memecah stringnya.

**Halaman berikutnya — lampiran**, satu per halaman: nomor ROP + penerima +
nominal sebagai kepala, lalu gambarnya besar.

| Keadaan lampiran | Perlakuan |
|---|---|
| Foto | ditempel di halaman lampiran |
| PDF | tak bisa ditempel → di halaman 1 ditulis *"lampiran PDF — terlampir terpisah"* supaya Finance ingat menyertakan berkasnya dan bos tahu ada yang menyusul |
| Tak ada | ditandai jelas di kolomnya. Per 14 Agu ada 3 (2× alat pembersih kaca, 1× lepas bracket banner) |

### 4.4 Tandai dibayar sekaligus

`POST /finance/rop/lembar-bayar/{id}/bayar-banyak` — centang beberapa item,
**satu** berkas bukti transfer, sekali unggah. Simpan berkasnya sekali lewat
`RopAttachment`, lalu **pakai path yang sama** untuk tiap ROP terpilih dengan
memanggil `RopService::tandaiDibayar()` per item di dalam satu transaksi.

Jangan menulis `rop_requests` langsung — lewat service, supaya validasi transisi
dan `RopStatusLog` tetap jalan.

### 4.5 Terbilang

Tak ada helper `terbilang()` di repo ini (sudah dicek 14 Agu). Tambahkan fungsi
**murni** di `app/Helpers.php` — mudah diuji, tanpa dependensi.

## 5. Keputusan yang menempel di rencana ini

**Status `disetujui` selama ini tak pernah terpakai.** Kelima ROP yang lunas
melompat `diajukan` → `dibayar`, padahal `RopService::TRANSISI` mewajibkan lewat
`disetujui`. Artinya persetujuan bos belum pernah tercatat di sistem sama sekali.

Rencana ini memutuskan: **membuat lembar bayar = Finance menyetujui.** Item yang
masuk lembar dinaikkan ke `disetujui` (Finance memang memeriksanya sebelum meminta
uang ke bos), lalu `bayar-banyak` menaikkannya ke `dibayar`. Peta transisi
dihormati tanpa memaksa Finance menekan tombol dua kali, dan lembar bayar punya
arti tegas: *"sudah lolos Finance, menunggu uang bos."*

Kalau kelak persetujuan bos mau tercatat terpisah dari persetujuan Finance, itu
kolom/status tambahan — putuskan sebelum dibangun, bukan sesudah.

## 6. Berkas

**Baru**
- `database/sql/2026_08_XX_rop_payment_sheet.sql`
- `app/Models/RopPaymentSheet.php`, `app/Models/RopPaymentSheetItem.php`
- `app/Http/Controllers/Finance/RopLembarBayarController.php`
- `resources/views/finance/rop/lembar-bayar/index.blade.php`
- `resources/views/finance/rop/lembar-bayar/cetak.blade.php` ← berdiri sendiri

**Diubah**
- `routes/web.php` — di bawah grup `finance/` yang sudah ada
- `app/Services/RopService.php` — `nomorLembarBayar()`
- `app/Helpers.php` — `terbilang()`
- `public/assets/css/rop.sweettreats.css`

## 7. ⚠️ Dua ranjau yang sudah menggigit 14 Agu 2026

1. **Rute WAJIB di bawah segmen `finance/`.** `AuthGuard` mencocokkan
   **segmen-1** URL dengan node `access_path`; `finance` sudah ada di
   `AuthGuard::SKIP_RBAC`, jadi aman. Segmen-1 **baru** (mis. `lembar-bayar/...`)
   akan **dipantulkan ke halaman beranda untuk semua orang, Super Admin
   sekalipun** — dan gejalanya **302 ke beranda, bukan 403**, jadi terlihat
   seperti tombol yang tak berfungsi. Itu persis bug yang mematikan fitur
   kategori petty cash Reza (lihat `log-update.md` 14 Agu).
2. **Menyentuh `public/assets/**` → prod WAJIB di-rsync sesudah deploy.**
   `public_html` prod itu SALINAN, bukan symlink.

## 8. Cara membuktikannya

1. **SQL di dev dulu, jalankan dua kali** — pastikan idempoten.
2. **Buat lembar dari 7 ROP nyata.** Periksa: nomor `BAYAR/2608/001`,
   `jumlah_item` = 7, `total` = 1.119.572, snapshot nominal cocok.
3. **Cetak → simpan PDF.** Periksa dengan mata: sidebar tidak ikut, tabel tak
   terpotong, total & terbilang benar, tiap lampiran foto satu halaman, yang PDF
   tertulis "terlampir terpisah", tiga yang tanpa lampiran tertandai.
4. **`pay_to_text` tercetak utuh** — termasuk yang panjang seperti
   `"TALITHA MARITZA// BCA 5475304095"`. Terpotong = uang salah alamat.
5. **Bayar banyak**: centang 3 item, unggah SATU bukti → ketiganya `dibayar`,
   `paid_proof_path` ketiganya menunjuk berkas yang sama, ada 3 baris
   `rop_status_log`.
6. **Uji gerbang lewat HTTP sungguhan**, bukan menebak dari kode: login sebagai
   user non-Finance → buka layar dan POST `bayar-banyak` → harus ditolak. **Periksa
   URL akhirnya** — `curl -L` bisa mengikuti pentalan ke halaman login lalu
   melaporkan 200. Sudah dua kali menipu.
7. **Nomor tak bertabrakan**: buat dua lembar berturut-turut → 001, 002.

## 9. Di luar cakupan

- **Tautan yang dibagikan** untuk bos (centang lunas sendiri + unggah bukti
  langsung). Ditunda sadar: bos tak mau buka aplikasi, dan tautan tanpa login itu
  urusan keamanan tersendiri — koordinasikan dengan chat security-hardening kalau
  kelak dibangun.
- Mengisi kolom bank `vendors`.
- Mengubah form pengajuan agar menolak lampiran PDF.
