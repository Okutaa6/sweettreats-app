<?php

namespace Tests\Feature;

use App\Http\Controllers\AffiliatePortalController;
use App\Http\Controllers\Crud\SweetMatesController;
use App\Http\Controllers\Set\ContentReviewController;
use App\Models\AffiliateRewardClaim;
use App\Models\RawAffiliate;
use App\Services\KocPasien;
use App\Services\KocReward;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Reward KOC — pintu `used`, surplus konten terbawa, tautan KOC → pasien,
 * baris jumlah kontrol. Handoff: docs/handoff/handoff-koc-reward-surplus-kontrol.md §5.
 *
 * ⭐ = kendali negatif: membuktikan pagarnya MENOLAK, bukan cuma jalan bahagia.
 *
 * SQLite in-memory: mysqld lokal mati oleh kueri metadata, dan uji ini tak
 * butuh apa pun yang khas MySQL.
 *
 *   vendor/bin/phpunit tests/Feature/KocRewardSurplusTest.php
 */
class KocRewardSurplusTest extends TestCase
{
    private const BRONZE = 'T-BRONZE';
    private const SILVER = 'T-SILVER';
    private const GOLD   = 'T-GOLD';
    private const ADMIN  = 'U-ADMIN';

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'uji_sqlite',
            'database.connections.uji_sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('uji_sqlite');

        DB::statement('CREATE TABLE raw_affiliate (
            id VARCHAR(36) PRIMARY KEY, name VARCHAR(255), phone VARCHAR(30), type VARCHAR(10),
            reward_program VARCHAR(20), reward_program_started_at DATETIME, reward_program_chosen_at DATETIME, is_active TINYINT DEFAULT 1,
            raw_customer_id VARCHAR(36), created_at DATETIME)');
        DB::statement('CREATE TABLE affiliate_content (
            id VARCHAR(36) PRIMARY KEY, raw_affiliate_id VARCHAR(36), status VARCHAR(20), created_at DATETIME)');
        DB::statement('CREATE TABLE affiliate_reward_tier (
            id VARCHAR(36) PRIMARY KEY, name VARCHAR(100), required_content_count INT,
            reward_description VARCHAR(255), sort_order INT, is_active TINYINT, created_at DATETIME)');
        DB::statement('CREATE TABLE affiliate_reward_claim (
            id VARCHAR(36) PRIMARY KEY, raw_affiliate_id VARCHAR(36), affiliate_reward_tier_id VARCHAR(36),
            status VARCHAR(20), claimed_at DATETIME, processed_by VARCHAR(36), processed_at DATETIME,
            consumed_content_count INT, notes TEXT, created_at DATETIME)');
        DB::statement('CREATE TABLE raw_customer (
            id VARCHAR(36) PRIMARY KEY, name VARCHAR(255), phone VARCHAR(30), tanggal_lahir DATE,
            raw_contact_id VARCHAR(36))');
        DB::statement('CREATE TABLE raw_contact (id VARCHAR(36) PRIMARY KEY, phone VARCHAR(30), created_at DATETIME)');
        DB::statement('CREATE TABLE customer_omnicare_link (
            id VARCHAR(36) PRIMARY KEY, raw_customer_id VARCHAR(255) NOT NULL, omnicare_pid VARCHAR(50) NOT NULL UNIQUE)');
        DB::statement('CREATE TABLE omnicare_sales_detail (
            id INTEGER PRIMARY KEY AUTOINCREMENT, p_id VARCHAR(50), trx_date DATE, item_name VARCHAR(500))');

        DB::table('affiliate_reward_tier')->insert([
            ['id' => self::BRONZE, 'name' => 'Bronze Mates', 'required_content_count' => 3,  'reward_description' => 'FREE Scaling',             'sort_order' => 1, 'is_active' => 1, 'created_at' => '2026-01-01'],
            ['id' => self::SILVER, 'name' => 'Silver Mates', 'required_content_count' => 12, 'reward_description' => 'FREE Retainer + Scaling',  'sort_order' => 2, 'is_active' => 1, 'created_at' => '2026-01-01'],
            ['id' => self::GOLD,   'name' => 'Gold Mates',   'required_content_count' => 15, 'reward_description' => 'FREE Retainer + Whitening', 'sort_order' => 3, 'is_active' => 1, 'created_at' => '2026-01-01'],
        ]);
    }

    // ── alat bantu ───────────────────────────────────────────────────────

    private function koc(string $id, ?string $program, int $konten, string $phone = '081200000000'): RawAffiliate
    {
        DB::table('raw_affiliate')->insert([
            'id' => $id, 'name' => $id, 'phone' => $phone, 'type' => 'koc',
            'reward_program' => $program, 'is_active' => 1, 'created_at' => '2026-08-01 00:00:00',
        ]);
        $this->konten($id, $konten);
        return RawAffiliate::find($id);
    }

    /** Konten approved — sengaja semua di detik yang SAMA (bentuk nyata Linaily). */
    private function konten(string $affiliateId, int $n, string $waktu = '2026-08-08 10:25:14'): void
    {
        for ($i = 0; $i < $n; $i++) {
            DB::table('affiliate_content')->insert([
                'id' => 'K-' . (++$this->seq), 'raw_affiliate_id' => $affiliateId,
                'status' => 'approved', 'created_at' => $waktu,
            ]);
        }
    }

    private function klaim(string $affiliateId, string $tierId, string $status, string $dibuat = '2026-09-23 19:37:00'): string
    {
        $id = 'C-' . (++$this->seq);
        DB::table('affiliate_reward_claim')->insert([
            'id' => $id, 'raw_affiliate_id' => $affiliateId, 'affiliate_reward_tier_id' => $tierId,
            'status' => $status, 'claimed_at' => $status === 'eligible' ? null : $dibuat, 'created_at' => $dibuat,
        ]);
        return $id;
    }

    private function berikan(string $claimId): AffiliateRewardClaim
    {
        return (new KocReward())->tandaiDiberikan($claimId, self::ADMIN, 'uji');
    }

    private function segar(RawAffiliate $a): RawAffiliate
    {
        return RawAffiliate::find($a->id);
    }

    /**
     * Pindah program LEWAT CONTROLLER PORTAL SUNGGUHAN (bukan update kolom),
     * supaya pagar canSwitchProgram() dan penanda siklus ikut teruji.
     */
    private function pindah(string $affiliateId, string $program): void
    {
        session()->forget(['error', 'success']);
        session(['affiliate_id' => $affiliateId]);
        request()->merge(['reward_program' => $program]);
        app(AffiliatePortalController::class)->switchRewardProgram();

        $this->assertNull(session('error'), "pindah ke {$program} ditolak: " . session('error'));
        $this->assertSame($program, RawAffiliate::find($affiliateId)->reward_program);
    }

    private function privat(object $obj, string $metode, ...$args)
    {
        $m = new ReflectionMethod($obj, $metode);
        $m->setAccessible(true);
        return $m->invoke($obj, ...$args);
    }

    // ── §1 pintu used ────────────────────────────────────────────────────

    /** 1. Klaim ditandai used → KOC boleh pindah program. */
    public function test_klaim_ditandai_diberikan_membuka_pindah_program(): void
    {
        $linaily = $this->koc('LINAILY', '3x', 7);
        $claimId = $this->klaim('LINAILY', self::BRONZE, 'claimed');

        $claim = $this->berikan($claimId);

        $this->assertSame('used', $claim->status);
        $this->assertSame(self::ADMIN, $claim->processed_by);
        $this->assertNotNull($claim->processed_at);
        $this->assertSame(3, $claim->consumed_content_count, 'Bronze makan TEPAT 3');
        $this->assertSame('2026-09-23 19:37:00', $claim->fresh()->claimed_at->format('Y-m-d H:i:s'),
            'claimed_at = kapan KOC meminta — tak boleh ditimpa waktu pemberian');
        $this->assertTrue($this->segar($linaily)->canSwitchProgram());
    }

    /** ⭐ 2. Masih claimed → tetap terkunci (kendali negatif §1). */
    public function test_klaim_masih_claimed_tetap_terkunci(): void
    {
        $linaily = $this->koc('LINAILY', '3x', 7);
        $this->klaim('LINAILY', self::BRONZE, 'claimed');

        $this->assertFalse($linaily->canSwitchProgram());
    }

    /** ⭐ Pintu hanya dari 'claimed': eligible (belum diminta) & used (sudah) ditolak. */
    public function test_pintu_menolak_eligible_dan_yang_sudah_used(): void
    {
        $this->koc('A', '3x', 3);
        $eligible = $this->klaim('A', self::BRONZE, 'eligible');

        try {
            $this->berikan($eligible);
            $this->fail('eligible tak boleh langsung jadi used');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('belum mengajukan', $e->getMessage());
        }

        $this->koc('B', '3x', 3);
        $claimed = $this->klaim('B', self::BRONZE, 'claimed');
        $this->berikan($claimed);
        try {
            $this->berikan($claimed);
            $this->fail('dua kali ditandai = konten dimakan dua kali');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sudah ditandai', $e->getMessage());
        }
        $this->assertSame(3, (int) DB::table('affiliate_reward_claim')->where('id', $claimed)->value('consumed_content_count'));
    }

    // ── §2 surplus terbawa ───────────────────────────────────────────────

    /** 3. Pindah sesudah Bronze used → 4, bukan 0 dan bukan 7 (lewat controller portal sungguhan). */
    public function test_pindah_program_sesudah_bronze_used_membawa_empat(): void
    {
        $linaily = $this->koc('LINAILY', '3x', 7);
        $this->berikan($this->klaim('LINAILY', self::BRONZE, 'claimed'));

        session(['affiliate_id' => 'LINAILY']);
        request()->merge(['reward_program' => '12x']);
        app(AffiliatePortalController::class)->switchRewardProgram();

        $linaily = $this->segar($linaily);
        $this->assertSame('12x', $linaily->reward_program);
        $this->assertNull($linaily->reward_program_started_at, 'stempel waktu tak ditulis lagi');
        $this->assertSame(4, $linaily->approvedContentCount());
        $this->assertSame(['count' => 4, 'required' => 12], array_intersect_key(
            $linaily->currentProgramProgress(), ['count' => 1, 'required' => 1]));
    }

    /** Stempel waktu lama (kalau ada) tak lagi menghanguskan apa pun. */
    public function test_reward_program_started_at_tak_dibaca_lagi(): void
    {
        $a = $this->koc('A', '12x', 5);
        DB::table('raw_affiliate')->where('id', 'A')->update(['reward_program_started_at' => '2030-01-01 00:00:00']);

        $this->assertSame(5, $this->segar($a)->approvedContentCount());
    }

    /** ⭐ 4. Bronze makan tepat 3 — 15 konten tak memuaskan Bronze DAN Silver dari kolam yang sama. */
    public function test_limabelas_konten_tak_dipakai_dua_kali(): void
    {
        $a = $this->koc('A', '3x', 14);
        $this->berikan($this->klaim('A', self::BRONZE, 'claimed'));
        $this->pindah('A', '12x');
        $a = $this->segar($a);

        $this->assertSame(11, $a->approvedContentCount());
        $this->assertFalse($a->currentProgramProgress()['achieved'], '14 konten = Bronze(3) + 11, belum Silver');

        // Konten ke-15 → tepat cukup untuk Silver.
        $this->konten('A', 1);
        $this->assertTrue($this->segar($a)->currentProgramProgress()['achieved']);
    }

    /** 5. Turun kelas (Silver → Bronze) → surplus tetap terbawa, dan klaim Bronze baru bisa lahir. */
    public function test_turun_kelas_membawa_surplus(): void
    {
        $a = $this->koc('A', '3x', 3);
        $this->berikan($this->klaim('A', self::BRONZE, 'claimed', '2026-08-01 10:00:00'));  // siklus 1: Bronze
        $this->pindah('A', '12x');
        $this->konten('A', 14);                                                          // 14 konten baru
        $this->berikan($this->klaim('A', self::SILVER, 'claimed', '2026-09-01 10:00:00'));  // siklus 2: Silver
        $this->pindah('A', '3x');  // siklus 3: turun ke Bronze
        $a = $this->segar($a);

        $this->assertSame(2, $a->approvedContentCount(), '17 − 3 − 12 = 2 terbawa ke Bronze');
        $this->assertNull($a->cycleRewardClaim(), 'klaim Bronze siklus 1 itu sejarah, bukan milik siklus ini');
        $this->assertTrue($a->canSwitchProgram());

        // Satu konten lagi → Bronze siklus 3 layak, klaim BARU lahir saat konten di-approve.
        $this->konten('A', 1);
        $this->privat(new ContentReviewController(), 'syncRewardEligibility', 'A');
        $baru = $this->segar($a)->cycleRewardClaim();
        $this->assertNotNull($baru);
        $this->assertSame('eligible', $baru->status);
        $this->assertSame(2, DB::table('affiliate_reward_claim')->where('raw_affiliate_id', 'A')
            ->where('affiliate_reward_tier_id', self::BRONZE)->count());
    }

    /** ⭐ Tetap di tier yang sama sesudah used → TIDAK lahir klaim kembar dari surplus. */
    public function test_tanpa_pindah_program_tak_lahir_klaim_ulang(): void
    {
        $this->koc('LINAILY', '3x', 7);
        $this->berikan($this->klaim('LINAILY', self::BRONZE, 'claimed'));

        $this->privat(new ContentReviewController(), 'syncRewardEligibility', 'LINAILY');

        $this->assertSame(1, DB::table('affiliate_reward_claim')->where('raw_affiliate_id', 'LINAILY')->count());
        $this->assertSame('used', RawAffiliate::find('LINAILY')->cycleRewardClaim()->status);
    }

    /**
     * Temuan audit #2: Bronze used → Silver (belum selesai) → kembali Bronze.
     * Versi pertama menganggap klaim Bronze lama masih "siklus ini" (karena ia
     * tetap used-terakhir), jadi KOC yang cuma mau Bronze terkunci selamanya.
     */
    public function test_pergi_lalu_kembali_ke_tier_sama_membuka_siklus_baru(): void
    {
        $this->koc('A', '3x', 3);
        $this->berikan($this->klaim('A', self::BRONZE, 'claimed'));
        $this->pindah('A', '12x');          // Silver, tak pernah selesai
        $this->pindah('A', '3x');           // kembali ke Bronze
        $this->konten('A', 3);

        $this->assertNull(RawAffiliate::find('A')->cycleRewardClaim(), 'klaim Bronze lama = sejarah');
        $this->privat(new ContentReviewController(), 'syncRewardEligibility', 'A');
        $this->assertSame('eligible', RawAffiliate::find('A')->cycleRewardClaim()?->status);
    }

    /**
     * ⭐ Pindah program lalu klaim lahir & diberikan di DETIK YANG SAMA (alur
     * nyata: pindah → redirect → klaim eligible lahir). Klaim itu milik siklus
     * BARU — versi yang membandingkan processed_at menganggapnya lama dan
     * membuka klaim kembar. Ditangkap E2E dengan data prod 26 Sep.
     */
    public function test_klaim_sedetik_dengan_pindah_program_milik_siklus_baru(): void
    {
        $this->koc('A', '3x', 3);
        $this->berikan($this->klaim('A', self::BRONZE, 'claimed'));
        $this->pindah('A', '12x');
        $this->konten('A', 12);

        $sekarang = now()->format('Y-m-d H:i:s');   // detik yang sama dengan reward_program_chosen_at
        $baru = $this->klaim('A', self::SILVER, 'claimed', $sekarang);
        $this->berikan($baru);

        $siklus = RawAffiliate::find('A')->cycleRewardClaim();
        $this->assertSame($baru, $siklus?->id);
        $this->assertSame('used', $siklus->status);
        $this->privat(new ContentReviewController(), 'syncRewardEligibility', 'A');
        $this->assertSame(1, DB::table('affiliate_reward_claim')->where('affiliate_reward_tier_id', self::SILVER)->count(),
            'tak boleh lahir klaim Silver kembar dari surplus');
    }

    /** ⭐ Pintu menolak klaim basi yang bukan milik program berjalan (POST rakitan). */
    public function test_pintu_menolak_klaim_bukan_siklus_berjalan(): void
    {
        $this->koc('A', '12x', 20);
        $basi = $this->klaim('A', self::BRONZE, 'claimed');  // Bronze, padahal program = Silver

        try {
            $this->berikan($basi);
            $this->fail('klaim tier yang tak dipilih tak boleh memakan konten');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('bukan klaim program', $e->getMessage());
        }
        $this->assertSame(20, RawAffiliate::find('A')->approvedContentCount());
    }

    /** ⭐ 6. Surplus tidak kedaluwarsa — konten setahun lalu tetap terhitung. */
    public function test_surplus_tidak_kedaluwarsa(): void
    {
        DB::table('raw_affiliate')->insert(['id' => 'A', 'name' => 'A', 'type' => 'koc', 'reward_program' => '3x', 'is_active' => 1]);
        $this->konten('A', 5, '2025-01-01 00:00:00');
        $this->berikan($this->klaim('A', self::BRONZE, 'claimed'));
        $this->pindah('A', '12x');

        $this->assertSame(2, RawAffiliate::find('A')->approvedContentCount());
    }

    // ── §3/§4 tautan pasien & kontrol ────────────────────────────────────

    private function pasienDewiSanti(): void
    {
        DB::table('raw_customer')->insert([
            ['id' => 'CUS-SIMATUPANG', 'name' => 'DEWI SANTI SIMATUPANG',      'phone' => '087880405047', 'tanggal_lahir' => '1991-08-26'],
            ['id' => 'CUS-SARTIKA',    'name' => 'DEWI SANTI SARTIKA AGUSTIN', 'phone' => '07880405047',   'tanggal_lahir' => '1991-08-26'], // bentuk NYATA prod: satu angka 8 hilang,
        ]);
        DB::table('customer_omnicare_link')->insert([
            ['id' => 'L1', 'raw_customer_id' => 'CUS-SIMATUPANG', 'omnicare_pid' => 'C-2012-0059'],
            ['id' => 'L2', 'raw_customer_id' => 'CUS-SARTIKA',    'omnicare_pid' => 'S-2011-0892'],
        ]);
        for ($i = 1; $i <= 23; $i++) {
            DB::table('omnicare_sales_detail')->insert([
                'p_id' => 'C-2012-0059', 'trx_date' => sprintf('2025-%02d-%02d', intdiv($i - 1, 2) + 1, $i % 2 ? 5 : 20),
                'item_name' => 'Kontrol Behel',
            ]);
        }
    }

    /** ⭐ 7. HP ganda → tak ditebak; baris kontrol berbunyi "perlu dipilih". */
    public function test_hp_ganda_dibiarkan_untuk_manusia(): void
    {
        $this->pasienDewiSanti();
        $dewi = $this->koc('DEWI', '12x', 1, '0878-8040-5047');
        $layanan = new KocPasien();

        $this->assertSame(KocPasien::GANDA, $layanan->tautkanOtomatis($dewi));
        $this->assertNull($this->segar($dewi)->raw_customer_id);

        $r = $layanan->ringkasanKontrol([$this->segar($dewi)])['DEWI'];
        $this->assertSame('perlu_dipilih', $r['status']);
        $this->assertNull($r['jumlah'], 'bukan angka tebakan, bukan 0');

        // Kosong juga "perlu dipilih".
        $x = $this->koc('X', null, 0, '081111111111');
        $this->assertSame(KocPasien::KOSONG, $layanan->tautkanOtomatis($x));
        $this->assertSame('perlu_dipilih', $layanan->ringkasanKontrol([$this->segar($x)])['X']['status']);
    }

    /** Cocok tunggal → terisi otomatis, dan tak pernah menimpa tautan yang ada. */
    public function test_cocok_tunggal_ditautkan_otomatis(): void
    {
        DB::table('raw_customer')->insert(['id' => 'CUS-ZIVI', 'name' => 'ZIVI', 'phone' => '081234567890']);
        $zivi = $this->koc('ZIVI', 'above_12x', 10, '+62 812-3456-7890');
        $layanan = new KocPasien();

        $this->assertSame(KocPasien::TERTAUT, $layanan->tautkanOtomatis($zivi));
        $this->assertSame('CUS-ZIVI', $this->segar($zivi)->raw_customer_id);

        DB::table('raw_affiliate')->where('id', 'ZIVI')->update(['raw_customer_id' => 'CUS-PILIHAN-MANUSIA']);
        $this->assertSame(KocPasien::SUDAH, $layanan->tautkanOtomatis($this->segar($zivi)));
        $this->assertSame('CUS-PILIHAN-MANUSIA', $this->segar($zivi)->raw_customer_id);
    }

    /** Pasien yang nomornya hanya ada di jangkar raw_contact ikut jadi kandidat. */
    public function test_kandidat_lewat_raw_contact(): void
    {
        DB::table('raw_contact')->insert(['id' => 'CT-1', 'phone' => '081299998888']);
        DB::table('raw_customer')->insert(['id' => 'CUS-ANAK', 'name' => 'ANAK', 'phone' => null, 'raw_contact_id' => 'CT-1']);
        $a = $this->koc('A', null, 0, '6281299998888');

        $this->assertSame(['CUS-ANAK'], (new KocPasien())->kandidat($a)->pluck('id')->all());
    }

    /** ⭐ 8. Jumlah kontrol deterministik — kasus Dewi Santi, dijalankan dua kali. */
    public function test_jumlah_kontrol_deterministik(): void
    {
        $this->pasienDewiSanti();
        $dewi = $this->koc('DEWI', '12x', 1, '087880405047');
        $layanan = new KocPasien();

        $urutan1 = $layanan->kandidat($dewi)->pluck('id')->all();
        $urutan2 = $layanan->kandidat($dewi)->pluck('id')->all();
        $this->assertSame($urutan1, $urutan2, 'daftar pilihan harus selalu berurutan sama');

        $layanan->tautkan($dewi, 'CUS-SIMATUPANG');  // manusia memilih
        $dewi = $this->segar($dewi);

        $r1 = $layanan->ringkasanKontrol([$dewi])['DEWI'];
        $r2 = $layanan->ringkasanKontrol([$dewi])['DEWI'];
        $this->assertSame($r1, $r2);
        $this->assertSame(23, $r1['jumlah']);
        $this->assertSame('2025-12-05', $r1['terakhir']);
    }

    /** Satu kunjungan dengan dua item kontrol dihitung SEKALI; item non-kontrol tak dihitung; pid ganda dijumlah. */
    public function test_kontrol_dihitung_per_kunjungan_lintas_pid(): void
    {
        DB::table('raw_customer')->insert(['id' => 'CUS-A', 'name' => 'A', 'phone' => '081200000000']);
        DB::table('customer_omnicare_link')->insert([
            ['id' => 'L1', 'raw_customer_id' => 'CUS-A', 'omnicare_pid' => 'B-1'],
            ['id' => 'L2', 'raw_customer_id' => 'CUS-A', 'omnicare_pid' => 'S-1'],
        ]);
        DB::table('omnicare_sales_detail')->insert([
            ['p_id' => 'B-1', 'trx_date' => '2026-09-01', 'item_name' => 'Kontrol Behel RA'],
            ['p_id' => 'B-1', 'trx_date' => '2026-09-01', 'item_name' => 'Kontrol Behel RB'],
            ['p_id' => 'B-1', 'trx_date' => '2026-09-01', 'item_name' => 'Service Charge'],
            ['p_id' => 'S-1', 'trx_date' => '2026-09-20', 'item_name' => 'kontrol behel'],
            ['p_id' => 'S-1', 'trx_date' => '2026-09-25', 'item_name' => 'Scaling'],
        ]);
        $a = $this->koc('A', null, 0);
        (new KocPasien())->tautkan($a, 'CUS-A');

        $r = (new KocPasien())->ringkasanKontrol([$this->segar($a)])['A'];
        $this->assertSame(2, $r['jumlah']);
        $this->assertSame('2026-09-20', $r['terakhir']);
    }

    /** ⭐ 9. Kelayakan tetap dari jumlah video — kontrol 0 tetap bisa klaim. */
    public function test_kontrol_nol_tetap_layak_klaim(): void
    {
        DB::table('raw_customer')->insert(['id' => 'CUS-Z', 'name' => 'Z', 'phone' => '081200000000']);
        $a = $this->koc('Z', '3x', 3);
        (new KocPasien())->tautkan($a, 'CUS-Z');
        $this->assertSame(0, (new KocPasien())->ringkasanKontrol([$this->segar($a)])['Z']['jumlah']);

        $this->privat(new ContentReviewController(), 'syncRewardEligibility', 'Z');
        $this->assertSame('eligible', $this->segar($a)->cycleRewardClaim()?->status);
        $this->assertTrue($this->privat(new SweetMatesController(), 'isEligibleForClaim', $this->segar($a)));

        // Dan KOC yang BELUM tertaut sama sekali pun tetap layak.
        $b = $this->koc('TANPA-PASIEN', '3x', 3, '089999999999');
        $this->privat(new ContentReviewController(), 'syncRewardEligibility', 'TANPA-PASIEN');
        $this->assertSame('eligible', $this->segar($b)->cycleRewardClaim()?->status);
    }

    /** Tautan ke rekam yang sudah dihapus/digabung → "perlu dipilih", BUKAN "0×". */
    public function test_tautan_patah_bukan_nol(): void
    {
        $a = $this->koc('A', null, 0);
        DB::table('raw_affiliate')->where('id', 'A')->update(['raw_customer_id' => 'CUS-SUDAH-DIGABUNG']);

        $r = (new KocPasien())->ringkasanKontrol([$this->segar($a)])['A'];
        $this->assertSame('perlu_dipilih', $r['status']);
        $this->assertNull($r['jumlah']);
    }

    /** ⭐ Pencocokan ekor nomor tak menjaring nomor yang benar-benar lain. */
    public function test_ekor_nomor_tak_menjaring_nomor_lain(): void
    {
        DB::table('raw_customer')->insert([
            ['id' => 'CUS-1', 'name' => 'X', 'phone' => '081234567890'],
            ['id' => 'CUS-2', 'name' => 'Y', 'phone' => '081234567891'],   // beda digit terakhir
            ['id' => 'CUS-3', 'name' => 'Z', 'phone' => '0812-3456-7890'], // nomor sama, berformat
        ]);
        $a = $this->koc('A', null, 0, '+62 812 3456 7890');

        $this->assertSame(['CUS-1', 'CUS-3'], (new KocPasien())->kandidat($a)->pluck('id')->all());
    }

    /** ⭐ Open redirect (temuan audit #3): ?return= ke host lain ditolak. */
    public function test_return_url_hanya_host_sendiri(): void
    {
        $uji = function (string $ret) {
            app()->instance('request', \Illuminate\Http\Request::create('https://app.sweettreats.id/koc/X/pasien', 'GET', ['return' => $ret]));
            return $this->privat(new \App\Http\Controllers\Crud\KocController(), 'urlKembali');
        };

        $this->assertSame('https://app.sweettreats.id/sweet-mates?tier=bronze', $uji('https://app.sweettreats.id/sweet-mates?tier=bronze'));
        foreach ([
            'https://app.sweettreats.id.evil.com/x',
            'https://app.sweettreats.id@evil.com/',
            '//evil.com/x',
            'javascript:alert(1)',
            'https://evil.com/?https://app.sweettreats.id',
        ] as $jahat) {
            $this->assertStringNotContainsString('evil', $uji($jahat), "lolos: {$jahat}");
            $this->assertStringNotContainsString('javascript', $uji($jahat), "lolos: {$jahat}");
        }
    }

    /** Lima calon hari-pertama: Ni Wayan (13) memenuhi Silver seketika — disengaja & diterima Ikhsan. */
    public function test_ni_wayan_memenuhi_silver_seketika(): void
    {
        $niWayan = $this->koc('NI-WAYAN', null, 13);
        $this->pindah('NI-WAYAN', '12x');

        $this->assertTrue($this->segar($niWayan)->currentProgramProgress()['achieved']);
    }
}
