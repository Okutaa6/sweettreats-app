<?php

namespace App\Console\Commands;

use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Auto-match pasien SweetTreats (raw_customer, dari sheets) ke pasien
 * Omnicare (omnicare_patient, dari API) — orang yang SAMA, ID beda.
 *
 *   php artisan omnicare:match-patients              # match yang belum ke-link
 *   php artisan omnicare:match-patients --rematch    # hapus link 'system' lalu match ulang
 *   php artisan omnicare:match-patients --dry-run    # hitung saja, tidak tulis DB
 *
 * Strategi (lihat customer_omnicare_link SQL):
 *   - Phone = GATE. Tanpa kecocokan nomor (normalized), tidak dipertimbangkan.
 *     Alasan: model contact-anchored (1 nomor banyak pasien) bikin phone
 *     PERLU tapi TIDAK cukup — nama/DOB yang membedakan anggota keluarga.
 *   - Skor 0-100: phone 30 + DOB 35 + nama 35 (exact). Auto-link bila ≥ 60.
 *   - Ambiguous (2 kandidat skor mirip) TIDAK di-link — disisakan review manual.
 *   - 1:1 dijaga UNIQUE di tabel; bentrok di-resolve pakai skor tertinggi.
 */
class MatchOmnicarePatients extends Command
{
    protected $signature = 'omnicare:match-patients
                            {--rematch : Hapus link buatan sistem lalu match ulang dari nol}
                            {--dry-run : Tidak tulis ke DB, hanya laporkan hasil}
                            {--min-score=60 : Skor minimum untuk auto-link (default 60)}';

    protected $description = 'Auto-match raw_customer ↔ omnicare_patient (phone+DOB+nama) ke customer_omnicare_link';

    /** Selisih skor minimum antara kandidat #1 dan #2 supaya tidak dianggap ambigu. */
    private const AMBIGUITY_MARGIN = 10;

    private bool $dryRun = false;
    private int  $minScore = 60;

    public function handle(): int
    {
        $this->dryRun  = (bool) $this->option('dry-run');
        $this->minScore = max(40, (int) $this->option('min-score'));

        $this->line('');
        $this->line(str_repeat('═', 64));
        $this->info('Omnicare Patient Match — ' . now()->format('Y-m-d H:i:s'));
        $this->line(str_repeat('═', 64));
        if ($this->dryRun) {
            $this->warn('▶ DRY RUN — tidak ada penulisan DB');
        }

        if ($this->option('rematch') && ! $this->dryRun) {
            $deleted = DB::table('customer_omnicare_link')->where('matched_by', 'system')->delete();
            $this->warn("▶ --rematch: {$deleted} link buatan sistem dihapus");
        }

        // ── Link yang sudah ada (jangan ditimpa, jaga idempoten) ──────────
        $linkedCustomers = DB::table('customer_omnicare_link')->pluck('raw_customer_id')->flip();
        $linkedPids      = DB::table('customer_omnicare_link')->pluck('omnicare_pid')->flip();

        // ── Index pasien Omnicare berdasarkan phone key ───────────────────
        $this->line('▶ Memuat omnicare_patient & membangun index nomor…');
        $phoneIndex = $this->buildPhoneIndex($linkedPids);
        $this->line('  ' . count($phoneIndex) . ' nomor unik ter-index');

        // ── Iterasi raw_customer ──────────────────────────────────────────
        $customers = DB::table('raw_customer')
            ->select('id', 'name', 'phone', 'tanggal_lahir')
            ->get();

        $stats = [
            'total'        => $customers->count(),
            'already'      => 0,
            'no_phone'     => 0,
            'no_candidate' => 0,
            'below_score'  => 0,
            'ambiguous'    => 0,
            'matched'      => 0,
        ];

        // staged[omnicarePid] = baris link kandidat terbaik (resolve bentrok by skor)
        $staged = [];

        foreach ($customers as $c) {
            if (isset($linkedCustomers[$c->id])) {
                $stats['already']++;
                continue;
            }

            $keys = $this->phoneKeys($c->phone);
            if (empty($keys)) {
                $stats['no_phone']++;
                continue;
            }

            // Kumpulkan kandidat unik dari semua phone key customer ini
            $candidates = [];
            foreach ($keys as $k) {
                foreach ($phoneIndex[$k] ?? [] as $p) {
                    $candidates[$p['pid']] = $p;
                }
            }
            if (empty($candidates)) {
                $stats['no_candidate']++;
                continue;
            }

            // Skor tiap kandidat
            $scored = [];
            foreach ($candidates as $p) {
                $scored[] = $this->score($c, $p);
            }
            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

            $best = $scored[0];
            if ($best['score'] < $this->minScore) {
                $stats['below_score']++;
                continue;
            }

            // Guard ambiguitas: kandidat #2 terlalu dekat → bukan auto-link
            if (isset($scored[1])
                && $scored[1]['score'] >= $this->minScore
                && ($best['score'] - $scored[1]['score']) < self::AMBIGUITY_MARGIN) {
                $stats['ambiguous']++;
                continue;
            }

            $row = [
                'raw_customer_id'  => $c->id,
                'omnicare_pid'     => $best['pid'],
                'match_confidence' => $this->tier($best['score']),
                'match_method'     => $best['method'],
                'match_score'      => $best['score'],
            ];

            // Bentrok: pid yang sama diklaim 2 customer → simpan skor tertinggi
            if (isset($staged[$best['pid']])) {
                if ($best['score'] > $staged[$best['pid']]['match_score']) {
                    $staged[$best['pid']] = $row;
                }
                // selisih kecil pun: pemenang tetap yang lebih tinggi; idempoten cukup
            } else {
                $staged[$best['pid']] = $row;
            }
        }

        $stats['matched'] = count($staged);

        // ── Tulis hasil ───────────────────────────────────────────────────
        if (! $this->dryRun && ! empty($staged)) {
            $now = now();
            $rows = [];
            foreach ($staged as $r) {
                $rows[] = $r + [
                    'id'         => (string) Str::uuid(),
                    'matched_at' => $now,
                    'matched_by' => 'system',
                    'created_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('customer_omnicare_link')->insert($chunk);
            }
        }

        $this->report($stats, $staged);

        return self::SUCCESS;
    }

    /**
     * Index omnicare_patient: phoneKey → array kandidat.
     * Pakai mobile_number DAN whatsapp_number (keduanya bisa jadi anchor).
     * Pasien yang sudah ke-link di-skip.
     */
    private function buildPhoneIndex($linkedPids): array
    {
        $index = [];

        DB::table('omnicare_patient')
            ->select('pid', 'full_name', 'dob', 'mobile_number', 'whatsapp_number')
            ->orderBy('pid')
            ->chunk(2000, function ($patients) use (&$index, $linkedPids) {
                foreach ($patients as $p) {
                    if (isset($linkedPids[$p->pid])) {
                        continue;
                    }
                    $cand = [
                        'pid'      => $p->pid,
                        'name'     => $this->normName($p->full_name),
                        'tokens'   => $this->tokenSet($p->full_name),
                        'dob'      => $this->validDob($p->dob),
                    ];
                    foreach ([$p->mobile_number, $p->whatsapp_number] as $raw) {
                        foreach ($this->phoneKeys($raw) as $k) {
                            $index[$k][$p->pid] = $cand;
                        }
                    }
                }
            });

        // Buang keying-by-pid jadi list biasa
        foreach ($index as $k => $byPid) {
            $index[$k] = array_values($byPid);
        }

        return $index;
    }

    /**
     * Skor satu pasangan customer↔pasien. Total 0-100.
     *   phone 30 (selalu kena — index berbasis phone) + DOB 35 + nama ≤35.
     */
    private function score(object $c, array $p): array
    {
        $score   = 30;            // phone gate — sudah pasti cocok di sini
        $parts   = ['phone'];

        // DOB
        $cDob = $this->validDob($c->tanggal_lahir);
        if ($cDob !== null && $p['dob'] !== null && $cDob === $p['dob']) {
            $score += 35;
            $parts[] = 'dob';
        }

        // Nama
        [$nameScore, $nameTag] = $this->nameScore($c->name, $p);
        $score += $nameScore;
        if ($nameTag !== null) {
            $parts[] = $nameTag;
        }

        return [
            'pid'    => $p['pid'],
            'score'  => min(100, $score),
            'method' => implode('+', $parts),
        ];
    }

    /**
     * Skor nama: exact/token-set 35, subset 25, fuzzy 15, else 0.
     * @return array{0:int,1:?string}
     */
    private function nameScore(?string $rawName, array $p): array
    {
        $cName   = $this->normName($rawName);
        $cTokens = $this->tokenSet($rawName);
        if ($cName === '' || empty($p['tokens'])) {
            return [0, null];
        }

        // Exact atau token-set sama (urutan kata berbeda dianggap sama)
        if ($cName === $p['name'] || $cTokens === $p['tokens']) {
            return [35, 'name'];
        }

        // Subset: semua token yang lebih pendek ada di yang lebih panjang
        $short = count($cTokens) <= count($p['tokens']) ? $cTokens : $p['tokens'];
        $long  = count($cTokens) <= count($p['tokens']) ? $p['tokens'] : $cTokens;
        if (count($short) >= 2 && count(array_intersect($short, $long)) === count($short)) {
            return [25, 'name~sub'];
        }

        // Fuzzy: kemiripan string keseluruhan ≥ 0.82
        similar_text($cName, $p['name'], $pct);
        if ($pct >= 82.0) {
            return [15, 'name~fuzzy'];
        }

        return [0, null];
    }

    /** Confidence tier dari skor numerik. */
    private function tier(int $score): string
    {
        return match (true) {
            $score >= 100 => 'exact',
            $score >= 80  => 'high',
            default       => 'medium',
        };
    }

    /**
     * Phone key untuk join. Pakai Phone::normalize (standar app) + 1 varian
     * untuk typo umum '00xxx' (orang ketik 0 dua kali) → '0xxx'.
     */
    private function phoneKeys(?string $raw): array
    {
        $keys = [];

        $n = Phone::normalize($raw);
        if ($n !== null) {
            $keys[$n] = true;
        }

        $d = preg_replace('/\D/', '', (string) $raw);
        if (strlen((string) $d) > 3 && str_starts_with((string) $d, '00') && ! str_starts_with((string) $d, '0062')) {
            $n2 = Phone::normalize(substr($d, 1)); // buang satu '0' di depan
            if ($n2 !== null) {
                $keys[$n2] = true;
            }
        }

        return array_keys($keys);
    }

    /** Tanggal lahir valid → string Y-m-d, atau null. Buang 0000-00-00 & tahun absurd. */
    private function validDob($dob): ?string
    {
        if (empty($dob)) {
            return null;
        }
        $s = substr((string) $dob, 0, 10);
        if ($s === '0000-00-00') {
            return null;
        }
        $year = (int) substr($s, 0, 4);
        if ($year < 1900 || $year > (int) date('Y')) {
            return null;
        }
        return $s;
    }

    /**
     * Normalisasi nama: lowercase, buang non-alfanumerik, rapikan spasi.
     * Sekaligus runtuhkan nama ganda "X X" — ~32% omnicare_patient punya
     * first_name == last_name sehingga full_name jadi tertulis dua kali
     * ("Nazwa Sabrina Nazwa Sabrina"). Tanpa ini, fuzzy/subset rusak.
     */
    private function normName(?string $name): string
    {
        $s = strtolower(trim((string) $name));
        $s = preg_replace('/[^a-z0-9\s]/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);
        if ($s === '') {
            return '';
        }

        $w = explode(' ', $s);
        $n = count($w);
        if ($n >= 2 && $n % 2 === 0) {
            $half = $n / 2;
            if (array_slice($w, 0, $half) === array_slice($w, $half)) {
                $s = implode(' ', array_slice($w, 0, $half));
            }
        }

        return $s;
    }

    /** Token nama unik & terurut — untuk perbandingan tahan-urutan. */
    private function tokenSet(?string $name): array
    {
        $norm = $this->normName($name);
        if ($norm === '') {
            return [];
        }
        $tokens = array_unique(explode(' ', $norm));
        sort($tokens);
        return $tokens;
    }

    private function report(array $s, array $staged): void
    {
        $tiers = ['exact' => 0, 'high' => 0, 'medium' => 0];
        foreach ($staged as $r) {
            $tiers[$r['match_confidence']]++;
        }

        $this->line('');
        $this->line(str_repeat('─', 64));
        $this->info('HASIL MATCHING');
        $this->line(str_repeat('─', 64));
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['raw_customer diproses',        number_format($s['total'])],
                ['  ↳ sudah ter-link (skip)',    number_format($s['already'])],
                ['  ↳ tanpa nomor valid',        number_format($s['no_phone'])],
                ['  ↳ nomor tak ada di Omnicare', number_format($s['no_candidate'])],
                ['  ↳ skor di bawah ambang',     number_format($s['below_score'])],
                ['  ↳ ambigu (perlu manual)',    number_format($s['ambiguous'])],
                ['MATCH baru', number_format($s['matched'])],
                ['  ↳ exact (100)',  number_format($tiers['exact'])],
                ['  ↳ high (80-99)', number_format($tiers['high'])],
                ['  ↳ medium (60-79)', number_format($tiers['medium'])],
            ]
        );

        $totalLinkable = $s['total'] - $s['already'];
        if ($totalLinkable > 0) {
            $rate = round($s['matched'] / $totalLinkable * 100, 1);
            $this->line("▶ Match rate: {$rate}% dari {$totalLinkable} customer belum ter-link");
        }
        if ($this->dryRun) {
            $this->warn('▶ DRY RUN — tidak ada baris ditulis. Jalankan tanpa --dry-run untuk commit.');
        } else {
            $this->info('✓ ' . number_format($s['matched']) . ' baris ditulis ke customer_omnicare_link');
        }
    }
}
