# Omnicare (Lokadok) Open API — ringkasan spek vendor 10 Agu 2026

> Sumber: `Open API Documentation — sweettreats (4).pdf`, *Generated 10/08/2026
> 23:09*, practice **sweettreats (#29957)**. Diringkas chat HUB 4 Sep 2026 supaya
> spek ini **ada di repo**, bukan hanya di unduhan Ekky. Ini balasan vendor atas
> proposal kita (`project_omnicare_push`: fase 1 push reservasi/pasien, fase 2
> pembayaran). **Kalau ada versi PDF lain, bandingkan dulu dengan daftar 24
> endpoint di bawah sebelum mengubah rencana.**

## ⛔ Temuan utama: TIDAK ADA endpoint tulis untuk pembayaran

24 endpoint: **18 READ, 6 WRITE**. Keenam WRITE-nya menyentuh **pasien, janji
temu, dan jadwal dokter** — bukan invoice, bukan pembayaran, bukan transaksi.

| WRITE | Fungsi | Status |
|---|---|---|
| `set/add_patient` | daftarkan pasien (`pfname` wajib; `pid` boleh dikirim) | hidup |
| `set/update_patient` | ubah demografi pasien by `pid` | hidup |
| `set/insert_appt` | buat janji temu (`date`,`time`,`pid`,`place`,`doc_id`) → `appt_id` + `booking_code` | hidup |
| `set/update_appt_by_id` | ubah janji temu by `appt_id` | hidup |
| `set/cancel_appt` | batalkan (soft, idempoten) | hidup |
| `set/update_doctor` | sinkron jadwal/status dokter, idempoten via `request_id` | **preview — belum hidup** |

Pembayaran hanya bisa **DIBACA**: `get/sales_payments`, `get/unpaid_invoices`
(real-time, saldo tersisa + WA pasien), `get/sales_detail`, dan `get/cashflow`
(*preview*). ⇒ **Modul "fitur pembayaran sendiri → push ke Omnicare" belum
ditopang spek ini.** Yang ditopang: **reservasi & pasien dua arah** — persis
fase 1 proposal kita ("hilangkan input ganda staf").

## Model akses — semuanya berbeda dari kebiasaan REST

- **Semua request `POST`**, termasuk `get/*`. `Content-Type: application/json`.
- Kredensial di **body JSON** (`username` = subdomain practice, `password` =
  passkey dari *Practice Place → Edit Membership → "Passkey Sync"*). Tak ada
  header Authorization. Parameter domain (`place`, `pid`, `start`, `end`,
  `per_page`) di **query string**.
- Base: `https://api.lokadok.xyz/get/<endpoint>` dan `/set/<endpoint>`.
- ⚠️ **HTTP selalu 200.** Hasil sebenarnya di `metadata.code`:
  `200` sukses · `400` parameter/auth salah · `401` **record tak ada / aturan
  bisnis gagal** (termasuk *"Data kosong"*) · `402` **rate-limit** (*"Masa jeda.
  Akses dibatasi."*) atau **slot penuh** (*"Sesi dokter penuh"*).
  Kode klien yang cuma memeriksa status HTTP akan **lolos palsu** — jebakan
  yang sama dengan `laravel.log` sepi.
- Paginasi: `per_page` = **nomor halaman** (mulai 1), total di `metadata.pages`.
- Laporan per cabang **maks rentang 31 hari** (`commission`, `sales_detail`,
  `sales_payments`, `product_sales`, `daily_visit_report`).

## 18 endpoint READ

| Endpoint | Isi | Catatan |
|---|---|---|
| `all_medical_records` | semua rekam medis, filter cabang/tanggal | `doc_id` berisi **nama** dokter |
| `medical_record` | riwayat per `pid` | ada `follow_up_date`, `status` |
| `all_patients` | semua pasien | `calculated_final_point`, `last_visit_cal_id` |
| `find_patient_by_mobile_dob` | cari pasien by HP + tgl lahir | |
| `search_patients` | cari lintas cabang (nama/HP/NIK) untuk dedupe | **preview** |
| `location` | daftar cabang | `wa_number` per cabang |
| `doctor_list` | dokter per cabang; `date` → hanya yang bisa direservasi | `doc_id` **angka**, `duty_period` |
| `time_slots` | slot per dokter; slot penuh **hilang dari daftar** | multi-booking |
| `polyclinic_list` | daftar poli | |
| `procedure_list` | daftar tindakan + harga | contoh: `Control Metal Braces`, poli **Ortodonti** |
| `commission` | komisi per baris jual per staf medis | `doc_id` **angka**; maks 31 hari |
| `sales_detail` | detail penjualan + `payment_status` | `base_price`, `usage_cost`, `net_profit` |
| `sales_payments` | catatan pembayaran (metode, tipe, voucher) | |
| `unpaid_invoices` | **real-time** piutang pasien + HP/WA | untuk tagih / tandai |
| `product_sales` | penjualan produk: qty, omzet, **profit** | `sum_base`, `net_profit`, diskon |
| `daily_visit_report` | ringkasan kunjungan + top tindakan | |
| `patient_transactions` | rekap transaksi per pasien | **preview** |
| `cashflow` | arus kas masuk/keluar | **preview** |

## Apa artinya untuk modul yang sedang dipetakan (§ peta antrian)

- **Omnicare push → dipecah.** (a) *Reservasi & pasien dua arah* — **siap
  requirement sekarang**, spek ada. (b) *Pembayaran → Omnicare* — **belum ada
  endpoint**; masuk tracker sebagai **DITUNDA beralasan** supaya Executive
  melihat kita menunggu vendor, bukan diam.
- **ORTHO** — `procedure_list` punya poli **Ortodonti** dengan kode tindakan;
  `all_medical_records.procedures` + `medical_record.follow_up_date` cukup
  untuk *tracking perawatan*. ⚠️ `doc_id` di rekam medis = nama, di komisi =
  angka — **wajib pemetaan**, jangan cocokkan nama (memori
  `project_rekam_medis_omnicare`).
- **COGS / Inventory** — `sales_detail.base_price`/`usage_cost` dan
  `product_sales.sum_base`/`net_profit` adalah **data HPP mentah dari vendor**;
  ini sumber yang bisa dipakai COGS begitu Finance mengajukan.
- **WA notif** — `unpaid_invoices` dan `location.wa_number` sudah menyediakan
  nomor tujuan; pengirimnya tetap harus kita bangun (lewat Cekat).
- **Akun dokter** — `set/update_doctor` masih *preview*; jangan menggantungkan
  sinkron jadwal dokter padanya dulu.
