# Log Update — jurnal milestone

> Entri terbaru selalu DI ATAS. Tiap entri ditulis ringkas & mandiri supaya
> bisa langsung di-copy ke alat task-management (Gemini) tanpa konteks tambahan.
> Potret utuh proyek: `status-proyek.md`. Jangan mengedit entri lama —
> file ini append-only.

---
## 2026-09-09 — Slot kembar & "Terisi" tanpa orang: gerbong `fix/slot-kembar` LULUS DEV, prod menunggu kata Ekky

Laporan 8 Sep: admin mau menambah pasien **drg. Dharma** di **Cinere 10 Sep 17:00** →
jam kembar (9 kotak: 17:00–18:00 muncul dua kali) **dan** 17:00 "Terisi" padahal
daftar Cinere kosong. Ditelusuri ke prod: **dua hal berbeda**, hanya satu yang bug.

- **Kembar = bug.** Rutin Kamis Cinere `17:00–20:00` ditumpuk Hari Tambahan `17:00–18:30`
  (Della, 8 Sep 13:35, alasan "dokter on call"); `computeTimeSlots()` mencetak deret per
  rentang lalu menempelkannya. **Berulang tiap Sabtu** untuk Panji Bintaro (`10:00–20:00` +
  `15:00–20:00` → **10 jam kembar**) tanpa pernah dilaporkan.
- **"Terisi" = BUKAN bug.** Ghita Lestary sudah memesan Dharma di **Kalideres** `17:00–17:30`
  (pending, 5 Sep). `resolveBookedRanges()` sengaja tanpa filter cabang — satu dokter, satu
  tempat. Yang salah **layarnya diam**. Fakta pendukung: 0 dari 931 hari-dokter sejak Jan
  2026 pernah dipakai >1 cabang; jadwal lintas-cabang Dharma/Wira = template on-call.
- **Ops sudah beres sendiri**: Della berkoordinasi — Ghita dibatalkan 14:41, HERI WIDODO
  dipesan Cinere 17:00 pukul 14:54.

### Isi gerbong (branch `fix/slot-kembar` dari main `546044d`, sudah di origin)
1. **`gabungRentangBertindih()`** — rentang bertindih digabung sebelum slot dicetak; yang cuma
   bersentuhan tetap dua sesi; rentang nol/terbalik **TIDAK dibuang** (⚠️ `AttendanceReconciler`
   menilai "dokter dijadwalkan?" dari koleksi kosong/tidak — membuang = menggeser absensi;
   prod punya Dharma Bintaro Minggu `22:00–20:00`). Versi pertamaku membuangnya — ketahuan
   saat audit, sebelum sempat naik.
2. **`after:start_hour`** pada jam PRAKTIK di `scheduleStore/Update` (istirahat sudah dijaga,
   praktik kelupaan → asal baris terbalik). Efek sadar: jadwal lintas tengah malam ditolak
   (prod nol, pencetak slot memang tak bisa).
3. **Commit Reza `a252215`** — ditinjau baris per baris, **lulus semua pagar**, dan satu hal
   lebih kuat dari usulan: rute staf **terpisah** `/rawreservation/slots/…` di balik AuthGuard
   (bukan bendera di rute publik). `booked_by` = cabang · jam · nama pasien; spanduk hari
   `other_branch_bookings` (menutup "pindah cabang nol menit"); peringatan tumpang tindih di
   layar jadwal dokter (client-side, tak memblok). **Tanpa nomor HP.** Kotak Terisi = `<span>`
   supaya tap HP hidup. Segmen `rawreservation` punya 14 grant di prod → lolos jebakan 302.
4. Rapi kecil: sesi habis di rute staf → "sesi habis — muat ulang", bukan "Unexpected token <".
5. Handoff `docs/handoff/handoff-penanda-slot-lintas-cabang.md` diluruskan: Reza sudah
   mengerjakan; kini catatan keputusan + §10 sisa kecil.

**Commit checklist Reza `15dfd1a` SENGAJA TIDAK ikut**: butuh SQL `excuse_source` yang belum
di prod, dan menggeser cron `checklist:mark-missed` 00:10→06:15 yang terbukti jalan. Gerbong
sendiri. Dua commitnya terpisah rapi — `a252215` duduk langsung di main, tak perlu dipecah.

### Bukti
- **46/46 unit** lokal (15 baru, tanpa DB) · **10/10 E2E di dev, 84 asersi** — persona
  Front Office Cinere (Della) vs form publik vs tanpa sesi: kembar hilang; staf lihat
  Kalideres·jam·nama; **string HP tak ada di keluaran**; publik nol kunci detail & nol nama;
  tanpa sesi → 302 signin; jam terbalik ditolak & tak tersimpan; payload peringatan jadwal
  memuat kedua cabang tanpa baris istirahat.
- **Diadu ke data prod** (baca-saja, dua proses lama vs baru, asal trait dicetak): **3→0**
  kombinasi berkembar · **0** jam hilang dari 166 · **0/518** hari-dokter berubah di
  `praktikPada` (jalur absensi dokter tak tersentuh) · Dharma/Cinere/10 Sep → 6 slot.
- Dev: `/`, `/signin`, `/reservasi` **200**; JS baru ter-serve.

### Temuan lain — utang, bukan gerbong ini
- **Label "90 hari" bohong**: loop `computeAvailableDates()` 535 hari; Dharma/Cinere 77 tanggal
  s/d **24 Feb 2028**, cuma 13 dalam 90 hari. 12 reservasi nyata di luar 90 hari → yang salah
  **labelnya**, jangan pendekkan jendelanya.
- Dharma Bintaro Minggu `22:00–20:00`: nol slot, tapi absensi mencatat "praktik tanpa scan"
  tiap Minggu. Keputusan ops (maksudnya mungkin 20:00–22:00).
- Dua baris redundan (exception Cinere 10 Sep; Panji Sabtu `15:00–20:00`) — **tak berbahaya
  lagi**, rapi-rapi Della kapan saja, tanpa tenggat.
- Pindah cabang nol menit tak dipagari (butuh matriks jarak) — spanduk penggantinya yang jujur.
- Reza (commit checklist): komentar `AttendanceRequestWorkflow` masih "cron 00:10".

### Naik prod — resep
`deploy.sh main` → **rsync `assets/` SAJA** (`public/assets/js/reservation-form.js` tersentuh;
rsync seluruh `public/` pernah mematikan prod 6 menit) → `curl` beranda **200** + cek URL
akhir → buka Cinere/Dharma/10 Sep: **6 kotak**, 17:00 bertanda Heri Widodo · Cinere.
**Nol SQL, nol migration, nol urutan mengikat** (gabung membuat baris lama tak berbahaya
walau dibiarkan; validasi cuma untuk simpanan berikutnya).
Dev **dibiarkan** di `fix/slot-kembar` (+ composer dev) supaya bisa dilihat mata; kembalikan
ke `feat/it-tracker` `f111832` + `--no-dev` sesudah prod.

### Pelajaran
- Satu laporan bisa dua sebab dengan dua nasib — pisahkan dulu, baru perbaiki; kalau tidak,
  yang bukan bug ikut "diperbaiki" dan justru membuka double-booking.
- Menggabung rentang: **jangan buang yang kosong** — ada pemanggil lain yang membaca
  kekosongan koleksi sebagai keputusan (di sini: absensi dokter).
- Dev tak selalu bisa mereproduksi (Dharma tak punya jadwal Cinere di dev) → E2E pakai
  fixture dalam transaksi + persona sungguhan, dan **adu kode ke data prod tanpa deploy**.

---
## 2026-09-06 — ✅ NAIK PROD: tiga sebab "sistem sering error & double input"

Prod `4846b95` (dari `171d5c1`, ~21:45 WIB). Backup `prod_pre_galat2002_20260906_1448.sql.gz`
(50M, gzip diuji). **SQL enum dulu, baru deploy** — kode yang menaikkan flag
`pulang_cepat` sebelum enumnya ada akan melempar galat di `store()`. Beranda & signin 200.

### Audit ulang sebelum naik — dua yang ketemu, dua-duanya ditutup
1. Kotak placeholder (130×130, r10) **tak sama** dengan kotak `<img>` tile (130×150, r12,
   bingkai 3px) → baris strip melompat tingginya dan bingkai merah `.flagged` tak berlaku
   ke placeholder. Disamakan; `.aws-tile.flagged .aws-tunda` ditambah.
2. Ketiga halaman ber-`data-kunci-kirim` **memang** memakai `layouts.app` (tempat skripnya
   hidup) — diperiksa, bukan diasumsikan. `timpaPulang()` menyediakan keempat kunci
   `$hasil[...]` yang dipakai `store()`. `$descOut` terdefinisi (1177) sebelum pagar (1184).

### Angka SEBELUM → SESUDAH, diukur di prod pada data nyata

| Halaman | tile | `<img>` sebelum | `<img>` sesudah | placeholder | tombol |
|---|---|---|---|---|---|
| Pengawasan **21 Agu** (hari tersibuk) | 57 | **57** | **12** | 45 | 9 |
| Pengawasan **hari ini** | 39 | 39 | **12** | 27 | 9 |
| `?semua=1` (179 baris flag) | — | 39 | **12** | — | — |

Kode baru ter-autoload **dari berkas prod** (`kembarBaruSaja`, `JEDA_TIMPA_DETIK`,
`jamPulangTerjadwal`, `LABEL[pulang_cepat]`) · poller `visibilityState` & skrip
`data-kunci-kirim` tercetak di layout · layar absen akun baku sungguhan render dengan
`var konfirmasi` + handler `need_confirm` · `/pettycash` memuat **2** form ber-kunci
(tambah + transfer) · kendali negatif 5 tabel **tak bergeser** (bukti 1158 · scan 1158 ·
flag 274 · petty 397 · att_day 706).

Galat non-`[2002]` sejak deploy: **nol dari aplikasi** (satu-satunya adalah `shell_exec`
dari skrip pre-flight-ku sendiri — `shell_exec` dimatikan di hosting). `[2002]` hari ini:
**0** — tapi ini Minggu, lalu lintas rendah; **bukan bukti**. Bacaan yang sah = hari kerja
pertama, bandingkan dengan 31–46/hari sebelumnya.

⚠️ Celah kecil yang SENGAJA dibiarkan: `timpaPulang()` tidak memanggil `evaluasiFlags()`,
jadi pulang-timpa dari IP asing tak menaikkan `ip_asing`/`luar_seharian` baru (flag dari
pulang pertama tetap ada di bukti yang sama). Kasusnya sempit; dicatat, bukan dikerjakan.

---

## 2026-09-06 — Tiga sebab "sistem sering error & double input" — DEV LULUS, prod belum

Branch `fix/galat-2002-kiriman-ganda` (dari `main` `d68679f`), **tiga commit
terpisah** supaya bisa di-revert sendiri-sendiri. Diagnosis (sudah terukur di
prod, tidak diukur ulang): `docs/handoff/handoff-diagnosis-galat-2002-kiriman-ganda-2026-09-06.md`.

| | Commit | Uji |
|---|---|---|
| **A** Pengawasan + poller | `5763445` | `PengawasanFotoTest` 4 · 11 |
| **B** Absen HP masuk→pulang | `c5a553a` | `AbsenHpJedaTest` 7 · 44 |
| **C** Petty cash kiriman ganda | `6fed3f6` | `PettyCashKembarTest` 5 · 19 |

Regresi: `LayarAbsensiRenderTest` 10 · `PagarLemburLayarTest` 3 · `KoreksiAbsenTest` 16 — hijau.
**Total 45 uji baru+regresi, 170 assertion**, per kelas.

### A · Pengawasan — 49 `<img>` → 12

Tiap `<img src=pengawasan/foto/N>` = satu boot Laravel + satu koneksi DB, dan
HTTP/2 mengirim semuanya serentak. Kini **anggaran GLOBAL 12 `<img>`** di
muatan pertama; sisanya `<span class="aws-tunda" data-src>` + tombol *"Muat N
foto lagi"* per strip (12 per klik). Tile tetap ada semua — nama & jam terbaca,
klik tile tetap membuka panel yang memuat fotonya sendiri.

**Angka:** 49 selfie (seed = rata-rata prod) → `<img>` **49 → 12**, 37
placeholder, 49 tile utuh. ⚠️ Hari tersibuk *dev* cuma **6 selfie** — di bawah
anggaran, jadi pengukuran atas data dev vakum (6 → 6); yang bermakna adalah
seed 49 di uji.

Ujinya menghitung **ELEMEN** `<img>` ber-`src` ke rute foto — bukan kata. Kata
`pengawasan/foto` sengaja tetap muncul 49× (JSON `AWS_BUKTI` + `data-src`);
menghitung kata akan merah selamanya.

Baris flag (`?semua=1`) **tidak diubah**: tak pernah merender `<img>`; fotonya
sudah dimuat panel saat baris diklik.

Poller lonceng (30 dtk) & reservasi baru (20 dtk): berhenti saat
`document.visibilityState !== 'visible'`, lanjut saat kembali. 118 dari 574
galat 2002 jatuh di dua poller ini.

**Nol berkas di `public/assets`** (gaya inline) → prod **tak butuh rsync**.

### B · Absen HP — peringatan yang bisa dilanjut, bukan penghalang

1. Pulang **<5 menit** sesudah masuk → dialog: *"Kamu baru absen masuk N detik
   lalu. Yang ini absen PULANG, bukan mengulang masuk. Lanjutkan?"* Server
   menuntut `confirm=1` — tombol bukan pagar.
2. Pulang lebih awal dari jam pulang aturan (**baku saja**) → **teks di dalam
   tombol**: *"Jadwalmu sampai 17.00. Ini pulang cepat 2 jam."* Tetap hidup,
   tetap tercatat, jadi flag **`pulang_cepat`** (enum ditambah —
   `2026_09_06_flag_pulang_cepat.sql`, idempoten). Fleksibel: nol peringatan.
3. Pulang yang tercatat ≤5 menit sesudah masuk boleh **DITIMPA** pulang
   berikutnya: UPDATE bukti + scan yang ada (bukan insert kedua — reconciler
   melipat ≤5 menit), jam & foto lama masuk `audit_log`. Pulang **normal** yang
   sudah ada tetap ditolak.

`JEDA_TIMPA_DETIK = 300`, sengaja = `$dedupMinutes` reconciler (diuji).

⚠️ **Salah baca skema, ketahuan dari uji**: `jam_pulang` hidup di
**`att_work_rule_day`** (baris hari), bukan di `att_work_rule` — tabel aturan
tak punya kolom jam sama sekali. Helper `jamPulangTerjadwal()` dibetulkan;
7 uji sempat *error* (Unknown column), bukan gagal logika.

**4 kasus lama TIDAK disentuh** — koreksi lewat layar, keputusan Ekky.

### C · Petty cash — pengaman tombol + pagar server 10 detik

Pengaman tombol = atribut **`data-kunci-kirim`** pada `<form>`, ditangani satu
skrip bersama di `layouts/app.blade.php` (tombol mati + "Menyimpan…", kiriman
kedua dibatalkan; pulih lewat `pageshow`/15 dtk). Dipasang di **tambah &
transfer** petty cash (yang di `:1149` memang milik modal EDIT), **pengajuan
ROP**, **pengajuan absensi** — dua terakhir belum punya kembaran, cukup tombol.

Pagar server `PettyCashTransaction::kembarBaruSaja()`: baris IDENTIK
(`created_by + amount + description + trx_date`) ≤**10 detik** [USULAN HUB]
→ flash **ramah** *"Pengeluaran yang sama baru saja tersimpan — tidak dicatat
dua kali"*, bukan galat. Kembar persis ditolak, mirip boleh (pola pagar
lembur). Di `store()` dan `transfer()` (kunci pada baris keluar).

### Sengaja TIDAK dikerjakan

- **Foto tanpa boot Laravel** (tautan bertanda tangan ke berkas statis) — opsi
  struktural §1.2 diagnosis; A1 sudah menghilangkan gerombolannya.
- **Flag `single_scan` yang sudah ada** dari 4 kasus lama — koreksi data, Ekky.
- **Pagar server untuk ROP & pengajuan absensi** — nol kembaran dalam 14 hari.
- **Pengaman tombol untuk Tarik Tunai / Setor Cash** — sudah ber-`disabled`
  sampai valid; tak termasuk lingkup (tambah & transfer).
- `[2002]` sebagai gejala hosting — sesi terpisah, sesuai kesepakatan.

### Naik prod butuh

**1 SQL** (`2026_09_06_flag_pulang_cepat.sql`) + deploy **PENUH** (helper &
metode baru) + **nol rsync**. Dev dipinjam dari chat IT Tracker dan dikembalikan
persis ke `feat/it-tracker` `f111832`, `--no-dev` dipulihkan, beranda 200.

---
## 2026-09-05 — ✅ NAIK PROD: koreksi absen, ambang DODO, ekspor rentang + koreksi data §A & §B

Prod `e324d1b` (dari `2400896`). Backup `~/backup/prod_pre_koreksiabsen_20260905_0851.sql.gz`
(49M, gzip diuji). Beranda & signin **200**.

### Yang berubah di prod — diff potret sebelum/sesudah, tak ada yang di luar rencana

| | Sebelum | Sesudah |
|---|---|---|
| tabel `att_day_koreksi` | belum ada | ada, 0 baris |
| `dodo_ambang_menit` | belum ada | **0** (pagar mati = perilaku lama) |
| Yovanka sisa cuti | **−2.0** | **0.0** |
| Ramdan sisa cuti | 11.0 | **11.0** (memang tak boleh berubah) |
| Alpajri | GS Pagi, terbuka | GS Pagi ditutup 4 Sep → **Fleksibel Operasional** 5 Sep |
| Ela | tak punya aturan | **Fleksibel Kantor** sejak **2026-09-02** (scan pertamanya) |
| Dodi | tak punya aturan | **Fleksibel Kantor** sejak **2026-09-08** (ditetapkan Ekky) |
| `att_day` | 676 | 680 (4 hari Ela) |
| `att_cuti_mutasi` | 42 | 48 (6 pembalik) |
| aktif tanpa aturan | 13 | 11 |

**Tak bergeser** (kendali negatif): total lembur Agustus 34.927 menit · daftar klaim
DODO · Melani & Putri tetap `alpa` · `att_scan` · `att_request` · `users` ·
`att_period` · alpa Agustus tetap 2 · 28 baris buku besar orang lain, 66,0 hari.

### Dua cacat yang ditangkap POTRET PROD, sebelum sempat merusak

**1. Aturan Alpajri ternyata `GS Pagi`, bukan `GS`.** Skrip §B mencocokkan `'GS'`
→ **nol baris tertutup**, lalu blok berikutnya tetap menyisipkan penugasan baru.
Alpajri akan berakhir dengan **dua aturan aktif** dan mana yang menang ditentukan
urutan MySQL — persis cacat "baris rangkap" yang sudah menimpa 8 orang di prod
5–6 Agu, dan **tanpa satu pun galat**. Ketahuan karena potret dibaca, bukan
diasumsikan.

**2. `DB::insert()` mengembalikan BOOLEAN, bukan jumlah baris.** Penjaga
`harus(..., 1)` karena itu **lolos palsu** di §B (true → 1) dan **berhenti palsu**
di §A. Akibat nyata: §A berhenti separuh jalan — Yovanka sudah terpasang, Ramdan
belum. Diperbaiki ke `DB::affectingStatement()`, lalu dijalankan ulang (skripnya
idempoten lewat `NOT EXISTS`, jadi Yovanka menyisipkan 0 dan Ramdan 3+1).
⚠️ Penjaga §B yang "lulus" itu **tak membuktikan apa pun** — yang membuktikannya
SELECT verifikasi sesudahnya, yang untungnya memang ada.

### Verifikasi prod

- 4 kelas baru ter-autoload **dari berkas prod** (`ReflectionClass::getFileName()`).
- **Invarian `biasa + DODO = total`: 59 pasangan orang-bulan, 0 pelanggaran, 0
  selisih sel-vs-orang.** Kali ini **tidak vakum** — prod punya 34.927 menit lembur
  nyata (di dev angkanya nol, jadi sapuan dev memang tak membuktikan apa-apa).
- Empat layar render sungguhan dengan sesi Finance: Koreksi (35.886 B) · Lembar
  Gaji Agustus (77.771 B) · Kehadiran (179.133 B) · ekspor CSV rentang (2.192 B).
- Backfill Ela: `att:reconcile 2026-09-02..05` → diff **tepat 4 baris**, semuanya
  Ela, semuanya `hadir`. Nol baris orang lain bergeser.
- `att:periksa-kejanggalan` **nihil** untuk cek 1 & 2 — pembalik `batal-*` dikenali,
  jadi Yovanka & Ramdan **tidak** dilaporkan ulang selamanya. Sisa 1 temuan sah:
  *Executive Pamulang* (Branch Executive, role yang catatan lama sudah sebut
  bawaannya kosong).

### ⚠️ Ambang DODO sengaja diset 0 — dan ini bukan basa-basi

Kode berbawaan 420, jadi begitu deploy mendarat ambangnya **langsung aktif**.
Diset `0` seketika. Buktinya kenapa itu penting, diukur di prod: klaim DODO
Agustus ada **enam**, bukan dua seperti catatan lama —
Afrina 115 · Della R 120 · **Melani 129** · **Muhamad Ikhsan 660** · Rokhmatun 60 ·
Della R 60 = **1.144 menit**. Dengan ambang 420 hanya Ikhsan yang bertahan (jadi
420); sisanya **nol**. DODO Agustus akan jatuh **1.144 → 420** pada bulan yang
mungkin sudah dibayar.

Dengan ambang 0, angka Agustus **sama persis** seperti sebelum deploy (DODO 1.144,
biasa 33.783). Menaikkannya ke 420 = keputusan Finance, lewat layar Lembar Gaji,
tanpa deploy.

### ✅ AMBANG DODO DISETEL 420 oleh Della — empat pemeriksaan LULUS

Della Puspita Sari (Finance) menyetelnya lewat layar Lembar Gaji pukul 17:09.
`audit_log`: *"Ambang DODO diubah: tanpa ambang → 7 jam"*, `old_values`
`{"ambang_menit":0}` — atas namanya, bukan akun bersama. Persis yang diminta.

| Pemeriksaan | Hasil |
|---|---|
| Seluruh klaim DODO Agustus tetap **1.144 menit** (nol menguap) | ✅ |
| Ikhsan pecah **420 DODO + 240 biasa** | ✅ |
| Lima klaim lain **0 DODO**, seluruhnya (**484 menit**) jadi lembur biasa | ✅ |
| Invarian: **0 pelanggaran**, 0 selisih sel-vs-orang | ✅ |

Rincian per klaim sesudah ambang:

```
Muhamad Ikhsan    23 Agu  klaim 660 -> DODO 420 + biasa 240
Melani Safrida    22 Agu  klaim 129 -> DODO   0 + biasa 129
Della Raqisiah    17 Agu  klaim 120 -> DODO   0 + biasa 120
Afrina Apriliani  10 Agu  klaim 115 -> DODO   0 + biasa 115
Rokhmatun         25 Agu  klaim  60 -> DODO   0 + biasa  60
Della Raqisiah    25 Agu  klaim  60 -> DODO   0 + biasa  60
```

Total lembur `att_day` Agustus **tak bergerak** (34.927 menit) — ambang hanya
memindahkan menit antar kolom, tak pernah menghilangkannya. DODO Agustus
1.144 → **420**, sisanya masuk lembur biasa.

⚠️ **Pasangan orang-bulan kini 60, bukan 59** — dan itu BENAR, bukan regresi:
angka 59 diukur SEBELUM backfill Ela. Ela menyumbang satu pasangan September
(4 hari, dihitung 15:54). Agustus 31 orang + September 29 = 60.
*Angka harapan yang dikutip dari pengukuran lama akan basi begitu data
bertambah — periksa sebabnya sebelum menyebutnya gagal.*
### 🔎 Lembar Gaji TAK PUNYA JALAN MASUK — ditemukan Ekky, dibetulkan

Ekky membuka Kehadiran sebagai Della (Finance) dan bertanya: *"where is lembar
gaji?"* Ternyata memang tak ada di mana pun.

* Layar itu **tak ada di deret tab Finance** — tab-nya berisi 6 lainnya saja.
* Node menunya **ADA** (`Lembar Gaji → absensi/lembar-gaji`, `abs-gaji-…`) tapi
  **NOL role di-grant di prod**, jadi tak muncul di sidebar siapa pun.
* Di **dev** node yang sama punya **9 grant**. Itulah kenapa ia terlihat normal
  selama pengembangan dan tak terlihat sama sekali di produksi.

Jadi sejak layar itu dibuat, satu-satunya jalan masuk adalah **mengetik URL** —
dan aku menyuruh Ekky "buka Absensi → Lembar Gaji" tanpa memeriksa jalannya ada.

**Dibetulkan dua sisi:**
1. **Tab** `Lembar Gaji` ditambahkan, **tersaring peran**. Bukan ditampilkan ke
   semua yang lolos `canSeeAllBranches`: kedua gerbang itu tak sama — `Admin
   Backup` boleh melihat Kehadiran tapi sudah dicabut dari Lembar Gaji, jadi
   menampilkan tabnya = menu tampil lalu 403. Daftarnya **ditanyakan** ke
   `LembarGajiController::BOLEH` (kini publik), tidak disalin.
2. **Grant node** di prod ke `BOLEH` **persis**: Finance, Head IT, IT, Konsultan
   Finance, Konsultan IT, Super Admin, Admin General — **7 role**.
   ⚠️ `Admin` & `Admin Backup` **sengaja tidak** diberi, meski dev memberi
   keduanya. Kendali negatif: total grant sistem 513 → 520 (persis +7).

Diverifikasi sebagai Della sungguhan: deret tab kini memuat **Lembar Gaji**, dan
sidebar juga.

#### ⚠️ Uji yang gagal karena SALAH LINGKUP, bukan salah kode

Uji negatifnya ("tab tak boleh tampil untuk akun bersama") gagal — dan sempat
kukejar sebagai cacat blade selama beberapa langkah. Dua kesalahanku:

1. **Jendela debug 700 karakter** memotong tab ke-7, jadi aku menyimpulkan tabnya
   tak dirender padahal ada. *Periksa panjang jendelanya sebelum menyimpulkan.*
2. Ujinya menyapu **SELURUH halaman**, padahal sidebar juga memuat tautan yang
   sama — dan grant sidebar **berbeda antar environment**. Jadi ia lulus/gagal
   karena sebab yang sama sekali lain. Kini dibatasi ke blok deret tab saja.

Justru kegagalan itu yang menyingkap perbedaan grant dev↔prod — sebab
sesungguhnya dari pertanyaan Ekky.
### ✅ PAGAR RECONCILE LAMPAU NAIK PROD — `2087022`

Permintaan Ekky, dan sengaja **bukan** dry-run lebih dulu: *"Dry-run menolong
orang yang sudah curiga; yang membuat kejadian ini adalah orang yang tidak
curiga."*

`att:reconcile` kini **MENOLAK** tanggal lebih tua dari `BATAS_HARI` (14)
kecuali `--force`, dan saat menolak ia **mencetak siapa saja** yang
penempatannya bergeser di rentang itu (`App\Services\PenempatanBergeser`).
Penjadwal harian (kemarin+hari ini) tak tersentuh — diuji di prod, jalan normal.

**Detektornya diadu dengan pengukuran Ekky sendiri, dan sempat SALAH.**
Versi pertama menyalin penyaring `CabangBertugas` apa adanya (`kind = 'rolling'`)
→ menemukan **3 orang**, sementara Ekky mengukur **6**. Yang terlewat — Alpajri,
Cici, Rivanti — semuanya `kind = 'home'`. Cabang bertugas jatuh kembali ke cabang
ASAL kalau tak ada rolling, dan baris `home` itulah jejak kapan cabang asal
berubah; jadi penempatan Bintaro milik Alpajri, **yang melahirkan seluruh temuan
ini**, justru yang tersaring keluar. Penyaring dibuang. Sesudahnya: **6 orang,
9 baris — cocok persis dengan hitungan Ekky.**

Pelajarannya: *detektor yang melapor KURANG jauh lebih berbahaya daripada yang
melapor berlebih* — dan menyalin penyaring dari kelas tetangga adalah cara
mudah untuk melaporkan kurang.

Keenam nama (jangan reconcile tanggal lampau mereka sampai ada pagar — kini ada):
Alpajri · Cici Nurjanah · Della Raqisiah · Ramdan · Rivanti Nadiana · Verini.

### ✅ Alpajri 25 Agu dipulihkan — lewat layar, alasan dari Ekky

`alpa → libur`, lewat POST `/absensi/koreksi` sesi Admin_Ekky, jejaknya sejajar
dengan Melani & Putri. **Alpa Agustus kini 0.** Buku besar koreksi: 3 baris.

### 🔒 Akun bersama dicabut dari gerbang Lembar Gaji

`Admin` & `Admin Backup` dibuang dari `LembarGajiController::BOLEH`. Alasan Ekky:
bukan lebar gerbangnya yang mengganggu, melainkan bahwa ambang DODO menentukan
uang — disetel dari akun bersama, `audit_log` mencatat nama yang tak menunjuk
siapa pun, dan jejak yang tak bisa menunjuk orang bukan jejak. Sisanya dibiarkan.
Diverifikasi di prod: Finance/Admin General/Head IT **200**, Admin & Admin Backup
**403**.

### 📌 Ralat lanjutan

- **Layar ambang DODO memang ADA** — Ekky sudah memverifikasinya live. Pencarian
  string `dodo_ambang_menit` memang cuma menemukan `LemburDodo.php` sebab
  pemakainya lewat konstanta.
- **ABG hari ini 15, bukan 16** (ralat Ekky, diterima): aturan Fleksibel Kantor
  Dodi baru berlaku **8 September**, jadi FK = 7 hari ini dan 8 hari Senin.
  Tanggalnya memang benar — Dodi nol scan, nol att_day, mulai Senin.
- Struktur pengelompokan tetap seperti dilaporkan: lewat `att_work_rule.tipe`,
  bukan cabang NULL. Keduanya kebetulan berimpit sekarang.

**Uji: 80 hijau, 220 assertion** (8 kelas lama + `PagarReconcileLampauTest` 6).
### ✅ §C SELESAI — dan sebuah landasan berbahaya ikut tersingkap

Alasan diberikan Ekky sendiri (Maulid; Sunter tetap buka dengan awak minimum —
Rokhmatun asistensi dokter & Tito operasional, keduanya lembur disetujui; Melani
& Putri diliburkan). Dijalankan lewat **POST `/absensi/koreksi`** dengan sesi
Admin_Ekky — bukan panggilan service — jadi gerbang peran, validasi, pagar
periode, dan penulisan jejak audit ikut terbukti. Keduanya `libur`, dan **tetap
`libur` sesudah `att:reconcile 2026-08-25`**: inti desainnya terbukti di prod.

#### ⛔⛔ REKONSILIASI TANGGAL LAMPAU MENULIS ULANG SEJARAH

Reconcile 25 Agu itu juga **mengubah Alpajri dari `libur` jadi `alpa`** — hari
yang sebelumnya benar. Rantai buktinya lengkap:

* Backup memuat barisnya apa adanya: id 1692, `rule_id` 6, status **`libur`**,
  `computed_at` 2026-08-26 06:10.
* Sebabnya `sys_user_position` menempatkannya di **Bintaro**, dan Bintaro libur
  Maulid (exception #7, dibuat 21 Agu, per-cabang sejak awal).
* Penempatan itu **ditutup 1 Sep 04:47** saat restrukturisasi Konsultan.
* Aturan kerja & pengajuannya **tak berubah** sejak 20 Agu — jadi bukan itu.
* Reconcile 5 Sep memakai penempatan **sekarang** (tak ada) → tak ada libur
  cabang → Selasa hari kerja → **`alpa`**.

Artinya: **`att_day` dihitung dari keadaan organisasi HARI INI, bukan keadaan
saat tanggal itu berlangsung.** Tiap reconcile atas tanggal lampau menimpa baris
lama dengan struktur terkini — dan hari yang dulu benar bisa berubah jadi salah,
tanpa satu pun galat.

* Penjadwal harian **aman** (cuma kemarin + hari ini).
* `att:periksa-kejanggalan` **tidak** menangkapnya — ia memeriksa cuti & aturan,
  bukan pergeseran status.
* **Ruang lingkupnya belum diukur.** Mengukurnya butuh dry-run (reconcile di
  dalam transaksi lalu rollback) — belum dibuat.

**Keadaan: Alpajri 25 Agu MASIH `alpa` di prod** (alpa Agustus = 1, bukan 0).
Pemulihannya disiapkan tapi **diblokir gerbang keamanan** — tulisan prod di luar
yang diizinkan Ekky. Menunggu keputusannya.

### 📌 Dua ralat atas laporanku sendiri

**1. Layar ambang DODO ITU ADA.** `grep -rn "dodo_ambang_menit"` hanya menemukan
`app/Services/LemburDodo.php`, sebab controller/blade/rute memakai **konstanta**
`LemburDodo::KUNCI_AMBANG` dan field `ambang_menit` — bukan string kuncinya.
Yang hidup di prod: `LembarGajiController::setelanAmbang()`, rute POST
`absensi/lembar-gaji/ambang`, dan blok `<details class="lg-ambang">` yang
terbukti ter-render (77.764 byte, `value="0"`). Carilah dengan **KUNCI_AMBANG /
ambang_menit / setelanAmbang**.
⚠️ Gerbangnya `LembarGajiController::BOLEH` — **lebih luas** dari "hanya
Finance/Head IT". Admin_Ekky (Admin General) boleh menyetel.

**2. "ABG" itu tipe aturan `fleksibel`, bukan "tanpa cabang".** Yang bukan
fleksibel DAN tanpa cabang masuk grup **"Belum ada cabang"**. Keduanya kebetulan
berimpit sekarang (nol orang fleksibel yang punya cabang), tapi bukan definisi
yang sama: sebelum 5 Sep Alpajri (GS Pagi + cabang NULL) ada di "Belum ada
cabang", **bukan** ABG — ia masuk ABG justru karena aturannya diubah.
Isi ABG kini **16** = Fleksibel Kantor 8 + Fleksibel 5 + Fleksibel Operasional 3
(sebelum hari ini 13, sesuai hitungan Ekky).
### ⏸️ §C Melani & Putri DITAHAN — sengaja, menunggu satu kalimat dari Ekky

Alatnya siap dan teruji, periodenya **tidak terkunci** (satu-satunya periode
21/07–20/08 masih `open`), jadi teknis tak ada halangan. Yang menahan: **alasannya
tersimpan permanen di catatan yang menentukan uang, dan aku tak boleh mengarangnya.**

Draf awalku menyebut "rekan se-cabang juga tercatat libur" — **itu keliru**, dan
ketahuan saat memeriksa: Verini memang `libur` 25 Agu, tapi karena ia **rolling ke
Cinere yang tutup**, bukan karena libur bergantian di Sunter. Fakta yang benar-benar
terukur:

- Maulid Nabi terdaftar libur **hanya** Bintaro, Cinere, Kalideres — **Sunter tidak**.
- Sunter 25 Agu: Rokhmatun `hadir`, Tito `hadir`, Verini `libur` (rolling ke Cinere),
  Melani & Putri `alpa`, nol scan, tanpa pengajuan apa pun.

Jadi **belum ada dasar tercatat** kenapa keduanya tidak masuk. Begitu Ekky memberi
satu kalimat alasannya, koreksinya dijalankan lewat layar.

### 🔎 Temuan sampingan di prod (di luar lingkup, layak ditindak terpisah)

`laravel.log` prod memuat **31–46 galat per hari**, dan **100% hari ini (40 dari
40)** bertipe `SQLSTATE[HY000] [2002] Operation not permitted` — batas koneksi
hosting bersama. Nol galat dari fitur baru. Ini **kronis dan sudah ada sebelum
deploy**, tapi artinya ada pengguna yang halamannya gagal dimuat tiap hari.

---
## 2026-09-05 — ABSENSI: koreksi berjejak audit, ambang DODO, ekspor rentang bebas

Tiga fitur dari serah-terima `docs/handoff/handoff-absensi-koreksi-2026-09-05.md`,
plus pemeriksa harian. **Semua di dev; prod belum disentuh sama sekali.**

### ⛔ Temuan yang mengubah urutan pekerjaan: perbaikan cuti hari libur TIDAK ADA di prod

Serah-terima menyatakan kode "cuti tak memakan hari libur" **sudah hidup di
prod**, sehingga yang tersisa cuma membersihkan data lama. Itu tidak benar, dan
salahnya bukan salah baca — melainkan **pengukuran yang benar atas benda yang
salah**:

* Branch `fix/cuti-hari-libur` **nol commit** (`reflog`: "Created from
  origin/main"). Karena itu `git merge-base --is-ancestor` terhadap prod memang
  LULUS — yang lulus branch **kosong**, bukan perbaikannya.
* Blob `app/Services/CutiService.php` **sama persis di seluruh branch** repo;
  `potongDariPengajuan()` masih memotong tiap hari kalender, dan komentarnya
  sendiri menyebut "belum melihat jadwal kerja/hari libur".
* Perbaikan aslinya (10 berkas + 6 kelas uji, ±830 baris `app/`) masih
  **menggantung tanpa commit** di worktree `sweettreats-dodo`.

**Akibatnya:** koreksi §A tetap aman dijalankan (pembalik tak menghapus baris
asli, jadi penjaga idempoten tak melahirkan potongan lagi), tapi ia **tidak
menghentikan sumbernya** — pengajuan cuti berikutnya akan tetap memotong hari
libur. Menyelamatkan kerjaan yang menggantung itu **pekerjaan tersendiri dan
belum dikerjakan**.

Pelajarannya sudah lama tercatat di proyek ini dan berulang lagi: *bentuk hasil
palsu yang paling mahal bukan yang kacau, melainkan yang rapi dan salah.*
**Nama branch bukan bukti isi branch.**

### D1 · Koreksi absen oleh admin — `/absensi/koreksi`

Sebelumnya tak ada satu pun jalan mengubah `alpa` hari LAMPAU; yang ada cuma
koreksi cuti (saldo) dan modal karyawan untuk hari ini.

**Jebakan yang ditutup:** `att_day` itu tabel **turunan** — reconciler
meng-`upsert` kolom `status` tiap pagi 06:10. Koreksi yang ditulis langsung ke
sana akan bertahan sore ini lalu **hilang diam-diam besok**, dan tak ada yang
akan menghubungkan keduanya. Jadi koreksi disimpan sebagai **deklarasi**
(`att_day_koreksi`) dan dibaca reconciler sebagai kata terakhir — pola yang sama
dengan pengajuan yang di-ACC.

* Alasan **wajib** (min 8 karakter), tersimpan bersama nama & waktu + AuditLog.
* **Periode gaji tertutup DITOLAK** dengan menyebut nama periodenya. Angka di
  sana sudah dibayarkan.
* **Buku besar**: baris tak pernah dihapus; membatalkan = mencabut, dan
  cabutannya ikut tercatat.
* `telat_menit` dinolkan saat hari dipindah ke status tak-bekerja — kalau tidak,
  koreksi membetulkan satu angka sambil merusak kolom Total Telat.

### D2 · Ambang DODO bisa diatur — di layar Lembar Gaji

Aturan Ekky: `>=` ambang → DODO sebesar ambang, sisanya lembur biasa;
`<` ambang → **bukan DODO sama sekali**. Bawaan **7 jam**, disimpan di
`sys_setting`, diubah Finance tanpa deploy, berjejak audit.

* **Per HARI, bukan per periode.** Dua hari libur penuh tetap dua hari DODO;
  kalau ambang dikenakan pada jumlah sebulan, hari kedua lenyap tanpa jejak.
* **Invarian dijaga**: `biasa + DODO` tetap sama persis dengan seluruh lembur
  di-ACC pada ambang 0/60/420/600/900 — diuji eksplisit. `LemburDodo::dasar()`
  nol baris disentuh.
* Jumlah sel Kehadiran == angka Lembar Gaji, juga diuji (dua layar yang
  berselisih soal DODO adalah cacat yang paling sulit dilacak).

### D3 · Ekspor CSV di layar Kehadiran, rentang tanggal bebas

Ekspor lama hidup di Periode Gaji dan cuma bisa mengeluarkan periode utuh —
"1–15 Agustus" memaksa orang **membuat periode dulu**, mengubah data supaya bisa
membacanya. Sekarang dari–sampai bebas, mengikuti **penyaring cabang yang sedang
aktif** (disalin ke form lewat JS, sebab penyaringan layar ini di sisi klien).
Roster dari aturan kerja, bukan `att_day`, supaya orang tanpa data tetap tampil.

### E · `att:periksa-kejanggalan` — harian 06:30, BACA-SAJA

Tiga pola yang **sudah terbukti terjadi**: potongan cuti di hari libur, potongan
kembar, karyawan aktif tanpa aturan kerja.

* **Tak membetulkan apa pun** — Ramdan membuktikan kenapa: potongan janggal bisa
  sudah tertutup koreksi manual, dan pembetulan otomatis justru melahirkan
  **kelebihan bayar**. Tiap temuan menyebut apakah orangnya punya koreksi manual.
* **Stempel waktu ditulis walau nol temuan**, supaya "sudah diperiksa" bisa
  dibedakan dari "belum pernah jalan".
* Jalan pertama di dev melaporkan 11 temuan yang semuanya benar tak punya aturan
  (Admin*, Sales). Akun sistem **dikecualikan, bukan disembunyikan** — jumlah &
  namanya tetap dicetak. Sisa: 1 temuan nyata.

### Uji — 59 hijau, 161 assertion (dev, PER KELAS)

| Kelas | Hasil |
|---|---|
| AmbangDodoTest | 9 · 41 |
| KoreksiAbsenTest | 13 · 20 |
| KehadiranEksporTest | 5 · 13 |
| LayarAbsensiRenderTest | 4 · 15 |
| LemburDodoTest (regresi) | 9 · 18 |
| PagarLemburTest (regresi) | 16 · 42 |
| PagarLemburLayarTest (regresi) | 3 · 12 |

`LemburDodoTest` & `PagarLemburTest` **dipatok ke ambang 0** di setUp: keduanya
menguji pecahan/chip, bukan ambang. Tanpa itu keduanya merah karena alasan yang
salah.

### Tiga uji merah yang ternyata salah patokan — bukan salah kode

1. `Carbon\Carbon` vs `Illuminate\Support\Carbon` — **cacat sungguhan**, 9 dari
   11 uji TypeError. Turunan tak bisa disubstitusi ke atas.
2. Tanggal uji 2027 dipilih supaya "tak bertabrakan data nyata" → rekonsiliasi
   **membuang baris masa depan sebagai basi**, jadi yang diuji bukan koreksinya.
   Sekarang tanggal diambil dari `att_day` yang benar-benar ada.
3. Persona ekspor memakai karyawan acak ber-`group_name` 'Finance' → **302**,
   sebab AuthGuard memeriksa akses menu lewat **`group_id`**. Ganti ke akun
   Finance sungguhan.

⚠️ Sapuan invarian atas seluruh data dev **tidak membuktikan apa-apa soal angka**:
`att_day.lembur_menit_disetujui` **nol di seluruh dev** (6 lembur di-ACC ada,
tapi belum terekonsiliasi). 51 pasangan orang-bulan, 0 pelanggaran — vakum.
Bukti D2 ada di `AmbangDodoTest`, yang membangun skenarionya sendiri.

### Yang MENUNGGU keputusan — belum dijalankan di environment mana pun

| Berkas | Isi |
|---|---|
| `2026_09_05_att_day_koreksi.sql` | prasyarat D1 — **sudah dijalankan di dev**, prod belum |
| `2026_09_05_koreksi_cuti_yovanka_ramdan.sql` | §A — pembalik, bukan hapus |
| `2026_09_05_aturan_kerja_alpajri_ela_dodi.sql` | §B — + `att:reconcile 2026-09-02 2026-09-05` untuk Ela |

⚠️ **Ramdan: saldonya TETAP 11 sesudah koreksi, dan itu benar.** Yang diperbaiki
catatannya (5 baris untuk 1 hari cuti), bukan angkanya. Kalau saldonya berubah,
ada yang keliru.

### §C Melani & Putri 25 Agu — BELUM dikerjakan

Alatnya (D1) sudah ada dan teruji, tapi tindakannya menyentuh **data prod**, dan
prod belum diizinkan. Begitu diizinkan: lewat layar `/absensi/koreksi`,
`alpa → libur`, dengan alasan yang menyebut konfirmasi Nissa — bukan tembak DB.

### Audit ulang atas kerjaan sendiri — 7 temuan, semuanya ditutup

Disisir lagi sesudah selesai, mencari bug & inkonsistensi. Dua yang berarti,
satu yang menentukan fiturnya dipakai atau diabaikan.

**1. Ekspor MENGHILANGKAN ORANG.** Layar Kehadiran punya jaring-pengaman untuk
"ada `att_day` tapi tak punya aturan kerja" (data lama) — ekspornya tidak.
Orangnya tampil di matriks lalu lenyap dari CSV. Nol kasus di dev sekarang,
tapi jaringnya ada di layar justru karena pernah terjadi. Disamakan.

**2. Kolom "Hadir" berarti DUA hal.** Ekspor memakai `hadir+telat`; matriks di
layar (`if/elseif` per sel) **dan** ekspor Periode Gaji memakai `hadir` saja.
Satu kata, dua angka, di layar yang sama — tepat jenis selisih yang baru
ketahuan sesudah dipakai rapat. Disamakan ke arti layar.

**3. Pemeriksa akan melaporkan Yovanka & Ramdan SELAMANYA.** Yang paling
menentukan. `att_cuti_mutasi` itu buku besar: koreksi §A menambah pembalik,
**tidak menghapus** baris salahnya. Jadi begitu koreksi dijalankan, sapuan
harian melaporkan keduanya **setiap hari**, untuk kesalahan yang sudah
dibereskan. Pemeriksa yang mengulang temuan mati akan berhenti dibaca — dan
yang berhenti dibaca sama saja dengan tak ada.
Ditutup dengan konvensi `ref_id = 'batal-<id asli>'` yang **diikat dua arah**:
konstanta `AttPeriksaKejanggalan::PREFIX_BATAL` dan catatan di kepala SQL
koreksi yang menunjuk balik ke konstanta itu.

**4 & 5. Bentuk laporan pemeriksa.** Catatan "akun sistem dikecualikan"
tercetak dari dalam pengumpul → muncul di **atas seluruh laporan**, sebelum
judul bagian mana pun, padahal milik bagian 3; dan **lolos dari `--diam`**.
Sekarang pengumpul mengumpulkan, yang mencetak cuma `laporkan()`.

**6. Satu dari tiga kueri** di `AttPeriksaKejanggalan` tak memakai `COLLATE`
seperti dua saudaranya. Disamakan. (Diukur: di dev ketiganya `utf8mb4_unicode_ci`
— FK ke `users` memang memaksanya sama, jadi ini menutup risiko environment,
bukan cacat yang hidup.)

**7. `simpan()`/`cabut()` tak atomik** — baris buku besar bisa ter-commit
sementara hitung-ulangnya gagal: buku besar bilang "sudah dikoreksi", layar
masih status lama, sampai penjadwal besok pagi. Dibungkus `DB::transaction`.

Kendali negatif ekspor dipertegas jadi `assertStatus(403)`: "bukan 200" bisa
lolos karena pantulan menu, bukan karena gerbang perannya bekerja. Dipastikan
dengan membaca `AuthGuard` — ia **tak pernah** membaca `group_name`, jadi
satu-satunya yang bisa menolak memang `canSeeAllBranches()`.

**Diperiksa dan ternyata BERSIH** (supaya tak diperiksa ulang): gerbang keenam
layar pemakai `_finance_tabs` semuanya `canSeeAllBranches` (tak ada tab tampil
lalu 403) · `max:64` pada penyaring cabang aman (id `raw_branch` maks 36
karakter) · rute baru ada di grup middleware yang benar · invarian DODO tetap
terjaga dari konstruksinya (kedua pemanggil memakai pengurangan).

**Uji sesudah audit: 67 hijau, 176 assertion** (+8 uji baru: `PeriksaKejanggalanTest`
6, regresi ekspor 2).

⚠️ `LemburDodoTest` sempat MERAH lagi saat dijalankan berurutan, hijau 9/9
sendirian — **batas laju koneksi hosting dev**, kejadian ke-6 dengan pola yang
sama. Jangan dikejar sebagai regresi.

### Pagar buku besar cuti — dibangun sesudah audit (temuan ke-8)

Gap #2 yang tadinya cuma "dilaporkan" ternyata lebih tajam dari dugaan awal, dan
ditutup. Diperiksa: jatah cuti dipotong **hanya** oleh `AttendanceRequestWorkflow`
saat pengajuan di-ACC; `KoreksiAbsen` tak pernah menyentuh `att_cuti_mutasi`.

Akibatnya, kalau `cuti` bisa disetel dari layar koreksi, **satu baris Lembar Gaji
memuat dua angka yang saling membantah**: kolom **Cuti** (dari `att_day`) naik,
**Sisa Cuti** (dari buku besar) diam. Orangnya dapat hari cuti yang tak memakan
jatahnya — tanpa satu pun galat.

- `cuti` **satu-satunya** status ber-buku-besar (diperiksa: `sakit`/`izin`/`wfh`
  tak punya jatah tersimpan di mana pun). Pagarnya sempit, bukan menyapu.
- `STATUS` (cermin enum `att_day`) **dipisah** dari `STATUS_PILIHAN` (boleh dipilih).
- Ditolak di **server**, bukan cuma disembunyikan dari dropdown.
- Arah sebaliknya (`cuti → lain`) **tidak** diblokir — membetulkan cuti yang
  salah tercatat itu pekerjaan sah — tapi memicu peringatan bahwa jatahnya tak
  kembali sendiri, dan peringatan itu **ikut masuk ke jejak audit**.
- Ketiadaannya **dijelaskan di layar**: pilihan yang hilang tanpa keterangan
  bikin orang mengira layarnya rusak lalu cari jalan memutar ke basis data.

⚠️ **Dan pagar itu sendiri sempat cacat**: pemeriksaannya kutaruh **sesudah**
`validate()`. Karena `validate()` melempar, aturan `in:` menang duluan dan yang
sampai ke pengguna cuma *"The selected status is invalid"* — persis pesan hampa
yang pagar ini ada untuk menghindarinya. Pagarnya bekerja, **pesannya** yang tak
pernah tampil. Ditangkap uji HTTP di dev, bukan dengan membaca ulang kode.

### Ambang DODO itu RETROAKTIF — set 0 dulu saat deploy

Lembar Gaji menghitung ulang bulan lampau tiap kali dibuka, jadi ambang 7 jam
**mengubah bulan yang mungkin sudah dibayar**. Catatan 24–27 Agu: DODO Agustus
prod = Afrina 115 + Della R 120 menit, **dua-duanya klaim satu hari di bawah
7 jam** → jadi 0 DODO, pindah ke lembur biasa. Total kekal, **pembagiannya**
bergeser — dan pembagian itulah yang dikalikan tarif berbeda.

Disarankan deploy dengan `dodo_ambang_menit = 0` (perilaku persis hari ini),
lalu Finance yang menaikkannya ke 420 sebagai keputusan sadar, lewat layar,
tanpa deploy. Setelannya memang ada untuk itu.

Checklist deploy lengkap: `docs/handoff/checklist-deploy-absensi-koreksi.md`.

**Uji sesudah pagar: 72 hijau, 197 assertion**, seluruh 8 kelas lulus dalam satu
lintasan.

### Satu hal yang SENGAJA dilaporkan, bukan diperbaiki

1. **Layar Kehadiran tak menandai sel yang sudah dikoreksi.** Jejaknya lengkap
   di `/absensi/koreksi`, tapi di matriks — tempat orang sebenarnya melihat —
   hari koreksian tampak identik dengan hari hasil hitungan. Yang membaca layar
   melihat *fakta*, bukan *keputusan*. Perbaikannya menyentuh perender sel JS
   di layar yang dipakai harian, jadi tak dikerjakan tanpa diminta.
### Lingkungan

Dev dipinjam dari chat IT Tracker dan **dikembalikan persis** ke `feat/it-tracker`
`f111832` (bukan ke tip origin yang satu commit lebih maju), `--no-dev`
dipulihkan, berkas untracked mereka utuh, beranda dev `200`. Cacah baris 6 tabel
tak bergeser — `DatabaseTransactions`, bukan `RefreshDatabase`.

Branch: `feat/absensi-koreksi`. Naik prod butuh **3 SQL + deploy PENUH** (empat
kelas baru), **nol rsync** (gaya sengaja inline di blade).

---


## 2026-09-02 — IT REQUEST TRACKER: pemetaan + desain + rencana build (belum dikoding)

Fitur baru, belum ada satu baris kode pun. Yang selesai hari ini adalah
**seluruh pekerjaan berpikirnya**, supaya chat build tinggal membangun.

**Masalahnya.** Permintaan ke IT datang lewat WhatsApp dan omongan. Tak ada
jejak, tak ada urutan, dan yang paling mahal: **tak ada definisi "selesai"
yang disepakati sebelum koding dimulai**. Sekitar 5 modul besar per bulan
lewat begitu saja. Trade-off yang Ekky terima secara sadar: alur ini menambah
beban, tapi menukarnya dengan tidak semuanya jadi terburu-buru.

**Dasarnya templat FRM-IT-01..07** dari Konsultan IT & Management. Tidak
ditelan mentah — kata-katanya dimanusiakan, isinya tidak dikurangi.

### Tiga temuan yang menentukan sistem ini hidup atau mati

**1. Ketujuh formulir TIDAK sejenis — ada tiga kelas.**

| Kelas | Formulir | Sifat |
|---|---|---|
| A | 01 · 05 · 07 | diisi pemohon, bahasa manusia |
| B | 03 · 04 · 06 | **TERISI SENDIRI**, IT tinggal mengonfirmasi |
| C | 02 | harus benar-benar dipikirkan, tak bisa diotomatiskan |

⛔ **Kelas B adalah pembeda sistem ini dari PDF.** Ketiganya mendokumentasikan
pekerjaan yang **sudah** dilakukan — branch/commit, hasil uji, checklist
deploy. Bukti dari kerja nyata 1 Sep: FRM-IT-03 minta commit, sudah ada
(`c28b665`); FRM-IT-04 minta hasil uji, sudah ada (`att:audit-role` berat 0);
FRM-IT-06 minta backup/rollback, checklist itu memang dijalankan hari itu.
**Kalau Kelas B dirancang sebagai formulir kosong untuk diketik ulang, di
situlah sistemnya mati** — orang berhenti mengisi dalam dua minggu.

**2. Jenjang S/M/L, dan 02 penuh JANGAN diminta untuk M.** Tim dua orang akan
tenggelam. S = 01 + catatan singkat. M = 02 ringkas (hanya tabel FR +
Kriteria Diterima). L = ketujuhnya penuh.

**3. Paraf dipangkas 3 → 1-2.** Templat minta 3 tanda tangan di hampir tiap
tahap; di tim 2 orang itu berarti orang yang sama meneken kotak berbeda —
teater, bukan kendali. FRM-IT-03 dan 04 tanpa paraf pihak lain sama sekali.

### Desain: dimulai ulang atas keputusan Ekky

Ronde pertama dihentikan bukan karena hasilnya jelek, tapi karena
**tampilannya berubah-ubah dan jadi susah dilacak**. Ekky memilih mulai bersih
dengan brief tertulis. Keputusan itu benar.

⚠️ **Sumbu papan sempat salah, dan itu salahku.** Brief pertamaku menekankan
"menunggu siapa — ini bukan detail, ini inti", jadi desainer membuat *pemegang
tiket* sebagai kolom. Yang benar: **kolomnya FASE**; "menunggu siapa" itu
atribut di dalam kartu.

Hasilnya paket di `Downloads/design_handoff_it_tracker/`. Yang paling berharga
di situ bukan gambarnya, tapi **`it-store.js` — implementasi rujukan mesin
fasenya** (`pathFor` per jenjang, `RANK`, gerbang tiap transisi beserta daftar
syarat yang kurang, posisi antrean). §14 menunjuk ke berkas itu alih-alih
menceritakan ulang logikanya dalam prosa; ruang salah tafsirnya jauh lebih kecil.

**Aturan panas yang tak boleh diubah:** hanya menunggu **di luar IT** yang
memanas (≥3 hari kuning, ≥7 hari merah). Tiket 30 hari yang duduk di DEV
**tetap heat 0** — papan ini menagih orang lain, bukan menghakimi IT sendiri.
Ini jadi kendali negatif wajib di uji terima.

### §14 Rencana Build

Enam tabel (`it_ticket`, `it_ticket_fase`, `it_form`, `it_fr`, `it_test_case`,
`it_temuan`). Dua keputusan skema yang paling mudah salah, dikunci di dokumen:

- `prioritas_pemohon` dan `prioritas_it` **wajib dua kolom terpisah**.
- "berapa hari menunggu" **dihitung** dari `it_ticket_fase.masuk_at`, tidak
  disimpan sebagai angka. Angka tersimpan akan basi diam-diam, dan seluruh
  kredibilitas papan bergantung pada angka ini benar.

Kelas B diisi perintah artisan `it:sync-git` + tabel `it_git_ref` — **bukan**
menjalankan `git log` dari request web.

**18 uji terima**, termasuk tiga kendali negatif: tiket 30 hari di DEV harus
`heat=0`, cacah baris tabel lama tak berubah, dan pemeriksaan **URL akhir**
bukan cuma kode HTTP.

### Lingkungan lokal — ⚠️ RALAT, klaim awalku terlalu longgar

**Yang kutulis semula (dibiarkan supaya jejaknya terlihat):** *"MariaDB lokal
yang 'crash' 28 Agu ternyata shutdown bersih; galat InnoDB 'LSN in the future'
terbatas di `rblas_db` saja. Nyala 0 galat, Laravel boot ke DB `sweettreats`
(129 tabel, 36 user)."*

**Yang terukur beberapa menit kemudian (2 Sep 21:00-21:10):** mysqld lokal
**segfault dan mati, 7+ kali**. Pemicunya kueri yang meminta **ukuran** tabel
se-skema — `information_schema.tables` dengan `data_length` — yaitu persis apa
yang dijalankan `php artisan db:show` dan kebanyakan panel GUI database.
⛔ **Jangan jalankan `php artisan db:show` di lokal.**

Bagian "shutdown bersih 28 Agu" itu **tetap benar** — tapi aku memakainya
sebagai bukti sehat hari ini, dan itu yang salah. Jumlah tabelnya juga keliru:
**136, bukan 129**.

**Tiga dugaan yang KUTOLAK dengan pengukuran** (dicatat supaya tak diulang):
- Bukan satu tabel rusak. Tersangka `sys_project_todo` sehat sendirian, dan
  kueri yang **mengecualikannya tetap crash**.
- Bukan ambang jumlah tabel. 126 crash, **130 selamat** — non-monoton, flaky.
- Bukan statistik persisten InnoDB. Dimatikan → **tetap crash 3 dari 3**.

⚠️ Pencarian binerku sempat menghasilkan tersangka yang terdengar meyakinkan
dan **salah**; yang menangkapnya **kendali negatif**, bukan kecerdasan. Pola
yang layak diingat: kalau menduga "tabel X rusak", uji dulu *kecualikan X*.

⚠️ Dan jebakan lamanya muncul lagi dalam wujud baru: **`mysql_error.log` tidak
mencatat crash ini sama sekali** (mysqld jalan mode `--console`), berkasnya
terakhir ditulis 28 Agustus. Sama persis dengan pelajaran `laravel.log` nol
galat waktu prod mati 29 Agu: **log sepi bukan bukti sehat.** Buktinya ada di
`mysqld.dmp`.

**Praktisnya untuk chat build: lokal tetap layak dipakai.** `SELECT` biasa,
`count(*)`, dan migrasi/DDL semuanya aman dan sudah diuji. Yang harus dijauhi
cuma perintah/panel yang menampilkan ukuran tabel se-skema.

### Status

Dokumen: `docs/rencana/rencana-it-tracker.md` (§1-13 pemetaan, §14 build) dan
`docs/brief-desain/design-brief-it-tracker.md`. **Prod tak tersentuh.**
Berikutnya: chat build terpisah, Fase 1 (papan + FRM-IT-01) dulu.

---

## 2026-09-01 dini hari — KONSULTAN + RESTRUKTURISASI ROLE NAIK PROD

Prod `86dca82` → **`c28b665`**. Gerbong yang tertahan sejak 21 Agustus akhirnya
naik: tiga role Konsultan per bidang, **Koordinator GS**, dan pemindahan bagan
di bawah Supervisor. Nol SQL tambahan selain satu berkas, **nol rsync**
(nol berkas `public/` berubah).

### Dua garis yang menyimpang akhirnya disatukan

`main` memuat Konsultan; `prod` memuat saklar Accurate + Komisi Dokter. Keduanya
digabung jadi `prod/konsultan`: **nol konflik, nol tumpang tindih kode** — kedua
sisi menyentuh berkas yang berbeda. Diuji di dev lebih dulu karena kombinasi
ini belum pernah ada di lingkungan mana pun.

### ⚠️ Aku salah memberi tahu: SQL ini MEMINDAHKAN ORANG

Kukatakan ke Ekky "deploy ini membuat role, tanpa memindahkan siapa pun."
**Salah.** SQL-nya memindahkan empat orang sekaligus:

| Orang | Dari | Ke |
|---|---|---|
| Achmad Haickal | Consultant | **Konsultan Finance** |
| Rahmat | Consultant | **Konsultan IT** |
| Hari | Consultant | **Konsultan Marketing** |
| Alpajri Suhendar | General Services | **Koordinator GS** |

Aku sempat melihat keadaan itu di dev 27 Agu dan membacanya sebagai "chat build
menyiapkan dev", bukan "SQL-nya yang melakukannya". Bukti ada di depan mata,
kesimpulan salah — pola yang sama dengan salah baca `git reset --hard`.

**Akibatnya bukan cuma salah omong**: begitu SQL jalan tapi kode belum,
`isFinance()` belum mengenal `Konsultan Finance`, sehingga Haickal sesaat
**boleh mengedit petty cash** — kuasa yang Finance sendiri tak punya. Jendelanya
pendek (SQL → deploy berturut-turut, jam 04.46 WIB, nol user aktif) dan langsung
ditutup, tapi urutan yang benar seharusnya **kode dulu, baru SQL**, persis
kebalikan dari yang kulakukan.

### ⚠️ Alarm kedua yang TIDAK beralasan — dan datanya yang membuktikan

Sesudah SQL, Alpajri kehilangan cabang (`raw_branch_id` NULL) dan posisi
aktifnya tertutup. Kuduga itu kerusakan `syncFromUser()`. **Bukan.** Ketiganya
disengaja dan alasannya tertulis di SQL:

- **Cabang NULL** — Koordinator GS itu peninjau lintas cabang seperti Anisa &
  Irma, yang juga NULL dan **tetap punya baris `att_day`**, karena cabang datang
  dari mesin scan bukan dari `users.raw_branch_id`.
- **Posisi ditutup** — supaya Fajri tak ikut jadi penyebut "staf GS" di
  dashboard review; kalau dibiarkan **dia meninjau dirinya sendiri**.
- **Dicocokkan lewat NAMA LENGKAP, bukan email** — email prod/dev berbeda, dan
  ada akun "Fajri" lain yang **bukan** orang ini.

Aturan kerjanya **`GS Pagi` masih aktif**, jadi dia tak lenyap dari Kehadiran.

### Terverifikasi di prod

**`att:audit-role` berat 0** (7 sedang · **0 ringan** · 4 ok — lebih bersih dari
dev yang 10/1/2). Pagar uang tertutup: `Konsultan Finance` → `isFinance=true`,
**`canEditPettyCash=false`**. Ketiga Konsultan `isPengamat=true`. Saklar Accurate
**tetap mati**. Komisi Dokter tetap hidup. `access:rebuild` 24 berkas, mtime
bergerak. **Nol galat baru**, semua halaman 200.

Backup: `~/backup/prod_pre_konsultan_20260901_0446.sql.gz` (49 MB), titik balik
`86dca82`.

### Ramdan — pemindahan Reza berlaku hari ini

Reza memindahkannya **berjadwal**, dan per 1 September posisi `home` Bintaro
sudah **AKTIF** sementara Kalideres & rolling Pamulang tertutup 31 Agu. Bintaro
sempat punya dua GS, Kalideres diisi Noval, Pamulang sengaja kosong. Absensinya
utuh — `att_day` 26–30 Agu tercatat Pamulang dari mesin scan.

⚠️ Untuk Finance: `users.raw_branch_id` Ramdan kini **Bintaro**, dan penguncian
gaji memakai cabang **asal**. Jadi bulan Agustus ia bekerja di **Pamulang**,
tercatat di Pamulang, tapi muncul di lembar gaji **Bintaro** — padahal sebelum
perubahan Reza cabang asalnya **Kalideres**. Tiga cabang dalam satu periode.

### Tambahan sore — akses laporan untuk kedua konsultan

Permintaan Ekky: Hari & Haickal diberi "sales report seperti yang Sales
punya" + Executive Report. **Diperiksa dulu, dan sebagian besar ternyata
tak perlu dikerjakan** — `report` (Laporan Penjualan) dan `report/executive`
sudah mereka miliki dari SQL Konsultan pagi tadi. Yang benar-benar kurang
cuma **`sales-dashboard`**.

Diberikan lewat `database/sql/2026_09_01_konsultan_dashboard_sales.sql`
(idempoten, dijalankan 2x hasilnya sama) + `access:rebuild`. Node induknya
**ikut di-grant** — tanpa itu anak tak muncul di sidebar.

Terverifikasi dengan memanggil gerbang sungguhan, bukan membaca baris grant:
ketiganya **BOLEH**, dan menunya muncul di `Data Center > Dashboard >
Dashboard Sales`.

**SENGAJA TIDAK diberikan** walau role Sales punya: `rawdoctor`,
`rawcustomer`, `pettycash`, `rawreservation` — itu data operasional
pasien/dokter/uang, bukan laporan, dan konsultan itu PENGAMAT.

ℹ️ Ganjil tapi bukan temuan gerbong ini: **Finance (Della) & Head Marketing
(Yovanka) TIDAK punya** akses ke dua laporan itu, sementara konsultan punya.
Diserahkan ke Ekky untuk ditinjau.

### Utang yang tersisa

**Checklist Alpajri belum tuntas**: 1 draft (10 Agu) + 3 missed (25, 27, 30 Agu).
Posisinya sudah ditutup, jadi **tak ada lagi jalan baginya menyelesaikannya**.
Ekky sudah diberi tahu sebelum deploy dan memilih lanjut.

---

## 2026-08-29 dini hari — Komisi Dokter naik prod · dan aku MEMATIKAN PROD 6 menit

Halaman **Komisi Dokter** (Reza) naik prod `86dca82`. Tapi entri ini terutama
tentang **kesalahanku sendiri**: `rsync` yang kujalankan untuk mengirim CSS/JS
**menimpa `public_html/index.php`** dan membuat seluruh situs 500.

### ⛔ INSIDEN — `rsync -a public/ public_html/` MEMBUNUH PROD

`public_html` prod adalah **salinan sejajar**, bukan symlink. Karena itu
`index.php` di sana **sengaja berbeda**: ia menunjuk `__DIR__.'/../laravel/…`,
sedangkan `laravel/public/index.php` menunjuk `__DIR__.'/../…`. Aku menyalin
**seluruh** direktori, jadi versi laravel menimpa versi public_html dan PHP
mencari `vendor/` di tempat yang tak ada.

**Gejalanya menyesatkan**: `laravel.log` **nol galat**, karena gagalnya terjadi
sebelum Laravel sempat hidup. Aku sempat menyimpulkan "nol galat = sehat"
padahal situsnya mati. Yang membongkarnya cuma `curl` ke `/` → **500**.

**Aku tahu aturannya** ("public_html prod itu SALINAN") dan tetap menjalankan
`rsync -a` untuk seluruh `public/`. Aturannya kupakai untuk mengingat *rsync-nya
wajib*, bukan untuk memikirkan *apa lagi yang ikut tersalin*.

> **ATURAN BARU — rsync ke `public_html` prod WAJIB dibatasi:**
> ```
> rsync -a laravel/public/assets/ public_html/assets/
> ```
> **JANGAN PERNAH** `rsync` seluruh `public/` → `public_html/`.
> `index.php` dan berkas akar lain di sana bukan salinan — mereka disesuaikan.

Ditambal dengan mengembalikan ketiga rujukan `index.php` ke `../laravel/`
(maintenance, vendor/autoload, bootstrap/app). Prod hidup lagi: `/` **200**.
Padam **±6 menit**, jam 02.00 WIB — nol user aktif.

⚠️ Penambalannya sempat **diblokir classifier** dua kali (menyunting dan bahkan
membaca `public_html/index.php`). Aku berhenti dan menyerahkan perintahnya ke
Ekky daripada memaksa lewat jalan lain; akhirnya jalan lewat PowerShell.

### DATA AMAN — dan itu diperiksa, bukan diasumsikan

Yang rusak **hanya satu berkas PHP di pintu depan**. SQL malam ini **murni
menambah** (`INSERT IGNORE` + `CREATE INDEX`) — nol `UPDATE`, `DELETE`, `DROP`.

| Bukti sesudah pulih | |
|---|---|
| `omnicare_sales_detail` | 145.699 baris |
| `omnicare_commission` | 87.706 (tumbuh 53 dari sync 02.00 — normal) |
| komisi Agustus | **Rp 403.940.654**, tak bergeser |
| `accurate_push_log` | **3.118, tak bertambah** — saklar tetap menahan |
| `att_day` Agustus | 483 baris |

Backup penuh 48 MB dibuat **sebelum** apa pun:
`~/backup/prod_pre_komisi_20260828_1857.sql.gz`, titik balik kode `e796d3a`.

### Komisi Dokter — auditnya

Reza menghindari **empat** jebakan langganan proyek ini, sadar dan tertulis di
kodenya: segmen URL sendiri (bukan di bawah `finance` yang ada di `SKIP_RBAC`),
grant **by link bukan by id node**, ikon **FA5** bukan FA6, dan — yang paling
penting di halaman uang — **tanpa `JOIN` ke `raw_doctor`**, karena
`omnicare_doc_id` kembar akan melipatgandakan `SUM(commission_amount)`.
Dikelompokkan per `doc_id`, bukan nama dokter.

Branch-nya **115 commit di belakang main** tapi merge **nol konflik**. Kedua SQL
idempoten — dibuktikan dijalankan dua kali di **dua keadaan berbeda** (tabel
belum ada → skip; index sudah ada → skip).

**Angkanya cocok tiga arah**: KomisiQuery = SQL pembanding independen = **yang
sudah ditulis push ke Accurate sebagai "Beban fee dokter"**, Rp 403.940.654 ·
2.324 tindakan · 29 dokter, **selisih Rp 0**.

9 kasus render lulus di prod (19–669 ms), termasuk rentang kosong, **tanggal
terbalik yang ditukar sendiri**, dan tanggal ngaco yang jatuh ke bawaan.

**Temuan laten, tak menahan**: push Accurate menjumlahkan komisi **tanpa
memfilter `commission_role`**, Reza memfilter `doctor`. Hari ini selisihnya nol
(87.706 baris semuanya `doctor`). Kalau Omnicare kelak mengirim role lain,
**jurnal Accurate yang akan salah**, bukan laporan Reza.

### ⚠️ Prod masih sengaja di luar `main`

Prod berjalan dari **`prod/komisi-dokter`** (`86dca82`) = `prod/accurate-saklar`
+ Komisi Dokter. **Tanpa Konsultan**, yang belum disetujui prod. Saklar
`ACCURATE_PUSH_ENABLED=false` **terverifikasi masih menahan** sesudah merge.
Saat Konsultan disetujui: gabungkan branch prod ini ke main DULU, baru
kembalikan prod ke main — kalau tidak, saklar **dan** Komisi Dokter hilang.

---

## 2026-08-28 pagi — Kiriman ke Accurate DIJEDA atas permintaan Finance

Finance mapping ulang **di sisi Accurate**, jadi kita harus berhenti mengirim
apa pun ke sana. Naik prod **`e796d3a`**, jam 09.00 WIB — nol SQL, nol rsync.

### Kenapa ini bukan sekadar mematikan cron

Push harian **bukan cuma menambah**: hari yang berubah **dihapus lalu ditulis
ulang** (`sales-receipt/delete.do` → `sales-invoice/delete.do` → re-push).
Run 03.30 pagi ini membuktikannya: 5 hari baru, **1 diperbaiki** (Pamulang
22 Agu). Membiarkannya menyala selama Finance menata ulang akun = menyiram
buku Accurate tiap 03.30 dengan dokumen yang mengacu mapping lama.

### ⚠️ Jebakan yang hampir kena — `is_active` TIDAK memagari push

Cara paling naluriah, menonaktifkan `accurate_credentials.is_active`, **tidak
menghentikan push sama sekali**: `AccuratePushDaily` tak pernah membaca kolom
itu. Yang mati justru `accurate:pull-glaccount` dan `accurate:resolve-host` —
tarikan Chart of Accounts yang Finance **butuhkan** untuk mapping ulang.
Persis kebalikan dari yang diminta. Diperiksa dengan grep, bukan diasumsikan.

### Yang berhenti vs yang tetap hidup

| Berhenti (MENULIS) | Tetap jalan (MEMBACA) |
|---|---|
| `accurate:push-daily` — jadwal **dan** manual | `omnicare:sync` 02.00 — cermin lokal tetap terisi, **nol data hilang** |
| jalur **buku asli** `accurate:push-sample` | `accurate:pull-glaccount` — COA tetap segar |
| | `rop:tarik-vendor`, sandbox push-sample |
| | Ekspor CSV ROP — impor **manual** Finance, bukan kiriman kita |

Gerbangnya dipasang di **penjadwal DAN di dalam perintah**. Mematikan cron saja
tetap membiarkan orang menjalankannya manual — dan perintah ini menghapus
dokumen. `--force` disediakan untuk sekali jalan terkendali; `push-daily`
mewariskannya ke `push-sample` karena sudah lolos gerbangnya sendiri.

### Terverifikasi

**Dev 9/9** — bawaan `true` (deploy sendiri tak mengubah apa pun) · jadwal
muncul saat menyala · menolak saat mati · `--force` menembus · jalur buku asli
ditolak sementara sandbox lolos · dan yang menentukan: **`schedule:run` tidak
menjalankannya**.

**Prod 5/5** — perintah menolak dengan sebabnya · `push-sample` buku asli
ditolak · `schedule:run` diam · keempat tugas Omnicare/vendor tetap terjadwal ·
`pull-glaccount` sukses di **kelima buku cabang** (176–181 akun).

### Dua kesalahanku sendiri, dua-duanya GAGAL SENYAP

Ditemukan justru saat menguji pagar yang dibuat untuk mencegah kegagalan senyap:

1. Kolomnya **`trans_date`**, bukan `trx_date` — ditebak, tak dibaca.
   `try/catch` akan menelan galatnya, jadi pengingatnya cuma **tak pernah
   muncul** dan tak ada yang tahu kenapa.
2. Filter `!= 'deleted'` ikut menghitung baris **`dry_run`** sebagai terkirim,
   membuat saran `--days` terlalu pendek. **Saran yang terlalu pendek lebih
   berbahaya daripada tak ada saran.**

Keduanya ditutup, dan kueri versi benarnya diadu dengan data prod (baca-saja,
3.118 baris).

### ⚠️ Yang WAJIB diingat saat menyalakan lagi

Jendela auditnya **14 hari**. Kalau jeda lebih lama dari itu, hari-hari yang
lebih tua **tidak akan pernah ikut terkirim** — dan tak ada yang memberi tahu.
Karena itu tiap kali gerbangnya menolak, ia mencetak angkanya sendiri:

```
Terakhir ter-push: 2026-08-27 (1 hari lalu).
Saat menyalakan lagi jalankan sekali dengan --days=3.
```

Resep menyalakan: ubah `.env` → `php artisan config:clear` → **jalankan sekali
manual dengan `--days` yang dicetak** → baru serahkan ke cron.

### ⚠️ Prod sengaja TIDAK di `main`

`main` (`2853d07`) sudah memuat gerbong **Konsultan** yang belum disetujui naik
prod. Jadi "deploy main" bukan tindakan netral. Saklarnya dipetik terpisah ke
atas `e70dad8` (pra-Konsultan) sebagai branch **`prod/accurate-saklar`**, dan
prod berjalan dari situ. **Saat Konsultan disetujui, branch ini digabung ke main
dulu, baru prod dikembalikan ke main** — kalau tidak, saklarnya hilang.

Cadangan: `~/backup/env.prod.pra-saklar-20260828_0159`, titik balik `761d7a7`.

ℹ️ Bukan temuan kita: sandbox `testing` gagal `pull-glaccount` dengan *"Data
Session Key tidak tepat"* — **sudah begitu sejak 4 Agustus**, galat & endpoint
sama persis. Kelima buku asli tak terpengaruh.

---

## 2026-08-27 — EMPAT GERBONG NAIK PROD dalam satu hari

Prod berjalan `a49a0ef` → … → **`761d7a7`**. Empat gerbong, masing-masing
di-backup, diverifikasi, dan dicatat sendiri-sendiri.

| Jam | Gerbong | Commit | Verifikasi |
|---|---|---|---|
| 11.23 | **Lembar Bayar ROP** | `0e7f26d` | 16/16 |
| 16.34 | **§M dua pagar lembur** | `26381ee` | 8/8 + kendali negatif |
| 16.57 | **Pasien Baru / Cekat** (Reza) | `67cd56b` | 31 dev + 16 prod |
| 17.24 | **§N belajar-sendiri IPv4** | `761d7a7` | 15/15 + dua putar-ulang |

### §N — belajar-sendiri IPv4, dan cara membuktikannya tanpa memercayai siapa pun

Yang naik: `WifiBelajarSendiri` kini juga belajar IPv4, ambang saksi sama
dengan IPv6 (**2 orang / 72 jam**), tapi hasilnya baris **COCOK-PENUH**, bukan
prefix. Nol SQL, nol rsync (blade berubah, `public/` tidak). Deploy **PENUH**
karena ada class baru `WifiIpv4Simulasi`.

**Angka c5 tidak dipercaya begitu saja — diulang sendiri.** Caranya: worktree
terpisah di server PROD dari `origin/feat/wifi-ipv4-belajar`, **vendor DISALIN
80 MB, bukan di-symlink**. Symlink membuat `$baseDir` Composer menunjuk balik ke
`app/` prod, jadi yang jalan diam-diam kode LAMA dan hasilnya hijau palsu.
Pagar: `ReflectionClass::getFileName()` dicetak sebelum apa pun dihitung.

**Putaran A** (dunia nyata, 2–27 Agu, 686 absen):

```
tertolong   130     hilang  0  ← HARUS NOL
pindah        0     ← cocok ke cabang LAIN, HARUS NOL
tetap cocok 511  ·  tetap gagal  45
✓ att_branch_ip 38 baris, att_hp_bukti 686 — sama seperti sebelum.
```

Cocok persis dengan laporan c5 (mereka 44 tetap-gagal, aku 45 — satu absen baru
masuk di antara dua putaran). **Reproduksi independen, bukan konfirmasi sopan.**

**Putaran B** — mencabut dulu 11 baris IPv4 manual 23 Agu, lalu tanya: *apakah
mesin menangkapnya sendiri?* **7 dari 11 ter-belajar, semuanya lebih awal dari
manusia** (mis. `180.244.158.43` Sunter 16 Agu vs manual 23 Agu). Dan
`hilang 3` di putaran ini **bukan regresi** — kita memang sengaja mencabut
barisnya; **4 alamat tak tergantikan** (Bintaro ×3, Cinere ×1) saksinya orang
fleksibel sehingga tak pernah mencapai ambang. Itulah sebabnya perintahnya:

> ⛔ **JANGAN cabut 10 baris IPv4 manual 23 Agu.** Tujuh punya pengganti
> otomatis, empat **tidak**.

**Pagar `ipv4Publik()` wajib tetap ada** — dan isinya lebih teliti dari dugaan:
selain `FILTER_FLAG_NO_PRIV_RANGE`, ia menutup **100.64.0.0/10 (CGNAT)** secara
khusus, blok yang flag PHP itu justru tak cakup. Tanpa pagar ini, di belakang
proxy yang salah konfigurasi satu alamat bisa terdaftar dan **kelima cabang
terbaca jadi satu**.

**Verifikasi prod 15/15**: 10 baris manual utuh · 4 tak-tergantikan utuh ·
`att_branch_ip` tetap 38 (deploy tak menulis apa pun) · tiga alamat terdaftar
cocok sementara **tetangga oktet +1 tetap DITOLAK** (nol pelebaran) · lima
alamat privat/CGNAT/loopback ditolak · blade kompilasi bersih · 4 rute & 2
perintah artisan terdaftar · **nol galat baru di `laravel.log` sesudah deploy**.

**Bukan janji — ditunjukkan.** Simulasi dijalankan lagi di prod dengan kode yang
sekarang hidup (24–27 Agu): mesin **mendaftarkan sendiri** `36.69.103.246`
(Sunter, saksi Della & Melani) dan `180.251.182.171` (Pamulang, saksi Mutiara &
Ramdan) — persis dua alamat yang c5 ramalkan. Sementara `125.165.152.185`
(Bintaro, muncul hari ini) **tetap ditolak: `orang_kurang`**. Ambangnya bekerja,
bukan meloloskan borongan.

Cocok otomatis per pekan: **77% · 72% · 74% · 81%** — dan angka pekan berjalan
belum memuat efek §N sama sekali, karena deploy baru jam 17.24.

### Yang perlu diketahui, bukan temuan gerbong ini

- **`[2002] Operation not permitted` muncul 41 kali di prod hari ini**, semuanya
  **sebelum** deploy. Ini penolakan koneksi MySQL hosting bersama, gejala yang
  sama dengan yang membatasi phpunit ke ±17 uji per proses. Sesudah deploy: nol.
  Bukan disebabkan gerbong mana pun, tapi layak diawasi sendiri.
- **Daftar "alamat masih gagal" itu SEJARAH, bukan keadaan sekarang.**
  `branch_id_cocok` ditulis pada saat absen; baris sebelum pendaftaran manual
  23 Agu selamanya berbunyi gagal. Yang benar-benar masih gagal sesudah 23 Agu
  cuma **tiga**, dan dua di antaranya kini punya jalan keluar otomatis.

### Dev dikembalikan — ke `main`, bukan ke branch asalnya

Dev tadinya di `feat/wifi-ipv4-belajar`. Dikembalikan ke **`main` `761d7a7`**,
bukan `feature/rop-lembar-bayar` seperti catatan serah-terima, **setelah
diperiksa** bahwa branch itu **0 commit di depan main** — jadi tak ada yang
hilang, dan dev kini cermin prod. Sepuluh perubahan di dev semuanya
**untracked**, aman melewati pindah branch.

⛔ **Dan tujuh berkas Konsultan + Alur Persetujuan MASIH menunggu di-commit** —
`AlurPersetujuanService`, `AlurPersetujuanController`, `AuditRole`, dua berkas
uji, dua SQL. Ada di dev, di working copy lokal, dan di
`wip/snapshot-konsultan-alur-2026-08-22`. Sudah diperiksa masih utuh sesudah
dev pindah branch. **Chat pemiliknya yang harus commit.**

### Catatan kerja

Tiga kali skrip verifikasi merah karena **nama kolom ditebak**: `ip_address`
(sebenarnya `ip`), `branch_name` (sebenarnya `name`). Semua di skrip pemeriksa,
nol di kode yang naik. Pengulangan pelajaran lama: **baca
`Schema::getColumnListing()` dulu, jangan tebak.** Satu galat 255 tanpa keluaran
sama sekali ternyata cuma `require "vendor/autoload.php"` yang lupa —
`display_errors` mati di CLI prod, jadi fatal error keluar senyap.

---

## 2026-08-27 — DUA PAGAR PENGAJUAN LEMBUR (dev lulus, prod belum)

Rencana `docs/rencana/rencana-pagar-lembur.md` dibangun utuh. Keduanya lahir
dari audit DODO 23–24 Agu, dan keduanya soal **uang salah bayar tanpa ada yang
bersuara**. Branch `feat/pagar-lembur` (dari `main` `0e7f26d`), commit
`c363de9`. **Nol SQL, nol `public/assets`** → tak perlu migration maupun rsync.

### Pagar 1 — pengajuan lembur KEMBAR

Sebelum ini, tak ada apa pun yang mencegah dua pengajuan lembur di-ACC untuk
orang + tanggal + jam yang sama. Kejadian nyata: **Afrina, 10 Agu** — req#30 &
req#31 dikirim berselang **116 detik**, jam (19:15–21:10), alasan, dan menit
identik; Anisa menyetujui keduanya berselang 8 menit; reconciler menjumlahkan →
**230 menit dibayar untuk sesi 115 menit**. Sudah dibetulkan 23 Agu, tapi
pagarnya belum ada.

Kelas baru `App\Services\LemburKembar`. Tiga perlakuan berbeda, sengaja:

| Keadaan | Perlakuan |
|---|---|
| jam **persis sama** | **ditolak** saat mengajukan; pesannya menyebut nomor pengajuan yang sudah ada + apa yang harus dilakukan |
| jam **bertindih sebagian** (19:00–21:00 vs 19:15–21:10) | **diteruskan**, tapi penyetuju melihat peringatannya di kartu, tepat di atas tombol Setujui |
| bersambung (08–10 lalu 10–12) atau terpisah | lewat, tak diganggu |

Yang **tidak** dilakukan, dan itu disengaja: tak ada penggabungan otomatis, tak
ada penolakan diam-diam. Dua sesi lembur terpisah di hari yang sama itu **sah**;
yang salah selama ini adalah tak ada yang diberi tahu. Status `rejected` juga
sengaja tak ikut memagari — itu justru cara kembar dibetulkan (req#31
di-`rejected`, bukan dihapus), jadi kalau ia ikut memagari, pengiriman ulang
yang benar jadi mustahil.

Dipasang di **dua titik**: saat mengajukan DAN saat meng-ACC. Tombol yang
disembunyikan bukan pagar — form buatan sendiri tetap bisa POST. Di titik simpan
malah dicek dua kali: sekali lebih awal (pesan rapi, foto tak terlanjur
tersimpan) dan sekali lagi **di dalam transaksi**, supaya klik ganda tak
menyelinap.

### Pagar 2 — peringatan chip datang SESUDAH keputusan

`LemburDodo::chipMencurigakan()` menyaring `status = 'approved'`, jadi ia
memperingatkan Finance soal yang **sudah terlanjur di-ACC**. Uangnya memang
masih terlindungi (peringatannya di Lembar Gaji, layar terakhir sebelum gaji) —
jadi ini bukan lubang. Tapi menangkap sebelum ACC jauh lebih murah daripada
mencabut ACC.

- `pending_l1`/`pending_l2` kini ikut diperiksa. (`pending_l2` diikutkan walau
  lembur hari ini satu lapis — supaya pagarnya tak ikut berlubang diam-diam
  kalau rantainya diperpanjang.)
- Yang lebih berharga: peringatannya kini tampil **di layar persetujuan**.
  Penyetuju punya konteks "hari itu dia libur atau tidak"; Finance sering tidak.
- Baris di Lembar Gaji kini menyebut **sudah/belum di-ACC** — yang belum masih
  bisa dibetulkan pengajunya tanpa mencabut apa pun.

⚠️ "Hari libur" **tidak** dihitung ulang di layar persetujuan: tetap lewat
`AttendanceReconciler::liburUntuk()` (aturan mingguan + kalender libur + cabang
bertugas). Keputusan cocok/tidaknya chip dipusatkan di
`LemburDodo::masalahChip()` supaya dua layar tak punya dua definisi yang
lambat laun berselisih. Jalan pintas `att_day.status='libur'` sudah terbukti
memberi 2 positif palsu — reconciler menulis `hadir` ketika orang bekerja di
hari liburnya.

### Uji terima §3 — 11 poin, semua lulus di dev

**28 uji baru+lama hijau** (9 `LemburDodoTest` lama + 16 `PagarLemburTest` +
3 `PagarLemburLayarTest`), 72 assertion.

Satu di antaranya **fixture nyata dari prod**, disumbang sesi HUB yang menyapu
prod baca-saja: **Alpajri Suhendar, 11 Agu** — req#113 (08:00–10:00, 120 menit)
dan req#92 (18:00–23:31, 331 menit), dua-duanya di-ACC, `att_day` 451 = 120+331.
Orang ini benar-benar bekerja dua giliran dan reconciler benar menjumlahkannya.
Pagar kembar **wajib** membiarkannya lewat — pagar yang menolak orang jujur
lebih mahal daripada kembar yang lolos, karena korbannya berhenti memakai
formnya sama sekali. Jamnya dipertahankan apa adanya (23:31, bukan dibulatkan
jadi 21:00): angka ganjil dan rentang panjang justru yang menjatuhkan pembanding
ceroboh. Diuji dari dua arah — saat mengajukan **dan** lewat `peta()` yang
dipakai layar persetujuan.

**Sapuan prod (baca-saja, oleh sesi HUB): 0 pasang bertindih.** Pola Fajri-15-Agu
yang ketemu di dev belum terjadi di prod; satu-satunya kembar prod (Afrina) sudah
ditutup 23 Agu. Jadi pagar ini naik sebagai **pencegahan, bukan pemadam** —
blast radius nol.

**Kelasnya sendiri lalu DIADU dengan data prod — 8/8.** Sapuan pertama memakai
kueri irisan yang berdiri sendiri, bukan `LemburKembar` — dua implementasi yang
belum pernah diadu, dan "0" dari kueri yang tak akan pernah hidup di prod itu
keyakinan yang bersandar pada tempat yang salah. Ditutup tanpa menggeser prod:
kelasnya diambil lewat `git show` ke `/tmp` lalu di-`require` di skrip yang
mem-bootstrap aplikasi prod — nol tulis, HEAD prod tetap `0e7f26d`. `peta()`
atas **158 lembur aktif prod** → 0 kartu berperingatan, **sepakat** dengan kueri
yang berdiri sendiri.

⚠️ Yang membuat hasil itu bermakna bukan angka nolnya, melainkan **kontrol
negatifnya**: giliran sisipan 22:00–23:00 (di dalam giliran malam Alpajri) dan
09:30–11:00 (serempet 30 menit) **tertangkap, dan diakui SEBAGIAN — bukan
persis**. Tanpa kontrol itu, "0 temuan" tak bisa dibedakan dari "pemeriksanya
tidur". Sisi sahnya juga diuji: klaim baru 12:00–14:00 di celah antara dua
giliran Alpajri → 0, tak diganggu.

⚠️ **Jebakan yang ikut ketemu, dan sudah ditambal di komentar (`bc5c10e`)**:
menyapu baris yang SUDAH ADA dengan `bertindih()` tanpa `$kecualiId` membuat
tiap baris dibandingkan dengan **dirinya sendiri** — semuanya terbaca "kembar
persis". Itu yang membuat percobaan pertama melaporkan Alpajri "gagal 2×";
harness-nya, bukan kodenya. Bentuk hasil palsu yang paling meyakinkan: angkanya
rapi dan salah. `peta()` menutupnya sendiri, jadi untuk penyapuan itulah pintu
yang benar. **Ini kali ketiga dalam beberapa hari uji merah ternyata patokannya
yang salah, bukan kodenya** — patokan uji memang harus ikut diaudit.

Yang paling meyakinkan justru datang dari **data dev yang sudah ada**: sapuan
menemukan **kembar sungguhan yang masih hidup** — **Fajri, 15 Agu**, req#34
(18:00–21:00, sudah di-ACC) dan req#35 (18:07–21:00, masih menunggu). Dengan
kode lama, Anisa akan menyetujui #35 tanpa satu pun tanda dan Fajri dibayar dua
kali untuk satu sesi. Sekarang kartunya berbunyi. Tiga kartu menunggu lainnya
tetap bersih — nol positif palsu.

Verifikasi lain di dev: **invarian `biasa + dodo = total` pegang di 51 pasangan
orang-bulan, 0 pelanggaran** · `chipMencurigakan` 0 temuan, 5 ms, cakupan naik
dari 6 ke 7 baris · seluruh blade terkompilasi · **cacah baris 5 tabel tak
bergeser sepetak pun** sebelum-sesudah uji (18/6/889/0/48).

⚠️ **Catatan lingkungan, bukan cacat kode**: menjalankan >±17 uji ber-DB dalam
SATU proses phpunit di dev membuat MySQL menolak koneksi (`SQLSTATE[HY000]
[2002] Operation not permitted`) — batas laju koneksi hosting bersama. Terbukti
lingkungan, bukan kode: pada `phpunit` penuh, **`LemburDodoTest` yang tak
kusentuh pun ikut gagal 9/9**, dan tiap kelas lulus 100% berulang kali saat
dijalankan sendiri. Untuk sesi berikutnya: **jalankan per kelas**, jangan satu
proses besar.

### Yang sengaja TIDAK disentuh

- **req#162 (Melani Safrida, 22 Agu, `pending_l1`)** — Ekky sedang mengonfirmasi
  ke Nissa. Dipakai baca-saja sebagai kasus uji; barisnya ada di **prod**, tak
  ada di dev, jadi tak mungkin tersentuh dari sini.
- **`LemburDodo::dasar()`** — invarian `biasa + dodo = total` bergantung
  padanya, sudah terverifikasi di 31 pasangan orang-bulan prod.
- Nol penggabungan otomatis pengajuan kembar. Manusia yang memutuskan mana yang
  sah; sistem cukup memberi tahu.

**PROD BELUM.** Dev ditinggal di `feat/pagar-lembur`, `composer install
--no-dev --optimize-autoloader` sudah dikembalikan.

---

## 2026-08-27 — WiFi belajar sendiri untuk IPv4: cocok-penuh, nol pelebaran (DEV)

Tiga hari lalu sepuluh alamat IPv4 didaftarkan **dengan tangan** ke lima cabang,
dan catatannya sendiri menyebut itu *stopgap*: IPv4 berotasi, daftar itu akan
basi. Hari ini penggantinya dibangun — dan pemutaran ulang data prod menunjukkan
**daftar manual itu memang sudah basi dalam empat hari.**

**Belum naik prod.** Dev lulus penuh; prod menunggu keputusan Ekky.
Branch `feat/wifi-ipv4-belajar`. Nol SQL, nol `public/assets/**` (tak perlu rsync).

### Lubangnya

`WifiBelajarSendiri` **sengaja melewati IPv4** — docblock-nya sendiri menulis
*"IPv4 tak pernah lewat"* — dengan alasan "IPv4 cabang bertahan, cukup
didaftarkan manual sekali". Alasannya benar. Yang tak diantisipasi: **sebuah
cabang bisa tak pernah kebagian pendaftaran manual itu sama sekali.**

Kalideres **nol baris IPv4** sampai 23 Agu. Akibatnya 13 absen dalam 3 minggu
harus pilih cabang manual — belajar-sendiri melewatinya (lewat IPv4), pencocokan
/48 tak menolongnya (cuma IPv6). Jatuh di antara dua jalur, dan tak ada yang
berteriak. Cinere juga nol.

### Yang dibangun

Ambang, jendela, dan penilai saksi **persis sama dengan IPv6** — 2 orang
berbeda / 72 jam, cabang efektif, orang fleksibel tak dihitung — tapi hasilnya
baris **cocok-penuh**, bukan prefix. Empat syarat:

1. alamat itu **belum** terdaftar (baris **nonaktif = veto IT**, tak boleh
   ditawar ulang — pembedaan yang sama dengan veto IPv6),
2. **≥ 2 orang berbeda** memakainya dalam 72 jam,
3. semuanya **memilih cabang yang sama**,
4. alamat itu **tak pernah** dipakai memilih cabang lain **sepanjang sejarah**
   (tanpa batas waktu — jendela menjawab "siapa di sana sekarang", syarat ini
   menjawab "alamat ini pernah jadi milik siapa lagi").

**Nol pelebaran.** `180.252.169.219` tetap ditolak walau `.218` terdaftar.
Melebarkan ke /24 bukan cuma berisiko, **tak akan menolong**: alamat satu cabang
melompat antar blok tak berkerabat (Kalideres memakai `36.77.251/252.x` **dan**
`180.242.129–131.x` dalam periode yang sama).

### Larangan "jangan daftarkan IP seluler" TIDAK dicabut — buktinya yang berubah

Dulu dibuktikan dari **bentuk alamat** (v4 = curiga). Cara itu sudah terbukti
keliru sekali: absen ber-IPv4 di Kalideres sempat disimpulkan "orang pakai data
seluler", lalu Ekky bertanya langsung ke Cici — dia memakai WiFi klinik, dan
datanya membenarkan Cici.

Sekarang dibuktikan dari **perilaku**: data seluler pribadi memberi tiap orang
alamat sendiri, jadi satu alamat yang dipakai beberapa orang cabang yang sama
adalah jaringan bersama. Bukti yang sah = berapa orang berbeda memakainya,
bukan bloknya.

⚠️ **Sisa risikonya diterima sadar, dan dibayar dengan KELIHATAN.** Kalau CGNAT
seluler benar-benar dibagi dua staf cabang yang sama dalam 72 jam, alamat itu
akan terdaftar. Karena IPv4 tak punya pagar kekerabatan /48, tiap baris IPv4
otomatis **wajib** berlabel `PERLU DITINJAU` + notifikasi IT + AuditLog.
Terdaftar tapi kelihatan. **Kalau pertahanan itu suatu saat dilepas, yang
tersisa cuma risikonya** — K6 yang harus merah duluan.

### Uji terima §7 rencana — 12 poin, semuanya hijau

`att:wifi-uji` **15 → 22 kasus, 22/22 hijau di dev.**

⚠️ **K6 BERUBAH ARTI.** Dulu harapannya *"IPv4/CGNAT TIDAK PERNAH daftar"* —
ditulis sebagai pengerasan sadar. Sekarang kasus itu justru mengunci hal yang
lebih penting: dua alamat di dalamnya **tak bisa dibedakan oleh sistem**.
`114.10.70.167` memang CGNAT Telkomsel; `110.137.194.229` memang WiFi Bintaro
(diverifikasi manusia). Dari sisi bukti keduanya identik — satu alamat, tiga
orang Bintaro, sepuluh menit. Menebak mana yang mana dari bentuk alamatnya
adalah kesalahan yang sudah pernah dibuat. Jadi keduanya didaftarkan, dan yang
diuji sekarang adalah pertahanannya: cocok-penuh + label + notifikasi IT.

Kasus baru: **K16** (2 saksi → daftar, tetangga ±1 ditolak) · **K17** (satu
orang berkali-kali → tidak) · **K18** (2 orang beda cabang → tidak) · **K19**
(pernah dipakai cabang lain → tidak, walau 2 saksi sepakat) · **K20**
(`::ffff:` menyamar → jalur IPv4, dua bentuk tulisan jadi SATU baris) ·
**K21** (§6 tinjauan).

### Uji 10 — putar ulang **data prod**, di dalam transaksi yang dibatalkan

Perkakas baru `att:wifi-ipv4-simulasi`. Dijalankan dari **worktree terpisah** di
server prod (vendor **disalin**, bukan di-symlink — symlink membuat autoloader
menunjuk balik ke `app/` prod dan diam-diam menjalankan kode LAMA). Aplikasi
prod yang hidup tak disentuh sama sekali; `att_branch_ip` 38 baris & `att_hp_bukti`
685 baris **sama persis sebelum dan sesudah**.

**Putaran A — tabel pendaftaran apa adanya (regresi murni), 685 absen:**

| | |
|---|---|
| tertolong | **130** (dulu gagal cocok, kini cocok) |
| hilang | **0** |
| pindah cabang | **0** |
| tetap gagal | 44 |

**Putaran B — sepuluh baris manual 23 Agu dicabut dulu**, untuk menjawab
"apakah mesin akan menangkapnya sendiri":

- **7 dari 11** baris manual ter-belajar sendiri — dan **semuanya lebih awal
  dari manusia**: 16 Agu (`180.244.158.43`), 19 Agu (`180.251.180.196`,
  `36.77.251.46`), 20 Agu (`36.71.209.30`), 21 Agu (`36.77.252.85`,
  `180.252.87.18`, `125.166.76.115`). Manusia baru mendaftarkannya 23 Agu.
- **DUA alamat BARU** yang belum pernah didaftarkan siapa pun ikut tertangkap:
  `36.69.103.246` (Sunter, 25 Agu) dan `180.251.182.171` (Pamulang, 26 Agu).
  **Keduanya sedang gagal cocok di prod hari ini.** Daftar manual 23 Agu basi
  dalam empat hari — persis yang diramalkan catatannya sendiri.
- 3 "hilang" di putaran ini **bukan regresi**: ketiganya di `125.165.188.209`,
  alamat yang memang dicabut paksa dari tabel.

### ⚠️ Temuan yang butuh keputusan: aturan durable ini **lebih ketat** dari sapuan manual

**Empat** alamat manual tidak ter-belajar: `110.137.194.229` (Bintaro),
`180.252.80.82` (Cinere), `125.165.189.132` & `125.165.188.209` (Bintaro).
Sebabnya satu dan sama — **masing-masing cuma punya SATU saksi yang sah**:

| alamat | pemakai | yang sah |
|---|---|---|
| `110.137.194.229` | Asih · Yovanka · Alif · Ikhsan | **1** (tiga sisanya fleksibel) |
| `180.252.80.82` | Irma · Verini | **0** — Irma fleksibel, dan Verini asalnya **Sunter** padahal barisnya Cinere |
| `125.165.189.132` | Asih · Della P. · Talitha | **1** |
| `125.165.188.209` | Asih · Della P. · Anisa · Talitha | **1** |

Sapuan manual 23 Agu menghitung **siapa pun**; aturan ini menuntut saksi
**ber-cabang-asal sama & tidak fleksibel** — dan semua orang fleksibel memang
`raw_branch_id`-nya kosong, jadi syarat "cabang asal sama" tak bisa diperiksa
untuk mereka (keputusan lama, sengaja).

Konsekuensi langsung: **§8-3 rencana terbukti benar — jangan cabut sepuluh baris
manual 23 Agu.** Empat di antaranya tak akan tergantikan.

**Pilihan yang tersedia (belum diambil, menunggu Ekky)**: izinkan orang
fleksibel jadi saksi **PENDUKUNG** — butuh ≥1 staf asal **dan** total ≥2.
Keempat alamat di atas akan tertangkap kalau itu dipakai. Ini alternatif yang
sudah pernah dicatat waktu ambang diturunkan 3→2, dan belum pernah ditolak.

### §6 — "perlu ditinjau" hanya untuk yang tak dijamin kekerabatan

Kelima baris otomatis di prod semuanya se-/48 dengan jalur yang sudah ada; sejak
pencocokan /48 mereka cocok dengan sendirinya, jadi menandainya "perlu ditinjau"
tak menambah apa-apa — dan halaman yang berteriak untuk hal yang bukan keputusan
akan berhenti dibaca. Sekarang tinjauan menyala hanya untuk **baris IPv4** dan
**/48 yang benar-benar baru**. Baris otomatis tak boleh menjamin baris otomatis
lain. **Satu aturan** (`WifiBelajarSendiri::perluTinjau`) dipakai label penulisan
**dan** sorotan layar — dibuktikan dengan merender halamannya sungguhan: banner
"1 jaringan", tepat **1 baris disorot** dari 11.

Salin layar juga diluruskan: kalimat *"jaringan seluler tak pernah ikut"* sudah
tidak benar dan sudah dihapus.

### Lubang senyap yang ikut ditutup

`::ffff:a.b.c.d` dan `a.b.c.d` adalah alamat yang sama, dan proxy bisa mengirim
bentuk mana saja. Kalau bentuk yang tersimpan berbeda dari bentuk yang datang,
barisnya **tak akan pernah cocok DAN tak bisa didaftarkan ulang** (UNIQUE(ip)
menolak kembarannya) — macet tanpa gejala. Kini dibakukan di **tiga** tempat:
`matchBranch()`, jalur belajar, dan **kedua pintu pendaftaran manual**
(`AbsensiBranchIpController::store()` + `PengawasanController::daftarkanIp()`).

### Audit pra-prod — dua temuan, keduanya ditutup

Diminta sebelum naik prod. Selain 22 kasus, dijalankan pemeriksa terpisah untuk
jalur yang tak disentuh kasus mana pun (masukan aneh, kedua pintu pendaftaran
lewat HTTP, render Pengawasan & WiFi Cabang) — semua di dalam transaksi yang
dibatalkan.

**⚠️ Bug: alamat DALAM bisa terdaftar otomatis.** `127.0.0.1`, `10.0.0.5`,
`0.0.0.0` lolos sampai penghitungan saksi. Bahayanya baru kelihatan kalau
dirangkai dengan risiko yang sudah lama tercatat: seandainya aplikasi berada di
belakang proxy tanpa `TrustProxies`, `request()->ip()` berubah jadi **satu
alamat yang sama untuk semua orang**. Dua orang cabang mana pun memenuhi ambang,
alamat itu terdaftar, dan **seluruh absen lima cabang terbaca sebagai satu
cabang** — sementara flag `ip_asing` berhenti berbunyi tepat saat dibutuhkan.
Ditutup: menolak privat/loopback/link-local/cadangan + `100.64/10`. Menahan
**mesin** saja — manusia tetap boleh mendaftarkannya, dan baris yang sudah ada
dihormati. Dikunci **K22**.

**⚠️ Inkonsistensi: dokumentasi membantah kodenya sendiri.** Docblock kelas masih
membuka dengan "mendaftarkan prefix IPv6" + EMPAT SYARAT versi IPv6 walau IPv4
sudah ikut; komentar di titik panggil masih menulis "yang KETIGA" padahal
ambangnya 2 sejak 18 Agu. Keduanya diluruskan, dan kini membawa peringatan agar
angka aturan tak diketik ulang di luar tempat asalnya.

### Catatan uji: K10 sempat merah — dan itu DATA DEV, bukan kode

`users.raw_branch_id` **Alpajri kosong di dev** (aturannya `baku`), sehingga ia
tak pernah bisa jadi saksi WiFi dan ambang baru tercapai di orang ke-3. Diperiksa
ke prod: **di prod Alpajri normal (Bintaro), dan tak ada satu pun orang ber-aturan
`baku` yang cabang asalnya kosong.** Murni cacat data dev. Dipastikan dengan
menjalankan K10 di `main` — merah juga di sana.

K10 kini tak lagi mematok "kejadian ke-2"; ia mencari sendiri kejadian saat
ambang tercapai. Urutan ambang tetap terkunci di K7 dengan orang yang datanya
stabil.

---

## 2026-08-24 dini hari — DODO NAIK PROD · sapu IPv4 lima cabang · dua bayar-salah ditutup

Malam panjang: satu fitur naik prod, satu lubang jaringan disapu di kelima
cabang, dan **dua kesalahan bayar ditemukan lalu ditutup** — dua-duanya
ketahuan bukan dari laporan orang, tapi karena datanya diperiksa.

### DODO naik prod — `2ef8a35`

Lembur di **hari libur** tarifnya beda, jadi Finance harus bisa melihatnya
terpisah. Diaudit dulu sebelum naik, statis **dan** hidup di dev.

**Yang membuat kodenya layak dipercaya:**
- `AttendanceReconciler` **penambahan MURNI** (`liburUntuk()`), **nol baris lama
  diubah** — bentuk teraman untuk kode yang menghitung gaji.
- Memakai `CabangBertugas` (naik 22 Agu), **tidak** menyalin ulang definisi
  "libur" yang menyangkut aturan mingguan + kalender + cabang bertugas.
- **`biasa = total − dodo`** ditulis sebagai PENGURANGAN + clamp `min()`, jadi
  `biasa + dodo` **selalu** sama persis dengan angka lama. Kalau suatu hari tak
  sama, itu tanda bocor — bukan pembulatan.
- **Kolom CSV diganti nama, bukan disisipi** (`Lembur (jam)` → `Lembur Biasa
  (jam)` + `DODO (jam)`). Kalau namanya dibiarkan sama sementara isinya
  mengecil, spreadsheet Finance lama akan diam-diam memakai angka yang salah.
  Perubahan yang berisik jauh lebih aman daripada yang sopan.

**Verifikasi prod (6/6)**: invarian pegang di **31 pasangan orang-bulan** ·
Agustus total **22.699** = biasa **22.464** + DODO **235** menit ·
`chipMencurigakan` **0 temuan, 4 ms** · Lembar Gaji & Kehadiran render.
Nol SQL, nol rsync. Merge nol konflik.

### ⚠️ Bayar-salah #1 — lembur kembar Afrina, 115 menit dibayar 230

Ketemu saat mengaudit DODO; **bukan bug DODO** — fiturnya cuma membuatnya
kelihatan. req#30 & req#31: jam sama (19:15–21:10), alasan sama kata per kata,
115 menit masing-masing, dibuat **berselang 116 detik**, dua-duanya di-ACC
Anisa berselang 8 menit. Reconciler menjumlahkan keduanya → **230 menit untuk
sesi 115 menit.**

Ditutup: req#31 di-`rejected` (**bukan dihapus** — jejaknya dijaga) + baris
`att_request_approval` berisi alasannya, lalu `att:reconcile 2026-08-10`.
**230 → 115.** Prod kini **nol kasus kembar**. Pagarnya belum ada → §M.

### ⚠️ Bayar-salah #2 — chip salah, dan yang pertama tertangkap justru Ekky

`req#172` milik Ekky: 23 Agu (**Minggu**), 660 menit, chip `jam` (lembur
biasa). Aturannya `Fleksibel Kantor` yang libur **Minggu & Senin** → itu DODO
yang akan dibayar tarif biasa. Masih `pending_l1`, jadi belum ada uang bergerak;
chip dibetulkan jadi `tambah_hari` dengan jejak di `att_rule_log`.

**Dan celahnya justru terbuka di situ**: `chipMencurigakan()` menyaring
`status='approved'`, jadi peringatannya datang **sesudah** keputusan diambil.
Dijalankan dengan menyertakan `pending_l1`, ia langsung menangkap satu lagi:
**Melani Safrida req#162**, 22 Agu (**Sabtu** = hari kerja biasa baginya),
chip `tambah_hari`, 129 menit — arah sebaliknya. **Sengaja dibiarkan**,
menunggu Ekky mengonfirmasi ke Nissa. → §M.

### WiFi: lubang IPv4 di kelima cabang

Ekky di Kalideres, minta WiFi-nya dicek. IPv6-nya sudah beres sejak /48. Yang
ketemu: **Kalideres tak pernah punya satu pun IPv4 terdaftar** → 13 absen dalam
3 minggu harus **pilih cabang manual**. Cinere juga nol.

`WifiBelajarSendiri` **sengaja melewati IPv4** (asumsi "IPv4 cabang bertahan",
cukup didaftarkan manual sekali). Yang tak diantisipasi: sebuah cabang bisa tak
pernah kebagian pendaftaran manual itu.

Disapu dengan pagar bukti: **≥2 orang berbeda, pilihan cabang konsisten, tak
pernah dipakai cabang lain**. Dari 39 kandidat: **10 didaftarkan, 31 ditolak**
(mayoritas 1 orang — ambangnya bekerja). Putar ulang 21 hari:
**158 kegagalan → 128 kini cocok & benar.** Uji 16/16: kesepuluh cocok, dan
**tetangga oktet +1 tetap ditolak** — IPv4 tetap cocok-penuh, nol pelebaran.

⚠️ **RALAT yang perlu diingat**: aku sempat menyimpulkan absen ber-IPv4 itu
"orang pakai data seluler". **Ekky bertanya langsung ke Cici — dia memakai WiFi
klinik**, dan datanya membenarkan Cici: `36.77.252.85` dipakai **4 orang
berbeda** dalam 5 hari, semuanya memilih Kalideres. Satu alamat dipakai banyak
orang = jaringan bersama. Pengulangan pelajaran peta WiFi Bintaro: **jangan
tebak identitas jaringan dari bentuk alamatnya.**

**Jangan lebarkan IPv4 ke /24** — bukan cuma berisiko, tak akan menolong:
alamat satu cabang melompat antar blok tak berkerabat. → §N.

### Utang yang disiapkan jadi dua chat build

| | Isi | Rencana |
|---|---|---|
| **§M** | pagar lembur kembar + peringatan chip di titik keputusan | `rencana-pagar-lembur.md` |
| **§N** | belajar-sendiri IPv4 (cocok-penuh, nol pelebaran) | `rencana-wifi-ipv4-belajar.md` |

Urutan nilainya: **pagar kembar dulu** — itu satu-satunya yang sudah
benar-benar menyebabkan bayar dobel.

### Terjadwal & tertahan

**Malam ini**: Ramdan pindah PENUH Kalideres → Bintaro, lalu Fajri jadi
Koordinator GS — urutan Ramdan-dulu supaya Bintaro tak pernah kosong pengisi.
Ramdan libur Selasa 25; Pamulang sengaja kosong GS.

⛔ **Konsultan + Alur Persetujuan masih MENAHAN** — kodenya belum di-commit dan
tak bisa dipisah (`AttendanceRequestWorkflow` kini bergantung pada
`AlurPersetujuanService`). Chat pemiliknya yang harus commit.

---

## 2026-08-22 malam — DUA GERBONG NAIK PROD + celah 5 Agustus ditutup

Jendela sepi terverifikasi sebelum menyentuh apa pun: **nol user aktif dalam 30
menit terakhir** (server 23:38 WIB Sabtu, aktivitas terakhir 22:17). Prod naik
dari `a49a0ef` → `0e309a8` → **`722d164`**.

### Gerbong 1 — Libur ikut cabang BERTUGAS · `0e309a8`

Backup 47 MB (`prod_pre_libur_cabang_20260822_1639.sql.gz`) → merge → deploy
penuh → SQL sapu rentang terbalik → `att:reconcile 2026-08-01 2026-08-31`.

**Rentang reconcile sengaja cuma Agustus, dan itu diputuskan setelah memeriksa,
bukan ditebak**: dari 9 baris `att_work_rule_exception` prod, **cuma 3 yang
per-cabang dan semuanya 25 Agu** — sisanya NASIONAL, jadi tak tersentuh
perbaikan ini. Ditambah **nol periode gaji tertutup**. Reconcile lebih lebar tak
ada gunanya dan cuma memperbesar permukaan risiko.

**Hasilnya: HANYA `libur` yang bergerak, 57 → 68.** `hadir` 261, `telat` 28,
`cuti` 13, `sakit` 3, `izin` 1 — tak bergeser sedikit pun. 10/10 uji lulus:

| Orang | Asal | Bertugas 25 Agu | Hasil |
|---|---|---|---|
| **Verini** | Sunter (buka) | **Cinere (tutup)** | **`libur`** — batal jadi alpa |
| **Della Raqisiah** | Cinere (tutup) | Sunter (buka) | hari kerja |
| **Ramdan** | Kalideres (tutup) | Pamulang (buka) | hari kerja |

SQL sapu menghapus **persis 2 baris** yang sudah didokumentasikan. **rsync
memindahkan 0 berkas** — mengonfirmasi branch ini memang tak menyentuh
`public/`, meralat catatan yang menyebut rsync wajib.

### Gerbong 2 — Branding GP (Reza) · `722d164`

Diaudit dulu, statis **dan** hidup di dev, sebelum naik. **Reza menghindari
jebakan langganan proyek ini**: rute barunya diberi prefix `konten-pillar/`
supaya mewarisi RBAC, lengkap dengan komentar. SQL-nya **terbukti idempoten**
(dijalankan 2× di dev: nol galat, kolom & index tak kembar, data utuh).

Naik prod: SQL lewat **mysql CLI** (ada `DELIMITER`) → deploy penuh → **rsync
memindahkan 1 berkas** (`theme-marketing.css` — kali ini memang perlu).
14/14 uji lulus: skema + index + prosedur sementara dibuang, **185 konten lama
utuh**, CSS di `public_html` md5-nya cocok, dan keempat layar render termasuk
tab yang tak dia sentuh.

**Tiga temuan, tak satu pun menahan** — untuk ditindaklanjuti Reza:
1. **Hapus dokter GP = hapus keras, nol jejak** (nol `AuditLog` di seluruh diff;
   dibuktikan: `audit_log` tak bertambah sesudah penghapusan).
2. **Dan itu satu-satunya cara mengeluarkan dokter** — skema punya `is_active` +
   `scopeActive()`, tapi cuma ada 3 rute (tambah/ubah/hapus). Jalur lembutnya
   ada di database tapi tak terjangkau. Ini yang paling layak diminta: satu
   tombol nonaktifkan menyelesaikan temuan 1 sekaligus.
3. **Kurasi tak dipaksakan di server** — `gp_doctor_id` cuma divalidasi
   `exists:raw_doctor,id`; dokter di luar daftar GP tetap diterima (dibuktikan).

Bukan temuan Reza tapi perlu dicatat: `calendar.blade.php` **memuat FontAwesome
6.5.1 dari cdnjs** — sudah ada di `main` sebelum push-nya. Halaman itu
bergantung host luar untuk menampilkan ikon, padahal stack resmi FA 5.8.2 lokal.

### Celah 5 Agustus ditutup — opsi C

`att_user_rule` Irma & Anisa berbunyi `FO-Asdent` **5 Agu → 4 Agu** (panjang
nol), dan `Fleksibel Operasional` baru mulai 6 Agu. **5 Agustus tak tertutup
aturan apa pun** → hari itu dilewati mesin. Anisa punya **scan 11:36 + lembur
120 menit yang SUDAH di-ACC** tanpa hari kerja di belakangnya.

Ditutup pakai **Fleksibel Operasional** (dimundurkan ke 5 Agu), bukan FO-Asdent
— menutup pakai aturan baku akan memberi **Irma alpa palsu** (nol scan, nol
pengajuan, Rabu = hari kerja), padahal sehari kemudian dia memang fleksibel dan
tak pernah dinilai alpa. Hasil: **Anisa `hadir` + lembur tercatat**, **Irma nol
alpa**, **alpa Agustus tetap 0**. Kemungkinan besar ini juga menutup pertanyaan
lama *"Irma 5 Agu"*.

### Demo Lembar Bayar ROP di dev

Data prod di-mirror ke dev untuk demo ke Della: 33 baris hidup, 30 lampiran +
5 bukti transfer, 12/12 metrik sama dengan prod. ⚠️ **Id user BERBEDA antara
prod dan dev** — `requester_id` punya FK ke `users`, jadi salinan mentah
**DITOLAK database**, bukan rusak diam-diam; impor wajib memetakan lewat email.
Perkakas siap pakai: **`bash ~/tools/reset-rop-demo.sh`** di server dev, aman
diulang.

⚠️ **Pelajaran**: menguji fitur yang MENGUBAH STATUS di atas data yang mau
dipakai demo itu salah urutan. Uji ujung-ke-ujung sempat menyetujui 6 pengajuan
dan meninggalkan 6 baris `rop_status_log`; `coa_no` dikembalikan tapi statusnya
terlupa — **Ekky yang menemukannya** lewat perbandingan tangkapan layar dev vs
prod, bukan aku.

### Keputusan terjadwal — Ramdan & Fajri

**Jendelanya: setelah operasional Minggu 23 Agu selesai.** Bukan lebih awal —
Minggu itu Ramdan masih mengisi checklist **Pamulang** dan Fajri masih mengisi
**Bintaro**.

**Urutan: Ramdan DULU, baru Fajri** — prinsipnya *isi dulu, baru kosongkan*.
Bintaro sekarang cuma punya Alpajri sebagai GS; kalau Fajri lebih dulu jadi
peninjau, Bintaro nol pengisi, dan kalau pemindahan Ramdan tersandung, lubang
itu jadi tak tentu ujungnya. Ramdan dulu = Bintaro sempat punya dua, lalu
tersisa satu. **Tak ada satu momen pun kosong.**

- **Ramdan**: pindah **PENUH** Kalideres → Bintaro (bukan rolling). Tiga baris
  data: `users.raw_branch_id`, posisi `home`, dan posisi `rolling` Pamulang
  **ditutup** (bukan dihapus). Nol kode. Riwayat 8 hari Pamulang-nya **tidak
  berubah** — `att_day.raw_branch_id` datang dari mesin scan, bukan data user.
- **Konsekuensi disepakati**: **Ramdan libur Selasa 25 Agu** ikut Bintaro yang
  tutup, dan **Pamulang sengaja dibiarkan kosong GS** sampai orang baru masuk.
- **Fajri**: menyusul di malam yang sama — **kalau** chat Konsultan sempat
  commit hari Minggu. Kalau tidak, Ramdan tetap jalan sendiri dan Fajri
  menyusul; tak ada lubang karena Alpajri tetap mengisi sampai dia beralih.

### ⛔ Yang MENAHAN gerbong berikutnya

Kerjaan **Konsultan + Alur Persetujuan masih belum di-commit** — cuma ada di
branch penyelamat `wip/snapshot-konsultan-alur-2026-08-22` (`06905c4`, 4.687
baris) yang isinya campuran dua chat.

**Keduanya tak bisa dipisah**: `AttendanceRequestWorkflow::__construct()`
sekarang menerima `AlurPersetujuanService` sebagai ketergantungan, dan kode
delegasinya memanggil konsep Konsultan (*"Delegat yang jabatannya PENGAMAT
dilewati"*). Konsultan tak bisa berangkat tanpa Alur.

⚠️ **Dan menjalankan SQL-nya saja BUKAN jalan pintas**: `canEditPettyCash()`
isinya `! isFinance()` — satu-satunya daftar hardcode yang MENUTUP pintu. Tanpa
kodenya, `Konsultan Finance` lolos sebagai "bukan Finance" lalu **bisa mengedit
petty cash** — kuasa yang Finance sendiri tak punya.

→ Chat pemiliknya yang harus commit; mereka tahu potongan mana miliknya.

---

## 2026-08-22a — Role Konsultan + restrukturisasi Supervisor (dev, 23/23 uji lulus)

Tiga role konsultan per bidang (**Konsultan Finance / Marketing / IT**) dan satu
role struktural baru (**Koordinator GS**) hidup di dev. Bagan pindah:
Coordinator Dentist dan Koordinator GS kini di bawah Supervisor; General
Services di bawah Koordinator GS. Rencana: `rencana/rencana-konsultan-struktur.md`.

**Prod belum.** SQL-nya idempoten dan sudah ditulis supaya jalan di dua
lingkungan, tapi belum dijalankan di sana.

### Wewenang ACC memang dipindah — dua tabel, bukan satu

Yang membuat restrukturisasi ini nyata bukan kotak di bagan, melainkan
`att_approval_rule`. Dibuktikan lewat `chainFor()` sungguhan di dev, bukan
dibaca dari tabel lalu disimpulkan:

| Pengaju | Sebelum | Sesudah |
|---|---|---|
| Assistant Dentist | Irma **atau** Anisa | **Irma saja** (baris Supervisor dicabut) |
| General Services | Anisa | **Fajri + Anisa** (sengaja dua, sampai Fajri terbukti) |
| Coordinator Dentist (Irma) | Dani | **Anisa** |
| Koordinator GS (Fajri) | — | **Anisa** |

Konsultan: **nol baris dua-duanya arah** — nol sebagai penyetuju (tak meng-ACC
siapa pun), nol sebagai pengaju (tak mengajukan cuti/lembur). Itu yang
menegakkan kebijakannya, bukan menu.

### Tiga temuan yang tak ada di rencana

**1. `isFinance()` itu MEMBATASI, bukan memberi — dan hampir terlewat.**
`canEditPettyCash()` = `! isFinance()`. Kalau `Konsultan Finance` tidak
didaftarkan di situ, ia lolos sebagai "bukan Finance" lalu boleh **mengedit
petty cash** — kuasa yang Finance sendiri tak punya. Menambahkan nama role ke
gerbang biasanya membuka pintu; di fungsi ini ia menutupnya, dan melewatkannya
= eskalasi hak diam-diam. Semua daftar hardcode lain memang menambah, yang satu
ini mengurangi.

**2. Memindahkan Fajri bukan cuma "menyembunyikan divisi" — ia MENUTUP baris
posisinya.** Rencana §9.1 menduga dia berisiko kehilangan divisi checklist.
Yang sebenarnya terjadi lebih keras: `checklistDivisionForGroup()` mencocokkan
**nama** role dengan nama divisi (kolom `sys_checklist_division.group_id` ada
tapi **tak pernah dibaca** — NULL di dev dan prod), dan begitu divisinya null,
`UserPosition::syncFromUser()` **aktif menutup** posisi `home`-nya
(`effective_to` = kemarin). Bukan pasif.
Yang **aman**: `cabangSesiUntuk()` cuma membaca posisi `kind='rolling'` lalu
jatuh balik ke `users.raw_branch_id` — jadi absensi dan cabang efektifnya tak
tersentuh.
Keputusan Ekky: **Fajri jadi PENINJAU, bukan pengisi.** Node `my-checklist`
dicabut dari role-nya, halaman pengawas checklist ditambahkan. Hasilnya
`checklistVisibleDivisionIds()` = `[General Services]` — uji #10 lulus, dan
tak ada orang yang meninjau divisinya sendiri.

**3. Id node menu BERBEDA antar lingkungan.** "Dashboard Marketing" di dev
`a2203f78`, di prod `a21e0d15`. Karena itu SQL-nya **tidak** memuat satu pun
daftar id node: aksesnya dikloning dengan **membaca baris role induk di
lingkungan itu sendiri**, jadi node yang cuma ada di salah satu sisi (Lembar
Gaji cuma dev; Data Center pada Head IT cuma prod) ikut dengan sendirinya.

### Konsultan IT: penuh, dikurangi alat keamanan

Keputusan Ekky "penuh kecuali yang sensitif". Head IT ternyata membawa lebih
dari yang disebut waktu bertanya — bukan cuma Audit Log dan WiFi Cabang, tapi
**Pusat Akun: User, Role, Akses, Setting**. Tak satu pun dijaga gerbang
nama-role, jadi node-nya saja sudah memberi kuasanya penuh: bisa membuat akun
dan memberi dirinya sendiri akses apa pun. Dicabut lima: `setusermanagement`,
`setgroup`, `setaccess`, `setsystemprofile`, `absensi/wifi`. **Kesehatan tetap**
(cuma route GET, papan diagnosa). Dicabut, bukan dibiarkan lalu 403 — menu
tampil-lalu-403 itu kegagalan senyap yang justru diburu detektor Pusat Akun.

Kalau Ekky menilai ini kelewat sempit, kembaliannya satu baris: hapus link-nya
dari daftar DELETE di langkah 3b.

### Fajri disetarakan dengan Anisa (koreksi Ekky 22 Agu, +13 uji)

Melihat bagannya, Ekky mengoreksi cakupan: Koordinator GS meninjau checklist
**setara Supervisor** — Assistant Dentist, Front Office, **dan** General
Services — bukan cuma GS. Dua dugaannya juga benar, dan dua-duanya memang belum
terjadi sebelum ini:

- **Cakupan 3 divisi** dikerjakan lewat `parent_id_2` ("atasan kedua, khusus
  supervisi checklist") pada Front Office & Assistant Dentist.
  `supervisedDescendants()` memang menelusuri `parent_id` **dan** `parent_id_2`
  — itu mekanisme yang disediakan, dan prod sendiri dulu memakainya begitu
  (`General Services.parent_id_2 = Assistant Dentist`). **Bagan tak berubah
  bentuk**: `_orgnode.blade.php` menggambar pohon dari `parent_id` saja dan
  menampilkan atasan kedua sebagai chip "Atasan lain", jadi Front Office tetap
  tergambar di bawah Supervisor — sesuai catatan Ekky bahwa **role Koordinator
  FO memang belum ada**.
- **Lintas cabang**: `raw_branch_id` Fajri dikosongkan. `resolveChecklistBranchId()`
  memakai cabang sesi lebih dulu, dan di layar review filternya `if ($branchId)`
  — jadi **NULL = tanpa filter = semua cabang**. Anisa, Irma, dan Robi semuanya
  sudah NULL; Fajri disamakan. **Absensinya tak terganggu**: cabang absensi
  datang dari mesin scan (`att_day.raw_branch_id` + `cabang_terlibat`), bukan
  dari `users.raw_branch_id` — buktinya Anisa & Irma sama-sama NULL dan tetap
  punya baris `att_day`; 12 baris `att_day` Fajri utuh.
- **Posisi checklist lamanya ditutup.** Kalau dibiarkan terbuka, Fajri ikut
  terhitung sebagai **staf GS di penyebut** dashboard review — dia meninjau
  dirinya sendiri. Ini yang `syncFromUser()` akan lakukan sendiri; dikerjakan
  di SQL supaya konsisten sekarang, bukan menunggu admin kebetulan menyunting
  akunnya.

**Pagar yang ikut diuji**: cakupan review **tidak** menular jadi wewenang ACC.
Pengajuan Front Office tetap ke **Anisa**, Assistant Dentist tetap ke **Irma** —
Fajri tak muncul di keduanya. `parent_id_2` memang tak dibaca `chainFor()`.

Uji: **13/13** (`Anisa` vs `Fajri` vs `Tito` staf GS, dibandingkan berdampingan
lewat helper sungguhan dengan sesi dipalsukan seperti saat login). Dua kegagalan
pertama ternyata **bug di ujinya sendiri**, bukan di sistem: `?? 'x'`
memperlakukan `null` sebagai "tak ada", jadi perbandingan `=== null` tak pernah
bisa benar.

### Pesan tolak konsultan dijujurkan (§10.1 lunas)

Dulu: *"Alur persetujuan untuk jabatan ini belum diatur… Hubungi admin"* —
terbaca seperti sistem rusak, dan mengirim orang mengejar admin untuk sesuatu
yang tak akan pernah diperbaiki. Sekarang kalimat kebijakan: *"Jabatan konsultan
tidak mengajukan cuti, lembur, atau izin lewat sistem ini. Kehadiran tetap
dicatat lewat absen seperti biasa."*

### Audit + bug yang ketemu DI KODEKU SENDIRI (22 Agu, +12 uji)

Audit subsistem role→akses→ACC dijalankan di **dev dan prod**, jadi bisa
dipisahkan mana bawaan lama dan mana yang baru.

**Bug nyata, sudah diperbaiki.** Kebijakan "konsultan tak mengajukan" kutaruh di
dalam `pastikanApexBukanSalahKonfigurasi()` — padahal `submit()` cuma memanggil
fungsi itu **kalau rantainya kosong**. Artinya kebijakannya cuma tegak selama
datanya kebetulan kosong: **satu baris** `att_approval_rule` untuk role
konsultan (sekarang gampang, ada layar Alur Persetujuan) membuat rantainya tak
kosong, pemeriksaannya tak pernah jalan, dan konsultan **bisa mengajukan**.
Kebijakan dan data jadi dua sumber kebenaran yang bisa berselisih diam-diam.
Diperbaiki jadi `pastikanBolehMengajukan()` yang dipanggil **tanpa syarat** di
awal `submit()`.

`act()` punya penyakit yang sama di arah sebaliknya — "tak meng-ACC siapa pun"
cuma dijaga data. Dipasangi pagar kembarannya. Konstantanya diganti nama jadi
`ROLE_TANPA_WEWENANG` (dulu `..._PENGAJUAN`) karena kini menjaga dua arah.
Diuji lewat **skenario sabotase**: baris aturan sengaja disisipkan untuk
konsultan, lalu dibuktikan pengajuan & ACC-nya tetap ditolak — 12/12, seluruhnya
di dalam transaksi yang di-rollback.

**Temuan lain (bawaan lama, BUKAN dari kerjaan ini):**

- **`Admin` bukan anggota `canSeeAllBranches()`** padahal punya node-nya →
  di prod 3 menu 403 (Upload Data, Antrian Pengajuan, Rekap Pengeluaran); di dev
  7. Daftar `$ADMIN` di controller lain memuat 'Admin', `canSeeAllBranches()`
  tidak. Inkonsistensi lama.
- **Supervisor / IT / Head IT → "Upload Data" 403** di prod. Sama sebabnya.
- **Pengajuan Executive lolos sendiri tanpa jejak** (apex sah, by design) —
  disebut di sini supaya tercatat sebagai permukaan risiko, bukan temuan baru.
- Prod bersih dari: rujukan role hantu, siklus, penyetuju kosong, user yatim.

Kerjaan ini menambah **satu** kombinasi 403 baru: Konsultan IT → "Upload Data",
warisan persis dari Head IT.

**Kloning diam-diam memberi konsultan node `pengajuan` (ROP)** — role
`Consultant` lama tidak punya itu, dan `PengajuanController::store()` tak
bergerbang nama-role, jadi node-nya saja sudah cukup untuk **mengajukan
pengeluaran uang**. **Keputusan Ekky 2026-08-22: konsultan tak mengajukan apa
pun, termasuk ROP.** Node-nya dicabut dari ketiga role, DAN dipasangi pagar kode
`PengajuanController::tolakPengamat()` di `index`/`create`/`store` — karena
mencabut menu saja itu persis pola rapuh yang baru saja menggigit di alur cuti:
satu klik "Set Access" mengembalikan menunya, dan kemampuannya ikut kembali
tanpa siapa pun sadar.

**`Admin` yang punya 3 menu 403 di prod: sengaja DIBIARKAN** (keputusan Ekky).
Auditor akan terus melaporkannya sebagai SEDANG sampai ada yang memutuskan.

### Standardisasi: "konsultan" jadi SIFAT, bukan nama (ACC Ekky 22 Agu)

Akar semua kerapuhan di atas satu: konsultan dikenali dari **nama**, jadi tiap
bidang baru menuntut perubahan kode di daftar yang tersebar — dan lupa satu =
kegagalan senyap, persis yang baru saja terjadi. Tiga hal dibangun.

**1. Kolom `group_path.wewenang_pengajuan`** (`penuh` / `pengamat`). Menemani
`landing_page`, `theme`, `att_default_rule_id` yang memang sudah menyimpan sifat
per-role di tabel yang sama — pola lama, bukan mekanisme baru. Kedua pagar
membacanya. **Konsultan bidang berikutnya = satu baris SQL, nol koding.**
Dibuktikan dua arah: menandai `General Services` sebagai pengamat langsung
memblokirnya (namanya sama sekali bukan konsultan), dan mengembalikan konsultan
ke `penuh` langsung melepas pagarnya. Jadi yang bekerja memang kolomnya.
Daftar nama lama tinggal jadi **parasut** untuk satu keadaan: kode sudah naik,
SQL belum — tanpa itu, urutan deploy terbalik akan menghilangkan pagarnya diam-diam.

**2. Daftar baku alat keamanan yang dicabut saat mengkloning** —
`setusermanagement`, `setgroup`, `setaccess`, `setsystemprofile`, `absensi/wifi`,
`auditlog`. Bukan sekadar dicatat: role pengamat yang memegang salah satunya
jadi temuan BERAT di auditor. Resep lengkapnya di rencana §11.

**3. `php artisan att:audit-role`** — detektor kegagalan senyap jadi perintah
tetap, bukan skrip sekali pakai. Yang sekali pakai menemukan enam menu 403 di
prod hari ini lalu hilang; yang ini akan menemukannya lagi bulan depan.
`--berat` untuk cron, dan exit code ≠ 0 kalau ada temuan berat.
Prinsip yang dijaga: **jangan menyalin daftar gerbang ke dalam auditor** —
salinan akan basi dan detektornya jadi sumber kebohongan baru. Karena itu
`canSeeAllBranches()` **dipanggil sungguhan** dengan sesi palsu per role, dan
konstanta controller dibaca lewat **refleksi**. Satu yang masih cermin
(`absensi/aturan`) ditandai jelas di keluarannya.

Bukti bahwa ia benar membaca kode nyata: dijalankan di dev yang **kodenya belum
di-patch**, ia melaporkan 15 menu Konsultan Finance/IT ber-403 — persis yang
hilang begitu patch kodenya naik.

Uji pagar: **14/14**, termasuk dua skenario sabotase dan dua uji kolom,
seluruhnya dalam transaksi yang di-rollback.

**Definisi "lolos sendiri" diperbaiki supaya auditornya layak buat cron.**
Versi pertama menandai pengajuan Executive sebagai BERAT — padahal itu memang
apex yang sah, jadi exit code-nya selalu ≠ 0 dan alarmnya jadi kebisingan yang
diabaikan. Pembedanya sekarang diambil dari STRUKTUR, bukan daftar nama: puncak
sungguhan **tak punya atasan di bagan**. Role yang PUNYA atasan tapi rantai
ACC-nya buntu = pengajuannya lolos tanpa jejak padahal ada orang di atasnya yang
seharusnya menyetujui — itu lubang §3.2, dan itu yang pantas membangunkan orang.
Hasil di dev sesudah patch: **berat 0, exit 0**.

Sifat "pengamat" dipindah ke helper bersama `isPengamat()`/`rolePengamatIds()`
di `Helpers.php` supaya alur cuti dan controller ROP memakai **satu** sumber,
bukan dua salinan yang bisa berselisih.

### Uji terima #1/#2/#4 LULUS HIDUP (11/11) — dan satu lulus-palsu yang nyaris lolos

Uji lewat HTTP sungguhan: login betulan, lalu **39 link menu** dibuka satu per
satu sebagai keempat role (Konsultan Finance 16 · Konsultan IT 10 · Konsultan
Marketing 8 · Koordinator GS 5).

| # | Hasil |
|---|---|
| #1 | keempatnya mendarat di `/akun` (layar absen) |
| #2 | ketiga konsultan tertolak dari `pengajuan` (ROP) |
| #4 | **nol 403 dan nol pentalan** di seluruh 39 menu |

**⚠️ Versi pertama uji ini LULUS 15/15 — dan itu bohong.** Rencana §8 sudah
memperingatkan bahwa `curl -L` bisa mengikuti pentalan ke halaman login lalu
melapor 200. Yang tak disebut di sana: **RBAC di aplikasi ini tidak menjawab
403, melainkan MEMANTULKAN (302) ke landing page role**. Jadi menghitung "nol
403" saja akan meluluskan halaman yang sebenarnya tak bisa dibuka sama sekali —
semuanya tampak "200 OK". Versi kedua membandingkan **URL akhir** dengan path
yang diminta, dan langsung menemukan bedanya.

Kejadian susulannya melengkapi pelajaran: versi ketat itu awalnya menandai
`report` GAGAL karena dialihkan ke `report/sales`. Diperiksa ke rutenya —
`/report` memang **sengaja** `redirect()->route('report.sales')`, ada komentar
"dipakai sidebar menu, link='report'". Jadi aturannya diperhalus: pengalihan
yang tetap di **segmen-1 yang sama** itu sah; yang dihitung gagal adalah 403
atau terlempar ke segmen lain. Ketat tanpa jadi rewel.

Dikerjakan tanpa menyentuh git: berkas ter-patch ditaruh sementara di dev lalu
dikembalikan (`git checkout`), akun uji `@uji.local` dibuat lalu dihapus. Dev
pulih utuh — branch tak bergeser, md5 `Helpers.php` & `AttendanceRequestWorkflow.php`
kembali ke aslinya. Dua berkas yang isinya bercampur dengan chat sebelah
(`AturanKerjaController`, `AttendanceRequestWorkflow`) diuji lewat varian
dev-baseline + suntinganku saja, karena versi lokalnya bergantung pada kelas
yang belum ada di dev.

### Yang BELUM & utang

- **Prod belum dijalankan.** Uji terima §8 kini LULUS SEMUA di dev
  (23 struktur + 13 Fajri + 14 pagar + 11 HTTP hidup). Naik prod =
  1 SQL (`2026_08_21_konsultan_struktur.sql`) + deploy kode + `access:rebuild`.
- **Akun konsultan di prod sudah ada** (Haickal, Hari, Rahmat) — SQL-nya
  memindahkan mereka lewat email. Di dev akun uji dibuat lalu dihapus lagi.
- ⚠️ **Perubahan kode masih UNSTAGED dan bercampur dengan pekerjaan chat lain.**
  Working copy lokal dipakai bersama; `AturanKerjaController.php` dan
  `AttendanceRequestWorkflow.php` berisi suntingan dua pihak sekaligus. Sengaja
  tak di-commit supaya pekerjaan chat sebelah tak ikut terbawa.
- ⚠️ **Gerbang kembar yang kini berbeda.** `AlurPersetujuanController::guard()`
  (chat sebelah) dan `AturanKerjaController::guard()` dulu identik dan
  komentarnya bilang begitu. Aku menambahkan `Konsultan Finance`/`Konsultan IT`
  ke yang kedua saja. `dataTab()` statis dan tak berpagar, jadi tab-nya tetap
  terbaca — tapi tombol simpannya 403. Siapa pun yang menggabungkan keduanya
  harus memutuskan sadar.
- **Head IT (dan karenanya Konsultan IT) punya menu "Upload Data" yang 403** —
  `/absensi` berpagar `canSeeAllBranches()` dan Head IT tak di dalamnya. Ini
  **sudah begitu sejak sebelum kerjaan ini**; diwarisi karena kloning menu.
  Node-nya **tak boleh** dicabut: `link='absensi'` adalah kunci RBAC untuk
  SELURUH `absensi/*` (AuthGuard mencocokkan `segment(1)`), jadi mencabutnya
  malah mematikan Cuti, Aturan Kerja, dan Lembar Gaji sekaligus.
- **Role `Consultant` lama sengaja tidak dihapus** sampai ketiganya terbukti
  pindah (§10.2).
- ⚠️ **Layar Aturan Kerja menentukan gaji**, dan Konsultan Finance/IT sekarang
  boleh membukanya (konsekuensi "Finance utuh"). Kalau itu kelewat jauh untuk
  orang luar: cabut dua nama dari `AturanKerjaController` **dan** buang node
  `absensi/aturan` dari group_access kedua role — dua-duanya, jangan satu saja.

---


## 2026-08-21d — Libur cabang ikut cabang BERTUGAS · DEV LULUS 11/11, prod menunggu

Perbaikan dari entri `2026-08-21c` dibangun dan diuji di dev. **Semua 11 uji
terima §7 lulus**, termasuk uji 1–3 dengan data prod yang diputar ulang.
Branch `fix/libur-cabang-bertugas` (`9da2d5d`). **Prod belum** — menunggu izin
Ekky. Tenggatnya Selasa 25 Agu; kalau lewat, rencana cadangan §8 tetap berlaku.

### Yang dikerjakan

**1. `CabangBertugas` — resolusi cabang bertugas, pramuat sekali.**
Kelas baru di `app/Services/`. `muat()` menarik seluruh baris `rolling` dalam
satu kueri; `pada()` menyelesaikan per tanggal **di memori**. Cabang bertugas
itu per-orang per-TANGGAL — memanggil `positionBranchOn()` di dalam loop harian
akan jadi jumlah-orang × jumlah-hari kueri.

Aturannya sengaja **sempit**: hanya `kind='rolling'` yang menang, `home`
**tidak** — alasan yang sama persis dengan `cabangSesiUntuk()`. Tombol reassign
di Posisi Checklist menulis baris `home` tanpa menyentuh `users.raw_branch_id`,
jadi kalau `home` ikut menang, supervisor checklist diam-diam bisa memindahkan
**hari libur** seseorang. Sejak libur menentukan gaji, itu wewenang yang tak
pernah diberikan ke peran itu.

**2. `resolveDay()` TAMBAH parameter, tidak menukar.**
`isDateLocked()` dan `hapusHariBasi()` tetap memakai cabang asal. Ini dibuktikan
uji 6, bukan sekadar diklaim — lihat bawah.

**3. Kalender libur akhirnya tampil di Kehadiran.**
Strip di atas matriks ("25 Agt · MAULID NABI · Bintaro · Cinere · Kalideres")
plus penanda per-sel **per-orang**. `hariLiburTerjadwal()` **tidak disentuh** —
pemisahannya disengaja (dipakai gerbang absen), jadi sumbernya terpisah.

**4. Rentang penempatan terbalik ditolak di titik simpan.**
`UserPosition::booted()` melempar kalau `effective_to < effective_from`.
Akar penyebabnya juga ditutup: `akhiriSekarang()` **menghapus** penempatan yang
belum berjalan sehari pun, bukan menyimpannya cacat. Empat titik penutupan
(`positionAssign`, `positionRemove`, `rollingRemove`, `syncFromUser`) semua
lewat situ sekarang.

### Uji terima §7 — 11/11 lulus

Uji 1–3 memakai data prod yang diputar ulang di dev (orang, penempatan, dan
kalender libur asli), bukan data karangan.

| # | Hasil |
|---|---|
| 1 | Verini → `libur`, tanpa form apa pun ✅ |
| 2 | Della → `hadir`, 09:48–18:12, telat 0 ✅ |
| 3 | Ramdan → `hadir`, 08:41–17:05 ✅ |
| 4 | Afrina (Bintaro, non-rolling) → tetap `libur` ✅ |
| 5 | Melani (Sunter) & Ahmad (Pamulang) → tetap hari kerja ✅ |
| 6 | Penguncian **tidak** ikut berpindah ✅ (lihat bawah) |
| 7 | Kueri **652 → 653** (+1 pramuat), 200ms → 235ms ✅ |
| 8 | Libur ditambah sesudah reconcile → alpa basi bersih ✅ |
| 9 | Kehadiran menandai 3 cabang tutup saja ✅ |
| 10 | Absensi Saya: Verini `LIBUR`, Della `HADIR` ✅ |
| 11 | Simpan rentang terbalik → ditolak ✅ |

**Uji 6 dibuat cermin supaya tak bisa lolos karena kebetulan.** Periode gaji
**Sunter** ditutup, lalu baris kedua orang sengaja dirusak jadi `izin`:
- **Verini** (asal Sunter, *bertugas* Cinere) → tetap `izin` = **beku**. Kunci
  ikut cabang asal.
- **Della** (asal Cinere, *bertugas* Sunter yang dikunci) → ditulis ulang jadi
  `hadir` = **tidak beku**. Kunci tidak ikut rolling.

Keduanya berlawanan arah, jadi hasil ini tak mungkin kebetulan.

**Bukti "sebelum" diambil pada data yang sama.** Dengan kode lama, Della &
Ramdan tercatat `libur` **dengan `scan_count = 2`** — masuk sehari penuh,
kerjanya dibuang. Untuk Verini, penjaga "alpa tak boleh dicap ke tanggal yang
belum lewat" menyembunyikan akibatnya di tanggal 25, jadi klaim ALPA-nya diuji
A/B di **tanggal lampau** (18 Agu): kode lama → **`alpa`**, kode baru →
**`libur`**. Terbukti, bukan diasumsikan.

### Dua hal yang berubah dari rencana — perlu diketahui

**a. Libur kalender tanpa scan sekarang MENGHASILKAN baris `libur`.**
Kode lama tak menulis baris sama sekali kalau hari tutup dan tak ada scan, jadi
perbaikannya cuma membuat Verini "tidak kena alpa" — layarnya tetap kosong, dan
kosong tak bisa dibedakan dari "belum ada datanya". Itu persis keadaan yang
membuat libur cabang tak pernah terlihat. Uji 1 & 10 memang meminta baris
`libur`, jadi baris itu ditulis. Libur **pola mingguan** sengaja tidak ikut —
Senin tutup sudah terbaca dari aturan kerjanya dan tersorot di matriks.

⚠️ **Efeknya**: kolom "Libur" di lembar gaji kini menghitung hari libur
kalender, bukan cuma "datang di hari tutup". Kolom informasi — tak ada yang
dihitung darinya, dan baris tanpa scan tetap terbedakan lewat `scan_count = 0`.

**b. Rentang terbalik di prod ada DUA, bukan satu.**
Selain baris Verini (`a2821d77…`), ada `a27346ed…` — baris `home` Bintaro
2026-08-08 → 08-07 milik user yang sudah tak ada. Lahir dari mekanisme yang
sama. Menguatkan bahwa ini cacat sistemik, bukan satu kecelakaan.

**c. Penjaga di model saja TIDAK cukup — dua jalur melewatinya.**
`UserManagement` menutup posisi lewat **mass-update query builder** (nonaktifkan
akun & hapus akun). Query builder tak membangkitkan event model, jadi penjaga
`UserPosition::booted()` **dilewati tanpa suara**. Terbukti berdampingan di dev:
pola baru → posisi dihapus, 0 terbalik; pola lama → `2026-08-22 → 2026-08-21`.
Jalur **hapus akun** itulah yang paling mungkin melahirkan baris yatim di (b) —
usernya ikut hilang, jadi tak ada lagi yang bisa menjelaskan barisnya. Sudah
ditutup. Pelajarannya umum: penjaga model hanya menjaga jalur Eloquent.

### ⚠️ Temuan sampingan yang BELUM ditambal (butuh keputusan Ekky)

`att_user_rule` prod punya **2 baris terbalik** dengan sebab yang sama: id 8
(**Irma**) & id 9 (**Anisa**), `FO-Asdent` `2026-08-05 → 2026-08-04`, lahir saat
keduanya dipindah ke `Fleksibel Operasional` per 6 Agu.

Akibatnya nyata: karena barisnya berpanjang nol dan aturan berikutnya baru mulai
6 Agu, **5 Agu keduanya tak punya aturan kerja sama sekali** → harinya dilewati
`resolveRule()`. **Anisa punya 1 scan pada 5 Agu yang tak pernah jadi hari
kerja.** Ini kemungkinan besar jawaban dari *"Irma 5 Agu masih tanda tanya"*.

Sengaja tidak ditambal bersama perbaikan libur: memperbaikinya mengubah siapa
yang dinilai pada 5 Agu dan bisa memunculkan alpa baru.

### Naik prod butuh apa

1. Deploy `fix/libur-cabang-bertugas` (ada class baru → deploy PENUH, bukan
   `--quick`; ada CSS blade → **rsync `public/`**, public_html prod itu salinan).
2. `database/sql/2026_08_21_sapu_rentang_penempatan_terbalik.sql` (2 baris).
3. `php artisan att:reconcile` untuk rentang yang memuat 25 Agu, **sesudah**
   deploy — kalau tidak, baris lamanya tetap salah.

Kalender libur 25 Agu di prod **sudah benar** (3 baris per-cabang, Sunter &
Pamulang memang buka) — jangan diubah jadi "Semua cabang".

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

## 2026-08-21 — Lembar Bayar ROP: main ditarik masuk, 41 uji hijau lagi

Branch `feature/rop-lembar-bayar` sempat **23 commit tertinggal** dari main —
main jalan terus (petty cash dompet-holder, rekonsiliasi Sunter/Cinere, WiFi
/48 yang sudah naik prod). Dibiarkan lebih lama, merge-nya cuma makin mahal
dan berisiko menarik mundur kerjaan orang lain.

Main ditarik masuk. **Tabrakan cuma satu berkas** (`docs/log-update.md`);
`routes/web.php` dan `status-proyek.md` menyatu otomatis, dan nol berkas kode
ROP yang bentrok. Sesudah merge: 483 rute terdaftar, semua blade kompilasi,
**41 uji ujung-ke-ujung hijau lagi**, dan layar milik orang lain tetap sehat
(petty cash · antrian & rekap ROP · Kehadiran · Pengajuan · Akun semuanya 200).

⚠️ **Ralat tanggal jurnal.** Tiga entri yang kutulis berlabel "14 Agu
(lanjutan 2/3/4)" sebenarnya kerja **20 Agu** (commit 15:25–17:08); yang
14 Agu cuma build awal + audit pertama. Sudah dibetulkan dan diurutkan ulang.
Jurnal yang salah tanggal menyesatkan pembacanya — apalagi file ini dibaca
alat lain di luar chat.

⚠️ **Temuan sampingan, bukan punyaku**: dev **belum menjalankan SQL petty cash**
dari main — `petty_cash_transaction.loan_id`, `petty_cash_period.opening_set_by`,
dan tabel `petty_cash_holder_wallet` semuanya belum ada di dev (di prod sudah).
Layar petty cash dev masih 200, tapi kalau ada yang mau menguji fitur itu di
dev, empat SQL `2026_08_18_*` & `2026_08_19_*` perlu dijalankan dulu.

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

## 2026-08-20 (17:08) — Lembar Bayar ROP: UJI PENUH ulang: 41 dari 41 lolos

Sapu statis + uji ujung-ke-ujung di dev dari keadaan bersih, 16 bab:
login & sesi · layar pilih · tiga jalur negatif · buat lembar 7 item ·
nomor & snapshot · tujuan transfer · lampiran · thumbnail · struktur form ·
bayar-banyak · kunci setelah dibayar · catatan kepanjangan · cap dikirim ·
sekuens nomor · gerbang · rute. **41 lolos, 0 gagal.**

Yang dibuktikan ulang, bukan diasumsikan:
- **3 ROP dibayar dengan SATU berkas bukti** — inti fiturnya; dicek sampai ke
  DB: ketiganya menunjuk berkas yang sama, dan masing-masing dapat **tepat
  satu** baris `rop_status_log` untuk lembar itu, plus notifikasi ke pengaju.
- Total Rp 17.516.682 & terbilangnya — angka yang sama persis dengan prod.
- Tujuan yang dilengkapi Finance tercetak, tujuan asli pengaju tetap utuh.
- Lampiran PDF tergabung; yang tanpa lampiran disebut; nol PDF gagal render.
- Struktur form diperiksa lewat `DOMDocument`: 7 form tujuan, **nol bersarang,
  nol medan nyasar**.
- Gerbang non-Finance **403 di ketujuh titik** dengan CSRF sah.

**Dua "kegagalan" pertama ternyata harapan ujiku yang salah, bukan bug:**
(a) nomor lembar tak mulai dari 001 karena uji pilihan-kembar di atasnya
**sah** memakai sekuens — setelah di-dedup, kiriman itu memang permintaan
satu item yang valid; skripnya kini menguji FORMAT + kenaikan tepat satu,
bukan angka mati. (b) hitungan baris log terlihat 3 karena log **append-only**
dan status sempat kureset berkali-kali tanpa menghapus jejak — justru bukti
jejaknya jujur. Setelah disaring per lembar: tepat 1 baris per ROP.

Pelajaran: **uji yang memancing bug palsu sama merepotkannya dengan bug
asli** — patokan uji harus ikut diaudit, bukan cuma kodenya.

---

## 2026-08-20 (16:56) — Lembar Bayar ROP: Thumbnail lampiran di layar isi lembar

Ekky **dua kali** mengharapkan foto muncul di layar Lembar Bayar. Itu bukan
salah paham yang perlu diluruskan — itu sinyal kebutuhan: sebelum lembar
dikirim ke bos, Finance perlu memastikan tiap baris punya invoice yang BENAR,
dan itu tak bisa dinilai dari kata "foto". Sebelumnya harus membuka tab satu
per satu.

Kolom Lampiran kini menampilkan gambar 62px. **PDF ikut bergambar** —
`RopPratinjauPdf` dipakai ulang, dan hasil rendernya **berkas yang sama**
dengan yang dipakai halaman cetak, jadi tidak ada kerja dobel; diberi tanda
"PDF" di pojok supaya jelas berkas aslinya bukan foto. `object-position: top`
dipilih karena bagian ATAS invoice (nama toko & nomor) yang paling menolong
saat dilihat sekilas. Ada berkas tapi gagal dirender → tautan "buka berkas",
bukan sel yang diam.

Terbukti di dev untuk ketiga keadaan: foto → thumbnail fotonya; PDF →
thumbnail halaman pertama + tanda PDF; tanpa lampiran → penanda "tak ada".
Struktur form tetap sehat (nol bersarang) dan halaman cetak tidak berubah.

⚠️ Sekalian ralat: satu lampiran di dev sempat kosong karena **aku sendiri**
yang menimpanya dengan PDF uji 7 halaman lalu mengosongkannya saat
bersih-bersih — dan itu kebetulan baris pertama lembar, jadi paling kelihatan.
Sudah dipulihkan; 10 dari 12 ROP dev berlampiran, nol berkas hilang.

---

## 2026-08-20 (15:25) — Lembar Bayar ROP: AUDIT KEDUA: 7 temuan, satu bikin tombol salah kirim

Audit ulang setelah fitur PDF-tergabung & lengkapi-tujuan ditambahkan. Yang
paling penting: **uji HTTP hijau tidak membuktikan UI-nya jalan.**

**1. Form bersarang — tombol mengirim form yang salah.** Form "lengkapi tujuan"
berada DI DALAM form bayar-banyak. HTML melarang form bersarang: parser browser
membuang tag form dalamnya, jadi tombol Simpan **mengirim form bayar-banyak**.
Uji HTTP-ku lolos karena menembak endpoint langsung dengan curl — form-nya
sendiri tak pernah teruji dari browser. Fix: form dipindah ke luar tabel,
medannya ditautkan lewat atribut `form=""` (HTML5).

**2. PDF berhalaman banyak berisiko salah render.** `mergeImageLayers()`
meratakan SELURUH urutan gambar, bukan frame yang sedang ditunjuk — tiap
halaman berisiko ditulis sebagai gabungan semua halaman. Diganti
`setIteratorIndex()` + `getImage()` yang tak ambigu. Uji PDF 1 halaman dulu
lolos justru karena cuma satu halaman.

**3. Pemotongan PDF senyap.** PDF lebih dari 5 halaman dipotong tanpa memberi
tahu siapa pun. Di dokumen pembayaran itu tak bisa diterima. Jumlah halaman
asli kini dicatat di berkas pendamping (supaya cache tidak menghilangkan
informasinya) dan sisanya disebut di lembar.

**4. `pluck()` mengambil yang salah.** `orderByDesc` + `pluck` menyisakan
lengkapan tujuan **paling lama**, karena kunci yang sama ditimpa oleh yang
dibaca terakhir. Kebalikan dari maksudnya. Diganti menaik.

**5.** Tujuan masih bisa diubah setelah item **dibayar** — uangnya sudah keluar;
kini dikunci per baris, di layar DAN di server.
**6.** Penuntun langkah berbohong: "Cetak jadi PDF" selalu tercentang padahal
sistem tak tahu apakah benar dicetak. Kini ikut status dikirim.
**7.** Regex "teks ini memuat nomor rekening" tersalin di model DAN view →
satu sumber `RopPaymentSheetItem::terlihatBernomor()`. Plus variabel mati
dibuang, relasi `pelengkap` ikut eager-load.

**SUDAH DIVERIFIKASI RUNTIME di dev** (sesudah branch petty cash selesai &
sudah masuk main, dev dikembalikan ke branch ini):
- **1** — HTML hasil render di-parse pakai `DOMDocument` (aturan parsing yang
  sama dengan browser): 3 form tujuan berdiri sendiri, **nol yang bersarang**,
  dan semua `form=""` menunjuk id yang benar-benar ada.
- **2 & 3** — PDF **7 halaman** diuji: melaporkan 7 asli, merender 5, memotong
  2; **md5 kelima halaman berbeda semua** (bug penggabungan memang hilang);
  saat cache dipakai ulang jumlah asli tetap terbaca 7; dan lembar cetak
  menyebut "Hanya 5 halaman pertama yang ditempel … (2 halaman)".
- **4** — lengkapan tujuan TERBARU yang di-prefill di lembar berikutnya.
- **5** — item yang sudah dibayar: form-nya hilang dari DOM **dan** POST
  langsungnya ditolak; yang belum dibayar tetap bisa diubah.
- **6** — langkah "Cetak jadi PDF" kini bertanda `1`+`is-aktif`, bukan centang.
- Gerbang non-Finance **403 di keenam endpoint**, termasuk `/tujuan` yang baru.
  Akun uji & berkas uji dibersihkan.

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

## 2026-08-14 (lanjutan) — AUDIT ULANG Lembar Bayar: 5 temuan, 2 di antaranya 500 sungguhan

Diminta menyisir ulang kode sendiri. **Uji fungsional hijau semua ternyata bukan
jaminan** — dua jalur yang tak pernah kulewati saat menguji ternyata berujung
Server Error, dan satu cacat cetak justru ditemukan Ekky dari cetakan sungguhan
di HP, bukan oleh 39 uji otomatis.

| # | Temuan | Akibat | Status |
|---|---|---|---|
| 1 | `pilih[]` berisi id kembar | **HTTP 500** | diperbaiki di akarnya |
| 2 | Catatan bayar 500 huruf | **HTTP 500** | diperbaiki + sabuk kedua |
| 3 | Nominal pecah dua baris di lembar cetak | dokumen ambigu | diperbaiki |
| 4 | `sys_profile` kosong → warning di halaman cetak | kotor | diperbaiki |
| 5 | Tautan penanda beda huruf & biru asing | drift visual | diperbaiki |

**1. Pilihan kembar → 500.** Dua id sama dalam satu kiriman menabrak
`UNIQUE(sheet_id, rop_id)`. Yang membuatnya jadi 500 dan bukan pesan sopan:
`QueryException` **bukan** `HttpException`, jadi lolos dari penangkap galat yang
sudah ada. `jumlah_item` dan `total` juga ikut menggelembung. Diperbaiki di
akarnya (`array_unique` sebelum apa pun disentuh), bukan dengan memperlebar
penangkap galat — karena yang salah datanya, bukan penanganannya. Terbukti:
kirim `15,15,9` → lembar berisi **2** baris, total & urutan tepat.

**2. Catatan 500 huruf → 500.** Catatan pembayaran masih ditempeli
`" (lembar BAYAR/2608/001)"` sebelum masuk `rop_status_log.catatan` yang
`varchar(500)` → *Data too long*, dan **seluruh pembayaran gagal**, bukan cuma
catatannya. Batas validasi turun ke 450 (menyamai `maxlength` di layar) + dipotong
`mb_substr` 500 sebagai sabuk kedua: kalau kelak sufiksnya berubah, yang terjadi
catatan terpotong — bukan uang gagal tercatat. Catatan log terpanjang kini 474.

**3. Nominal pecah dua baris** — "Rp" di satu baris, "2.100.000" di baris
berikutnya, TOTAL juga. **Ditemukan Ekky dari cetakan sungguhan**; uji otomatisku
memeriksa apakah angkanya ADA, bukan bagaimana ia JATUH. Pelajarannya: uji
berbasis string tak bisa menggantikan melihat. Kolom jumlah & nomor ROP kini
`white-space: nowrap` + lebarnya disetel.

**4 & 5.** `$profil->sysname` tanpa `?->` (tak konsisten dengan baris logo tepat
di atasnya) → warning kalau `sys_profile` kosong. Dan `a { font-family: Poppins }`
di `style.css` menang atas font warisan `.rop-fin`, jadi penanda lampiran yang
kebetulan berupa tautan beda huruf dari yang bukan — **sekeluarga dengan bug
"label Darurat jadi biru"** dulu: aturan tingkat-ELEMEN mengalahkan pewarisan.

**Juga:** `whereNumber` dipasang pada rute `lembar-bayar/{id}` (disamakan dengan
`rop/{id}`) → `/lembar-bayar/abc` kini 404 rapi. Label kartu "Belum dibayar
seluruhnya" yang membingungkan → "Layak masuk lembar".

**Sesudah perbaikan (dev, `bb3f6fb`):** kedua kasus yang tadinya 500 kini 302
dengan pesan Indonesia yang menjelaskan · catatan 450 huruf tetap berhasil ·
8 layar 200 · gerbang non-Finance **403 di keenam endpoint** (GET index/show/
cetak + POST store/bayar-banyak/dikirim) dengan CSRF sah. Akun uji dihapus.

### Finance bisa MELENGKAPI tujuan transfer — dan koreksi angka yang kusebut sebelumnya

⚠️ **Ralat.** Aku sempat menyebut *"6 dari 12 tujuan transfer prod tidak bisa
ditransfer"*. Itu **menyesatkan**: aku menghitung semua baris termasuk yang
**sudah dibayar** dan tak akan pernah masuk lembar lagi. Yang benar, dari **7
pengajuan yang betul-betul akan masuk lembar: 5 sudah bernomor rekening, 2
tidak** (`TRIYANI ( MAS ENDRIK)`, `Ekky`) — dan keduanya reimbursement internal.
Empat "polos" sisanya semuanya `ALIF IMAM FADILLAH` yang sudah lunas, yang
rekeningnya pun sebenarnya diketahui dari salah satu pengajuannya.

Ekky menunjuk hal yang benar: **nomor rekening sudah ditulis langsung di kolom
"dibayarkan kepada"** — praktiknya sudah jalan, jadi **tidak perlu kolom nomor
rekening baru di pengajuan**. Bukti terkuatnya: kolom `vendors.bank_nama`/
`bank_rekening`/`bank_atas_nama` sudah disiapkan sejak awal dan setelah 75
vendor tersinkron **terisi NOL**. Kolom yang disediakan tapi tak dipakai jadi
sampah.

Yang dibangun justru yang paling murah dan tepat sasaran: **Finance melengkapi
tujuan transfer DI LEMBAR**, untuk baris yang pengajunya menulis tanpa rekening.

**Kenapa di lembar, bukan di pengajuan** — ini yang menentukan benar-salahnya:
pengajuan **terkunci setelah dikirim** (aturan audit bersih yang sudah berlaku).
Menambal `pay_to_text` berarti mengubah tulisan pengaju dan membuat jejaknya
berbohong. Lembar bayar itu **dokumen Finance**, jadi melengkapinya di sana
adalah tindakan Finance atas dokumennya sendiri. Kolomnya per-ITEM, bukan
per-ROP, karena satu ROP bisa masuk beberapa lembar dan tiap lembar dokumen
tersendiri.

- `tujuan_transfer` + `tujuan_oleh` di `rop_payment_sheet_items`. NULL = pakai
  tulisan pengaju apa adanya; disimpan **hanya kalau beda**, jadi jelas baris
  mana yang disentuh Finance.
- `tujuanBerlaku()` / `tujuanBernomor()` / `tujuanDilengkapi()` di model =
  satu-satunya tempat aturannya ditulis; layar & halaman cetak lewat sana.
- Layar pilih: hanya baris **tanpa** nomor rekening yang dapat isian, ter-prefill
  dari lengkapan **lembar sebelumnya** — rekening yang sudah susah dicari tidak
  perlu diketik ulang tiap ROP itu masuk lembar baru.
- Masih bisa dibetulkan **selama lembar belum dicap dikirim**; sesudah itu
  dikunci (form hilang DAN POST ditolak — dua-duanya diuji). Alasannya:
  dokumennya sudah beredar di luar sistem, dan dua versi kebenaran lebih
  berbahaya daripada satu versi yang kurang rapi.

SQL `2026_08_14_rop_sheet_tujuan_transfer.sql` dijalankan **dua kali** di dev —
kolom tak berganda, FK tetap **tepat satu** (MariaDB tak punya
`ADD CONSTRAINT IF NOT EXISTS`, jadi dijaga lewat `information_schema`).

### Lampiran PDF akhirnya IKUT TERCETAK — "dikirim terpisah" dihapus

Ekky bertanya "apa ga bisa digabung ya?" saat melihat catatan *lampiran PDF
dikirim terpisah*. **Ternyata bisa** — dan rencana semula menyerah terlalu cepat.

Rencana §4.3 menetapkan PDF tak bisa ditempel di halaman cetak, jadi berkasnya
dikirim tersendiri. Itu menyisakan persis kerepotan yang mau dihapus fitur ini:
Finance tetap mengirim berkas kedua, bos tetap mencocokkan sendiri. Dicek ke
server: **Ghostscript (`/usr/bin/gs`) + ekstensi PHP Imagick dengan delegate PDF
ADA di dev DAN prod.** Jadi PDF dirender jadi gambar dan diperlakukan persis
seperti foto.

`RopPratinjauPdf` (baru): render 150 dpi, maksimal 5 halaman, hasilnya di-cache
di `public/uploads/rop/pratinjau` dengan kunci memuat `mtime` berkas asal —
lampiran yang diganti dirender ulang sendiri, cetak ulang tidak memanggil
Ghostscript lagi (terbukti: 0,86 dtk → 0,31 dtk).

⚠️ **Gagal render tidak menjatuhkan lembar.** Semua kegagalan (ekstensi hilang,
PDF rusak/terkunci, kebijakan Imagick memblokir) pulang sebagai array kosong dan
lembar kembali ke perilaku lama. Lembar selalu terbit.

Satu lampiran kini boleh jadi beberapa halaman, tiap halaman diberi penomoran
"lampiran halaman 1 dari 2" supaya tak ada yang terasa hilang. Kolom Lampiran
menandai PDF yang sudah tergabung sebagai "terlampir"; catatan "perlu dikirim
terpisah" tinggal jalan mundur yang jarang muncul.

Hasil di dev: lembar contoh naik dari 5 jadi **6 halaman**, semuanya dalam satu
berkas, penanda `PDF*` hilang sama sekali.

### Data uji seragam menciptakan bug palsu

Ekky mencetak lembar 7-item dan berkata *"jujur, gua ga nemu bukti-bukti dari si
pengaju, apa emang ga ada?"* — kecurigaan yang **beralasan**: di PDF-nya halaman
2–5 berisi **invoice yang identik**, semuanya bernominal Rp 12.852.000, padahal
ROP-nya Rp 113.392 / Rp 46.600 / Rp 279.000. Lembar itu memang tampak salah.

Sebabnya bukan fiturnya: **semua lampiran dev kuarahkan ke satu berkas contoh
yang sama.** Dicek ke prod sebagai pembanding — **10 lampiran, 10 berkas UNIK**
(dibandingkan lewat md5), 15–109 KB, nama UUID, 9 `.jpg` + 1 `.pdf`, nol yang
hilang dari disk. Bukti dari pengaju memang ada dan berbeda-beda per pengajuan.

Diperbaiki dengan membuat invoice tiruan **satu per ROP**, angka & uraiannya
cocok dengan ROP masing-masing. Foto asli prod **tidak disalin** ke dev: itu
dokumen usaha sungguhan (nota vendor, sebagian memuat nomor rekening), dan dev
lebih longgar.

**Pelajaran:** data uji yang seragam bukan cuma menyembunyikan bug — ia
**menciptakan bug palsu** dan membuang waktu orang yang memeriksanya.

### Salah paham yang kusebabkan sendiri

Gambar lampiran di dev bertuliskan "INVOICE UJI" dan Ekky mengiranya bagian dari
fitur. Itu berkas palsu buatanku untuk menguji. Sudah kuganti jadi tiruan invoice
seukuran foto asli (905×1280, sama dengan hasil kompresi `rop-foto.js`) — sekalian
menjawab keluhan "halaman lampirannya kosong melompong": itu karena gambar ujiku
cuma 600×360, bukan karena desainnya.

Catatan untuk Dela: saat "Save as PDF" dari HP, **Paper size-nya default Letter**.
Chrome HP mengabaikan `@page { size: A4 }`, jadi harus dipilih A4 di dialog.

### Potret data ROP prod (baca-saja) + data contoh dev disamakan bentuknya

Dicek langsung ke prod atas permintaan Ekky, **read-only**. Tiga hal yang
mengubah gambaran:

**a. `accurate_staging_glaccount` TERNYATA SUDAH ADA di prod — 1035 baris.**
Catatan lama (31 Juli) bilang tabel ini belum ada dan karena itu Finance tak
akan bisa meng-ACC di prod. **Itu sudah tidak benar.** Dropdown akun beban akan
terisi, jadi jalur "akun beban wajib" di lembar bayar aman dipakai di prod.
Catatan lama sudah dikoreksi supaya tidak dipakai menunda apa pun.

**b. Separuh tujuan transfer di prod TIDAK BISA DITRANSFER.** Dari 12 pengajuan,
**6 `pay_to_text`-nya polos — nama saja tanpa nomor rekening** ("ALIF IMAM
FADILLAH", "TRIYANI ( MAS ENDRIK)", "Ekky"). Artinya lembar bayar bisa sampai ke
bos dengan separuh baris yang tak bisa dieksekusi, dan dia bertanya balik —
persis kerja bolak-balik yang mau dihapus fitur ini. **Ini soal DATA, bukan
kode**; perlu disepakati dengan Dela apakah nomor rekening diwajibkan saat
mengajukan.

**c. Formatnya benar-benar tak seragam** — separator `;` · `//` · ` - ` · `( )` ·
spasi polos. Konfirmasi kuat bahwa `pay_to_text` **tak boleh dipecah** dengan
parsing; dicetak apa adanya memang satu-satunya cara yang benar.

Angka prod: **7 diajukan Rp 17.516.682 · 5 dibayar Rp 1.118.125 · 0 disetujui**
(membenarkan klaim rencana bahwa status `disetujui` tak pernah terpakai). Yang
sudah dibayar semuanya reimbursement FIN; yang besar (Rp 12.852.000 Marketing,
Rp 3.887.400 Bintaro) justru masih menunggu. `vendor_id` NULL di semua baris;
75 vendor, `bank_rekening` terisi **nol**. `rop_payment_sheets` belum ada di prod.

**Data contoh dev disamakan bentuknya**: jumlah, status, rentang nominal
(Rp 38.290–12.852.000), campuran 6 ber-nomor / 6 polos, dan 4 foto + 1 PDF +
2 tanpa lampiran — semuanya persis prod. **Nama & nomor rekeningnya KARANGAN**:
menyalin nama orang dan rekening asli ke dev tidak dilakukan dengan sengaja,
karena dev lebih longgar dan akun ujinya gampang dibuat. Lembar contoh
`BAYAR/2608/001` (7 item, Rp 17.516.682, 5 halaman) sudah dibuat untuk ditinjau.

### Masukan Dela (Finance) 15.46 — kolom "Unit" → "Departemen"

Diterapkan. Alasannya tepat: kolom itu bersebelahan dengan **Jumlah**, jadi
"Unit" terbaca sebagai satuan barang (pcs/box), padahal isinya cost centre.
Lebar kolom ikut dinaikkan supaya "Kantor Pusat" (12 huruf, cost centre
terpanjang) tidak terpotong.

⚠️ **Catatan istilah yang belum diputuskan:** lima dari sembilan cost centre itu
**klinik** (Bintaro, Sunter, Kalideres, Pamulang, Cinere), bukan departemen —
"Departemen: Bintaro" sedikit janggal secara harfiah walau jelas maksudnya.
Dan benda yang sama kini punya **tiga nama** di aplikasi: "Cost centre" (filter
antrian & rekap), "Dibebankan ke" (drawer ACC), "Departemen" (lembar cetak).
Perlu disatukan kapan-kapan — atau disepakati bahwa layar internal pakai istilah
Accurate dan dokumen untuk bos pakai bahasa awam.

**Yang TIDAK diperbaiki, sengaja — perlu keputusan Ekky:** membuat lembar
sekaligus **menyetujui** isinya, dan lembar **tidak bisa dihapus**. Jadi lembar
yang salah dibuat meninggalkan pengajuan dalam status `disetujui` tanpa jalan
mundur dari layar (tombol "Urungkan" di antrian cuma hidup 2 menit dan hanya
untuk yang menyetujui). Belum jadi masalah nyata, tapi ini utang yang sadar.

---

## 2026-08-14 — Lembar Bayar ROP dibangun & lolos uji di DEV (belum prod)

**Branch `feature/rop-lembar-bayar` (`14ca406`), live di dev.** Menjawab keluhan
nyata Finance: ROP dikirim ke bos **satu per satu** lewat WhatsApp — satu berkas
ROP + satu invoice per item, 15 pembayaran = 30 berkas. Sekarang jadi **satu
lembar cetak**: daftar, total, terbilang, dan lampirannya.

### Fiturnya SEPASANG — yang kedua yang menentukan

Bos sering membayar beberapa ROP dalam **satu transfer**, tapi `paid_proof_path`
itu per-ROP dan `bayar()` menerima satu id. Kalau cuma lembar cetaknya yang
dibangun, kerja Finance **cuma pindah tempat**: dari kirim 30 berkas jadi unggah
1 berkas 15 kali. Karena itu ada **"tandai dibayar sekaligus"** — centang
beberapa item, unggah SATU bukti, berkas disimpan sekali dan path yang sama
dipakai untuk tiap ROP lewat `RopService::tandaiDibayar()`, jadi transisi
status, jejak audit, dan notifikasi ke pengaju tetap utuh per pengajuan.

**Terbukti di dev:** ROP/MKT/2607/002 + ROP/BIN/2608/001 kini menunjuk berkas
bukti yang **sama persis** (`cfb4f665…png`), dengan baris `rop_status_log`
sendiri-sendiri.

### Yang dipakai ulang, bukan ditulis ulang

Mesin sekuens atomik `nomorBaru()` diangkat jadi `urutBaru()` dan dipakai
bersama `nomorLembarBayar()` — nomor lembar `BAYAR/2608/001` memakai
`rop_counter` dengan kunci semu `BAYAR`, jadi **tak ada SQL `LAST_INSERT_ID`
yang disalin** dan sifat atomiknya tak perlu dibuktikan dua kali. Lampiran lewat
`RopAttachment`, jejak lewat `RopStatusLog`, gerbang menyalin pola `guard()`
Finance yang sudah ada.

### Keputusan yang menempel (rencana §5)

**Membuat lembar = Finance menyetujui.** Status `disetujui` selama ini praktis
tak terpakai. Kini item yang masuk lembar naik ke `disetujui`, lalu
`bayar-banyak` menaikkannya ke `dibayar` — peta transisi dihormati tanpa
menyuruh Finance menekan tombol dua kali.

⚠️ **Satu keputusan yang TIDAK ada di rencana dan perlu diketahui:** `setujui()`
mewajibkan akun beban, sedangkan drawer antrian hanya menawarkan pilihan akun
untuk status `diajukan`. Kalau lembar menaikkan item ke `disetujui` tanpa akun,
item itu **tak akan pernah bisa dilengkapi lagi dari layar mana pun** dan rekap
Accurate-nya bolong selamanya. Karena itu layar lembar meminta akun beban per
baris — **dengan saran akun dari penerima yang sama sudah terpilih otomatis**,
jadi umumnya tinggal dilihat sekilas. Satu-satunya kelonggaran: kalau staging
Accurate belum ada di server itu, daftarnya memang kosong → lembar tetap boleh
dibuat, dan `RopService::lengkapiAkun()` yang menambalnya kelak.

### Tiga ranjau yang sengaja dijinakkan di depan

1. **Rute di bawah segmen `finance/`** (sudah di `AuthGuard::SKIP_RBAC`), dan
   dideklarasikan **di atas** wildcard `rop/{id}` — kalau di bawah, wildcard itu
   menelannya jadi `show('lembar-bayar')` → 404. Wildcard-nya sekalian diberi
   `whereNumber('id')` supaya lubangnya tertutup dari dua sisi.
2. **`pay_to_text` = NOMOR REKENING** (kolom bank `vendors` semuanya NULL,
   `vendor_id` selalu NULL). Dicetak apa adanya, tebal, boleh membungkus, tanpa
   `Str::limit` — terpotong = uang salah alamat. Diuji: `CAHAYA TIGA BINTANG
   ABADI` tercetak utuh.
3. **Halaman cetak berdiri sendiri**, tanpa `@extends('layouts.app')`. Gayanya
   inline (bukan dari `public/assets`) supaya dokumen yang harus tercetak benar
   tidak bergantung pada aset yang bisa 404, dan tanpa Google Fonts supaya tak
   menunggu jaringan. Ini pemakai `@media print` pertama di repo ini.

### Cara pembuktiannya (dev, HTTP sungguhan)

**38 dari 39 uji lolos**; satu-satunya gagal ternyata **alat ujinya**, bukan
fiturnya (`printf` merusak byte PNG di Git Bash; `curl.exe` tak bisa membaca
jalur MSYS `/tmp`) — diulang setelah diperbaiki, lolos.

- **SQL dijalankan DUA KALI** di dev → idempoten, struktur & FK sesuai rancangan.
- **Gerbang diuji lewat HTTP, bukan ditebak dari kode**, dan `curl -L` **tidak**
  dipercaya: yang diperiksa kode status mentah + `%{url_effective}`. Front Office
  dapat **403 di ketiga endpoint tulis** (buat lembar, bayar-banyak, dikirim)
  **dengan CSRF token yang SAH** — sempat 419, dan 419 itu menguji lapisan CSRF,
  bukan gerbang perannya. Uji pertama juga sempat "lolos" palsu karena login
  gagal diam-diam (rute login ternyata `/signin`, bukan `/login`).
- Nomor berurutan 001 → 002 → 003 tanpa tabrakan; total & terbilang benar
  (Rp 5.530.000 → "Lima juta lima ratus tiga puluh ribu rupiah"); snapshot
  nominal cocok; sidebar tidak ikut tercetak; ketiga keadaan lampiran benar
  (foto → halaman sendiri · PDF → ditandai `PDF*` + catatan dikirim terpisah ·
  tak ada → ditandai di kolomnya); pembayaran ganda ditolak dengan pesan yang
  menjelaskan; lembar tak ada → 404, bukan 500.
- Akun uji dihapus setelah selesai — dan itu **sekalian membuktikan FK
  `ON DELETE SET NULL`**: lembar tetap hidup dengan `dibuat_oleh` NULL, semua
  layar & halaman cetak tetap 200.

### Belum & perlu keputusan

- **PROD belum disentuh** (menunggu Ekky). Saat naik: jalankan
  `database/sql/2026_08_14_rop_payment_sheet.sql`, dan karena
  `public/assets/css/rop.sweettreats.css` ikut berubah → **WAJIB rsync**
  (public_html prod = salinan, bukan symlink).
- **Tidak dibuatkan node menu.** Jalan masuknya tombol "Lembar Bayar" di layar
  Antrian & Rekap ROP — layar yang memang dibuka Finance tiap hari. Node baru
  berarti satu permukaan RBAC lagi yang harus di-grant di tiap server. Kalau
  Ekky mau ada di sidebar, itu SQL access + `access:rebuild` menyusul.
- **Alamat klinik tidak tercetak** di kepala lembar: `sys_profile` cuma punya
  logo, `systitle`, `sysname` — tak ada kolom alamat, dan mengarang alamat di
  dokumen pembayaran bukan pilihan. Kalau perlu, itu tambahan kolom.
- Data uji tertinggal di dev: 3 lembar (BAYAR/2608/001–003) + beberapa ROP dev
  berubah status jadi `dibayar` + dua lampiran tiruan pada ROP #9 & #15.

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
