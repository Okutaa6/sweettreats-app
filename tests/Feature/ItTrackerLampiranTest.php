<?php

namespace Tests\Feature;

use App\Models\ItForm;
use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Lampiran FRM-IT-01 (rencana §7 B, utang §14.11). Kelas TERPISAH (batas ±17
 * uji ber-DB per proses). Berkas nyata ditulis ke public/uploads/it-tracker —
 * dihapus lagi di tearDown; baris DB ikut rollback transaksi.
 */
class ItTrackerLampiranTest extends TestCase
{
    use DatabaseTransactions;

    private ItTrackerService $svc;
    private array $berkasDibuat = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);
        if (! DB::select("SHOW TABLES LIKE 'it_form'")) {
            $this->markTestSkipped('Tabel it_form belum ada.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->berkasDibuat as $p) @unlink(public_path($p));
        parent::tearDown();
    }

    private function sesi(string $groupName, ?string $uid = null): array
    {
        return [
            'isLoggedIn' => true, 'id' => $uid ?: ('uji-' . Str::random(8)),
            'group_id'   => DB::table('group_path')->where('nama', $groupName)->value('id') ?: 'grp-uji',
            'group_name' => $groupName, 'name' => 'Uji ' . $groupName,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time(),
        ];
    }

    private function lampiranDari(ItTicket $t): array
    {
        $l = ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-01')->value('payload')['lampiran'] ?? [];
        foreach ($l as $x) $this->berkasDibuat[] = $x['path'];
        return $l;
    }

    private function isiPermintaan(): array
    {
        return ['judul' => 'Uji lampiran ' . Str::random(5), 'sumber' => 'permintaan', 'jenis_permintaan' => 'Fitur baru',
            'prioritas_pemohon' => 'Sedang', 'latar' => 'FO kirim screenshot lewat WA', 'kebutuhan' => 'bukti menempel di tiket', 'terdampak' => 'FO'];
    }

    /** Pemohon melampirkan 2 berkas saat mengisi → tampil di detail DAN di ekspor (gambar = pratinjau). */
    public function test_pemohon_melampirkan_dua_berkas_tampil_di_detail_dan_ekspor(): void
    {
        $ses = $this->sesi('Head Marketing', 'uji-pemohon-l');
        $this->withSession($ses)->post(route('it-tracker.store'), $this->isiPermintaan() + ['lampiran' => [
            UploadedFile::fake()->image('layar-error.png', 320, 200),
            UploadedFile::fake()->create('contoh-laporan.xlsx', 40, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]])->assertRedirect()->assertSessionHas('success');

        $t = ItTicket::orderByDesc('id')->first();
        $l = $this->lampiranDari($t);
        $this->assertCount(2, $l);
        $this->assertSame('layar-error.png', $l[0]['nama']);
        $this->assertStringStartsWith('uploads/it-tracker/frm01/', $l[0]['path']);
        $this->assertFileExists(public_path($l[0]['path']));
        $this->assertSame('uji-pemohon-l', $l[0]['oleh']);

        $this->withSession($ses)->get(route('it-tracker.show', $t->id))->assertOk()
            ->assertSee('layar-error.png')->assertSee('contoh-laporan.xlsx')->assertSee('itk-lamp img', false);
        $this->withSession($ses)->get(route('it-tracker.ekspor', $t->id))->assertOk()
            ->assertSee('LAMPIRAN')->assertSee('L-01')->assertSee('contoh-laporan.xlsx')
            ->assertSee('<img class="itk-doc-lamp-img"', false);
    }

    /** Jenis tak dikenal ditolak dengan pesan yang MENYEBUT yang diterima (P3), tiket tidak lahir. */
    public function test_jenis_tak_dikenal_ditolak_dengan_pesan_yang_menyebut_yang_diterima(): void
    {
        $n = ItTicket::count();
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon-l'))
            ->from(route('it-tracker.permintaan'))
            ->post(route('it-tracker.store'), $this->isiPermintaan() + ['lampiran' => [
                UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
            ]])->assertRedirect(route('it-tracker.permintaan'))
            ->assertSessionHasErrors(['lampiran.0']);

        $pesan = session('errors')->first('lampiran.0');
        $this->assertStringContainsString('Yang diterima', $pesan);
        $this->assertStringContainsString('PDF', $pesan);
        $this->assertStringContainsString('Excel', $pesan);
        $this->assertSame($n, ItTicket::count(), 'validasi gagal = tiket tidak lahir');
    }

    /** Menambah belakangan: pemohon boleh selama TRIASE, orang lain 403, Head IT kapan saja; lampiran BERTAHAN lewat fase. */
    public function test_tambah_belakangan_hak_akses_dan_bertahan_lewat_fase(): void
    {
        $t = $this->svc->buat($this->isiPermintaan(), 'uji-pemohon-l');
        $img = fn ($n) => UploadedFile::fake()->image($n . '.png', 50, 50);

        // orang lain (Head departemen lain) → 403, bukan 302
        $this->withSession($this->sesi('Head Marketing', 'uji-orang-lain'))
            ->post(route('it-tracker.lampiran', $t->id), ['lampiran' => [$img('a')]])->assertForbidden();
        // IT dev (bukan Head IT) → 403
        $this->withSession($this->sesi('IT', 'uji-reza'))
            ->post(route('it-tracker.lampiran', $t->id), ['lampiran' => [$img('a')]])->assertForbidden();

        // pemohon di TRIASE → boleh
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon-l'))
            ->post(route('it-tracker.lampiran', $t->id), ['lampiran' => [$img('a')]])->assertRedirect()->assertSessionHas('success');
        $this->assertCount(1, $this->lampiranDari($t));

        // sesudah ditriase (ANTRE) pemohon TIDAK boleh lagi, Head IT boleh
        $this->svc->triase($t, 'M', 3, 'P2', 'uji-headit');
        $t = $t->fresh();
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon-l'))
            ->post(route('it-tracker.lampiran', $t->id), ['lampiran' => [$img('b')]])->assertForbidden();
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.lampiran', $t->id), ['lampiran' => [$img('b')]])->assertRedirect()->assertSessionHas('success');

        // maju beberapa fase — lampiran tetap ada & tetap tampil
        $this->svc->pindahFase($t, 'ANALISIS', 'uji-headit');
        $this->svc->pindahFase($t->fresh(), 'DEV', 'uji-headit');
        $l = $this->lampiranDari($t);
        $this->assertCount(2, $l);
        $this->assertSame(['a.png', 'b.png'], array_column($l, 'nama'));
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon-l'))
            ->get(route('it-tracker.show', $t->id))->assertOk()->assertSee('a.png')->assertSee('b.png');

        // rute lampiran tanpa berkas → validasi menyebut yang diterima
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->from(route('it-tracker.show', $t->id))
            ->post(route('it-tracker.lampiran', $t->id), [])->assertRedirect()->assertSessionHasErrors('lampiran');
    }

    /** Batas jumlah per tiket ditegakkan di layanan. */
    public function test_batas_jumlah_lampiran(): void
    {
        $t = $this->svc->buat($this->isiPermintaan(), 'uji-pemohon-l');
        $banyak = array_map(fn ($i) => UploadedFile::fake()->image("f$i.png", 10, 10), range(1, ItTrackerService::LAMPIRAN_MAKS));
        $this->svc->tambahLampiran($t, $banyak, 'uji-headit');
        $this->assertCount(ItTrackerService::LAMPIRAN_MAKS, $this->lampiranDari($t));
        try {
            $this->svc->tambahLampiran($t, [UploadedFile::fake()->image('lebih.png', 10, 10)], 'uji-headit');
            $this->fail('melebihi batas harus ditolak');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Maksimal', $e->getMessage());
        }
    }

    /** Kendali negatif: fitur ini tak menyentuh tabel lama & RopAttachment lama tetap menulis ke uploads/rop. */
    public function test_tabel_lama_dan_rop_tak_berubah(): void
    {
        $tabel = ['users', 'rop_requests', 'att_day', 'group_access'];
        $sebelum = collect($tabel)->mapWithKeys(fn ($x) => [$x => DB::table($x)->count()]);

        $t = $this->svc->buat($this->isiPermintaan(), 'uji-pemohon-l');
        $this->svc->tambahLampiran($t, [UploadedFile::fake()->image('x.png', 10, 10)], 'uji-headit');
        $this->lampiranDari($t);

        foreach ($tabel as $x) $this->assertSame($sebelum[$x], DB::table($x)->count(), $x);

        // jalur ROP lama: folder dasar tetap uploads/rop/<jenis>
        $p = app(\App\Services\RopAttachment::class)->simpanBerkas(UploadedFile::fake()->image('nota.jpg', 10, 10), 'uji-rop');
        $this->berkasDibuat[] = $p;
        $this->assertStringStartsWith('uploads/rop/uji-rop/', $p);
    }
}
