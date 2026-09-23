# Audit HUB — pembatalan persetujuan ROP (`feat/rop-batal-persetujuan`)

> Untuk chat build. Bilang saja: **"baca `docs/handoff/audit-hub-rop-batal-persetujuan.md`"**.
> Disusun 16 Sep 2026. Uji dijalankan sendiri oleh HUB, bukan dipercaya dari laporan.

---

## Dulu yang lolos

| pemeriksaan HUB | hasil |
|---|---|
| `RopBatalPersetujuanTest` | **15/15, 69 assertion** ✅ |
| `tests/js/uji-drawer-rop.cjs` | **38 pemeriksaan hijau** ✅ |
| Uji JS benar-benar membaca blade asli? | **YA** — `readFileSync` + regex `@section('js')` ✅ |
| Kendali negatif HUB sendiri di sisi JS | baris 586 `bisaBayar` → `false` ⇒ **3 merah**, berkas dikembalikan **md5 cocok** ✅ |
| Basis branch | `23fb5b1` = **prod**, bukan basis lama ✅ |
| `vendor/` di worktree | direktori nyata, **bukan symlink** ✅ |
| `RopStatusLog` terimpor di `RopService` | ya, baris 8 ✅ |

**Temuan #1 kalian benar, dan premisnya sudah HUB verifikasi**: `cetak()` cuma
ber-`guard()` + `findOrFail` — **tidak ada pagar status sama sekali**. Lembar
memang bisa dicetak ulang kapan saja, oleh siapa pun yang boleh membuka Finance.
Jadi "dokumen sejarah" itu **tidak arsip — ia diterbitkan ulang sesuai permintaan**.

⭐ HUB perlu mengakui ini: 16 Sep HUB memberi tahu Ekky bahwa total lembar yang
beku itu **"disengaja, biarkan"**. Itu benar soal *jangan menulis ulang sejarah*,
tapi HUB tidak memikirkan bahwa dokumennya bisa dicetak ulang — yang mengubahnya
dari catatan menjadi tagihan hidup. **Jawaban kalian lebih baik daripada saran
HUB**: TOTAL lama tetap tercetak, selisihnya ditulis sebagai baris baru. Dua-duanya
dapat.

Yang juga tepat dan jangan diubah:
- Coret **dan** pudar **dan** cap — tiga tanda, karena satu bisa hilang di
  fotokopi hitam-putih. Ini dokumen yang menggerakkan uang.
- `terbilang` mengikuti "yang harus dibayar". Di dokumen pembayaran, terbilang
  itu pengaman kedua; kalau ia membacakan angka lama, ia mengesahkan angka salah.
- Selisih dihitung dari `items.amount` (**snapshot**), sumber yang sama dengan
  `lembar->total` — jadi selisihnya pasti bertemu. Memakai `rop->amount` yang
  hidup akan membuat angka tidak menutup.
- `STATUS_KADALUARSA` ikut ditandai, bukan cuma `ditolak`. Tidak diminta handoff,
  dan benar.
- Pagar `urungkanPenolakan` terhadap pembatalan — **tangkapan yang HUB tidak
  minta dan tidak terpikir**. Mengurungkan akan memulihkan ke `diajukan` (status
  yang salah) sambil menimpa `reviewed_by` penyetuju aslinya. Bagus.
- Uji kendali `5e` (lembar tanpa pembatalan tidak berubah sedikit pun) dan `6b`
  (penolakan biasa tetap bisa diurungkan). Itu yang membuat 15/15 layak dipercaya.

---

## ⛔ TEMUAN — pesan ke PENGAJU masih berbunyi "Pengajuan ditolak"

Temuan #4 kalian sendiri: *"pesan 'alasan wajib diisi' menyebut 'apa yang harus
diperbaiki' — salah alamat untuk pembatalan."* Betul. Tapi perbaikannya berhenti
di **layar Finance**. Kanal yang benar-benar sampai ke pengaju tidak ikut:

```php
// RopService::tolak() — tidak berubah
return $this->pindah($rop, STATUS_DITOLAK, $actorId, $alasan, [...],
    'rop_ditolak', 'Pengajuan ditolak', function (RopRequest $r) use ($alasan) {
        return $r->number . ' ditolak. Alasan: ' . $alasan;
    });
```

Judul `Pengajuan ditolak` dan badan `… ditolak. Alasan: …` dikirim **sama persis**
baik dari `diajukan` maupun dari `disetujui`.

**Bukti prod, hari ini** — tiga notifikasi yang lahir dari pembatalan 16 Sep:

```
[rop_ditolak] Pengajuan ditolak
  ROP/BIN/2609/008 ditolak. Alasan: VA sudah kedaluwarsa sebelum sempat dibayar…
  link=/pengajuan/79   belum dibaca
```

Anisa mengajukan ini 3 Sep, **disetujui** 3 Sep, dan 13 hari kemudian menerima
"Pengajuan ditolak". Yang sebenarnya terjadi: persetujuannya ditarik, dan ia
**harus mengajukan ulang**. Kalimat `alasan` memang membawa maksudnya — tapi
judulnya tidak, dan justru judul yang terbaca lebih dulu.

Ini persis kelas yang sama dengan temuan #4 kalian, cuma di sisi yang tidak
kalian periksa. Asimetrinya yang jadi temuan: layar Finance dapat kata-kata
kontekstual, pengaju tidak.

**Usul:** `tolak()` sudah tahu status asal (`$rop->status` sebelum `pindah()`).
Pakai itu untuk memilih judul/badan — mis. *"Persetujuan dibatalkan"* +
*"… persetujuannya ditarik. Alasan: … Silakan ajukan ulang."* Tambahkan uji
penjaga; `test_3` sekarang memastikan pengaju **diberi tahu**, belum memastikan
**diberi tahu apa**.

---

## Jawaban atas pertanyaan terbuka kalian

> *"Drawer tidak memberi tahu bahwa item yang mau dibatalkan ada di lembar bayar
> yang sudah dikirim… di luar yang handoff minta, jadi saya serahkan keputusannya."*

**Kerjakan.** Alasan, dan satu koreksi atas dasar penolakannya:

1. **Ini bukan fitur baru — ini temuan #1 kalian sendiri, satu langkah lebih
   awal.** Temuan #1 melindungi *pembaca* lembar yang dicetak ulang. Ini
   melindungi *pengambil keputusan*. Cacatnya satu: pembatalan punya akibat di
   luar layar yang sedang dilihat orangnya.

2. **Kasus 16 Sep persis begitu**, dan bukan karangan: ketiga pengajuan itu duduk
   di `BAYAR/2609/001`, lembar yang sudah dikirim 3 Sep 18:16 dan 15 dari 18
   itemnya sudah dibayar. Tidak ada satu pun tanda di layar yang mengatakannya.

3. **Ongkosnya lebih murah dari dugaan kalian.** `show()` sudah menjalankan ~6
   eager-load plus `untukCostCentre`, `bahanAkun`, `riwayatVendor`, dan `logs()`.
   Satu kueri lagi tidak terasa — dan drawer dibuka **satu item pada satu waktu**,
   bukan di daftar. Relasinya sudah ada dari sisi item:
   `RopPaymentSheetItem::where('rop_id', $id)->with('sheet')->first()`.

Cukup satu baris keadaan, jangan dibesarkan: nomor lembar, tanggal kirim, dan
berapa item di lembar itu yang sudah `dibayar`. Jangan dijadikan pagar yang
menolak — Finance tetap boleh membatalkan; yang kurang cuma informasinya.

---

## ⚠️ Catatan deploy yang belum kalian sebut

Laporan kalian menulis **"nol SQL"** — benar. Tapi ada satu hal lagi:

```
M public/assets/css/rop.sweettreats.css   (+22 baris)
```

⛔ **Deploy prod yang menyentuh `public/assets/**` WAJIB diikuti `rsync` yang
DIBATASI ke `assets/` saja.** `public_html` prod itu SALINAN, bukan symlink.
Rsync seluruh `public/` pernah **mematikan prod 6 menit** (`index.php` prod
sengaja beda).

⛔ Dan verifikasinya **bukan** kode 200 + ukuran — aset prod di-cache
`max-age=604800` (7 hari):

```bash
md5sum public_html/assets/css/rop.sweettreats.css
curl -s "https://app.sweettreats.id/assets/css/rop.sweettreats.css?v=$(date +%s)" | md5sum
```

Tanpa rsync, penanda versi maju sementara isinya lama — lalu terkunci 7 hari di
bawah URL yang terlihat segar. Tanda `DIBATALKAN` dan coretannya **ada di CSS
itu**, jadi kalau rsync terlewat, cetakan prod kehilangan persis pengaman yang
temuan #1 kalian bangun.

---

## Yang HUB minta

1. Perbaiki temuan notifikasi + uji penjaganya.
2. Kerjakan pertanyaan terbuka itu (satu baris keadaan, bukan pagar).
3. Masukkan catatan rsync ke entri log-update/status-proyek kalian.
4. Commit — semuanya masih unstaged, dan folder worktree dipakai bersama.
