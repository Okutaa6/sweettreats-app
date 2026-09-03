<?php

namespace Tests\Feature;

use App\Models\ItForm;
use App\Models\ItFr;
use App\Models\ItTestCase;
use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use App\Services\ItTracker\PhaseEngine;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * IT Request Tracker FASE 2b — uji terima §14.14 (tambahan atas §14.6).
 * Kelas TERPISAH (batas ±17 uji ber-DB per proses). DatabaseTransactions;
 * SHOW TABLES LIKE, bukan Schema::hasTable().
 */
class ItTracker2bTest extends TestCase
{
    use DatabaseTransactions;

    private ItTrackerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);

        // ⚠️ Bukan SHOW COLUMNS/DESCRIBE (ikut membunuh mysqld lokal, sama
        // seperti information_schema) — SELECT … LIMIT 0 lewat jalur kueri biasa.
        try {
            DB::select('SELECT terkunci FROM it_test_case LIMIT 0');
            DB::select('SELECT lahir_dari_id, lahir_via FROM it_ticket LIMIT 0');
        } catch (\Throwable) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_03_it_tracker_2b.sql dulu.');
        }
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

    private function tiket(string $jenjang = 'M', string $fase = 'ANALISIS', string $uid = 'uji-pemohon'): ItTicket
    {
        $t = $this->svc->buat([
            'judul' => 'Uji 2b ' . Str::random(6), 'sumber' => 'permintaan',
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

    /** bentuk mesin fase tiket (nama bukan view(): bentrok dgn TestCase Laravel) */
    private function lihat(ItTicket $t): array
    {
        return $this->svc->viewTicket($this->svc->muatRelasi(ItTicket::query())->find($t->id));
    }

    // ─── FRM-IT-02 ─────────────────────────────────────────────────────────

    /** Kendali negatif: tiket M TIDAK PERNAH melihat satu pun field khusus L. */
    public function test_tiket_m_tak_melihat_field_l(): void
    {
        $t = $this->tiket('M');

        $html = $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get('/it-tracker/tiket/' . $t->id)->assertStatus(200)->getContent();

        $this->assertStringContainsString('02 RINGKAS', $html);
        foreach (['02 PENUH', 'TIDAK TERMASUK', 'DESAIN TEKNIS', 'name="lingkup"', 'name="luar"', 'name="desain"'] as $fieldL) {
            $this->assertStringNotContainsString($fieldL, $html, "Field L bocor ke tiket M: $fieldL");
        }
        $this->assertStringContainsString('naikkan jenjang', $html, 'Jalan keluar ke L lewat triase, bukan membuka field L.');

        // …dan server pun menolak menulis field L ke tiket M
        try {
            $this->svc->setLingkupL($t, 'a', 'b', 'c');
            $this->fail('setLingkupL pada M harus ditolak.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('jenjang L', $e->getMessage());
        }

        // L melihatnya
        $l = $this->tiket('L');
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get('/it-tracker/tiket/' . $l->id)->assertStatus(200)->assertSee('02 PENUH')->assertSee('TIDAK TERMASUK');
    }

    /** T2 §14.13: mengubah baris FR sesudah diparaf MENGHAPUS paraf; gerbang menutup lagi. */
    public function test_edit_fr_sesudah_paraf_menghapus_paraf(): void
    {
        $t = $this->tiket('M');
        $this->svc->simpanFr($t, [['kebutuhan' => 'Push invoice', 'prioritas' => 'Tinggi', 'kriteria' => 'muncul di Accurate H+1']]);
        $this->svc->setKomponen($t, ['Aplikasi']);
        $this->svc->parafBo($t, 'uji-robi', 'Robi');

        $g = PhaseEngine::gateInfo($this->lihat($t));
        $this->assertTrue($g['ok'], 'FR + komponen + paraf → gerbang terbuka.');

        // menyimpan ulang tabel yang SAMA tidak menggugurkan paraf
        $this->svc->simpanFr($t, [['kebutuhan' => 'Push invoice', 'prioritas' => 'Tinggi', 'kriteria' => 'muncul di Accurate H+1']]);
        $this->assertTrue(PhaseEngine::gateInfo($this->lihat($t))['ok'], 'Simpan ulang tanpa perubahan tak boleh menghapus paraf.');

        // mengubah kriteria → paraf hilang, gerbang menutup
        $this->svc->simpanFr($t, [['kebutuhan' => 'Push invoice', 'prioritas' => 'Tinggi', 'kriteria' => 'muncul di Accurate H+1 06:00']]);
        $v = $this->lihat($t);
        $this->assertArrayNotHasKey('paraf', $v['fr02']);
        $g = PhaseEngine::gateInfo($v);
        $this->assertFalse($g['ok']);
        $this->assertSame(['paraf satu Business Owner'], $g['missing']);
    }

    /** Paraf = Business Owner (Executive ATAU Konsultan), bukan IT; catatan dapur tak menggugurkan paraf. */
    public function test_paraf_bo_konsultan_boleh_it_tidak(): void
    {
        $t = $this->tiket('M');
        $this->svc->simpanFr($t, [['kebutuhan' => 'x', 'kriteria' => 'y']]);
        $this->svc->setKomponen($t, ['API']);

        $this->withSession($this->sesi('Head IT', 'uji-headit'))->post('/it-tracker/tiket/' . $t->id . '/paraf')->assertStatus(403);
        $this->withSession($this->sesi('IT'))->post('/it-tracker/tiket/' . $t->id . '/paraf')->assertStatus(403);

        // Konsultan = Business Owner juga (JANGAN patok Executive)
        $konsultan = DB::table('group_path')->where('nama', 'like', 'Konsultan%')->value('nama')
            ?: DB::table('group_path')->where('nama', 'Executive')->value('nama');
        $this->withSession($this->sesi($konsultan))->post('/it-tracker/tiket/' . $t->id . '/paraf')->assertStatus(302);
        $this->assertNotEmpty($this->lihat($t)['fr02']['paraf']);

        // catatan dapur (internal IT, tak dilihat BO) TIDAK menggugurkan paraf
        $this->svc->setDapur($t, '5 hari kerja', 'RBAC');
        $this->assertNotEmpty($this->lihat($t)['fr02']['paraf']);
    }

    /** Ketergantungan = relasi: DITUNDA boleh, DITOLAK tidak; chip tampil di papan. */
    public function test_dependensi_relasi_ke_tiket(): void
    {
        $t = $this->tiket('M');
        $ditunda = $this->svc->buat(['judul' => 'Uji dep ' . Str::random(4), 'sumber' => 'inisiatif',
            'jenjang' => 'S', 'bobot' => 1, 'prioritas_it' => 'P3'], 'uji-headit');
        $this->svc->parkir($ditunda, 'DITUNDA', 'nanti', 'uji-headit');
        $ditolak = $this->svc->buat(['judul' => 'Uji tolak ' . Str::random(4), 'sumber' => 'inisiatif',
            'jenjang' => 'S', 'bobot' => 1, 'prioritas_it' => 'P3'], 'uji-headit');
        $this->svc->parkir($ditolak, 'DITOLAK', 'tidak', 'uji-headit');

        $this->svc->tambahDep($t, $ditunda->nomor);
        try {
            $this->svc->tambahDep($t, $ditolak->nomor);
            $this->fail('DITOLAK tak boleh jadi ketergantungan.');
        } catch (DomainException) {
        }

        $this->assertSame([$ditunda->nomor], $this->lihat($t)['deps']);
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get('/it-tracker')->assertStatus(200)->assertSee('menunggu ' . $ditunda->nomor);
    }

    // ─── FRM-IT-04 ─────────────────────────────────────────────────────────

    /** TC-01..05 terkunci: tak bisa dihapus / diganti nama; tetap diisi & terhitung. */
    public function test_tc_tetap_terkunci_tapi_terhitung(): void
    {
        $t = $this->tiket('L', 'UJI');

        $tc = ItTestCase::where('ticket_id', $t->id)->orderBy('kode')->get();
        $this->assertCount(5, $tc, 'Masuk UJI → lima pemeriksaan tetap sudah menunggu.');
        $this->assertTrue($tc->every(fn ($r) => $r->terkunci));
        $this->assertStringContainsString('Fitur lama terkait tetap berjalan', $tc[4]->skenario);

        foreach (['hapus' => fn () => $this->svc->hapusTc($t, 'TC-05'),
                  'ganti nama' => fn () => $this->svc->simpanTc($t, [['kode' => 'TC-05', 'skenario' => 'diganti', 'status' => 'pass']])] as $apa => $coba) {
            try { $coba(); $this->fail("TC-05 tak boleh $apa."); } catch (DomainException $e) { $this->assertStringContainsString('pemeriksaan tetap', $e->getMessage()); }
        }

        // diisi normal + baris baru lewat hantu; ringkasan menghitung SEMUANYA
        $this->svc->simpanTc($t, [
            ['kode' => 'TC-01', 'aktual' => 'ok', 'status' => 'pass'], ['kode' => 'TC-02', 'aktual' => 'ok', 'status' => 'pass'],
            ['kode' => 'TC-03', 'aktual' => 'ok', 'status' => 'pass'], ['kode' => 'TC-04', 'aktual' => '500', 'status' => 'fail'],
            ['kode' => 'TC-05', 'aktual' => 'ok', 'status' => 'pass'],
            ['kode' => '', 'skenario' => 'Ubah akses lalu login ulang', 'diharapkan' => 'berlaku', 'aktual' => 'berlaku', 'status' => 'pass'],
        ]);
        $v = $this->lihat($t);
        $this->assertCount(6, $v['fr04']['tc']);
        $this->assertSame('TC-06', $v['fr04']['tc'][5]['id']);
        $g = PhaseEngine::gateInfo($v);
        $this->assertContains('1 test case masih Fail', $g['missing']);

        // TC bebas boleh dihapus; TC-05 masih ada
        $this->svc->hapusTc($t, 'TC-06');
        $this->assertCount(5, $this->lihat($t)['fr04']['tc']);
        $this->assertTrue(ItTestCase::where('ticket_id', $t->id)->where('kode', 'TC-05')->exists());
    }

    /** 1 Major terbuka → gerbang UAT tertutup dengan kalimat menyebut jumlah; ditutup → terbuka. */
    public function test_major_terbuka_menahan_gerbang_uat(): void
    {
        $t = $this->tiket('L', 'UJI');
        $this->svc->simpanTc($t, array_map(fn ($i) => ['kode' => sprintf('TC-%02d', $i), 'aktual' => 'ok', 'status' => 'pass'], range(1, 5)));
        $major = $this->svc->tambahTemuan($t, 'Error 500 di halaman akses', 'Major');
        $this->svc->tambahTemuan($t, 'Label kurang jelas', 'Minor');

        try {
            $this->svc->majukan($t->fresh(), 'uji-headit');
            $this->fail('Major terbuka harus menahan gerbang.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('1 temuan Major masih terbuka — menahan gerbang', $e->getMessage());
        }

        $this->svc->ubahTemuan($t, $major->id, ['status' => 'Selesai']);
        $this->assertSame('UAT', $this->svc->majukan($t->fresh(), 'uji-headit'), 'Major selesai (Minor tetap terbuka) → gerbang terbuka.');
    }

    // ─── FRM-IT-05 ─────────────────────────────────────────────────────────

    /** "Perlu Perbaikan" → fase kembali DEV DAN konfirmasi FRM-IT-03 ter-reset. */
    public function test_perlu_perbaikan_kembali_dev_dan_reset_konfirmasi(): void
    {
        $t = $this->tiket('M', 'DEV');
        $this->svc->konfirmasiDev($t, 'selesai', 'uji-headit');
        $this->assertNotNull(ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-03')->value('dikonfirmasi_at'));
        $this->svc->pindahFase($t = $t->fresh(), 'UAT', 'uji-headit');

        $this->assertSame('DEV', $this->svc->uatPutuskan($t->fresh(), 'perbaikan', 'uji-pemohon'));
        $this->assertSame('DEV', $t->fresh()->fase);
        $this->assertNull(ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-03')->value('dikonfirmasi_at'),
            'Konfirmasi 03 harus DIRESET — kode yang ditolak pemohon dikonfirmasi ulang.');
        $this->assertFalse(PhaseEngine::gateInfo($this->lihat($t))['ok'], 'Gerbang DEV tertutup lagi.');
    }

    /** Skenario ditulis IT, dijawab pemohon; "dengan Catatan" mati tanpa catatan, lalu catatan → tindak lanjut. */
    public function test_uat_skenario_jawaban_dan_catatan(): void
    {
        $t = $this->tiket('M', 'DEV');
        $this->svc->simpanSkenario($t, [['proses' => 'Buka halaman Cuti', 'harapan' => 'sisa 12 hari'], ['proses' => 'Ajukan cuti 2 hari', 'harapan' => 'sisa 10']]);
        $this->svc->pindahFase($t = $t->fresh(), 'UAT', 'uji-headit');

        // tanpa catatan: "Disetujui dengan Catatan" ditolak
        try { $this->svc->uatPutuskan($t->fresh(), 'catatan', 'uji-pemohon'); $this->fail(); }
        catch (DomainException $e) { $this->assertStringContainsString('catatan', $e->getMessage()); }

        // pemohon menjawab lewat HTTP
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon'))
            ->post('/it-tracker/tiket/' . $t->id . '/jawab', [
                'jawaban' => [0 => ['sesuai' => '1'], 1 => ['sesuai' => '0', 'catatan' => 'sisa jadi 11, bukan 10']],
                'masukan' => 'tombol ajukan kecil',
            ])->assertStatus(302)->assertSessionHasNoErrors();

        // IT tak boleh menjawab untuk pemohon
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post('/it-tracker/tiket/' . $t->id . '/jawab', ['masukan' => 'x'])->assertStatus(403);

        $this->assertSame('GOLIVE', $this->svc->uatPutuskan($t->fresh(), 'catatan', 'uji-pemohon'));
        $tl = $this->lihat($t)['fr05']['tindakLanjut'];
        $this->assertCount(2, $tl, 'catatan baris Tidak + masukan → 2 butir tindak lanjut');
        $this->assertSame('UAT', $tl[0]['via']);
    }

    // ─── spawnTicket — SATU mekanisme ──────────────────────────────────────

    /** RTL evaluasi → tiket baru di TRIASE, pemohon sama, induk tercatat; catatan UAT jalan yang sama. */
    public function test_spawn_tiket_dari_evaluasi_dan_uat(): void
    {
        $t = $this->tiket('L', 'EVALUASI', 'uji-pemohon');
        $this->svc->simpanEvaluasi($t, ['sesuai' => 4], [], [['txt' => 'Antre upload foto offline']]);

        $anak = $this->svc->lahirkanTiket($this->svc->muatRelasi(ItTicket::query())->find($t->id), 'evaluasi', 0, 'uji-headit');
        $this->assertSame('TRIASE', $anak->fase);
        $this->assertSame('uji-pemohon', $anak->pemohon_user_id);
        $this->assertSame($t->id, $anak->lahir_dari_id);
        $this->assertSame('Evaluasi', $anak->lahir_via);
        $this->assertNull($anak->jenjang);
        $this->assertSame($anak->nomor, $this->lihat($t)['fr07']['rtl'][0]['ticketId']);

        // dua kali → ditolak
        try { $this->svc->lahirkanTiket($this->svc->muatRelasi(ItTicket::query())->find($t->id), 'evaluasi', 0, 'uji-headit'); $this->fail(); }
        catch (DomainException $e) { $this->assertStringContainsString('sudah dijadikan tiket', $e->getMessage()); }

        // jalan yang SAMA untuk catatan UAT
        $u = $this->tiket('M', 'UAT');
        ItForm::updateOrCreate(['ticket_id' => $u->id, 'kode' => 'FRM-IT-05'],
            ['payload' => ['skenario' => [], 'masukan' => '', 'tindakLanjut' => [['id' => 'TL-01', 'txt' => 'perbesar tombol', 'via' => 'UAT', 'ticketId' => null]]]]);
        $anak2 = $this->svc->lahirkanTiket($this->svc->muatRelasi(ItTicket::query())->find($u->id), 'uat', 0, 'uji-headit');
        $this->assertSame('UAT', $anak2->lahir_via);
        $this->assertSame('TRIASE', $anak2->fase);

        // papan menampilkan chip asal
        $this->withSession($this->sesi('Head IT', 'uji-headit'))->get('/it-tracker')->assertStatus(200)->assertSee('dari ' . $t->nomor);
    }

    // ─── FRM-IT-07 ─────────────────────────────────────────────────────────

    /** Evaluator = pemohon (default), bisa dialihkan Head IT; orang lain ditolak. */
    public function test_evaluasi_hanya_evaluator(): void
    {
        $t = $this->tiket('L', 'EVALUASI', 'uji-pemohon');

        $this->withSession($this->sesi('Executive', 'uji-robi'))
            ->post('/it-tracker/tiket/' . $t->id . '/evaluasi', ['aspek' => ['sesuai' => 5]])->assertStatus(403);
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon'))
            ->post('/it-tracker/tiket/' . $t->id . '/evaluasi', ['aspek' => ['sesuai' => 5, 'mudah' => 4]])->assertStatus(302);
        $this->assertSame(['sesuai' => 5, 'mudah' => 4], $this->lihat($t)['fr07']['aspek']);

        // dialihkan ke Robi → Robi boleh, pemohon tidak lagi
        $this->svc->alihkanEvaluator($t, 'uji-robi');
        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon'))
            ->post('/it-tracker/tiket/' . $t->id . '/evaluasi', ['aspek' => ['stabil' => 3]])->assertStatus(403);
        $this->withSession($this->sesi('Executive', 'uji-robi'))
            ->post('/it-tracker/tiket/' . $t->id . '/evaluasi', ['aspek' => ['sesuai' => 5, 'mudah' => 4, 'stabil' => 5, 'dampak' => 4, 'manfaat' => 5]])->assertStatus(302);

        $this->assertTrue(PhaseEngine::gateInfo($this->lihat($t))['ok'], 'Lima aspek → tiket bisa ditutup.');
    }

    // ─── Kendali negatif ───────────────────────────────────────────────────

    public function test_tabel_lama_tak_tersentuh(): void
    {
        $tabel = ['users', 'att_day', 'rop_requests', 'omnicare_commission', 'group_access', 'access_path'];
        $sebelum = [];
        foreach ($tabel as $tb) $sebelum[$tb] = DB::table($tb)->count();

        $t = $this->tiket('L', 'UJI');
        $this->svc->simpanTc($t, [['kode' => 'TC-01', 'status' => 'pass']]);
        $this->svc->tambahTemuan($t, 'x', 'Minor');
        $a = $this->tiket('M');
        $this->svc->simpanFr($a, [['kebutuhan' => 'a', 'kriteria' => 'b']]);
        $this->svc->parafBo($a, 'uji-robi', 'Robi');

        foreach ($tabel as $tb) {
            $this->assertSame($sebelum[$tb], DB::table($tb)->count(), "Cacah baris {$tb} berubah!");
        }
    }
}
