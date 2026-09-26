# Handoff — Berkas yatim di `storage/app/att_request` (susulan gerbong foto wajib)

> Untuk **chat build** yang sedang memegang `feat/foto-wajib-lembur-wfh`.
> Temuan audit HUB 26 September 2026. **Kecil, tapi murah — dan kalian sedang
> memegang berkasnya.** Naik prod menunggu ini (keputusan Ikhsan).

---

## 1. Temuannya

`$file->move()` ada **DI LUAR dan SEBELUM** `DB::transaction` (baris ~428).
Di dalam transaksi ada **tiga** jalan gagal:

| # | Pemicu | Punya pra-cek di luar transaksi? |
|---|---|---|
| 1 | `pastikanJatahWfhCukup()` — jatah WFH habis | ⛔ **TIDAK** |
| 2 | `pastikanLemburTakKembar()` — kembar (cek ke-2, konkurensi) | ✅ ya, baris 345 |
| 3 | `AttendanceRequestWorkflow::submit()` — mis. alur persetujuan belum diatur | ⛔ tidak (memang tak bisa) |

Ketiganya me-rollback baris DB. **Tak satu pun menghapus berkasnya.** `unlink`
tidak ada sama sekali di controller itu — sudah dicek.

**Kenapa ini baru penting sekarang.** Sebelum gerbang foto, WFH melampirkan foto
1 dari 7 kali, lembur 43%. Sesudahnya **setiap** lembur & WFH membawa berkas.
Jalur yang dulu sesekali meninggalkan yatim, sekarang melakukannya **100%**.

**Dan ada separuh lagi yang lebih terasa buat orangnya:** karena jatah WFH
satu-satunya yang tanpa pra-cek, orang yang mencoba hari WFH ke-3 dalam sebulan
harus **melampirkan foto dulu**, baru diberi tahu jatahnya habis. Itu persis
cacat "dua kali kerja" yang kalian sudah hindari untuk kembar — alasannya kalian
tulis sendiri di komentar baris 353–365. Jatah WFH tak pernah kebagian.

⚠️ Besarnya jujur saja: **kecil.** WFH cuma 7 pengajuan sepanjang masa, jatahnya
2/bulan. Bukan lubang keamanan, bukan kehilangan data — rembesan penyimpanan
pelan di folder yang bisa berisi surat dokter, plus satu langkah mubazir.

---

## 2. Perbaikan — dua bagian, obatnya sudah ada di berkas yang sama

### a. Pra-cek jatah WFH (menutup #1 + cacat UX-nya)

`hariWfhTerpakai()` **sudah** punya parameter `bool $kunci = false` (baris 698)
dan hanya memanggil `lockForUpdate()` kalau `true`. Yang mengunci mati cuma
`pastikanJatahWfhCukup()`. Jadi:

1. Tambah parameter di `pastikanJatahWfhCukup(string $userId, string $dari, string $sampai, bool $kunci = true)`
   dan teruskan ke `hariWfhTerpakai($userId, $awal, $akhir, $kunci)`.
2. Panggil versi **tanpa kunci** sebagai pra-cek — **sesudah gerbang foto**,
   **sebelum** `$file->move()`:

```php
// Jatah WFH dicek DUA KALI, pola yang sama dengan lembur kembar di atas:
// di sini tanpa kunci supaya pesannya jelas dan berkas foto tak terlanjur
// tersimpan sia-sia, lalu sekali lagi DI DALAM transaksi dengan lockForUpdate
// supaya dua kiriman berbarengan tak sama-sama lolos.
if ($data['tipe'] === 'wfh') {
    try {
        $this->pastikanJatahWfhCukup($userId, $data['tanggal_from'], $data['tanggal_to'], false);
    } catch (\RuntimeException $e) {
        return back()->withInput()->with('error', $e->getMessage());
    }
}
```

⛔ **Jangan menyalin kata-kata pesannya.** Pakai `$e->getMessage()` supaya
kalimat "Jatah kerja dari rumah … sisa N hari" tetap hidup di SATU tempat.

⚠️ **Urutan: sesudah gerbang foto, bukan sebelum.** Alasannya kebalikan dari
kasus kembar: kembar berarti pengajuannya memang tak boleh ada sama sekali,
sedangkan jatah habis itu keadaan yang orangnya mungkin ingin tahu justru
sesudah yakin pengajuannya sudah lengkap. Kalau kalian tak setuju, katakan —
ini pendapat, bukan temuan.

### b. Bersihkan berkas di jalur gagal (menutup #2 dan #3)

`$dir` sekarang hidup di dalam blok `if ($request->hasFile('foto'))`. Angkat ke
luar supaya bisa dipakai di `catch`:

```php
} catch (\Throwable $e) {
    // Berkas sudah terlanjur dipindah SEBELUM transaksi, dan berkas tidak ikut
    // rollback. Tanpa ini tiap penolakan di dalam transaksi meninggalkan yatim
    // di gudang lampiran — dan sejak foto WAJIB, tiap kiriman membawa berkas.
    if ($fotoName) { @unlink($dir . '/' . $fotoName); }
    if ($e instanceof \RuntimeException) {
        return back()->withInput()->with('error', $e->getMessage());
    }
    throw $e;                      // galat lain tetap naik seperti sebelumnya
}
```

⚠️ `catch` sekarang `\Throwable`, tapi perilakunya **tidak berubah**:
`RuntimeException` tetap jadi pesan ramah, sisanya tetap dilempar ulang. Yang
bertambah cuma pembersihannya. Kalau ini terasa terlalu lebar, alternatifnya
dua `catch` terpisah — silakan pilih, asal galat non-RuntimeException tidak
diam-diam tertelan.

⛔ **Hanya `@unlink` berkas yang dipindah request INI** (`$fotoName` lokal).
Jangan sekali-kali menyapu folder berdasarkan pola atau umur.

---

## 3. Uji yang diharapkan

Perluas `tests/Feature/PagarLemburLayarTest.php` (tempat uji foto kalian).

1. WFH melebihi jatah + foto dilampirkan → **ditolak**, pesan menyebut jatah
2. ⭐ dan **tidak ada berkas tertinggal** — hitung isi `storage/app/att_request`
   sebelum & sesudah, harus sama
3. ⭐ **kendali positif:** kiriman WFH yang BERHASIL → berkasnya **ADA**
   (pembersihan tidak boleh kebablasan menghapus yang sah)
4. WFH dalam jatah + foto → diterima seperti biasa
5. ⭐ pra-cek tidak menggeser gerbang foto: WFH melebihi jatah **tanpa** foto →
   yang muncul pesan **FOTO**, bukan pesan jatah

Kendali negatifnya: cabut pra-cek → uji 2 merah; cabut `@unlink` → uji 2 merah;
pasang `@unlink` di jalur sukses → uji 3 merah. Verifikasi tiap sabotase benar
kena sasaran sebelum percaya hasilnya — seperti yang kalian lakukan kemarin.

---

## 4. Yang JANGAN disentuh

- ⛔ Cek jatah **di dalam transaksi tetap dengan `lockForUpdate`**. Pra-cek itu
  tambahan, **bukan pengganti** — persis seperti pagar kembar. Menghapus yang
  di dalam transaksi membuka kembali celah dua kiriman berbarengan.
- ⛔ Gerbang foto dan penempatannya sudah benar. Jangan digeser.
- ⛔ Jangan bikin perintah penyapu berkas yatim. Yang lama tidak perlu disapu —
  diukur di prod 26 Sep: **0 berkas yatim**, 239 lampiran semuanya bisa dibuka.
- ⛔ `berkas()` memeriksa **DUA** jalur: `storage/app/att_request/` dan
  `public_path('uploads/att_request/')` (di prod = `public_html/uploads/...`,
  isinya 11 berkas lama). Kalau menghitung berkas, hitung dua-duanya — HUB
  sempat salah lapor "11 hilang" gara-gara cuma melihat satu.

---

## 5. Catatan deploy

Nol SQL · tanpa rsync · base tetap `5b3483d` = main. Gabung ke gerbong foto yang
sama, **satu deploy**, jangan dua. Naik prod menunggu ini — keputusan Ikhsan,
dan aman karena pengumuman WhatsApp ke karyawan **belum dikirim**, jadi tak ada
tanggal yang mengikat.
