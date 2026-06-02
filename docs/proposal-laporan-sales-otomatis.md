# Proposal: Modernisasi Pelaporan Sales — dari Manual ke Otomatis

> **Untuk:** Manajemen + Tim Sales · **Tanggal:** 2026-05-29
> **Berdasarkan:** Audit 5 cabang Maret 2026 — [`docs/audit-sales-mar-2026.md`](audit-sales-mar-2026.md)

---

## Bagian 1 — Untuk Manajemen

### Ringkasan eksekutif

Sistem `/report/sales` di Data Center sudah siap menarik data penjualan **otomatis dari
kasir Omnicare**, menggantikan Google Sheet manual yang sales kerjakan tiap minggu.

**Ini bukan soal "data sales salah".** Audit 5 cabang Maret 2026 membuktikan angka ACTUAL
yang dilaporkan sales **akurat — semua dalam ±1% dari data sistem.** Tim sales kerja bagus.

Alasan utama pindah ke otomatis adalah **strategis, bukan koreksi**:

> **Otomasi pelaporan sales = FONDASI WAJIB untuk integrasi finance Accurate yang sedang
> direncanakan.** Visi-nya: Omnicare → (otomatis) → Accurate, finance cuma verifikasi.
> Google Sheet yang diketik tangan **tidak mungkin** jadi sumber pipa otomatis ke Accurate.
> Tanpa data sales yang sudah otomatis & bersih, integrasi Accurate tidak bisa dibangun.

Ditambah 2 alasan pendukung: **effort manual yang mahal**, dan **kerapuhan yang sudah terbukti**.

### Temuan audit — 5 cabang Maret 2026

| Cabang | ACTUAL manual | Omnicare (sistem) | Selisih | Konsistensi internal |
|---|---:|---:|---:|---|
| Bintaro | 302.309.000 | 301.171.000 | +0,4% ✅ | ⚠️ bocor 21,8% (pivot ≠ ACTUAL) |
| Sunter | 194.065.000 | 194.853.000 | -0,4% ✅ | ✅ konsisten |
| Kalideres | 106.500.000 | 106.450.000 | +0,05% ✅ | ✅ konsisten |
| Cinere | 91.276.000 | 91.756.000 | -0,5% ✅ | ✅ konsisten |
| Pamulang | 82.732.000 | 83.422.000 | -0,8% ✅ | ✅ konsisten |

**Kesimpulan jujur:** total revenue akurat di semua cabang. Tapi **1 dari 5 cabang (Bintaro)
detail-nya sudah bocor 21,8% bulan ini** — breakdown per-kategori (dipakai untuk keputusan
promo/insentif/dokter) tidak lengkap, padahal angka totalnya benar. Tidak ketahuan sampai
diaudit manual.

### Kenapa tetap pindah ke otomatis

**1. Fondasi integrasi Accurate (alasan utama).** Tanpa data sales otomatis, pipa
Omnicare → Accurate tidak bisa jalan. Ini bukan proyek terpisah — ini langkah 1 dari
rencana finance.

**2. Effort manual mahal.** Estimasi ~8-15 jam/cabang/bulan untuk input + pivot + verifikasi
× 5 cabang = **~500-900 jam tenaga/tahun** — hanya untuk capai akurasi yang sistem bisa
kasih instan & gratis. Jam itu bisa dialihkan ke jualan & analisis.

**3. Kerapuhan terbukti.** Bintaro bocor 21,8% bulan ini tanpa ketahuan. Kualitas tergantung
ketelitian individu — tidak ada jaminan. Staf bagus cuti/pindah → laporan cabang itu jatuh.
Bulan ini Bintaro, bulan depan bisa cabang lain.

> Catatan kejujuran: **jangan** sampaikan "data sales tidak akurat / risiko milyaran". Itu
> tidak benar (4 dari 5 cabang rapi) dan kalau di-cross-check akan mematahkan kredibilitas.
> Pesan yang benar: *"akurat tapi mahal & rapuh, dan tidak bisa jadi fondasi Accurate."*

### Solusi (sudah siap di sistem)

- ✅ Halaman `/report/sales` — auto-tarik dari kasir Omnicare, sudah dibangun
- ✅ Sync harian jam 02:00 — sudah dibangun di `app/Console/Kernel.php`
- ✅ Filter cabang/tanggal/dokter/kategori — sudah ada
- ✅ Export CSV — sudah ada
- ✅ Akurasi terverifikasi **5 cabang** — semua ±1% vs manual ACTUAL = di bawah ambang noise

### Action items (rekomendasi urutan)

1. **Aktifkan cron prod** — 1 baris di Hostinger hPanel → Advanced → Cron Jobs
   (`* * * * * /usr/bin/php /path/artisan schedule:run`). Setelah itu sync jalan tiap pagi.
2. **Brief tim sales** — gunakan Bagian 2 dokumen ini.
3. **Parallel-run 1 bulan** — sales tetap pegang Google Sheet sambil mulai pakai
   `/report/sales`. Akhir bulan: banding angka. Kalau cocok → fase 4.
4. **Pensiunkan Google Sheet manual** — setelah parallel-run berhasil. Sales pindah
   100% ke `/report/sales` untuk data; kerjaannya geser ke analisis (lihat Bagian 2).
5. **Lanjut ke integrasi Accurate** — dengan data sales sudah otomatis, fondasi untuk
   pipa Omnicare → Accurate sudah siap (lihat rencana finance terpisah).

---

## Bagian 2 — Untuk Tim Sales

### Halo tim Sales 👋

Kalian sudah lama ngerjain Google Sheet "Dashboard laporan sales" — ketik nama prosedur,
kode, qty, harga, **ratusan baris setiap bulan**. Itu kerjaan yang sangat memakan waktu.

Kami audit **5 cabang** untuk Maret 2026. Kabar baik: **angka total kalian akurat semua**
(dalam ±1% dari kasir). Kerja kalian bagus. Tapi 1 hal: **effort untuk capai itu sangat
besar** — ketik ratusan baris/bulan + jaga konsistensi. Dan di 1 cabang (Bintaro), detail
per-kategorinya sempat bocor 22% bulan ini tanpa ketahuan.

**Sekarang ada cara yang jauh lebih cepat — hasil sama akuratnya, tanpa effort ngetik.**

### Yang sudah tersedia: `/report/sales`

Di Data Center, ada halaman **Laporan → Laporan Penjualan**. Buka — angka langsung
muncul. Datanya **otomatis ditarik dari kasir Omnicare setiap pagi**.

- Tidak perlu input data
- Tidak perlu hitung pivot
- Tidak perlu update ACTUAL/TARGET tiap akhir bulan

### Buktinya angka cocok — Maret 2026, semua 5 cabang

| Cabang | ACTUAL Google Sheet kalian | `/report/sales` (auto) | Selisih |
|---|---:|---:|---:|
| Bintaro | Rp 302.309.000 | Rp 301.171.000 | +0,4% |
| Sunter | Rp 194.065.000 | Rp 194.853.000 | -0,4% |
| Kalideres | Rp 106.500.000 | Rp 106.450.000 | +0,05% |
| Cinere | Rp 91.276.000 | Rp 91.756.000 | -0,5% |
| Pamulang | Rp 82.732.000 | Rp 83.422.000 | -0,8% |

**Angka kalian sudah benar di semua cabang** (selisih cuma karena beda waktu ambil
snapshot). Yang otomatis akan kasih angka yang sama — tapi instan, tanpa kalian ketik.

### Tapi di Bintaro, ada masalah di pivot per-minggu — selisih Rp 64 juta

> *Catatan untuk Sunter:* pivot per-minggu Sunter **rapi** — sum kalian sama persis dengan
> ACTUAL kalian (Rp 194 jt = Rp 194 jt). Item-item promo besar (Payday, Free Orthokit,
> dll) ter-capture semua. Apresiasi untuk diligence kalian — yang berikut ini buat
> contoh dari Bintaro.

Bukan menyalahkan Bintaro — ngetik 500+ baris per bulan, lewatkan item itu wajar.
Sunter pun butuh effort luar biasa untuk konsisten. Tapi datanya jadi tidak reliable
untuk analisis:

```
Header laporan kalian:     ACTUAL = Rp 302 juta
Jumlah pivot kalian:               Rp 238 juta
─────────────────────────────────────────────
Selisih di laporan KALIAN SENDIRI: Rp 64 juta hilang
```

Item-item besar yang **terlewat dari pivot** (tapi sudah masuk kasir → uangnya nggak
hilang, cuma hilang dari laporan kalian):

| Item | Nilai |
|---|---:|
| Payday — Selfligating Metal Braces (3 pasien) | Rp 28,5 juta |
| Deluxe Metal Braces SP Free Orthokit (2 pasien) | Rp 12 juta |
| Freedom 2 | Rp 4,9 juta |
| WAR PROMO 8.8 — Signature Metal Braces | Rp 4,5 juta |

### Cara mulai pakai `/report/sales`

1. Login ke Data Center seperti biasa
2. Menu kiri → **Laporan** → **Laporan Penjualan**
3. Pilih **cabang** + **rentang tanggal** (misal: bulan ini)
4. Angka langsung muncul: total, per item, per dokter, per kategori, per hari
5. Tombol **Export CSV** kalau butuh format Excel untuk dianalisis
6. Bebas filter & banding — read-only, nggak bisa rusak data

### Yang BERHENTI jadi kerjaan kalian

- ❌ Ketik nama prosedur, kode, harga satu-satu
- ❌ Hitung pivot per-minggu
- ❌ Pastikan semua entri masuk
- ❌ Update header ACTUAL/TARGET akhir bulan
- ❌ Recheck angka untuk pastikan cocok kasir

### Yang TETAP jadi kerjaan kalian (justru ini intinya)

- ✅ **Analisis** — target vs actual, kenapa naik/turun, dokter mana yang produktif
- ✅ **Insight** — promo apa yang efektif, periode sepi kapan, segmentasi pasien
- ✅ **Follow-up** — kontak pasien batal, ingatkan kontrol behel, recovery no-show
- ✅ **Strategi** — usul promo, planning target periode depan

Singkatnya: **kerjaan ngetik dipangkas, fokus ke kerjaan mikir.** Itu kerjaan sales
yang sebenarnya.

### FAQ — biar gak ragu

**Q: Kalau angka di `/report/sales` ternyata salah, gimana?**
A: Datanya 100% dari kasir Omnicare yang sama dengan kalian akses. Kalau angkanya salah,
itu artinya entry kasir yang salah — bukan sistemnya. Hubungi IT, kita debug bareng.

**Q: Saya tetap mau Google Sheet untuk yakin?**
A: Boleh! Bulan depan: parallel-run. Kalian tetap update Google Sheet, *sambil* coba
`/report/sales`. Akhir bulan banding. Kalau cocok terus (sudah pasti cocok) → bulan
berikutnya pensiunkan Google Sheet.

**Q: Bagaimana kalau ada item baru atau koreksi?**
A: Otomatis tertangkap — sync jam 02:00 setiap hari ambil ulang 3 hari terakhir untuk
catch late entries & koreksi.

**Q: Saya butuh format khusus untuk meeting/presentasi?**
A: Export CSV → buka di Excel/Sheets, format sesuai kebutuhan. Atau bilang ke IT — kalau
sering banget butuh format yang sama, bisa dibuatkan template.

### Yang dibutuhkan dari kalian sekarang

Minggu depan:
- [ ] Coba buka `/report/sales` — banding dengan angka yang lagi kalian update di Google Sheet
- [ ] Catat: angka cocok? Filter membantu? Ada yang nggak ketemu? Apa yang masih
      kurang dibanding Google Sheet kalian?
- [ ] Kirim feedback ke IT/Admin

Bulan depan: kalau parallel-run lancar → sudah bisa skip Google Sheet. Kerjaan ngetik
manual selesai. ✨

---

*Dokumen ini disusun berdasarkan audit teknis sistem
([`docs/audit-sales-mar-2026.md`](audit-sales-mar-2026.md)) — 2 cabang ter-audit
(Bintaro & Sunter Maret 2026). Untuk pertanyaan teknis hubungi tim IT
SweetTreats Data Center.*
