  -- =====================================================================
  -- Cekat API — Kolom `code` di raw_doc_service — 2026-05-16
  -- Jalankan di phpMyAdmin pada DB `sweettreats` (lokal) / `u695655413_app_sweettreat` (prod)
  --
  -- Tujuan: vendor (Cekat) mengirim/membaca treatment via KODE (mis. SVC001),
  -- bukan nama bebas. Endpoint service_range & customer_profile memetakan
  -- kode -> service utama lewat kolom ini.
  --
  -- Catatan:
  --  - raw_doc_service_detail SUDAH punya `code_service` + `price` + `is_active`
  --    (dipakai utk hitung min/max harga). Kolom `code` ini di tabel INDUK.
  --  - Kode aplikasi harus tahan `code` NULL (belum semua service diberi kode).
  -- =====================================================================

  ALTER TABLE `raw_doc_service`
    ADD COLUMN `code` VARCHAR(50) NULL DEFAULT NULL
      COMMENT 'Kode treatment utk integrasi vendor (Cekat), mis. SVC001. NULL = belum dipetakan'
      AFTER `name`;

  ALTER TABLE `raw_doc_service`
    ADD UNIQUE KEY `uniq_doc_service_code` (`code`);

  -- =====================================================================
  -- Mapping kode treatment (disepakati 2026-05-16, sesuai data master lokal).
  -- Kode bebas diubah kapan saja via UPDATE (mis. jadi 'TMBL0126') —
  -- ini hanya identifier kontrak dengan vendor, bukan struktur.
  -- Jalankan blok ini di produksi setelah ALTER di atas.
  -- =====================================================================
  UPDATE `raw_doc_service` SET `code` = 'SVC001' WHERE `name` = 'Kontrol Behel';
  UPDATE `raw_doc_service` SET `code` = 'SVC002' WHERE `name` = 'Pemasangan Behel';
  UPDATE `raw_doc_service` SET `code` = 'SVC003' WHERE `name` = 'Konsultasi';
  UPDATE `raw_doc_service` SET `code` = 'SVC004' WHERE `name` = 'PSA (Perawatan Saluran Akar)';
  UPDATE `raw_doc_service` SET `code` = 'SVC005' WHERE `name` = 'Odontectomy';
  UPDATE `raw_doc_service` SET `code` = 'SVC006' WHERE `name` = 'Penambalan';
  UPDATE `raw_doc_service` SET `code` = 'SVC007' WHERE `name` = 'Scaling';
  UPDATE `raw_doc_service` SET `code` = 'SVC008' WHERE `name` = 'Whitening';
  UPDATE `raw_doc_service` SET `code` = 'SVC009' WHERE `name` = 'Orthodonti';
  UPDATE `raw_doc_service` SET `code` = 'SVC010' WHERE `name` = 'Pencabutan';
  UPDATE `raw_doc_service` SET `code` = 'SVC011' WHERE `name` = 'Lainnya';

  -- Cek hasil: SELECT id, name, code FROM raw_doc_service ORDER BY sort_order;
