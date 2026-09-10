<?php

namespace Tests\Feature;

use App\Models\ItForm;
use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use App\Services\ItTracker\PhaseEngine;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\MenyemaiGrantItTracker;
use Tests\TestCase;

/**
 * Revisi pasca-demo 5 Sep (permintaan Ekky):
 *  1. kategori unit usaha SweetTreats / RB Las — satu papan, aksen oranye
 *  2. catatan kemajuan "sudah sampai mana" — termasuk saat Go Live
 *  3. badge nama PIC di kartu papan
 *  4. unggah bahan rancangan di FRM-IT-02
 * Kelas terpisah (batas ±17 uji ber-DB per proses).
 */
class ItTrackerRevisiDemoTest extends TestCase
{
    use DatabaseTransactions;
    use MenyemaiGrantItTracker;

    private ItTrackerService $svc;
    private array $berkasDibuat = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);
        try {
            DB::select('SELECT unit, kemajuan FROM it_ticket LIMIT 0');
        } catch (\Throwable) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_05_it_tracker_unit_kemajuan.sql dulu.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->berkasDibuat as $p) @unlink(public_path($p));
        parent::tearDown();
    }

    private function sesi(string $groupName, string $uid): array
    {
        $gid = DB::table('group_path')->where('nama', $groupName)->value('id') ?: 'grp-uji';
        $this->pastikanGrantItTracker($gid);   // grant disemai di dalam transaksi — uji menguji KEMAMPUAN peran, bukan siapa yang ber-grant hari ini

        return ['isLoggedIn' => true, 'id' => $uid,
            'group_id' => $gid,
            'group_name' => $groupName, 'name' => 'Uji ' . $groupName,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time()];
    }

    private function tiket(string $unit = 'sweettreats', string $fase = 'DEV', string $jenjang = 'M'): ItTicket
    {
        $t = $this->svc->buat(['judul' => 'Uji revisi ' . Str::random(5), 'sumber' => 'permintaan', 'jenis_permintaan' => 'Fitur baru',
            'prioritas_pemohon' => 'Sedang', 'latar' => 'uji', 'kebutuhan' => 'uji', 'terdampak' => 'x', 'unit' => $unit], 'uji-pemohon-r');
        $this->svc->triase($t, $jenjang, 3, 'P2', 'uji-headit', 'uji-reza');
        if ($fase !== 'ANTRE') $this->svc->pindahFase($t = $t->fresh(), $fase, 'uji-headit');
        return $t->fresh();
    }

    /** Kategori unit: tersimpan dari formulir, default SweetTreats, RB Las beraksen oranye di papan. */
    public function test_unit_usaha_rb_las_ditandai_di_papan(): void
    {
        $isi = ['judul' => 'Uji RB Las ' . Str::random(4), 'sumber' => 'permintaan', 'jenis_permintaan' => 'Fitur baru',
            'prioritas_pemohon' => 'Tinggi', 'latar' => 'las', 'kebutuhan' => 'las', 'terdampak' => 'produksi'];

        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon-r'))
            ->post(route('it-tracker.store'), $isi + ['unit' => 'rblas'])->assertRedirect()->assertSessionHas('success');
        $rb = ItTicket::orderByDesc('id')->first();
        $this->assertSame('rblas', $rb->unit);

        // tanpa memilih unit → SweetTreats (yang lazim), bukan kosong
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon-r'))
            ->post(route('it-tracker.store'), ['judul' => 'Uji default ' . Str::random(4)] + $isi)->assertRedirect();
        $this->assertSame('sweettreats', ItTicket::orderByDesc('id')->first()->unit);

        // unit ngawur ditolak validasi
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon-r'))
            ->from(route('it-tracker.permintaan'))
            ->post(route('it-tracker.store'), $isi + ['unit' => 'pabrik-tahu'])->assertSessionHasErrors('unit');

        $html = $this->withSession($this->sesi('Head IT', 'uji-headit'))->get(route('it-tracker.index'))->assertOk()->getContent();
        $this->assertStringContainsString('itk-rblas', $html, 'kartu RB Las beraksen oranye');
        // RONDE 3: badge di kartu DIBUANG — RB Las = garis kiri oranye, dijelaskan legenda
        $this->assertStringNotContainsString('>RB LAS<', $html, 'badge unit turun dari kartu');
        $this->assertStringContainsString('garis kiri oranye', $html, 'legenda menjelaskan warnanya');
        // yang lazim TIDAK ditandai — menandai semuanya sama saja tak menandai apa pun
        $this->assertStringNotContainsString('itk-unit sweettreats', $html);
    }

    /** Catatan kemajuan: ditulis IT, tampil di kartu & detail, dengan siapa + kapan; kosong = terhapus. */
    public function test_catatan_kemajuan_sudah_sampai_mana(): void
    {
        $t = $this->tiket();

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.kemajuan', $t->id), ['kemajuan' => 'Tinggal uji di 2 cabang, tunggu contoh laporan Finance'])
            ->assertRedirect()->assertSessionHas('success');

        $t = $t->fresh();
        $this->assertSame('Tinggal uji di 2 cabang, tunggu contoh laporan Finance', $t->kemajuan);
        $this->assertSame('uji-headit', $t->kemajuan_oleh);
        $this->assertNotNull($t->kemajuan_at);

        $papan = $this->withSession($this->sesi('Head IT', 'uji-headit'))->get(route('it-tracker.index'))->assertOk();
        $papan->assertSee('Tinggal uji di 2 cabang')->assertSee('itk-kemajuan', false);
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon-r'))
            ->get(route('it-tracker.show', $t->id))->assertOk()
            ->assertSee('SUDAH SAMPAI MANA')->assertSee('Tinggal uji di 2 cabang');

        // kosong = catatan dihapus, bukan string kosong yang menipu
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.kemajuan', $t->id), ['kemajuan' => '   '])->assertRedirect();
        $t = $t->fresh();
        $this->assertNull($t->kemajuan);
        $this->assertNull($t->kemajuan_at);
    }

    /** Catatan kemajuan bisa ditulis di Go Live juga (permintaan Ekky), tapi bukan oleh sembarang orang. */
    public function test_kemajuan_di_go_live_dan_pagarnya(): void
    {
        $g = $this->tiket('sweettreats', 'GOLIVE');
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.kemajuan', $g->id), ['kemajuan' => 'Dipasang 5 Sep 02:00, pemohon sudah dikabari'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Dipasang 5 Sep 02:00, pemohon sudah dikabari', $g->fresh()->kemajuan);

        // PIC boleh; pemohon & Business Owner tidak
        $this->withSession($this->sesi('IT', 'uji-reza'))
            ->post(route('it-tracker.kemajuan', $g->id), ['kemajuan' => 'dari PIC'])->assertRedirect();
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon-r'))
            ->post(route('it-tracker.kemajuan', $g->id), ['kemajuan' => 'x'])->assertForbidden();
        $this->withSession($this->sesi('Executive', 'uji-robi'))
            ->post(route('it-tracker.kemajuan', $g->id), ['kemajuan' => 'x'])->assertForbidden();

        // tiket yang sudah diparkir tak menerima catatan kemajuan
        $p = $this->tiket();
        $this->svc->parkir($p, 'DITUNDA', 'menunggu vendor', 'uji-headit');
        try {
            $this->svc->setKemajuan($p->fresh(), 'masih jalan kok', 'uji-headit');
            $this->fail('tiket ditunda harus ditolak');
        } catch (DomainException $e) {
            $this->assertStringContainsString('buka lagi', $e->getMessage());
        }
    }

    /** Badge nama PIC tampil di kartu papan. */
    public function test_badge_nama_pic_di_kartu(): void
    {
        DB::table('users')->insert(['id' => 'uji-reza', 'nama' => 'Reza Fauzi Ramadhan', 'email' => 'uji-reza@uji.test',
            'password' => 'x', 'status' => 1, 'group_id' => DB::table('group_path')->where('nama', 'IT')->value('id') ?: 'grp-uji',
            'created_at' => now(), 'updated_at' => now()]);
        $this->tiket();

        $this->withSession($this->sesi('Head IT', 'uji-headit'))->get(route('it-tracker.index'))->assertOk()
            ->assertSee('itk-pic-chip', false)->assertSee('Reza Fauzi Ramadhan');
    }

    /** Bahan rancangan di FRM-IT-02: masuk formulir 02 (bukan 01), tak menggugurkan paraf, dipagari izin analisis. */
    public function test_unggah_bahan_rancangan_di_analisis(): void
    {
        $t = $this->tiket('sweettreats', 'ANALISIS', 'M');
        $this->svc->simpanFr($t, [['kebutuhan' => 'a', 'prioritas' => 'Tinggi', 'kriteria' => 'b']]);
        $this->svc->setKomponen($t, ['Aplikasi']);
        $this->svc->parafBo($t, 'uji-robi', 'Robi');

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.lampiran', $t->id), ['kode' => 'FRM-IT-02', 'lampiran' => [
                UploadedFile::fake()->image('mockup-laporan.png', 200, 120),
                UploadedFile::fake()->create('alur-proses.pdf', 60, 'application/pdf'),
            ]])->assertRedirect()->assertSessionHas('success');

        $p02 = ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-02')->value('payload');
        foreach ($p02['lampiran'] as $x) $this->berkasDibuat[] = $x['path'];
        $this->assertCount(2, $p02['lampiran']);
        $this->assertStringStartsWith('uploads/it-tracker/frm02/', $p02['lampiran'][0]['path']);
        $this->assertFileExists(public_path($p02['lampiran'][0]['path']));
        $this->assertNotEmpty($p02['paraf'], 'bahan rancangan BUKAN isi yang disetujui — paraf tak boleh gugur');
        $this->assertTrue(PhaseEngine::gateInfo($this->svc->viewTicket($this->svc->muatRelasi(ItTicket::query())->find($t->id)))['ok']);

        // FRM-IT-01 tidak ikut terisi
        $p01 = ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-01')->value('payload');
        $this->assertArrayNotHasKey('lampiran', $p01 ?? []);

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.show', $t->id))->assertOk()
            ->assertSee('BAHAN RANCANGAN')->assertSee('mockup-laporan.png');

        // IT dev & fase lain ditolak
        $this->withSession($this->sesi('IT', 'uji-reza'))
            ->post(route('it-tracker.lampiran', $t->id), ['kode' => 'FRM-IT-02', 'lampiran' => [UploadedFile::fake()->image('x.png', 10, 10)]])
            ->assertForbidden();
        $this->svc->pindahFase($t->fresh(), 'DEV', 'uji-headit');
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.lampiran', $t->id), ['kode' => 'FRM-IT-02', 'lampiran' => [UploadedFile::fake()->image('y.png', 10, 10)]])
            ->assertForbidden();
    }

    /** Kendali negatif: tabel lama tak tersentuh oleh kelima revisi. */
    public function test_tabel_lama_tak_berubah(): void
    {
        $tabel = ['users', 'rop_requests', 'att_day', 'group_access'];
        $sebelum = collect($tabel)->mapWithKeys(fn ($x) => [$x => DB::table($x)->count()]);

        $t = $this->tiket('rblas');
        $this->svc->setKemajuan($t, 'jalan', 'uji-headit');

        foreach ($tabel as $x) $this->assertSame($sebelum[$x], DB::table($x)->count(), $x);
    }
}
