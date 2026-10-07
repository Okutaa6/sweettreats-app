<?php

namespace Tests\Feature;

use App\Services\Ortho\OrtBranches;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tab Kalender — pindah bulan dan pemilih 12 bulan.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KENAPA BERKAS INI ADA
 * ────────────────────────────────────────────────────────────────────────────
 * Pemilih bulan dibangun 7 Okt 2026, bentuknya meniru navigasi kalender halaman
 * Kehadiran. Audit v2 menemukan ia TANPA UJI SAMA SEKALI: seluruh tab Kalender
 * cuma tersentuh satu `assertOk()` sambil lalu.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG DIJAGA, DAN KENAPA JUSTRU INI
 * ────────────────────────────────────────────────────────────────────────────
 * Pemilihnya TAUTAN biasa, bukan grid yang dibangun JS seperti aslinya — justru
 * supaya bisa diuji tanpa peramban. Yang bisa pecah diam-diam ada dua, dan
 * keduanya soal TAHUN:
 *
 *   1. Label tahun di panel harus mengikuti bulan yang sedang DILIHAT, bukan
 *      tahun berjalan. Memakai `now()->year` akan benar sepanjang tahun ini dan
 *      salah begitu seseorang menggulir ke Desember lalu menekan panah kanan.
 *
 *   2. Ke-12 tautannya harus tetap di tahun yang sama dengan yang dilihat.
 *      Sekali `sprintf` memakai tahun berjalan, memilih "Mar" dari Desember
 *      2025 akan melompat ke Maret 2026 — setahun meleset, tanpa galat.
 *
 * ⚠ Legenda ikut dijaga di sini. Sampai 7 Okt 2026 ia memasang 'hadir' dan
 *   'absen' — dua warna yang MUSTAHIL muncul di grid ini, karena
 *   OrtQuery::kalender() menyaring `closed_at IS NULL` sehingga kunjungan tak
 *   pernah masuk — sambil melewatkan tiga yang benar-benar muncul.
 */
class OrtoKalenderBulanTest extends TestCase
{
    use DatabaseTransactions;

    private string $placeId;
    private array $sesi;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ort_case', 'ort_control'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Tabel {$t} belum ada.");
            }
        }
        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }
        $this->placeId = (string) array_key_first($peta);

        $gid = DB::table('group_path')->where('nama', 'Supervisor')->value('id');
        if (! $gid) {
            $this->markTestSkipped("Role 'Supervisor' tidak ada di DB ini.");
        }
        $node = DB::table('access_path')->where('link', 'tracking-ortho')->first(['id', 'pid']);
        foreach (array_filter([$node->id, $node->pid]) as $accessId) {
            if (! DB::table('group_access')->where('group_id', $gid)->where('access_id', $accessId)->exists()) {
                DB::table('group_access')->insert(['id' => (string) Str::uuid(), 'group_id' => $gid, 'access_id' => $accessId]);
            }
        }

        $this->sesi = [
            'isLoggedIn' => true, 'id' => 'uji-' . Str::random(8), 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Sales', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ];
    }

    private function kalender(?string $bulan = null): string
    {
        return $this->withSession($this->sesi)
            ->get('/tracking-ortho?' . http_build_query(array_filter([
                'utama' => $this->placeId, 'tab' => 'kalender', 'bulan' => $bulan,
            ])))->assertOk()->getContent();
    }

    // ════════════════════════════════════════════════════════════════════════

    /** Pemilihnya ada, dan isinya tepat dua belas bulan. */
    public function test_pemilih_memuat_dua_belas_bulan(): void
    {
        $html = $this->kalender('2026-05');

        $this->assertStringContainsString('data-ort-bulan-panel', $html, 'Panel pemilih harus dirender.');
        $this->assertSame(12, substr_count($html, 'ort-cal-dp__m'), 'Harus tepat 12 chip bulan.');
        $this->assertStringContainsString('Lompat ke hari ini', $html);
    }

    /** ⭐ Label tahun mengikuti bulan yang DILIHAT, bukan tahun berjalan. */
    public function test_tahun_mengikuti_bulan_yang_dilihat(): void
    {
        $this->assertMatchesRegularExpression(
            '~ort-cal-dp__th tabular">\s*2025~', $this->kalender('2025-12'),
            'Melihat Desember 2025 tapi panelnya menyebut tahun lain.'
        );
    }

    /**
     * ⭐ Ke-12 tautannya tetap di tahun yang sedang dilihat.
     *
     * Inilah yang pecah paling sunyi: memilih "Mar" dari Desember 2025 lalu
     * mendarat di Maret 2026 tidak melempar galat apa pun — kalendernya tampil
     * rapi, isinya saja milik tahun yang salah.
     */
    public function test_tautan_bulan_tidak_melompat_tahun(): void
    {
        $html = $this->kalender('2025-12');

        preg_match_all('~bulan=(\d{4})-(\d{2})[^"]*"\s+class="ort-cal-dp__m~', $html, $m, PREG_SET_ORDER);

        $this->assertCount(12, $m, 'Ke-12 chip harus membawa tautan ?bulan=.');

        $bulanTerlihat = [];
        foreach ($m as $x) {
            $this->assertSame('2025', $x[1], 'Chip bulan melompat keluar tahun yang sedang dilihat.');
            $bulanTerlihat[] = $x[2];
        }

        $this->assertSame(
            ['01','02','03','04','05','06','07','08','09','10','11','12'],
            $bulanTerlihat,
            'Ke-12 bulan harus lengkap dan berurutan.'
        );
    }

    /** Chip bulan yang sedang dibuka ditandai — kalau tidak, panelnya tidak memberi tahu posisi. */
    public function test_bulan_berjalan_ditandai(): void
    {
        $this->assertStringContainsString(
            'ort-cal-dp__m is-on', $this->kalender('2026-05'),
            'Bulan yang sedang dilihat harus menyala di pemilihnya.'
        );
    }

    /** Panah kiri/kanan melintasi batas tahun dengan benar. */
    public function test_panah_melintasi_batas_tahun(): void
    {
        $html = $this->kalender('2026-01');

        $this->assertStringContainsString('bulan=2025-12', $html, 'Panah kiri dari Januari harus ke Desember tahun lalu.');
        $this->assertStringContainsString('bulan=2026-02', $html, 'Panah kanan dari Januari harus ke Februari.');
    }

    /**
     * ⭐ Legenda hanya menyebut warna yang BISA muncul.
     *
     * OrtQuery::kalender() menyaring `closed_at IS NULL`, jadi kunjungan tidak
     * pernah masuk grid — 'hadir' dan 'absen' di legenda adalah janji yang tak
     * bisa ditepati, dan pembacanya akan mengira datanya yang salah.
     */
    public function test_legenda_tidak_menjanjikan_warna_yang_mustahil(): void
    {
        $html = $this->kalender();

        preg_match_all('~ort-legend-dot ort-chip--([a-z]+)~', $html, $m);
        $kunci = array_values(array_unique($m[1]));

        $this->assertNotContains('hadir', $kunci, 'Kunjungan tidak pernah masuk grid kalender.');
        $this->assertNotContains('absen', $kunci, 'Begitu juga ketidakhadiran.');

        foreach (['menunggu', 'usulan', 'fu', 'rsv', 'batal', 'telat', 'telatlama'] as $bisa) {
            $this->assertContains($bisa, $kunci, "Keadaan '{$bisa}' bisa muncul tapi tidak ada di legenda.");
        }
    }

    /** "Hari Ini" membuang ?bulan= — bukan menambahnya. */
    public function test_hari_ini_mengosongkan_bulan(): void
    {
        $html = $this->kalender('2025-03');

        $this->assertMatchesRegularExpression(
            '~<a href="[^"]*tab=kalender[^"]*"[^>]*class="ort-btn"~', $html,
            'Tombol Hari Ini harus kembali ke bulan berjalan tanpa membawa ?bulan=.'
        );
        $this->assertStringContainsString('fa-dot-circle', $html, 'Ikonnya nama Font Awesome 5, bukan gaya FA6.');
    }
}
