# Rencana: WiFi Cabang Belajar Sendiri (auto-daftar prefix baru)

> Status: **DIBANGUN — live di dev, belum prod** (12 Agu 2026, branch
> `feature/wifi-belajar-sendiri`). Delapan kasus §6 lulus diputar ulang di dev
> memakai baris prod asli; satu kasus dikoreksi, lihat §6.
> ACC Ekky 2026-08-12 — ambang **3 orang**, **otomatis penuh**.
> Basis kode: `integrasi/dev-2026-08-06` (= `main` = prod, `645dc12`).
> Dokumen ini ditulis supaya sesi/chat BARU bisa langsung membangun tanpa
> mengulang penyelidikan seminggu terakhir.

## 1. Masalahnya

Prefix IPv6 IndiHome **berputar kira-kira mingguan**. Terukur dari data prod:

| Jalur | Hidup | Umur |
|---|---|---|
| Bintaro `2092:2313` + `2092:134e` | 5–9 Agu | ~5 hari |
| → rotasi → `2092:10d5` + `2092:2777` | 11 Agu–kini | jalan |
| Sunter `2062:208c` | 5–11 Agu | ~6 hari |

Tiap rotasi, semua orang beraturan `baku` di cabang itu kena flag `ip_asing`
sampai ada manusia yang sadar lalu mendaftarkan prefix barunya. 11 Agu: **7
orang** ke-flag dalam sejam. Skalanya akan membesar — 5 cabang × 2 jalur, dan
cabang lain baru mulai digelar mingguan.

⚠️ **Bintaro punya DUA jalur dan keduanya berputar bareng.** Mendaftarkan satu
saja tidak cukup — 11 Agu `10d5` didaftarkan, tapi `2777` terlewat sehingga
Firda (11 Agu malam), Alpajri & Yovanka (12 Agu pagi) tetap ke-flag.

**Ini masalah KEBISINGAN, bukan integritas** — tak ada yang gagal absen (WiFi =
saksi, bukan gerbang). Ongkos sesungguhnya: waktu review Anisa, dan flag jadi
terbiasa merah sehingga kejanggalan yang asli tenggelam.

## 2. Jalan keluar yang dipertimbangkan

| Opsi | Putusan |
|---|---|
| **1. IP statis Telkom** | Ekky kejar harganya. Satu-satunya obat sumber, tapi Telkom lambat — bukan jalan keluar jangka pendek. |
| **2. Cocokkan `/48` (bukan `/64`)** | **TIDAK diadopsi sebagai pencocok.** Temuannya dipakai sebagai pagar di opsi 3. Alasan: `/48` di IndiHome kemungkinan agregat AREA, bukan jatah satu pelanggan → rumah karyawan sekitar klinik bisa ikut cocok, membalik premis WiFi-saksi. Ekky masih menanyakan ke Telkom. |
| **3. Belajar sendiri** | **DIBANGUN.** Isi dokumen ini. |

### Temuan `/48` yang dipakai sebagai pagar

Semua prefix satu cabang **tetap di `/48` yang sama** melewati rotasi, dan tiap
cabang punya `/48` berbeda:

| Cabang | `/48` | `/64` yang pernah terlihat |
|---|---|---|
| Bintaro | `2001:448a:2092` | 2313 · 134e · 10d5 · 2777 |
| Sunter | `2001:448a:2062` | 208c |
| Pamulang | `2001:448a:2020` | 27df |
| Kalideres | `2001:448a:90b0` | ebf |
| Cinere | `2001:448a:2033` | 12b8 |

Jadi kekerabatan `/48` = bukti kuat "gedung yang sama, nomor baru".

## 3. Aturan yang dibangun

Saat absen HP **tidak cocok** jaringan mana pun dan user memilih cabang manual,
periksa `/64` itu:

**Daftarkan OTOMATIS bila SEMUA terpenuhi:**
1. **≥ 3 orang berbeda** pernah memakai `/64` ini
2. ketiganya **cabang asalnya sama** dengan cabang yang dipilih
3. dalam **72 jam** terakhir
4. **`/48`-nya sekerabat** dengan prefix cabang itu yang sudah terdaftar

**Kalau 1–3 terpenuhi tapi 4 TIDAK** (mis. cabang baru yang belum punya prefix
apa pun): **jangan** otomatis — tawarkan sekali klik ke IT.

**Satu orang saja: JANGAN PERNAH otomatis.** Kasus Kalideres (Rizka, 6–7 Agu)
membuktikan kenapa: satu orang tak bisa dibedakan dari WiFi rumahnya sendiri —
waktu itu penyelesaiannya lewat memeriksa foto selfie.

### Kenapa pagar `/48` penting

Dia mencegah skenario "3 karyawan absen bareng dari tempat lain" (mis. pelatihan
di luar) ikut terdaftar sebagai cabang. Sekaligus memetik manfaat opsi 2 **tanpa**
memikul risikonya: rumah karyawan yang kebetulan se-`/48` tetap tak lolos, karena
mustahil ada 3 karyawan cabang yang sama absen dari satu rumah.

### Sisa risiko yang diterima sadar

Kafe/tetangga dekat klinik yang se-`/48` dan didatangi ≥3 staf bisa ikut
terdaftar. Ongkosnya kecil (WiFi cuma saksi) dan ditutup dengan:
- baris hasil auto diberi label jelas **"otomatis — perlu ditinjau"**
- **beri tahu IT** tiap kali auto-daftar terjadi
- gampang dinonaktifkan dari halaman WiFi Cabang yang sudah ada

## 4. Bahan yang SUDAH ADA (jangan bangun ulang)

- `AttBranchIp::matchBranch()` — pencocokan biner `/64`, sudah menangani
  IPv4 & IPv6
- `PengawasanController::daftarkanIp()` — aksi mendaftarkan IP dari flag
- Deteksi "≥3 flag `ip_asing` serentak satu cabang" — `PengawasanController`
  `GRUP_MIN`, dipakai untuk kotak GRUP
- `AttendanceHpAbsen::absen()` — titik pemicu yang tepat (saat absen, bukan
  saat review); di sinilah `$match` dihitung
- Halaman **WiFi Cabang** `/absensi/wifi` — CRUD + aktif/nonaktif
- `att_rule_log` / `AuditLog` — pola pencatatan jejak
- `admin_notification` — pola pemberitahuan

## 5. Kenapa pemicunya harus saat ABSEN, bukan saat review

11 Agu pagi, 7 orang datang berurutan 09:34–10:38. Kalau auto-daftar jalan di
orang **ketiga** (09:43), orang ke-4 sampai ke-7 langsung cocok dan **tak
pernah ke-flag**. Kalau pemicunya di `att:flag-sweep` (06:20 besoknya),
semuanya terlanjur merah — tak ada gunanya.

## 6. Uji yang WAJIB lulus (putar ulang data nyata minggu ini)

| Kasus | Harus | Hasil uji |
|---|---|---|
| `10d5` 11 Agu — 7 org Bintaro/1 jam, se-`/48` | **auto** di orang ke-3; flag tinggal 3 | ✅ auto di Rizka 09:39 |
| `2777` — Firda 11/8 mlm + Alpajri & Yovanka 12/8 pagi | ~~auto saat orang ke-3~~ → **TIDAK** auto, hanya 2 orang memenuhi syarat | ✅ ditolak (lihat koreksi) |
| Cinere `2033:12b8` 7 Agu — 2 org, tak se-`/48` | **TIDAK** auto (tawarkan klik) | ✅ ditolak |
| Kalideres `90b0:ebf` — 1 org | **TIDAK** auto | ✅ ditolak |
| 3 org beda cabang di satu jaringan | **TIDAK** auto | ✅ ditolak |
| Seluler (`114.10.x`, CGNAT) | **TIDAK PERNAH** auto — dipakai jutaan orang | ✅ ditolak walau 3 org |
| 3 org cabang sama tapi `/48` asing | **tawarkan** ke IT, jangan auto | ✅ tawarkan |
| Saklar dimatikan | tak pernah auto | ✅ mati total |
| Orang `fleksibel` | tak ikut hitungan (flag-nya memang di-suppress) | ✅ Talitha & Reza tak menguatkan |

### ⚠️ Koreksi kasus `2777` (12 Agu, setelah data prod diputar ulang)

Kasus 2 sebagaimana ditulis **tidak bisa berlaku** — ia bertabrakan dengan
baris terakhir tabel ini sendiri. Orang ketiga di `2777` adalah **Yovanka, dan
dia berjadwal "Fleksibel Kantor"**. Kalau fleksibel ikut dihitung supaya kasus 2
lolos, maka di kasus 1 Talitha juga ikut — auto bergeser ke orang ke-3 yang
lebih awal dan flag tersisa jadi **2**, bukan 3. Kedua harapan itu tak bisa
benar bersamaan.

Yang dipertahankan: **aturannya** (ACC Ekky 12 Agu). Alasannya bukan sekadar
konsistensi — **semua** orang berjadwal fleksibel punya `cabang asal` KOSONG di
sistem, jadi syarat §3 no. 2 memang tak bisa diperiksa untuk mereka. Mereka juga
justru orang yang paling sering absen dari cabang lain: Talitha tercatat di
Cinere, Pamulang, dan Bintaro dalam sepekan yang sama. Saksi paling lemah untuk
"jaringan ini milik cabang X".

Akibat nyatanya: `2777` tetap perlu didaftarkan manual (dan memang sudah, 12
Agu). Orang Bintaro non-fleksibel ketiga yang menyentuh jalur itu akan
memicunya sendiri.

⚠️ **Jangan pernah mendaftarkan IP seluler.** Rizka 7 Agu absen pulang lewat
Telkomsel `114.10.70.167`. CGNAT — mendaftarkannya = membuka absen dari mana pun.

## 7. Catatan lapangan yang menghemat waktu

🔎 **Perangkat penanda** — ekor `b0cc:8a6a:22f5:8c53` punya interface-id TETAP
(bukan privacy extension) dan muncul di `134e`, `10d5`, DAN `2777`. Kalau ragu
sebuah prefix baru itu Bintaro atau bukan, cari ekor ini.

🐤 **Alpajri = kenari tambang** — General Services, absen paling pagi
(07:35–07:42), jadi dia yang PERTAMA kena tiap rotasi. Flag dia pagi-pagi =
sinyal jaringan berganti nomor.

📶 **Satu WiFi dua wajah** — jaringan yang sama memberi IPv4 DAN IPv6. Ikhsan
11 Agu tak ke-flag bukan karena istimewa, tapi karena HP-nya lewat IPv4
(`180.252.170.180`, sudah terdaftar) sementara rekannya lewat IPv6 yang baru
berputar. **Jangan simpulkan "beda alamat = beda WiFi".**

🕒 **IPv4 jauh lebih stabil daripada IPv6** — `180.252.170.180` bertahan 7 hari
melewati rotasi IPv6. Yang berputar khusus IPv6.

🔴 **Cincin merah di Pengawasan menandai ORANG pada hari itu**, bukan tiap absen.
Satu flag terbuka → semua fotonya hari itu ikut merah, termasuk absen yang
sebenarnya cocok.

## 7b. Sudah dibangun — apa yang ada di mana (12 Agu 2026)

Branch `feature/wifi-belajar-sendiri`, **live di dev, BELUM prod.**

| Berkas | Isi |
|---|---|
| `app/Services/WifiBelajarSendiri.php` | otak keputusan. `nilai()` murni (tak menulis apa pun), `pelajari()` menulis |
| `app/Services/AttendanceHpAbsen.php` | pemicunya — sesudah bukti ditulis, sebelum flag dinilai |
| `app/Console/Commands/WifiBelajarUji.php` | pemutar-ulang 8 kasus §6 lewat jalur absen sungguhan |
| `database/sql/2026_08_12_wifi_belajar_sendiri.sql` | kolom `att_branch_ip.sumber` + saklar `sys_setting` |
| `resources/views/absensi/wifi.blade.php` | kartu saklar, spanduk "perlu ditinjau", baris auto disorot |

**Vonis** yang dikembalikan bukan cuma ya/tidak, tapi beralasan — `bukan_ipv6`,
`orang_kurang`, `kerabat_48_tak_ada`, `saklar_mati`, `sudah_cocok`,
`ditolak_it`, `lengkap`, `galat`. Itu yang membuat kasus "ditolak" bisa
dibedakan satu sama lain saat diuji.

**`ditolak_it`** = baris untuk prefix itu pernah lahir lalu dinonaktifkan IT.
Keputusan itu permanen dan tak pernah ditawar ulang. Sengaja seketat teks
prefix: baris berekor yang mati karena rotasi (mis. `2313` lama) BUKAN veto —
kalau jalur itu kembali dipakai bertiga, memang sudah semestinya dikenali lagi.

**Notifikasi dikirim per role** (`ROLE_PENINJAU = ['Head IT', 'IT']`).
`target_role` harus cocok PERSIS dengan `group_path.nama` dan tak mengenal
hierarki — satu baris "IT" saja berarti Head IT tak pernah diberi tahu. Kalau
nanti ada role baru yang memegang halaman ini, tambahkan namanya di sana; K10
akan menolak nama yang tak ada di `group_path`.

**Hanya IPv6.** Itu yang membuktikan larangan "jangan pernah daftarkan IP
seluler" tanpa perlu menebak operator: IPv4 tak pernah lewat pintu ini, dan
IPv6 seluler tetap gugur di pagar `/48`. `::ffff:` (IPv4 menyamar) ikut ditolak.

**Tiga orang pertama tetap ke-flag.** Itu harga bukti yang mereka kumpulkan, dan
gampang ditutup borongan di Pengawasan.

Menjalankan ulang ujinya: `php artisan att:wifi-uji` (dikunci mati di produksi —
ia menulis data uji). Sesudah selesai, daftar WiFi dipulihkan otomatis.

## 8. Sesudah dibangun

Flag `ip_asing` sisa rotasi bisa ditutup borongan lewat fitur yang sudah live
(Pengawasan → "Semua tanggal" → banner pintar / centang). Per 12 Agu ada **18
flag terbuka** 5–12 Agu, mayoritas sebabnya sama: jaringan telat didaftarkan.
