<?php

namespace App\Http\Controllers;

use App\Models\RawCusReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Segmen 'export' ada di daftar $skipRbac AuthGuard, jadi RBAC dilewati
     * SEPENUHNYA untuk route ini — padahal controller ini tak punya pemeriksaan
     * apa pun. Siapa saja yang berhasil login bisa menarik CSV-nya.
     *
     * Penjaga di sini menyamakan aturannya dengan HALAMAN INDUK tempat tombol
     * export berada (pola yang sama dengan AuthGuard: cocokkan link ke
     * access_path milik grup). Tak dipakai hasMenuAccess() karena helper itu
     * hanya menelusuri 3 level, sedangkan sebagian node cuma 2 level — ia bisa
     * mengembalikan false secara diam-diam untuk orang yang sebenarnya berhak.
     */
    private function pastikanBolehLihat(string $link, string $namaHalaman): void
    {
        $boleh = DB::table('group_access as ga')
            ->join('access_path as ap', 'ap.id', '=', 'ga.access_id')
            ->where('ga.group_id', session('group_id'))
            ->where('ap.link', $link)
            ->exists();

        if (! $boleh) {
            abort(403, 'Kamu tidak punya akses ke data ' . $namaHalaman . '.');
        }
    }

    /**
     * Batas cabang. Yang tidak berhak lintas-cabang WAJIB punya cabang, dan
     * datanya dikunci ke cabang itu. Sebelumnya filter cabang hanya dipasang
     * KALAU sesi punya raw_branch_id — sehingga role kantor pusat yang tak
     * bercabang (mis. IT, Creative, Content Creator) menarik data SELURUH
     * cabang sepanjang masa, lengkap dengan nama & nomor telepon pasien.
     */
    private function batasCabang(): ?string
    {
        if (canSeeAllBranches()) {
            return null;                       // memang berhak lintas cabang
        }

        $cabang = session('raw_branch_id');
        if (! $cabang) {
            abort(403, 'Akun ini tak terikat cabang, jadi tak bisa menarik data lintas cabang.');
        }

        return $cabang;
    }

    public function reservations()
    {
        $this->pastikanBolehLihat('rawreservation', 'reservasi');
        $kunciCabang = $this->batasCabang();

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

        if ($kunciCabang) {
            $query->where('raw_branch_id', $kunciCabang);
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
        // tombolnya ada di halaman Affiliate (link menu 'rawaffiliate')
        $this->pastikanBolehLihat('rawaffiliate', 'komisi affiliate');

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
