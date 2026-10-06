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

        /*
         * Saringan "Status Pasien" dari prompt_jadwal_kontrol.md: Semua /
         * Active / Inactive. Kunci TERPISAH (`rutin`) dari saringan `status`
         * di bawah, dan itu disengaja — prompt memintanya "bekerja bareng
         * filter lain yang sudah ada, jangan hapus filter lama".
         *
         * ⚠ Bawaannya SEMUA, dan tidak ada cabang yang memasang
         *   `where sales_active = 1`. "Inactive" artinya TURUN KE BAWAH, bukan
         *   hilang — lihat OrtCase::SALES_PASIF.
         */
        if (filled($filter['rutin'] ?? null)) {
            match ($filter['rutin']) {
                'Active'   => $q->where('k.sales_active', OrtCase::SALES_AKTIF),
                'Inactive' => $q->where('k.sales_active', OrtCase::SALES_PASIF),
                default    => null,
            };
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

        // Sumbu yang sama seperti di dasar(). Tanpa kolom prefiks karena ini
        // kueri Eloquent atas satu tabel.
        if (filled($filter['rutin'] ?? null)) {
            match ($filter['rutin']) {
                'Active'   => $q->where('sales_active', OrtCase::SALES_AKTIF),
                'Inactive' => $q->where('sales_active', OrtCase::SALES_PASIF),
                default    => null,
            };
        }

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

        $q->select(self::kolomBaris());

        /*
         * ────────────────────────────────────────────────────────────────────
         * URUTAN BARIS
         * ────────────────────────────────────────────────────────────────────
         * prompt_jadwal_kontrol.md: "Pasien Active selalu di atas, Inactive
         * selalu di paling bawah (tetap tampil). Di dalam grup Active,
         * urutkan dari yang paling mendesak."
         *
         * ⚠ BERLAKU DI SEMUA TAB sejak 6 Okt 2026, termasuk "Semua".
         *
         * Sebelumnya tab "Semua" diurutkan `sch_date` demi aturan yang tertulis
         * di _tabel.blade.php — "'Semua' hanya DIURUTKAN Terlewat → Hari
         * ini/Minggu ini → Mendatang". Itu dibatalkan karena efeknya di layar,
         * dan Reza yang menunjukkannya: daftarnya terbaca seperti diurutkan
         * ABJAD.
         *
         * Ia tidak salah lihat. Sejak kolom "Jadwal" dilebur ke Last Visit,
         * `sch_date` TIDAK LAGI TAMPIL di layar — jadi kunci urut utamanya
         * tidak terlihat, dan yang tersisa sebagai pola yang bisa dibaca mata
         * justru pemecah serinya: nama. Terukur di cabang Bintaro, empat baris
         * berbagi jatuh tempo 30 Sep dan berbaris LIA · RAAYA · SITI DHIYA ·
         * SITI HALIMATU — alfabetis sempurna, tanpa sebab yang kelihatan.
         *
         * Obatnya bukan mengembalikan kolomnya, tapi mengurutkan pada apa yang
         * MEMANG TERLIHAT: badge umur di kolom Last Visit. Sesudah ini warna
         * badge menurun rapi dari merah ke kuning ke hijau dari atas ke bawah,
         * dan urutannya menerangkan dirinya sendiri tanpa kolom tambahan.
         *
         * Aturan "tidak dikelompokkan" di _tabel.blade.php TIDAK dilanggar —
         * yang berubah kunci urutnya, bukan ada/tidaknya kepala kelompok.
         *
         * sales_active dan pemecah seri berlaku di semua tab.
         */
        $q->orderByDesc('k.sales_active');

        {
            /*
             * Mendesak = SIKLUS TERLEWAT sebagai pecahan, bukan selisih hari.
             * Pasien berinterval 21 hari yang telat 10 hari (1,48 siklus)
             * lebih mendesak daripada pasien berinterval 60 hari yang telat 10
             * hari (1,17 siklus) — padahal "telat"-nya sama. Mengurutkan
             * dengan selisih hari akan menyamakan keduanya.
             *
             * Pecahan, BUKAN intdiv: pasien 1,9 siklus harus berdiri di atas
             * pasien 1,1 siklus, bukan sama-sama "1".
             *
             * ⚠ Jepitan LEAST/GREATEST-nya WAJIB sama dengan
             *   OrtCase::intervalKontrol(). Kalau salah satu diubah, yang lain
             *   ikut — sumpah dua-arah itu ditulis di OrtCase.php.
             *
             * ⚠ Ini EKSPRESI, jadi tidak bisa dilayani index, dan
             *   idx_ort_control_worklist berhenti MEMBERI urutan — LIMIT tidak
             *   lagi berhenti dini. Pada <=1.000 baris terbuka per cabang itu
             *   filesort di memori, di bawah 10 ms. Linear terhadap jumlah
             *   kasus aktif per cabang, bukan aman tanpa syarat.
             *
             * NULL last_visit_date naik ke ATAS: bracket terpasang tanpa satu
             * pun kunjungan tercatat adalah keadaan terburuk di himpunan ini,
             * dan OrtCase::isHilang() sudah memperlakukan NULL sebagai hilang.
             * Tanpa baris ini, DESC akan menerjunkannya ke dasar grup Active —
             * tepat ke tempat yang paling salah.
             */
            $q->orderByRaw('k.last_visit_date IS NOT NULL')
                ->orderByRaw(
                    'DATEDIFF(?, k.last_visit_date)'
                    . ' / LEAST(?, GREATEST(?, COALESCE(k.median_gap_days, ?))) DESC',
                    [$this->hariIni()->toDateString(), OrtCase::IRAMA_MAX_HARI,
                     OrtCase::IRAMA_MIN_HARI, OrtControl::USULAN_MAJU_HARI]
                );
        }

        /* `sch_date` turun jadi PEMECAH SERI, bukan kunci utama. Ia tetap
           berguna di situ: dua pasien dengan kemendesakan sama persis diurutkan
           oleh jatuh temponya, bukan oleh abjad. */
        return $q->orderBy('c.sch_date')
            ->orderBy('k.patient_name')
            // Pemecah seri UNIK, dan ini memperbaiki bug yang sudah ada:
            // sch_date + patient_name tidak unik, jadi baris bisa berpindah
            // halaman antar klik tanpa ada yang berubah.
            ->orderBy('c.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Dua angka untuk badge "· N Inactive, di bawah" di kaki/kepala daftar.
     *
     * ⚠ Kenapa badge ini WAJIB ada, bukan hiasan: dengan 50 baris per halaman,
     *   400 pasien Inactive berarti delapan halaman terakhir yang isinya
     *   Inactive semua — dan tidak ada yang akan membukanya. Mengurutkan
     *   mereka ke bawah menyembunyikan mereka dengan cara yang terasa
     *   disengaja. Tanpa angka yang bisa diklik, fitur ini adalah tombol
     *   "sembunyikan pasien" yang tidak diberi label demikian.
     *
     * Dihitung atas populasi yang SAMA dengan yang dirender (cabang, urgensi,
     * pencarian, dokter, status) TAPI tanpa saringan `rutin` itu sendiri —
     * kalau tidak, angkanya berbohong terhadap daftar di bawahnya.
     *
     * @return array{aktif:int, pasif:int}
     */
    public function hitungSalesAktif(string $placeId, string $urgensi = 'semua', array $filter = [], ?string $asal = null): array
    {
        unset($filter['rutin']);

        $baris = $this->terapkanUrgensi($this->dasar($placeId, $filter, $asal), $urgensi)
            ->select('k.sales_active', DB::raw('COUNT(*) AS n'))
            ->groupBy('k.sales_active')
            ->pluck('n', 'sales_active');

        return [
            'aktif' => (int) ($baris[OrtCase::SALES_AKTIF] ?? 0),
            'pasif' => (int) ($baris[OrtCase::SALES_PASIF] ?? 0),
        ];
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

    /**
     * Kolom yang dibawa SATU BARIS daftar kerja — satu sumber, dua pemakai.
     *
     * ⚠ Baris daftar ini stdClass dari query builder, BUKAN model: kolom yang
     *   tidak ikut di-select melempar "Undefined property", bukan null
     *   diam-diam. Sudah kena saat hasil follow-up mulai ditampilkan.
     *
     * Dipisah jadi metode 6 Okt 2026 karena panel baris kini dimuat sendiri
     * (lazy), dan panel yang di-select berbeda dari tabelnya akan menampilkan
     * fakta yang berbeda untuk baris yang sama.
     *
     * @return list<string>
     */
    public static function kolomBaris(): array
    {
        return [
            // ⚠ `k.id as ort_case_id`, BUKAN `c.ort_case_id`. Di dasar() keduanya
            //   nilainya identik (join-nya memang pada kolom itu), tapi
            //   barisKasus() memakai LEFT JOIN — dan di sana `c.ort_case_id`
            //   jadi NULL untuk pasien yang belum punya kontrol terbuka,
            //   sementara panel butuh id kasusnya untuk mengambil riwayat.
            'c.id', 'k.id as ort_case_id', 'c.seq', 'c.place_id', 'c.is_proposal',
            'c.sch_date', 'c.sch_source', 'c.sch_basis', 'c.sch_basis_n',
            'c.fu_date', 'c.fu_by', 'c.fu_channel', 'c.fu_result', 'c.fu_note',
            'c.reservation_id', 'c.rsv_date', 'c.rsv_status',
            'c.visit_date', 'c.unscheduled', 'c.outcome', 'c.closed_at',
            'k.patient_name', 'k.patient_phone', 'k.omnicare_pid',
            'k.bracket_type', 'k.bracket_arch', 'k.doctor_name', 'k.doctor_ref_id',
            'k.install_date', 'k.install_place_id', 'k.owner_place_id',
            'k.visit_count', 'k.control_count', 'k.avg_gap_days', 'k.median_gap_days',
            'k.gap_spread_pct',
            'k.last_visit_date', 'k.install_is_estimated',
            'k.sales_active',
        ];
    }

    /**
     * SATU baris daftar kerja — bahan panel yang dimuat belakangan.
     *
     * Gerbangnya sama dengan daftar(): `dasar()` sudah mengunci ke cabang ini,
     * jadi kontrol milik cabang lain mengembalikan null dan pemanggilnya
     * menjawab 404. Panel tidak boleh jadi celah membaca pasien orang.
     */
    public function satuBaris(string $placeId, string $controlId): ?object
    {
        return $this->dasar($placeId)
            ->where('c.id', $controlId)
            ->select(self::kolomBaris())
            ->first();
    }

    /**
     * SATU baris panel yang dibangun dari KASUS, bukan dari kontrol.
     *
     * ────────────────────────────────────────────────────────────────────────
     * KENAPA ADA DUA JALAN KE PANEL YANG SAMA
     * ────────────────────────────────────────────────────────────────────────
     * Daftar kerja menampilkan KONTROL TERBUKA — satu baris = satu kontrol, dan
     * satuBaris() cukup. Tab "Pasien di cabang lain" menampilkan KASUS, dan
     * kasus di sana sering BELUM punya kontrol terbuka sama sekali ("belum
     * dijadwalkan" di layar). Mencari panelnya lewat id kontrol berarti
     * pasien-pasien itu tidak pernah bisa dibuka.
     *
     * LEFT JOIN, jadi kolom kontrol bernilai NULL kalau memang belum ada.
     * Panel sudah tahan terhadap itu: $telat jadi false, $bolehBatalFu jadi
     * false, dan Profile + kedua riwayat semuanya berasal dari kasus.
     *
     * ⚠ TANPA gerbang cabang — sengaja. Pemanggilnya yang memutuskan, karena
     *   aturannya di sini BEDA dari daftar kerja: pasien yang sedang dipegang
     *   cabang lain boleh DILIHAT selama cabang pasangnya kita. Lihat
     *   OrthoController::panelBaris().
     */
    public function barisKasus(string $caseId): ?object
    {
        return DB::table('ort_case as k')
            ->leftJoin('ort_control as c', 'c.id', '=', 'k.open_control_id')
            ->where('k.id', $caseId)
            ->select(self::kolomBaris())
            ->first();
    }

    /**
     * Riwayat KUNJUNGAN per pasien — kolom "Kontrol" di panel baris.
     *
     * prompt_jadwal_kontrol.md (Phase 2) meminta bentuknya persis:
     *     1. 4 Okt 2026, Sunter, drg. Nama Dokter
     *     2. 1 Sep 2026, Bintaro, drg. Nama Dokter
     *
     * ⚠ Memuat SEMUA kontrol, bukan hanya yang dikunjungi — dan itu keputusan
     *   yang sempat salah sekali. Versi pertama menyaring `visit_date IS NOT
     *   NULL` supaya daftarnya murni "riwayat datang". Akibatnya kontrol
     *   TERBUKA hilang dari panel, dan bersamanya hilang pula satu-satunya
     *   tempat layar ini berkata "Belum ada reservasi" — tugas Sales yang
     *   paling sering. Kontrol yang belum dikunjungi memakai `sch_date` dan
     *   keadaannya dikatakan apa adanya, jadi tidak ada yang terbaca sebagai
     *   kunjungan yang tidak pernah terjadi.
     *
     * Nama dokternya per-baris, datang dari ort_control.doctor_ref_id yang
     * diisi tarikan malam. Baris lama yang belum sempat diisi backfill
     * (`ort:pull-visits --isi-dokter`) mengembalikan null, dan layar menulis
     * "—" di situ — bukan menyalin dokter kasus, yang akan membuat semua baris
     * terlihat ditangani orang yang sama padahal tidak diketahui.
     *
     * ⚠ Batasnya 20, naik dari 8 (6 Okt 2026). Daftar ini kini bergulir di
     *   dalam kolomnya — lima butir terlihat, sisanya digulir — jadi batas 8
     *   membuat gulirnya nyaris tak berguna. Panel dimuat belakangan dan
     *   ukurannya 13 KB, jadi dua belas butir tambahan itu murah.
     *
     * Satu kueri untuk SELURUH halaman, dipotong di PHP. Alasannya sama dengan
     * kontrolTerakhir(): "N terbaru per grup" butuh window function yang tidak
     * portabel atau derived table yang tidak ter-index.
     *
     * @return array<string, list<\App\Models\OrtControl>>  ort_case_id => kontrol, TERBARU DI ATAS
     */
    public function riwayatKontrol(array $caseIds, int $n = 20): array
    {
        if (empty($caseIds)) {
            return [];
        }

        // Diurutkan pada tanggal yang BERLAKU untuk baris itu: tanggal datang
        // kalau sudah datang, tanggal jadwal kalau belum. Mengurutkan pada
        // visit_date saja akan menerjunkan semua kontrol terbuka ke dasar,
        // padahal justru itu yang paling perlu dilihat lebih dulu.
        $semua = OrtControl::whereIn('ort_case_id', $caseIds)
            ->orderBy('ort_case_id')
            ->orderByRaw('COALESCE(visit_date, sch_date) DESC')
            ->orderByDesc('seq')
            ->get();

        $hasil = [];
        foreach ($semua as $c) {
            $hasil[$c->ort_case_id] ??= [];
            if (count($hasil[$c->ort_case_id]) < $n) {
                $hasil[$c->ort_case_id][] = $c;
            }
        }

        return $hasil;
    }

    /**
     * Riwayat FOLLOW-UP per pasien — kolom "Follow Up" di panel baris.
     *
     *     1. 30 Sep 2026, oleh Nama Sales
     *     2. 1 Sep 2026, oleh Nama Sales
     *
     * Sumbernya ort_followup, satu baris per pasien per hari. Sebelum tabel itu
     * ada, daftar ini tidak punya bahan sama sekali: ort_control.fu_date cuma
     * menyimpan follow-up TERAKHIR.
     *
     * ⚠ Dibungkus Schema::hasTable — SQL modul ini dijalankan manual, jadi ada
     *   jendela di mana kodenya sudah naik tapi tabelnya belum ada. Panelnya
     *   tetap harus terbuka di jendela itu; kolomnya kosong, bukan 500.
     *
     * @return array<string, list<\App\Models\OrtFollowUp>>  ort_case_id => follow-up, TERBARU DI ATAS
     */
    public function riwayatFollowUp(array $caseIds, int $n = 20): array
    {
        if (empty($caseIds) || ! \Illuminate\Support\Facades\Schema::hasTable('ort_followup')) {
            return [];
        }

        $semua = \App\Models\OrtFollowUp::whereIn('ort_case_id', $caseIds)
            ->orderBy('ort_case_id')
            ->orderByDesc('fu_date')
            ->get();

        $hasil = [];
        foreach ($semua as $f) {
            $hasil[$f->ort_case_id] ??= [];
            if (count($hasil[$f->ort_case_id]) < $n) {
                $hasil[$f->ort_case_id][] = $f;
            }
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
