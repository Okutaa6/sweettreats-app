<?php

namespace Tests\Feature;

use App\Models\ItForm;
use App\Models\ItGitRef;
use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use App\Services\ItTracker\PhaseEngine;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * IT Request Tracker FASE 2a — uji terima §14.6 no. 14-16 + dua kendali negatif.
 *
 * Kelas TERPISAH dari ItTrackerTest: phpunit dev tumbang di >±17 uji ber-DB per
 * proses, jadi jangan digabung.
 *
 * DatabaseTransactions (DB dipakai bersama), dan `SHOW TABLES LIKE` — bukan
 * Schema::hasTable() yang membunuh mysqld lokal.
 */
class ItTracker2aTest extends TestCase
{
    use DatabaseTransactions;

    private ItTrackerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);

        if (! DB::select("SHOW TABLES LIKE 'it_git_ref'")) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_03_it_tracker_git_ref.sql dulu.');
        }
    }

    private function sesi(string $groupName, ?string $uid = null): array
    {
        return [
            'isLoggedIn' => true,
            'id'         => $uid ?: ('uji-' . Str::random(8)),
            'group_id'   => DB::table('group_path')->where('nama', $groupName)->value('id') ?: 'grp-uji',
            'group_name' => $groupName,
            'name'       => 'Uji ' . $groupName,
            'branch_day' => date('Y-m-d'),
            'expires_in' => time() + 3600,
            'login_at'   => time(),
            'pa_touch'   => time(),
        ];
    }

    private function tiket(array $o = [], string $uid = 'uji-pemohon'): ItTicket
    {
        return $this->svc->buat(array_merge([
            'judul'             => 'Uji 2a ' . Str::random(6),
            'sumber'            => 'permintaan',
            'jenis_permintaan'  => 'Fitur baru',
            'prioritas_pemohon' => 'Sedang',
            'latar'             => 'uji latar',
            'kebutuhan'         => 'uji kebutuhan',
            'terdampak'         => 'Marketing',
        ], $o), $uid);
    }

    /** Bawa tiket M sampai duduk di GOLIVE (jalur formulir 2b sengaja dilewati). */
    private function tiketDiGoLive(string $uid = 'uji-pemohon'): ItTicket
    {
        $t = $this->tiket([], $uid);
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit');
        $this->svc->pindahFase($t = $t->fresh(), 'GOLIVE', 'uji-headit');

        return $t->fresh();
    }

    // ─── §14.6 no. 14 — it:sync-git idempoten ──────────────────────────────

    public function test_sync_git_idempoten_dan_hanya_membaca_subject(): void
    {
        $repo   = sys_get_temp_dir() . '/itk-git-' . Str::random(6);
        $nomor  = 'IT-2099-777';   // nomor karangan, takkan bentrok data nyata
        $nomor2 = 'IT-2099-778';

        $git = function (array $args) use ($repo) {
            $p = new Process(array_merge(['git'], $args), $repo);
            $p->run();
            return $p;
        };

        mkdir($repo);
        (new Process(['git', 'init', '-q', $repo]))->run();
        $git(['config', 'user.email', 'uji@dev.local']);
        $git(['config', 'user.name', 'Uji']);

        file_put_contents($repo . '/a.txt', 'satu');
        $git(['add', '-A']);
        $git(['commit', '-q', '-m', "$nomor: tambah berkas + 2026_09_03_uji.sql"]);

        // Commit yang menyebut DUA tiket sekaligus — keduanya harus tercatat.
        file_put_contents($repo . '/b.txt', 'dua');
        $git(['add', '-A']);
        $git(['commit', '-q', '-m', "$nomor + $nomor2: satu deploy dua tiket"]);

        // Commit yang menyebut nomor HANYA di body — sengaja TIDAK dicatat
        // (kalau tidak, prosa yang menyebut nomor jadi jejak palsu).
        file_put_contents($repo . '/c.txt', 'tiga');
        $git(['add', '-A']);
        $git(['commit', '-q', '-m', "refactor tanpa nomor\n\nRef: $nomor"]);

        try {
            $kode = Artisan::call('it:sync-git', ['--repo' => $repo]);
            $this->assertSame(0, $kode, Artisan::output());

            $sesudahSekali = ItGitRef::whereIn('ticket_nomor', [$nomor, $nomor2])->count();
            $this->assertSame(3, $sesudahSekali,
                '2 commit ber-subject → 3 jejak (satu commit menyebut dua tiket).');

            // #14: dijalankan 2x tak menggandakan
            Artisan::call('it:sync-git', ['--repo' => $repo]);
            $this->assertSame($sesudahSekali,
                ItGitRef::whereIn('ticket_nomor', [$nomor, $nomor2])->count(),
                'it:sync-git harus idempoten.');

            // body-only tidak ikut, dan selisihnya DIBERITAHU (bukan senyap)
            $this->assertStringContainsString('hanya di BODY', Artisan::output());

            // Dipilih lewat ISI, bukan urutan: dua commit uji dibuat dalam detik
            // yang sama, jadi orderBy(commit_at) goyah.
            $r = ItGitRef::where('ticket_nomor', $nomor)
                ->where('subject', 'like', '%2026_09_03_uji.sql%')->first();
            $this->assertNotNull($r, 'Commit ber-script harus tercatat.');
            $this->assertSame(40, strlen($r->commit_sha));
            $this->assertStringContainsString('2026_09_03_uji.sql', $r->subject);
            $this->assertContains('2026_09_03_uji.sql', $r->scriptDisebut());
        } finally {
            ItGitRef::whereIn('ticket_nomor', [$nomor, $nomor2])->delete();
            (new Process(['rm', '-rf', $repo]))->run();
        }
    }

    /**
     * Tombol "segarkan" di layar — RUTE-nya, bukan cuma perintahnya.
     * Ini yang kelewat sekali: uji perintah lulus sementara rutenya 500 karena
     * satu impor hilang. Perintah yang benar tak menolong kalau tombolnya mati.
     */
    public function test_tombol_segarkan_git_jalan_dan_dipagari(): void
    {
        $t = $this->tiket();

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post('/it-tracker/tiket/' . $t->id . '/sync-git')
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        // Bukan orang IT → ditolak di controller
        $this->withSession($this->sesi('Executive'))
            ->post('/it-tracker/tiket/' . $t->id . '/sync-git')
            ->assertStatus(403);
    }

    public function test_sync_git_menolak_nomor_ngawur(): void
    {
        $this->assertSame(1, Artisan::call('it:sync-git', ['--ticket' => 'bukan-nomor; rm -rf /']));
        $this->assertStringContainsString('tidak berbentuk', Artisan::output());
    }

    // ─── §14.6 no. 15 — FRM-IT-03 tampil SUDAH TERISI ──────────────────────

    public function test_frm03_tampil_terisi_dari_git_lalu_dikonfirmasi(): void
    {
        $t = $this->tiket();
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit');
        $this->svc->pindahFase($t = $t->fresh(), 'DEV', 'uji-headit');
        $t->pic_user_id = 'uji-headit';
        $t->save();

        ItGitRef::create([
            'ticket_nomor' => $t->nomor,
            'branch'       => 'feat/uji-2a',
            'commit_sha'   => str_repeat('a', 40),
            'subject'      => $t->nomor . ': bikin layar + 2026_09_03_uji.sql',
            'commit_at'    => now(),
        ]);

        // Layar menampilkan branch/commit TANPA diketik siapa pun
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get('/it-tracker/tiket/' . $t->id)
            ->assertStatus(200)
            ->assertSee('feat/uji-2a')
            ->assertSee('aaaaaaa')                      // 7 karakter sha
            ->assertSee('2026_09_03_uji.sql')
            ->assertSee('Terisi sendiri dari git');

        // Tombol konfirmasi mengisi dikonfirmasi_at
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post('/it-tracker/tiket/' . $t->id . '/konfirmasi', ['catatan' => ''])
            ->assertStatus(302);

        $this->assertNotNull(
            ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-03')->value('dikonfirmasi_at')
        );
    }

    // ─── §14.6 no. 16 — ekspor FRM-IT-01 ───────────────────────────────────

    public function test_ekspor_frm01_memuat_identitas_terisi(): void
    {
        $uid = DB::table('users')->where('status', 1)->value('id');
        $u   = DB::table('users')->where('id', $uid)->first();

        $t = $this->tiket([], $uid);

        $res = $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get('/it-tracker/tiket/' . $t->id . '/ekspor')
            ->assertStatus(200)
            ->assertSee('FRM-IT-01 — REQUIREMENT REQUEST')
            ->assertSee('A. IDENTITAS PEMOHON')
            ->assertSee('Departemen / Jabatan')
            ->assertSee('Tanggal Pengajuan')
            ->assertSee('B. DETAIL KEBUTUHAN')
            ->assertSee('C. REVIEW AWAL (DIISI IT)');

        // Identitas TERISI walau layar pengisian tak pernah menanyakannya
        $res->assertSee(e($u->nama), false);
        $res->assertSee(e($u->email), false);
    }

    public function test_ekspor_menolak_tiket_tanpa_frm01(): void
    {
        $t = $this->svc->buat([
            'judul' => 'Inisiatif 2a ' . Str::random(4), 'sumber' => 'inisiatif',
            'jenjang' => 'S', 'bobot' => 1, 'prioritas_it' => 'P3',
        ], 'uji-headit');

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get('/it-tracker/tiket/' . $t->id . '/ekspor')
            ->assertStatus(302);   // dipantulkan dengan penjelasan, bukan 500
    }

    // ─── FRM-IT-06 Checklist Go Live ───────────────────────────────────────

    public function test_baris_otomatis_tak_bisa_diisi_tangan(): void
    {
        $t = $this->tiketDiGoLive();
        $t->pic_user_id = 'uji-headit';
        $t->save();

        foreach (['uat', 'script'] as $k) {
            try {
                $this->svc->setPra($t, $k, 'ya', 'uji-headit');
                $this->fail("Baris otomatis '$k' seharusnya ditolak.");
            } catch (DomainException $e) {
                $this->assertStringContainsString('menyala sendiri', $e->getMessage());
            }
        }

        // …dan tak pernah tersimpan sebagai salinan yang bisa basi
        $this->svc->setPra($t, 'backup', 'ya', 'uji-headit');
        $p = ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-06')->value('payload'); // model meng-cast ke array
        $this->assertArrayNotHasKey('uat', $p['pra']);
        $this->assertArrayNotHasKey('script', $p['pra']);
        $this->assertSame('ya', $p['pra']['backup']);
    }

    public function test_uat_dan_script_menyala_sendiri_dari_data(): void
    {
        $t = $this->tiketDiGoLive();

        $v = $this->svc->viewTicket($this->svc->muatRelasi(ItTicket::query())->find($t->id));
        $this->assertArrayNotHasKey('uat', $v['fr06']['pra'], 'Belum ada keputusan UAT → belum menyala.');
        $this->assertArrayNotHasKey('script', $v['fr06']['pra'], 'Belum ada jejak git → belum menyala.');

        // Sumbernya data, bukan klik: keputusan UAT + jejak git
        ItForm::create(['ticket_id' => $t->id, 'kode' => 'FRM-IT-05',
            'payload' => ['keputusan' => 'setuju']]);
        ItGitRef::create(['ticket_nomor' => $t->nomor, 'branch' => 'feat/x',
            'commit_sha' => str_repeat('b', 40), 'subject' => $t->nomor . ': kerja', 'commit_at' => now()]);

        $v = $this->svc->viewTicket($this->svc->muatRelasi(ItTicket::query())->find($t->id));
        $this->assertSame('ya', $v['fr06']['pra']['uat']);
        $this->assertSame('ya', $v['fr06']['pra']['script']);
    }

    public function test_acc_business_owner_bukan_milik_it(): void
    {
        $t = $this->tiketDiGoLive();

        // Head IT ditolak — paraf yang bisa dibubuhkan pelaksana bukan kendali
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post('/it-tracker/tiket/' . $t->id . '/bo')
            ->assertStatus(403);

        // Executive boleh
        $this->withSession($this->sesi('Executive'))
            ->post('/it-tracker/tiket/' . $t->id . '/bo')
            ->assertStatus(302);

        $p = ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-06')->value('payload');
        $this->assertTrue($p['bo']);
    }

    public function test_gerbang_golive_terbuka_saat_checklist_lengkap(): void
    {
        $t = $this->tiketDiGoLive();
        $t->pic_user_id = 'uji-headit';
        $t->save();

        $muat = fn () => $this->svc->viewTicket($this->svc->muatRelasi(ItTicket::query())->find($t->id));

        $g = PhaseEngine::gateInfo($muat());
        $this->assertFalse($g['ok']);
        $this->assertStringContainsString('pra-deploy', implode(' ', $g['missing']));

        // dua baris otomatis: dari data
        ItForm::create(['ticket_id' => $t->id, 'kode' => 'FRM-IT-05', 'payload' => ['keputusan' => 'setuju']]);
        ItGitRef::create(['ticket_nomor' => $t->nomor, 'branch' => 'feat/x',
            'commit_sha' => str_repeat('c', 40), 'subject' => $t->nomor . ': kerja', 'commit_at' => now()]);

        // sisanya dijawab petugas deploy
        foreach (['backup', 'akses', 'rollback'] as $k) $this->svc->setPra($t, $k, 'ya', 'uji-headit');
        $this->svc->setPra($t, 'panduan', 'na', 'uji-headit');
        foreach (array_column(PhaseEngine::PASCA, 'k') as $k) $this->svc->setPasca($t, $k, 'uji-headit');

        $g = PhaseEngine::gateInfo($muat());
        $this->assertFalse($g['ok'], 'Masih kurang ACC Business Owner.');
        $this->assertSame(['persetujuan Business Owner'], $g['missing']);

        $this->svc->setujuiBusinessOwner($t->fresh(), 'uji-robi');

        $g = PhaseEngine::gateInfo($muat());
        $this->assertTrue($g['ok'], 'Checklist lengkap → gerbang terbuka.');
        $this->assertSame('SELESAI', $g['next']);   // jenjang M
    }

    public function test_checklist_hanya_di_fase_golive(): void
    {
        $t = $this->tiket();
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit');

        try {
            $this->svc->setPra($t->fresh(), 'backup', 'ya', 'uji-headit');
            $this->fail('Checklist di luar GOLIVE harus ditolak.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Go Live', $e->getMessage());
        }
    }

    // ─── Regresi hasil audit ulang (3 Sep sore) ────────────────────────────

    /**
     * INSIDEN yang latarnya diisi MELAHIRKAN FRM-IT-01 padahal pemohonnya
     * NULL — dan ekspornya dulu 500 (`optional($p->group)`: argumen fungsi
     * dievaluasi lebih dulu, tak seperti `??`). Dokumennya harus terbentuk dan
     * berkata jujur bahwa tiket ini tak punya pemohon.
     */
    public function test_ekspor_tiket_tanpa_pemohon_tidak_500(): void
    {
        $t = $this->svc->buat([
            'judul' => 'Uji insiden berlatar ' . Str::random(4), 'sumber' => 'insiden',
            'jenjang' => 'S', 'bobot' => 1, 'prioritas_it' => 'P1',
            'jenis_permintaan' => 'Lainnya', 'latar' => 'prod mati 6 menit', 'kebutuhan' => 'pulih',
        ], 'uji-headit');

        $this->assertNull($t->pemohon_user_id);
        $this->assertTrue(ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-01')->exists(),
            'Insiden berlatar memang melahirkan FRM-IT-01 — itu yang membuat jalur ini terjangkau.');

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get('/it-tracker/tiket/' . $t->id . '/ekspor')
            ->assertStatus(200)
            ->assertSee('lahir dari IT sendiri')
            ->assertSee('A. IDENTITAS PEMOHON');
    }

    /** Checklist Go-Live tetap terbaca sesudah tiket ditutup, tapi BACA-SAJA. */
    public function test_checklist_tetap_terbaca_sesudah_ditutup(): void
    {
        $t = $this->tiketDiGoLive();
        $t->pic_user_id = 'uji-headit';
        $t->save();

        $this->svc->setPra($t, 'backup', 'ya', 'uji-headit');
        $this->svc->setPasca($t, 'akses', 'uji-headit');
        $this->svc->pindahFase($t->fresh(), 'SELESAI', 'uji-headit');

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get('/it-tracker/tiket/' . $t->id)
            ->assertStatus(200)
            ->assertSee('FRM-IT-06 — GO LIVE CHECKLIST')
            ->assertSee('baca-saja')
            ->assertDontSee('Setujui sebagai Business Owner');

        // …dan layanan menolak menulis di luar fase Go Live
        try {
            $this->svc->setPra($t->fresh(), 'akses', 'ya', 'uji-headit');
            $this->fail('Menulis checklist sesudah tutup harus ditolak.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Go Live', $e->getMessage());
        }
    }

    /**
     * Daftar baris otomatis DITURUNKAN dari PhaseEngine::PRA, bukan disalin.
     * Salinan tangan akan menganga diam-diam: layar menandai "otomatis"
     * sementara layanan tetap mengizinkannya diketik.
     */
    public function test_daftar_baris_otomatis_ikut_phase_engine(): void
    {
        $dariMesin = array_column(
            array_filter(PhaseEngine::PRA, fn ($p) => ! empty($p['auto'])), 'k');

        $this->assertSame($dariMesin, ItTrackerService::praOtomatis());
        $this->assertNotEmpty($dariMesin);
    }

    /**
     * ⛔ Kemacetan permanen yang hampir lolos: "File/script siap" menyala dari
     * jejak git dan tak boleh diisi tangan — jadi tiket TANPA commit ber-nomor
     * (perubahan konfigurasi murni) tak akan pernah bisa lepas dari Go Live.
     * N-A diizinkan HANYA saat sistem memang tak punya apa pun; "Ya" tetap
     * haram, dan begitu jejak git muncul, bukti mengalahkan pengecualian.
     */
    public function test_script_tanpa_commit_tak_boleh_macet_permanen(): void
    {
        $t = $this->tiketDiGoLive();
        $t->pic_user_id = 'uji-headit';
        $t->save();

        // Mengaku punya bukti yang tak ada: DITOLAK
        try {
            $this->svc->setPra($t, 'script', 'ya', 'uji-headit');
            $this->fail('"Ya" pada baris otomatis harus ditolak.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('menyala sendiri', $e->getMessage());
        }

        // Tak ada jejak git sama sekali → N-A boleh, dan TERSIMPAN
        $this->svc->setPra($t, 'script', 'na', 'uji-headit');
        $p = ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-06')->value('payload');
        $this->assertSame('na', $p['pra']['script'], 'Jawaban manusia tak boleh ikut dibuang.');

        $v = $this->svc->viewTicket($this->svc->muatRelasi(ItTicket::query())->find($t->id));
        $this->assertSame('na', $v['fr06']['pra']['script']);

        // Begitu jejak git muncul, BUKTI mengalahkan pengecualian
        ItGitRef::create(['ticket_nomor' => $t->nomor, 'branch' => 'feat/x',
            'commit_sha' => str_repeat('d', 40), 'subject' => $t->nomor . ': kerja', 'commit_at' => now()]);

        $v = $this->svc->viewTicket($this->svc->muatRelasi(ItTicket::query())->find($t->id));
        $this->assertSame('ya', $v['fr06']['pra']['script']);

        // …dan sejak itu N-A tak lagi diterima
        try {
            $this->svc->setPra($t->fresh(), 'script', 'na', 'uji-headit');
            $this->fail('N-A harus ditolak saat jejak git sudah ada.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('sudah ada', $e->getMessage());
        }
    }

    /**
     * Byte non-UTF-8 pernah menghasilkan 500 telanjang ("Unable to encode
     * attribute [payload] … Malformed UTF-8"), karena payload di-cast JSON dan
     * json_encode menolaknya. Browser selalu mengirim UTF-8, tapi tempelan
     * lawas / klien non-browser tidak. Kegagalan tak boleh berbentuk halaman
     * error — dan teks yang SAH harus lewat utuh, termasuk titik-tengah.
     */
    public function test_teks_rusak_tak_meledakkan_payload(): void
    {
        $rusak = "02:11 backup \xb7 02:24 curl 200";           // 0xB7 telanjang
        $sah   = "02:11 backup \xc2\xb7 02:24 curl beranda 200"; // · UTF-8 sah

        $this->assertFalse(mb_check_encoding($rusak, 'UTF-8'));
        $this->assertTrue(mb_check_encoding(ItTrackerService::teksBersih($rusak), 'UTF-8'));
        $this->assertSame($sah, ItTrackerService::teksBersih($sah), 'Teks sah harus lewat UTUH.');

        $t = $this->tiketDiGoLive();
        $t->pic_user_id = 'uji-headit';
        $t->save();

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post('/it-tracker/tiket/' . $t->id . '/deploy', ['catatan' => $rusak])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $p = ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-06')->value('payload');
        $this->assertTrue(mb_check_encoding($p['deploy'], 'UTF-8'));
        $this->assertStringContainsString('backup', $p['deploy']);
    }

    // ─── Kendali negatif ───────────────────────────────────────────────────

    public function test_tabel_lama_tak_tersentuh_dan_dev_tetap_dingin(): void
    {
        $tabel = ['users', 'att_day', 'rop_requests', 'omnicare_commission', 'group_access', 'access_path'];
        $sebelum = [];
        foreach ($tabel as $tb) $sebelum[$tb] = DB::table($tb)->count();

        $t = $this->tiketDiGoLive();
        $t->pic_user_id = 'uji-headit';
        $t->save();
        $this->svc->setPra($t, 'backup', 'ya', 'uji-headit');
        $this->svc->setPasca($t, 'akses', 'uji-headit');
        Artisan::call('it:sync-git', ['--ticket' => 'IT-2099-999']);

        foreach ($tabel as $tb) {
            $this->assertSame($sebelum[$tb], DB::table($tb)->count(), "Cacah baris {$tb} berubah!");
        }

        // Kendali negatif kedua: 30 hari di DEV tetap heat 0
        $dev = $this->tiket();
        $this->svc->triase($dev, 'M', 3, 'P1', 'uji-headit');
        $this->svc->pindahFase($dev = $dev->fresh(), 'DEV', 'uji-headit');
        DB::table('it_ticket')->where('id', $dev->id)->update(['fase_since' => now()->subDays(30)]);

        $w = PhaseEngine::waitOf($this->svc->viewTicket($this->svc->muatRelasi(ItTicket::query())->find($dev->id)));
        $this->assertSame(30, $w['days']);
        $this->assertSame(0, $w['heat'], 'Yang menunggu IT TIDAK memanas.');
    }
}
