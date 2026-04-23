<?php

namespace App\Http\Controllers;

use App\Models\RawCusReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function reservations()
    {
        $dateFrom = request('date_from');
        $dateTo   = request('date_to');
        $branchId = request('branch');
        $status   = request('status');
        $doctorId = request('doctor');

        $query = RawCusReservation::with(['customer', 'branch', 'doctor', 'services.docService', 'promo'])
            ->orderBy('date')
            ->orderBy('start_hour');

        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }
        if ($branchId) {
            $query->where('raw_branch_id', $branchId);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($doctorId) {
            $query->where('raw_doctor_id', $doctorId);
        }

        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch) {
            $query->where('raw_branch_id', $sessionBranch);
        }

        $reservations = $query->get();

        $label = ($dateFrom && $dateTo) ? $dateFrom . '_' . $dateTo : 'all';
        $filename = 'reservasi_' . $label . '.csv';

        return new StreamedResponse(function () use ($reservations) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Tanggal', 'Jam Mulai', 'Jam Selesai', 'Pasien', 'Telepon',
                'Dokter', 'Cabang', 'Perawatan', 'Status', 'Promo',
                'Total Reservasi', 'Keterangan Kehadiran', 'Created At'
            ]);

            foreach ($reservations as $r) {
                $services = $r->services->map(fn($s) => $s->docService?->name)->filter()->implode(', ');
                fputcsv($handle, [
                    Carbon::parse($r->date)->format('d/m/Y'),
                    substr($r->start_hour, 0, 5),
                    substr($r->end_hour, 0, 5),
                    $r->customer?->name ?? '-',
                    $r->customer?->phone ?? '-',
                    $r->doctor?->name ?? '-',
                    $r->branch?->name ?? '-',
                    $services ?: '-',
                    $r->status,
                    $r->promo?->promo_code ?? '-',
                    $r->total_reservasi ?? 0,
                    $r->keterangan_kehadiran ?? '-',
                    $r->created_at ? Carbon::parse($r->created_at)->format('d/m/Y H:i') : '-',
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function commissions()
    {
        $dateFrom = request('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo   = request('date_to', Carbon::now()->toDateString());

        $commissions = DB::table('raw_commission')
            ->join('raw_affiliate', 'raw_commission.raw_affiliate_id', '=', 'raw_affiliate.id')
            ->join('raw_cus_reservation', 'raw_commission.raw_cus_reservation_id', '=', 'raw_cus_reservation.id')
            ->leftJoin('raw_customer', 'raw_cus_reservation.raw_customer_id', '=', 'raw_customer.id')
            ->leftJoin('raw_branch', 'raw_cus_reservation.raw_branch_id', '=', 'raw_branch.id')
            ->select(
                'raw_cus_reservation.date',
                'raw_customer.name as customer_name',
                'raw_branch.name as branch_name',
                'raw_affiliate.name as affiliate_name',
                'raw_affiliate.phone as affiliate_phone',
                'raw_affiliate.bank_account_info',
                'raw_commission.amount',
                'raw_commission.status',
                'raw_commission.created_at'
            )
            ->whereBetween('raw_cus_reservation.date', [$dateFrom, $dateTo])
            ->orderBy('raw_cus_reservation.date')
            ->get();

        $filename = 'komisi_' . $dateFrom . '_' . $dateTo . '.csv';

        return new StreamedResponse(function () use ($commissions) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Tanggal Reservasi', 'Pasien', 'Cabang', 'Nama Afiliasi',
                'Telepon Afiliasi', 'Info Bank', 'Jumlah Komisi', 'Status', 'Created At'
            ]);

            foreach ($commissions as $c) {
                fputcsv($handle, [
                    Carbon::parse($c->date)->format('d/m/Y'),
                    $c->customer_name ?? '-',
                    $c->branch_name ?? '-',
                    $c->affiliate_name,
                    $c->affiliate_phone ?? '-',
                    $c->bank_account_info ?? '-',
                    $c->amount,
                    $c->status,
                    $c->created_at ? Carbon::parse($c->created_at)->format('d/m/Y H:i') : '-',
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
