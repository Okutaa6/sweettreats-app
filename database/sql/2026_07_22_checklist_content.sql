-- ============================================================================
-- Checklist content — katalog kategori + task harian per divisi (GS/FO/Asdent)
-- ----------------------------------------------------------------------------
-- Konten SOP klinik untuk mengisi "Checklist Saya". cadence=daily, raw_branch_id
-- NULL (berlaku semua cabang). Bootstrap items disnapshot dari katalog ini saat
-- karyawan buka /my-checklist (lihat MyChecklistController::bootstrapItems).
--
-- ID deterministik (cat-*/task-*) supaya re-run idempoten & bisa direferensikan.
-- Editable belakangan lewat UI Setup Checklist (DigitalChecklistController).
-- Seed 2026-07-22 (feature/absensi, local dulu).
-- ============================================================================

START TRANSACTION;

-- Bersihkan dulu (idempoten): hapus task lalu kategori dengan id yang dikelola seed ini.
DELETE FROM sys_checklist_task     WHERE category_id LIKE 'cat-gs-%'  OR category_id LIKE 'cat-fo-%'  OR category_id LIKE 'cat-asd-%';
DELETE FROM sys_checklist_category WHERE id         LIKE 'cat-gs-%'  OR id         LIKE 'cat-fo-%'  OR id         LIKE 'cat-asd-%';

-- ── Kategori ────────────────────────────────────────────────────────────────
INSERT INTO sys_checklist_category
  (id, division_id, raw_branch_id, name, description, cadence, sort_order, is_active, deleted_at, created_at, updated_at)
VALUES
  ('cat-gs-buka',      'GS',     NULL, 'Buka Klinik (Pagi)',      NULL, 'daily', 1, 1, NULL, NOW(), NULL),
  ('cat-gs-harian',    'GS',     NULL, 'Perawatan Harian',        NULL, 'daily', 2, 1, NULL, NOW(), NULL),
  ('cat-gs-tutup',     'GS',     NULL, 'Tutup Klinik (Sore)',     NULL, 'daily', 3, 1, NULL, NOW(), NULL),

  ('cat-fo-buka',      'FO',     NULL, 'Persiapan Buka',          NULL, 'daily', 1, 1, NULL, NOW(), NULL),
  ('cat-fo-ops',       'FO',     NULL, 'Operasional Pasien',      NULL, 'daily', 2, 1, NULL, NOW(), NULL),
  ('cat-fo-tutup',     'FO',     NULL, 'Tutup',                   NULL, 'daily', 3, 1, NULL, NOW(), NULL),

  ('cat-asd-persiapan','ASDENT', NULL, 'Persiapan Ruang Dental',  NULL, 'daily', 1, 1, NULL, NOW(), NULL),
  ('cat-asd-praktik',  'ASDENT', NULL, 'Selama Praktik',          NULL, 'daily', 2, 1, NULL, NOW(), NULL),
  ('cat-asd-tutup',    'ASDENT', NULL, 'Tutup Ruang',             NULL, 'daily', 3, 1, NULL, NOW(), NULL);

-- ── Task ────────────────────────────────────────────────────────────────────
INSERT INTO sys_checklist_task
  (id, category_id, title, description, day_of_week, requires_note, requires_photo, sort_order, is_active, deleted_at, created_at, updated_at)
VALUES
  -- GS
  ('task-gs-buka-1',  'cat-gs-buka',   'Nyalakan listrik & AC semua ruangan',             NULL, NULL, 0, 0, 1, 1, NULL, NOW(), NULL),
  ('task-gs-buka-2',  'cat-gs-buka',   'Buka pintu & pagar, cek kebersihan area tunggu & toilet', NULL, NULL, 0, 0, 2, 1, NULL, NOW(), NULL),
  ('task-gs-buka-3',  'cat-gs-buka',   'Isi ulang galon/dispenser air',                   NULL, NULL, 0, 0, 3, 1, NULL, NOW(), NULL),
  ('task-gs-har-1',   'cat-gs-harian', 'Cek stok ATK & keperluan umum',                   NULL, NULL, 1, 0, 1, 1, NULL, NOW(), NULL),
  ('task-gs-har-2',   'cat-gs-harian', 'Buang sampah medis & umum',                       NULL, NULL, 0, 0, 2, 1, NULL, NOW(), NULL),
  ('task-gs-tut-1',   'cat-gs-tutup',  'Matikan listrik/AC ruangan tak terpakai, kunci pintu & pagar', NULL, NULL, 0, 0, 1, 1, NULL, NOW(), NULL),
  ('task-gs-tut-2',   'cat-gs-tutup',  'Cek kompresor & genset',                          NULL, NULL, 0, 0, 2, 1, NULL, NOW(), NULL),

  -- FO
  ('task-fo-buka-1',  'cat-fo-buka',   'Nyalakan komputer & sistem antrian',              NULL, NULL, 0, 0, 1, 1, NULL, NOW(), NULL),
  ('task-fo-buka-2',  'cat-fo-buka',   'Cek jadwal pasien hari ini',                      NULL, NULL, 0, 0, 2, 1, NULL, NOW(), NULL),
  ('task-fo-buka-3',  'cat-fo-buka',   'Siapkan kas kecil / kembalian',                   NULL, NULL, 0, 0, 3, 1, NULL, NOW(), NULL),
  ('task-fo-ops-1',   'cat-fo-ops',    'Konfirmasi pasien (WhatsApp/telepon)',            NULL, NULL, 0, 0, 1, 1, NULL, NOW(), NULL),
  ('task-fo-ops-2',   'cat-fo-ops',    'Update status reservasi di sistem',               NULL, NULL, 0, 0, 2, 1, NULL, NOW(), NULL),
  ('task-fo-ops-3',   'cat-fo-ops',    'Input pembayaran & cetak kwitansi',               NULL, NULL, 0, 0, 3, 1, NULL, NOW(), NULL),
  ('task-fo-tut-1',   'cat-fo-tutup',  'Rekap kas harian & cocokkan',                     NULL, NULL, 1, 0, 1, 1, NULL, NOW(), NULL),
  ('task-fo-tut-2',   'cat-fo-tutup',  'Backup laporan harian',                           NULL, NULL, 0, 0, 2, 1, NULL, NOW(), NULL),

  -- Asdent
  ('task-asd-pre-1',  'cat-asd-persiapan', 'Nyalakan dental unit & kompresor',            NULL, NULL, 0, 0, 1, 1, NULL, NOW(), NULL),
  ('task-asd-pre-2',  'cat-asd-persiapan', 'Cek & isi bahan (glove, masker, cotton)',     NULL, NULL, 0, 0, 2, 1, NULL, NOW(), NULL),
  ('task-asd-pre-3',  'cat-asd-persiapan', 'Sterilkan instrumen (autoclave)',             NULL, NULL, 0, 0, 3, 1, NULL, NOW(), NULL),
  ('task-asd-pra-1',  'cat-asd-praktik',   'Siapkan tray sesuai jadwal tindakan',         NULL, NULL, 0, 0, 1, 1, NULL, NOW(), NULL),
  ('task-asd-pra-2',  'cat-asd-praktik',   'Dampingi dokter tiap tindakan',               NULL, NULL, 0, 0, 2, 1, NULL, NOW(), NULL),
  ('task-asd-pra-3',  'cat-asd-praktik',   'Catat pemakaian bahan habis pakai',           NULL, NULL, 1, 0, 3, 1, NULL, NOW(), NULL),
  ('task-asd-tut-1',  'cat-asd-tutup',     'Bersihkan & sterilkan instrumen',             NULL, NULL, 0, 0, 1, 1, NULL, NOW(), NULL),
  ('task-asd-tut-2',  'cat-asd-tutup',     'Matikan dental unit & kompresor',             NULL, NULL, 0, 0, 2, 1, NULL, NOW(), NULL),
  ('task-asd-tut-3',  'cat-asd-tutup',     'Rapikan & restock ruangan',                   NULL, NULL, 0, 0, 3, 1, NULL, NOW(), NULL);

COMMIT;
