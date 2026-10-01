<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtUsulan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — panggilan telepon mendahului tanggal.
 *
 * TEMUAN YANG MELAHIRKAN BERKAS INI. Tangga tombol lama menaruh
 * `is_proposal => 'konfirmasi'` sebagai arm PERTAMA, jadi tombol Follow-up
 * **mustahil** muncul di baris usulan. Sales dipaksa menyetujui tanggal lebih
 * dulu, baru boleh menelepon — terbalik dari lapangan, di mana panggilan untuk
 * MENANYAKAN kapan pasien bisa datang justru mendahului penentuan tanggalnya.
 *
 * Dan tanggal itu, untuk 76% pasien, adalah tebakan dua minggu (terukur prod:
 * hanya 24% pasien beririma rapat). Tombol sekali klik tanpa dialog di atas
 * tebakan sebesar itu mencatat kesepakatan yang belum pernah terjadi — brief
 * Revisi 3 menyangka tanggalnya sudah disepakati dokter dan pasien di kursi
 * perawatan, dan Ikhsan mengoreksinya 1 Okt: SALES yang menentukan.
 *
 * Dua hal yang dijaga di sini, dan yang kedua lebih penting:
 *
 *   • pemilih tanggalnya adalah MESIN PAGAR YANG SAMA dengan yang melahirkan
 *     usulan, jadi ia tidak pernah bisa menawarkan hari mustahil, dan
 *   • ⭐ satu panggilan = satu penulisan, dan satu Urungkan memulihkan
 *     KEDUANYA. Kalau Urungkan hanya memulihkan follow-up, jadwal yang telanjur
 *     terpasang tertinggal — lebih membingungkan daripada tidak ada Urungkan.
 */
class OrtoPanggilanTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-PANGGIL';

    private string $placeId;
    private string $branchId;
    private string $doctorRef = 'UJI-PANGGIL-DOC';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_control')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }
        if (! Schema::hasColumn('ort_case', 'gap_spread_pct')) {
            $this->markTestSkipped('Jalankan database/sql/2026_10_02_ort_sebaran_irama.sql dulu.');
        }
        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        $this->placeId  = (string) array_key_first($peta);
        $this->branchId = (string) $peta[$this->placeId]['branch_id'];
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    /** @param list<int> $hariIso 1 Senin … 7 Minggu */
    private function dokterPraktik(array $hariIso): void
    {
        $docId = 'uji-panggil-doc-' . Str::random(6);
        DB::table('raw_doctor')->insert([
            'id' => $docId, 'name' => 'drg. Uji Panggil',
            'omnicare_doc_id' => $this->doctorRef, 'is_active' => 1,
        ]);

        foreach ($hariIso as $h) {
            DB::table('raw_doctor_schedule')->insert([
                'id' => (string) Str::uuid(), 'raw_doctor_id' => $docId,
                'raw_branch_id' => $this->branchId, 'day' => $h,
                'start_hour' => '10:00:00', 'end_hour' => '20:00:00', 'type' => 'work',
            ]);
        }
    }

    /**
     * Satu pasien dengan riwayat berpola, plus satu baris terbuka.
     *
     * @param  list<int>  $selang  jarak antar kunjungan; menentukan tingkat irama
     */
    private function pasien(array $selang, array $kontrolUbah = []): OrtControl
    {
        $pid     = self::TANDA . '-' . Str::random(6);
        $hariIni = Carbon::today();

        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji Panggil ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => $hariIni->copy()->subDays(800)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF, 'doctor_ref_id' => $this->doctorRef,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);

        $riwayat = [];
        $tgl     = $hariIni->copy();
        foreach (array_reverse($selang) as $s) {
            $tgl = $tgl->copy()->subDays($s);
            $riwayat[] = $tgl->toDateString();
        }

        $seq = 0;
        foreach (array_reverse($riwayat) as $t) {
            OrtControl::create([
                'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => ++$seq,
                'place_id' => $this->placeId, 'is_proposal' => 0,
                'sch_date' => $t, 'sch_source' => OrtControl::SCH_REKAM_MEDIS,
                'visit_date' => $t, 'visit_source' => 'rekam_medis', 'visit_confidence' => 'pasti',
                'outcome' => OrtControl::OUT_HADIR, 'closed_at' => now(),
                'closed_by' => 'uji', 'created_by' => 'uji',
            ]);
        }

        app(\App\Services\Ortho\OrtRollup::class);

        $irama = OrtCase::irama($riwayat);
        $kasus->forceFill([
            'median_gap_days' => $irama['median'],
            'gap_spread_pct'  => $irama['sebar'],
            'last_visit_date' => $hariIni->copy()->subDays(end($selang))->toDateString(),
        ])->save();

        return OrtControl::create(array_merge([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => ++$seq,
            'place_id' => $this->placeId, 'is_proposal' => 1,
            'sch_date' => $hariIni->copy()->addDays(20)->toDateString(),
            'sch_source' => OrtControl::SCH_SISTEM,
            'sch_basis' => $irama['basis'], 'sch_basis_n' => $irama['selang'],
            'created_by' => 'sistem',
        ], $kontrolUbah));
    }

    private function sesi(): array
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

        return [
            'isLoggedIn' => true, 'id' => 'uji-panggil', 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Sales', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ];
    }

    private function halaman(array $query = []): string
    {
        $url = '/tracking-ortho?' . http_build_query(array_merge(['utama' => $this->placeId], $query));

        return $this->withSession($this->sesi())->get($url)->assertOk()->getContent();
    }

    // ── Pemilih tanggal: mesin pagar yang sama ──────────────────────────────

    /**
     * ⭐ Meniru drg. Andy: SATU hari praktik saja, Rabu. Dialah sebab 117
     *   usulan Kalideres mendarat di hari dokternya tidak ada.
     */
    public function test_pemilih_hanya_menawarkan_hari_praktik_dokter(): void
    {
        $this->dokterPraktik([3]);              // Rabu saja
        $ctl = $this->pasien([28, 28, 29, 28]);

        $hari = app(OrtUsulan::class)->hariSahBerikutnya(
            $ctl->case, Carbon::today()->addDays(3), $this->placeId, 6
        );

        $this->assertNotEmpty($hari, 'Dokternya praktik Rabu — pasti ada Rabu dalam enam minggu.');
        foreach ($hari as $t) {
            $this->assertSame(3, Carbon::parse($t)->dayOfWeekIso, "{$t} bukan hari praktik dokter.");
        }
    }

    /** ⭐ Senin klinik tutup — tidak boleh bocor ke pemilih. */
    public function test_pemilih_tidak_pernah_menawarkan_hari_klinik_tutup(): void
    {
        $this->dokterPraktik([1, 2, 3, 4, 5, 6, 7]);   // praktik tiap hari
        $ctl = $this->pasien([28, 28, 29, 28]);

        $hari = app(OrtUsulan::class)->hariSahBerikutnya(
            $ctl->case, Carbon::today()->addDays(3), $this->placeId, 10
        );

        foreach ($hari as $t) {
            $this->assertNotContains(
                Carbon::parse($t)->dayOfWeekIso, OrtUsulan::HARI_TUTUP,
                "{$t} jatuh di hari klinik tutup."
            );
        }
    }

    /** Dokter tak terpetakan: daftar KOSONG, bukan daftar tebakan. */
    public function test_dokter_tak_terpetakan_tidak_menghasilkan_pilihan(): void
    {
        $ctl = $this->pasien([28, 28, 29, 28]);   // sengaja tanpa dokterPraktik()

        $this->assertSame([], app(OrtUsulan::class)->hariSahBerikutnya(
            $ctl->case, Carbon::today()->addDays(3), $this->placeId, 6
        ));
    }

    /**
     * Tanggal yang SUDAH dipegang baris ini tetap ditawarkan.
     *
     * Tanpa pengecualian ini, baris itu terhitung melawan kuotanya sendiri dan
     * tanggalnya sendiri hilang dari daftar — pasien bilang "tanggal usulan itu
     * saja cocok", lalu Sales tidak menemukannya di layar.
     */
    public function test_tanggal_sendiri_tetap_ditawarkan_meski_harinya_penuh(): void
    {
        $this->dokterPraktik([1, 2, 3, 4, 5, 6, 7]);
        $ctl   = $this->pasien([28, 28, 29, 28]);
        $milik = $ctl->sch_date->toDateString();

        $hari = app(OrtUsulan::class)->hariSahBerikutnya(
            $ctl->case, $ctl->sch_date, $this->placeId, 6, $milik
        );

        $this->assertContains($milik, $hari, 'Tanggal yang sedang dipakai baris ini harus tetap bisa dipilih.');
    }

    // ── Tangga tombol ───────────────────────────────────────────────────────

    /** ⭐ Irama longgar: yang ditawarkan MENELEPON, bukan menyetujui tebakan. */
    public function test_usulan_irama_longgar_menawarkan_follow_up(): void
    {
        $this->dokterPraktik([1, 2, 3, 4, 5, 6, 7]);
        $ctl = $this->pasien([20, 90, 25, 110]);

        $this->assertSame(OrtCase::BASIS_LONGGAR, $ctl->sch_basis, 'Prasyarat.');
        $this->assertSame('follow_up', $ctl->tindakanBerikutnya());

        $html = $this->halaman(['q' => self::TANDA]);
        $this->assertStringContainsString('Catat Follow-up', $html);
    }

    /** Irama rapat: tebakannya memang bagus, sekali klik tetap jujur. */
    public function test_usulan_irama_rapat_tetap_menawarkan_konfirmasi(): void
    {
        $this->dokterPraktik([1, 2, 3, 4, 5, 6, 7]);
        $ctl = $this->pasien([28, 28, 29, 28]);

        $this->assertSame(OrtCase::BASIS_RAPAT, $ctl->sch_basis, 'Prasyarat.');
        $this->assertSame('konfirmasi', $ctl->tindakanBerikutnya());
    }

    /** Sesudah ditelepon, yang tersisa memasang tanggalnya. */
    public function test_sesudah_follow_up_menawarkan_pilih_tanggal(): void
    {
        $ctl = $this->pasien([20, 90, 25, 110], [
            'fu_date' => Carbon::today()->toDateString(), 'fu_channel' => 'telepon', 'fu_by' => 'Uji Sales',
        ]);

        $this->assertSame('pilih_tanggal', $ctl->tindakanBerikutnya());
    }

    // ── Satu panggilan, satu penulisan ──────────────────────────────────────

    /** ⭐ Follow-up dan jadwal ditulis bersama, bukan dua langkah. */
    public function test_satu_kiriman_mencatat_fu_sekaligus_memasang_jadwal(): void
    {
        $ctl      = $this->pasien([20, 90, 25, 110]);
        $disepakati = Carbon::today()->addDays(35)->toDateString();

        $this->withSession($this->sesi())
            ->post(route('ortho.followup', $ctl->id), [
                'kanal' => 'telepon', 'sch_tanggal' => $disepakati,
            ])->assertRedirect();

        $ctl->refresh();
        $this->assertSame(Carbon::today()->toDateString(), $ctl->fu_date->toDateString());
        $this->assertSame('telepon', $ctl->fu_channel);
        $this->assertSame($disepakati, $ctl->sch_date->toDateString());
        $this->assertSame(0, (int) $ctl->is_proposal, 'Tanggal dari pasien bukan usulan mesin lagi.');
        $this->assertSame(OrtControl::SCH_MANUAL, $ctl->sch_source);
    }

    /** "Belum dapat tanggal" itu hasil yang sah — usulannya dibiarkan utuh. */
    public function test_tanpa_tanggal_hanya_mencatat_follow_up(): void
    {
        $ctl  = $this->pasien([20, 90, 25, 110]);
        $lama = $ctl->sch_date->toDateString();

        $this->withSession($this->sesi())
            ->post(route('ortho.followup', $ctl->id), ['kanal' => 'wa'])
            ->assertRedirect();

        $ctl->refresh();
        $this->assertNotNull($ctl->fu_date, 'Follow-up tetap tercatat.');
        $this->assertSame($lama, $ctl->sch_date->toDateString(), 'Usulannya tidak disentuh.');
        $this->assertSame(1, (int) $ctl->is_proposal);
    }

    /** Isian tanggal bebas dipakai kalau chip hari dokter tidak dipilih. */
    public function test_tanggal_bebas_dipakai_kalau_chip_tidak_dipilih(): void
    {
        $ctl        = $this->pasien([20, 90, 25, 110]);
        $disepakati = Carbon::today()->addDays(40)->toDateString();

        $this->withSession($this->sesi())
            ->post(route('ortho.followup', $ctl->id), [
                'kanal' => 'langsung', 'sch_tanggal_lain' => $disepakati,
            ])->assertRedirect();

        $this->assertSame($disepakati, $ctl->refresh()->sch_date->toDateString());
    }

    /** ⭐ Satu Urungkan memulihkan KEDUANYA, bukan cuma follow-up-nya. */
    public function test_urungkan_memulihkan_follow_up_dan_jadwal(): void
    {
        $ctl  = $this->pasien([20, 90, 25, 110]);
        $lama = $ctl->sch_date->toDateString();

        $resp = $this->withSession($this->sesi())
            ->post(route('ortho.followup', $ctl->id), [
                'kanal' => 'telepon', 'sch_tanggal' => Carbon::today()->addDays(35)->toDateString(),
            ]);

        $token = $resp->getSession()->get('ort_undo');
        $this->assertNotEmpty($token, 'Aksi tulis harus memberi token Urungkan.');

        $this->withSession($this->sesi())
            ->post(route('ortho.urungkan', $token))->assertRedirect();

        $ctl->refresh();
        $this->assertNull($ctl->fu_date, 'Follow-up dipulihkan.');
        $this->assertSame($lama, $ctl->sch_date->toDateString(), 'Dan jadwalnya ikut dipulihkan.');
        $this->assertSame(1, (int) $ctl->is_proposal, 'Barisnya kembali jadi usulan.');
    }

    // ── Yang tidak boleh berubah ────────────────────────────────────────────

    /** Baris BUKAN usulan tetap satu klik — tidak ada yang perlu dipilih. */
    public function test_baris_bukan_usulan_tetap_satu_klik(): void
    {
        $ctl = $this->pasien([28, 28, 29, 28], ['is_proposal' => 0, 'sch_basis' => null]);

        $this->assertSame('follow_up', $ctl->tindakanBerikutnya());

        $html = $this->halaman(['q' => self::TANDA]);
        $this->assertStringContainsString(
            route('ortho.followup', $ctl->id), $html,
            'Baris biasa tetap POST langsung, bukan membuka lembar.'
        );
    }

    /** Lembar panggilan hanya menawarkan hari yang lolos pagar. */
    public function test_lembar_panggilan_merender_hari_praktik_dokter(): void
    {
        $this->dokterPraktik([3]);           // Rabu saja
        $ctl = $this->pasien([20, 90, 25, 110]);

        $html = $this->halaman(['sheet' => 'panggil', 'kontrol' => $ctl->id]);

        preg_match_all('/name="sch_tanggal" value="(\d{4}-\d{2}-\d{2})"/', $html, $m);
        $this->assertNotEmpty($m[1], 'Lembar harus menawarkan hari praktik dokter.');
        foreach ($m[1] as $t) {
            $this->assertSame(3, Carbon::parse($t)->dayOfWeekIso, "{$t} bukan hari praktik dokter.");
        }
    }
}
