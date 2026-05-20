# SweetTreats Open API — Dokumentasi untuk Cekat

Versi: 1.0 · Terakhir diperbarui: 2026-05-16

API ini memungkinkan Cekat (AI WhatsApp) mengirim profil customer & reservasi
ke sistem klinik SweetTreats, serta membaca range harga treatment & jadwal
dokter yang tersedia.

---

## 1. Base URL

| Environment | Base URL | Catatan |
|---|---|---|
| **Sandbox** | `https://app.sweettreats.id/apicekatdev` | Data uji, ditulis ke tabel terpisah, **tidak** memengaruhi data klinik asli. Gunakan ini untuk semua pengembangan & pengetesan. |
| **Live** | `https://app.sweettreats.id/apicekat` | Produksi — menulis ke data klinik sungguhan. |

> Endpoint **baca** (`service_range`, `jadwal_dokter`) mengembalikan data asli
> yang sama di sandbox maupun live (read-only), supaya hasil tes realistis.

---

## 2. Autentikasi

Setiap request **wajib** menyertakan header:

```
Authorization: Bearer <TOKEN>
```

- Token sandbox dan live **berbeda** dan diberikan terpisah oleh tim SweetTreats
  via channel aman (tidak ditulis di dokumen ini).
- Token sandbox hanya berlaku di base URL sandbox, token live hanya di live.

Contoh response gagal auth (HTTP `401`):

```json
{ "status": "error", "code": "UNAUTHORIZED", "message": "Token tidak valid atau tidak aktif." }
```

---

## 3. Konvensi Umum

**Format sukses** (HTTP `200`/`201`):

```json
{ "status": "success", "data": { ... } }
```

**Format error** (HTTP `4xx`/`5xx`):

```json
{ "status": "error", "code": "<CODE>", "message": "<pesan>", "errors": { ... opsional ... } }
```

| Code | HTTP | Arti |
|---|---|---|
| `UNAUTHORIZED` | 401 | Token tidak ada / salah / nonaktif. |
| `VALIDATION_ERROR` | 422 | Input tidak lengkap / format salah (lihat `errors`). |
| `TREATMENT_NOT_FOUND` | 422 | `treatment` tidak bisa dipetakan ke layanan. |
| `BRANCH_NOT_FOUND` | 422 | `cabang` tidak dikenal. |
| `SLOT_UNAVAILABLE` | 409 | Slot/jadwal yang diminta tidak tersedia. |
| `SERVER_ERROR` | 500 | Kesalahan internal. |

- **Rate limit**: 60 request / menit (lebih → HTTP `429`).
- **Format telepon**: kirim apa adanya — sistem menormalkan `+62`, `62`, spasi,
  strip menjadi `08xxxxxxxxx`. Nomor harus 10–15 digit setelah dinormalkan.
- **Format tanggal**: `YYYY-MM-DD`. **Format jam**: `HH:MM` (24 jam, mis. `09:00`).

---

## 4. Endpoint

### 4.1 `POST /customer_profile`

Catat profil/lead customer (kontak + intent + calon pasien).

**Request body (JSON):**

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `cabang` | string | ✓ | Nama atau ID cabang. |
| `nama` | string | ✓ | Nama customer (maks 255). |
| `no_telpon` | string | ✓ | Nomor WhatsApp. |
| `treatment` | string | – | Kode (mis. `SVC001`) atau nama treatment. |
| `intent` | string | – | Label intent/journey (mis. `Stat_Leads`). |

**Contoh:**

```bash
curl -X POST https://app.sweettreats.id/apicekatdev/customer_profile \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"cabang":"Bintaro","nama":"Budi","no_telpon":"+62 812-3456-7890","treatment":"SVC001","intent":"Stat_Leads"}'
```

**Sukses (200):**

```json
{ "status":"success",
  "data":{ "raw_contact_id":"...", "raw_customer_id":"...",
           "branch_matched":true, "treatment_matched":true, "sandbox":true } }
```

`branch_matched`/`treatment_matched` = `false` artinya data tetap tersimpan,
tapi cabang/treatment tidak bisa dipetakan (cek ejaan / pakai kode).

---

### 4.2 `GET /service_range`

Ambil range harga (min–max) per kode treatment.

**Query param:** `codes` — daftar kode dipisah koma, mis. `?codes=SVC001,SVC002`.

**Contoh:**

```bash
curl "https://app.sweettreats.id/apicekatdev/service_range?codes=SVC001,SVC002" \
  -H "Authorization: Bearer <TOKEN>"
```

**Sukses (200):**

```json
{ "status":"success",
  "data":{ "services":[
    {"code":"SVC001","name":"Scaling","min_price":150000,"max_price":300000,"found":true},
    {"code":"SVC999","name":null,"min_price":null,"max_price":null,"found":false}
  ]}}
```

`min_price`/`max_price` dihitung dari sub-layanan aktif treatment tsb.

---

### 4.3 `GET /jadwal_dokter`

Jadwal & ketersediaan dokter di sebuah cabang.

**Query param:**

| Param | Wajib | Keterangan |
|---|---|---|
| `cabang` | ✓ | Nama/ID cabang. |
| `hari` | – | Filter hari praktik: nama (`senin`..`minggu`) atau angka `1`–`7` (1=Senin). Hanya dokter yang praktik hari itu yang ditampilkan. |
| `tanggal` | – | `YYYY-MM-DD`. Jika diisi, response menambahkan `slots` per dokter. |
| `service` | – | Kode/nama treatment — memengaruhi durasi slot. |
| `doctor_id` | – | Batasi ke satu dokter. |

Setiap dokter selalu menyertakan `practice_days` (daftar hari praktik, mis. `["Selasa","Kamis"]`).

**Bentuk respons menyesuaikan kebutuhan (hemat token untuk AI):**

| Kondisi | Yang dikembalikan per dokter |
|---|---|
| `hari` diisi, `tanggal` kosong (pertanyaan umum "siapa praktik hari X") | `practice_days` + `next_date` (tanggal terdekat hari itu) + `total_dates` (jumlah). **Tanpa** daftar tanggal panjang. |
| `tanggal` diisi (mau booking) | `practice_days` + `available_dates` + `slots` tanggal itu. |
| Keduanya kosong | `practice_days` + `available_dates` lengkap. |

**Contoh — "dokter Bintaro hari Selasa siapa saja" (mode ringkas):**

```bash
curl "https://app.sweettreats.id/apicekatdev/jadwal_dokter?cabang=Bintaro&hari=selasa" \
  -H "Authorization: Bearer <TOKEN>"
```

**Sukses (200):**

```json
{ "status":"success",
  "data":{
    "branch":{"id":"...","name":"Bintaro"},
    "hari":"Selasa",
    "doctors":[
      { "id":"...","name":"drg. A","photo_url":"...",
        "practice_days":["Selasa","Minggu"],
        "next_date":"2026-05-19",
        "total_dates":13 }
    ]}}
```

**Contoh — mau booking tanggal tertentu** (`?cabang=Bintaro&tanggal=2026-05-19`): tiap dokter berisi `available_dates` + `slots` (`{ "slots":[{"time":"09:00","end_time":"09:30","available":true}], "sessions":{...}, "slot_interval":30 }`).

> Tanpa `hari` & `tanggal`: semua dokter, `hari` = `null`, `available_dates` lengkap.

---

### 4.4 `POST /reservation`

Buat reservasi. Pasien lama dikenali otomatis dari `no_telpon`; jika belum
ada, pasien baru dibuat.

**Request body (JSON):**

| Field | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `cabang` | string | ✓ | Nama/ID cabang. |
| `nama` | string | ✓ | Nama pasien. |
| `no_telpon` | string | ✓ | Nomor WhatsApp. |
| `treatment` | string | ✓ | Kode/nama treatment. |
| `date` | string | ✓ | `YYYY-MM-DD`, hari ini atau setelahnya. |
| `start_hour` | string | ✓ | `HH:MM`. |
| `end_hour` | string | ✓ | `HH:MM`, setelah `start_hour`. |
| `raw_doctor_id` | string | – | Jika kosong, sistem memilih dokter yang melayani treatment & slotnya kosong. |
| `intent` | string | – | Catatan/intent. |

**Contoh:**

```bash
curl -X POST https://app.sweettreats.id/apicekatdev/reservation \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"cabang":"Bintaro","nama":"Budi","no_telpon":"081234567890","treatment":"SVC001","date":"2026-05-20","start_hour":"09:00","end_hour":"09:30"}'
```

**Sukses (201):**

```json
{ "status":"success",
  "data":{ "reservation_id":"...", "status":"pending", "customer_id":"...",
           "pasien_baru_lama":"baru", "raw_doctor_id":"...", "sandbox":true } }
```

**Slot tidak tersedia (409):**

```json
{ "status":"error","code":"SLOT_UNAVAILABLE",
  "message":"Slot 09:00 pada 2026-05-20 tidak tersedia / di luar jadwal dokter." }
```

---

## 5. Treatment Mapping

`treatment` boleh berupa **kode** (disarankan, mis. `SVC001`) atau **nama**.
Resolusi berlapis: kode treatment → kode sub-layanan → nama treatment → nama
sub-layanan. Pakai kode bila memungkinkan agar akurat. Daftar kode diberikan
terpisah oleh tim SweetTreats.

## 6. Sandbox

Semua tulis di sandbox masuk ke tabel uji terpisah dan tidak memengaruhi KPI,
jadwal dokter, atau laporan klinik. Lakukan seluruh integrasi & pengetesan di
sandbox lebih dulu; pindah ke live hanya setelah lolos.

## 7. Changelog

- **1.0 (2026-05-16)** — Rilis awal: `customer_profile`, `service_range`,
  `jadwal_dokter`, `reservation` + sandbox.
