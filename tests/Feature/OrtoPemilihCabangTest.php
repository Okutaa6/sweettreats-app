<?php

namespace Tests\Feature;

use App\Services\Ortho\OrtBranches;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — siapa yang boleh memilih cabang utama, dan siapa yang tidak.
 *
 * TEMUAN YANG MELAHIRKAN BERKAS INI (audit HUB, 30 Sep 2026). Orang yang tidak
 * punya `raw_branch_id` dan tidak terdaftar di canSeeAllBranches() dipaku ke
 * cabang PERTAMA — Bintaro, 927 kasus aktif — dan pemilih cabangnya tidak
 * dirender sama sekali. Tanpa galat, tanpa penjelasan: mereka melihat satu dari
 * lima cabang dan mengira itu seluruh datanya. Kena empat orang, dan TIGA di
 * antaranya justru yang menguji modul ini (Head IT, IT, Supervisor/pemohon).
 *
 * Perbaikannya membuang pemeriksaan role di OrthoController::resolveBranch():
 * siapa pun yang sampai ke sana memang tidak punya cabang, dan mereka toh sudah
 * melihat satu cabang penuh — yang berubah cuma cabang MANA.
 *
 * Yang dijaga berkas ini ada DUA SISI, dan sisi kedua yang lebih penting:
 *   • yang tanpa cabang BISA memilih (temuan di atas tidak kembali), dan
 *   • yang PUNYA cabang tetap terkunci — `?utama=` yang diketik manual harus
 *     tetap ditolak. Tanpa pagar itu, perbaikan ini jadi lubang IDOR.
 * Plus satu kendali negatif: perbaikan ini TIDAK boleh diam-diam melonggarkan
 * canSeeAllBranches(), konstanta yang dipakai 47 tempat dan ikut membuka unggah
 * sidik jari, koreksi absen, periode gaji, dan petty cash lintas cabang.
 *
 * Diuji lewat rute HTTP sungguhan, bukan dengan memanggil resolveBranch() —
 * supaya yang terbukti adalah jalan yang benar-benar dilewati pengguna.
 *
 * Grant menu disemai sendiri di dalam transaksi (pola MenyemaiGrantItTracker):
 * uji ini menguji KEMAMPUAN peran, bukan siapa yang kebetulan ber-grant hari
 * ini. Kalau tidak, ia merah tiap kali keputusan akses berganti.
 */
class OrtoPemilihCabangTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string,array> peta cabang: place_id => ['nama'=>, 'branch_id'=>] */
    private array $peta = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_case')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }
        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }

        $this->peta = app(OrtBranches::class)->all();
        if (count($this->peta) < 2) {
            $this->markTestSkipped('Butuh minimal 2 cabang ber-omnicare_place_id.');
        }
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    private function sesi(string $groupName, ?string $rawBranchId = null): array
    {
        /*
         * Role yang tak ada di DB ini DILEWATI, bukan dianggap gagal.
         * Daftar role berbeda antar lingkungan — dev punya Head IT, IT, dan
         * Admin; DB pengembangan lokal tidak. Uji yang memaksakan keberadaan
         * role akan merah di tempat yang salah dan menyamarkan kegagalan yang
         * sebenarnya. Yang diuji adalah PERILAKU "tanpa cabang", dan itu tetap
         * terbukti lewat role yang memang ada di sini.
         */
        $gid = DB::table('group_path')->where('nama', $groupName)->value('id');
        if (! $gid) {
            $this->markTestSkipped("Role '{$groupName}' tidak ada di DB ini — diuji di lingkungan yang punya.");
        }
        $this->pastikanGrant($gid);

        return [
            'isLoggedIn'    => true,
            'id'            => 'uji-' . Str::random(8),
            'group_id'      => $gid,
            'group_name'    => $groupName,
            'name'          => 'Uji ' . $groupName,
            'raw_branch_id' => $rawBranchId,
            'branch_day'    => date('Y-m-d'),
            'expires_in'    => time() + 3600,
            'login_at'      => time(),
            'pa_touch'      => time(),
        ];
    }

    /** Grant node `tracking-ortho` + induknya, ikut rollback transaksi uji. */
    private function pastikanGrant(string $groupId): void
    {
        $node = DB::table('access_path')->where('link', 'tracking-ortho')->first(['id', 'pid']);
        if (! $node) {
            return;
        }

        foreach (array_filter([$node->id, $node->pid]) as $accessId) {
            $ada = DB::table('group_access')->where('group_id', $groupId)->where('access_id', $accessId)->exists();
            if (! $ada) {
                // group_access.id tak punya default — WAJIB diisi sendiri.
                DB::table('group_access')->insert([
                    'id' => (string) Str::uuid(), 'group_id' => $groupId, 'access_id' => $accessId,
                ]);
            }
        }
    }

    /**
     * Cabang utama yang SEDANG dilihat, dibaca dari halamannya.
     *
     * Dua bentuk, karena memang dua tampilan berbeda: yang boleh memilih dapat
     * tombol dropdown dengan baris aktif di menunya; yang terkunci dapat pil
     * biasa ber-title. Membaca keduanya membuat uji ini bicara tentang APA yang
     * dilihat pengguna, bukan tentang nilai variabel di dalam controller.
     */
    private function cabangUtama(string $html): ?string
    {
        if (preg_match('/ort-btabpick__item is-active.*?ort-btabpick__nama">([^<]+)/s', $html, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/title="Cabang utama Anda · ([^"]+)"/', $html, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * ⚠ Dicocokkan ke KELAS tombolnya, bukan ke `data-ort-btabpick-toggle`.
     *   Atribut itu juga muncul di dalam <script> partial-nya sebagai selektor
     *   (`querySelector('[data-ort-btabpick-toggle]')`), dan skrip itu selalu
     *   dirender — jadi mencocokkannya akan berbunyi "ada" bahkan untuk user
     *   terkunci. `ort-btab--pick` hanya lahir di markup tombolnya.
     */
    private function adaPemilih(string $html): bool
    {
        return str_contains($html, 'ort-btab ort-btab--pick');
    }

    /**
     * Kelompok "di cabang lain" — tombol pembuka DAN pil cabangnya harus berada
     * di dalam SATU pembungkus `.ort-btablain`, sementara pil cabang utama
     * tetap di luar.
     *
     * ⚠ Diperiksa lewat DOM, bukan cocok-string. Urutan atribut dan baris baru
     *   yang ditinggalkan Blade membuat regex hijau/merah untuk alasan yang
     *   bukan strukturnya.
     *
     * @return array{dalam:int, tombol:bool, utamaDiLuar:int}|null
     */
    private function strukturKelompok(string $html): ?array
    {
        $d = new \DOMDocument();
        libxml_use_internal_errors(true);
        $d->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();

        $x = new \DOMXPath($d);
        $bungkus = $x->query("//span[@class='ort-btablain']")->item(0);
        if (! $bungkus) {
            return null;
        }
        $strip = $x->query("//div[contains(@class,'ort-branchtabs')]")->item(0);

        /* ⚠ JANGAN contains(@class,'ort-btab'): ia juga cocok dengan
           `ort-btabpick` DAN `ort-btablain` — dua pembungkus, bukan pil. Yang
           dihitung di sini elemennya langsung: <a> pil cabang di dalam, dan
           penanda cabang utama (bintang / tombol dropdown) di luar. */
        return [
            'dalam'  => $x->query(".//a[contains(@class,'ort-btab ')]", $bungkus)->length,
            'tombol' => $x->query(".//button[contains(@class,'ort-btab--more')]", $bungkus)->length === 1,
            // Cabang utama: pil berbintang (user terkunci) atau tombol dropdown
            // (role lintas cabang). Keduanya WAJIB di luar pembungkus.
            'utamaDiDalam' => $x->query(
                ".//*[contains(@class,'ort-btab__star') or contains(@class,'ort-btab--pick')]", $bungkus
            )->length,
            'utamaDiLuar'  => $x->query(
                "//*[contains(@class,'ort-btab__star') or contains(@class,'ort-btab--pick')]"
            )->length,
        ];
    }

    /** @return array{0:string,1:string} [place_id, nama] cabang ke-$i */
    private function cabangKe(int $i): array
    {
        $pid = array_keys($this->peta)[$i];

        return [(string) $pid, (string) $this->peta[$pid]['nama']];
    }

    // ── Tanpa cabang → BOLEH memilih ────────────────────────────────────────

    /**
     * Inti temuannya. Supervisor tidak ada di canSeeAllBranches() dan tidak
     * punya cabang; sebelum perbaikan ia dipaku ke cabang pertama tanpa pemilih.
     *
     * @dataProvider roleTanpaCabang
     */
    public function test_role_tanpa_cabang_mendapat_pemilih(string $role): void
    {
        $html = $this->withSession($this->sesi($role, null))
            ->get('/tracking-ortho')->assertOk()->getContent();

        $this->assertTrue(
            $this->adaPemilih($html),
            "{$role} tanpa cabang harus mendapat pemilih cabang, bukan dipaku ke cabang pertama."
        );
    }

    public static function roleTanpaCabang(): array
    {
        // Empat yang terdampak + satu yang memang sudah boleh sejak awal,
        // supaya perbaikan ini terbukti tidak merusak yang lama.
        return [
            'Supervisor'  => ['Supervisor'],
            'Head IT'     => ['Head IT'],
            'IT'          => ['IT'],
            'Admin'       => ['Admin'],
            'Super Admin' => ['Super Admin'],
        ];
    }

    public function test_tanpa_cabang_bisa_berpindah_lewat_pemilih(): void
    {
        [$pid, $nama] = $this->cabangKe(1);

        $html = $this->withSession($this->sesi('Supervisor', null))
            ->get('/tracking-ortho?utama=' . $pid)->assertOk()->getContent();

        $this->assertSame($nama, $this->cabangUtama($html), 'Pilihan ?utama= harus dipakai.');
    }

    public function test_utama_ngawur_tidak_meledak(): void
    {
        [, $namaPertama] = $this->cabangKe(0);

        $html = $this->withSession($this->sesi('Supervisor', null))
            ->get('/tracking-ortho?utama=99999-tidak-ada')->assertOk()->getContent();

        $this->assertSame(
            $namaPertama, $this->cabangUtama($html),
            'Nilai ?utama= yang tidak dikenal harus jatuh ke cabang pertama, bukan 500 atau halaman kosong.'
        );
    }

    // ── Punya cabang → TETAP terkunci (pagar terpenting) ────────────────────

    /**
     * ⭐ Kendali negatif terpenting. Kalau ini merah, perbaikan pemilih cabang
     *    sudah berubah jadi lubang IDOR: Sales cabang A bisa membaca daftar
     *    pasien cabang B cuma dengan mengetik ?utama= di bilah alamat.
     *
     * @dataProvider roleBercabang
     */
    public function test_user_bercabang_tidak_bisa_menembus_lewat_utama(string $role): void
    {
        [$pidRumah, $namaRumah] = $this->cabangKe(0);
        [$pidLain]              = $this->cabangKe(1);

        $html = $this->withSession($this->sesi($role, $this->peta[$pidRumah]['branch_id']))
            ->get('/tracking-ortho?utama=' . $pidLain)->assertOk()->getContent();

        $this->assertSame(
            $namaRumah, $this->cabangUtama($html),
            "{$role} ber-cabang harus TETAP di cabangnya walau mengetik ?utama= cabang lain."
        );
    }

    /**
     * Mereka memang harus terkunci — bukan kelalaian, melainkan rancangannya:
     * "siapa yang menagih" adalah pertanyaan per cabang.
     *
     * @dataProvider roleBercabang
     */
    public function test_user_bercabang_tidak_mendapat_pemilih(string $role): void
    {
        [$pidRumah] = $this->cabangKe(0);

        $html = $this->withSession($this->sesi($role, $this->peta[$pidRumah]['branch_id']))
            ->get('/tracking-ortho')->assertOk()->getContent();

        $this->assertFalse(
            $this->adaPemilih($html),
            "{$role} terkunci cabang tidak boleh diberi pemilih — afordansi yang ujungnya ditolak server."
        );
    }

    public static function roleBercabang(): array
    {
        return ['Front Office' => ['Front Office'], 'Sales' => ['Sales']];
    }

    // ── Kendali negatif konstanta global ────────────────────────────────────

    /**
     * ⭐ Perbaikan ini TIDAK boleh diam-diam menambah siapa pun ke
     *    canSeeAllBranches(). Konstanta itu dipakai 47 tempat dan ikut membuka
     *    Upload Data fingerprint, Mapping Karyawan, Koreksi Absen, Periode
     *    Gaji, dan petty cash lintas cabang — jauh di luar urusan ortho.
     *
     * @dataProvider roleTanpaCabang
     */
    public function test_konstanta_lintas_cabang_tidak_dilonggarkan(string $role): void
    {
        session(['group_name' => $role]);

        $sudahBoleh = in_array($role, ['Super Admin', 'Admin General', 'Admin Backup', 'Finance', 'Konsultan Finance'], true);

        $this->assertSame(
            $sudahBoleh, canSeeAllBranches(),
            "canSeeAllBranches() untuk {$role} berubah — perbaikan pemilih cabang ortho tidak boleh menyentuh konstanta ini."
        );
    }

    // ── Kelompok "di cabang lain" ───────────────────────────────────────────

    /**
     * ⭐ Tombol pembuka dan cabang-cabangnya satu kesatuan.
     *
     * Sebelum 5 Okt 2026 ketiganya pil sejajar di flex row yang sama dengan pil
     * cabang utama — saat strip dibuka, "Sunter/Cinere/Pamulang" terbaca setara
     * dengan "Bintaro", padahal mereka milik tombol di kirinya dan isinya
     * lihat-saja.
     */
    public function test_cabang_lain_dibungkus_jadi_satu_kelompok(): void
    {
        $html = $this->withSession($this->sesi('Supervisor'))
            ->get('/tracking-ortho?' . http_build_query(['utama' => $this->cabangKe(0)[0]]))
            ->assertOk()->getContent();

        $s = $this->strukturKelompok($html);
        if ($s === null) {
            $this->markTestSkipped('Cabang utama ini tidak punya pasien di cabang lain — stripnya memang tidak dirender.');
        }

        $this->assertTrue($s['tombol'], 'Tombol pembuka wajib DI DALAM pembungkus.');
        $this->assertGreaterThan(0, $s['dalam'], 'Pil cabang lain wajib DI DALAM pembungkus.');
    }

    /** Pil cabang utama TIDAK ikut terbungkus — ia bukan bagian kelompok ini. */
    public function test_cabang_utama_tetap_di_luar_kelompok(): void
    {
        $html = $this->withSession($this->sesi('Supervisor'))
            ->get('/tracking-ortho?' . http_build_query(['utama' => $this->cabangKe(0)[0]]))
            ->assertOk()->getContent();

        $s = $this->strukturKelompok($html);
        if ($s === null) {
            $this->markTestSkipped('Cabang utama ini tidak punya pasien di cabang lain.');
        }

        $this->assertSame(0, $s['utamaDiDalam'],
            'Cabang utama TIDAK boleh ikut terbungkus — ia bukan bagian kelompok "di cabang lain".');
        $this->assertGreaterThan(0, $s['utamaDiLuar'],
            'Prasyarat: penanda cabang utama (bintang / dropdown) memang dirender.');
    }
}

