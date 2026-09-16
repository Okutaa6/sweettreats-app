<?php

namespace App\Console\Commands;

use App\Support\OmnicareEncounters;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reconciliation engine — cocokkan reservasi SweetTreats dengan encounter
 * (kunjungan nyata) di Omnicare. Menjawab: "pasien yang booking, jadi datang?
 * Sesuai cabang? Berapa yang dia bayar?"
 *
 *   php artisan omnicare:reconcile                      # 30 hari ke belakang
 *   php artisan omnicare:reconcile --days=90            # window lebih panjang
 *   php artisan omnicare:reconcile --dry-run            # hitung saja
 *   php artisan omnicare:reconcile --rematch            # hapus hasil sistem, ulang
 *   php artisan omnicare:reconcile --revisit --days=90  # TINJAU ULANG vonis lama
 *   php artisan omnicare:reconcile --perbaiki-batal     # lepas cap no-show dari pembatalan
 *
 * Jembatan identitas: raw_cus_reservation → raw_customer → customer_omnicare_link
 * → omnicare_pid → omnicare_sales_detail.p_id. Tanpa Step 2 (match-patients),
 * command ini tidak punya pegangan siapa-siapa.
 *
 * Hasil per reservasi (di reservation_omnicare_link):
 *   - linked   : encounter ketemu (omnicare_record_id terisi)
 *   - no_show  : window sudah lewat, tidak ada encounter
 *   - cancelled: reservasi DIBATALKAN — bukan no-show (lihat di bawah)
 *   - conflict : >1 encounter kandidat sama kuat → match_confidence='low'
 *   - no_match : customer belum ter-link ke Omnicare
 *
 * Jadwal: jalan 03:00 setelah omnicare:sync 02:00 (lihat Kernel.php).
 *
 * ────────────────────────────────────────────────────────────────────────────
 * ⭐ PERUBAHAN 2026-09-16 — TIGA hal
 * ────────────────────────────────────────────────────────────────────────────
 *
 * 1. BANYAK pid per pasien. Omnicare menerbitkan p_id per cabang, jadi satu
 *    pasien bisa punya beberapa. Versi lama memakai leftJoin ke
 *    customer_omnicare_link dan mengambil satu `omnicare_pid` — sesudah tabel
 *    itu boleh berisi banyak baris per pasien, leftJoin yang sama akan
 *    MENGGANDAKAN reservasinya (1 reservasi → N baris vonis). Karena itu
 *    join-nya dibuang dan pid dikumpulkan lebih dulu sebagai peta.
 *    Kunjungan dicari di SELURUH pid milik pasien itu.
 *
 * 2. ⛔ RESERVASI DIBATALKAN BUKAN KETIDAKHADIRAN. 385 reservasi ber-status
 *    `cancelled` ikut divonis `orphan_noshow` (12 Jun–10 Sep). Pasiennya
 *    memberi tahu; mencampurnya menaikkan angka no-show dari 18,2% menjadi
 *    26,8% — dan angka yang kebesaran itu sempat dipakai berhari-hari.
 *    Sekarang pembatalan mendapat vonis sendiri: `cancelled`.
 *    ⚠️ Pembatalan yang TETAP datang tetap di-link seperti biasa — yang
 *    berubah hanya vonis saat tidak ada kunjungan sama sekali.
 *
 * 3. VONIS LAMA BISA DITINJAU ULANG (`--revisit`). Dulu `no_customer_link`
 *    bersifat permanen: reservasi yang divonis begitu tak pernah dinilai lagi
 *    meski pasiennya kemudian tertaut. 284 baris dalam 3 bulan terakhir kena.
 *
 * ⛔ MATCH_WINDOW TIDAK DILEBARKAN dan jangan dilebarkan. 469 dari 594
 *    kegagalan penjodohan terjadi di hari yang PERSIS SAMA — jendela bukan
 *    penyebabnya, dan melebarkannya justru menambah jodoh palsu.
 */
class OmnicareReconcile extends Command
{
    protected $signature = 'omnicare:reconcile
                            {--days=30 : Lookback hari untuk reservasi yang diproses}
                            {--dry-run : Tidak tulis ke DB, hanya laporkan}
                            {--rematch : Hapus hasil reconcile sistem lalu ulang}
                            {--revisit : Tinjau ulang vonis no_customer_link & conflict_multi (tidak menyentuh yang sudah ter-link)}
                            {--perbaiki-batal : Lepas cap no-show dari reservasi yang DIBATALKAN, lalu berhenti}';

    protected $description = 'Cocokkan reservasi SweetTreats ↔ encounter Omnicare (reservation_omnicare_link)';

    /** Toleransi geser tanggal reservasi ↔ encounter (hari). */
    private const MATCH_WINDOW = 3;

    /**
     * Vonis yang BOLEH ditinjau ulang. Sengaja sempit.
     * ⛔ `link_*` TIDAK ADA di sini dan tidak boleh ditambahkan — meninjau
     *    ulang tautan yang sudah berhasil berarti membongkar atribusi
     *    pendapatan yang sudah dipakai orang.
     */
    private const VONIS_BISA_DITINJAU = ['no_customer_link', 'conflict_multi'];

    /**
     * Vonis yang menghasilkan baris TANPA encounter.
     *
     * ⛔ Tiap nilai di sini WAJIB punya ember sendiri di
     *    `ReconciliationController` — kalau tidak, reservasinya lenyap dari
     *    layar /reconcile tanpa jejak. Itu bukan bahaya hipotetis: vonis
     *    `cancelled` sempat begitu, jadi reservasi yang dulu tampil sebagai
     *    no-show akan HILANG begitu vonisnya dibetulkan, dan orang akan
     *    mengira datanya rusak alih-alih diperbaiki.
     *    Dijaga uji: tests/Feature/PenjodohanOmnicareTest.php
     */
    public const VONIS_TANPA_ENCOUNTER = [
        'no_customer_link',
        'orphan_noshow',
        'cancelled',
        'conflict_multi',
    ];

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $days  = max(1, (int) $this->option('days'));
        $today = Carbon::today();
        $from  = $today->copy()->subDays($days);

        $this->line('');
        $this->line(str_repeat('═', 64));
        $this->info('Omnicare Reconcile — ' . now()->format('Y-m-d H:i:s'));
        $this->line(str_repeat('═', 64));
        if ($this->dryRun) {
            $this->warn('▶ DRY RUN — tidak ada penulisan DB');
        }

        // ── Langkah 3a berdiri sendiri, supaya efeknya bisa dipisahkan ────
        if ($this->option('perbaiki-batal')) {
            return $this->perbaikiBatal();
        }

        $this->line("▶ Reservasi diproses: {$from->toDateString()} → {$today->toDateString()}");

        $noShowSebelum = $this->angkaNoShow();

        if ($this->option('rematch') && ! $this->dryRun) {
            $deleted = DB::table('reservation_omnicare_link')->where('reconciled_by', 'system')->delete();
            $this->warn("▶ --rematch: {$deleted} baris reconcile sistem dihapus");
        }

        // ── --revisit: buang HANYA vonis yang boleh ditinjau ulang ────────
        $ditinjauRes = $this->option('revisit') ? $this->buangVonisLama($from, $today) : [];
        $ditinjau    = count($ditinjauRes);

        $alreadyLinked = DB::table('reservation_omnicare_link')
            ->whereNotNull('raw_cus_reservation_id')
            ->pluck('raw_cus_reservation_id')
            ->flip();

        // ⚠️ Pada --dry-run, baris lamanya sengaja TIDAK dihapus — jadi tanpa
        //    baris ini reservasinya akan dianggap "sudah ter-reconcile" dan
        //    dry-run melapor 0 perubahan untuk pekerjaan yang sebenarnya akan
        //    menaut banyak. Runbook menyuruh dry-run DULU; ramalan yang diam
        //    membuat orang menyimpulkan perintahnya tidak berguna.
        if ($this->dryRun) {
            foreach ($ditinjauRes as $rid) {
                unset($alreadyLinked[$rid]);
            }
        }

        // ── Peta pid per pasien ───────────────────────────────────────────
        // ⛔ JANGAN kembalikan ini menjadi leftJoin. Satu pasien kini boleh
        //    punya banyak pid; leftJoin akan menggandakan tiap reservasi
        //    sebanyak pid pasiennya, dan tiap duplikat itu ditulis sebagai
        //    baris vonis tersendiri.
        $pidsPasien = [];
        foreach (DB::table('customer_omnicare_link')->select('raw_customer_id', 'omnicare_pid')->get() as $l) {
            $pidsPasien[$l->raw_customer_id][] = $l->omnicare_pid;
        }

        // Reservasi dalam scope: tanggal sudah lewat / hari ini, di 5 cabang ter-map
        $reservations = DB::table('raw_cus_reservation as r')
            ->join('raw_branch as b', 'b.id', '=', 'r.raw_branch_id')
            ->whereNotNull('b.omnicare_place_id')
            ->whereDate('r.date', '>=', $from)
            ->whereDate('r.date', '<=', $today)
            ->select(
                'r.id', 'r.date', 'r.raw_customer_id', 'r.status',
                'b.omnicare_place_id as place_id', 'b.name as branch_name'
            )
            ->orderBy('r.date')
            ->get();

        $stats = [
            'scanned'   => $reservations->count(),
            'already'   => 0,
            'linked'    => 0,
            'no_show'   => 0,
            'cancelled' => 0,
            'conflict'  => 0,
            'no_match'  => 0,
            'open'      => 0, // window belum tutup, biarkan
            'multi_pid' => 0, // reservasi yang tertolong karena pasiennya >1 pid
        ];

        $rows = [];

        foreach ($reservations as $r) {
            if (isset($alreadyLinked[$r->id])) {
                $stats['already']++;
                continue;
            }

            $resDate     = Carbon::parse($r->date);
            $windowClose = $resDate->copy()->addDays(self::MATCH_WINDOW);
            $windowOpen  = $windowClose->gte($today); // encounter masih bisa muncul
            $dibatalkan  = $this->dibatalkan($r->status);

            $pids = $pidsPasien[$r->raw_customer_id] ?? [];

            // ── Customer belum ter-link ke Omnicare → no_match ────────────
            if (empty($pids)) {
                $rows[] = $this->row($r, [
                    'omnicare_record_id' => null,
                    'match_confidence'   => 'no_match',
                    'match_method'       => 'no_customer_link',
                    'notes'              => 'Customer belum ter-link ke Omnicare (jalankan omnicare:match-patients atau review manual).',
                ]);
                $stats['no_match']++;
                continue;
            }

            // ── Kandidat encounter: kunjungan pasien dalam window tanggal ──
            //    Dicari di SEMUA pid miliknya — inilah perbaikan lintas-cabang.
            $candidates = $this->candidateEncounters(
                $pids,
                $resDate->copy()->subDays(self::MATCH_WINDOW),
                $windowClose
            );

            if (empty($candidates)) {
                if ($windowOpen) {
                    $stats['open']++; // belum bisa disimpulkan no-show
                    continue;
                }

                // ⛔ Pembatalan BUKAN ketidakhadiran.
                if ($dibatalkan) {
                    $rows[] = $this->row($r, [
                        'omnicare_record_id' => null,
                        'match_confidence'   => 'no_match',
                        'match_method'       => 'cancelled',
                        'notes'              => 'Reservasi DIBATALKAN — tidak dihitung sebagai tidak hadir.',
                    ]);
                    $stats['cancelled']++;
                    continue;
                }

                $rows[] = $this->row($r, [
                    'omnicare_record_id' => null,
                    'match_confidence'   => 'no_match',
                    'match_method'       => 'orphan_noshow',
                    'notes'              => 'Tidak ada encounter dalam ±' . self::MATCH_WINDOW . ' hari — kemungkinan no-show.',
                ]);
                $stats['no_show']++;
                continue;
            }

            if (count($pids) > 1) {
                $stats['multi_pid']++;
            }

            // ── Pilih kandidat terbaik: jarak tanggal terkecil, cabang cocok ──
            foreach ($candidates as &$cand) {
                $cand['date_dist']   = abs(Carbon::parse($cand['trx_date'])->diffInDays($resDate));
                $cand['same_branch'] = ((string) $cand['place_id'] === (string) $r->place_id);
            }
            unset($cand);

            usort($candidates, function ($a, $b) {
                return [$a['date_dist'], $a['same_branch'] ? 0 : 1]
                   <=> [$b['date_dist'], $b['same_branch'] ? 0 : 1];
            });

            $best = $candidates[0];

            // Ambigu: kandidat #2 sama jarak & sama status cabang → conflict
            $ambiguous = isset($candidates[1])
                && $candidates[1]['date_dist'] === $best['date_dist']
                && $candidates[1]['same_branch'] === $best['same_branch'];

            if ($ambiguous) {
                $list = array_map(
                    fn($c) => "{$c['encounter_id']} ({$c['trx_date']})",
                    array_slice($candidates, 0, 4)
                );
                $rows[] = $this->row($r, [
                    'omnicare_record_id' => null,
                    'match_confidence'   => 'low',
                    'match_method'       => 'conflict_multi',
                    'notes'              => 'Beberapa encounter sama kuat — perlu pilih manual: ' . implode(', ', $list),
                ]);
                $stats['conflict']++;
                continue;
            }

            // ── Linked ────────────────────────────────────────────────────
            $confidence = $this->confidence($best['date_dist'], $best['same_branch']);
            $method     = $best['same_branch'] ? 'link_date_branch' : 'link_date';
            $note       = $best['same_branch']
                ? null
                : "Cabang encounter ({$best['branch_name']}) beda dari reservasi ({$r->branch_name}).";

            $rows[] = $this->row($r, [
                'omnicare_record_id' => $best['encounter_id'],
                'match_confidence'   => $confidence,
                'match_method'       => $method,
                'actual_revenue'     => $best['revenue'],
                'payment_status'     => $best['payment_status'],
                'encounter_date'     => $best['trx_date'],
                'notes'              => $note,
            ]);
            $stats['linked']++;
        }

        if (! $this->dryRun && ! empty($rows)) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('reservation_omnicare_link')->insert($chunk);
            }
        }

        // ⚠️ Dry-run harus MERAMAL, bukan menyalin "sebelum" ke kolom "sesudah".
        //    Kolom kembar yang diam membuat orang menyimpulkan tidak ada yang
        //    akan berubah — dan mereka tidak akan menjalankan perintahnya.
        $noShowSesudah = $this->dryRun
            ? $this->ramalanNoShow($noShowSebelum, [
                'no_show'   => $stats['no_show'],
                'linked'    => $stats['linked'],
                'cancelled' => $stats['cancelled'],
            ])
            : $this->angkaNoShow();

        $this->report($stats, $ditinjau, $noShowSebelum, $noShowSesudah);

        return self::SUCCESS;
    }

    // ────────────────────────────────────────────────────────────────────────
    // LANGKAH 3a — pembatalan yang terlanjur dicap tidak hadir
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Lepas cap no-show dari reservasi yang ber-status `cancelled`.
     *
     * Dipisahkan dari alur utama supaya efeknya pada angka no-show bisa
     * dilaporkan TERPISAH dari efek multi-pid dan rerun — kalau ketiganya
     * jalan sekaligus, tak akan ketahuan mana yang menyebabkan apa.
     *
     * Idempoten: jalan kedua akan menemukan 0 baris.
     * ⛔ Hanya menyentuh baris `reconciled_by = 'system'`. Vonis yang pernah
     *    disentuh manusia (`manual_noshow`, pilihan encounter manual) tidak
     *    boleh ditimpa mesin.
     */
    private function perbaikiBatal(): int
    {
        $this->line('▶ LANGKAH 3a — melepas cap no-show dari reservasi yang DIBATALKAN');

        $sebelum = $this->angkaNoShow();

        $target = DB::table('reservation_omnicare_link as l')
            ->join('raw_cus_reservation as r', 'r.id', '=', 'l.raw_cus_reservation_id')
            ->where('l.match_method', 'orphan_noshow')
            ->where('l.reconciled_by', 'system')
            // LOWER() supaya penyaringan SQL ini sepakat dengan `dibatalkan()`
            // yang dipakai alur harian. Tanpa itu, satu jalur membetulkan
            // "Cancelled" dan jalur lain tidak — dan selisihnya tak akan pernah
            // kelihatan. (MySQL ci memaafkannya; jangan bergantung pada itu.)
            ->whereIn(DB::raw('LOWER(r.status)'), $this->statusBatal())
            ->pluck('l.id');

        $this->line('  ' . $target->count() . ' baris bercap orphan_noshow padahal reservasinya dibatalkan');

        $diubah = 0;
        if (! $this->dryRun && $target->isNotEmpty()) {
            foreach ($target->chunk(500) as $chunk) {
                $diubah += DB::table('reservation_omnicare_link')
                    ->whereIn('id', $chunk->all())
                    ->update([
                        'match_method' => 'cancelled',
                        'notes'        => 'Reservasi DIBATALKAN — cap "tidak hadir" dilepas ' . now()->toDateString() . '.',
                        'updated_at'   => now(),
                    ]);
            }
        }

        // Dry-run meramal: tiap baris target pindah dari ember no-show ke batal.
        $sesudah = $this->dryRun
            ? $this->ramalanNoShow(
                ['no_show' => $sebelum['no_show'] - $target->count(),
                 'linked'  => $sebelum['linked'],
                 'batal'   => $sebelum['batal']],
                ['no_show' => 0, 'linked' => 0, 'cancelled' => $target->count()]
            )
            : $this->angkaNoShow();

        $this->line('');
        $this->info('ANGKA NO-SHOW — sebelum vs sesudah koreksi pembatalan (kriteria terima #6)'
            . ($this->dryRun ? ' — kolom "Sesudah" adalah RAMALAN' : ''));
        $this->tabelNoShow($sebelum, $sesudah);

        if ($this->dryRun) {
            $this->warn('▶ DRY RUN — tidak ada baris diubah.');
        } else {
            $this->info("✓ {$diubah} baris diubah dari orphan_noshow → cancelled");
        }

        return self::SUCCESS;
    }

    // ────────────────────────────────────────────────────────────────────────
    // LANGKAH 3 — buang vonis lama yang boleh ditinjau ulang
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Hapus baris bervonis `no_customer_link` / `conflict_multi` dalam window,
     * supaya alur utama menilainya lagi dari nol.
     *
     * ⛔ Tiga pagar: hanya vonis di VONIS_BISA_DITINJAU · hanya
     *    `reconciled_by = 'system'` · hanya yang `omnicare_record_id` masih
     *    KOSONG. Baris yang sudah punya encounter tidak pernah disentuh,
     *    bahkan kalau seseorang salah menandainya.
     *
     * @return string[] id RESERVASI yang vonisnya dibuang (atau akan dibuang,
     *                  kalau --dry-run) — dipakai pemanggil supaya dry-run
     *                  ikut menilai ulang dan bisa meramalkan hasilnya.
     */
    private function buangVonisLama(Carbon $from, Carbon $today): array
    {
        $baris = DB::table('reservation_omnicare_link as l')
            ->join('raw_cus_reservation as r', 'r.id', '=', 'l.raw_cus_reservation_id')
            ->whereIn('l.match_method', self::VONIS_BISA_DITINJAU)
            ->where('l.reconciled_by', 'system')
            ->whereNull('l.omnicare_record_id')
            ->whereDate('r.date', '>=', $from)
            ->whereDate('r.date', '<=', $today)
            ->select('l.id', 'l.raw_cus_reservation_id')
            ->get();

        $this->warn('▶ --revisit: ' . $baris->count() . ' vonis lama akan dinilai ulang ('
            . implode(', ', self::VONIS_BISA_DITINJAU) . ')');

        $resIds = $baris->pluck('raw_cus_reservation_id')->filter()->unique()->values()->all();

        if ($this->dryRun || $baris->isEmpty()) {
            return $resIds;
        }

        foreach ($baris->pluck('id')->chunk(500) as $chunk) {
            DB::table('reservation_omnicare_link')->whereIn('id', $chunk->all())->delete();
        }

        return $resIds;
    }

    // ────────────────────────────────────────────────────────────────────────

    /** Status reservasi yang berarti DIBATALKAN. */
    private function statusBatal(): array
    {
        return ['cancelled', 'canceled', 'batal'];
    }

    private function dibatalkan(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), $this->statusBatal(), true);
    }

    /**
     * Angka no-show saat ini, dari reservation_omnicare_link.
     * Penyebutnya sengaja "reservasi yang sudah selesai dinilai" — linked +
     * no-show — supaya pembatalan tidak lagi mengambang di antara keduanya.
     */
    private function angkaNoShow(): array
    {
        $noShow = DB::table('reservation_omnicare_link')
            ->whereIn('match_method', ['orphan_noshow', 'manual_noshow'])->count();
        $linked = DB::table('reservation_omnicare_link')
            ->whereNotNull('omnicare_record_id')->count();
        $batal  = DB::table('reservation_omnicare_link')
            ->where('match_method', 'cancelled')->count();

        $penyebut = $linked + $noShow;

        return [
            'no_show'  => $noShow,
            'linked'   => $linked,
            'batal'    => $batal,
            'persen'   => $penyebut > 0 ? round($noShow / $penyebut * 100, 1) : 0.0,
        ];
    }

    /**
     * Ramalan angka no-show untuk --dry-run.
     *
     * `--revisit` hanya membuang vonis `no_customer_link` / `conflict_multi` —
     * dua-duanya TIDAK ikut dihitung sebagai no-show maupun ter-link, jadi
     * ramalannya cukup penjumlahan. Kalau suatu saat VONIS_BISA_DITINJAU
     * diperluas, rumus ini harus ikut ditinjau.
     *
     * @param array{no_show:int,linked:int,cancelled:int} $tambah
     */
    private function ramalanNoShow(array $sebelum, array $tambah): array
    {
        $noShow = $sebelum['no_show'] + $tambah['no_show'];
        $linked = $sebelum['linked']  + $tambah['linked'];
        $penyebut = $linked + $noShow;

        return [
            'no_show' => $noShow,
            'linked'  => $linked,
            'batal'   => $sebelum['batal'] + $tambah['cancelled'],
            'persen'  => $penyebut > 0 ? round($noShow / $penyebut * 100, 1) : 0.0,
        ];
    }

    private function tabelNoShow(array $a, array $b): void
    {
        $this->table(
            ['Metrik', 'Sebelum', 'Sesudah'],
            [
                ['Dicap tidak hadir',     number_format($a['no_show']), number_format($b['no_show'])],
                ['Ter-link (datang)',     number_format($a['linked']),  number_format($b['linked'])],
                ['Dibatalkan (terpisah)', number_format($a['batal']),   number_format($b['batal'])],
                ['ANGKA NO-SHOW',         $a['persen'] . '%',           $b['persen'] . '%'],
            ]
        );
    }

    /**
     * Encounter kandidat untuk satu pasien dalam rentang tanggal, di SELURUH
     * pid miliknya. Grouping per encounter_id + aturan revenue di
     * App\Support\OmnicareEncounters.
     *
     * @param string[] $pids
     */
    private function candidateEncounters(array $pids, Carbon $start, Carbon $end): array
    {
        if (empty($pids)) {
            return [];
        }

        $lines = DB::table('omnicare_sales_detail')
            ->whereIn('p_id', $pids)
            ->whereBetween('trx_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('encounter_id')
            ->where('encounter_id', '<>', '')
            ->select('encounter_id', 'trx_date', 'place_id', 'branch_name', 'doctor',
                     'net_sell_price', 'item_type', 'item_name', 'qty_sold', 'payment_status')
            ->get();

        return OmnicareEncounters::group($lines);
    }

    /** Confidence link berdasar jarak tanggal & kecocokan cabang. */
    private function confidence(int $dateDist, bool $sameBranch): string
    {
        if (! $sameBranch) {
            return 'low'; // datang ke cabang lain — worth review
        }
        return match (true) {
            $dateDist === 0 => 'exact',
            $dateDist <= 1  => 'high',
            default         => 'medium',
        };
    }

    /** Bangun satu baris reservation_omnicare_link dengan default kolom. */
    private function row(object $r, array $override): array
    {
        $now = now();
        return array_merge([
            'id'                     => (string) Str::uuid(),
            'raw_cus_reservation_id' => $r->id,
            'omnicare_record_id'     => null,
            'place_id'               => $r->place_id,
            'match_confidence'       => 'no_match',
            'match_method'           => null,
            'service_match'          => 'unknown',
            'expected_revenue'       => null,
            'actual_revenue'         => null,
            'revenue_variance'       => null,
            'payment_status'         => null,
            'encounter_date'         => null,
            'reconciled_at'          => $now,
            'reconciled_by'          => 'system',
            'notes'                  => null,
            'created_at'             => $now,
        ], $override);
    }

    private function report(array $s, int $ditinjau, array $noShowSebelum, array $noShowSesudah): void
    {
        $this->line('');
        $this->line(str_repeat('─', 64));
        $this->info('HASIL RECONCILE');
        $this->line(str_repeat('─', 64));
        $this->table(
            ['Status', 'Jumlah'],
            [
                ['Reservasi di-scan',            number_format($s['scanned'])],
                ['  ↳ sudah ter-reconcile',      number_format($s['already'])],
                ['  ↳ window masih terbuka',     number_format($s['open'])],
                ['LINKED (encounter ketemu)',    number_format($s['linked'])],
                ['  ↳ pasien punya >1 pid',      number_format($s['multi_pid'])],
                ['NO-SHOW (tidak datang)',       number_format($s['no_show'])],
                ['DIBATALKAN (bukan no-show)',   number_format($s['cancelled'])],
                ['CONFLICT (review manual)',     number_format($s['conflict'])],
                ['NO-MATCH (customer ?)',        number_format($s['no_match'])],
            ]
        );

        if ($ditinjau > 0) {
            $this->line("▶ --revisit: {$ditinjau} RESERVASI dinilai ulang "
                . '(⛔ baris yang sudah ter-link tidak disentuh)');
        }

        // Penyebut sengaja TIDAK memasukkan pembatalan — itu inti Langkah 3a.
        $processed = $s['linked'] + $s['no_show'] + $s['conflict'];
        if ($processed > 0) {
            $rate = round($s['linked'] / $processed * 100, 1);
            $this->line("▶ Conversion (linked / selesai): {$rate}%");
        }

        $this->line('');
        $this->info('ANGKA NO-SHOW menyeluruh (kriteria terima #6)'
            . ($this->dryRun ? ' — kolom "Sesudah" adalah RAMALAN' : ''));
        $this->tabelNoShow($noShowSebelum, $noShowSesudah);

        // Antrean manusia — jangan biarkan membesar diam-diam.
        $antrean = DB::table('reservation_omnicare_link')
            ->where('match_method', 'conflict_multi')
            ->whereNull('omnicare_record_id')
            ->count();
        $tanpaLink = DB::table('reservation_omnicare_link')
            ->where('match_method', 'no_customer_link')
            ->count();

        $this->line('');
        $this->warn("▶ MENUNGGU MANUSIA: {$antrean} conflict · {$tanpaLink} tanpa tautan pasien");
        $this->warn('  Daftar yang bisa dibuka: php artisan omnicare:tinjau-tautan --antrean');

        if ($this->dryRun) {
            $this->warn('▶ DRY RUN — tidak ada baris ditulis.');
        } else {
            $written = $s['linked'] + $s['no_show'] + $s['cancelled'] + $s['conflict'] + $s['no_match'];
            $this->info('✓ ' . number_format($written) . ' baris ditulis ke reservation_omnicare_link');
        }
    }
}
