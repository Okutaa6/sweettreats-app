# Rencana: Akun Dokter (tampilan + absen)

> Status: **BAHAN TERKUMPUL, belum dibangun** — 12 Agu 2026.
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

1. Kolom penghubung `users.raw_doctor_id` (§3)
2. Role "Dokter" berdiri sendiri (§4)
3. Keputusan arah absensi berbasis jadwal (§6)
4. Desain layar — brief di `docs/brief-desain/design-brief-akun-dokter.md`
