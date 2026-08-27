# Rencana — Role Konsultan & Restrukturisasi Supervisor

> **Status**: rencana, ACC Ekky 2026-08-21. **Belum dibangun di mana pun.**
> Eksekusi pertama di **dev**, bukan prod.
> Chat HUB menulis rencana ini; pembangunannya di chat build.
>
> Semua angka & perilaku di bawah **diverifikasi langsung ke DB dan kode prod**
> pada 2026-08-21, bukan diingat.

---

## 1. Masalah yang diselesaikan

Dua hal terpisah yang kebetulan datang bersamaan.

**(a) Konsultan.** Perusahaan memakai konsultan yang **wajib absen**, tapi tak
boleh punya wewenang: tak mengajukan cuti/lembur, dan **tak meng-ACC pengajuan
siapa pun**. Mereka boleh melakukan pekerjaan bidang yang mereka dampingi —
"konsultan finance bisa apa yang Finance bisa" — tapi tak lebih dari itu.

**(b) Struktur.** Supervisor akan membawahi **Coordinator Dentist**, **Front
Office**, dan **Koordinator GS** (role baru). Coordinator Dentist membawahi
Assistant Dentist; Koordinator GS membawahi General Services.

---

## 2. Yang SUDAH benar tanpa perlu dikerjakan

Role `Consultant` sudah ada di prod dengan tiga orang: **Hari**
(`hari@stdc.id`, marketing), **Rahmat** (`rahmat@sweettreats.id`, IT),
**Achmad Haickal** (`haickal@sweettreats.id`, finance — dibuat 21 Agu 2026).

Tanpa satu baris pun diubah, mereka sudah:

- **wajib absen** — `att_default_rule_id` role ini = *Fleksibel*; `landing_page`
  = `akun`, jadi begitu login langsung mendarat di layar absen. Terbukti
  dipakai: Rahmat absen masuk & pulang 14 Agu, WiFi-nya dikenali Bintaro,
  `att_day` = hadir.
- **nol wewenang ACC** — `Consultant` **nol baris** sebagai `approver_role_id`.
- **tak bisa mengajukan apa pun.**

⚠️ Yang terakhir bekerja lewat jalan yang menyesatkan:
`AttendanceRequestWorkflow::pastikanApexBukanSalahKonfigurasi()` menolak mentah
kiriman dari role yang tak dikenal grafik ACC dengan pesan *"Alur persetujuan
untuk jabatan ini belum diatur… Hubungi admin"*. Sesuai kebijakan, tapi
**terbaca seperti kerusakan**. → utang §10.1.

`stdc.id` = **Sweet Treats Dental Clinic, domain LAMA perusahaan**. Akses Hari
ke Laporan Penjualan → Executive **disengaja dan sah** (dikonfirmasi Ekky
21 Agu). Bukan kebocoran — jangan "diperbaiki".

---

## 3. Cara kerja mesin wewenang — tiga hal yang gampang salah tebak

Ini bagian terpenting dokumen. Satu saja terlewat, hasilnya struktur yang salah
**tanpa satu pun pesan error**.

### 3.1 ACC datang dari `att_approval_rule`, BUKAN dari bagan organisasi

`AttendanceRequestWorkflow::chainFor()` menaiki tabel `att_approval_rule`
(`role_id` → `approver_role_id`), **bukan** `group_path.parent_id`.

`group_path.parent_id` cuma menggerakkan dua hal: **tampilan bagan organisasi**
dan **cakupan review Checklist Akreditasi** (`supervisedDescendants()`).
`group_path.level` tak menggerakkan apa pun — ia dihitung, tak pernah dibaca
sebagai gerbang.

> **Konsekuensi**: memindah kotak di bagan **tidak** memindahkan wewenang ACC.
> Restrukturisasi = **dua tabel**, selalu.

### 3.2 `sort_order` BUKAN lapis berjenjang

`chainFor()` mengumpulkan **semua** approver-role dari satu frontier lalu
melebur user-nya jadi **satu lapis**. Ditambah `MAX_LEVELS` = **1 untuk semua
jenis** sejak 2026-08-05.

Akibatnya hari ini: Assistant Dentist punya dua baris (Coordinator Dentist
`sort 1`, Supervisor `sort 2`) — dan itu berarti pengajuannya bisa di-ACC
**Irma ATAU Anisa, siapa saja duluan**, bukan Irma dulu baru Anisa. Datanya
cocok: dari 116 ACC nyata, Anisa 72 dan Irma 22.

> **Konsekuensi**: kalau maunya "cuma atasan langsung", baris yang lain harus
> **DICABUT**, bukan diberi `sort_order` lebih besar.

### 3.3 Kemampuan role dijaga NAMA role yang di-hardcode

`group_access` cuma memberi **menu**. Kemampuan sesungguhnya dijaga daftar teks
yang dibandingkan dengan `session('group_name')`:

`RopService::ROLE_PENYETUJU` / `ROLE_ANTRIAN` / `ROLE_REKAP` ·
`CutiController::BOLEH` · `LembarGajiController::BOLEH` ·
`AturanKerjaController` · `PengawasanController` · `AbsensiBranchIpController` ·
`AuditLogController` · `canSeeAllBranches()` · `isFinance()`

> **Dua konsekuensi berlawanan.** Menguntungkan: role bernama `Konsultan
> Finance` otomatis **tak** cocok dengan `ROLE_PENYETUJU = 'Finance'`, jadi ia
> tak bisa menyetujui ROP secara tak sengaja. Merugikan: mengkloning menu
> Finance bulat-bulat menghasilkan menu yang **tampil tapi 403** — kegagalan
> senyap yang justru diburu detektor Pusat Akun.
>
> **JANGAN me-rename role** — gerbangnya membandingkan teks; rename memutus
> semuanya sekaligus, dan sesi yang sedang berjalan masih memegang nama lama.

`att_approval_rule` **tak punya FK** ke `group_path`: salah ketik nama role
masuk diam-diam dan tak me-rute ke mana pun. Hitung ulang barisnya sesudah
menulis.

---

## 4. Desain role Konsultan

Satu role per bidang — karena akses melekat ke **role**, bukan ke orang, jadi
satu `Consultant` tak bisa sekaligus sekaku Finance dan sekaku Marketing.

| Role baru | Induk yang ditiru | Orang |
|---|---|---|
| `Konsultan Finance` | Finance | Achmad Haickal |
| `Konsultan Marketing` | Head Marketing | Hari |
| `Konsultan IT` | Head IT | Rahmat |

Untuk **ketiganya**, tanpa kecuali:

- `att_default_rule_id` = **Fleksibel**, `landing_page` = `akun`.
- **NOL baris sebagai `approver_role_id`** di `att_approval_rule` — ini yang
  menegakkan "tak meng-ACC pengajuan siapa pun".
- **NOL baris sebagai `role_id`** — konsultan tak mengajukan cuti/lembur.

**Keputusan Ekky 2026-08-21**: `Konsultan Finance` = **Finance utuh, TERMASUK
menyetujui ROP**. Larangan "tak boleh ACC" berlaku untuk **pengajuan orang**
(cuti/lembur/izin), bukan untuk pekerjaan Finance sendiri — menyetujui ROP itu
memang tugas Finance, bukan wewenang tambahan.

⚠️ Karena itu arah implementasinya **bukan memangkas menu** melainkan
**menambahkan nama role ke daftar hardcode** (§6). Memangkas justru memberi
konsultan *kurang* dari yang diputuskan.

---

## 5. Struktur baru — dua tabel, bukan satu

```
SEKARANG                                 SESUDAH
Executive                                Executive
├─ Supervisor                            └─ Supervisor
│  ├─ Front Office                          ├─ Coordinator Dentist   ← PINDAH
│  ├─ General Services (parent2: Asdent)    │  └─ Assistant Dentist
│  └─ Sales                                 ├─ Front Office
├─ Coordinator Dentist                      ├─ Koordinator GS        ← BARU
│  └─ Assistant Dentist                     │  └─ General Services   ← PINDAH
└─ Head Marketing / Head IT / Finance       └─ Sales
```

### 5.1 `group_path` (bagan + cakupan checklist)

| Role | Aksi |
|---|---|
| `Coordinator Dentist` | `parent_id` → Supervisor (dari Executive) |
| `Koordinator GS` | **role baru**, `parent_id` = Supervisor |
| `General Services` | `parent_id` → Koordinator GS (dari Supervisor) |
| `General Services` | `parent_id_2` = Assistant Dentist → **dibuang** (jalur kedua yang tak dipakai `att_approval_rule` dan tak pernah dijelaskan) |
| Assistant Dentist / General Services | `level` ikut turun — pakai `relevel()` di `Set\Group`, jangan tulis angkanya manual |

### 5.2 `att_approval_rule` (wewenang ACC yang sesungguhnya)

| Pengaju | Sekarang | Sesudah |
|---|---|---|
| `Assistant Dentist` | Coordinator Dentist **+ Supervisor** | **Coordinator Dentist saja** — cabut baris Supervisor (§3.2) |
| `General Services` | Supervisor | **Koordinator GS** |
| `Coordinator Dentist` | Executive | **Supervisor** |
| `Koordinator GS` | — | **Supervisor** (baru) |
| `Front Office` | Supervisor | tetap |
| `Supervisor` | Executive | tetap |

> ⚠️ **Keputusan yang belum diambil**: sesudah ini Anisa **tak lagi** bisa
> meng-ACC Asdent maupun GS secara langsung — semuanya lewat Irma dan Fajri.
> Kalau Anisa harus tetap bisa (mis. saat Irma/Fajri cuti), baris Supervisor
> dipertahankan — tapi sadari itu berarti **siapa saja duluan**, bukan eskalasi
> berjenjang.

### 5.3 Orangnya

Alpajri Suhendar Al Qhusni (`fajri@sweettreats.id`) pindah role
`General Services` → `Koordinator GS`.

⚠️ Mengubah `users.group_id` **TIDAK** mengubah `att_user_rule`. Aturan kerja
Alpajri (*GS*, berlaku 2026-08-08) menempel pada orangnya dan **tetap** — itu
memang yang diinginkan. Tapi `att_default_rule_id` role baru **tidak** berlaku
surut untuknya; default hanya menyala saat akun **dibuat**.

---

## 6. Config vs kode — pembagian kerja yang jujur

**Config (SQL + `php artisan access:rebuild`)** — tanpa deploy kode: role baru,
`group_access`, `att_approval_rule`, `group_path`, pindah role orang, akun
konsultan di dev.

**Kode (chat build, bukan HUB)** — tanpa ini `Konsultan Finance` akan melihat
menu yang berujung 403:

1. `RopService::ROLE_PENYETUJU` — satu teks → **daftar**, tambahkan
   `Konsultan Finance`. Periksa ikutannya di `RopService` (~baris 199) dan
   `RopSapu`.
2. `CutiController::BOLEH` + `LembarGajiController::BOLEH` — tambah
   `Konsultan Finance`.
3. `canSeeAllBranches()` — tambah `Konsultan Finance`; Finance ada di situ, dan
   tanpa ini konsultan terkunci ke satu cabang.
4. Pesan tolak pengajuan konsultan (§10.1).

Untuk `Konsultan Marketing` & `Konsultan IT`: **tentukan dulu** menu mana yang
benar-benar perlu, lalu **pilih salah satu** — pangkas node-nya, atau tambahkan
nama rolenya ke gerbang. Jangan tinggalkan menu yang 403.

---

## 7. Urutan eksekusi di DEV

Dev cuma **satu working copy bersama**. Semua langkah di bawah **SQL + artisan**,
tak menggeser branch — jadi aman dijalankan sementara chat lain memakai dev.

1. Backup DB dev.
2. Buat 3 role konsultan (`group_path`): nama, `landing_page='akun'`,
   `att_default_rule_id` = Fleksibel, `parent_id` sesuai bidang.
3. Salin `group_access` dari role induk **termasuk node wadah** — pola:
   `database/sql/2026_08_13_dev_selaraskan_akses_front_office.sql` (idempoten,
   `NOT EXISTS`).
4. Buat role `Koordinator GS` + aksesnya (mulai dari salinan General Services).
5. `att_approval_rule` sesuai §5.2 — **cabut dulu yang harus dicabut**, baru
   tambah. Hitung ulang barisnya sesudahnya.
6. `group_path` sesuai §5.1, lalu **relevel**.
7. Pindahkan orang: Hari, Rahmat, Haickal ke role konsultannya; Alpajri ke
   Koordinator GS.
8. **`php artisan access:rebuild`** — wajib. Tanpa ini
   `storage/app/group_access/<id>.txt` tak lahir dan menunya tak muncul walau
   barisnya sudah ada.
9. Uji §8.

⚠️ Sesudah `access:rebuild`, pastikan berkasnya benar-benar berubah (cek mtime).
Prod pernah menjalankan **kode 4 Juni** karena 162 salinan bertanggal membajak
nama kelasnya.

---

## 8. Uji terima — yang harus dibuktikan, bukan diasumsikan

Uji HTTP "hijau" **tidak** membuktikan UI jalan: `curl -L` bisa mengikuti
pentalan ke halaman login lalu melapor 200. **Periksa URL akhirnya.**

| # | Yang diuji | Lulus kalau |
|---|---|---|
| 1 | Konsultan login | mendarat di layar absen, bisa absen |
| 2 | Konsultan buka layar pengajuan | tak ditawari cuti/lembur, atau ditolak dengan pesan kebijakan |
| 3 | Konsultan di antrian ACC orang | **tak muncul** sebagai approver di mana pun |
| 4 | Konsultan Finance buka tiap menu Finance | **nol 403** |
| 5 | Talitha & Alif ajukan cuti | approver = **Yovan**, bukan Konsultan Marketing |
| 6 | Asdent ajukan cuti | approver = **Irma saja** (bukan Irma atau Anisa) |
| 7 | GS ajukan cuti | approver = **Fajri** |
| 8 | Fajri ajukan cuti | approver = **Anisa** |
| 9 | Irma ajukan cuti | approver = **Anisa** (bukan lagi Dani) |
| 10 | Checklist Akreditasi Fajri | divisinya masih terlihat (§9.1) |
| 11 | Checklist Anisa | cakupannya sesuai harapan sesudah Irma masuk subtree-nya |
| 12 | Kehadiran | nol orang hilang dari roster |

---

## 9. Risiko & jebakan

### 9.1 ⚠️ Memindahkan Fajri bisa mematikan Checklist-nya

`sys_checklist_division` punya tiga baris: **Assistant Dentist**, **Front
Office**, **General Services** — namanya **persis** sama dengan nama role, dan
`checklistDivisionForGroup()` (`app/Helpers.php`) mencocokkannya **sama persis**.

Begitu Alpajri pindah ke role `Koordinator GS`, tak ada divisi bernama itu →
**dia berisiko kehilangan divisi checklist-nya**. Tabel divisi juga punya kolom
`group_id`, jadi ada dua jalur yang mungkin — **periksa mana yang benar-benar
dibaca sebelum memindahkan orangnya**, dan uji #10 wajib lulus.

### 9.2 Cakupan review Anisa melebar diam-diam

`supervisedDescendants()` menelusuri `parent_id`. Begitu Coordinator Dentist
masuk ke bawah Supervisor, **divisi Assistant Dentist ikut masuk cakupan review
checklist Anisa**. Mungkin memang diinginkan — tapi itu efek samping struktur,
bukan sesuatu yang diminta. Putuskan sadar, jangan ditemukan belakangan.

### 9.3 Irma kehilangan jalur ke Executive

Sesudah §5.2 pengajuan Irma pergi ke Anisa, bukan Dani. Itu memang maksud
restrukturisasi — tapi Irma perlu diberi tahu, bukan dibiarkan menemukan sendiri.

### 9.4 Sales

`Sales` tetap di bawah Supervisor dan tak disebut dalam restrukturisasi. Keenam
akun Sales adalah **login bersama per cabang** (nol scan, nol `att_day`, nol
bukti HP), jadi tak berpengaruh ke absensi. Dibiarkan.

---

## 10. Utang & yang sengaja TIDAK dikerjakan

1. **Pesan tolak pengajuan konsultan masih berbunyi seperti galat** ("Alur
   persetujuan untuk jabatan ini belum diatur… Hubungi admin"). Perlu
   dijujurkan jadi kalimat kebijakan. Kode, chat build.
2. **Role `Consultant` lama tidak dihapus** sampai ketiga orang benar-benar
   pindah dan diuji. Menghapus role yang masih dipakai = orang kehilangan menu
   tanpa jejak.
3. **Jangan sentuh `Executive` / `Branch Executive`** dalam kerjaan ini. Robi &
   Dani sudah punya aturan kerja pribadi (*Fleksibel*, 5 Agu) dan **tidak**
   hilang dari Kehadiran; yang kosong cuma default role-nya, dan itu baru
   menggigit saat akun **baru** dibuat di role itu.
4. **Prod menunggu dev lulus §8.** Tak ada satu pun langkah di dokumen ini yang
   boleh dijalankan langsung di prod.

---

## 11. STANDAR role konsultan — resep untuk bidang berikutnya

> Ditulis 2026-08-22 sesudah tiga role pertama berdiri dan diaudit. Tujuannya
> satu: konsultan bidang berikutnya (HR, Ops, apa pun) **tidak** menuntut
> perubahan kode, dan tidak bisa gagal diam-diam.

### 11.1 Yang membuat sebuah role jadi "konsultan" = SATU KOLOM

`group_path.wewenang_pengajuan` = `'pengamat'`.

Itu saja. Kolom itu yang dibaca `AttendanceRequestWorkflow` di **dua** pagar —
`pastikanBolehMengajukan()` (sisi kirim) dan `act()` (sisi ACC). Artinya:

- **JANGAN** menambahkan nama role baru ke daftar mana pun di kode.
  `ROLE_PENGAMAT_PARASUT` bukan daftar yang harus dirawat — ia parasut untuk
  satu keadaan saja: kode sudah naik, SQL belum.
- Kolom ini **tidak** menyentuh pekerjaan bidang. Konsultan Finance tetap
  menyetujui ROP; jalurnya `RopService`, bukan `att_approval_rule`.
- Diuji: menandai `General Services` sebagai pengamat langsung memblokirnya,
  dan mengembalikan konsultan ke `penuh` langsung melepas pagarnya — jadi yang
  bekerja memang kolomnya, bukan namanya.

### 11.2 Resep membuat konsultan bidang baru

1. `INSERT` role `Konsultan <Bidang>` — `landing_page = 'akun'`,
   `att_default_rule_id` = *Fleksibel*, `parent_id` = role induk bidangnya,
   `wewenang_pengajuan = 'pengamat'`.
2. Kloning `group_access` dari role induk dengan **membaca barisnya di
   lingkungan itu** — jangan pernah menulis daftar id node (id berbeda antar
   dev/prod).
3. **Cabut daftar baku alat keamanan** (§11.3).
4. **NOL baris** `att_approval_rule`, dua arah.
5. `php artisan access:rebuild`, lalu pastikan `storage/app/group_access/<id>.txt`
   benar-benar berubah mtime-nya.
6. `php artisan att:audit-role` — harus nol temuan BERAT.
7. Kalau menu induknya memuat halaman bergerbang nama-role (§3.3), tambahkan
   nama role baru ke gerbang itu **atau** buang node-nya. Jangan tinggalkan
   menu yang 403 — auditor akan menandainya.

### 11.3 Daftar baku yang DICABUT saat mengkloning

Alat keamanan & administrasi tak pernah "kerjaan bidang". Tak satu pun dijaga
gerbang nama-role, jadi **node-nya saja sudah memberi kuasanya penuh**:

| link | kenapa dicabut |
|---|---|
| `setusermanagement` | bisa membuat & menyunting akun siapa pun |
| `setgroup` | bisa mengubah role, termasuk role dirinya |
| `setaccess` | bisa memberi dirinya sendiri akses apa pun |
| `setsystemprofile` | setelan sistem |
| `absensi/wifi` | saksi WiFi absensi |
| `auditlog` | jejak siapa mengubah apa |

`setkesehatan` **tidak** dicabut: route GET saja, papan diagnosa.

Daftar ini hidup di `App\Console\Commands\AuditRole::ALAT_KEAMANAN` dan
**ditegakkan**, bukan sekadar diingat: role pengamat yang memegang salah satunya
jadi temuan BERAT.

**Ditambah `pengajuan` (ROP).** Keputusan Ekky 2026-08-22: konsultan tak
mengajukan **apa pun**, termasuk pengeluaran uang. Kategorinya sengaja dipisah
(`AuditRole::NODE_PENGAJUAN`) karena alasannya bukan keamanan sistem melainkan
wewenang. Ini kemampuan yang **tak sengaja diberikan** oleh kloning: role
`Consultant` lama tak punya node itu, dan `PengajuanController::store()` tak
bergerbang nama-role — jadi node-nya saja sudah cukup.
Pagar kodenya: `PengajuanController::tolakPengamat()` (`isPengamat()`),
dipasang di `index`, `create`, dan `store`.

### 11.4 Yang diperiksa auditor tiap dijalankan

`php artisan att:audit-role` (`--berat` untuk cron):

menu tampil-tapi-403 · rujukan role hantu di `att_approval_rule` (tabel itu tak
punya FK) · role berpenghuni yang pengajuannya **lolos sendiri tanpa jejak** ·
role penyetuju yang kosong penghuninya · siklus di rantai ACC maupun bagan ·
role pengamat yang punya wewenang/alat keamanan · divisi checklist tanpa role
senama · pengawas checklist tanpa divisi · user ber-role hantu.

Gerbangnya **dievaluasi dari sumbernya** — `canSeeAllBranches()` dipanggil
sungguhan dengan sesi palsu per role, konstanta controller dibaca lewat
refleksi — supaya auditornya tak jadi salinan yang basi. Satu yang masih cermin
(`absensi/aturan`, array inline di metode privat) ditandai jelas di keluarannya.
