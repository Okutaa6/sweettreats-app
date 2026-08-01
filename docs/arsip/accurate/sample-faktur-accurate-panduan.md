# Sample Faktur Accurate — Bintaro April 2026 (auto-generated)

**Untuk:** Tim Finance
**File:** `sample-faktur-accurate-bintaro-april-2026.csv`

## Apa ini

Ini **contoh hasil olahan otomatis** dari Data Center — mengambil data April 2026 cabang
Bintaro dari Omnicare, lalu membentuknya menjadi **format faktur Accurate** (seperti yang
selama ini kalian susun manual). **1 bulan penuh = 25 faktur harian.**

Tujuannya: kalian cek apakah outputnya **sudah benar & sesuai** sebelum kami nyalakan push
otomatis.

## Bukti angkanya sudah cocok (sudah kami validasi)

| Item | Hasil otomatis | Data Accurate April kalian | Cocok? |
|---|--:|--:|:--:|
| **Pendapatan per akun** (12 akun) | 353.261.000 | 353.261.000 | ✅ persis, tiap akun |
| **Fee dokter** | 133.823.989 | 133.823.989 | ✅ persis |
| **Rekap pembayaran per metode** | (8 metode) | Summary Penerimaan | ✅ persis |

Jadi angkanya bukan kira-kira — sudah kami cocokkan sampai rupiah dengan data kalian.

## Struktur file (3 seksi per faktur harian)

- **HEADER** — 1 baris/hari: No Faktur `PLJ{yymmdd}`, tanggal, customer `C.00001` (Umum).
- **ITEM** — pendapatan: tiap layanan → akun pendapatan (hasil mapping yang kalian setujui),
  nama = "Layanan, ID Pasien, Nama Pasien", + ID dokter (Seller).
- **EXPENSE** — fee dokter → `210205 Hutang Fee Dokter`.
- **EXPENSE(bayar)** — alokasi pembayaran: Debit/Transfer/QRIS → `110314 Piutang Non Tunai`,
  Tunai → `110315 Piutang Usaha Cash`, Voucher Tunai → `210110 Uang Muka Penjualan`,
  Endorsement → `600201 Biaya Promosi`.

## Yang mohon Finance cek

1. **Akun di seksi ITEM sudah benar?** (ini inti — pemetaan yang kalian setujui)
2. **Struktur HEADER/EXPENSE** sudah sesuai cara kalian? (No Faktur, customer, fee dokter)
3. **Baris bertanda "(perlu Finance)"** — pembayaran **Poin Hadiah / Voucher 25K-100K** kami
   belum tahu akun yang tepat (ini sisi pembayaran, di luar mapping pendapatan kemarin).
   Mohon tentukan akunnya.

## Catatan

- Refund/kelebihan bayar sudah **dikeluarkan** dari faktur (sesuai arahan = retur, bukan
  penjualan).
- Ini baru **1 cabang (Bintaro) 1 bulan (April)** sebagai contoh. Setelah kalian OK,
  cabang & bulan lain mengikuti pola yang sama, otomatis.
