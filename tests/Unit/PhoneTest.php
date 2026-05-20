<?php

namespace Tests\Unit;

use App\Support\Phone;
use Tests\TestCase;

/**
 * Unit test App\Support\Phone::normalize — logika murni (tanpa DB).
 *
 * Dataset SENGAJA identik dengan CekatHelpersTest::phoneProvider supaya
 * paritas helper baru vs ApiCekatController::normalizePhone terjamin.
 * Kalau test ini hijau & CekatHelpersTest hijau, kedua normalisasi sama.
 */
class PhoneTest extends TestCase
{
    /** @dataProvider phoneProvider */
    public function test_normalize(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
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
            'null'               => [null, null],
            'huruf semua'        => ['abcd', null],
            'terlalu pendek'     => ['021', null],
            'hanya 62'           => ['62', null],
        ];
    }
}
