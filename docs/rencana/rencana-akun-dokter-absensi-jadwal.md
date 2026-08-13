# Arah Absensi Dokter — berbasis jadwal praktik (§6)

> Status: **ARAH DIPUTUSKAN — pilihan C (tipe `jadwal`) ACC Ekky 13 Agu 2026.**
> Satu pertanyaan masih terbuka: **K1** (cara bayar dokter) — perlu tanya Finance.
> Sikap sementara untuk K1 ada di §4 K1; ia TIDAK memblokir pembangunan.
> Prasyarat §8 butir 3 dari `docs/rencana/rencana-akun-dokter.md`.
> Basis kode: `integrasi/dev-2026-08-06`. Semua temuan di bawah dibaca dari kode,
> bukan dugaan — nomor barisnya ditulis supaya bisa diperiksa ulang.

Rencana induk §6 menyimpulkan dokter terjepit dua kotak: diberi cabang asal →
layar terkunci satu cabang; dikosongkan seperti orang kantor → jam kerjanya tak
dinilai. Setelah kodenya dibaca, **jepitan itu ternyata lebih sempit daripada
dugaan** — sebagian sudah beres sendiri. Dokumen ini memisahkan yang sudah beres
dari yang benar-benar perlu diputuskan.

---

## 1. Tiga hal yang ternyata SUDAH beres

Ini mengubah pertanyaannya, jadi ditaruh paling depan.

**① Absen lintas cabang sudah jalan hari ini, tanpa perubahan apa pun.**
`AttendanceReconciler::reconcileUser()` mengambil scan **hanya berdasarkan
`user_id`** — tak ada penyaringan cabang sama sekali
([AttendanceReconciler.php:332-336](../../app/Services/AttendanceReconciler.php#L332-L336)) —
lalu menggabungkan seluruh scan satu orang dari SEMUA mesin jadi satu hari kerja
dan mencatat cabang mana saja yang terlibat di kolom `cabang_terlibat`
([:514](../../app/Services/AttendanceReconciler.php#L514)). Ini memang dibangun
sengaja untuk Rohma yang berpindah cabang. Dokter yang Senin tap di Pamulang dan
Rabu di Bintaro sudah tercatat benar sekarang juga.

**② Cabang layar juga sudah punya jalurnya.** Perbaikan "cabang efektif" dari
rencana gelar Cinere-Pamulang §5 sudah terpasang di branch ini:
`cabangSesiUntuk()` mengisi cabang sesi saat login dari posisi hari itu, bukan
mentah-mentah dari `users.raw_branch_id`
([Helpers.php:815-822](../../app/Helpers.php#L815-L822)). Kalau kelak dokter
memang perlu cabang sesi, sambungannya satu titik — bukan 51.

**③ Untuk layar dokter sendiri, cabang sesi hampir tak relevan.** Tiga layar di
brief desain menyaring per-**dokter**, bukan per-cabang: "reservasi hari ini" =
`raw_cus_reservation.raw_doctor_id = saya`, "jadwal saya" = jadwal miliknya
sendiri lintas cabang. Sedangkan layar Absen mengambil cabang dari **IP tempat
dia berdiri**, dengan pilihan manual sebagai cadangan
([AttendanceHpAbsen.php:50-58](../../app/Services/AttendanceHpAbsen.php#L50-L58)) —
bukan dari sesi.

> **Akibatnya:** "diberi cabang asal → layar terkunci" **bukan lagi alasan untuk
> mengosongkan `raw_branch_id` dokter.** Yang tersisa dari §6 tinggal satu
> pertanyaan sungguhan: **hari praktik dokter dinilai atau tidak, dan dengan apa.**

---

## 2. Yang benar-benar belum ada

Mesin absensi mengenal **dua** jenis aturan saja
([2026_07_18_absensi_work_rule.sql:41](../../database/sql/2026_07_18_absensi_work_rule.sql#L41)):

| tipe | perilaku | cocok untuk dokter? |
|---|---|---|
| `baku` | jam per hari-dalam-seminggu dari `att_work_rule_day`; hari kerja tanpa scan → **alpa** | tidak — lihat §3 |
| `fleksibel` | scan cuma penanda, **tak pernah** alpa, tak pernah dinilai | tidak — itu persis yang §6 tolak |

Dan yang paling menentukan: **jadwal menempel pada ATURAN, bukan pada ORANG.**
`att_work_rule_day` berkunci `(rule_id, day)`. Dua dokter dengan hari praktik
berbeda **tidak bisa berbagi satu aturan** — padahal keenam dokter gelombang
pertama punya pola berbeda-beda (1 sampai 5 baris jadwal, cabang berlainan).

Sementara itu jawaban yang dicari sudah ada di tempat lain: trait
`ResolvesDoctorAvailability::resolveWorkingRanges()`
([:303-365](../../app/Http/Controllers/Concerns/ResolvesDoctorAvailability.php#L303-L365))
sudah menghitung "dokter ini praktik jam berapa di tanggal ini", lengkap dengan
jadwal khusus (`add`/`replace`) dan cuti dokter. Itu mesin yang sama yang dipakai
pasien saat memesan. **Sistem sudah tahu dokter seharusnya di mana hari ini —
mesin absensi saja yang belum boleh bertanya.**

---

## 3. Tiga pilihan

### Pilihan A — satu aturan `baku` per dokter (6 aturan)

Isi `att_work_rule_day` tiap dokter dari jadwal prakteknya.

- ✅ Nol perubahan mesin.
- ❌ **Dua sumber kebenaran untuk fakta yang sama.** Jadwal praktik diubah di
  halaman Dokter (dan itu sering — ada 57 baris jadwal khusus di prod), aturan
  kerjanya tidak ikut berubah. Yang terjadi bukan error, melainkan **alpa palsu
  di hari dokter memang tak praktik** — persis kelas kegagalan-senyap yang sudah
  dua kali menggigit proyek ini.
- ❌ **Cabang tak bisa diungkapkan sama sekali.** `att_work_rule_day` tak punya
  kolom cabang. Jadi gagasan inti §6 — "hadir di cabang yang sesuai jadwalnya
  atau bukan" — mati di sini.
- ❌ **Jadwal khusus tak bisa diungkapkan.** `att_work_rule_exception` berlaku
  per-cabang atau nasional, **tak pernah per-orang**
  ([:74](../../database/sql/2026_07_18_absensi_work_rule.sql#L74)). Jadwal khusus
  seorang dokter tak punya tempat di sana.

### Pilihan B — `fleksibel`

- ✅ Nol pekerjaan, dan dokter tak pernah salah dituduh bolos.
- ❌ Tak dinilai sama sekali — yang §6 sebut tidak memadai.
- ❌ Ada efek samping yang tak kelihatan dari namanya: bagi orang fleksibel,
  **SEMUA flag pengawasan dimatikan**
  ([AttendanceHpAbsen.php:97](../../app/Services/AttendanceHpAbsen.php#L97)).
  Dokter yang absen dari jaringan asing, dari laptop, atau dari perangkat yang
  dipakai akun lain **tidak memunculkan apa pun**. Itu keputusan yang layak
  diambil sadar, bukan kebetulan ikut terbawa label "fleksibel".

### Pilihan C — jenis aturan baru `jadwal` ⭐ rekomendasi

Aturan bertipe `jadwal` **tidak membawa jam sendiri**. Saat rekonsiliasi, mesin
menanyakan jadwal praktik orangnya lewat `users.raw_doctor_id` — sumber yang
sama dengan yang dipakai pasien saat memesan.

- ✅ **Satu sumber kebenaran.** Jadwal diubah di halaman Dokter → penilaian
  absensi ikut berubah hari itu juga. Tak ada yang perlu diingat untuk
  disinkronkan, jadi tak ada yang bisa lupa.
- ✅ **Jadwal khusus & cuti dokter ikut gratis** — `resolveWorkingRanges()` sudah
  menanganinya, termasuk aturan halus "`replace` menembus cuti, `add` membuka
  hari libur".
- ✅ **Cabang jadi bisa diungkapkan.** Jadwal menyebut cabang per hari;
  `cabang_terlibat` sudah dicatat tiap hari. Membandingkan keduanya = gagasan
  inti §6, dan bahan-bahannya sudah ada dua-duanya.
- ✅ Nol dampak ke ±16 karyawan yang ada: mereka tetap `baku`/`fleksibel`, dan
  cabang `jadwal` baru dimasuki kalau `tipe = 'jadwal'`.
- ⚠️ Ongkosnya: `MODIFY` satu enum + satu cabang resolve di reconciler + varian
  `resolveWorkingRanges` yang **tak dikunci satu cabang** (yang ada sekarang
  wajib menerima `$branchId`). Sedang, bukan besar.

---

## 4. Empat keputusan yang diminta

Tiga di antaranya bukan soal teknis — ini soal apa artinya bagi orangnya.

### K1. Dokter tak scan di hari praktiknya — apa vonisnya?

Rencana induk §5 menyebut dokter kemungkinan **dibayar per tindakan, bukan gaji
bulanan**. Kalau benar, kata "alpa" jadi label tanpa konsekuensi: ia dibuat untuk
memotong gaji, dan akan tampil di rekap Finance sebagai angka menakutkan yang
tidak berarti apa-apa.

- **Usul:** status terpisah (mis. `tak_scan`) yang **tidak masuk hitungan gaji** —
  jujur bahwa hari itu tak ada jejak, tanpa menuduh bolos. Ongkos: satu `MODIFY`
  enum `att_day.status`, persis seperti yang sudah dilakukan untuk `sakit`
  ([2026_08_11_aturan_sakit.sql:29](../../database/sql/2026_08_11_aturan_sakit.sql#L29)).
- **Perlu Ekky pastikan dulu:** dokter memang tidak bergaji bulanan? Kalau
  ternyata bergaji, `alpa` biasa justru yang benar dan usul ini gugur.

> **Sikap sementara (Ekky 13 Agu: belum tahu, perlu tanya Finance).**
> Sampai jawabannya datang, hari praktik tanpa scan **tidak menghasilkan baris
> `att_day` sama sekali** — bukan `alpa`, bukan status baru. Alasannya:
>
> - Tidak menuduh siapa pun sebelum artinya diketahui, dan tak ada angka
>   menyesatkan yang masuk rekap Finance.
> - Tak perlu `MODIFY` enum sekarang — jadi keputusan K1 nanti tetap terbuka
>   penuh ke dua arah, tak terlanjur dikunci oleh sesuatu yang sudah dipasang.
> - **Aman untuk dibalik.** Reconcile idempoten, dan `hapusHariBasi()` sudah
>   membersihkan baris yang tak lagi dihasilkan
>   ([AttendanceReconciler.php:302-325](../../app/Services/AttendanceReconciler.php#L302-L325)).
>   Begitu K1 dijawab, reconcile ulang rentangnya dan barisnya muncul dengan
>   vonis yang benar — tanpa membersihkan apa pun dengan tangan.
>
> ⚠️ Yang harus ikut dibangun bersamanya: **hitungan** hari praktik tanpa scan
> per dokter, dilaporkan seperti `tanpa_aturan` sudah dilaporkan
> ([:251-256](../../app/Services/AttendanceReconciler.php#L251-L256)). Tanpa itu,
> "tidak menghasilkan baris" berubah dari sikap sadar menjadi kegagalan-senyap —
> persis kelas cacat yang dulu menelan 538 scan Head IT.

### K2. Dokter hadir di cabang yang tidak sesuai jadwalnya — apa artinya?

- **Usul: flag pengawasan, bukan penolakan dan bukan alpa.** Ikut filosofi yang
  sudah berlaku — *"WiFi = SAKSI bukan GERBANG"*
  ([AttendanceHpAbsen.php:19-20](../../app/Services/AttendanceHpAbsen.php#L19-L20)).
  Dokter bisa saja tukar jadwal dengan rekannya dan itu sah; yang sistem tahu
  cuma "hari ini beda dari biasanya". Jenis flag baru (mis. `cabang_beda_jadwal`)
  langsung masuk layar Pengawasan yang sudah ada, dan bisa ditutup borongan.
- Ongkos: satu `MODIFY` enum `att_flag.jenis`
  ([2026_07_24_absen_hp_core.sql:92](../../database/sql/2026_07_24_absen_hp_core.sql#L92)).

### K3. Telat berlaku untuk dokter?

Di mesin `baku`, telat **mendorong jam wajib pulang**
([AttendanceReconciler.php:419](../../app/Services/AttendanceReconciler.php#L419)) —
logika gaji. Bagi dokter, datang telat konsekuensinya beda sama sekali: **pasien
yang sudah memesan slot itu menunggu.**

- **Usul:** catat `telat_menit` sebagai informasi, **jangan** turunkan
  `wajib_pulang`. Angkanya berguna untuk klinik, hukumannya tidak berlaku.

### K4. `users.raw_branch_id` dokter — diisi atau dikosongkan?

Alasan lama untuk mengosongkan sudah gugur (§1). Yang tersisa: kolom itu masih
dipakai reconciler untuk **dua** hal — mencocokkan `att_work_rule_exception`
per-cabang ([:566-584](../../app/Services/AttendanceReconciler.php#L566-L584))
dan mengunci periode gaji yang sudah ditutup
([:613-622](../../app/Services/AttendanceReconciler.php#L613-L622)). Dikosongkan
berarti: libur cabang tak berlaku untuknya (libur nasional tetap berlaku), dan
penutupan periode per-cabang tak membekukan barisnya.

- **Usul: ISI dengan Pamulang.** Keenam dokter gelombang pertama praktik di sana,
  jadi itu cabang yang paling sering benar. NULL di sistem ini berarti "lihat
  semua cabang" — bukan makna yang kita mau, dan bukan makna yang aman untuk data
  pasien.

---

## 5. Jebakan yang sudah terverifikasi — jangan ditemukan ulang saat membangun

1. **Penomoran hari BERBEDA antara dua dunia ini.**
   `raw_doctor_schedule.day` memakai ISO — **1=Senin … 7=Minggu**
   ([ResolvesDoctorAvailability.php:61](../../app/Http/Controllers/Concerns/ResolvesDoctorAvailability.php#L61)).
   `att_work_rule_day.day` memakai Carbon — **0=Minggu … 6=Sabtu**
   ([2026_07_18_absensi_work_rule.sql:56](../../database/sql/2026_07_18_absensi_work_rule.sql#L56)).
   Menyambung keduanya tanpa konversi **menggeser jadwal semua dokter satu hari**,
   dan gagalnya diam — Minggu (7 vs 0) kasus terburuknya.

2. **`resolveWorkingRanges()` wajib menerima satu cabang.** Untuk absensi kita
   justru butuh "semua cabang di tanggal ini". Perlu varian baru, bukan pemanggilan
   ulang per cabang — memanggil per cabang berarti cuti dan `replace` dievaluasi
   berkali-kali dengan hasil yang bisa saling bertentangan.

3. **Jadwal khusus & cuti dokter ada di balik feature flag.**
   `config('features.flexible_scheduling')` (sekarang `true`). Kalau flag itu
   mati, jadwal khusus **hilang tanpa pesan** dari perhitungan absensi juga.

4. **Reconciler hanya menyapa orang yang punya baris `att_user_rule`**
   ([:235](../../app/Services/AttendanceReconciler.php#L235)). Dokter tanpa baris
   itu **tak akan pernah muncul di rekap** — scannya jadi yatim, dan itu persis
   kegagalan yang dulu menelan 538 scan Head IT tanpa satu pun pesan
   ([:251-256](../../app/Services/AttendanceReconciler.php#L251-L256)).

5. **`hariLiburTerjadwal()` membaca `att_work_rule_day`**
   ([Helpers.php:1109-1136](../../app/Helpers.php#L1109-L1136)) — dipakai sebagai
   gerbang di layar Absen. Untuk aturan `jadwal` yang tak punya baris hari, ia
   mengembalikan `null` = "tak bisa dijawab", dan layar sengaja **tidak** memasang
   gerbang atas ketidaktahuan. Jadi aman secara default, tapi perlu diajari
   membaca jadwal praktik supaya gerbang hari-libur dokter benar-benar berfungsi.

6. **Dua jalur cuti — pengingat dari rencana induk §5.** `raw_doctor_leave`
   (menutup kursi booking) vs `att_request` (berdampak gaji). Begitu dokter punya
   akun dan melihat menu pengajuan, jalur karyawan jadi **terjangkau olehnya**.
   Kalau dokter mengajukan cuti lewat sana, cutinya tercatat untuk gaji tapi
   **kursi prakteknya tetap terbuka untuk pasien**. Rencana induk sudah menandai
   ini; yang baru: risikonya menjadi nyata pada hari akun dibuat, bukan nanti saat
   fitur cuti dokter dibuka.

---

## 6. Kalau C disetujui — urutan kerjanya

1. `att_work_rule.tipe` tambah `'jadwal'`; buat satu aturan bernama "Dokter"
   (tanpa baris `att_work_rule_day`), tugaskan ke enam dokter lewat `att_user_rule`.
2. Varian "semua cabang" dari `resolveWorkingRanges()`, dengan konversi hari
   ISO ↔ Carbon di satu tempat saja.
3. Cabang `jadwal` di `reconcileUser()`: tak praktik → `libur`; praktik + scan →
   `hadir` (+ telat sebagai informasi, K3); praktik tanpa scan → hasil K1.
4. Perbandingan cabang → flag (K2).
5. `hariLiburTerjadwal()` diajari membaca jadwal praktik (jebakan §5.5).
6. Uji dengan data prod asli enam dokter itu — termasuk seorang yang lintas
   cabang dalam satu minggu, satu tanggal berjadwal khusus, dan satu hari Minggu
   (penjaga jebakan §5.1).

---

## 7. Ringkas

| Pertanyaan | Keputusan (13 Agu 2026) |
|---|---|
| Jenis aturan | ✅ **C — tipe baru `jadwal`**, baca jadwal praktik langsung. **ACC Ekky.** |
| K1 tak scan di hari praktik | ⏳ **TERBUKA — tanya Finance cara bayar dokter.** Sementara: tak menghasilkan baris `att_day`, tapi WAJIB dihitung & dilaporkan |
| K2 salah cabang | flag pengawasan, bukan penolakan — usul, belum di-ACC eksplisit |
| K3 telat | dicatat sebagai informasi, tak menurunkan wajib pulang — usul, belum di-ACC eksplisit |
| K4 `raw_branch_id` | **diisi Pamulang**, alasan lama untuk dikosongkan sudah gugur — usul, belum di-ACC eksplisit |

**Satu pertanyaan untuk Finance:** dokter dibayar per tindakan atau gaji bulanan?
Itu satu-satunya yang menentukan apakah "alpa" punya arti bagi mereka.
