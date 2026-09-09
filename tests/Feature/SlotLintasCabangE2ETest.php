<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * E2E gerbong "slot lintas cabang" — dijalankan di DEV dengan PERSONA SUNGGUHAN.
 *
 * Skenario yang diulang persis dari prod 10 Sep 2026: admin Cinere membuka
 * slot seorang dokter yang ternyata sudah dipesan di Kalideres. Dulu layarnya
 * cuma bilang "Terisi" (dan jamnya kembar); sekarang wajib:
 *   • kembarnya hilang (perbaikan gabung rentang),
 *   • kotak Terisi bisa menjelaskan dirinya — cabang · jam · nama pasien,
 *   • tanpa nomor HP, di mana pun,
 *   • dan PASIEN di form publik tak pernah melihat apa pun selain "Terisi".
 *
 * Persona: Front Office @ Cinere (AuthGuard membaca group_id → grant menu
 * 'rawreservation' & 'rawdoctor' harus ada di grupnya; group_name saja tak
 * cukup). Dokter uji = dokter TANPA jadwal sama sekali, supaya tak ada baris
 * lain yang ikut bicara. Semua fixture di dalam transaksi → dev tak berubah.
 *
 * ⚠️ Uji yang diam bukan uji: prasyarat yang hilang membuat kelas ini GAGAL
 * dengan pesan jelas, bukan markTestSkipped.
 */
class SlotLintasCabangE2ETest extends TestCase
{
    use DatabaseTransactions;

    private const NAMA_PASIEN = 'UJI PASIEN LINTAS CABANG';
    private const HP_PASIEN   = '08111222333';

    private string $dokter;
    private string $cinere;
    private string $kalideres;
    private string $tanggal;      // Kamis, ≥14 hari ke depan
    private object $adminCinere;  // Front Office @ Cinere
    private string $pasienId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cinere    = (string) DB::table('raw_branch')->where('name', 'Cinere')->value('id');
        $this->kalideres = (string) DB::table('raw_branch')->where('name', 'Kalideres')->value('id');
        $this->assertNotSame('', $this->cinere, 'cabang Cinere tidak ada di environment ini');
        $this->assertNotSame('', $this->kalideres, 'cabang Kalideres tidak ada di environment ini');

        $dokter = DB::table('raw_doctor as d')
            ->leftJoin('raw_doctor_schedule as s', 's.raw_doctor_id', '=', 'd.id')
            ->groupBy('d.id')
            ->havingRaw('COUNT(s.id) = 0')
            ->value('d.id');
        $this->assertNotNull($dokter, 'butuh satu dokter TANPA jadwal sebagai kanvas uji');
        $this->dokter = (string) $dokter;

        $admin = DB::table('users as u')
            ->join('group_path as g', 'g.id', '=', 'u.group_id')
            ->where('g.nama', 'Front Office')
            ->where('u.status', 1)
            ->where('u.raw_branch_id', $this->cinere)
            ->first(['u.id', 'u.nama', 'u.group_id', 'u.raw_branch_id']);
        $this->assertNotNull($admin, 'butuh Front Office aktif @ Cinere sebagai persona admin cabang');
        $this->adminCinere = $admin;

        $t = Carbon::today()->addDays(14);
        while ($t->dayOfWeekIso !== 4) {
            $t->addDay();
        }
        $this->tanggal = $t->toDateString();

        // Pasien uji dengan nomor HP yang KHAS — bukti bahwa HP tak pernah bocor
        // harus berupa "string ini tidak ada di keluaran", bukan sekadar "kunci
        // phone tidak ada".
        $this->pasienId = (string) Str::uuid();
        DB::table('raw_customer')->insert([
            'id' => $this->pasienId, 'name' => self::NAMA_PASIEN, 'phone' => self::HP_PASIEN,
        ]);
    }

    // ── fixture ────────────────────────────────────────────────────────────

    private function jadwal(string $cabang, string $mulai, string $selesai): void
    {
        DB::table('raw_doctor_schedule')->insert([
            'id' => (string) Str::uuid(), 'raw_doctor_id' => $this->dokter, 'raw_branch_id' => $cabang,
            'day' => 4, 'start_hour' => $mulai, 'end_hour' => $selesai, 'type' => 'work',
        ]);
    }

    private function hariTambahan(string $cabang, string $mulai, string $selesai): void
    {
        DB::table('raw_doctor_schedule_exception')->insert([
            'id' => (string) Str::uuid(), 'raw_doctor_id' => $this->dokter, 'raw_branch_id' => $cabang,
            'date' => $this->tanggal, 'type' => 'add', 'start_hour' => $mulai, 'end_hour' => $selesai,
            'reason' => 'uji', 'created_at' => now(),
        ]);
    }

    private function reservasi(string $cabang, string $mulai, string $selesai, string $status = 'pending'): void
    {
        DB::table('raw_cus_reservation')->insert([
            'id' => (string) Str::uuid(), 'raw_branch_id' => $cabang, 'raw_customer_id' => $this->pasienId,
            'raw_doctor_id' => $this->dokter, 'date' => $this->tanggal,
            'start_hour' => $mulai, 'end_hour' => $selesai, 'status' => $status, 'is_waiting' => 0,
            'created_at' => now(),
        ]);
    }

    private function sesi(object $u): array
    {
        return [
            'isLoggedIn' => true, 'id' => $u->id, 'group_id' => $u->group_id, 'group_name' => 'Front Office',
            'name' => $u->nama, 'raw_branch_id' => $u->raw_branch_id, 'branch_day' => date('Y-m-d'),
            'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time(),
        ];
    }

    private function slotStaf(): array
    {
        return $this->withSession($this->sesi($this->adminCinere))
            ->getJson("/rawreservation/slots/{$this->cinere}/{$this->dokter}/{$this->tanggal}?duration=30")
            ->assertStatus(200)
            ->json();
    }

    private function slotPublik(): array
    {
        return $this->getJson("/api/reservation/slots/{$this->cinere}/{$this->dokter}/{$this->tanggal}?duration=30")
            ->assertStatus(200)
            ->json();
    }

    private function slotJam(array $json, string $jam): ?array
    {
        foreach ($json['slots'] as $s) {
            if ($s['time'] === $jam) return $s;
        }
        return null;
    }

    // ── 1. kembar ──────────────────────────────────────────────────────────

    /** Persis kejadian prod: rutin 17-20 ditumpuk Hari Tambahan 17-18:30 → dulu 9 kotak. */
    public function test_hari_tambahan_menindih_tidak_lagi_mencetak_slot_kembar(): void
    {
        $this->jadwal($this->cinere, '17:00:00', '20:00:00');
        $this->hariTambahan($this->cinere, '17:00:00', '18:30:00');

        $jam = array_column($this->slotStaf()['slots'], 'time');

        $this->assertSame(['17:00', '17:30', '18:00', '18:30', '19:00', '19:30'], $jam,
            'enam jam, masing-masing sekali — bukan 9 kotak');
    }

    // ── 2. penjelasan untuk staf ───────────────────────────────────────────

    /** Skenario Ghita: dipesan di Kalideres, dilihat dari Cinere. */
    public function test_admin_cinere_melihat_siapa_dan_di_cabang_mana_slot_terisi(): void
    {
        $this->jadwal($this->cinere, '17:00:00', '20:00:00');
        $this->reservasi($this->kalideres, '17:00:00', '17:30:00');

        $json = $this->slotStaf();
        $s17  = $this->slotJam($json, '17:00');

        $this->assertTrue($s17['is_booked'], 'satu dokter tak bisa di dua cabang — 17:00 wajib tertahan');
        $this->assertSame('Kalideres',        $s17['booked_by']['branch']);
        $this->assertSame(self::NAMA_PASIEN,  $s17['booked_by']['nama']);
        $this->assertSame('17:00–17:30',      $s17['booked_by']['jam']);

        // Spanduk hari: ada janji di cabang lain — walau 17:30 sendiri bebas.
        $this->assertCount(1, $json['other_branch_bookings']);
        $this->assertSame('Kalideres', $json['other_branch_bookings'][0]['branch']);
        $this->assertTrue($this->slotJam($json, '17:30')['available'],
            '17:30 memang bebas — itulah lubang "pindah cabang nol menit" yang ditutup spanduk, bukan dengan memblokir');
    }

    /** Keputusan Ekky 8 Sep: HP tidak diambil, tidak dikirim, tidak dirender. */
    public function test_nomor_hp_tak_pernah_ada_di_keluaran_staf(): void
    {
        $this->jadwal($this->cinere, '17:00:00', '20:00:00');
        $this->reservasi($this->kalideres, '17:00:00', '17:30:00');

        $mentah = $this->withSession($this->sesi($this->adminCinere))
            ->getJson("/rawreservation/slots/{$this->cinere}/{$this->dokter}/{$this->tanggal}?duration=30")
            ->getContent();

        $this->assertStringContainsString(self::NAMA_PASIEN, $mentah, 'nama pasien memang boleh');
        $this->assertStringNotContainsString(self::HP_PASIEN, $mentah, 'nomor HP TIDAK boleh, dalam bentuk apa pun');
        $this->assertDoesNotMatchRegularExpression('/"(phone|no_hp|hp|wa|telp)"/i', $mentah);
    }

    /** Reservasi di cabang sendiri: kotak menjelaskan, tapi spanduk "cabang lain" diam. */
    public function test_reservasi_di_cabang_sendiri_tidak_masuk_spanduk(): void
    {
        $this->jadwal($this->cinere, '17:00:00', '20:00:00');
        $this->reservasi($this->cinere, '17:00:00', '17:30:00');

        $json = $this->slotStaf();

        $this->assertSame('Cinere', $this->slotJam($json, '17:00')['booked_by']['branch']);
        $this->assertSame([], $json['other_branch_bookings']);
    }

    /** Dokter tak praktik di Cinere hari itu — justru karena di Kalideres. Spanduk tetap muncul. */
    public function test_dokter_tak_praktik_di_cabang_ini_tapi_spanduk_tetap_muncul(): void
    {
        $this->jadwal($this->kalideres, '17:00:00', '20:00:00');
        $this->reservasi($this->kalideres, '17:00:00', '17:30:00');

        $json = $this->slotStaf();

        $this->assertSame([], $json['slots'], 'tak ada jadwal Cinere → nol slot');
        $this->assertCount(1, $json['other_branch_bookings'], 'tapi admin tetap diberi tahu dokternya di mana');
    }

    /** Batal & waiting list tidak menahan slot, dan tidak memunculkan spanduk. */
    public function test_batal_dan_waitlist_tidak_menahan_slot_maupun_spanduk(): void
    {
        $this->jadwal($this->cinere, '17:00:00', '20:00:00');
        $this->reservasi($this->kalideres, '17:00:00', '17:30:00', 'cancelled');
        $this->reservasi($this->kalideres, '17:30:00', '18:00:00', 'waitlist');

        $json = $this->slotStaf();

        $this->assertTrue($this->slotJam($json, '17:00')['available']);
        $this->assertTrue($this->slotJam($json, '17:30')['available']);
        $this->assertSame([], $json['other_branch_bookings']);
    }

    // ── 3. pagar: pasien & tanpa sesi ──────────────────────────────────────

    /** Form publik: pasien melihat "Terisi" dan TIDAK LEBIH — nama, cabang, HP semuanya tak ada. */
    public function test_pasien_di_form_publik_tidak_melihat_apa_pun_selain_terisi(): void
    {
        $this->jadwal($this->cinere, '17:00:00', '20:00:00');
        $this->reservasi($this->kalideres, '17:00:00', '17:30:00');

        $res    = $this->getJson("/api/reservation/slots/{$this->cinere}/{$this->dokter}/{$this->tanggal}?duration=30")
            ->assertStatus(200);
        $json   = $res->json();
        $mentah = $res->getContent();
        $s17    = $this->slotJam($json, '17:00');

        $this->assertTrue($s17['is_booked'], 'slotnya tetap tertahan');
        $this->assertArrayNotHasKey('booked_by', $s17);
        $this->assertArrayNotHasKey('other_branch_bookings', $json);
        $this->assertStringNotContainsString(self::NAMA_PASIEN, $mentah);
        $this->assertStringNotContainsString(self::HP_PASIEN, $mentah);
        $this->assertStringNotContainsString('Kalideres', $mentah);
    }

    /** Rute staf tanpa sesi: memantul ke login — bukan JSON berisi nama orang. */
    public function test_rute_staf_tanpa_sesi_memantul_ke_login_bukan_data(): void
    {
        $this->jadwal($this->cinere, '17:00:00', '20:00:00');
        $this->reservasi($this->kalideres, '17:00:00', '17:30:00');

        $res = $this->get("/rawreservation/slots/{$this->cinere}/{$this->dokter}/{$this->tanggal}?duration=30");

        $res->assertStatus(302);
        $this->assertStringContainsString('signin', (string) $res->headers->get('Location'));
        $this->assertStringNotContainsString(self::NAMA_PASIEN, $res->getContent());
    }

    // ── 4. Layar Jadwal Dokter ─────────────────────────────────────────────

    /** Jam praktik terbalik (asal-usul baris Dharma Minggu 22:00-20:00) kini ditolak saat disimpan. */
    public function test_jam_praktik_terbalik_ditolak_saat_disimpan(): void
    {
        $kirim = fn (string $mulai, string $selesai) => $this->withSession($this->sesi($this->adminCinere))
            ->post("/rawdoctor/{$this->dokter}/schedule", [
                'raw_branch_id' => $this->cinere, 'day' => 4, 'start_hour' => $mulai, 'end_hour' => $selesai,
            ]);

        $kirim('22:00', '20:00')->assertSessionHasErrors('end_hour');
        $this->assertSame(0, DB::table('raw_doctor_schedule')->where('raw_doctor_id', $this->dokter)->count(),
            'baris terbalik tak boleh tersimpan');

        $kirim('17:00', '20:00')->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('raw_doctor_schedule')->where('raw_doctor_id', $this->dokter)->count(),
            'jam praktik normal tetap bisa disimpan');
    }

    /** Halaman jadwal dokter membawa bahan peringatan tumpang tindih — kedua cabang, tanpa baris istirahat. */
    public function test_halaman_jadwal_dokter_membawa_bahan_peringatan_tumpang_tindih(): void
    {
        $this->jadwal($this->cinere, '17:00:00', '20:00:00');
        $this->jadwal($this->kalideres, '17:00:00', '21:00:00');
        DB::table('raw_doctor_schedule')->insert([
            'id' => (string) Str::uuid(), 'raw_doctor_id' => $this->dokter, 'raw_branch_id' => $this->cinere,
            'day' => 4, 'start_hour' => '18:00:00', 'end_hour' => '18:30:00', 'type' => 'break',
        ]);

        $html = $this->withSession($this->sesi($this->adminCinere))
            ->get("/rawdoctor/{$this->dokter}")
            ->assertStatus(200)
            ->getContent();

        $this->assertMatchesRegularExpression('/<script[^>]*id="dxOverlapData"[^>]*>(.*?)<\/script>/s', $html);
        preg_match('/<script[^>]*id="dxOverlapData"[^>]*>(.*?)<\/script>/s', $html, $m);
        $data = json_decode($m[1], true);

        $this->assertNotNull($data, 'payload peringatan harus JSON yang sah');
        $cabang = array_column($data['schedules'], 'bname');
        sort($cabang);
        $this->assertSame(['Cinere', 'Kalideres'], $cabang, 'kedua cabang ikut, baris istirahat TIDAK (istirahat bukan bentrok)');
        $this->assertStringContainsString('id="dxjOverlapWarn"', $html, 'wadah peringatan drawer jam kerja ada');
        $this->assertStringContainsString('id="dxcOverlapWarn"', $html, 'wadah peringatan jadwal khusus ada');
    }
}
