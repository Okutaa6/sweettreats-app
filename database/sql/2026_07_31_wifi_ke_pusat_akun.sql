-- ============================================================================
-- WiFi Cabang: pindah node menu dari seksi Absensi ke seksi IT (Pusat Akun)
-- ============================================================================
-- Alasan (docs/spec/peta-tema.md §4): "lokasi menu = domain". WiFi Cabang itu
-- infrastruktur IT, bukan alur kerja absensi harian — dipindah ke Pusat Akun
-- (Data Center → Pusat Akun), setelah Setting. Path URL TIDAK berubah
-- (tetap absensi/wifi) supaya bookmark/link lama tetap hidup; zona warnanya
-- jadi grafit lewat body-class zona-it (layouts/app.blade.php + zona.css).
--
-- Jalankan di dev & prod, lalu WAJIB: php artisan access:rebuild
-- (menu & hak akses dibaca dari cache storage/app/group_access).

UPDATE `access_path`
SET `pid` = 'e10e4cd1-137d-46d7-bb0f-7864cfc5a4fc',   -- Pusat Akun
    `urutan` = 5                                       -- setelah Setting (4)
WHERE `id` = 'abs-wifi-0000000000000';

-- Grant TIDAK diubah: siapa yang boleh lihat WiFi Cabang tetap sama
-- (group_access abs-wifi-*). Yang pindah cuma posisi menunya.
-- CATATAN: kalau ada role yang punya abs-wifi tapi TIDAK punya node parent
-- 'Pusat Akun' (e10e4cd1-...), menu barunya tidak akan kelihatan — beri juga
-- grant parent Pusat Akun ke role itu:
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('pa-parent-grant-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, 'e10e4cd1-137d-46d7-bb0f-7864cfc5a4fc'
FROM `group_path` gp
WHERE EXISTS (SELECT 1 FROM `group_access` gw
              WHERE gw.group_id = gp.id AND gw.access_id = 'abs-wifi-0000000000000')
  AND NOT EXISTS (SELECT 1 FROM `group_access` gx
                  WHERE gx.group_id = gp.id AND gx.access_id = 'e10e4cd1-137d-46d7-bb0f-7864cfc5a4fc');

-- Node "Pusat Akun" ada di bawah "Data Center" — pastikan parent teratasnya
-- juga ter-grant untuk role yang sama (kalau belum):
INSERT INTO `group_access` (`id`, `group_id`, `access_id`)
SELECT CONCAT('dc-parent-grant-', LOWER(REPLACE(gp.nama, ' ', '-'))), gp.id, '32361898-c607-11ec-9240-809133ff6caa'
FROM `group_path` gp
WHERE EXISTS (SELECT 1 FROM `group_access` gw
              WHERE gw.group_id = gp.id AND gw.access_id = 'abs-wifi-0000000000000')
  AND NOT EXISTS (SELECT 1 FROM `group_access` gx
                  WHERE gx.group_id = gp.id AND gx.access_id = '32361898-c607-11ec-9240-809133ff6caa');
