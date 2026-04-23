<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateContent extends Model
{
    protected $table = 'affiliate_content';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'raw_affiliate_id',
        'platform',
        'content_url',
        'content_type',
        'caption',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'created_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING  => ['label' => 'Menunggu',  'color' => 'warning'],
            self::STATUS_APPROVED => ['label' => 'Disetujui', 'color' => 'success'],
            self::STATUS_REJECTED => ['label' => 'Ditolak',   'color' => 'danger'],
        ];
    }

    public static function platformOptions(): array
    {
        return [
            'tiktok'    => 'TikTok',
            'instagram' => 'Instagram',
            'youtube'   => 'YouTube',
        ];
    }

    public static function contentTypeOptions(): array
    {
        return [
            'video' => 'Video',
            'reels' => 'Reels',
            'story' => 'Story',
            'post'  => 'Post',
        ];
    }

    public function affiliate()
    {
        return $this->belongsTo(RawAffiliate::class, 'raw_affiliate_id', 'id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Users::class, 'reviewed_by', 'id');
    }

    public function canBeEditedBy(RawAffiliate $affiliate): bool
    {
        return $this->raw_affiliate_id === $affiliate->id
            && in_array($this->status, [self::STATUS_PENDING, self::STATUS_REJECTED], true);
    }

    public function resetToPending(): void
    {
        $this->status       = self::STATUS_PENDING;
        $this->reviewed_by  = null;
        $this->reviewed_at  = null;
        $this->review_notes = null;
    }
}
