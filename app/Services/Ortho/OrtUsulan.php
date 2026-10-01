<?php

namespace App\Services\Ortho;

use App\Models\OrtCase;
use App\Models\OrtControl;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Menghitung TANGGAL JATUH TEMPO kontrol berikutnya — atau memutuskan bahwa
 * pasien ini sebaiknya tidak diberi jatuh tempo sama sekali.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * APA YANG DIHITUNG DI SINI, DAN APA YANG BUKAN
 * ────────────────────────────────────────────────────────────────────────────
 * Kolom Jadwal adalah PENGINGAT. Brief kamus istilah:
 *
 *     SCH · Jadwal    · tanggal pasien SEHARUSNYA datang kontrol
 *     RSV · Reservasi · reservasi nyata. Kosong = pasien belum booking
 *
 * Jadi yang lahir dari kelas ini bukan janji dengan siapa pun. Tidak ada slot
 * yang dipesan, tidak ada pasien yang diberi tahu, tidak ada yang bisa
 * bentrok. Janji yang sesungguhnya dibuat di modul Reservasi, lengkap dengan
 * jam, dokter, dan aturan anti-bentrok miliknya sendiri.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KENAPA PAGAR JADWALNYA DIBUANG
 * ────────────────────────────────────────────────────────────────────────────
 * Kelas ini lahir dari audit HUB 1 Okt: satu tarikan mundur melahirkan 1.154
 * usulan, 651 di antaranya pada SATU tanggal yang sama, dan 117 di antaranya
 * di hari drg. Andy tidak praktik. Jawaban pertama saya adalah memasang pagar
 * keras — klinik tutup, hari praktik dokter, kapasitas cabang.
 *
 * Pagar itu mengobati gejalanya. Penyakitnya ada di tempat lain: sebuah LANTAI
 * yang mendorong setiap jatuh tempo yang sudah lewat ke `hari ini + 3`. Saat
 * menarik riwayat berbulan-bulan, semua pasien yang tertinggal menumpuk di
 * tanggal dekat yang sama. Begitu lantainya dibuang, jatuh tempo menyebar
 * sendiri karena diturunkan dari kunjungan terakhir masing-masing pasien —
 * terukur: 34 pasien di 29 tanggal berbeda, paling padat 2 orang sehari,
 * TANPA pagar kapasitas apa pun.
 *
 * Dan pagar hari dokter memang tidak pernah jadi urusan di sini: ketersediaan
 * dokter diperiksa saat MEMESAN, oleh modul Reservasi lewat trait
 * ResolvesDoctorAvailability yang memotong jam istirahat, cuti, dan
 * pengecualian per tanggal. Jauh lebih teliti daripada yang pernah ada di sini.
 *
 * ⚠ Satu pagar TETAP, dan ia bukan soal jadwal melainkan beban kerja: pasien
 *   yang hilang lebih dari 6 bulan tidak diberi jatuh tempo. Dari 927 pasien
 *   Bintaro, 495 hilang lebih dari itu; memberi mereka jatuh tempo berarti
 *   membanjiri daftar kerja harian dengan nama yang tidak akan datang. Mereka
 *   punya tempat sendiri di kartu "Belum punya jadwal".
 */
class OrtUsulan
{
    /**
     * @return array{tanggal: ?string, sebab: ?string, basis: ?string, basis_n: int}
     *         tanggal null + sebab terisi = sengaja TIDAK diberi jatuh tempo.
     *         basis 'irama_rapat'   = irama pasien rapat, tanggalnya bisa dipegang
     *         basis 'irama_longgar' = ada irama tapi sebarannya lebar — perkiraan
     *         basis 'default'       = riwayat belum cukup, dipakai +30 hari
     *         basis_n               = dari berapa selang irama itu dihitung
     */
    public function untuk(OrtCase $kasus, CarbonInterface $kunjungan, string $placeId): array
    {
        /*
         * Satu-satunya pagar yang tersisa — dan ia soal beban kerja, bukan
         * soal tanggal. Keputusan pemilik produk 1 Okt.
         *
         * $placeId tidak lagi ikut menentukan apa pun di sini; ia dipertahankan
         * di tanda tangan karena pemanggilnya memang tahu cabang, dan kalau
         * suatu hari jatuh tempo perlu berbeda per cabang (mis. klinik yang
         * target jaraknya lain), di sinilah tempatnya masuk.
         */
        if (Carbon::instance($kunjungan)->startOfDay()->diffInDays(Carbon::today(), false) > OrtCase::HILANG_HARI) {
            return ['tanggal' => null, 'sebab' => 'hilang', 'basis' => null, 'basis_n' => 0];
        }

        /*
         * Titik berangkatnya IRAMA PASIEN ITU SENDIRI.
         *
         * Terukur di prod: +30 untuk semua orang meleset 22 hari dari kunjungan
         * yang benar-benar terjadi; irama pasien meleset 14 dan menang di 86%
         * kasus. Pasien yang terbiasa datang tiap 26 hari tidak berubah jadi
         * pasien 30 hari karena angka itu bulat.
         *
         * Dua pagar menjaga "irama" tetap berarti irama:
         *   • butuh minimal IRAMA_MIN_SELANG selang, kalau tidak jatuh ke +30.
         *     Satu selang bukan irama, itu satu kebetulan.
         *   • dijepit ke [IRAMA_MIN_HARI, IRAMA_MAX_HARI]. Pasien yang pernah
         *     hilang lalu kembali punya selang ratusan hari; meneruskannya
         *     berarti ikut melembagakan kelalaiannya.
         *
         * Dihitung SEGAR di sini, bukan dibaca dari kolom median_gap_days. Saat
         * tarikan malam berjalan, kunjungan yang baru saja ditulis sudah masuk
         * hitungan ini, sedangkan kolomnya baru disegarkan OrtRollup sesudah
         * seluruh kasus selesai diproses — selisih satu kunjungan, dan justru
         * kunjungan terpenting.
         */
        $irama = $this->iramaPasien($kasus);
        $maju  = $irama['median'] === null ? null : max(
            OrtCase::IRAMA_MIN_HARI,
            min(OrtCase::IRAMA_MAX_HARI, $irama['median'])
        );

        /*
         * Tingkat keyakinan. Ia tidak lagi menentukan tombol apa pun — hanya
         * memberi tahu Sales seberapa bisa dipegang tanggal ini. Pasien
         * berselang 28·28·28 jatuh temponya bisa dipercaya; pasien 20·90·25·110
         * tidak, sekalipun selangnya lebih banyak.
         */
        $tanggal = OrtControl::tanggalUsulan($kunjungan, $maju);

        return [
            // BOLEH di masa lalu. Itu arti "Terlewat", dan itulah gunanya.
            'tanggal' => $tanggal->toDateString(),
            'sebab'   => null,
            'basis'   => $maju === null ? OrtCase::BASIS_DEFAULT : $irama['basis'],
            'basis_n' => $irama['selang'],
        ];
    }

    // ════════════════════════════════════════════════════════════════════════

    /**
     * Irama pasien ini, dihitung dari kunjungan yang SUDAH tercatat.
     *
     * Rumusnya sengaja tidak tinggal di sini: OrtRollup menyimpan angka yang
     * sama ke kolom median_gap_days untuk ditampilkan, dan dua salinan rumus
     * median adalah dua kesempatan untuk berbeda tanpa ada yang sadar.
     *
     * @return array{median: ?int, selang: int, min: ?int, max: ?int, sebar: ?int, basis: string}
     */
    private function iramaPasien(OrtCase $kasus): array
    {
        $tanggal = OrtControl::where('ort_case_id', $kasus->id)
            ->whereNotNull('visit_date')
            ->orderBy('visit_date')
            ->pluck('visit_date');

        return OrtCase::irama($tanggal);
    }
}
