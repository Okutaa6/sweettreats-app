-- ============================================================================
-- 2026_07_23_fix_landing_sales.sql
--
-- Role "Sales" tak bisa login sama sekali — bukan sekadar satu halaman tertutup.
--
-- Alurnya: AuthController melempar user ke group_path.landing_page setelah login.
-- landing_page Sales diisi 'dashboard', TAPI Sales tak punya grant 'dashboard'.
-- AuthGuard menolak, lalu mencoba melempar ke landing page-nya — yang ternyata
-- halaman ITU JUGA. Karena tujuannya sama dengan yang barusan ditolak, AuthGuard
-- memilih abort(403) demi mencegah redirect-loop. Hasilnya user terkunci total.
--   (lihat app/Http/Middleware/AuthGuard.php: cek `segment(1) === landing_page`)
--
-- Sales SUDAH punya grant 'sales-dashboard' dan route-nya ada, jadi cukup
-- arahkan landing-nya ke sana. 4 user terdampak: Sales Sunter, Sales Admin,
-- Sales Pamulang, Sales Cinere.
--
-- Ditulis pakai NAMA role (bukan UUID) supaya jalan di server mana pun.
-- Idempoten.
-- ============================================================================

UPDATE `group_path`
SET `landing_page` = 'sales-dashboard'
WHERE `nama` = 'Sales'
  AND `landing_page` = 'dashboard';

-- ============================================================================
-- VERIFIKASI — landing tiap role HARUS termasuk yang di-grant ke role itu.
-- Query ini mendaftar role yang landing-nya menunjuk halaman yang tak dia punya
-- (harus mengembalikan 0 baris; segmen yang di-skip AuthGuard tak dihitung):
--
--   SELECT g.nama, g.landing_page
--   FROM group_path g
--   WHERE g.landing_page IS NOT NULL
--     AND SUBSTRING_INDEX(g.landing_page, '/', 1) NOT IN
--         ('export','api','auditlog','executive','fund-sources','finance')
--     AND NOT EXISTS (
--       SELECT 1 FROM group_access ga
--       JOIN access_path ap ON ap.id = ga.access_id
--       WHERE ga.group_id = g.id
--         AND ap.link = SUBSTRING_INDEX(g.landing_page, '/', 1)
--     );
-- ============================================================================
