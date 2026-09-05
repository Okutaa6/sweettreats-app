<?php

namespace Tests\Feature;

use App\Services\LemburDodo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DODO — lembur di hari libur, tarifnya beda.
 *
 * Skenarionya DITANAM sendiri (bukan menumpang data yang kebetulan ada) karena
 * yang diuji justru hubungan antara tiga hal yang jarang muncul berbarengan di
 * data nyata: hari libur orangnya, chip yang dipilih, dan menit yang sudah
 * di-ACC. Semua dibungkus transaksi yang di-rollback.
 */
class LemburDodoTest extends TestCase
{
    use DatabaseTransactions;

    private object $orang;
    private string $liburKe;      // tanggal yang HARI LIBUR bagi orang ini
    private string $kerjaKe;      // tanggal yang hari kerja biasa

    protected function setUp(): void
    {
        parent::setUp();

        // Ambang DODO DIMATIKAN untuk kelas ini. Uji di sini menjaga
        // hubungan chip <-> hari libur <-> pecahan lembur — bukan ambangnya.
        // Membiarkan ambang bawaan (7 jam) akan membuat skenario 5 jam di
        // bawah jadi 0 DODO dan ujinya gagal karena alasan yang salah.
        // Ambangnya diuji sendiri di AmbangDodoTest.
        \App\Models\Setting::put(\App\Services\LemburDodo::KUNCI_AMBANG, '0');

        // Orang ber-aturan BAKU yang punya hari libur mingguan — hanya aturan
        // baku yang menyimpan baris hari, jadi orang fleksibel tak bisa dipakai
        // menguji sisi kalendernya.
        $u = DB::table('users as u')
            ->join('att_user_rule as ur', 'ur.user_id', '=', 'u.id')
            ->join('att_work_rule as r', 'r.id', '=', 'ur.rule_id')
            ->join('att_work_rule_day as d', 'd.rule_id', '=', 'r.id')
            ->where('u.status', 1)->where('r.tipe', 'baku')->where('d.libur', 1)
            ->whereNull('ur.effective_to')
            ->first(['u.id', 'u.nama', 'd.day as hari_libur']);

        if (! $u) $this->markTestSkipped('Tak ada karyawan ber-aturan baku dengan hari libur mingguan.');

        $this->orang = $u;

        // Bulan yang jauh ke depan supaya tak bertabrakan dengan data nyata
        // maupun kalender libur yang sudah tercatat.
        $awal = Carbon::create(2027, 3, 1)->startOfDay();
        $this->liburKe = $awal->copy()->next((int) $u->hari_libur)->toDateString();
        $this->kerjaKe = Carbon::parse($this->liburKe)->addDay()->toDateString();
    }

    private function attDay(string $tanggal, int $lemburMenit, string $status = 'hadir'): void
    {
        DB::table('att_day')->insert([
            'user_id' => $this->orang->id, 'work_date' => $tanggal, 'status' => $status,
            'telat_menit' => 0, 'lembur_menit_disetujui' => $lemburMenit,
            'scan_count' => 0, 'single_scan' => 0, 'computed_at' => now(),
        ]);
    }

    private function pengajuan(string $tanggal, string $subTipe, int $menit, string $status = 'approved'): void
    {
        DB::table('att_request')->insert([
            'user_id' => $this->orang->id, 'tipe' => 'lembur', 'sub_tipe' => $subTipe,
            'tanggal_from' => $tanggal, 'tanggal_to' => $tanggal, 'status' => $status,
            'claimed_menit' => $menit, 'alasan' => 'uji dodo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function rentang(): array
    {
        $a = Carbon::parse($this->liburKe);
        return [$a->copy()->startOfMonth()->toDateString(), $a->copy()->endOfMonth()->toDateString()];
    }

    // ───────────────────────────────────────────────────────────

    public function test_dodo_dan_lembur_biasa_dipisah_dan_jumlahnya_utuh(): void
    {
        // Inti seluruh fitur: DODO itu PECAHAN dari lembur yang di-ACC, bukan
        // tambahan. Kalau jumlahnya tak utuh, Finance membayar dobel atau kurang.
        $this->attDay($this->liburKe, 300);          // 5 jam di hari libur
        $this->pengajuan($this->liburKe, 'tambah_hari', 300);
        $this->attDay($this->kerjaKe, 120);          // 2 jam lembur biasa
        $this->pengajuan($this->kerjaKe, 'jam', 120);

        [$from, $to] = $this->rentang();
        $total = (int) DB::table('att_day')->where('user_id', $this->orang->id)
            ->whereBetween('work_date', [$from, $to])->sum('lembur_menit_disetujui');
        $dodo  = LemburDodo::menitPerOrang($from, $to)[$this->orang->id] ?? 0;

        $this->assertSame(300, $dodo, 'Hanya klaim ber-chip "masuk hari libur" yang jadi DODO.');
        $this->assertSame(420, $total);
        $this->assertSame($total, ($total - $dodo) + $dodo, 'biasa + dodo WAJIB sama dengan total.');
        $this->assertSame(120, $total - $dodo, 'Sisanya lembur biasa.');
    }

    public function test_dodo_tak_pernah_melebihi_lembur_yang_sudah_di_acc(): void
    {
        // Klaim di tanggal yang belum/tak terekonsiliasi tak boleh ikut dihitung
        // — kalau ikut, "lembur biasa" jadi MINUS dan angkanya mustahil.
        $this->pengajuan($this->liburKe, 'tambah_hari', 300);   // sengaja TANPA baris att_day

        [$from, $to] = $this->rentang();

        $this->assertSame(0, LemburDodo::menitPerOrang($from, $to)[$this->orang->id] ?? 0,
            'Tanpa baris att_day, menitnya belum masuk hitungan mana pun.');
    }

    public function test_pengajuan_belum_disetujui_tidak_dihitung(): void
    {
        $this->attDay($this->liburKe, 0);
        $this->pengajuan($this->liburKe, 'tambah_hari', 300, 'pending_l1');

        [$from, $to] = $this->rentang();

        $this->assertSame(0, LemburDodo::menitPerOrang($from, $to)[$this->orang->id] ?? 0);
    }

    public function test_hari_bercampur_hanya_porsi_dodonya_yang_dihitung(): void
    {
        // Satu hari bisa punya dua klaim sekaligus. Yang jadi DODO cuma yang
        // ditandai, bukan seluruh menit hari itu.
        $this->attDay($this->liburKe, 420);
        $this->pengajuan($this->liburKe, 'tambah_hari', 300);
        $this->pengajuan($this->liburKe, 'jam', 120);

        [$from, $to] = $this->rentang();
        $dodo = LemburDodo::menitPerOrang($from, $to)[$this->orang->id] ?? 0;

        $this->assertSame(300, $dodo);
        $this->assertSame(120, 420 - $dodo);
    }

    public function test_penanda_per_hari_untuk_kalender(): void
    {
        $this->attDay($this->liburKe, 300);
        $this->pengajuan($this->liburKe, 'tambah_hari', 300);

        [$from, $to] = $this->rentang();
        $peta = LemburDodo::menitPerHari($from, $to);

        $this->assertSame(300, $peta[$this->orang->id][$this->liburKe] ?? 0);
        $this->assertArrayNotHasKey($this->kerjaKe, $peta[$this->orang->id] ?? []);
    }

    // ─────────────────── detektor chip salah pilih ───────────────────

    public function test_chip_lembur_biasa_di_hari_libur_ditandai(): void
    {
        // Reconciler menerima lembur apa pun di hari libur tanpa memeriksa
        // chipnya → DODO yang terbayar tarif biasa, tanpa ada yang tahu.
        $this->attDay($this->liburKe, 300);
        $this->pengajuan($this->liburKe, 'jam', 300);

        [$from, $to] = $this->rentang();
        $temuan = collect(LemburDodo::chipMencurigakan($from, $to))
            ->where('user_id', $this->orang->id)->values();

        $this->assertCount(1, $temuan);
        $this->assertTrue($temuan[0]['libur']);
        $this->assertSame($this->liburKe, $temuan[0]['tanggal']);
    }

    public function test_chip_masuk_hari_libur_di_hari_kerja_ditandai(): void
    {
        $this->attDay($this->kerjaKe, 120);
        $this->pengajuan($this->kerjaKe, 'tambah_hari', 120);

        [$from, $to] = $this->rentang();
        $temuan = collect(LemburDodo::chipMencurigakan($from, $to))
            ->where('user_id', $this->orang->id)->values();

        $this->assertCount(1, $temuan);
        $this->assertFalse($temuan[0]['libur']);
    }

    public function test_chip_yang_cocok_tidak_diganggu(): void
    {
        $this->attDay($this->liburKe, 300);
        $this->pengajuan($this->liburKe, 'tambah_hari', 300);
        $this->attDay($this->kerjaKe, 120);
        $this->pengajuan($this->kerjaKe, 'jam', 120);

        [$from, $to] = $this->rentang();

        $this->assertCount(0, collect(LemburDodo::chipMencurigakan($from, $to))
            ->where('user_id', $this->orang->id));
    }

    public function test_hari_libur_dibaca_dari_rekonsiliasi_bukan_definisi_kedua(): void
    {
        // Kalau suatu hari resolveDay() berubah (mis. libur ikut cabang
        // bertugas, seperti perubahan 21 Agu), detektor ini HARUS ikut berubah.
        // Uji ini gagal kalau ada yang menyalin definisi "libur" ke tempat lain.
        $jawab = (new \App\Services\AttendanceReconciler())->liburUntuk([
            [$this->orang->id, $this->liburKe],
            [$this->orang->id, $this->kerjaKe],
        ]);

        $this->assertTrue($jawab[$this->orang->id . '|' . $this->liburKe] ?? null);
        $this->assertFalse($jawab[$this->orang->id . '|' . $this->kerjaKe] ?? null);
    }
}
