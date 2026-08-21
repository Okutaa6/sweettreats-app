# Log Update — jurnal milestone

> Entri terbaru selalu DI ATAS. Tiap entri ditulis ringkas & mandiri supaya
> bisa langsung di-copy ke alat task-management (Gemini) tanpa konteks tambahan.
> Potret utuh proyek: `status-proyek.md`. Jangan mengedit entri lama —
> file ini append-only.

---

## 2026-08-21c — Libur cabang tak mengikuti rolling · tiga orang salah dua arah

Ekky bertanya soal libur Maulid Nabi Selasa 25 Agu: *"apa benar fiturnya sudah
bekerja? kalau anak-anak tak masuk, tidak dihitung alpa?"* Jawabannya ya —
mesinnya sehat. Yang tidak sehat adalah **siapa yang dianggap terkena liburnya.**

### Mesinnya memang bekerja

`AttendanceReconciler::resolveDay()` — exception **menang** atas aturan harian
dan langsung mengembalikan "libur", jadi yang tak masuk tidak dihitung alpa.
Ada pula `hapusHariBasi()` yang membersihkan alpa lama kalau libur ditambahkan
*sesudah* rekonsiliasi terlanjur jalan. Logikanya benar.

### Yang salah: pencocokan pakai cabang ASAL, bukan cabang BERTUGAS

`resolveDay()` mencocokkan exception ke `users.raw_branch_id`. Reconciler
**tidak pernah** memanggil `positionBranchOn()` maupun `cabangSesiUntuk()` —
fitur rolling sudah tahu persis siapa di mana pada tanggal berapa, tapi
pencocokan liburnya tak pernah bertanya ke situ.

25 Agu: **Bintaro/Cinere/Kalideres tutup, Sunter/Pamulang buka.** Tiga orang
yang sedang rolling jadi salah — dan salahnya ke **dua arah berlawanan**:

| Orang | Asal | Bertugas | Akibat sekarang |
|---|---|---|---|
| **Verini** | Sunter (buka) | Cinere (**tutup**) | dinilai hari kerja → tak masuk = **ALPA** |
| **Della Raqisiah** | Cinere (tutup) | Sunter (**buka**) | dicatat **libur**, scan-nya dibuang |
| **Ramdan** | Kalideres (tutup) | Pamulang (**buka**) | sama |

"Kerjanya hilang" bukan bahasa berlebihan. Cabang `if ($libur)` di reconciler
tetap menulis baris `libur` **walaupun ada scan**; satu-satunya jalur yang
membuatnya "hadir" adalah **form lembur** — yang berarti membayar upah lembur
untuk hari kerja biasa.

### Keputusan Ekky: ikut rolling, dan tanpa form apa pun

Instingnya tepat, dan itu memang satu-satunya perbaikan yang dibutuhkan. Kalau
pencocokan mengikuti penempatan efektif, ketiganya beres sendiri: Verini libur,
Della & Ramdan hari kerja biasa. **Nol form.** Datanya sudah ada — ini menghapus
beban dari orang, bukan menambah.

⚠️ **Jebakan yang harus dibawa ke perbaikannya**: `$home` dipakai untuk **tiga
arti** yang kebetulan dilebur jadi satu variabel. Yang berubah hanya
`resolveDay()`. `isDateLocked()` dan `hapusHariBasi()` harus **tetap** memakai
cabang asal — menukarnya borongan membuat penguncian periode gaji ikut
berpindah mengikuti rolling, kerusakan yang jauh lebih sunyi.

### Temuan kedua: kalender libur TIDAK PERNAH tampil di layar mana pun

Ini yang membuat masalahnya tak terdeteksi sampai Ekky bertanya. **Kehadiran**
hanya membaca `att_work_rule_day` (pola mingguan); **Absensi Saya** memakai
`hariLiburTerjadwal()`, yang docblock-nya menyatakan terang-terangan bahwa
kalender libur *"tidak dilihat di sini"*.

Jadi `att_work_rule_exception` **ditulis satu layar dan dibaca satu pembaca
saja: mesin malam hari.** Nisa tak punya cara memverifikasi catatannya benar
selain menunggu harinya lewat. Kalau libur tampil di Kehadiran, cabang yang
belum tercatat akan **terlihat sendiri** — layar yang menampilkan keadaan itu
alat audit, bukan hiasan.

### Temuan ketiga

Baris `sys_user_position` id `a2821d77…` milik Verini: `effective_from` 15 Agu,
`effective_to` **14 Agu** — berakhir sebelum dimulai, lolos tanpa validasi. Tak
berbahaya sekarang, tapi begitu perbaikan di atas mendarat, **data penempatan
ikut menentukan gaji** — jadi validasinya jadi bagian perbaikan, bukan tambahan.

### ⚠️ RALAT — kesalahanku sendiri, dan bentuknya penting

Awalnya kulaporkan **"7 orang berisiko alpa"** karena Sunter & Pamulang tak
punya baris libur, dan kusarankan menggantinya dengan satu baris "Semua cabang
(nasional)". **Itu salah.** Ekky meralat: kedua cabang itu memang **BUKA** hari
itu; penutupan tiga cabang itu keputusan operasional yang disengaja.

Aku menyimpulkan "hari besar agama = libur nasional" dari sifat harinya, bukan
dari data. Bentuk kesalahan yang sama persis dengan `stdc.id` beberapa jam
sebelumnya: **menyimpulkan dari sifat sesuatu, bukan dari yang tercatat.**
Untungnya kusampaikan sebagai temuan untuk dikonfirmasi, bukan langsung
dieksekusi — kalau baris Nisa telanjur kuganti jadi nasional, **10 orang di dua
cabang yang seharusnya masuk akan mendapat libur palsu.**

Entri libur Nisa **benar apa adanya. Jangan diubah jadi nasional.**

### Yang dihasilkan

Tiga rencana + tiga prompt chat baru (§J, §K, §L di
`docs/spec/prompt-chat-baru.md`):

| Rencana | Isi |
|---|---|
| `rencana-libur-cabang-bertugas.md` | ⏰ **tenggat Selasa 25 Agu** — perbaikan di atas + tampilkan kalender libur + validasi penempatan |
| `rencana-konsultan-struktur.md` | role Konsultan + restrukturisasi Supervisor |
| `rencana-alur-persetujuan.md` | layar admin `att_approval_rule` + delegasi |

Kalau perbaikan libur tak sempat sebelum Selasa: **tidak apa-apa.** Cadangannya
murah — 3 baris `att_day` dikoreksi setelah lewat, oleh chat HUB, berjejak
audit. Yang penting kita tahu **sebelum** kejadian, bukan menemukannya di rekap
gaji.

---

## 2026-08-21b — Akun konsultan Haickal naik PROD · desain role Konsultan diputuskan

Ekky membuka arah baru: **konsultan** yang wajib absen tapi tak boleh punya
wewenang. Sebelum apa pun dibangun, mesin wewenangnya diaudit dulu — dan
ternyata separuh yang diminta **sudah berlaku sendiri**.

### Yang diaudit dulu: siapa sebenarnya yang boleh meng-ACC

Dugaan Ekky "bawahan cuma bisa di-ACC atasannya" **terkonfirmasi**, dan bukan
hardcode: tabel `att_approval_rule` (role → role), 13 baris. Content Creator
(Talitha) & Creative (Alif) → **Head Marketing (Yovan)**, persis seperti yang
dia sebut. Dicek juga ke kenyataan, bukan cuma ke konfigurasi: dari 116 baris
ACC nyata, pelakunya hanya Anisa (72), Irma (22), Dani (16), Ekky (5),
Yovanka (1). Tak ada yang menembus jalur.

**Tiga temuan yang mengubah rencana:**

1. **`sort_order` BUKAN lapis berjenjang.** `chainFor()` mengumpulkan semua
   approver-role satu frontier jadi **satu lapis**, dan `MAX_LEVELS` semua = 1.
   Jadi Assistant Dentist hari ini bisa di-ACC **Irma ATAU Anisa, siapa saja
   duluan** — bukan Irma dulu baru Anisa. Datanya cocok (Irma 22, Anisa 72).
   Konsekuensi untuk rencana Koordinator GS: kalau GS punya baris ke Koordinator
   GS **dan** masih punya baris ke Supervisor, Fajri **atau** Anisa sama-sama
   bisa ACC. Mau Fajri saja → baris Supervisor harus dicabut.

2. **Bagan organisasi TIDAK menggerakkan wewenang ACC.** `group_path.parent_id`
   cuma menggerakkan bagan + cakupan review Checklist Akreditasi;
   `att_approval_rule` yang menentukan ACC. Dua tabel terpisah — mengubah satu
   saja menghasilkan struktur yang salah **tanpa error apa pun**.

3. **Gerbang nama role justru menguntungkan.** Role bernama `Konsultan Finance`
   otomatis tak cocok dengan `RopService::ROLE_PENYETUJU = 'Finance'`, jadi ia
   tak bisa menyetujui ROP tanpa kita berbuat apa-apa. Sisi buruknya: sebagian
   besar menu Finance (Cuti, Aturan Kerja, Antrian ROP, Pengawasan) **juga**
   dijaga nama role — kloning menu bulat-bulat berujung **403**, kegagalan
   senyap yang justru diburu detektor Pusat Akun.

### Role `Consultant` ternyata sudah ada, dan sudah sesuai maunya

Hari (`hari@stdc.id`, konsultan marketing) & Rahmat (`rahmat@sweettreats.id`,
konsultan IT) sudah hidup di prod. Tanpa dikerjakan apa pun, mereka **sudah**
wajib absen (aturan *Fleksibel*), **sudah** nol wewenang ACC (nol baris sebagai
`approver_role_id`), dan **sudah** tak bisa mengajukan apa pun.

Yang terakhir itu bekerja lewat jalan yang menyesatkan:
`pastikanApexBukanSalahKonfigurasi()` **menolak mentah** kiriman dari role yang
tak dikenal grafik ACC, dengan pesan *"Alur persetujuan untuk jabatan ini belum
diatur… Hubungi admin"*. Sesuai kebijakan, tapi terbaca seperti **kerusakan**.
Utang: teksnya perlu dijujurkan jadi kebijakan, bukan galat.

### Akun Achmad Haickal — DIBUAT DI PROD

Konsultan finance baru. Sengaja ditaruh di role `Consultant` yang **sama persis**
dengan Hari & Rahmat (keputusan Ekky: role khusus menyusul setelah rencananya
jadi; sekarang cukup bisa login & absen).

| | |
|---|---|
| email | `haickal@sweettreats.id` |
| role | `Consultant` (identik Hari & Rahmat) |
| aturan kerja | Fleksibel, `effective_from` **2026-08-21** |
| sandi | sementara acak, `must_change_password=1` |

Dibuat lewat skrip yang meniru `UserManagement::store()` — model yang sama
dengan layar Pusat Akun — **kecuali** `effective_from` ditetapkan eksplisit,
karena layar itu selalu memakai `now()`. Itulah lubang yang dua kali melahirkan
alpa palsu; di sini ditutup dengan bertanya lebih dulu kapan orangnya mulai
kerja. Jejak audit **beratas-nama Muhamad Ikhsan**, bukan pelaku kosong.

**16 pemeriksaan lulus, 0 gagal** — termasuk yang tak bisa dibuktikan oleh
"tak ada error": `password_verify` terhadap sandi yang diserahkan, kemunculannya
di **query roster Kehadiran yang sama dengan layarnya**, dan `AuthGuard`
mengizinkan segmen `/akun` sehingga layar absen benar-benar terbuka.

### Keputusan Ekky yang tercatat

- **Konsultan Finance = Finance utuh, TERMASUK menyetujui ROP.** Larangan
  "tak boleh ACC" berlaku untuk **pengajuan orang** (cuti/lembur/izin), bukan
  untuk pekerjaan Finance sendiri. ⚠️ Ini **perubahan kode**, bukan config:
  `ROLE_PENYETUJU` harus jadi daftar, plus `CutiController::BOLEH`,
  `LembarGajiController::BOLEH`, `canSeeAllBranches()`.
- **"Koordinator Asdent" = `Coordinator Dentist` yang sudah ada** (typo Ekky).
  Tak ada role baru; Irma tetap.
- **Fajri = Alpajri Suhendar Al Qhusni** (`fajri@`, General Services) — bukan
  Mutiara Nurul Fajri di Front Office.
- Role baru yang benar-benar baru hanya **Koordinator GS**.

### Dua yang tadinya terbuka — sudah dijawab Ekky di hari yang sama

**`stdc.id` itu domain LAMA Sweet Treats Dental Clinic, bukan pihak luar.**
Sempat kucatat sebagai dugaan kebocoran karena `report/executive` melekat di
role `Consultant` — itu **kesimpulan yang salah, ditarik dari nama domain, bukan
dari data.** Akses Hari ke Laporan Penjualan → Executive **disengaja dan sah**;
jangan "diperbaiki". Pelajarannya sama tuanya dengan proyek ini: nama bukan
bukti — sudah pernah kena lewat Anisa ≠ Admin_Nisa dan dua orang bernama Fajri.

**`Coordinator Dentist` PINDAH ke bawah Supervisor.** Pengajuan Irma yang
tadinya ke Dani menjadi ke Anisa. Rencana lengkapnya ditulis hari itu juga di
`docs/rencana/rencana-konsultan-struktur.md` — termasuk satu risiko yang belum
kelihatan sebelumnya: memindahkan Fajri ke role `Koordinator GS` **berisiko
mematikan divisi Checklist Akreditasi-nya**, karena `sys_checklist_division`
mencocokkan nama divisi **persis** dengan nama role, dan tak ada divisi bernama
"Koordinator GS".

---

## 2026-08-21 — Absen HP: pencocokan WiFi pindah dari /64 ke /48 · LIVE PROD

Keluhan Ekky: "kena flag terus padahal kemarin sudah didaftarkan dan diperbaiki".
Ternyata dua hal berbeda, dan yang kedua jauh lebih besar dari keluhannya.

### Yang pertama: itu bukan flag, dan melesetnya 68 detik

Ekky ber-aturan `Fleksibel Kantor`, dan `AttendanceHpAbsen` melewati evaluasi
flag untuk orang fleksibel (`$flags = $fleksibel ? [] : ...`). `att_flag`
miliknya **kosong sama sekali**. Yang dia lihat adalah layar "WiFi tidak
dikenali — pilih cabangmu dulu ya", bukan flag.

Absennya 21 Agu 09.53.14; IP `180.252.169.218` didaftarkan 09.54.22. Meleset
**68 detik**. Barisnya bersih, tak ada spasi siluman — bukan bug.

### Yang kedua: rotasi Telkom lebih cepat daripada tangan manusia

Bintaro memakai **tujuh prefix /64 berbeda dalam 17 hari** — kira-kira tiap dua
hari. Tiap rotasi menghanguskan seluruh pendaftaran. Belajar-sendiri menutup
sebagian, tapi butuh 2 saksi dalam 72 jam, jadi absen pertama-kedua di tiap
prefix baru **selalu** gagal. Mekanismenya benar, cuma permanen ketinggalan.

Terukur di prod: **133 dari 474 absen HP sepanjang Agustus (28,1%) gagal cocok.**

Tapi ketujuh prefix Bintaro duduk di `2001:448a:2092::/48` yang SAMA — yang
berputar cuma 16 bit di dalam jatah pelanggan. Dan `WifiBelajarSendiri` sudah
memakai kekerabatan /48 sebagai pagar keamanannya. Jadi selama ini pencocokan
memakai /64 sementara pagarnya memakai /48: dua ukuran untuk satu pertanyaan.

### Yang dikerjakan — branch `fix/wifi-cocok-48`, DEV SAJA (prod belum)

`AttBranchIp::matchBranch()` kini membandingkan **/48**, dengan /64 yang persis
tetap diutamakan. Dua pagar dipasang bersamaan:

- **Veto IT menang atas kekerabatan.** Baris nonaktif yang `ip`-nya persis teks
  prefix bersih (`2001:448a:2092:d21::`) tetap menolak. Baris nonaktif BEREKOR
  bukan veto — pembedaan yang persis sama sudah dipakai `nilai()` sejak dulu.
  Tanpa pagar ini, kekerabatan /48 akan menghidupkan kembali prefix yang sudah
  dimatikan manusia, diam-diam.
- **Pendaftaran menolak /48 yang sudah dimiliki cabang lain**, di KEDUA pintu:
  `AbsensiBranchIpController::store()` dan `PengawasanController::daftarkanIp()`.
  Pintu kedua ketemu waktu audit — dan justru itu yang lebih rawan, dipegang SPV
  dari layar flag, bukan IT yang paham peta jaringan.

### Buktinya

- `att:wifi-uji` **15/15 lulus** (dari 10 kasus; K1/K2/K5/K8/K10 berubah arti,
  K11–K15 baru).
- **K1 adalah bukti utamanya**: kejadian nyata 11 Agu yang dulu mem-flag tujuh
  orang Bintaro sekarang mem-flag **nol**.
- Putar ulang **134 absen prod yang gagal cocok** lewat kode baru + tabel
  pendaftaran prod, di dalam transaksi yang dibatalkan (DB dev tak berubah):
  **70 jadi cocok (52,2%)**. IPv6: 57 tertolong, 6 tetap gagal — keenamnya HP
  pribadi/seluler, memang seharusnya gagal.
- Hanya **satu** absen berpindah cabang: Irma 13 Agu memilih Cinere padahal
  jaringannya Pamulang. Dia memang sedang di Pamulang (tercatat di docblock
  `WifiBelajarSendiri`) — jaringannya yang benar, orangnya yang salah pilih.
- Pra-terbang prod: tak ada /48 dipegang dua cabang, dan ketujuh baris nonaktif
  semuanya berekor sehingga tak satu pun berubah jadi veto. Nol regresi.

### Efek samping yang disengaja

Vonis **DAFTAR praktis mati** — prefix sekerabat sudah cocok sendiri, jadi
`pelajari()` bahkan tak dipanggil. Karena itulah satu-satunya jalur yang dulu
memberi tahu IT, **TAWARKAN kini benar-benar dikirim**. Sebelumnya vonis itu
cuma dikembalikan sebagai nilai dan **tak pernah dibaca siapa pun**, meski
docblock kelasnya menjanjikan "tawarkan sekali klik ke IT".

### Temuan audit yang berdiri sendiri dari perubahan ini

- Layar WiFi Cabang menulis **"3 orang"** padahal `AMBANG_ORANG` sudah
  diturunkan jadi 2 pada 18 Agu — salah sejak sebelum perubahan ini, tak pernah
  ikut diperbarui. Angkanya kini diambil dari konstantanya supaya tak bisa
  melenceng lagi. Kalimat "berganti seminggu sekali" juga dibetulkan (data prod:
  ~2 hari).
- **Saklar belajar-sendiri TIDAK lagi mengembalikan pencocokan ke /64.** Ia
  mengurus pendaftaran, bukan pencocokan. Dipatok di K11 supaya tak ditemukan
  sebagai kejutan waktu ada insiden.
- `last_seen_at` saat cocok lewat /48 distempel ke baris ber-id terkecil di /48
  itu — untuk Bintaro selalu `10d5::` yang sudah tak dipakai. Menyesatkan
  ringan bagi IT; belum diperbaiki.

### Yang sengaja TIDAK diperbaiki

**IPv4: 58 dari 70 kegagalan tetap gagal.** IPv4 tak punya padanan /48 yang
aman — melebarkannya ke /24 atau /16 berarti menerima satu wilayah ISP utuh.
Jalur IPv4 sengaja tak disentuh, dan itu dikunci lewat K14 (`180.252.169.219`
tak boleh cocok dengan `180.252.169.218` yang terdaftar).

### NAIK PROD 21 Agu 10.50 — `618ca57` (titik balik `b0c126e`)

Tanpa SQL, tanpa sentuhan `public/assets` (jadi tanpa rsync). `deploy.sh main`
bersih.

**Uji regresi di prod, bukan di dev.** Sebelum deploy, hasil pencocokan kode
LAMA direkam untuk **351 alamat unik** yang pernah dipakai absen sejak 1 Agu.
Sesudah deploy direkam ulang lalu dibandingkan baris per baris:

| | |
|---|---|
| tetap sama | 299 |
| **baru cocok** | **52** |
| hilang cocoknya | **0** |
| pindah cabang | **0** |

Cocok naik dari 265 → 317 dari 351 alamat. Nol kehilangan, nol perpindahan.

*(Jangan rancu dengan "1 pindah" pada putar-ulang di dev: yang itu diukur
terhadap pilihan manual orangnya — Irma — bukan terhadap kecocokan sebelumnya.)*

**Verifikasi hidup-hidup di prod**, semuanya lulus:

- rotasi karangan yang belum pernah ada di **kelima** cabang langsung dikenali
  (`2001:448a:2092:ffff::…` → Bintaro, dst) — itu inti perubahannya
- yang harus ditolak tetap ditolak: CGNAT Telkomsel, IPv4 tetangga
  `…169.219`, HP pribadi Reza, seluler Rokhmatun, dan `::ffff:` IPv4 menyamar
- pagar pendaftaran /48 bekerja di prod; nol /48 dipegang dua cabang
- halaman WiFi Cabang benar-benar ter-render (49.828 byte) dan teksnya sudah
  memuat angka dari konstanta, bukan "3 orang"
- **`APP_ENV` prod = `production`**, jadi kunci mati `att:wifi-uji` memang
  aktif di sana. Sengaja diperiksa: kalau nilainya bukan itu, perintah uji bisa
  MENGHAPUS absensi prod.


---

## 2026-08-20b — Petty cash Sunter & Cinere direkonsiliasi sampai rupiah

Sehari penuh menelusuri kenapa Verini melihat uang Sunter di layar Cinere.
Ujungnya bukan satu bug, tapi rantai empat kejadian.

### Rantainya

1. **Petty cash tak mengikuti penempatan.** Sebelum push Reza hari ini,
   `resolveBranchId()` membaca cabang dari SESI, yang hanya dihitung ulang
   sekali sehari. Verini (ditempatkan di Cinere sejak 13 Agu) mencatat
   pengeluaran 15 Agu ke **Sunter** — keterangannya sendiri berbunyi "kirim
   ledermix dari pamulang **ke cinere**". Della kebalikannya.
   Diperbaiki push Reza: `lockedBranchId()` membaca `positionBranchOn()`
   segar tiap permintaan.
2. **Penghapusan dompet menyapu data orang lain, senyap.** 15 Agu 19.50.33
   `destroySource()` menghapus 5 baris — 4 transfer Della + pengeluaran
   Verini Rp21.000 — tanpa mengisi `deleted_by`. Verini baru sadar 5 hari
   kemudian. **Sejak insiden itu Della berhenti mencatat ke sistem** dan
   lanjut memakai buku manual.
3. **Tombol Pindah Periode dipakai 1,5 jam setelah naik prod** (20 Agu
   13.37.02) pada pasangan Cinere↔Sunter. Karena Sunter sudah punya periode,
   ia **menukar**, bukan memindahkan searah — ikut menyeret saldo awal kedua
   periode. Tak ada jejak audit; ketahuan hanya dari cap `updated_at` dua
   periode yang sama persis sedetik.
4. **Top-up Rp970.940 (19 Agu) tak pernah masuk sistem** — tak ada pengajuan
   top-up untuk Sunter maupun Cinere sama sekali.

### Rekonsiliasinya cocok SAMPAI RUPIAH

Buku manual Della konsisten dari ujung ke ujung, dan sistem meleset persis
sebesar yang belum dicatat:

```
Sistem sebelum        1.310.500
8 pengeluaran hilang   −836.962
                      ──────────
                        473.538   = saldo akhir buku Della
```

Delapan pengeluaran yang SUDAH tercatat berjumlah Rp660.440 — sama persis
dengan delapan baris pertama sheet-nya, satu per satu. Dan top-up 970.940
itu angka ganjil justru karena **menggenapkan**: saldo Della tinggal
Rp29.060, ditambah 970.940 pas Rp1.000.000.

### Yang dikerjakan (semua ada jejak auditnya)

| Jam | Tindakan |
|---|---|
| 14.56 | 8 setoran Della (Rp4.737.000) dikembalikan Cinere → Sunter |
| 15.09 | Pengeluaran Verini Rp21.000 dipulihkan → Cinere |
| 15.42 | 4 transfer Della dipulihkan (korban insiden yang sama) |
| 15.46 | Saldo awal Cinere dikoreksi Rp1.033.170 → Rp83.300 |
| 15.57 | Top-up Rp970.940 dicatat, ref bank `1908/FTSCY/WS95271` |
| 16.06 | 8 pengeluaran dari buku Della dicatat (Rp836.962) |

**Hasil**: Sunter kas kerja **Rp473.538** (= bukunya). Cinere **Rp14.800**
(= hitungan Verini: 83.300 − 21.000 − 47.500).

### Keputusan yang diambil sadar

* **Pencatat diisi Admin_Ekky, bukan Della/Verini.** Yang mengetik memang
  bukan mereka; menaruh nama mereka akan membuat jejaknya berbohong. Nama
  mereka ada di keterangan sebagai yang mengeluarkan uang.
* **Pengajuan top-up Verini Rp985.200 TIDAK dibuat** — itu permintaan uang
  atas namanya yang lewat persetujuan; harus dia sendiri.
* **Bukti fisik belum dilampirkan** untuk yang dicatat menyusul; ditulis
  terang-terangan di keterangan & audit.
* **Pembagian dompet direkonstruksi dari tanggal** (15 Agu = BSI, 16 Agu ke
  atas = BCA) karena buku Della tak memisahkan dompet. Yang terverifikasi
  persis adalah TOTALNYA.

### Perbaikan kode: penghapusan dompet tak lagi senyap (`b0c126e`, prod)

`destroySource()` kini mengisi `deleted_by`, menghitung dulu berapa baris &
milik siapa, menulis `AuditLog`, dan menampilkan hasilnya **merah** dengan
nama pemiliknya bila menyentuh data orang lain. Teks konfirmasi menyebut
terus terang bahwa transaksi orang lain ikut terhapus.

Diuji lewat HTTP di dev: `deleted_by` terisi, audit menyebut "2 di antaranya
milik Afrina Apriliani", pesan tampil `alert-danger`.

⚠️ **Masih ada 27 baris terhapus tanpa nama** — semuanya Bintaro, 15 kejadian
Juni–Agustus, beberapa bernilai jutaan. Tak bisa diatribusikan surut.

### ⚠️ Agustus Sunter SENGAJA parsial (keputusan Ekky)

Sistem memuat 12 Agu ke atas saja. Saldo akhir BENAR (Rp473.538), tapi isi
1-11 Agu tidak ada — dan itu diputuskan sadar, bukan terlewat.

```
saldo awal kurang catat      −126.000   (tercatat 1jt, sebenarnya 874.000)
2 top-up tak tercatat      +1.675.455   (906.400 8 Agu · 769.055 13 Agu)
22 pengeluaran tak tercatat −1.549.455
                           ───────────
                                    0   ← saling meniadakan
```

Itu sebabnya saldo akhirnya tetap tepat meski bulannya bolong.

**KONSEKUENSI: laporan pengeluaran Sunter Agustus kurang catat ~Rp1,55 juta.**
Jangan dipakai untuk rekap per kategori tanpa membaca catatan ini. Sumbernya
lengkap di buku manual Della; bisa dilengkapi kapan saja (±25 baris).
Catatan yang sama ditulis di `audit_log` supaya terbaca dari dalam aplikasi.

### Sisa

* **`movePeriod` masih tanpa jejak audit** dan masih menukar diam-diam kalau
  periode tujuan berisi. Perbaikan yang sama seperti `destroySource` belum
  dikerjakan — ini yang paling layak berikutnya.
* Verini perlu mengajukan top-up Rp985.200 sendiri.
* BSI Sunter menyisakan Rp58.190; buku Della tak memisahkan dompet, jadi
  angka itu sisa rekonstruksi, bukan uang yang hilang.

---

## 2026-08-20 — Push Reza (petty cash dompet-holder) NAIK PROD + 1 tambalan

**Prod = main = `260ddef`.** Isinya push Reza `fix/pettycash-update` +
satu perbaikan SQL dariku.

### Isi push Reza

Status pinjaman per-baris (LUNAS/SEBAGIAN/BELUM lewat `settles_transaction_id`
+ `borrower_name`) · sub-dompet per operator untuk rolling (`holder_user_id`,
NULL = kas cabang) · chip "dicatat oleh" saat >1 pencatat · **modal pindah
periode antar cabang** · modal rincian pengajuan top-up.

Kualitasnya naik lagi. Runbook `fix_pettycash_cinere_to_sunter.sql` layak
dicontoh: verifikasi `SELECT` dulu, guard di `WHERE` supaya tak bisa kena baris
salah, blok ROLLBACK, dan urutan wajib yang menjelaskan **kenapa** SQL harus
duluan sebelum rolling dibuat. `movePeriod` juga rapi — Super Admin + periode
tertutup ditolak di kedua sisi + satu transaksi + sentinel `period_year=9999`
untuk menghindari tabrakan unique saat menukar dua periode.

### Yang kutambal

`2026_08_19_pettycash_holder_wallet.sql` memakai `ALTER` polos sementara **dua
saudaranya** dijaga lewat `INFORMATION_SCHEMA`. Dijalankan dua kali:

    ERROR 1060 (42S21): Duplicate column name 'holder_user_id'

Penting karena SQL prod dijalankan TANGAN — sekali ragu lalu mengulang,
prosesnya berhenti dan mudah dikira ada yang rusak. Dan itu **bukan hipotetis**:
saat SQL dijalankan di prod, ketiga berkas melaporkan "already exists" di jalan
PERTAMA — Reza sudah menerapkannya manual duluan. Versi aslinya akan berhenti
dengan galat di situ.

### ⚠️ Kesalahanku: dev mundur 44 commit

Cabang Reza bercabang dari `283dade` (14 Agu) — **44 commit di belakang main**.
Aku men-`reset --hard` dev ke cabangnya, jadi dev kehilangan seluruh WiFi
belajar-sendiri (berkasnya tak ada), `att:gelar-akun`, perbaikan RBAC kategori,
dan semua docs. Dev tidak rusak — cuma jadi snapshot lama yang konsisten —
tapi tetap salah, dan kalau ada yang menguji di sana hasilnya menyesatkan.

Dibetulkan dengan **menggabung, bukan mengganti**: `integrasi/dev-2026-08-20` =
`main` + cabang Reza (nol konflik). Dev dipulihkan, `att:wifi-uji` kembali
10/10.

**Pelajaran: sebelum men-deploy cabang orang lain ke dev, periksa dulu
`git log <cabang>..origin/main`.** Kalau tidak kosong, cabang itu tertinggal —
gabungkan ke branch integrasi, jangan checkout langsung.

### ⚠️ Utang yang belum dibayar

**Runbook Cinere → Sunter BELUM dijalankan.** Ia berisi `<placeholder>` dan
harus dijalankan bertahap sambil membaca hasil tiap langkah — tak boleh dipipe
ke `mysql`. Butuh koordinasi Ekky: minta Della berhenti mencatat → STEP 1
verifikasi → STEP 2 eksekusi → STEP 3 validasi → **baru** buat rolling
Della→Sunter → Super Admin serahkan saldo ke dompet Della.

**Pemindahan periode tak mencatat SIAPA.** `petty_cash_periods` cuma punya
`created_at`/`updated_at`, dan `PettyCashController` tak punya satu pun
`AuditLog::record` — sementara Absensi, Cuti, dan Akses mencatat. Jadi
memindahkan sebulan uang antar cabang tercatat *kapan*, tak pernah *oleh siapa*.
Digabung dengan kewenangan Super Admin bebas mengedit transaksi seumur apa pun
(ACC 15 Agu) dan hanya ada SATU Super Admin di prod, ini pergeseran wewenang
yang layak diputuskan sadar — bukan cacat kode.

### Verifikasi

Dev: `att:wifi-uji` 10/10 · 4 halaman kunci 200 tanpa galat · gerbang
pindah-periode **403** untuk Front Office (yang memang bisa membuka
`/pettycash`, jadi RBAC meloloskannya sampai ke controller — uji yang sah,
bukan pentalan 302 yang menipu) · ketiga SQL idempoten dua kali jalan.

Prod: rute terdaftar · halaman sehat · **rsync aset dijalankan** —
`components.sweettreats.css` md5-nya berbeda sebelum rsync, sama sesudahnya ·
nol galat di log.

---

## 2026-08-18 — Rotasi Telkom menyapu 5 cabang · ambang saksi WiFi 3 → 2

**Prod = main = `92c0c66`.**

### Yang terjadi

Telkom memutar prefix IPv6 di **semua cabang** sekitar 18 Agu. Dampaknya
terukur: **16 orang berbeda ke-flag dalam sehari** (14–16 Agu rata-rata 6–8).
Flag menggantung menumpuk jadi 50 (37 baru + 13 dicek).

### Belajar-sendiri AKHIRNYA memicu — 3 kali

Pertama kali sejak fitur ini dibuat:

| Cabang | Kapan | Saksinya |
|---|---|---|
| Cinere `2033:e6c` | 15 Agu | Anggita, Wahyudin, **Verini** |
| Pamulang `2020:d63e` | 18 Agu | Alviona, Mutiara, **Ramdan** |
| Bintaro `2092:d21` | 18 Agu | Afrina, Asih, Alpajri |

**Dua dari tiga hanya mungkin karena perbaikan cabang-efektif 15 Agu** — Verini
dan Ramdan sama-sama orang yang *ditempatkan*, yang sebelum itu tak pernah
dihitung. Pamulang cuma punya 2 staf asal; tanpa Ramdan terhitung, cabang itu
tak akan pernah bisa mendaftar sendiri. Perbaikan yang kemarin cuma teori,
hari ini berbuah.

### Tapi dua mentok di 2-dari-3 → didaftarkan tangan

`2001:448a:90b0:58::` (Kalideres, 7 flag) dan `2001:448a:2092:270e::` (Bintaro
jalur-2, 5 flag). Keduanya sekerabat /48 — mesin sudah yakin, cuma kurang satu
saksi. Sesudah didaftarkan, **semua** alamat IPv6 yang tadi merah langsung cocok.

### Ambang diturunkan 3 → 2 (ACC Ekky)

Akar masalahnya bukan sial, tapi aritmetika. Calon saksi = staf asal ber-aturan
baku:

| Cabang | Calon saksi | Ambang 3 |
|---|---|---|
| Pamulang | 2 | **tak pernah cukup** sendirian |
| Cinere | 3 | butuh **100%** staf dalam 72 jam |
| Kalideres | 4 | butuh 3 dari 4 |
| Bintaro · Sunter | 5 | lega |

Untuk **tiga dari lima cabang**, ambangnya sama atau lebih tinggi dari jumlah
orangnya — mesin dirancang gagal di sana, dan sisanya jatuh ke tangan manusia
tiap kali Telkom memutar prefix (~mingguan). Itu yang bikin Ekky bolak-balik
mendaftarkan.

⚠️ **Yang menjaga keamanan bukan angka itu**, melainkan pagar kekerabatan /48:
tiap cabang punya blok Telkom sendiri, jadi dua orang di kafe tak akan pernah
lolos — blok kafe bukan blok klinik. **Pagar itu tidak diturunkan**, dan batas
"satu orang tak pernah cukup" juga tetap.

**Kasus uji: 4 dari 10 berubah, semuanya lulus (10/10 di dev).**
* `K1` pemicunya kini Firda (sah ke-2). Penjaga §6 "fleksibel tak menguatkan"
  PINDAH ke sini — kalau Talitha ikut dihitung, pemicunya jadi kejadiannya.
* `K2` **berubah arti**: dulu membuktikan "2 belum cukup", kini membuktikan
  bukti boleh **melintasi hari** di dalam jendela 72 jam (Firda malam 11/8,
  Alpajri pagi 12/8).
* `K7` ditambah assertion terpenting: di orang ke-2 (ambang tercapai) vonisnya
  **TAWARKAN**, bukan DAFTAR — inilah yang membuktikan pagar /48 berdiri
  sendiri dari ambang orang.
* `K10` prasyarat bergeser ke kejadian ke-2.

Diperiksa sebelum naik: sesudah dua pendaftaran manual, **sisa jaringan yang
belum cocok semuanya IPv4** — yang tak pernah lewat pintu otomatis, ambang
berapa pun. Jadi tak ada yang mengendap menunggu mendaftar sendiri.

### Kalideres: akunnya terpakai

Cici & Noval absen 16 & 18 Agu. Rivanti baru login pertama 18 Agu 10.58 lalu
absen semenit kemudian — alpa 16 Agu-nya karena belum memegang akun, bukan
bolos. 17 Agu kosong karena Senin (klinik tutup).

⚠️ **Sisa**: 12 flag dari kedua prefix itu sekarang sudah jelas sebabnya dan
tinggal dibereskan — kerjaan chat beres-beres flag. Delapan alamat IPv4 masih
merah dan memang tak akan pernah mendaftar sendiri.

---

## 2026-08-15 — KALIDERES digelar: KELIMA CABANG LENGKAP + koreksi soal penjadwal

**Prod = main = `c5cee46`.**

### Kalideres — cabang terakhir

3 akun baru: **Cici Nurjanah** (Asdent, sisa cuti 7) · **Rivanti Nadiana** (FO,
sisa 4) · **Noval Fijriansyah** (GS, karyawan baru — menggantikan Ramdan, yang
kini ditempatkan di Pamulang). Angka cuti Cici & Rivanti cocok persis dengan
sheet HR dan dengan dev.

Dengan ini **kelima cabang lengkap di prod**: Bintaro 5 · Sunter 5 · Kalideres 4 ·
Cinere 3 · Pamulang 2 = 19 staf operasional.

Aturan kerja dipasang mulai **16 Agu**, bukan hari pembuatan akun — demo hari ini
perkenalan, mereka mulai dinilai besok. Memasang hari ini akan mengulang 5 alpa
palsu gelombang Sunter. Perkakasnya sekarang mencetak tanggal itu di layar
sebelum menulis apa pun, jadi salahnya kelihatan sebelum terjadi.

**Lubang yang ketahuan gara-gara Noval**: `Carbon::parse(null)` diam-diam
menghasilkan HARI INI, jadi karyawan baru tanpa tanggal kontrak akan terlihat
punya kontrak yang berakhir setahun lagi — dan `resetKontrakKedaluwarsa()` kelak
memberinya jatah 12 tanpa dasar. Sekarang kontrak null tetap null dan periodenya
**dorman**, seperti yang memang dirancang.

### ⚠️ KOREKSI: penjadwal prod TERNYATA JALAN

Entri 14 Agu menyimpulkan `schedule:run` sepertinya **tidak** berjalan di prod.
**Itu keliru.** Buktinya tegas: baris `sys_checklist_submission` berstatus
`missed` bercap **00:10:01–00:10:02 tiap hari** selama sepuluh hari berturut
(5–15 Agu). Itu cron, bukan manusia.

Inferensi 14 Agu cacat: aku menyimpulkan dari `att_day.computed_at` yang seragam,
padahal satu `att:reconcile` rentang-lebar memang menimpa seluruh jendela — jadi
capnya seragam entah cron jalan atau tidak. Keberatan itu kutulis sendiri lalu
kuabaikan. **Reza yang benar.**

Pelajaran yang bisa dipakai lagi: untuk membuktikan cron jalan, cari tabel yang
HANYA disentuh cron dan yang capnya TIDAK pernah ditimpa proses manual.

### Push Reza — checklist terkunci setelah "buka kembali" di-ACC

**KOREKSI: sudah PROD sejak `c5cee46`, bukan "dev saja".** Perbaikannya ikut naik
saat deploy Noval, karena aku mendorong seluruh ujung cabang integrasi ke main —
cabang itu sudah memuat merge-nya. Aku sempat melaporkan "belum prod"; keliru.
Kode itu jadi hidup tanpa keputusan sadar. **Pelajaran: `push integrasi:main`
membawa SEMUA yang ada di cabang itu, bukan cuma commit terakhir — periksa
`git log origin/main..HEAD` sebelum mendorong.**

Diaudit sesudahnya dan hasilnya bersih (lihat entri di bawah). `checklist:mark-missed` mengecap tanggal
lampau `missed`, sedangkan `isEditable()` hanya true untuk draft/rejected — jadi
atasan meng-approve, banner muncul, tapi checklist tetap terkunci tanpa tombol
Kirim. **Hanya kelihatan di prod** karena di lokal/dev cron tak jalan.

Diperbaiki dua lapis: jalur baca mengembalikan `missed`→`draft` saat grant aktif,
dan jalur cron melewati orang yang punya grant aktif. Ditelaah: gerbangnya benar —
`activeDailyGrant()` menyaring `status=approved` DAN `fulfilled_at IS NULL`, jadi
tak ada jalan membuka kunci tanpa persetujuan.

Ini melahirkan aturan yang lebih umum: **kalau membangun fitur yang membuka
kembali sesuatu di masa lampau, periksa apakah ada cron yang menutupnya lagi.**

### Audit ulang prod sesudah Reza uji di dev (15 Agu sore) — BERSIH

Sembilan pemeriksaan, semuanya lolos: tak ada aturan kerja aktif ganda · tak ada
periode cuti berjalan ganda · tak ada baris rolling lumpuh · tak ada selisih jam
scan vs bukti · tak ada staf bercabang tanpa baris posisi. Alpa cuma Alviona
(nyata). Absen-tanpa-penilaian cuma Anisa 5 Agu (historis). Tanpa aturan kerja
cuma "Executive Pamulang" (akun peran, bukan orang).

**Perbaikan Reza terbukti bekerja di prod, dari pemakaian NYATA** — bukan uji
karangan. Tiga baris `missed` berubah jadi `draft` saat orangnya membuka halaman
pukul 12.54–12.56 (Firda 6 & 12 Agu, Verini 14 Agu). Yang dibuka sebelum deploy
(11.0x) masih `missed` — konsisten, karena perbaikannya di jalur baca dan
menyembuhkan saat halaman dibuka.

**Jalur cron diuji kering**: `checklist:mark-missed --dry-run` → **0 draft→missed**.
Artinya baris `draft` milik Verini (14 Agu, grant aktif) TIDAK akan dicap missed
nanti malam. Tanpa perbaikan ini, cron akan mengunci ulang checklist yang baru
dibuka atasannya siang tadi. Mingguan juga 0.

⚠️ **Catatan yang belum jadi masalah tapi perlu diketahui**: grant "buka kembali"
**tak punya kedaluwarsa**. 13 grant disetujui, **nol** terpakai. Selama grant
menggantung, cron melewati orang itu untuk tanggal itu **selamanya** — jadi hari
tersebut tak pernah masuk hitungan "tidak diisi". Wajar sekarang (fiturnya baru
jalan hari ini), tapi kalau grant menumpuk tak terpakai, angka kepatuhan
checklist diam-diam jadi lebih bagus dari kenyataannya.

⚠️ **Galat koneksi DB prod menyembur**: 43 galat hari ini, **semuanya dalam dua
menit** (12.37–12.38), pada query `group_access` — query terpanas aplikasi
(AuthGuard). 14 Agu: 66. Pola semburan, bukan rembesan; bukan disebabkan
perubahan kode. Sudah lama ada, layak diselidiki terpisah.

### Temuan lain

**Alviona alpa 14 Agu — nyata, bukan buatan sistem.** Dia satu-satunya staf
berjadwal baku yang tak absen hari itu, dan tak punya pengajuan izin/sakit/cuti
apa pun. Sejak akunnya dibuat dia baru sekali absen (13 Agu, saat demo). Perlu
ditanyakan Anisa — bukan ditambal dari sini.

---

## 2026-08-14 — NAIK PROD: WiFi cabang efektif + kategori petty cash Reza + 6 temuan audit

**Prod = main = `fc70d2a`.** Isinya push Reza + tiga perbaikan, plus pembersihan
data hasil audit.

### Temuan audit prod (dikerjakan setelah deploy dev, sebelum naik prod)

**a. Rahmat tak pernah muncul di Kehadiran — dan sebabnya sistemik.** Dia absen
14 Agu 11.19 (foto ada, WiFi cocok) tapi tak muncul di layar mana pun. Sebab:
tak punya `att_user_rule`, dan layar Kehadiran menyusun rosternya DARI tabel
itu. Akarnya role `Consultant` tak punya `att_default_rule_id` — lubang yang
sama dengan Alpajri, yang kukira sudah tertutup 13 Agu (ternyata cuma 3 role
operasional yang kuurus). Kini: Rahmat dapat `Fleksibel` (ACC Ekky), role
Consultant dapat bawaan, Hari dipindah ke Consultant.

**b. LIMA ALPA PALSU — dan aku penyebabnya.** Della Raqisiah, Melani, Putri,
Rokhmatun, Tito tercatat `alpa` 13 Agu. Akun mereka kubuat 13 Agu sore dengan
aturan berlaku hari itu juga, padahal mereka baru mulai absen 14 Agu. Ini
PERSIS jebakan Alpajri yang peringatannya kutulis sendiri — dan tetap kumasuki,
karena yang kutulis cuma komentar, bukan perbaikan pada perkakasnya. Sudah
dibereskan (effective_from dimajukan ke absen pertama → dihitung ulang → 5
baris basi gugur). Prod kini **nol alpa**. `att:gelar-akun` sekarang punya
`--mulai=` dan selalu mencetak tanggalnya sebelum menulis.

**c. Satu jam absen salah 4 jam.** `att_scan.scanned_at` Rahmat tertulis
07.19.46 padahal kejadiannya 11.19.46 — dan `att_day` membaca kolom itu, jadi
Kehadiran menampilkan jam keliru sementara Pengawasan menampilkan yang benar.
Waktu asli dibuktikan dari kolom `hash` baris itu sendiri (sha1 atas jam
kejadian) + nama berkas fotonya. Data sudah dibetulkan & dihitung ulang.
⚠️ **MEKANISMENYA BELUM TERJELASKAN**: `scanned_at` dan `taken_at` ditulis dari
satu variabel `$now` yang sama dalam satu transaksi, dan `$now` tak pernah
dimutasi (`workDate()` pakai `->copy()`). Satu kejadian dari 193 scan. Kalau
menimpa orang ber-aturan `baku`, geseran 4 jam bisa membalik tepat-waktu jadi
telat. **Perlu ditengok lagi** — bukti lengkapnya ada di baris `att_scan` 193.

**d. Bersih**: tak ada aturan kerja ganda · tak ada baris rolling lumpuh · tak
ada kategori transaksi yatim · jam scan vs bukti kini nol selisih.

**e. Masih terbuka**: role `Executive` (2 org) & `Branch Executive` (1 org)
belum punya aturan bawaan — akun BARU di sana akan lenyap seperti Rahmat.
Akun "Executive Pamulang" tak punya aturan kerja (tampaknya akun peran, bukan
orang). Anisa 5 Agu punya absen tanpa `att_day`. Flag: 15 baru, 7 dicek.

**f. Kewenangan Super Admin petty cash: DISETUJUI Ekky** — jendela edit 8 jam
untuk user biasa, Super Admin bebas tanpa batas.

---

### Isi gerbongnya

**1. WiFi belajar-sendiri: saksi ditimbang pakai cabang EFEKTIF.**
Penilainya membandingkan `users.raw_branch_id` dengan cabang yang dipilih,
sehingga orang yang sedang DITEMPATKAN di suatu cabang tak pernah terhitung —
padahal dialah saksi paling sahih (penugasannya tertulis, bertanggal, berjejak
konfirmasi). Ongkosnya terukur: **dua cabang tak akan pernah bisa mendaftarkan
jaringannya sendiri**, karena staf asalnya lebih sedikit dari ambang 3 —
Pamulang punya 2, Kalideres punya 1.

Dua kejadian nyata, keduanya berhenti di 2 dari 3:
* 13 Agu 14.49–14.51, /64 baru Pamulang — yang hilang: **Ramdan**
* 14 Agu 09.02–09.16, /64 baru Cinere — yang hilang: **Verini**

Penempatan ditimbang pada tanggal bukti masing-masing, bukan hari ini (jendela
72 jam bisa memuat penempatan yang mulai/berakhir di tengahnya). Pagar lain tak
disentuh: /48 tetap harus sekerabat, orang fleksibel tetap tak menguatkan.

Diuji: `att:wifi-uji` **10/10 lulus** (tak ada regresi), plus pemutaran ulang
kejadian Cinere pagi ini — Verini kini muncul di daftar pendukung, orang ketiga
memicu DAFTAR.

**2. Push Reza — kategori pengeluaran dinamis + jendela edit.** Kategori pindah
dari konstanta ke tabel `petty_cash_categories`, dikelola Super Admin dari
Kelola Wallet. Kualitasnya jelas naik dari audit sebelumnya: SQL idempoten, seed
cocok persis dengan konstanta lama, gerbang Super Admin di tiap aksi, kategori
terpakai tak bisa dihapus, dan **ada fallback kalau tabelnya belum dibuat** —
jadi kode boleh naik sebelum SQL. Pelajaran "migrate dulu baru SQL" diterapkan.

**3. Tapi fiturnya mati total — dan ini yang paling berharga dari review.**
Keempat aksi CRUD dipasang di segmen `petty-cash-categories`, sementara
`AuthGuard` mencocokkan SEGMEN-1 dengan node `access_path`. Tak ada node
berlink itu dan segmennya tak masuk `SKIP_RBAC`, jadi **tiap POST dipantulkan
ke landing page sebelum menyentuh controller — untuk semua orang, Super Admin
sekalipun**. Halaman induknya (`fund-sources`) selamat justru karena sudah lebih
dulu ada di `SKIP_RBAC`; fitur barunya mewarisi asumsinya tanpa mewarisi
barisnya.

Gejalanya menipu: bukan 403, melainkan 302 ke beranda — tombolnya tampak
sekadar tak berbuat apa-apa, gampang dikira soal tampilan.

Dibuktikan di dev sebelum & sesudah: POST Super Admin semula 302 ke `/setgroup`
dengan nol baris tercipta; sesudah perbaikan kategori benar-benar dibuat.
Karena segmen itu kini melewati RBAC, gerbang controller-nya diuji hidup-hidup:
user non-Super-Admin **403**, nol tercipta, kategori sistem `pinjaman` utuh.

**Keputusan yang menunggu Ekky** (kebijakan, bukan bug): jendela edit turun
24 jam → 8 jam untuk user biasa, DAN Super Admin kini bebas tanpa batas — dulu
kunci berlaku untuk semua. Super Admin di prod cuma satu (Admin_Reza), jadi
setiap koreksi lewat 8 jam harus lewat dia. Jejaknya tetap ada (`edited_at`,
`updated_by`, layar Riwayat).

**Sisi data (prod, di luar gerbong):** WiFi Kalideres `2001:448a:90b0:48a1::`
didaftarkan (rotasi dari `ebf`) — Rizka langsung cocok. Sunter mulai terpakai:
Putri & Tito absen dan cocok otomatis.

**Catatan koreksi:** sempat saya duga IPv4 Pamulang `180.251.180.38` salah
daftar karena Asih (staf Bintaro) ikut cocok ke Pamulang. Keliru — fotonya
menunjukkan dia memang sedang di Pamulang, dan sistem menandainya lintas cabang
Bintaro → Pamulang dengan benar. Pelajarannya: selfie itu kebenaran dasar;
jangan menyimpulkan jaringan salah sebelum melihat fotonya.

---

## 2026-08-13 — Sunter digelar ke PROD (4 akun) + Irma dapat Pengawasan Absen

Prod sekarang memuat **4 dari 5 cabang**: ABG/Office, Bintaro, Cinere, Pamulang,
dan Sunter. Tinggal **Kalideres** — menunggu sheet HR & info penempatannya.

**Sunter (4 akun baru)**: Putri Syafira (Asdent, sisa cuti 7) · Rokhmatun
(Asdent, 0) · Tito Muhammad Taqiyuddin (GS, 0) · Melani Safrida (FO, 0). Verini
sudah lebih dulu digelar kemarin karena penempatannya di Cinere. Saldo 0 pada
ketiga orang itu berarti **belum berhak** — kontrak pertama belum genap sekali
perpanjangan — bukan cutinya habis; karena itu ditulis jatah 0 tanpa baris
penyesuaian, supaya tak terbaca seolah haknya sudah terpakai.

Semua bersandi sementara `sweettreats2026` dan wajib ganti saat login pertama.
Dibuktikan lewat login HTTP sungguhan: masuk → langsung dipagari halaman Ganti
Password, tanpa galat.

**Kontrak Rokhmatun habis 11 Agu 2026** — sudah lewat saat akunnya dibuat.
Sengaja dimasukkan apa adanya supaya perpanjangannya terlihat, bukan tenggelam.
**Sore itu juga HR mengonfirmasi sudah diperpanjang** → 11 Agu 2027, jatah 12.
Dikerjakan lewat jalur resmi (`CutiService::mulaiKontrakBaru`, sama dengan
tombol **Kontrak Baru** di Absensi → Cuti): periode lama ditutup, periode baru
dibuka 12 Agu 2026 – 11 Agu 2027 saldo 12. Riwayat kontrak pertamanya tetap
utuh, tidak ditimpa. Dia satu-satunya yang kontraknya lewat tanggal.

⚠️ **Penjadwal harian sepertinya TIDAK berjalan di prod.** `resetKontrakKedaluwarsa()`
terdaftar tiap 01:20 dan seharusnya memperbarui kontrak kedaluwarsa sendiri,
tapi seluruh `att_day` bercap satu waktu yang sama (hitung-ulang manual), bukan
pola harian. `crontab` tak bisa dibaca dari shell (dikelola hPanel), jadi belum
terbukti hitam-putih — **perlu dipastikan**. Kalau memang mati, yang ikut mati
diam-diam: reset kontrak cuti, `att:reconcile` harian, `att:flag-sweep`,
`absensi:selesaikan-l2`, sapuan ROP, dan sinkron Omnicare.

**Satu ranjau ditutup di perkakasnya.** `att:gelar-akun --sandi=` dulu menyetel
ulang sandi SEMUA orang di daftar, termasuk yang sudah ada. Karena perintah ini
dijalankan ulang tiap gelombang cabang sementara daftarnya makin panjang,
menggelar Kalideres nanti akan mencabut sandi pribadi seluruh Cinere, Pamulang,
dan Sunter sekaligus — mereka terkunci di luar tanpa galat, dan satu-satunya
petunjuk cuma lewat di layar orang yang menjalankan perintahnya. Sekarang
penyetelan ulang **melewati orang yang sudah mengganti sandinya sendiri**.
Nyaris kejadian hari ini: Alviona, Mutiara, dan Ramdan sudah ganti sandi
beberapa jam sebelum gelombang Sunter dijalankan.

**Irma (Coordinator Dentist) dapat Pengawasan Absen** — setara Nissa
(`Admin_Nisa`, Admin Backup) di sisi pengawasan. Diuji di dev dengan login
sungguhan: `/pengawasan` terbuka, `/absensi/kehadiran` dipental. Kontrol kedua
itu yang penting — membuktikan haknya berhenti tepat di batas yang diminta.
Node admin absensi Nissa yang lain (Kehadiran, Cuti, WiFi Cabang, Upload Data)
sengaja **tidak** ikut: itu wewenang yang tak pernah diputuskan siapa pun.

Dua hal yang perlu diketahui pemakainya: halaman Pengawasan Absen **tak
menyaring per cabang** (Irma yang ABG melihat seluruh klinik, sama seperti
Nissa), dan haknya menempel di **role** — sekarang isinya Irma seorang, tapi
Coordinator Dentist berikutnya akan mewarisinya.

**Catatan pemeriksaan**: verifikasi sisa cuti sempat salah baca (Putri terlihat
17, padahal 7). `CutiPeriode::sisa()` menghitung `saldo_awal − Σ mutasi.hari`;
baris penyesuaian bernilai positif berarti MEMOTONG. Datanya benar sejak awal —
query pemeriksanya yang keliru arah.

Prod: `97b14bd`.

---

## 2026-08-12 — WiFi Cabang belajar sendiri: DIBANGUN, live di DEV (belum prod)

Branch `feature/wifi-belajar-sendiri`. Nomor IPv6 dari Telkom berputar kira-kira
mingguan; tiap rotasi seisi cabang kena flag "absen dari luar WiFi" sampai ada
manusia yang mendaftarkan nomor barunya — 11 Agu lima orang Bintaro ke-flag
dalam sejam. Ini masalah **kebisingan**, bukan integritas: tak ada yang gagal
absen (WiFi cuma saksi). Ongkosnya waktu review Anisa, dan flag jadi terbiasa
merah sehingga kejanggalan asli tenggelam.

Sekarang jaringan yang nomornya baru berganti **mendaftarkan dirinya sendiri**
begitu 3 orang cabang itu terbukti memakainya dalam 72 jam — asalkan blok
alamatnya sekerabat dengan jalur cabang yang sudah terdaftar. Pemicunya saat
**absen**, bukan saat review: itu yang membuat rekan yang datang sesudahnya
langsung cocok dan tak ikut ke-flag. Tiga orang pertama tetap ke-flag.

**Pagarnya**: satu orang tak pernah cukup · jaringan seluler tak pernah ikut
(hanya IPv6 yang lewat pintu ini) · blok `/48` harus sekerabat, kalau tidak
cuma ditawarkan ke IT · orang berjadwal fleksibel tak menguatkan apa pun ·
baris hasilnya berlabel "perlu ditinjau" dan disorot di halaman WiFi Cabang ·
IT dapat notifikasi tiap kejadian · ada saklar mati yang bisa diklik admin.

### Diuji dengan memutar ulang kejadian nyata, bukan dengan membuka halaman

Delapan kasus dijalankan lewat `AttendanceHpAbsen::absen()` — jalur kode yang
sama persis dengan HP karyawan — memakai salinan baris prod 5–12 Agu. Semua
lulus. Rotasi `10d5` mendaftar sendiri di orang ketiga (Rizka 09:39) dan flag
11 Agu turun dari 5 jadi 3. Seluler ditolak walau dipakai 3 orang sekaligus.

Cara ini yang menyelamatkan: labelnya menembus batas kolom `varchar(191)`, dan
karena fitur ini sengaja menelan galat supaya absen tak pernah gagal, akibatnya
**senyap** — pendaftaran tak pernah terjadi padahal syaratnya lengkap. Uji yang
cuma mengecek "halaman kebuka" tak akan pernah melihatnya.

### Satu kasus di rencana ternyata mustahil

§6 menuntut `2777` mendaftar otomatis di orang ke-3. Orang ketiganya **Yovanka,
berjadwal Fleksibel Kantor** — sementara baris lain di tabel yang sama bilang
fleksibel tak ikut hitungan. Keduanya tak bisa benar bersamaan. Aturannya yang
dipertahankan (ACC Ekky): semua orang fleksibel punya cabang asal KOSONG di
sistem, jadi syaratnya memang tak bisa diperiksa untuk mereka — dan merekalah
yang paling sering absen dari cabang lain. `2777` tetap manual (sudah, 12 Agu).

### Audit sesudah dibangun — 5 temuan, semua diperbaiki

Yang paling merugikan **tak akan pernah kelihatan dari layar**: pemberitahuan
"ada WiFi baru terdaftar" dikirim ke role bernama `IT`, padahal halaman itu
dipegang **`Head IT` DAN `IT`** — dua role terpisah, dan sasaran notifikasi
harus cocok persis. Head IT takkan pernah menerima satu pun. Pengawasan yang
dijanjikan sebagai penutup risiko jadi separuh buta.

Sisanya: (2) kalau IT menonaktifkan sebuah baris lalu orang bertiga datang
lagi, hasilnya benar tapi **laporannya bohong** — sistem tetap bilang
"DAFTAR" padahal tak menulis apa pun; (3) pembelajaran berjalan **di dalam**
transaksi absen sambil menelan galat, dan deadlock InnoDB membatalkan seluruh
transaksi sehingga absennya justru jatuh — persis yang mau dihindari, kini
dipindah ke sesudah commit; (4) satu query per baris bukti di jalur yang
dilewati tiap absen ber-IP asing; (5) jendela 72 jam memindai seluruh tabel
karena indeksnya tak cocok.

Kasus uji naik dari 8 jadi **10** — dua tambahan mengunci veto IT dan
jangkauan notifikasi (termasuk menolak nama role yang tak ada di `group_path`,
karena salah ketik di situ = notifikasi yang tak pernah dibaca siapa pun).

**Belum prod.** Butuh SQL `2026_08_12_wifi_belajar_sendiri.sql` lebih dulu
(kolom `sumber`, indeks `idx_bukti_taken`, saklar).

---

## 2026-08-11 — GERBONG BESAR NAIK PROD: Sakit · flag borongan · keamanan lampiran

Prod dari `bf15798` → **`bc5f3c9`** (17 commit). Urutan yang dijalankan:
SQL sakit **dulu** → `deploy.sh main` → **rsync aset** (wajib: `public_html`
prod itu SALINAN, bukan symlink — CSS baru takkan terkirim tanpa ini) →
verifikasi. Tak ada lampiran lama di webroot prod (0 berkas), jadi langkah
pemindahan gugur dengan sendirinya.

**Isinya**: jenis **Sakit** (surat dokter wajib ≥3 hari, dibayar penuh, satu
lapis persetujuan) · **flag borongan** lintas tanggal + banner pintar ·
**kolom libur Kehadiran** dibaca dari aturan kerja (Senin, bukan Minggu) ·
**lampiran pengajuan diamankan** (surat dokter tak lagi bisa diambil tanpa
login) · 8 celah dari dua putaran audit · **penunjuk "Sakit?" di modal
Koreksi**.

### Penunjuk "Sakit?" — dan apa yang ketahuan saat membangunnya

Semua alasan koreksi berarti hal yang sama: *"saya SEBENARNYA masuk,
catatannya yang keliru"* — makanya koreksi yang disetujui dicatat **hadir**.
Jadi "Sakit" TIDAK boleh jadi salah satu alasan koreksi: menyetujuinya akan
menandai orang **hadir** di hari dia justru terbaring di rumah. Yang dipasang
penunjuk arah, bukan opsi baru.

⚠️ **Temuan saat menguji**: modal Koreksi ternyata **cuma bisa dibuka untuk
HARI INI** — lewat satu tombol "Absen hari ini keliru? Lapor koreksi". Hari
lampau **tidak bisa diketuk sama sekali**. Dua akibatnya:
1. Penunjuk ini menyala hanya kalau **hari ini** berstatus alpa (teruji).
2. **Alpa hari lampau tak punya jalur mandiri bagi karyawan** — Asih tak bisa
   membereskan alpa 5 Agu-nya sendiri dari layar Absensi Saya. Ini lubang
   TERPISAH yang belum diputuskan mau ditutup atau tidak.

Tanggalnya ikut dibawa ke form Sakit: kalau orang harus memilih ulang tanggal,
gesekan itu justru mendorongnya balik ke jalur koreksi yang keliru.

### Uji di prod sesudah deploy — semua lulus

52 sapuan halaman × 4 peran (nol galat 500, matriks akses masuk akal) · 13
pemeriksaan fitur inti · 8 perlindungan lampiran (termasuk path-traversal) ·
9 pemeriksaan penunjuk Sakit di dev termasuk kasus "hari ini alpa" · log
Laravel bersih · nol sisa data uji.

⚠️ Catatan jujur: satu pemeriksaan lagi-lagi terbaca "tanpa login → 200" —
itu **cacat skrip uji** (session store dipakai ulang antar request dalam satu
proses), bukan lubang. Diverifikasi ulang lewat HTTP asli tanpa cookie: `302`
ke login. Skrip sapuan halaman sudah di-`flush()`; `uji_berkas.php` belum —
kalau dipakai lagi, benahi dulu.

### Sisa pekerjaan

**Alpa Asih 5 Agu masih terbuka** — sekarang sudah BISA ditutup lewat
pengajuan Sakit resmi (fiturnya live), tapi sengaja belum dikerjakan karena
butuh pengaju & penyetuju sungguhan, bukan disuntik lewat SQL.

## 2026-08-11 — Penyesuaian data ABG + Bintaro NAIK PROD (bertahap)

Prod digelar **bertahap**: sekarang baru ABG/Office + operasional Bintaro,
cabang lain menyusul minggu per minggu. Jadi 13 nama cabang lain yang belum
ada di prod itu **bukan data hilang** — memang belum gilirannya. Ini penting
dicatat karena sempat terbaca sebagai kekurangan.

**SQL `2026_08_11_bintaro_abg_penyesuaian.sql`** — diuji dua kali di dev
(idempoten), lalu naik prod. Isinya:

1. **Empat orang ABG lepas dari cabang** (Ikhsan, Reza, Anisa, Irma). Anisa &
   Irma = Fleksibel Operasional, Ikhsan & Reza = Fleksibel Kantor. Ini bukan
   kosmetik: `session('raw_branch_id')` diisi dari kolom itu saat login, dan
   ReservationController **mengunci keras** daftar reservasi ke cabang
   tersebut — dropdown filternya pun terkunci. Selama kolom terisi, orang
   kantor cuma bisa melihat SATU cabang.
2. **Aturan kerja rangkap dibereskan** — 8 orang punya dua baris aktif karena
   penugasan 6 Agu tak menutup yang 5 Agu. Aturan mana yang berlaku jadi
   ditentukan urutan balikan MySQL. Untuk Anisa & Irma salah satunya `baku`,
   yang bisa memunculkan alpa pada orang yang jam kerjanya justru tidak
   dinilai — **dan itu sumber alpa Irma 5 Agu**. Aturan baku mereka dibuat
   TAK PERNAH berlaku (salah tempel sejak awal, bukan kebijakan sehari);
   baris tetap disimpan + jejak di `att_rule_log`.
3. **Alpajri diberi aturan GS** — dia absen tapi tak punya aturan sama sekali,
   jadi tak pernah dinilai.
4. **Saldo cuti & kontrak ikut sheet HR**: Firda 6 · Rizka 6 · Asih 7 ·
   Afrina 0 · Alpajri 0. Kolom "Kontrak" di sheet = **hari jadi kontrak**
   (akhir periode berjalan sekaligus awal periode berikutnya saat
   diperpanjang), jadi periode berjalan = 12 bulan mundur dari tanggal itu.
   ⚠️ Asumsi 12 bulan; kalau ada kontrak 6/24 bulan, tanggal MULAI-nya salah.

**Hasil di prod: alpa turun dari 2 tinggal 1.** Alpa Irma hilang sendiri
(memang bukan bolos). Yang tersisa alpa Asih 5 Agu — dia benar-benar sakit,
dan sengaja TIDAK ditambal lewat UPDATE; menunggu fitur Sakit naik prod lalu
ditutup lewat pengajuan resmi supaya ada pengaju, penyetuju, dan jejaknya.

### Dua jebakan yang ketahuan karena diuji dulu, bukan diasumsikan

**Email Alpajri berbeda antar lingkungan** — prod `fajri@`, dev `apajri@`.
Versi pertama skrip cuma cocok ke prod, jadi di dev bagian saldonya diam-diam
tak kena. Kalau langsung dijalankan ke prod, selisihnya baru ketahuan entah
kapan. Sekarang dicocokkan ke dua-duanya, sekalian ejaan dev "Apajri"
disamakan jadi "Alpajri" (sheet & prod sudah benar).

**Aturan Alpajri sempat bikin 3 alpa palsu.** Awalnya disetel berlaku 5 Agu
mengikuti yang lain — hasilnya dia langsung dapat alpa 5–7 Agu, padahal scan
pertamanya memang baru 8 Agu: dia belum ikut sistem absen waktu itu. Menilai
orang untuk hari sebelum dia terdaftar sama dengan menghukumnya atas data
yang belum pernah ada. Diperbaiki jadi berlaku sejak 8 Agu. **Ketahuan karena
hasil reconcile diperiksa, bukan dianggap beres.**

## 2026-08-11 — Jenis "Sakit" + beres-beres flag borongan (di dev, nunggu review)

Dua keputusan Ekky hari ini, dibangun satu gerbong di `integrasi/dev-2026-08-06`
(`c47eba8`). **Belum naik prod** — nunggu review, sepaket dengan fix kolom libur
Kehadiran (`dab9d92`).

**SAKIT — sebelumnya tak punya tempat sama sekali.** Orang sakit yang tak
mengajukan apa pun otomatis jadi **alpa**; itu yang menimpa Asih 5 Agu.
Menutupnya pakai "izin" sebenarnya mempan, tapi menulis alasan yang SALAH ke
catatan permanen orang — dan sekali tercatat begitu, bedanya hilang selamanya.
Aturannya (keputusan Ekky): **surat dokter wajib mulai 3 hari berturut** (1–2
hari cukup kabar — menuntut surat untuk demam semalam justru mendorong orang
masuk sambil sakit), **dibayar penuh** (kolom sendiri di lembar gaji + CSV, nol
logika potongan, jatah cuti tak disentuh), **persetujuan satu lapis** (sakit
mendesak, sering diajukan atas nama orangnya).

**FLAG BORONGAN.** Antrian Pengawasan terikat SATU tanggal, padahal rotasi WiFi
menebar flag serumpun lintas hari (16 terbuka 5–11 Agu). Sekarang ada toggle
**"Semua tanggal"**, centang pilih-banyak, dan **"pilih semua sejenis"**. Tiga
pagar yang sengaja dipasang: hanya jenis cerita-jaringan yang boleh diborong
(`single_scan` & `perangkat_desktop` tetap satuan — ceritanya per-orang),
**catatan wajib** dan ikut masuk audit log, dan gruping-otomatis-harian
dimatikan di mode lintas karena kunci "serentak" bisa palsu antar hari. Semua
ditegakkan **di server**, bukan cuma tombol yang disembunyikan.

Bonusnya **banner pintar**: flag `ip_asing` yang alamatnya KINI sudah cocok ke
jaringan terdaftar dikenali sendiri oleh sistem dan ditawarkan selesai sekali
klik — persis kelas kejadian yang sudah 3× berulang seminggu ini.

**Uji di dev: 31 pemeriksaan, semua lulus** — termasuk yang paling menentukan,
rantai penuh **alpa → pengajuan sakit disetujui → reconcile → sakit** (subjek
Ahmad Saipulloh, 9 Agu; keadaan dikembalikan setelah uji), dan pagar server
yang menolak menutup `single_scan` lewat borongan. Catatan dari uji: orang
beraturan **fleksibel tak pernah kena alpa** (§3.3) sehingga tak bisa dipakai
menguji ini — sempat terbaca "gagal" sebelum subjeknya diganti ke yang murni
baku.

### ⚠️ URUTAN DEPLOY PROD (dibaca sebelum naik)

1. **SQL DULU** — `database/sql/2026_08_11_aturan_sakit.sql` (idempoten).
   Terbalik = halaman yang menulis status meledak, karena enum belum menerima
   `sakit`. Sebaliknya aman: SQL duluan tanpa kode cuma menambah nilai enum
   yang belum terpakai.
2. `bash deploy.sh main` — sudah termasuk `access:rebuild` + clear cache
   config/route/view (rute lampiran yang baru butuh ini).
3. **Pindahkan lampiran lama** keluar dari webroot — sengaja TIDAK dijalankan
   otomatis tanpa kehadiran Ekky:
   `mv <prod>/public/uploads/att_request/* <prod>/storage/app/att_request/`
   Aman karena rute lampiran punya fallback; setelah pindah, berkas lama ikut
   terlindungi dan fallback-nya boleh dicabut.
4. Sesudah live: alpa **Asih 5 Agu** ditutup lewat **pengajuan sakit resmi**,
   bukan UPDATE manual — koreksi kehadiran harus punya pengaju, penyetuju, dan
   jejaknya. **Irma 5 Agu belum dipastikan sebabnya**, jangan asal ditutup.

### Audit pasca-build (`af479b5`) — 6 celah ketemu & ditutup

Sapu ulang SEMUA tempat yang mendaftar status/tipe, karena menambah satu nilai
enum artinya menyentuh belasan daftar yang tersebar. Hasilnya enam celah, dan
yang terbesar justru di luar zona absensi:

1. **Lintas modul — orang sakit tetap dicap "checklist bolong".**
   `ChecklistExcuseService` hanya mengecualikan cuti & izin, jadi karyawan
   sakit dihukum untuk hari yang justru sudah resmi diizinkan.
2. **Absensi Saya**: hari sakit tak masuk ember rekap mana pun — bukan hadir,
   bukan alpa, bukan cuti. Angkanya jadi tak genap sebulan dan terlihat
   seperti data hilang.
3. **Absensi Saya**: deteksi "bulan ini ada kehadiran asli" tak mengenal sakit.
4. **Periode Gaji**: CSV payroll tak punya kolom Sakit.
5. **Rekap pengajuan**: ringkasan per tipe tak menghitung sakit.
6. **Pengawasan**: kartu "kehadiran hari itu" dikunci per ORANG saja, padahal
   mode lintas tanggal memuat flag dari banyak hari — flag 5 Agu disodori
   kehadiran 11 Agu. Salah hari, tapi tampil meyakinkan seolah benar. Kunci
   diubah jadi orang+tanggal; tiap flag membawa tanggalnya sendiri.

Plus pesan hasil borongan yang tadinya berbohong: flag yang baru saja ditutup
orang lain dilaporkan sebagai "jenisnya harus diperiksa satu per satu". Kini
"salah jenis" dan "sudah selesai duluan" dihitung terpisah.

**Uji audit di dev: 18 pemeriksaan, semua lulus** — menguji perilaku lewat
jalur sebenarnya (HTTP + reconcile + kirim borongan), bukan sekadar keberadaan
konstanta. Termasuk regresi: `wfh` tetap TIDAK mengecualikan checklist seperti
sebelumnya.

### Audit putaran 2 (`1d394d5`, `448bb16`) — sudut keamanan, kuota, batas

Putaran pertama mencari "daftar yang terlewat". Putaran kedua sengaja mencari
yang lain: hak akses, kuota, dan perilaku saat data menumpuk.

**🔒 TEMUAN TERBESAR — surat dokter tersimpan telanjang di `public/`.**
Lampiran `att_request` ditulis ke `public/uploads/att_request` dan bisa diambil
**siapa pun yang punya URL-nya, tanpa login sama sekali** — terukur, bukan
dugaan: `200` di dev, sementara foto selfie karyawan justru sudah benar (`302`
ke login). Selama lampiran cuma foto lembur, ini kelalaian lama yang tenang;
begitu jenis **sakit** ada, isinya berubah jadi **surat dokter — data
kesehatan**. Alasannya sama persis dengan foto selfie yang dulu dipindah:
"wajah karyawan bukan aset publik" — surat dokter apalagi.
Diperbaiki: unggahan baru masuk `storage/app/att_request` (di luar webroot),
disajikan lewat rute ber-auth `akun/pengajuan/berkas/{id}` yang cuma
mengizinkan **pemiliknya, Finance/Admin, atau orang di rantai
persetujuannya** — selebihnya 403. Nama berkas dikunci `basename()` supaya
baris DB yang aneh tak bisa keluar folder. Lampiran LAMA tetap terbaca lewat
fallback, jadi tak ada yang rusak.

**Dua temuan "layar berbohong pelan-pelan":**
- Mode lintas + filter *Selesai* memuat SELURUH flag selesai sejak awal. Flag
  terbuka jumlahnya wajar (layar ini yang menghabiskannya), tapi yang sudah
  selesai menumpuk selamanya → layar makin berat tiap bulan sampai suatu hari
  berhenti terbuka. Dibatasi **90 hari**, dan batasnya **dikatakan di layar** —
  daftar terpotong diam-diam terbaca sebagai "cuma segini".
- Kartu riwayat menghitung flag di bulan **tanggal terpilih** tapi berlabel
  "bulan ini" — ambigu begitu panel memuat flag dari bulan lain. Kini
  menyebut nama bulannya.

**Yang diperiksa dan ternyata SUDAH benar** (dicatat supaya tak diaudit ulang):
jatah cuti tak terpotong oleh sakit (`potongDariPengajuan` berhenti kalau tipe
bukan `cuti`) · validasi unggahan ketat (`image|mimes|max:4096`) · tanggal
terbalik dijaga `after_or_equal` · label jenis di layar Rekap & Persetujuan
mewarisi satu sumber sehingga "Sakit" muncul sendiri · tak ada `whereIn('tipe')`
lain yang diam-diam melewatkan sakit · `deploy.sh` sudah membersihkan cache
rute (rute baru takkan 404).

**Uji akhir dev — semua lulus:** 13 fitur inti · 18 temuan audit putaran 1 ·
8 perlindungan lampiran (termasuk uji path-traversal `../../../.env` dan
fallback berkas lama) · **52 sapuan halaman × 4 peran, nol galat 500**, dengan
matriks akses masuk akal (Supervisor tak bisa buka layar Finance, karyawan
dilempar dari layar admin).

⚠️ Catatan kejujuran uji: satu pemeriksaan sempat terbaca "tanpa login → 200"
dan itu **cacat skripnya**, bukan lubang — session store dipakai ulang antar
request dalam satu proses. Diverifikasi ulang lewat HTTP asli tanpa cookie:
`302` ke login. Skrip sapuan halaman kini mem-`flush()` sesi tiap request.

## 2026-08-07 (tutup hari) — WiFi Cinere muncul sendiri; 2 catatan koreksi

Entri "WiFi Kalideres" di bawah ditulis siang 7 Agu. Sore harinya data berubah,
jadi tiga hal di sana perlu disusul — **entri lama tidak diedit, ini susulannya.**

**1. Cinere TIDAK kosong lagi.** Entri siang bilang "Cinere kosong (belum ada
yang pernah absen dari sana)". Sore itu juga muncul jaringan baru
`2001:448a:2033:12b8` — 3 absen, Talitha (masuk 10:29, pulang 18:35) dan Alif
(pulang 18:34), dua-duanya memilih **Cinere** manual. Belum terdaftar.

**Bukti bahwa ini WiFi tempat kerja, bukan rumah, jauh lebih kuat daripada
kasus Kalideres**: dua ORANG BERBEDA di satu jaringan berjarak satu menit
(18:34 & 18:35). Satu rumah tak menghasilkan pola itu. Kalideres kemarin butuh
verifikasi foto justru karena cuma satu orang — Rizka — sehingga WiFi cabang
dan WiFi rumah terlihat sama persis. **Aturan praktisnya: ≥2 orang berbeda di
satu /64 dalam rentang menit = jaringan tempat kerja.** Kalau cuma satu orang,
baru buka fotonya.

Ketiga alamat itu punya 64 bit belakang yang berbeda-beda (`2420:…`, `bfb9:…`,
`ca2:…`) — persis privacy extension yang sudah diantisipasi. Mendaftarkan SATU
saja langsung menutup Talitha dan Alif sekaligus.

**2. Pendaftaran Pamulang belum pernah terpakai.** Anisa mendaftarkan
`2001:448a:2020:27df` (Pamulang) jam 09:35 berdasarkan data 6 Agu — hari itu
Alif, Talitha, dan Yovanka absen dari sana dalam satu menit (21:52–21:53).
Tapi 7 Agu **tak ada satu pun** absen dari prefix itu: Alif & Talitha justru di
Cinere, Yovanka tidak absen. `last_seen_at` masih kosong. Bukan salah daftar —
cuma belum kepakai. Perlu dilihat lagi setelah beberapa hari; kalau tetap sepi
sementara orangnya rutin di Cinere, berarti yang 6 Agu itu yang perlu
dipertanyakan.

**3. Rizka dapat flag KETIGA.** Entri siang menyebut 2 flag; sorenya bertambah
`luar_seharian`. Sebabnya: absen pulang 20:16 lewat **data seluler Telkomsel**
(`114.10.70.167`), bukan WiFi Kalideres — jadi kedua absennya hari itu tak
cocok, dan flag "di luar jaringan seharian" wajar menyala. ⚠️ **IP seluler
tidak bisa didaftarkan** (CGNAT, berputar, dipakai jutaan orang — mendaftarkan
= membuka absen dari mana saja). Jadi selama orang absen pakai kuota, flag ini
akan terus muncul dan memang harus ditutup manual. Bukan cacat.

**⚠️ JAM DI `att_branch_ip` BISA MELESET 7 JAM — jangan salah baca urutan.**
MySQL & OS server jalan di **UTC**, sedangkan Laravel menulis **Asia/Jakarta**.
Baris yang ditulis lewat aplikasi = WIB; baris yang ditulis lewat SQL langsung
= UTC. Baris Kalideres (id 13) tercatat `03:16` padahal aslinya **10:16 WIB**.
Akibatnya berbahaya: dibaca apa adanya, pendaftaran (03:16) seolah SEBELUM
absen Rizka (09:59) — dan kesimpulan yang menggoda adalah "pencocokan IP
rusak". **Itu keliru.** Urutan sebenarnya 09:59 absen → 10:16 daftar, dan
pencocokan sehat. Penanda gampang: `id` 13 lebih besar dari `id` 12 (09:35),
padahal AUTO_INCREMENT tak bisa mundur — kalau id dan jam bertentangan,
**percaya id-nya.**

**Cakupan saksi WiFi per akhir 7 Agu** — Bintaro 2 IPv4 + 2 IPv6 (lengkap,
aktif) · Sunter 1+1 (lengkap, aktif) · Kalideres 0+1 (belum pernah cocok) ·
Pamulang 0+1 (belum pernah cocok) · **Cinere 0+0 — ada jaringan aktif yang
belum didaftarkan.** Dari 20 absen 7 Agu, 5 tak cocok (25%): Rizka ×2,
Talitha ×2, Alif ×1.

**Tindakan berikutnya (kecil, belum dikerjakan):** daftarkan
`2001:448a:2033:12b8:…` sebagai Cinere — buktinya sudah cukup kuat tanpa perlu
buka foto; lalu Anisa menutup 3 flag Rizka yang sebabnya sudah terang.

## 2026-08-07 — WiFi Kalideres didaftarkan (diverifikasi FOTO, bukan tebakan IP)

Rizka absen dari jaringan tak dikenal dua kali (6 Agu 20:40 pulang, 7 Agu 09:59
masuk) — **alamat sama persis sampai ekornya**, `2001:448a:90b0:ebf:...`,
dua sesi berjarak 13 jam. Stabil sebagai jaringan, tapi datanya TIDAK bisa
membedakan dua cerita: WiFi cabang, atau WiFi rumah (pulang setelah sampai
rumah, masuk sebelum berangkat) — polanya identik.

**Yang memutuskan: foto selfie.** Ekky membuka bukti absennya dan memastikan
Rizka memang berada di cabang. Baru setelah itu didaftarkan (label menyebut
verifikasi fotonya). Ini pelajaran yang layak diulang: **kalau IP ambigu,
fotonya yang menjawab** — 10 detik melihat bukti mengalahkan berhari-hari
menunggu data IP menumpuk. Ongkos salahnya timpang: tak mendaftarkan = flag
harian yang merepotkan; salah mendaftarkan = orang bisa absen dari rumah
TANPA flag, dan flag itu satu-satunya pemicu pemeriksaan.

**Alurnya (normal, bukan anomali)**: Rizka masuk di Bintaro 6 Agu, pindah
penempatan ke Kalideres, menginap, lalu absen dari Kalideres 7 Agu. Roster
tetap Bintaro karena penempatannya **sementara** — dan itu memang rancangannya:
`att_day.raw_branch_id` cuma untuk pengelompokan laporan, bukan sumber
kebenaran, dan satu hari kerja memang boleh lintas cabang (preseden lama:
Rokhmatun, perawat Sunter yang menutup Kalideres, tercatat di 4 mesin).
⚠️ Saran sebelumnya agar roster Rizka dipindah ke Kalideres **KELIRU** dan
dibatalkan — jangan diulang. Kebutuhan yang sebenarnya sudah punya rumah:
fitur **Penempatan** milik Reza (`docs/rencana/rencana-penempatan.md`).

Diuji setelah didaftarkan: alamat Rizka cocok Kalideres, HP lain di prefix yang
sama juga cocok, prefix tetangga TIDAK ikut cocok, dan Bintaro/Sunter/Pamulang
tetap pada cabangnya masing-masing.

**Cakupan saksi WiFi sekarang** — Bintaro 2 IPv4 + 2 IPv6 (lengkap) · Sunter
1+1 (lengkap) · Kalideres 0+1 · Pamulang 0+1 · Cinere kosong (belum ada yang
pernah absen dari sana). Kalideres & Pamulang belum punya wajah IPv4-nya:
orang pertama yang HP-nya lewat IPv4 akan manual sekali, lalu tinggal
didaftarkan. Sisa: 2 flag Rizka hari ini (`ip_asing` masih "baru", `pola_ip`
sudah "dicek") — sebabnya sudah jelas, tinggal ditutup Anisa.

## 2026-08-07 dini hari — Fitur "Minta Buka Kembali" checklist (Reza) NAIK PROD, teruji

Ekky menyerahkan malam ini penuh ke HUB ("lanjut tanpa aku"): audit → dev →
uji → prod → uji ulang. Semua selesai, tak ada temuan yang menahan.

**Isinya**: karyawan bisa meminta membuka tanggal checklist yang sudah lewat
supaya bisa diisi; atasan menyetujui/menolak dari halaman baru
`/checklist/reopen-requests`. Izin bersifat **sekali pakai** — begitu
dikirim, harinya terkunci lagi. Plus perbaikan kecil voucher topup petty cash
dan form mingguan. (Branch `feat/checklist-reopen-request`, 1 commit, 13
berkas; merge bersih tanpa konflik.)

**Hasil audit kode (sebelum naik)**:
- Rute baru semua di bawah prefix `checklist` / `my-checklist` yang sudah ada
  → tak butuh node menu baru, bebas dari jebakan "grant tanpa node".
- Gerbang atasan berlapis: supervisor checklist + dibatasi divisi + dibatasi
  cabang. Gerbang karyawan ditegakkan **di server**, bukan cuma di tampilan —
  kirim tanggal lampau tanpa izin ditolak dengan pesan jelas.
- SQL `2026_08_06_checklist_reopen_request.sql` idempoten, collation sudah
  disamakan dgn `users` (Reza belajar dari insiden illegal-mix kemarin).
- Catatan kecil yang DITERIMA sebagai wajar: mencentang item hari lampau
  berstatus draf tidak dijaga izin — tapi **kirim**-nya dijaga, jadi tak ada
  riwayat yang bisa dipalsukan.

**Uji fungsional 11 kasus — LULUS SEMUA di dev DAN prod** (data uji bertanda,
dibersihkan sendiri, diverifikasi nol sisa): pending bukan izin · izin hanya
untuk orang+tanggal yang diminta · sekali pakai lalu mati · varian mingguan ·
anti-dobel pengajuan · JOIN users bebas masalah collation.

**Uji HTTP di dev** (login sungguhan): Afrina (FO) — my-checklist hari ini,
tanggal lampau, riwayat semua 200; Anisa — checklist, review, dan halaman baru
reopen-requests semua 200. Catatan: akun dev Firda tak bisa dipakai uji
(wajib-ganti-password) — bukan bug.

**Prod**: backup `~/backups/prod_pre_reopen_2026-08-07.sql.gz` (45MB) → main
`27ddb7a` → SQL → autoload+cache → verifikasi: tabel 16 kolom, 8 rute reopen,
seluruh Blade terkompilasi, situs 200, halaman baru mental ke signin tanpa
login, **0 error pasca-deploy**, data lama utuh (224 task, 27 petty cash, 39
bukti absen, 11 hadir hari ini).

**Penyetuju di prod saat ini** = pemegang menu checklist: **Anisa & Irma**
(+Super Admin secara kode, tapi belum pegang menunya — utang lama). Kalau Ekky
mau ikut menyetujui, tinggal grant menu checklist ke role-nya.

## 2026-08-06 — Push Reza (checklist mingguan + petty cash) NAIK DEV

Branch Reza `minorfixing-pettycash-weeklychecklist` (1 commit, 17 berkas)
digabung ke branch integrasi `integrasi/dev-2026-08-06` — **tanpa konflik** —
lalu naik dev. Isinya dua hal: kolom `weeks_of_month` per task checklist
(rotasi tugas "minggu ke-berapa dalam bulan", kosong = tiap minggu seperti
sebelumnya), dan jejak `deleted_by` untuk petty cash + transport.

**Diperiksa sebelum naik.** Reza mengirim migration DAN file SQL untuk kolom
yang sama — sekilas itu resep dijalankan dua kali. Ternyata aman: migration-nya
berhenti lebih dulu kalau kolomnya sudah ada, SQL-nya mengecek
`information_schema`, dan Reza memang menulisnya sebagai cermin yang disengaja
(prod pakai jalur SQL manual). Kedua SQL murni menambah kolom nullable — tak
ada yang merusak data lama. Dijalankan di dev, ketiga kolom terpasang benar.

Verifikasi dev: situs 200, `view:cache` berhasil mengompilasi **seluruh** Blade
(penangkap bug sintaks seperti kasus `@if` menempel dulu), dan tak ada error
baru setelah deploy. Dua error di log hari ini berasal dari percobaan
diagnostik sesi ini pukul 11:44–11:45, lebih dari sejam sebelum deploy.

**⚠️ BUTUH KEPUTUSAN EKKY SEBELUM NAIK PROD — perubahan kendali keuangan.**
Sebelumnya edit & hapus transaksi petty cash **hanya boleh Super Admin**.
Commit ini **mencabut syarat itu** dan menggantinya dengan batas **24 jam sejak
transaksi dibuat**. Jadi orang cabang kini bisa mengubah/menghapus transaksi di
cabangnya sendiri selama masih di bawah 24 jam. Penyeimbangnya sudah ada:
Finance tetap view-only, cabang terkunci ke cabangnya, dan siapa yang menghapus
kini tercatat (`deleted_by`, tampil di sub-tab Riwayat). Rancangannya masuk
akal, tapi ini melonggarkan kendali uang tunai — **jangan naik prod sebelum
Ekky menyatakan setuju.**

## 2026-08-06 malam — 🚀 NAIK PROD: gabungan Reza + absensi, plus dua ranjau ditutup

Prod naik dari `07fd818` ke `e28f89b`. Backup dulu:
`~/backups/prod_pre_gabungan_2026-08-06.sql.gz` (45MB) +
`~/backups/prod_env_pre_gabungan_2026-08-06.bak`.

**Isinya**: seluruh perbaikan checklist & petty cash dari Reza · Tukar Hari ·
lembur datang-awal · jadwal fleksibel per role · gerbang absen di hari libur ·
alasan koreksi baru · jenis pengajuan disaring per aturan kerja.

**Sebelum naik, empat lubang tombol Reset petty cash dibereskan** (sebelumnya
cuma tertutup gerbang fitur — sekarang tombolnya sendiri memang layak pakai):
- **Periode yang sudah DITUTUP ditolak.** Jalur lain semua menghormati itu;
  reset tak boleh jadi satu-satunya pintu belakang ke angka yang sudah
  di-sign-off.
- **Saldo bulan-bulan berikutnya dihitung ulang.** Saldo awal itu SNAPSHOT saat
  periode dibuat, bukan turunan hidup — menghapus transaksi bulan lalu tanpa
  menghitung ulang meninggalkan selisih hantu yang terbawa selamanya. Periode
  yang sudah ditutup dilewati DAN menghentikan rantai: selisihnya tetap ada,
  tapi itu keputusan sadar yang terlihat, bukan kerusakan senyap.
- **Jejak audit**: siapa, kapan, cabang/periode mana, berapa baris, berapa
  nilainya — dicatat SEBELUM barisnya hilang.
- **"Reset Riwayat Review" dipersempit** ke baris yang benar-benar tampil.
  Dulu judulnya "20 terakhir" tapi perintahnya menghapus seluruh cabang
  sepanjang masa, termasuk satu-satunya catatan siapa menyetujui berapa untuk
  uang yang sudah cair. Tanpa pilihan kini tak menghapus apa pun.
Diuji di dev: periode tertutup ditolak & transaksinya utuh · saldo berikutnya
terkoreksi 700rb→1jt · periode tertutup menghentikan rantai · pilih 1 dari 3
riwayat hanya menghapus 1 · jejak audit tercatat.

**DUA RANJAU DITUTUP:**
1. **`APP_ENV` prod akhirnya dibetulkan** dari `local` → `production`. Ini
   bukan kosmetik: gerbang fitur Reza menyimpulkan "local berarti dev, boleh
   hapus" — di prod kita itu berarti tombol hapus-permanen data uang MENYALA.
   Sudah kuubah jadi daftar-putih (mati kecuali dinyalakan eksplisit), jadi
   sekarang dua lapis: environment benar DAN default aman.
2. **Kejutan saat migrate**: tabel `sys_checklist_weekly_day_submit` sudah ada
   padahal migration-nya belum tercatat — DDL terlanjur jalan tanpa catatan.
   Karena isinya nol baris, tabelnya di-drop lalu dibuat ulang oleh migration
   supaya struktur & catatan konsisten. Semua 7 migration kini tercatat,
   **0 tertunda**.

**Verifikasi prod**: 40 halaman lintas peran semuanya 200 · halaman depan &
login 200 · log nol error baru · `environment=production`, debug MATI, tombol
reset MATI · skema baru lengkap (att_request_hari, weekly_day_submit,
parent_id_2, kind, requester_name, excuse.source, jenis 'tukar') · 5 aturan
kerja dengan hari yang benar · **absensi hari ini tetap jalan: 11 bukti absen
HP, 11 baris att_day** — perbaikan "absen langsung terlihat" tak terganggu ·
aset publik ikut di-rsync (public_html prod itu COPY, bukan symlink).

**Yang masih menggantung**: tombol Reset tetap MATI di prod (daftar-putih).
Kalau kelak perlu dinyalakan, sekarang sudah aman dipakai — empat lubangnya
sudah ditutup.

## 2026-08-06 sore — Perbaikan Reza diperiksa & DIGABUNG; satu default berbahaya dicegat

Reza mengirim perbaikan atas 8 temuan audit — di branch BARU
`fixing-pettycash-checklist` (dicabang dari `main`, semuanya disquash jadi satu
commit), bukan melanjutkan branch lama. Sudah diperiksa, digabung dengan fitur
absensi, dan **dev sekarang menjalankan keduanya** (`integrasi/dev-2026-08-06`).
Cadangan `~/backups/dev_pre_integrasi_2026-08-06.sql.gz`.

**Perbaikannya bagus — dan saya uji perilakunya, bukan cuma baca kodenya:**
- **Form mingguan lama tak lagi buntu** — backfill (migration + SQL) mengisi
  baris per-hari untuk submission lama; diperiksa: nol submission
  submitted/approved yang tanpa baris, dan baris approved membawa reviewer-nya.
- **Minggu yang sudah disetujui tak turun jadi draft** saat cuti backdate
  di-ACC — diuji sungguhan: status tetap approved, jejak reviewer utuh.
- **Mencabut pengecualian tak lagi mengunci minggu selamanya** — diuji: cuti
  sepekan di-ACC memasang 6 pengecualian, ditolak → tercabut semua, dan
  minggunya bisa diisi lagi.
- **Pengecualian MANUAL karyawan tak lagi ditimpa** jadi bawaan sistem.
- **Kartu antrian menampilkan nama yang diketik DAN nama akun**, jadi identitas
  asli tak bisa tertutupi teks bebas.
- **Lima temuan tombol Reset ditutup lewat gerbang fitur** — rutenya 404 kalau
  gerbangnya mati. Rancangan yang tepat.

**⚠️ SATU HAL SAYA CEGAT — dan Reza tak mungkin tahu.** Gerbang itu default-nya
disimpulkan dari `APP_ENV === 'local'`, artinya "kalau local berarti dev, jadi
boleh". **Produksi kita berjalan dengan `APP_ENV=local`** (temuan kemarin, belum
dibereskan karena mengubahnya bisa menggeser hal lain). Jadi default itu justru
akan **MENYALAKAN penghapusan permanen data uang di produksi** — persis
kebalikan dari maksudnya. Diubah jadi daftar-putih: mati kecuali dinyalakan
eksplisit lewat `.env`. Dev dinyalakan (`PETTY_CASH_RESET_ENABLED=true`) supaya
alat bersih-bersih Reza tetap ada; prod tak perlu apa-apa karena default-nya
sudah aman. Pelajarannya: **fitur destruktif pakai daftar-putih, bukan
daftar-hitam** — lingkungan yang salah label lalu jadi aman, bukan terbuka.

**Masih menggantung (aman karena di balik gerbang, tapi belum beres):** tombol
reset tetap tanpa cek periode-tertutup, tanpa hitung-ulang saldo bulan
berikutnya, tanpa jejak audit, dan "Reset Riwayat Review" masih menghapus
seluruh cabang sepanjang masa. Kalau kelak gerbangnya dinyalakan di prod, empat
itu harus dibereskan dulu.

**Verifikasi gabungan di dev**: 42 halaman lintas 8 peran semuanya 200; uji
absensi 30 pemeriksaan lulus; uji perbaikan Reza 9 pemeriksaan lulus; log nol
error. Merge tanpa satu pun konflik — dua perubahan di berkas yang sama
(`submit()` dan halaman Audit Trail) menyatu utuh.

**Yang perlu diputuskan**: `APP_ENV=local` di produksi sebaiknya dibetulkan
jadi `production`. Sekarang tak ada kode yang bercabang atasnya selain config
bawaan, tapi ini ranjau — pustaka mana pun yang menganggap "local = boleh
macam-macam" akan salah kaprah, dan kejadian hari ini contoh pertamanya.

## 2026-08-06 — Tukar Hari, lembur datang-awal, jadwal per role — LIVE DI DEV

Lima keputusan produk dari diskusi 5–6 Agustus dibangun sekaligus, di branch
`feature/absensi-tukar-hari` yang **dipotong dari `main` dan bersih dari
kerjaan Reza** — jadi bisa naik prod sendiri tanpa menunggu perbaikan Reza.
Dev untuk sementara diambil alih (izin Ekky); cadangan
`~/backups/dev_pre_tukar_2026-08-06.sql.gz`, dev sebelumnya di
`checklist-pettycash` @ dbbe44a kalau perlu dikembalikan.

**1. Tukar Hari — satu pengajuan, dua sisi.** "Saya masuk 9+10, gantinya libur
12+13", satu kali ACC. Bukan dua form terpisah: kalau kedua sisi bisa diputus
sendiri-sendiri, orang bisa dapat libur pengganti tanpa hari masuknya — persis
lubang yang bikin fitur ini ada. Tanggalnya DAFTAR, bukan rentang, supaya tukar
9+10 ↔ 12+13 tidak menyeret tanggal 11 yang tak terlibat (terbukti di uji).
Dijaga: jumlah kedua sisi harus sama, satu tanggal tak boleh di dua sisi, dan
semuanya satu periode gaji — jatuh ke bulan kalender kalau Finance belum
membuat periodenya, supaya fiturnya tak macet menunggu pekerjaan orang lain.

**2. Jenis pengajuan disaring per aturan kerja.** Tukar Hari & Kerja dari Rumah
hanya untuk yang fleksibel. Disaring di layar DAN ditolak di server lewat
fungsi yang sama — tombol yang disembunyikan tetap bisa dikirim lewat form
buatan sendiri.

**3. Jadwal hari lewat ROLE, bukan nama orang** (permintaan Ekky). Kuncinya
memisahkan dua hal: `tipe` aturan menentukan CARA MENILAI (fleksibel = tak
pernah alpa, boleh Tukar Hari & WFH), konfigurasi hari menentukan HARI MANA
yang libur. Jadi sekarang ada tiga aturan yang sama-sama fleksibel:
- **Fleksibel** — tanpa jadwal (Executive: Dani, Hari, Robi)
- **Fleksibel Kantor** — libur Minggu + Senin (Head IT, IT, Finance, Head
  Marketing, Creative, Content Creator)
- **Fleksibel Operasional** — masuk Minggu, libur Senin (Supervisor,
  Coordinator Dentist → Anisa & Irma)
Tidak ada satu pun `if nama == 'Anisa'`. Halaman Aturan Kerja juga dapat tombol
**"Terapkan ke karyawan yang sudah ada"** — sebelumnya aturan per role hanya
berlaku untuk AKUN BARU, dan itulah yang selama ini memaksa daftar nama muncul
di SQL. Tombolnya terpisah dari Simpan karena mengubah aturan kerja orang yang
sedang berjalan itu keputusan, bukan efek samping.

**4. Lembur datang-awal akhirnya terbaca.** Jadwal masuk 10:00 tapi absen 08:00
untuk persiapan — dua jam itu kerja, dan selama ini mesin saran cuma mengenal
sisi pulang. Patokannya jam masuk paling awal, bukan ambang telat (kalau pakai
ambang telat, orang tepat waktu pun tampak lembur). Ambang 30 menit. Hari yang
punya dua sisi mengisikan yang terlama tapi menyebut sisi satunya — memilihkan
diam-diam berarti separuh lemburnya hilang. Tetap usulan: yang memutuskan
manusia lewat form + ACC atasan.

**5. Gerbang absen di hari libur + kalender tak lagi kosong.** Absen di hari
libur terjadwal memunculkan pertanyaan "dalam rangka apa — Tukar hari atau
Lembur", ditanya SEKARANG selagi orangnya paling tahu alasannya. Absennya tak
pernah ditolak. Hari libur terjadwal orang fleksibel kini menghasilkan baris
`libur` alih-alih kosong — kosong itu ambigu, tak bisa dibedakan dari "belum
ada datanya", padahal tukar hari perlu terlihat kontras dengan libur biasa.

**Alasan koreksi absen diperbarui** ke kosakata pasca-fingerprint, termasuk
"Salah pilih cabang" yang sekaligus jadi isyarat bagi SPV bahwa pendaftaran
WiFi cabang itu belum beres.

**Verifikasi dev**: 38 halaman lintas 8 peran semuanya 200, log bersih. Tukar
Hari diuji 7 skenario lewat controller (pintu yang dipakai orang, bukan fungsi
di baliknya — pelajaran dari bug Carbon kemarin); lembur 4 skenario; gerbang &
kalender diuji untuk empat kelompok aturan. Tombol "Terapkan" idempoten.

**Diuji sendiri 2026-08-06 (Ekky keluar)**: uji ujung-ke-ujung 30 pemeriksaan
di dev — SEMUA LULUS. Satu BUG NYATA ketemu & diperbaiki di situ: jenis `tukar`
belum terdaftar di plafon lapis persetujuan, jadi Tukar Hari naik ke lapis DUA
lalu menggantung tak terlihat siapa pun (kelas kesalahan yang sama dengan bug
Carbon kemarin — sesuatu yang baru lupa didaftarkan di sebuah peta, lalu gagal
dengan diam). Selain angkanya ditambah, `submit()` kini MENOLAK jenis yang belum
terdaftar supaya kelalaian berikutnya berbunyi keras di dev, bukan diam sampai
ke gaji. Dev ditinggal bersih: nol sisa data uji, log nol error.

**Belum naik prod.** Butuh 2 SQL (`2026_08_06_tukar_hari.sql` — **wajib mysql
CLI karena DELIMITER** — dan `2026_08_06_jadwal_fleksibel.sql`).

## 2026-08-05 sore — Kode absensi NAIK PROD + daftar WiFi Bintaro dirapikan

Menutup dua utang yang masih menggantung dari entri di bawah.

### 1. Perbaikan "absen langsung terlihat" akhirnya di prod

Prod naik dari `f309061` ke `6b6ed60`. Sebelum ini prod sudah punya **datanya**
benar (9 orang tercatat hadir) tapi belum punya **kodenya** — artinya besok
absen akan kembali tak terlihat sampai lusa, kecuali dijalankan manual tiap
hari. Sekarang tidak perlu lagi: begitu absen tersimpan, catatan hariannya
langsung dihitung untuk orang itu saja.

**Diaudit dulu, tidak langsung dinaikkan.** Yang diperiksa:
- Perubahan kodenya cuma satu berkas (`AkunAbsenController`), dibungkus
  try/catch — kalau perhitungannya gagal, absennya tetap sah tersimpan.
- **Perbaikan tipe Carbon dibuktikan bekerja**, bukan dipercaya. Di dev
  ditemukan jejak nyata kegagalannya jam 13:14 (`reconcile kilat gagal:
  Argument #1 must be of type Illuminate\Support\Carbon`) — error itu tertelan
  try/catch, jadi absen tetap masuk tapi perhitungannya diam-diam mati. Jalur
  kodenya dijalankan ulang di dev dengan versi yang sudah diperbaiki: tipenya
  benar, reconcile lolos tanpa error.
- **Pelajaran berkas bayangan dipakai**: setelah deploy, diverifikasi lewat
  refleksi bahwa PHP benar-benar memuat `AkunAbsenController.php` yang asli
  (bukan salinan bertanggal) dan method barunya ada. Tanpa cek ini, deploy bisa
  "berhasil" tapi tak berefek — persis yang terjadi tadi siang.
- SQL `2026_08_05_absensi_mulai_5_agustus.sql` sudah dijalankan sebelumnya;
  diverifikasi: 15 aturan semuanya mulai 2026-08-05, **0 baris** att_day
  sebelum 5 Agustus, 9 baris `hadir` untuk 5 Agustus.
- Reconcile dijalankan ulang untuk hari ini → tetap 9 `hadir`, tidak ada baris
  ganda. Idempotensinya nyata, bukan klaim.

Backup sebelum eksekusi: `~/backups/prod_pre_kilat_2026-08-05.sql`.

### 2. Daftar WiFi Bintaro: 10 baris → 5, dan petanya akhirnya jelas

Ekky menelusuri sendiri tiap SSID kantor lewat halaman WiFi Cabang. Hasilnya
**Bintaro punya DUA jalur internet, bukan tiga**:

- **Jalur A** — SSID Xiaomi_FD3D_5G, Sweet Treats Lt.2, Sweet Treats BO →
  IPv4 `180.252.168.224`, IPv6 prefix `2001:448a:2092:2313::/64`
- **Jalur B** — SSID Sweet Treats (dan kemungkinan Lt.1) →
  IPv4 `180.252.170.180`, IPv6 prefix `2001:448a:2092:134e::/64`

Dua penemuan yang menjelaskan kebingungan sejak pagi: **Lt.2 dan BO memberi
alamat yang sama persis** (satu jaringan, dua nama SSID), dan **router Xiaomi
tidak melayani IPv6 sama sekali** — makanya tidak ada menu IPv6 di panelnya,
dan siapa pun lewat SSID itu selalu tampak sebagai IPv4.

**Kenapa daftarnya membengkak jadi 10**: pencocokan IPv6 memakai 64 bit
pertama, tapi pemeriksaan duplikat pada **formulir** membandingkan teks alamat.
Karena ekor IPv6 tiap HP berbeda, jaringan yang sudah tercakup bisa
didaftarkan berulang tanpa peringatan apa pun. Lima dari sepuluh baris lahir
begitu — termasuk dua yang didaftarkan sore ini saat menelusuri.

Dinonaktifkan (bukan dihapus) lewat `2026_08_05_wifi_bintaro_rapikan.sql`:
tiga baris kembar, plus **dua baris prefix `ab6` dari 25 Juli yang sudah tak
dipakai**. Yang basi itu bukan sekadar berantakan — kalau Telkom sudah melepas
prefix-nya ke pelanggan lain, orang di luar kantor bisa terbaca sebagai
Bintaro. Sisa **tepat 5 baris aktif**: empat wajah Bintaro + Sunter.

**Uji sebenarnya besok pagi**: kalau orang Bintaro absen tanpa flag `ip_asing`,
kedua jalur sudah tertutup benar.

### Utang baru yang dicatat

**Formulir WiFi perlu sadar-jaringan.** Pemeriksaan duplikatnya harus
membandingkan jaringan (64 bit pertama untuk IPv6), bukan teks alamat, dan
bilang "jaringan ini sudah tercakup entri X". Tanpa itu daftarnya akan
menggemuk lagi dengan sendirinya.

Catatan kebersihan: 3 error di log prod hari ini semuanya **bukan bug
aplikasi** — dua query diagnostik dari sesi kerja (termasuk `--columns` yang
tidak ada, dan kolom `branch_id` yang memang bukan nama kolom sebenarnya) dan
satu gangguan koneksi DB sekejap. Diperiksa: tak ada kode aplikasi yang
menyebut kolom itu.

## 2026-08-05 sore — AUDIT MENYELURUH prod + dev (2 bug diperbaiki, 3 utang dicatat)

Permintaan Ekky: scan ulang seluruhnya, cari error/bug/inkonsistensi. Diperiksa
19 pemeriksaan otomatis di KEDUA server: struktur menu, render menu tiap peran,
koherensi data absensi, rantai persetujuan, aturan kerja, perintah terjadwal,
detektor Kesehatan, dan log.

**Dikonfirmasi beres**: perbaikan "absen langsung terlihat" sudah live di prod
& dev (prod `cb6dbe5`); 9 orang tercatat hadir; tak ada scan yang tak punya
baris harian; tak ada pengajuan tertahan lapis-2; semua rantai persetujuan
1 lapis; role Executive ada penghuninya; 4 perintah terjadwal terdaftar.

### Dua bug NYATA ditemukan & diperbaiki (dev)

1. **Executive Report 403 padahal di-grant.** Tiga node menu di dev menyimpan
   link berawalan garis miring (`/report/executive`, `/my-checklist`,
   `/my-checklist/history`). Gerbang halaman mencocokkan link tanpa garis
   miring, jadi Executive/Admin/Super Admin ditolak di halaman yang menunya
   tampil. Prod bersih sejak awal. Diperbaiki
   (`2026_08_05_normalisasi_link_node.sql`) — terbukti 403 → 200.
   Perlakuannya beda per node, sengaja: yang punya kembaran bersih grant-nya
   dipindah lalu node lamanya dibuang (kalau di-strip begitu saja hasilnya
   justru "node dobel"), sisanya cukup dibuang garis miringnya.

2. **Halaman pribadi lenyap kalau kebetulan punya anak.** Ini regresi gabungan
   hari ini: perbaikan "leluhur-wadah" membuat "Checklist Saya" berubah jadi
   induk, dan pengumpul menu sengaja melewatkan node ber-anak — akibatnya
   halaman checklist milik General Services & Assistant Dentist **hilang dari
   menu sama sekali**, bukan cuma pindah tempat. Gejalanya diam, tanpa error.
   Diperbaiki di kode: drop-up akun kini memakai pengumpul yang menyertakan
   node ber-anak. Sekalian "Riwayat Checklist" ditempatkan sebagai halaman
   pribadi (drop-up), bukan berdiri sendiri di menu utama.
   Sesudahnya prod & dev seragam: GS/Asdent menu utama kosong (memang begitu
   desainnya — kerjaan mereka semua pribadi) dengan drop-up lengkap.

### Tiga utang dicatat, BUKAN diperbaiki (butuh keputusan / bukan hari ini)

1. **Node menu ber-link kembar** — prod: `pettycash` ×2, `rawreservation` ×4,
   `report` ×2; dev serupa. Bahayanya: mencabut akses lewat satu node tidak
   mencabut aksesnya, karena gerbang cukup menemukan grant di node kembarannya.
   **Semuanya warisan lama, bukan dari kerjaan hari ini**, dan detektor
   Kesehatan memang sudah menandainya (badge 8 di prod). Perlu keputusan mana
   node yang sah sebelum dirapikan — bukan tebakan.
2. **Menu mati**: node "Sales" ber-link `complaint` tidak punya route
   (prod & dev) — tak dipegang siapa pun, jadi tak terlihat; cruft. Di dev
   ada lima menu Accurate (`accurate-*`) tanpa route karena proyeknya
   di-pause, tapi **masih dipegang Creative & Head Marketing** → kalau diklik
   berujung 404. Prod tidak kena.
3. **Satu galat DB sekilas di prod** 14:13 (`SQLSTATE[2002] Operation not
   permitted` saat membaca `users`) — sekali saja, khas gangguan koneksi
   sesaat hosting, bukan pola. Dicatat supaya kalau berulang sudah ada jejaknya.
   (Tiga galat prod lainnya berasal dari skrip diagnosa saya sendiri dan satu
   perintah salah opsi — bukan aplikasi.)

Catatan: "peran ber-grant tapi menu utama kosong" (GS, Asdent, FO di dev)
diperiksa satu per satu dan **bukan cacat** — semua grant mereka memang
halaman pribadi yang tempatnya di drop-up.

## 2026-08-05 siang — PROD DIBERESKAN: absensi resmi mulai 5 Agustus, 9 orang tercatat hadir

Lanjutan temuan di bawah. Keputusan Ekky: **4 Agustus dinyatakan tidak berlaku,
penilaian absensi dimulai 5 Agustus.** Dikerjakan langsung di prod (backup
dulu: `~/backups/prod_pre_absensi_start_2026-08-05.sql.gz`).

**Hasil di prod, terverifikasi:**
- **9 orang yang absen hari ini kini tercatat "hadir"** — Reza 07:33, Yovanka/
  Firda/Talitha 09:31, Della 09:33, Afrina 09:48, Rizka 10:16, Ikhsan 10:43,
  Anisa 11:36. Layar mereka tidak lagi menyangkal absen mereka.
- **6 alpa palsu 4 Agustus terhapus**, dan tidak akan lahir lagi.
- Mulai-berlaku aturan kerja 15 orang digeser ke 2026-08-05.

**Kenapa bukan sekadar menghapus barisnya**: menghapus itu sementara — siapa
pun yang kelak menjalankan ulang perhitungan untuk rentang yang memuat 4
Agustus akan melahirkan alpa itu lagi, tanpa ada yang sadar. Yang dipakai
adalah mekanisme sistem sendiri (mulai-berlaku aturan kerja), jadi tanggal
sebelum 5 Agustus tidak menghasilkan baris apa pun dan pembersih bawaan
membuang yang lama. Berkas: `2026_08_05_absensi_mulai_5_agustus.sql`.
**Konsekuensi yang disadari & bisa dibalik**: kalau kelak data mesin sidik
jari 1–4 Agustus diunggah, scan itu tidak akan dinilai. Kalau suatu saat perlu,
cukup mundurkan lagi tanggalnya — tidak ada data yang dibuang.

**MASIH MENGGANTUNG — perbaikan kodenya belum di prod.** Yang di atas
membereskan data hari ini; supaya besok-besok absen langsung terlihat tanpa
disuruh jalan manual, kode `fix(absensi): absen langsung terlihat hari itu
juga` perlu naik prod. Prod melacak branch `main`, dan **push ke `main`
diblokir pengaman** — perlu Ekky mengizinkan atau melakukannya sendiri.
Tanpa itu, tiap hari absen baru terlihat besok paginya (atau harus dijalankan
`att:reconcile <hari-ini> <hari-ini>` manual).

## 2026-08-05 siang — "Sudah absen kok ditulis Belum absen?" (sebabnya ketemu, fix ada di dev)

Ekky melapor dari HP: sudah absen, tapi layar Absensi Saya tetap menulis
**"Belum absen"** dan rekap Agustus **"0 hari tercatat"**. Diperiksa langsung
di prod (baca-saja): absennya **tidak hilang** dan datanya **tidak rusak**.

**Sebabnya sederhana tapi berdampak luas**: absen menulis ke tabel scan/bukti,
sedangkan semua layar absensi membaca tabel harian (`att_day`) yang **hanya
dibuat sapuan otomatis jam 06:10 pagi**. Jadi absen jam 10:43 baru muncul
besok pagi.

**Kena SEMUA orang, bukan cuma Ekky.** Diperiksa jam 13:10 di prod: sembilan
orang sudah absen hari ini (Reza 07:33, Yovanka/Firda/Talitha 09:31, Della
09:33, Afrina 09:48, Rizka 10:16, Ikhsan 10:43, Anisa 11:36) — dan
**sembilan-sembilannya belum punya catatan harian**. Semua melihat layar yang
menyangkal absen mereka. Ini kelas kegagalan-senyap yang paling mahal: orang
menyimpulkan fiturnya rusak, atau mencoba absen berulang kali.

**Sudah diperbaiki dan live di DEV** (belum di prod): begitu absen tersimpan,
catatan hariannya langsung dihitung untuk orang itu saja. Aman dilakukan
tengah hari — perhitungannya idempoten, hanya menyentuh satu orang satu hari,
melewati periode gaji yang sudah ditutup, dan **tidak** memvonis alpa untuk
hari yang masih berjalan (penjaga itu memang sudah ada sejak awal). Sapuan
06:10 tetap jadi vonis final; ini mempercepat, bukan menggantikan. Teruji di
lokal & dev untuk dua jenis aturan kerja (baku & fleksibel), dijalankan dua
kali tetap satu baris. Kalau perhitungannya gagal, absennya tetap sah.

**DUA HAL UNTUK CHAT PROD:**
1. Naikkan kode terbaru, lalu jalankan **sekali**
   `php artisan att:reconcile 2026-08-05 2026-08-05` — supaya sembilan orang
   yang sudah terlanjur absen hari ini langsung terlihat, tanpa menunggu besok.
   (Prod per 13:10 masih di `f309061`, belum memuat perbaikan ini.)
2. **Keputusan Ekky, bukan teknis**: di prod ada **6 catatan "alpa" tanggal
   4 Agustus** (Afrina, Anisa, Asih, Firda, Irma, Rizka), dibuat sapuan pagi
   tadi karena 4 Agustus hari kerja tanpa satu pun scan — padahal absen HP
   memang baru dipakai 5 Agustus. Sistemnya benar, premisnya yang salah.
   Pilihan: hapus enam baris itu, tandai 4 Agustus sebagai pengecualian, atau
   biarkan lalu dikoreksi lewat pengajuan. **Risiko yang sama berulang tiap
   hari** untuk orang beraturan "baku" yang belum absen lewat HP dan datanya
   belum diunggah dari mesin sidik jari.

## 2026-08-05 — Hari pertama absen HP: 9 orang kantor masuk ABG + temuan WiFi Bintaro

### Orang kantor masuk ABG (SELESAI, prod + dev)

Yovanka, Talitha, Alif, Dani, Hari, dan Robi **tidak punya aturan kerja sama
sekali**. Daftar Kehadiran dibangun dari `att_user_rule`, jadi mereka tak
muncul di halaman Kehadiran mana pun — bukan cuma tak ada di grup ABG. Mereka
bisa absen (Yovanka & Talitha sudah absen pagi ini) tapi kehadirannya tak
pernah dinilai, dan justru kena flag `ip_asing`, karena pengecualian flag hanya
berlaku bagi yang beraturan Fleksibel.

Keputusan Ekky: keenamnya masuk ABG, dan semua penghuni ABG beraturan
Fleksibel. Terpasang lewat `2026_08_05_abg_fleksibel.sql`. **ABG kini 9 orang.**
Sisa tanpa aturan tinggal 11 akun peran bersama (Admin\*, Sales\*, Executive
Pamulang) — memang tidak boleh punya aturan, itu akun jabatan, bukan orang.

Catatan kecil yang menyelamatkan: email Dani/Hari/Robi ternyata `@stdc.id`,
bukan `@sweettreats.id`. Kalau tidak diperiksa dulu, SQL-nya akan melewati
mereka bertiga tanpa pesan apa pun.

### Temuan: "WiFi = saksi" belum berjalan di Bintaro

Pagi ini 7 orang absen dari Bintaro dan **tak satu pun cocok dengan WiFi
terdaftar** — semuanya lewat jalur manual pilih-cabang. Yang cocok WiFi hanya
Reza di Sunter. Lima orang kena flag `ip_asing`; Anisa sudah meninjau semuanya
lalu mendaftarkan tiap IP yang muncul, satu per satu, jam 11.17–11.18.

**Anisa tidak salah** — dia melakukan persis yang disediakan sistem. Yang keliru
premisnya. Dua sebab, dua-duanya terbukti dari data hari ini:

1. **Satu WiFi punya dua wajah.** Jaringan dual-stack punya IPv4 dan IPv6
   sekaligus; HP mana memakai yang mana tergantung perangkat. Bukti: di halaman
   WiFi Cabang, IP Ekky terbaca `180.252.168.224` (IPv4, sama persis dengan
   Yovanka), sedangkan saat absen ia tercatat sebagai IPv6
   `2001:448a:2092:2313:...`. Satu HP, satu WiFi, dua alamat yang tak mirip.
2. **Alamatnya berputar.** Prefix IPv6 yang didaftarkan 25 Juli (`ab6`) sudah
   tidak dipakai hari ini, dan IPv4 IndiHome bersifat dinamis.

Kantor Bintaro punya 3 WiFi, jadi menutupnya butuh 6 entri (3 sambungan × 2
wajah), bukan 3 — dan itu pun tidak akan bertahan.

**Yang menenangkan**: IP tak cocok **tidak pernah menolak absen**, hanya meminta
orang memilih cabang lalu memunculkan flag. Jadi taruhannya bukan "orang tak
bisa absen", melainkan flag palsu tiap hari dan pekerjaan pendaftaran tanpa
ujung bagi Anisa.

**Perkembangan penting**: Ekky punya akses admin ke router kantor (Xiaomi, di
belakang modem IndiHome `192.168.1.1`). Itu membuka jalan yang jauh lebih murah
dan cepat daripada menunggu IP statis Telkom: matikan IPv6 di WiFi staf supaya
tiap sambungan hanya punya satu wajah, lalu pasang DDNS supaya alamatnya punya
nama tetap dan aplikasi tinggal mengikuti namanya. Belum dieksekusi, menunggu
keputusan.

## 2026-08-05 — Akar dari semua "menu hilang": PROD menjalankan kode basi (DIPERBAIKI)

Berangkat dari empat permintaan Ekky (cabut Promo Talitha, Pengajuan pindah ke
drop-up, HP mendarat di absensi, dan "sidebar Anisa kok belum berubah"), yang
ketemu jauh lebih dalam.

### Temuan utama: prod memuat kelas dari berkas basi

`php artisan access:rebuild` di prod **selama ini menjalankan kode tertanggal
4 Juni**. Sebabnya: prod menyimpan **162 salinan berpenanda tanggal** (sisa era
FileZilla, mis. `RebuildGroupAccess20260604.php`) di dalam `app/`, dan
salinan-salinan itu mendeklarasikan **nama kelas yang sama** dengan berkas
aslinya. Saat Artisan memindai folder Commands, berkas basi ikut dimuat lebih
dulu, lalu versi asli tak pernah dipakai lagi.

Akibatnya nyata dan sempat membingungkan: perbaikan menu sudah benar di dev,
dinaikkan ke prod, `access:rebuild` dijalankan — **dan tidak terjadi apa-apa**.
Dev bersih dari salinan semacam ini (nol berkas), jadi masalahnya hanya muncul
di prod. Ini juga berarti setiap "kok di prod nggak ngefek" di masa lalu layak
dicurigai ke arah yang sama.

**Beres**: 162 berkas dipindahkan (bukan dihapus) ke
`~/backups/shadow-app-2026-08-05/`, autoloader dibangun ulang, cache
dibersihkan. Terverifikasi kelasnya kini dimuat dari berkas yang benar, dan
20 command lain memang sudah sehat sejak awal. Semua berkas itu **tak terlacak
git**, jadi tak ada yang hilang dari repo.

### Kenapa menu Anisa & Irma tak pernah muncul

Paket sales Anisa (Pasien, Doctor, Reservation, Laporan Penjualan, Dashboard
Sales) dan akses Doctor+Reservasi Irma sudah di-ACC dan sudah di-grant sejak
5 Agu — **dan tak pernah terlihat**. Menu dirender sebagai pohon yang hanya
dibangun dari node level-0 yang di-grant; halaman yang diberikan tapi akarnya
tidak ikut diberikan hilang tanpa suara. Ini penyebab yang sama dengan insiden
15 node saat gerbong naik dan menu personal role lama — **tiga kali dalam dua
hari, satu akar masalah**.

Diperbaiki di kodenya: pembangun pohon kini menyisipkan leluhur yang tak
di-grant sebagai **wadah** dengan link dikosongkan — murni soal tampil, bukan
izin. Diuji dan terbukti: `hasMenuAccess()` tetap menolak, grant di tabel tak
bertambah, tapi menunya muncul. `Set > Group > Access` juga disatukan ke
implementasi yang sama, karena dulu ia menyalin logika pohon sendiri — sekali
admin menyimpan akses lewat layar itu, perbaikan ini akan hilang diam-diam.

**Jebakan yang hampir kena**: sempat direncanakan memperbaikinya dengan
meng-grant akar "Data Center" saja. Untung diuji di dev dulu — node akar itu
membawa link `MetricaSetting`, jadi itu sama dengan **memberi Anisa & Irma
halaman yang justru dikecualikan** waktu paket sales diputuskan. Ternyata
halaman itu bahkan tidak ada (tak ada rutenya, 404), jadi link matinya
dikosongkan supaya akar itu jadi wadah murni dan aman di-grant kapan pun.

### Yang berubah untuk pengguna

- **Pengajuan pindah ke drop-up akun.** Mengajukan pengeluaran itu kerjaan
  pribadi; buktinya bagi Anisa & Yovanka section "Keuangan" di menu utama
  berdiri hanya untuk menampung satu baris itu. Antrian & Rekap tetap di menu
  utama karena itu kerjaan role Finance/pengelola.
- **Dibuka dari HP, halaman pertama jadi Absensi Saya.** Sengaja sempit: HP
  saja (tablet & komputer tidak berubah), hanya saat masuk (tautan langsung
  tak pernah dialihkan), dan hanya untuk yang punya akses absen — akun peran
  bersama (Sales, Admin, Super Admin, Branch Executive) tidak ikut, terverifikasi.
- **Anisa** kini punya Pasien, Doctor, Reservation, Laporan Penjualan,
  Dashboard Sales. **Irma** punya Doctor + Reservation.
- **Talitha** tak lagi punya menu Promo (keputusan Ekky).
- **Baris kembar dibersihkan**: Anisa sempat akan melihat "Reservasi" empat
  kali dan "Laporan Penjualan" dua kali (node kembar lama). Admin General juga
  ikut dirapikan — akun itu sudah melihat dua baris untuk satu halaman
  sebelum ini.

### Hasil audit & verifikasi

Sebelum dan sesudah naik prod, sapuan yang sama dijalankan: **nol baris
kembar, nol section kosong, nol peran tanpa menu, nol kebocoran izin,
nol menu yang mengarah ke halaman tak ada** (satu-satunya tadinya
`MetricaSetting`, sudah dibereskan), dan **nol role yang landing-nya tak
di-grant**. Ke-17 peran yang ada penghuninya dirender satu per satu di dev
dan di prod. Log aplikasi prod bersih.

## 2026-08-05 — Yovanka tak bisa absen → ketemu lubang akses yang lebih gawat (DIPERBAIKI di prod)

Ekky melapor: drop-up akun Yovanka (Head Marketing) cuma berisi "Ganti kata
sandi" dan "Keluar" — menu **Absensi Saya hilang**, jadi ia tak bisa absen.

**Bukan akibat batch rapi-rapi.** Dibuktikan, bukan dikira-kira: seluruh
pasangan (peran, node) dari backup pra-deploy dibandingkan dengan keadaan
sesudahnya — **nol grant hilang**, 376 → 397 baris, murni bertambah.

**Akar masalahnya dari gerbong 4 Agu**: waktu itu node menu + grant disalin
dev→prod hanya untuk **8 role BARU**. Role LAMA yang sudah lama ada di prod —
Executive, Head Marketing, Content Creator, Creative — tak pernah kebagian
node personal. Ini kelas kegagalan yang sama dengan insiden 15 node: yang
kurang adalah DATA, dan gagalnya diam.

**Temuan yang jauh lebih gawat, ketemu sambil menelusuri**: Head Marketing dan
Executive adalah **penyetuju** di `att_approval_rule`, tapi tak punya menu
Inbox Persetujuan sama sekali. Sejak persetujuan jadi satu lapis hari ini,
Executive adalah satu-satunya penyetuju bagi para head/SPV (Yovanka, Della,
Ekky, Anisa, Irma) dan Head Marketing satu-satunya bagi Talitha & Alif. Tanpa
inbox, **pengajuan pertama akan menggantung tanpa gejala apa pun** — pengaju
merasa sudah mengirim, penyetuju tak pernah tahu. Belum ada korban semata-mata
karena prod belum punya satu pun pengajuan absensi.

**Perbaikan** (`database/sql/2026_08_05_menu_personal_role_lama.sql`, sudah
jalan di prod + dev, `access:rebuild` sudah):
- **Absensi Saya** untuk Executive, Head Marketing, Content Creator, Creative
  → 6 orang nyata kini bisa absen: Yovanka, Talitha, Alif, Dani, Hari, Robi.
- **Inbox Persetujuan** untuk Head Marketing & Executive → rantai persetujuan
  tersambung. Diverifikasi: **nol peran penyetuju yang tanpa inbox**.

**Sengaja TIDAK diberi** (keputusan sadar, ditulis di berkas SQL-nya):
- Absen untuk **akun-peran bersama** — Sales (6 akun cabang), Admin, Admin
  General, Admin Backup, Super Admin, Branch Executive. Itu akun peran, bukan
  orang; memberi mereka absen sama dengan mengarang data kehadiran. Orangnya
  absen dari akun pribadi masing-masing.
- Inbox untuk Content Creator & Creative — mereka bukan penyetuju, inboxnya
  dijamin selalu kosong. Dev memberi grant ini, prod sengaja beda.

**Selisih dev vs prod yang SUDAH BENAR begini (jangan "dilengkapi")**: menu
Accurate (proyek di-pause), Lembar Gaji (sengaja disembunyikan di prod, belum
selesai), dan node cruft berawalan garis-miring (`/my-checklist`,
`/report/executive`) yang cuma ada di dev.

**Masih menggantung, butuh keputusan Ekky** (tidak diubah sepihak):
- **Yovanka tak punya menu Promo** (`marketing-promo`) padahal Talitha
  (Content Creator) punya — atasan tak bisa melihat yang stafnya kelola.
- Super Admin (Admin_Reza) tak punya menu Checklist di prod, dev punya.
- Admin/Admin General/Admin Backup/Super Admin tak punya Petty Cash di prod.

## 2026-08-05 — Batch rapi-rapi NAIK PROD (4 item, terverifikasi)

Prod sekarang berjalan di `main` @ `b26eb26`. Keempat item batch "rapi-rapi
pasca-launch" sudah live: sidebar Absensi jadi pintu tunggal "Kehadiran",
gerbang sopan absen HP di komputer, persetujuan absensi satu lapis, dan
sidebar Marketing tertata tiga kelompok. Tidak ada perubahan aset (CSS/JS),
jadi rsync `public_html` tidak diperlukan kali ini.

**Diaudit ulang dari nol sebelum naik** (chat HUB, bukan mengandalkan laporan
chat pembuatnya). Yang diperiksa dan hasilnya:

- Klaim "baris sidebar yang disembunyikan memang berujung 403" **dibuktikan
  langsung di kode**: keempat controller (Upload, Mapping, Rekap, Periode)
  benar-benar dijaga `canSeeAllBranches`. Jadi yang hilang dari sidebar
  memang pintu buntu, bukan halaman yang bisa dipakai.
- Grant node "Kehadiran" **persis sama** dengan daftar `canSeeAllBranches`
  (Super Admin, Admin General, Admin Backup, Finance) — tidak lebih.
- Kasus paling mudah salah **ditangani benar**: Executive hanya memegang Rekap
  Pengeluaran tanpa Antrian, dan kode sengaja mengecualikannya dari aturan
  penyembunyian — kalau tidak, satu-satunya pintunya ikut hilang.
- Prasyarat prod dicek dulu, bukan diasumsikan: induk menu "Absensi" dan
  "Marketing" ada, node kol/koc sudah ada (jadi blok pembuatnya no-op),
  14 halaman marketing masing-masing tepat satu node, dan aturan persetujuan
  tetap 13 baris.
- **Risiko "Executive kosong" tidak berlaku di prod**: ada 3 penghuni aktif
  (Robi, Hari, Dani). Catatan untuk Ekky: **Hari** juga berperan Executive,
  jadi ia ikut menerima pengajuan para head/SPV — kalau itu tidak diinginkan,
  perannya perlu diubah.
- Backlog persetujuan warisan **nihil**: prod belum punya satu pun pengajuan
  absensi (`att_request` kosong), jadi `absensi:selesaikan-l2` memang no-op.

**Urutan eksekusi**: backup DB prod (`~/backups/prod_pre_rapi_2026-08-05.sql.gz`,
45 MB) → `deploy.sh main` → 2 SQL (`2026_08_05_sidebar_kehadiran`,
`2026_08_05_sidebar_marketing`) → `access:rebuild` + `cache:clear` +
`view:clear` → `absensi:selesaikan-l2`.

**Verifikasi prod — semua lulus**: nol link menu dobel; typo "Kode Refferal"
sudah jadi "Kode Referral"; tiga section marketing berdiri dengan 14 halaman
di kelompok yang benar; urutan menu lain tidak bergeser (parent dikali sepuluh
sekali saja, section baru menyelip di 41–43 antara Marketing 40 dan Keluhan
50); tidak ada node yatim; berkas akses hasil rebuild memuat "Kehadiran" untuk
tepat empat peran itu dan tidak untuk Supervisor; situs 200; **0 error** di log
aplikasi.

**Branch**: `feature/rapi-pasca-launch` digabung maju (fast-forward) ke
`feature/rop`, `release/2026-08-pengeluaran`, dan `main` — keempatnya kini di
`b26eb26`, jadi tidak ada lagi cabang yang tertinggal. WIP chat pola-interaksi
di worktree `feature/rop` tidak tersentuh (12 berkasnya tidak beririsan dengan
11 berkas batch).

**Yang perlu diketahui pengguna**: menu berubah, jadi semua orang perlu
logout-login sekali supaya sidebar barunya muncul.

## 2026-08-05 — Batch rapi-rapi: AUDIT + NAIK DEV (terverifikasi, prod belum)

Dev sekarang menjalankan branch `feature/rapi-pasca-launch` (5 commit, dari
feature/rop). **Prod TIDAK disentuh** — dikerjakan Ekky di chat terpisah.

**Audit adversarial dulu (dua penelaah, kode + SQL).** Area inti dinyatakan
bersih; 6 temuan, yang layak sudah diperbaiki sebelum naik:

- **BUG NYATA, ketemu & difix**: SQL Kehadiran meresolusi induk lewat link
  `absensi` — pembawa link itu BEDA antar server (lokal: induk "Absensi";
  dev/prod: anaknya "Upload Data"), jadi di dev/prod node-nya tak akan pernah
  dibuat dan gagalnya DIAM. Diganti resolusi lewat nama induk. Ini persis
  kelas jebakan yang bikin insiden 15 node saat gerbong kemarin.
- **Jaring pengaman ditambah**: `absensi:selesaikan-l2` kini juga dijadwalkan
  harian 06:25, bukan cuma langkah manual — kalau satu server lupa
  menjalankannya, pengajuan warisan sembuh sendiri, tidak beku diam-diam.
- **Jejak audit**: finalisasi menulis baris persetujuan seperti ACC manusia,
  jadi "siapa yang menyelesaikan ini" tetap terjawab dari riwayat.
- **Kedua SQL dibungkus transaksi** — gagal di tengah = tak berubah sama
  sekali, jadi aman diulang (sebelumnya, putus di titik tertentu bisa membuat
  urutan menu terkali dua kali).
- **Gerbang absen HP**: alasan tertulisnya dikoreksi (halaman ini memakai
  kamera langsung, bukan pemilih berkas — yang dijaga adalah absen dari meja
  mana pun), dan halaman di baliknya dimatikan betulan supaya Tab+Enter tak
  bisa menjalankan alur kamera tak terlihat.
- **Dicatat, tidak diubah** (perilaku lama, di luar batch): kalau role
  Executive tak punya penghuni aktif, pengajuan para head bisa lolos sendiri.
  Di dev aman — Robi & Hari dua-duanya aktif sebagai Executive.

**Verifikasi dev — semua lulus:**
- Data menu: nol duplikat link, typo "Kode Refferal" hilang, node Kehadiran
  terpasang, **8 grant link `absensi` utuh** (kunci akses seluruh /absensi/*),
  3 section marketing berdiri, urutan menu lain tak bergeser.
- Sidebar per peran: Finance melihat "Kehadiran" (tab-tab lama sudah tidak
  muncul), Head Marketing melihat tepat 3 kelompok baru, Executive tetap
  memegang Rekap Pengeluaran, Head IT/Supervisor/FO tak lagi melihat baris
  absensi yang dulu berujung 403.
- **41 halaman dijalankan per peran: semua 200**, termasuk seluruh halaman tab
  (masih terjangkau dari bar tab walau tak ada di sidebar) dan 14 halaman
  marketing. Yang bukan haknya tetap ditolak dengan pesan jelas.
- Persetujuan: 16 jabatan semua 1 lapis dengan penyetuju benar; 2 pengajuan
  warisan difinalkan + jejaknya tercatat; sisa pending_l2 = nol.
- Absen HP: gerbang komputer tampil utuh, alur kamera halaman tetap utuh.
- Log dev bersih (0 error aplikasi).

**Catatan koordinasi**: kerjaan ini belum digabung ke `feature/rop` — worktree
branch itu sedang dipakai chat pola-interaksi, dan penggabungannya perlu
dilakukan dari sana (atau dengan izin terpisah). Dev untuk sementara berjalan
di branch batch ini, yang isinya = feature/rop + 4 item ini.

**Kalau nanti naik prod, urutannya**: 2 SQL (`2026_08_05_sidebar_kehadiran`,
`2026_08_05_sidebar_marketing`) → `php artisan access:rebuild` →
`php artisan absensi:selesaikan-l2` → pastikan role Executive ada penghuninya.

## 2026-08-05 — Batch "rapi-rapi pasca-launch" SELESAI LOKAL (4 item, menunggu review → dev)

Keempat item yang di-ACC kemarin selesai dibangun & diuji di lokal (branch
`feature/rapi-pasca-launch` dari feature/rop; belum di-commit, menunggu review
Ekky sebelum naik dev). PROD TIDAK disentuh.

- **(1) Sidebar Absensi = pintu tunggal "Kehadiran"** — Upload Data / Mapping /
  Rekap Persetujuan / Periode Gaji keluar dari sidebar, cukup lewat bar tab
  yang sudah ada di dalam halaman; "Rekap Pengeluaran" ROP sama (tombol Rekap
  di Antrian sudah ada sejak awal). Dikerjakan lewat KODE render menu
  (`shellTabbedLinks` di Helpers) — grant TIDAK dicabut, kunci AuthGuard
  `absensi` aman (jebakan prod kemarin tak terulang). Node menu "Kehadiran"
  baru via SQL `2026_08_05_sidebar_kehadiran.sql` (grant hanya role yang
  memang boleh buka: Finance/Admin General/Admin Backup/Super Admin).
  Bonus: entri sidebar yang selama ini berujung 403 (mis. Upload Data di
  Head IT/IT) ikut hilang — kegagalan senyap berkurang. Role Executive tetap
  pegang "Rekap Pengeluaran" (satu-satunya pintu dia — dilindungi khusus).
  Teruji per 6 role × 6 halaman: menu, sorot aktif, judul header benar.
- **(2) Absen HP ditutup sopan di komputer** — /akun/absen dibuka dari desktop
  kini menampilkan penjelasan "absen lewat HP/tablet di titik absen cabang"
  (+ arahan Koreksi Absensi kalau darurat), tanpa tombol lanjut. HP + TABLET
  tetap normal (deteksi: pointer mouse tanpa layar sentuh). Menu TIDAK
  disembunyikan — halaman tetap ada, isinya yang menjelaskan.
- **(3) Persetujuan absensi jadi SATU lapis** — staf di-ACC atasan langsung,
  selesai (FO/GS/Sales→Supervisor; Asdent→Koordinator ATAU Supervisor;
  Creative/CC→Head Marketing; IT→Head IT); pengajuan milik head/SPV/
  koordinator/Finance di-ACC role Executive (Dani ATAU Robi, siapa cepat).
  13 aturan `att_approval_rule` TIDAK berubah (isinya memang sudah atasan
  langsung) — yang dipangkas plafon lapis di engine (semua jenis form = 1).
  Pengajuan lama yang keburu menunggu lapis-2 difinalkan otomatis command
  baru `absensi:selesaikan-l2` (wajib dijalankan sekali saat deploy; kalau
  tidak, mereka macet tak terlihat selamanya). Teruji: rantai 11 role semua
  1 lapis, approver benar; command idempoten.
  ⚠️ Prasyarat data di dev/prod: akun Robi ber-role Executive (di lokal baru
  Dani) — tanpa itu praktisnya hanya Dani yang menerima.
- **(4) Sidebar marketing 3 kelompok** — "Dashboard & Laporan" (Dashboard
  Marketing, Report Referral, Klaim Komisi) · "Konten" (Konten Pillar,
  EP Konten, Breakdown, Campaign Framework, Review Konten, Keyword) ·
  "Referral & Member" (Kode Referral, Affiliate, KOL, KOC, Sweet Mates).
  SQL `2026_08_05_sidebar_marketing.sql`: pindah node BY LINK (id beda antar
  env aman), parent section baru + grant mengikuti pemegang anak, typo
  "Refferal"→"Referral" dibereskan sekalian, node KOL/KOC dibuat hanya di
  env yang belum punya. Promo sengaja tetap di Data Center (di luar
  keputusan). Idempoten — diuji jalan 2×, hasil sama.
- **Deploy dev nanti butuh**: 2 SQL di atas + `php artisan access:rebuild` +
  `php artisan absensi:selesaikan-l2` + verifikasi akun Robi (Executive).

## 2026-08-05 — Penyesuaian hari pertama prod (feedback Ekky)

- **Supervisor (Nisa) dapat paket pengawasan sales** di prod & dev: Reservasi,
  Pasien, Doctor, Laporan Penjualan, Dashboard Sales (karena dia SPV sales;
  MetricaSetting sengaja tidak — wilayah IT).
- **Lembar Gaji disembunyikan di prod** (fitur belum selesai; dev tetap ada).
- WiFi "Sunter" yang kemarin dicurigai salah label ternyata BENAR — Ekky
  memang menguji dari cabang Sunter.
- Ditemukan keterikatan arsitektur: node menu "Upload Data" (link `absensi`)
  adalah syarat gerbang semua halaman /absensi/* — tidak bisa dicabut dari
  sidebar tanpa perubahan kode (dibuktikan: dicabut → Aturan Kerja ikut mati
  → dikembalikan).
- **Batch "rapi-rapi pasca-launch" di-ACC Ekky**, dikerjakan menyusul:
  (1) sidebar absensi dikonsolidasi — "Kehadiran" jadi pintu tunggal, Upload/
  Mapping/Rekap/Periode jadi tab (tab bar-nya sudah ada); Rekap Pengeluaran
  ROP sama; (2) absen HP: akses dari desktop ditutup sopan (kekhawatiran:
  desktop bisa unggah file foto, bukan kamera langsung); (3) persetujuan
  absensi jadi SATU lapis — staf di-ACC atasan langsung (Nisa/Irma), pengajuan
  head/SPV/koordinator di-ACC Executive (Dani ATAU Robi); (4) sidebar
  marketing ditata 3 kelompok (struktur datanya tidak berubah oleh gerbong —
  tampilan beda karena shell baru reskin).

## 2026-08-04 tengah malam — 🚀 GERBONG NAIK PROD (deploy besar selesai)

- **Prod (app.sweettreats.id) sekarang menjalankan gerbong penuh** — commit
  `06938ed`: absensi + absen HP + Pusat Akun + cuti + lembar gaji + checklist/
  AC + dashboard marketing + login baru + reskin zona + ROP + Aturan Kerja.
- Sebelum naik: fitur Aturan Kerja diuji fungsional di dev (10/10 lolos:
  CRUD aturan, log jejak, penugasan backdate ditolak, kalender libur +
  tumpang-tindih ditolak, gate role, hapus bersih).
- Eksekusi prod: backup DB 45MB → merge rilis→main (1 konflik komentar) →
  deploy kode → **8 role baru** (UUID = dev) → **32 SQL** (2 via mysql CLI
  karena DELIMITER) → **24 migration** → 9 akun karyawan baru + 3 akun
  marketing dipindah ke email @sweettreats.id → akses FO = salinan Sales →
  WiFi Bintaro+Sunter didaftarkan → saldo cuti dibuka (9 akun) → 75 vendor +
  CoA Accurate ditarik → rsync aset.
- **Insiden saat deploy, ketemu & dibereskan**: 15 node menu (Absensi, Akun,
  Persetujuan, Checklist) ternyata tak pernah dibikin SQL mana pun — dulu
  dibuat manual di dev → disalin utuh ke prod + seluruh grant 8 role
  disalin dari dev (50 baris). Pelajaran: grant BY LINK diam saja kalau
  node-nya tak ada.
- Verifikasi akhir per peran di prod: Head IT, Finance, Supervisor, FO —
  semua halaman kunci 200, yang bukan haknya tertolak, log prod 0 error.
  12/12 akun baru terkunci wajib-ganti-password.
- Kredensial akun: file LOKAL (gitignored), BUKAN di Drive.
- PR: tidak lewat PR — merge langsung sesuai jendela deploy malam ini.
- **Checklist prod DIBERESKAN juga (tanpa nunggu Reza)** — 403 Supervisor
  ternyata 3 lapis data yang hilang di prod: (1) bagan role (parent/level)
  belum diisi → disalin dari dev (17 role); (2) tabel divisi checklist prod
  KOSONG → 3 divisi (FO/GS/ASDENT) disalin; (3) posisi user belum di-sync →
  dijalankan logika resmi UserPosition::syncFromUser utk 12 akun (4 staf
  operasional dapat posisi). Hasil: Nissa bisa buka dashboard/review/setup/
  monitoring-AC checklist di prod (semua 200) — siap setup data besok;
  Firda dapat form checklist FO berisi tugas.
- Tersisa (bukan penghalang): cek cron hPanel `schedule:run`, 14 karyawan
  sisanya dibuat via Pusat Akun, kabari kredensial (terutama 3 akun marketing
  yang emailnya berubah).

## 2026-08-04 malam — Fitur Aturan Kerja SELESAI di lokal (halaman 3 tab)

- Halaman **Aturan Kerja** (`/absensi/aturan`, menu seksi Absensi) selesai
  dibangun & lolos uji di lokal — branch baru `feature/aturan-kerja` dari
  `feature/rop`. Tiga tab sesuai rencana:
  1. **Aturan** — daftar aturan (GS / FO-Asdent / Fleksibel) + tambah/ubah/
     hapus, editor jam per hari (libur, window masuk, pulang, mulai lembur).
     Ubah aturan yang dipakai orang → konfirmasi "berlaku untuk N karyawan
     mulai besok".
  2. **Penugasan** — tabel karyawan → aturan aktif; ganti aturan = baris baru
     ber-tanggal-mulai, baris lama ditutup otomatis (riwayat = jejak gaji,
     tidak pernah diedit). Plus **default per role** untuk akun baru
     (Pusat Akun otomatis pasang aturan saat akun dibuat).
  3. **Kalender Libur** — libur nasional/tanggal khusus (input manual SKB
     3 Menteri), mekanisme = baris exception pola SQL Lebaran, nol perubahan
     mesin. Libur ≠ cuti bersama (ada rambu + link ke halaman Cuti).
- Rel pengaman sesuai rencana: gate role di controller (Supervisor + Head IT +
  Finance + Super Admin — Supervisor ditambah Ekky saat review 2026-08-04),
  semua perubahan berlaku KE DEPAN saja (backdate ditolak),
  mesin rekonsiliasi TIDAK disentuh, tiap perubahan tercatat di log baru
  `att_rule_log` (siapa, kapan, dari→ke — tampil di bawah halaman).
- Uji: 28/28 skenario headless lulus (guard 403, CRUD aturan, tutup-baris-lama,
  engine baca aturan lama utk kemarin & baru utk hari ini, overlap libur
  ditolak, libur lampau terkunci) + smoke HTTP login Head IT → halaman render
  utuh dengan menu sidebar & JS aktif.
- Audit putaran kedua (sesi sama) menemukan & memperbaiki 5 hal: (1) KRITIS —
  section JS salah nama (`script` vs yield layout `js`) bikin seluruh JS
  halaman mati; (2) hapus aturan meninggalkan default-role menggantung →
  bikin AKUN BARU bisa 500, kini default ikut dilepas + hook Pusat Akun
  defensif; (3) nama aturan/libur tak lagi diinterpolasi ke JS confirm
  (celah self-XSS); (4) old() dibatasi ke form create saja (gagal-validasi
  satu form tadinya menulari nilai semua editor — bahaya rename tak sengaja);
  (5) minor: atribut `data-toggle` diganti `data-buka` (hindari data-API
  Bootstrap), colspan empty-state log, log rule.ubah simetris dari↔ke.
- Deploy nanti butuh: `database/sql/2026_08_04_aturan_kerja.sql` +
  `php artisan access:rebuild`. BELUM di-commit — menunggu review Ekky.

## 2026-08-04 malam — Deploy prod ditunda menunggu fitur Aturan Kerja; demo besok

- Keputusan Ekky: deploy prod MENUNGGU fitur Aturan Kerja selesai — fitur itu
  naik prioritas jadi dikerjakan sekarang dan ikut gerbong rilis.
- Besok (2026-08-05) demo di office/ABG + cabang Bintaro memakai dev — data
  demo absensi/cuti/ROP sudah terisi.
- Hotfix keamanan sweet-mates tidak ikut ditunda; tetap siap naik duluan.
- Aturan Kerja dibangun TANPA brief desain claude.ai/design — halaman setting
  internal, mengikuti pola desain yang sudah ada; hanya komponen editor
  jam-per-hari yang baru.

## 2026-08-04 — Paket "Pengaturan Operasional" di-ACC: 2 fitur, 2 pemilik

- Dua fitur baru direncanakan & di-ACC Ekky, dikerjakan setelah prod stabil:
  1. **Aturan Kerja (Ekky)** — UI kelola jam kerja, penugasan aturan per
     karyawan (default per role), kalender libur nasional. Selama ini semua
     itu harus lewat SQL. Rencana: `docs/rencana/rencana-aturan-kerja.md`.
  2. **Penempatan (Reza)** — staf rolling/backup ke cabang lain tercatat
     resmi; checklist otomatis ikut cabang penempatan (selama ini salah
     gedung); Nissa & Irma boleh menempatkan staf mana pun di bawah bagan
     (lintas domain). Rencana: `docs/rencana/rencana-penempatan.md`.
- Keputusan kunci: cabang asal karyawan TIDAK PERNAH diubah untuk rolling;
  penempatan wajib bertanggal akhir; jam kerja tidak ikut penempatan.
- Prompt chat build Aturan Kerja siap di `docs/spec/prompt-chat-baru.md` §E.
  Rencana Penempatan siap diserahkan ke Reza (basis branch `feature/rop`).

## 2026-08-04 — Dev diisi data demo + fix grup cabang Anisa/Irma

- Dev diisi data dummy untuk demo: 943 hari-kerja absensi (1 Jul–4 Agu, 27
  karyawan, pola realistis: hadir/telat/alpa/libur-Senin), 10 pengajuan
  cuti/izin/WFH/lembur approved, 7 potongan saldo cuti, dan 14 pengajuan ROP
  lintas status (menunggu ACC s/d selesai, termasuk 1 urgent, 1 ditolak,
  1 kadaluarsa). Halaman Kehadiran, Cuti, antrian ROP, dan Rekap sekarang
  hidup untuk demo.
- Penanda dummy: att_day rentang 1 Jul–4 Agu; att_request & mutasi cuti
  berawalan "[DEMO]" — gampang dibersihkan saat mau data asli.
- Fix data: Anisa & Irma tadinya nongol "Belum ada cabang" di kalender —
  kolom cabang mereka kosong. Diisi Sunter (cabang asal sesuai roster).
  Catatan: mereka TIDAK masuk grup "Kantor (ABG)" karena grup itu khusus
  aturan jam-bebas (tak dinilai), sedangkan mereka dinilai jam FO-Asdent.

## 2026-08-03 — Push Reza ke-2 masuk + bug 500 checklist difix

- Kerjaan baru Reza (export Excel checklist harian/mingguan + pengecualian
  per-pertanyaan) di-merge ke branch integrasi `feature/rop`, nol konflik,
  sudah live & diuji di dev.
- Bug dari kerjaan itu ditemukan & difix: halaman /checklist error 500 karena
  `@if` Blade ditulis menempel kata (`tugas@if`). Dev hijau lagi, log 0 error.
- Catatan deploy prod nambah 1: SQL checklist Reza wajib dijalankan via mysql
  CLI (pakai DELIMITER), bukan lewat tinker.
- Dibuat `docs/status-proyek.md` (potret utuh proyek) + file log ini.
- Status gerbong prod: `release/2026-08-pengeluaran` ikut maju, isi lengkap,
  masih masa uji dev. Deploy prod direncanakan minggu ini.

## 2026-08-01 sore — Ganjalan terakhir gerbong ditutup (glaccount)

- DDL Accurate + command `accurate:pull-glaccount` dipetik dari proyek
  Accurate (yang di-pause) ke gerbong rilis — tanpa ini Finance tidak bisa
  memilih akun beban saat meng-ACC pengajuan di prod.
- Dibuktikan di dev: 6 buku Accurate tertarik (135–183 akun per buku).
- Gerbong prod dinyatakan LENGKAP secara teknis. Keputusan menyusul yang
  disepakati: hotfix sweet-mates naik prod DULUAN sendirian; PR gerbong
  dibuka nanti saat isinya beku (bukan sekarang).

## 2026-08-01 — Semua kerjaan menyatu di satu branch + rilis dipotong

- Reskin zona Absensi (Kehadiran kalender, tema per zona, emoji→ikon) selesai
  testing → di-commit → digabung ke `feature/rop`. Konflik dengan kerjaan
  Reza ternyata sepele (2 emoji), selesai.
- Fix jadwal-khusus-dokter-vs-cuti ikut gerbong (keputusan Ekky).
- Branch rilis `release/2026-08-pengeluaran` dipotong (±91 commit, ±298
  berkas) — kandidat PR ke main.
- Manifest deploy prod dirapikan: 30 SQL urut, 19 migration, 8 role + 29
  akun, gotcha rsync aset. Rujukan: `docs/deploy/manifest-prod.md`.
- Aturan kerja baru disepakati: SEMUA kerjaan bermuara ke `feature/rop`;
  Reza cabang dari sana; tidak ada branch lompat langsung ke main.

## 2026-08-01 pagi — ROP live di dev, digabung dengan kerjaan Reza

- Fitur Pengajuan/ROP fase 1 (pengganti Formulir A kertas) selesai dibangun,
  diaudit (7 bug internal difix), dan di-deploy ke dev bersama kerjaan Reza
  (cuci AC, foto checklist, dashboard marketing).
- Master vendor terisi otomatis dari Accurate (73 vendor).
- Gerbang peran terverifikasi: Finance & Head IT bisa buka antrian; Supervisor
  bisa mengajukan tapi tidak bisa ACC; staf biasa tidak bisa mengajukan.
- Ditemukan & dipetakan: 2 dependensi prod baru (tabel akun Accurate + menu
  induk "Keuangan") — masuk manifest.
