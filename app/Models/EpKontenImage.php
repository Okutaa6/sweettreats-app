<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * EpKontenImage
 *
 * Foto/gambar yang di-attach ke 1 record ep_konten.
 * Setiap upload menghasilkan 3 versi WebP (orig/large/thumb) untuk hemat
 * bandwidth saat render list & thumbnail.
 *
 * Workflow review:
 *   - Default status: pending
 *   - User dengan akses edit ep_konten boleh tandai: approved atau revisi
 *   - Field reviewed_by / reviewed_at audit siapa yang review & kapan
 */
class EpKontenImage extends Model
{
    protected $table = 'ep_konten_image';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false; // manual created_at, konsisten dengan EpKonten

    protected $fillable = [
        'id',
        'ep_konten_id',
        'path_original',
        'path_large',
        'path_thumb',
        'original_name',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'sort_order',
        'label',
        'review_status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'created_at'  => 'datetime',
        'reviewed_at' => 'datetime',
        'size_bytes'  => 'integer',
        'width'       => 'integer',
        'height'      => 'integer',
        'sort_order'  => 'integer',
    ];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVISI   = 'revisi';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING  => ['label' => 'Pending',       'color' => 'warning'],
            self::STATUS_APPROVED => ['label' => 'Disetujui',     'color' => 'success'],
            self::STATUS_REVISI   => ['label' => 'Perlu Revisi',  'color' => 'danger'],
        ];
    }

    public function konten()
    {
        return $this->belongsTo(EpKonten::class, 'ep_konten_id');
    }

    public function getThumbUrlAttribute(): string
    {
        return asset($this->path_thumb);
    }

    public function getLargeUrlAttribute(): string
    {
        return asset($this->path_large);
    }

    public function getOriginalUrlAttribute(): string
    {
        return asset($this->path_original);
    }
}
