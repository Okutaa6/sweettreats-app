<?php

namespace Tests\Feature;

use App\Models\ItForm;
use App\Models\ItTicket;
use App\Models\ItTicketFase;
use App\Services\ItTracker\ItTrackerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Triase dibuka untuk Executive & Konsultan IT — keputusan Ekky 9 Sep 2026.
 *
 * Bukan dengan menaikkan mereka ke head_it (itu mencabut hak paraf Business
 * Owner mereka), melainkan lewat satu predikat sempit: bolehTriase(). Semua
 * yang lain — pelewatan paraf, PIC, tunda/tolak — tetap Head IT.
 *
 * Akun SUNGGUHAN per grup (AuthGuard membaca group_id dari sesi dan mencocokkan
 * grant di DB). Grup yang belum punya akun aktif di lingkungan ini diberi satu
 * akun sementara di dalam transaksi uji — grupnya tetap grup sungguhan.
 */
class ItTrackerTriaseExecutiveTest extends TestCase
{
    use DatabaseTransactions;

    private ItTrackerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);

        foreach (['Executive', 'Konsultan IT', 'Konsultan Finance', 'Konsultan Marketing', 'Admin General', 'Admin Backup', 'Head IT'] as $g) {
            if (! DB::table('group_path')->where('nama', $g)->exists()) {
                $this->markTestSkipped("Grup '$g' tidak ada di lingkungan ini (jalankan SQL 2026_08_21_konsultan_struktur).");
            }
        }
        if (! DB::table('group_access as ga')->join('access_path as ap', 'ap.id', '=', 'ga.access_id')
            ->join('group_path as gp', 'gp.id', '=', 'ga.group_id')
            ->where('ap.link', 'it-tracker')->where('gp.nama', 'Konsultan IT')->exists()) {
            $this->markTestSkipped('Konsultan IT belum ber-grant it-tracker (jalankan SQL 2026_09_02_it_tracker_access).');
        }
    }

    // ───────────────────────── pembantu ─────────────────────────

    /** Akun aktif sungguhan di grup itu; kalau nihil, satu akun sementara DI GRUP SUNGGUHAN (ikut rollback). */
    private function akun(string $grup): object
    {
        $gid = DB::table('group_path')->where('nama', $grup)->value('id');
        $u   = DB::table('users')->where('group_id', $gid)->where('status', 1)->first(['id', 'nama']);
        if ($u) return (object) ['id' => $u->id, 'nama' => $u->nama, 'gid' => $gid, 'grup' => $grup];

        $contoh = (array) DB::table('users')->where('status', 1)->whereNotNull('group_id')->first();
        $contoh['id']       = (string) Str::uuid();
        $contoh['nama']     = 'Uji ' . $grup;
        $contoh['email']    = 'uji-' . Str::lower(Str::random(8)) . '@uji.local';
        $contoh['group_id'] = $gid;
        foreach (['created_at', 'updated_at'] as $k) if (array_key_exists($k, $contoh)) $contoh[$k] = now();
        DB::table('users')->insert($contoh);

        return (object) ['id' => $contoh['id'], 'nama' => $contoh['nama'], 'gid' => $gid, 'grup' => $grup];
    }

    private function sesi(object $a): array
    {
        return [
            'isLoggedIn' => true, 'id' => $a->id, 'group_id' => $a->gid, 'group_name' => $a->grup, 'name' => $a->nama,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time(),
        ];
    }

    private function tiketTriase(): ItTicket
    {
        return $this->svc->buat([
            'judul' => 'Uji triase ' . Str::random(6), 'sumber' => 'permintaan', 'jenis_permintaan' => 'Fitur baru',
            'prioritas_pemohon' => 'Sedang', 'latar' => 'uji', 'kebutuhan' => 'uji', 'terdampak' => 'Marketing',
        ], 'uji-pemohon-triase');
    }

    /** tiket M di ANALISIS dengan analisis lengkap — hanya paraf BO yang kurang (pola ItTrackerTeruskanTest). */
    private function siapParaf(): ItTicket
    {
        $t = $this->tiketTriase();
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

    private function isiTriase(array $tambah = []): array
    {
        return array_merge(['jenjang' => 'M', 'bobot' => 3, 'prioritas_it' => 'P2'], $tambah);
    }

    // ───────────────────────── yang dibuka ─────────────────────────

    public function test_konsultan_it_triase_berhasil_tiket_pindah_ke_antre_dan_tercatat_siapa(): void
    {
        $k = $this->akun('Konsultan IT');
        $t = $this->tiketTriase();

        $r = $this->withSession($this->sesi($k))->post(route('it-tracker.triase', $t->id), $this->isiTriase());
        $r->assertStatus(302);
        $this->assertNull($r->getSession()->get('error'));

        $t = $t->fresh();
        $this->assertSame('ANTRE', $t->fase, 'TRIASE → ANTRE');
        $this->assertSame(['M', 3, 'P2'], [$t->jenjang, (int) $t->bobot, $t->prioritas_it]);
        $this->assertNull($t->pic_user_id, 'Konsultan IT tidak menunjuk PIC.');

        // jejaknya: langkah ANTRE dicatat atas nama pentriase
        $antre = ItTicketFase::where('ticket_id', $t->id)->where('fase', 'ANTRE')->first();
        $this->assertSame($k->id, $antre->oleh_user_id, 'Siapa yang mentriase harus bisa ditelusuri.');

        // …dan tampil di detail tiket, sebagai ELEMEN, bagi siapa pun yang membacanya
        $this->withSession($this->sesi($this->akun('Head IT')))->get(route('it-tracker.show', $t->id))
            ->assertStatus(200)
            ->assertSee('itk-triase-oleh', false)
            ->assertSee(e($k->nama), false);
    }

    public function test_executive_triase_berhasil_termasuk_pilihan_modul(): void
    {
        $e = $this->akun('Executive');
        $t = $this->tiketTriase();
        $modulId = DB::table('it_modul')->value('id');

        $r = $this->withSession($this->sesi($e))
            ->post(route('it-tracker.triase', $t->id), $this->isiTriase(['jenjang' => 'S', 'modul_id' => $modulId]));
        $r->assertStatus(302);
        $this->assertNull($r->getSession()->get('error'));

        $t = $t->fresh();
        $this->assertSame('ANTRE', $t->fase);
        $this->assertSame('S', $t->jenjang);
        if ($modulId) {
            $this->assertSame((int) $modulId, (int) $t->modul_id, 'Pilihan modul ikut terbuka — tiket tanpa modul memutus tautan katalog.');
        }
        $this->assertSame($e->id, ItTicketFase::where('ticket_id', $t->id)->where('fase', 'ANTRE')->value('oleh_user_id'));
    }

    // ───────────────────────── yang tetap tertutup ─────────────────────────

    public function test_konsultan_finance_dan_marketing_ditolak(): void
    {
        foreach (['Konsultan Finance', 'Konsultan Marketing'] as $grup) {
            $t = $this->tiketTriase();
            $this->withSession($this->sesi($this->akun($grup)))
                ->post(route('it-tracker.triase', $t->id), $this->isiTriase())
                ->assertStatus(403);
            $this->assertSame('TRIASE', $t->fresh()->fase, "$grup: tiket tak boleh bergeser.");
        }
    }

    public function test_admin_general_dan_admin_backup_ditolak(): void
    {
        foreach (['Admin General', 'Admin Backup'] as $grup) {
            $t = $this->tiketTriase();
            $this->withSession($this->sesi($this->akun($grup)))
                ->post(route('it-tracker.triase', $t->id), $this->isiTriase())
                ->assertStatus(403);
            $this->assertSame('TRIASE', $t->fresh()->fase, "$grup: tiket tak boleh bergeser.");
        }
    }

    // ───────────────────────── yang paling penting: Executive tak kehilangan paraf ─────────────────────────

    public function test_executive_masih_bisa_paraf_bo_di_fase_analisis(): void
    {
        $e = $this->akun('Executive');
        $t = $this->siapParaf();
        $this->assertEmpty($this->fr02($t)['paraf'] ?? null, 'sebelum: belum diparaf');

        $this->withSession($this->sesi($e))->post(route('it-tracker.paraf', $t->id))->assertStatus(302);

        $this->assertNotEmpty($this->fr02($t)['paraf'] ?? null, 'Paraf BO tetap milik Executive — membuka triase TIDAK boleh mencabutnya.');
    }

    public function test_executive_tidak_bisa_meneruskan_alias_melewati_paraf(): void
    {
        $t = $this->siapParaf();

        $this->withSession($this->sesi($this->akun('Executive')))
            ->post(route('it-tracker.teruskan', $t->id), ['alasan' => 'coba lewati'])
            ->assertStatus(403);

        $this->assertEmpty($this->fr02($t)['diteruskan'] ?? null, 'Tak ada jejak pelewatan.');
    }

    public function test_executive_tidak_bisa_menetapkan_pic_lewat_rute_pic_maupun_di_dalam_triase(): void
    {
        $e     = $this->akun('Executive');
        $reza  = DB::table('users as u')->join('group_path as g', 'g.id', '=', 'u.group_id')
            ->where('g.nama', 'IT')->where('u.status', 1)->value('u.id') ?: $this->akun('Head IT')->id;

        // rute /pic — tetap Head IT
        $t = $this->tiketTriase();
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit');
        $this->withSession($this->sesi($e))->post(route('it-tracker.pic', $t->id), ['pic_user_id' => $reza])->assertStatus(403);
        $this->assertNull($t->fresh()->pic_user_id);

        // pic_user_id yang diselipkan ke formulir triase — ditolak TERANG, tiket tak bergeser
        $t2 = $this->tiketTriase();
        $this->withSession($this->sesi($e))
            ->post(route('it-tracker.triase', $t2->id), $this->isiTriase(['pic_user_id' => $reza]))
            ->assertStatus(403);
        $t2 = $t2->fresh();
        $this->assertSame('TRIASE', $t2->fase);
        $this->assertNull($t2->pic_user_id);
    }

    public function test_head_it_tak_kehilangan_apa_pun(): void
    {
        $h    = $this->akun('Head IT');
        $reza = DB::table('users as u')->join('group_path as g', 'g.id', '=', 'u.group_id')
            ->where('g.nama', 'IT')->where('u.status', 1)->value('u.id') ?: $h->id;

        // triase + PIC sekaligus
        $t = $this->tiketTriase();
        $this->withSession($this->sesi($h))
            ->post(route('it-tracker.triase', $t->id), $this->isiTriase(['pic_user_id' => $reza]))->assertStatus(302);
        $t = $t->fresh();
        $this->assertSame('ANTRE', $t->fase);
        $this->assertSame($reza, $t->pic_user_id);

        // ganti PIC, tunda, buka lagi
        $this->withSession($this->sesi($h))->post(route('it-tracker.pic', $t->id), ['pic_user_id' => $h->id])->assertStatus(302);
        $this->assertSame($h->id, $t->fresh()->pic_user_id);
        $this->withSession($this->sesi($h))->post(route('it-tracker.parkir', $t->id), ['ke' => 'DITUNDA', 'alasan' => 'uji'])->assertStatus(302);
        $this->assertSame('DITUNDA', $t->fresh()->fase);

        // pelewatan paraf
        $p = $this->siapParaf();
        $this->withSession($this->sesi($h))->post(route('it-tracker.teruskan', $p->id), ['alasan' => 'BO cuti sampai 12 Sep, pemohon menunggu'])->assertStatus(302);
        $this->assertNotEmpty($this->fr02($p)['diteruskan'] ?? null);
    }

    // ───────────────────────── peran() tak berubah — diuji langsung ─────────────────────────

    public function test_peran_executive_dan_konsultan_it_tetap_pengawas_predikatnya_yang_terbuka(): void
    {
        $this->assertSame('pengawas', ItTrackerService::peran('Executive'));
        $this->assertSame('pengawas', ItTrackerService::peran('Konsultan IT'));
        $this->assertSame('head_it', ItTrackerService::peran('Head IT'));

        $this->assertTrue(ItTrackerService::bolehTriase('Head IT'));
        $this->assertTrue(ItTrackerService::bolehTriase('Super Admin'));
        $this->assertTrue(ItTrackerService::bolehTriase('Executive'));
        $this->assertTrue(ItTrackerService::bolehTriase('Konsultan IT'));
        foreach (['Konsultan Finance', 'Konsultan Marketing', 'Consultant', 'Admin General', 'Admin Backup', 'Admin', 'IT', 'Finance', null] as $g) {
            $this->assertFalse(ItTrackerService::bolehTriase($g), "'$g' tetap tertutup");
        }
    }

    // ───────────────────────── elemen layar: yang tampil = yang boleh ─────────────────────────

    public function test_layar_executive_memuat_modal_triase_tanpa_pic_tanpa_tunda_tolak(): void
    {
        $e = $this->akun('Executive');
        $t = $this->tiketTriase();

        $papan = $this->withSession($this->sesi($e))->get('/it-tracker')->assertStatus(200);
        $papan->assertSee('id="itkModalTriase"', false)
              ->assertSee('itkBukaTriase(\'' . $t->id . '\'', false)
              ->assertDontSee('id="itkParkirForm"', false)
              ->assertDontSee('name="pic_user_id"', false);

        $detail = $this->withSession($this->sesi($e))->get(route('it-tracker.show', $t->id))->assertStatus(200);
        $detail->assertSee('id="itkModalTriase"', false)
               ->assertDontSee('id="itkParkirForm"', false)
               ->assertDontSee('Tunda / Tolak', false)
               ->assertDontSee(route('it-tracker.pic', $t->id), false)
               ->assertDontSee(route('it-tracker.teruskan', $t->id), false);
    }

    public function test_layar_konsultan_finance_tanpa_modal_dan_head_it_masih_punya_tunda_tolak(): void
    {
        $t = $this->tiketTriase();

        $this->withSession($this->sesi($this->akun('Konsultan Finance')))->get('/it-tracker')->assertStatus(200)
            ->assertDontSee('id="itkModalTriase"', false)
            ->assertDontSee('itkBukaTriase(\'' . $t->id . '\'', false);

        $this->withSession($this->sesi($this->akun('Head IT')))->get('/it-tracker')->assertStatus(200)
            ->assertSee('id="itkModalTriase"', false)
            ->assertSee('id="itkParkirForm"', false);
    }
}
