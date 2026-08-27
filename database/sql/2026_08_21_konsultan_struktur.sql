-- ============================================================================
-- Role Konsultan + restrukturisasi Supervisor
-- Rencana: docs/rencana/rencana-konsultan-struktur.md (ACC Ekky 2026-08-21)
--
-- Aman diulang (idempoten): semua INSERT ber-NOT EXISTS, semua UPDATE
-- berkondisi. Aman dijalankan di dev MAUPUN prod — tak ada satu pun id node
-- menu yang di-hardcode, karena beberapa id access_path BERBEDA antar
-- lingkungan (mis. "Dashboard Marketing": dev a2203f78, prod a21e0d15).
-- Akses dikloning dengan MEMBACA baris role induk di lingkungan itu sendiri.
--
-- SESUDAH menjalankan ini WAJIB:  php artisan access:rebuild
--    hasMenuAccess() membaca storage/app/group_access/<groupId>.txt yang
--    DI-CACHE. Tanpa rebuild, menunya tetap kosong walau grant-nya sudah masuk.
--    Lalu PASTIKAN mtime berkasnya benar-benar berubah.
--
-- File ini TIDAK memberi kemampuan, cuma MENU. Kemampuan dijaga nama role
--    yang di-hardcode di kode (RopService::ROLE_PENYETUJU, CutiController::BOLEH,
--    LembarGajiController::BOLEH, canSeeAllBranches(), isFinance(),
--    AturanKerjaController). Tanpa deploy kode pendampingnya, Konsultan Finance
--    melihat menu yang berujung 403 — kegagalan senyap. Jalankan berbarengan.
--
-- Id role baru sengaja tetap & terbaca (pola `role-dokter-000000000000`) supaya
-- dev dan prod memakai id yang SAMA: att_approval_rule tak punya FK ke
-- group_path, jadi id yang berbeda antar lingkungan = aturan yang diam-diam
-- tak me-rute ke mana pun.
--   role-konsultan-finance-0   role-konsultan-marketing
--   role-konsultan-it-000000   role-koordinator-gs-0000
-- ============================================================================


-- ============================================================================
-- 1. ROLE BARU (group_path)
--    landing_page 'akun' = layar absen; att_default_rule_id = Fleksibel,
--    dicari lewat NAMA karena id att_work_rule berbeda antar lingkungan
--    (Fleksibel kebetulan 3 di dua-duanya, tapi jangan bertaruh pada itu).
--    level diisi 0 dulu — dihitung ulang oleh langkah 6 (relevel).
-- ============================================================================

INSERT INTO group_path (id, nama, parent_id, parent_id_2, level, deskripsi, landing_page, theme, att_default_rule_id)
SELECT 'role-konsultan-finance-0', 'Konsultan Finance',
       (SELECT id FROM group_path g WHERE g.nama = 'Finance' LIMIT 1),
       NULL, 0,
       'Konsultan bidang Finance. Wajib absen. Tidak meng-ACC pengajuan siapa pun (nol baris di att_approval_rule) dan tidak mengajukan cuti/lembur. Pekerjaan Finance sendiri — termasuk menyetujui ROP — TETAP boleh (keputusan Ekky 2026-08-21).',
       'akun', 'theme-default',
       (SELECT id FROM att_work_rule w WHERE w.nama = 'Fleksibel' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM group_path x WHERE x.id = 'role-konsultan-finance-0');

INSERT INTO group_path (id, nama, parent_id, parent_id_2, level, deskripsi, landing_page, theme, att_default_rule_id)
SELECT 'role-konsultan-marketing', 'Konsultan Marketing',
       (SELECT id FROM group_path g WHERE g.nama = 'Head Marketing' LIMIT 1),
       NULL, 0,
       'Konsultan bidang Marketing. Wajib absen. Tidak meng-ACC pengajuan siapa pun dan tidak mengajukan cuti/lembur.',
       'akun', 'theme-default',
       (SELECT id FROM att_work_rule w WHERE w.nama = 'Fleksibel' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM group_path x WHERE x.id = 'role-konsultan-marketing');

INSERT INTO group_path (id, nama, parent_id, parent_id_2, level, deskripsi, landing_page, theme, att_default_rule_id)
SELECT 'role-konsultan-it-000000', 'Konsultan IT',
       (SELECT id FROM group_path g WHERE g.nama = 'Head IT' LIMIT 1),
       NULL, 0,
       'Konsultan bidang IT. Wajib absen. Tidak meng-ACC pengajuan siapa pun dan tidak mengajukan cuti/lembur. Alat keamanan (User/Role/Akses/Setting/WiFi Cabang) sengaja TIDAK ikut — lihat langkah 3b.',
       'akun', 'theme-default',
       (SELECT id FROM att_work_rule w WHERE w.nama = 'Fleksibel' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM group_path x WHERE x.id = 'role-konsultan-it-000000');

-- Koordinator GS — role struktural sungguhan (BUKAN konsultan): membawahi
-- General Services, mengajukan ke Supervisor, dan meninjau checklist GS.
-- att_default_rule_id disamakan dengan General Services (aturan 'GS').
INSERT INTO group_path (id, nama, parent_id, parent_id_2, level, deskripsi, landing_page, theme, att_default_rule_id)
SELECT 'role-koordinator-gs-0000', 'Koordinator GS',
       (SELECT id FROM group_path g WHERE g.nama = 'Supervisor' LIMIT 1),
       NULL, 0,
       'Koordinator General Services. Membawahi General Services; meninjau checklist divisi GS (bukan mengisinya).',
       'akun', 'theme-default',
       (SELECT id FROM att_work_rule w WHERE w.nama = 'GS' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM group_path x WHERE x.id = 'role-koordinator-gs-0000');


-- ============================================================================
-- 2. AKSES MENU — kloning dari role induk
--    Dibaca dari group_access role induk di lingkungan ini, jadi node yang
--    cuma ada di salah satu lingkungan ikut dengan sendirinya (mis. Lembar
--    Gaji cuma ada di dev; Data Center pada Head IT cuma ada di prod).
--    Node WADAH (link NULL) ikut terbawa — tanpa itu menunya tak punya judul
--    section dan anaknya tak ter-render.
-- ============================================================================

INSERT INTO group_access (id, group_id, access_id)
SELECT UUID(), 'role-konsultan-finance-0', ga.access_id
FROM group_access ga
WHERE ga.group_id = (SELECT id FROM group_path g WHERE g.nama = 'Finance' LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM group_access x
                  WHERE x.group_id = 'role-konsultan-finance-0' AND x.access_id = ga.access_id);

INSERT INTO group_access (id, group_id, access_id)
SELECT UUID(), 'role-konsultan-marketing', ga.access_id
FROM group_access ga
WHERE ga.group_id = (SELECT id FROM group_path g WHERE g.nama = 'Head Marketing' LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM group_access x
                  WHERE x.group_id = 'role-konsultan-marketing' AND x.access_id = ga.access_id);

INSERT INTO group_access (id, group_id, access_id)
SELECT UUID(), 'role-konsultan-it-000000', ga.access_id
FROM group_access ga
WHERE ga.group_id = (SELECT id FROM group_path g WHERE g.nama = 'Head IT' LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM group_access x
                  WHERE x.group_id = 'role-konsultan-it-000000' AND x.access_id = ga.access_id);


-- ── 2b. Yang sudah dipunyai role `Consultant` hari ini ikut dibawa, supaya
--        Hari/Rahmat/Haickal tak KEHILANGAN apa pun saat dipindah. Terutama
--        Laporan Penjualan → Executive: akses Hari ke sana disengaja dan sah
--        (dikonfirmasi Ekky 2026-08-21; stdc.id = domain LAMA perusahaan,
--        bukan pihak luar). Dicocokkan lewat NAMA role, jadi kalau lingkungan
--        ini belum punya role `Consultant` (dev) langkah ini jadi 0 baris.
INSERT INTO group_access (id, group_id, access_id)
SELECT UUID(), t.gid, ga.access_id
FROM group_access ga
CROSS JOIN (SELECT 'role-konsultan-finance-0' AS gid
            UNION ALL SELECT 'role-konsultan-marketing'
            UNION ALL SELECT 'role-konsultan-it-000000') t
WHERE ga.group_id = (SELECT id FROM group_path g WHERE g.nama = 'Consultant' LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM group_access x
                  WHERE x.group_id = t.gid AND x.access_id = ga.access_id);

-- Dev belum punya role `Consultant`, jadi 2b tak menghasilkan apa-apa di sana.
-- Beri jaring pengaman lewat id node (identik di dua lingkungan, sudah
-- diperiksa): Laporan Penjualan + Executive beserta wadahnya.
INSERT INTO group_access (id, group_id, access_id)
SELECT UUID(), t.gid, ap.id
FROM access_path ap
CROSS JOIN (SELECT 'role-konsultan-finance-0' AS gid
            UNION ALL SELECT 'role-konsultan-marketing'
            UNION ALL SELECT 'role-konsultan-it-000000') t
WHERE ap.id IN (
        '32361898-c607-11ec-9240-809133ff6caa',  -- Data Center       (wadah)
        'a1e50b67-8f97-42e3-88f4-453978c84e62',  -- Report            (wadah)
        'a1e50da5-c99b-4058-b2f7-f768080f754a',  -- Laporan Penjualan → report
        'a1f30730-a6da-4f8d-bb12-ba61d65f643f'   -- Executive         → report/executive
      )
  AND NOT EXISTS (SELECT 1 FROM group_access x
                  WHERE x.group_id = t.gid AND x.access_id = ap.id);


-- ── 3b. PANGKAS alat keamanan dari Konsultan IT.
--        Keputusan Ekky 2026-08-21: "penuh, kecuali yang sensitif". Head IT
--        membawa Pusat Akun (User/Role/Akses/Setting) dan WiFi Cabang; itu
--        alat keamanan, bukan kerjaan bidang — dan tak satu pun dijaga gerbang
--        nama-role, jadi node-nya SAJA sudah memberi kuasanya penuh:
--        `setusermanagement`/`setgroup`/`setaccess` = bisa membuat akun dan
--        memberi dirinya sendiri akses apa pun; `absensi/wifi` = saksi WiFi
--        absensi. DIBUANG, bukan dibiarkan lalu 403 — menu yang tampil tapi
--        403 adalah kegagalan senyap yang justru diburu detektor Pusat Akun.
--        Kesehatan (setkesehatan) TETAP: cuma route GET, papan diagnosa.
--        Kalau Ekky mau mengembalikannya, hapus link-nya dari daftar ini.
DELETE ga FROM group_access ga
JOIN access_path ap ON ap.id = ga.access_id COLLATE utf8mb4_unicode_ci
WHERE ga.group_id = 'role-konsultan-it-000000'
  AND ap.link IN ('setusermanagement', 'setgroup', 'setaccess', 'setsystemprofile', 'absensi/wifi');


-- ── 3b-2. PENGAJUAN ROP dicabut dari KETIGA role konsultan.
--         Keputusan Ekky 2026-08-22: "konsultan ga bisa melakukan pengajuan".
--         Kloning menu dari role induk diam-diam membawa node `pengajuan` —
--         role `Consultant` lama TIDAK punya itu, jadi ini kemampuan yang tak
--         sengaja diberikan, bukan yang sudah ada. `PengajuanController::store()`
--         tak bergerbang nama-role, jadi node-nya saja = bisa mengajukan uang.
--         Pagar kodenya ada di PengajuanController::tolakPengamat() (isPengamat);
--         pencabutan di sini supaya menunya juga tak tampil.
DELETE ga FROM group_access ga
JOIN access_path ap ON ap.id = ga.access_id COLLATE utf8mb4_unicode_ci
WHERE ga.group_id IN ('role-konsultan-finance-0','role-konsultan-marketing','role-konsultan-it-000000')
  AND ap.link = 'pengajuan';


-- ── 3c. Koordinator GS: mulai dari salinan General Services …
INSERT INTO group_access (id, group_id, access_id)
SELECT UUID(), 'role-koordinator-gs-0000', ga.access_id
FROM group_access ga
WHERE ga.group_id = (SELECT id FROM group_path g WHERE g.nama = 'General Services' LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM group_access x
                  WHERE x.group_id = 'role-koordinator-gs-0000' AND x.access_id = ga.access_id);

-- … lalu BUANG "Checklist Saya"/"Riwayat Checklist": Fajri jadi PENINJAU, bukan
-- pengisi (keputusan Ekky 2026-08-21). checklistDivisionForGroup() mencocokkan
-- NAMA role dengan nama divisi dan tak ada divisi bernama "Koordinator GS",
-- jadi halaman itu akan kosong selamanya kalau dibiarkan.
DELETE ga FROM group_access ga
JOIN access_path ap ON ap.id = ga.access_id COLLATE utf8mb4_unicode_ci
WHERE ga.group_id = 'role-koordinator-gs-0000'
  AND ap.link IN ('my-checklist', 'my-checklist/history');

-- … dan TAMBAHKAN halaman pengawas checklist (disalin dari Supervisor, dibatasi
-- ke klaster checklist). Ini yang membuat checklistHasSupervisorPageAccess()
-- bernilai true; digabung dengan checklistVisibleDivisionIds() yang otomatis
-- berisi [GS] (karena General Services jadi anak Koordinator GS di langkah 5),
-- hasilnya isChecklistSupervisor() = true → uji terima #10 lulus.
INSERT INTO group_access (id, group_id, access_id)
SELECT UUID(), 'role-koordinator-gs-0000', ga.access_id
FROM group_access ga
JOIN access_path ap ON ap.id = ga.access_id COLLATE utf8mb4_unicode_ci
WHERE ga.group_id = (SELECT id FROM group_path g WHERE g.nama = 'Supervisor' LIMIT 1)
  AND (ap.link IN ('checklist', 'checklist/review', 'checklist/setup')
       OR (ap.link IS NULL AND ap.nama LIKE 'Monitoring Form%'))
  AND NOT EXISTS (SELECT 1 FROM group_access x
                  WHERE x.group_id = 'role-koordinator-gs-0000' AND x.access_id = ga.access_id);


-- ============================================================================
-- 4. att_approval_rule — WEWENANG ACC YANG SESUNGGUHNYA
--    §3.1: ini, bukan group_path.parent_id, yang memutuskan siapa meng-ACC.
--    §3.2: sort_order BUKAN lapis. Semua approver dilebur jadi SATU lapis
--          (MAX_LEVELS semua = 1). "Cuma atasan langsung" = baris lain DICABUT.
--    CABUT DULU, baru tambah.
-- ============================================================================

-- 4a. Assistant Dentist: Irma SAJA (cabut Supervisor).
--     Hari ini Asdent bisa di-ACC Irma ATAU Anisa, siapa saja duluan — bukan
--     berjenjang. Keputusan Ekky: Assistant Dentist → Irma saja.
DELETE r FROM att_approval_rule r
WHERE r.role_id          COLLATE utf8mb4_unicode_ci = (SELECT id FROM group_path g WHERE g.nama = 'Assistant Dentist' LIMIT 1)
  AND r.approver_role_id COLLATE utf8mb4_unicode_ci = (SELECT id FROM group_path g WHERE g.nama = 'Supervisor' LIMIT 1);

-- 4b. Coordinator Dentist: ke Supervisor (Anisa), bukan lagi Executive (Dani).
DELETE r FROM att_approval_rule r
WHERE r.role_id          COLLATE utf8mb4_unicode_ci = (SELECT id FROM group_path g WHERE g.nama = 'Coordinator Dentist' LIMIT 1)
  AND r.approver_role_id COLLATE utf8mb4_unicode_ci = (SELECT id FROM group_path g WHERE g.nama = 'Executive' LIMIT 1);

INSERT INTO att_approval_rule (id, role_id, approver_role_id, sort_order)
SELECT UUID(),
       (SELECT id FROM group_path g WHERE g.nama = 'Coordinator Dentist' LIMIT 1),
       (SELECT id FROM group_path g WHERE g.nama = 'Supervisor' LIMIT 1), 1
WHERE NOT EXISTS (
    SELECT 1 FROM att_approval_rule x
    WHERE x.role_id          COLLATE utf8mb4_unicode_ci = (SELECT id FROM group_path g WHERE g.nama = 'Coordinator Dentist' LIMIT 1)
      AND x.approver_role_id COLLATE utf8mb4_unicode_ci = (SELECT id FROM group_path g WHERE g.nama = 'Supervisor' LIMIT 1));

-- 4c. General Services: Fajri (Koordinator GS) DAN Anisa (Supervisor).
--     Keputusan Ekky: "General Services → Fajri + Anisa (sampai Fajri
--     terbukti)". Baris Supervisor SENGAJA dipertahankan. Sadari artinya
--     (§3.2): siapa saja duluan, BUKAN Fajri dulu baru Anisa.
INSERT INTO att_approval_rule (id, role_id, approver_role_id, sort_order)
SELECT UUID(),
       (SELECT id FROM group_path g WHERE g.nama = 'General Services' LIMIT 1),
       'role-koordinator-gs-0000', 1
WHERE NOT EXISTS (
    SELECT 1 FROM att_approval_rule x
    WHERE x.role_id          COLLATE utf8mb4_unicode_ci = (SELECT id FROM group_path g WHERE g.nama = 'General Services' LIMIT 1)
      AND x.approver_role_id COLLATE utf8mb4_unicode_ci = 'role-koordinator-gs-0000');

-- 4d. Koordinator GS mengajukan ke Supervisor (uji #8: Fajri → Anisa).
INSERT INTO att_approval_rule (id, role_id, approver_role_id, sort_order)
SELECT UUID(), 'role-koordinator-gs-0000',
       (SELECT id FROM group_path g WHERE g.nama = 'Supervisor' LIMIT 1), 1
WHERE NOT EXISTS (
    SELECT 1 FROM att_approval_rule x
    WHERE x.role_id COLLATE utf8mb4_unicode_ci = 'role-koordinator-gs-0000'
      AND x.approver_role_id COLLATE utf8mb4_unicode_ci = (SELECT id FROM group_path g WHERE g.nama = 'Supervisor' LIMIT 1));

-- 4e. KONSULTAN — NOL BARIS, dua-duanya arah. Ditegakkan, bukan diasumsikan:
--     kalau seseorang pernah menambahkannya lewat layar admin, dibersihkan.
--       · nol sebagai approver_role_id → tak meng-ACC pengajuan siapa pun
--       · nol sebagai role_id          → tak bisa mengajukan cuti/lembur
DELETE FROM att_approval_rule
WHERE role_id          IN ('role-konsultan-finance-0','role-konsultan-marketing','role-konsultan-it-000000')
   OR approver_role_id IN ('role-konsultan-finance-0','role-konsultan-marketing','role-konsultan-it-000000');


-- ============================================================================
-- 5. group_path — BAGAN ORGANISASI (+ cakupan review checklist)
--    Ingat §3.1: langkah ini TIDAK memindahkan wewenang ACC. Itu langkah 4.
-- ============================================================================

UPDATE group_path SET parent_id = (SELECT id FROM (SELECT id FROM group_path WHERE nama = 'Supervisor' LIMIT 1) s)
WHERE nama = 'Coordinator Dentist';

UPDATE group_path SET parent_id = 'role-koordinator-gs-0000'
WHERE nama = 'General Services';

-- parent_id_2 General Services (= Assistant Dentist) DIBUANG: jalur kedua yang
-- tak pernah dipakai att_approval_rule dan tak pernah dijelaskan. Efek nyatanya
-- cuma satu — lewat supervisedDescendants(), divisi GS ikut masuk cakupan
-- review Coordinator Dentist. Sesudah ini GS ditinjau Koordinator GS.
-- (Di dev kolomnya sudah NULL; di prod berisi Assistant Dentist.)
UPDATE group_path SET parent_id_2 = NULL
WHERE nama = 'General Services' AND parent_id_2 IS NOT NULL;


-- ============================================================================
-- 6. RELEVEL — level dihitung, jangan ditulis manual.
--    Pengganti Set\Group::relevel() (metode private, tak bisa dipanggil dari
--    SQL). Pohonnya dangkal; empat sapuan lebih dari cukup dan sapuan yang
--    tak mengubah apa pun berhenti dengan sendirinya (WHERE-nya tak cocok).
-- ============================================================================

UPDATE group_path SET level = 0 WHERE parent_id IS NULL AND level <> 0;
UPDATE group_path c JOIN group_path p ON p.id = c.parent_id SET c.level = p.level + 1 WHERE c.level <> p.level + 1;
UPDATE group_path c JOIN group_path p ON p.id = c.parent_id SET c.level = p.level + 1 WHERE c.level <> p.level + 1;
UPDATE group_path c JOIN group_path p ON p.id = c.parent_id SET c.level = p.level + 1 WHERE c.level <> p.level + 1;
UPDATE group_path c JOIN group_path p ON p.id = c.parent_id SET c.level = p.level + 1 WHERE c.level <> p.level + 1;


-- ============================================================================
-- 7. ORANGNYA
--    Dicocokkan lewat email (prod) DAN nama (dev, di mana emailnya berbeda —
--    lihat memori project_roster_karyawan: "email beda antar env"). Baris yang
--    tak cocok cuma jadi 0 baris, jadi aman di dua-duanya.
--
--    Mengubah users.group_id TIDAK mengubah att_user_rule. Aturan kerja yang
--    menempel pada ORANGNYA tetap — itu memang yang diinginkan. Sebaliknya
--    att_default_rule_id role baru TIDAK berlaku surut; ia cuma menyala saat
--    akun BARU dibuat.
-- ============================================================================

UPDATE users SET group_id = 'role-konsultan-finance-0',  updated_at = NOW()
WHERE email = 'haickal@sweettreats.id' AND group_id <> 'role-konsultan-finance-0';

UPDATE users SET group_id = 'role-konsultan-marketing', updated_at = NOW()
WHERE email = 'hari@stdc.id'           AND group_id <> 'role-konsultan-marketing';

UPDATE users SET group_id = 'role-konsultan-it-000000', updated_at = NOW()
WHERE email = 'rahmat@sweettreats.id'  AND group_id <> 'role-konsultan-it-000000';

-- Alpajri Suhendar Al Qhusni. SENGAJA dicocokkan lewat NAMA LENGKAP, bukan
-- email: prod `fajri@sweettreats.id`, dev `apajri@sweettreats.id`, dan dev
-- masih menyimpan akun LAIN bernama "Fajri" (`fajri@stdc.id`) yang BUKAN orang
-- ini. Dibatasi ke yang sekarang General Services supaya sekali jalan.
-- (Bandingkan jebakan lama: Anisa ≠ Admin_Nisa; Fajri ≠ Mutiara Nurul Fajri.)
UPDATE users SET group_id = 'role-koordinator-gs-0000', updated_at = NOW()
WHERE nama = 'Alpajri Suhendar Al Qhusni'
  AND group_id = (SELECT id FROM (SELECT id FROM group_path WHERE nama = 'General Services' LIMIT 1) s);


-- ============================================================================
-- 9. "PENGAMAT" JADI SIFAT DI DATA, BUKAN DAFTAR NAMA DI KODE
--
--    Sampai 22 Agu, "konsultan tak mengajukan & tak meng-ACC" ditegakkan oleh
--    daftar teks di AttendanceRequestWorkflow. Artinya tiap role konsultan baru
--    (Konsultan HR, Konsultan Ops, …) menuntut perubahan kode — dan lupa satu
--    baris = kegagalan senyap. Kolom ini memindahkan keputusannya ke data:
--    konsultan berikutnya cukup SATU BARIS SQL, nol koding.
--
--    Kolom ini menemani `landing_page`, `theme`, dan `att_default_rule_id` yang
--    memang sudah menyimpan sifat per-role di tabel yang sama — pola lama,
--    bukan mekanisme baru.
--
--    'penuh'    = mengajukan & menyetujui seperti biasa (bawaan semua role)
--    'pengamat' = TIDAK mengajukan cuti/lembur/izin, TIDAK meng-ACC pengajuan
--                 siapa pun. TIDAK menyentuh pekerjaan bidang: Konsultan
--                 Finance tetap menyetujui ROP lewat RopService.
--
--    ⚠️ MariaDB: `IF NOT EXISTS` di ALTER didukung (server 11.8), jadi aman diulang.
-- ============================================================================

ALTER TABLE group_path
    ADD COLUMN IF NOT EXISTS wewenang_pengajuan
        ENUM('penuh','pengamat') NOT NULL DEFAULT 'penuh'
        COMMENT 'pengamat = tak mengajukan & tak meng-ACC pengajuan orang (mis. konsultan); tak menyentuh kerjaan bidang';

UPDATE group_path
SET wewenang_pengajuan = 'pengamat'
WHERE (nama = 'Consultant' OR nama LIKE 'Konsultan %')
  AND wewenang_pengajuan <> 'pengamat';


-- ============================================================================
-- 8. FAJRI SETARA ANISA — cakupan review 3 divisi + lintas cabang
--    Keputusan Ekky 2026-08-22, sesudah melihat bagan: Koordinator GS meninjau
--    checklist SETARA Supervisor — Assistant Dentist, Front Office, DAN
--    General Services — bukan cuma GS.
-- ============================================================================

-- 8a. `parent_id_2` = "atasan kedua, khusus supervisi checklist". Itu memang
--     mekanisme yang disediakan: supervisedDescendants() menelusuri parent_id
--     DAN parent_id_2, jadi satu divisi bisa punya dua atasan. Prod sendiri
--     dulu memakainya begitu (General Services.parent_id_2 = Assistant Dentist).
--
--     Bagan organisasi TIDAK berubah bentuk: _orgnode.blade.php menggambar
--     pohonnya dari parent_id saja dan menampilkan parent_id_2 sebagai chip
--     "Atasan lain". Front Office tetap tergambar di bawah Supervisor —
--     sesuai catatan Ekky bahwa role Koordinator FO memang belum ada.
--
--     ⚠️ Ini TIDAK memberi Fajri wewenang ACC atas FO/Asdent. Wewenang ACC
--     cuma dari att_approval_rule (§3.1) dan di sana Fajri tak disebut untuk
--     mereka. Pengajuan FO tetap ke Anisa, Asdent tetap ke Irma.
UPDATE group_path SET parent_id_2 = 'role-koordinator-gs-0000'
WHERE nama IN ('Front Office', 'Assistant Dentist')
  AND (parent_id_2 IS NULL OR parent_id_2 <> 'role-koordinator-gs-0000');

-- 8b. Lintas cabang. resolveChecklistBranchId() memakai session('raw_branch_id')
--     lebih dulu; kalau terisi, orangnya TERKUNCI ke cabang itu. Di layar
--     review, `if ($branchId)` — jadi NULL berarti TANPA filter = semua cabang.
--     Anisa (Supervisor), Irma (Coordinator Dentist), dan Robi (Executive)
--     semuanya sudah ber-raw_branch_id NULL. Fajri disamakan.
--
--     Absensinya TIDAK terganggu: cabang absensi datang dari mesin scan dan
--     disimpan di att_day.raw_branch_id + cabang_terlibat, bukan dibaca dari
--     users.raw_branch_id. Buktinya Anisa & Irma sama-sama NULL dan tetap
--     punya baris att_day.
UPDATE users SET raw_branch_id = NULL, updated_at = NOW()
WHERE nama = 'Alpajri Suhendar Al Qhusni'
  AND group_id = 'role-koordinator-gs-0000'
  AND raw_branch_id IS NOT NULL;

-- 8c. Tutup posisi checklist lamanya (divisi GS). Ini yang akan dilakukan
--     UserPosition::syncFromUser() sendiri begitu ia dipanggil; dikerjakan di
--     sini supaya keadaannya konsisten SEKARANG, bukan menunggu admin kebetulan
--     menyunting akunnya. Kalau dibiarkan terbuka, Fajri ikut terhitung sebagai
--     STAF GS di penyebut dashboard review — jadi dia meninjau dirinya sendiri.
--     Anisa & Irma sama-sama nol posisi terbuka; Fajri disamakan.
UPDATE sys_user_position p
JOIN users u ON u.id = p.user_id COLLATE utf8mb4_unicode_ci
SET p.effective_to = CURRENT_DATE - INTERVAL 1 DAY, p.updated_at = NOW()
WHERE u.nama = 'Alpajri Suhendar Al Qhusni'
  AND u.group_id = 'role-koordinator-gs-0000'
  AND p.kind = 'home'
  AND p.effective_to IS NULL;
