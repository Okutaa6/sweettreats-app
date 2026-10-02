<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * OrtControl — satu baris = satu jadwal kontrol.
 *
 * ATURAN DOMAIN YANG MENYEDERHANAKAN SEGALANYA:
 *   satu kasus punya PALING BANYAK SATU kontrol terbuka (closed_at IS NULL).
 * Karena itu pola "ambil baris terakhir per grup" tidak pernah dibutuhkan di
 * modul ini — cukup scope terbuka(). Aturannya dijaga OrtScheduler, bukan
 * database (MySQL tidak punya UNIQUE parsial); `ort:rollup --verify`
 * melaporkan kasus yang melanggar.
 *
 * TANGGA STATUS — satu tombol per baris, selalu menunjukkan anak tangga
 * berikutnya:
 *   Menunggu → (fu_date terisi)         Sudah di-FU
 *            → (reservation_id terisi)  Ada reservasi
 *            → (visit_date terisi)      Hadir          ← diisi MESIN
 *            → (ditutup manusia)        Tidak hadir
 *
 * KOLOM DATANG DIISI MESIN. Sales tidak punya tombolnya sama sekali — kolom
 * ini datang dari cermin rekam medis lewat `ort:pull-visits` tiap malam.
 * Sebaliknya, "tidak hadir" SELALU manusia: ketidakhadiran itu kesimpulan dari
 * ketiadaan, dan kalau mesin menutupnya, pasien terlantar otomatis dapat
 * tanggal baru lalu keluar dari kartu Terlewat — jejak penelantarannya terhapus.
 *
 * KOLOM RESERVASI hanya penunjuk + snapshot. Jam, dokter, layanan, dan aturan
 * anti-bentrok semuanya milik modul Reservasi.
 *
 * Lihat database/sql/2026_09_23_ort_tracking_schema.sql.
 */
class OrtControl extends Model
{
    protected $table = 'ort_control';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'ort_case_id', 'seq', 'place_id', 'is_proposal',
        'sch_date', 'sch_source', 'sch_note', 'sch_basis', 'sch_basis_n',
        'fu_date', 'fu_channel', 'fu_by', 'fu_result', 'fu_note',
        'reservation_id', 'rsv_date', 'rsv_status', 'rsv_synced_at',
        'visit_date', 'visit_source', 'visit_confidence',
        'omnicare_trx_id', 'omnicare_encounter_id', 'visit_filled_at', 'unscheduled',
        'outcome', 'closed_at', 'closed_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'seq'             => 'integer',
        'is_proposal'     => 'boolean',
        'unscheduled'     => 'boolean',
        'sch_date'        => 'date',
        'fu_date'         => 'date',
        'rsv_date'        => 'date',
        'visit_date'      => 'date',
        'rsv_synced_at'   => 'datetime',
        'visit_filled_at' => 'datetime',
        'closed_at'       => 'datetime',
    ];

    /** Bagaimana tanggal jadwal ini lahir. */
    public const SCH_MANUAL      = 'manual';      // diketik Sales
    public const SCH_SISTEM      = 'sistem';      // usulan +30 hari
    public const SCH_REKAM_MEDIS = 'rekam_medis'; // rekonstruksi penyemaian

    /** Bagaimana kontrol ini ditutup. */
    public const OUT_HADIR          = 'hadir';
    public const OUT_TIDAK_HADIR    = 'tidak_hadir';
    public const OUT_DIJADWAL_ULANG = 'dijadwal_ulang';
    public const OUT_BATAL          = 'batal';

    /**
     * HASIL follow-up — apa yang terjadi saat Sales menghubungi pasien.
     * Sampai 4 Okt 2026 hanya tanggal/kanal/siapa yang tersimpan; HASILNYA
     * tidak dicatat sama sekali, jadi "berapa pasien tidak respon bulan ini"
     * tidak pernah terjawab. Pilihan tetap supaya bisa dihitung; catatan
     * bebas (fu_note) menampung yang tidak masuk kategori.
     */
    public const FU_DIHUBUNGI         = 'dihubungi';
    public const FU_TIDAK_RESPON      = 'tidak_respon';
    public const FU_MINTA_JADWAL_ULANG = 'minta_jadwal_ulang';
    public const FU_MENOLAK           = 'menolak';
    public const FU_SALAH_NOMOR       = 'salah_nomor';

    /**
     * Label kanal untuk layar. `ucfirst('wa')` berbunyi "Wa" — terbaca seperti
     * salah ketik. Lembar tidak lagi menanyakan kanal (5 Okt 2026), jadi kamus
     * ini melayani baris LAMA; yang tidak dikenal ditampilkan apa adanya.
     */
    public const FU_KANAL = [
        'wa'       => 'WhatsApp',
        'telepon'  => 'Telepon',
        'langsung' => 'Langsung',
        'lainnya'  => 'Lainnya',
    ];

    /** Kunci → label layar. Satu-satunya sumber untuk dropdown dan tampilan. */
    public const FU_HASIL = [
        self::FU_DIHUBUNGI          => 'Sudah dihubungi, bersedia',
        self::FU_TIDAK_RESPON       => 'Tidak respon',
        self::FU_MINTA_JADWAL_ULANG => 'Minta jadwal ulang',
        self::FU_MENOLAK            => 'Menolak / berhenti',
        self::FU_SALAH_NOMOR        => 'Salah nomor',
    ];

    /** Jatuh tempo berikutnya = kunjungan terakhir + ini, kalau irama belum diketahui. */
    public const USULAN_MAJU_HARI = 30;

    /**
     * Jeda minimum untuk tanggal yang DIKETIK Sales.
     *
     * ⚠ Sengaja TIDAK berlaku untuk jatuh tempo yang dihitung sistem. Dulu
     *   berlaku untuk keduanya, dan itu kekeliruan yang mahal — lihat
     *   tanggalUsulan() di bawah.
     *
     * Untuk tanggal yang diketik, jeda ini masuk akal: Sales butuh waktu
     * mengabari pasien sebelum tanggalnya tiba. Dipakai OrthoController
     * (geser & jadwalkan) dan atribut `min` pada pemilih tanggal di _sheet.
     */
    public const MIN_MAJU_HARI = 3;

    /**
     * TANGGAL JATUH TEMPO kontrol berikutnya: kunjungan terakhir + irama
     * pasien, atau + 30 hari kalau riwayatnya belum cukup.
     *
     * ────────────────────────────────────────────────────────────────────
     * BOLEH JATUH DI MASA LALU. ITU BUKAN CACAT, ITU JAWABANNYA.
     * ────────────────────────────────────────────────────────────────────
     * Kolom Jadwal adalah PENGINGAT, bukan janji — brief kamus istilah:
     * "SCH · Jadwal · tanggal pasien SEHARUSNYA datang kontrol". Janji yang
     * sesungguhnya hidup di kolom Reservasi (`rsv_date`), lengkap dengan
     * jam, dokter, dan aturan anti-bentrok milik modul Reservasi.
     *
     * Fungsi ini dulu melantai ke `hari ini + MIN_MAJU_HARI`, dengan alasan
     * "jadwal tidak boleh lahir sudah terlewat". Alasan itu benar untuk
     * JANJI dan salah untuk JATUH TEMPO: pasien yang kontrol terakhirnya 200
     * hari lalu memang sudah telat 170 hari, dan itulah yang perlu dilihat
     * Sales. Diukur di DB pengembangan, 26 dari 39 pasien aktif jatuh
     * temponya sudah lewat, rata-rata 43 hari — semuanya tersembunyi oleh
     * lantai itu.
     *
     * Dan lantai itu pula yang melahirkan 651 usulan di satu tanggal yang
     * dilaporkan audit HUB: saat menarik riwayat mundur berbulan-bulan,
     * SETIAP pasien yang jatuh temponya sudah lewat dijepit ke tanggal dekat
     * yang sama. Tanpa lantai, jatuh tempo menyebar sendiri karena
     * diturunkan dari kunjungan terakhir masing-masing pasien — terukur: 34
     * pasien di 29 tanggal berbeda, paling padat 2 orang sehari, tanpa pagar
     * kapasitas apa pun.
     */
    public static function tanggalUsulan(CarbonInterface $kunjungan, ?int $majuHari = null): Carbon
    {
        // $majuHari = irama pasien sendiri. Null berarti riwayatnya belum
        // cukup untuk punya irama, dan kita jatuh ke +30 — bukan menebak.
        //
        // ⚠ Parameter $hariIni DIBUANG, bukan diabaikan diam-diam. Jatuh
        //   tempo tidak lagi bergantung pada hari ini sama sekali, dan
        //   parameter yang ada tapi tidak berpengaruh adalah undangan untuk
        //   mengira lantainya masih bekerja.
        return Carbon::instance($kunjungan)->startOfDay()
            ->addDays($majuHari ?? self::USULAN_MAJU_HARI);
    }

    /**
     * Jatuh tempo diterjemahkan jadi PERKIRAAN yang bisa diucapkan — bukan
     * tanggal kalender.
     *
     * ─────────────────────────────────────────────────────────────────────
     * KENAPA BUKAN TANGGAL
     * ─────────────────────────────────────────────────────────────────────
     * Tanggalnya tebakan: irama pasien sendiri pun meleset 14 hari (terukur
     * prod), dan tanggal sungguhannya baru ditentukan saat Sales menelepon dan
     * menawarkan hari praktik dokter. Menuliskan "28 Okt '26" memberi
     * ketepatan yang tidak kita punya, dan Sales menyebutkannya ke pasien.
     *
     * Satuannya mengikuti yang MEMANG dipakai Sales hari ini: sheet mereka
     * punya 30 kolom BULAN, dan ukuran yang mereka tetapkan sendiri "kurang
     * dari 2 kunjungan dalam 6 bulan". Jadi jauh = bulan, dekat = pekan.
     *
     * ⚠ Yang sudah lewat TIDAK mengulang chip Status. Chip sudah berbunyi
     *   "Terlewat 6 hari"; kolom ini cuma menjawab "kapan kira-kira waktunya",
     *   jadi untuk mereka jawabannya "Sudah waktunya" dan titik.
     *
     * Murni fungsi tanggal — tanpa kueri, tanpa model — supaya tabel, drawer,
     * dan uji memakai kalimat yang sama persis. Pola yang sama dengan
     * OrtCase::irama().
     */
    public static function perkiraanJatuhTempo(CarbonInterface $tempo, ?CarbonInterface $hariIni = null): string
    {
        $tempo   = Carbon::instance($tempo)->startOfDay();
        $hariIni = $hariIni ? Carbon::instance($hariIni)->startOfDay() : Carbon::today();

        $selisih = $hariIni->diffInDays($tempo, false);

        if ($selisih < 0)  { return 'Sudah waktunya'; }
        if ($selisih === 0) { return 'Hari ini'; }
        if ($selisih <= 6)  { return 'Pekan ini'; }

        if ($selisih <= 27) {
            return '± ' . max(1, (int) round($selisih / 7)) . ' pekan lagi';
        }

        // Tahun ikut ditulis hanya kalau beda tahun — "± Jan" di bulan Desember
        // bisa dibaca Januari yang baru lewat.
        return '± ' . $tempo->translatedFormat(
            $tempo->year === $hariIni->year ? 'M' : "M 'y"
        );
    }

    /** Ambang derajat keterlambatan. */
    public const TERLEWAT_PARAH_HARI = 14;

    // ── Relasi

    public function case()
    {
        return $this->belongsTo(OrtCase::class, 'ort_case_id');
    }

    public function reservation()
    {
        return $this->belongsTo(RawCusReservation::class, 'reservation_id');
    }

    // ── Scope

    public function scopeTerbuka($q)
    {
        return $q->whereNull('closed_at');
    }

    public function scopeDiCabang($q, string $placeId)
    {
        return $q->where('place_id', $placeId);
    }

    /**
     * Daftar kerja harian. Sengaja digerakkan dari tabel INI, bukan dari
     * ort_case — penyaring paling selektif ada di sini, dan ORDER BY-nya ikut
     * index idx_ort_control_worklist yang sama.
     */
    public function scopeDaftarKerja($q, string $placeId)
    {
        return $q->where('place_id', $placeId)->whereNull('closed_at')->orderBy('sch_date');
    }

    public function scopeTerlewat($q, $hariIni = null)
    {
        return $q->whereNull('closed_at')->where('sch_date', '<', $hariIni ?: now()->toDateString());
    }

    // ── Turunan untuk tampilan

    public function isTerbuka(): bool
    {
        return $this->closed_at === null;
    }

    /** Berapa hari terlewat. 0 = belum terlewat atau sudah ditutup. */
    public function hariTerlewat($hariIni = null): int
    {
        if (! $this->isTerbuka() || $this->sch_date === null) {
            return 0;
        }

        $acuan = $hariIni ? \Carbon\Carbon::parse($hariIni) : now();
        $acuan = $acuan->startOfDay();

        return $this->sch_date->lt($acuan) ? $this->sch_date->diffInDays($acuan) : 0;
    }

    /**
     * Kunci status untuk chip di layar. Sembilan keadaan, jangan ditambah —
     * daftarnya dikunci di brief desain.
     */
    public function statusKey($hariIni = null): string
    {
        if ($this->visit_date)                        return 'hadir';
        if ($this->outcome === self::OUT_TIDAK_HADIR) return 'tidak_hadir';
        if ($this->rsv_status === 'cancelled')        return 'reservasi_batal';

        /*
         * ⚠ Baris `is_proposal => 'usulan'` DIBUANG dari sini.
         *
         * Dulu ia berdiri di atas pemeriksaan terlewat, jadi jatuh tempo yang
         * sudah lewat tetap berchip "Usulan jadwal" alih-alih "Terlewat".
         * Itu masuk akal selama tanggalnya dianggap janji yang belum
         * disepakati — tapi kolom Jadwal adalah PENGINGAT, dan pengingat yang
         * lewat artinya pasien telat, siapa pun yang menuliskan tanggalnya.
         *
         * Bersama lantai MIN_MAJU_HARI yang juga sudah dibuang, inilah lapis
         * kedua yang menyembunyikan 26 dari 39 pasien terlambat di DB
         * pengembangan: yang satu mendorong tanggalnya ke masa depan, yang
         * satu lagi membungkam chip-nya.
         */
        $telat = $this->hariTerlewat($hariIni);
        if ($telat > self::TERLEWAT_PARAH_HARI) return 'terlewat_parah';
        if ($telat > 0)                         return 'terlewat';

        if ($this->reservation_id) return 'ada_reservasi';
        if ($this->fu_date)        return 'sudah_fu';

        return 'menunggu';
    }

    /**
     * Anak tangga berikutnya — inilah satu-satunya tombol di kolom tindakan.
     * NULL = tidak ada yang bisa dikerjakan (kontrol sudah tertutup).
     *
     * ⚠ KEMBARAN. Tangga yang sama dihitung ulang di _tabel.blade.php supaya
     *   daftar tidak perlu memuat model per baris. Keduanya WAJIB sama persis;
     *   kalau yang satu berubah, yang lain ikut — kalau tidak, tombol di
     *   drawer dan tombol di baris akan berbeda untuk kontrol yang sama.
     *
     * "Konfirmasi jadwal" DIBUANG. Tidak ada yang perlu dikonfirmasi dari
     * sebuah pengingat: kolom Jadwal menjawab "kapan pasien ini seharusnya
     * datang", dan menyetujuinya tidak mengubah apa pun maupun memberi tahu
     * pasien. Kesepakatan yang sebenarnya tercatat saat reservasi dibuat.
     *
     * Tangganya kembali seperti brief: telepon dulu, lalu pesankan.
     */
    public function tindakanBerikutnya(): ?string
    {
        if (! $this->isTerbuka()) return null;

        if (! $this->fu_date) return 'follow_up';
        if (! $this->reservation_id || $this->rsv_status === 'cancelled') return 'buat_reservasi';

        return null; // sudah ada reservasi — tinggal menunggu pasien datang
    }
}
