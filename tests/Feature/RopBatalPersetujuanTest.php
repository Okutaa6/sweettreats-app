<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\RopRequest;
use App\Models\RopStatusLog;
use App\Services\RopService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tombol "Batalkan persetujuan" — pintu untuk transisi `disetujui` → `ditolak`.
 *
 * Kenapa ada: 16 Sep 2026 tiga antrian bayar (ROP/SUN/2609/004,
 * ROP/BIN/2609/007, ROP/BIN/2609/008) tertahan 13 hari karena VA-nya
 * kedaluwarsa. `batal` cuma untuk `diajukan`, jendela `urungkan` 120 detik
 * sudah lewat, dan `tolak` — yang SUDAH diizinkan RopService::TRANSISI —
 * tidak punya tombol di layar mana pun. Mesinnya lengkap, pintunya tidak ada.
 *
 * Lima poin uji terima di docs/handoff/prompt-build-tolak-setelah-disetujui.md,
 * plus satu pagar yang lahir dari fitur ini (urungkan pembatalan).
 *
 * Jalan di atas DB nyata dalam transaksi yang di-rollback — pola yang sama
 * dengan PettyCashKembarTest.
 */
class RopBatalPersetujuanTest extends TestCase
{
    use DatabaseTransactions;

    /** Yang menyetujui (Finance). */
    private object $penyetuju;
    /** Yang membatalkan — sengaja ORANG LAIN, dan bukan penyetuju aslinya. */
    private object $pembatal;
    private object $pengaju;
    private string $costCentre;

    protected function setUp(): void
    {
        parent::setUp();

        // Dua akun berbeda yang dua-duanya lolos guard() antrian ROP
        // (canSeeAllBranches). Pembatalan datang belakangan, kadang berhari-hari
        // — orang yang menyetujui bisa sedang cuti, jadi yang diuji justru
        // "penyetuju ≠ pembatal".
        $kandidat = DB::table('users as u')->join('group_path as g', 'g.id', '=', 'u.group_id')
            ->where('u.status', 1)
            ->whereIn('g.nama', ['Finance', 'Super Admin', 'Admin General', 'Konsultan Finance'])
            ->get(['u.id', 'u.nama', 'u.group_id', 'g.nama as role']);

        if ($kandidat->count() < 2) {
            $this->markTestSkipped('Butuh dua akun Finance/Admin berbeda untuk memisahkan penyetuju & pembatal.');
        }

        $this->penyetuju = $kandidat[0];
        $this->pembatal  = $kandidat[1];

        $this->pengaju = DB::table('users')->where('status', 1)
            ->whereNotIn('id', [$this->penyetuju->id, $this->pembatal->id])
            ->first(['id', 'nama']) ?? $this->penyetuju;

        $this->costCentre = (string) DB::table('rop_cost_centre')->where('aktif', 1)->value('kode');
        if ($this->costCentre === '') $this->markTestSkipped('Master cost centre kosong.');
    }

    /* ═════════════════════ Perkakas ═════════════════════ */

    private function sesi(object $u): array
    {
        return [
            'isLoggedIn' => true,
            'id'         => $u->id,
            'group_id'   => $u->group_id,
            'group_name' => $u->role,
            'expires_in' => time() + 3600,
            'login_at'   => time(),
            'pa_touch'   => time(),
            'branch_day' => date('Y-m-d'),
        ];
    }

    /** Pengajuan yang SUDAH disetujui oleh $this->penyetuju, siap dibatalkan. */
    private function ropDisetujui(float $nominal = 406240, ?string $status = null): RopRequest
    {
        $rop = RopRequest::create([
            'number'       => 'ROP/' . $this->costCentre . '/UJI/' . substr(uniqid(), -6),
            'requester_id' => $this->pengaju->id,
            'cost_centre'  => $this->costCentre,
            'description'  => 'Uji pembatalan persetujuan — VA kedaluwarsa',
            'amount'       => $nominal,
            'needed_by'    => now()->addDays(2)->toDateString(),
            'status'       => $status ?: RopRequest::STATUS_DISETUJUI,
            'coa_no'       => '6-1000',
            'coa_name'     => 'Beban uji',
            'reviewed_by'  => $this->penyetuju->id,
            'reviewed_at'  => now()->subDays(13),   // jendela urungkan sudah jauh lewat
            'created_at'   => now()->subDays(14),
            'updated_at'   => now()->subDays(13),
        ]);

        // Baris log pertama + log persetujuan, seperti yang ditulis service.
        RopStatusLog::create([
            'rop_request_id' => $rop->id, 'dari_status' => null,
            'ke_status' => RopRequest::STATUS_DIAJUKAN,
            'actor_id' => $this->pengaju->id, 'created_at' => now()->subDays(14),
        ]);
        RopStatusLog::create([
            'rop_request_id' => $rop->id, 'dari_status' => RopRequest::STATUS_DIAJUKAN,
            'ke_status' => RopRequest::STATUS_DISETUJUI,
            'actor_id' => $this->penyetuju->id, 'created_at' => now()->subDays(13),
        ]);

        return $rop;
    }

    /* ═══════════════════════ §Uji 1 ═══════════════════════ */

    public function test_1_finance_yang_bukan_penyetuju_asli_bisa_membatalkan_lewat_layar(): void
    {
        $rop = $this->ropDisetujui();

        $this->withSession($this->sesi($this->pembatal))
            ->post('/finance/rop/' . $rop->id . '/tolak', [
                'alasan' => 'VA sudah kedaluwarsa sebelum sempat dibayar. Silakan ajukan ulang dengan nomor VA yang baru.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(RopRequest::STATUS_DITOLAK, $rop->fresh()->status,
            'Transisi disetujui → ditolak sudah diizinkan TRANSISI; layar harus bisa memintanya.');

        $this->assertNotSame((string) $this->penyetuju->id, (string) $this->pembatal->id,
            'Prasyarat uji: pembatal memang bukan penyetuju aslinya.');
    }

    /* ═══════════════════════ §Uji 2 ═══════════════════════ */

    public function test_2_alasan_kosong_ditolak_validasi_dan_status_tidak_bergerak(): void
    {
        $rop = $this->ropDisetujui();

        $this->withSession($this->sesi($this->pembatal))
            ->post('/finance/rop/' . $rop->id . '/tolak', ['alasan' => ''])
            ->assertSessionHasErrors('alasan');

        $this->assertSame(RopRequest::STATUS_DISETUJUI, $rop->fresh()->status,
            'Alasan wajib adalah pagar kedua — tanpa alasan, tidak ada yang berubah.');
    }

    /* ═══════════════════════ §Uji 3 ═══════════════════════ */

    public function test_3_sesudah_dibatalkan_bisa_diajukan_ulang_pengaju_diberi_tahu_dan_jejaknya_benar(): void
    {
        $rop    = $this->ropDisetujui();
        $alasan = 'VA sudah kedaluwarsa sebelum sempat dibayar.';

        $notifSebelum = AdminNotification::where('type', 'rop_ditolak')
            ->where('user_id', (string) $this->pengaju->id)->count();

        $this->withSession($this->sesi($this->pembatal))
            ->post('/finance/rop/' . $rop->id . '/tolak', ['alasan' => $alasan])
            ->assertSessionHas('success');

        $segar = $rop->fresh();

        $this->assertTrue($segar->bisaDiajukanUlang(),
            'Jalur pemulihannya adalah pengajuan ulang — kalau ini false, Finance membatalkan ke jalan buntu.');
        $this->assertSame($alasan, $segar->reject_reason);

        $log = RopStatusLog::where('rop_request_id', $rop->id)
            ->where('ke_status', RopRequest::STATUS_DITOLAK)->latest('id')->first();

        $this->assertNotNull($log, 'Pembatalan wajib meninggalkan jejak.');
        $this->assertSame(RopRequest::STATUS_DISETUJUI, $log->dari_status,
            'Jejak harus menyebut asalnya `disetujui`, bukan `diajukan` — itu yang membedakan pembatalan dari penolakan biasa.');
        $this->assertSame((string) $this->pembatal->id, (string) $log->actor_id,
            'Yang tercatat harus yang MEMBATALKAN, bukan yang dulu menyetujui.');
        $this->assertSame($alasan, $log->catatan);

        $this->assertSame($notifSebelum + 1,
            AdminNotification::where('type', 'rop_ditolak')
                ->where('user_id', (string) $this->pengaju->id)->count(),
            'Pengaju harus diberi tahu — dia yang harus mengajukan ulang.');
    }

    public function test_3b_pengaju_diberi_tahu_PEMBATALAN_bukan_penolakan(): void
    {
        // Yang terbaca pengaju lebih dulu adalah JUDULNYA. "Pengajuan ditolak"
        // untuk pembatalan 13 hari sesudah ACC itu menyesatkan, meski badan
        // pesannya membawa alasan yang benar — pengajuannya tidak salah,
        // persetujuannya yang ditarik.
        $rop = $this->ropDisetujui();

        $this->withSession($this->sesi($this->pembatal))
            ->post('/finance/rop/' . $rop->id . '/tolak', ['alasan' => 'VA sudah kedaluwarsa.'])
            ->assertSessionHas('success');

        $notif = AdminNotification::where('user_id', (string) $this->pengaju->id)
            ->where('type', 'rop_ditolak')->latest('id')->first();

        $this->assertNotNull($notif);
        $this->assertSame('Persetujuan dibatalkan', $notif->title);
        $this->assertStringContainsString('ditarik kembali', $notif->body);
        $this->assertStringContainsString('ajukan ulang', $notif->body,
            'Pengaju harus tahu langkah berikutnya, bukan cuma bahwa sesuatu gagal.');
    }

    public function test_3c_penolakan_biasa_kata_kata_lamanya_TIDAK_berubah(): void
    {
        // Kendali: yang dipakai sehari-hari tidak boleh ikut bergeser.
        $rop = $this->ropDisetujui(150000, RopRequest::STATUS_DIAJUKAN);

        $this->withSession($this->sesi($this->pembatal))
            ->post('/finance/rop/' . $rop->id . '/tolak', ['alasan' => 'Lampiran belum ada.'])
            ->assertSessionHas('success');

        $notif = AdminNotification::where('user_id', (string) $this->pengaju->id)
            ->where('type', 'rop_ditolak')->latest('id')->first();

        $this->assertSame('Pengajuan ditolak', $notif->title);
        $this->assertStringContainsString($rop->number . ' ditolak. Alasan: Lampiran belum ada.', $notif->body);
        $this->assertStringNotContainsString('ajukan ulang', $notif->body);
    }

    /* ═══════════════════════ §Uji 4 ═══════════════════════ */

    public function test_4_yang_sudah_dibayar_atau_selesai_tidak_bisa_dibatalkan(): void
    {
        foreach ([RopRequest::STATUS_DIBAYAR, RopRequest::STATUS_SELESAI] as $status) {
            $rop = $this->ropDisetujui(195050, $status);

            $this->withSession($this->sesi($this->pembatal))
                ->post('/finance/rop/' . $rop->id . '/tolak', ['alasan' => 'coba-coba'])
                ->assertSessionHas('error');

            $this->assertSame($status, $rop->fresh()->status,
                'TRANSISI tidak mengizinkan keluar dari ' . $status . ' ke ditolak.');
        }
    }

    public function test_4b_tombol_batalkan_hanya_dirender_di_kaki_cabang_disetujui(): void
    {
        $html = $this->withSession($this->sesi($this->pembatal))
            ->get('/finance/rop')->assertOk()->getContent();

        // Tombol ada di markup, tapi hidup di #ropFootBayar — dan footer itu
        // hanya ditampilkan JS untuk status `disetujui`. Yang diuji di sini:
        // tombolnya memang duduk di dalam blok bayar, bukan di blok ACC.
        $this->assertStringContainsString('id="ropBukaBatalAcc"', $html,
            'Pintu pembatalan harus ada di layar antrian.');

        $footBayar = mb_substr($html, (int) mb_strpos($html, 'id="ropFootBayar"'));
        $footBayar = mb_substr($footBayar, 0, (int) mb_strpos($footBayar, '</aside>'));

        $this->assertStringContainsString('ropBukaBatalAcc', $footBayar,
            'Tombolnya harus di dalam #ropFootBayar — kalau di #ropFootAcc, dia muncul di status yang salah.');
        $this->assertStringContainsString('rop-aksi-sekunder', $footBayar,
            'Aksi sekunder, bukan tombol sejajar seukuran dengan "Tandai dibayar".');
    }

    /* ═══════════════════════ §Uji 5 ═══════════════════════ */

    public function test_5_membatalkan_satu_item_tidak_merusak_penandaan_item_lain_di_lembar_bayar(): void
    {
        $a = $this->ropDisetujui(219000);   // yang akan dibatalkan
        $b = $this->ropDisetujui(195050);   // yang tetap dibayar

        $sheetId = DB::table('rop_payment_sheets')->insertGetId([
            'number'      => 'BAYAR/UJI/' . substr(uniqid(), -6),
            'dibuat_oleh' => $this->penyetuju->id,
            'dibuat_at'   => now()->subDays(13),
            'jumlah_item' => 2,
            'total'       => 414050,        // SNAPSHOT — sengaja tidak ikut turun
            'updated_at'  => now()->subDays(13),
        ]);
        foreach ([[$a, 1], [$b, 2]] as [$rop, $urut]) {
            DB::table('rop_payment_sheet_items')->insert([
                'sheet_id' => $sheetId, 'rop_id' => $rop->id,
                'amount' => $rop->amount, 'urutan' => $urut, 'created_at' => now()->subDays(13),
            ]);
        }

        $this->withSession($this->sesi($this->pembatal))
            ->post('/finance/rop/' . $a->id . '/tolak', ['alasan' => 'VA sudah kedaluwarsa sebelum sempat dibayar.'])
            ->assertSessionHas('success');

        // Item yang LAIN tetap bisa ditandai dibayar dari lembar yang sama.
        $this->withSession($this->sesi($this->pembatal))
            ->post('/finance/rop/lembar-bayar/' . $sheetId . '/bayar-banyak', [
                'pilih'   => [$b->id],
                'paid_at' => now()->toDateString(),
            ])
            ->assertSessionHas('success');

        $this->assertSame(RopRequest::STATUS_DITOLAK, $a->fresh()->status);
        $this->assertSame(RopRequest::STATUS_DIBAYAR, $b->fresh()->status,
            'Satu item ditolak tidak boleh mengunci sisa lembar.');

        $this->assertSame('414050.00', (string) DB::table('rop_payment_sheets')->where('id', $sheetId)->value('total'),
            'Total lembar itu kolom BEKU: dokumen yang sudah dikirim ke bank tidak berubah angkanya di belakang.');

        // Dan selisihnya DIJELASKAN di layar — kalau tidak, Rp 219.000 yang
        // hilang terbaca sebagai kesalahan hitung, lalu ditanyakan lewat WhatsApp.
        $html = $this->withSession($this->sesi($this->pembatal))
            ->get('/finance/rop/lembar-bayar/' . $sheetId)->assertOk()->getContent();

        $this->assertStringContainsString('keluar dari lembar ini sesudah dibuat', $html);
        $this->assertStringContainsString($a->number, $html,
            'Item yang dibatalkan disebut nomornya, bukan cuma dihitung.');
    }

    public function test_5d_kiriman_bayar_yang_menyertakan_item_dibatalkan_tidak_membayar_sebagian(): void
    {
        [$sheetId, $a, $b] = $this->lembarDenganDuaItem();

        app(RopService::class)->tolak($a, 'VA kedaluwarsa', (string) $this->pembatal->id);

        // Layar tidak merender checkbox untuk item yang dibatalkan, tapi kiriman
        // apa pun bisa dibuat tangan — atau tab lama dikirim ulang. Yang penting:
        // satu item tak layak menolak SELURUH kiriman, bukan membayar separuhnya.
        $this->withSession($this->sesi($this->pembatal))
            ->post('/finance/rop/lembar-bayar/' . $sheetId . '/bayar-banyak', [
                'pilih'   => [$a->id, $b->id],
                'paid_at' => now()->toDateString(),
            ])
            ->assertSessionHas('error');

        $this->assertSame(RopRequest::STATUS_DITOLAK, $a->fresh()->status);
        $this->assertSame(RopRequest::STATUS_DISETUJUI, $b->fresh()->status,
            'Item sehat tidak boleh ikut tertandai dibayar dari kiriman yang ditolak.');
    }

    /**
     * Keadaan lembar yang dikirim ke drawer.
     *
     * ⛔ TIDAK lewat `GET /finance/rop/{id}` — bukan karena malas, tapi karena
     * jalur itu memanggil `RopCoaCatalog::untukCostCentre()` yang di dalamnya ada
     * `Schema::hasTable('accurate_staging_glaccount')`, dan kueri
     * `information_schema` MEMATIKAN mysqld LOKAL (aman di dev/prod). Terbukti
     * terisolasi 16 Sep: `DB::table('rop_requests')->count()` jalan, lalu
     * `Schema::hasTable` → "server has gone away" seketika. Jadi drawer ROP
     * memang tidak bisa dibuka sama sekali dari mesin lokal.
     *
     * Yang diuji karena itu helpernya langsung. Rendering barisnya ditutup dari
     * sisi layar oleh `tests/js/uji-drawer-rop.cjs` [7].
     */
    private function lembarUntuk(RopRequest $rop): ?array
    {
        $m = new \ReflectionMethod(\App\Http\Controllers\Finance\RopQueueController::class, 'lembarTerakhir');
        $m->setAccessible(true);

        return $m->invoke(app(\App\Http\Controllers\Finance\RopQueueController::class), $rop);
    }

    public function test_5f_drawer_tahu_lembar_bayar_tempat_pengajuan_ini_duduk(): void
    {
        [$sheetId, $a, $b] = $this->lembarDenganDuaItem();

        // Lembar sudah dikirim ke bos dan satu itemnya sudah dibayar — persis
        // keadaan 16 Sep. Orang yang mau membatalkan harus tahu ini SEBELUM
        // menekan, bukan sesudah.
        DB::table('rop_payment_sheets')->where('id', $sheetId)->update([
            'dikirim_at' => now()->subDays(13), 'dikirim_oleh' => $this->penyetuju->id,
        ]);
        app(RopService::class)->tandaiDibayar($b, ['paid_at' => now()], (string) $this->pembatal->id);

        $lembar = $this->lembarUntuk($a->fresh());

        $this->assertNotNull($lembar, 'Drawer harus tahu lembarnya.');
        $this->assertSame(2, $lembar['item']);
        $this->assertSame(1, $lembar['dibayar'],
            'Berapa isi lembar yang uangnya sudah keluar = seberapa jauh lembar ini berjalan.');
        $this->assertNotNull($lembar['dikirim']);

        // Dan ini KETERANGAN, bukan pagar: membatalkan tetap boleh.
        $this->withSession($this->sesi($this->pembatal))
            ->post('/finance/rop/' . $a->id . '/tolak', ['alasan' => 'VA kedaluwarsa'])
            ->assertSessionHas('success');
        $this->assertSame(RopRequest::STATUS_DITOLAK, $a->fresh()->status);
    }

    public function test_5g_pengajuan_di_luar_lembar_mana_pun_tidak_mengarang_keadaan(): void
    {
        $this->assertNull($this->lembarUntuk($this->ropDisetujui()),
            'Tanpa lembar, drawer tidak boleh menampilkan baris keadaan sama sekali.');
    }

    public function test_5h_yang_dipakai_adalah_lembar_TERBARU_bukan_yang_pertama(): void
    {
        // Satu ROP boleh masuk lebih dari satu lembar (UNIQUE-nya per sheet+rop).
        // Yang relevan untuk keputusan hari ini adalah yang terakhir.
        [, $a, ] = $this->lembarDenganDuaItem();

        $lembarBaru = DB::table('rop_payment_sheets')->insertGetId([
            'number'      => 'BAYAR/UJI2/' . substr(uniqid(), -6),
            'dibuat_oleh' => $this->penyetuju->id,
            'dibuat_at'   => now(),
            'jumlah_item' => 1,
            'total'       => 219000,
            'updated_at'  => now(),
        ]);
        DB::table('rop_payment_sheet_items')->insert([
            'sheet_id' => $lembarBaru, 'rop_id' => $a->id,
            'amount' => 219000, 'urutan' => 1, 'created_at' => now(),
        ]);

        $this->assertSame(1, $this->lembarUntuk($a)['item'],
            'Lembar terbaru berisi 1 item; kalau yang terbaca lembar lama, angkanya 2.');
    }

    /* ═══════ Lembar CETAK — dokumen yang menggerakkan uang ═══════ */

    public function test_5b_lembar_cetak_menandai_item_yang_dibatalkan_dan_menyebut_yang_harus_dibayar(): void
    {
        [$sheetId, $a, $b] = $this->lembarDenganDuaItem();

        app(RopService::class)->tolak($a, 'VA kedaluwarsa', (string) $this->pembatal->id);

        $html = $this->withSession($this->sesi($this->pembatal))
            ->get('/finance/rop/lembar-bayar/' . $sheetId . '/cetak')->assertOk()->getContent();

        // Lembar boleh dicetak ulang kapan saja. Tanpa tanda, cetakan ulang
        // tampak persis seperti tagihan yang masih berlaku — dan uangnya keluar.
        $this->assertStringContainsString('DIBATALKAN', $html,
            'Baris yang dibatalkan harus bercap di lembar cetak.');
        $this->assertStringContainsString('YANG HARUS DIBAYAR', $html);
        $this->assertStringContainsString('Rp 195.050', $html,
            'Angka yang harus dibayar ditulis, bukan disuruh hitung sendiri.');

        // TOTAL lama TETAP tercetak — dokumen yang sudah dikirim ke bank tidak
        // boleh berubah angkanya di belakang.
        $this->assertStringContainsString('Rp 414.050', $html);

        // Terbilang itu pengaman kedua atas angka yang benar-benar ditransfer;
        // kalau ia masih membacakan total lama, ia mengesahkan angka yang salah.
        // (Dirender lewat ucfirst, jadi huruf pertamanya besar.)
        $this->assertStringContainsString(ucfirst(terbilang(195050)), $html);
        $this->assertStringNotContainsString(ucfirst(terbilang(414050)), $html,
            'Terbilang tidak boleh lagi menyebut total sebelum pembatalan.');
    }

    public function test_5c_selisih_dihitung_dari_SNAPSHOT_lembar_bukan_nominal_hidup(): void
    {
        [$sheetId, $a, ] = $this->lembarDenganDuaItem();

        app(RopService::class)->tolak($a, 'VA kedaluwarsa', (string) $this->pembatal->id);

        // Nominal ROP digeser SESUDAH lembar dibuat — bukan skenario karangan:
        // layar lembar sudah punya penanda "kini Rp …" justru untuk kasus ini.
        // `lembar->total` dijumlahkan dari snapshot `items.amount`, jadi
        // pengurangnya harus dari sana juga; kalau membaca `rop->amount` yang
        // hidup, selisihnya tidak bertemu dan angka "yang harus dibayar" salah.
        DB::table('rop_requests')->where('id', $a->id)->update(['amount' => 999999]);

        $layar = $this->withSession($this->sesi($this->pembatal))
            ->get('/finance/rop/lembar-bayar/' . $sheetId)->assertOk()->getContent();

        $this->assertStringContainsString('Rp 219.000 tidak jadi dibayar', $layar);
        $this->assertStringContainsString('yang benar-benar dibayar Rp 195.050', $layar);
        $this->assertStringNotContainsString('Rp 780.999', $layar,
            'Kalau aritmetikanya memakai nominal hidup, selisihnya jadi 999.999−219.000.');

        $cetak = $this->withSession($this->sesi($this->pembatal))
            ->get('/finance/rop/lembar-bayar/' . $sheetId . '/cetak')->assertOk()->getContent();

        $this->assertStringContainsString('&minus;Rp 219.000', $cetak);
        $this->assertStringContainsString(ucfirst(terbilang(195050)), $cetak,
            'Terbilang harus ikut snapshot, bukan nominal hidup.');
    }

    public function test_5e_lembar_tanpa_pembatalan_tidak_berubah_sedikit_pun(): void
    {
        // Kendali: jalur normal (yang dipakai tiap hari) tidak boleh kejatuhan
        // baris koreksi, cap, atau terbilang yang bergeser.
        [$sheetId, , ] = $this->lembarDenganDuaItem();

        $layar = $this->withSession($this->sesi($this->pembatal))
            ->get('/finance/rop/lembar-bayar/' . $sheetId)->assertOk()->getContent();
        $cetak = $this->withSession($this->sesi($this->pembatal))
            ->get('/finance/rop/lembar-bayar/' . $sheetId . '/cetak')->assertOk()->getContent();

        $this->assertStringNotContainsString('keluar dari lembar ini sesudah dibuat', $layar);
        // Frasa milik fitur ini saja — kata "dibatalkan" sendirian juga muncul
        // di komentar skrip layout, jadi bukan penanda yang sah.
        $this->assertStringNotContainsString('masih menunggu uang, ', $layar);
        $this->assertStringNotContainsString('DIBATALKAN', $cetak);
        $this->assertStringNotContainsString('YANG HARUS DIBAYAR', $cetak);
        $this->assertStringContainsString(ucfirst(terbilang(414050)), $cetak,
            'Tanpa pembatalan, terbilang tetap membacakan total lembar.');
    }

    /** Lembar dengan dua item disetujui: [sheetId, itemA 219rb, itemB 195.050]. */
    private function lembarDenganDuaItem(): array
    {
        $a = $this->ropDisetujui(219000);
        $b = $this->ropDisetujui(195050);

        $sheetId = DB::table('rop_payment_sheets')->insertGetId([
            'number'      => 'BAYAR/UJI/' . substr(uniqid(), -6),
            'dibuat_oleh' => $this->penyetuju->id,
            'dibuat_at'   => now()->subDays(13),
            'jumlah_item' => 2,
            'total'       => 414050,
            'updated_at'  => now()->subDays(13),
        ]);
        foreach ([[$a, 1], [$b, 2]] as [$rop, $urut]) {
            DB::table('rop_payment_sheet_items')->insert([
                'sheet_id' => $sheetId, 'rop_id' => $rop->id,
                'amount' => $rop->amount, 'urutan' => $urut, 'created_at' => now()->subDays(13),
            ]);
        }

        return [$sheetId, $a, $b];
    }

    /* ═══════ Jalur fetch (yang sebenarnya dipakai layar) ═══════ */

    public function test_7_respons_json_menyebut_status_asal_supaya_layar_tak_menebak(): void
    {
        $batal = $this->ropDisetujui(406240);
        $biasa = $this->ropDisetujui(150000, RopRequest::STATUS_DIAJUKAN);

        // Layar mengirim lewat fetch, bukan submit biasa — inilah jalur yang
        // dipakai sehari-hari, dan `dari` yang menentukan kata & angka mana yang
        // bergeser di sisi layar.
        $this->withSession($this->sesi($this->pembatal))
            ->postJson('/finance/rop/' . $batal->id . '/tolak', ['alasan' => 'VA kedaluwarsa'])
            ->assertOk()
            ->assertJson(['ok' => true, 'dari' => RopRequest::STATUS_DISETUJUI, 'amount' => 406240]);

        $this->withSession($this->sesi($this->pembatal))
            ->postJson('/finance/rop/' . $biasa->id . '/tolak', ['alasan' => 'Lampiran belum ada'])
            ->assertOk()
            ->assertJson(['ok' => true, 'dari' => RopRequest::STATUS_DIAJUKAN]);
    }

    public function test_7b_alasan_kosong_lewat_fetch_memberi_pesan_yang_sesuai_konteks(): void
    {
        $batal = $this->ropDisetujui();
        $biasa = $this->ropDisetujui(150000, RopRequest::STATUS_DIAJUKAN);

        $pesanBatal = $this->withSession($this->sesi($this->pembatal))
            ->postJson('/finance/rop/' . $batal->id . '/tolak', ['alasan' => ''])
            ->assertStatus(422)->json('message');

        $pesanBiasa = $this->withSession($this->sesi($this->pembatal))
            ->postJson('/finance/rop/' . $biasa->id . '/tolak', ['alasan' => ''])
            ->assertStatus(422)->json('message');

        // "Apa yang harus diperbaiki" salah alamat untuk pembatalan: pengajuannya
        // tidak salah, keadaannya yang berubah.
        $this->assertStringContainsString('kenapa persetujuannya ditarik', $pesanBatal);
        $this->assertStringContainsString('apa yang harus diperbaiki', $pesanBiasa);
    }

    public function test_7c_yang_sudah_dibayar_memberi_409_lewat_fetch_bukan_500(): void
    {
        $rop = $this->ropDisetujui(195050, RopRequest::STATUS_DIBAYAR);

        $this->withSession($this->sesi($this->pembatal))
            ->postJson('/finance/rop/' . $rop->id . '/tolak', ['alasan' => 'coba-coba'])
            ->assertStatus(409)
            ->assertJsonStructure(['error']);
    }

    /* ═══════ Pagar yang lahir dari fitur ini ═══════ */

    public function test_6_pembatalan_persetujuan_tidak_bisa_diurungkan_ke_status_yang_salah(): void
    {
        $rop = $this->ropDisetujui();

        // Dibatalkan BARU SAJA — jadi jendela 120 detik urungkanPenolakan masih
        // terbuka, dan reviewed_by sudah jadi si pembatal. Tanpa pagar,
        // "Urungkan" akan memulihkannya ke `diajukan`: item keluar dari antrian
        // bayar dan minta di-ACC ulang, padahal ACC-nya tidak pernah dicabut.
        app(RopService::class)->tolak($rop, 'VA kedaluwarsa', (string) $this->pembatal->id);

        $this->withSession($this->sesi($this->pembatal))
            ->post('/finance/rop/' . $rop->id . '/urungkan')
            ->assertSessionHas('error');

        $this->assertSame(RopRequest::STATUS_DITOLAK, $rop->fresh()->status,
            'Lebih baik menolak urungkan daripada memulihkan ke status yang salah.');
    }

    public function test_6b_penolakan_biasa_tetap_bisa_diurungkan(): void
    {
        // Kendali negatif: pagar di atas tidak boleh ikut mematikan jalur urung
        // penolakan biasa yang sudah dipakai sehari-hari.
        $rop = $this->ropDisetujui(150000, RopRequest::STATUS_DIAJUKAN);

        app(RopService::class)->tolak($rop, 'Lampiran belum ada', (string) $this->pembatal->id);
        $this->assertSame(RopRequest::STATUS_DITOLAK, $rop->fresh()->status);

        $this->withSession($this->sesi($this->pembatal))
            ->post('/finance/rop/' . $rop->id . '/urungkan')
            ->assertSessionHas('success');

        $this->assertSame(RopRequest::STATUS_DIAJUKAN, $rop->fresh()->status,
            'Penolakan dari `diajukan` memang pulihnya ke `diajukan`.');
    }
}
