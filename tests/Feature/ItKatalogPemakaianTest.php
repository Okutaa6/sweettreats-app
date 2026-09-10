<?php

namespace Tests\Feature;

use App\Models\ItModul;
use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * §14.26 — katalog 37 entri, taut tiket→modul dari berkas seed, dan kolom
 * PEMAKAIAN ("dipakai N× 7 hari terakhir").
 *
 * Yang dijaga di sini:
 *  1. katalog jalan dua kali tetap 37 (bukan 41), dua modul benar-benar PINDAH
 *     departemen — idempotensi harus memperbarui, bukan menggandakan;
 *  2. ⛔ nama modul di berkas seed yang tak ada di katalog → perintah BERHENTI
 *     menyebut barisnya; tak boleh membuat entri sendiri, tak boleh menebak,
 *     tak boleh melewati diam-diam;
 *  3. ⛔ "—" ≠ 0. Modul tanpa pemetaan tampil "—" (belum terukur); modul
 *     terpetakan yang sunyi tampil "0×" — menyamakan keduanya adalah kebohongan
 *     yang paling mudah dipercaya;
 *  4. angka pemakaian tersimpan WAJIB ditemani stempel waktunya.
 *
 * Kelas terpisah — batas ±17 uji ber-DB per proses.
 */
class ItKatalogPemakaianTest extends TestCase
{
    use DatabaseTransactions;

    private ItTrackerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ItTrackerService::class);
        try {
            DB::select('SELECT pakai_tabel, pakai_kolom, pakai_7hari, pakai_at FROM it_modul LIMIT 0');
        } catch (\Throwable) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_07_it_modul_pemakaian.sql dulu.');
        }
    }

    private function sesi(string $grup, string $uid): array
    {
        return ['isLoggedIn' => true, 'id' => $uid,
            'group_id' => DB::table('group_path')->where('nama', $grup)->value('id') ?: 'grp-uji',
            'group_name' => $grup, 'name' => 'Uji ' . $grup,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time()];
    }

    private function seedKatalog(): void
    {
        Artisan::call('it:seed-katalog', ['--env-guard' => 'local']);
    }

    // ── 1. katalog 37 entri, idempoten, dua pindah departemen ─────────────

    public function test_katalog_37_entri_dan_dua_pindah_departemen(): void
    {
        $this->seedKatalog();
        $this->seedKatalog();   // dua kali: harus 37, BUKAN 41

        $this->assertSame(37, ItModul::count());
        $status = ItModul::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $this->assertSame([25, 3, 9], [$status['hidup'], $status['dibangun'], $status['rencana']]);

        $jenis = ItModul::selectRaw('jenis, COUNT(*) n')->groupBy('jenis')->pluck('n', 'jenis');
        $this->assertSame(21, $jenis['Operasional']);
        $this->assertSame(8, $jenis['Laporan & Dashboard']);
        $this->assertSame(2, $jenis['Integrasi']);
        $this->assertSame(6, $jenis['Platform']);

        // empat entri baru §14.26
        foreach (['ORTHO — tracking & retensi' => ['Sales', 'rencana'], 'Akun Dokter' => ['Sales', 'dibangun'],
                  'Project Tracker' => ['IT', 'rencana'], 'Notifikasi' => ['IT', 'rencana']] as $nama => [$dept, $st]) {
            $m = ItModul::where('nama', $nama)->first();
            $this->assertNotNull($m, "entri baru $nama");
            $this->assertSame($dept, $m->departemen);
            $this->assertSame($st, $m->status);
        }

        // dua yang PINDAH — bukti terukur ada di catatannya, bukan sekadar dipindah
        foreach (['Persetujuan', 'Akun Saya'] as $nama) {
            $m = ItModul::where('nama', $nama)->firstOrFail();
            $this->assertSame('Operasional / HR', $m->departemen, "$nama pindah dari IT");
            $this->assertStringContainsString('PINDAH dari IT', (string) $m->catatan);
        }
    }

    // ── 2. berkas seed: kolom modul dicocokkan PERSIS ─────────────────────

    /** Nama modul yang tak ada di katalog → BERHENTI, sebut barisnya, tak menulis apa pun. */
    public function test_modul_tak_dikenal_menghentikan_seeder(): void
    {
        $this->seedKatalog();
        $awal = ItTicket::count();

        $psv = $this->berkas([
            ['201', 'Uji modul asing', 'inisiatif', '', '', '', 'S', '1', '', 'P2', 'DEV', '2026-09-01 09:00', 'aktif', '', '', '', 'uji', 'Modul Yang Tak Ada'],
        ]);
        $kode = Artisan::call('it:seed-antrian', ['berkas' => $psv, '--env-guard' => 'local']);
        $keluaran = Artisan::output();

        $this->assertSame(1, $kode, 'perintah gagal, bukan lanjut diam-diam');
        $this->assertStringContainsString('BERHENTI', $keluaran);
        $this->assertStringContainsString('baris 2', $keluaran, 'nomor barisnya disebut');
        $this->assertStringContainsString('Modul Yang Tak Ada', $keluaran);
        $this->assertSame($awal, ItTicket::count(), 'tak ada tiket yang terlanjur dibuat');
        $this->assertSame(37, ItModul::count(), '⛔ seeder TIDAK membuat entri katalog sendiri');
    }

    /**
     * Kolom modul terisi → modul_id tertaut; kolom kosong → tetap NULL (sah).
     *
     * Berjalan di DB manapun (dev/prod sudah berisi antrean nyata): seed_urut
     * 201/202 di luar rentang berkas asli (1..36 per 9 Sep 2026) supaya seeder
     * yang idempoten tak menimpa tiket sungguhan, dan angka katalog dijaga
     * sebagai SELISIH, bukan angka mutlak untuk papan kosong. Kalimat
     * rekonsiliasi hanya tampil bila ada tiket tanpa modul — maka yang tanpa
     * modul disemai lebih dulu sebagai patokan.
     */
    public function test_taut_modul_dari_berkas_dan_boleh_kosong(): void
    {
        $this->seedKatalog();
        $absensi = ItModul::where('nama', 'Absensi')->firstOrFail();
        $baris = fn (string $urut, string $judul, string $modul) =>
            [$urut, $judul, 'inisiatif', '', '', '', 'S', '1', '', 'P2', 'DEV', '2026-09-01 09:00', 'aktif', '', '', '', 'uji', $modul];

        // 1. tanpa modul → NULL, dan baris "Tanpa modul" katalog lahir/bertambah
        $this->assertSame(0, Artisan::call('it:seed-antrian', ['berkas' => $this->berkas([$baris('202', 'Uji tanpa modul', '')]), '--env-guard' => 'local']));
        $dua = ItTicket::where('seed_urut', 202)->firstOrFail();
        $this->assertNull($dua->modul_id, 'tiket tanpa modul memang sah — bukan ditebak');
        $patokan = $this->jumlahKatalog();
        $this->assertGreaterThanOrEqual(1, $patokan['yatim']);

        // 2. bermodul → tertaut, dan katalog menghitungnya di tempat yang benar
        $this->assertSame(0, Artisan::call('it:seed-antrian', ['berkas' => $this->berkas([$baris('201', 'Uji taut modul', 'Absensi')]), '--env-guard' => 'local']));
        $satu = ItTicket::where('seed_urut', 201)->firstOrFail();
        $this->assertSame($absensi->id, $satu->modul_id);

        $sesudah = $this->jumlahKatalog();
        $this->assertSame($patokan['bertaut'] + 1, $sesudah['bertaut'], 'tiket bermodul menambah yang bertaut');
        $this->assertSame($patokan['yatim'],       $sesudah['yatim'],   'baris Tanpa modul tak bertambah');
        $this->assertSame($patokan['terbuka'] + 1, $sesudah['terbuka'], 'terbuka di papan ikut bertambah satu');
        $this->assertSame($sesudah['terbuka'], $sesudah['bertaut'] + $sesudah['yatim'], 'penjumlahannya cocok');
    }

    // ── 3 & 4. kolom pemakaian ────────────────────────────────────────────

    /** Seeder menetapkan PEMETAAN (tabel+kolom), bukan angkanya. */
    public function test_seeder_menetapkan_pemetaan_bukan_angka(): void
    {
        $this->seedKatalog();

        $petaan = ItModul::whereNotNull('pakai_tabel')->get();
        $this->assertGreaterThanOrEqual(11, $petaan->count(), 'sebelas pemetaan §14.26 minimal');
        $this->assertSame('att_scan', ItModul::where('nama', 'Absensi')->value('pakai_tabel'));
        $this->assertSame('sys_complaint', ItModul::where('nama', 'Keluhan Sistem')->value('pakai_tabel'));
        $this->assertNull(ItModul::where('nama', 'Katalog Modul')->value('pakai_tabel'), 'yang tak terukur dibiarkan kosong');

        // Angka BUKAN urusan seeder — itu milik it:hitung-pemakaian. Diuji dengan
        // penanda: taruh angka mencolok, jalankan seeder lagi, angkanya harus utuh.
        // (Menguji "harus NULL" akan lulus/gagal tergantung apakah perintah hitung
        //  pernah jalan di DB ini — patokan yang tak bisa dipercaya.)
        $absensi = ItModul::where('nama', 'Absensi')->firstOrFail();
        $absensi->forceFill(['pakai_7hari' => 4321, 'pakai_at' => now()->subDays(3)])->save();
        $this->seedKatalog();
        $sesudah = $absensi->fresh();
        $this->assertSame(4321, $sesudah->pakai_7hari, 'seeder tak menyentuh angka pemakaian');
        $this->assertTrue($sesudah->pakai_at->isSameDay(now()->subDays(3)), 'stempel waktunya pun tak digeser seeder');
    }

    /** Perintah hitung menulis angka + stempel waktu; yang tak dipetakan tetap NULL. */
    public function test_hitung_pemakaian_menulis_angka_dan_stempel(): void
    {
        $this->seedKatalog();
        Artisan::call('it:hitung-pemakaian', ['--diam' => true]);
        $keluaran = Artisan::output();

        $absensi = ItModul::where('nama', 'Absensi')->firstOrFail();
        $this->assertNotNull($absensi->pakai_7hari, 'modul terpetakan mendapat angka (boleh 0)');
        $this->assertNotNull($absensi->pakai_at, '⚠️ angka tersimpan WAJIB berstempel waktu — supaya usianya kelihatan');
        $this->assertTrue($absensi->pakai_at->isToday());

        $tanpa = ItModul::where('nama', 'Katalog Modul')->firstOrFail();
        $this->assertNull($tanpa->pakai_7hari, 'yang tak dipetakan TIDAK dinolkan — nol berarti mati, itu beda');
        $this->assertNull($tanpa->pakai_at);

        $this->assertStringContainsString('belum punya pemetaan', $keluaran);
    }

    /** Tabel yang tak ada di lingkungan ini dilaporkan, bukan menjatuhkan perintah. */
    public function test_tabel_hilang_dilaporkan_bukan_galat(): void
    {
        $m = ItModul::create(['nama' => 'Uji Tabel Hilang ' . Str::random(4), 'departemen' => 'IT', 'jenis' => 'Platform',
            'status' => 'hidup', 'sejak_terbukti' => false, 'urutan' => 999,
            'pakai_tabel' => 'tabel_yang_tak_pernah_ada', 'pakai_kolom' => 'created_at']);

        $this->assertSame(0, Artisan::call('it:hitung-pemakaian', ['--diam' => true]));
        $this->assertStringContainsString('tidak ada di lingkungan ini', Artisan::output());
        $this->assertNull($m->fresh()->pakai_7hari, 'angkanya dibiarkan apa adanya, tidak dinolkan');
    }

    /** ⛔ Di layar: "—" untuk yang belum terukur, "0×" untuk yang terpetakan tapi sunyi. */
    public function test_layar_membedakan_belum_terukur_dari_nol(): void
    {
        $this->seedKatalog();
        Artisan::call('it:hitung-pemakaian', ['--diam' => true]);

        $html = $this->withSession($this->sesi('Head IT', 'uji-headit-pk'))
            ->get(route('it-tracker.katalog', ['tampilan' => 'daftar']))->assertOk()
            ->assertSee('DIPAKAI 7 HR')
            ->assertSee('belum terukur')
            ->getContent();

        // modul terpetakan yang sunyi menampilkan ANGKA nol, bukan "—"
        $keluhan = ItModul::where('nama', 'Keluhan Sistem')->firstOrFail();
        $this->assertNotNull($keluhan->pakai_7hari);
        $this->assertStringContainsString('itk-pakai sunyi', $html, 'nol punya rupa sendiri (amber), bukan pudar');
        $this->assertStringContainsString('itk-pakai takterukur', $html, '"—" punya rupa sendiri');
        $this->assertMatchesRegularExpression('/itk-pakai (?:sunyi|hidup)[^>]*>\s*<b class="num">\d+×/', $html);

        // stempel waktunya IKUT tampil — angka tersimpan boleh, asal usianya kelihatan
        $this->assertStringContainsString('per ' . now()->translatedFormat('j M'), $html);
    }

    /** KENDALI NEGATIF: papan tak berubah oleh kolom pemakaian. */
    public function test_papan_tak_terpengaruh(): void
    {
        $this->seedKatalog();
        Artisan::call('it:hitung-pemakaian', ['--diam' => true]);

        $this->withSession($this->sesi('Head IT', 'uji-headit-pk'))
            ->get(route('it-tracker.index'))->assertOk()
            ->assertDontSee('DIPAKAI 7 HR')
            ->assertDontSee('itk-pakai', false);
    }

    /** berkas PSV sekali-pakai: 18 kolom, dihapus di tearDown */
    private array $berkasDibuat = [];

    /** Kalimat rekonsiliasi katalog "N bertaut + M tanpa modul = T tiket terbuka di papan", dibaca sebagai ELEMEN dari halaman. */
    private function jumlahKatalog(): array
    {
        $html = $this->withSession($this->sesi('Head IT', 'uji-headit-pk'))
            ->get(route('it-tracker.katalog', ['tampilan' => 'daftar']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/(\d+) bertaut \+ (\d+) tanpa modul = (\d+) tiket terbuka di papan/', $html);
        preg_match('/(\d+) bertaut \+ (\d+) tanpa modul = (\d+) tiket terbuka di papan/', $html, $m);
        return ['bertaut' => (int) $m[1], 'yatim' => (int) $m[2], 'terbuka' => (int) $m[3]];
    }

    private function berkas(array $baris): string
    {
        $kepala = 'urut|judul|sumber|pemohon_email|pemohon_nama|pic_email|jenjang|bobot|prioritas_pemohon|'
            . 'prioritas_it|fase|masuk_at|status|alasan_tunda|parent_urut|deps_urut|latar|modul';
        $isi = $kepala . "\n" . implode("\n", array_map(fn ($b) => implode('|', $b), $baris)) . "\n";
        $path = storage_path('app/uji-psv-' . Str::random(8) . '.psv');
        file_put_contents($path, $isi);
        $this->berkasDibuat[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->berkasDibuat as $p) @unlink($p);
        parent::tearDown();
    }
}
