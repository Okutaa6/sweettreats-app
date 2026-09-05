<?php

namespace Tests\Feature;

use App\Models\AttDayKoreksi;
use App\Services\AttendanceReconciler;
use App\Services\KoreksiAbsen;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Koreksi absen oleh admin.
 *
 * Uji terpenting di kelas ini adalah `test_koreksi_bertahan_setelah_
 * rekonsiliasi_ulang`. Cacat yang paling mungkin BUKAN "koreksinya tak
 * tersimpan" — itu ketahuan dalam sedetik. Yang berbahaya adalah koreksi yang
 * tersimpan, terlihat benar sore ini, lalu DIHAPUS diam-diam oleh penjadwal
 * 06:10 besok pagi, karena `att_day` itu tabel turunan yang menimpa dirinya
 * sendiri. Tak ada yang akan menghubungkan keduanya.
 *
 * ⚠️ TANGGAL UJI DIAMBIL DARI DATA, BUKAN DIKARANG.
 * Percobaan pertama memakai tanggal 2027 supaya "tak bertabrakan dengan data
 * nyata". Hasilnya empat uji merah: rekonsiliasi membuang baris masa depan
 * sebagai basi, jadi yang diuji sebenarnya bukan koreksinya. Sekarang kelas ini
 * memakai hari LAMPAU yang benar-benar sudah punya baris att_day dan tidak
 * terkunci periode gaji — keadaan yang sama dengan Melani & Putri 25 Agu.
 * Semuanya di dalam transaksi yang di-rollback.
 */
class KoreksiAbsenTest extends TestCase
{
    use DatabaseTransactions;

    private object $orang;
    private string $tanggal;
    private string $alami;      // status hasil rekonsiliasi, sebelum dikoreksi

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('att_day_koreksi')) {
            $this->markTestSkipped('database/sql/2026_09_05_att_day_koreksi.sql belum dijalankan.');
        }

        // Hari yang SUDAH punya baris att_day = bukti orangnya punya aturan
        // kerja aktif dan tanggalnya sudah pernah dihitung. Dua syarat yang
        // kalau ditebak akan membuat ujinya merah karena alasan yang salah.
        $kandidat = DB::table('att_day as d')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('u.status', 1)
            ->whereBetween('d.work_date', [
                Carbon::today()->subDays(45)->toDateString(),
                Carbon::today()->subDay()->toDateString(),
            ])
            ->orderByDesc('d.work_date')
            ->limit(60)
            ->get(['u.id', 'u.nama', 'd.work_date', 'd.status']);

        foreach ($kandidat as $k) {
            $tgl = substr((string) $k->work_date, 0, 10);
            if (KoreksiAbsen::terkunci($k->id, $tgl)) continue;   // periode tertutup — bukan kasus uji

            $this->orang   = $k;
            $this->tanggal = $tgl;
            $this->alami   = (string) $k->status;

            return;
        }

        $this->markTestSkipped('Tak ada hari att_day lampau yang di luar periode gaji tertutup.');
    }

    /** Status apa pun yang BUKAN status alaminya — supaya perubahannya terlihat. */
    private function statusLain(): string
    {
        foreach (['izin', 'sakit', 'libur', 'cuti'] as $s) {
            if ($s !== $this->alami) return $s;
        }

        return 'alpa';
    }

    private function statusHariItu(): ?string
    {
        return DB::table('att_day')->where('user_id', $this->orang->id)
            ->whereDate('work_date', $this->tanggal)->value('status');
    }

    private function koreksiKe(string $status): AttDayKoreksi
    {
        return KoreksiAbsen::simpan($this->orang->id, $this->tanggal, $status,
            'uji otomatis — koreksi absen berjejak audit', null, 'Uji');
    }

    // ─────────────────── inti ───────────────────

    public function test_koreksi_mengubah_status_hari_itu(): void
    {
        $baru = $this->statusLain();
        $this->koreksiKe($baru);

        $this->assertSame($baru, $this->statusHariItu());
    }

    public function test_koreksi_bertahan_setelah_rekonsiliasi_ulang(): void
    {
        // INTI FITUR. att_day itu turunan; reconcile meng-upsert kolom status.
        // Koreksi yang cuma ditulis ke att_day akan hilang di sini tanpa pesan.
        $baru = $this->statusLain();
        $this->koreksiKe($baru);

        $hari = Carbon::parse($this->tanggal);
        (new AttendanceReconciler())->reconcile($hari, $hari->copy(), $this->orang->id);

        $this->assertSame($baru, $this->statusHariItu(),
            'Koreksi WAJIB bertahan melewati rekonsiliasi — kalau tidak, penjadwal harian menghapusnya diam-diam.');
    }

    public function test_mencabut_koreksi_mengembalikan_hasil_rekonsiliasi(): void
    {
        $baru = $this->statusLain();
        $k = $this->koreksiKe($baru);
        $this->assertSame($baru, $this->statusHariItu());

        KoreksiAbsen::cabut($k->fresh(), null, 'Uji', 'salah orang');

        $this->assertSame($this->alami, $this->statusHariItu(),
            'Sesudah dicabut, hari itu kembali persis ke hasil rekonsiliasi.');
    }

    public function test_cabutan_ditandai_bukan_dihapus(): void
    {
        // Buku besar. Jejak yang bisa hilang bukan jejak.
        $k = $this->koreksiKe($this->statusLain());
        KoreksiAbsen::cabut($k->fresh(), null, 'Uji', 'salah orang');

        $baris = AttDayKoreksi::find($k->id);

        $this->assertNotNull($baris, 'Baris koreksi TIDAK boleh dihapus.');
        $this->assertNotNull($baris->dicabut_at);
        $this->assertSame('salah orang', $baris->dicabut_alasan);
    }

    public function test_status_lama_ikut_tercatat(): void
    {
        $k = $this->koreksiKe($this->statusLain());

        $this->assertSame($this->alami, $k->status_lama,
            'Tanpa status lama, riwayatnya tak bisa dibaca ulang enam bulan lagi.');
    }

    public function test_koreksi_terbaru_yang_menang(): void
    {
        $this->koreksiKe('izin');
        $this->koreksiKe('sakit');

        $this->assertSame('sakit', $this->statusHariItu());
    }

    // ─────────────────── aturan penimpaan (murni, tanpa DB) ───────────────────

    public function test_status_tak_bekerja_menolkan_telat(): void
    {
        // "Telat 45 menit" pada hari yang dinyatakan libur bukan cuma aneh
        // dibaca: angkanya ikut terjumlah di kolom Total Telat pada Lembar Gaji.
        // Diuji sebagai fungsi murni — status hasil rekonsiliasi tak bisa
        // dipaksa punya telat, dan memaksanya justru menguji hal lain.
        $rows = [[
            'user_id' => 'u1', 'work_date' => '2026-08-25',
            'status' => 'alpa', 'telat_menit' => 45,
        ]];
        $peta = ['u1|2026-08-25' => (object) ['status' => 'libur']];

        $hasil = KoreksiAbsen::terapkan($rows, $peta);

        $this->assertSame('libur', $hasil[0]['status']);
        $this->assertSame(0, $hasil[0]['telat_menit']);
    }

    public function test_status_bekerja_tidak_menolkan_telat(): void
    {
        // Kendali negatif: koreksi ke 'telat'/'hadir' TIDAK boleh menghapus
        // menit telat yang benar-benar dihitung dari scan.
        $rows = [[
            'user_id' => 'u1', 'work_date' => '2026-08-25',
            'status' => 'alpa', 'telat_menit' => 45,
        ]];
        $peta = ['u1|2026-08-25' => (object) ['status' => 'telat']];

        $hasil = KoreksiAbsen::terapkan($rows, $peta);

        $this->assertSame('telat', $hasil[0]['status']);
        $this->assertSame(45, $hasil[0]['telat_menit']);
    }

    public function test_baris_tanpa_koreksi_tak_disentuh(): void
    {
        $rows = [[
            'user_id' => 'u2', 'work_date' => '2026-08-25',
            'status' => 'hadir', 'telat_menit' => 10,
        ]];

        $this->assertSame($rows, KoreksiAbsen::terapkan($rows, ['u1|2026-08-25' => (object) ['status' => 'libur']]));
    }

    // ─────────────────── gerbang periode tertutup ───────────────────

    public function test_tanggal_di_periode_tertutup_dikenali_terkunci(): void
    {
        $this->assertFalse(KoreksiAbsen::terkunci($this->orang->id, $this->tanggal),
            'Prasyarat: tanggal uji belum terkunci.');

        $id = $this->tutupPeriode();

        $this->assertTrue(KoreksiAbsen::terkunci($this->orang->id, $this->tanggal));
        $this->assertNotNull(KoreksiAbsen::periodePengunci($this->orang->id, $this->tanggal),
            'Penolakan harus bisa menyebut periode mana yang menguncinya.');

        DB::table('att_period')->where('id', $id)->delete();
    }

    public function test_rekonsiliasi_tak_menyentuh_hari_yang_terkunci(): void
    {
        // Kendali negatif untuk gerbang di atas: kalaupun ada koreksi tercatat,
        // hari di periode tertutup TIDAK boleh ikut berubah. Angka di sana
        // sudah jadi dasar gaji yang dibayarkan.
        $id = $this->tutupPeriode();

        AttDayKoreksi::create([
            'user_id' => $this->orang->id, 'work_date' => $this->tanggal, 'status' => 'izin',
            'status_lama' => $this->alami, 'alasan' => 'uji — seharusnya tak berlaku',
            'dibuat_nama' => 'Uji', 'created_at' => now(),
        ]);

        $hari = Carbon::parse($this->tanggal);
        (new AttendanceReconciler())->reconcile($hari, $hari->copy(), $this->orang->id);

        $this->assertSame($this->alami, $this->statusHariItu(),
            'Periode tertutup membekukan att_day — koreksi pun tak boleh menembusnya.');

        DB::table('att_period')->where('id', $id)->delete();
    }

    private function tutupPeriode(): int
    {
        $d = Carbon::parse($this->tanggal);

        return (int) DB::table('att_period')->insertGetId([
            'nama'         => 'Uji tutup (otomatis)',
            'periode_from' => $d->copy()->subDay()->toDateString(),
            'periode_to'   => $d->copy()->addDay()->toDateString(),
            'status'       => 'closed',
            'raw_branch_id'=> null,
        ]);
    }

    // ─────────────────── kendali negatif ───────────────────

    public function test_orang_lain_tidak_ikut_berubah(): void
    {
        $sebelum = DB::table('att_day')
            ->where('user_id', '!=', $this->orang->id)
            ->whereDate('work_date', $this->tanggal)
            ->orderBy('user_id')->pluck('status', 'user_id')->all();

        $this->koreksiKe($this->statusLain());

        $this->assertSame($sebelum, DB::table('att_day')
            ->where('user_id', '!=', $this->orang->id)
            ->whereDate('work_date', $this->tanggal)
            ->orderBy('user_id')->pluck('status', 'user_id')->all(),
            'Koreksi satu orang tak boleh menggeser status orang lain di tanggal yang sama.');
    }

    public function test_tanggal_lain_tidak_ikut_berubah(): void
    {
        $lain = Carbon::parse($this->tanggal)->subDay()->toDateString();
        $sebelum = DB::table('att_day')->where('user_id', $this->orang->id)
            ->whereDate('work_date', $lain)->value('status');

        $this->koreksiKe($this->statusLain());

        $this->assertSame($sebelum, DB::table('att_day')->where('user_id', $this->orang->id)
            ->whereDate('work_date', $lain)->value('status'));
    }
}
