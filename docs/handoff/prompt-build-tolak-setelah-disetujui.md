# Tombol Tolak di tahap `disetujui` — pintu yang hilang

> Untuk chat build. Bilang saja: **"baca `docs/handoff/prompt-build-tolak-setelah-disetujui.md`"**.
> Disusun chat HUB 16 Sep 2026, diverifikasi terhadap PROD. Dasar: `origin/main` = `23fb5b1` (= prod).

---

## Kenapa ini ada — kejadian nyata 16 Sep

Della (Finance) minta tolong lewat Ekky: **batalkan 3 antrian bayar**. VA-nya sudah
kedaluwarsa sebelum sempat ditransfer.

| id | nomor | nominal |
|---|---|---|
| 77 | `ROP/SUN/2609/004` | Rp 406.240 |
| 78 | `ROP/BIN/2609/007` | Rp 195.050 |
| 79 | `ROP/BIN/2609/008` | Rp 219.000 |

Ketiganya `disetujui` 3 Sep 18:16 oleh Della sendiri, lewat lembar `BAYAR/2609/001`.
**Tertahan 13 hari.** Yang terjadi waktu HUB memeriksa:

- `batal` → tidak bisa. `RopRequest::bisaDibatalkan()` hanya `diajukan`.
- `urungkan` → tidak bisa. `RopService::DETIK_URUNG = 120`. Jendelanya lewat 13 hari lalu.
- `tolak` → **boleh**. `TRANSISI[STATUS_DISETUJUI] = [STATUS_DIBAYAR, STATUS_DITOLAK]`.
  Rute ada (`POST rop/{id}/tolak`), controller ada, service ada, notifikasi ada.

**Tapi tombolnya tidak ada di layar mana pun.** Mesinnya lengkap, pintunya tidak dibuat.

HUB sempat salah dan bilang ke Ekky "Della bisa melakukannya sendiri" — Ekky balas
dengan tangkapan layar: *"ngga daa tombol tolaknya?"*. Betul, memang tidak ada.

Akhirnya HUB menjalankan `RopService::tolak()` **langsung di prod** dengan aktor =
Della dan alasan dari Della. Berhasil, jejak auditnya bersih. Tapi itu bukan cara
yang benar — dan **ini pasti terulang**.

---

## Yang sudah ada (jangan dibangun ulang)

Hampir semuanya sudah jadi. Periksa sendiri sebelum menulis kode baru:

| bagian | berkas | keadaan |
|---|---|---|
| rute | `routes/web.php:503` `finance.rop.tolak` | ✅ ada, tanpa pagar status |
| controller | `RopQueueController::tolak()` | ✅ ada, validasi `alasan` wajib ≤500 |
| service | `RopService::tolak()` → `pindah()` → `catat()` → `AdminNotification::notify('rop_ditolak')` | ✅ ada |
| transisi | `TRANSISI[disetujui] = [dibayar, ditolak]` | ✅ **sudah mengizinkan** |
| form alasan | `#ropDTolakBlok` + `#ropTolakForm` + `#ropDAlasan` (blade baris 322–340) | ✅ ada, di luar footer |
| action form | baris 548: `ropTolakForm.action = basis + '/' + id + '/tolak'` | ✅ **sudah dipasang untuk SEMUA status** |
| ajukan ulang | `bisaDiajukanUlang()` = `[ditolak, kadaluarsa]`, rantai `resubmit_of` | ✅ ada |

Yang **tidak** ada cuma satu: tombol pemicunya. `#ropBukaTolak` duduk di dalam
`#ropFootAcc` (baris 352), dan `#ropFootAcc` disembunyikan saat status `disetujui`:

```js
// resources/views/finance/rop/index.blade.php:552-556
var bisaAcc   = d.status === 'diajukan';
var bisaBayar = d.status === 'disetujui';
document.getElementById('ropFootAcc').style.display   = bisaAcc ? '' : 'none';
document.getElementById('ropFootBayar').style.display = bisaBayar ? '' : 'none';
```

`#ropFootBayar` (baris 360) cuma berisi **Tandai dibayar**. Itu lubangnya.

---

## Yang HUB minta dibangun

**Munculkan Tolak di cabang `disetujui`.** Kecil, tapi ada empat keputusan yang
jangan diputuskan sambil lalu:

### 1. Kata-katanya beda dari tahap `diajukan`

Di `diajukan`, "Tolak" berarti *belum pernah disetujui*. Di `disetujui`, uangnya
**sudah masuk lembar bayar dan mungkin sudah dikirim ke bank**. Menolak di sini
artinya **membatalkan persetujuan yang sudah keluar** — bobotnya lain.

Usul HUB: label **"Batalkan persetujuan"** (bukan "Tolak" polos), dan hint di
bawahnya yang menyebut akibatnya — pengaju diberi tahu, dan dia harus mengajukan
ulang. Ini pola P3 di `docs/spec/pola-interaksi.md` (tombol yang bilang apa yang
akan terjadi). Kalau kalian punya kata yang lebih baik, pakai — yang penting
jangan dua tombol berlabel sama dengan arti berbeda.

### 2. Tombolnya harus lebih sulit dipencet daripada di `diajukan`

Di `diajukan` Tolak itu `rop-btn--ghost`. Di `disetujui`, jangan sejajar visual
dengan **Tandai dibayar** — Finance sedang buru-buru menutup lembar, dan dua
tombol bersebelahan yang sama menonjol adalah undangan salah pencet. Taruh
sebagai aksi sekunder (lebih kecil / di bawah / terpisah garis), bukan
berdampingan seukuran.

Alasan wajib diisi sudah jadi pagar kedua — `alasan.required` ada di controller.
Jangan dilepas.

### 3. Siapa yang boleh

`RopQueueController::guard()` sudah membatasi ke Finance. **Jangan tambah pagar
"hanya penyetuju aslinya"** seperti `urungkanPersetujuan()` (yang `abort(403)`
kalau `reviewed_by !== $actorId`). Alasannya: pembatalan seperti ini datang
belakangan, kadang berhari-hari, dan orang yang menyetujui bisa sedang cuti.
Yang penting **tercatat siapa yang membatalkan** — `rop_status_log.actor_id`
sudah melakukannya.

### 4. Item yang ditolak tetap duduk di lembar bayar

Ini sudah aman, tapi ketahui supaya tidak "diperbaiki" jadi rusak:

- `rop_payment_sheets.total` itu **kolom beku**, dihitung saat lembar dibuat.
  Setelah 3 item ditolak, `BAYAR/2609/001` masih tertulis Rp 17.103.835 padahal
  yang benar-benar dibayar Rp 16.283.545. **Biarkan.** Lembar cetak itu dokumen
  sejarah — yang sudah dikirim ke bank tidak boleh berubah angkanya di belakang.
- `RopLembarBayarController::bayarLembar()` sudah memeriksa per item
  (`status !== disetujui` → pesan galat yang menyebut nomornya). Jadi menolak
  satu item di lembar yang belum lunas **tidak merusak** penandaan item lain.

Yang **boleh** ditambahkan kalau kalian mau: penanda di layar lembar bahwa
sekian item sudah ditolak, supaya selisih total tidak terbaca sebagai kesalahan
hitung. Opsional, bukan syarat.

---

## Uji yang HUB minta

1. `disetujui` → `ditolak` lewat layar, oleh Finance yang **bukan** penyetuju asli → berhasil.
2. Alasan kosong → ditolak dengan pesan yang ada sekarang.
3. Setelah ditolak: `bisaDiajukanUlang()` = `true`, notifikasi `rop_ditolak`
   sampai ke pengaju, `rop_status_log` mencatat `disetujui → ditolak` dengan
   actor yang benar.
4. `dibayar` / `selesai` → tombolnya **tidak** muncul (`TRANSISI` tidak
   mengizinkan; jangan sampai tombol muncul lalu `abort(409)` — tombol yang
   gagal lebih buruk daripada tombol yang tidak ada).
5. Lembar bayar dengan 1 item ditolak: menandai item **lain** tetap jalan.

---

## Keadaan prod sesudah eksekusi HUB (patokan, jangan diubah)

```
rop_status_log  77/78/79 : disetujui -> ditolak, Della Puspita Sari, 2026-09-16 09:48:32
                catatan   : "VA sudah kedaluwarsa sebelum sempat dibayar.
                             Silakan ajukan ulang dengan nomor VA yang baru."
admin_notification       : 3 baris [rop_ditolak] ke Anisa Aulia, belum dibaca
antrian bayar (disetujui): 0 pengajuan
BAYAR/2609/001           : 15 dibayar (Rp 16.283.545) + 3 ditolak (Rp 820.290)
bisaDiajukanUlang()      : YA untuk ketiganya
```

---

## Pola yang lebih besar dari tiket ini

Ini **kali ketiga** dalam satu minggu HUB menemukan bentuk yang sama:

1. `omnicare:reconcile` menyelesaikan 98,6% tapi **nol manusia pernah membuka
   layarnya** — 1.061 no-show + 108 konflik mengantre sejak Juni.
2. Tautan pasien yang ambigu ditandai "untuk ditinjau manual" — **layar
   peninjauannya tidak pernah dibuat**.
3. Ini: mesin negara mengizinkan `disetujui → ditolak`, **tombolnya tidak ada**.

Polanya: **sistem bisa melakukannya, manusia tak punya pintu untuk memintanya.**
Yang muncul ke permukaan bukan sebagai bug — tapi sebagai orang yang menunggu
13 hari lalu menanyakannya lewat WhatsApp.

Kalau kalian menambah transisi status di mana pun setelah ini, pertanyaan
wajibnya satu: **siapa yang memencetnya, dan dari layar mana.**
