-- ============================================================================
-- DEV menyusul PROD: akses menu role Front Office
--
-- Temuan 13 Agu 2026: role Front Office di DEV cuma punya 3 node (semuanya
-- "kerjaan pribadi": my-checklist, akun, persetujuan) sehingga menu utamanya
-- kosong. Di PROD role yang sama punya 5 menu utama — Reservation, Pasien,
-- Doctor, Laporan Penjualan, Dashboard Sales. Dev-lah yang tertinggal.
--
-- Ini penting bukan cuma soal tampilan: §5 rencana gelar Cinere-Pamulang
-- bertumpu pada "FO yang ditempatkan di cabang lain melihat reservasi cabang
-- yang salah". Selama dev tak punya menu Reservation, perbaikan itu tak bisa
-- dibuktikan di dev sama sekali.
--
-- Aman diulang. Aman juga kalau tak sengaja dijalankan di prod: semua baris
-- di sana sudah ada, jadi NOT EXISTS membuatnya jadi 0 baris.
--
-- ⚠️ SESUDAH menjalankan ini WAJIB: php artisan access:rebuild
-- hasMenuAccess() membaca berkas storage/app/group_access/<groupId>.txt yang
-- DI-CACHE — tanpa rebuild, menunya tetap kosong walau grant-nya sudah masuk
-- (jebakan yang pernah kena 2026-06-30 di Executive Report).
--
-- CATATAN: dua node prod TIDAK ikut — "Dashboard" (wadah) dan "Dashboard Sales"
-- (link sales-dashboard) memang belum pernah dibuat di access_path dev. Itu
-- utang lama Sales Dashboard (langkah manual menu/akses dev belum dikerjakan),
-- bukan bagian penggelaran ini. Jadi dev dapat 4 dari 5 menu utama prod.
-- ============================================================================

INSERT INTO group_access (id, group_id, access_id)
SELECT UUID(), 'a245532a-d3e5-4d1d-8b48-3d1cad4dcb7f', ap.id
FROM access_path ap
WHERE ap.id IN (
    -- wadah (tanpa link, dipakai sebagai judul section)
    '32361898-c607-11ec-9240-809133ff6caa',  -- Data Center
    '452446c4-23cd-4964-a539-087010d8add3',  -- Reservation Data Center
    'a1e50b67-8f97-42e3-88f4-453978c84e62',  -- Report
    -- halaman
    '478bae36-6a93-4c40-9505-89e215546e39',  -- Reservation      → rawreservation
    '66d9388c-dc7d-42f8-9770-ac990f42cfa0',  -- Pasien           → rawcustomer
    '5c552e98-339c-44f1-a394-0c4a4da3929b',  -- Doctor           → rawdoctor
    'a1e50da5-c99b-4058-b2f7-f768080f754a'   -- Laporan Penjualan → report
)
AND NOT EXISTS (
    SELECT 1 FROM group_access ga
    WHERE ga.group_id  = 'a245532a-d3e5-4d1d-8b48-3d1cad4dcb7f'
      AND ga.access_id = ap.id
);

-- Halaman pendaratan sesudah login: prod mengarahkan FO ke Reservation,
-- dev masih ke Checklist Saya. (Dari HP tetap mendarat di Absensi Saya —
-- landingUntukPerangkat yang mengurus itu, tak terpengaruh.)
UPDATE group_path
SET landing_page = 'rawreservation'
WHERE id = 'a245532a-d3e5-4d1d-8b48-3d1cad4dcb7f'
  AND landing_page <> 'rawreservation';
