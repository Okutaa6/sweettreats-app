<?php

namespace Tests\Feature;

use App\Models\RawContact;
use App\Models\RawCustomer;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integration test model 1-nomor-banyak-pasien (contact-anchored).
 *
 * Catatan: sama seperti CekatApiTest — test DB = MySQL asli (RefreshDatabase
 * di-comment di phpunit.xml), jadi test yang MENULIS data di-skip secara
 * default supaya suite tidak flaky/merusak data dev. Jalankan manual dengan:
 *
 *   RUN_DB_TESTS=1 php artisan test --filter=ReservationContactTest
 *
 * Setiap test membersihkan datanya sendiri di tearDown.
 */
class ReservationContactTest extends TestCase
{
    private array $cleanupContactIds = [];
    private array $cleanupCustomerIds = [];
    private string $phone = '081299900011'; // nomor uji yang tidak mungkin nyata

    protected function setUp(): void
    {
        parent::setUp();
        if (! env('RUN_DB_TESTS')) {
            $this->markTestSkipped('Set RUN_DB_TESTS=1 untuk menjalankan test DB ini.');
        }
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();
        parent::tearDown();
    }

    private function purge(): void
    {
        RawCustomer::where('phone', $this->phone)->delete();
        RawContact::where('phone', $this->phone)->delete();
    }

    /** Self-healing: pasien legacy (raw_contact_id NULL) ikut teradopsi. */
    public function test_resolve_contact_mengadopsi_pasien_legacy(): void
    {
        $legacy = RawCustomer::create([
            'id'    => (string) Str::uuid(),
            'name'  => 'Pasien Legacy',
            'phone' => $this->phone,
            // raw_contact_id sengaja NULL (data lama / buatan Cekat)
        ]);

        $ctrl = new class {
            use \App\Http\Controllers\Concerns\ResolvesPatientContact;
            public function run(string $p): RawContact
            {
                return \Illuminate\Support\Facades\DB::transaction(
                    fn () => $this->resolveContact($p, 'Test')
                );
            }
        };

        $contact = $ctrl->run($this->phone);

        $legacy->refresh();
        $this->assertSame($contact->id, $legacy->raw_contact_id,
            'Pasien legacy harus teradopsi ke contact yang di-resolve.');
    }

    /** 2 pendaftaran nomor sama, nama beda → 2 customer, 1 contact. */
    public function test_dua_pasien_satu_nomor(): void
    {
        $ctrl = new class {
            use \App\Http\Controllers\Concerns\ResolvesPatientContact;
            public function run(string $p, string $n): RawContact
            {
                return \Illuminate\Support\Facades\DB::transaction(
                    fn () => $this->resolveContact($p, $n)
                );
            }
        };

        $c1 = $ctrl->run($this->phone, 'Suami');
        RawCustomer::create([
            'id' => (string) Str::uuid(), 'raw_contact_id' => $c1->id,
            'name' => 'Suami', 'phone' => $this->phone, 'relasi' => 'diri_sendiri',
        ]);
        $c2 = $ctrl->run($this->phone, 'Istri');
        RawCustomer::create([
            'id' => (string) Str::uuid(), 'raw_contact_id' => $c2->id,
            'name' => 'Istri', 'phone' => $this->phone, 'relasi' => 'pasangan',
        ]);

        $this->assertSame($c1->id, $c2->id, 'Harus contact yang sama.');
        $this->assertSame(1, RawContact::where('phone', $this->phone)->count());
        $this->assertSame(2, RawCustomer::where('phone', $this->phone)->count());
    }

    /** Endpoint check-customer mengembalikan daftar patients[]. */
    public function test_check_customer_mengembalikan_daftar_pasien(): void
    {
        $contact = RawContact::create([
            'id' => (string) Str::uuid(), 'phone' => $this->phone, 'name' => 'A',
        ]);
        RawCustomer::create([
            'id' => (string) Str::uuid(), 'raw_contact_id' => $contact->id,
            'name' => 'Anak Satu', 'phone' => $this->phone, 'relasi' => 'anak',
        ]);

        $res = $this->getJson('/api/reservation/check-customer?phone=' . $this->phone);

        $res->assertOk()
            ->assertJson(['found' => true])
            ->assertJsonStructure([
                'found',
                'contact' => ['id', 'phone', 'name'],
                'patients' => [['customer_id', 'name', 'relasi', 'tanggal_lahir']],
            ]);
    }
}
