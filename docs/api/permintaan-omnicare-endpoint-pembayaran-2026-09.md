# Permintaan Endpoint Tulis Pembayaran — Sweet Treats → Omnicare (Fase 2)

> Untuk dikirim ke tim Omnicare/Lokadok. Disusun 4 Sep 2026 sebagai **lanjutan**
> dari *Proposal Sweet Treats API v2* (Juni 2026) dan **balasan** atas *Open API
> Documentation — sweettreats (#29957), 10 Agu 2026*. Bahasa sengaja netral:
> ini permintaan otomasi, bukan penggantian sistem.

---

## 1. Terima kasih & konfirmasi apa yang sudah kami terima

Dokumen Open API 10 Agustus menjawab **Fase 1** proposal kami dengan lengkap:

| Yang kami minta (v2, Fase 1) | Yang diberikan |
|---|---|
| Add / Update Reservasi | `set/insert_appt`, `set/update_appt_by_id`, `set/cancel_appt` |
| Add / Update Pasien | `set/add_patient`, `set/update_patient` |
| Update Dokter (status + jadwal) | `set/update_doctor` (*preview*) — idempoten via `request_id` ✅ |
| Idempotensi & anti-duplikat (pertanyaan terbuka #6) | `request_id` di `update_doctor`; `cancel_appt` idempoten ✅ |
| Respons saat slot penuh (pertanyaan #4) | `metadata.code 402 — "Sesi dokter penuh"` ✅ |
| Dedup pasien (pertanyaan #1) | `get/search_patients` (*preview*), `get/find_patient_by_mobile_dob` ✅ |
| Katalog kode (pertanyaan #7) | 200 / 400 / 401 / 402 ✅ |

Kami siap mengimplementasikan Fase 1 di sisi kami.

## 2. Yang belum ada: endpoint TULIS untuk pembayaran

Di v2 kami menulis Fase 2 (Pembayaran) sebagai **gambaran, bukan spesifikasi**
— sengaja, agar tim Omnicare merancang arsitektur write keuangannya sendiri.
Dokumen 10 Agustus belum memuatnya: pembayaran saat ini hanya bisa **dibaca**
(`sales_payments`, `unpaid_invoices`, `sales_detail`, `cashflow` *preview*).

Surat ini adalah **spesifikasi konkret** yang dulu kami janjikan "menyusul".
Supaya mudah bagi tim Omnicare, **bentuk data yang kami usulkan mencerminkan
skema READ yang sudah ada** — nama field sama dengan `sales_payments` dan
`sales_detail`, jadi tak ada kosakata baru.

### 2.1 Tujuan bisnis (satu paragraf)

Sweet Treats ingin mencatat pembayaran pasien di sistem kami (kasir/front
office memakai satu layar untuk reservasi → kedatangan → pembayaran), lalu
**mengirimkannya ke Omnicare agar Omnicare tetap menjadi sumber kebenaran
keuangan dan rekam medis.** Tidak ada perhitungan yang dipindahkan dari
Omnicare; kami mengirim *fakta pembayaran*, Omnicare tetap memutuskan pajak,
BHP, pembulatan, dan komisi.

### 2.2 Endpoint yang diminta

#### `POST /set/add_payment` — catat satu pembayaran atas invoice/encounter

Kredensial di body seperti endpoint lain; parameter domain di query string.

```
POST /set/add_payment HTTP/1.1
Host: api.lokadok.xyz
Content-Type: application/json

{
  "username": "<subdomain>",
  "password": "<passkey>",
  "place": "<place>",
  "request_id": "ST-PAY-20260904-000123",     // idempoten: kirim ulang = hasil sama, bukan duplikat
  "encounter_id": "4004",                     // ATAU "invoice_num": "DTG-0526-0040" — salah satu wajib
  "pid": "P-0001",
  "pay_amount": 390700,
  "payment_method": "Debit",                  // dari daftar nilai valid (lihat 2.4)
  "payment_type": "Bayar",                    // Bayar | DP | Pelunasan (mohon konfirmasi daftarnya)
  "voucher": "",                              // kode voucher bila ada
  "payment_note": "EDC BCA, ref 8821",
  "paid_at": "2026-09-04 14:20:00",           // waktu kasir; zona Asia/Jakarta
  "cashier": "ekky"                           // username operator Sweet Treats (jejak audit)
}
```

Respons sukses yang diharapkan — **mengembalikan identitas Omnicare** supaya
kami menyimpan `payment_id` mereka, bukan ID kami:

```
{ "response": { "payment_id": "14907241890000000000000123",
                "invoice_num": "DTG-0526-0040",
                "invoice_status": "AKTIF",
                "amount_due": 0 },
  "metadata": { "message": "Transaksi Sukses", "code": 200 } }
```

Kode yang kami harapkan: `200` sukses · `400` parameter salah · `401` invoice /
encounter tidak ada, atau **pembayaran melebihi sisa tagihan** (mohon pesan
yang menyebut `amount_due`) · `402` rate-limit · dan **kirim ulang dengan
`request_id` sama → `200` dengan `payment_id` yang sama** (bukan `401`
"duplikat").

#### `POST /set/cancel_payment` — batalkan pembayaran (soft, beralasan)

```
{ "username", "password", "place", "payment_id", "cancel_reason", "cancel_reason_note", "request_id" }
```

Sesuai prinsip v2 §"Edit & Hapus": **tidak ada hard-delete** — pembayaran
dibatalkan tetap tampil di `get/sales_payments` dengan `invoice_status` /
`cancel_reason` terisi (field ini sudah ada di skema READ).

#### (Opsional, fase lanjut) `POST /set/add_invoice`

Hanya jika Omnicare **mensyaratkan** invoice ada sebelum pembayaran untuk
encounter yang belum di-checkout. Kalau `add_payment` ke `encounter_id` sudah
cukup membuat invoice-nya, endpoint ini **tidak perlu**. Mohon konfirmasi mana
alurnya.

### 2.3 Pertanyaan yang perlu jawaban Omnicare

1. **Kunci pembayaran**: apakah pembayaran menempel ke `invoice_num` atau
   `encounter_id`? Bolehkah kami mengirim salah satu, dan apa aturannya bila
   encounter belum punya invoice?
2. **Daftar nilai** `payment_method` dan `payment_type` yang valid per practice
   (cash, QRIS, EDC per bank, transfer, asuransi). Bolehkah kami menariknya
   lewat endpoint `get/…` supaya tak perlu hard-code?
3. **Pembayaran sebagian / DP**: boleh lebih dari satu `add_payment` per
   invoice? `amount_due` dihitung Omnicare dan dikembalikan?
4. **Pajak, BHP, pembulatan**: kami kirim `pay_amount` apa adanya; Omnicare
   yang memutuskan alokasinya — betul? Adakah kasus kami harus mengirim
   rinciannya?
5. **Komisi**: pembayaran yang kami kirim ikut memicu perhitungan komisi
   dokter/asisten di Omnicare seperti pembayaran dari kasir Omnicare — betul?
6. **Sandbox**: environment WRITE terpisah untuk uji (pertanyaan v2 #5, belum
   terjawab). Ini **prasyarat** sebelum kami mengirim pembayaran nyata.
7. **Rate limit WRITE** (pertanyaan v2 #8): berapa request/detik?
8. **`update_doctor`** masih *preview* — perkiraan kapan hidup?

### 2.4 Yang kami janjikan dari sisi Sweet Treats

- Setiap kiriman membawa `request_id` unik dan **kami tidak akan mengirim ulang
  tanpa `request_id` yang sama** — Omnicare bebas dari duplikat dari sisi kami.
- Kami menyimpan `payment_id` Omnicare sebagai kunci; ID internal kami tak
  pernah dikirim sebagai identitas.
- Rekonsiliasi harian: kami membandingkan kiriman kami dengan
  `get/sales_payments` dan melaporkan selisih ke Omnicare, bukan menimpa.
- Uji penuh di sandbox dulu; pembayaran nyata hanya sesudah tim Omnicare
  menyatakan siap.

## 3. Ringkasan permintaan

| # | Endpoint | Prioritas |
|---|---|---|
| 1 | `set/add_payment` (idempoten via `request_id`) | **wajib** |
| 2 | `set/cancel_payment` (soft) | wajib |
| 3 | `get/payment_methods` (atau daftar nilai tertulis) | wajib |
| 4 | sandbox WRITE | **prasyarat** |
| 5 | `set/add_invoice` | hanya bila alur mengharuskan |

Terima kasih. Kami siap berdiskusi teknis kapan pun tim Omnicare luang.

— Tim IT Sweet Treats
