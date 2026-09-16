<?php

namespace App\Console\Commands;

use App\Support\OmnicareIdentitas as Ident;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daftar yang MENUNGGU MANUSIA pada integrasi Omnicare.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * ⭐ KENAPA PERINTAH INI ADA
 * ────────────────────────────────────────────────────────────────────────────
 * Pola yang sedang kita perbaiki: mesin menyiapkan antrean kerja, antreannya
 * tak punya pintu. `matched_by` = 'system' untuk SELURUH 14.176 tautan — nol
 * koreksi manusia, selamanya. Kodenya menyisakan kasus ambigu "untuk review
 * manual", tapi layar reviewnya tidak pernah ada. Hal yang sama menimpa
 * /reconcile: antreannya ada, pintunya tidak.
 *
 * ⚠️ PERINTAH INI BUKAN LAYAR ITU. Ia hanya membuat antreannya BISA DIBACA,
 *    supaya angka yang tersisa tidak diam-diam membesar tanpa ada yang tahu.
 *    Layar tinjauan yang sesungguhnya — tempat orang MEMILIH, bukan sekadar
 *    melihat — masih utang. Jangan laporkan ini sebagai "review manual sudah
 *    ada".
 *
 *   php artisan omnicare:tinjau-tautan                 # ringkasan semua antrean
 *   php artisan omnicare:tinjau-tautan --antrean       # antrean rekonsiliasi
 *   php artisan omnicare:tinjau-tautan --dob           # LANGKAH 3b: tandai tautan rapuh
 *   php artisan omnicare:tinjau-tautan --dob --tulis   # sama, tapi benar-benar menandai
 */
class TinjauTautanOmnicare extends Command
{
    protected $signature = 'omnicare:tinjau-tautan
                            {--antrean : Cetak antrean rekonsiliasi (conflict & tanpa tautan pasien)}
                            {--dob : LANGKAH 3b — periksa tautan lama yang DOB-nya bertentangan}
                            {--tulis : Untuk --dob, benar-benar menulis review_flag (default: hanya melaporkan)}
                            {--limit=50 : Baris maksimum yang dicetak per daftar}';

    protected $description = 'Daftar antrean Omnicare yang menunggu manusia (conflict, tanpa tautan, tautan rapuh)';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $this->line('');
        $this->line(str_repeat('═', 64));
        $this->info('Antrean tinjauan Omnicare — ' . now()->format('Y-m-d H:i:s'));
        $this->line(str_repeat('═', 64));

        $adaPilihan = $this->option('antrean') || $this->option('dob');

        $this->ringkasan();

        if ($this->option('antrean') || ! $adaPilihan) {
            $this->antrean($limit);
        }

        if ($this->option('dob')) {
            $this->periksaDob($limit);
        } elseif (! $adaPilihan) {
            $this->line('');
            $this->line('▶ LANGKAH 3b (tautan lama yang DOB-nya bertentangan) tidak dijalankan.');
            $this->line('  Jalankan: php artisan omnicare:tinjau-tautan --dob');
        }

        $this->line('');
        $this->warn('⚠️ Ini DAFTAR, bukan layar keputusan. Tidak ada tombol di sini —');
        $this->warn('   apa pun yang muncul di atas masih menunggu orang, dan akan terus');
        $this->warn('   menunggu sampai layarnya dibuat.');

        return self::SUCCESS;
    }

    // ────────────────────────────────────────────────────────────────────────

    private function ringkasan(): void
    {
        $conflict = DB::table('reservation_omnicare_link')
            ->where('match_method', 'conflict_multi')->whereNull('omnicare_record_id')->count();
        $noLink = DB::table('reservation_omnicare_link')
            ->where('match_method', 'no_customer_link')->count();
        // `review_flag` datang dari 2026_09_16_customer_omnicare_link_multi_pid.sql.
        // Kalau SQL-nya belum dijalankan, katakan begitu — jangan periksa skema
        // lewat PHP (information_schema/SHOW COLUMNS terbukti mematikan mysqld
        // lokal di mesin ini).
        try {
            $ditandai = DB::table('customer_omnicare_link')->whereNotNull('review_flag')->count();
        } catch (\Throwable $e) {
            $ditandai = -1;
        }
        $manual = DB::table('customer_omnicare_link')->where('matched_by', '<>', 'system')->count();
        $total = DB::table('customer_omnicare_link')->count();

        $this->table(
            ['Antrean', 'Jumlah'],
            [
                ['Reservasi conflict (pilih encounter)',  number_format($conflict)],
                ['Reservasi tanpa tautan pasien',         number_format($noLink)],
                ['Tautan ditandai rapuh (Langkah 3b)',
                    $ditandai < 0 ? 'kolom review_flag BELUM ADA — jalankan SQL multi-pid dulu'
                                  : number_format($ditandai)],
                ['Tautan hasil koreksi MANUSIA',          number_format($manual) . ' dari ' . number_format($total)],
            ]
        );

        if ($manual === 0 && $total > 0) {
            $this->warn('⚠️ NOL koreksi manusia dari ' . number_format($total) . ' tautan. Itu bukan');
            $this->warn('   tanda mesinnya sempurna — itu tanda pintunya tidak pernah ada.');
        }
    }

    private function antrean(int $limit): void
    {
        $this->line('');
        $this->info('ANTREAN REKONSILIASI — reservasi yang menunggu dipilih manusia');

        $conflicts = DB::table('reservation_omnicare_link as l')
            ->join('raw_cus_reservation as r', 'r.id', '=', 'l.raw_cus_reservation_id')
            ->leftJoin('raw_customer as c', 'c.id', '=', 'r.raw_customer_id')
            ->leftJoin('raw_branch as b', 'b.id', '=', 'r.raw_branch_id')
            ->where('l.match_method', 'conflict_multi')
            ->whereNull('l.omnicare_record_id')
            ->orderByDesc('r.date')
            ->limit($limit)
            ->select('r.date', 'c.name', 'c.phone', 'b.name as cabang', 'l.notes')
            ->get();

        if ($conflicts->isEmpty()) {
            $this->line('  (kosong)');
        } else {
            $this->table(
                ['tanggal', 'pasien', 'nomor', 'cabang', 'kandidat'],
                $conflicts->map(fn($c) => [
                    $c->date, $c->name, $c->phone, $c->cabang,
                    \Illuminate\Support\Str::limit((string) $c->notes, 60),
                ])->all()
            );
        }

        $sisa = DB::table('reservation_omnicare_link')
            ->where('match_method', 'conflict_multi')->whereNull('omnicare_record_id')->count();
        if ($sisa > $limit) {
            $this->line('  … ' . ($sisa - $limit) . ' lagi (naikkan --limit)');
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // LANGKAH 3b — tautan lama yang dibuat tanpa DOB, kini bisa diuji
    // ────────────────────────────────────────────────────────────────────────

    /**
     * 7.729 tautan (54,5%) dibuat dengan `phone+name` saja — tanpa DOB.
     * Sesudah backfill DOB (Langkah 1), banyak di antaranya kini BISA diuji:
     * kalau DOB kita dan DOB Omnicare bertentangan, tautan itu patut dicurigai.
     *
     * ⛔ TIDAK MENGHAPUS APA PUN. Hanya menandai `review_flag`. Menghapus
     *    tautan yang salah lebih berbahaya daripada membiarkannya sampai ada
     *    manusia melihat — tautan yang hilang menjadikan pasien "belum pernah
     *    datang" dan menghapus riwayatnya dari layar, sementara tautan yang
     *    salah setidaknya kelihatan salah.
     */
    private function periksaDob(int $limit): void
    {
        $this->line('');
        $this->info('LANGKAH 3b — tautan lama yang DOB-nya BERTENTANGAN dengan Omnicare');

        $rows = DB::table('customer_omnicare_link as l')
            ->join('raw_customer as c', 'c.id', '=', 'l.raw_customer_id')
            ->join('omnicare_patient as p', 'p.pid', '=', 'l.omnicare_pid')
            ->whereNotNull('c.tanggal_lahir')
            ->whereNotNull('p.dob')
            ->select('l.id', 'l.raw_customer_id', 'l.omnicare_pid', 'l.match_method',
                     'c.name as nama_kita', 'c.tanggal_lahir',
                     'p.full_name as nama_omnicare', 'p.dob')
            ->get();

        // ⭐ DI-TRIASE, bukan ditumpuk. "Hari & bulan tertukar" itu salah
        //    TANGGAL; "lahir beda 30 tahun" itu salah ORANG. Menyebut keduanya
        //    "bertentangan" membuat angkanya terdengar jauh lebih menakutkan
        //    daripada kenyataannya, dan tindakannya pun berbeda.
        $kelompok = ['tertukar' => [], 'selisih_sehari' => [], 'bertentangan' => []];
        foreach ($rows as $r) {
            $jenis = Ident::bedaDob($r->tanggal_lahir, $r->dob);
            if ($jenis === 'sama') {
                continue;
            }
            $kelompok[$jenis][] = $r;
        }

        $semua = array_merge(...array_values($kelompok));

        $this->line('  ' . number_format($rows->count()) . ' tautan punya DOB di kedua sisi');
        $this->line('  ' . number_format(count($semua)) . ' di antaranya BERBEDA:');
        $this->table(
            ['jenis selisih', 'jumlah', 'artinya'],
            [
                ['hari & bulan TERTUKAR', count($kelompok['tertukar']),
                    'artefak format d/m/Y — hampir pasti orang yang SAMA'],
                ['selisih 1 hari', count($kelompok['selisih_sehari']),
                    'salah ketik / zona waktu — kemungkinan besar orang yang sama'],
                ['BERTENTANGAN', count($kelompok['bertentangan']),
                    '⛔ patut dicurigai sebagai orang yang BERBEDA'],
            ]
        );

        if (empty($semua)) {
            $this->info('✓ Tidak ada yang perlu ditandai.');
            return;
        }

        $baris = [];
        foreach ($kelompok as $jenis => $daftar) {
            foreach ($daftar as $r) {
                $baris[] = [
                    $jenis, $r->raw_customer_id, substr((string) $r->tanggal_lahir, 0, 10),
                    $r->omnicare_pid, substr((string) $r->dob, 0, 10),
                    $r->nama_kita, $r->nama_omnicare, $r->match_method,
                ];
            }
        }
        $this->table(
            ['jenis', 'pasien (kita)', 'DOB kita', 'pid', 'DOB Omnicare', 'nama kita', 'nama Omnicare', 'metode'],
            array_slice($baris, 0, $limit)
        );
        if (count($baris) > $limit) {
            $this->line('  … ' . (count($baris) - $limit) . ' lagi (naikkan --limit)');
        }

        if (! $this->option('tulis')) {
            $this->warn('▶ Hanya melaporkan. Tambahkan --tulis untuk menandai review_flag.');
            return;
        }

        $total = 0;
        foreach ($kelompok as $jenis => $daftar) {
            if (empty($daftar)) {
                continue;
            }
            foreach (array_chunk(array_column($daftar, 'id'), 500) as $chunk) {
                $total += DB::table('customer_omnicare_link')
                    ->whereIn('id', $chunk)
                    ->update(['review_flag' => 'dob_' . $jenis, 'updated_at' => now()]);
            }
        }
        $this->info("✓ {$total} tautan ditandai (`dob_tertukar` / `dob_selisih_sehari` / "
            . '`dob_bertentangan`) — MENUNGGU MANUSIA, tidak dihapus.');
    }
}
