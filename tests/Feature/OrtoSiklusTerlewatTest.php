<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — siklus terlewat (`missed_count`), tahap 3 dari prompt 4 Okt.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * "Terlewat 45 hari" terbaca sama untuk dua pasien yang keadaannya jauh
 * berbeda: yang berinterval 21 hari sudah melewatkan DUA kontrol, yang
 * berinterval 60 hari belum satu pun. Ambang hari (14 = "terlewat parah")
 * tidak bisa membedakan itu. Siklus bisa.
 *
 * Rumusnya satu dan statis — OrtCase::hitungSiklusTerlewat() — dipakai
 * model, tabel (stdClass dari query builder), panel baris, dan padanan
 * SQL-nya di OrtQuery::dasar(). Berkas ini mengunci tiga hal:
 *
 *   1. rumusnya, termasuk jepitan interval [21,60] dan cadangan +30 —
 *      PERSIS yang dipakai jatuh tempo, supaya "2 siklus" di layar dan
 *      jatuh tempo yang dihitung OrtUsulan tidak pernah saling membantah;
 *   2. padanan SQL-nya memilih baris yang sama dengan rumus PHP;
 *   3. layarnya menyebut siklus hanya saat ≥ SIKLUS_ESKALASI.
 *
 * ⚠ SENGAJA tidak ada kolom `missed_count`. Nilainya berubah tiap hari walau
 *   datanya diam — penyakit yang sama dengan visit_count_180d. Aritmetika
 *   murni dari dua kolom yang sudah ada.
 */
class OrtoSiklusTerlewatTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-SIKLUS';

    private string $placeId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_case') || ! Schema::hasColumn('ort_case', 'median_gap_days')) {
            $this->markTestSkipped('Jalankan SQL skema ortho + 2026_10_01_ort_tahap_c.sql dulu.');
        }
        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        $this->placeId = (string) array_key_first($peta);
    }

    // ── 1. Rumus ────────────────────────────────────────────────────────────

    /** @return array<string, array{int|null, int|null, int}> hari lalu · median · siklus */
    public static function kasusRumus(): array
    {
        return [
            '70 hari, median 30 → 2'               => [70, 30, 2],
            '70 hari, median 60 → 1'               => [70, 60, 1],
            '70 hari, tanpa median → +30 → 2'      => [70, null, 2],
            '70 hari, median 10 → dijepit 21 → 3'  => [70, 10, 3],
            '70 hari, median 90 → dijepit 60 → 1'  => [70, 90, 1],
            '20 hari, median 30 → 0 (belum lewat)' => [20, 30, 0],
            '30 hari persis, median 30 → 1'        => [30, 30, 1],
            'belum pernah datang → 0'              => [null, 30, 0],
        ];
    }

    /** @dataProvider kasusRumus */
    public function test_rumus_siklus(?int $hariLalu, ?int $median, int $harapan): void
    {
        $hariIni   = Carbon::parse('2026-10-04');
        $lastVisit = $hariLalu === null ? null : $hariIni->copy()->subDays($hariLalu)->toDateString();

        $this->assertSame($harapan, OrtCase::hitungSiklusTerlewat($lastVisit, $median, $hariIni));
    }

    /** Kunjungan di MASA DEPAN (data salah) tidak boleh jadi siklus negatif. */
    public function test_kunjungan_masa_depan_bukan_siklus_negatif(): void
    {
        $this->assertSame(0, OrtCase::hitungSiklusTerlewat('2026-12-01', 30, '2026-10-04'));
    }

    /** Interval = jepitan yang SAMA dengan OrtUsulan. Kalau ini berubah, jatuh tempo ikut. */
    public function test_interval_mengikuti_jepitan_irama(): void
    {
        $this->assertSame(OrtControl::USULAN_MAJU_HARI, OrtCase::intervalKontrol(null));
        $this->assertSame(OrtCase::IRAMA_MIN_HARI,      OrtCase::intervalKontrol(5));
        $this->assertSame(OrtCase::IRAMA_MAX_HARI,      OrtCase::intervalKontrol(200));
        $this->assertSame(36,                           OrtCase::intervalKontrol(36));
    }

    /** Metode instance hanya mendelegasi — satu rumus, bukan dua. */
    public function test_metode_instance_sama_dengan_statis(): void
    {
        $c = $this->pasien(70, 30);

        $this->assertSame(
            OrtCase::hitungSiklusTerlewat($c->last_visit_date->toDateString(), 30),
            $c->siklusTerlewat()
        );
        $this->assertSame(2, $c->siklusTerlewat());
    }

    // ── 2. Padanan SQL & 3. layar ───────────────────────────────────────────

    /**
     * ⭐ INTI: saringan "Terlewat 2× atau lebih" memilih persis baris yang
     * rumus PHP sebut ≥ 2 — tidak lebih, tidak kurang.
     */
    public function test_saringan_sql_sepakat_dengan_rumus_php(): void
    {
        $dua  = $this->pasien(70, 30);   // 2 siklus  → masuk
        $satu = $this->pasien(70, 60);   // 1 siklus  → tidak
        $tiga = $this->pasien(70, 10);   // jepit 21 → 3 → masuk

        $html  = $this->halaman(['status' => 'Terlewat 2× atau lebih']);
        $lolos = $this->barisTampil($html);

        $this->assertContains($this->idBaris($dua),  $lolos, '2 siklus harus lolos saringan.');
        $this->assertContains($this->idBaris($tiga), $lolos, '3 siklus (median dijepit ke 21) harus lolos.');
        $this->assertNotContains($this->idBaris($satu), $lolos, '1 siklus TIDAK boleh lolos — ambangnya siklus, bukan hari.');
    }

    /** Chip tabel menyebut siklus hanya saat ≥ eskalasi; panel baris memberi tanda "eskalasi". */
    public function test_layar_menyebut_siklus_saat_eskalasi(): void
    {
        $k = $this->pasien(70, 30);   // 2 siklus, jadwal sudah lewat

        $html = $this->halaman();

        /* Chip Status sudah dibuang bersama kolomnya (6 Okt 2026); kalimat
           "Terlewat N hari · N siklus" pindah ke TOOLTIP tanggal Last Visit.
           Yang dijaga tidak berubah: jumlah siklus wajib disebut saat sudah
           mencapai ambang eskalasi. */
        $this->assertMatchesRegularExpression('/Terlewat \d+ hari · 2 siklus/', $html,
            'Tooltip jatuh tempo harus menyebut jumlah siklus saat ≥ ' . OrtCase::SIKLUS_ESKALASI . '.');
        /* Tandanya pindah 3 Okt 2026. Dulu "· eskalasi" di daftar fakta panel;
           sekarang DUA tempat, dan keduanya lebih terbaca: ringkasan pola di
           kepala kolom KONTROL, plus badge di kolom Nama yang terlihat TANPA
           membuka panel — itu yang penting, karena baris inilah yang paling
           perlu ditangani lebih dulu. */
        $this->assertStringContainsString('Terlewat 2 siklus', $html,
            'Badge di kolom Nama harus menandai eskalasi tanpa perlu membuka panel.');

        // Ringkasan pola hidup di panel, dan panel dimuat belakangan.
        $this->assertStringContainsString('terlewat 2 siklus', $this->panel($k),
            'Ringkasan pola di kolom Kontrol harus menandai eskalasi.');
    }

    public function test_layar_diam_saat_belum_eskalasi(): void
    {
        $this->pasien(70, 60);   // 1 siklus → tidak disebut di chip

        $html = $this->halaman();

        $this->assertDoesNotMatchRegularExpression('/Terlewat \d+ hari · \d+ siklus/', $html,
            'Satu siklus bukan eskalasi — chip tetap "Terlewat N hari" saja.');
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    /**
     * Pasien yang kunjungan terakhirnya $hariLalu hari lalu, ber-median $median,
     * dengan satu kontrol TERBUKA yang jatuh temponya = kunjungan + interval
     * (jadi sudah lewat, dan muncul di tab List sebagai Terlewat).
     */
    private function pasien(int $hariLalu, ?int $median): OrtCase
    {
        $pid   = self::TANDA . '-' . Str::random(6);
        $last  = Carbon::today()->subDays($hariLalu);

        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji Siklus ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => $last->copy()->subDays(300)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'last_visit_date' => $last->toDateString(), 'median_gap_days' => $median,
            'control_count' => 1, 'visit_count' => 1, 'visit_count_180d' => 1,
        ]);

        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 2,
            'place_id' => $this->placeId, 'is_proposal' => 1,
            'sch_date' => $last->copy()->addDays(OrtCase::intervalKontrol($median))->toDateString(),
            'sch_source' => OrtControl::SCH_SISTEM, 'created_by' => 'uji',
        ]);

        return $kasus;
    }

    private function halaman(array $query = []): string
    {
        $gid = DB::table('group_path')->where('nama', 'Supervisor')->value('id');
        if (! $gid) {
            $this->markTestSkipped("Role 'Supervisor' tidak ada di DB ini.");
        }
        $node = DB::table('access_path')->where('link', 'tracking-ortho')->first(['id', 'pid']);
        foreach (array_filter([$node->id, $node->pid]) as $accessId) {
            if (! DB::table('group_access')->where('group_id', $gid)->where('access_id', $accessId)->exists()) {
                DB::table('group_access')->insert(['id' => (string) Str::uuid(), 'group_id' => $gid, 'access_id' => $accessId]);
            }
        }

        return $this->withSession([
            'isLoggedIn' => true, 'id' => 'uji-' . Str::random(8), 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Sales', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ])->get('/tracking-ortho?' . http_build_query(array_merge(['utama' => $this->placeId], $query)))
          ->assertOk()->getContent();
    }

    /** Nomor RM pasien uji yang tampil — nama dipotong .ort-clamp2, RM tidak. */
    /**
     * Baris SIAPA yang tampil — dikenali dari penanda barisnya.
     *
     * ⚠ Dulu ini memungut nomor rekam medis dari teks halaman. Itu berhenti
     *   bekerja 6 Okt 2026 karena dua hal sekaligus: kolom Nama merender
     *   `Str::title($r->patient_name)` sehingga tandanya berubah huruf, dan
     *   PID mentah yang selama ini menyelamatkannya hidup di panel baris —
     *   panel yang kini dimuat belakangan dan tidak lagi ada di HTML halaman.
     *
     * @return list<string> id kontrol
     */
    private function barisTampil(string $html): array
    {
        preg_match_all('~data-ort-row="([^"]+)"~', $html, $m);

        return array_values(array_unique($m[1]));
    }

    /** Id kontrol yang mewakili pasien ini di daftar kerja. */
    private function idBaris(OrtCase $kasus): string
    {
        return (string) OrtControl::where('ort_case_id', $kasus->id)->whereNull('closed_at')->value('id');
    }

    /** Isi panel baris — dimuat tersendiri sejak panel jadi lazy. */
    private function panel(OrtCase $kasus): string
    {
        return $this->halaman(['frag' => 'panel', 'kontrol' => $this->idBaris($kasus)]);
    }
}
