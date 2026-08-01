<?php

namespace App\Console\Commands;

use App\Models\Vendor;
use App\Services\Accurate\AccurateClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * rop:tarik-vendor
 *
 * Isi master vendor ROP dari Accurate (`/api/vendor/list.do`) supaya pengaju
 * memilih nama penerima yang sama persis dengan yang dipakai pembukuan —
 * bukan mengetik ulang dengan ejaan sendiri.
 *
 * Vendor dianggap satu berdasarkan NAMA (tanpa peduli besar-kecil huruf), bukan
 * `accurate_id`. Alasannya: supplier yang sama terdaftar di tiap buku cabang
 * dengan id sendiri-sendiri, jadi dedup per id membuat satu nama muncul enam
 * kali di combobox pengaju. Yang disimpan baris pertama yang ditemui; buku
 * asalnya dicatat di slug_sumber sebagai jejak.
 *
 * Catatan fase 2: saat push ke Accurate dinyalakan, tiap buku butuh id vendor
 * versinya sendiri — itu perlu tabel pemetaan terpisah, bukan mengubah tabel
 * ini. Untuk v1 vendor hanya bantuan pengetikan nama penerima.
 *
 * Vendor buatan tangan (sumber=manual) TIDAK pernah disentuh perintah ini.
 *
 * Contoh:
 *   php artisan rop:tarik-vendor                 # semua buku aktif
 *   php artisan rop:tarik-vendor --slug=holding  # satu buku saja
 *   php artisan rop:tarik-vendor --dry-run       # lihat dulu, jangan simpan
 */
class RopTarikVendor extends Command
{
    protected $signature = 'rop:tarik-vendor
                            {--slug= : Tarik dari satu buku Accurate saja}
                            {--dry-run : Tampilkan hasil tanpa menyimpan}';

    protected $description = 'Tarik master vendor dari Accurate untuk dipakai di Pengajuan/ROP';

    public function handle(AccurateClient $client): int
    {
        $kredensial = DB::table('accurate_credentials')
            ->where('is_active', 1)
            ->whereNotNull('api_token')
            ->when($this->option('slug'), fn ($q, $s) => $q->where('slug', $s))
            ->get();

        if ($kredensial->isEmpty()) {
            $this->error('Tidak ada kredensial Accurate aktif' . ($this->option('slug') ? ' untuk slug itu.' : '.'));
            return self::FAILURE;
        }

        $kering = (bool) $this->option('dry-run');
        $baru = $diperbarui = $gagal = 0;

        foreach ($kredensial as $cred) {
            $this->line("Buku <info>{$cred->slug}</info> …");

            $host = $cred->host;
            if (! $host) {
                try {
                    $host = $client->resolveHost($cred->api_token)['host'];
                } catch (\Throwable $e) {
                    $this->error("  gagal resolve host: {$e->getMessage()}");
                    $gagal++;
                    continue;
                }
            }

            $halaman = 1;
            $dariBuku = 0;

            do {
                $resp = $client->get($host, '/api/vendor/list.do', $cred->api_token, [
                    'fields'   => 'id,name,vendorNo',
                    'sp.page'  => $halaman,
                    'sp.pageSize' => 100,
                ]);

                if (! $resp->success) {
                    $this->error("  gagal ambil halaman {$halaman}: " . ($resp->error ?? 'tidak diketahui'));
                    $gagal++;
                    break;
                }

                foreach ($resp->data as $row) {
                    $row = (array) $row;
                    $accurateId = isset($row['id']) ? (string) $row['id'] : null;
                    $nama = trim((string) ($row['name'] ?? ''));
                    if ($nama === '' || ! $accurateId) {
                        continue;
                    }
                    $dariBuku++;

                    if ($kering) {
                        continue;
                    }

                    // Dicari per nama supaya supplier yang sama di banyak buku
                    // tidak jadi banyak baris di combobox pengaju. Satu vendor
                    // bermasalah tidak boleh menghentikan seluruh penarikan.
                    try {
                        $vendor = Vendor::where('nama', $nama)->first();

                        if ($vendor) {
                            // Yang sudah ada cuma disegarkan penanda sinkronnya.
                            // Nama & sumbernya tidak ditimpa — vendor yang pernah
                            // dirapikan manual jangan dikembalikan ke ejaan Accurate.
                            $vendor->forceFill(['last_synced_at' => now()])->save();
                            $diperbarui++;
                            continue;
                        }

                        Vendor::create([
                            'nama'           => $nama,
                            'accurate_id'    => $accurateId,
                            'accurate_no'    => $row['vendorNo'] ?? null,
                            'slug_sumber'    => $cred->slug,
                            'sumber'         => Vendor::SUMBER_ACCURATE,
                            'last_synced_at' => now(),
                        ]);
                        $baru++;
                    } catch (\Throwable $e) {
                        $this->warn("  lewati \"{$nama}\": " . $e->getMessage());
                        $gagal++;
                    }
                }

                $totalHalaman = $resp->pageCount ?? 1;
                $halaman++;
            } while ($halaman <= $totalHalaman);

            $this->line("  {$dariBuku} vendor terbaca.");
        }

        if ($kering) {
            $this->info('Uji coba selesai — tidak ada yang disimpan.');
            return self::SUCCESS;
        }

        $this->info("Selesai: {$baru} vendor baru, {$diperbarui} diperbarui"
            . ($gagal ? ", {$gagal} buku gagal" : '') . '.');
        $this->line('Total vendor aktif sekarang: ' . Vendor::aktif()->count());

        return $gagal && ! $baru && ! $diperbarui ? self::FAILURE : self::SUCCESS;
    }
}
