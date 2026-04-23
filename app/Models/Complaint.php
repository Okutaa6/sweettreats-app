<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sys_complaint';

    const STATUS_NEW      = 'new';
    const STATUS_RECEIVED = 'received';
    const STATUS_HANDLED  = 'handled';

    const STATUS_LABELS = [
        self::STATUS_NEW      => 'Baru',
        self::STATUS_RECEIVED => 'Diterima',
        self::STATUS_HANDLED  => 'Sudah Ditangani',
    ];

    protected $fillable = [
        'id',
        'sender_user_id',
        'sender_name',
        'raw_branch_id',
        'message',
        'status',
        'reply',
    ];

    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = 'updated_at';
    const CREATED_AT = 'created_at';

    public function sender()
    {
        return $this->belongsTo(Users::class, 'sender_user_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusPillClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_NEW      => 'st-pill--warning',
            self::STATUS_RECEIVED => 'st-pill--info',
            self::STATUS_HANDLED  => 'st-pill--neutral',
            default               => 'st-pill--neutral',
        };
    }

    public static function nextStatus(string $current): ?string
    {
        return match ($current) {
            self::STATUS_NEW      => self::STATUS_RECEIVED,
            self::STATUS_RECEIVED => self::STATUS_HANDLED,
            default               => null,
        };
    }
}
