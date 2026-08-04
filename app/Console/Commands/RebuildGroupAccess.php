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

        // Rebuild PENUH = semua perubahan tertunda ikut terterapkan → bersihkan
        // penanda pending (banner "Perubahan belum berlaku" di halaman Akses).
        // Rebuild parsial (--group) tidak membersihkan: grup lain mungkin masih tertunda.
        if (! $this->option('group')) {
            @unlink($dir . '/.pending');
            Cache::forget('pk_kesehatan_total'); // badge Kesehatan ikut segar
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

        $paths = $this->denganLeluhurWadah(
            DB::table('access_path')->whereIn('id', $accessIds)->orderBy('urutan')->get()
        );

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

    /**
     * Lengkapi daftar node dengan LELUHUR yang tidak di-grant, sebagai WADAH saja.
     *
     * Kenapa perlu: pohon di bawah dibangun mulai dari node level-0 yang
     * di-grant. Kalau seseorang diberi halaman tapi induk/akarnya tidak ikut
     * diberi, halaman itu tak pernah muncul di menu — sementara di tabel akses
     * kelihatan lengkap. Gagal DIAM, dan sudah tiga kali menggigit dalam dua
     * hari: 15 node saat gerbong naik, menu personal role lama, lalu paket
     * sales Anisa & akses Doctor/Reservasi Irma yang di-ACC tapi tak terlihat.
     *
     * Kenapa tidak diselesaikan dengan meng-grant akarnya saja: sebuah node
     * bisa merangkap wadah DAN halaman. Akar "Data Center" dulu membawa link
     * `MetricaSetting`, jadi meng-grant akar = memberi halaman itu juga —
     * persis yang dikecualikan waktu Supervisor diberi paket sales. (Link itu
     * ternyata mati — rutenya tak ada — dan sudah dikosongkan lewat
     * `database/sql/2026_08_05_akses_data_center_root.sql`.) Prinsipnya tetap:
     * "tampilkan wadahnya" dan "beri halamannya" harus bisa diputus terpisah.
     *
     * Yang disuntikkan hanyalah node PEMBUNGKUS: `link` dikosongkan supaya
     * tidak menambah akses apa pun. AuthGuard menanyai tabel langsung (tak
     * lewat berkas ini), `hasMenuAccess()` mencocokkan link sehingga tak akan
     * cocok, dan perender menu memang mengabaikan node ber-link kosong.
     * Jadi ini murni soal TAMPIL, bukan soal izin.
     */
    private function denganLeluhurWadah(\Illuminate\Support\Collection $paths): \Illuminate\Support\Collection
    {
        $punya = $paths->keyBy('id');
        $tambahan = [];

        // Telusuri ke atas dari tiap node sampai akar; maksimal beberapa lapis
        // (pohon menu = parent → sub → child), penjaga anti-siklus tetap ada.
        foreach ($paths as $node) {
            $pid = $node->pid ?? null;
            $lapis = 0;
            while (! empty($pid) && $lapis < 5) {
                if ($punya->has($pid) || isset($tambahan[$pid])) {
                    $pid = ($punya[$pid] ?? $tambahan[$pid])->pid ?? null;
                } else {
                    $induk = DB::table('access_path')->where('id', $pid)->first();
                    if (! $induk) break;
                    $wadah = clone $induk;
                    $wadah->link = '';           // wadah, bukan halaman — tak memberi akses
                    $tambahan[$pid] = $wadah;
                    $pid = $induk->pid ?? null;
                }
                $lapis++;
            }
        }

        if (! $tambahan) {
            return $paths;
        }

        return $paths->concat(array_values($tambahan))
            ->sortBy(fn ($n) => (int) ($n->urutan ?? 0))
            ->values();
    }
}
