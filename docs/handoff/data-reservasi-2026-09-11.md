# Data Reservasi + Omnicare — keadaan PROD, 11 September 2026

**Semua angka diukur langsung di PROD** (HEAD `954ba6a`) oleh chat HUB, 10–11 Sep 2026.
Bukan dev, bukan ingatan, bukan dokumen lama.

> Berkas ini **menggantikan** `data-reservasi-cekat-2026-09-10.md` (dihapus).
> Versi lama memuat empat angka yang kemudian terbukti salah — lihat §9.

Chat build: pakai angka di sini apa adanya. Kalau butuh angka baru, minta HUB
mengukur ulang. **Patuhi §8 (batas bukti) — beberapa angka adalah batas atas,
bukan fakta.**

---

## 1. Ukuran & bentuk

| | |
|---|---|
| Reservasi seumur sistem | 6.382 |
| 90 hari terakhir | 5.621 |
| Pasien (`raw_customer`) | 15.702 · Kontak (`raw_contact`) 12.321 |
| Buku mendatang | 1.153 reservasi, sampai 2027 |

Per bulan (kolom `date`): Jun 1.243 · Jul 1.476 · **Agu 1.578** · Sep 1.206 (berjalan)
· Okt 419 · Nov 92 · Des 15.

Cabang (90 hari): Bintaro 1.678 · Sunter 1.565 · Cinere 857 · Kalideres 847 ·
Pamulang 674. Merata, tak ada cabang mati.

**Sejak Juni, reservasi ≈ kunjungan nyata** (Jul 1.259 vs 1.260; Agu 1.332 vs
1.330). Modul ini tulang punggung seluruh kegiatan klinik, bukan sistem sampingan.

**Klinik ini secara operasional adalah klinik ORTHO**: Kontrol Behel 3.365 dari
~4.500 reservasi lampau (≈75%).

## 2. Yang SEHAT — jangan diutak-atik

| pemeriksaan | hasil |
|---|---|
| Tindihan jadwal dokter dalam 5.621 reservasi (90 hr) | **0** |
| Reservasi punya baris perawatan | 5.583 / 5.621 (99,3%) |
| Reservasi jatuh pada hari dokter memang berjadwal | **98,5%** |
| Reservasi tanpa dokter · dokter nonaktif berjadwal · durasi >6 jam | 0 · 0 · 0 |

Bentengnya di lapisan yang benar: `slotConflict()` jalan **di server sebelum
insert**, memakai `resolveBookedRanges()` yang sama dengan penggambar slot.

**Mesin rekonsiliasi** `omnicare:reconcile` (cron 03:00): 5.174 baris,
cakupan **98,6%** dari sisi reservasi, mutu **95,9% `exact`**, jalan
**14 dari 14 hari tanpa bolong**, Rp **2,61 miliar** pendapatan nyata tertaut.
Artinya: kehadiran & pendapatan bisa dihitung **tanpa satu centang pun dari staf**.

Fondasi penjadwalan terpakai: 35 dokter aktif · 13 layanan · 148 sub-layanan ·
108 jadwal · 76 jadwal khusus · **323 baris durasi per-cabang**.

## 3. Kapasitas — kendala kita PERMINTAAN, bukan kursi

60 hari: **4.496 jam dokter tersedia, 1.062 jam terpesan → utilisasi 23,6%**
(25,9% kalau hanya menghitung dokter-cabang yang benar-benar praktik).
**48,7% hari-dokter berjadwal nol pesanan.**

Per cabang: Sunter 31,9% · Pamulang 26,7% · Cinere 21,3% · Bintaro 20,6% ·
Kalideres 18,2%.

⛔ **ANGKA KONKURENSI & RENTANG UTILISASI DI BAGIAN INI SUDAH DIRALAT — LIHAT §21.**
Yang lama (puncak 3, rata 1,0–1,6, "satu pasien pada satu waktu") dihitung dari
**slot yang dipesan** dan **kekecilan**. Yang benar dari cap waktu nyata:
puncak tertinggi **5** (Sunter), **hari biasa puncaknya 2**, dan pembandingnya
**3–5 dokter dijadwalkan serentak**.

⛔ Rentang "24%–37%, kemungkinan ~30%" juga **dicabut**. Dua metode independen
sepakat di **~20–24%** (slot 23,6% · menit pasien nyata 19,3%).

Kesimpulannya tak berubah — **kapasitas bukan kendala.** Konsekuensi: no-show
tidak membuang kursi, hanya uang; waiting list lemah sebagai alat kapasitas
(konversi 31%, 41 dari 132).

## 4. Kehadiran & peramalnya

Dari 4.474 reservasi yang tanggalnya sudah lewat (90 hr): `confirmed` 2.664 (60%)
· `cancelled` 605 · `completed` 584 (13%) · `pending` 502 · `waitlist` 119.
**Status `completed` tak bisa dipakai mengukur apa pun.**

**Peramal terkuat = jarak pemesanan** (kenyataan dari mesin, bukan centang):

| dipesan | reservasi | % datang |
|---|---|---|
| hari itu juga | 224 | **91,6%** |
| 1–3 hari | 2.125 | 80,5% |
| 4–7 hari | 538 | 73,2% |
| 8–30 hari | 1.510 | 69,6% |
| **>30 hari** | 764 | **62,6%** |

Rentang 29 poin. Yang dipesan >30 hari didominasi Sp.Ort (kontrol berkala):
drg. Nugroho 146 reservasi @52,4% · Komalawaty 123 @74,5% · Andy 87 @61,8% ·
Maharetta 78 @55,2%. **Buku mendatang 730 dari 1.153 (63%) adalah pesanan
jarak jauh** → ~270 berisiko tidak datang.

⛔ **Follow-up H-1 BUKAN tuas.** Tampak +17,8 poin (confirm 88,6% vs tak
dihubungi 70,8%), tapi perbandingan antar cabang membantahnya:
Bintaro menelepon **87,0%** → datang 76,4%; Pamulang menelepon **0,2%** →
datang 74,0%. **435× usaha untuk 2,4 poin.** Itu seleksi, bukan sebab.
(Ekky: isian H-1 tergantung rajin-tidaknya FO, jadi memang tak reliable.)

Nilai sebenarnya panggilan H-1 = **pendeteksian**, bukan pencegahan:
80% pembatalan tercatat di H-1 (345) atau hari-H (179).

Hari dalam pekan bukan tuas (71,6–76,4%). **Senin semua cabang tutup.**

## 5. ORTHO — retensi sudah punya bentuk terukur

Kohor pasien dipasang behel → apakah kontrol:

| dipasang | pasien | ≥1 kontrol | rata kontrol |
|---|---|---|---|
| Mei | 20 | 95,0% | 3,6 |
| Jun | 65 | **83,1%** | 2,2 |
| Jul | 85 | 85,9% | 1,7 |
| Agu | 70 | 74,3% | 1,0 |

Jeda antar Kontrol Behel (2.780 jeda): normal 3–5 minggu **46%** · <3 minggu 22%
· 5–8 minggu 19% · 2–4 bulan 12% → **31% datang lebih lambat dari jendela normal.**

**Corong Konsultasi → Pemasangan Behel: 592 pasien konsultasi, 194 lanjut
(32,8%).** Jeda tengah **14 hari** (rata 21, terlama 119). Dua dari tiga
konsultasi tidak berlanjut, dan tak ada apa pun yang mengisi jendela dua minggu itu.

Retensi umum sehat: dari 2.290 pasien yang pernah reservasi, **1.501 (66%)
datang ≥2 kali**.

## 6. Pertumbuhan — baru vs lama

| bulan | kunjungan | pasien | baru | lama | Rp baru | Rp lama | % baru |
|---|---|---|---|---|---|---|---|
| Jun | 1.259 | 1.054 | 186 | 868 | 469 jt | 903 jt | 34,2% |
| Jul | 1.260 | 1.097 | 165 | 932 | 373 jt | 965 jt | 27,9% |
| **Agu** | 1.331 | 1.163 | **268** | 895 | 470 jt | 884 jt | 34,7% |
| Sep | 519 | 486 | 119 | 367 | 145 jt | 319 jt | 31,2% |

**Sepertiga pendapatan dari pasien baru** → ketergantungan akuisisi tinggi.
⚠️ Agustus: pasien baru naik 62% (165→268) tapi Rp dari pasien baru hampir
datar → **nilai per pasien baru turun**: Jun 2,52 jt → Jul 2,26 jt → **Agu 1,76 jt**.
Bersambung dengan konversi konsultasi 32,8%.

Bruto Omnicare ~**Rp 1,35 miliar/bulan**. Diskon **cuma 0,2–0,7%** → kebocoran
harga BUKAN di diskon. Target 5 cabang Agustus: Rp 1.200.981.000.

## 7. Lubang logika di modul reservasi

1. **Kebenaran tinggal di tabel samping.** `omnicare:reconcile` menulis ke
   `reservation_omnicare_link`, **tak pernah balik** ke `raw_cus_reservation`.
   498 `pending` lampau tak pernah disentuh (`updated_at` NULL).
2. **Status tak punya siklus hidup.** Inline edit mengizinkan status apa pun ke
   status apa pun. Tak ada yang kedaluwarsa. `completed` hanya dari tangan manusia.
3. **Tiga kebenaran soal "apakah dia datang"**: `status`, `keterangan_kehadiran`
   (terisi 20% dan menurun), dan tabel rekonsiliasi. Tak ada yang mendamaikan.
4. **Tak ada alasan pembatalan.** 652 batal. `keterangan` terisi 305 tapi isinya
   catatan perawatan ("KONTROL METAL", "BENERIN KAWAT"), bukan alasan.
5. **Tak ada riwayat.** Hanya `updated_by` (penyunting terakhir). Reservasi
   digeser tiga kali tak meninggalkan jejak.
6. **Tak ada `created_by`.** Dan `audit_log` (6.456 baris) **nol baris
   reservasi** → 6.400 reservasi lama **tak akan pernah** bisa diatribusikan.
7. Kolom mati: `total_reservasi` terisi 3 dari 6.397 · promo 1 dari 5.621 ·
   `marketing_promo_id` 0.
8. Sisa kecil yang sudah berhenti sendiri: 5 baris `is_waiting` melenceng
   (Apr–Mei) · 26 baris durasi nol (Mei–Jun, 2 sejak Juli) · 1 baris tanpa tanggal.

## 8. ⛔ BATAS BUKTI — jangan dilanggar

- **Utilisasi ~20–24%** — dikuatkan DUA metode independen (slot reservasi 23,6%,
  menit pasien nyata 19,3%). ⛔ Rentang lama "24%–37%, kemungkinan ~30%"
  **DICABUT** (§21): ia bersandar pada asumsi banyak walk-in yang kemudian runtuh.
- **"Walk-in"**: kunjungan tanpa reservasi 33–42%, TAPI sudah dibedah (§9) —
  yang benar-benar tak pernah dibooking **paling banyak ~11%**.
- ⛔ **NO-SHOW BUKAN 25%. Yang benar 18,2%.** Angka 25–28% yang sempat dipakai
  **memuat 385 reservasi yang DIBATALKAN** dan ikut dicap `orphan_noshow`.
  Pembatalan bukan ketidakhadiran. Termasuk batal → 26,8%; tanpa batal → **18,2%**.
  Setelah penjodohan diperbaiki angkanya akan turun lagi (proyeksi ~14%).
- **Angka no-show klinik yang "sebenarnya" tetap tak boleh diklaim** kalau
  bersandar pada `keterangan_kehadiran` (terisi 20%). Pakai angka mesin.
- **Konversi konsultasi 32,8%** menghitung pasien, bukan kunjungan.
- **Tidak diukur sama sekali**: nilai uang per reservasi di sistem kita (tak ada
  kolom harga) · isi percakapan sebelum reservasi · siapa membuat booking.

## 9. Mesin penjodohan — cacat yang ditemukan 11 Sep

Kunjungan Omnicare tanpa tautan reservasi, **dibedah tuntas** (3 bulan terakhir,
1.513 kunjungan). Pembedahan pertama:

| sebab | porsi |
|---|---|
| Pasien tak tertaut ke sistem kita | **40,0%** (605) |
| **Pasien tertaut & ADA reservasi ≤7 hari → GAGAL JODOH** | **35,7%** (540) |
| Abu-abu (reservasi 8–60 hari) | 13,5% (205) |
| Benar-benar tak pernah dibooking | **10,8%** (163) |

Kantong 40% itu **dibedah lagi** (605 kunjungan) dan ternyata juga mayoritas
cacat kita sendiri:

| | |
|---|---|
| **Nomor HP-nya ADA di `raw_customer`** | **379 (62,6%)** ← penautan kita gagal |
| Nama persis cocok (HP tidak) | 103 (17,0%) ← kemungkinan cacat juga |
| Tak ada jejak HP maupun nama | **120 (19,8%)** ← memang cuma di Omnicare |
| `pid` belum ada di `omnicare_patient` | 3 (0,5%) ← sinkron pasien tertinggal |

Kantong abu-abu 205 juga **dibedah** (200 terukur): **121 (60%) datang lebih
AWAL dari bookingnya**, 79 lebih lambat; jarak 8–20 hr 84 · **21–35 hr 74**
(irama kontrol behel) · 36–60 hr 42. Yang menentukan adalah vonis pada
reservasi terdekatnya: **`link_date_branch` 122** — reservasi itu **sudah
terjodoh ke kunjungan LAIN**, jadi kunjungan ini **kunjungan TAMBAHAN yang
memang tak dibooking** · `orphan_noshow` 48 (reschedule tak tercatat, ambigu) ·
masalah tautan 29.

ℹ️ Pola "datang lebih awal, jarak 21–35 hari" = pasien ortho **datang di luar
jadwal** (kawat lepas / sakit). Hari ini sepenuhnya tak terlihat karena
kunjungan itu tak punya reservasi. Sinyal operasional yang layak dipakai ORTHO.

### ⭐ GAMBARAN AKHIR — dua pertiga masalahnya milik kita sendiri

| sebab sebenarnya | kunjungan | porsi celah |
|---|---|---|
| **Cacat data-linking KITA** (540 gagal jodoh + 482 gagal taut + 29) | **~1.051** | **69,5%** |
| **Benar-benar tak dibooking** (163 + 122 kunjungan tambahan) | **~285** | **18,8%** |
| Pasien memang cuma ada di Omnicare | 120 | 7,9% |
| Reschedule tak tercatat (ambigu) | ~48 | 3,2% |

⭐ **Angka yang paling layak dipakai di deck: kunjungan tanpa booking cuma
~7% dari SELURUH kunjungan klinik** (285 dari ~4.300 dalam 3 bulan).
⚠️ **Hati-hati menyebut "cakupan 93%".** Yang tepat, setelah perbaikan (§18):
**~89% kunjungan akan tertaut ke sebuah reservasi**, ~7% walk-in sejati,
~3% pasien yang hanya ada di Omnicare. Angka 93% = 100% dikurangi walk-in,
dan itu **bukan** hal yang sama dengan "tertaut".

⛔ **Karena itu ceritanya BUKAN "FO melewati sistem" dan BUKAN "walk-in".**
Yang benar-benar di luar sistem reservasi kita paling banyak **~19%** (120+163).
**Dua pertiga celahnya adalah penjodohan kita yang gagal** — bisa diperbaiki
sendiri, tanpa vendor, tanpa mengubah kebiasaan siapa pun.

### ⭐ AKAR MASALAH — Omnicare menerbitkan `p_id` PER CABANG

Awalan pid = cabang: `B-` 6.801 · `S-` 4.545 · `C-` 2.673 · `D-` 1.422 ·
`P-` 975 (+ sisa pid lama berupa angka, mis. `0000000021`).

**892 orang punya lebih dari satu pid** (nomor sama + nama sama) — total
**1.797 pid kembar**. Contoh nyata: `ISMU RAHARJO → B-2007-2758, B-2008-2793` ·
`NISA APRIATI → 0000000021, B-1910-2164`.

Dari 379 kasus "HP ada di sistem kita": **150 karena pasien kita SUDAH tertaut
ke pid LAIN** (kunjungan ini terjadi di bawah pid cabang lain) · 58 gagal skor ·
36 ditahan penjaga ambiguitas (nomor dipakai >1 pasien).

⛔ **Cacatnya ada di KODE, bukan skema**: `MatchOmnicarePatients` memakai
`$linkedCustomers = pluck('raw_customer_id')->flip()` lalu **melewati pasien yang
sudah tertaut** — memaksa **1 pid per pasien**, padahal kenyataannya **1 pasien
bisa punya N pid** (satu per cabang). Kunjungan di cabang kedua tak akan pernah
bisa terjodoh, selamanya.

**Perbaikan yang benar: izinkan banyak pid per `raw_customer`** (tabel
`customer_omnicare_link` sudah berbentuk baris-per-tautan, jadi skemanya
kemungkinan sudah sanggup — yang menahan adalah logika kodenya).

Dari 594 yang gagal jodoh: **469 di hari yang PERSIS SAMA**, dan cabangnya
cocok di 585 dari 594. Jadi bukan geser tanggal, bukan salah cabang.

Vonis yang tercatat pada reservasi itu:

| tercatat sebagai | jumlah | artinya |
|---|---|---|
| `no_customer_link` | **284** | divonis "pasien tak tertaut" — padahal kini sudah tertaut. **Vonis permanen, tak pernah ditinjau ulang** |
| `conflict_multi` | 139 | menunggu review manual yang layarnya tak pernah dibuka |
| `link_date_branch` | 137 | reservasi terjodoh ke encounter LAIN (2 kunjungan sehari) |
| `orphan_noshow` | **31** | salah dicap tidak datang padahal datang hari itu |

⭐ **Perbaikan termurah: jalankan ulang rekonsiliasi untuk baris bervonis
`no_customer_link` dan `conflict_multi`.** Bukan kode baru — rerun.

## 10. Identitas pasien — akar masalahnya

`raw_customer` **tidak punya kolom `p_id` Omnicare.** Identitas dibangun oleh
`omnicare:match-patients` dengan skor: **phone 30 + DOB 35 + nama 35**, auto-link
bila ≥60. ⚠️ Tautannya **LENGKET** — ditebak sekali lalu disimpan permanen; yang
jalan tiap malam hanya pencarian untuk pasien baru.

| | |
|---|---|
| Tautan tersimpan | 14.176 (90,3% dari semua pasien) |
| **Pasien AKTIF (reservasi 90 hr) tertaut** | **1.775 / 2.157 = 82,3%** |
| Pasien aktif **belum** tertaut | **382** (314 tanpa DOB) |
| `matched_by` = `system` | **14.176 — nol koreksi manusia, selamanya** |

Mutu tautan: **`phone+name` 7.729 (54,5%) — tanpa DOB sama sekali** ·
`phone+dob+name` 6.227 · fuzzy/sub/phone+dob 220.

⚠️ Kita memakai model 1-nomor-banyak-pasien (682 kontak punya 2–7 pasien).
Pada nomor berbagi itu, **276 tautan hanya `phone+name`** — ibu & anak di satu
nomor, dibedakan cuma nama.

⭐ **Peluang termurah**: Omnicare punya DOB untuk 15.906 dari 16.500 (96,4%);
kita cuma 8.802 dari 15.702 (56%). **5.786 pasien kita bisa diisi DOB-nya
sekarang** lewat tautan yang ada → cakupan 56% → ~93%. Satu SQL, nol kode.
Ini langsung membuka `find_patient_by_mobile_dob` (satu-satunya endpoint
pencarian pasien yang LIVE, dan ia **mewajibkan DOB**).

## 11. Integrasi Omnicare dua arah (tiket IT-2026-011)

**Alur nyata hari ini (dikonfirmasi Ekky): FO mengetik di sistem KITA dulu,
baru Omnicare.** → dorongan bisa dipasang **tanpa mengubah kebiasaan FO**.

**Arah yang disepakati**: identitas SATU ARAH dari Omnicare (`p_id`, `doc_id`,
`place_id`, `appt_id`), entri TETAP di sistem kita lalu didorong keluar.
Alasan: sistem kita punya durasi per-cabang, benteng anti-tabrakan (0 tindihan),
waiting list, prefill lead, pembatasan cabang — Omnicare tidak.

**Sudah siap**: `raw_branch.omnicare_place_id` **5/5** ·
`raw_doctor.omnicare_doc_id` **32/37** (format angka: 13683, 13692 — cocok
`doctor_list`) · klien API + sinkron harian + mesin rekonsiliasi sudah jalan.

**Penghalang terukur**:
1. **4 dokter AKTIF tanpa `omnicare_doc_id`**: drg. Rista (12 reservasi/90hr) ·
   drg Wiliam (7) · drg Fanisa (3) · drg. Retno (0). Tak ada yang punya jadwal
   mendatang → bisa dibereskan sebelum go-live.
2. DOB kita 55,8% (lihat §10 — bisa dinaikkan ke ~93% hari ini).
3. 382 pasien aktif belum tertaut → calon duplikat saat push pertama.

**5 PERTANYAAN KE VENDOR (Ekky akan tanyakan sebelum membangun):**
1. ⛔ **`set/insert_appt` tak punya kunci idempoten.** `update_doctor` punya
   `request_id`; `cancel_appt` "idempotent on replay"; `insert_appt` tak punya
   keduanya. Retry setelah timeout = janji temu ganda.
2. ⛔ **Model kapasitas berbeda.** Kita: nol tindihan per dokter (terbukti).
   Omnicare: **MULTI-BOOKING** ("slot stays listed while it still has capacity"),
   dan `insert_appt` bisa menolak **402 "Sesi dokter penuh"**.
3. ⛔ **`doc_id` tak konsisten di API vendor sendiri**: `all_medical_records`
   mengembalikan `"doc_id": "drg. Andi"` (NAMA), `commission` & `doctor_list`
   mengembalikan `"23372"` (ANGKA). **Selalu pakai angka dari `doctor_list`.**
4. Pembatasan laju 402 "Masa jeda" + banyak endpoint baca dibatasi 31 hari →
   backfill perlu throttle & bisa dilanjutkan.
5. ⛔ **TIDAK ADA endpoint BACA janji temu.** Dari 24 endpoint, appointment hanya
   `set/insert_appt`, `set/update_appt_by_id`, `set/cancel_appt` — semuanya TULIS.
   Akibatnya: hari ini kita tak bisa tahu kunjungan mana yang punya janji temu;
   dan setelah mendorong, **kita tak bisa membaca balik untuk memastikan.**
   Digabung dengan (1) itu pasangan berbahaya.

ℹ️ `set/cancel_appt` menerima **`cancel_reason` + `cancel_reason_note`** — persis
lubang §7.4. Mengadopsi model janji-temu Omnicare akan memaksa kita mencatatnya.

ℹ️ Spek vendor 10 Agu: 6 WRITE = pasien/janji/dokter. **TAK ADA tulis pembayaran.**
Dokumen: `docs/api/omnicare-open-api-2026-08-10.md`.

## 12. Rekam medis Omnicare — inventaris LENGKAP

`omnicare_medical_record`: **46 kolom**, 51.385 baris. Tingkat isi sejak 1 Juni
(3.686 rekam):

| kelompok | isi |
|---|---|
| Hampir 100% | `record_id` · cabang · tanggal · `time_in`/`time_out` · `p_id` · nama · `queue_number` · `doctor` · `doc_id` · **`booking_code` 99,9%** · **`procedures` 99,9%** |
| Demografi | **DOB 97,6%** · HP 99,4% · WA 99,3% · alamat 97,2% · email 89,2% · NIK 74,8% |
| ~~`prescription` 92,2%~~ | ⛔ **SALAH — lihat §24. Isi sungguhan 0,014%** |
| Klinis tipis | `treatment_plan_note` **41,3%** · `diagnose_note` 39,9% · `chief_complaint` 30,0% · `subjective` 28,4% · `objective` 22,2% · `diagnoses` **19,4%** · `plan_note` 4,0% |
| Kosong total | 5 kolom asuransi — semuanya 0% |
| Vital sign | 0,2–9% (klinik gigi, wajar) |

⚠️ Ralat catatan lama ("tindakan 100%, keluhan 27%, diagnosa 39%"): keluhan
**30,0%**, `diagnose_note` **39,9%**, tapi `diagnoses` terstruktur cuma **19,4%**.

**Halaman pasien sudah menarik SELURUH 46 kolom** (`CustomerController` baris
~372, `->get()`), tapi timeline Riwayat hanya merender **tiga**:
`chief_complaint`, `diagnose_note`/`diagnoses`, `procedures`.

**Yang layak ditambahkan (perubahan TAMPILAN saja — nol SQL, nol API):**
`prescription` (92,2%) → `treatment_plan_note` (41,3%) → `doctor` (100%) →
`subjective` (28,4%) → `objective` (22,2%).
**Jangan** tambahkan: 5 kolom asuransi (0%), vital sign (<9%), `plan_note` (4%).

⛔ Kolom `doctor`/`doc_id` di rekam medis berisi **NAMA**. Tampilkan saja,
**jangan dipakai men-join** ke `raw_doctor` — sudah pernah menyasarkan kita.

## 13. Jalur masuk reservasi

| pintu | keadaan |
|---|---|
| Admin CRUD `rawreservation` | jalur utama |
| Impor CSV/Sheets (staging → promote) | terpakai |
| Form publik `/reservasi` | Jun 32 · Jul 43 (puncak) · Agu 12 · **Sep 0** |
| Form `/pasien-baru/{slug}` (Cekat) | **4 lead**, semua 4–6 Sep, lalu berhenti |
| Open API Cekat `/apicekat/*` | **NOL** — 2 token 19 Mei, `last_used_at` NULL |

⛔ **MaxChat ≠ Cekat.** Isi `raw_contact` selama ini dari **MaxChat**
(4.538 sejak 18 Feb, masih menulis 10–30/hari). Cekat baru go-live 4 Sep.
Corong MaxChat: 4.538 kontak → ~403 pernah reservasi (**8,9%**); 4.041 tanpa
jejak apa pun di `raw_customer` (diuji cocok 9 digit terakhir nomor).

⚠️ Katalog modul melaporkan "Pasien Baru / Lead Cekat AI" `pakai_7hari=133`
padahal proxy-nya `raw_contact.created_at` = 110 maxchat + 23 admin + **4 landing**.
Modulnya terbaca "hidup" karena menunjuk tabel yang salah.

## 14. Proyeksi pendapatan — sudah diuji mundur

| bulan | terlihat di tgl 15 | total akhir | % terlihat | kunjungan nyata |
|---|---|---|---|---|
| Jun | 785 | 1.137 | 69,0% | 1.259 |
| Jul | 869 | 1.259 | 69,0% | 1.260 |
| Agu | 1.032 | 1.332 | 77,5% | 1.330 |

Polanya ada (Jun & Jul persis 69,0%), tapi **kalibrasi belum cukup**: memakai
rasio Jun–Jul di 15 Agustus memprediksi 1.496 vs kenyataan 1.332 — **meleset +12%**.
46–62% pesanan untuk tanggal 16–31 baru masuk **setelah** tanggal 15.

**5 peringatan desain:**
1. Harus memodelkan "pesanan yang belum masuk" (22–31% bulan), bukan mengalikan
   yang terlihat.
2. Pakai peluang hadir **per jarak-pesan** (62,6%–91,6%), bukan satu rata-rata —
   buku mendatang 63% berisi pesanan jarak jauh.
3. ⛔ Selesaikan dulu §9 — kalau kunjungan tak terjodoh dianggap "di luar sistem",
   proyeksi akan bias.
4. ⛔ **`raw_branch_target` masih cacat**: 1 cabang **YATIM** (`a16e7afb…`, nominal 0,
   tak cocok `raw_branch` mana pun) dan **collation berbeda** → JOIN biasa GAGAL,
   harus `CAST(... AS CHAR)`.
5. Rp/kunjungan beda jauh antar cabang (Kalideres 786 rb – Pamulang 1,09 jt) —
   satu angka rata akan menyesatkan per cabang.

## 15. Agenda yang sudah disepakati Ekky

1. **`created_by` + `cancel_reason` + riwayat perubahan** — SATU paket, satu
   perubahan tabel. ⚠️ Tak bisa mundur: `audit_log` nol baris reservasi.
2. **Rekam medis lebih lengkap di halaman pasien** (§12) — tampilan saja.
3. **Realisasi harga per perawatan** — `expected_revenue` kosong (0 dari 3.048),
   tapi Omnicare punya `gross_price`/`discount`/`base_price`/`net_profit` per baris.
   Bisa dihitung hari ini tanpa API baru.
4. **Tagihan tertunggak** — `get/unpaid_invoices` LIVE di vendor; di data kita
   sudah terlihat 8 kunjungan `unpaid` senilai Rp 7,14 juta, tak ada yang melihat.
5. *(diusulkan HUB, menunggu Ekky)* **Backfill DOB 5.786 pasien** dari Omnicare.
6. *(diusulkan HUB)* **Rerun rekonsiliasi** untuk vonis `no_customer_link` +
   `conflict_multi` (§9).
7. *(diusulkan HUB)* **Buka akses `/reconcile`** — 1 dari 31 grup, dan grup itu
   **0 pengguna**. 1.061 no-show + 108 konflik menganggur sejak Juni.

## 16. HARGA — apa yang boleh dan tidak boleh dihitung

⛔ **MARGIN TIDAK BISA DIHITUNG.** `net_profit` di `omnicare_sales_detail`
**sama persis** dengan `net_sell_price` karena `base_price` dan `usage_cost`
kosong. Kita hanya bisa bicara **pendapatan**. Siapa pun yang bertanya "berapa
untungnya" harus dijawab: **datanya tidak ada.** (Ini juga jawaban setengah
untuk tiket COGS IT-2026-028.)

### Harga dasar (3 bulan, prod)

| | |
|---|---|
| Paket behel | **Rp 4,32 juta** rata-rata (174 terjual, total Rp 752 juta) |
| Kontrol behel reguler / spesialis | Rp 349.840 / Rp 447.538 |
| Nilai seumur pasien ortho | **Rp 6,82 juta** (2.926 pasien, di luar kontrol & voucher) |
| Pendapatan rata per kunjungan | Rp 1,03 juta |
| Service charge | Rp 50.170 |

### ⭐ NO-SHOW — jawaban yang benar (DIUKUR, bukan aritmetika)

627 reservasi bercap `orphan_noshow` (Jun–11 Agu), dilacak apakah pasiennya
muncul lagi:

| | |
|---|---|
| Kembali ≤14 hari | **198 (31,6%)** |
| Kembali 15–30 hari | 94 (15,0%) |
| Kembali 31–60 hari | 79 (12,6%) |
| **TIDAK kembali dalam 60 hari** | **256 (40,8%)** |

⛔ **JANGAN menghitung kerugian no-show sebagai 25% × pendapatan.** Dua sebab:
**(a) angkanya bukan 25% melainkan 18,2%** (lihat §18), dan **(b) 59,2% yang
"tidak datang" KEMBALI** — itu pendapatan **tertunda**, bukan hilang. Dan karena
jadwal ~70% kosong, mereka tidak merebut kursi siapa pun.

Kalimat yang sah untuk ruangan:
> "No-show kami 18%, dan 59% dari mereka kembali dalam 60 hari. Yang benar-benar
> lepas sekitar **7% dari seluruh reservasi**."

Nilai, dengan rentang jujur: ~135 kunjungan lepas/bulan × Rp 350 ribu (harga
kontrol; mayoritas no-show memang kontrol) s/d Rp 1,03 juta (rata semua
kunjungan) = **Rp 47–139 juta/bulan**, kebenarannya dekat ujung bawah.
⚠️ "Tidak kembali dalam 60 hari" **BUKAN** "hilang selamanya".

### Yang boleh dihargai

1. **Corong konsultasi**: 398 dari 592 konsultasi tak berlanjut × Rp 4,32 juta =
   **Rp 1,72 miliar paket yang tidak terjadi.** ⚠️ **JANGAN sajikan sebagai
   "kerugian"** — sebagian pasien bukan kandidat atau tidak mampu. Yang sah:
   **tiap 5 poin kenaikan konversi ≈ Rp 26 juta/bulan.**
2. **Retensi ortho**: 17% kohor Juni nol kontrol setelah 3 bulan. Ortho butuh
   ~18–24 kontrol; berhenti setelah pemasangan = **~Rp 7 juta kontrol tak
   tertagih** per pasien. ~12 pasien/bulan → **~Rp 86 juta/bulan pendapatan masa
   depan berisiko.** ⚠️ Paling lemah dari ketiganya: sebagian mungkin cuma
   terlambat, dan pendapatannya mengalir selama dua tahun.
3. **Kapasitas menganggur = temuan PENGHEMATAN, bukan peluang pendapatan.**
   Karena kendalanya permintaan: **jangan investasi di kursi, dokter, atau jam
   buka.** Alihkan ke akuisisi & retensi.

### ⛔ Yang TIDAK boleh dihargai

- **4.041 percakapan MaxChat yang menguap** — komposisinya tak diketahui
  (tanya jam buka? salah sambung? pasien lama?). Menaksirnya = angka karangan.
- **Cacat penjodohan** — itu risiko keputusan salah, bukan rupiah.
- **Apa pun yang menyebut margin/laba.** Lihat peringatan di kepala bagian ini.

## 17. Tiket terkait di IT Tracker PROD

IT-2026-011 (L5, ANTRE) Omnicare dua arah · IT-2026-023 (M3, ANTRE) notifikasi WA
reservasi · IT-2026-013 (M5, ANALISIS) ORTHO · IT-2026-033 (M3, GOLIVE) Pasien
Baru/Lead · IT-2026-025 (S1, DEV) modal detail pasien.

Modul `Notifikasi` (id 35) status *rencana*: **"Hari ini TAK ADA satu pun
pemberitahuan keluar aplikasi."** Itu sebab langsung §7.1–7.3.

⚠️ Perlu dipastikan: tiket ORTHO IT-2026-013 terdaftar di bawah modul **COGS**,
padahal modul "ORTHO — tracking & retensi" (id 7) ada sendiri.

ℹ️ **CRM Cekat belum jadi tiket** — keputusan Ekky 11 Sep: Cekat sendiri belum
siap masuk fase improvement. Jangan dibuatkan tiket dulu.

---

## Lampiran: klaim HUB yang DIRALAT (jangan dipakai lagi)

| klaim | ralat |
|---|---|
| "Cekat nol lalu lintas = fitur gagal" | Cekat baru go-live 4 Sep; yang nol adalah **API**-nya, dan jalur nyatanya bukan API |
| "Kita tak tahu siapa yang benar-benar datang" | Tahu — mesin rekonsiliasi sejak 2 Juni, 98,6% cakupan |
| "Follow-up H-1 tuas terbukti +17,8 poin" | Dibantah perbandingan cabang: 435× usaha → 2,4 poin |
| "Sistem menebak ulang identitas tiap malam" | Tautan **lengket**; tebakan sekali lalu disimpan |
| "63% pasien hidup di Omnicare saja" | **~40%**, dan 35,7% ternyata kegagalan penjodohan |
| "~37% kunjungan adalah walk-in" | Paling banyak **~11%** yang benar-benar tak dibooking |
| "Utilisasi sebenarnya ~37%" | **~30%**, rentang 24–37% |
| "p_id ke `raw_customer` mendesak" | Tidak — tautannya sudah tersimpan permanen; itu denormalisasi |

---

## 18. NERACA — angka yang berjodoh satu sama lain

Diukur **12 Jun – 10 Sep 2026**. Bagian ini ada supaya deck tidak menyajikan
angka yang saling bertentangan.

```
RESERVASI 3.889 (non-batal)          KUNJUNGAN OMNICARE 3.878
  terbukti hadir      2.519  64,8%     tertaut reservasi  2.460  63,4%
  dicap tidak hadir     561  18,2%     TIDAK tertaut      1.418  36,6%
  TAK BISA DINILAI      809  20,8%
  (dibatalkan 609, dihitung terpisah)
```

### ⛔ Koreksi no-show — ini yang ketiga kalinya

| | |
|---|---|
| Termasuk reservasi yang dibatalkan | hadir 2.584 · tidak 946 → **26,8%** |
| **Tanpa yang dibatalkan** | hadir 2.519 · tidak 561 → **18,2%** |

**385 reservasi ber-`status='cancelled'` ikut divonis `orphan_noshow`.**
Pembatalan bukan ketidakhadiran — pasiennya memberi tahu. Angka 25–28% yang
sempat dipakai berhari-hari memuat mereka. **Pakai 18,2%.**

### Kenapa 36,6% "tanpa reservasi" tidak bertentangan dengan walk-in ~7%

1.418 kunjungan tak tertaut terdiri dari: **cacat penjodohan ~986 (69,5%)** ·
**walk-in sejati ~267 (6,9% dari seluruh kunjungan)** · pasien cuma di Omnicare
~112 (2,9%) · ambigu ~53. Angka 36,6% itu **mentah**, bukan walk-in.

### Proyeksi setelah penjodohan diperbaiki — ⛔ JANGAN tulis sebagai terukur

| | |
|---|---|
| Kunjungan tertaut reservasi | ~89% |
| Walk-in sejati | ~7% |
| Pasien cuma ada di Omnicare | ~3% |
| No-show | ~14% |

---

## 19. Untuk chat deck — angka mana yang akan bergeser

Deck boleh disusun **sebelum** perbaikan penjodohan naik. Strukturnya tidak akan
berubah. Tapi pisahkan dua jenis angka:

### ✅ TIDAK akan bergeser — aman dibakukan sekarang

Volume & sebaran cabang · **kapasitas ~70% menganggur & puncak 1–3 pasien
bersamaan** · distribusi jarak pemesanan · bauran perawatan (75% Kontrol Behel) ·
kohor ortho & jeda kontrol · **konversi Konsultasi→Behel 32,8%** · retensi 66% ·
pertumbuhan baru vs lama · seluruh harga · **margin tak bisa dihitung** ·
59,2% no-show kembali dalam 60 hari.

### ⚠️ AKAN bergeser — tandai slide-nya, segarkan sebelum presentasi

Apa pun yang berbunyi **"% datang" · "no-show" · "tertaut" · "cakupan" ·
"Rp tertaut"** — termasuk tabel % datang **per cabang**, **per dokter**, dan
**per jarak pemesanan**, serta Rp 2,61 miliar yang tertaut.

**Semuanya bergeser ke arah yang lebih baik** (kehadiran naik, no-show turun,
cakupan naik). Jadi tak akan ada klaim yang harus ditarik ke sesuatu yang lebih
buruk.

⛔ **Jangan tulis angka proyeksi (~14%, ~89%) seolah sudah terukur.** Tulis angka
hari ini, lalu minta HUB mengukur ulang seluruhnya dalam satu putaran setelah
perbaikan naik.

### ⛔ Tiga versi lama yang HARUS dibuang dari kedua deck

1. "Sepertiga kunjungan adalah walk-in" — salah, ~7%.
2. "FO melewati sistem / tidak input ke Data Center" — salah, dan **menuduh
   lima tim FO untuk sesuatu yang ternyata cacat kode kita sendiri.**
3. "No-show 25%" — salah, memuat pembatalan. Yang benar 18,2%.

Ketiganya terdengar sangat meyakinkan. Itu yang membuatnya berbahaya.

---

## 20. Dua tabel yang sempat tertinggal

Dibahas di HUB tapi belum masuk berkas sampai 11 Sep sore. Keduanya berguna
untuk deck operasional.

### Bentuk hari — kapan pasien datang (90 hari, jam mulai reservasi)

| jam | reservasi | | jam | reservasi |
|---|---|---|---|---|
| 09 | 55 | | 15 | 453 |
| **10** | **593** | | **16** | **827** ← puncak |
| **11** | 566 | | **17** | 711 |
| 12 | 77 ← makan siang | | 18 | 488 |
| **13** | 609 | | **19** | 531 |
| 14 | 479 | | 20–21 | 121 |

**Dua puncak: pagi 10–11 (1.159) dan sore 16–19 (2.557).** Sore lebih dari dua
kali lipat pagi. Lembah jelas di jam 12. Berguna untuk keputusan penempatan staf
dan jam praktik — dan konsisten dengan kepadatan rata **6 pasien per dokter per
hari-buka** (tersibuk 32).

### Sebaran antar dokter — % datang (reservasi lampau, dari mesin)

| dokter | reservasi | % datang |
|---|---|---|
| drg. Anindito (Dito) | 339 | **78,7%** |
| drg. Rachmad (Ryan) | 574 | 78,4% |
| drg. Rosmailah | 293 | 78,6% |
| drg. Komalawaty, Sp. Ort | 652 | 75,5% |
| drg. Nugroho, Sp. Ort | 519 | 73,2% |
| drg. Muhammad (Fridhi) | 372 | 72,5% |
| drg. Stiefany Vega | 239 | 71,2% |
| drg. Yasnelly, Sp. Ort | 381 | 70,0% |
| drg. Andy, Sp.Ort | 373 | 68,8% |
| drg Yusmaini, Sp Ort | 215 | **66,2%** |

Rentang **12 poin**. ⚠️ Sebagian besar kemungkinan **bauran kasus**, bukan mutu
dokter — Sp.Ort menangani kontrol berkala yang dipesan jauh hari, dan jarak
pemesanan itulah peramal sebenarnya (§4). Yang layak ditanyakan: drg. Nugroho
73,2% keseluruhan tapi **jatuh ke 52,4% pada pesanan >30 hari**.

⛔ **Jangan sajikan tabel ini sebagai peringkat kinerja dokter.** Ia mengukur
perilaku pasien, bukan pekerjaan dokter — dan tabel ini **ikut bergeser**
setelah perbaikan penjodohan (§19).

---

## 21. ⭐ KAPASITAS — DIUKUR ULANG 11 Sep dgn sumber INDEPENDEN

Dipicu keberatan rekan Ekky: *"3 pasien bareng itu maksudnya apa? padahal dokter
cuma 1?"* Keberatan itu benar, dan **angka lama salah karena KEKECILAN.**

⛔ **Angka §3 yang lama (puncak 3, rata 1,0–1,6) dihitung dari SLOT YANG DIPESAN.**
Itu melewatkan walk-in dan kunjungan yang molor dari slotnya. **Pakai angka di
bawah ini.**

### Konkurensi NYATA — dari `time_in`/`time_out` Omnicare

Cap waktu Omnicare ternyata layak pakai: 1.430 rekam 16–45 menit · 1.383 rekam
1–15 menit · 431 rekam 46–120 menit · hanya 290 di bawah 1 menit.

| cabang | puncak tertinggi (3 bln) | rata puncak harian | **dokter dijadwalkan serentak** |
|---|---|---|---|
| Sunter | **5** | 2,7 | 4 |
| Bintaro | **4** | 2,5 | **5** |
| Kalideres | 4 | 1,9 | 3 |
| Pamulang | 3 | 1,7 | 3 |
| Cinere | 3 | 1,6 | 4 |

Sebaran puncak harian (336 hari-cabang): 1 pasien **26,2%** ·
**2 pasien 47,0%** · 3 pasien 18,2% · 4 pasien 7,4% · 5 pasien 1,2%.

### Utilisasi — DUA metode independen sepakat

| metode | utilisasi |
|---|---|
| Dari slot reservasi (60 hari) | 23,6% |
| **Dari menit pasien NYATA (12 Jun–11 Sep)** | **19,3%** |

80.225 menit pasien nyata (3.256 kunjungan, rata **24,6 menit**) dari
**6.914 jam** dokter tersedia. Dikoreksi untuk rekam tanpa cap waktu: **≈23%**.

⛔ **RALAT ATAS RALAT:** HUB sempat menaikkan utilisasi ke "~30%, mungkin 37%"
dengan alasan banyak walk-in tak terhitung. **Asumsi itu runtuh** (§9: dua
pertiga celah ternyata cacat penjodohan, walk-in cuma ~7%). **Angka yang benar
~20–24%** — kembali ke ukuran pertama, kini dikuatkan sumber kedua.

### Kalimat yang dipakai di deck

> **Cabang kami distaf untuk 3 sampai 5 dokter sekaligus.
> Yang benar-benar ditangani pada satu waktu: dua orang.**

Punya angka, punya pembanding, tak butuh tafsiran. Untuk keberatan "tapi sore
rame": sore memang 2× pagi, tapi puncak tertinggi yang pernah terjadi di cabang
paling ramai pun **5 orang**, di ruangan yang menjadwalkan 4 dokter.

ℹ️ Konteks yang menjawab "kan sehari bisa belasan pasien": Bintaro rata
**15,3 reservasi/hari** (pernah 44), Sunter 13,3 (pernah 43). Itu **berurutan**
— 15 pasien × 24,6 menit ≈ 6¼ jam, tersebar di hari buka 10–12 jam. Banyak
pasien sehari dan sedikit pasien pada satu waktu **tidak bertentangan**.

---

## 22. BEBAN HARIAN & PENEMPATAN DOKTER — [DECK MANAJEMEN]

Diukur 11 Sep, 90 hari. **Belum pernah dibahas sebelumnya.** Menurut HUB ini
temuan paling bisa ditindak dari seluruh pengukuran — dan tidak butuh anggaran.

### ⭐ Penempatan dokter TIDAK mengikuti ramainya hari — terbalik

| hari | reservasi/hari | jam dokter tersedia | **pasien per jam-dokter** |
|---|---|---|---|
| **Minggu** | 59,9 | **67,3** (paling sedikit) | **0,89** |
| Sabtu | 71,7 | 100,2 | 0,72 |
| Rabu | 59,5 | 84,2 | 0,71 |
| Jumat | 44,8 | 74,8 | 0,60 |
| **Selasa** | 31,6 | **113,5** (paling banyak) | **0,28** |
| **Kamis** | 22,4 | 88,0 | **0,25** |

**Selasa punya jam dokter terbanyak tapi pasien nyaris paling sedikit.** Minggu
kebalikannya. Selisihnya **3,5×** — satu jam dokter di hari Minggu melayani tiga
setengah kali lebih banyak pasien daripada di hari Kamis.

Ini **bukan** temuan "kapasitas kurang" atau "kapasitas lebih", melainkan
**kapasitasnya ada di hari yang salah**. Memperbaikinya = memindahkan jadwal.

⚠️ **SEBELUM DISAJIKAN — tiga syarat**, karena ini menyentuh jadwal orang:
1. Jam dokter diambil dari **jadwal berulang**; jadwal khusus per tanggal belum
   diterapkan di hitungan per-hari-pekan ini.
2. Dokter bisa dijadwalkan Selasa/Kamis untuk alasan yang tak terlihat di data
   reservasi (tindakan panjang, walk-in, administrasi).
3. **Konfirmasi dulu ke yang mengatur jadwal.** Jangan sajikan sebagai
   rekomendasi sebelum itu.

### Keliaran beban harian (90 hari)

| cabang | terendah | tengah | tertinggi | selisih |
|---|---|---|---|---|
| **Kalideres** | 1 | **5** | **37** | **7,4×** |
| Sunter | 1 | 9 | 43 | 4,8× |
| Cinere | 1 | 6 | 25 | 4,2× |
| Pamulang | 1 | 6 | 20 | 3,3× |
| Bintaro | 1 | 16 | 44 | 2,8× |

**Setiap cabang punya hari dengan cuma 1 pasien.** Kalideres hari biasanya 5,
hari tersibuknya 37.

### Bingkai yang disarankan untuk deck manajemen

> **Kapasitas kita cukup. Cuma sedang ditaruh di hari yang salah, dan kita
> tidak tahu hari mana yang akan ramai.**

Kalimat pertama menutup pertanyaan investasi. Kalimat kedua membuka dua
pekerjaan konkret: memindahkan jadwal, dan **meramalkan beban** — yang persis
fitur proyeksi (§14), kini dengan alasan operasional, bukan cuma alasan target.

---

## 23. ⭐ PEMBAGIAN DUA DECK — bagian mana milik siapa

Keputusan Ekky 11 Sep: ada **dua audiens**, dan pembahasannya berbeda.
Pembagiannya bukan soal memangkas satu tumpukan — ini **dua tumpukan berbeda**
yang kebetulan diukur bersamaan.

> **DECK SISTEM (internal/IT)** menjawab: *"sistem reservasi kita bagaimana?"*
> **DECK KLINIK (manajemen)** menjawab: *"klinik kita bagaimana?"*
> Data reservasi adalah **alat ukurnya**, bukan **subjeknya**.

| § | isi | deck |
|---|---|---|
| 1 | Ukuran & bentuk | **SISTEM** (ortho 75% → juga KLINIK) |
| 2 | Yang sehat (0 tindihan, mesin rekonsiliasi) | **SISTEM** |
| 3 | Kapasitas | **KLINIK** |
| 4 | Kehadiran & jarak pemesanan | **SISTEM** (bagian Sp.Ort → KLINIK) |
| 5 | ORTHO retensi & konversi konsultasi | **KLINIK** |
| 6 | Pertumbuhan baru vs lama | **KLINIK** |
| 7 | Lubang logika modul | **SISTEM** |
| 8 | Batas bukti | **KEDUANYA** — disiplin, wajib dibaca |
| 9 | Cacat penjodohan + akar | **SISTEM** |
| 10 | Identitas pasien | **SISTEM** |
| 11 | Integrasi Omnicare + 5 pertanyaan vendor | **SISTEM** |
| 12 | Rekam medis (46 kolom) | **SISTEM** |
| 13 | Jalur masuk reservasi | **SISTEM** |
| 14 | Proyeksi pendapatan | **KEDUANYA** (kelayakan=sistem, nilai=klinik) |
| 15 | Agenda yang disepakati | **SISTEM** |
| 16 | Harga & no-show | **KLINIK** |
| 17 | Tiket IT Tracker | **SISTEM** |
| 18 | Neraca | **SISTEM** |
| 19 | Angka mana yang bergeser | **KEDUANYA** — wajib |
| 20 | Jam kedatangan & sebaran dokter | **KLINIK** |
| 21 | Kapasitas diukur ulang | **KLINIK** |
| 22 | Beban harian & penempatan dokter | **KLINIK** |
| Lampiran | Klaim yang diralat | **KEDUANYA** — wajib |

⛔ **Jangan seret kapasitas/ortho/harga ke deck internal** — di sana mereka
mengalihkan perhatian dari pekerjaan yang harus diputuskan.
⛔ **Jangan seret cacat penjodohan ke ruangan manajemen** sebagai bagian
tersendiri — cukup **satu kalimat** yang menempel pada angkanya (lihat §19).

ℹ️ **Konkurensi (§21) bukan slide.** Ia jawaban satu baris untuk satu pertanyaan
yang datang sekali: *"perlu tambah kursi/dokter?"* — tidak. Selesai. HUB sempat
menghabiskan empat putaran di sana; jangan diulang.

---

## 24. ⛔ RALAT 11 Sep — `prescription` 92,2% itu SALAH

Ditemukan chat build, **diverifikasi ulang HUB di prod**. Ini ralat atas
kesalahan **metode HUB**, bukan salah ketik.

### Yang terjadi

`prescription` terisi-sebagai-string **47.870 dari 51.385 (93,2%)** — tapi
**berisi obat sungguhan hanya 7 baris (0,014%)**, terakhir April 2022. Sisanya
harfiah tanda titik-koma:

```
19.452 × [;]          14.912 × [;   ;]        8.181 × [;   ;   ;]
 3.216 × [;   ;   ;   ;]        1.140 × [;   ;   ;   ;   ;]
```

Minggu prod 4–10 Sep: "terisi" 308 dari 365 (84,4%), **berisi 0**.

### ⚠️ Pelajaran metode — berlaku untuk pengukuran apa pun ke depan

Predikat `TRIM(kol) <> ''` **mengukur tanda baca, bukan isi**, pada kolom yang
menyimpan daftar ber-pemisah. Yang benar:

```sql
TRIM(REPLACE(REPLACE(REPLACE(COALESCE(kol,''),';',''),CHAR(10),''),CHAR(13),'')) <> ''
```

HUB menguji ulang **9 kolom** rekam medis dengan predikat ketat:
**hanya `prescription` yang terpengaruh**; delapan lainnya identik di kedua cara.
Tapi kalau ada kolom lain yang kebetulan berpemisah, cara lama tak akan
menangkapnya — **selalu pakai predikat ketat.**

### Urutan yang BENAR (predikat ketat, minggu prod 4–10 Sep, 365 rekam)

| kolom | terisi | status tampilan |
|---|---|---|
| `procedures` | **100%** | sudah tampil |
| `diagnose_note` | 38,9% | sudah tampil |
| **`treatment_plan_note`** | **37,3%** | ⭐ **juara di antara yang belum tampil** |
| `chief_complaint` | 27,9% | sudah tampil |
| `subjective` | 24,1% | belum |
| `objective` | 21,4% | belum |
| `diagnoses` | 17,3% | dipakai sbg cadangan |
| `plan_note` | 4,9% | lewati |
| **`prescription`** | **0%** | ⛔ jangan ditampilkan |

**Seluruh tabel (51.385 rekam)**: procedures 99,7% · treatment_plan_note 35,1% ·
diagnose_note 27,1% · chief_complaint 24,1% · subjective 20,1% · objective 19,0% ·
diagnoses 11,0% · plan_note 5,7% · **prescription 0,0%**.

⛔ **Konsekuensi**: klaim lama *"prescription 92,2%, lebih sering dari keluhan
maupun diagnosa"* **dicabut**. Yang benar: **`treatment_plan_note` juaranya.**

## 25. RALAT 11 Sep — titik pembuatan reservasi: **ENAM**, bukan 3 atau 5

Berkas ini dan prompt build versi pertama menyebut **3**. Chat build meralatnya
jadi **5**. **Diverifikasi HUB di `origin/main`: ada 6** — keduanya kurang.

| # | titik |
|---|---|
| 1 | `ReservationController.php:789` — form admin |
| 2 | `LandingReservationController.php:166` — form publik |
| 3 | **`ReservasiCabangController.php:59`** — reservasi per cabang ← terlewat di kedua hitungan |
| 4 | `ReservationPromoter.php:103` |
| 5 | `ReservationPromoter.php:211` |
| 6 | `ReservationAutoProcessor.php:258` |

Patokan: `git grep -n "RawCusReservation::create" -- app/`.
⚠️ **Jangan percaya daftar ini — jalankan ulang grep-nya.** Dua hitungan
sebelumnya meleset karena mengandalkan ingatan/pembacaan sebagian.

## 26. ⛔ Ada pembatalan yang LAHIR batal — tak pernah lewat gerbang alasan

`ReservationCsvImporter::mapStatus()` (baris 668–675) memetakan `"batal"` /
`"cancel"` → `STATUS_CANCELLED` **saat PEMBUATAN**. Baris impor **lahir
sudah-batal** tanpa pernah melewati perubahan status.

Konsekuensi untuk pekerjaan `cancel_reason`: gerbang "tak boleh batal tanpa
alasan" **tidak cukup** berbentuk pagar di perubahan status.
⛔ **Jangan pernah mengklaim "100% pembatalan punya alasan."**

---

## 27. ⛔ RALAT ATAS RALAT — §25 salah: 6 call-site, tapi **5 HIDUP**

Urutan ralatnya: berkas ini & prompt versi pertama bilang **3** → chat build
meralat jadi **5** → HUB meralat lagi jadi **6** (§25) → **HUB yang salah.**

**`ReservasiCabangController::store()` TIDAK PUNYA RUTE.** Di seluruh `routes/`
hanya ada satu baris menyentuh controller itu:
`Route::get('reservasicabang', …@index)`. Diverifikasi juga lewat
`php artisan route:list`: cuma `GET|HEAD reservasicabang`.
`store`/`create`/`edit`/`update`/`destroy` **tak bisa dicapai.**

⭐ **Jadi hitungan 5 dari chat build yang tepat.** Ralat HUB benar sebagai hasil
grep, **salah sebagai kesimpulan** — `RawCusReservation::create` yang tak
terpanggil bukan "jalur pembuatan". Pelajaran: **grep menemukan call-site,
bukan jalur; selalu cek rutenya.**

### ⚠️ Tapi ada RANJAU di kode mati itu

`ReservasiCabangController::store()` bisa membuat reservasi, dan ia:
- tidak mengisi `created_by`
- **tidak memanggil `slotConflict()`** — nol pagar anti-tabrakan
- tidak menyetel `status`
- tidak memasukkan baris perawatan

Selama tanpa rute ia diam. Begitu ada yang menambah `Route::post('reservasicabang/…')`,
kita langsung punya jalur yang bisa menabrakkan jadwal dan melahirkan reservasi
tanpa jejak — dan klaim **"0 tindihan dalam 5.621 reservasi"** berhenti dijamin.

**Keputusan diserahkan chat build**: hapus metode matinya, ATAU isi `created_by`
+ panggil `slotConflict()` supaya aman kalau kelak dihidupkan. **Jangan
dibiarkan tanpa keputusan.**

## 28. Keadaan pekerjaan jejak reservasi di DEV (11 Sep sore)

Sudah ada di dev, **belum di-commit**, di atas `test/reza-checklist-dev`.
SQL `2026_09_11_reservasi_jejak.sql` **sudah jalan di dev** — `created_by`,
`cancel_reason`, `cancel_reason_note` ketiganya ada.

✅ **`created_by` terisi di 5 dari 5 jalur hidup** (diverifikasi HUB).
✅ Chat build **menulis uji**: `tests/Feature/JejakReservasiTest.php` dan
`tests/Unit/RiwayatRekamMedisTest.php`.

⚠️ **Menyentuh `public/assets/css/**` (2 berkas)** → naik prod **WAJIB rsync,
dibatasi ke `assets/` saja**. (Beda dari gerbong checklist yang tak perlu rsync.)

⚠️ Jalankan uji **per kelas**, jangan beruntun (phpunit dev tumbang di >±17 uji
ber-DB per proses).

⛔ **Jangan gabungkan dengan `rekonstruksipettycash`** (Reza, `212607e`, 11 Sep
14:31 — "2 wallet, Pindah Saldo, cut-over saldo awal"). Itu area lain dan
menyentuh UANG; push petty cash Reza sebelumnya menghasilkan 8 temuan saat
ditinjau + 4 lubang Reset. Antrean & tinjauan sendiri.

⛔ **Jangan jalankan build penjodohan sebelum jejak di-commit** — working copy
lokal DAN dev sama-sama dipakai bersama; dua tumpukan perubahan tak ter-commit
dari dua pekerjaan berbeda tak bisa dipisahkan lagi saat mau commit.

---

## 29. SAPUAN CONTROLLER — metode tanpa rute yang menulis (11 Sep)

Diminta chat build setelah `ReservasiCabangController` dihapus: *"metode
controller tanpa rute = ranjau tidur, dan pagar yang ada di controller lain
bukan pagar."* Disapu HUB di kode prod.

| | |
|---|---|
| Metode publik controller diperiksa | **536** |
| Punya rute | 524 |
| Tanpa rute | 18 |
| **Tanpa rute TAPI MENULIS** | **5** |

✅ **Kelimanya di `ReservasiCabangController`** — `store` (48), `update` (103),
`destroy` (164), `serviceStore` (178), `serviceDestroy` (194). Semuanya
`abortIfViewOnly` = **TIDAK**.

⭐ **Tidak ada kembarannya di controller lain.** 13 metode tanpa rute yang
tersisa tak menulis apa pun. Penghapusan oleh chat build (`e64232f`)
menyelesaikan **seluruh kategori**, bukan satu kasus.

Cara menyapu ulang: bandingkan `Route::getRoutes()` (action `controller`) dengan
`ReflectionClass::getMethods(IS_PUBLIC)` yang `getDeclaringClass()`-nya sama,
lalu saring badan metode dengan pola tulis (`::create(`, `->update(`,
`->delete(`, `->save(`, `->insert(`, `DB::insert|update|delete|statement`).
⚠️ **Lewati berkas yang nama kelasnya tak cocok path** — kalau tidak, autoload
berkas bayangan bikin skripnya fatal.

## 30. ⭐ BERKAS BAYANGAN PROD — dibersihkan 11 Sep, dan bahayanya NYATA

Sapuan §29 tersandung **8 berkas bayangan** di `app/Http/Controllers/Crud/`,
masing-masing mendeklarasikan nama kelas yang sama dengan controller asli:
`CustomerController(1)(2)` · `CustomerController20260401(1)(2)` ·
`ReservationController(1)(2)` · `…20260330(2)` · `…20260413_2`.
Semua **tak terlacak git**, umur **30 Mar – 13 Apr** (lima bulan).

### Kenapa berbahaya — `deploy.sh` membangun ULANG classmap tiap deploy

`deploy.sh` baris 52: `composer install --no-dev --optimize-autoloader`. Ia
memindai `app/` dan memilih **satu berkas per nama kelas**. Dengan bayangan
hadir, **tiap deploy adalah lemparan dadu** — dan deploy berikutnya (jejak
reservasi) menyentuh persis `CustomerController` dan `ReservationController`.

Kalau classmap memilih versi 30 Maret, prod kehilangan kerja lima bulan
**tanpa satu galat pun** dan halamannya tetap terbuka normal.

ℹ️ **Bukan teori**: 11 Sep **16:13:17** skrip sapuan HUB memicu galat persis itu
— *"Cannot declare class App\Http\Controllers\Crud\ReservationController, because
the name is already in use"* — tiga menit sebelum berkasnya dihapus.

### Yang dilakukan

1. Cadangkan ke `~/backup-bayangan-20260911/bayangan-controllers.tar.gz`
   (8.083 byte) + `MANIFEST.md5`.
2. **Uji pulih lebih dulu** — ekstrak ke `/tmp`, `md5sum -c`: **8/8 OK**.
   Baru setelah itu dihapus.
3. Hapus 8 berkas. Classmap **tidak** dibangun ulang (ia memang sudah menunjuk
   berkas asli, 0 entri ke bayangan).
4. Verifikasi: `ReflectionClass` tetap menunjuk `CustomerController.php`
   (660 baris) & `ReservationController.php` (1.407 baris) · beranda **200**
   dengan **URL akhir benar** · `/signin` 200 · **nol galat pengguna**.

### Sisa yang belum dibereskan di prod

`app/.DS_Store` · `app/Http/.DS_Store` · `app/Http/Controllers/Set/Concerns/` ·
`app/Models/Webhook.php` — semua tak terlacak, **tak membajak nama kelas**, jadi
tak mendesak. Periksa `Webhook.php` sebelum deploy besar.

⚠️ **`storage/logs/laravel.log` = 326 MB.** Belum ditangani, di luar lingkup hari ini.

---

## 31. Verifikasi tampilan rekam medis di dev (11 Sep) — LULUS

Dibandingkan langsung prod vs dev, pasien **Vanessa Taw**
(`45b6f582-4df4-11f1-9779-15f9a44a83b4`), rekam 9 Sep 2026:

✅ Baris **"Rencana: observasi gigi c kanan, gigi 27"** muncul di **dev**, tidak
ada di prod. `treatment_plan_note` **berhasil dirender**.
✅ **`prescription` tidak muncul** — sesuai ralat §24.

### "Kok datanya cuma segini?" — memang segitu isinya

Vanessa justru pasien **terlengkap** yang bisa ditemukan (40 rekam,
2023-07-20 s/d 2026-09-09):

| kolom | terisi |
|---|---|
| Tindakan | **40 (100%)** |
| Keluhan | **39 (98%)** |
| **Rencana** | **37 (93%)** |
| Objektif | 34 (85%) |
| Diagnosa terstruktur | 21 (53%) |
| Diagnosa catatan | 12 (30%) |
| Subjektif | **3 (8%)** |
| **Resep** | **0** |

Rekam 9 Sep menampilkan 4 dari 6 kolom karena `subjective` memang tak ada di
rekam itu (Vanessa cuma punya 3 dari 40). **Tampilan tidak menyembunyikan apa
pun** — rekam medisnya yang memang tipis (§24).

### ⚠️ Kejanggalan: panel kanan vs timeline bisa BERTENTANGAN

Di dev, panel kanan tertulis "Kunjungan terakhir **10 Jun 2026**" sementara
timeline menampilkan rekam **9 Sep 2026** — di satu halaman.

**Bukan bug kode.** Rekam medis dev **identik** dengan prod (40 rekam, tanggal
sama), tapi **data penjualan dev BASI**: 38 kunjungan vs 41, LTV 20,3 jt vs
21,9 jt, terakhir 10 Jun vs 9 Sep. Di prod keduanya cocok.

⚠️ **Tapi kerapuhannya nyata**: panel kanan (LTV/kunjungan/terakhir) membaca
`omnicare_sales_detail`, timeline membaca `omnicare_medical_record`. Kalau kedua
tabel itu sempat melenceng **di prod** (mis. sinkron gagal sebagian), halaman
akan menampilkan dua kebenaran bertentangan **tanpa peringatan apa pun**.
Tidak mendesak — tapi kandidat pemeriksa kesehatan.

ℹ️ Konsekuensi praktis: **dev selalu terlihat lebih miskin dari prod** untuk
angka uang/kunjungan. Jangan menilai fitur dari angka LTV di dev.

## 32. ✅ PROD BERSIH DARI NAMA KELAS GANDA (11 Sep, sesudah §30)

Dipindai `app/`, `database/`, `routes/`: **287 kelas, NOL nama kelas ganda.**

Artinya bahaya §30 **tertutup seluruhnya**, bukan cuma di folder Controllers —
`composer install --no-dev --optimize-autoloader` yang dijalankan `deploy.sh`
tiap deploy tak lagi punya pilihan ambigu. **Deploy berikutnya bukan lagi
lemparan dadu.**

Cara memeriksa ulang: pindai tiap `*.php`, ambil `namespace` + `class`, kelompokkan
per FQCN, laporkan yang punya >1 berkas. ⚠️ Jangan pakai `class_exists()` untuk
ini — ia meng-autoload dan berkas bayangan akan memfatalkan skripnya (§29).

---

## 33. Deploy jejak reservasi + rekam medis — PROD 11 Sep (`a23c947`)

SQL `2026_09_11_reservasi_jejak.sql` dijalankan DULU, lalu merge → `deploy.sh
main` → **rsync `assets/` saja** (kerjaan ini menyentuh 2 berkas CSS — beda dari
gerbong checklist hari yang sama yang tak perlu rsync).

Verifikasi: ReservationController 1.407→**1.608 baris** · CustomerController
660→**694** · ReservasiCabangController **66 baris / 1 metode** (CRUD mati
hilang) · 3 kolom fillable · CSS ter-serve 200 (65 KB & 37 KB) ·
`public_html/index.php` **md5 tak bergeser** · beranda & `/signin` 200 dengan
URL akhir benar · **0 galat**. Titik balik `398e4dc`.

⚠️ **Pelajaran deploy**: branch dibangun di atas titik LAMA (`1213f19`), main
sudah maju ~200 commit. Merge bersih, **tapi uji WAJIB dijalankan ulang pada
HASIL MERGE** — branch lulus 15/15, hasil merge memunculkan 1 galat. Galat itu
ternyata **merah palsu** (`lead_status` tak ada di DB lokal, ADA di prod & dev),
tapi tanpa mengujinya kita tak akan tahu bedanya.

⚠️ Worktree uji: **vendor harus DISALIN, bukan symlink** (Git Bash Windows
kebetulan menyalin). Patokan penyelamat tetap
`(new ReflectionClass(X))->getFileName()`.

## 34. ⛔ CACAT TEKS REKAM MEDIS — dilaporkan Ekky, ditelusuri 11 Sep

Laporan: *"banyak tulisan rekam medis kepotong-potong setengah."*
Ditelusuri HUB dan **dipisah jadi dua hal yang berbeda**.

### (a) Yang BUKAN cacat kita — "..." dari Omnicare

Rekam yang dilaporkan berbunyi `Rencana: posisi kawat niti 0,12 up/low...`.
Diverifikasi tiga lapis: layar = kolom DB = **`raw_payload` (jawaban MENTAH API
Omnicare)**, ketiganya identik termasuk titik-titiknya. `Str::limit` kita di 90;
teksnya 32 karakter — tak mungkin kena.

**207 dari 16.130 kolom klinis (1,3%) berakhir "..."**, panjang **8–179**
karakter (tengah 31). Pemotongan sistematis akan seragam panjangnya; yang
bervariasi begini hampir pasti **diketik dokternya**.

⛔ **JANGAN bangun logika untuk "memulihkan" teks ini.** Tak ada yang bisa
dipulihkan.

### (b) Yang BENAR-BENAR rusak — `$clean` meleburkan baris

`CustomerController::buildRiwayat()` ~baris 499:
```php
str_replace(['<br>','<br/>','<br />'], ' ', $v)   // ganti-baris -> SPASI
```
Di catatan gigi tiap baris = satu temuan/tindakan. Meleburnya membuat catatan
terbaca seperti potongan yang disambung serampangan.

| cacat | jumlah | porsi dari 16.130 |
|---|---|---|
| **`<br>` dilebur jadi spasi** | **4.303** | **26,7%** |
| **Kata MENEMPEL (`</div><div>`)** | **189** | **1,2%** |
| **`&nbsp;` tak terbuang `trim()`** | **855** | **5,3%** |

Kata menempel paling berbahaya — **mengubah arti, bukan cuma rupa**:
`...distal 24</div><div>ra ganti niti...` → **`distal 24ra ganti niti`**.
(`strip_tags` membuang tag tanpa menyisipkan apa pun.)

`&nbsp;` → `html_entity_decode` mengubahnya jadi **U+00A0**, dan **`trim()` PHP
tidak membuang U+00A0** (hanya `" \t\n\r\0\x0B"`).

⚠️ **BUKAN regresi dari PR jejak reservasi.** `$clean` identik sudah ada di
`398e4dc` untuk keluhan/objektif/diagnosa. PR itu menambah `subjektif` +
`rencana` lewat pembersih yang sama → cacatnya **jadi jauh lebih terlihat**,
bukan baru lahir.

**Prompt perbaikan siap**: `docs/handoff/prompt-build-teks-rekam-medis.md`

ℹ️ Catatan cara kerja: contoh yang Ekky kirim justru jatuh di 1,3% yang **bukan**
salah kita — tapi memeriksanya yang membuka 26,7% yang salah kita. Kecurigaan
yang "meleset" tetap berbuah.

---

## 35. Perbaikan teks rekam medis — SIAP, menunggu prod (`99bce72`)

Dikerjakan chat build, **diverifikasi ulang HUB terhadap 16.127 kolom klinis
prod 2026**:

| pemeriksaan | hasil |
|---|---|
| Kini berbaris banyak | **4.018 (24,9%)** |
| Sisa U+00A0 | **0** ✅ |
| Tag HTML bocor | **0** ✅ |
| Kata masih menempel | **0** ✅ |
| Teks berakhir `"..."` | **207 dari 207 UTUH** ✅ (Bagian 0 dihormati) |

Uji: `RiwayatRekamMedisTest` **9 → 17 uji / 35 assertion**, hijau.
⚠️ Satu assertion lama **diubah** karena ia mengunci perilaku yang salah
(`'TERAPI : SCALLING KELAS 3 RA RB'` dengan satu spasi). Kriteria "uji lama
tetap hijau" yang HUB tulis karena itu tak bisa berlaku harfiah — kriteria itu
disusun sebelum ada yang sadar sebuah uji mengabadikan cacatnya.

### Dua penyimpangan chat build dari saran HUB — keduanya LEBIH BENAR

1. **String ber-`\n` + CSS `white-space: pre-line`, bukan array baris.**
   `.pxd-tl__kv` grid `96px 1fr` dan tiap `@if` memancarkan SEPASANG span; array
   akan menggeser grid — bahaya yang HUB tandai sendiri lalu HUB sarankan
   melanggarnya. Nilainya tetap dirender `{{ }}` (di-escape), ganti barisnya
   dari CSS, **bukan HTML mentah dari sistem luar**.
2. **Pemangkasan pakai regex `/u`, bukan `trim($s, charlist)`.**
   `trim()` dengan charlist bekerja **per-BYTE**; memasukkan U+00A0
   (`0xC2 0xA0`) bisa **mencacah karakter multibyte** di tepi teks. Saran HUB
   akan menanam bug halus yang hanya muncul pada masukan non-ASCII.

Urutan yang menutup `24ra`: tag blok (`br`, `/div`, `/p`, `/li`, `/tr`) → `"\n"`
**SEBELUM** `strip_tags`. Tag inline (`i`, `span`, `ul`) sengaja **tidak**
diubah — memecahnya justru memotong kalimat utuh. (Prod 2026: br 10.038 ·
div 1.820 · i 6 · ul 4 · span 4.)

ℹ️ Chat build juga memverifikasi hal yang tak diminta: `procedures` dan
`prescription` lewat `$pecah` yang **tak pernah** memanggil `strip_tags` —
kalau isinya HTML, ia akan tampil sebagai tag ter-escape. **Nol HTML dari
17.945 baris.** Tak ada yang perlu dikerjakan, tapi kini terbukti, bukan diasumsikan.

⚠️ **Naik prod WAJIB rsync `assets/` saja** — menyentuh `pasien-redesign.css`.
Dan verifikasinya **bukan** kode 200 + ukuran; lihat catatan cache 7 hari di
memori `reference_deploy_access`.

---

## 36. Audit HUB atas perbaikan teks — dua temuan, ditutup (`c3f1e33`)

Berkas auditnya: `docs/handoff/audit-hub-99bce72.md`.

### ⛔ Temuan 1 — regex hanya menangkap tag PENUTUP

`99bce72` mengubah `</div>`, `</p>`, `</li>`, `</tr>` jadi `"\n"`, tapi **tag
PEMBUKA** `<div>`, `<p>`, `<li>` dibuang `strip_tags` tanpa disisipi apa pun →
kata tetap menempel. **Kelas bug yang sama dengan `24ra`, bentuk lain.**
4 rekam prod nyata (0,02%), semuanya mengubah arti: `pc rapasang` · `rb 16pc RA`
· `stop elasticnext` · `34,44next pasang`.

**Perbaikan (`c3f1e33`) — satu karakter**, `/` jadi `/?`:
```php
'~<\s*/?\s*(?:br|div|p|li|tr)\b[^>]*>~i'
```

### ⚠️ Temuan 2 — dev menjalankan berkas uji LAMA

Kode perbaikan di dev identik dengan commit, tapi berkas ujinya tidak ikut:
dev 148 baris/9 uji vs commit 248 baris/17 uji. Klaim "9 → 17" benar untuk
commit, **tidak untuk dev** — hijau palsu bentuk baru. Sudah disamakan.

### ✅ Verifikasi akhir HUB (16.127 kolom klinis prod 2026)

| | |
|---|---|
| Berbaris banyak | **4.023 (24,9%)** |
| Sisa U+00A0 · tag bocor · **masih menempel** | **0 · 0 · 0** |
| Teks berakhir `"..."` | **207 asli, 207 UTUH** |
| `\b` aman utk `<divider>`/`<pre>`/`<print>`/`<bruh>`/`<paragraf>`/`<liter>` | ✅ tak satu pun jadi ganti baris |
| Div bersarang | ✅ `a ⏎ b`, tanpa baris kosong |
| Inline (`span`/`i`/`ul`/`b`) | ✅ tak tersentuh |
| Uji unit | **23 uji / 42 assertion** hijau |
| dev md5 = commit md5 (3 berkas) | ✅ |

ℹ️ Selisih kecil tak penting: chat build menghitung teks "..." **200**, HUB
**207** — beda jendela pengukuran. Kesimpulan identik: 100% utuh.

ℹ️ **Batas yang diketahui & dibiarkan**: tag yang TIDAK dikenal (mis. `<pre>`,
`<divider>`) tetap merekatkan kata, karena `strip_tags` membuangnya tanpa
pengganti. Prod 2026 tak punya satu pun (br 10.038 · div 1.820 · i 6 · ul 4 ·
span 4), dan sapuan akhir memastikan **0 kasus nyata tersisa**. Tak diperbaiki
karena tak ada yang perlu diperbaiki.

⚠️ **mysqld lokal HUB mati** (HUB menjalankan `SHOW COLUMNS` — jebakan yang sudah
tercatat, tetap kena). `JejakReservasiTest` karena itu 15 galat **karena
lingkungan**; bukti sah sebelum mati: **15/15, 47 assertion**.

**Siap prod.** Nol SQL. ⚠️ Menyentuh `pasien-redesign.css` → **WAJIB rsync
DIBATASI ke `assets/`**, dan verifikasinya **md5 HTTP + pembuat-cache vs md5
disk** (cache 7 hari), bukan kode 200 + ukuran.

---

## 37. ✅ PERBAIKAN TEKS REKAM MEDIS NAIK PROD — `23fb5b1`

Audit pra-prod dijalankan HUB (dua putaran perbaikan: `99bce72` → `c3f1e33`).

| pemeriksaan | hasil |
|---|---|
| Merge | bersih, **tepat 3 berkas**, nol kejutan |
| Uji pada **HASIL MERGE** (vendor DISALIN) | `RiwayatRekamMedisTest` **23/23, 42 assertion** |
| `JejakReservasiTest` | 1 merah **PALSU** — `lead_status` tak ada di DB lokal, ADA di prod |
| Sapuan 16.127 kolom klinis prod | berbaris 4.023 (24,9%) · 0 U+00A0 · 0 tag bocor · **0 menempel** · **207/207** `"..."` utuh |
| Prod sesudah naik | `23fb5b1` · CustomerController **753 baris** · regex & `pre-line` terpasang · beranda & `/signin` 200 URL akhir benar · **0 galat** |
| Aset (**patokan baru**) | md5 HTTP+pembuat-cache **=** md5 disk · `filemtime` **sejajar** dua lokasi · `index.php` **utuh** |

**Bukti akhir**: rekam yang dulu terbaca `pc rapasang 37,47` kini
`ligature 16-15 & 25-27, pc ra` ⏎ `pasang 37,47 niti rb 12`.

### ⭐ Temuan operasional baru: JENDELA DEPLOY

`created_by` per 15 menit sesudah deploy `a23c947`:

```
16:00–16:29   11 reservasi · terisi  0 · KOSONG 11   <- jendela deploy
16:30 →       17 reservasi · terisi 17 · KOSONG  0   <- 100%
```

**Kode LAMA masih melayani permintaan ~25 menit setelah `deploy.sh` selesai.**
11 reservasi lahir tanpa `created_by` — bukan lubang fitur, melainkan peralihan.

⚠️ **Konsekuensi untuk verifikasi pasca-deploy**: jangan menyimpulkan "fiturnya
tidak mengisi" dari menit-menit pertama. **Tunggu ~30 menit** sebelum menilai.
Kalau tidak, kita akan mengejar hantu — atau lebih buruk, me-rollback fitur yang
sebenarnya sehat.

ℹ️ Bukti pemakaian nyata: 28 reservasi dibuat sejak deploy oleh Firda Mareta
Naskah (9), Della Raqisiah (3), Cici Nurjanah (3), Sales Sunter (2); 1
pembatalan dengan alasan terisi. Dua galat prod hari itu **dua-duanya dari
skrip HUB sendiri**, nol dari pengguna.

---

## 38. Teks berakhir `"..."` — DITUTUP dengan panggilan API LANGSUNG

Ekky bertanya ulang apakah `Rencana: posisi kawat niti 0,12 up/low...` terpotong.
HUB memanggil **API Omnicare secara langsung** (`/get/all_medical_records`,
place Bintaro, 9 Sep 2026 — baca saja, satu permintaan, kode 200).

Jawaban API, baru diambil:
```
pasien: Helmalia Emila · booking MRT71GMB
treatment_plan_note  'posisi kawat niti 0,12 up/low...'
procedures           '26PM047 - Signature Metal Braces drg Nugroho'
chief_complaint / subjective / objective / diagnose_note / diagnoses : NULL
```

**Bukti kini EMPAT lapis, semuanya identik**: layar = kolom DB =
`raw_payload` (salinan saat sinkron) = **API dipanggil ulang hari ini**.

⛔ **Tidak ada titik mana pun di sistem kita yang memotongnya.** Dan kolom klinis
lain memang **NULL di sumbernya** — bukan hilang di perjalanan.

Dua kemungkinan tersisa, keduanya **di luar jangkauan kode**: dokternya mengetik
"..." sebagai singkatan, atau Omnicare memotong di dalam sebelum sampai API-nya.
Panjang yang bervariasi **8–179 karakter** pada 207 kasus lain condong ke yang
pertama. Kalau mau sampai akar: tanya drg. Nugroho atau vendor.

ℹ️ Bukti perbaikan berbaris pada pasien yang sama: dari 25 rekam Helmalia, hanya
2 kolom punya tag ganti-baris — keduanya kini benar. Contoh 27 Apr 2025:
`terapi :` ⏎ `scalling kelas 3 ra rb` ⏎ `PRO OD 18 28`.
⚠️ **Halamannya memang akan terlihat hampir sama** — rekamnya mayoritas satu
baris. Itu benar, bukan gagal.

ℹ️ Kebetulan: assertion uji lama yang harus diubah chat build berbunyi
`'TERAPI : SCALLING KELAS 3 RA RB'` (satu spasi) — **uji itu mengabadikan bentuk
rusak rekam Helmalia**, pasien yang Ekky pakai memeriksa.
