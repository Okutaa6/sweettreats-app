<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawAffiliate;
use App\Models\RawBranch;
use App\Models\RawCusReservation;
use App\Models\RawPromo;

class ReportReferralController extends Controller
{
    public function index()
    {
        // Filter dari request
        $filterAffiliate = request('affiliate_id');
        $filterBranch    = request('branch_id');
        $filterDateFrom  = request('date_from', now()->startOfMonth()->format('Y-m-d'));
        $filterDateTo    = request('date_to', now()->format('Y-m-d'));
        $filterKlaim     = request('klaim'); // '' | '0' | '1'

        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch) {
            $filterBranch = $sessionBranch; // paksa filter dan kunci dropdown
        }

        // Query utama:
        // reservasi yang punya promo → join ke affiliate
        $query = RawCusReservation::with([
                'customer',
                'branch',
                'promo.affiliate',
                'services.docService',
            ])
            ->whereNotNull('raw_promo_id') // cuma yang pakai promo/referral
            ->orderBy('date', 'desc');

        // Filter affiliate
        if ($filterAffiliate) {
            $query->whereHas('promo', function ($q) use ($filterAffiliate) {
                $q->where('raw_affiliate_id', $filterAffiliate);
            });
        }

        // Filter cabang
        if ($filterBranch) {
            $query->where('raw_branch_id', $filterBranch);
        }

        // Filter tanggal
        if ($filterDateFrom) {
            $query->whereDate('date', '>=', $filterDateFrom);
        }
        if ($filterDateTo) {
            $query->whereDate('date', '<=', $filterDateTo);
        }

        // Filter status klaim
        if ($filterKlaim !== null && $filterKlaim !== '') {
            $query->where('promo_klaim', $filterKlaim);
        }

        $perPage      = $this->perPage ?? 20;
        $reservations = $query->paginate($perPage)->withQueryString();

        // Total komisi dari hasil filter
        $totalKomisi = $query->get()->sum(function ($r) {
            return $r->commission->amount ?? 0;
        });

        // Data dropdown filter
        $affiliates = RawAffiliate::orderBy('name')->get();
        $branches   = $sessionBranch
            ? RawBranch::where('id', $sessionBranch)->get()
            : RawBranch::orderBy('name')->get();

        return view('crud.report-referral.index', compact(
            'reservations',
            'affiliates',
            'branches',
            'filterAffiliate',
            'filterBranch',
            'filterDateFrom',
            'filterDateTo',
            'filterKlaim',
            'totalKomisi'
        ));
    }
}