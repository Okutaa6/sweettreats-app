<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * OrtCase — satu baris = satu episode perawatan behel.
 *
 * Satu orang bisa punya dua episode (lepas, lalu pasang lagi). Tanggal pasang,
 * cabang pasang, dan jenis bracket melekat ke EPISODE, bukan ke orang — itu
 * sebabnya tabel ini berdiri sendiri dan bukan kolom tambahan di raw_customer.
 *
 * IDENTITAS
 *   omnicare_pid    jangkar utama. NULL = pasien belum tertaut ke rekam medis;
 *                   kasusnya TETAP dibuat dan ditandai di UI, supaya tidak
 *                   hilang diam-diam seperti di sheet lama.
 *   raw_customer_id FK app-level, nullable. Nama & telepon di-snapshot supaya
 *                   kartu tetap terender tanpa lookup.
 *
 * KOLOM ROLLUP (owner_place_id … rollup_at)
 *   Turunan yang DISIMPAN. Bukan demi kecepatan render, tapi karena tiganya
 *   dipakai sebagai PENYARING:
 *     open_control_id IS NULL → antrean "Belum punya jadwal"
 *     visit_count_180d < 2    → tingkat "Jarang kontrol" (definisi Sales)
 *     owner_place_id          → pasien milik cabang mana
 *   Menyaring pada nilai yang dihitung saat baca = full scan tiap buka halaman.
 *
 *   ⚠ HANYA App\Services\Ortho\OrtRollup yang boleh menulis kolom itu. Jalur
 *     tulis yang lupa memanggilnya membuat halaman bohong TANPA error.
 *     `ort:rollup --verify` membandingkannya dengan hitungan sebenarnya.
 *
 * Lihat database/sql/2026_09_23_ort_tracking_schema.sql.
 */
class OrtCase extends Model
{
    protected $table = 'ort_case';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'omnicare_pid', 'raw_customer_id', 'patient_name', 'patient_phone',
        'install_date', 'install_place_id', 'install_is_estimated',
        'bracket_type', 'bracket_arch',
        'doctor_ref_id', 'doctor_name',
        'status', 'finished_at',
        'owner_place_id', 'open_control_id', 'next_sch_date', 'next_is_proposal',
        'last_visit_date', 'last_visit_place_id',
        'control_count', 'visit_count', 'visit_count_180d',
        'avg_gap_days', 'median_gap_days', 'gap_spread_pct',
        'branches_visited', 'rollup_at',
        'seed_source', 'note', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'install_date'         => 'date',
        'install_is_estimated' => 'boolean',
        'finished_at'      => 'date',
        'next_sch_date'    => 'date',
        'last_visit_date'  => 'date',
        'next_is_proposal' => 'boolean',
        'control_count'    => 'integer',
        'visit_count'      => 'integer',
        'visit_count_180d' => 'integer',
        'avg_gap_days'     => 'integer',
        'median_gap_days'  => 'integer',
        'gap_spread_pct'   => 'integer',
        'rollup_at'        => 'datetime',
    ];

    /** Status episode. Perawatan berhenti hanya lewat salah satu dari ini. */
    public const STATUS_AKTIF        = 'aktif';
    public const STATUS_SELESAI      = 'selesai';        // bracket dilepas
    public const STATUS_PUTUS        = 'putus';          // berhenti di tengah
    public const STATUS_PINDAH_KLINIK = 'pindah_klinik'; // keluar dari Sweet Treats

    /** Ambang "Hilang" dan "Jarang kontrol" — definisi Sales, jangan diubah diam-diam. */
    public const HILANG_HARI      = 180;
    public const JARANG_MIN_VISIT = 2;

    /** Target jarak antar kontrol (hari). Wajar 28–45. */
    public const TARGET_GAP = 30;

    /**
     * Berapa SELANG minimum sebelum irama pasien boleh dipercaya.
     *
     * Dua selang berarti tiga kunjungan. Di bawah itu "median" cuma satu
     * angka tunggal yang menyamar jadi statistik — satu kunjungan yang
     * kebetulan telat sudah menentukan seluruh jadwal pasien ke depan.
     */
    public const IRAMA_MIN_SELANG = 2;

    /**
     * Batas waras irama (hari). Di luar ini yang terukur bukan irama.
     *
     * Bawah 21: kontrol ortho di bawah tiga minggu tidak lazim; biasanya itu
     * kunjungan perbaikan bracket lepas yang kebetulan terekam sebagai
     * kontrol. Atas 60: selang dua bulan ke atas bukan irama melainkan
     * kelalaian — meneruskannya berarti sistem ikut melembagakan
     * keterlambatan pasien alih-alih menjadwalkannya kembali.
     */
    public const IRAMA_MIN_HARI = 21;

    public const IRAMA_MAX_HARI = 60;

    /**
     * Ambang irama RAPAT: sebaran selang, dalam persen median.
     *
     * Rumusnya (max − min) / median <= 0,5, dari handoff HUB 1 Okt, dan sudah
     * diukur di prod: hanya **24%** pasien yang memenuhinya. Angka itu yang
     * mengikat seluruh rancangan — untuk 76% sisanya, tanggal yang kita hitung
     * adalah tebakan dua minggu dan tidak pantas ditawarkan lewat tombol
     * sekali klik.
     *
     * ⚠ JANGAN memakai banyaknya selang (`sch_basis_n`) sebagai pengganti.
     *   Itu mengukur banyaknya data, bukan konsistensinya: pasien berselang
     *   20·90·25·110 punya LEBIH BANYAK selang daripada pasien 28·28·28, tapi
     *   iramanya jauh lebih tidak bisa ditebak. Ukuran lama justru menilai
     *   yang liar sebagai yang paling meyakinkan.
     */
    public const IRAMA_RAPAT_PCT = 50;

    /** Butuh berapa selang sebelum sebuah irama boleh disebut RAPAT. */
    public const IRAMA_RAPAT_MIN_SELANG = 3;

    /** Tingkat keyakinan irama — nilai yang disimpan di ort_control.sch_basis. */
    public const BASIS_RAPAT   = 'irama_rapat';
    public const BASIS_LONGGAR = 'irama_longgar';
    public const BASIS_DEFAULT = 'default';

    /**
     * MEDIAN selang antar kunjungan — irama pasien.
     *
     * Median, bukan rata-rata, dan itu bukan selera. `avg_gap_days` yang
     * sudah ada tertarik pencilan: satu kali pasien hilang 8 bulan lalu
     * kembali sudah cukup menggeser rata-ratanya puluhan hari, dan dari situ
     * seluruh usulan berikutnya ikut melar. Median tidak bergeming.
     *
     * Dipakai DUA tempat yang harus selalu sepakat — OrtRollup menyimpannya
     * ke kolom untuk ditampilkan, OrtUsulan menghitungnya segar saat
     * mengusulkan tanggal. Karena itu rumusnya tinggal di sini, satu salinan.
     *
     * Yang dikembalikan termasuk SEBARANNYA. Versi pertama fungsi ini
     * membuang array selangnya begitu median diambil, dan akibatnya tidak ada
     * satu pun tempat di repo yang bisa membedakan pasien rajin tiap 30 hari
     * dari pasien 20·90·25·110 yang mediannya kebetulan mirip.
     *
     * @param  iterable<CarbonInterface|string>  $tanggal  kunjungan, urut menaik
     * @return array{median: ?int, selang: int, min: ?int, max: ?int, sebar: ?int, basis: string}
     *         median null = belum cukup selang · sebar = persen median
     */
    public static function irama(iterable $tanggal): array
    {
        $hari    = [];
        $sebelum = null;

        foreach ($tanggal as $t) {
            if ($t === null) {
                continue;
            }

            $kini = $t instanceof CarbonInterface
                ? Carbon::instance($t)->startOfDay()
                : Carbon::parse($t)->startOfDay();

            if ($sebelum !== null) {
                $selisih = $sebelum->diffInDays($kini);
                // Dua kunjungan di hari yang sama bukan selang — itu satu
                // kunjungan yang terekam dua kali, atau kontrol + perbaikan
                // bracket yang lepas di hari yang sama.
                if ($selisih > 0) {
                    $hari[] = $selisih;
                }
            }

            $sebelum = $kini;
        }

        $n = count($hari);
        if ($n < self::IRAMA_MIN_SELANG) {
            return [
                'median' => null, 'selang' => $n,
                'min' => null, 'max' => null, 'sebar' => null,
                'basis' => self::BASIS_DEFAULT,
            ];
        }

        sort($hari);
        $median = (int) ($n % 2
            ? $hari[intdiv($n, 2)]
            : round(($hari[$n / 2 - 1] + $hari[$n / 2]) / 2));

        $min = (int) $hari[0];
        $max = (int) $hari[$n - 1];

        // Sebaran dalam PERSEN median, dibulatkan. Disimpan sebagai persen
        // supaya tidak butuh kolom pecahan dan tetap terbaca orang saat
        // dikueri tangan: 0 = selalu sama persis, 50 = batas rapat, 300 = liar.
        $sebar = $median > 0 ? (int) round(($max - $min) / $median * 100) : null;

        $rapat = $n >= self::IRAMA_RAPAT_MIN_SELANG
              && $sebar !== null
              && $sebar <= self::IRAMA_RAPAT_PCT;

        return [
            'median' => $median,
            'selang' => $n,
            'min'    => $min,
            'max'    => $max,
            'sebar'  => $sebar,
            'basis'  => $rapat ? self::BASIS_RAPAT : self::BASIS_LONGGAR,
        ];
    }

    // ── Relasi. Dipakai lewat with(); JANGAN ->join() ke luar keluarga ort_*
    //    (collation prod campur, lihat header berkas SQL).

    public function controls()
    {
        return $this->hasMany(OrtControl::class, 'ort_case_id')->orderBy('seq');
    }

    public function openControl()
    {
        return $this->belongsTo(OrtControl::class, 'open_control_id');
    }

    public function transfers()
    {
        return $this->hasMany(OrtTransfer::class, 'ort_case_id')->orderBy('transfer_date');
    }

    public function reminders()
    {
        return $this->hasMany(OrtReminder::class, 'ort_case_id')->orderByDesc('reminded_at');
    }

    public function customer()
    {
        return $this->belongsTo(RawCustomer::class, 'raw_customer_id');
    }

    // ── Scope

    public function scopeAktif($q)
    {
        return $q->where('status', self::STATUS_AKTIF);
    }

    /** Pasien yang ditagih cabang ini. */
    public function scopeDipegang($q, string $placeId)
    {
        return $q->where('owner_place_id', $placeId);
    }

    /**
     * Antrean penjadwalan: belum punya kontrol terbuka.
     *
     * Sengaja membaca kolom cache, BUKAN `whereDoesntHave` — versi relasi itu
     * jadi subquery berkorelasi yang dieksekusi sekali per kasus (542× tiap
     * buka halaman di Bintaro).
     */
    public function scopeBelumPunyaJadwal($q)
    {
        return $q->whereNull('open_control_id');
    }

    /** Pasien kami yang sedang dipegang cabang lain — isi tab cabang lain. */
    public function scopeDiCabangLain($q, string $homePlaceId)
    {
        return $q->where('install_place_id', $homePlaceId)
                 ->where('owner_place_id', '<>', $homePlaceId);
    }

    // ── Turunan untuk tampilan

    public function isTertaut(): bool
    {
        return filled($this->omnicare_pid);
    }

    /** Sudah berapa lama menghilang, dalam hari. NULL = belum pernah datang. */
    public function hariSejakKunjunganTerakhir(): ?int
    {
        return $this->last_visit_date?->diffInDays(now()->startOfDay());
    }

    public function isHilang(): bool
    {
        $hari = $this->hariSejakKunjunganTerakhir();

        return $hari === null || $hari > self::HILANG_HARI;
    }

    public function isJarangKontrol(): bool
    {
        return $this->visit_count_180d < self::JARANG_MIN_VISIT;
    }

    /** Daftar place_id cabang yang pernah didatangi, dari kolom CSV cache. */
    public function branchesVisited(): array
    {
        return array_values(array_filter(explode(',', (string) $this->branches_visited)));
    }
}
