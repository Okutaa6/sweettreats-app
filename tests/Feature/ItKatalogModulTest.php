<?php

namespace Tests\Feature;

use App\Models\ItModul;
use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use App\Services\ItTracker\KatalogModul;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Katalog Modul — halaman saudara papan (rencana §14.24–14.25).
 *
 * Yang dijaga di sini adalah empat janji yang mudah rusak diam-diam:
 *  1. FITUR DITURUNKAN dari access_path — bukan diketik, jadi tak bisa basi.
 *  2. ⛔ TAK ADA TEBAKAN MODUL DARI JUDUL. Tiket tanpa modul_id masuk baris
 *     "Tanpa modul", dengan penjumlahan eksplisit yang bisa dicocokkan.
 *  3. Tampilan bawaan mengikuti PERAN (Executive → Matriks, IT → Daftar).
 *  4. Non-IT boleh membaca, tidak boleh menambah entri.
 *
 * Kelas terpisah — batas ±17 uji ber-DB per proses.
 */
class ItKatalogModulTest extends TestCase
{
    use DatabaseTransactions;

    private ItTrackerService $svc;
    private KatalogModul $katalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc     = app(ItTrackerService::class);
        $this->katalog = app(KatalogModul::class);

        try {
            DB::select('SELECT modul_id FROM it_ticket LIMIT 0');
            DB::select('SELECT nama, access_id FROM it_modul LIMIT 0');
        } catch (\Throwable) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_06_it_modul_katalog.sql dulu.');
        }
    }

    private function sesi(string $groupName, string $uid): array
    {
        return ['isLoggedIn' => true, 'id' => $uid,
            'group_id' => DB::table('group_path')->where('nama', $groupName)->value('id') ?: 'grp-uji',
            'group_name' => $groupName, 'name' => 'Uji ' . $groupName,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time()];
    }

    private function modul(array $isi = []): ItModul
    {
        return ItModul::create(array_merge([
            'nama' => 'Uji Modul ' . Str::random(6), 'departemen' => 'IT', 'jenis' => 'Platform',
            'status' => 'hidup', 'sejak' => '2026-05-01', 'sejak_terbukti' => true, 'urutan' => 990,
        ], $isi));
    }

    private function tiket(?int $modulId, string $fase = 'ANTRE'): ItTicket
    {
        $t = $this->svc->buat(['judul' => 'Uji katalog ' . Str::random(6), 'sumber' => 'permintaan',
            'jenis_permintaan' => 'Fitur baru', 'prioritas_pemohon' => 'Sedang', 'latar' => 'uji',
            'kebutuhan' => 'uji', 'terdampak' => 'x', 'unit' => 'sweettreats'], 'uji-pemohon-k');
        $this->svc->triase($t, 'M', 3, 'P2', 'uji-headit');
        if ($fase !== 'ANTRE') $this->svc->pindahFase($t = $t->fresh(), $fase, 'uji-headit');
        $t = $t->fresh();
        $t->modul_id = $modulId;
        $t->save();

        return $t->fresh();
    }

    // ─── Seeder: isi katalog = isi dokumen ─────────────────────────────────

    /** 37 entri · 25 hidup / 3 dibangun / 9 rencana · per jenis 21/8/2/6 (§14.26). Idempoten. */
    public function test_seeder_mengisi_37_modul_dan_idempoten(): void
    {
        Artisan::call('it:seed-katalog', ['--env-guard' => 'local']);
        $keluaran = Artisan::output();
        $this->assertStringContainsString('hidup 25', $keluaran);
        $this->assertStringContainsString('dibangun 3', $keluaran);
        $this->assertStringContainsString('rencana 9', $keluaran);

        $status = ItModul::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $jenis  = ItModul::selectRaw('jenis, COUNT(*) n')->groupBy('jenis')->pluck('n', 'jenis');
        $this->assertSame(37, ItModul::count());
        $this->assertSame([25, 3, 9], [$status['hidup'], $status['dibangun'], $status['rencana']]);
        $this->assertSame(21, $jenis['Operasional']);
        $this->assertSame(8, $jenis['Laporan & Dashboard']);
        $this->assertSame(2, $jenis['Integrasi']);
        $this->assertSame(6, $jenis['Platform']);
        $this->assertSame(8, ItModul::distinct()->count('departemen'));

        // jalan ulang: memperbarui, TIDAK menggandakan (37, bukan 74)
        Artisan::call('it:seed-katalog', ['--env-guard' => 'local']);
        $this->assertSame(37, ItModul::count());
        $this->assertStringContainsString('0 baru', Artisan::output());
    }

    /** Gerbang lingkungan: --env-guard yang salah menolak, tanpa menulis apa pun. */
    public function test_seeder_menolak_env_guard_salah(): void
    {
        $sebelum = ItModul::count();
        $kode = Artisan::call('it:seed-katalog', ['--env-guard' => 'prod']);
        $this->assertSame(1, $kode);
        $this->assertStringContainsString('DITOLAK', Artisan::output());
        $this->assertSame($sebelum, ItModul::count());
    }

    // ─── Fitur DITURUNKAN dari access_path ────────────────────────────────

    /**
     * Node bercabang → daun ber-link di bawahnya; daun ber-link → dirinya sendiri;
     * daun TANPA link → nol (ia memang tak pernah tampil di sidebar); tanpa node → nol.
     * Aturannya harus sama persis dengan shellCollectLeaves() di app/Helpers.php.
     */
    public function test_fitur_diturunkan_dari_pohon_menu(): void
    {
        $akar  = 'uji-kat-akar-' . Str::random(6);
        $anakA = 'uji-kat-a-' . Str::random(6);
        $anakB = 'uji-kat-b-' . Str::random(6);
        $mati  = 'uji-kat-mati-' . Str::random(6);
        DB::table('access_path')->insert([
            ['id' => $akar,  'pid' => '',    'nama' => 'Uji Katalog', 'icon' => '', 'urutan' => 900, 'link' => '', 'urutan_path' => null, 'pid_path' => null],
            ['id' => $anakA, 'pid' => $akar, 'nama' => 'Halaman A',   'icon' => '', 'urutan' => 1, 'link' => 'uji-a', 'urutan_path' => null, 'pid_path' => null],
            ['id' => $anakB, 'pid' => $akar, 'nama' => 'Halaman B',   'icon' => '', 'urutan' => 2, 'link' => 'uji-b', 'urutan_path' => null, 'pid_path' => null],
            ['id' => $mati,  'pid' => $akar, 'nama' => 'Baris Mati',  'icon' => '', 'urutan' => 3, 'link' => '', 'urutan_path' => null, 'pid_path' => null],
        ]);

        $k = app(KatalogModul::class);   // instans baru: pohonnya belum ter-cache
        $this->assertCount(2, $k->fitur($akar), 'node bercabang menghitung daun ber-link saja');
        $this->assertSame(['Halaman A', 'Halaman B'], array_column($k->fitur($akar), 'nama'));
        $this->assertCount(1, $k->fitur($anakA), 'daun ber-link = dirinya sendiri');
        $this->assertCount(0, $k->fitur($mati), 'daun tanpa link tak pernah tampil di sidebar — nol');
        $this->assertCount(0, $k->fitur(null), 'tanpa node menu → nol, bukan galat');
        $this->assertSame($akar, $k->cariJalur('Uji Katalog'));
        $this->assertSame($anakB, $k->cariJalur('Uji Katalog>Halaman B'));
        $this->assertNull($k->cariJalur('Uji Katalog>Tak Ada'));
    }

    /** Modul tanpa access_id → "tanpa menu" di layar, 0 fitur, TIDAK galat. */
    public function test_modul_tanpa_menu_tidak_galat(): void
    {
        $m = $this->modul(['nama' => 'Uji Tanpa Menu ' . Str::random(4), 'access_id' => null, 'status' => 'hidup']);

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.katalog'))->assertOk()
            ->assertSee($m->nama)->assertSee('tanpa menu');
        $this->assertCount(0, $this->katalog->fitur($m->access_id));
    }

    // ─── ⛔ Tanpa tebakan: baris "Tanpa modul" + penjumlahan yang bisa dicocokkan ──

    /**
     * Tiket yang judulnya "berbau" modul TIDAK ditebak — ia tetap masuk
     * "Tanpa modul". Inilah pagar terhadap regex modulOf() di mockup: tebakan
     * yang meleset tak terlihat siapa pun dan merusak angka utang selamanya.
     */
    public function test_tiket_tanpa_modul_tidak_ditebak_dari_judul(): void
    {
        $m = $this->modul(['nama' => 'Petty Cash Uji ' . Str::random(4)]);
        $t = $this->tiket(null);
        $t->judul = 'Petty Cash — kategori SQL pensiun (uji tebakan)';
        $t->save();

        $html = $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.katalog'))->assertOk()
            ->assertSee('Tanpa modul')->getContent();

        $this->assertNull($t->fresh()->modul_id, 'judul tidak boleh menautkan modul');
        // nomornya muncul di daftar yatim, bukan di bawah modul Petty Cash
        $this->assertStringContainsString(str_replace('IT-2026-', '#', $t->nomor), $html);
        $this->assertSame(0, ItTicket::where('modul_id', $m->id)->count());
    }

    /** Penjumlahan "N bertaut + M tanpa modul = X" HARUS sama dengan cacah tiket terbuka. */
    public function test_penjumlahan_bertaut_dan_tanpa_modul_cocok(): void
    {
        $m = $this->modul();
        $this->tiket($m->id);
        $this->tiket($m->id, 'DEV');
        $this->tiket(null);

        $terbuka = ItTicket::whereNotIn('fase', ['SELESAI', 'DITOLAK'])->count();
        $yatim   = ItTicket::whereNotIn('fase', ['SELESAI', 'DITOLAK'])->whereNull('modul_id')->count();

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.katalog'))->assertOk()
            ->assertSee(($terbuka - $yatim) . ' bertaut + ' . $yatim . ' tanpa modul = ' . $terbuka . ' tiket terbuka di papan');
    }

    /** Utang = tiket terbuka termasuk yang DITUNDA; berjalan = terbuka tapi tidak ditunda. */
    public function test_utang_menghitung_yang_ditunda_berjalan_tidak(): void
    {
        $m = $this->modul();
        $this->tiket($m->id);
        $tunda = $this->tiket($m->id, 'DEV');
        $this->svc->parkir($tunda, 'DITUNDA', 'menunggu keputusan', 'uji-headit');
        $tutup = $this->tiket($m->id, 'DEV');
        $this->svc->parkir($tutup, 'DITOLAK', 'duplikat', 'uji-headit');

        $cacah = $this->katalog->cacahTiket()[$m->id];
        $this->assertSame(2, $cacah['terbuka'], 'yang DITOLAK tidak lagi jadi utang');
        $this->assertSame(1, $cacah['berjalan']);
        $this->assertSame(1, $cacah['ditunda']);
    }

    /** Angka tiket bisa ditelusuri: papan menerima ?modul= dan menyebutnya. */
    public function test_klik_angka_membuka_papan_tersaring(): void
    {
        $m    = $this->modul();
        $ikut = $this->tiket($m->id);
        $luar = $this->tiket(null);

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.index', ['modul' => $m->id]))->assertOk()
            ->assertSee('Modul: ' . $m->nama, false)
            ->assertSee($ikut->judul)
            ->assertDontSee($luar->judul);
    }

    // ─── Lensa, tampilan, peran ───────────────────────────────────────────

    /** Bawaan mengikuti peran: Executive mendarat di Matriks, Head IT di Daftar. */
    public function test_tampilan_bawaan_mengikuti_peran(): void
    {
        $exec = $this->withSession($this->sesi('Executive', 'uji-robi'))
            ->get(route('it-tracker.katalog'))->assertOk()->getContent();
        $this->assertStringContainsString('itk-mx-grid', $exec, 'Executive mendarat di Matriks');

        $it = $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.katalog'))->assertOk()->getContent();
        $this->assertStringContainsString('itk-kat-row kepala', $it, 'Head IT mendarat di Daftar');
        $this->assertStringNotContainsString('itk-mx-grid', $it);

        // pilihan pengguna tetap menang atas bawaan peran
        $this->withSession($this->sesi('Executive', 'uji-robi'))
            ->get(route('it-tracker.katalog', ['tampilan' => 'daftar']))->assertOk()
            ->assertSee('itk-kat-row kepala', false);
    }

    /** Lensa per jenis: kolom ketiga berubah arti jadi PEMILIK, bukan mengulang judul. */
    public function test_lensa_jenis_menampilkan_pemilik_di_kolom_ketiga(): void
    {
        Artisan::call('it:seed-katalog', ['--env-guard' => 'local']);

        $dept = $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.katalog', ['lensa' => 'dept']))->assertOk()->getContent();
        $this->assertStringContainsString('>JENIS<', $dept);

        $jenis = $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.katalog', ['lensa' => 'jenis']))->assertOk()->getContent();
        $this->assertStringContainsString('>PEMILIK<', $jenis);
        $this->assertStringNotContainsString('>JENIS<', $jenis);
    }

    // ─── Hak: baca untuk semua yang boleh masuk, tulis hanya Head IT ──────

    /** Non-IT boleh MEMBACA katalog tapi tak melihat & tak bisa memakai "＋ Entri modul". */
    public function test_non_it_boleh_baca_tidak_boleh_menambah(): void
    {
        $this->withSession($this->sesi('Executive', 'uji-robi'))
            ->get(route('it-tracker.katalog'))->assertOk()
            ->assertSee('APA SAJA YANG KAMI PUNYA')
            ->assertDontSee('＋ Entri modul');

        $this->withSession($this->sesi('Executive', 'uji-robi'))
            ->post(route('it-tracker.katalog.store'), ['nama' => 'Tembus ' . Str::random(4),
                'departemen' => 'IT', 'jenis' => 'Platform', 'status' => 'rencana'])
            ->assertStatus(403);

        $this->withSession($this->sesi('IT', 'uji-reza'))
            ->post(route('it-tracker.katalog.store'), ['nama' => 'Tembus2 ' . Str::random(4),
                'departemen' => 'IT', 'jenis' => 'Platform', 'status' => 'rencana'])
            ->assertStatus(403);
    }

    /** Head IT menambah entri; nama kembar ditolak (nama = kunci idempotensi seeder). */
    public function test_head_it_menambah_entri_dan_nama_kembar_ditolak(): void
    {
        $nama = 'Modul Uji Baru ' . Str::random(5);

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.katalog.store'), ['nama' => $nama, 'departemen' => 'Finance',
                'jenis' => 'Integrasi', 'status' => 'rencana', 'catatan' => 'lahir dari tiket uji'])
            ->assertRedirect()->assertSessionHas('success');

        $m = ItModul::where('nama', $nama)->first();
        $this->assertNotNull($m);
        $this->assertSame('rencana', $m->status);
        $this->assertNull($m->sejak, 'tanggal tidak dikarang saat entri dibuat');
        $this->assertFalse((bool) $m->sejak_terbukti);

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.katalog.store'), ['nama' => $nama, 'departemen' => 'IT',
                'jenis' => 'Platform', 'status' => 'rencana'])
            ->assertSessionHasErrors('nama');
        $this->assertSame(1, ItModul::where('nama', $nama)->count());
    }

    // ─── Sambungan papan ⇄ katalog ────────────────────────────────────────

    /** Triase menanyakan modul — dan boleh dikosongkan tanpa memaksa tebakan. */
    public function test_triase_menyimpan_modul_dan_boleh_kosong(): void
    {
        $m = $this->modul();

        $t = $this->svc->buat(['judul' => 'Uji triase modul ' . Str::random(4), 'sumber' => 'permintaan',
            'jenis_permintaan' => 'Fitur baru', 'prioritas_pemohon' => 'Sedang', 'latar' => 'uji',
            'kebutuhan' => 'uji', 'terdampak' => 'x'], 'uji-pemohon-k');
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.triase', $t->id), ['jenjang' => 'M', 'bobot' => 3, 'prioritas_it' => 'P2', 'modul_id' => $m->id])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame($m->id, $t->fresh()->modul_id);

        $u = $this->svc->buat(['judul' => 'Uji triase tanpa modul ' . Str::random(4), 'sumber' => 'permintaan',
            'jenis_permintaan' => 'Fitur baru', 'prioritas_pemohon' => 'Sedang', 'latar' => 'uji',
            'kebutuhan' => 'uji', 'terdampak' => 'x'], 'uji-pemohon-k');
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.triase', $u->id), ['jenjang' => 'S', 'bobot' => 2, 'prioritas_it' => 'P3'])
            ->assertRedirect();
        $this->assertNull($u->fresh()->modul_id);
    }

    /** Detail tiket menyebut modulnya — atau menyebut bahwa ia belum punya. Head IT bisa membetulkan. */
    public function test_detail_menyebut_modul_dan_head_it_bisa_menautkan(): void
    {
        $m = $this->modul();
        $t = $this->tiket(null);

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.show', $t->id))->assertOk()->assertSee('Tanpa modul katalog');

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.modul', $t->id), ['modul_id' => $m->id])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame($m->id, $t->fresh()->modul_id);

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.show', $t->id))->assertOk()->assertSee($m->nama);

        // dilepas lagi → kembali ke "Tanpa modul"
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.modul', $t->id), ['modul_id' => ''])->assertRedirect();
        $this->assertNull($t->fresh()->modul_id);

        // IT pelaksana tidak boleh menautkan
        $this->withSession($this->sesi('IT', 'uji-reza'))
            ->post(route('it-tracker.modul', $t->id), ['modul_id' => $m->id])->assertStatus(403);
    }

    /** KENDALI NEGATIF: papan tidak berubah perilakunya tanpa parameter modul. */
    public function test_papan_tak_berubah_tanpa_parameter_modul(): void
    {
        $m = $this->modul();
        $a = $this->tiket($m->id);
        $b = $this->tiket(null);

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.index'))->assertOk()
            ->assertSee($a->judul)->assertSee($b->judul)
            ->assertDontSee('Modul: ' . $m->nama, false);
    }
}
