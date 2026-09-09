<?php

namespace Tests\Feature;

use App\Models\ItModul;
use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\MenyemaiGrantItTracker;
use Tests\TestCase;

/**
 * E2E per PERSONA — Katalog Modul × Papan Antrean, satu perjalanan penuh tiap orang.
 *
 * Bukan uji unit: tiap metode meniru satu orang memakai sistem dari layar ke
 * layar, dan yang diperiksa adalah APA YANG IA LIHAT — termasuk yang TIDAK
 * boleh ia lihat. Empat persona:
 *   · Della  — Head Finance (pemohon)     : mengajukan, melihat miliknya
 *   · Ekky   — Head IT                    : triase + modul, entri katalog
 *   · Reza   — IT pelaksana               : menarik tiket, menggerakkan
 *   · Robi   — Executive                  : datang sebulan sekali, matriks
 *
 * Kelas terpisah — batas ±17 uji ber-DB per proses.
 */
class ItKatalogE2ETest extends TestCase
{
    use DatabaseTransactions;
    use MenyemaiGrantItTracker;

    private ItTrackerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);

        try {
            DB::select('SELECT modul_id FROM it_ticket LIMIT 0');
        } catch (\Throwable) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_06_it_modul_katalog.sql dulu.');
        }
    }

    // ── perkakas persona ──────────────────────────────────────────────────

    private function sesi(string $grup, string $uid): array
    {
        $gid = DB::table('group_path')->where('nama', $grup)->value('id') ?: 'grp-uji';
        $this->pastikanGrantItTracker($gid);   // grant disemai di dalam transaksi — uji menguji KEMAMPUAN peran, bukan siapa yang ber-grant hari ini

        return ['isLoggedIn' => true, 'id' => $uid,
            'group_id' => $gid,
            'group_name' => $grup, 'name' => 'Uji ' . $grup,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time()];
    }

    private function della(): array { return $this->sesi('Finance', 'e2e-della'); }
    private function ekky(): array  { return $this->sesi('Head IT', 'e2e-ekky'); }
    private function reza(): array  { return $this->sesi('IT', 'e2e-reza'); }
    private function robi(): array  { return $this->sesi('Executive', 'e2e-robi'); }

    private function modul(string $dept = 'Finance', string $nama = null): ItModul
    {
        return ItModul::create(['nama' => $nama ?: ('E2E ' . Str::random(7)), 'departemen' => $dept,
            'jenis' => 'Operasional', 'status' => 'hidup', 'sejak' => '2026-06-04',
            'sejak_terbukti' => true, 'urutan' => 900]);
    }

    /** Della mengajukan permintaan lewat formulir — persis seperti di layar. */
    private function ajukan(string $judul, array $sesi = null): ItTicket
    {
        $this->withSession($sesi ?: $this->della())
            ->post(route('it-tracker.store'), [
                'judul' => $judul, 'sumber' => 'permintaan', 'jenis_permintaan' => 'Fitur baru',
                'prioritas_pemohon' => 'Tinggi', 'latar' => 'uji e2e', 'kebutuhan' => 'uji e2e',
                'terdampak' => 'Finance', 'unit' => 'sweettreats',
            ])->assertRedirect()->assertSessionHas('success');

        return ItTicket::where('judul', $judul)->firstOrFail();
    }

    // ── PERSONA: Della (Head Finance) ─────────────────────────────────────

    /**
     * Della mengajukan → tiketnya muncul di "Permintaan Saya" dengan posisi
     * antrean, DAN di katalog ia melihat angka Finance bertambah — tanpa perlu
     * mengerti kata "modul".
     */
    public function test_della_mengajukan_lalu_melihat_dampaknya(): void
    {
        $m = $this->modul('Finance');
        $judul = 'Rekap kas kecil per cabang ' . Str::random(4);
        $t = $this->ajukan($judul);

        $this->withSession($this->della())->get(route('it-tracker.permintaan'))->assertOk()
            ->assertSee($judul)->assertSee('Menunggu triase IT');

        // sebelum ditriase ia belum bertaut modul — dan katalog mengatakannya apa adanya
        $this->withSession($this->della())->get(route('it-tracker.katalog'))->assertOk()
            ->assertSee('Tanpa modul');

        // sesudah Head IT menautkan, angka modulnya hidup tanpa perawatan
        $this->withSession($this->ekky())->post(route('it-tracker.triase', $t->id),
            ['jenjang' => 'M', 'bobot' => 3, 'prioritas_it' => 'P1', 'modul_id' => $m->id])->assertRedirect();

        $this->withSession($this->della())->get(route('it-tracker.katalog', ['tampilan' => 'daftar']))->assertOk()
            ->assertSee($m->nama)->assertSee('1 tiket');
    }

    /**
     * ⛔ Della TIDAK boleh membaca isi tiket departemen lain — aturan rencana §12
     * yang sudah dipegang detail tiket, sekarang juga dipegang panel katalog.
     * Angkanya tetap terlihat (beban IT memang harus terbaca), judulnya tidak.
     */
    public function test_della_melihat_angka_tapi_bukan_isi_tiket_departemen_lain(): void
    {
        $mFin = $this->modul('Finance');
        $mMkt = $this->modul('Marketing');

        $milikku = $this->ajukan('Kas kecil milik Della ' . Str::random(4));
        $this->withSession($this->ekky())->post(route('it-tracker.triase', $milikku->id),
            ['jenjang' => 'S', 'bobot' => 2, 'prioritas_it' => 'P2', 'modul_id' => $mFin->id])->assertRedirect();

        $orangLain = $this->ajukan('Rahasia Marketing ' . Str::random(4), $this->sesi('Head Marketing', 'e2e-yovan'));
        $this->withSession($this->ekky())->post(route('it-tracker.triase', $orangLain->id),
            ['jenjang' => 'S', 'bobot' => 2, 'prioritas_it' => 'P2', 'modul_id' => $mMkt->id])->assertRedirect();

        $html = $this->withSession($this->della())->get(route('it-tracker.katalog'))->assertOk()
            ->assertSee($milikku->judul)                       // miliknya: terbaca penuh
            ->assertDontSee($orangLain->judul)                 // punya orang lain: tidak
            ->assertSee('Tiket departemen lain — isinya tidak ditampilkan')
            ->getContent();

        // tapi ANGKA modul Marketing tetap ada — yang disensor isinya, bukan bebannya
        $this->assertStringContainsString($mMkt->nama, $html);
        $this->assertMatchesRegularExpression('/' . preg_quote($mMkt->nama, '/') . '.{0,4000}?1 tiket/s', $html);

        // dan pintu detailnya tetap tertutup di controller, bukan cuma di layar
        $this->withSession($this->della())->get(route('it-tracker.show', $orangLain->id))->assertStatus(403);
    }

    // ── PERSONA: Ekky (Head IT) ───────────────────────────────────────────

    /**
     * Triase = satu layar: jenjang, bobot, prioritas, PIC, DAN modul katalog.
     * Sesudah itu papan bisa disaring ke modul itu, dan katalog menyebut jumlah
     * yang sama — dua tampilan atas satu data.
     */
    public function test_ekky_triase_dengan_modul_lalu_menelusuri_angkanya(): void
    {
        $m = $this->modul('Finance');
        $a = $this->ajukan('ROP lembar bayar ' . Str::random(4));
        $b = $this->ajukan('ROP rekap pengeluaran ' . Str::random(4));

        foreach ([$a, $b] as $t) {
            $this->withSession($this->ekky())->post(route('it-tracker.triase', $t->id),
                ['jenjang' => 'M', 'bobot' => 3, 'prioritas_it' => 'P2', 'modul_id' => $m->id])
                ->assertRedirect()->assertSessionHas('success');
        }

        // katalog: 2 tiket di modul itu
        $this->withSession($this->ekky())->get(route('it-tracker.katalog', ['tampilan' => 'daftar']))->assertOk()
            ->assertSee('2 tiket');

        // klik angkanya → papan tersaring, hanya dua itu, dan chipnya menyebut modulnya
        $this->withSession($this->ekky())->get(route('it-tracker.index', ['modul' => $m->id]))->assertOk()
            ->assertSee('Modul: ' . $m->nama, false)
            ->assertSee($a->judul)->assertSee($b->judul);

        // detail tiket menyebut modulnya, dengan tautan balik ke katalog
        $this->withSession($this->ekky())->get(route('it-tracker.show', $a->id))->assertOk()
            ->assertSee($m->nama)->assertSee(route('it-tracker.katalog', ['modul' => $m->id]), false);
    }

    /** Modul lahir dari tiket: Ekky membuat entri baru dari katalog, lalu menautkannya. */
    public function test_ekky_membuat_entri_modul_lalu_menautkan_tiket(): void
    {
        $t = $this->ajukan('Stok bahan habis pakai ' . Str::random(4));
        $this->withSession($this->ekky())->post(route('it-tracker.triase', $t->id),
            ['jenjang' => 'L', 'bobot' => 5, 'prioritas_it' => 'P2'])->assertRedirect();
        $this->assertNull($t->fresh()->modul_id, 'boleh ditriase tanpa modul — tak ada tebakan');

        $nama = 'Inventory E2E ' . Str::random(5);
        $this->withSession($this->ekky())->post(route('it-tracker.katalog.store'),
            ['nama' => $nama, 'departemen' => 'Stok / Inventory', 'jenis' => 'Operasional',
             'status' => 'rencana', 'catatan' => 'lahir dari tiket ' . $t->nomor])
            ->assertRedirect()->assertSessionHas('success');

        $m = ItModul::where('nama', $nama)->firstOrFail();
        $this->assertSame('rencana', $m->status);

        // tautkan dari halaman tiket (tiket lama tak lewat triase baru pun tetap bisa)
        $this->withSession($this->ekky())->post(route('it-tracker.modul', $t->id), ['modul_id' => $m->id])
            ->assertRedirect()->assertSessionHas('success');

        $this->withSession($this->ekky())->get(route('it-tracker.katalog', ['tampilan' => 'daftar']))->assertOk()
            ->assertSee($nama)->assertSee('1 tiket');
    }

    // ── PERSONA: Reza (IT pelaksana) ──────────────────────────────────────

    /** Reza menarik tiket tanpa PIC, menggerakkannya, dan angka katalog ikut bergerak. */
    public function test_reza_menarik_dan_menggerakkan_lalu_angka_katalog_ikut(): void
    {
        $m = $this->modul('Operasional / HR');
        $t = $this->ajukan('Kehadiran cabang baru ' . Str::random(4));
        $this->withSession($this->ekky())->post(route('it-tracker.triase', $t->id),
            ['jenjang' => 'S', 'bobot' => 2, 'prioritas_it' => 'P1', 'modul_id' => $m->id])->assertRedirect();

        // sebelum ditarik ia belum boleh menggerakkan
        $this->withSession($this->reza())->get(route('it-tracker.show', $t->id))->assertOk()
            ->assertSee('Tarik — aku yang kerjakan');

        $this->withSession($this->reza())->post(route('it-tracker.tarik', $t->id))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('e2e-reza', $t->fresh()->pic_user_id);

        $this->assertSame('DEV', $this->svc->majukan($t->fresh(), 'e2e-reza'));

        // katalog: masih 1 tiket terbuka di modul itu, dan PIC-nya terbaca di panel
        $this->withSession($this->reza())->get(route('it-tracker.katalog', ['tampilan' => 'daftar']))->assertOk()
            ->assertSee($m->nama)->assertSee('1 tiket')->assertSee('Uji IT');
    }

    // ── PERSONA: Robi (Executive) ─────────────────────────────────────────

    /**
     * Robi datang sebulan sekali: ia mendarat di MATRIKS, melihat 33 modul
     * sekali lihat, menyaring "semua laporan lintas departemen", dan tak
     * ditawari satu pun tombol tulis.
     */
    public function test_robi_mendarat_di_matriks_dan_menyaring_laporan(): void
    {
        Artisan::call('it:seed-katalog', ['--env-guard' => 'local']);

        $html = $this->withSession($this->robi())->get(route('it-tracker.katalog'))->assertOk()
            ->assertSee('APA SAJA YANG KAMI PUNYA')
            ->assertDontSee('＋ Entri modul')
            ->getContent();
        $this->assertStringContainsString('itk-mx-grid', $html, 'Executive mendarat di Matriks');
        $this->assertSame(37, substr_count($html, 'itk-mchip'), '37 modul dalam satu layar (§14.26)');

        // pertanyaan khas Robi: "perlihatkan semua laporan & dashboard"
        $lap = $this->withSession($this->robi())
            ->get(route('it-tracker.katalog', ['lensa' => 'jenis', 'jenis' => 'Laporan & Dashboard', 'tampilan' => 'daftar']))
            ->assertOk()->assertSee('PEMILIK')->getContent();
        $this->assertSame(8, substr_count($lap, 'itk-kat-row rencana') + substr_count($lap, 'itk-kat-row hidup')
            + substr_count($lap, 'itk-kat-row dibangun'), 'delapan laporan lintas departemen');

        // ia boleh membaca papan, tapi bukan menulis katalog
        $this->withSession($this->robi())->post(route('it-tracker.katalog.store'),
            ['nama' => 'X ' . Str::random(4), 'departemen' => 'IT', 'jenis' => 'Platform', 'status' => 'rencana'])
            ->assertStatus(403);
    }

    /**
     * Angka yang dilihat Robi harus bisa DICOCOKKAN, bukan dipercaya:
     * "N bertaut + M tanpa modul = X" harus sama dengan cacah tiket terbuka,
     * bahkan sesudah ada yang ditunda dan ada yang ditolak.
     */
    public function test_angka_katalog_cocok_dengan_papan_sesudah_tunda_dan_tolak(): void
    {
        $m = $this->modul('Finance');
        $a = $this->ajukan('Cocok A ' . Str::random(4));
        $b = $this->ajukan('Cocok B ' . Str::random(4));
        $c = $this->ajukan('Cocok C ' . Str::random(4));

        foreach ([$a, $b, $c] as $t) {
            $this->withSession($this->ekky())->post(route('it-tracker.triase', $t->id),
                ['jenjang' => 'S', 'bobot' => 2, 'prioritas_it' => 'P3', 'modul_id' => $m->id])->assertRedirect();
        }
        $this->svc->parkir($b->fresh(), 'DITUNDA', 'menunggu Finance', 'e2e-ekky');
        $this->svc->parkir($c->fresh(), 'DITOLAK', 'duplikat', 'e2e-ekky');
        $yatim = $this->ajukan('Tanpa modul ' . Str::random(4));

        $terbuka = ItTicket::whereNotIn('fase', ['SELESAI', 'DITOLAK'])->count();
        $nYatim  = ItTicket::whereNotIn('fase', ['SELESAI', 'DITOLAK'])->whereNull('modul_id')->count();

        $this->withSession($this->robi())->get(route('it-tracker.katalog', ['tampilan' => 'daftar']))->assertOk()
            ->assertSee(($terbuka - $nYatim) . ' bertaut + ' . $nYatim . ' tanpa modul = ' . $terbuka . ' tiket terbuka di papan')
            // yang ditunda masuk UTANG (2 tiket · 1 tunda), yang ditolak tidak lagi
            ->assertSee('2 tiket · 1 tunda')
            // dan tautan ke papan MEMBUKA parkiran, karena yang ditunda ikut dihitung
            // sebagai utang — papan yang menyembunyikannya akan tampak kurang dari
            // angka yang baru saja diklik
            ->assertSee('parkir=1');

        $this->assertSame(['SELESAI', 'DITOLAK'], \App\Services\ItTracker\KatalogModul::FASE_TERBUKA_KECUALI);
        $this->assertNull($yatim->fresh()->modul_id);
    }

    // ── Kendali negatif lintas halaman ────────────────────────────────────

    /**
     * Papan tak berubah perilakunya oleh katalog: tanpa ?modul= semua tiket
     * tetap tampil, dan penyaring modul yang menunjuk entri tak dikenal
     * MENYEBUT dirinya (bukan papan kosong tanpa sebab).
     */
    public function test_papan_tetap_utuh_dan_saringan_basi_menyebut_dirinya(): void
    {
        $m = $this->modul('Finance');
        $t = $this->ajukan('Utuh ' . Str::random(4));
        $this->withSession($this->ekky())->post(route('it-tracker.triase', $t->id),
            ['jenjang' => 'M', 'bobot' => 3, 'prioritas_it' => 'P2', 'modul_id' => $m->id])->assertRedirect();

        $this->withSession($this->ekky())->get(route('it-tracker.index'))->assertOk()
            ->assertSee($t->judul)->assertDontSee('Filter menyala');

        // tautan basi (entri katalog yang sudah tak ada) → jangan diam
        $this->withSession($this->ekky())->get(route('it-tracker.index', ['modul' => 999999]))->assertOk()
            ->assertSee('yang tidak ada di katalog')
            ->assertSee('Lepas filter modul');
    }

    /** Pencarian katalog yang tak menemukan apa pun menyebut alasannya — di KETIGA tampilan. */
    public function test_pencarian_kosong_menyebut_alasannya_di_semua_tampilan(): void
    {
        Artisan::call('it:seed-katalog', ['--env-guard' => 'local']);

        foreach (['daftar', 'kartu', 'matriks'] as $tampilan) {
            $this->withSession($this->ekky())
                ->get(route('it-tracker.katalog', ['q' => 'zzz-tak-ada-modul', 'tampilan' => $tampilan]))
                ->assertOk()
                ->assertSee('Tak ada modul yang cocok', false)
                ->assertSee('Hapus pencarian');
        }
    }
}
