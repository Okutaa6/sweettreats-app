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

    /**
     * Ronde 2 — lima pemeriksaan tetap uji internal (baris TC TERKUNCI: tak
     * bisa dihapus / diganti nama, tapi diisi & ikut terhitung). TC-05 adalah
     * yang menangkap kerusakan diam-diam — jangan sampai bisa hilang.
     */
    public const TC_TETAP = [
        ['id' => 'TC-01', 'skenario' => 'Fungsi utama sesuai Kriteria Diterima FR',            'harapan' => 'semua FR lolos kriterianya'],
        ['id' => 'TC-02', 'skenario' => 'Validasi input & penanganan error',                   'harapan' => 'input salah ditolak sopan, tak ada error 500'],
        ['id' => 'TC-03', 'skenario' => 'Database / API — data tersimpan & terkirim benar',    'harapan' => 'data konsisten di DB dan sistem tujuan'],
        ['id' => 'TC-04', 'skenario' => 'Hak akses per role',                                  'harapan' => 'role tanpa hak tidak bisa membuka/mengubah'],
        ['id' => 'TC-05', 'skenario' => 'Fitur lama terkait tetap berjalan (regresi)',         'harapan' => 'alur lama tidak berubah perilaku'],
    ];

    /** Ronde 2 — lima aspek evaluasi (FRM-IT-07), skala 1-5. */
    public const EVAL_ASPEK = [
        ['k' => 'sesuai',  't' => 'Kesesuaian dengan kebutuhan'],
        ['k' => 'mudah',   't' => 'Kemudahan penggunaan'],
        ['k' => 'stabil',  't' => 'Stabilitas'],
        ['k' => 'dampak',  't' => 'Dampak ke proses kerja'],
        ['k' => 'manfaat', 't' => 'Manfaat yang dirasakan'],
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
            case 'ANALISIS': $party = 'IT + pemohon'; $label = 'Dianalisis IT bersama ' . $pem; break;
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
            // Ronde 2 (ADITIF): `missing` tetap, `checks[]` ditambah — lampu per
            // syarat. Ruang lingkup HANYA untuk L (02 penuh tak pernah diminta
            // dari M); paraf satu Business Owner jadi syarat gerbang.
            $F  = $t['fr02'] ?? [];
            $fr = $F['fr'] ?? [];
            $adaKriteria = false;
            foreach ($fr as $r) {
                if (! empty($r['kriteria']) && trim($r['kriteria']) !== '') { $adaKriteria = true; break; }
            }
            $checks = [
                ['t' => '≥1 FR dengan Kriteria Diterima', 'ok' => $adaKriteria],
                ['t' => 'komponen terdampak',            'ok' => ! empty($F['komponen'])],
            ];
            if ($j === 'L') {
                $checks[] = ['t' => 'ruang lingkup termasuk / tidak termasuk (02 penuh)',
                             'ok' => ! empty($F['lingkup']) && trim($F['lingkup']) !== ''];
            }
            // Jalan pintas BERTANDA (§14.16): Head IT boleh meneruskan tanpa paraf,
            // lampu menyala tapi TEKSNYA berubah — pelewatan tak boleh menyamar jadi paraf.
            if (empty($F['paraf']) && ! empty($F['diteruskan'])) {
                $checks[] = ['t' => 'diteruskan Head IT tanpa paraf BO', 'ok' => true];
            } else {
                $checks[] = ['t' => 'paraf satu Business Owner', 'ok' => ! empty($F['paraf'])];
            }

            $m = self::kurangDari($checks);
            return ['kind' => 'advance', 'next' => 'DEV', 'label' => 'Buka gerbang → Development', 'ok' => ! $m, 'missing' => $m, 'checks' => $checks, 'actor' => 'it'];
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
            // Ronde 2 (ADITIF): tiga lampu; kalimat Major MENYEBUT JUMLAHNYA.
            $tc    = $t['fr04']['tc'] ?? [];
            $belum = count(array_filter($tc, fn ($r) => empty($r['status'])));
            $fail  = count(array_filter($tc, fn ($r) => ($r['status'] ?? null) === 'fail'));
            $open  = count(array_filter($t['fr04']['temuan'] ?? [],
                fn ($x) => ($x['tingkat'] ?? '') === 'Major' && ($x['status'] ?? '') !== 'Selesai'));
            $checks = [
                ['t' => $belum ? $belum . ' test case belum berstatus' : 'semua test case berstatus', 'ok' => count($tc) > 0 && ! $belum],
                ['t' => $fail ? $fail . ' test case masih Fail' : 'tidak ada test case Fail',        'ok' => ! $fail],
                ['t' => $open ? $open . ' temuan Major masih terbuka — menahan gerbang' : 'nol temuan Major terbuka', 'ok' => ! $open],
            ];
            $m = self::kurangDari($checks);
            return ['kind' => 'advance', 'next' => 'UAT', 'label' => 'Serahkan → UAT', 'ok' => ! $m, 'missing' => $m, 'checks' => $checks, 'actor' => 'it'];
        }

        if ($f === 'UAT') {
            // Ronde 2: "Disetujui dengan Catatan" juga membuka gerbang.
            $k  = $t['fr05']['keputusan'] ?? null;
            $ok = $k === 'setuju' || $k === 'catatan';
            return ['kind' => 'advance', 'next' => 'GOLIVE', 'label' => 'Lanjut → Go Live', 'ok' => $ok,
                'missing' => $ok ? [] : ['keputusan pemohon di FRM-IT-05'],
                'checks'  => [['t' => 'pemohon menjawab: Disetujui / Disetujui dengan Catatan', 'ok' => $ok]],
                'actor' => 'pemohon'];
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
            // Ronde 2: lima aspek dinilai (skala 1-5) membuka penutupan tiket;
            // rencana tindak lanjut yang belum bertiket TIDAK menahan.
            $a = $t['fr07']['aspek'] ?? [];
            $n = count(array_filter(self::EVAL_ASPEK, fn ($x) => ! empty($a[$x['k']])));
            $ok = $n === count(self::EVAL_ASPEK);
            $rtlOpen = count(array_filter($t['fr07']['rtl'] ?? [], fn ($x) => empty($x['ticketId'])));
            $checks = [
                ['t' => $ok ? 'lima aspek dinilai' : $n . '/5 aspek dinilai', 'ok' => $ok],
                ['t' => $rtlOpen ? $rtlOpen . ' rencana tindak lanjut belum dijadikan tiket (boleh dibiarkan)' : 'tindak lanjut sudah bertiket / tidak ada', 'ok' => true],
            ];
            return ['kind' => 'advance', 'next' => 'SELESAI', 'label' => 'Tutup tiket', 'ok' => $ok,
                'missing' => $ok ? [] : ['lima aspek evaluasi (atau tutup otomatis 14 hari)'],
                'checks' => $checks, 'actor' => 'evaluator'];
        }

        return null;
    }

    /** `missing` = teks lampu yang belum menyala (urutan dipertahankan). */
    private static function kurangDari(array $checks): array
    {
        return array_values(array_map(fn ($c) => $c['t'], array_filter($checks, fn ($c) => ! $c['ok'])));
    }
}
