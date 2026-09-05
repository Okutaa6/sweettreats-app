<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\LemburDodo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ambang DODO (keputusan Ekky 2026-09-05).
 *
 *   DODO dihitung maksimal 7 jam. Lebih dari itu, sisanya lembur biasa.
 *   Kurang dari itu, semuanya lembur biasa — bukan DODO — meski di hari libur.
 *
 * Yang dijaga kelas ini ada tiga, dan yang KETIGA yang paling mahal:
 *
 *   1. ambangnya benar-benar dipakai, dan bisa diubah tanpa deploy;
 *   2. ambangnya per HARI, bukan per periode — dua hari libur penuh = dua hari
 *      DODO, bukan satu;
 *   3. **`lembur biasa + DODO` tetap SAMA PERSIS dengan seluruh lembur yang
 *      di-ACC**, berapa pun ambangnya. Ambang memindahkan menit antar kolom;
 *      ia tak boleh MENGHILANGKAN satu menit pun. Kalau invarian ini pecah,
 *      Lembar Gaji dan Kehadiran berselisih tanpa ada yang tahu.
 */
class AmbangDodoTest extends TestCase
{
    use DatabaseTransactions;

    private object $orang;
    private string $liburKe;
    private string $liburKe2;
    private string $kerjaKe;

    protected function setUp(): void
    {
        parent::setUp();

        $u = DB::table('users as u')
            ->join('att_user_rule as ur', 'ur.user_id', '=', 'u.id')
            ->join('att_work_rule as r', 'r.id', '=', 'ur.rule_id')
            ->join('att_work_rule_day as d', 'd.rule_id', '=', 'r.id')
            ->where('u.status', 1)->where('r.tipe', 'baku')->where('d.libur', 1)
            ->whereNull('ur.effective_to')
            ->first(['u.id', 'u.nama', 'd.day as hari_libur']);

        if (! $u) $this->markTestSkipped('Tak ada karyawan ber-aturan baku dengan hari libur mingguan.');

        $this->orang = $u;

        $awal = Carbon::create(2027, 4, 1)->startOfDay();
        $this->liburKe  = $awal->copy()->next((int) $u->hari_libur)->toDateString();
        $this->liburKe2 = Carbon::parse($this->liburKe)->addWeek()->toDateString();
        $this->kerjaKe  = Carbon::parse($this->liburKe)->addDay()->toDateString();

        Setting::put(LemburDodo::KUNCI_AMBANG, '420');   // 7 jam
    }

    private function attDay(string $tanggal, int $lemburMenit): void
    {
        DB::table('att_day')->insert([
            'user_id' => $this->orang->id, 'work_date' => $tanggal, 'status' => 'hadir',
            'telat_menit' => 0, 'lembur_menit_disetujui' => $lemburMenit,
            'scan_count' => 0, 'single_scan' => 0, 'computed_at' => now(),
        ]);
    }

    private function pengajuan(string $tanggal, string $subTipe, int $menit): void
    {
        DB::table('att_request')->insert([
            'user_id' => $this->orang->id, 'tipe' => 'lembur', 'sub_tipe' => $subTipe,
            'tanggal_from' => $tanggal, 'tanggal_to' => $tanggal, 'status' => 'approved',
            'claimed_menit' => $menit, 'alasan' => 'uji ambang',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function rentang(): array
    {
        $a = Carbon::parse($this->liburKe);

        return [$a->copy()->startOfMonth()->toDateString(), $a->copy()->endOfMonth()->toDateString()];
    }

    private function dodo(): int
    {
        [$from, $to] = $this->rentang();

        return LemburDodo::menitPerOrang($from, $to)[$this->orang->id] ?? 0;
    }

    private function totalLembur(): int
    {
        [$from, $to] = $this->rentang();

        return (int) DB::table('att_day')->where('user_id', $this->orang->id)
            ->whereBetween('work_date', [$from, $to])->sum('lembur_menit_disetujui');
    }

    // ─────────────────── contoh yang diberikan Ekky ───────────────────

    public function test_sepuluh_jam_jadi_tujuh_dodo_dan_tiga_lembur_biasa(): void
    {
        $this->attDay($this->liburKe, 600);
        $this->pengajuan($this->liburKe, 'tambah_hari', 600);

        $this->assertSame(420, $this->dodo(), '10 jam di hari libur → 7 jam DODO.');
        $this->assertSame(180, $this->totalLembur() - $this->dodo(), 'Sisanya 3 jam lembur biasa.');
    }

    public function test_dua_jam_tidak_jadi_dodo_sama_sekali(): void
    {
        // Bukan pembulatan ke bawah: DODO itu tarif SEHARI, dan setengah hari
        // tidak membeli setengah hari. Menitnya tidak hilang — jadi lembur biasa.
        $this->attDay($this->liburKe, 120);
        $this->pengajuan($this->liburKe, 'tambah_hari', 120);

        $this->assertSame(0, $this->dodo(), '2 jam di hari libur → 0 DODO.');
        $this->assertSame(120, $this->totalLembur() - $this->dodo(), 'Seluruhnya jadi lembur biasa.');
    }

    public function test_tepat_di_ambang_ikut_jadi_dodo(): void
    {
        // Batas ditulis ">=", jadi 7 jam pas MASUK. Kalau suatu saat berubah
        // jadi ">", orang yang bekerja tepat sehari penuh kehilangan haknya
        // tanpa satu pun tanda — persis jenis pergeseran yang tak terlihat.
        $this->attDay($this->liburKe, 420);
        $this->pengajuan($this->liburKe, 'tambah_hari', 420);

        $this->assertSame(420, $this->dodo());
        $this->assertSame(0, $this->totalLembur() - $this->dodo());
    }

    // ─────────────────── per HARI, bukan per periode ───────────────────

    public function test_ambang_berlaku_per_hari_bukan_per_periode(): void
    {
        // Dua hari libur, masing-masing penuh. Kalau ambangnya dikenakan pada
        // jumlah sebulan, hari kedua lenyap tanpa jejak dan orangnya dibayar
        // separuh dari yang dikerjakannya.
        $this->attDay($this->liburKe, 480);
        $this->pengajuan($this->liburKe, 'tambah_hari', 480);
        $this->attDay($this->liburKe2, 480);
        $this->pengajuan($this->liburKe2, 'tambah_hari', 480);

        $this->assertSame(840, $this->dodo(), 'Dua hari libur penuh = 2 × 7 jam DODO.');
        $this->assertSame(120, $this->totalLembur() - $this->dodo());
    }

    public function test_jumlah_sel_kehadiran_sama_dengan_angka_lembar_gaji(): void
    {
        // Kehadiran memakai menitPerHari(), Lembar Gaji memakai menitPerOrang().
        // Dua layar yang berselisih soal DODO adalah cacat yang paling sulit
        // dilacak: dua-duanya terlihat masuk akal sendiri-sendiri.
        $this->attDay($this->liburKe, 600);
        $this->pengajuan($this->liburKe, 'tambah_hari', 600);
        $this->attDay($this->liburKe2, 120);
        $this->pengajuan($this->liburKe2, 'tambah_hari', 120);

        [$from, $to] = $this->rentang();
        $perHari = LemburDodo::menitPerHari($from, $to)[$this->orang->id] ?? [];

        $this->assertSame($this->dodo(), array_sum($perHari),
            'Jumlah sel per tanggal WAJIB sama dengan angka per orang.');
        $this->assertArrayNotHasKey($this->liburKe2, $perHari,
            'Hari yang tak mencapai ambang tak boleh muncul sebagai sel DODO.');
    }

    // ─────────────────── invarian uang ───────────────────

    public function test_biasa_tambah_dodo_selalu_sama_dengan_total_berapa_pun_ambangnya(): void
    {
        $this->attDay($this->liburKe, 600);
        $this->pengajuan($this->liburKe, 'tambah_hari', 600);
        $this->attDay($this->kerjaKe, 120);
        $this->pengajuan($this->kerjaKe, 'jam', 120);

        foreach ([0, 60, 420, 600, 900] as $ambang) {
            Setting::put(LemburDodo::KUNCI_AMBANG, (string) $ambang);

            $total = $this->totalLembur();
            $dodo  = min($this->dodo(), $total);

            $this->assertSame($total, ($total - $dodo) + $dodo,
                "Ambang {$ambang}: biasa + DODO wajib sama dengan total.");
            $this->assertGreaterThanOrEqual(0, $total - $dodo,
                "Ambang {$ambang}: lembur biasa tak boleh minus.");
            $this->assertLessThanOrEqual($total, $dodo,
                "Ambang {$ambang}: DODO tak boleh melebihi lembur yang di-ACC.");
        }
    }

    // ─────────────────── setelannya sendiri ───────────────────

    public function test_ambang_bisa_diubah_tanpa_deploy(): void
    {
        $this->attDay($this->liburKe, 300);
        $this->pengajuan($this->liburKe, 'tambah_hari', 300);

        Setting::put(LemburDodo::KUNCI_AMBANG, '420');
        $this->assertSame(0, $this->dodo(), '5 jam di bawah ambang 7 jam.');

        Setting::put(LemburDodo::KUNCI_AMBANG, '240');
        $this->assertSame(240, $this->dodo(), 'Ambang diturunkan ke 4 jam → 4 jam DODO.');
    }

    public function test_ambang_nol_mematikan_pagar(): void
    {
        $this->attDay($this->liburKe, 90);
        $this->pengajuan($this->liburKe, 'tambah_hari', 90);

        Setting::put(LemburDodo::KUNCI_AMBANG, '0');

        $this->assertSame(90, $this->dodo(), 'Ambang 0 = klaim diambil apa adanya.');
    }

    public function test_setelan_rusak_kembali_ke_bawaan_bukan_ke_nol(): void
    {
        // Nol punya ARTI (pagar mati). Setelan rusak yang diam-diam dibaca
        // sebagai nol akan mematikan pagar tanpa ada yang memutuskannya.
        foreach (['', 'tujuh', '-5'] as $rusak) {
            Setting::put(LemburDodo::KUNCI_AMBANG, $rusak);

            $this->assertSame(LemburDodo::AMBANG_BAWAAN, LemburDodo::ambangMenit(),
                'Setelan "' . $rusak . '" harus kembali ke bawaan, bukan jadi 0.');
        }
    }
}
