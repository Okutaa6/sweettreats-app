<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Marketing Dashboard — "glance & go" buat tim Marketing.
 *
 * Global + filter cabang opsional, fokus AKUISISI & RETENSI pasien + aktivitas
 * marketing yang datanya hidup (konten editorial, roster KOL/KOC, promo).
 * Layout terinspirasi dashboard analytics modern (donut, stacked bar, funnel
 * mengalir) — tapi warna tetap brand (cream/brown), bukan dark.
 *
 * ⚠️ Catatan data (audit prod 2026-06-26): atribusi promo→reservasi→komisi
 * MASIH KOSONG → section "Atribusi" empty-state. Lihat memory
 * project_marketing_dashboard.
 *
 * Scope cabang: bridge via raw_branch (id UUID ⇄ omnicare_place_id).
 */
class MarketingDashboardController extends Controller
{
    private const BULAN = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

    /** Bucket sumber pasien — urutan tampil + warna + ikon. */
    private const SOURCE_BUCKETS = [
        'social'  => ['label' => 'Social Media',  'color' => '#C13584', 'icon' => 'fa-hashtag'],
        'endorse' => ['label' => 'Endorse / KOL', 'color' => '#7A3415', 'icon' => 'fa-bullhorn'],
        'wom'     => ['label' => 'Word of Mouth', 'color' => '#2F8E55', 'icon' => 'fa-user-friends'],
        'offline' => ['label' => 'Offline',       'color' => '#C0791F', 'icon' => 'fa-map-signs'],
        'web'     => ['label' => 'Web & Lokasi',  'color' => '#3C5FA8', 'icon' => 'fa-globe'],
        'other'   => ['label' => 'Lainnya',       'color' => '#9A7256', 'icon' => 'fa-question'],
    ];

    public function index()
    {
        $month = request('bulan');
        $date  = $month ? Carbon::parse($month . '-01')->startOfMonth() : Carbon::now()->startOfMonth();
        $monthStart = $date->format('Y-m-d');
        $monthEnd   = $date->copy()->endOfMonth()->format('Y-m-d');
        $range      = (int) request('range', 12);
        if (! in_array($range, [6, 12], true)) $range = 12;

        $branches    = DB::table('raw_branch')->orderBy('name')->get();
        $selected    = request('cabang') ?: null;
        $selBranch   = $selected ? $branches->firstWhere('id', $selected) : null;
        if (! $selBranch) { $selected = null; }

        if ($selected) {
            $branchIds  = [$selected];
            $placeIds   = $selBranch->omnicare_place_id ? [$selBranch->omnicare_place_id] : [];
            $branchName = $selBranch->name;
        } else {
            $branchIds  = $branches->pluck('id')->all();
            $placeIds   = $branches->pluck('omnicare_place_id')->filter()->values()->all();
            $branchName = 'Semua Cabang';
        }

        return view('dashboard.marketing', [
            'date'        => $date,
            'monthValue'  => $date->format('Y-m'),
            'monthLabel'  => self::BULAN[$date->month] . ' ' . $date->year,
            'range'       => $range,
            'branches'    => $branches,
            'selected'    => $selected,
            'branchName'  => $branchName,
            'target'      => $this->buildTargetProgress($date, $branchIds, $placeIds),
            'kpi'         => $this->buildKpi($date, $monthStart, $monthEnd, $branchIds, $placeIds),
            'sourceMix'   => $this->buildSourceMix($branchIds),
            'acq'         => $this->buildAcquisition($date, $placeIds, $range),
            'convFunnel'  => $this->buildConversionFunnel($branchIds, $monthStart, $monthEnd),
            'retFunnel'   => $this->buildRetentionFunnel($placeIds, $monthStart, $monthEnd),
            'content'     => $this->buildContent($monthStart, $monthEnd),
            'roster'      => $this->buildRoster(),
            'promos'      => $this->buildPromos($selected),
            'keywords'    => $this->buildKeywords(),
            'attribution' => $this->buildAttribution($branchIds),
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  HEADLINE — Revenue bulan ini (aktual Omnicare) vs Target cabang
    // ════════════════════════════════════════════════════════════════
    /**
     * Progres penjualan vs target bulan/cabang terpilih.
     *
     * Beda dari Sales Dashboard: di sini bulan bisa dipilih bebas lewat filter,
     * jadi titik "as of" menyesuaikan — bulan berjalan pakai hari ini (pace),
     * bulan lampau pakai akhir bulan (aktual penuh), bulan mendatang belum ada.
     * Bentuk array-nya sengaja disamakan dengan SalesDashboardController agar
     * markup hero card bisa dipakai ulang.
     */
    private function buildTargetProgress(Carbon $date, array $branchIds, array $placeIds): array
    {
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd   = $date->copy()->endOfMonth();
        $today      = Carbon::now()->startOfDay();

        // Titik "as of": hari ini bila bulan berjalan, akhir bulan bila lampau,
        // sebelum bulan mulai (aktual 0) bila mendatang.
        if ($today->lt($monthStart)) {
            $asOf        = $monthStart->copy()->subDay(); // rentang kosong → aktual 0
            $elapsedDays = 0;
        } elseif ($today->gt($monthEnd)) {
            $asOf        = $monthEnd->copy();
            $elapsedDays = $monthEnd->day;
        } else {
            $asOf        = $today->copy();
            $elapsedDays = $today->day;
        }
        $daysInMonth = $date->daysInMonth;

        $actualMtd = $asOf->gte($monthStart)
            ? $this->omnicareNet($placeIds, $monthStart->format('Y-m-d'), $asOf->format('Y-m-d'))
            : 0.0;

        $targetMonth = (float) DB::table('raw_branch_target')
            ->whereIn('cabang_id', $branchIds)
            ->where('tahun', $date->year)
            ->where('bulan', $date->month)
            ->sum('nominal');

        $hasTarget  = $targetMonth > 0;
        $attainment = $hasTarget ? ($actualMtd / $targetMonth) * 100 : null;
        $expected   = $hasTarget ? $targetMonth * ($elapsedDays / $daysInMonth) : null;

        return [
            'month_label' => self::BULAN[$date->month] . ' ' . $date->year,
            'actual'      => $actualMtd,
            'target'      => $targetMonth,
            'has_target'  => $hasTarget,
            'attainment'  => $attainment,
            'expected'    => $expected,
            'on_track'    => $hasTarget ? ($actualMtd >= $expected) : null,
            'remaining'   => $hasTarget ? max(0, $targetMonth - $actualMtd) : null,
            'pct_clamped' => $hasTarget ? min(100, max(0, $attainment)) : 0,
        ];
    }

    /** Revenue net Omnicare (exclude void & service charge) — sumber sama dgn Sales Dashboard. */
    private function omnicareNet(array $placeIds, string $start, string $end): float
    {
        if (empty($placeIds)) return 0.0;
        return (float) DB::table('omnicare_sales_detail')
            ->whereIn('place_id', $placeIds)
            ->whereBetween('trx_date', [$start, $end])
            ->where(fn($q) => $q->where('item_type', '<>', 'V')->orWhereNull('item_type'))
            ->whereRaw("LOWER(COALESCE(item_name, '')) NOT LIKE 'service charge%'")
            ->sum('net_sell_price');
    }

    // ════════════════════════════════════════════════════════════════
    //  KPI strip
    // ════════════════════════════════════════════════════════════════
    private function buildKpi(Carbon $date, string $start, string $end, array $branchIds, array $placeIds): array
    {
        $prevStart = $date->copy()->subMonth()->startOfMonth()->format('Y-m-d');
        $prevEnd   = $date->copy()->subMonth()->endOfMonth()->format('Y-m-d');

        $pasienBaru = $this->newPatients($placeIds, $start, $end);
        $pasienPrev = $this->newPatients($placeIds, $prevStart, $prevEnd);

        $kunjungan = 0;
        if (! empty($placeIds)) {
            $kunjungan = (int) DB::table('omnicare_sales_detail')
                ->whereIn('place_id', $placeIds)
                ->whereBetween('trx_date', [$start, $end])
                ->whereNotNull('encounter_id')
                ->distinct('encounter_id')->count('encounter_id');
        }

        $kontenTayang = (int) DB::table('ep_konten')->whereBetween('tanggal_upload', [$start, $end])->count();
        $affiliate    = (int) DB::table('raw_affiliate')->whereIn('type', ['kol', 'koc'])->count();

        return [
            'pasien_baru'       => $pasienBaru,
            'pasien_baru_delta' => $pasienBaru - $pasienPrev,
            'kunjungan'         => $kunjungan,
            'konten_tayang'     => $kontenTayang,
            'affiliate'         => $affiliate,
        ];
    }

    private function newPatients(array $placeIds, string $start, string $end): int
    {
        if (empty($placeIds)) return 0;
        return (int) DB::table('omnicare_medical_record')
            ->whereIn('branch_id', $placeIds)
            ->whereBetween('date', [$start, $end])
            ->where('is_new_patient', 1)
            ->distinct('p_id')->count('p_id');
    }

    // ════════════════════════════════════════════════════════════════
    //  HERO — Sumber Pasien (donut + legend), kumulatif dari source_app
    // ════════════════════════════════════════════════════════════════
    private function buildSourceMix(array $branchIds): array
    {
        $rows = DB::table('raw_customer')->whereIn('raw_branch_id', $branchIds)
            ->selectRaw('source_app, COUNT(*) c')->groupBy('source_app')->get();

        $buckets = array_fill_keys(array_keys(self::SOURCE_BUCKETS), 0);
        $total = 0;
        foreach ($rows as $r) {
            $buckets[$this->bucketSource($r->source_app)] += (int) $r->c;
            $total += (int) $r->c;
        }
        $known = $total - $buckets['other'];

        $items = [];
        foreach (self::SOURCE_BUCKETS as $key => $meta) {
            $n = $buckets[$key];
            if ($n === 0 && $key !== 'other') continue;
            $items[] = ['key' => $key, 'label' => $meta['label'], 'color' => $meta['color'],
                'icon' => $meta['icon'], 'count' => $n,
                'pct' => $total > 0 ? round($n / $total * 100, 1) : 0];
        }
        usort($items, function ($a, $b) {
            if ($a['key'] === 'other') return 1;
            if ($b['key'] === 'other') return -1;
            return $b['count'] <=> $a['count'];
        });

        // Geometri donut (stroke-dasharray segmen, viewBox 0 0 120 120, r=46)
        $r = 46; $circ = 2 * M_PI * $r; $offset = 0; $segs = [];
        foreach ($items as $it) {
            if ($it['pct'] <= 0) continue;
            $len = $circ * $it['pct'] / 100;
            $segs[] = ['color' => $it['color'], 'dash' => round($len, 2),
                'gap' => round($circ - $len, 2), 'offset' => round(-$offset, 2)];
            $offset += $len;
        }

        return ['items' => $items, 'total' => $total, 'known' => $known,
            'segs' => $segs, 'circ' => round($circ, 2), 'r' => $r];
    }

    private function bucketSource(?string $raw): string
    {
        $s = mb_strtolower(trim((string) $raw));
        if ($s === '' || $s === '-' || $s === 'admin' || $s === 'sc') return 'other';
        $has = fn(array $needles) => array_filter($needles, fn($n) => str_contains($s, $n)) !== [];

        if ($has(['endo', 'endrs', 'selebgram', 'influencer', 'kol', 'koc'])) return 'endorse';
        if ($has(['insta', 'instg', 'intag', 'intsg', 'instr', 'instsg', 'tiktok', 'tikt', 'fyp',
                  'facebook', 'twitter', 'youtube']) || $s === 'ig' || str_starts_with($s, 'ig ')) return 'social';
        if ($has(['flyer', 'flayer', 'falyer', 'fyayer', 'flya', 'brosur', 'banner', 'spanduk',
                  'pamflet', 'event', 'walk'])) return 'offline';
        if ($has(['website', 'web', 'google', 'maps', 'internet', 'browser', 'online',
                  'lokasi', 'lewat'])) return 'web';
        if ($has(['kelu', 'teman', 'temen', 'kerabat', 'keranat', 'saudara', 'rekomen', 'pacar',
                  'anak', 'suami', 'istri', 'kaka', 'ponakan', 'mamah', 'ibu', 'ayah', 'cucu',
                  'drg', 'dokter', 'dok ', 'px ', 'pasien'])) return 'wom';
        return 'other';
    }

    // ════════════════════════════════════════════════════════════════
    //  Akuisisi: pasien baru vs lama per bulan (stacked bar) — DATA ASLI
    // ════════════════════════════════════════════════════════════════
    private function buildAcquisition(Carbon $date, array $placeIds, int $months): array
    {
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $m = $date->copy()->subMonths($i);
            $series[$m->format('Y-m')] = ['label' => substr(self::BULAN[$m->month], 0, 3),
                'full' => self::BULAN[$m->month] . ' ' . $m->year, 'baru' => 0, 'lama' => 0];
        }
        if (! empty($placeIds)) {
            $start = $date->copy()->subMonths($months - 1)->startOfMonth()->format('Y-m-d');
            $end   = $date->copy()->endOfMonth()->format('Y-m-d');
            foreach (DB::table('omnicare_medical_record')->whereIn('branch_id', $placeIds)
                ->whereBetween('date', [$start, $end])
                ->selectRaw("DATE_FORMAT(date,'%Y-%m') ym, SUM(is_new_patient=1) baru, SUM(is_new_patient=0) lama")
                ->groupBy('ym')->get() as $r) {
                if (isset($series[$r->ym])) { $series[$r->ym]['baru'] = (int) $r->baru; $series[$r->ym]['lama'] = (int) $r->lama; }
            }
        }

        $totals = array_map(fn($m) => $m['baru'] + $m['lama'], $series);
        $max = max(1, $totals ? max($totals) : 1);
        $totalBaru = array_sum(array_column($series, 'baru'));
        $totalLama = array_sum(array_column($series, 'lama'));

        // Geometri SVG (viewBox 0 0 640 200)
        $W = 640; $H = 200; $padT = 16; $padB = 26; $padL = 30; $padR = 6;
        $plotH = $H - $padT - $padB; $plotW = $W - $padL - $padR;
        $n = count($series); $slot = $plotW / max(1, $n); $barW = round($slot * 0.58, 1);
        $py = fn($v) => round($padT + (1 - $v / $max) * $plotH, 1);
        $baseY = $py(0);

        $bars = []; $i = 0; $hasData = false;
        foreach ($series as $m) {
            $tot = $m['baru'] + $m['lama'];
            if ($tot > 0) $hasData = true;
            $cx = $padL + $slot * ($i + 0.5);
            $x  = round($cx - $barW / 2, 1);
            $lamaTop = $py($m['lama']);              // lama di bawah (dari baseline)
            $baruTop = $py($m['baru'] + $m['lama']); // baru ditumpuk di atas lama
            $bars[] = [
                'x' => $x, 'w' => $barW, 'tx' => round($cx, 1),
                'lama_y' => $lamaTop, 'lama_h' => round(max(0, $baseY - $lamaTop), 1),
                'baru_y' => $baruTop, 'baru_h' => round(max(0, $lamaTop - $baruTop), 1),
                'tick' => $m['label'], 'full' => $m['full'],
                'baru' => $m['baru'], 'lama' => $m['lama'], 'tot' => $tot,
            ];
            $i++;
        }
        $grid = [];
        foreach ([0, 0.5, 1] as $f) $grid[] = ['y' => $py($max * $f), 'label' => (string) round($max * $f)];

        return ['has_data' => $hasData, 'width' => $W, 'height' => $H, 'base_y' => $baseY,
            'bars' => $bars, 'grid' => $grid, 'total_baru' => $totalBaru, 'total_lama' => $totalLama];
    }

    // ════════════════════════════════════════════════════════════════
    //  Funnel KONVERSI (per bulan): Reservasi → Aktif → Datang
    //  Scope: reservasi dgn tanggal kunjungan di bulan terpilih + cabang.
    // ════════════════════════════════════════════════════════════════
    private function buildConversionFunnel(array $branchIds, string $start, string $end): array
    {
        // Stage subset berlapis biar SELALU monotonic:
        // Reservasi (semua) ⊇ Aktif (tidak batal) ⊇ Datang (hadir & tidak batal).
        $base = fn() => DB::table('raw_cus_reservation')
            ->whereIn('raw_branch_id', $branchIds)->whereBetween('date', [$start, $end]);

        $reservasi = (int) $base()->count();
        $aktif     = (int) $base()->where('status', '<>', 'cancelled')->count();
        $datang = (int) DB::table('reservation_omnicare_link as l')
            ->join('raw_cus_reservation as r', 'r.id', '=', 'l.raw_cus_reservation_id')
            ->whereIn('r.raw_branch_id', $branchIds)
            ->whereBetween('r.date', [$start, $end])
            ->where('r.status', '<>', 'cancelled')
            ->whereNotNull('l.omnicare_record_id')
            // DISTINCT: 1 reservasi bisa punya >1 link omnicare — tanpa ini
            // "Hadir" bisa melebihi "Aktif" (funnel tak monotonic lagi).
            ->distinct()
            ->count('r.id');

        return $this->funnelStages([
            ['label' => 'Reservasi', 'sub' => 'dibuat',      'count' => $reservasi],
            ['label' => 'Aktif',     'sub' => 'tidak batal',  'count' => $aktif],
            ['label' => 'Hadir',     'sub' => 'jadi datang',  'count' => $datang],
        ], '#C0791F');
    }

    // ════════════════════════════════════════════════════════════════
    //  Funnel RETENSI (per bulan): pasien yang DATANG bulan ini, lalu
    //  tier loyalitas-nya (jumlah kunjungan SEUMUR HIDUP di cabang).
    //  Datang(bln) → Returning(≥2) → Reguler(≥3) → Loyal(≥6).
    // ════════════════════════════════════════════════════════════════
    private function buildRetentionFunnel(array $placeIds, string $start, string $end): array
    {
        $total = $r2 = $r3 = $r6 = 0;
        if (! empty($placeIds)) {
            $ph = implode(',', array_fill(0, count($placeIds), '?'));
            $row = DB::selectOne(
                "SELECT COUNT(*) total, SUM(life>=2) r2, SUM(life>=3) r3, SUM(life>=6) r6
                 FROM (
                   SELECT mo.p_id, lc.life
                   FROM (SELECT DISTINCT p_id FROM omnicare_medical_record
                         WHERE branch_id IN ($ph) AND date BETWEEN ? AND ?) mo
                   JOIN (SELECT p_id, COUNT(*) life FROM omnicare_medical_record
                         WHERE branch_id IN ($ph) GROUP BY p_id) lc ON lc.p_id = mo.p_id
                 ) t",
                array_merge($placeIds, [$start, $end], $placeIds)
            );
            $total = (int) ($row->total ?? 0); $r2 = (int) ($row->r2 ?? 0);
            $r3 = (int) ($row->r3 ?? 0); $r6 = (int) ($row->r6 ?? 0);
        }

        return $this->funnelStages([
            ['label' => 'Kunjungan', 'sub' => 'pasien bln ini', 'count' => $total],
            ['label' => 'Returning', 'sub' => '≥2 kunjungan',   'count' => $r2],
            ['label' => 'Reguler',   'sub' => '≥3 kunjungan',   'count' => $r3],
            ['label' => 'Loyal',     'sub' => '≥6 kunjungan',   'count' => $r6],
        ], '#2F8E55');
    }

    /** Hitung pct relatif ke stage pertama + geometri band SVG funnel. */
    private function funnelStages(array $stages, string $accent): array
    {
        $first = $stages[0]['count'] ?: 0;
        $n = count($stages);
        $W = 600; $H = 188; $maxHalf = 58; $midY = 84;
        $colW = $W / $n;

        $pts = [];
        foreach ($stages as $i => &$s) {
            $s['pct'] = $first > 0 ? round($s['count'] / $first * 100) : 0;
            $half = ($first > 0 ? $s['count'] / $first : 0) * $maxHalf;
            $s['cx'] = round($colW * ($i + 0.5), 1);
            $s['top'] = round($midY - $half, 1);
            $s['bot'] = round($midY + $half, 1);
            $pts[] = $s;
        }
        unset($s);

        // Path band: tepi atas kiri→kanan, lalu tepi bawah balik
        $top = "M 0 {$pts[0]['top']} ";
        foreach ($pts as $p) $top .= "L {$p['cx']} {$p['top']} ";
        $top .= "L {$W} " . end($pts)['top'] . " ";
        $top .= "L {$W} " . end($pts)['bot'] . " ";
        foreach (array_reverse($pts) as $p) $top .= "L {$p['cx']} {$p['bot']} ";
        $top .= "L 0 {$pts[0]['bot']} Z";

        return ['stages' => $stages, 'path' => $top, 'accent' => $accent,
            'width' => $W, 'height' => $H, 'col_w' => round($colW, 1), 'mid_y' => $midY,
            'rate' => $first > 0 ? round(end($stages)['count'] / $first * 100) : 0];
    }

    // ════════════════════════════════════════════════════════════════
    //  Throughput konten editorial (program-wide)
    // ════════════════════════════════════════════════════════════════
    private function buildContent(string $start, string $end): array
    {
        $statusMeta = [
            'selesai'     => ['label' => 'Selesai',    'color' => '#2F8E55'],
            'progress'    => ['label' => 'Dikerjakan', 'color' => '#3C5FA8'],
            'revisi'      => ['label' => 'Revisi',      'color' => '#C0791F'],
            'done_revisi' => ['label' => 'Done Revisi', 'color' => '#2C8C63'],
            'riset'       => ['label' => 'Riset',       'color' => '#8E4FA8'],
            'on_hold'     => ['label' => 'On Hold',     'color' => '#9A7256'],
            'draft'       => ['label' => 'Draft',       'color' => '#A9866A'],
        ];
        $raw = DB::table('ep_konten')->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->toArray();
        $total = array_sum($raw);
        $statuses = [];
        foreach ($statusMeta as $key => $meta) {
            $n = (int) ($raw[$key] ?? 0);
            if ($n === 0) continue;
            $statuses[] = ['label' => $meta['label'], 'color' => $meta['color'],
                'count' => $n, 'pct' => $total > 0 ? round($n / $total * 100) : 0];
        }
        $tayangBulan = (int) DB::table('ep_konten')->whereBetween('tanggal_upload', [$start, $end])->count();
        $tipe = DB::table('ep_konten')->whereNotNull('tipe_konten')
            ->selectRaw('tipe_konten, COUNT(*) c')->groupBy('tipe_konten')->orderByDesc('c')->limit(5)->get()
            ->map(fn($r) => ['label' => ucwords(str_replace('_', ' ', $r->tipe_konten)), 'count' => (int) $r->c])->all();

        return ['total' => $total, 'statuses' => $statuses, 'tayang_bulan' => $tayangBulan, 'tipe' => $tipe];
    }

    // ════════════════════════════════════════════════════════════════
    //  Roster KOL / KOC
    // ════════════════════════════════════════════════════════════════
    private function buildRoster(): array
    {
        $kol = (int) DB::table('raw_affiliate')->where('type', 'kol')->count();
        $koc = (int) DB::table('raw_affiliate')->where('type', 'koc')->count();
        $katLabels = [
            'komika_standup' => 'Komika / Stand-up', 'musisi_penyanyi' => 'Musisi / Penyanyi',
            'influencer' => 'Influencer', 'content_creator' => 'Content Creator', 'athlete' => 'Atlet',
            'aktris' => 'Aktris', 'aktor' => 'Aktor', 'moms_content_creator' => 'Moms Creator',
        ];
        $kategori = DB::table('raw_affiliate')->where('type', 'kol')
            ->whereNotNull('kol_kategori')->where('kol_kategori', '<>', '')
            ->selectRaw('kol_kategori, COUNT(*) c')->groupBy('kol_kategori')->orderByDesc('c')->limit(5)->get()
            ->map(fn($r) => ['label' => $katLabels[$r->kol_kategori] ?? ucwords(str_replace('_', ' ', $r->kol_kategori)),
                'count' => (int) $r->c])->all();
        $top = DB::table('raw_affiliate')->where('type', 'kol')->where('followers_count', '>', 0)
            ->orderByDesc('followers_count')->limit(5)->get(['name', 'kol_kategori', 'followers_count'])
            ->map(fn($r) => ['name' => $r->name,
                'kategori' => $r->kol_kategori ? ($katLabels[$r->kol_kategori] ?? ucwords(str_replace('_', ' ', $r->kol_kategori))) : null,
                'followers' => $this->shortNum((int) $r->followers_count)])->all();

        return ['kol' => $kol, 'koc' => $koc, 'kategori' => $kategori, 'top' => $top];
    }

    // ════════════════════════════════════════════════════════════════
    //  Katalog promo (margin tetap disembunyikan)
    // ════════════════════════════════════════════════════════════════
    private function buildPromos(?string $selected)
    {
        $today = Carbon::now()->format('Y-m-d');
        $q = DB::table('marketing_promo')->where('is_active', 1);
        if ($selected) {
            $q->where(function ($w) use ($selected) {
                $w->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))->from('marketing_promo_branch')
                        ->whereColumn('marketing_promo_branch.marketing_promo_id', 'marketing_promo.id');
                })->orWhereExists(function ($sub) use ($selected) {
                    $sub->select(DB::raw(1))->from('marketing_promo_branch')
                        ->whereColumn('marketing_promo_branch.marketing_promo_id', 'marketing_promo.id')
                        ->where('marketing_promo_branch.raw_branch_id', $selected);
                });
            });
        }
        return $q->select('id', 'nama_promo', 'campaign_type', 'status', 'harga_normal', 'harga_promo',
                'periode_start', 'periode_end', 'detail_promo', 'syarat_ketentuan', 'cetakan')
            ->orderByRaw("FIELD(status,'aktif','planning','selesai')")->orderBy('periode_start')->limit(8)->get()
            ->map(function ($p) use ($today) {
                $p->is_upcoming = $p->periode_start && $p->periode_start > $today;
                return $p;
            });
    }

    private function buildKeywords(): array
    {
        $today = Carbon::now()->format('Y-m-d');
        return [
            'active'  => (int) DB::table('raw_keyword')->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))->count(),
            'expired' => (int) DB::table('raw_keyword')->where('end_date', '<', $today)->count(),
        ];
    }

    private function buildAttribution(array $branchIds): array
    {
        $resvPromo = (int) DB::table('raw_cus_reservation')->whereIn('raw_branch_id', $branchIds)
            ->where(fn($q) => $q->whereNotNull('raw_promo_id')->where('raw_promo_id', '<>', '')
                ->orWhere(fn($w) => $w->whereNotNull('marketing_promo_id')->where('marketing_promo_id', '<>', '')))
            ->count();
        $commission = (int) DB::table('raw_commission')->count();
        return ['has_data' => ($resvPromo > 0 || $commission > 0), 'resv_promo' => $resvPromo,
            'commission' => $commission, 'promo_codes' => (int) DB::table('raw_promo')->count()];
    }

    private function shortNum(int $n): string
    {
        if ($n >= 1_000_000) return rtrim(rtrim(number_format($n / 1_000_000, 1, ',', '.'), '0'), ',') . 'jt';
        if ($n >= 1_000)     return rtrim(rtrim(number_format($n / 1_000, 1, ',', '.'), '0'), ',') . 'rb';
        return (string) $n;
    }
}
