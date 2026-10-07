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
 * Pita "kontrol #N selesai — hadir 1 Okt · terisi sendiri dari rekam medis".
 *
 * ────────────────────────────────────────────────────────────────────────────
 * APA ITU, DAN KENAPA IA ADA
 * ────────────────────────────────────────────────────────────────────────────
 * Saat sistem mengusulkan jadwal baru (is_proposal), barisnya muncul dengan
 * tanggal yang tidak pernah diminta siapa pun. Pita ini menjelaskan DARI MANA
 * usulan itu datang: kontrol sebelumnya sudah selesai, dan tanggal kunjungannya
 * terisi sendiri dari rekam medis. Tanpa itu, tanggal usulan terbaca seperti
 * janji yang entah siapa buat.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG DIJAGA DI SINI
 * ────────────────────────────────────────────────────────────────────────────
 *   1. Pita dan tombol tutupnya SELALU berpasangan, dan keduanya memakai id
 *      yang sama dengan baris di atasnya.
 *
 *   2. ⚠ `.ort-done[hidden] { display: none }` ADA di CSS. Ini yang paling
 *      mudah hilang tanpa suara: .ort-done itu `display: flex`, dan flex
 *      MENGALAHKAN `[hidden]` bawaan peramban. Hilangkan satu baris CSS itu,
 *      dan tombol × tetap "berfungsi" — localStorage terisi, atribut hidden
 *      terpasang — tapi pitanya tidak pernah menghilang dari layar. Tidak ada
 *      galat, tidak ada uji HTTP yang bisa melihatnya.
 *
 *   3. Pelepasan `ort-tr--nb`. Kelas itu membuang garis bawah baris di atas
 *      DAN memangkas padding-nya jadi 7px, justru karena pita ini menempel di
 *      bawahnya. Disembunyikan tanpa melepasnya, barisnya berakhir tanpa garis
 *      dan lebih pendek dari tetangganya — jadi skripnya wajib menyebutnya.
 */
class OrtoPitaSelesaiTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJIPITA';

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

    /**
     * Pasien dengan DUA kontrol: satu sudah selesai dengan kunjungan, satu lagi
     * usulan yang terbuka. Persis bentuk yang memunculkan pita itu.
     *
     * @return string id kontrol terbuka — kunci pita-nya
     */
    private function pasienBerpita(string $nama): string
    {
        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => self::TANDA . '-' . Str::random(6),
            'patient_name' => $nama,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::today()->subDays(400)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'last_visit_date' => Carbon::today()->subDays(6)->toDateString(),
            'median_gap_days' => 30,
            'control_count' => 2, 'visit_count' => 1, 'visit_count_180d' => 1,
        ]);

        // Yang SUDAH selesai — inilah yang diceritakan pita.
        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date'   => Carbon::today()->subDays(7)->toDateString(),
            'visit_date' => Carbon::today()->subDays(6)->toDateString(),
            'visit_source' => 'rekam_medis',
            'closed_at' => Carbon::now()->subDays(6),
            'sch_source' => OrtControl::SCH_SISTEM, 'created_by' => 'uji',
        ]);

        // Usulan yang terbuka — barisnya yang tampil di daftar kerja.
        $buka = OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 2,
            'place_id' => $this->placeId, 'is_proposal' => 1,
            'sch_date' => Carbon::today()->addDays(24)->toDateString(),
            'sch_source' => OrtControl::SCH_SISTEM, 'created_by' => 'uji',
        ]);
        $kasus->forceFill(['open_control_id' => $buka->id])->save();

        return $buka->id;
    }

    private function halaman(string $cari): string
    {
        return $this->withSession($this->sesi)
            ->get('/tracking-ortho?' . http_build_query([
                'utama' => $this->placeId, 'q' => $cari,
            ]))->assertOk()->getContent();
    }

    // ════════════════════════════════════════════════════════════════════════

    /** ⭐ Pita, tombol tutupnya, dan barisnya memakai SATU id yang sama. */
    public function test_pita_membawa_tombol_tutup_berid_sama(): void
    {
        $nama = 'Uji Pita ' . Str::random(5);
        $id   = $this->pasienBerpita($nama);

        $html = $this->halaman($nama);

        $this->assertStringContainsString('kontrol #1 selesai', $html,
            'Prasyarat: pitanya memang muncul untuk bentuk data ini.');

        $this->assertStringContainsString('data-ort-done="' . $id . '"', $html,
            'Pita dikunci ke id kontrol terbuka.');
        $this->assertStringContainsString('data-ort-done-x="' . $id . '"', $html,
            'Dan membawa tombol tutupnya.');

        /* Id yang sama dengan barisnya — skripnya memakai ini untuk melepas
           ort-tr--nb dari baris di atas. Kalau keduanya berbeda, pita
           menghilang tapi barisnya ditinggal tanpa garis bawah. */
        $this->assertStringContainsString('data-ort-row="' . $id . '"', $html,
            'Pita dan baris di atasnya memakai id yang sama.');
    }

    /** Jumlah pita dan jumlah tombol tutup tidak boleh berbeda. */
    public function test_tiap_pita_punya_tepat_satu_tombol(): void
    {
        $nama = 'Uji Pita ' . Str::random(5);
        $this->pasienBerpita($nama);

        /* ⚠ <script> DIBUANG dulu, dan ini aturan umum untuk uji yang
             MENCACAH penanda — bukan kehati-hatian berlebihan.

             Versi pertama skrip pita menyusun pemilihnya sebagai string
             ('[data-ort-row="' + id + '"]'), dan itu membuat SEMBILAN uji merah
             sekaligus: OrtoPerHalamanTest dan OrtoTahapCHalamanTest menghitung
             baris dengan mencacah penanda di seluruh halaman, jadi setiap
             halaman terbaca punya satu baris lebih banyak. Skripnya sudah
             ditulis ulang tanpa literal itu; pembuang ini tetap di sini supaya
             uji ini tidak ikut pecah kalau suatu hari ada yang menambahkannya
             kembali. */
        $html = preg_replace('~<script.*?</script>~s', '', $this->halaman($nama));

        $this->assertSame(
            substr_count($html, 'data-ort-done="'),
            substr_count($html, 'data-ort-done-x="'),
            'Setiap pita wajib bisa ditutup — pita tanpa tombol tidak bisa dihilangkan sama sekali.'
        );
    }

    /**
     * ⭐ Aturan CSS yang membuat tombolnya benar-benar bekerja.
     *
     * Dijaga di sini karena TIDAK ADA uji HTTP yang bisa melihatnya: tanpa
     * baris ini, `hidden` tetap terpasang dan localStorage tetap terisi —
     * pitanya saja yang tidak pernah hilang, karena `display: flex` miliknya
     * mengalahkan `[hidden]` bawaan peramban.
     */
    public function test_css_memaksa_pita_benar_benar_hilang(): void
    {
        $css = file_get_contents(public_path('assets/css/ort-tracking.sweettreats.css'));

        $this->assertMatchesRegularExpression(
            '~\.ort-done\[hidden\]\s*\{\s*display:\s*none~', $css,
            '.ort-done itu display:flex — tanpa aturan ini, atribut hidden tidak menyembunyikan apa pun.'
        );
    }
}
