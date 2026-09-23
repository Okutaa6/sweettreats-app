# Audit HUB — `fix/pettycash-audit-fix` (`a4dea38`)

> Tindak lanjut Reza atas `audit-hub-reza-rekonstruksi-pettycash.md`.
> Disusun HUB 16 Sep 2026, diverifikasi terhadap PROD.

---

## Kedua temuan: DITUTUP

| | keadaan |
|---|---|
| **Temuan 1** — `close()` bisa mengunci kas Rp 0 palsu | ✅ diperbaiki |
| **Temuan 2** — AuditLog mencatat angka yang bukan angka yang hilang | ✅ diperbaiki |
| **Temuan 3** — nol uji petty cash | ⏳ tidak disentuh (memang keputusan Ikhsan, bukan pemblokir) |

Satu berkas, 29 baris tambah. Tidak ada logika baru yang dikarang.

### Yang HUB verifikasi

**Kondisi jendela migrasi kini identik di EMPAT tempat** — HUB cocokkan satu per satu:

```
358  $openingWindowOpen = handover_at !== null && opening_set_by === null   (index)
970  $windowOpen        = handover_at !== null && opening_set_by === null   (setOpening)
1494 pagar BARU         = handover_at !== null && opening_set_by === null   (close)
1564                    = handover_at !== null && opening_set_by === null   (resetOpening)
```

Itu yang membuat pagarnya layak dipercaya: ia memakai definisi yang sudah ada,
bukan definisi kelima yang bisa menyimpang diam-diam.

**Ada jalan keluar — periode tidak bisa terkunci selamanya.** HUB memeriksa ini
khusus, karena pagar yang menolak tanpa jalan keluar hanya memindahkan
masalahnya. `resetOpening(mode=cancel)` mengosongkan `handover_at`, dijaga
Super Admin. Jadi migrasi yang telanjur dimulai lalu ditinggalkan bisa
dibatalkan, dan periodenya bisa ditutup normal.

**Angka barunya benar.** `netPerSource()` diangkat jadi `$netBefore` dan dipakai
ulang — nol kueri tambahan. `$totalBefore` tetap dipakai untuk `old_values`
(potret kolom, memang harus apa adanya), `$totalTampil` untuk kalimat yang
dibaca manusia. Pemisahan itu tepat.

**`php -l` bersih · nol penanda konflik dengan `main` · basis memuat `212607e`.**

---

## Radius: pagar ini tidak mengubah apa pun hari ini

HUB mengukur di prod:

| | |
|---|---|
| Periode yang SEDANG dalam jendela migrasi | **0** |
| Periode tertutup ber-`closing_balance` = 0 (gejala nol palsu) | **0** |
| Total periode · yang pernah ditutup | **23 · 0** |

⭐ **`close()` belum pernah dipakai sama sekali di prod.** Jadi cacatnya nyata
tapi **laten** — jebakan yang menunggu, bukan kerusakan yang sudah terjadi.
Itu menempatkan temuan ini pada ukurannya yang benar: mendesak diperbaiki
SEBELUM fitur migrasi dipakai, bukan pembersihan setelah kejadian.

---

## ℹ️ Angka di pesan commit sudah bergeser — dan itu wajar

Pesan commit menyebut Bintaro **Rp 2.605.200**. Saat HUB mengukur ulang sore
ini angkanya **Rp 2.557.200**.

Bukan kesalahan siapa pun: petty cash dipakai sepanjang hari. Bintaro mencatat
transaksi pukul 13:45 dan 13:46 — sesudah pengukuran HUB yang dikutip Reza.

⚠️ Pelajaran untuk runbook, bukan untuk kode: **angka petty cash adalah potret,
bukan konstanta.** Siapa pun yang menjalankan Migrasi Saldo harus membaca angka
yang dicetak layar **saat itu**, bukan angka dari dokumen ini atau dari audit
mana pun.

---

## Yang HUB sarankan sekarang

**Layak naik.** Tapi bukan malam ini, dan alasannya tidak berubah:

Ini cut-over saldo kas lima cabang. Perbaikannya mendarat beberapa jam sebelum
jendela deploy, dan **repo ini masih nol uji petty cash** — jadi satu-satunya
jaring yang ada adalah pembacaan manusia. Malam hari, bersama dua gerbong lain,
itu tipis.

Besok pagi lebih baik, dan bukan sekadar soal waktu: kalau hasil migrasinya
perlu dicocokkan dengan **hitung fisik**, orang kantor harus ada.

### Sebelum naik

1. **Jalankan `resetOpening` sekali di satu cabang uji** (dev atau cabang yang
   paling sepi), lalu buka layarnya: tiap kartu harus berbunyi Rp 0, dan pesan
   suksesnya harus menyebut **dua** angka — saldo tampil dan saldo awal.
2. **Coba tutup periode itu selagi migrasi menggantung** → harus DITOLAK dengan
   pesan yang menyebut sebabnya. Itu pagar yang baru dibangun; ia belum pernah
   dijalankan di lingkungan mana pun.
3. **Lalu batalkan migrasinya** (`mode=cancel`) dan tutup lagi → harus berhasil.
   Itu membuktikan jalan keluarnya ada.

Ketiganya lima menit, dan menutup satu-satunya hal yang belum terbukti:
pagar barunya sendiri.

### Catatan deploy (tidak berubah dari audit pertama)

- **2 SQL manual** — aditif, idempoten (`account_number` nullable, `retired_at`,
  index, + menonaktifkan kategori `pinjaman`). `deploy.sh` TIDAK menjalankan SQL.
- ⛔ Menyentuh `public/assets/css/components.sweettreats.css` → **WAJIB rsync
  DIBATASI ke `assets/`**, diverifikasi md5 bukan kode 200.
- `fund_source_branch_accounts` sudah ada di prod (12 baris) — ALTER-nya
  mengenai data nyata.

---

# Demo end-to-end di DEV — 16 Sep 2026 sore

Dijalankan HUB di dev (branch `fix/pettycash-audit-fix`, 2 SQL diterapkan),
empat adegan per persona, di periode Bintaro 09/2026 (Cash Rp 10.000 tanpa
mutasi · BSI Rp 1.000.000 dengan mutasi −Rp 1.000.000).

| adegan | persona | hasil |
|---|---|---|
| 1. Migrasi Saldo (pertahankan Cash+BCA) | Super Admin | ✅ kartu jadi 0 · pesan menyebut **dua angka** · 8 dompet dipensiunkan |
| 2. Tutup Periode selagi migrasi menggantung | operator cabang | ✅ **DITOLAK** — `is_closed` tetap 0 |
| 3. Batalkan migrasi | Super Admin | ✅ `handover_at` dikosongkan |
| 4. Tutup Periode lagi | operator cabang | ⛔ **berhasil — tapi lihat temuan di bawah** |

Pesan adegan 1, apa adanya:

> *"Migrasi saldo dimulai — saldo Rp 10.000 dikosongkan (saldo awal Rp 1.010.000
> + mutasi bulan berjalan)."*

Temuan 2 tertutup: dua angka muncul, dan keduanya benar.
Adegan 2 membuktikan pagar Temuan 1 benar-benar menggigit — ia belum pernah
dijalankan di lingkungan mana pun sebelum ini.

---

## ⛔ TEMUAN BARU — "Batalkan migrasi" tidak memulihkan saldo

```php
if ($mode === 'cancel') {
    $period->handover_at = null;
    $period->save();          // <- HANYA ini
    ...
```

`source_opening_balances` dan `opening_balance` **tetap berisi angka
lawan-mutasi** yang ditulis saat migrasi. Jadi urutan berikut **melewati pagar
baru sepenuhnya** dan menghasilkan persis kerusakan yang pagar itu dibangun
untuk mencegah:

```
Migrasi Saldo  →  Batalkan  →  Tutup Periode
```

Terukur di dev:

| | |
|---|---|
| `opening_balance` sebelum migrasi | **Rp 1.010.000** |
| sesudah batal + tutup | **Rp 1.000.000** |
| **tidak kembali** | **Rp 10.000** |
| `closing_balance` terkunci | **Rp 0** (seharusnya Rp 10.000) |

Mekanismenya umum, bukan khusus angka ini: **dompet yang tidak punya transaksi
di periode itu dijatuhkan dari snapshot** saat migrasi (memang disengaja —
"Wallet tanpa transaksi cukup tidak masuk snapshot"), dan `cancel` tidak pernah
mengembalikannya. Makin banyak dompet diam, makin besar yang hilang.

### ⭐ Yang meringankan

`AuditLog` menyimpan potret lengkapnya:

```json
{"source_opening_balances":{"1":10000,"3":1000000},"opening_balance":1010000}
```

Jadi pemulihan manual mungkin — tapi harus ada yang **tahu** bahwa itu perlu
dilakukan, dan tidak ada apa pun di layar yang memberitahunya.

### Usul

`cancel` harus mengembalikan `source_opening_balances` + `opening_balance` ke
nilai sebelum migrasi. Sumbernya sudah ada di `AuditLog.old_values`, tapi
membacanya kembali dari sana rapuh. Lebih bersih: **simpan potretnya di
periodenya sendiri** saat migrasi dimulai (mis. kolom
`opening_snapshot_before`), lalu `cancel` memulihkan dari situ dan
mengosongkannya.

⚠️ **Ralat HUB**: audit sebelumnya menyebut jalur `cancel` sebagai "jalan keluar"
dan memakainya sebagai alasan bahwa periode tak bisa terkunci selamanya. Itu
benar soal *terkunci*, tapi HUB **tidak memeriksa apakah ia memulihkan keadaan**.
Ternyata tidak. Demo inilah yang menangkapnya — bukan pembacaan kode.

---

## Keadaan dev sesudah demo

Dikembalikan: branch `test/reza-checklist-dev` @ `d95f9d3`, periode 26 kembali
ke `opening 1.010.000 / belum ditutup / handover kosong`, 8 dompet
di-un-pensiunkan, kategori `pinjaman` dihidupkan lagi, `.env` dipulihkan,
beranda dev HTTP 200.

ℹ️ Kolom `retired_at` + index **sengaja dibiarkan** di dev — aditif, tidak
mengubah perilaku selama nol baris terisi, dan dev memang akan membutuhkannya.
