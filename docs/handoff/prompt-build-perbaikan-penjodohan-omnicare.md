# Prompt untuk chat BUILD — Perbaikan Penjodohan Pasien ↔ Omnicare

> Salin seluruh isi berkas ini sebagai pesan pertama ke chat build.
> Disusun chat HUB 11 Sep 2026 dari pengukuran langsung di PROD.
>
> ⛔ **JANGAN digabung satu PR dengan** `prompt-build-jejak-reservasi-rekam-medis.md`.
> Prompt itu menambah **apa** yang ditampilkan dari rekam medis; prompt ini
> mengubah **rekam medis SIAPA** yang menempel ke seorang pasien. Kalau keduanya
> naik bersamaan lalu ada pasien melihat resep orang lain, tak akan ketahuan yang
> mana penyebabnya. Boleh satu chat, **wajib dua PR terpisah**, dan PR ini
> diverifikasi tuntas sebelum yang satunya disentuh.

---

Kamu chat **build** untuk SweetTreats. **Baca dulu**
`docs/handoff/data-reservasi-2026-09-11.md` §9–§10 — semua angka di bawah dari
sana, sudah diukur di prod. **Jangan ukur ulang.**

## Kenapa ini penting

Rekonsiliasi harian `omnicare:reconcile` dipakai untuk mengatribusikan
**Rp 2,61 miliar** dan untuk memutuskan siapa yang tidak datang. Dari celah
kunjungan-tanpa-reservasi (1.513 dalam 3 bulan), **~69,5% ternyata cacat
penjodohan kita sendiri** — bukan walk-in, bukan kelalaian FO.

## ⭐ AKAR MASALAH — Omnicare menerbitkan `p_id` PER CABANG

Awalan pid = cabang: `B-` 6.801 · `S-` 4.545 · `C-` 2.673 · `D-` 1.422 ·
`P-` 975 (+ pid lama berupa angka, mis. `0000000021`).

**892 orang punya lebih dari satu pid** (nomor + nama sama) — **1.797 pid kembar**.
Contoh nyata: `ISMU RAHARJO → B-2007-2758, B-2008-2793`.

`app/Console/Commands/MatchOmnicarePatients.php` memakai
`$linkedCustomers = DB::table('customer_omnicare_link')->pluck('raw_customer_id')->flip()`
lalu **melewati pasien yang sudah tertaut**. Itu memaksa **1 pid per pasien**,
padahal kenyataannya **1 pasien bisa punya N pid — satu per cabang**.
Akibatnya **kunjungan pasien di cabang kedua tak akan pernah terjodoh, selamanya.**

Bukti terukur: dari 379 kunjungan yang nomor HP-nya jelas ada di `raw_customer`
tapi tak tertaut — **150 karena pasien kita sudah tertaut ke pid LAIN**,
58 gagal skor, 36 ditahan penjaga ambiguitas.

---

## Yang dikerjakan — TIGA langkah, berurutan, jangan dibalik

### Langkah 1 — Backfill DOB (data saja, nol kode)

Omnicare punya DOB untuk **15.906 dari 16.500** pasien (96,4%); kita cuma
**8.802 dari 15.702** (56%). **5.786 pasien kita bisa diisi DOB-nya sekarang**
lewat `customer_omnicare_link` yang sudah ada → cakupan 56% jadi ~93%.

Kenapa ini duluan: skor penjodohan adalah **phone 30 + DOB 35 + nama 35**.
Dengan DOB terisi, langkah 2 bekerja di atas data yang jauh lebih kuat, dan
jumlah kasus ambigu turun sendirinya.

- SQL di `database/sql/`, idempoten, **hanya mengisi yang NULL** — jangan pernah
  menimpa `tanggal_lahir` yang sudah ada.
- Laporkan: berapa baris terisi, berapa dilewati.

### Langkah 2 — Izinkan BANYAK pid per pasien

- Buang asumsi 1:1 di `MatchOmnicarePatients` (`$linkedCustomers` skip).
- Satu `raw_customer` boleh punya **beberapa baris** di `customer_omnicare_link`.
- **Satu `omnicare_pid` tetap hanya boleh menempel ke SATU `raw_customer`** —
  batas ini jangan dilonggarkan, ia yang mencegah dua orang bertukar rekam.
- ⛔ **JANGAN buang penjaga ambiguitas** (kandidat #2 terlalu dekat → tidak
  di-link). Itu yang menahan ibu & anak di satu nomor agar tak tertukar.
  Kita punya **682 kontak dengan 2–7 pasien di satu nomor** — penjaga itu nyata
  gunanya, bukan kehati-hatian berlebih.
- ⛔ **JANGAN melebarkan `MATCH_WINDOW`** (sekarang 3 hari, ada di
  `OmnicareReconcile` dan digandakan sebagai `WINDOW` di
  `ReconciliationController`). **469 dari 594 kegagalan terjadi di hari yang
  PERSIS SAMA** — jendela bukan penyebabnya, dan melebarkannya justru menambah
  jodoh palsu.

### Langkah 3 — Tinjau ulang vonis lama & rerun

- Vonis `no_customer_link` bersifat **permanen**: reservasi yang dulu divonis
  begitu tak pernah dinilai ulang meski pasiennya kemudian tertaut. **284 baris**
  dalam 3 bulan terakhir terkena ini.
- Rerun rekonsiliasi **hanya** untuk baris bervonis `no_customer_link` dan
  `conflict_multi`. ⛔ **Jangan** menyentuh baris yang sudah `link_*` berhasil.
- Sediakan perintah yang bisa dijalankan ulang dengan aman (idempoten) dan
  punya mode **`--dry-run`** yang mencetak rencana tanpa menulis.

### Langkah 3a — ⛔ Reservasi DIBATALKAN jangan dicap "tidak hadir"

**385 reservasi bercap `cancelled` ikut divonis `orphan_noshow`** (12 Jun–10 Sep).
Pembatalan **bukan** ketidakhadiran — pasiennya memberi tahu. Mencampurnya
menaikkan angka no-show dari **18,2% menjadi 26,8%**, dan angka yang kebesaran
itu sempat dipakai berhari-hari.

- `OmnicareReconcile` **jangan** memvonis `orphan_noshow` untuk reservasi
  ber-`status='cancelled'`. Pakai vonis tersendiri (mis. `cancelled`) atau
  lewati sama sekali — yang penting **jangan masuk hitungan no-show**.
- Perbaiki juga baris lama yang sudah terlanjur bercap begitu.
- Laporkan angka no-show **sebelum dan sesudah** koreksi ini, terpisah dari
  efek langkah 2 dan 3, supaya ketahuan mana yang disebabkan apa.

### Langkah 3b — Periksa ulang tautan lama yang rapuh (BONUS, kerjakan kalau sempat)

**7.729 tautan (54,5%) dibuat dengan `phone+name` saja — tanpa DOB.** Setelah
langkah 1, banyak di antaranya kini **bisa diuji**: kalau DOB kita dan DOB
Omnicare **bertentangan**, tautan itu patut dicurigai.

⛔ **Jangan hapus otomatis.** Cukup **tandai** (`notes` / kolom penanda) dan
laporkan jumlahnya. Menghapus tautan yang salah lebih berbahaya daripada
membiarkannya sampai ada manusia melihat.

---

## ⛔ Kriteria terima — angka, bukan perasaan

Wajib melaporkan **sebelum vs sesudah** untuk seluruh `customer_omnicare_link`:

1. Jumlah tautan · jumlah `raw_customer` yang tertaut · jumlah pid tertaut.
2. **Pasien AKTIF tertaut**: sekarang **1.775 dari 2.157 (82,3%)** — harus naik.
3. **Nol pid menempel ke lebih dari satu `raw_customer`.** Ini pagar keselamatan;
   kalau ada satu saja, berhenti dan laporkan.
4. **Nol tautan LAMA yang berubah pasangannya.** Langkah 2 hanya boleh
   **menambah** tautan, tidak memindahkan yang sudah ada.
5. Sesudah rerun: berapa `no_customer_link` dan `conflict_multi` yang berhasil
   jadi `link_*`, dan berapa `orphan_noshow` yang berkurang.
   (Konteks: 31 reservasi terbukti **salah dicap tidak datang padahal pasiennya
   datang di hari yang sama**.)
6. **Angka no-show sesudah perbaikan harus dilaporkan ulang.** Sekarang ~25%.
   Kalau berubah drastis, itu temuan, bukan kesalahan — laporkan apa adanya.

## ⭐ Jangan ulangi pola yang sedang kita perbaiki

`matched_by` = `system` untuk **seluruh 14.176 baris — nol koreksi manusia,
selamanya**. Kodenya menyisakan kasus ambigu "untuk review manual", tapi
**layar reviewnya tidak pernah ada.** Pola yang sama menimpa `/reconcile`:
mesinnya menyiapkan antrean kerja, antreannya tak punya pintu.

Maka: apa pun yang tersisa ambigu sesudah PR ini **harus keluar sebagai daftar
yang bisa dibuka manusia**, bukan angka yang diam-diam membesar. Kalau membuat
layar baru terlalu besar untuk PR ini, **minimal cetak daftarnya lewat
perintah artisan** dan katakan terus terang bahwa layarnya belum ada.

## Aturan kerja repo ini

- **SQL langsung, bukan Laravel migration** — `database/sql/`, bertanggal,
  idempoten. `deploy.sh` TIDAK menjalankan SQL.
- ⛔ **Jangan `Schema::hasTable`/`hasColumn`/`SHOW COLUMNS`/`information_schema`**
  — terbukti mematikan mysqld lokal. Pakai `kolomAda()`/`tabelAda()`.
- ⛔ `DB::insert()` mengembalikan **boolean**, bukan jumlah baris — pakai
  `affectingStatement()`.
- ⚠️ Penomoran hari: repo ini memakai **ISO (1=Senin … 7=Minggu)**, terverifikasi
  98,5%. **Senin semua cabang TUTUP** (tak ada `day=1`).
- **Jangan auto-commit.** Biarkan tidak ter-stage untuk ditinjau Ekky. Branch sendiri.
- ⚠️ **Working copy lokal & DEV sama-sama dipakai bersama banyak chat.**
  `git status` dulu; catat branch yang hidup di dev dan **kembalikan** sesudah
  selesai. Login dev `/signin`, bukan `/login`.
- Uji **per kelas**, jangan belasan kelas beruntun (phpunit dev tumbang di
  >±17 uji ber-DB per proses).
- ⚠️ Dev di-`--no-dev` → `composer install` dulu, phpunit tak ada.

## Di luar lingkup PR ini

**Jangan** dikerjakan di sini: dorongan ke Omnicare / `set/insert_appt`
(tiket IT-2026-011, masih menunggu 5 jawaban vendor) · kolom `p_id` di
`raw_customer` (**tidak perlu** — tautannya sudah tersimpan permanen, menyalinnya
cuma denormalisasi) · membuka akses `/reconcile` (keputusan akses, bukan kode) ·
`created_by`/`cancel_reason`/rekam medis (prompt sebelah).
