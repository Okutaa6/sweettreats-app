<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ApiCekatController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Unit test untuk normalizePhone() — logika murni (tanpa DB),
 * jadi aman dijalankan di environment apa pun.
 */
class CekatHelpersTest extends TestCase
{
    private function normalize(?string $input): ?string
    {
        $m = new ReflectionMethod(ApiCekatController::class, 'normalizePhone');
        $m->setAccessible(true);

        return $m->invoke(new ApiCekatController(), $input);
    }

    /** @dataProvider phoneProvider */
    public function test_normalize_phone(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->normalize($input));
    }

    public static function phoneProvider(): array
    {
        return [
            'sudah 08'           => ['081234567890', '081234567890'],
            'plus 62 + strip'    => ['+62 812-3456-7890', '081234567890'],
            'prefix 62'          => ['62812345678', '0812345678'],
            'prefix 0062'        => ['0062812345678', '0812345678'],
            'tanpa 0 depan'      => ['8123456789', '08123456789'],
            'ada spasi & kurung' => ['(0812) 3456 7890', '081234567890'],
            'kosong'             => ['', null],
            'null'              => [null, null],
            'huruf semua'        => ['abcd', null],
            'terlalu pendek'     => ['021', null],
            'hanya 62'           => ['62', null],
        ];
    }
}
