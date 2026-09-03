# Sweet Treats API — Integrasi dengan Omnicare (v2)

Dokumen ini menjelaskan spesifikasi API untuk **otomasi sinkronisasi data** antara Sweet Treats dan Omnicare — mengurangi input ganda di sisi staf. Sweet Treats mengirim (push) data ke Omnicare dan menarik (pull) laporan dari Omnicare.

**Catatan versi:** v2 adalah pengembangan dari proposal sebelumnya. Perubahan utama: (1) penambahan **API Dokter**, (2) **konvensi ID** memakai identifier Omnicare untuk entitas yang sudah ada di kedua sistem, (3) **roadmap Fase 2 (Pembayaran)** ditambahkan sebagai gambaran arah. Data promo dan referral/affiliate **tidak termasuk** dalam scope integrasi ini.

---

## Konvensi ID (penting)

Agar tidak terjadi ketidakcocokan identifier antar sistem:

- **Entitas yang sudah ada di Omnicare** (cabang, dokter, pasien lama, layanan yang sudah dibuat) → request memakai **ID Omnicare** (`place`, `doc_id`, `pid`, dst), **bukan** ID internal Sweet Treats. Sweet Treats sudah menyimpan pemetaan ini dari hasil GET API yang berjalan.
- **Entitas baru** (pasien baru, reservasi baru) → **Omnicare yang men-generate ID** dan mengembalikannya di response. Sweet Treats menyimpan ID tersebut untuk referensi berikutnya.

> Mohon tim Omnicare mengonfirmasi/menyesuaikan nama field final. Struktur di bawah bersifat **proposal** dan mengacu pada kosakata field yang sudah ada pada GET API Omnicare.

---

# FASE 1 — POST: Sweet Treats → Omnicare

## API Reservasi

### API Add Reservasi
`POST domain/reservasi/create`

**Request Body**
```json
{
  "placeId": "29958",
  "branchName": "Sunter",
  "patientType": "baru",
  "patientId": "",
  "doctorId": "13706",
  "doctorName": "drg. Berliana Oktavia",
  "reservationDate": "2026-05-28",
  "reservationTime": "16:00",
  "estimatedEndTime": "17:00",
  "notes": "",
  "sourceChannel": "WEB",
  "status": "pending",
  "isWaiting": 0,
  "services": [
    { "itemCode": "26CON178", "itemName": "Control Metal Braces" }
  ],
  "patient": {
    "fullName": "Rahmat",
    "whatsappNo": "08123456789",
    "email": "rahmat@gmail.com",
    "gender": "L",
    "birthDate": "1998-06-10"
  }
}
```
**Response**
```json
{ "responseCode": "00", "responseMessage": "Insert Success", "reservationId": "RSV-000456", "patientId": "P000123" }
```
> `patientId` (pid) hanya dikembalikan bila pasien baru dibuat inline (patientType = `baru`) — disimpan Sweet Treats untuk referensi berikutnya.

**Body Specification**

| Variabel | Deskripsi | Max |
|---|---|---|
| placeId * | ID cabang **Omnicare** (`place`) | 255 |
| branchName | Nama cabang | 255 |
| patientType * | `baru` / `lama` | 10 |
| patientId | `pid` Omnicare (wajib jika patientType = `lama`) | 255 |
| doctorId * | `doc_id` Omnicare | 255 |
| doctorName | Nama dokter | 255 |
| reservationDate * | YYYY-MM-DD | 10 |
| reservationTime * | HH:mm | 8 |
| estimatedEndTime | HH:mm | 8 |
| notes | Catatan / keluhan pasien | 200 |
| sourceChannel | WEB / WA / CS / APP | 255 |
| status | pending / confirmed / done / cancelled | 20 |
| isWaiting | 1 = waiting list, 0 = normal | 1 |
| services * | Array of object service | array |
| patient | Object data pasien (wajib jika patientType = `baru`) | object |

**Object services[]:** `itemCode *` (kode prosedur **Omnicare**, hasil pemetaan dari `procedureCode` Sweet Treats), `itemName` (opsional, untuk keterbacaan).
**Object patient:** `fullName *`, `whatsappNo *`, `email`, `gender *` (L/P), `birthDate` (YYYY-MM-DD).

**Catatan:** Jika `patientType = lama`, `patientId` (pid) wajib. Jika `baru`, object `patient` wajib & Omnicare membuat pasien + mengembalikan `pid`. Satu reservasi bisa >1 service.

### API Update Reservasi
`POST domain/reservasi/update`

**Request Body**
```json
{
  "reservationId": "RSV-000456",
  "placeId": "29958",
  "doctorId": "13706",
  "reservationDate": "2026-05-29",
  "reservationTime": "17:00",
  "estimatedEndTime": "18:00",
  "status": "confirmed",
  "notes": "Reschedule atas permintaan pasien",
  "services": [ { "itemCode": "26CON178", "itemName": "Control Metal Braces" } ]
}
```
**Response**
```json
{ "responseCode": "00", "responseMessage": "Update Success", "reservationId": "RSV-000456" }
```
**Catatan:** `reservationId` wajib. Hanya reservasi `pending`/`confirmed` yang boleh diupdate.

---

## API Pasien

### API Add Pasien
`POST domain/pasien/create`

**Request Body**
```json
{
  "fullName": "Rahmat",
  "whatsappNo": "08123456789",
  "email": "rahmat@gmail.com",
  "gender": "L",
  "birthDate": "1998-06-10",
  "nik": "317xxxxxxxxxxxxx",
  "address": "Jakarta",
  "birthPlace": "Jakarta",
  "sourceApp": "WEB",
  "placeId": "29958",
  "notes": ""
}
```
**Response**
```json
{ "responseCode": "00", "responseMessage": "Insert Success", "patientId": "P000123" }
```

**Body Specification**

| Variabel | Deskripsi | Max |
|---|---|---|
| fullName * | Nama lengkap | 255 |
| whatsappNo * | Nomor WhatsApp (**boleh sama** antar pasien — lihat catatan) | 255 |
| email | Email | 100 |
| gender * | L / P | 1 |
| birthDate | YYYY-MM-DD | 10 |
| nik | Nomor identitas (KTP) | 20 |
| address | Alamat | text |
| birthPlace | Tempat lahir | 50 |
| sourceApp | Sumber pendaftaran | 255 |
| placeId | `place` Omnicare tempat pertama daftar | 255 |
| notes | Catatan | text |

**Catatan identitas pasien (penting):** Identitas pasien = **`pid` (nomor pasien/RM Omnicare)**, BUKAN nomor HP. Satu nomor WhatsApp **boleh dipakai banyak pasien** (mis. orang tua mendaftarkan beberapa anak) — Sweet Treats memakai model ini. Response Add mengembalikan `patientId` (= `pid`) untuk disimpan.

**Dedup & lintas cabang:** Sebelum membuat pasien baru, Sweet Treats akan **mencari** apakah pasien sudah ada di Omnicare (pasien bisa terdaftar dari cabang lain — `pid` bersifat global). Jika sudah ada → pakai `pid` existing (jangan buat duplikat). Maka kami minta endpoint pencarian pasien lintas cabang (lihat `GET domain/pasien/list` dengan `search`). Idealnya `POST domain/pasien/create` juga **idempoten** (jika kombinasi pencocokan sudah ada, kembalikan `pid` existing).

### API Update Pasien
`POST domain/pasien/update` — body sama dengan Add + `patientId *` (pid Omnicare) di depan. Response: `Update Success` + `patientId`.

---

## API Layanan (Prosedur)

Layanan Sweet Treats 2 level: **Service** (kategori utama) & **Service Detail** (sub-prosedur). Satu service bisa punya banyak detail.

> **Catatan arah (hasil review):** Omnicare **sudah memiliki master prosedur/layanan** (terlihat di data penjualan sebagai `item_code`/`kode` + nama barang/jasa), namun **belum ada endpoint master-list-nya** di API. Karena itu prosedur **master-nya di Omnicare**. Pendekatan yang diusulkan: Sweet Treats **memetakan** layanannya ke kode prosedur Omnicare (kunci: `procedureCode` Sweet Treats ↔ `item_code` Omnicare), bukan memaksakan katalog Sweet Treats sebagai sumber. Karena itu kami **meminta endpoint baru** `GET domain/layanan/list` (master prosedur + `item_code` per `place`) agar pemetaan bisa dibangun & dijaga otomatis.
>
> Endpoint POST Layanan di bawah bersifat **opsional** — hanya dipakai bila Omnicare mengizinkan Sweet Treats membuat/memperbarui prosedur dari sisi kami. Jika tidak, cukup pemetaan + `GET domain/layanan/list`.

### API Add / Update Service
`POST domain/layanan/create` · `POST domain/layanan/update`

**Request (create)**
```json
{ "serviceName": "Whitening", "treatmentCategory": "estetika", "durationMin": 60, "price": 350000, "sortOrder": 1 }
```
**Response:** `{ "responseCode": "00", "responseMessage": "Insert Success", "serviceId": "..." }`

| Variabel | Deskripsi | Max |
|---|---|---|
| serviceName * | Nama layanan | 255 |
| treatmentCategory * | estetika / standar / pembedahan / lainnya | 50 |
| durationMin * | Durasi (menit) | numeric |
| price * | Harga | numeric |
| sortOrder | Urutan tampil | numeric |

Update: tambah `serviceId *` di depan.

### API Add / Update Service Detail
`POST domain/layanan/detail/create` · `POST domain/layanan/detail/update`

**Request (create)**
```json
{ "serviceId": "...", "procedureName": "Control Metal Braces", "procedureCode": "26CON178", "durationMin": 60, "price": 350000, "sortOrder": 1, "isActive": 1 }
```
**Response:** `{ "responseCode": "00", "responseMessage": "Insert Success", "procedureDetailId": "..." }`

| Variabel | Deskripsi | Max |
|---|---|---|
| serviceId * | ID layanan induk | 255 |
| procedureName * | Nama sub-prosedur | 255 |
| procedureCode | Kode unik sub-prosedur | 100 |
| durationMin * | Durasi (menit) | numeric |
| price * | Harga | numeric |
| sortOrder | Urutan tampil | numeric |
| isActive | 1 = aktif, 0 = nonaktif | 1 |

> **Perlu konfirmasi Omnicare:** apakah katalog prosedur dikelola per-`place` atau global.

---

## API Dokter (BARU di v2)

Sinkronisasi **master dokter** (status aktif & jadwal) agar konsisten di kedua sistem — bagian dari otomasi data, **bukan** menggantikan pengelolaan dokter di Omnicare.

### API Update Dokter
`POST domain/dokter/update`

**Request Body**
```json
{
  "doctorId": "13706",
  "placeId": "29958",
  "isActive": 1,
  "sessionDuration": 30,
  "bookOnline": 1,
  "schedule": [
    { "dayOfWeek": 1, "startTime": "09:00", "endTime": "17:00", "breakStart": "12:00", "breakEnd": "13:00" }
  ]
}
```
**Response**
```json
{ "responseCode": "00", "responseMessage": "Update Success", "doctorId": "13706" }
```

| Variabel | Deskripsi | Max |
|---|---|---|
| doctorId * | `doc_id` Omnicare | 255 |
| placeId * | `place` Omnicare | 255 |
| isActive | 1 = aktif, 0 = nonaktif | 1 |
| sessionDuration | Durasi sesi (menit) | numeric |
| bookOnline | 1 = bisa booking online | 1 |
| schedule | Array jadwal per hari (dayOfWeek 0–6, jam, jam istirahat) | array |

> **Komisi/fee dokter — TIDAK termasuk v2.** Saat ini komisi dokter dikelola di Omnicare; Sweet Treats hanya menariknya secara agregat via laporan Arus Kas (`komisiTenagaMedis`). Pengelolaan komisi dokter dari Sweet Treats akan dibahas pada fase berikutnya (lihat Roadmap).

---

# GET: Omnicare → Sweet Treats (laporan)

Endpoint yang diminta agar Sweet Treats dapat menarik laporan. Struktur mengacu pada laporan yang sudah ada di Omnicare. Semua mendukung pagination (`page`, `limit`) dan mengembalikan `totalRecords`/`totalPages`.

| Endpoint | Sumber laporan Omnicare | Filter utama |
|---|---|---|
| `GET domain/encounter/list` | Daftar Pemeriksaan Tenaga Medis | startDate*, endDate*, tenagaMedis, poliklinik, status |
| `GET domain/report/procedure-sales` | Prosedur / Layanan | startDate*, endDate*, tenagaMedis, pelayanan |
| `GET domain/report/patient-transactions` | Transaksi Pasien | startDate*, endDate*, asuransi, keanggotaan |
| `GET domain/report/cashflow` | Arus Kas | startDate*, endDate* |
| `GET domain/report/sales-detail` | Rincian Penjualan | startDate*, endDate*, poliklinik, tenagaMedis, asuransi |

**Field response inti** (ringkas; detail penuh sama seperti proposal sebelumnya):
- **encounter/list:** tanggal, waktu, kodeBook, noId, pasien, nik, tenagaMedis, poliklinik, perawatan, diagnosa, asuransi, noFaktur, totalBiaya, status.
- **procedure-sales:** namaProsedur, kode, terjual, totalGross, diskon, totalNet, totalBhp, netProfit, bagianKlinik (+ summary).
- **patient-transactions:** pasien, noId, keanggotaan, jlhTransaksi, prosedur, farmasi, paket, penggunaanVoucher, totalTransaksi, rataRata (+ summary).
- **cashflow:** kesediaanKas; penerimaanKas{penjualanTunai, pembayaranPiutang, pembayaranAsuransi}; pengeluaranKas{pembelianStokPeralatan, **komisiTenagaMedis**, komisiReferal}.
- **sales-detail:** noFaktur, noId, pasien, tenagaMedis, poliklinik, kode, barangJasa, nilaiGross, diskon, nilaiJual, pajak, nilaiModalBhp, netProfit, waktuTransaksi.

### Proposal GET tambahan (belum ada di laporan Omnicare)
- `GET domain/pasien/list` — sinkronisasi & **pencarian** master pasien **lintas cabang** (search nama/HP/NIK; field: noId/pid, pasien, noHp, email, jenisKelamin, tanggalLahir, nik, alamat, tanggalDaftar). Dipakai untuk dedup sebelum membuat pasien baru.
- `GET domain/layanan/list` — master prosedur/layanan + `item_code` per `place` (field: itemCode, namaLayanan, kategori, harga, isActive). Dipakai untuk memetakan `services[]` Sweet Treats ke prosedur Omnicare.
- `GET domain/rekam-medis/list` — riwayat kunjungan & perawatan pasien (field: noId, pasien, tanggalKunjungan, tenagaMedis, poliklinik, diagnosa, perawatan, resep, catatan). **Data sensitif — wajib autentikasi ketat.**

---

# ROADMAP — FASE 2 (gambaran, spec menyusul)

Bagian ini **belum spesifikasi final** — disampaikan agar tim Omnicare memahami arah integrasi dan dapat merancang arsitektur write keuangan sejak awal (tidak bolak-balik).

## API Pembayaran (rencana Fase 2)
**Tujuan:** Saat ini pembayaran dicatat di Omnicare lalu Sweet Treats menariknya via laporan (`cashflow` / `sales-detail`). Ke depan, Sweet Treats ingin **mengirim (push) data pembayaran** dari layar konfirmasi pembayaran di sisi Sweet Treats — sehingga diskon yang sudah dihitung di Sweet Treats langsung akurat dan tidak diinput ulang.

**Gambaran data yang akan dikirim** (per encounter/transaksi): referensi `encounter_id`/`reservationId`, `pid`, daftar item (`item_code`, qty, gross, diskon, nilai jual), metode pembayaran, status pembayaran.

**Yang perlu didiskusikan bersama Omnicare untuk Fase 2:**
- Endpoint write ke modul penjualan/keuangan Omnicare (mis. `POST domain/pembayaran/create`).
- Daftar nilai `paymentMethod` yang valid (cash, qris, EDC per bank, dll).
- Aturan pajak, BHP/biaya alat, pembulatan agar konsisten dengan perhitungan internal Omnicare.
- Idempotensi & pencegahan duplikat (client reference).

## Komisi Dokter (rencana lanjutan)
Saat ini di Omnicare. Ke depan kemungkinan dikelola di Sweet Treats lalu di-push (tarif per tindakan yang berubah-ubah). **Masih TBD** — disebut agar arah jelas.

---

# Sinkronisasi 2 Arah & Hal yang Perlu Disepakati

Bagian ini merangkum perilaku sinkronisasi dua arah dan pertanyaan terbuka yang perlu disepakati bersama tim Omnicare sebelum implementasi.

## 1. Edit & Hapus (soft-delete, dua arah)
- **Tidak ada hard-delete.** Data pasien/klinik bersifat audit → "hapus" = ubah status, bukan menghilangkan baris.
- **Sweet Treats → Omnicare:** reservasi batal = `status: cancelled` (via update); layanan/dokter nonaktif = `isActive: 0`.
- **Omnicare → Sweet Treats:** jika ada record yang dibatalkan/dihapus di Omnicare, mohon ditandai lewat **flag status** (mis. `status: cancelled/deleted`) di response GET — **jangan** hanya menghilangkan barisnya, karena perubahan seperti itu tidak bisa kami deteksi.

## 2. Sumber Kebenaran (source of truth) per data
Agar tidak saling menimpa saat kedua sistem mengubah data yang sama:

| Data | Master | Catatan |
|---|---|---|
| Reservasi (pembuatan) | Sweet Treats | dibuat di Sweet Treats |
| Status reservasi: selesai/done | Omnicare | ditandai saat kejadian di klinik |
| Pembatalan reservasi | Dua arah | batal bisa dari Sweet Treats (pasien) atau Omnicare → disinkron |
| Pasien (data pendaftaran) | Sweet Treats | intake di Sweet Treats; Omnicare boleh koreksi → kirim balik |
| Layanan/prosedur | Omnicare | master prosedur di Omnicare; Sweet Treats memetakan |
| Dokter | Omnicare (saat ini) | Sweet Treats sinkron status/jadwal |
| Rekam medis | Omnicare | klinis; Sweet Treats pull saja |
| Pembayaran | Omnicare (Fase 1) | Fase 2: Sweet Treats push |

> Saat Sweet Treats menarik perubahan dari Omnicare, data masuk ke tabel staging dulu lalu di-merge sesuai aturan master di atas — sehingga tidak ada data yang tertimpa diam-diam.

## 3. Mekanisme deteksi perubahan (incremental)
Agar edit dari Omnicare tertarik tanpa full-scan harian, mohon GET menyediakan:
- field **`updatedAt`** + parameter **`since` / `updatedAfter`** (tarik hanya yang berubah). *Pola ini sudah kami pakai pada pasien via `update_timestamp`.*
- **(opsional, fase lanjut)** webhook dari Omnicare ke Sweet Treats saat ada perubahan — paling real-time.

## 4. Pertanyaan terbuka (perlu jawaban Omnicare)
1. **Identitas & dedup pasien:** konfirmasi `pid` sebagai identitas; `POST pasien/create` idempoten (kembalikan `pid` existing jika cocok by HP+DOB/NIK); `GET pasien/list` search **lintas cabang**.
2. **Pemetaan layanan:** sediakan `GET domain/layanan/list` (master prosedur + `item_code` per `place`) sebagai kunci pencocokan `services[]`.
3. **Reservasi → encounter ↔ pembayaran (Fase 1):** reservasi yang kami push menjadi encounter/transaksi di Omnicare. Karena di Fase 1 pembayaran masih dicatat staff di Omnicare, bagaimana memastikan reservasi yang kami push **tersambung** ke transaksi pembayaran (hindari encounter/transaksi ganda atau yatim)?
4. **Bentrok jadwal:** jika slot sudah terisi saat kami push reservasi, apa response error-nya? (Sweet Treats tetap pemegang jadwal; kami tampilkan error ke staf untuk diselesaikan.)
5. **Environment sandbox/testing** terpisah untuk WRITE — agar data uji tidak masuk Omnicare produksi.
6. **Idempotensi & retry:** dukungan `external_ref`/client reference agar pengiriman ulang (jaringan) tidak membuat duplikat — berlaku umum (bukan hanya pembayaran).
7. **Katalog kode error:** selain `00`, mohon daftar kode error (validasi / duplikat / not-found / slot-penuh / auth-gagal) beserta arti.
8. **Rate limit & batch untuk WRITE:** berapa request/detik untuk endpoint POST? (READ saat ini cooldown 10 detik untuk endpoint sales.)
9. **Format data:** samakan per arah — tanggal (`YYYY-MM-DD` vs `DD/MM/YYYY`), nilai gender (`L/P` vs `M/F`), normalisasi nomor HP (`08xx` vs `+62`).
10. **Autentikasi WRITE + audit:** mekanisme auth untuk POST (API key per `place` / Bearer / IP whitelist), dan field identitas pengirim (mis. `sourceApp` + user staf) agar jejak audit di Omnicare menunjukkan asal Sweet Treats.
11. **Bootstrap awal:** sebelum sinkronisasi dua arah aktif, pencocokan data existing (Sweet Treats sudah punya pemetaan via `customer_omnicare_link` / `reservation_omnicare_link`).

# Catatan Umum

- Entitas lintas-sistem memakai **ID Omnicare** (`place`, `doc_id`, `pid`); entitas baru → ID di-generate Omnicare & dikembalikan di response.
- `responseCode` `00` = sukses; selain itu = error (sertakan pesan field yang gagal validasi).
- POST (Sweet Treats → Omnicare): `Content-Type: application/json`. GET (Omnicare → Sweet Treats): query parameter.
- Timestamp: timezone **Asia/Jakarta (WIB)**.
- Autentikasi API dibahas terpisah (API Key / Bearer Token) — diharapkan sama untuk READ & WRITE.
- Semua GET mendukung pagination (`page`, `limit`) + `totalRecords`/`totalPages`.
- **Data promo & referral/affiliate TIDAK termasuk** scope integrasi ini.
- Struktur di dokumen ini bersifat **proposal** — mohon konfirmasi/penyesuaian dari tim Omnicare.

---

## Lampiran — Pemetaan ke Database Sweet Treats (catatan internal)

| Kategori | Tabel Sweet Treats |
|---|---|
| Reservasi | `raw_cus_reservation`, `raw_cus_res_service` |
| Pasien | `raw_customer` (+ `raw_contact`, `customer_omnicare_link`) |
| Layanan | `raw_doc_service`, `raw_doc_service_detail` |
| Dokter | `raw_doctor`, `raw_doctor_schedule` |
| Cabang | `raw_branch` (`omnicare_place_id`) |
| Pembayaran (Fase 2) | `cash_deposit_transactions` |
