<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — kolom Jadwal adalah TANGGAL JATUH TEMPO, bukan tanggal janji.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * AMBIGUITAS YANG MELAHIRKAN BERKAS INI (1 Okt 2026)
 * ────────────────────────────────────────────────────────────────────────────
 * Brief sudah mengatakannya sejak awal — kamus istilah: "SCH · Jadwal ·
 * tanggal pasien SEHARUSNYA datang kontrol", dan "RSV · Reservasi · kosong =
 * pasien belum booking". Tapi implementasinya menyimpang jadi tanggal janji:
 * dijepit supaya tidak pernah di masa lalu, dipagar hari praktik dokter dan
 * kapasitas, lalu ditawarkan lewat tombol sekali klik.
 *
 * Akibatnya keterlambatan disembunyikan DUA KALI, dan dua-duanya dijaga di
 * berkas ini:
 *
 *   1. DI DATA — lantai `MIN_MAJU_HARI` mendorong setiap jatuh tempo yang
 *      sudah lewat ke `hari ini + 3`.
 *   2. DI LAYAR — baris ber-`is_proposal` dikecualikan dari chip "Terlewat"
 *      dan dari kartu Terlewat, jadi sekalipun tanggalnya lewat ia tetap
 *      berbunyi "Usulan jadwal".
 *
 * Terukur di DB pengembangan: 26 dari 39 pasien aktif jatuh temponya sudah
 * lewat, rata-rata 43 hari, terlama 279 hari. Tidak satu pun terlihat.
 *
 * ⚠ Dan lantai itu pula yang melahirkan 651 usulan di satu tanggal yang
 *   dilaporkan audit HUB. Uji "menyebar sendiri" di bawah memperagakan ulang:
 *   tanpa lantai dan TANPA pagar kapasitas apa pun, jatuh tempo menyebar
 *   karena diturunkan dari kunjungan terakhir masing-masing pasien.
 */
class OrtoJatuhTempoTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-JTEMPO';

    private string $placeId;
    private string $branchId;
    private string $doctorRef = 'UJI-JTEMPO-DOC';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_control')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }
        if (! Schema::hasColumn('ort_case', 'gap_spread_pct')) {
            $this->markTestSkipped('Jalankan database/sql/2026_10_02_ort_sebaran_irama.sql dulu.');
        }
        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        $this->placeId  = (string) array_key_first($peta);
        $this->branchId = (string) $peta[$this->placeId]['branch_id'];
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    /** Dokter yang praktik HANYA Rabu — meniru drg. Andy di Kalideres. */
    private function dokterPraktikRabuSaja(): void
    {
        $docId = 'uji-jtempo-doc-' . Str::random(6);
        DB::table('raw_doctor')->insert([
            'id' => $docId, 'name' => 'drg. Uji Jatuh Tempo',
            'omnicare_doc_id' => $this->doctorRef, 'is_active' => 1,
        ]);
        DB::table('raw_doctor_schedule')->insert([
            'id' => (string) Str::uuid(), 'raw_doctor_id' => $docId,
            'raw_branch_id' => $this->branchId, 'day' => 3,
            'start_hour' => '15:00:00', 'end_hour' => '22:00:00', 'type' => 'work',
        ]);
    }

    /**
     * Pasien dengan satu kunjungan tertutup $kunjunganLalu hari yang lalu,
     * lalu satu baris terbuka yang jatuh temponya dihitung sistem.
     */
    private function pasien(int $kunjunganLalu, ?int $iramaHari = null): OrtControl
    {
        $pid     = self::TANDA . '-' . Str::random(6);
        $hariIni = Carbon::today();

        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji JTempo ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => $hariIni->copy()->subDays(800)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF, 'doctor_ref_id' => $this->doctorRef,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
            'median_gap_days' => $iramaHari,
            'last_visit_date' => $hariIni->copy()->subDays($kunjunganLalu)->toDateString(),
        ]);

        $tglKunjungan = $hariIni->copy()->subDays($kunjunganLalu)->toDateString();
        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => $tglKunjungan, 'sch_source' => OrtControl::SCH_REKAM_MEDIS,
            'visit_date' => $tglKunjungan, 'visit_source' => 'rekam_medis',
            'visit_confidence' => 'pasti', 'outcome' => OrtControl::OUT_HADIR,
            'closed_at' => now(), 'closed_by' => 'uji', 'created_by' => 'uji',
        ]);

        $jatuhTempo = OrtControl::tanggalUsulan(Carbon::parse($tglKunjungan), $iramaHari);

        return OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 2,
            'place_id' => $this->placeId, 'is_proposal' => 1,
            'sch_date' => $jatuhTempo->toDateString(),
            'sch_source' => OrtControl::SCH_SISTEM, 'created_by' => 'sistem',
        ]);
    }

    private function halaman(array $query = []): string
    {
        $gid = DB::table('group_path')->where('nama', 'Supervisor')->value('id');
        if (! $gid) {
            $this->markTestSkipped("Role 'Supervisor' tidak ada di DB ini.");
        }

        $node = DB::table('access_path')->where('link', 'tracking-ortho')->first(['id', 'pid']);
        foreach (array_filter([$node->id, $node->pid]) as $accessId) {
            $ada = DB::table('group_access')->where('group_id', $gid)->where('access_id', $accessId)->exists();
            if (! $ada) {
                DB::table('group_access')->insert([
                    'id' => (string) Str::uuid(), 'group_id' => $gid, 'access_id' => $accessId,
                ]);
            }
        }

        $url = '/tracking-ortho?' . http_build_query(array_merge(['utama' => $this->placeId], $query));

        return $this->withSession([
            'isLoggedIn' => true, 'id' => 'uji-jtempo', 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Sales', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ])->get($url)->assertOk()->getContent();
    }

    // ── 1 · Lapis pertama: tanggalnya sendiri ───────────────────────────────

    /** ⭐ Kendali langsung atas lantai MIN_MAJU_HARI yang sudah dibuang. */
    public function test_jatuh_tempo_boleh_di_masa_lalu(): void
    {
        $tempo = OrtControl::tanggalUsulan(Carbon::today()->subDays(60), 30);

        $this->assertSame(
            Carbon::today()->subDays(30)->toDateString(), $tempo->toDateString(),
            'Kunjungan 60 hari lalu + irama 30 = jatuh tempo 30 hari LALU, bukan hari ini + 3.'
        );
        $this->assertTrue($tempo->isPast(), 'Dan itu memang di masa lalu.');
    }

    /** Pasien yang belum telat tetap dapat tanggal di masa depan. */
    public function test_pasien_belum_telat_jatuh_temponya_di_masa_depan(): void
    {
        $tempo = OrtControl::tanggalUsulan(Carbon::today()->subDays(10), 30);

        $this->assertSame(Carbon::today()->addDays(20)->toDateString(), $tempo->toDateString());
    }

    /** ⭐ Pagar jadwal benar-benar gugur: Senin dan hari dokter libur dibiarkan. */
    public function test_jatuh_tempo_boleh_jatuh_hari_tutup_dan_hari_dokter_libur(): void
    {
        $this->dokterPraktikRabuSaja();

        // Dicari kunjungan yang jatuh temponya persis hari Senin.
        for ($i = 0; $i < 7; $i++) {
            $kunjungan = Carbon::today()->subDays(10 + $i);
            if (OrtControl::tanggalUsulan($kunjungan, 30)->dayOfWeekIso === 1) {
                break;
            }
        }

        $tempo = OrtControl::tanggalUsulan($kunjungan, 30);

        $this->assertSame(1, $tempo->dayOfWeekIso, 'Prasyarat: jatuh temponya memang Senin.');
        $this->assertNotSame(
            3, $tempo->dayOfWeekIso,
            'Dan bukan Rabu — satu-satunya hari dokter ini praktik. Keduanya dibiarkan apa adanya.'
        );
    }

    /**
     * ⭐ PERAGAAN ULANG 651.
     *
     * Dua puluh pasien dengan kunjungan terakhir di hari berbeda-beda. Tanpa
     * lantai dan tanpa pagar kapasitas apa pun, jatuh temponya menyebar
     * sendiri — karena diturunkan dari kunjungan masing-masing, bukan dari
     * hari ini.
     */
    public function test_jatuh_tempo_menyebar_sendiri_tanpa_pagar_kapasitas(): void
    {
        $tanggal = [];
        for ($i = 0; $i < 20; $i++) {
            // Semuanya SUDAH telat, 40..59 hari sejak kunjungan terakhir —
            // persis populasi yang dulu menumpuk di satu tanggal.
            $tanggal[] = OrtControl::tanggalUsulan(Carbon::today()->subDays(40 + $i), 30)->toDateString();
        }

        $unik = array_count_values($tanggal);

        $this->assertCount(20, $unik, 'Dua puluh pasien, dua puluh tanggal berbeda.');
        $this->assertSame(1, max($unik), 'Nol penumpukan — tanpa satu pun pagar kapasitas.');
    }

    // ── 2 · Lapis kedua: layarnya ───────────────────────────────────────────

    /** ⭐ Jatuh tempo yang lewat berchip Terlewat, bukan "Usulan jadwal". */
    public function test_jatuh_tempo_lewat_berchip_terlewat(): void
    {
        $ctl = $this->pasien(kunjunganLalu: 60, iramaHari: 30);

        $this->assertTrue($ctl->sch_date->isPast(), 'Prasyarat: tanggalnya memang sudah lewat.');
        $this->assertSame(1, (int) $ctl->is_proposal, 'Prasyarat: tanggalnya dihitung sistem.');

        $html = $this->halaman(['q' => self::TANDA]);

        $this->assertStringContainsString('Terlewat 30 hari', $html);
        $this->assertStringNotContainsString(
            'Usulan jadwal', $html,
            'Chip "Usulan jadwal" sudah dibuang — ia dulu yang membungkam keterlambatan.'
        );
    }

    /** ⭐ Dan ia ikut terhitung di KARTU Terlewat, bukan cuma di chip. */
    public function test_jatuh_tempo_lewat_terhitung_di_kartu(): void
    {
        $sebelum = app(OrtQuery::class)->kartu($this->placeId)['terlewat'];

        $this->pasien(kunjunganLalu: 60, iramaHari: 30);

        $this->assertSame(
            $sebelum + 1, app(OrtQuery::class)->kartu($this->placeId)['terlewat'],
            'Kartu dan chip harus menghitung populasi yang sama.'
        );
    }

    /** Sub-tab "Terlewat" menampilkan baris yang sama dengan angka kartunya. */
    public function test_sub_tab_terlewat_sepakat_dengan_kartu(): void
    {
        $this->pasien(kunjunganLalu: 60, iramaHari: 30);

        $q       = app(OrtQuery::class);
        $kartu   = $q->kartu($this->placeId)['terlewat'];
        $urgensi = $q->hitungUrgensi($this->placeId)['terlewat'];

        $this->assertSame(
            $kartu, $urgensi,
            'Kartu Terlewat dan sub-tab Terlewat dulu memakai dua definisi berbeda.'
        );
    }

    /** Baris yang jatuh temponya hari ini ikut terhitung "Hari ini". */
    public function test_jatuh_tempo_hari_ini_terhitung(): void
    {
        $sebelum = app(OrtQuery::class)->hitungUrgensi($this->placeId)['hari-ini'];

        $this->pasien(kunjunganLalu: 30, iramaHari: 30);

        $this->assertSame(
            $sebelum + 1, app(OrtQuery::class)->hitungUrgensi($this->placeId)['hari-ini'],
            'Jatuh tempo hari ini adalah pekerjaan hari ini, siapa pun yang menuliskan tanggalnya.'
        );
    }

    // ── 3 · Tombol ──────────────────────────────────────────────────────────

    /** ⭐ Tidak ada lagi yang perlu "dikonfirmasi" dari sebuah pengingat. */
    public function test_tidak_ada_tombol_konfirmasi_jadwal(): void
    {
        $this->pasien(kunjunganLalu: 10, iramaHari: 30);

        $html = $this->halaman(['q' => self::TANDA]);

        $this->assertStringNotContainsString('Konfirmasi jadwal', $html);
        $this->assertStringContainsString('Catat Follow-up', $html, 'Anak tangga pertamanya menelepon.');
    }

    public function test_tangga_tombol_model_dan_tabel_sepakat(): void
    {
        $ctl = $this->pasien(kunjunganLalu: 10, iramaHari: 30);

        $this->assertSame('follow_up', $ctl->tindakanBerikutnya());

        $ctl->update(['fu_date' => Carbon::today()->toDateString(), 'fu_by' => 'Uji Sales']);
        $this->assertSame('buat_reservasi', $ctl->fresh()->tindakanBerikutnya());
    }

    // ── 4 · Yang TIDAK berubah ──────────────────────────────────────────────

    /** Tanggal yang DIKETIK Sales tetap tidak boleh di masa lalu. */
    public function test_tanggal_ketikan_sales_tetap_dijaga(): void
    {
        $ctl = $this->pasien(kunjunganLalu: 10, iramaHari: 30);

        $gid = DB::table('group_path')->where('nama', 'Supervisor')->value('id');
        if (! $gid) {
            $this->markTestSkipped("Role 'Supervisor' tidak ada di DB ini.");
        }

        $this->withSession([
            'isLoggedIn' => true, 'id' => 'uji-jtempo', 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Sales', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ])->post(route('ortho.geser', $ctl->id), [
            'tanggal' => Carbon::today()->subDay()->toDateString(),
        ]);

        $this->assertSame(
            1, (int) $ctl->fresh()->is_proposal,
            'Geser ke masa lalu harus ditolak — menggeser PENGINGAT ke belakang tidak ada gunanya.'
        );
    }

    /** `MIN_MAJU_HARI` tidak dihapus, cuma berhenti dipakai jalur hitung. */
    public function test_jeda_minimum_masih_ada_untuk_input_manual(): void
    {
        $this->assertSame(3, OrtControl::MIN_MAJU_HARI);
    }
}
