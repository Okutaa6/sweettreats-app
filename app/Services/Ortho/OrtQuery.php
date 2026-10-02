<?php

namespace App\Services\Ortho;

use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Models\OrtReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lapisan kueri Tracking Ortho — pola app/Services/Omnicare/KomisiQuery.php.
 *
 * Tiga aturan yang dipegang seluruh kelas ini:
 *
 *  1. JOIN hanya antar tabel ort_*. Ke raw_* dan omnicare_* selalu kueri
 *     terpisah + whereIn — `JOIN raw_branch` adalah persis join yang meledak
 *     #1267 di prod (collation campur) padahal mulus di lokal.
 *
 *  2. Daftar kerja digerakkan dari ort_control, BUKAN ort_case. Penyaring
 *     paling selektif ada di sana (place_id + closed_at IS NULL memangkas ke
 *     belasan baris) dan ORDER BY-nya ikut index idx_ort_control_worklist yang
 *     sama. Dibalik = membaca ±600 kasus cabang sebelum membuang yang tertutup.
 *
 *  3. Antrean memakai kolom cache open_control_id, bukan whereDoesntHave.
 *     Versi relasi itu jadi subquery berkorelasi — sekali eksekusi per kasus,
 *     542x tiap buka halaman di Bintaro.
 */
class OrtQuery
{
    /**
     * Baris per halaman.
     *
     * Dulu 10, dan itu angka prototipe yang tak pernah diuji ke data
     * sungguhan: Bintaro punya 959 kasus aktif, jadi 10 berarti 96 halaman.
     * Mencari satu pasien lewat tombol "berikutnya" bukan pekerjaan yang bisa
     * diminta dari siapa pun. 50 membuatnya 20 halaman, dan baris ortho itu
     * ramping — satu nama, satu tanggal, tiga kolom status.
     */
    public const PER_HAL = 50;

    public function __construct(private OrtBranches $branches)
    {
    }

    /** Hari ini sebagai acuan. Dipisah supaya bisa dibekukan saat pengujian. */
    public function hariIni(): Carbon
    {
        return Carbon::today();
    }

    // ════════════════════════════════════════════════════════════════════
    // KARTU RINGKAS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Tiga kartu dalam DUA kueri, bukan enam.
     *
     * Kartu 1 & 2 berbagi penggerak yang sama (place_id + kontrol terbuka),
     * jadi satu pemindaian index mengisi keduanya lewat SUM(CASE). Kartu 3
     * datang dari ort_case — tabelnya beda, jadi kueri terpisah; jangan
     * dipaksa lewat UNION.
     */
    public function kartu(string $placeId): array
    {
        $hariIni = $this->hariIni()->toDateString();
        $plus6   = $this->hariIni()->copy()->addDays(6)->toDateString();
        $lewat14 = $this->hariIni()->copy()->subDays(OrtControl::TERLEWAT_PARAH_HARI)->toDateString();

        $r = DB::table('ort_control')
            ->whereNull('closed_at')
            ->where('place_id', $placeId)
            /*
             * ⚠ `is_proposal = 0` DIBUANG dari dua hitungan terlewat.
             *
             * Kolom Jadwal adalah jatuh tempo, dan jatuh tempo yang lewat
             * berarti pasien telat — siapa pun yang menuliskan tanggalnya.
             * Selama pengecualian ini ada, kartu Terlewat nyaris selalu nol
             * sekalipun puluhan pasien tertinggal berbulan-bulan, karena
             * hampir semua baris terbuka memang is_proposal = 1.
             *
             * Dua hitungan pertama TETAP mengecualikannya, dan itu disengaja:
             * "Jadwal minggu ini · N sudah reservasi · N belum" bercerita
             * tentang pekerjaan memesan, bukan tentang keterlambatan.
             */
            ->selectRaw(
                'COALESCE(SUM(sch_date BETWEEN ? AND ? AND is_proposal = 0), 0) AS minggu_ini,'
                . ' COALESCE(SUM(sch_date BETWEEN ? AND ? AND is_proposal = 0'
                . '   AND reservation_id IS NOT NULL AND COALESCE(rsv_status, "") <> "cancelled"), 0) AS sudah_rsv,'
                . ' COALESCE(SUM(sch_date < ?), 0) AS terlewat,'
                . ' COALESCE(SUM(sch_date < ?), 0) AS terlewat_lama',
                [$hariIni, $plus6, $hariIni, $plus6, $hariIni, $lewat14]
            )
            ->first();

        $antrean = OrtCase::aktif()->dipegang($placeId)->belumPunyaJadwal();
        $backlog = (clone $antrean)->count();
        $hilang  = (clone $antrean)->where(function ($q) {
            $q->whereNull('last_visit_date')
              ->orWhere('last_visit_date', '<', $this->hariIni()->copy()->subDays(OrtCase::HILANG_HARI));
        })->count();

        $aktif = OrtCase::aktif()->dipegang($placeId)->count();

        return [
            'minggu_ini'      => (int) $r->minggu_ini,
            'sudah_rsv'       => (int) $r->sudah_rsv,
            'belum_rsv'       => (int) $r->minggu_ini - (int) $r->sudah_rsv,
            'terlewat'        => (int) $r->terlewat,
            'terlewat_lama'   => (int) $r->terlewat_lama,
            'persen_terlewat' => $aktif > 0 ? round((int) $r->terlewat / $aktif * 100, 1) : 0.0,
            'backlog'         => $backlog,
            'hilang'          => $hilang,
            'aktif'           => $aktif,
        ];
    }

    /** Hitungan per sub-tab kegentingan — badge angka di segmented control. */
    public function hitungUrgensi(string $placeId, array $filter = [], ?string $asal = null): array
    {
        $hariIni = $this->hariIni()->toDateString();
        $plus6   = $this->hariIni()->copy()->addDays(6)->toDateString();

        $r = $this->dasar($placeId, $filter, $asal)
            ->selectRaw(
                'COUNT(*) AS semua,'
                . ' COALESCE(SUM(c.sch_date < ?), 0) AS terlewat,'
                . ' COALESCE(SUM(c.sch_date = ?), 0) AS hari_ini,'
                . ' COALESCE(SUM(c.sch_date BETWEEN ? AND ?), 0) AS minggu_ini,'
                . ' COALESCE(SUM(c.sch_date > ?), 0) AS mendatang',
                [$hariIni, $hariIni, $hariIni, $plus6, $plus6]
            )
            ->first();

        return [
            'semua'      => (int) $r->semua,
            'terlewat'   => (int) $r->terlewat,
            'hari-ini'   => (int) $r->hari_ini,
            'minggu-ini' => (int) $r->minggu_ini,
            'mendatang'  => (int) $r->mendatang,
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    // TAB LIST — daftar kerja harian
    // ════════════════════════════════════════════════════════════════════

    /**
     * Kueri dasar daftar kerja. JOIN ke ort_case aman (sesama ort_*, collation
     * sama) dan jadi lookup PK, bukan pemindaian.
     */
    private function dasar(string $placeId, array $filter = [], ?string $asalPlaceId = null)
    {
        $q = DB::table('ort_control as c')
            ->join('ort_case as k', 'k.id', '=', 'c.ort_case_id')
            ->where('c.place_id', $placeId)
            ->whereNull('c.closed_at')
            ->where('k.status', OrtCase::STATUS_AKTIF);

        // Mode cabang lain: $placeId = cabang yang sedang MEMEGANG, $asalPlaceId
        // = cabang kita. Dua-duanya perlu — tanpa saringan asal, tab "Sunter"
        // akan menampilkan seluruh pasien Sunter, bukan pasien kita yang
        // kebetulan kontrol di sana.
        if ($asalPlaceId !== null) {
            $q->where('k.install_place_id', $asalPlaceId);
        }

        if (filled($filter['q'] ?? null)) {
            $cari  = trim($filter['q']);
            $angka = preg_replace('/\D+/', '', $cari);

            // Nama atau telepon saja — tidak pernah nomor RM (aturan domain 18).
            // LIKE '%..%' tak bisa pakai index, tapi selalu berjalan DI DALAM
            // satu cabang (±600 baris) jadi ruang pindainya kecil.
            $q->where(function ($w) use ($cari, $angka) {
                $w->where('k.patient_name', 'like', '%' . $cari . '%');
                if (strlen($angka) >= 3) {
                    $w->orWhere('k.patient_phone', 'like', '%' . $angka . '%');
                }
            });
        }

        if (filled($filter['dokter'] ?? null)) {
            $q->where('k.doctor_ref_id', $filter['dokter']);
        }

        // Filter Status memakai bahasa layar (brief §6), diterjemahkan ke kolom.
        // "Terlewat" dan "Transfer" bukan nilai tersimpan — keduanya turunan.
        if (filled($filter['status'] ?? null)) {
            $hariIni = $this->hariIni()->toDateString();
            match ($filter['status']) {
                'Menunggu'        => $q->whereNull('c.fu_date')->whereNull('c.reservation_id')
                                       ->where('c.sch_date', '>=', $hariIni),
                'Sudah di-FU'     => $q->whereNotNull('c.fu_date')->whereNull('c.reservation_id'),

                /*
                 * Dua saringan KERJA HARIAN. Yang di atas menjawab "siapa yang
                 * sudah ditangani"; dua ini menjawab pertanyaan yang sebetulnya
                 * ditanyakan tiap pagi dan tiap sore.
                 *
                 * "Belum pernah di-FU" sengaja TIDAK memeriksa sch_date, beda
                 * dari "Menunggu" di atas yang membuang pasien terlewat. Yang
                 * terlewat justru yang paling perlu ditelepon — membuangnya
                 * dari daftar panggilan adalah persis cara pasien hilang.
                 *
                 * "Saya FU hari ini" mencocokkan fu_by ke nama penggunanya.
                 * fu_by memang nama tampilan, bukan id — jadi dua orang
                 * bernama sama akan saling melihat pekerjaan. Itu diterima:
                 * saringan ini untuk meninjau pekerjaan sendiri, bukan pagar
                 * akses, dan datanya toh sudah terlihat seluruhnya di kolom FU.
                 */
                'Belum pernah di-FU' => $q->whereNull('c.fu_date'),
                'Saya FU hari ini'   => filled($filter['saya'] ?? null)
                    ? $q->where('c.fu_date', $hariIni)->where('c.fu_by', $filter['saya'])
                    // Tanpa nama pengguna, saringan ini tidak punya arti. Lebih
                    // jujur mengosongkan daftar daripada diam-diam menampilkan
                    // follow-up orang lain sebagai pekerjaan sendiri.
                    : $q->whereRaw('1 = 0'),
                'Ada reservasi'   => $q->whereNotNull('c.reservation_id')
                                       ->where(fn ($w) => $w->whereNull('c.rsv_status')->orWhere('c.rsv_status', '<>', 'cancelled')),
                'Terlewat'        => $q->where('c.sch_date', '<', $hariIni),
                /*
                 * "Terlewat 2× atau lebih" = sudah SIKLUS_ESKALASI interval
                 * lewat sejak kunjungan terakhir — bukan ambang hari. Pasien
                 * berinterval 21 hari yang telat 45 hari sudah dua siklus;
                 * pasien berinterval 60 hari dengan telat yang sama baru
                 * setengah. Hitungannya di SQL supaya menumpang
                 * idx_ort_case_backlog (memuat last_visit_date), dan jepitan
                 * LEAST/GREATEST-nya WAJIB sama dengan OrtCase::intervalKontrol().
                 */
                'Terlewat 2× atau lebih' => $q->whereNotNull('k.last_visit_date')
                    ->whereRaw(
                        'DATEDIFF(?, k.last_visit_date) >= ? * LEAST(?, GREATEST(?, COALESCE(k.median_gap_days, ?)))',
                        [$hariIni, OrtCase::SIKLUS_ESKALASI, OrtCase::IRAMA_MAX_HARI,
                         OrtCase::IRAMA_MIN_HARI, OrtControl::USULAN_MAJU_HARI]
                    ),
                'Reservasi batal' => $q->where('c.rsv_status', 'cancelled'),
                // Bukan lagi sebuah STATUS — hampir semua baris terbuka memang
                // tanggalnya dihitung sistem. Tetap berguna sebagai saringan:
                // "mana yang belum pernah disentuh tangan sama sekali".
                'Belum disentuh tangan' => $q->where('c.is_proposal', 1),
                'Transfer'        => $q->whereColumn('c.place_id', '<>', 'k.install_place_id'),
                default           => null,
            };
        }

        if (filled($filter['dari'] ?? null)) {
            $q->where('c.sch_date', '>=', $filter['dari']);
        }

        if (filled($filter['sampai'] ?? null)) {
            $q->where('c.sch_date', '<=', $filter['sampai']);
        }

        return $q;
    }

    /** Terapkan sub-tab kegentingan ke kueri dasar. */
    private function terapkanUrgensi($q, string $urgensi)
    {
        $hariIni = $this->hariIni()->toDateString();
        $plus6   = $this->hariIni()->copy()->addDays(6)->toDateString();

        return match ($urgensi) {
            'terlewat'   => $q->where('c.sch_date', '<', $hariIni),
            'hari-ini'   => $q->where('c.sch_date', '=', $hariIni),
            'minggu-ini' => $q->whereBetween('c.sch_date', [$hariIni, $plus6]),
            'mendatang'  => $q->where('c.sch_date', '>', $plus6),
            default      => $q,
        };
    }

    /**
     * Kegentingan untuk kueri berbasis KASUS (tab Pasien Ortho & tab cabang
     * lain), dibaca dari kolom rollup ort_case.next_sch_date.
     *
     * Sengaja terpisah dari terapkanUrgensi(): yang itu menyaring
     * ort_control.sch_date lewat alias `c`, yang tidak ada di kueri kasus.
     * Ambangnya sengaja dibuat persis sama supaya angka di kedua tempat
     * tidak pernah berbeda untuk pasien yang sama.
     *
     * Kasus tanpa jadwal (next_sch_date NULL) tidak masuk keranjang mana pun
     * selain "Semua" — itu memang benar, mereka punya antrean sendiri.
     */
    private function terapkanUrgensiKasus($q, string $urgensi)
    {
        $hariIni = $this->hariIni()->toDateString();
        $plus6   = $this->hariIni()->copy()->addDays(6)->toDateString();

        return match ($urgensi) {
            'terlewat'   => $q->where('next_sch_date', '<', $hariIni),
            'hari-ini'   => $q->where('next_sch_date', '=', $hariIni),
            'minggu-ini' => $q->whereBetween('next_sch_date', [$hariIni, $plus6]),
            'mendatang'  => $q->where('next_sch_date', '>', $plus6),
            default      => $q,
        };
    }

    /**
     * Hitungan sub-tab kegentingan untuk kueri berbasis KASUS.
     *
     * "Semua" di sini = jumlah PASIEN, bukan jumlah jadwal — jadi angkanya
     * boleh berbeda dari hitungUrgensi() di tab List, dan itu memang benar:
     * pasien tanpa jadwal ikut terhitung di sini tapi tidak punya baris di
     * daftar kerja.
     */
    public function hitungUrgensiKasus(string $placeId, array $filter = [], ?string $asal = null): array
    {
        $hariIni = $this->hariIni()->toDateString();
        $plus6   = $this->hariIni()->copy()->addDays(6)->toDateString();

        $r = $this->dasarKasus($placeId, $filter, $asal)
            ->selectRaw(
                'COUNT(*) AS semua,'
                . ' COALESCE(SUM(next_sch_date < ?), 0) AS terlewat,'
                . ' COALESCE(SUM(next_sch_date = ?), 0) AS hari_ini,'
                . ' COALESCE(SUM(next_sch_date BETWEEN ? AND ?), 0) AS minggu_ini,'
                . ' COALESCE(SUM(next_sch_date > ?), 0) AS mendatang',
                [$hariIni, $hariIni, $hariIni, $plus6, $plus6]
            )
            ->first();

        return [
            'semua'      => (int) $r->semua,
            'terlewat'   => (int) $r->terlewat,
            'hari-ini'   => (int) $r->hari_ini,
            'minggu-ini' => (int) $r->minggu_ini,
            'mendatang'  => (int) $r->mendatang,
        ];
    }

    /**
     * Kueri kasus dasar untuk roster dan tab cabang lain, dengan saringan
     * pencarian/dokter yang sama seperti dasar().
     */
    /**
     * $rawat — sumbu STATUS PERAWATAN, terpisah dari sumbu kegentingan jadwal:
     *   'aktif'   (bawaan) perawatan berjalan — satu-satunya yang punya tugas
     *   'selesai'          perawatan sudah ditutup (Freedom, Lepas Bracket,
     *                      atau Tandai Selesai). Dibaca `status <> aktif`,
     *                      bukan `= selesai`, supaya kalau suatu hari ada yang
     *                      menulis STATUS_PUTUS / STATUS_PINDAH_KLINIK (hari
     *                      ini belum ada), pasiennya tidak lenyap dari layar.
     *   null               tanpa saringan status — hanya untuk hitungRoster().
     */
    private function dasarKasus(string $placeId, array $filter = [], ?string $asal = null, ?string $rawat = 'aktif')
    {
        $q = OrtCase::dipegang($placeId);

        if ($rawat === 'aktif') {
            $q->where('status', OrtCase::STATUS_AKTIF);
        } elseif ($rawat === 'selesai') {
            $q->where('status', '<>', OrtCase::STATUS_AKTIF);
        }

        if ($asal !== null) {
            $q->where('install_place_id', $asal);
        }

        if (filled($filter['q'] ?? null)) {
            $cari  = trim($filter['q']);
            $angka = preg_replace('/\D+/', '', $cari);
            $q->where(function ($w) use ($cari, $angka) {
                $w->where('patient_name', 'like', '%' . $cari . '%');
                if (strlen($angka) >= 3) {
                    $w->orWhere('patient_phone', 'like', '%' . $angka . '%');
                }
            });
        }

        if (filled($filter['dokter'] ?? null)) {
            $q->where('doctor_ref_id', $filter['dokter']);
        }

        return $q;
    }

    /** Halaman daftar kerja. 10 baris/halaman, terlewat di paling atas. */
    public function daftar(string $placeId, string $urgensi = 'semua', array $filter = [], int $perPage = self::PER_HAL, ?string $asal = null)
    {
        $q = $this->terapkanUrgensi($this->dasar($placeId, $filter, $asal), $urgensi);

        return $q->select([
            'c.id', 'c.ort_case_id', 'c.seq', 'c.place_id', 'c.is_proposal',
            'c.sch_date', 'c.sch_source', 'c.sch_basis', 'c.sch_basis_n',
            // fu_channel/result/note ikut: tabel memakai query builder (stdClass),
            // jadi kolom yang tidak di-select melempar "Undefined property" —
            // bukan null diam-diam. Sudah kena saat hasil FU mulai ditampilkan.
            'c.fu_date', 'c.fu_by', 'c.fu_channel', 'c.fu_result', 'c.fu_note',
            'c.reservation_id', 'c.rsv_date', 'c.rsv_status',
            'c.visit_date', 'c.unscheduled', 'c.outcome', 'c.closed_at',
            'k.patient_name', 'k.patient_phone', 'k.omnicare_pid',
            'k.bracket_type', 'k.bracket_arch', 'k.doctor_name', 'k.doctor_ref_id',
            'k.install_date', 'k.install_place_id', 'k.owner_place_id',
            'k.visit_count', 'k.control_count', 'k.avg_gap_days', 'k.median_gap_days',
            'k.gap_spread_pct',
            'k.last_visit_date', 'k.install_is_estimated',
        ])
            ->orderBy('c.sch_date')
            ->orderBy('k.patient_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Badge "Baru masuk dari <Cabang>" — pasien yang baru ditransfer ke sini
     * dan belum sempat di-follow-up. Begitu ada FU, badge-nya hilang sendiri.
     *
     * @return array<string,string> ort_case_id => place_id asal
     */
    public function masukDari(array $caseIds, string $placeId): array
    {
        if (empty($caseIds)) {
            return [];
        }

        $rows = DB::table('ort_transfer')
            ->whereIn('ort_case_id', $caseIds)
            ->where('to_place_id', $placeId)
            ->orderByDesc('transfer_date')
            ->get(['ort_case_id', 'from_place_id']);

        $hasil = [];
        foreach ($rows as $r) {
            $hasil[$r->ort_case_id] ??= (string) $r->from_place_id;
        }

        // Sudah di-follow-up berarti sudah disambut — badge tidak perlu lagi.
        $sudahFu = DB::table('ort_control')
            ->whereIn('ort_case_id', array_keys($hasil))
            ->whereNull('closed_at')
            ->whereNotNull('fu_date')
            ->pluck('ort_case_id')
            ->all();

        foreach ($sudahFu as $id) {
            unset($hasil[$id]);
        }

        return $hasil;
    }

    /**
     * Baris penutup di bawah baris "Usulan jadwal": kontrol sebelumnya selesai
     * bagaimana, dan siapa yang mengisinya.
     *
     * Inilah pengganti toast — ia menjawab "barisnya kok berubah?" tepat di
     * tempat pertanyaannya muncul, dan hilang sendiri setelah dikonfirmasi.
     *
     * @param  array<int,object>  $baris  baris hasil daftar()
     * @return array<string,array{teks:string,catatan:string,absen:bool}>
     */
    public function usulanSelesai(array $baris): array
    {
        $usulan = array_filter($baris, fn ($r) => (bool) $r->is_proposal);
        if (empty($usulan)) {
            return [];
        }

        $caseIds = array_map(fn ($r) => $r->ort_case_id, $usulan);

        $sebelum = OrtControl::whereIn('ort_case_id', $caseIds)
            ->whereNotNull('closed_at')
            ->orderBy('ort_case_id')
            ->orderByDesc('seq')
            ->get()
            ->groupBy('ort_case_id');

        $hasil = [];
        foreach ($usulan as $r) {
            $prev = $sebelum[$r->ort_case_id][0] ?? null;
            if (! $prev) {
                continue;
            }

            $absen = $prev->outcome === OrtControl::OUT_TIDAK_HADIR;

            $hasil[$r->id] = [
                'absen'   => $absen,
                'teks'    => $absen
                    ? 'kontrol #' . $prev->seq . ' ditutup — tidak hadir ' . $prev->sch_date->translatedFormat('j M')
                    : 'kontrol #' . $prev->seq . ' selesai — hadir ' . optional($prev->visit_date)->translatedFormat('j M'),
                'catatan' => match (true) {
                    $absen                                       => 'ditandai manual oleh Sales',
                    /* `unscheduled` = kunjungan ini tidak cocok dengan baris jadwal
                       mana pun di sistem. Ia TIDAK tahu apakah pasien memesan —
                       reservasi hidup di raw_cus_reservation dan hampir seluruhnya
                       belum dijodohkan. Teks lama "datang tanpa reservasi" mengaku
                       tahu hal itu. Satu dari lima tempat klaim yang sama hidup. */
                    (bool) $prev->unscheduled                    => 'tidak ada jadwal tercatat · terisi sendiri dari rekam medis',
                    $prev->visit_source === 'rekam_medis'        => 'terisi sendiri dari rekam medis',
                    default                                      => 'ditandai manual oleh Sales',
                },
            ];
        }

        return $hasil;
    }

    /** Empat kontrol terakhir satu kasus — isi panel baris yang dibuka. */
    public function kontrolTerakhir(array $caseIds, int $n = 4): array
    {
        if (empty($caseIds)) {
            return [];
        }

        $semua = OrtControl::whereIn('ort_case_id', $caseIds)
            ->orderBy('ort_case_id')
            ->orderByDesc('seq')
            ->get();

        $hasil = [];
        foreach ($semua as $c) {
            $hasil[$c->ort_case_id] ??= [];
            if (count($hasil[$c->ort_case_id]) < $n) {
                $hasil[$c->ort_case_id][] = $c;
            }
        }

        foreach ($hasil as $id => $baris) {
            $hasil[$id] = array_reverse($baris);
        }

        return $hasil;
    }

    // ════════════════════════════════════════════════════════════════════
    // ANTREAN PENJADWALAN — kartu ke-3
    // ════════════════════════════════════════════════════════════════════

    /**
     * Pasien yang belum punya kontrol terbuka, terurut paling lama menghilang.
     * Satu range scan di idx_ort_case_backlog — nol subquery, nol anti-join.
     */
    public function antrean(string $placeId, array $filter = [], int $perPage = self::PER_HAL, string $tingkat = 'semua')
    {
        $q = $this->terapkanTingkatAntrean(
            $this->saringAntrean(
                OrtCase::aktif()->dipegang($placeId)->belumPunyaJadwal(),
                $filter
            ),
            $tingkat
        );

        // NULL last_visit_date = belum pernah datang sama sekali → paling genting.
        return $q->orderByRaw('last_visit_date IS NOT NULL')
            ->orderBy('last_visit_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Saringan tingkat antrean. Disaring di KUERI, bukan setelah paginasi —
     * kalau dipangkas di PHP, nomor halaman dan jumlah barisnya berbohong.
     *
     * Ambangnya sama persis dengan OrtCase::isHilang(): belum pernah datang,
     * atau kunjungan terakhirnya lebih dari 180 hari lalu.
     */
    /**
     * ⚠ Ketiganya harus SALING LEPAS dan menutupi semuanya — angka sub-tab
     *   dijumlahkan dari sini, dan kalau ada yang tumpang tindih, "Semua"
     *   tidak sama dengan jumlah ketiganya. Pembacanya akan menyalahkan
     *   kuerinya, bukan ambangnya.
     */
    private function terapkanTingkatAntrean($q, string $tingkat)
    {
        $hilangSejak = $this->hariIni()->copy()->subDays(OrtCase::HILANG_HARI)->toDateString();
        $baruSejak   = $this->hariIni()->copy()->subDays(OrtCase::TARGET_GAP)->toDateString();

        return match ($tingkat) {
            'hilang' => $q->where(fn ($w) => $w->whereNull('last_visit_date')
                                               ->orWhere('last_visit_date', '<', $hilangSejak)),
            // Baru datang tapi tak punya jadwal berikutnya — hampir selalu
            // karena OrtUsulan sengaja tidak mengusulkan tanggal.
            'baru'   => $q->whereNotNull('last_visit_date')
                          ->where('last_visit_date', '>=', $baruSejak),
            'jarang' => $q->whereNotNull('last_visit_date')
                          ->where('last_visit_date', '>=', $hilangSejak)
                          ->where('last_visit_date', '<', $baruSejak),
            default  => $q,
        };
    }

    /**
     * Hitungan sub-tab antrean. Dihitung atas populasi yang sama dengan yang
     * dirender, jadi angkanya tidak bisa menunjuk baris yang tak ada di layar.
     */
    public function hitungAntrean(string $placeId, array $filter = []): array
    {
        $dasar = fn () => $this->saringAntrean(
            OrtCase::aktif()->dipegang($placeId)->belumPunyaJadwal(),
            $filter
        );

        return [
            'semua'  => $dasar()->count(),
            'hilang' => $this->terapkanTingkatAntrean($dasar(), 'hilang')->count(),
            'jarang' => $this->terapkanTingkatAntrean($dasar(), 'jarang')->count(),
            'baru'   => $this->terapkanTingkatAntrean($dasar(), 'baru')->count(),
        ];
    }

    /** Saringan pencarian & dokter untuk kueri antrean. */
    private function saringAntrean($q, array $filter)
    {
        if (filled($filter['q'] ?? null)) {
            $cari  = trim($filter['q']);
            $angka = preg_replace('/\D+/', '', $cari);
            $q->where(function ($w) use ($cari, $angka) {
                $w->where('patient_name', 'like', '%' . $cari . '%');
                if (strlen($angka) >= 3) {
                    $w->orWhere('patient_phone', 'like', '%' . $angka . '%');
                }
            });
        }

        if (filled($filter['dokter'] ?? null)) {
            $q->where('doctor_ref_id', $filter['dokter']);
        }

        return $q;
    }

    /**
     * Pecah antrean jadi dua tingkat kegentingan, urutan tetap.
     * Ambangnya definisi Sales sendiri, bukan karangan sistem.
     */
    /**
     * Tiga kelompok, dan yang KETIGA sempat dihapus lalu kembali.
     *
     * Revisi 3 membuangnya ("Baru selesai kontrol — tinggal dijadwalkan")
     * dengan alasan yang waktu itu benar: barisnya akan tinggal di tab List
     * sebagai usulan, jadi tak perlu tempat kedua. Begitu sistem boleh
     * memutuskan TIDAK memberi jatuh tempo, alasan itu gugur. Tanpa kelompok
     * ini, pasien yang baru selesai kontrol lenyap dari layar tanpa
     * penjelasan: persis keluhan yang Revisi 3 justru lahir untuk
     * memperbaikinya.
     *
     * ⚠ SEBABNYA TINGGAL SATU, dan komentar ini pernah menyebut tiga.
     *   Versi sebelumnya berbunyi "karena dokternya tak terjadwal, harinya
     *   penuh, atau klinik tutup" — ketiga pagar itu sudah dibuang bersama
     *   lantai MIN_MAJU_HARI, jadi tak satu pun bisa terjadi lagi. Satu-satunya
     *   sebab yang tersisa: pasien hilang lebih dari OrtCase::HILANG_HARI, dan
     *   itu keputusan beban kerja, bukan pagar jadwal.
     *
     * Urutannya sengaja: yang paling lama tak terlihat di atas.
     */
    public function kelompokkanAntrean($baris): array
    {
        $hilang = [];
        $jarang = [];
        $baru   = [];

        $batasBaru = $this->hariIni()->copy()->subDays(OrtCase::TARGET_GAP);

        foreach ($baris as $c) {
            if ($c->isHilang()) {
                $hilang[] = $c;
            } elseif ($c->last_visit_date && $c->last_visit_date->gte($batasBaru)) {
                // Baru saja datang, tapi belum punya jatuh tempo berikutnya —
                // biasanya karena tarikannya dijalankan dengan --tanpa-usulan,
                // atau karena baris jatuh temponya dihapus ort:rapikan-usulan.
                $baru[] = $c;
            } else {
                $jarang[] = $c;
            }
        }

        return [
            'hilang' => [
                'label' => 'Hilang lebih dari 6 bulan',
                'hint'  => 'kontrol terakhir lebih dari 180 hari lalu',
                'warna' => '#C0392B',
                'baris' => $hilang,
            ],
            'jarang' => [
                'label' => 'Jarang kontrol — kurang dari 2 kali per 6 bulan',
                'hint'  => 'definisi Sales',
                'warna' => '#B5701A',
                'baris' => $jarang,
            ],
            'baru' => [
                'label' => 'Baru selesai kontrol — tinggal dijadwalkan',
                'hint'  => 'belum punya jatuh tempo berikutnya',
                'warna' => '#2F8E55',
                'baris' => $baru,
            ],
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    // TAB CABANG LAIN — lihat-saja
    // ════════════════════════════════════════════════════════════════════

    /**
     * Pasien behel aktif per cabang — untuk pemilih cabang utama.
     *
     * Hanya dipanggil untuk role lintas cabang (Super Admin, Admin General,
     * Admin Backup, Finance). User yang terkunci ke satu cabang tidak pernah
     * membayar kueri ini: dropdown-nya memang tidak dirender.
     *
     * Dilayani prefix (status, owner_place_id) dari idx_ort_case_roster.
     *
     * @return array<string,int> place_id => jumlah
     */
    public function hitungPerCabang(): array
    {
        $rows = DB::table('ort_case')
            ->select('owner_place_id', DB::raw('COUNT(*) AS n'))
            ->where('status', OrtCase::STATUS_AKTIF)
            ->groupBy('owner_place_id')
            ->get();

        $hasil = [];
        foreach ($rows as $r) {
            $hasil[(string) $r->owner_place_id] = (int) $r->n;
        }

        return $hasil;
    }

    /**
     * Kolom mana yang BELUM PERNAH terisi sama sekali di cabang ini.
     *
     * Brief §kamus menetapkan `X` dipertahankan apa adanya — "sel kosong
     * terasa seperti data hilang, X terasa seperti fakta", dan per baris itu
     * memang benar. Yang menyesatkan keadaan lain: saat SELURUH kolom berisi X,
     * ia berhenti terbaca sebagai "belum terjadi" dan mulai terbaca sebagai
     * "Sales tidak bekerja". Padahal Sales sudah menelepon puluhan orang —
     * catatannya saja yang masih di Google Sheets.
     *
     * Jadi X tetap, dan keadaannya yang dijelaskan sekali di atas tabel.
     *
     * @return array{fu: bool, rsv: bool} true = belum pernah ada isinya
     */
    public function kolomBelumDipakai(string $placeId): array
    {
        $ada = fn (string $kolom) => OrtControl::where('place_id', $placeId)
            ->whereNotNull($kolom)->exists();

        return ['fu' => ! $ada('fu_date'), 'rsv' => ! $ada('reservation_id')];
    }

    /** Berapa pasien kami yang dipegang tiap cabang lain — badge di tab cabang. */
    public function hitungPerCabangLain(string $homePlaceId): array
    {
        $rows = DB::table('ort_case')
            ->select('owner_place_id', DB::raw('COUNT(*) AS n'))
            ->where('status', OrtCase::STATUS_AKTIF)
            ->where('install_place_id', $homePlaceId)
            ->whereColumn('owner_place_id', '<>', 'install_place_id')
            ->groupBy('owner_place_id')
            ->get();

        $hasil = [];
        foreach ($rows as $r) {
            $hasil[(string) $r->owner_place_id] = (int) $r->n;
        }

        return $hasil;
    }

    /**
     * Isi tab cabang lain. Ini SATU-SATUNYA tempat di halaman yang menembus
     * kunci cabang — penyaringnya bukan "cabang aktif", melainkan "pasien yang
     * cabang pasangnya kita, tapi penagihnya bukan".
     */
    public function diCabangLain(string $homePlaceId, string $tujuanPlaceId, int $perPage = self::PER_HAL, string $urgensi = 'semua', array $filter = [])
    {
        $q = $this->terapkanUrgensiKasus(
            $this->dasarKasus($tujuanPlaceId, $filter, $homePlaceId),
            $urgensi
        );

        return $q->orderByRaw('next_sch_date IS NULL DESC')
            ->orderBy('next_sch_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Pengingat TERAKHIR per kasus, untuk chip "Diingatkan 18 Jan".
     *
     * Sengaja dua kueri, bukan latest-per-group: window function tidak portabel
     * dan derived table tidak ter-index. Halamannya sudah dipangkas ke <=10
     * baris, jadi kueri kedua ini murah.
     *
     * @return array<string, OrtReminder> ort_case_id => pengingat terakhir
     */
    public function pengingatTerakhir(array $caseIds): array
    {
        if (empty($caseIds)) {
            return [];
        }

        $hasil = [];
        foreach (OrtReminder::whereIn('ort_case_id', $caseIds)->orderByDesc('reminded_at')->get() as $r) {
            $hasil[$r->ort_case_id] ??= $r;   // yang pertama = yang terbaru
        }

        return $hasil;
    }

    /**
     * Mandek di cabang lain: jadwal terlewat >14 hari, atau tak dijadwalkan
     * >30 hari sejak kunjungan terakhir (termasuk yang belum pernah datang).
     *
     * ⚠ Di LAYAR keadaan ini disebut "Belum ditangani <Cabang>", bukan
     *   "tertinggal" — kata itu terbaca ambigu oleh Sales. Nama method sengaja
     *   dibiarkan supaya diff-nya kecil; kalau suatu hari diganti, ganti juga
     *   $tertinggal di OrtQuery::cabangLain() dan _cabang_lain.blade.php.
     *
     * Ini juga satu-satunya penentu apakah tombol "Ingatkan" muncul. Tanpa
     * saringan ini, Sales akan memberondong cabang tetangga untuk pasien yang
     * sebenarnya baik-baik saja.
     */
    public function tertinggal(OrtCase $case): bool
    {
        $hariIni = $this->hariIni();

        if ($case->next_sch_date) {
            return $case->next_sch_date->lt($hariIni->copy()->subDays(OrtControl::TERLEWAT_PARAH_HARI));
        }

        return $case->last_visit_date === null
            || $case->last_visit_date->lt($hariIni->copy()->subDays(30));
    }

    // ════════════════════════════════════════════════════════════════════
    // TAB PASIEN ORTHO — roster per orang
    // ════════════════════════════════════════════════════════════════════

    /**
     * Daftar per pasien, 10 per halaman, terlewat di atas. Satu range scan di
     * idx_ort_case_roster; kontrol terbuka di-eager-load supaya kartu bisa
     * menampilkan chip status tanpa N+1.
     */
    public function roster(string $placeId, array $filter = [], int $perPage = self::PER_HAL, string $urgensi = 'semua', ?string $asal = null, string $rawat = 'aktif')
    {
        $q = $this->terapkanUrgensiKasus(
            $this->dasarKasus($placeId, $filter, $asal, $rawat)->with('openControl'),
            $urgensi
        );

        // Mode Selesai: yang paling baru ditutup di atas — itu yang paling
        // mungkin ditanyakan Sales ("tadi malam siapa yang otomatis selesai?").
        if ($rawat === 'selesai') {
            return $q->orderByRaw('finished_at IS NULL')
                ->orderByDesc('finished_at')
                ->orderBy('patient_name')
                ->paginate($perPage)
                ->withQueryString();
        }

        return $q->orderByRaw('next_sch_date IS NULL')
            ->orderBy('next_sch_date')
            ->orderBy('patient_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Dua angka untuk pemilah Aktif | Selesai di kepala tab Pasien Ortho.
     * Dihitung atas populasi yang SAMA dengan yang dirender (cabang, asal,
     * pencarian, dokter) — tanpa saringan status — supaya angkanya tidak
     * berbohong terhadap daftar di bawahnya.
     */
    public function hitungRoster(string $placeId, array $filter = [], ?string $asal = null): array
    {
        $r = $this->dasarKasus($placeId, $filter, $asal, null)
            ->selectRaw(
                'COALESCE(SUM(status = ?), 0) AS aktif, COALESCE(SUM(status <> ?), 0) AS selesai',
                [OrtCase::STATUS_AKTIF, OrtCase::STATUS_AKTIF]
            )
            ->first();

        return ['aktif' => (int) $r->aktif, 'selesai' => (int) $r->selesai];
    }

    // ════════════════════════════════════════════════════════════════════
    // TAB KALENDER — jadwal kontrol (SCH), bukan kunjungan
    // ════════════════════════════════════════════════════════════════════

    /**
     * Isi grid bulanan 6×7. Mengembalikan batas grid dan jadwal terbuka yang
     * jatuh di dalamnya, dikelompokkan per tanggal.
     *
     * Satu kueri di idx_ort_control_worklist (place_id + closed_at + rentang
     * sch_date) — grid selalu ≤42 hari jadi rentangnya sempit.
     */
    public function kalender(
        string $placeId,
        Carbon $bulan,
        array $filter = [],
        string $urgensi = 'semua',
        ?string $asal = null,
    ): array {
        $awalBulan = $bulan->copy()->startOfMonth();
        $awalGrid  = $awalBulan->copy()->subDays($awalBulan->dayOfWeek);   // mundur ke Minggu
        $akhirGrid = $awalGrid->copy()->addDays(41);

        $rows = $this->dasar($placeId, $filter, $asal)
            ->whereBetween('c.sch_date', [$awalGrid->toDateString(), $akhirGrid->toDateString()])
            ->select(['c.id', 'c.ort_case_id', 'c.seq', 'c.sch_date', 'c.is_proposal',
                      'c.fu_date', 'c.reservation_id', 'c.rsv_status',
                      'k.patient_name', 'k.doctor_name'])
            ->orderBy('c.sch_date')
            ->orderBy('k.patient_name')
            ->get();

        // Hitungan sub-tab dihitung dari baris grid INI, bukan kueri global:
        // grid hanya 42 hari, jadi hitungan global akan menampilkan angka yang
        // tidak ada wujudnya di layar saat pengguna menggulir ke bulan lain.
        $hariIni = $this->hariIni()->toDateString();
        $plus6   = $this->hariIni()->copy()->addDays(6)->toDateString();

        // ⚠ `! $r->is_proposal` dibuang dari 'hari-ini' dan 'minggu-ini'.
        //   Kalender ini papan BEBAN KERJA, dan beban itu ada entah tanggalnya
        //   dihitung sistem atau digeser tangan. Dulu keranjangnya menghitung
        //   satu populasi sementara gridnya menggambar populasi lain.
        $keranjang = fn ($r, string $u) => match ($u) {
            'terlewat'   => $r->sch_date < $hariIni,
            'hari-ini'   => $r->sch_date === $hariIni,
            'minggu-ini' => $r->sch_date >= $hariIni && $r->sch_date <= $plus6,
            'mendatang'  => $r->sch_date > $plus6,
            default      => true,
        };

        $hitung = ['semua' => $rows->count()];
        foreach (['terlewat', 'hari-ini', 'minggu-ini', 'mendatang'] as $u) {
            $hitung[$u] = $rows->filter(fn ($r) => $keranjang($r, $u))->count();
        }

        $tampil = $urgensi === 'semua'
            ? $rows
            : $rows->filter(fn ($r) => $keranjang($r, $urgensi));

        $perHari = [];
        foreach ($tampil as $r) {
            $perHari[$r->sch_date][] = $r;
        }

        return [
            'bulan'     => $awalBulan,
            'awalGrid'  => $awalGrid,
            'akhirGrid' => $akhirGrid,
            'perHari'   => $perHari,
            'total'     => $tampil->count(),
            'hitung'    => $hitung,
        ];
    }

    /**
     * Isi drawer harian: jadwal terbuka pada satu tanggal + jam reservasinya.
     * Jam diambil lewat whereIn ke raw_cus_reservation, TANPA join.
     */
    public function hari(string $placeId, Carbon $tgl, array $filter = [], ?string $asal = null): array
    {
        $rows = $this->dasar($placeId, $filter, $asal)
            ->where('c.sch_date', $tgl->toDateString())
            ->select(['c.id', 'c.ort_case_id', 'c.seq', 'c.sch_date', 'c.is_proposal',
                      'c.fu_date', 'c.reservation_id', 'c.rsv_status', 'c.rsv_date',
                      'k.patient_name', 'k.doctor_name', 'k.patient_phone'])
            ->orderBy('k.patient_name')
            ->get();

        $rsvIds = $rows->pluck('reservation_id')->filter()->all();
        $jam    = [];
        if ($rsvIds && \Illuminate\Support\Facades\Schema::hasTable('raw_cus_reservation')) {
            foreach (DB::table('raw_cus_reservation')->whereIn('id', $rsvIds)->get(['id', 'start_hour', 'end_hour']) as $v) {
                $jam[$v->id] = [$v->start_hour, $v->end_hour];
            }
        }

        // Yang punya jam di atas, lalu urut jam; sisanya di bawah.
        $urut = $rows->sortBy(fn ($r) => ($jam[$r->reservation_id][0] ?? '99:99') . $r->patient_name)->values();

        return ['baris' => $urut, 'jam' => $jam];
    }

    // ════════════════════════════════════════════════════════════════════
    // PENDUKUNG
    // ════════════════════════════════════════════════════════════════════

    /** Dokter yang benar-benar punya pasien ortho di cabang ini — isi dropdown filter. */
    public function dokterDiCabang(string $placeId): array
    {
        $rows = DB::table('ort_case')
            ->select('doctor_ref_id', 'doctor_name')
            ->where('status', OrtCase::STATUS_AKTIF)
            ->where('owner_place_id', $placeId)
            ->whereNotNull('doctor_ref_id')
            ->groupBy('doctor_ref_id', 'doctor_name')
            ->orderBy('doctor_name')
            ->get();

        $hasil = [];
        foreach ($rows as $r) {
            $hasil[(string) $r->doctor_ref_id] = (string) $r->doctor_name;
        }

        return $hasil;
    }
}
