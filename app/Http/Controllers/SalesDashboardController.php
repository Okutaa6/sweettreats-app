<?php

namespace App\Http\Controllers;

use App\Models\RawCusReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sales Dashboard — operational day-to-day view untuk tim Sales.
 *
 * Fokus: hari ini (atau tanggal yang dipilih). Menggabungkan data SweetTreats
 * (reservasi, jadwal dokter, promo) dengan data Omnicare (pemasukan aktual).
 */
class SalesDashboardController extends Controller
{
    public function index()
    {
        // Tanggal yang dilihat — default hari ini. Sales bisa pilih tanggal lain.
        $dateStr = request('date', Carbon::now()->format('Y-m-d'));
        $date    = Carbon::parse($dateStr)->startOfDay();
        $dateYmd = $date->format('Y-m-d');
        $dowIso  = $date->dayOfWeekIso; // 1=Senin ... 7=Minggu (match raw_doctor_schedule.day)

        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

        return view('dashboard.sales', [
            'date'          => $date,
            'dateYmd'       => $dateYmd,
            'dayLabel'      => $dayNames[$dowIso] . ', ' . $date->format('d M Y'),
            'isToday'       => $date->isToday(),
            'summary'       => $this->buildSummary($dateYmd),
            'reservasiPerBranch' => $this->buildReservasiPerBranch($dateYmd),
            'doctorSchedule'     => $this->buildDoctorSchedule($dateYmd, $dowIso),
            'scheduleExceptions' => $this->buildScheduleExceptions($dateYmd),
            'activePromos'  => $this->buildActivePromos($dateYmd),
            'kolVisits'     => $this->buildKolVisits($dateYmd),
            'staleBranches' => $this->buildStaleBranches(),
            'statusOptions' => RawCusReservation::statusOptions(),
        ]);
    }

    /**
     * Summary cards: reservasi count, pemasukan planned vs actual, pasien baru.
     */
    private function buildSummary(string $dateYmd): array
    {
        // Reservasi hari ini — count + breakdown status
        $reservasi = DB::table('raw_cus_reservation')
            ->where('date', $dateYmd)
            ->selectRaw('COUNT(*) AS total, status')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalReservasi = $reservasi->sum();

        // Pemasukan PLANNED — dari reservasi SweetTreats (total_reservasi)
        $plannedIncome = (float) DB::table('raw_cus_reservation')
            ->where('date', $dateYmd)
            ->where('status', '!=', RawCusReservation::STATUS_CANCELLED)
            ->sum('total_reservasi');

        // Pemasukan ACTUAL — dari Omnicare sales (trx_date = tanggal ini)
        $actualIncome = (float) DB::table('omnicare_sales_detail')
            ->where('trx_date', $dateYmd)
            ->sum('net_sell_price');

        $actualVisits = (int) DB::table('omnicare_sales_detail')
            ->where('trx_date', $dateYmd)
            ->distinct('encounter_id')
            ->count('encounter_id');

        // Pasien baru — reservasi hari ini dengan customer pasien_baru_lama='baru'
        $pasienBaru = DB::table('raw_cus_reservation as r')
            ->join('raw_customer as c', 'c.id', '=', 'r.raw_customer_id')
            ->where('r.date', $dateYmd)
            ->where('c.pasien_baru_lama', 'baru')
            ->distinct('r.raw_customer_id')
            ->count('r.raw_customer_id');

        return [
            'total_reservasi'  => $totalReservasi,
            'reservasi_status' => $reservasi,
            'planned_income'   => $plannedIncome,
            'actual_income'    => $actualIncome,
            'actual_visits'    => $actualVisits,
            'income_variance'  => $actualIncome - $plannedIncome,
            'pasien_baru'      => $pasienBaru,
        ];
    }

    /**
     * Reservasi per cabang untuk tanggal terpilih.
     * Mulai FROM raw_branch + leftJoin dengan kondisi tanggal di ON clause,
     * supaya semua cabang tetap muncul (count 0) walau tidak ada reservasi.
     */
    private function buildReservasiPerBranch(string $dateYmd)
    {
        return DB::table('raw_branch as b')
            ->leftJoin('raw_cus_reservation as r', function ($join) use ($dateYmd) {
                $join->on('r.raw_branch_id', '=', 'b.id')
                     ->where('r.date', '=', $dateYmd);
            })
            ->selectRaw('
                b.id AS branch_id,
                b.name AS branch_name,
                COUNT(r.id) AS total,
                SUM(CASE WHEN r.status = "pending" THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN r.status = "confirmed" THEN 1 ELSE 0 END) AS confirmed,
                SUM(CASE WHEN r.status = "completed" THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN r.status = "cancelled" THEN 1 ELSE 0 END) AS cancelled,
                COALESCE(SUM(CASE WHEN r.status != "cancelled" THEN r.total_reservasi ELSE 0 END), 0) AS revenue
            ')
            ->groupBy('b.id', 'b.name')
            ->orderByDesc('total')
            ->orderBy('b.name')
            ->get();
    }

    /**
     * Jadwal dokter praktik di tanggal terpilih (recurring schedule + leave flag).
     * Dikelompokkan per cabang.
     */
    private function buildDoctorSchedule(string $dateYmd, int $dowIso)
    {
        $schedules = DB::table('raw_doctor_schedule as ds')
            ->join('raw_doctor as d', 'd.id', '=', 'ds.raw_doctor_id')
            ->join('raw_branch as b', 'b.id', '=', 'ds.raw_branch_id')
            ->where('ds.day', $dowIso)
            ->select(
                'd.id AS doctor_id',
                'd.name AS doctor_name',
                'b.id AS branch_id',
                'b.name AS branch_name',
                'ds.start_hour',
                'ds.end_hour'
            )
            ->orderBy('b.name')
            ->orderBy('ds.start_hour')
            ->orderBy('d.name')
            ->get();

        // Dokter yang cuti pada tanggal ini
        $onLeave = DB::table('raw_doctor_leave')
            ->where('start_date', '<=', $dateYmd)
            ->where('end_date', '>=', $dateYmd)
            ->pluck('raw_doctor_id')
            ->toArray();

        $schedules->each(function ($s) use ($onLeave) {
            $s->on_leave = in_array($s->doctor_id, $onLeave, true);
            $s->start_hm = substr((string) $s->start_hour, 0, 5);
            $s->end_hm   = substr((string) $s->end_hour, 0, 5);
        });

        // Group by branch untuk display
        return $schedules->groupBy('branch_name');
    }

    /**
     * Perubahan jadwal (exception) di tanggal terpilih — kalau fitur flexible aktif.
     */
    private function buildScheduleExceptions(string $dateYmd)
    {
        if (! config('features.flexible_scheduling', false)) {
            return collect();
        }

        return DB::table('raw_doctor_schedule_exception as e')
            ->join('raw_doctor as d', 'd.id', '=', 'e.raw_doctor_id')
            ->join('raw_branch as b', 'b.id', '=', 'e.raw_branch_id')
            ->where('e.date', $dateYmd)
            ->select(
                'd.name AS doctor_name',
                'b.name AS branch_name',
                'e.type',
                'e.start_hour',
                'e.end_hour',
                'e.reason'
            )
            ->orderBy('b.name')
            ->get();
    }

    /**
     * Promo marketing yang aktif & berlaku pada tanggal terpilih.
     */
    private function buildActivePromos(string $dateYmd)
    {
        return DB::table('marketing_promo')
            ->where('status', 'aktif')
            ->where(function ($q) use ($dateYmd) {
                $q->whereNull('periode_start')->orWhere('periode_start', '<=', $dateYmd);
            })
            ->where(function ($q) use ($dateYmd) {
                $q->whereNull('periode_end')->orWhere('periode_end', '>=', $dateYmd);
            })
            ->select('id', 'nama_promo', 'campaign_type', 'harga_normal', 'harga_promo', 'periode_start', 'periode_end')
            ->orderBy('periode_end')
            ->get();
    }

    /**
     * Kunjungan terkait KOL pada tanggal terpilih, per cabang.
     * KOL visit = reservasi yang pakai kode referral dari affiliate type='kol'.
     */
    private function buildKolVisits(string $dateYmd)
    {
        return DB::table('raw_cus_reservation as r')
            ->join('raw_promo as p', 'p.id', '=', 'r.raw_promo_id')
            ->join('raw_affiliate as a', 'a.id', '=', 'p.raw_affiliate_id')
            ->join('raw_branch as b', 'b.id', '=', 'r.raw_branch_id')
            ->leftJoin('raw_customer as c', 'c.id', '=', 'r.raw_customer_id')
            ->where('r.date', $dateYmd)
            ->where('a.type', 'kol')
            ->select(
                'b.name AS branch_name',
                'a.name AS kol_name',
                'a.kol_kategori',
                'c.name AS patient_name',
                'r.start_hour',
                'r.status'
            )
            ->orderBy('b.name')
            ->orderBy('r.start_hour')
            ->get()
            ->groupBy('branch_name');
    }

    /**
     * Cabang dengan data Omnicare stale (tidak ada sales >= 7 hari).
     * Alert untuk sales lead — bisa indikasi staff backlog input / cabang issue.
     */
    private function buildStaleBranches()
    {
        $today = Carbon::now()->startOfDay();

        $rows = DB::table('omnicare_location as ol')
            ->leftJoin('omnicare_sales_detail as os', 'os.place_id', '=', 'ol.id')
            ->whereIn('ol.id', config('omnicare.active_branches', []))
            ->selectRaw('ol.id, ol.name, MAX(os.trx_date) AS last_trx')
            ->groupBy('ol.id', 'ol.name')
            ->get();

        return $rows->map(function ($r) use ($today) {
            $days = $r->last_trx ? $today->diffInDays(Carbon::parse($r->last_trx)) : null;
            $r->days_since = $days;
            return $r;
        })->filter(fn($r) => $r->days_since === null || $r->days_since >= 7)
          ->values();
    }
}
