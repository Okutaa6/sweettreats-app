<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $table = 'admin_notification';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'raw_branch_id', 'type', 'title', 'body', 'link', 'is_read', 'created_at',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'created_at' => 'datetime',
    ];

    public static function notify(string $type, string $title, ?string $body = null, ?string $link = null, ?string $branchId = null): self
    {
        return self::create([
            'type'          => $type,
            'title'         => $title,
            'body'          => $body,
            'link'          => $link,
            'raw_branch_id' => $branchId,
            'created_at'    => now(),
        ]);
    }

    public static function unreadForUser(): \Illuminate\Database\Eloquent\Builder
    {
        $userId = session('id');
        $branchId = session('raw_branch_id');

        return self::where('is_read', false)
            ->where(function ($q) use ($userId, $branchId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
                if ($branchId) {
                    $q->where(function ($q2) use ($branchId) {
                        $q2->whereNull('raw_branch_id')->orWhere('raw_branch_id', $branchId);
                    });
                }
            })
            ->orderByDesc('created_at');
    }
}
