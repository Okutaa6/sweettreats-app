<?php

namespace App\Services\ItTracker;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Mesin fase IT Request Tracker — TERJEMAHAN SETIA dari implementasi rujukan
 * design_handoff_it_tracker/it-store.js (pathFor, RANK, waitOf, queuePos,
 * gateInfo, PRA/PASCA). Jangan menafsirkan ulang aturannya di sini; kalau
 * aturan berubah, ubah dulu rencananya (docs/rencana/rencana-it-tracker.md).
 *
 * Tiket direpresentasikan sebagai array asosiatif berbentuk sama dengan objek
 * JS-nya: id, title, source, pemohon (['name' => ..]), jenjang, prioIT, pic,
 * fase, faseSince (string ISO / Carbon), evaluator, fr01..fr07 (array payload).
 * ItTrackerService::viewTicket() yang merakit bentuk ini dari DB — mesin ini
 * murni, tanpa kueri, supaya bisa diuji unit tanpa database.
 */
class PhaseEngine
{
    /** k = kunci fase, t = judul templat, s = label pendek */
    public const PH = [
        ['k' => 'REQ',      't' => 'Requirement',       's' => 'Requirement'],
        ['k' => 'ANALISIS', 't' => 'Analysis & Design', 's' => 'Analisis'],
        ['k' => 'DEV',      't' => 'Development',       's' => 'Development'],
        ['k' => 'UJI',      't' => 'Internal Testing',  's' => 'Uji Internal'],
        ['k' => 'UAT',      't' => 'UAT',               's' => 'UAT'],
        ['k' => 'GOLIVE',   't' => 'Go Live',           's' => 'Go Live'],
        ['k' => 'EVALUASI', 't' => 'Evaluation',        's' => 'Evaluasi'],
    ];

    public const RANK = ['TRIASE' => 0, 'ANTRE' => 1, 'ANALISIS' => 2, 'DEV' => 3, 'UJI' => 4, 'UAT' => 5, 'GOLIVE' => 6, 'EVALUASI' => 7];

    /** Checklist Go-Live pra-deploy (FRM-IT-06 §B). auto = terisi sendiri. */
    public const PRA = [
        ['k' => 'uat',      't' => 'UAT disetujui', 'auto' => true],
        ['k' => 'backup',   't' => 'Backup tersedia'],
        ['k' => 'script',   't' => 'File/script siap', 'auto' => true],
        ['k' => 'akses',    't' => 'Akses & konfigurasi siap'],
        ['k' => 'panduan',  't' => 'Panduan pengguna'],
        ['k' => 'rollback', 't' => 'Rencana rollback siap'],
    ];

    /** Pemeriksaan pasca Go-Live (FRM-IT-06 §D) — termasuk pelajaran rsync 29 Agu. */
    public const PASCA = [
        ['k' => 'akses',  't' => 'Aplikasi dapat diakses — curl beranda 200 + URL akhir'],
        ['k' => 'fungsi', 't' => 'Fungsi utama berjalan'],
        ['k' => 'data',   't' => 'Data / integrasi benar'],
        ['k' => 'hak',    't' => 'Hak akses sesuai'],
        ['k' => 'cabang', 't' => 'Cabang target terverifikasi'],
    ];

    public static function phShort(string $k): string
    {
        foreach (self::PH as $p) if ($p['k'] === $k) return $p['s'];
        return $k;
    }

    public static function phLong(string $k): string
    {
        foreach (self::PH as $p) if ($p['k'] === $k) return $p['t'];
        return $k;
    }

    /** TRIASE & ANTRE hidup di kolom Requirement papan. */
    public static function phaseOf(string $f): string
    {
        return ($f === 'TRIASE' || $f === 'ANTRE') ? 'REQ' : $f;
    }

    /** Jenjang menentukan fase yang dilewati: S 2, M 5, L 7. */
    public static function pathFor(?string $j): array
    {
        if ($j === 'S') return ['REQ', 'DEV'];
        if ($j === 'M') return ['REQ', 'ANALISIS', 'DEV', 'UAT', 'GOLIVE'];
        return ['REQ', 'ANALISIS', 'DEV', 'UJI', 'UAT', 'GOLIVE', 'EVALUASI'];
    }

    public static function rankOf(?string $f): int
    {
        return self::RANK[$f] ?? -1;
    }

    /** Aktif = di salah satu fase berjalan (bukan SELESAI/DITUNDA/DITOLAK). */
    public static function isActive(array $t): bool
    {
        return isset(self::RANK[$t['fase']]);
    }

    /** "Di tangan IT" = sudah lolos antre (ANALISIS ke atas). */
    public static function inHand(array $tickets): int
    {
        $n = 0;
        foreach ($tickets as $t) {
            if (self::isActive($t) && self::RANK[$t['fase']] >= 2) $n++;
        }
        return $n;
    }

    /** Kemajuan "UAT · 4/5" — posisi fase di jalur jenjangnya. */
    public static function progressOf(array $t): ?array
    {
        if (empty($t['jenjang'])) return null;
        $path = self::pathFor($t['jenjang']);
        $i = array_search(self::phaseOf($t['fase']), $path, true);
        if ($i === false) return null;

        return [
            'i'     => $i + 1,
            'n'     => count($path),
            'label' => self::phShort(self::phaseOf($t['fase'])) . ' · ' . ($i + 1) . '/' . count($path),
        ];
    }

    /**
     * "Menunggu siapa, sejak berapa hari" — dihitung, bukan diisi.
     * Memanas HANYA bila penunggunya di luar IT:
     *   heat = luar ? (days >= 7 ? 2 : days >= 3 ? 1 : 0) : 0
     * Kendali negatif uji terima no. 9: tiket 30 hari di DEV wajib heat=0.
     */
    public static function waitOf(array $t, ?CarbonInterface $now = null): array
    {
        $now   = $now ? CarbonImmutable::instance($now) : CarbonImmutable::now();
        $since = CarbonImmutable::parse($t['faseSince']);
        $days  = max(0, (int) floor($since->diffInSeconds($now) / 86400));
        $pem   = $t['pemohon']['name'] ?? 'pemohon';

        $party = 'IT';
        $luar  = false;
        $label = '';

        switch ($t['fase']) {
            case 'TRIASE':   $label = 'Menunggu triase IT'; break;
            case 'ANTRE':    $party = null; $label = 'Antre'; break;
            case 'ANALISIS': $party = 'IT + pemohon'; $label = 'Digodok IT + ' . $pem; break;
            case 'DEV':
            case 'UJI':      $label = 'Di tangan IT'; break;
            case 'UAT':      $party = $pem; $luar = true; $label = 'Menunggu ' . $pem . ' (UAT)'; break;
            case 'GOLIVE':   $party = 'Executive'; $luar = true; $label = 'Menunggu Executive (ACC Go-Live)'; break;
            case 'EVALUASI':
                $party = $t['evaluator'] ?? $pem;
                $luar  = true;
                $label = 'Menunggu evaluasi ' . ($t['evaluator'] ?? $pem);
                break;
            default:         $party = null; $label = '';
        }

        $heat = $luar ? ($days >= 7 ? 2 : ($days >= 3 ? 1 : 0)) : 0;

        return ['party' => $party, 'days' => $days, 'luar' => $luar, 'heat' => $heat, 'label' => $label];
    }

    /**
     * Kelas CSS strip "menunggu" — helper PRESENTASI (bukan bagian rujukan
     * it-store.js). Satu-satunya tempat aturan warna diturunkan dari waitOf();
     * ketiga blade memakainya supaya papan, halaman Head, dan detail tak
     * pernah menampilkan warna yang saling bertentangan untuk tiket yang sama.
     */
    public static function waitCls(array $w, string $fase): string
    {
        if ($fase === 'ANTRE')  return 'antre';
        if ($w['heat'] === 2)   return 'panas2';
        if ($w['heat'] === 1)   return 'panas1';
        return $w['luar'] ? 'luar' : 'dalam';
    }

    /** Posisi antrean: di belakang berapa item aktif. */
    public static function queuePos(array $t, array $tickets): int
    {
        $n = 0;
        foreach ($tickets as $x) {
            if (! self::isActive($x) || $x['id'] === $t['id']) continue;
            if (self::rankOf($x['fase']) > self::rankOf($t['fase'])
                || (self::rankOf($x['fase']) === self::rankOf($t['fase'])
                    && strcmp((string) $x['faseSince'], (string) $t['faseSince']) < 0)) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Gerbang — apa yang membukanya (rencana §4) + daftar `missing` untuk
     * tombol mati yang bilang kenapa (pola P3).
     * kind: 'triase' | 'advance'; actor: 'ekky'|'it'|'pemohon'|'bo'|'evaluator'.
     */
    public static function gateInfo(array $t): ?array
    {
        $j = $t['jenjang'] ?? null;
        $f = $t['fase'];

        if ($f === 'TRIASE') {
            return ['kind' => 'triase', 'label' => 'Triase', 'actor' => 'ekky'];
        }

        if ($f === 'ANTRE') {
            $next = self::pathFor($j)[1];
            return ['kind' => 'advance', 'next' => $next, 'label' => 'Tarik → ' . self::phShort($next), 'ok' => true, 'missing' => [], 'actor' => 'it'];
        }

        if ($f === 'ANALISIS') {
            $m  = [];
            $fr = $t['fr02']['fr'] ?? [];
            $adaKriteria = false;
            foreach ($fr as $r) {
                if (! empty($r['kriteria']) && trim($r['kriteria']) !== '') { $adaKriteria = true; break; }
            }
            if (! $adaKriteria)                       $m[] = '≥1 FR dengan Kriteria Diterima';
            if (empty($t['fr02']['lingkup']))         $m[] = 'ruang lingkup';
            if (empty($t['fr02']['komponen']))        $m[] = 'komponen terdampak';
            return ['kind' => 'advance', 'next' => 'DEV', 'label' => 'Buka gerbang → Development', 'ok' => ! $m, 'missing' => $m, 'actor' => 'it'];
        }

        if ($f === 'DEV') {
            if ($j === 'S') {
                $ok = ! empty($t['fr03']['note']) && trim($t['fr03']['note']) !== '';
                return ['kind' => 'advance', 'next' => 'SELESAI', 'label' => 'Tandai selesai', 'ok' => $ok, 'missing' => $ok ? [] : ['catatan singkat pengerjaan'], 'actor' => 'it'];
            }
            $next = $j === 'L' ? 'UJI' : 'UAT';
            $ok   = ! empty($t['fr03']['confirmed']);
            return ['kind' => 'advance', 'next' => $next, 'label' => 'Siap → ' . self::phShort($next), 'ok' => $ok, 'missing' => $ok ? [] : ['konfirmasi Development Record (FRM-IT-03)'], 'actor' => 'it'];
        }

        if ($f === 'UJI') {
            $m  = [];
            $tc = $t['fr04']['tc'] ?? [];
            $adaTanpaStatus = ! $tc;
            foreach ($tc as $r) if (empty($r['status'])) { $adaTanpaStatus = true; break; }
            if ($adaTanpaStatus) $m[] = 'semua test case berstatus';
            $open = 0;
            foreach (($t['fr04']['temuan'] ?? []) as $x) {
                if (($x['tingkat'] ?? '') === 'Major' && ($x['status'] ?? '') !== 'Selesai') $open++;
            }
            if ($open) $m[] = $open . ' temuan Major masih terbuka';
            return ['kind' => 'advance', 'next' => 'UAT', 'label' => 'Serahkan → UAT', 'ok' => ! $m, 'missing' => $m, 'actor' => 'it'];
        }

        if ($f === 'UAT') {
            $ok = ($t['fr05']['keputusan'] ?? null) === 'setuju';
            return ['kind' => 'advance', 'next' => 'GOLIVE', 'label' => 'Lanjut → Go Live', 'ok' => $ok, 'missing' => $ok ? [] : ['persetujuan pemohon (FRM-IT-05)'], 'actor' => 'pemohon'];
        }

        if ($f === 'GOLIVE') {
            $m     = [];
            $pra   = $t['fr06']['pra'] ?? [];
            $pasca = $t['fr06']['pasca'] ?? [];
            $kurang = [];
            foreach (self::PRA as $p) if (empty($pra[$p['k']])) $kurang[] = $p['t'];
            if ($kurang) $m[] = 'pra-deploy: ' . implode(', ', $kurang);
            foreach (self::PASCA as $p) {
                if (($pasca[$p['k']] ?? null) !== 'pass') { $m[] = 'pemeriksaan pasca Go-Live belum Pass semua'; break; }
            }
            if (empty($t['fr06']['bo'])) $m[] = 'persetujuan Business Owner';
            $next = $j === 'L' ? 'EVALUASI' : 'SELESAI';
            return ['kind' => 'advance', 'next' => $next, 'label' => $next === 'SELESAI' ? 'Tutup → Selesai' : 'Lanjut → Evaluasi', 'ok' => ! $m, 'missing' => $m, 'actor' => 'bo'];
        }

        if ($f === 'EVALUASI') {
            $ok = ! empty($t['fr07']['hasil']);
            return ['kind' => 'advance', 'next' => 'SELESAI', 'label' => 'Tutup tiket', 'ok' => $ok, 'missing' => $ok ? [] : ['hasil evaluasi (atau tutup otomatis 14 hari)'], 'actor' => 'evaluator'];
        }

        return null;
    }
}
