-- ============================================================================
-- att_approval_rule — aturan approval absensi PER-ROLE (gantiin att_approver per-user)
-- ----------------------------------------------------------------------------
-- Model: role pengaju → [role approver] (any-one dalam satu lapis). Resolver
-- (AttendanceRequestWorkflow::chainFor) jalan NAIK graf ini, tiap lapis =
-- daftar USER dari role approver (salah satu approve = maju). Dipangkas per
-- jenis form (MAX_LEVELS: cuti/lembur=2, izin/libur/wfh=1).
--
-- KENAPA per-role, bukan turunin dari group_path.parent_id: chain approval
-- absensi NYIMPANG dari pohon org. Contoh keras — Assistant Dentist: parent org
-- cuma Coordinator Dentist, tapi approval = [Coordinator Dentist ATAU Supervisor]
-- (any-one). Makanya aturan ditulis eksplisit di tabel ini, decoupled.
--
-- Apex = Executive (Dani): tak punya baris sbg role_id → chain kosong → auto-approve.
-- Tambah karyawan = set group_id-nya = NOL koding, NOL ubah aturan (POC terbukti).
-- att_approver (per-user, 2026_07_18_absensi_approver.sql) jadi DORMANT — tak
-- dibaca lagi, tabel dibiarkan (reversible). Seed 2026-07-22 (feature/absensi, local).
--
-- Referensi: docs/rencana/rencana-rekonsiliasi-absensi.md §4/§5.4, scratchpad poc_approval_byrole.php.
-- ============================================================================

CREATE TABLE IF NOT EXISTS att_approval_rule (
  id               VARCHAR(255) NOT NULL PRIMARY KEY,
  role_id          VARCHAR(255) NOT NULL COMMENT 'group_path.id pengaju',
  approver_role_id VARCHAR(255) NOT NULL COMMENT 'group_path.id approver (any-one dlm 1 lapis)',
  sort_order       INT          NOT NULL DEFAULT 0,
  created_at       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rule (role_id, approver_role_id),
  KEY idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

START TRANSACTION;

-- idempoten: kosongkan lalu isi ulang seluruh aturan.
DELETE FROM att_approval_rule;

-- ATURAN DITULIS PAKAI NAMA JABATAN, BUKAN UUID.
--
-- Versi awal file ini menuliskan group_path.id (UUID) hasil generate di mesin
-- LOKAL. Di server lain UUID itu tak ada, dan att_approval_rule TIDAK punya
-- foreign key ke group_path — jadi INSERT tetap sukses tapi tak menunjuk apa
-- pun. Akibatnya chainFor() tak menemukan satu pun approver → rantai kosong →
-- SELURUH cuti/lembur/izin langsung berstatus approved tanpa atasan, tanpa
-- error, tanpa jejak. Baru ketahuan waktu gaji dibayarkan.
--
-- Dengan JOIN ke group_path.nama, aturan mengikat ke jabatan yang benar-benar
-- ada di server. Jabatan yang belum ada di server otomatis TERLEWAT (bukan
-- salah-tunjuk), dan itu aman karena AttendanceRequestWorkflow sekarang menolak
-- auto-approve bila jabatan pengaju tak dikenal grafik persetujuan.
--
-- WAJIB dicek setelah dijalankan (lihat query verifikasi di bawah).
INSERT INTO att_approval_rule (id, role_id, approver_role_id, sort_order, created_at)
SELECT m.id, r.id, a.id, m.sort_order, NOW()
FROM (
              SELECT 'rule-gs-spv'      AS id, 'General Services'    AS pengaju,   'Supervisor'          AS penyetuju, 1 AS sort_order
    UNION ALL SELECT 'rule-fo-spv',           'Front Office',              'Supervisor',          1
    UNION ALL SELECT 'rule-sales-spv',        'Sales',                     'Supervisor',          1
    -- Assistant Dentist → [Coordinator Dentist ATAU Supervisor] (any-one, satu lapis)
    UNION ALL SELECT 'rule-asd-koord',        'Assistant Dentist',         'Coordinator Dentist', 1
    UNION ALL SELECT 'rule-asd-spv',          'Assistant Dentist',         'Supervisor',          2
    -- Marketing
    UNION ALL SELECT 'rule-creative-hm',      'Creative',                  'Head Marketing',      1
    UNION ALL SELECT 'rule-content-hm',       'Content Creator',           'Head Marketing',      1
    -- Head / lapis-1 → Executive
    UNION ALL SELECT 'rule-spv-exec',         'Supervisor',                'Executive',           1
    UNION ALL SELECT 'rule-koord-exec',       'Coordinator Dentist',       'Executive',           1
    UNION ALL SELECT 'rule-hm-exec',          'Head Marketing',            'Executive',           1
    UNION ALL SELECT 'rule-fin-exec',         'Finance',                   'Executive',           1
    -- IT berjenjang: staf IT → Head IT → Executive
    UNION ALL SELECT 'rule-it-headit',        'IT',                        'Head IT',             1
    UNION ALL SELECT 'rule-headit-exec',      'Head IT',                   'Executive',           1
) m
JOIN group_path r ON r.nama = m.pengaju
JOIN group_path a ON a.nama = m.penyetuju;

-- Executive sengaja TANPA baris sebagai pengaju → apex, auto-approve (sah).

COMMIT;

-- ============================================================================
-- VERIFIKASI — JALANKAN INI, JANGAN DILEWATI.
-- Harus mengembalikan 13. Kalau kurang, ada nama jabatan yang belum ada di
-- server: pengajuan dari jabatan itu akan DITOLAK dengan pesan jelas (bukan
-- diam-diam disetujui), tapi tetap harus dibereskan.
--
--   SELECT COUNT(*) AS jumlah_aturan FROM att_approval_rule;
--
-- Melihat jabatan mana yang gagal terpasang:
--
--   SELECT m.pengaju, m.penyetuju,
--          r.id IS NULL AS pengaju_tak_ada,
--          a.id IS NULL AS penyetuju_tak_ada
--   FROM ( SELECT 'General Services' pengaju, 'Supervisor' penyetuju
--          UNION ALL SELECT 'Front Office','Supervisor'
--          UNION ALL SELECT 'Sales','Supervisor'
--          UNION ALL SELECT 'Assistant Dentist','Coordinator Dentist'
--          UNION ALL SELECT 'Assistant Dentist','Supervisor'
--          UNION ALL SELECT 'Creative','Head Marketing'
--          UNION ALL SELECT 'Content Creator','Head Marketing'
--          UNION ALL SELECT 'Supervisor','Executive'
--          UNION ALL SELECT 'Coordinator Dentist','Executive'
--          UNION ALL SELECT 'Head Marketing','Executive'
--          UNION ALL SELECT 'Finance','Executive'
--          UNION ALL SELECT 'IT','Head IT'
--          UNION ALL SELECT 'Head IT','Executive' ) m
--   LEFT JOIN group_path r ON r.nama = m.pengaju
--   LEFT JOIN group_path a ON a.nama = m.penyetuju
--   WHERE r.id IS NULL OR a.id IS NULL;
-- ============================================================================
