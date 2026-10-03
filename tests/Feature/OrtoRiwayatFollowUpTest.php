<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Models\OrtFollowUp;
use App\Services\Ortho\OrtBranches;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — RIWAYAT follow-up, satu baris per pasien per hari.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * `ort_control` menyimpan follow-up TERAKHIR saja — telepon kedua menimpa yang
 * pertama. Jadi "sudah berapa kali pasien ini ditelepon bulan ini, dan oleh
 * siapa" tidak punya jawaban di data, padahal itu pertanyaan yang ditanyakan
 * tiap rapat. prompt_jadwal_kontrol.md (Phase 2) memintanya sebagai daftar
 * bernomor, dan meminta UNIQUE (pasien, tanggal) plus tombol yang hijau lagi
 * besok TANPA cron.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG DIJAGA DI SINI
 * ────────────────────────────────────────────────────────────────────────────
 *   • ⭐ dua follow-up di hari yang sama = SATU baris, bukan dua
 *   • ⭐ hari berikutnya boleh baris baru — itu yang membuat tombol hijau lagi
 *   • Urungkan membuang baris yang baru dibuat, dan MEMULIHKAN yang diperbarui
 *   • batalFollowUp membuang baris riwayatnya juga — kalau tidak, tombolnya
 *     kembali hijau sementara riwayat masih mengaku pasien ditelepon hari itu
 *   • kamusnya tetap satu: OrtControl::FU_HASIL, tidak diduplikasi
 */
class OrtoRiwayatFollowUpTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-FURIWAYAT';

    private string $placeId;
    private array $sesi;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ort_case', 'ort_control', 'ort_followup'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("Tabel {$t} belum ada — jalankan database/sql/2026_10_07_ort_riwayat_followup.sql.");
            }
        }

        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }
        $this->placeId = (string) array_key_first($peta);

        $gid = DB::table('group_path')->where('nama', 'Supervisor')->value('id');
        if (! $gid) {
            $this->markTestSkipped("Role 'Supervisor' tidak ada di DB ini.");
        }
        $node = DB::table('access_path')->where('link', 'tracking-ortho')->first(['id', 'pid']);
        foreach (array_filter([$node->id, $node->pid]) as $accessId) {
            if (! DB::table('group_access')->where('group_id', $gid)->where('access_id', $accessId)->exists()) {
                DB::table('group_access')->insert(['id' => (string) Str::uuid(), 'group_id' => $gid, 'access_id' => $accessId]);
            }
        }

        // Satu sesi untuk seluruh uji: token Urungkan terikat ke session('id').
        $this->sesi = [
            'isLoggedIn' => true, 'id' => 'uji-' . Str::random(8), 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Sales', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ];
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    private function kontrol(array $ubah = []): OrtControl
    {
        $pid   = self::TANDA . '-' . Str::random(6);
        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji FURiwayat ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::today()->subDays(300)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);

        return OrtControl::create(array_merge([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => Carbon::today()->toDateString(),
            'sch_source' => OrtControl::SCH_MANUAL, 'created_by' => 'uji',
        ], $ubah));
    }

    private function catat(OrtControl $ctl, array $isi = [])
    {
        return $this->withSession($this->sesi)
            ->from('/tracking-ortho?utama=' . $this->placeId)
            ->post('/tracking-ortho/kontrol/' . $ctl->id . '/follow-up', $isi);
    }

    private function riwayat(OrtControl $ctl)
    {
        return OrtFollowUp::riwayat($ctl->ort_case_id)->get();
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ Satu baris per pasien per hari
    // ════════════════════════════════════════════════════════════════════════

    public function test_follow_up_menulis_satu_baris_riwayat(): void
    {
        $ctl = $this->kontrol();

        $this->catat($ctl, ['hasil' => OrtControl::FU_DIHUBUNGI, 'catatan' => 'bersedia'])
            ->assertRedirect();

        $baris = $this->riwayat($ctl);

        $this->assertCount(1, $baris);
        $this->assertSame(OrtControl::FU_DIHUBUNGI, $baris[0]->fu_result);
        $this->assertSame('bersedia', $baris[0]->fu_note);
        $this->assertSame('Uji Sales', $baris[0]->fu_by, 'Nama pencatat ini yang dirender "oleh Nama Sales".');
        $this->assertSame($ctl->id, $baris[0]->ort_control_id, 'Kontrol yang sedang dikejar ikut dicatat sebagai konteks.');
        $this->assertSame((string) $this->placeId, (string) $baris[0]->place_id);
    }

    /** ⭐ Dua kali di hari yang sama: tetap SATU baris, nilainya yang terbaru. */
    public function test_dua_follow_up_sehari_tetap_satu_baris(): void
    {
        $ctl = $this->kontrol();

        $this->catat($ctl, ['hasil' => OrtControl::FU_TIDAK_RESPON])->assertRedirect();
        $this->catat($ctl, ['hasil' => OrtControl::FU_DIHUBUNGI, 'catatan' => 'akhirnya diangkat'])->assertRedirect();

        $baris = $this->riwayat($ctl);

        $this->assertCount(1, $baris, 'UNIQUE (ort_case_id, fu_date) melarang baris kedua di hari yang sama.');
        $this->assertSame(OrtControl::FU_DIHUBUNGI, $baris[0]->fu_result, 'Isinya yang terbaru.');
        $this->assertSame('akhirnya diangkat', $baris[0]->fu_note);
    }

    /**
     * ⭐ Hari yang BERBEDA boleh baris baru — inilah yang membuat tombol hijau
     * lagi besok tanpa cron, dan sekaligus yang membuat daftar bernomor punya
     * lebih dari satu baris untuk dihitung.
     */
    public function test_hari_berbeda_menambah_baris(): void
    {
        $ctl = $this->kontrol();

        $this->catat($ctl, ['tanggal' => Carbon::today()->subDays(2)->toDateString()])->assertRedirect();
        $this->catat($ctl, ['tanggal' => Carbon::today()->subDay()->toDateString()])->assertRedirect();
        $this->catat($ctl, ['hasil' => OrtControl::FU_DIHUBUNGI])->assertRedirect();

        $baris = $this->riwayat($ctl);

        $this->assertCount(3, $baris);
        // Terbaru di atas — bentuk yang diminta prompt untuk daftar bernomor.
        $this->assertSame(
            Carbon::today()->toDateString(),
            $baris[0]->fu_date->toDateString(),
            'Riwayat harus terbaru di atas.'
        );
    }

    public function test_sudah_hari_ini_dibaca_dari_tanggal(): void
    {
        $ctl = $this->kontrol();
        $hariIni = Carbon::today()->toDateString();

        $this->assertFalse(OrtFollowUp::sudahHariIni($ctl->ort_case_id, $hariIni));

        $this->catat($ctl, ['hasil' => OrtControl::FU_DIHUBUNGI])->assertRedirect();

        $this->assertTrue(OrtFollowUp::sudahHariIni($ctl->ort_case_id, $hariIni));
        $this->assertFalse(
            OrtFollowUp::sudahHariIni($ctl->ort_case_id, Carbon::today()->addDay()->toDateString()),
            'Besok harus kosong lagi — itu seluruh mekanisme "hijau lagi besok".'
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    // Urungkan
    // ════════════════════════════════════════════════════════════════════════

    public function test_urungkan_membuang_baris_yang_baru_dibuat(): void
    {
        $ctl = $this->kontrol();

        $this->catat($ctl, ['hasil' => OrtControl::FU_DIHUBUNGI])->assertRedirect();
        $this->assertCount(1, $this->riwayat($ctl));

        $token = session('ort_undo');
        $this->assertNotNull($token, 'Prasyarat: aksi menghasilkan token Urungkan.');

        $this->withSession($this->sesi)->post('/tracking-ortho/urungkan/' . $token)->assertRedirect();

        $this->assertCount(0, $this->riwayat($ctl), 'Urungkan harus ikut membuang baris riwayatnya.');
        $this->assertNull($ctl->refresh()->fu_date);
    }

    /**
     * ⭐ Dan kalau barisnya sudah ada lalu DIPERBARUI, Urungkan harus
     * MEMULIHKAN nilai lamanya — bukan menghapus barisnya. Menghapus akan
     * menghilangkan follow-up pertama yang sah hanya karena yang kedua
     * diurungkan.
     */
    public function test_urungkan_memulihkan_baris_yang_diperbarui(): void
    {
        $ctl = $this->kontrol();

        $this->catat($ctl, ['hasil' => OrtControl::FU_TIDAK_RESPON, 'catatan' => 'panggilan pertama'])->assertRedirect();
        $this->catat($ctl, ['hasil' => OrtControl::FU_MENOLAK, 'catatan' => 'salah klik'])->assertRedirect();

        $token = session('ort_undo');
        $this->withSession($this->sesi)->post('/tracking-ortho/urungkan/' . $token)->assertRedirect();

        $baris = $this->riwayat($ctl);

        $this->assertCount(1, $baris, 'Barisnya tidak boleh ikut terhapus.');
        $this->assertSame(OrtControl::FU_TIDAK_RESPON, $baris[0]->fu_result, 'Nilai lama harus kembali.');
        $this->assertSame('panggilan pertama', $baris[0]->fu_note);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Batalkan follow-up
    // ════════════════════════════════════════════════════════════════════════

    /**
     * ⭐ "Panggilan itu tidak dihitung" harus berarti hal yang SAMA di kedua
     * tempat. Kalau ort_control dikosongkan tapi ort_followup dibiarkan,
     * tombolnya kembali hijau sementara riwayat masih mengaku pasien ditelepon
     * hari itu — dua layar, dua jawaban, untuk satu pertanyaan.
     */
    public function test_batal_follow_up_membuang_baris_riwayatnya(): void
    {
        $ctl = $this->kontrol();

        $this->catat($ctl, ['hasil' => OrtControl::FU_DIHUBUNGI])->assertRedirect();
        $this->assertCount(1, $this->riwayat($ctl));

        $this->withSession($this->sesi)
            ->from('/tracking-ortho?utama=' . $this->placeId)
            ->post('/tracking-ortho/kontrol/' . $ctl->id . '/batal-follow-up')
            ->assertRedirect();

        $this->assertCount(0, $this->riwayat($ctl));
        $this->assertNull($ctl->refresh()->fu_date);
    }

    /** Dan membatalkan pembatalan memulihkan barisnya utuh. */
    public function test_urungkan_pembatalan_membangun_ulang_barisnya(): void
    {
        $ctl = $this->kontrol();

        $this->catat($ctl, ['hasil' => OrtControl::FU_SALAH_NOMOR, 'catatan' => '0812 bukan pasien ini'])->assertRedirect();

        $this->withSession($this->sesi)
            ->from('/tracking-ortho?utama=' . $this->placeId)
            ->post('/tracking-ortho/kontrol/' . $ctl->id . '/batal-follow-up')
            ->assertRedirect();

        $token = session('ort_undo');
        $this->withSession($this->sesi)->post('/tracking-ortho/urungkan/' . $token)->assertRedirect();

        $baris = $this->riwayat($ctl);

        $this->assertCount(1, $baris, 'Barisnya harus kembali ada.');
        $this->assertSame(OrtControl::FU_SALAH_NOMOR, $baris[0]->fu_result);
        $this->assertSame('0812 bukan pasien ini', $baris[0]->fu_note);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Kamus tidak diduplikasi
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Kolom hasil/kanal di ort_followup bernama PERSIS seperti di ort_control,
     * dan kamusnya tetap satu-satunya di OrtControl. Dua kamus untuk satu nilai
     * adalah cara dua layar mulai menampilkan label berbeda untuk hal yang sama.
     */
    public function test_kamus_tidak_diduplikasi_di_model_riwayat(): void
    {
        $this->assertFalse(
            defined(OrtFollowUp::class . '::FU_HASIL'),
            'OrtFollowUp tidak boleh punya kamus sendiri — satu-satunya di OrtControl::FU_HASIL.'
        );
        $this->assertNotEmpty(OrtControl::FU_HASIL);
    }

    /** Baris riwayat tidak pernah diubah sesudah ditulis, jadi tanpa updated_at. */
    public function test_tabel_riwayat_tanpa_updated_at(): void
    {
        $this->assertFalse((new OrtFollowUp)->usesTimestamps());
        $this->assertFalse(
            Schema::hasColumn('ort_followup', 'updated_at'),
            'Kolom updated_at tidak dipakai — jangan menambahkannya tanpa alasan.'
        );
    }
}
