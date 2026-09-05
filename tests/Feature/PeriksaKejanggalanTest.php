<?php

namespace Tests\Feature;

use App\Console\Commands\AttPeriksaKejanggalan;
use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `att:periksa-kejanggalan` — pemeriksa harian.
 *
 * Yang dijaga di sini bukan cuma "temuannya benar", tapi juga **bentuk
 * laporannya**: pemeriksa yang laporannya berantakan atau penuh derau akan
 * diabaikan dalam sepekan, dan pemeriksa yang diabaikan sama saja dengan tak
 * ada. Itu bukan soal rasa — itu yang menentukan fiturnya berguna atau tidak.
 */
class PeriksaKejanggalanTest extends TestCase
{
    use DatabaseTransactions;

    private function jalankan(array $opsi = []): string
    {
        Artisan::call('att:periksa-kejanggalan', $opsi + ['--hari' => 90]);

        return Artisan::output();
    }

    public function test_baca_saja_tak_mengubah_satu_baris_pun(): void
    {
        // Sifat paling penting perintah ini. Ramdan buktinya: potongan janggal
        // bisa sudah tertutup koreksi manual, jadi pembetulan otomatis justru
        // melahirkan kelebihan bayar.
        $sebelum = [
            'mutasi' => DB::table('att_cuti_mutasi')->count(),
            'hari'   => DB::table('att_day')->count(),
            'aturan' => DB::table('att_user_rule')->count(),
        ];

        $this->jalankan();

        $this->assertSame($sebelum, [
            'mutasi' => DB::table('att_cuti_mutasi')->count(),
            'hari'   => DB::table('att_day')->count(),
            'aturan' => DB::table('att_user_rule')->count(),
        ]);
    }

    public function test_catatan_akun_sistem_muncul_di_bawah_bagiannya(): void
    {
        $out = $this->jalankan();

        $posBagian3 = strpos($out, '3. Karyawan aktif TANPA aturan kerja');
        $posCatatan = strpos($out, 'akun sistem dikecualikan');

        if ($posCatatan === false) {
            $this->markTestSkipped('Environment ini tak punya akun sistem tanpa aturan kerja.');
        }

        $this->assertNotFalse($posBagian3);
        $this->assertGreaterThan($posBagian3, $posCatatan,
            'Catatan pengecualian milik bagian 3 — kalau tercetak dari dalam pengumpul, '
            . 'ia muncul di ATAS seluruh laporan, sebelum judul bagian mana pun.');
    }

    public function test_diam_membungkam_rincian_termasuk_catatan_pengecualian(): void
    {
        $out = $this->jalankan(['--diam' => true]);

        $this->assertStringNotContainsString('akun sistem dikecualikan', $out,
            '--diam harus membungkam SEMUA rincian, bukan sebagian.');
        $this->assertStringNotContainsString('── 1.', $out);
    }

    public function test_stempel_waktu_ditulis_walau_nol_temuan(): void
    {
        // "Nol temuan" punya dua arti yang kelihatannya sama: sudah diperiksa &
        // bersih, atau tak pernah dijalankan. Yang kedua lebih berbahaya karena
        // terlihat menenangkan.
        Setting::put(AttPeriksaKejanggalan::KUNCI_JALAN, '2000-01-01 00:00:00');

        $this->jalankan();

        $this->assertNotSame('2000-01-01 00:00:00',
            Setting::get(AttPeriksaKejanggalan::KUNCI_JALAN),
            'Stempel wajib diperbarui tiap sapuan selesai.');
    }

    public function test_akun_sistem_tak_dihitung_sebagai_temuan(): void
    {
        $out = $this->jalankan();

        // Akun sistem memang benar tak punya aturan kerja. Kalau ikut dihitung,
        // laporannya penuh baris baik-baik saja dan berhenti dibaca orang.
        //
        // Diperiksa lewat KOLOM KETERANGAN ('role Sales …'), bukan lewat nama:
        // namanya memang muncul di catatan pengecualian, jadi mencari nama akan
        // gagal karena alasan yang salah. Dan bukan lewat pipa tabel, yang cuma
        // detail perapian dan bisa berubah tanpa perilaku berubah.
        $this->assertStringNotContainsString('role Sales', $out,
            'Akun sistem tak boleh muncul sebagai baris temuan.');
        $this->assertStringNotContainsString('role Super Admin', $out);
    }
}
