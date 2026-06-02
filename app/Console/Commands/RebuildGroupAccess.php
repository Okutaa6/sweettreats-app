<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Regenerate file cache akses sidebar (storage/app/group_access/{groupId}.txt)
 * dari tabel access_path + group_access.
 *
 *   php artisan access:rebuild              # semua group
 *   php artisan access:rebuild --group=<id> # 1 group
 *
 * Normalnya .txt di-regenerate otomatis saat admin simpan akses di
 * Set > Group > Access. Command ini dipakai kalau access_path / group_access
 * di-update langsung via SQL (mis. saat deploy fitur baru).
 */
class RebuildGroupAccess extends Command
{
    protected $signature = 'access:rebuild {--group= : Group ID tertentu (default: semua group)}';

    protected $description = 'Regenerate storage/app/group_access/*.txt dari access_path + group_access';

    public function handle(): int
    {
        $dir = storage_path('app/group_access');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $groups = DB::table('group_path')
            ->when($this->option('group'), fn($q, $g) => $q->where('id', $g))
            ->pluck('id');

        if ($groups->isEmpty()) {
            $this->error('Tidak ada group ditemukan.');
            return self::FAILURE;
        }

        foreach ($groups as $gid) {
            $tree = $this->buildTree($gid);
            file_put_contents(
                $dir . '/' . $gid . '.txt',
                json_encode($tree, JSON_PRETTY_PRINT)
            );
            Cache::forget('group_access_' . $gid);
            $this->line('  ✓ ' . $gid . ' — ' . count($tree) . ' parent menu');
        }

        $this->info('Rebuilt ' . $groups->count() . ' group access file(s).');
        return self::SUCCESS;
    }

    /**
     * Bangun tree parent → sub → child dari access yang dimiliki group.
     * Mereplikasi logika Group::access_generator().
     */
    private function buildTree(string $groupId): array
    {
        $accessIds = DB::table('group_access')
            ->where('group_id', $groupId)
            ->pluck('access_id')
            ->all();

        if (empty($accessIds)) {
            return [];
        }

        $paths = DB::table('access_path')
            ->whereIn('id', $accessIds)
            ->orderBy('urutan')
            ->get();

        $tree = [];

        foreach ($paths as $parent) {
            if (! empty($parent->pid)) {
                continue; // hanya parent (pid null/kosong)
            }
            $node = (array) $parent;
            $subs = [];

            foreach ($paths as $sub) {
                if ($sub->pid !== $parent->id) {
                    continue;
                }
                $subNode  = (array) $sub;
                $children = [];

                foreach ($paths as $child) {
                    if ($child->pid !== $sub->id) {
                        continue;
                    }
                    $children[] = (array) $child;
                }

                if (! empty($children)) {
                    $subNode['child'] = $children;
                }
                $subs[] = $subNode;
            }

            if (! empty($subs)) {
                $node['sub'] = $subs;
            }
            $tree[] = $node;
        }

        return $tree;
    }
}
