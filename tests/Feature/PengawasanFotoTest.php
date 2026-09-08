<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Halaman Pengawasan tak boleh menembakkan satu request foto per selfie.
 *
 * Diagnosis 6 Sep 2026: tiap <img> ke pengawasan/foto = satu boot Laravel +
 * satu koneksi DB; 49–179 sekaligus (HTTP/2 mengirim semua yang terlihat
 * serentak) → hosting bersama menolak koneksi → galat [2002] yang juga
 * menjatuhkan halaman ORANG LAIN. 272 dari 278 kegagalan pemeriksa akses ada
 * di link=pengawasan.
 *
 * ⚠️ Yang dihitung adalah ELEMEN <img> ber-src ke rute foto — bukan kata
 * "pengawasan/foto". Kata itu sengaja tetap muncul 49 kali di halaman (di
 * JSON AWS_BUKTI dan di data-src placeholder); yang memicu request hanya
 * <img src>. Menghitung kata akan merah selamanya, menghitung elemen mengukur
 * hal yang benar.
 */
class PengawasanFotoTest extends TestCase
{
    use DatabaseTransactions;

    /** Jumlah selfie yang ditanam — meniru hari sibuk prod (rata-rata 49). */
    private const N_BUKTI = 49;

    /** Tanggal jauh di depan: strip pasti kosong sebelum ditanam. */
    private const TANGGAL = '2027-03-01';

    private object $peninjau;

    protected function setUp(): void
    {
        parent::setUp();

        // Persona = akun sungguhan dari role yang PUNYA node 'pengawasan'.
        // AuthGuard memantulkan (302) role tanpa grant, dan grant beda antar
        // environment — jadi jangan menebak rolenya.
        $u = DB::table('users as u')
            ->join('group_access as ga', 'ga.group_id', '=', 'u.group_id')
            ->join('access_path as ap', 'ap.id', '=', 'ga.access_id')
            ->join('group_path as g', 'g.id', '=', 'u.group_id')
            ->where('ap.link', 'pengawasan')->where('u.status', 1)
            ->first(['u.id', 'u.nama', 'u.group_id', 'u.raw_branch_id', 'g.nama as role']);
        if (! $u) $this->markTestSkipped('Tak ada akun yang punya node pengawasan.');
        $this->peninjau = $u;

        $orang  = DB::table('users')->where('status', 1)->limit(self::N_BUKTI)->pluck('id');
        $cabang = DB::table('raw_branch')->value('id');
        if ($orang->count() < 5 || ! $cabang) $this->markTestSkipped('Data dasar tak cukup.');

        // 49 selfie di satu hari kerja: masuk & pulang bergantian, jam berbeda-beda.
        $rows = [];
        for ($i = 0; $i < self::N_BUKTI; $i++) {
            $rows[] = [
                'user_id'         => $orang[$i % $orang->count()],
                'jenis'           => $i % 2 ? 'pulang' : 'masuk',
                'photo_path'      => 'att_selfie/uji_' . $i . '.jpg',
                'ip'              => '10.0.0.' . ($i % 250 + 1),
                'branch_id_cocok' => $cabang,
                'is_desktop'      => 0,
                'taken_at'        => self::TANGGAL . sprintf(' %02d:%02d:00', 7 + intdiv($i, 6), ($i * 7) % 60),
                'created_at'      => now(),
            ];
        }
        DB::table('att_hp_bukti')->insert($rows);
    }

    private function halaman(): string
    {
        $res = $this->withSession([
            'isLoggedIn' => true, 'id' => $this->peninjau->id, 'group_id' => $this->peninjau->group_id,
            'group_name' => $this->peninjau->role, 'name' => $this->peninjau->nama,
            'raw_branch_id' => $this->peninjau->raw_branch_id, 'branch_day' => date('Y-m-d'),
            'expires_in' => time() + 3600, 'login_at' => time(), 'pa_touch' => time(),
        ])->get('/pengawasan?tanggal=' . self::TANGGAL);

        $res->assertStatus(200);

        return $res->getContent();
    }

    /** ELEMEN <img> yang src-nya menunjuk rute foto — inilah yang memicu request. */
    private function jumlahImgFoto(string $html): int
    {
        return preg_match_all('#<img\b[^>]*\bsrc="[^"]*/pengawasan/foto/\d+#', $html);
    }

    public function test_muatan_pertama_maksimal_12_img_ke_rute_foto(): void
    {
        $html = $this->halaman();

        $this->assertLessThanOrEqual(12, $this->jumlahImgFoto($html),
            'Muatan pertama tak boleh menembakkan lebih dari 12 request foto.');
        $this->assertGreaterThan(0, $this->jumlahImgFoto($html),
            'Kendali negatif: nol <img> berarti strip-nya tak dirender, bukan berarti hemat.');
    }

    public function test_tidak_ada_selfie_yang_hilang_dari_strip(): void
    {
        // Menghemat request tak boleh dibayar dengan menyembunyikan orang.
        // 49 selfie → 49 tile: 12 berfoto + 37 placeholder ber-data-src.
        $html = $this->halaman();

        $tile        = preg_match_all('#<button\b[^>]*\bdata-nav-bukti="\d+"#', $html);
        $placeholder = preg_match_all('#<span\b[^>]*\bclass="aws-tunda"[^>]*\bdata-src="[^"]*/pengawasan/foto/\d+"#', $html);

        $this->assertSame(self::N_BUKTI, $tile, 'Semua selfie harus tetap punya tile.');
        $this->assertSame(self::N_BUKTI, $this->jumlahImgFoto($html) + $placeholder,
            'Tiap tile: entah <img> (dalam anggaran) atau placeholder (ditunda) — tak ada yang hilang.');
    }

    public function test_tombol_muat_lagi_menyebut_sisa_yang_ditunda(): void
    {
        $html = $this->halaman();

        $this->assertMatchesRegularExpression('#data-muat-strip[^>]*>\s*Muat \d+ foto lagi#', $html,
            'Foto yang ditunda harus punya jalan masuk yang terlihat, bukan hilang diam-diam.');
    }

    public function test_placeholder_tidak_membawa_atribut_src(): void
    {
        // Pagar terhadap regresi paling mungkin: seseorang "merapikan" placeholder
        // jadi <img src> lagi. data-src tak memicu request; src memicu.
        $html = $this->halaman();

        $this->assertSame(0, preg_match_all('#<img\b[^>]*\bdata-src=#', $html),
            'Yang ditunda harus <span data-src>, bukan <img data-src> (browser tetap tak memuatnya, tapi pola ini rawan diubah jadi src).');
    }
}
