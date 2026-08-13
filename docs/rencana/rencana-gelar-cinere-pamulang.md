# Rencana: Gelar Cinere & Pamulang (+ perbaikan cabang-efektif)

> Status: **SIAP DIKERJAKAN** — data sudah dikumpulkan & diverifikasi 12 Agu 2026.
> Basis kode: `integrasi/dev-2026-08-06`. Dev sudah punya semua orangnya.
> Dokumen ini ditulis supaya chat baru bisa langsung jalan tanpa menyelidiki ulang.

## 1. Konteks

Prod digelar **bertahap**. Sudah live: ABG/Office + operasional Bintaro.
Giliran berikut: **Cinere & Pamulang**. Sunter & Kalideres menyusul.

Nama yang belum ada di prod itu **bukan data hilang** — memang belum gilirannya.

## 2. Siapa yang dibuatkan akun

### Operasional cabang (dari sheet HR 12 Agu)

| Cabang | Nama | Role | Aturan | Kontrak | Cuti |
|---|---|---|---|---|---|
| Cinere | Anggita Sarah Aprillia | Assistant Dentist | FO-Asdent | 21-Apr-27 | 10 |
| Cinere | Della Raqisiah | Front Office | FO-Asdent | 28-Nov-26 | 6 |
| Cinere | Wahyudin | General Services | GS | 06-May-27 | 11 |
| Pamulang | Alviona Zunka Prasetyo | Assistant Dentist | FO-Asdent | 21-Okt-26 | 2 |
| Pamulang | Mutiara Nurul Fajri | Front Office | FO-Asdent | 07-Nov-26 | 7 |

⚠️ **Ahmad Saipulloh (GS Pamulang) SUDAH RESIGN** — jangan dibuatkan akun.
Yang di dev sebaiknya **dinonaktifkan**, bukan dihapus (jejak absennya utuh).

### Orang penempatan (rolling) — ikut terbawa

| Nama | Cabang ASAL | Ditempatkan di | Kontrak | Cuti |
|---|---|---|---|---|
| Ramdan Ramdhani | **Kalideres** | Pamulang | 24-Jul-27 | 12 |
| Verini Selvia Naskah | **Sunter** | Cinere | 07-Nov-26 | 0 |

⚠️ Keduanya berasal dari cabang yang **belum digelar**. Tetap perlu akun
(dengan cabang asal Kalideres/Sunter) + baris **rolling** ke cabang penempatan.
**JANGAN mengubah `users.raw_branch_id` mereka** — lihat §4.

⚠️ Ejaan: sheet HR menulis "Verini Selvia **Naskah**", dev "**Nashkah**".
Samakan ke versi sheet.

### Aturan cuti (dari keputusan Ekky 11–12 Agu)

Jatah **12 hari per kontrak, diberikan di depan**. Tapi **karyawan baru BELUM
BERHAK** — jatah muncul saat kontrak DIPERPANJANG. Angka di sheet = **sisa**,
bukan jatah awal. Pencatatannya: `saldo_awal` = 12, selisihnya ditutup satu
baris `att_cuti_mutasi` jenis `koreksi` berketerangan jujur (jangan mengklaim
"terpakai N hari" — kita cuma tahu besarnya).

⚠️ **Verini bersaldo 0** — perlu dipastikan ke Ekky: "belum berhak" (kontrak
pertama, seperti Della/Talitha/Alif/Reza) atau "sudah terpakai habis"?
Perlakuannya beda: yang pertama TIDAK diberi baris penyesuaian.

Kolom "Kontrak" = **hari jadi kontrak** (akhir periode berjalan = awal periode
berikutnya). `kontrak_mulai` = 12 bulan mundur. Asumsi 12 bulan; kalau ada yang
6/24 bulan, tanggal MULAI-nya salah (akhir tetap benar).

## 3. Cara membuat akunnya

Dev **sudah punya semuanya** — aturan kerja, posisi checklist, email. Jadi
memindahkan ke prod = menyalin, bukan menyusun ulang.

⚠️ **Cocokkan lewat EMAIL, jangan nama.** Ejaan berbeda antar sumber sudah
terbukti memakan waktu (Alpajri/Apajri, Meilani/Melani, Naskah/Nashkah).
⚠️ **Email bisa beda antar lingkungan** — Alpajri: prod `fajri@`, dev `apajri@`.
⚠️ `group_path` prod ⊃ dev (prod punya 2 role ekstra) → **petakan lewat NAMA
role**, jangan id.

Tiap orang butuh: baris `users` · `att_user_rule` · `sys_user_position`
(kind=`home`) · `att_cuti_periode`. Password acak + `must_change_password`.

## 4. ⚠️ JANGAN pindahkan cabang asal orang penempatan

Ini keputusan yang pernah diambil dan **dibatalkan sekali** — jangan diulang.
Kasus Rizka (menginap di Kalideres, 6–7 Agu): sempat muncul saran memindahkan
rosternya, lalu dibatalkan karena keliru.

Alasannya: `users.raw_branch_id` itu **identitas roster & dasar rekap**,
sedangkan penempatan sifatnya sementara. Menggesernya membuat rekap bulan lalu
ikut berubah arti. Absensi lintas cabang memang sudah didukung sejak awal
(`att_day.cabang_terlibat`).

## 5. Temuan yang harus ikut diperbaiki: cabang efektif

Fitur **rolling SUDAH ADA** (Reza, `2026_08_05_user_position_rolling.sql`) —
`sys_user_position.kind` = `home` | `rolling`, dan `scopeActiveOn()`
memprioritaskan rolling. Ada juga helper `positionBranchOn($userId, $date)`.

**Masalahnya: helper itu cuma dipakai SATU tempat** (`ChecklistExcuseService`).
Halaman lain mengambil cabang dari **sesi login**, yang diisi dari
`users.raw_branch_id` di `AuthController`. Terhitung **51 titik di 12
controller** — Reservasi, Pasien, Dokter, Petty Cash, Cuci AC, Checklist, dll.

Akibat nyata: **Verini (FO) ditempatkan di Cinere akan melihat reservasi
Sunter.** Checklist-nya benar, tapi pekerjaan utamanya melihat data cabang yang
salah. Sama untuk Ramdan.

### Usul perbaikan — satu titik, bukan 51

Semua titik itu membaca **nilai sesi yang sama**. Jadi cukup ubah **saat
login**: isi cabang sesi dari `positionBranchOn(today)` dengan fallback ke
`users.raw_branch_id`.

Yang penting: **kolom database TIDAK disentuh.** Cabang asal tetap identitas
roster; yang berubah cuma "hari ini bekerja di mana" — dan itu memang arti yang
benar untuk penyaringan layar. Absensi tak terganggu karena dia membaca kolom
langsung, bukan sesi.

Batas yang diterima sadar: sesi diisi saat login, jadi rolling yang dimulai di
tengah hari baru berlaku setelah keluar-masuk sekali.

**Uji yang wajib lulus:**
- Verini (home Sunter, rolling Cinere) login → Reservasi menampilkan **Cinere**
- Rolling berakhir → kembali melihat **Sunter** tanpa perubahan data
- Orang tanpa `sys_user_position` (mis. ABG) → tetap seperti sekarang
- Orang ABG bercabang NULL (Ikhsan/Reza/Anisa/Irma) → tetap melihat semua cabang
- `att_day`/rekap **tidak berubah** sama sekali

## 6. Urutan yang disarankan

1. Perbaikan cabang-efektif (kecil, dan membuat sisanya bermakna)
2. Buat 5 akun operasional + 2 akun orang penempatan
3. Baris rolling untuk Ramdan → Pamulang, Verini → Cinere
4. Nonaktifkan Ahmad Saipulloh di dev
5. Uji di dev, baru prod (SQL dulu kalau ada, lalu `deploy.sh`, lalu rsync bila
   menyentuh `public/assets/**`)

## 7. Prasyarat yang belum terjawab

- **Saldo Verini 0**: belum berhak atau sudah habis?
- **Ela Puput Cahyani** (ada di sheet ABG, belum punya akun) — masih dilewati
- Kontrak **Alif & Reza** masih dicari Ekky
