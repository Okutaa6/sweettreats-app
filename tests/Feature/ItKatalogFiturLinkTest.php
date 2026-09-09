<?php

namespace Tests\Feature;

use App\Models\ItModul;
use App\Services\ItTracker\KatalogModul;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * §14.27 — fitur dipetakan per LINK, bukan per node.
 *
 * Kenapa ini penting sampai butuh kelasnya sendiri:
 *  · MENU mengelompokkan berdasarkan NAVIGASI, KATALOG berdasarkan KEPEMILIKAN.
 *    Menunjuk satu node parent = kelebihan (memuat modul lain), menunjuk satu
 *    daun = kekurangan (saudara-saudaranya hilang).
 *  · Satu HALAMAN bisa punya banyak LABEL menu — `rawreservation` muncul 4×.
 *    Menghitung node = menghitung label; menghitung link = menghitung halaman.
 *    Yang dijanjikan katalog kepada Executive adalah "halaman yang bisa dibuka".
 *  · `link` STABIL antar lingkungan; id node TIDAK.
 *
 * Kelas terpisah — batas ±17 uji ber-DB per proses.
 */
class ItKatalogFiturLinkTest extends TestCase
{
    use DatabaseTransactions;

    private KatalogModul $katalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->katalog = app(KatalogModul::class);
        try {
            DB::select('SELECT fitur_link FROM it_modul LIMIT 0');
        } catch (\Throwable) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_08_it_modul_fitur_link.sql dulu.');
        }
    }

    private function sesi(string $grup, string $uid): array
    {
        return ['isLoggedIn' => true, 'id' => $uid,
            'group_id' => DB::table('group_path')->where('nama', $grup)->value('id') ?: 'grp-uji',
            'group_name' => $grup, 'name' => 'Uji ' . $grup,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time()];
    }

    /** pohon menu sekali-pakai: satu HALAMAN dengan DUA label + satu daun mati */
    private function pohonUji(): array
    {
        $akar = 'uji-l-akar-' . Str::random(6);
        $a1   = 'uji-l-a1-' . Str::random(6);
        $a2   = 'uji-l-a2-' . Str::random(6);   // link SAMA dengan a1, nama beda
        $b    = 'uji-l-b-' . Str::random(6);
        $mati = 'uji-l-mati-' . Str::random(6);
        DB::table('access_path')->insert([
            ['id' => $akar, 'pid' => '',    'nama' => 'Uji Link',   'icon' => '', 'urutan' => 900, 'link' => '', 'urutan_path' => null, 'pid_path' => null],
            ['id' => $a1,   'pid' => $akar, 'nama' => 'Halaman A',  'icon' => '', 'urutan' => 1, 'link' => 'uji-link-a', 'urutan_path' => null, 'pid_path' => null],
            ['id' => $a2,   'pid' => $akar, 'nama' => 'A (alias)',  'icon' => '', 'urutan' => 2, 'link' => 'uji-link-a', 'urutan_path' => null, 'pid_path' => null],
            ['id' => $b,    'pid' => $akar, 'nama' => 'Halaman B',  'icon' => '', 'urutan' => 3, 'link' => 'uji-link-b', 'urutan_path' => null, 'pid_path' => null],
            ['id' => $mati, 'pid' => $akar, 'nama' => 'Baris Mati', 'icon' => '', 'urutan' => 4, 'link' => '', 'urutan_path' => null, 'pid_path' => null],
        ]);

        return [$akar, $a1, $b];
    }

    private function modul(array $isi): ItModul
    {
        return ItModul::create(array_merge([
            'nama' => 'Uji Link ' . Str::random(6), 'departemen' => 'IT', 'jenis' => 'Platform',
            'status' => 'hidup', 'sejak_terbukti' => false, 'urutan' => 995,
        ], $isi));
    }

    // ── inti: satu halaman dihitung SEKALI ────────────────────────────────

    /** Dua label menu untuk satu link = SATU fitur, bukan dua. */
    public function test_satu_halaman_dua_label_dihitung_sekali(): void
    {
        [$akar] = $this->pohonUji();

        // lewat daftar link
        $m = $this->modul(['fitur_link' => ['uji-link-a', 'uji-link-a', 'uji-link-b']]);
        $f = app(KatalogModul::class)->fiturModul($m);
        $this->assertCount(2, $f, 'link kembar di daftar pun cuma sekali');
        $this->assertSame(['uji-link-a', 'uji-link-b'], array_column($f, 'link'));

        // lewat node (jalur cadangan) — node A dan A(alias) berbagi link
        $n = $this->modul(['nama' => 'Uji Node ' . Str::random(5), 'access_id' => $akar]);
        $this->assertCount(2, app(KatalogModul::class)->fiturModul($n),
            'jalur node pun dedup per link: 3 daun ber-link → 2 halaman');
    }

    /** Modul boleh memiliki rute yang tersebar di beberapa cabang menu. */
    public function test_rute_boleh_lintas_cabang(): void
    {
        [$akar, $a1] = $this->pohonUji();
        $lain = 'uji-l-lain-' . Str::random(6);
        DB::table('access_path')->insert([
            ['id' => $lain, 'pid' => '', 'nama' => 'Cabang Lain', 'icon' => '', 'urutan' => 901, 'link' => 'uji-link-c', 'urutan_path' => null, 'pid_path' => null],
        ]);

        $m = $this->modul(['fitur_link' => ['uji-link-a', 'uji-link-c']]);
        $f = app(KatalogModul::class)->fiturModul($m);
        $this->assertSame(['uji-link-a', 'uji-link-c'], array_column($f, 'link'),
            'kepemilikan tak harus mengikuti bentuk pohon menu');
    }

    /** Link yang tak ada di menu lingkungan ini tak dihitung — dan tidak galat. */
    public function test_link_asing_tidak_dihitung_dan_tidak_galat(): void
    {
        $this->pohonUji();
        $m = $this->modul(['fitur_link' => ['uji-link-a', 'link-yang-tak-ada-di-menu']]);

        $f = app(KatalogModul::class)->fiturModul($m);
        $this->assertCount(1, $f, 'katalog menjanjikan halaman yang BISA DIBUKA di sini');
        $this->assertSame('uji-link-a', $f[0]['link']);
    }

    /** Modul tanpa rute & tanpa node → 0 fitur + "tanpa menu" (jangan berubah). */
    public function test_tanpa_rute_tetap_tanpa_menu(): void
    {
        $m = $this->modul(['nama' => 'Uji Tanpa Rute ' . Str::random(4), 'fitur_link' => null, 'access_id' => null]);

        $this->assertCount(0, app(KatalogModul::class)->fiturModul($m));
        $this->withSession($this->sesi('Head IT', 'uji-headit-l'))
            ->get(route('it-tracker.katalog', ['tampilan' => 'daftar']))->assertOk()
            ->assertSee($m->nama)->assertSee('tanpa menu');
    }

    // ── uji terima §14.27 dengan katalog sungguhan ────────────────────────

    /** Tiga yang diperbaiki naik; tiga yang sudah benar TIDAK bergeser. */
    public function test_tiga_koreksi_naik_tiga_patokan_tetap(): void
    {
        Artisan::call('it:seed-katalog', ['--env-guard' => 'local']);
        $k = app(KatalogModul::class);
        $n = fn (string $nama) => count($k->fiturModul(ItModul::where('nama', $nama)->firstOrFail()));

        // sebelum §14.27: 1 · 1 · 3
        $this->assertSame(2, $n('Reservasi'));
        $this->assertSame(3, $n('Master Jadwal & Layanan'));
        $this->assertSame(4, $n('Checklist Operasional'));

        // patokan yang TIDAK boleh rusak
        $this->assertSame(8, $n('Absensi'));
        $this->assertSame(6, $n('Konten'));
        $this->assertSame(6, $n('Pusat Akun'));

        // tanpa menu tetap nol
        $this->assertSame(0, $n('Integrasi Accurate'));
        $this->assertSame(0, $n('Integrasi Omnicare'));
        $this->assertSame(0, $n('Kontak & Percakapan'));
    }

    /** Jumlah link unik seluruh katalog tak melebihi jumlah halaman yang ada. */
    public function test_link_unik_tidak_melebihi_64(): void
    {
        Artisan::call('it:seed-katalog', ['--env-guard' => 'local']);
        $k = app(KatalogModul::class);

        $link = [];
        foreach (ItModul::all() as $m) {
            foreach ($k->fiturModul($m) as $f) $link[$f['link']] = true;
        }
        $this->assertLessThanOrEqual(64, count($link), 'katalog tak boleh mengaku punya halaman lebih banyak dari yang ada');
        $this->assertGreaterThan(40, count($link), 'dan tak boleh mendadak menyusut — itu tanda pemetaan putus');
    }

    /** Di luar produksi, halaman MENYEBUT bahwa angka pemakaian tak berarti di sini. */
    public function test_keterangan_lingkungan_di_luar_produksi(): void
    {
        $html = $this->withSession($this->sesi('Head IT', 'uji-headit-l'))
            ->get(route('it-tracker.katalog'))->assertOk()->getContent();

        if (app()->environment() === 'production') {
            $this->assertStringNotContainsString('itk-nota-lingkungan', $html);
            return;
        }
        $this->assertStringContainsString('itk-nota-lingkungan', $html);
        $this->assertStringContainsString('hanya berarti di produksi', $html);
    }

    /** KENDALI NEGATIF: papan tak menyentuh pemetaan fitur sama sekali. */
    public function test_papan_tak_terpengaruh(): void
    {
        Artisan::call('it:seed-katalog', ['--env-guard' => 'local']);

        $this->withSession($this->sesi('Head IT', 'uji-headit-l'))
            ->get(route('it-tracker.index'))->assertOk()
            ->assertDontSee('fitur_link', false)
            ->assertDontSee('itk-nota-lingkungan', false);
    }
}
