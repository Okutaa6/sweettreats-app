# Rencana — Belajar-sendiri untuk IPv4 (cocok-penuh, tanpa pelebaran)

> **Status**: ✅ **DIBANGUN & LULUS DEV 2026-08-27** — branch
> `feat/wifi-ipv4-belajar`, **prod belum**. Hasil uji terima §7 ada di §9 di
> bawah; jurnalnya di `docs/log-update.md` 27 Agu. ACC Ekky 2026-08-23.
> Tambalan manualnya sudah jalan di prod (§2) — ini yang membuatnya berhenti
> butuh manusia. Semua angka diverifikasi ke DB **prod** 2026-08-23.

---

## 1. Lubangnya

`WifiBelajarSendiri` **sengaja melewati IPv4**. Docblock-nya menyatakan
alasannya: *"KENAPA HANYA IPv6: itulah satu-satunya yang berputar (IPv4 cabang
bertahan…) … IPv4 tak pernah lewat"* — asumsinya IPv4 cabang stabil, jadi cukup
didaftarkan manual **sekali**.

Asumsinya masuk akal. Yang tak diantisipasi: **sebuah cabang bisa tak pernah
kebagian pendaftaran manual itu sama sekali.**

Kalideres punya **nol baris IPv4** sampai 23 Agu 2026. Akibatnya **13 absen
dalam 3 minggu harus PILIH CABANG MANUAL** — belajar-sendiri tak menolong
(lewat IPv4), pencocokan /48 tak menolong (cuma IPv6). Jatuh di antara dua
jalur, dan tak ada yang berteriak.

Cinere juga nol. Sunter cuma satu.

## 2. Tambalan manual yang sudah jalan (23 Agu)

10 alamat didaftarkan manual dengan pagar: **≥2 orang berbeda**, **pilihan
cabang konsisten satu**, **tak pernah dipakai cabang lain sepanjang sejarah**.
Dari 39 kandidat: **8 lolos, 31 ditolak** (mayoritas cuma 1 orang).

Hasil putar-ulang 21 hari: **158 kegagalan → 128 kini cocok & benar**, 29 sisa
(semuanya alamat 1-orang, di bawah ambang), 1 beda cabang (kasus lama Irma).
Uji 16/16: kedelapan alamat cocok, **kedelapan tetangganya (oktet +1) tetap
ditolak**.

⚠️ **Ini stopgap.** IPv4 berotasi; daftar itu akan basi.

## 3. Yang perlu dibangun

**Ambang saksi yang SAMA dengan IPv6** — `AMBANG_ORANG = 2` dalam
`JENDELA_JAM = 72` — tapi hasilnya baris **cocok-penuh**, bukan prefix.

Bukti kelayakan yang harus dipenuhi sebelum sebuah IPv4 didaftarkan otomatis:

1. **≥ AMBANG_ORANG orang BERBEDA** memakai alamat itu dalam jendela.
2. **Semua memilih cabang yang SAMA** secara manual.
3. Alamat itu **tak pernah** dipakai memilih cabang lain sepanjang sejarah.
4. Alamat itu **belum** terdaftar untuk cabang mana pun.

Kenapa "satu alamat dipakai banyak orang" itu bukti kuat: kalau itu data
seluler pribadi, tiap orang dapat alamat sendiri. Alamat yang dipakai 4 orang
berbeda dalam 5 hari **adalah jaringan bersama**. `36.77.252.85` dipakai Anisa,
Rivanti, Cici, dan Reza — semuanya memilih Kalideres.

## 4. ⚠️ JANGAN melebarkan IPv4 ke /24 (atau apa pun)

Bukan cuma berisiko — **tak akan menolong.** Alamat satu cabang melompat antar
blok yang tak berkerabat sama sekali. Kalideres memakai **`36.77.251/252.x`
DAN `180.242.129–131.x`** dalam periode yang sama. Tak ada satu /24 pun yang
menampung keduanya, jadi melebarkan cuma membuka tetangga tanpa menutup
masalahnya.

Dan pagar tetangga itu nyata: `180.252.169.218` (Bintaro) bersebelahan dengan
`180.252.169.219` yang **harus** ditolak — sudah dikunci uji K14.

Sebaran alamat IPv4 unik per cabang, 21 hari (bukti lompatannya):
Kalideres **9** dari 17 absen · Sunter **8**/16 · Bintaro **7**/16 ·
Cinere **5**/8 · Pamulang **2**/5.

## 5. ⚠️ Pelajaran yang harus dibawa: jangan tebak dari bentuk alamat

Penulis dokumen ini sempat menyimpulkan absen ber-IPv4 itu "orang pakai data
seluler". **Ekky bertanya langsung ke Cici, dan dia memakai WiFi klinik.**
Datanya kemudian membenarkan Cici.

Ini pengulangan pelajaran peta WiFi Bintaro (*"satu WiFi dua wajah IPv4+IPv6 —
jangan tebak 'beda alamat = beda WiFi'"*). Bukti yang sah itu **perilaku**
(berapa orang berbeda memakainya, dan cabang apa yang mereka pilih), bukan
bentuk atau blok alamatnya.

## 6. Tinjauan: hanya untuk /48 yang BENAR-BENAR baru

Temuan sampingan 23 Agu: kelima baris `sumber = otomatis`
(id 20, 21, 22, 25, 26) semuanya **se-/48 dengan baris yang sudah ada** — sejak
perbaikan /48 (21 Agu) mereka **sudah cocok sendiri tanpa didaftarkan**.
Menandainya "perlu ditinjau" tak menambah apa-apa.

Tinjauan sebaiknya dinyalakan **hanya untuk /48 yang tak dikenal sama sekali** —
di situlah WiFi rumah seseorang bisa terdaftar sebagai jaringan cabang. Yang
se-/48 langsung disahkan saja.

(id 25 sudah ditandai diverifikasi Ekky di lokasi Kalideres, 23 Agu.)

## 7. Uji terima

| # | Yang diuji | Lulus kalau |
|---|---|---|
| 1 | 2 orang berbeda, 1 cabang konsisten, dalam 72 jam | alamat **didaftarkan** cocok-penuh |
| 2 | 1 orang saja, berkali-kali | **TIDAK** didaftarkan |
| 3 | 2 orang tapi memilih cabang **berbeda** | **TIDAK** didaftarkan |
| 4 | Alamat pernah dipakai cabang lain di masa lalu | **TIDAK** didaftarkan |
| 5 | Tetangga oktet +1 dari alamat terdaftar | **ditolak** (nol pelebaran) |
| 6 | `180.252.169.219` vs `…218` terdaftar | **ditolak** — K14 tetap hijau |
| 7 | `::ffff:` IPv4 menyamar sebagai IPv6 | tetap lewat jalur IPv4, bukan prefix |
| 8 | IPv6 | perilaku **tak berubah** sedikit pun |
| 9 | `att:wifi-uji` 15 kasus lama | tetap hijau |
| 10 | Putar ulang absen prod yang gagal | jumlah yang cocok **naik**, nol yang hilang, nol pindah cabang |
| 11 | /48 baru sama sekali | **TAWARKAN** ke IT, bukan didaftarkan diam-diam |
| 12 | /64 se-/48 dengan yang sudah ada | disahkan tanpa minta tinjauan (§6) |

Uji 10 wajib memakai **data prod yang diputar ulang**, di dalam transaksi yang
dibatalkan — pola yang sama dengan perbaikan /48 (21 Agu) dan libur-cabang
(22 Agu).

## 8. Yang sengaja TIDAK dikerjakan

1. **Jangan melebarkan IPv4** ke /24, /16, atau apa pun (§4).
2. **Jangan mengubah jalur IPv6** — sudah 87% cocok dan baru saja diperbaiki.
3. **Jangan mencabut** 10 baris IPv4 yang didaftarkan manual 23 Agu; biarkan
   sampai penggantinya terbukti jalan.
4. `180.242.129–131.x` (jalur kedua Kalideres, berotasi lintas /24) tetap tak
   bisa ditangani cocok-penuh maupun pelebaran. **Pantau**, jangan dipaksa.
5. Prod menunggu dev lulus §7.

---

## 9. Hasil uji terima — DEV LULUS 2026-08-27

`att:wifi-uji` **15 → 22 kasus, 22/22 hijau**. Halaman WiFi Cabang dirender
sungguhan (bukan cuma `php -l`). Nol SQL, nol `public/assets/**`.

| # | Yang diuji | Hasil | Buktinya |
|---|---|---|---|
| 1 | 2 orang, 1 cabang, 72 jam | ✅ | K16 — daftar di orang ke-2, alamat penuh |
| 2 | 1 orang berkali-kali | ✅ | K17 — `orang_kurang`, n=1 berapa kali pun |
| 3 | 2 orang cabang berbeda | ✅ | K18 — tak terdaftar untuk cabang mana pun |
| 4 | Pernah dipakai cabang lain | ✅ | K19 — `pernah_cabang_lain` walau n=2 |
| 5 | Tetangga oktet ±1 | ✅ | K16 — `.84 .86 .251.85 .185` semua ditolak |
| 6 | `180.252.169.219` vs `.218` | ✅ | K14 tetap hijau, tak disentuh |
| 7 | `::ffff:` menyamar | ✅ | K20 — jalur IPv4, dua bentuk jadi SATU baris |
| 8 | IPv6 tak berubah | ✅ | K1–K5, K7–K13 hijau tanpa diubah |
| 9 | 15 kasus lama tetap hijau | ✅ | K6 **berubah arti** (lihat bawah); K10 diperbaiki karena data dev |
| + | Pagar alamat-dalam (temuan audit) | ✅ | K22 — `10.x`/`127.0.0.1`/`100.64/10` tak bisa terdaftar otomatis |
| 10 | Putar ulang absen prod | ✅ | 130 tertolong · **0 hilang** · **0 pindah** |
| 11 | /48 baru sama sekali | ✅ | K7 — TAWARKAN, bukan daftar diam-diam |
| 12 | /64 se-/48 tanpa tinjauan | ✅ | K21 + render halaman: 1 disorot dari 11 |

### ⚠️ K6 berubah arti — jangan dikira rusak

Harapan lamanya *"IPv4/CGNAT TIDAK PERNAH daftar"*. Itu tak bisa dipertahankan
bersamaan dengan §3, dan §5 menjelaskan kenapa: `114.10.70.167` (CGNAT
Telkomsel) dan `110.137.194.229` (WiFi Bintaro, diverifikasi manusia) **identik
dari sisi bukti** — satu alamat, tiga orang Bintaro, sepuluh menit. Membedakan
keduanya menuntut menebak dari bentuk alamat, kesalahan yang sudah pernah dibuat.

K6 kini mengunci **pertahanannya**, bukan penolakannya: cocok-penuh (tetangga
tetap ditolak) + label `PERLU DITINJAU` + notifikasi ke tiap role peninjau.
Kalau salah satu dari itu lepas, K6 yang merah duluan.

### Putar ulang data prod — dua putaran, dua pertanyaan berbeda

Dijalankan dari **worktree terpisah** di server prod. ⚠️ `vendor` harus
**disalin**, bukan di-symlink: lewat symlink, autoloader Composer menghitung
`$baseDir` dari path yang sudah di-resolve sehingga `App\…` menunjuk balik ke
`app/` prod — simulasinya diam-diam menjalankan kode LAMA. Aplikasi prod yang
hidup tak disentuh; `att_branch_ip` 38 & `att_hp_bukti` 685 sama sebelum-sesudah.

**A — tabel apa adanya (regresi murni), 685 absen**: tertolong **130**,
hilang **0**, pindah **0**, tetap gagal 44.

**B — 10 baris manual 23 Agu dicabut dulu** ("apakah mesin menangkapnya sendiri"):
**7 dari 11** ter-belajar, **semuanya lebih awal dari manusia** (16–21 Agu vs
23 Agu). Plus **2 alamat baru** yang belum pernah didaftarkan siapa pun dan
**sedang gagal cocok hari ini**: `36.69.103.246` (Sunter, 25 Agu) dan
`180.251.182.171` (Pamulang, 26 Agu). Tiga "hilang" di putaran ini semuanya di
`125.165.188.209` — alamat yang memang dicabut paksa, bukan regresi.

### ⚠️ Aturan ini LEBIH KETAT dari sapuan manual — dan itu butuh keputusan

Empat alamat manual tak tergantikan, sebabnya satu dan sama: **cuma punya SATU
saksi yang sah**, sisanya orang fleksibel (yang `raw_branch_id`-nya memang
kosong, jadi syarat "cabang asal sama" tak bisa diperiksa).

| alamat | pemakai | saksi sah |
|---|---|---|
| `110.137.194.229` | Asih · Yovanka · Alif · Ikhsan | 1 |
| `180.252.80.82` | Irma · Verini | 0 (Verini asalnya Sunter, barisnya Cinere) |
| `125.165.189.132` | Asih · Della P. · Talitha | 1 |
| `125.165.188.209` | Asih · Della P. · Anisa · Talitha | 1 |

→ **§8-3 terbukti: jangan cabut sepuluh baris manual 23 Agu.**

**Pilihan yang tersedia, belum diambil**: izinkan orang fleksibel jadi saksi
**PENDUKUNG** (butuh ≥1 staf asal **dan** total ≥2). Keempatnya akan tertangkap.
Alternatif ini sudah pernah dicatat waktu ambang diturunkan 3→2 dan belum
pernah ditolak — tapi ia melonggarkan satu-satunya pagar struktural yang dimiliki
jalur IPv4, jadi keputusannya milik Ekky, bukan milik chat build.

### Audit pra-prod 27 Agu — dua temuan, keduanya ditutup

Diminta Ekky sebelum naik prod: uji ulang di dev + audit bug & inkonsistensi.
Selain 22 kasus, dijalankan pemeriksa terpisah untuk jalur yang TIDAK disentuh
kasus mana pun — masukan aneh ke `nilai()` & `perluTinjau()`, kedua pintu
pendaftaran lewat HTTP, `matchBranch` untuk dua bentuk tulisan, dan render
halaman Pengawasan + WiFi Cabang. Semua di dalam transaksi yang dibatalkan.

**⚠️ TEMUAN 1 (bug, ditutup) — alamat DALAM bisa terdaftar otomatis.**
`127.0.0.1`, `10.0.0.5`, `0.0.0.0` semuanya lolos sampai ke penghitungan saksi.
Konsekuensinya baru kelihatan kalau dirangkai: seandainya aplikasi suatu saat
berada di belakang proxy yang `TrustProxies`-nya belum dipasang — risiko yang
sudah tercatat sejak audit absen-HP — maka `request()->ip()` berhenti menjadi
alamat karyawan dan berubah jadi **satu alamat yang sama untuk semua orang**,
sering `10.x`. Dua orang cabang mana pun langsung memenuhi ambang, alamat itu
terdaftar, lalu **seluruh absen di lima cabang terbaca sebagai satu cabang** —
sementara flag `ip_asing` berhenti berbunyi justru saat ia paling dibutuhkan.

Ditutup dengan pagar `ipv4Publik()`: menolak privat, loopback, link-local,
ruang cadangan, dan `100.64/10` (CGNAT bersama RFC 6598, tak terjaring filter
bawaan PHP). **Ini bukan menebak identitas dari bentuk alamat** — §5 melarang
itu dan benar; ini fakta struktural: alamat-dalam tak bisa menjadi alamat
internet publik sebuah cabang. Pagar hanya menahan **mesin**; manusia tetap
boleh mendaftarkannya manual, dan baris yang sudah ada tetap dihormati
(`nilai()` menjawab `sudah_cocok`, bukan menimpanya). Jalur IPv6 tak
membutuhkannya: ULA & link-local tak akan sekerabat /48 dengan blok cabang.
Dikunci **K22**.

**⚠️ TEMUAN 2 (inkonsistensi, ditutup) — dokumentasi membantah kodenya sendiri.**
Dua tempat:
- Docblock kelas `WifiBelajarSendiri` masih membuka dengan *"mendaftarkan prefix
  IPv6"* + "EMPAT SYARAT" versi IPv6, padahal 20 baris di bawahnya menyatakan
  IPv4 ikut. Pembaca yang berhenti di atas menyimpulkan hal yang salah — persis
  asal-usul lubang ini.
- Komentar di titik panggil (`AttendanceHpAbsen`) menulis *"yang KETIGA"*
  (ambangnya 2 sejak 18 Agu) dan *"prefix IPv6 berputar mingguan"*.

Keduanya diluruskan, dan masing-masing kini membawa peringatan agar angka
aturan & keluarga alamat **tidak diketik ulang** di luar tempat asalnya —
pelajaran yang sama dengan "3 orang" yang bertahan di layar berbulan-bulan.

Sisanya hijau: 10 masukan aneh ke `nilai()` tak ada yang melempar dan tak ada
yang bervonis DAFTAR · kedua pintu pendaftaran membakukan `::ffff:` dan menolak
bentuk kembarannya sebagai duplikat · `matchBranch` menjawab sama untuk kedua
bentuk dan tetap menolak tetangga · Pengawasan & WiFi Cabang render dengan baris
IPv4 otomatis terpasang · jumlah baris disorot sama dengan angka banner.

### Catatan: K10 sempat merah karena DATA DEV

`users.raw_branch_id` Alpajri **kosong di dev** (aturan `baku`), jadi ia tak
pernah bisa jadi saksi. Diperiksa ke prod: **prod normal** — Alpajri di Bintaro,
dan **tak ada** orang ber-aturan `baku` yang cabang asalnya kosong. Dipastikan
bukan regresi dengan menjalankan K10 di `main`: merah juga di sana. K10 kini
mencari sendiri kejadian saat ambang tercapai; urutan ambang tetap dikunci K7.
