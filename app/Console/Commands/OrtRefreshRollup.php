<?php

namespace App\Console\Commands;

use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtRollup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hitung ulang — atau PERIKSA — kolom ringkasan `ort_case`.
 *
 * (Kelasnya sengaja tidak bernama OrtRollup: nama itu sudah dipakai
 *  service-nya, dan berkas ini mengimpor keduanya.)
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KENAPA PERINTAH INI ADA
 * ────────────────────────────────────────────────────────────────────────────
 * Kolom `owner_place_id`, `open_control_id`, `visit_count_180d` dan
 * kawan-kawannya adalah TURUNAN YANG DISIMPAN. Bukan demi kecepatan render —
 * ketiganya dipakai sebagai PENYARING (antrean memilih `open_control_id IS
 * NULL`, "Jarang kontrol" memilih `visit_count_180d < 2`, tab cabang memilih
 * `owner_place_id`), dan menyaring pada nilai yang dihitung saat baca berarti
 * pindai seluruh tabel.
 *
 * Harganya: jalur tulis yang lupa memanggil refresh() membuat halaman
 * BERBOHONG TANPA ERROR. Tidak ada log, tidak ada 500 — cuma angka yang salah
 * dan Sales yang pelan-pelan berhenti percaya. `--verify` adalah satu-satunya
 * cara mengetahuinya.
 *
 * Ada juga yang basi SENDIRINYA, tanpa ada yang menulis apa pun:
 * `visit_count_180d` menyusut karena WAKTU BERJALAN. Pasien bisa jatuh ke
 * "Jarang kontrol" di hari Minggu ketika tidak ada satu baris pun berubah.
 * Tarikan malam menyegarkan cabang yang ia sentuh; perintah ini untuk
 * menyegarkan sisanya, atau seluruhnya sesudah pemeliharaan.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * --verify TIDAK MENULIS APA PUN
 * ────────────────────────────────────────────────────────────────────────────
 * Cara kerja OrtRollup::verifikasi() adalah menghitung ULANG lalu membandingkan
 * dengan yang tersimpan — jadi ia memperbaiki sambil memeriksa. Itu tidak boleh
 * terjadi di balik nama "verify": perintah pemeriksaan yang diam-diam mengubah
 * data adalah perintah yang tidak bisa dipakai untuk menyelidiki masalah.
 *
 * Karena itu seluruh pemeriksaan dibungkus transaksi yang SELALU dibatalkan.
 * Logikanya tetap satu (tidak ada salinan kedua yang bisa menyimpang), tapi
 * hasilnya benar-benar baca-saja. Mau memperbaikinya? Jalankan tanpa --verify.
 */
class OrtRefreshRollup extends Command
{
    protected $signature = 'ort:rollup
                            {--cabang= : batasi ke satu place_id}
                            {--verify : PERIKSA penyimpangan, tidak menulis apa pun}';

    protected $description = 'Hitung ulang (atau periksa) kolom ringkasan ort_case';

    public function __construct(
        private OrtRollup $rollup,
        private OrtBranches $branches,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('ort_case')) {
            $this->error('Tabel ort_case belum ada. Jalankan SQL-nya dulu.');

            return self::FAILURE;
        }

        $cabang = $this->option('cabang') ?: null;
        $label  = $cabang ? $this->branches->nama($cabang) : 'semua cabang';

        return $this->option('verify')
            ? $this->periksa($cabang, $label)
            : $this->segarkan($cabang, $label);
    }

    // ════════════════════════════════════════════════════════════════════════

    private function segarkan(?string $cabang, string $label): int
    {
        $this->info("Menghitung ulang ringkasan · {$label}");

        $n = $this->rollup->refreshSemua($cabang);

        $this->info("{$n} kasus aktif disegarkan.");
        $this->line('Kasus yang sudah selesai sengaja dilewati — ringkasannya beku');
        $this->line('sejak ditutup, dan tidak ada yang bisa menggesernya lagi.');

        return self::SUCCESS;
    }

    private function periksa(?string $cabang, string $label): int
    {
        $this->info("Memeriksa ringkasan · {$label} · TIDAK menulis apa pun");
        $this->newLine();

        /*
         * Transaksi yang selalu dibatalkan — lihat catatan kepala berkas.
         * verifikasi() mendeteksi penyimpangan dengan cara MEMPERBAIKI lalu
         * membandingkan; rollback-lah yang membuat perintah ini baca-saja.
         */
        DB::beginTransaction();

        try {
            $masalah = $this->rollup->verifikasi($cabang);
        } finally {
            DB::rollBack();
        }

        if (! $masalah) {
            $this->info('Bersih — kolom ringkasan cocok dengan hitungan sebenarnya.');
            $this->catatanCakupan();

            return self::SUCCESS;
        }

        // Dikelompokkan per JENIS: seribu baris "cache visit_count melenceng"
        // adalah satu masalah dengan satu sebab, bukan seribu masalah.
        $perJenis = [];
        foreach ($masalah as $m) {
            $perJenis[$m['jenis']][] = $m;
        }

        $ringkas = [];
        foreach ($perJenis as $jenis => $daftar) {
            $ringkas[] = [$jenis, count($daftar), substr($daftar[0]['kasus'], 0, 8) . '… (' . $daftar[0]['nilai'] . ')'];
        }
        $this->table(['Jenis penyimpangan', 'Kasus', 'Contoh pertama'], $ringkas);

        $this->newLine();
        $this->warn(count($masalah) . ' penyimpangan pada ' . count($perJenis) . ' jenis.');

        if (isset($perJenis['kontrol terbuka ganda'])) {
            $this->warn('⚠ "kontrol terbuka ganda" TIDAK sembuh dengan `ort:rollup` biasa —');
            $this->warn('  itu pelanggaran aturan domain (satu kasus = satu jadwal terbuka),');
            $this->warn('  bukan cache yang basi. Tutup salah satunya dari layar dulu.');
        }

        $adaGantung = array_filter(array_keys($perJenis), fn ($j) => str_contains($j, 'jadwal terbuka'));
        if ($adaGantung) {
            $this->warn('⚠ "kasus selesai masih punya jadwal terbuka" juga bukan cache basi:');
            $this->warn('  penutupan otomatis (Lepas Bracket / paket Freedom) memang tidak');
            $this->warn('  menyentuh jadwal yang tersisa, sedangkan "Tandai Selesai" manual');
            $this->warn('  membatalkannya. Tidak terlihat di layar mana pun. Keputusan mau');
            $this->warn('  disamakan yang mana ada di pemilik produk.');
        }

        $sisaCache = array_filter(array_keys($perJenis), fn ($j) => str_starts_with($j, 'cache '));
        if ($sisaCache) {
            $this->newLine();
            $this->line('Penyimpangan ber-awalan "cache" sembuh dengan menjalankan');
            $this->line('`php artisan ort:rollup' . ($cabang ? ' --cabang=' . $cabang : '') . '` (tanpa --verify).');
        }

        $this->catatanCakupan();

        // Keluar bukan-nol supaya bisa dipakai sebagai pemeriksa kesehatan.
        return self::FAILURE;
    }

    private function catatanCakupan(): void
    {
        $this->newLine();
        $this->line('Cakupan: kolom ringkasan diperiksa untuk kasus AKTIF saja.');
        $this->line('Pemeriksaan "jadwal terbuka menggantung" berlaku untuk semua status.');
    }
}
