<?php

namespace Tests\Feature;

use App\Models\ItForm;
use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use App\Services\ItTracker\PhaseEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * it:seed-antrian (rencana §14.21–14.22) — uji terima memakai BERKAS ASLI
 * docs/rencana/seed-antrian-it-2026-09-04.psv. Akun pemohon/PIC yang tak ada
 * di DB lokal dibuat sekali-pakai (rollback bersama transaksi).
 * Kelas terpisah (batas ±17 uji ber-DB per proses).
 */
class ItTrackerSeedAntrianTest extends TestCase
{
    use DatabaseTransactions;

    private const PSV = 'docs/rencana/seed-antrian-it-2026-09-04.psv';
    private ItTrackerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);
        try {
            DB::select('SELECT seed_urut FROM it_ticket LIMIT 0');
        } catch (\Throwable) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_04_it_tracker_seed_urut.sql dulu.');
        }
        if (ItTicket::whereNotNull('seed_urut')->exists()) {
            $this->markTestSkipped('DB ini sudah berisi tiket seed — uji butuh DB tanpa seed.');
        }
        $this->sediakanAkun();
        // Kolom ke-18 PSV (§14.26) dicocokkan PERSIS ke it_modul.nama; kalau katalog
        // kosong, seeder memang BERHENTI. Jadi katalog disiapkan dulu — urutan yang
        // sama dipakai saat naik dev/prod: it:seed-katalog → it:seed-antrian.
        if (\App\Models\ItModul::count() < 37) {
            $this->artisan('it:seed-katalog', ['--env-guard' => 'local'])->run();
        }
    }

    /** akun sekali-pakai untuk setiap email/nama di PSV yang belum ada di DB ini */
    private function sediakanAkun(): void
    {
        $peran = ['reza@sweettreats.id' => 'IT', 'ikhsan@sweettreats.id' => 'Head IT', 'robi@stdc.id' => 'Executive',
            'creative@stdc.id' => 'Head Marketing', 'dellaps@sweettreats.id' => 'Finance', 'anisa@sweettreats.id' => 'Supervisor',
            'nisasweettreats29@gmail.com' => 'Admin Backup'];
        $nama = ['reza@sweettreats.id' => 'Reza Fauzi Ramadhan', 'ikhsan@sweettreats.id' => 'Muhamad Ikhsan', 'robi@stdc.id' => 'Robi',
            'creative@stdc.id' => 'Yovan', 'dellaps@sweettreats.id' => 'Della Puspita Sari', 'anisa@sweettreats.id' => 'Anisa Aulia',
            'nisasweettreats29@gmail.com' => 'Admin_Nisa'];
        foreach ($peran as $email => $role) {
            if (DB::table('users')->where('email', $email)->exists()) continue;
            if ($email === 'nisasweettreats29@gmail.com' && DB::table('users')->where('nama', 'Admin_Nisa')->exists()) continue;
            DB::table('users')->insert(['id' => 'uji-seed-' . Str::random(6), 'nama' => $nama[$email], 'email' => $email, 'password' => 'x', 'status' => 1,
                'group_id' => DB::table('group_path')->where('nama', $role)->value('id') ?: 'grp-uji', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function jalankan(string $psv = self::PSV, string $guard = 'local'): int
    {
        return $this->artisan('it:seed-antrian', ['berkas' => base_path($psv), '--env-guard' => $guard])->run();
    }

    /**
     * Jumlah tiket & jumlah yang aktif DIHITUNG dari berkasnya, tidak dipatok.
     *
     * Sebelum 9 Sep 2026 angkanya ditulis mati (34 dan 30). Berkas antrean ini
     * memang bertambah tiap ada permintaan baru — dua tiket audit disisipkan
     * sore itu dan lima asersi langsung merah, padahal seedernya benar. Uji yang
     * merah karena datanya bertambah mengajari orang mengabaikan warna merah.
     * Yang dijaga uji ini adalah SIFATNYA (idempoten, counter maju sekali,
     * nomor dari counter), bukan angkanya.
     */
    private function barisPsv(): array
    {
        $baris = array_slice(array_filter(array_map('trim', file(base_path(self::PSV)))), 1);
        $parkir = 0;
        foreach ($baris as $b) {
            $k = explode('|', $b);
            if (($k[12] ?? '') === 'ditunda') $parkir++;
        }
        return ['total' => count($baris), 'aktif' => count($baris) - $parkir, 'parkir' => $parkir];
    }

    private function urut(int $n): ItTicket
    {
        return $this->svc->muatRelasi(ItTicket::query())->where('seed_urut', $n)->firstOrFail();
    }

    private function sesi(string $groupName, string $uid): array
    {
        return ['isLoggedIn' => true, 'id' => $uid, 'group_id' => DB::table('group_path')->where('nama', $groupName)->value('id') ?: 'grp-uji',
            'group_name' => $groupName, 'name' => 'Uji', 'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time()];
    }

    /** Jalan 2× → sebanyak baris berkas, bukan dua kali lipat; counter maju sekali; nomor dari counter. */
    public function test_idempoten_dua_kali_jalan(): void
    {
        $n = $this->barisPsv()['total'];
        $lama = collect(['users', 'rop_requests', 'att_day', 'group_access'])->mapWithKeys(fn ($x) => [$x => DB::table($x)->count()]);
        $awal = ItTicket::count();
        $counterAwal = (int) DB::table('it_counter')->where('periode', '2026')->value('last_no');

        $this->assertSame(0, $this->jalankan());
        $this->assertSame(0, $this->jalankan());

        $this->assertSame($awal + $n, ItTicket::count());
        $this->assertSame($n, ItTicket::whereNotNull('seed_urut')->count());
        $this->assertSame($n, ItTicket::whereNotNull('seed_urut')->distinct()->count('nomor'));
        $this->assertSame($counterAwal + $n, (int) DB::table('it_counter')->where('periode', '2026')->value('last_no'), "counter maju tepat {$n} (bukan " . ($n * 2) . ')');
        $this->assertSame(sprintf('IT-2026-%03d', $counterAwal + 1), $this->urut(1)->nomor, 'nomor dari counter, bukan hardcode');
        foreach ($lama as $x => $n) $this->assertSame($n, DB::table($x)->count(), $x);
    }

    /** Pemohon tak ditemukan → berhenti menyebut barisnya; tak ada satu tiket pun lahir. */
    public function test_pemohon_tak_ditemukan_berhenti_menyebut_baris(): void
    {
        $psv = 'storage/app/uji-seed-rusak.psv';
        $isi = file(base_path(self::PSV));
        $isi[8] = str_replace('robi@stdc.id|Robi', 'tidak-ada@nowhere.test|Orang Hilang', $isi[8]); // baris 9 = urut 08
        file_put_contents(base_path($psv), implode('', $isi));
        try {
            $kode = \Illuminate\Support\Facades\Artisan::call('it:seed-antrian', ['berkas' => base_path($psv), '--env-guard' => 'local']);
            $out  = \Illuminate\Support\Facades\Artisan::output();
            $this->assertSame(1, $kode);
            $this->assertStringContainsString('baris 9', $out);
            $this->assertStringContainsString('tidak-ada@nowhere.test', $out);
            $this->assertStringContainsString('BERHENTI', $out);
            $this->assertSame(0, ItTicket::whereNotNull('seed_urut')->count(), 'berhenti = tidak setengah jalan');
        } finally {
            @unlink(base_path($psv));
        }
    }

    /** --env-guard salah → ditolak sebelum menyentuh DB. */
    public function test_env_guard_menolak_lingkungan_yang_tak_cocok(): void
    {
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('it:seed-antrian', ['berkas' => base_path(self::PSV), '--env-guard' => 'prod']));
        $this->assertStringContainsString('DITOLAK', \Illuminate\Support\Facades\Artisan::output());
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('it:seed-antrian', ['berkas' => base_path(self::PSV)]));
        $this->assertSame(0, ItTicket::whereNotNull('seed_urut')->count());
    }

    /** "Berapa hari menunggu" jujur dari masuk_at; heat DEV tetap 0 walau tua; UAT menunggu Robi memanas. */
    public function test_hari_menunggu_dan_panas_jujur(): void
    {
        $this->jalankan();
        $hari = fn (string $tgl) => Carbon::parse($tgl)->diffInDays(now());

        $w07 = PhaseEngine::waitOf($this->svc->viewTicket($this->urut(7)));
        $this->assertSame($hari('2026-08-13 13:24'), $w07['days']);
        $w04 = PhaseEngine::waitOf($this->svc->viewTicket($this->urut(4)));
        $this->assertSame($hari('2026-07-07 14:04'), $w04['days']);
        $this->assertGreaterThanOrEqual(50, $w04['days']);
        $this->assertSame(0, $w04['heat'], 'DEV = di tangan IT, tak memanas walau tua');

        $w08 = PhaseEngine::waitOf($this->svc->viewTicket($this->urut(8)));
        $this->assertSame('UAT', $this->urut(8)->fase);
        $this->assertSame($hari('2026-09-01 16:55'), $w08['days']);
        // memanas sesuai aturan (≥3 hari kuning, ≥7 merah) — bukan angka mati yang basi seiring waktu
        $this->assertTrue($w08['luar'], 'UAT menunggu Robi = di LUAR IT');
        $this->assertSame($w08['days'] >= 7 ? 2 : ($w08['days'] >= 3 ? 1 : 0), $w08['heat']);

        $t = $this->urut(7);
        $this->assertSame('2026-08-13 13:24', $t->created_at->format('Y-m-d H:i'));
        $fase = \App\Models\ItTicketFase::where('ticket_id', $t->id)->get();
        $this->assertCount(1, $fase, 'tepat satu baris fase, masuk_at dari berkas');
        $this->assertSame('2026-08-13 13:24', $fase->first()->masuk_at->format('Y-m-d H:i'));
    }

    /** 4 ditunda (12, 20, 21, 30) beralasan, lewat parkir() → audit_log; tak dihitung aktif; beban aktif 30, macet ≥3. */
    public function test_ditunda_dan_panel_beban(): void
    {
        $nAudit = DB::table('audit_log')->count();
        $this->jalankan();
        foreach ([12, 20, 21, 30] as $u) {
            $t = $this->urut($u);
            $this->assertSame('DITUNDA', $t->fase, "urut $u");
            $this->assertNotEmpty($t->alasan_parkir);
        }
        $this->assertSame('L', $this->urut(30)->jenjang, '30 sudah bertriase L·4·P3 lalu diparkir');
        $this->assertSame('P3', $this->urut(30)->prioritas_it);
        $this->assertGreaterThanOrEqual($nAudit + 4, DB::table('audit_log')->count(), 'parkir tercatat ke audit_log');

        $views = ItTicket::whereNotNull('seed_urut')->get()->map(fn ($t) => $this->svc->viewTicket($this->svc->muatRelasi(ItTicket::query())->find($t->id)))->all();
        $beban = $this->svc->ringkasanBeban($views);
        $this->assertSame($this->barisPsv()['aktif'], $beban['aktif']);
        $this->assertGreaterThanOrEqual(3, $beban['macet']);
    }

    /** Induk–anak (01 ← 02/03/26; 05 ← 26), deps (13→11, 22→20, 24→23, 29→28, 30→09), evaluator 01 = pemohon, insiden di DEV. */
    public function test_relasi_evaluator_dan_fase(): void
    {
        $this->jalankan();
        $t01 = $this->urut(1);
        $this->assertSame('EVALUASI', $t01->fase);
        $this->assertNotNull($t01->pemohon_user_id);
        $this->assertSame($t01->pemohon_user_id, $t01->evaluator_user_id, 'evaluator = HR (pemohon)');
        foreach ([2, 3] as $u) $this->assertSame($t01->id, $this->urut($u)->induk_id);
        $this->assertSame($this->urut(5)->id, $this->urut(26)->induk_id);
        foreach ([2, 3, 4, 25, 26, 27] as $u) $this->assertSame('DEV', $this->urut($u)->fase, "insiden $u di DEV");

        $deps = fn ($u) => ItForm::where('ticket_id', $this->urut($u)->id)->where('kode', 'FRM-IT-02')->value('payload')['deps'] ?? [];
        $this->assertSame([$this->urut(11)->nomor], $deps(13));
        $this->assertSame([$this->urut(20)->nomor], $deps(22));
        $this->assertSame([$this->urut(23)->nomor], $deps(24));
        $this->assertSame([$this->urut(28)->nomor], $deps(29));
        $this->assertSame([$this->urut(9)->nomor], $deps(30));

        // tiket di ANALISIS/UAT/EVALUASI TIDAK diisi FR/paraf/skenario palsu — lampu mati apa adanya
        $g09 = PhaseEngine::gateInfo($this->svc->viewTicket($this->urut(9)));
        $this->assertFalse($g09['ok']);
        $this->assertSame(0, DB::table('it_fr')->where('ticket_id', $this->urut(9)->id)->count());
        $this->assertNull(ItForm::where('ticket_id', $this->urut(8)->id)->where('kode', 'FRM-IT-05')->first());
        $this->assertSame('TRIASE', $this->urut(28)->fase);
        $this->assertNull($this->urut(28)->jenjang);
    }

    /** Penanda "[DRAF IT …]" baris 28–30 tetap tampil di detail & ekspor; pemohon 29 = Anisa (sementara). */
    public function test_penanda_draf_it_tampil(): void
    {
        $this->jalankan();
        $t28 = $this->urut(28);
        $ses = $this->sesi('Head IT', 'uji-headit-seed');
        $this->withSession($ses)->get(route('it-tracker.show', $t28->id))->assertOk()->assertSee('[DRAF IT — belum dikonfirmasi pemohon]');
        $this->withSession($ses)->get(route('it-tracker.ekspor', $t28->id))->assertOk()->assertSee('[DRAF IT — belum dikonfirmasi pemohon]');
        $this->assertSame('Anisa Aulia', DB::table('users')->where('id', $this->urut(29)->pemohon_user_id)->value('nama'));
        $this->assertStringContainsString('PEMOHON SEMENTARA', ItForm::where('ticket_id', $this->urut(29)->id)->where('kode', 'FRM-IT-01')->value('payload')['latar']);
    }

    /**
     * Papan bertab jenis permintaan (r3a, keputusan Ekky 5 Sep): S → Support,
     * M/L → Fitur / Modul, belum ditriase → tampil di KEDUA tab lagi.
     * Tab bekerja di sisi klien, jadi yang dijaga di sini penandanya: hilang penanda
     * = semua kartu jatuh ke tab yang salah tanpa suara.
     */
    public function test_papan_bertab_jenis_permintaan(): void
    {
        $this->jalankan();
        $html = $this->withSession($this->sesi('Head IT', 'uji-headit-seed'))
            ->get(route('it-tracker.index'))->assertOk()
            ->assertSee('Support')->assertSee('Fitur / Modul')
            ->assertSee('Belum ditriase')->getContent();

        // kartu membawa jenisnya; jumlah penanda = jumlah kartu aktif (30 aktif + 4 ditunda tak di papan)
        $this->assertSame($this->barisPsv()['aktif'], substr_count($html, 'data-kartu data-jenis='));
        // r3a: DUA tab; yang belum ditriase ikut dihitung di keduanya (13+7 dan 6+7),
        // dan catatan di kanan band menyebut hitungan gandanya — bukan disembunyikan
        $this->assertSame(2, substr_count($html, 'class="itk-tj" data-jenis'), 'dua tab jenis permintaan');
        $this->assertStringContainsString('tampil di kedua tab', $html);
        $this->assertStringContainsString('data-jenis="baru"', $html);
        $this->assertSame(6, substr_count($html, 'data-kartu data-jenis="support"'), 'enam tiket S (insiden)');
        $this->assertSame(17, substr_count($html, 'data-kartu data-jenis="fitur"'), 'tujuh belas tiket M/L (empat tiket stabilisasi §14.26 ikut)');
        $this->assertSame(7, substr_count($html, 'data-kartu data-jenis="baru"'), 'tujuh belum ditriase');

        // tahap di dalam kolom = tab (Development · Uji Internal · UAT · Go Live · Evaluasi)
        $this->assertGreaterThanOrEqual(5, substr_count($html, 'data-ft='));
        $this->assertStringContainsString('data-fasetab', $html);
        $this->assertStringNotContainsString('>S</a>', $html, 'chip filter S/M/L lama sudah dicabut');
        $this->assertStringNotContainsString('itk-jenjang', $html, 'badge jenjang turun dari kartu papan (mengulang tab jenis)');
        $this->assertStringNotContainsString('itk-prog', $html, 'chip fase turun dari kartu (mengulang kolom); angkanya pindah ke strip');
        $this->assertStringContainsString('itk-wait-prog', $html);
        $this->assertStringContainsString('itk-legenda-warna', $html, 'legenda lima saluran warna');

        // parkiran juga bertab (Ditunda · Ditolak · Selesai) dan kartunya berpenanda jenis
        $this->assertSame(3, substr_count($html, 'data-pkp="'), 'tiga panel status parkiran (Ditunda · Ditolak · Selesai)');
        $this->assertSame(4, substr_count($html, 'data-parkir data-jenis='), 'empat tiket ditunda dari seed');
        $this->assertSame(4, substr_count($html, 'data-parkir data-jenis="fitur"'), 'keempatnya M/L');
        $this->assertStringContainsString('data-pkp="DITOLAK"', $html);
    }

    /** Jalan ulang MEMPERBARUI judul/latar tapi TIDAK memundurkan tiket yang sudah bergerak di layar. */
    public function test_jalan_ulang_tak_memundurkan_fase(): void
    {
        $this->jalankan();
        $t13 = $this->urut(13);
        $this->svc->triase($t13, 'M', 3, 'P2', 'uji-headit-seed'); // di layar: TRIASE → ANTRE
        $this->svc->pindahFase($t13->fresh(), 'ANALISIS', 'uji-headit-seed');

        $this->assertSame(0, $this->jalankan());
        $t13 = $this->urut(13);
        $this->assertSame('ANALISIS', $t13->fase, 'berkas bilang TRIASE, layar sudah ANALISIS → dipertahankan');
        $this->assertSame($this->barisPsv()['total'], ItTicket::whereNotNull('seed_urut')->count());
        // yang belum bergerak tetap sinkron dengan berkas
        $this->assertSame('TRIASE', $this->urut(28)->fase);
    }
}
