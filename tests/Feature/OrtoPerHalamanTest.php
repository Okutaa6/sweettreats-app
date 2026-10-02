<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — pemilih jumlah baris per halaman.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * Kaki tabel cuma berbunyi "Menampilkan 1–23 dari 23 jadwal" — jumlahnya
 * terkunci 50. Reza minta bisa diatur, seperti modul Reservasi.
 *
 * Yang dijaga berkas ini, dan yang kedua paling mudah lupa:
 *
 *   1. pilihannya benar-benar mengubah jumlah baris;
 *   2. ⚠ nilai di luar whitelist JATUH KE BAWAAN, tidak menarik semua.
 *      `?per_page=99999` akan menarik 1.639 baris prod dalam satu kueri —
 *      dan halaman ini mati pelan-pelan tanpa ada yang melapor;
 *   3. berpindah halaman tidak kehilangan pilihannya (withQueryString);
 *   4. mengubah jumlah baris MEMBUANG `page` — kalau tidak, orang di halaman 7
 *      memilih "100" lalu mendarat di halaman kosong.
 *
 * Fixture membuat 12 pasien sendiri lalu menyaring lewat `?q=` supaya data
 * lain di DB tidak ikut terhitung. Semuanya rollback DatabaseTransactions.
 */
class OrtoPerHalamanTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-PERHAL';

    /** Cukup untuk melewati 10 tapi masih di bawah 25 — membedakan dua pilihan. */
    private const JUMLAH = 12;

    private string $placeId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_case')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }
        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        $this->placeId = (string) array_key_first($peta);
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    private function semai(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $pid   = self::TANDA . '-' . Str::random(6);
            $kasus = OrtCase::create([
                'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
                'omnicare_pid' => $pid, 'patient_name' => self::TANDA . ' ' . $pid,
                'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
                'install_date' => Carbon::today()->subDays(400)->toDateString(),
                'status' => OrtCase::STATUS_AKTIF,
                'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
            ]);

            OrtControl::create([
                'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 1,
                'place_id' => $this->placeId, 'is_proposal' => 0,
                // Tanggal berbeda-beda supaya urutannya pasti dan halaman 2 berisi.
                'sch_date' => Carbon::today()->addDays($i)->toDateString(),
                'sch_source' => OrtControl::SCH_MANUAL, 'created_by' => 'uji',
            ]);
        }
    }

    private function halaman(array $query = []): string
    {
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

        $url = '/tracking-ortho?' . http_build_query(array_merge(
            ['utama' => $this->placeId, 'q' => self::TANDA], $query
        ));

        return $this->withSession([
            'isLoggedIn' => true, 'id' => 'uji-' . Str::random(8), 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Sales', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ])->get($url)->assertOk()->getContent();
    }

    /** Berapa baris pasien uji yang benar-benar terender. */
    private function jumlahBaris(string $html): int
    {
        preg_match_all('/' . preg_quote(self::TANDA, '/') . '-\w{6}/', $html, $m);

        return count(array_unique($m[0]));
    }

    /**
     * Pilihan per-halaman mana yang sedang ber-`selected`.
     *
     * ⚠ Dibaca lewat regex yang mentoleransi BARIS BARU: Blade memecah atribut
     *   `value` dan `selected` ke dua baris, jadi mencocokkan string
     *   `per_page=50" selected` akan gagal untuk alasan yang bukan bugnya.
     */
    private function perHalAktif(string $html): ?int
    {
        if (preg_match('~<option value="[^"]*per_page=(\d+)"\s+selected~', $html, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** Seluruh URL di dalam pemilih, sudah di-decode. @return list<string> */
    private function urlPemilih(string $html): array
    {
        preg_match_all('~<option value="([^"]*per_page=[^"]*)"~', $html, $m);

        return array_map('html_entity_decode', $m[1]);
    }

    // ── Uji ─────────────────────────────────────────────────────────────────

    public function test_pilihan_mengubah_jumlah_baris(): void
    {
        $this->semai(self::JUMLAH);

        $this->assertSame(10, $this->jumlahBaris($this->halaman(['per_page' => 10])),
            'per_page=10 harus memotong di 10 baris.');
        $this->assertSame(self::JUMLAH, $this->jumlahBaris($this->halaman(['per_page' => 25])),
            'per_page=25 memuat seluruh ' . self::JUMLAH . ' pasien uji.');
    }

    /** Bawaan tetap PER_HAL — tidak diturunkan diam-diam oleh fitur ini. */
    public function test_tanpa_parameter_memakai_bawaan(): void
    {
        $this->semai(self::JUMLAH);

        $html = $this->halaman();

        $this->assertSame(self::JUMLAH, $this->jumlahBaris($html));
        $this->assertSame(
            OrtQuery::PER_HAL, $this->perHalAktif($html),
            'Pilihan ' . OrtQuery::PER_HAL . ' harus ber-selected saat tanpa parameter.'
        );
    }

    /**
     * ⭐ PAGARNYA. Nilai di luar whitelist jatuh ke bawaan — bukan menarik semua.
     */
    public function test_nilai_liar_jatuh_ke_bawaan(): void
    {
        $this->semai(self::JUMLAH);

        foreach (['99999', 'abc', '0', '-5', '7'] as $liar) {
            $html = $this->halaman(['per_page' => $liar]);

            $this->assertSame(
                self::JUMLAH, $this->jumlahBaris($html),
                "per_page={$liar} harus jatuh ke bawaan " . OrtQuery::PER_HAL . ", bukan diikuti."
            );
            $this->assertSame(
                OrtQuery::PER_HAL, $this->perHalAktif($html),
                "per_page={$liar}: pilihan aktif harus kembali ke bawaan."
            );
        }
    }

    /** Pindah halaman tidak boleh kehilangan pilihannya. */
    public function test_tautan_halaman_membawa_per_page(): void
    {
        $this->semai(self::JUMLAH);

        $html = $this->halaman(['per_page' => 10]);

        $this->assertStringContainsString('page=2', $html, 'Prasyarat: 12 pasien / 10 = ada halaman 2.');
        $this->assertMatchesRegularExpression(
            '~href="[^"]*per_page=10[^"]*page=2|href="[^"]*page=2[^"]*per_page=10~', $html,
            'Tautan halaman 2 wajib membawa per_page=10.'
        );
    }

    /**
     * Mengubah jumlah baris MEMBUANG `page`. Tanpa ini, orang di halaman 2
     * memilih 25 lalu mendarat di halaman kosong — 12 pasien tidak punya
     * halaman 2 kalau satu halaman memuat 25.
     */
    public function test_mengubah_jumlah_membuang_nomor_halaman(): void
    {
        $this->semai(self::JUMLAH);

        $html = $this->halaman(['per_page' => 10, 'page' => 2]);

        $url = $this->urlPemilih($html);
        $this->assertNotEmpty($url, 'Prasyarat: pemilih terender.');

        foreach ($url as $u) {
            /* ⚠ JANGAN cek substring 'page=2' — `per_page=25` memuatnya, dan
               ujinya merah untuk alasan yang sama sekali bukan bugnya. Query
               string-nya diurai dulu, lalu kuncinya yang diperiksa. */
            parse_str((string) parse_url($u, PHP_URL_QUERY), $q);

            $this->assertArrayNotHasKey(
                'page', $q,
                "URL pemilih tidak boleh membawa nomor halaman: {$u}"
            );
            $this->assertArrayHasKey('per_page', $q, "URL pemilih wajib membawa per_page: {$u}");
        }
    }

    /** Pemilihnya ada di SEMUA daftar, bukan cuma tab List. */
    public function test_pemilih_ada_di_roster_dan_antrean(): void
    {
        $this->semai(3);

        foreach ([['tab' => 'pasien'], ['kartu' => 'backlog']] as $query) {
            $this->assertStringContainsString(
                'data per halaman', str_replace(['jadwal per halaman', 'pasien per halaman'], 'data per halaman',
                    $this->halaman($query)),
                'Pemilih harus ada di ' . json_encode($query)
            );
        }
    }
}
