<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — HASIL follow-up (tahap 4 dari prompt 4 Okt 2026).
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * Sampai hari ini "Catat Follow-up" sekali klik tanpa form: tanggal, kanal
 * bawaan WA, dan siapa. HASIL teleponnya — bersedia, tidak respon, minta
 * jadwal ulang, menolak, salah nomor — tidak pernah tercatat. Reza memilih
 * PILIHAN TETAP + CATATAN BEBAS lewat lembar samping (varian ke-5).
 *
 * Yang dikunci di sini, dan urutannya mengikuti jalur Sales:
 *   1. kamus hasil dijaga validasi — teks bebas di `hasil` DITOLAK
 *   2. hasil + catatan tersimpan bersama fu_date/kanal/siapa
 *   3. satu Urungkan memulihkan KEDUANYA — kalau tidak, Urungkan memulihkan
 *      tanggal tapi meninggalkan hasil yang salah (lebih buruk dari tidak ada)
 *   4. Batalkan follow-up mengosongkan keduanya
 *   5. lembar ?sheet=follow-up merender lima pilihan dari OrtControl::FU_HASIL
 *      dan form-nya mengarah ke /follow-up (supaya tercegat POLA di JS)
 *   6. tabel TIDAK lagi merender form sekali klik — tombolnya pembuka lembar
 *
 * Jalur lama (POST tanpa `hasil`) SENGAJA tetap sah: nullable, bukan required.
 * Berkas uji lain masih memakainya, dan itu bukan sesuatu yang perlu dipaksa
 * berubah hari ini.
 */
class OrtoHasilFollowUpTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-HASILFU';

    private string $placeId;
    private array  $sesi;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_control') || ! Schema::hasColumn('ort_control', 'fu_result')) {
            $this->markTestSkipped('Jalankan database/sql/2026_10_05_ort_hasil_followup.sql dulu.');
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

        // Satu sesi untuk seluruh uji: token Urungkan terikat ke session('id').
        $this->sesi = [
            'isLoggedIn' => true, 'id' => 'uji-' . Str::random(8), 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Sales', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ];
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    /** Satu kontrol TERBUKA tanpa follow-up, milik cabang ini. */
    private function kontrol(array $ubah = []): OrtControl
    {
        $pid   = self::TANDA . '-' . Str::random(6);
        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji HasilFU ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::today()->subDays(300)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);

        return OrtControl::create(array_merge([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => Carbon::today()->toDateString(),
            'sch_source' => OrtControl::SCH_MANUAL, 'created_by' => 'uji',
        ], $ubah));
    }

    private function utama(): array
    {
        return ['utama' => $this->placeId];
    }

    // ── 1. Kamus dijaga ─────────────────────────────────────────────────────

    public function test_hasil_di_luar_kamus_ditolak(): void
    {
        $ctl = $this->kontrol();

        $resp = $this->withSession($this->sesi)
            ->from('/tracking-ortho?' . http_build_query($this->utama()))
            ->post('/tracking-ortho/kontrol/' . $ctl->id . '/follow-up', ['hasil' => 'pasien lagi sibuk']);

        $resp->assertSessionHasErrors('hasil');
        $this->assertNull($ctl->refresh()->fu_date, 'Validasi gagal = tidak ada satu kolom pun yang ditulis.');
        $this->assertNull($ctl->fu_result);
    }

    // ── 2. Tersimpan bersama ────────────────────────────────────────────────

    public function test_hasil_dan_catatan_tersimpan(): void
    {
        $ctl = $this->kontrol();

        $this->withSession($this->sesi)
            ->post('/tracking-ortho/kontrol/' . $ctl->id . '/follow-up', [
                'hasil'   => OrtControl::FU_MINTA_JADWAL_ULANG,
                'kanal'   => 'telepon',
                'catatan' => '  minta dihubungi lagi Kamis sore  ',
            ])->assertRedirect();

        $ctl->refresh();
        $this->assertSame(OrtControl::FU_MINTA_JADWAL_ULANG, $ctl->fu_result);
        $this->assertSame('minta dihubungi lagi Kamis sore', $ctl->fu_note, 'Catatan di-trim.');
        $this->assertSame('telepon', $ctl->fu_channel);
        $this->assertNotNull($ctl->fu_date);
    }

    /** Jalur lama (tanpa `hasil`) tetap sah — nullable, bukan required. */
    public function test_tanpa_hasil_tetap_sah(): void
    {
        $ctl = $this->kontrol();

        $this->withSession($this->sesi)
            ->post('/tracking-ortho/kontrol/' . $ctl->id . '/follow-up')->assertRedirect();

        $ctl->refresh();
        $this->assertNotNull($ctl->fu_date);
        $this->assertNull($ctl->fu_result, 'Tidak dikirim = NULL, bukan dikarang.');
    }

    // ── 3. Urungkan memulihkan KEDUANYA ─────────────────────────────────────

    public function test_urungkan_memulihkan_hasil_dan_catatan(): void
    {
        $ctl = $this->kontrol([
            'fu_date' => Carbon::today()->subDays(3)->toDateString(), 'fu_channel' => 'wa', 'fu_by' => 'Lama',
            'fu_result' => OrtControl::FU_TIDAK_RESPON, 'fu_note' => 'catatan lama',
        ]);

        $resp = $this->withSession($this->sesi)
            ->post('/tracking-ortho/kontrol/' . $ctl->id . '/follow-up', [
                'hasil' => OrtControl::FU_DIHUBUNGI, 'catatan' => 'catatan baru',
            ]);
        $token = $resp->getSession()->get('ort_undo');
        $this->assertNotEmpty($token, 'Aksi tulis harus mengeluarkan token Urungkan.');
        $this->assertSame(OrtControl::FU_DIHUBUNGI, $ctl->refresh()->fu_result);

        $this->withSession($this->sesi)
            ->post('/tracking-ortho/urungkan/' . $token)->assertRedirect();

        $ctl->refresh();
        $this->assertSame(OrtControl::FU_TIDAK_RESPON, $ctl->fu_result, 'Urungkan harus memulihkan HASIL lama.');
        $this->assertSame('catatan lama',               $ctl->fu_note,   'Urungkan harus memulihkan CATATAN lama.');
        $this->assertSame('Lama',                       $ctl->fu_by);
    }

    // ── 4. Batalkan follow-up mengosongkan keduanya ─────────────────────────

    public function test_batal_follow_up_mengosongkan_hasil_dan_catatan(): void
    {
        $ctl = $this->kontrol([
            'fu_date' => Carbon::today()->toDateString(), 'fu_channel' => 'wa', 'fu_by' => 'Uji',
            'fu_result' => OrtControl::FU_SALAH_NOMOR, 'fu_note' => 'nomor tidak aktif',
        ]);

        $this->withSession($this->sesi)
            ->post('/tracking-ortho/kontrol/' . $ctl->id . '/batal-follow-up')->assertRedirect();

        $ctl->refresh();
        $this->assertNull($ctl->fu_date);
        $this->assertNull($ctl->fu_result, 'Membatalkan panggilan = hasilnya ikut tidak dihitung.');
        $this->assertNull($ctl->fu_note);
    }

    // ── 5 & 6. Layar ────────────────────────────────────────────────────────

    public function test_lembar_merender_lima_pilihan_dan_mengarah_ke_follow_up(): void
    {
        $ctl = $this->kontrol();

        $html = $this->withSession($this->sesi)
            ->get('/tracking-ortho?' . http_build_query($this->utama() + ['sheet' => 'follow-up', 'kontrol' => $ctl->id]))
            ->assertOk()->getContent();

        foreach (OrtControl::FU_HASIL as $val => $label) {
            $this->assertStringContainsString('value="' . $val . '"', $html, "Pilihan '{$val}' harus ada di lembar.");
        }
        $this->assertMatchesRegularExpression(
            '~<form id="ortSheetForm" method="POST" action="[^"]*/kontrol/' . preg_quote($ctl->id, '~') . '/follow-up"~',
            $html,
            'Form lembar harus mengarah ke /follow-up supaya tercegat POLA submit realtime.'
        );
        $this->assertStringContainsString('name="hasil"', $html);
        $this->assertStringContainsString('name="catatan"', $html);
    }

    public function test_tabel_membuka_lembar_bukan_submit_sekali_klik(): void
    {
        $ctl = $this->kontrol();

        $html = $this->withSession($this->sesi)
            ->get('/tracking-ortho?' . http_build_query($this->utama()))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '~<a href="[^"]*sheet=follow-up[^"]*kontrol=' . preg_quote($ctl->id, '~') . '[^"]*"~',
            $html,
            'Tombol Catat Follow-up di tabel harus membuka lembar.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~<form method="POST" action="[^"]*/kontrol/' . preg_quote($ctl->id, '~') . '/follow-up"~',
            $html,
            'Form sekali klik untuk baris ini tidak boleh ada lagi di tabel.'
        );
    }
}
