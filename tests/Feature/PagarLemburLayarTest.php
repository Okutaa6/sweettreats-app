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
            // orderBy WAJIB: `first()` tanpa urutan memilih orang yang berbeda
            // dari run ke run, dan uji yang fixture-nya berpindah-pindah akan
            // hijau/merah bergantian tanpa ada kode yang berubah.
            ->orderBy('u.id')
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

    /* ═══════════════════════════════════════════════════════════════════
       FOTO BUKTI WAJIB untuk lembur & WFH (Nisa/Supervisor, 2026-09-26).

       Diukur di prod 26 Sep: 302 dari 531 klaim lembur (56,9%) dikirim tanpa
       foto, dan 96,6% tetap disetujui — premis lama "approver yang menilai
       kelengkapan" sudah diuji kenyataan dan gagal.

       ⚠️ Pagar yang kegalakan di sini ongkosnya BUKAN daftar yang rapi,
       melainkan orang yang berhenti mengklaim lembur yang benar-benar
       dikerjakan. Karena itu tiap penolakan di bawah berpasangan dengan
       kendali yang membuktikan jenis LAIN tidak ikut terkena.
       ═══════════════════════════════════════════════════════════════════ */

    private ?object $serbaBisa = null;

    /**
     * Orang yang aturan kerjanya mengizinkan lembur DAN wfh.
     *
     * Di-cache: kalau tiap pemanggilan memilih ulang, baris yang dibuat untuk
     * ORANG A bisa diadu dengan kiriman ORANG B — dan uji kembar akan lulus
     * atau gagal karena alasan yang salah.
     */
    private function orangSerbaBisa(): object
    {
        if ($this->serbaBisa) return $this->serbaBisa;

        $m = new \ReflectionMethod(
            \App\Http\Controllers\Absensi\AkunPengajuanController::class, 'jenisUntuk'
        );
        $m->setAccessible(true);

        $kandidat = DB::table('users as u')
            ->join('att_user_rule as ur', 'ur.user_id', '=', 'u.id')
            ->where('u.status', 1)->whereNull('ur.effective_to')
            // orderBy WAJIB: tanpa urutan yang pasti, tiap uji bisa memilih
            // orang yang berbeda dan kegagalannya jadi tak bisa diulang.
            ->orderBy('u.id')->distinct()->limit(40)
            ->get(['u.id', 'u.nama', 'u.group_id', 'u.raw_branch_id']);

        $alur = new AttendanceRequestWorkflow();

        foreach ($kandidat as $u) {
            $jenis = $m->invoke(null, $u->id);
            if (! isset($jenis['lembur'], $jenis['wfh'])) continue;

            // Harus punya RANTAI PERSETUJUAN untuk tiap jenis yang diuji —
            // tanpa itu store() menolak dengan "alur persetujuan belum diatur",
            // dan ujinya merah karena prasyarat, bukan karena pagar foto.
            foreach (['lembur', 'wfh', 'sakit', 'cuti', 'izin'] as $t) {
                if (empty($alur->chainFor($u->id, $t)[0])) continue 2;
            }

            return $this->serbaBisa = $u;
        }

        $this->markTestSkipped(
            'Tak ada karyawan yang boleh lembur+wfh sekaligus punya alur persetujuan lengkap.');
    }

    /** Baris lembur milik orangSerbaBisa() — pasangan yang benar untuk kirim(). */
    private function lemburSerbaBisa(string $jamFrom, string $jamTo): string
    {
        return (string) DB::table('att_request')->insertGetId([
            'user_id' => $this->orangSerbaBisa()->id, 'tipe' => 'lembur', 'sub_tipe' => 'jam',
            'tanggal_from' => $this->tanggal, 'tanggal_to' => $this->tanggal,
            'jam_from' => $jamFrom, 'jam_to' => $jamTo,
            'status' => 'pending_l1',
            'claimed_menit' => (int) Carbon::createFromFormat('H:i', $jamTo)
                ->diffInMinutes(Carbon::createFromFormat('H:i', $jamFrom)),
            'alasan' => 'uji foto wajib (baris pembanding)',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Hari kerja jauh di depan, bukan hari libur mingguan siapa pun di uji ini. */
    private function tanggalKerja(): string
    {
        return Carbon::create(2027, 6, 1)->startOfDay()->next(Carbon::WEDNESDAY)->toDateString();
    }

    /**
     * Hapus berkas yang baru saja diunggah uji.
     *
     * Berkas TIDAK ikut rollback transaksi — tanpa ini gudang lampiran lokal
     * bertambah tiap kali suite dijalankan.
     */
    private function bersihkanBerkas(int $n = 1): void
    {
        $dir = storage_path('app/att_request');

        foreach (AttRequest::whereNotNull('foto')->orderByDesc('id')->limit($n)->pluck('foto') as $f) {
            if ($f && is_file($dir . '/' . $f)) unlink($dir . '/' . $f);
        }
    }

    /** Berapa berkas yang ada di gudang lampiran sekarang. */
    private function cacahBerkas(): int
    {
        $dir = storage_path('app/att_request');

        return is_dir($dir) ? count(glob($dir . '/*')) : 0;
    }

    /**
     * ⚠️ Berkas ikut di dalam $data, BUKAN argumen ketiga: argumen ketiga
     * `post()` itu HEADERS. Menaruh UploadedFile di sana membuat `hasFile()`
     * false, dan ujinya melapor "ditolak padahal foto dikirim" — merah palsu
     * yang menyalahkan kode, bukan ujinya.
     */
    private function kirim(array $isi, $foto = null)
    {
        $data = array_merge([
            'tanggal_from' => $this->tanggal,
            'tanggal_to'   => $this->tanggal,
            'alasan'       => 'uji foto wajib',
        ], $isi);

        // Lembur & WFH punya aturan tanggalnya sendiri; yang diuji di sini
        // gerbang FOTO, jadi jangan sampai merah karena hal lain.
        if (($isi['tipe'] ?? null) === 'wfh') {
            $data['tanggal_from'] = $data['tanggal_to'] = $this->tanggalKerja();
        }

        if ($foto) $data['foto'] = $foto;

        return $this->withSession($this->sesi($this->orangSerbaBisa()))
            ->post('/akun/pengajuan', $data);
    }

    public function test_foto_lembur_TANPA_foto_ditolak(): void
    {
        $sebelum = AttRequest::count();

        $this->kirim([
            'tipe' => 'lembur', 'sub_tipe' => 'jam',
            'jam_from' => '19:00', 'jam_to' => '21:00',
        ])->assertSessionHas('error');

        $this->assertStringContainsString('foto bukti', (string) session('error'),
            'Pesannya harus menyebut APA yang kurang, bukan sekadar menolak.');
        $this->assertSame($sebelum, AttRequest::count(), 'Tidak boleh ada baris yang lahir.');
    }

    public function test_foto_lembur_DENGAN_foto_diterima(): void
    {
        // Kendali positif — inilah yang memastikan pagarnya tidak menutup jalan
        // orang yang memang lembur.
        $sebelum = AttRequest::count();

        $this->kirim(
            ['tipe' => 'lembur', 'sub_tipe' => 'jam', 'jam_from' => '05:00', 'jam_to' => '07:00'],
            \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg')
        );

        $this->assertStringNotContainsString('foto bukti', (string) session('error'));
        $this->assertSame($sebelum + 1, AttRequest::count());
        $this->bersihkanBerkas();
    }

    public function test_foto_wfh_TANPA_foto_ditolak(): void
    {
        $sebelum = AttRequest::count();

        $this->kirim(['tipe' => 'wfh'])->assertSessionHas('error');

        $this->assertStringContainsString('foto bukti', (string) session('error'));
        $this->assertSame($sebelum, AttRequest::count());
    }

    public function test_foto_wfh_DENGAN_foto_diterima(): void
    {
        $sebelum = AttRequest::count();

        $res = $this->kirim(
            ['tipe' => 'wfh'],
            \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg')
        );

        $this->assertStringNotContainsString('foto bukti', (string) session('error'));
        $this->assertSame($sebelum + 1, AttRequest::count(),
            'error=' . var_export(session('error'), true)
            . ' status=' . $res->getStatusCode()
            . ' ke=' . ($res->headers->get('Location') ?: '-'));
        $this->bersihkanBerkas();
    }

    /** ⭐ Kendali: pagar baru tidak boleh bocor ke jenis lain. */
    public function test_foto_sakit_1_hari_tanpa_foto_TETAP_diterima(): void
    {
        // Sakit 1–2 hari SENGAJA tanpa surat: menuntut surat untuk demam
        // semalam justru mendorong orang masuk kerja sambil sakit.
        $sebelum = AttRequest::count();

        $this->kirim(['tipe' => 'sakit']);

        $this->assertStringNotContainsString('foto bukti', (string) session('error'));
        $this->assertStringNotContainsString('surat dokter', (string) session('error'));
        $this->assertSame($sebelum + 1, AttRequest::count());
    }

    /** ⭐ Kendali: cuti & izin tetap boleh tanpa lampiran. */
    public function test_foto_cuti_dan_izin_tanpa_foto_TETAP_diterima(): void
    {
        foreach (['cuti', 'izin'] as $tipe) {
            $sebelum = AttRequest::count();

            $this->kirim(['tipe' => $tipe]);

            $this->assertStringNotContainsString('foto bukti', (string) session('error'),
                $tipe . ' tidak boleh ikut terkena pagar foto.');
            $this->assertSame($sebelum + 1, AttRequest::count(), $tipe . ' harus tetap masuk.');
        }
    }

    /**
     * ⭐ Kiriman yang DITOLAK tidak boleh meninggalkan berkas yatim.
     *
     * Gerbangnya sengaja ditaruh sebelum $file->move(); kalau kelak ada yang
     * memindahkannya ke bawah, gudang lampiran akan pelan-pelan terisi berkas
     * milik pengajuan yang tidak pernah ada.
     */
    public function test_foto_kiriman_yang_ditolak_tak_meninggalkan_berkas(): void
    {
        $sebelum = $this->cacahBerkas();

        // Ditolak karena LEMBUR KEMBAR, tapi membawa foto — jadi yang diuji
        // memang urutan simpan-vs-tolak, bukan ketiadaan foto.
        $this->lemburSerbaBisa('19:15', '21:10');
        $this->kirim(
            ['tipe' => 'lembur', 'sub_tipe' => 'jam', 'jam_from' => '19:15', 'jam_to' => '21:10'],
            \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg')
        )->assertSessionHas('error');

        $this->assertSame($sebelum, $this->cacahBerkas(),
            'Kiriman yang ditolak meninggalkan berkas di storage/app/att_request.');
    }

    /**
     * Urutan gerbang: KEMBAR lebih dulu daripada FOTO.
     *
     * Kalau terbalik, orang yang mengirim lembur kembar tanpa foto akan disuruh
     * melampirkan foto, mengirim ulang, lalu BARU ditolak karena kembar — dua
     * kali kerja untuk pengajuan yang memang tidak boleh ada.
     */
    public function test_foto_pesan_KEMBAR_menang_atas_pesan_foto(): void
    {
        $pertama = $this->lemburSerbaBisa('19:15', '21:10');

        $this->kirim([
            'tipe' => 'lembur', 'sub_tipe' => 'jam',
            'jam_from' => '19:15', 'jam_to' => '21:10',
        ])->assertSessionHas('error');

        $galat = (string) session('error');
        $this->assertStringContainsString('#' . $pertama, $galat);
        $this->assertStringNotContainsString('foto bukti', $galat);
    }

    /**
     * Dua unggahan dalam DETIK yang sama tidak boleh saling menimpa.
     *
     * Nama berkasnya dulu `{user}_{time()}.{ext}` — dua kiriman orang yang sama
     * dalam satu detik menghasilkan nama identik. Di Linux (prod) `rename`
     * menimpa DIAM-DIAM: foto pengajuan lama berganti jadi foto pengajuan baru,
     * dan tak ada gejala sampai ada yang membuka lampiran lama dan melihat
     * bukti milik hari lain. Sejak foto WAJIB, tiap pengajuan membawa berkas —
     * peluangnya naik, bukan turun.
     */
    public function test_foto_dua_unggahan_sedetik_tidak_saling_menimpa(): void
    {
        $sebelum = $this->cacahBerkas();

        // Jam berbeda supaya tidak tertahan pagar kembar — yang diuji di sini
        // penamaan berkasnya, bukan pagar itu.
        foreach ([['05:00', '07:00'], ['13:00', '15:00']] as [$dari, $sampai]) {
            $this->kirim(
                ['tipe' => 'lembur', 'sub_tipe' => 'jam', 'jam_from' => $dari, 'jam_to' => $sampai],
                \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg')
            );
        }

        $this->assertSame($sebelum + 2, $this->cacahBerkas(),
            'Dua kiriman berfoto harus menghasilkan DUA berkas — kalau cuma satu, '
            . 'yang kedua menimpa yang pertama dan satu pengajuan kehilangan buktinya.');

        $this->bersihkanBerkas(2);
    }

    /** Layar ikut memberi tahu SEBELUM dikirim (P3), bukan menolak sesudahnya. */
    public function test_foto_form_menyiarkan_daftar_jenis_yang_wajib(): void
    {
        $html = $this->withSession($this->sesi($this->orangSerbaBisa()))
            ->get('/akun/pengajuan')->assertOk()->getContent();

        $this->assertStringContainsString('TIPE_WAJIB_FOTO', $html,
            'Form membaca daftarnya dari model — satu sumber kebenaran.');
        $this->assertStringContainsString('"lembur"', $html);
        $this->assertStringContainsString('tidak bisa dikirim', $html,
            'Teks wajibnya ikut terkirim ke layar.');
        $this->assertStringNotContainsString(
            'opsional &mdash; kalau lembur/kerja dari rumah tanpa absen mesin', $html,
            'Kalimat lama sudah tidak benar untuk lembur/wfh.');
    }

    /* ═══════════════════════════════════════════════════════════════════
       BERKAS YATIM & PRA-CEK JATAH WFH (temuan audit HUB 26 Sep).

       `$file->move()` terjadi SEBELUM transaksi dibuka, dan berkas tidak ikut
       rollback. Tiga jalan gagal di dalam transaksi (jatah WFH, kembar cek
       ke-2, workflow submit) dulu meninggalkan berkas yatim. Sejak foto WAJIB,
       yang dulu sesekali jadi SETIAP KALI.

       Jatah WFH juga satu-satunya yang tak punya pra-cek: orangnya harus
       melampirkan foto dulu baru diberi tahu jatahnya habis.
       ═══════════════════════════════════════════════════════════════════ */

    /** Habiskan jatah WFH bulan tanggal ini (2 hari/bulan). */
    private function habiskanJatahWfh(string $tanggal): void
    {
        $orang = $this->orangSerbaBisa();
        $bulan = \Illuminate\Support\Carbon::parse($tanggal)->startOfMonth();

        for ($i = 0; $i < \App\Http\Controllers\Absensi\AkunPengajuanController::WFH_JATAH_BULANAN; $i++) {
            $hari = $bulan->copy()->addDays($i)->toDateString();
            DB::table('att_request')->insert([
                'user_id' => $orang->id, 'tipe' => 'wfh',
                'tanggal_from' => $hari, 'tanggal_to' => $hari,
                'status' => 'pending_l1', 'alasan' => 'uji jatah',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_yatim_wfh_melebihi_jatah_ditolak_dan_TAK_meninggalkan_berkas(): void
    {
        $this->habiskanJatahWfh($this->tanggalKerja());
        $sebelumBerkas = $this->cacahBerkas();

        $res = $this->kirim(
            ['tipe' => 'wfh'],
            \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg')
        );

        $res->assertSessionHas('error');
        $this->assertStringContainsString('Jatah kerja dari rumah', (string) session('error'),
            'Pesannya harus menyebut jatah, dan kalimatnya datang dari '
            . 'pastikanJatahWfhCukup() — satu tempat, bukan disalin.');

        // ⭐ Inti temuan HUB: kiriman yang ditolak tidak boleh menyisakan berkas.
        $this->assertSame($sebelumBerkas, $this->cacahBerkas(),
            'Kiriman WFH yang ditolak meninggalkan berkas yatim di gudang lampiran.');
    }

    /** ⭐ Kendali positif: pembersihan tidak boleh kebablasan. */
    public function test_yatim_kiriman_WFH_yang_BERHASIL_berkasnya_tetap_ada(): void
    {
        $sebelumBerkas = $this->cacahBerkas();

        $this->kirim(
            ['tipe' => 'wfh'],
            \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg')
        );

        $this->assertStringNotContainsString('Jatah kerja dari rumah', (string) session('error'));
        $this->assertSame($sebelumBerkas + 1, $this->cacahBerkas(),
            'Jalur sukses TIDAK boleh ikut terhapus — lampirannya justru buktinya.');

        // Dan berkasnya memang milik baris yang tersimpan.
        $baris = AttRequest::where('tipe', 'wfh')->orderByDesc('id')->first();
        $this->assertNotNull($baris->foto);
        $this->assertFileExists(storage_path('app/att_request/' . $baris->foto));

        $this->bersihkanBerkas();
    }

    /** WFH masih dalam jatah → jalan seperti biasa (pra-cek tidak menghalangi). */
    public function test_yatim_wfh_dalam_jatah_tetap_diterima(): void
    {
        $sebelum = AttRequest::count();

        $this->kirim(
            ['tipe' => 'wfh'],
            \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg')
        );

        $this->assertSame($sebelum + 1, AttRequest::count(),
            'Ditolak karena hal lain: ' . (string) session('error'));

        $this->bersihkanBerkas();
    }

    /**
     * ⭐ Pra-cek jatah TIDAK menggeser gerbang foto.
     *
     * WFH yang jatahnya habis DAN tanpa foto → yang muncul pesan FOTO. Urutan
     * ini disengaja: jatah habis lebih berguna diketahui sesudah orangnya yakin
     * pengajuannya lengkap, sedangkan foto adalah syarat kelengkapan itu.
     */
    public function test_yatim_gerbang_FOTO_tetap_bicara_duluan(): void
    {
        $this->habiskanJatahWfh($this->tanggalKerja());

        $this->kirim(['tipe' => 'wfh'])->assertSessionHas('error');

        $galat = (string) session('error');
        $this->assertStringContainsString('foto bukti', $galat);
        $this->assertStringNotContainsString('Jatah kerja dari rumah', $galat);
    }

    /**
     * ⭐ Lapis PEMBERSIHAN diuji sendiri, lewat jalan gagal yang TIDAK punya
     * pra-cek: alur persetujuan belum diatur → `submit()` melempar di dalam
     * transaksi, baris DB di-rollback, tapi berkasnya sudah terlanjur pindah.
     *
     * Kenapa uji ini perlu terpisah: pra-cek jatah dan pembersihan `@unlink`
     * saling menutupi. Kalau keduanya hanya diuji lewat kasus jatah WFH,
     * mencabut salah satunya tetap hijau — dan uji yang tidak bisa merah saat
     * pagarnya dicabut bukan pagar, cuma hiasan.
     */
    public function test_yatim_workflow_gagal_pun_tak_meninggalkan_berkas(): void
    {
        $m = new \ReflectionMethod(
            \App\Http\Controllers\Absensi\AkunPengajuanController::class, 'jenisUntuk'
        );
        $m->setAccessible(true);
        $alur = new AttendanceRequestWorkflow();

        $korban = null;
        foreach (DB::table('users as u')->join('att_user_rule as ur', 'ur.user_id', '=', 'u.id')
            ->where('u.status', 1)->whereNull('ur.effective_to')
            ->orderBy('u.id')->distinct()->limit(60)
            ->get(['u.id', 'u.nama', 'u.group_id', 'u.raw_branch_id']) as $u) {
            if (! isset($m->invoke(null, $u->id)['lembur'])) continue;
            if (empty($alur->chainFor($u->id, 'lembur')[0])) { $korban = $u; break; }
        }

        if (! $korban) $this->markTestSkipped('Semua kandidat punya alur persetujuan.');

        $sebelumBerkas = $this->cacahBerkas();
        $sebelumBaris  = AttRequest::count();

        $res = $this->withSession($this->sesi($korban))->post('/akun/pengajuan', [
            'tipe' => 'lembur', 'sub_tipe' => 'jam',
            'tanggal_from' => $this->tanggalKerja(), 'tanggal_to' => $this->tanggalKerja(),
            'jam_from' => '19:00', 'jam_to' => '21:00', 'alasan' => 'uji berkas yatim',
            'foto' => \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $res->assertSessionHas('error');
        $this->assertSame($sebelumBaris, AttRequest::count(), 'Barisnya harus ikut rollback.');
        $this->assertSame($sebelumBerkas, $this->cacahBerkas(),
            'Transaksi di-rollback tapi berkasnya tertinggal — persis yatim yang dicari.');
    }

    /**
     * Pra-cek TIDAK menggantikan cek berkunci di dalam transaksi.
     *
     * Kalau kelak ada yang menghapus cek di dalam transaksi karena "sudah ada
     * pra-cek", dua kiriman berbarengan bisa sama-sama lolos. Uji ini menjaga
     * bentuknya: yang di dalam transaksi masih memakai kunci.
     */
    public function test_yatim_cek_jatah_di_dalam_transaksi_masih_berkunci(): void
    {
        $kode = file_get_contents(app_path('Http/Controllers/Absensi/AkunPengajuanController.php'));

        $this->assertMatchesRegularExpression(
            '/DB::transaction\(function[\s\S]{0,600}pastikanJatahWfhCukup\(\$userId, \$data\[.tanggal_from.\], \$data\[.tanggal_to.\]\)/',
            $kode,
            'Cek di dalam transaksi harus tetap dipanggil TANPA argumen $kunci=false '
            . '(default true = lockForUpdate).');

        $this->assertStringContainsString('hariWfhTerpakai($userId, $awal, $akhir, $kunci)', $kode);
    }
}
