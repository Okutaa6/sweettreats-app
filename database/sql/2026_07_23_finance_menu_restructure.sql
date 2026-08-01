-- ============================================================================
-- 2026_07_23_finance_menu_restructure.sql
--
-- Dua perubahan menu (sidebar per-role, via access_path + group_access):
--
--  TASK 2 — Absensi jadi SATU pintu di sidebar.
--    Upload/Mapping/Rekap/Periode kini sudah berbentuk TAB di dalam surface
--    Finance, jadi tak perlu 4 item terpisah di samping. Sisakan 1 leaf terlihat
--    = "Rekap Persetujuan" (landing headline Finance). Grant AuthGuard (segment-1
--    'absensi') dipindah ke node PARENT — parent tak pernah dirender jadi leaf
--    (punya anak), jadi tetap 1 item tampil tapi semua /absensi/* tetap lolos guard.
--
--  TASK 1 — Petty Cash (Monitoring, /finance/pettycash) dapat MENU sendiri.
--    Sebelumnya tak ada node menu sama sekali (cuma bisa via URL). Bikin section
--    "Keuangan" > item "Petty Cash". Route 'finance' skip-RBAC (di-guard
--    canSeeAllBranches di controller) → menu ini murni untuk tampilan sidebar.
--
-- Idempoten (aman dijalankan ulang). Konteks: docs/rencana/rencana-rekonsiliasi-absensi.md.
-- ============================================================================

-- ── TASK 2 ──────────────────────────────────────────────────────────────────
-- Pindahkan grant AuthGuard ke parent "Absensi" (link segment-1 = 'absensi').
UPDATE `access_path` SET `link` = 'absensi' WHERE `id` = 'abs-parent-000000000000';

-- Copot leaf redundant dari sidebar (ungrant). Halaman tetap diakses lewat tab
-- (AuthGuard lolos via parent, controller di-guard canSeeAllBranches).
DELETE FROM `group_access` WHERE `access_id` IN (
    'abs-upload-000000000000',
    'abs-mapping-0000000000',
    'abs-period-00000000000',
    'a2515f68-5b08-4c21-a500-d44195b48e3e',   -- dup "Rekap Persetujuan" (child abs-recap)
    'a2515f38-d336-415a-81e5-243e69fb1a17'    -- dup "Upload Data" (child abs-upload)
);
-- Sisa yang granted di bawah "Absensi" = abs-recap → satu item "Rekap Persetujuan".

-- ── TASK 1 ──────────────────────────────────────────────────────────────────
-- Section "Keuangan" + item "Petty Cash".
INSERT INTO `access_path` (`id`,`pid`,`nama`,`icon`,`urutan`,`urutan_path`,`link`,`pid_path`) VALUES
    ('fin-parent-000000000000','','Keuangan','icon-fa fas fa-wallet',95,NULL,'',NULL),
    ('fin-pettycash-000000000','fin-parent-000000000000','Petty Cash','icon-fa fas fa-coins',1,NULL,'finance/pettycash','fin-parent-000000000000,fin-pettycash-000000000')
ON DUPLICATE KEY UPDATE
    `nama` = VALUES(`nama`), `icon` = VALUES(`icon`), `link` = VALUES(`link`),
    `pid` = VALUES(`pid`), `urutan` = VALUES(`urutan`), `pid_path` = VALUES(`pid_path`);

-- Grant "Keuangan" + "Petty Cash" ke grup yg boleh lintas-cabang
-- (mirror persis grup yang punya "Absensi": Finance, Admin, Admin Backup,
-- Super Admin, Admin General). Delete-first supaya idempoten.
DELETE FROM `group_access` WHERE `access_id` IN ('fin-parent-000000000000','fin-pettycash-000000000');
INSERT INTO `group_access` (`id`,`group_id`,`access_id`)
SELECT UUID(), ga.`group_id`, ap.`id`
FROM `group_access` ga
CROSS JOIN (
    SELECT 'fin-parent-000000000000' AS id
    UNION ALL SELECT 'fin-pettycash-000000000'
) ap
WHERE ga.`access_id` = 'abs-parent-000000000000';
