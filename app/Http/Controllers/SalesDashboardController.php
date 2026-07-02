<?php

namespace App\Http\Controllers;

use App\Models\RawCusReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sales Dashboard — "glance & go" buat tim Sales.
 *
 * Fokus dashboard (BUKAN report): kondisi sekarang + progres vs target +
 * hal yang butuh aksi + shortcut. Detail/historis ada di Laporan Penjualan.
 *
 * Scope per cabang: user dengan session('raw_branch_id') dikunci ke cabangnya;
 * tanpa session branch (admin/HQ) = agregat semua cabang aktif.
 */
class SalesDashboardController extends Controller
{
    private const BULAN = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

    public function index()
    {
        $date    = Carbon::parse(request('date', Carbon::now()->format('Y-m-d')))->startOfDay();
        $anchor  = $date->format('Y-m-d');
        $dowIso  = $date->dayOfWeekIso;

        // ── Resolusi cabang (per-user) ─────────────────────────────────
        $sessionBranch = session('raw_branch_id') ?: null;
        if ($sessionBranch) {
            $branch     = DB::table('raw_branch')->where('id', $sessionBranch)->first();
            $branchIds  = [$sessionBranch];
            $placeIds   = ($branch && $branch->omnicare_place_id) ? [$branch->omnicare_place_id] : [];
            $branchName = $branch->name ?? 'Cabang';
            $scopeAll   = false;
        } else {
            $branches   = DB::table('raw_branch')->get();
            $branchIds  = $branches->pluck('id')->all();
            $placeIds   = $branches->pluck('omnicare_place_id')->filter()->values()->all();
            $branchName = 'Semua Cabang';
            $scopeAll   = true;
        }

        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

        $target        = $this->buildTargetProgress($date, $branchIds, $placeIds);
        $kpi           = $this->buildKpi($date, $anchor, $branchIds, $placeIds);
        $activePromos  = $this->buildActivePromos($anchor, $sessionBranch);
        // Selalu ambil dua-duanya: yang AKTIF (bisa ditawarin sekarang) + yang AKAN datang (prep)
        $upcomingPromos = $this->buildUpcomingPromos($anchor, $sessionBranch);
        $reservasiBaru = $this->buildReservasiBaru($branchIds);

        return view('dashboard.sales', [
            'date'         => $date,
            'anchor'       => $anchor,
            'dayLabel'     => $dayNames[$dowIso] . ', ' . $date->format('d M Y'),
            'isToday'      => $date->isToday(),
            'branchName'   => $branchName,
            'scopeAll'     => $scopeAll,
            'target'       => $target,
            'kpi'          => $kpi,
            'trend'        => $this->buildTrend($date, $placeIds, $target),
            'activePromos' => $activePromos,
            'upcomingPromos' => $upcomingPromos,
            'reservasiBaru'=> $reservasiBaru,
            'reservasiBar' => $this->buildReservasiPerHari($branchIds, $date),
            'actions'      => $this->buildActionItems($anchor, $dowIso, $branchIds, $placeIds),
            'aksiCepat'    => $this->buildAksiCepat($reservasiBaru['count'], $activePromos, $kpi, $target),
            'doctorSchedule' => $this->buildDoctorSchedule($anchor, $dowIso, $branchIds),
        ]);
    }

    /**
     * HEADLINE — Revenue bulan ini (aktual Omnicare) vs Target.
     */
    private function buildTargetProgress(Carbon $date, array $branchIds, array $placeIds): array
    {
        $monthStart  = $date->copy()->startOfMonth()->format('Y-m-d');
        $elapsedDays = $date->day;
        $daysInMonth = $date->daysInMonth;

        $actualMtd = $this->omnicareNet($placeIds, $monthStart, $date->format('Y-m-d'));

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
            'pace_pct'    => $hasTarget ? min(100, ($expected / max(1, $targetMonth)) * 100) : 0,
        ];
    }

    /**
     * Grafik penjualan HARIAN bulan ini (batang per tanggal) + garis target/hari
     * sebagai pembanding — DATA ASLI. Koordinat SVG dihitung di sini.
     */
    private function buildTrend(Carbon $date, array $placeIds, array $target): array
    {
        $daysInMonth = $date->daysInMonth;
        $anchorDay   = $date->day;
        $monthStart  = $date->copy()->startOfMonth()->format('Y-m-d');

        $daily = [];
        if (! empty($placeIds)) {
            $daily = DB::table('omnicare_sales_detail')
                ->whereIn('place_id', $placeIds)
                ->whereBetween('trx_date', [$monthStart, $date->format('Y-m-d')])
                ->where(fn($q) => $q->where('item_type', '<>', 'V')->orWhereNull('item_type'))
                ->whereRaw("LOWER(COALESCE(item_name, '')) NOT LIKE 'service charge%'")
                ->selectRaw('DAY(trx_date) d, SUM(net_sell_price) net')
                ->groupBy('d')->pluck('net', 'd')->toArray();
        }

        $targetDaily = $target['has_target'] ? (float) $target['target'] / $daysInMonth : 0;
        $monShort = substr(self::BULAN[$date->month], 0, 3);
        $vals = [];
        for ($d = 1; $d <= $anchorDay; $d++) $vals[$d] = (float) ($daily[$d] ?? 0);
        $max = max(max($vals ?: [0]), $targetDaily, 1);

        // Geometri SVG (viewBox 0 0 600 180)
        $W = 600; $H = 180; $padL = 6; $padR = 6; $padT = 14; $padB = 22;
        $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
        $px = fn($d) => round($padL + ($daysInMonth > 1 ? ($d - 1) / ($daysInMonth - 1) : 0) * $plotW, 1);
        $py = fn($v) => round($padT + (1 - $v / $max) * $plotH, 1);
        $baseY = $py(0);
        $barW  = round($plotW / $daysInMonth * 0.6, 1);

        // Batang penjualan per hari (1..hari ini)
        $bars = []; $hasData = false;
        for ($d = 1; $d <= $anchorDay; $d++) {
            $v = $vals[$d];
            if ($v > 0) $hasData = true;
            $topY = $py($v);
            $bars[] = [
                'x'     => round($px($d) - $barW / 2, 1),
                'w'     => $barW,
                'y'     => $topY,
                'h'     => round(max(0, $baseY - $topY), 1),
                'label' => $d . ' ' . $monShort,
                'val'   => 'Rp ' . number_format($v, 0, ',', '.'),
                'today' => ($d === $anchorDay),
            ];
        }

        $grid = [];
        foreach ([0, 0.5, 1] as $f) {
            $val = $max * $f;
            $grid[] = ['y' => $py($val), 'label' => 'Rp ' . round($val / 1_000_000) . 'jt'];
        }

        $xticks = [];
        foreach (array_values(array_unique([1, 5, 10, 15, 20, 25, $daysInMonth])) as $d) {
            $xticks[] = ['x' => $px($d), 'label' => $d . ' ' . $monShort];
        }

        return [
            'has_data'     => $hasData,
            'has_target'   => $targetDaily > 0,
            'width'        => $W, 'height' => $H,
            'base_y'       => $baseY, 'top_y' => $py($max),
            'bars'         => $bars,
            'grid'         => $grid,
            'xticks'       => $xticks,
            'today_x'      => $px($anchorDay),
            'target_y'     => $targetDaily > 0 ? $py($targetDaily) : null,
            'target_label' => $targetDaily > 0 ? 'target/hari ~Rp ' . number_format($targetDaily / 1_000_000, 1, ',', '.') . 'jt' : null,
        ];
    }

    private function buildKpi(Carbon $date, string $anchor, array $branchIds, array $placeIds): array
    {
        $monthStart = $date->copy()->startOfMonth()->format('Y-m-d');
        // Omnicare sync jalan nightly → data "hari ini" biasanya belum lengkap
        // sampai next sync. Tile ini pakai H-1 (kemarin) supaya angkanya
        // reliable, dibandingkan H-2 untuk tren harian.
        $yday    = $date->copy()->subDay()->format('Y-m-d');
        $prevDay = $date->copy()->subDays(2)->format('Y-m-d');

        $revenueYday = $this->omnicareNet($placeIds, $yday, $yday);
        $revenuePrev = $this->omnicareNet($placeIds, $prevDay, $prevDay);

        $resv = DB::table('raw_cus_reservation')
            ->whereIn('raw_branch_id', $branchIds)
            ->where('date', $anchor)
            ->selectRaw('COUNT(*) total, SUM(status = "pending") pending')
            ->first();

        $pasienBaru = 0; $kunjunganMtd = 0;
        if (! empty($placeIds)) {
            $pasienBaru = (int) DB::table('omnicare_medical_record')
                ->whereIn('branch_id', $placeIds)
                ->whereBetween('date', [$monthStart, $anchor])
                ->where('is_new_patient', 1)
                ->distinct('p_id')->count('p_id');

            $kunjunganMtd = (int) DB::table('omnicare_sales_detail')
                ->whereIn('place_id', $placeIds)
                ->whereBetween('trx_date', [$monthStart, $anchor])
                ->whereNotNull('encounter_id')
                ->distinct('encounter_id')->count('encounter_id');
        }

        return [
            'revenue_yday'      => $revenueYday,
            'revenue_delta'     => $revenueYday - $revenuePrev,
            'reservasi_today'   => (int) ($resv->total ?? 0),
            'reservasi_pending' => (int) ($resv->pending ?? 0),
            'pasien_baru_mtd'   => $pasienBaru,
            'kunjungan_mtd'     => $kunjunganMtd,
        ];
    }

    private function buildActivePromos(string $anchor, ?string $sessionBranch)
    {
        $q = DB::table('marketing_promo')
            ->where('status', 'aktif')->where('is_active', 1)
            ->where(fn($w) => $w->whereNull('periode_start')->orWhere('periode_start', '<=', $anchor))
            ->where(fn($w) => $w->whereNull('periode_end')->orWhere('periode_end', '>=', $anchor));
        $this->promoBranchFilter($q, $sessionBranch);

        return $q->select('id', 'nama_promo', 'campaign_type', 'harga_normal', 'harga_promo', 'periode_start', 'periode_end', 'detail_promo', 'syarat_ketentuan')
            ->orderBy('periode_end')->limit(8)->get();
    }

    /** Promo AKAN datang: mulai di masa depan (≤60 hari), buat fallback kalau ga ada yang aktif. */
    private function buildUpcomingPromos(string $anchor, ?string $sessionBranch)
    {
        $until = Carbon::parse($anchor)->addDays(60)->format('Y-m-d');
        $q = DB::table('marketing_promo')
            ->where('is_active', 1)
            ->whereIn('status', ['planning', 'aktif'])
            ->where('periode_start', '>', $anchor)
            ->where('periode_start', '<=', $until);
        $this->promoBranchFilter($q, $sessionBranch);

        return $q->select('id', 'nama_promo', 'campaign_type', 'harga_normal', 'harga_promo', 'periode_start', 'periode_end', 'detail_promo', 'syarat_ketentuan')
            ->orderBy('periode_start')->limit(8)->get();
    }

    /** Filter promo ke cabang user: promo all-cabang (tanpa baris branch) ATAU yang nge-cover cabang ini. */
    private function promoBranchFilter($q, ?string $sessionBranch): void
    {
        if (! $sessionBranch) {
            return;
        }
        $q->where(function ($w) use ($sessionBranch) {
            $w->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))->from('marketing_promo_branch')
                    ->whereColumn('marketing_promo_branch.marketing_promo_id', 'marketing_promo.id');
            })->orWhereExists(function ($sub) use ($sessionBranch) {
                $sub->select(DB::raw(1))->from('marketing_promo_branch')
                    ->whereColumn('marketing_promo_branch.marketing_promo_id', 'marketing_promo.id')
                    ->where('marketing_promo_branch.raw_branch_id', $sessionBranch);
            });
        });
    }

    private function buildReservasiBaru(array $branchIds): array
    {
        $today = Carbon::now()->format('Y-m-d');
        $base = DB::table('raw_cus_reservation')
            ->whereIn('raw_cus_reservation.raw_branch_id', $branchIds)
            ->where('raw_cus_reservation.status', 'pending')
            ->where('raw_cus_reservation.date', '>=', $today);

        $count = (clone $base)->count();
        $latest = (clone $base)
            ->leftJoin('raw_customer as c', 'c.id', '=', 'raw_cus_reservation.raw_customer_id')
            ->orderBy('raw_cus_reservation.date')->orderBy('raw_cus_reservation.start_hour')
            ->limit(5)
            ->get(['raw_cus_reservation.date', 'raw_cus_reservation.start_hour', 'c.name as customer_name']);

        return ['count' => $count, 'latest' => $latest];
    }

    /** Bar "reservasi per hari" 5 hari ke depan — DATA ASLI. */
    private function buildReservasiPerHari(array $branchIds, Carbon $date): array
    {
        $dowShort = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
        $days = [];
        for ($i = 0; $i < 5; $i++) {
            $d = $date->copy()->addDays($i);
            $days[$d->format('Y-m-d')] = [
                'label' => $d->format('d'),
                'mon'   => substr(self::BULAN[$d->month], 0, 3),
                'dow'   => $dowShort[$d->dayOfWeekIso],
                'count' => 0,
            ];
        }

        $counts = DB::table('raw_cus_reservation')
            ->whereIn('raw_branch_id', $branchIds)
            ->whereIn('date', array_keys($days))
            ->where('status', '!=', RawCusReservation::STATUS_CANCELLED)
            ->selectRaw('date, COUNT(*) c')->groupBy('date')->pluck('c', 'date')->toArray();

        foreach ($counts as $dt => $c) {
            $key = Carbon::parse($dt)->format('Y-m-d');
            if (isset($days[$key])) $days[$key]['count'] = (int) $c;
        }

        $vals = array_column($days, 'count');
        return ['days' => array_values($days), 'max' => max(1, $vals ? max($vals) : 1)];
    }

    /** Aksi Cepat kontekstual (prioritas hari ini) — DATA ASLI. */
    private function buildAksiCepat(int $pendingCount, $activePromos, array $kpi, array $target): array
    {
        $rp = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $items = [];

        $items[] = [
            'icon' => 'fa-calendar-check', 'tint' => '#FBE7D2', 'color' => '#C0791F',
            'title' => 'Konfirmasi reservasi',
            'sub'   => $pendingCount > 0 ? 'menunggu persetujuan' : 'semua sudah dikonfirmasi',
            'badge' => $pendingCount > 0 ? (string) $pendingCount : null, 'badge_type' => 'amber',
            'url'   => url('rawreservation'),
        ];

        $best = null; $bestD = 0;
        foreach ($activePromos as $p) {
            if ($p->harga_normal && $p->harga_promo && $p->harga_normal > $p->harga_promo) {
                $d = ($p->harga_normal - $p->harga_promo) / $p->harga_normal * 100;
                if ($d > $bestD) { $bestD = $d; $best = $p; }
            }
        }
        if ($best) {
            $items[] = [
                'icon' => 'fa-tags', 'tint' => '#F7E3EC', 'color' => '#B14A78',
                'title' => 'Tawarkan ' . $best->nama_promo, 'sub' => 'diskon terbesar buat kejar target',
                'badge' => '-' . round($bestD) . '%', 'badge_type' => 'green', 'url' => url('marketing-promo'),
            ];
        }

        if ($target['has_target'] && $target['on_track'] === false) {
            $items[] = [
                'icon' => 'fa-bullseye', 'tint' => '#E2F1EA', 'color' => '#2F8E55',
                'title' => 'Kejar target bulan ini', 'sub' => 'kurang ' . $rp($target['remaining']) . ' lagi',
                'badge' => null, 'badge_type' => 'arrow', 'url' => url('report'),
            ];
        } else {
            $items[] = [
                'icon' => 'fa-chart-bar', 'tint' => '#E2F1EA', 'color' => '#2F8E55',
                'title' => 'Lihat laporan penjualan', 'sub' => 'rincian & histori penjualan',
                'badge' => null, 'badge_type' => 'arrow', 'url' => url('report'),
            ];
        }

        return $items;
    }

    private function buildActionItems(string $anchor, int $dowIso, array $branchIds, array $placeIds): array
    {
        $cuti = DB::table('raw_doctor_leave as l')
            ->join('raw_doctor as d', 'd.id', '=', 'l.raw_doctor_id')
            ->where('l.start_date', '<=', $anchor)->where('l.end_date', '>=', $anchor)
            ->whereExists(function ($sub) use ($branchIds, $dowIso) {
                $sub->select(DB::raw(1))->from('raw_doctor_schedule as ds')
                    ->whereColumn('ds.raw_doctor_id', 'd.id')
                    ->whereIn('ds.raw_branch_id', $branchIds)->where('ds.day', $dowIso);
            })->distinct()->pluck('d.name')->all();

        $stale = [];
        if (! empty($placeIds)) {
            $today = Carbon::now()->startOfDay();
            $rows = DB::table('omnicare_location as ol')
                ->leftJoin('omnicare_sales_detail as os', 'os.place_id', '=', 'ol.id')
                ->whereIn('ol.id', $placeIds)
                ->selectRaw('ol.name, MAX(os.trx_date) AS last_trx')
                ->groupBy('ol.id', 'ol.name')->get();
            foreach ($rows as $r) {
                $days = $r->last_trx ? $today->diffInDays(Carbon::parse($r->last_trx)) : null;
                if ($days === null || $days >= 7) $stale[] = ['name' => $r->name, 'days' => $days];
            }
        }

        return ['dokter_cuti' => $cuti, 'stale' => $stale];
    }

    private function buildDoctorSchedule(string $anchor, int $dowIso, array $branchIds)
    {
        $schedules = DB::table('raw_doctor_schedule as ds')
            ->join('raw_doctor as d', 'd.id', '=', 'ds.raw_doctor_id')
            ->join('raw_branch as b', 'b.id', '=', 'ds.raw_branch_id')
            ->whereIn('ds.raw_branch_id', $branchIds)->where('ds.day', $dowIso)
            ->select('d.id AS doctor_id', 'd.name AS doctor_name', 'b.name AS branch_name', 'ds.start_hour', 'ds.end_hour')
            ->orderBy('b.name')->orderBy('ds.start_hour')->orderBy('d.name')->get();

        $onLeave = DB::table('raw_doctor_leave')
            ->where('start_date', '<=', $anchor)->where('end_date', '>=', $anchor)
            ->pluck('raw_doctor_id')->toArray();

        $schedules->each(function ($s) use ($onLeave) {
            $s->on_leave = in_array($s->doctor_id, $onLeave, true);
            $s->start_hm = substr((string) $s->start_hour, 0, 5);
            $s->end_hm   = substr((string) $s->end_hour, 0, 5);
        });

        return $schedules->groupBy('branch_name');
    }

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
}
