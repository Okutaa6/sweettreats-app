<?php

namespace Tests\Feature;

use App\Models\ItFr;
use App\Models\ItTicket;
use App\Models\ItTicketFase;
use App\Services\ItTracker\ItTrackerService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * IT Request Tracker — uji terima rencana §14.6 yang butuh DB/HTTP:
 * no. 2 (ubah jenjang mengubah jalur), 6 (riwayat fase tepat satu baris),
 * 11-13 (akses), 17 (kendali negatif: tabel lama tak tersentuh).
 *
 * DatabaseTransactions, bukan RefreshDatabase — DB dev/lokal dipakai bersama.
 * ⚠️ Status 200 tak membuktikan apa-apa: RBAC MEMANTULKAN (302), bukan 403 —
 * uji akses memeriksa URL tujuan/isi halaman, bukan sekadar kode HTTP.
 */
class ItTrackerTest extends TestCase
{
    use DatabaseTransactions;

    private ItTrackerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);

        // ⚠️ JANGAN pakai Schema::hasTable() di sini: di MariaDB lokal, kueri
        // information_schema ber-data_length yang dipakainya MEMBUNUH mysqld
        // (tablespace lama korup; terbukti 2 Sep). SHOW TABLES LIKE aman.
        if (! DB::select("SHOW TABLES LIKE 'it_ticket'")) {
            $this->markTestSkipped('Tabel it_ticket belum ada — jalankan database/sql/2026_09_02_it_tracker_core.sql dulu.');
        }
    }

    private function sesi(string $groupName, ?string $uid = null): array
    {
        $gid = DB::table('group_path')->where('nama', $groupName)->value('id') ?: 'grp-uji';

        return [
            'isLoggedIn' => true,
            'id'         => $uid ?: ('uji-' . Str::random(8)),
            'group_id'   => $gid,
            'group_name' => $groupName,
            'name'       => 'Uji ' . $groupName,
            'branch_day' => date('Y-m-d'),
            'expires_in' => time() + 3600,
            'login_at'   => time(),
            'pa_touch'   => time(),
        ];
    }

    private function tiketBaru(array $override = [], string $uid = 'uji-pemohon'): ItTicket
    {
        return $this->svc->buat(array_merge([
            'judul'             => 'Uji tracker ' . Str::random(6),
            'sumber'            => 'permintaan',
            'jenis_permintaan'  => 'Fitur baru',
            'prioritas_pemohon' => 'Sedang',
            'latar'             => 'uji',
            'kebutuhan'         => 'uji',
        ], $override), $uid);
    }

    // ─── mesin + riwayat ───────────────────────────────────────────────────

    public function test_nomor_tiket_berurut_dan_format_benar(): void
    {
        $a = $this->tiketBaru();
        $b = $this->tiketBaru();

        $this->assertMatchesRegularExpression('/^IT-\d{4}-\d{3}$/', $a->nomor);
        $this->assertSame(
            (int) substr($a->nomor, -3) + 1,
            (int) substr($b->nomor, -3),
            'Nomor tiket harus berurut (counter atomik, bukan MAX+1).'
        );
    }

    /** §14.6 no. 6 — tiap perpindahan: TEPAT satu baris baru, baris lama tertutup. */
    public function test_riwayat_fase_tepat_satu_baris_tiap_pindah(): void
    {
        $t = $this->tiketBaru();

        $this->assertSame('TRIASE', $t->fase);
        $this->assertSame(1, ItTicketFase::where('ticket_id', $t->id)->count());
        $this->assertSame('it', ItTicketFase::where('ticket_id', $t->id)->value('pihak_menunggu'));

        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit');

        $rows = ItTicketFase::where('ticket_id', $t->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertNotNull($rows[0]->keluar_at, 'Baris TRIASE wajib tertutup saat pindah.');
        $this->assertNull($rows[1]->keluar_at);
        $this->assertSame('ANTRE', $rows[1]->fase);
    }

    /** §14.6 no. 2 — jenjang menentukan jalur; ANTRE menarik ke fase kedua jalurnya. */
    public function test_jenjang_mengubah_jalur(): void
    {
        $s = $this->tiketBaru();
        $this->svc->triase($s, 'S', 1, 'P2', 'uji-headit');
        $this->assertSame('DEV', $this->svc->majukan($s->fresh(), 'uji-headit'), 'Jalur S: ANTRE → DEV.');

        $m = $this->tiketBaru();
        $this->svc->triase($m, 'M', 3, 'P1', 'uji-headit');
        $this->assertSame('ANALISIS', $this->svc->majukan($m->fresh(), 'uji-headit'), 'Jalur M: ANTRE → ANALISIS.');
    }

    /** §14.6 no. 3 — gerbang ANALISIS→DEV di DB: ditolak + pesan menyebut yang kurang. */
    public function test_gerbang_analisis_di_db(): void
    {
        $t = $this->tiketBaru();
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit');
        $this->svc->majukan($t = $t->fresh(), 'uji-headit'); // → ANALISIS

        try {
            $this->svc->majukan($t->fresh()->load('forms', 'frs', 'testCases', 'temuan', 'anak'), 'uji-headit');
            $this->fail('Gerbang ANALISIS→DEV harus menolak tanpa Kriteria Diterima.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Kriteria Diterima', $e->getMessage());
            $this->assertStringContainsString('ruang lingkup', $e->getMessage());
        }

        // Lengkapi: 1 FR berkriteria + lingkup + komponen → gerbang terbuka.
        ItFr::create(['ticket_id' => $t->id, 'kode' => 'FR-01', 'kebutuhan' => 'uji',
            'kriteria_diterima' => 'Terlihat di layar X.', 'urutan' => 1]);
        \App\Models\ItForm::create(['ticket_id' => $t->id, 'kode' => 'FRM-IT-02',
            'payload' => ['lingkup' => 'Termasuk A.', 'komponen' => ['Aplikasi']]]);

        $this->assertSame('DEV', $this->svc->majukan($t->fresh(), 'uji-headit'));
    }

    /** UAT hanya diputuskan pemohon; setuju → GOLIVE, perbaikan → DEV. */
    public function test_uat_putusan_pemohon(): void
    {
        $t = $this->tiketBaru();
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit');
        // paksa ke UAT lewat jalur riwayat resmi (data formulir Fase 2 belum ada)
        $this->svc->pindahFase($t = $t->fresh(), 'UAT', 'uji-headit');

        $this->assertSame('GOLIVE', $this->svc->uatPutuskan($t->fresh(), 'setuju', 'uji-pemohon'));

        $t2 = $this->tiketBaru();
        $this->svc->triase($t2, 'M', 3, 'P1', 'uji-headit');
        $this->svc->pindahFase($t2 = $t2->fresh(), 'UAT', 'uji-headit');
        $this->assertSame('DEV', $this->svc->uatPutuskan($t2->fresh(), 'perbaikan', 'uji-pemohon'));
    }

    public function test_parkir_wajib_alasan_dan_buka_lagi(): void
    {
        $t = $this->tiketBaru();

        try {
            $this->svc->parkir($t, 'DITUNDA', '  ', 'uji-headit');
            $this->fail('Parkir tanpa alasan harus ditolak.');
        } catch (DomainException) {
        }

        $this->svc->parkir($t, 'DITUNDA', 'nunggu komisi 70%', 'uji-headit');
        $this->assertSame('DITUNDA', $t->fresh()->fase);

        // Belum pernah ditriase → buka lagi kembali ke TRIASE.
        $this->assertSame('TRIASE', $this->svc->bukaLagi($t->fresh(), 'uji-headit'));
    }

    // ─── akses (HTTP) ──────────────────────────────────────────────────────

    /** §14.6 no. 12 — Reza (role IT) mencoba triase → DITOLAK DI CONTROLLER. */
    public function test_role_it_tak_boleh_triase(): void
    {
        $t = $this->tiketBaru();

        $this->withSession($this->sesi('IT'))
            ->post('/it-tracker/tiket/' . $t->id . '/triase',
                ['jenjang' => 'M', 'bobot' => 3, 'prioritas_it' => 'P1'])
            ->assertStatus(403);

        $this->assertSame('TRIASE', $t->fresh()->fase, 'Tiket tak boleh bergeser.');
    }

    /** Reza hanya menggerakkan tiket yang dia PIC — tiket orang lain 403. */
    public function test_role_it_hanya_maju_milik_sendiri(): void
    {
        $t = $this->tiketBaru();
        $this->svc->triase($t, 'S', 1, 'P1', 'uji-headit');
        $this->svc->majukan($t = $t->fresh(), 'uji-headit'); // DEV, PIC = uji-headit

        $this->withSession($this->sesi('IT', 'uji-reza'))
            ->post('/it-tracker/tiket/' . $t->id . '/maju')
            ->assertStatus(403);
    }

    /** §14.6 no. 5 — UAT→GOLIVE tak bisa dibuka IT lewat /maju, dan bukan-pemohon ditolak di /uat. */
    public function test_uat_tak_bisa_dibuka_it(): void
    {
        $t = $this->tiketBaru([], 'uji-pemohon-uat');
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit');
        $this->svc->pindahFase($t = $t->fresh(), 'UAT', 'uji-headit');

        $this->withSession($this->sesi('Head IT'))
            ->post('/it-tracker/tiket/' . $t->id . '/maju')
            ->assertStatus(403);

        $this->withSession($this->sesi('Head IT'))
            ->post('/it-tracker/tiket/' . $t->id . '/uat', ['keputusan' => 'setuju'])
            ->assertStatus(403);

        $this->assertSame('UAT', $t->fresh()->fase);
    }

    /** §14.6 no. 11 — Head melihat ringkasan beban, TIDAK isi tiket orang lain. */
    public function test_head_tak_melihat_tiket_orang_lain(): void
    {
        $t = $this->tiketBaru([], 'uji-orang-lain');

        // /it-tracker untuk head → diarahkan ke halaman permintaannya
        $this->withSession($this->sesi('Head Marketing'))
            ->get('/it-tracker')
            ->assertRedirect(route('it-tracker.permintaan'));

        // halaman permintaan memuat RINGKASAN beban, tanpa judul tiket orang lain
        $res = $this->withSession($this->sesi('Head Marketing'))
            ->get('/it-tracker/permintaan')
            ->assertStatus(200)
            ->assertSee('ANTREAN IT SAAT INI')
            ->assertDontSee(e($t->judul), false);

        // detail tiket orang lain → 403
        $this->withSession($this->sesi('Head Marketing'))
            ->get('/it-tracker/tiket/' . $t->id)
            ->assertStatus(403);
    }

    /**
     * §14.6 no. 13 — role tanpa grant membuka /it-tracker → TERPENTAL (302 ke
     * landing), bukan 403 dan bukan halaman papan. Periksa URL tujuan.
     */
    public function test_role_tanpa_grant_terpental(): void
    {
        $gid = (string) Str::uuid();
        DB::table('group_path')->insert([
            'id' => $gid, 'nama' => 'Uji Tanpa Grant ' . Str::random(4),
            'landing_page' => 'akun',
        ]);

        $sesi = $this->sesi('Uji Tanpa Grant');
        $sesi['group_id'] = $gid;

        $res = $this->withSession($sesi)->get('/it-tracker');
        $res->assertStatus(302);
        $this->assertStringNotContainsString('it-tracker', $res->headers->get('Location', ''),
            'Pentalan tak boleh kembali ke it-tracker.');
    }

    /** Papan render untuk Head IT: panel beban + kartu + tombol triase. */
    public function test_papan_render_untuk_head_it(): void
    {
        $t = $this->tiketBaru();

        $this->withSession($this->sesi('Head IT'))
            ->get('/it-tracker')
            ->assertStatus(200)
            ->assertSee('BEBAN IT SAAT INI')
            ->assertSee($t->nomor)
            ->assertSee('Triase — tetapkan jenjang &amp; prioritas', false);
    }

    /** Detail render: prioritas pemohon vs IT dua kolom + gerbang menyebut yang kurang. */
    public function test_detail_render_dengan_gerbang(): void
    {
        $t = $this->tiketBaru();
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit');
        $this->svc->majukan($t = $t->fresh(), 'uji-headit'); // → ANALISIS

        $this->withSession($this->sesi('Head IT'))
            ->get('/it-tracker/tiket/' . $t->id)
            ->assertStatus(200)
            ->assertSee('PRIORITAS PEMOHON')
            ->assertSee('PRIORITAS IT')
            ->assertSee('Kriteria Diterima'); // tombol mati menyebut yang kurang
    }

    // ─── regresi hasil audit 2 Sep ─────────────────────────────────────────

    /** Tiket TANPA pemohon (inisiatif) tak boleh deadlock di UAT — Head IT yang memutuskan. */
    public function test_uat_inisiatif_diputuskan_head_it(): void
    {
        $t = $this->svc->buat([
            'judul' => 'Inisiatif uji ' . Str::random(4), 'sumber' => 'inisiatif',
            'jenjang' => 'M', 'bobot' => 3, 'prioritas_it' => 'P2',
            'jenis_permintaan' => 'Fitur baru', 'latar' => 'uji', 'kebutuhan' => 'uji',
        ], 'uji-headit');
        $this->svc->pindahFase($t, 'UAT', 'uji-headit');

        // Role IT biasa tetap ditolak…
        $this->withSession($this->sesi('IT'))
            ->post('/it-tracker/tiket/' . $t->id . '/uat', ['keputusan' => 'setuju'])
            ->assertStatus(403);

        // …tapi Head IT bisa — tiket tidak macet permanen.
        $this->withSession($this->sesi('Head IT'))
            ->post('/it-tracker/tiket/' . $t->id . '/uat', ['keputusan' => 'setuju'])
            ->assertStatus(302);

        $this->assertSame('GOLIVE', $t->fresh()->fase);
    }

    /** Konfirmasi FRM-IT-03 membuka gerbang DEV→UAT jenjang M (dulu buntu selamanya). */
    public function test_konfirmasi_dev_membuka_gerbang_m(): void
    {
        $t = $this->tiketBaru();
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit');
        $this->svc->pindahFase($t = $t->fresh(), 'DEV', 'uji-headit');
        $t->pic_user_id = 'uji-headit';
        $t->save();

        try {
            $this->svc->majukan($t->fresh(), 'uji-headit');
            $this->fail('Gerbang DEV harus tertutup sebelum konfirmasi.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('FRM-IT-03', $e->getMessage());
        }

        // panel konfirmasinya benar-benar tampil di layar detail
        $this->withSession($this->sesi('Head IT'))
            ->get('/it-tracker/tiket/' . $t->id)
            ->assertStatus(200)
            ->assertSee('FRM-IT-03 — DEVELOPMENT RECORD')
            ->assertSee('Benar, ini yang kukerjakan');

        $this->svc->konfirmasiDev($t->fresh(), 'kerjaan selesai, commit abc123', 'uji-headit');
        $this->assertSame('UAT', $this->svc->majukan($t->fresh(), 'uji-headit'));
    }

    /** Parkir hanya untuk tiket aktif — DITOLAK/SELESAI tak bisa diubah jadi DITUNDA. */
    public function test_parkir_hanya_tiket_aktif(): void
    {
        $t = $this->tiketBaru();
        $this->svc->parkir($t, 'DITOLAK', 'sudah ada fiturnya', 'uji-headit');

        try {
            $this->svc->parkir($t->fresh(), 'DITUNDA', 'coba hidupkan lagi', 'uji-headit');
            $this->fail('Tiket DITOLAK tidak boleh diparkir ulang.');
        } catch (DomainException) {
        }

        $this->assertSame('DITOLAK', $t->fresh()->fase);
        $this->assertSame('sudah ada fiturnya', $t->fresh()->alasan_parkir,
            'Alasan penolakan tak boleh tertimpa.');
    }

    /** Insiden: formulir menyusul — cukup judul + jenjang, langsung ke DEV. */
    public function test_insiden_masuk_cepat_tanpa_prosa(): void
    {
        $this->withSession($this->sesi('Head IT'))
            ->post('/it-tracker/tiket', [
                'sumber' => 'insiden', 'judul' => 'Insiden uji ' . Str::random(4),
                'jenjang' => 'S', 'bobot' => 2, 'prioritas_it' => 'P1',
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $t = ItTicket::where('judul', 'like', 'Insiden uji %')->latest('id')->first();
        $this->assertSame('DEV', $t->fase);
        $this->assertSame('insiden', $t->sumber);
    }

    /** ANTRE tanpa jenjang tak boleh ditarik — pathFor(null) jatuh ke jalur L. */
    public function test_antre_tanpa_jenjang_tak_bisa_maju(): void
    {
        $t = $this->tiketBaru();
        // paksa keadaan janggal (hanya terjangkau lewat DB langsung)
        $this->svc->pindahFase($t, 'ANTRE', 'uji-headit');

        try {
            $this->svc->majukan($t->fresh(), 'uji-headit');
            $this->fail('ANTRE tanpa jenjang harus ditolak.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('jenjang', $e->getMessage());
        }
    }

    // ─── kendali negatif ───────────────────────────────────────────────────

    /** §14.6 no. 17 — fitur ini tidak mengubah cacah baris tabel lama mana pun. */
    public function test_tabel_lama_tak_tersentuh(): void
    {
        $tabel = ['users', 'att_day', 'rop_requests', 'omnicare_commission', 'group_access', 'access_path'];
        $sebelum = [];
        foreach ($tabel as $tb) {
            $sebelum[$tb] = DB::table($tb)->count();
        }

        $t = $this->tiketBaru();
        $this->svc->triase($t, 'S', 2, 'P1', 'uji-headit');
        $this->svc->majukan($t = $t->fresh(), 'uji-headit');
        $this->svc->catatanDev($t, 'uji kendali negatif', 'uji-headit');
        $this->svc->majukan($t->fresh(), 'uji-headit'); // S: DEV → SELESAI

        foreach ($tabel as $tb) {
            $this->assertSame($sebelum[$tb], DB::table($tb)->count(),
                "Cacah baris {$tb} berubah — fitur baru menyentuh tabel lama!");
        }
    }
}
