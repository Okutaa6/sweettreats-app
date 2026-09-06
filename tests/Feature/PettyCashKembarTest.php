<?php

namespace Tests\Feature;

use App\Models\PettyCashTransaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Petty cash — kiriman ganda tidak lagi melahirkan dua baris.
 *
 * Diagnosis 6 Sep 2026: form tambah pengeluaran tak punya pengaman klik ganda;
 * dua baris identik lahir berjarak 0 dan 4 detik (Firda #187/#188, #344/#345).
 * Pengaman tombol dipasang di layar, tapi PAGAR-nya di server: kembar PERSIS
 * dalam 10 detik ditolak dengan flash ramah, MIRIP tetap boleh.
 *
 * Diuji lewat POST /pettycash/expense sungguhan — dua kiriman identik dalam
 * satu detik → satu baris; dua kiriman beda keterangan → dua baris.
 */
class PettyCashKembarTest extends TestCase
{
    use DatabaseTransactions;

    private object $pembuat;
    private int $periodId;
    private int $sourceId;
    private string $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        // Pembuat = akun sungguhan non-Finance TANPA cabang terkunci (admin):
        // canEditPettyCash() = !isFinance(), dan lockedBranchId() null → tak ada
        // pemeriksaan cabang yang bisa menolak fixture.
        $this->pembuat = DB::table('users as u')->join('group_path as g', 'g.id', '=', 'u.group_id')
            ->where('u.status', 1)->whereNull('u.raw_branch_id')
            ->whereIn('g.nama', ['Super Admin', 'Admin General'])
            ->first(['u.id', 'u.nama', 'u.group_id', 'g.nama as role'])
            ?? $this->markTestSkipped('Tak ada akun admin tanpa cabang.');

        $cabang = DB::table('raw_branch')->value('id');
        $this->sourceId = (int) DB::table('fund_sources')->where('is_active', 1)->value('id');
        $this->kategori = PettyCashTransaction::activeCategoryKeys()[0] ?? '';
        if (! $cabang || ! $this->sourceId || $this->kategori === '') $this->markTestSkipped('Data dasar petty cash tak cukup.');

        // Periode uji jauh di depan, saldo awal sumber dana diisi supaya guard
        // "sumber belum punya saldo" tidak ikut menolak.
        try {
            $this->periodId = (int) DB::table('petty_cash_periods')->insertGetId([
                'period_year'             => 2031,
                'period_month'            => 3,
                'raw_branch_id'           => $cabang,
                'float_amount'            => 1500000,
                'opening_balance'         => 0,
                'source_opening_balances' => json_encode([$this->sourceId => 1000000]),
                'is_closed'               => 0,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Skema petty_cash_periods di environment ini berbeda: ' . $e->getMessage());
        }
    }

    private function kirim(string $keterangan)
    {
        return $this->withSession([
            'isLoggedIn' => true, 'id' => $this->pembuat->id, 'group_id' => $this->pembuat->group_id,
            'group_name' => $this->pembuat->role, 'name' => $this->pembuat->nama,
            'raw_branch_id' => null, 'branch_day' => date('Y-m-d'),
            'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time(),
        ])->post('/pettycash/expense?tab=petty_cash', [
            'period_id'      => $this->periodId,
            'trx_date'       => '2031-03-05',
            'description'    => $keterangan,
            'category'       => $this->kategori,
            'amount'         => 62900,
            'fund_source_id' => $this->sourceId,
        ]);
    }

    private function jumlahBaris(): int
    {
        return PettyCashTransaction::where('period_id', $this->periodId)->where('created_by', $this->pembuat->id)->count();
    }

    // ───────────────────────── lewat HTTP sungguhan ─────────────────────────

    public function test_dua_post_identik_dalam_satu_detik_hanya_satu_baris_dan_flash_ramah(): void
    {
        $r1 = $this->kirim('reimburse gojek th riska');
        $r1->assertStatus(302);
        $this->assertSame(1, $this->jumlahBaris());
        $this->assertStringContainsString('berhasil', (string) $r1->getSession()->get('success'));

        $r2 = $this->kirim('reimburse gojek th riska');   // klik kedua, <1 detik kemudian
        $r2->assertStatus(302);

        $this->assertSame(1, $this->jumlahBaris(), 'Kembar persis dalam jendela → TIDAK boleh jadi baris kedua.');
        $this->assertNull($r2->getSession()->get('error'), 'Dibalas RAMAH — bukan galat. Dari sudut pandang orangnya pengeluarannya memang sudah tersimpan.');
        $this->assertStringContainsString('baru saja tersimpan', (string) $r2->getSession()->get('success'));
    }

    public function test_dua_post_beda_keterangan_tetap_dua_baris(): void
    {
        // Kendali negatif — pagar tak boleh menelan pengeluaran sungguhan yang
        // kebetulan mirip. Dua "gojek" di hari yang sama itu wajar.
        $this->kirim('reimburse gojek th riska')->assertStatus(302);
        $this->kirim('reimburse gojek th riska pp')->assertStatus(302);

        $this->assertSame(2, $this->jumlahBaris(), 'MIRIP tetap boleh — hanya kembar PERSIS yang ditolak.');
    }

    // ───────────────────────── pagarnya sendiri ─────────────────────────

    public function test_kembar_di_luar_jendela_bukan_kembar(): void
    {
        // Pengeluaran identik 30 detik kemudian bisa saja memang dua kali
        // (dua tiket parkir). Jendelanya sempit dengan sengaja.
        $this->kirim('parkir')->assertStatus(302);
        PettyCashTransaction::where('period_id', $this->periodId)->update(['created_at' => now()->subSeconds(30)]);

        $this->assertFalse(PettyCashTransaction::kembarBaruSaja($this->pembuat->id, 62900, 'parkir', '2031-03-05'));
        $this->kirim('parkir')->assertStatus(302);
        $this->assertSame(2, $this->jumlahBaris());
    }

    public function test_pembuat_berbeda_bukan_kembar(): void
    {
        // Dua orang mencatat pengeluaran identik = dua pengeluaran.
        $this->kirim('materai')->assertStatus(302);

        $this->assertTrue(PettyCashTransaction::kembarBaruSaja($this->pembuat->id, 62900, 'materai', '2031-03-05'));
        $this->assertFalse(PettyCashTransaction::kembarBaruSaja('orang-lain', 62900, 'materai', '2031-03-05'));
        $this->assertFalse(PettyCashTransaction::kembarBaruSaja(null, 62900, 'materai', '2031-03-05'),
            'Tanpa pembuat tak ada yang bisa dibandingkan — jangan menolak berdasarkan ketidaktahuan.');
    }

    public function test_jendela_sepuluh_detik(): void
    {
        $this->assertSame(10, PettyCashTransaction::JENDELA_KEMBAR_DETIK,
            'Kalau angka ini berubah, catatan di diagnosis (0 & 4 detik) harus dibaca ulang dulu.');
    }
}
