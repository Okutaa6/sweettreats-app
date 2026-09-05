<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ekspor Kehadiran ber-rentang bebas.
 *
 * ⚠️ Status 200 tak membuktikan apa-apa di aplikasi ini: RBAC memantulkan
 * (302 ke beranda / signin), bukan 403, jadi pemeriksaan yang cuma melihat
 * kode status bisa HIJAU PALSU sambil sebenarnya mengunduh halaman login.
 * Karena itu tiap uji memeriksa ISI CSV-nya.
 */
class KehadiranEksporTest extends TestCase
{
    use DatabaseTransactions;

    private object $orang;

    protected function setUp(): void
    {
        parent::setUp();

        $u = DB::table('users as u')
            ->join('att_user_rule as ur', 'ur.user_id', '=', 'u.id')
            ->join('att_work_rule as r', 'r.id', '=', 'ur.rule_id')
            ->where('u.status', 1)->where('r.tipe', 'baku')
            ->whereNull('ur.effective_to')
            ->first(['u.id', 'u.nama', 'u.group_id', 'u.raw_branch_id']);

        if (! $u) $this->markTestSkipped('Tak ada karyawan ber-aturan baku.');

        $this->orang = $u;
    }

    /** Sesi Finance — canSeeAllBranches() membaca group_name, bukan tabel. */
    private function sesiFinance(): array
    {
        return [
            'isLoggedIn' => true,
            'id'         => $this->orang->id,
            'group_id'   => $this->orang->group_id,
            'group_name' => 'Finance',
            'name'       => 'Uji Finance',
            'raw_branch_id' => $this->orang->raw_branch_id,
            'branch_day' => date('Y-m-d'),
            'expires_in' => time() + 3600,
            'login_at'   => time(),
            'pa_touch'   => time(),
        ];
    }

    private function attDay(string $tanggal, string $status): void
    {
        DB::table('att_day')->where('user_id', $this->orang->id)->whereDate('work_date', $tanggal)->delete();
        DB::table('att_day')->insert([
            'user_id' => $this->orang->id, 'work_date' => $tanggal, 'status' => $status,
            'telat_menit' => 0, 'lembur_menit_disetujui' => 0,
            'scan_count' => 0, 'single_scan' => 0, 'computed_at' => now(),
        ]);
    }

    private function unduh(array $params): string
    {
        $res = $this->withSession($this->sesiFinance())->get('/absensi/kehadiran/export?' . http_build_query($params));
        $res->assertStatus(200);

        return $res->streamedContent();
    }

    // ───────────────────────────────────────────────────────────

    public function test_rentang_bebas_tidak_terikat_periode_gaji(): void
    {
        // Inti permintaannya: "1–15 Agustus" tanpa harus membuat periode dulu.
        $this->attDay('2027-05-03', 'hadir');
        $this->attDay('2027-05-20', 'hadir');

        $csv = $this->unduh(['from' => '2027-05-01', 'to' => '2027-05-15']);

        $this->assertStringContainsString('Nama', $csv, 'Header CSV harus ikut terkirim.');
        $this->assertStringContainsString($this->orang->nama, $csv);

        $baris = $this->barisOrang($csv);
        $this->assertSame('1', $baris[3] ?? null,
            'Hanya hari di dalam rentang yang boleh terhitung — 20 Mei di luar rentang.');
    }

    public function test_kolom_dodo_terpisah_dari_lembur_biasa(): void
    {
        // Kolom yang isinya menyusut TIDAK boleh memakai nama lama — pelajaran
        // yang sama dengan ekspor Lembar Gaji: spreadsheet Finance yang lama
        // akan memakai angka yang mengecil tanpa satu pun tanda.
        $csv = $this->unduh(['from' => '2027-05-01', 'to' => '2027-05-15']);

        $this->assertStringContainsString('Lembur Biasa (jam)', $csv);
        $this->assertStringContainsString('DODO (jam)', $csv);
        $this->assertStringNotContainsString('"Lembur (jam)"', $csv);
    }

    public function test_orang_tanpa_data_tetap_muncul_dengan_nol(): void
    {
        // Roster diambil dari aturan kerja, bukan att_day. Kalau ikut att_day,
        // orang kantor yang memang tak nge-tap hilang diam-diam dari rekap.
        DB::table('att_day')->where('user_id', $this->orang->id)
            ->whereBetween('work_date', ['2027-07-01', '2027-07-31'])->delete();

        $csv = $this->unduh(['from' => '2027-07-01', 'to' => '2027-07-31']);

        $this->assertStringContainsString($this->orang->nama, $csv,
            'Orang tanpa satu pun baris di rentang ini tetap harus tampil.');
    }

    public function test_rentang_terbalik_ditolak(): void
    {
        $res = $this->withSession($this->sesiFinance())
            ->get('/absensi/kehadiran/export?from=2027-05-20&to=2027-05-01');

        $res->assertSessionHasErrors('to');
    }

    public function test_bukan_finance_tidak_bisa_mengunduh(): void
    {
        // Kendali negatif untuk gerbangnya. Tanpa ini, "200 + ada isinya" tak
        // membuktikan gerbangnya bekerja — cuma membuktikan halamannya hidup.
        $sesi = $this->sesiFinance();
        $sesi['group_name'] = 'Supervisor';

        $res = $this->withSession($sesi)->get('/absensi/kehadiran/export?from=2027-05-01&to=2027-05-15');

        $this->assertNotSame(200, $res->getStatusCode(),
            'Role tanpa canSeeAllBranches() tak boleh mengunduh rekap seluruh cabang.');
    }

    /** Ambil baris CSV milik orang uji, sebagai array kolom. */
    private function barisOrang(string $csv): array
    {
        foreach (explode("\n", $csv) as $l) {
            $kol = str_getcsv(trim($l));
            if (($kol[0] ?? null) === $this->orang->nama) return $kol;
        }

        $this->fail('Baris untuk ' . $this->orang->nama . ' tak ditemukan di CSV.');
    }
}
