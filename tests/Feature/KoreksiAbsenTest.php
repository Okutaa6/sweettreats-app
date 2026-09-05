<?php

namespace Tests\Feature;

use App\Models\AttDayKoreksi;
use App\Services\AttendanceReconciler;
use App\Services\KoreksiAbsen;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Koreksi absen oleh admin.
 *
 * Uji yang paling penting di kelas ini adalah
 * `test_koreksi_bertahan_setelah_rekonsiliasi_ulang`. Cacat yang paling mungkin
 * terjadi pada fitur ini BUKAN "koreksinya tak tersimpan" — itu ketahuan dalam
 * sedetik. Yang berbahaya adalah koreksi yang tersimpan, terlihat benar sore
 * ini, lalu DIHAPUS diam-diam oleh penjadwal 06:10 besok pagi, karena `att_day`
 * itu tabel turunan yang menimpa dirinya sendiri. Tak ada yang akan
 * menghubungkan keduanya.
 */
class KoreksiAbsenTest extends TestCase
{
    use DatabaseTransactions;

    private object $orang;
    private string $tanggal;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Schema::hasTable('att_day_koreksi')) {
            $this->markTestSkipped('database/sql/2026_09_05_att_day_koreksi.sql belum dijalankan.');
        }

        $u = DB::table('users as u')
            ->join('att_user_rule as ur', 'ur.user_id', '=', 'u.id')
            ->join('att_work_rule as r', 'r.id', '=', 'ur.rule_id')
            ->where('u.status', 1)->where('r.tipe', 'baku')
            ->whereNull('ur.effective_to')
            ->first(['u.id', 'u.nama']);

        if (! $u) $this->markTestSkipped('Tak ada karyawan ber-aturan baku.');

        $this->orang = $u;

        // Tanggal LAMPAU tapi jauh dari data nyata, dan di luar periode gaji
        // mana pun yang tercatat — supaya yang diuji koreksinya, bukan
        // kuncian periode yang kebetulan mengenainya.
        $this->tanggal = '2027-02-10';

        DB::table('att_day')->where('user_id', $u->id)->whereDate('work_date', $this->tanggal)->delete();
        DB::table('att_day')->insert([
            'user_id' => $u->id, 'work_date' => $this->tanggal, 'status' => 'alpa',
            'telat_menit' => 45, 'lembur_menit_disetujui' => 0,
            'scan_count' => 0, 'single_scan' => 0, 'computed_at' => now(),
        ]);
    }

    private function statusHariItu(): ?string
    {
        return DB::table('att_day')->where('user_id', $this->orang->id)
            ->whereDate('work_date', $this->tanggal)->value('status');
    }

    private function koreksiKe(string $status): AttDayKoreksi
    {
        return KoreksiAbsen::simpan($this->orang->id, $this->tanggal, $status,
            'uji otomatis — 25 Agu Sunter tidak libur tapi ybs diliburkan bergantian',
            null, 'Uji');
    }

    // ───────────────────────────────────────────────────────────

    public function test_koreksi_mengubah_status_hari_itu(): void
    {
        $this->assertSame('alpa', $this->statusHariItu());

        $this->koreksiKe('libur');

        $this->assertSame('libur', $this->statusHariItu());
    }

    public function test_koreksi_bertahan_setelah_rekonsiliasi_ulang(): void
    {
        // INTI FITUR. att_day itu turunan; reconcile meng-upsert kolom status.
        // Koreksi yang cuma ditulis ke att_day akan hilang di sini tanpa pesan.
        $this->koreksiKe('libur');

        $hari = Carbon::parse($this->tanggal);
        (new AttendanceReconciler())->reconcile($hari, $hari->copy(), $this->orang->id);

        $this->assertSame('libur', $this->statusHariItu(),
            'Koreksi WAJIB bertahan melewati rekonsiliasi — kalau tidak, penjadwal harian menghapusnya diam-diam.');
    }

    public function test_status_tak_bekerja_menolkan_telat(): void
    {
        // "Telat 45 menit" pada hari yang dinyatakan libur bukan cuma aneh
        // dibaca: angkanya ikut terjumlah di kolom Total Telat pada Lembar Gaji.
        $this->koreksiKe('libur');

        $telat = DB::table('att_day')->where('user_id', $this->orang->id)
            ->whereDate('work_date', $this->tanggal)->value('telat_menit');

        $this->assertSame(0, (int) $telat);
    }

    public function test_mencabut_koreksi_mengembalikan_hasil_rekonsiliasi(): void
    {
        $k = $this->koreksiKe('libur');
        $this->assertSame('libur', $this->statusHariItu());

        KoreksiAbsen::cabut($k->fresh(), null, 'Uji', 'salah orang');

        $this->assertNotSame('libur', $this->statusHariItu(),
            'Sesudah dicabut, hari itu kembali ke apa pun yang dihitung rekonsiliasi.');
    }

    public function test_cabutan_ditandai_bukan_dihapus(): void
    {
        // Buku besar. Jejak yang bisa hilang bukan jejak.
        $k = $this->koreksiKe('libur');
        KoreksiAbsen::cabut($k->fresh(), null, 'Uji', 'salah orang');

        $baris = AttDayKoreksi::find($k->id);

        $this->assertNotNull($baris, 'Baris koreksi TIDAK boleh dihapus.');
        $this->assertNotNull($baris->dicabut_at);
        $this->assertSame('salah orang', $baris->dicabut_alasan);
    }

    public function test_status_lama_ikut_tercatat(): void
    {
        $k = $this->koreksiKe('libur');

        $this->assertSame('alpa', $k->status_lama,
            'Tanpa status lama, riwayatnya tak bisa dibaca ulang enam bulan lagi.');
    }

    public function test_koreksi_terbaru_yang_menang(): void
    {
        $this->koreksiKe('libur');
        $this->koreksiKe('izin');

        $this->assertSame('izin', $this->statusHariItu());
    }

    // ─────────────────── gerbang periode tertutup ───────────────────

    public function test_tanggal_di_periode_tertutup_dikenali_terkunci(): void
    {
        // Angka di periode tertutup sudah jadi dasar gaji yang DIBAYARKAN.
        // Gerbangnya diuji lewat definisi yang sama yang dipakai reconciler —
        // bukan salinan syaratnya, yang cepat atau lambat akan berselisih.
        $this->assertFalse(KoreksiAbsen::terkunci($this->orang->id, $this->tanggal),
            'Prasyarat: tanggal uji belum terkunci.');

        $id = DB::table('att_period')->insertGetId([
            'nama' => 'Uji tutup', 'periode_from' => '2027-02-01', 'periode_to' => '2027-02-28',
            'status' => 'closed', 'raw_branch_id' => null,
        ]);

        $this->assertTrue(KoreksiAbsen::terkunci($this->orang->id, $this->tanggal));
        $this->assertNotNull(KoreksiAbsen::periodePengunci($this->orang->id, $this->tanggal),
            'Penolakan harus bisa menyebut periode mana yang menguncinya.');

        DB::table('att_period')->where('id', $id)->delete();
    }

    public function test_rekonsiliasi_tak_menyentuh_hari_yang_terkunci(): void
    {
        // Kendali negatif untuk gerbang di atas: kalaupun ada koreksi tercatat,
        // hari di periode tertutup TIDAK boleh ikut berubah.
        $id = DB::table('att_period')->insertGetId([
            'nama' => 'Uji tutup', 'periode_from' => '2027-02-01', 'periode_to' => '2027-02-28',
            'status' => 'closed', 'raw_branch_id' => null,
        ]);

        AttDayKoreksi::create([
            'user_id' => $this->orang->id, 'work_date' => $this->tanggal, 'status' => 'libur',
            'status_lama' => 'alpa', 'alasan' => 'uji — seharusnya tak berlaku',
            'dibuat_nama' => 'Uji', 'created_at' => now(),
        ]);

        $hari = Carbon::parse($this->tanggal);
        (new AttendanceReconciler())->reconcile($hari, $hari->copy(), $this->orang->id);

        $this->assertSame('alpa', $this->statusHariItu(),
            'Periode tertutup membekukan att_day — koreksi pun tak boleh menembusnya.');

        DB::table('att_period')->where('id', $id)->delete();
    }

    // ─────────────────── kendali negatif ───────────────────

    public function test_orang_lain_tidak_ikut_berubah(): void
    {
        $lain = DB::table('att_day')
            ->where('user_id', '!=', $this->orang->id)
            ->whereDate('work_date', $this->tanggal)
            ->count();

        $this->koreksiKe('libur');

        $this->assertSame($lain, DB::table('att_day')
            ->where('user_id', '!=', $this->orang->id)
            ->whereDate('work_date', $this->tanggal)
            ->count(), 'Koreksi satu orang tak boleh menyentuh baris orang lain.');
    }

    public function test_tanggal_lain_tidak_ikut_berubah(): void
    {
        $besok = Carbon::parse($this->tanggal)->addDay()->toDateString();
        DB::table('att_day')->where('user_id', $this->orang->id)->whereDate('work_date', $besok)->delete();
        DB::table('att_day')->insert([
            'user_id' => $this->orang->id, 'work_date' => $besok, 'status' => 'alpa',
            'telat_menit' => 0, 'lembur_menit_disetujui' => 0,
            'scan_count' => 0, 'single_scan' => 0, 'computed_at' => now(),
        ]);

        $this->koreksiKe('libur');

        $this->assertSame('alpa', DB::table('att_day')->where('user_id', $this->orang->id)
            ->whereDate('work_date', $besok)->value('status'));
    }
}
