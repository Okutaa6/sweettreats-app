<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateRewardClaim extends Model
{
    protected $table = 'affiliate_reward_claim';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'raw_affiliate_id',
        'affiliate_reward_tier_id',
        'status',
        'claimed_at',
        'processed_by',
        'processed_at',
        'consumed_content_count',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'claimed_at'             => 'datetime',
        'processed_at'           => 'datetime',
        'created_at'             => 'datetime',
        'consumed_content_count' => 'integer',
    ];

    const STATUS_ELIGIBLE = 'eligible';
    const STATUS_CLAIMED  = 'claimed';
    const STATUS_USED     = 'used';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ELIGIBLE => ['label' => 'Bisa Diklaim',  'color' => 'info'],
            self::STATUS_CLAIMED  => ['label' => 'Sudah Diklaim', 'color' => 'warning'],
            self::STATUS_USED     => ['label' => 'Sudah Dipakai', 'color' => 'success'],
        ];
    }

    public function affiliate()
    {
        return $this->belongsTo(RawAffiliate::class, 'raw_affiliate_id', 'id');
    }

    public function tier()
    {
        return $this->belongsTo(AffiliateRewardTier::class, 'affiliate_reward_tier_id', 'id');
    }

    public function processor()
    {
        return $this->belongsTo(Users::class, 'processed_by', 'id');
    }
}
