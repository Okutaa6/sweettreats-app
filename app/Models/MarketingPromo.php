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

    public static function statusOptions(): array
    {
        return [
            'planning' => ['label' => 'Planning', 'color' => 'warning'],
            'aktif'    => ['label' => 'Aktif',    'color' => 'success'],
            'selesai'  => ['label' => 'Selesai',  'color' => 'neutral'],
        ];
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
