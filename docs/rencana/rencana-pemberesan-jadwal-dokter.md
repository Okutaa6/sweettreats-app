# Rencana — Pemberesan Jadwal Dokter 5 Cabang

> Tiket IT Tracker: **IT-2026-042** (Support, PIC Ikhsan).
> Disusun HUB 17 Sep 2026. Status: **DIRENCANAKAN, belum dikerjakan** — menunggu
> jawaban Anisa/Sales atas pertanyaan §4.

---

## 1. Masalahnya

Dua sumber jadwal dokter sedang berbeda, dan keduanya tidak bisa dipercaya:

- **Poster jadwal (5 cabang)** — data LAMA. Belum memuat perubahan Sep 2026
  (Bintaro Sabtu +2 dokter 4 Sep, Kalideres Minggu 12 Sep, Cinere Kamis 17 Sep).
- **Tabel prod `raw_doctor_schedule`** — data BARU tapi berantakan. Ini yang
  menentukan **slot reservasi online**, jadi kalau salah, pasien yang kena.

Diukur di prod 17 Sep 2026 (92 baris `work`, 5 pengecualian ke depan):

| Jenis kekacauan | Contoh | Akibat |
|---|---|---|
| Baris rusak, akhir sebelum awal | Bintaro Min, Dharma Sp.Pros `22:00–20:00` | tak bisa dipakai apa pun, ranjau untuk kode |
| Baris kembar / tumpang tindih, orang sama | Kalideres Min Vinny `10:00–20:45` **dan** `10:00–21:00`; Bintaro Sab Panji `10–20` **dan** `15–20`; Bintaro Sel Rista `10–16` **dan** `16–17` | slot ganda / hitungan kapasitas keliru |
| Jam melewati tutup 20:00 | Andy Kalideres Rab s/d 22:00 · Yasnelly Sunter Jum s/d 21:00 · Dharma Kalideres Kam s/d 21:00 · Vinny Kalideres Min s/d 21:00 | slot online dibuka saat klinik tutup |
| Jam sebelum buka 10:00 | Fridhi Sunter Sab 09:00 · Dito Bintaro Min 09:00 | sama |
| Dokter NONAKTIF masih punya jadwal | Cinere Kam, drg Cut Yuliza Sp Ort 12–18 | slot dibuka untuk dokter yang tak praktik |
| Satu dokter di BANYAK cabang jam yang sama | **drg. Wira Ardana Sp.BM — Selasa 10–20 di KELIMA cabang**; drg Wiliam Sp BM Bintaro Rab 10–20; drg. Dharma Sp.Pros di 4 cabang | fisik mustahil; kemungkinan "on-call" yang dicatat sebagai jadwal reguler |
| Pengecualian hanya di satu cabang | 5 baris, semua Bintaro; empat cabang lain NOL | perubahan mingguan cabang lain tidak tercatat di sistem — atau dicatat dengan mengubah jadwal reguler |

Selisih poster ↔ prod per cabang (lengkap) ada di percakapan HUB 17 Sep; ringkasnya
**tidak satu pun dari lima poster cocok penuh**, selisih paling banyak di Cinere
(nama "Drg. Aisyah" di poster tidak ada di `raw_doctor`; Dito Rabu 10–13:30 vs prod 16–20).

## 2. Prinsip

1. **Ini masalah DATA, bukan kode.** Yang memutuskan benar/salah = FO/kacab tiap cabang,
   bukan IT. IT menyiapkan lembarnya, menandai yang mencurigakan, dan menerapkan putusan.
2. **Jangan langsung di prod.** Semua perubahan dicoba di DEV di atas potret prod dulu.
3. **Poster lama BUKAN acuan.** Acuannya jadwal operasional minggu ini menurut cabang.
4. **Sesudah bersih, poster dibuat DARI prod**, bukan diketik ulang — supaya tidak
   berbeda lagi tiga bulan kemudian.

## 3. Langkah

| # | Langkah | Siapa | Bentuk |
|---|---|---|---|
| 1 | **Lembar tinjau** dari prod: satu tabel per cabang × hari, persis isi prod, + kolom `putusan` (benar / ubah jam jadi … / hapus) + baris kosong untuk dokter yang belum tercatat. Baris mencurigakan (§1) sudah ditandai otomatis. | HUB (baca-saja prod) | `docs/handoff/lembar-tinjau-jadwal-dokter-<tgl>.md` |
| 2 | **Cabang mengisi putusan.** Lewat Anisa (operasional) — satu cabang satu penanggung jawab. Pertanyaan §4 dijawab dulu supaya penanda tidak bingung soal spesialis on-call & jam tutup. | Anisa / FO tiap cabang | lembar yang sama, terisi |
| 3 | **Uji di DEV.** Salin potret `raw_doctor_schedule` + `raw_doctor_schedule_exception` prod ke dev; jalankan skrip perubahan (idempoten, per `id`); verifikasi lewat `App\Services\DoctorSchedule` — slot yang keluar per cabang × hari harus sama dengan lembar. | HUB | skrip SQL + laporan verifikasi |
| 4 | **Cek reservasi mendatang** yang jatuh DI LUAR jam baru (dokter/cabang/tanggal). Daftar ini diberikan ke FO **sebelum** jadwal diubah, supaya pasien dihubungi dulu. | HUB → FO | daftar per cabang |
| 5 | **Terapkan di prod, hari SENIN** (semua cabang tutup, tidak ada booking berjalan). Dump dua tabel dulu → rollback = restore. SQL manual, bukan `deploy.sh` (tak ada kode). | HUB, izin eksplisit Ikhsan | SQL + dump |
| 6 | **Poster baru dari prod**: ekspor jadwal per cabang dalam bentuk yang bisa langsung dipakai desainer. (Halaman jadwal publik di sweettreats.id yang membaca tabel yang sama = fitur, tiket terpisah kalau mau.) | HUB → Marketing | ekspor per cabang |

Yang **tidak** termasuk: mengubah kode `DoctorSchedule`, layar jadwal dokter, atau
aturan absensi dokter. Kalau saat langkah 3 ternyata ada pagar kode yang harus ditambah
(mis. menolak `end < start` saat input), itu tiket sendiri.

## 4. Pertanyaan untuk Anisa (operasional) & Sales

**Spesialis on-call — putusan menyeluruh, paling menentukan:**
1. drg. Wira Ardana Sp.BM tercatat Selasa 10–20 di **lima cabang sekaligus**. Apakah
   beliau memang on-call (datang kalau ada pasien bedah mulut) — bukan praktik reguler?
   Kalau ya: apakah pasien boleh **booking online** ke beliau, atau harus lewat FO/telepon?
   (Jawaban ini menentukan apakah barisnya DIHAPUS dari jadwal reguler atau dipertahankan.)
2. Pertanyaan yang sama untuk drg. Dharma Sp.Pros (Bintaro Kam, Sunter Sel/Kam,
   Kalideres Kam, Cinere Kam) dan drg Wiliam Sp BM (Bintaro Rab).
3. Kalau spesialis on-call TIDAK boleh muncul sebagai slot online — apakah poster juga
   sengaja tidak memuat mereka (seperti sekarang), atau mau ditulis "by appointment"?

**Jam operasional:**
4. Jam tutup resmi 20:00. Ada jadwal sampai 20:30 / 20:45 / 21:00 / 22:00 (Sunter, Kalideres).
   Apakah itu lembur yang memang terjadi (dokter menyelesaikan pasien terakhir), atau
   salah ketik? Kalau memang terjadi: boleh pasien **booking** di jam itu, atau slot
   online tetap berhenti 20:00?
5. Poster Pamulang menulis banyak akhir praktik 19:30 sementara sistem 20:00. Apakah
   "pasien terakhir 19:30" itu aturan cabang? Kalau ya, sistem harus ikut 19:30.
6. Ada jadwal mulai 09:00 (Fridhi Sunter Sab, Dito Bintaro Min). Klinik memang buka
   lebih awal untuk mereka, atau salah ketik?

**Dokter & nama:**
7. Poster Cinere menulis **"Drg. Aisyah"** (Selasa 10–15, Minggu 15–20). Nama ini tidak ada
   di sistem. Sistem di slot itu: drg. Laksmi Indriandini (Sel) dan drg. Retno Purnamasari
   (Min 16–20). Siapa yang benar praktik? Apakah "Aisyah" nama panggilan salah satunya?
8. "Drg. Rara" (Cinere) = drg. Rahmawati Sunarya? "Drg. Ryan" = drg. Rachmad Farulian
   Nasution, "Drg. Dito" = drg. Anindito — sudah sesuai catatan sistem; mohon konfirmasi Rara.
9. drg Cut Yuliza Sp Ort sudah NONAKTIF di sistem tapi masih punya jadwal Cinere Kamis 12–18.
   Sudah benar-benar tidak praktik? (Kalau ya, barisnya dihapus.)
10. drg Fanisa Ayu C (Cinere Kam & Jum 16–20), drg Ira Septiana & drg. Vinny (Bintaro Sab),
    drg. Denny Heriawan (Kalideres Sel 10–15), drg. Panji & drg. Rista (Bintaro Jum) — ada di
    sistem, tidak ada di poster. Memang praktik di situ?
11. Kebalikannya: drg. Dito Cinere **Rabu 10:00–13:30** (poster) vs **16:00–20:00** (sistem) —
    mana yang berlaku sekarang?

**Cara kerja ke depan (supaya tidak berantakan lagi):**
12. Siapa di tiap cabang yang berwenang mengubah jadwal dokter di sistem? Sekarang jejaknya
    tercampur (perubahan Bintaro, Kalideres, Cinere oleh akun berbeda-beda).
13. Perubahan **sementara** (dokter cuti seminggu, ganti jam sekali) — apakah cabang tahu
    ada fitur *pengecualian tanggal* di sistem? Sekarang cuma Bintaro yang memakainya (5
    baris); cabang lain nol — padahal pasti ada perubahan. Kalau cabang lain mengubah
    jadwal REGULER untuk keperluan sementara, itu sumber kekacauan berikutnya.
14. Untuk Sales/Marketing: poster berikutnya mau dibuat dari ekspor sistem (kami siapkan
    per cabang), supaya satu sumber? Format apa yang paling gampang untuk desainer?

## 5. Risiko yang sudah dilihat

- **Reservasi yang sudah masuk** di jam yang akan dihapus/dipersempit → langkah 4 wajib
  sebelum langkah 5, jangan dibalik.
- **`DoctorSchedule` memakai ISO (1=Senin..7=Minggu)**, tabel absensi memakai Carbon
  (0=Minggu..6=Sabtu). Skrip perubahan hanya menyentuh sisi ISO. Salah satu hari = semua
  dokter bergeser sehari, dan gagalnya diam.
- Menghapus baris spesialis on-call mengubah **kapasitas** yang dilihat dashboard reservasi
  (utilisasi ~20–24% sekarang) — angkanya akan naik; itu benar, bukan regresi.
