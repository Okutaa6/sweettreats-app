-- =====================================================================
-- Cekat API — Tabel Sandbox (_dev) — 2026-05-16
-- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
--
-- Tujuan: endpoint sandbox (apicekatdev/*) menulis ke tabel kembar `_dev`
-- supaya data uji vendor TIDAK mengotori data klinik asli (KPI, jadwal
-- dokter, laporan). Hanya 5 tabel "write-sink" yang diduplikasi; endpoint
-- BACA (service_range, jadwal_dokter) tetap baca tabel asli.
--
-- `CREATE TABLE ... LIKE` menyalin kolom + index + default PERSIS dari tabel
-- asli, sehingga anti schema-drift saat dibuat. Jika tabel asli di-ALTER di
-- masa depan, tabel `_dev` HARUS di-re-sync (drop lalu buat ulang — lihat
-- blok komentar di bawah; default tidak aktif agar tak hapus data uji).
-- =====================================================================

CREATE TABLE `raw_contact_dev`          LIKE `raw_contact`;
CREATE TABLE `raw_customer_dev`         LIKE `raw_customer`;
CREATE TABLE `raw_cus_journey_dev`      LIKE `raw_cus_journey`;
CREATE TABLE `raw_cus_reservation_dev`  LIKE `raw_cus_reservation`;
CREATE TABLE `raw_cus_res_service_dev`  LIKE `raw_cus_res_service`;

-- =====================================================================
-- RE-SYNC (jalankan HANYA bila tabel asli berubah struktur).
-- PERINGATAN: ini menghapus seluruh data uji di tabel _dev.
-- Uncomment lalu jalankan:
-- =====================================================================
-- DROP TABLE IF EXISTS `raw_contact_dev`;
-- DROP TABLE IF EXISTS `raw_customer_dev`;
-- DROP TABLE IF EXISTS `raw_cus_journey_dev`;
-- DROP TABLE IF EXISTS `raw_cus_reservation_dev`;
-- DROP TABLE IF EXISTS `raw_cus_res_service_dev`;
-- (lalu jalankan kembali blok CREATE TABLE ... LIKE di atas)
