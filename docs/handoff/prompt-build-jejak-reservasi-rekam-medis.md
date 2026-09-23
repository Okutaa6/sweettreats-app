# Prompt untuk chat BUILD — Jejak Reservasi + Rekam Medis Pasien

> Salin seluruh isi berkas ini sebagai pesan pertama ke chat build.
> Disusun chat HUB 11 Sep 2026 dari pengukuran langsung di PROD.

---

Kamu chat **build** untuk SweetTreats. Tugasmu di sini menulis kode fitur.
**Baca dulu** `docs/handoff/data-reservasi-2026-09-11.md` — semua angka di
bawah berasal dari sana, sudah diukur di prod, **jangan ukur ulang**.

Ada **dua pekerjaan yang sengaja digabung** karena keduanya menyentuh area yang
sama dan memisahkannya berarti mengubah tabel/berkas yang sama dua kali.

---

## BAGIAN A — Jejak reservasi (`created_by` + alasan batal + riwayat)

### Masalah yang diselesaikan (terukur di prod)

| lubang | angka |
|---|---|
| Reservasi tak punya `created_by` | hanya ada `updated_by` = penyunting terakhir |
| Pembatalan tanpa alasan | **652 batal**; `keterangan` terisi 305 tapi isinya catatan perawatan ("KONTROL METAL", "BENERIN KAWAT"), bukan alasan |
| Tak ada riwayat perubahan | reservasi digeser 3× tak meninggalkan jejak apa pun |

⛔ **Tidak bisa diisi mundur.** `audit_log` (6.456 baris) berisi login/logout,
AttFlag, UserDevice, Users, ItTicket, petty cash — **nol baris reservasi**.
6.400 reservasi lama **tak akan pernah** bisa diatribusikan. Pencatatan hanya
dimulai dari hari fitur ini naik. Jangan menjanjikan angka historis.

### Yang dibangun

1. **`created_by`** di `raw_cus_reservation`.
   ⛔ **RALAT 11 Sep — bukan 3 jalur, tapi ENAM.** Versi pertama prompt ini
   menyebut 3; chat build meralatnya jadi 5; **diverifikasi HUB di `origin/main`:
   ada 6.** Semua harus mengisi `created_by`:

   | # | titik pembuatan |
   |---|---|
   | 1 | `ReservationController.php:789` — form admin |
   | 2 | `LandingReservationController.php:166` — form publik |
   | 3 | **`ReservasiCabangController.php:59`** — layar reservasi per cabang (terlewat di kedua hitungan sebelumnya) |
   | 4 | `ReservationPromoter.php:103` |
   | 5 | `ReservationPromoter.php:211` |
   | 6 | `ReservationAutoProcessor.php:258` |

   Patokannya: `git grep -n "RawCusReservation::create" -- app/` — **jangan
   percaya daftar ini, jalankan ulang grep-nya**; kalau ada yang bertambah sejak
   11 Sep, daftar ini basi.

   Form publik: isi penanda non-user (mis. `NULL` + sumber yang jelas), jangan
   dipaksa ke user id palsu. Jalur impor/otomatis: tandai sebagai sistem, bukan
   orang yang kebetulan menekan tombol.

2. **`cancel_reason`** (pilihan terbatas) + **`cancel_reason_note`** (teks bebas).
   ⚠️ **Pakai nama & nilai yang sama dengan Omnicare** — `set/cancel_appt`
   menerima `cancel_reason` dan `cancel_reason_note`. Kalau kelak reservasi
   didorong ke Omnicare (tiket IT-2026-011), kolomnya tinggal diteruskan.
   Wajib diisi saat status diubah ke `cancelled`, dari layar mana pun —
   termasuk **inline update** (`ReservationController` ~baris 1242, daftar
   `$allowed` memuat `status`). Jangan hanya memagari form Edit.

   ⛔ **TAPI ada jalur yang melewati gerbang itu sama sekali** (temuan chat
   build, diverifikasi HUB): `ReservationCsvImporter::mapStatus()` baris 668-675
   memetakan `"batal"`/`"cancel"` → `STATUS_CANCELLED` **saat PEMBUATAN**. Baris
   impor **lahir sudah-batal** tanpa pernah melewati perubahan status.

   Konsekuensi: gerbang "tak boleh batal tanpa alasan" **tidak cukup** berbentuk
   pagar di perubahan status. Putuskan salah satu dan tulis alasannya:
   (a) impor mengisi `cancel_reason` = 'impor' otomatis, atau
   (b) baris lahir-batal ditandai berbeda supaya tak mencemari statistik alasan.
   ⛔ **Jangan pernah mengklaim "100% pembatalan punya alasan".**

3. **Riwayat perubahan.** ⭐ **Saran kuat: JANGAN bikin tabel baru.** Pakai
   `audit_log` yang sudah ada — kolomnya sudah pas:
   `user_id, user_name, action, model_type, model_id, description, old_values,
   new_values, ip_address, created_at`. Catat perubahan `status`, `date`,
   `start_hour`/`end_hour`, dan `raw_doctor_id`.
   Kalau menurutmu `audit_log` tidak cukup, **jelaskan alasannya dulu** sebelum
   menambah tabel.

### Kriteria terima (A)

- Reservasi baru dari **ketiga** jalur punya `created_by` yang benar
  (atau penanda yang jelas untuk form publik).
- Status diubah ke `cancelled` **tanpa alasan → ditolak**, dari form Edit
  **maupun** inline update.
- Menggeser tanggal/jam/dokter meninggalkan satu baris `audit_log` berisi
  nilai lama dan baru.
- Reservasi lama (tanpa `created_by`) tetap tampil normal, tidak error.

---

## BAGIAN B — Rekam medis lebih lengkap di halaman pasien

### Keadaan sekarang

`CustomerController` (sekitar baris 364–378) **sudah menarik SELURUH 46 kolom**
`omnicare_medical_record` untuk pasien itu (`->get()`), lalu membangun timeline
Riwayat di sekitar baris 512–570. Tapi yang dirender hanya **tiga**:
`chief_complaint`, `diagnose_note`/`diagnoses`, `procedures`.

**Jadi ini perubahan TAMPILAN. Nol SQL, nol perubahan sinkronisasi, nol API.**

### Yang ditambahkan, urut prioritas (angka = tingkat isi di prod, 3.686 rekam)

⛔ **RALAT 11 Sep — `prescription` DICORET.** Prompt versi pertama menaruhnya di
puncak dengan 92,2%. **Itu salah dan HUB yang salah.** Diukur ulang di prod
(51.385 rekam): terisi-sebagai-string 47.870 (93,2%), tapi **berisi obat
sungguhan cuma 7 baris (0,014%)** — terakhir April 2022. Sisanya harfiah tanda
titik-koma: `;` (19.452×), `;   ;` (14.912×), `;   ;   ;` (8.181×).
Minggu 4–10 Sep: **0 dari 365**. Angka 92,2% itu mengukur **tanda baca**.

⚠️ **Pelajaran metode**: kolom ber-pemisah tidak boleh diuji `<> ''`. Predikat
yang benar membuang pemisah dulu:
`TRIM(REPLACE(REPLACE(REPLACE(COALESCE(kol,''),';',''),CHAR(10),''),CHAR(13),'')) <> ''`
(HUB memeriksa 8 kolom lain dengan predikat ketat — **hanya `prescription` yang
terpengaruh**, sisanya identik.)

**Urutan yang BENAR** (predikat ketat, minggu prod 4–10 Sep, 365 rekam):

| kolom | terisi | catatan |
|---|---|---|
| `procedures` | **100%** | sudah ditampilkan |
| **`diagnose_note`** | **38,9%** | sudah ditampilkan |
| **`treatment_plan_note`** | **37,3%** | ⭐ **juara di antara yang belum tampil** — rencana perawatan, paling berguna untuk klinik ortho |
| `chief_complaint` | 27,9% | sudah ditampilkan |
| `subjective` | 24,1% | belum tampil |
| `objective` | 21,4% | belum tampil |
| `diagnoses` | 17,3% | sudah dipakai sbg cadangan `diagnose_note` |
| `plan_note` | 4,9% | lewati |
| **`prescription`** | **0%** | ⛔ **JANGAN ditampilkan** |
| `doctor` | 100% | siapa yang menangani — tampilkan sbg teks saja |

Jadi yang ditambahkan: **`treatment_plan_note` → `doctor` → `subjective` →
`objective`.** Itu saja.

### ⛔ JANGAN ditampilkan

- **5 kolom asuransi** (`p_ins_*`, `p_insurance`) — **0% terisi**, klinik tidak
  memakai asuransi.
- **Vital sign** (`vs_weight`, `vs_height`, `vs_temperature`, `vs_pulse`,
  `vs_respiration`, `vs_blood_pressure`, `vs_waist_line`) — 0,2–9%, klinik gigi.
- `plan_note` — 4,0%.

### ⛔ Jebakan yang sudah pernah menggigit

Kolom `doctor` dan `doc_id` di **rekam medis** berisi **NAMA**
(mis. `"drg. Andi"`), sedangkan di `doctor_list`/`commission` berisi **ANGKA**
(mis. `"23372"`). **Tampilkan saja sebagai teks — JANGAN dipakai men-join ke
`raw_doctor`.** Mencocokkan dokter lewat nama sudah terbukti nyasar ke rekam
dokter lain.

### Kriteria terima (B)

- Rekam dengan `prescription` terisi menampilkannya; yang kosong **tidak**
  meninggalkan label kosong atau baris hampa.
- `treatment_plan_note` tampil dan terbaca terpisah dari diagnosa.
- Pasien tanpa tautan Omnicare sama sekali → halaman tetap normal, tanpa error.
- Tak ada kueri tambahan ke DB (datanya sudah ada di `$medicalRecords`).

---

## Aturan kerja yang berlaku di repo ini

- **SQL langsung, bukan Laravel migration** — taruh di `database/sql/` dengan
  penamaan bertanggal, idempoten. (`deploy.sh` TIDAK menjalankan SQL; prasyarat
  dijalankan manual.)
- ⛔ **Jangan `Schema::hasTable` / `Schema::hasColumn` / `SHOW COLUMNS` /
  `information_schema`** — terbukti mematikan mysqld lokal. Pakai helper
  `kolomAda()` / `tabelAda()` yang sudah ada di repo.
  (Catatan: `CustomerController` yang lama masih memakai `Schema::hasTable` —
  biarkan saja, jangan sekalian dirombak di PR ini.)
- ⛔ `DB::insert()` mengembalikan **boolean**, bukan jumlah baris — pakai
  `affectingStatement()` kalau butuh hitungan.
- **Ikon: FontAwesome 5.8.2** — nama ikon khas FA6 akan jadi kotak kosong.
- **Warna `#461500` terkunci**; aksen (brown-2/3/pale, text-*, cream-*) fleksibel.
- **Jangan auto-commit.** Selesaikan pekerjaannya, **biarkan tidak ter-stage**
  untuk ditinjau Ekky. Pakai branch sendiri.
- ⚠️ **Working copy lokal dipakai bersama banyak chat.** `git status` dulu;
  jangan commit berkas campuran; jangan menggeser branch tanpa mengembalikannya.
- ⚠️ **DEV juga satu working copy bersama.** Cek branch siapa yang sedang hidup
  di sana dan kembalikan sesudah selesai. Login dev `/signin`, bukan `/login`.
- ⚠️ Kalau menambah **rute baru bersegmen-1 baru**, ia harus masuk
  `AuthGuard::SKIP_RBAC` atau akan **mati senyap** (gejalanya 302 ke beranda,
  bukan 403). Bagian A & B seharusnya tidak menambah segmen baru — kalau ternyata
  perlu, ingat ini.
- Uji: jalankan **per kelas**, jangan belasan kelas beruntun (phpunit dev tumbang
  di >±17 uji ber-DB per proses).

## Di luar lingkup PR ini

Jangan dikerjakan di sini, sudah punya tempat sendiri:
**rerun rekonsiliasi** `no_customer_link`/`conflict_multi` · **banyak pid per
pasien** (Omnicare menerbitkan pid per cabang) · **backfill DOB** · **akses
`/reconcile`** · dorongan ke Omnicare (tiket IT-2026-011) · realisasi harga per
perawatan · tagihan tertunggak.
