<?php

namespace Tests\Feature;

use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — kolom yang BELUM PERNAH dipakai dijelaskan sekali di atas
 * tabel, bukan dibiarkan terbaca sebagai kegagalan orang.
 *
 * TEMUAN YANG MELAHIRKAN BERKAS INI (audit HUB, 1 Okt 2026). Di prod kolom
 * RESERVASI berisi `X` untuk 35.317 baris tanpa kecuali, dan kolom FOLLOW-UP
 * juga nol — padahal Sales sudah menelepon puluhan orang, catatannya saja yang
 * masih di Google Sheets. Satu kolom penuh `X` berhenti terbaca "belum
 * terjadi" dan mulai terbaca "Sales tidak bekerja".
 *
 * Keputusan rancangannya sengaja berkompromi, dan itu yang dikunci di sini:
 *
 *   • `X` per baris TETAP. Brief §kamus menetapkannya — "sel kosong terasa
 *     seperti data hilang, X terasa seperti fakta" — dan per baris itu benar.
 *   • Yang ditambahkan cuma SPANDUK, dan cuma saat SELURUH kolom kosong.
 *   • Spanduknya HILANG SENDIRI begitu kolomnya mulai terisi. Ini bagian yang
 *     paling mudah lupa diuji dan paling memalukan kalau salah: spanduk
 *     "belum pernah dipakai" yang masih tampil setelah dipakai membuat
 *     halamannya berbohong.
 *
 * Semua perubahan data ikut rollback DatabaseTransactions.
 */
class OrtoKolomKosongTest extends TestCase
{
    use DatabaseTransactions;

    private string $placeId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_control')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }
        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }
        if (! app(OrtBranches::class)->all()) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        // Cabang yang paling banyak punya baris kontrol — supaya langkah
        // "isi satu" di bawah benar-benar punya baris untuk diisi.
        $pid = DB::table('ort_control')->select('place_id')
            ->groupBy('place_id')->orderByRaw('COUNT(*) DESC')->value('place_id');

        if (! $pid) {
            $this->markTestSkipped('Belum ada baris ort_control di DB ini.');
        }

        $this->placeId = (string) $pid;
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    /** Kosongkan kedua kolom di cabang uji — keadaan prod hari pertama. */
    private function kosongkan(): void
    {
        OrtControl::where('place_id', $this->placeId)->update([
            'fu_date' => null, 'reservation_id' => null, 'rsv_date' => null, 'rsv_status' => null,
        ]);
    }

    private function isiSatu(string $kolom, string $nilai): void
    {
        $id = OrtControl::where('place_id', $this->placeId)->value('id');
        OrtControl::where('id', $id)->update([$kolom => $nilai]);
    }

    /**
     * Supervisor sengaja: ia tidak punya cabang, jadi cabang yang dilihat bisa
     * ditentukan lewat `?utama=` dan tampilannya BUKAN lihat-saja — spanduk ini
     * memang hanya untuk orang yang mengerjakan cabangnya sendiri.
     */
    private function halaman(): string
    {
        $gid = DB::table('group_path')->where('nama', 'Supervisor')->value('id');
        if (! $gid) {
            $this->markTestSkipped("Role 'Supervisor' tidak ada di DB ini.");
        }

        $node = DB::table('access_path')->where('link', 'tracking-ortho')->first(['id', 'pid']);
        foreach (array_filter([$node->id, $node->pid]) as $accessId) {
            $ada = DB::table('group_access')->where('group_id', $gid)->where('access_id', $accessId)->exists();
            if (! $ada) {
                DB::table('group_access')->insert([
                    'id' => (string) Str::uuid(), 'group_id' => $gid, 'access_id' => $accessId,
                ]);
            }
        }

        return $this->withSession([
            'isLoggedIn' => true, 'id' => 'uji-' . Str::random(8), 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Kolom Kosong', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ])->get('/tracking-ortho?utama=' . $this->placeId)->assertOk()->getContent();
    }

    private function adaSpanduk(string $html): bool
    {
        return str_contains($html, 'belum pernah dipakai di');
    }

    // ── Mesin keputusannya ──────────────────────────────────────────────────

    public function test_kedua_kolom_kosong_terdeteksi(): void
    {
        $this->kosongkan();

        $k = app(OrtQuery::class)->kolomBelumDipakai($this->placeId);

        $this->assertTrue($k['fu'], 'fu_date nol di seluruh cabang → belum dipakai.');
        $this->assertTrue($k['rsv'], 'reservation_id nol di seluruh cabang → belum dipakai.');
    }

    /** ⭐ SATU baris terisi sudah cukup — kolomnya bukan lagi "belum pernah". */
    public function test_satu_baris_terisi_membatalkan_klaim_belum_dipakai(): void
    {
        $this->kosongkan();
        $this->isiSatu('fu_date', now()->toDateString());

        $k = app(OrtQuery::class)->kolomBelumDipakai($this->placeId);

        $this->assertFalse($k['fu'], 'Satu baris terisi sudah membuat klaim "belum pernah" salah.');
        $this->assertTrue($k['rsv'], 'Kolom yang satunya tidak ikut terpengaruh.');
    }

    public function test_tiap_cabang_dihitung_sendiri(): void
    {
        $lain = DB::table('ort_control')->where('place_id', '<>', $this->placeId)
            ->whereNotNull('fu_date')->value('place_id');

        if (! $lain) {
            $this->markTestSkipped('Butuh cabang lain yang fu_date-nya sudah terisi.');
        }

        $this->kosongkan();

        $this->assertTrue(app(OrtQuery::class)->kolomBelumDipakai($this->placeId)['fu']);
        $this->assertFalse(
            app(OrtQuery::class)->kolomBelumDipakai((string) $lain)['fu'],
            'Mengosongkan satu cabang tidak boleh ikut menjelaskan cabang lain.'
        );
    }

    // ── Yang dilihat pengguna ───────────────────────────────────────────────

    public function test_spanduk_muncul_saat_kolom_belum_pernah_dipakai(): void
    {
        $this->kosongkan();
        $html = $this->halaman();

        $this->assertTrue($this->adaSpanduk($html), 'Spanduk penjelas harus muncul.');
        $this->assertStringContainsString('Follow-up dan Reservasi', $html, 'Kedua kolom disebut.');
        $this->assertStringContainsString('belum tercatat di sistem', $html, 'Inti pesannya: belum tercatat, bukan belum dikerjakan.');
    }

    /** ⭐ Yang paling memalukan kalau salah: spanduk yang tidak mau pergi. */
    public function test_spanduk_hilang_setelah_kolom_terpakai(): void
    {
        $this->kosongkan();
        $this->assertTrue($this->adaSpanduk($this->halaman()), 'Prasyarat: spanduk memang sedang tampil.');

        $this->isiSatu('fu_date', now()->toDateString());
        $rsv = DB::table('raw_cus_reservation')->value('id');
        $this->isiSatu('reservation_id', $rsv ? (string) $rsv : 'uji-rsv');

        $this->assertFalse(
            $this->adaSpanduk($this->halaman()),
            'Sesudah kolomnya dipakai, spanduk "belum pernah dipakai" harus hilang sendiri.'
        );
    }

    /** Hanya satu kolom yang kosong → hanya kolom itu yang disebut. */
    public function test_hanya_kolom_yang_kosong_yang_disebut(): void
    {
        $this->kosongkan();
        $this->isiSatu('fu_date', now()->toDateString());

        $html = $this->halaman();

        $this->assertTrue($this->adaSpanduk($html));
        $this->assertStringContainsString('Kolom Reservasi belum pernah dipakai', $html);
        $this->assertStringNotContainsString('Follow-up dan Reservasi', $html, 'Follow-up sudah dipakai — jangan ikut disebut.');
    }

    /**
     * `X` per baris TIDAK dihapus. Spanduk ini menambah penjelasan, bukan
     * menggantikan lambangnya — brief §kamus menetapkan X dipertahankan.
     */
    public function test_tanda_x_per_baris_tetap_ada(): void
    {
        $this->kosongkan();

        $this->assertStringContainsString('ort-x', $this->halaman(), 'X per baris tetap dirender.');
    }
}
