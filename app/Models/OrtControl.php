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
        'fu_date', 'fu_channel', 'fu_by',
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

    /** Usulan berikutnya = kunjungan terakhir + ini. Tapi lihat MIN_MAJU. */
    public const USULAN_MAJU_HARI = 30;

    /**
     * Jadwal tidak boleh lahir sudah terlewat. Kalau kunjungan terakhirnya
     * sudah lama sekali, usulan dihitung dari HARI INI + 3, bukan dari
     * kunjungan terakhir + 30.
     */
    public const MIN_MAJU_HARI = 3;

    /**
     * Tanggal usulan kontrol berikutnya: kunjungan + 30, TAPI tidak pernah
     * lebih dekat dari hari ini + MIN_MAJU_HARI.
     *
     * KENAPA JADI SATU FUNGSI. Aturan ini dulu disalin di empat tempat, dan
     * satu di antaranya — justru yang paling sering jalan, tarikan malam —
     * lupa menyalin separuhnya. Akibatnya nyata: rekam medis kadang masuk
     * terlambat berminggu-minggu (brief menyebut sampai 40 hari), dan usulan
     * yang lahir malam itu tanggalnya SUDAH LEWAT. Paginya Sales melihat
     * "Terlewat 10 hari" pada jadwal yang umurnya baru beberapa jam, di baris
     * yang bahkan belum pernah disepakati siapa pun. Lebih buruk lagi,
     * usulan yang lahir terlewat langsung naik ke puncak daftar kerja dan
     * mendesak pasien yang benar-benar tertinggal.
     *
     * Jeda minimum itu bukan angka teknis: Sales butuh waktu mengabari
     * pasien sebelum tanggalnya tiba. Validasi input manual (OrthoController)
     * dan atribut `min` pada pemilih tanggal (_sheet) memakai batas yang sama.
     */
    public static function tanggalUsulan(
        CarbonInterface $kunjungan,
        ?CarbonInterface $hariIni = null,
        ?int $majuHari = null
    ): Carbon {
        $hariIni = $hariIni ? Carbon::instance($hariIni)->startOfDay() : Carbon::today();

        // $majuHari = irama pasien sendiri (Tahap C). Null berarti pasien itu
        // belum punya cukup riwayat untuk punya irama, dan kita jatuh ke +30 —
        // bukan menebak. Lantai MIN_MAJU_HARI di bawah berlaku untuk keduanya.
        $usul   = Carbon::instance($kunjungan)->startOfDay()
            ->addDays($majuHari ?? self::USULAN_MAJU_HARI);
        $paling = $hariIni->copy()->addDays(self::MIN_MAJU_HARI);

        return $usul->lt($paling) ? $paling : $usul;
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
        if ($this->is_proposal)                       return 'usulan';

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
     */
    public function tindakanBerikutnya(): ?string
    {
        if (! $this->isTerbuka())  return null;
        if ($this->is_proposal)    return 'konfirmasi';
        if (! $this->fu_date)      return 'follow_up';
        if (! $this->reservation_id || $this->rsv_status === 'cancelled') return 'buat_reservasi';

        return null; // sudah ada reservasi — tinggal menunggu pasien datang
    }
}
