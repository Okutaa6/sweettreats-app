# Panduan Mapping Akun — Omnicare → Accurate

**Untuk:** Tim Finance
**File kerja:** `mapping-akun-accurate.csv` (buka di Excel)

## Konteks singkat

Kita sedang mengotomasi alur pencatatan penjualan:

```
Omnicare (sumber)  →  Data Center (olah)  →  Accurate (otomatis)
```

Data penjualan/pembayaran/komisi ditarik dari Omnicare, diolah di Data Center menjadi
format impor Accurate, lalu dikirim otomatis — **menggantikan input manual**.

Agar tiap layanan masuk ke **akun pendapatan Accurate yang benar**, kita perlu satu
**tabel pemetaan** (layanan → akun). Angka datanya sudah kami validasi cocok dengan
laporan Finance (revenue, pembayaran, dan komisi — semuanya persis). Yang tersisa dan
**butuh keputusan Finance** hanyalah pemetaan akun ini, karena itu ranah akuntansi.

## Cara kerja pemetaan

Setiap layanan di Omnicare punya kode (mis. `26CB068`, `26PO226`). Huruf depan kode
(`CB`, `PO`, dst) menentukan jenis layanan → dipetakan ke satu akun pendapatan Accurate.
Worksheet ini mengelompokkan **semua layanan per prefix**, jadi Finance cukup menilai
per-kelompok, bukan per-item satu-satu.

## Cara kami memvalidasi (penting)

Selain menebak dari kemiripan, kami **mencocokkan langsung ke data faktur Accurate yang
sudah ada** (bulan April 2026, semua cabang) — yaitu akun yang **benar-benar dipakai
Finance**. Ini ada di kolom **Akun REAL Accurate**. Hasilnya: usulan kami **cocok dengan
akun asli Accurate pada ~98,5% revenue**. Jadi worksheet ini bukan sekadar tebakan.

## Yang perlu Finance lakukan

Buka `mapping-akun-accurate.csv`, lihat kolom **Status**:

| Status | Artinya | Aksi Finance |
|---|---|---|
| ✅ **TERVERIFIKASI ACCURATE** | Usulan kami = akun asli di faktur Accurate | Cukup lihat sekilas (sudah terbukti benar) |
| ➕ **DARI ACCURATE (isi ini)** | Akun diambil dari data asli Accurate | **Salin** ke kolom *AKUN FINAL* / konfirmasi |
| ⚠ **PERIKSA — beda dgn Accurate** | Usulan kami beda dengan Accurate | **Tentukan** yang benar |
| 🔒 **dari file Finance** / 💡 **USULAN** | Belum muncul di Accurate April; pakai file/kemiripan | **Konfirmasi/koreksi** |
| ✍ **PERLU DIISI FINANCE** | Belum ada acuan (produk retail kecil) | **Isi** akun yang tepat |

**Kolom yang diisi Finance:** `AKUN FINAL (isi Finance)` (nomor akun Accurate) dan
`Catatan` bila perlu.

### Catatan khusus — item PROMO
Item promo (mis. "Scaling Kamis & Jumat", "Selfcare - Whitening") di Accurate ternyata
**tidak selalu** masuk akun *Promo (400102)* — beberapa dibukukan ke **akun layanan aslinya**
(promo scaling → Scaling; promo whitening → Bleaching). Mohon Finance tegaskan aturannya:
promo dibukukan ke akun *Promo* atau ke akun *layanan dasarnya*? (Baris dengan catatan
"Campur akun di Accurate" perlu perhatian khusus.)

## Cakupan saat ini (cabang Bintaro, seluruh riwayat)

- ✅ **TERKUNCI + USULAN sudah menutup 98,2% revenue** — Finance tinggal verifikasi/konfirmasi.
- ⚠️ **PERLU DIISI hanya ~1,8% revenue**, mayoritas produk retail kecil (sikat gigi, pasta,
  orthokit) dan beberapa layanan (root canal varian, perio). Silakan tentukan akunnya.

## Catatan penting

- Baris **"(TANPA KODE) P"** = item promo yang namanya berupa tanggal (mis. "MARET - Metal
  Braces") tanpa kode — kemungkinan masuk **Promo (400102)**, mohon konfirmasi cara
  penanganannya (aturan berbasis nama).
- **Voucher Tunai** dan **Service Charge** diperlakukan sesuai file Finance (Service Charge →
  400305; Voucher Tunai = alat bayar / akun uang muka, ditangani di sisi pembayaran).
- Setelah worksheet ini diisi & disetujui, kami kunci sebagai tabel pemetaan resmi di Data
  Center, lalu buat **sample faktur Accurate 1 bulan** untuk Finance cek akhir sebelum
  push otomatis dinyalakan.

## Setelah ini

1. Finance isi/konfirmasi worksheet → kirim balik.
2. Kami encode jadi tabel mapping di Data Center.
3. Kami generate **sample faktur Accurate (1 bulan, Bintaro)** — HEADER + ITEM (revenue,
   akun sudah benar) + EXPENSE (fee dokter) + rekap pembayaran.
4. Finance verifikasi sample → bila cocok, push otomatis dinyalakan (mulai 1 cabang).
