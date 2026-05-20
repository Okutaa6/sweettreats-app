<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Feature test ringan untuk Open API Cekat.
 *
 * Catatan: test DB = MySQL asli (RefreshDatabase di-comment di phpunit.xml),
 * jadi test di sini sengaja HANYA menguji jalur tanpa-token. Middleware
 * VerifyApiClientToken mengembalikan 401 SEBELUM menyentuh DB ketika header
 * Authorization tidak ada — jadi test ini tidak menulis/membaca data apa pun
 * dan tidak flaky. Skenario sukses/validasi/sandbox diuji manual via
 * docs/cekat-api.http (butuh token & tabel api_clients).
 */
class CekatApiTest extends TestCase
{
    public function test_get_endpoint_tanpa_token_mengembalikan_401(): void
    {
        $res = $this->getJson('/apicekatdev/service_range?codes=SVC001');

        $res->assertStatus(401)
            ->assertJson(['status' => 'error', 'code' => 'UNAUTHORIZED']);
    }

    public function test_post_endpoint_tanpa_token_mengembalikan_401(): void
    {
        $res = $this->postJson('/apicekatdev/customer_profile', [
            'cabang'    => 'Bintaro',
            'nama'      => 'Tes',
            'no_telpon' => '081234567890',
        ]);

        $res->assertStatus(401)
            ->assertJson(['status' => 'error', 'code' => 'UNAUTHORIZED']);
    }

    public function test_live_endpoint_tanpa_token_juga_401(): void
    {
        $this->getJson('/apicekat/service_range?codes=SVC001')
            ->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHORIZED']);
    }
}
