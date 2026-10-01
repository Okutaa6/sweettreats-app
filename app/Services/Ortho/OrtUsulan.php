<?php

namespace App\Services\Ortho;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Models\RawDoctorSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Memutuskan TANGGAL usulan kontrol berikutnya — atau memutuskan bahwa usulan
 * itu sebaiknya TIDAK ada.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KENAPA KELAS INI LAHIR
 * ────────────────────────────────────────────────────────────────────────────
 * Audit 1 Okt 2026 menjalankan tarikan mundur di data prod penuh: 1.154 usulan
 * lahir, dan **651 di antaranya pada satu tanggal yang sama**. Sebabnya aturan
 * lama "kunjungan + 30, lalu dijepit supaya tidak lahir terlewat" — saat
 * menarik riwayat berbulan-bulan, SETIAP pasien yang +30-nya sudah lewat
 * dijepit ke tanggal dekat yang sama. Dari situ semuanya menumpuk.
 *
 * Yang membuatnya mahal bukan kerapiannya. Tanggal itu jatuh hari Minggu, dan
 * drg. Andy — satu-satunya dokter ortho Kalideres — cuma praktik RABU. Jadi
 * 117 usulan Kalideres mendarat di hari yang mustahil. Sales menelepon pasien,
 * menyebut tanggal, lalu tahu dokternya libur.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * DUA KELAS KESALAHAN, DIPERLAKUKAN BERBEDA
 * ────────────────────────────────────────────────────────────────────────────
 *   SALAH LUNAK — meleset dari irama pasien. Pasien menggeser. Selesai.
 *   SALAH KERAS — dokter libur / klinik tutup / hari sudah penuh.
 *                 Janjinya MUSTAHIL.
 *
 * Pagar keras tidak boleh dilanggar. Dan kalau salah satunya TIDAK DIKETAHUI,
 * jawabannya bukan menebak melainkan TIDAK MENGUSULKAN — biarkan Sales
 * menjadwalkan sendiri. Usulan yang mustahil lebih mahal daripada tak ada
 * usulan.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * ANGKA YANG MENGIKAT — terukur di PROD, jangan diukur ulang di dev
 * ────────────────────────────────────────────────────────────────────────────
 * Klinik tutup SENIN: reservasi 0 dan kunjungan ortho 0 sepanjang Jul–Sep.
 *
 * ⚠ MINGGU BUKAN hari tutup — justru tersibuk ketiga (936 reservasi · 521
 *   kunjungan). Dugaan "akhir pekan tutup" itu keliru dan sempat hampir
 *   dipakai berdasarkan 13 baris jadwal di DB lokal; yang menyelamatkannya
 *   pengukuran prod, bukan nalar.
 */
class OrtUsulan
{
    /** Hari klinik tutup, ISO (1 Senin … 7 Minggu). */
    public const HARI_TUTUP = [1];

    /** Sejauh apa boleh menggeser mencari hari sah sebelum menyerah. */
    private const JELAJAH_HARI = 45;

    /**
     * Dipakai kalau sebuah cabang-hari belum punya riwayat sama sekali. Bukan
     * angka operasional — cuma jaring supaya cabang baru tidak berambang nol,
     * yang berarti nol usulan selamanya.
     */
    private const AMBANG_CADANGAN = 12;

    /** Cache per proses. "place|dow" => ambang · "place|tanggal" => terpakai. */
    private array $ambang = [];

    private array $terpakai = [];

    /** @param int|null $ambangTetap dari --ambang=, kalau operasional menimpanya */
    public function __construct(private ?int $ambangTetap = null)
    {
    }

    /**
     * @return array{tanggal: ?string, sebab: ?string, basis: ?string, basis_n: int}
     *         tanggal null + sebab terisi = sengaja TIDAK diusulkan.
     *         basis 'irama_rapat'   = irama pasien RAPAT, usulan layak sekali klik
     *         basis 'irama_longgar' = ada irama tapi sebarannya lebar — tebakan
     *         basis 'default'       = riwayat belum cukup, dipakai +30
     *         basis_n         = dari berapa selang irama itu dihitung
     */
    public function untuk(OrtCase $kasus, CarbonInterface $kunjungan, string $placeId): array
    {
        $hariIni = Carbon::today();

        /*
         * Pagar 1 — pasien yang sudah lama hilang.
         *
         * Keputusan pemilik produk 1 Okt. Dari 927 pasien Bintaro, 495 hilang
         * lebih dari 6 bulan; sebagian sudah lepas behel di tempat lain.
         * Mengusulkan tanggal untuk mereka membanjiri daftar kerja harian
         * dengan nama yang tidak akan datang, dan menenggelamkan pasien yang
         * masih aktif. Mereka sudah punya tempat sendiri: kartu "Belum punya
         * jadwal" kelompok "Hilang lebih dari 6 bulan", yang sudah mengurut
         * terlama-dulu.
         */
        if (Carbon::instance($kunjungan)->startOfDay()->diffInDays($hariIni, false) > OrtCase::HILANG_HARI) {
            return ['tanggal' => null, 'sebab' => 'hilang', 'basis' => null, 'basis_n' => 0];
        }

        /*
         * Titik awal — sinyal LUNAK: IRAMA PASIEN ITU SENDIRI (Tahap C).
         *
         * Terukur di prod: +30 untuk semua orang meleset 22 hari dari
         * kunjungan yang benar-benar terjadi; irama pasien meleset 14 dan
         * menang di 86% kasus. Masuk akal — pasien yang terbiasa datang tiap
         * 26 hari tidak berubah jadi pasien 30 hari karena angka itu bulat.
         *
         * Dua pagar menjaga "irama" tetap berarti irama:
         *   • butuh minimal IRAMA_MIN_SELANG selang, kalau tidak jatuh ke +30.
         *     Satu selang bukan irama, itu satu kebetulan.
         *   • dijepit ke [IRAMA_MIN_HARI, IRAMA_MAX_HARI]. Pasien yang pernah
         *     hilang lalu kembali punya selang ratusan hari; meneruskannya
         *     berarti menjadwalkan ulang kelalaian, bukan kontrol.
         *
         * Dihitung SEGAR di sini, bukan dibaca dari kolom median_gap_days.
         * Saat tarikan malam berjalan, kunjungan yang baru saja ditulis sudah
         * masuk hitungan ini, sedangkan kolomnya baru disegarkan OrtRollup
         * sesudah seluruh kasus selesai diproses — selisih satu kunjungan yang
         * justru kunjungan terpenting.
         */
        $irama = $this->iramaPasien($kasus);
        $maju  = $irama['median'] === null ? null : max(
            OrtCase::IRAMA_MIN_HARI,
            min(OrtCase::IRAMA_MAX_HARI, $irama['median'])
        );

        /*
         * Tingkat keyakinan, bukan sekadar "pakai irama atau tidak".
         *
         * Terukur di prod: irama mengalahkan +30 (meleset 14 hari lawan 22,
         * menang 86%), TAPI irama pun meleset 14 hari dan hanya 24% pasien
         * yang iramanya rapat. Untuk 76% sisanya tanggal ini tebakan dua
         * minggu — dan layar memakai tingkat ini untuk memutuskan apakah
         * pantas menawarkan tombol sekali klik atau justru menyuruh menelepon
         * dulu.
         */
        $basis   = $maju === null ? OrtCase::BASIS_DEFAULT : $irama['basis'];
        $basisN  = $irama['selang'];
        $tanggal = OrtControl::tanggalUsulan($kunjungan, $hariIni, $maju);

        /*
         * Dokter tak terpetakan atau tak punya jadwal di cabang ini berarti
         * hari praktiknya TIDAK DIKETAHUI — bukan "tidak pernah praktik".
         * Pemetaan 37 dokter kita ↔ 53 dokter Omnicare memang masih pekerjaan
         * terbuka. Itu alasan untuk DIAM, bukan untuk menebak.
         */
        $hariPraktik = $this->hariPraktikDokter($kasus, $placeId);
        if ($hariPraktik === []) {
            return ['tanggal' => null, 'sebab' => 'dokter_tak_terjadwal', 'basis' => null, 'basis_n' => 0];
        }

        for ($i = 0; $i < self::JELAJAH_HARI; $i++, $tanggal = $tanggal->copy()->addDay()) {
            if (in_array($tanggal->dayOfWeekIso, self::HARI_TUTUP, true)) {
                continue;                                   // pagar 3 — klinik tutup
            }
            if (! in_array($tanggal->dayOfWeekIso, $hariPraktik, true)) {
                continue;                                   // pagar 4 — dokter tak praktik
            }
            if ($this->penuh($placeId, $tanggal)) {
                continue;                                   // pagar 5 — hari sudah penuh
            }

            $this->catatTerpakai($placeId, $tanggal);

            return [
                'tanggal' => $tanggal->toDateString(),
                'sebab'   => null,
                'basis'   => $basis,
                'basis_n' => $basisN,
            ];
        }

        // Enam minggu dijelajahi, nol hari sah. Lebih jujur menyerah daripada
        // memaksakan tanggal yang melanggar pagar.
        return ['tanggal' => null, 'sebab' => 'tak_ada_slot', 'basis' => null, 'basis_n' => 0];
    }

    // ════════════════════════════════════════════════════════════════════════

    /**
     * Tanggal-tanggal sah berikutnya untuk pasien ini — bahan PEMILIH, bukan
     * keputusan.
     *
     * Inilah mesin pagar yang sama persis dengan yang melahirkan usulan,
     * dibuka supaya Sales bisa memilih sendiri saat menelepon. Konsekuensinya
     * yang paling berharga: pemilihnya **tidak pernah bisa menawarkan hari
     * mustahil**. Kalau klinik tutup, dokternya tidak praktik, atau harinya
     * sudah penuh, tanggal itu tidak pernah sampai ke layar — tanpa satu pun
     * aturan kedua yang bisa melenceng diam-diam dari aturan pertama.
     *
     * Dipanggil saat merender lembar panggilan, jadi sengaja TIDAK mencatat
     * kuota terpakai: menawarkan bukan memesan. Yang mencatat kuota hanyalah
     * untuk(), saat usulan benar-benar lahir.
     *
     * @param  string|null  $tanggalSendiri  tanggal yang SUDAH dipegang baris
     *         yang sedang dijadwal ulang. Pagar kapasitas dilewati khusus untuk
     *         tanggal itu — kalau tidak, baris itu terhitung melawan kuotanya
     *         sendiri dan tanggal yang sedang dipakainya justru hilang dari
     *         daftar pilihan. Pasien yang bilang "tanggal usulan itu saja
     *         cocok" lalu tidak menemukannya di layar.
     *
     * @return list<string> tanggal Y-m-d, menaik. [] = tidak ada yang sah
     *                      (dokter tak terpetakan, atau enam minggu penuh)
     */
    public function hariSahBerikutnya(
        OrtCase $kasus,
        CarbonInterface $dari,
        string $placeId,
        int $jumlah = 6,
        ?string $tanggalSendiri = null
    ): array {
        $hariPraktik = $this->hariPraktikDokter($kasus, $placeId);
        if ($hariPraktik === []) {
            return [];
        }

        $hasil   = [];
        $tanggal = Carbon::instance($dari)->startOfDay();

        for ($i = 0; $i < self::JELAJAH_HARI && count($hasil) < $jumlah; $i++, $tanggal = $tanggal->copy()->addDay()) {
            if (in_array($tanggal->dayOfWeekIso, self::HARI_TUTUP, true)) {
                continue;
            }
            if (! in_array($tanggal->dayOfWeekIso, $hariPraktik, true)) {
                continue;
            }
            $hari = $tanggal->toDateString();

            // Pagar kapasitas, KECUALI untuk tanggal yang memang sudah dipegang
            // baris ini. Hari penuh bukan alasan menyembunyikan tanggal yang
            // tidak akan menambah beban sama sekali.
            if ($hari !== $tanggalSendiri && $this->penuh($placeId, $tanggal)) {
                continue;
            }

            $hasil[] = $hari;
        }

        return $hasil;
    }

    /**
     * Irama pasien ini, dihitung dari kunjungan yang SUDAH tercatat.
     *
     * Rumusnya sengaja tidak tinggal di sini: OrtRollup menyimpan angka yang
     * sama ke kolom median_gap_days untuk ditampilkan, dan dua salinan rumus
     * median adalah dua kesempatan untuk berbeda tanpa ada yang sadar.
     *
     * @return array{median: ?int, selang: int}
     */
    private function iramaPasien(OrtCase $kasus): array
    {
        $tanggal = OrtControl::where('ort_case_id', $kasus->id)
            ->whereNotNull('visit_date')
            ->orderBy('visit_date')
            ->pluck('visit_date');

        return OrtCase::irama($tanggal);
    }

    /**
     * Hari-hari (ISO) dokter kasus ini praktik di cabang itu.
     * [] berarti TIDAK DIKETAHUI.
     *
     * ⚠ Sengaja TIDAK memakai trait ResolvesDoctorAvailability. Trait itu milik
     *   controller dan menghitung SLOT JAM — memotong istirahat, cuti, dan
     *   pengecualian per-tanggal. Jauh lebih berat daripada yang dibutuhkan di
     *   sini, dan menyeret ketergantungan request. Yang dipakai ulang
     *   KONVENSInya: `day` = dayOfWeekIso (1 Senin … 7 Minggu), dan baris
     *   bertipe `break` bukan jam kerja. Kalau konvensi itu berubah, kedua
     *   tempat wajib ikut berubah.
     *
     * @return list<int>
     */
    private function hariPraktikDokter(OrtCase $kasus, string $placeId): array
    {
        if (! filled($kasus->doctor_ref_id)) {
            return [];
        }

        // Nol JOIN lintas keluarga tabel — collation prod campur. Ambil id
        // dulu, tempel sebagai literal.
        $doctorIds = DB::table('raw_doctor')
            ->where('omnicare_doc_id', (string) $kasus->doctor_ref_id)
            ->pluck('id')->all();

        $branchId = app(OrtBranches::class)->branchId($placeId);

        if (! $doctorIds || ! $branchId) {
            return [];
        }

        return DB::table('raw_doctor_schedule')
            ->whereIn('raw_doctor_id', $doctorIds)
            ->where('raw_branch_id', $branchId)
            ->where('type', '<>', RawDoctorSchedule::TYPE_BREAK)
            ->distinct()->pluck('day')
            ->map(fn ($d) => (int) $d)->values()->all();
    }

    /** Sudahkah (cabang, tanggal) menyentuh ambangnya? */
    private function penuh(string $placeId, CarbonInterface $tanggal): bool
    {
        $kunci = $placeId . '|' . $tanggal->toDateString();

        if (! isset($this->terpakai[$kunci])) {
            // Jadwal yang SUDAH ada di hari itu ikut dihitung — usulan baru
            // menumpang hari yang mungkin sudah dipesan Sales.
            $this->terpakai[$kunci] = (int) OrtControl::where('place_id', $placeId)
                ->where('sch_date', $tanggal->toDateString())
                ->whereNull('closed_at')
                ->count();
        }

        return $this->terpakai[$kunci] >= $this->ambangUntuk($placeId, $tanggal);
    }

    private function catatTerpakai(string $placeId, CarbonInterface $tanggal): void
    {
        $kunci = $placeId . '|' . $tanggal->toDateString();
        $this->terpakai[$kunci] = ($this->terpakai[$kunci] ?? 0) + 1;
    }

    /**
     * Ambang pasien per (cabang, hari-dalam-minggu) — DITURUNKAN DARI BEBAN
     * NYATA, bukan angka yang ditebak.
     *
     * Cermin rekam medis sudah menyimpan berapa pasien yang sungguh dilayani
     * tiap cabang tiap hari. Mediannya adalah jawaban yang dibuktikan klinik
     * itu sendiri: beda wajar antara Bintaro (959 kasus) dan Cinere (203),
     * beda antara Rabu dan Kamis, dan ikut bergeser sendiri kalau kliniknya
     * tumbuh. Satu angka tetap untuk lima cabang akan selalu salah di salah
     * satu ujungnya.
     *
     * Median, bukan rata-rata: satu hari luar biasa sibuk tidak boleh
     * menaikkan ambang sepanjang tahun.
     */
    private function ambangUntuk(string $placeId, CarbonInterface $tanggal): int
    {
        if ($this->ambangTetap !== null) {
            return $this->ambangTetap;
        }

        $kunci = $placeId . '|' . $tanggal->dayOfWeekIso;

        if (! isset($this->ambang[$kunci])) {
            $this->ambang[$kunci] = $this->medianBeban($placeId, $tanggal->dayOfWeekIso);
        }

        return $this->ambang[$kunci];
    }

    private function medianBeban(string $placeId, int $dow): int
    {
        // ⚠ DAYOFWEEK() MySQL: 1 Minggu … 7 Sabtu. ISO: 1 Senin … 7 Minggu.
        //   Jembatannya (iso % 7) + 1 — gampang keliru, jadi ditulis terang.
        $mysqlDow = ($dow % 7) + 1;

        $perHari = DB::table('omnicare_sales_detail')
            ->where('place_id', $placeId)
            ->whereRaw('DAYOFWEEK(trx_date) = ?', [$mysqlDow])
            ->where('trx_date', '>=', Carbon::today()->subYear()->toDateString())
            ->groupBy('trx_date')
            ->selectRaw('COUNT(DISTINCT p_id) AS n')
            ->pluck('n')->map(fn ($n) => (int) $n)->sort()->values()->all();

        if (! $perHari) {
            return self::AMBANG_CADANGAN;
        }

        $t = count($perHari);

        return (int) ($t % 2
            ? $perHari[intdiv($t, 2)]
            : ceil(($perHari[$t / 2 - 1] + $perHari[$t / 2]) / 2));
    }
}
