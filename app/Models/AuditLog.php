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

    /** Nilai kolom ini tak boleh ikut tersimpan di log (hash pun jangan). */
    private const RAHASIA = ['password', 'token_hash', 'remember_token'];

    private static function samarkan(?array $values): ?array
    {
        if (! $values) return $values ?: null;
        foreach (self::RAHASIA as $k) {
            if (array_key_exists($k, $values)) $values[$k] = '[disamarkan]';
        }
        return $values;
    }

    /** $hanya: batasi key getChanges() yang disimpan (buang derau updated_at dkk). */
    public static function record(string $action, ?string $description = null, ?Model $model = null, ?array $oldValues = null, ?array $hanya = null): self
    {
        $baru = $model?->getChanges() ?: null;
        if ($baru && $hanya !== null) {
            $baru = array_intersect_key($baru, array_flip($hanya)) ?: null;
        }

        return self::create([
            'user_id'    => session('id'),
            'user_name'  => session('name'),
            'action'     => $action,
            'model_type' => $model ? class_basename($model) : null,
            'model_id'   => $model?->getKey(),
            'description'=> $description,
            'old_values' => self::samarkan($oldValues),
            'new_values' => self::samarkan($baru),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
