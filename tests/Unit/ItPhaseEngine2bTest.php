<?php

namespace Tests\Unit;

use App\Services\ItTracker\PhaseEngine;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Mesin fase — tambahan RONDE 2 (Fase 2b). Kontrak: pathFor / RANK / aturan
 * panas IDENTIK dengan r1; gateInfo ADITIF (`missing` tetap, `checks[]`
 * ditambah). Uji ini mengunci bagian aditifnya; kendali kontrak lamanya ada
 * di ItPhaseEngineTest (harus tetap hijau).
 */
class ItPhaseEngine2bTest extends TestCase
{
    private function tiket(array $o = []): array
    {
        return array_merge([
            'id' => 'IT-2026-001', 'title' => 'Uji', 'source' => 'permintaan',
            'pemohon' => ['name' => 'Della'], 'jenjang' => 'M', 'prioIT' => 'P1',
            'fase' => 'ANALISIS', 'faseSince' => CarbonImmutable::now()->toIso8601String(),
            'evaluator' => null, 'children' => [],
        ], $o);
    }

    /** Kontrak lama tak bergeser: jalur, peringkat, panas. */
    public function test_kontrak_r1_identik(): void
    {
        $this->assertSame(['REQ', 'DEV'], PhaseEngine::pathFor('S'));
        $this->assertSame(['REQ', 'ANALISIS', 'DEV', 'UAT', 'GOLIVE'], PhaseEngine::pathFor('M'));
        $this->assertSame(['REQ', 'ANALISIS', 'DEV', 'UJI', 'UAT', 'GOLIVE', 'EVALUASI'], PhaseEngine::pathFor('L'));
        $this->assertSame(['TRIASE' => 0, 'ANTRE' => 1, 'ANALISIS' => 2, 'DEV' => 3, 'UJI' => 4, 'UAT' => 5, 'GOLIVE' => 6, 'EVALUASI' => 7], PhaseEngine::RANK);

        $now = CarbonImmutable::parse('2026-09-03 12:00:00');
        $w = PhaseEngine::waitOf($this->tiket(['fase' => 'DEV', 'faseSince' => $now->subDays(30)->toIso8601String()]), $now);
        $this->assertSame(0, $w['heat'], 'Yang menunggu IT tidak memanas — masih.');
    }

    /** checks[] ADITIF: missing tetap ada dan sama dengan lampu yang mati. */
    public function test_checks_aditif_dan_konsisten_dengan_missing(): void
    {
        foreach (['ANALISIS', 'UJI', 'UAT', 'EVALUASI'] as $fase) {
            $g = PhaseEngine::gateInfo($this->tiket(['fase' => $fase, 'jenjang' => 'L']));
            $this->assertArrayHasKey('missing', $g, $fase);
            $this->assertArrayHasKey('checks', $g, $fase);
            $mati = array_values(array_map(fn ($c) => $c['t'], array_filter($g['checks'], fn ($c) => ! $c['ok'])));
            $this->assertSame(! $mati, $g['ok'], "$fase: ok harus = semua lampu menyala");
            $this->assertCount(count($mati), $g['missing'], "$fase: satu entri missing per lampu mati");
            // Teks missing == teks lampu HANYA di fase yang acuannya memang
            // menurunkannya dari checks (ANALISIS, UJI). Untuk UAT/EVALUASI, r2
            // sengaja memakai kalimat missing yang berbeda dari lampunya —
            // engine meniru acuan, jadi jangan dipaksa sama di sini.
            if (in_array($fase, ['ANALISIS', 'UJI'], true)) {
                $this->assertSame($mati, $g['missing'], "$fase: missing harus = lampu yang mati");
            }
        }
    }

    /** M tidak pernah diminta ruang lingkup; L diminta. Paraf BO wajib keduanya. */
    public function test_kedalaman_02_per_jenjang(): void
    {
        $M = PhaseEngine::gateInfo($this->tiket(['jenjang' => 'M']));
        $L = PhaseEngine::gateInfo($this->tiket(['jenjang' => 'L']));

        $teksM = array_column($M['checks'], 't');
        $teksL = array_column($L['checks'], 't');

        $this->assertCount(3, $M['checks'], 'M: FR · komponen · paraf — TIDAK ADA lingkup');
        $this->assertCount(4, $L['checks'], 'L: + ruang lingkup');
        $this->assertNotContains('ruang lingkup termasuk / tidak termasuk (02 penuh)', $teksM);
        $this->assertContains('ruang lingkup termasuk / tidak termasuk (02 penuh)', $teksL);
        $this->assertContains('paraf satu Business Owner', $teksM);
        $this->assertContains('paraf satu Business Owner', $teksL);
    }

    /** Gerbang UJI: tiga lampu, kalimat Major MENYEBUT JUMLAH + "menahan gerbang". */
    public function test_gerbang_uji_tiga_lampu(): void
    {
        $g = PhaseEngine::gateInfo($this->tiket(['fase' => 'UJI', 'jenjang' => 'L', 'fr04' => [
            'tc' => [['status' => 'pass'], ['status' => null], ['status' => 'fail']],
            'temuan' => [['tingkat' => 'Major', 'status' => 'Terbuka'], ['tingkat' => 'Major', 'status' => 'Terbuka'], ['tingkat' => 'Minor', 'status' => 'Terbuka']],
        ]]));

        $this->assertFalse($g['ok']);
        $this->assertSame(['1 test case belum berstatus', '1 test case masih Fail', '2 temuan Major masih terbuka — menahan gerbang'], $g['missing']);

        $ok = PhaseEngine::gateInfo($this->tiket(['fase' => 'UJI', 'jenjang' => 'L', 'fr04' => [
            'tc' => [['status' => 'pass']], 'temuan' => [['tingkat' => 'Major', 'status' => 'Selesai'], ['tingkat' => 'Minor', 'status' => 'Terbuka']],
        ]]));
        $this->assertTrue($ok['ok'], 'Major selesai + Minor terbuka → gerbang terbuka (Minor boleh dibawa).');
    }

    /** UAT: "Disetujui dengan Catatan" juga membuka; belum diputuskan → tertutup. */
    public function test_gerbang_uat_menerima_catatan(): void
    {
        $this->assertTrue(PhaseEngine::gateInfo($this->tiket(['fase' => 'UAT', 'fr05' => ['keputusan' => 'catatan']]))['ok']);
        $this->assertTrue(PhaseEngine::gateInfo($this->tiket(['fase' => 'UAT', 'fr05' => ['keputusan' => 'setuju']]))['ok']);
        $this->assertFalse(PhaseEngine::gateInfo($this->tiket(['fase' => 'UAT', 'fr05' => ['keputusan' => 'perbaikan']]))['ok']);
        $this->assertFalse(PhaseEngine::gateInfo($this->tiket(['fase' => 'UAT']))['ok']);
    }

    /** EVALUASI: lima aspek membuka; RTL yang belum bertiket TIDAK menahan. */
    public function test_gerbang_evaluasi_lima_aspek(): void
    {
        $g = PhaseEngine::gateInfo($this->tiket(['fase' => 'EVALUASI', 'jenjang' => 'L',
            'fr07' => ['aspek' => ['sesuai' => 4, 'stabil' => 5], 'rtl' => [['txt' => 'x', 'ticketId' => null]]]]));
        $this->assertFalse($g['ok']);
        $this->assertSame('2/5 aspek dinilai', $g['checks'][0]['t']);
        $this->assertTrue($g['checks'][1]['ok'], 'RTL belum bertiket boleh dibiarkan.');

        $penuh = PhaseEngine::gateInfo($this->tiket(['fase' => 'EVALUASI', 'jenjang' => 'L',
            'fr07' => ['aspek' => ['sesuai' => 4, 'mudah' => 3, 'stabil' => 5, 'dampak' => 4, 'manfaat' => 5]]]));
        $this->assertTrue($penuh['ok']);
        $this->assertSame('SELESAI', $penuh['next']);
    }

    /** Konstanta ronde 2 ada dan berbentuk benar. */
    public function test_konstanta_ronde2(): void
    {
        $this->assertCount(5, PhaseEngine::TC_TETAP);
        $this->assertSame('TC-05', PhaseEngine::TC_TETAP[4]['id']);
        $this->assertStringContainsString('Fitur lama terkait tetap berjalan', PhaseEngine::TC_TETAP[4]['skenario']);
        $this->assertSame(['sesuai', 'mudah', 'stabil', 'dampak', 'manfaat'], array_column(PhaseEngine::EVAL_ASPEK, 'k'));
    }
}
