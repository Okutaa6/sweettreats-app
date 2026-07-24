<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan key-value sederhana. Dipakai untuk teks yang boleh diubah admin
 * tanpa deploy (mis. teks Info/pengumuman di my-checklist).
 */
class Setting extends Model
{
    protected $table = 'sys_setting';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        try {
            $val = static::query()->where('key', $key)->value('value');
            return ($val === null || $val === '') ? $default : $val;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
