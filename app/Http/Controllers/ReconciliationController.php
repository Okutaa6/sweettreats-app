<?php

namespace App\Http\Controllers;

use App\Support\OmnicareEncounters;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi — UI tindak lanjut hasil engine `omnicare:reconcile`.
 *
 * Engine mencocokkan reservasi SweetTreats ↔ encounter Omnicare otomatis.
 * Yang ambigu (>1 encounter sama kuat) ditandai conflict — di sinilah FO/Sales
 * Admin per cabang memilih encounter yang benar secara manual.
 *
 * Branch scoping: user ter-scope (session raw_branch_id) hanya lihat cabangnya.
 *
 * Status diturunkan dari kolom, bukan disimpan eksplisit:
 *   omnicare_record_id terisi            → linked
 *   confidence 'low', record_id kosong   → conflict (perlu pilih manual)
 *   method 'orphan_noshow'/'manual_noshow' → no-show
 *   method 'cancelled'                   → reservasi DIBATALKAN, BUKAN no-show
 *   method 'no_customer_link'            → customer belum ter-link Omnicare
 */
class ReconciliationController extends Controller
{
    private const WINDOW = 3; // hari — harus sama dengan OmnicareReconcile::MATCH_WINDOW

    public function index()
    {
        [$placeId, $branchLocked, $branchName, $branches] = $this->resolveBranch();

        $base = fn() => DB::table('reservation_omnicare_link as l')
            ->when($placeId, fn($q) => $q->where('l.place_id', $placeId));

        // ── Ringkasan ─────────────────────────────────────────────────────
        $summary = [
            'linked'   => (clone $base())->whereNotNull('l.omnicare_record_id')->count(),
            'conflict' => (clone $base())->where('l.match_confidence', 'low')
                                         ->whereNull('l.omnicare_record_id')->count(),
            'no_show'  => (clone $base())->whereIn('l.match_method', ['orphan_noshow', 'manual_noshow'])->count(),
            'no_link'  => (clone $base())->where('l.match_method', 'no_customer_link')->count(),
            // ⛔ Pembatalan BUKAN ketidakhadiran — dihitung SENDIRI dan sengaja
            //    TIDAK masuk penyebut konversi. Kalau bucket ini tidak ada,
            //    reservasi yang dibatalkan lenyap dari layar tanpa jejak.
            'cancelled' => (clone $base())->where('l.match_method', 'cancelled')->count(),
            'revenue'  => (float) (clone $base())->whereNotNull('l.omnicare_record_id')->sum('l.actual_revenue'),
        ];
        $finished = $summary['linked'] + $summary['no_show'] + $summary['conflict'];
        $summary['conversion'] = $finished > 0 ? round($summary['linked'] / $finished * 100, 1) : 0.0;

        // ── Conflict queue (perlu tindakan) ───────────────────────────────
        $conflicts = (clone $base())
            ->join('raw_cus_reservation as r', 'r.id', '=', 'l.raw_cus_reservation_id')
            ->leftJoin('raw_customer as c', 'c.id', '=', 'r.raw_customer_id')
            ->leftJoin('raw_branch as b', 'b.id', '=', 'r.raw_branch_id')
            ->leftJoin('raw_doctor as d', 'd.id', '=', 'r.raw_doctor_id')
            ->where('l.match_confidence', 'low')
            ->whereNull('l.omnicare_record_id')
            ->orderBy('r.date')
            ->select('l.id', 'l.notes', 'r.id as res_id', 'r.date as res_date',
                     'c.name as cust_name', 'c.phone as cust_phone',
                     'b.name as branch', 'd.name as doctor')
            ->get();

        foreach ($conflicts as $cf) {
            $cf->candidates = $this->candidatesFor($cf->res_id);
        }

        // ── Reservasi tanpa link customer (informational) ─────────────────
        $noLink = (clone $base())
            ->join('raw_cus_reservation as r', 'r.id', '=', 'l.raw_cus_reservation_id')
            ->leftJoin('raw_customer as c', 'c.id', '=', 'r.raw_customer_id')
            ->leftJoin('raw_branch as b', 'b.id', '=', 'r.raw_branch_id')
            ->where('l.match_method', 'no_customer_link')
            ->orderBy('r.date')
            ->select('r.date as res_date', 'c.name as cust_name',
                     'c.phone as cust_phone', 'b.name as branch')
            ->get();

        // ── Riwayat hasil reconcile (linked + no-show + dibatalkan) ───────
        //    `cancelled` WAJIB ikut. Kalau tidak, reservasi yang dulu tampil
        //    sebagai no-show hilang sama sekali dari layar begitu vonisnya
        //    diperbaiki — orang akan mengira datanya hilang, bukan dibetulkan.
        $resolved = (clone $base())
            ->join('raw_cus_reservation as r', 'r.id', '=', 'l.raw_cus_reservation_id')
            ->leftJoin('raw_customer as c', 'c.id', '=', 'r.raw_customer_id')
            ->leftJoin('raw_branch as b', 'b.id', '=', 'r.raw_branch_id')
            ->where(function ($q) {
                $q->whereNotNull('l.omnicare_record_id')
                  ->orWhereIn('l.match_method', ['orphan_noshow', 'manual_noshow', 'cancelled']);
            })
            ->orderByDesc('r.date')
            ->select('l.id', 'l.omnicare_record_id', 'l.match_confidence', 'l.match_method',
                     'l.actual_revenue', 'l.payment_status', 'l.encounter_date', 'l.notes',
                     'l.reconciled_by', 'r.date as res_date',
                     'c.name as cust_name', 'b.name as branch')
            ->limit(300)
            ->get();

        return view('report.reconcile', compact(
            'summary', 'conflicts', 'noLink', 'resolved',
            'placeId', 'branchLocked', 'branchName', 'branches'
        ));
    }

    /**
     * Resolusi conflict: admin memilih encounter yang benar.
     */
    public function link(Request $request, string $id)
    {
        $encounterId = trim((string) $request->input('encounter_id'));
        $link = DB::table('reservation_omnicare_link')->where('id', $id)->first();

        if (! $link || ! $link->raw_cus_reservation_id) {
            return back()->with('error', 'Baris rekonsiliasi tidak ditemukan.');
        }
        if ($encounterId === '') {
            return back()->with('error', 'Encounter belum dipilih.');
        }

        // Validasi: encounter harus benar milik pasien reservasi ini, dalam window
        $candidates = $this->candidatesFor($link->raw_cus_reservation_id);
        $chosen = collect($candidates)->firstWhere('encounter_id', $encounterId);
        if (! $chosen) {
            return back()->with('error', 'Encounter tidak valid untuk reservasi ini.');
        }

        DB::table('reservation_omnicare_link')->where('id', $id)->update([
            'omnicare_record_id' => $chosen['encounter_id'],
            'match_confidence'   => 'manual',
            'match_method'       => 'manual',
            'actual_revenue'     => $chosen['revenue'],
            'payment_status'     => $chosen['payment_status'],
            'encounter_date'     => $chosen['trx_date'],
            'notes'              => 'Dipilih manual oleh ' . $this->actor() . ' pada ' . now()->format('Y-m-d H:i'),
            'reconciled_by'      => $this->actor(),
            'reconciled_at'      => now(),
        ]);

        return back()->with('success', 'Reservasi berhasil di-link ke encounter ' . $encounterId . '.');
    }

    /**
     * Tandai reservasi sebagai no-show (tidak ada encounter yang cocok).
     */
    public function noshow(Request $request, string $id)
    {
        $link = DB::table('reservation_omnicare_link')->where('id', $id)->first();
        if (! $link || ! $link->raw_cus_reservation_id) {
            return back()->with('error', 'Baris rekonsiliasi tidak ditemukan.');
        }

        DB::table('reservation_omnicare_link')->where('id', $id)->update([
            'omnicare_record_id' => null,
            'match_confidence'   => 'no_match',
            'match_method'       => 'manual_noshow',
            'actual_revenue'     => null,
            'payment_status'     => null,
            'encounter_date'     => null,
            'notes'              => 'Ditandai no-show manual oleh ' . $this->actor() . ' pada ' . now()->format('Y-m-d H:i'),
            'reconciled_by'      => $this->actor(),
            'reconciled_at'      => now(),
        ]);

        return back()->with('success', 'Reservasi ditandai no-show.');
    }

    // ────────────────────────────────────────────────────────────────────

    /**
     * Encounter kandidat untuk satu reservasi — semua kunjungan pasien dalam
     * ±WINDOW hari. Dipakai UI conflict (tampilkan semua, manusia yang pilih)
     * dan validasi server-side saat link.
     */
    private function candidatesFor(string $reservationId): array
    {
        // ⛔ JANGAN kembalikan leftJoin ke customer_omnicare_link di sini.
        //    Satu pasien boleh punya BANYAK pid (Omnicare menerbitkan p_id per
        //    cabang) — leftJoin + first() akan memungut satu pid sembarang dan
        //    menyembunyikan kunjungan pasien di cabang lain, persis cacat yang
        //    diperbaiki 2026-09-16. Ambil SEMUA pid-nya.
        $res = DB::table('raw_cus_reservation as r')
            ->leftJoin('raw_branch as b', 'b.id', '=', 'r.raw_branch_id')
            ->where('r.id', $reservationId)
            ->select('r.date', 'r.raw_customer_id', 'b.omnicare_place_id as place_id')
            ->first();

        if (! $res || empty($res->raw_customer_id)) {
            return [];
        }

        $pids = DB::table('customer_omnicare_link')
            ->where('raw_customer_id', $res->raw_customer_id)
            ->pluck('omnicare_pid')
            ->all();

        if (empty($pids)) {
            return [];
        }

        $d = Carbon::parse($res->date);
        $lines = DB::table('omnicare_sales_detail')
            ->whereIn('p_id', $pids)
            ->whereBetween('trx_date', [
                $d->copy()->subDays(self::WINDOW)->toDateString(),
                $d->copy()->addDays(self::WINDOW)->toDateString(),
            ])
            ->whereNotNull('encounter_id')
            ->where('encounter_id', '<>', '')
            ->select('encounter_id', 'trx_date', 'place_id', 'branch_name', 'doctor',
                     'net_sell_price', 'item_type', 'item_name', 'qty_sold', 'payment_status')
            ->get();

        $enc = OmnicareEncounters::group($lines);

        // Tandai cabang cocok + urutkan terdekat dulu
        foreach ($enc as &$e) {
            $e['same_branch'] = ((string) $e['place_id'] === (string) $res->place_id);
        }
        unset($e);

        usort($enc, fn($a, $b) =>
            abs(Carbon::parse($a['trx_date'])->diffInDays(Carbon::parse($res->date)))
            <=> abs(Carbon::parse($b['trx_date'])->diffInDays(Carbon::parse($res->date)))
        );

        return $enc;
    }

    /**
     * Resolve branch scope — pola sama dengan SalesReportController.
     * @return array{0:?string,1:bool,2:string,3:\Illuminate\Support\Collection}
     */
    private function resolveBranch(): array
    {
        $branches = DB::table('omnicare_location')
            ->whereIn('id', config('omnicare.active_branches', []))
            ->orderBy('name')
            ->get(['id', 'name']);

        $branchUuid = session('raw_branch_id');
        if ($branchUuid) {
            $placeId = DB::table('raw_branch')->where('id', $branchUuid)->value('omnicare_place_id');
            if ($placeId) {
                $name = optional($branches->firstWhere('id', $placeId))->name ?? 'Cabang';
                return [$placeId, true, $name, $branches];
            }
        }

        $placeId = request('place') ?: null;
        if ($placeId && ! $branches->contains('id', $placeId)) {
            $placeId = null;
        }
        $name = $placeId
            ? (optional($branches->firstWhere('id', $placeId))->name ?? 'Cabang')
            : 'Semua Cabang';

        return [$placeId, false, $name, $branches];
    }

    /** Nama pelaku tindakan manual untuk audit trail. */
    private function actor(): string
    {
        return (string) (session('name') ?: session('user_id') ?: 'admin');
    }
}
