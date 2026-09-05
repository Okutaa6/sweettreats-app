<?php

namespace Tests\Feature;

use App\Console\Commands\AttReconcile;
use App\Services\PenempatanBergeser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pagar `att:reconcile` atas tanggal lampau.
 *
 * Lahir dari kejadian 5 Sep 2026: `att:reconcile 2026-08-25` mengubah Alpajri
 * dari `libur` jadi `alpa`, karena penempatannya di Bintaro (yang libur Maulid)
 * ditutup 1 Sep. `att_day` dihitung dari keadaan organisasi HARI INI, jadi
 * menghitung ulang masa lalu menerapkan struktur sekarang ke masa lalu.
 *
 * ⚠️ Yang diuji di sini adalah **penolakannya**, bukan peringatannya.
 * Peringatan hanya menolong orang yang sudah curiga; yang menyebabkan kejadian
 * itu justru orang yang tidak curiga.
 */
class PagarReconcileLampauTest extends TestCase
{
    use DatabaseTransactions;

    private function jalankan(string $from, string $to, array $opsi = []): array
    {
        $kode = Artisan::call('att:reconcile', ['from' => $from, 'to' => $to] + $opsi);

        return [$kode, Artisan::output()];
    }

    public function test_rentang_baru_tetap_boleh_jalan(): void
    {
        // Penjadwal harian memakai kemarin+hari ini — ia TIDAK boleh ikut kena
        // pagar, kalau tidak absensi berhenti diperbarui tanpa ada yang sadar.
        [$kode, $out] = $this->jalankan(
            Carbon::yesterday()->toDateString(),
            Carbon::today()->toDateString()
        );

        $this->assertSame(0, $kode);
        $this->assertStringContainsString('Rekonsiliasi', $out);
        $this->assertStringNotContainsString('Ditolak', $out);
    }

    public function test_tepat_di_batas_masih_boleh(): void
    {
        [$kode, $out] = $this->jalankan(
            Carbon::today()->subDays(AttReconcile::BATAS_HARI)->toDateString(),
            Carbon::today()->toDateString()
        );

        $this->assertSame(0, $kode, 'Batasnya inklusif — tepat N hari masih lewat.');
        $this->assertStringNotContainsString('Ditolak', $out);
    }

    public function test_lebih_tua_dari_batas_DITOLAK(): void
    {
        $tua = Carbon::today()->subDays(AttReconcile::BATAS_HARI + 1)->toDateString();

        [$kode, $out] = $this->jalankan($tua, $tua);

        $this->assertSame(1, $kode, 'Harus GAGAL, bukan sekadar memperingatkan.');
        $this->assertStringContainsString('Ditolak', $out);
        $this->assertStringContainsString('--force', $out, 'Penolakan wajib menyebut jalan keluarnya.');
    }

    public function test_force_menembus_pagar(): void
    {
        $tua = Carbon::today()->subDays(AttReconcile::BATAS_HARI + 1)->toDateString();

        [$kode, $out] = $this->jalankan($tua, $tua, ['--force' => true]);

        $this->assertSame(0, $kode);
        $this->assertStringContainsString('--force', $out);
        $this->assertStringContainsString('Rekonsiliasi', $out, 'Sesudah ditembus, ia benar-benar jalan.');
    }

    public function test_penolakan_menyebut_siapa_yang_penempatannya_bergeser(): void
    {
        // Inti nilainya: menolak saja tak menolong. Yang menolong adalah tahu
        // SIAPA yang harinya kemungkinan bergeser di rentang itu.
        if (! \Illuminate\Support\Facades\Schema::hasTable('sys_user_position')) {
            $this->markTestSkipped('sys_user_position tak ada di environment ini.');
        }

        $orang = DB::table('users')->where('status', 1)->first(['id', 'nama']);
        $cab   = DB::table('raw_branch')->first(['id']);
        if (! $orang || ! $cab) $this->markTestSkipped('Data dasar tak cukup.');

        $dari   = Carbon::today()->subDays(60)->toDateString();
        $sampai = Carbon::today()->subDays(30)->toDateString();

        DB::table('sys_user_position')->insert([
            'id'             => (string) \Illuminate\Support\Str::uuid(),
            'user_id'        => $orang->id,
            'raw_branch_id'  => $cab->id,
            'kind'           => 'rolling',
            'is_primary'     => 1,
            'confirmed_at'   => now(),
            'effective_from' => $dari,
            'effective_to'   => $sampai,      // sudah berakhir = bergeser
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $geser = PenempatanBergeser::dalamRentang($dari, $sampai);
        $this->assertNotEmpty($geser, 'Penempatan yang sudah berakhir wajib terdeteksi.');
        $this->assertContains($orang->nama, array_column($geser, 'nama'));

        [$kode, $out] = $this->jalankan($dari, $sampai);
        $this->assertSame(1, $kode);
        $this->assertStringContainsString('BERGESER', $out);
        $this->assertStringContainsString($orang->nama, $out,
            'Namanya harus TERCETAK — daftar tanpa nama tak menolong siapa pun.');
    }

    public function test_rentang_terbalik_tetap_ditolak_lebih_dulu(): void
    {
        // Pagar baru tak boleh menelan pemeriksaan lama.
        [$kode, $out] = $this->jalankan(
            Carbon::today()->toDateString(),
            Carbon::yesterday()->toDateString()
        );

        $this->assertSame(1, $kode);
        $this->assertStringContainsString('terbalik', $out);
    }
}
