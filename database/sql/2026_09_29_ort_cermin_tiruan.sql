-- ════════════════════════════════════════════════════════════════════════════
-- Cermin rekam medis TIRUAN untuk menguji `ort:seed-cases` — 2026-09-29
-- ⚠️ HANYA UNTUK DB LOKAL. Di produksi berkas ini jadi no-op total.
-- ════════════════════════════════════════════════════════════════════════════
-- IDEMPOTENT: bagian 1 menghapus jejak sebelumnya, jadi aman dijalankan
-- berulang kali dan hasilnya selalu sama. Tanggal RELATIF ke CURDATE(), jadi
-- properti "400 hari lalu" tetap benar kapan pun dijalankan.
--
-- DB tujuan: LOKAL `sweettreats` saja.
--            Prod bernama `u695655413_app_sweettreat` → seluruh perintah di
--            sini dikunci `WHERE @boleh = 1` dan @boleh hanya 1 kalau nama
--            database persis 'sweettreats'.
--
-- ── Konteks ─────────────────────────────────────────────────────────────────
-- `ort:seed-cases` membangun ort_case + riwayat kontrolnya dengan membaca
-- omnicare_sales_detail dan omnicare_patient. Di DB lokal kedua tabel itu
-- KOSONG (0 baris) dan `.env` lokal tidak punya kredensial Omnicare, jadi
-- `omnicare:sync` tidak bisa dijalankan. Tanpa berkas ini penyemai tidak bisa
-- dibuktikan sama sekali sebelum menyentuh produksi — padahal ia perintah yang
-- menulis ribuan baris sekaligus.
--
-- Kenapa SQL, bukan artisan command: menulis ke omnicare_* dari PHP akan
-- tampak seperti penulis kedua yang bersaing dengan `omnicare:sync`. Presedennya
-- sudah ada di repo ini: database/sql/2026_08_28_komisi_dokter_seed_dummy.sql.
--
-- ── Yang dilakukan ──────────────────────────────────────────────────────────
-- Menyemai 12 pasien tiruan yang tiap-tiapnya MENGUJI SATU KEPUTUSAN penyemai:
--
--   SIM-0001  lurus: pasang promo lalu kontrol rutin      → 1 episode, tanggal PASTI
--   SIM-0002  lepas lalu pasang lagi (Freedom 2)          → 2 episode, ep1 selesai
--   SIM-0003  tanpa baris pemasangan sama sekali          → 1 episode, tanggal DIPERKIRAKAN
--   SIM-0004  cuma beli Ortho Wax & tempat retainer       → TIDAK jadi kasus
--   SIM-0005  pasang Bintaro, dua kontrol terakhir Sunter → owner_place_id jadi Sunter
--   SIM-0006  "Lepas Bracket" tanpa pemasangan terlihat   → TIDAK jadi kasus
--   (kosong)  p_id kosong                                  → dihitung terpisah, tidak meledak
--   SIM-0008  tiga baris di SATU hari, trx terkecil justru
--             barang penyerta                              → 1 kunjungan, wakil = baris KONTROL
--   SIM-0009  Scaling & Tambal saja                        → tidak muncul di mana pun
--   SIM-0010  Stellar Ceramic, kontrol berikutnya "Metal"  → bracket_type tetap Ceramic
--   SIM-0011  "Pasang Bracket Atas"                        → bracket_arch = atas
--   SIM-0012  tidak ada di omnicare_patient                → nama jatuh ke p_name
--
-- Semua baris penjualan bertanda `sales_notes = 'SIMULASI-ORTHO'` dan semua
-- pasien ber-pid awalan `SIM-`. Itu satu-satunya cara membersihkannya.
--
-- ── Yang SENGAJA TIDAK dilakukan ────────────────────────────────────────────
--   • TIDAK menyentuh ort_* sama sekali. Berkas ini hanya mengisi CERMIN;
--     yang harus mengisi ort_* adalah `ort:seed-cases` — justru itu yang diuji.
--     (Untuk sekadar melihat halaman tanpa rantai nyata: `ort:seed-demo`.)
--   • TIDAK memakai RAND(). Angka yang Anda lihat hari ini sama dengan besok.
--   • TIDAK dijalankan `deploy.sh`. Jalankan sendiri, di lokal saja.
-- ════════════════════════════════════════════════════════════════════════════

SET @boleh := IF(DATABASE() = 'sweettreats', 1, 0);
SET @t     := CURDATE();

-- Cabang. Harus sama dengan raw_branch.omnicare_place_id di DB ini.
SET @bintaro := '29957';
SET @sunter  := '29958';


-- ── 1. Bersihkan jejak sebelumnya ──────────────────────────────────────────
DELETE FROM omnicare_sales_detail  WHERE @boleh = 1 AND sales_notes  = 'SIMULASI-ORTHO';
DELETE FROM customer_omnicare_link WHERE @boleh = 1 AND omnicare_pid LIKE 'SIM-%';
DELETE FROM omnicare_patient       WHERE @boleh = 1 AND pid          LIKE 'SIM-%';


-- ── 2. Identitas pasien ────────────────────────────────────────────────────
-- SIM-0012 SENGAJA tidak ada di sini: penyemai harus jatuh ke p_name dari baris
-- penjualan. Kasus tanpa nama tidak bisa dicari Sales, jadi rantai cadangan itu
-- wajib terbukti bekerja.
INSERT INTO omnicare_patient (pid, full_name, mobile_number)
SELECT * FROM (
              SELECT 'SIM-0001' AS pid, 'Simulasi Lurus'          AS full_name, '081200000001' AS mobile_number
    UNION ALL SELECT 'SIM-0002',        'Simulasi Dua Episode',        '081200000002'
    UNION ALL SELECT 'SIM-0003',        'Simulasi Tanpa Pemasangan',   '081200000003'
    UNION ALL SELECT 'SIM-0004',        'Simulasi Beli Aksesori',      '081200000004'
    UNION ALL SELECT 'SIM-0005',        'Simulasi Lintas Cabang',      '081200000005'
    UNION ALL SELECT 'SIM-0006',        'Simulasi Lepas Saja',         '081200000006'
    UNION ALL SELECT 'SIM-0008',        'Simulasi Satu Hari Tiga',     '081200000008'
    UNION ALL SELECT 'SIM-0009',        'Simulasi Bukan Ortho',        '081200000009'
    UNION ALL SELECT 'SIM-0010',        'Simulasi Ceramic',            '081200000010'
    UNION ALL SELECT 'SIM-0011',        'Simulasi Rahang Atas',        '081200000011'
) s WHERE @boleh = 1;


-- ── 3. Satu tautan ke raw_customer ─────────────────────────────────────────
-- Menguji pengisian ort_case.raw_customer_id. Kalau raw_customer kosong,
-- SELECT ini menghasilkan nol baris dan berkas tetap jalan.
INSERT INTO customer_omnicare_link (id, raw_customer_id, omnicare_pid, match_confidence, match_method)
SELECT 'SIM-LINK-0001', c.id, 'SIM-0001', 'manual', 'simulasi'
FROM raw_customer c
WHERE @boleh = 1
ORDER BY c.id
LIMIT 1;


-- ── 4. Baris penjualan (cermin rekam medis) ────────────────────────────────
-- Nama item & kode diambil dari katalog produksi NYATA yang terdokumentasi di
-- docs/arsip/laporan-lama/audit-sales-mar-2026.md dan
-- docs/arsip/accurate/mapping-akun-accurate.csv — bukan karangan. Kalau
-- OrtItemRules salah membacanya, salahnya kelihatan di sini, bukan di prod.
INSERT INTO omnicare_sales_detail
    (trx_id, trx_date, p_id, p_name, place_id, branch_name,
     item_name, item_extra_name, item_code, encounter_id, doctor, doc_reff_id,
     sales_notes, qty_sold, net_sell_price)
SELECT * FROM (
    -- SIM-0001 — lurus: pasang promo, lalu kontrol tiap ±30 hari.
              SELECT 'SIM-0001-1' AS trx_id, DATE_SUB(@t, INTERVAL 400 DAY) AS trx_date, 'SIM-0001' AS p_id, 'Simulasi Lurus' AS p_name, @bintaro AS place_id, 'Bintaro' AS branch_name,
                     'WAR PROMO 8.8 - Signature Metal Braces' AS item_name, '' AS item_extra_name, '25PM145' AS item_code,
                     'ENC-0001-1' AS encounter_id, 'drg. Simulasi A' AS doctor, 'DOC-A' AS doc_reff_id,
                     'SIMULASI-ORTHO' AS sales_notes, 1 AS qty_sold, 6500000 AS net_sell_price
    UNION ALL SELECT 'SIM-0001-2', DATE_SUB(@t, INTERVAL 370 DAY), 'SIM-0001', 'Simulasi Lurus', @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0001-2', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 250000
    UNION ALL SELECT 'SIM-0001-3', DATE_SUB(@t, INTERVAL 340 DAY), 'SIM-0001', 'Simulasi Lurus', @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0001-3', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 250000
    UNION ALL SELECT 'SIM-0001-4', DATE_SUB(@t, INTERVAL 310 DAY), 'SIM-0001', 'Simulasi Lurus', @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0001-4', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 250000
    UNION ALL SELECT 'SIM-0001-5', DATE_SUB(@t, INTERVAL 280 DAY), 'SIM-0001', 'Simulasi Lurus', @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0001-5', 'drg. Simulasi B', 'DOC-B', 'SIMULASI-ORTHO', 1, 250000

    -- SIM-0002 — dua episode. Freedom 2 menutup yang pertama; 540 hari kemudian
    -- pasang lagi. Menguji `uq_ort_case_pid (omnicare_pid, install_date)`.
    UNION ALL SELECT 'SIM-0002-1', DATE_SUB(@t, INTERVAL 900 DAY), 'SIM-0002', 'Simulasi Dua Episode', @bintaro, 'Bintaro', 'Deluxe Metal Braces', '', NULL, 'ENC-0002-1', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 5900000
    UNION ALL SELECT 'SIM-0002-2', DATE_SUB(@t, INTERVAL 870 DAY), 'SIM-0002', 'Simulasi Dua Episode', @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0002-2', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 250000
    UNION ALL SELECT 'SIM-0002-3', DATE_SUB(@t, INTERVAL 840 DAY), 'SIM-0002', 'Simulasi Dua Episode', @bintaro, 'Bintaro', 'Freedom 2', '', '26PK167', 'ENC-0002-3', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 4900000
    UNION ALL SELECT 'SIM-0002-4', DATE_SUB(@t, INTERVAL 300 DAY), 'SIM-0002', 'Simulasi Dua Episode', @bintaro, 'Bintaro', 'Signature Metal Braces', '', '25PM145', 'ENC-0002-4', 'drg. Simulasi B', 'DOC-B', 'SIMULASI-ORTHO', 1, 6500000
    UNION ALL SELECT 'SIM-0002-5', DATE_SUB(@t, INTERVAL 270 DAY), 'SIM-0002', 'Simulasi Dua Episode', @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0002-5', 'drg. Simulasi B', 'DOC-B', 'SIMULASI-ORTHO', 1, 250000

    -- SIM-0003 — tidak ada satu pun baris pemasangan. Inilah mayoritas pasien
    -- nyata: nama promo pemasangannya tidak tertangkap pola apa pun, atau
    -- pemasangannya terjadi sebelum rentang pindai. Tanggal pasang DIPERKIRAKAN.
    UNION ALL SELECT 'SIM-0003-1', DATE_SUB(@t, INTERVAL 300 DAY), 'SIM-0003', 'Simulasi Tanpa Pemasangan', @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0003-1', 'drg. Simulasi C', 'DOC-C', 'SIMULASI-ORTHO', 1, 250000
    UNION ALL SELECT 'SIM-0003-2', DATE_SUB(@t, INTERVAL 270 DAY), 'SIM-0003', 'Simulasi Tanpa Pemasangan', @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0003-2', 'drg. Simulasi C', 'DOC-C', 'SIMULASI-ORTHO', 1, 250000
    UNION ALL SELECT 'SIM-0003-3', DATE_SUB(@t, INTERVAL 240 DAY), 'SIM-0003', 'Simulasi Tanpa Pemasangan', @bintaro, 'Bintaro', 'Control Self Ligating', '', '26CON192', 'ENC-0003-3', 'drg. Simulasi C', 'DOC-C', 'SIMULASI-ORTHO', 1, 300000

    -- SIM-0004 — cuma belanja. Tidak boleh jadi pasien behel.
    UNION ALL SELECT 'SIM-0004-1', DATE_SUB(@t, INTERVAL 100 DAY), 'SIM-0004', 'Simulasi Beli Aksesori', @bintaro, 'Bintaro', 'Ortho Wax', '', '26PO300', 'ENC-0004-1', NULL, NULL, 'SIMULASI-ORTHO', 1, 35000
    UNION ALL SELECT 'SIM-0004-2', DATE_SUB(@t, INTERVAL  80 DAY), 'SIM-0004', 'Simulasi Beli Aksesori', @bintaro, 'Bintaro', 'Tempat Retainer', '', NULL, 'ENC-0004-2', NULL, NULL, 'SIMULASI-ORTHO', 1, 50000

    -- SIM-0005 — pasang di Bintaro, dua kontrol terakhir di Sunter.
    -- install_place_id harus Bintaro, owner_place_id harus Sunter.
    UNION ALL SELECT 'SIM-0005-1', DATE_SUB(@t, INTERVAL 250 DAY), 'SIM-0005', 'Simulasi Lintas Cabang', @bintaro, 'Bintaro', 'Signature Metal Braces', '', '25PM145', 'ENC-0005-1', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 6500000
    UNION ALL SELECT 'SIM-0005-2', DATE_SUB(@t, INTERVAL 220 DAY), 'SIM-0005', 'Simulasi Lintas Cabang', @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0005-2', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 250000
    UNION ALL SELECT 'SIM-0005-3', DATE_SUB(@t, INTERVAL 190 DAY), 'SIM-0005', 'Simulasi Lintas Cabang', @sunter,  'Sunter',  'Control Metal Braces', '', '26CON178', 'ENC-0005-3', 'drg. Simulasi D', 'DOC-D', 'SIMULASI-ORTHO', 1, 250000
    UNION ALL SELECT 'SIM-0005-4', DATE_SUB(@t, INTERVAL 160 DAY), 'SIM-0005', 'Simulasi Lintas Cabang', @sunter,  'Sunter',  'Control Metal Braces', '', '26CON178', 'ENC-0005-4', 'drg. Simulasi D', 'DOC-D', 'SIMULASI-ORTHO', 1, 250000

    -- SIM-0006 — pelepasan tanpa pemasangan yang pernah terlihat. Episode yang
    -- lahir dan mati di hari yang sama itu bukan perawatan, cuma derau.
    UNION ALL SELECT 'SIM-0006-1', DATE_SUB(@t, INTERVAL 100 DAY), 'SIM-0006', 'Simulasi Lepas Saja', @bintaro, 'Bintaro', 'Lepas Bracket', '', NULL, 'ENC-0006-1', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 1500000

    -- p_id KOSONG — nyata di data prod. Harus dihitung, bukan meledak.
    UNION ALL SELECT 'SIM-0007-1', DATE_SUB(@t, INTERVAL  50 DAY), '',         'Tanpa Nomor RM',        @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0007-1', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 250000

    -- SIM-0008 — TIGA baris di satu hari. Urut trx_id, yang PERTAMA adalah
    -- barang penyerta (A-900 < B-100 secara leksikal). Aturan wakil harus
    -- memilih baris KONTROL (B-100), bukan yang kebetulan pertama.
    UNION ALL SELECT 'SIM-0008-A-900', DATE_SUB(@t, INTERVAL 90 DAY), 'SIM-0008', 'Simulasi Satu Hari Tiga', @bintaro, 'Bintaro', 'Kawat Self Ligating',  '', '26PO226', 'ENC-0008', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 150000
    UNION ALL SELECT 'SIM-0008-B-100', DATE_SUB(@t, INTERVAL 90 DAY), 'SIM-0008', 'Simulasi Satu Hari Tiga', @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0008', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 250000
    UNION ALL SELECT 'SIM-0008-C-500', DATE_SUB(@t, INTERVAL 90 DAY), 'SIM-0008', 'Simulasi Satu Hari Tiga', @bintaro, 'Bintaro', 'Orthokit',            '', 'ORTKIT-001', 'ENC-0008', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 85000

    -- SIM-0009 — tidak ada urusan ortho sama sekali.
    UNION ALL SELECT 'SIM-0009-1', DATE_SUB(@t, INTERVAL 120 DAY), 'SIM-0009', 'Simulasi Bukan Ortho', @bintaro, 'Bintaro', 'Scaling kls 3',   '', '26SC264', 'ENC-0009-1', 'drg. Simulasi E', 'DOC-E', 'SIMULASI-ORTHO', 1, 450000
    UNION ALL SELECT 'SIM-0009-2', DATE_SUB(@t, INTERVAL 100 DAY), 'SIM-0009', 'Simulasi Bukan Ortho', @bintaro, 'Bintaro', 'Tambal Komposit', '', '26TB100', 'ENC-0009-2', 'drg. Simulasi E', 'DOC-E', 'SIMULASI-ORTHO', 1, 350000

    -- SIM-0010 — pasang Ceramic, kontrol berikutnya bernama "Metal".
    -- bracket_type harus tetap Ceramic: yang pertama menyebut yang menang.
    UNION ALL SELECT 'SIM-0010-1', DATE_SUB(@t, INTERVAL 150 DAY), 'SIM-0010', 'Simulasi Ceramic', @bintaro, 'Bintaro', 'Stellar Ceramic Braces Spesialis', '', '26OR001', 'ENC-0010-1', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 12000000
    UNION ALL SELECT 'SIM-0010-2', DATE_SUB(@t, INTERVAL 120 DAY), 'SIM-0010', 'Simulasi Ceramic', @bintaro, 'Bintaro', 'Control Metal Braces',             '', '26CON178', 'ENC-0010-2', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 250000

    -- SIM-0011 — rahang disebut di nama item.
    UNION ALL SELECT 'SIM-0011-1', DATE_SUB(@t, INTERVAL 180 DAY), 'SIM-0011', 'Simulasi Rahang Atas', @bintaro, 'Bintaro', 'Pasang Bracket Atas',  '', '26OT001', 'ENC-0011-1', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 4500000
    UNION ALL SELECT 'SIM-0011-2', DATE_SUB(@t, INTERVAL 150 DAY), 'SIM-0011', 'Simulasi Rahang Atas', @bintaro, 'Bintaro', 'Control Metal Braces', '', '26CON178', 'ENC-0011-2', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 250000

    -- SIM-0012 — tidak ada di omnicare_patient. Nama harus jatuh ke p_name,
    -- dan nomor telepon memang tidak ada (kartu tetap terender).
    UNION ALL SELECT 'SIM-0012-1', DATE_SUB(@t, INTERVAL 200 DAY), 'SIM-0012', 'Simulasi Tanpa Rekam Medis', @bintaro, 'Bintaro', 'Signature Metal Braces', '', '25PM145', 'ENC-0012-1', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 6500000
    UNION ALL SELECT 'SIM-0012-2', DATE_SUB(@t, INTERVAL 170 DAY), 'SIM-0012', 'Simulasi Tanpa Rekam Medis', @bintaro, 'Bintaro', 'Control Metal Braces',   '', '26CON178', 'ENC-0012-2', 'drg. Simulasi A', 'DOC-A', 'SIMULASI-ORTHO', 1, 250000
) s WHERE @boleh = 1;


-- ── 5. Ringkasan ───────────────────────────────────────────────────────────
SELECT
    IF(@boleh = 1, 'CERMIN TIRUAN DISEMAI', 'DILEWATI — bukan DB lokal') AS status,
    (SELECT COUNT(*) FROM omnicare_sales_detail WHERE sales_notes = 'SIMULASI-ORTHO') AS baris_penjualan,
    (SELECT COUNT(*) FROM omnicare_patient      WHERE pid LIKE 'SIM-%')               AS pasien,
    'Lanjut: php artisan ort:seed-cases  (pratinjau) lalu --commit'                   AS langkah_berikutnya;
