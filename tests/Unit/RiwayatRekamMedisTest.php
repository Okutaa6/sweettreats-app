<?php

namespace Tests\Unit;

use App\Http\Controllers\Crud\CustomerController;
use App\Models\RawCustomer;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Timeline Riwayat pasien — kolom rekam medis Omnicare yang dirender.
 *
 * Murni unit: buildRiwayat() cuma memformat koleksi yang SUDAH ditarik
 * controller (nol kueri tambahan), jadi relasi `reservations` di-set manual
 * dan tak ada DB yang disentuh.
 *
 *   php artisan test --filter=RiwayatRekamMedisTest
 */
class RiwayatRekamMedisTest extends TestCase
{
    /** Panggil buildRiwayat() yang private tanpa lewat HTTP. */
    private function riwayat(array $records): array
    {
        $customer = new RawCustomer(['name' => 'Pasien Uji']);
        $customer->setRelation('reservations', new Collection());

        $m = new \ReflectionMethod(CustomerController::class, 'buildRiwayat');
        $m->setAccessible(true);

        return $m->invoke(new CustomerController(), $customer, new Collection(array_map(
            fn (array $r) => (object) ($r + [
                'date' => '2026-09-01', 'time_in' => '10:00:00', 'booking_code' => 'BK-1',
                'record_id' => 'R-1', 'doctor' => 'Andi', 'is_new_patient' => 0,
                'chief_complaint' => null, 'diagnose_note' => null, 'diagnoses' => null,
                'procedures' => null, 'subjective' => null, 'objective' => null,
                'prescription' => null, 'treatment_plan_note' => null,
            ]),
            $records
        )));
    }

    private function rekam(array $records): object
    {
        return $this->riwayat($records)['items'][0];
    }

    /**
     * ⭐ Inti Bagian B. `prescription` yang isinya HANYA pemisah ";" harus
     * terbaca KOSONG. Di data lokal 45.173 dari 48.464 rekam "terisi" dengan
     * bentuk seperti ini — kalau tes "kolomnya tidak kosong" yang dipakai,
     * hampir setiap kunjungan akan menumbuhkan blok RESEP yang hampa.
     */
    public function test_resep_berisi_pemisah_saja_dianggap_kosong(): void
    {
        foreach ([';', ';   ;', '  ', ';;;', "\n;\n"] as $palsu) {
            $this->assertSame(
                [],
                $this->rekam([['prescription' => $palsu]])->resep,
                'Resep "' . addcslashes($palsu, "\n") . '" seharusnya tidak menghasilkan item.'
            );
        }
    }

    /** Resep yang sungguhan dipecah per item. */
    public function test_resep_nyata_dipecah_per_item(): void
    {
        $item = $this->rekam([['prescription' => 'Amoxicillin 500mg x10 ; Asam Mefenamat 500mg x10']]);

        $this->assertSame(['Amoxicillin 500mg x10', 'Asam Mefenamat 500mg x10'], $item->resep);
    }

    /**
     * Sumbernya kerap mengulang item yang sama berkali-kali dalam satu baris
     * ("Amoxicillin 500mg x10" delapan kali) — jangan dirender delapan kali.
     */
    public function test_resep_kembar_dibuang(): void
    {
        $berulang = implode(' ; ', array_fill(0, 8, 'Amoxicillin 500mg  x10'));

        $this->assertSame(['Amoxicillin 500mg x10'], $this->rekam([['prescription' => $berulang]])->resep);
    }

    /** treatment_plan_note dibaca terpisah dari diagnosa, dan <br>-nya dibersihkan. */
    public function test_rencana_perawatan_terbaca_terpisah_dari_diagnosa(): void
    {
        $item = $this->rekam([[
            'diagnose_note'       => 'Karies profunda',
            'treatment_plan_note' => 'TERAPI :<br>SCALLING KELAS 3 RA RB',
        ]]);

        $this->assertSame('Karies profunda', $item->diagnosa);
        $this->assertSame('TERAPI : SCALLING KELAS 3 RA RB', $item->rencana);
    }

    /**
     * Rencana ada TANPA diagnosa adalah kasus yang lazim (41,3% vs 19,4%).
     * Kalau blok "Diagnosa & Rencana" digantung pada diagnosa saja, isi yang
     * paling berguna untuk klinik ortho justru yang paling sering hilang.
     */
    public function test_rencana_tanpa_diagnosa_tetap_terbaca_dan_masuk_ringkasan(): void
    {
        $item = $this->rekam([['treatment_plan_note' => 'Kontrol behel 3 minggu lagi']]);

        $this->assertNull($item->diagnosa);
        $this->assertSame('Kontrol behel 3 minggu lagi', $item->rencana);
        $this->assertStringContainsString('Kontrol behel', $item->summary);
    }

    /** SOAP: subjective ikut terbaca, di samping objective yang sudah ada. */
    public function test_subjektif_dan_objektif_terbaca(): void
    {
        $item = $this->rekam([['subjective' => 'Nyeri saat mengunyah', 'objective' => 'Gigi 36 goyang']]);

        $this->assertSame('Nyeri saat mengunyah', $item->subjektif);
        $this->assertSame('Gigi 36 goyang', $item->objektif);
    }

    /**
     * ⛔ Kolom `doctor` di rekam medis berisi NAMA (di `doctor_list` berisi
     * ANGKA). Ia hanya boleh ditampilkan apa adanya — mencocokkan dokter lewat
     * nama sudah pernah menyasarkan kita ke rekam dokter lain.
     */
    public function test_dokter_ditampilkan_sebagai_teks_apa_adanya(): void
    {
        $this->assertSame('drg. Andi', $this->rekam([['doctor' => 'Andi']])->doctor);
        $this->assertSame('drg. Budi', $this->rekam([['doctor' => 'drg. Budi']])->doctor);
        $this->assertSame('Dokter tidak tercatat', $this->rekam([['doctor' => '']])->doctor);
    }

    /** Pasien tanpa tautan Omnicare sama sekali → halaman tetap normal. */
    public function test_pasien_tanpa_rekam_medis_tidak_error(): void
    {
        $hasil = $this->riwayat([]);

        $this->assertSame([], $hasil['items']);
        $this->assertSame(0, $hasil['rekam']);
    }

    /** Kunjungan tanpa catatan klinis apa pun tetap punya ringkasan terbaca. */
    public function test_kunjungan_tanpa_catatan_klinis_punya_ringkasan(): void
    {
        $item = $this->rekam([['prescription' => ';']]);

        $this->assertSame([], $item->resep);
        $this->assertSame([], $item->tindakan);
        $this->assertSame('Kunjungan tanpa catatan klinis', $item->summary);
    }
}
