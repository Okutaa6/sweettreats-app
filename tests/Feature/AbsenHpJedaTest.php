<?php

namespace Tests\Feature;

use App\Http\Controllers\Absensi\AkunAbsenController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Absen HP — pulang sesaat sesudah masuk bukan lagi jebakan.
 *
 * Diagnosis 6 Sep 2026: sesudah masuk tercatat tombol langsung jadi "Pulang";
 * ketukan kedua = pulang 6–65 detik kemudian; reconciler melipatnya → single_scan;
 * pulang sungguhan sore harinya DITOLAK dan orangnya tak bisa memperbaiki sendiri.
 *
 * Keputusan Ekky: TIDAK ada penghalang. Dua peringatan yang bisa dilanjut, dan
 * pulang ≤5 menit boleh DITIMPA pulang berikutnya. Diuji dengan akun sungguhan
 * lewat POST /akun/absen, bukan memanggil service — supaya yang terbukti adalah
 * jalan yang dilewati jempol orang.
 *
 * Waktu dikendalikan Carbon::setTestNow pada hari jauh di depan, jadi tak
 * bertabrakan dengan absen nyata siapa pun. Foto: JPEG palsu (magic bytes saja
 * yang diperiksa simpanFoto). Berkas yang lahir dihapus di tearDown.
 */
class AbsenHpJedaTest extends TestCase
{
    use DatabaseTransactions;

    private const HARI = '2027-03-02';   // Selasa — hari kerja GS (libur Senin)

    private object $baku;        // aturan baku ber-jam_pulang
    private object $fleksibel;
    private string $cabang;
    private array $fotoSebelum = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = (string) DB::table('raw_branch')->value('id');
        if (! $this->cabang) $this->markTestSkipped('Tak ada cabang.');

        $aktif = fn (string $tipe) => DB::table('users as u')
            ->join('att_user_rule as ur', 'ur.user_id', '=', 'u.id')
            ->join('att_work_rule as wr', 'wr.id', '=', 'ur.rule_id')
            ->where('u.status', 1)->where('wr.tipe', $tipe)
            ->where('ur.effective_from', '<=', self::HARI)
            ->where(fn ($q) => $q->whereNull('ur.effective_to')->orWhere('ur.effective_to', '>=', self::HARI))
            // jam_pulang hidup di BARIS HARI, bukan di aturan — cari yang hari itu
            // (Selasa) bukan libur dan punya jam pulang.
            ->when($tipe === 'baku', fn ($q) => $q
                ->join('att_work_rule_day as d', 'd.rule_id', '=', 'wr.id')
                ->where('d.day', Carbon::parse(self::HARI)->dayOfWeek)
                ->where('d.libur', 0)->whereNotNull('d.jam_pulang'))
            ->first(['u.id', 'u.nama', 'u.group_id', 'u.raw_branch_id']);

        $b = $aktif('baku');
        $f = $aktif('fleksibel');
        if (! $b || ! $f) $this->markTestSkipped('Butuh satu akun baku ber-jam_pulang dan satu fleksibel.');
        if (jamPulangTerjadwal($b->id, Carbon::parse(self::HARI)) === null) {
            $this->markTestSkipped('Akun baku terpilih tak punya jam pulang pada ' . self::HARI . ' (libur?).');
        }
        $this->baku = $b;
        $this->fleksibel = $f;

        $this->fotoSebelum = glob(storage_path('app/att_selfie/*.jpg')) ?: [];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (array_diff(glob(storage_path('app/att_selfie/*.jpg')) ?: [], $this->fotoSebelum) as $f) @unlink($f);
        parent::tearDown();
    }

    private function sesi(object $u): array
    {
        return [
            'isLoggedIn' => true, 'id' => $u->id, 'group_id' => $u->group_id, 'group_name' => 'Uji',
            'name' => $u->nama, 'raw_branch_id' => $u->raw_branch_id, 'branch_day' => date('Y-m-d'),
            'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time(),
        ];
    }

    private function absen(object $u, string $jenis, string $jam, array $extra = [])
    {
        Carbon::setTestNow(Carbon::parse(self::HARI . ' ' . $jam));

        return $this->withSession($this->sesi($u))->withHeaders(['Accept' => 'application/json'])
            ->post('/akun/absen', [
                'jenis'         => $jenis,
                'foto_base64'   => 'data:image/jpeg;base64,' . base64_encode("\xFF\xD8" . str_repeat('x', 64)),
                'branch_manual' => $this->cabang,   // IP uji tak dikenal → wajib pilih cabang
            ] + $extra);
    }

    private function buktiPulang(object $u)
    {
        return DB::table('att_hp_bukti')->where('user_id', $u->id)->where('jenis', 'pulang')
            ->where('taken_at', '>=', self::HARI . ' 06:00:00')->where('taken_at', '<', '2027-03-03 06:00:00')
            ->get();
    }

    // ───────────────────────── skenario ketukan ganda ─────────────────────────

    public function test_pulang_6_detik_sesudah_masuk_ditolak_tanpa_confirm_dengan_kalimat_yang_membedakan(): void
    {
        $this->absen($this->baku, 'masuk', '08:00:00')->assertStatus(200)->assertJson(['ok' => true]);

        $res = $this->absen($this->baku, 'pulang', '08:00:06');

        $res->assertStatus(422)->assertJson(['ok' => false, 'need_confirm' => true]);
        $pesan = $res->json('pesan');
        $this->assertStringContainsString('PULANG', $pesan);
        $this->assertStringContainsString('bukan mengulang masuk', $pesan,
            'Kalimatnya wajib menjawab ketakutan yang sebenarnya: "masuk-ku tadi gagal".');
        $this->assertSame(0, $this->buktiPulang($this->baku)->count(), 'Belum boleh tercatat tanpa confirm.');
    }

    public function test_dengan_confirm_tercatat_lalu_pulang_sungguhan_menimpa_bukan_ditolak(): void
    {
        $this->absen($this->baku, 'masuk', '08:00:00')->assertStatus(200);
        $this->absen($this->baku, 'pulang', '08:00:06', ['confirm' => '1'])->assertStatus(200)->assertJson(['ok' => true]);

        $awal = $this->buktiPulang($this->baku);
        $this->assertSame(1, $awal->count());
        $this->assertSame(self::HARI . ' 08:00:06', $awal[0]->taken_at);

        // +8 jam: pulang sungguhan. Sebelum perbaikan ini DITOLAK "sudah absen pulang".
        $res = $this->absen($this->baku, 'pulang', '16:00:06');
        $res->assertStatus(200)->assertJson(['ok' => true, 'ditimpa' => true]);

        $akhir = $this->buktiPulang($this->baku);
        $this->assertSame(1, $akhir->count(), 'MENIMPA baris yang ada — bukan insert kedua.');
        $this->assertSame(self::HARI . ' 16:00:06', $akhir[0]->taken_at);
        $this->assertSame($awal[0]->id, $akhir[0]->id, 'id yang sama = memang ditimpa.');

        // scan-nya ikut bergeser, dan att_day membaca yang kedua
        $scan = DB::table('att_scan')->where('id', $akhir[0]->att_scan_id)->value('scanned_at');
        $this->assertSame(self::HARI . ' 16:00:06', $scan);
        $day = DB::table('att_day')->where('user_id', $this->baku->id)->where('work_date', self::HARI)->first();
        $this->assertNotNull($day, 'Reconcile kilat harus melahirkan att_day hari itu.');
        $this->assertSame(self::HARI . ' 16:00:06', $day->scan_last);

        // jejak lama tak hilang
        $this->assertTrue(DB::table('audit_log')->where('description', 'like', 'Absen pulang ditimpa%')
            ->where('description', 'like', '%08:00:06%')->exists(), 'Jam lama wajib tercatat di audit_log.');
    }

    public function test_pulang_lebih_dari_5_menit_sesudah_masuk_tak_perlu_confirm(): void
    {
        // Kendali negatif: peringatannya hanya untuk yang sedini itu.
        $this->absen($this->baku, 'masuk', '08:00:00')->assertStatus(200);
        $this->absen($this->baku, 'pulang', '17:30:00')->assertStatus(200)->assertJson(['ok' => true, 'ditimpa' => false]);
    }

    public function test_pulang_sungguhan_tidak_bisa_ditimpa(): void
    {
        // Timpa hanya untuk pulang ≤5 menit sesudah masuk. Pulang normal yang
        // sudah ada tetap ditolak — kalau tidak, tap ulang malam hari
        // menggeser jam pulang orang.
        $this->absen($this->baku, 'masuk', '08:00:00')->assertStatus(200);
        $this->absen($this->baku, 'pulang', '17:30:00')->assertStatus(200);

        $this->absen($this->baku, 'pulang', '21:00:00')->assertStatus(422)->assertJsonFragment(['ok' => false]);
        $this->assertSame(self::HARI . ' 17:30:00', $this->buktiPulang($this->baku)[0]->taken_at);
    }

    // ───────────────────────── pulang cepat ─────────────────────────

    public function test_orang_baku_pulang_sebelum_jam_pulang_diberi_teks_dan_flag_tapi_tetap_tercatat(): void
    {
        $jadwal = jamPulangTerjadwal($this->baku->id, Carbon::parse(self::HARI));   // mis. 17:00
        $awal   = Carbon::parse(self::HARI . ' ' . $jadwal)->subHours(2)->format('H:i:s');

        $this->absen($this->baku, 'masuk', '08:00:00')->assertStatus(200);

        // teks DI DALAM tombol, di halaman
        Carbon::setTestNow(Carbon::parse(self::HARI . ' ' . $awal));
        $html = $this->withSession($this->sesi($this->baku))->get('/akun/absen')->assertStatus(200)->getContent();
        $this->assertMatchesRegularExpression('#<button[^>]*id="btn-mulai"[^>]*>.*?Ini pulang cepat 2 jam\..*?</button>#s', $html,
            'Peringatan harus TEKS di dalam tombol — bukan dialog, bukan penghalang.');
        $this->assertStringContainsString('Jadwalmu sampai ' . str_replace(':', '.', $jadwal), $html);

        // tetap tercatat + flag pulang_cepat
        $this->absen($this->baku, 'pulang', $awal)->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertSame(1, $this->buktiPulang($this->baku)->count());
        $this->assertTrue(DB::table('att_flag')->where('user_id', $this->baku->id)
            ->where('work_date', self::HARI)->where('jenis', 'pulang_cepat')->exists(),
            'Pulang cepat wajib tercatat sebagai flag supaya terlihat di Pengawasan.');
    }

    public function test_orang_fleksibel_pulang_siang_tanpa_peringatan_apa_pun(): void
    {
        $this->absen($this->fleksibel, 'masuk', '08:00:00')->assertStatus(200);

        Carbon::setTestNow(Carbon::parse(self::HARI . ' 13:00:00'));
        $html = $this->withSession($this->sesi($this->fleksibel))->get('/akun/absen')->assertStatus(200)->getContent();
        $this->assertStringNotContainsString('pulang cepat', $html, 'Fleksibel tak punya jam pulang — nol peringatan.');

        $this->absen($this->fleksibel, 'pulang', '13:00:00')->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertFalse(DB::table('att_flag')->where('user_id', $this->fleksibel->id)
            ->where('work_date', self::HARI)->where('jenis', 'pulang_cepat')->exists());
    }

    public function test_jeda_timpa_sama_dengan_lipatan_reconciler(): void
    {
        // Kalau suatu saat berbeda, ada jendela di mana pulang tak bisa ditimpa
        // padahal reconciler tetap melipatnya — persis lubang yang ditutup di sini.
        $this->assertSame(300, AkunAbsenController::JEDA_TIMPA_DETIK);
    }
}
