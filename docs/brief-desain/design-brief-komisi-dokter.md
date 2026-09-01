# Design Brief — Komisi Dokter

⚠️ **SATU layar per sesi claude.ai/design.** File ini berisi **2 sesi**.
Tiap sesi, tempel: blok **Konteks produk** + **Angka nyata** + **Kamus istilah** +
blok **Gaya visual** + **satu** blok layar. Jangan gabung layar.

Audiens halaman ini: **Finance**. Halaman read-only, murni pelaporan.

---

## Konteks produk (tempel di TIAP sesi)

Klinik gigi 5 cabang (Bintaro, Sunter, Kalideres, Pamulang, Cinere). Halaman ini
menggantikan pekerjaan manual Finance: buka sistem rekam medis Omnicare, print PDF
"Rincian Komisi" satu per satu per cabang, lalu rekap ulang di spreadsheet untuk
menghitung fee yang harus dibayar ke tiap dokter tiap bulan.

Dokter di sini **freelance dan praktek di lebih dari satu cabang**. Komisinya
dihitung per tindakan: sebagian skema flat (mis. Rp 50.000 per tindakan), sebagian
persentase dari harga tindakan (mis. 12%).

Halaman ini **khusus komisi dokter**. Sistem juga mencatat komisi Assistant
Dentist, tapi itu sengaja tidak ditampilkan di sini — jangan didesain.

Basis angkanya **mengikuti pembayaran yang sudah masuk**, bukan akrual. Artinya
bulan yang sudah lewat angkanya final dan stabil; bulan berjalan masih naik tiap
hari mengikuti cicilan pasien yang masuk. Ini harus terasa di UI — Finance tidak
boleh salah kira angka bulan berjalan sudah final.

Pain point yang HARUS terasa terjawab di desain:

1. Finance langsung tahu berapa yang harus dibayar ke tiap dokter bulan ini,
   tanpa buka Omnicare.
2. Kalau dokter protes nominalnya, Finance bisa **telusuri sampai baris tindakan**
   (tanggal, pasien, tindakan, skema, nominal) dalam hitungan detik.
3. Untuk dokter yang praktek di banyak cabang, terlihat jelas berapa beban tiap
   cabang — karena pembukuannya per cabang.

Audiens Finance lebih suka **angka lengkap, breakdown, dan konteks pembanding** —
bukan ringkasan visual. Tabel padat dan rapi lebih dihargai daripada kartu besar
berisi satu angka.

Bahasa UI: Indonesia awam, bukan istilah akunting Inggris. **Tanpa emoji** — ikon
SVG garis.

## Angka nyata untuk mengisi desain (tempel di TIAP sesi — bukan lorem)

- Total komisi Bintaro, April 2026: **Rp 133.823.989** (angka asli tervalidasi).
- Nama dokter: drg. Putri Moortiningsih Hasheila · drg. Anindito Nurpradipto
  Wibisono · drg. Nugroho Sp.Ort · drg. Rachmad Farulian Nasution (drg. Ryan).
- Nama tindakan: Scaling · Tambal Komposit · Pencabutan Gigi · Kontrol Behel ·
  Perawatan Saluran Akar · Bleaching · Veneer.
- Kode tindakan bergaya `26CB068`.
- Nominal komisi per dokter per bulan wajar di kisaran Rp 4 juta – Rp 45 juta.
- Jumlah pasien per dokter per bulan wajar 20–160 orang.

## Kamus istilah (tempel di TIAP sesi — konsisten di semua layar)

| Istilah di UI | Artinya |
|---|---|
| **Komisi** | Fee yang harus dibayar klinik ke dokter. Bukan komisi rujukan/affiliate. |
| **Pasien** | Jumlah orang berbeda yang ditangani. Satu orang datang 3x tetap dihitung 1. |
| **Kunjungan** | Jumlah kedatangan. Satu orang datang 3x dihitung 3. |
| **Tindakan** | Baris perawatan yang menghasilkan komisi (scaling, tambal, dst). |
| **Omzet dikomisikan** | Nilai penjualan tindakan yang jadi dasar hitung komisi. |
| **Skema** | Cara hitung komisi tindakan itu: `12%` atau `Rp 50.000 flat`. |

Chip status:

| Chip | Teks | Warna |
|---|---|---|
| Dokter nonaktif | "Nonaktif" | abu `#6B6257` / bg `#EFEAE3` |
| Periode belum final | "Belum final" | kuning `#B5701A` / bg `#FBEFD9` |

## Gaya visual — zona BURGUNDY / Finance (tempel di TIAP sesi)

- Latar cream `#F4ECE2`, teks ink `#3B1500`, kartu putih radius besar, font Plus
  Jakarta Sans, **angka tabular** (wajib — semua kolom rupiah rata kanan dan
  digitnya sejajar).
- Chrome halaman burgundy: header/panel aksen `#8A2E43`, gradient header tabel
  `linear-gradient(120deg,#7A2739,#5A1B29)` teks terang, chip aktif
  `linear-gradient(135deg,#8A2E43,#6B2333)`, bg lembut `#F6E3E7`.
- Disiplin warna: burgundy **hanya** untuk chrome (header, tab, tombol). Merah
  terang `#C0392B` tetap khusus makna negatif — jangan ketukar.
- **Desktop-first 1280px+.** Ini halaman kerja Finance di depan layar besar.
- **Tanpa emoji**; ikon SVG garis stroke currentColor.
- Kepadatan: baris tabel rapat, jangan boros whitespace. Kartu ringkasan boleh ada
  tapi kecil dan di atas — bukan hero besar yang mendorong tabel ke bawah lipatan.

---

## SESI 1 — Layar: Daftar Komisi Dokter (desktop, zona BURGUNDY)

Layar pertama yang dibuka Finance tiap awal bulan untuk menyiapkan pembayaran fee.

1. **Header halaman**: judul "Komisi Dokter", subjudul menyebut cabang aktif +
   rentang tanggal + jumlah hari (mis. "Semua Cabang · 1 – 31 Juli 2026 · 31 hari").
2. **Bar filter** satu baris: rentang tanggal (dua date input) + tombol preset
   cepat (Bulan Lalu · Bulan Ini · 30 Hari · 90 Hari) + dropdown cabang + tombol
   "Unduh CSV" di ujung kanan. Untuk user yang terkunci ke satu cabang, dropdown
   cabang tampil **terkunci** dengan nama cabangnya — desainkan state ini.
3. **Peringatan periode belum final**: kalau rentang yang dipilih menyentuh hari
   ini, muncul baris tipis kuning di bawah filter: "Periode berjalan — angka masih
   bertambah mengikuti pembayaran yang masuk. Untuk pembayaran fee, pakai bulan
   yang sudah lewat." Desainkan state ini, jangan sebagai modal.
4. **Baris kartu ringkasan** (kecil, 4 buah, sejajar): Total Komisi · Jumlah
   Dokter · Total Pasien · Rata-rata Komisi per Dokter. Tiap kartu tampilkan
   **pembanding periode sebelumnya** (mis. "Rp 486,2 jt" dengan baris kecil di
   bawahnya "▲ 12% vs Juni"). Konteks pembanding ini penting untuk Finance.
5. **Grid kartu dokter** (3–4 kolom). Tiap kartu berisi: foto bulat (ada fallback
   inisial), nama dokter, chip spesialisasi, lalu **angka komisi besar sebagai hal
   paling menonjol**, dan di bawahnya baris metrik rapat: jumlah pasien ·
   kunjungan · tindakan · jumlah cabang. Kalau dokter praktek di >1 cabang,
   tampilkan deret nama cabang kecil (mis. "Bintaro · Sunter · Cinere"). Kartu
   dokter nonaktif diberi chip "Nonaktif" dan sedikit diredam. Seluruh kartu bisa
   diklik → layar detail.
6. **Kontrol urutan**: kecil di atas grid — urutkan berdasarkan Komisi Tertinggi /
   Jumlah Pasien / Nama. Default: komisi tertinggi.
7. **Baris total** di bawah grid: "Total 18 dokter · Rp 486.240.700" — Finance
   perlu angka penutup yang bisa dicocokkan.
8. **State kosong**: kalau tak ada komisi di periode itu, tampilkan panel ramah
   yang mengajari, bukan sekadar "Tidak ada data" — sebutkan kemungkinan
   penyebabnya (periode salah, cabang salah, data belum tersinkron).

## SESI 2 — Layar: Detail Komisi Satu Dokter (desktop, zona BURGUNDY)

Layar yang dibuka saat Finance perlu membuktikan angka ke dokter yang bertanya.

1. **Header dokter**: tombol kembali, foto, nama besar, chip spesialisasi, chip
   nonaktif bila perlu. Di kanan header: **filter periode & cabang yang sama persis
   dengan layar daftar** (nilainya terbawa dari layar sebelumnya) + tombol
   "Unduh CSV" + tombol "Cetak Rincian" (untuk diberikan ke dokter).
2. **Baris kartu ringkasan** (5, rapat): Total Komisi · Pasien · Kunjungan ·
   Omzet Dikomisikan · Rata-rata Komisi per Pasien. Kartu "Total Komisi" paling
   menonjol. Sertakan pembanding periode sebelumnya.
3. **Empat tab** — ini inti layarnya:

   **Tab "Per Cabang"** — menjawab kebutuhan breakdown fee antar cabang.
   Tabel: Cabang · Pasien · Kunjungan · Tindakan · Omzet Dikomisikan · Komisi ·
   Porsi (%). Kolom Porsi ditampilkan sebagai angka **dan** bar horizontal tipis
   di dalam sel. Baris total di `<tfoot>`.

   **Tab "Per Perawatan"** — menjawab "komisi per perawatan".
   Tabel: Kode · Nama Tindakan · Jumlah · Omzet Dikomisikan · Skema · Komisi ·
   Porsi (%). Kolom **Skema** menampilkan `12%` atau `Rp 50.000 flat`; kalau
   skemanya beda-beda antar cabang tampilkan rentang (mis. `10% – 12%`) dengan ikon
   info kecil. Baris total di `<tfoot>`. Ini tabel terpenting di halaman — beri
   ruang paling besar.

   **Tab "Tren Bulanan"** — 12 bulan terakhir. Tabel bulanan (Bulan · Pasien ·
   Kunjungan · Komisi · Selisih vs bulan sebelumnya) **didampingi** grafik batang
   sederhana di atasnya. Tabelnya jangan dihilangkan demi grafik — Finance membaca
   angkanya.

   **Tab "Rincian"** — bukti baris per baris. Tabel padat: Tanggal · No. Invoice ·
   Pasien · Tindakan · Jumlah · Omzet · Skema · Komisi · Cabang. Ada pagination di
   bawah dan header tabel yang menempel saat di-scroll (sticky), karena tabel ini
   bisa ratusan baris.

4. Semua tab mempertahankan filter periode & cabang saat berpindah.
5. **State kosong per tab**: mis. dokter yang periode itu hanya praktek di satu
   cabang — tab "Per Cabang" tetap tampil dengan satu baris, jangan disembunyikan.

---

## Yang TIDAK usah didesain

- **Komisi asisten (Assistant Dentist)** — datanya ada di sistem, tapi halaman ini
  sengaja fokus ke dokter saja. Jangan ada tab, kartu, atau kolom asisten.
- **Rekap komisi per cabang untuk seluruh dokter** (Bintaro Rp 133 jt, Sunter
  Rp 98 jt, dst) — breakdown cabang cukup dari sisi satu dokter di layar detail.
- Alur pembayaran fee (tandai lunas, upload bukti transfer, status payout) —
  halaman ini **read-only**, murni pelaporan.
- Pengaturan skema/rate komisi — itu diatur di Omnicare, bukan di app ini.
- Komisi rujukan / affiliate / KOC — domain terpisah, halamannya sudah ada.
- Tampilan HP — halaman ini desktop-first, sudah ada gerbang sopan untuk HP.
- Sidebar, header, dan shell aplikasi — sudah ada, jangan ikut didesain.

---

## Catatan untuk implementasi (bukan untuk ditempel ke Claude Design)

Sumber data: tabel **`omnicare_commission`** (`database/sql/2026_07_07_omnicare_commission.sql`),
1 baris per tenaga medis per baris tindakan, `commission_amount` = nilai komisi
FINAL. Di prod berisi ±80.911 baris, auto-sync tiap hari 02:00 lewat
`omnicare:sync` (`app/Console/Kernel.php`). Angkanya tervalidasi cocok persis
dengan laporan "Rincian Komisi" internal Omnicare.

- Semua query difilter tetap `commission_role = 'doctor'` — baris asisten dibuang
  di lapisan query, bukan sekadar disembunyikan di tampilan.
- Join ke master dokter: `omnicare_commission.doc_id` ↔ `raw_doctor.omnicare_doc_id`,
  nama pakai `COALESCE(rd.name, oc.doctor)`. Perhatikan jebakan nama kolom: FK
  dokter bernama `doc_id` di sini, tapi `doc_reff_id` di `omnicare_sales_detail`.
- Daftar dokter **digerakkan dari data komisi**, bukan dari `raw_doctor`. Kalau
  digerakkan dari master, dokter yang `omnicare_doc_id`-nya belum ter-mapping akan
  salah tampil Rp 0 — berbahaya untuk halaman uang.
- Periode default saat halaman dibuka: **bulan lalu** (beda dari Laporan Penjualan
  yang default bulan berjalan), karena basis "after payment" membuat bulan lalu
  sudah final dan itulah yang dipakai membayar fee.
- Reuse yang sudah ada: `SalesReportController::resolveContext()` +
  `resolveBranch()` + `export()` untuk filter periode, branch-scoping, dan CSV;
  `resources/views/crud/doctor/list.blade.php` untuk pola grid kartu;
  `App\Support\DoctorSpec::forName()` untuk chip spesialisasi.
- Tabel ini **belum ada di DB lokal** — apply `2026_07_07_omnicare_commission.sql`
  lalu `php artisan omnicare:sync --endpoint=commission` sebelum development.
  Di prod sudah ada datanya.
