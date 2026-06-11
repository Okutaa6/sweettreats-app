<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mode akses per (group, halaman). Default kalau row tidak ada = 'full'.
 *
 * Constants:
 *   - MODE_FULL      : akses penuh (default)
 *   - MODE_VIEW_ONLY : read-only, tidak bisa create/update/delete
 *
 * page_key contoh: 'rawreservation'. Generic supaya bisa dipakai untuk
 * halaman lain di masa depan tanpa migration tambahan.
 */
class GroupPageAccessMode extends Model
{
    use HasFactory;

    protected $table = 'group_page_access_modes';

    protected $fillable = [
        'group_id',
        'page_key',
        'access_mode',
    ];

    public const MODE_FULL      = 'full';
    public const MODE_VIEW_ONLY = 'view_only';

    public function group()
    {
        return $this->belongsTo(GroupPath::class, 'group_id', 'id');
    }

    /**
     * Resolve mode untuk (group_id, page_key). Return 'full' kalau:
     *   - group_id null (user belum login / session tidak ada group)
     *   - tidak ada row di tabel untuk pasangan ini (default)
     */
    public static function modeFor(?string $groupId, string $pageKey): string
    {
        if (! $groupId) return self::MODE_FULL;
        $mode = static::where('group_id', $groupId)
            ->where('page_key', $pageKey)
            ->value('access_mode');
        return $mode ?: self::MODE_FULL;
    }

    /**
     * Bulk upsert mode untuk satu page_key. Input: ['group_id' => 'mode', ...].
     * Mode 'full' akan menghapus row (default state, no row needed).
     */
    public static function bulkSet(string $pageKey, array $groupModes): void
    {
        foreach ($groupModes as $groupId => $mode) {
            $mode = $mode === self::MODE_VIEW_ONLY ? self::MODE_VIEW_ONLY : self::MODE_FULL;
            if ($mode === self::MODE_FULL) {
                static::where('group_id', $groupId)->where('page_key', $pageKey)->delete();
            } else {
                static::updateOrCreate(
                    ['group_id' => $groupId, 'page_key' => $pageKey],
                    ['access_mode' => $mode],
                );
            }
        }
    }
}
