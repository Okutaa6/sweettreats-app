<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jejak perubahan Aturan Kerja (siapa, kapan, dari→ke). Append-only, tabel
 * sendiri — JANGAN numpang audit_log login (rencana-aturan-kerja.md §3).
 * Skema: database/sql/2026_08_04_aturan_kerja.sql.
 */
class AttRuleLog extends Model
{
    protected $table = 'att_rule_log';

    public $timestamps = false;   // append-only, cukup created_at manual (pola AttScan)

    protected $fillable = ['actor_id', 'actor_nama', 'aksi', 'target', 'detail', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    /** Tulis satu baris jejak. $detail array → JSON. */
    public static function catat(string $aksi, string $target, array $detail = []): void
    {
        static::create([
            'actor_id'   => (string) session('id') ?: null,
            'actor_nama' => session('name') ?: null,
            'aksi'       => $aksi,
            'target'     => $target,
            'detail'     => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
        ]);
    }
}
