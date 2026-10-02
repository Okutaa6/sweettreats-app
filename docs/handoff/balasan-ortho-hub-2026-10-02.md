# Balasan — Handoff ORTHO HUB 1 Okt 2026

> Dari **Reza** (+ Claude di VS Code) untuk **HUB**, 2 Oktober 2026.
> Menjawab kedua berkas handoff 1 Okt: *"usulan menumpuk & reservasi tak
> tertaut"* dan *"rancangan: mesin usulan & layar kerja Sales"*.
>
> Sepuluh dari tiga belas butir dikerjakan apa adanya. **Tiga ditolak**, dan
> bagian terpenting dokumen ini adalah alasannya — karena ketiganya justru yang
> Anda tandai paling mendesak.

---

## 1. Yang dikerjakan

| Butir | Status | Di mana |
|---|---|---|
| 1e `--tanpa-usulan` untuk tarikan mundur pertama | ✅ | `OrtPullVisits.php:95` |
| 2a tautkan reservasi yang sudah ada | ✅ butuh `--commit` | `OrtTautkanReservasi.php` |
| 2b kolom 100% kosong bukan lambang kegagalan | ✅ | `OrtQuery::kolomBelumDipakai()` + spanduk di `list.blade.php` |
| §1 irama pasien menggantikan `+30` | ✅ | `OrtCase::irama()`, median, dijepit [21,60] |
| §2.3 tandai keyakinan usulan | ✅ | `gap_spread_pct` + `sch_basis` bertingkat |
| §3 `install_is_estimated` | ✅ kolom + penyemai + layar | `2026_10_01_ort_tahap_c.sql` |
| §4a paginasi 10 → 50 | ✅ | `OrtQuery::PER_HAL` |
| §4b urutan abjad | ✅ hilang sendiri | sesudah penumpukan tanggal beres |
| §4c saringan kerja harian | ✅ | optgroup "Kerja hari ini" |
| §4d FU sebelum konfirmasi | ✅ | dengan **menghapus** langkah konfirmasi — lihat §3 |

Dua catatan yang mungkin menarik buat Anda:

**§2.3 sempat salah ukur, dan ujinya yang membongkar.** Versi pertama memakai
BANYAKNYA selang (`sch_basis_n >= 6`) sebagai penanda keyakinan. Itu mengukur
banyaknya data, bukan konsistensinya: pasien berselang 20·90·25·110 punya lebih
banyak selang daripada pasien 28·28·28, tapi iramanya jauh lebih liar — dan
ukuran itu justru menilainya paling meyakinkan. Sekarang memakai rumus Anda,
`(max − min) / median ≤ 0,5`.

**§3 ternyata sudah dihitung penyemai sejak hari pertama** dan ditulis sebagai
kalimat ke kolom `note`. Yang kurang cuma kolom yang bisa dikueri. Backfill-nya
membaca kalimat itu, jadi data yang sudah disemai tidak perlu disemai ulang.

---

## 2. Tiga butir yang DITOLAK — dan kenapa

Butir **1a (pagar kapasitas)**, **1b (hari praktik dokter)**, dan
**1c (klinik tutup Senin)**.

### Diagnosisnya berbeda dari yang kami kira

Jawaban pertama kami memang memasang ketiganya. Sesudah terpasang, penumpukan
hilang — tapi hanya karena pagar kapasitas mendorong baris ke hari lain. Akar
masalahnya ternyata di tempat lain.

Penyebab 651 usulan di satu tanggal adalah **lantai `MIN_MAJU_HARI`** di
`OrtControl::tanggalUsulan()`: setiap jatuh tempo yang sudah lewat dijepit ke
`hari ini + 3`. Saat menarik riwayat berbulan-bulan, semua pasien yang
tertinggal menumpuk di tanggal dekat yang sama. Lantai itu yang kami pasang,
dan lantai itu pula yang kami cabut.

Diukur sesudah lantainya dibuang, **tanpa pagar apa pun**:

| | |
|---|---|
| Pasien aktif | 34 |
| Tanggal jatuh tempo berbeda | 28 |
| Paling padat satu tanggal | **2 orang** |
| Rata-rata per tanggal | 1,21 |

Sebabnya jatuh tempo mewarisi sebaran kunjungan terakhir: 39 pasien punya 32
tanggal kunjungan berbeda. Kunjungan tersebar di semua hari buka, jadi jatuh
temponya ikut tersebar.

### Dan maknanya berubah

Kolom Jadwal bukan tanggal janji, melainkan **jatuh tempo** — dan brief sendiri
sudah menuliskannya di kamus istilah: *"SCH · Jadwal · tanggal pasien SEHARUSNYA
datang"*, berpasangan dengan *"RSV · kosong = pasien belum booking"*.

Kalau tidak ada slot yang dipesan dan tidak ada pasien yang diberi tahu, tidak
ada yang bisa mustahil. Menggeser jatuh tempo karena harinya penuh justru
membuatnya berbohong tentang kapan pasien sebenarnya sudah waktunya kontrol.

**Pagar hari dokter tidak hilang — ia pindah ke tempat yang benar.** Diperiksa
saat MEMESAN, oleh `OrtJadwalDokter` yang memakai ulang trait
`ResolvesDoctorAvailability` milik modul Reservasi: memotong jam istirahat,
cuti, dan pengecualian per tanggal. Jauh lebih teliti daripada yang pernah ada
di mesin usulan. Lembar Buat Reservasi kini menampilkan hari praktik dokter
pasien itu lengkap dengan jam dan seberapa penuh.

### Kelemahannya, dikatakan apa adanya

Semua angka itu dari **39 pasien lokal**; prod punya 1.639. Perkiraan dari
angka Anda sendiri — 627 kunjungan ortho Sabtu dalam tiga bulan — memberi
sekitar **10 pasien per cabang per Sabtu**. Jauh dari 235, tapi tetap
perkiraan, bukan pengukuran.

### ⚠ Yang kami minta dari Anda

**Ukur sebaran jatuh tempo di prod sesudah tarikan pertama.** Itu satu-satunya
yang membuktikan keputusan ini benar di volume sebenarnya:

```sql
SELECT sch_date, COUNT(*) n FROM ort_control
 WHERE is_proposal = 1 AND closed_at IS NULL
 GROUP BY sch_date ORDER BY n DESC LIMIT 5;
```

Kalau ada tanggal berisi puluhan pasien dalam satu cabang, **lapor dan pagarnya
kami pasang kembali** — mesinnya masih utuh, jadi reversibel.

---

## 3. Satu butir yang dijawab dengan membongkar premisnya

Butir **§4d**: *"apakah Sales boleh mencatat follow-up sebelum jadwal
dikonfirmasi?"*

Jawabannya: **langkah konfirmasinya yang dihapus.**

Pemicunya kalimat Anda sendiri di §1 — *"Tidak ada aturan otomatis yang layak
dikonfirmasi massal. Usulan terbaik yang bisa kita hitung pun masih tebakan dua
minggu."* Kalau benar begitu, tombol sekali klik tanpa dialog di atas tebakan
14 hari memang tidak pantas ada.

Ditambah koreksi Ikhsan di §0: **Sales yang menentukan tanggal, dokter hanya
menyarankan lisan.** Brief Revisi 3 menyangka tanggalnya sudah disepakati
dokter dan pasien di kursi perawatan — itulah premis yang membuat "satu klik"
masuk akal, dan premis itu salah.

Tangga tombolnya sekarang dua anak: **Catat Follow-up → Buat Reservasi**.
Telepon dulu, baru pesankan — persis urutan di lapangan.

---

## 4. Kolom Jadwal berhenti menyebut tanggal

> ⚠ **DIBATALKAN 5 Okt 2026 — tiga hari sesudah surat ini dikirim.**
>
> Bagian di bawah ini **sudah tidak berlaku**, tapi dibiarkan utuh karena ia
> catatan apa yang kami sampaikan ke Anda saat itu.
>
> Yang membatalkannya: perkiraan memberi masa depan empat tingkat tapi masa
> lalu cuma satu ember ("Sudah waktunya"). Daftar diurutkan dari jatuh tempo
> terdekat, jadi semua yang terlambat menumpuk di atas — dan di situ persis
> mereka kehilangan pembeda. Enam baris teratas semuanya "Sudah waktunya",
> padahal yang satu telat 32 hari dan lima sisanya 2–3 hari.
>
> Itu penyakit yang sama dengan lantai `MIN_MAJU_HARI` yang kami laporkan di
> §2: yang satu meratakan datanya, yang satu lagi meratakan tampilannya.
>
> Kolomnya kembali menampilkan **tanggal**, dengan garis putus amber selama
> masih hitungan mesin dan perkiraannya di tooltip. Keadaan terkini ada di
> `docs/brief-desain/brief-tracking-ortho-berjalan.md` §4.

Di luar yang Anda minta, dan asalnya dari membaca ulang brief konsultan
IT-2026-013 beserta berkas data Sales:

- Sheet Sales **tidak punya kolom tanggal kontrol berikutnya**. Berkas Cinere
  punya 149 kolom, 30 di antaranya kolom BULAN. Petugas mencatat tanggal pasien
  DATANG di bawah bulannya.
- Target brief butir 1 justru ingin kolom bulan itu hilang, dan tak satu pun
  dari tujuh butir target meminta tanggal ramalan.
- Kata Anisa: *"di kasih tau kalau udah lama ga kontrol dan di tawarin
  jadwal"* — pemicunya durasi, tanggalnya ditawarkan saat menelepon.

Jadi kolom Jadwal kini berbunyi `Pekan ini` · `± 3 pekan lagi` · `± Nov` ·
`Sudah waktunya`. Satuannya mengikuti yang memang dipakai Sales. Tanggalnya
sendiri tetap dihitung dan tetap mengurutkan daftar, memilah sub-tab, mengisi
kartu, dan jadi default lembar Buat Reservasi — ia hanya berhenti dipajang.

Sesudah ini, satu-satunya tanggal di tabel adalah tanggal **nyata**: kolom
Follow-up dan kolom Reservasi.

---

## 5. Tiga pertanyaan Anda yang jawabannya bukan milik IT

| Pertanyaan | Status |
|---|---|
| Ambang pasien per cabang per hari | **Gugur** — pagar kapasitasnya dibuang, lihat §2 |
| Boleh mengusulkan tanggal untuk pasien hilang >6 bulan? | **Diputuskan: tidak.** Mereka tidak diberi jatuh tempo dan mendarat di kartu "Belum punya jadwal". Ini satu-satunya pagar yang tersisa, dan ia soal beban kerja bukan soal tanggal |
| Impor follow-up lama dari Sheets | **MASIH TERBUKA** — menunggu Ikhsan |

---

## 6. Satu hal yang kami temukan dan belum ada solusinya

**Pemetaan dokter bolong.** `ort_case.doctor_ref_id` → `raw_doctor.omnicare_doc_id`:
di DB pengembangan hanya **12 dari 42 kasus** yang terpetakan, dan 13 tidak sama
sekali.

Akibatnya pemilih hari praktik dokter sering tidak bisa menampilkan apa pun, dan
Sales kembali mengisi tanggal sendiri. Layarnya sudah dirancang tetap berguna
tanpa dokter — alasannya dikatakan apa adanya, dan kotak tanggal bebas selalu
ada — tapi itu menambal gejalanya, bukan menyembuhkan.

Kalau angka prod ternyata jauh lebih baik dari dev, tolong kabari; kalau sama
buruknya, ini kandidat pekerjaan berikutnya.

---

## 7. Deploy

Seluruh langkah manual ada di **`docs/deploy/deploy-ortho.md`** — enam berkas
SQL, `access:rebuild`, enam perintah isi data, dan rsync yang dibatasi ke
`assets/`.

Dua kegagalan di sana **sunyi**: melewatkan `access:rebuild` (404 di semua
halaman) dan melewatkan `ort:rollup` (irama kosong untuk semua pasien, tanpa
pesan galat).
