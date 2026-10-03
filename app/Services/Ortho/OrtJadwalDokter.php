<?php

namespace App\Services\Ortho;

use App\Http\Controllers\Concerns\ResolvesDoctorAvailability;
use App\Models\OrtCase;
use App\Models\RawCusReservation;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use App\Models\RawDoctorScheduleException;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hari praktik dokter pasien ini — beserta jamnya dan seberapa penuh.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KENAPA KELAS INI ADA
 * ────────────────────────────────────────────────────────────────────────────
 * Lembar "Buat Reservasi" di modul ini dulu menanyakan SATU KOTAK TANGGAL
 * kosong, lalu mengoper apa pun yang diketik ke modul Reservasi — di mana
 * Sales ditanyai tanggal lagi, kali ini dengan jadwal dokter yang sebenarnya.
 * Dua kali ditanya, dan yang pertama tanpa tahu apa-apa.
 *
 * Akibatnya Sales menelepon pasien sambil menebak: "Rabu bisa, Bu?" tanpa tahu
 * dokternya praktik hari apa, jam berapa, dan sudah berapa penuh.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * MEMAKAI ULANG, BUKAN MENULIS ULANG
 * ────────────────────────────────────────────────────────────────────────────
 * Seluruh jawabannya sudah ada di trait ResolvesDoctorAvailability milik modul
 * Reservasi: ia memotong jam istirahat (`raw_doctor_schedule` type=break),
 * cuti (`raw_doctor_leave`), dan pengecualian per tanggal
 * (`raw_doctor_schedule_exception`). Kelas ini TIDAK menghitung satu pun dari
 * itu sendiri — ia memanggil trait yang sama persis dengan yang dipakai form
 * Reservasi dan API slot.
 *
 * ⚠ Itu keputusan yang sengaja. Repo ini SUDAH punya tiga rumus kapasitas yang
 *   saling berbeda (DoctorController memakai `durasi_shift × 2` tanpa memotong
 *   istirahat dan tanpa menyaring reservasi batal). Menambah rumus keempat di
 *   sini akan membuat angka di layar ortho berbeda dari angka di layar
 *   Reservasi untuk hari yang sama — dan Sales melihat keduanya.
 *
 * Angka penuh diambil dari `sessions[*].available_count/total_count`, bukan
 * dari rumus kapasitas mana pun, supaya ia selalu cocok dengan grid slot yang
 * akan dilihat Sales satu layar kemudian.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * DOKTER YANG TIDAK TERPETAKAN BUKAN KASUS PINGGIRAN
 * ────────────────────────────────────────────────────────────────────────────
 * Diukur di DB pengembangan: dari 42 kasus, hanya 12 yang `doctor_ref_id`-nya
 * bisa dipetakan lewat `raw_doctor.omnicare_doc_id`, dan 13 tidak terpetakan
 * sama sekali. Jadi dua dari tiga kali, layar ini TIDAK akan punya dokter.
 *
 * Karena itu `untuk()` mengembalikan alasan yang bisa dibaca manusia, bukan
 * sekadar daftar kosong. Lembarnya wajib tetap berguna tanpa dokter — kotak
 * tanggal bebas tetap ada, dan alasannya dikatakan apa adanya.
 */
class OrtJadwalDokter
{
    use ResolvesDoctorAvailability;

    /** Berapa hari praktik ke depan yang ditawarkan (lembar satu pasien). */
    private const JUMLAH_HARI = 6;

    /**
     * Jendela sel tabel: 7 hari BERGULIR, hari ini sampai +6.
     *
     * Bukan pekan kalender Senin–Minggu, dan itu disengaja. Pekan kalender
     * membusuk sepanjang pekan: dibuka Sabtu ia menyisakan satu-dua hari dan
     * selnya kosong untuk sebagian besar dokter — justru di hari Sales paling
     * sibuk menelepon. Tujuh hari bergulir selalu memuat setiap hari praktik
     * berulang TEPAT SEKALI, jadi dokter Selasa/Kamis selalu menampilkan dua
     * tanggal, tiap hari, tanpa kecuali.
     *
     * Efek sampingnya yang justru paling berharga: jendelanya SATU DAN SAMA
     * untuk semua baris, sehingga pra-muatnya satu BETWEEN dan memo-nya runtuh
     * ke (dokter × hari praktik) — bukan per pasien.
     */
    public const JENDELA_TABEL_HARI = 7;

    /** Berapa pil tanggal yang dirender sebelum sisanya jadi "+N". */
    public const PIL_MAKS = 3;

    /**
     * Sisa slot (persen) yang masih disebut "hampir penuh" — pil kuning.
     * Usulan prompt: sisa <= 20%.
     *
     * Konstanta, bukan nilai di Blade: ambang yang hidup di template adalah
     * ambang yang akan bercabang dua begitu panel baris ikut mewarnai.
     */
    public const AMBANG_HAMPIR_PENUH_PCT = 20;

    /**
     * Sejauh apa menengok ke depan. Dibatasi karena computeAvailableDates()
     * menjelajah 535 hari dan menanyai DB untuk tiap tanggal yang lolos —
     * kita cuma butuh enam yang pertama.
     */
    private const JELAJAH_HARI = 70;

    public function __construct(private OrtBranches $branches)
    {
    }

    /**
     * @return array{
     *     hari: list<array{tanggal:string, label:string, jam:?string, terisi:int, total:int, penuh:bool}>,
     *     dokter: ?string,
     *     sebab: ?string
     * }
     *     sebab terisi = daftar `hari` kosong, dan ini alasannya:
     *       'tanpa_dokter'   pasien belum punya doctor_ref_id
     *       'tak_terpetakan' ada kodenya tapi tidak ketemu di raw_doctor
     *       'tak_praktik'    dokternya tidak punya jadwal di cabang ini
     *       'tak_ada_hari'   terpetakan & berjadwal, tapi 10 minggu ke depan kosong
     */
    public function untuk(OrtCase $kasus, string $placeId, ?CarbonInterface $mulai = null): array
    {
        $kosong = ['hari' => [], 'dokter' => null, 'sebab' => null];

        $branchId = $this->branches->branchId($placeId);
        if (! $branchId) {
            // array_merge, BUKAN operator +. `$kosong` sudah memuat
            // 'sebab' => null, dan `+` mempertahankan nilai sisi KIRI untuk
            // kunci yang sama — jadi versi lama mengembalikan sebab NULL dan
            // lembarnya tidak pernah menjelaskan kasus ini sama sekali.
            return array_merge($kosong, ['sebab' => 'tak_praktik']);
        }

        if (! filled($kasus->doctor_ref_id)) {
            return array_merge($kosong, ['sebab' => 'tanpa_dokter']);
        }

        /*
         * Dua kegagalan yang BERBEDA, dan membedakannya penting: yang satu
         * diperbaiki tim IT (memetakan kode dokter), yang satu diperbaiki
         * operasional (mengisi jadwal praktik). Satu pesan untuk keduanya
         * mengirim Sales mengejar perbaikan yang salah.
         */
        $dokter = RawDoctor::where('omnicare_doc_id', (string) $kasus->doctor_ref_id)->first();
        if (! $dokter) {
            return array_merge($kosong, ['sebab' => 'tak_terpetakan']);
        }

        if (! $this->berjadwalDi($dokter->id, $branchId)) {
            return array_merge($kosong, ['dokter' => $dokter->name, 'sebab' => 'tak_praktik']);
        }

        /*
         * computeAvailableDates() mulai dari hari ini dan memberi SEMUA tanggal
         * praktik sampai 535 hari ke depan. Kita saring ke jendela yang masuk
         * akal lalu ambil enam pertama — menghitung jam untuk 70-an tanggal
         * berarti 70-an putaran kueri untuk sesuatu yang tak akan dilihat.
         */
        $dari   = Carbon::instance($mulai ?: Carbon::today())->startOfDay();
        $sampai = $dari->copy()->addDays(self::JELAJAH_HARI);

        $tanggal = collect($this->computeAvailableDates($branchId, $dokter->id))
            ->filter(fn ($d) => $d['date'] >= $dari->toDateString() && $d['date'] <= $sampai->toDateString())
            ->take(self::JUMLAH_HARI);

        if ($tanggal->isEmpty()) {
            return array_merge($kosong, [
                'dokter' => $dokter->name,
                'sebab'  => 'tak_ada_hari',
            ]);
        }

        $hari = [];
        foreach ($tanggal as $t) {
            $slot = $this->computeTimeSlots($branchId, $dokter->id, $t['date']);

            // Dijumlahkan dari SESI, bukan dihitung ulang dari jadwal mentah —
            // inilah angka yang akan dilihat Sales di grid slot nanti.
            $total  = 0;
            $luang  = 0;
            foreach (($slot['sessions'] ?? []) as $sesi) {
                $total += (int) ($sesi['total_count'] ?? 0);
                $luang += (int) ($sesi['available_count'] ?? 0);
            }

            $hari[] = [
                'tanggal' => $t['date'],
                'label'   => $t['label'],
                'jam'     => $this->rentangJam($slot['slots'] ?? []),
                'terisi'  => max(0, $total - $luang),
                'total'   => $total,
                'penuh'   => $total > 0 && $luang === 0,
            ];
        }

        return ['hari' => $hari, 'dokter' => $dokter->name, 'sebab' => null];
    }

    /** Kalimat siap pakai untuk tiap sebab — supaya layar tidak mengarang sendiri. */
    public function penjelasan(?string $sebab, ?string $namaDokter = null): ?string
    {
        $dok = $namaDokter ?: 'Dokter pasien ini';

        return match ($sebab) {
            'tanpa_dokter'   => 'Pasien ini belum punya dokter ortho tercatat, jadi hari praktiknya tidak bisa ditampilkan.',
            'tak_terpetakan' => 'Dokter pasien ini belum dipetakan ke data dokter kami, jadi hari praktiknya belum bisa ditampilkan.',
            'tak_praktik'    => $dok . ' belum punya jadwal praktik tercatat di cabang ini.',
            'tak_ada_hari'   => $dok . ' tidak punya hari praktik dalam 10 minggu ke depan di cabang ini.',
            // Sebab TERPISAH, bukan memakai ulang 'tak_ada_hari': kalimat itu
            // menyebut "10 minggu" karena lembar reservasi memang menjelajah
            // JELAJAH_HARI = 70. Sel tabel hanya melihat 7 hari, jadi memakai
            // kalimat yang sama di sana adalah klaim yang salah.
            'tak_ada_hari_pekan' => $dok . ' tidak praktik di cabang ini dalam 7 hari ke depan.',
            default          => null,
        };
    }

    // ════════════════════════════════════════════════════════════════════════

    /**
     * Hari praktik untuk SEKUMPULAN baris sekaligus — 5 kueri, bukan 27/baris.
     *
     * ────────────────────────────────────────────────────────────────────────
     * KENAPA INI ADA DI SAMPING `untuk()`
     * ────────────────────────────────────────────────────────────────────────
     * `untuk()` dirancang untuk satu pasien di lembar 410px: ia menjelajah
     * JELAJAH_HARI = 70 dan mengambil enam hari PRAKTIK. Sel tabel butuh yang
     * lain — tujuh hari KALENDER, semuanya, dengan hari cuti ikut ditandai.
     * Keduanya dibiarkan terpisah dengan sengaja, dan ketiga belas uji
     * `untuk()` tetap jadi penjaga supaya lembarnya tidak berubah diam-diam.
     *
     * Yang TIDAK terpisah adalah angkanya: keduanya menjumlahkan
     * `sessions[*].total_count/available_count` dari trait yang sama, jadi
     * tabel dan lembar tidak mungkin berbeda pendapat soal seberapa penuh
     * sebuah hari.
     *
     * ────────────────────────────────────────────────────────────────────────
     * DARI ~1.350 KUERI JADI 5
     * ────────────────────────────────────────────────────────────────────────
     * Versi naif memanggil `untuk()` per baris: pencarian dokter (1) +
     * berjadwalDi (1) + computeAvailableDates (3) + computeTimeSlots ×6 (±24)
     * ≈ 27 kueri per baris, ~1.350 untuk 50 baris.
     *
     * ⚠ Gerbang H-3 TIDAK menyelamatkan, dan ini perlu dikatakan terang-terangan
     *   supaya tidak ada yang mengandalkannya lagi: "hari ini >= H-3 sebelum
     *   jatuh tempo" juga BENAR untuk semua baris yang jatuh temponya sudah
     *   lewat. Di sub-tab Terlewat (`OrtQuery::terapkanUrgensi`, sch_date <
     *   hari ini) 100% baris lolos gerbang. Gerbang itu menolong "Mendatang",
     *   bukan halaman yang paling sering dibuka.
     *
     * Jadi peredamnya dua, dan keduanya wajib:
     *   1. MEMO per dokter — keluaran ini sama sekali tidak bergantung pasien,
     *      hanya pada (cabang, dokter, jendela). Dua pasien dokter yang sama
     *      memakai hasil yang IDENTIK, bukan cuma mirip. Itu membatasi
     *      pemanggilan ke (dokter × hari praktik).
     *   2. PRA-MUAT — empat kueri agregat dititipkan ke trait, sehingga
     *      pemanggilan yang tersisa itu nol I/O. Memo sendirian tidak memberi
     *      jaminan: satu cabang dengan 50 dokter berbeda mengembalikannya ke
     *      ~1.300 kueri.
     *
     * @param  iterable<object>  $baris  stdClass dari OrtQuery::daftar() ATAU model;
     *                                   dibaca lewat ->ort_case_id / ->doctor_ref_id
     *                                   / ->last_visit_date / ->median_gap_days
     * @return array<string, array{
     *     hari: list<array{tanggal:string,label:string,hari:string,jam:?string,
     *                      terisi:int,total:int,penuh:bool,cuti:bool,nada:string}>,
     *     dokter: ?string, sebab: ?string}>
     *     Dikunci ort_case.id. Baris yang BELUM masuk jendela H-3 tidak muncul
     *     di map sama sekali — bukan entri kosong — supaya layar bisa
     *     membedakan "belum waktunya" dari "sudah waktunya tapi tidak ada
     *     dokternya".
     */
    public function untukBanyak(iterable $baris, string $placeId, ?CarbonInterface $mulai = null): array
    {
        $hariIni = Carbon::instance($mulai ?: Carbon::today())->startOfDay();
        $dari    = $hariIni->copy();
        $sampai  = $hariIni->copy()->addDays(self::JENDELA_TABEL_HARI - 1);

        // ── Siapa yang perlu dihitung, dan kodenya apa ──────────────────────
        $perlu = [];
        foreach ($baris as $r) {
            $caseId = (string) ($r->ort_case_id ?? $r->id ?? '');
            if ($caseId === '' || array_key_exists($caseId, $perlu)) {
                continue;
            }
            if (! $this->sudahWaktunya($r, $hariIni)) {
                continue;
            }
            $perlu[$caseId] = filled($r->doctor_ref_id ?? null) ? (string) $r->doctor_ref_id : null;
        }

        if (! $perlu) {
            return [];
        }

        $branchId = $this->branches->branchId($placeId);
        if (! $branchId) {
            return array_map(
                fn () => ['hari' => [], 'dokter' => null, 'sebab' => 'tak_praktik'],
                $perlu
            );
        }

        // ── SATU whereIn menggantikan N kali ->first() ──────────────────────
        // keyBy aman karena raw_doctor.omnicare_doc_id ber-index UNIQUE
        // (uq_raw_doctor_omnicare) — alasan lengkapnya di berjadwalDi() di bawah.
        $kode = array_values(array_unique(array_filter($perlu)));

        $dokter = $kode
            ? RawDoctor::whereIn('omnicare_doc_id', $kode)
                ->get(['id', 'name', 'omnicare_doc_id'])
                ->keyBy('omnicare_doc_id')
            : collect();

        $idDokter = $dokter->pluck('id')->map(fn ($v) => (string) $v)->unique()->values()->all();

        $hasil = [];
        try {
            $jadwalPerDokter = $idDokter ? $this->praMuat($idDokter, $branchId, $dari, $sampai) : [];

            foreach ($perlu as $caseId => $kodeDok) {
                $hasil[$caseId] = $this->untukSatuDariPraMuat(
                    $kodeDok, $dokter, $jadwalPerDokter, $branchId, $idDokter
                );
            }
        } finally {
            // WAJIB dibersihkan: instance ini bisa dipakai lagi dalam request
            // yang sama (mis. lembar reservasi dirender sesudah tabel), dan
            // pra-muat jendela 7 hari akan membuat lembar itu kehilangan hari
            // praktik ke-4 sampai ke-6 tanpa pesan galat apa pun.
            $this->praMuatJadwal            = null;
            $this->praMuatCuti              = null;
            $this->praMuatPengecualian      = null;
            $this->praMuatTerpesan          = null;
            $this->praMuatJendela           = null;
            $this->praMuatSertakanTertutup  = false;
        }

        return $hasil;
    }

    // ════════════════════════════════════════════════════════════════════════

    /**
     * Sudah waktunya menampilkan hari praktik? (gerbang H-3 milik prompt)
     *
     * `last_visit_date` NULL dianggap SUDAH waktunya — bracket terpasang tanpa
     * satu pun kunjungan tercatat adalah keadaan terburuk di himpunan ini, dan
     * `OrtCase::isHilang()` sudah memperlakukan NULL sebagai hilang.
     * Menyembunyikan hari praktiknya justru menyembunyikan pasien yang paling
     * perlu ditelepon.
     */
    private function sudahWaktunya(object $r, CarbonInterface $hariIni): bool
    {
        if (! filled($r->last_visit_date ?? null)) {
            return true;
        }

        $interval = OrtCase::intervalKontrol(
            isset($r->median_gap_days) && $r->median_gap_days !== null
                ? (int) $r->median_gap_days
                : null
        );

        $muncul = Carbon::parse($r->last_visit_date)
            ->addDays($interval)
            ->subDays(OrtCase::HARI_PRAKTIK_MUNCUL);

        return $hariIni->greaterThanOrEqualTo($muncul->startOfDay());
    }

    /**
     * Empat kueri agregat, lalu dititipkan ke trait. Datar — jumlahnya tidak
     * bergantung pada banyaknya baris MAUPUN banyaknya dokter.
     *
     * @param  list<string>  $idDokter
     * @return array<string, bool>  idDokter => punya jadwal di cabang ini
     */
    private function praMuat(array $idDokter, string $branchId, CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $d = $dari->toDateString();
        $s = $sampai->toDateString();

        // (1) Jadwal berulang, work + break, SEMUA hari. Sekaligus jawaban
        //     "berjadwal di cabang ini?" tanpa N kali exists().
        $jadwal = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->whereIn('raw_doctor_id', $idDokter)
            ->get();

        // (2) Cuti yang BERIRISAN dengan jendela saja — bukan seluruh riwayat
        //     cuti dokter, karena tanggal di luar jendela tidak pernah dievaluasi.
        $cuti = DB::table('raw_doctor_leave')
            ->whereIn('raw_doctor_id', $idDokter)
            ->where('start_date', '<=', $s)
            ->where('end_date', '>=', $d)
            ->get(['raw_doctor_id', 'start_date', 'end_date']);

        // (3) Pengecualian per tanggal — hanya kalau fiturnya hidup, pagar yang
        //     sama dengan trait-nya.
        $kecuali = collect();
        if (config('features.flexible_scheduling')) {
            $kecuali = RawDoctorScheduleException::where('raw_branch_id', $branchId)
                ->whereIn('raw_doctor_id', $idDokter)
                ->whereBetween('date', [$d, $s])
                ->get();
        }

        // (4) Reservasi yang menahan slot. Saringan statusnya WAJIB sama persis
        //     dengan resolveBookedRanges() — batal dan waitlist melepas slot,
        //     status NULL (data lama) tetap dianggap menahan.
        //     EXPLAIN memilih range index `date` untuk jendela 7 hari, jadi ini
        //     tidak butuh index baru di tabel paling panas di sistem.
        $terpesan = DB::table('raw_cus_reservation')
            ->whereIn('raw_doctor_id', $idDokter)
            ->whereBetween('date', [$d, $s])
            ->whereNotNull('start_hour')
            ->whereNotNull('end_hour')
            ->where(function ($q) {
                $q->whereNotIn('status', [
                    RawCusReservation::STATUS_CANCELLED,
                    RawCusReservation::STATUS_WAITLIST,
                ])->orWhereNull('status');
            })
            ->get(['raw_doctor_id', 'date', 'start_hour', 'end_hour']);

        $this->praMuatJadwal       = [];
        $this->praMuatCuti         = [];
        $this->praMuatPengecualian = [];
        $this->praMuatTerpesan     = [];

        $berjadwal = [];
        foreach ($idDokter as $id) {
            $barisJadwal = $jadwal->filter(fn ($x) => (string) $x->raw_doctor_id === $id)->values();

            $this->praMuatJadwal[$this->praKunci($id, $branchId)]       = $barisJadwal;
            $this->praMuatCuti[$id]                                      = $cuti->filter(fn ($x) => (string) $x->raw_doctor_id === $id)->values();
            $this->praMuatPengecualian[$this->praKunci($id, $branchId)] = $kecuali->filter(fn ($x) => (string) $x->raw_doctor_id === $id)->values();

            $berjadwal[$id] = $barisJadwal->where('type', '!=', RawDoctorSchedule::TYPE_BREAK)->isNotEmpty();
        }

        foreach ($terpesan as $t) {
            $kunci = $this->praKunci((string) $t->raw_doctor_id, Carbon::parse($t->date)->toDateString());
            $this->praMuatTerpesan[$kunci][] = [
                'start' => Carbon::parse($t->start_hour)->format('H:i'),
                'end'   => Carbon::parse($t->end_hour)->format('H:i'),
            ];
        }

        $this->praMuatJendela          = ['dari' => $d, 'sampai' => $s];
        $this->praMuatSertakanTertutup = true;

        return $berjadwal;
    }

    /**
     * Satu baris, dihitung dari pra-muat. Nol kueri.
     *
     * @param  array<string, bool>  $berjadwal
     * @param  list<string>         $idDokter
     */
    private function untukSatuDariPraMuat(
        ?string $kodeDok,
        $dokter,
        array $berjadwal,
        string $branchId,
        array $idDokter
    ): array {
        $kosong = ['hari' => [], 'dokter' => null, 'sebab' => null];

        if ($kodeDok === null) {
            return array_merge($kosong, ['sebab' => 'tanpa_dokter']);
        }

        $dok = $dokter->get($kodeDok);
        if (! $dok) {
            return array_merge($kosong, ['sebab' => 'tak_terpetakan']);
        }

        $id = (string) $dok->id;
        if (empty($berjadwal[$id])) {
            return array_merge($kosong, ['dokter' => $dok->name, 'sebab' => 'tak_praktik']);
        }

        $hari = [];
        foreach ($this->computeAvailableDates($branchId, $dok->id) as $t) {
            $cuti = ($t['tertutup'] ?? null) === 'cuti';
            $tgl  = Carbon::parse($t['date']);

            if ($cuti) {
                // Hari cuti dirender, tidak dihilangkan. Sel yang diam-diam
                // melewati Kamis membuat Sales mengira datanya rusak; pil
                // "Kam cuti" menjawabnya di tempat.
                $hari[] = [
                    'tanggal' => $t['date'],
                    'label'   => $t['label'],
                    'hari'    => $tgl->translatedFormat('D'),
                    'jam'     => null,
                    'terisi'  => 0,
                    'total'   => 0,
                    'penuh'   => false,
                    'cuti'    => true,
                    'nada'    => 'faint',
                ];
                continue;
            }

            $slot  = $this->computeTimeSlots($branchId, $dok->id, $t['date']);
            $total = 0;
            $luang = 0;
            foreach (($slot['sessions'] ?? []) as $sesi) {
                $total += (int) ($sesi['total_count'] ?? 0);
                $luang += (int) ($sesi['available_count'] ?? 0);
            }

            $hari[] = [
                'tanggal' => $t['date'],
                'label'   => $t['label'],
                'hari'    => $tgl->translatedFormat('D'),
                'jam'     => $this->rentangJam($slot['slots'] ?? []),
                'terisi'  => max(0, $total - $luang),
                'total'   => $total,
                'penuh'   => $total > 0 && $luang === 0,
                'cuti'    => false,
                'nada'    => $this->nada($total, $luang, false),
            ];
        }

        if (! $hari) {
            return array_merge($kosong, ['dokter' => $dok->name, 'sebab' => 'tak_ada_hari_pekan']);
        }

        return ['hari' => $hari, 'dokter' => $dok->name, 'sebab' => null];
    }

    /**
     * Warna pil: seberapa penuh hari itu.
     *
     * Dihitung di PHP, bukan di Blade — ambang yang hidup di template adalah
     * ambang yang akan bercabang dua begitu panel baris ikut mewarnai.
     */
    private function nada(int $total, int $luang, bool $cuti): string
    {
        return match (true) {
            $cuti        => 'faint',
            $total === 0 => 'faint',
            $luang === 0 => 'bad',
            $luang * 100 / $total <= self::AMBANG_HAMPIR_PENUH_PCT => 'warn',
            default      => 'ok',
        };
    }

    /**
     * Punyakah dokter ini jadwal apa pun di cabang itu?
     *
     * ⚠ Pencarian kodenya sengaja `->first()`, dan itu AMAN karena
     *   `raw_doctor.omnicare_doc_id` punya index UNIQUE
     *   (`uq_raw_doctor_omnicare`) — satu kode tidak mungkin menunjuk dua
     *   baris. Nama dokter MEMANG berkembar di dev (drg. Nugroho dan drg.
     *   Putri Moortiningsih masing-masing dua baris), tapi kembarannya
     *   ber-omnicare_doc_id NULL, jadi tak pernah ikut terjaring lewat kode.
     *
     *   Jembatan reservasi di ReservationController memilih di antara
     *   kandidat JAMAK karena ia juga punya cadangan pencocokan NAMA, dan di
     *   sanalah kembaran itu muncul. Kelas ini sengaja tidak memakai cadangan
     *   nama: menebak dokter berarti menebak hari praktik, dan hari praktik
     *   yang salah akan disebut ke pasien lewat telepon.
     */
    private function berjadwalDi(string $doctorId, string $branchId): bool
    {
        return \Illuminate\Support\Facades\DB::table('raw_doctor_schedule')
            ->where('raw_doctor_id', $doctorId)
            ->where('raw_branch_id', $branchId)
            ->exists();
    }

    /** "10:00 – 20:00" dari slot pertama dan terakhir. */
    private function rentangJam(array $slots): ?string
    {
        if (! $slots) {
            return null;
        }

        $awal  = $slots[0]['time'] ?? null;
        $akhir = $slots[count($slots) - 1]['end_time'] ?? null;

        return $awal && $akhir ? $awal . ' – ' . $akhir : null;
    }
}
