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
