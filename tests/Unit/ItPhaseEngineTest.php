<?php

namespace Tests\Unit;

use App\Services\ItTracker\PhaseEngine;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Mesin fase IT Tracker — uji terima rencana §14.6 no. 1, 3, 4, 5, 7, 8, 9, 10
 * di tingkat mesin (murni, tanpa DB). PhaseEngine adalah terjemahan setia
 * it-store.js; uji ini mengunci terjemahannya.
 */
class ItPhaseEngineTest extends TestCase
{
    private function tiket(array $override = []): array
    {
        return array_merge([
            'id'        => 'IT-2026-001',
            'title'     => 'Uji',
            'source'    => 'permintaan',
            'pemohon'   => ['name' => 'Della'],
            'jenjang'   => 'M',
            'prioIT'    => 'P1',
            'fase'      => 'DEV',
            'faseSince' => CarbonImmutable::now()->toIso8601String(),
            'evaluator' => null,
            'children'  => [],
        ], $override);
    }

    /** #1 — jenjang S melewati 2 fase, M 5, L 7. */
    public function test_path_for_sesuai_jenjang(): void
    {
        $this->assertCount(2, PhaseEngine::pathFor('S'));
        $this->assertCount(5, PhaseEngine::pathFor('M'));
        $this->assertCount(7, PhaseEngine::pathFor('L'));
        $this->assertSame(['REQ', 'DEV'], PhaseEngine::pathFor('S'));
    }

    /** #7 — tiket di UAT 4 hari → luar, heat=1 (kuning). */
    public function test_uat_empat_hari_kuning(): void
    {
        $now = CarbonImmutable::parse('2026-09-02 12:00:00');
        $w = PhaseEngine::waitOf($this->tiket([
            'fase' => 'UAT', 'faseSince' => $now->subDays(4)->toIso8601String(),
        ]), $now);

        $this->assertTrue($w['luar']);
        $this->assertSame(4, $w['days']);
        $this->assertSame(1, $w['heat']);
        $this->assertSame('Menunggu Della (UAT)', $w['label']);
    }

    /** #8 — tiket di UAT 8 hari → heat=2 (merah). */
    public function test_uat_delapan_hari_merah(): void
    {
        $now = CarbonImmutable::parse('2026-09-02 12:00:00');
        $w = PhaseEngine::waitOf($this->tiket([
            'fase' => 'UAT', 'faseSince' => $now->subDays(8)->toIso8601String(),
        ]), $now);

        $this->assertSame(2, $w['heat']);
    }

    /**
     * #9 — KENDALI NEGATIF PALING PENTING: tiket 30 hari di DEV wajib heat=0.
     * Yang menunggu IT tidak memanas — kalau ini merah, aturannya salah
     * diterjemahkan.
     */
    public function test_dev_tigapuluh_hari_tetap_dingin(): void
    {
        $now = CarbonImmutable::parse('2026-09-02 12:00:00');
        $w = PhaseEngine::waitOf($this->tiket([
            'fase' => 'DEV', 'faseSince' => $now->subDays(30)->toIso8601String(),
        ]), $now);

        $this->assertSame(30, $w['days']);
        $this->assertFalse($w['luar']);
        $this->assertSame(0, $w['heat'], 'Tiket di tangan IT TIDAK boleh memanas — rencana §12.');
    }

    /** #10 — angka hari dihitung dari faseSince (masuk_at), bukan tersimpan. */
    public function test_hari_dihitung_dari_masuk(): void
    {
        $now = CarbonImmutable::parse('2026-09-10 12:00:00');
        $t = $this->tiket(['fase' => 'GOLIVE', 'faseSince' => '2026-09-02T12:00:00+07:00']);

        // Tiket yang sama, "hari ini" berbeda → angka bergerak sendiri.
        $this->assertSame(8, PhaseEngine::waitOf($t, $now->timezone('+07:00'))['days']);
        $this->assertSame(9, PhaseEngine::waitOf($t, $now->addDay()->timezone('+07:00'))['days']);
    }

    /** #3 — gerbang ANALISIS→DEV menolak tanpa kriteria & MENYEBUT yang kurang. */
    public function test_gerbang_analisis_menyebut_yang_kurang(): void
    {
        $g = PhaseEngine::gateInfo($this->tiket(['fase' => 'ANALISIS', 'fr02' => ['fr' => []]]));

        $this->assertFalse($g['ok']);
        $this->assertContains('≥1 FR dengan Kriteria Diterima', $g['missing']);
        // ⚠️ KONTRAK BERUBAH SADAR (Fase 2b, rencana §14.13 TERKUNCI): r1 mewajibkan
        // ruang lingkup untuk semua jenjang; r2 hanya untuk L — "02 PENUH jangan
        // pernah muncul untuk M" — dan menambah paraf satu Business Owner.
        // Tiket uji ini jenjang M, jadi lingkup TIDAK boleh diminta.
        $this->assertNotContains('ruang lingkup', $g['missing']);
        $this->assertContains('komponen terdampak', $g['missing']);
        $this->assertContains('paraf satu Business Owner', $g['missing']);

        $lengkap = PhaseEngine::gateInfo($this->tiket(['fase' => 'ANALISIS', 'fr02' => [
            'fr'       => [['id' => 'FR-01', 'kriteria' => 'Selesai kalau X terlihat di layar Y.']],
            'komponen' => ['Aplikasi'],
            'paraf'    => ['oleh' => 'Robi', 'at' => '2026-09-03T10:00:00+07:00'],
        ]]));
        $this->assertTrue($lengkap['ok']);
        $this->assertSame('DEV', $lengkap['next']);
    }

    /** #4 — gerbang UJI→UAT menolak selama ada temuan Major terbuka. */
    public function test_gerbang_uji_menolak_major_terbuka(): void
    {
        $dasar = ['fase' => 'UJI', 'jenjang' => 'L', 'fr04' => [
            'tc'     => [['id' => 'TC-01', 'status' => 'pass']],
            'temuan' => [['desc' => 'error 500', 'tingkat' => 'Major', 'status' => 'Terbuka']],
        ]];

        $g = PhaseEngine::gateInfo($this->tiket($dasar));
        $this->assertFalse($g['ok']);
        // ⚠️ KONTRAK BERUBAH SADAR (Fase 2b, §14.14 no. 9): kalimatnya kini
        // MENYEBUT JUMLAH dan berakhiran "— menahan gerbang".
        $this->assertContains('1 temuan Major masih terbuka — menahan gerbang', $g['missing']);

        $dasar['fr04']['temuan'][0]['status'] = 'Selesai';
        $this->assertTrue(PhaseEngine::gateInfo($this->tiket($dasar))['ok']);
    }

    /** #5 — gerbang UAT→GOLIVE milik pemohon (actor), butuh keputusan setuju. */
    public function test_gerbang_uat_milik_pemohon(): void
    {
        $g = PhaseEngine::gateInfo($this->tiket(['fase' => 'UAT']));
        $this->assertSame('pemohon', $g['actor']);
        $this->assertFalse($g['ok']);

        $ok = PhaseEngine::gateInfo($this->tiket(['fase' => 'UAT', 'fr05' => ['keputusan' => 'setuju']]));
        $this->assertTrue($ok['ok']);
        $this->assertSame('GOLIVE', $ok['next']);
    }

    /** Jenjang S di DEV selesai lewat catatan singkat, bukan formulir penuh. */
    public function test_jenjang_s_selesai_lewat_catatan(): void
    {
        $tanpa = PhaseEngine::gateInfo($this->tiket(['jenjang' => 'S', 'fase' => 'DEV']));
        $this->assertFalse($tanpa['ok']);
        $this->assertSame(['catatan singkat pengerjaan'], $tanpa['missing']);

        $dengan = PhaseEngine::gateInfo($this->tiket(['jenjang' => 'S', 'fase' => 'DEV',
            'fr03' => ['note' => 'fix query export, commit f4a2c81']]));
        $this->assertTrue($dengan['ok']);
        $this->assertSame('SELESAI', $dengan['next']);
    }

    /** queuePos — di belakang berapa item aktif. */
    public function test_posisi_antrean(): void
    {
        $now = CarbonImmutable::now();
        $antre = $this->tiket(['id' => 'IT-2026-010', 'fase' => 'ANTRE', 'faseSince' => $now->toIso8601String()]);
        $semua = [
            $antre,
            $this->tiket(['id' => 'IT-2026-002', 'fase' => 'DEV']),
            $this->tiket(['id' => 'IT-2026-003', 'fase' => 'UAT']),
            $this->tiket(['id' => 'IT-2026-004', 'fase' => 'ANTRE',
                'faseSince' => $now->subDay()->toIso8601String()]), // antre lebih dulu
            $this->tiket(['id' => 'IT-2026-005', 'fase' => 'SELESAI']), // tak aktif, tak dihitung
        ];

        $this->assertSame(3, PhaseEngine::queuePos($antre, $semua));
    }

    /** progressOf — "UAT · 4/5" untuk jenjang M. */
    public function test_kemajuan_fase(): void
    {
        $p = PhaseEngine::progressOf($this->tiket(['fase' => 'UAT', 'jenjang' => 'M']));
        $this->assertSame('UAT · 4/5', $p['label']);

        $this->assertNull(PhaseEngine::progressOf($this->tiket(['jenjang' => null])));
    }
}
