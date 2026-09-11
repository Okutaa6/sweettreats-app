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

    /**
     * treatment_plan_note dibaca terpisah dari diagnosa, dan `<br>`-nya jadi
     * GANTI BARIS — bukan spasi.
     *
     * ⚠️ Assertion ini dulu berbunyi 'TERAPI : SCALLING KELAS 3 RA RB' (satu
     * spasi). Itu mengunci perilaku yang SALAH: `$clean` lama meleburkan tiap
     * baris klinis jadi satu paragraf. Diperbaiki 2026-09-11.
     */
    public function test_rencana_perawatan_terbaca_terpisah_dari_diagnosa(): void
    {
        $item = $this->rekam([[
            'diagnose_note'       => 'Karies profunda',
            'treatment_plan_note' => 'TERAPI :<br>SCALLING KELAS 3 RA RB',
        ]]);

        $this->assertSame('Karies profunda', $item->diagnosa);
        $this->assertSame("TERAPI :\nSCALLING KELAS 3 RA RB", $item->rencana);
    }

    // ── Teks klinis: ganti-baris dipertahankan ──────────────────────────────

    /** ⭐ `a<br>b` = DUA baris. Terukur 4.303 kolom (26,7%) memakai `<br>`. */
    public function test_br_jadi_ganti_baris_bukan_spasi(): void
    {
        $asli = 'ligature gigi 24-23<br>reposisi button ke palatal 24-27 <br/>bikin hook di gigi 44<br />edukasi elastik';

        $item = $this->rekam([['treatment_plan_note' => $asli]]);

        $this->assertSame([
            'ligature gigi 24-23',
            'reposisi button ke palatal 24-27',
            'bikin hook di gigi 44',
            'edukasi elastik',
        ], explode("\n", $item->rencana));
    }

    /**
     * ⛔ Cacat paling berbahaya: `strip_tags` membuang `</div><div>` tanpa
     * menyisipkan apa pun, jadi dua kata MENEMPEL — itu mengubah ARTI.
     * "…di distal 24" + "ra ganti niti" pernah jadi "distal 24ra".
     */
    public function test_penutup_div_tidak_menempelkan_kata(): void
    {
        $item = $this->rekam([[
            'diagnose_note' => '<div>ligature 22-11, ocs diganti baru di distal 24</div><div>ra ganti niti 0.16</div>',
        ]]);

        $this->assertStringNotContainsString('24ra', $item->diagnosa);
        $this->assertSame([
            'ligature 22-11, ocs diganti baru di distal 24',
            'ra ganti niti 0.16',
        ], explode("\n", $item->diagnosa));
    }

    /**
     * `&nbsp;` jadi U+00A0 setelah decode, dan `trim()` PHP TIDAK membuangnya —
     * 855 kolom (5,3%) menyisakan spasi tak terlihat di ujung.
     */
    public function test_nbsp_tidak_menyisakan_spasi_tak_terlihat(): void
    {
        $item = $this->rekam([['chief_complaint' => '&nbsp;gigi&nbsp;&nbsp;ngilu&nbsp;']]);

        $this->assertSame('gigi ngilu', $item->keluhan);
        $this->assertStringNotContainsString("\u{00A0}", $item->keluhan);
    }

    /** Tag INLINE tidak boleh memotong kalimat yang utuh (prod: i/span/ul). */
    public function test_tag_inline_tidak_memecah_baris(): void
    {
        $item = $this->rekam([['objective' => 'gigi <i>36</i> goyang <span>derajat 2</span>']]);

        $this->assertSame('gigi 36 goyang derajat 2', $item->objektif);
    }

    /** Rekam berbaris tunggal tampil PERSIS seperti sebelumnya. */
    public function test_baris_tunggal_tidak_berubah(): void
    {
        $item = $this->rekam([['chief_complaint' => 'kontrol rutin behel']]);

        $this->assertSame('kontrol rutin behel', $item->keluhan);
        $this->assertStringNotContainsString("\n", $item->keluhan);
    }

    /** Baris kosong/tag kosong tak boleh jadi baris hampa atau label kosong. */
    public function test_baris_kosong_dibuang(): void
    {
        $item = $this->rekam([['subjective' => '<br><br>nyeri<br>&nbsp;<br><div></div>']]);

        $this->assertSame('nyeri', $item->subjektif);
        $this->assertNull($this->rekam([['subjective' => '<br>&nbsp;<div></div>']])->subjektif);
    }

    /**
     * ⛔ Teks yang BERAKHIR "..." datang apa adanya dari Omnicare (diverifikasi
     * sampai `raw_payload`). Jangan pernah "dipulihkan" atau dipangkas.
     */
    public function test_titik_titik_dari_omnicare_dibiarkan_utuh(): void
    {
        $item = $this->rekam([['treatment_plan_note' => 'posisi kawat niti 0,12 up/low...']]);

        $this->assertSame('posisi kawat niti 0,12 up/low...', $item->rencana);
    }

    /** Ringkasan kartu tetap SATU baris walau nilainya berbaris banyak. */
    public function test_ringkasan_tetap_satu_baris(): void
    {
        $item = $this->rekam([['chief_complaint' => 'gigi ngilu<br>gusi bengkak']]);

        $this->assertStringNotContainsString("\n", $item->summary);
        $this->assertStringContainsString('gigi ngilu · gusi bengkak', $item->summary);
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
