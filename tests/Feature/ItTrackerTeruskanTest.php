<?php

namespace Tests\Feature;

use App\Models\ItForm;
use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use App\Services\ItTracker\PhaseEngine;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\MenyemaiGrantItTracker;
use Tests\TestCase;

/**
 * §14.16/§14.19 — JALAN PINTAS PARAF BERTANDA: Head IT boleh MELEWATI paraf
 * Business Owner di gerbang 02, tapi tercatat (siapa · kapan · alasan) dan
 * terlihat sampai Go Live + ekspor. Ini pelewatan, BUKAN paraf.
 * Kelas terpisah (batas ±17 uji ber-DB per proses).
 */
class ItTrackerTeruskanTest extends TestCase
{
    use DatabaseTransactions;
    use MenyemaiGrantItTracker;

    private ItTrackerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);
        if (! DB::select("SHOW TABLES LIKE 'it_form'")) {
            $this->markTestSkipped('Tabel it_form belum ada.');
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

    /** tiket M di ANALISIS dengan analisis lengkap — hanya paraf BO yang kurang */
    private function siapParaf(): ItTicket
    {
        $t = $this->svc->buat(['judul' => 'Uji teruskan ' . Str::random(5), 'sumber' => 'permintaan', 'jenis_permintaan' => 'Fitur baru',
            'prioritas_pemohon' => 'Sedang', 'latar' => 'uji', 'kebutuhan' => 'uji', 'terdampak' => 'Marketing'], 'uji-pemohon-t');
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit');
        $this->svc->pindahFase($t = $t->fresh(), 'ANALISIS', 'uji-headit');
        $t = $t->fresh(); $t->pic_user_id = 'uji-headit'; $t->save();
        $this->svc->simpanFr($t, [['kebutuhan' => 'a', 'prioritas' => 'Tinggi', 'kriteria' => 'b']]);
        $this->svc->setKomponen($t, ['Aplikasi']);
        return $t->fresh();
    }

    private function fr02(ItTicket $t): array
    {
        return ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-02')->value('payload') ?? [];
    }

    private function gate(ItTicket $t): array
    {
        return PhaseEngine::gateInfo($this->svc->viewTicket($this->svc->muatRelasi(ItTicket::query())->find($t->id)));
    }

    /** Head IT + alasan → diteruskan tersimpan, gerbang terbuka, fr02.paraf TETAP kosong, lampu berganti teks. */
    public function test_head_it_meneruskan_dengan_alasan(): void
    {
        $t = $this->siapParaf();
        $this->assertFalse($this->gate($t)['ok'], 'sebelum: tertahan paraf');

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.teruskan', $t->id), ['alasan' => 'Robi cuti sampai 12 Sep, pemohon menunggu'])
            ->assertRedirect()->assertSessionHas('success');

        $p = $this->fr02($t);
        $this->assertArrayNotHasKey('paraf', $p, 'pelewatan TIDAK PERNAH mengisi paraf');
        $this->assertSame('uji-headit', $p['diteruskan']['oleh_id']);
        $this->assertSame('Robi cuti sampai 12 Sep, pemohon menunggu', $p['diteruskan']['alasan']);
        $this->assertNotEmpty($p['diteruskan']['at']);

        $g = $this->gate($t);
        $this->assertTrue($g['ok']);
        $teks = array_column($g['checks'], 't');
        $this->assertContains('diteruskan Head IT tanpa paraf BO', $teks, 'lampu menyala tapi teksnya jujur');
        $this->assertNotContains('paraf satu Business Owner', $teks);

        // gerbang benar-benar terbuka: maju → DEV
        $this->withSession($this->sesi('Head IT', 'uji-headit'))->post(route('it-tracker.maju', $t->id))->assertRedirect();
        $this->assertSame('DEV', $t->fresh()->fase);
    }

    /** Tanpa alasan / alasan pendek → ditolak dengan pesan yang menyebut sebabnya (P3); tak ada yang tersimpan. */
    public function test_alasan_wajib_minimal_10_karakter(): void
    {
        $t = $this->siapParaf();
        $ses = $this->sesi('Head IT', 'uji-headit');

        $r = $this->withSession($ses)->from(route('it-tracker.show', $t->id))->post(route('it-tracker.teruskan', $t->id), []);
        $r->assertRedirect()->assertSessionHasErrors('alasan');
        $this->assertStringContainsString('alasan', mb_strtolower(session('errors')->first('alasan')));

        $this->withSession($ses)->from(route('it-tracker.show', $t->id))->post(route('it-tracker.teruskan', $t->id), ['alasan' => 'sibuk'])
            ->assertRedirect()->assertSessionHasErrors('alasan');
        $this->assertStringContainsString('10 karakter', session('errors')->first('alasan'));

        try {
            $this->svc->teruskanTanpaBo($t, '02', '   x  ', 'uji-headit', 'Ekky');
            $this->fail('layanan pun harus menolak alasan pendek');
        } catch (DomainException $e) {
            $this->assertStringContainsString('minimal 10 karakter', $e->getMessage());
        }
        $this->assertArrayNotHasKey('diteruskan', $this->fr02($t));
        $this->assertFalse($this->gate($t)['ok']);
    }

    /** Reza (it_dev) → 403 · BO (pengawas) → 403 · pemohon → 403. */
    public function test_hanya_head_it(): void
    {
        $t = $this->siapParaf();
        $isi = ['alasan' => 'alasan yang cukup panjang untuk lolos'];
        $this->withSession($this->sesi('IT', 'uji-reza'))->post(route('it-tracker.teruskan', $t->id), $isi)->assertForbidden();
        $this->withSession($this->sesi('Executive', 'uji-robi'))->post(route('it-tracker.teruskan', $t->id), $isi)->assertForbidden();
        $this->withSession($this->sesi('Konsultan Marketing', 'uji-hari'))->post(route('it-tracker.teruskan', $t->id), $isi)->assertForbidden();
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon-t'))->post(route('it-tracker.teruskan', $t->id), $isi)->assertForbidden();
        $this->assertArrayNotHasKey('diteruskan', $this->fr02($t));
    }

    /** Penanda tampil di detail (2 tempat), kartu papan, dan ekspor FRM-IT-01 — ikut sampai fase berikutnya. */
    public function test_penanda_tampil_di_detail_papan_ekspor_dan_ikut_ke_fase_berikut(): void
    {
        $t = $this->siapParaf();
        $this->svc->teruskanTanpaBo($t, '02', 'Hari sedang di luar kota sampai pekan depan', 'uji-headit', 'Ekky');
        $ses = $this->sesi('Head IT', 'uji-headit');

        $detail = $this->withSession($ses)->get(route('it-tracker.show', $t->id))->assertOk();
        $detail->assertSee('itk-lewat-banner', false)->assertSee('itk-paraf-lewat', false)
            ->assertSee('tanpa paraf Business Owner')->assertSee('Hari sedang di luar kota');
        $this->assertSame(2, substr_count($detail->getContent(), 'Hari sedang di luar kota'), 'kepala tiket + panel 02');

        $this->withSession($ses)->get(route('it-tracker.index'))->assertOk()->assertSee('itk-lewat-chip', false); // penanda, bukan kata (legenda memuat katanya)
        $this->withSession($ses)->get(route('it-tracker.ekspor', $t->id))->assertOk()
            ->assertSee('itk-doc-lewat', false)->assertSee('tanpa paraf Business Owner')->assertSee('Hari sedang di luar kota');

        // maju ke DEV lalu UAT — penanda tetap di kepala tiket & ekspor
        $this->svc->majukan($this->svc->muatRelasi(ItTicket::query())->find($t->id), 'uji-headit');
        $this->svc->pindahFase($t->fresh(), 'UAT', 'uji-headit');
        $this->withSession($ses)->get(route('it-tracker.show', $t->id))->assertOk()->assertSee('itk-lewat-banner', false);
        $this->withSession($ses)->get(route('it-tracker.ekspor', $t->id))->assertOk()->assertSee('tanpa paraf Business Owner');
    }

    /** T2 konsisten: mengubah FR sesudah diteruskan → pelewatan gugur, gerbang menutup; identik & dapur tidak. */
    public function test_ubah_isi_menggugurkan_pelewatan(): void
    {
        $t = $this->siapParaf();
        $this->svc->teruskanTanpaBo($t, '02', 'BO tak merespons dua hari kerja', 'uji-headit', 'Ekky');
        $this->assertTrue($this->gate($t)['ok']);

        $this->svc->setKomponen($t, ['Aplikasi']);                 // identik
        $this->svc->setDapur($t, '3 hari', 'risiko kecil');         // dapur
        $this->assertArrayHasKey('diteruskan', $this->fr02($t), 'identik & dapur tidak menggugurkan');

        $this->svc->simpanFr($t, [['kebutuhan' => 'a', 'prioritas' => 'Tinggi', 'kriteria' => 'b DIUBAH']]);
        $this->assertArrayNotHasKey('diteruskan', $this->fr02($t), 'isi berubah → pelewatan gugur');
        $this->assertFalse($this->gate($t)['ok']);
        $this->assertContains('paraf satu Business Owner', $this->gate($t)['missing']);
    }

    /** BO memparaf SESUDAH diteruskan → keduanya tersimpan, penanda tetap tampil. */
    public function test_paraf_menyusul_tidak_menghapus_jejak(): void
    {
        $t = $this->siapParaf();
        $this->svc->teruskanTanpaBo($t, '02', 'Robi cuti, pemohon sudah menunggu seminggu', 'uji-headit', 'Ekky');
        $this->withSession($this->sesi('Executive', 'uji-robi'))->post(route('it-tracker.paraf', $t->id))->assertRedirect()->assertSessionHas('success');

        $p = $this->fr02($t);
        $this->assertNotEmpty($p['paraf']);
        $this->assertNotEmpty($p['diteruskan'], 'paraf tidak menghapus jejak pelewatan');
        $this->withSession($this->sesi('Head IT', 'uji-headit'))->get(route('it-tracker.show', $t->id))->assertOk()
            ->assertSee('itk-lewat-banner', false)->assertSee('BO memparaf menyusul')->assertSee('Diparaf');
    }

    /** Gerbang '06' (ACC Go Live) SENGAJA tidak bisa diteruskan. */
    public function test_gerbang_06_ditolak(): void
    {
        $t = $this->siapParaf();
        try {
            $this->svc->teruskanTanpaBo($t, '06', 'alasan yang panjang sekali pun', 'uji-headit', 'Ekky');
            $this->fail('06 harus ditolak');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Go Live', $e->getMessage());
        }
        $this->assertSame(['02'], ItTrackerService::GERBANG_BOLEH_DITERUSKAN);
    }

    /** Kendali negatif: paraf BO normal TIDAK menampilkan penanda apa pun; tabel lama utuh. */
    public function test_paraf_normal_tanpa_penanda_dan_tabel_lama_utuh(): void
    {
        $tabel = ['users', 'rop_requests', 'att_day', 'group_access'];
        $sebelum = collect($tabel)->mapWithKeys(fn ($x) => [$x => DB::table($x)->count()]);

        $t = $this->siapParaf();
        $this->svc->parafBo($t, 'uji-robi', 'Robi');
        $ses = $this->sesi('Head IT', 'uji-headit');
        $this->withSession($ses)->get(route('it-tracker.show', $t->id))->assertOk()
            ->assertDontSee('itk-lewat-banner', false)->assertDontSee('tanpa paraf Business Owner')->assertDontSee('Teruskan tanpa paraf BO');
        $this->withSession($ses)->get(route('it-tracker.index'))->assertOk()->assertDontSee('itk-lewat-chip', false); // legenda warna memuat KATA itu; yang diuji penandanya
        $this->withSession($ses)->get(route('it-tracker.ekspor', $t->id))->assertOk()->assertDontSee('itk-doc-lewat', false);

        foreach ($tabel as $x) $this->assertSame($sebelum[$x], DB::table($x)->count(), $x);
    }
}
