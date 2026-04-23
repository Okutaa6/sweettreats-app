<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawAffiliate extends Model
{
    protected $table = 'raw_affiliate';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'phone',
        'email',
        'photo',
        'social_tiktok',
        'social_instagram',
        'social_youtube',
        'social_platform',
        'social_username',
        'raw_branch_id',
        'raw_doctor_id',
        'raw_doc_service_id',
        'reward_program',
        'pin',
        'role',
        'type',
        'bank_account_info',
        'commission_rate',
        'is_active',
        'registration_status',
        'registration_notes',
        'created_at',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'is_active'       => 'boolean',
        'created_at'      => 'datetime',
    ];

    const REG_STATUS_PENDING  = 'pending';
    const REG_STATUS_APPROVED = 'approved';
    const REG_STATUS_REJECTED = 'rejected';

    public static function roles(): array
    {
        return [
            'pasien'      => 'Pasien',
            'influencer'  => 'Influencer',
            'dokter'      => 'Dokter',
        ];
    }

    public static function types(): array
    {
        return [
            'koc' => 'KOC',
            'kol' => 'KOL',
        ];
    }

    public static function registrationStatusOptions(): array
    {
        return [
            self::REG_STATUS_PENDING  => ['label' => 'Menunggu',  'color' => 'warning'],
            self::REG_STATUS_APPROVED => ['label' => 'Disetujui', 'color' => 'success'],
            self::REG_STATUS_REJECTED => ['label' => 'Ditolak',   'color' => 'danger'],
        ];
    }

    public static function rewardPrograms(): array
    {
        return [
            '3x'        => 'Bronze Mates',
            '12x'       => 'Silver Mates',
            'above_12x' => 'Gold Mates',
        ];
    }

    public static function rewardTiers(): array
    {
        return [
            '3x' => [
                'name'          => 'Bronze Mates',
                'content_count' => 3,
                'content_desc'  => '3× Kontrol + 3 Video',
                'reward'        => 'FREE Scaling',
                'icon'          => 'fa-medal',
                'color'         => '#c68a56',
                'gradient'      => 'linear-gradient(135deg, #c68a56, #a86b3c)',
                'bg_soft'       => 'linear-gradient(135deg, #fbe8d4, #f4d2b0)',
            ],
            '12x' => [
                'name'          => 'Silver Mates',
                'content_count' => 12,
                'content_desc'  => '12× Kontrol + 12 Video',
                'reward'        => 'FREE Retainer + Scaling',
                'icon'          => 'fa-award',
                'color'         => '#9ea3ae',
                'gradient'      => 'linear-gradient(135deg, #b8bcc4, #8a8f9a)',
                'bg_soft'       => 'linear-gradient(135deg, #eef0f3, #dde0e6)',
            ],
            'above_12x' => [
                'name'          => 'Gold Mates',
                'content_count' => 13,
                'content_desc'  => '>12× Kontrol + >12 Video',
                'reward'        => 'FREE Retainer + Whitening',
                'icon'          => 'fa-crown',
                'color'         => '#d4a84b',
                'gradient'      => 'linear-gradient(135deg, #e6c25a, #b88a2c)',
                'bg_soft'       => 'linear-gradient(135deg, #fdf2d4, #f7e2a8)',
            ],
        ];
    }

    public function rewardTier(): ?array
    {
        if (!$this->reward_program) return null;
        return self::rewardTiers()[$this->reward_program] ?? null;
    }

    public static function socialPlatforms(): array
    {
        return [
            'tiktok'    => 'TikTok',
            'instagram' => 'Instagram',
            'youtube'   => 'YouTube',
            'facebook'  => 'Facebook',
            'twitter'   => 'Twitter / X',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'raw_doctor_id', 'id');
    }

    public function docService()
    {
        return $this->belongsTo(RawDocService::class, 'raw_doc_service_id', 'id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('uploads/affiliate/' . $this->photo) : null;
    }

    public function promos()
    {
        return $this->hasMany(RawPromo::class, 'raw_affiliate_id', 'id');
    }

    public function commissions()
    {
        return $this->hasMany(RawCommission::class, 'raw_affiliate_id', 'id');
    }

    public function contents()
    {
        return $this->hasMany(AffiliateContent::class, 'raw_affiliate_id', 'id');
    }

    public function rewardClaims()
    {
        return $this->hasMany(AffiliateRewardClaim::class, 'raw_affiliate_id', 'id');
    }

    public function approvedContentCount(): int
    {
        return $this->contents()->where('status', AffiliateContent::STATUS_APPROVED)->count();
    }

    public function currentTier(): ?AffiliateRewardTier
    {
        $count = $this->approvedContentCount();

        return AffiliateRewardTier::active()
            ->where('required_content_count', '<=', $count)
            ->orderByDesc('required_content_count')
            ->first();
    }

    public function nextTier(): ?AffiliateRewardTier
    {
        $count = $this->approvedContentCount();

        return AffiliateRewardTier::active()
            ->where('required_content_count', '>', $count)
            ->orderBy('required_content_count')
            ->first();
    }
}
