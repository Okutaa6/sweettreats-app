<?php

namespace App\Console\Commands;

use App\Services\Accurate\AccurateClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Resolve + cache host data usaha untuk tiap API Token di accurate_credentials.
 *
 * Sekaligus jadi validasi token: kalau token salah/dicabut, resolve gagal dan
 * dilaporkan per-cabang. Host disimpan untuk dipakai command pull berikutnya.
 *
 *   php artisan accurate:resolve-host --all
 *   php artisan accurate:resolve-host --slug=holding
 */
class AccurateResolveHost extends Command
{
    protected $signature = 'accurate:resolve-host
                            {--slug= : Resolve satu Data Usaha saja (mis. holding)}
                            {--all : Resolve semua Data Usaha aktif}
                            {--force : Resolve ulang walau host masih fresh}';

    protected $description = 'Resolve + cache host data usaha Accurate dari API Token (sekaligus validasi token)';

    public function handle(AccurateClient $client): int
    {
        $slug  = $this->option('slug');
        $all   = $this->option('all');
        $force = $this->option('force');

        if (! $slug && ! $all) {
            $this->error('Pilih --slug=<nama> atau --all.');
            return self::FAILURE;
        }

        $query = DB::table('accurate_credentials')->where('is_active', 1);
        if ($slug) {
            $query->where('slug', $slug);
        }
        $creds = $query->orderBy('slug')->get();

        if ($creds->isEmpty()) {
            $this->error('Tidak ada kredensial yang cocok.');
            return self::FAILURE;
        }

        $refreshDays = (int) config('accurate.host_refresh_days', 30);
        $rows = [];
        $ok = 0; $fail = 0; $skip = 0;

        foreach ($creds as $c) {
            if (empty($c->api_token)) {
                $rows[] = [$c->slug, 'SKIP', '— (token kosong)'];
                $skip++;
                continue;
            }

            $fresh = $c->host
                && $c->host_checked_at
                && now()->diffInDays($c->host_checked_at) < $refreshDays;

            if ($fresh && ! $force) {
                $rows[] = [$c->slug, 'CACHED', $c->host];
                $skip++;
                continue;
            }

            try {
                $info = $client->resolveHost($c->api_token);
                DB::table('accurate_credentials')->where('id', $c->id)->update([
                    'host'            => $info['host'],
                    'db_numeric_id'   => $info['id'],
                    'host_checked_at' => now(),
                    'updated_at'      => now(),
                ]);
                $note = $info['host'];
                if ($info['licenseEnd']) {
                    $note .= '  (lisensi s/d ' . $info['licenseEnd'] . ($info['trial'] ? ', TRIAL' : '') . ')';
                }
                $rows[] = [$c->slug, 'OK', $note];
                $ok++;
            } catch (\Throwable $e) {
                $rows[] = [$c->slug, 'FAIL', mb_substr($e->getMessage(), 0, 70)];
                $fail++;
            }
        }

        $this->table(['Data Usaha', 'Status', 'Host / Keterangan'], $rows);
        $this->line(sprintf('OK: %d   FAIL: %d   SKIP: %d', $ok, $fail, $skip));

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
