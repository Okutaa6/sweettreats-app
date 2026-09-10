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
 * Wakil Head IT — keputusan Ekky 10 Sep 2026, alasan kelangsungan kerja.
 *
 * "Sebagai Head IT aku nggak akan selalu ada, dan konsultan yang biasanya
 * mengganti pekerjaanku. Kalau aku sakit seminggu, pekerjaan tidak ada yang
 * bergerak dong?" Yang sebenarnya berhenti bukan pengembangan — IT pelaksana
 * bisa menarik tiketnya sendiri dari Antre dan mendorongnya sampai Go Live —
 * melainkan PENILAIAN Head IT, dan puncaknya satu: tak ada yang bisa menulis
 * FRM-IT-02, sehingga semua tiket M/L berhenti tepat di pintu Analisis.
 *
 * Konsultan IT kini memegang paket itu. Executive TIDAK: kalau Business Owner
 * menulis analisis lalu memaraf analisisnya sendiri, parafnya berhenti berarti.
 *
 * Kelas ini juga menjaga jalan pintas "langsung mulai Analysis & Design" dari
 * layar penilaian awal: hanya untuk yang benar-benar bisa menganalisis, dan
 * hanya untuk jenjang M/L yang punya fase itu.
 *
 * Akun SUNGGUHAN per grup (AuthGuard membaca group_id dari sesi).
 */
class ItTrackerWakilHeadItTest extends TestCase
{
    use DatabaseTransactions;

    private ItTrackerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);

        foreach (['Konsultan IT', 'Executive', 'Head IT', 'IT', 'Konsultan Finance'] as $g) {
            if (! DB::table('group_path')->where('nama', $g)->exists()) {
                $this->markTestSkipped("Grup '$g' tidak ada di lingkungan ini (jalankan SQL 2026_08_21_konsultan_struktur).");
            }
        }
    }

    // ───────────────────────── perkakas ─────────────────────────

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

    private function tiket(): ItTicket
    {
        return $this->svc->buat([
            'judul' => 'Uji wakil ' . Str::random(6), 'sumber' => 'permintaan', 'jenis_permintaan' => 'Fitur baru',
            'prioritas_pemohon' => 'Sedang', 'latar' => 'uji', 'kebutuhan' => 'uji', 'terdampak' => 'Marketing',
        ], 'uji-pemohon-wakil');
    }

    /** Tiket M di ANALISIS, analisis BELUM ditulis — inilah pintu yang macet tanpa Head IT. */
    private function diAnalisis(): ItTicket
    {
        $t = $this->tiket();
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit-w');
        $this->svc->pindahFase($t = $t->fresh(), 'ANALISIS', 'uji-headit-w');
        return $t->fresh();
    }

    private function fr02(ItTicket $t): array
    {
        return ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-02')->value('payload') ?? [];
    }

    private function isiNilai(array $tambah = []): array
    {
        return array_merge(['jenjang' => 'M', 'bobot' => 3, 'prioritas_it' => 'P2'], $tambah);
    }

    // ───────── inti: pintu yang macet kini terbuka untuk wakil ─────────

    public function test_konsultan_it_menulis_analisis_frm_it_02_lalu_gerbangnya_terbuka(): void
    {
        $k = $this->akun('Konsultan IT');
        $t = $this->diAnalisis();

        // 1. menulis analisis — yang sebelumnya Head IT saja
        $this->withSession($this->sesi($k))->post(route('it-tracker.fr', $t->id), [
            'baris' => [['kebutuhan' => 'Rekap ortho per cabang', 'prioritas' => 'Tinggi', 'kriteria' => 'angka cocok dengan Omnicare']],
        ])->assertStatus(302)->assertSessionHasNoErrors();
        $this->withSession($this->sesi($k))->post(route('it-tracker.komponen', $t->id), ['komponen' => ['Aplikasi']])
            ->assertStatus(302)->assertSessionHasNoErrors();
        // baris FR hidup di tabel it_fr, komponennya di payload FRM-IT-02
        $this->assertSame(1, DB::table('it_fr')->where('ticket_id', $t->id)->count(), 'baris FR tersimpan oleh wakil');
        $this->assertNotEmpty($this->fr02($t)['komponen'] ?? [], 'komponen terdampak tersimpan oleh wakil');

        // 2. paraf Business Owner tetap milik BO — wakil tidak memarafnya sendiri
        $this->withSession($this->sesi($this->akun('Executive')))
            ->post(route('it-tracker.paraf', $t->id))->assertStatus(302);
        $this->assertNotEmpty($this->fr02($t)['paraf'] ?? null);

        // 3. gerbang ke Development dibuka wakil
        $this->withSession($this->sesi($k))->post(route('it-tracker.maju', $t->id))->assertStatus(302);
        $this->assertSame('DEV', $t->fresh()->fase, 'Analisis → Development oleh wakil.');
    }

    public function test_konsultan_it_memegang_seluruh_pekerjaan_head_it(): void
    {
        $k    = $this->akun('Konsultan IT');
        $reza = $this->akun('IT');

        // menilai + menunjuk PIC sekaligus
        $t = $this->tiket();
        $this->withSession($this->sesi($k))
            ->post(route('it-tracker.triase', $t->id), $this->isiNilai(['pic_user_id' => $reza->id]))
            ->assertStatus(302);
        $t = $t->fresh();
        $this->assertSame('ANTRE', $t->fase);
        $this->assertSame($reza->id, $t->pic_user_id, 'Wakil boleh menunjuk PIC.');

        // memindahkan PIC lewat rute /pic
        $this->withSession($this->sesi($k))
            ->post(route('it-tracker.pic', $t->id), ['pic_user_id' => $this->akun('Head IT')->id])->assertStatus(302);
        $this->assertNotSame($reza->id, $t->fresh()->pic_user_id);

        // menaut modul katalog sesudah penilaian
        $modulId = DB::table('it_modul')->value('id');
        if ($modulId) {
            $this->withSession($this->sesi($k))
                ->post(route('it-tracker.modul', $t->id), ['modul_id' => $modulId])->assertStatus(302);
            $this->assertSame((int) $modulId, (int) $t->fresh()->modul_id);
        }

        // menunda, lalu membuka lagi
        $this->withSession($this->sesi($k))
            ->post(route('it-tracker.parkir', $t->id), ['ke' => 'DITUNDA', 'alasan' => 'menunggu data Finance'])->assertStatus(302);
        $this->assertSame('DITUNDA', $t->fresh()->fase);
        $this->withSession($this->sesi($k))->post(route('it-tracker.buka', $t->id))->assertStatus(302);
        $this->assertSame('ANTRE', $t->fresh()->fase);
    }

    /** Pelewatan paraf BO: wakil boleh, DAN jejaknya tetap bertanda — bukan menyamar jadi paraf. */
    public function test_konsultan_it_boleh_meneruskan_tanpa_paraf_dan_tetap_bertanda(): void
    {
        $k = $this->akun('Konsultan IT');
        $t = $this->diAnalisis();
        $this->svc->simpanFr($t, [['kebutuhan' => 'a', 'prioritas' => 'Tinggi', 'kriteria' => 'b']]);
        $this->svc->setKomponen($t, ['Aplikasi']);

        $this->withSession($this->sesi($k))
            ->post(route('it-tracker.teruskan', $t->id), ['alasan' => 'Robi cuti sampai 15 Sep, pemohon menunggu'])
            ->assertStatus(302);

        $f = $this->fr02($t);
        $this->assertNotEmpty($f['diteruskan'] ?? null, 'pelewatan tercatat');
        $this->assertEmpty($f['paraf'] ?? null, 'pelewatan TIDAK menjadi paraf');
    }

    // ───────── yang TIDAK berubah: Executive ─────────

    public function test_executive_tetap_tak_boleh_menulis_analisis_maupun_meneruskan(): void
    {
        $e = $this->akun('Executive');
        $t = $this->diAnalisis();

        $this->withSession($this->sesi($e))->post(route('it-tracker.fr', $t->id), [
            'baris' => [['kebutuhan' => 'x', 'prioritas' => 'Tinggi', 'kriteria' => 'y']],
        ])->assertStatus(403);
        $this->withSession($this->sesi($e))->post(route('it-tracker.komponen', $t->id), ['komponen' => ['Aplikasi']])->assertStatus(403);
        $this->assertSame(0, DB::table('it_fr')->where('ticket_id', $t->id)->count(), 'Business Owner tidak menulis analisis yang akan diparafnya sendiri.');
        $this->assertEmpty($this->fr02($t)['komponen'] ?? [], 'komponen pun tak tersentuh Executive');

        $this->svc->simpanFr($t, [['kebutuhan' => 'a', 'prioritas' => 'Tinggi', 'kriteria' => 'b']]);
        $this->svc->setKomponen($t, ['Aplikasi']);
        $this->withSession($this->sesi($e))
            ->post(route('it-tracker.teruskan', $t->id), ['alasan' => 'melewati parafku sendiri'])->assertStatus(403);
        $this->assertEmpty($this->fr02($t)['diteruskan'] ?? null);

        // …tapi parafnya sendiri tetap jalan
        $this->withSession($this->sesi($e))->post(route('it-tracker.paraf', $t->id))->assertStatus(302);
        $this->assertNotEmpty($this->fr02($t)['paraf'] ?? null);
    }

    public function test_executive_tetap_tak_boleh_menunda_menunjuk_pic_atau_memajukan(): void
    {
        $e = $this->akun('Executive');
        $t = $this->tiket();
        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit-w');

        $this->withSession($this->sesi($e))
            ->post(route('it-tracker.parkir', $t->id), ['ke' => 'DITUNDA', 'alasan' => 'coba tunda'])->assertStatus(403);
        $this->withSession($this->sesi($e))
            ->post(route('it-tracker.pic', $t->id), ['pic_user_id' => $this->akun('IT')->id])->assertStatus(403);
        $this->withSession($this->sesi($e))->post(route('it-tracker.maju', $t->id))->assertStatus(403);

        $t = $t->fresh();
        $this->assertSame('ANTRE', $t->fase);
        $this->assertNull($t->pic_user_id);
    }

    public function test_konsultan_finance_tak_kebagian_apa_pun(): void
    {
        $kf = $this->akun('Konsultan Finance');
        $t  = $this->diAnalisis();

        $this->withSession($this->sesi($kf))->post(route('it-tracker.fr', $t->id), [
            'baris' => [['kebutuhan' => 'x', 'prioritas' => 'Tinggi', 'kriteria' => 'y']],
        ])->assertStatus(403);
        $this->withSession($this->sesi($kf))->post(route('it-tracker.maju', $t->id))->assertStatus(403);
        $this->withSession($this->sesi($kf))
            ->post(route('it-tracker.parkir', $t->id), ['ke' => 'DITOLAK', 'alasan' => 'coba tolak'])->assertStatus(403);

        $this->assertSame('ANALISIS', $t->fresh()->fase);
    }

    // ───────── peran() tak bergeser ─────────

    public function test_peran_konsultan_it_tetap_pengawas_predikatnya_yang_terbuka(): void
    {
        $this->assertSame('pengawas', ItTrackerService::peran('Konsultan IT'), 'kalau ini jadi head_it, hak paraf BO-nya hilang');
        $this->assertSame('head_it', ItTrackerService::peran('Head IT'));

        $this->assertTrue(ItTrackerService::bolehKerjaHeadIt('Head IT'));
        $this->assertTrue(ItTrackerService::bolehKerjaHeadIt('Super Admin'));
        $this->assertTrue(ItTrackerService::bolehKerjaHeadIt('Konsultan IT'));
        foreach (['Executive', 'Konsultan Finance', 'Konsultan Marketing', 'Consultant', 'Admin General', 'Admin Backup', 'Admin', 'IT', 'Finance', null] as $g) {
            $this->assertFalse(ItTrackerService::bolehKerjaHeadIt($g), "'$g' bukan wakil Head IT");
        }

        // penilaian awal tetap lebih luas dari pekerjaan Head IT
        $this->assertTrue(ItTrackerService::bolehTriase('Executive'));
        $this->assertTrue(ItTrackerService::bolehTriase('Konsultan IT'));
        $this->assertFalse(ItTrackerService::bolehTriase('Konsultan Finance'));
    }

    /** IT pelaksana tak kehilangan apa pun: menarik tiket tanpa PIC lalu memajukannya sendiri. */
    public function test_it_pelaksana_tak_terganggu(): void
    {
        $reza = $this->akun('IT');
        $t    = $this->tiket();
        $this->svc->triase($t, 'S', 2, 'P2', 'uji-headit-w');

        $this->withSession($this->sesi($reza))->post(route('it-tracker.tarik', $t->id))->assertStatus(302);
        $this->assertSame($reza->id, $t->fresh()->pic_user_id);
        $this->withSession($this->sesi($reza))->post(route('it-tracker.maju', $t->id))->assertStatus(302);
        $this->assertSame('DEV', $t->fresh()->fase, 'inilah sebabnya pengembangan TIDAK mandek tanpa Head IT');
    }

    // ───────── jalan pintas: langsung mulai Analysis & Design ─────────

    public function test_wakil_menilai_dan_langsung_mulai_analisis_tanpa_singgah_di_antre(): void
    {
        $k = $this->akun('Konsultan IT');
        $t = $this->tiket();

        $this->withSession($this->sesi($k))
            ->post(route('it-tracker.triase', $t->id), $this->isiNilai(['lanjut' => 'analisis']))
            ->assertStatus(302)->assertSessionHas('success');

        $t = $t->fresh();
        $this->assertSame('ANALISIS', $t->fase, 'lompat langsung, bukan lewat Antre');
        $this->assertSame('M', $t->jenjang);
        $this->assertSame(0, ItTicketFase::where('ticket_id', $t->id)->where('fase', 'ANTRE')->count(),
            'tak ada baris ANTRE di riwayat — tiketnya memang tak pernah singgah, bukan masuk-keluar dalam satu detik');
        $this->assertSame(2, ItTicketFase::where('ticket_id', $t->id)->count(), 'riwayatnya dua langkah: Requirement lalu Analysis & Design');
        $this->assertSame($k->id, ItTicketFase::where('ticket_id', $t->id)->where('fase', 'ANALISIS')->value('oleh_user_id'));

        // ⚠️ penilainya harus TETAP terbaca di layar. jejakTriase() dulu mencari langkah
        // ANTRE secara khusus, jadi tiket yang melompat justru kehilangan nama penilainya.
        $this->withSession($this->sesi($k))->get(route('it-tracker.show', $t->id))->assertOk()
            ->assertSee('itk-triase-oleh', false)
            ->assertSee('<b>Dinilai oleh</b><span>' . e($k->nama), false);
    }

    /** Jenjang S tak punya fase Analisis: pilihannya ditolak, tapi penilaiannya TETAP tersimpan. */
    public function test_jenjang_s_tetap_ke_antre_dan_pesannya_menyebut_kenapa(): void
    {
        $k = $this->akun('Konsultan IT');
        $t = $this->tiket();

        $r = $this->withSession($this->sesi($k))
            ->post(route('it-tracker.triase', $t->id), $this->isiNilai(['jenjang' => 'S', 'lanjut' => 'analisis']));
        $r->assertStatus(302);

        $t = $t->fresh();
        $this->assertSame('ANTRE', $t->fase, 'S tak boleh melompat ke Development tanpa pengembang');
        $this->assertSame('S', $t->jenjang, 'penilaiannya tetap tersimpan — bukan digagalkan');
        $this->assertStringContainsString('Jenjang S', (string) $r->getSession()->get('success'));
    }

    /** Executive boleh menilai, TIDAK boleh memulai analisis — kalau boleh, papan berkata "sedang dianalisis" tanpa penganalisis. */
    public function test_executive_tak_boleh_memakai_jalan_pintas_analisis(): void
    {
        $e = $this->akun('Executive');
        $t = $this->tiket();

        $this->withSession($this->sesi($e))
            ->post(route('it-tracker.triase', $t->id), $this->isiNilai(['lanjut' => 'analisis']))
            ->assertStatus(403);

        $this->assertSame('TRIASE', $t->fresh()->fase, 'ditolak sebelum menulis apa pun');
    }

    // ───────── layar: yang tampil = yang boleh ─────────

    public function test_layar_wakil_memuat_tombol_head_it_dan_jalan_pintas(): void
    {
        $k = $this->akun('Konsultan IT');
        $t = $this->tiket();

        $papan = $this->withSession($this->sesi($k))->get('/it-tracker')->assertStatus(200);
        $papan->assertSee('id="itkModalTriase"', false)
              ->assertSee('id="itkParkirForm"', false)
              ->assertSee('value="analisis"', false);

        $this->svc->triase($t, 'M', 3, 'P1', $k->id);
        $detail = $this->withSession($this->sesi($k))->get(route('it-tracker.show', $t->id))->assertStatus(200);
        $detail->assertSee('Tunda / Tolak', false)
               ->assertSee(route('it-tracker.pic', $t->id), false);
    }

    public function test_layar_executive_tanpa_tombol_head_it_dan_tanpa_jalan_pintas(): void
    {
        $e = $this->akun('Executive');
        $t = $this->tiket();

        $papan = $this->withSession($this->sesi($e))->get('/it-tracker')->assertStatus(200);
        $papan->assertSee('id="itkModalTriase"', false)
              ->assertDontSee('id="itkParkirForm"', false)
              ->assertDontSee('value="analisis"', false);

        $this->svc->triase($t, 'M', 3, 'P1', 'uji-headit-w');
        $this->withSession($this->sesi($e))->get(route('it-tracker.show', $t->id))->assertStatus(200)
            ->assertDontSee('Tunda / Tolak', false)
            ->assertDontSee(route('it-tracker.pic', $t->id), false);
    }
}
