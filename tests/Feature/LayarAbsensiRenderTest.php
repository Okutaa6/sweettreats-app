<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ketiga layar yang disentuh benar-benar RENDER, dan kendalinya benar-benar
 * TERCETAK di dalamnya.
 *
 * ⚠️ Kode status tak dipakai sebagai bukti: RBAC di aplikasi ini memantulkan
 * 302 ke beranda, dan pemeriksaan yang berhenti di "200" sudah pernah melapor
 * hijau palsu. Tiap uji memeriksa ISI halamannya.
 *
 * Ini pengganti mesin untuk "dilihat mata sekali" — bukan penggantinya untuk
 * urusan rasa, tapi cukup untuk menjawab "apakah tombolnya ada di sana".
 */
class LayarAbsensiRenderTest extends TestCase
{
    use DatabaseTransactions;

    private object $finance;

    protected function setUp(): void
    {
        parent::setUp();

        // Akun Finance SUNGGUHAN: AuthGuard memeriksa akses menu lewat
        // group_id, bukan group_name, jadi persona karangan tertahan 302.
        $u = DB::table('users as u')
            ->join('group_path as g', 'g.id', '=', 'u.group_id')
            ->where('u.status', 1)->where('g.nama', 'Finance')
            ->first(['u.id', 'u.nama', 'u.group_id', 'u.raw_branch_id']);

        if (! $u) $this->markTestSkipped('Tak ada akun Finance di environment ini.');

        $this->finance = $u;
    }

    private function buka(string $url)
    {
        return $this->withSession([
            'isLoggedIn' => true,
            'id'         => $this->finance->id,
            'group_id'   => $this->finance->group_id,
            'group_name' => 'Finance',
            'name'       => 'Uji Finance',
            'raw_branch_id' => $this->finance->raw_branch_id,
            'branch_day' => date('Y-m-d'),
            'expires_in' => time() + 3600,
            'login_at'   => time(),
            'pa_touch'   => time(),
        ])->get($url);
    }

    public function test_layar_koreksi_absen_render_dengan_formulirnya(): void
    {
        $res = $this->buka('/absensi/koreksi');
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString('Koreksi absen', $html);
        $this->assertStringContainsString('name="alasan"', $html, 'Alasan wajib — kolomnya harus ada.');
        $this->assertStringContainsString('name="work_date"', $html);
        $this->assertStringContainsString('periode gaji yang sudah ditutup', $html,
            'Batasnya harus disebut di layar, bukan cuma ditegakkan di server.');
    }

    public function test_layar_kehadiran_memuat_formulir_ekspor_rentang(): void
    {
        $res = $this->buka('/absensi/kehadiran');
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString('khExportForm', $html);
        $this->assertStringContainsString('Ekspor CSV', $html);
        $this->assertStringContainsString('khExpBranch', $html,
            'Penyaring cabang harus ikut terkirim — kalau tidak, unduhan beda dari yang dilihat.');
    }

    public function test_layar_lembar_gaji_memajang_ambang_dodo(): void
    {
        $res = $this->buka('/absensi/lembar-gaji');
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString('Ambang DODO', $html);
        $this->assertStringContainsString('name="ambang_menit"', $html);
        $this->assertStringContainsString('DODO', $html);
    }

    public function test_tab_koreksi_absen_muncul_di_deret_tab(): void
    {
        // Fitur yang tak punya jalan masuk sama saja dengan tak ada.
        $res = $this->buka('/absensi/periode');
        $res->assertStatus(200);

        $this->assertStringContainsString('Koreksi Absen', $res->getContent());
    }
}
