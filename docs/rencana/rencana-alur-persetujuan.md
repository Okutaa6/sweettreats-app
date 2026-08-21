# Rencana — Layar admin Alur Persetujuan (+ delegasi)

> **Status**: rencana, ACC Ekky 2026-08-21. Belum dibangun. **Tanpa tenggat.**
> Semua fakta diverifikasi ke kode dan DB **prod** 2026-08-21.

---

## 1. Kenapa

Hari ini "siapa meng-ACC siapa" hanya bisa diubah lewat SQL. Ekky ingin itu bisa
diatur dari sisi admin — dan itu **layak**, karena datanya sudah 100%
data-driven: satu tabel `att_approval_rule` (`role_id` → `approver_role_id`),
13 baris, dibaca `AttendanceRequestWorkflow::chainFor()`.

Keputusan Ekky yang mendasari: **hierarki itu ATURAN, bukan kebiasaan.** Itu
menuntut dua hal sekaligus — layar untuk mengaturnya (§3) **dan** jawaban untuk
penyetuju yang sedang tak ada (§4). Yang kedua bukan tambahan; tanpa itu,
hierarki ketat = pengajuan beku.

## 2. Pondasi yang sudah ada — jangan bangun ulang

- **Detektor**: Pusat Akun → Kesehatan sudah punya `#5 Aturan persetujuan
  nyasar` (`KesehatanController::approvalNyasar()`) yang menangkap baris
  menunjuk role yang tak ada.
- **Pagar hapus role**: `Set\Group` sudah menolak menghapus role yang masih
  dipakai sebagai pengaju/penyetuju.
- **Rumah layarnya**: `/absensi/aturan` sudah punya 3 tab (Aturan · Penugasan ·
  Kalender Libur) dan sudah berperan *"menulis aturan yang dibaca mesin"*.
  Tab ke-4 **Alur Persetujuan** duduk pas di situ.

## 3. Empat pagar — WAJIB, bukan opsional

Komentar di detektor #5 menyimpan catatan ini: ***"Sudah kejadian 1× — SEMUA
cuti auto-approve."*** Aturan persetujuan yang rusak pernah membuat seluruh
pengajuan cuti lolos sendiri. Memberi layar admin untuk mengedit tabel itu =
memberi tombol yang lebih mudah untuk mengulanginya.

### 3.1 Perubahan berlaku SURUT ke pengajuan yang sedang menggantung

`expected_role_id` **NULL di seluruh 116 baris** `att_request_approval`: rantai
dihitung ulang saat orang menekan tombol (`act()` memanggil `chainFor()` lagi),
**tidak dibekukan saat mengajukan**.

Akibatnya mencabut satu baris bisa membuat pengajuan yang sedang menunggu
**tak bisa di-ACC siapa pun**. Layar wajib menghitung dan menampilkan
*"perubahan ini menyentuh N pengajuan yang sedang menunggu"* **sebelum**
menyimpan.

> Pertimbangkan juga membekukan `expected_role_id` saat submit. Itu memperbaiki
> akar masalahnya, tapi mengubah perilaku — putuskan sadar, jangan sambil lalu.

### 3.2 Pagar auto-approve TIDAK melindungi semua role

`pastikanApexBukanSalahKonfigurasi()` melempar galat kalau role pengaju **tak
pernah muncul sebagai `approver_role_id`**. Tapi role yang **dirinya sendiri
penyetuju** dianggap "apex sah" → rantai kosong → **auto-approve**.

Role yang kena: **Supervisor · Head Marketing · Head IT · Coordinator Dentist ·
Executive**. Kalau baris `Supervisor → Executive` terhapus lewat layar admin,
pengajuan Anisa **lolos otomatis tanpa ada yang tahu** — persis bentuk insiden
yang dulu.

**Layar harus MENOLAK menyimpan keadaan itu**, bukan sekadar memperingatkan.

### 3.3 `sort_order` akan berbohong kalau ditampilkan sebagai "Lapis"

`chainFor()` melebur **semua** approver-role satu frontier jadi **satu lapis**,
dan `MAX_LEVELS` = **1 untuk semua jenis** sejak 2026-08-05. Buktinya di data:
Assistant Dentist punya dua baris (Coordinator Dentist, Supervisor) dan
kenyataannya **Irma 22 ACC, Anisa 20 ACC** — siapa saja duluan, bukan berjenjang.

Pilih salah satu, jangan menggantung:
- **UI jujur**: tampilkan sebagai *"siapa saja di daftar ini boleh meng-ACC"*.
- **Mesin diubah** dulu jadi benar-benar berjenjang (mahal — layar lapis-dua
  sengaja dipensiunkan 5 Agu).

Rekomendasi: **UI jujur.** Menampilkan tingkatan palsu lebih berbahaya daripada
tak menampilkan tingkatan.

### 3.4 Tak ada FK, dan collation berbeda

`att_approval_rule` tak punya FK ke `group_path`; salah ketik masuk diam-diam.
Kolomnya `utf8mb4_general_ci` sementara `group_path` `utf8mb4_unicode_ci` — join
polos = error 1267 (lihat `COLLATE` eksplisit di detektor #5). Validasi wajib di
aplikasi, dan normalisasi collation-nya masuk daftar rapi-rapi.

---

## 4. Penyetuju yang sedang tak ada

Tak ada jalan pintas: `act()` mewajibkan penyetuju ada di rantai — **Super Admin
pun tak bisa menerobos.** Penyetuju tunggal yang cuti = pengajuan timnya beku.

Satu pelepasan sudah ada tanpa disengaja: `usersOfRoles()` hanya menghitung user
ber-`status = 1`, dan **role kosong dilewati tanpa memakan jatah lapis** —
rantainya lanjut ke atasnya. Jadi *menonaktifkan akun* memicu naik-otomatis;
*cuti* tidak.

Dua lapis yang disarankan, urut:

1. **Otomatis dari cuti** — kalau penyetuju punya cuti yang sudah disetujui
   melingkupi tanggal itu, perlakukan seperti tak ada → rantai turun ke atasnya.
   Nol kerja manusia, dan datanya sudah tercatat.
2. **Delegasi manual berjangka** — untuk sakit mendadak yang tak sempat dicatat.
   ⚠️ **Tabelnya sudah ada dan belum pernah dipakai**: `att_approver`
   (`user_id`, `approver_user_id`, `level`, `catatan`), dibuat 18 Juli persis
   untuk "siapa approve siapa" per orang, **nol pembacaan di seluruh kode**.
   Hidupkan + tambahkan rentang tanggal. Jangan bikin tabel baru.

---

## 5. Uji terima

| # | Yang diuji | Lulus kalau |
|---|---|---|
| 1 | Ubah penyetuju satu role | pengajuan baru me-rute ke penyetuju baru |
| 2 | Cabut baris saat ada pengajuan menggantung | layar **memberi tahu jumlahnya** sebelum menyimpan |
| 3 | Cabut `Supervisor → Executive` | **DITOLAK** (§3.2), bukan sekadar peringatan |
| 4 | Simpan role yang tak ada / salah ketik | ditolak, tak ada baris nyasar tercipta |
| 5 | Buat lingkaran (A ACC B, B ACC A) | ditolak atau ditangani terang-terangan |
| 6 | Penyetuju sedang cuti disetujui | rantai turun ke atasnya, tanpa campur tangan |
| 7 | Delegasi berjangka aktif | delegat bisa ACC; di luar rentang tak bisa |
| 8 | Sesudah semua perubahan | detektor Kesehatan #5 tetap **bersih** |
| 9 | Layar dibuka non-admin | 403 |

## 6. Yang sengaja TIDAK dikerjakan

1. **Jangan menampilkan "Lapis 1 / Lapis 2"** selama `MAX_LEVELS` masih 1 (§3.3).
2. **Jangan me-rename role** dari layar ini — gerbang di seluruh aplikasi
   membandingkan `session('group_name')` sebagai **teks**; rename memutus
   semuanya sekaligus, dan sesi yang sedang berjalan masih memegang nama lama.
3. **Jangan bikin tabel delegasi baru** — `att_approver` sudah ada (§4).
4. Prod menunggu dev lulus §5.

## 7. Kaitan dengan rencana lain

Perubahan struktur di `rencana-konsultan-struktur.md` §5.2 dikerjakan lewat
**SQL** karena layar ini belum ada. Begitu layar ini jadi, pencabutan baris
`General Services → Supervisor` (saat Fajri sudah terbukti) jadi **pemakaian
pertamanya** — dan itu uji nyata yang bagus.
