<?php

namespace Tests\Feature;

use App\Services\LemburDodo;
use App\Services\LemburKembar;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dua pagar untuk pengajuan lembur — uji terima §3
 * docs/rencana/rencana-pagar-lembur.md.
 *
 * Keduanya soal uang yang salah bayar tanpa ada yang bersuara:
 *   1. dua pengajuan untuk sesi yang sama, dua-duanya di-ACC (Afrina, 10 Agu);
 *   2. chip DODO salah pilih yang baru ketahuan SESUDAH keputusan diambil.
 *
 * Skenarionya ditanam sendiri di bulan yang jauh ke depan supaya tak
 * bersinggungan dengan data nyata — termasuk req#162 (Melani) yang sedang
 * menunggu konfirmasi dan tak boleh disentuh. Semua dibungkus transaksi yang
 * di-rollback (DatabaseTransactions, bukan RefreshDatabase — DB dev dipakai
 * bersama).
 */
class PagarLemburTest extends TestCase
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

        // Aturan BAKU: hanya jenis ini yang menyimpan baris hari, jadi hanya
        // dari sini "hari libur mingguan" bisa dijawab pasti.
        $u = DB::table('users as u')
            ->join('att_user_rule as ur', 'ur.user_id', '=', 'u.id')
            ->join('att_work_rule as r', 'r.id', '=', 'ur.rule_id')
            ->join('att_work_rule_day as d', 'd.rule_id', '=', 'r.id')
            ->where('u.status', 1)->where('r.tipe', 'baku')->where('d.libur', 1)
            ->whereNull('ur.effective_to')
            ->first(['u.id', 'u.nama', 'd.day as hari_libur']);

        if (! $u) $this->markTestSkipped('Tak ada karyawan ber-aturan baku dengan hari libur mingguan.');

        $this->orang   = $u;
        $awal          = Carbon::create(2027, 5, 1)->startOfDay();
        $this->liburKe = $awal->copy()->next((int) $u->hari_libur)->toDateString();
        $this->kerjaKe = Carbon::parse($this->liburKe)->addDay()->toDateString();
    }

    /** @return string id pengajuan yang baru dibuat */
    private function lembur(
        string $tanggal,
        ?string $jamFrom,
        ?string $jamTo,
        string $status = 'pending_l1',
        string $subTipe = 'jam'
    ): string {
        $menit = ($jamFrom && $jamTo)
            ? (int) Carbon::createFromFormat('H:i', $jamTo)->diffInMinutes(Carbon::createFromFormat('H:i', $jamFrom))
            : 0;

        return (string) DB::table('att_request')->insertGetId([
            'user_id'      => $this->orang->id,
            'tipe'         => 'lembur',
            'sub_tipe'     => $subTipe,
            'tanggal_from' => $tanggal,
            'tanggal_to'   => $tanggal,
            'jam_from'     => $jamFrom,
            'jam_to'       => $jamTo,
            'status'       => $status,
            'claimed_menit' => $menit,
            'alasan'       => 'uji pagar lembur',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function attDay(string $tanggal, int $lemburMenit): void
    {
        DB::table('att_day')->insert([
            'user_id' => $this->orang->id, 'work_date' => $tanggal, 'status' => 'hadir',
            'telat_menit' => 0, 'lembur_menit_disetujui' => $lemburMenit,
            'scan_count' => 0, 'single_scan' => 0, 'computed_at' => now(),
        ]);
    }

    private function rentang(): array
    {
        $a = Carbon::parse($this->liburKe);
        return [$a->copy()->startOfMonth()->toDateString(), $a->copy()->endOfMonth()->toDateString()];
    }

    // ═══════════ PAGAR 1 — lembur kembar ═══════════

    /** §3 #1 — kembar PERSIS ditolak, dan pesannya menyebut nomor yang sudah ada. */
    public function test_kembar_persis_terdeteksi_dan_pesannya_menyebut_nomor_pengajuan(): void
    {
        // Persis kasus Afrina: tanggal & jam identik, kiriman kedua menyusul.
        $pertama = $this->lembur($this->kerjaKe, '19:15', '21:10');

        $tindihan = LemburKembar::bertindih($this->orang->id, $this->kerjaKe, '19:15', '21:10');

        $this->assertCount(1, $tindihan);
        $this->assertTrue(LemburKembar::adaYangPersis($tindihan), 'Jam identik WAJIB terbaca sebagai kembar persis.');

        $pesan = LemburKembar::pesanTolak($tindihan, $this->kerjaKe, '19:15', '21:10');
        $this->assertStringContainsString('#' . $pertama, $pesan,
            'Pesan harus menyebut nomor pengajuan yang sudah ada — "gagal" saja membuat orang mengirim ulang.');
    }

    /** §3 #2 — tindihan SEBAGIAN tertangkap, tapi tidak ditolak. */
    public function test_tindihan_sebagian_tertangkap_tapi_tidak_ditolak(): void
    {
        $this->lembur($this->kerjaKe, '19:00', '21:00');

        $tindihan = LemburKembar::bertindih($this->orang->id, $this->kerjaKe, '19:15', '21:10');

        $this->assertCount(1, $tindihan, 'Tindihan sebagian tak boleh lolos hanya karena jamnya tak persis sama.');
        $this->assertFalse(LemburKembar::adaYangPersis($tindihan),
            'Bertindih sebagian BUKAN alasan menolak — penyetuju yang diberi tahu.');
        $this->assertStringContainsString('19:00–21:00', LemburKembar::ringkas($tindihan));
    }

    /** §3 #3 — dua sesi terpisah di hari yang sama tetap boleh. */
    public function test_dua_sesi_terpisah_di_hari_yang_sama_tetap_boleh(): void
    {
        $this->lembur($this->kerjaKe, '08:00', '10:00');

        $this->assertSame([], LemburKembar::bertindih($this->orang->id, $this->kerjaKe, '19:00', '21:00'),
            'Lembur pagi dan lembur malam di hari yang sama itu sah.');
    }

    /**
     * §3 #3, versi PRODUKSI — dua giliran sah milik Alpajri Suhendar, 11 Agu 2026.
     *
     * Bentuknya diambil apa adanya dari prod (sapuan baca-saja 27 Agu):
     *   req#113  08:00–10:00  120 menit  approved
     *   req#92   18:00–23:31  331 menit  approved
     *   att_day  hadir, scan 07:42–23:31, lembur 451 = 120 + 331 (cocok)
     *
     * Orang ini benar-benar bekerja dua giliran dan reconciler benar
     * menjumlahkannya. Kalau pagar kembar menolak yang seperti ini, ia menolak
     * orang yang jujur — kegagalan yang jauh lebih mahal daripada kembar yang
     * lolos, karena korbannya berhenti memakai formnya sama sekali.
     *
     * Sengaja dipisah dari uji sesi-terpisah yang jamnya karangan: fixture nyata
     * memakai jam ganjil (23:31, bukan 21:00) dan rentang yang jauh lebih
     * panjang — persis jenis angka yang membuat pembanding ceroboh tergelincir.
     */
    public function test_dua_giliran_sah_dari_prod_alpajri_11_agu_tetap_lewat(): void
    {
        $pagi = $this->lembur($this->kerjaKe, '08:00', '10:00', 'approved');

        // Giliran malam menyusul — inilah yang dulu tak punya pagar sama sekali,
        // dan yang sekarang tak boleh ikut terjaring.
        $this->assertSame([], LemburKembar::bertindih($this->orang->id, $this->kerjaKe, '18:00', '23:31'),
            'Dua giliran terpisah di hari yang sama WAJIB lewat — ini kejadian nyata di prod.');

        $malam = $this->lembur($this->kerjaKe, '18:00', '23:31', 'approved');

        // Dan dari arah sebaliknya juga: kartu mana pun tak boleh menuduh yang lain.
        $rows = \App\Models\AttRequest::whereIn('id', [$pagi, $malam])->get();
        $this->assertSame([], LemburKembar::peta($rows),
            'Layar persetujuan pun tak boleh memberi peringatan pada dua giliran yang sah.');

        // Angka yang dijumlahkan reconciler tetap seperti di prod: 120 + 331.
        $this->assertSame(451, (int) DB::table('att_request')->whereIn('id', [$pagi, $malam])->sum('claimed_menit'));
    }

    /** Sesi yang BERSAMBUNG (10:00 selesai → 10:00 mulai) bukan tindihan. */
    public function test_sesi_bersambung_bukan_tindihan(): void
    {
        $this->lembur($this->kerjaKe, '08:00', '10:00');

        $this->assertSame([], LemburKembar::bertindih($this->orang->id, $this->kerjaKe, '10:00', '12:00'));
    }

    /** Yang sudah ditolak tak boleh menghalangi pengganti yang benar. */
    public function test_pengajuan_rejected_tidak_menghalangi(): void
    {
        // Begitulah kembar Afrina dibetulkan: req#31 di-`rejected`, bukan
        // dihapus. Kalau baris itu ikut memagari, pengiriman ulang yang sah
        // jadi mustahil.
        $this->lembur($this->kerjaKe, '19:15', '21:10', 'rejected');

        $this->assertSame([], LemburKembar::bertindih($this->orang->id, $this->kerjaKe, '19:15', '21:10'));
    }

    /** Tanggal lain tak ikut terseret. */
    public function test_tanggal_berbeda_tidak_dianggap_kembar(): void
    {
        $this->lembur($this->kerjaKe, '19:15', '21:10');

        $this->assertSame([], LemburKembar::bertindih($this->orang->id, $this->liburKe, '19:15', '21:10'));
    }

    /** Jam tak lengkap → dilaporkan, tapi TAK PERNAH jadi alasan menolak. */
    public function test_jam_tak_lengkap_dilaporkan_tapi_tidak_menolak(): void
    {
        $this->lembur($this->kerjaKe, null, null);

        $tindihan = LemburKembar::bertindih($this->orang->id, $this->kerjaKe, '19:15', '21:10');

        $this->assertCount(1, $tindihan);
        $this->assertFalse($tindihan[0]['jam_lengkap']);
        $this->assertFalse(LemburKembar::adaYangPersis($tindihan));
    }

    /** Peta borongan (layar persetujuan) memberi jawaban sama dengan per-kartu. */
    public function test_peta_borongan_sepadan_dengan_pemeriksaan_satuan(): void
    {
        $a = $this->lembur($this->kerjaKe, '19:00', '21:00');
        $b = $this->lembur($this->kerjaKe, '19:15', '21:10');
        $c = $this->lembur($this->kerjaKe, '05:00', '06:00');    // bersih

        $rows = \App\Models\AttRequest::whereIn('id', [$a, $b, $c])->get();
        $peta = LemburKembar::peta($rows);

        $this->assertArrayHasKey((string) $a, $peta);
        $this->assertArrayHasKey((string) $b, $peta);
        $this->assertArrayNotHasKey((string) $c, $peta, 'Yang tak bertindih tak boleh masuk peta.');
        $this->assertCount(1, $peta[(string) $a], 'Kartu tak boleh menuduh dirinya sendiri.');
    }

    // ═══════════ PAGAR 2 — chip diperiksa sebelum keputusan ═══════════

    /** §3 #5 — chip salah pada pengajuan yang MASIH MENUNGGU sudah muncul. */
    public function test_chip_salah_pada_pengajuan_pending_sudah_muncul(): void
    {
        // Kasus req#172 (Ekky) & req#162 (Melani): dua-duanya `pending_l1`,
        // dua-duanya tak terlihat sama sekali dengan aturan lama.
        $this->lembur($this->liburKe, '19:00', '21:00', 'pending_l1', 'jam');

        [$from, $to] = $this->rentang();
        $temuan = collect(LemburDodo::chipMencurigakan($from, $to))
            ->where('user_id', $this->orang->id)->where('tanggal', $this->liburKe)->values();

        $this->assertCount(1, $temuan, 'Yang masih menunggu keputusan justru paling murah dibetulkan.');
        $this->assertSame('pending_l1', $temuan[0]['status']);
        $this->assertTrue($temuan[0]['libur']);
    }

    /** §3 #6 — yang sudah approved tetap muncul (tak ada regresi). */
    public function test_chip_salah_pada_pengajuan_approved_tetap_muncul(): void
    {
        $this->attDay($this->liburKe, 120);
        $this->lembur($this->liburKe, '19:00', '21:00', 'approved', 'jam');

        [$from, $to] = $this->rentang();
        $temuan = collect(LemburDodo::chipMencurigakan($from, $to))
            ->where('user_id', $this->orang->id)->where('tanggal', $this->liburKe)->values();

        $this->assertCount(1, $temuan);
        $this->assertSame('approved', $temuan[0]['status']);
    }

    /** Yang sudah ditolak TIDAK ikut diperiksa — tak ada yang perlu dibetulkan. */
    public function test_chip_salah_pada_pengajuan_rejected_tidak_muncul(): void
    {
        $this->lembur($this->liburKe, '19:00', '21:00', 'rejected', 'jam');

        [$from, $to] = $this->rentang();

        $this->assertCount(0, collect(LemburDodo::chipMencurigakan($from, $to))
            ->where('user_id', $this->orang->id)->where('tanggal', $this->liburKe));
    }

    /** §3 #7 — chip yang cocok tidak diganggu, di kedua status. */
    public function test_chip_yang_cocok_tidak_diganggu(): void
    {
        $this->lembur($this->liburKe, '19:00', '21:00', 'pending_l1', 'tambah_hari');
        $this->lembur($this->kerjaKe, '19:00', '21:00', 'approved', 'jam');

        [$from, $to] = $this->rentang();

        $this->assertCount(0, collect(LemburDodo::chipMencurigakan($from, $to))
            ->where('user_id', $this->orang->id));
    }

    /** §3 #8 — orang tanpa jawaban "libur atau tidak" TIDAK dituduh. */
    public function test_aturan_yang_tak_bisa_dijawab_tidak_dituduh(): void
    {
        // Orang ber-aturan `Fleksibel` tak punya baris hari sama sekali, jadi
        // liburUntuk() tak memberi jawaban. Menuduh berdasarkan ketidaktahuan
        // menghasilkan peringatan yang lama-lama diabaikan orang.
        $this->assertNull(LemburDodo::masalahChip(null, 'jam'));
        $this->assertNull(LemburDodo::masalahChip(null, 'tambah_hari'));

        $this->assertNotNull(LemburDodo::masalahChip(true, 'jam'));
        $this->assertNotNull(LemburDodo::masalahChip(false, 'tambah_hari'));
        $this->assertNull(LemburDodo::masalahChip(true, 'tambah_hari'));
        $this->assertNull(LemburDodo::masalahChip(false, 'jam'));
    }

    /**
     * §3 #9 — peringatan layar persetujuan memakai liburUntuk(), BUKAN
     * definisi kedua.
     *
     * Uji ini gagal kalau ada yang menyalin definisi "libur" ke layar
     * persetujuan: jawabannya harus identik dengan yang diberi rekonsiliasi,
     * yang menyangkut aturan mingguan + kalender libur + cabang bertugas.
     * Jalan pintas `att_day.status='libur'` sudah terbukti memberi positif
     * palsu — reconciler menulis `hadir` ketika orang bekerja di hari liburnya.
     */
    public function test_peta_chip_layar_persetujuan_ikut_rekonsiliasi(): void
    {
        $salah  = $this->lembur($this->liburKe, '19:00', '21:00', 'pending_l1', 'jam');
        $benar  = $this->lembur($this->kerjaKe, '19:00', '21:00', 'pending_l1', 'jam');

        $rows = \App\Models\AttRequest::whereIn('id', [$salah, $benar])->get();
        $peta = LemburDodo::petaChip($rows);

        $this->assertArrayHasKey((string) $salah, $peta);
        $this->assertArrayNotHasKey((string) $benar, $peta);
        $this->assertTrue($peta[(string) $salah]['libur']);

        // Sumber kebenarannya memang rekonsiliasi, bukan status att_day.
        $jawab = (new \App\Services\AttendanceReconciler())->liburUntuk([
            [$this->orang->id, $this->liburKe],
            [$this->orang->id, $this->kerjaKe],
        ]);
        $this->assertTrue($jawab[$this->orang->id . '|' . $this->liburKe] ?? null);
        $this->assertFalse($jawab[$this->orang->id . '|' . $this->kerjaKe] ?? null);
    }

    /**
     * §3 #11 — invarian `biasa + dodo = total` tetap pegang.
     *
     * Sudah terverifikasi di 31 pasangan orang-bulan produksi. Yang paling
     * mungkin merusaknya adalah perubahan hari ini: pengajuan `pending` kini
     * ikut DIPERIKSA detektor chip — tapi ia TIDAK boleh ikut DIHITUNG sebagai
     * DODO, karena `att_day.lembur_menit_disetujui` hanya memuat yang di-ACC.
     * Kalau ikut, "lembur biasa" jadi minus dan Finance melihat angka mustahil.
     */
    public function test_pending_diperiksa_tapi_tidak_ikut_dihitung_sebagai_dodo(): void
    {
        $this->attDay($this->liburKe, 300);
        $this->lembur($this->liburKe, '16:00', '21:00', 'approved', 'tambah_hari');   // 300 mnt, ikut
        $this->lembur($this->liburKe, '05:00', '07:00', 'pending_l1', 'tambah_hari'); // 120 mnt, JANGAN ikut

        [$from, $to] = $this->rentang();
        $total = (int) DB::table('att_day')->where('user_id', $this->orang->id)
            ->whereBetween('work_date', [$from, $to])->sum('lembur_menit_disetujui');
        $dodo  = LemburDodo::menitPerOrang($from, $to)[$this->orang->id] ?? 0;

        $this->assertSame(300, $dodo, 'Hanya yang di-ACC yang boleh jadi DODO.');
        $this->assertSame($total, ($total - $dodo) + $dodo, 'biasa + dodo WAJIB sama dengan total.');
        $this->assertGreaterThanOrEqual(0, $total - $dodo, 'Lembur biasa tak boleh minus.');
    }
}
