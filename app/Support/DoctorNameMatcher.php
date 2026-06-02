<?php

namespace App\Support;

/**
 * Fuzzy match nama dokter antara Omnicare (Lokadok) dan SweetTreats.
 *
 * Pattern divergence yang ditangani (real-world findings dari Sunter/Bintaro
 * sync 2026-05-20):
 *   - Nickname suffix: "(ryan)" vs "(drg. Ryan)" → both stripped
 *   - Title prefix: "DR. drg. Komalawaty" vs "drg. Komalawaty" → DR. stripped on compare
 *   - Specialty format: "Sp. Ort" vs "Sp.Ort" vs "Sp.orto" → case+period normalized
 *   - Comma quirks: "Riyadi, Sp." vs "Riyadi Sp." → comma stripped
 *   - Double space typo: "Wahyuni  Tarihoran" → collapsed
 *   - Partial name: "drg. Denny" Omnicare vs "drg. Denny Heriawan" SweetTreats
 *     → token containment match
 *   - Spelling typo: "Stiefani" vs "Stiefany", "Adhi" vs "Adi" → Levenshtein ≤ 2
 *
 * IMPORTANT: Original strings TIDAK diubah. Normalisasi hanya untuk perbandingan.
 *
 * Confidence levels yang dikembalikan match():
 *   - 100 = exact setelah normalisasi (paling aman, auto-link)
 *   - 90  = token containment (semua token name pendek ada di name panjang)
 *   - 70  = Levenshtein typo (1-2 char diff)
 *   - 0   = no match
 */
final class DoctorNameMatcher
{
    /**
     * Stop tokens yang di-strip saat tokenize signature.
     * Cover: prefix gelar, specialty Indonesian dental practice.
     */
    private const STOP_TOKENS = [
        // Gelar prefix
        'dr', 'drg', 'prof',
        // Specialty suffixes (case-insensitive, sudah lowercased)
        'sp', 'spkga', 'spkg', 'spkga', 'spbm', 'spbmm', 'sport', 'sporto',
        'ort', 'orto', 'pros', 'kg', 'kga', 'bm', 'bmm', 'pedo', 'perio',
        'endo', 'konservasi',
    ];

    /**
     * Generate canonical signature dari nama untuk perbandingan.
     * Output: lowercase string, parens stripped, punctuation removed,
     * stop tokens removed, whitespace normalized.
     *
     * Contoh:
     *   "DR. drg. Komalawaty Sp. Ort"           → "komalawaty"
     *   "drg. Rachmad Farulian Nasution (ryan)" → "rachmad farulian nasution"
     *   "drg. Nugroho Ahmad Riyadi, Sp. Ort"    → "nugroho ahmad riyadi"
     */
    public static function signature(string $name): string
    {
        $s = strtolower($name);
        // Strip parenthesized content (nickname like "(drg. Ryan)" or "(ryan)")
        $s = preg_replace('/\([^)]*\)/', ' ', $s);
        // Strip commas, periods
        $s = str_replace([',', '.'], ' ', $s);
        // Collapse whitespace
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);

        if ($s === '') {
            return '';
        }

        // Tokenize and remove stop tokens
        $tokens = explode(' ', $s);
        $significant = [];
        foreach ($tokens as $t) {
            if ($t === '' || in_array($t, self::STOP_TOKENS, true)) {
                continue;
            }
            $significant[] = $t;
        }

        return implode(' ', $significant);
    }

    /**
     * Compare dua nama dokter. Return confidence 0-100.
     *
     * Tier-1: exact match setelah normalisasi → 100
     * Tier-2: shorter name's tokens fully contained in longer → 90
     * Tier-3: Levenshtein ≤ 2 untuk handle typo → 70
     * Else: 0
     */
    public static function compare(string $a, string $b): int
    {
        $sa = self::signature($a);
        $sb = self::signature($b);

        if ($sa === '' || $sb === '') {
            return 0;
        }

        // Tier 1: exact after normalization
        if ($sa === $sb) {
            return 100;
        }

        // Tier 2: token containment
        $tokensA = explode(' ', $sa);
        $tokensB = explode(' ', $sb);
        $shorter = count($tokensA) <= count($tokensB) ? $tokensA : $tokensB;
        $longer  = count($tokensA) <= count($tokensB) ? $tokensB : $tokensA;

        $allInLonger = true;
        foreach ($shorter as $t) {
            if (! in_array($t, $longer, true)) {
                $allInLonger = false;
                break;
            }
        }
        if ($allInLonger && count($shorter) >= 1) {
            // Anti-collision: butuh setidaknya 1 token dengan length ≥ 4
            // (hindari false match karena "ali" cocok dengan "ali budiman" + "ali wibowo")
            $hasSignificantToken = false;
            foreach ($shorter as $t) {
                if (strlen($t) >= 4) {
                    $hasSignificantToken = true;
                    break;
                }
            }
            if ($hasSignificantToken) {
                return 90;
            }
        }

        // Tier 3: Levenshtein for typo handling
        // Hanya kalau kedua signature cukup panjang untuk hindari false positive
        if (strlen($sa) >= 6 && strlen($sb) >= 6) {
            $maxLen = max(strlen($sa), strlen($sb));
            // Allow more distance untuk string lebih panjang
            $threshold = $maxLen >= 15 ? 2 : 1;
            $dist = levenshtein($sa, $sb);
            if ($dist > 0 && $dist <= $threshold) {
                return 70;
            }
        }

        return 0;
    }

    /**
     * Find best match dari list of candidate names.
     *
     * @param string $target Nama yang dicari match-nya
     * @param array $candidates Array of [id => name]
     * @return array{id: mixed, name: string, confidence: int}|null
     *         NULL kalau tidak ada match ATAU ada ambiguity (multiple high-confidence)
     */
    public static function bestMatch(string $target, array $candidates): ?array
    {
        $matches = [];
        foreach ($candidates as $id => $name) {
            $conf = self::compare($target, $name);
            if ($conf > 0) {
                $matches[] = ['id' => $id, 'name' => $name, 'confidence' => $conf];
            }
        }

        if (empty($matches)) {
            return null;
        }

        // Sort by confidence desc
        usort($matches, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        $top = $matches[0];

        // Ambiguity check: kalau ada >1 match dengan top confidence yang sama tinggi → reject
        // (kita prefer leave unmapped daripada salah link)
        $topConfidence = $top['confidence'];
        $topCount = 0;
        foreach ($matches as $m) {
            if ($m['confidence'] === $topConfidence) {
                $topCount++;
            }
        }
        if ($topCount > 1) {
            return null;
        }

        return $top;
    }
}
