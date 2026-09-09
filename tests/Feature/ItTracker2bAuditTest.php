<?php

namespace Tests\Feature;

use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\MenyemaiGrantItTracker;
use Tests\TestCase;

/**
 * Fase 2b — REGRESI temuan audit + E2E persona 3 Sep 2026 malam.
 * Tiap uji mengunci satu jalan buntu yang pernah nyata; kelas TERPISAH
 * (batas ±17 uji ber-DB per proses).
 */
class ItTracker2bAuditTest extends TestCase
{
    use DatabaseTransactions;
    use MenyemaiGrantItTracker;

    private ItTrackerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);
        try {
            DB::select('SELECT terkunci FROM it_test_case LIMIT 0');
        } catch (\Throwable) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_03_it_tracker_2b.sql dulu.');
        }
    }

    private function sesi(string $groupName, ?string $uid = null): array
    {
        $gid = DB::table('group_path')->where('nama', $groupName)->value('id') ?: 'grp-uji';
        $this->pastikanGrantItTracker($gid);   // grant disemai di dalam transaksi — uji menguji KEMAMPUAN peran, bukan siapa yang ber-grant hari ini

        return [
            'isLoggedIn' => true, 'id' => $uid ?: ('uji-' . Str::random(8)),
            'group_id'   => $gid,
            'group_name' => $groupName, 'name' => 'Uji ' . $groupName,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time(),
        ];
    }

    private function tiket(string $jenjang = 'M', string $fase = 'ANALISIS', string $uid = 'uji-pemohon'): ItTicket
    {
        $t = $this->svc->buat([
            'judul' => 'Uji audit ' . Str::random(6), 'sumber' => 'permintaan',
            'jenis_permintaan' => 'Fitur baru', 'prioritas_pemohon' => 'Sedang',
            'latar' => 'uji', 'kebutuhan' => 'uji', 'terdampak' => 'Marketing',
        ], $uid);
        $this->svc->triase($t, $jenjang, 3, 'P1', 'uji-headit');
        if ($fase !== 'ANTRE') {
            $this->svc->pindahFase($t = $t->fresh(), $fase, 'uji-headit');
        }
        $t = $t->fresh();
        $t->pic_user_id = 'uji-headit';
        $t->save();
        return $t;
    }

    private function lihat(ItTicket $t): array
    {
        return $this->svc->viewTicket($this->svc->muatRelasi(ItTicket::query())->find($t->id));
    }

    /** analisis lengkap + paraf BO — titik awal beberapa uji */
    private function analisisLengkap(string $jenjang = 'M'): ItTicket
    {
        $t = $this->tiket($jenjang);
        $this->svc->simpanFr($t, [['kebutuhan' => 'a', 'prioritas' => 'Tinggi', 'kriteria' => 'b']]);
        $this->svc->setKomponen($t, ['Aplikasi']);
        if ($jenjang === 'L') $this->svc->setLingkupL($t, 'x', 'y', '');
        $this->svc->parafBo($t, 'uji-robi', 'Robi');
        return $t->fresh();
    }

    /** P0 E2E: "Disetujui dengan Catatan" HARUS menyalakan lampu pra-deploy "UAT disetujui". */
    public function test_uat_dengan_catatan_menyalakan_lampu_golive(): void
    {
        $t = $this->tiket('M', 'UAT');
        $this->svc->simpanSkenario($t, [['proses' => 'p', 'harapan' => 'h']]);
        $this->svc->jawabUat($t, [['sesuai' => '0', 'catatan' => 'beda']], null);
        $this->svc->uatPutuskan($t, 'catatan', 'uji-pemohon');

        $v = $this->lihat($t);
        $this->assertSame('GOLIVE', $t->fresh()->fase);
        $this->assertSame('ya', $v['fr06']['pra']['uat'] ?? null, 'dulu hanya setuju yang menyala → tiket ber-catatan macet PERMANEN di Go Live');
    }

    /** Simpan-ulang komponen/lingkup yang identik TIDAK menggugurkan paraf; yang berubah menggugurkan. */
    public function test_simpan_ulang_identik_tak_menggugurkan_paraf(): void
    {
        $t = $this->analisisLengkap('L');
        $this->assertNotEmpty($this->lihat($t)['fr02']['paraf']);

        $this->svc->setKomponen($t, ['Aplikasi']);
        $this->svc->setLingkupL($t, 'x', 'y', '');
        $this->assertNotEmpty($this->lihat($t)['fr02']['paraf'], 'klik Simpan tanpa perubahan = paraf tetap');

        $this->svc->setKomponen($t, ['API']);
        $this->assertEmpty($this->lihat($t)['fr02']['paraf'] ?? null, 'komponen berubah = paraf gugur');
    }

    /** Paraf mengikuti isi: analisis kosong tak bisa diparaf, dan pesannya menyebut yang kurang. */
    public function test_paraf_menolak_analisis_kosong(): void
    {
        $t = $this->tiket('M');
        try {
            $this->svc->parafBo($t, 'uji-robi', 'Robi');
            $this->fail('paraf analisis kosong harus ditolak');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Belum bisa diparaf', $e->getMessage());
            $this->assertStringContainsString('Kriteria Diterima', $e->getMessage());
        }
    }

    /** Jenjang S ditolak di tengah Analisis (tiket terkunci tanpa panel); ubah bobot saja tak menggugurkan paraf. */
    public function test_ubah_jenjang_s_ditolak_dan_bobot_tak_gugurkan_paraf(): void
    {
        $t = $this->analisisLengkap('L');
        try {
            $this->svc->ubahJenjang($t, 'S', 1, 'P3');
            $this->fail('S harus ditolak');
        } catch (DomainException $e) {
            $this->assertStringContainsString('hanya bisa M atau L', $e->getMessage());
        }
        $this->svc->ubahJenjang($t, 'L', 5, 'P2');
        $this->assertNotEmpty($this->lihat($t)['fr02']['paraf'], 'bobot/prio berubah, jenjang tetap → paraf tetap');
        $this->svc->ubahJenjang($t, 'M', 5, 'P2');
        $this->assertEmpty($this->lihat($t)['fr02']['paraf'] ?? null, 'jenjang berubah → paraf gugur');
    }

    /** Kode TC yang tak dikenal (tab basi) ditolak, bukan lahir sebagai baris kembar. */
    public function test_tc_kode_tak_dikenal_ditolak(): void
    {
        $t = $this->tiket('L', 'UJI');
        try {
            $this->svc->simpanTc($t, [['kode' => 'TC-09', 'skenario' => 'basi', 'aktual' => 'x', 'status' => 'pass']]);
            $this->fail('kode tak dikenal harus ditolak');
        } catch (DomainException $e) {
            $this->assertStringContainsString('TC-09', $e->getMessage());
        }
        $this->assertCount(5, $this->lihat($t)['fr04']['tc']);
    }

    /** "Perlu Perbaikan" mengarsipkan jawaban lama — putaran UAT berikutnya mulai bersih. */
    public function test_perbaikan_mengarsipkan_jawaban(): void
    {
        $t = $this->tiket('M', 'UAT');
        $this->svc->simpanSkenario($t, [['proses' => 'p', 'harapan' => 'h']]);
        $this->svc->jawabUat($t, [['sesuai' => '0', 'catatan' => 'salah nomor']], 'masukan lama');
        $this->svc->uatPutuskan($t, 'perbaikan', 'uji-pemohon');

        $f = $this->lihat($t)['fr05'];
        $this->assertNull($f['skenario'][0]['sesuai']);
        $this->assertSame('', $f['skenario'][0]['catatan']);
        $this->assertSame('', $f['masukan']);
        $this->assertCount(1, $f['putaranLalu']);
        $this->assertSame('salah nomor', $f['putaranLalu'][0]['skenario'][0]['catatan']);
        $this->assertSame('masukan lama', $f['putaranLalu'][0]['masukan']);
    }

    /** HTTP: pemohon menekan keputusan TANPA "Simpan jawabanku" — jawaban ikut tersimpan. */
    public function test_jawab_dan_putus_satu_kiriman(): void
    {
        $t = $this->tiket('M', 'UAT');
        $this->svc->simpanSkenario($t, [['proses' => 'p', 'harapan' => 'h'], ['proses' => 'q', 'harapan' => 'r']]);

        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon'))
            ->post(route('it-tracker.jawab', $t->id), [
                'jawaban' => [['sesuai' => '1'], ['sesuai' => '0', 'catatan' => 'kurang']],
                'masukan' => 'm', 'keputusan' => 'catatan',
            ])->assertRedirect()->assertSessionHas('success');

        $f = $this->lihat($t)['fr05'];
        $this->assertSame('GOLIVE', $t->fresh()->fase);
        $this->assertTrue($f['skenario'][0]['sesuai']);
        $this->assertSame('kurang', $f['skenario'][1]['catatan']);
        $this->assertCount(2, $f['tindakLanjut'], 'catatan baris + masukan → 2 butir');
    }

    /** HTTP: evaluator (pemohon, bukan IT) boleh menutup tiket di EVALUASI — "menutup tiket adalah parafnya". */
    public function test_evaluator_boleh_menutup_tiket(): void
    {
        $t = $this->tiket('L', 'EVALUASI');
        $this->svc->simpanEvaluasi($t, ['sesuai' => 4, 'mudah' => 3, 'stabil' => 5, 'dampak' => 4, 'manfaat' => 5], [], []);

        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon'))
            ->get(route('it-tracker.show', $t->id))->assertOk()->assertSee('Tutup');
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon'))
            ->post(route('it-tracker.maju', $t->id))->assertRedirect();
        $this->assertSame('SELESAI', $t->fresh()->fase);

        // pemohon di fase lain tetap 403 (bukan pintu belakang)
        $u = $this->tiket('M', 'DEV');
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon'))
            ->post(route('it-tracker.maju', $u->id))->assertForbidden();
    }

    /** HTTP: otorisasi SEBELUM validasi — orang tanpa hak dapat 403, bukan 302 galat validasi. */
    public function test_otorisasi_sebelum_validasi(): void
    {
        $t = $this->tiket('M', 'UAT');
        $this->withSession($this->sesi('Head Marketing', 'uji-orang-lain'))
            ->post(route('it-tracker.uat', $t->id), ['keputusan' => 'ngawur'])->assertForbidden();
        $this->withSession($this->sesi('Head Marketing', 'uji-orang-lain'))
            ->post(route('it-tracker.konfirmasi', $t->id), ['catatan' => str_repeat('x', 5000)])->assertForbidden();
    }

    /** Spawn dari masukan panjang: judul dipangkas 255, tak 500. */
    public function test_spawn_masukan_panjang_tak_meledak(): void
    {
        $t = $this->tiket('M', 'UAT');
        $this->svc->simpanSkenario($t, [['proses' => 'p', 'harapan' => 'h']]);
        $this->svc->jawabUat($t, [['sesuai' => '1']], str_repeat('panjang ', 60));
        $this->svc->uatPutuskan($t, 'catatan', 'uji-pemohon');

        $anak = $this->svc->lahirkanTiket($this->svc->muatRelasi(ItTicket::query())->find($t->id), 'uat', 0, 'uji-headit');
        $this->assertSame(255, mb_strlen($anak->judul));
        $this->assertSame('TRIASE', $anak->fase);
    }

    /** IT yang bukan PIC tidak disodori formulir skenario yang berujung 403. */
    public function test_skenario_hanya_untuk_head_it_atau_pic(): void
    {
        $t = $this->tiket('M', 'DEV'); // PIC = uji-headit
        $this->withSession($this->sesi('IT', 'uji-reza-lain'))
            ->get(route('it-tracker.show', $t->id))->assertOk()->assertDontSee('Simpan skenario');
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.show', $t->id))->assertOk()->assertSee('Simpan skenario');
    }
}
