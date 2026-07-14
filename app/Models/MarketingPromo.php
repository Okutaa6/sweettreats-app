<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingPromo extends Model
{
    protected $table = 'marketing_promo';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nama_promo',
        'campaign_type',
        'detail_promo',
        'syarat_ketentuan',
        'cetakan',
        'bulan_promo',
        'tahun_promo',
        'periode_start',
        'periode_end',
        'status',
        'harga_normal',
        'harga_promo',
        'cogs_fee_dokter',
        'margin_rp',
        'margin_pct',
        'voucher_tambahan_pasien',
        'is_active',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'periode_start'           => 'date',
        'periode_end'             => 'date',
        'harga_normal'            => 'decimal:2',
        'harga_promo'             => 'decimal:2',
        'cogs_fee_dokter'         => 'decimal:2',
        'margin_rp'               => 'decimal:2',
        'margin_pct'              => 'decimal:4',
        'voucher_tambahan_pasien' => 'decimal:2',
        'is_active'               => 'boolean',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    public static function campaignTypes(): array
    {
        return [
            'promo_bulanan'  => 'Promo Bulanan',
            'promo_cabang'   => 'Promo Cabang',
            'sweet_surprise' => 'Sweet Surprise',
            'cicilan_behel'  => 'Cicilan Behel',
            'tanggal_kembar' => 'Tanggal Kembar',
            'payday'         => 'Payday',
        ];
    }

    /**
     * Warna aksen per jenis campaign (untuk badge + garis tepi card).
     * 'color' = teks/aksen pekat, 'bg' = latar badge muda.
     */
    public static function campaignColors(): array
    {
        return [
            'promo_bulanan'  => ['color' => '#7a3b00', 'bg' => '#f3ebe4'],
            'promo_cabang'   => ['color' => '#155e63', 'bg' => '#e2f1f1'],
            'sweet_surprise' => ['color' => '#a01a55', 'bg' => '#fbe6ef'],
            'cicilan_behel'  => ['color' => '#0d47a1', 'bg' => '#e3edfb'],
            'tanggal_kembar' => ['color' => '#5b2a86', 'bg' => '#efe6f7'],
            'payday'         => ['color' => '#1b5e20', 'bg' => '#e4f2e5'],
        ];
    }

    public function campaignColor(): array
    {
        return self::campaignColors()[$this->campaign_type] ?? ['color' => '#8a5a2b', 'bg' => '#f3ebe4'];
    }

    public static function statusOptions(): array
    {
        return [
            'planning' => ['label' => 'Planning', 'color' => 'warning'],
            'aktif'    => ['label' => 'Aktif',    'color' => 'success'],
            'selesai'  => ['label' => 'Selesai',  'color' => 'neutral'],
        ];
    }

    /**
     * Status otomatis dari periode:
     *   sebelum periode_start          → planning
     *   di dalam rentang periode       → aktif
     *   setelah periode_end            → selesai
     * Bandingkan per-hari (batas inklusif). Return null kalau tak ada tanggal
     * sama sekali → status dibiarkan manual.
     */
    public static function deriveStatus($start, $end, $on = null): ?string
    {
        $on    = $on ? \Carbon\Carbon::parse($on)->startOfDay() : \Carbon\Carbon::today();
        $start = $start ? \Carbon\Carbon::parse($start)->startOfDay() : null;
        $end   = $end ? \Carbon\Carbon::parse($end)->startOfDay() : null;

        if (!$start && !$end)          return null;
        if ($end && $on->gt($end))     return 'selesai';
        if ($start && $on->lt($start)) return 'planning';
        return 'aktif';
    }

    public function derivedStatus(?\DateTimeInterface $on = null): ?string
    {
        return self::deriveStatus($this->periode_start, $this->periode_end, $on);
    }

    /**
     * Selaraskan kolom `status` semua promo dengan periode-nya (dipakai command
     * terjadwal + self-heal saat list dibuka). Tiga UPDATE saling eksklusif;
     * promo tanpa tanggal tidak disentuh. Return jumlah baris yang berubah.
     */
    public static function syncStatuses($on = null): int
    {
        $today = ($on ? \Carbon\Carbon::parse($on) : \Carbon\Carbon::today())->toDateString();
        $n = 0;

        // Selesai — periode_end sudah lewat.
        $n += static::whereNotNull('periode_end')
            ->whereDate('periode_end', '<', $today)
            ->where('status', '!=', 'selesai')
            ->update(['status' => 'selesai']);

        // Planning — belum sampai periode_start.
        $n += static::whereNotNull('periode_start')
            ->whereDate('periode_start', '>', $today)
            ->where('status', '!=', 'planning')
            ->update(['status' => 'planning']);

        // Aktif — dalam rentang & minimal satu tanggal terisi.
        $n += static::where(fn($q) => $q->whereNotNull('periode_start')->orWhereNotNull('periode_end'))
            ->where(fn($q) => $q->whereNull('periode_start')->orWhereDate('periode_start', '<=', $today))
            ->where(fn($q) => $q->whereNull('periode_end')->orWhereDate('periode_end', '>=', $today))
            ->where('status', '!=', 'aktif')
            ->update(['status' => 'aktif']);

        return $n;
    }

    public function branches()
    {
        return $this->belongsToMany(
            RawBranch::class,
            'marketing_promo_branch',
            'marketing_promo_id',
            'raw_branch_id'
        );
    }

    public function reservations()
    {
        return $this->hasMany(RawCusReservation::class, 'marketing_promo_id', 'id');
    }

    public function isAllBranches(): bool
    {
        return $this->branches()->count() === 0;
    }

    public function isWithinPeriode(?\DateTimeInterface $on = null): bool
    {
        $on = $on ?: now();
        if ($this->periode_start && $on < $this->periode_start) return false;
        if ($this->periode_end && $on > $this->periode_end->endOfDay()) return false;
        return true;
    }

    /**
     * Diskon (%) dari harga normal ke harga promo. Null kalau data tak lengkap.
     */
    public function diskonPct(): ?int
    {
        if (!$this->harga_normal || !$this->harga_promo) return null;
        if ($this->harga_normal <= $this->harga_promo) return null;
        return (int) round((($this->harga_normal - $this->harga_promo) / $this->harga_normal) * 100);
    }

    /**
     * Klasifikasi promo ke satu "ember" periode untuk tampilan card (grouping).
     * Prioritas: selesai > sedang berjalan > bulan ini > bulan depan > akan datang.
     */
    public function periodeBucket(): string
    {
        $start = $this->periode_start;
        $end   = $this->periode_end;

        if (!$start && !$end) return 'tanpa_periode';
        if ($end && now()->gt($end->copy()->endOfDay())) return 'selesai';
        if ($this->isWithinPeriode()) return 'berjalan';

        if ($start) {
            if ($start->isSameMonth(now()))            return 'bulan_ini';
            if ($start->isSameMonth(now()->addMonth())) return 'bulan_depan';
        }
        return 'akan_datang';
    }

    /**
     * Metadata ember periode (urutan tampil + label + warna aksen).
     */
    public static function periodeBuckets(): array
    {
        return [
            'berjalan'      => ['label' => 'Sedang Berjalan',  'icon' => 'fa-play-circle',  'accent' => '#1e7e34'],
            'bulan_ini'     => ['label' => 'Bulan Ini',        'icon' => 'fa-calendar-day', 'accent' => '#461500'],
            'bulan_depan'   => ['label' => 'Bulan Depan',      'icon' => 'fa-calendar-plus','accent' => '#8a5a2b'],
            'akan_datang'   => ['label' => 'Akan Datang',      'icon' => 'fa-clock',        'accent' => '#6c757d'],
            'tanpa_periode' => ['label' => 'Tanpa Periode',    'icon' => 'fa-infinity',     'accent' => '#6c757d'],
            'selesai'       => ['label' => 'Sudah Selesai',    'icon' => 'fa-flag-checkered','accent' => '#adb5bd'],
        ];
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', 1)->where('status', 'aktif');
    }

    public function scopeForBranch($query, ?string $branchId)
    {
        if (!$branchId) return $query;
        return $query->where(function ($q) use ($branchId) {
            $q->whereDoesntHave('branches')
              ->orWhereHas('branches', fn($b) => $b->where('raw_branch_id', $branchId));
        });
    }

    public function scopeWithinPeriode($query, ?\DateTimeInterface $on = null)
    {
        $on = $on ?: now();
        return $query
            ->where(fn($q) => $q->whereNull('periode_start')->orWhere('periode_start', '<=', $on))
            ->where(fn($q) => $q->whereNull('periode_end')->orWhere('periode_end', '>=', $on));
    }
}
