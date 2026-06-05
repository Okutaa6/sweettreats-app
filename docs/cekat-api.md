# SweetTreats Open API — Dokumentasi untuk Cekat

Versi: 1.4 · Terakhir diperbarui: 2026-05-23

API ini memungkinkan Cekat (AI WhatsApp) mengirim profil customer & reservasi
ke sistem klinik SweetTreats, serta membaca range harga treatment, jadwal
dokter, daftar treatment (kode), dan daftar cabang aktif.

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
- **PIC untuk token & dukungan teknis**: Ekky Rizki (RBLAS) — email
  `ekky@rblas.id`, channel komunikasi via WhatsApp/email. Token sandbox dikirim
  lebih dulu untuk integrasi; token live diserahkan setelah sandbox lolos tes.

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
| `NOT_FOUND` | 404 | Resource (mis. `reservation_id`) tidak ditemukan. |
| `SERVER_ERROR` | 500 | Kesalahan internal. |

- **Rate limit**: 60 request / menit (lebih → HTTP `429`).
- **Format telepon**: kirim apa adanya — sistem menormalkan `+62`, `62`, spasi,
  strip menjadi `08xxxxxxxxx`. Nomor harus 10–15 digit setelah dinormalkan.
- **Format tanggal**: `YYYY-MM-DD`. **Format jam**: `HH:MM` (24 jam, mis. `09:00`).
- **Zona waktu**: server pakai **Asia/Jakarta (WIB / UTC+7)**. Semua tanggal &
  jam yang dikirim/diterima diasumsikan WIB. Tidak ada konversi otomatis.

### Enum nilai

**`status` reservasi** (dipakai di `POST /reservation`, `GET /reservation/{id}`):

| Nilai | Label | Arti |
|---|---|---|
| `pending` | Menunggu Konfirmasi | Default saat baru dibuat; menunggu admin konfirmasi. |
| `confirmed` | Dikonfirmasi | Admin sudah konfirmasi, customer diharapkan datang. |
| `waitlist` | Waiting List | Antrian (slot belum pasti tersedia). |
| `completed` | Selesai | Kunjungan selesai. |
| `cancelled` | Dibatalkan | Reservasi dibatalkan. |

**`category` treatment** (dipakai di `GET /treatments`):
`behel`, `estetika`, `standar`, `pembedahan`, `lainnya`.

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
| `dokter` | string | – | Nama atau ID dokter preferensi lead (mis. `"drg. Anita"`). Resolusi berlapis: ID persis → nama persis → nama LIKE. Disimpan ke `raw_customer.raw_doctor_id` bila ter-resolve. |
| `intent` | string | – | Label intent/journey (mis. `Stat_Leads`). |

**Contoh:**

```bash
curl -X POST https://app.sweettreats.id/apicekatdev/customer_profile \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"cabang":"Bintaro","nama":"Budi","no_telpon":"+62 812-3456-7890","treatment":"SVC001","dokter":"drg. Anita","intent":"Stat_Leads"}'
```

**Sukses (200):**

```json
{ "status":"success",
  "data":{ "raw_contact_id":"...", "raw_customer_id":"...",
           "branch_matched":true, "treatment_matched":true,
           "doctor_matched":true, "sandbox":true } }
```

`branch_matched` / `treatment_matched` / `doctor_matched` = `false` artinya
data tetap tersimpan, tapi cabang/treatment/dokter tidak bisa dipetakan
(cek ejaan / pakai kode atau ID). Pakai endpoint `/treatments` & `/branches`
(§4.5, §4.6) untuk lihat daftar kode & nama aktif.

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
| `service` | – | Kode (mis. `SVC001`) / nama treatment — memengaruhi durasi slot. |
| `sub_service` | – | Kode (`code_service`) atau nama sub-layanan di bawah `service`. **Wajib disertai `service` induk** — bila kosong → `422 VALIDATION_ERROR`. Durasi slot diambil dari chain: `raw_doctor_branch_detail_duration` → `raw_doc_service_detail.duration_minutes` → fall-back service induk. |
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
    "service_id":null,
    "sub_service_id":null,
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

Setelah dapat `reservation_id` dari sukses 201, pakai **§4.4b
`GET /reservation/{id}`** untuk cek status terkini (mis. saat customer chat
ulang menanyakan konfirmasi).

---

### 4.4b `GET /reservation/{id}`

Cek status & detail reservasi yang sudah dibuat. Pasangan natural dari §4.4
POST — dipakai AI Cekat saat customer follow-up: *"reservasi saya udah
dikonfirmasi belum?"*

**Path param:** `id` = `reservation_id` dari response §4.4.

**Contoh:**

```bash
curl https://app.sweettreats.id/apicekatdev/reservation/38c76aed-ef86-4ad0-990d-c3f2f44e02d3 \
  -H "Authorization: Bearer <TOKEN>"
```

**Sukses (200):**

```json
{ "status":"success",
  "data":{
    "reservation_id":"...",
    "status":"confirmed",
    "status_label":"Dikonfirmasi",
    "date":"2026-05-19",
    "start_hour":"10:00",
    "end_hour":"10:30",
    "branch":  {"id":"...","name":"Bintaro"},
    "doctor":  {"id":"...","name":"drg. Anindito"},
    "customer":{"id":"...","name":"Budi","phone":"081234567890"},
    "services":[{"service_id":"...","code":"SVC001","name":"Kontrol Behel","sub_service_id":null}],
    "keterangan":"Reservasi via Cekat - Stat_Leads",
    "keterangan_kehadiran":null,
    "sandbox":true
  }}
```

`status_label` = label Bahasa Indonesia yg siap ditampilkan ke customer.
Untuk daftar nilai `status` lihat §3 ("Enum nilai"). Bila admin pindah jadwal
di CMS, panggilan ini akan mencerminkan `date`/`start_hour`/`end_hour` terkini.

**Tidak ditemukan (404):**

```json
{ "status":"error", "code":"NOT_FOUND",
  "message":"Reservasi tidak ditemukan: <id>" }
```

---

### 4.5 `GET /treatments`

**Discovery endpoint** — daftar lengkap treatment (service induk) + sub-layanan
aktif + kode. **Tujuan: Cekat refresh mapping otomatis** saat staff klinik
tambah/ubah treatment baru di CMS — tanpa harus koordinasi ulang dengan tim
Cekat.

**Query param:** tidak ada (read-only). Identik di sandbox & live.

**Contoh:**

```bash
curl "https://app.sweettreats.id/apicekatdev/treatments" \
  -H "Authorization: Bearer <TOKEN>"
```

**Sukses (200):**

```json
{ "status":"success",
  "data":{ "treatments":[
    { "id":"...", "code":"SVC001", "name":"Kontrol Behel",
      "category":"behel", "duration_minutes":30, "price":150000,
      "sub_services":[
        {"id":"...","code":"CBLM01","name":"Control Metal Braces",
         "duration_minutes":30,"price":150000}
      ]},
    { "id":"...", "code":"SVC007", "name":"Scaling", "category":"standar",
      "duration_minutes":60, "price":300000, "sub_services":[] }
  ]}}
```

`code` boleh `null` (treatment belum dipetakan staff). `sub_services` hanya
yang aktif. **Rekomendasi**: cache di sisi Cekat, refresh otomatis tiap 24 jam.

---

### 4.6 `GET /branches`

**Discovery endpoint** — daftar cabang aktif. Sama tujuannya seperti
`/treatments`: Cekat tahu cabang baru tanpa diberitahu manual.

**Query param:** tidak ada.

**Contoh:**

```bash
curl "https://app.sweettreats.id/apicekatdev/branches" \
  -H "Authorization: Bearer <TOKEN>"
```

**Sukses (200):**

```json
{ "status":"success",
  "data":{ "branches":[
    {"id":"...","name":"Bintaro"},
    {"id":"...","name":"Kemang"}
  ]}}
```

---

## 5. Treatment Mapping

`treatment` boleh berupa **kode** (disarankan, mis. `SVC001`) atau **nama**.
Resolusi berlapis: kode treatment → kode sub-layanan → nama treatment → nama
sub-layanan. Pakai kode bila memungkinkan agar akurat.

**Daftar kode terbaru ada di `GET /treatments` (§4.5)** — tidak perlu lagi
minta manual ke tim SweetTreats. Staff klinik tambah treatment baru di CMS →
langsung muncul di endpoint discovery.

## 6. Sandbox

Semua tulis di sandbox masuk ke tabel uji terpisah dan tidak memengaruhi KPI,
jadwal dokter, atau laporan klinik. Lakukan seluruh integrasi & pengetesan di
sandbox lebih dulu; pindah ke live hanya setelah lolos.

## 7. Known Limitations

Disampaikan jujur supaya tim Cekat menyusun alur AI dengan ekspektasi tepat:

- **Tidak ada webhook *kami → Cekat*.** Bila admin re-schedule/cancel/complete
  reservasi di CMS, Cekat **tidak otomatis diberitahu**. Solusi sementara:
  panggil `GET /reservation/{id}` (§4.4b) saat customer follow-up untuk dapat
  status terkini. Webhook reverse mungkin ditambahkan di fase berikutnya.
- **Rate limit per-IP**, bukan per-vendor. Bila Cekat berbagi infra IP dengan
  layanan lain, kuota 60/menit ditanggung bersama. Akan dipisah jadi per-token
  di fase berikutnya bila perlu.
- **Tidak ada audit log per-request** yang dibuka ke vendor. Internal hanya
  menyimpan `last_used_at` di `api_clients`. Untuk debug mendalam, koordinasi
  dengan PIC (§2).
- **`treatments` & `branches` tidak ber-pagination.** Asumsi data klinik puluhan,
  bukan ribuan. Bila >500 item suatu hari, pagination akan ditambah.
- **LIKE resolution** (`dokter`, `treatment`, `sub_service` via nama) berpotensi
  ambigu bila ada dua entri nama mirip. Tie-breaker: urutan `sort_order` lalu
  `name` (deterministic, tetapi bukan eksplisit "match terbaik"). **Pakai
  kode/ID bila kritikal**.
- **`GET /reservation/{id}`** sengaja **tidak mengembalikan customer phone
  ter-mask** — disambungkan apa adanya karena Cekat sendiri yang mengirimnya.
  Jangan teruskan field `customer.phone` ke pihak ketiga.

## 8. Changelog

- **1.4 (2026-05-23)** — Tambah `GET /reservation/{id}` (§4.4b) untuk cek status reservasi (alur follow-up customer). Doc: dokumentasikan zona waktu (WIB), enum `status` & `category`, PIC token (§2), known limitations (§7), kode error `NOT_FOUND` (404).
- **1.3 (2026-05-23)** — Discovery: tambah `GET /treatments` (§4.5) & `GET /branches` (§4.6). Vendor refresh daftar kode treatment & cabang otomatis dari API, tanpa koordinasi manual.
- **1.2 (2026-05-23)** — `customer_profile`: tambah field opsional `dokter` (nama/ID dokter preferensi lead). Resolusi ID → nama persis → nama LIKE. Disimpan ke `raw_customer.raw_doctor_id` bila ter-resolve. Response menambahkan `doctor_matched`. Non-fatal bila tak ketemu.
- **1.1 (2026-05-23)** — `jadwal_dokter`: tambah query param `sub_service` (kode `code_service` / nama sub-layanan) yang memengaruhi durasi slot lewat chain `raw_doctor_branch_detail_duration` → `raw_doc_service_detail.duration_minutes`. Wajib disertai `service` induk — bila kosong → `422 VALIDATION_ERROR`. Response menambahkan `service_id` & `sub_service_id` untuk transparansi.
- **1.0 (2026-05-16)** — Rilis awal: `customer_profile`, `service_range`,
  `jadwal_dokter`, `reservation` + sandbox.
