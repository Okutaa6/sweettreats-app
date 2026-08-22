<?php

namespace Tests\Unit;

use App\Services\CabangBertugas;
use PHPUnit\Framework\TestCase;

/**
 * Aturan resolusi cabang bertugas — bagian MURNI (tanpa DB).
 * Uji terima §7 no. 1–5 dijalankan di dev dengan data prod; ini penjaga
 * regresi untuk aturannya sendiri.
 */
class CabangBertugasTest extends TestCase
{
    private function baris(string $branch, ?string $from, ?string $to): object
    {
        return (object) ['raw_branch_id' => $branch, 'effective_from' => $from, 'effective_to' => $to];
    }

    /** Tanpa penempatan sementara → cabang asal (uji 4 & 5: tak ada regresi). */
    public function test_tanpa_rolling_jatuh_ke_cabang_asal(): void
    {
        $this->assertSame('bintaro', CabangBertugas::pada([], '2026-08-25', 'bintaro'));
    }

    /** Verini: asal Sunter, rolling Cinere terbuka sejak 13 Agu (uji 1). */
    public function test_rolling_terbuka_menang(): void
    {
        $rows = [$this->baris('cinere', '2026-08-13', null)];
        $this->assertSame('cinere', CabangBertugas::pada($rows, '2026-08-25', 'sunter'));
        $this->assertSame('sunter', CabangBertugas::pada($rows, '2026-08-12', 'sunter'));
    }

    /** Della: asal Cinere, rolling Sunter 18–31 Agu (uji 2). */
    public function test_rolling_berbatas_hanya_di_dalam_rentang(): void
    {
        $rows = [$this->baris('sunter', '2026-08-18', '2026-08-31')];
        $this->assertSame('cinere', CabangBertugas::pada($rows, '2026-08-17', 'cinere'));
        $this->assertSame('sunter', CabangBertugas::pada($rows, '2026-08-18', 'cinere'));  // inklusif
        $this->assertSame('sunter', CabangBertugas::pada($rows, '2026-08-31', 'cinere'));  // inklusif
        $this->assertSame('cinere', CabangBertugas::pada($rows, '2026-09-01', 'cinere'));
    }

    /** ABG/Office (cabang asal NULL) tak boleh dikunci baris penempatan. */
    public function test_cabang_asal_null_tetap_null(): void
    {
        $rows = [$this->baris('cinere', '2026-08-13', null)];
        $this->assertNull(CabangBertugas::pada($rows, '2026-08-25', null));
    }

    /** Yang BERBATAS menang atas yang terbuka — urutan sama dgn scopeActiveOn(). */
    public function test_berbatas_menang_atas_terbuka(): void
    {
        $rows = [
            $this->baris('sunter', '2026-08-18', '2026-08-31'),
            $this->baris('cinere', '2026-08-13', null),
        ];
        // Sengaja belum diurut muat(); pada() memakai urutan yang diberikan,
        // jadi yang diuji di sini: baris pertama yang cocok yang dipakai.
        $this->assertSame('sunter', CabangBertugas::pada($rows, '2026-08-25', 'kalideres'));
    }
}
