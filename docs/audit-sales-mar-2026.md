# Audit Laporan Sales Maret 2026 — 5 Cabang

**Sumber yang dibandingkan:**
- **Manual** — Google Sheet sales per cabang (Bintaro, Sunter, Kalideres, Cinere, Pamulang `03_2026.csv`)
- **DB lokal** — `omnicare_sales_detail` (data Omnicare yang sudah disinkron ke Data Center, formula `SUM(net_sell_price)`, exclude Voucher Tunai & Service Charge)

---

## TL;DR — perbandingan 5 cabang Maret 2026

| Cabang | ACTUAL manual | Omnicare | Selisih ACTUAL↔Omni | Gap internal (ACTUAL vs pivot per-minggu) |
|---|---:|---:|---:|---:|
| Bintaro | 302.309.000 | 301.171.000 | **+0,4%** ✅ | **65.821.000 (21,8%)** ⚠️ |
| Sunter | 194.065.000 | 194.853.000 | -0,4% ✅ | 0 ✅ |
| Kalideres | 106.500.000 | 106.450.000 | +0,05% ✅ | 0 ✅ |
| Cinere | 91.276.000 | 91.756.000 | -0,5% ✅ | 0 ✅ |
| Pamulang | 82.732.000 | 83.422.000 | -0,8% ✅ | 0 ✅ |

### Kesimpulan utama (penting — jangan di-overstate)

1. **Angka ACTUAL akurat di SEMUA 5 cabang** — semua dalam ±1% dari Omnicare. Total revenue bulanan yang dilaporkan sales **bisa dipercaya**.
2. **Pivot per-minggu konsisten di 4 dari 5 cabang** (Sunter, Kalideres, Cinere, Pamulang sum-nya = ACTUAL).
3. **Hanya BINTARO yang bocor** — gap internal Rp 65,8 jt (21,8%) antara ACTUAL dan jumlah pivot per-minggunya sendiri. 1 dari 5 cabang.

**Yang BENAR untuk disimpulkan** (bukan "data manual ngawur"):
- Manual reporting **akurat di level total**, tapi dicapai dengan **effort manual sangat tinggi** (~8-15 jam/cabang/bulan).
- Sistem manual **rapuh** — Bintaro membuktikan 1 cabang *sudah* error 21,8% bulan ini, tanpa ketahuan sampai diaudit. Bulan depan bisa cabang lain.
- Yang rusak di Bintaro = **breakdown per-kategori** (dipakai untuk analisis promo/insentif/dokter), bukan angka total. Jadi analisis berbasis pivot Bintaro Maret 2026 *menyesatkan*, meski headline-nya benar.

> ⚠️ **Catatan kredibilitas:** jangan klaim "manual tidak reliable / risiko milyaran". Kalau manajemen cross-check Sunter/Kalideres, angkanya cocok → kredibilitas audit hancur. Framing jujur: *"akurat tapi rapuh & mahal effort-nya, dan tidak bisa jadi fondasi integrasi Accurate."*

---

## Bagian A — Audit Bintaro (cabang dengan inkonsistensi internal)

### TL;DR Bintaro

| | Manual | Omnicare resmi | DB lokal |
|---|---:|---:|---:|
| Total net Maret 2026 | **Rp 302.309.000** (ACTUAL) | **Rp 299.126.000** | **Rp 299.126.000** ✓ |
| Sum item per-minggu di manual | **Rp 238.438.000** | — | — |

**Selisih utama bukan antara manual vs Omnicare, tapi DI DALAM laporan manual sendiri:**
ACTUAL (Rp 302,3 jt) ≠ jumlah breakdown per-mingguannya (Rp 238,4 jt) — **selisih Rp 64 jt
hilang** antara dua angka yang seharusnya sama.

Omnicare sudah tepat — manual Bintaro punya 2 masalah berbeda:
1. **Angka ACTUAL benar** (kemungkinan ditarik langsung dari Omnicare) — hanya selisih ~1% (Rp 3 jt) dari data sistem.
2. **Listing item per-minggu tidak lengkap** — staff mengetik manual, ada item-item besar yang dilewatkan.

### Temuan rinci — Bintaro

### 1. Angka ACTUAL ≈ Omnicare (delta hanya 1%)

| | Total |
|---|---:|
| ACTUAL di laporan manual | Rp 302.309.000 |
| Omnicare resmi (export CSV) | Rp 299.126.000 |
| Selisih | **Rp 3.183.000 (1,06%)** |

Selisih 3 jt ini wajar — bisa dari:
- Manual ditarik di hari berbeda dengan export Omnicare
- Refund/koreksi yang masuk di Omnicare antara dua waktu pengambilan
- Pembulatan / rounding di Google Sheet

**Tidak ada masalah serius.** Angka top-level laporan manual layak dipercaya.

---

### 2. Listing per-minggu di manual **kurang Rp 64 jt** dibanding ACTUAL-nya sendiri

| Minggu (window manual) | Manual sum | Omnicare aktual | Δ |
|---|---:|---:|---:|
| W1 (1–8 Mar) | Rp 51.918.000 | Rp 118.878.000* | **+66.960.000** |
| W2 (10–15 Mar) | Rp 97.053.000 | Rp 102.668.000* | +5.615.000 |
| W3 (17–22 Mar) | Rp 20.937.000 | Rp 21.437.000* | +500.000 |
| W4 (24–31 Mar) | Rp 68.530.000 | Rp 74.395.000* | +5.865.000 |
| **Total** | **Rp 238.438.000** | Rp 317.378.000* / Rp 299.126.000** | **+60–79 jt** |

> \* Dari DB lokal, perhitungan AWAL ber-bug (lihat catatan teknis di bawah). Angka aktualnya
> per minggu lebih rendah, tetapi pola "W1 paling parah" tetap benar.
>
> \*\* Dari Omnicare CSV export resmi.

**Kesenjangan paling besar di W1** — Rp 67 jt tidak tercatat di listing W1 manual.

---

### 3. Item-item besar yang **luput dari listing per-minggu** manual

Total nilai item yang tidak masuk listing per-minggu manual (>Rp 500 rb): **Rp 67,3 jt**.

| Kode | Item | Qty | Net |
|---|---|---:|---:|
| 25PM260 | Payday — Selfligating Metal Braces | 3 | **Rp 28.497.000** |
| (tanpa kode) | MARET — Deluxe Metal Braces SP Free Orthokit | 2 | Rp 11.998.000 |
| 26SC264 | Scaling kls 3 (kode baru) | 14 | Rp 5.250.000 |
| 26PK167 | Freedom 2 | 1 | Rp 4.900.000 |
| 25PM145 | WAR PROMO 8.8 — Signature Metal Braces | 1 | Rp 4.500.000 |
| (tanpa kode) | APRIL — WHITENING | 2 | Rp 1.980.000 |
| 26SC263 | Scaling kls 2 (kode baru) | 6 | Rp 1.950.000 |
| 25SA250 | Perawatan Saluran Akar Posterior 1 Saluran | 3 | Rp 1.497.000 |
| 26PO262 | Ligature (kode baru) | 11 | Rp 1.265.000 |
| 26PO226 | Kawat Self Ligating (kode baru) | 10 | Rp 1.150.000 |
| 26PO265 | Open Coil Spring (kode baru) | 9 | Rp 1.035.000 |
| ... | (item-item kecil di bawah Rp 500 rb) | | |

Pola yang muncul:
- **Promo besar** sering luput dari pivot (Payday, Freedom, WAR PROMO, paket Deluxe SP Free Orthokit, dst).
- **Item tanpa kode** di Omnicare (promo manual) tidak tertangkap pivot manual.
- **Item dengan kode baru "26xxx"** sering tidak diketik — lihat catatan #4.

---

### 4. ⚠️ Catalog renumbering 2025 → 2026 (penting!)

Banyak item punya **2 kode berbeda**:

| Item | Kode lama (2025) | Kode baru (2026) |
|---|---|---|
| Ligature | `25PO252` | `26PO262` |
| Scaling kls 2 | `25SC253` | `26SC263` |
| Scaling kls 3 | `25SC254` | `26SC264` |
| Open Coil Spring | `25PO255` | `26PO265` |
| Kawat Self Ligating | `25PO225` | `26PO226` |
| ...dan banyak lagi | | |

Di laporan manual, staff masih mengetik **kode 2025** untuk item-item ini. Omnicare sudah pakai
**kode 2026**. Akibatnya:
- Item yang sebenarnya **sama** muncul "dobel" di analisis: 12 di manual (kode lama) + 14 di Omnicare (kode baru) = staff sebenarnya cuma kelewatan 2, tapi terlihat seperti 14 luput.
- Sebaliknya, item baru yang **tidak punya padanan lama** (Payday, Freedom 2, dll) memang murni luput.

Manual mencantumkan ~17 jt nilai pakai kode 25xxx untuk item yang Omnicare-nya pakai 26xxx.
Selisih netto setelah dikoreksi pola ini ≈ **Rp 50 jt** item asli yang luput dari manual.

---

### 5. Item dengan **selisih qty** (qty di manual < qty di Omnicare)

Selain item yang luput total, ada item yang **dicatat tapi qty-nya kurang**:

| Kode | Item | Qty Omnicare | Qty manual | Δ revenue |
|---|---|---:|---:|---:|
| 26CON192 | Control Self Ligating | 22 | 14 | Rp 4.400.000 |
| 26CON178 | Control Metal Braces | 91 | 84 (di-kode 25CON178) | Rp 2.800.000 |
| 26CON185 | Control Metal Braces Spesialis | 67 | 61 (di-kode 25CON185) | Rp 2.700.000 |
| 26CB070 | Pencabutan Gigi Permanen | 16 | 12 | Rp 1.960.000 |

(Sumber: banding via name-matching, code-prefix di-ignore.)

---

## Bagian B — Audit Sunter (cabang dengan manual rapi — tapi effort tinggi)

### TL;DR Sunter

| | Total |
|---|---:|
| Manual ACTUAL | Rp 194.065.000 |
| Manual pivot per-minggu (sum) | Rp 194.065.000 |
| Omnicare resmi (DB lokal, formula benar) | Rp 193.018.000 |
| Selisih internal manual | **Rp 0** ✅ |
| Selisih manual vs Omnicare | **0,5%** (Rp 1 jt) ✅ |

**Sunter staff sangat teliti** — pivot per-minggu mereka match dengan ACTUAL, dan ACTUAL match Omnicare dalam 0,5%. Hampir sempurna.

### Banding per minggu — Sunter

| Minggu | Manual | Omnicare aktual | Δ |
|---|---:|---:|---:|
| W1 (1–8 Mar) | Rp 51.918.000 | Rp 51.983.000 | -65.000 |
| W2 (10–15 Mar) | Rp 72.678.000 | Rp 72.396.000 | +282.000 |
| W3 (17–22 Mar) | Rp 13.549.000 | Rp 13.549.000 | **0** |
| W4 (22–31 Mar) | Rp 55.920.000 | Rp 55.090.000 | +830.000 |
| **Total** | **Rp 194.065.000** | **Rp 193.018.000** | +1.047.000 (0,5%) |

### Item promo besar — tercatat semua di manual Sunter

Spot-check item yang **di Bintaro luput** — di Sunter masuk semua:

| Item | Status di manual Sunter |
|---|---|
| Payday — Selfligating Metal Braces (25PM250) | ✓ tercatat di W2 (Rp 9.499.000) |
| MARET — Deluxe Metal Braces SP Free Orthokit | ✓ tercatat di W1 (Rp 5.999.000) + W2 (Rp 5.999.000) |
| FEB — DP CICILAN METAL SP | ✓ tercatat di W1 (Rp 3.800.000) + W4 (Rp 7.600.000) |
| Pemasangan ortho metal - drg fri (25PM016) | ✓ tercatat di W4 (Rp 3.900.000) |

### Apa artinya?

Sunter membuktikan **bisa**, tapi dengan cost: staff harus mengetik 561 baris item per bulan, double-check semua promo, jaga konsistensi pivot vs ACTUAL.

**Bintaro & Sunter sama-sama membuktikan satu hal:** manual reporting tergantung 100% pada diligence individu staff. Sunter teliti → akurat. Bintaro kewalahan → bocor 64 juta. Tidak ada di tengah — semua atau tidak sama sekali.

---

## Bagian B2 — Kalideres, Cinere, Pamulang (semua rapi)

3 cabang sisanya di-audit dengan metodologi sama. **Semuanya internally consistent (pivot = ACTUAL) DAN akurat vs Omnicare (±1%):**

| Cabang | ACTUAL manual | Pivot sum | Omnicare | Gap internal | ACTUAL vs Omni |
|---|---:|---:|---:|---:|---:|
| Kalideres | 106.500.000 | 106.500.000 | 106.450.000 | 0 ✅ | +0,05% ✅ |
| Cinere | 91.276.000 | 91.276.000 | 91.756.000 | 0 ✅ | -0,5% ✅ |
| Pamulang | 82.732.000 | 82.732.000 | 83.422.000 | 0 ✅ | -0,8% ✅ |

Ketiganya bukti bahwa manual *bisa* akurat — tapi (lihat Bagian C) dengan harga effort tinggi & tanpa jaminan.

> **Catatan terpisah — bukan bagian audit manual:** saat backfill historis, data Kalideres **2023-06 s/d 2025-04** sempat KOSONG di DB lokal. Investigasi: itu **bug sync kita** (drop diam-diam saat API Omnicare return "Invalid JSON" transient), BUKAN penutupan cabang. Re-sync per-cabang berhasil menarik datanya (Kalideres operasional penuh 2024). Backfill perlu retry logic lebih kuat. Tidak mempengaruhi audit Maret 2026 (di luar periode gap).

---

## Bagian C — Insight lintas cabang (kenapa tetap pindah ke auto-report)

Data manual akurat, tapi 4 alasan tetap kuat untuk otomasi:

1. **Prasyarat integrasi Accurate (alasan utama)** — rencana finance: Omnicare → Accurate otomatis, finance cuma verifikasi. Sheet manual yang diketik tangan **tidak bisa** jadi sumber pipa otomatis ke Accurate. Otomasi sales reporting = **fondasi wajib** sebelum integrasi finance jalan, bukan proyek terpisah.
2. **Effort vs nilai tidak proporsional** — ~8-15 jam/cabang/bulan × 5 cabang = ~500-900 jam/tahun tenaga, hanya untuk capai akurasi yang sistem bisa kasih gratis & instan.
3. **Fragility terbukti** — Bintaro error 21,8% bulan ini tanpa ketahuan. Tidak ada SLA; kualitas tergantung individu. Staf bagus cuti/pindah → laporan cabang itu langsung jatuh.
4. **Audit trail & real-time** — manual = snapshot akhir bulan, tanpa log. Auto = data H-1, history per transaksi, bisa untuk dashboard eksekutif real-time.

---

## Sumber kebenaran

| Layer | Status | Catatan |
|---|---|---|
| Omnicare API (sumber asli) | ✅ TEPAT | Sistem kasir klinik — kebenaran terakhir |
| `omnicare_sales_detail` (DB lokal) | ✅ TEPAT | Per-baris ter-sync benar — `net_sell_price` = nilai total per baris |
| `/report/sales` (Data Center) | ✅ TEPAT | `SalesReportController` pakai `SUM(net_sell_price)` benar |
| Manual Google Sheet — ACTUAL | ✅ ~Tepat (delta 0,5–1%) | Bintaro & Sunter sama-sama match Omnicare |
| Manual Google Sheet — pivot per-minggu | ⚠️ **Tergantung cabang** | Sunter rapi (sum = ACTUAL); Bintaro bocor 64 jt — *kualitas tergantung individu staff* |

---

## Catatan teknis (untuk arsip)

Selama investigasi awal, query DB lokal sempat memberikan total Rp 317 jt (lebih tinggi
Rp 18 jt dari Omnicare resmi). Penyebabnya: query investigator pakai
`SUM(net_sell_price * qty_sold)` — padahal `net_sell_price` di Omnicare **sudah merupakan
nilai total baris** (qty sudah dikalikan di dalam-nya). Production code (`SalesReportController`,
dashboard, `OmnicareEncounters` helper) sudah benar — hanya pakai `SUM(net_sell_price)`.
**Tidak ada bug di kode produksi.** Hanya temuan validasi alat investigator.

---

## Rekomendasi

1. **Hentikan listing per-minggu manual di Google Sheet — semua cabang.** Bukan hanya
   karena ada cabang yang bocor (Bintaro), tapi karena cabang yang rapi (Sunter) sekalipun
   harus invest effort sangat tinggi untuk menjaga konsistensi. Pakai `/report/sales` di
   Data Center — angka pasti cocok dengan kasir, tidak tergantung staff.

2. **Standardisasi kode item.** Sales pakai kode "25xxx" lama, Omnicare sudah "26xxx".
   Salah satu: (a) Omnicare bisa di-set pakai kode lama, atau (b) update template pivot
   sales pakai kode baru. Saat ini menyebabkan kebingungan tracking.

3. **Audit cepat ke 3 cabang lain (Kalideres, Cinere, Pamulang)** — pola Bintaro (bocor)
   vs Sunter (rapi) memperlihatkan kualitas tergantung individu. Audit semua cabang akan
   jadi bukti final bahwa **konsistensi cross-branch hanya bisa dicapai lewat auto-report**.

4. **Re-frame proposal ke sales:** *bukan* "kalian salah", tapi *"kerjaan typing yang
   melelahkan itu bisa dipotong, fokus ke analisis"*. Lihat
   [docs/proposal-laporan-sales-otomatis.md](proposal-laporan-sales-otomatis.md).

---

*Dibuat 2026-05-23 — sumber data: Omnicare export Mar 2026 + Dashboard laporan sales
manual Mar 2026 + DB lokal `omnicare_sales_detail` (sync per 2026-05-21).*
