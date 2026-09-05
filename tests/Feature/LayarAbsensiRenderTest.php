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

    /** Sesi Finance sungguhan — group_id-nya yang menentukan akses menu. */
    private function sesi(): array
    {
        return [
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
        ];
    }

    private function buka(string $url)
    {
        return $this->withSession($this->sesi())->get($url);
    }

    private function kirim(string $url, array $data)
    {
        return $this->withSession($this->sesi())->post($url, $data);
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

    public function test_layar_koreksi_menjelaskan_kenapa_cuti_tak_ada(): void
    {
        // Pilihan yang hilang tanpa keterangan terbaca sebagai layar rusak.
        $html = $this->buka('/absensi/koreksi')->getContent();

        $this->assertStringContainsString('sengaja tidak ada', $html);
        $this->assertStringNotContainsString('<option value="cuti"', $html,
            'Dropdown status tak boleh menawarkan cuti.');
    }

    public function test_post_cuti_langsung_ke_server_tetap_ditolak(): void
    {
        // Tombol yang disembunyikan bukan pagar — form buatan sendiri tetap bisa
        // mengirim. Pagarnya harus di server, dan pesannya harus menerangkan.
        $res = $this->kirim('/absensi/koreksi', [
            'user_id'   => $this->finance->id,
            'work_date' => now()->subDays(2)->toDateString(),
            'status'    => 'cuti',
            'alasan'    => 'uji otomatis — seharusnya ditolak pagar buku besar',
        ]);

        $res->assertSessionHas('error');
        $this->assertStringContainsString('Sisa Cuti', (string) session('error'));
    }

    public function test_akun_bersama_tak_boleh_menyetel_ambang_dodo(): void
    {
        // Ambang DODO menentukan uang. Kalau disetel dari akun BERSAMA,
        // audit_log mencatat nama yang tak menunjuk siapa pun — dan jejak yang
        // tak bisa menunjuk orang bukan jejak. (Keputusan Ekky, 5 Sep 2026.)
        foreach (['Admin', 'Admin Backup'] as $bersama) {
            $sesi = $this->sesi();
            $sesi['group_name'] = $bersama;

            $res = $this->withSession($sesi)->get('/absensi/lembar-gaji');

            $this->assertSame(403, $res->getStatusCode(),
                "Akun bersama \"{$bersama}\" tak boleh membuka Lembar Gaji.");
        }
    }

    public function test_role_perorangan_tetap_boleh(): void
    {
        // Kendali negatif: pencabutan di atas tak boleh ikut menutup orang yang
        // memang berhak — termasuk Admin General, yang dipakai Ekky sendiri.
        foreach (['Finance', 'Admin General', 'Head IT'] as $role) {
            $sesi = $this->sesi();
            $sesi['group_name'] = $role;

            $res = $this->withSession($sesi)->get('/absensi/lembar-gaji');

            $this->assertSame(200, $res->getStatusCode(), "{$role} harus tetap bisa membuka Lembar Gaji.");
        }
    }
    public function test_tab_koreksi_absen_muncul_di_deret_tab(): void
    {
        // Fitur yang tak punya jalan masuk sama saja dengan tak ada.
        $res = $this->buka('/absensi/periode');
        $res->assertStatus(200);

        $this->assertStringContainsString('Koreksi Absen', $res->getContent());
    }
}
