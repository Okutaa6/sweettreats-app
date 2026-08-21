<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EpKonten extends Model
{
    protected $table = 'ep_konten';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'judul',
        'deskripsi',
        'platforms',
        'tipe_konten',
        'is_breakdown',
        'lokasi',
        'acc_by',
        'status',
        'prioritas',
        'script',
        'caption',
        'referensi',
        'framework',
        'visual_plan',
        'note',
        'tanggal_upload',
        'waktu_pengerjaan',
        'ep_campaign_framework_id',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'platforms'      => 'array',
        'is_breakdown'   => 'boolean',
        'tanggal_upload' => 'date',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function scopeBreakdown($q)  { return $q->where('is_breakdown', 1); }
    public function scopeRegular($q)    { return $q->where('is_breakdown', 0); }

    public static function platformOptions(): array
    {
        return [
            'ig_post'  => 'Instagram Post',
            'ig_story' => 'Instagram Stories',
            'tiktok'   => 'TikTok',
            'gp'       => 'Branding GP',
        ];
    }

    /**
     * Legacy platform values yang sekarang dimerge ke 'ig_post'.
     * Dipakai saat normalize read & saat filter calendar.
     */
    public static function legacyIgPostAliases(): array
    {
        return ['ig_feeds', 'ig_reels'];
    }

    /**
     * Normalize platforms saat dibaca: data lama (ig_feeds / ig_reels)
     * di-merge jadi ig_post dan di-dedup.
     */
    public function getPlatformsAttribute($value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) return [];

        $aliases = self::legacyIgPostAliases();
        $mapped  = array_map(fn ($p) => in_array($p, $aliases, true) ? 'ig_post' : $p, $value);

        return array_values(array_unique($mapped));
    }

    public static function tipeKontenOptions(): array
    {
        return [
            'paid_ads'    => 'Paid Ads',
            'video_reels' => 'Video / Reels',
            'carousel'    => 'Carousel',
            'single_post' => 'Single Post',
            'collab_post' => 'Collab Post',
            'story'       => 'Story',
            'live'        => 'Live',
        ];
    }

    /**
     * Color mapping per tipe konten — soft pastels untuk visual variety
     * tanpa terlalu vivid. Format: ['bg', 'text', 'border'].
     */
    public static function tipeKontenColors(): array
    {
        return [
            'paid_ads'    => ['bg' => '#ede9fe', 'text' => '#5b21b6', 'border' => '#c4b5fd'],
            'video_reels' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5'],
            'carousel'    => ['bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d'],
            'single_post' => ['bg' => '#dbeafe', 'text' => '#1e40af', 'border' => '#93c5fd'],
            'collab_post' => ['bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7'],
            'story'       => ['bg' => '#fce7f3', 'text' => '#9d174d', 'border' => '#f9a8d4'],
            'live'        => ['bg' => '#ffe4e6', 'text' => '#9f1239', 'border' => '#fda4af'],
        ];
    }

    /**
     * Color mapping per status — pastel-soft.
     */
    public static function statusColors(): array
    {
        return [
            'draft'       => ['bg' => '#f3f4f6', 'text' => '#4b5563', 'dot' => '#9ca3af'],
            'riset'       => ['bg' => '#dbeafe', 'text' => '#1e40af', 'dot' => '#3b82f6'],
            'progress'    => ['bg' => '#fef3c7', 'text' => '#92400e', 'dot' => '#f59e0b'],
            'revisi'      => ['bg' => '#fde2e2', 'text' => '#991b1b', 'dot' => '#ef4444'],
            'done_revisi' => ['bg' => '#cffafe', 'text' => '#155e75', 'dot' => '#06b6d4'],
            'on_hold'     => ['bg' => '#e9ecef', 'text' => '#495057', 'dot' => '#6b7280'],
            'selesai'     => ['bg' => '#d1fae5', 'text' => '#065f46', 'dot' => '#10b981'],
        ];
    }

    /**
     * Color mapping per platform.
     */
    public static function platformColors(): array
    {
        return [
            'ig_post'  => ['bg' => '#dbeafe', 'text' => '#1e3a8a', 'icon' => 'fa-brands fa-instagram'],
            'ig_story' => ['bg' => '#d1fae5', 'text' => '#065f46', 'icon' => 'fa-brands fa-instagram'],
            'tiktok'   => ['bg' => '#fef3c7', 'text' => '#854d0e', 'icon' => 'fa-brands fa-tiktok'],
            'gp'       => ['bg' => '#f3e8ff', 'text' => '#6b21a8', 'icon' => 'fa-solid fa-user-doctor'],
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'draft'       => ['label' => 'Draft',        'color' => 'neutral'],
            'riset'       => ['label' => 'Riset',        'color' => 'info'],
            'progress'    => ['label' => 'Progress',     'color' => 'warning'],
            'revisi'      => ['label' => 'Revisi',       'color' => 'danger'],
            'done_revisi' => ['label' => 'Done Revisi',  'color' => 'info'],
            'on_hold'     => ['label' => 'On Hold',      'color' => 'neutral'],
            'selesai'     => ['label' => 'Selesai',      'color' => 'success'],
        ];
    }

    public static function prioritasOptions(): array
    {
        return [
            'low'    => ['label' => 'Low',    'color' => 'neutral'],
            'normal' => ['label' => 'Normal', 'color' => 'info'],
            'high'   => ['label' => 'High',   'color' => 'warning'],
            'urgent' => ['label' => 'Urgent', 'color' => 'danger'],
        ];
    }

    public function pillars()
    {
        return $this->belongsToMany(
            EpKontenPillar::class,
            'ep_konten_pillar_pivot',
            'ep_konten_id',
            'ep_konten_pillar_id'
        );
    }

    public function campaign()
    {
        return $this->belongsTo(EpCampaignFramework::class, 'ep_campaign_framework_id', 'id');
    }

    public function images()
    {
        return $this->hasMany(EpKontenImage::class, 'ep_konten_id')
                    ->orderBy('sort_order')
                    ->orderBy('created_at');
    }
}
