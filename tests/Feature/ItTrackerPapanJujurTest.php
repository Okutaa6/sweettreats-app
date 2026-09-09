<?php

namespace Tests\Feature;

use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Gerbong "papan jujur" (5 Sep) — empat lubang yang membuat papan berbohong
 * pelan-pelan atau menahan orang tanpa perlu:
 *  A1. kolom kosong KARENA FILTER tampak sama dengan kolom yang memang kosong
 *  A2. pencarian tak menyentuh parkiran → tiket yang sudah ditutup seolah lenyap
 *  A3. triase dari detail melempar Head IT balik ke papan
 *  #5. IT pelaksana tak bisa menarik tiket sendiri (PIC hanya Head IT)
 *
 * Kelas terpisah — batas ±17 uji ber-DB per proses.
 */
class ItTrackerPapanJujurTest extends TestCase
{
    use DatabaseTransactions;

    private ItTrackerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);
    }

    private function sesi(string $groupName, string $uid): array
    {
        return ['isLoggedIn' => true, 'id' => $uid,
            'group_id' => DB::table('group_path')->where('nama', $groupName)->value('id') ?: 'grp-uji',
            'group_name' => $groupName, 'name' => 'Uji ' . $groupName,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time()];
    }

    private function tiket(array $isi = [], ?string $jenjang = 'M', string $fase = 'ANTRE'): ItTicket
    {
        $t = $this->svc->buat(array_merge([
            'judul' => 'Uji jujur ' . Str::random(6), 'sumber' => 'permintaan', 'jenis_permintaan' => 'Fitur baru',
            'prioritas_pemohon' => 'Sedang', 'latar' => 'uji', 'kebutuhan' => 'uji', 'terdampak' => 'x',
            'unit' => 'sweettreats',
        ], $isi), 'uji-pemohon-j');

        if ($jenjang) {
            $this->svc->triase($t, $jenjang, 3, 'P2', 'uji-headit');
            if ($fase !== 'ANTRE') $this->svc->pindahFase($t = $t->fresh(), $fase, 'uji-headit');
        }

        return $t->fresh();
    }

    // ─── A1: kolom kosong menyebut ke mana tiketnya pergi ──────────────────

    /**
     * Filter aktif → papan membawa alasan + jalan keluar sekali klik, dan tiap
     * kolom membawa hitungan SEBELUM filter supaya JS bisa membedakan
     * "kosong karena tersaring" dari "memang tak ada".
     */
    public function test_papan_membawa_alasan_tersaring_dan_hitungan_sebelum_filter(): void
    {
        $this->tiket(['judul' => 'Tiket permintaan biasa']);
        $ses = $this->sesi('Head IT', 'uji-headit');

        // tanpa filter: tak ada kotak alasan sama sekali
        $polos = $this->withSession($ses)->get(route('it-tracker.index'))->assertOk()->getContent();
        // uji ELEMEN-nya, bukan katanya: JS papan selalu menyebut data-fase-saring
        $this->assertStringNotContainsString('class="itk-fase-saring"', $polos, 'tanpa filter tak perlu kotak alasan');
        $this->assertStringContainsString('data-raw-fitur=', $polos, 'hitungan sebelum filter selalu ikut');

        // filter sumber: alasannya menyebut TAB SEBELAH, bukan sekadar "kosong"
        $html = $this->withSession($ses)->get(route('it-tracker.index', ['sumber' => 'inisiatif']))->assertOk()
            ->assertSee('ada di tab Permintaan')->assertSee('Buka tab Permintaan')->getContent();
        $this->assertStringContainsString('class="itk-fase-saring"', $html);
        // dan hitungan mentahnya TIDAK ikut tersaring — itulah gunanya
        $this->assertMatchesRegularExpression('/data-raw-fitur="[1-9]/', $html);

        // filter macet & pencarian punya kalimatnya sendiri
        $this->withSession($ses)->get(route('it-tracker.index', ['macet' => 1]))->assertOk()
            ->assertSee('penunggunya IT atau belum 3 hari', false)->assertSee('Lepas filter Macet');
        $this->withSession($ses)->get(route('it-tracker.index', ['q' => 'zzz-tak-ada']))->assertOk()
            ->assertSee('Hapus pencarian');
    }

    /** RB Las: alasannya menyebut unitnya, bukan kalimat umum. */
    public function test_filter_unit_menyebut_unitnya(): void
    {
        $this->tiket(['judul' => 'Tiket sweettreats saja']);

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.index', ['unit' => 'rblas']))->assertOk()
            ->assertSee('bukan tiket RB Las')->assertSee('Lepas filter RB Las');
    }

    // ─── A2: pencarian menjangkau parkiran ────────────────────────────────

    /** Tiket yang sudah SELESAI tetap ketemu — papan menunjuk ke parkiran. */
    public function test_pencarian_menjangkau_parkiran(): void
    {
        $t = $this->tiket(['judul' => 'Lapor komisi dokter bulanan'], 'S', 'DEV');
        $this->svc->parkir($t, 'DITUNDA', 'menunggu data Finance', 'uji-headit');

        $ses = $this->sesi('Head IT', 'uji-headit');
        $html = $this->withSession($ses)->get(route('it-tracker.index', ['q' => 'komisi dokter bulanan']))
            ->assertOk()
            ->assertSee('ada di <strong>parkiran</strong>', false)
            ->assertSee('Buka parkiran')
            ->getContent();
        $this->assertStringContainsString($t->nomor, $html, 'nomornya disebut, bukan cuma jumlahnya');

        // kendali negatif: kata yang tak ada di mana pun tidak memunculkan petunjuk palsu
        $this->withSession($ses)->get(route('it-tracker.index', ['q' => 'zzz-tak-ada-di-mana-pun']))
            ->assertOk()->assertDontSee('Buka parkiran');
    }

    /** Parkiran hanya disaring PENCARIAN — filter lain (macet/sumber) tak menyentuhnya. */
    public function test_parkiran_tak_ikut_filter_selain_pencarian(): void
    {
        $t = $this->tiket(['judul' => 'Tiket parkir ' . Str::random(4)], 'S', 'DEV');
        $this->svc->parkir($t, 'DITOLAK', 'duplikat', 'uji-headit');

        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.index', ['sumber' => 'inisiatif']))->assertOk()
            ->assertSee($t->nomor);
    }

    // ─── A3: triase tanpa dilempar balik ke papan ─────────────────────────

    /** Detail tiket TRIASE memuat modalnya sendiri; tak ada lagi lemparan ?q=. */
    public function test_triase_bisa_dari_halaman_detail(): void
    {
        $t = $this->tiket([], null);
        $this->assertSame('TRIASE', $t->fase);

        $html = $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->get(route('it-tracker.show', $t->id))->assertOk()
            ->assertSee('itkBukaTriase', false)
            ->assertSee('itkModalTriase', false)
            ->getContent();
        $this->assertStringNotContainsString('it-tracker?q=' . $t->nomor, $html, 'tak dilempar balik ke papan');

        // dan formulirnya sungguh bekerja dari sana
        $this->withSession($this->sesi('Head IT', 'uji-headit'))
            ->post(route('it-tracker.triase', $t->id), ['jenjang' => 'M', 'bobot' => 3, 'prioritas_it' => 'P2'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('ANTRE', $t->fresh()->fase);

        // IT pelaksana tak melihat modalnya (gerbangnya tetap di controller)
        $u = $this->tiket([], null);
        $this->withSession($this->sesi('IT', 'uji-reza'))->get(route('it-tracker.show', $u->id))->assertOk()
            ->assertDontSee('itkModalTriase', false);
    }

    // ─── #5: tarik tiket sendiri ──────────────────────────────────────────

    /** IT pelaksana menarik tiket tanpa PIC → jadi miliknya, dan gerbangnya terbuka. */
    public function test_it_pelaksana_menarik_tiket_tanpa_pic(): void
    {
        $t = $this->tiket();
        $this->assertNull($t->pic_user_id, 'triase tanpa PIC memang boleh');

        // sebelum ditarik: ia terkunci di luar tiketnya sendiri
        $this->withSession($this->sesi('IT', 'uji-reza'))->get(route('it-tracker.show', $t->id))->assertOk()
            ->assertSee('Tarik — aku yang kerjakan');

        $this->withSession($this->sesi('IT', 'uji-reza'))->post(route('it-tracker.tarik', $t->id))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('uji-reza', $t->fresh()->pic_user_id);

        // sesudah jadi PIC, ANTRE → Development memang haknya (bolehGerakkan)
        $this->assertSame('ANALISIS', $this->svc->majukan($t->fresh(), 'uji-reza'));
    }

    /** Tiket yang SUDAH ber-PIC tak bisa direbut — pemindahan tetap lewat Head IT. */
    public function test_tiket_ber_pic_tak_bisa_direbut(): void
    {
        $t = $this->tiket();
        $t->pic_user_id = 'uji-headit';
        $t->save();

        $this->withSession($this->sesi('IT', 'uji-reza'))->post(route('it-tracker.tarik', $t->id))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame('uji-headit', $t->fresh()->pic_user_id, 'PIC lama tak berubah');

        // tombolnya pun tak ditawarkan
        $this->withSession($this->sesi('IT', 'uji-reza'))->get(route('it-tracker.show', $t->id))->assertOk()
            ->assertDontSee('Tarik — aku yang kerjakan');
    }

    /** Bukan orang IT tak boleh menarik apa pun — gerbangnya di controller, bukan di tombol. */
    public function test_non_it_ditolak_menarik(): void
    {
        $t = $this->tiket();

        $this->withSession($this->sesi('Head Marketing', 'uji-pemohon-j'))
            ->post(route('it-tracker.tarik', $t->id))->assertStatus(403);
        $this->withSession($this->sesi('Executive', 'uji-robi'))
            ->post(route('it-tracker.tarik', $t->id))->assertStatus(403);
        $this->assertNull($t->fresh()->pic_user_id);
    }

    /** Tiket parkir/selesai tak bisa ditarik — tak ada kerjaan yang sedang berjalan di sana. */
    public function test_tiket_parkir_tak_bisa_ditarik(): void
    {
        $t = $this->tiket();
        $this->svc->parkir($t, 'DITUNDA', 'menunggu keputusan', 'uji-headit');

        $this->withSession($this->sesi('IT', 'uji-reza'))->post(route('it-tracker.tarik', $t->id))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertNull($t->fresh()->pic_user_id);
    }
}
