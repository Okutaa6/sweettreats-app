<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales Report — rekap penjualan periodik dari data Omnicare (omnicare_sales_detail).
 *
 * Menggantikan workflow manual: download Omnicare → ketik ke sheets.
 * 6 report dalam 1 page tabbed, masing-masing bisa di-export CSV (UTF-8 BOM,
 * kebuka rapi di Excel — konsisten dengan ExportController).
 *
 * Branch scoping: user yang ter-scope ke cabang (session raw_branch_id) hanya
 * lihat cabangnya. Executive/admin lihat semua + bisa filter cabang.
 */
class SalesReportController extends Controller
{
    /** Tab key => label. */
    private const TABS = [
        'rekap'    => 'Rekap Penjualan',
        'prosedur' => 'Penjualan per Prosedur',
        'dokter'   => 'Penjualan per Dokter',
        'pasien'   => 'Transaksi Pasien',
        'staff'    => 'Penjualan per Staff',
        'rincian'  => 'Rincian Penjualan',
        'piutang'  => 'Piutang',
    ];

    public function index()
    {
        $ctx = $this->resolveContext();

        $report = $this->buildReport($ctx['tab'], $ctx['start'], $ctx['end'], $ctx['placeId']);

        return view('report.sales', array_merge($ctx, [
            'tabs'   => self::TABS,
            'report' => $report,
        ]));
    }

    public function export()
    {
        $ctx = $this->resolveContext();
        [$headers, $rows] = $this->buildExport($ctx['tab'], $ctx['start'], $ctx['end'], $ctx['placeId']);

        $tabLabel = self::TABS[$ctx['tab']] ?? $ctx['tab'];
        $filename = 'report_' . $ctx['tab'] . '_' . $ctx['start'] . '_' . $ctx['end'] . '.csv';

        return new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM supaya Excel baca encoding + kolom dengan benar
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // Context resolution
    // ────────────────────────────────────────────────────────────────

    private function resolveContext(): array
    {
        $tab = request('tab', 'rekap');
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'rekap';
        }

        // Date range — fleksibel. Default: bulan berjalan (s/d hari ini).
        $today = Carbon::now();
        $start = $end = null;
        try {
            if (request('date_from')) {
                $start = Carbon::parse(request('date_from'))->format('Y-m-d');
            }
            if (request('date_to')) {
                $end = Carbon::parse(request('date_to'))->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            $start = $end = null;
        }
        if (! $start || ! $end) {
            $start = $today->copy()->startOfMonth()->format('Y-m-d');
            $end   = $today->copy()->format('Y-m-d');
        }
        if ($end < $start) {
            [$start, $end] = [$end, $start]; // swap kalau kebalik
        }

        // Preset cepat untuk quick-filter
        $presets = [
            'Hari Ini'   => [$today->format('Y-m-d'), $today->format('Y-m-d')],
            'Minggu Ini' => [$today->copy()->startOfWeek()->format('Y-m-d'),
                             $today->copy()->endOfWeek()->format('Y-m-d')],
            'Bulan Ini'  => [$today->copy()->startOfMonth()->format('Y-m-d'),
                             $today->format('Y-m-d')],
            'Bulan Lalu' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
                             $today->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d')],
            '30 Hari'    => [$today->copy()->subDays(29)->format('Y-m-d'), $today->format('Y-m-d')],
            '90 Hari'    => [$today->copy()->subDays(89)->format('Y-m-d'), $today->format('Y-m-d')],
        ];

        [$placeId, $branchLocked, $branchName, $branches] = $this->resolveBranch();

        return compact('tab', 'start', 'end', 'presets',
                       'placeId', 'branchLocked', 'branchName', 'branches');
    }

    /**
     * Resolve branch scope.
     * @return array{0:?string,1:bool,2:string,3:\Illuminate\Support\Collection}
     *   [placeId(null=all), locked(bool), branchName, allBranches]
     */
    private function resolveBranch(): array
    {
        $branches = DB::table('omnicare_location')
            ->whereIn('id', config('omnicare.active_branches', []))
            ->orderBy('name')
            ->get(['id', 'name']);

        // User ter-scope ke cabang? (pola sama dengan ExportController)
        $branchUuid = session('raw_branch_id');
        if ($branchUuid) {
            $placeId = DB::table('raw_branch')->where('id', $branchUuid)->value('omnicare_place_id');
            if ($placeId) {
                $name = optional($branches->firstWhere('id', $placeId))->name ?? 'Cabang';
                return [$placeId, true, $name, $branches];
            }
        }

        // Executive/admin: optional ?place= filter
        $placeId = request('place') ?: null;
        if ($placeId && ! $branches->contains('id', $placeId)) {
            $placeId = null;
        }
        $name = $placeId
            ? (optional($branches->firstWhere('id', $placeId))->name ?? 'Cabang')
            : 'Semua Cabang';

        return [$placeId, false, $name, $branches];
    }

    /** Apply branch + date filter ke query builder omnicare_sales_detail. */
    private function scopedQuery(string $start, string $end, ?string $placeId)
    {
        $q = DB::table('omnicare_sales_detail')->whereBetween('trx_date', [$start, $end]);
        if ($placeId) {
            $q->where('place_id', $placeId);
        }
        return $q;
    }

    // ────────────────────────────────────────────────────────────────
    // Report builders (untuk display)
    // ────────────────────────────────────────────────────────────────

    private function buildReport(string $tab, string $start, string $end, ?string $placeId): array
    {
        return match ($tab) {
            'prosedur' => ['prosedur' => $this->qProsedur($start, $end, $placeId)->get()],
            'dokter'   => ['dokter'   => $this->qDokter($start, $end, $placeId)->get()],
            'pasien'   => ['pasien'   => $this->qPasien($start, $end, $placeId)->get()],
            'staff'    => ['staff'    => $this->qStaff($start, $end, $placeId)->get()],
            'rincian'  => ['rincian'  => $this->qRincian($start, $end, $placeId)->limit(300)->get(),
                           'rincianTotal' => $this->scopedQuery($start, $end, $placeId)->count()],
            'piutang'  => ['piutang'  => $this->qPiutang($start, $end, $placeId)->get()],
            default    => $this->buildRekap($start, $end, $placeId),
        };
    }

    private function buildRekap(string $start, string $end, ?string $placeId): array
    {
        // Summary — Voucher Tunai (item_type 'V') & Service Charge DIPISAH dari
        // total utama. "Net" = prosedur + obat saja (cocok catatan manual cabang).
        $summary = $this->scopedQuery($start, $end, $placeId)
            ->selectRaw('
                COUNT(*) AS line_count,
                COUNT(DISTINCT encounter_id) AS visits,
                COUNT(DISTINCT p_id) AS patients,
                COALESCE(SUM(CASE WHEN item_type <> "V" AND item_name NOT LIKE "Service Charge%" THEN discount       ELSE 0 END),0) AS discount,
                COALESCE(SUM(CASE WHEN item_type <> "V" AND item_name NOT LIKE "Service Charge%" THEN net_sell_price ELSE 0 END),0) AS net,
                COALESCE(SUM(CASE WHEN item_type  = "V" THEN net_sell_price ELSE 0 END),0) AS voucher,
                COALESCE(SUM(CASE WHEN item_name LIKE "Service Charge%" THEN net_sell_price ELSE 0 END),0) AS service_charge
            ')->first();

        // Per-hari (net exclude voucher + service charge)
        $perDay = $this->scopedQuery($start, $end, $placeId)
            ->selectRaw('
                trx_date,
                COUNT(*) AS line_count,
                COUNT(DISTINCT encounter_id) AS visits,
                COALESCE(SUM(CASE WHEN item_type <> "V" AND item_name NOT LIKE "Service Charge%" THEN net_sell_price ELSE 0 END),0) AS net
            ')
            ->groupBy('trx_date')
            ->orderBy('trx_date')
            ->get();

        $payment = $this->scopedQuery($start, $end, $placeId)
            ->selectRaw('payment_status, COUNT(*) AS line_count, COALESCE(SUM(net_sell_price),0) AS net')
            ->groupBy('payment_status')
            ->get();

        // Komposisi penjualan per tipe (P=procedure, D=obat/BHP, SC=service charge, V=voucher)
        $composition = $this->scopedQuery($start, $end, $placeId)
            ->selectRaw('
                CASE WHEN item_name LIKE "Service Charge%" THEN "SC" ELSE item_type END AS item_type,
                COUNT(*) AS line_count,
                COALESCE(SUM(qty_sold),0)       AS qty,
                COALESCE(SUM(net_sell_price),0) AS net
            ')
            ->groupBy(DB::raw('CASE WHEN item_name LIKE "Service Charge%" THEN "SC" ELSE item_type END'))
            ->orderByDesc('net')
            ->get();

        return ['rekap' => [
            'summary'      => $summary,
            'perDay'       => $perDay,
            'payment'      => $payment,
            'composition'  => $composition,
            'patientStats' => $this->patientNewVsOld($start, $end, $placeId),
        ]];
    }

    /**
     * Klasifikasi pasien dalam periode: Baru / Repeat-Ortho / Repeat-NonOrtho.
     *
     *  - "Baru": bulan registrasi (YYMM di p_id Omnicare <prefix>-YYMM-NNNN)
     *    jatuh dalam periode filter. Konsisten dengan BARU/LAMA Omnicare.
     *  - "Repeat - Behel/Ortho": pasien lama yang punya item ortho (kode prefix
     *    CON=kontrol behel, PO/OT=pasang ortho) — repeat "terikat" jadwal kontrol.
     *  - "Repeat - Non-Ortho": pasien lama tanpa item ortho (scaling, tambal,
     *    cabut, dll.) — sinyal loyalitas lebih murni.
     */
    private function patientNewVsOld(string $start, string $end, ?string $placeId): object
    {
        $startYYMM = Carbon::parse($start)->format('ym');
        $endYYMM   = Carbon::parse($end)->format('ym');

        // Per pasien: bulan registrasi + apakah ada item ortho/behel
        $sub = $this->scopedQuery($start, $end, $placeId)
            ->whereNotNull('p_id')->where('p_id', '!=', '')
            ->selectRaw(
                "p_id,
                 REGEXP_SUBSTR(p_id, '[0-9]{4}') AS reg_yymm,
                 MAX(CASE WHEN item_code REGEXP '^[0-9]{2}(CON|PO|OT)' THEN 1 ELSE 0 END) AS has_ortho"
            )
            ->groupBy('p_id');

        $row = DB::query()->fromSub($sub, 't')
            ->selectRaw(
                "COUNT(*) AS total,
                 COALESCE(SUM(CASE WHEN reg_yymm BETWEEN ? AND ? THEN 1 ELSE 0 END),0) AS baru,
                 COALESCE(SUM(CASE WHEN NOT (reg_yymm BETWEEN ? AND ?) AND has_ortho = 1 THEN 1 ELSE 0 END),0) AS repeat_ortho,
                 COALESCE(SUM(CASE WHEN NOT (reg_yymm BETWEEN ? AND ?) AND has_ortho = 0 THEN 1 ELSE 0 END),0) AS repeat_nonortho",
                [$startYYMM, $endYYMM, $startYYMM, $endYYMM, $startYYMM, $endYYMM]
            )
            ->first();

        $ortho    = (int) ($row->repeat_ortho ?? 0);
        $nonOrtho = (int) ($row->repeat_nonortho ?? 0);

        return (object) [
            'baru'            => (int) ($row->baru ?? 0),
            'repeat_ortho'    => $ortho,
            'repeat_nonortho' => $nonOrtho,
            'repeat_total'    => $ortho + $nonOrtho,
            'total'           => (int) ($row->total ?? 0),
        ];
    }

    private function qStaff(string $start, string $end, ?string $placeId)
    {
        return $this->scopedQuery($start, $end, $placeId)
            ->whereNotNull('username')->where('username', '!=', '')
            ->selectRaw('
                username,
                COUNT(*) AS line_count,
                COUNT(DISTINCT invoice_num)  AS invoices,
                COUNT(DISTINCT encounter_id) AS visits,
                COALESCE(SUM(net_sell_price),0) AS net
            ')
            ->groupBy('username')
            ->orderByDesc('net');
    }

    // Query builders — dipakai bersama display + export

    private function qProsedur(string $start, string $end, ?string $placeId)
    {
        return $this->scopedQuery($start, $end, $placeId)
            ->selectRaw('
                item_code, item_name, item_type,
                SUM(qty_sold) AS qty,
                COALESCE(SUM(gross_price),0)    AS gross,
                COALESCE(SUM(discount),0)       AS discount,
                COALESCE(SUM(net_sell_price),0) AS net,
                COALESCE(SUM(net_profit),0)     AS profit
            ')
            ->groupBy('item_code', 'item_name', 'item_type')
            ->orderByDesc('qty');
    }

    private function qDokter(string $start, string $end, ?string $placeId)
    {
        return DB::table('omnicare_sales_detail as os')
            ->leftJoin('raw_doctor as rd', 'rd.omnicare_doc_id', '=', 'os.doc_reff_id')
            ->whereBetween('os.trx_date', [$start, $end])
            ->when($placeId, fn($q) => $q->where('os.place_id', $placeId))
            ->whereNotNull('os.doc_reff_id')->where('os.doc_reff_id', '!=', '')
            ->selectRaw('
                os.doc_reff_id,
                COALESCE(rd.name, os.doctor) AS doctor_name,
                COUNT(DISTINCT os.encounter_id) AS visits,
                COUNT(*) AS line_count,
                COALESCE(SUM(os.net_sell_price),0) AS net,
                COALESCE(SUM(os.net_profit),0)     AS profit
            ')
            ->groupBy('os.doc_reff_id', 'doctor_name')
            ->orderByDesc('net');
    }

    private function qPasien(string $start, string $end, ?string $placeId)
    {
        return $this->scopedQuery($start, $end, $placeId)
            ->selectRaw('
                p_id, p_name,
                COUNT(DISTINCT encounter_id) AS visits,
                COUNT(*) AS line_count,
                COALESCE(SUM(net_sell_price),0) AS total,
                ROUND(COALESCE(SUM(net_sell_price),0) / GREATEST(COUNT(DISTINCT encounter_id),1)) AS avg_visit
            ')
            ->whereNotNull('p_id')->where('p_id', '!=', '')
            ->groupBy('p_id', 'p_name')
            ->orderByDesc('total');
    }

    private function qRincian(string $start, string $end, ?string $placeId)
    {
        return $this->scopedQuery($start, $end, $placeId)
            ->select('trx_date', 'invoice_num', 'p_name', 'doctor', 'item_code',
                     'item_name', 'item_type', 'qty_sold', 'gross_price',
                     'discount', 'net_sell_price', 'payment_status')
            ->orderBy('trx_date')
            ->orderBy('invoice_num');
    }

    private function qPiutang(string $start, string $end, ?string $placeId)
    {
        return $this->scopedQuery($start, $end, $placeId)
            ->where('payment_status', 'unpaid')
            ->select('trx_date', 'invoice_num', 'p_name', 'doctor', 'item_name',
                     'qty_sold', 'net_sell_price')
            ->orderBy('trx_date');
    }

    // ────────────────────────────────────────────────────────────────
    // Export builders — return [headers[], rows[][]]
    // ────────────────────────────────────────────────────────────────

    private function buildExport(string $tab, string $start, string $end, ?string $placeId): array
    {
        return match ($tab) {
            'prosedur' => [
                ['Kode', 'Nama Item', 'Tipe', 'Terjual', 'Gross', 'Diskon', 'Net', 'Profit'],
                $this->qProsedur($start, $end, $placeId)->get()->map(fn($r) => [
                    $r->item_code, $r->item_name, $r->item_type, (int) $r->qty,
                    (int) $r->gross, (int) $r->discount, (int) $r->net, (int) $r->profit,
                ])->all(),
            ],
            'dokter' => [
                ['ID Dokter', 'Nama Dokter', 'Kunjungan', 'Line Items', 'Net', 'Profit'],
                $this->qDokter($start, $end, $placeId)->get()->map(fn($r) => [
                    $r->doc_reff_id, $r->doctor_name, (int) $r->visits, (int) $r->line_count,
                    (int) $r->net, (int) $r->profit,
                ])->all(),
            ],
            'pasien' => [
                ['ID Pasien', 'Nama Pasien', 'Kunjungan', 'Line Items', 'Total', 'Rata-rata/Kunjungan'],
                $this->qPasien($start, $end, $placeId)->get()->map(fn($r) => [
                    $r->p_id, $r->p_name, (int) $r->visits, (int) $r->line_count,
                    (int) $r->total, (int) $r->avg_visit,
                ])->all(),
            ],
            'rincian' => [
                ['Tanggal', 'No Faktur', 'Pasien', 'Dokter', 'Kode', 'Item', 'Tipe',
                 'Qty', 'Gross', 'Diskon', 'Net', 'Status Bayar'],
                $this->qRincian($start, $end, $placeId)->get()->map(fn($r) => [
                    $r->trx_date, $r->invoice_num, $r->p_name, $r->doctor, $r->item_code,
                    $r->item_name, $r->item_type, (float) $r->qty_sold, (int) $r->gross_price,
                    (int) $r->discount, (int) $r->net_sell_price, $r->payment_status,
                ])->all(),
            ],
            'piutang' => [
                ['Tanggal', 'No Faktur', 'Pasien', 'Dokter', 'Item', 'Qty', 'Nilai Belum Lunas'],
                $this->qPiutang($start, $end, $placeId)->get()->map(fn($r) => [
                    $r->trx_date, $r->invoice_num, $r->p_name, $r->doctor, $r->item_name,
                    (float) $r->qty_sold, (int) $r->net_sell_price,
                ])->all(),
            ],
            'staff' => [
                ['Staff/Username', 'Faktur', 'Kunjungan', 'Line Items', 'Total Net'],
                $this->qStaff($start, $end, $placeId)->get()->map(fn($r) => [
                    $r->username, (int) $r->invoices, (int) $r->visits,
                    (int) $r->line_count, (int) $r->net,
                ])->all(),
            ],
            default => [
                ['Tanggal', 'Line Items', 'Kunjungan', 'Net (tanpa Voucher & Service Charge)'],
                $this->buildRekap($start, $end, $placeId)['rekap']['perDay']->map(fn($r) => [
                    $r->trx_date, (int) $r->line_count, (int) $r->visits, (int) $r->net,
                ])->all(),
            ],
        };
    }
}
