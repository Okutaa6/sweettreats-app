<?php

namespace App\Console\Commands;

use App\Services\Accurate\AccurateClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PUSH sample harian LENGKAP dari Omnicare ke Accurate sandbox via API murni —
 * model 3 DOKUMEN per hari (bukan netting-1-faktur ala impor manual Finance):
 *
 *   1. sales-invoice/save.do   → revenue per akun (detailItem SVC-*)
 *   2. sales-receipt/save.do   → pembayaran per metode (bankNo + paymentMethod),
 *                                melunasi faktur hari itu
 *   3. journal-voucher/save.do → fee dokter: Dr 510101 Beban / Cr 210205 Hutang
 *
 * Hasil: laporan Accurate (penjualan per akun, laba-rugi, saldo bank) terisi
 * proper & otomatis — pengganti input manual Finance.
 *
 *   php artisan accurate:push-sample --from=bintaro --start=2026-04-01 --end=2026-04-30 --dry-run
 *   php artisan accurate:push-sample --from=bintaro --start=2026-04-01 --end=2026-04-30
 *   php artisan accurate:push-sample --clean
 */
class AccuratePushSample extends Command
{
    protected $signature = 'accurate:push-sample
                            {--from=bintaro : cabang sumber (Omnicare)}
                            {--to=testing : Data Usaha tujuan (sandbox)}
                            {--start=2026-04-01 : tanggal mulai}
                            {--end=2026-04-30 : tanggal akhir}
                            {--dry-run : tampilkan rencana tanpa kirim}
                            {--allow-live : WAJIB untuk push ke buku cabang asli (non-testing)}
                            {--clean : hapus semua dokumen hasil push di tujuan}
                            {--force : abaikan saklar accurate.push_enabled}';

    protected $description = 'Push sample 3-dokumen (invoice+receipt+journal fee dokter) Omnicare → Accurate sandbox';

    /** prefix kode Omnicare → akun revenue (tervalidasi vs Accurate April). */
    private array $prefix = [
        'PO'=>'400202','OT'=>'400202','CON'=>'400202','BC'=>'400201','CB'=>'400002','GP'=>'400006',
        'KS'=>'400305','OB'=>'400302','OD'=>'400005','OR'=>'400007','PK'=>'400101','PL'=>'400007',
        'PM'=>'400102','SA'=>'400003','SC'=>'400004','TB'=>'400001','PRO'=>'400102','PB'=>'400001',
        'PAK'=>'400101','PC'=>'400002','SCA'=>'400004','PSA'=>'400003',
    ];

    /**
     * Nomor akun kas/bank per Data Usaha — HASIL PREFLIGHT CoA asli + jawaban
     * Finance (2026-07-09): nomor BEDA antar cabang (110104 = BCA di Bintaro
     * tapi = BSI di Kalideres!). Sunter pakai rekening dr. Komalawaty, Cinere
     * dr. Ira, Pamulang tidak punya BCA (fallback BSI). Kliring 110196-8
     * dibuat seragam di semua buku (ACC Finance).
     */
    private array $bankCfg = [
        'testing'   => ['kas' => '110101', 'bca' => '110104', 'bsi' => '110107', 'mandiri' => '110199', 'fallback' => '110199'],
        'bintaro'   => ['kas' => '110101', 'bca' => '110104', 'bsi' => '110107', 'mandiri' => '110103', 'fallback' => '110104'],
        'sunter'    => ['kas' => '110101', 'bca' => '110104', 'bsi' => '110106', 'mandiri' => '110103', 'fallback' => '110104'],
        'kalideres' => ['kas' => '110101', 'bca' => '110103', 'bsi' => '110104', 'mandiri' => null, 'fallback' => '110103'],
        'cinere'    => ['kas' => '110101', 'bca' => '110104', 'bsi' => '110106', 'mandiri' => '110103', 'fallback' => '110106'],
        'pamulang'  => ['kas' => '110101', 'bca' => null, 'bsi' => '110104', 'mandiri' => '110103', 'fallback' => '110104'],
    ];

    /**
     * metode bayar Omnicare → [paymentMethod Accurate, bankNo per cabang].
     * Enum valid (diverifikasi empiris): BANK_CHEQUE, BANK_TRANSFER, EDC,
     * E_WALLET, DEBIT_CARD. (CASH/TRANSFER/OTHER dari spec DITOLAK API.)
     * Tunai → BANK_TRANSFER ke akun Kas (efek GL sama: kas bertambah).
     * QRIS/Debit BRI: tidak ada akun khusus di CoA → bank utama cabang
     * (dilaporkan ke Finance).
     */
    private function payTarget(string $method, string $slug): array
    {
        $cfg = $this->bankCfg[$slug] ?? $this->bankCfg['testing'];
        $m = strtolower($method);
        if (str_contains($m, 'voucher tunai')) return ['BANK_TRANSFER', '110196']; // Kliring Voucher Tunai
        if (str_contains($m, 'endorse')) return ['BANK_TRANSFER', '110197'];       // Kliring Endorsement
        if (str_contains($m, 'voucher') || str_contains($m, 'poin') || str_contains($m, 'cashback')) {
            return ['BANK_TRANSFER', '110198']; // Kliring Voucher & Poin (promo)
        }
        if ($m === 'tunai') return ['BANK_TRANSFER', $cfg['kas']];
        if (str_contains($m, 'qris')) return ['E_WALLET', $cfg['fallback']];
        if (str_contains($m, 'debit bca')) return ['DEBIT_CARD', $cfg['bca'] ?? $cfg['fallback']];
        if (str_contains($m, 'debit bsi')) return ['DEBIT_CARD', $cfg['bsi'] ?? $cfg['fallback']];
        if (str_contains($m, 'transfer bca')) return ['BANK_TRANSFER', $cfg['bca'] ?? $cfg['fallback']];
        if (str_contains($m, 'transfer bsi')) return ['BANK_TRANSFER', $cfg['bsi'] ?? $cfg['fallback']];
        if (str_contains($m, 'mandiri')) return ['BANK_TRANSFER', $cfg['mandiri'] ?? $cfg['fallback']];
        if (str_contains($m, 'debit')) return ['DEBIT_CARD', $cfg['fallback']];
        return ['BANK_TRANSFER', $cfg['fallback']]; // transfer lain / DP
    }

    public function handle(AccurateClient $client): int
    {
        // Saklar mati (Finance mapping ulang) hanya memagari jalur BUKU ASLI.
        // Sandbox/testing tetap boleh — mematikan itu juga cuma menghalangi
        // orang menguji mapping barunya, padahal itu justru pekerjaannya.
        // push-daily mewariskan --force karena sudah lolos gerbangnya sendiri.
        if ($this->option('allow-live') && ! config('accurate.push_enabled', true) && ! $this->option('force')) {
            $this->warn('Push ke buku ASLI SENGAJA dimatikan (ACCURATE_PUSH_ENABLED=false).');
            $this->line('Sandbox (--to=testing) tetap jalan. Paksa sekali dengan --force.');

            return self::SUCCESS;
        }

        $to = (string) $this->option('to');
        $isLive = $to !== 'testing';
        if ($isLive && ! $this->option('allow-live')) {
            $this->error("'{$to}' = buku cabang ASLI. Tambahkan --allow-live kalau memang yakin (atau --dry-run dulu).");
            return self::FAILURE;
        }
        if ($isLive && $to !== (string) $this->option('from')) {
            $this->error('Push live: --to harus sama dengan --from (tiap cabang ke bukunya sendiri).');
            return self::FAILURE;
        }
        $dest = DB::table('accurate_credentials')->where('slug', $to)->whereNotNull('api_token')->first();
        if (! $dest || empty($dest->host)) {
            $this->error("Kredensial/host '{$to}' belum siap.");
            return self::FAILURE;
        }

        if ($this->option('clean')) {
            return $this->clean($client, $dest);
        }

        $from = (string) $this->option('from');
        $src = DB::table('accurate_credentials')->where('slug', $from)->first();
        $placeId = (string) ($src->branch_place_id ?? '');
        if ($placeId === '') {
            $this->error("Cabang '{$from}' tidak punya branch_place_id.");
            return self::FAILURE;
        }
        $start = (string) $this->option('start');
        $end   = (string) $this->option('end');

        // resolver: nama layanan → akun asli Accurate (primary), fallback prefix/rules
        $svc = [];
        foreach (DB::table('accurate_staging_sales_item')->get(['service_name', 'account_no']) as $r) {
            $p = mb_strtolower(trim(explode(',', (string) $r->service_name)[0]));
            if ($p === '') continue;
            $svc[$p][(string) $r->account_no] = ($svc[$p][(string) $r->account_no] ?? 0) + 1;
        }
        foreach ($svc as $p => $a) { arsort($a); $svc[$p] = (string) array_key_first($a); }

        $itemMap = DB::table('accurate_item_account_map')->where('slug', $to)->pluck('item_no', 'account_no')->all();

        // hari = union(hari penjualan, hari komisi) — komisi basis "after payment"
        // bisa muncul di hari tanpa penjualan (mis. cabang tutup tapi ada
        // pembayaran masuk utk penjualan sebelumnya) → jurnal fee tetap dibuat.
        $salesDays = DB::table('omnicare_sales_detail')->where('place_id', $placeId)
            ->whereBetween('trx_date', [$start, $end])->distinct()->pluck('trx_date');
        $commDays = DB::table('omnicare_commission')->where('place_id', $placeId)
            ->whereBetween('trx_date', [$start, $end])->distinct()->pluck('trx_date');
        $days = $salesDays->merge($commDays)->map(fn ($d) => (string) $d)->unique()->sort()->values();

        $this->line("Push 3-dokumen {$from} (place {$placeId}) → {$to}, {$days->count()} hari ({$start}..{$end})");
        if (! $this->option('dry-run')) {
            $client->post($dest->host, '/api/customer/save.do', $dest->api_token, ['name' => 'Umum', 'customerNo' => 'C.00001']);
        }

        // kode cabang di nomor dokumen HANYA utk sandbox (1 Data Usaha utk 5
        // cabang). Push live per-buku → nomor normal PLJ{yymmdd}.
        $br = $isLive ? '' : ('-' . strtoupper(mb_substr($from, 0, 3))); // -BIN/-SUN/...

        $ok = 0; $fail = 0;
        foreach ($days as $date) {
            $ymd = Carbon::parse($date);
            $num = 'PLJ' . $br . $ymd->format('ymd');

            // ── data hari ini ──
            $rev = $this->revenueByAccount($placeId, $date, $svc);
            $R = array_sum($rev);
            $pays = $this->paymentsByMethod($placeId, $date);
            $fee = (float) DB::table('omnicare_commission')->where('place_id', $placeId)->where('trx_date', $date)->sum('commission_amount');
            if ($R <= 0 && $fee <= 0) continue;

            if ($this->option('dry-run')) {
                $this->line(sprintf('  %s  rev=%s (%d akun)  bayar=%s (%d metode)  fee=%s',
                    $num, number_format($R), count($rev), number_format(array_sum(array_column($pays, 'amount'))), count($pays), number_format($fee)));
                continue;
            }

            // ── 1+2. INVOICE + RECEIPTS (hanya kalau ada penjualan hari itu) ──
            // Idempoten: kalau faktur nomor ini masih 'pushed' (mis. repair
            // fee-only yang tidak menghapus invoice), skip ke jurnal — jangan
            // gagal di duplikat nomor lalu meninggalkan hari tanpa jurnal.
            $rcptOk = 0;
            $invExists = DB::table('accurate_push_log')->where('slug', $to)
                ->where('invoice_number', $num)->where('source_slug', 'omnicare-v2-invoice')
                ->where('status', 'pushed')->exists();
            if ($R > 0 && $invExists) {
                $this->line("  · {$num} faktur sudah ada (skip invoice+receipt, lanjut jurnal)");
            }
            if ($R > 0 && ! $invExists) {
                // live: penjualan Voucher Tunai lewat detailExpense 210110
                // (persis model faktur asli Finance — item VCH tidak diperlukan)
                $voucher = 0.0;
                $revItems = $rev;
                if ($isLive && isset($revItems['210110'])) {
                    $voucher = $revItems['210110'];
                    unset($revItems['210110']);
                }
                // live: WAJIB semua akun punya item asli (SVC-* cuma ada di sandbox)
                $missing = array_diff(array_keys($revItems), array_keys($itemMap));
                if ($isLive && $missing) {
                    $this->error("  ✗ {$num}: akun tanpa item di buku {$to}: " . implode(',', $missing) . ' — jalankan scan item dulu.');
                    $fail++;
                    continue;
                }
                $detailItem = [];
                foreach ($revItems as $acc => $amt) {
                    // useTax1=false: buku asli punya PPN nempel di item — faktur
                    // harian Finance TANPA pajak, jadi dimatikan per baris.
                    $detailItem[] = ['itemNo' => $itemMap[$acc] ?? 'SVC-400305', 'quantity' => 1,
                        'unitPrice' => round($amt, 2), 'useTax1' => false,
                        'detailName' => 'Pendapatan akun ' . $acc];
                }
                $payload = [
                    'customerNo' => 'C.00001', 'transDate' => $ymd->format('d/m/Y'), 'number' => $num,
                    'taxable' => false,
                    'description' => 'Penjualan ' . ucfirst($from) . ' ' . $ymd->format('d M Y') . ' — Data Center (Omnicare)',
                    'detailItem' => $detailItem,
                ];
                if ($voucher > 0) {
                    $payload['detailExpense'] = [[
                        'accountNo' => '210110', 'expenseAmount' => round($voucher, 2),
                        'detailName' => 'Penjualan Voucher Tunai',
                    ]];
                }
                $inv = $client->post($dest->host, '/api/sales-invoice/save.do', $dest->api_token, $payload);
                if (! $inv->isSuccess()) {
                    $this->error("  ✗ {$num} invoice: " . mb_substr((string) $inv->error, 0, 100));
                    $fail++;
                    continue;
                }
                $invId = $inv->record['id'] ?? null;
                $this->logPush($to, $date, $num, $invId, 'invoice', $R);

                // Pembayaran hari X bisa > invoice hari X (bayar DP/cicilan invoice
                // hari lain). Alokasi ke invoice di-cap sisa piutangnya; kelebihan
                // tetap dicatat sebagai penerimaan (invoice-link sebagian).
                $remaining = $R; // sisa piutang faktur hari ini
                foreach ($pays as $i => $p) {
                    [$pm, $bank] = $this->payTarget($p['method'], $to);
                    $rNum = 'RCP' . $br . $ymd->format('ymd') . '-' . ($i + 1);
                    $alloc = min($p['amount'], max($remaining, 0));
                    if ($alloc <= 0) {
                        // piutang hari ini sudah lunas — sisa pembayaran = untuk
                        // invoice hari lain; di sample dilewati agar buku seimbang.
                        $this->line("    · {$p['method']} " . number_format($p['amount']) . ' → piutang hari ini sudah lunas, skip (bayar utk invoice hari lain)');
                        continue;
                    }
                    $remaining -= $alloc;
                    $rc = $client->post($dest->host, '/api/sales-receipt/save.do', $dest->api_token, [
                        'customerNo' => 'C.00001', 'transDate' => $ymd->format('d/m/Y'), 'number' => $rNum,
                        'bankNo' => $bank, 'paymentMethod' => $pm, 'chequeAmount' => round($alloc, 2),
                        'description' => 'Pembayaran ' . $p['method'] . ' ' . $ymd->format('d M Y'),
                        'detailInvoice' => [[
                            'invoiceNo' => $num, 'paymentAmount' => round($alloc, 2),
                        ]],
                    ]);
                    if ($rc->isSuccess()) {
                        $rcptOk++;
                        $this->logPush($to, $date, $rNum, $rc->record['id'] ?? null, 'receipt', $alloc);
                    } else {
                        $this->warn("    ⚠ receipt {$p['method']}: " . mb_substr((string) $rc->error, 0, 90));
                    }
                }
            }

            // ── 3. JOURNAL fee dokter ──
            $jvOk = '-';
            $jNum = 'JVF' . $br . $ymd->format('ymd');
            // idempoten: skip kalau jurnal nomor ini sudah pernah ke-push
            $jvExists = DB::table('accurate_push_log')->where('slug', $to)
                ->where('invoice_number', $jNum)->where('status', 'pushed')->exists();
            if ($jvExists) {
                $jvOk = '✓(ada)';
            } elseif ($fee > 0) {
                // format terverifikasi: amount positif = debit, negatif = kredit
                $jv = $client->post($dest->host, '/api/journal-voucher/save.do', $dest->api_token, [
                    'transDate' => $ymd->format('d/m/Y'), 'number' => $jNum,
                    'description' => 'Fee dokter ' . ucfirst($from) . ' ' . $ymd->format('d M Y'),
                    'detailJournalVoucher' => [
                        ['accountNo' => '510101', 'amount' => round($fee, 2), 'description' => 'Beban fee dokter'],
                        ['accountNo' => '210205', 'amount' => -round($fee, 2), 'description' => 'Hutang fee dokter'],
                    ],
                ]);
                $jvOk = $jv->isSuccess() ? '✓' : '✗' . mb_substr((string) $jv->error, 0, 60);
                if ($jv->isSuccess()) {
                    $this->logPush($to, $date, $jNum, $jv->record['id'] ?? null, 'journal', $fee);
                }
            }

            $this->line(sprintf('  ✓ %s  inv=%s  receipt=%d/%d  jurnal-fee=%s',
                $num, number_format($R), $rcptOk, count($pays), $jvOk));
            $ok++;
            usleep(250000);
        }

        $this->info("SELESAI — {$ok} hari terkirim, {$fail} gagal." . ($this->option('dry-run') ? ' (dry-run)' : ''));
        if ($ok > 0) $this->line('Rollback: php artisan accurate:push-sample --clean');
        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    // ────────────────────────────────────────────────────────────────

    private function revenueByAccount(string $placeId, string $date, array $svc): array
    {
        // Termasuk penjualan Voucher Tunai (item_type V) → 210110 Uang Muka
        // Penjualan (permintaan Finance: masuk faktur, bukan di-exclude).
        // Konsekuensi bagus: subTotal faktur = total pembayaran hari itu →
        // receipt teralokasi penuh.
        $rev = [];
        foreach (DB::table('omnicare_sales_detail')->where('place_id', $placeId)->where('trx_date', $date)
            ->get(['item_name', 'item_code', 'item_type', 'net_sell_price']) as $r) {
            if ($r->item_type === 'V') {
                $rev['210110'] = ($rev['210110'] ?? 0) + (float) $r->net_sell_price;
                continue;
            }
            $a = $this->resolve($r->item_name, $r->item_code, $svc);
            if ($a === null) continue;
            $rev[$a] = ($rev[$a] ?? 0) + (float) $r->net_sell_price;
        }
        return array_filter($rev, fn ($v) => $v > 0);
    }

    /** @return array<int,array{method:string,amount:float}> */
    private function paymentsByMethod(string $placeId, string $date): array
    {
        $out = [];
        foreach (DB::table('omnicare_sales_payments')->where('place_id', $placeId)->where('invoice_date', $date)
            ->where('invoice_status', 'AKTIF')
            ->select('payment_method')->selectRaw('SUM(actual_amount) s')->groupBy('payment_method')->get() as $p) {
            if ((float) $p->s <= 0) continue; // refund net-negatif per metode: skip (edge case, manual)
            $out[] = ['method' => (string) $p->payment_method, 'amount' => (float) $p->s];
        }
        return $out;
    }

    private function resolve(string $name, ?string $code, array $svc): ?string
    {
        $n = mb_strtolower(trim($name));
        if (str_starts_with($n, 'request pengembalian deposit') || str_starts_with($n, 'request kelebihan bayar')) return null;
        if (isset($svc[$n])) return $svc[$n];
        if (str_starts_with($n, 'service charge') || preg_match('/apd|swab antigen/i', $n)) return '400305';
        if ($code && preg_match('/^\d{0,2}([A-Z]+)\d+/', $code, $m) && isset($this->prefix[$m[1]])) return $this->prefix[$m[1]];
        if (preg_match('/sikat gigi|pasta gigi|wax|brush|floss|propolis|orthokit|tongue|retainer|totebag|oxyfresh|mini screw|dr\.brush|interdental/i', $n)) return '400304';
        return '400305';
    }

    private function logPush(string $to, string $date, string $number, $accId, string $kind, float $amount): void
    {
        DB::table('accurate_push_log')->insert([
            'slug' => $to, 'source_slug' => 'omnicare-v2-' . $kind, 'trans_date' => $date,
            'invoice_number' => $number, 'accurate_invoice_id' => $accId, 'status' => 'pushed',
            'item_lines' => 0, 'gross_amount' => $amount, 'message' => '3-dokumen sample',
        ]);
    }

    private function clean(AccurateClient $client, object $dest): int
    {
        // hapus urutan terbalik: receipt & journal dulu, baru invoice
        $order = ['omnicare-v2-receipt' => '/api/sales-receipt/delete.do',
                  'omnicare-v2-journal' => '/api/journal-voucher/delete.do',
                  'omnicare-v2-invoice' => '/api/sales-invoice/delete.do',
                  'omnicare-sample' => '/api/sales-invoice/delete.do'];
        $total = 0;
        foreach ($order as $srcSlug => $ep) {
            $rows = DB::table('accurate_push_log')->where('slug', $dest->slug)->where('source_slug', $srcSlug)
                ->whereNotNull('accurate_invoice_id')->where('status', '!=', 'deleted')->get()->unique('accurate_invoice_id');
            foreach ($rows as $row) {
                $r = $client->get($dest->host, $ep, $dest->api_token, ['id' => $row->accurate_invoice_id]);
                if ($r->isSuccess()) {
                    DB::table('accurate_push_log')->where('accurate_invoice_id', $row->accurate_invoice_id)
                        ->where('source_slug', $srcSlug)->update(['status' => 'deleted']);
                    $total++;
                }
                usleep(150000);
            }
        }
        $this->info("Dihapus {$total} dokumen.");
        return self::SUCCESS;
    }
}
