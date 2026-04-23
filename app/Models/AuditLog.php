<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_log';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_name', 'action', 'model_type', 'model_id',
        'description', 'old_values', 'new_values', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public static function record(string $action, ?string $description = null, ?Model $model = null, ?array $oldValues = null): self
    {
        return self::create([
            'user_id'    => session('id'),
            'user_name'  => session('name'),
            'action'     => $action,
            'model_type' => $model ? class_basename($model) : null,
            'model_id'   => $model?->getKey(),
            'description'=> $description,
            'old_values' => $oldValues,
            'new_values' => $model?->getChanges() ?: null,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
