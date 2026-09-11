<?php

namespace Tests\Feature;

use App\Http\Controllers\Crud\ReservationController;
use App\Models\AuditLog;
use App\Models\RawCusReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Jejak reservasi: created_by + alasan batal + riwayat perubahan.
 *
 * Sama seperti ReservationContactTest: test DB = MySQL asli (RefreshDatabase
 * di-comment di phpunit.xml), jadi di-skip secara default supaya suite tidak
 * merusak data dev. Jalankan manual:
 *
 *   RUN_DB_TESTS=1 php artisan test --filter=JejakReservasiTest
 *
 * PRASYARAT: kolom created_by/created_via/cancel_reason/cancel_reason_note
 * sudah ada (database/sql/2026_09_11_reservasi_jejak.sql).
 *
 * Controller dipanggil LANGSUNG, bukan lewat HTTP: gerbang RBAC/menu tidak
 * sedang diuji di sini, dan melewatinya membuat test ini tidak ikut tumbang
 * saat grant menu berbeda antara dev dan prod.
 */
class JejakReservasiTest extends TestCase
{
    private ReservationController $ctrl;
    private string $tanda = 'UJI-JEJAK';   // penanda supaya pembersihan tak menyentuh data lain
    private array $bersihkan = [];

    // raw_cus_reservation punya FK ke raw_branch/raw_customer/raw_doctor, jadi
    // fixture harus menumpang baris yang MEMANG ada. Tidak ada yang dibuat dan
    // tidak ada yang diubah di tiga tabel itu — cuma dibaca id-nya.
    private ?string $branchId = null;
    private ?string $customerId = null;
    private ?string $doctorId = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! env('RUN_DB_TESTS')) {
            $this->markTestSkipped('Set RUN_DB_TESTS=1 untuk menjalankan test DB ini.');
        }

        $this->branchId   = \DB::table('raw_branch')->value('id');
        // Sengaja pasien yang cabangnya SUDAH terisi: update() mengisi cabang
        // pasien yatim, dan test tidak boleh mengubah data yang bukan miliknya.
        $this->customerId = \DB::table('raw_customer')->whereNotNull('raw_branch_id')->value('id')
            ?: \DB::table('raw_customer')->value('id');
        $this->doctorId   = \DB::table('raw_doctor')->value('id');
        if (! $this->branchId || ! $this->customerId || ! $this->doctorId) {
            $this->markTestSkipped('Butuh minimal 1 cabang, 1 pasien, dan 1 dokter di DB.');
        }

        $this->ctrl = new ReservationController();
        session(['id' => 'uji-user-1', 'name' => 'Penguji']);
    }

    protected function tearDown(): void
    {
        foreach ($this->bersihkan as $id) {
            AuditLog::where('model_type', 'RawCusReservation')->where('model_id', $id)->delete();
            RawCusReservation::where('id', $id)->delete();
        }
        $this->bersihkan = [];
        parent::tearDown();
    }

    private function buatReservasi(array $atribut = []): RawCusReservation
    {
        $r = RawCusReservation::create($atribut + [
            'id'              => (string) Str::uuid(),
            'raw_branch_id'   => $this->branchId,
            'raw_customer_id' => $this->customerId,
            'raw_doctor_id'   => $this->doctorId,
            'date'            => '2026-09-20',
            'start_hour'      => '10:00:00',
            'end_hour'        => '10:30:00',
            'status'          => RawCusReservation::STATUS_CONFIRMED,
            'keterangan'      => $this->tanda,
            'created_at'      => now(),
        ]);
        $this->bersihkan[] = $r->id;

        return $r;
    }

    /** Pasang request yang dibaca helper request() di dalam controller. */
    private function minta(array $data): void
    {
        $req = Request::create('/rawreservation/uji/inline', 'PATCH', $data);
        $req->setLaravelSession(app('session.store'));
        app()->instance('request', $req);
    }

    private function inline(string $id, array $data)
    {
        $this->minta($data);

        return $this->ctrl->updateInline($id);
    }

    private function jejakUntuk(string $id)
    {
        return AuditLog::where('model_type', 'RawCusReservation')->where('model_id', $id)->get();
    }

    // ── Alasan pembatalan ───────────────────────────────────────────────────

    /** ⭐ Kriteria terima: batal TANPA alasan ditolak — dari jalur inline. */
    public function test_inline_batal_tanpa_alasan_ditolak(): void
    {
        $r = $this->buatReservasi();

        $res = $this->inline($r->id, ['field' => 'status', 'value' => RawCusReservation::STATUS_CANCELLED]);

        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame(
            RawCusReservation::STATUS_CONFIRMED,
            $r->fresh()->status,
            'Status TIDAK boleh berubah saat alasannya ditolak.'
        );
        $this->assertCount(0, $this->jejakUntuk($r->id), 'Penolakan bukan perubahan — jangan dicatat.');
    }

    /** Alasan di luar daftar ditolak (tak bisa mengarang slug dari luar layar). */
    public function test_inline_alasan_tak_dikenal_ditolak(): void
    {
        $r = $this->buatReservasi();

        $res = $this->inline($r->id, [
            'field' => 'status', 'value' => RawCusReservation::STATUS_CANCELLED,
            'cancel_reason' => 'asal-ketik',
        ]);

        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame(RawCusReservation::STATUS_CONFIRMED, $r->fresh()->status);
    }

    /** "Lainnya" tanpa penjelasan = sama tak terpakainya dengan tanpa alasan. */
    public function test_inline_lainnya_wajib_disertai_catatan(): void
    {
        $r = $this->buatReservasi();

        $res = $this->inline($r->id, [
            'field' => 'status', 'value' => RawCusReservation::STATUS_CANCELLED,
            'cancel_reason' => RawCusReservation::CANCEL_LAINNYA, 'cancel_reason_note' => '   ',
        ]);

        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame(RawCusReservation::STATUS_CONFIRMED, $r->fresh()->status);
    }

    /** Batal DENGAN alasan tersimpan — dan meninggalkan satu baris riwayat. */
    public function test_inline_batal_dengan_alasan_tersimpan_dan_tercatat(): void
    {
        $r = $this->buatReservasi();

        $res = $this->inline($r->id, [
            'field' => 'status', 'value' => RawCusReservation::STATUS_CANCELLED,
            'cancel_reason' => 'pasien_sakit', 'cancel_reason_note' => 'demam',
        ]);

        $this->assertSame(200, $res->getStatusCode());

        $segar = $r->fresh();
        $this->assertSame(RawCusReservation::STATUS_CANCELLED, $segar->status);
        $this->assertSame('pasien_sakit', $segar->cancel_reason);
        $this->assertSame('demam', $segar->cancel_reason_note);

        $jejak = $this->jejakUntuk($r->id);
        $this->assertCount(1, $jejak);
        $this->assertSame(RawCusReservation::STATUS_CONFIRMED, $jejak[0]->old_values['status']);
        $this->assertSame(RawCusReservation::STATUS_CANCELLED, $jejak[0]->new_values['status']);
        $this->assertStringContainsString('Pasien sakit', $jejak[0]->description);
    }

    /** Payload lengkap untuk update() (form Edit mewajibkan semuanya). */
    private function editForm(RawCusReservation $r, array $ubah = []): array
    {
        return $ubah + [
            'raw_branch_id'   => $r->raw_branch_id,
            'raw_customer_id' => $r->raw_customer_id,
            'raw_doctor_id'   => $r->raw_doctor_id,
            'date'            => '2026-09-20',
            'start_hour'      => '10:00:00',
            'end_hour'        => '10:30:00',
            'status'          => $r->status,
        ];
    }

    private function simpanForm(RawCusReservation $r, array $ubah = [], bool $ajax = false)
    {
        $req = Request::create(
            '/rawreservation/' . $r->id, 'PUT', $this->editForm($r, $ubah),
            [], [], $ajax ? ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : []
        );
        $req->setLaravelSession(app('session.store'));
        app()->instance('request', $req);

        return $this->ctrl->update($r->id);
    }

    /**
     * ⭐ Kriteria terima: pagar alasan berlaku di form Edit JUGA, bukan cuma
     * inline. Memagari satu layar saja sama dengan tidak memagari — 652
     * pembatalan tanpa alasan lahir dari lubang seperti itu.
     *
     * Form Edit dipakai lewat DUA bentuk response: drawer mengirim AJAX (422
     * JSON, dibaca rrFormShowErrors) dan halaman standalone mengirim biasa
     * (302 back + errors). Dua-duanya harus MENOLAK — yang diuji di sini bukan
     * bentuk responsenya, tapi bahwa statusnya tidak jadi berubah.
     */
    public function test_form_edit_batal_tanpa_alasan_ditolak_ajax(): void
    {
        $r = $this->buatReservasi();

        $res = $this->simpanForm($r, ['status' => RawCusReservation::STATUS_CANCELLED], true);

        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame(RawCusReservation::STATUS_CONFIRMED, $r->fresh()->status);
        $this->assertCount(0, $this->jejakUntuk($r->id));
    }

    public function test_form_edit_batal_tanpa_alasan_ditolak_halaman_biasa(): void
    {
        $r = $this->buatReservasi();

        $res = $this->simpanForm($r, ['status' => RawCusReservation::STATUS_CANCELLED]);

        $this->assertSame(302, $res->getStatusCode(), 'Jalur non-AJAX memantul balik dengan errors.');
        $this->assertSame(RawCusReservation::STATUS_CONFIRMED, $r->fresh()->status);
    }

    /** Form Edit dengan alasan → tersimpan + satu baris riwayat. */
    public function test_form_edit_batal_dengan_alasan_tersimpan(): void
    {
        $r = $this->buatReservasi();

        $this->simpanForm($r, [
            'status'             => RawCusReservation::STATUS_CANCELLED,
            'cancel_reason'      => 'reschedule',
            'cancel_reason_note' => 'minta pindah minggu depan',
        ]);

        $segar = $r->fresh();
        $this->assertSame(RawCusReservation::STATUS_CANCELLED, $segar->status);
        $this->assertSame('reschedule', $segar->cancel_reason);
        $this->assertSame('minta pindah minggu depan', $segar->cancel_reason_note);

        $jejak = $this->jejakUntuk($r->id);
        $this->assertCount(1, $jejak);
        $this->assertSame(RawCusReservation::STATUS_CANCELLED, $jejak[0]->new_values['status']);
    }

    /**
     * Menyunting reservasi yang MEMANG sudah batal tidak dipaksa beralasan —
     * 652 baris lama tak mungkin diisi mundur, dan memaksanya cuma memblokir
     * pekerjaan lain di baris yang sama.
     */
    public function test_reservasi_yang_sudah_batal_tidak_dipaksa_beralasan_lagi(): void
    {
        $r = $this->buatReservasi(['status' => RawCusReservation::STATUS_CANCELLED]);

        $res = $this->inline($r->id, ['field' => 'status', 'value' => RawCusReservation::STATUS_CANCELLED]);

        $this->assertSame(200, $res->getStatusCode());
    }

    /**
     * Keluar dari "batal" MENGOSONGKAN alasannya. Alasan batal yang menempel
     * di reservasi yang sudah aktif lagi adalah kebohongan diam-diam.
     */
    public function test_keluar_dari_batal_mengosongkan_alasan(): void
    {
        $r = $this->buatReservasi([
            'status' => RawCusReservation::STATUS_CANCELLED,
            'cancel_reason' => 'pasien_sakit', 'cancel_reason_note' => 'demam',
        ]);

        $this->inline($r->id, ['field' => 'status', 'value' => RawCusReservation::STATUS_CONFIRMED]);

        $segar = $r->fresh();
        $this->assertNull($segar->cancel_reason);
        $this->assertNull($segar->cancel_reason_note);
    }

    // ── Riwayat perubahan ───────────────────────────────────────────────────

    /**
     * ⭐ Kriteria terima: menggeser tanggal/jam/dokter meninggalkan SATU baris
     * audit_log berisi nilai lama dan baru.
     */
    public function test_geser_jadwal_meninggalkan_satu_baris_riwayat(): void
    {
        $r = $this->buatReservasi();

        $m = new \ReflectionMethod(ReservationController::class, 'catatJejak');
        $m->setAccessible(true);

        // Dokter kedua kalau ada; kalau klinik uji cuma punya satu, cukup uji
        // pergeseran tanggal & jam.
        $doctor2 = \DB::table('raw_doctor')->where('id', '<>', $this->doctorId)->value('id');

        $sebelum = $r->getOriginal();
        $r->update(array_filter([
            'date'          => '2026-09-25',
            'start_hour'    => '14:00:00',
            'raw_doctor_id' => $doctor2,
        ]));
        $this->minta([]);
        $m->invoke($this->ctrl, $r, $sebelum);

        $jejak = $this->jejakUntuk($r->id);
        $this->assertCount(1, $jejak, 'Satu penyuntingan = satu baris, bukan satu baris per kolom.');

        $this->assertSame('2026-09-20 00:00:00', $jejak[0]->old_values['date']);
        $this->assertSame('2026-09-25 00:00:00', $jejak[0]->new_values['date']);
        $this->assertSame('10:00:00', $jejak[0]->old_values['start_hour']);
        $this->assertSame('14:00:00', $jejak[0]->new_values['start_hour']);
        if ($doctor2) {
            $this->assertSame($doctor2, $jejak[0]->new_values['raw_doctor_id']);
        }
        $this->assertSame('uji-user-1', $jejak[0]->user_id, 'Riwayat harus tahu SIAPA yang menggeser.');
    }

    /** Penyuntingan yang tak menggeser jadwal/status bukan jejak — jangan dicatat. */
    public function test_perubahan_tanpa_geser_jadwal_tidak_dicatat(): void
    {
        $r = $this->buatReservasi();

        $res = $this->inline($r->id, ['field' => 'keterangan', 'value' => $this->tanda . ' catatan baru']);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertCount(0, $this->jejakUntuk($r->id));
    }

    // ── created_by ──────────────────────────────────────────────────────────

    /**
     * Reservasi lama (created_by NULL) tetap bisa dibaca & disunting — kolom
     * baru tidak boleh membuat 6.400 baris warisan meledak.
     */
    public function test_reservasi_lama_tanpa_created_by_tetap_bisa_disunting(): void
    {
        $r = $this->buatReservasi(['created_by' => null, 'created_via' => null]);

        $res = $this->inline($r->id, ['field' => 'status', 'value' => RawCusReservation::STATUS_COMPLETED]);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(RawCusReservation::STATUS_COMPLETED, $r->fresh()->status);
        $this->assertNull($r->fresh()->created_by);
    }

    /**
     * ⭐ Kriteria terima: reservasi baru dari jalur admin (store()) punya
     * created_by yang benar. Ini jalur utama FO — kalau cuma $fillable yang
     * ditambah tanpa controller-nya mengisi, kolomnya diam-diam selalu NULL.
     */
    public function test_store_mengisi_created_by_dan_via_admin(): void
    {
        $req = Request::create('/rawreservation/new', 'POST', [
            'raw_branch_id'   => $this->branchId,
            'raw_customer_id' => $this->customerId,
            'raw_doctor_id'   => $this->doctorId,
            'date'            => '2027-01-14',   // jauh di depan: tak menabrak jadwal nyata
            'start_hour'      => '21:00:00',
            'end_hour'        => '21:30:00',
            'keterangan'      => $this->tanda,
        ], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $req->setLaravelSession(app('session.store'));
        app()->instance('request', $req);

        $res  = $this->ctrl->store();
        $body = json_decode($res->getContent(), true);

        $this->assertTrue($body['ok'] ?? false, 'store() gagal: ' . $res->getContent());
        $this->bersihkan[] = $body['id'];

        $baru = RawCusReservation::find($body['id']);
        $this->assertSame('uji-user-1', $baru->created_by, 'Pembuat reservasi harus tercatat.');
        $this->assertSame(RawCusReservation::VIA_ADMIN, $baru->created_via);
    }

    /** Kolom jejak benar-benar tersimpan (bukan cuma ada di $fillable). */
    public function test_created_by_dan_created_via_tersimpan(): void
    {
        $r = $this->buatReservasi([
            'created_by'  => 'uji-user-1',
            'created_via' => RawCusReservation::VIA_ADMIN,
        ]);

        $segar = $r->fresh();
        $this->assertSame('uji-user-1', $segar->created_by);
        $this->assertSame('admin', $segar->created_via);
    }

    /**
     * Form publik TIDAK punya user — created_by NULL disengaja, dan
     * created_via-lah yang membedakannya dari baris warisan.
     */
    public function test_form_publik_created_by_null_tapi_via_terisi(): void
    {
        $r = $this->buatReservasi([
            'created_by'  => null,
            'created_via' => RawCusReservation::VIA_LANDING,
        ]);

        $segar = $r->fresh();
        $this->assertNull($segar->created_by);
        $this->assertSame('landing', $segar->created_via);
    }
}
