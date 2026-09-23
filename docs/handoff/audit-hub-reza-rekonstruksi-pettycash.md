# Audit HUB — push Reza `rekonstruksipettycash` (`212607e`)

> Untuk Reza / chat yang melanjutkan. Disusun HUB 16 Sep 2026.
> Diuji di worktree terpisah + disimulasikan terhadap **data PROD nyata** (baca-saja).

---

## Ringkasan

Kodenya **jauh lebih rapi dari dugaan awal HUB**. Banyak jebakan repo ini yang
sudah dihindari sendiri oleh Reza tanpa diberi tahu. Dua temuan di bawah bukan
soal kecerobohan — keduanya di celah yang memang gelap.

⛔ **Belum layak naik prod malam ini** sampai Temuan 1 diputuskan. Temuan 2
(nol uji) keputusan Ekky, bukan pemblokir teknis.

---

## Yang sudah benar — diverifikasi HUB, bukan dibaca sekilas

| pemeriksaan | hasil |
|---|---|
| `php -l` seluruh berkas PHP yang berubah | bersih ✅ |
| Rute baru bersegmen-1 baru? (jebakan 302-senyap) | **tidak** — semua di bawah `pettycash`/`finance` yang sudah ada ✅ |
| Sisa rujukan ke 2 rute yang DIHAPUS (`loanReturn`, `topup`) | **nol** di seluruh app/resources/public ✅ |
| Jebakan COLLATION `raw_branch` (JOIN gagal di prod) | **terhindar** — Reza pakai `RawBranch::find()`, bukan JOIN ✅ |
| `Schema::hasTable` (mematikan mysqld lokal) di jalur petty cash | **nol** ✅ |
| Wallet pensiun lenyap dari riwayat bulan lampau? | **tidak** — ada "jaring pengaman wallet pensiunan" yang membangunkan kartunya kalau punya saldo/transaksi di periode itu ✅ |
| Pinjaman menggantung akibat fitur dihapus? | **nol** — `borrower_name` 0 baris, `settles_transaction_id` 0 baris ✅ |
| Kedua SQL | aditif, idempoten, dan punya bagian "yang SENGAJA TIDAK dilakukan" ✅ |

**Matematika uang disimulasikan atas saldo prod 5 cabang** — tiap kartu wajib
jatuh tepat Rp 0 sesudah migrasi:

```
Bintaro    Cash 82.200 · BCA 2.113.701 · BSI 246.756 · GoPay 162.543   -> semua 0 ✅
Kalideres  BCA 1.000.000 · BSI 0                                        -> semua 0 ✅
Sunter     Cash 63.000 · BCA 807.000                                    -> semua 0 ✅
Pamulang   BCA 426.090 · Seabank 154.410                                -> semua 0 ✅
Cinere     BCA 1.000.000                                                -> semua 0 ✅
```

Trik `$nol[$sid] = -$net` itu benar dan elegan: mutasi tidak dihapus, tapi tiap
kartu tetap berbunyi nol. Lolos di kelima cabang.

ℹ️ Konteks prod: 5 wallet benar-benar dipakai (BCA 314 trx · Cash 106 · BSI 53 ·
Seabank 29 · GoPay 21), **Rp 97 juta dalam 90 hari**. Bintaro & Sunter yang
punya 4 wallet — merekalah sasaran migrasi.

---

## ⛔ TEMUAN 1 — bulan bisa DITUTUP di tengah migrasi, dan nol palsunya jadi permanen

`close()` tidak punya pagar terhadap migrasi yang menggantung:

```php
public function close(Request $request, $periodId)
{
    $this->requireWriteAccess();          // <- BUKAN Super Admin
    ...
    if (! $period->is_closed) {
        $net = (float) $trxClass::netForPeriod($period->id);
        $period->update([
            'closing_balance' => (float) $period->opening_balance + $net,
            'is_closed'       => 1,
        ]);
    }
```

Urutan yang merusak, dan semuanya jalur normal:

1. Super Admin menekan **Migrasi Saldo**. `opening_balance` diisi lawan-mutasi,
   `handover_at` = now, `opening_set_by` = **null** (menunggu hitung fisik).
2. Operator cabang **belum sempat** mengisi hitung fisiknya.
3. Siapa pun ber-`requireWriteAccess` menekan **Tutup Periode**.
4. `closing_balance = opening_balance + net` = **0 tepat, karena memang dirancang
   begitu**. Bulan terkunci dengan kas Rp 0, dan angka itu **terbawa sebagai
   saldo awal bulan depan**.

Di Bintaro hari ini yang lenyap dari buku bukan Rp 80.456 melainkan
**Rp 2.605.200** — dan `is_closed` tidak punya jalan balik lewat UI.

Uangnya tidak hilang dari database (transaksi utuh, AuditLog menyimpan saldo
awal), tapi memulihkannya jadi pekerjaan tangan.

⚠️ Perhatikan asimetrinya: `resetOpening()` dijaga **Super Admin + bulan
berjalan + tidak read-only**, sedangkan `close()` — yang membekukan hasilnya —
cuma `requireWriteAccess()`.

**Usul:** tolak `close()` selama `handover_at !== null && opening_set_by === null`,
dengan pesan yang menyebut sebabnya ("migrasi saldo belum diisi operator
cabang"). Pagar satu `if`, dan pola "tombol mati yang bilang kenapa" (P3) sudah
jadi kebiasaan repo ini.

---

## ⚠️ TEMUAN 2 — angka yang dicatat bukan angka yang hilang dari layar

```php
$openingBefore = $period->openingPerSourceResolved();
$totalBefore   = array_sum($openingBefore);     // <- saldo AWAL saja
...
AuditLog::record('update', sprintf('... Rp %s dikosongkan ...', number_format($totalBefore)));
```

`$totalBefore` hanya komponen **saldo awal**, bukan saldo yang tampil
(saldo awal + mutasi bulan berjalan) — padahal yang dikosongkan dari layar
adalah yang tampil. Diukur di prod, ia meleset **ke dua arah**:

| cabang | dicatat AuditLog | yang benar-benar hilang dari layar |
|---|---|---|
| Bintaro | Rp 80.456 | **Rp 2.605.200** (32× lebih besar) |
| Cinere | Rp 110.100 | Rp 1.000.000 |
| Kalideres | Rp 529.500 | Rp 1.000.000 |
| Sunter | Rp 398.100 | Rp 870.000 |
| Pamulang | Rp 830.000 | **Rp 580.500** (lebih kecil) |

Angka yang sama juga muncul di pesan sukses yang dibaca operator: *"saldo awal
Rp 80.456 dikosongkan"* — sementara di layarnya Rp 2,6 juta baru saja jadi nol.
Di operasi yang menyentuh uang, catatan itu satu-satunya bukti keadaan sebelum.

**Usul:** catat keduanya — saldo awal *dan* saldo tampil per wallet — atau
minimal pakai saldo tampil sebagai angka utama. Isinya sudah ada di tangan
(`$openingBefore` + `netPerSource()`), tinggal dijumlahkan.

---

## ⚠️ TEMUAN 3 — nol uji, dan repo ini memang belum punya uji petty cash sama sekali

Push ini: **0 berkas uji**. Repo: **0 uji petty cash** sebelumnya.

Yang tidak tertutup apa pun: migrasi saldo, pemensiunan wallet per cabang,
jaring pengaman wallet pensiunan, `netPerSource`, `openingPerSourceResolved`,
dan penutupan periode — 916 baris berubah di satu controller yang memegang
uang tunai lima cabang.

HUB **tidak** meminta ini jadi syarat naik prod — itu keputusan Ekky. Tapi
minimal tiga kasus layak dikunci, dan ketiganya murni hitungan (tidak perlu
route, jadi aman dari jebakan mysqld lokal):

1. tiap kartu jatuh Rp 0 sesudah migrasi (kendali: matikan trik `-$net` → merah)
2. tak boleh memensiunkan SELURUH wallet cabang
3. periode dalam migrasi tak bisa ditutup (sesudah Temuan 1 diperbaiki)

---

## Catatan deploy

- **2 SQL, dijalankan MANUAL** (aditif: `account_number` jadi nullable,
  `retired_at`, index, + menonaktifkan kategori `pinjaman`). `deploy.sh` TIDAK
  menjalankan SQL.
- ⛔ Menyentuh `public/assets/css/components.sweettreats.css` → **WAJIB rsync
  yang DIBATASI ke `assets/`**. Verifikasi md5, bukan kode 200 (cache 7 hari).
- `fund_source_branch_accounts` sudah ada di prod (12 baris) — ALTER-nya
  mengenai data nyata, bukan tabel kosong.
