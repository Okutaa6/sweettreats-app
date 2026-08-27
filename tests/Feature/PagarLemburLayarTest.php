<?php

namespace Tests\Feature;

use App\Models\AttRequest;
use App\Services\AttendanceRequestWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uji terima §3 #2, #4 dan #9 — bagian yang HANYA bisa dibuktikan lewat HTTP.
 *
 * Yang diuji di sini bukan logikanya (itu di PagarLemburTest), melainkan bahwa
 * logikanya benar-benar TERPASANG di jalur yang dilewati manusia:
 *   • kiriman kembar lewat POST langsung — tanpa membuka form — tetap ditolak;
 *   • peringatannya benar-benar TERCETAK di halaman persetujuan.
 *
 * ⚠️ Status 200 tak membuktikan apa-apa: RBAC di sini MEMANTULKAN (302 ke
 * beranda / signin), bukan 403, jadi `curl -L` bisa melapor 200 sambil
 * sebenarnya menampilkan halaman login. Karena itu tiap uji memeriksa ISI
 * halamannya, bukan kodenya.
 */
class PagarLemburLayarTest extends TestCase
{
    use DatabaseTransactions;

    private object $orang;
    private string $tanggal;

    protected function setUp(): void
    {
        parent::setUp();

        $u = DB::table('users as u')
            ->join('att_user_rule as ur', 'ur.user_id', '=', 'u.id')
            ->join('att_work_rule as r', 'r.id', '=', 'ur.rule_id')
            ->join('att_work_rule_day as d', 'd.rule_id', '=', 'r.id')
            ->where('u.status', 1)->where('r.tipe', 'baku')->where('d.libur', 1)
            ->whereNull('ur.effective_to')
            ->first(['u.id', 'u.nama', 'u.group_id', 'u.raw_branch_id', 'd.day as hari_libur']);

        if (! $u) $this->markTestSkipped('Tak ada karyawan ber-aturan baku dengan hari libur mingguan.');

        $this->orang = $u;

        // HARI LIBUR orang ini, jauh ke depan — sekalian memancing chip salah:
        // lembur biasa (`jam`) di hari libur = DODO yang terbayar tarif biasa.
        $this->tanggal = Carbon::create(2027, 6, 1)->startOfDay()
            ->next((int) $u->hari_libur)->toDateString();
    }

    private function sesi(object $user): array
    {
        return [
            'isLoggedIn'    => true,
            'id'            => $user->id,
            'group_id'      => $user->group_id,
            'group_name'    => 'Uji',
            'name'          => $user->nama ?? 'Uji',
            'raw_branch_id' => $user->raw_branch_id ?? null,
            'branch_day'    => date('Y-m-d'),
            'expires_in'    => time() + 3600,
            'login_at'      => time(),
            'pa_touch'      => time(),
        ];
    }

    private function lembur(string $jamFrom, string $jamTo, string $subTipe = 'jam'): string
    {
        return (string) DB::table('att_request')->insertGetId([
            'user_id' => $this->orang->id, 'tipe' => 'lembur', 'sub_tipe' => $subTipe,
            'tanggal_from' => $this->tanggal, 'tanggal_to' => $this->tanggal,
            'jam_from' => $jamFrom, 'jam_to' => $jamTo,
            'status' => 'pending_l1',
            'claimed_menit' => (int) Carbon::createFromFormat('H:i', $jamTo)
                ->diffInMinutes(Carbon::createFromFormat('H:i', $jamFrom)),
            'alasan' => 'uji pagar lembur (layar)',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ───────────────────────────────────────────────────────────

    /**
     * §3 #4 — POST langsung tanpa lewat UI tetap ditolak.
     *
     * Tombol yang disembunyikan bukan pagar: form buatan sendiri tetap bisa
     * mengirim. Kembar Afrina lahir persis dari kirim-ulang, bukan dari niat
     * jahat — tapi pagarnya harus tetap di server, bukan di layar.
     */
    public function test_kiriman_kembar_lewat_post_langsung_ditolak_di_server(): void
    {
        $pertama = $this->lembur('19:15', '21:10');
        $sebelum = AttRequest::where('user_id', $this->orang->id)->count();

        $res = $this->withSession($this->sesi($this->orang))
            ->post('/akun/pengajuan', [
                'tipe'         => 'lembur',
                'sub_tipe'     => 'jam',
                'tanggal_from' => $this->tanggal,
                'tanggal_to'   => $this->tanggal,
                'jam_from'     => '19:15',
                'jam_to'       => '21:10',
                'alasan'       => 'kiriman kedua, jam persis sama',
            ]);

        $res->assertSessionHas('error');
        $this->assertStringContainsString('#' . $pertama, (string) session('error'),
            'Pesannya harus menyebut nomor pengajuan yang sudah ada.');

        $this->assertSame($sebelum, AttRequest::where('user_id', $this->orang->id)->count(),
            'Tak boleh ada baris baru — kalau ada, menitnya ikut terjumlah begitu di-ACC.');
    }

    /** §3 #3 lewat HTTP — sesi kedua yang tak bertindih tetap boleh lewat. */
    public function test_sesi_terpisah_lewat_post_tetap_diterima(): void
    {
        $this->lembur('19:15', '21:10');
        $sebelum = AttRequest::where('user_id', $this->orang->id)->count();

        $res = $this->withSession($this->sesi($this->orang))
            ->post('/akun/pengajuan', [
                'tipe'         => 'lembur',
                'sub_tipe'     => 'jam',
                'tanggal_from' => $this->tanggal,
                'tanggal_to'   => $this->tanggal,
                'jam_from'     => '05:00',
                'jam_to'       => '07:00',
                'alasan'       => 'sesi pagi, terpisah',
            ]);

        // Alur persetujuan bisa saja belum diatur untuk orang ini — itu galat
        // LAIN dan bukan urusan pagar ini. Yang dijaga: pagar kembar tak boleh
        // ikut bicara.
        $galat = (string) session('error');
        $this->assertStringNotContainsString('sudah pernah dikirim', $galat,
            'Lembur pagi 05:00–07:00 tak bertindih dengan 19:15–21:10 — jangan ditolak.');

        if ($galat === '') {
            $res->assertSessionHas('success');
            $this->assertSame($sebelum + 1, AttRequest::where('user_id', $this->orang->id)->count());
        }
    }

    /**
     * §3 #2 + #9 — peringatan benar-benar TERCETAK di layar persetujuan.
     *
     * Penyetujunya diambil dari chainFor() — rantai yang sesungguhnya — supaya
     * ujinya ikut membuktikan bahwa kartunya memang sampai ke inbox orang itu,
     * bukan cuma bahwa HTML-nya bisa dirender.
     */
    public function test_layar_persetujuan_menampilkan_kedua_peringatan(): void
    {
        $wf     = new AttendanceRequestWorkflow();
        $rantai = $wf->chainFor($this->orang->id, 'lembur');
        $approverId = $rantai[0][0] ?? null;

        if (! $approverId) $this->markTestSkipped('Pengaju uji tak punya rantai persetujuan lembur.');

        $approver = DB::table('users')->where('id', $approverId)
            ->first(['id', 'nama', 'group_id', 'raw_branch_id']);
        if (! $approver) $this->markTestSkipped('Baris approver tak ditemukan.');

        // Dua pengajuan yang jamnya bertindih SEBAGIAN (bukan persis, jadi
        // keduanya memang sah masuk) + chip `jam` di hari libur.
        $a = $this->lembur('19:00', '21:00');
        $b = $this->lembur('19:15', '21:10');

        $res = $this->withSession($this->sesi($approver))->get('/persetujuan');

        $html = $res->getContent();

        // Bukti kita benar-benar di halaman persetujuan, bukan dipantulkan.
        $this->assertStringContainsString('Inbox Persetujuan', $html,
            'Kalau ini gagal, RBAC memantulkan — dan semua assert di bawahnya jadi tak bermakna.');

        $this->assertStringContainsString('bertindih dengan pengajuan lain', $html,
            'Penyetuju harus diberi tahu soal tindihan jam SEBELUM menekan Setujui.');
        $this->assertStringContainsString('#' . $a, $html);
        $this->assertStringContainsString('#' . $b, $html);

        $this->assertStringContainsString('Jenis lembur perlu dicek', $html,
            'Chip salah pilih harus tampil di titik keputusan, bukan cuma di Lembar Gaji.');
        $this->assertStringContainsString('kemungkinan DODO yang terbayar tarif biasa', $html);
    }
}
