# Draf FRM-IT-01 — COGS · Inventory · Slip Gaji

> **Status: DRAF DISUSUN IT, BELUM DIKONFIRMASI PEMOHON.** Ditulis chat HUB
> 4 Sep 2026 dari pembicaraan Ekky, supaya ketiganya **tampil di papan** dan
> antriannya jelas bagi Executive. Ketiga tiket ini masuk seed
> (`seed-antrian-it-2026-09-04.psv` baris 28–30) dengan penanda draf di
> latarnya. **Pemohon sebenarnya harus membaca, mengubah, dan menekan
> "konfirmasi" sebelum tiket lolos triase → Analisis.** Kalau pemohon tidak
> mau — itu temuan yang berharga, catat.

---

## 28 · COGS — kendali harga, HPP, dan promo

**Pemohon:** Finance — Della Puspita Sari · **Jenjang usulan:** L · **Masuk:** Triase

**Latar belakang / masalah saat ini.** Harga tindakan dan produk hidup di
Omnicare, HPP hidup di kepala Finance dan lembar terpisah, promo hidup di
Marketing. Tidak ada satu tempat yang memperlihatkan **margin per tindakan
per cabang**, dan tidak ada yang berbunyi saat harga berubah atau salah.
Kesalahan harga baru ketahuan di laporan bulanan.

**Kebutuhan dan hasil yang diharapkan.**
1. **Master harga** per tindakan/produk **per cabang**, dengan **riwayat
   perubahan** (siapa, kapan, dari berapa ke berapa).
2. **HPP** per tindakan/produk — diisi Finance; bahan mentah tersedia dari
   Omnicare (`sales_detail.base_price/usage_cost`, `product_sales.sum_base`).
3. **Margin** per tindakan per cabang, dihitung — bukan diketik.
4. **Daftar promo aktif** dan dampaknya ke margin (promo yang membuat margin
   negatif harus terlihat sebelum diluncurkan, bukan sesudah).
5. **Peringatan selisih**: harga yang terekam di transaksi Omnicare ≠ master →
   tampil di dashboard hari itu juga.
6. **Dashboard** untuk management: margin per cabang, per kategori, tren.

**Pengguna & proses yang terdampak.** Finance (pemilik HPP & master harga),
Executive (pembaca dashboard), Marketing (promo harus lewat pemeriksaan
margin), Front Office (harga yang dipakai di kasir).

**Target waktu.** — *(diisi pemohon)*

**Yang belum bisa ditetapkan IT** (harus dari Finance): daftar HPP awal,
aturan pembulatan, kategori tindakan, siapa yang boleh mengubah master.

**Ketergantungan.** Data HPP dari Finance. Menjadi prasyarat **Inventory**.

---

## 29 · Inventory / stok — menggantikan lembar kerja staf

**Pemohon:** *(sementara)* Supervisor Operasional — Anisa Aulia — **konfirmasi
pemilik sebenarnya saat triase** · **Jenjang usulan:** L · **Masuk:** Triase

**Latar belakang / masalah saat ini.** Stok bahan dan produk per cabang
dicatat staf di Google Sheets: masuk dari pembelian, keluar dari pemakaian
tindakan, opname manual. Pekerjaan berulang, rawan selisih, dan tak
tersambung ke pembelian (yang sudah ada di Data Center lewat ROP/petty cash)
maupun ke pemakaian (yang sudah ada di Omnicare).

**Kebutuhan dan hasil yang diharapkan.**
1. **Master item/SKU** yang **disamakan dengan Omnicare** (`product_sales.sku`,
   `item_id`) — satu kode, bukan dua.
2. **Stok masuk otomatis** dari pembelian yang sudah tercatat (ROP lembar
   bayar, petty cash) — tanpa input ulang.
3. **Stok keluar otomatis** dari pemakaian tindakan/produk di Omnicare
   (`sales_detail`, `product_sales`).
4. **Opname per cabang** di Data Center menggantikan sheet; selisih opname vs
   sistem tampil dan harus dijelaskan.
5. **Peringatan stok minimum** per item per cabang.
6. **Nilai persediaan** (qty × HPP) → umpan ke **COGS**.

**Pengguna & proses yang terdampak.** Staf cabang (opname, tak lagi mengetik
sheet), Supervisor/Ops (kendali stok), Finance (nilai persediaan), Purchasing
(pemicu beli).

**Target waktu.** — *(diisi pemohon)*

**Risiko yang harus disebut jujur.** Asumsi *"kalau datanya lengkap dan clean,
otomasi mudah"* **belum terbukti** — data Omnicare terbukti tidak seragam
(`doc_id` nama vs angka; keluhan/diagnosa 27–39%). Kebersihan master
item adalah pekerjaan pertama, bukan kodingnya.

**Ketergantungan.** COGS (#28) untuk HPP; kebersihan master item Omnicare.

---

## 30 · Slip gaji & insentif

**Pemohon:** HR — Admin_Nisa · **Jenjang usulan:** L · **Masuk:** Antre →
**Ditunda** (alasan: IT sedang fokus IT Tracker & modul Finance; dibuka lagi
sesudah komisi 70% dan jatah cuti selesai)

**Latar belakang / masalah saat ini.** Gaji dihitung dari beberapa sumber yang
kini sudah ada di Data Center — absensi (alpa, lembur normal & DODO), cuti,
komisi dokter 70% — tapi **slip per karyawan** masih disusun manual. Ada
"Lembar Gaji" sebagai laporan, belum ada slip yang bisa diberikan ke karyawan
dan direkap ke Finance.

**Kebutuhan dan hasil yang diharapkan.**
1. **Slip per karyawan per periode**: gaji pokok · lembur (dua tarif: normal
   dan DODO — jangan disatukan, pernah keliru) · potongan alpa/terlambat ·
   insentif · komisi (dokter 70%) · tunjangan.
2. Komponen absensi dan komisi **terisi sendiri** dari data yang sudah ada
   (Kelas B) — HR hanya mengisi yang memang manual (insentif, tunjangan).
3. **Aturan insentif** ditetapkan HR (belum ada dokumennya hari ini).
4. **Ekspor**: PDF per karyawan + rekap per cabang untuk Finance.
5. *(lanjut)* jurnal gaji ke Accurate.

**Pengguna & proses yang terdampak.** HR (penyusun), Finance (pembayar),
seluruh karyawan (penerima slip), dokter (komisi).

**Target waktu.** — *(diisi pemohon)*

**Ketergantungan.** Penyesuaian komisi 70% (#09) · Jatah cuti (#10) · absensi
stabil (#01).
