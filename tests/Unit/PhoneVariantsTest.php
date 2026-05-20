<?php

namespace Tests\Unit;

use App\Http\Controllers\Concerns\ResolvesPatientContact;
use Tests\TestCase;

/**
 * Unit test untuk ResolvesPatientContact::phoneVariants — logika MURNI
 * (tanpa DB). Variant inilah yang dipakai mencocokkan raw_contact &
 * raw_customer lintas format, jadi wajib benar.
 */
class PhoneVariantsTest extends TestCase
{
    private function variants(?string $phone): array
    {
        $obj = new class {
            use ResolvesPatientContact;
            public function call(?string $p): array
            {
                return $this->phoneVariants($p);
            }
        };

        return $obj->call($phone);
    }

    public function test_nomor_valid_menghasilkan_4_variant(): void
    {
        $v = $this->variants('081234567890');

        $this->assertSame([
            '081234567890',      // 08xx
            '81234567890',       // 8xx
            '6281234567890',     // 62xx
            '006281234567890',   // 0062xx
        ], $v);
    }

    public function test_format_62_dinormalisasi_dulu_sebelum_variant(): void
    {
        // 62812... -> norm 0812... -> variant set yang sama.
        $this->assertSame(
            $this->variants('081234567890'),
            $this->variants('+62 812-3456-7890')
        );
    }

    public function test_nomor_tak_valid_fallback_ke_raw_trim(): void
    {
        $this->assertSame(['abc'], $this->variants('  abc  '));
    }

    public function test_kosong_atau_null_menghasilkan_array_kosong(): void
    {
        $this->assertSame([], $this->variants(''));
        $this->assertSame([], $this->variants(null));
    }
}
