<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Terbitkan token Open API untuk seorang vendor.
 *
 *   php artisan apiclient:issue Cekat sandbox
 *   php artisan apiclient:issue Cekat live
 *
 * Token plaintext hanya ditampilkan SEKALI di sini; yang disimpan ke DB
 * hanya hash sha256-nya. Kirim token ke vendor lewat channel aman.
 */
class IssueApiClientToken extends Command
{
    protected $signature = 'apiclient:issue
                            {name : Nama vendor, mis. Cekat}
                            {environment=sandbox : live atau sandbox}';

    protected $description = 'Terbitkan token Open API baru untuk vendor (disimpan ter-hash di api_clients)';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $env  = strtolower(trim((string) $this->argument('environment')));

        if ($name === '') {
            $this->error('Nama vendor wajib diisi.');
            return self::FAILURE;
        }

        if (! in_array($env, ['live', 'sandbox'], true)) {
            $this->error("environment harus 'live' atau 'sandbox' (diberikan: {$env}).");
            return self::FAILURE;
        }

        $token = bin2hex(random_bytes(32)); // 64 hex char

        DB::table('api_clients')->insert([
            'id'          => (string) Str::uuid(),
            'name'        => $name,
            'token_hash'  => hash('sha256', $token),
            'environment' => $env,
            'is_active'   => 1,
            'created_at'  => now(),
        ]);

        $this->newLine();
        $this->info("Token {$env} untuk \"{$name}\" berhasil diterbitkan.");
        $this->warn('Simpan sekarang — token ini TIDAK akan ditampilkan lagi:');
        $this->newLine();
        $this->line("  {$token}");
        $this->newLine();
        $this->line('Header yang dipakai vendor:');
        $this->line("  Authorization: Bearer {$token}");
        $this->newLine();

        return self::SUCCESS;
    }
}
