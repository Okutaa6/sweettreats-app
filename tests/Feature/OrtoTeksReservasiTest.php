<?php

namespace Tests\Feature;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Services\Ortho\OrtBranches;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tracking Ortho — tab "Tanggal reservasi" hanya boleh mengatakan yang ia tahu.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * YANG MELAHIRKAN BERKAS INI
 * ────────────────────────────────────────────────────────────────────────────
 * Reza menunjukkan panel baris pasien: kontrol #10 berbunyi "Datang langsung
 * 27 Jun '26 tanpa reservasi" dengan chip HIJAU, sementara #8 dan #9 berbunyi
 * "Tidak ada reservasi". Pertanyaannya: tanggal reservasi itu diambil dari mana?
 *
 * Jawabannya membongkar dua kesalahan sekaligus:
 *
 * 1. Reservasi TIDAK berasal dari Omnicare. Ia hidup di `raw_cus_reservation`,
 *    tabel Sweet Treats sendiri, dan hampir seluruhnya belum pernah dijodohkan
 *    ke `ort_control` (0 dari 35.317 di prod). Jadi "Tidak ada reservasi"
 *    sebenarnya berarti "belum pernah dicek".
 *
 * 2. `unscheduled = 1` artinya "kunjungan ini tidak cocok dengan baris jadwal
 *    mana pun di sistem kita" — BUKAN "pasien datang tanpa memesan". Teksnya
 *    mengaku tahu hal yang tidak diketahui sistem, dan chip hijaunya membuatnya
 *    terbaca seperti fakta terverifikasi. Klaim yang sama muncul di EMPAT
 *    tempat: panel baris, badge tabel, tooltip drawer, dan COMMENT kolom di DB.
 *
 * Plus satu bug urutan: cabang `unscheduled` diperiksa SEBELUM `rsv_date`,
 * jadi baris walk-in yang berhasil ditautkan `ort:tautkan-reservasi` tetap
 * berbunyi "tanpa reservasi" dan menyembunyikan tanggal yang baru diisi.
 * Tabel utama dan drawer detail memakai urutan yang benar — dua layar berbeda
 * pendapat tentang baris yang sama.
 *
 * ⚠ Teks yang menjelaskan SEBAB adalah teks yang paling cepat basi. Berkas ini
 *   menahan dua sisi: yang benar harus tampil, dan klaim yang tidak bisa
 *   dibuktikan tidak boleh tampil lagi.
 *
 * Tanggal 2021 dipakai di seluruh berkas supaya tidak mungkin bertabrakan
 * dengan data peraga (yang memakai hari ini − 90..700 hari).
 */
class OrtoTeksReservasiTest extends TestCase
{
    use DatabaseTransactions;

    private const TANDA = 'UJI-RSVTEKS';

    private string $placeId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ort_case')) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_schema.sql dulu.');
        }
        if (! DB::table('access_path')->where('link', 'tracking-ortho')->exists()) {
            $this->markTestSkipped('Jalankan database/sql/2026_09_23_ort_tracking_access.sql dulu.');
        }

        $peta = app(OrtBranches::class)->all();
        if (! $peta) {
            $this->markTestSkipped('Tidak ada cabang ber-omnicare_place_id.');
        }

        $this->placeId = (string) array_key_first($peta);
    }

    // ── Perkakas ────────────────────────────────────────────────────────────

    /** Satu pasien dengan satu kontrol TERBUKA (seq 1), jadi pasti muncul di List. */
    private function pasien(): OrtCase
    {
        $pid = self::TANDA . '-' . Str::random(6);

        $kasus = OrtCase::create([
            'id' => (string) Str::uuid(), 'seed_source' => self::TANDA,
            'omnicare_pid' => $pid, 'patient_name' => 'Uji RsvTeks ' . $pid,
            'owner_place_id' => $this->placeId, 'install_place_id' => $this->placeId,
            'install_date' => Carbon::today()->subDays(400)->toDateString(),
            'status' => OrtCase::STATUS_AKTIF,
            'control_count' => 0, 'visit_count' => 0, 'visit_count_180d' => 0,
        ]);

        OrtControl::create([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => 1,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => Carbon::today()->toDateString(),
            'sch_source' => OrtControl::SCH_MANUAL, 'created_by' => 'uji',
        ]);

        return $kasus;
    }

    /** Kontrol TERTUTUP (sudah hadir) di kasus itu — inilah yang tampil di 4 riwayat panel. */
    private function tertutup(OrtCase $kasus, int $seq, string $visit, array $ubah = []): OrtControl
    {
        return OrtControl::create(array_merge([
            'id' => (string) Str::uuid(), 'ort_case_id' => $kasus->id, 'seq' => $seq,
            'place_id' => $this->placeId, 'is_proposal' => 0,
            'sch_date' => $visit, 'sch_source' => OrtControl::SCH_REKAM_MEDIS,
            'visit_date' => $visit, 'visit_source' => 'rekam_medis',
            'outcome' => OrtControl::OUT_HADIR, 'closed_at' => now(), 'closed_by' => 'uji',
            'created_by' => 'uji',
        ], $ubah));
    }

    private function sesi(): array
    {
        $gid = DB::table('group_path')->where('nama', 'Supervisor')->value('id');
        if (! $gid) {
            $this->markTestSkipped("Role 'Supervisor' tidak ada di DB ini.");
        }

        $node = DB::table('access_path')->where('link', 'tracking-ortho')->first(['id', 'pid']);
        foreach (array_filter([$node->id, $node->pid]) as $accessId) {
            $ada = DB::table('group_access')->where('group_id', $gid)->where('access_id', $accessId)->exists();
            if (! $ada) {
                DB::table('group_access')->insert([
                    'id' => (string) Str::uuid(), 'group_id' => $gid, 'access_id' => $accessId,
                ]);
            }
        }

        return [
            'isLoggedIn' => true, 'id' => 'uji-' . Str::random(8), 'group_id' => $gid,
            'group_name' => 'Supervisor', 'name' => 'Uji Sales', 'raw_branch_id' => null,
            'branch_day' => date('Y-m-d'), 'expires_in' => time() + 3600,
            'login_at' => time(), 'pa_touch' => time(),
        ];
    }

    /**
     * Halaman List — memuat panel baris (_baris_terbuka) untuk tiap pasien.
     *
     * ⚠ `$saring` WAJIB dipakai oleh assertion NEGATIF. Tanpa saringan, halaman
     *   ini memuat seluruh pasien cabang itu — termasuk pasien DB lokal yang
     *   memang punya reservasi — jadi "tombolnya tidak ada" akan merah karena
     *   baris orang lain. Sudah kena sekali.
     */
    private function halaman(array $saring = []): string
    {
        return $this->withSession($this->sesi())
            ->get('/tracking-ortho?' . http_build_query(['utama' => $this->placeId] + $saring))
            ->assertOk()->getContent();
    }

    /**
     * Isi PANEL baris pasien ini — diambil tersendiri sejak 6 Okt 2026.
     *
     * Panel tidak lagi ikut dirender di halaman daftar: ia dimuat saat barisnya
     * dibuka (?frag=panel). Alasannya terukur — 95% dari fragmen tabel 454 KB
     * adalah 23 panel yang paling banyak SATU yang pernah terbuka, dan menukar
     * DOM sebesar itu tiap kali follow-up disimpan terasa seperti memuat ulang
     * halaman.
     *
     * Diminta lewat id KASUS: panel juga melayani pasien yang belum punya
     * kontrol terbuka sama sekali (tab "Pasien di cabang lain"), jadi kunci
     * kontrol tidak selalu tersedia. Isinya memuat seluruh riwayat kontrol
     * kasus itu, termasuk yang sudah ditutup.
     */
    private function panel(OrtCase $kasus): string
    {
        return $this->withSession($this->sesi())
            ->get('/tracking-ortho?' . http_build_query([
                'utama' => $this->placeId, 'frag' => 'panel', 'kasus' => $kasus->id,
            ]))->assertOk()->getContent();
    }

    /** Drawer detail — memuat matriks (_drawer_detail) seluruh kontrol kasus itu. */
    private function drawer(OrtCase $kasus): string
    {
        return $this->withSession($this->sesi())
            ->get('/tracking-ortho/' . $kasus->id . '?' . http_build_query(['utama' => $this->placeId]))
            ->assertOk()->getContent();
    }

    // ── Uji ─────────────────────────────────────────────────────────────────

    /**
     * ⭐ INTI BERKAS INI: `rsv_date` menang atas `unscheduled`.
     *
     * Baris walk-in yang sudah DITAUTKAN harus menampilkan tanggal
     * reservasinya — bukan tetap berbunyi "tanpa jadwal" karena
     * `ort:tautkan-reservasi` tidak mereset `unscheduled`.
     */
    public function test_rsv_date_menang_atas_unscheduled(): void
    {
        $k = $this->pasien();
        $this->tertutup($k, 2, '2021-03-19', [
            'unscheduled' => 1,
            'reservation_id' => 'uji-rsv-' . Str::random(6),
            'rsv_date' => '2021-03-17', 'rsv_status' => 'confirmed',
        ]);

        $html = $this->panel($k);

        // `{{ }}` meng-escape tanda petik jadi &#039; — cocokkan ke HTML yang
        // benar-benar ter-render, bukan ke teks PHP-nya.
        /* Pembawanya berganti 3 Okt 2026: garis waktu mini tiga tampilan
           (data-v="rsv") digantikan kolom KONTROL di panel tiga kolom, dan
           keadaan reservasi jadi sub-baris ber-penanda `data-ort-rsv`.
           Yang DIJAGA tidak berubah sedikit pun — baris walk-in yang sudah
           tertaut wajib menampilkan tanggal reservasinya, bukan tetap
           berbunyi "tanpa jadwal". */
        $this->assertMatchesRegularExpression(
            '/data-ort-rsv>\s*17 Mar &#039;21 ·/', $html,
            'Baris yang sudah tertaut harus menampilkan TANGGAL reservasinya di kolom Kontrol.'
        );
    }

    /** `unscheduled` tanpa tautan: katakan yang diketahui, jangan mengarang. */
    public function test_unscheduled_tanpa_tautan_berkata_jujur(): void
    {
        $k = $this->pasien();
        $this->tertutup($k, 2, '2021-07-02', ['unscheduled' => 1]);

        $html = $this->panel($k);

        $this->assertStringContainsString(
            'Datang 2 Jul &#039;21 — tidak ada jadwal tercatat', $html,
            'unscheduled = "tidak cocok dengan jadwal mana pun", bukan "pasien tidak memesan".'
        );
        $this->assertStringNotContainsStringIgnoringCase(
            'tanpa reservasi', $html,
            'Sistem TIDAK TAHU apakah pasien punya reservasi. Frasa ini mengaku tahu.'
        );
    }

    /** Kontrol terbuka tanpa reservasi: ini tugas Sales, teksnya harus bisa ditindak. */
    public function test_terbuka_tanpa_reservasi_berbunyi_belum(): void
    {
        $k = $this->pasien();

        $html = $this->panel($k);

        $this->assertStringContainsString('Belum ada reservasi', $html);
        $this->assertStringContainsString('Belum dipesan', $html);
    }

    /** Riwayat tertutup tanpa tautan: "belum dicek" ≠ "tidak ada". */
    public function test_tertutup_tanpa_tautan_berbunyi_tidak_tertaut(): void
    {
        $k = $this->pasien();
        $this->tertutup($k, 2, '2021-09-14');

        $html = $this->panel($k);

        $this->assertStringContainsString(
            'Tidak tertaut reservasi', $html,
            'Riwayat yang belum pernah dijodohkan tidak boleh berbunyi "Tidak ada reservasi".'
        );
    }

    /**
     * ⭐ DUA sumber dinyatakan terpisah: rekam medis dan reservasi.
     *
     * Fixture ini sengaja memberi tanggal yang BERBEDA — pasien dipesankan
     * 21 Nov tapi baru datang 23 Nov. Itu bentuk yang membuat penggabungan
     * lama berbahaya: sampai 7 Okt 2026 lipatan ini cuma menyebut reservasi,
     * sementara tanggal di baris judul di atasnya datang dari visit_date. Saat
     * keduanya kebetulan sama, layar terbaca seperti mengulang satu fakta;
     * saat berbeda — seperti di sini — tidak ada petunjuk sama sekali mana
     * yang mana, dan selisih dua hari itu persis yang ingin dilihat Sales.
     */
    public function test_lipatan_memisahkan_kunjungan_dari_reservasi(): void
    {
        $k = $this->pasien();
        $this->tertutup($k, 2, '2021-11-23', [
            'reservation_id' => 'uji-rsv-' . Str::random(6),
            'rsv_date' => '2021-11-21', 'rsv_status' => 'confirmed',
        ]);

        $html = $this->panel($k);

        $this->assertStringContainsString('>Dari rekam medis</span>', $html,
            'Barisnya diberi label sumbernya, bukan dibiarkan ditebak.');

        $this->assertMatchesRegularExpression(
            '~ort-chip--hadir[^>]*>Hadir</span>\s*23 Nov~', $html,
            'Baris rekam medis menyebut tanggal KUNJUNGAN (23 Nov).'
        );
        $this->assertMatchesRegularExpression(
            '~data-ort-rsv>\s*21 Nov~', $html,
            'Baris reservasi menyebut tanggal RESERVASI (21 Nov) — bukan tanggal kunjungan.'
        );

        /* Dari MANA angkanya, bukan sekadar angkanya: tarikan malam dan
           penandaan manual Sales punya tingkat kepercayaan berbeda, dan ini
           satu-satunya tempat perbedaan itu terlihat. */
        /* "terisi sendiri" saja — label barisnya sudah menyebut "dari rekam
           medis", dan mengulangnya memaksa barisnya membungkus. */
        $this->assertStringContainsString('terisi sendiri', $html,
            'Sumber angka kunjungan ikut dikatakan.');
    }

    /** Kontrol yang masih terbuka berkata belum ada kunjungan — bukan diam. */
    public function test_kontrol_terbuka_berkata_belum_ada_kunjungan(): void
    {
        $k = $this->pasien();

        $html = $this->panel($k);

        $this->assertStringContainsString('belum ada kunjungan tercatat', $html,
            'Kolom kosong tanpa kalimat terbaca seperti data yang hilang.');
    }

    /**
     * ⭐ Lipatan butir kontrol HARUS memakai delegasi, bukan sapuan sekali-jalan.
     *
     * Uji tepat di atas membuktikan kalimatnya sudah dirender server dengan
     * benar. Ia TETAP tidak pernah terlihat selama sehari penuh, karena tombol
     * yang membukanya tidak punya pendengar sama sekali:
     *
     *   querySelectorAll('[data-ort-kdetail]')  ← menyapu DOM sekali, saat muat
     *
     * sementara tombol-tombol itu baru lahir dari fetch di buka(), jauh
     * sesudahnya — panel baris dimuat belakangan sejak 6 Okt 2026. Yang terlihat
     * Reza: chevron yang bisa diklik tapi tidak melakukan apa pun, dan
     * kesimpulan wajar bahwa kontrol tanpa reservasi memang tidak punya isi.
     *
     * ⚠ Tidak ada uji Feature yang bisa menangkap ini lewat HTML: server
     *   merender keduanya dengan sempurna, yang putus cuma pengikatnya di
     *   peramban. Jadi pagarnya memang menyasar POLA SUMBERNYA — tidak lazim di
     *   repo ini, dan disengaja.
     */
    public function test_lipatan_kontrol_memakai_delegasi(): void
    {
        $js = file_get_contents(resource_path('views/crud/ortho/_js_panel.blade.php'));

        $this->assertStringNotContainsString(
            "querySelectorAll('[data-ort-kdetail]')", $js,
            'Sapuan sekali-jalan tidak pernah menemukan tombol yang lahir dari fetch.'
        );
        $this->assertStringContainsString(
            "document.addEventListener('click'", $js,
            'Pendengarnya harus didelegasikan ke document supaya menjangkau simpul yang datang belakangan.'
        );

        /* Dan pagar sekali-pasangnya. Berkas ini ikut tertukar tiap fragmen
           tabel diperbarui, jadi tanpa penanda ini pendengar document menumpuk
           dan satu klik memanggil lipat() dua kali — membuka lalu langsung
           menutup. Gejalanya identik dengan bug yang baru saja diperbaiki. */
        $this->assertStringContainsString(
            'ortKdetailPasang', $js,
            'Pendengar document wajib dipagari supaya tidak terpasang dua kali.'
        );
    }

    /**
     * Dan panelnya memang TIDAK membawa skrip itu — inilah sebab delegasinya
     * wajib, dinyatakan sebagai fakta yang bisa pecah, bukan asumsi.
     */
    public function test_fragmen_panel_tidak_membawa_skripnya(): void
    {
        $k = $this->pasien();
        $this->tertutup($k, 2, '2021-09-14');

        $html = $this->panel($k);

        $this->assertStringContainsString('data-ort-kdetail', $html,
            'Panel membawa tombol lipatannya.');
        $this->assertStringNotContainsString('ortKdetailPasang', $html,
            'Tapi tidak membawa skrip yang memasang pendengarnya.');
    }

    /**
     * Dua layar harus sepakat tentang baris yang sama.
     *
     * Panel baris (List) dan matriks drawer detail membaca kolom yang sama.
     * Kalau urutan cabangnya berbeda, satu menampilkan tanggal dan satu lagi
     * menyembunyikannya — dan Sales tidak tahu mana yang benar.
     */
    public function test_panel_baris_dan_drawer_detail_sepakat(): void
    {
        $k = $this->pasien();
        $this->tertutup($k, 2, '2021-11-23', [
            'unscheduled' => 1,
            'reservation_id' => 'uji-rsv-' . Str::random(6),
            'rsv_date' => '2021-11-21', 'rsv_status' => 'confirmed',
        ]);

        $list   = $this->panel($k);
        $drawer = $this->drawer($k);

        $this->assertMatchesRegularExpression('/data-ort-rsv>\s*21 Nov &#039;21 ·/', $list,
            'Panel baris: tanggal reservasi tampil.');
        // Matriks drawer merender tiap tanggal di barisnya sendiri, berindentasi —
        // jadi `>21 Nov<` tidak pernah bersebelahan. Regex menoleransi whitespace.
        // `\s*<` di ujung juga membedakannya dari kolom Jadwal yang berbunyi
        // "23 Nov &#039;21" (format j M 'y), bukan "23 Nov" polos (format j M).
        $this->assertMatchesRegularExpression('/>\s*21 Nov\s*</', $drawer,
            'Drawer detail: kolom Reservasi menampilkan tanggal yang sama.');
        $this->assertMatchesRegularExpression('/>\s*23 Nov\s*</', $drawer,
            'Drawer detail: kolom Datang tetap menampilkan tanggal kunjungan.');
    }

    // ── Tautan ke modul Reservasi ───────────────────────────────────────────

    /**
     * ⭐ Tombol di lipatan butir kontrol menunjuk SATU reservasi persis.
     *
     * `?reservation_id=` menyaring daftar Reservasi ke baris itu
     * (ReservationController:128). Versi lama modul ini mengirim
     * date_from/date_to — itu mendaratkan Sales di SELURUH reservasi hari itu
     * dan menyuruhnya mencari sendiri.
     */
    public function test_tautan_reservasi_menunjuk_satu_baris(): void
    {
        $k   = $this->pasien();
        $rsv = 'uji-rsv-' . Str::random(8);
        $this->tertutup($k, 2, '2021-05-04', [
            'reservation_id' => $rsv, 'rsv_date' => '2021-05-04', 'rsv_status' => 'confirmed',
        ]);

        $html = $this->panel($k);

        $this->assertStringContainsString(
            'reservation_id=' . $rsv, $html,
            'Tombol harus membawa reservation_id, bukan rentang tanggal.'
        );
        $this->assertStringContainsString('Buka di Reservasi', $html);
    }

    /**
     * ⭐ WAITLIST wajib membawa `view=waitlist`, dan ini kegagalan SUNYI kalau
     * terlewat: tab bawaan 'list' sengaja MENGECUALIKAN status waitlist
     * (ReservationController, "Tab waitlist segregation"), jadi tautan tanpa
     * parameter ini mendarat di daftar KOSONG — terbaca seperti reservasinya
     * hilang, padahal ia ada dan sehat.
     */
    public function test_tautan_waitlist_membawa_tabnya(): void
    {
        $k   = $this->pasien();
        $rsv = 'uji-rsv-' . Str::random(8);
        $this->tertutup($k, 2, '2021-06-08', [
            'reservation_id' => $rsv, 'rsv_date' => null,
            'rsv_status' => \App\Models\RawCusReservation::STATUS_WAITLIST,
        ]);

        $html = $this->panel($k);

        $this->assertMatchesRegularExpression(
            '~reservation_id=' . preg_quote($rsv, '~') . '[^"]*view=waitlist~', $html,
            'Tautan waitlist tanpa view=waitlist mendarat di daftar kosong.'
        );
    }

    /** Kontrol tanpa reservasi tidak diberi tombol — tidak ada yang bisa dibuka. */
    public function test_tanpa_reservasi_tanpa_tombol(): void
    {
        $k = $this->pasien();
        $this->tertutup($k, 2, '2021-08-17');

        // Disaring ke pasien uji ini saja — lihat catatan di halaman().
        $this->assertStringNotContainsString('Buka di Reservasi', $this->panel($k));
    }

    /** Tooltip di drawer detail ikut jujur — klaim keempat yang ditemukan. */
    public function test_tooltip_drawer_tidak_mengarang(): void
    {
        $k = $this->pasien();
        $this->tertutup($k, 2, '2021-04-08', ['unscheduled' => 1]);

        $drawer = $this->drawer($k);

        $this->assertStringContainsString('tidak cocok dengan jadwal mana pun di sistem', $drawer);
        $this->assertStringNotContainsStringIgnoringCase('tanpa reservasi', $drawer);
    }
}
