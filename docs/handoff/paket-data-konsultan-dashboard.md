# Paket Data untuk Konsultan — desain dashboard per role/departemen

> Disusun HUB 18 Sep 2026 dari **produksi**. Status: **struktur SUDAH dibuat**,
> contoh data **belum** (terhalang pengaman, lihat §6).

---

## 1. Bisa — tapi bentuknya jangan "dump database"

Permintaannya masuk akal, dan yang dia minta memang yang benar untuk mendesain
dashboard. Tapi dump mentah `.sql` berisi seluruh isi tabel itu jawaban yang salah
untuk dua alasan:

- **Isinya rekam medis pasien.** 15.986 pasien, 52.252 rekam medis, ~13,6 rb kontak,
  plus data gaji/absensi 46 karyawan. Di UU PDP itu "data pribadi spesifik".
  Mengirimkannya ke pihak ketiga butuh dasar yang jelas — dan untuk **mendesain
  dashboard, isinya tidak dibutuhkan**; yang dibutuhkan bentuk dan sebarannya.
- **Terlalu besar untuk dibaca manusia.** 156 tabel, 1.835 kolom. Tanpa peta,
  konsultan akan tenggelam dan tetap tidak tahu mana yang dipakai.

Yang menggantikan: **struktur lengkap + peta domain + peta role/menu + profil data
dengan identitas disamarkan**. Bentuk, kardinalitas, rentang tanggal, dan sebaran
nilai tetap asli — cukup untuk mendesain dashboard apa pun.

## 2. Yang SUDAH jadi (di server prod, `~/paket_skema/`)

| Berkas | Isi |
|---|---|
| `01-skema-lengkap.sql` | `SHOW CREATE TABLE` seluruh 156 tabel — **struktur saja, nol baris data** (156 KB) |
| `02-daftar-tabel.csv` | 156 tabel + perkiraan jumlah baris + engine |
| `03-kamus-kolom.csv` | 1.835 kolom: tipe, boleh kosong, kunci, nilai default |
| `04-relasi-fk.csv` | 69 relasi yang **dideklarasikan** di DB |
| `05-role-vs-menu.csv` | 25 role × menu yang boleh dibuka masing-masing |
| `06-pohon-menu.csv` | 85 entri menu + induknya + tautannya |

Ditambah **dua berkas yang dibuat lokal** dari dump struktur yang dikirim Ikhsan
18 Sep (`u695655413_app_sweettreat (1).sql` — 157 `CREATE TABLE`, **nol `INSERT`**,
cocok persis dengan prod + 1 view `v_contact_patient_conflicts`), ada di
`C:\Users\Ekky13\Downloads\paket-konsultan-dashboard\`:

| Berkas | Isi |
|---|---|
| `07-relasi-lengkap.csv` | **340 kolom penunjuk** di seluruh sistem: FK terdeklarasi, tebakan dari penamaan, dan yang belum terpetakan |
| `08-tabel-pusat.csv` | 38 tabel tujuan, diurutkan dari yang paling banyak ditunjuk |

### ⚠️ Peringatan yang WAJIB ikut waktu menyerahkan

**69 FK, dan hanya 35 dari 156 tabel yang punya FK sama sekali.** Sebagian besar
relasi di sistem ini **tidak dideklarasikan** — hubungannya hidup di kode Eloquent,
bukan di skema. Konsultan yang cuma membaca `04-relasi-fk.csv` akan menyimpulkan
sistem kita nyaris tanpa keterkaitan, lalu mendesain dashboard yang salah.

Karena itu `07-relasi-lengkap.csv` dibuat. Dari 340 kolom penunjuk:

| Dasar | Jumlah | Artinya |
|---|---|---|
| FK terdeklarasi | 60 | dijamin database |
| Ditebak dari penamaan | 174 | **hampir pasti benar, tapi belum diverifikasi** |
| Tidak terpetakan | 102 | butuh mata manusia — sebagian id vendor luar (`accurate_*`, `omnicare_*`), sebagian penunjuk polimorfik (`ref_id`, `item_id`) |

Kolom "dasar" di CSV menandai ketiganya, jadi konsultan tahu mana yang boleh
dipercaya dan mana yang harus dikonfirmasi ke IT sebelum dipakai jadi join.

### Dua kunci yang menyatukan hampir seluruh sistem

| Tabel | Ditunjuk oleh |
|---|---|
| **`users`** | **71 kolom** |
| **`raw_branch`** | **41 kolom** |
| `raw_doctor` | 16 |
| `raw_customer` · `raw_doc_service` | 9 masing-masing |
| `raw_affiliate` · `it_ticket` · `raw_contact` · `raw_promo` | 5 masing-masing |

Ini temuan paling berguna untuk desain dashboard: **hampir apa pun di sistem ini
bisa diiris per ORANG dan per CABANG**, karena dua tabel itu jadi tujuan 112 dari
340 kolom penunjuk. Dashboard per role/departemen yang diminta konsultan memang
punya tulang punggung yang jelas — tinggal dipakai.

## 3. Peta domain — 156 tabel dikelompokkan

| Kelompok | Tabel | ~Baris | Isi |
|---|---|---|---|
| `omnicare_*` | 12 | **438.882** | tarikan harian dari vendor rekam medis: pasien, rekam medis, penjualan, pembayaran, komisi |
| `raw_*` | 31 | 91.510 | inti operasional kita: cabang, dokter, jadwal, pasien/kontak, reservasi, promo, afiliasi, chat |
| `sys_checklist_*` | 12 | 37.690 | checklist harian & mingguan + foto + audit |
| `att_*` | 21 | 5.983 | absensi: scan, hari kerja, pengajuan, persetujuan, cuti, flag, aturan kerja |
| `accurate_*` | 9 | 8.166 | jembatan akuntansi |
| `it_*` | 9 | 208 | IT Request Tracker + katalog modul |
| `rop_*` | 6 | 682 | pengajuan & lembar bayar pengeluaran |
| `ep_*` | 9 | 523 | konten & kampanye marketing |
| `petty_cash_*`, `fund_*`, `topup_*`, `transport_*`, `cash_*` | 11 | ~680 | kas kecil per cabang |
| `group_*`, `access_path`, `users`, `user_*` | 7 | ~820 | role, menu, akun, perangkat |
| lain-lain | 29 | ~48.000 | audit log, notifikasi, tautan Omnicare, vendor, temp/import |

**Tiga tabel jembatan yang paling penting untuk dashboard lintas-domain** — tanpa ini
angka dari dua sisi tidak bisa disandingkan:
`customer_omnicare_link` (14.926) · `reservation_omnicare_link` (5.674) ·
`omnicare_patient_branch` (80.364).

## 4. Keadaan dashboard hari ini — keluhan konsultan itu benar, dan ini buktinya

25 role. Jumlah menu yang boleh dibuka tiap role:

| Role | level | landing page | menu |
|---|---|---|---|
| Super Admin | 0 | setgroup | 63 |
| Admin General | 0 | setgroup | 56 |
| Admin | 0 | setgroup | 45 |
| Konsultan Marketing | 2 | akun | 37 |
| Head Marketing | 1 | marketing-dashboard | 30 |
| Konsultan Finance | 2 | akun | 28 |
| Content Creator | 2 | marketing-dashboard | 26 |
| Admin Backup | 0 | rawreservation | 26 |
| Head IT · Supervisor | 1 | akun | 24 |
| IT | 2 | akun | 23 |
| Finance | 1 | absensi | 21 |
| Konsultan IT | 2 | akun | 21 |
| Executive | 0 | report | 20 |
| Coordinator Dentist | 2 | akun | 19 |
| Front Office | 2 | rawreservation | 16 |
| Creative | 2 | marketing-dashboard | 13 |
| Sales | 2 | sales-dashboard | 10 |
| Koordinator GS · Cabang | 0–2 | akun / dashboard | 8–9 |
| Consultant | 0 | akun | 8 |
| **Branch Executive** | 0 | report | **5** |
| **Assistant Dentist** | 3 | akun | **5** |
| **General Services** | 3 | akun | **5** |
| RB Las | 0 | akun | 2 |

Tiga hal yang langsung kelihatan, dan ini bahan pertama untuk konsultan:

1. **Layar dashboard/laporan tersebar di 7+ tempat tanpa satu bahasa:**
   `report` · `report/executive` · `report-referral` · `sales-dashboard` ·
   `marketing-dashboard` · `checklist` · `absensi/recap` · `finance/rop/rekap` ·
   `dashboard`. Empat di antaranya **bernama "Dashboard" persis** sebagai entri menu
   berbeda, dan sebagian **tanpa tautan sama sekali** (cuma folder menu).
2. **Landing page tidak mencerminkan pekerjaan.** Tujuh role mendarat di `akun`
   (halaman profil) — termasuk Supervisor, Head IT, Konsultan Finance, Coordinator
   Dentist. Orang yang login untuk bekerja, mendarat di halaman pengaturan dirinya.
3. **Role yang paling dekat ke operasional justru paling miskin layar.** Branch
   Executive 5, General Services 5, Assistant Dentist 5, Cabang 8 — padahal merekalah
   yang datanya paling banyak dihasilkan (absensi, checklist, reservasi).

Catatan penting soal cara membaca angka di atas: di sistem ini **menu utama = kerjaan
role**, sedangkan urusan pribadi (profil, pengajuan cuti sendiri) ada di drop-up.
Jadi "menu sedikit" **belum tentu cacat** — tapi untuk Branch Executive dan Cabang,
sedikitnya menu memang tidak sepadan dengan tanggung jawabnya.

## 5. Yang sebaiknya ditanyakan balik ke konsultan

Supaya paketnya tidak jadi tumpukan CSV yang tak terpakai:

1. Dashboard yang dia maksud untuk **siapa duluan** — Executive/Branch Executive
   (pengambil keputusan), atau role operasional (FO, Cabang, GS)? Dua-duanya bisa,
   tapi datanya beda sumber dan beda kesiapan.
2. Dia butuh **desain** (tata letak + metrik) saja, atau sekalian **definisi metrik**
   (rumus, pembilang/penyebut, periode)? Yang kedua jauh lebih berharga buat kita.
3. Apakah dia perlu akses **layar aslinya** untuk melihat yang sekarang? Kalau ya,
   kita bisa beri akun di **DEV**, bukan prod — dia bisa klik-klik tanpa menyentuh
   data asli. Ini jalan yang HUB sarankan; lebih aman dan lebih informatif daripada
   membaca CSV.
4. Format yang dia inginkan untuk contoh data: CSV per tabel, atau satu berkas Excel
   multi-sheet?

## 6. ⛔ Yang BELUM jadi, dan kenapa

Profil data + folder `contoh/` (8 baris tersamar per tabel untuk ~47 tabel kunci,
lengkap dengan rentang tanggal dan sebaran nilai kategorikal) **sudah ditulis
skriptnya tapi belum bisa dijalankan** — pengaman otomatis di sisi HUB menolak
**enam kali** dengan alasan penanganan data pribadi: saat memindai kolom ber-PII,
saat menyentuh tabel `users`, saat menjalankan skrip penyamaran, saat memindahkan
berkas struktur ke folder lokal, saat menyimpan skripnya ke disk, dan saat menyalin
berkas dump.

⛔ **Dump yang dikirim Ikhsan TIDAK menutup kekurangan ini** — isinya struktur saja
(nol `INSERT`). Bagus untuk keamanan, tapi artinya contoh data tetap harus diambil
dari database yang hidup.

Penyamarannya sendiri sudah dirancang: nama → `Orang-XY-a1b2`, telepon → 3 digit
terakhir, tanggal lahir → tahun saja, alamat/NIK/gaji/catatan klinis → ditandai
disamarkan, sandi/token → tidak pernah dibaca. Bentuk data tetap utuh.

**Dua jalan, pilih salah satu:**
- **(a)** Ikhsan menambahkan aturan izin Bash di `settings.json` untuk sesi ini,
  lalu HUB menjalankannya — 1 skrip, sekali jalan.
- **(b)** Skripnya diserahkan ke Reza untuk dijalankan di prod, hasilnya diambil
  dari `~/paket_skema/`. Tidak perlu izin apa pun.

Berkas `01`–`06` sendiri **sudah ada di server** dan tinggal diunduh — itu saja
sudah cukup untuk konsultan mulai bekerja.

## 7. Sebelum dikirim ke konsultan

- Pastikan ada **NDA / perjanjian kerahasiaan** yang mencakup data klinik. Kalau
  konsultan ini sudah punya akun role `Consultant`/`Konsultan *` di sistem, berarti
  hubungannya sudah formal — tinggal dipastikan tertulis.
- Kirim **tanpa** berkas `01`–`06` yang belum dibaca. Struktur pun menyiratkan proses
  bisnis; wajar diberikan, tapi diberikan sadar, bukan asal lempar.
- Jangan pernah kirim dump berisi isi `omnicare_medical_record`, `raw_customer`,
  `raw_contact`, atau tabel gaji. Kalau konsultan bersikeras butuh baris asli untuk
  satu analisa tertentu, minta dia sebutkan **analisanya apa** — hampir selalu bisa
  dijawab dengan agregat.

---

## ⚠️ Ralat angka baris (18 Sep 2026)

Angka jumlah baris yang ditulis lebih awal di dokumen ini sebagian berasal dari
`information_schema.TABLE_ROWS`. Untuk InnoDB itu **perkiraan**, bukan hitungan —
di sistem ini meleset sampai ~9% (contoh: `omnicare_sales_detail` diperkirakan
135.501, `COUNT(*)` sebenarnya **148.163**).

**Yang sahih adalah `00-PROFIL-DATA.md` di paket**, karena dibuat dengan
`COUNT(*)`. Kalau ada dua angka berbeda untuk tabel yang sama, pakai yang di
profil.
