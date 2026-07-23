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
-- Referensi: docs/rencana-rekonsiliasi-absensi.md §4/§5.4, scratchpad poc_approval_byrole.php.
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

INSERT INTO att_approval_rule (id, role_id, approver_role_id, sort_order, created_at) VALUES
  -- Operasional → Supervisor (Nisa)
  ('rule-gs-spv',    '3d65a0bb-da4d-4a8c-a6a7-7f957beee978', '19f22e68-62d5-4b6e-b040-7120783c141b', 1, NOW()), -- General Services
  ('rule-fo-spv',    '8c3537eb-c7e5-45fa-bcc5-717cecd039cd', '19f22e68-62d5-4b6e-b040-7120783c141b', 1, NOW()), -- Front Office
  ('rule-sales-spv', 'a11d4a41-e705-4213-a398-75109a1ba73c', '19f22e68-62d5-4b6e-b040-7120783c141b', 1, NOW()), -- Sales

  -- Assistant Dentist → [Coordinator Dentist (Irma) ATAU Supervisor (Nisa)] any-one
  ('rule-asd-koord', 'f2663ddb-5eba-4e7c-a10e-b9c9038d6505', '8fa0a3a7-63ee-4043-b177-456f8012d379', 1, NOW()),
  ('rule-asd-spv',   'f2663ddb-5eba-4e7c-a10e-b9c9038d6505', '19f22e68-62d5-4b6e-b040-7120783c141b', 2, NOW()),

  -- Marketing staff → Head Marketing (Yovan)
  ('rule-creative-hm', 'b6ce4510-105e-4f87-836f-149e6b5391a7', '107822d4-5c5b-4b5a-8e36-f73aa1ee9af9', 1, NOW()), -- Creative
  ('rule-content-hm',  '51d75867-c562-42c8-8f9c-e828f00c1b41', '107822d4-5c5b-4b5a-8e36-f73aa1ee9af9', 1, NOW()), -- Content Creator

  -- Head/lapis-1 → Executive (Dani)
  ('rule-spv-exec',   '19f22e68-62d5-4b6e-b040-7120783c141b', '3255acd1-15e7-43d9-8528-c59e01451cce', 1, NOW()), -- Supervisor
  ('rule-koord-exec', '8fa0a3a7-63ee-4043-b177-456f8012d379', '3255acd1-15e7-43d9-8528-c59e01451cce', 1, NOW()), -- Coordinator Dentist
  ('rule-hm-exec',    '107822d4-5c5b-4b5a-8e36-f73aa1ee9af9', '3255acd1-15e7-43d9-8528-c59e01451cce', 1, NOW()), -- Head Marketing
  ('rule-fin-exec',   '9fb20179-325b-4a89-8109-24f502da3134', '3255acd1-15e7-43d9-8528-c59e01451cce', 1, NOW()), -- Finance

  -- IT berjenjang (step 2b): staf IT (Reza) → Head IT (Ekky) → Executive (Dani).
  ('rule-it-headit',   '83de68e5-5d49-437f-af19-d23c805820ed', 'a1700701-0000-4000-8000-000000000001', 1, NOW()), -- IT → Head IT
  ('rule-headit-exec', 'a1700701-0000-4000-8000-000000000001', '3255acd1-15e7-43d9-8528-c59e01451cce', 1, NOW()); -- Head IT → Executive

-- Executive (3255acd1) sengaja TANPA baris → apex, auto-approve.

COMMIT;
