<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateRewardTier extends Model
{
    protected $table = 'affiliate_reward_tier';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'required_content_count',
        'reward_description',
        'sort_order',
        'is_active',
        'created_at',
    ];

    protected $casts = [
        'required_content_count' => 'integer',
        'sort_order'             => 'integer',
        'is_active'              => 'boolean',
        'created_at'             => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('sort_order');
    }
}
