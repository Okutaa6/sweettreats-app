# Serah-Terima — Koreksi Absensi & Tiga Fitur Baru

> Ditulis chat HUB **5 Sep 2026** sesudah pembahasan dengan Ekky. Semua angka
> di bawah **diukur langsung di prod**, bukan dari ingatan. Untuk dikerjakan
> chat build baru.

## Aturan yang dipakai — diputuskan Ekky 5 Sep

> **Cuti TIDAK memakan hari libur orang itu sendiri.**

Kodenya **sudah hidup di prod** (`liburUntuk()`). Yang tersisa adalah **data
lama** yang terlanjur dipotong sebelum perbaikan mendarat. Ekky memilih aturan
ini secara sadar meski artinya mengoreksi keputusannya sendiri di masa lalu.

Libur mingguan per aturan (`att_work_rule_day`, `day` 0=Minggu … 6=Sabtu):

| Aturan | Libur |
|---|---|
| GS · FO-Asdent · GS Pagi | hari **1** (Senin) |
| Fleksibel Kantor *(= "Kantor ABG")* | hari **0 & 1** (Minggu & Senin) |
| Fleksibel Operasional | hari **1** (Senin) — ikut klinik, **masuk Minggu** |
| Fleksibel | — |

---

## A. Koreksi data — dua orang, terukur

### A1 · Yovanka Rima Millennia — cuti minus

`user_id a1c29697-7657-4f96-a92a-6638aa85bc26` · aturan **Fleksibel Kantor**
sejak 6 Agu · pengajuan cuti **id 243**, 3–8 Sep, *approved*.

Sistem memotong **6 hari** (`att_cuti_mutasi` id **31–36**), termasuk:
- **id 34 — 6 Sep 2026 = Minggu** ⛔ liburnya
- **id 35 — 7 Sep 2026 = Senin** ⛔ liburnya

**Perbaikan:** batalkan mutasi **34 dan 35**. Cuti terpakai jadi 4 hari
(3, 4, 5, 8 Sep). Saldonya yang sekarang **minus** harus kembali **tidak minus**
— verifikasi angkanya sesudah perbaikan, jangan diasumsikan.

### A2 · Ramdan Ramdhani — potongan kembar + hari libur

Aturan **GS** (libur Senin). Mutasi yang ada:

| id | tanggal | hari | keterangan | vonis |
|---|---|---|---|---|
| 22 | 16 Agu (**Minggu**) | −1 | Pengajuan cuti di-ACC | ✅ **SAH** — Minggu hari kerja GS |
| 24 | 16 Agu (**Minggu**) | −1 | Pengajuan cuti di-ACC | ⛔ **KEMBAR** dari id 22 |
| 23 | 17 Agu (**Senin**) | −1 | Pengajuan cuti di-ACC | ⛔ **hari liburnya** |
| 25 | 17 Agu (**Senin**) | −1 | Pengajuan cuti di-ACC | ⛔ kembar **dan** hari libur |
| 27 | 21 Agu | +3 | `ganti_hari` "salah input" (oleh Ekky) | ⛔ tambalan atas kekacauan di atas |

Ekky: *"Ramdan salah input dan malah input berkali-kali; sebenarnya dia cuma
cuti 2 hari (16–17). Aku berusaha koreksi tapi ternyata salah — malah +3 bukan
+2."* Lalu, sesudah melihat 17 Agu itu Senin: *"harusnya B saja — aku salah pada
diriku di masa lalu, tak melihat fakta hari itu libur."*

**Perbaikan:** sisakan **hanya id 22**. Batalkan **23, 24, 25, dan 27**.

⚠️ **Hasil akhirnya: sisa tetap 11 hari** — angka yang sekarang sudah tampil.
Yang diperbaiki bukan saldonya, tapi **catatannya**: sekarang 5 baris untuk 1
hari cuti. Kalau ada yang mengaudit, riwayat itu tak bisa dijelaskan.

⛔ **JANGAN menghapus baris** kalau tabelnya dipakai sebagai buku besar. Pakai
mutasi pembalik ber-keterangan jelas, atau tandai batal — apa pun yang dipakai
`CutiService`. **Jejaknya harus tetap terbaca**; ini angka yang menentukan uang.

---

## B. Penetapan aturan kerja — tiga orang

⚠️ **Ini jebakan yang sudah dua kali menggigit**: `effective_from` = **hari
orang itu MULAI KERJA**, bukan hari barisnya dibuat. Salah isi = alpa palsu.

| Orang | Sekarang | Jadi | Catatan |
|---|---|---|---|
| **Alpajri Suhendar Al Qhusni** (`fajri@`) | GS · cabang **NULL** | **Fleksibel Operasional** | Cabang **tetap NULL** — itu disengaja supaya ia bisa meninjau lintas cabang tanpa meninjau dirinya sendiri. ⚠️ 4 checklist-nya masih menggantung (10, 25, 27, 30 Agu) — perubahan aturan **tidak** menyelesaikannya |
| **Ela** (`ela@rblas.id`) | **tak punya aturan** · cabang NULL | **Fleksibel Kantor** + ABG | **6 scan** (2–5 Sep, masuk & pulang) tapi **0 baris `att_day`** → layarnya menulis "Belum absen". Butuh **backfill `att_day` dari scan yang sudah ada** |
| **Dodi** (`dodi@rblas.id`) | **tak punya aturan** · cabang NULL | **Fleksibel Kantor** + ABG | **0 scan** — tak perlu backfill, tapi tanpa aturan ia akan tetap tak terlihat besok |

**Hanya dua orang RB Las di seluruh sistem** (Ela & Dodi) — jadi memperbaiki
keduanya menutup seluruhnya.

📌 **"ABG" bukan cabang.** Tak ada `raw_branch` bernama ABG. Yang ada adalah
kelompok **Kantor (ABG), 13 orang** di layar Kehadiran — pengelompokan
berdasarkan aturan **Fleksibel Kantor**. Jadi *memberi aturan itu* = *menaruh
mereka di ABG*. **Verifikasi** mereka muncul di kelompok itu sesudahnya.

---

## C. Melani & Putri — 25 Agustus

Keduanya **Sunter**, aturan FO-Asdent, berstatus **`alpa`** pada 2026-08-25.

Konteks yang sudah diperiksa, supaya tak salah tafsir:
- Maulid Nabi terdaftar libur **hanya untuk Bintaro, Cinere, Kalideres**.
- **Pamulang & Sunter sengaja TIDAK libur** (dikonfirmasi Ekky) — jadi ini
  **bukan** kalender yang terlewat.
- Sunter hari itu: **2 hadir · 2 alpa · 1 libur** — cabangnya buka, dan
  seseorang di sana **sudah berhasil** ditandai libur.

⇒ Mekanismenya **sudah ada**; yang belum ada adalah **layarnya**. Perbaikan
Melani & Putri sebaiknya dilakukan **lewat fitur D1 di bawah**, bukan tembak
langsung ke basis data — supaya sekalian membuktikan fiturnya bekerja.

---

## D. Tiga fitur baru

### D1 · Koreksi absen oleh admin — berjejak audit

**Belum ada sama sekali.** Yang ada cuma koreksi **cuti**
(`CutiController::koreksi`) dan modal koreksi karyawan **untuk hari ini saja**.
Tak ada jalan mengubah `alpa → hadir/libur` untuk hari lampau.

- Ubah status `att_day` satu orang satu tanggal: **alpa · hadir · libur · izin ·
  sakit · cuti**.
- **Alasan wajib**, tersimpan bersama **siapa & kapan**.
- ⛔ **Hormati periode gaji yang sudah DITUTUP** — `att_day` beku saat periode
  ditutup (§D2). Kalau tanggalnya di periode tertutup: **tolak dengan pesan
  jelas**, jangan diam-diam mengubah angka yang sudah dibayarkan.
- Kasus uji nyata: Melani & Putri, 25 Agu, `alpa → libur`.

### D2 · Ambang DODO bisa diatur

Aturan baru dari Ekky:

> DODO dihitung **maksimal 7 jam**. Lebih dari itu, sisanya **lembur biasa**.
> Kurang dari itu, **semuanya lembur biasa** — bukan DODO — meski di hari libur.
>
> 10 jam DODO → **7 DODO + 3 lembur** · 2 jam DODO → **0 DODO + 2 lembur**

- **Ambangnya WAJIB bisa diatur**, jangan ditanam di kode (Ekky setuju). Taruh
  di `att_work_rule` atau setelan sistem, dengan jejak siapa mengubah.
- ⛔ **JEBAKAN PALING MAHAL**: DODO itu **pecahan dari angka lembur lama, bukan
  tambahan**. Rumus yang berlaku: `lembur biasa = total att_day − DODO`.
  **Uji terima wajib: `lembur biasa + DODO` tetap SAMA PERSIS dengan angka
  lembur yang selama ini dipakai** — untuk setiap orang, setiap periode. Kalau
  meleset, Lembar Gaji dan Kehadiran akan berselisih tanpa ada yang tahu.
- Baca `app/Services/LemburDodo.php` sebelum menyentuh apa pun — kelas itu
  satu-satunya tempat "mana yang DODO" diputuskan, dan komentarnya menjelaskan
  kenapa patokannya chip `sub_tipe='tambah_hari'` dan **bukan** tanggal kalender.

### D3 · Ekspor di layar Kehadiran, ber-rentang tanggal

Sudah ada `AbsensiPeriodController::export` — CSV payroll **per periode**, di
halaman Periode Gaji. Ekky ingin ekspor **di layar Kehadiran** supaya HR/Finance
tak perlu lewat periode dulu, dan **bisa memilih rentang tanggal bebas**.

- Rentang bebas (dari–sampai), bukan terikat periode.
- Isi minimal setara ekspor yang ada: Nama · Hadir · Telat · Alpa · Cuti · dst.
- Ikut penyaring yang sedang aktif di layar (cabang, dll) — kalau tidak, orang
  akan mengekspor sesuatu yang berbeda dari yang dilihatnya.

---

## E. Usulan: pemeriksa harian — `att:periksa-kejanggalan`

**Bukan permintaan Ekky; usulan HUB yang dia setujui.**

Empat mutasi salah di §A **baru ketahuan karena disapu manual malam ini**. Tak
ada apa pun yang memberi tahu. Ketiga kejanggalan ini **sudah terbukti terjadi**
dan ketiganya menentukan uang:

1. **Cuti dipotong di hari libur orangnya** — 4 kejadian ditemukan.
2. **Potongan cuti kembar** (dua mutasi, tanggal & orang sama) — 2 kejadian.
3. **Orang aktif tanpa aturan kerja** — 2 orang (Ela, Dodi), dan salah satunya
   **sudah scan 6 kali** tanpa satu pun tercatat.

Jalankan harian, laporkan ke layar Kesehatan atau notifikasi. Yang penting:
**nol temuan harus berarti "sudah diperiksa", bukan "belum pernah dijalankan"** —
tampilkan kapan terakhir berjalan.

---

## F. Catatan yang tak masuk lingkup, tapi jangan hilang

- Halaman **Aturan Kerja** menulis *"Belum ada libur mendatang"* — kalender
  libur berhenti di Maret 2026. Bukan mendesak, tapi kalau tak ada yang
  mengisinya, hari libur nasional berikutnya jadi **alpa massal**.
- **4 checklist Alpajri** masih menggantung (10 Agu draft; 25, 27, 30 Agu
  missed). SQL koreksi untuk **27 Agu** sudah di-commit
  (`2026_09_01_koreksi_checklist_alpajri.sql`) tapi **belum pernah dijalankan**.
  Menjalankan satu tanpa yang lain akan terlihat ganjil di laporan — perlu
  keputusan Ekky, bukan diputuskan chat build.

---

## G. Lingkungan

- ⚠️ **Ini menyentuh PROD.** Koreksi §A, §B, §C adalah data produksi yang
  menentukan gaji. Kerjakan dulu di dev/lokal, tunjukkan hasilnya ke Ekky,
  **baru minta izin prod secara eksplisit**.
- Semua perubahan data: **semua syarat di `WHERE`** (id **dan** nama **dan**
  nilai lama), supaya salah satu tak cocok = **nol baris berubah**, bukan baris
  lain yang berubah. Catat nilai lama sebelum mengubah.
- **Kendali negatif wajib**: cacah orang lain yang tak tersentuh tetap sama.
- ⛔ JANGAN `db:show` / `Schema::hasTable()` / `SHOW COLUMNS` / `DESCRIBE` —
  membunuh mysqld lokal. Pakai `SHOW TABLES LIKE` + `SELECT kolom LIMIT 0`.
- ⚠️ JOIN `users` ke tabel `att_*` / `raw_*` butuh
  **`COLLATE utf8mb4_unicode_ci`** — collation-nya beda, dan tanpa itu kueri
  gagal dengan *"Illegal mix of collations"*.
- phpunit **PER KELAS** (mati di >±17 uji ber-DB per proses).
- dev = **satu working copy bersama**: `git status --porcelain | grep "^??"`
  dulu; pindah branch dengan `checkout` **TANPA** `reset --hard`.

Selesai: entri `docs/log-update.md` + salin ke Google Drive + catat ke memori.
