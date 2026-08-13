# Rencana: Akun Dokter (tampilan + absen)

> Status: **PRASYARAT 1–3 SELESAI & LIVE DEV, 4 menunggu handoff desain** — 13 Agu 2026.
> Prod belum disentuh sama sekali.
> Desain layar lewat claude.ai/design (brief: `docs/brief-desain/design-brief-akun-dokter.md`).
> Basis kode: `integrasi/dev-2026-08-06`.

## 1. Kenapa ada

Dokter akan ikut absen dan butuh akun. Tapi lebih dari itu: **dunia dokter
sudah lengkap di database, mereka cuma tak bisa melihatnya.**

| Yang sudah ada di prod | Jumlah |
|---|---|
| `raw_doctor` (aktif) | 35 |
| `raw_doctor_schedule` — jadwal praktik | 103 |
| `raw_doctor_schedule_exception` — jadwal khusus | 57 |
| `raw_doctor_leave` — cuti dokter | 28 |
| `raw_doctor_service` — layanan per dokter | 261 |
| `raw_cus_reservation` | 4.759 |

Jadi pekerjaannya bukan membangun data, tapi **membukakan jendela**.

## 2. Enam dokter yang jadi gelombang pertama

Semua ada di prod & aktif. **Nol punya akun `users`.**

| Nama di prod | Praktik di | Jadwal |
|---|---|---|
| drg. Ahmad Haikal Noor | Pamulang | 3 |
| drg. Putri Moortiningsih Hasheila | Bintaro, Pamulang | 4 |
| drg. Maharetta Ditaprilia, Sp. Ort | Pamulang | 1 |
| drg. Irine Krisnawati | Bintaro, Pamulang | 4 |
| drg. Nadya Ayustia | Pamulang | 2 |
| drg Yusmaini Maizir Sp Ort | Cinere, Pamulang | 5 |

Keenamnya praktik di **Pamulang** — jadi ini menyatu dengan penggelaran
Pamulang, bukan proyek terpisah.

## 3. ⚠️ PENGHALANG: `users` dan `raw_doctor` tidak terhubung

Tak ada kolom penghubung sama sekali. `users` tak punya `raw_doctor_id`,
`raw_doctor` tak punya `user_id`.

**Akibatnya akun dokter yang dibuat sekarang = akun kosong** yang tak bisa
menampilkan apa pun tentang dirinya. Satu kolom penghubung adalah **prasyarat,
bukan pilihan**. Sarannya `users.raw_doctor_id` (nullable, unik) — arah itu
membuat "user ini dokter yang mana" terbaca dari sisi user.

## 4. Role dokter — berdiri sendiri, dan itu SUDAH didukung

Tak perlu perubahan skema. `group_path` punya `parent_id` nullable, dan
**Executive** serta **Super Admin** sudah persis begitu: `parent_id` NULL,
`level` 0. Jadi role "Dokter" tinggal dibuat dengan pola yang sama.

Role yang ada sekarang tak ada yang cocok — "Assistant Dentist" dan
"Coordinator Dentist" itu untuk asisten, bukan dokternya.

## 5. Isi akun (keputusan Ekky 12 Agu)

**MASUK:**
- **Jadwal praktik saya** — termasuk jadwal khusus & lintas cabang
- **Reservasi hari ini** — siapa yang akan dilayani. Kemungkinan besar ini yang
  paling sering dibuka, lebih sering daripada jadwal
- **Absen** — alasan awal akun ini dibuat

**DILEWATI dulu (keputusan Ekky):** cuti dokter.

### 5b. Rekam medis untuk dokter — BISA, dengan satu prasyarat keras

> ⚠️ **KOREKSI 13 Agu.** Versi awal bagian ini menutup fitur ini dengan alasan
> "data klinis basi 54 hari". **Itu salah** — angkanya diambil dari **dev**.
> **Prod sinkron normal**: sync terakhir 13 Agu 02:04, rekam terbaru 12 Agu,
> 50.133 baris. Yang basi cuma salinan dev (sync dev berhenti 20 Jun).
> Keberatan itu gugur; jangan dipakai lagi sebagai alasan.

Datanya memang sudah ada dan hidup. Yang belum ada bukan datanya, melainkan
**cara memastikan dokter hanya melihat pasiennya sendiri.**

#### ✅ Rancangan yang dipilih (ACC Ekky 13 Agu)

| Keputusan | Isi |
|---|---|
| **Gerbang akses** | Riwayat pasien terbuka **hanya bila pasien itu punya reservasi ke dokter tsb**. Sekali terbuka, riwayatnya UTUH — termasuk kunjungan yang ditangani dokter lain. |
| **Isi yang tampil** | `procedures` (tindakan) + `diagnose_note` + `chief_complaint`. |
| **TIDAK tampil** | `prescription`, tanda vital, NIK, alamat, email, no asuransi. Disaring **di lapisan query**, bukan di Blade. |

Gerbang lewat reservasi itu dipilih karena menjawab kasus nyata: **pasien
rujukan/pengganti** yang baru pertama ditangani dokter ini justru paling butuh
riwayat dokter sebelumnya. Kalau dibatasi "rekam yang saya tangani sendiri",
pasien seperti itu tampak tanpa riwayat sama sekali — padahal dokternya butuh.

**Rantai otorisasi — seluruhnya data KITA, tidak menyentuh `doc_id`:**

```
dokter (users.raw_doctor_id)
  → raw_cus_reservation.raw_doctor_id      ← GERBANG di sini
  → raw_customer
  → customer_omnicare_link.omnicare_pid
  → omnicare_medical_record.p_id
```

**Diuji di prod 13 Agu — semua 6 dokter menghasilkan data nyata:**

| Dokter | reservasi | pasien unik | tertaut Omni | rekam tersedia |
|---|---|---|---|---|
| Ahmad Haikal Noor | 142 | 56 | 50 | 433 |
| Putri Moortiningsih | 124 | 61 | 53 | 409 |
| **Maharetta Ditaprilia** | 128 | 59 | 51 | **425** |
| Irine Krisnawati | 45 | 23 | 21 | 104 |
| Nadya Ayustia | 10 | 10 | 8 | 28 |
| Yusmaini Maizir | 177 | 80 | 66 | 150 |

Dua temuan yang **membatalkan kekhawatiran sebelumnya**:

1. **Maharetta bukan lagi masalah.** Ia nol rekam di bawah namanya sendiri, tapi
   punya **425 rekam tersedia** lewat 59 pasiennya. Karena gerbangnya reservasi
   dan bukan `doc_id`, soal "nama Omnicare tak cocok" **lenyap seluruhnya**.
2. **Isi klinis jauh lebih lengkap dari dugaan.** Global cuma 27%/39% punya
   keluhan/diagnosa — tapi pada populasi pasien yang benar-benar dilihat dokter,
   angkanya **82%** (337 dari 409). Sebabnya masuk akal: pasien ortho/behel
   dengan kontrol berulang, dan di situ keluhan memang diisi. Peringatan "kartu
   kosong 3 dari 4 kali" **tidak berlaku** untuk kasus ini.

> **Karena itu, pemetaan `doc_id` di bawah TURUN dari prasyarat wajib menjadi
> catatan.** Nama dokter di rekam medis tetap dipakai sebagai **label tampilan**
> ("ditangani: Irine Krisnawati") — tapi TIDAK PERNAH untuk otorisasi.

#### ⚠️ Catatan: JANGAN cocokkan nama untuk apa pun yang bersifat otorisasi

Mencocokkan nama dokter Omnicare ke `raw_doctor` **gagal berbahaya**, bukan
gagal kosong. Dibuktikan di prod 13 Agu:

| Dicari | `doc_id` terjaring | Sebenarnya | Rekam |
|---|---|---|---|
| drg. Ahmad **Haikal** Noor | `20685` | ✅ Haikal Noor | 602 |
| | `14634` | ❌ **Nugroho Ahmad Riyadi** | 4.389 |
| | `20682` | ❌ **Ahmad Pambuko** | 92 |
| drg. **Putri** Moortiningsih | `20683` | ✅ Putri Moortiningsih | 787 |
| | `18428` | ❌ **Nabila Daneta Putri** | 285 |

Token nama depan bertabrakan antar dokter. Akibatnya bukan layar kosong —
**dokter membaca rekam medis pasien dokter lain.** Untuk data klinis, itu jenis
kegagalan yang tak boleh punya kemungkinan terjadi sama sekali.

`RawDoctor::resolveByIdOrName()` **tidak boleh dipakai untuk jalur ini.** Ia
dibuat untuk impor CSV & Open API, di mana salah tebak berarti reservasi nyasar
— mahal, tapi bisa diperbaiki. Di sini biayanya beda kelas.

**Yang dipakai: tabel pemetaan eksplisit**, diisi manusia sekali, tak pernah
oleh tebakan:

- `doc_id` terisi **100%** (50.133 dari 50.133) dan **bersih 1:1** ke nama —
  nol `doc_id` yang dipakai lebih dari satu nama.
- Totalnya cuma **52 `doc_id` untuk 49 nama** — pekerjaan sekali duduk.
- Satu dokter boleh punya beberapa `doc_id` (2 kasus: Annisa Basyira 3, Ira
  Septiani 2 — kemungkinan pendaftaran ulang), jadi pemetaannya **satu-ke-banyak**.
- ⚠️ **drg. Maharetta Ditaprilia nol rekam** padahal punya 128 reservasi di
  sistem kita. Entah nama Omnicare-nya berbeda, entah memang belum pernah.
  Harus dipastikan manusia saat memetakan, jangan ditebak.

#### Prasyarat kedua: jejak audit

Belum ada satu pun pencatatan saat rekam medis dibuka. Untuk kehadiran itu bisa
ditolerir; untuk rekam medis, "siapa membuka rekam siapa" justru biasanya
kewajiban intinya. (Kepatuhan Permenkes 24/2022 soal Rekam Medis Elektronik
perlu dicek ke yang menangani legal/klinis — bukan disimpulkan dari dokumen ini.)

#### Yang perlu diketahui saat merancang layarnya

Kelengkapan isi klinis **timpang**, dan itu menentukan bentuk kartunya
(prod, 13.143 rekam 12 bulan terakhir):

| Field | Terisi |
|---|---|
| `procedures` | **100%** |
| `prescription` | 92% |
| `diagnose_note` | 39% |
| `chief_complaint` | 27% |
| `subjective` / `objective` | 26% / 24% |

Jadi kartu riwayat harus bertumpu pada **tindakan**, dengan keluhan/diagnosa
sebagai bonus kalau ada — bukan sebaliknya. Kalau dibalik, layarnya akan tampak
rusak di mayoritas kunjungan.

⚠️ Tabel ini **bukan cuma isi klinis**: NIK 19.116 · alamat 46.508 · email
44.104 · tgl lahir 46.986 · resep 44.692. Penyaringan field **wajib di lapisan
query**, bukan sekadar tidak ditampilkan di Blade.

ℹ️ Dokter **sudah punya akses Omnicare sendiri**. Jadi nilai tambah layar kita
bukan menggantikan EMR, melainkan **konteksnya**: rekam medis muncul di tempat
dokter sudah berada — di samping jadwal dan daftar pasien hari ini, di HP,
di sela pasien. Untuk pendalaman klinis, tautkan ke Omnicare.

#### Yang masih WAJIB dibangun sebelum dibuka ke dokter sungguhan

1. **Jejak audit** saat rekam medis dibuka — sekarang nol. Minimal: siapa,
   pasien mana, kapan. Pakai `AuditLog` yang sudah ada.
2. **Penyaringan field di lapisan query** — `select()` eksplisit hanya kolom
   yang boleh, jangan `select *` lalu disembunyikan di Blade.

#### Catatan penyajian

`procedures` berisi **kode internal** yang tak berguna bagi pembaca —
mis. `26CON178 - Control Metal Braces;26SC262 - Scaling kls 1`. Bersihkan
kodenya dan pecah per `;` sebelum ditampilkan.

Isi Omnicare juga membawa artefak escaping HTML (`<br>`, `&nbsp;`) —
`OmnicareEncounters::buildClinicalSummary()` sudah menanganinya, pakai ulang
jangan tulis sendiri.

#### Keadaan dev untuk pengujian

Sync medical dev dijalankan ulang 13 Agu (izin Ekky) termasuk backfill
15 Jun–13 Agu: **50.324 baris, rekam terbaru 13 Agu, tak ada lubang lagi**.
Dev kini setara prod untuk keperluan uji.

**JANGAN dimasukkan:** lembar gaji (dokter kemungkinan dibayar per tindakan,
bukan gaji bulanan) dan checklist (itu urusan staf operasional klinik).

⚠️ Kalau nanti cuti dokter dibuka: sistem punya **DUA** jalur cuti.
`raw_doctor_leave` (efeknya pasien tak bisa booking) dan `att_request` (efeknya
ke gaji). Dokter harus lewat yang **pertama** — kalau dipaksa lewat jalur
karyawan, cutinya tercatat untuk gaji tapi kursi prakteknya tetap terbuka untuk
booking. Kesalahan yang mahal.

## 6. Masalah lintas cabang — dan kelebihan yang bisa dimanfaatkan

Dokter praktik di lebih dari satu cabang **secara alami**. Itu berbenturan
dengan dua kotak yang ada:

- Diberi cabang asal → layar Reservasi terkunci ke satu cabang (persis masalah
  yang baru diperbaiki untuk Ikhsan/Reza — lihat `rencana-gelar-cinere-pamulang.md` §5)
- Dikosongkan seperti orang kantor → jam kerjanya tak dinilai, padahal dokter
  justru punya jadwal praktik yang jelas

**Tapi dokter punya kelebihan yang tak dimiliki karyawan mana pun: sistem sudah
tahu seharusnya mereka di mana tiap hari** — jadwal prakteknya menyebutkan
cabang per hari.

Jadi untuk dokter, absensi bisa lebih pintar: bukan "hadir atau tidak", tapi
**"hadir di cabang yang sesuai jadwalnya atau bukan"**. Itu sekaligus
menyelesaikan masalah lintas-cabang tanpa harus memilih antara mengunci cabang
atau tak menilai sama sekali.

Arah ini perlu diputuskan sebelum membangun, bukan sesudah.

## 7. Yang sudah ada dan jangan dibangun ulang

- `AttendanceHpAbsen::absen()` — mesin absen HP (selfie + WiFi saksi), sudah
  jalan untuk karyawan
- `RawDoctor` model + relasi ke schedule/reservation/service — sudah lengkap
- Halaman Dokter (`DoctorController`) — sudah ada untuk sisi admin
- Pola akun pribadi: zona `akun/*` (`AkunAbsensiController`, `AkunAbsenController`)

## 8. Prasyarat sebelum mulai

1. ✅ **LIVE DEV** — Kolom penghubung `users.raw_doctor_id` (§3).
   `database/sql/2026_08_13_akun_dokter_prasyarat.sql` +
   migration `2026_08_13_100000_add_raw_doctor_id_to_users_table.php`.
   Relasi model terpasang: `Users::doctor()` / `RawDoctor::user()`.
2. ✅ **LIVE DEV** — Role "Dokter" berdiri sendiri (§4), di file SQL yang
   sama. `parent_id` NULL, `level` 0, landing `akun`, grant zona pribadi saja.
   ⚠️ Sesudah SQL: `php artisan access:rebuild`.
3. ✅ **DIPUTUSKAN 13 Agu** — arah absensi berbasis jadwal (§6):
   **tipe aturan baru `jadwal`** yang membaca jadwal praktik langsung, ACC Ekky.
   Rinciannya + urutan kerja di `docs/rencana/rencana-akun-dokter-absensi-jadwal.md`.
   Temuan yang mengubah §6: absen lintas cabang **sudah** jalan hari ini, jadi
   alasan mengosongkan cabang dokter gugur — yang tersisa cuma "hari praktik
   dinilai atau tidak".
   ⏳ Sisa satu pertanyaan ke Finance: **dokter dibayar per tindakan atau gaji
   bulanan?** Itu menentukan vonis "tak scan di hari praktik". TIDAK memblokir
   pembangunan — sikap sementaranya sudah ditulis dan aman dibalik.
4. ⏳ Desain layar — brief di `docs/brief-desain/design-brief-akun-dokter.md`,
   dibawa Ekky ke claude.ai/design.

⚠️ Butir 1–3 sudah jalan di **DEV saja** (`c544ea7`). **PROD belum disentuh.**
Urutan deploy prod nanti: `php artisan migrate --force` → SQL prasyarat → SQL
aturan jadwal → `php artisan access:rebuild`.

⚠️⚠️ **JANGAN buat akun dokter di prod sebelum SQL aturan `jadwal` jalan di
sana.** Reconciler hanya menyapa orang yang punya `att_user_rule`; akun yang
lahir sebelum `att_default_rule_id` role Dokter terisi akan punya scan YATIM
yang tak pernah dinilai, tanpa satu pun pesan.
