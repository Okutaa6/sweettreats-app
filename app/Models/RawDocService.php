<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawDocService extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_doc_service';

    protected $fillable = [
        'id',
        'name',
        'code',
        'category',
        'sort_order',
        'duration_minutes',
        'price',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $casts = [
        'duration_minutes' => 'double',
        'price'            => 'double',
        'sort_order'       => 'integer', // BARU
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    // new: daftar kategori layanan
    public static function categories(): array
    {
        return [
            'behel'      => 'Behel',
            'estetika'   => 'Estetika',
            'standar'    => 'Perawatan Standar',
            'pembedahan' => 'Pembedahan',
            'lainnya'    => 'Lainnya',
        ];
    }

    public function reservationServices()
    {
        return $this->hasMany(RawCusResService::class, 'raw_doc_service_id', 'id');
    }

    public function details()
    {
        return $this->hasMany(RawDocServiceDetail::class, 'raw_doc_service_id', 'id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function allDetails()
    {
        return $this->hasMany(RawDocServiceDetail::class, 'raw_doc_service_id', 'id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Range harga per kode treatment (dipakai endpoint Cekat service_range).
     * Min/max diambil dari raw_doc_service_detail aktif; fallback ke price service.
     *
     * @param  array<int,string> $codes
     * @return array<int,array{code:string,name:?string,min_price:?float,max_price:?float,found:bool}>
     */
    public static function priceRangeByCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($c) => strtoupper(trim((string) $c)),
            $codes
        ))));

        if (empty($codes)) {
            return [];
        }

        $services = self::whereIn('code', $codes)->get(['id', 'code', 'name', 'price']);
        $byCode   = $services->keyBy(fn ($s) => strtoupper((string) $s->code));

        $detailAgg = [];
        $serviceIds = $services->pluck('id')->all();
        if (! empty($serviceIds)) {
            $detailAgg = RawDocServiceDetail::whereIn('raw_doc_service_id', $serviceIds)
                ->where('is_active', 1)
                ->selectRaw('raw_doc_service_id, MIN(price) as min_p, MAX(price) as max_p')
                ->groupBy('raw_doc_service_id')
                ->get()
                ->keyBy('raw_doc_service_id');
        }

        $out = [];
        foreach ($codes as $code) {
            $svc = $byCode->get($code);
            if (! $svc) {
                $out[] = [
                    'code'      => $code,
                    'name'      => null,
                    'min_price' => null,
                    'max_price' => null,
                    'found'     => false,
                ];
                continue;
            }

            $agg = $detailAgg[$svc->id] ?? null;
            $min = $agg ? (float) $agg->min_p : (float) ($svc->price ?? 0);
            $max = $agg ? (float) $agg->max_p : (float) ($svc->price ?? 0);

            $out[] = [
                'code'      => $code,
                'name'      => $svc->name,
                'min_price' => $min,
                'max_price' => $max,
                'found'     => true,
            ];
        }

        return $out;
    }

    /**
     * Resolve string treatment dari vendor → RawDocService.
     * Prioritas: code persis → nama persis → nama LIKE.
     */
    public static function resolveByTreatment(?string $treatment): ?self
    {
        $t = trim((string) $treatment);
        if ($t === '') {
            return null;
        }

        $byCode = self::whereRaw('UPPER(code) = ?', [strtoupper($t)])->first();
        if ($byCode) {
            return $byCode;
        }

        $byName = self::whereRaw('LOWER(name) = ?', [mb_strtolower($t)])->first();
        if ($byName) {
            return $byName;
        }

        return self::where('name', 'like', '%' . $t . '%')->first();
    }
}